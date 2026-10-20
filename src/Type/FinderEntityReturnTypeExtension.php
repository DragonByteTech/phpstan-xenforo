<?php

namespace DragonByteTech\PHPStanXenForo\Type;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

use function count, in_array, preg_replace, str_contains, str_replace;

/**
 * Finders that do not declare `@extends Finder<Entity>`, as is common in third-party add-ons,
 * fetch untyped entities. XenForo pairs each finder with the entity of the same name, so the
 * entity is derived from the finder's class name.
 */
final class FinderEntityReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
	private const FINDER = 'XF\Mvc\Entity\Finder';
	private const ENTITY = 'XF\Mvc\Entity\Entity';

	public function __construct(
		private readonly ReflectionProvider $reflectionProvider
	)
	{
	}

	public function getClass(): string
	{
		return self::FINDER;
	}

	public function isMethodSupported(MethodReflection $methodReflection): bool
	{
		return in_array($methodReflection->getName(), ['fetch', 'fetchOne'], true);
	}

	public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type
	{
		$declaredType = ParametersAcceptorSelector::selectFromArgs(
			$scope,
			$methodCall->getArgs(),
			$methodReflection->getVariants()
		)->getReturnType();

		$entityClass = $this->getEntityClass($scope->getType($methodCall->var));
		if ($entityClass === null)
		{
			return $declaredType;
		}

		$entity = new ObjectType($entityClass);

		return $methodReflection->getName() === 'fetchOne'
			? TypeCombinator::addNull($entity)
			: new GenericObjectType('XF\Mvc\Entity\AbstractCollection', [$entity]);
	}

	private function getEntityClass(Type $finderType): ?string
	{
		$classReflections = $finderType->getObjectClassReflections();
		if (count($classReflections) !== 1)
		{
			return null;
		}

		$finder = $classReflections[0];
		$ancestor = $finder->getAncestorWithClassName(self::FINDER);
		$entityType = $ancestor?->getActiveTemplateTypeMap()->getType('T');
		if ($entityType !== null && !$entityType->equals(new ObjectType(self::ENTITY)))
		{
			return null;
		}

		$finderClass = $finder->getName();
		if (!str_contains($finderClass, '\\Finder\\'))
		{
			return null;
		}

		$entityClass = preg_replace('/Finder$/', '', str_replace('\\Finder\\', '\\Entity\\', $finderClass));
		if (
			$entityClass === null
			|| !$this->reflectionProvider->hasClass($entityClass)
			|| !$this->reflectionProvider->getClass($entityClass)->isSubclassOf(self::ENTITY)
		)
		{
			return null;
		}

		return $entityClass;
	}
}
