#!/bin/sh
# Runs tests/fixtures against a XenForo 2.3 installation:
#   tests/run.sh /path/to/xenforo [phpstan options]
# The fixtures rely on the add-on in tests/addon. It is linked into the installation for the run
# and removed afterwards; it is never installed, as the extension does not use the database.

set -eu

if [ $# -lt 1 ] || [ ! -f "$1/src/XF.php" ]; then
	echo "Usage: $0 /path/to/xenforo [phpstan options]" >&2
	exit 2
fi

root=$(cd "$1" && pwd)
shift

tests=$(cd "$(dirname "$0")" && pwd)
addon_id=PHPStanXenForoTest
link="$root/src/addons/$addon_id"

if [ -e "$link" ] && [ ! -L "$link" ]; then
	echo "$link exists and is not a link; not replacing it" >&2
	exit 1
fi

ln -sfn "$tests/addon/$addon_id" "$link"
trap 'rm -f "$link"' EXIT INT TERM

XF_ROOT="$root" XF_ADDON="$addon_id" "$tests/../vendor/bin/phpstan" analyse -c "$tests/phpstan.neon" "$@"
