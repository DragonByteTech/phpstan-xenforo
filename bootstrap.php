<?php

use Composer\Autoload\ClassLoader;
use DragonByteTech\PHPStanXenForo\AddOnContext;

if (!class_exists(AddOnContext::class))
{
	spl_autoload_register(static function (string $class): void
	{
		$prefix = 'DragonByteTech\\PHPStanXenForo\\';
		if (str_starts_with($class, $prefix))
		{
			$file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
			if (is_file($file))
			{
				require $file;
			}
		}
	});
}

$rootDir = AddOnContext::locateRootDirectory((string) getcwd(), AddOnContext::getConfigurationDirectory());
if ($rootDir === null)
{
	return;
}

require_once $rootDir . '/src/XF.php';

// Not \XF::start(): standardizeEnvironment() unregisters the phar stream wrapper that
// phpstan.phar runs from, and startSystem() installs XenForo's error and exception handlers.
\XF::bootstrap($rootDir);
\XF::startAutoloader();
\XF::setupClassAliases();
require_once $rootDir . '/src/utf8.php';

$context = AddOnContext::get();

foreach ($context->addOnIds AS $addOnId)
{
	$composerDir = $context->getComposerDirectory($addOnId);
	if ($composerDir !== null && is_file(dirname($composerDir) . '/autoload.php'))
	{
		require_once dirname($composerDir) . '/autoload.php';
	}
}

// XenForo registers the Composer classes of every active add-on, which optional integrations
// and seeders rely on. Other add-ons' classes are appended, so they never shadow the analysed
// add-on's dependencies, and their `files` entries are not run.
foreach (array_diff($context->getInstalledAddOnIds(), $context->addOnIds) AS $addOnId)
{
	$composerDir = $context->getComposerDirectory($addOnId);
	if ($composerDir === null)
	{
		continue;
	}

	$loader = new ClassLoader(dirname($composerDir));
	foreach (['autoload_namespaces.php' => 'set', 'autoload_psr4.php' => 'setPsr4'] AS $file => $method)
	{
		/** @var array<string, list<string>|string> $map Composer's generated autoload map */
		$map = is_file($composerDir . '/' . $file) ? require $composerDir . '/' . $file : [];
		foreach ($map AS $prefix => $paths)
		{
			$loader->$method($prefix, $paths);
		}
	}

	/** @var array<string, string> $classMap Composer's generated class map */
	$classMap = is_file($composerDir . '/autoload_classmap.php') ? require $composerDir . '/autoload_classmap.php' : [];
	$loader->addClassMap($classMap);
	$loader->register(false);
}

// XFCP proxies only exist once XF\Extension::extendClass() declares them, and PHPStan loads
// the add-on's extension classes at runtime while reflecting them.
spl_autoload_register(static function (string $class) use ($context): void
{
	$baseClass = $context->getProxyBase($class);
	if ($baseClass === null)
	{
		return;
	}

	if (!class_exists($baseClass))
	{
		$baseClass = AddOnContext::getFakeBaseClass($baseClass);
		if ($baseClass === null)
		{
			return;
		}
	}

	class_alias($baseClass, $class);
});
