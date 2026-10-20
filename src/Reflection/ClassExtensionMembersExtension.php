<?php

namespace DragonByteTech\PHPStanXenForo\Reflection;

use DragonByteTech\PHPStanXenForo\AddOnContext;
use PHPStan\Analyser\OutOfClassScope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;
use PHPStan\Reflection\PropertiesClassReflectionExtension;
use PHPStan\Reflection\PropertyReflection;
use PHPStan\Reflection\ReflectionProvider;

/**
 * At runtime XenForo instantiates the extended class, so members an add-on adds through a
 * class extension are available wherever the extended class is typed.
 */
final class ClassExtensionMembersExtension implements MethodsClassReflectionExtension, PropertiesClassReflectionExtension
{
	/**
	 * Extensions of one class are siblings, so resolving a member on one can lead back to it
	 * through the shared parent.
	 *
	 * @var array<string, true>
	 */
	private array $resolving = [];

	public function __construct(
		private readonly ReflectionProvider $reflectionProvider
	)
	{
	}

	public function hasMethod(ClassReflection $classReflection, string $methodName): bool
	{
		return $this->findExtension(
			$classReflection,
			'method:' . $methodName,
			static fn (ClassReflection $extension): bool => $extension->hasNativeMethod($methodName)
		) !== null;
	}

	public function getMethod(ClassReflection $classReflection, string $methodName): MethodReflection
	{
		$extension = $this->findExtension(
			$classReflection,
			'method:' . $methodName,
			static fn (ClassReflection $extension): bool => $extension->hasNativeMethod($methodName)
		);
		if ($extension === null)
		{
			throw new \LogicException("No class extension declares {$classReflection->getName()}::{$methodName}()");
		}

		return $extension->getNativeMethod($methodName);
	}

	public function hasProperty(ClassReflection $classReflection, string $propertyName): bool
	{
		return $this->findExtension(
			$classReflection,
			'property:' . $propertyName,
			static fn (ClassReflection $extension): bool => $extension->hasInstanceProperty($propertyName)
		) !== null;
	}

	public function getProperty(ClassReflection $classReflection, string $propertyName): PropertyReflection
	{
		$extension = $this->findExtension(
			$classReflection,
			'property:' . $propertyName,
			static fn (ClassReflection $extension): bool => $extension->hasInstanceProperty($propertyName)
		);
		if ($extension === null)
		{
			throw new \LogicException("No class extension declares {$classReflection->getName()}::\${$propertyName}");
		}

		return $extension->getInstanceProperty($propertyName, new OutOfClassScope());
	}

	/**
	 * @param callable(ClassReflection): bool $declaresMember
	 */
	private function findExtension(ClassReflection $classReflection, string $member, callable $declaresMember): ?ClassReflection
	{
		$context = AddOnContext::get();

		$classNames = [$classReflection->getName(), ...$classReflection->getParentClassesNames()];
		foreach ($classNames AS $className)
		{
			foreach ($context->getExtensionsOf($className) AS $extensionClass)
			{
				if (
					$extensionClass === $classReflection->getName()
					|| $classReflection->isSubclassOf($extensionClass)
					|| !$this->reflectionProvider->hasClass($extensionClass)
				)
				{
					continue;
				}

				$key = $extensionClass . '::' . $member;
				if (isset($this->resolving[$key]))
				{
					continue;
				}

				$this->resolving[$key] = true;
				try
				{
					$extension = $this->reflectionProvider->getClass($extensionClass);
					if ($declaresMember($extension))
					{
						return $extension;
					}
				}
				finally
				{
					unset($this->resolving[$key]);
				}
			}
		}

		return null;
	}
}
