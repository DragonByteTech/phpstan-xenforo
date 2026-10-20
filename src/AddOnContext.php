<?php

namespace DragonByteTech\PHPStanXenForo;

use function array_filter, array_key_exists, array_keys, array_shift, array_slice, basename, class_exists, count, dirname, explode, file_get_contents, getcwd, getenv, glob, implode, is_array, is_dir, is_file, is_string, json_decode, ltrim, realpath, simplexml_load_file, str_contains, str_replace, str_starts_with, strlen, strrpos, substr;

/**
 * Describes the XenForo installation and add-ons being analysed. The bootstrap file and the
 * PHPStan services share one instance, since bootstrap files cannot read container parameters.
 */
final class AddOnContext
{
	private static ?self $instance = null;

	/**
	 * @var array<string, string> XFCP proxy class => class it proxies
	 */
	private array $proxyBases = [];

	/**
	 * @var array<string, list<string>> extended class => extension classes
	 */
	private array $extensions = [];

	/**
	 * @var array<string, true>
	 */
	private array $extensionClasses = [];

	/**
	 * @var array<string, true>
	 */
	private array $loadedAddOnIds = [];

	/**
	 * @param list<string> $addOnIds the analysed add-on first, then installed dependencies
	 */
	private function __construct(
		public readonly ?string $rootDir,
		public readonly array $addOnIds
	)
	{
		foreach ($addOnIds AS $addOnId)
		{
			$this->loadClassExtensions($addOnId);
		}
	}

	public static function get(): self
	{
		return self::$instance ??= self::detect((string) getcwd(), self::getConfigurationDirectory());
	}

	/**
	 * @param ?string $configurationDirectory searched when the working directory is not inside
	 *                                        an installation or add-on, as IDEs run PHPStan from
	 *                                        the project root
	 */
	public static function detect(string $workingDirectory, ?string $configurationDirectory = null): self
	{
		$workingDirectory = realpath($workingDirectory) ?: $workingDirectory;

		$rootDir = self::locateRootDirectory($workingDirectory, $configurationDirectory);
		if ($rootDir === null)
		{
			return new self(null, []);
		}

		$targetIds = getenv('XF_ADDON');
		if (is_string($targetIds) && $targetIds !== '')
		{
			$targetIds = explode(',', $targetIds);
		}
		else
		{
			$addOnId = self::findAddOnId($rootDir, $workingDirectory);
			if ($addOnId === null && $configurationDirectory !== null)
			{
				$addOnId = self::findAddOnId($rootDir, $configurationDirectory);
			}

			$targetIds = array_filter([$addOnId]);
		}

		return new self($rootDir, self::withDependencies($rootDir, $targetIds));
	}

	/**
	 * The extension is often installed globally, so finding no installation means the project
	 * is not XenForo and the extension stays inactive.
	 */
	public static function locateRootDirectory(string $workingDirectory, ?string $configurationDirectory = null): ?string
	{
		$rootDir = getenv('XF_ROOT');
		if (is_string($rootDir) && $rootDir !== '')
		{
			if (!is_file($rootDir . '/src/XF.php'))
			{
				throw new \RuntimeException("XF_ROOT does not point to a XenForo installation: {$rootDir}");
			}
		}
		else
		{
			$rootDir = self::findRootDirectory($workingDirectory);
			if ($rootDir === null && $configurationDirectory !== null)
			{
				$rootDir = self::findRootDirectory($configurationDirectory);
			}

			if ($rootDir === null)
			{
				return null;
			}
		}

		return realpath($rootDir) ?: $rootDir;
	}

	public function isXenForo(): bool
	{
		return $this->rootDir !== null;
	}

	public function getAddOnDirectory(string $addOnId): string
	{
		return $this->rootDir . '/src/addons/' . $addOnId;
	}

	public function getProxyBase(string $proxyClass): ?string
	{
		if (!isset($this->proxyBases[$proxyClass]) && str_contains($proxyClass, '\\XFCP_'))
		{
			// Optional integrations extend classes of add-ons they do not require, and those
			// classes extend proxies that only their own add-on declares.
			$addOnId = $this->findAddOnIdForClass($proxyClass);
			if ($addOnId !== null)
			{
				$this->loadClassExtensions($addOnId);
			}
		}

		return $this->proxyBases[$proxyClass] ?? null;
	}

	/**
	 * @return list<string>
	 */
	public function getExtensionsOf(string $class): array
	{
		return $this->extensions[$class] ?? [];
	}

	public function isExtensionClass(string $class): bool
	{
		return isset($this->extensionClasses[$class]);
	}

	/**
	 * @return list<string>
	 */
	public function getOptionHintFiles(): array
	{
		$files = [];
		foreach ($this->addOnIds AS $addOnId)
		{
			$file = $this->getAddOnDirectory($addOnId) . '/_output/option_hint.php';
			if (is_file($file))
			{
				$files[] = $file;
			}
		}

		return $files;
	}

	/**
	 * Mirrors the fake base classes XenForo substitutes when an extended class does not exist.
	 */
	public static function getFakeBaseClass(string $class): ?string
	{
		if (str_contains($class, '\\View\\'))
		{
			return 'XF\Mvc\View';
		}

		if (str_contains($class, '\\Finder\\'))
		{
			return 'XF\Mvc\Entity\Finder';
		}

		if (str_contains($class, '\\Repository\\'))
		{
			return 'XF\Mvc\Entity\Repository';
		}

		return null;
	}

	private function loadClassExtensions(string $addOnId): void
	{
		if (isset($this->loadedAddOnIds[$addOnId]))
		{
			return;
		}

		$this->loadedAddOnIds[$addOnId] = true;

		foreach ($this->readClassExtensions($addOnId) AS $extension)
		{
			if (!$extension['active'])
			{
				continue;
			}

			$fromClass = $this->resolveClassAlias(ltrim($extension['from_class'], '\\'));
			$toClass = ltrim($extension['to_class'], '\\');

			$separator = strrpos($toClass, '\\');
			if ($separator === false)
			{
				continue;
			}

			$proxyClass = substr($toClass, 0, $separator) . '\\XFCP_' . substr($toClass, $separator + 1);

			$this->proxyBases[$proxyClass] = $fromClass;
			$this->extensions[$fromClass][] = $toClass;
			$this->extensionClasses[$toClass] = true;
		}
	}

	/**
	 * Add-ons installed from a release have no development output, only release data.
	 *
	 * @return list<array{from_class: string, to_class: string, active: bool}>
	 */
	private function readClassExtensions(string $addOnId): array
	{
		$addOnDir = $this->getAddOnDirectory($addOnId);
		$extensions = [];

		if (is_dir($addOnDir . '/_output/class_extensions'))
		{
			$files = glob($addOnDir . '/_output/class_extensions/*.json') ?: [];
			foreach ($files AS $file)
			{
				if (basename($file) === '_metadata.json')
				{
					continue;
				}

				$extension = json_decode((string) file_get_contents($file), true);
				if (
					is_array($extension)
					&& is_string($extension['from_class'] ?? null)
					&& is_string($extension['to_class'] ?? null)
				)
				{
					$extensions[] = [
						'from_class' => $extension['from_class'],
						'to_class' => $extension['to_class'],
						'active' => (bool) ($extension['active'] ?? true),
					];
				}
			}

			return $extensions;
		}

		$file = $addOnDir . '/_data/class_extensions.xml';
		$xml = is_file($file) ? simplexml_load_file($file) : false;
		if ($xml === false)
		{
			return [];
		}

		foreach ($xml->extension AS $extension)
		{
			$extensions[] = [
				'from_class' => (string) $extension['from_class'],
				'to_class' => (string) $extension['to_class'],
				'active' => (string) ($extension['active'] ?? '1') !== '0',
			];
		}

		return $extensions;
	}

	/**
	 * Add-on IDs are one or two namespace segments, as in XFMG or Vendor/AddOn.
	 */
	private function findAddOnIdForClass(string $class): ?string
	{
		if ($this->rootDir === null)
		{
			return null;
		}

		$segments = explode('\\', $class);
		for ($length = 1; $length <= 2 && $length < count($segments); $length++)
		{
			$addOnId = implode('/', array_slice($segments, 0, $length));
			if (is_file($this->getAddOnDirectory($addOnId) . '/addon.json'))
			{
				return $addOnId;
			}
		}

		return null;
	}

	/**
	 * Class extensions may target pre-2.3 names such as XF\Repository\User, which XenForo
	 * aliases to the suffixed class at runtime.
	 */
	private function resolveClassAlias(string $class): string
	{
		if (!class_exists('XF', false))
		{
			return $class;
		}

		return \XF::getClassForAlias($class);
	}

	/**
	 * Reads --configuration from the command line, since PHPStan does not expose the
	 * configuration path before bootstrap files run.
	 */
	public static function getConfigurationDirectory(): ?string
	{
		$arguments = $_SERVER['argv'] ?? [];
		if (!is_array($arguments))
		{
			return null;
		}

		$configurationFile = null;
		$expectsValue = false;
		foreach ($arguments AS $argument)
		{
			if (!is_string($argument) || $argument === '--')
			{
				break;
			}

			if ($expectsValue)
			{
				$configurationFile = $argument;
				$expectsValue = false;
			}
			else if ($argument === '-c' || $argument === '--configuration')
			{
				$expectsValue = true;
			}
			else if (str_starts_with($argument, '--configuration='))
			{
				$configurationFile = substr($argument, strlen('--configuration='));
			}
			else if (str_starts_with($argument, '-c') && !str_starts_with($argument, '--'))
			{
				$configurationFile = substr($argument, 2);
			}
		}

		if ($configurationFile === null || $configurationFile === '')
		{
			return null;
		}

		$configurationFile = realpath($configurationFile);

		return $configurationFile === false ? null : dirname($configurationFile);
	}

	private static function findRootDirectory(string $directory): ?string
	{
		while (true)
		{
			if (is_file($directory . '/src/XF.php'))
			{
				return $directory;
			}

			$parent = dirname($directory);
			if ($parent === $directory)
			{
				return null;
			}

			$directory = $parent;
		}
	}

	private static function findAddOnId(string $rootDir, string $directory): ?string
	{
		$addOnsDir = $rootDir . '/src/addons/';

		while (str_starts_with($directory . '/', $addOnsDir) && strlen($directory) >= strlen($addOnsDir))
		{
			if (is_file($directory . '/addon.json'))
			{
				return substr($directory, strlen($addOnsDir));
			}

			$directory = dirname($directory);
		}

		return null;
	}

	/**
	 * @param list<string> $addOnIds
	 *
	 * @return list<string>
	 */
	private static function withDependencies(string $rootDir, array $addOnIds): array
	{
		$resolved = [];
		$pending = $addOnIds;

		while ($pending)
		{
			$addOnId = str_replace('\\', '/', array_shift($pending));
			if (array_key_exists($addOnId, $resolved))
			{
				continue;
			}

			$addOnDir = $rootDir . '/src/addons/' . $addOnId;
			if (!is_dir($addOnDir))
			{
				continue;
			}

			$resolved[$addOnId] = true;

			$json = is_file($addOnDir . '/addon.json')
				? json_decode((string) file_get_contents($addOnDir . '/addon.json'), true)
				: null;
			$require = is_array($json) ? $json['require'] ?? null : null;
			foreach (array_keys(is_array($require) ? $require : []) AS $requirement)
			{
				$pending[] = (string) $requirement;
			}
		}

		return array_keys($resolved);
	}
}
