<?php
/**
 * آزمون خودکار ماژول اعلان‌ها (Hodima Media) روی وردپرس ابزار تست.
 *   HARNESS=1 php tools/wp-harness/notification-tests.php
 * خروجی: هر آزمون ✔/✘؛ کد خروج ۱ اگر یکی رد شود. ci-check.sh هم اجرا می‌کند.
 *
 * باگ‌های رفع‌شده را قفل می‌کند: پاک شدن کش با هر تغییر وضعیت (ویرایش سریع،
 * انتشار زمان‌بندی‌شده)، ذخیره «بستن خودکار» و «نقش کاربر»، موقعیت‌های بی‌ظاهر،
 * ستون وضعیت فهرست، حالت شفاف (PNG) و لینک روی کل عکس.
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

if ( ! class_exists( 'HodimaNotificationFront' ) || ! function_exists( 'hodima_save_notification_data' ) ) {
	echo "✘ ماژول اعلان‌ها لود نشده است\n";
	exit( 1 );
}

wp_set_current_user( 1 );

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
	$front = ( new ReflectionClass( HodimaNotificationFront::class ) )->newInstanceWithoutConstructor();
	$front->prepareAndEnqueueAssets();
	ob_start();
	$front->renderNotificationsHTML();
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
$html   = hodima_nt_render();
$popup  = static function ( int $post ) use ( $html ): string {
	return preg_match( '/<div id="hd-notification-' . $post . '"[^>]*>/', $html, $m ) ? $m[0] : '';
};
hodima_nt( 'حالت شفاف کلاس hd-notif-transparent دارد', str_contains( $popup( $png ), 'hd-notif-transparent' ), true );
hodima_nt( 'حالت شفاف کادر/سایه درون‌خطی ندارد', str_contains( $popup( $png ), 'box-shadow' ), false );
hodima_nt( 'اعلان معمولی کلاس شفاف ندارد', str_contains( $popup( $legacy ), 'hd-notif-transparent' ), false );
hodima_nt( 'موقعیت قدیمی middle_left ← گوشه چپ', str_contains( $popup( $legacy ), 'hd-pos-bottom_left' ), true );
hodima_nt( 'لینک مقصد چاپ می‌شود', str_contains( $html, 'href="https://hodima.test/sale/" class="hd-notif-half-link"' ), true );
hodima_nt( 'اعلان تمام‌شده چاپ نمی‌شود', '' === $popup( $ended ), true );
hodima_nt( 'ترتیب اولویت (۰۱ پیش از ۰۲)', strpos( $html, 'hd-notification-' . $png ) < strpos( $html, 'hd-notification-' . $legacy ), true );

foreach ( get_posts( [ 'post_type' => 'hd_notification', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ] ) as $old ) {
	wp_delete_post( (int) $old, true );
}
delete_transient( 'hd_active_notifications' );

echo "\n" . ( $failed ? "✘ {$failed} آزمون رد شد، {$passed} قبول" : "✔ همه {$passed} آزمون اعلان‌ها قبول" ) . "\n";
exit( $failed ? 1 : 0 );
