#!/usr/bin/env bash
# آزمون صفحه «شماره‌های تماس» پیشخوان (Commerce 1.3.1) در Chromium با ورود واقعی مدیر — راهنما: phone-admin-check.mjs
#   phone-admin-check.sh [src-root]       (پیش‌فرض: همین مخزن؛ حدود چند دقیقه)
# وردپرس ابزار تست را با php -S (چند کارگر) بالا می‌آورد و آدرس https://hodima.test را در Chromium به آن نگاشت می‌کند.
set -uo pipefail
here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"; repo="${1:-$(cd "$here/../.." && pwd)}"
base="${HODIMA_HARNESS:-/tmp/hodima-harness}"; export HODIMA_WP="$base/wp" HODIMA_HARNESS="$base"
port="${CCSS_PORT:-8766}"; export CCSS_PORT="$port"
[ -f "$base/wp/wp-load.php" ] || "$here/setup.sh"
[ -d "$repo/node_modules/playwright-core" ] || ( cd "$repo" && npm ci --no-audit --no-fund --silent )
"$here/link.sh" "$repo"
rm -f "$HODIMA_WP/wp-content/database/.ht.sqlite" "$HODIMA_WP/debug.log"
HARNESS_WC=1 php "$here/fixtures.php" >/dev/null 2>&1
router="$base/phone-router.php"
cat > "$router" <<'PHP'
<?php
// مسیریاب php -S: فایل واقعی را خودش می‌دهد، بقیه به index.php وردپرس؛ HTTPS و میزبان ابزار تست
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
HARNESS_WC=1 PHP_CLI_SERVER_WORKERS=4 php -S "127.0.0.1:$port" -t "$HODIMA_WP" "$router" >"$base/phone-server.log" 2>&1 &
srv=$!
trap 'kill $srv 2>/dev/null' EXIT
for _ in $(seq 1 40); do curl -sf -o /dev/null "http://127.0.0.1:$port/wp-login.php" && break; sleep 0.5; done
( cd "$repo" && node "$here/phone-admin-check.mjs" )
rc=$?
[ -s "$HODIMA_WP/debug.log" ] && { echo "debug.log (PHP):"; grep -h "PHP " "$HODIMA_WP/debug.log" | sed 's/^\[[^]]*\] //' | cut -c1-200 | sort | uniq -c | head; }
exit $rc
