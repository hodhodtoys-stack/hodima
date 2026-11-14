<?php
/**
 * آزمون خودکار «ورود و امنیت» Core (includes/login.php) روی وردپرس ابزار تست.
 *   HARNESS=1 php tools/wp-harness/login-tests.php
 * خروجی: هر آزمون ✔/✘؛ کد خروج ۱ اگر یکی رد شود. ci-check.sh هم اجرا می‌کند.
 *
 * آدرس لوگو داخل CSS چاپ می‌شود، پس آزمون اصلی «تزریق CSS ممکن نیست» است؛ بعد
 * پیام خطای یکسان، حدس ?author=N، فهرست کاربران REST و هدرها (HODIMA-AUDIT.md بخش ۴۳۹).
 */

declare(strict_types=1);

$_SERVER['HTTP_HOST'] = $_SERVER['SERVER_NAME'] = 'hodima.test';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['HTTPS']       = 'on';
require rtrim( (string) ( getenv( 'HODIMA_WP' ) ?: '/tmp/hodima-harness/wp' ), '/' ) . '/wp-load.php';

$failed = 0;
$passed = 0;

/** یک آزمون: مقدار واقعی === مقدار مورد انتظار. */
function hodima_lt( string $name, mixed $actual, mixed $expected ): void {
	global $failed, $passed;
	if ( $actual === $expected ) {
		++$passed;
		echo "  ✔ {$name}\n";
		return;
	}
	++$failed;
	echo "  ✘ {$name}\n      انتظار: " . var_export( $expected, true ) . "\n      واقعی:  " . var_export( $actual, true ) . "\n";
}

if ( ! function_exists( 'hodima_core_login_clean_logo_url' ) ) {
	echo "✘ ماژول ورود و امنیت لود نشده است\n";
	exit( 1 );
}

echo "=== آدرس لوگو (تزریق CSS ممکن نباشد)\n";
$good = [
	'png'    => 'https://hodhodli.com/wp-content/uploads/logo.png',
	'webp'   => 'https://cdn.example.com/a/b/logo.webp',
	'svg'    => 'https://example.com/logo.svg',
	'jpg'    => 'https://example.com/logo.JPG',
	'query'  => 'https://example.com/logo.png?v=3',
];
foreach ( $good as $label => $url ) {
	hodima_lt( "آدرس درست: {$label}", hodima_core_login_clean_logo_url( $url ), $url );
}
hodima_lt( 'فاصله دور آدرس گرفته می‌شود', hodima_core_login_clean_logo_url( "  https://example.com/logo.png \n" ), 'https://example.com/logo.png' );

$bad = [
	'http (بدون https)'           => 'http://example.com/logo.png',
	'بدون پروتکل'                 => '//example.com/logo.png',
	'javascript'                  => 'javascript:alert(1)//.png',
	'data URI'                    => 'data:image/png;base64,AAAA',
	'نقل‌قول و بستن url'          => 'https://example.com/logo.png")}body{display:none',
	'پرانتز'                      => 'https://example.com/logo(1).png',
	'نقل‌قول تکی'                 => "https://example.com/lo'go.png",
	'فاصله داخل آدرس'             => 'https://example.com/lo go.png',
	'بک‌اسلش'                     => 'https://example.com/lo\\go.png',
	'کاراکتر <'                   => 'https://example.com/<x>.png',
	'نام کاربری در آدرس'          => 'https://user:pass@example.com/logo.png',
	'پسوند gif'                   => 'https://example.com/logo.gif',
	'پسوند php'                   => 'https://example.com/logo.php',
	'بی‌پسوند'                    => 'https://example.com/logo',
	'پسوند فقط در query'          => 'https://example.com/run.php?x=.png',
	'خالی'                        => '',
	'خیلی بلند'                   => 'https://example.com/' . str_repeat( 'a', 520 ) . '.png',
];
foreach ( $bad as $label => $url ) {
	hodima_lt( "آدرس رد می‌شود: {$label}", hodima_core_login_clean_logo_url( $url ), '' );
}

echo "=== پاک‌سازی تنظیمات\n";
$d = hodima_core_login_defaults();
hodima_lt( 'پیش‌فرض: روشن', $d['enabled'], true );
hodima_lt( 'پیش‌فرض: ارتفاع ۴۸', $d['logo_height'], 48 );
hodima_lt( 'ارتفاع کمتر از حداقل', hodima_core_login_sanitize( [ 'logo_height' => '5' ] )['logo_height'], 24 );
hodima_lt( 'ارتفاع بیشتر از حداکثر', hodima_core_login_sanitize( [ 'logo_height' => '9999' ] )['logo_height'], 120 );
hodima_lt( 'ارتفاع غیرعددی', hodima_core_login_sanitize( [ 'logo_height' => 'abc' ] )['logo_height'], 48 );
hodima_lt( 'فرم: چک‌باکس تیک‌نخورده = خاموش', hodima_core_login_sanitize( [ 'logo_url' => '' ] )['enabled'], false );
hodima_lt( 'فرم: چک‌باکس تیک‌خورده = روشن', hodima_core_login_sanitize( [ 'enabled' => '1' ] )['enabled'], true );
hodima_lt( 'دیتابیس: کلید نبود = پیش‌فرض روشن', hodima_core_login_sanitize( [], true )['enabled'], true );
hodima_lt( 'دیتابیس: false ذخیره‌شده = خاموش', hodima_core_login_sanitize( [ 'enabled' => false ], true )['enabled'], false );
hodima_lt( 'آدرس نامعتبر در دیتابیس خوانده نمی‌شود', hodima_core_login_sanitize( [ 'logo_url' => 'javascript:alert(1)' ], true )['logo_url'], '' );

echo "=== حرف اول نام سایت\n";
$old_name = (string) get_option( 'blogname' );
update_option( 'blogname', 'هدهدلی' );
hodima_lt( 'نام فارسی', hodima_core_login_letter(), 'ه' );
update_option( 'blogname', '"><b>x' );
hodima_lt( 'نام با نویسه خطرناک: فقط حرف', hodima_core_login_letter(), 'b' );
update_option( 'blogname', '«»!!' );
hodima_lt( 'نام بی‌حرف: جایگزین', hodima_core_login_letter(), 'ه' );
update_option( 'blogname', $old_name );

echo "=== متغیرهای CSS چاپ‌شده\n";
update_option( HODIMA_CORE_LOGIN_OPTION, [ 'enabled' => true, 'logo_url' => 'https://example.com/logo.png', 'logo_height' => 64 ] );
wp_styles()->registered = array_diff_key( wp_styles()->registered, [ 'hodima-core-login' => 1 ] );
do_action( 'login_enqueue_scripts' );
$after = (string) implode( '', (array) ( wp_styles()->get_data( 'hodima-core-login', 'after' ) ?: [] ) );
hodima_lt( 'آدرس لوگو در CSS', str_contains( $after, '--hd-login-logo:url("https://example.com/logo.png");' ), true );
hodima_lt( 'ارتفاع لوگو در CSS', str_contains( $after, '--hd-login-logo-h:64px;' ), true );
hodima_lt( 'حرف اول در CSS', 1 === preg_match( '/--hd-login-letter:"[\p{L}\p{N}]"/u', $after ), true );

// آدرس خراب که مستقیم در دیتابیس نوشته شده (دور زدن فرم) باز هم وارد CSS نمی‌شود
update_option( HODIMA_CORE_LOGIN_OPTION, [ 'enabled' => true, 'logo_url' => 'https://e.com/a.png");}body{display:none', 'logo_height' => 48 ] );
wp_styles()->registered = array_diff_key( wp_styles()->registered, [ 'hodima-core-login' => 1 ] );
do_action( 'login_enqueue_scripts' );
$after = (string) implode( '', (array) ( wp_styles()->get_data( 'hodima-core-login', 'after' ) ?: [] ) );
hodima_lt( 'آدرس خراب دیتابیس در CSS نیست', str_contains( $after, 'display:none' ) || str_contains( $after, '--hd-login-logo:' ), false );

echo "=== کلاس بدنه و خاموش شدن\n";
update_option( HODIMA_CORE_LOGIN_OPTION, [ 'enabled' => true, 'logo_url' => '', 'logo_height' => 48 ] );
$classes = (array) apply_filters( 'login_body_class', [ 'login' ], 'login' );
hodima_lt( 'بدون لوگو: کلاس حرف', in_array( 'hodima-login--letter', $classes, true ) && in_array( 'hodima-login', $classes, true ), true );
update_option( HODIMA_CORE_LOGIN_OPTION, [ 'enabled' => true, 'logo_url' => 'https://example.com/logo.png', 'logo_height' => 48 ] );
$classes = (array) apply_filters( 'login_body_class', [ 'login' ], 'login' );
hodima_lt( 'با لوگو: کلاس لوگو', in_array( 'hodima-login--logo', $classes, true ), true );
update_option( HODIMA_CORE_LOGIN_OPTION, [ 'enabled' => false, 'logo_url' => '', 'logo_height' => 48 ] );
hodima_lt( 'کلید خاموش: ظاهر خاموش', hodima_core_login_look_active(), false );
hodima_lt( 'کلید خاموش: کلاس بدنه نمی‌آید', in_array( 'hodima-login', (array) apply_filters( 'login_body_class', [ 'login' ], 'login' ), true ), false );
hodima_lt( 'کلید خاموش: آدرس لوگوی وردپرس برمی‌گردد', apply_filters( 'login_headerurl', 'https://wordpress.org/' ), 'https://wordpress.org/' );
update_option( HODIMA_CORE_LOGIN_OPTION, [ 'enabled' => true, 'logo_url' => '', 'logo_height' => 48 ] );
hodima_lt( 'کلید روشن: لوگو به خود سایت لینک می‌شود', apply_filters( 'login_headerurl', 'https://wordpress.org/' ), home_url( '/' ) );
hodima_lt( 'عنوان صفحه بدون نام وردپرس', str_contains( (string) apply_filters( 'login_title', 'Log In ‹ x — WordPress', 'ورود' ), 'WordPress' ), false );

echo "=== پیام خطای یکسان\n";
$e = new WP_Error();
$e->add( 'invalid_username', '<strong>خطا:</strong> نام کاربری ali ثبت نشده است.' );
$out = apply_filters( 'wp_login_errors', $e, '' );
hodima_lt( 'نام کاربری نامعتبر: پیام جدید', $out->get_error_codes(), [ 'hodima_login_failed' ] );
$e = new WP_Error();
$e->add( 'incorrect_password', '<strong>خطا:</strong> رمز ali اشتباه است.' );
$out = apply_filters( 'wp_login_errors', $e, '' );
hodima_lt( 'رمز غلط: همان پیام', $out->get_error_codes(), [ 'hodima_login_failed' ] );
hodima_lt( 'پیام نام کاربری را تکرار نمی‌کند', str_contains( $out->get_error_message(), 'ali' ), false );
$e = new WP_Error();
$e->add( 'invalid_email', 'x' );
$e->add( 'incorrect_password', 'y' );
hodima_lt( 'چند پیام لو‌دهنده: فقط یکی می‌ماند', apply_filters( 'wp_login_errors', $e, '' )->get_error_codes(), [ 'hodima_login_failed' ] );
$e = new WP_Error();
$e->add( 'empty_password', 'رمز خالی' );
hodima_lt( 'خطای خالی بودن رمز دست‌نخورده', apply_filters( 'wp_login_errors', $e, '' )->get_error_codes(), [ 'empty_password' ] );
$e = new WP_Error();
$e->add( 'invalidcombo', 'بازیابی' );
hodima_lt( 'بازیابی رمز (انتشار بعدی) دست‌نخورده', apply_filters( 'wp_login_errors', $e, '' )->get_error_codes(), [ 'invalidcombo' ] );
add_filter( 'hodima_core_login_generic_errors', '__return_false' );
$e = new WP_Error();
$e->add( 'invalid_username', 'x' );
hodima_lt( 'فیلتر خاموش‌کننده کار می‌کند', apply_filters( 'wp_login_errors', $e, '' )->get_error_codes(), [ 'invalid_username' ] );
remove_filter( 'hodima_core_login_generic_errors', '__return_false' );

echo "=== حدس ?author=N\n";
hodima_lt( 'author=1 بازدیدکننده', hodima_core_login_is_author_probe( [ 'author' => '1' ], false ), true );
hodima_lt( 'author=-1', hodima_core_login_is_author_probe( [ 'author' => '-1' ], false ), true );
hodima_lt( 'author=12 وارد‌شده آزاد است', hodima_core_login_is_author_probe( [ 'author' => '12' ], true ), false );
hodima_lt( 'author=abc (نام) حدس شمارشی نیست', hodima_core_login_is_author_probe( [ 'author' => 'abc' ], false ), false );
hodima_lt( 'author آرایه‌ای', hodima_core_login_is_author_probe( [ 'author' => [ '1' ] ], false ), false );
hodima_lt( 'بدون author', hodima_core_login_is_author_probe( [ 's' => 'x' ], false ), false );
hodima_lt( 'author=1abc', hodima_core_login_is_author_probe( [ 'author' => '1abc' ], false ), false );

echo "=== فهرست کاربران REST\n";
$routes = [ '/wp/v2/users' => [], '/wp/v2/users/(?P<id>[\d]+)' => [], '/wp/v2/users/me' => [], '/wp/v2/posts' => [], '/wp/v2/usersx' => [] ];
wp_set_current_user( 0 );
hodima_lt( 'بازدیدکننده: مسیرهای کاربران حذف', array_keys( (array) apply_filters( 'rest_endpoints', $routes ) ), [ '/wp/v2/posts', '/wp/v2/usersx' ] );
wp_set_current_user( 1 );
hodima_lt( 'وارد‌شده: همه می‌ماند', array_keys( (array) apply_filters( 'rest_endpoints', $routes ) ), array_keys( $routes ) );
wp_set_current_user( 0 );

echo "=== هدرهای امنیتی\n";
$h = hodima_core_login_security_headers();
hodima_lt( 'nosniff', $h['X-Content-Type-Options'] ?? '', 'nosniff' );
hodima_lt( 'Referrer-Policy', $h['Referrer-Policy'] ?? '', 'strict-origin-when-cross-origin' );
$merged = (array) apply_filters( 'wp_headers', [ 'Referrer-Policy' => 'no-referrer' ] );
hodima_lt( 'هدر موجود جای هدر ما را نمی‌گیرد', $merged['Referrer-Policy'], 'no-referrer' );
hodima_lt( 'هدر تازه اضافه می‌شود', $merged['X-Content-Type-Options'] ?? '', 'nosniff' );

// برگرداندن داده آزمایشی
delete_option( HODIMA_CORE_LOGIN_OPTION );

echo "\n";
if ( $failed ) {
	echo "✘ {$failed} آزمون رد شد ({$passed} قبول)\n";
	exit( 1 );
}
echo "✔ همه {$passed} آزمون قبول\n";
