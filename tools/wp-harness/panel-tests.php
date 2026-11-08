<?php
/**
 * آزمون خودکار Hodima Panel (پنل کاربری): فعال‌سازی، جدول‌ها، برگه، فایل‌های سفارش،
 * اقلام با قیمت لحظه ثبت، شمارنده‌های گفتگو، حذف کامل سفارش، زمان UTC، رندر واقعی
 * همه بخش‌های پنل (مهمان و مشتری) و صفحه‌های پیشخوان بدون هشدار PHP، قفل پروفایل
 * پیش از چاپ، مالکیت گفتگو و nonce حذف گروهی.
 *   HARNESS=1 HARNESS_WC=1 php tools/wp-harness/panel-tests.php
 * خروجی: هر آزمون ✔/✘؛ کد خروج ۱ اگر یکی رد شود. افزونه را خودش فعال می‌کند (اجرای دوم
 * همین فایل با افزونه لودشده). ci-check.sh هم اجرا می‌کند.
 */

declare(strict_types=1);

$_SERVER['HTTP_HOST'] = $_SERVER['SERVER_NAME'] = 'hodima.test';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['HTTPS']       = 'on';
$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
$hp_wp_dir              = rtrim( (string) ( getenv( 'HODIMA_WP' ) ?: '/tmp/hodima-harness/wp' ), '/' );
require $hp_wp_dir . '/wp-load.php';

if ( ! getenv( 'HARNESS' ) ) {
	fwrite( STDERR, "این آزمون داده می‌سازد و پاک می‌کند؛ فقط روی ابزار تست: HARNESS=1\n" );
	exit( 2 );
}

// مرحله ۱: فعال‌سازی واقعی (هوک فعال‌سازی) و اجرای دوباره با افزونه لودشده
if ( ! defined( 'HODIMA_PANEL_VERSION' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	$r = activate_plugin( 'hodima-panel/hodima-panel.php' );
	if ( is_wp_error( $r ) ) {
		echo '✘ فعال‌سازی: ' . $r->get_error_message() . "\n";
		exit( 1 );
	}
	passthru( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ), $code );
	exit( (int) $code );
}

use Hodima\Panel\Files;
use Hodima\Panel\Install;
use Hodima\Panel\Page;
use Hodima\Panel\Profile;
use Hodima\Panel\Sms;
use Hodima\Panel\Otp;
use Hodima\Panel\Auth;
use Hodima\Panel\Admin_Sms;
use function Hodima\Panel\table;

$failed = 0;
$passed = 0;

function hodima_pt( string $name, mixed $actual, mixed $expected ): void {
	global $failed, $passed;
	if ( $actual === $expected ) {
		++$passed;
		echo "  ✔ {$name}\n";
		return;
	}
	++$failed;
	echo "  ✘ {$name}\n      انتظار: " . var_export( $expected, true ) . "\n      واقعی:  " . var_export( $actual, true ) . "\n";
}

/** خطای «ریدایرکت/پایان» به جای exit تا آزمون ادامه یابد. */
final class Hodima_Pt_Stop extends RuntimeException {}

// اولویت ۰: پیش از فیلتر ابزار تست (harness-core.php) که ریدایرکت را فقط چاپ می‌کند
add_filter( 'wp_redirect', static function ( mixed $location ): never {
	throw new Hodima_Pt_Stop( 'redirect:' . (string) $location );
}, 0 );
$hodima_pt_die = static fn(): callable => static function ( $message ): never {
	throw new Hodima_Pt_Stop( 'die:' . ( is_string( $message ) ? $message : wp_json_encode( $message ) ) );
};
add_filter( 'wp_die_handler', $hodima_pt_die );
// کوکی ورود در CLI بعد از چاپ خروجی آزمون فقط هشدار «headers already sent» می‌دهد
add_filter( 'send_auth_cookies', '__return_false' );
add_filter( 'wp_die_ajax_handler', $hodima_pt_die );
add_filter( 'wp_die_json_handler', $hodima_pt_die );

/** اجرای یک تابع و برگرداندن پیام Hodima_Pt_Stop (یا '' اگر عادی تمام شد). */
function hodima_pt_run( callable $fn ): string {
	ob_start();
	try {
		$fn();
		return '';
	} catch ( Hodima_Pt_Stop $e ) {
		return $e->getMessage();
	} finally {
		$GLOBALS['hodima_pt_last_output'] = (string) ob_get_clean();
	}
}

/** رندر کامل یک آدرس سایت در فرایند جدا (render.php)؛ [html، stderr]. */
function hodima_pt_render( string $path, int $user = 0 ): array {
	$env = 'HARNESS=1 HARNESS_WC=1 HODIMA_WP=' . escapeshellarg( (string) getenv( 'HODIMA_WP' ) ?: '/tmp/hodima-harness/wp' ) . ( $user ? ' HARNESS_USER=' . $user : '' );
	$err = tempnam( sys_get_temp_dir(), 'hp' );
	$out = (string) shell_exec( $env . ' ' . escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/render.php' ) . ' ' . escapeshellarg( $path ) . ' full 2>' . escapeshellarg( (string) $err ) );
	$stderr = (string) file_get_contents( (string) $err );
	unlink( (string) $err );
	return [ $out, trim( $stderr ) ];
}

global $wpdb;

echo "== فعال‌سازی، جدول‌ها، برگه\n";
foreach ( array_keys( Install::schema() ) as $name ) {
	hodima_pt( "جدول {$name} ساخته شده", (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', table( $name ) ) ), table( $name ) );
}
hodima_pt( 'نسخه پایگاه داده ثبت شده', get_option( Install::DB_OPTION ), Install::DB_VERSION );
$page_id = Page::id();
hodima_pt( 'برگه پنل ساخته و منتشر شده', $page_id > 0 && 'publish' === get_post_status( $page_id ), true );
hodima_pt( 'نامک برگه user-panel', get_post_field( 'post_name', $page_id ), 'user-panel' );
hodima_pt( 'فعال‌سازی دوباره برگه دوم نمی‌سازد', ( static function (): int {
	Install::activate();
	return count( get_posts( [ 'post_type' => 'page', 'name' => 'user-panel', 'post_status' => 'any', 'fields' => 'ids' ] ) );
} )(), 1 );
hodima_pt( 'آدرس بخش', Page::url( [ 'section' => 'orders' ] ), get_permalink( $page_id ) . '?section=orders' );
hodima_pt( 'robots.txt: مسیر پنل خصوصی', in_array( '/user-panel/', Page::private_paths( [] ), true ), true );

echo "== زمان (ذخیره UTC، نمایش به ساعت سایت)\n";
update_option( 'timezone_string', 'Asia/Tehran' );
// ارقام فارسی را ماژول جلالی Commerce می‌سازد؛ فقط ساعت مهم است
hodima_pt( 'UTC ← ساعت تهران', strtr( \Hodima\Panel\show_date( '2025-01-01 00:00:00', 'H:i' ), [ '۰' => '0', '۳' => '3' ] ), '03:30' );
hodima_pt( 'تاریخ خالی', \Hodima\Panel\show_date( '0000-00-00 00:00:00' ), '' );

// داده آزمون: دو مشتری، یک محصول پیش‌خرید
$hp_users = [];
foreach ( [ 'hp_a' => '09120000001', 'hp_b' => '09120000002' ] as $login => $mobile ) {
	$existing = get_user_by( 'login', $login );
	if ( $existing ) {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( $existing->ID );
	}
	$id = wp_create_user( $login, wp_generate_password(), $login . '@hodima.test' );
	update_user_meta( $id, 'mobile', $mobile );
	// پروفایل کامل = داده صورت‌حساب ووکامرس (مرحله ۳)
	foreach ( [ 'billing_first_name' => 'مشتری', 'billing_last_name' => $login, 'billing_email' => $login . '@hodima.test', 'billing_address_1' => 'تهران، خیابان آزمون، پلاک ۱', 'billing_postcode' => '1234567890' ] as $mk => $mv ) {
		update_user_meta( $id, $mk, $mv );
	}
	$hp_users[] = (int) $id;
}
[ $user_a, $user_b ] = $hp_users;
$product = (int) wp_insert_post( [ 'post_type' => 'product', 'post_status' => 'publish', 'post_title' => 'گیره آزمون پنل' ] );
update_post_meta( $product, '_price', '1250' );
update_post_meta( $product, '_up_unit_capacity', '12' );
update_post_meta( $product, '_up_sale_unit', 'کیلو' );
update_post_meta( $product, '_prebuy_enabled', 'yes' );
update_post_meta( $product, '_up_min_qty', '0.5' );
$carton = (int) wp_insert_post( [ 'post_type' => 'product', 'post_status' => 'publish', 'post_title' => 'کش کارتنی آزمون' ] );
update_post_meta( $carton, '_price', '100' );
update_post_meta( $carton, '_up_sale_unit', 'کارتن' );
update_post_meta( $carton, '_up_min_qty', '2' );
update_post_meta( $carton, '_prebuy_enabled', 'yes' );
$draft = (int) wp_insert_post( [ 'post_type' => 'product', 'post_status' => 'draft', 'post_title' => 'پیش‌نویس' ] );
update_post_meta( $draft, '_prebuy_enabled', 'yes' );

echo "== اعتبارسنجی اقلام در سرور (حداقل، مضرب، محصول مجاز)\n";
$v = \Hodima\Panel\Orders::validate_items( 'prebuy', [ $product, $carton ], [ $product => '2.5', $carton => '3' ] );
hodima_pt( 'کیلو مضرب ۰٫۵ و کارتن بالای حداقل (مضرب لازم نیست) پذیرفته', [ count( $v['items'] ), $v['errors'] ], [ 2, [] ] );
$v = \Hodima\Panel\Orders::validate_items( 'prebuy', [ $product ], [ $product => '2.3' ] );
hodima_pt( 'کیلو غیرمضرب رد', [ count( $v['items'] ), count( $v['errors'] ) ], [ 0, 1 ] );
$v = \Hodima\Panel\Orders::validate_items( 'prebuy', [ $carton ], [ $carton => '1' ] );
hodima_pt( 'کمتر از حداقل رد', [ count( $v['items'] ), str_contains( $v['errors'][0] ?? '', 'حداقل سفارش 2' ) ], [ 0, true ] );
$v = \Hodima\Panel\Orders::validate_items( 'prebuy', [ $carton ], [ $carton => 'abc' ] );
hodima_pt( 'تعداد غیرعددی رد', count( $v['errors'] ), 1 );
$v = \Hodima\Panel\Orders::validate_items( 'prebuy', [ $carton ], [ $carton => '999999999' ] );
hodima_pt( 'تعداد ناممکن رد', count( $v['errors'] ), 1 );
$v = \Hodima\Panel\Orders::validate_items( 'preinvoice', [ $product ], [ $product => '1' ] );
hodima_pt( 'محصولی که برای پیش‌فاکتور فعال نیست رد', count( $v['errors'] ), 1 );
$v = \Hodima\Panel\Orders::validate_items( 'prebuy', [ $draft, 999999 ], [ $draft => '5', 999999 => '5' ] );
hodima_pt( 'پیش‌نویس و ناموجود رد', [ count( $v['items'] ), count( $v['errors'] ) ], [ 0, 2 ] );
$variable = (int) wp_insert_post( [ 'post_type' => 'product', 'post_status' => 'publish', 'post_title' => 'محصول چندمدلی آزمون' ] );
update_post_meta( $variable, '_hx_type', 'variable' );
update_post_meta( $variable, '_price', '100' );
update_post_meta( $variable, '_prebuy_enabled', 'yes' );
$noprice = (int) wp_insert_post( [ 'post_type' => 'product', 'post_status' => 'publish', 'post_title' => 'محصول بی‌قیمت آزمون' ] );
update_post_meta( $noprice, '_prebuy_enabled', 'yes' );
update_post_meta( $noprice, '_up_sale_unit', "\u{200F} كارتن  " );
update_post_meta( $noprice, '_up_min_qty', '2' );
$v = \Hodima\Panel\Orders::validate_items( 'prebuy', [ $variable, $noprice ], [ $variable => '1', $noprice => '2' ] );
hodima_pt( 'محصول چندمدلی و بی‌قیمت رد (سفارش با مبلغ ۰ ساخته نمی‌شود)', [ count( $v['items'] ), count( $v['errors'] ), str_contains( $v['errors'][0] ?? '', 'قیمت' ) ], [ 0, 2, true ] );
$v = \Hodima\Panel\Orders::validate_items( 'prebuy', [ $carton ], [ $carton => '999999999' ] );
hodima_pt( 'بیشتر از سقف: پیام «بیشترین تعداد»، نه «حداقل»', [ str_contains( $v['errors'][0] ?? '', 'بیشترین تعداد' ), str_contains( $v['errors'][0] ?? '', 'حداقل' ) ], [ true, false ] );
$rules = \Hodima\Panel\Orders::product_rules( $noprice );
hodima_pt( 'واحد فروش یکدست («ك» عربی، فاصله، نشانه جهت) ← «کارتن» بی قانون مضرب', [ $rules['unit'], $rules['multiple'] ], [ 'کارتن', false ] );

echo "== ثبت سفارش و اقلام (قیمت و ظرفیت لحظه ثبت، تعداد اعشاری)\n";
$now = \Hodima\Panel\now();
$wpdb->insert( table( 'orders' ), [ 'user_id' => $user_a, 'type' => 'prebuy', 'status' => 'pending', 'created_at' => $now, 'updated_at' => $now ] );
$order_a = (int) $wpdb->insert_id;
$v       = \Hodima\Panel\Orders::validate_items( 'prebuy', [ (string) $product, (string) $product, '999999' ], [ $product => '2.5' ] );
$total   = \Hodima\Panel\Orders::save_items( $order_a, $v['items'] );
hodima_pt( 'جمع = ۲٫۵ × ۱۲ × ۱۲۵۰', $total, 37500.0 );
$item = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE order_id = %d', table( 'order_items' ), $order_a ) );
hodima_pt( 'محصول تکراری و ناموجود ذخیره نشد', (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE order_id = %d', table( 'order_items' ), $order_a ) ), 1 );
hodima_pt( 'تعداد اعشاری درست ذخیره شد', (float) $item->quantity, 2.5 );
hodima_pt( 'قیمت و ظرفیت لحظه ثبت', [ (float) $item->unit_price, (int) $item->unit_capacity, (float) $item->line_total ], [ 1250.0, 12, 37500.0 ] );
update_post_meta( $product, '_price', '9999' );
hodima_pt( 'تغییر قیمت بعدی اقلام ثبت‌شده را عوض نمی‌کند', (float) $wpdb->get_var( $wpdb->prepare( 'SELECT unit_price FROM %i WHERE order_id = %d', table( 'order_items' ), $order_a ) ), 1250.0 );
update_post_meta( $product, '_price', '1250' );
$wpdb->update( table( 'orders' ), [ 'total_price' => $total ], [ 'id' => $order_a ] );

echo "== فرم پیش‌خرید (admin-post)\n";
wp_set_current_user( $user_b );
$_POST = $_REQUEST = [ 'action' => 'save_prebuy', '_wpnonce' => wp_create_nonce( 'user_panel_action' ), 'products' => [ (string) $product ], 'qty' => [ $product => '3' ] ];
$stop  = hodima_pt_run( [ \Hodima\Panel\Orders::class, 'handle_form' ] );
$order_b = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE user_id = %d ORDER BY id DESC LIMIT 1', table( 'orders' ), $user_b ) );
hodima_pt( 'سفارش ثبت و به فهرست سفارش‌ها رفت', [ str_contains( $stop, 'section=orders' ), $order_b > 0 ], [ true, true ] );
hodima_pt( 'مبلغ کل سفارش ذخیره شد', (float) $wpdb->get_var( $wpdb->prepare( 'SELECT total_price FROM %i WHERE id = %d', table( 'orders' ), $order_b ) ), 45000.0 );
$_POST = $_REQUEST = [ 'action' => 'save_prebuy', '_wpnonce' => wp_create_nonce( 'user_panel_action' ), 'order_id' => (string) $order_a, 'products' => [ (string) $product ] ];
hodima_pt( 'ویرایش سفارش مشتری دیگر رد شد', str_starts_with( hodima_pt_run( [ \Hodima\Panel\Orders::class, 'handle_form' ] ), 'die:' ), true );
$_POST = $_REQUEST = [ 'action' => 'save_prebuy', '_wpnonce' => wp_create_nonce( 'user_panel_action' ), 'products' => [ (string) $product ], 'qty' => [ $product => '0.2' ] ];
$stop  = hodima_pt_run( [ \Hodima\Panel\Orders::class, 'handle_form' ] );
hodima_pt( 'تعداد نامعتبر ← برگشت به فرم با فهرست خطا، بی سفارش تازه', [ str_contains( $stop, 'msg=invalid_items' ), count( (array) get_transient( 'hodima_panel_errors_' . $user_b ) ), (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE user_id = %d', table( 'orders' ), $user_b ) ) ], [ true, 1, 1 ] );
$hp_draft = get_transient( 'hodima_panel_draft_' . $user_b );
hodima_pt( 'ورودی فرم ردشده برای پر کردن دوباره نگه داشته شد', [ $hp_draft['type'] ?? '', $hp_draft['order_id'] ?? -1, $hp_draft['qty'][ $product ] ?? null ], [ 'prebuy', 0, 0.2 ] );
[ $html ] = hodima_pt_render( '/user-panel/?section=prebuy', $user_b );
wp_cache_flush(); // رندر در پردازش جدا ترنزینت را پاک کرد؛ کش همین پردازش کهنه است
hodima_pt( 'فرم بعد از خطا: همان تعداد و انتخاب، خطا، یک‌بار؛ محصول چندمدلی/بی‌قیمت در فرم نیست', [ (bool) preg_match( '/name="qty\[' . $product . '\]"\s+value="0.2"/', $html ), (bool) preg_match( '/value="' . $product . '" checked/', $html ), str_contains( $html, 'hpn-notice--error' ), false === get_transient( 'hodima_panel_draft_' . $user_b ), str_contains( $html, 'محصول چندمدلی آزمون' ), str_contains( $html, 'محصول بی‌قیمت آزمون' ) ], [ true, true, true, true, false, false ] );
// همه یا هیچ: خطای ذخیره اقلام سفارش تازه‌ای باقی نمی‌گذارد
$hp_break = static fn( string $q ): string => str_starts_with( ltrim( $q ), 'INSERT' ) && str_contains( $q, 'hodima_panel_order_items' ) ? 'SELECT * FROM hp_no_such_table' : $q;
add_filter( 'query', $hp_break );
$hp_orders_b = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE user_id = %d', table( 'orders' ), $user_b ) );
$_POST = $_REQUEST = [ 'action' => 'save_prebuy', '_wpnonce' => wp_create_nonce( 'user_panel_action' ), 'products' => [ (string) $product ], 'qty' => [ $product => '1' ] ];
$wpdb->suppress_errors( true );
$stop  = hodima_pt_run( [ \Hodima\Panel\Orders::class, 'handle_form' ] );
$wpdb->suppress_errors( false );
remove_filter( 'query', $hp_break );
hodima_pt( 'خطای ذخیره اقلام: سفارش نیمه‌کاره نمی‌ماند، پیام خطا', [ str_contains( $stop, 'msg=db_error' ), (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE user_id = %d', table( 'orders' ), $user_b ) ) - $hp_orders_b ], [ true, 0 ] );
delete_transient( 'hodima_panel_draft_' . $user_b );
// ویرایش: نوع فرم دیگر نوع سفارش را عوض نمی‌کند؛ بعد از بررسی مدیر قفل است
$_POST = $_REQUEST = [ 'action' => 'save_preinvoice', '_wpnonce' => wp_create_nonce( 'user_panel_action' ), 'order_id' => (string) $order_b, 'products' => [ (string) $product ], 'qty' => [ $product => '1' ] ];
hodima_pt_run( [ \Hodima\Panel\Orders::class, 'handle_form' ] );
hodima_pt( 'ویرایش با فرم پیش‌فاکتور نوع پیش‌خرید را نگه داشت', $wpdb->get_var( $wpdb->prepare( 'SELECT type FROM %i WHERE id = %d', table( 'orders' ), $order_b ) ), 'prebuy' );
update_post_meta( $product, '_prebuy_enabled', 'no' );
[ $html ] = hodima_pt_render( '/user-panel/?section=prebuy&edit_order=' . $order_b, $user_b );
update_post_meta( $product, '_prebuy_enabled', 'yes' );
hodima_pt( 'ویرایش سفارش با قلمی که دیگر فعال نیست: هشدار با نام قلم', [ str_contains( $html, 'دیگر برای پیش‌خرید فعال نیستند' ), str_contains( $html, 'گیره آزمون پنل' ) ], [ true, true ] );
$wpdb->update( table( 'orders' ), [ 'status' => 'awaiting_payment' ], [ 'id' => $order_b ] );
$_POST = $_REQUEST = [ 'action' => 'save_prebuy', '_wpnonce' => wp_create_nonce( 'user_panel_action' ), 'order_id' => (string) $order_b, 'products' => [ (string) $product ], 'qty' => [ $product => '50' ] ];
$stop  = hodima_pt_run( [ \Hodima\Panel\Orders::class, 'handle_form' ] );
hodima_pt( 'ویرایش سفارش در انتظار پرداخت قفل است', [ str_contains( $stop, 'msg=locked' ), (float) $wpdb->get_var( $wpdb->prepare( 'SELECT quantity FROM %i WHERE order_id = %d', table( 'order_items' ), $order_b ) ) ], [ true, 1.0 ] );
$_POST = $_REQUEST = [];
$_GET  = [ 'action' => 'delete_order', 'order_id' => (string) $order_b, '_wpnonce' => wp_create_nonce( 'delete_order_' . $order_b ) ];
$_REQUEST = $_GET;
$stop  = hodima_pt_run( [ \Hodima\Panel\Orders::class, 'handle_request' ] );
hodima_pt( 'حذف سفارش در انتظار پرداخت رد (مدرک مالی می‌ماند)', [ str_contains( $stop, 'msg=locked' ), null !== \Hodima\Panel\Orders::get( $order_b ) ], [ true, true ] );
$_POST = $_REQUEST = [ 'upload_receipt' => '1', 'order_id' => (string) $order_b, '_wpnonce_receipt' => wp_create_nonce( 'upload_receipt_' . $order_b ) ];
$_GET  = [];
$wpdb->update( table( 'orders' ), [ 'status' => 'cancelled' ], [ 'id' => $order_b ] );
$stop  = hodima_pt_run( [ \Hodima\Panel\Orders::class, 'handle_request' ] );
hodima_pt( 'فیش برای سفارش لغوشده رد و وضعیت لغو ماند', [ str_contains( $stop, 'msg=receipt_not_allowed' ), $wpdb->get_var( $wpdb->prepare( 'SELECT status FROM %i WHERE id = %d', table( 'orders' ), $order_b ) ) ], [ true, 'cancelled' ] );
$wpdb->update( table( 'orders' ), [ 'status' => 'pending' ], [ 'id' => $order_b ] );
hodima_pt( 'فیش برای سفارش در انتظار بررسی رد', str_contains( hodima_pt_run( [ \Hodima\Panel\Orders::class, 'handle_request' ] ), 'msg=receipt_not_allowed' ), true );
$_POST = $_REQUEST = [];
$_GET  = [ 'action' => 'delete_order', 'order_id' => (string) $order_b, '_wpnonce' => wp_create_nonce( 'delete_order_' . $order_b ) ];
$_REQUEST = $_GET;
$stop  = hodima_pt_run( [ \Hodima\Panel\Orders::class, 'handle_request' ] );
hodima_pt( 'حذف سفارش در انتظار بررسی', [ str_contains( $stop, 'msg=deleted' ), null === \Hodima\Panel\Orders::get( $order_b ) ], [ true, true ] );
$_GET = $_REQUEST = [];
$_POST = $_REQUEST = [];

echo "== گفتگوی سفارش (شمارنده خوانده‌نشده)\n";
wp_set_current_user( $user_a );
\Hodima\Panel\Orders::add_chat_message( $order_a, $user_a, 'user', 'سلام' );
\Hodima\Panel\Orders::add_chat_message( $order_a, $user_a, 'system', 'فیش آپلود شد' );
$chat = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE order_id = %d', table( 'chats' ), $order_a ) );
hodima_pt( 'پیام مشتری و سیستمی برای مدیر نخوانده (پیام فیش واقعا ثبت شد)', [ (int) $chat->admin_unread, (int) $chat->user_unread ], [ 2, 0 ] );
wp_set_current_user( 1 );
\Hodima\Panel\Orders::add_chat_message( $order_a, $user_a, 'admin', 'پاسخ' );
\Hodima\Panel\Orders::add_chat_message( $order_a, $user_a, 'system', 'پیش‌فاکتور صادر شد', 'user' );
$chat = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE order_id = %d', table( 'chats' ), $order_a ) );
hodima_pt( 'پیام مدیر و سیستمی‌برای‌مشتری برای مشتری نخوانده', (int) $chat->user_unread, 2 );
hodima_pt( 'آخرین پیام', [ $chat->last_message_preview, $chat->last_message_sender ], [ 'پیش‌فاکتور صادر شد', 'system' ] );
hodima_pt( 'چهار پیام ذخیره شد', (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE chat_id = %d', table( 'chat_messages' ), $chat->id ) ), 4 );

echo "== فایل‌های سفارش (پوشه خصوصی، نوع واقعی، اندازه، دسترسی)\n";
$tmpdir = get_temp_dir();
$png    = $tmpdir . 'hp-test.png';
file_put_contents( $png, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==' ) );
$pdf = $tmpdir . 'hp-test.pdf';
file_put_contents( $pdf, "%PDF-1.4\n%test\n" );
$fake = $tmpdir . 'hp-fake.png';
file_put_contents( $fake, '<?php echo 1;' );
$fid = Files::store( $order_a, Files::RECEIPT, $png, 'فیش من.png', false );
hodima_pt( 'فیش PNG واقعی ذخیره شد', is_int( $fid ) && $fid > 0, true );
$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', table( 'order_files' ), $fid ) );
$path = Files::path( (string) $row->file );
hodima_pt( 'در پوشه خصوصی، با نام تصادفی', [ str_starts_with( (string) $path, Files::dir() . '/' ), str_contains( (string) $row->file, 'فیش' ), is_file( (string) $path ), $row->mime ], [ true, false, true, 'image/png' ] );
hodima_pt( 'پوشه خصوصی بسته است (.htaccess و index.php)', [ str_contains( (string) file_get_contents( Files::dir() . '/.htaccess' ), 'Require all denied' ), is_file( Files::dir() . '/index.php' ) ], [ true, true ] );
hodima_pt( 'پوشه خصوصی بیرون از مسیر uploads عمومی سال/ماه', str_contains( Files::dir(), 'hodima-panel-private' ), true );
hodima_pt( 'PDF واقعی پذیرفته', is_int( Files::store( $order_a, Files::INVOICE, $pdf, 'f.pdf', false ) ), true );
hodima_pt( 'PHP با پسوند png رد', Files::store( $order_a, Files::RECEIPT, $fake, 'x.png', false ), 'file_type' );
hodima_pt( 'PDF ساختگی رد', Files::store( $order_a, Files::RECEIPT, $fake, 'x.pdf', false ), 'file_type' );
hodima_pt( 'پسوند php/svg رد', [ Files::store( $order_a, Files::RECEIPT, $png, 'x.php', false ), Files::store( $order_a, Files::RECEIPT, $png, 'x.svg', false ) ], [ 'file_type', 'file_type' ] );
$big = $tmpdir . 'hp-big.png';
file_put_contents( $big, str_repeat( 'a', Files::MAX_BYTES + 1 ) );
hodima_pt( 'بیشتر از ۵ مگابایت رد', Files::store( $order_a, Files::RECEIPT, $big, 'big.png', false ), 'file_size' );
hodima_pt( 'آپلود بدون فایل', Files::upload( $order_a, Files::RECEIPT, [] ), 'file_missing' );
hodima_pt( 'فایلی که آپلود HTTP نیست رد', Files::upload( $order_a, Files::RECEIPT, [ 'tmp_name' => $png, 'name' => 'a.png', 'error' => UPLOAD_ERR_OK ] ), 'file_save' );
for ( $i = Files::count( $order_a, Files::RECEIPT ); $i < Files::MAX_PER_KIND; $i++ ) {
	Files::store( $order_a, Files::RECEIPT, $png, 'r.png', false );
}
hodima_pt( 'سقف ۹ فیش', Files::store( $order_a, Files::RECEIPT, $png, 'r.png', false ), 'file_limit' );
hodima_pt( 'شمارش فیش', Files::count( $order_a, Files::RECEIPT ), 9 );
$rows = Files::for_order( $order_a, Files::RECEIPT );
hodima_pt( 'آدرس فایل = نشانی امن (نه آدرس مستقیم)', $rows[0]->url ?? '', home_url( '/?hodima_panel_file=' . $fid ) );
hodima_pt( 'صاحب سفارش و مدیر می‌بینند', [ Files::resolve( $fid, $user_a )['mime'] ?? '', Files::resolve( $fid, 1 )['mime'] ?? '' ], [ 'image/png', 'image/png' ] );
hodima_pt( 'مشتری دیگر و مهمان نمی‌بینند', [ Files::resolve( $fid, $user_b ), Files::resolve( $fid, 0 ) ], [ null, null ] );
hodima_pt( 'مسیر با ../ پذیرفته نمی‌شود', [ Files::path( '../wp-config.php' ), Files::path( '/etc/passwd' ) ], [ null, null ] );
$wpdb->update( table( 'orders' ), [ 'status' => 'receipt_uploaded' ], [ 'id' => $order_a ] );
hodima_pt( 'با ۹ فیش، فیش تازه مجاز نیست', \Hodima\Panel\Orders::can_upload_receipt( \Hodima\Panel\Orders::get( $order_a ) ), false );
foreach ( [ 'pending' => [ true, false ], 'awaiting_payment' => [ false, true ], 'completed' => [ false, false ], 'cancelled' => [ false, false ] ] as $st => $want ) {
	$o = (object) [ 'id' => 0, 'status' => $st ];
	hodima_pt( "قانون وضعیت {$st}: ویرایش/فیش", [ \Hodima\Panel\Orders::can_edit( $o ), \Hodima\Panel\Orders::can_upload_receipt( $o ) ], $want );
}
$path = (string) $path;

echo "== مالکیت گفتگو (مشتری دیگر نمی‌تواند در گفتگوی سفارش من بنویسد)\n";
add_filter( 'wp_doing_ajax', '__return_true' );
wp_set_current_user( $user_b );
$_POST = $_REQUEST = [ 'order_id' => (string) $order_a, 'nonce' => wp_create_nonce( 'hodima_panel_chat' ), 'message' => 'نفوذ' ];
$stop = hodima_pt_run( static fn() => do_action( 'wp_ajax_hodima_panel_chat_send' ) );
hodima_pt( 'ارسال مشتری دیگر رد شد', str_contains( $GLOBALS['hodima_pt_last_output'], '"success":false' ), true );
hodima_pt( 'پیامی اضافه نشد', (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE chat_id = %d', table( 'chat_messages' ), $chat->id ) ), 4 );
wp_set_current_user( $user_a );
$_POST = $_REQUEST = [ 'order_id' => (string) $order_a, 'nonce' => wp_create_nonce( 'hodima_panel_chat' ), 'message' => "سلام \\\"دوباره\\\"" ];
hodima_pt_run( static fn() => do_action( 'wp_ajax_hodima_panel_chat_send' ) );
hodima_pt( 'ارسال صاحب سفارش پذیرفته شد', str_contains( $GLOBALS['hodima_pt_last_output'], '"success":true' ), true );
hodima_pt( 'متن بدون \\ اضافه ذخیره شد', $wpdb->get_var( $wpdb->prepare( 'SELECT message FROM %i WHERE chat_id = %d ORDER BY id DESC LIMIT 1', table( 'chat_messages' ), $chat->id ) ), 'سلام "دوباره"' );
$_POST = $_REQUEST = [ 'order_id' => (string) $order_a, 'nonce' => wp_create_nonce( 'hodima_panel_chat' ) ];
hodima_pt_run( static fn() => do_action( 'wp_ajax_hodima_panel_chat_history' ) );
hodima_pt( 'باز کردن گفتگو پیام‌ها را برای مشتری خوانده می‌کند', (int) $wpdb->get_var( $wpdb->prepare( 'SELECT user_unread FROM %i WHERE order_id = %d', table( 'chats' ), $order_a ) ), 0 );
remove_filter( 'wp_doing_ajax', '__return_true' );
$_POST = $_REQUEST = [];

echo "== پروفایل: کامل بودن از فیلدهای ووکامرس، قفل، ذخیره\n";
$fresh = (int) wp_create_user( '09121112233', wp_generate_password(), '09121112233@hodima.test' );
update_user_meta( $fresh, 'mobile', '09121112233' );
$fresh_user = get_userdata( $fresh );
hodima_pt( 'کاربر تازه ثبت‌نامی: ناقص، ایمیل ساختگی و نام=موبایل خالی نشان داده می‌شوند', [ Profile::is_complete( $fresh_user ), Profile::fields( $fresh_user )['email'], Profile::fields( $fresh_user )['name'], Profile::fields( $fresh_user )['mobile'] ], [ false, '', '', '09121112233' ] );
wp_set_current_user( $fresh );
$stop = hodima_pt_run( static fn() => do_action( 'hodima_panel_request', 'orders' ) );
hodima_pt( 'پروفایل ناقص ← ریدایرکت به پروفایل', str_contains( $stop, 'redirect:' ) && str_contains( $stop, 'section=profile' ), true );
$_POST = $_REQUEST = [ 'action' => 'save_prebuy', '_wpnonce' => wp_create_nonce( 'user_panel_action' ), 'products' => [ (string) $product ], 'qty' => [ $product => '3' ] ];
$stop  = hodima_pt_run( [ \Hodima\Panel\Orders::class, 'handle_form' ] );
$_POST = $_REQUEST = [];
hodima_pt( 'پروفایل ناقص: فرم سفارش مستقیم (admin-post) هم قفل، بی سفارش', [ str_contains( $stop, 'msg=profile_locked' ), (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE user_id = %d', table( 'orders' ), $fresh ) ) ], [ true, 0 ] );
$hp_editor = wp_insert_user( [ 'user_login' => 'hp-editor', 'user_pass' => wp_generate_password(), 'user_email' => 'hp-editor@hodima.test', 'role' => 'editor' ] );
hodima_pt( 'کارکنان: ویرایشگر هم کارمند است، مشتری نه', [ is_int( $hp_editor ) && Profile::is_staff( get_userdata( $hp_editor ) ), Profile::is_staff( get_userdata( $user_a ) ) ], [ true, false ] );
if ( is_int( $hp_editor ) && $hp_editor > 1 ) {
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $hp_editor );
}
hodima_pt( 'بخش پروفایل و پشتیبانی باز است', [ hodima_pt_run( static fn() => do_action( 'hodima_panel_request', 'profile' ) ), hodima_pt_run( static fn() => do_action( 'hodima_panel_request', 'support' ) ) ], [ '', '' ] );
wp_set_current_user( 1 );
hodima_pt( 'مدیر هیچ‌وقت قفل نمی‌شود', hodima_pt_run( static fn() => do_action( 'hodima_panel_request', 'orders' ) ), '' );
hodima_pt( 'مشتری قدیمی ووکامرس با صورت‌حساب کامل قفل نمی‌شود', Profile::is_complete( get_userdata( $user_a ) ), true );
$fu = get_userdata( $fresh );
hodima_pt( 'ذخیره: نام موبایلی رد', Profile::save( $fu, [ 'name' => '۰۹۱۲۱۱۱۲۲۳۳', 'email' => 'a@b.ir', 'address' => 'تهران خیابان یک پلاک ۲', 'postcode' => '1234567890' ] ) !== '', true );
hodima_pt( 'ذخیره: ایمیل ساختگی رد', Profile::save( $fu, [ 'name' => 'علی رضایی', 'email' => '09121112233@hodima.test', 'address' => 'تهران خیابان یک پلاک ۲', 'postcode' => '1234567890' ] ) !== '', true );
hodima_pt( 'ذخیره: ایمیل کاربر دیگر رد', Profile::save( $fu, [ 'name' => 'علی رضایی', 'email' => 'hp_a@hodima.test', 'address' => 'تهران خیابان یک پلاک ۲', 'postcode' => '1234567890' ] ), 'این ایمیل قبلاً توسط کاربر دیگری ثبت شده است.' );
hodima_pt( 'ذخیره: کد پستی کوتاه رد', Profile::save( $fu, [ 'name' => 'علی رضایی', 'email' => 'ali@hodima.test', 'address' => 'تهران خیابان یک پلاک ۲', 'postcode' => '12345' ] ) !== '', true );
$hp_mails = 0;
$hp_mail  = static function () use ( &$hp_mails ): bool {
	++$hp_mails;
	return false;
};
add_filter( 'pre_wp_mail', $hp_mail );
hodima_pt( 'ذخیره معتبر (کد پستی با رقم فارسی و خط تیره)', Profile::save( $fu, [ 'name' => 'علی رضایی مقدم', 'email' => 'ali@hodima.test', 'address' => "تهران، خیابان 'یک'، پلاک ۲", 'postcode' => '۱۲۳۴۵-۶۷۸۹۰' ] ), '' );
remove_filter( 'pre_wp_mail', $hp_mail );
hodima_pt( 'اعلان تغییر ایمیل به ایمیل ساختگی فرستاده نشد', $hp_mails, 0 );
clean_user_cache( $fresh );
$fu = get_userdata( $fresh );
hodima_pt( 'در فیلدهای صورت‌حساب ووکامرس ذخیره شد', [ get_user_meta( $fresh, 'billing_first_name', true ), get_user_meta( $fresh, 'billing_last_name', true ), get_user_meta( $fresh, 'billing_postcode', true ), get_user_meta( $fresh, 'billing_address_1', true ), get_user_meta( $fresh, 'billing_country', true ), get_user_meta( $fresh, 'billing_phone', true ), get_user_meta( $fresh, 'billing_email', true ) ], [ 'علی', 'رضایی مقدم', '1234567890', "تهران، خیابان 'یک'، پلاک ۲", 'IR', '09121112233', 'ali@hodima.test' ] );
hodima_pt( 'نام به‌روز شد و پروفایل کامل است؛ ایمیل حساب بی لینک تأیید همان قبلی (فقط ایمیل صورت‌حساب تازه)', [ $fu->display_name, $fu->user_email, $fu->first_name, Profile::is_complete( $fu ) ], [ 'علی رضایی مقدم', '09121112233@hodima.test', 'علی', true ] );
hodima_pt( 'متای قدیمی پنل نوشته نمی‌شود', [ get_user_meta( $fresh, 'user_address', true ), get_user_meta( $fresh, 'up_profile_completed', true ) ], [ '', '' ] );
wp_set_current_user( $fresh );
hodima_pt( 'بعد از ذخیره قفل برداشته شد', hodima_pt_run( static fn() => do_action( 'hodima_panel_request', 'orders' ) ), '' );
// AJAX: رمز کوتاه پیش از هر ذخیره‌ای رد شود
add_filter( 'wp_doing_ajax', '__return_true' );
$_POST = $_REQUEST = [ 'nonce' => wp_create_nonce( 'up_update_profile_action' ), 'display_name' => 'نام تازه کامل', 'user_email' => 'ali@hodima.test', 'address' => 'تهران خیابان یک پلاک ۲', 'postcode' => '1234567890', 'new_password' => 'abc', 'confirm_password' => 'abc' ];
hodima_pt_run( static fn() => do_action( 'wp_ajax_hodima_panel_profile' ) );
clean_user_cache( $fresh );
hodima_pt( 'رمز کوتاه رد و چیزی ذخیره نشد', [ str_contains( $GLOBALS['hodima_pt_last_output'], '"success":false' ), get_userdata( $fresh )->display_name ], [ true, 'علی رضایی مقدم' ] );
$_POST['new_password'] = $_POST['confirm_password'] = '';
$_POST['display_name'] = 'نام تازه کامل';
$_REQUEST              = $_POST;
hodima_pt_run( static fn() => do_action( 'wp_ajax_hodima_panel_profile' ) );
clean_user_cache( $fresh );
hodima_pt( 'ذخیره AJAX', [ str_contains( $GLOBALS['hodima_pt_last_output'], '"success":true' ), get_userdata( $fresh )->display_name ], [ true, 'نام تازه کامل' ] );
remove_filter( 'wp_doing_ajax', '__return_true' );
$_POST = $_REQUEST = [];
require_once ABSPATH . 'wp-admin/includes/user.php';
if ( $fresh > 1 ) { // wp_create_user شکست‌خورده (WP_Error) به عدد ۱ = حذف مدیر ابزار تست می‌شد
	wp_delete_user( $fresh );
}

echo "== پیام تازه: مشاوره قبل از خرید (تیکت) یا پیگیری سفارش (گفتگوی همان سفارش)\n";
wp_set_current_user( $user_a );
$_SERVER['REQUEST_METHOD'] = 'POST';
$hp_ticket_post            = static function ( array $fields ) use ( &$hp_ticket_post ): string {
	$_POST = $_REQUEST = [ 'support_action' => 'create_ticket', '_wpnonce_create_ticket' => wp_create_nonce( 'create_ticket_nonce' ) ] + $fields;
	return hodima_pt_run( static fn() => do_action( 'hodima_panel_request', 'support' ) );
};
$hp_ticket_count = static fn(): int => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE user_id = %d', table( 'tickets' ), $user_a ) );
$hp_chat_count   = static fn( int $order ): int => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i m INNER JOIN %i c ON c.id = m.chat_id WHERE c.order_id = %d', table( 'chat_messages' ), table( 'chats' ), $order ) );
// فیلدهای فرم قدیمی (واحد و فوریت) دیگر اثری ندارند
$stop   = $hp_ticket_post( [ 'topic' => 'consult', 'subject' => 'سؤال', 'message' => 'متن \\"نقل\\"', 'priority' => 'high', 'department' => 'finance' ] );
$ticket = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE user_id = %d ORDER BY id DESC LIMIT 1', table( 'tickets' ), $user_a ) );
hodima_pt( 'مشاوره: تیکت ثبت و به همان رشته رفت', [ str_contains( $stop, 'redirect:' ), str_contains( $stop, 'thread_id=' . (int) ( $ticket->id ?? 0 ) ), str_contains( $stop, 'msg=ticket_sent' ) ], [ true, true, true ] );
hodima_pt( 'مشاوره: نوع «مشاوره قبل از خرید»، فوریت عادی، واحد فرستاده‌شده نادیده', [ $ticket->department ?? '', $ticket->priority ?? '', \Hodima\Panel\ticket_departments()[ $ticket->department ?? '' ] ?? '' ], [ 'consult', 'normal', 'مشاوره قبل از خرید' ] );
hodima_pt( 'کد پیگیری', $ticket->reference_code ?? '', sprintf( 'TKT-%06d', (int) ( $ticket->id ?? 0 ) ) );
// پاسخ مشتری به تیکت: هر رد با پیام (نه بازگشت بی‌صدا)
$hp_reply = static function ( array $fields ): string {
	$_POST = $_REQUEST = [ 'support_action' => 'reply_to_ticket', '_wpnonce_reply_ticket' => wp_create_nonce( 'reply_ticket_nonce' ) ] + $fields;
	return hodima_pt_run( static fn() => do_action( 'hodima_panel_request', 'support' ) );
};
$r_empty = $hp_reply( [ 'thread_id' => (string) $ticket->id, 'message' => '  ' ] );
$r_other = $hp_reply( [ 'thread_id' => '999999', 'message' => 'سلام' ] );
$wpdb->update( table( 'tickets' ), [ 'status' => 'closed' ], [ 'id' => $ticket->id ] );
$r_closed = $hp_reply( [ 'thread_id' => (string) $ticket->id, 'message' => 'سلام' ] );
$wpdb->update( table( 'tickets' ), [ 'status' => 'open' ], [ 'id' => $ticket->id ] );
hodima_pt( 'پاسخ تیکت ردشده: ریدایرکت با پیام (خالی، تیکت دیگران، بسته)', [ str_contains( $r_empty, 'msg=message_empty' ), str_contains( $r_other, 'msg=error_owner' ), str_contains( $r_closed, 'msg=ticket_closed' ), (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE ticket_id = %d', table( 'ticket_messages' ), $ticket->id ) ) ], [ true, true, true, 1 ] );
hodima_pt( 'متن بدون \\ اضافه', $wpdb->get_var( $wpdb->prepare( 'SELECT message FROM %i WHERE ticket_id = %d', table( 'ticket_messages' ), $ticket->id ) ), 'متن "نقل"' );
$stop = $hp_ticket_post( [ 'topic' => 'consult', 'subject' => '', 'message' => 'حداقل سفارش چقدر است؟' ] );
hodima_pt( 'مشاوره بی موضوع: موضوع پیش‌فرض', (string) $wpdb->get_var( $wpdb->prepare( 'SELECT subject FROM %i WHERE user_id = %d ORDER BY id DESC LIMIT 1', table( 'tickets' ), $user_a ) ), 'مشاوره قبل از خرید' );
$before = $hp_ticket_count();
$stop   = $hp_ticket_post( [ 'topic' => 'consult', 'subject' => 'خالی', 'message' => '   ' ] );
hodima_pt( 'پیام خالی: بی تیکت، برگشت به فرم با پیام', [ $hp_ticket_count(), str_contains( $stop, 'msg=message_empty' ), str_contains( $stop, 'new=1' ) ], [ $before, true, true ] );
// پیگیری سفارش: پیام به گفتگوی همان سفارش، نه تیکت تازه
$chats_before = $hp_chat_count( $order_a );
$stop         = $hp_ticket_post( [ 'topic' => 'order', 'order_id' => (string) $order_a, 'order_message' => 'ارسال کی است؟', 'message' => 'متن بخش مشاوره' ] );
hodima_pt( 'پیگیری سفارش: پیام در گفتگوی سفارش، بی تیکت تازه', [ $hp_chat_count( $order_a ) - $chats_before, $hp_ticket_count(), str_contains( $stop, 'msg=order_message_sent' ) ], [ 1, $before, true ] );
hodima_pt( 'پیگیری سفارش: پیش‌نمایش و نخوانده مدیر', (array) $wpdb->get_row( $wpdb->prepare( 'SELECT last_message_preview, last_message_sender FROM %i WHERE order_id = %d', table( 'chats' ), $order_a ), ARRAY_A ), [ 'last_message_preview' => 'ارسال کی است؟', 'last_message_sender' => 'user' ] );
$chats_b = $hp_chat_count( $order_b );
$stop    = $hp_ticket_post( [ 'topic' => 'order', 'order_id' => (string) $order_b, 'order_message' => 'نفوذ' ] );
hodima_pt( 'پیگیری سفارش مشتری دیگر رد شد', [ $hp_chat_count( $order_b ), str_contains( $stop, 'msg=error_owner' ) ], [ $chats_b, true ] );
$stop = $hp_ticket_post( [ 'topic' => 'order', 'order_id' => (string) $order_a, 'order_message' => '' ] );
hodima_pt( 'پیگیری سفارش با پیام خالی رد شد', str_contains( $stop, 'msg=message_empty' ), true );
// بدون JS هر دو بخش دیده می‌شوند: دکمه فشرده‌شده بر گزینه انتخاب‌شده مقدم است
$stop = $hp_ticket_post( [ 'topic' => 'consult', 'topic_submit' => 'order', 'order_id' => (string) $order_a, 'order_message' => 'با دکمه سفارش' ] );
hodima_pt( 'دکمه «ارسال در گفتگوی سفارش» نوع را تعیین می‌کند', [ str_contains( $stop, 'msg=order_message_sent' ), $hp_ticket_count() ], [ true, $before ] );
$_SERVER['REQUEST_METHOD'] = 'GET';
$_POST                     = $_REQUEST = [];

echo "== پیامک: سه سرویس، پشتیبان، گزارش (پاسخ سرویس‌ها شبیه‌سازی‌شده)\n";
$hp_http  = [];
$hp_reply = [];
add_filter( 'pre_http_request', static function ( $pre, array $args, string $url ) use ( &$hp_http, &$hp_reply ) {
	foreach ( [ 'payamak-panel.com' => 'melipayamak', 'kavenegar.com' => 'kavenegar', 'sms.ir' => 'smsir' ] as $host => $id ) {
		if ( str_contains( $url, $host ) ) {
			$hp_http[] = [ 'id' => $id, 'url' => $url, 'args' => $args ];
			$r         = $hp_reply[ $id ] ?? [ 500, '{}' ];
			return $r instanceof WP_Error ? $r : [ 'headers' => [], 'body' => $r[1], 'response' => [ 'code' => $r[0], 'message' => '' ], 'cookies' => [], 'filename' => null ];
		}
	}
	return $pre;
}, 10, 3 );
$hp_last_code = static function () use ( &$hp_http ): string {
	$req  = end( $hp_http );
	$body = $req['args']['body'] ?? [];
	if ( is_string( $body ) ) {
		$body = json_decode( $body, true );
		return (string) ( $body['parameters'][0]['value'] ?? '' );
	}
	return (string) ( $body['text'] ?? $body['token'] ?? '' );
};
$ok_reply = [ 'melipayamak' => [ 200, '{"Value":"5012345678901234567","RetStatus":1,"StrRetStatus":"Ok"}' ], 'kavenegar' => [ 200, '{"return":{"status":200,"message":"تایید شد"},"entries":[]}' ], 'smsir' => [ 200, '{"status":1,"message":"موفق","data":{"messageId":1}}' ] ];
delete_option( Sms::OPTION );
delete_option( Sms::LOG_OPTION );
hodima_pt( 'بدون تنظیم: پیامک آماده نیست و کدی ساخته نمی‌شود', [ Sms::ready(), Otp::issue( 'k0', '09120000009', 'login', '198.51.100.1' )['ok'], count( $hp_http ) ], [ false, false, 0 ] );
update_option( Sms::OPTION, [ 'primary' => 'melipayamak', 'fallback' => 'kavenegar', 'providers' => [ 'melipayamak' => [ 'username' => 'hodhod', 'password' => 'secret-pass', 'body_id' => '123456' ], 'kavenegar' => [ 'api_key' => 'KAVE-KEY', 'template' => 'verify' ], 'smsir' => [ 'api_key' => 'SMSIR-KEY', 'template_id' => '777' ] ] ] );
hodima_pt( 'با تنظیم ملی‌پیامک آماده است', Sms::ready(), true );
$hp_reply = $ok_reply;
$r        = Sms::send_code( '09121234567', '48213' );
$req      = $hp_http[0] ?? [];
hodima_pt( 'ملی‌پیامک: آدرس REST و فیلدهای الگو', [ $r['ok'], $r['provider'], $req['url'] ?? '', $req['args']['body'] ?? [] ], [ true, 'melipayamak', 'https://rest.payamak-panel.com/api/SendSMS/BaseServiceNumber', [ 'username' => 'hodhod', 'password' => 'secret-pass', 'text' => '48213', 'to' => '09121234567', 'bodyId' => '123456' ] ] );
$hp_http  = [];
$hp_reply = [ 'melipayamak' => [ 200, '{"Value":"11","RetStatus":11,"StrRetStatus":"InvalidBodyId"}' ] ] + $ok_reply;
$r        = Sms::send_code( '09121234567', '11111' );
hodima_pt( 'ملی‌پیامک خطا داد ← همان کد با کاوه‌نگار (پشتیبان)', [ $r['ok'], $r['provider'], count( $hp_http ), $hp_http[1]['url'] ?? '', $hp_http[1]['args']['body'] ?? [] ], [ true, 'kavenegar', 2, 'https://api.kavenegar.com/v1/KAVE-KEY/verify/lookup.json', [ 'receptor' => '09121234567', 'token' => '11111', 'template' => 'verify' ] ] );
hodima_pt( 'مهلت هر سرویس ۸ ثانیه (نه ۱۲)، پشتیبان در بودجه کل', [ $hp_http[0]['args']['timeout'] ?? 0, ( $hp_http[1]['args']['timeout'] ?? 99 ) <= Sms::TIMEOUT, Sms::BUDGET < 2 * 12 ], [ Sms::TIMEOUT, true, true ] );
$hp_http  = [];
$hp_reply = [ 'melipayamak' => new WP_Error( 'http', 'timeout' ), 'kavenegar' => [ 418, '{"return":{"status":418,"message":"اعتبار کافی نیست"}}' ] ];
$r        = Sms::send_code( '09121234567', '22222' );
hodima_pt( 'هر دو خطا ← ناموفق با پیام سرویس آخر', [ $r['ok'], $r['error'] ], [ false, 'کاوه‌نگار: اعتبار کافی نیست' ] );
$hp_http  = [];
$hp_reply = $ok_reply;
$r        = Sms::send_with( 'smsir', '09121234567', '33333' );
$req      = $hp_http[0] ?? [];
hodima_pt( 'sms.ir: سربرگ کلید و بدنه JSON الگو', [ $r['ok'], $req['args']['headers']['X-API-KEY'] ?? '', json_decode( (string) ( $req['args']['body'] ?? '' ), true ) ], [ true, 'SMSIR-KEY', [ 'mobile' => '09121234567', 'templateId' => 777, 'parameters' => [ [ 'name' => 'CODE', 'value' => '33333' ] ] ] ] );
$log = Sms::log_entries();
hodima_pt( 'گزارش: شماره نیمه‌پنهان، بی کد و بی کلید', [ $log[0]['to'] ?? '', str_contains( (string) wp_json_encode( $log ), '33333' ), str_contains( (string) wp_json_encode( $log ), 'secret-pass' ), count( $log ) ], [ '0912***4567', false, false, 6 ] );
update_option( Sms::OPTION, [ 'primary' => 'melipayamak', 'fallback' => 'melipayamak', 'providers' => [ 'melipayamak' => [ 'username' => 'u', 'password' => '', 'body_id' => '1' ] ] ] );
hodima_pt( 'سرویس ناقص آماده نیست؛ پشتیبان تکراری نادیده', [ Sms::ready(), Sms::settings()['fallback'] ], [ false, '' ] );
update_option( Sms::OPTION, [ 'primary' => 'melipayamak', 'fallback' => '', 'providers' => [ 'melipayamak' => [ 'username' => 'hodhod', 'password' => 'secret-pass', 'body_id' => '123456' ] ] ] );

echo "== پیشخوان: تنظیمات پیامک (ذخیره، رمز چاپ‌نشده، رفت‌وبرگشت، دسترسی، پیامک آزمایشی)\n";
require_once HODIMA_PANEL_DIR . '/includes/admin-sms.php';
update_option( Sms::OPTION, [ 'primary' => 'melipayamak', 'fallback' => 'kavenegar', 'providers' => [ 'melipayamak' => [ 'username' => 'hodhod', 'password' => 'secret-pass', 'body_id' => '123456' ], 'kavenegar' => [ 'api_key' => 'KAVE-KEY', 'template' => 'verify' ] ] ] );
wp_set_current_user( 1 );
ob_start();
Admin_Sms::render();
$page = (string) ob_get_clean();
hodima_pt( 'صفحه: رمز و کلید ذخیره‌شده در HTML چاپ نمی‌شود', [ str_contains( $page, 'secret-pass' ), str_contains( $page, 'KAVE-KEY' ), str_contains( $page, 'value="hodhod"' ), str_contains( $page, '(ذخیره‌شده)' ) ], [ false, false, true, true ] );
hodima_pt( 'صفحه: کالبد (سربرگ، ۴ آمار، آزمایشی، سرویس‌ها، گزارش) و تب سرویس اصلی باز', [ substr_count( $page, 'class="hdp-stat"' ), str_contains( $page, 'hodima_panel_sms_test' ), str_contains( $page, 'hodima_panel_sms_save' ), str_contains( $page, 'hdp-more' ), (bool) preg_match( '/id="hp-sms-tab-melipayamak"[^>]*aria-selected="true"/', $page ) ], [ 4, true, true, true, true ] );
// رفت‌وبرگشت: فرم رندرشده را مثل مرورگر بفرست؛ چیزی نباید عوض شود
$before = get_option( Sms::OPTION );
$doc    = new DOMDocument();
@$doc->loadHTML( '<?xml encoding="utf-8"?>' . $page ); // phpcs:ignore
$post   = [];
foreach ( ( new DOMXPath( $doc ) )->query( '//form[.//input[@name="action" and @value="hodima_panel_sms_save"]]//input[@name]' ) as $input ) {
	$name = $input->getAttribute( 'name' );
	$type = $input->getAttribute( 'type' );
	if ( in_array( $type, [ 'radio', 'checkbox' ], true ) && ! $input->hasAttribute( 'checked' ) ) {
		continue;
	}
	parse_str( $name . '=' . rawurlencode( $input->getAttribute( 'value' ) ), $one );
	$post = array_replace_recursive( $post, $one );
}
Admin_Sms::save( $post );
$after = get_option( Sms::OPTION );
hodima_pt( 'رفت‌وبرگشت فرم: تنظیم‌ها و رمزها بی‌تغییر', [ $after['primary'], $after['fallback'], $after['providers']['melipayamak'], $after['providers']['kavenegar'] ], [ $before['primary'], $before['fallback'], $before['providers']['melipayamak'], [ 'api_key' => 'KAVE-KEY', 'template' => 'verify' ] ] );
Admin_Sms::save( [ 'primary' => 'kavenegar', 'fallback' => 'kavenegar', 'providers' => [ 'melipayamak' => [ 'password' => '', 'password_clear' => '1' ], 'kavenegar' => [ 'api_key' => ' NEW-KEY ' ] ] ] );
$after = get_option( Sms::OPTION );
hodima_pt( 'ذخیره: پشتیبان همان اصلی ← خاموش؛ «پاک شود» رمز را پاک کرد؛ کلید تازه تمیز', [ $after['primary'], $after['fallback'], $after['providers']['melipayamak']['password'], $after['providers']['kavenegar']['api_key'] ], [ 'kavenegar', '', '', 'NEW-KEY' ] );
Admin_Sms::save( [ 'primary' => 'evil', 'fallback' => 'x' ] );
hodima_pt( 'سرویس ناشناخته پذیرفته نمی‌شود', [ get_option( Sms::OPTION )['primary'], get_option( Sms::OPTION )['fallback'] ], [ '', '' ] );
wp_set_current_user( $user_b );
$_POST = $_REQUEST = [ '_wpnonce' => wp_create_nonce( 'hodima_panel_sms_save' ), 'primary' => 'smsir' ];
hodima_pt( 'مشتری نمی‌تواند ذخیره کند', str_starts_with( hodima_pt_run( [ Admin_Sms::class, 'handle_save' ] ), 'die:' ), true );
wp_set_current_user( 1 );
$_POST = $_REQUEST = [ 'primary' => 'smsir' ];
hodima_pt( 'ذخیره بی nonce رد', str_starts_with( hodima_pt_run( [ Admin_Sms::class, 'handle_save' ] ), 'die:' ), true );
update_option( Sms::OPTION, [ 'primary' => 'melipayamak', 'fallback' => '', 'providers' => [ 'melipayamak' => [ 'username' => 'hodhod', 'password' => 'secret-pass', 'body_id' => '123456' ] ] ] );
$hp_http  = [];
$hp_reply = $ok_reply;
$_POST    = $_REQUEST = [ '_wpnonce' => wp_create_nonce( 'hodima_panel_sms_test' ), 'test_mobile' => '۰۹۱۲۳۳۳۴۴۵۵', 'test_service' => '' ];
$stop     = hodima_pt_run( [ Admin_Sms::class, 'handle_test' ] );
$tr       = get_transient( 'hodima_panel_sms_test_1' );
hodima_pt( 'پیامک آزمایشی: با سرویس اصلی، نتیجه برای نمایش، شماره نیمه‌پنهان', [ str_contains( $stop, 'hp_msg=tested' ), count( $hp_http ), $tr['ok'] ?? null, $tr['to'] ?? '' ], [ true, 1, true, '0912***4455' ] );
$_GET = [ 'hp_msg' => 'tested' ];
ob_start();
Admin_Sms::render();
$page = (string) ob_get_clean();
hodima_pt( 'نتیجه آزمایشی یک بار نشان داده می‌شود', [ str_contains( $page, 'ارسال شد با ملی‌پیامک به 0912***4455' ), false === get_transient( 'hodima_panel_sms_test_1' ) ], [ true, true ] );
$_GET  = [];
$_POST = $_REQUEST = [ '_wpnonce' => wp_create_nonce( 'hodima_panel_sms_test' ), 'test_mobile' => '123' ];
hodima_pt( 'پیامک آزمایشی با شماره نامعتبر', str_contains( hodima_pt_run( [ Admin_Sms::class, 'handle_test' ] ), 'hp_msg=test_mobile' ), true );
$_POST = $_REQUEST = [];
update_option( Sms::OPTION, [ 'primary' => 'melipayamak', 'fallback' => '', 'providers' => [ 'melipayamak' => [ 'username' => 'hodhod', 'password' => 'secret-pass', 'body_id' => '123456' ] ] ] );

echo "== کد یک‌بار مصرف (اعتبار، تلاش، هدف، محدودیت ارسال)\n";
$hp_otp_reset = static function (): void {
	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%transient%hodima_panel_%'" );
	wp_cache_flush();
};
$hp_otp_reset();
$hp_http = [];
$r       = Otp::issue( 'key-1', '09125550001', 'login', '198.51.100.2' );
$code    = $hp_last_code();
hodima_pt( 'کد ۵ رقمی فرستاده شد', [ $r['ok'], (bool) preg_match( '/^\d{5}$/', $code ), $r['wait'] ], [ true, true, Otp::COOLDOWN ] );
hodima_pt( 'ارسال دوباره پیش از ۲ دقیقه رد', Otp::issue( 'key-1', '09125550001', 'login', '198.51.100.2' )['wait'] > 0, true );
hodima_pt( 'کد با هدف دیگر پذیرفته نمی‌شود', Otp::check( 'key-1', $code, 'profile' )['ok'], false );
hodima_pt( 'کد اشتباه', Otp::check( 'key-1', '00000' === $code ? '11111' : '00000', 'login' )['error'], 'کد اشتباه است.' );
hodima_pt( 'کد درست (با رقم فارسی) پذیرفته', Otp::check( 'key-1', strtr( $code, [ '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' ] ), 'login' )['ok'], true );
hodima_pt( 'کد مصرف‌شده دوباره پذیرفته نمی‌شود', Otp::check( 'key-1', $code, 'login' )['ok'], false );
Otp::issue( 'key-2', '09125550002', 'login', '198.51.100.2' );
$code2 = $hp_last_code();
$wrong = '00000' === $code2 ? '11111' : '00000';
foreach ( range( 1, Otp::MAX_ATTEMPTS ) as $_ ) {
	Otp::check( 'key-2', $wrong, 'login' );
}
hodima_pt( 'بعد از ۳ تلاش اشتباه، کد درست هم پذیرفته نمی‌شود', Otp::check( 'key-2', $code2, 'login' )['ok'], false );
$hp_otp_reset();
for ( $i = 0; $i < Otp::PER_MOBILE_HOUR; $i++ ) {
	Otp::issue( 'key-m' . $i, '09125550003', 'login', '198.51.100.' . ( 10 + $i ) );
	delete_transient( 'hodima_panel_otpcool_' . substr( wp_hash( '09125550003' ), 0, 24 ) );
}
hodima_pt( 'سقف ۵ کد در ساعت برای یک شماره (حتی از IPهای مختلف)', Otp::issue( 'key-mx', '09125550003', 'login', '198.51.100.99' )['ok'], false );
$hp_otp_reset();
// سقف کل سایت: IP و شماره عوض‌شونده هم بی‌نهایت پیامک نمی‌گیرند
$hp_site = static fn(): int => 2;
add_filter( 'hodima_panel_otp_site_hourly', $hp_site );
$r1 = Otp::issue( 'key-s1', '09125550011', 'login', '198.51.100.31' )['ok'];
$r2 = Otp::issue( 'key-s2', '09125550012', 'login', '198.51.100.32' )['ok'];
$hp_http = [];
$r3      = Otp::issue( 'key-s3', '09125550013', 'login', '198.51.100.33' );
remove_filter( 'hodima_panel_otp_site_hourly', $hp_site );
hodima_pt( 'سقف ساعتی کل سایت: ارسال سوم رد و هیچ پیامکی نرفت', [ $r1, $r2, $r3['ok'], str_contains( $r3['error'], 'موقتا محدود' ), count( $hp_http ) ], [ true, true, false, true, 0 ] );
hodima_pt( 'پیش‌فرض سقف کل سایت', Otp::site_limit(), Otp::SITE_HOUR );
$hp_otp_reset();
// قفل بررسی کد: درخواست هم‌زمان (قفل گرفته‌شده) حدس را مصرف نمی‌کند و قفل کهنه آزاد می‌شود
Otp::issue( 'key-l', '09125550021', 'login', '198.51.100.41' );
$code_l = $hp_last_code();
$hp_store = 'hodima_panel_otp_' . substr( hash( 'sha256', 'key-l|' . wp_salt( 'nonce' ) ), 0, 40 );
$hp_lock  = '_' . $hp_store . '_lock';
$wpdb->insert( $wpdb->options, [ 'option_name' => $hp_lock, 'option_value' => (string) time(), 'autoload' => 'off' ] );
$busy = Otp::check( 'key-l', $code_l, 'login' );
hodima_pt( 'قفل گرفته‌شده: «در حال بررسی»، کد هنوز معتبر و تلاشی مصرف نشد', [ $busy['ok'], str_contains( $busy['error'], 'در حال بررسی' ), (int) ( get_transient( $hp_store )['attempts'] ?? -1 ) ], [ false, true, 0 ] );
$wpdb->update( $wpdb->options, [ 'option_value' => (string) ( time() - 100 ) ], [ 'option_name' => $hp_lock ] );
$ok_l = Otp::check( 'key-l', $code_l, 'login' )['ok'];
hodima_pt( 'قفل کهنه آزاد شد، کد درست پذیرفته و ردیف قفل پاک شد', [ $ok_l, (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE option_name LIKE %s', $wpdb->options, '%otp%_lock' ) ) ], [ true, 0 ] );
Otp::issue( 'key-t', '09125550022', 'login', '198.51.100.42' );
$code_t   = $hp_last_code();
$hp_store = 'hodima_panel_otp_' . substr( hash( 'sha256', 'key-t|' . wp_salt( 'nonce' ) ), 0, 40 );
$data_t   = get_transient( $hp_store );
$data_t['expires'] = time() + 10;
set_transient( $hp_store, $data_t, 10 );
Otp::check( 'key-t', '00000' === $code_t ? '11111' : '00000', 'login' );
$hp_timeout = (int) get_option( '_transient_timeout_' . $hp_store );
hodima_pt( 'حدس اشتباه عمر کد را تمدید نمی‌کند', $hp_timeout > 0 && $hp_timeout <= time() + 10, true );
$hp_otp_reset();

echo "== ورود و ثبت‌نام\n";
$_SERVER['REQUEST_METHOD'] = 'POST';
$hp_auth = static function ( array $post ): array {
	$_POST = $_REQUEST = $post + [ 'hodima_auth_nonce' => wp_create_nonce( Auth::NONCE ) ];
	$auth  = new Auth();
	$stop  = hodima_pt_run( static fn() => $auth->handle() );
	return [ $auth, $stop ];
};
wp_set_current_user( 0 );
unset( $_COOKIE[ Auth::COOKIE ] );
[ $a ] = $hp_auth( [ 'send_otp' => '1', 'otp_mobile' => '۰۹۱۲ ۷۷۷ ۰۰۰۱' ] );
$code  = $hp_last_code();
hodima_pt( 'شماره تازه (رقم فارسی و فاصله) ← کد و نمای تأیید', [ $a->view, $a->mobile, '' !== ( $_COOKIE[ Auth::COOKIE ] ?? '' ) ], [ 'otp_verify', '09127770001', true ] );
hodima_pt( 'پیش از تأیید هیچ حسابی ساخته نشده', null === Auth::find_user( '09127770001' ), true );
[ $a, $stop ] = $hp_auth( [ 'verify_otp' => '1', 'otp_code' => $code ] );
$new = Auth::find_user( '09127770001' );
hodima_pt( 'کد درست ← حساب تازه، ورود و رفتن به پروفایل', [ $new instanceof WP_User, str_contains( $stop, 'redirect:' ) && str_contains( $stop, 'msg=welcome' ), get_current_user_id() === ( $new->ID ?? -1 ) ], [ true, true, true ] );
hodima_pt( 'حساب تازه: موبایل در متا و صورت‌حساب، ایمیل ساختگی', [ get_user_meta( (int) $new->ID, 'mobile', true ), get_user_meta( (int) $new->ID, 'billing_phone', true ), Profile::is_placeholder_email( (string) $new->user_email ) ], [ '09127770001', '09127770001', true ] );
wp_set_current_user( 0 );
$hp_otp_reset();
unset( $_COOKIE[ Auth::COOKIE ] );
$hp_auth( [ 'send_otp' => '1', 'otp_mobile' => '09127770001' ] );
[ $a, $stop ] = $hp_auth( [ 'verify_otp' => '1', 'otp_code' => $hp_last_code() ] );
hodima_pt( 'شماره موجود با کد ← ورود همان حساب (بی حساب دوم)', [ str_contains( $stop, 'redirect:' ) && ! str_contains( $stop, 'welcome' ), count( get_users( [ 'login' => '09127770001' ] ) ) ], [ true, 1 ] );
wp_set_current_user( 0 );
[ $a ] = $hp_auth( [ 'send_otp' => '1', 'otp_mobile' => '09127770002', Auth::TRAP => 'http://spam' ] );
hodima_pt( 'فیلد ضد ربات پر ← رد بی ارسال', [ $a->error, $a->view ], [ 'درخواست پذیرفته نشد.', 'login' ] );
[ $a ] = $hp_auth( [ 'send_otp' => '1', 'otp_mobile' => '0912' ] );
hodima_pt( 'شماره نامعتبر', $a->view . '|' . (string) str_contains( $a->error, 'نامعتبر' ), 'otp_request|1' );
$_POST                = $_REQUEST = [ 'send_otp' => '1', 'otp_mobile' => '09127770003', 'hodima_auth_nonce' => 'bad' ];
$a                    = new Auth();
hodima_pt_run( static fn() => $a->handle() );
hodima_pt( 'nonce کهنه ← پیام، بی ارسال', str_contains( $a->error, 'منقضی' ), true );
// ورود با رمز: سقف IP+شماره و سقف جدای هر شماره
wp_set_password( 'Right-Pass-123', (int) $new->ID );
$hp_otp_reset();
for ( $i = 0; $i < 5; $i++ ) {
	$hp_auth( [ 'login_with_password' => '1', 'mobile' => '09127770001', 'password' => 'wrong' ] );
}
[ $a ] = $hp_auth( [ 'login_with_password' => '1', 'mobile' => '09127770001', 'password' => 'Right-Pass-123' ] );
hodima_pt( '۵ رمز اشتباه از یک IP ← حتی رمز درست موقتا رد', str_contains( $a->error, 'تلاش بیش از حد' ), true );
delete_transient( 'hodima_panel_login_' . substr( wp_hash( Auth::ip() . '|09127770001' ), 0, 24 ) );
for ( $i = 0; $i < 5; $i++ ) {
	$hp_auth( [ 'login_with_password' => '1', 'mobile' => '09127770001', 'password' => 'wrong' ] );
	delete_transient( 'hodima_panel_login_' . substr( wp_hash( Auth::ip() . '|09127770001' ), 0, 24 ) );
}
[ $a ] = $hp_auth( [ 'login_with_password' => '1', 'mobile' => '09127770001', 'password' => 'Right-Pass-123' ] );
hodima_pt( '۱۰ تلاش برای یک شماره (IP عوض‌شده) ← قفل شماره', str_contains( $a->error, 'تلاش بیش از حد' ), true );
$hp_otp_reset();
[ $a, $stop ] = $hp_auth( [ 'login_with_password' => '1', 'mobile' => '+98 912 777 0001', 'password' => 'Right-Pass-123' ] );
hodima_pt( 'رمز درست با شماره ‎+98‎ ← ورود', str_contains( $stop, 'redirect:' ), true );
// رمز از مسیر استاندارد وردپرس (افزونه‌های امنیتی ببینند)
$hp_failed = [];
$hp_fail   = static function ( string $login ) use ( &$hp_failed ): void {
	$hp_failed[] = $login;
};
add_action( 'wp_login_failed', $hp_fail );
$hp_otp_reset();
wp_set_current_user( 0 );
$hp_auth( [ 'login_with_password' => '1', 'mobile' => '09127770001', 'password' => 'wrong' ] );
$hp_auth( [ 'login_with_password' => '1', 'mobile' => '09127779999', 'password' => 'wrong' ] );
hodima_pt( 'رمز اشتباه ← رویداد wp_login_failed (حساب موجود و ناموجود)', $hp_failed, [ '09127770001', '09127779999' ] );
remove_action( 'wp_login_failed', $hp_fail );
$hp_block = static fn() => new WP_Error( 'blocked', 'blocked' );
add_filter( 'authenticate', $hp_block, 99 );
[ $a, $stop ] = $hp_auth( [ 'login_with_password' => '1', 'mobile' => '09127770001', 'password' => 'Right-Pass-123' ] );
remove_filter( 'authenticate', $hp_block, 99 );
hodima_pt( 'افزونه امنیتی (فیلتر authenticate) ورود پنل را هم می‌بندد', [ str_contains( (string) $stop, 'redirect:' ), get_current_user_id() ], [ false, 0 ] );
// سقف یک IP روی همه شماره‌ها (امتحان رمز روی شماره‌های زیاد)
$hp_otp_reset();
for ( $i = 0; $i < 20; $i++ ) {
	$hp_auth( [ 'login_with_password' => '1', 'mobile' => '0912888' . str_pad( (string) $i, 4, '0', STR_PAD_LEFT ), 'password' => 'wrong' ] );
}
[ $a, $stop ] = $hp_auth( [ 'login_with_password' => '1', 'mobile' => '09127770001', 'password' => 'Right-Pass-123' ] );
hodima_pt( '۲۰ رمز اشتباه از یک IP روی شماره‌های مختلف ← رمز درست شماره دیگر هم موقتا رد', [ str_contains( $a->error, 'تلاش بیش از حد' ), str_contains( (string) $stop, 'redirect:' ) ], [ true, false ] );
$hp_otp_reset();
// ورود با کد فقط به حساب با شماره تأییدشده (نام کاربری ۰۹… را هر کسی می‌سازد)
$hp_squat = wp_insert_user( [ 'user_login' => '09127776666', 'user_pass' => 'Squat-Pass-1', 'user_email' => 'squat@hodima.test', 'role' => 'customer' ] );
unset( $_COOKIE[ Auth::COOKIE ] );
$hp_auth( [ 'send_otp' => '1', 'otp_mobile' => '09127776666' ] );
[ $a, $stop ] = $hp_auth( [ 'verify_otp' => '1', 'otp_code' => $hp_last_code() ] );
hodima_pt( 'کد درست برای شماره‌ای که فقط نام کاربری حساب دیگری است: ورود نه، حساب تازه نه', [ is_int( $hp_squat ), str_contains( (string) $stop, 'redirect:' ), get_current_user_id(), str_contains( $a->error, 'تأیید نشده' ), Auth::find_verified( '09127776666' ) ], [ true, false, 0, true, null ] );
if ( is_int( $hp_squat ) && $hp_squat > 1 ) {
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $hp_squat );
}
$hp_otp_reset();
// کارکنان فقط از wp-login
$hp_staff = wp_insert_user( [ 'user_login' => 'hp-staff', 'user_pass' => 'Staff-Pass-123', 'user_email' => 'hp-staff@hodima.test', 'role' => null !== get_role( 'shop_manager' ) ? 'shop_manager' : 'administrator' ] );
$hp_staff = is_int( $hp_staff ) ? $hp_staff : 0;
update_user_meta( $hp_staff, 'mobile', '09127778888' );
$hp_otp_reset();
[ $a, $stop ] = $hp_auth( [ 'login_with_password' => '1', 'mobile' => '09127778888', 'password' => 'Staff-Pass-123' ] );
hodima_pt( 'کارمند با رمز درست: وارد نمی‌شود، راهنمای wp-login', [ $hp_staff > 1, str_contains( (string) $stop, 'redirect:' ), get_current_user_id(), str_contains( $a->error, 'مدیریتی' ) ], [ true, false, 0, true ] );
[ $a ] = $hp_auth( [ 'login_with_password' => '1', 'mobile' => '09127778888', 'password' => 'wrong' ] );
hodima_pt( 'کارمند با رمز اشتباه: همان خطای عادی (مدیر بودن لو نمی‌رود)', str_contains( $a->error, 'مدیریتی' ), false );
unset( $_COOKIE[ Auth::COOKIE ] );
$hp_auth( [ 'send_otp' => '1', 'otp_mobile' => '09127778888' ] );
[ $a, $stop ] = $hp_auth( [ 'verify_otp' => '1', 'otp_code' => $hp_last_code() ] );
hodima_pt( 'کارمند با کد پیامکی درست: وارد نمی‌شود', [ str_contains( (string) $stop, 'redirect:' ), get_current_user_id(), str_contains( $a->error, 'مدیریتی' ) ], [ false, 0, true ] );
$_SERVER['REQUEST_METHOD'] = 'GET';
$_POST                     = $_REQUEST = [];

echo "== پروفایل: تغییر رمز و ایمیل فقط با رمز فعلی یا کد پیامکی\n";
$hp_otp_reset();
wp_set_current_user( (int) $new->ID );
update_user_meta( (int) $new->ID, 'billing_address_1', 'تهران خیابان آزمون پلاک ۹' );
update_user_meta( (int) $new->ID, 'billing_postcode', '1234567890' );
// ایمیل‌ها ضبط می‌شوند و فرستاده نمی‌شوند (لینک تأیید ایمیل را آزمون از متن ایمیل برمی‌دارد)
$hp_sent     = [];
$hp_mail_cap = static function ( mixed $r, array $atts ) use ( &$hp_sent ): bool {
	$hp_sent[] = $atts;
	return true;
};
add_filter( 'pre_wp_mail', $hp_mail_cap, 10, 2 );
$hp_link_of = static function ( array $mail ): array {
	preg_match( '/hpn_uid=(\d+)&hpn_email=([a-z0-9]+)/', (string) ( $mail['message'] ?? '' ), $m );
	return [ 'hpn_uid' => $m[1] ?? '', 'hpn_email' => $m[2] ?? '' ];
};
$hp_confirm = static function ( array $get ): string {
	$_GET = $_REQUEST = $get;
	$stop = hodima_pt_run( static fn() => do_action( 'hodima_panel_request', 'profile' ) );
	$_GET = $_REQUEST = [];
	return $stop;
};
wp_update_user( [ 'ID' => (int) $new->ID, 'user_email' => 'real@hodima.test', 'display_name' => 'سارا محمدی' ] );
add_filter( 'wp_doing_ajax', '__return_true' );
$hp_profile = static function ( array $extra ) use ( $new ): string {
	$_POST = $_REQUEST = $extra + [ 'nonce' => wp_create_nonce( 'up_update_profile_action' ), 'display_name' => 'سارا محمدی', 'user_email' => 'real@hodima.test', 'address' => 'تهران خیابان آزمون پلاک ۹', 'postcode' => '1234567890' ];
	hodima_pt_run( static fn() => do_action( 'wp_ajax_hodima_panel_profile' ) );
	clean_user_cache( (int) $new->ID );
	return $GLOBALS['hodima_pt_last_output'];
};
$out = $hp_profile( [ 'user_email' => 'thief@hodima.test' ] );
hodima_pt( 'تغییر ایمیل واقعی بی تأیید رد', [ str_contains( $out, '"need_verify":true' ), get_userdata( (int) $new->ID )->user_email ], [ true, 'real@hodima.test' ] );
$out = $hp_profile( [ 'new_password' => 'NewPass-456', 'confirm_password' => 'NewPass-456', 'current_password' => 'wrong' ] );
hodima_pt( 'تغییر رمز با رمز فعلی اشتباه رد', [ str_contains( $out, '"success":false' ), wp_check_password( 'Right-Pass-123', get_userdata( (int) $new->ID )->user_pass ) ], [ true, true ] );
$cookies = 0;
$hp_cookie = static function () use ( &$cookies ): void {
	++$cookies;
};
add_action( 'set_auth_cookie', $hp_cookie );
$out = $hp_profile( [ 'new_password' => 'NewPass-456', 'confirm_password' => 'NewPass-456', 'current_password' => 'Right-Pass-123' ] );
hodima_pt( 'تغییر رمز با رمز فعلی درست + ورود دوباره (بیرون نمی‌اندازد)', [ str_contains( $out, '"success":true' ), wp_check_password( 'NewPass-456', get_userdata( (int) $new->ID )->user_pass ), $cookies ], [ true, true, 1 ] );
$_POST = $_REQUEST = [ 'nonce' => wp_create_nonce( 'up_update_profile_action' ) ];
hodima_pt_run( static fn() => do_action( 'wp_ajax_hodima_panel_profile_code' ) );
hodima_pt( 'کد پیامکی تأیید به موبایل حساب', [ str_contains( $GLOBALS['hodima_pt_last_output'], '"success":true' ), ( end( $hp_http )['args']['body']['to'] ?? '' ) ], [ true, '09127770001' ] );
$pcode = $hp_last_code();
$out   = $hp_profile( [ 'user_email' => 'sara@hodima.test', 'verify_code' => '00000' === $pcode ? '11111' : '00000' ] );
hodima_pt( 'کد اشتباه ← ایمیل عوض نشد', [ str_contains( $out, '"success":false' ), get_userdata( (int) $new->ID )->user_email ], [ true, 'real@hodima.test' ] );
$out = $hp_profile( [ 'user_email' => 'sara@hodima.test', 'verify_code' => $pcode ] );
$hp_mail = end( $hp_sent ) ?: [];
hodima_pt( 'کد درست ← ایمیل حساب هنوز همان؛ لینک تأیید به ایمیل تازه، در انتظار', [ str_contains( $out, '"success":true' ), get_userdata( (int) $new->ID )->user_email, Profile::pending_email( get_userdata( (int) $new->ID ) ), $hp_mail['to'] ?? '', '' !== $hp_link_of( $hp_mail )['hpn_email'], str_contains( $out, '"keep":true' ) ], [ true, 'real@hodima.test', 'sara@hodima.test', 'sara@hodima.test', true, true ] );
$hp_link = $hp_link_of( $hp_mail );
$stop    = $hp_confirm( [ 'hpn_uid' => $hp_link['hpn_uid'], 'hpn_email' => 'wrongtoken' . $hp_link['hpn_email'] ] );
hodima_pt( 'لینک با توکن اشتباه: نامعتبر، ایمیل همان', [ str_contains( $stop, 'msg=email_link_invalid' ), get_userdata( (int) $new->ID )->user_email ], [ true, 'real@hodima.test' ] );
$hp_mails_before = count( $hp_sent );
wp_set_current_user( 0 );
$stop = $hp_confirm( $hp_link );
wp_set_current_user( (int) $new->ID );
clean_user_cache( (int) $new->ID );
hodima_pt( 'لینک درست (حتی بی ورود): ایمیل حساب عوض شد، انتظار پاک، اعلان به ایمیل قبلی', [ str_contains( $stop, 'msg=email_confirmed' ), get_userdata( (int) $new->ID )->user_email, Profile::pending_email( get_userdata( (int) $new->ID ) ), get_user_meta( (int) $new->ID, 'billing_email', true ), $hp_sent[ $hp_mails_before ]['to'] ?? '' ], [ true, 'sara@hodima.test', '', 'sara@hodima.test', 'real@hodima.test' ] );
hodima_pt( 'لینک مصرف‌شده دوباره کار نمی‌کند', str_contains( $hp_confirm( $hp_link ), 'msg=email_link_invalid' ), true );
$out = $hp_profile( [ 'user_email' => 'sara@hodima.test', 'display_name' => 'سارا محمدی‌پور' ] );
hodima_pt( 'تغییر بی‌خطر (نام) بی تأیید', str_contains( $out, '"success":true' ), true );
// کد پیامکی با خطای یک فیلد مصرف نمی‌شود
$hp_otp_reset();
Otp::issue( 'user-' . (int) $new->ID, '09127770001', 'profile', '198.51.100.8' );
$pcode2 = $hp_last_code();
$out    = $hp_profile( [ 'user_email' => 'sara2@hodima.test', 'postcode' => '123', 'verify_code' => $pcode2 ] );
$out2   = $hp_profile( [ 'user_email' => 'sara2@hodima.test', 'verify_code' => $pcode2 ] );
hodima_pt( 'کد درست + کد پستی غلط: خطای فیلد، کد هنوز معتبر برای ارسال درست', [ str_contains( $out, '"success":false' ), str_contains( $out2, '"success":true' ), Profile::pending_email( get_userdata( (int) $new->ID ) ) ], [ true, true, 'sara2@hodima.test' ] );
// ذخیره دوباره با همان ایمیل در انتظار: بی تأیید رمز و بی ایمیل تازه؛ «ارسال دوباره» ایمیل می‌فرستد؛ سقف ساعتی
delete_transient( 'hodima_panel_mail_' . (int) $new->ID );
$hp_n = count( $hp_sent );
$out  = $hp_profile( [ 'user_email' => 'sara2@hodima.test', 'display_name' => 'سارا محمدی‌پور' ] );
// «ارسال دوباره لینک»: فرم جدا (POST معمولی)
$hp_resend = static function ( string $nonce = '' ): string {
	$_SERVER['REQUEST_METHOD'] = 'POST';
	$_POST                     = $_REQUEST = [ 'hpn_resend_email' => '1', '_wpnonce_resend' => '' !== $nonce ? $nonce : wp_create_nonce( 'hodima_panel_resend_email' ) ];
	$stop                      = hodima_pt_run( static fn() => do_action( 'hodima_panel_request', 'profile' ) );
	$_SERVER['REQUEST_METHOD'] = 'GET';
	$_POST                     = $_REQUEST = [];
	return $stop;
};
$out2 = $hp_resend();
hodima_pt( 'همان ایمیل در انتظار: ذخیره بی رمز و بی ایمیل؛ «ارسال دوباره» یک ایمیل', [ str_contains( $out, '"success":true' ), str_contains( $out2, 'msg=profile_email_sent' ), count( $hp_sent ) - $hp_n, ( end( $hp_sent ) ?: [] )['to'] ?? '' ], [ true, true, 1, 'sara2@hodima.test' ] );
$hp_resend();
$hp_resend();
$out = $hp_resend();
hodima_pt( 'سقف ۳ ایمیل تأیید در ساعت', [ count( $hp_sent ) - $hp_n, str_contains( $out, 'msg=profile_email_limited' ) ], [ Profile::EMAIL_SENDS_PER_HOUR, true ] );
hodima_pt( '«ارسال دوباره» بی nonce درست رد', str_starts_with( $hp_resend( 'bad' ), 'die:' ), true );
[ $html ] = hodima_pt_render( '/user-panel/?section=profile', (int) $new->ID );
hodima_pt( 'فرم پروفایل: در انتظار تأیید با دکمه‌ای که مال فرم جداست (Enter = ذخیره، نه ارسال دوباره)', [ str_contains( $html, 'sara2@hodima.test</bdi>' ), (bool) preg_match( '/<button[^>]+form="hpn-resend-email"/', $html ), str_contains( $html, 'id="hpn-resend-email"' ) ], [ true, true, true ] );
// تا تأیید، ایمیل در «خروجی داده» هست و «حذف داده» پاکش می‌کند
$hp_export = \Hodima\Panel\Privacy::export( 'sara@hodima.test' );
hodima_pt( 'خروجی داده: ایمیل در انتظار تأیید', str_contains( (string) wp_json_encode( $hp_export['data'], JSON_UNESCAPED_UNICODE ), 'sara2@hodima.test' ), true );
// ایمیل در فاصله برای حساب دیگری ثبت شد ← لینک ایمیل را نمی‌دزدد
delete_transient( 'hodima_panel_mail_' . (int) $new->ID );
$hp_profile( [ 'user_email' => 'taken@hodima.test', 'current_password' => 'NewPass-456' ] );
$hp_link  = $hp_link_of( end( $hp_sent ) ?: [] );
$hp_other = wp_insert_user( [ 'user_login' => 'hp_taken', 'user_pass' => wp_generate_password(), 'user_email' => 'taken@hodima.test' ] );
$stop     = $hp_confirm( $hp_link );
hodima_pt( 'ایمیل در فاصله مال حساب دیگر شد: تأیید رد، ایمیل همان', [ str_contains( $stop, 'msg=email_taken' ), get_userdata( (int) $new->ID )->user_email ], [ true, 'sara@hodima.test' ] );
if ( is_int( $hp_other ) && $hp_other > 1 ) {
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $hp_other );
}
$hp_profile( [ 'user_email' => 'sara@hodima.test', 'current_password' => 'NewPass-456' ] );
hodima_pt( 'برگشت به ایمیل حساب: انتظار پاک می‌شود', Profile::pending_email( get_userdata( (int) $new->ID ) ), '' );
delete_transient( 'hodima_panel_mail_' . (int) $new->ID );
Profile::request_email_change( get_userdata( (int) $new->ID ), 'erase-me@hodima.test', true );
$hp_had   = Profile::pending_email( get_userdata( (int) $new->ID ) );
$hp_erase = \Hodima\Panel\Privacy::erase( 'sara@hodima.test' );
hodima_pt( 'حذف داده: ایمیل در انتظار تأیید هم پاک می‌شود', [ $hp_had, get_user_meta( (int) $new->ID, Profile::PENDING_META, true ), $hp_erase['items_removed'] ], [ 'erase-me@hodima.test', '', true ] );
// سقف حدس رمز فعلی
delete_transient( 'hodima_panel_pwcheck_' . (int) $new->ID );
for ( $i = 0; $i < Profile::CURRENT_PASSWORD_TRIES; $i++ ) {
	$hp_profile( [ 'new_password' => 'Guess-Pass-1', 'confirm_password' => 'Guess-Pass-1', 'current_password' => 'wrong-' . $i ] );
}
$out = $hp_profile( [ 'new_password' => 'Guess-Pass-1', 'confirm_password' => 'Guess-Pass-1', 'current_password' => 'NewPass-456' ] );
hodima_pt( '۵ رمز فعلی اشتباه ← حتی رمز درست موقتا رد', [ str_contains( (string) ( json_decode( $out, true )['data']['message'] ?? '' ), 'چند بار' ), wp_check_password( 'NewPass-456', get_userdata( (int) $new->ID )->user_pass ) ], [ true, true ] );
delete_transient( 'hodima_panel_pwcheck_' . (int) $new->ID );
// کد پیامکی فقط به شماره تأییدشده (نه billing_phone که در «حساب من» ووکامرس بی تأیید عوض می‌شود)
$hp_otp_reset();
delete_user_meta( (int) $new->ID, 'mobile' );
update_user_meta( (int) $new->ID, 'billing_phone', '09127775555' );
$hp_http = [];
$_POST   = $_REQUEST = [ 'nonce' => wp_create_nonce( 'up_update_profile_action' ) ];
hodima_pt_run( static fn() => do_action( 'wp_ajax_hodima_panel_profile_code' ) );
hodima_pt( 'بی شماره تأییدشده: کد پروفایل به billing_phone نمی‌رود', [ str_contains( $GLOBALS['hodima_pt_last_output'], '"success":false' ), count( $hp_http ), Profile::verified_mobile( get_userdata( (int) $new->ID ) ) ], [ true, 0, '' ] );
[ $html ] = hodima_pt_render( '/user-panel/?section=profile', (int) $new->ID );
hodima_pt( 'بی شماره تأییدشده: فرم پروفایل بی فیلد کد پیامکی', [ str_contains( $html, 'name="verify_code"' ), str_contains( $html, 'name="current_password"' ) ], [ false, true ] );
update_user_meta( (int) $new->ID, 'mobile', '09127770001' );
update_user_meta( (int) $new->ID, 'billing_phone', '09127770001' );
remove_action( 'set_auth_cookie', $hp_cookie );
remove_filter( 'wp_doing_ajax', '__return_true' );
// فرم بی جاوااسکریپت: POST معمولی به صفحه پنل، همان قانون‌ها، ریدایرکت با پیام
$_SERVER['REQUEST_METHOD'] = 'POST';
$hp_form = static function ( array $extra ) use ( $new ): string {
	$_POST = $_REQUEST = $extra + [ 'up_profile_nonce' => wp_create_nonce( 'up_update_profile_action' ), 'display_name' => 'سارا محمدی‌پور', 'user_email' => 'sara@hodima.test', 'address' => 'تهران خیابان آزمون پلاک ۹', 'postcode' => '1234567890' ];
	$stop  = hodima_pt_run( static fn() => do_action( 'hodima_panel_request', 'profile' ) );
	clean_user_cache( (int) $new->ID );
	return $stop;
};
$stop = $hp_form( [ 'display_name' => 'سارا بی‌جاوا' ] );
hodima_pt( 'فرم بی JS: ذخیره و ریدایرکت با پیام', [ str_contains( $stop, 'msg=profile_saved' ), get_userdata( (int) $new->ID )->display_name ], [ true, 'سارا بی‌جاوا' ] );
$stop = $hp_form( [ 'new_password' => 'Other-Pass-789', 'confirm_password' => 'Other-Pass-789' ] );
hodima_pt( 'فرم بی JS: تغییر رمز بی تأیید رد، خطا برای نمایش', [ str_contains( $stop, 'msg=profile_error' ), wp_check_password( 'NewPass-456', get_userdata( (int) $new->ID )->user_pass ), str_contains( Profile::take_error( (int) $new->ID ), 'رمز فعلی' ), Profile::take_error( (int) $new->ID ) ], [ true, true, true, '' ] );
$_POST = $_REQUEST = [ 'up_profile_nonce' => 'bad', 'display_name' => 'نفوذ' ];
hodima_pt( 'فرم بی JS: nonce نادرست رد', str_starts_with( hodima_pt_run( static fn() => do_action( 'hodima_panel_request', 'profile' ) ), 'die:' ), true );
$_POST = $_REQUEST = [];
$stop = $hp_form( [ 'address' => 'تهران کوچه نگه‌داشته پلاک ۷', 'postcode' => '12' ] );
[ $html ] = hodima_pt_render( '/user-panel/?section=profile', (int) $new->ID );
[ $html2 ] = hodima_pt_render( '/user-panel/?section=profile', (int) $new->ID );
hodima_pt( 'فرم بی JS با خطا: نوشته‌های مشتری در فرم می‌ماند (یک بار)، رمز نه', [ str_contains( $stop, 'msg=profile_error' ), str_contains( $html, 'تهران کوچه نگه‌داشته پلاک ۷' ), str_contains( $html, 'value="12"' ), str_contains( $html2, 'تهران کوچه نگه‌داشته پلاک ۷' ) ], [ true, true, true, false ] );
[ $html ] = hodima_pt_render( '/user-panel/?section=profile', (int) $new->ID );
hodima_pt( 'فرم پروفایل POST است (رمز هرگز در آدرس)', (bool) preg_match( '/<form[^>]+id="hpn-profile"[^>]+method="post"/', $html ), true );
// کارکنان: تغییر رمز/ایمیل فقط با رمز فعلی، نه کد پیامکی
if ( $hp_staff > 1 ) {
	wp_set_current_user( $hp_staff );
	$hp_otp_reset();
	add_filter( 'wp_doing_ajax', '__return_true' );
	$_POST = $_REQUEST = [ 'nonce' => wp_create_nonce( 'up_update_profile_action' ) ];
	hodima_pt_run( static fn() => do_action( 'wp_ajax_hodima_panel_profile_code' ) );
	remove_filter( 'wp_doing_ajax', '__return_true' );
	hodima_pt( 'کارمند: کد پیامکی پروفایل فرستاده نمی‌شود', str_contains( $GLOBALS['hodima_pt_last_output'], '"success":false' ), true );
	Otp::issue( 'user-' . $hp_staff, '09127778888', 'profile', '198.51.100.7' );
	$r = Profile::process( get_userdata( $hp_staff ), [ 'name' => 'مدیر فروشگاه', 'email' => 'hp-staff@hodima.test', 'user_email' => 'hp-staff@hodima.test', 'address' => 'تهران خیابان آزمون پلاک ۹', 'postcode' => '1234567890', 'new_password' => 'Hijack-Pass-1', 'confirm_password' => 'Hijack-Pass-1', 'verify_code' => $hp_last_code() ] );
	hodima_pt( 'کارمند: کد پیامکی درست هم رمز را عوض نمی‌کند', [ $r['ok'], $r['need_verify'], wp_check_password( 'Staff-Pass-123', get_userdata( $hp_staff )->user_pass ) ], [ false, true, true ] );
	[ $html ] = hodima_pt_render( '/user-panel/?section=profile', $hp_staff );
	hodima_pt( 'کارمند: فرم پروفایل بی فیلد کد پیامکی', str_contains( $html, 'name="verify_code"' ), false );
	wp_delete_user( $hp_staff );
}
$_SERVER['REQUEST_METHOD'] = 'GET';
remove_filter( 'pre_wp_mail', $hp_mail_cap, 10 );
$_POST = $_REQUEST = [];
require_once ABSPATH . 'wp-admin/includes/user.php';
wp_delete_user( (int) $new->ID );
wp_set_current_user( 0 );

echo "== رندر واقعی پنل (بدون هشدار PHP)\n";
$wpdb->update( table( 'tickets' ), [ 'user_unread' => 1 ], [ 'id' => $ticket->id ] );
[ $html, $err ] = hodima_pt_render( '/user-panel/' );
hodima_pt( 'مهمان: فرم ورود', str_contains( $html, 'hodima_auth_nonce' ) && str_contains( $html, '</html>' ), true );
hodima_pt( 'مهمان: noindex', (bool) preg_match( "/<meta name='robots' content='[^']*noindex/", $html ), true );
hodima_pt( 'مهمان: CSS و JS یکپارچه پنل، بی اسکریپت گفتگو', [ str_contains( $html, 'css/panel.css' ), str_contains( $html, 'js/panel.js' ), str_contains( $html, 'js/chat.js' ), str_contains( $html, 'css/login.css' ) ], [ true, true, false, false ] );
hodima_pt( 'مهمان: بدون خطا', $err, '' );
hodima_pt( 'مهمان: ثبت‌نام فقط با کد، فیلد ضد ربات تازه، پیامک فعال', [ str_contains( $html, 'do_register' ), str_contains( $html, 'up_website_url' ), str_contains( $html, Auth::TRAP ), str_contains( $html, 'name="send_otp"' ), str_contains( $html, 'data-hpn-tabs' ), str_contains( $html, 'name="login_with_password"' ) ], [ false, false, true, true, true, true ] );
delete_option( Sms::OPTION );
[ $html ] = hodima_pt_render( '/user-panel/' );
hodima_pt( 'مهمان، پیامک تنظیم‌نشده: فقط فرم رمز، بی تب', [ str_contains( $html, 'با شماره موبایل و رمز عبور' ), str_contains( $html, 'name="send_otp"' ), str_contains( $html, 'data-hpn-tabs' ), str_contains( $html, 'name="login_with_password"' ) ], [ true, false, false, true ] );
$sections = [
	'dashboard'  => 'hpn-stats',
	'orders'     => 'hpn-order',
	'prebuy'     => 'گیره آزمون پنل',
	'preinvoice' => 'فعلا محصولی',
	'support'    => 'hpn-thread',
	'profile'    => 'data-hpn-profile',
];
foreach ( $sections as $section => $needle ) {
	[ $html, $err ] = hodima_pt_render( '/user-panel/?section=' . $section . ( 'support' === $section ? '&thread_id=' . $ticket->id : '' ), $user_a );
	hodima_pt( "مشتری: بخش {$section} کامل و بدون خطا", [ str_contains( $html, $needle ), str_contains( $html, '</html>' ), $err ], [ true, true, '' ] );
	if ( 'prebuy' === $section ) {
		hodima_pt( 'فرم سفارش بی خطای ذخیره‌شده: کادر خطای خالی نیست', str_contains( $html, 'hpn-notice--error' ), false );
	}
}
[ $html, $err ] = hodima_pt_render( '/user-panel/?section=support&new=1', $user_a );
hodima_pt( 'پیام تازه: دو گزینه، بی «واحد» و «فوریت»، سفارش خود مشتری در فهرست', [ str_contains( $html, 'value="consult"' ), str_contains( $html, 'value="order"' ), str_contains( $html, 'name="department"' ), str_contains( $html, 'name="priority"' ), str_contains( $html, '<option value="' . $order_a . '"' ), str_contains( $html, '<option value="' . $order_b . '"' ), str_contains( $html, 'hpn-inbox--new' ), $err ], [ true, true, false, false, true, false, true, '' ] );
// کاربر بی سفارش؛ شناسه فقط اگر واقعا ساخته شد (WP_Error به عدد = ۱ = حذف مدیر)
$hp_noorder = wp_insert_user( [ 'user_login' => 'hp-no-order', 'user_pass' => wp_generate_password(), 'user_email' => 'no-order@hodima.test', 'role' => 'subscriber' ] );
$hp_noorder = is_int( $hp_noorder ) ? $hp_noorder : 0;
[ $html ] = $hp_noorder ? hodima_pt_render( '/user-panel/?section=support&new=1', $hp_noorder ) : [ '' ];
if ( $hp_noorder > 1 ) {
	wp_delete_user( $hp_noorder );
}
hodima_pt( 'پیام تازه بی سفارش: «پیگیری سفارش» غیرفعال با توضیح، بی فهرست سفارش', [ (bool) preg_match( '/value="order"\s+disabled/', $html ), str_contains( $html, 'هنوز سفارشی ندارید' ), str_contains( $html, 'name="order_id"' ) ], [ true, true, false ] );
[ $html, $err ] = hodima_pt_render( '/user-panel/?section=prebuy&edit_order=' . $order_a, $user_a );
hodima_pt( 'فرم ویرایش سفارش در حال بررسی: پیام قفل، بی فرم', [ str_contains( $html, 'قابل ویرایش نیست' ), str_contains( $html, 'name="products[]"' ), $err ], [ true, false, '' ] );
// داشبورد: شمارش با GROUP BY و قدیمی‌ترین سفارش منتظر پرداخت، بی خواندن همه سفارش‌ها
$hp_dash = wp_insert_user( [ 'user_login' => 'hp_dash', 'user_pass' => wp_generate_password(), 'user_email' => 'hp_dash@hodima.test', 'role' => 'customer' ] );
if ( is_int( $hp_dash ) && $hp_dash > 1 ) {
	foreach ( [ 'billing_first_name' => 'داشبورد', 'billing_last_name' => 'آزمون', 'billing_email' => 'hp_dash@hodima.test', 'billing_address_1' => 'تهران، خیابان آزمون، پلاک ۳', 'billing_postcode' => '1234567890' ] as $mk => $mv ) {
		update_user_meta( $hp_dash, $mk, $mv );
	}
	$hp_ids = [];
	foreach ( [ 'completed', 'awaiting_payment', 'awaiting_payment', 'pending', 'cancelled' ] as $st ) {
		$wpdb->insert( table( 'orders' ), [ 'user_id' => $hp_dash, 'type' => 'prebuy', 'status' => $st, 'total_price' => 1000, 'created_at' => \Hodima\Panel\now(), 'updated_at' => \Hodima\Panel\now() ] );
		$hp_ids[] = (int) $wpdb->insert_id;
	}
	[ $html ] = hodima_pt_render( '/user-panel/?section=dashboard', $hp_dash );
	$hp_stat  = static fn( string $label ): string => preg_match( '#<span>' . preg_quote( $label, '#' ) . '</span><strong>([^<]*)</strong>#u', $html, $m ) ? $m[1] : '?';
	hodima_pt( 'داشبورد: شمار هر گروه، قدیمی‌ترین منتظر پرداخت، ۳ سفارش اخیر', [ $hp_stat( 'در حال بررسی' ), $hp_stat( 'منتظر پرداخت شما' ), $hp_stat( 'تکمیل‌شده' ), str_contains( $html, 'پیش‌فاکتور سفارش #' . number_format_i18n( $hp_ids[1] ) . ' آماده است' ), substr_count( $html, 'class="hpn-recent__row"' ) ], [ number_format_i18n( 1 ), number_format_i18n( 2 ), number_format_i18n( 1 ), true, 3 ] );
	foreach ( $hp_ids as $hp_id ) {
		\Hodima\Panel\Orders::delete( $hp_id );
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $hp_dash );
}
[ $html ] = hodima_pt_render( '/?hodima_panel_file=' . $fid, $user_a );
hodima_pt( 'نشانی امن: صاحب سفارش خود فایل را می‌گیرد', str_starts_with( $html, "\x89PNG" ), true );
[ $html ] = hodima_pt_render( '/?hodima_panel_file=' . $fid, $user_b );
hodima_pt( 'نشانی امن: مشتری دیگر فایل را نمی‌گیرد', [ str_starts_with( $html, "\x89PNG" ), str_contains( $html, 'اجازه دیدنش را ندارید' ) ], [ false, true ] );
[ $html, $err ] = hodima_pt_render( '/?hodima_panel_file=' . $fid );
hodima_pt( 'نشانی امن: مهمان ← ریدایرکت به پنل', [ str_starts_with( $html, "\x89PNG" ), str_contains( $err, 'REDIRECT -> ' . Page::url() ) ], [ false, true ] );
hodima_pt( 'نشانی امن: مهمان ← بعد از ورود همان فایل (redirect_to)', str_contains( $err, 'redirect_to=' . rawurlencode( Files::url( $fid ) ) ), true );
hodima_pt( 'باز کردن تیکت پیش از منو خوانده شد', (int) $wpdb->get_var( $wpdb->prepare( 'SELECT user_unread FROM %i WHERE id = %d', table( 'tickets' ), $ticket->id ) ), 0 );
$wpdb->update( table( 'orders' ), [ 'status' => 'completed' ], [ 'id' => $order_a ] );
[ $html ] = hodima_pt_render( '/user-panel/?section=orders', $user_a );
$wpdb->update( table( 'orders' ), [ 'status' => 'receipt_uploaded' ], [ 'id' => $order_a ] );
hodima_pt( 'سفارش‌ها (تکمیل‌شده): فیش‌ها هنوز دیده می‌شوند، با نشانی امن، و اسکریپت گفتگو', [ str_contains( $html, 'hodima_panel_file=' . $fid ), str_contains( $html, 'hodima-panel-private' ), str_contains( $html, 'js/chat.js' ) ], [ true, false, true ] );
[ $html, $err ] = hodima_pt_render( '/user-panel/?section=orders', $user_a );
hodima_pt( 'سفارش‌ها: مراحل، پنجره گفتگو، فیلتر، نوار پایین موبایل', [ str_contains( $html, 'hpn-steps' ), str_contains( $html, '<dialog' ), str_contains( $html, 'filter=wait' ), str_contains( $html, 'hpn-bottom' ), $err ], [ true, true, true, true, '' ] );
[ $html ] = hodima_pt_render( '/user-panel/?section=orders&filter=done', $user_a );
hodima_pt( 'سفارش‌ها: فیلتر «تکمیل‌شده» سفارش در حال بررسی را نشان نمی‌دهد', str_contains( $html, 'id="hpn-order-' . $order_a . '"' ), false );
[ $html ] = hodima_pt_render( '/user-panel/?section=orders&filter=done&chat=' . $order_a, $user_a );
hodima_pt( 'سفارش‌ها: ?chat= سفارشِ بیرون از صفحه را هم می‌آورد (لینک پیام‌ها کار کند)', str_contains( $html, 'data-hpn-chat="' . $order_a . '"' ), true );
[ $html ] = hodima_pt_render( '/user-panel/?section=orders&filter=done&chat=' . $order_b, $user_a );
hodima_pt( 'سفارش‌ها: ?chat= سفارش مشتری دیگر را نمی‌آورد', str_contains( $html, 'hpn-order-' . $order_b ), false );

echo "== پیشخوان (طرح مرحله ۶: سفارش‌ها، یک سفارش، تیکت‌ها، کادر محصول)\n";
require_once ABSPATH . 'wp-admin/includes/admin.php';
require_once HODIMA_PANEL_DIR . '/includes/admin-orders.php';
require_once HODIMA_PANEL_DIR . '/includes/admin-tickets.php';
require_once HODIMA_PANEL_DIR . '/includes/admin-product.php';
use Hodima\Panel\Admin_Orders;
use Hodima\Panel\Admin_Tickets;
use Hodima\Panel\Admin_Product;
wp_set_current_user( 1 );
set_current_screen( 'toplevel_page_hodima-panel-orders' );
$GLOBALS['menu'] = $GLOBALS['submenu'] = [];
set_transient( 'hodima_panel_badge_count', 7, MINUTE_IN_SECONDS );
Admin_Orders::menu();
Admin_Tickets::menu();
ob_start();
Admin_Orders::kit_notice();
hodima_pt( 'کیت نصب است: هشدار «ناقص نصب شده» نمی‌آید', [ Admin_Orders::kit_ready(), (string) ob_get_clean() ], [ true, '' ] );
$hp_menu = array_values( array_filter( $GLOBALS['menu'], static fn( $m ): bool => ( $m[2] ?? '' ) === Admin_Orders::SLUG ) );
hodima_pt( 'منو بدون عدد کنار نام (قاعده ثابت ۴) و زیرمنوی تیکت', [ $hp_menu[0][0] ?? '', array_column( $GLOBALS['submenu'][ Admin_Orders::SLUG ] ?? [], 2 ) ], [ 'پنل مشتریان', [ Admin_Orders::SLUG, Admin_Tickets::SLUG ] ] );
$hp_admin = static function ( array $get, callable $fn ): string {
	$_GET  = $get;
	$_REQUEST = $get;
	$stop  = hodima_pt_run( $fn );
	return $stop . $GLOBALS['hodima_pt_last_output'];
};
$hp_post = static function ( array $get, array $post, callable $fn ): string {
	$_SERVER['REQUEST_METHOD'] = 'POST';
	$_GET                      = $get;
	$_POST                     = $post;
	$_REQUEST                  = $get + $post;
	$stop                      = hodima_pt_run( $fn );
	$_SERVER['REQUEST_METHOD'] = 'GET';
	$_GET                      = $_POST = $_REQUEST = [];
	return $stop;
};
$render_orders = [ Admin_Orders::class, 'render' ];
$out = $hp_admin( [ 'page' => Admin_Orders::SLUG ], $render_orders );
hodima_pt( 'فهرست همه سفارش‌ها: آمار، نوار ابزار، ردیف کار شما', [ str_contains( $out, 'فیش برای بررسی' ), str_contains( $out, 'hpa-orders-form' ), (bool) preg_match( '/<tr class="is-todo">.*?value="' . $order_a . '"/s', $out ), str_contains( $out, 'hp_a' ) ], [ true, true, true, true ] );
$out = $hp_admin( [ 'page' => Admin_Orders::SLUG, 'view' => 'done' ], $render_orders );
hodima_pt( 'فیلتر «تکمیل» سفارش در حال بررسی فیش را ندارد', str_contains( $out, 'value="' . $order_a . '"' ), false );
$out = $hp_admin( [ 'page' => Admin_Orders::SLUG, 's' => '#' . $order_a ], $render_orders );
hodima_pt( 'جستجو با شماره سفارش', str_contains( $out, 'value="' . $order_a . '"' ), true );
$out = $hp_admin( [ 'page' => Admin_Orders::SLUG, 's' => '0912000000' ], $render_orders );
hodima_pt( 'جستجو با موبایل مشتری', str_contains( $out, 'value="' . $order_a . '"' ), true );
$out = $hp_admin( [ 'page' => Admin_Orders::SLUG, 'user_id' => (string) $user_b ], $render_orders );
hodima_pt( 'فیلتر یک مشتری دیگر سفارش این مشتری را ندارد', [ str_contains( $out, 'value="' . $order_a . '"' ), str_contains( $out, 'همه مشتری‌ها' ) ], [ false, true ] );

$wpdb->update( table( 'chats' ), [ 'admin_unread' => 3 ], [ 'order_id' => $order_a ] );
$out = $hp_admin( [ 'page' => Admin_Orders::SLUG, 'action' => 'view', 'order_id' => (string) $order_a ], $render_orders );
hodima_pt( 'یک سفارش: جمع اقلام همان مبلغ مشتری، فیش با نشانی امن، کار بعدی', [ str_contains( $out, number_format_i18n( 37500 ) . ' تومان' ), str_contains( $out, 'hodima_panel_file=' . $fid ), str_contains( $out, 'کار بعدی: فیش را بررسی کنید' ), str_contains( $out, 'hodima-panel-private' ) ], [ true, true, true, false ] );
hodima_pt( 'باز کردن سفارش فقط همان گفتگو را خوانده می‌کند', (int) $wpdb->get_var( $wpdb->prepare( 'SELECT admin_unread FROM %i WHERE order_id = %d', table( 'chats' ), $order_a ) ), 0 );
$view_get = [ 'page' => Admin_Orders::SLUG, 'action' => 'view', 'order_id' => (string) $order_a ];
$handle   = [ Admin_Orders::class, 'handle' ];
$stop     = $hp_post( $view_get, [ 'order_id' => (string) $order_a, 'hp_action' => 'complete' ], $handle );
hodima_pt( 'تکمیل بی nonce رد شد', [ str_starts_with( $stop, 'die:' ), \Hodima\Panel\Orders::get( $order_a )->status ], [ true, 'receipt_uploaded' ] );
$msgs = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE chat_id = %d', table( 'chat_messages' ), $chat->id ) );
$stop = $hp_post( $view_get, [ 'order_id' => (string) $order_a, 'hp_action' => 'complete', '_wpnonce' => wp_create_nonce( 'hodima_panel_order_' . $order_a ) ], $handle );
hodima_pt( 'تکمیل سفارش: وضعیت، پیام خودکار به مشتری، ریدایرکت', [ \Hodima\Panel\Orders::get( $order_a )->status, (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE chat_id = %d', table( 'chat_messages' ), $chat->id ) ) - $msgs, str_contains( $stop, 'hp_msg=completed' ) ], [ 'completed', 1, true ] );
$stop = $hp_post( $view_get, [ 'order_id' => (string) $order_a, 'hp_action' => 'complete', '_wpnonce' => wp_create_nonce( 'hodima_panel_order_' . $order_a ) ], $handle );
hodima_pt( 'تکمیل دوباره: پیام تکراری به مشتری نمی‌رود', [ str_contains( $stop, 'hp_msg=already_completed' ), (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE chat_id = %d', table( 'chat_messages' ), $chat->id ) ) - $msgs ], [ true, 1 ] );
$stop = $hp_post( $view_get, [ 'order_id' => (string) $order_a, 'hp_action' => 'status', 'order_status' => 'hacked', '_wpnonce' => wp_create_nonce( 'hodima_panel_order_' . $order_a ) ], $handle );
hodima_pt( 'وضعیت دستی ناشناخته نادیده', \Hodima\Panel\Orders::get( $order_a )->status, 'completed' );
$stop = $hp_post( $view_get, [ 'order_id' => (string) $order_a, 'hp_action' => 'status', 'order_status' => 'receipt_uploaded', '_wpnonce' => wp_create_nonce( 'hodima_panel_order_' . $order_a ) ], $handle );
hodima_pt( 'وضعیت دستی بی پیام خودکار', [ \Hodima\Panel\Orders::get( $order_a )->status, (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE chat_id = %d', table( 'chat_messages' ), $chat->id ) ) - $msgs ], [ 'receipt_uploaded', 1 ] );
$stop = $hp_post( $view_get, [ 'order_id' => (string) $order_a, 'hp_action' => 'chat', 'message' => 'پاسخ \\"مدیر\\"', '_wpnonce' => wp_create_nonce( 'hodima_panel_order_' . $order_a ) ], $handle );
hodima_pt( 'پیام مدیر با امضا و بی \\ اضافه؛ برای مشتری نخوانده', [ str_starts_with( (string) $wpdb->get_var( $wpdb->prepare( "SELECT message FROM %i WHERE chat_id = %d AND sender_type = 'admin' ORDER BY id DESC LIMIT 1", table( 'chat_messages' ), $chat->id ) ), "پاسخ \"مدیر\"\n\nپاسخگو:" ), str_contains( $stop, 'hp_msg=chat' ) ], [ true, true ] );

$render_tickets = [ Admin_Tickets::class, 'render' ];
$wpdb->update( table( 'tickets' ), [ 'status' => 'open', 'admin_unread' => 2 ], [ 'id' => $ticket->id ] );
$out = $hp_admin( [ 'page' => Admin_Tickets::SLUG ], $render_tickets );
hodima_pt( 'تیکت‌ها: پیش‌فرض «نیاز به پاسخ» با همین تیکت', [ str_contains( $out, 'aria-current="page">نیاز به پاسخ' ), str_contains( $out, 'thread_id=' . $ticket->id ) ], [ true, true ] );
$out = $hp_admin( [ 'page' => Admin_Tickets::SLUG, 'thread_id' => (string) $ticket->id ], $render_tickets );
hodima_pt( 'یک تیکت کنار فهرست: پیام، موبایل مشتری، خوانده شد', [ str_contains( $out, 'متن &quot;نقل&quot;' ), str_contains( $out, '09120000001' ), (int) $wpdb->get_var( $wpdb->prepare( 'SELECT admin_unread FROM %i WHERE id = %d', table( 'tickets' ), $ticket->id ) ) ], [ true, true, 0 ] );
$t_get = [ 'page' => Admin_Tickets::SLUG, 'thread_id' => (string) $ticket->id ];
$stop  = $hp_post( $t_get, [ 'thread_id' => (string) $ticket->id, 'hp_action' => 'reply', 'message' => 'پاسخ' ], [ Admin_Tickets::class, 'handle' ] );
hodima_pt( 'پاسخ تیکت بی nonce رد شد', str_starts_with( $stop, 'die:' ), true );
$before = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT user_unread FROM %i WHERE id = %d', table( 'tickets' ), $ticket->id ) );
$stop   = $hp_post( $t_get, [ 'thread_id' => (string) $ticket->id, 'hp_action' => 'reply', 'message' => 'پاسخ پشتیبانی', '_wpnonce' => wp_create_nonce( 'hodima_panel_ticket_' . $ticket->id ) ], [ Admin_Tickets::class, 'handle' ] );
$t_row  = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', table( 'tickets' ), $ticket->id ) );
hodima_pt( 'پاسخ تیکت: «پاسخ داده شد»، برای مشتری نخوانده', [ $t_row->status, (int) $t_row->user_unread - $before, str_contains( $stop, 'hp_msg=reply' ) ], [ 'answered', 1, true ] );
$stop  = $hp_post( $t_get, [ 'thread_id' => (string) $ticket->id, 'hp_action' => 'close', 'message' => '', '_wpnonce' => wp_create_nonce( 'hodima_panel_ticket_' . $ticket->id ) ], [ Admin_Tickets::class, 'handle' ] );
hodima_pt( 'بستن تیکت', $wpdb->get_var( $wpdb->prepare( 'SELECT status FROM %i WHERE id = %d', table( 'tickets' ), $ticket->id ) ), 'closed' );
$t_row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', table( 'tickets' ), $ticket->id ) );
$hp_close_msgs = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE ticket_id = %d AND sender_type = 'system'", table( 'ticket_messages' ), $ticket->id ) );
hodima_pt( 'بستن تیکت: پیام سیستمی و نشان «تازه» برای مشتری', [ $hp_close_msgs, (int) $t_row->user_unread > 0, $t_row->last_message_sender ], [ 1, true, 'system' ] );
$hp_post( $t_get, [ 'thread_id' => (string) $ticket->id, 'hp_action' => 'close', '_wpnonce' => wp_create_nonce( 'hodima_panel_ticket_' . $ticket->id ) ], [ Admin_Tickets::class, 'handle' ] );
hodima_pt( 'بستن دوباره: پیام تکراری نه', (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE ticket_id = %d AND sender_type = 'system'", table( 'ticket_messages' ), $ticket->id ) ), 1 );
// برگه پنل از دست رفته: هشدار در پیشخوان و ساخت/انتشار دوباره همان برگه
$hp_page = Page::id();
wp_update_post( [ 'ID' => $hp_page, 'post_status' => 'draft' ] );
ob_start();
Admin_Orders::page_notice();
$hp_note = (string) ob_get_clean();
$hp_ensured = \Hodima\Panel\Page::ensure();
hodima_pt( 'برگه پیش‌نویس‌شده: هشدار با دکمه، ensure همان برگه را منتشر می‌کند', [ str_contains( $hp_note, 'hodima_panel_page' ), $hp_ensured, (string) $wpdb->get_var( $wpdb->prepare( 'SELECT post_status FROM %i WHERE ID = %d', $wpdb->posts, $hp_page ) ) ], [ true, $hp_page, 'publish' ] );
ob_start();
Admin_Orders::page_notice();
hodima_pt( 'برگه سالم: بی هشدار', (string) ob_get_clean(), '' );
$out = $hp_admin( $t_get, $render_tickets );
hodima_pt( 'تیکت بسته فرم پاسخ ندارد', str_contains( $out, 'name="message"' ), false );

// کادر محصول: رفت‌وبرگشت فرم رندرشده بی تغییر متا؛ ذخیره بی کادر چیزی را خاموش نمی‌کند
$box = static function ( int $pid ): string {
	ob_start();
	Admin_Product::render( get_post( $pid ) );
	return (string) ob_get_clean();
};
$form_of = static function ( string $html ): array {
	$doc = new DOMDocument();
	@$doc->loadHTML( '<?xml encoding="utf-8"?><div>' . $html . '</div>' ); // phpcs:ignore
	$out = [];
	foreach ( $doc->getElementsByTagName( 'input' ) as $in ) {
		$type = $in->getAttribute( 'type' );
		if ( 'checkbox' === $type && ! $in->hasAttribute( 'checked' ) ) {
			continue;
		}
		$out[ $in->getAttribute( 'name' ) ] = $in->getAttribute( 'value' );
	}
	foreach ( $doc->getElementsByTagName( 'select' ) as $sel ) {
		$val = '';
		foreach ( $sel->getElementsByTagName( 'option' ) as $opt ) {
			if ( $opt->hasAttribute( 'selected' ) ) {
				$val = $opt->getAttribute( 'value' );
			}
		}
		$out[ $sel->getAttribute( 'name' ) ] = $val;
	}
	return $out;
};
$meta_of = static fn( int $pid ): array => array_map( static fn( string $k ): string => (string) get_post_meta( $pid, $k, true ), [ '_prebuy_enabled' => '_prebuy_enabled', '_preinvoice_enabled' => '_preinvoice_enabled', '_up_sale_unit' => '_up_sale_unit', '_up_unit_capacity' => '_up_unit_capacity', '_up_min_qty' => '_up_min_qty' ] );
$html = $box( $product );
hodima_pt( 'کادر محصول: کلید روشن، پیش‌نمایش همان جمله پنل مشتری', [ (bool) preg_match( '/name="_prebuy_enabled"[^>]*checked/', $html ), str_contains( $html, 'هر کیلو ' . number_format_i18n( 12 ) . ' عدد · حداقل ' ), str_contains( $html, 'در پنل: پیش‌خرید' ) ], [ true, true, true ] );
update_post_meta( $product, '_preinvoice_enabled', 'no' ); // خالی و no هر دو یعنی خاموش؛ ذخیره همیشه no می‌نویسد (مثل قبل)
$before = $meta_of( $product );
$_POST  = wp_slash( $form_of( $html ) );
Admin_Product::save( $product );
hodima_pt( 'کادر محصول: رفت‌وبرگشت فرم بی تغییر متا', $meta_of( $product ), $before );
$_POST = [ '_up_sale_unit' => 'جین' ];
Admin_Product::save( $product );
hodima_pt( 'ذخیره بی کادر (ویرایش سریع) چیزی را عوض نمی‌کند', $meta_of( $product ), $before );
$_POST = [ 'hodima_panel_product_box' => '1', '_up_sale_unit' => 'کیلو', '_up_unit_capacity' => '۱۲', '_up_min_qty' => '۰٫۵' ];
Admin_Product::save( $product );
hodima_pt( 'تیک‌ها خاموش، رقم فارسی', [ get_post_meta( $product, '_prebuy_enabled', true ), get_post_meta( $product, '_up_unit_capacity', true ) ], [ 'no', '12' ] );
foreach ( $before as $k => $v ) {
	update_post_meta( $product, $k, $v );
}
$_POST = [];

echo "== حذف گروهی فقط با nonce\n";
$list_get = [ 'page' => Admin_Orders::SLUG ];
$stop     = $hp_post( $list_get, [ 'hp_bulk' => 'delete', 'order_ids' => [ (string) $order_a ] ], $handle );
hodima_pt( 'بدون nonce رد شد', str_starts_with( $stop, 'die:' ), true );
hodima_pt( 'سفارش ماند', (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE id = %d', table( 'orders' ), $order_a ) ), 1 );
$stop = $hp_post( $list_get, [ 'hp_bulk' => 'delete', 'order_ids' => [ (string) $order_a ], '_wpnonce' => wp_create_nonce( 'hodima_panel_orders_bulk' ) ], $handle );
hodima_pt( 'با nonce حذف شد', str_contains( $stop, 'hp_msg=deleted' ), true );
$left = [];
foreach ( [ 'orders' => 'id', 'order_items' => 'order_id', 'order_files' => 'order_id', 'chats' => 'order_id' ] as $t => $col ) {
	$left[ $t ] = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE %i = %d', table( $t ), $col, $order_a ) );
}
hodima_pt( 'حذف کامل: سفارش، اقلام، فایل، گفتگو', $left, [ 'orders' => 0, 'order_items' => 0, 'order_files' => 0, 'chats' => 0 ] );
hodima_pt( 'پیام‌های گفتگو هم حذف شد', (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE chat_id = %d', table( 'chat_messages' ), $chat->id ) ), 0 );
hodima_pt( 'خود فایل فیش حذف شد', file_exists( $path ), false );
$_GET = $_POST = $_REQUEST = [];

echo "== حریم خصوصی و به‌روزرسانی خودکار\n";
use Hodima\Panel\Privacy;
hodima_pt( 'به‌روزرسانی خودکار Core پنل را می‌شناسد', function_exists( '\Hodima\Core\Updates\packages' ) ? ( \Hodima\Core\Updates\packages()['hodima-panel'] ?? '' ) : 'no-core', HODIMA_PANEL_FILE );
hodima_pt( 'خروجی و حذف داده در ابزار وردپرس ثبت شد', [ isset( apply_filters( 'wp_privacy_personal_data_exporters', [] )['hodima-panel'] ), isset( apply_filters( 'wp_privacy_personal_data_erasers', [] )['hodima-panel'] ) ], [ true, true ] );
$hp_priv = static function ( int $uid ) use ( $wpdb, $png ): array {
	$t = \Hodima\Panel\now();
	$wpdb->insert( table( 'orders' ), [ 'user_id' => $uid, 'type' => 'prebuy', 'status' => 'completed', 'total_price' => 5000, 'created_at' => $t, 'updated_at' => $t ] );
	$oid = (int) $wpdb->insert_id;
	\Hodima\Panel\Orders::add_chat_message( $oid, $uid, 'user', 'پیام خصوصی آزمون' );
	$path = (string) Files::path( (string) $wpdb->get_var( $wpdb->prepare( 'SELECT file FROM %i WHERE id = %d', table( 'order_files' ), (int) Files::store( $oid, Files::RECEIPT, $png, 'r.png', false ) ) ) );
	$wpdb->insert( table( 'tickets' ), [ 'user_id' => $uid, 'reference_code' => 'TK-P', 'department' => 'sales', 'subject' => 'تیکت خصوصی', 'status' => 'open', 'created_at' => $t, 'updated_at' => $t ] );
	$tid = (int) $wpdb->insert_id;
	$wpdb->insert( table( 'ticket_messages' ), [ 'ticket_id' => $tid, 'sender_id' => $uid, 'sender_type' => 'user', 'message' => 'متن تیکت خصوصی', 'created_at' => $t ] );
	return [ $oid, $tid, $path ];
};
$hp_count = static fn( string $t, int $uid ): int => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE user_id = %d', table( $t ), $uid ) );
$priv_user = (int) wp_create_user( 'hp_priv', wp_generate_password(), 'hp_priv@hodima.test' );
[ $p_order, $p_ticket, $p_path ] = $hp_priv( $priv_user );
$export = Privacy::export( 'hp_priv@hodima.test', 1 );
$flat   = wp_json_encode( $export['data'], JSON_UNESCAPED_UNICODE );
hodima_pt( 'خروجی: سفارش، گفتگو، فیش و تیکت', [ str_contains( $flat, 'hodima-panel-order-' . $p_order ), str_contains( $flat, 'پیام خصوصی آزمون' ), str_contains( $flat, 'hodima_panel_file=' ), str_contains( $flat, 'متن تیکت خصوصی' ), $export['done'] ], [ true, true, true, true, true ] );
hodima_pt( 'خروجی ایمیل ناشناس خالی', Privacy::export( 'nobody@hodima.test', 1 ), [ 'data' => [], 'done' => true ] );
update_option( 'woocommerce_erasure_request_removes_order_data', 'no' );
$erase = Privacy::erase( 'hp_priv@hodima.test', 1 );
hodima_pt( 'حذف (تنظیم ووکامرس خاموش): تیکت حذف، سفارش مدرک مالی ماند', [ $hp_count( 'tickets', $priv_user ), (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE ticket_id = %d', table( 'ticket_messages' ), $p_ticket ) ), $hp_count( 'orders', $priv_user ), $erase['items_removed'], $erase['items_retained'] ], [ 0, 0, 1, true, true ] );
update_option( 'woocommerce_erasure_request_removes_order_data', 'yes' );
$erase = Privacy::erase( 'hp_priv@hodima.test', 1 );
hodima_pt( 'حذف (تنظیم روشن): سفارش، گفتگو و فایل فیش هم حذف', [ $hp_count( 'orders', $priv_user ), $hp_count( 'chats', $priv_user ), file_exists( $p_path ), $erase['items_removed'] ], [ 0, 0, false, true ] );
delete_option( 'woocommerce_erasure_request_removes_order_data' );
require_once ABSPATH . 'wp-admin/includes/user.php';
[ , , $p_path ] = $hp_priv( $priv_user );
wp_delete_user( $priv_user, $user_b );
hodima_pt( 'حذف حساب با سپردن محتوا: سفارش و تیکت به کاربر دیگر رسید', [ $hp_count( 'orders', $priv_user ), $hp_count( 'orders', $user_b ) > 0, $hp_count( 'tickets', $user_b ) > 0, file_exists( $p_path ) ], [ 0, true, true, true ] );
$priv_user = wp_create_user( 'hp_priv2', wp_generate_password(), 'hp_priv2@hodima.test' );
$priv_user = is_int( $priv_user ) ? $priv_user : 0;
update_user_meta( $priv_user, 'billing_first_name', 'نرگس' );
update_user_meta( $priv_user, 'billing_last_name', 'کاظمی' );
update_user_meta( $priv_user, 'mobile', '09126660001' );
[ $p_order2, , $p_path ] = $hp_priv( $priv_user );
if ( $priv_user > 1 ) {
	wp_delete_user( $priv_user );
}
$p_note = (string) $wpdb->get_var( $wpdb->prepare( "SELECT m.message FROM %i m INNER JOIN %i c ON c.id = m.chat_id WHERE c.order_id = %d AND m.sender_type = 'system' ORDER BY m.id DESC LIMIT 1", table( 'chat_messages' ), table( 'chats' ), $p_order2 ) );
hodima_pt( 'حذف حساب (بی سپردن): سفارش، گفتگو و فایل فیش می‌ماند؛ تیکت حذف', [ $priv_user > 1, $hp_count( 'orders', $priv_user ), $hp_count( 'chats', $priv_user ), $hp_count( 'tickets', $priv_user ), file_exists( $p_path ) ], [ true, 1, 1, 0, true ] );
hodima_pt( 'حذف حساب: نام و موبایل مشتری در گفتگوی سفارش ثبت شد', [ str_contains( $p_note, 'نرگس کاظمی' ), str_contains( $p_note, '09126660001' ), str_contains( $p_note, 'hp_priv2@hodima.test' ) ], [ true, true, true ] );
\Hodima\Panel\Orders::delete( $p_order2 );

// پاک‌سازی
require_once ABSPATH . 'wp-admin/includes/user.php';
foreach ( $hp_users as $id ) {
	wp_delete_user( $id );
}
foreach ( [ $product, $carton, $draft ] as $pid ) {
	wp_delete_post( $pid, true );
}
foreach ( [ $png, $pdf, $fake, $big ] as $tmpfile ) {
	wp_delete_file( $tmpfile );
}
$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE user_id IN (%d, %d)', table( 'tickets' ), $user_a, $user_b ) );

echo "\n" . ( $failed ? "✘ {$failed} آزمون رد شد ({$passed} قبول)" : "✔ همه {$passed} آزمون قبول" ) . "\n";
exit( $failed ? 1 : 0 );
