<?php
/**
 * آزمون خودکار ماژول اعلان‌ها (Hodima Media) روی وردپرس ابزار تست.
 *   HARNESS=1 php tools/wp-harness/notification-tests.php
 * خروجی: هر آزمون ✔/✘؛ کد خروج ۱ اگر یکی رد شود. ci-check.sh هم اجرا می‌کند.
 *
 * باگ‌های رفع‌شده را قفل می‌کند: پاک شدن کش با هر تغییر وضعیت (ویرایش سریع،
 * انتشار زمان‌بندی‌شده)، ذخیره «بستن خودکار» و «نقش کاربر»، موقعیت‌های بی‌ظاهر،
 * ستون وضعیت فهرست، حالت شفاف (PNG) و لینک روی کل عکس (بخش ۲۰۹)؛ و نسخه ۴
 * (بخش ۲۱۰): <dialog>، srcset با شناسه پیوست (و پیدا کردن پیوست اعلان قدیمی از
 * آدرس)، کش قالب قدیمی، ذخیره فقط فیلدهای فرستاده‌شده و محدوده عددها؛ و نسخه ۵
 * (بخش ۲۱۱): آمار (شمارنده اتمی، فقط منتشرشده، محدودیت نرخ، صفر کردن)، کپی،
 * دسته محصول، فیلدهای تازه؛ و نسخه ۶ (بخش ۲۱۲): آمار روزانه (جدول، افزایش اتمی،
 * روزهای خالی صفر، بازه، صفر کردن و حذف) و صفحه «آمار»؛ و نسخه ۷ (بخش ۲۱۳): سربرگ
 * فهرست و کلید روشن/خاموش فهرست (nonce، دسترسی، پاک شدن کش).
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

/** یک آزمون: مقدار واقعی === مقدار مورد انتظار. */
function hodima_nt( string $name, mixed $actual, mixed $expected ): void {
	global $failed, $passed;
	if ( $actual === $expected ) {
		++$passed;
		echo "  ✔ {$name}\n";
		return;
	}
	++$failed;
	echo "  ✘ {$name}\n      انتظار: " . var_export( $expected, true ) . "\n      واقعی:  " . var_export( $actual, true ) . "\n";
}

if ( ! class_exists( 'Hodima_Notification_Front' ) || ! function_exists( 'hodima_save_notification_data' ) ) {
	echo "✘ ماژول اعلان‌ها لود نشده است\n";
	exit( 1 );
}

wp_set_current_user( 1 );
if ( function_exists( 'hodima_notification_daily_install' ) ) {
	delete_option( 'hodima_notif_daily_db' ); // جدول روزانه (در سایت admin_init، در admin-ajax هم)
	hodima_notification_daily_install();
}

/** یک اعلان منتشرشده با متاهای داده‌شده. */
function hodima_nt_create( array $meta = [], string $status = 'publish', string $title = 'اعلان آزمون' ): int {
	$args = [
		'post_type'   => 'hd_notification',
		'post_status' => $status,
		'post_title'  => $title,
	];
	if ( 'future' === $status ) {
		$args['post_date'] = gmdate( 'Y-m-d H:i:s', time() + DAY_IN_SECONDS );
	}
	$id = (int) wp_insert_post( $args );
	foreach ( $meta as $key => $value ) {
		update_post_meta( $id, '_' . $key, $value );
	}
	return $id;
}

/** خروجی HTML اعلان‌های صفحه فعلی (بدون هوک‌های دیگر wp_footer). */
function hodima_nt_render(): string {
	delete_transient( 'hd_active_notifications' );
	$front = ( new ReflectionClass( Hodima_Notification_Front::class ) )->newInstanceWithoutConstructor();
	$front->prepare();
	ob_start();
	$front->render_all();
	return (string) ob_get_clean();
}

// پاک‌سازی اجرای قبلی
foreach ( get_posts( [ 'post_type' => 'hd_notification', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ] ) as $old ) {
	wp_delete_post( (int) $old, true );
}

echo "=== کش فهرست اعلان‌های فعال\n";
$id = hodima_nt_create( [ 'hd_notif_bg_image' => 'https://hodima.test/a.png' ] );
set_transient( 'hd_active_notifications', [ 'stale' ], HOUR_IN_SECONDS );
wp_update_post( [ 'ID' => $id, 'post_status' => 'draft' ] ); // مثل ویرایش سریع (بدون nonce فرم)
hodima_nt( 'پیش‌نویس کردن از فهرست کش را پاک می‌کند', get_transient( 'hd_active_notifications' ), false );

$future = hodima_nt_create( [ 'hd_notif_bg_image' => 'https://hodima.test/a.png' ], 'future' );
set_transient( 'hd_active_notifications', [ 'stale' ], HOUR_IN_SECONDS );
wp_publish_post( $future );
hodima_nt( 'انتشار زمان‌بندی‌شده کش را پاک می‌کند', get_transient( 'hd_active_notifications' ), false );

set_transient( 'hd_active_notifications', [ 'stale' ], HOUR_IN_SECONDS );
wp_trash_post( $future );
hodima_nt( 'زباله‌دان کش را پاک می‌کند', get_transient( 'hd_active_notifications' ), false );

echo "=== ذخیره فرم\n";
$id    = hodima_nt_create();
$_POST = [
	'hodima_notification_meta_nonce' => wp_create_nonce( 'hodima_save_notification_data' ),
	'hd_notif_is_active'             => '1',
	'hd_notif_priority'              => '3',
	'hd_notif_bg_image'              => 'https://hodima.test/a.png',
	'hd_notif_bg_transparent'        => '1',
	'hd_notif_auto_close_time'       => '12',
	'hd_notif_target_role'           => 'customer',
	'hd_notif_position'              => 'top_bar',
	'hd_notif_end_date'              => '2026-02-30',
];
hodima_save_notification_data( $id );
$_POST = [];
hodima_nt( 'عرض بیش از حد به ۲۰۰۰ محدود', ( static function () use ( $id ): string {
	$_POST = [ 'hodima_notification_meta_nonce' => wp_create_nonce( 'hodima_save_notification_data' ), 'hd_notif_width' => '99999', 'hd_notif_anim_in' => 'explode' ];
	hodima_save_notification_data( $id );
	$_POST = [];
	return get_post_meta( $id, '_hd_notif_width', true ) . '|' . get_post_meta( $id, '_hd_notif_anim_in', true );
} )(), '2000|zoom_in' );
hodima_nt( 'فیلدی که در فرم نیامده دست نمی‌خورد (بستن خودکار)', get_post_meta( $id, '_hd_notif_auto_close_time', true ), '12' );
hodima_nt( 'کلید روشن/خاموش نیامده = خاموش', get_post_meta( $id, '_hd_notif_bg_transparent', true ), '0' );
update_post_meta( $id, '_hd_notif_bg_transparent', '1' );
hodima_nt( 'بستن خودکار ذخیره می‌شود (قبلا همیشه ۰)', get_post_meta( $id, '_hd_notif_auto_close_time', true ), '12' );
hodima_nt( 'نقش کاربر ذخیره می‌شود (قبلا همیشه all)', get_post_meta( $id, '_hd_notif_target_role', true ), 'customer' );
hodima_nt( 'موقعیت بی‌ظاهر (top_bar) پذیرفته نمی‌شود', get_post_meta( $id, '_hd_notif_position', true ), 'center' );
hodima_nt( 'شفافیت ذخیره می‌شود', get_post_meta( $id, '_hd_notif_bg_transparent', true ), '1' );
hodima_nt( 'اولویت دو رقمی', get_post_meta( $id, '_hd_notif_priority', true ), '03' );
hodima_nt( 'تاریخ نامعتبر خالی می‌شود', get_post_meta( $id, '_hd_notif_end_date', true ), '' );

echo "=== ستون وضعیت فهرست\n";
$today     = current_time( 'Y-m-d' );
$yesterday = wp_date( 'Y-m-d', time() - DAY_IN_SECONDS );
$tomorrow  = wp_date( 'Y-m-d', time() + DAY_IN_SECONDS );
$img       = [ 'hd_notif_bg_image' => 'https://hodima.test/a.jpg' ];
$state     = static fn( int $post ): string => hodima_notification_display_state( $post )[1];
hodima_nt( 'فعال', $state( hodima_nt_create( $img ) ), 'فعال' );
hodima_nt( 'تمام‌شده (قبلا «فعال»)', $state( hodima_nt_create( $img + [ 'hd_notif_end_date' => $yesterday ] ) ), 'تمام‌شده' );
hodima_nt( 'آخرین روز هنوز فعال', $state( hodima_nt_create( $img + [ 'hd_notif_end_date' => $today ] ) ), 'فعال' );
hodima_nt( 'هنوز شروع نشده', $state( hodima_nt_create( $img + [ 'hd_notif_start_date' => $tomorrow ] ) ), 'هنوز شروع نشده' );
hodima_nt( 'بدون عکس', $state( hodima_nt_create() ), 'بدون عکس' );
hodima_nt( 'خاموش', $state( hodima_nt_create( $img + [ 'hd_notif_is_active' => '0' ] ) ), 'غیرفعال' );
hodima_nt( 'پیش‌نویس', $state( hodima_nt_create( $img, 'draft' ) ), 'منتشر نشده' );
hodima_nt( 'زمان‌بندی‌شده', $state( hodima_nt_create( $img, 'future' ) ), 'زمان‌بندی‌شده' );

echo "=== خروجی سایت\n";
foreach ( get_posts( [ 'post_type' => 'hd_notification', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ] ) as $old ) {
	wp_delete_post( (int) $old, true );
}
$png    = hodima_nt_create( [ 'hd_notif_bg_image' => 'https://hodima.test/a.png', 'hd_notif_bg_transparent' => '1', 'hd_notif_main_link' => 'https://hodima.test/sale/' ], 'publish', 'پی‌ان‌جی' );
$legacy = hodima_nt_create( [ 'hd_notif_bg_image' => 'https://hodima.test/b.jpg', 'hd_notif_position' => 'middle_left', 'hd_notif_priority' => '02' ], 'publish', 'قدیمی' );
$ended  = hodima_nt_create( [ 'hd_notif_bg_image' => 'https://hodima.test/c.jpg', 'hd_notif_end_date' => $yesterday ], 'publish', 'تمام' );
// پیوست واقعی (بدون فایل): متادیتا با اندازه‌ها برای srcset
$att = (int) wp_insert_post( [ 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_mime_type' => 'image/png', 'post_title' => 'promo', 'guid' => 'https://hodima.test/wp-content/uploads/2026/10/promo.png' ] );
update_post_meta( $att, '_wp_attached_file', '2026/10/promo.png' );
update_post_meta(
	$att,
	'_wp_attachment_metadata',
	[
		'width'  => 1200,
		'height' => 900,
		'file'   => '2026/10/promo.png',
		'sizes'  => [
			'medium'       => [ 'file' => 'promo-300x225.png', 'width' => 300, 'height' => 225, 'mime-type' => 'image/png' ],
			'medium_large' => [ 'file' => 'promo-768x576.png', 'width' => 768, 'height' => 576, 'mime-type' => 'image/png' ],
		],
	]
);
$upload  = wp_get_upload_dir()['baseurl'] . '/2026/10/promo.png';
$with_id = hodima_nt_create( [ 'hd_notif_bg_image' => $upload, 'hd_notif_bg_image_id' => (string) $att, 'hd_notif_width' => '500' ], 'publish', 'با شناسه' );
$by_url  = hodima_nt_create( [ 'hd_notif_bg_image' => $upload, 'hd_notif_position' => 'center', 'hd_notif_mobile_mode' => 'corner', 'hd_notif_skip_search' => '1' ], 'publish', 'فقط آدرس' );
$html    = hodima_nt_render();
$popup  = static function ( int $post ) use ( $html ): string {
	return preg_match( '/<dialog id="hd-notification-' . $post . '"[^>]*>/', $html, $m ) ? $m[0] : '';
};
hodima_nt( 'حالت شفاف کلاس hd-notif-transparent دارد', str_contains( $popup( $png ), 'hd-notif-transparent' ), true );
hodima_nt( 'حالت شفاف رنگ کادر ندارد', str_contains( $popup( $png ), '--hd-notif-bg' ), false );
hodima_nt( 'اعلان معمولی رنگ کادر دارد', str_contains( $popup( $legacy ), '--hd-notif-bg:#ffffff' ), true );
hodima_nt( 'هر اعلان یک <dialog> است', substr_count( $html, '<dialog id="hd-notification-' ), 4 );
hodima_nt( 'با شناسه پیوست: srcset و sizes', 1 === preg_match( '/id="hd-notification-' . $with_id . '".*?data-srcset="[^"]*768w[^"]*" data-sizes="\(max-width: 768px\) 90vw, 500px"/s', $html ), true );
hodima_nt( 'با شناسه پیوست: ابعاد واقعی عکس', 1 === preg_match( '/id="hd-notification-' . $with_id . '".*?width="1200" height="900"/s', $html ), true );
hodima_nt( 'اعلان قدیمی فقط با آدرس: پیوست پیدا می‌شود (srcset)', 1 === preg_match( '/id="hd-notification-' . $by_url . '".*?data-srcset=/s', $html ), true );
hodima_nt( 'حالت موبایل و «تازه از جستجو» در data', str_contains( $popup( $by_url ), 'data-mobile-mode="corner"' ) && str_contains( $popup( $by_url ), 'data-skip-search="1"' ), true );
hodima_nt( 'پیش‌فرض حالت موبایل اعلان قدیمی: پنجره وسط', str_contains( $popup( $legacy ), 'data-mobile-mode="modal"' ), true );
hodima_nt( 'عکس تا نمایش دانلود نمی‌شود (بدون src)', 1 === preg_match( '/<img\s+src=/', $html ), false );
hodima_nt( 'کش ساخته شد با قالب تازه', isset( get_transient( 'hd_active_notifications' )[0]['meta'] ), true );
set_transient( 'hd_active_notifications', [ [ 'hd_notif_is_active' => '1', '_post_id' => 1 ] ], HOUR_IN_SECONDS ); // قالب کش تا 2.0.2
$front = ( new ReflectionClass( Hodima_Notification_Front::class ) )->newInstanceWithoutConstructor();
$front->prepare();
ob_start();
$front->render_all();
hodima_nt( 'کش قالب قدیمی دوباره ساخته می‌شود (بدون خطا)', substr_count( (string) ob_get_clean(), '<dialog' ), 4 );
hodima_nt( 'اعلان معمولی کلاس شفاف ندارد', str_contains( $popup( $legacy ), 'hd-notif-transparent' ), false );
hodima_nt( 'موقعیت قدیمی middle_left ← گوشه چپ', str_contains( $popup( $legacy ), 'hd-pos-bottom_left' ), true );
hodima_nt( 'لینک مقصد دور عکس', 1 === preg_match( '#<a class="hd-notif-half-link" href="https://hodima.test/sale/">\s*<img#', $html ), true );
hodima_nt( 'اعلان تمام‌شده چاپ نمی‌شود', '' === $popup( $ended ), true );
hodima_nt( 'ترتیب اولویت (۰۱ پیش از ۰۲)', strpos( $html, 'hd-notification-' . $png ) < strpos( $html, 'hd-notification-' . $legacy ), true );

foreach ( get_posts( [ 'post_type' => 'hd_notification', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ] ) as $old ) {
	wp_delete_post( (int) $old, true );
}
wp_delete_post( $att, true );
delete_transient( 'hd_active_notifications' );

echo "=== آمار، کپی و دسته محصول\n";
/** فراخوانی هندلر ajax بدون پایان اسکریپت: خروجی JSON. */
final class Hodima_Nt_Stop extends Exception {}
add_filter( 'wp_doing_ajax', '__return_true' );
add_filter( 'wp_die_ajax_handler', static fn() => static function (): void { throw new Hodima_Nt_Stop(); } );
// پیش از فیلتر خود ابزار تست (mu-plugins) که ریدایرکت را چاپ و لغو می‌کند
add_filter( 'wp_redirect', static function ( string $to ): string { throw new Hodima_Nt_Stop( $to ); }, 0 );
$ajax = static function ( array $post ): string {
	$_POST = $post;
	ob_start();
	try {
		hodima_notification_track_event();
	} catch ( Hodima_Nt_Stop ) {
		// پایان هندلر
	}
	$_POST = [];
	return (string) ob_get_clean();
};
// سهمیه محدودیت نرخ اجرای قبلی (یک دقیقه) پاک شود
delete_transient( function_exists( 'hodima_rate_limit_key' ) ? hodima_rate_limit_key( 'hd_notif_rl_' ) : 'hd_notif_rl_' . md5( '203.0.113.10' ) );
$sid = hodima_nt_create( [ 'hd_notif_bg_image' => 'https://hodima.test/a.jpg' ] );
$ajax( [ 'id' => (string) $sid, 'event' => 'view' ] );
$ajax( [ 'id' => (string) $sid, 'event' => 'view' ] );
$ajax( [ 'id' => (string) $sid, 'event' => 'click' ] );
$out = $ajax( [ 'id' => (string) $sid, 'event' => 'close' ] );
$st  = hodima_notification_stats( $sid );
hodima_nt( 'شمارنده‌ها (۲ نمایش، ۱ کلیک، ۱ بستن)', [ $st['views'], $st['clicks'], $st['closes'] ], [ 2, 1, 1 ] );
hodima_nt( 'نرخ کلیک', $st['ctr'], 50.0 );
hodima_nt( 'تاریخ شروع آمار ثبت می‌شود', $st['since'], current_time( 'Y-m-d' ) );
$today = current_time( 'Y-m-d' );
$daily = hodima_notification_daily( [ $sid ], wp_date( 'Y-m-d', time() - 2 * DAY_IN_SECONDS ), $today );
hodima_nt( 'آمار روزانه: امروز ۲ نمایش، ۱ کلیک، ۱ بستن', $daily[ $today ], [ 'views' => 2, 'clicks' => 1, 'closes' => 1 ] );
hodima_nt( 'آمار روزانه: ۳ روز با روزهای خالی صفر', [ count( $daily ), array_sum( array_column( $daily, 'views' ) ) ], [ 3, 2 ] );
hodima_nt( 'جمع بازه هر نوتیفیکیشن', hodima_notification_range_totals( $today, $today )[ $sid ] ?? [], [ 'views' => 2, 'clicks' => 1, 'closes' => 1 ] );
hodima_nt( 'پاسخ JSON ثبت شد', str_contains( $out, '"recorded":true' ), true );
$draft = hodima_nt_create( [], 'draft' );
$ajax( [ 'id' => (string) $draft, 'event' => 'view' ] );
hodima_nt( 'پیش‌نویس شمرده نمی‌شود', hodima_notification_stats( $draft )['views'], 0 );
$ajax( [ 'id' => (string) $sid, 'event' => 'hack' ] );
$ajax( [ 'id' => '1', 'event' => 'view' ] ); // نوشته معمولی
hodima_nt( 'رویداد نامعتبر/نوشته دیگر شمرده نمی‌شود', [ hodima_notification_stats( $sid )['views'], (int) get_post_meta( 1, '_hd_notif_stats_views', true ) ], [ 2, 0 ] );
for ( $i = 0; $i < 70; $i++ ) {
	$ajax( [ 'id' => (string) $sid, 'event' => 'view' ] );
}
delete_transient( function_exists( 'hodima_rate_limit_key' ) ? hodima_rate_limit_key( 'hd_notif_rl_' ) : 'hd_notif_rl_' . md5( '203.0.113.10' ) );
// ۴ رویداد معتبر بالا هم از همان سهمیه ۶۰ تایی بودند: ۲ + (۶۰ − ۴) = ۵۸
hodima_nt( 'محدودیت نرخ: حداکثر ۶۰ رویداد در دقیقه از یک IP', hodima_notification_stats( $sid )['views'], 58 );
$_POST = [ 'hodima_notification_meta_nonce' => wp_create_nonce( 'hodima_save_notification_data' ), 'hd_notif_stats_reset' => '1' ];
hodima_save_notification_data( $sid );
$_POST = [];
hodima_nt( 'ذخیره فرم دیگر آمار را صفر نمی‌کند (صفر کردن در تب آمار)', hodima_notification_stats( $sid )['views'], 58 );
// صفحه آمار: خروجی کامل (سیستم طراحی Core فقط در پیشخوان لود می‌شود)
if ( ! function_exists( 'hodima_admin_page_open' ) ) {
	require_once WP_PLUGIN_DIR . '/hodima-core/includes/admin-ui.php';
}
$_GET = [ 'page' => 'hodima-notification-stats', 'range' => '7' ];
ob_start();
hodima_notification_stats_render();
$page = (string) ob_get_clean();
$_GET = [];
hodima_nt( 'صفحه آمار: کاشی‌ها، دو نمودار، جدول', [ substr_count( $page, 'class="hd-stat"' ), substr_count( $page, 'class="hdn-chart"' ), str_contains( $page, 'hodima_notif_stats_reset' ) ], [ 4, 2, true ] );
hodima_nt( 'صفحه آمار: نمودار ۷ ستون روز', substr_count( $page, 'class="hdn-chart__col"' ), 14 );
hodima_nt( 'صفحه آمار: تب «آمار» فعال', 1 === preg_match( '/aria-current="page"[^>]*>|<a class="[^"]*hd-tabs__item[^"]*" href="[^"]*hodima-notification-stats"[^>]*aria-current="page"/', $page ), true );
// صفر کردن از صفحه آمار (کل و روزانه)
$_POST = [ 'post' => (string) $sid, '_wpnonce' => wp_create_nonce( 'hodima_notif_stats_reset_' . $sid ) ];
$_REQUEST = $_POST;
try {
	hodima_notification_stats_reset_action();
} catch ( Hodima_Nt_Stop ) {
	// ریدایرکت
}
$_POST = $_REQUEST = [];
hodima_nt( 'صفر کردن از تب آمار: کل و روزانه', [ hodima_notification_stats( $sid )['views'], hodima_notification_daily( [ $sid ], $today, $today )[ $today ]['views'] ], [ 0, 0 ] );
$gone = hodima_nt_create( [ 'hd_notif_bg_image' => 'https://hodima.test/a.jpg' ] );
hodima_notification_stat_increment( $gone, 'view' );
wp_delete_post( $gone, true );
hodima_nt( 'حذف نوتیفیکیشن آمار روزانه‌اش را پاک می‌کند', hodima_notification_range_totals( $today, $today )[ $gone ] ?? 'none', 'none' );

update_post_meta( $sid, '_hd_notif_cooldown_days', '7' );
$_GET = [ 'post' => (string) $sid, '_wpnonce' => wp_create_nonce( 'hodima_notif_duplicate_' . $sid ) ];
$_REQUEST = $_GET;
try {
	hodima_notification_duplicate();
	$redirect = '';
} catch ( Hodima_Nt_Stop $e ) {
	$redirect = $e->getMessage();
}
$_GET = $_REQUEST = [];
$copies = get_posts( [ 'post_type' => 'hd_notification', 'post_status' => 'draft', 'title' => get_the_title( $sid ) . ' (کپی)', 'fields' => 'ids' ] );
$copy   = (int) ( $copies[0] ?? 0 );
hodima_nt( 'کپی: پیش‌نویس با تنظیمات', [ $copy > 0, get_post_meta( $copy, '_hd_notif_cooldown_days', true ), get_post_meta( $copy, '_hd_notif_bg_image', true ) ], [ true, '7', 'https://hodima.test/a.jpg' ] );
hodima_nt( 'کپی: آمار کپی نمی‌شود', get_post_meta( $copy, '_hd_notif_stats_views', true ), '' );
hodima_nt( 'کپی: به صفحه ویرایش کپی می‌رود', str_contains( $redirect, 'post=' . $copy ), true );

hodima_nt( 'شناسه‌ها: آرایه چک‌باکس با خالی و تکراری', hodima_notification_sanitize( hodima_notification_fields()['hd_notif_target_product_cat'], [ '', '5', '5', 'x', '12' ] ), '5,12' );

// پیشخوان: سربرگ و تب‌های فهرست، کلید روشن/خاموش فهرست
require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
require_once ABSPATH . 'wp-admin/includes/screen.php';
set_current_screen( 'edit-hd_notification' );
ob_start();
hodima_notification_admin_header();
$head = (string) ob_get_clean();
hodima_nt( 'سربرگ فهرست: تب‌ها، «نوتیفیکیشن‌ها» فعال، بدون wp-header-end دوم', [ substr_count( $head, 'hd-tabs__item' ), 1 === preg_match( '/edit\.php\?post_type=hd_notification"[^>]*aria-current="page"/', $head ), str_contains( $head, 'wp-header-end' ) ], [ 3, true, false ] );
$tid = hodima_nt_create( [ 'hd_notif_bg_image' => 'https://hodima.test/a.jpg' ] );
set_transient( 'hd_active_notifications', [ 'stale' ], HOUR_IN_SECONDS );
$toggle = static function ( array $post ): string {
	$_POST = $post;
	$_REQUEST = $post;
	ob_start();
	try {
		hodima_notification_toggle();
	} catch ( Hodima_Nt_Stop ) {
		// پایان هندلر
	}
	$_POST = $_REQUEST = [];
	return (string) ob_get_clean();
};
$res = $toggle( [ 'id' => (string) $tid, 'active' => '0', 'nonce' => wp_create_nonce( 'hodima_notif_toggle' ) ] );
hodima_nt( 'کلید فهرست: خاموش، کش پاک، پاسخ «غیرفعال»', [ get_post_meta( $tid, '_hd_notif_is_active', true ), get_transient( 'hd_active_notifications' ), str_contains( $res, '"label":"\u063a\u06cc\u0631\u0641\u0639\u0627\u0644"' ) || str_contains( $res, '"label":"غیرفعال"' ) ], [ '0', false, true ] );
$toggle( [ 'id' => (string) $tid, 'active' => '1', 'nonce' => 'bad' ] );
hodima_nt( 'کلید فهرست: nonce نادرست کاری نمی‌کند', get_post_meta( $tid, '_hd_notif_is_active', true ), '0' );
wp_set_current_user( 0 );
$toggle( [ 'id' => (string) $tid, 'active' => '1', 'nonce' => wp_create_nonce( 'hodima_notif_toggle' ) ] );
wp_set_current_user( 1 );
hodima_nt( 'کلید فهرست: بدون دسترسی کاری نمی‌کند', get_post_meta( $tid, '_hd_notif_is_active', true ), '0' );
$GLOBALS['current_screen'] = null;

// دسته محصول: صفحه دسته و زیردسته و محصول
if ( ! taxonomy_exists( 'product_cat' ) ) {
	register_post_type( 'product', [ 'public' => true ] );
	register_taxonomy( 'product_cat', 'product', [ 'public' => true, 'hierarchical' => true, 'rewrite' => false ] );
}
$parent  = (int) ( term_exists( 'nt-hair', 'product_cat' )['term_id'] ?? wp_insert_term( 'nt-hair', 'product_cat' )['term_id'] );
$child   = (int) ( term_exists( 'nt-clips', 'product_cat' )['term_id'] ?? wp_insert_term( 'nt-clips', 'product_cat', [ 'parent' => $parent ] )['term_id'] );
$other   = (int) ( term_exists( 'nt-bags', 'product_cat' )['term_id'] ?? wp_insert_term( 'nt-bags', 'product_cat' )['term_id'] );
$product = (int) wp_insert_post( [ 'post_type' => 'product', 'post_status' => 'publish', 'post_title' => 'گل‌سر آزمون' ] );
wp_set_object_terms( $product, [ $child ], 'product_cat' );
$cat_notif = hodima_nt_create( [ 'hd_notif_bg_image' => 'https://hodima.test/a.jpg', 'hd_notif_target_product_cat' => (string) $parent, 'hd_notif_visitor' => 'returning', 'hd_notif_min_pageviews' => '3', 'hd_notif_cooldown_days' => '7' ], 'publish', 'دسته' );
$on = static function ( array $query ) use ( $cat_notif ): bool {
	$GLOBALS['wp_query']     = new WP_Query( $query );
	$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
	return str_contains( hodima_nt_render(), 'hd-notification-' . $cat_notif . '"' );
};
hodima_nt( 'دسته انتخاب‌شده: نمایش', $on( [ 'taxonomy' => 'product_cat', 'term' => 'nt-hair', 'tax_query' => [ [ 'taxonomy' => 'product_cat', 'terms' => $parent ] ] ] ), true );
hodima_nt( 'زیردسته: نمایش', $on( [ 'tax_query' => [ [ 'taxonomy' => 'product_cat', 'terms' => $child ] ], 'product_cat' => 'nt-clips' ] ), true );
hodima_nt( 'دسته دیگر: نه', $on( [ 'tax_query' => [ [ 'taxonomy' => 'product_cat', 'terms' => $other ] ], 'product_cat' => 'nt-bags' ] ), false );
hodima_nt( 'محصول زیردسته: نمایش', $on( [ 'p' => $product, 'post_type' => 'product' ] ), true );
hodima_nt( 'صفحه اصلی: نه', $on( [] ), false );
$on( [ 'p' => $product, 'post_type' => 'product' ] );
$html = hodima_nt_render();
hodima_nt( 'data بازدیدکننده/حداقل صفحه/روزهای مکث', str_contains( $html, 'data-visitor="returning" data-min-pv="3" data-cooldown="7"' ), true );
$GLOBALS['wp_query'] = $GLOBALS['wp_the_query'] = new WP_Query();
wp_delete_post( $product, true );
foreach ( [ $child, $parent, $other ] as $t ) {
	wp_delete_term( $t, 'product_cat' );
}
foreach ( get_posts( [ 'post_type' => 'hd_notification', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ] ) as $old ) {
	wp_delete_post( (int) $old, true );
}
delete_transient( 'hd_active_notifications' );

echo "\n" . ( $failed ? "✘ {$failed} آزمون رد شد، {$passed} قبول" : "✔ همه {$passed} آزمون اعلان‌ها قبول" ) . "\n";
exit( $failed ? 1 : 0 );
