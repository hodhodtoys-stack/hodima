#!/usr/bin/env bash
# آزمون «ورود بسته» Hodima SEO (core/package-import) در Chromium با ورود واقعی مدیر و ووکامرس واقعی — راهنما: package-import-check.mjs
#   package-import-check.sh [src-root]       (پیش‌فرض: همین مخزن؛ چند دقیقه)
# نسخه ووکامرس ابزار تست (wc-setup.sh) را با php -S بالا می‌آورد. بسته playground اسکریپت‌ها و CSS پیشخوان
# (jQuery، TinyMCE، select2) را ندارد: یک بار wordpress-6.7.zip رسمی در ~/.cache/hodima/wpfull گرفته و فقط
# پوشه‌های js/css/images آن کنار نصب ابزار تست کپی می‌شود (فایل PHP هسته عوض نمی‌شود).
set -uo pipefail
here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"; repo="${1:-$(cd "$here/../.." && pwd)}"
base="${HODIMA_HARNESS_WC:-/tmp/hodima-harness-wc}"; export HODIMA_WP="$base/wp" HODIMA_HARNESS="$base"
port="${PKG_PORT:-8777}"; export PKG_PORT="$port"
[ -d "$HODIMA_WP/wp-content/plugins/woocommerce" ] || HODIMA_HARNESS_WC="$base" "$here/wc-setup.sh"
[ -d "$repo/node_modules/playwright-core" ] || ( cd "$repo" && npm ci --no-audit --no-fund --silent )

full="$HOME/.cache/hodima/wpfull/wordpress"
if [ ! -f "$full/wp-includes/js/jquery/jquery.min.js" ]; then
	mkdir -p "$(dirname "$full")"
	curl -fsSL --max-time 300 -o "$full.zip" https://downloads.wordpress.org/release/wordpress-6.7.zip && ( cd "$(dirname "$full")" && unzip -qo wordpress.zip ) && rm -f "$full.zip"
fi
for d in wp-admin/css wp-admin/js wp-admin/images wp-includes/js wp-includes/css wp-includes/fonts wp-includes/images; do
	mkdir -p "$HODIMA_WP/$d" && cp -r --update=none "$full/$d/." "$HODIMA_WP/$d/"
done

HODIMA_HARNESS="$base" "$here/link.sh" "$repo" >/dev/null
rm -f "$HODIMA_WP/wp-content/mu-plugins/harness-wc-stub.php" "$HODIMA_WP/wp-content/database/.ht.sqlite" "$HODIMA_WP/debug.log"
# پیشخوان راست‌به‌چپ مثل سایت فارسی (فقط ابزار تست)
cat > "$HODIMA_WP/wp-content/mu-plugins/zz-rtl-admin.php" <<'PHP'
<?php
add_action( 'init', static function () { if ( is_admin() && isset( $GLOBALS['wp_locale'] ) ) { $GLOBALS['wp_locale']->text_direction = 'rtl'; } }, 0 );
PHP
php "$here/wc-fixtures.php" install >/dev/null 2>&1
php "$here/wc-fixtures.php" data >/dev/null 2>&1

router="$base/pkg-router.php"
cat > "$router" <<'PHP'
<?php
$_SERVER['HTTPS'] = 'on';
$_SERVER['HTTP_HOST'] = $_SERVER['SERVER_NAME'] = 'hodima.test';
$path = rawurldecode( (string) parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) );
$root = $_SERVER['DOCUMENT_ROOT'];
if ( is_dir( $root . $path ) && is_file( rtrim( $root . $path, '/' ) . '/index.php' ) ) { $path = rtrim( $path, '/' ) . '/index.php'; }
if ( '/' !== $path && is_file( $root . $path ) ) {
	if ( ! str_ends_with( $path, '.php' ) ) { return false; }
	$_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'] = $path; $_SERVER['SCRIPT_FILENAME'] = $root . $path;
	chdir( dirname( $root . $path ) ); require $root . $path; return true;
}
$_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'] = '/index.php'; $_SERVER['SCRIPT_FILENAME'] = $root . '/index.php';
chdir( $root ); require $root . '/index.php';
PHP
PHP_CLI_SERVER_WORKERS=4 php -S "127.0.0.1:$port" -t "$HODIMA_WP" "$router" >"$base/pkg-server.log" 2>&1 &
srv=$!
trap 'kill $srv 2>/dev/null' EXIT
for _ in $(seq 1 40); do curl -sf -o /dev/null "http://127.0.0.1:$port/wp-login.php" && break; sleep 0.5; done
( cd "$repo" && node "$here/package-import-check.mjs" )
rc=$?
[ -s "$HODIMA_WP/debug.log" ] && { echo "debug.log (PHP، جز هشدار افزونه SQLite):"; grep -h "PHP " "$HODIMA_WP/debug.log" | grep -v sqlite-database-integration | sed 's/^\[[^]]*\] //' | cut -c1-200 | sort | uniq -c | head; }
exit $rc
