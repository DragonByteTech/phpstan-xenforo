<?php

// The XenForo 2.3 symbols that src/ and bootstrap.php use, with XenForo's own signatures, so the
// extension can be analysed without an installation. Scanned by phpstan.neon.dist, never loaded.
// Where XenForo declares no type, the stub adds the loosest one its code allows: Structure's
// arrays as add-ons fill them, and the string that the string helpers always return.

namespace
{
	class XF
	{
		public static function bootstrap(string $rootDirectory): void
		{
		}

		public static function startAutoloader()
		{
		}

		public static function setupClassAliases(): void
		{
		}

		public static function getClassForAlias(string $alias, bool $useFileExistence = true): string
		{
			return $alias;
		}

		/**
		 * @return string
		 */
		public static function stringToClass($string, $formatter, $defaultInfix = null)
		{
			return $string;
		}
	}
}

namespace XF\Mvc\Entity
{
	abstract class Entity
	{
		public const INT                   =  0x0001;
		public const UINT                  =  0x0002;
		public const FLOAT                 =  0x0003;
		public const BOOL                  = 0x10004;
		public const STR                   =  0x0005;
		public const BINARY                =  0x0006;
		public const SERIALIZED            = 0x10007;
		public const SERIALIZED_ARRAY      = 0x10008;
		public const JSON                  = 0x10009;
		public const JSON_ARRAY            = 0x10010;
		public const LIST_LINES            = 0x10011;
		public const LIST_COMMA            = 0x10012;
		public const LIST_ARRAY            = 0x10013;

		public const TO_ONE  = 1;
		public const TO_MANY = 2;

		/**
		 * @param Structure $structure
		 * @return Structure
		 * @throws \LogicException
		 */
		public static function getStructure(Structure $structure)
		{
			return $structure;
		}
	}

	abstract class Finder
	{
	}

	class Structure
	{
		public $table;
		public $primaryKey;
		/** @var array<string, array<string, mixed>> */
		public $columns = [];
		/** @var array<string, array<string, mixed>> */
		public $relations = [];
		/** @var array<string, bool|array<string, mixed>> */
		public $getters = [];
	}
}

namespace XF\Util
{
	class Php
	{
		/**
		 * @return string
		 */
		public static function camelCase($string, $glue = '_')
		{
			return $string;
		}
	}
}
