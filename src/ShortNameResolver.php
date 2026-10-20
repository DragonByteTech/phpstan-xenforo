<?php

namespace DragonByteTech\PHPStanXenForo;

use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

use function count, explode, preg_match, str_contains, str_ends_with;

/**
 * Resolves legacy short names such as 'XF:User' the way XenForo's factories do, given the
 * class the factory's class-string template is bound to.
 */
final class ShortNameResolver
{
	/**
	 * Template bound => [namespace, class name suffix].
	 */
	private const FORMATS = [
		'XF\Mvc\Entity\Entity' => ['Entity', ''],
		'XF\Mvc\Entity\Finder' => ['Finder', 'Finder'],
		'XF\Mvc\Entity\Repository' => ['Repository', 'Repository'],
		'XF\Service\AbstractService' => ['Service', 'Service'],
		'XF\ControllerPlugin\AbstractPlugin' => ['ControllerPlugin', 'Plugin'],
		'XF\Validator\AbstractValidator' => ['Validator', ''],
		'XF\Criteria\AbstractCriteria' => ['Criteria', 'Criteria'],
	];

	public function __construct(
		private readonly ReflectionProvider $reflectionProvider
	)
	{
	}

	/**
	 * 'Vendor\AddOn:Name' and 'Vendor\AddOn:Infix:Name', or a bare validator name such as 'Email'.
	 */
	public static function isShortName(string $value): bool
	{
		return (bool) preg_match('/^(?:[A-Za-z_][\w\\\\]*:(?:\w+:)?[\w\\\\]+|\w+)$/', $value);
	}

	public function resolve(string $shortName, Type $bound): ?string
	{
		foreach (self::FORMATS AS $baseClass => [$namespace, $suffix])
		{
			if (!(new ObjectType($baseClass))->isSuperTypeOf($bound)->yes())
			{
				continue;
			}

			if (!str_contains($shortName, ':'))
			{
				// XF\App's validator factory treats bare names as core validators.
				if ($namespace === 'Validator')
				{
					$shortName = 'XF:' . $shortName;
				}
				else
				{
					return null;
				}
			}

			$parts = explode(':', $shortName, 3);
			$className = count($parts) === 3
				? "{$parts[0]}\\{$parts[1]}\\{$namespace}\\{$parts[2]}"
				: "{$parts[0]}\\{$namespace}\\{$parts[1]}";

			if (
				$suffix !== ''
				&& !str_ends_with($className, $suffix)
				&& $this->reflectionProvider->hasClass($className . $suffix)
			)
			{
				return $className . $suffix;
			}

			if ($this->reflectionProvider->hasClass($className))
			{
				return $className;
			}

			// Finders are optional: XenForo uses the base finder for entities without one.
			return $baseClass === 'XF\Mvc\Entity\Finder' ? $baseClass : null;
		}

		return null;
	}
}
