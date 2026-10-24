<?php
/**
 * آزمون خودکار فرم لید تلفنی (Commerce، components/phone): شماره، توکن امضاشده و حداقل زمان،
 * محدودیت نرخ، تکراری، ثبت لید و تاریخچه، شکست ذخیره، خروجی CSV.
 *   HARNESS=1 php tools/wp-harness/phone-tests.php
 * خروجی: هر آزمون ✔/✘؛ کد خروج ۱ اگر یکی رد شود. هر اجرا لیدهای خودش را می‌سازد و پاک می‌کند. ci-check.sh هم اجرا می‌کند.
 * (HODIMA-AUDIT.md بخش ۲۲۸)
 */

declare(strict_types=1);

$_SERVER['HTTP_HOST'] = $_SERVER['SERVER_NAME'] = 'hodima.test';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['HTTPS']       = 'on';
$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
require rtrim( (string) ( getenv( 'HODIMA_WP' ) ?: '/tmp/hodima-harness/wp' ), '/' ) . '/wp-load.php';

if ( ! getenv( 'HARNESS' ) ) {
	fwrite( STDERR, "این آزمون داده می‌سازد و پاک می‌کند؛ فقط روی ابزار تست: HARNESS=1\n" );
	exit( 2 );
}
if ( ! function_exists( 'hodima_phone_process' ) ) {
	echo "✘ ماژول فرم تلفن لود نشده است\n";
	exit( 1 );
}

$failed = 0;
$passed = 0;

function hodima_ph( string $name, mixed $actual, mixed $expected ): void {
	global $failed, $passed;
	if ( $actual === $expected ) {
		++$passed;
		echo "  ✔ {$name}\n";
		return;
	}
	++$failed;
	echo "  ✘ {$name}\n      انتظار: " . var_export( $expected, true ) . "\n      واقعی:  " . var_export( $actual, true ) . "\n";
}

/** نشانه‌های موقت ضد اسپم (محدودیت نرخ، تکراری) را پاک می‌کند تا آزمون‌ها مستقل بمانند. */
function hodima_ph_reset(): void {
	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%transient%hodima_phone_%'" );
	wp_cache_flush();
}

/** یک ارسال ajax با توکن به سن مشخص (ثانیه). */
function hodima_ph_post( array $fields, int $age = 5 ): string {
	$time = (string) ( time() - $age );
	$mac  = hash_hmac( 'sha256', 'hodima_phone|' . $time, wp_salt( 'nonce' ) );
	$_SERVER['REQUEST_METHOD'] = 'POST';
	$_POST = $fields + [ 'security_token' => $time . '.' . $mac ];
	return hodima_phone_process( true );
}

$phone  = '09123456789';
$cleanup = static function (): void {
	foreach ( get_posts( [ 'post_type' => 'hodima_phone_lead', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ] ) as $id ) {
		wp_delete_post( (int) $id, true );
	}
	foreach ( get_posts( [ 'post_type' => 'hodima_phone_lead', 'post_status' => 'trash', 'posts_per_page' => -1, 'fields' => 'ids' ] ) as $id ) {
		wp_delete_post( (int) $id, true );
	}
};
$cleanup();
hodima_ph_reset();

echo "=== یکدست‌سازی و اعتبار شماره\n";
foreach ( [
	'موبایل لاتین'              => [ '09123456789', '09123456789' ],
	'رقم فارسی و خط تیره'       => [ '۰۹۱۲-۳۴۵-۶۷۸۹', '09123456789' ],
	'رقم عربی'                  => [ '٠٩١٢٣٤٥٦٧٨٩', '09123456789' ],
	'+98'                       => [ '+98 912 345 6789', '09123456789' ],
	'0098'                      => [ '00989123456789', '09123456789' ],
	'98 بدون صفر'               => [ '989123456789', '09123456789' ],
	'+98 با صفر اضافه'          => [ '+98 0912 345 6789', '09123456789' ],
	'بدون صفر اول'              => [ '9123456789', '09123456789' ],
	'ثابت تهران'                => [ '021-12345678', '02112345678' ],
	'ثابت با +98'               => [ '+98 21 1234 5678', '02112345678' ],
	'ثابت بدون صفر'             => [ '2112345678', '02112345678' ],
	'بی‌معنی ۱۰ رقمی با صفر'    => [ '0123456789', '' ],
	'خیلی کوتاه'                => [ '123', '' ],
	'خیلی بلند'                 => [ '091234567890', '' ],
	'کد شهر 09 ثابت نیست'       => [ '09212345678', '09212345678' ], // موبایل ۰۹۲ (رایتل) معتبر است
	'کد شهر صفر'                => [ '00212345678', '' ],
	'حروف'                      => [ 'abc', '' ],
	'خالی'                      => [ '', '' ],
] as $name => [ $in, $out ] ) {
	hodima_ph( $name, hodima_phone_normalize( $in ), $out );
}

echo "=== توکن امضاشده (جایگزین nonce)\n";
$token = hodima_phone_issue_token();
hodima_ph( 'توکن تازه: زودتر از حداقل زمان', hodima_phone_check_token( $token ), 'fast' );
$old = ( time() - 10 ) . '.' . hash_hmac( 'sha256', 'hodima_phone|' . ( time() - 10 ), wp_salt( 'nonce' ) );
hodima_ph( 'توکن ۱۰ ثانیه‌ای: معتبر', hodima_phone_check_token( $old ), 'ok' );
hodima_ph( 'امضای غلط', hodima_phone_check_token( ( time() - 10 ) . '.abc' ), 'bad' );
hodima_ph( 'خالی', hodima_phone_check_token( '' ), 'bad' );
hodima_ph( 'بدون نقطه', hodima_phone_check_token( 'xyz' ), 'bad' );
$ancient = ( time() - 13 * HOUR_IN_SECONDS ) . '.' . hash_hmac( 'sha256', 'hodima_phone|' . ( time() - 13 * HOUR_IN_SECONDS ), wp_salt( 'nonce' ) );
hodima_ph( 'بیش از ۱۲ ساعت: منقضی', hodima_phone_check_token( $ancient ), 'bad' );
$future = ( time() + 600 ) . '.' . hash_hmac( 'sha256', 'hodima_phone|' . ( time() + 600 ), wp_salt( 'nonce' ) );
hodima_ph( 'زمان آینده: رد', hodima_phone_check_token( $future ), 'bad' );

echo "=== ثبت لید (ajax)\n";
hodima_ph_reset();
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [ 'phone' => $phone ];
hodima_ph( 'بدون توکن: token', hodima_phone_process( true ), 'token' );
hodima_ph_reset();
hodima_ph( 'توکن خیلی تازه: wait', hodima_ph_post( [ 'phone' => $phone ], 0 ), 'wait' );
hodima_ph_reset();
hodima_ph( 'honeypot پر: spam', hodima_ph_post( [ 'phone' => $phone, 'website' => 'http://x' ] ), 'spam' );
hodima_ph_reset();
hodima_ph( 'honeypot آرایه: spam', hodima_ph_post( [ 'phone' => $phone, 'website' => [ 'x' ] ] ), 'spam' );
$cleanup();
hodima_ph_reset();
hodima_ph( 'شماره نامعتبر: bad', hodima_ph_post( [ 'phone' => '0123456789' ] ), 'bad' );
hodima_ph( 'غلط تایپی کسی را قفل نمی‌کند (شماره درست بلافاصله بعد)', hodima_ph_post( [ 'phone' => $phone, 'page_url' => home_url( '/hair/' ), 'page_title' => 'کلیپس' ] ), 'ok' );

$ids = hodima_phone_lead_ids( $phone );
hodima_ph( 'یک لید ساخته شد', count( $ids ), 1 );
$data = hodima_phone_lead_data( $ids[0] ?? 0 );
hodima_ph( 'اولین ثبت هم در تاریخچه است', count( $data['history'] ), 1 );
hodima_ph( 'شمارنده ۱ و آدرس صفحه', [ $data['count'], $data['last_url'], $data['last_title'] ], [ 1, home_url( '/hair/' ), 'کلیپس' ] );

echo "=== تکراری، نرخ و ثبت دوباره\n";
hodima_ph( 'همان شماره در یک دقیقه: ok بدون ثبت دوباره', hodima_ph_post( [ 'phone' => $phone ] ), 'ok' );
hodima_ph( 'شمارنده تغییر نکرد', hodima_phone_lead_data( $ids[0] )['count'], 1 );

delete_transient( 'hodima_phone_dup_' . wp_hash( $phone ) ); // پنجره تکراری گذشت؛ نرخ هنوز
hodima_ph( 'ارسال بعدی در ۱۵ ثانیه: wait', hodima_ph_post( [ 'phone' => $phone ] ), 'wait' );

hodima_ph_reset();
hodima_ph( 'بعد از پنجره نرخ: ok', hodima_ph_post( [ 'phone' => $phone, 'page_url' => 'https://evil.example/x', 'page_title' => 'دوم' ] ), 'ok' );
$data = hodima_phone_lead_data( $ids[0] );
hodima_ph( 'همان لید: شمارنده ۲ و تاریخچه ۲', [ $data['count'], count( $data['history'] ) ], [ 2, 2 ] );
hodima_ph( 'آدرس سایت دیگر پذیرفته نمی‌شود', $data['last_url'], '' );
hodima_ph( 'هنوز یک لید', count( hodima_phone_lead_ids( $phone ) ), 1 );

echo "=== سقف ساعتی\n";
add_filter( 'hodima_phone_hourly_limit', static fn(): int => 2 );
hodima_ph_reset();
$r = [];
foreach ( [ '09120000001', '09120000002', '09120000003' ] as $n ) {
	delete_transient( 'hodima_phone_rl_' . wp_hash( '203.0.113.9' ) );
	$r[] = hodima_ph_post( [ 'phone' => $n ] );
}
hodima_ph( 'دو ثبت اول ok، سوم wait', $r, [ 'ok', 'ok', 'wait' ] );
remove_all_filters( 'hodima_phone_hourly_limit' );
hodima_ph( 'ثبتِ ردشده با سقف، شماره را برای ثبت بعدی آزاد می‌گذارد', hodima_phone_lead_ids( '09120000003' ), [] );

echo "=== ذخیره ناموفق «موفق» گزارش نمی‌شود\n";
hodima_ph_reset();
add_filter( 'wp_insert_post_empty_content', '__return_true' ); // wp_insert_post با WP_Error شکست می‌خورد
hodima_ph( 'شکست ذخیره: err', hodima_ph_post( [ 'phone' => '09350000001' ] ), 'err' );
remove_filter( 'wp_insert_post_empty_content', '__return_true' );
hodima_ph( 'لیدی ساخته نشد', hodima_phone_lead_ids( '09350000001' ), [] );
hodima_ph( 'بعد از شکست همان شماره بلافاصله دوباره قابل ثبت است', (function (): string {
	delete_transient( 'hodima_phone_rl_' . wp_hash( '203.0.113.9' ) );
	return hodima_ph_post( [ 'phone' => '09350000001' ] );
})(), 'ok' );

echo "=== لید قدیمی بدون تاریخچه\n";
$cleanup();
hodima_ph_reset();
$legacy = wp_insert_post( [ 'post_type' => 'hodima_phone_lead', 'post_title' => '09351112233', 'post_status' => 'publish' ] );
update_post_meta( (int) $legacy, 'hodima_request_count', 2 );
update_post_meta( (int) $legacy, 'hodima_page_url', 'https://hodima.test/x/' );
update_post_meta( (int) $legacy, 'hodima_page_title', 'صفحه قدیمی' );
update_post_meta( (int) $legacy, 'hodima_submit_time', '2026-10-01 08:00' );
$d = hodima_phone_lead_data( (int) $legacy );
hodima_ph( 'متاهای قدیمی خوانده می‌شود', [ $d['count'], $d['last_title'], $d['last_time'] ], [ 2, 'صفحه قدیمی', '2026-10-01 08:00' ] );
hodima_ph( 'ثبت تازه روی لید قدیمی', hodima_ph_post( [ 'phone' => '09351112233', 'page_url' => home_url( '/' ), 'page_title' => 'خانه' ] ), 'ok' );
$d = hodima_phone_lead_data( (int) $legacy );
hodima_ph( 'شمارنده ۳ و آخرین ثبت قبلی در تاریخچه ماند', [ $d['count'], count( $d['history'] ), $d['history'][0]['title'] ?? '' ], [ 3, 2, 'صفحه قدیمی' ] );
hodima_ph( 'لید دیده‌شده دوباره «جدید» می‌شود', hodima_lead_state( (int) $legacy ), 'new' );

echo "=== ۱۰ ثبت آخر در تاریخچه\n";
for ( $i = 0; $i < 12; $i++ ) {
	hodima_ph_reset();
	hodima_ph_post( [ 'phone' => '09351112233', 'page_title' => "ثبت {$i}" ] );
}
$d = hodima_phone_lead_data( (int) $legacy );
hodima_ph( 'تاریخچه حداکثر ۱۰', count( $d['history'] ), 10 );
hodima_ph( 'آخرین ثبت در تاریخچه', $d['history'][9]['title'] ?? '', 'ثبت 11' );

echo "=== بدون جاوااسکریپت (admin-post)\n";
hodima_ph_reset();
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [ 'phone' => '09360000009', 'form_uid' => 'hodima-phone-1' ];
hodima_ph( 'بدون توکن هم ثبت می‌شود (توکن فقط ajax)', hodima_phone_process( false ), 'ok' );
hodima_ph_reset();
$_POST = [ 'phone' => '09360000010', 'website' => 'x' ];
hodima_ph( 'honeypot: spam', hodima_phone_process( false ), 'spam' );
hodima_ph( 'ورودی آرایه phone[]: bad نه خطای PHP', ( function (): string { $_POST = [ 'phone' => [ '1' ] ]; hodima_ph_reset(); return hodima_phone_process( false ); } )(), 'bad' );

echo "=== پیام بدون جاوااسکریپت در فرم\n";
$_GET = [ 'hodima_phone' => 'bad', 'form_id' => 'hodima-phone-1' ];
$html = hodima_phone_render_form(); // نمونه ۱ این فرایند
hodima_ph( 'پیام خطا و aria-invalid برای شماره نامعتبر', str_contains( $html, 'hodima-msg-error' ) && str_contains( $html, 'aria-invalid="true"' ), true );
$_GET = [ 'hodima_phone' => 'ok', 'form_id' => 'hodima-phone-2' ];
$html = hodima_phone_render_form();
hodima_ph( 'پیام موفق برای فرم خودش', str_contains( $html, 'hodima-msg-success' ), true );
$_GET = [ 'hodima_phone' => 'hack<script>', 'form_id' => 'hodima-phone-3' ];
hodima_ph( 'مقدار ناشناخته پیامی نمی‌سازد', str_contains( hodima_phone_render_form(), 'hodima-msg-' ), false );
$_GET = [];

echo "=== پیشخوان: کارها (دیده شد، زباله‌دان، بازگردانی، حذف همیشگی)\n";
$cleanup();
hodima_ph_reset();
$admin_id = 1;
wp_set_current_user( $admin_id );
$mk = static function ( string $phone, array $extra = [] ): int {
	$id = wp_insert_post( [ 'post_type' => 'hodima_phone_lead', 'post_title' => $phone, 'post_status' => 'publish' ] + $extra );
	update_post_meta( (int) $id, HODIMA_PHONE_META, [ 'count' => 2, 'last_title' => 'کلیپس مو', 'last_url' => 'https://hodima.test/hair/', 'last_time' => '2026-10-20 10:00', 'history' => [ [ 'url' => 'https://hodima.test/hair/', 'title' => 'کلیپس مو', 'time' => '2026-10-20 10:00' ] ] ] );
	return (int) $id;
};
$l1 = $mk( '09121110001' );
$l2 = $mk( '09121110002' );
$l3 = $mk( '09121110003' );
hodima_ph( 'سه لید جدید', hodima_phone_counts( true )['new'], 3 );
hodima_ph( 'seen: دو لید', hodima_phone_apply_op( 'seen', [ $l1, $l2 ] ), 2 );
hodima_ph( 'شمارنده‌ها: ۱ جدید، ۲ دیده‌شده', [ hodima_phone_counts()['new'], hodima_phone_counts()['seen'] ], [ 1, 2 ] );
hodima_ph( 'unseen', hodima_phone_apply_op( 'unseen', [ $l2 ] ), 1 );
hodima_ph( 'شناسه نامربوط (نوشته معمولی) نادیده', hodima_phone_apply_op( 'trash', [ (int) wp_insert_post( [ 'post_title' => 'x', 'post_status' => 'publish' ] ) ] ), 0 );
hodima_ph( 'trash', hodima_phone_apply_op( 'trash', [ $l3 ] ), 1 );
hodima_ph( 'زباله‌دان: ۱ و کل ۲', [ hodima_phone_counts()['trash'], hodima_phone_counts()['total'] ], [ 1, 2 ] );
hodima_ph( 'delete روی لید غیر زباله‌دان انجام نمی‌شود', hodima_phone_apply_op( 'delete', [ $l1 ] ), 0 );
hodima_ph( 'restore', hodima_phone_apply_op( 'restore', [ $l3 ] ), 1 );
hodima_ph( 'بازگردانده‌شده دوباره «منتشرشده» است', get_post_status( $l3 ), 'publish' );
hodima_phone_apply_op( 'trash', [ $l3 ] );
hodima_ph( 'حذف همیشگی از زباله‌دان', hodima_phone_apply_op( 'delete', [ $l3 ] ), 1 );
hodima_ph( 'لید دیگر نیست', get_post( $l3 ), null );
require_once ABSPATH . 'wp-admin/includes/user.php'; // wp_delete_user
$old_sub = get_user_by( 'login', 'phone-test-sub' );
if ( $old_sub instanceof WP_User ) {
	wp_delete_user( $old_sub->ID ); // بقایای اجرای نیمه‌کاره
}
$sub = wp_insert_user( [ 'user_login' => 'phone-test-sub', 'user_pass' => 'x', 'user_email' => 'phone-test-sub@example.com', 'role' => 'subscriber' ] );
wp_set_current_user( is_wp_error( $sub ) ? 0 : (int) $sub );
hodima_ph( 'کاربر بدون دسترسی: هیچ‌چیز تغییر نمی‌کند', hodima_phone_apply_op( 'trash', [ $l1 ] ), 0 );
wp_set_current_user( $admin_id );
if ( ! is_wp_error( $sub ) ) {
	wp_delete_user( (int) $sub );
}

echo "=== پیشخوان: صفحه فهرست و جزئیات\n";
if ( ! function_exists( 'hodima_admin_header' ) ) {
	require_once HODIMA_COMMERCE_DIR . '/inc/admin-ui-fallback.php'; // هدر پیشخوان فقط در is_admin() لود می‌شود
}
$render = static function ( callable $fn, mixed ...$args ): string {
	hodima_phone_counts( true ); // شمارنده‌ها در هر درخواست واقعی تازه‌اند؛ اینجا همه در یک پردازه‌اند
	ob_start();
	$fn( ...$args );
	return (string) ob_get_clean();
};
$_GET = [];
$html = $render( 'hodima_phone_render_list' );
hodima_ph( 'جدول و هر دو لید', str_contains( $html, 'hd-leads__table' ) && str_contains( $html, '0912 111 0001' ) && str_contains( $html, '0912 111 0002' ), true );
hodima_ph( 'لید جدید با نقطه و برچسب صفحه‌خوان', str_contains( $html, 'is-new' ) && str_contains( $html, '(جدید)' ), true );
hodima_ph( 'کنترل‌ها دسترس‌پذیرند (برچسب کپی، چک‌باکس)', str_contains( $html, 'aria-label="کپی شماره 09121110001"' ) && str_contains( $html, 'aria-label="انتخاب 09121110001"' ), true );
hodima_ph( 'بدون زباله‌دان وقتی خالی است', str_contains( $html, 'state=trash' ), false );
$_GET = [ 'state' => 'new' ];
$html = $render( 'hodima_phone_render_list' );
hodima_ph( 'نمای جدید: فقط لید دیده‌نشده', str_contains( $html, '0912 111 0002' ) && ! str_contains( $html, '0912 111 0001' ), true );
$_GET = [ 's' => '۰۹۱۲۱۱۱۰۰۰۱' ];
$html = $render( 'hodima_phone_render_list' );
hodima_ph( 'جستجو با ارقام فارسی', str_contains( $html, '0912 111 0001' ) && ! str_contains( $html, '0912 111 0002' ), true );
$_GET = [ 's' => '99999' ];
$html = $render( 'hodima_phone_render_list' );
hodima_ph( 'جستجوی بی‌نتیجه: حالت خالی', str_contains( $html, 'موردی پیدا نشد' ) && ! str_contains( $html, 'hd-leads__table' ), true );
$_GET = [];
hodima_phone_apply_op( 'trash', [ $l2 ] );
$_GET = [ 'state' => 'trash' ];
$html = $render( 'hodima_phone_render_list' );
hodima_ph( 'زباله‌دان: بازگردانی و حذف همیشگی با تأیید', str_contains( $html, 'value="restore:' . $l2 . '"' ) && str_contains( $html, 'data-hd-confirm' ), true );
hodima_phone_apply_op( 'restore', [ $l2 ] );
$_GET = [];
$many = [];
for ( $i = 0; $i < 25; $i++ ) {
	$many[] = $mk( sprintf( '0935%07d', $i ) );
}
$_GET = [ 'paged' => '2' ];
$html = $render( 'hodima_phone_render_list' );
hodima_ph( 'صفحه‌بندی: ۲۷ شماره ← صفحه ۲ فقط ۷ ردیف', substr_count( $html, 'data-hd-row' ), 7 );
hodima_ph( 'پیوند صفحه‌ها', str_contains( $html, 'page-numbers' ), true );
$_GET = [];
$empty_html = '';
foreach ( array_merge( $many, [ $l1, $l2 ] ) as $id ) {
	wp_delete_post( $id, true );
}
$empty_html = $render( 'hodima_phone_render_list' );
hodima_ph( 'بدون لید: توضیح فرم فوتر', str_contains( $empty_html, 'هنوز شماره‌ای ثبت نشده' ), true );

$d = $mk( '09126667788' );
hodima_ph( 'لید تازه جدید است', hodima_lead_state( $d ), 'new' );
$html = $render( 'hodima_phone_render_detail', $d );
hodima_ph( 'جزئیات: شماره، تاریخچه، خلاصه', str_contains( $html, '0912 666 7788' ) && str_contains( $html, 'hd-leads__timeline' ) && str_contains( $html, 'تعداد درخواست‌ها' ), true );
hodima_ph( 'جداساز چپ‌به‌راست دور شماره عنوان', str_contains( $html, "\u{2066}0912 666 7788\u{2069}" ), true );
hodima_ph( 'باز کردن جزئیات = دیده شد', hodima_lead_state( $d ), 'seen' );
wp_delete_post( $d, true );

echo "=== پیشخوان: منو و ریدایرکت پیشخوان قدیمی\n";
global $menu;
require_once ABSPATH . 'wp-admin/includes/plugin.php'; // add_menu_page
$menu = [];
do_action( 'admin_menu' );
$slugs = array_column( (array) $menu, 2 );
hodima_ph( 'منوی «شماره‌های تماس» ثبت شد', in_array( HODIMA_PHONE_PAGE, $slugs, true ), true );
hodima_ph( 'منوی خودکار پست‌تایپ برداشته شد', in_array( 'edit.php?post_type=hodima_phone_lead', $slugs, true ), false );
hodima_ph( 'نشانی صفحه', hodima_phone_page_url( [ 'lead' => 5 ] ), admin_url( 'admin.php?page=hodima-phone-leads&lead=5' ) );

echo "=== نگهداری خودکار\n";
delete_option( HODIMA_PHONE_SETTINGS );
hodima_ph( 'پیش‌فرض خاموش', hodima_phone_retention_months(), 0 );
hodima_ph( 'خاموش: چیزی پاک نمی‌شود', hodima_phone_retention_run(), 0 );
update_option( HODIMA_PHONE_SETTINGS, [ 'retention_months' => 999 ] );
hodima_ph( 'سقف ۱۲۰ ماه', hodima_phone_retention_months(), 120 );
update_option( HODIMA_PHONE_SETTINGS, [ 'retention_months' => -5 ] );
hodima_ph( 'منفی = خاموش', hodima_phone_retention_months(), 0 );
$old_date = wp_date( 'Y-m-d H:i:s', strtotime( '-14 months' ) );
$new_date = wp_date( 'Y-m-d H:i:s', strtotime( '-2 months' ) );
$old = $mk( '09130000001', [ 'post_date' => $old_date, 'post_date_gmt' => get_gmt_from_date( $old_date ) ] );
$fresh = $mk( '09130000002', [ 'post_date' => $new_date, 'post_date_gmt' => get_gmt_from_date( $new_date ) ] );
$oldtrash = $mk( '09130000003', [ 'post_date' => $old_date, 'post_date_gmt' => get_gmt_from_date( $old_date ) ] );
wp_trash_post( $oldtrash );
update_option( HODIMA_PHONE_SETTINGS, [ 'retention_months' => 12 ] );
hodima_phone_retention_schedule();
hodima_ph( 'روشن: رویداد شبانه زمان‌بندی شد', false !== wp_next_scheduled( HODIMA_PHONE_RETENTION_EVENT ), true );
hodima_ph( 'قدیمی‌تر از ۱۲ ماه (حتی زباله‌دان) پاک شد', hodima_phone_retention_run(), 2 );
hodima_ph( 'لید قدیمی رفت و تازه ماند', [ get_post( $old ), get_post( $oldtrash ), get_post( $fresh ) instanceof WP_Post ], [ null, null, true ] );
update_option( HODIMA_PHONE_SETTINGS, [ 'retention_months' => 0 ] );
hodima_phone_retention_schedule();
hodima_ph( 'خاموش: رویداد برداشته شد', wp_next_scheduled( HODIMA_PHONE_RETENTION_EVENT ), false );
delete_option( HODIMA_PHONE_SETTINGS );
wp_delete_post( $fresh, true );
wp_set_current_user( 0 );

echo "=== CSV\n";
$cleanup();
hodima_ph_reset();
$a = wp_insert_post( [ 'post_type' => 'hodima_phone_lead', 'post_title' => '09121110000', 'post_status' => 'publish' ] );
update_post_meta( (int) $a, HODIMA_PHONE_META, [ 'count' => 4, 'last_title' => '=HYPERLINK("x")', 'last_url' => 'https://hodima.test/', 'last_time' => '2026-10-20 10:00', 'history' => [] ] );
update_post_meta( (int) $a, 'hodima_seen', 1 );
wp_insert_post( [ 'post_type' => 'hodima_phone_lead', 'post_title' => '09122220000', 'post_status' => 'publish' ] );

/** تابع CSV با exit تمام می‌شود؛ پس در پردازه جدا اجرا و خروجی‌اش گرفته می‌شود. */
function hodima_ph_csv( bool $valid_nonce ): string {
	$nonce = $valid_nonce ? "wp_create_nonce( 'hodima_export_csv' )" : "'bad'";
	$code  = "<?php\n"
		. "\$_SERVER['HTTP_HOST'] = \$_SERVER['SERVER_NAME'] = 'hodima.test'; \$_SERVER['REQUEST_URI'] = '/'; \$_SERVER['HTTPS'] = 'on';\n"
		. "require rtrim( (string) ( getenv( 'HODIMA_WP' ) ?: '/tmp/hodima-harness/wp' ), '/' ) . '/wp-load.php';\n"
		. "wp_set_current_user( 1 );\n"
		. "\$_GET = [ 'export_hodima_phones' => '1', '_wpnonce' => {$nonce} ];\n"
		. "hodima_export_phones_csv();\n";
	$file = tempnam( sys_get_temp_dir(), 'hdph' ) ?: '/tmp/hdph.php';
	file_put_contents( $file, $code );
	$out = (string) shell_exec( 'HARNESS=1 php ' . escapeshellarg( $file ) . ' 2>&1' );
	unlink( $file );
	return $out;
}

$csv   = hodima_ph_csv( true );
$lines = array_values( array_filter( explode( "\n", trim( $csv ) ) ) );
hodima_ph( 'BOM و سرستون', str_starts_with( $csv, "\xEF\xBB\xBF" ) && str_contains( $lines[0] ?? '', 'شماره' ), true );
hodima_ph( 'سرستون و دو ردیف لید', count( $lines ), 3 );
// fputcsv مقدار را نقل‌قول می‌کند: "=""0912…"""؛ اکسل آن را ="0912…" می‌خواند و صفر اول می‌ماند
hodima_ph( 'شماره با ="…" (صفر اول در اکسل می‌ماند)', str_contains( $csv, '"=""09121110000"""' ) && str_contains( $csv, '"=""09122220000"""' ), true );
hodima_ph( 'فرمول در عنوان بی‌اثر می‌شود', str_contains( $csv, "'=HYPERLINK" ), true );
hodima_ph( 'وضعیت مشاهده‌شده و جدید', str_contains( $csv, 'مشاهده شده' ) && str_contains( $csv, 'جدید' ), true );
hodima_ph( 'بدون هشدار PHP در خروجی', str_contains( $csv, 'Deprecated' ) || str_contains( $csv, 'Warning' ) || str_contains( $csv, 'Fatal' ), false );

$out = hodima_ph_csv( false );
hodima_ph( 'nonce غلط: پیام خطا، نه فایل', str_contains( $out, 'اجازه دانلود' ) && ! str_contains( $out, 'مشاهده شده' ), true );

$cleanup();
hodima_ph_reset();
wp_set_current_user( 0 );

echo "\n" . ( $failed ? "✘ {$failed} آزمون رد شد ({$passed} قبول)" : "✔ همه {$passed} آزمون فرم تلفن قبول" ) . "\n";
exit( $failed ? 1 : 0 );
