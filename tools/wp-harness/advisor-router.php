<?php
/**
 * مسیریاب php -S برای وردپرس ابزار تست با میزبان https://hodima.test (دستار سئو؛ خزش واقعی محلی).
 * راه‌اندازی (پیش‌نیاز: fixtures.php اجرا شده):
 *   HARNESS_WC=1 PHP_CLI_SERVER_WORKERS=4 php -S 127.0.0.1:8791 -t /tmp/hodima-harness/wp tools/wp-harness/advisor-router.php &
 * بعد: PORT=8791 HARNESS=1 php tools/wp-harness/advisor-crawl-local.php
 * سرور را با شناسه‌اش بکش (ps -eo pid,args | grep '[p]hp -S 127.0.0.1:8791'؛ pkill -f شل را هم می‌کشد).
 */

$_SERVER['HTTPS']     = 'on';
$_SERVER['HTTP_HOST'] = $_SERVER['SERVER_NAME'] = 'hodima.test';
$path                 = rawurldecode( (string) parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) );
$root                 = $_SERVER['DOCUMENT_ROOT'];

if ( is_dir( $root . $path ) && is_file( rtrim( $root . $path, '/' ) . '/index.php' ) ) {
	$path = rtrim( $path, '/' ) . '/index.php';
}

if ( '/' !== $path && is_file( $root . $path ) ) {
	if ( ! str_ends_with( $path, '.php' ) ) {
		return false;
	}
	$_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'] = $path;
	$_SERVER['SCRIPT_FILENAME'] = $root . $path;
	chdir( dirname( $root . $path ) );
	require $root . $path;
	return true;
}

$_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $root . '/index.php';
chdir( $root );
require $root . '/index.php';
