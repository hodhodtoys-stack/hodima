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
// ویرایش: نوع فرم دیگر نوع سفارش را عوض نمی‌کند؛ بعد از بررسی مدیر قفل است
$_POST = $_REQUEST = [ 'action' => 'save_preinvoice', '_wpnonce' => wp_create_nonce( 'user_panel_action' ), 'order_id' => (string) $order_b, 'products' => [ (string) $product ], 'qty' => [ $product => '1' ] ];
hodima_pt_run( [ \Hodima\Panel\Orders::class, 'handle_form' ] );
hodima_pt( 'ویرایش با فرم پیش‌فاکتور نوع پیش‌خرید را نگه داشت', $wpdb->get_var( $wpdb->prepare( 'SELECT type FROM %i WHERE id = %d', table( 'orders' ), $order_b ) ), 'prebuy' );
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
hodima_pt( 'نام و ایمیل حساب هم به‌روز شد و پروفایل کامل است', [ $fu->display_name, $fu->user_email, $fu->first_name, Profile::is_complete( $fu ) ], [ 'علی رضایی مقدم', 'ali@hodima.test', 'علی', true ] );
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
wp_delete_user( $fresh );

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
[ $html, $err ] = hodima_pt_render( '/user-panel/?section=prebuy&edit_order=' . $order_a, $user_a );
hodima_pt( 'فرم ویرایش سفارش در حال بررسی: پیام قفل، بی فرم', [ str_contains( $html, 'قابل ویرایش نیست' ), str_contains( $html, 'name="products[]"' ), $err ], [ true, false, '' ] );
[ $html ] = hodima_pt_render( '/?hodima_panel_file=' . $fid, $user_a );
hodima_pt( 'نشانی امن: صاحب سفارش خود فایل را می‌گیرد', str_starts_with( $html, "\x89PNG" ), true );
[ $html ] = hodima_pt_render( '/?hodima_panel_file=' . $fid, $user_b );
hodima_pt( 'نشانی امن: مشتری دیگر فایل را نمی‌گیرد', [ str_starts_with( $html, "\x89PNG" ), str_contains( $html, 'اجازه دیدنش را ندارید' ) ], [ false, true ] );
[ $html, $err ] = hodima_pt_render( '/?hodima_panel_file=' . $fid );
hodima_pt( 'نشانی امن: مهمان ← ریدایرکت به پنل', [ str_starts_with( $html, "\x89PNG" ), str_contains( $err, 'REDIRECT -> ' . Page::url() ) ], [ false, true ] );
hodima_pt( 'باز کردن تیکت پیش از منو خوانده شد', (int) $wpdb->get_var( $wpdb->prepare( 'SELECT user_unread FROM %i WHERE id = %d', table( 'tickets' ), $ticket->id ) ), 0 );
$wpdb->update( table( 'orders' ), [ 'status' => 'completed' ], [ 'id' => $order_a ] );
[ $html ] = hodima_pt_render( '/user-panel/?section=orders', $user_a );
$wpdb->update( table( 'orders' ), [ 'status' => 'receipt_uploaded' ], [ 'id' => $order_a ] );
hodima_pt( 'سفارش‌ها (تکمیل‌شده): فیش‌ها هنوز دیده می‌شوند، با نشانی امن، و اسکریپت گفتگو', [ str_contains( $html, 'hodima_panel_file=' . $fid ), str_contains( $html, 'hodima-panel-private' ), str_contains( $html, 'js/chat.js' ) ], [ true, false, true ] );

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
hodima_pt( 'جزئیات سفارش: جمع اقلام همان مبلغ مشتری', [ $stop, str_contains( $out, number_format_i18n( 37500 ) . ' تومان' ), str_contains( $out, 'hodima_panel_file=' . $fid ) ], [ '', true, true ] );
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
foreach ( [ $product, $carton, $draft ] as $pid ) {
	wp_delete_post( $pid, true );
}
foreach ( [ $png, $pdf, $fake, $big ] as $tmpfile ) {
	wp_delete_file( $tmpfile );
}
$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE user_id IN (%d, %d)', table( 'tickets' ), $user_a, $user_b ) );

echo "\n" . ( $failed ? "✘ {$failed} آزمون رد شد ({$passed} قبول)" : "✔ همه {$passed} آزمون قبول" ) . "\n";
exit( $failed ? 1 : 0 );
