<?php

namespace DragonByteTech\PHPStanXenForo\Type;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\NeverType;
use PHPStan\Type\Type;

/**
 * Manager::getEmptyCollection() is documented as a raw ArrayCollection, which is read as a
 * collection of any entity and so cannot be returned where a specific entity type is declared.
 */
final class EmptyCollectionReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
	public function getClass(): string
	{
		return 'XF\Mvc\Entity\Manager';
	}

	public function isMethodSupported(MethodReflection $methodReflection): bool
	{
		return $methodReflection->getName() === 'getEmptyCollection';
	}

	public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type
	{
		return new GenericObjectType('XF\Mvc\Entity\ArrayCollection', [new NeverType(true)]);
	}
}
