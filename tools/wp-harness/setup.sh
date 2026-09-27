#!/usr/bin/env bash
# One-time: download WordPress + SQLite drop-in into $HODIMA_HARNESS (default /tmp/hodima-harness).
# wordpress.org is blocked in the cloud sandbox, so WordPress comes from the npm
# package @wp-playground/wordpress-builds (WordPress 6.7 + sqlite-database-integration).
set -euo pipefail
here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
base="${HODIMA_HARNESS:-/tmp/hodima-harness}"; wp="$base/wp"
mkdir -p "$base/pkg"
if [ ! -f "$base/pkg/package/src/wordpress/wp-nightly.zip" ]; then
	( cd "$base/pkg" && npm pack --silent @wp-playground/wordpress-builds@0.9.19 >/dev/null \
		&& tar xzf wp-playground-wordpress-builds-*.tgz package/src/wordpress/wp-nightly.zip package/src/sqlite-database-integration/sqlite-database-integration.zip \
		&& rm -f wp-playground-wordpress-builds-*.tgz )
fi
rm -rf "$wp"; mkdir -p "$wp"
( cd "$wp" && unzip -q "$base/pkg/package/src/wordpress/wp-nightly.zip" )
( cd "$wp/wp-content/plugins" && unzip -q "$base/pkg/package/src/sqlite-database-integration/sqlite-database-integration.zip" && mv sqlite-database-integration-main sqlite-database-integration )
sed -e "s#{SQLITE_IMPLEMENTATION_FOLDER_PATH}#$wp/wp-content/plugins/sqlite-database-integration#" \
    -e "s#{SQLITE_PLUGIN}#sqlite-database-integration/load.php#" \
    "$wp/wp-content/plugins/sqlite-database-integration/db.copy" > "$wp/wp-content/db.php"
cp "$here/wp-config.php.tpl" "$wp/wp-config.php"
mkdir -p "$wp/wp-content/mu-plugins"; cp "$here"/mu-plugins/*.php "$wp/wp-content/mu-plugins/"
echo "✔ WordPress ready in $wp"
