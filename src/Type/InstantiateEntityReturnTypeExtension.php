<?php

namespace DragonByteTech\PHPStanXenForo\Type;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;

use function count, is_int;

/**
 * Manager::instantiateEntity() documents its result as nullable whenever $options matches
 * `int-mask<INSTANTIATE_PROXIED>`, which includes the default 0. It only returns null when the
 * PROXIED or ALLOW_INVALID bit is set; otherwise it returns the entity or throws.
 */
final class InstantiateEntityReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
	/**
	 * Manager::INSTANTIATE_ALLOW_INVALID | Manager::INSTANTIATE_PROXIED
	 */
	private const NULLABLE_OPTIONS = 0x1 | 0x2;

	public function getClass(): string
	{
		return 'XF\Mvc\Entity\Manager';
	}

	public function isMethodSupported(MethodReflection $methodReflection): bool
	{
		return $methodReflection->getName() === 'instantiateEntity';
	}

	public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type
	{
		$returnType = ParametersAcceptorSelector::selectFromArgs(
			$scope,
			$methodCall->getArgs(),
			$methodReflection->getVariants()
		)->getReturnType();

		$args = $methodCall->getArgs();
		if (!isset($args[3]))
		{
			return TypeCombinator::removeNull($returnType);
		}

		$options = $scope->getType($args[3]->value)->getConstantScalarValues();
		if (count($options) === 1 && is_int($options[0]) && ($options[0] & self::NULLABLE_OPTIONS) === 0)
		{
			return TypeCombinator::removeNull($returnType);
		}

		return $returnType;
	}
}
