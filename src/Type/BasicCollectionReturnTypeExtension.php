<?php

namespace DragonByteTech\PHPStanXenForo\Type;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

/**
 * Manager::getBasicCollection() is documented as a raw ArrayCollection, which drops the entity
 * type of the array it wraps.
 */
final class BasicCollectionReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
	public function getClass(): string
	{
		return 'XF\Mvc\Entity\Manager';
	}

	public function isMethodSupported(MethodReflection $methodReflection): bool
	{
		return $methodReflection->getName() === 'getBasicCollection';
	}

	public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type
	{
		$entity = new ObjectType('XF\Mvc\Entity\Entity');

		$args = $methodCall->getArgs();
		$valueType = isset($args[0]) ? $scope->getType($args[0]->value)->getIterableValueType() : $entity;
		if (!$entity->isSuperTypeOf($valueType)->yes())
		{
			$valueType = $entity;
		}

		return new GenericObjectType('XF\Mvc\Entity\ArrayCollection', [$valueType]);
	}
}
