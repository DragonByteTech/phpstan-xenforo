<?php

namespace DragonByteTech\PHPStanXenForo\Type;

use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\DynamicMethodReturnTypeExtension;
use PHPStan\Type\Type;

use function in_array;

/**
 * Finder's fluent methods return $this but are documented as returning Finder, or not at all,
 * which drops the finder subclass and its entity type from the rest of the chain.
 */
final class FinderFluentReturnTypeExtension implements DynamicMethodReturnTypeExtension
{
	/**
	 * Every XF\Mvc\Entity\Finder method that only returns $this, as of XenForo 2.3.
	 */
	private const FLUENT_METHODS = [
		'exists',
		'indexHint',
		'limit',
		'limitByPage',
		'offset',
		'order',
		'resetOrder',
		'resetWhere',
		'setDefaultOrder',
		'setParentFinder',
		'where',
		'whereAddOnActive',
		'whereId',
		'whereIds',
		'whereIf',
		'whereImpossible',
		'whereOr',
		'whereSql',
		'with',
		'withEntity',
	];

	public function getClass(): string
	{
		return 'XF\Mvc\Entity\Finder';
	}

	public function isMethodSupported(MethodReflection $methodReflection): bool
	{
		return $methodReflection->getDeclaringClass()->getName() === 'XF\Mvc\Entity\Finder'
			&& in_array($methodReflection->getName(), self::FLUENT_METHODS, true);
	}

	public function getTypeFromMethodCall(MethodReflection $methodReflection, MethodCall $methodCall, Scope $scope): Type
	{
		return $scope->getType($methodCall->var);
	}
}
