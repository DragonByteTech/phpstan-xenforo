<?php

namespace DragonByteTech\PHPStanXenForo\Ignore;

use DragonByteTech\PHPStanXenForo\ShortNameResolver;
use PhpParser\Node;
use PHPStan\Analyser\Error;
use PHPStan\Analyser\IgnoreErrorExtension;
use PHPStan\Analyser\Scope;
use PHPStan\Type\ObjectType;

use function preg_match;

/**
 * XenForo factories still accept legacy short names such as 'XF:User' where they declare
 * class-string<T>. The argument is accepted only when it names a class that exists.
 */
final class ShortNameArgumentIgnoreExtension implements IgnoreErrorExtension
{
	public function __construct(
		private readonly ShortNameResolver $shortNameResolver
	)
	{
	}

	public function shouldIgnore(Error $error, Node $node, Scope $scope): bool
	{
		if (
			$error->getIdentifier() !== 'argument.type'
			|| !$node instanceof Node\Expr\CallLike
			|| $node->isFirstClassCallable()
			|| !preg_match(
				'/^Parameter #(\d+) \$(\w+) .* expects class-string<([\w\\\\]+)>(?:\|null)?, .+ given\.$/',
				$error->getMessage(),
				$matches
			)
		)
		{
			return false;
		}

		$argument = null;
		foreach ($node->getArgs() AS $position => $arg)
		{
			if ($arg->name !== null ? $arg->name->toString() === $matches[2] : $position + 1 === (int) $matches[1])
			{
				$argument = $arg;
				break;
			}
		}

		if ($argument === null)
		{
			return false;
		}

		$shortNames = $scope->getType($argument->value)->getConstantStrings();
		if (!$shortNames)
		{
			return false;
		}

		$bound = new ObjectType($matches[3]);
		foreach ($shortNames AS $shortName)
		{
			if (
				!ShortNameResolver::isShortName($shortName->getValue())
				|| $this->shortNameResolver->resolve($shortName->getValue(), $bound) === null
			)
			{
				return false;
			}
		}

		return true;
	}
}
