<?php

namespace DragonByteTech\PHPStanXenForo\Ignore;

use DragonByteTech\PHPStanXenForo\AddOnContext;
use PhpParser\Node;
use PHPStan\Analyser\Error;
use PHPStan\Analyser\IgnoreErrorExtension;
use PHPStan\Analyser\Scope;

use function preg_match;

/**
 * Docblock conventions common in XenForo and add-on code. These would hide real errors in
 * other projects, so they apply only inside a XenForo installation.
 */
final class XenForoDocblockIgnoreExtension implements IgnoreErrorExtension
{
	private const PATTERNS = [
		// `@extends \XF\Entity\User` on class extensions, for PhpStorm; PHPStan expects generics.
		'phpDoc.parseError' => '/^PHPDoc tag @extends has invalid value \([^<]+\): Unexpected token "\\\\n \*?", expected \'<\'/',

		// `@param null $foo` for optional parameters that default to null.
		'argument.type' => '/^Parameter .+ expects null, .+ given\.$/',
	];

	public function shouldIgnore(Error $error, Node $node, Scope $scope): bool
	{
		$pattern = self::PATTERNS[$error->getIdentifier() ?? ''] ?? null;

		return $pattern !== null
			&& AddOnContext::get()->isXenForo()
			&& preg_match($pattern, $error->getMessage()) === 1;
	}
}
