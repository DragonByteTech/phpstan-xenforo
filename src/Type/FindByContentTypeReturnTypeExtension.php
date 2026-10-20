<?php

namespace DragonByteTech\PHPStanXenForo\Type;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

/**
 * XF\App::findByContentType() documents its collection result as XF\AbstractCollection, a
 * class that does not exist, so every call on the result is reported against an unknown class.
 */
final class FindByContentTypeReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
	public function getClass(): string
	{
		return 'XF\App';
	}

	public function isMethodSupported(MethodReflection $methodReflection): bool
	{
		return $methodReflection->getName() === 'findByContentType';
	}

	public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type
	{
		$entity = new ObjectType('XF\Mvc\Entity\Entity');
		$collection = new GenericObjectType('XF\Mvc\Entity\AbstractCollection', [$entity]);
		$single = TypeCombinator::addNull($entity);

		$args = $methodCall->getArgs();
		$isArray = isset($args[1]) ? $scope->getType($args[1]->value)->isArray() : null;

		if ($isArray?->yes())
		{
			return $collection;
		}

		if ($isArray?->no())
		{
			return $single;
		}

		return TypeCombinator::union($collection, $single);
	}
}
