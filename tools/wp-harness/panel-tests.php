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
	update_user_meta( $id, 'up_profile_completed', '1' );
	$hp_users[] = (int) $id;
}
[ $user_a, $user_b ] = $hp_users;
$product = (int) wp_insert_post( [ 'post_type' => 'product', 'post_status' => 'publish', 'post_title' => 'گیره آزمون پنل' ] );
update_post_meta( $product, '_price', '1250' );
update_post_meta( $product, '_up_unit_capacity', '12' );
update_post_meta( $product, '_up_sale_unit', 'کیلو' );
update_post_meta( $product, '_prebuy_enabled', 'yes' );

echo "== ثبت سفارش و اقلام (قیمت و ظرفیت لحظه ثبت، تعداد اعشاری)\n";
$now = \Hodima\Panel\now();
$wpdb->insert( table( 'orders' ), [ 'user_id' => $user_a, 'type' => 'prebuy', 'status' => 'pending', 'created_at' => $now, 'updated_at' => $now ] );
$order_a = (int) $wpdb->insert_id;
$total   = \Hodima\Panel\Legacy\save_order_items( $order_a, [ (string) $product, (string) $product, '999999' ], [ $product => '2.5' ] );
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
$stop  = hodima_pt_run( '\Hodima\Panel\Legacy\handle_order_form' );
$order_b = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE user_id = %d ORDER BY id DESC LIMIT 1', table( 'orders' ), $user_b ) );
hodima_pt( 'سفارش ثبت و به فهرست سفارش‌ها رفت', [ str_contains( $stop, 'section=orders' ), $order_b > 0 ], [ true, true ] );
hodima_pt( 'مبلغ کل سفارش ذخیره شد', (float) $wpdb->get_var( $wpdb->prepare( 'SELECT total_price FROM %i WHERE id = %d', table( 'orders' ), $order_b ) ), 45000.0 );
$_POST = $_REQUEST = [ 'action' => 'save_prebuy', '_wpnonce' => wp_create_nonce( 'user_panel_action' ), 'order_id' => (string) $order_a, 'products' => [ (string) $product ] ];
hodima_pt( 'ویرایش سفارش مشتری دیگر رد شد', str_starts_with( hodima_pt_run( '\Hodima\Panel\Legacy\handle_order_form' ), 'die:' ), true );
\Hodima\Panel\Legacy\delete_order( $order_b );
$_POST = $_REQUEST = [];

echo "== گفتگوی سفارش (شمارنده خوانده‌نشده)\n";
wp_set_current_user( $user_a );
\Hodima\Panel\Legacy\add_chat_message( $order_a, $user_a, 'user', 'سلام' );
\Hodima\Panel\Legacy\add_chat_message( $order_a, $user_a, 'system', 'فیش آپلود شد' );
$chat = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE order_id = %d', table( 'chats' ), $order_a ) );
hodima_pt( 'پیام مشتری و سیستمی برای مدیر نخوانده (پیام فیش واقعا ثبت شد)', [ (int) $chat->admin_unread, (int) $chat->user_unread ], [ 2, 0 ] );
wp_set_current_user( 1 );
\Hodima\Panel\Legacy\add_chat_message( $order_a, $user_a, 'admin', 'پاسخ' );
\Hodima\Panel\Legacy\add_chat_message( $order_a, $user_a, 'system', 'پیش‌فاکتور صادر شد', 'user' );
$chat = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE order_id = %d', table( 'chats' ), $order_a ) );
hodima_pt( 'پیام مدیر و سیستمی‌برای‌مشتری برای مشتری نخوانده', (int) $chat->user_unread, 2 );
hodima_pt( 'آخرین پیام', [ $chat->last_message_preview, $chat->last_message_sender ], [ 'پیش‌فاکتور صادر شد', 'system' ] );
hodima_pt( 'چهار پیام ذخیره شد', (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE chat_id = %d', table( 'chat_messages' ), $chat->id ) ), 4 );

echo "== فایل‌های سفارش\n";
$up   = wp_upload_dir();
$path = $up['path'] . '/hp-test-receipt.png';
file_put_contents( $path, 'x' );
$fid = Files::add( $order_a, Files::RECEIPT, [ 'file' => $path, 'url' => $up['url'] . '/hp-test-receipt.png', 'type' => 'image/png' ], 'receipt.png' );
hodima_pt( 'فیش ثبت شد', $fid > 0, true );
hodima_pt( 'شمارش فیش', Files::count( $order_a, Files::RECEIPT ), 1 );
$rows = Files::for_order( $order_a );
hodima_pt( 'آدرس فایل از مسیر نسبی', $rows[0]->url ?? '', $up['url'] . '/hp-test-receipt.png' );
hodima_pt( 'فایل بیرون uploads پذیرفته نمی‌شود', Files::add( $order_a, Files::RECEIPT, [ 'file' => '/etc/passwd' ], 'x' ), 0 );

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

echo "== قفل پروفایل پیش از چاپ صفحه\n";
delete_user_meta( $user_b, 'up_profile_completed' );
wp_set_current_user( $user_b );
$stop = hodima_pt_run( static fn() => do_action( 'hodima_panel_request', 'orders' ) );
hodima_pt( 'بدون پروفایل کامل ← ریدایرکت به پروفایل', str_contains( $stop, 'redirect:' ) && str_contains( $stop, 'section=profile' ), true );
hodima_pt( 'بخش پروفایل باز است', hodima_pt_run( static fn() => do_action( 'hodima_panel_request', 'profile' ) ), '' );
update_user_meta( $user_b, 'up_profile_completed', '1' );

echo "== تیکت پشتیبانی\n";
wp_set_current_user( $user_a );
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST                     = $_REQUEST = [ 'support_action' => 'create_ticket', '_wpnonce_create_ticket' => wp_create_nonce( 'create_ticket_nonce' ), 'subject' => 'سؤال', 'message' => 'متن \\"نقل\\"', 'priority' => 'urgent!', 'department' => 'finance' ];
$stop                      = hodima_pt_run( static fn() => do_action( 'hodima_panel_request', 'support' ) );
$ticket                    = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE user_id = %d ORDER BY id DESC LIMIT 1', table( 'tickets' ), $user_a ) );
hodima_pt( 'تیکت ثبت و ریدایرکت شد', str_contains( $stop, 'redirect:' ) && null !== $ticket, true );
hodima_pt( 'اولویت نامجاز ← متوسط؛ دپارتمان مجاز ماند', [ $ticket->priority ?? '', $ticket->department ?? '' ], [ 'normal', 'finance' ] );
hodima_pt( 'کد پیگیری', $ticket->reference_code ?? '', sprintf( 'TKT-%06d', (int) ( $ticket->id ?? 0 ) ) );
hodima_pt( 'متن بدون \\ اضافه', $wpdb->get_var( $wpdb->prepare( 'SELECT message FROM %i WHERE ticket_id = %d', table( 'ticket_messages' ), $ticket->id ) ), 'متن "نقل"' );
$_SERVER['REQUEST_METHOD'] = 'GET';
$_POST                     = $_REQUEST = [];

echo "== رندر واقعی پنل (بدون هشدار PHP)\n";
$wpdb->update( table( 'tickets' ), [ 'user_unread' => 1 ], [ 'id' => $ticket->id ] );
[ $html, $err ] = hodima_pt_render( '/user-panel/' );
hodima_pt( 'مهمان: فرم ورود', str_contains( $html, 'hodima_auth_nonce' ) && str_contains( $html, '</html>' ), true );
hodima_pt( 'مهمان: noindex', (bool) preg_match( "/<meta name='robots' content='[^']*noindex/", $html ), true );
hodima_pt( 'مهمان: فقط CSS ورود', str_contains( $html, 'css/login.css' ) && ! str_contains( $html, 'css/panel.css' ), true );
hodima_pt( 'مهمان: بدون خطا', $err, '' );
$sections = [
	'dashboard'  => 'stats-grid',
	'orders'     => 'user-orders-table',
	'prebuy'     => 'گیره آزمون پنل',
	'preinvoice' => 'فعلا محصولی',
	'support'    => 'ts-app',
	'profile'    => 'profileForm',
];
foreach ( $sections as $section => $needle ) {
	[ $html, $err ] = hodima_pt_render( '/user-panel/?section=' . $section . ( 'support' === $section ? '&thread_id=' . $ticket->id : '' ), $user_a );
	hodima_pt( "مشتری: بخش {$section} کامل و بدون خطا", [ str_contains( $html, $needle ), str_contains( $html, '</html>' ), $err ], [ true, true, '' ] );
}
hodima_pt( 'باز کردن تیکت پیش از منو خوانده شد', (int) $wpdb->get_var( $wpdb->prepare( 'SELECT user_unread FROM %i WHERE id = %d', table( 'tickets' ), $ticket->id ) ), 0 );
[ $html ] = hodima_pt_render( '/user-panel/?section=orders', $user_a );
hodima_pt( 'سفارش‌ها: فیش و اسکریپت گفتگو', str_contains( $html, 'hp-test-receipt.png' ) === false && str_contains( $html, 'js/chat.js' ), true );

echo "== پیشخوان\n";
require_once ABSPATH . 'wp-admin/includes/admin.php';
require_once HODIMA_PANEL_DIR . '/legacy/admin/menu.php';
require_once HODIMA_PANEL_DIR . '/legacy/admin/support.php';
wp_set_current_user( 1 );
set_current_screen( 'toplevel_page_hodima-panel-orders' );
$_GET = [ 'page' => 'hodima-panel-orders' ];
$stop = hodima_pt_run( '\Hodima\Panel\Legacy\Admin\render_orders_page' );
hodima_pt( 'فهرست مشتری‌ها', [ $stop, str_contains( $GLOBALS['hodima_pt_last_output'], 'hp_a' ) ], [ '', true ] );
$_GET = [ 'page' => 'hodima-panel-orders', 'user_id' => (string) $user_a ];
$stop = hodima_pt_run( '\Hodima\Panel\Legacy\Admin\render_orders_page' );
hodima_pt( 'سفارش‌های یک مشتری', [ $stop, str_contains( $GLOBALS['hodima_pt_last_output'], '#' . $order_a ) ], [ '', true ] );
$_GET = [ 'page' => 'hodima-panel-orders', 'action' => 'view', 'order_id' => (string) $order_a ];
$wpdb->update( table( 'chats' ), [ 'admin_unread' => 3 ], [ 'order_id' => $order_a ] );
$stop = hodima_pt_run( '\Hodima\Panel\Legacy\Admin\render_orders_page' );
$out  = $GLOBALS['hodima_pt_last_output'];
hodima_pt( 'جزئیات سفارش: جمع اقلام همان مبلغ مشتری', [ $stop, str_contains( $out, number_format_i18n( 37500 ) . ' تومان' ), str_contains( $out, 'hp-test-receipt.png' ) ], [ '', true, true ] );
hodima_pt( 'باز کردن سفارش فقط همان گفتگو را خوانده می‌کند', (int) $wpdb->get_var( $wpdb->prepare( 'SELECT admin_unread FROM %i WHERE order_id = %d', table( 'chats' ), $order_a ) ), 0 );
$_GET = [ 'page' => 'hodima-panel-support' ];
$stop = hodima_pt_run( '\Hodima\Panel\Legacy\Admin\render_support_page' );
hodima_pt( 'فهرست تیکت‌ها', [ $stop, str_contains( $GLOBALS['hodima_pt_last_output'], 'TKT-' ) ], [ '', true ] );
$_GET = [ 'page' => 'hodima-panel-support', 'action' => 'view_thread', 'thread_id' => (string) $ticket->id ];
$stop = hodima_pt_run( '\Hodima\Panel\Legacy\Admin\render_support_page' );
hodima_pt( 'یک تیکت', [ $stop, str_contains( $GLOBALS['hodima_pt_last_output'], 'متن &quot;نقل&quot;' ) ], [ '', true ] );

echo "== حذف گروهی فقط با nonce\n";
require_once HODIMA_PANEL_DIR . '/legacy/admin/lists.php';
$_GET     = [ 'page' => 'hodima-panel-orders', 'user_id' => (string) $user_a ];
$_REQUEST = $_POST = [ 'action' => 'delete', 'order_ids' => [ (string) $order_a ] ];
$stop     = hodima_pt_run( static function () use ( $user_a ): void {
	( new \Hodima\Panel\Legacy\Admin\Orders_List( $user_a ) )->prepare_items();
} );
hodima_pt( 'بدون nonce رد شد', str_starts_with( $stop, 'die:' ), true );
hodima_pt( 'سفارش ماند', (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE id = %d', table( 'orders' ), $order_a ) ), 1 );
$_REQUEST['_wpnonce'] = wp_create_nonce( 'bulk-hodima_panel_orders' );
$stop                 = hodima_pt_run( static function () use ( $user_a ): void {
	( new \Hodima\Panel\Legacy\Admin\Orders_List( $user_a ) )->prepare_items();
} );
hodima_pt( 'با nonce حذف شد', $stop, '' );
$left = [];
foreach ( [ 'orders' => 'id', 'order_items' => 'order_id', 'order_files' => 'order_id', 'chats' => 'order_id' ] as $t => $col ) {
	$left[ $t ] = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE %i = %d', table( $t ), $col, $order_a ) );
}
hodima_pt( 'حذف کامل: سفارش، اقلام، فایل، گفتگو', $left, [ 'orders' => 0, 'order_items' => 0, 'order_files' => 0, 'chats' => 0 ] );
hodima_pt( 'پیام‌های گفتگو هم حذف شد', (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE chat_id = %d', table( 'chat_messages' ), $chat->id ) ), 0 );
hodima_pt( 'خود فایل فیش حذف شد', file_exists( $path ), false );
$_GET = $_POST = $_REQUEST = [];

// پاک‌سازی
require_once ABSPATH . 'wp-admin/includes/user.php';
foreach ( $hp_users as $id ) {
	wp_delete_user( $id );
}
wp_delete_post( $product, true );
$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE user_id IN (%d, %d)', table( 'tickets' ), $user_a, $user_b ) );

echo "\n" . ( $failed ? "✘ {$failed} آزمون رد شد ({$passed} قبول)" : "✔ همه {$passed} آزمون قبول" ) . "\n";
exit( $failed ? 1 : 0 );
