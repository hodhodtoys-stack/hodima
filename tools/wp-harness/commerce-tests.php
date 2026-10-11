<?php
/**
 * آزمون خودکار ماژول‌های Commerce روی وردپرس ابزار تست.
 *   HARNESS=1 php tools/wp-harness/commerce-tests.php
 * خروجی: هر آزمون ✔/✘؛ کد خروج ۱ اگر یکی رد شود. ci-check.sh هم اجرا می‌کند.
 *
 * تاریخ شمسی (core/time-jalali): نمایش شمسی بماند ولی قالب‌های ماشینی که
 * ووکامرس در فرم‌های پیشخوان به کار می‌برد میلادی بمانند — باگ قبلی تاریخ
 * سفارش را هنگام ذخیره ۱۹۷۰ می‌کرد (HODIMA-AUDIT.md بخش ۹۹).
 */

declare(strict_types=1);

$_SERVER['HTTP_HOST'] = $_SERVER['SERVER_NAME'] = 'hodima.test';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['HTTPS']       = 'on';
require rtrim( (string) ( getenv( 'HODIMA_WP' ) ?: '/tmp/hodima-harness/wp' ), '/' ) . '/wp-load.php';

$failed = 0;
$passed = 0;

/** یک آزمون: مقدار واقعی === مقدار مورد انتظار. */
function hodima_ct( string $name, mixed $actual, mixed $expected ): void {
	global $failed, $passed;
	if ( $actual === $expected ) {
		++$passed;
		echo "  ✔ {$name}\n";
		return;
	}
	++$failed;
	echo "  ✘ {$name}\n      انتظار: " . var_export( $expected, true ) . "\n      واقعی:  " . var_export( $actual, true ) . "\n";
}

if ( ! class_exists( 'Hodima\Core\Time_Jalali\Modules\Jalali_Date' ) ) {
	echo "✘ ماژول تاریخ شمسی لود نشده است\n";
	exit( 1 );
}

update_option( 'timezone_string', 'Asia/Tehran' );
$ts = 1760175000; // 2025-10-11 09:30 UTC = 13:00 تهران

echo "=== تاریخ شمسی: قالب ماشینی میلادی می‌ماند (فرم‌های پیشخوان ووکامرس)\n";
hodima_ct( "date_i18n( 'Y-m-d' ) تاریخ سفارش/حراج", date_i18n( 'Y-m-d', $ts ), '2025-10-11' );
hodima_ct( "date_i18n( 'H' ) ساعت سفارش", date_i18n( 'H', $ts ), '09' );
hodima_ct( "date_i18n( 'i' ) دقیقه سفارش", date_i18n( 'i', $ts ), '30' );
hodima_ct( "date_i18n( 's' ) ثانیه سفارش", date_i18n( 's', $ts ), '00' );
hodima_ct( "wp_date( 'Y-m-d H:i:s' )", wp_date( 'Y-m-d H:i:s', $ts ), '2025-10-11 13:00:00' );
hodima_ct( "wp_date( 'Ymd' )", wp_date( 'Ymd', $ts ), '20251011' );
hodima_ct( "wp_date( 'c' )", wp_date( 'c', $ts ), '2025-10-11T13:00:00+03:30' );
// همان محاسبه ذخیره سفارش ووکامرس (WC_Meta_Box_Order_Data::save)
$posted = date_i18n( 'Y-m-d', $ts ) . ' ' . (int) date_i18n( 'H', $ts ) . ':' . (int) date_i18n( 'i', $ts ) . ':' . (int) date_i18n( 's', $ts );
hodima_ct( 'ذخیره فرم سفارش تاریخ را نگه می‌دارد', gmdate( 'Y-m-d H:i:s', (int) strtotime( $posted ) ), '2025-10-11 09:30:00' );

$post_id = wp_insert_post( [ 'post_title' => 'jalali-test', 'post_status' => 'publish', 'post_date' => '2025-10-11 13:00:00' ] );
hodima_ct( "get_the_time( 'U' ) عدد است", is_int( get_the_time( 'U', $post_id ) ), true );
hodima_ct( "get_the_date( 'Y-m-d' )", get_the_date( 'Y-m-d', $post_id ), '2025-10-11' );

echo "=== تاریخ شمسی: نمایش شمسی با رقم فارسی می‌ماند\n";
hodima_ct( "wp_date( 'j F Y' )", wp_date( 'j F Y', $ts ), '۱۹ مهر ۱۴۰۴' );
hodima_ct( "wp_date( 'l j F Y' )", wp_date( 'l j F Y', $ts ), 'شنبه ۱۹ مهر ۱۴۰۴' );
hodima_ct( "wp_date( 'Y/m/d H:i' )", wp_date( 'Y/m/d H:i', $ts ), '۱۴۰۴/۰۷/۱۹ ۱۳:۰۰' );
hodima_ct( "wp_date( 'Y' ) سال عنوان‌ها", wp_date( 'Y', $ts ), '۱۴۰۴' );
hodima_ct( "wp_date( 'F' )", wp_date( 'F', $ts ), 'مهر' );
hodima_ct( "date_i18n( 'Y/m/d' )", date_i18n( 'Y/m/d', $ts ), '۱۴۰۴/۰۷/۱۹' );
hodima_ct( "get_the_date( 'j F Y' )", get_the_date( 'j F Y', $post_id ), '۱۹ مهر ۱۴۰۴' );
wp_delete_post( (int) $post_id, true );

echo "=== تاریخ شمسی: نوروز و کبیسه (با زمان نجومی اعتدال مقایسه شده)\n";
foreach ( [
	'2024-03-20' => '۱۴۰۳/۰۱/۰۱',
	'2025-03-20' => '۱۴۰۳/۱۲/۳۰',
	'2025-03-21' => '۱۴۰۴/۰۱/۰۱',
	'2026-03-20' => '۱۴۰۴/۱۲/۲۹',
	'2026-03-21' => '۱۴۰۵/۰۱/۰۱',
	'2029-03-20' => '۱۴۰۸/۰۱/۰۱',
	'2030-03-20' => '۱۴۰۸/۱۲/۳۰',
] as $gregorian => $jalali ) {
	hodima_ct( "{$gregorian} ← {$jalali}", wp_date( 'Y/m/d', (int) strtotime( $gregorian . ' 12:00:00 UTC' ) ), $jalali );
}

echo "\n" . ( $failed ? "✘ {$failed} آزمون رد شد ({$passed} قبول)" : "✔ همه {$passed} آزمون Commerce قبول" ) . "\n";
exit( $failed ? 1 : 0 );
