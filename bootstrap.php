<?php

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
	$addOnDir = $context->getAddOnDirectory($addOnId);
	$addOnJson = is_file($addOnDir . '/addon.json')
		? json_decode((string) file_get_contents($addOnDir . '/addon.json'), true)
		: null;

	$composerDir = is_array($addOnJson) && is_string($addOnJson['composer_autoload'] ?? null)
		? $addOnDir . '/' . trim($addOnJson['composer_autoload'], '/')
		: null;
	if ($composerDir !== null && is_file(dirname($composerDir) . '/autoload.php'))
	{
		require_once dirname($composerDir) . '/autoload.php';
	}
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
