# PHPStan for XenForo add-ons

A [PHPStan](https://phpstan.org) extension that analyses [XenForo](https://xenforo.com) 2.3 add-ons from inside a development installation,
without the false positives XenForo's runtime conventions otherwise produce.

## What it handles

| XenForo convention | Without the extension | With it |
|---|---|---|
| `XFCP_*` proxies | Class not found; analysis aborts | Aliased to the extended class (or XenForo's fake base for missing views, finders and repositories) |
| Class extensions | Members added by an extension are undefined on the extended class | Methods and properties of every active extension of the add-on and its dependencies are visible on the extended class |
| Entity structures | Columns, getters and relations without `@property` tags are undefined | Typed from `getStructure()`, using the same rules as `xf-dev:entity-class-properties` |
| Options | `XF\Options::$foo` is undefined | Typed from `_output/option_hint.php`; other options are `mixed` |
| Short names | `finder('XF:User')` returns the template bound | Resolves to the named class, including `Vendor:Infix:Name` plugins and bare validator names |
| Finder chains | `where()`, `order()`, etc. lose the finder subclass and entity type | Return the finder they were called on |
| `__get()` bags | `ParameterBag`, `Session`, `App`, … report undefined properties | Treated as universal object crates |
| Core PHPDoc | `@param null $foo`, array-only query params, `Phrase` titles, … | Targeted ignores for the known false positives |

The extension is safe to install globally. Outside a XenForo installation it does nothing, and
every rule it adds is limited to XenForo classes or switched off. Project-wide settings such as
`treatPhpDocTypesAsCertain` therefore belong in each add-on's own config (see below).

## Install

Install globally, next to a global PHPStan:

```sh
composer global config repositories.phpstan-xenforo path /Users/Shared/www/git/phpstan-xenforo
composer global config allow-plugins.phpstan/extension-installer true
composer global require phpstan/extension-installer dragonbytetech/phpstan-xenforo:@dev
```

`phpstan/extension-installer` includes `extension.neon` automatically. Without it, add the file to
the add-on's `includes` yourself.

## Use

Add a `phpstan.neon.dist` to the add-on root:

```neon
parameters:
	level: 5

	# XenForo PHPDoc describes intent rather than enforced types, and add-ons guard against
	# values the docblocks rule out.
	treatPhpDocTypesAsCertain: false

	paths:
		- .
	excludePaths:
		analyseAndScan:
			- vendor (?)
			- _data (?)
			- _dev (?)
			- _files (?)
			- _no_upload (?)
			- _output (?)
			- _releases (?)
```

`(?)` marks a path as optional, since not every add-on has every directory. Leave `_output` out
of scanning: `option_hint.php` declares a second `XF\Options` that hides the real one.

Then run PHPStan from the add-on directory:

```sh
cd src/addons/Vendor/AddOn
phpstan analyse
```

The XenForo root and add-on ID come from the working directory. When the working directory is
not inside an add-on, they come from the directory of the `--configuration` (`-c`) file instead,
so this works from the XenForo root as well:

```sh
phpstan analyse -c src/addons/Vendor/AddOn/phpstan.neon.dist
```

To override both, set `XF_ROOT` (the installation root) and `XF_ADDON` (comma-separated add-on
IDs).

Add-ons listed in `addon.json` `require` are loaded as well, so members they add through class
extensions resolve too. Class extensions of any other add-on are loaded when analysis reaches one
of its proxies, as optional integrations do. Add-ons installed from a release, without
development output, are read from `_data/class_extensions.xml`.

Keep the file out of release builds by adding this to the `exec` list in `build.json`:

```json
"rm -f _build/upload/src/addons/{addon_id}/phpstan.neon.dist"
```

### PhpStorm

PhpStorm may run PHPStan from the project root, but it passes the configuration file on the
command line. Point **Settings › PHP › Quality Tools › PHPStan › Configuration file** at the
add-on's `phpstan.neon.dist`. PhpStorm takes one configuration file per project; change it when
you switch add-ons.

### Core option types

Core options are `mixed` until you generate a stub. Run this from the XenForo root:

```sh
php cmd.php xf-dev:generate-options-stub
```

The command writes `Options.stub` to the root, and the extension picks it up from there.
Regenerate it when options change. For a stub somewhere else, give an absolute path:

```neon
parameters:
	xenforo:
		coreOptionsStub: /path/to/Options.stub
```

## Tests

`tests/fixtures` holds `assertType()` cases. They rely on `DBTech/MemberMap`'s class extensions
and options:

```sh
cd src/addons/DBTech/MemberMap
phpstan analyse -c /Users/Shared/www/git/phpstan-xenforo/tests/phpstan.neon
```

CI cannot run them, as it has no XenForo. It lints every file on each supported PHP version,
checks code style, and analyses the extension itself at level max against the XenForo
declarations in `tests/stubs/xenforo.php`. To run the same checks locally:

```sh
composer install --no-plugins
composer cs
composer analyse
```

`--no-plugins` matters when `phpstan/extension-installer` is installed globally: global plugins
also run in this project and would point the global PHPStan at this project's `vendor`.

## Limits

- XenForo is loaded with `XF::bootstrap()`, `startAutoloader()` and `setupClassAliases()` only.
  `XF::start()` unregisters the `phar` stream wrapper PHPStan runs from. No app is set up and the
  database is never touched.
- Entity structures are built at analysis time. When `getStructure()` throws, because it needs the
  app, every unknown property of that entity resolves to `mixed`, so nothing false is reported.
- An argument typed as an extension class but given its base class is ignored only when the
  message names both classes. Unions and generics are compared textually.
