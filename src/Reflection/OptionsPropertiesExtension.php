<?php

namespace DragonByteTech\PHPStanXenForo\Reflection;

use DragonByteTech\PHPStanXenForo\AddOnContext;
use PHPStan\PhpDoc\TypeStringResolver;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\PropertiesClassReflectionExtension;
use PHPStan\Reflection\PropertyReflection;
use PHPStan\Type\FloatType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\MixedType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\TypeTraverser;

use function file_get_contents, is_file, preg_match_all, trim;

/**
 * Options are read as properties of XF\Options. Types come from the option hint files
 * XenForo writes to _output/option_hint.php; options without a hint are mixed.
 */
final class OptionsPropertiesExtension implements PropertiesClassReflectionExtension
{
	/**
	 * @var array<string, Type>|null
	 */
	private ?array $types = null;

	public function __construct(
		private readonly TypeStringResolver $typeStringResolver,
		private readonly ?string $coreOptionsStub = null
	)
	{
	}

	public function hasProperty(ClassReflection $classReflection, string $propertyName): bool
	{
		return $classReflection->is('XF\Options') || $classReflection->isSubclassOf('XF\Options');
	}

	public function getProperty(ClassReflection $classReflection, string $propertyName): PropertyReflection
	{
		return new MagicPropertyReflection(
			$classReflection,
			$this->getTypes()[$propertyName] ?? new MixedType()
		);
	}

	/**
	 * @return array<string, Type>
	 */
	private function getTypes(): array
	{
		if ($this->types !== null)
		{
			return $this->types;
		}

		$context = AddOnContext::get();

		// xf-dev:generate-options-stub writes Options.stub to the working directory by default.
		$files = [];
		$coreOptionsStub = $this->coreOptionsStub
			?? ($context->rootDir !== null ? $context->rootDir . '/Options.stub' : null);
		if ($coreOptionsStub !== null)
		{
			$files[] = $coreOptionsStub;
		}
		foreach ($context->getOptionHintFiles() AS $file)
		{
			$files[] = $file;
		}

		$this->types = [];
		foreach ($files AS $file)
		{
			if (!is_file($file))
			{
				continue;
			}

			preg_match_all(
				'/^\s*\*\s*@property(?:-read)?\s+(.+?)\s+\$(\w+)/m',
				(string) file_get_contents($file),
				$matches,
				\PREG_SET_ORDER
			);

			foreach ($matches AS [, $typeString, $optionId])
			{
				try
				{
					$this->types[$optionId] = $this->widenSubOptionStrings(
						$this->typeStringResolver->resolve(trim($typeString))
					);
				}
				catch (\Throwable)
				{
					$this->types[$optionId] = new MixedType();
				}
			}
		}

		return $this->types;
	}

	/**
	 * Sub-option values are stored as submitted, so hints type numeric settings such as
	 * watchAlertActiveOnly['days'] as string, although XenForo does arithmetic on them.
	 */
	private function widenSubOptionStrings(Type $type): Type
	{
		return TypeTraverser::map($type, static function (Type $type, callable $traverse): Type
		{
			if (!$type->isConstantArray()->yes())
			{
				return $traverse($type);
			}

			return TypeTraverser::map($type, static function (Type $type, callable $traverse): Type
			{
				if ($type instanceof StringType)
				{
					return TypeCombinator::union($type, new IntegerType(), new FloatType());
				}

				return $traverse($type);
			});
		});
	}
}
