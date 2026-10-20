<?php

/** @noinspection ALL */

declare(strict_types=1);

use PhpCsFixer\Finder;
use XFCsFixer\ConfigHelper;

$finder = Finder::create()
	->in(__DIR__)
	->exclude('vendor')
	->name('*.php')
	->ignoreDotFiles(true)
	->ignoreVCSIgnored(true)
;

return (new ConfigHelper($finder))->getConfig();
