<?php

namespace DragonByteTech\PHPStanXenForo\Type;

use DragonByteTech\PHPStanXenForo\ShortNameResolver;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ExtendedMethodReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\ExpressionTypeResolverExtension;
use PHPStan\Type\Generic\GenericClassStringType;
use PHPStan\Type\Generic\TemplateType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\Type;

use function array_slice, count;

/**
 * XenForo factories are typed with class-string<T> templates, so legacy short names such as
 * 'XF:User' would otherwise resolve to the template bound rather than the named class.
 */
final class ShortNameTypeResolverExtension implements ExpressionTypeResolverExtension
{
	public function __construct(
		private readonly ReflectionProvider $reflectionProvider,
		private readonly ShortNameResolver $shortNameResolver
	)
	{
	}

	public function getType(Expr $expr, Scope $scope): ?Type
	{
		if (
			!($expr instanceof MethodCall || $expr instanceof StaticCall)
			|| !$expr->name instanceof Identifier
			|| $expr->isFirstClassCallable()
		)
		{
			return null;
		}

		$args = $expr->getArgs();
		if (!$args || $args[0]->unpack || $args[0]->name !== null)
		{
			return null;
		}

		$shortNames = $scope->getType($args[0]->value)->getConstantStrings();
		if (count($shortNames) !== 1 || !ShortNameResolver::isShortName($shortNames[0]->getValue()))
		{
			return null;
		}

		$method = $this->getMethod($expr, $scope);
		if ($method === null)
		{
			return null;
		}

		$variants = $method->getVariants();
		$parameter = $variants[0]->getParameters()[0] ?? null;
		$parameterType = $parameter?->getType();
		if (
			!$parameterType instanceof GenericClassStringType
			|| !$parameterType->getGenericType() instanceof TemplateType
		)
		{
			return null;
		}

		$className = $this->shortNameResolver->resolve(
			$shortNames[0]->getValue(),
			$parameterType->getGenericType()->getBound()
		);
		if ($className === null)
		{
			return null;
		}

		$argTypes = [new GenericClassStringType(new ObjectType($className))];
		foreach (array_slice($args, 1) AS $arg)
		{
			$argTypes[] = $scope->getType($arg->value);
		}

		return ParametersAcceptorSelector::selectFromTypes($argTypes, $variants, false)->getReturnType();
	}

	private function getMethod(MethodCall|StaticCall $expr, Scope $scope): ?ExtendedMethodReflection
	{
		$methodName = $expr->name instanceof Identifier ? $expr->name->toString() : null;
		if ($methodName === null)
		{
			return null;
		}

		if ($expr instanceof MethodCall)
		{
			return $scope->getMethodReflection($scope->getType($expr->var), $methodName);
		}

		if (!$expr->class instanceof Name)
		{
			return null;
		}

		$className = $scope->resolveName($expr->class);
		if (!$this->reflectionProvider->hasClass($className))
		{
			return null;
		}

		$class = $this->reflectionProvider->getClass($className);

		return $class->hasMethod($methodName) ? $class->getMethod($methodName, $scope) : null;
	}
}
