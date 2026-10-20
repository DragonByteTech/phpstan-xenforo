<?php

namespace DragonByteTech\PHPStanXenForo\Ignore;

use DragonByteTech\PHPStanXenForo\AddOnContext;
use PhpParser\Node;
use PHPStan\Analyser\Error;
use PHPStan\Analyser\IgnoreErrorExtension;
use PHPStan\Analyser\Scope;

use function array_diff, explode, ltrim, preg_match, preg_replace_callback, str_contains, strpos, substr;

/**
 * Add-ons type against their own extension of a class, but values arrive typed as the class
 * they extend. XenForo always instantiates the extended class, so these mismatches cannot
 * happen at runtime.
 *
 * Errors carry no type objects, so the expected and given types are read from the message.
 */
final class ExtensionTypeMismatchIgnoreExtension implements IgnoreErrorExtension
{
	private const PATTERNS = [
		'argument.type' => '/ expects (.+) given\.$/',
		'parameterByRef.type' => '/ expects (.+) given\.$/',
		'return.type' => '/ should return (.+?) but returns (.+)\.$/',
		'assign.propertyType' => '/ \((.+?)\) does not accept (.+)\.$/',
	];

	public function shouldIgnore(Error $error, Node $node, Scope $scope): bool
	{
		$pattern = self::PATTERNS[$error->getIdentifier() ?? ''] ?? null;
		if ($pattern === null || !preg_match($pattern, $error->getMessage(), $matches))
		{
			return false;
		}

		if (isset($matches[2]))
		{
			return $this->isExtensionMismatch($matches[1], $matches[2]);
		}

		// "expects A, B given" is ambiguous when either type contains ", ", so try every split.
		$pair = $matches[1];
		$offset = 0;
		while (($position = strpos($pair, ', ', $offset)) !== false)
		{
			if ($this->isExtensionMismatch(substr($pair, 0, $position), substr($pair, $position + 2)))
			{
				return true;
			}

			$offset = $position + 2;
		}

		return false;
	}

	private function isExtensionMismatch(string $expected, string $given): bool
	{
		$context = AddOnContext::get();

		$replaced = false;
		$given = preg_replace_callback(
			'/\\\\?[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)+/',
			static function (array $match) use ($context, $expected, &$replaced): string
			{
				foreach ($context->getExtensionsOf(ltrim($match[0], '\\')) AS $extension)
				{
					if (str_contains($expected, $extension))
					{
						$replaced = true;

						return $extension;
					}
				}

				return $match[0];
			},
			$given
		);

		if (!$replaced || $given === null)
		{
			return false;
		}

		if ($given === $expected)
		{
			return true;
		}

		if (str_contains($expected . $given, '<'))
		{
			return false;
		}

		return array_diff(explode('|', $given), explode('|', $expected)) === [];
	}
}
