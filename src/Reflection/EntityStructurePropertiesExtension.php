<?php

namespace DragonByteTech\PHPStanXenForo\Reflection;

use PHPStan\PhpDoc\TypeStringResolver;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\ParametersAcceptorSelector;
use PHPStan\Reflection\PropertiesClassReflectionExtension;
use PHPStan\Reflection\PropertyReflection;
use PHPStan\Type\MixedType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Util\Php;

use function array_key_exists, is_array, is_string, str_ends_with, ucfirst;

/**
 * Resolves entity columns, getters and relations that have no @property tag, following the
 * same rules as xf-dev:entity-class-properties.
 */
final class EntityStructurePropertiesExtension implements PropertiesClassReflectionExtension
{
	/**
	 * @var array<string, array<string, array{type: Type, writable: bool, writableType?: Type}>|null>
	 */
	private array $properties = [];

	public function __construct(
		private readonly TypeStringResolver $typeStringResolver
	)
	{
	}

	public function hasProperty(ClassReflection $classReflection, string $propertyName): bool
	{
		if (!$classReflection->is(Entity::class) && !$classReflection->isSubclassOf(Entity::class))
		{
			return false;
		}

		$properties = $this->getStructureProperties($classReflection);

		return $properties === null || isset($properties[$propertyName]);
	}

	public function getProperty(ClassReflection $classReflection, string $propertyName): PropertyReflection
	{
		$property = $this->getStructureProperties($classReflection)[$propertyName] ?? null;

		return new MagicPropertyReflection(
			$classReflection,
			$property['type'] ?? new MixedType(),
			$property['writable'] ?? true,
			$property['writableType'] ?? null
		);
	}

	/**
	 * @return array<string, array{type: Type, writable: bool, writableType?: Type}>|null null when the structure is unknown
	 */
	private function getStructureProperties(ClassReflection $classReflection): ?array
	{
		$className = $classReflection->getName();
		if (array_key_exists($className, $this->properties))
		{
			return $this->properties[$className];
		}

		$this->properties[$className] = null;

		if ($classReflection->isAbstract() || $classReflection->isInterface())
		{
			return null;
		}

		try
		{
			/** @var class-string<Entity> $className */
			$structure = $className::getStructure(new Structure());
		}
		catch (\Throwable)
		{
			return null;
		}

		$properties = [];

		$getters = [];
		foreach ($structure->getters AS $getter => $definition)
		{
			$methodName = is_array($definition) && is_string($definition['getter'] ?? null)
				? $definition['getter']
				: 'get' . ucfirst(Php::camelCase($getter));
			if (!$classReflection->hasNativeMethod($methodName))
			{
				continue;
			}

			$method = $classReflection->getNativeMethod($methodName);
			$getters[$getter] = ParametersAcceptorSelector::combineAcceptors($method->getVariants())->getReturnType();
		}

		foreach ($structure->columns AS $column => $definition)
		{
			$typeString = !empty($definition['typeHint'])
				? $definition['typeHint']
				: self::getColumnTypes()[$definition['type']] ?? 'mixed';
			$type = $this->resolveType($typeString);
			if (!empty($definition['nullable']) && !str_ends_with($typeString, '|null'))
			{
				$type = TypeCombinator::addNull($type);
			}

			// A getter with the same name shadows the column, leaving the raw value behind the
			// bypass suffix. Censored columns expose the uncensored value the same way.
			if (isset($getters[$column]) || !empty($definition['censor']))
			{
				$properties[$column . '_'] = ['type' => $type, 'writable' => true];
			}

			if (!isset($getters[$column]))
			{
				$properties[$column] = ['type' => $type, 'writable' => true];
			}
		}

		// Entity::__set() writes the column a getter shadows, so writes take the column's type.
		foreach ($getters AS $getter => $type)
		{
			$column = $properties[$getter . '_'] ?? null;
			$properties[$getter] = $column !== null
				? ['type' => $type, 'writable' => true, 'writableType' => $column['type']]
				: ['type' => $type, 'writable' => false];
		}

		foreach ($structure->relations AS $relation => $definition)
		{
			if (!is_string($definition['entity'] ?? null))
			{
				continue;
			}

			$entityClass = '\\' . \XF::stringToClass($definition['entity'], '%s\Entity\%s');
			$type = $this->resolveType(
				($definition['type'] ?? null) === Entity::TO_MANY
					? '\XF\Mvc\Entity\AbstractCollection<' . $entityClass . '>'
					: $entityClass . '|null'
			);

			$properties[isset($getters[$relation]) ? $relation . '_' : $relation] = [
				'type' => $type,
				'writable' => false,
			];
		}

		return $this->properties[$className] = $properties;
	}

	private function resolveType(string $typeString): Type
	{
		try
		{
			return $this->typeStringResolver->resolve($typeString);
		}
		catch (\Throwable)
		{
			return new MixedType();
		}
	}

	/**
	 * Mirrors XF\Cli\Command\Development\EntityClassProperties::getEntityTypeMap(). Not a
	 * constant: PHPStan instantiates the extension before the bootstrap file loads XenForo.
	 *
	 * @return array<int, string>
	 */
	private static function getColumnTypes(): array
	{
		return [
			Entity::INT => 'int',
			Entity::UINT => 'int',
			Entity::FLOAT => 'float',
			Entity::BOOL => 'bool',
			Entity::STR => 'string',
			Entity::BINARY => 'string',
			Entity::SERIALIZED => 'array|bool',
			Entity::SERIALIZED_ARRAY => 'array',
			Entity::JSON => 'scalar|array|null',
			Entity::JSON_ARRAY => 'array|null',
			Entity::LIST_LINES => 'array',
			Entity::LIST_COMMA => 'array',
			Entity::LIST_ARRAY => 'array',
		];
	}
}
