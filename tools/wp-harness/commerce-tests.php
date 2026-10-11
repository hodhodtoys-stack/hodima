<?php
/**
 * آزمون خودکار ماژول‌های Commerce روی وردپرس ابزار تست.
 *   HARNESS=1 php tools/wp-harness/commerce-tests.php
 * خروجی: هر آزمون ✔/✘؛ کد خروج ۱ اگر یکی رد شود. ci-check.sh هم اجرا می‌کند.
 *
 * تاریخ شمسی (core/time-jalali): نمایش شمسی بماند ولی قالب‌های ماشینی که
 * ووکامرس در فرم‌های پیشخوان به کار می‌برد میلادی بمانند — باگ قبلی تاریخ
 * سفارش را هنگام ذخیره ۱۹۷۰ می‌کرد (HODIMA-AUDIT.md بخش ۹۹).
 * کتابخانه شمسی Core، یکدست‌سازی موبایل/کد پستی/کد ملی، و داده و فیلد شهرها (بخش ۱۰۰).
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

echo "=== کتابخانه شمسی Hodima Core (includes/jalali.php)\n";
hodima_ct( 'شمسی ← میلادی ۱۴۰۴/۰۷/۱۹', hodima_jalali_to_gregorian( 1404, 7, 19 ), [ 2025, 10, 11 ] );
hodima_ct( 'میلادی ← شمسی ۲۰۲۵-۰۳-۲۰', hodima_jalali_from_gregorian( 2025, 3, 20 ), [ 1403, 12, 30 ] );
hodima_ct( '۱۴۰۳ کبیسه، ۱۴۰۴ نه', [ hodima_jalali_is_leap( 1403 ), hodima_jalali_is_leap( 1404 ) ], [ true, false ] );
hodima_ct( 'رفت و برگشت ۱۳۰۰ تا ۱۵۰۰ (هر ۱۷ روز)', ( static function (): bool {
	for ( $t = strtotime( '1921-03-21 UTC' ); $t < strtotime( '2121-03-21 UTC' ); $t += 17 * DAY_IN_SECONDS ) {
		[ $y, $m, $d ] = array_map( 'intval', explode( '-', gmdate( 'Y-n-j', $t ) ) );
		if ( hodima_jalali_to_gregorian( ...hodima_jalali_from_gregorian( $y, $m, $d ) ) !== [ $y, $m, $d ] ) {
			return false;
		}
	}
	return true;
} )(), true );
$tz = new DateTimeZone( 'Asia/Tehran' );
hodima_ct( "قالب 'jS F Y' (پسوند ام)", hodima_jalali_format( 'jS F Y', $ts, $tz ), '۱۹ام مهر ۱۴۰۴' );
hodima_ct( "قالب 'g:i a' (۱۲ ساعته)", hodima_jalali_format( 'g:i a', $ts, $tz ), '۱:۰۰ ب.ظ' );
hodima_ct( "قالب 'D، j M' (روز و ماه کوتاه)", hodima_jalali_format( 'D، j M', $ts, $tz ), 'ش، ۱۹ مهر' );
hodima_ct( "قالب 't' اسفند ۱۴۰۳", hodima_jalali_format( 't', (int) strtotime( '2025-03-01 12:00 UTC' ), $tz ), '۳۰' );
hodima_ct( "قالب '\\\\Y Y' (نویسه گریزخورده)", hodima_jalali_format( '\\Y Y', $ts, $tz, false ), 'Y 1404' );

echo "=== ورودی‌های ایرانی (Iran_Input)\n";
require_once WP_PLUGIN_DIR . '/hodima-commerce/core/time-jalali/modules/class-iran-input.php';
$in = Hodima\Core\Time_Jalali\Modules\Iran_Input::class;
foreach ( [ '+98 912 345 6789' => '09123456789', '00989123456789' => '09123456789', '9123456789' => '09123456789', '۰۹۱۲-۳۴۵-۶۷۸۹' => '09123456789', '02112345678' => '02112345678' ] as $raw => $want ) {
	hodima_ct( "موبایل «{$raw}»", $in::mobile( (string) $raw ), $want ); // کلید عددی آرایه int می‌شود
}
hodima_ct( 'موبایل ثابت نامعتبر', $in::is_mobile( '02112345678' ), false );
hodima_ct( 'کد پستی «۱۲۳۴۵-۶۷۸۹۰»', $in::postcode( '۱۲۳۴۵-۶۷۸۹۰' ), '1234567890' );
hodima_ct( 'کد ملی درست/نادرست/تکراری', [ $in::is_national_id( '0499370899' ), $in::is_national_id( '1234567890' ), $in::is_national_id( '1111111111' ) ], [ true, false, false ] );
hodima_ct( 'کد ملی ۸ رقمی ← ۱۰ رقم', $in::national_id( '۴۵۶۷۸۹۰۱' ), '0045678901' );

echo "=== استان‌ها و شهرها (data/iran-cities.php)\n";
$cities_class = Hodima\Core\Time_Jalali\Modules\Iran_Cities::class;
if ( ! class_exists( $cities_class ) ) {
	require_once WP_PLUGIN_DIR . '/hodima-commerce/core/time-jalali/modules/class-iran-cities.php';
}
$data   = $cities_class::data();
$states = ( new $cities_class() )->add_iran_provinces( [] )['IR'];
hodima_ct( '۳۱ استان، همه با شهر', [ count( $data['cities'] ), array_keys( $data['cities'] ) === array_keys( $states ) ], [ 31, true ] );
hodima_ct( 'بیش از ۱۲۰۰ شهر', array_sum( array_map( 'count', $data['cities'] ) ) > 1200, true );
hodima_ct( 'مرکز استان اول (تهران، اصفهان، البرز)', [ $data['cities']['THR'][0], $data['cities']['ESF'][0], $data['cities']['ALZ'][0] ], [ 'تهران', 'اصفهان', 'کرج' ] );
hodima_ct( 'بدون شهر تکراری در یک استان', array_filter( $data['cities'], static fn( array $list ): bool => count( $list ) !== count( array_unique( array_map( [ $cities_class, 'name_key' ], $list ) ) ) ), [] );
hodima_ct( 'نام‌های رایج به شهر موجود اشاره دارند', array_filter( $data['aliases'], static fn( array $pairs, string $code ): bool => (bool) array_diff( array_keys( $pairs ), $data['cities'][ $code ] ), ARRAY_FILTER_USE_BOTH ), [] );

$cities = new $cities_class();
$_POST  = [ 'billing_country' => 'IR', 'billing_state' => 'ESF' ];
$args   = $cities->city_field_args( [ 'type' => 'select' ], 'billing_city', 'نجف‌آباد' );
hodima_ct( 'شهر ذخیره‌شده با املای دیگر انتخاب‌شده می‌ماند', [ $args['type'], $args['options']['نجف‌آباد'] ?? null, isset( $args['options']['نجف آباد'] ) ], [ 'select', 'نجف آباد', false ] );
$args = $cities->city_field_args( [ 'type' => 'select' ], 'billing_city', 'ساوجبلاغ' );
hodima_ct( 'شهر قدیمی خارج از فهرست گم نمی‌شود + «در فهرست نیست»', [ $args['options']['ساوجبلاغ'] ?? null, array_key_last( $args['options'] ) ], [ 'ساوجبلاغ', $cities_class::OTHER ] );
$_POST = [ 'billing_country' => 'DE' ];
$args  = $cities->city_field_args( [ 'type' => 'select', 'options' => [ '' => '-' ] ], 'billing_city', 'Berlin' );
hodima_ct( 'کشور دیگر ← کادر متنی', [ $args['type'], isset( $args['options'] ) ], [ 'text', false ] );
$_POST = [];

echo "\n" . ( $failed ? "✘ {$failed} آزمون رد شد ({$passed} قبول)" : "✔ همه {$passed} آزمون Commerce قبول" ) . "\n";
exit( $failed ? 1 : 0 );
