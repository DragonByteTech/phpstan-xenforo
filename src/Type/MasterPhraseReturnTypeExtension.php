<?php

namespace DragonByteTech\PHPStanXenForo\Type;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeTraverser;

/**
 * Entities such as StyleProperty document getMasterPhrase() as returning XF\Phrase because
 * they import that class, but the method returns the XF\Entity\Phrase entity.
 */
final class MasterPhraseReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
	public function getClass(): string
	{
		return 'XF\Mvc\Entity\Entity';
	}

	public function isMethodSupported(MethodReflection $methodReflection): bool
	{
		return $methodReflection->getName() === 'getMasterPhrase';
	}

	public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type
	{
		$returnType = ParametersAcceptorSelector::selectFromArgs(
			$scope,
			$methodCall->getArgs(),
			$methodReflection->getVariants()
		)->getReturnType();

		return TypeTraverser::map($returnType, static function (Type $type, callable $traverse): Type
		{
			if ($type instanceof ObjectType && $type->getClassName() === 'XF\Phrase')
			{
				return new ObjectType('XF\Entity\Phrase');
			}

			return $traverse($type);
		});
	}
}
