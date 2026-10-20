<?php

namespace DragonByteTech\PHPStanXenForo\Ignore;

use PhpParser\Node;
use PHPStan\Analyser\Error;
use PHPStan\Analyser\IgnoreErrorExtension;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;

use function preg_match;

/**
 * When a getter shadows a column, xf-dev:entity-class-properties documents the property with
 * the getter's type, but Entity::__set() still writes the raw column (also documented with a
 * trailing underscore), so assignments are typed by the column, not the getter.
 */
final class EntityColumnAssignIgnoreExtension implements IgnoreErrorExtension
{
	public function __construct(
		private readonly ReflectionProvider $reflectionProvider
	)
	{
	}

	public function shouldIgnore(Error $error, Node $node, Scope $scope): bool
	{
		if (
			$error->getIdentifier() !== 'assign.propertyType'
			|| !preg_match('/^Property ([\w\\\\]+)::\$(\w+) \(/', $error->getMessage(), $matches)
			|| !$this->reflectionProvider->hasClass($matches[1])
		)
		{
			return false;
		}

		$class = $this->reflectionProvider->getClass($matches[1]);

		return $class->isSubclassOf('XF\Mvc\Entity\Entity')
			&& $class->hasInstanceProperty($matches[2] . '_');
	}
}
