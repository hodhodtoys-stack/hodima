<?php
/**
 * آزمون فهرست منوی هدر (تب «هدر» ← «منوی هدر»، قالب 4.0.9) روی وردپرس ابزار تست.
 *   HARNESS=1 php tools/wp-harness/header-menu-tests.php
 * خروجی: هر آزمون ✔/✘؛ کد خروج ۱ اگر یکی رد شود.
 *
 * قفل می‌کند: گزینه‌ها (پیش‌فرض + همه فهرست‌ها)، پاک‌سازی ذخیره (شناسه ناشناخته/متن = پیش‌فرض)،
 * هدر واقعی با فهرست انتخابی، برگشت خودکار به مکان منوی اصلی (بدون انتخاب و با فهرست حذف‌شده)،
 * و ماندن دکمه زیرمنوی موبایل روی فهرست انتخابی.
 */

declare(strict_types=1);

$_SERVER['HTTP_HOST']       = 'hodima.test';
$_SERVER['SERVER_NAME']     = 'hodima.test';
$_SERVER['REQUEST_URI']     = '/';
$_SERVER['HTTPS']           = 'on';
$_SERVER['REMOTE_ADDR']     = '203.0.113.10';
$_SERVER['HTTP_USER_AGENT'] = 'HarnessBrowser/1.0';
require rtrim( (string) ( getenv( 'HODIMA_WP' ) ?: '/tmp/hodima-harness/wp' ), '/' ) . '/wp-load.php';

$failed = 0;
$passed = 0;

function hodima_hm( string $name, mixed $actual, mixed $expected ): void {
	global $failed, $passed;
	if ( $actual === $expected ) {
		++$passed;
		echo "  ✔ {$name}\n";
		return;
	}
	++$failed;
	echo "  ✘ {$name}\n      انتظار: " . var_export( $expected, true ) . "\n      واقعی:  " . var_export( $actual, true ) . "\n";
}

/** فهرست با یک آیتم والد و یک زیرآیتم؛ شناسه فهرست را برمی‌گرداند. */
function hodima_hm_menu( string $name, string $parent ): int {
	$menu   = (int) wp_create_nav_menu( $name );
	$parent_id = wp_update_nav_menu_item( $menu, 0, [ 'menu-item-title' => $parent, 'menu-item-url' => home_url( '/' . rawurlencode( $name ) . '/' ), 'menu-item-status' => 'publish' ] );
	wp_update_nav_menu_item( $menu, 0, [ 'menu-item-title' => $parent . ' زیر', 'menu-item-url' => home_url( '/sub/' ), 'menu-item-status' => 'publish', 'menu-item-parent-id' => $parent_id ] );
	return $menu;
}

/** خروجی واقعی header.php. */
function hodima_hm_header(): string {
	ob_start();
	get_header();
	$html = (string) ob_get_clean();
	// فقط ناحیه منوی اصلی
	return preg_match( '#<nav class="main-nav".*?</nav>#s', $html, $m ) ? $m[0] : '';
}

// حالت فرزند: هدر را با مقدار داده‌شده برای گزینه می‌سازد (hodima_settings() در هر اجرا یک‌بار کش می‌شود)
if ( ( $argv[1] ?? '' ) === 'render' ) {
	update_option( HODIMA_SETTINGS_OPTION, [ 'header_menu' => (string) ( $argv[2] ?? '0' ) ] );
	echo hodima_hm_header();
	exit( 0 );
}

$saved_settings  = get_option( HODIMA_SETTINGS_OPTION );
$saved_locations = get_theme_mod( 'nav_menu_locations' );

$menu_a = hodima_hm_menu( 'آزمون الف', 'آیتم‌الف' );
$menu_b = hodima_hm_menu( 'آزمون ب', 'آیتم‌ب' );
set_theme_mod( 'nav_menu_locations', [ HODIMA_MENU_PRIMARY => $menu_a ] );

echo "گزینه‌ها\n";
$options = hodima_header_menu_options();
hodima_hm( 'کلید ۰ = پیش‌فرض و اول فهرست', array_key_first( $options ), 0 );
hodima_hm( 'فهرست الف در گزینه‌ها', $options[ $menu_a ] ?? null, 'آزمون الف' );
hodima_hm( 'فهرست ب در گزینه‌ها', $options[ $menu_b ] ?? null, 'آزمون ب' );
hodima_hm( 'فیلد گزینه‌ها را از options_cb می‌گیرد', array_key_exists( $menu_b, hodima_settings_field_options( hodima_settings_fields()['header_menu'] ) ), true );

echo "ذخیره (پاک‌سازی)\n";
$field = hodima_settings_fields()['header_menu'];
hodima_hm( 'پیش‌فرض ۰', $field['default'], '0' );
hodima_hm( 'شناسه معتبر می‌ماند', Hodima_Setting_Type::Select->sanitize( 'header_menu', $field, (string) $menu_b ), (string) $menu_b );
hodima_hm( 'شناسه ناشناخته = پیش‌فرض', Hodima_Setting_Type::Select->sanitize( 'header_menu', $field, '999999' ), '0' );
hodima_hm( 'متن دلخواه = پیش‌فرض', Hodima_Setting_Type::Select->sanitize( 'header_menu', $field, 'abc<script>' ), '0' );
hodima_hm( 'مقدار ارسال‌نشده = پیش‌فرض', Hodima_Setting_Type::Select->sanitize( 'header_menu', $field, null ), '0' );
$clean = hodima_settings_sanitize( [ 'header_menu' => (string) $menu_b ] );
hodima_hm( 'ذخیره کامل تنظیمات', $clean['header_menu'], (string) $menu_b );

echo "هدر سایت\n";
/** هدر با مقدار گزینه، در یک اجرای جدا. */
$render = static function ( string $value ): string {
	$out = shell_exec( 'HARNESS=1 ' . escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' render ' . escapeshellarg( $value ) . ' 2>/dev/null' );
	return is_string( $out ) ? $out : '';
};
$has = static fn( string $html ): array => [ str_contains( $html, 'آیتم‌الف' ), str_contains( $html, 'آیتم‌ب' ) ];

$html = $render( '0' );
hodima_hm( 'بدون انتخاب: فهرست مکان منوی اصلی (الف)', $has( $html ), [ true, false ] );
hodima_hm( 'دکمه زیرمنو روی فهرست مکان', str_contains( $html, 'class="submenu-toggle"' ), true );

$html = $render( (string) $menu_b );
hodima_hm( 'فهرست ب انتخاب شود: فقط ب', $has( $html ), [ false, true ] );
hodima_hm( 'دکمه زیرمنوی موبایل روی فهرست انتخابی', str_contains( $html, 'class="submenu-toggle"' ), true );
hodima_hm( 'ساختار منوی اصلی (main-menu) می‌ماند', str_contains( $html, 'class="main-menu"' ), true );

$html = $render( '999999' );
hodima_hm( 'شناسه ناموجود: برگشت به مکان منوی اصلی', $has( $html ), [ true, false ] );

wp_delete_nav_menu( $menu_b );
$html = $render( (string) $menu_b );
hodima_hm( 'فهرست انتخابی حذف شد: هدر خالی نمی‌ماند، مکان منوی اصلی', $has( $html ), [ true, false ] );

echo "\nنتیجه: {$passed} قبول، {$failed} رد\n";

// پاک‌سازی
wp_delete_nav_menu( $menu_a );
wp_delete_nav_menu( $menu_b ); // (اگر پیش‌تر حذف شده باشد بی‌اثر است)
false === $saved_settings ? delete_option( HODIMA_SETTINGS_OPTION ) : update_option( HODIMA_SETTINGS_OPTION, $saved_settings );
false === $saved_locations ? remove_theme_mod( 'nav_menu_locations' ) : set_theme_mod( 'nav_menu_locations', $saved_locations );

exit( $failed ? 1 : 0 );
