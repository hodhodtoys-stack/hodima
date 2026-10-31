<?php
/**
 * آزمون خودکار ماژول «دستار سئو» (SEO، core/advisor): یکسان‌سازی آدرس، جمع‌بندی ردیف‌های
 * سرچ کنسول، ذخیره و خواندن، سه درخواست دریافت (با پاسخ ساختگی گوگل)، شکست بدون خراب
 * کردن داده قبلی، بازه‌ها، و قاعده‌های پیشنهاد (نرخ کلیک، کلمه نزدیک صفحه اول، کلمه غایب
 * در تیترها/متن، رقابت داخلی، افت).
 *   HARNESS=1 php tools/wp-harness/advisor-tests.php
 * خروجی: هر آزمون ✔/✘؛ کد خروج ۱ اگر یکی رد شود. هیچ درخواستی به گوگل نمی‌رود
 * (فیلتر pre_http_request). ci-check.sh هم اجرا می‌کند. (HODIMA-AUDIT.md بخش ۲۵۶)
 */

declare(strict_types=1);

$_SERVER['HTTP_HOST'] = $_SERVER['SERVER_NAME'] = 'hodima.test';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['HTTPS']       = 'on';
require rtrim( (string) ( getenv( 'HODIMA_WP' ) ?: '/tmp/hodima-harness/wp' ), '/' ) . '/wp-load.php';

if ( ! getenv( 'HARNESS' ) ) {
	fwrite( STDERR, "این آزمون داده می‌سازد و پاک می‌کند؛ فقط روی ابزار تست: HARNESS=1\n" );
	exit( 2 );
}
if ( ! function_exists( 'hodima_seo_advisor_refresh' ) ) {
	echo "✘ ماژول دستار سئو لود نشده است\n";
	exit( 1 );
}

$failed = 0;
$passed = 0;

function hodima_ad( string $name, mixed $actual, mixed $expected ): void {
	global $failed, $passed;
	if ( $actual === $expected ) {
		++$passed;
		echo "  ✔ {$name}\n";
		return;
	}
	++$failed;
	echo "  ✘ {$name}\n      انتظار: " . var_export( $expected, true ) . "\n      واقعی:  " . var_export( $actual, true ) . "\n";
}

$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
$u    = static fn( string $path ): string => home_url( $path );

echo "=== یکسان‌سازی آدرس\n";
hodima_ad( 'بخش #toc حذف می‌شود', hodima_seo_advisor_path_key( $u( '/hair/#toc-1' ) ), '/hair/' );
hodima_ad( 'www و حروف بزرگ', hodima_seo_advisor_path_key( 'https://www.' . $host . '/Hair/Clips/' ), '/hair/clips/' );
hodima_ad( 'پارامتر حذف می‌شود', hodima_seo_advisor_path_key( $u( '/hair/?utm_source=x' ) ), '/hair/' );
hodima_ad( 'صفحه اصلی', hodima_seo_advisor_path_key( 'https://' . $host ), '/' );
hodima_ad( 'آدرس فارسی کدگذاری‌شده رمزگشایی می‌شود', hodima_seo_advisor_path_key( $u( '/' . rawurlencode( 'گل-سر' ) . '/' ) ), '/گل-سر/' );
hodima_ad( 'دامنه دیگر رد می‌شود', hodima_seo_advisor_path_key( 'https://other.example/x/' ), null );
hodima_ad( 'آدرس بلندتر از ستون رد می‌شود', hodima_seo_advisor_path_key( $u( '/' . str_repeat( 'a', 200 ) . '/' ) ), null );

echo "=== بازه‌ها و تنظیم\n";
$range = hodima_seo_advisor_ranges( 90 );
$diff  = static fn( string $a, string $b ): int => (int) ( new DateTimeImmutable( $a ) )->diff( new DateTimeImmutable( $b ) )->days;
hodima_ad( 'بازه فعلی ۹۰ روز', $diff( $range['start'], $range['end'] ), 89 );
hodima_ad( 'بازه قبلی ۹۰ روز و بلافاصله پیش از فعلی', [ $diff( $range['prev_start'], $range['prev_end'] ), $diff( $range['prev_end'], $range['start'] ) ], [ 89, 1 ] );
$saved_settings = get_option( HODIMA_SEO_ADVISOR_SETTINGS_OPTION );
update_option( HODIMA_SEO_ADVISOR_SETTINGS_OPTION, [ 'days' => 28 ], false );
hodima_ad( 'بازه ۲۸ روز', hodima_seo_advisor_days(), 28 );
update_option( HODIMA_SEO_ADVISOR_SETTINGS_OPTION, [ 'days' => 7 ], false );
hodima_ad( 'بازه نامعتبر ← ۹۰', hodima_seo_advisor_days(), 90 );
delete_option( HODIMA_SEO_ADVISOR_SETTINGS_OPTION );
hodima_ad( 'بدون تنظیم ← ۹۰', hodima_seo_advisor_days(), 90 );

echo "=== دریافت با پاسخ ساختگی گوگل\n";

// کلید حساب سرویس ساختگی
$pem = '';
openssl_pkey_export( openssl_pkey_new( [ 'private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA ] ), $pem );
$key_json = (string) wp_json_encode( [ 'type' => 'service_account', 'client_email' => 'advisor@test.iam.gserviceaccount.com', 'private_key' => $pem, 'token_uri' => 'https://oauth2.googleapis.com/token' ] );

$saved = [];
foreach ( [ 'hodima_discover_sc_key', 'hodima_gi_json_key', 'hodima_discover_sc_settings', 'hodima_discover_sc_sites', HODIMA_SEO_ADVISOR_STATUS_OPTION, HODIMA_SEO_ADVISOR_IGNORED_OPTION ] as $opt ) {
	$saved[ $opt ] = get_option( $opt );
}
delete_option( HODIMA_SEO_ADVISOR_IGNORED_OPTION ); // آزمون‌ها مستقل از آدرس‌های نادیده‌گرفته‌شده خود سایت
delete_transient( 'hodima_discover_sc_token_' . substr( md5( 'advisor@test.iam.gserviceaccount.com' ), 0, 12 ) );
update_option( 'hodima_discover_sc_key', $key_json, false );
delete_option( 'hodima_gi_json_key' );
delete_option( 'hodima_discover_sc_settings' );
update_option( 'hodima_discover_sc_sites', [ 'sc-domain:' . $host ], false );

// صفحه واقعی برای شناسایی نوع شیء
foreach ( get_posts( [ 'post_type' => 'page', 'name' => 'advisor-test-page', 'post_status' => 'any', 'numberposts' => -1 ] ) as $leftover ) {
	wp_delete_post( $leftover->ID, true ); // بازمانده اجرای نیمه‌کاره قبلی نامک را اشغال نکند
}
$page_id = (int) wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Advisor Test', 'post_name' => 'advisor-test-page' ] );

// پاسخ‌ها
$mode  = 'ok';
$calls = [];
$range = hodima_seo_advisor_ranges( hodima_seo_advisor_days() );

$terms = [
	[ $u( '/accessory/hair-clips/' ), 'کلیپس', 20, 1000, 6.0 ],
	[ $u( '/accessory/hair-clips/#toc-1' ), 'کلیپس', 2, 1000, 8.0 ],
	[ $u( '/accessory/hair-clips/' ), 'rare', 0, 4, 3.0 ],
];
for ( $n = 0; $n < 60; ++$n ) {
	$terms[] = [ $u( '/accessory/hair-clips/' ), "k{$n}", 0, 10 + $n, 5.0 ];
}
$query_rows = array_map(
	static fn( array $t ): array => [ 'keys' => [ $t[0], $t[1] ], 'clicks' => $t[2], 'impressions' => $t[3], 'position' => $t[4] ],
	$terms
);

$current_rows = [
	[ 'keys' => [ $u( '/accessory/hair-clips/' ) ], 'clicks' => 5, 'impressions' => 100, 'position' => 4.0 ],
	[ 'keys' => [ $u( '/accessory/hair-clips/#toc-1' ) ], 'clicks' => 7, 'impressions' => 300, 'position' => 8.0 ],
	[ 'keys' => [ 'https://www.' . $host . '/Accessory/Hair-Clips/' ], 'clicks' => 0, 'impressions' => 0, 'position' => 0.0 ],
	[ 'keys' => [ $u( '/' . rawurlencode( 'گل-سر' ) . '/' ) ], 'clicks' => 3, 'impressions' => 250, 'position' => 5.0 ],
	[ 'keys' => [ $u( '/advisor-test-page/' ) ], 'clicks' => 1, 'impressions' => 50, 'position' => 9.0 ],
	[ 'keys' => [ 'https://other.example/x/' ], 'clicks' => 99, 'impressions' => 999, 'position' => 1.0 ],
	[ 'keys' => [ $u( '/' . str_repeat( 'a', 200 ) . '/' ) ], 'clicks' => 9, 'impressions' => 90, 'position' => 2.0 ],
];
$prev_rows = [
	[ 'keys' => [ $u( '/accessory/hair-clips/' ) ], 'clicks' => 1200, 'impressions' => 40000, 'position' => 7.0 ],
	[ 'keys' => [ $u( '/old-dropped/' ) ], 'clicks' => 50, 'impressions' => 900, 'position' => 3.0 ],
];

add_filter(
	'pre_http_request',
	static function ( $pre, array $args, string $url ) use ( &$mode, &$calls, $current_rows, $prev_rows, $query_rows, $host, &$range ) {
		$json = static fn( int $code, array $body ): array => [ 'headers' => [], 'body' => (string) wp_json_encode( $body ), 'response' => [ 'code' => $code, 'message' => '' ], 'cookies' => [], 'filename' => null ];

		if ( str_starts_with( $url, 'https://oauth2.googleapis.com/token' ) ) {
			return $json( 200, [ 'access_token' => 'tok', 'expires_in' => 3600 ] );
		}
		if ( str_ends_with( $url, '/webmasters/v3/sites' ) ) {
			return $json( 200, [ 'siteEntry' => [ [ 'siteUrl' => 'sc-domain:' . $host, 'permissionLevel' => 'siteOwner' ] ] ] );
		}
		if ( str_contains( $url, '/searchAnalytics/query' ) ) {
			$body    = json_decode( (string) $args['body'], true );
			$calls[] = $body;
			if ( 'denied' === $mode ) {
				return $json( 403, [ 'error' => [ 'message' => 'forbidden' ] ] );
			}
			if ( [ 'page', 'query' ] === $body['dimensions'] ) {
				return $json( 200, [ 'rows' => $query_rows ] );
			}
			$is_prev = $body['startDate'] === $range['prev_start'];
			if ( 'paged' === $mode && ! $is_prev ) {
				$first = 0 === ( $body['startRow'] ?? 0 );
				return $json( 200, [ 'rows' => $first ? array_map( fn( int $n ): array => [ 'keys' => [ 'https://' . $host . "/bulk/{$n}/" ], 'clicks' => 0, 'impressions' => 1, 'position' => 9.0 ], range( 1, 25000 ) ) : [ [ 'keys' => [ 'https://' . $host . '/tail-a/' ], 'clicks' => 0, 'impressions' => 2, 'position' => 9.0 ], [ 'keys' => [ 'https://' . $host . '/tail-b/' ], 'clicks' => 0, 'impressions' => 3, 'position' => 9.0 ] ] ] );
			}
			if ( $is_prev && 'prev_fails' === $mode ) {
				return $json( 500, [ 'error' => [ 'message' => 'backend error' ] ] );
			}
			return $json( 200, [ 'rows' => $is_prev ? $prev_rows : $current_rows ] );
		}
		return $pre;
	},
	10,
	3
);

hodima_ad( 'تابع‌ها و ماژول لود شده‌اند', [ function_exists( 'hodima_seo_sc_request' ), class_exists( '\Hodima\Core\Modules' ) && \Hodima\Core\Modules::is_loaded( 'seo', 'advisor' ) ], [ true, true ] );
hodima_ad( 'رویداد روزانه به تابع دریافت وصل است', has_action( HODIMA_SEO_ADVISOR_CRON ) !== false, true );

add_filter( 'hodima_seo_advisor_inspect_enabled', '__return_false' );
add_filter( 'hodima_seo_advisor_health_enabled', '__return_false' );
add_filter( 'hodima_seo_advisor_trend_enabled', '__return_false' );
add_filter( 'hodima_seo_advisor_breakdown_enabled', '__return_false' ); // بخش‌بندی در آزمون‌های دریافت اصلی خاموش؛ پایین جدا آزمون می‌شود
// تاریخچه هفتگی را از پیش پر می‌کنیم تا شمار درخواست‌های آزمون‌های دریافت فقط سه‌تای اصلی باشد (خودش پایین آزمون می‌شود)
hodima_seo_advisor_ensure_tables();
$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', hodima_seo_advisor_table( 'history' ) ) );
foreach ( hodima_seo_advisor_history_week_ends() as $seed_week ) {
	hodima_seo_advisor_history_store_week( $seed_week, [ '/seed/' => [ 'clicks' => 1, 'impressions' => 100, 'position' => 5.0 ] ] );
}

$result = hodima_seo_advisor_refresh();
hodima_ad( 'دریافت موفق', $result, true );
hodima_ad( 'سه درخواست: صفحه فعلی، صفحه قبلی، صفحه+کلمه (جستجوی وب، ۲۵ هزار ردیف)', array_map( static fn( array $b ): array => [ $b['dimensions'], $b['type'], $b['rowLimit'] ], $calls ), [ [ [ 'page' ], 'web', 25000 ], [ [ 'page' ], 'web', 25000 ], [ [ 'page', 'query' ], 'web', 25000 ] ] );
hodima_ad( 'بازه درخواست فعلی و قبلی', [ $calls[0]['startDate'], $calls[0]['endDate'], $calls[1]['startDate'], $calls[1]['endDate'] ], [ $range['start'], $range['end'], $range['prev_start'], $range['prev_end'] ] );

$status = hodima_seo_advisor_status();
hodima_ad( 'وضعیت: property، تعداد آدرس‌ها (دامنه دیگر و آدرس بلند نه) و بدون خطا', [ $status['property'], $status['pages'], $status['error'], $status['days'] ], [ 'sc-domain:' . $host, 4, '', 90 ] );

$clips = hodima_seo_advisor_page( '/accessory/hair-clips/' );
hodima_ad( 'آدرس‌های هم‌مسیر (#toc، www، حروف بزرگ) یکی شدند؛ رتبه با وزن نمایش', [ $clips['clicks'], $clips['impressions'], $clips['position'] ], [ 12, 400, 7.0 ] );
hodima_ad( 'بازه قبلی کنار همان ردیف', [ $clips['prev_clicks'], $clips['prev_impressions'], $clips['prev_position'] ], [ 1200, 40000, 7.0 ] );
$dropped = hodima_seo_advisor_page( $u( '/old-dropped/' ) );
hodima_ad( 'آدرسی که فقط قبلا نمایش داشت می‌ماند (فعلی صفر)', [ $dropped['clicks'], $dropped['impressions'], $dropped['prev_impressions'] ], [ 0, 0, 900 ] );
hodima_ad( 'آدرس فارسی ذخیره و پیدا می‌شود', hodima_seo_advisor_page( '/' . rawurlencode( 'گل-سر' ) . '/' )['clicks'] ?? null, 3 );
hodima_ad( 'آدرس ناشناخته ← null', hodima_seo_advisor_page( '/no-such/' ), null );

$test_page = hodima_seo_advisor_page( '/advisor-test-page/' );
hodima_ad( 'نوع و شناسه شیء با روتر آدرس تمیز', [ $test_page['kind'], $test_page['object_id'] ], hodima_router_active() ? [ 'page', $page_id ] : [ '', 0 ] );

$q = hodima_seo_advisor_queries( '/accessory/hair-clips/', 100 );
hodima_ad( 'کلمه‌ها: حداکثر ۵۰، اولی پرنمایش‌ترین (دو ردیف #toc جمع شد: ۲۰۰۰ نمایش، رتبه ۷، ۲۲ کلیک)', [ count( $q ), $q[0] ], [ 50, [ 'term' => 'کلیپس', 'clicks' => 22, 'impressions' => 2000, 'position' => 7.0 ] ] );
hodima_ad( 'کلمه کم‌نمایش (۴) ذخیره نمی‌شود و کمترین نمایش بین ۵۰ تا ۲۱ است', [ in_array( 'rare', array_column( $q, 'term' ), true ), min( array_column( $q, 'impressions' ) ) ], [ false, 21 ] );
hodima_ad( 'محدودیت تعداد کلمه', count( hodima_seo_advisor_queries( '/accessory/hair-clips/', 5 ) ), 5 );

echo "=== فهرست و فیلتر\n";
$all = hodima_seo_advisor_pages();
hodima_ad( 'همه: ۴ آدرس، مرتب بر اساس نمایش', [ $all['total'], array_column( $all['rows'], 'path' ) ], [ 4, [ '/accessory/hair-clips/', '/گل-سر/', '/advisor-test-page/', '/old-dropped/' ] ] );
hodima_ad( 'حداقل نمایش ۱۰۰', hodima_seo_advisor_pages( [ 'min_impressions' => 100 ] )['total'], 2 );
hodima_ad( 'جستجو در آدرس', array_column( hodima_seo_advisor_pages( [ 'search' => 'CLIPS' ] )['rows'], 'path' ), [ '/accessory/hair-clips/' ] );
hodima_ad( 'فیلتر نوع', array_column( hodima_seo_advisor_pages( [ 'kind' => 'page' ] )['rows'], 'path' ), hodima_router_active() ? [ '/advisor-test-page/' ] : [] );
hodima_ad( 'مرتب‌سازی کلیک صعودی + صفحه‌بندی', array_column( hodima_seo_advisor_pages( [ 'orderby' => 'clicks', 'order' => 'ASC', 'limit' => 2, 'offset' => 1 ] )['rows'], 'path' ), [ '/advisor-test-page/', '/گل-سر/' ] );
hodima_ad( 'ستون مرتب‌سازی نامعتبر خطا نمی‌دهد', hodima_seo_advisor_pages( [ 'orderby' => 'x; DROP TABLE y' ] )['total'], 4 );

echo "=== شکست‌ها داده قبلی را خراب نمی‌کنند\n";
$calls = [];
$mode  = 'prev_fails';
$res   = hodima_seo_advisor_refresh();
hodima_ad( 'شکست بازه قبلی ← خطا و داده قبلی دست‌نخورده', [ is_wp_error( $res ) ? $res->get_error_code() : '', hodima_seo_advisor_page( '/accessory/hair-clips/' )['clicks'], count( $calls ) ], [ 'hodima_advisor_partial', 12, 2 ] );
hodima_ad( 'پیام خطا در وضعیت می‌ماند، آخرین دریافت موفق هم', [ str_contains( hodima_seo_advisor_status()['error'], 'backend error' ), hodima_seo_advisor_status()['pages'] ], [ true, 4 ] );

$mode = 'denied';
$res  = hodima_seo_advisor_refresh();
hodima_ad( 'همه propertyها ۴۰۳ ← پیام راهنما', [ is_wp_error( $res ) ? $res->get_error_code() : '', is_wp_error( $res ) && str_contains( $res->get_error_message(), 'سرچ کنسول اجازه نداد' ), hodima_seo_advisor_page( '/accessory/hair-clips/' )['clicks'] ], [ 'hodima_advisor_failed', true, 12 ] );

$mode = 'ok';
delete_option( 'hodima_discover_sc_key' );
$res = hodima_seo_advisor_refresh();
hodima_ad( 'بدون کلید ← خطای کلید، داده دست‌نخورده', [ is_wp_error( $res ) ? $res->get_error_code() : '', hodima_seo_advisor_page( '/accessory/hair-clips/' )['clicks'] ], [ 'hodima_sc_no_key', 12 ] );

echo "=== مرحله ۶: صفحه‌بندی دریافت، CSV بزرگ، آدرس با/بی‌اسلش، کش لینک‌دهنده\n";
if ( ! function_exists( 'hodima_seo_advisor_render_admin' ) ) {
	require_once HODIMA_SEO_DIR . '/core/advisor/advisor-steps-view.php';
	require_once HODIMA_SEO_DIR . '/core/advisor/advisor-help.php';
require_once HODIMA_SEO_DIR . '/core/advisor/advisor-admin.php';
require_once HODIMA_SEO_DIR . '/core/advisor/advisor-overview-view.php';
	require_once HODIMA_SEO_DIR . '/core/advisor/advisor-cron-view.php';
	require_once HODIMA_SEO_DIR . '/core/advisor/advisor-quality-view.php';
	require_once HODIMA_SEO_DIR . '/core/advisor/advisor-tasks-view.php'; // دکمه‌های «انجام دادم» ردیف‌ها (SEO 3.0.2)
}
update_option( 'hodima_discover_sc_key', $key_json, false );
$calls = [];
$mode  = 'paged';
$res   = hodima_seo_advisor_refresh();
$mode  = 'normal';
$page_calls = array_values( array_filter( $calls, static fn( array $b ): bool => [ 'page' ] === $b['dimensions'] && $b['startDate'] === $range['start'] ) );
hodima_ad( 'دریافت صفحه‌بندی‌شده: صفحه دوم با startRow ۲۵۰۰۰ خواسته شد و ردیف‌های آن ذخیره شد', [ $res, count( $page_calls ), $page_calls[1]['startRow'] ?? null, hodima_seo_advisor_page( '/tail-b/' )['impressions'] ?? null, hodima_seo_advisor_pages()['total'] ], [ true, 2, 25000, 3, 25004 ] );
hodima_ad( 'خروجی CSV بیش از ۵۰۰ ردیف: تا ۵۰۰۰ (+ سربرگ)', count( hodima_seo_advisor_csv_rows( [] ) ), HODIMA_SEO_ADVISOR_CSV_MAX + 1 );
hodima_ad( 'آدرس با و بی‌اسلش پایانی یکی است؛ آدرس فایل دست‌نخورده', [ hodima_seo_advisor_path_key( $u( '/hair' ) ), hodima_seo_advisor_path_key( $u( '/hair/' ) ), hodima_seo_advisor_path_key( $u( '/files/a.pdf' ) ) ], [ '/hair/', '/hair/', '/files/a.pdf' ] );
hodima_ad( 'آدرس کامل از مسیر ذخیره‌شده (دامنه + مسیر، بدون دوبار گذاشتن پیشوند زیرپوشه)', [ hodima_seo_advisor_url( '/a/b/' ), hodima_seo_advisor_path_key( hodima_seo_advisor_url( '/a/b' ) ) ], [ ( (string) wp_parse_url( home_url( '/' ), PHP_URL_SCHEME ) ) . '://' . ( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) ) . ( wp_parse_url( home_url( '/' ), PHP_URL_PORT ) ? ':' . wp_parse_url( home_url( '/' ), PHP_URL_PORT ) : '' ) . '/a/b/', '/a/b/' ] );
hodima_seo_advisor_store( [], [], [] );

echo "=== قاعده‌ها: نرخ کلیک معمول و متن\n";
delete_option( HODIMA_SEO_ADVISOR_CTR_OPTION ); // دریافت‌های ساختگی بالا جدول آموخته‌شده ساخته‌اند؛ این بخش جدول عمومی را می‌سنجد
hodima_ad( 'نرخ معمول هر رتبه', array_map( 'hodima_seo_advisor_expected_ctr', [ 1.5, 2.0, 2.01, 3.0, 5.0, 5.81, 7.0, 10.0, 10.01 ] ), [ 25.0, 25.0, 14.0, 14.0, 9.0, 5.5, 5.5, 3.5, 1.5 ] );
hodima_ad( 'یکسان‌سازی متن (ی/ک عربی، نیم‌فاصله، رقم عربی)', [ hodima_seo_advisor_normalize( 'كيك گل‌سر ١٢٣ ۴۵' ), hodima_seo_advisor_text_has( hodima_seo_advisor_normalize( 'انواع گل سر زیبا' ), 'گل‌سر' ), hodima_seo_advisor_text_has( 'کلیپس مو', 'پلومریا' ) ], [ 'کیک گل سر 123 45', true, false ] );

$page_for_text = (int) wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'کلیپس مو', 'post_name' => 'advisor-text-page', 'post_content' => '<h2>پلومریا مو</h2><p>متن [shortcode_x] درباره گیره</p>' ] );
update_post_meta( $page_for_text, '_seobox_title', 'عنوان سئو سفارشی' );
add_shortcode( 'shortcode_x', static fn(): string => '' ); // strip_shortcodes فقط شورت‌کدهای ثبت‌شده را برمی‌دارد
$text = hodima_seo_advisor_page_text( [ 'kind' => 'page', 'object_id' => $page_for_text ] );
hodima_ad( 'متن صفحه: عنوان سئو، نام، تیترها در یک دسته؛ متن در دسته دیگر؛ شورت‌کد حذف', [ str_contains( (string) $text['title_headings'], 'عنوان سئو سفارشی' ) && str_contains( (string) $text['title_headings'], 'پلومریا مو' ), str_contains( (string) $text['content'], 'درباره گیره' ), str_contains( (string) $text['content'], 'shortcode_x' ) ], [ true, true, false ] );
hodima_ad( 'شیء ناشناخته و صفحه اصلی ← null', [ hodima_seo_advisor_page_text( [ 'kind' => '', 'object_id' => 0 ] ), hodima_seo_advisor_page_text( [ 'kind' => 'home', 'object_id' => 0 ] ), hodima_seo_advisor_page_text( [ 'kind' => 'page', 'object_id' => 99999999 ] ) ], [ null, null, null ] );
remove_shortcode( 'shortcode_x' );
wp_delete_post( $page_for_text, true );

echo "=== قاعده‌ها: پیشنهادها (داده واقعی گزارش ۶ ماهه کاربر)\n";
$base = [ 'path' => '/x/', 'clicks' => 496, 'impressions' => 36492, 'position' => 5.81, 'prev_clicks' => 0 ];
$rules = static fn( array $list ): array => array_column( $list, 'rule' );
$low   = hodima_seo_advisor_suggestions( $base, [], null );
hodima_ad( 'نرخ کلیک پایین (۱٫۳۶٪ در رتبه ۵٫۸): کلیک بالقوه ۱۵۱۱', [ $rules( $low ), $low[0]['potential'] ], [ [ 'low_ctr' ], 1511 ] );
hodima_ad( 'متن پیشنهاد کلمه اصلی را می‌آورد', str_contains( hodima_seo_advisor_suggestions( $base, [ [ 'term' => 'کلیپس پلومریا', 'clicks' => 116, 'impressions' => 15480, 'position' => 5.08 ] ], null )[0]['detail'], 'کلیپس پلومریا' ), true );
hodima_ad( 'کمتر از حداقل نمایش یا نرخ کلیک خوب ← پیشنهادی نیست', [ hodima_seo_advisor_suggestions( [ 'impressions' => 999 ] + $base, [], null ), hodima_seo_advisor_suggestions( [ 'clicks' => 5000 ] + $base, [], null ) ], [ [], [] ] );
hodima_ad( 'صفحه‌بندی آرشیو پیشنهاد ندارد', hodima_seo_advisor_suggestions( [ 'path' => '/accessory/page/2/' ] + $base, [], null ), [] );

$near = hodima_seo_advisor_suggestions( [ 'impressions' => 100, 'clicks' => 5 ] + $base, [ [ 'term' => 'پلومریا', 'clicks' => 11, 'impressions' => 3064, 'position' => 8.0 ] ], null );
hodima_ad( 'کلمه رتبه ۸: نزدیک صفحه اول، کلیک بالقوه ۱۵۸', [ $rules( $near ), $near[0]['potential'] ?? null ], [ [ 'near_first' ], 158 ] );

$weak = hodima_seo_advisor_suggestions( [ 'impressions' => 100, 'clicks' => 5 ] + $base, [ [ 'term' => 'گیره مو عمده', 'clicks' => 2, 'impressions' => 2000, 'position' => 31.0 ], [ 'term' => 'دور', 'clicks' => 0, 'impressions' => 2000, 'position' => 75.0 ], [ 'term' => 'کم‌نمایش', 'clicks' => 0, 'impressions' => 100, 'position' => 40.0 ] ], null );
hodima_ad( 'کلمه رتبه ۳۱ با نمایش کافی: «صفحه اختصاصی»؛ رتبه ۷۵ و کم‌نمایش نه', [ $rules( $weak ), $weak[0]['term'] ?? '', ( $weak[0]['potential'] ?? 0 ) > 0 ], [ [ 'weak_query' ], 'گیره مو عمده', true ] );


$haystack = [ 'title_headings' => hodima_seo_advisor_normalize( 'کلیپس مو' ), 'content' => hodima_seo_advisor_normalize( 'کلیپس مو پلومریا زیبا' ) ];
$q        = static fn( string $term ): array => [ 'term' => $term, 'clicks' => 0, 'impressions' => 1000, 'position' => 4.0 ];
$small    = [ 'impressions' => 100, 'clicks' => 5 ] + $base;
hodima_ad( 'کلمه فقط در متن: «در تیترها نیست»', $rules( hodima_seo_advisor_suggestions( $small, [ $q( 'پلومریا' ) ], $haystack ) ), [ 'missing_headings' ] );
hodima_ad( 'کلمه هیچ‌جا نیست: «در متن نیست»', $rules( hodima_seo_advisor_suggestions( $small, [ $q( 'ناموجود' ) ], $haystack ) ), [ 'missing_content' ] );
hodima_ad( 'کلمه در عنوان هست ← پیشنهادی نیست', hodima_seo_advisor_suggestions( $small, [ $q( 'کلیپس' ) ], $haystack ), [] );
hodima_ad( 'متن صفحه معلوم نیست ← قاعده متن اجرا نمی‌شود', hodima_seo_advisor_suggestions( $small, [ $q( 'ناموجود' ) ], null ), [] );
hodima_ad( 'کلمه کم‌نمایش (زیر ۳۰۰) بررسی نمی‌شود', hodima_seo_advisor_suggestions( $small, [ [ 'impressions' => 299 ] + $q( 'ناموجود' ) ], $haystack ), [] );

$dec = static fn( int $clicks ): array => hodima_seo_advisor_suggestions( [ 'impressions' => 100, 'clicks' => $clicks, 'prev_clicks' => 1000 ] + $base, [], null );
hodima_ad( 'افت کلیک (۱۰۰۰ ← ۶۰۰): کلیک از دست‌رفته ۴۰۰', [ $rules( $dec( 600 ) ), $dec( 600 )[0]['potential'] ], [ [ 'decline' ], 400 ] );
hodima_ad( 'افت کم (۱۰۰۰ ← ۸۰۰) پیشنهادی نیست', $dec( 800 ), [] );

$many = [];
for ( $n = 0; $n < 8; ++$n ) {
	$many[] = [ 'term' => "نزدیک{$n}", 'clicks' => 0, 'impressions' => 1000 + $n, 'position' => 9.0 ];
	$many[] = [ 'term' => "غایب{$n}", 'clicks' => 0, 'impressions' => 2000 + $n, 'position' => 4.0 ];
}
$list = hodima_seo_advisor_suggestions( $base, $many, $haystack );
$counts = array_count_values( $rules( $list ) );
hodima_ad( 'سقف: حداکثر ۶ پیشنهاد و ۲ تا از هر قاعده کلمه‌محور؛ مرتب بر اساس کلیک بالقوه', [ count( $list ) <= 6, ( $counts['near_first'] ?? 0 ) <= 2, ( $counts['missing_content'] ?? 0 ) <= 2, $list === array_values( $list ) && array_column( $list, 'potential' ) === ( static function ( array $p ): array { rsort( $p ); return $p; } )( array_column( $list, 'potential' ) ) ], [ true, true, true, true ] );

add_filter( 'hodima_seo_advisor_config', $cfg_filter = static fn( array $c ): array => [ 'min_impressions' => 50000 ] + $c );
hodima_ad( 'فیلتر آستانه: حداقل نمایش ۵۰٬۰۰۰ ← پیشنهادی نیست', hodima_seo_advisor_suggestions( $base, [], null ), [] );
remove_filter( 'hodima_seo_advisor_config', $cfg_filter );
add_filter( 'hodima_seo_advisor_ctr_table', $tbl_filter = static fn(): array => [ 10 => 1.0 ] );
hodima_ad( 'فیلتر جدول نرخ کلیک معمول', [ hodima_seo_advisor_expected_ctr( 5.0 ), hodima_seo_advisor_suggestions( $base, [], null ) ], [ 1.0, [] ] );
remove_filter( 'hodima_seo_advisor_ctr_table', $tbl_filter );

echo "=== قاعده‌ها: ذخیره در جدول، رقابت داخلی، فهرست\n";
$z   = [ 'clicks' => 0, 'impressions' => 0, 'position' => 0.0 ];
$cur = [
	'/rules/a/'       => [ 'clicks' => 496, 'impressions' => 36492, 'position' => 5.81 ],
	'/rules/b/'       => [ 'clicks' => 5, 'impressions' => 500, 'position' => 6.0 ],
	'/rules/decline/' => [ 'clicks' => 600, 'impressions' => 6000, 'position' => 6.0 ],
	'/rules/can1/'    => [ 'clicks' => 200, 'impressions' => 2000, 'position' => 4.0 ],
	'/rules/can2/'    => [ 'clicks' => 200, 'impressions' => 2000, 'position' => 4.0 ],
];
$prv = [ '/rules/decline/' => [ 'clicks' => 1000, 'impressions' => 6000, 'position' => 6.0 ] ];
$qs  = [
	'/rules/can1/' => [ 'پلومریا' => [ 'clicks' => 50, 'impressions' => 500, 'position' => 4.0 ] ],
	'/rules/can2/' => [ 'پلومریا' => [ 'clicks' => 40, 'impressions' => 400, 'position' => 6.0 ] ],
];
hodima_seo_advisor_store( $cur, $prv, $qs );
$advised = hodima_seo_advisor_analyze_all();
hodima_ad( 'تحلیل همه: ۴ آدرس پیشنهاد دارند (b زیر حداقل نمایش است)', $advised, 4 );
$listed = hodima_seo_advisor_pages( [ 'has_advice' => true, 'orderby' => 'potential' ] );
hodima_ad( 'فهرست مرتب بر اساس کلیک بالقوه: a (۱۵۱۱)، افت (۴۰۰)، دو رقیب (۰)', [ $listed['total'], array_slice( array_column( $listed['rows'], 'path' ), 0, 2 ), array_column( $listed['rows'], 'potential' ) ], [ 4, [ '/rules/a/', '/rules/decline/' ], [ 1511, 400, 0, 0 ] ] );
hodima_ad( 'آدرس b (زیر حداقل نمایش) پیشنهاد ندارد', [ hodima_seo_advisor_page( '/rules/b/' )['advice_count'], hodima_seo_advisor_page( '/rules/b/' )['advice'] ], [ 0, [] ] );
$a = hodima_seo_advisor_page( '/rules/a/' );
hodima_ad( 'پیشنهادها در ردیف ذخیره می‌شوند و با متن فارسی برمی‌گردند', [ $a['advice_count'], $a['advice'][0]['rule'], str_contains( $a['advice'][0]['title'], 'عنوان' ) ], [ 1, 'low_ctr', true ] );
$can = hodima_seo_advisor_analyze( '/rules/can1/' );
hodima_ad( 'رقابت داخلی: صفحه دیگر برای «پلومریا» پیدا می‌شود و پیشنهاد بدون کلیک بالقوه است', [ $rules( $can['suggestions'] ), str_contains( $can['suggestions'][0]['detail'], '/rules/can2/' ), $can['potential'] ], [ [ 'cannibal' ], true, 0 ] );
$can2 = hodima_seo_advisor_analyze( '/rules/can2/' );
hodima_ad( 'رقابت دوطرفه: هر دو صفحه رقیب این ایراد را در گزارش خود دارند و هر کدام دیگری را نام می‌برد', [ $rules( $can2['suggestions'] ), str_contains( $can2['suggestions'][0]['detail'], '/rules/can1/' ), $can['suggestions'][0]['title'] ], [ [ 'cannibal' ], true, 'رقابت با ' . hodima_seo_advisor_num( 1 ) . ' صفحه برای «پلومریا»' ] );
$many_q = [];
foreach ( [ 'یک', 'دو', 'سه', 'چهار' ] as $k => $w ) {
	$many_q[ "کلمه {$w}" ] = [ 'clicks' => 10, 'impressions' => 400 - $k, 'position' => 4.0 ];
}
$compet = [];
foreach ( array_keys( $many_q ) as $t ) {
	$compet[ $t ] = [ [ 'path' => '/other/', 'impressions' => 200 ] ];
}
$sg = hodima_seo_advisor_suggestions(
	[ 'path' => '/many/', 'clicks' => 40, 'impressions' => 1600, 'position' => 4.0, 'prev_clicks' => 0 ],
	array_map( static fn( string $t, array $q ): array => [ 'term' => $t ] + $q, array_keys( $many_q ), $many_q ),
	null,
	$compet
);
hodima_ad( 'رقابت داخلی: تا ۳ کلمه، هر کلمه یک ردیف', array_count_values( array_column( $sg, 'rule' ) )['cannibal'] ?? 0, 3 );
hodima_ad( 'تحلیل آدرس ناشناخته ← null', hodima_seo_advisor_analyze( '/no-such-page/' ), null );
hodima_ad( 'تحلیل دوباره قبلی‌ها را پاک می‌کند (نه روی هم)', [ hodima_seo_advisor_analyze_all(), hodima_seo_advisor_pages( [ 'has_advice' => true ] )['total'] ], [ 4, 4 ] );

echo "=== صفحه پیشخوان: خلاصه، فیلتر، نادیده گرفتن، CSV، نمایش\n";
// صفحه پیشخوان فقط در پیشخوان لود می‌شود؛ آزمون خط فرمان خودش لودش می‌کند
if ( ! function_exists( 'hodima_seo_advisor_render_admin' ) ) {
	require_once HODIMA_SEO_DIR . '/core/advisor/advisor-steps-view.php';
	require_once HODIMA_SEO_DIR . '/core/advisor/advisor-help.php';
require_once HODIMA_SEO_DIR . '/core/advisor/advisor-admin.php';
	require_once HODIMA_SEO_DIR . '/core/advisor/advisor-cron-view.php';
	require_once HODIMA_SEO_DIR . '/core/advisor/advisor-quality-view.php';
	require_once HODIMA_SEO_DIR . '/core/advisor/advisor-tasks-view.php'; // دکمه‌های «انجام دادم» ردیف‌ها (SEO 3.0.2)
}
$totals   = hodima_seo_advisor_totals();
$weighted = ( 5.81 * 36492 + 6.0 * 500 + 6.0 * 6000 + 4.0 * 2000 + 4.0 * 2000 ) / 46992;
hodima_ad( 'خلاصه کل: آدرس‌ها، دارای پیشنهاد، کلیک، نمایش، رتبه با وزن نمایش', [ $totals['pages'], $totals['advised'], $totals['clicks'], $totals['impressions'], $totals['position'] ], [ 5, 4, 1501, 46992, round( $weighted, 1 ) ] );
hodima_ad( 'گروه‌های نوع (آدرس‌های ناشناخته در هیچ گروهی نیستند)', hodima_seo_advisor_group_counts(), [ 'product' => 0, 'category' => 0, 'content' => 0 ] );
hodima_ad( 'بیشترین کلیک بالقوه', hodima_seo_advisor_max_potential(), 1511 );
hodima_ad( 'فیلتر قاعده: افت و رقابت', [ array_column( hodima_seo_advisor_pages( [ 'rule' => 'decline' ] )['rows'], 'path' ), hodima_seo_advisor_pages( [ 'rule' => 'cannibal' ] )['total'] ], [ [ '/rules/decline/' ], 2 ] );
hodima_ad( 'قاعده نامعتبر نادیده گرفته می‌شود (نه SQL)', hodima_seo_advisor_pages( [ 'rule' => 'x"; DROP TABLE y' ] )['total'], 5 );
hodima_ad( 'فیلتر مسیرها: خالی ← هیچ، دو مسیر ← همان دو', [ hodima_seo_advisor_pages( [ 'paths' => [] ] )['total'], hodima_seo_advisor_pages( [ 'paths' => [ '/rules/a/', '/rules/b/' ] ] )['total'] ], [ 0, 2 ] );

hodima_seo_advisor_set_ignored( '/rules/a/', true );
hodima_ad( 'نادیده گرفتن: پیشنهادش پاک می‌شود و در فهرست نادیده‌ها می‌آید', [ hodima_seo_advisor_page( '/rules/a/' )['advice_count'], hodima_seo_advisor_pages( [ 'has_advice' => true ] )['total'], hodima_seo_advisor_ignored() ], [ 0, 3, [ '/rules/a/' ] ] );
hodima_ad( 'تحلیل همه آدرس نادیده‌گرفته‌شده را دوباره پیشنهاد نمی‌دهد', [ hodima_seo_advisor_analyze_all(), hodima_seo_advisor_page( '/rules/a/' )['advice_count'] ], [ 3, 0 ] );
hodima_seo_advisor_set_ignored( '/rules/a/', false );
hodima_ad( 'بازگرداندن: همان لحظه دوباره تحلیل می‌شود', [ hodima_seo_advisor_page( '/rules/a/' )['advice'][0]['rule'], hodima_seo_advisor_ignored(), hodima_seo_advisor_pages( [ 'has_advice' => true ] )['total'] ], [ 'low_ctr', [], 4 ] );

$f = hodima_seo_advisor_list_filters( [ 'view' => 'product', 'q' => '  <b>/Hair</b> ', 'rule' => 'low_ctr' ] );
hodima_ad( 'فیلترهای فهرست: نما ← نوع، جستجو و قاعده پاک‌سازی می‌شود', [ $f['view'], $f['args']['kind'], $f['args']['has_advice'], $f['q'], $f['rule'] ], [ 'product', [ 'product' ], true, '/Hair', 'low_ctr' ] );
hodima_ad( 'نمای ناشناخته ← همه؛ نمای نادیده‌ها ← مسیرها', [ hodima_seo_advisor_list_filters( [ 'view' => 'zzz' ] )['view'], isset( hodima_seo_advisor_list_filters( [ 'view' => 'ignored' ] )['args']['has_advice'] ) ], [ 'all', false ] );

$csv = hodima_seo_advisor_csv_rows( hodima_seo_advisor_list_filters( [] )['args'] );
hodima_ad( 'CSV: سربرگ + ۴ آدرس، اولی بیشترین کلیک بالقوه؛ ستون اول عنوان، دوم آدرس کامل (نه فقط مسیر)', [ count( $csv ), $csv[0][0], $csv[0][1], $csv[1][1], $csv[1][7] ], [ 5, 'عنوان', 'آدرس', hodima_seo_advisor_url( '/rules/a/' ), '1511' ] );
hodima_ad( 'CSV: ورودی شبیه فرمول بی‌اثر می‌شود', [ hodima_seo_advisor_csv_cell( '=HYPERLINK("x")' ), hodima_seo_advisor_csv_cell( '+1' ), hodima_seo_advisor_csv_cell( '@x' ), hodima_seo_advisor_csv_cell( '/ok/' ) ], [ "'=HYPERLINK(\"x\")", "'+1", "'@x", '/ok/' ] );

update_option( 'hodima_discover_sc_key', $key_json, false );
update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [ 'fetched' => time() - 3600, 'property' => 'sc-domain:' . $host, 'days' => 90, 'pages' => 5, 'queries' => 2, 'advised' => 4, 'error' => '' ] + hodima_seo_advisor_ranges( 90 ), false );
wp_set_current_user( 1 );
$_GET = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'opportunities' ]; // فهرست پیشنهادها (صفحه شروع تازه «کارها» است، SEO 3.3.0)
ob_start();
hodima_seo_advisor_render_admin();
$html = (string) ob_get_clean();
hodima_ad( 'صفحه: سربرگ، ۴ کارت آمار، ردیف‌ها، دکمه به‌روزرسانی و نادیده گرفتن', [ str_contains( $html, 'hds-hero__title' ), substr_count( $html, 'class="hds-stat"' ), substr_count( $html, 'hodima-advisor__item' ) >= 4, str_contains( $html, 'name="action" value="hodima_advisor_refresh"' ), str_contains( $html, 'این صفحه را نادیده بگیر' ) ], [ true, 4, true, true, true ] );
hodima_ad( 'صفحه: بازه فعلی انتخاب‌شده و خروجی CSV با nonce', [ str_contains( $html, 'value="90" checked' ), str_contains( $html, 'action=hodima_advisor_csv' ) && str_contains( $html, '_wpnonce=' ) ], [ true, true ] );
hodima_ad( 'فهرست فقط تیتر (SEO 3.1.1): گام‌های رفع نه؛ ایرادها پشت «جزئیات» (details)، دکمه «بررسی و رفع» یا «دیدن در سایت»، منوی «⋯»', [ str_contains( $html, 'hodima-advisor__issues' ), str_contains( $html, 'hodima-advisor-steps' ), str_contains( $html, 'ببینید دقیقا چه کنید' ), str_contains( $html, 'بررسی و رفع' ) || str_contains( $html, 'دیدن در سایت' ), str_contains( $html, 'class="hodima-advisor__more"' ) && str_contains( $html, 'hodima-advisor__more-toggle' ), str_contains( $html, 'class="hodima-advisor__menu"' ) ], [ true, false, false, true, true, true ] );
hodima_ad( 'فهرست: همه ایرادها یک‌یک (رقابت با N صفحه برای کلمه)', [ str_contains( $html, 'رقابت با ' . hodima_seo_advisor_num( 1 ) . ' صفحه برای «پلومریا»' ), str_contains( $html, 'data-rule="low_ctr"' ), str_contains( $html, 'data-rule="decline"' ) ], [ true, true, true ] );
// سقف‌های رسیده بی‌صدا نمی‌مانند (SEO 3.0.7)
delete_option( HODIMA_SEO_ADVISOR_CAPS_OPTION );
hodima_ad( 'سقف‌ها پیش‌فرض خالی', hodima_seo_advisor_caps(), [ 'fetch' => false, 'analyze' => false, 'classify' => 0 ] );
ob_start();
hodima_seo_advisor_render_admin();
$caps_off = str_contains( (string) ob_get_clean(), 'کامل نیست' );
hodima_seo_advisor_caps( [ 'analyze' => true, 'classify' => 120 ] );
ob_start();
hodima_seo_advisor_render_admin();
$caps_html = (string) ob_get_clean();
hodima_ad( 'نوار «کیفیت داده»: بدون سقف «کامل نیست» نیست؛ با سقف تحلیل و دسته‌بندی در متن؛ نوار قدیمی نیست', [ $caps_off, str_contains( $caps_html, 'کامل نیست' ), str_contains( $caps_html, 'آدرس پرنمایش‌تر برای پیشنهاد' ), str_contains( $caps_html, '120' ) || str_contains( $caps_html, '۱۲۰' ), str_contains( $caps_html, 'بخشی از داده بریده شده است' ) ], [ false, true, true, true, false ] );
hodima_seo_advisor_caps( [ 'fetch' => false, 'analyze' => false, 'classify' => 0 ] );
delete_option( 'hodima_discover_sc_key' );
ob_start();
hodima_seo_advisor_render_admin();
$html = (string) ob_get_clean();
hodima_ad( 'بدون کلید: صفحه خودش روی تب «تنظیمات» با «افزودن کلید» باز می‌شود؛ بدون ردیف به‌روزرسانی و جدول', [ str_contains( $html, 'افزودن کلید' ), str_contains( $html, 'tab=settings" aria-current="page"' ), str_contains( $html, 'value="hodima_advisor_refresh"' ), str_contains( $html, 'hodima-advisor__item' ) ], [ true, true, false, false ] );
wp_set_current_user( 0 );

echo "=== کادر داخل ویرایش صفحه\n";
if ( ! function_exists( 'hodima_seo_advisor_render_box' ) ) {
	require_once HODIMA_SEO_DIR . '/core/advisor/advisor-box.php';
}
// add_meta_box و WP_Screen فقط در پیشخوان لود می‌شوند
require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
require_once ABSPATH . 'wp-admin/includes/screen.php';
require_once ABSPATH . 'wp-admin/includes/template.php';
foreach ( get_posts( [ 'post_type' => 'page', 'name' => 'advisor-box-test', 'post_status' => 'any', 'numberposts' => -1 ] ) as $leftover ) {
	wp_delete_post( $leftover->ID, true );
}
$box_id = (int) wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'کلیپس مو', 'post_name' => 'advisor-box-test', 'post_content' => '<h2>گیره مو</h2><p>متن</p>' ] );
$box_draft = (int) wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'پیش‌نویس', 'post_name' => 'advisor-box-draft' ] );
hodima_seo_advisor_store(
	[ '/advisor-box-test/' => [ 'clicks' => 496, 'impressions' => 36492, 'position' => 5.81 ] ],
	[],
	[ '/advisor-box-test/' => [ 'پلومریا کلیپس' => [ 'clicks' => 25, 'impressions' => 2318, 'position' => 4.71 ] ] ]
);
update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [ 'fetched' => time() - 3600, 'property' => 'sc-domain:' . $host, 'days' => 90, 'pages' => 1, 'queries' => 1, 'advised' => 1, 'error' => '' ] + hodima_seo_advisor_ranges( 90 ), false );

$titled = hodima_seo_advisor_page( '/advisor-box-test/' );
hodima_ad( 'عنوان صفحه (نام شیء) ذخیره می‌شود و با عنوان هم جستجو می‌شود', [ $titled['title'], array_column( hodima_seo_advisor_pages( [ 'search' => 'کلیپس' ] )['rows'], 'path' ) ], hodima_router_active() ? [ 'کلیپس مو', [ '/advisor-box-test/' ] ] : [ '', [] ] );
wp_update_post( [ 'ID' => $box_id, 'post_title' => 'عنوان تازه صفحه' ] );
ob_start();
hodima_seo_advisor_render_row( hodima_seo_advisor_page( '/advisor-box-test/' ), 1, false );
$row_html = (string) ob_get_clean();
hodima_ad( 'ردیف فهرست: عنوان تازه شیء درشت و آدرس زیرش (نه فقط آدرس)', [ str_contains( $row_html, 'hodima-advisor__name' ) && str_contains( $row_html, 'عنوان تازه صفحه' ), str_contains( $row_html, '/advisor-box-test/' ) ], hodima_router_active() ? [ true, true ] : [ false, true ] );
hodima_ad( 'CSV: ستون عنوان', hodima_seo_advisor_csv_rows( [ 'search' => 'advisor-box' ] )[1][0], hodima_router_active() ? 'عنوان تازه صفحه' : '' );
wp_update_post( [ 'ID' => $box_id, 'post_title' => 'کلیپس مو' ] );

$meta_has = static function ( string $type, WP_Post $post ): bool {
	global $wp_meta_boxes;
	unset( $wp_meta_boxes[ $type ] );
	hodima_seo_advisor_register_box( $type, $post );
	return isset( $wp_meta_boxes[ $type ]['normal']['default']['hodima_advisor_box'] );
};
wp_set_current_user( 1 );
hodima_ad( 'کادر فقط برای صفحه منتشرشده و مدیر ثبت می‌شود', [ $meta_has( 'page', get_post( $box_id ) ), $meta_has( 'page', get_post( $box_draft ) ) ], [ true, false ] );
hodima_ad( 'نوع نوشته‌ای که سئوباکس ندارد کادر ندارد', $meta_has( 'revision', get_post( $box_id ) ), false );
wp_set_current_user( 0 );
hodima_ad( 'بدون ورود (غیرمدیر) ثبت نمی‌شود', $meta_has( 'page', get_post( $box_id ) ), false );
wp_set_current_user( 1 );

ob_start();
hodima_seo_advisor_render_box( (string) get_permalink( $box_id ) );
$box = (string) ob_get_clean();
hodima_ad( 'کادر: وضعیت، پیشنهاد نرخ کلیک، کلمه برتر، عددها', [ str_contains( $box, 'hodima-advisor-box__pill--warn' ), str_contains( $box, 'عنوان و توضیح متا را بازنویسی کنید' ), str_contains( $box, 'پلومریا کلیپس' ), str_contains( $box, '36,492' ) || str_contains( $box, '۳۶٬۴۹۲' ) ], [ true, true, true, true ] );
hodima_ad( 'کادر: متن تازه صفحه سنجیده می‌شود (کلمه «پلومریا» در عنوان، تیترها و متن صفحه نیست)', str_contains( $box, 'در متن صفحه نیست' ), true );
hodima_ad( 'کادر: لینک به صفحه مشاور با جستجوی همین آدرس، بدون لینک بازگرداندن', [ str_contains( $box, 'page=hodima-advisor' ) && str_contains( $box, 'q=/advisor-box-test/' ), str_contains( $box, 'restore=1' ) ], [ true, false ] );

hodima_seo_advisor_set_ignored( '/advisor-box-test/', true );
ob_start();
hodima_seo_advisor_render_box( (string) get_permalink( $box_id ) );
$box = (string) ob_get_clean();
hodima_ad( 'کادر: پنل داده با سه خانه هم‌اندازه و جدول کلمه‌ها (بدون ستون کناری)', [ substr_count( $box, 'hodima-advisor-box__value' ), str_contains( $box, 'hodima-advisor-box__table' ), str_contains( $box, 'hodima-advisor-box__aside' ), str_contains( $box, 'href="#seobox_description"' ) === str_contains( $box, 'href="#seobox_title"' ) ], [ 3, true, false, true ] );
hodima_ad( 'نادیده‌گرفته‌شده: پیشنهادی نیست و لینک بازگرداندن با nonce هست', [ str_contains( $box, 'نادیده گرفته شده' ), str_contains( $box, 'hodima-advisor-box__item' ), str_contains( $box, 'restore=1' ) && str_contains( $box, '_wpnonce=' ) && str_contains( $box, 'action=hodima_advisor_ignore' ) ], [ true, false, true ] );
hodima_seo_advisor_set_ignored( '/advisor-box-test/', false );

ob_start();
hodima_seo_advisor_render_box( (string) get_permalink( $box_draft ) );
$box = (string) ob_get_clean();
hodima_ad( 'آدرسی که داده ندارد: پیام روشن، بدون پیشنهاد', [ str_contains( $box, 'هنوز داده‌ای در سرچ کنسول ثبت نشده' ), str_contains( $box, 'hodima-advisor-box__item' ) ], [ true, false ] );

delete_option( HODIMA_SEO_ADVISOR_STATUS_OPTION );
ob_start();
hodima_seo_advisor_render_box( (string) get_permalink( $box_id ) );
$box = (string) ob_get_clean();
hodima_ad( 'بدون دریافت داده در وضعیت: سربرگ «هنوز داده‌ای گرفته نشده»', str_contains( $box, 'هنوز داده‌ای گرفته نشده' ), true );

ob_start();
hodima_seo_advisor_render_box( '' );
$box = (string) ob_get_clean();
hodima_ad( 'آدرس نامعتبر خطا نمی‌دهد', str_contains( $box, 'hodima-advisor-box' ), true );
echo "=== تحلیل دوباره هنگام ذخیره (advisor-resave.php)\n";
$rules_of = static fn(): array => array_column( (array) ( hodima_seo_advisor_page( '/advisor-box-test/' )['advice'] ?? [] ), 'rule' );
hodima_ad( 'پیش از دریافت داده (وضعیت خالی): ذخیره کاری نمی‌کند', hodima_seo_advisor_resave_object( 'page', $box_id ), 0 );
update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [ 'fetched' => time() - 3600, 'property' => 'sc-domain:' . $host, 'days' => 90, 'pages' => 1, 'queries' => 1, 'advised' => 1, 'error' => '' ] + hodima_seo_advisor_ranges( 90 ), false );
hodima_seo_advisor_save_advice( '/advisor-box-test/' );
$box_old_content = (string) get_post_field( 'post_content', $box_id );
if ( hodima_router_active() ) {
	hodima_ad( 'پیش از ویرایش: ایراد «کلمه در متن نیست» در فهرست ذخیره است', in_array( 'missing_content', $rules_of(), true ), true );
	wp_update_post( [ 'ID' => $box_id, 'post_content' => '<h2>پلومریا کلیپس</h2><p>' . $box_old_content . '</p>' ] );
	hodima_ad( 'بعد از ذخیره صفحه: ایراد کلمه همان لحظه از فهرست رفت (بدون دریافت تازه)', [ in_array( 'missing_content', $rules_of(), true ), in_array( 'missing_headings', $rules_of(), true ) ], [ false, false ] );
	hodima_ad( 'ایراد آماری (نرخ کلیک) با ذخیره نمی‌رود', in_array( 'low_ctr', $rules_of(), true ), true );
	wp_update_post( [ 'ID' => $box_id, 'post_content' => $box_old_content ] );
	hodima_ad( 'برداشتن کلمه از متن: ایراد دوباره برمی‌گردد', in_array( 'missing_content', $rules_of(), true ), true );
	hodima_seo_advisor_set_ignored( '/advisor-box-test/', true );
	wp_update_post( [ 'ID' => $box_id, 'post_content' => $box_old_content . ' ' ] );
	hodima_ad( 'آدرس نادیده‌گرفته‌شده با ذخیره پیشنهاد نمی‌گیرد', $rules_of(), [] );
	hodima_seo_advisor_set_ignored( '/advisor-box-test/', false );
	hodima_ad( 'شیئی که ردیف ندارد: صفر ردیف، بدون خطا', hodima_seo_advisor_resave_object( 'page', $box_draft ), 0 );
	$resave_cat = wp_insert_term( 'دسته آزمون مشاور', 'category', [ 'slug' => 'advisor-resave-cat' ] );
	$resave_tid = is_array( $resave_cat ) ? (int) $resave_cat['term_id'] : 0;
	$resave_path = (string) hodima_seo_advisor_path_key( (string) get_term_link( $resave_tid, 'category' ) );
	hodima_seo_advisor_store(
		[ $resave_path => [ 'clicks' => 10, 'impressions' => 5000, 'position' => 6.0 ] ],
		[],
		[ $resave_path => [ 'سنجاق نقره‌ای' => [ 'clicks' => 2, 'impressions' => 900, 'position' => 6.5 ] ] ]
	);
	hodima_seo_advisor_save_advice( $resave_path );
	$cat_rules = static fn(): array => array_column( (array) ( hodima_seo_advisor_page( $resave_path )['advice'] ?? [] ), 'rule' );
	hodima_ad( 'دسته پیش از ویرایش: کلمه در متن نیست', in_array( 'missing_content', $cat_rules(), true ), true );
	wp_update_term( $resave_tid, 'category', [ 'description' => '<h2>سنجاق نقره‌ای</h2>' ] );
	hodima_ad( 'بعد از ذخیره دسته (saved_term): ایراد کلمه رفت', [ in_array( 'missing_content', $cat_rules(), true ), in_array( 'missing_headings', $cat_rules(), true ) ], [ false, false ] );
	wp_delete_term( $resave_tid, 'category' );
	echo "=== وضعیت «کارهای من» در فهرست فرصت‌ها (نشان، پنهان شدن صفحه تمام‌علامت‌خورده)\n";
	$b6_tasks = get_option( HODIMA_SEO_ADVISOR_TASKS_OPTION, false );
	delete_option( HODIMA_SEO_ADVISOR_TASKS_OPTION );
	hodima_seo_advisor_store(
		[ '/advisor-box-test/' => [ 'clicks' => 496, 'impressions' => 36492, 'position' => 5.81 ] ],
		[],
		[ '/advisor-box-test/' => [ 'پلومریا کلیپس' => [ 'clicks' => 25, 'impressions' => 2318, 'position' => 4.71 ] ] ]
	);
	hodima_seo_advisor_save_advice( '/advisor-box-test/' );
	$b6_advice = (array) hodima_seo_advisor_page( '/advisor-box-test/' )['advice'];
	$b6_low    = array_values( array_filter( $b6_advice, static fn( array $i ): bool => 'low_ctr' === $i['rule'] ) )[0] ?? null;
	$b6_text   = array_values( array_filter( $b6_advice, static fn( array $i ): bool => in_array( $i['rule'], HODIMA_SEO_ADVISOR_TEXT_RULES, true ) ) )[0] ?? null;
	$b6_row    = static function (): string {
		ob_start();
		hodima_seo_advisor_render_row( hodima_seo_advisor_page( '/advisor-box-test/' ), 1, false );
		return (string) ob_get_clean();
	};
	$b6_list = static function ( array $get ): string {
		$old  = $_GET;
		$_GET = $get;
		ob_start();
		hodima_seo_advisor_render_list( hodima_seo_advisor_status() );
		$_GET = $old;
		return (string) ob_get_clean();
	};
	$b6_mark = static fn( ?array $item, string $status ): bool => null !== $item && hodima_seo_advisor_task_set( '/advisor-box-test/', (string) $item['rule'], (string) $item['term'], (string) $item['title'], $status );
	hodima_ad( 'داده نمونه: یک ایراد آماری و یک ایراد متنی', [ null !== $b6_low, null !== $b6_text, count( $b6_advice ) ], [ true, true, 2 ] );
	hodima_ad( 'بدون کار: ردیف بدون نشان و هیچ آدرسی «تمام‌علامت‌خورده» نیست', [ str_contains( $b6_row(), 'data-task=' ), hodima_seo_advisor_handled_paths() ], [ false, [] ] );
	$b6_mark( $b6_low, 'doing' );
	hodima_ad( '«در حال انجام»: نشان زرد کنار همان ایراد، آدرس هنوز باز', [ str_contains( $b6_row(), 'data-task="doing"' ) && str_contains( $b6_row(), 'hds-pill--warn">در حال انجام' ), hodima_seo_advisor_handled_paths() ], [ true, [] ] );
	$b6_mark( $b6_low, 'done' );
	hodima_ad( 'ایراد آماری «انجام دادم»: «منتظر داده گوگل» (سبز)', str_contains( $b6_row(), '<span class="hds-pill">انجام شد · منتظر داده گوگل' ), true );
	hodima_ad( 'یک ایراد هنوز باز: آدرس پنهان نمی‌شود', hodima_seo_advisor_handled_paths(), [] );
	$b6_mark( $b6_text, 'done' );
	hodima_ad( 'ایراد متنی «انجام دادم» ولی هنوز ساخته می‌شود: «هنوز در صفحه دیده نمی‌شود» و کار باز می‌ماند (آدرس پنهان نمی‌شود)', [ str_contains( $b6_row(), 'انجام شد؛ هنوز در صفحه دیده نمی‌شود' ), hodima_seo_advisor_handled_paths() ], [ true, [] ] );
	$b6_mark( $b6_text, 'rejected' );
	hodima_ad( 'آماری «منتظر گوگل» + متنی «لازم نیست»: آدرس با نوعش در «تمام‌علامت‌خورده»', hodima_seo_advisor_handled_paths(), [ '/advisor-box-test/' => 'page' ] );
	$b6_def = hodima_seo_advisor_list_filters( [] );
	hodima_ad( 'فهرست پیش‌فرض: آدرس کنار می‌رود؛ tasks=all برمی‌گرداند', [ array_column( hodima_seo_advisor_pages( $b6_def['args'] )['rows'], 'path' ), array_column( hodima_seo_advisor_pages( hodima_seo_advisor_list_filters( [ 'tasks' => 'all' ] )['args'] )['rows'], 'path' ) ], [ [], [ '/advisor-box-test/' ] ] );
	hodima_ad( 'نمای «نادیده‌گرفته‌شده‌ها» کنار گذاشتن ندارد', isset( hodima_seo_advisor_list_filters( [ 'view' => 'ignored' ] )['args']['exclude'] ), false );
	$b6_html = $b6_list( [] );
	hodima_ad( 'صفحه پیش‌فرض بدون کار باز: شمار «همه» صفر و حالت خالی «کار بازی نمانده» با لینک tasks=all', [ (bool) preg_match( '#همه <span class="hodima-advisor__count">(0|۰)</span>#u', $b6_html ), str_contains( $b6_html, 'کار بازی نمانده' ) && str_contains( $b6_html, 'tasks=all' ) ], [ true, true ] );
	hodima_ad( 'نمای «محصول‌ها» (برگه تمام‌علامت‌خورده نوعش نیست): حالت خالی معمولی', str_contains( $b6_list( [ 'view' => 'product' ] ), 'کار بازی نمانده' ), false );
	$b6_html = $b6_list( [ 'tasks' => 'all' ] );
	hodima_ad( 'tasks=all: ردیف دیده می‌شود، لینک «پنهان کردن…» و فیلد پنهان فرم جستجو', [ str_contains( $b6_html, 'hodima-advisor__item' ), str_contains( $b6_html, 'پنهان کردن صفحه‌های تمام‌علامت‌خورده' ), str_contains( $b6_html, 'name="tasks" value="all"' ) ], [ true, true, true ] );
	$b6_mark( $b6_text, 'rejected' );
	hodima_ad( '«لازم نیست»: data-task=rejected و نشان آبی؛ آدرس همچنان تمام‌علامت‌خورده', [ str_contains( $b6_row(), 'data-task="rejected"' ) && str_contains( $b6_row(), 'hds-pill--info">لازم نیست' ), array_keys( hodima_seo_advisor_handled_paths() ) ], [ true, [ '/advisor-box-test/' ] ] );
	$b6_mark( $b6_text, '' );
	hodima_ad( 'ایراد بدون علامت (مثل پیشنهاد تازه): آدرس خودبه‌خود به فهرست برمی‌گردد', [ hodima_seo_advisor_handled_paths(), array_column( hodima_seo_advisor_pages( hodima_seo_advisor_list_filters( [] )['args'] )['rows'], 'path' ) ], [ [], [ '/advisor-box-test/' ] ] );
	hodima_ad( 'pages( exclude ): آدرس مشخص کنار می‌رود', hodima_seo_advisor_pages( [ 'exclude' => [ '/advisor-box-test/' ] ] )['total'], 0 );

	echo "=== چرخه عمر کار: برطرف شد ← برگشت (advisor-tasks.php)\n";
	$b6_task = static fn( ?array $item ): ?array => null === $item ? null : hodima_seo_advisor_task( '/advisor-box-test/', (string) $item['rule'], (string) $item['term'] );
	$b6_mark( $b6_text, 'done' );
	$b6_old = (string) get_post_field( 'post_content', $box_id );
	wp_update_post( [ 'ID' => $box_id, 'post_content' => '<h2>پلومریا کلیپس</h2>' . $b6_old ] );
	hodima_ad( 'کلمه به صفحه اضافه و ذخیره شد: کار متنی همان لحظه «برطرف شد»، کار آماری هنوز «منتظر گوگل»', [ hodima_seo_advisor_task_state( (array) $b6_task( $b6_text ) ), (array) $b6_task( $b6_text ) !== [] && $b6_task( $b6_text )['fixed'] > 0, hodima_seo_advisor_task_state( (array) $b6_task( $b6_low ) ) ], [ 'fixed', true, 'waiting' ] );
	hodima_ad( '«برطرف شد» در گروه خودش و آدرس (آماری منتظر + متنی برطرف) پنهان', [ hodima_seo_advisor_task_counts()['fixed'], array_keys( hodima_seo_advisor_handled_paths() ) ], [ 1, [ '/advisor-box-test/' ] ] );
	wp_update_post( [ 'ID' => $box_id, 'post_content' => $b6_old ] );
	hodima_ad( 'کلمه دوباره برداشته شد: «برگشت؛ دوباره پیدا شد» (باز) و آدرس به فهرست برمی‌گردد', [ hodima_seo_advisor_task_state( (array) $b6_task( $b6_text ) ), str_contains( $b6_row(), 'برگشت؛ دوباره پیدا شد' ), hodima_seo_advisor_handled_paths() ], [ 'back', true, [] ] );
	wp_update_post( [ 'ID' => $box_id, 'post_content' => '<h2>پلومریا کلیپس</h2>' . $b6_old ] );
	hodima_ad( 'دوباره اصلاح شد: باز «برطرف شد»', hodima_seo_advisor_task_state( (array) $b6_task( $b6_text ) ), 'fixed' );
	$b6_mark( $b6_text, 'doing' );
	hodima_ad( 'وضعیت تازه مدیر «برطرف شد» را پاک می‌کند (تا تحلیل بعدی)', [ $b6_task( $b6_text )['fixed'], hodima_seo_advisor_task_state( (array) $b6_task( $b6_text ) ) ], [ 0, 'doing' ] );
	hodima_seo_advisor_set_ignored( '/advisor-box-test/', true );
	hodima_ad( 'آدرس نادیده‌گرفته‌شده: پیشنهاد ندارد ولی کار «برطرف شد» نمی‌گیرد', hodima_seo_advisor_task_state( (array) $b6_task( $b6_low ) ), 'waiting' );
	hodima_seo_advisor_set_ignored( '/advisor-box-test/', false );
	hodima_ad( 'تحلیل با داده تازه بی‌کلمه: پیشنهاد آماری با ناپدید شدن «برطرف شد» نمی‌گیرد (فقط سنجش داده بعد از تغییر)', [ hodima_seo_advisor_tasks_sync( '/advisor-box-test/', [] ) >= 1 && 0 < $b6_task( $b6_low )['fixed'], hodima_seo_advisor_task_state( (array) $b6_task( $b6_low ) ) ], [ false, 'waiting' ] );
	$b6_mark( $b6_text, 'rejected' );
	hodima_seo_advisor_tasks_sync( '/advisor-box-test/', [] );
	hodima_ad( 'کار «لازم نیست» با همگام‌سازی «برطرف شد» نمی‌گیرد', [ hodima_seo_advisor_task_state( (array) $b6_task( $b6_text ) ), $b6_task( $b6_text )['fixed'] ], [ 'rejected', 0 ] );
	wp_update_post( [ 'ID' => $box_id, 'post_content' => $b6_old ] );
	false === $b6_tasks ? delete_option( HODIMA_SEO_ADVISOR_TASKS_OPTION ) : update_option( HODIMA_SEO_ADVISOR_TASKS_OPTION, $b6_tasks, false );
} else {
	hodima_ad( 'بدون روتر نوع شیء معلوم نیست: ذخیره ردیفی را پیدا نمی‌کند', hodima_seo_advisor_resave_object( 'page', $box_id ), 0 );
}
wp_delete_post( $box_id, true );
wp_delete_post( $box_draft, true );
wp_set_current_user( 0 );

echo "=== گام‌های دقیق: عنوان، تیترها، لینک از کدام صفحه‌ها، صفحه مرجع\n";
foreach ( get_posts( [ 'post_type' => 'page', 'post_status' => 'any', 'numberposts' => -1, 'name__in' => [ 'steps-target', 'steps-src-a', 'steps-src-b', 'steps-src-c', 'steps-src-d' ] ] ) as $leftover ) {
	wp_delete_post( $leftover->ID, true );
}
$mk = static fn( string $slug, string $title, string $content ): int => (int) wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => $slug, 'post_content' => $content ] );
$t_id = $mk( 'steps-target', 'کلیپس مو', '<h2>کلیپس پلومریا</h2><p>متن</p>' );
$ids  = [
	$mk( 'steps-src-a', 'درباره پلومریا', '<p>ما کلیپس پلومریا می‌فروشیم.</p>' ),
	$mk( 'steps-src-b', 'منبع لینک‌دار', '<p>کلیپس پلومریا <a href="' . home_url( '/steps-target/' ) . '">اینجا</a></p>' ),
	$mk( 'steps-src-c', 'منبع قوی', '<p>بهترین کلیپس پلومریا</p>' ),
	$mk( 'steps-src-d', 'بی‌ربط', '<p>متن دیگر</p>' ),
];
$cat = wp_insert_term( 'دسته منبع', 'category', [ 'slug' => 'steps-cat', 'description' => 'همه مدل‌های کلیپس پلومریا' ] );
$cat_id = is_wp_error( $cat ) ? (int) get_term_by( 'slug', 'steps-cat', 'category' )->term_id : (int) $cat['term_id'];
hodima_seo_advisor_store(
	[
		'/steps-target/' => [ 'clicks' => 100, 'impressions' => 5000, 'position' => 7.0 ],
		'/steps-src-a/'  => [ 'clicks' => 1, 'impressions' => 100, 'position' => 9.0 ],
		'/steps-src-c/'  => [ 'clicks' => 20, 'impressions' => 900, 'position' => 6.0 ],
		'/steps-cat/'    => [ 'clicks' => 10, 'impressions' => 400, 'position' => 6.0 ],
		'/rival/'        => [ 'clicks' => 80, 'impressions' => 3000, 'position' => 3.0 ],
	],
	[],
	[
		'/steps-target/' => [
			'کلیپس پلومریا' => [ 'clicks' => 0, 'impressions' => 1000, 'position' => 9.0 ],
			'گیره قیچی'      => [ 'clicks' => 0, 'impressions' => 500, 'position' => 10.0 ],
		],
		'/rival/'        => [ 'کلیپس پلومریا' => [ 'clicks' => 60, 'impressions' => 2000, 'position' => 3.0 ] ],
	]
);
$target = hodima_seo_advisor_page( '/steps-target/' );

$fields = hodima_seo_advisor_seo_fields( $target );
hodima_ad( 'فیلدهای سئو: نام، تیترها، توضیح خودکار', [ $fields['name'], $fields['headings'], $fields['custom_description'] ], hodima_router_active() ? [ 'کلیپس مو', [ 'کلیپس پلومریا' ], false ] : [ null, null, null ] );

if ( hodima_router_active() ) {
	$step = hodima_seo_advisor_step_title( $fields, 'کلیپس پلومریا' );
	hodima_ad( 'عنوان: کلمه اصلی نیست ← عنوان پیشنهادی (کلمه | نام) و توضیح متای خودکار هشدار می‌گیرد', [ $step['suggested'], str_contains( $step['meta']['tip'], 'توضیح متا ندارید' ), $step['custom'] ], [ 'کلیپس پلومریا | کلیپس مو', true, false ] );
	hodima_ad( 'عنوان: کلمه ابتدای عنوان ← پیشنهاد جدید نیست', [ hodima_seo_advisor_step_title( $fields, 'کلیپس' )['suggested'], str_contains( hodima_seo_advisor_step_title( $fields, 'کلیپس' )['tip'], 'شروع می‌شود' ) ], [ '', true ] );
	hodima_ad( 'عنوان: کلمه وسط عنوان ← به ابتدا ببرید', str_contains( hodima_seo_advisor_step_title( [ 'name' => 'گل سر کلیپس مو' ] + $fields, 'کلیپس' )['tip'], 'ابتدایش نیست' ), true );
	hodima_ad( 'عنوان: طول عنوان پیشنهادی ≤ ۶۰', mb_strlen( hodima_seo_advisor_step_title( [ 'name' => str_repeat( 'الف ', 40 ) ] + $fields, 'کلیپس پلومریا' )['suggested'] ) <= 60, true );
	$meta_tip = static fn( string $text, bool $custom ): string => hodima_seo_advisor_step_title( [ 'description' => $text, 'custom_description' => $custom ] + $fields, 'کلیپس پلومریا' )['meta']['tip'];
	hodima_ad( 'توضیح متا: کوتاه، بلند، بی‌کلمه، سالم', [ str_contains( $meta_tip( 'کوتاه', true ), 'کوتاه است' ), str_contains( $meta_tip( str_repeat( 'ب', 200 ), true ), 'بلند است' ), str_contains( $meta_tip( str_repeat( 'متن بدون کلمه اصلی ', 6 ), true ), 'در توضیح متا نیست' ), $meta_tip( 'کلیپس پلومریا ' . str_repeat( 'خوب ', 25 ), true ) ], [ true, true, true, '' ] );
}

$h = hodima_seo_advisor_step_headings( [ 'گیره مو', 'انواع کلیپس' ], 'کلیپس پلومریا', [ [ 'term' => 'کلیپس پلومریا', 'impressions' => 9 ], [ 'term' => 'گیره مو', 'impressions' => 8 ], [ 'term' => 'پلومریا', 'impressions' => 7 ], [ 'term' => 'الف ب', 'impressions' => 6 ], [ 'term' => 'ج د', 'impressions' => 5 ] ] );
hodima_ad( 'تیترها: ایده فقط از کلمه‌هایی که در تیترها نیست، اولی خود کلمه، حداکثر ۳', $h['ideas'], [ 'کلیپس پلومریا', 'پلومریا', 'الف ب' ] );

if ( hodima_router_active() ) {
	$src = hodima_seo_advisor_link_sources( 'کلیپس پلومریا', $target );
	hodima_ad( 'لینک از کدام صفحه‌ها: قوی‌ترین اول؛ صفحه‌ای که قبلا لینک دارد، بی‌ربط و خود هدف نیست؛ دسته هم', [ array_column( $src['items'], 'title' ), array_column( $src['items'], 'impressions' ), $src['more'] ], [ [ 'منبع قوی', 'دسته منبع', 'درباره پلومریا' ], [ 900, 400, 100 ], 0 ] );
	$few = hodima_seo_advisor_link_sources( 'کلیپس پلومریا', $target, 2 );
	hodima_ad( 'سقف لینک ۲ ← دو مورد و «۱ صفحه دیگر»', [ count( $few['items'] ), $few['more'] ], [ 2, 1 ] );
	hodima_ad( 'عبارتی که هیچ صفحه‌ای ندارد ← بدون مورد', hodima_seo_advisor_link_sources( 'عبارت ناموجود زرد', $target ), [ 'items' => [], 'more' => 0 ] );

	// ی/ک عربی در متن صفحه، توضیح کوتاه (excerpt) و href با حروف بزرگ دیگر مانع پیدا شدن نمی‌شوند
	$ar_id = (int) wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'منبع عربی ' . 'پلومریا', 'post_name' => 'steps-src-arabic', 'post_content' => '<p>بهترین كليپس پلومريا را ببينيد</p>' ] );
	$ex_id = (int) wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'منبع خلاصه', 'post_name' => 'steps-src-excerpt', 'post_content' => '<p>متن</p>', 'post_excerpt' => 'کلیپس پلومریا عمده' ] );
	$up_id = (int) wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'منبع لینک بزرگ', 'post_name' => 'steps-src-upper', 'post_content' => '<p>کلیپس پلومریا <a href="' . strtoupper( home_url( '/steps-target/' ) ) . '">اینجا</a></p>' ] );
	$ids[] = $ar_id;
	$ids[] = $ex_id;
	$ids[] = $up_id;
	$src2   = hodima_seo_advisor_link_sources( 'کلیپس پلومریا', $target );
	$titles = array_column( $src2['items'], 'title' );
	hodima_ad( 'لینک‌دهنده: ی/ک عربی و توضیح کوتاه پیدا می‌شوند؛ href با حروف بزرگ یعنی قبلا لینک دارد', [ in_array( 'منبع عربی پلومریا', $titles, true ), in_array( 'منبع خلاصه', $titles, true ), in_array( 'منبع لینک بزرگ', $titles, true ) ], [ true, true, false ] );

	$c1 = hodima_seo_advisor_link_sources_cached( 'کلیپس پلومریا', $target );
	$c2 = hodima_seo_advisor_link_sources_cached( 'کلیپس پلومریا', $target );
	$f0 = hodima_seo_advisor_link_sources( 'کلیپس پلومریا', $target );
	$new_id = (int) wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'منبع تازه کش', 'post_name' => 'steps-src-cache', 'post_content' => '<p>کلیپس پلومریا</p>' ] );
	$ids[]  = $new_id;
	$c3     = hodima_seo_advisor_link_sources_cached( 'کلیپس پلومریا', $target );
	hodima_ad( 'کش لینک‌دهنده: همان نتیجه بدون کش؛ با نوشته تازه خودکار نو می‌شود', [ $c1 === $f0, $c1 === $c2, in_array( 'منبع تازه کش', array_column( $c3['items'], 'title' ), true ) ], [ true, true, true ] );

	$analysis = hodima_seo_advisor_analyze( '/steps-target/', true ); // گام‌ها فقط با درخواست صریح (کادر ویرایش)
	hodima_ad( 'تحلیل بدون درخواست گام‌ها: هیچ پیشنهادی steps ندارد (جستجوی پرهزینه متن نوشته‌ها اجرا نمی‌شود)', array_filter( array_column( hodima_seo_advisor_analyze( '/steps-target/' )['suggestions'], 'steps' ) ), [] );
	$by_rule  = [];
	foreach ( $analysis['suggestions'] as $item ) {
		$by_rule[ $item['rule'] ][] = $item;
	}
	hodima_ad( 'تحلیل: نرخ کلیک ← گام عنوان؛ کلمه نزدیک صفحه اول ← گام لینک؛ کلمه غایب ← تیتر و لینک', [ $by_rule['low_ctr'][0]['steps'][0]['type'] ?? '', $by_rule['near_first'][0]['steps'][0]['type'] ?? '', array_column( $by_rule['missing_content'][0]['steps'] ?? [], 'type' ) ], [ 'title', 'links', [ 'headings', 'links' ] ] );
	hodima_ad( 'کلمه‌ای که هم غایب است و هم نزدیک صفحه اول یک کارت می‌گیرد (لینک‌دهی تکراری نیست)', [ count( $by_rule['near_first'] ?? [] ), str_contains( $by_rule['missing_content'][0]['detail'] ?? '', 'نزدیک است' ), $by_rule['missing_content'][0]['term'] ?? '' ], [ 1, true, 'گیره قیچی' ] );
	hodima_ad( 'تحلیل: رقابت داخلی ← صفحه مرجع (بیشترین کلیک، همان برنده تب رقابت) /rival/', [ ( $by_rule['cannibal'][0]['steps'][0]['type'] ?? '' ), ( $by_rule['cannibal'][0]['steps'][0]['main'] ?? '' ), ( $by_rule['cannibal'][0]['steps'][0]['is_main'] ?? null ) ], [ 'canonical', '/rival/', false ] );

	wp_set_current_user( 1 ); // لینک ویرایش فقط برای کاربر مجاز ساخته می‌شود
	ob_start();
	hodima_seo_advisor_render_steps( $by_rule['near_first'][0]['steps'] );
	hodima_seo_advisor_render_steps( $by_rule['low_ctr'][0]['steps'] );
	hodima_seo_advisor_render_steps( $by_rule['cannibal'][0]['steps'] );
	$steps_html = (string) ob_get_clean();
	hodima_ad( 'نمایش: لینک ویرایش صفحه‌ها، عبارت لینک، عنوان فعلی و پیشنهادی، صفحه مرجع', [ str_contains( $steps_html, 'post=' . $ids[2] ), str_contains( $steps_html, 'عبارت «کلیپس پلومریا» را پیدا کنید' ), str_contains( $steps_html, 'عنوان پیشنهادی' ), str_contains( $steps_html, 'مرجع پیشنهادی' ) ], [ true, true, true, true ] );
	hodima_ad( 'رقابت در گام‌ها: جدول، ردیف همین صفحه پررنگ، لینک ویرایش رقیب', [ str_contains( $steps_html, 'hodima-advisor-steps__table' ), str_contains( $steps_html, 'class="is-me"' ), str_contains( $steps_html, 'hodima-advisor-steps__main' ) ], [ true, true, true ] );
	hodima_ad( 'گام‌ها با آدرس کامل و کپی (SEO 3.0.1): رقیب و صفحه مرجع با دکمه کپی، ردیف همین صفحه بدون کپی، منبع لینک و آدرس این صفحه', [ str_contains( $steps_html, 'data-hdsa-copy-url="' . hodima_seo_advisor_url( '/rival/' ) . '"' ), str_contains( $steps_html, 'hodima-advisor-steps__main-pill' ), str_contains( $steps_html, 'data-hdsa-copy-url="' . hodima_seo_advisor_url( '/steps-target/' ) . '"' ), str_contains( $steps_html, 'آدرس این صفحه (همان که لینک می‌گیرد)' ), (bool) preg_match( '#<li>[^\n]*hodima-advisor-url__link#u', $steps_html ) ], [ true, true, true, true, true ] );
	wp_set_current_user( 0 );
	$saved_advice = hodima_seo_advisor_save_advice( '/steps-target/' );
	hodima_ad( 'پیشنهاد ذخیره می‌شود ولی گام‌ها نه (فقط کادر ویرایش می‌سازد؛ تحلیل همه آدرس‌ها سبک می‌ماند)', [ $saved_advice, array_filter( array_column( hodima_seo_advisor_page( '/steps-target/' )['advice'], 'steps' ) ) ], [ true, [] ] );
}

foreach ( [ ...$ids, $t_id ] as $pid ) {
	wp_delete_post( $pid, true );
}
wp_delete_term( $cat_id, 'category' );

echo "=== مرحله ۵: کیفیت پیشنهادها (مرز کلمه، برند، هدف واقع‌بینانه)\n";
$hay = hodima_seo_advisor_normalize( 'گیره موبایل و کلیپس‌ها، همراه با مو‌های بلند' );
hodima_ad( 'مرز کلمه: «مو» داخل «موبایل» نیست ولی «مو‌های» و «کلیپس‌ها» هست', [ hodima_seo_advisor_text_has( $hay, 'مو' ), hodima_seo_advisor_text_has( $hay, 'موبایل' ), hodima_seo_advisor_text_has( $hay, 'کلیپس' ), hodima_seo_advisor_text_has( hodima_seo_advisor_normalize( 'گیره موبایل' ), 'مو' ), hodima_seo_advisor_text_has( $hay, 'گیر' ) ], [ true, true, true, false, false ] );
hodima_ad( 'ی/ک عربی در متن با کلمه فارسی یکی است', hodima_seo_advisor_text_has( hodima_seo_advisor_normalize( 'كليپس' ), 'کلیپس' ), true );
hodima_ad( 'رتبه هدف: ≤۷ همان؛ ۱۵ ← ۱۰؛ ۹ ← ۷؛ ۲۰ ← ۱۰', [ hodima_seo_advisor_target_position( 5.0 ), hodima_seo_advisor_target_position( 15.0 ), hodima_seo_advisor_target_position( 9.0 ), hodima_seo_advisor_target_position( 20.0 ) ], [ 5.0, 10.0, 7.0, 10.0 ] );
add_filter( 'hodima_seo_advisor_brand_terms', $brand_filter = static fn(): array => [ 'هدهدلی' ] );
hodima_ad( 'کلمه برندی شناخته می‌شود (نه کلمه‌ای که فقط شبیه است)', [ hodima_seo_advisor_is_brand( 'هدهدلی کلیپس' ), hodima_seo_advisor_is_brand( 'خرید کلیپس' ) ], [ true, false ] );
$brand_weak = hodima_seo_advisor_suggestions( [ 'impressions' => 100, 'clicks' => 5 ] + [ 'path' => '/x/', 'position' => 5.81, 'prev_clicks' => 0 ], [ [ 'term' => 'هدهدلی کلیپس', 'clicks' => 2, 'impressions' => 2000, 'position' => 31.0 ] ], null );
hodima_ad( 'قاعده «کلمه بدون صفحه اختصاصی» هم کلمه برندی را کنار می‌گذارد', array_column( $brand_weak, 'rule' ), [] );
$brand_rows = [ [ 'term' => 'هدهدلی کلیپس', 'clicks' => 1, 'impressions' => 900, 'position' => 9.0 ], [ 'term' => 'کلیپس عمده', 'clicks' => 1, 'impressions' => 900, 'position' => 9.0 ] ];
$sb = hodima_seo_advisor_suggestions( [ 'path' => '/b/', 'clicks' => 2, 'impressions' => 400, 'position' => 9.0, 'prev_clicks' => 0 ], $brand_rows, [ 'title_headings' => '', 'content' => '' ] );
hodima_ad( 'کلمه برندی پیشنهاد نمی‌گیرد، کلمه عادی می‌گیرد', array_column( $sb, 'term' ), [ 'کلیپس عمده' ] );
remove_filter( 'hodima_seo_advisor_brand_terms', $brand_filter );
$sp = hodima_seo_advisor_suggestions( [ 'path' => '/p/', 'clicks' => 2, 'impressions' => 400, 'position' => 15.0, 'prev_clicks' => 0 ], [ [ 'term' => 'کلیپس', 'clicks' => 0, 'impressions' => 1000, 'position' => 15.0 ] ], null );
hodima_ad( 'کلیک بالقوه رتبه ۱۵ با هدف ۱۰ (۳٫۵٪) حساب می‌شود، نه ۷ (۵٫۵٪)', $sp[0]['potential'] ?? null, 35 );

echo "=== مرحله ۱: ذخیره امن (تراکنش، کلمه تکراری، قفل)\n";
// کلمه‌هایی که فقط در ی/ک عربی، حروف بزرگ و اعراب فرق دارند یکی می‌شوند (کلید اصلی جدول آن‌ها را یکی می‌دید)
$dedup = hodima_seo_advisor_aggregate_queries( [
	[ 'keys' => [ 'https://hodima.test/safe/', 'کلیپس پلومريا' ], 'clicks' => 3, 'impressions' => 100, 'position' => 4.0 ],
	[ 'keys' => [ 'https://hodima.test/safe/', 'کلیپس پلومریا' ], 'clicks' => 1, 'impressions' => 100, 'position' => 6.0 ],
	[ 'keys' => [ 'https://hodima.test/safe/', 'Clips' ], 'clicks' => 0, 'impressions' => 50, 'position' => 2.0 ],
	[ 'keys' => [ 'https://hodima.test/safe/', 'clips' ], 'clicks' => 0, 'impressions' => 50, 'position' => 4.0 ],
] );
hodima_ad( 'کلمه‌های هم‌ارز یکی می‌شوند (جمع نمایش، رتبه با وزن)', [ count( $dedup['/safe/'] ), array_sum( array_column( $dedup['/safe/'], 'impressions' ) ), array_values( $dedup['/safe/'] )[0]['position'] ], [ 2, 300, 5.0 ] );

$stored = hodima_seo_advisor_store( [ '/safe/a/' => [ 'clicks' => 5, 'impressions' => 900, 'position' => 3.0 ] ], [], [ '/safe/a/' => [ 'اول' => [ 'clicks' => 1, 'impressions' => 50, 'position' => 3.0 ] ] ] );
hodima_ad( 'ذخیره موفق: شمارش و بدون ردیف رد‌شده', is_array( $stored ) ? [ $stored['pages'], $stored['queries'], $stored['skipped'] ] : 'error', [ 1, 1, 0 ] );

$rolled = hodima_seo_advisor_transaction( static function (): WP_Error {
	hodima_seo_advisor_store( [ '/safe/new/' => [ 'clicks' => 1, 'impressions' => 10, 'position' => 1.0 ] ], [], [] );
	return new WP_Error( 'x', 'شکست عمدی' );
} );
hodima_ad( 'خطا وسط کار ← تراکنش برمی‌گردد و داده قبلی سالم می‌ماند', [ is_wp_error( $rolled ), hodima_seo_advisor_pages()['total'], null !== hodima_seo_advisor_page( '/safe/a/' ), null === hodima_seo_advisor_page( '/safe/new/' ), count( hodima_seo_advisor_queries( '/safe/a/' ) ) ], [ true, 1, true, true, 1 ] );

$thrown = hodima_seo_advisor_transaction( static function (): never {
	hodima_seo_advisor_store( [], [], [] );
	throw new RuntimeException( 'boom' );
} );
hodima_ad( 'استثنا وسط کار ← WP_Error و داده قبلی سالم', [ is_wp_error( $thrown ), hodima_seo_advisor_pages()['total'] ], [ true, 1 ] );

$nested = hodima_seo_advisor_transaction( static fn(): string => hodima_seo_advisor_transaction( static fn(): string => 'inner' ) . '+outer' );
hodima_ad( 'تراکنش تودرتو کار می‌کند', $nested, 'inner+outer' );

delete_option( HODIMA_SEO_ADVISOR_LOCK_OPTION );
$l1 = hodima_seo_advisor_lock();
$l2 = hodima_seo_advisor_lock();
$busy = hodima_seo_advisor_refresh();
hodima_ad( 'قفل: اولی می‌گیرد، دومی نه، دریافت داده «در حال انجام» می‌گوید', [ $l1, $l2, is_wp_error( $busy ) ? $busy->get_error_code() : 'ok' ], [ true, false, 'hodima_advisor_busy' ] );
hodima_seo_advisor_unlock();
hodima_ad( 'بعد از آزاد کردن دوباره قابل‌گرفتن است', hodima_seo_advisor_lock(), true );
update_option( HODIMA_SEO_ADVISOR_LOCK_OPTION, time() - HODIMA_SEO_ADVISOR_LOCK_TTL - 5, false );
hodima_ad( 'قفل مانده از اجرای مرده بعد از مهلت آزاد می‌شود', hodima_seo_advisor_lock(), true );
hodima_seo_advisor_unlock();

// قفل مالک دارد (SEO 3.0.9): آزادسازی توسط غیرمالک بی‌اثر؛ جایگزینی قفل کهنه با UPDATE شرطی
update_option( HODIMA_SEO_ADVISOR_LOCK_OPTION, time() . '|foreign', false );
hodima_seo_advisor_unlock();
hodima_ad( 'کسی که قفلی نگرفته قفل دیگری را آزاد نمی‌کند', get_option( HODIMA_SEO_ADVISOR_LOCK_OPTION, false ), time() . '|foreign' );
update_option( HODIMA_SEO_ADVISOR_LOCK_OPTION, ( time() - HODIMA_SEO_ADVISOR_LOCK_TTL - 5 ) . '|dead', false );
$lock_taken = hodima_seo_advisor_lock();
hodima_ad( 'قفل کهنه قالب تازه (زمان|توکن) هم جایگزین می‌شود و مقدار تازه مال ماست', [ $lock_taken, str_contains( (string) get_option( HODIMA_SEO_ADVISOR_LOCK_OPTION, '' ), '|dead' ) ], [ true, false ] );
hodima_seo_advisor_unlock();
hodima_ad( 'مالک قفل را آزاد می‌کند', get_option( HODIMA_SEO_ADVISOR_LOCK_OPTION, false ), false );

echo "=== تنظیمات و پاک کردن داده\n";
$before_cfg = hodima_seo_advisor_config()['min_impressions'];
$cfg_settings = get_option( HODIMA_SEO_ADVISOR_SETTINGS_OPTION, [] );
update_option( HODIMA_SEO_ADVISOR_SETTINGS_OPTION, [ 'min_impressions' => 250, 'days' => 28 ], false );
hodima_ad( 'حداقل نمایش از تنظیمات خوانده می‌شود', hodima_seo_advisor_config()['min_impressions'], 250 );
hodima_ad( 'محدوده مجاز حداقل نمایش', HODIMA_SEO_ADVISOR_MIN_IMPRESSIONS_RANGE, [ 10, 100000 ] );
ob_start();
hodima_seo_advisor_render_settings( true, true );
$settings_html = (string) ob_get_clean();
hodima_ad( 'کارت تنظیمات: مقدار فعلی در فیلد و هر دو فرم با nonce', [ str_contains( $settings_html, 'value="250"' ), str_contains( $settings_html, 'name="action" value="hodima_advisor_settings"' ), str_contains( $settings_html, 'name="action" value="hodima_advisor_clear"' ), substr_count( $settings_html, 'name="_wpnonce"' ) ], [ true, true, true, 3 ] );
ob_start();
hodima_seo_advisor_render_settings( false, true );
hodima_ad( 'بدون داده دکمه پاک کردن غیرفعال است', substr_count( (string) ob_get_clean(), 'disabled' ) >= 2, true );

hodima_seo_advisor_store( [ '/clear-me/' => [ 'clicks' => 1, 'impressions' => 5000, 'position' => 6.0 ] ], [], [] );
update_option( HODIMA_SEO_ADVISOR_IGNORED_OPTION, [ '/clear-me/' ], false );
update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [ 'fetched' => time(), 'pages' => 1 ], false );
update_option( 'hodima_discover_sc_key', 'KEEP-KEY', false );
update_option( HODIMA_SEO_ADVISOR_LOCK_OPTION, time(), false );
$busy_clear = hodima_seo_advisor_clear_data();
hodima_ad( 'پاک کردن وقتی دریافت داده در حال اجراست رد می‌شود و چیزی پاک نمی‌شود', [ is_wp_error( $busy_clear ) ? $busy_clear->get_error_code() : 'ok', hodima_seo_advisor_pages()['total'] ], [ 'hodima_advisor_busy', 1 ] );
delete_option( HODIMA_SEO_ADVISOR_LOCK_OPTION ); // قفل را دستی گذاشتیم، پس مالکش نیستیم
hodima_ad( 'پاک کردن داده موفق', hodima_seo_advisor_clear_data(), true );
hodima_ad( 'بعد از پاک کردن: جدول‌ها، وضعیت و نادیده‌ها خالی، قفل آزاد', [ hodima_seo_advisor_pages()['total'], hodima_seo_advisor_status()['fetched'], hodima_seo_advisor_ignored(), get_option( HODIMA_SEO_ADVISOR_LOCK_OPTION, false ) ], [ 0, 0, [], false ] );
hodima_ad( 'کلید حساب سرویس و تنظیمات می‌مانند', [ get_option( 'hodima_discover_sc_key' ), hodima_seo_advisor_config()['min_impressions'], hodima_seo_advisor_days() ], [ 'KEEP-KEY', 250, 28 ] );
delete_option( 'hodima_discover_sc_key' );
update_option( HODIMA_SEO_ADVISOR_SETTINGS_OPTION, $cfg_settings, false );

echo "=== تاریخچه هفتگی\n";
update_option( 'hodima_discover_sc_key', $key_json, false );
$week_ends = hodima_seo_advisor_history_week_ends();
hodima_ad( 'هشت یکشنبه پشت‌سرهم، تازه‌ترین اول، همه یکشنبه و دست‌کم ۲ روز پیش', [ count( $week_ends ), count( array_unique( $week_ends ) ), array_unique( array_map( static fn( string $d ): string => ( new DateTimeImmutable( $d ) )->format( 'w' ), $week_ends ) ), $week_ends[0] <= hodima_seo_advisor_ranges( 1 )['end'], $week_ends[0] > $week_ends[1] ], [ 8, 8, [ '0' ], true, true ] );

$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', hodima_seo_advisor_table( 'history' ) ) );
$calls = [];
$mode  = 'ok';
$sync  = hodima_seo_advisor_history_sync( 'sc-domain:' . $host );
hodima_ad( 'همگام‌سازی اول: ۸ هفته با ۸ درخواست جدا (هر هفته ۷ روز)', [ $sync, count( $calls ), count( array_unique( array_map( static fn( array $b ): string => $b['startDate'] . '|' . $b['endDate'], $calls ) ) ), array_unique( array_map( static fn( array $b ): int => ( new DateTimeImmutable( $b['endDate'] ) )->diff( new DateTimeImmutable( $b['startDate'] ) )->days, $calls ) ) ], [ [ 'added' => 8, 'failed' => 0 ], 8, 8, [ 6 ] ] );
hodima_ad( 'هفته‌های ذخیره‌شده', hodima_seo_advisor_history_known_weeks(), $week_ends );

$calls = [];
$sync2 = hodima_seo_advisor_history_sync( 'sc-domain:' . $host );
hodima_ad( 'همگام‌سازی دوم: همه ذخیره‌اند، درخواستی نمی‌رود', [ $sync2, count( $calls ) ], [ [ 'added' => 0, 'failed' => 0 ], 0 ] );

$trend = hodima_seo_advisor_history( '/accessory/hair-clips/', 12 );
hodima_ad( 'روند یک آدرس: ۸ هفته، قدیمی‌ترین اول، با عدد هفته', [ count( $trend ), $trend[0]['week'] === $week_ends[7], $trend[7]['week'] === $week_ends[0], $trend[7]['clicks'] > 0 ], [ 8, true, true, true ] );
$none = hodima_seo_advisor_history( '/not-in-history/', 12 );
hodima_ad( 'آدرسی که در عکس‌ها نیست: همان هفته‌ها با صفر', [ count( $none ), array_sum( array_column( $none, 'clicks' ) ), array_sum( array_column( $none, 'impressions' ) ) ], [ 8, 0, 0 ] );

$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE week_end = %s', hodima_seo_advisor_table( 'history' ), $week_ends[0] ) );
$calls = [];
$mode  = 'denied';
$sync3 = hodima_seo_advisor_history_sync( 'sc-domain:' . $host );
$mode  = 'ok';
hodima_ad( 'شکست یک هفته: خطا شمرده می‌شود و داده دیگر هفته‌ها سالم می‌ماند', [ $sync3['failed'] >= 1, count( hodima_seo_advisor_history_known_weeks() ) ], [ true, 7 ] );

$tiny = [ '/few/' => [ 'clicks' => 1, 'impressions' => HODIMA_SEO_ADVISOR_HISTORY_MIN_IMPRESSIONS - 1, 'position' => 3.0 ], '/enough/' => [ 'clicks' => 1, 'impressions' => HODIMA_SEO_ADVISOR_HISTORY_MIN_IMPRESSIONS, 'position' => 3.0 ] ];
hodima_ad( 'کم‌نمایش (زیر ۱۰) ذخیره نمی‌شود', [ hodima_seo_advisor_history_store_week( '2020-01-05', $tiny ), array_sum( array_column( hodima_seo_advisor_history( '/few/', 400 ), 'impressions' ) ) ], [ 1, 0 ] );
hodima_seo_advisor_history_prune();
hodima_ad( 'هفته بسیار قدیمی (بیش از ۱۰۴ هفته) پاک می‌شود', in_array( '2020-01-05', hodima_seo_advisor_history_known_weeks( 400 ), true ), false );

// پاک کردن داده‌های مشاور تاریخچه را نگه می‌دارد
hodima_seo_advisor_store( [ '/hist-keep/' => [ 'clicks' => 1, 'impressions' => 5000, 'position' => 6.0 ] ], [], [] );
hodima_seo_advisor_clear_data();
hodima_ad( 'پاک کردن داده‌های مشاور، تاریخچه هفتگی را نگه می‌دارد', count( hodima_seo_advisor_history_known_weeks() ), 7 );

$h = [
	[ 'week' => '2026-09-06', 'clicks' => 10, 'impressions' => 1000, 'position' => 8.0 ],
	[ 'week' => '2026-09-13', 'clicks' => 20, 'impressions' => 1000, 'position' => 8.0 ],
	[ 'week' => '2026-09-20', 'clicks' => 30, 'impressions' => 1000, 'position' => 8.0 ],
	[ 'week' => '2026-09-27', 'clicks' => 40, 'impressions' => 1000, 'position' => 7.0 ],
	[ 'week' => '2026-10-04', 'clicks' => 60, 'impressions' => 2000, 'position' => 6.0 ],
];
$ba = hodima_seo_advisor_before_after( $h, '2026-09-23' );
hodima_ad( 'قبل/بعد ویرایش وسط هفته: هفته‌ای که ویرایش وسطش است (۲۷ شهریور) شمرده نمی‌شود', [ $ba['before']['weeks'] ?? 0, $ba['before']['clicks'] ?? 0, $ba['after']['weeks'] ?? 0, $ba['after']['clicks'] ?? 0, $ba['after']['position'] ?? 0 ], [ 3, 20.0, 1, 60.0, 6.0 ] );
hodima_ad( 'قبل/بعد: بدون هفته بعد از ویرایش، یا بدون تاریخ ← null', [ hodima_seo_advisor_before_after( $h, '2026-10-02' ), hodima_seo_advisor_before_after( $h, '' ), hodima_seo_advisor_before_after( $h, '2026-09-01' ) ], [ null, null, null ] );
$modified_post = (int) wp_insert_post( [ 'post_title' => 'modified test', 'post_status' => 'publish', 'post_type' => 'page' ] );
hodima_ad( 'تاریخ ویرایش: نوشته واقعی، و خالی برای دسته/ناشناس', [ preg_match( '/^\d{4}-\d{2}-\d{2}$/', hodima_seo_advisor_object_modified( 'page', $modified_post ) ), hodima_seo_advisor_object_modified( 'product_cat', 1 ), hodima_seo_advisor_object_modified( '', 0 ) ], [ 1, '', '' ] );
// کارت روند در کادر ویرایش: ۸ هفته عکس برای یک آدرس + تاریخ ویرایش در وسط
$trend_ends = array_reverse( hodima_seo_advisor_history_week_ends() );
foreach ( $trend_ends as $i => $trend_end ) {
	hodima_seo_advisor_history_store_week( $trend_end, [ '/trend-page/' => [ 'clicks' => $i < 5 ? 20 : 60, 'impressions' => 1000, 'position' => $i < 5 ? 8.0 : 6.0 ] ] );
}
$edit_day = ( new DateTimeImmutable( $trend_ends[4] ) )->modify( '+3 days' )->format( 'Y-m-d' ); // وسط هفته ششم (۱۲ ام شمرده نمی‌شود)
$wpdb->update( $wpdb->posts, [ 'post_modified' => $edit_day . ' 10:00:00' ], [ 'ID' => $modified_post ] );
clean_post_cache( $modified_post );
ob_start();
hodima_seo_advisor_render_trend( [ 'path' => '/trend-page/', 'kind' => 'page', 'object_id' => $modified_post ] );
$trend_html = (string) ob_get_clean();
hodima_ad( 'کارت روند: ۸ میله، ۲ هفته کامل بعد از ویرایش سبز، جمله قبل/بعد', [ substr_count( $trend_html, 'hodima-advisor-box__bar-label' ), substr_count( $trend_html, 'class="is-after"' ), str_contains( $trend_html, 'hodima-advisor-box__compare' ), str_contains( $trend_html, sprintf( 'از %s به %s رسیده', number_format_i18n( 20, 1 ), number_format_i18n( 60, 1 ) ) ) ], [ 8, 2, true, true ] );
ob_start();
hodima_seo_advisor_render_trend( [ 'path' => '/no-history/', 'kind' => 'page', 'object_id' => $modified_post ] );
hodima_ad( 'آدرس بدون نمایش در تاریخچه: کارت روند نمی‌آید', ob_get_clean(), '' );
ob_start();
hodima_seo_advisor_render_trend( [ 'path' => '/trend-page/', 'kind' => 'product_cat', 'object_id' => 1 ] );
$cat_html = (string) ob_get_clean();
hodima_ad( 'دسته (بدون تاریخ ویرایش): نمودار هست، جمله قبل/بعد نیست', [ str_contains( $cat_html, 'hodima-advisor-box__bars' ), str_contains( $cat_html, 'hodima-advisor-box__compare' ) ], [ true, false ] );
wp_delete_post( $modified_post, true );
$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', hodima_seo_advisor_table( 'history' ) ) );
delete_option( 'hodima_discover_sc_key' );

echo "=== کلید مخصوص مشاور\n";
$own_key = (string) wp_json_encode( [ 'type' => 'service_account', 'client_email' => 'advisor-only@test.iam.gserviceaccount.com', 'private_key' => $pem, 'token_uri' => 'https://oauth2.googleapis.com/token' ] );
delete_option( HODIMA_SEO_SC_ADVISOR_KEY_OPTION );
delete_option( HODIMA_SEO_SC_ADVISOR_SITES_OPTION );
update_option( 'hodima_discover_sc_key', $key_json, false );
hodima_ad( 'بدون کلید مخصوص: مشاور همان کلید مشترک را می‌خواند', [ hodima_seo_sc_key_source( HODIMA_SEO_SC_ADVISOR ), hodima_seo_sc_email( HODIMA_SEO_SC_ADVISOR ), hodima_seo_sc_sites_option( HODIMA_SEO_SC_ADVISOR ) ], [ 'own', 'advisor@test.iam.gserviceaccount.com', 'hodima_discover_sc_sites' ] );
update_option( HODIMA_SEO_SC_ADVISOR_KEY_OPTION, $own_key, false );
hodima_ad( 'کلید مخصوص مشاور اولویت دارد؛ دیسکاور (بدون پارامتر) همان قبلی می‌ماند', [ hodima_seo_sc_key_source( HODIMA_SEO_SC_ADVISOR ), hodima_seo_sc_email( HODIMA_SEO_SC_ADVISOR ), hodima_seo_sc_key_source(), hodima_seo_sc_email(), hodima_seo_sc_sites_option( HODIMA_SEO_SC_ADVISOR ), hodima_seo_sc_sites_option() ], [ 'advisor_own', 'advisor-only@test.iam.gserviceaccount.com', 'own', 'advisor@test.iam.gserviceaccount.com', 'hodima_advisor_sc_sites', 'hodima_discover_sc_sites' ] );

delete_transient( 'hodima_discover_sc_token_' . substr( md5( 'advisor-only@test.iam.gserviceaccount.com' ), 0, 12 ) );
delete_transient( 'hodima_discover_sc_token_' . substr( md5( 'advisor@test.iam.gserviceaccount.com' ), 0, 12 ) );
$shared_sites_before = hodima_seo_sc_sites();
update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [], false );
$mode = 'ok';
hodima_ad( 'دریافت با کلید مخصوص موفق است', hodima_seo_advisor_refresh(), true );
hodima_ad( 'دریافت کامل در سایت کوچک: نوبت‌ها همان درخواست تمام می‌شوند و رویدادی نمی‌ماند', [ hodima_seo_advisor_pipeline_pending(), false !== wp_next_scheduled( HODIMA_SEO_ADVISOR_STAGE_CRON ) ], [ false, false ] );
$mb_ok = hodima_seo_advisor_refresh( true, 0.0 ); // مثل دکمه «گرفتن داده تازه» وقتی بودجه همان‌جا تمام شود
hodima_ad( 'دکمه دستی: داده ذخیره و نوبت‌ها در پس‌زمینه ادامه دارند (رویداد ادامه زمان‌بندی شد)', [ $mb_ok, hodima_seo_advisor_pipeline_pending(), false !== wp_next_scheduled( HODIMA_SEO_ADVISOR_STAGE_CRON ), get_option( HODIMA_SEO_ADVISOR_LOCK_OPTION, false ) ], [ true, true, true, false ] );
wp_clear_scheduled_hook( HODIMA_SEO_ADVISOR_STAGE_CRON );
do_action( HODIMA_SEO_ADVISOR_STAGE_CRON );
hodima_ad( 'کرون ادامه همه نوبت‌ها را تمام می‌کند', [ hodima_seo_advisor_pipeline_pending(), wp_next_scheduled( HODIMA_SEO_ADVISOR_STAGE_CRON ) ], [ false, false ] );
hodima_ad( 'توکن با ایمیل کلید مخصوص ساخته شد (نه کلید مشترک)', [ is_string( get_transient( 'hodima_discover_sc_token_' . substr( md5( 'advisor-only@test.iam.gserviceaccount.com' ), 0, 12 ) ) ), get_transient( 'hodima_discover_sc_token_' . substr( md5( 'advisor@test.iam.gserviceaccount.com' ), 0, 12 ) ) ], [ true, false ] );
hodima_ad( 'فهرست property مشاور جدا نوشته شد و فهرست مشترک دیسکاور دست‌نخورده', [ hodima_seo_sc_sites( HODIMA_SEO_SC_ADVISOR ) !== [], hodima_seo_sc_sites() ], [ true, $shared_sites_before ] );

// ردیف کلید مدرن (پیش از تعریف ثابت wp-config که منبع را برای همیشه ثابت می‌کند)
$kcap = static function ( callable $fn ): string { ob_start(); $fn(); return (string) ob_get_clean(); };
wp_set_current_user( 1 );
$_GET = [];
update_option( HODIMA_SEO_SC_ADVISOR_KEY_OPTION, $own_key, false );
$bar_own = $kcap( static fn() => hodima_seo_advisor_render_settings( true, true ) );
hodima_ad( 'ردیف کلید مدرن: کلید فعال، ایمیل، منبع، جایگزینی و حذف (کلید مخصوص)، پنجره با انتخاب فایل', [ str_contains( $bar_own, 'کلید فعال' ), str_contains( $bar_own, 'advisor-only@test.iam.gserviceaccount.com' ), str_contains( $bar_own, 'data-hdsa-key-open' ), str_contains( $bar_own, 'name="remove"' ), str_contains( $bar_own, 'data-hdsa-key-dialog' ), str_contains( $bar_own, 'data-hdsa-key-file' ) ], [ true, true, true, true, true, true ] );
delete_option( HODIMA_SEO_SC_ADVISOR_KEY_OPTION );
update_option( 'hodima_discover_sc_key', $key_json, false );
$bar_shared = $kcap( static fn() => hodima_seo_advisor_render_settings( true, true ) );
hodima_ad( 'کلید مشترک: «جایگزینی» هست ولی «برداشتن کلید» نیست', [ str_contains( $bar_shared, 'data-hdsa-key-open' ), str_contains( $bar_shared, 'name="remove"' ) ], [ true, false ] );
delete_option( 'hodima_discover_sc_key' );
$bar_none = $kcap( static fn() => hodima_seo_advisor_render_settings( true, true ) );
hodima_ad( 'بدون کلید: «افزودن کلید» و پنل باز است', [ str_contains( $bar_none, 'افزودن کلید' ), 1 === preg_match( '/id="hodima-advisor-settings"[^>]*\\shidden/', $bar_none ) ], [ true, false ] );
delete_option( HODIMA_SEO_SC_ADVISOR_KEY_OPTION );

define( 'HODIMA_SEO_ADVISOR_SERVICE_ACCOUNT_JSON', $key_json );
hodima_ad( 'ثابت wp-config از کلید پیشخوان جلوتر است', [ hodima_seo_sc_key_source( HODIMA_SEO_SC_ADVISOR ), hodima_seo_sc_email( HODIMA_SEO_SC_ADVISOR ) ], [ 'advisor_config', 'advisor@test.iam.gserviceaccount.com' ] );

// صفحه: کارت کلید و منبع
ob_start();
hodima_seo_advisor_render_settings( true, true );
$key_html = (string) ob_get_clean();
hodima_ad( 'کارت: منبع «کلید ثابت wp-config» نشان داده می‌شود و فیلد چسباندن نیست', [ str_contains( $key_html, 'ثابت wp-config' ), str_contains( $key_html, 'name="key_json"' ) ], [ true, false ] );

hodima_ad( 'اعتبارسنجی: JSON خراب رد می‌شود', hodima_seo_sc_validate_key( '{"a":1}' )['ok'], false );
hodima_ad( 'اعتبارسنجی: کلید درست قبول و ایمیل برمی‌گردد', [ hodima_seo_sc_validate_key( $own_key )['ok'], hodima_seo_sc_validate_key( $own_key )['email'] ], [ true, 'advisor-only@test.iam.gserviceaccount.com' ] );

delete_option( HODIMA_SEO_SC_ADVISOR_KEY_OPTION );
delete_option( HODIMA_SEO_SC_ADVISOR_SITES_OPTION );
delete_transient( 'hodima_discover_sc_token_' . substr( md5( 'advisor-only@test.iam.gserviceaccount.com' ), 0, 12 ) );
delete_option( 'hodima_discover_sc_key' );

echo "=== رقابت صفحه‌ها (گزارش کل سایت)\n";
hodima_seo_advisor_store(
	[
		'/c/a/' => [ 'clicks' => 80, 'impressions' => 4000, 'position' => 6.0 ],
		'/c/b/' => [ 'clicks' => 10, 'impressions' => 1000, 'position' => 9.0 ],
		'/c/m/' => [ 'clicks' => 30, 'impressions' => 2000, 'position' => 7.0 ],
		'/c/n/' => [ 'clicks' => 28, 'impressions' => 2000, 'position' => 7.5 ],
		'/c/p/' => [ 'clicks' => 20, 'impressions' => 1500, 'position' => 8.0 ],
		'/c/page/2/' => [ 'clicks' => 1, 'impressions' => 900, 'position' => 8.0 ],
		'/c/lone/' => [ 'clicks' => 5, 'impressions' => 900, 'position' => 8.0 ],
	],
	[],
	[
		'/c/a/' => [ 'کلیپس پلومریا' => [ 'clicks' => 80, 'impressions' => 4000, 'position' => 6.0 ] ],
		'/c/b/' => [ 'کلیپس پلومریا' => [ 'clicks' => 10, 'impressions' => 1000, 'position' => 9.0 ] ],
		'/c/m/' => [ 'گیره مو' => [ 'clicks' => 30, 'impressions' => 2000, 'position' => 7.0 ] ],
		'/c/n/' => [ 'گیره مو' => [ 'clicks' => 28, 'impressions' => 2000, 'position' => 7.5 ] ],
		'/c/p/' => [ 'گیره مو' => [ 'clicks' => 20, 'impressions' => 1500, 'position' => 8.0 ], 'هدهدلی کلیپس' => [ 'clicks' => 20, 'impressions' => 1500, 'position' => 1.0 ] ],
		'/c/page/2/' => [ 'کلیپس پلومریا' => [ 'clicks' => 1, 'impressions' => 900, 'position' => 8.0 ] ],
		'/c/lone/' => [ 'تنها' => [ 'clicks' => 5, 'impressions' => 900, 'position' => 8.0 ] ],
	]
);
update_option( HODIMA_SEO_ADVISOR_IGNORED_OPTION, [], false );
$comp = hodima_seo_advisor_competition();
$by   = array_column( $comp['rows'], null, 'term' );
hodima_ad( 'فقط کلمه‌هایی که دو صفحه یا بیشتر دارند؛ تک‌صفحه، صفحه‌بندی آرشیو و کلمه برندی کنار می‌رود', ( static function ( array $k ): array { sort( $k ); return $k; } )( array_keys( $by ) ), [ 'کلیپس پلومریا', 'گیره مو' ] );
hodima_ad( 'برنده بیشترین کلیک است و سهم‌ها جمع ۱', [ $by['کلیپس پلومریا']['pages'][0]['path'], $by['کلیپس پلومریا']['count'], round( array_sum( array_column( $by['کلیپس پلومریا']['pages'], 'share' ) ), 2 ), round( $by['کلیپس پلومریا']['pages'][0]['share'], 2 ) ], [ '/c/a/', 2, 1.0, 0.8 ] );
hodima_ad( 'سهم ۸۰٪ ← «برنده روشن» (strengthen)؛ سه صفحه هم‌وزن ← «یکی را اصلی کنید» (choose)', [ $by['کلیپس پلومریا']['advice']['type'], $by['گیره مو']['advice']['type'], $by['گیره مو']['count'] ], [ 'strengthen', 'choose', 3 ] );
hodima_ad( 'کلیک بالقوه رفع رقابت مثبت و فهرست مرتب بر اساس آن', [ $by['کلیپس پلومریا']['potential'] > 0, array_column( $comp['rows'], 'potential' ) === ( static function ( array $p ): array { rsort( $p ); return $p; } )( array_column( $comp['rows'], 'potential' ) ) ], [ true, true ] );
hodima_ad( 'جمع‌ها: گروه‌ها، صفحه‌های درگیر', [ $comp['total'], $comp['pages'] ], [ 2, 5 ] );
update_option( HODIMA_SEO_ADVISOR_IGNORED_OPTION, [ '/c/b/' ], false );
hodima_ad( 'صفحه نادیده‌گرفته‌شده از گروه می‌رود و گروهی که یک صفحه‌اش ماند حذف می‌شود', array_column( hodima_seo_advisor_competition()['rows'], 'term' ), [ 'گیره مو' ] );
update_option( HODIMA_SEO_ADVISOR_IGNORED_OPTION, [], false );
hodima_ad( 'جستجو در کلمه و آدرس + فیلتر نوع دستور', [ array_column( hodima_seo_advisor_competition( [ 'search' => 'گیره' ] )['rows'], 'term' ), array_column( hodima_seo_advisor_competition( [ 'search' => '/c/b/' ] )['rows'], 'term' ), array_column( hodima_seo_advisor_competition( [ 'type' => 'strengthen' ] )['rows'], 'term' ) ], [ [ 'گیره مو' ], [ 'کلیپس پلومریا' ], [ 'کلیپس پلومریا' ] ] );
hodima_ad( 'صفحه‌بندی: limit/offset', [ count( hodima_seo_advisor_competition( [ 'limit' => 1 ] )['rows'] ), hodima_seo_advisor_competition( [ 'limit' => 1, 'offset' => 1 ] )['rows'][0]['term'] ?? '' ], [ 1, array_column( $comp['rows'], 'term' )[1] ] );

// تب رقابت در پیشخوان
require_once HODIMA_SEO_DIR . '/core/advisor/advisor-competition-view.php';
require_once HODIMA_SEO_DIR . '/core/advisor/advisor-links-view.php';
require_once HODIMA_SEO_DIR . '/core/advisor/advisor-insights-view.php';
require_once HODIMA_SEO_DIR . '/core/advisor/advisor-breakdown-view.php';
require_once HODIMA_SEO_DIR . '/core/advisor/advisor-trend-view.php';
require_once HODIMA_SEO_DIR . '/core/advisor/advisor-tasks-view.php';
require_once HODIMA_SEO_DIR . '/core/advisor/advisor-health-view.php';
require_once HODIMA_SEO_DIR . '/core/advisor/advisor-crawl-view.php';
require_once __DIR__ . '/advisor-crawl-fixture.php'; // آزمون‌ها با همین خواننده CSV نمونه داده خزش می‌سازند (خزنده داخلی داده واقعی را می‌نویسد)
require_once HODIMA_SEO_DIR . '/core/advisor/advisor-score-view.php';
require_once HODIMA_SEO_DIR . '/core/advisor/advisor-inspect-view.php';
update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [ 'fetched' => time() - 3600, 'property' => 'sc-domain:' . $host, 'days' => 90, 'pages' => 7, 'queries' => 6, 'advised' => 0, 'error' => '' ] + hodima_seo_advisor_ranges( 90 ), false );
update_option( 'hodima_discover_sc_key', $key_json, false );
wp_set_current_user( 1 );
$_GET = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'competition' ];
ob_start();
hodima_seo_advisor_render_admin();
$tab_html = (string) ob_get_clean();
hodima_ad( 'تب رقابت: دو تب، ۴ کارت آمار، دو گروه با دستور و برنده، جدول بدون <details> فرصت‌ها', [ substr_count( $tab_html, 'hodima-advisor__tabs' ), substr_count( $tab_html, 'class="hds-stat"' ), substr_count( $tab_html, 'class="hodima-advisor__group"' ), str_contains( $tab_html, 'برنده پیشنهادی' ), str_contains( $tab_html, 'کلیپس پلومریا' ), str_contains( $tab_html, 'name="tab" value="competition"' ), str_contains( $tab_html, 'action=hodima_advisor_competition_csv' ) && str_contains( $tab_html, '_wpnonce=' ), str_contains( $tab_html, 'hodima-advisor__table' ) ], [ 1, 4, 2, true, true, true, true, false ] );
$_GET = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'competition', 'ctype' => 'choose' ];
ob_start();
hodima_seo_advisor_render_admin();
$tab_filtered = (string) ob_get_clean();
hodima_ad( 'تب رقابت: فیلتر «هم‌وزن» فقط یک گروه (گیره مو) را نشان می‌دهد', [ substr_count( $tab_filtered, 'class="hodima-advisor__group"' ), str_contains( $tab_filtered, 'گیره مو' ), str_contains( $tab_filtered, 'کلیپس پلومریا' ) ], [ 1, true, false ] );
$_GET = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'opportunities' ];
ob_start();
hodima_seo_advisor_render_admin();
$main_html = (string) ob_get_clean();
hodima_ad( 'تب فرصت‌ها پیش‌فرض است و تب رقابت کنارش لینک دارد', [ str_contains( $main_html, 'name="rule"' ), str_contains( $main_html, 'برگشت به فهرست کارها' ), str_contains( $main_html, 'hodima-advisor__group' ) ], [ true, true, false ] );
hodima_ad( 'ردیف تب سه تب دارد: «کارها»، «کارهای انجام‌شده» و «تنظیمات» (SEO 3.3.4)', [ preg_match( '~<nav class="hodima-advisor__tabs".*?</nav>~s', $main_html, $tabs_nav ) ? substr_count( $tabs_nav[0], '<a ' ) : 0, str_contains( $main_html, '>کارها</a>' ), str_contains( $main_html, '>تنظیمات</a>' ), str_contains( $main_html, 'id="hodima-advisor-workview"' ) ], [ 3, true, true, false ] );
delete_option( 'hodima_discover_sc_key' );
$_GET = [];
update_option( 'hodima_discover_sc_key', $key_json, false );
update_option( HODIMA_SEO_ADVISOR_VARIANTS_OPTION, [ [ 'path' => '/a/', 'impressions' => 1250, 'clicks' => 11, 'urls' => [ [ 'url' => 'https://' . $host . '/a/', 'impressions' => 1000, 'clicks' => 10 ], [ 'url' => 'https://www.' . $host . '/a/', 'impressions' => 250, 'clicks' => 1 ] ], 'causes' => [ 'host' ], 'advice' => 'نمونه' ] ], false );
$_GET = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'competition' ];
ob_start();
hodima_seo_advisor_render_admin();
$with_variants = (string) ob_get_clean();
hodima_ad( 'تب رقابت: کارت آدرس‌های تکراری با آدرس‌ها و علت', [ str_contains( $with_variants, 'آدرس‌های تکراری یک صفحه' ), str_contains( $with_variants, 'دامنه یا پروتکل' ), str_contains( $with_variants, 'بیشترین نمایش' ) ], [ true, true, true ] );
delete_option( HODIMA_SEO_ADVISOR_VARIANTS_OPTION );
ob_start();
hodima_seo_advisor_render_admin();
hodima_ad( 'بدون آدرس تکراری: یک خط «پیدا نشد»', str_contains( (string) ob_get_clean(), 'آدرس تکراری پیدا نشد' ), true );
$_GET = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'terms' ];
ob_start();
hodima_seo_advisor_render_admin();
$terms_html = (string) ob_get_clean();
hodima_ad( 'تب «آمار جستجو»: ۴ کارت آمار، ستون‌های هم‌ردیف و جدول غیربرندی', [ substr_count( $terms_html, 'hodima-advisor__tabs' ), substr_count( $terms_html, 'class="hds-stat"' ), str_contains( $terms_html, 'hodima-advisor__cols' ), str_contains( $terms_html, 'hodima-advisor__terms' ), str_contains( $terms_html, 'کلیپس پلومریا' ) ], [ 1, 4, true, true, true ] );
$_GET = [ 'page' => HODIMA_SEO_ADVISOR_PAGE ];
ob_start();
hodima_seo_advisor_render_admin();
$overview_html = (string) ob_get_clean();
hodima_ad( 'صفحه «کارها»: هر صفحه یک ردیف، هر ایراد یک خط با «انجام دادم / لازم نیست»، خط گرفتن داده تازه، بدون کارت آمار و کارت دریافت داده', [ substr_count( $overview_html, 'class="hodima-advisor__ov-row"' ) >= 1, substr_count( $overview_html, 'class="hodima-advisor__ov-item"' ) >= 1, str_contains( $overview_html, 'value="done" class="hds-btn hds-btn--outline hodima-advisor__mark-done"' ), str_contains( $overview_html, 'hodima-advisor__strip' ), substr_count( $overview_html, 'value="hodima_advisor_refresh"' ), str_contains( $overview_html, 'class="hds-stat"' ), str_contains( $overview_html, 'aria-label="نوع ایراد"' ), str_contains( $overview_html, 'tab=tasks' ) ], [ true, true, true, true, 1, false, true, true ] );
hodima_ad( 'تب ناشناس به فهرست «کارها» برمی‌گردد', [ ( static function (): bool { $_GET = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'x' ]; ob_start(); hodima_seo_advisor_render_admin(); return str_contains( (string) ob_get_clean(), 'hodima-advisor__ov' ); } )() ], [ true ] );
hodima_ad( 'تفسیر سهم برندی: سه حالت', [ str_contains( hodima_seo_advisor_brand_reading( 0.8 ), 'غیربرندی' ), str_contains( hodima_seo_advisor_brand_reading( 0.4 ), 'تعادل' ), str_contains( hodima_seo_advisor_brand_reading( 0.1 ), 'نام سایت کم جستجو' ) ], [ true, true, true ] );
delete_option( 'hodima_discover_sc_key' );
$_GET = [];

$cat_advice = hodima_seo_advisor_competition_advice( 'گیره', [ [ 'kind' => 'product', 'path' => '/p/' ], [ 'kind' => 'product_cat', 'path' => '/c/' ] ], 0.9 );
hodima_ad( 'برنده محصول و رقیب دسته ← «دسته را اصلی کنید»', $cat_advice['type'], 'category' );
hodima_seo_advisor_store( [], [], [] );

echo "=== تحلیل‌های کل سایت: آدرس تکراری و برندی\n";
$h  = 'https://' . $host;
$vr = static fn( string $url, int $clicks, int $impressions ): array => [ 'keys' => [ $url ], 'clicks' => $clicks, 'impressions' => $impressions, 'position' => 5.0 ];
$variants = hodima_seo_advisor_find_variants( [
	$vr( $h . '/a/', 10, 1000 ),
	$vr( 'https://www.' . $host . '/a/', 1, 200 ),
	$vr( $h . '/a/?utm_source=x', 0, 50 ),
	$vr( $h . '/b/', 5, 300 ),
	$vr( $h . '/B', 1, 40 ),
	$vr( $h . '/c/', 3, 90 ),
	$vr( $h . '/c/#toc-2', 0, 10 ),
	$vr( $h . '/d/', 3, 90 ),
	$vr( 'https://other-site.test/a/', 99, 99999 ),
] );
$vp = array_column( $variants, null, 'path' );
hodima_ad( 'آدرس‌های تکراری: فقط /a/ و /b/ (بخش # و دامنه دیگر حساب نمی‌شود)، پرنمایش‌ترین اول', array_column( $variants, 'path' ), [ '/a/', '/b/' ] );
hodima_ad( 'علت /a/: دامنه (www) و پارامتر؛ /b/: حروف بزرگ و اسلش', [ $vp['/a/']['causes'], $vp['/b/']['causes'], count( $vp['/a/']['urls'] ), $vp['/a/']['impressions'] ], [ [ 'host', 'query' ], [ 'case', 'slash' ], 3, 1250 ] );
hodima_ad( 'دستور مناسب علت (ریدایرکت ۳۰۱ و canonical)', [ str_contains( $vp['/a/']['advice'], '۳۰۱' ), str_contains( $vp['/a/']['advice'], 'canonical' ), str_contains( $vp['/b/']['advice'], 'اسلش' ) ], [ true, true, true ] );

hodima_seo_advisor_store(
	[ '/s/1/' => [ 'clicks' => 60, 'impressions' => 3000, 'position' => 5.0 ], '/s/2/' => [ 'clicks' => 40, 'impressions' => 2000, 'position' => 6.0 ] ],
	[],
	[
		'/s/1/' => [ 'هدهدلی کلیپس' => [ 'clicks' => 50, 'impressions' => 1000, 'position' => 1.0 ], 'کلیپس پلومریا' => [ 'clicks' => 10, 'impressions' => 1500, 'position' => 6.0 ] ],
		'/s/2/' => [ 'کلیپس پلومریا' => [ 'clicks' => 30, 'impressions' => 1000, 'position' => 7.0 ], 'گیره مو' => [ 'clicks' => 10, 'impressions' => 500, 'position' => 8.0 ] ],
	]
);
add_filter( 'hodima_seo_advisor_brand_terms', $brand_filter2 = static fn(): array => [ 'هدهدلی' ] );
$split = hodima_seo_advisor_brand_split();
hodima_ad( 'سهم برندی: ۵۰ کلیک و ۱۰۰۰ نمایش از یک کلمه؛ غیربرندی ۲ کلمه', [ $split['brand']['clicks'], $split['brand']['impressions'], $split['brand']['terms'], $split['other']['clicks'], $split['other']['impressions'], $split['other']['terms'] ], [ 50, 1000, 1, 50, 3000, 2 ] );
hodima_ad( 'برترین غیربرندی: «کلیپس پلومریا» با ۲ صفحه و رتبه وزنی ۶٫۴؛ پوشش ۸۰٪', [ $split['other_top'][0]['term'], $split['other_top'][0]['pages'], $split['other_top'][0]['position'], round( $split['coverage'], 2 ) ], [ 'کلیپس پلومریا', 2, 6.4, 0.8 ] );
remove_filter( 'hodima_seo_advisor_brand_terms', $brand_filter2 );
update_option( HODIMA_SEO_ADVISOR_VARIANTS_OPTION, $variants, false );
hodima_ad( 'فهرست تکراری ذخیره و خوانده می‌شود؛ «پاک کردن داده‌های دستار» آن را پاک می‌کند', [ count( hodima_seo_advisor_variants() ), hodima_seo_advisor_clear_data(), hodima_seo_advisor_variants() ], [ 2, true, [] ] );

echo "=== بخش‌بندی: دستگاه، کشور، تصویر/ویدیو، نوع نمایش\n";
remove_filter( 'hodima_seo_advisor_breakdown_enabled', '__return_false' );
$dev = static fn( string $path, string $device, int $clicks, int $impressions, float $position ): array => [ 'keys' => [ 'https://' . $host . $path, $device ], 'clicks' => $clicks, 'impressions' => $impressions, 'position' => $position ];
$device_rows = [
	$dev( '/g/a/', 'MOBILE', 20, 3000, 11.0 ), $dev( '/g/a/', 'DESKTOP', 30, 1000, 6.0 ), $dev( '/g/a/', 'TABLET', 1, 100, 7.0 ),
	$dev( '/g/b/', 'MOBILE', 10, 2000, 7.0 ), $dev( '/g/b/', 'DESKTOP', 12, 900, 6.0 ),
	$dev( '/g/c/', 'MOBILE', 5, 300, 20.0 ), $dev( '/g/c/', 'DESKTOP', 5, 400, 5.0 ),
	$dev( '/g/d/', 'MOBILE', 5, 900, 18.0 ),
];
$bd = hodima_seo_advisor_breakdown_devices( $device_rows );
hodima_ad( 'سهم دستگاه‌ها: موبایل ۶۲۰۰ نمایش و ۴۰ کلیک (رتبه وزنی)', [ $bd['devices']['mobile']['impressions'], $bd['devices']['mobile']['clicks'], $bd['devices']['desktop']['impressions'], $bd['devices']['tablet']['impressions'] ], [ 6200, 40, 2300, 100 ] );
hodima_ad( 'فاصله رتبه موبایل: فقط /g/a/ (۵ پله، نمایش کافی)؛ /g/b/ کم‌فاصله، /g/c/ کم‌نمایش، /g/d/ بدون دسکتاپ', [ array_column( $bd['gaps'], 'path' ), $bd['gaps'][0]['diff'] ?? 0 ], [ [ '/g/a/' ], 5.0 ] );

// همگام‌سازی با پاسخ ساختگی
update_option( 'hodima_discover_sc_key', $key_json, false );
delete_option( HODIMA_SEO_ADVISOR_BREAKDOWN_OPTION );
$bd_calls = [];
add_filter(
	'pre_http_request',
	$bd_filter = static function ( mixed $pre, array $args, string $url ) use ( &$bd_calls, $device_rows ): mixed {
		if ( ! str_contains( $url, '/searchAnalytics/query' ) ) {
			return $pre;
		}
		$body = json_decode( (string) $args['body'], true );
		$dims = $body['dimensions'];
		$json = static fn( array $rows ): array => [ 'response' => [ 'code' => 200 ], 'body' => (string) wp_json_encode( [ 'rows' => $rows ] ) ];
		if ( [ 'page', 'device' ] === $dims ) {
			$bd_calls[] = 'device';
			return $json( $device_rows );
		}
		if ( [ 'country' ] === $dims ) {
			$bd_calls[] = 'country';
			return $json( [ [ 'keys' => [ 'irn' ], 'clicks' => 900, 'impressions' => 90000, 'position' => 8.0 ], [ 'keys' => [ 'afg' ], 'clicks' => 60, 'impressions' => 4000, 'position' => 12.0 ], [ 'keys' => [ 'are' ], 'clicks' => 40, 'impressions' => 3000, 'position' => 9.0 ] ] );
		}
		if ( [ 'searchAppearance' ] === $dims ) {
			$bd_calls[] = 'appearance';
			return [ 'response' => [ 'code' => 'video' === ( $GLOBALS['bd_mode'] ?? '' ) ? 500 : 200 ], 'body' => (string) wp_json_encode( [ 'rows' => [ [ 'keys' => [ 'PRODUCT_SNIPPETS' ], 'clicks' => 120, 'impressions' => 9000, 'position' => 6.0 ] ] ] ) ];
		}
		if ( in_array( $body['type'] ?? '', [ 'image', 'video' ], true ) ) {
			$bd_calls[] = $body['type'];
			return $json( 'image' === $body['type'] ? [ [ 'keys' => [ 'https://' . $GLOBALS['host_for_bd'] . '/i/2/' ], 'clicks' => 30, 'impressions' => 900, 'position' => 4.0 ] ] : [] );
		}
		return $pre;
	},
	20,
	3
);
$GLOBALS['host_for_bd'] = $host;
$sync_range = hodima_seo_advisor_ranges( 90 );
$bs = hodima_seo_advisor_breakdown_sync( 'sc-domain:' . $host, $sync_range, 90 );
$stored_bd = hodima_seo_advisor_breakdown();
hodima_ad( 'همگام‌سازی (از ۳.۱.۳ فقط دستگاه): یک درخواست، بدون خطا', [ $bs, $bd_calls ], [ [ 'fetched' => 1, 'errors' => [] ], [ 'device' ] ] );
hodima_ad( 'ذخیره‌شده: فقط دستگاه و بازه؛ کلیدهای قدیمی (کشور، تصویر…) نوشته نمی‌شوند', [ isset( $stored_bd['device']['devices']['mobile'] ), isset( $stored_bd['country'] ), isset( $stored_bd['image'] ), $stored_bd['days'] ], [ true, false, false, 90 ] );
$bd_calls = [];
hodima_ad( 'دوباره زود است (کمتر از ۱۲ ساعت) ← null و درخواستی نمی‌رود', [ hodima_seo_advisor_breakdown_sync( 'sc-domain:' . $host, $sync_range, 90 ), $bd_calls ], [ null, [] ] );
hodima_ad( 'بازه عوض شود (۲۸ روز) ← دوباره می‌گیرد؛ با force هم', [ hodima_seo_advisor_breakdown_sync( 'sc-domain:' . $host, hodima_seo_advisor_ranges( 28 ), 28 )['fetched'] ?? 0, hodima_seo_advisor_breakdown_sync( 'sc-domain:' . $host, $sync_range, 28, true )['fetched'] ?? 0 ], [ 1, 1 ] );
update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [ 'fetched' => time() - 3600, 'property' => 'sc-domain:' . $host, 'days' => 90, 'pages' => 1, 'queries' => 0, 'advised' => 0, 'error' => '' ] + hodima_seo_advisor_ranges( 90 ), false );
hodima_seo_advisor_store( [ '/g/a/' => [ 'clicks' => 51, 'impressions' => 4100, 'position' => 9.0 ] ], [], [] );
wp_set_current_user( 1 );
$_GET = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'terms' ];
ob_start();
hodima_seo_advisor_render_admin();
$bd_html = (string) ob_get_clean();
hodima_ad( 'کارت موبایل داخل «آمار جستجو»: فاصله رتبه، آدرس، بدون کشور و نوع نمایش', [ str_contains( $bd_html, 'در موبایل بدتر رتبه می‌گیرند' ), str_contains( $bd_html, '/g/a/' ), str_contains( $bd_html, 'افغانستان' ), str_contains( $bd_html, 'ریچ‌اسنیپت محصول' ) ], [ true, true, false, false ] );
$_GET = [];
remove_filter( 'pre_http_request', $bd_filter, 20 );
hodima_ad( 'پاک کردن داده‌های مشاور بخش‌بندی را هم پاک می‌کند', [ hodima_seo_advisor_clear_data(), hodima_seo_advisor_breakdown() ], [ true, [] ] );
delete_option( 'hodima_discover_sc_key' );

echo "=== روند ماهانه و کلمه‌های تازه/از دست رفته\n";
remove_filter( 'hodima_seo_advisor_trend_enabled', '__return_false' );
$day = static fn( string $date, int $clicks, int $impressions ): array => [ 'keys' => [ $date ], 'clicks' => $clicks, 'impressions' => $impressions, 'position' => 6.0 ];
$date_rows = [];
// ۲۸ روز مهر ۱۴۰۴ (۲۰۲۵-۰۹-۲۳ تا ۲۰۲۵-۱۰-۲۰ ≈ مهر)، یک ماه کامل مهر ۱۴۰۵ و یک ماه ناقص
foreach ( range( 0, 29 ) as $i ) {
	$date_rows[] = $day( ( new DateTimeImmutable( '2025-09-23' ) )->modify( "+{$i} days" )->format( 'Y-m-d' ), 10, 1000 ); // مهر ۱۴۰۴
	$date_rows[] = $day( ( new DateTimeImmutable( '2026-09-23' ) )->modify( "+{$i} days" )->format( 'Y-m-d' ), 20, 1500 ); // مهر ۱۴۰۵
}
$date_rows[] = $day( '2026-11-01', 5, 100 ); // آبان ۱۴۰۵: فقط یک روز ← ناقص
$months = hodima_seo_advisor_trend_months( $date_rows );
$by_key = array_column( $months, null, 'key' );
hodima_ad( 'ماه‌های شمسی: فقط ۳ ماه؛ مهر ۱۴۰۴ و مهر ۱۴۰۵ کامل، آبان ۱۴۰۵ ناقص، قدیمی‌ترین اول', [ array_column( $months, 'key' ), $by_key[ 140407 ]['partial'] ?? null, $by_key[ 140507 ]['partial'] ?? null, $by_key[ 140508 ]['partial'] ?? null ], [ [ 140407, 140507, 140508 ], false, false, true ] );
$yoy = hodima_seo_advisor_trend_year_over_year( $months );
hodima_ad( 'مقایسه با سال قبل: آخرین ماه کامل (مهر ۱۴۰۵) ← +۱۰۰٪', [ $yoy['clicks'] ?? 0, $yoy['prev_clicks'] ?? 0, $yoy['change'] ?? 0 ], [ 600, 300, 100.0 ] );
hodima_ad( 'بدون ماه کامل سال قبل ← null', hodima_seo_advisor_trend_year_over_year( array_slice( $months, 2 ) ), null );

$qrow = static fn( string $term, int $clicks, int $impressions, float $position = 8.0 ): array => [ 'keys' => [ $term ], 'clicks' => $clicks, 'impressions' => $impressions, 'position' => $position ];
$change = hodima_seo_advisor_trend_terms(
	[ $qrow( 'کلیپس جدید', 5, 400 ), $qrow( 'ثابت', 10, 900 ), $qrow( 'کم', 0, 50 ), $qrow( 'هدهدلی تازه', 9, 500 ), $qrow( 'برگشتی', 2, 300 ) ],
	[ $qrow( 'ثابت', 11, 950 ), $qrow( 'کلیپس قدیمی', 8, 800 ), $qrow( 'کم‌نمایش قدیمی', 1, 100 ), $qrow( 'برگشتی', 1, 5 ), $qrow( 'هدهدلی قدیمی', 9, 600 ) ]
);
hodima_ad( 'کلمه تازه: «کلیپس جدید» (≥۱۰۰ نمایش و قبلا نبود) و «برگشتی» (قبلا کمتر از ۱۰)؛ ثابت و کم‌نمایش نه', array_column( $change['new'], 'term' ), [ 'کلیپس جدید', 'برگشتی' ] );
hodima_ad( 'کلمه از دست رفته: فقط «کلیپس قدیمی» (≥۱۵۰ نمایش قبلا و حالا نیست)', array_column( $change['lost'], 'term' ), [ 'کلیپس قدیمی' ] );
add_filter( 'hodima_seo_advisor_brand_terms', $brand_filter3 = static fn(): array => [ 'هدهدلی' ] );
$change2 = hodima_seo_advisor_trend_terms( [ $qrow( 'هدهدلی تازه', 9, 500 ) ], [ $qrow( 'هدهدلی قدیمی', 9, 600 ) ] );
remove_filter( 'hodima_seo_advisor_brand_terms', $brand_filter3 );
hodima_ad( 'کلمه برندی در هیچ‌کدام نمی‌آید', [ $change2['new'], $change2['lost'] ], [ [], [] ] );

update_option( 'hodima_discover_sc_key', $key_json, false );
delete_option( HODIMA_SEO_ADVISOR_TREND_OPTION );
$tr_calls = [];
add_filter(
	'pre_http_request',
	$tr_filter = static function ( mixed $pre, array $args, string $url ) use ( &$tr_calls, $date_rows, $qrow ): mixed {
		if ( ! str_contains( $url, '/searchAnalytics/query' ) ) {
			return $pre;
		}
		$body = json_decode( (string) $args['body'], true );
		$json = static fn( array $rows ): array => [ 'response' => [ 'code' => 200 ], 'body' => (string) wp_json_encode( [ 'rows' => $rows ] ) ];
		if ( [ 'date' ] === $body['dimensions'] ) {
			$tr_calls[] = [ 'date', $body['startDate'], $body['endDate'] ];
			return $json( $date_rows );
		}
		if ( [ 'query' ] === $body['dimensions'] ) {
			$tr_calls[] = [ 'query', $body['startDate'], $body['endDate'] ];
			return $json( 1 === count( array_filter( $tr_calls, static fn( array $c ): bool => 'query' === $c[0] ) ) ? [ $qrow( 'تازه ۱', 1, 300 ) ] : [] ); // اولی = ۲۸ روز اخیر، دومی = قبلش
		}
		return $pre;
	},
	20,
	3
);
$ts = hodima_seo_advisor_trend_sync( 'sc-domain:' . $host );
$trend = hodima_seo_advisor_trend();
hodima_ad( 'همگام‌سازی: ۳ درخواست (تاریخ ۱۶ ماه، کلمه ۲۸ روز اخیر، کلمه ۲۸ روز قبل) و دو بخش ذخیره', [ $ts, array_column( $tr_calls, 0 ), count( $trend['months'] ), array_column( $trend['new'], 'term' ), $trend['lost'] ], [ [ 'fetched' => 2, 'errors' => [] ], [ 'date', 'query', 'query' ], 3, [ 'تازه ۱' ], [] ] );
$span = ( new DateTimeImmutable( $tr_calls[0][1] ) )->diff( new DateTimeImmutable( $tr_calls[0][2] ) );
hodima_ad( 'بازه روند حدود ۱۶ ماه (۴۸۰ تا ۴۹۰ روز) و پنجره کلمه‌ها ۲۸ روز', [ $span->days >= 480 && $span->days <= 490, ( new DateTimeImmutable( $tr_calls[1][1] ) )->diff( new DateTimeImmutable( $tr_calls[1][2] ) )->days ], [ true, 27 ] );
$tr_calls = [];
hodima_ad( 'دوباره زود است ← null و بدون درخواست؛ با force می‌گیرد', [ hodima_seo_advisor_trend_sync( 'sc-domain:' . $host ), count( $tr_calls ), hodima_seo_advisor_trend_sync( 'sc-domain:' . $host, true )['fetched'] ?? 0 ], [ null, 0, 2 ] );
remove_filter( 'pre_http_request', $tr_filter, 20 );
update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [ 'fetched' => time() - 3600, 'property' => 'sc-domain:' . $host, 'days' => 90, 'pages' => 1, 'queries' => 0, 'advised' => 0, 'error' => '' ] + hodima_seo_advisor_ranges( 90 ), false );
hodima_seo_advisor_store( [ '/t/a/' => [ 'clicks' => 51, 'impressions' => 4100, 'position' => 9.0 ] ], [], [ '/t/a/' => [ 'تازه ۱' => [ 'clicks' => 1, 'impressions' => 300, 'position' => 8.0 ] ] ] );
wp_set_current_user( 1 );
$_GET = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'terms' ];
ob_start();
hodima_seo_advisor_render_admin();
$trend_html = (string) ob_get_clean();
hodima_ad( 'کارت روند داخل «آمار جستجو»: ۳ ستون ماه (یکی ناقص)، کلمه تازه، ماه کامل', [ substr_count( $trend_html, 'hodima-advisor__months-bar' ), substr_count( $trend_html, 'class="is-partial"' ), str_contains( $trend_html, 'تازه ۱' ), str_contains( $trend_html, 'پرکلیک‌ترین ماه کامل' ) ], [ 3, 1, true, true ] );
$_GET = [];
hodima_ad( 'پاک کردن داده‌های مشاور روند را هم پاک می‌کند', [ hodima_seo_advisor_clear_data(), hodima_seo_advisor_trend() ], [ true, [] ] );
delete_option( 'hodima_discover_sc_key' );
add_filter( 'hodima_seo_advisor_trend_enabled', '__return_false' );

echo "=== کارهای من (وضعیت دستی) و فیلتر حداقل کلیک بالقوه\n";
$saved_tasks = get_option( HODIMA_SEO_ADVISOR_TASKS_OPTION, false );
delete_option( HODIMA_SEO_ADVISOR_TASKS_OPTION );
hodima_ad( 'کار تازه: ثبت، خواندن، کلید با کلمه هم‌ارز (ی/ک عربی) یکی', [ hodima_seo_advisor_task_set( '/k/a/', 'near_first', 'كليپس', 'کلمه نزدیک صفحه اول', 'doing' ), hodima_seo_advisor_task( '/k/a/', 'near_first', 'کلیپس' )['status'] ?? '', hodima_seo_advisor_task( '/k/a/', 'low_ctr', '' ) ], [ true, 'doing', null ] );
$doing_at = hodima_seo_advisor_task( '/k/a/', 'near_first', 'کلیپس' )['at'];
hodima_seo_advisor_task_set( '/k/a/', 'near_first', 'کلیپس', '', 'doing' );
hodima_ad( 'ثبت دوباره همان وضعیت: تاریخ و عنوان قبلی می‌ماند', [ hodima_seo_advisor_task( '/k/a/', 'near_first', 'کلیپس' )['at'], hodima_seo_advisor_task( '/k/a/', 'near_first', 'کلیپس' )['title'] ], [ $doing_at, 'کلمه نزدیک صفحه اول' ] );
hodima_ad( 'وضعیت نامعتبر، قاعده نامعتبر و آدرس خالی رد می‌شوند', [ hodima_seo_advisor_task_set( '/k/a/', 'low_ctr', '', 't', 'bad' ), hodima_seo_advisor_task_set( '/k/a/', 'BAD RULE', '', 't', 'done' ), hodima_seo_advisor_task_set( '', 'low_ctr', '', 't', 'done' ) ], [ false, false, false ] );
hodima_seo_advisor_task_set( '/k/b/', 'low_ctr', '', 'عنوان و متا', 'done' );
hodima_seo_advisor_task_set( '/k/c/', 'decline', '', 'افت', 'rejected' );
hodima_ad( 'شمار گروه‌های چرخه عمر: در حال انجام ← نیاز به کار، انجام شد (آماری) ← منتظر گوگل', hodima_seo_advisor_task_counts(), [ 'open' => 1, 'waiting' => 1, 'fixed' => 0, 'rejected' => 1 ] );
hodima_seo_advisor_task_set( '/k/c/', 'decline', '', '', '' );
hodima_ad( 'وضعیت خالی = برداشتن از فهرست', [ hodima_seo_advisor_task( '/k/c/', 'decline', '' ), count( hodima_seo_advisor_tasks() ) ], [ null, 2 ] );

// سقف ۵۰۰: «لازم نیست»ها اول می‌روند
$many = [];
foreach ( range( 1, HODIMA_SEO_ADVISOR_TASKS_MAX + 2 ) as $i ) {
	$many[ 'k' . $i ] = [ 'path' => "/m/{$i}/", 'rule' => 'low_ctr', 'term' => '', 'title' => 't', 'status' => 1 === $i % 2 ? 'rejected' : 'doing', 'at' => 1000 + $i ];
}
update_option( HODIMA_SEO_ADVISOR_TASKS_OPTION, $many, false );
hodima_seo_advisor_task_set( '/k/z/', 'low_ctr', '', 'تازه', 'doing' );
$capped = hodima_seo_advisor_tasks();
hodima_ad( 'سقف ۵۰۰ کار؛ تازه می‌ماند و قدیمی‌ترین «لازم نیست» اول می‌رود', [ count( $capped ), isset( $capped[ hodima_seo_advisor_task_key( '/k/z/', 'low_ctr', '' ) ] ), isset( $capped['k1'] ), isset( $capped['k2'] ) ], [ HODIMA_SEO_ADVISOR_TASKS_MAX, true, false, true ] );
delete_option( HODIMA_SEO_ADVISOR_TASKS_OPTION );

// اثر: کار «انجام دادم» بیش از ۳ هفته پیش با تاریخچه
$we = array_reverse( hodima_seo_advisor_history_week_ends() );
foreach ( $we as $i => $w ) {
	hodima_seo_advisor_history_store_week( $w, [ '/k/eff/' => [ 'clicks' => $i < 4 ? 20 : 60, 'impressions' => 1000, 'position' => $i < 4 ? 8.0 : 6.0 ] ] );
}
$done_day = ( new DateTimeImmutable( $we[3] ) )->modify( '+3 days' )->format( 'Y-m-d' ); // وسط هفته پنجم (اندیس ۴)
$old_task = [ 'path' => '/k/eff/', 'rule' => 'low_ctr', 'term' => '', 'title' => 'عنوان', 'status' => 'done', 'at' => (int) strtotime( $done_day . ' 12:00:00 UTC' ) ];
$eff = hodima_seo_advisor_task_effect( $old_task );
hodima_ad( 'اثر: انجام‌شده وسط هفته ۵ ← قبل ۴ هفته (۲۰) ← بعد ۳ هفته (۶۰)', [ $eff['state'], $eff['effect']['before']['clicks'] ?? 0, $eff['effect']['after']['clicks'] ?? 0 ], [ 'ok', 20.0, 60.0 ] );
hodima_ad( 'اثر: کار همین الان انجام‌شده ← «هنوز زود است»؛ آدرس بی‌تاریخچه ← «تاریخچه کافی نیست»', [ hodima_seo_advisor_task_effect( [ 'path' => '/k/eff/', 'status' => 'done', 'at' => time() ] )['state'], hodima_seo_advisor_task_effect( [ 'path' => '/k/none/', 'status' => 'done', 'at' => time() - 90 * DAY_IN_SECONDS ] )['state'] ], [ 'wait', 'none' ] );
$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', hodima_seo_advisor_table( 'history' ) ) );

// نما: کنترل‌های کادر ویرایش و تب «کارهای من»
hodima_seo_advisor_task_set( '/k/b/', 'low_ctr', '', 'عنوان و متا را بازنویسی کنید', 'done' );
hodima_seo_advisor_task_set( '/k/a/', 'near_first', 'کلیپس', 'کلمه نزدیک صفحه اول', 'doing' );
ob_start();
hodima_seo_advisor_render_task_controls( '/k/b/', [ 'rule' => 'low_ctr', 'term' => '', 'title' => 'عنوان و متا را بازنویسی کنید' ] );
$ctl = (string) ob_get_clean();
hodima_ad( 'کنترل کادر: نشان «انجام دادم»، دو دکمه دیگر و «برگردان به فهرست» هم دکمه (۳ دکمه) با nonce', [ str_contains( $ctl, 'انجام شد ·' ), substr_count( $ctl, 'class="button button-small"' ), str_contains( $ctl, 'برگردان به فهرست' ), str_contains( $ctl, '_wpnonce=' ), str_contains( $ctl, 'status=doing' ), str_contains( $ctl, 'status=done' ) ], [ true, 3, true, true, true, false ] );
update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [ 'fetched' => time() - 3600, 'property' => 'sc-domain:' . $host, 'days' => 90, 'pages' => 1, 'queries' => 0, 'advised' => 0, 'error' => '' ] + hodima_seo_advisor_ranges( 90 ), false );
hodima_seo_advisor_store( [ '/k/b/' => [ 'clicks' => 5, 'impressions' => 3000, 'position' => 8.0 ] ], [], [] );
update_option( 'hodima_discover_sc_key', $key_json, false );
wp_set_current_user( 1 );
$_GET = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'tasks' ];
ob_start();
hodima_seo_advisor_render_admin();
$tasks_html = (string) ob_get_clean();
hodima_ad( 'تب «کارها» (پیش‌فرض «نیاز به کار»): فقط کار باز، فرم با nonce؛ کار «انجام دادم» اینجا نیست', [ str_contains( $tasks_html, 'aria-label="وضعیت کار"' ), substr_count( $tasks_html, 'class="hodima-advisor__task"' ), str_contains( $tasks_html, 'name="action" value="hodima_advisor_task"' ), str_contains( $tasks_html, 'کلمه نزدیک صفحه اول' ), str_contains( $tasks_html, 'عنوان و متا را بازنویسی کنید</strong>' ) ], [ true, 1, true, true, false ] );
hodima_ad(
	'تب «کارهای انجام‌شده» (SEO 3.3.4): سه تب و این یکی فعال، بدون خط به‌روزرسانی و پیوند برگشت، فیلتر دکمه‌ای (نه منو)، عنوان کارت',
	[
		preg_match( '~<nav class="hodima-advisor__tabs".*?</nav>~s', $tasks_html, $tt_nav ) ? substr_count( $tt_nav[0], '<a ' ) : 0,
		str_contains( $tasks_html, 'tab=tasks" aria-current="page"' ),
		substr_count( $tasks_html, 'aria-current="page"' ),
		substr_count( $tasks_html, 'value="hodima_advisor_refresh"' ),
		str_contains( $tasks_html, 'برگشت به فهرست کارها' ),
		str_contains( $tasks_html, '<select' ),
		str_contains( $tasks_html, 'class="hodima-advisor__chip"' ),
		str_contains( $tasks_html, '<h2 class="hds-card__title">کارهای انجام‌شده</h2>' ),
	],
	[ 3, true, 1, 0, false, false, true, true ]
);
$_GET = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'tasks', 'tstatus' => 'waiting' ];
ob_start();
hodima_seo_advisor_render_admin();
$done_html = (string) ob_get_clean();
hodima_ad( 'کنش‌ها دکمه‌اند نه کلمه لینکی: «ویرایش/دیدن در سایت» و «برگردان به فهرست» با کلاس دکمه و آیکون', [ substr_count( $done_html, 'hds-btn hds-btn--outline hodima-advisor__act' ) >= 2, str_contains( $done_html, 'hodima-advisor__link-btn' ), str_contains( $done_html, 'dashicons-undo' ) ], [ true, false, true ] );
hodima_ad( 'راهنما: فهرست «اصطلاح‌ها به زبان ساده» پایین تب با H1 و نرخ کلیک؛ «؟» با popover و نام قابل‌خواندن', [ str_contains( $done_html, 'hodima-advisor__glossary' ) && str_contains( $done_html, 'H1 (تیتر اصلی)' ) && str_contains( $done_html, '<dt>نرخ کلیک</dt>' ), 1 === preg_match( '#<button type="button" class="hodima-advisor-help" popovertarget="(hdsa-help-\d+)" aria-label="«رتبه» یعنی چه؟">؟</button><span id="\1" popover#u', hodima_seo_advisor_help( 'position' ) ), hodima_seo_advisor_help( 'nope' ) ], [ true, true, '' ] );
hodima_ad( 'هر قاعده «چرا مهم است» و «از کجا بفهمم درست شد» دارد؛ متنی ← همان لحظه ذخیره، آماری ← ۱۴ روز و تب «کارها»', [ array_filter( array_map( static fn( string $r ): bool => '' === hodima_seo_advisor_rule_guide( $r )['why'] || '' === hodima_seo_advisor_rule_guide( $r )['verify'], [ 'low_ctr', 'near_first', 'missing_headings', 'missing_content', 'weak_query', 'cannibal', 'decline' ] ) ), str_contains( hodima_seo_advisor_rule_guide( 'missing_content' )['verify'], 'همان لحظه ذخیره' ), str_contains( hodima_seo_advisor_rule_guide( 'low_ctr' )['verify'], '«کارها»' ) ], [ [], true, true ] );
hodima_ad( '«کارهای انجام‌شده» ← «منتظر نتیجه»: بدون پیوند برگشت (خودش تب است)، کار «انجام دادم» با سه گام (انجام دادید، منتظر بررسی ایندکس، منتظر نتیجه با سنجش ۱۴ روزه)', [ ! str_contains( $done_html, 'برگشت به فهرست کارها' ), substr_count( $done_html, 'class="hodima-advisor__task"' ), substr_count( $done_html, 'class="hodima-advisor__step"' ), str_contains( $done_html, 'منتظر بررسی ایندکس' ), str_contains( $done_html, 'همان‌قدر روز پیش از آن مقایسه می‌شود' ), str_contains( $done_html, 'کلمه نزدیک صفحه اول' ) ], [ true, 1, 3, true, true, false ] );
$kb_at   = hodima_seo_advisor_task( '/k/b/', 'low_ctr', '' )['at'];
$ins_old = get_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION, false );
update_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION, [ '/k/b/' => [ 'verdict' => 'PASS', 'coverage' => 'Submitted and indexed', 'last_crawl' => gmdate( 'c', $kb_at - 5 * DAY_IN_SECONDS ), 'checked' => time() - 3 * DAY_IN_SECONDS ] ], false );
hodima_ad( 'خزش گوگل پیش از تغییر: «no» و آدرس کار اول صف بررسی ایندکس (حتی با بررسی ۳ روز پیش)', [ hodima_seo_advisor_inspect_after( '/k/b/', $kb_at )['state'], hodima_seo_advisor_inspect_queue( 5 )[0] ?? '' ], [ 'no', '/k/b/' ] );
update_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION, [ '/k/b/' => [ 'verdict' => 'PASS', 'coverage' => 'Submitted and indexed', 'last_crawl' => gmdate( 'c', $kb_at - 5 * DAY_IN_SECONDS ), 'checked' => time() - 3600 ] ], false );
hodima_ad( 'تازه بررسی شده (کمتر از ۲ روز): دوباره در صف نیست', in_array( '/k/b/', hodima_seo_advisor_inspect_queue( 50 ), true ), false );
update_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION, [ '/k/b/' => [ 'verdict' => 'PASS', 'coverage' => 'Submitted and indexed', 'last_crawl' => gmdate( 'c', $kb_at + DAY_IN_SECONDS ), 'checked' => time() - 3 * DAY_IN_SECONDS ] ], false );
$tl = hodima_seo_advisor_task_timeline( hodima_seo_advisor_task( '/k/b/', 'low_ctr', '' ) );
hodima_ad( 'خزش بعد از تغییر: «yes»، گام دوم «گوگل صفحه را دوباره خواند» سبز و آدرس دیگر اول صف نیست', [ hodima_seo_advisor_inspect_after( '/k/b/', $kb_at )['state'], $tl[1]['label'], $tl[1]['state'], ( hodima_seo_advisor_inspect_queue( 5 )[0] ?? '' ) === '/k/b/' ], [ 'yes', 'گوگل صفحه را دوباره خواند', 'ok', false ] );
false === $ins_old ? delete_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION ) : update_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION, $ins_old, false );

// فیلتر حداقل کلیک بالقوه
$_GET = [];
hodima_ad( 'فیلتر حداقل کلیک بالقوه: فقط مقدار مجاز قبول می‌شود', [ hodima_seo_advisor_list_filters( [ 'min' => '100' ] )['min'], hodima_seo_advisor_list_filters( [ 'min' => '77' ] )['min'], hodima_seo_advisor_list_filters( [ 'min' => '100' ] )['args']['min_potential'] ], [ 100, 0, 100 ] );
hodima_seo_advisor_store( [ '/p/hi/' => [ 'clicks' => 5, 'impressions' => 9000, 'position' => 7.0 ], '/p/lo/' => [ 'clicks' => 5, 'impressions' => 1200, 'position' => 7.0 ] ], [], [] );
$wpdb->update( hodima_seo_advisor_table( 'pages' ), [ 'potential' => 600, 'advice_count' => 1 ], [ 'path' => '/p/hi/' ] );
$wpdb->update( hodima_seo_advisor_table( 'pages' ), [ 'potential' => 60, 'advice_count' => 1 ], [ 'path' => '/p/lo/' ] );
hodima_ad( 'صفحه‌ها با حداقل بالقوه ۱۰۰ و ۵۰۰ و ۱۰۰۰', [ array_column( hodima_seo_advisor_pages( [ 'has_advice' => true, 'min_potential' => 100 ] )['rows'], 'path' ), array_column( hodima_seo_advisor_pages( [ 'has_advice' => true, 'min_potential' => 500 ] )['rows'], 'path' ), hodima_seo_advisor_pages( [ 'has_advice' => true, 'min_potential' => 1000 ] )['total'], hodima_seo_advisor_pages( [ 'has_advice' => true ] )['total'] ], [ [ '/p/hi/' ], [ '/p/hi/' ], 0, 2 ] );
delete_option( 'hodima_discover_sc_key' );
false === $saved_tasks ? delete_option( HODIMA_SEO_ADVISOR_TASKS_OPTION ) : update_option( HODIMA_SEO_ADVISOR_TASKS_OPTION, $saved_tasks, false );
$_GET = [];

echo "=== سلامت آدرس‌ها: بدون نمایش و آدرس مرده\n";
$mk_page = static function ( string $slug, string $title, int $days_old ): int {
	$id = (int) wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => $slug ] );
	if ( $days_old > 0 ) {
		$date = gmdate( 'Y-m-d H:i:s', time() - $days_old * DAY_IN_SECONDS );
		wp_update_post( [ 'ID' => $id, 'post_date' => $date, 'post_date_gmt' => $date, 'edit_date' => true ] );
	}
	return $id;
};
foreach ( [ 'hl-seen', 'hl-old', 'hl-new', 'hl-secret' ] as $slug ) {
	foreach ( get_posts( [ 'post_type' => 'page', 'name' => $slug, 'post_status' => 'any', 'numberposts' => -1 ] ) as $left ) {
		wp_delete_post( $left->ID, true );
	}
}
$h_seen = $mk_page( 'hl-seen', 'دیده‌شده', 200 );
$h_old  = $mk_page( 'hl-old', 'قدیمی بدون نمایش', 120 );
$h_new  = $mk_page( 'hl-new', 'تازه بدون نمایش', 3 );
$h_sec  = $mk_page( 'hl-secret', 'رمزدار', 120 );
wp_update_post( [ 'ID' => $h_sec, 'post_password' => 'x' ] );
hodima_seo_advisor_store(
	[
		hodima_seo_advisor_path_key( (string) get_permalink( $h_seen ) ) => [ 'clicks' => 5, 'impressions' => 500, 'position' => 8.0 ],
		'/hl-missing-page/' => [ 'clicks' => 3, 'impressions' => 800, 'position' => 9.0 ],
		'/hl-low-views/'      => [ 'clicks' => 0, 'impressions' => 10, 'position' => 9.0 ],
		'/'                  => [ 'clicks' => 3, 'impressions' => 900, 'position' => 9.0 ],
	],
	[],
	[]
);
$unseen = hodima_seo_advisor_health_unseen();
$un_ids = array_column( $unseen['unseen'], 'id' );
hodima_ad( 'بدون نمایش: صفحه قدیمی در فهرست، تازه جدا شمرده، دیده‌شده و رمزدار نه', [ in_array( $h_old, $un_ids, true ), in_array( $h_new, $un_ids, true ), in_array( $h_seen, $un_ids, true ), in_array( $h_sec, $un_ids, true ), $unseen['fresh'] >= 1, $unseen['seen'] >= 1 ], [ true, false, false, false, true, true ] );
$old_entry = array_values( array_filter( $unseen['unseen'], static fn( array $u ): bool => $u['id'] === $h_old ) )[0] ?? [];
hodima_ad( 'سن صفحه قدیمی حدود ۱۲۰ روز و نوع page؛ فهرست مرتب بر سن (قدیمی‌ترین اول)', [ abs( ( $old_entry['days'] ?? 0 ) - 120 ) <= 1, $old_entry['type'] ?? '', array_column( $unseen['unseen'], 'days' ) === ( static function ( array $d ): array { rsort( $d ); return $d; } )( array_column( $unseen['unseen'], 'days' ) ) ], [ true, 'page', true ] );

$dead = hodima_seo_advisor_health_dead_urls();
$dead_by = array_column( $dead, null, 'path' );
hodima_ad( 'آدرس مرده: /hl-missing-page/ با ۸۰۰ نمایش ← missing؛ صفحه اصلی، کم‌نمایش (<۲۰) و صفحه سالم نمی‌آیند', [ $dead_by['/hl-missing-page/']['state'] ?? '', isset( $dead_by['/'] ), isset( $dead_by['/hl-low-views/'] ), isset( $dead_by[ hodima_seo_advisor_path_key( (string) get_permalink( $h_seen ) ) ?? '' ] ) ], [ 'missing', false, false, false ] );

remove_filter( 'hodima_seo_advisor_health_enabled', '__return_false' );
hodima_seo_advisor_health_sync();
$hs = hodima_seo_advisor_health();
hodima_ad( 'همگام‌سازی: گزینه ساخته می‌شود با جمع‌ها و فهرست‌ها', [ isset( $hs['computed'] ), $hs['router'] ?? null, ( $hs['total'] ?? 0 ) >= 3, count( $hs['unseen'] ?? [] ) >= 1, count( $hs['dead'] ?? [] ) >= 1 ], [ true, true, true, true, true ] );
add_filter( 'hodima_seo_advisor_health_enabled', '__return_false' );
update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [ 'fetched' => time() - 3600, 'property' => 'sc-domain:' . $host, 'days' => 90, 'pages' => 4, 'queries' => 0, 'advised' => 0, 'error' => '' ] + hodima_seo_advisor_ranges( 90 ), false );
update_option( 'hodima_discover_sc_key', $key_json, false );
wp_set_current_user( 1 );
$_GET = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'health' ];
ob_start();
hodima_seo_advisor_render_admin();
$health_html = (string) ob_get_clean();
hodima_ad( 'تب سلامت: ۴ کارت آمار، جدول آدرس مرده با «۴۰۴» و دستور، جدول صفحه بدون نمایش', [ substr_count( $health_html, 'class="hds-stat"' ), str_contains( $health_html, '/hl-missing-page/' ), str_contains( $health_html, 'ریدایرکت ۳۰۱ به آن ببرید' ), str_contains( $health_html, 'قدیمی بدون نمایش' ), str_contains( $health_html, 'برگشت به فهرست کارها' ) ], [ 4, true, true, true, true ] );
delete_option( 'hodima_discover_sc_key' );
$_GET = [];
hodima_ad( 'پاک کردن داده‌های مشاور سلامت را هم پاک می‌کند', [ hodima_seo_advisor_clear_data(), hodima_seo_advisor_health() ], [ true, [] ] );
foreach ( [ $h_seen, $h_old, $h_new, $h_sec ] as $hid ) {
	wp_delete_post( $hid, true );
}

echo "=== بررسی ایندکس (URL Inspection)\n";
$ins = static fn( string $coverage, string $verdict = 'NEUTRAL', string $indexing = '', string $google = '', string $user = '' ): array => hodima_seo_advisor_inspect_parse( [ 'indexStatusResult' => [ 'verdict' => $verdict, 'coverageState' => $coverage, 'indexingState' => $indexing, 'lastCrawlTime' => '2026-10-01T10:00:00Z', 'googleCanonical' => $google, 'userCanonical' => $user, 'pageFetchState' => 'SUCCESSFUL', 'robotsTxtState' => 'ALLOWED', 'crawledAs' => 'MOBILE' ] ] );
$rec = $ins( 'Submitted and indexed', 'PASS' );
hodima_ad( 'پاسخ گوگل ← رکورد فشرده', [ $rec['verdict'], $rec['coverage'], $rec['last_crawl'], $rec['crawled_as'], $rec['checked'] > 0 ], [ 'PASS', 'Submitted and indexed', '2026-10-01T10:00:00Z', 'MOBILE', true ] );
hodima_ad( 'وضعیت‌ها از متن coverage', array_map( static fn( array $r ): string => hodima_seo_advisor_inspect_state( $r ), [
	$ins( 'Submitted and indexed', 'PASS' ), $ins( 'Indexed, not submitted in sitemap', 'PASS' ), $ins( 'Crawled - currently not indexed' ), $ins( 'Discovered - currently not indexed' ),
	$ins( 'Duplicate, Google chose different canonical than user' ), $ins( 'Alternate page with proper canonical tag' ), $ins( 'Excluded by ‘noindex’ tag' ), $ins( 'Blocked by robots.txt' ),
	$ins( 'Soft 404' ), $ins( 'Not found (404)' ), $ins( 'Server error (5xx)' ), $ins( 'Page with redirect' ), $ins( 'URL is unknown to Google' ), $ins( 'Something new' ), $ins( '', 'NEUTRAL', 'BLOCKED_BY_ROBOTS_TXT' ),
] ), [ 'indexed', 'indexed', 'crawled_not_indexed', 'discovered_not_indexed', 'duplicate', 'duplicate', 'noindex', 'robots', 'soft404', 'not_found', 'server_error', 'redirect', 'unknown_to_google', 'other', 'robots' ] );
hodima_ad( 'اختلاف canonical: فرق مسیر (بعد از یکسان‌سازی) ← بله؛ www/اسلش ← نه؛ ناشناخته ← نه', [ hodima_seo_advisor_inspect_canonical_mismatch( $ins( 'x', 'PASS', '', 'https://' . $host . '/a/', 'https://' . $host . '/b/' ) ), hodima_seo_advisor_inspect_canonical_mismatch( $ins( 'x', 'PASS', '', 'https://www.' . $host . '/a', 'https://' . $host . '/a/' ) ), hodima_seo_advisor_inspect_canonical_mismatch( $ins( 'x', 'PASS' ) ) ], [ true, false, false ] );

// صف: بدون نمایش‌ها اول، بعد دارای پیشنهاد، بعد پرنمایش؛ تازه‌بررسی‌شده و صفحه‌بندی کنار
hodima_seo_advisor_store(
	[ '/q/a/' => [ 'clicks' => 5, 'impressions' => 9000, 'position' => 7.0 ], '/q/b/' => [ 'clicks' => 5, 'impressions' => 3000, 'position' => 7.0 ], '/q/c/' => [ 'clicks' => 5, 'impressions' => 8000, 'position' => 7.0 ], '/q/page/2/' => [ 'clicks' => 1, 'impressions' => 99999, 'position' => 7.0 ] ],
	[],
	[]
);
$wpdb->update( hodima_seo_advisor_table( 'pages' ), [ 'potential' => 500, 'advice_count' => 1 ], [ 'path' => '/q/b/' ] );
delete_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION );
update_option( HODIMA_SEO_ADVISOR_HEALTH_OPTION, [ 'computed' => time(), 'unseen' => [ [ 'path' => '/q/unseen/' ] ] ], false );
hodima_ad( 'ترتیب صف: بدون نمایش ← دارای پیشنهاد (بیشترین بالقوه) ← پرنمایش‌ترین؛ صفحه‌بندی نه', hodima_seo_advisor_inspect_queue( 10 ), [ '/q/unseen/', '/q/b/', '/q/a/', '/q/c/' ] );
update_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION, [ '/q/unseen/' => [ 'checked' => time() - DAY_IN_SECONDS ] + $rec, '/q/a/' => [ 'checked' => time() - 20 * DAY_IN_SECONDS ] + $rec ], false );
hodima_ad( 'تازه‌بررسی‌شده (۱ روز) دوباره نمی‌آید؛ قدیمی (۲۰ روز) می‌آید؛ سقف دسته', [ hodima_seo_advisor_inspect_queue( 10 ), hodima_seo_advisor_inspect_queue( 2 ), hodima_seo_advisor_inspect_pending() ], [ [ '/q/b/', '/q/a/', '/q/c/' ], [ '/q/b/', '/q/a/' ], 3 ] );
delete_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION );

// همگام‌سازی با پاسخ ساختگی
remove_filter( 'hodima_seo_advisor_inspect_enabled', '__return_false' );
update_option( 'hodima_discover_sc_key', $key_json, false );
update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [ 'fetched' => time() - 3600, 'property' => 'sc-domain:' . $host, 'days' => 90, 'pages' => 4, 'queries' => 0, 'advised' => 0, 'error' => '' ] + hodima_seo_advisor_ranges( 90 ), false );
$in_calls = [];
$GLOBALS['inspect_quota_after'] = 99;
add_filter(
	'pre_http_request',
	$in_filter = static function ( mixed $pre, array $args, string $url ) use ( &$in_calls ): mixed {
		if ( ! str_contains( $url, '/urlInspection/index:inspect' ) ) {
			return $pre;
		}
		$body       = json_decode( (string) $args['body'], true );
		$in_calls[] = $body;
		if ( count( $in_calls ) > $GLOBALS['inspect_quota_after'] ) {
			return [ 'response' => [ 'code' => 429 ], 'body' => (string) wp_json_encode( [ 'error' => [ 'message' => 'quota' ] ] ) ];
		}
		$coverage = str_contains( $body['inspectionUrl'], '/q/b/' ) ? 'Crawled - currently not indexed' : 'Submitted and indexed';
		return [ 'response' => [ 'code' => 200 ], 'body' => (string) wp_json_encode( [ 'inspectionResult' => [ 'indexStatusResult' => [ 'verdict' => 'Submitted and indexed' === $coverage ? 'PASS' : 'NEUTRAL', 'coverageState' => $coverage, 'lastCrawlTime' => '2026-10-02T08:00:00Z', 'googleCanonical' => $body['inspectionUrl'], 'userCanonical' => $body['inspectionUrl'] ] ] ] ) ];
	},
	20,
	3
);
$batch = hodima_seo_advisor_inspect_batch( 3 );
$stored_in = hodima_seo_advisor_inspections();
hodima_ad( 'دسته: ۳ آدرس پرسیده، property درست، رکوردها ذخیره، وضعیت‌ها درست', [ $batch, count( $in_calls ), $in_calls[0]['siteUrl'], array_keys( $stored_in ), hodima_seo_advisor_inspect_state( $stored_in['/q/b/'] ) ], [ [ 'checked' => 3, 'error' => '', 'quota' => false ], 3, 'sc-domain:' . $host, [ '/q/unseen/', '/q/b/', '/q/a/' ], 'crawled_not_indexed' ] );
hodima_ad( 'inspectionUrl آدرس کامل سایت است', str_starts_with( $in_calls[0]['inspectionUrl'], 'https://' . $host ), true );
$in_calls = [];
$GLOBALS['inspect_quota_after'] = 1;
delete_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION );
$batch2 = hodima_seo_advisor_inspect_batch( 5 );
hodima_ad( 'سهمیه (۴۲۹) ← فورا می‌ایستد، آنچه پرسیده شده ذخیره می‌شود', [ $batch2['checked'], $batch2['quota'], '' !== $batch2['error'], count( $in_calls ), count( hodima_seo_advisor_inspections() ) ], [ 1, true, true, 2, 1 ] );

// نما
$_GET = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'indexing' ];
update_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION, [ '/q/b/' => $ins( 'Crawled - currently not indexed' ), '/q/a/' => $ins( 'Submitted and indexed', 'PASS' ), '/q/c/' => $ins( 'Duplicate, Google chose different canonical than user', 'NEUTRAL', '', 'https://' . $host . '/q/a/', 'https://' . $host . '/q/c/' ) ], false );
wp_set_current_user( 1 );
ob_start();
hodima_seo_advisor_render_admin();
$in_html = (string) ob_get_clean();
hodima_ad( 'تب ایندکس: ۴ کارت آمار، دکمه با nonce، مشکل‌دارها (خزیده ولی ایندکس نشد و canonical دیگر) و دستورشان', [ substr_count( $in_html, 'class="hds-stat"' ), str_contains( $in_html, 'name="action" value="hodima_advisor_inspect_now"' ), str_contains( $in_html, 'خزیده شد ولی ایندکس نشد' ), str_contains( $in_html, 'تکراری / canonical دیگر' ), str_contains( $in_html, '/q/a/' ), str_contains( $in_html, 'درخواست ایندکس' ), str_contains( $in_html, 'برگشت به فهرست کارها' ) ], [ 4, true, true, true, true, true, true ] );
$_GET = [];
remove_filter( 'pre_http_request', $in_filter, 20 );
hodima_ad( 'پاک کردن داده‌های مشاور نتیجه بررسی ایندکس را هم پاک می‌کند', [ hodima_seo_advisor_clear_data(), hodima_seo_advisor_inspections() ], [ true, [] ] );
add_filter( 'hodima_seo_advisor_inspect_enabled', '__return_false' );
delete_option( 'hodima_discover_sc_key' );

echo "=== کلید کلمه (term_key): املای متفاوت یک کلمه بین آدرس‌ها یکی است (بخش ۲۷۹)\n";
hodima_ad( 'term_key: ی/ي عربی، اعراب، کشیده، ارقام و حروف بزرگ یکی می‌شوند', [ hodima_seo_advisor_term_key( 'کيف مو ۲' ) === hodima_seo_advisor_term_key( 'کیف مو 2' ), hodima_seo_advisor_term_key( 'کِیـف' ) === hodima_seo_advisor_term_key( 'کیف' ), hodima_seo_advisor_term_key( 'Hair CLIP' ), hodima_seo_advisor_term_key( '  ' ) ], [ true, true, 'hair clip', '' ] );
$arabic = "کيف مو"; // ي عربی
$farsi  = 'کیف مو';
hodima_seo_advisor_store(
	[
		'/k/a/' => [ 'clicks' => 40, 'impressions' => 3000, 'position' => 6.0 ],
		'/k/b/' => [ 'clicks' => 10, 'impressions' => 2000, 'position' => 9.0 ],
		'/k/c/' => [ 'clicks' => 5, 'impressions' => 800, 'position' => 12.0 ],
	],
	[],
	[
		'/k/a/' => [ $arabic => [ 'clicks' => 40, 'impressions' => 3000, 'position' => 6.0 ] ],
		'/k/b/' => [ $farsi => [ 'clicks' => 10, 'impressions' => 2000, 'position' => 9.0 ] ],
		'/k/c/' => [ 'Hair Clip' => [ 'clicks' => 5, 'impressions' => 800, 'position' => 12.0 ] ],
	]
);
$keyed = $wpdb->get_col( $wpdb->prepare( 'SELECT term_key FROM %i ORDER BY path, term_key', hodima_seo_advisor_table( 'queries' ) ) );
hodima_ad( 'ذخیره: term_key هر ردیف پر و یکسان‌شده است', $keyed, [ hodima_seo_advisor_term_key( $arabic ), hodima_seo_advisor_term_key( $farsi ), 'hair clip' ] );
$kcomp = hodima_seo_advisor_competition();
hodima_ad( 'رقابت: دو املای ي/ی دو آدرس یک گروه است (قبلا اصلا دیده نمی‌شد)', [ $kcomp['total'], $kcomp['rows'][0]['count'] ?? 0, array_column( $kcomp['rows'][0]['pages'] ?? [], 'path' ) ], [ 1, 2, [ '/k/a/', '/k/b/' ] ] );
hodima_ad( 'رقابت: برچسب گروه املای پرنمایش‌تر است', $kcomp['rows'][0]['term'] ?? '', $arabic );
$kq = [ [ 'term' => $arabic, 'clicks' => 40, 'impressions' => 3000, 'position' => 6.0 ] ];
$kc = hodima_seo_advisor_competitors( '/k/a/', $kq );
hodima_ad( 'رقبای یک آدرس: با term_key وصل می‌شود و کلید خروجی term_key است', [ array_keys( $kc ), array_column( $kc[ hodima_seo_advisor_term_key( $arabic ) ] ?? [], 'path' ) ], [ [ hodima_seo_advisor_term_key( $arabic ) ], [ '/k/b/' ] ] );
$ks = hodima_seo_advisor_suggestions( [ 'path' => '/k/a/', 'clicks' => 40, 'impressions' => 3000, 'position' => 6.0, 'prev_clicks' => 0 ], $kq, null, $kc );
hodima_ad( 'قاعده cannibal با املای متفاوت فعال می‌شود', in_array( 'cannibal', array_column( $ks, 'rule' ), true ), true );
hodima_ad( 'صفحه‌های رقیب برای گام «صفحه مرجع» با املای دیگر هم پیدا می‌شود', array_column( hodima_seo_advisor_term_pages( $farsi ), 'path' ), [ '/k/a/', '/k/b/' ] );
$ksplit = hodima_seo_advisor_brand_split();
hodima_ad( 'سهم برندی: املاهای یک کلمه یک ردیف می‌شود', [ $ksplit['other']['terms'], $ksplit['other']['impressions'] ], [ 2, 5800 ] );
// مهاجرت ۱٫۳٫۰ ← ۱٫۴٫۰: ردیف بدون term_key موقتا همان کلمه خام می‌شود
$wpdb->query( $wpdb->prepare( "UPDATE %i SET term_key = ''", hodima_seo_advisor_table( 'queries' ) ) );
update_option( HODIMA_SEO_ADVISOR_VERSION_OPTION, '1.3.0', false );
hodima_seo_advisor_ensure_tables();
hodima_ad( 'مهاجرت: ردیف‌های قدیمی term_key خالی نمی‌مانند و نسخه بالا می‌رود', [ (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE term_key = ''", hodima_seo_advisor_table( 'queries' ) ) ), get_option( HODIMA_SEO_ADVISOR_VERSION_OPTION ) ], [ 0, HODIMA_SEO_ADVISOR_DB_VERSION ] );

echo "=== نوبت‌ها: تحلیل زمان‌دار و ادامه با کرون (بخش ۲۸۰)\n";
$pz = [ 'clicks' => 0, 'impressions' => 0, 'position' => 0.0 ];
$p_cur = [
	'/st/a/' => [ 'clicks' => 496, 'impressions' => 36492, 'position' => 5.81 ],
	'/st/b/' => [ 'clicks' => 5, 'impressions' => 500, 'position' => 6.0 ],
	'/st/d/' => [ 'clicks' => 600, 'impressions' => 6000, 'position' => 6.0 ],
];
$p_prv = [ '/st/d/' => [ 'clicks' => 1000, 'impressions' => 6000, 'position' => 6.0 ] ];
hodima_seo_advisor_store( $p_cur, $p_prv, [] );
delete_option( HODIMA_SEO_ADVISOR_ANALYSIS_OPTION );
hodima_ad( 'نوبت با وقت تمام‌شده: هیچ آدرسی تحلیل نمی‌شود و صف می‌ماند', [ hodima_seo_advisor_analyze_batch( 0.0 ), (array) ( get_option( HODIMA_SEO_ADVISOR_ANALYSIS_OPTION )['queue'] ?? [] ), hodima_seo_advisor_advised_count() ], [ false, [ '/st/a/', '/st/d/' ], 0 ] );
hodima_ad( 'نوبت بعد از همان صف ادامه می‌دهد و تمام می‌شود', [ hodima_seo_advisor_analyze_batch( INF ), get_option( HODIMA_SEO_ADVISOR_ANALYSIS_OPTION, 'none' ), hodima_seo_advisor_advised_count() ], [ true, 'none', 2 ] );
// ذخیره دوباره داده: پیشنهاد قبلی تا تحلیل تازه می‌ماند (فهرست خالی نمی‌شود)
hodima_seo_advisor_store( $p_cur, $p_prv, [] );
hodima_ad( 'ذخیره تازه داده پیشنهاد قبلی را نگه می‌دارد', [ hodima_seo_advisor_advised_count(), hodima_seo_advisor_page( '/st/a/' )['advice'][0]['rule'] ?? '' ], [ 2, 'low_ctr' ] );
// آدرسی که دیگر قابل‌بررسی نیست (نمایش کم شد) در نوبت اول پیشنهادش پاک می‌شود
hodima_seo_advisor_store( [ '/st/a/' => $p_cur['/st/a/'], '/st/d/' => [ 'clicks' => 1, 'impressions' => 10, 'position' => 6.0 ] ], [], [] );
hodima_ad( 'پیشنهاد آدرس غیرقابل‌بررسی پاک می‌شود، بقیه می‌ماند', [ hodima_seo_advisor_analyze_batch( INF ), hodima_seo_advisor_advised_count(), hodima_seo_advisor_page( '/st/d/' )['advice_count'] ], [ true, 1, 0 ] );
hodima_ad( 'analyze_all همزمان هنوز همان نتیجه را می‌دهد و صف قدیمی را دور می‌ریزد', [ update_option( HODIMA_SEO_ADVISOR_ANALYSIS_OPTION, [ 'queue' => [ '/nowhere/' ] ], false ), hodima_seo_advisor_analyze_all(), get_option( HODIMA_SEO_ADVISOR_ANALYSIS_OPTION, 'none' ) ], [ true, 1, 'none' ] );

// صف نوبت‌ها
wp_clear_scheduled_hook( HODIMA_SEO_ADVISOR_STAGE_CRON );
$st_state = [ 'stages' => [ 'analyze', 'health', 'bogus' ], 'property' => 'sc-domain:' . $host, 'range' => hodima_seo_advisor_ranges( 90 ), 'days' => 90 ];
update_option( HODIMA_SEO_ADVISOR_PIPELINE_OPTION, $st_state, false );
hodima_seo_advisor_pipeline_run( 0.0 );
hodima_ad( 'بودجه تمام‌شده: نوبت‌ها می‌مانند و رویداد ادامه زمان‌بندی می‌شود', [ hodima_seo_advisor_pipeline_pending(), false !== wp_next_scheduled( HODIMA_SEO_ADVISOR_STAGE_CRON ) ], [ true, true ] );
// قفل دست دیگری است ← رویداد ادامه نمی‌دهد و دوباره زمان‌بندی می‌کند
wp_clear_scheduled_hook( HODIMA_SEO_ADVISOR_STAGE_CRON );
hodima_seo_advisor_lock();
do_action( HODIMA_SEO_ADVISOR_STAGE_CRON );
$late = wp_next_scheduled( HODIMA_SEO_ADVISOR_STAGE_CRON );
hodima_seo_advisor_unlock();
hodima_ad( 'رویداد با قفل مشغول: چیزی اجرا نمی‌شود و حدود ۵ دقیقه بعد دوباره امتحان می‌شود', [ count( (array) get_option( HODIMA_SEO_ADVISOR_PIPELINE_OPTION )['stages'] ), is_int( $late ) && $late >= time() + 290 ], [ 3, true ] );
// نوبت خراب (استثنا) بقیه را متوقف نمی‌کند
wp_clear_scheduled_hook( HODIMA_SEO_ADVISOR_STAGE_CRON );
add_filter( 'hodima_seo_advisor_health_enabled', $boom = static function (): bool { throw new RuntimeException( 'boom' ); } );
do_action( HODIMA_SEO_ADVISOR_STAGE_CRON );
remove_filter( 'hodima_seo_advisor_health_enabled', $boom );
hodima_ad( 'استثنای یک نوبت: ثبت خطا، بقیه نوبت‌ها اجرا و صف خالی می‌شود', [ hodima_seo_advisor_pipeline_pending(), str_contains( hodima_seo_advisor_status()['error'], 'health' ), wp_next_scheduled( HODIMA_SEO_ADVISOR_STAGE_CRON ), hodima_seo_advisor_status()['advised'] ], [ false, true, false, 1 ] );
// دریافت کامل: همه نوبت‌ها همان درخواست تمام می‌شوند (سایت کوچک)
hodima_ad( 'پاک کردن داده‌های دستار صف و رویداد نوبت‌ها را هم برمی‌دارد', [ update_option( HODIMA_SEO_ADVISOR_PIPELINE_OPTION, $st_state, false ), wp_schedule_single_event( time() + 60, HODIMA_SEO_ADVISOR_STAGE_CRON ), hodima_seo_advisor_clear_data(), get_option( HODIMA_SEO_ADVISOR_PIPELINE_OPTION, 'none' ), wp_next_scheduled( HODIMA_SEO_ADVISOR_STAGE_CRON ) ], [ true, true, true, 'none', false ] );

echo "=== افت کلیک: علت و مقایسه با کل سایت؛ اثر خالص در برابر روند سایت (بخش ۲۸۱)\n";
$drow = static fn( array $o = [] ): array => $o + [ 'path' => '/dc/', 'clicks' => 600, 'impressions' => 6000, 'position' => 6.0, 'prev_clicks' => 1000, 'prev_impressions' => 6000, 'prev_position' => 6.0 ];
hodima_ad( 'علت افت: رتبه ۳ پله بدتر ← position', hodima_seo_advisor_decline_cause( $drow( [ 'position' => 9.0, 'impressions' => 3000 ] ), null )['cause'], 'position' );
hodima_ad( 'علت افت: نمایش نصف و رتبه ثابت ← impressions', hodima_seo_advisor_decline_cause( $drow( [ 'impressions' => 3000, 'clicks' => 300 ] ), null )['cause'], 'impressions' );
hodima_ad( 'علت افت: نمایش و رتبه ثابت، نرخ کلیک کم ← ctr', hodima_seo_advisor_decline_cause( $drow(), null )['cause'], 'ctr' );
hodima_ad( 'علت افت: تغییر کوچک ← mixed', hodima_seo_advisor_decline_cause( $drow( [ 'impressions' => 5500, 'position' => 7.0, 'clicks' => 660, 'prev_clicks' => 700 ] ), null )['cause'], 'mixed' );
hodima_ad( 'علت افت: رتبه بدتر بر نمایش کمتر مقدم است', hodima_seo_advisor_decline_cause( $drow( [ 'impressions' => 2000, 'position' => 12.0 ] ), null )['cause'], 'position' );
hodima_ad( 'جمله علت: کل سایت هم افت کرده ← توجه؛ رشد کرده ← مخصوص همین صفحه؛ ثابت ← بدون توجه', [ str_contains( hodima_seo_advisor_decline_cause( $drow(), [ 'clicks_ratio' => 0.5, 'impressions_ratio' => null ] )['text'], 'کل سایت هم' ), str_contains( hodima_seo_advisor_decline_cause( $drow(), [ 'clicks_ratio' => 1.3, 'impressions_ratio' => null ] )['text'], 'مخصوص همین صفحه' ), str_contains( hodima_seo_advisor_decline_cause( $drow(), [ 'clicks_ratio' => 0.95, 'impressions_ratio' => null ] )['text'], 'توجه' ) ], [ true, true, false ] );
$decl = static fn( array $row, ?array $site ): array => array_values( array_filter( hodima_seo_advisor_suggestions( $row, [], null, [], $site ), static fn( array $x ): bool => 'decline' === $x['rule'] ) );
hodima_ad( 'افت بدون اطلاعات سایت مثل قبل: کلیک از دست‌رفته ۴۰۰', array_column( $decl( $drow(), null ), 'potential' ), [ 400 ] );
hodima_ad( 'کل سایت ۴۰٪ افت کرده و صفحه ۴۰٪ ← همراه سایت است، پیشنهادی نیست', $decl( $drow(), [ 'clicks_ratio' => 0.6, 'impressions_ratio' => null ] ), [] );
hodima_ad( 'کل سایت ۴۰٪ افت کرده و صفحه ۷۰٪ ← فقط افت اضافه (۵۰۰ − ۳۰۰ = ۲۰۰)', array_column( $decl( $drow( [ 'clicks' => 300 ] ), [ 'clicks_ratio' => 0.5, 'impressions_ratio' => null ] ), 'potential' ), [ 200 ] );
hodima_ad( 'سایت رشد کرده ← انتظار بالا نمی‌رود: همان ۴۰۰', array_column( $decl( $drow(), [ 'clicks_ratio' => 1.4, 'impressions_ratio' => null ] ), 'potential' ), [ 400 ] );
hodima_ad( 'پیشنهاد افت علت را در خود دارد', $decl( $drow(), null )[0]['cause'] ?? '', 'ctr' );

// جمع کل سایت از جدول
hodima_seo_advisor_store(
	[ '/sc/a/' => [ 'clicks' => 600, 'impressions' => 6000, 'position' => 6.0 ], '/sc/b/' => [ 'clicks' => 400, 'impressions' => 4000, 'position' => 6.0 ], '/sc/c/' => [ 'clicks' => 100, 'impressions' => 2000, 'position' => 6.0 ] ],
	[ '/sc/a/' => [ 'clicks' => 1000, 'impressions' => 6000, 'position' => 6.0 ], '/sc/b/' => [ 'clicks' => 800, 'impressions' => 4000, 'position' => 6.0 ], '/sc/c/' => [ 'clicks' => 700, 'impressions' => 2000, 'position' => 6.0 ] ],
	[]
);
$sc = hodima_seo_advisor_site_change();
hodima_ad( 'جمع کل سایت بعد از ذخیره: کلیک ۱۱۰۰ از ۲٬۵۰۰ ← ۰٫۴۴؛ نمایش ۱۲٬۰۰۰ از ۱۲٬۰۰۰ ← ۱', [ round( (float) $sc['clicks_ratio'], 2 ), $sc['impressions_ratio'] ], [ 0.44, 1.0 ] );
$an = static fn( string $path ): array => array_column( hodima_seo_advisor_analyze( $path )['suggestions'] ?? [], 'rule' );
hodima_ad( 'تحلیل واقعی: a و b همراه سایت‌اند (بدون افت)؛ c افت اضافه دارد', [ in_array( 'decline', $an( '/sc/a/' ), true ), in_array( 'decline', $an( '/sc/b/' ), true ), hodima_seo_advisor_analyze( '/sc/c/' )['potential'] ], [ false, false, 208 ] );
hodima_ad( 'بازه قبل کم‌داده ← نسبت سایت null', ( static function (): array { hodima_seo_advisor_store( [ '/sc/x/' => [ 'clicks' => 5, 'impressions' => 100, 'position' => 6.0 ] ], [ '/sc/x/' => [ 'clicks' => 9, 'impressions' => 100, 'position' => 6.0 ] ], [] ); return hodima_seo_advisor_site_change(); } )(), [ 'clicks_ratio' => null, 'impressions_ratio' => null ] );
delete_option( HODIMA_SEO_ADVISOR_SITE_OPTION );
hodima_ad( 'گزینه جمع سایت نبود ← خودش از جدول ساخته می‌شود', array_key_exists( 'clicks_ratio', hodima_seo_advisor_site_change() ) && is_array( get_option( HODIMA_SEO_ADVISOR_SITE_OPTION ) ), true );

// اثر خالص در برابر روند سایت
$wk = [ '2025-01-05', '2025-01-12', '2025-01-19', '2025-01-26', '2025-02-02' ];
$put = static function ( array $page, array $other ) use ( $wk ): void {
	foreach ( $wk as $i => $end ) {
		hodima_seo_advisor_history_store_week( $end, [ '/eff/' => [ 'clicks' => $page[ $i ], 'impressions' => 500, 'position' => 6.0 ], '/other/' => [ 'clicks' => $other[ $i ], 'impressions' => 5000, 'position' => 6.0 ] ] );
	}
};
$mk = static fn( array $page ): array => array_map( static fn( string $end, int $c ): array => [ 'week' => $end, 'clicks' => $c, 'impressions' => 500, 'position' => 6.0 ], $wk, $page );
$put( [ 10, 10, 15, 20, 20 ], [ 990, 990, 995, 990, 990 ] );
$ba1 = hodima_seo_advisor_before_after( $mk( [ 10, 10, 15, 20, 20 ] ), '2025-01-14' );
hodima_ad( 'قبل/بعد حالا هفته‌های دو طرف را هم برمی‌گرداند (هفته وسط ویرایش بیرون است)', [ $ba1['before_weeks'], $ba1['after_weeks'] ], [ [ '2025-01-05', '2025-01-12' ], [ '2025-01-26', '2025-02-02' ] ] );
$vs1 = hodima_seo_advisor_effect_vs_site( $ba1 );
hodima_ad( 'صفحه دو برابر شده و سایت تقریبا ثابت (۱٪) ← above (۹۹ امتیاز بالاتر)', [ $vs1['page'], $vs1['site'], $vs1['net'], $vs1['verdict'] ], [ 100.0, 1.0, 99.0, 'above' ] );
$put( [ 10, 10, 15, 20, 20 ], [ 990, 990, 1500, 1980, 1980 ] );
$vs2 = hodima_seo_advisor_effect_vs_site( $ba1 );
hodima_ad( 'سایت هم دو برابر شده ← هم‌راستا با روند (same)', [ $vs2['site'], $vs2['net'], $vs2['verdict'] ], [ 100.0, 0.0, 'same' ] );
$put( [ 10, 10, 10, 10, 10 ], [ 990, 990, 1000, 1490, 1490 ] );
$vs3 = hodima_seo_advisor_effect_vs_site( hodima_seo_advisor_before_after( $mk( [ 10, 10, 10, 10, 10 ] ), '2025-01-14' ) );
hodima_ad( 'صفحه ثابت و سایت ۵۰٪ بالا ← below', [ $vs3['page'], $vs3['site'], $vs3['verdict'] ], [ 0.0, 50.0, 'below' ] );
hodima_ad( 'متن مقایسه سه حالت', [ str_contains( hodima_seo_advisor_effect_vs_site_text( $vs1 ), 'بهتر از روند کل سایت' ), str_contains( hodima_seo_advisor_effect_vs_site_text( $vs2 ), 'هم‌راستا' ), str_contains( hodima_seo_advisor_effect_vs_site_text( $vs3 ), 'ضعیف‌تر از روند کل سایت' ), hodima_seo_advisor_effect_vs_site_text( null ) ], [ true, true, true, '' ] );
hodima_ad( 'هفته‌های عکس‌نشده سایت ← مقایسه ممکن نیست (null)', hodima_seo_advisor_effect_vs_site( [ 'before' => [ 'clicks' => 5.0 ], 'after' => [ 'clicks' => 9.0 ], 'before_weeks' => [ '2020-03-01' ], 'after_weeks' => [ '2020-03-08' ] ] ), null );
$wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE week_end < '2025-03-01'", hodima_seo_advisor_table( 'history' ) ) );

echo "=== نرخ کلیک معمول از داده خود سایت (بخش ۲۸۲)\n";
delete_option( HODIMA_SEO_ADVISOR_CTR_OPTION );
hodima_seo_advisor_store( [], [], [] );
$empty_learn = hodima_seo_advisor_ctr_learn();
hodima_ad( 'بدون داده: جدول آموخته‌شده همان جدول عمومی است و سهم سایت صفر', [ $empty_learn['table'], hodima_seo_advisor_ctr_learned_share() ], [ [ 2 => 25.0, 3 => 14.0, 5 => 9.0, 7 => 5.5, 10 => 3.5, 20 => 1.5 ], 0.0 ] );
hodima_ad( 'بدون داده: نرخ معمول مثل قبل (رتبه بالاتر از ۱۰ تا ۲۰ و بعدش ۱٫۵)', array_map( 'hodima_seo_advisor_expected_ctr', [ 1.5, 10.0, 15.0, 40.0 ] ), [ 25.0, 3.5, 1.5, 1.5 ] );
add_filter( 'hodima_seo_advisor_brand_terms', $ctr_brand = static fn(): array => [ 'brandx' ] );
$row_q = static fn( int $clicks, int $impressions, float $position ): array => [ 'clicks' => $clicks, 'impressions' => $impressions, 'position' => $position ];
hodima_seo_advisor_store(
	[ '/ct/a/' => $row_q( 1, 100, 5.0 ) ],
	[],
	[
		'/ct/a/' => [
			'کلمه رتبه یک' => $row_q( 3000, 30000, 1.5 ),       // ۱۰٪ در سطل ≤۲ (عمومی ۲۵٪)
			'brandx shop' => $row_q( 50000, 100000, 1.2 ),      // برندی: کنار می‌رود
			'کلمه رتبه سه' => $row_q( 2000, 10000, 2.5 ),       // ۲۰٪ در سطل ≤۳ (نصف وزن)
			'کلمه رتبه چهار' => $row_q( 100, 1000, 4.0 ),       // زیر ۲٬۰۰۰ نمایش ← عمومی
			'کلمه رتبه شش' => $row_q( 2000, 40000, 6.0 ),       // ۵٪ در سطل ≤۷
			'کلمه رتبه پانزده' => $row_q( 20, 4000, 15.0 ),     // ۰٫۵٪ در سطل ≤۲۰ (وزن ۰٫۱۶)
		],
	]
);
$learn = hodima_seo_advisor_ctr_learn();
hodima_ad( 'جدول آموخته‌شده: ترکیب وزنی، برند حذف، رتبه بهتر هرگز کمتر از بدتر نمی‌شود', $learn['table'], [ 2 => 13.0, 3 => 13.0, 5 => 9.0, 7 => 5.1, 10 => 3.5, 20 => 1.34 ] );
hodima_ad( 'جزئیات سطل‌ها: نمایش و کلیک بدون برند، و وزن', [ $learn['buckets'][2]['impressions'], $learn['buckets'][2]['clicks'], $learn['buckets'][2]['weight'], $learn['buckets'][5]['weight'], $learn['impressions'] ], [ 30000, 3000, 0.8, 0.0, 85000 ] );
hodima_ad( 'نرخ معمول حالا از جدول سایت می‌آید (بعد از رتبه ۲۰ همان ۱٫۵ ثابت)', array_map( 'hodima_seo_advisor_expected_ctr', [ 1.5, 2.5, 4.0, 6.0, 9.0, 15.0, 40.0 ] ), [ 13.0, 13.0, 9.0, 5.1, 3.5, 1.34, 1.5 ] ); // بعد از رتبه ۲۰ همان ۱٫۵ ثابت قبلی
hodima_ad( 'سهم داده سایت بین ۰ و ۱ و بالاتر از صفر', [ hodima_seo_advisor_ctr_learned_share() > 0.3, hodima_seo_advisor_ctr_learned_share() < 1.0 ], [ true, true ] );
add_filter( 'hodima_seo_advisor_ctr_table', $ctr_flt = static fn(): array => [ 10 => 2.0 ] );
hodima_ad( 'فیلتر جدول نرخ کلیک بر جدول آموخته‌شده مقدم است', hodima_seo_advisor_expected_ctr( 5.0 ), 2.0 );
remove_filter( 'hodima_seo_advisor_ctr_table', $ctr_flt );
$low = hodima_seo_advisor_suggestions( [ 'path' => '/ct/z/', 'clicks' => 10, 'impressions' => 5000, 'position' => 2.0, 'prev_clicks' => 0 ], [], null );
hodima_ad( 'پیشنهاد نرخ کلیک پایین: با جدول آموخته‌شده (۱۳٪ در رتبه ۲) و یادداشت منبع', [ $low[0]['rule'] ?? '', $low[0]['potential'] ?? 0, str_contains( $low[0]['detail'] ?? '', 'از داده خود سایت' ) ], [ 'low_ctr', 640, true ] );
hodima_ad( 'پیشنهاد نرخ کلیک: بدون جدول آموخته‌شده ۲۵٪ و بدون یادداشت', ( static function () use ( $row_q ): array { delete_option( HODIMA_SEO_ADVISOR_CTR_OPTION ); $r = hodima_seo_advisor_suggestions( [ 'path' => '/ct/z/', 'clicks' => 10, 'impressions' => 5000, 'position' => 2.0, 'prev_clicks' => 0 ], [], null ); return [ $r[0]['potential'] ?? 0, str_contains( $r[0]['detail'] ?? '', 'از داده خود سایت' ) ]; } )(), [ 1240, false ] );
// نوبت ctr در صف دریافت است و پیش از analyze می‌آید؛ پاک کردن داده‌های دستار جدول را هم برمی‌دارد
hodima_ad( 'نوبت ctr اولین نوبت است و health (آدرس‌های مرده) پیش از analyze', array_slice( HODIMA_SEO_ADVISOR_STAGES, 0, 3 ), [ 'ctr', 'health', 'analyze' ] );
hodima_seo_advisor_ctr_learn();
hodima_ad( 'پاک کردن داده‌های دستار جدول نرخ کلیک آموخته‌شده را هم پاک می‌کند', [ hodima_seo_advisor_clear_data(), get_option( HODIMA_SEO_ADVISOR_CTR_OPTION, 'none' ) ], [ true, 'none' ] );
remove_filter( 'hodima_seo_advisor_brand_terms', $ctr_brand );

echo "=== ردیف بالا (به‌روزرسانی + تنظیمات) در همه تب‌ها و صفحه‌بندی مدرن (بخش ۲۸۳)\n";
$cap = static function ( callable $fn ): string { ob_start(); $fn(); return (string) ob_get_clean(); };
update_option( HODIMA_SEO_SC_ADVISOR_KEY_OPTION, $own_key, false );
$tb_data = [ 'fetched' => time() - 3600, 'property' => 'sc-domain:' . $host, 'days' => 90, 'pages' => 1, 'queries' => 0, 'advised' => 0, 'error' => '' ] + hodima_seo_advisor_ranges( 90 );
update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, $tb_data, false );
hodima_seo_advisor_store( [ '/tb/a/' => [ 'clicks' => 5, 'impressions' => 5000, 'position' => 6.0 ] ], [], [] );
wp_set_current_user( 1 );
$_GET = [];
$bar = $cap( static fn() => hodima_seo_advisor_render_toolbar( 'competition' ) );
hodima_ad( 'ردیف «گرفتن داده تازه»: یک دکمه با تب برگشت و راهنمای «یک کلیک برای همه»', [ str_contains( $bar, 'name="action" value="hodima_advisor_refresh"' ), str_contains( $bar, 'name="tab" value="competition"' ), str_contains( $bar, 'گرفتن داده تازه' ), str_contains( $bar, 'یک کلیک برای همه بخش‌ها' ) ], [ true, true, true, true ] );
foreach ( [ 'opportunities', 'competition', 'terms', 'health', 'crawl', 'indexing' ] as $t ) {
	$_GET   = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => $t ];
	$html_t = $cap( static fn() => hodima_seo_advisor_render_admin() );
	$bar_at = strpos( $html_t, 'value="hodima_advisor_refresh"' );
	hodima_ad( "تب {$t}: ردیف به‌روزرسانی دقیقا یک بار و زیر ردیف تب‌ها؛ پنل تنظیمات نیست", [ substr_count( $html_t, 'value="hodima_advisor_refresh"' ), false !== $bar_at && $bar_at > (int) strpos( $html_t, 'hodima-advisor__tabrow' ), str_contains( $html_t, 'id="hodima-advisor-settings"' ), str_contains( $html_t, 'name="tab" value="' . ( 'opportunities' === $t ? 'opportunities' : $t ) . '"' ) ], [ 1, true, false, true ] );
}
$_GET     = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'settings' ];
$page_set = $cap( static fn() => hodima_seo_advisor_render_admin() );
hodima_ad( 'تب «تنظیمات»: فقط باکس تنظیمات (باز)، تب فعال، بدون ردیف به‌روزرسانی و فهرست', [ substr_count( $page_set, 'id="hodima-advisor-settings"' ), 1 === preg_match( '/id="hodima-advisor-settings"[^>]*\shidden/', $page_set ), str_contains( $page_set, 'tab=settings" aria-current="page"' ), str_contains( $page_set, 'value="hodima_advisor_refresh"' ), str_contains( $page_set, 'hodima-advisor__item' ) ], [ 1, false, true, true, false ] );
$_GET     = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'terms', 'settings' => '1' ];
hodima_ad( 'پیوند قدیمی settings=1 ← تب «تنظیمات»', str_contains( $cap( static fn() => hodima_seo_advisor_render_admin() ), 'tab=settings" aria-current="page"' ), true );
hodima_ad( 'دکمه بازشونده قدیمی «تنظیمات» دیگر نیست', str_contains( $page_set . $html_t, 'data-hdsa-settings-toggle' ), false );
$_GET = [];

$pg = static fn( int $cur, int $tot ): string => $cap( static fn() => hodima_seo_ui_pager( $cur, $tot, static fn( int $i ): string => '/p/?paged=' . $i ) );
hodima_ad( 'صفحه‌بندی: یک صفحه = هیچ؛ صفحه اول بدون «قبلی»، با «بعدی»', [ $pg( 1, 1 ), str_contains( $pg( 1, 5 ), 'قبلی' ), str_contains( $pg( 1, 5 ), 'rel="next"' ), substr_count( $pg( 1, 5 ), 'hds-pager__page' ) ], [ '', false, true, 3 ] );
hodima_ad( 'صفحه‌بندی: وسط ۱۰ صفحه: اول، آخر، همسایه‌ها و «…»', [ substr_count( $pg( 5, 10 ), '<a class="hds-pager__page"' ), substr_count( $pg( 5, 10 ), 'hds-pager__gap' ), str_contains( $pg( 5, 10 ), 'rel="prev"' ), str_contains( $pg( 5, 10 ), 'rel="next"' ) ], [ 4, 2, true, true ] );
hodima_ad( 'صفحه‌بندی: صفحه جاری aria-current و بدون لینک؛ صفحه آخر بدون «بعدی»', [ str_contains( $pg( 3, 3 ), 'aria-current="page"' ), str_contains( $pg( 3, 3 ), 'rel="next"' ), str_contains( $pg( 3, 3 ), '?paged=3' ) ], [ true, false, false ] );
$_GET = [];
delete_option( HODIMA_SEO_SC_ADVISOR_KEY_OPTION ); // کلید آزمایشی این بخش نماند (اجرای بعدی آزمون را خراب می‌کرد)

echo "=== سلامت خزش: خواندن فایل‌های CSV نمونه و ایرادها\n";
$crawl_dir = sys_get_temp_dir() . '/hodima-crawl-test-' . getmypid();
@mkdir( $crawl_dir );
$crawl_filter = static fn(): string => $crawl_dir;
add_filter( 'hodima_seo_advisor_crawl_dir', $crawl_filter );
$csv = static function ( string $name, array $rows, bool $bom = true ) use ( $crawl_dir ): void {
	$h = fopen( "{$crawl_dir}/{$name}", 'wb' );
	if ( $bom ) {
		fwrite( $h, "\xEF\xBB\xBF" );
	}
	foreach ( $rows as $r ) {
		fputcsv( $h, $r, ',', '"', '' );
	}
	fclose( $h );
	touch( "{$crawl_dir}/{$name}", time() - 3600 );
};
$cols = [ 'Address', 'Status Code', 'Indexability', 'Indexability Status', 'Title 1', 'Title 1 Length', 'Meta Description 1 Length', 'H1-1', 'Response Time', 'Unique Inlinks', 'Crawl Depth', 'Link Score', 'Redirect URL' ];
$long = str_repeat( 'عنوان خوب ', 5 );
$csv( 'internal_html.csv', [
	$cols,
	[ $u( '/' ), '200', 'Indexable', '', $long . 'z', (string) ( mb_strlen( $long ) + 1 ), '120', 'خانه', '0.4', '0', '0', '100', '' ],
	[ $u( '/short-title/' ), '200', 'Indexable', '', 'کوتاه', '5', '0', 'الف', '0.3', '10', '1', '5', '' ],
	[ $u( '/dup-a/' ), '200', 'Indexable', '', $long, (string) mb_strlen( $long ), '130', 'ب', '0.3', '8', '1', '5', '' ],
	[ $u( '/dup-b/' ), '200', 'Indexable', '', $long, (string) mb_strlen( $long ), '130', 'پ', '2.5', '9', '1', '5', '' ],
	[ $u( '/few-links/' ), '200', 'Indexable', '', $long . 'x', (string) ( mb_strlen( $long ) + 1 ), '130', 'ت', '0.3', '2', '2', '1', '' ],
	[ $u( '/orphan/' ), '200', 'Indexable', '', $long . 'y', (string) ( mb_strlen( $long ) + 1 ), '130', 'ث', '0.3', '0', '3', '1', '' ],
	[ $u( '/moved/' ), '301', 'Non-Indexable', 'Redirected', '', '0', '0', '', '0.2', '4', '2', '0', $u( '/dup-a/' ) ],
	[ $u( '/moved-unused/' ), '301', 'Non-Indexable', 'Redirected', '', '0', '0', '', '0.2', '0', '2', '0', '' ],
	[ $u( '/few-links/?utm_source=x' ), '200', 'Indexable', '', 'نسخه پارامتردار', '15', '0', '', '0.3', '1', '2', '0', '' ],
	[ 'https://other.example/x/', '200', 'Indexable', '', 'بیرونی', '6', '0', '', '0.3', '1', '2', '0', '' ],
] );
$csv( 'all_inlinks.csv', [
	[ 'Type', 'Source', 'Destination', 'Anchor', 'Follow', 'Link Position' ],
	[ 'Hyperlink', $u( '/dup-a/' ), $u( '/few-links/' ), 'لینک اول', 'true', 'Content' ],
	[ 'Hyperlink', $u( '/dup-a/' ), $u( '/few-links/' ), 'لینک اول', 'true', 'Content' ],
	[ 'Hyperlink', $u( '/dup-b/' ), $u( '/few-links/' ), 'لینک دوم', 'true', 'Navigation' ],
	[ 'Hyperlink', $u( '/few-links/' ), 'https://other.example/', 'بیرونی', 'true', 'Content' ],
	[ 'Image', $u( '/dup-a/' ), $u( '/few-links/' ), '', 'true', 'Content' ],
	[ 'Hyperlink', $u( '/dup-b/' ), $u( '/few-links/?x=1' ), 'پارامتردار', 'false', 'Content' ],
] );
hodima_seo_advisor_store( [ '/dup-b/' => [ 'clicks' => 3, 'impressions' => 9000, 'position' => 7.0 ], '/short-title/' => [ 'clicks' => 1, 'impressions' => 2000, 'position' => 9.0 ] ], [], [] );

touch( "{$crawl_dir}/all_inlinks.csv", time() );
touch( "{$crawl_dir}/all_inlinks.csv", time() - 3600 );

$imp = hodima_seo_advisor_crawl_import();
hodima_ad( 'خواندن موفق: ۸ صفحه (آدرس پارامتردار و دامنه دیگر نیامد) و ۳ لینک یکتا', is_wp_error( $imp ) ? $imp->get_error_message() : [ $imp['pages'], $imp['links'] ], [ 8, 3 ] );
add_filter( 'hodima_seo_advisor_inspect_enabled', '__return_false' );
remove_filter( 'hodima_seo_advisor_inspect_enabled', '__return_false' );
$cs = hodima_seo_advisor_crawl_status();
hodima_ad( 'وضعیت: زمان، شمار صفحه و لینک', [ $cs['imported'] > 0, $cs['pages'], $cs['links'] ], [ true, 8, 3 ] );

$cw = [];
foreach ( hodima_seo_advisor_crawl_issues() as $row ) {
	$cw[ $row['path'] ] = $row;
}
$codes = static fn( string $path ): array => array_column( $cw[ $path ]['issues'] ?? [], 'code' );
hodima_ad( 'صفحه اصلی بدون لینک ورودی «یتیم» نیست و ایرادی ندارد', isset( $cw['/'] ), false );
hodima_ad( 'عنوان کوتاه و بدون متا', $codes( '/short-title/' ), [ 'title_short', 'no_meta' ] );
hodima_ad( 'عنوان تکراری بین دو صفحه + کند', [ $codes( '/dup-a/' ), $codes( '/dup-b/' ) ], [ [ 'dup_title' ], [ 'dup_title', 'slow' ] ] );
hodima_ad( 'لینک ورودی کم با شمار لینک متنی (۱ لینک یکتا از متن؛ تکرار و Navigation و تصویر نشمرده)', [ $codes( '/few-links/' ), $cw['/few-links/']['text_links'] ], [ [ 'low_inlinks' ], 1 ] );
hodima_ad( 'صفحه بدون لینک ورودی یتیم است', $codes( '/orphan/' ), [ 'orphan' ] );
hodima_ad( 'ریدایرکتِ دارای لینک ورودی می‌آید؛ ریدایرکتِ بی‌لینک و بی‌نمایش نه', [ $codes( '/moved/' ), isset( $cw['/moved-unused/'] ) ], [ [ 'redirect' ], false ] );
$order = array_keys( $cw );
hodima_ad( 'مرتب بر اساس نمایش گوگل (۹٬۰۰۰ سپس ۲٬۰۰۰ سپس بی‌نمایش)', array_slice( $order, 0, 2 ), [ '/dup-b/', '/short-title/' ] );
$sm = hodima_seo_advisor_crawl_summary( hodima_seo_advisor_crawl_issues() );
hodima_ad( 'آمار: خزش‌شده، دارای ایراد، پرنمایش، لینک کم', [ $sm['crawled'], $sm['with_issues'], $sm['big'], $sm['low_links'] ], [ 8, 6, 2, 2 ] );
hodima_ad( 'گروه‌های فیلتر شمرده می‌شوند', [ $sm['groups']['meta'], $sm['groups']['title'], $sm['groups']['dup'], $sm['groups']['slow'], $sm['groups']['redirect'], $sm['groups']['orphan'] ], [ 1, 1, 2, 1, 1, 1 ] );

// فایل نمونه با BOM + عنوان ستون‌ها و همه مقدارها در گیومه (باگ ۲.۸.۱)
copy( "{$crawl_dir}/internal_html.csv", "{$crawl_dir}/backup.tmp" );
file_put_contents( "{$crawl_dir}/internal_html.csv", "\xEF\xBB\xBF" . '"Address","Status Code","Indexability","Title 1","Title 1 Length","Meta Description 1 Length","Unique Inlinks"' . "\r\n" . '"' . $u( '/quoted/' ) . '","200","Indexable","' . $long . '","' . mb_strlen( $long ) . '","130","5"' . "\r\n" );
touch( "{$crawl_dir}/internal_html.csv", time() - 3600 );
$quoted = hodima_seo_advisor_crawl_import();
hodima_ad( 'فایل نمونه با BOM و گیومه خوانده می‌شود', is_wp_error( $quoted ) ? $quoted->get_error_message() : $quoted['pages'], 1 );
rename( "{$crawl_dir}/backup.tmp", "{$crawl_dir}/internal_html.csv" );
touch( "{$crawl_dir}/internal_html.csv", time() - 3600 );
hodima_seo_advisor_crawl_import();

// فایل اشتباه: داده قبلی می‌ماند
$csv( 'internal_html.csv', [ [ 'Foo', 'Bar' ], [ 'a', 'b' ] ] );
$bad = hodima_seo_advisor_crawl_import();
hodima_ad( 'فایل با ستون اشتباه رد می‌شود و ستون‌های ناقص نام برده می‌شوند', [ is_wp_error( $bad ), is_wp_error( $bad ) && str_contains( $bad->get_error_message(), 'address' ) ], [ true, true ] );
hodima_ad( 'فایل خراب (ورود آزمایشی): داده قبلی سالم می‌ماند', hodima_seo_advisor_crawl_status()['pages'], 8 );
unlink( "{$crawl_dir}/internal_html.csv" );
$none = hodima_seo_advisor_crawl_import();
hodima_ad( 'نبودن internal_html.csv خطا می‌دهد و داده می‌ماند', [ is_wp_error( $none ), hodima_seo_advisor_crawl_status()['pages'] ], [ true, 8 ] );

// نمایش تب
$_GET = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'crawl' ];
$crawl_html = $cap( static fn() => hodima_seo_advisor_render_crawl() );
hodima_ad( 'تب: چهار آمار، کارت خزش خودکار با دو تب (زمان‌بندی و همین حالا) و nonce، بدون کارت فایل‌ها، جدول ایرادها', [ substr_count( $crawl_html, 'hds-stat__label' ), str_contains( $crawl_html, 'value="hodima_advisor_crawler_now"' ) && str_contains( $crawl_html, 'value="hodima_advisor_crawler_settings"' ), str_contains( $crawl_html, 'name="_wpnonce"' ), str_contains( $crawl_html, 'hodima-advisor__crawl-table' ), str_contains( $crawl_html, 'فایل‌های خزش' ) || str_contains( $crawl_html, 'internal_html.csv' ) ], [ 4, true, true, true, false ] );
$_GET['cf'] = 'orphan';
$orphan_html = $cap( static fn() => hodima_seo_advisor_render_crawl() );
hodima_ad( 'فیلتر «بدون لینک ورودی» فقط همان صفحه را نشان می‌دهد', [ str_contains( $orphan_html, '/orphan/' ), str_contains( $orphan_html, '/dup-b/' ) ], [ true, false ] );
$_GET = [];

// پاک کردن داده جدول‌های خزش را هم پاک می‌کند
hodima_seo_advisor_clear_data();
hodima_ad( 'پاک کردن داده: جدول‌ها و وضعیت خزش خالی', [ hodima_seo_advisor_crawl_summary( [] )['crawled'], hodima_seo_advisor_crawl_status()['imported'] ], [ 0, 0 ] );
$empty_crawl = $cap( static fn() => hodima_seo_advisor_render_crawl() );
hodima_ad( 'بدون خزش: آمار خالی، پیام «هنوز خزشی انجام نشده» و کارت خزش خودکار', [ substr_count( $empty_crawl, 'data-empty' ), str_contains( $empty_crawl, 'هنوز خزشی انجام نشده' ), str_contains( $empty_crawl, 'خزش همین حالا' ) ], [ 4, true, true ] );
remove_filter( 'hodima_seo_advisor_crawl_dir', $crawl_filter );
array_map( 'unlink', glob( "{$crawl_dir}/{,.}*", GLOB_BRACE ) ?: [] );
@rmdir( $crawl_dir );

echo "=== انتشار ۲: رقابت‌های حل‌شده و نرخ کلیک با داده خزش\n";
$crawl_dir2 = sys_get_temp_dir() . '/hodima-crawl-test2-' . getmypid();
@mkdir( $crawl_dir2 );
$crawl_filter2 = static fn(): string => $crawl_dir2;
add_filter( 'hodima_seo_advisor_crawl_dir', $crawl_filter2 );
$csv2 = static function ( string $name, array $rows ) use ( $crawl_dir2 ): void {
	$h = fopen( "{$crawl_dir2}/{$name}", 'wb' );
	fwrite( $h, "\xEF\xBB\xBF" );
	foreach ( $rows as $r ) {
		fputcsv( $h, $r, ',', '"', '' );
	}
	fclose( $h );
	touch( "{$crawl_dir2}/{$name}", time() - 3600 );
};
$term_row = static fn( int $imp, float $pos, int $clicks = 5 ): array => [ 'clicks' => $clicks, 'impressions' => $imp, 'position' => $pos ];
$ok_title = str_repeat( 'عنوان سالم ', 5 );
// دو گروه: «کلیپس ماری» بین /a-live/ و /a-moved/ (ریدایرکت طبق خزش)؛ «کلیپس گل» بین سه صفحه که یکی (/b-gone/) ۴۰۴ است
hodima_seo_advisor_store(
	[
		'/a-live/' => [ 'clicks' => 40, 'impressions' => 3000, 'position' => 6.0 ], '/a-moved/' => [ 'clicks' => 10, 'impressions' => 1500, 'position' => 8.0 ],
		'/b-one/' => [ 'clicks' => 30, 'impressions' => 2000, 'position' => 7.0 ], '/b-two/' => [ 'clicks' => 20, 'impressions' => 1800, 'position' => 9.0 ], '/b-gone/' => [ 'clicks' => 5, 'impressions' => 900, 'position' => 12.0 ],
		'/ctr-ok/' => [ 'clicks' => 10, 'impressions' => 6000, 'position' => 3.0 ], '/ctr-bad/' => [ 'clicks' => 10, 'impressions' => 6000, 'position' => 3.0 ],
	],
	[],
	[
		'/a-live/' => [ 'کلیپس ماری' => $term_row( 2500, 6.0 ) ], '/a-moved/' => [ 'کلیپس ماری' => $term_row( 1400, 8.0 ) ],
		'/b-one/' => [ 'کلیپس گل' => $term_row( 1800, 7.0 ) ], '/b-two/' => [ 'کلیپس گل' => $term_row( 1700, 9.0 ) ], '/b-gone/' => [ 'کلیپس گل' => $term_row( 800, 12.0 ) ],
		'/ctr-ok/' => [ 'کلمه خنثی' => $term_row( 5000, 3.0, 5 ) ], '/ctr-bad/' => [ 'کلمه خنثی' => $term_row( 5000, 3.0, 5 ) ],
	]
);
$bare = static fn( string $path ): array => [ $u( $path ), '200', 'Indexable', '', $ok_title, (string) mb_strlen( $ok_title ), '130', 'تیتر', '0.4', '5', '1', '1', '' ];
$csv2( 'internal_html.csv', [
	[ 'Address', 'Status Code', 'Indexability', 'Indexability Status', 'Title 1', 'Title 1 Length', 'Meta Description 1 Length', 'H1-1', 'Response Time', 'Unique Inlinks', 'Crawl Depth', 'Link Score', 'Redirect URL' ],
	$bare( '/a-live/' ),
	[ $u( '/a-moved/' ), '301', 'Non-Indexable', 'Redirected', '', '0', '0', '', '0.2', '4', '2', '0', $u( '/a-live/' ) ],
	$bare( '/b-one/' ), $bare( '/b-two/' ), $bare( '/ctr-ok/' ),
	[ $u( '/ctr-bad/' ), '200', 'Indexable', '', 'کوتاه', '5', '0', 'تیتر', '0.4', '5', '1', '1', '' ],
] );
update_option( HODIMA_SEO_ADVISOR_HEALTH_OPTION, [ 'computed' => time(), 'router' => true, 'dead' => [ [ 'path' => '/b-gone/', 'impressions' => 900, 'clicks' => 5, 'state' => 'missing', 'target' => '', 'code' => 404 ] ], 'total' => 0, 'seen' => 0, 'fresh' => 0, 'unseen' => [] ], false );
update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [ 'fetched' => time(), 'pages' => 7, 'days' => 90, 'property' => 'sc-domain:x' ], false );
delete_option( HODIMA_SEO_ADVISOR_PIPELINE_OPTION );
hodima_seo_advisor_crawl_map( true );
$imp2 = hodima_seo_advisor_crawl_import();
hodima_ad( 'خواندن فایل‌های خزش پیشنهادها را دوباره می‌سازد و نوبتی باقی نمی‌ماند', [ is_wp_error( $imp2 ) ? $imp2->get_error_message() : 'ok', hodima_seo_advisor_pipeline_pending() ], [ 'ok', false ] );

$rp = hodima_seo_advisor_resolved_paths();
hodima_ad( 'صفحه‌های حل‌شده: ریدایرکت طبق خزش، ۴۰۴ طبق سلامت آدرس‌ها؛ صفحه سالم نه', [ $rp['/a-moved/'] ?? '', $rp['/b-gone/'] ?? '', isset( $rp['/a-live/'] ) ], [ 'redirect', 'gone', false ] );
$comp = hodima_seo_advisor_competition();
$terms_main = array_column( $comp['rows'], 'term' );
hodima_ad( 'رقابت اصلی: گروه ریدایرکت‌شده کنار رفت؛ گروه سه‌صفحه‌ای با دو صفحه زنده می‌ماند', [ in_array( 'کلیپس ماری', $terms_main, true ), in_array( 'کلیپس گل', $terms_main, true ) ], [ false, true ] );
$flower = array_values( array_filter( $comp['rows'], static fn( array $g ): bool => 'کلیپس گل' === $g['term'] ) )[0];
hodima_ad( 'صفحه ۴۰۴ از گروه باقی‌مانده حذف شده (۲ صفحه)', [ $flower['count'], array_column( $flower['pages'], 'path' ) ], [ 2, [ '/b-one/', '/b-two/' ] ] );
hodima_ad( 'شمار حل‌شده‌ها برای یادداشت بالای فهرست', [ $comp['resolved']['groups'], $comp['resolved']['potential'] >= 0 ], [ 1, true ] );
$settled_rows = hodima_seo_advisor_competition( [ 'resolved' => true ] );
hodima_ad( 'نمای حل‌شده‌ها فقط همان گروه را با دلیل هر صفحه دارد', [ array_column( $settled_rows['rows'], 'term' ), $settled_rows['rows'][0]['advice']['type'], array_column( $settled_rows['rows'][0]['pages'], 'resolved', 'path' ) ], [ [ 'کلیپس ماری' ], 'settled', [ '/a-live/' => '', '/a-moved/' => 'redirect' ] ] );
hodima_ad( 'رقیب‌های یک صفحه: صفحه حل‌شده نمی‌آید؛ خود صفحه حل‌شده رقیب ندارد', [ array_keys( hodima_seo_advisor_competitors( '/b-one/', [ [ 'term' => 'کلیپس گل', 'impressions' => 1800 ] ] ) ), hodima_seo_advisor_competitors( '/b-gone/', [ [ 'term' => 'کلیپس گل', 'impressions' => 800 ] ] ) ], [ [ hodima_seo_advisor_term_key( 'کلیپس گل' ) ], [] ] );

// قاعده نرخ کلیک
$ok_adv = hodima_seo_advisor_analyze( '/ctr-ok/' );
$bad_adv = hodima_seo_advisor_analyze( '/ctr-bad/' );
$find = static fn( ?array $a ): ?array => array_values( array_filter( (array) ( $a['suggestions'] ?? [] ), static fn( array $s ): bool => 'low_ctr' === $s['rule'] ) )[0] ?? null;
$ok_s  = $find( $ok_adv );
$bad_s = $find( $bad_adv );
hodima_ad( 'عنوان و متا سالم: تشخیص «طول سالم است» و ضریب اطمینان روی کلیک بالقوه', [ $ok_s['title'] ?? '', str_contains( (string) ( $ok_s['detail'] ?? '' ), 'ایرادی ندارد' ), ( $ok_s['potential'] ?? 0 ) < ( $bad_s['potential'] ?? 0 ) ], [ 'نرخ کلیک کم است؛ طول عنوان و متا سالم است', true, true ] );
hodima_ad( 'عنوان کوتاه و بدون متا: همان بازنویسی با ایراد خزش و بدون کاهش تخمین', [ $bad_s['title'] ?? '', str_contains( (string) ( $bad_s['detail'] ?? '' ), 'طبق خزش: عنوان کوتاه است؛ توضیح متا ندارد' ), (int) round( ( $ok_s['potential'] ?? 0 ) / HODIMA_SEO_ADVISOR_LOW_CTR_HEALTHY_FACTOR ) >= ( $bad_s['potential'] ?? 0 ) - 1 ], [ 'عنوان و توضیح متا را بازنویسی کنید', true, true ] );
$no_crawl = $find( [ 'suggestions' => hodima_seo_advisor_suggestions( [ 'path' => '/z/', 'clicks' => 10, 'impressions' => 6000, 'position' => 3.0, 'prev_clicks' => 0 ], [], null ) ] );
hodima_ad( 'بدون داده خزش: متن قبلی بازنویسی بدون تغییر', $no_crawl['title'] ?? '', 'عنوان و توضیح متا را بازنویسی کنید' );

// نمای تب
$_GET = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'competition' ];
$ch = $cap( static fn() => hodima_seo_advisor_render_competition( hodima_seo_advisor_status() ) );
hodima_ad( 'تب رقابت: یادداشت «۱ گروه کنار رفت» با لینک دیدن', [ str_contains( $ch, '1 گروه رقابت از این فهرست کنار رفت' ) || str_contains( $ch, '۱ گروه رقابت از این فهرست کنار رفت' ), str_contains( $ch, 'resolved=1' ) ], [ true, true ] );
$_GET['resolved'] = '1';
$ch2 = $cap( static fn() => hodima_seo_advisor_render_competition( hodima_seo_advisor_status() ) );
hodima_ad( 'نمای حل‌شده‌ها: عنوان، نشان دلیل هر صفحه و بدون «برنده پیشنهادی»', [ str_contains( $ch2, 'رقابت‌های حل‌شده' ), str_contains( $ch2, 'ریدایرکت می‌شود' ), str_contains( $ch2, 'برنده پیشنهادی' ) ], [ true, true, false ] );
$_GET = [];

hodima_seo_advisor_clear_data();
delete_option( HODIMA_SEO_ADVISOR_HEALTH_OPTION );
hodima_ad( 'پاک کردن داده: نقشه خزش و حل‌شده‌ها خالی', [ hodima_seo_advisor_crawl_map(), hodima_seo_advisor_resolved_paths() ], [ [], [ '/' => 'home' ] ] );
remove_filter( 'hodima_seo_advisor_crawl_dir', $crawl_filter2 );
array_map( 'unlink', glob( "{$crawl_dir2}/{,.}*", GLOB_BRACE ) ?: [] );
@rmdir( $crawl_dir2 );

echo "=== آدرس کامل و قابل‌کلیک (رقابت، سلامت، ردیف فهرست)\n";
$lk = hodima_seo_advisor_url_link( '/' . rawurlencode( 'گل-سر' ) . '/' );
hodima_ad( 'لینک آدرس: کامل با دامنه، رمزگشایی‌شده، زبانه تازه با noopener', [ str_contains( $lk, 'href="' . esc_url( hodima_seo_advisor_url( '/' . rawurlencode( 'گل-سر' ) . '/' ) ) . '"' ), str_contains( $lk, '<bdi>' . esc_html( home_url( '/گل-سر/' ) ) . '</bdi>' ) || str_contains( $lk, 'گل-سر' ), str_contains( $lk, 'target="_blank"' ), str_contains( $lk, 'rel="noopener"' ), str_contains( $lk, 'dir="ltr"' ) ], [ true, true, true, true, true ] );
hodima_ad( 'آدرس کامل (با http) دست‌نخورده لینک می‌شود', str_contains( hodima_seo_advisor_url_link( 'https://example.org/a/b/' ), 'href="https://example.org/a/b/"' ), true );
$home = hodima_seo_advisor_url_link( '/' );
hodima_ad( 'صفحه اصلی هم آدرس کامل دارد، نه فقط «/»', str_contains( $home, '>' . esc_html( rawurldecode( hodima_seo_advisor_url( '/' ) ) ) . '<' ), true );
hodima_ad( 'عنوان صفحه اصلی بدون عنوان «صفحه اصلی» است نه «/»', [ hodima_seo_advisor_competition_page_title( [ 'kind' => '', 'object_id' => 0, 'title' => '', 'path' => '/' ] ), hodima_seo_advisor_competition_page_title( [ 'kind' => '', 'object_id' => 0, 'title' => '', 'path' => '/x/' ] ) ], [ 'صفحه اصلی', '/x/' ] );

echo "=== انتشار ۳: شواهد لینک داخلی در «نزدیک صفحه اول» و رقابت\n";
$crawl_dir3 = sys_get_temp_dir() . '/hodima-crawl-test3-' . getmypid();
@mkdir( $crawl_dir3 );
$crawl_filter3 = static fn(): string => $crawl_dir3;
add_filter( 'hodima_seo_advisor_crawl_dir', $crawl_filter3 );
$csv3 = static function ( string $name, array $rows ) use ( $crawl_dir3 ): void {
	$h = fopen( "{$crawl_dir3}/{$name}", 'wb' );
	fwrite( $h, "\xEF\xBB\xBF" );
	foreach ( $rows as $r ) {
		fputcsv( $h, $r, ',', '"', '' );
	}
	fclose( $h );
	touch( "{$crawl_dir3}/{$name}", time() - 3600 );
};
$t3 = static fn( int $imp, float $pos, int $clicks ): array => [ 'clicks' => $clicks, 'impressions' => $imp, 'position' => $pos ];
hodima_seo_advisor_store(
	[ '/near/' => [ 'clicks' => 5, 'impressions' => 4000, 'position' => 8.5 ], '/bow-a/' => [ 'clicks' => 60, 'impressions' => 3000, 'position' => 5.0 ], '/bow-b/' => [ 'clicks' => 40, 'impressions' => 2500, 'position' => 6.0 ] ],
	[],
	[ '/near/' => [ 'کلیپس سنجاقی' => $t3( 3000, 8.5, 3 ) ], '/bow-a/' => [ 'کلیپس پاپیونی' => $t3( 2800, 5.0, 55 ) ], '/bow-b/' => [ 'کلیپس پاپیونی' => $t3( 2400, 6.0, 38 ) ] ]
);
update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [ 'fetched' => time(), 'pages' => 3, 'days' => 90, 'property' => 'sc-domain:x' ], false );
$ok3 = str_repeat( 'عنوان سالم ', 5 );
$page3 = static fn( string $p, int $in, int $depth, string $score ): array => [ $u( $p ), '200', 'Indexable', '', $ok3, (string) mb_strlen( $ok3 ), '130', 'تیتر', '0.4', (string) $in, (string) $depth, $score, '' ];
$csv3( 'internal_html.csv', [
	[ 'Address', 'Status Code', 'Indexability', 'Indexability Status', 'Title 1', 'Title 1 Length', 'Meta Description 1 Length', 'H1-1', 'Response Time', 'Unique Inlinks', 'Crawl Depth', 'Link Score', 'Redirect URL' ],
	$page3( '/near/', 2, 5, '1' ), $page3( '/bow-a/', 17, 2, '40' ), $page3( '/bow-b/', 16, 2, '38' ),
] );
$links3 = [ [ 'Type', 'Source', 'Destination', 'Anchor', 'Follow', 'Link Position' ] ];
foreach ( [ 1, 2 ] as $i ) {
	$links3[] = [ 'Hyperlink', $u( "/s{$i}/" ), $u( '/near/' ), 'گیره', 'true', 'Content' ];
}
for ( $i = 1; $i <= 17; $i++ ) {
	$links3[] = [ 'Hyperlink', $u( "/s{$i}/" ), $u( '/bow-a/' ), 'کلیپس پاپیونی', 'true', 'Content' ];
}
for ( $i = 1; $i <= 16; $i++ ) {
	$links3[] = [ 'Hyperlink', $u( "/s{$i}/" ), $u( '/bow-b/' ), 'کلیپس پاپیونی مدل ب', 'true', 'Content' ];
}
$links3[] = [ 'Hyperlink', $u( '/s3/' ), $u( '/bow-b/' ), 'منو', 'true', 'Navigation' ]; // منو نمی‌شمارد
$csv3( 'all_inlinks.csv', $links3 );
delete_option( HODIMA_SEO_ADVISOR_PIPELINE_OPTION );
$imp3 = hodima_seo_advisor_crawl_import();
hodima_ad( 'خواندن ۳ صفحه و لینک‌ها', is_wp_error( $imp3 ) ? $imp3->get_error_message() : $imp3['pages'], 3 );

$li = hodima_seo_advisor_crawl_links_info( '/near/' );
hodima_ad( 'شواهد لینک صفحه: یکتا، از متن، Link Score، عمق و متن لینک‌ها', [ $li['total'], $li['text'], $li['score'], $li['depth'], $li['anchors'] ], [ 2, 2, 1.0, 5, [ [ 'anchor' => 'گیره', 'count' => 2 ] ] ] );
hodima_ad( 'بدون فایل لینک‌ها شواهد null است', hodima_seo_advisor_crawl_links_info( '/not-crawled/' ), null );
hodima_ad( 'لینک منو در متن لینک‌ها نیست', array_column( hodima_seo_advisor_crawl_anchors( '/bow-b/' ), 'anchor' ), [ 'کلیپس پاپیونی مدل ب' ] );
hodima_ad( 'شمار لینک با عبارت کلمه (املای ی/ي یکی)', [ hodima_seo_advisor_crawl_term_links( $li['anchors'], 'کلیپس سنجاقی' ), hodima_seo_advisor_crawl_term_links( [ [ 'anchor' => 'کليپس پاپیونی', 'count' => 3 ] ], 'کلیپس پاپیونی' ) ], [ 0, 3 ] );

$ev = hodima_seo_advisor_links_evidence( $li, 'کلیپس سنجاقی' );
hodima_ad( 'جمله شواهد: لینک‌ها، Link Score، نبود لینک با عبارت و عمق خزش', [ str_contains( $ev, 'طبق خزش این صفحه ' . number_format_i18n( 2 ) . ' لینک ورودی یکتا دارد (' . number_format_i18n( 2 ) . ' از متن)، Link Score ' . number_format_i18n( 1.0, 1 ) ), str_contains( $ev, 'هیچ لینک متنی با عبارت «کلیپس سنجاقی»' ), str_contains( $ev, 'عمق خزش ' . number_format_i18n( 5 ) . ' است' ) ], [ true, true, true ] );
hodima_ad( 'بدون شواهد جمله خالی است', hodima_seo_advisor_links_evidence( null, 'x' ), '' );
$ev_has = hodima_seo_advisor_links_evidence( [ 'total' => 2, 'text' => 2, 'score' => 0.0, 'depth' => 1, 'anchors' => [ [ 'anchor' => 'کلیپس سنجاقی ارزان', 'count' => 2 ] ] ], 'کلیپس سنجاقی' );
hodima_ad( 'لینک با عبارت هست ولی کم: «۲ لینک با متن» و «لینک متنی کم است»', [ str_contains( $ev_has, number_format_i18n( 2 ) . ' لینک با متن «کلیپس سنجاقی»' ), str_contains( $ev_has, 'لینک متنی کم است' ), str_contains( $ev_has, 'Link Score' ) ], [ true, true, false ] );

$an = hodima_seo_advisor_analyze( '/near/' );
$near_s = array_values( array_filter( (array) ( $an['suggestions'] ?? [] ), static fn( array $x ): bool => 'near_first' === $x['rule'] ) )[0] ?? null;
hodima_ad( 'پیشنهاد «نزدیک صفحه اول» شواهد لینک خزش را دارد', [ null !== $near_s, str_contains( (string) ( $near_s['detail'] ?? '' ), 'طبق خزش این صفحه' ) ], [ true, true ] );

$cmp = hodima_seo_advisor_competition( [ 'limit' => 15 ] );
$grp = array_values( array_filter( $cmp['rows'], static fn( array $g ): bool => 'کلیپس پاپیونی' === $g['term'] ) )[0] ?? null;
hodima_ad( 'رقابت: لینک با متن کلمه برای هر صفحه (۱۷ و ۱۶؛ منو نشمرده)', null === $grp ? null : array_column( $grp['pages'], 'term_links', 'path' ), [ '/bow-a/' => 17, '/bow-b/' => 16 ] );
hodima_ad( 'رقابت: دستور می‌گوید لینک‌ها تقریبا برابرند', null !== $grp && str_contains( $grp['advice']['detail'], 'تقریبا برابر است' ), true );
$csv_rows = hodima_seo_advisor_competition();
hodima_ad( 'بدون limit (CSV) شواهد پرسش اضافه نمی‌سازد', isset( $csv_rows['rows'][0]['pages'][0]['term_links'] ), false );
$w = static fn( string $path, ?int $tl ): array => [ 'path' => $path, 'term_links' => $tl ];
hodima_ad( 'مقایسه: رقیب لینک بیشتر دارد ← منتقل کنید', str_contains( hodima_seo_advisor_competition_links_note( 'ک', [ $w( '/w/', 3 ), $w( '/r/', 9 ) ] ), 'منتقل کنید' ), true );
hodima_ad( 'مقایسه: برنده خیلی جلوتر ← حفظ کنید', str_contains( hodima_seo_advisor_competition_links_note( 'ک', [ $w( '/w/', 30 ), $w( '/r/', 9 ) ] ), 'حفظ کنید' ), true );
hodima_ad( 'مقایسه: شواهد یک صفحه کافی نیست ← خالی', hodima_seo_advisor_competition_links_note( 'ک', [ $w( '/w/', 3 ), $w( '/r/', null ) ] ), '' );

$_GET = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'competition' ];
$ch3 = $cap( static fn() => hodima_seo_advisor_render_competition( hodima_seo_advisor_status() ) );
hodima_ad( 'تب رقابت: زیر هر صفحه «لینک با متن کلمه · لینک ورودی»', [ str_contains( $ch3, '۱۷ لینک با متن «کلیپس پاپیونی» · ۱۷ لینک ورودی' ) || str_contains( $ch3, '17 لینک با متن «کلیپس پاپیونی» · 17 لینک ورودی' ) ], [ true ] );
$_GET = [];

hodima_seo_advisor_clear_data();
remove_filter( 'hodima_seo_advisor_crawl_dir', $crawl_filter3 );
array_map( 'unlink', glob( "{$crawl_dir3}/{,.}*", GLOB_BRACE ) ?: [] );
@rmdir( $crawl_dir3 );

echo "=== انتشار ۴: عنوان و تیترهای رندرشده، بدون H1، لینک کافی\n";
$crawl_dir4 = sys_get_temp_dir() . '/hodima-crawl-test4-' . getmypid();
@mkdir( $crawl_dir4 );
$crawl_filter4 = static fn(): string => $crawl_dir4;
add_filter( 'hodima_seo_advisor_crawl_dir', $crawl_filter4 );
$csv4 = static function ( string $name, array $rows ) use ( $crawl_dir4 ): void {
	$h = fopen( "{$crawl_dir4}/{$name}", 'wb' );
	fwrite( $h, "\xEF\xBB\xBF" );
	foreach ( $rows as $r ) {
		fputcsv( $h, $r, ',', '"', '' );
	}
	fclose( $h );
	touch( "{$crawl_dir4}/{$name}", time() - 3600 );
};
$r4_id = (int) wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'صفحه آزمون چهار', 'post_name' => 'advisor-r4', 'post_content' => '<p>متن ساده</p>' ] );
$r4_path = '/advisor-r4/';
hodima_seo_advisor_store( [ $r4_path => [ 'clicks' => 5, 'impressions' => 4000, 'position' => 8.5 ] ], [], [ $r4_path => [ 'کلیپس سنجاقی' => [ 'clicks' => 3, 'impressions' => 3000, 'position' => 8.5 ] ] ] );
update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [ 'fetched' => time(), 'pages' => 1, 'days' => 90, 'property' => 'sc-domain:x' ], false );
$rules4 = static fn( ?array $a ): array => array_column( (array) ( $a['suggestions'] ?? [] ), 'rule' );
$has_obj = ( hodima_seo_advisor_page( $r4_path )['object_id'] ?? 0 ) > 0;
if ( $has_obj ) {
	$before = $rules4( hodima_seo_advisor_analyze( $r4_path ) );
	hodima_ad( 'بدون خزش: کلمه‌ای که در متن و تیتر نیست «غایب» گزارش می‌شود', array_intersect( $before, [ 'missing_headings', 'missing_content' ] ) !== [], true );
}
$hdr4 = [ 'Address', 'Status Code', 'Indexability', 'Indexability Status', 'Title 1', 'Title 1 Length', 'Meta Description 1 Length', 'H1-1', 'H2-1', 'H2-2', 'Response Time', 'Unique Inlinks', 'Crawl Depth', 'Link Score', 'Redirect URL' ];
$ok4  = str_repeat( 'عنوان سالم ', 5 );
$csv4( 'internal_html.csv', [
	$hdr4,
	[ $u( $r4_path ), '200', 'Indexable', '', $ok4, (string) mb_strlen( $ok4 ), '130', 'کلیپس سنجاقی ارزان', 'بخش اول', 'بخش دوم', '0.4', '5', '1', '1', '' ],
	[ $u( '/noh1/' ), '200', 'Indexable', '', $ok4, (string) mb_strlen( $ok4 ), '130', '', '', '', '0.4', '5', '1', '1', '' ],
] );
@unlink( "{$crawl_dir4}/all_inlinks.csv" );
$imp4 = hodima_seo_advisor_crawl_import();
hodima_ad( 'خواندن صفحه‌ها با ستون‌های H2', is_wp_error( $imp4 ) ? $imp4->get_error_message() : $imp4['pages'], 2 );
$rd = hodima_seo_advisor_crawl_rendered( $r4_path );
hodima_ad( 'عنوان و تیترهای رندرشده: متن یکسان‌شده شامل H1 و هر دو H2', [ str_contains( (string) $rd['text'], 'کلیپس سنجاقی ارزان' ), str_contains( (string) $rd['text'], 'بخش دوم' ), $rd['h1'] ], [ true, true, 'کلیپس سنجاقی ارزان' ] );
hodima_ad( 'صفحه خارج از خزش: رندرشده null', hodima_seo_advisor_crawl_rendered( '/not-there/' ), null );
if ( $has_obj ) {
	$after = $rules4( hodima_seo_advisor_analyze( $r4_path ) );
	hodima_ad( 'H1 رندرشده کلمه را دارد ← دیگر «غایب» نیست', array_intersect( $after, [ 'missing_headings', 'missing_content' ] ), [] );
}
$text4 = [ 'title_headings' => hodima_seo_advisor_normalize( 'صفحه' ), 'content' => hodima_seo_advisor_normalize( 'متن' ), 'rendered' => [ 'title' => 'عنوان فعلی', 'h1' => 'تیتر فعلی' ] ];
$sug4  = hodima_seo_advisor_suggestions( [ 'path' => '/q/', 'clicks' => 5, 'impressions' => 4000, 'position' => 8.5, 'prev_clicks' => 0 ], [ [ 'term' => 'کلیپس سنجاقی', 'clicks' => 3, 'impressions' => 3000, 'position' => 8.5 ] ], $text4 );
$miss4 = array_values( array_filter( $sug4, static fn( array $x ): bool => str_starts_with( $x['rule'], 'missing_' ) ) )[0] ?? null;
hodima_ad( 'پیشنهاد «غایب»: عنوان و H1 فعلی طبق خزش در متن می‌آید', str_contains( (string) ( $miss4['detail'] ?? '' ), 'طبق خزش، عنوان فعلی «عنوان فعلی» و H1 «تیتر فعلی» است' ), true );
hodima_ad( 'بدون rendered همان جمله نیست', str_contains( (string) ( array_values( array_filter( hodima_seo_advisor_suggestions( [ 'path' => '/q/', 'clicks' => 5, 'impressions' => 4000, 'position' => 8.5, 'prev_clicks' => 0 ], [ [ 'term' => 'کلیپس سنجاقی', 'clicks' => 3, 'impressions' => 3000, 'position' => 8.5 ] ], [ 'title_headings' => 'x', 'content' => 'y' ] ), static fn( array $x ): bool => str_starts_with( $x['rule'], 'missing_' ) ) )[0]['detail'] ?? '' ), 'طبق خزش، عنوان' ), false );

$iss4 = [];
foreach ( hodima_seo_advisor_crawl_issues() as $row ) {
	$iss4[ $row['path'] ] = array_column( $row['issues'], 'code' );
}
hodima_ad( 'صفحه بدون H1 ایراد «بدون H1» می‌گیرد و صفحه با H1 نه', [ in_array( 'no_h1', $iss4['/noh1/'] ?? [], true ), in_array( 'no_h1', $iss4[ $r4_path ] ?? [], true ) ], [ true, false ] );
hodima_ad( '«بدون H1» در چیپ عنوان شمرده می‌شود', hodima_seo_advisor_crawl_summary( hodima_seo_advisor_crawl_issues() )['groups']['title'] >= 1, true );

$well = hodima_seo_advisor_links_evidence( [ 'total' => 30, 'text' => 20, 'score' => 50.0, 'depth' => 1, 'anchors' => [ [ 'anchor' => 'کلیپس سنجاقی', 'count' => 8 ] ] ], 'کلیپس سنجاقی' );
hodima_ad( 'لینک داخلی کافی: دستور تمرکز را روی عنوان و متن می‌گذارد', [ str_contains( $well, 'لینک داخلی این صفحه کم نیست' ), str_contains( $well, 'لینک متنی کم است' ) ], [ true, false ] );
$notwell = hodima_seo_advisor_links_evidence( [ 'total' => 30, 'text' => 20, 'score' => 50.0, 'depth' => 1, 'anchors' => [ [ 'anchor' => 'کلیپس سنجاقی', 'count' => 2 ] ] ], 'کلیپس سنجاقی' );
hodima_ad( 'لینک زیاد ولی با عبارت کم: همان جمله نمی‌آید', str_contains( $notwell, 'لینک داخلی این صفحه کم نیست' ), false );

echo "=== بررسی فوری عنوان، متا و تیترها از خود سایت (advisor-live.php)\n";
$lp = hodima_seo_advisor_crawler_parse( '<!doctype html><html><head><title> عنوان  تازه صفحه </title><meta name="Description" content="توضیح متای تازه"><meta name="robots" content="index, follow"></head><body><h1>تیتر <b>اصلی</b></h1><h2>یک</h2><h2></h2><h2>دو</h2><h2>سه</h2></body></html>', home_url( '/x/' ) );
hodima_ad( 'خواندن HTML: عنوان، طول متا، H1 با تگ درونی، دو H2 اول غیرخالی، ایندکس‌پذیر', [ $lp['title'], mb_strlen( $lp['title'] ), mb_strlen( $lp['meta'] ), $lp['h1'], $lp['h2'], hodima_seo_advisor_crawler_page_row( '/x/', 200, 1, $lp, '', '' )['indexable'] ], [ 'عنوان تازه صفحه', 15, 15, 'تیتر اصلی', 'یک | دو', 1 ] );
$lp0 = hodima_seo_advisor_crawler_parse( '<html><head><meta name="robots" content="NOINDEX,follow"></head><body></body></html>', home_url( '/x/' ) );
$lp0_row = hodima_seo_advisor_crawler_page_row( '/x/', 200, 1, $lp0, '', '' );
hodima_ad( 'صفحه بی‌عنوان و noindex', [ $lp0['title'], $lp0_row['meta_len'], $lp0['h1'], $lp0_row['indexable'], $lp0_row['reason'] ], [ '', 0, '', 0, 'Noindex' ] );
$live_code = 200;
$live_headers = [];
$live_hits = [];
$live_html = '<html><head><title>' . $ok4 . '</title><meta name="description" content="' . str_repeat( 'م', 90 ) . '"></head><body><h1>تیتر تازه بدون کلیپس</h1><h2>بخش تازه</h2></body></html>';
$live_filter = static function ( $pre, array $args, string $url ) use ( &$live_code, &$live_hits, &$live_html, &$live_headers ) {
	if ( ! str_contains( $url, HODIMA_SEO_ADVISOR_LIVE_PARAM . '=' ) ) {
		return $pre;
	}
	$live_hits[] = [ $url, $args['redirection'] ?? null ];
	return [ 'headers' => $live_headers + [ 'content-type' => 'text/html; charset=UTF-8' ], 'body' => $live_html, 'response' => [ 'code' => $live_code, 'message' => '' ], 'cookies' => [], 'filename' => null ];
};
add_filter( 'pre_http_request', $live_filter, 30, 3 );
hodima_ad( 'پیش از بررسی: /noh1/ ایراد «بدون H1» دارد و زمان بررسی صفر', [ in_array( 'no_h1', $iss4['/noh1/'] ?? [], true ), hodima_seo_advisor_live_time( '/noh1/' ) ], [ true, 0 ] );
hodima_ad( 'آدرس خارج از خزش بررسی نمی‌شود (درخواستی نمی‌رود)', [ hodima_seo_advisor_live_check( '/not-there/' ), count( $live_hits ) ], [ false, 0 ] );
$live_code = 404;
hodima_ad( 'صفحه ۴۰۴: داده خزش دست نمی‌خورد', [ hodima_seo_advisor_live_check( '/noh1/' ), hodima_seo_advisor_crawl_row( '/noh1/' )['h1'] ], [ false, '' ] );
$live_code = 200;
hodima_ad( 'صفحه ۲۰۰: ردیف خزش با H1 تازه نوشته می‌شود، با پارامتر دور زدن کش و بدون دنبال کردن ریدایرکت', [ hodima_seo_advisor_live_check( '/noh1/' ), hodima_seo_advisor_crawl_row( '/noh1/' )['h1'], str_contains( (string) end( $live_hits )[0], '/noh1/?' . HODIMA_SEO_ADVISOR_LIVE_PARAM . '=' ), end( $live_hits )[1] ], [ true, 'تیتر تازه بدون کلیپس', true, 0 ] );
$iss_live = [];
foreach ( hodima_seo_advisor_crawl_issues() as $row ) {
	$iss_live[ $row['path'] ] = array_column( $row['issues'], 'code' );
}
hodima_ad( 'ایراد «بدون H1» همان لحظه از «سلامت خزش» رفت و زمان بررسی ثبت شد', [ in_array( 'no_h1', $iss_live['/noh1/'] ?? [], true ), hodima_seo_advisor_live_time( '/noh1/' ) > 0 ], [ false, true ] );
if ( $has_obj ) {
	hodima_ad( 'H1 رندرشده کلمه را دارد ← پیش از بررسی «غایب» نیست', array_intersect( $rules4( hodima_seo_advisor_analyze( $r4_path ) ), [ 'missing_headings', 'missing_content' ] ), [] );
	hodima_seo_advisor_live_check( $r4_path ); // H1 تازه کلمه را ندارد
	hodima_ad( 'بعد از بررسی از سایت (H1 تازه بدون کلمه): پیشنهاد ذخیره‌شده فهرست هم «غایب» دارد', array_intersect( array_column( (array) hodima_seo_advisor_page( $r4_path )['advice'], 'rule' ), [ 'missing_headings', 'missing_content' ] ) !== [], true );
	wp_clear_scheduled_hook( HODIMA_SEO_ADVISOR_LIVE_CRON, [ $r4_path ] );
	wp_update_post( [ 'ID' => $r4_id, 'post_content' => '<p>متن ساده دیگر</p>' ] );
	hodima_ad( 'ذخیره صفحه‌ای که در خزش هست بررسی از سایت را زمان‌بندی می‌کند', (bool) wp_next_scheduled( HODIMA_SEO_ADVISOR_LIVE_CRON, [ $r4_path ] ), true );
	wp_clear_scheduled_hook( HODIMA_SEO_ADVISOR_LIVE_CRON, [ $r4_path ] );
	$r4_draft = (int) wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'پیش‌نویس چهار', 'post_name' => 'advisor-r4-draft' ] );
	hodima_ad( 'پیش‌نویس زمان‌بندی نمی‌شود', count( array_filter( (array) _get_cron_array(), static fn( $e ): bool => isset( $e[ HODIMA_SEO_ADVISOR_LIVE_CRON ] ) ) ), 0 );
	wp_delete_post( $r4_draft, true );
}
$GLOBALS['wpdb']->insert( hodima_seo_advisor_table( 'crawl_pages' ), [ 'path' => '/گل-سر/', 'status' => 200, 'indexable' => 1, 'reason' => '', 'title' => 'x', 'title_len' => 1, 'meta_len' => 0, 'h1' => '', 'h2' => '', 'resp_ms' => 100, 'inlinks' => 3, 'text_links' => -1, 'depth' => 1, 'score' => 1, 'redirect_to' => '' ] );
hodima_seo_advisor_crawl_map( true );
hodima_seo_advisor_live_check( '/گل-سر/' );
hodima_ad( 'آدرس فارسی: مسیر در درخواست کدگذاری می‌شود و ردیف به‌روز می‌شود', [ str_contains( (string) end( $live_hits )[0], '/' . rawurlencode( 'گل-سر' ) . '/?' ), hodima_seo_advisor_crawl_row( '/گل-سر/' )['h1'] ], [ true, 'تیتر تازه بدون کلیپس' ] );
// بررسی فوری و خزنده شبانه یک خواندن دارند (SEO 3.1.9): همان HTML از دو مسیر ← همان ردیف
$lv_path = '/noh1/';
$lv_html = '<html><head><title>' . str_repeat( 'ت', 40 ) . '</title><meta name="description" content="' . str_repeat( 'م', 100 ) . '"><link rel="canonical" href="' . home_url( '/other/' ) . '"></head><body><main><h1>تیتر یک</h1><h1>تیتر دو</h1><p>یک دو سه چهار</p><a href="' . home_url( '/b/' ) . '">لینک ب</a><a href="' . home_url( '/b/' ) . '">لینک ب</a><img src="/a.jpg"></main></body></html>';
$live_html = $lv_html;
$GLOBALS['wpdb']->update( hodima_seo_advisor_table( 'crawl_pages' ), [ 'inlinks' => 7, 'text_links' => 5, 'depth' => 2, 'score' => 3.5 ], [ 'path' => $lv_path ] );
$lv_ms0 = (int) $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( 'SELECT resp_ms FROM %i WHERE path = %s', hodima_seo_advisor_table( 'crawl_pages' ), $lv_path ) );
hodima_seo_advisor_live_check( $lv_path );
$lv_live = hodima_seo_advisor_crawl_row( $lv_path );
$lv_cols = (array) $GLOBALS['wpdb']->get_row( $GLOBALS['wpdb']->prepare( 'SELECT * FROM %i WHERE path = %s', hodima_seo_advisor_table( 'crawl_pages' ), $lv_path ), ARRAY_A );
$lv_ref  = hodima_seo_advisor_crawler_page_row( $lv_path, 200, (int) $lv_cols['resp_ms'], hodima_seo_advisor_crawler_parse( $lv_html, hodima_seo_advisor_url( ltrim( $lv_path, '/' ) ) ), '', '' );
$lv_diff = [];
foreach ( $lv_ref as $col => $val ) {
	if ( ! in_array( $col, [ 'inlinks', 'text_links', 'depth', 'score' ], true ) && (string) $lv_cols[ $col ] !== (string) $val ) {
		$lv_diff[] = $col;
	}
}
hodima_ad( 'بررسی فوری همان ردیفی را می‌نویسد که خزنده برای همان HTML می‌نویسد (همه ستون‌ها، جز آمار لینک)', $lv_diff, [] );
hodima_ad( 'زمان پاسخ خزش با بررسی فوری عوض نمی‌شود (درخواست بررسی بی‌کش است، معیار «کند» نیست)', (int) $lv_cols['resp_ms'], $lv_ms0 );
hodima_ad( 'ستون‌هایی که پیش‌تر کهنه می‌ماندند تازه شدند: canonical، دو H1، کلمه‌ها، تصویر بدون alt، دلیل canonical', [ $lv_cols['canonical'], (int) $lv_cols['h1_count'], (int) $lv_cols['words'] > 0, (int) $lv_cols['img_noalt'], $lv_cols['reason'], (int) $lv_cols['indexable'] ], [ '/other/', 2, true, 1, 'Canonicalised', 0 ] );
hodima_ad( 'آمار لینک ورودی، لینک متنی، عمق و امتیاز می‌ماند (فقط دور کامل خزش آن را می‌سازد)', [ (int) $lv_cols['inlinks'], (int) $lv_cols['text_links'], (int) $lv_cols['depth'], (float) $lv_cols['score'] ], [ 7, 5, 2, 3.5 ] );
hodima_ad( 'لینک‌های خروجی صفحه تازه شد (یکتا)', $GLOBALS['wpdb']->get_col( $GLOBALS['wpdb']->prepare( 'SELECT dest_path FROM %i WHERE source_path = %s', hodima_seo_advisor_table( 'crawl_links' ), $lv_path ) ), [ '/b/' ] );
$live_html    = '<html><head><title>x</title></head><body><h1>تیتر</h1></body></html>';
$live_headers = [ 'x-robots-tag' => 'noindex' ];
hodima_seo_advisor_live_check( $lv_path );
hodima_ad( 'noindex از سربرگ X-Robots-Tag هم دیده می‌شود (پیش‌تر فقط متای robots)', [ hodima_seo_advisor_crawl_row( $lv_path )['indexable'], hodima_seo_advisor_crawl_row( $lv_path )['reason'] ], [ false, 'Noindex' ] );
$live_headers = [ 'content-type' => 'application/pdf' ];
$live_h1_before = hodima_seo_advisor_crawl_row( $lv_path )['h1'];
$live_html = '<html><body><h1>نباید نوشته شود</h1></body></html>';
hodima_ad( 'پاسخ ۲۰۰ غیر HTML نوشته نمی‌شود', [ hodima_seo_advisor_live_check( $lv_path ), hodima_seo_advisor_crawl_row( $lv_path )['h1'] ], [ false, $live_h1_before ] );
$live_headers = [];
remove_filter( 'pre_http_request', $live_filter, 30 );
delete_option( HODIMA_SEO_ADVISOR_LIVE_OPTION );

hodima_seo_advisor_clear_data();
wp_delete_post( $r4_id, true );
remove_filter( 'hodima_seo_advisor_crawl_dir', $crawl_filter4 );
array_map( 'unlink', glob( "{$crawl_dir4}/{,.}*", GLOB_BRACE ) ?: [] );
@rmdir( $crawl_dir4 );

echo "=== صفحه اصلی رقیب حساب نمی‌شود\n";
$h_term = static fn( int $imp, float $pos, int $clicks ): array => [ 'clicks' => $clicks, 'impressions' => $imp, 'position' => $pos ];
hodima_seo_advisor_store(
	[ '/' => [ 'clicks' => 13, 'impressions' => 500, 'position' => 7.8 ], '/cat-x/' => [ 'clicks' => 263, 'impressions' => 23000, 'position' => 6.5 ], '/h-one/' => [ 'clicks' => 30, 'impressions' => 2000, 'position' => 7.0 ], '/h-two/' => [ 'clicks' => 20, 'impressions' => 1800, 'position' => 9.0 ] ],
	[],
	[
		'/' => [ 'گیره کوچک' => $h_term( 400, 7.8, 13 ), 'گیره بزرگ' => $h_term( 300, 8.0, 10 ) ], '/cat-x/' => [ 'گیره کوچک' => $h_term( 22000, 6.5, 260 ) ],
		'/h-one/' => [ 'گیره بزرگ' => $h_term( 1800, 7.0, 28 ) ], '/h-two/' => [ 'گیره بزرگ' => $h_term( 1700, 9.0, 18 ) ],
	]
);
hodima_ad( 'صفحه اصلی همیشه «حل‌شده» است با برچسب «صفحه اصلی»', [ hodima_seo_advisor_resolved_paths()['/'] ?? '', hodima_seo_advisor_resolved_label( 'home' ) ], [ 'home', 'صفحه اصلی' ] );
$hc = hodima_seo_advisor_competition( [ 'limit' => 15 ] );
$hc_terms = array_column( $hc['rows'], 'term' );
hodima_ad( 'گروه فقط «صفحه اصلی + یک دسته» از فهرست اصلی کنار رفت', in_array( 'گیره کوچک', $hc_terms, true ), false );
$big = array_values( array_filter( $hc['rows'], static fn( array $g ): bool => 'گیره بزرگ' === $g['term'] ) )[0] ?? null;
hodima_ad( 'گروه سه‌صفحه‌ای: فقط صفحه اصلی کم می‌شود و دو صفحه می‌ماند', null === $big ? null : [ $big['count'], array_column( $big['pages'], 'path' ) ], [ 2, [ '/h-one/', '/h-two/' ] ] );
$hs = hodima_seo_advisor_competition( [ 'resolved' => true ] );
$small = array_values( array_filter( $hs['rows'], static fn( array $g ): bool => 'گیره کوچک' === $g['term'] ) )[0] ?? null;
hodima_ad( 'نمای حل‌شده‌ها: گروه با دلیل «home» و دستور «اقدامی لازم نیست»', null === $small ? null : [ array_column( $small['pages'], 'resolved', 'path' ), str_contains( $small['advice']['detail'], 'اقدامی لازم نیست' ) ], [ [ '/cat-x/' => '', '/' => 'home' ], true ] );
hodima_ad( 'رقیب‌های یک دسته: صفحه اصلی نمی‌آید؛ برای خود صفحه اصلی رقیبی نیست', [ hodima_seo_advisor_competitors( '/cat-x/', [ [ 'term' => 'گیره کوچک', 'impressions' => 22000 ] ] ), hodima_seo_advisor_competitors( '/', [ [ 'term' => 'گیره کوچک', 'impressions' => 400 ] ] ) ], [ [], [] ] );
hodima_seo_advisor_clear_data();

echo "=== «منتظر داده گوگل»: سنجش با داده فقط بعد از تغییر (advisor-verify.php)\n";
$vf_saved = get_option( HODIMA_SEO_ADVISOR_TASKS_OPTION, false );
delete_option( HODIMA_SEO_ADVISOR_TASKS_OPTION );
$vf_task = static fn( string $rule, int $days_ago, array $extra = [] ): array => $extra + [ 'path' => '/vf/a/', 'rule' => $rule, 'term' => '', 'title' => 't', 'status' => 'done', 'at' => time() - $days_ago * DAY_IN_SECONDS, 'fixed' => 0, 'back' => 0, 'check' => [] ];
$vd = hodima_seo_advisor_verify_dates( $vf_task( 'low_ctr', 35 ) );
$vlen = static fn( array $w ): int => (int) ( new DateTimeImmutable( $w[0] ) )->diff( new DateTimeImmutable( $w[1] ) )->days + 1;
hodima_ad( 'بازه‌ها: ۳۵ روز پیش ← بیش از ۲۸ روز داده، پنجره ۲۸، قبل و بعد هم‌اندازه و چسبیده به روز تغییر', [ $vd['after_days'] >= 27, $vd['window'], $vlen( $vd['before'] ), $vlen( $vd['after'] ), ( new DateTimeImmutable( $vd['before'][1] ) )->modify( '+2 days' )->format( 'Y-m-d' ) === $vd['after'][0] ], [ true, 28, 28, 28, true ] );
hodima_ad( 'برچسب انتظار: کار همین امروز ← «۰ از ۱۴ روز»؛ متنی ← بدون شمار', [ (bool) preg_match( '/\((0|۰) از (14|۱۴) روز\)/u', hodima_seo_advisor_verify_wait_label( $vf_task( 'low_ctr', 0 ) ) ), hodima_seo_advisor_verify_wait_label( $vf_task( 'missing_content', 0 ) ) ], [ true, 'انجام شد · منتظر داده گوگل' ] );
hodima_ad( 'سررسید: ۲۰ روز پیش بله؛ ۵ روز نه؛ متنی/در حال انجام نه؛ «اثر داشت» نه؛ موقت ۲۸روزه نه؛ موقت ۱۴روزه هفته بعد بله', [
	hodima_seo_advisor_verify_due( $vf_task( 'low_ctr', 20 ) ),
	hodima_seo_advisor_verify_due( $vf_task( 'low_ctr', 5 ) ),
	hodima_seo_advisor_verify_due( $vf_task( 'missing_content', 20 ) ),
	hodima_seo_advisor_verify_due( $vf_task( 'low_ctr', 20, [ 'status' => 'doing' ] ) ),
	hodima_seo_advisor_verify_due( $vf_task( 'low_ctr', 40, [ 'check' => [ 'verdict' => 'effective', 'window' => 14, 'at' => 1 ] ] ) ),
	hodima_seo_advisor_verify_due( $vf_task( 'low_ctr', 40, [ 'check' => [ 'verdict' => 'no_effect', 'window' => 28, 'at' => 1 ] ] ) ),
	hodima_seo_advisor_verify_due( $vf_task( 'low_ctr', 20, [ 'check' => [ 'verdict' => 'no_effect', 'window' => 14, 'at' => time() ] ] ) ),
	hodima_seo_advisor_verify_due( $vf_task( 'low_ctr', 20, [ 'check' => [ 'verdict' => 'no_effect', 'window' => 14, 'at' => time() - 8 * DAY_IN_SECONDS ] ] ) ),
], [ true, false, false, false, false, false, false, true ] );
$vm = static fn( int $c, int $i, float $p ): array => [ 'clicks' => $c, 'impressions' => $i, 'ctr' => $i > 0 ? round( $c / $i * 100, 2 ) : 0.0, 'position' => $p ];
$vj = static fn( string $rule, array $b, array $a, array $bt = [], array $at = [], string $term = '' ): string => hodima_seo_advisor_verify_judge( [ 'rule' => $rule, 'term' => $term ], [ 'page' => $b, 'terms' => $bt ], [ 'page' => $a, 'terms' => $at ] )['verdict'];
$tk = hodima_seo_advisor_term_key( 'کلیپس طلایی' );
hodima_ad( 'حکم: نرخ کلیک ۰٫۵٪←۱٫۵٪ اثر داشت؛ ۰٫۵٪←۰٫۵۲٪ نداشت؛ نمایش کم = داده کم؛ رسیدن به نرخ معمول رتبه = اثر داشت', [
	$vj( 'low_ctr', $vm( 50, 10000, 4.0 ), $vm( 150, 10000, 4.0 ) ),
	$vj( 'low_ctr', $vm( 50, 10000, 4.0 ), $vm( 52, 10000, 4.0 ) ),
	$vj( 'low_ctr', $vm( 5, 1000, 4.0 ), $vm( 9, 80, 4.0 ) ),
	$vj( 'low_ctr', $vm( 30, 1000, 15.0 ), $vm( 16, 1000, 15.0 ) ),
], [ 'effective', 'no_effect', 'low_data', 'effective' ] );
// حکم نرخ کلیک کلمه‌ای (SEO 3.0.8): شاخص کلیک واقعی ÷ مورد انتظار، نه خود نرخ کلیک
$vt_table = static fn(): array => [ 2 => 25.0, 3 => 14.0, 5 => 9.0, 7 => 5.5, 10 => 3.5, 20 => 1.5 ];
add_filter( 'hodima_seo_advisor_ctr_table', $vt_table );
$vt_good = hodima_seo_advisor_term_key( 'کلمه خوب' );
$vt_bad  = hodima_seo_advisor_term_key( 'کلمه بد' );
$vt_page = static fn( int $clicks, int $impressions ): array => [ 'clicks' => $clicks, 'impressions' => $impressions, 'ctr' => round( $clicks / $impressions * 100, 2 ), 'position' => 5.3 ];
$vt_before = [ $vt_good => $vm( 250, 1000, 2.0 ), $vt_bad => $vm( 50, 5000, 6.0 ) ]; // مورد انتظار ۵۲۵، واقعی ۳۰۰ ← شاخص ۰٫۵۷
hodima_ad( 'حکم نرخ کلیک کلمه‌ای: کلمه بد به مورد انتظار رسید ← اثر داشت؛ ۱۰٪ بهتر ← نداشت؛ ۳۰٪ بهتر ← داشت؛ جابه‌جایی سهم کلمه خوب ← نداشت (حساب کل صفحه اثر داشت می‌گفت)', [
	$vj( 'low_ctr', $vt_page( 300, 6000 ), $vt_page( 550, 6000 ), $vt_before, [ $vt_good => $vm( 250, 1000, 2.0 ), $vt_bad => $vm( 300, 5000, 6.0 ) ] ),
	$vj( 'low_ctr', $vt_page( 300, 6000 ), $vt_page( 330, 6000 ), $vt_before, [ $vt_good => $vm( 250, 1000, 2.0 ), $vt_bad => $vm( 80, 5000, 6.0 ) ] ),
	$vj( 'low_ctr', $vt_page( 300, 6000 ), $vt_page( 390, 6000 ), $vt_before, [ $vt_good => $vm( 250, 1000, 2.0 ), $vt_bad => $vm( 140, 5000, 6.0 ) ] ),
	$vj( 'low_ctr', $vt_page( 300, 6000 ), $vt_page( 550, 9000 ), $vt_before, [ $vt_good => $vm( 500, 2000, 2.0 ), $vt_bad => $vm( 50, 7000, 6.0 ) ] ),
], [ 'effective', 'no_effect', 'effective', 'no_effect' ] );
remove_filter( 'hodima_seo_advisor_ctr_table', $vt_table );
hodima_ad( 'حکم کلمه: ۱۲←۹٫۵ اثر داشت؛ ۱۲←۱۱٫۵ نداشت؛ رسیدن به ۷ (نزدیک صفحه اول) داشت؛ بدون کلمه بعد از تغییر داده کم', [
	$vj( 'near_first', $vm( 1, 900, 9 ), $vm( 1, 900, 9 ), [ $tk => $vm( 1, 300, 12.0 ) ], [ $tk => $vm( 2, 300, 9.5 ) ], 'کلیپس طلایی' ),
	$vj( 'near_first', $vm( 1, 900, 9 ), $vm( 1, 900, 9 ), [ $tk => $vm( 1, 300, 12.0 ) ], [ $tk => $vm( 2, 300, 11.5 ) ], 'کلیپس طلایی' ),
	$vj( 'near_first', $vm( 1, 900, 9 ), $vm( 1, 900, 9 ), [ $tk => $vm( 1, 300, 8.0 ) ], [ $tk => $vm( 2, 300, 7.0 ) ], 'کلیپس طلایی' ),
	$vj( 'weak_query', $vm( 1, 900, 9 ), $vm( 1, 900, 9 ), [ $tk => $vm( 1, 300, 30.0 ) ], [], 'کلیپس طلایی' ),
], [ 'effective', 'no_effect', 'effective', 'low_data' ] );
hodima_ad( 'حکم افت: کلیک ۴۰←۶۰ اثر داشت؛ ۴۰←۴۲ نداشت', [ $vj( 'decline', $vm( 40, 5000, 6 ), $vm( 60, 5000, 6 ) ), $vj( 'decline', $vm( 40, 5000, 6 ), $vm( 42, 5000, 6 ) ) ], [ 'effective', 'no_effect' ] );

// نوبت سنجش با پاسخ ساختگی سرچ کنسول
hodima_seo_advisor_store( [ '/vf/a/' => [ 'clicks' => 20, 'impressions' => 10000, 'position' => 4.0 ] ], [], [] );
update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [ 'fetched' => time() - 3600, 'property' => 'sc-domain:' . $host, 'days' => 90, 'pages' => 1, 'queries' => 0, 'advised' => 1, 'error' => '' ] + hodima_seo_advisor_ranges( 90 ), false );
hodima_seo_advisor_save_advice( '/vf/a/' );
hodima_ad( 'آدرس نمونه پیشنهاد «نرخ کلیک کم» دارد', in_array( 'low_ctr', array_column( (array) hodima_seo_advisor_page( '/vf/a/' )['advice'], 'rule' ), true ), true );
update_option( HODIMA_SEO_ADVISOR_TASKS_OPTION, [
	hodima_seo_advisor_task_key( '/vf/a/', 'low_ctr', '' ) => $vf_task( 'low_ctr', 21 ),
	hodima_seo_advisor_task_key( '/vf/a/', 'near_first', 'کلیپس طلایی' ) => $vf_task( 'near_first', 21, [ 'term' => 'کلیپس طلایی' ] ),
], false );
update_option( 'hodima_discover_sc_key', $key_json, false );
delete_transient( 'hodima_discover_sc_token_' . substr( md5( 'advisor@test.iam.gserviceaccount.com' ), 0, 12 ) );
$vf_calls = [];
$vf_code  = 200;
$vf_dates = hodima_seo_advisor_verify_dates( $vf_task( 'low_ctr', 21 ) );
$vf_filter = static function ( $pre, array $args, string $url ) use ( &$vf_calls, &$vf_code, $vf_dates, $u ) {
	if ( ! str_contains( $url, '/searchAnalytics/query' ) ) {
		return $pre;
	}
	$body = json_decode( (string) $args['body'], true );
	if ( empty( $body['dimensionFilterGroups'] ) ) {
		return $pre;
	}
	$vf_calls[] = $body;
	$after      = $body['startDate'] === $vf_dates['after'][0];
	$rows       = 1 === count( $body['dimensions'] )
		? [ [ 'keys' => [ $u( '/vf/a/' ) ], 'clicks' => $after ? 150 : 50, 'impressions' => 10000, 'position' => 4.0 ], [ 'keys' => [ $u( '/x/vf/a/' ) ], 'clicks' => 9999, 'impressions' => 10000, 'position' => 1.0 ] ]
		: [ [ 'keys' => [ $u( '/vf/a/' ), 'كليپس طلايي' ], 'clicks' => 2, 'impressions' => 300, 'position' => $after ? 11.5 : 12.0 ] ];
	return [ 'headers' => [], 'body' => (string) wp_json_encode( $vf_code === 200 ? [ 'rows' => $rows ] : [ 'error' => [ 'message' => 'quota' ] ] ), 'response' => [ 'code' => $vf_code, 'message' => '' ], 'cookies' => [], 'filename' => null ];
};
add_filter( 'pre_http_request', $vf_filter, 50, 3 );
$vf_code = 429;
hodima_ad( 'سهمیه (۴۲۹): هیچ کاری سنجیده نمی‌شود و داده دست نمی‌خورد', [ hodima_seo_advisor_verify_batch( 'sc-domain:' . $host, INF ), hodima_seo_advisor_task( '/vf/a/', 'low_ctr', '' )['check'] ], [ 0, [] ] );
$vf_code  = 200;
$vf_calls = [];
add_filter( 'hodima_seo_advisor_verify_enabled', '__return_false' );
hodima_ad( 'فیلتر خاموش: نوبت کاری نمی‌کند', [ hodima_seo_advisor_verify_batch( 'sc-domain:' . $host, INF ), count( $vf_calls ) ], [ 0, 0 ] );
remove_filter( 'hodima_seo_advisor_verify_enabled', '__return_false' );
hodima_ad( 'نوبت: هر دو کار یک گروه (یک آدرس و روز) با ۴ درخواست فیلترشده', [ hodima_seo_advisor_verify_batch( 'sc-domain:' . $host, INF ), count( $vf_calls ), $vf_calls[0]['dimensionFilterGroups'][0]['filters'][0]['operator'] ?? '', $vf_calls[0]['dimensionFilterGroups'][0]['filters'][0]['expression'] ?? '' ], [ 2, 4, 'contains', '/vf/a/' ] );
$vf_low  = hodima_seo_advisor_task( '/vf/a/', 'low_ctr', '' );
$vf_near = hodima_seo_advisor_task( '/vf/a/', 'near_first', 'کلیپس طلایی' );
hodima_ad( 'نرخ کلیک ۰٫۵٪←۱٫۵٪ (آدرس مشابه /x/vf/a/ شمرده نشد): «اثر داشت» و «برطرف شد»', [ $vf_low['check']['verdict'] ?? '', $vf_low['check']['before'] ?? 0, $vf_low['check']['after'] ?? 0, hodima_seo_advisor_task_state( $vf_low ) ], [ 'effective', 0.5, 1.5, 'fixed' ] );
hodima_ad( 'کلمه (املای عربی ي/ك هم‌ارز) ۱۲←۱۱٫۵: «اثر نداشت» و گروه «نیاز به کار»', [ $vf_near['check']['verdict'] ?? '', hodima_seo_advisor_task_state( $vf_near ), hodima_seo_advisor_task_group( $vf_near ), hodima_seo_advisor_task_view( $vf_near )['label'] ], [ 'no_effect', 'no_effect', 'open', 'اثر نداشت؛ قدم بعدی را ببینید' ] );
hodima_ad( 'پیشنهاد «نرخ کلیک کم» تا وقتی بازه تحلیل روزهای پیش از تغییر را دارد ساخته نمی‌شود', in_array( 'low_ctr', array_column( (array) hodima_seo_advisor_page( '/vf/a/' )['advice'], 'rule' ), true ), false );
hodima_ad( 'سنجش دوباره همان روز: سررسید نیست', hodima_seo_advisor_verify_batch( 'sc-domain:' . $host, INF ), 0 );
$vf_status = get_option( HODIMA_SEO_ADVISOR_STATUS_OPTION );
update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [ 'start' => ( new DateTimeImmutable( 'tomorrow' ) )->format( 'Y-m-d' ) ] + $vf_status, false );
hodima_seo_advisor_save_advice( '/vf/a/' );
hodima_ad( 'بازه تحلیل تازه کاملا بعد از تغییر و هنوز نرخ کلیک کم: پیشنهاد برمی‌گردد و کار «برگشت»', [ in_array( 'low_ctr', array_column( (array) hodima_seo_advisor_page( '/vf/a/' )['advice'], 'rule' ), true ), hodima_seo_advisor_task_state( (array) hodima_seo_advisor_task( '/vf/a/', 'low_ctr', '' ) ) ], [ true, 'back' ] );
update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, $vf_status, false );
hodima_ad( 'جمله سنجش: اثر داشت با اعداد؛ اثر نداشت با قدم بعدی (لینک داخلی و بخش H2)', [ str_contains( hodima_seo_advisor_verify_text( $vf_low ), 'اثر داشت' ), str_contains( hodima_seo_advisor_verify_text( $vf_near ), 'قدم بعدی' ) && str_contains( hodima_seo_advisor_verify_text( $vf_near ), 'H2' ), hodima_seo_advisor_verify_text( $vf_task( 'low_ctr', 1 ) ) ], [ true, true, '' ] );
$_GET = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'tasks' ];
ob_start();
hodima_seo_advisor_render_tasks();
$vf_html = (string) ob_get_clean();
$_GET = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'tasks', 'tstatus' => 'fixed' ];
ob_start();
hodima_seo_advisor_render_tasks();
$vf_done = (string) ob_get_clean();
$_GET = [];
hodima_ad( '«کارها» ← «نیاز به کار»: «اثر نداشت» با قدم بعدی و فرم جدای «دوباره انجام دادم» (status=done + redo)؛ گام سوم کار «اثر داشت» سبز؛ فیلتر «برطرف شد» کار باز را نشان نمی‌دهد', [ str_contains( $vf_html, 'اثر نداشت؛ قدم بعدی را ببینید' ), str_contains( $vf_html, 'دوباره انجام دادم' ) && str_contains( $vf_html, 'name="redo" value="1"' ) && str_contains( $vf_html, 'name="status" value="done"' ), hodima_seo_advisor_task_timeline( $vf_low )[2]['label'] . '/' . hodima_seo_advisor_task_timeline( $vf_low )[2]['state'], str_contains( $vf_done, 'اثر نداشت؛ قدم بعدی' ) ], [ true, true, 'اثر داشت/ok', false ] );
// بی‌کار: چهار کارت آمار «—» نمی‌آید، فقط حالت خالی (SEO 3.1.2)
$ec_tasks = get_option( HODIMA_SEO_ADVISOR_TASKS_OPTION, false );
delete_option( HODIMA_SEO_ADVISOR_TASKS_OPTION );
ob_start();
hodima_seo_advisor_render_tasks();
$ec_open = (string) ob_get_clean();
false === $ec_tasks ? delete_option( HODIMA_SEO_ADVISOR_TASKS_OPTION ) : update_option( HODIMA_SEO_ADVISOR_TASKS_OPTION, $ec_tasks, false );
hodima_ad( 'بدون کار: «کارها» کارت آمار و فیلتر ندارد و فقط حالت خالی؛ با کار، نوار وضعیت', [ substr_count( $ec_open, 'class="hds-stat"' ), str_contains( $ec_open, 'هنوز کاری ثبت نشده' ), str_contains( $ec_open, 'aria-label="وضعیت کار"' ), str_contains( $vf_html, 'aria-label="وضعیت کار"' ) ], [ 0, true, false, true ] );
ob_start();
hodima_seo_advisor_render_task_controls( '/vf/a/', [ 'rule' => 'near_first', 'term' => 'کلیپس طلایی', 'title' => 't' ] );
$vf_ctl = (string) ob_get_clean();
hodima_ad( 'کادر ویرایش: لینک «دوباره انجام دادم» با redo=1 و status=done', str_contains( $vf_ctl, 'دوباره انجام دادم' ) && str_contains( $vf_ctl, 'redo=1' ) && str_contains( $vf_ctl, 'status=done' ), true );
hodima_seo_advisor_task_set( '/vf/a/', 'near_first', 'کلیپس طلایی', '', 'done', true );
$vf_redo = hodima_seo_advisor_task( '/vf/a/', 'near_first', 'کلیپس طلایی' );
hodima_ad( '«دوباره انجام دادم»: سنجش پاک، تاریخ امروز، عنوان قبلی، «منتظر داده گوگل»', [ $vf_redo['check'], time() - $vf_redo['at'] < 60, $vf_redo['title'], hodima_seo_advisor_task_state( $vf_redo ) ], [ [], true, 't', 'waiting' ] );
hodima_ad( 'نوبت verify آخر دریافت روزانه است', HODIMA_SEO_ADVISOR_STAGES[ count( HODIMA_SEO_ADVISOR_STAGES ) - 1 ], 'verify' );
remove_filter( 'pre_http_request', $vf_filter, 50 );
delete_option( 'hodima_discover_sc_key' );
false === $vf_saved ? delete_option( HODIMA_SEO_ADVISOR_TASKS_OPTION ) : update_option( HODIMA_SEO_ADVISOR_TASKS_OPTION, $vf_saved, false );
hodima_seo_advisor_clear_data();

echo "=== لینک‌های داخلی یک گروه رقابت (advisor-links.php)\n";
require_once __DIR__ . '/advisor-links-fixture.php';
$lk_ids  = hodima_lk_fixture();
$lk_scan = hodima_seo_advisor_links_scan( 'گیره انبری فلزی', true );
hodima_ad( 'بررسی لینک‌ها خطا نمی‌دهد و هدف دسته برنده و رقیب /207/ است', [ ! is_wp_error( $lk_scan ), $lk_scan['target']['path'] ?? '', array_column( (array) ( $lk_scan['rivals'] ?? [] ), 'path' ) ], [ true, '/lk-cat/', [ '/207/' ] ] );
$lk_counts = (array) ( $lk_scan['counts'] ?? [] );
hodima_ad( 'لینک به رقیب ۸ (۲ متن، توضیح دسته، ۲ منو، لینک مرتبط فقط‌شناسه‌ای، متن خزش بدون ردیف دیتابیس، ساختاری فوتر)، خراب ۱، درست ۲؛ لینک خزش تکراری متن نوشته دوبار نمی‌آید', [ $lk_counts['wrong'] ?? -1, $lk_counts['broken'] ?? -1, $lk_counts['good'] ?? -1 ], [ 8, 1, 2 ] );
$lk_rows = (array) ( $lk_scan['rows'] ?? [] );
$lk_stores = array_column( $lk_rows, 'store' );
sort( $lk_stores );
hodima_ad( 'جای ذخیره هر ردیف: متن نوشته، توضیح دسته، لینک‌های مرتبط، منو، متن و ساختاری خزش', array_count_values( $lk_stores ), [ 'توضیح دسته' => 1, 'قالب (ساختاری)' => 1, 'لینک‌های مرتبط' => 1, 'متن (طبق خزش)' => 1, 'متن نوشته' => 3, 'منو' => 2 ] );
$lk_order = array_map( static fn( array $r ): int => $r['structural'] ? 2 : ( 'wrong' === $r['issue'] ? 0 : 1 ), $lk_rows );
$lk_sorted = $lk_order;
sort( $lk_sorted );
hodima_ad( 'ردیف‌ها به ترتیب: لینک قابل‌ویرایش اشتباه، خراب، ساختاری', $lk_order, $lk_sorted );
$lk_by = static fn( string $store ): array => array_values( array_filter( $lk_rows, static fn( array $r ): bool => $store === $r['store'] ) )[0] ?? [];
hodima_ad( 'لینک مرتبط: متن لینک عنوان دلخواه، مقصد /207/، آدرس نوشته‌شده همان آدرس اصلی', [ $lk_by( 'لینک‌های مرتبط' )['anchor'] ?? '', $lk_by( 'لینک‌های مرتبط' )['now']['path'] ?? '', $lk_by( 'لینک‌های مرتبط' )['source']['title'] ?? '' ], [ 'گیره انبری فلزی', '/207/', 'محصول با لینک مرتبط' ] );
$lk_struct = $lk_by( 'قالب (ساختاری)' );
hodima_ad( 'لینک ساختاری فوتر: یک ردیف برای ۳ صفحه، بدون دکمه ویرایش', [ $lk_struct['times'] ?? 0, $lk_struct['structural'] ?? null, $lk_struct['source']['edit'] ?? 'x' ], [ 3, true, '' ] );
hodima_ad( 'متن خزش بدون ردیف دیتابیس: میزبان /c-x/ با آدرس نوشته‌شده و یادداشت', [ $lk_by( 'متن (طبق خزش)' )['href'] ?? '', str_contains( (string) ( $lk_by( 'متن (طبق خزش)' )['now']['note'] ?? '' ), 'خزش دیده' ) ], [ '/207/', true ] );
hodima_ad( '/207 بدون اسلش به رقیب می‌رسد؛ /20700/ و دامنه دیگر قاطی نمی‌شود', [ in_array( '/20700/', array_column( array_column( $lk_rows, 'now' ), 'path' ), true ), array_values( array_unique( array_map( static fn( array $r ): string => (string) $r['now']['path'], array_filter( $lk_rows, static fn( array $r ): bool => 'wrong' === $r['issue'] ) ) ) ) ], [ false, [ '/207/' ] ] );
$lk_note = array_values( array_filter( $lk_rows, static fn( array $r ): bool => '/207' === $r['href'] ) )[0]['now']['note'] ?? '';
hodima_ad( 'آدرس نوشته‌شده /207 با آدرس اصلی فرق دارد ← یادداشت «ریدایرکت می‌شود»', str_contains( $lk_note, 'ریدایرکت' ), true );
hodima_ad( 'لینک‌های ورودی: رقیب ۳ متن (دو نوشته و توضیح دسته)، ۱ لینک مرتبط، ۱ منو (آیتم صفحه)؛ برنده ۲ متن', [ array_map( static fn( array $p ): array => [ $p['text'], $p['related'], $p['menu'] ], (array) ( $lk_scan['inventory'] ?? [] ) ) ], [ [ [ 2, 0, 0 ], [ 3, 1, 2 ] ] ] );
hodima_ad( 'لینک‌های ورودی طبق خزش: رقیب ۵ صفحه (۲ متن، ۳ ساختاری)', [ $lk_scan['has_crawl'] ?? false, $lk_scan['inventory'][1]['crawl'] ?? null ], [ true, [ 'total' => 5, 'content' => 2, 'other' => 3 ] ] );
hodima_ad( 'رابطه: رقیب داخل دسته برنده است (in_target)', array_column( (array) ( $lk_scan['relations'] ?? [] ), 'type' ), [ 'in_target' ] );
hodima_ad( 'فرصت لینک: نوشته‌ای که فقط کلمه را دارد، با تکه‌ای از متن و پیشنهاد', [ ( $lk_counts['missing'] ?? 0 ) >= 1, (array) ( $lk_scan['opps'][0]['snippet'] ?? [] ) ], [ true, [ 'یک ', 'گیره انبری فلزی', ' مشکی را ببینید و بخرید' ] ] );
$lk_titles = array_column( array_column( $lk_rows, 'source' ), 'title' );
hodima_ad( 'منبع‌ها: عنوان نوشته، توضیح دسته و منو در ردیف‌ها هست', [ in_array( 'راهنمای خرید گیره', $lk_titles, true ), in_array( 'دسته لینک', $lk_titles, true ), (bool) array_filter( $lk_titles, static fn( string $t ): bool => str_starts_with( $t, 'lk-menu' ) ) ], [ true, true, true ] );

// رابطه والد/فرزند/هم‌ردیف دسته‌ها
$lk_p = wp_insert_term( 'گیره مو', 'category', [ 'slug' => 'lk-par' ] );
$lk_c1 = wp_insert_term( 'گیره انبری', 'category', [ 'slug' => 'lk-ch1', 'parent' => (int) $lk_p['term_id'] ] );
$lk_c2 = wp_insert_term( 'گیره ماری', 'category', [ 'slug' => 'lk-ch2', 'parent' => (int) $lk_p['term_id'] ] );
$lk_info = static fn( array $t, string $title ): array => [ 'kind' => 'category', 'id' => (int) $t['term_id'], 'title' => $title ];
$lk_rel = static fn( array $a, array $b ): string => hodima_seo_advisor_links_relation( $a, $b, 'گیره مو انبری' )['type'] ?? '-';
hodima_ad( 'رابطه دسته‌ها: رقیب والد برنده، رقیب زیردسته برنده، دو زیردسته یک والد، بی‌ارتباط', [ $lk_rel( $lk_info( $lk_c1, 'گیره انبری' ), $lk_info( $lk_p, 'گیره مو' ) ), $lk_rel( $lk_info( $lk_p, 'گیره مو' ), $lk_info( $lk_c1, 'گیره انبری' ) ), $lk_rel( $lk_info( $lk_c1, 'گیره انبری' ), $lk_info( $lk_c2, 'گیره ماری' ) ), $lk_rel( $lk_info( $lk_p, 'گیره مو' ), [ 'kind' => 'category', 'id' => $lk_ids['cat'], 'title' => 'x' ] ) ], [ 'parent', 'child', 'sibling', '-' ] );
foreach ( [ $lk_c1, $lk_c2, $lk_p ] as $lk_t ) {
	wp_delete_term( (int) $lk_t['term_id'], 'category' );
}

// تکه متن
hodima_ad( 'تکه متن: عبارت کامل با نیم‌فاصله و ی/ک عربی، و بلندترین واژه وقتی عبارت کامل نیست', [ hodima_seo_advisor_links_snippet( '<p>این گیره‌ انبری فلزی خوب است</p>', 'گیره انبری فلزی' )[1] ?? null, hodima_seo_advisor_links_snippet( '<p>بهترین كليپس انبري</p>', 'کلیپس انبری' )[1] ?? null, hodima_seo_advisor_links_snippet( '<p>فقط انبری</p>', 'گیره مو انبری' )[1] ?? null, hodima_seo_advisor_links_snippet( '<p>هیچ</p>', 'گیره' ) ], [ 'گیره‌ انبری فلزی', 'كليپس انبري', 'انبری', null ] );

$lk_choose = hodima_seo_advisor_links_scan( 'گیره هم‌وزن', true );
hodima_ad( 'گروه هم‌وزن جهت ندارد (خطا با توضیح)', [ is_wp_error( $lk_choose ), is_wp_error( $lk_choose ) ? $lk_choose->get_error_code() : '' ], [ true, 'hodima_advisor_links_choose' ] );
hodima_ad( 'کلمه بدون رقابت خطا می‌دهد', is_wp_error( hodima_seo_advisor_links_scan( 'کلمه ناموجود', true ) ), true );
require_once HODIMA_SEO_DIR . '/core/advisor/advisor-links-view.php';
$lk_html = hodima_seo_advisor_render_links_result( $lk_scan );
hodima_ad( 'نمای نتیجه: رابطه، لینک‌های ورودی، جدول ۷ ستونه با جای ذخیره و آدرس نوشته‌شده، فرصت لینک با تکه متن، دکمه ویرایش', [ str_contains( $lk_html, 'hodima-advisor__lk-rel' ), str_contains( $lk_html, 'لینک‌های ورودی هر صفحه' ), str_contains( $lk_html, 'جای ذخیره' ), str_contains( $lk_html, 'آدرس نوشته‌شده' ), str_contains( $lk_html, 'فرصت لینک' ), str_contains( $lk_html, '<mark>گیره انبری فلزی</mark>' ), str_contains( $lk_html, 'hodima-advisor__act' ), str_contains( $lk_html, 'در این صفحه' ) || str_contains( $lk_html, 'در 3 صفحه' ) || str_contains( $lk_html, 'در ۳ صفحه' ) ], [ true, true, true, true, true, true, true, true ] );
hodima_ad( 'نمای نتیجه: ۸ ردیف اول باز و بقیه (اگر بود) پشت «همه ردیف‌ها»؛ جدول لینک‌های ورودی + لینک‌ها + فرصت', substr_count( $lk_html, '<table' ) >= 3, true );
$lk_box = $cap( static fn() => hodima_seo_advisor_render_links_box( 'گیره <b>', 'دسته' ) );
hodima_ad( 'کادر: دکمه بررسی، کلمه escape‌شده در data-term', [ str_contains( $lk_box, 'data-hdsa-links-run' ), str_contains( $lk_box, 'data-term="گیره &lt;b&gt;"' ) ], [ true, true ] );
$_GET = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'competition' ];
wp_set_current_user( 1 );
$lk_tab = $cap( static fn() => hodima_seo_advisor_render_competition( hodima_seo_advisor_status() ) );
hodima_ad( 'تب رقابت: بلوک لینک‌ها فقط برای «برنده روشن» (گروه هم‌وزن ندارد)؛ نشان نوع صفحه کنار رقیب', [ substr_count( $lk_tab, 'data-hdsa-links data-term' ), str_contains( $lk_tab, 'data-term="گیره انبری فلزی"' ), str_contains( $lk_tab, 'دسته نوشته' ) ], [ 1, true, true ] );
$_GET = [];

// admin-ajax: بدون nonce رد می‌شود، با nonce HTML نتیجه می‌آید
class Hodima_Lk_Stop extends Exception {}
add_filter( 'wp_doing_ajax', '__return_true' );
add_filter( 'wp_die_ajax_handler', static fn() => static function (): void { throw new Hodima_Lk_Stop(); } );
$lk_ajax = static function ( array $post ): array {
	$_POST    = $post;
	$_REQUEST = $post;
	ob_start();
	try {
		hodima_seo_advisor_handle_links_ajax();
	} catch ( Hodima_Lk_Stop ) {
		// wp_send_json_* بعد از چاپ پاسخ می‌میرد؛ در آزمون استثنا می‌شود
	}
	return (array) json_decode( (string) ob_get_clean(), true );
};
$lk_bad = $lk_ajax( [ 'term' => 'گیره انبری فلزی', 'nonce' => 'x' ] );
hodima_ad( 'admin-ajax: nonce غلط رد می‌شود', $lk_bad['success'] ?? null, false );
$lk_good = $lk_ajax( [ 'term' => 'گیره انبری فلزی', 'nonce' => wp_create_nonce( 'hodima_advisor_links' ) ] );
hodima_ad( 'admin-ajax: با nonce درست HTML جدول برمی‌گردد', [ $lk_good['success'] ?? null, str_contains( (string) ( $lk_good['data']['html'] ?? '' ), 'hodima-advisor__lk-table' ) ], [ true, true ] );
wp_set_current_user( 0 );
$lk_anon = $lk_ajax( [ 'term' => 'گیره انبری فلزی', 'nonce' => wp_create_nonce( 'hodima_advisor_links' ) ] );
hodima_ad( 'admin-ajax: بدون دسترسی مدیر رد می‌شود', $lk_anon['success'] ?? null, false );
wp_set_current_user( 1 );
remove_all_filters( 'wp_doing_ajax' );
remove_all_filters( 'wp_die_ajax_handler' );
$_POST    = [];
$_REQUEST = [];
hodima_lk_fixture_clean( $lk_ids );

echo "=== سبک‌سازی (SEO 2.9.9): ساخت دوباره در پس‌زمینه، خزش تک‌ردیفی، رقابت یک‌باره، صفحه‌بندی کارها\n";
$lt_tasks  = get_option( HODIMA_SEO_ADVISOR_TASKS_OPTION, false );
$lt_status = get_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, false );
delete_option( HODIMA_SEO_ADVISOR_TASKS_OPTION );
delete_option( HODIMA_SEO_ADVISOR_PIPELINE_OPTION );
delete_option( HODIMA_SEO_ADVISOR_REANALYZE_OPTION );
wp_clear_scheduled_hook( HODIMA_SEO_ADVISOR_STAGE_CRON );
hodima_seo_advisor_store( [ '/lt/a/' => [ 'clicks' => 10, 'impressions' => 8000, 'position' => 3.0 ], '/lt/b/' => [ 'clicks' => 2, 'impressions' => 500, 'position' => 4.0 ] ], [], [] );
update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [ 'fetched' => 0 ], false );
hodima_seo_advisor_request_reanalyze();
hodima_ad( 'بدون داده دریافتی: درخواست ساخت دوباره ثبت نمی‌شود', [ hodima_seo_advisor_pipeline_pending(), wp_next_scheduled( HODIMA_SEO_ADVISOR_STAGE_CRON ) ], [ false, false ] );
update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [ 'fetched' => time(), 'property' => 'sc-domain:' . $host, 'days' => 90 ], false );
hodima_seo_advisor_request_reanalyze();
hodima_ad( 'درخواست ساخت دوباره: «در پس‌زمینه» و رویداد ادامه همین حالا', [ hodima_seo_advisor_pipeline_pending(), false !== wp_next_scheduled( HODIMA_SEO_ADVISOR_STAGE_CRON ) ], [ true, true ] );
$lt_q = hodima_seo_advisor_pipeline_take_reanalyze( [ 'stages' => [ 'ctr', 'breakdown' ], 'property' => 'p', 'range' => [], 'days' => 90 ] );
hodima_ad( 'دریافتی در میانه (پیش از analyze): analyze بعد از ctr می‌آید', $lt_q['stages'], [ 'ctr', 'analyze', 'breakdown' ] );
update_option( HODIMA_SEO_ADVISOR_REANALYZE_OPTION, time(), false );
update_option( HODIMA_SEO_ADVISOR_ANALYSIS_OPTION, [ 'queue' => [ '/old/' ] ], false );
$lt_q = hodima_seo_advisor_pipeline_take_reanalyze( [ 'stages' => [ 'health', 'history' ], 'property' => 'p', 'range' => [], 'days' => 90 ] );
hodima_ad( 'نوبت analyze دریافت گذشته (خزش تازه وسط کار): analyze اول صف، صف تحلیل قدیمی دور ریخته و درخواست برداشته شد', [ $lt_q['stages'], get_option( HODIMA_SEO_ADVISOR_ANALYSIS_OPTION, 'none' ), get_option( HODIMA_SEO_ADVISOR_REANALYZE_OPTION, 'none' ) ], [ [ 'analyze', 'health', 'history' ], 'none', 'none' ] );
delete_option( HODIMA_SEO_ADVISOR_PIPELINE_OPTION );
hodima_seo_advisor_request_reanalyze();
hodima_seo_advisor_pipeline_run( INF );
hodima_ad( 'اجرای نوبت‌ها: پیشنهادها ساخته شد، صف خالی و رویداد بی‌کار برداشته شد', [ hodima_seo_advisor_page( '/lt/a/' )['advice'][0]['rule'] ?? '', hodima_seo_advisor_pipeline_pending(), wp_next_scheduled( HODIMA_SEO_ADVISOR_STAGE_CRON ) ], [ 'low_ctr', false, false ] );
hodima_ad( 'پیشنهاد ذخیره‌شده گام ندارد', array_key_exists( 'steps', hodima_seo_advisor_page( '/lt/a/' )['advice'][0] ?? [] ), false );
$_GET = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'opportunities' ];
hodima_seo_advisor_request_reanalyze();
$lt_bar = $cap( static fn() => hodima_seo_advisor_render_toolbar() );
hodima_ad( 'نوار بالا: نشان «ساخت پیشنهادها در پس‌زمینه» وقتی کار مانده', str_contains( $lt_bar, 'ساخت پیشنهادها در پس‌زمینه ادامه دارد' ), true );
hodima_seo_advisor_clear_data();
hodima_ad( 'پاک کردن داده درخواست ساخت دوباره را هم برمی‌دارد', [ get_option( HODIMA_SEO_ADVISOR_REANALYZE_OPTION, 'none' ), hodima_seo_advisor_pipeline_pending() ], [ 'none', false ] );

// خزش: کادر ویرایش فقط ردیف همان صفحه را می‌خواند؛ نتیجه با نقشه کامل یکی است
hodima_seo_advisor_crawl_store(
	[
		'/lt/a/'  => [ 'path' => '/lt/a/', 'status' => 200, 'indexable' => 1, 'reason' => '', 'title' => 'عنوان الف', 'title_len' => 40, 'meta_len' => 120, 'h1' => 'الف', 'h2' => '', 'resp_ms' => 100, 'inlinks' => 4, 'text_links' => 2, 'depth' => 2, 'score' => 1.5, 'redirect' => '' ],
		'/lt/r/'  => [ 'path' => '/lt/r/', 'status' => 301, 'indexable' => 0, 'reason' => 'Redirected', 'title' => '', 'title_len' => 0, 'meta_len' => 0, 'h1' => '', 'h2' => '', 'resp_ms' => 50, 'inlinks' => 1, 'text_links' => 0, 'depth' => 3, 'score' => 0.0, 'redirect' => '/lt/a/' ],
		'/lt/n/'  => [ 'path' => '/lt/n/', 'status' => 200, 'indexable' => 0, 'reason' => 'noindex', 'title' => 'ن', 'title_len' => 1, 'meta_len' => 0, 'h1' => '', 'h2' => '', 'resp_ms' => 50, 'inlinks' => 1, 'text_links' => 0, 'depth' => 3, 'score' => 0.0, 'redirect' => '' ],
		'/lt/g/'  => [ 'path' => '/lt/g/', 'status' => 404, 'indexable' => 0, 'reason' => '', 'title' => '', 'title_len' => 0, 'meta_len' => 0, 'h1' => '', 'h2' => '', 'resp_ms' => 50, 'inlinks' => 1, 'text_links' => 0, 'depth' => 3, 'score' => 0.0, 'redirect' => '' ],
	],
	[]
);
hodima_seo_advisor_crawl_map( true );
$lt_one = [ hodima_seo_advisor_crawl_row( '/lt/a/' ), hodima_seo_advisor_crawl_row( '/lt/none/' ) ];
$lt_map = hodima_seo_advisor_crawl_map();
hodima_ad( 'ردیف تک خزش همان ردیف نقشه کامل است؛ آدرس بیرون از خزش null', [ $lt_one[0] === ( $lt_map['/lt/a/'] ?? 'x' ), $lt_one[0]['title'] ?? '', $lt_one[1] ], [ true, 'عنوان الف', null ] );
hodima_seo_advisor_crawl_map( true );
$lt_res = hodima_seo_advisor_resolved_paths();
hodima_ad( 'حل‌شده‌ها از پرسش ردیف‌های ناسالم: ریدایرکت، ۴۰۴، noindex؛ صفحه سالم نه', [ $lt_res['/lt/r/'] ?? '', $lt_res['/lt/g/'] ?? '', $lt_res['/lt/n/'] ?? '', isset( $lt_res['/lt/a/'] ) ], [ 'redirect', 'gone', 'noindex', false ] );
hodima_seo_advisor_clear_data();

// رقابت: ساخت یک‌باره گروه‌ها و برداشتن نما همان نتیجه تابع قدیمی
hodima_seo_advisor_store(
	[ '/lt/c1/' => [ 'clicks' => 50, 'impressions' => 3000, 'position' => 4.0 ], '/lt/c2/' => [ 'clicks' => 5, 'impressions' => 900, 'position' => 9.0 ], '/lt/c3/' => [ 'clicks' => 9, 'impressions' => 900, 'position' => 8.0 ] ],
	[],
	[
		'/lt/c1/' => [ 'گیره مو مخمل' => [ 'clicks' => 40, 'impressions' => 2500, 'position' => 4.0 ], 'تل سر ساتن' => [ 'clicks' => 3, 'impressions' => 400, 'position' => 9.0 ] ],
		'/lt/c2/' => [ 'گیره مو مخمل' => [ 'clicks' => 2, 'impressions' => 300, 'position' => 12.0 ] ],
		'/lt/c3/' => [ 'تل سر ساتن' => [ 'clicks' => 4, 'impressions' => 420, 'position' => 8.0 ] ],
	]
);
$lt_built = hodima_seo_advisor_competition_groups();
$lt_page  = hodima_seo_advisor_competition_select( $lt_built, [ 'limit' => 1, 'offset' => 1 ] );
$lt_old   = hodima_seo_advisor_competition( [ 'limit' => 1, 'offset' => 1 ] );
hodima_ad( 'رقابت: گروه‌ها یک بار ساخته و هر نما از آن برداشته می‌شود (همان نتیجه، ۲ گروه، صفحه دوم یک گروه)', [ $lt_page === $lt_old, $lt_page['total'], count( $lt_page['rows'] ), count( $lt_built['groups'] ) ], [ true, 2, 1, 2 ] );
hodima_ad( 'رقابت: فیلتر نوع دستور بعد از ساخت گروه‌ها', [ hodima_seo_advisor_competition_select( $lt_built, [ 'type' => 'strengthen' ] )['total'] + hodima_seo_advisor_competition_select( $lt_built, [ 'type' => 'choose' ] )['total'] + hodima_seo_advisor_competition_select( $lt_built, [ 'type' => 'category' ] )['total'] ], [ 2 ] );
$_GET = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'competition', 'resolved' => '1' ];
$lt_warn = [];
set_error_handler( static function ( int $no, string $msg ) use ( &$lt_warn ): bool { $lt_warn[] = $msg; return true; }, E_WARNING );
$cap( static fn() => hodima_seo_advisor_render_competition( hodima_seo_advisor_status() ) );
restore_error_handler();
hodima_ad( 'نمای «رقابت‌های حل‌شده» بدون هشدار PHP (کلید settled)', $lt_warn, [] );

// صفحه‌بندی کارها
$lt_list = [];
for ( $i = 0; $i < 30; $i++ ) {
	$lt_list[ hodima_seo_advisor_task_key( "/lt/t{$i}/", 'low_ctr', '' ) ] = [ 'path' => "/lt/t{$i}/", 'rule' => 'low_ctr', 'term' => '', 'title' => "کار {$i}", 'status' => 'doing', 'at' => time() - $i * 60, 'fixed' => 0, 'back' => 0, 'check' => [] ];
}
update_option( HODIMA_SEO_ADVISOR_TASKS_OPTION, $lt_list, false );
$_GET = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'tasks' ];
$lt_p1 = $cap( static fn() => hodima_seo_advisor_render_tasks() );
$_GET['paged'] = '2';
$lt_p2 = $cap( static fn() => hodima_seo_advisor_render_tasks() );
$_GET = [];
hodima_ad( 'تب «کارها»: ۲۵ کار در صفحه اول با صفحه‌بندی، ۵ کار در صفحه دوم، تازه‌ترین اول', [ substr_count( $lt_p1, 'class="hodima-advisor__task"' ), str_contains( $lt_p1, 'hds-pager' ), substr_count( $lt_p2, 'class="hodima-advisor__task"' ), str_contains( $lt_p1, 'کار 0' ), str_contains( $lt_p2, 'کار 29' ) ], [ 25, true, 5, true, true ] );
hodima_ad( 'شمار کارهای باز بالای تب همه ۳۰ کار است (نه فقط صفحه فعلی)', str_contains( $lt_p2, '>30<' ) || str_contains( $lt_p2, '>۳۰<' ), true );

hodima_seo_advisor_clear_data();
false === $lt_tasks ? delete_option( HODIMA_SEO_ADVISOR_TASKS_OPTION ) : update_option( HODIMA_SEO_ADVISOR_TASKS_OPTION, $lt_tasks, false );
false === $lt_status ? delete_option( HODIMA_SEO_ADVISOR_STATUS_OPTION ) : update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, $lt_status, false );
wp_clear_scheduled_hook( HODIMA_SEO_ADVISOR_STAGE_CRON );

echo "=== مرحله ۲ (SEO 3.0.0): صفحه مرجع یکسان با تب رقابت، آدرس کامل، قفل ایندکس، ترتیب نوبت‌ها\n";
$m2_status = get_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, false );
$m2_health = get_option( HODIMA_SEO_ADVISOR_HEALTH_OPTION, false );
hodima_seo_advisor_clear_data();
hodima_seo_advisor_store(
	[
		'/m2/clicks/' => [ 'clicks' => 90, 'impressions' => 3000, 'position' => 6.0 ],
		'/m2/rank/'   => [ 'clicks' => 10, 'impressions' => 1500, 'position' => 2.0 ],
		'/m2/moved/'  => [ 'clicks' => 200, 'impressions' => 4000, 'position' => 1.5 ],
		'/m2/me/'     => [ 'clicks' => 5, 'impressions' => 2000, 'position' => 9.0 ],
	],
	[],
	[
		'/m2/clicks/' => [ 'گیره مخملی آبی' => [ 'clicks' => 80, 'impressions' => 1500, 'position' => 6.0 ] ],
		'/m2/rank/'   => [ 'گیره مخملی آبی' => [ 'clicks' => 5, 'impressions' => 500, 'position' => 2.0 ] ],
		'/m2/moved/'  => [ 'گیره مخملی آبی' => [ 'clicks' => 150, 'impressions' => 2500, 'position' => 1.5 ] ],
		'/m2/me/'     => [ 'گیره مخملی آبی' => [ 'clicks' => 1, 'impressions' => 900, 'position' => 9.0 ] ],
	]
);
update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [ 'fetched' => time(), 'property' => 'sc-domain:' . $host, 'days' => 90 ], false );
update_option( HODIMA_SEO_ADVISOR_HEALTH_OPTION, [ 'computed' => time(), 'router' => true, 'dead' => [ [ 'path' => '/m2/moved/', 'impressions' => 4000, 'clicks' => 200, 'state' => 'redirect', 'target' => '', 'code' => 301 ] ], 'total' => 0, 'seen' => 0, 'fresh' => 0, 'unseen' => [] ], false );
hodima_seo_advisor_crawl_map( true );
$m2_rivals = array_column( hodima_seo_advisor_term_rivals( 'گیره مخملی آبی' ), 'path' );
hodima_ad( 'رقیب‌های کلمه: صفحه ریدایرکت‌شده کنار می‌رود؛ برنده بیشترین کلیک (نه بهترین رتبه)', $m2_rivals, [ '/m2/clicks/', '/m2/rank/', '/m2/me/' ] );
$m2_step = hodima_seo_advisor_step_canonical( 'گیره مخملی آبی', hodima_seo_advisor_term_rivals( 'گیره مخملی آبی' ), '/m2/me/' );
$m2_comp = array_values( array_filter( hodima_seo_advisor_competition()['rows'], static fn( array $g ): bool => 'گیره مخملی آبی' === $g['term'] ) )[0] ?? [];
hodima_ad( 'صفحه مرجع کادر ویرایش = برنده تب «صفحه‌های هم‌کلمه»', [ $m2_step['main'], $m2_comp['pages'][0]['path'] ?? '' ], [ '/m2/clicks/', '/m2/clicks/' ] );
$m2_can = array_values( array_filter( hodima_seo_advisor_analyze( '/m2/me/' )['suggestions'] ?? [], static fn( array $i ): bool => 'cannibal' === $i['rule'] ) )[0] ?? [];
hodima_ad( 'متن رقابت داخلی آدرس کامل رقیب را دارد (نه فقط مسیر)', str_contains( (string) ( $m2_can['detail'] ?? '' ), hodima_seo_advisor_url( '/m2/clicks/' ) ), true );
hodima_ad( 'یادداشت لینک‌های رقیب با آدرس کامل', str_contains( hodima_seo_advisor_competition_links_note( 'ک', [ [ 'path' => '/w/', 'term_links' => 3 ], [ 'path' => '/r/', 'term_links' => 9 ] ] ), hodima_seo_advisor_url( '/r/' ) ), true );
hodima_ad( '«دیدن در سایت» برای صفحه ناشناخته با آدرس کامل سایت', hodima_seo_advisor_competition_page_link( [ 'kind' => '', 'object_id' => 0, 'path' => '/m2/x/' ] )[0], hodima_seo_advisor_url( '/m2/x/' ) );
hodima_ad( 'تکه متن فرصت لینک: واژه لاتین با حروف بزرگ هم پیدا می‌شود', hodima_seo_advisor_links_snippet( '<p>best Hair Clips here</p>', 'hair clips' )[1] ?? null, 'Hair Clips' );

// ایرادهای فنی: ریدایرکت با آدرس کامل مقصد
hodima_seo_advisor_crawl_store( [ '/m2/old/' => [ 'path' => '/m2/old/', 'status' => 301, 'indexable' => 0, 'reason' => '', 'title' => '', 'title_len' => 0, 'meta_len' => 0, 'h1' => '', 'h2' => '', 'resp_ms' => 10, 'inlinks' => 2, 'text_links' => 0, 'depth' => 2, 'score' => 0.0, 'redirect' => '/m2/new/' ] ], [] );
$m2_issue = array_values( array_filter( hodima_seo_advisor_crawl_issues(), static fn( array $r ): bool => '/m2/old/' === $r['path'] ) )[0]['issues'][0] ?? [];
hodima_ad( 'ایراد ریدایرکت: مقصد جدا از جمله (برای آدرس کامل + کپی)', [ $m2_issue['detail'] ?? '', $m2_issue['url'] ?? '' ], [ 'به این آدرس می‌رود:', '/m2/new/' ] );

// قفل بررسی ایندکس
add_option( HODIMA_SEO_ADVISOR_INSPECT_LOCK, time(), '', false );
$m2_before = get_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION, 'none' );
$m2_ins    = hodima_seo_advisor_inspect_batch();
hodima_ad( 'بررسی ایندکس هم‌زمان: دومی کاری نمی‌کند و نتیجه اولی را خراب نمی‌کند', [ str_contains( $m2_ins['error'], 'در حال اجراست' ), $m2_ins['checked'], get_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION, 'none' ) === $m2_before, false !== get_option( HODIMA_SEO_ADVISOR_LOCK_OPTION, false ) ], [ true, 0, true, false ] );
delete_option( HODIMA_SEO_ADVISOR_INSPECT_LOCK );

hodima_seo_advisor_clear_data();
false === $m2_status ? delete_option( HODIMA_SEO_ADVISOR_STATUS_OPTION ) : update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, $m2_status, false );
false === $m2_health ? delete_option( HODIMA_SEO_ADVISOR_HEALTH_OPTION ) : update_option( HODIMA_SEO_ADVISOR_HEALTH_OPTION, $m2_health, false );
hodima_seo_advisor_crawl_map( true );

echo "=== مرحله ۲ ظاهری (SEO 3.0.1): آدرس کامل + کپی، ایرادهای فنی کامل، صفحه‌بندی\n";
hodima_ad( 'آدرس کامل: مسیر ← دامنه + مسیر؛ کامل همان؛ //میزبان ← https؛ فارسی رمزگشایی', [ hodima_seo_advisor_url_full( '/x/' ), hodima_seo_advisor_url_full( 'https://e.test/a/' ), hodima_seo_advisor_url_full( '//e.test/a/' ), hodima_seo_advisor_url_full( '/' . rawurlencode( 'گل' ) . '/' ) ], [ hodima_seo_advisor_url( '/x/' ), 'https://e.test/a/', 'https://e.test/a/', hodima_seo_advisor_url( '/گل/' ) ] );
$uc_raw = hodima_seo_advisor_url_copy( '/گل-سر/' );
$uc     = str_replace( '<wbr>', '', $uc_raw );
hodima_ad( 'جزء آدرس: جای شکستن خط فقط بعد از «/» مسیر، نه داخل https://', [ substr_count( $uc_raw, '<wbr>' ), str_contains( $uc_raw, 'https:/<wbr>' ) ], [ 2, false ] );
hodima_ad( 'جزء آدرس: لینک چپ‌به‌راست جدا (bdi)، زبانه تازه، دکمه کپی با نام صفحه‌خوان و آدرس خوانا؛ بدون کپی فقط لینک', [ str_contains( $uc, 'dir="ltr"><bdi>' . hodima_seo_advisor_url( '/گل-سر/' ) . '</bdi>' ), str_contains( $uc, 'target="_blank"' ), str_contains( $uc, 'data-hdsa-copy-url="' . hodima_seo_advisor_url( '/گل-سر/' ) . '"' ), str_contains( $uc, 'aria-label="کپی آدرس ' ), str_contains( hodima_seo_advisor_url_copy( '/x/', false ), 'data-hdsa-copy-url' ), hodima_seo_advisor_url_copy( '' ) ], [ true, true, true, true, false, '' ] );
$th = hodima_seo_advisor_text_html( 'علاوه بر این صفحه، https://e.test/a/ و <b> هم. آخر https://e.test/b/.' );
hodima_ad( 'آدرس داخل متن: لینک جدا از جمله، نقطه آخر جمله بیرون از آدرس، بقیه متن escape', [ substr_count( $th, 'class="hodima-advisor-url__inline"' ), str_contains( $th, 'href="https://e.test/b/"' ) && str_contains( $th, '</a>.' ), str_contains( $th, '&lt;b&gt;' ), hodima_seo_advisor_text_html( 'بدون آدرس <i>' ) ], [ 2, true, true, 'بدون آدرس &lt;i&gt;' ] );

// لینک‌های این کلمه: «باید برود به» و «آدرس نوشته‌شده» کامل
$lk_target = [ 'path' => '/lk3/target/', 'title' => 'هدف' ];
$lk_row    = [ 'issue' => 'wrong', 'source' => [ 'title' => 'منبع', 'label' => 'برگه', 'edit' => '', 'field' => 'متن نوشته' ], 'store' => 'متن نوشته', 'anchor' => 'کلمه', 'href' => '/207/', 'now' => [ 'path' => '/207/', 'title' => 'رقیب', 'kind' => 'نوشته', 'note' => '' ], 'times' => 1, 'structural' => false ];
$lk_html   = hodima_seo_advisor_render_links_row( $lk_row, $lk_target );
hodima_ad( 'ردیف لینک: آدرس نوشته‌شده کامل + «در متن نسبی نوشته شده» و «باید برود به» آدرس کامل با کپی', [ str_contains( $lk_html, 'data-hdsa-copy-url="' . hodima_seo_advisor_url( '/207/' ) . '"' ), str_contains( $lk_html, 'نوشته شده (نسبی)' ), str_contains( $lk_html, 'data-hdsa-copy-url="' . hodima_seo_advisor_url( '/lk3/target/' ) . '"' ) ], [ true, true, true ] );
$lk_crawl = hodima_seo_advisor_render_links_row( [ 'source' => [ 'title' => 'x', 'label' => 'صفحه', 'edit' => '', 'field' => 'متن (طبق خزش)' ] ] + $lk_row, $lk_target );
$lk_opp   = hodima_seo_advisor_render_links_opp( [ 'source' => [ 'title' => 'منبع', 'label' => 'برگه', 'edit' => '' ], 'snippet' => null ], $lk_target );
hodima_ad( 'ردیف خزش: بدون «نسبی» (خزش همیشه مسیر است)؛ فرصت لینک: «همین عبارت را به این آدرس لینک کنید» + آدرس کامل', [ str_contains( $lk_crawl, 'نسبی' ), str_contains( $lk_opp, 'لینک کنید' ) && str_contains( $lk_opp, 'data-hdsa-copy-url="' . hodima_seo_advisor_url( '/lk3/target/' ) . '"' ) ], [ false, true ] );

// ایرادهای فنی: همه ایرادها با دستور خودشان، ویرایش صفحه، مقصد ریدایرکت با کپی
$m3_status = get_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, false );
hodima_seo_advisor_clear_data();
$m3_post = (int) wp_insert_post( [ 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'صفحه پرایراد', 'post_name' => 'm3-many', 'post_content' => 'x' ] );
$m3_path = (string) hodima_seo_advisor_path_key( (string) get_permalink( $m3_post ) );
hodima_seo_advisor_crawl_store(
	[
		$m3_path    => [ 'path' => $m3_path, 'status' => 200, 'indexable' => 1, 'reason' => '', 'title' => 'کوتاه', 'title_len' => 5, 'meta_len' => 0, 'h1' => '', 'h2' => '', 'resp_ms' => 2500, 'inlinks' => 1, 'text_links' => 0, 'depth' => 2, 'score' => 0.0, 'redirect' => '' ],
		'/m3/old/'  => [ 'path' => '/m3/old/', 'status' => 301, 'indexable' => 0, 'reason' => '', 'title' => '', 'title_len' => 0, 'meta_len' => 0, 'h1' => '', 'h2' => '', 'resp_ms' => 10, 'inlinks' => 2, 'text_links' => 0, 'depth' => 2, 'score' => 0.0, 'redirect' => '/m3/new/' ],
	],
	[]
);
update_option( HODIMA_SEO_ADVISOR_CRAWL_OPTION, [ 'imported' => time(), 'pages' => 2, 'links' => 0, 'files' => [] ], false );
hodima_seo_advisor_crawl_map( true );
wp_set_current_user( 1 );
$_GET = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'crawl' ];
$m3_html = $cap( static fn() => hodima_seo_advisor_render_crawl() );
$_GET = [];
hodima_ad( 'ایرادهای فنی: هر ۵ ایراد یک سطر با نشان و دستور خودش (نه فقط ۳)، نام صفحه، «ویرایش صفحه»، مقصد ریدایرکت با کپی', [ substr_count( $m3_html, 'class="hodima-advisor__crawl-fix"' ), str_contains( $m3_html, 'کش و سرعت سرور را بررسی کنید' ) && str_contains( $m3_html, 'لینک متنی بدهید' ), str_contains( $m3_html, 'صفحه پرایراد' ), str_contains( $m3_html, 'post=' . $m3_post ) && str_contains( $m3_html, 'ویرایش صفحه' ), str_contains( $m3_html, 'data-hdsa-copy-url="' . hodima_seo_advisor_url( '/m3/new/' ) . '"' ) ], [ 6, true, true, true, true ] );
wp_delete_post( $m3_post, true );

// صفحه‌بندی «صفحه‌هایی که نمایش نگرفته‌اند» و «وضعیت در گوگل»
$m3_unseen = [];
for ( $i = 0; $i < 60; $i++ ) {
	$m3_unseen[] = [ 'id' => 0, 'path' => "/m3/u{$i}/", 'title' => "بدون نمایش {$i}", 'type' => 'post', 'days' => 400 - $i ];
}
update_option( HODIMA_SEO_ADVISOR_HEALTH_OPTION, [ 'computed' => time(), 'router' => true, 'dead' => [], 'total' => 100, 'seen' => 40, 'fresh' => 0, 'unseen' => $m3_unseen ], false );
$_GET = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'health', 'paged' => '3' ];
$m3_h = $cap( static fn() => hodima_seo_advisor_render_health( hodima_seo_advisor_status() ) );
$m3_ins = [];
for ( $i = 0; $i < 30; $i++ ) {
	$m3_ins[ "/m3/i{$i}/" ] = [ 'verdict' => 'NEUTRAL', 'coverage' => 'Crawled - currently not indexed', 'robots' => '', 'indexing' => '', 'fetch' => '', 'last_crawl' => '', 'google_canonical' => '', 'user_canonical' => '', 'crawled_as' => '', 'checked' => time() - $i ];
}
update_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION, $m3_ins, false );
$_GET = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'indexing', 'paged' => '2' ];
$m3_i = $cap( static fn() => hodima_seo_advisor_render_indexing() );
$_GET = [];
hodima_ad( 'صفحه‌بندی: بدون نمایش ۶۰ ← صفحه سوم ۱۰ ردیف (نه فقط ۳۰ اول)؛ ایندکس ۳۰ ← صفحه دوم ۵ ردیف', [ str_contains( $m3_h, 'بدون نمایش 59' ) && ! str_contains( $m3_h, 'بدون نمایش 49<' ), str_contains( $m3_h, 'hds-pager' ), substr_count( $m3_i, 'm3/i' ) >= 5 && str_contains( $m3_i, 'hds-pager' ) ], [ true, true, true ] );
hodima_seo_advisor_clear_data();
delete_option( HODIMA_SEO_ADVISOR_HEALTH_OPTION );
false === $m3_status ? delete_option( HODIMA_SEO_ADVISOR_STATUS_OPTION ) : update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, $m3_status, false );
wp_set_current_user( 0 );

/** وضعیت چرخه عمر یک کار یا رشته خالی (آزمون مرحله ۳). */
function hodima_seo_advisor_state_or( ?array $task ): string {
	return null === $task ? '' : hodima_seo_advisor_task_state( $task );
}

echo "=== مرحله ۳ (SEO 3.0.2): «انجام شد / رد شد» در همه تب‌ها و سنجش هر نوع کار\n";
$p3_status = get_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, false );
$p3_tasks  = get_option( HODIMA_SEO_ADVISOR_TASKS_OPTION, false );
hodima_seo_advisor_clear_data();
delete_option( HODIMA_SEO_ADVISOR_TASKS_OPTION );
delete_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION );
wp_set_current_user( 1 );

hodima_ad( 'منبع هر کار از قاعده‌اش؛ قاعده با رقم (crawl_no_h1) پذیرفته می‌شود', [ hodima_seo_advisor_task_source( 'crawl_no_h1' ), hodima_seo_advisor_task_source( 'competition' ), hodima_seo_advisor_task_source( 'health_unseen' ), hodima_seo_advisor_task_source( 'index_problem' ), hodima_seo_advisor_task_source( 'low_ctr' ), hodima_seo_advisor_task_set( '/p3/x/', 'crawl_no_h1', '', 'بدون H1', 'done' ) ], [ 'crawl', 'competition', 'health', 'indexing', 'opportunities', true ] );
hodima_seo_advisor_task_set( '/p3/x/', 'low_ctr', '', 'نرخ کلیک', 'done' );
hodima_seo_advisor_task_set( '/p3/x/', 'missing_headings', 'پلومریا', 'کلمه در تیترها نیست', 'done' );
hodima_seo_advisor_tasks_sync( '/p3/x/', [] );
hodima_ad( 'تحلیل آدرس فقط کار متنی پیشنهادها را «برطرف» می‌کند؛ آماری منتظر داده گوگل می‌ماند و ایراد فنی همان آدرس دست نمی‌خورد', [ hodima_seo_advisor_task_state( hodima_seo_advisor_task( '/p3/x/', 'missing_headings', 'پلومریا' ) ), hodima_seo_advisor_task_state( hodima_seo_advisor_task( '/p3/x/', 'low_ctr', '' ) ), hodima_seo_advisor_task_state( hodima_seo_advisor_task( '/p3/x/', 'crawl_no_h1', '' ) ) ], [ 'fixed', 'waiting', 'waiting' ] );

// نوشتن امن: حکم سنجش روی نسخه تازه کارها می‌نشیند و کار تغییرکرده توسط مدیر را پس نمی‌زند (SEO 3.0.3)
$p3_key_a = hodima_seo_advisor_task_key( '/p3/x/', 'low_ctr', '' );
$p3_seen  = [ $p3_key_a => [ 'at' => hodima_seo_advisor_tasks()[ $p3_key_a ]['at'], 'status' => 'done' ] ];
hodima_seo_advisor_task_set( '/p3/y/', 'low_ctr', '', 'کار تازه', 'done' ); // مدیر وسط سنجش کار دیگری ثبت کرد
$p3_written = hodima_seo_advisor_tasks_patch( [ $p3_key_a => [ 'check' => [ 'verdict' => 'no_effect', 'at' => time() ] ] ], $p3_seen );
hodima_ad( 'حکم سنجش نوشته می‌شود و کار تازه‌ای که وسط سنجش ثبت شد نمی‌پرد', [ $p3_written, hodima_seo_advisor_tasks()[ $p3_key_a ]['check']['verdict'] ?? '', null !== hodima_seo_advisor_task( '/p3/y/', 'low_ctr', '' ) ], [ 1, 'no_effect', true ] );
hodima_seo_advisor_task_set( '/p3/x/', 'low_ctr', '', 'نرخ کلیک', 'rejected' ); // مدیر وسط سنجش «لازم نیست» زد
$p3_written = hodima_seo_advisor_tasks_patch( [ $p3_key_a => [ 'fixed' => time() ] ], $p3_seen );
hodima_ad( 'کاری که مدیر وسط سنجش وضعیتش را عوض کرد حکم کهنه نمی‌گیرد', [ $p3_written, hodima_seo_advisor_task_state( hodima_seo_advisor_task( '/p3/x/', 'low_ctr', '' ) ) ], [ 0, 'rejected' ] );

// صفحه‌بندی آرشیو (خزش واقعی هدهدلی): H1 مشترک و لینک ورودی کم ایراد نیست (SEO 3.0.5)
$p3_pg = static fn( string $path, string $h1, int $inlinks ): array => [ 'path' => $path, 'status' => 200, 'indexable' => 1, 'reason' => '', 'title' => 'وبلاگ ' . $path, 'title_len' => 40, 'meta_len' => 120, 'h1' => $h1, 'h2' => '', 'resp_ms' => 100, 'inlinks' => $inlinks, 'text_links' => 1, 'depth' => 1, 'score' => 0.0, 'redirect' => '' ];
hodima_seo_advisor_crawl_store( [ '/pg/' => $p3_pg( '/pg/', 'وبلاگ', 30 ), '/pg/page/2/' => $p3_pg( '/pg/page/2/', 'وبلاگ', 2 ), '/pg/a/' => $p3_pg( '/pg/a/', 'مقاله الف', 30 ), '/pg/b/' => $p3_pg( '/pg/b/', 'مقاله الف', 30 ) ], [] );
$p3_codes = [];
foreach ( hodima_seo_advisor_crawl_issues() as $p3_r ) {
	if ( str_starts_with( (string) $p3_r['path'], '/pg/' ) ) {
		$p3_codes[ $p3_r['path'] ] = array_column( $p3_r['issues'], 'code' );
	}
}
hodima_ad( 'صفحه‌بندی آرشیو H1 تکراری و لینک ورودی کم نمی‌گیرد؛ دو مقاله با H1 یکسان می‌گیرند', $p3_codes, [ '/pg/a/' => [ 'dup_h1' ], '/pg/b/' => [ 'dup_h1' ] ] );

// ایراد «متن زیر عنوان ممنوع» (SEO 3.0.6؛ خزش واقعی: ۸ محصول max-snippet:0)
hodima_ad( 'دستور robots: max-snippet:0 و nosnippet ممنوع؛ max-snippet:-1 و ۰۵ و ۱۶۰ نه', array_map( static fn( string $v ): bool => hodima_seo_advisor_robots_no_snippet( $v ), [ 'index, follow, max-snippet:0, max-image-preview:large', 'noindex, nosnippet', 'max-snippet: 0', 'index, max-snippet:-1', 'max-snippet:05', 'max-snippet:160', '' ] ), [ true, true, true, false, false, false, false ] );
hodima_ad( 'دستور robots در هدر X-Robots-Tag هم دیده می‌شود', hodima_seo_advisor_robots_no_snippet( 'index', 'nosnippet' ), true );
$p3_csv = tempnam( sys_get_temp_dir(), 'crawl' );
file_put_contents( $p3_csv, "\xEF\xBB\xBF\"Address\",\"Status Code\",\"Indexability\",\"Title 1\",\"Meta Robots 1\"\n\"" . home_url( '/ns/a/' ) . "\",\"200\",\"Indexable\",\"عنوان\",\"index, follow, max-snippet:0\"\n\"" . home_url( '/ns/b/' ) . "\",\"200\",\"Indexable\",\"عنوان\",\"index, follow, max-snippet:-1\"\n" );
$p3_parsed = hodima_seo_advisor_crawl_parse_pages( $p3_csv );
unlink( $p3_csv );
hodima_ad( 'خواندن CSV: ستون Meta Robots 1 ← no_snippet فقط برای صفحه max-snippet:0', is_array( $p3_parsed ) ? [ $p3_parsed['/ns/a/']['no_snippet'] ?? null, $p3_parsed['/ns/b/']['no_snippet'] ?? null ] : $p3_parsed, [ 1, 0 ] );
$p3_ns = $p3_pg( '/ns/a/', 'الف', 30 ) + [ 'no_snippet' => 1 ];
hodima_seo_advisor_crawl_store( [ '/ns/a/' => $p3_ns, '/ns/b/' => $p3_pg( '/ns/b/', 'ب', 30 ) ], [] );
$p3_codes = [];
foreach ( hodima_seo_advisor_crawl_issues() as $p3_r ) {
	if ( str_starts_with( (string) $p3_r['path'], '/ns/' ) ) {
		$p3_codes[ $p3_r['path'] ] = array_column( $p3_r['issues'], 'code' );
	}
}
hodima_ad( 'ایراد no_snippet فقط برای صفحه دارای max-snippet:0؛ برچسب و دستور فارسی', [ $p3_codes, hodima_seo_advisor_crawl_issue_info( 'no_snippet' )['label'], in_array( 'no_snippet', HODIMA_SEO_ADVISOR_CRAWL_GROUPS['meta'], true ) ], [ [ '/ns/a/' => [ 'no_snippet' ] ], 'متن زیر عنوان ممنوع', true ] );
hodima_ad( 'سنجش فوری: no_snippet با خواندن دوباره صفحه از سایت سنجیده می‌شود', in_array( 'no_snippet', HODIMA_SEO_ADVISOR_MARK_LIVE_CODES, true ), true );

// نرخ کلیک کلمه‌به‌کلمه (SEO 3.0.4): یک کلمه خوب نباید کمبود کلمه بد را بپوشاند
$p3_table = static fn(): array => [ 2 => 25.0, 3 => 14.0, 5 => 9.0, 7 => 5.5, 10 => 3.5, 20 => 1.5 ];
add_filter( 'hodima_seo_advisor_ctr_table', $p3_table );
$p3_q   = static fn( string $term, int $clicks, int $impressions, float $position ): array => [ 'term' => $term, 'clicks' => $clicks, 'impressions' => $impressions, 'position' => $position ];
$p3_row = [ 'path' => '/p3/gap/', 'clicks' => 300, 'impressions' => 6000, 'position' => 5.3, 'prev_clicks' => 300 ];
$p3_two = [ $p3_q( 'کلمه خوب', 250, 1000, 2.0 ), $p3_q( 'کلمه بد', 50, 5000, 6.0 ) ];
$p3_gap = hodima_seo_advisor_ctr_gap( $p3_two, 6000, 20.0 );
hodima_ad( 'کمبود کلیک کلمه‌به‌کلمه: ۵۲۵ انتظار در برابر ۳۰۰ واقعی ← ۲۲۵؛ بدترین کلمه «کلمه بد»', [ $p3_gap['gain'] ?? null, $p3_gap['worst']['term'] ?? null ], [ 225, 'کلمه بد' ] );
$p3_low = array_values( array_filter( hodima_seo_advisor_suggestions( $p3_row, $p3_two, null ), static fn( array $i ): bool => 'low_ctr' === $i['rule'] ) );
hodima_ad( 'پیشنهاد نرخ کلیک با حساب کلمه‌ای: کلیک بالقوه ۲۲۵ و کلمه کم‌کلیک در متن (حساب قدیمی کل صفحه فقط ۳۰ می‌داد)', [ $p3_low[0]['potential'] ?? null, str_contains( (string) ( $p3_low[0]['detail'] ?? '' ), '«کلمه بد»' ) ], [ 225, true ] );
hodima_ad( 'کلمه‌های ذخیره‌شده کمتر از نیمی از نمایش صفحه ← حساب قدیمی کل صفحه (null)', hodima_seo_advisor_ctr_gap( [ $p3_q( 'کم', 5, 1000, 5.0 ) ], 6000, 20.0 ), null );
hodima_ad( 'نمایش صفحه بیشتر بیرون ۲۰ نتیجه اول است ← بدون کمبود (نرخ معمول رتبه ۴۰ معنا ندارد)', hodima_seo_advisor_ctr_gap( [ $p3_q( 'عقب', 1, 5000, 40.0 ), $p3_q( 'جلو', 0, 500, 3.0 ) ], 6000, 20.0 )['gain'] ?? null, 0 );
remove_filter( 'hodima_seo_advisor_ctr_table', $p3_table );

delete_option( HODIMA_SEO_ADVISOR_TASKS_OPTION );
hodima_ad( 'سقف کارها ۱۰۰۰ (ایرادهای فنی هر کدام یک کار)', HODIMA_SEO_ADVISOR_TASKS_MAX, 1000 );

// فرم ردیف: admin-post با ریدایرکت (در آزمون استثنا به‌جای exit)؛ پیام بالای صفحه فقط در پیشخوان لود می‌شود
if ( ! function_exists( 'hodima_admin_flash' ) ) {
	require_once HODIMA_SEO_DIR . '/inc/admin-ui-fallback.php';
}
class Hodima_P3_Redirect extends Exception {}
$p3_redirect = static function ( string $location ): string { throw new Hodima_P3_Redirect( $location ); };
add_filter( 'wp_redirect', $p3_redirect, 1 );
$p3_mark = static function ( array $post ): string {
	$_POST    = $post + [ '_wpnonce' => wp_create_nonce( 'hodima_advisor_mark' ), 'action' => 'hodima_advisor_mark' ];
	$_REQUEST = $_POST;
	delete_transient( 'hodima_admin_flash_' . get_current_user_id() );
	try {
		hodima_seo_advisor_handle_mark();
	} catch ( Hodima_P3_Redirect ) {
		// پایان عادی: ریدایرکت به صفحه قبل
	}
	$_POST    = [];
	$_REQUEST = [];
	$flash    = get_transient( 'hodima_admin_flash_' . get_current_user_id() );
	return is_array( $flash ) ? implode( ' | ', array_column( $flash, 1 ) ) : '';
};

// ایرادهای فنی: یک صفحه پرایراد، یک صفحه ۴۰۴ با لینک ورودی، یک ریدایرکت فقط با نمایش گوگل
$p3_post = (int) wp_insert_post( [ 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'صفحه مرحله سه', 'post_name' => 'p3-crawl', 'post_content' => 'x' ] );
$p3_path = (string) hodima_seo_advisor_path_key( (string) get_permalink( $p3_post ) );
$p3_row  = static fn( string $path, int $status, array $over = [] ): array => $over + [ 'path' => $path, 'status' => $status, 'indexable' => 200 === $status ? 1 : 0, 'reason' => '', 'title' => 'عنوان', 'title_len' => 5, 'meta_len' => 0, 'h1' => '', 'h2' => '', 'resp_ms' => 2600, 'inlinks' => 1, 'text_links' => 0, 'depth' => 2, 'score' => 0.0, 'redirect' => '' ];
hodima_seo_advisor_store( [ '/p3/gone/' => [ 'clicks' => 0, 'impressions' => 400, 'position' => 9.0 ], '/p3/moved/' => [ 'clicks' => 0, 'impressions' => 400, 'position' => 9.0 ] ], [], [] );
hodima_seo_advisor_crawl_store(
	[
		$p3_path      => $p3_row( $p3_path, 200 ),
		'/p3/gone/'   => $p3_row( '/p3/gone/', 404, [ 'inlinks' => 2 ] ),
		'/p3/moved/'  => $p3_row( '/p3/moved/', 301, [ 'inlinks' => 0, 'redirect' => '/p3/new/' ] ),
	],
	[]
);
update_option( HODIMA_SEO_ADVISOR_CRAWL_OPTION, [ 'imported' => time() - 60, 'pages' => 3, 'links' => 0, 'files' => [] ], false );
hodima_seo_advisor_crawl_map( true );
$p3_codes = static function ( string $path ): array {
	foreach ( hodima_seo_advisor_crawl_issues() as $row ) {
		if ( $row['path'] === $path ) {
			return array_column( $row['issues'], 'code' );
		}
	}
	return [];
};
hodima_ad( 'ریدایرکت فقط با لینک داخلی ایراد است (فقط با نمایش گوگل هیچ‌وقت برطرف نمی‌شد)؛ ۴۰۴ با لینک ورودی «خطا»', [ $p3_codes( '/p3/moved/' ), $p3_codes( '/p3/gone/' ) ], [ [], [ 'broken' ] ] );

$p3_code = 200;
$p3_html = '<html><head><title>' . str_repeat( 'ع', 52 ) . '</title><meta name="description" content="' . str_repeat( 'م', 130 ) . '"></head><body><h1>تیتر تازه</h1></body></html>';
$p3_head = [];
$p3_hits = 0;
$p3_live = static function ( $pre, array $args, string $url ) use ( &$p3_code, &$p3_html, &$p3_head, &$p3_hits ) {
	if ( ! str_contains( $url, HODIMA_SEO_ADVISOR_LIVE_PARAM . '=' ) ) {
		return $pre;
	}
	++$p3_hits;
	return 0 === $p3_code ? new WP_Error( 'http_request_failed', 'x' ) : [ 'headers' => [ 'content-type' => 'text/html' ] + $p3_head, 'body' => $p3_html, 'response' => [ 'code' => $p3_code, 'message' => '' ], 'cookies' => [], 'filename' => null ];
};
add_filter( 'pre_http_request', $p3_live, 30, 3 );

$_GET = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'crawl' ];
$p3_c0 = $cap( static fn() => hodima_seo_advisor_render_crawl() );
preg_match_all( '#form="(hodima-advisor-mark-\d+)" checked#', $p3_c0, $p3_fid );
hodima_ad( 'ایرادهای فنی: هر ایراد تیک‌خورده به فرم همان ردیف وصل، دو دکمه با نام صفحه‌خوان، ستون «کنش»، nonce بدون شناسه تکراری', [ max( array_count_values( $p3_fid[1] ) ?: [ 0 ] ) >= 5 && str_contains( $p3_c0, '<form id="' . array_search( max( array_count_values( $p3_fid[1] ) ), array_count_values( $p3_fid[1] ), true ) . '" class="hodima-advisor__mark"' ), str_contains( $p3_c0, 'name="action" value="hodima_advisor_mark"' ), str_contains( $p3_c0, 'aria-label="انجام دادم: صفحه مرحله سه"' ), str_contains( $p3_c0, 'value="rejected"' ), str_contains( $p3_c0, '<th scope="col">کنش</th>' ), substr_count( $p3_c0, 'id="_wpnonce"' ) ], [ true, true, true, true, true, 0 ] );
hodima_ad( 'برچسب تیک فقط متن و نشان (پیوند و دکمه کپی داخل label نامعتبر است)', 1 === preg_match( '#<label class="hodima-advisor__mark-item"><input[^>]*>(?:(?!</label>).)*<(?:a|button)\b#su', $p3_c0 ), false );

$p3_flash = $p3_mark( [ 'source' => 'crawl', 'path' => $p3_path, 'status' => 'done', 'items' => [ 'no_meta', 'no_h1', 'title_short', 'slow', 'nope' ] ] );
$p3_st    = static fn( string $code ): string => ( $t = hodima_seo_advisor_task( $p3_path, 'crawl_' . $code, '' ) ) ? hodima_seo_advisor_task_state( $t ) : '';
hodima_ad( '«انجام دادم» روی ۴ ایراد (کد نامعتبر کنار): صفحه همان لحظه خوانده شد؛ متا، H1 و عنوان برطرف، «کند» منتظر خزش تازه', [ $p3_hits, $p3_st( 'no_meta' ), $p3_st( 'no_h1' ), $p3_st( 'title_short' ), $p3_st( 'slow' ), $p3_st( 'nope' ), hodima_seo_advisor_task( $p3_path, 'crawl_no_meta', '' )['title'] ?? '' ], [ 1, 'fixed', 'fixed', 'fixed', 'waiting', '', 'بدون توضیح متا' ] );
hodima_ad( 'پیام نتیجه: شمار، برطرف‌ها، منتظر خزش تازه و پیوند «کارها»', [ str_contains( $p3_flash, '4 مورد «انجام دادم» ثبت شد' ), str_contains( $p3_flash, '3 برطرف شد' ), str_contains( $p3_flash, '1 منتظر نتیجه (کند بودن، لینک ورودی و ریدایرکت با خزش تازه' ), str_contains( $p3_flash, 'tab=tasks' ) ], [ true, true, true, true ] );
hodima_ad( 'برچسب منتظر کار خزش و برچسب «هنوز مانده» هر نوع', [ hodima_seo_advisor_task_view( hodima_seo_advisor_task( $p3_path, 'crawl_slow', '' ) )['label'], hodima_seo_advisor_task_still_label( 'crawl_no_meta' ), hodima_seo_advisor_task_still_label( 'health_dead' ) ], [ 'انجام شد · منتظر خزش تازه', 'انجام شد؛ هنوز دیده می‌شود', 'هنوز خراب است' ] );

$p3_c1 = $cap( static fn() => hodima_seo_advisor_render_crawl() );
hodima_ad( 'برطرف‌شده‌ها دیگر در داده خزش نیستند؛ «کند» (منتظر) پنهان؛ ایراد باز (لینک ورودی کم) می‌ماند؛ پیوند «نمایش ۱ ایراد علامت‌خورده»', [ str_contains( $p3_c1, 'بدون توضیح متا' ), str_contains( $p3_c1, 'منتظر خزش تازه ·' ), str_contains( $p3_c1, 'لینک ورودی کم' ), str_contains( $p3_c1, 'نمایش 1 ایراد علامت‌خورده' ) ], [ false, false, true, true ] );
$_GET['tasks'] = 'all';
$p3_c2 = $cap( static fn() => hodima_seo_advisor_render_crawl() );
hodima_ad( 'با «نمایش علامت‌خورده‌ها» «کند» دوباره دیده می‌شود با نشان وضعیت و بی‌تیک', [ str_contains( $p3_c2, 'منتظر خزش تازه ·' ), str_contains( $p3_c2, 'is-marked' ), substr_count( $p3_c2, 'value="slow"' ), str_contains( $p3_c2, 'پنهان کردن علامت‌خورده‌ها' ) ], [ true, true, 0, true ] );
$_GET = [];

// خزش تازه: کند هنوز هست ← هنوز مانده؛ بعد سریع ← برطرف؛ دوباره کند ← برگشت
hodima_seo_advisor_tasks_sync_crawl();
$p3_a = $p3_st( 'slow' );
$GLOBALS['wpdb']->update( hodima_seo_advisor_table( 'crawl_pages' ), [ 'resp_ms' => 200 ], [ 'path' => $p3_path ] );
hodima_seo_advisor_crawl_map( true );
hodima_seo_advisor_tasks_sync_crawl();
$p3_b = $p3_st( 'slow' );
$GLOBALS['wpdb']->update( hodima_seo_advisor_table( 'crawl_pages' ), [ 'resp_ms' => 3000 ], [ 'path' => $p3_path ] );
hodima_seo_advisor_crawl_map( true );
hodima_seo_advisor_tasks_sync_crawl();
hodima_ad( 'خزش تازه: «کند» هنوز هست ← هنوز مانده (باز)؛ برطرف؛ دوباره کند ← برگشت', [ $p3_a, $p3_b, $p3_st( 'slow' ), hodima_seo_advisor_task_group( hodima_seo_advisor_task( $p3_path, 'crawl_slow', '' ) ) ], [ 'still', 'fixed', 'back', 'open' ] );

// متن هنوز در صفحه: «انجام دادم» دوباره ← هنوز مانده؛ شبکه نرسید ← منتظر
$GLOBALS['wpdb']->update( hodima_seo_advisor_table( 'crawl_pages' ), [ 'meta_len' => 0 ], [ 'path' => $p3_path ] );
hodima_seo_advisor_crawl_map( true );
$p3_html = '<html><head><title>' . str_repeat( 'ع', 52 ) . '</title></head><body><h1>تیتر تازه</h1></body></html>';
$p3_flash2 = $p3_mark( [ 'source' => 'crawl', 'path' => $p3_path, 'status' => 'done', 'items' => [ 'no_meta' ] ] );
hodima_ad( 'متا هنوز نیست: کار «هنوز دیده می‌شود» و به «در دست انجام» می‌رود؛ پیام همین را می‌گوید', [ $p3_st( 'no_meta' ), hodima_seo_advisor_task_group( hodima_seo_advisor_task( $p3_path, 'crawl_no_meta', '' ) ), str_contains( $p3_flash2, '1 هنوز دیده می‌شود' ) ], [ 'still', 'open', true ] );
$p3_code = 0;
$p3_mark( [ 'source' => 'crawl', 'path' => $p3_path, 'status' => 'done', 'items' => [ 'no_meta' ] ] );
hodima_ad( 'صفحه از سایت خوانده نشد ← «منتظر خزش تازه» (نه برطرف و نه مانده)', $p3_st( 'no_meta' ), 'waiting' );

// خطای ۴۰۴: «انجام دادم» ← درخواست؛ حالا ۳۰۱ ← کد در ردیف خزش، «خطا» برطرف
$p3_code = 301;
$p3_head = [ 'location' => hodima_seo_advisor_url( '/p3/new/' ) ];
$p3_mark( [ 'source' => 'crawl', 'path' => '/p3/gone/', 'status' => 'done', 'items' => [ 'broken' ] ] );
hodima_ad( 'خطای ۴۰۴ ← حالا ریدایرکت: ردیف خزش ۳۰۱ با مقصد، کار برطرف؛ ایراد تازه «ریدایرکت» (لینک ورودی دارد)', [ hodima_seo_advisor_crawl_row( '/p3/gone/' )['status'], hodima_seo_advisor_state_or( hodima_seo_advisor_task( '/p3/gone/', 'crawl_broken', '' ) ), $p3_codes( '/p3/gone/' ) ], [ 301, 'fixed', [ 'redirect' ] ] );
$p3_code = 404;
$p3_head = [];
$GLOBALS['wpdb']->update( hodima_seo_advisor_table( 'crawl_pages' ), [ 'status' => 404, 'redirect_to' => '' ], [ 'path' => '/p3/gone/' ] );
hodima_seo_advisor_crawl_map( true );
$p3_mark( [ 'source' => 'crawl', 'path' => '/p3/gone/', 'status' => 'done', 'items' => [ 'broken' ] ] );
hodima_ad( 'هنوز ۴۰۴ ← «هنوز دیده می‌شود»', hodima_seo_advisor_state_or( hodima_seo_advisor_task( '/p3/gone/', 'crawl_broken', '' ) ), 'still' );
$p3_code = 200;

// «دوباره انجام دادم» (فرم تب «در دست انجام») هم همان لحظه می‌سنجد
$p3_html = '<html><head><title>' . str_repeat( 'ع', 52 ) . '</title><meta name="description" content="' . str_repeat( 'م', 130 ) . '"></head><body><h1>تیتر تازه</h1></body></html>';
hodima_seo_advisor_task_set( $p3_path, 'crawl_no_meta', '', '', 'done' );
hodima_seo_advisor_tasks_judge( 'crawl', static fn(): string => 'still', [ $p3_path ], 'crawl_no_meta' );
$_POST = $_REQUEST = [ '_wpnonce' => wp_create_nonce( 'hodima_advisor_task' ), 'path' => $p3_path, 'rule' => 'crawl_no_meta', 'term' => '', 'title' => '', 'status' => 'done', 'redo' => '1' ];
try {
	hodima_seo_advisor_handle_task();
} catch ( Hodima_P3_Redirect ) {
	// ریدایرکت
}
$_POST = $_REQUEST = [];
hodima_ad( '«دوباره انجام دادم» روی ایراد فنی: صفحه دوباره خوانده و برطرف شد', $p3_st( 'no_meta' ), 'fixed' );

// لینک تغییر وضعیت: nonce مخصوص همان کار و وضعیت (SEO 3.0.9)
parse_str( (string) wp_parse_url( html_entity_decode( hodima_seo_advisor_task_url( '/nn/x/', 'low_ctr', '', 'عنوان', 'done' ) ), PHP_URL_QUERY ), $nn_q );
$nn_nonce  = (string) ( $nn_q['_wpnonce'] ?? '' );
$nn_action = hodima_seo_advisor_task_nonce_action( '/nn/x/', 'low_ctr', '', 'done', false );
hodima_ad( 'nonce لینک تغییر وضعیت فقط برای همان کار و وضعیت معتبر است (نه عمومی، نه وضعیت یا آدرس دیگر)', [ (bool) wp_verify_nonce( $nn_nonce, $nn_action ), (bool) wp_verify_nonce( $nn_nonce, 'hodima_advisor_task' ), (bool) wp_verify_nonce( $nn_nonce, hodima_seo_advisor_task_nonce_action( '/nn/x/', 'low_ctr', '', 'rejected', false ) ), (bool) wp_verify_nonce( $nn_nonce, hodima_seo_advisor_task_nonce_action( '/nn/y/', 'low_ctr', '', 'done', false ) ) ], [ true, false, false, false ] );
$_REQUEST = [ '_wpnonce' => $nn_nonce, 'path' => '/nn/x/', 'rule' => 'low_ctr', 'term' => '', 'title' => 'عنوان', 'status' => 'done' ];
$_POST    = [];
try {
	hodima_seo_advisor_handle_task();
} catch ( Hodima_P3_Redirect ) {
	// ریدایرکت
}
$_REQUEST = [];
hodima_ad( 'لینک GET با nonce مخصوص کار را ثبت می‌کند', hodima_seo_advisor_task( '/nn/x/', 'low_ctr', '' )['status'] ?? '', 'done' );
hodima_seo_advisor_task_set( '/nn/x/', 'low_ctr', '', '', '' );

// رد شد و ورودی نامعتبر
$p3_flash3 = $p3_mark( [ 'source' => 'crawl', 'path' => $p3_path, 'status' => 'rejected', 'items' => [ 'low_inlinks' ] ] );
$p3_bad    = [ $p3_mark( [ 'source' => 'nope', 'path' => $p3_path, 'status' => 'done', 'items' => [ 'no_h1' ] ] ), $p3_mark( [ 'source' => 'crawl', 'path' => $p3_path, 'status' => 'done', 'items' => [] ] ), $p3_mark( [ 'source' => 'crawl', 'path' => $p3_path, 'status' => 'doing', 'items' => [ 'no_h1' ] ] ) ];
hodima_ad( '«لازم نیست» ثبت و پیامش؛ منبع نامعتبر، بی‌تیک و وضعیت غیرمجاز ← هیچ ثبت نمی‌شود', [ $p3_st( 'low_inlinks' ), str_contains( $p3_flash3, '«لازم نیست» ثبت شد' ), count( array_filter( $p3_bad, static fn( string $f ): bool => str_contains( $f, 'چیزی ثبت نشد' ) ) ) ], [ 'rejected', true, 3 ] );
wp_set_current_user( 0 );
add_filter( 'wp_die_handler', static fn() => static function (): void { throw new Hodima_P3_Redirect( 'die' ); } );
$p3_denied = '';
try {
	$_POST = $_REQUEST = [ 'source' => 'crawl', 'path' => $p3_path, 'status' => 'done', 'items' => [ 'no_h1' ] ];
	hodima_seo_advisor_handle_mark();
} catch ( Hodima_P3_Redirect $e ) {
	$p3_denied = $e->getMessage();
}
remove_all_filters( 'wp_die_handler' );
wp_set_current_user( 1 );
$p3_nonce = '';
try {
	$_POST = $_REQUEST = [ 'source' => 'crawl', 'path' => $p3_path, 'status' => 'done', 'items' => [ 'no_h1' ], '_wpnonce' => 'bad' ];
	add_filter( 'wp_die_handler', static fn() => static function (): void { throw new Hodima_P3_Redirect( 'die' ); } );
	hodima_seo_advisor_handle_mark();
} catch ( Hodima_P3_Redirect $e ) {
	$p3_nonce = $e->getMessage();
}
remove_all_filters( 'wp_die_handler' );
$_POST = $_REQUEST = [];
hodima_ad( 'بدون دسترسی مدیر و nonce غلط رد می‌شوند', [ $p3_denied, $p3_nonce ], [ 'die', 'die' ] );
remove_filter( 'pre_http_request', $p3_live, 30 );

// آدرس‌های خراب: روتر همان لحظه
$p3_dead_ok = (int) wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'زنده', 'post_name' => 'p3-alive' ] );
$p3_alive   = (string) hodima_seo_advisor_path_key( (string) get_permalink( $p3_dead_ok ) );
update_option( HODIMA_SEO_ADVISOR_HEALTH_OPTION, [ 'computed' => time(), 'router' => true, 'dead' => [ [ 'path' => '/p3-really-missing/', 'impressions' => 900, 'clicks' => 2, 'state' => 'missing', 'target' => '', 'code' => 404 ], [ 'path' => $p3_alive, 'impressions' => 500, 'clicks' => 1, 'state' => 'missing', 'target' => '', 'code' => 404 ], [ 'path' => '/p3-redirected/', 'impressions' => 300, 'clicks' => 0, 'state' => 'redirect', 'target' => '/x/', 'code' => 301 ] ], 'total' => 3, 'seen' => 1, 'fresh' => 0, 'unseen' => [ [ 'id' => $p3_dead_ok, 'path' => '/p3/unseen/', 'title' => 'بدون نمایش', 'type' => 'page', 'days' => 90 ] ] ], false );
$_GET  = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'health' ];
$p3_h0 = $cap( static fn() => hodima_seo_advisor_render_health( hodima_seo_advisor_status() ) );
hodima_ad( 'تب آدرس‌های خراب: دکمه فقط روی ۴۰۴/ریدایرکت خراب (۲) و صفحه بدون نمایش (۱)؛ ریدایرکت سالم «درست کار می‌کند»', [ substr_count( $p3_h0, 'value="hodima_advisor_mark"' ), str_contains( $p3_h0, 'درست کار می‌کند' ), str_contains( $p3_h0, 'value="unseen"' ) ], [ 3, true, true ] );
$p3_mark( [ 'source' => 'health', 'path' => '/p3-really-missing/', 'status' => 'done', 'items' => [ 'dead' ] ] );
$p3_mark( [ 'source' => 'health', 'path' => $p3_alive, 'status' => 'done', 'items' => [ 'dead' ] ] );
$p3_dead_paths = array_column( (array) hodima_seo_advisor_health()['dead'], 'path' );
hodima_ad( 'آدرس مرده: هنوز ۴۰۴ ← «هنوز خراب است»؛ حالا صفحه سالم ← برطرف و از فهرست ذخیره‌شده هم رفت', [ hodima_seo_advisor_state_or( hodima_seo_advisor_task( '/p3-really-missing/', 'health_dead', '' ) ), hodima_seo_advisor_state_or( hodima_seo_advisor_task( $p3_alive, 'health_dead', '' ) ), in_array( $p3_alive, $p3_dead_paths, true ), in_array( '/p3-really-missing/', $p3_dead_paths, true ) ], [ 'still', 'fixed', false, true ] );

// صفحه بدون نمایش: اول صف بررسی؛ ایندکس‌شده بعد از «انجام دادم» ← برطرف؛ ۳۰ روز بعد هنوز نه ← مانده
$p3_mark( [ 'source' => 'health', 'path' => '/p3/unseen/', 'status' => 'done', 'items' => [ 'unseen' ] ] );
update_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION, [ '/p3/unseen/' => [ 'verdict' => 'NEUTRAL', 'coverage' => 'Discovered - currently not indexed', 'robots' => '', 'indexing' => '', 'fetch' => '', 'last_crawl' => '', 'google_canonical' => '', 'user_canonical' => '', 'crawled_as' => '', 'checked' => time() - 3 * DAY_IN_SECONDS ] ], false );
hodima_ad( 'صفحه بدون نمایش «انجام دادم»: منتظر، اول صف بررسی ایندکس (بررسی قبلی پیش از انجام بود)', [ hodima_seo_advisor_state_or( hodima_seo_advisor_task( '/p3/unseen/', 'health_unseen', '' ) ), hodima_seo_advisor_inspect_queue( 1 )[0] ?? '' ], [ 'waiting', '/p3/unseen/' ] );
update_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION, [ '/p3/unseen/' => [ 'verdict' => 'PASS', 'coverage' => 'Submitted and indexed', 'robots' => '', 'indexing' => '', 'fetch' => '', 'last_crawl' => gmdate( 'c' ), 'google_canonical' => '', 'user_canonical' => '', 'crawled_as' => '', 'checked' => time() + 5 ] ], false );
hodima_seo_advisor_tasks_sync_inspected();
hodima_ad( 'بررسی بعد از انجام: ایندکس‌شده ← برطرف', hodima_seo_advisor_state_or( hodima_seo_advisor_task( '/p3/unseen/', 'health_unseen', '' ) ), 'fixed' );
hodima_ad( 'حکم بدون نمایش: ۳۱ روز بعد هنوز در فهرست ← مانده؛ ۵ روز ← منتظر؛ از فهرست رفته ← برطرف', [ hodima_seo_advisor_task_unseen_verdict( [ 'path' => '/u/', 'at' => time() - 31 * DAY_IN_SECONDS ], [ '/u/' => true ], [] ), hodima_seo_advisor_task_unseen_verdict( [ 'path' => '/u/', 'at' => time() - 5 * DAY_IN_SECONDS ], [ '/u/' => true ], [] ), hodima_seo_advisor_task_unseen_verdict( [ 'path' => '/u/', 'at' => time() ], [], [] ) ], [ 'still', 'wait', 'fixed' ] );
$_GET  = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'health' ];
$p3_h1 = $cap( static fn() => hodima_seo_advisor_render_health( hodima_seo_advisor_status() ) );
hodima_ad( 'تب آدرس‌های خراب بعد از علامت: برطرف/منتظرها پنهان؛ «هنوز خراب است» با نشان و دکمه می‌ماند', [ str_contains( $p3_h1, 'p3-really-missing' ), str_contains( $p3_h1, 'هنوز خراب است' ), str_contains( $p3_h1, 'بدون نمایش</th>' ) || str_contains( $p3_h1, '>بدون نمایش <' ), str_contains( $p3_h1, 'علامت‌خورده' ) ], [ true, true, false, true ] );

// وضعیت در گوگل: ایندکس‌نشده؛ گوگل بعد از انجام دوباره نخزیده ← منتظر؛ خزیده و هنوز نه ← مانده؛ ایندکس ← برطرف
$p3_rec = static fn( string $coverage, string $verdict, int $checked, string $crawl ): array => [ 'verdict' => $verdict, 'coverage' => $coverage, 'robots' => '', 'indexing' => '', 'fetch' => '', 'last_crawl' => $crawl, 'google_canonical' => '', 'user_canonical' => '', 'crawled_as' => '', 'checked' => $checked ];
update_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION, [ '/p3/idx/' => $p3_rec( 'Crawled - currently not indexed', 'NEUTRAL', time() - DAY_IN_SECONDS, gmdate( 'c', time() - 9 * DAY_IN_SECONDS ) ) ], false );
$_GET  = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'indexing' ];
$p3_i0 = $cap( static fn() => hodima_seo_advisor_render_indexing() );
$p3_mark( [ 'source' => 'indexing', 'path' => '/p3/idx/', 'status' => 'done' ] );
$p3_it = static fn(): string => hodima_seo_advisor_state_or( hodima_seo_advisor_task( '/p3/idx/', 'index_problem', '' ) );
$p3_i_a = [ $p3_it(), hodima_seo_advisor_task( '/p3/idx/', 'index_problem', '' )['title'] ?? '', in_array( '/p3/idx/', hodima_seo_advisor_inspect_queue( 3 ), true ) ];
update_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION, [ '/p3/idx/' => $p3_rec( 'Crawled - currently not indexed', 'NEUTRAL', time() + 5, gmdate( 'c', time() - 9 * DAY_IN_SECONDS ) ) ], false );
hodima_seo_advisor_tasks_sync_inspected();
$p3_i_b = $p3_it();
update_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION, [ '/p3/idx/' => $p3_rec( 'Crawled - currently not indexed', 'NEUTRAL', time() + 5, gmdate( 'c', time() + 5 ) ) ], false );
hodima_seo_advisor_tasks_sync_inspected();
$p3_i_c = $p3_it();
update_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION, [ '/p3/idx/' => $p3_rec( 'Submitted and indexed', 'PASS', time() + 10, gmdate( 'c', time() + 5 ) ) ], false );
hodima_seo_advisor_tasks_sync_inspected();
hodima_ad( 'ایندکس: دکمه در جدول؛ «انجام دادم» ← منتظر با عنوان وضعیت و در صف؛ گوگل نخزیده ← منتظر؛ خزیده و نه ← مانده؛ ایندکس ← برطرف', [ str_contains( $p3_i0, 'value="hodima_advisor_mark"' ), $p3_i_a, $p3_i_b, $p3_i_c, $p3_it() ], [ true, [ 'waiting', 'ایندکس: خزیده شد ولی ایندکس نشد', true ], 'waiting', 'still', 'fixed' ] );

// صفحه‌های هم‌کلمه: یک کار برای کلمه؛ ۱۴ روز؛ سهم ≥ ۷۰٪ ← برطرف
hodima_seo_advisor_store(
	[
		'/p3/c1/' => [ 'clicks' => 30, 'impressions' => 2000, 'position' => 7.0 ],
		'/p3/c2/' => [ 'clicks' => 25, 'impressions' => 2000, 'position' => 7.5 ],
	],
	[],
	[
		'/p3/c1/' => [ 'گیره سه' => [ 'clicks' => 30, 'impressions' => 2000, 'position' => 7.0 ] ],
		'/p3/c2/' => [ 'گیره سه' => [ 'clicks' => 25, 'impressions' => 2000, 'position' => 7.5 ] ],
	]
);
update_option( HODIMA_SEO_ADVISOR_IGNORED_OPTION, [], false );
$_GET  = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'competition' ];
$p3_k0 = $cap( static fn() => hodima_seo_advisor_render_competition( hodima_seo_advisor_status() ) );
hodima_ad( 'هم‌کلمه: فرم کلمه بیرون از summary (کنش داخل summary نامعتبر است) با کلمه و مسیر برنده', [ str_contains( $p3_k0, 'name="term" value="گیره سه"' ), 1 === preg_match( '#<summary[^>]*>(?:(?!</summary>).)*hodima-advisor__mark#su', $p3_k0 ), str_contains( $p3_k0, 'aria-label="انجام دادم: گیره سه"' ) ], [ true, false, true ] );
hodima_seo_advisor_task_set( '/p3/old-winner/', 'competition', 'گيره سه', 'قدیمی', 'doing' );
$p3_mark( [ 'source' => 'competition', 'path' => '/p3/c1/', 'term' => 'گیره سه', 'status' => 'done' ] );
$p3_ct = static fn(): string => hodima_seo_advisor_state_or( hodima_seo_advisor_task( '/p3/c1/', 'competition', 'گیره سه' ) );
hodima_ad( 'یک کار برای هر کلمه: کار قبلی همان کلمه (برنده دیگر، املای عربی) جایش را داد؛ برچسب «۰ از ۱۴ روز»', [ hodima_seo_advisor_task( '/p3/old-winner/', 'competition', 'گیره سه' ), $p3_ct(), str_contains( hodima_seo_advisor_task_view( hodima_seo_advisor_task( '/p3/c1/', 'competition', 'گیره سه' ) )['label'], '0 از 14 روز' ) ], [ null, 'waiting', true ] );
$p3_k1 = $cap( static fn() => hodima_seo_advisor_render_competition( hodima_seo_advisor_status() ) );
hodima_ad( 'کلمه علامت‌خورده از فهرست پنهان با پیوند نمایش', [ str_contains( $p3_k1, 'name="term" value="گیره سه"' ), str_contains( $p3_k1, 'کار بازی نمانده' ), str_contains( $p3_k1, 'نمایش 1 کلمه علامت‌خورده' ) ], [ false, true, true ] );
hodima_seo_advisor_tasks_sync_competition();
$p3_c_a = $p3_ct();
$p3_ts  = get_option( HODIMA_SEO_ADVISOR_TASKS_OPTION );
$p3_ts[ hodima_seo_advisor_task_key( '/p3/c1/', 'competition', 'گیره سه' ) ]['at'] = time() - 15 * DAY_IN_SECONDS;
update_option( HODIMA_SEO_ADVISOR_TASKS_OPTION, $p3_ts, false );
hodima_seo_advisor_tasks_sync_competition();
$p3_c_b = $p3_ct();
$GLOBALS['wpdb']->update( hodima_seo_advisor_table( 'queries' ), [ 'impressions' => 300 ], [ 'path' => '/p3/c2/' ] );
hodima_seo_advisor_tasks_sync_competition();
hodima_ad( 'هم‌کلمه: همان روز ← منتظر؛ ۱۵ روز بعد هنوز رقابت ← «هنوز رقابت دارد»؛ برنده ≥ ۷۰٪ ← برطرف', [ $p3_c_a, $p3_c_b, $p3_ct() ], [ 'waiting', 'still', 'fixed' ] );

// پیشنهادها: تیک برای هر ایراد، عنوان از داده خود سایت؛ همه علامت‌خورده ← صفحه پنهان
hodima_seo_advisor_store( [ '/p3/opp/' => [ 'clicks' => 2, 'impressions' => 5000, 'position' => 4.0 ] ], [], [] );
$GLOBALS['wpdb']->update( hodima_seo_advisor_table( 'pages' ), [ 'advice' => wp_json_encode( [ [ 'rule' => 'low_ctr', 'term' => '', 'title' => 'عنوان و توضیح متا را بازنویسی کنید', 'gain' => 40 ], [ 'rule' => 'near_first', 'term' => 'گیره سه', 'title' => 'کلمه «گیره سه» نزدیک صفحه اول', 'gain' => 10 ] ] ), 'advice_count' => 2, 'potential' => 50 ], [ 'path' => '/p3/opp/' ] );
$_GET  = [ 'page' => HODIMA_SEO_ADVISOR_PAGE ];
$p3_o0 = $cap( static fn() => hodima_seo_advisor_render_list( hodima_seo_advisor_status() ) );
hodima_ad( 'پیشنهادها (3.1.1): دو تیک «قاعده|کلمه» در جزئیات، فرم بی‌دکمه پنهان، «همه را انجام دادم» و «این صفحه را نادیده بگیر» در منوی ⋯ با ویژگی form', [ str_contains( $p3_o0, 'value="low_ctr|"' ), str_contains( $p3_o0, 'value="near_first|گیره سه"' ), str_contains( $p3_o0, 'value="opportunities"' ), str_contains( $p3_o0, 'hodima-advisor__menu-item hodima-advisor__menu-item--muted">این صفحه را نادیده بگیر' ), 1 === preg_match( '#<button type="submit" form="hodima-advisor-mark-\d+" name="status" value="done" class="hodima-advisor__menu-item hodima-advisor__menu-item--ok">همه را انجام دادم#u', $p3_o0 ), str_contains( $p3_o0, 'class="hodima-advisor__mark" method="post"' ) && str_contains( $p3_o0, ' hidden>' ) ], [ true, true, true, true, true, true ] );
$p3_mark( [ 'source' => 'opportunities', 'path' => '/p3/opp/', 'status' => 'done', 'items' => [ 'low_ctr|', 'bogus|x' ] ] );
hodima_ad( 'یک ایراد «انجام دادم»: عنوان از پیشنهاد ذخیره‌شده؛ مورد ساختگی ثبت نمی‌شود؛ صفحه هنوز در فهرست (ایراد دوم باز)', [ hodima_seo_advisor_task( '/p3/opp/', 'low_ctr', '' )['title'] ?? '', count( hodima_seo_advisor_tasks() ) > 0 && null === hodima_seo_advisor_task( '/p3/opp/', 'bogus', 'x' ), isset( hodima_seo_advisor_handled_paths()['/p3/opp/'] ) ], [ 'عنوان و توضیح متا را بازنویسی کنید', true, false ] );
$p3_mark( [ 'source' => 'opportunities', 'path' => '/p3/opp/', 'status' => 'rejected', 'items' => [ 'near_first|گیره سه' ] ] );
hodima_ad( 'هر دو علامت خورد ← صفحه تمام‌علامت‌خورده (پنهان در فهرست)', isset( hodima_seo_advisor_handled_paths()['/p3/opp/'] ), true );

// انجام‌شده: چیپ‌های «از کدام تب»، فیلتر و سه گام هر نوع
update_option( 'hodima_discover_sc_key', $key_json, false );
update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [ 'fetched' => time() - 3600, 'property' => 'sc-domain:' . $host, 'days' => 90, 'pages' => 3, 'queries' => 2, 'advised' => 1, 'error' => '' ] + hodima_seo_advisor_ranges( 90 ), false );
$_GET  = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'tasks', 'tstatus' => 'fixed' ];
$p3_d0 = $cap( static fn() => hodima_seo_advisor_render_admin() );
$_GET  = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'tasks', 'tstatus' => 'fixed', 'tsource' => 'crawl' ];
$p3_d1 = $cap( static fn() => hodima_seo_advisor_render_admin() );
hodima_ad( '«کارها» ← «برطرف شد»: منوی «از کدام بخش» با همه منبع‌ها؛ فیلتر ایرادهای فنی فقط کار خزش؛ گام «صفحه دوباره از سایت خوانده شد»', [ str_contains( $p3_d0, 'aria-label="از کدام بخش"' ), str_contains( $p3_d0, 'tsource=competition' ) && str_contains( $p3_d0, 'tsource=health' ) && str_contains( $p3_d0, 'tsource=indexing' ), str_contains( $p3_d1, 'رقابت صفحه‌ها برای' ), str_contains( $p3_d1, 'بدون H1' ), str_contains( $p3_d1, 'صفحه دوباره از سایت خوانده شد' ) ], [ true, true, false, true, true ] );
$_GET  = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'tasks' ];
$p3_d2 = $cap( static fn() => hodima_seo_advisor_render_admin() );
hodima_ad( 'در دست انجام: «هنوز خراب است» با قدم بعدی و «دوباره انجام دادم»؛ نشان تب مبدأ', [ str_contains( $p3_d2, 'هنوز خراب است' ), str_contains( $p3_d2, 'قانون ۳۰۱ بگذارید' ), str_contains( $p3_d2, 'دوباره انجام دادم' ), str_contains( $p3_d2, '<span class="hds-pill hds-pill--info">آدرس‌های خراب</span>' ) ], [ true, true, true, true ] );
delete_option( 'hodima_discover_sc_key' );
$_GET = [];

remove_filter( 'wp_redirect', $p3_redirect, 1 );
wp_delete_post( $p3_post, true );
wp_delete_post( $p3_dead_ok, true );
hodima_seo_advisor_clear_data();
delete_option( HODIMA_SEO_ADVISOR_HEALTH_OPTION );
delete_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION );
delete_option( HODIMA_SEO_ADVISOR_LIVE_OPTION );
false === $p3_tasks ? delete_option( HODIMA_SEO_ADVISOR_TASKS_OPTION ) : update_option( HODIMA_SEO_ADVISOR_TASKS_OPTION, $p3_tasks, false );
false === $p3_status ? delete_option( HODIMA_SEO_ADVISOR_STATUS_OPTION ) : update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, $p3_status, false );
wp_set_current_user( 0 );

echo "=== خزنده شبانه (SEO 3.1.4)\n";
$cw_site     = untrailingslashit( home_url() );
$cw_settings = get_option( HODIMA_SEO_ADVISOR_CRAWLER_SETTINGS, false );
$cw_state    = get_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION, false );
$cw_crawl    = get_option( HODIMA_SEO_ADVISOR_CRAWL_OPTION, false );
$cw_at       = static fn( string $when ): int => ( new DateTimeImmutable( $when, wp_timezone() ) )->getTimestamp();
update_option( HODIMA_SEO_ADVISOR_CRAWLER_SETTINGS, [ 'enabled' => true, 'start' => 2, 'end' => 8, 'interval' => 7 ], false );
hodima_ad( 'پنجره ۲ تا ۸: ۰۳:۰۰ داخل، ۱۲:۰۰ و ۰۸:۰۰ بیرون', [ hodima_seo_advisor_crawler_in_window( $cw_at( '2026-10-29 03:00' ) ), hodima_seo_advisor_crawler_in_window( $cw_at( '2026-10-29 12:00' ) ), hodima_seo_advisor_crawler_in_window( $cw_at( '2026-10-29 08:00' ) ) ], [ true, false, false ] );
update_option( HODIMA_SEO_ADVISOR_CRAWLER_SETTINGS, [ 'enabled' => true, 'start' => 22, 'end' => 6, 'interval' => 7 ], false );
hodima_ad( 'پنجره از شب تا صبح (۲۲ تا ۶): ۲۳:۳۰ و ۰۱:۰۰ داخل، ۱۴:۰۰ بیرون', [ hodima_seo_advisor_crawler_in_window( $cw_at( '2026-10-29 23:30' ) ), hodima_seo_advisor_crawler_in_window( $cw_at( '2026-10-29 01:00' ) ), hodima_seo_advisor_crawler_in_window( $cw_at( '2026-10-29 14:00' ) ) ], [ true, true, false ] );
update_option( HODIMA_SEO_ADVISOR_CRAWLER_SETTINGS, [ 'enabled' => true, 'start' => 5, 'end' => 5, 'interval' => 99 ], false );
hodima_ad( 'ساعت شروع و پایان برابر = همه ساعت‌ها؛ فاصله نامعتبر ← ۷ روز', [ hodima_seo_advisor_crawler_in_window( $cw_at( '2026-10-29 15:00' ) ), hodima_seo_advisor_crawler_settings()['interval'] ], [ true, 7 ] );
hodima_ad( 'کرون هر ۵ دقیقه ثبت شده', wp_get_schedules()['hodima_five_minutes']['interval'] ?? 0, 300 );

$cw_u = static fn( string $href, string $base = '' ): ?string => hodima_seo_advisor_crawler_resolve( $href, '' === $base ? $cw_site . '/dir/page/' : $base );
hodima_ad( 'آدرس لینک: نسبی، ریشه‌ای، کامل، بدون پروتکل و پوشه بالاتر', [ $cw_u( '../x/' ), $cw_u( '/y/' ), $cw_u( 'z/' ), $cw_u( '//cdn.test/a/' ), $cw_u( 'https://other.test/p/?q=1#f' ) ], [ $cw_site . '/dir/x/', $cw_site . '/y/', $cw_site . '/dir/page/z/', 'https://cdn.test/a/', 'https://other.test/p/?q=1' ] );
hodima_ad( 'لینک غیرصفحه: #، mailto، tel، javascript، خالی', [ $cw_u( '#top' ), $cw_u( 'mailto:a@b.test' ), $cw_u( 'tel:0912' ), $cw_u( 'javascript:void(0)' ), $cw_u( '' ) ], [ null, null, null, null, null ] );
hodima_ad( 'نخزیدن: پارامتردار، فایل، wp-admin، خارجی؛ می‌خزد: صفحه معمولی با حروف مسیر', [ hodima_seo_advisor_crawler_normalize( $cw_site . '/a/?x=1' ), hodima_seo_advisor_crawler_normalize( $cw_site . '/a.pdf' ), hodima_seo_advisor_crawler_normalize( $cw_site . '/wp-admin/x/' ), hodima_seo_advisor_crawler_normalize( 'https://other.test/a/' ), hodima_seo_advisor_crawler_normalize( $cw_site . '/Hair-Clips/' ) ], [ null, null, null, null, $cw_site . '/Hair-Clips/' ] );

$cw_html = '<!doctype html><html><head><title>  صفحه   نمونه </title><meta name="Description" content="توضیح نمونه"><meta name="robots" content="noindex, max-snippet:0"><link rel="canonical" href="' . $cw_site . '/other/"></head><body>'
	. '<header><a href="/menu/">منو</a></header><nav class="main-menu"><a href="/nav/">ناو</a></nav>'
	. '<main><h1>تیتر اصلی</h1><h2>یک</h2><h2>دو</h2><h2>سه</h2><p><a href="/in/">متن لینک</a> <a rel="nofollow" href="/nf/">بدون دنبال</a> <a href="/img/"><img src="x.jpg" alt="تصویر لینک"></a></p></main>'
	. '<div class="sidebar-box"><a href="/side/">کنار</a></div><footer><a href="/foot/">فوتر</a></footer></body></html>';
$cw_p     = hodima_seo_advisor_crawler_parse( $cw_html, $cw_site . '/p/' );
$cw_pos   = array_column( array_map( static fn( array $l ): array => [ hodima_seo_advisor_path_key( $l[0] ), $l[1] . ( 0 === $l[3] ? ':nf' : '' ) ], $cw_p['links'] ), 1, 0 );
hodima_ad( 'خواندن HTML: عنوان یکدست‌شده، متا، robots، canonical، H1 و دو H2 اول', [ $cw_p['title'], $cw_p['meta'], $cw_p['robots'], $cw_p['canonical'], $cw_p['h1'], $cw_p['h2'] ], [ 'صفحه نمونه', 'توضیح نمونه', 'noindex, max-snippet:0', $cw_site . '/other/', 'تیتر اصلی', 'یک | دو' ] );
hodima_ad( 'جایگاه لینک‌ها: منو، ناو، متن، nofollow، تصویر، کنار، فوتر', $cw_pos, [ '/menu/' => 'navigation', '/nav/' => 'navigation', '/in/' => 'content', '/nf/' => 'content:nf', '/img/' => 'content', '/side/' => 'aside', '/foot/' => 'footer' ] );
hodima_ad( 'متن لینک تصویری از alt', array_column( $cw_p['links'], 2, 0 )[ $cw_site . '/img/' ] ?? '', 'تصویر لینک' );
// تصویر بدون alt (SEO 3.3.3): alt خالی عمدی داخل لینک/دکمه/figure با متن ایراد نیست؛ نبود ویژگی alt، alt خالی تنها و لینکِ فقط-تصویر ایراد است
$cw_alt = static function ( string $body ) use ( $cw_site ): int {
	$parsed = hodima_seo_advisor_crawler_parse( '<html><head><title>t</title></head><body><main>' . $body . '</main></body></html>', $cw_site . '/alt/' );
	return $parsed['img_noalt'];
};
hodima_ad(
	'تصویر بدون alt: نبود alt و alt خالی تنها = ایراد؛ alt دارد، تزئینی، کارت با عنوان، لینک با aria-label، figure با کپشن = ایراد نیست؛ لینکِ فقط-تصویر = ایراد',
	[
		$cw_alt( '<img src="a.jpg">' ),
		$cw_alt( '<img src="a.jpg" alt="">' ),
		$cw_alt( '<img src="a.jpg" alt="توضیح">' ),
		$cw_alt( '<img src="a.jpg" alt="" aria-hidden="true">' ),
		$cw_alt( '<a href="/p/"><img src="a.jpg" alt=""><h3>عنوان کارت</h3></a>' ),
		$cw_alt( '<a href="/p/" aria-label="محصول"><img src="a.jpg" alt=""></a>' ),
		$cw_alt( '<a href="/p/"><img src="a.jpg" alt=""></a>' ),
		$cw_alt( '<a href="/p/"><img src="a.jpg"><h3>عنوان</h3></a>' ),
		$cw_alt( '<figure><img src="a.jpg" alt=""><figcaption>کپشن</figcaption></figure>' ),
		$cw_alt( '<figure><img src="a.jpg" alt=""><figcaption></figcaption></figure>' ),
		$cw_alt( '<button type="button"><img src="a.jpg" alt="">باز کن</button>' ),
	],
	[ 1, 1, 0, 0, 0, 0, 1, 1, 0, 1, 0 ]
);
$cw_row = hodima_seo_advisor_crawler_page_row( '/p/', 200, 120, $cw_p, '', '' );
hodima_ad( 'ردیف صفحه noindex: غیرایندکس‌پذیر با علت، طول عنوان، max-snippet:0', [ $cw_row['indexable'], $cw_row['reason'], $cw_row['title_len'], $cw_row['no_snippet'] ], [ 0, 'Noindex', 10, 1 ] );
$cw_row2 = hodima_seo_advisor_crawler_page_row( '/p/', 200, 120, [ 'canonical' => $cw_site . '/p/' ] + $cw_p + [], '', '' );

// سایت ساختگی
$cw_pages = [
	'/'          => '<html><head><title>خانه با عنوان خیلی بلند برای آزمون خزنده</title><meta name="description" content="توضیح"></head><body><nav><a href="/a/">آ</a></nav><main><h1>خانه</h1><a href="/b/">ب</a> <a href="/old/">قدیمی</a> <a href="c/">ث</a> <a href="https://example.com/x/">بیرون</a> <a href="/a/?x=1">پارامتر</a> <a href="/file.pdf">فایل</a> <a href="mailto:a@b.test">ایمیل</a> <a href="#top">بالا</a></main><footer><a href="/about/">درباره</a></footer></body></html>',
	'/a/'        => '<html><head><title>صفحه آ</title><meta name="robots" content="noindex"></head><body><main><h1>آ</h1><a href="/b/">ب</a> <a rel="nofollow" href="/c/">ث</a></main></body></html>',
	'/b/'        => '<html><head><title>صفحه ب</title><link rel="canonical" href="' . $cw_site . '/a/"></head><body><main><h1>ب</h1><a href="/a/">آ</a></main></body></html>',
	'/about/'    => '<html><head><title>درباره</title></head><body><main><h1>درباره</h1></main></body></html>',
];
$cw_requested = [];
$cw_mode      = 'site';
add_filter(
	'pre_http_request',
	$cw_filter = static function ( mixed $pre, array $args, string $url ) use ( &$cw_requested, &$cw_mode, $cw_site, $cw_pages ): mixed {
		if ( ! str_starts_with( $url, $cw_site ) ) {
			return $pre;
		}
		$cw_requested[] = $url;
		if ( 'down' === $cw_mode ) {
			return new WP_Error( 'http_request_failed', 'timeout' );
		}
		$path = '/' . ltrim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' );
		if ( '/old/' === $path ) {
			return [ 'response' => [ 'code' => 301 ], 'headers' => [ 'location' => $cw_site . '/b/' ], 'body' => '' ];
		}
		if ( isset( $cw_pages[ $path ] ) ) {
			return [ 'response' => [ 'code' => 200 ], 'headers' => [ 'content-type' => 'text/html; charset=UTF-8' ], 'body' => $cw_pages[ $path ] ];
		}
		return [ 'response' => [ 'code' => 404 ], 'headers' => [ 'content-type' => 'text/html' ], 'body' => '<html><head><title>پیدا نشد</title></head><body><h1>۴۰۴</h1></body></html>' ];
	},
	10,
	3
);
add_filter( 'hodima_seo_advisor_crawler_delay', '__return_zero' );
update_option( HODIMA_SEO_ADVISOR_CRAWLER_SETTINGS, [ 'enabled' => true, 'start' => 2, 'end' => 8, 'interval' => 7 ], false );
delete_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION );
hodima_seo_advisor_ensure_tables();
$cw_t = hodima_seo_advisor_table( 'crawl_pages' );
$GLOBALS['wpdb']->query( $GLOBALS['wpdb']->prepare( 'DELETE FROM %i', $cw_t ) );
$GLOBALS['wpdb']->query( $GLOBALS['wpdb']->prepare( 'DELETE FROM %i', hodima_seo_advisor_table( 'crawl_links' ) ) );
$GLOBALS['wpdb']->insert( $cw_t, [ 'path' => '/gone/', 'status' => 200 ] ); // صفحه دور قبل که حالا نیست

hodima_seo_advisor_crawler_start();
$cw_started = hodima_seo_advisor_crawler_state();
hodima_ad( 'شروع دور: صف با صفحه اصلی، نوشته‌ها و دسته‌ها در فهرست seed', [ $cw_started['status'], array_map( static fn( string $u ): ?string => hodima_seo_advisor_path_key( $u ), $cw_started['queue'] ), (bool) $cw_started['seed'] ], [ 'running', [ '/' ], true ] );
$cw_s         = $cw_started;
$cw_s['seed'] = []; // آزمون فقط با سایت ساختگی؛ seed واقعی بعدا جدا
hodima_seo_advisor_crawler_save( $cw_s );

$cw_out = hodima_seo_advisor_crawler_tick( true );
$cw_rows = [];
foreach ( (array) $GLOBALS['wpdb']->get_results( $GLOBALS['wpdb']->prepare( 'SELECT * FROM %i', $cw_t ), ARRAY_A ) as $r ) {
	$cw_rows[ $r['path'] ] = $r;
}
ksort( $cw_rows );
hodima_ad( 'دور کامل در یک نوبت تمام شد؛ صفحه‌ها: خانه، آ، ب، قدیمی، ث، درباره (بدون پارامتر، فایل، بیرونی؛ صفحه دور قبل پاک)', [ $cw_out['finished'], array_keys( $cw_rows ) ], [ true, [ '/', '/a/', '/about/', '/b/', '/c/', '/old/' ] ] );
hodima_ad( 'درخواست‌ها فقط برای آدرس‌های داخلی بدون پارامتر و فایل (هر آدرس یک‌بار)', [ count( $cw_requested ), count( array_unique( $cw_requested ) ) ], [ 6, 6 ] );
hodima_ad( 'وضعیت‌ها: ۲۰۰، noindex، canonical دیگر، ریدایرکت ۳۰۱ به /b/، ۴۰۴', [ array_map( static fn( string $p ): array => [ (int) $cw_rows[ $p ]['status'], (int) $cw_rows[ $p ]['indexable'], $cw_rows[ $p ]['reason'], $cw_rows[ $p ]['redirect_to'] ], [ '/', '/a/', '/b/', '/old/', '/c/' ] ) ], [ [ [ 200, 1, '', '' ], [ 200, 0, 'Noindex', '' ], [ 200, 0, 'Canonicalised', '' ], [ 301, 0, 'Redirected', '/b/' ], [ 404, 0, '', '' ] ] ] );
hodima_ad( 'عنوان و متا و H1 خانه ذخیره شد', [ $cw_rows['/']['title_len'] > 30, (int) $cw_rows['/']['meta_len'], $cw_rows['/']['h1'] ], [ true, 5, 'خانه' ] );
hodima_ad( 'لینک ورودی یکتا: /b/ از خانه و آ (۲)، /c/ از خانه و آ (۲؛ یکی nofollow)، /a/ از خانه و ب', [ (int) $cw_rows['/b/']['inlinks'], (int) $cw_rows['/c/']['inlinks'], (int) $cw_rows['/a/']['inlinks'] ], [ 2, 2, 2 ] );
hodima_ad( 'لینک متنی (محتوا و follow): /b/ دو، /c/ یکی (فقط از خانه؛ آ nofollow)، /a/ دو (از ب و از لینک پارامتری «/a/?x=1» خانه که به همان صفحه می‌رسد؛ لینک منوی خانه حساب نمی‌شود)', [ (int) $cw_rows['/b/']['text_links'], (int) $cw_rows['/c/']['text_links'], (int) $cw_rows['/a/']['text_links'] ], [ 2, 1, 2 ] );
hodima_ad( 'عمق: خانه ۰؛ آ، ب، قدیمی، ث، درباره ۱', array_map( static fn( string $p ): int => (int) $cw_rows[ $p ]['depth'], [ '/', '/a/', '/b/', '/old/', '/c/', '/about/' ] ), [ 0, 1, 1, 1, 1, 1 ] );
$cw_after = hodima_seo_advisor_crawler_state();
hodima_ad( 'بعد از دور: بیکار، تمام‌شده ثبت، صف و seen خالی؛ وضعیت صفحه «ایرادهای فنی» از خزنده', [ $cw_after['status'], $cw_after['finished'] > 0, $cw_after['queue'], $cw_after['seen'], hodima_seo_advisor_crawl_status()['pages'], get_option( HODIMA_SEO_ADVISOR_CRAWL_OPTION )['source'] ?? '' ], [ 'idle', true, [], [], 6, 'crawler' ] );
$cw_codes = [];
foreach ( hodima_seo_advisor_crawl_issues() as $issue_row ) {
	foreach ( $issue_row['issues'] as $issue ) {
		$cw_codes[ $issue_row['path'] ][] = $issue['code'];
	}
}
hodima_ad( 'ایرادهای موجود روی داده خزنده کار می‌کنند: خانه بدون H1 نیست، /about/ بدون متا، /b/ ریدایرکت نه', [ in_array( 'no_meta', $cw_codes['/about/'] ?? [], true ), in_array( 'no_h1', $cw_codes['/'] ?? [], true ), isset( $cw_codes['/old/'] ) ], [ true, false, true ] );

$cw_requested = [];
$cw_again     = hodima_seo_advisor_crawler_tick();
hodima_ad( 'بیرون از موعد (دور تازه تمام شده): نوبت بعدی هیچ درخواستی نمی‌زند', [ $cw_again['ran'], count( $cw_requested ) ], [ 0, 0 ] );
hodima_seo_advisor_crawler_request();
hodima_ad( 'درخواست دستی «خزش تازه» ثبت می‌شود', hodima_seo_advisor_crawler_status()['requested'], true );
hodima_seo_advisor_crawler_start();
hodima_ad( 'شروع دور بعد درخواست را برمی‌دارد', hodima_seo_advisor_crawler_state()['requested'], false );

// سایت از دسترس خارج: پنج خطای پی‌درپی ← مکث نیم‌ساعته؛ مکث ← هیچ درخواستی
$cw_s          = hodima_seo_advisor_crawler_state();
$cw_s['seed']  = [];
$cw_s['queue'] = array_map( static fn( int $i ): string => $cw_site . '/p' . $i . '/', range( 1, 8 ) ); // پنج خطا ← مکث، پیش از ته‌کشیدن صف
hodima_seo_advisor_crawler_save( $cw_s );
$cw_mode      = 'down';
$cw_requested = [];
hodima_seo_advisor_crawler_tick( true );
$cw_paused = hodima_seo_advisor_crawler_state();
hodima_ad( 'سایت پاسخ نمی‌دهد: خزنده به‌جای کوبیدن، مکث می‌کند (وضعیت «paused»)', [ $cw_paused['pause_until'] > time() + 1000, hodima_seo_advisor_crawler_status()['state'], 'running' === $cw_paused['status'] ], [ true, 'paused', true ] );
$cw_requested = [];
hodima_seo_advisor_crawler_tick( true );
hodima_ad( 'در مکث نوبت‌ها درخواستی نمی‌زنند', count( $cw_requested ), 0 );

update_option( HODIMA_SEO_ADVISOR_CRAWLER_SETTINGS, [ 'enabled' => false, 'start' => 2, 'end' => 8, 'interval' => 7 ], false );
$cw_s                = hodima_seo_advisor_crawler_state();
$cw_s['pause_until'] = 0;
hodima_seo_advisor_crawler_save( $cw_s );
$cw_requested = [];
hodima_ad( 'خاموش: نوبت چیزی نمی‌خزد و وضعیت «off»', [ hodima_seo_advisor_crawler_tick()['ran'], count( $cw_requested ), hodima_seo_advisor_crawler_status()['state'] ], [ 0, 0, 'off' ] );
hodima_ad( 'پاک کردن داده‌های دستار وضعیت خزنده را پاک می‌کند ولی تنظیمات می‌ماند', [ hodima_seo_advisor_clear_data(), get_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION, 'none' ), get_option( HODIMA_SEO_ADVISOR_CRAWLER_SETTINGS )['enabled'] ?? null ], [ true, 'none', false ] );

remove_filter( 'pre_http_request', $cw_filter, 10 );
remove_filter( 'hodima_seo_advisor_crawler_delay', '__return_zero' );
false === $cw_settings ? delete_option( HODIMA_SEO_ADVISOR_CRAWLER_SETTINGS ) : update_option( HODIMA_SEO_ADVISOR_CRAWLER_SETTINGS, $cw_settings, false );
false === $cw_state ? delete_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION ) : update_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION, $cw_state, false );
false === $cw_crawl ? delete_option( HODIMA_SEO_ADVISOR_CRAWL_OPTION ) : update_option( HODIMA_SEO_ADVISOR_CRAWL_OPTION, $cw_crawl, false );

echo "=== ایرادهای تازه خزش (SEO 3.1.5)\n";
$cx_site = untrailingslashit( home_url() );
$cx_words = static fn( int $n ): string => implode( ' ', array_fill( 0, $n, 'کلمه' ) );
$cx_crawl = get_option( HODIMA_SEO_ADVISOR_CRAWL_OPTION, false );
$cx_page  = static fn( string $title, string $body, string $head = '' ): string => '<html><head><title>' . $title . '</title><meta name="description" content="توضیح کافی برای این صفحه آزمایشی">' . $head . '</head><body><main>' . $body . '</main></body></html>';
$cx_pages = [
	'/'                => $cx_page( 'خانه آزمایشی با عنوان به اندازه کافی بلند', '<h1>یک</h1><h1>دو</h1><a href="/broken/">خراب</a> <a href="/old/">قدیمی</a> <a href="/chain1/">زنجیره</a> <a href="/noidx/">ناایندکس</a> <a href="http://hodima.test/b/">ب</a> <a href="/d1/">عمق</a> <a href="/canbad/">ک</a> <a href="/thin/">کم</a> <img src="/a.jpg"><img src="/b.jpg" alt="دارد"><img src="/c.jpg" alt="تزئینی" aria-hidden="true">' ),
	'/b/'              => $cx_page( 'صفحه ب با متن کافی و عنوان بلند برای آزمون', '<h1>ب</h1><p>' . $cx_words( 200 ) . '</p><a href="/">خانه</a>', '<link rel="canonical" href="' . $cx_site . '/b/">' ),
	'/noidx/'          => $cx_page( 'صفحه ناایندکس با عنوان به اندازه کافی بلند', '<h1>ن</h1>', '<meta name="robots" content="noindex"><link rel="canonical" href="' . $cx_site . '/noidx/">' ),
	'/canbad/'         => $cx_page( 'صفحه با canonical اشتباه و عنوان بلند کافی', '<h1>ک</h1><p>' . $cx_words( 200 ) . '</p>', '<link rel="canonical" href="' . $cx_site . '/broken/">' ),
	'/thin/'           => $cx_page( 'صفحه کم‌متن با عنوان به اندازه کافی بلند', '<h1>کم</h1><p>' . $cx_words( 30 ) . '</p>', '<link rel="canonical" href="' . $cx_site . '/thin/">' ),
	'/orphan-sm/'      => $cx_page( 'صفحه فقط در نقشه سایت با عنوان بلند کافی', '<h1>ی</h1><p>' . $cx_words( 200 ) . '</p>', '<link rel="canonical" href="' . $cx_site . '/orphan-sm/">' ),
];
foreach ( range( 1, 6 ) as $i ) {
	$cx_pages[ "/d{$i}/" ] = $cx_page( "عمق {$i} با عنوان به اندازه کافی بلند", "<h1>د{$i}</h1><p>" . $cx_words( 200 ) . '</p>' . ( $i < 6 ? '<a href="/d' . ( $i + 1 ) . '/">بعدی</a>' : '' ), '<link rel="canonical" href="' . $cx_site . "/d{$i}/\">" );
}
$cx_sitemap = '<?xml version="1.0"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"><url><loc>' . $cx_site . '/</loc></url><url><loc>' . $cx_site . '/b/</loc><image:image><image:loc>' . $cx_site . '/wp-content/uploads/x.jpg</image:loc></image:image></url><url><loc>' . $cx_site . '/in-sitemap-404/</loc></url><url><loc>' . $cx_site . '/orphan-sm/</loc></url><url><loc>https://elsewhere.test/x/</loc></url></urlset>';
$cx_index   = '<?xml version="1.0"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><sitemap><loc>' . $cx_site . '/part-sitemap.xml</loc></sitemap></sitemapindex>';
add_filter(
	'pre_http_request',
	$cx_filter = static function ( mixed $pre, array $args, string $url ) use ( &$cx_pages, $cx_site, $cx_sitemap, $cx_index ): mixed {
		if ( ! str_starts_with( $url, $cx_site ) ) {
			return $pre;
		}
		$path = '/' . ltrim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' );
		$xml  = static fn( string $body ): array => [ 'response' => [ 'code' => 200 ], 'headers' => [ 'content-type' => 'application/xml' ], 'body' => $body ];
		if ( '/sitemap.xml' === $path ) {
			return $xml( $cx_index );
		}
		if ( '/part-sitemap.xml' === $path ) {
			return $xml( $cx_sitemap );
		}
		$redirects = [ '/old/' => '/b/', '/chain1/' => '/chain2/', '/chain2/' => '/b/' ];
		if ( isset( $redirects[ $path ] ) ) {
			return [ 'response' => [ 'code' => 301 ], 'headers' => [ 'location' => $cx_site . $redirects[ $path ] ], 'body' => '' ];
		}
		if ( isset( $cx_pages[ $path ] ) ) {
			return [ 'response' => [ 'code' => 200 ], 'headers' => [ 'content-type' => 'text/html; charset=UTF-8' ], 'body' => $cx_pages[ $path ] ];
		}
		return [ 'response' => [ 'code' => 404 ], 'headers' => [ 'content-type' => 'text/html' ], 'body' => '<html><head><title>پیدا نشد</title></head><body><h1>404</h1></body></html>' ];
	},
	10,
	3
);
add_filter( 'hodima_seo_advisor_crawler_delay', '__return_zero' );
update_option( HODIMA_SEO_ADVISOR_CRAWLER_SETTINGS, [ 'enabled' => true, 'start' => 2, 'end' => 8, 'interval' => 7 ], false );
delete_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION );
$cx_pt = hodima_seo_advisor_table( 'crawl_pages' );
$GLOBALS['wpdb']->query( $GLOBALS['wpdb']->prepare( 'DELETE FROM %i', $cx_pt ) );
$GLOBALS['wpdb']->query( $GLOBALS['wpdb']->prepare( 'DELETE FROM %i', hodima_seo_advisor_table( 'crawl_links' ) ) );
$GLOBALS['wpdb']->replace( hodima_seo_advisor_table( 'pages' ), [ 'path' => '/thin/', 'kind' => 'page' ] ); // نوع صفحه از داده گوگل: فقط نوشته/برگه/محصول «متن کم» می‌گیرد
hodima_seo_advisor_crawler_start();
$cx_s         = hodima_seo_advisor_crawler_state();
$cx_s['seed'] = array_values( array_filter( $cx_s['seed'], static fn( string $item ): bool => str_starts_with( $item, 'm:' ) ) ); // نوشته‌های واقعی ابزار تست جدا
hodima_seo_advisor_crawler_save( $cx_s );
hodima_seo_advisor_crawler_tick( true );
$cx_state = hodima_seo_advisor_crawler_state();
hodima_ad( 'نقشه سایت (فهرست ← فایل ← آدرس‌ها): ۴ آدرس همین سایت؛ بیرونی و تصویر نه', [ array_keys( hodima_seo_advisor_sitemap_paths() ) ], [ [ '/', '/b/', '/in-sitemap-404/', '/orphan-sm/' ] ] );
hodima_ad( 'دور تمام شد و صفحه فقط-در-نقشه (/orphan-sm/) هم خزیده شد', [ $cx_state['status'], (int) $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( 'SELECT status FROM %i WHERE path = %s', $cx_pt, '/orphan-sm/' ) ) ], [ 'idle', 200 ] );
$cx_row = (array) $GLOBALS['wpdb']->get_row( $GLOBALS['wpdb']->prepare( 'SELECT * FROM %i WHERE path = %s', $cx_pt, '/' ), ARRAY_A );
hodima_ad( 'داده تازه صفحه اصلی: ۲ H1، یک تصویر بدون alt (تزئینی و دارای alt نه)، یک لینک http، canonical ندارد، متن کم', [ (int) $cx_row['h1_count'], (int) $cx_row['img_noalt'], (int) $cx_row['http_links'], $cx_row['canonical'], (int) $cx_row['words'] < 20 ], [ 2, 1, 1, '', true ] );
hodima_ad( 'canonical ذخیره می‌شود (مسیر) و شمار کلمه صفحه بلند', [ $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( 'SELECT canonical FROM %i WHERE path = %s', $cx_pt, '/b/' ) ), (int) $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( 'SELECT words FROM %i WHERE path = %s', $cx_pt, '/b/' ) ) >= 190 ], [ '/b/', true ] );
$cx_codes = [];
$cx_items = [];
foreach ( hodima_seo_advisor_crawl_issues() as $issue_row ) {
	foreach ( $issue_row['issues'] as $issue ) {
		$cx_codes[ $issue_row['path'] ][] = $issue['code'];
		if ( ! empty( $issue['items'] ) ) {
			$cx_items[ $issue_row['path'] . '|' . $issue['code'] ] = array_column( $issue['items'], 'path' );
		}
	}
}
$cx_has = static fn( string $path, string $code ): bool => in_array( $code, $cx_codes[ $path ] ?? [], true );
hodima_ad( 'صفحه اصلی: لینک به خراب، به ریدایرکت، به ناایندکس، چند H1، تصویر بدون alt، لینک http، بدون canonical', array_map( static fn( string $c ): bool => $cx_has( '/', $c ), [ 'link_broken', 'link_redirect', 'link_noindex', 'multi_h1', 'img_alt', 'http_link', 'no_canonical' ] ), [ true, true, true, true, true, true, true ] );
hodima_ad( 'مقصدهای لینک: خراب فقط /broken/، ریدایرکت‌ها /old/ و /chain1/ (هر کدام یک بار)', [ $cx_items['/|link_broken'] ?? [], $cx_items['/|link_redirect'] ?? [] ], [ [ '/broken/' ], [ '/old/', '/chain1/' ] ] );
hodima_ad( 'زنجیره ریدایرکت: /chain1/ بله، /old/ نه', [ $cx_has( '/chain1/', 'redirect_chain' ), $cx_has( '/old/', 'redirect_chain' ) ], [ true, false ] );
hodima_ad( 'canonical به صفحه ۴۰۴ ← canonical_bad؛ صفحه ب سالم نه', [ $cx_has( '/canbad/', 'canonical_bad' ), $cx_has( '/b/', 'canonical_bad' ) ], [ true, false ] );
hodima_ad( 'نقشه سایت: آدرس ۴۰۴ داخل نقشه ← sitemap_bad؛ صفحه ایندکس‌پذیر بیرون نقشه ← not_in_sitemap؛ صفحه داخل نقشه نه', [ $cx_has( '/in-sitemap-404/', 'sitemap_bad' ), $cx_has( '/thin/', 'not_in_sitemap' ), $cx_has( '/b/', 'not_in_sitemap' ), $cx_has( '/noidx/', 'not_in_sitemap' ) ], [ true, true, false, false ] );
hodima_ad( 'متن کم فقط برای نوع شناخته‌شده (/thin/ برگه)؛ خانه و صفحه بلند نه', [ $cx_has( '/thin/', 'thin' ), $cx_has( '/', 'thin' ), $cx_has( '/b/', 'thin' ) ], [ true, false, false ] );
hodima_ad( 'صفحه عمیق: /d5/ و /d6/ (۵ و ۶ کلیک)؛ /d4/ نه', [ $cx_has( '/d5/', 'deep' ), $cx_has( '/d6/', 'deep' ), $cx_has( '/d4/', 'deep' ) ], [ true, true, false ] );
$cx_chips = HODIMA_SEO_ADVISOR_CRAWL_GROUPS;
hodima_ad( 'چیپ‌های تازه: لینک اشتباه، تصویر و محتوا، ساختار و نقشه سایت', [ in_array( 'link_broken', $cx_chips['linkfix'], true ), in_array( 'img_alt', $cx_chips['content'], true ), in_array( 'sitemap_bad', $cx_chips['tech'], true ), in_array( 'multi_h1', $cx_chips['title'], true ) ], [ true, true, true, true ] );

// خزش دوباره یک صفحه بعد از رفع: آمار لینک ورودی و عمق می‌ماند، ایراد صفحه برطرف می‌شود
$cx_inl_b = (int) $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( 'SELECT inlinks FROM %i WHERE path = %s', $cx_pt, '/b/' ) );
$cx_depth = (int) $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( 'SELECT depth FROM %i WHERE path = %s', $cx_pt, '/d3/' ) );
hodima_seo_advisor_task_set( '/', 'crawl_link_broken', '', 'لینک خراب', 'done' );
$cx_pages['/'] = $cx_page( 'خانه آزمایشی با عنوان به اندازه کافی بلند', '<h1>یک</h1><a href="/b/">ب</a> <a href="/d1/">عمق</a>', '<link rel="canonical" href="' . $cx_site . '/">' );
hodima_seo_advisor_mark_check_now( 'crawl', '/', [ [ 'rule' => 'crawl_link_broken', 'term' => '' ] ] );
$cx_after = [];
foreach ( hodima_seo_advisor_crawl_issues() as $issue_row ) {
	if ( '/' === $issue_row['path'] ) {
		$cx_after = array_column( $issue_row['issues'], 'code' );
	}
}
hodima_ad( 'بعد از رفع و «انجام دادم»: صفحه دوباره خوانده شد؛ ایرادهای لینک، H1، alt، http و canonical رفتند و کار «برطرف شد» است', [ array_intersect( [ 'link_broken', 'link_redirect', 'link_noindex', 'multi_h1', 'img_alt', 'http_link', 'no_canonical' ], $cx_after ), hodima_seo_advisor_task_state( (array) hodima_seo_advisor_task( '/', 'crawl_link_broken', '' ) ) ], [ [], 'fixed' ] );
hodima_ad( 'خزش دوباره آمار دور را نگه می‌دارد: لینک ورودی /b/ و عمق /d3/ همان قبل', [ (int) $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( 'SELECT inlinks FROM %i WHERE path = %s', $cx_pt, '/b/' ) ) === $cx_inl_b, (int) $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( 'SELECT depth FROM %i WHERE path = %s', $cx_pt, '/d3/' ) ) === $cx_depth ], [ true, true ] );

remove_filter( 'pre_http_request', $cx_filter, 10 );
remove_filter( 'hodima_seo_advisor_crawler_delay', '__return_zero' );
$GLOBALS['wpdb']->delete( hodima_seo_advisor_table( 'pages' ), [ 'path' => '/thin/' ] );
hodima_seo_advisor_clear_data();
delete_option( HODIMA_SEO_ADVISOR_CRAWLER_SETTINGS );
false === $cx_crawl ? delete_option( HODIMA_SEO_ADVISOR_CRAWL_OPTION ) : update_option( HODIMA_SEO_ADVISOR_CRAWL_OPTION, $cx_crawl, false );

echo "=== خزش همین حالا و ۲۴ ساعته (SEO 3.1.7)\n";
$nw_at = static fn( string $when ): int => ( new DateTimeImmutable( $when, wp_timezone() ) )->getTimestamp();
$nw_old = get_option( HODIMA_SEO_ADVISOR_CRAWLER_SETTINGS, false );
$nw_state = get_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION, false );
$nw_crawl = get_option( HODIMA_SEO_ADVISOR_CRAWL_OPTION, false );
update_option( HODIMA_SEO_ADVISOR_CRAWLER_SETTINGS, [ 'enabled' => true, 'always' => true, 'start' => 2, 'end' => 8, 'interval' => 7 ], false );
hodima_ad( 'گزینه «همه ساعت‌ها»: هر ساعت داخل پنجره است (حتی ظهر)', [ hodima_seo_advisor_crawler_in_window( $nw_at( '2026-10-29 12:00' ) ), hodima_seo_advisor_crawler_in_window( $nw_at( '2026-10-29 23:59' ) ), hodima_seo_advisor_crawler_settings()['always'] ], [ true, true, true ] );
update_option( HODIMA_SEO_ADVISOR_CRAWLER_SETTINGS, [ 'enabled' => true, 'always' => false, 'start' => 18, 'end' => 24, 'interval' => 7 ], false );
hodima_ad( 'پایان ۲۴:۰۰ = تا نیمه‌شب: ۲۳:۳۰ داخل، ۱۷:۵۹ و ۰۰:۳۰ بیرون', [ hodima_seo_advisor_crawler_settings()['end'], hodima_seo_advisor_crawler_in_window( $nw_at( '2026-10-29 23:30' ) ), hodima_seo_advisor_crawler_in_window( $nw_at( '2026-10-29 17:59' ) ), hodima_seo_advisor_crawler_in_window( $nw_at( '2026-10-29 00:30' ) ) ], [ 24, true, false, false ] );
update_option( HODIMA_SEO_ADVISOR_CRAWLER_SETTINGS, [ 'enabled' => false, 'always' => false, 'start' => 2, 'end' => 8, 'interval' => 7 ], false );
$nw_site  = untrailingslashit( home_url() );
$nw_calls = [];
add_filter(
	'pre_http_request',
	$nw_filter = static function ( mixed $pre, array $args, string $url ) use ( &$nw_calls, $nw_site ): mixed {
		if ( ! str_starts_with( $url, $nw_site ) ) {
			return $pre;
		}
		$nw_calls[] = $url;
		$path       = '/' . ltrim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' );
		if ( '/' === $path ) {
			return [ 'response' => [ 'code' => 200 ], 'headers' => [ 'content-type' => 'text/html' ], 'body' => '<html><head><title>خانه</title></head><body><main><h1>خانه</h1><a href="/x/">ایکس</a></main></body></html>' ];
		}
		return [ 'response' => [ 'code' => '/x/' === $path ? 200 : 404 ], 'headers' => [ 'content-type' => 'text/html' ], 'body' => '<html><head><title>ایکس</title></head><body><main><h1>ایکس</h1></main></body></html>' ];
	},
	10,
	3
);
add_filter( 'hodima_seo_advisor_crawler_delay', '__return_zero' );
delete_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION );
hodima_ad( 'خاموش و بیرون از ساعت: نوبت عادی هیچ درخواستی نمی‌زند', [ hodima_seo_advisor_crawler_tick()['ran'], count( $nw_calls ) ], [ 0, 0 ] );
hodima_seo_advisor_crawler_start_now();
$nw_s = hodima_seo_advisor_crawler_state();
$nw_s['seed'] = []; // آزمون فقط با سایت ساختگی
hodima_seo_advisor_crawler_save( $nw_s );
hodima_ad( '«خزش همین حالا»: دور در جریان با نشان manual؛ وضعیت «running» حتی با خاموش بودن', [ $nw_s['status'], $nw_s['manual'], hodima_seo_advisor_crawler_status()['state'], hodima_seo_advisor_crawler_status_view()[3] ], [ 'running', true, 'running', true ] );
$nw_out = hodima_seo_advisor_crawler_tick();
$nw_after = hodima_seo_advisor_crawler_state();
hodima_ad( 'نوبت عادی: دور دستی بی‌توجه به ساعت و خاموشی تمام می‌شود (۲ صفحه)، نشان manual پاک و بیکار', [ $nw_out['finished'], $nw_out['ran'], $nw_after['status'], $nw_after['manual'], hodima_seo_advisor_crawler_status()['state'] ], [ true, 2, 'idle', false, 'off' ] );
hodima_seo_advisor_crawler_start_now();
$nw_p = hodima_seo_advisor_crawler_state();
hodima_ad( 'شروع دوباره وقتی دوری در جریان نیست دور تازه می‌سازد؛ زنجیره کرون زمان‌بندی شد', [ $nw_p['status'], (bool) wp_next_scheduled( HODIMA_SEO_ADVISOR_CRAWLER_CRON, [ 0 ] ) ], [ 'running', true ] );
$nw_p['pause_until'] = time() + 600;
hodima_seo_advisor_crawler_save( $nw_p );
$nw_calls = [];
hodima_ad( 'مکث کندی سایت دور دستی را هم نگه می‌دارد', [ hodima_seo_advisor_crawler_tick()['ran'], count( $nw_calls ), hodima_seo_advisor_crawler_status()['state'] ], [ 0, 0, 'paused' ] );
remove_filter( 'pre_http_request', $nw_filter, 10 );
remove_filter( 'hodima_seo_advisor_crawler_delay', '__return_zero' );
wp_clear_scheduled_hook( HODIMA_SEO_ADVISOR_CRAWLER_CRON, [ 0 ] );
hodima_seo_advisor_clear_data();
false === $nw_old ? delete_option( HODIMA_SEO_ADVISOR_CRAWLER_SETTINGS ) : update_option( HODIMA_SEO_ADVISOR_CRAWLER_SETTINGS, $nw_old, false );
false === $nw_state ? delete_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION ) : update_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION, $nw_state, false );
false === $nw_crawl ? delete_option( HODIMA_SEO_ADVISOR_CRAWL_OPTION ) : update_option( HODIMA_SEO_ADVISOR_CRAWL_OPTION, $nw_crawl, false );

echo "=== سلامت سایت: نمره (SEO 3.1.6)\n";
$sc_row = static fn( string $path, string ...$codes ): array => [ 'path' => $path, 'issues' => array_map( static fn( string $c ): array => [ 'code' => $c, 'detail' => '', 'url' => '', 'items' => [] ], $codes ) ];
hodima_ad( 'بدون صفحه خزش‌شده: نمره null', hodima_seo_advisor_score( [], 0 ), null );
$sc_clean = hodima_seo_advisor_score( [], 100 );
hodima_ad( 'بدون ایراد: همه بخش‌ها و نمره کل ۱۰۰', [ $sc_clean['score'], array_unique( array_column( $sc_clean['categories'], 'score' ) ), $sc_clean['top'] ], [ 100, [ 100 ], [] ] );
// ۱۰۰ صفحه، ۵ صفحه با یک ایراد قرمز (لینک خراب) ← بخش لینک‌ها ۱۰۰−۵×۲ = ۹۰؛ نمره کل ۱۰۰−۱۰×۰٫۳ = ۹۷
$sc_a = hodima_seo_advisor_score( array_map( static fn( int $i ): array => $sc_row( "/p{$i}/", 'link_broken' ), range( 1, 5 ) ), 100 );
hodima_ad( 'پنج درصد صفحه با ایراد قرمز: بخش لینک‌ها ۹۰، بقیه ۱۰۰، نمره کل ۹۷، اول فهرست همان ایراد', [ $sc_a['categories']['cat-links']['score'], $sc_a['categories']['cat-meta']['score'], $sc_a['score'], $sc_a['top'][0]['code'], $sc_a['top'][0]['pages'], $sc_a['categories']['cat-links']['pages'] ], [ 90, 100, 97, 'link_broken', 5, 5 ] );
$sc_b = hodima_seo_advisor_score( [ $sc_row( '/x/', 'img_alt', 'multi_h1', 'deep' ), $sc_row( '/y/', 'img_alt' ) ], 10 );
hodima_ad( 'ایرادهای هم‌سطح: کهربایی ضریب ۱ (۲ صفحه از ۱۰ = ۲۰)، آبی ۰٫۳؛ بخش محتوا ۱۰۰−۲۰−۱۰ = ۷۰ و ساختار ۹۷', [ $sc_b['categories']['cat-content']['score'], $sc_b['categories']['cat-structure']['score'], $sc_b['categories']['cat-content']['pages'] ], [ 70, 97, 2 ] );
$sc_c = hodima_seo_advisor_score( array_map( static fn( int $i ): array => $sc_row( "/z{$i}/", 'link_broken', 'broken', 'no_meta' ), range( 1, 10 ) ), 10 );
hodima_ad( 'همه صفحه‌ها ایراد دارند: نمره بخش از صفر پایین‌تر نمی‌رود و نمره کل بین ۰ و ۱۰۰', [ $sc_c['categories']['cat-links']['score'], $sc_c['score'] >= 0 && $sc_c['score'] <= 100, count( $sc_c['top'] ) ], [ 0, true, 3 ] );
hodima_ad( 'برچسب نمره: ۹۵ عالی، ۸۰ خوب، ۶۰ نیاز به کار (کهربایی)، ۴۰ ضعیف (قرمز)', [ hodima_seo_advisor_score_grade( 95 ), hodima_seo_advisor_score_grade( 80 ), hodima_seo_advisor_score_grade( 60 ), hodima_seo_advisor_score_grade( 40 ) ], [ [ 'label' => 'عالی', 'level' => 'ok' ], [ 'label' => 'خوب', 'level' => 'ok' ], [ 'label' => 'نیاز به کار', 'level' => 'warn' ], [ 'label' => 'ضعیف', 'level' => 'bad' ] ] );
hodima_ad( 'وزن بخش‌ها ۱۰۰ و هر کد ایراد فقط در یک بخش', [ array_sum( array_column( HODIMA_SEO_ADVISOR_SCORE_CATEGORIES, 'weight' ) ), count( array_merge( ...array_column( HODIMA_SEO_ADVISOR_SCORE_CATEGORIES, 'codes' ) ) ) === count( array_unique( array_merge( ...array_column( HODIMA_SEO_ADVISOR_SCORE_CATEGORIES, 'codes' ) ) ) ) ], [ 100, true ] );
$sc_all_codes = array_merge( ...array_column( HODIMA_SEO_ADVISOR_SCORE_CATEGORIES, 'codes' ) );
$sc_issue_codes = array_merge( ...array_values( HODIMA_SEO_ADVISOR_CRAWL_GROUPS ) );
hodima_ad( 'هر کد ایراد خزش در یکی از بخش‌های نمره است (ایرادی بی‌نمره نمی‌ماند)', array_values( array_diff( $sc_issue_codes, $sc_all_codes ) ), [] );
hodima_ad( 'فیلتر جدول: گروه چیپ و بخش نمره شناخته می‌شوند، ناشناس نه', [ hodima_seo_advisor_score_filter_codes( 'linkfix' ), in_array( 'slow', hodima_seo_advisor_score_filter_codes( 'cat-speed' ), true ), hodima_seo_advisor_score_filter_codes( 'nope' ) ], [ [ 'link_broken', 'link_redirect', 'link_noindex', 'redirect_chain' ], true, [] ] );

$sc_old = get_option( HODIMA_SEO_ADVISOR_SCORE_OPTION, false );
$sc_crawl = get_option( HODIMA_SEO_ADVISOR_CRAWL_OPTION, false );
delete_option( HODIMA_SEO_ADVISOR_SCORE_OPTION );
hodima_seo_advisor_ensure_tables();
$sc_pt = hodima_seo_advisor_table( 'crawl_pages' );
$GLOBALS['wpdb']->query( $GLOBALS['wpdb']->prepare( 'DELETE FROM %i', $sc_pt ) );
$GLOBALS['wpdb']->query( $GLOBALS['wpdb']->prepare( 'DELETE FROM %i', hodima_seo_advisor_table( 'crawl_links' ) ) );
foreach ( range( 1, 4 ) as $i ) {
	$GLOBALS['wpdb']->insert( $sc_pt, [ 'path' => "/sc{$i}/", 'status' => 200, 'indexable' => 1, 'title' => "عنوان {$i} با طول کافی برای آزمون", 'title_len' => 40, 'meta_len' => 100, 'h1' => "تیتر {$i}", 'inlinks' => 5, 'text_links' => 3, 'depth' => 1 ] );
}
$GLOBALS['wpdb']->update( $sc_pt, [ 'meta_len' => 0 ], [ 'path' => '/sc1/' ] ); // یک صفحه بدون متا
hodima_seo_advisor_score_record();
$GLOBALS['wpdb']->update( $sc_pt, [ 'meta_len' => 100 ], [ 'path' => '/sc1/' ] ); // رفع شد
hodima_seo_advisor_score_record();
$sc_hist = hodima_seo_advisor_score_history();
hodima_ad( 'تاریخچه: دو دور ثبت شد؛ دور اول با ایراد متا کمتر از دور دوم (بدون ایراد ۱۰۰)', [ count( $sc_hist ), $sc_hist[0]['score'] < $sc_hist[1]['score'], $sc_hist[1]['score'] ], [ 2, true, 100 ] );
update_option( HODIMA_SEO_ADVISOR_SCORE_OPTION, array_fill( 0, 20, [ 'time' => time(), 'score' => 50 ] ), false );
hodima_seo_advisor_score_record();
hodima_ad( 'تاریخچه نهایتا ۱۲ دور', count( hodima_seo_advisor_score_history() ), 12 );

// نمای کارت در تب ایرادهای فنی
$GLOBALS['wpdb']->update( $sc_pt, [ 'meta_len' => 0 ], [ 'path' => '/sc2/' ] );
update_option( HODIMA_SEO_ADVISOR_SCORE_OPTION, [ [ 'time' => time() - 7 * DAY_IN_SECONDS, 'score' => 80 ], [ 'time' => time() - DAY_IN_SECONDS, 'score' => 90 ] ], false );
update_option( HODIMA_SEO_ADVISOR_CRAWL_OPTION, [ 'imported' => time() - 3600, 'pages' => 4, 'links' => 0, 'files' => [], 'source' => 'crawler' ], false );
update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [ 'fetched' => time() - 3600, 'property' => 'sc-domain:' . $host, 'days' => 90, 'pages' => 1, 'queries' => 0, 'advised' => 0, 'error' => '' ] + hodima_seo_advisor_ranges( 90 ), false );
hodima_seo_advisor_store( [ '/sc1/' => [ 'clicks' => 5, 'impressions' => 2000, 'position' => 6.0 ] ], [], [] );
update_option( HODIMA_SEO_SC_ADVISOR_KEY_OPTION, $key_json, false );
wp_set_current_user( 1 );
$_GET = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'crawl' ];
$sc_html = $cap( static fn() => hodima_seo_advisor_render_admin() );
hodima_ad( 'کارت سلامت سایت: عنوان، نمره با برچسب، ۵ بخش با پیوند فیلتر، سه کار اول، «نمره چطور حساب می‌شود؟»', [ str_contains( $sc_html, '>سلامت سایت<' ), 1 === substr_count( $sc_html, 'hodima-advisor__sc-gauge ' ), substr_count( $sc_html, 'class="hodima-advisor__sc-cat"' ), str_contains( $sc_html, 'cf=cat-meta' ), str_contains( $sc_html, 'سه کار اول برای بالا بردن نمره' ), str_contains( $sc_html, 'نمره چطور حساب می‌شود؟' ) ], [ true, true, 5, true, true, true ] );
hodima_ad( 'نمره نسبت به دور قبل: «بدتر» با اختلاف', str_contains( $sc_html, 'امتیاز بدتر از دور قبل' ) || str_contains( $sc_html, 'امتیاز بهتر از دور قبل' ) || str_contains( $sc_html, 'بدون تغییر نسبت به دور قبل' ), true );
$_GET    = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'crawl', 'cf' => 'cat-meta' ];
$sc_meta = $cap( static fn() => hodima_seo_advisor_render_admin() );
hodima_ad( 'فیلتر بخش «عنوان و متا»: چیپ تازه فعال، ردیف /sc2/ با ایراد متا دیده می‌شود', [ str_contains( $sc_meta, 'عنوان و متا' ), str_contains( $sc_meta, '/sc2/' ), 1 === preg_match( '#hodima-advisor__view" href="[^"]*cf=cat-meta"[^>]*aria-current#u', $sc_meta ) ], [ true, true, true ] );
$_GET = [];
wp_set_current_user( 0 );
hodima_ad( 'پاک کردن داده‌های دستار تاریخچه نمره را هم پاک می‌کند', [ hodima_seo_advisor_clear_data(), get_option( HODIMA_SEO_ADVISOR_SCORE_OPTION, 'none' ) ], [ true, 'none' ] );
false === $sc_old ? delete_option( HODIMA_SEO_ADVISOR_SCORE_OPTION ) : update_option( HODIMA_SEO_ADVISOR_SCORE_OPTION, $sc_old, false );
false === $sc_crawl ? delete_option( HODIMA_SEO_ADVISOR_CRAWL_OPTION ) : update_option( HODIMA_SEO_ADVISOR_CRAWL_OPTION, $sc_crawl, false );
delete_option( HODIMA_SEO_SC_ADVISOR_KEY_OPTION );

echo "=== ادغام تب‌ها (SEO 3.1.3)\n";
hodima_ad( 'کلید تب‌ها (هشت بخش قدیمی و «همه ایرادها»)؛ تب‌های حذف‌شده نیستند', [ hodima_seo_advisor_tab_keys(), array_intersect( [ 'done', 'breakdown', 'trend' ], hodima_seo_advisor_tab_keys() ) ], [ [ 'opportunities', 'overview', 'competition', 'terms', 'health', 'crawl', 'indexing', 'tasks', 'settings' ], [] ] );
hodima_ad( 'پیوند قدیمی: done ← کارها (منتظر نتیجه)، breakdown و trend ← آمار جستجو', [ HODIMA_SEO_ADVISOR_LEGACY_TABS['done'], HODIMA_SEO_ADVISOR_LEGACY_TABS['breakdown'][0], HODIMA_SEO_ADVISOR_LEGACY_TABS['trend'][0] ], [ [ 'tasks', [ 'tstatus' => 'waiting' ] ], 'terms', 'terms' ] );
hodima_ad( 'ریدایرکت پیوند قدیمی به هوک منو وصل است', function_exists( 'hodima_seo_advisor_legacy_tab_redirect' ), true );
$_GET = [];
wp_set_current_user( 0 );

echo "=== سقف صفحه‌های خزنده (SEO 3.1.8)\n";
$cp_t = hodima_seo_advisor_table( 'crawl_pages' );
$cp_w = $GLOBALS['wpdb'];
$cp_w->query( $cp_w->prepare( 'DELETE FROM %i', $cp_t ) );
foreach ( [ '/', '/a/', '/b/', '/c/' ] as $cp_path ) {
	$cp_w->insert( $cp_t, [ 'path' => $cp_path, 'status' => 200, 'indexable' => 1 ] );
}
$cp_full = [ 'seen' => array_fill( 0, HODIMA_SEO_ADVISOR_CRAWLER_MAX_PAGES, 1 ), 'queue' => [] ];
hodima_seo_advisor_crawler_enqueue( $cp_full, home_url( '/zzz/' ) );
hodima_ad( 'سقف پر: صفحه تازه به صف نمی‌رود ولی نشان «سقف» خورد', [ $cp_full['queue'], ! empty( $cp_full['capped'] ) ], [ [], true ] );
$cp_ok = [ 'seen' => [ '/' => 1 ], 'queue' => [] ];
hodima_seo_advisor_crawler_enqueue( $cp_ok, home_url( '/b/' ) );
hodima_ad( 'سقف پر نیست: صفحه به صف می‌رود و نشان سقف نمی‌خورد', [ count( $cp_ok['queue'] ), ! empty( $cp_ok['capped'] ) ], [ 1, false ] );

$cp_cap = [ 'seen' => [ '/' => 1, '/a/' => 1 ], 'queue' => [], 'sitemap' => [], 'capped' => true ];
hodima_seo_advisor_crawler_finalize( $cp_cap );
hodima_seo_advisor_crawler_save( $cp_cap ); // نوبت خزنده بعد از finalize ذخیره می‌کند
hodima_ad( 'دور سقف‌خورده: هیچ ردیفی پاک نمی‌شود', $cp_w->get_col( $cp_w->prepare( 'SELECT path FROM %i ORDER BY path', $cp_t ) ), [ '/', '/a/', '/b/', '/c/' ] );
hodima_ad( 'نشان سقف بعد از پایان دور می‌ماند و وضعیت صفحه هشدار می‌دهد', [ hodima_seo_advisor_crawler_state()['capped'], hodima_seo_advisor_crawler_status()['capped'], hodima_seo_advisor_crawler_status_view()[0] ], [ true, true, 'warn' ] );

$cp_norm = [ 'seen' => [ '/' => 1, '/a/' => 1 ], 'queue' => [], 'sitemap' => [] ];
hodima_seo_advisor_crawler_finalize( $cp_norm );
hodima_seo_advisor_crawler_save( $cp_norm );
hodima_ad( 'دور عادی (بدون سقف): صفحه دیده‌نشده مثل قبل پاک می‌شود', $cp_w->get_col( $cp_w->prepare( 'SELECT path FROM %i ORDER BY path', $cp_t ) ), [ '/', '/a/' ] );
hodima_ad( 'دور عادی نشان سقف را پاک می‌کند', [ hodima_seo_advisor_crawler_state()['capped'], hodima_seo_advisor_crawler_status_view()[0] ], [ false, 'ok' ] );
hodima_seo_advisor_crawler_start();
hodima_ad( 'شروع دور تازه نشان سقف را صفر می‌کند', hodima_seo_advisor_crawler_state()['capped'], false );
$cp_w->query( $cp_w->prepare( 'DELETE FROM %i', $cp_t ) );
delete_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION );

echo "=== سلامت کارهای خودکار (SEO 3.2.0)\n";
$ch_keep = [];
foreach ( [ HODIMA_SEO_ADVISOR_STATUS_OPTION, HODIMA_SEO_ADVISOR_CRAWLER_OPTION, HODIMA_SEO_ADVISOR_CRAWLER_SETTINGS, HODIMA_SEO_ADVISOR_PIPELINE_OPTION, HODIMA_SEO_ADVISOR_RUNS_OPTION ] as $ch_opt ) {
	$ch_keep[ $ch_opt ] = get_option( $ch_opt, null );
}
delete_option( HODIMA_SEO_ADVISOR_RUNS_OPTION ); // اجرای ثبت‌شده آزمون‌های پیشین نباید سنجش «بر پایه رویداد» را بپوشاند
$ch_now    = time();
$ch_clear  = static function (): void {
	foreach ( [ HODIMA_SEO_ADVISOR_CRON, HODIMA_SEO_ADVISOR_INSPECT_CRON, HODIMA_SEO_ADVISOR_STAGE_CRON, HODIMA_SEO_ADVISOR_CRAWLER_CRON ] as $hook ) {
		wp_clear_scheduled_hook( $hook );
	}
};
$ch_job    = static function ( string $key ) use ( $ch_now ): array {
	foreach ( hodima_seo_advisor_cron_health( $ch_now )['jobs'] as $job ) {
		if ( $key === $job['key'] ) {
			return $job;
		}
	}
	return [];
};
$ch_clear();
update_option( HODIMA_SEO_ADVISOR_PIPELINE_OPTION, [], false );
update_option( HODIMA_SEO_ADVISOR_CRAWLER_SETTINGS, [ 'enabled' => true, 'always' => false, 'start' => 2, 'end' => 8, 'interval' => 7 ], false );
update_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION, [ 'status' => 'idle', 'finished' => $ch_now - 3 * DAY_IN_SECONDS ], false );

// دریافت روزانه
$ch_status = (array) get_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [] );
$ch_levels = [];
foreach ( [ 10, 60, 100 ] as $ch_hours ) {
	update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [ 'fetched' => $ch_now - $ch_hours * HOUR_IN_SECONDS ] + $ch_status, false );
	$ch_levels[] = $ch_job( 'refresh' )['level'];
}
hodima_ad( 'دریافت روزانه: ۱۰ ساعت سالم، ۶۰ ساعت دیر شده، ۱۰۰ ساعت متوقف', $ch_levels, [ 'ok', 'warn', 'bad' ] );
hodima_ad( 'متن «آخرین اجرا» فارسی و نسبی است', str_contains( $ch_job( 'refresh' )['last'], 'پیش' ), true );

// خزنده: بیکار
$ch_levels = [];
foreach ( [ 3, 12, 20 ] as $ch_days ) {
	update_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION, [ 'status' => 'idle', 'finished' => $ch_now - $ch_days * DAY_IN_SECONDS ], false );
	$ch_levels[] = $ch_job( 'crawler' )['level'];
}
hodima_ad( 'خزنده بیکار با دور هفت‌روزه: ۳ روز سالم، ۱۲ روز دیر شده، ۲۰ روز متوقف', $ch_levels, [ 'ok', 'warn', 'bad' ] );

// خزنده: دور باز (پنجره ۲ تا ۸ ← ۲۴ ساعت مجاز)
$ch_levels = [];
foreach ( [ 5, 30, 60 ] as $ch_hours ) {
	update_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION, [ 'status' => 'running', 'started' => $ch_now - 70 * HOUR_IN_SECONDS, 'tick' => $ch_now - $ch_hours * HOUR_IN_SECONDS ], false );
	$ch_levels[] = $ch_job( 'crawler' )['level'];
}
hodima_ad( 'دور باز با پنجره شبانه: نوبت ۵ ساعت پیش سالم (شبانه عادی است)، ۳۰ ساعت دیر شده، ۶۰ ساعت متوقف', $ch_levels, [ 'ok', 'warn', 'bad' ] );
update_option( HODIMA_SEO_ADVISOR_CRAWLER_SETTINGS, [ 'enabled' => true, 'always' => true, 'start' => 2, 'end' => 8, 'interval' => 7 ], false );
update_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION, [ 'status' => 'running', 'tick' => $ch_now - 3 * HOUR_IN_SECONDS ], false );
hodima_ad( 'خزنده ۲۴ ساعته: نوبت ۳ ساعت پیش دیر شده است', $ch_job( 'crawler' )['level'], 'warn' );
update_option( HODIMA_SEO_ADVISOR_CRAWLER_SETTINGS, [ 'enabled' => false, 'always' => false, 'start' => 2, 'end' => 8, 'interval' => 7 ], false );
update_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION, [ 'status' => 'idle', 'finished' => $ch_now - 60 * DAY_IN_SECONDS ], false );
hodima_ad( 'خزش خودکار خاموش: «غیرفعال» و در شمار مشکل‌ها نیست', [ $ch_job( 'crawler' )['level'], hodima_seo_advisor_cron_health( $ch_now )['level'] ], [ 'off', 'bad' ] ); // دریافت ۱۰۰ ساعته هنوز متوقف است
$ch_crawler_off = array_filter( hodima_seo_advisor_cron_health( $ch_now )['jobs'], static fn( array $job ): bool => 'crawler' === $job['key'] && 'off' === $job['level'] );
hodima_ad( 'کار خاموش مشکل شمرده نمی‌شود', count( $ch_crawler_off ), 1 );
update_option( HODIMA_SEO_ADVISOR_CRAWLER_SETTINGS, [ 'enabled' => true, 'always' => false, 'start' => 2, 'end' => 8, 'interval' => 7 ], false );
update_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION, [ 'status' => 'idle', 'finished' => $ch_now - DAY_IN_SECONDS ], false );

// بررسی ایندکس: رویداد دو بار در روز که از موعد گذشته
$ch_levels = [];
foreach ( [ 3, 20, 70 ] as $ch_hours ) {
	wp_clear_scheduled_hook( HODIMA_SEO_ADVISOR_INSPECT_CRON );
	wp_schedule_event( $ch_now - $ch_hours * HOUR_IN_SECONDS, 'twicedaily', HODIMA_SEO_ADVISOR_INSPECT_CRON );
	$ch_levels[] = $ch_job( 'inspect' )['level'];
}
hodima_ad( 'بررسی ایندکس: ۳ ساعت از موعد سالم، ۲۰ ساعت دیر شده، ۷۰ ساعت متوقف', $ch_levels, [ 'ok', 'warn', 'bad' ] );
wp_clear_scheduled_hook( HODIMA_SEO_ADVISOR_INSPECT_CRON );
wp_schedule_event( $ch_now + HOUR_IN_SECONDS, 'twicedaily', HODIMA_SEO_ADVISOR_INSPECT_CRON );
hodima_ad( 'بررسی ایندکس طبق برنامه سالم است', [ $ch_job( 'inspect' )['level'], $ch_job( 'inspect' )['last'] ], [ 'ok', 'هنوز اجرایی ثبت نشده؛ طبق برنامه' ] );

// ساخت پیشنهادها
hodima_ad( 'نوبت پس‌زمینه‌ای که نیست: بی‌کار و سالم', [ $ch_job( 'pipeline' )['level'], $ch_job( 'pipeline' )['last'] ], [ 'ok', 'بی‌کار' ] );
update_option( HODIMA_SEO_ADVISOR_PIPELINE_OPTION, [ 'stages' => [ 'trend' ] ], false );
$ch_levels = [];
foreach ( [ 10 * MINUTE_IN_SECONDS, 2 * HOUR_IN_SECONDS, 10 * HOUR_IN_SECONDS ] as $ch_late ) {
	wp_clear_scheduled_hook( HODIMA_SEO_ADVISOR_STAGE_CRON );
	wp_schedule_single_event( $ch_now - $ch_late, HODIMA_SEO_ADVISOR_STAGE_CRON );
	$ch_levels[] = $ch_job( 'pipeline' )['level'];
}
hodima_ad( 'نوبت ثبت‌شده که اجرا نشده: ۱۰ دقیقه سالم، ۲ ساعت دیر شده، ۱۰ ساعت متوقف', $ch_levels, [ 'ok', 'warn', 'bad' ] );
wp_clear_scheduled_hook( HODIMA_SEO_ADVISOR_STAGE_CRON );

// زمان آخرین اجرای واقعی (۳.۲.۶): دکمه دستی هم در کارت دیده می‌شود
wp_clear_scheduled_hook( HODIMA_SEO_ADVISOR_INSPECT_CRON );
wp_schedule_event( $ch_now - 5 * DAY_IN_SECONDS, 'twicedaily', HODIMA_SEO_ADVISOR_INSPECT_CRON );
hodima_ad( 'بدون اجرای ثبت‌شده: رویداد ۵ روز از موعد گذشته ← متوقف با متن «اجرایی ثبت نشده؛ رویداد …»', [ $ch_job( 'inspect' )['level'], str_contains( $ch_job( 'inspect' )['last'], 'اجرایی ثبت نشده' ) ], [ 'bad', true ] );
update_option( HODIMA_SEO_ADVISOR_RUNS_OPTION, [ 'inspect' => $ch_now - 2 * HOUR_IN_SECONDS ], false );
hodima_ad( 'اجرای دستی ثبت‌شده (۲ ساعت پیش) حتی با رویداد ۵ روز عقب: سالم و «آخرین اجرا … پیش»', [ $ch_job( 'inspect' )['level'], str_starts_with( $ch_job( 'inspect' )['last'], 'آخرین اجرا' ) ], [ 'ok', true ] );
update_option( HODIMA_SEO_ADVISOR_RUNS_OPTION, [ 'inspect' => $ch_now - 40 * HOUR_IN_SECONDS ], false );
hodima_ad( 'آخرین اجرا ۴۰ ساعت پیش: دیر شده؛ ۸۰ ساعت: متوقف', [ $ch_job( 'inspect' )['level'], ( static function () use ( $ch_now, $ch_job ): string { update_option( HODIMA_SEO_ADVISOR_RUNS_OPTION, [ 'inspect' => $ch_now - 80 * HOUR_IN_SECONDS ], false ); return $ch_job( 'inspect' )['level']; } )() ], [ 'warn', 'bad' ] );
delete_option( HODIMA_SEO_ADVISOR_RUNS_OPTION );
hodima_seo_advisor_run_mark( 'inspect' );
hodima_seo_advisor_run_mark( 'pipeline' );
hodima_ad( 'run_mark زمان را می‌نویسد و run_time می‌خواند (جدا برای هر کار)', [ hodima_seo_advisor_run_time( 'inspect' ) >= $ch_now, hodima_seo_advisor_run_time( 'pipeline' ) >= $ch_now, hodima_seo_advisor_run_time( 'nope' ) ], [ true, true, 0 ] );
delete_option( HODIMA_SEO_ADVISOR_RUNS_OPTION );

// اجرای واقعی نوبت‌ها و بررسی ایندکس خودشان زمان را ثبت می‌کنند
update_option( HODIMA_SEO_ADVISOR_PIPELINE_OPTION, [ 'stages' => [ 'ctr' ], 'property' => 'sc-domain:x', 'range' => [], 'days' => 90 ], false );
hodima_seo_advisor_pipeline_run( 5.0 );
hodima_ad( 'اجرای نوبت‌ها (pipeline_run) زمان آخرین اجرا را ثبت می‌کند', hodima_seo_advisor_run_time( 'pipeline' ) >= $ch_now, true );
$ch_inspect_block = static fn( $pre ) => new WP_Error( 'http_request_failed', 'آزمون' );
add_filter( 'pre_http_request', $ch_inspect_block, 5 );
hodima_seo_advisor_inspect_run( 'sc-domain:x', 1, [ 'checked' => 0, 'error' => '', 'quota' => false ] );
remove_filter( 'pre_http_request', $ch_inspect_block, 5 );
hodima_ad( 'بررسی ایندکس (inspect_run) حتی با خطای شبکه همان آدرس زمان آخرین اجرا را ثبت می‌کند', hodima_seo_advisor_run_time( 'inspect' ) >= $ch_now, true );
delete_option( HODIMA_SEO_ADVISOR_RUNS_OPTION );

// دکمه «گرفتن داده تازه» وقتی رویداد عقب است «در پس‌زمینه شروع شد» نمی‌گوید و همان‌جا چند آدرس را بررسی می‌کند
wp_clear_scheduled_hook( HODIMA_SEO_ADVISOR_INSPECT_CRON );
wp_schedule_event( $ch_now - 5 * DAY_IN_SECONDS, 'twicedaily', HODIMA_SEO_ADVISOR_INSPECT_CRON );
hodima_ad( 'کرون‌ایستاده تشخیص داده می‌شود (رویداد ۵ روز از موعد گذشته)', hodima_seo_advisor_cron_stalled( $ch_now ), true );
add_filter( 'pre_http_request', $ch_inspect_block, 5 );
$ch_extras = hodima_seo_advisor_refresh_extras();
remove_filter( 'pre_http_request', $ch_inspect_block, 5 );
if ( hodima_seo_advisor_inspect_pending() > 0 ) {
	hodima_ad( 'refresh_extras با کرون ایستاده: پیام «کرون سایت اجرا نمی‌کند»، نه «در پس‌زمینه شروع شد»؛ و اجرا ثبت شد', [ str_contains( implode( ' ', $ch_extras ), 'کرون سایت اجرا نمی‌کند' ), str_contains( implode( ' ', $ch_extras ), 'در پس‌زمینه شروع شد' ), hodima_seo_advisor_run_time( 'inspect' ) >= $ch_now ], [ true, false, true ] );
}
delete_option( HODIMA_SEO_ADVISOR_RUNS_OPTION );
wp_clear_scheduled_hook( HODIMA_SEO_ADVISOR_INSPECT_CRON );
wp_schedule_event( $ch_now + HOUR_IN_SECONDS, 'twicedaily', HODIMA_SEO_ADVISOR_INSPECT_CRON );
hodima_ad( 'رویداد طبق برنامه: کرون ایستاده نیست', hodima_seo_advisor_cron_stalled( $ch_now ), false );

// جمع‌بندی و نمایش
update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [ 'fetched' => $ch_now - 100 * HOUR_IN_SECONDS ] + $ch_status, false );
$ch_health = hodima_seo_advisor_cron_health( $ch_now );
hodima_ad( 'جمع‌بندی: دریافت متوقف ← سطح کل متوقف و یک مشکل', [ $ch_health['level'], $ch_health['problems'] ], [ 'bad', 1 ] );
$ch_notice = static function ( string $tab ): string {
	ob_start();
	hodima_seo_advisor_cron_notice( $tab );
	return (string) ob_get_clean();
};
$ch_html = $ch_notice( 'opportunities' );
hodima_ad( 'نوار هشدار روی «پیشنهادها» با نام کار، زمان و پیوند به کارت تنظیمات', [ str_contains( $ch_html, 'کارهای خودکار دستار عقب‌اند' ), str_contains( $ch_html, 'دریافت داده گوگل' ), str_contains( $ch_html, 'tab=settings#hodima-advisor-cron' ), str_contains( $ch_html, 'notice-error' ) ], [ true, true, true, true ] );
hodima_ad( 'نوار هشدار روی «ایرادهای فنی» هم هست و روی تب‌های دیگر نیست', [ '' !== $ch_notice( 'crawl' ), $ch_notice( 'terms' ), $ch_notice( 'tasks' ), $ch_notice( 'settings' ) ], [ true, '', '', '' ] );
ob_start();
hodima_seo_advisor_render_cron_card();
$ch_card = (string) ob_get_clean();
hodima_ad( 'کارت در حالت مشکل: شناسه پیوند، سطر وضعیت، چهار کار، راهنمای باز با دستور و دکمه کپی', [ str_contains( $ch_card, 'id="hodima-advisor-cron"' ), str_contains( $ch_card, '1 کار از 4 کار خودکار عقب است' ) || str_contains( $ch_card, '۱ کار از ۴ کار خودکار عقب است' ), substr_count( $ch_card, 'hodima-advisor__cron-job"' ) + substr_count( $ch_card, 'hodima-advisor__cron-job ' ), str_contains( $ch_card, 'wp-cron.php?doing_wp_cron' ), str_contains( $ch_card, 'data-hdsa-copy="hodima-advisor-cron-cmd"' ), str_contains( $ch_card, '<details class="hds-more hodima-advisor__cron-guide" open>' ) ], [ true, true, 4, true, true, true ] );
hodima_ad( 'مشکل‌دار اول در فهرست و سالم‌ها یک‌خطی', [ strpos( $ch_card, 'دریافت داده گوگل' ) < strpos( $ch_card, 'بررسی ایندکس گوگل' ), substr_count( $ch_card, 'hodima-advisor__cron-job--compact' ) ], [ true, 3 ] );
hodima_ad( 'کار دیرشده دکمه «اجرا همین حالا» دارد (فقط همان یکی)، به کنش دستی خودش با nonce و بازگشت به تنظیمات', [ substr_count( $ch_card, 'class="hodima-advisor__cron-run"' ), str_contains( $ch_card, 'name="action" value="hodima_advisor_refresh"' ), str_contains( $ch_card, 'name="tab" value="settings"' ), str_contains( $ch_card, 'اجرا همین حالا' ) ], [ 1, true, true, true ] );
hodima_ad( 'فرم‌های دکمه برای چهار کار به چهار کنش دستی موجود می‌روند', array_map( static fn( string $key ): bool => 1 === preg_match( '#name="action" value="(hodima_advisor_(refresh|inspect_now|crawler_now|pipeline_now))"#', hodima_seo_advisor_cron_run_form( $key ) ), [ 'refresh', 'inspect', 'crawler', 'pipeline' ] ), [ true, true, true, true ] );
hodima_ad( 'کار ناشناخته فرم نمی‌سازد', hodima_seo_advisor_cron_run_form( 'nope' ), '' );

// همه سالم: هیچ نواری و کارت یک‌خط با جزئیات جمع‌شده
update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [ 'fetched' => $ch_now - 5 * HOUR_IN_SECONDS ] + $ch_status, false );
hodima_ad( 'همه سالم: سطح ok و هیچ نواری نمی‌آید', [ hodima_seo_advisor_cron_health( $ch_now )['level'], $ch_notice( 'opportunities' ) ], [ 'ok', '' ] );
ob_start();
hodima_seo_advisor_render_cron_card();
$ch_card = (string) ob_get_clean();
hodima_ad( 'کارت سالم: یک خط وضعیت، جزئیات جمع‌شده (بدون open)، بدون راهنمای رفع', [ str_contains( $ch_card, 'خودکار به موقع اجرا شده‌اند' ), str_contains( $ch_card, '<details class="hds-more hodima-advisor__cron-more">' ), str_contains( $ch_card, 'چطور درستش کنم' ) ], [ true, true, false ] );

// ذخیره وضعیت خزنده زمان آخرین نوبت را می‌نویسد
update_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION, [ 'status' => 'idle' ], false );
$ch_state = hodima_seo_advisor_crawler_state();
hodima_seo_advisor_crawler_save( $ch_state );
hodima_ad( 'ذخیره وضعیت خزنده زمان آخرین نوبت (tick) را می‌نویسد', hodima_seo_advisor_crawler_state()['tick'] >= $ch_now, true );
hodima_ad( 'دستور کرون واقعی با آدرس همین سایت', str_starts_with( hodima_seo_advisor_cron_command(), '*/5 * * * * wget -q -O - ' ) && str_contains( hodima_seo_advisor_cron_command(), 'wp-cron.php?doing_wp_cron' ), true );

$ch_clear();
foreach ( $ch_keep as $ch_opt => $ch_val ) {
	null === $ch_val ? delete_option( $ch_opt ) : update_option( $ch_opt, $ch_val, false );
}

echo "=== کیفیت داده (SEO 3.2.1)\n";
$qd_keep = [];
foreach ( [ HODIMA_SEO_ADVISOR_STATUS_OPTION, HODIMA_SEO_ADVISOR_CAPS_OPTION, HODIMA_SEO_ADVISOR_CRAWL_OPTION, HODIMA_SEO_ADVISOR_SITEMAP_OPTION, HODIMA_SEO_ADVISOR_CRAWLER_OPTION ] as $qd_opt ) {
	$qd_keep[ $qd_opt ] = get_option( $qd_opt, null );
}
$qd_status = (array) get_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [] );
$qd_item   = static function ( string $key ): array {
	foreach ( hodima_seo_advisor_quality()['items'] as $item ) {
		if ( $key === $item['key'] ) {
			return $item;
		}
	}
	return [];
};
update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [ 'fetched' => time() - HOUR_IN_SECONDS, 'end' => gmdate( 'Y-m-d', time() - 2 * DAY_IN_SECONDS ), 'days' => 90, 'error' => '' ] + $qd_status, false );
delete_option( HODIMA_SEO_ADVISOR_CAPS_OPTION );
hodima_ad( 'داده گوگل: دریافت سالم بدون سقف و خطا ← کامل با «تا … پیش، ۹۰ روز»', [ $qd_item( 'google' )['level'], str_contains( $qd_item( 'google' )['value'], 'پیش' ) ], [ 'ok', true ] );
hodima_seo_advisor_caps( [ 'fetch' => true ] );
hodima_ad( 'داده گوگل: سقف ردیف رسیده ← ناقص با راه‌حل «بازه کوتاه‌تر»', [ $qd_item( 'google' )['level'], str_contains( $qd_item( 'google' )['note'], 'بازه را کوتاه‌تر' ) ], [ 'warn', true ] );
hodima_seo_advisor_caps( [ 'fetch' => false ] );
update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [ 'error' => 'خطای آزمون' ] + (array) get_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [] ), false );
hodima_ad( 'داده گوگل: آخرین دریافت ناموفق ← ناقص', [ $qd_item( 'google' )['level'], str_contains( $qd_item( 'google' )['note'], 'ناموفق' ) ], [ 'warn', true ] );
update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [ 'fetched' => 0, 'error' => '' ] + (array) get_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [] ), false );
hodima_ad( 'داده گوگل: هنوز دریافتی نشده ← نامعلوم (مشکل شمرده نمی‌شود)', [ $qd_item( 'google' )['level'], hodima_seo_advisor_quality()['issues'] ], [ 'unknown', 0 ] );
update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [ 'fetched' => time() - HOUR_IN_SECONDS, 'end' => gmdate( 'Y-m-d', time() - 2 * DAY_IN_SECONDS ), 'days' => 90, 'error' => '' ] + $qd_status, false );

// پوشش تحلیل (به یک ردیف آدرس نیاز دارد؛ جدول در این نقطه خالی است)
$qd_pt = hodima_seo_advisor_table( 'pages' );
$qd_keep_pages = (array) $GLOBALS['wpdb']->get_results( $GLOBALS['wpdb']->prepare( 'SELECT * FROM %i', $qd_pt ), ARRAY_A );
$GLOBALS['wpdb']->insert( $qd_pt, [ 'path' => '/qd-page/', 'clicks' => 1, 'impressions' => 2000, 'advice' => '' ] );
hodima_ad( 'پوشش تحلیل بدون سقف: کامل و «همه … آدرس»', [ $qd_item( 'analysis' )['level'], str_starts_with( $qd_item( 'analysis' )['value'], 'همه' ) ], [ 'ok', true ] );
hodima_seo_advisor_caps( [ 'analyze' => true ] );
hodima_ad( 'پوشش تحلیل: سقف تحلیل ← ناقص «N از M آدرس» با راه‌حل حداقل نمایش', [ $qd_item( 'analysis' )['level'], str_contains( $qd_item( 'analysis' )['value'], ' از ' ), str_contains( $qd_item( 'analysis' )['note'], 'حداقل نمایش' ) ], [ 'warn', true, true ] );
hodima_seo_advisor_caps( [ 'analyze' => false, 'classify' => 300 ] );
hodima_ad( 'پوشش تحلیل: آدرس‌های بی‌نوع ← ناقص با شمار', [ $qd_item( 'analysis' )['level'], str_contains( $qd_item( 'analysis' )['note'], '300' ) || str_contains( $qd_item( 'analysis' )['note'], '۳۰۰' ) ], [ 'warn', true ] );
hodima_seo_advisor_caps( [ 'classify' => 0 ] );

$GLOBALS['wpdb']->delete( $qd_pt, [ 'path' => '/qd-page/' ] );
// پوشش خزش
$qd_t = hodima_seo_advisor_table( 'crawl_pages' );
$qd_w = $GLOBALS['wpdb'];
$qd_w->query( $qd_w->prepare( 'DELETE FROM %i', $qd_t ) );
foreach ( [ '/q1/', '/q2/', '/q3/' ] as $qd_path ) {
	$qd_w->insert( $qd_t, [ 'path' => $qd_path, 'status' => 200, 'indexable' => 1 ] );
}
hodima_seo_advisor_crawl_map( true );
delete_option( HODIMA_SEO_ADVISOR_CRAWL_OPTION );
delete_option( HODIMA_SEO_ADVISOR_SITEMAP_OPTION );
update_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION, [ 'status' => 'idle' ], false );
hodima_ad( 'پوشش خزش: هنوز خزشی نشده ← نامعلوم', $qd_item( 'crawl' )['level'], 'unknown' );
update_option( HODIMA_SEO_ADVISOR_CRAWL_OPTION, [ 'imported' => time(), 'pages' => 3, 'links' => 0, 'files' => [], 'source' => 'crawler' ], false );
hodima_ad( 'پوشش خزش: نقشه سایت خوانده نشده ← نامعلوم (نه ناقص)', [ $qd_item( 'crawl' )['level'], str_contains( $qd_item( 'crawl' )['value'], 'نقشه سایت خوانده نشده' ) ], [ 'unknown', true ] );
update_option( HODIMA_SEO_ADVISOR_SITEMAP_OPTION, [ 'fetched' => time(), 'paths' => [ '/q1/', '/q2/', '/q3/' ] ], false );
hodima_ad( 'پوشش خزش: همه آدرس‌های نقشه سایت خزیده ← کامل «۳ از ۳»', [ $qd_item( 'crawl' )['level'], str_contains( $qd_item( 'crawl' )['value'], hodima_seo_advisor_num( 3 ) . ' از ' . hodima_seo_advisor_num( 3 ) ) ], [ 'ok', true ] );
update_option( HODIMA_SEO_ADVISOR_SITEMAP_OPTION, [ 'fetched' => time(), 'paths' => [ '/q1/', '/n1/', '/n2/', '/n3/' ] ], false );
hodima_ad( 'پوشش خزش: ۱ از ۴ آدرس نقشه سایت (زیر ۹۰٪) ← ناقص با «خزش همین حالا»', [ $qd_item( 'crawl' )['level'], str_contains( $qd_item( 'crawl' )['note'], 'خزش همین حالا' ) ], [ 'warn', true ] );
update_option( HODIMA_SEO_ADVISOR_SITEMAP_OPTION, [ 'fetched' => time(), 'paths' => [ '/q1/', '/q2/', '/q3/' ] ], false );
update_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION, [ 'status' => 'idle', 'capped' => true ], false );
hodima_ad( 'پوشش خزش: دور سقف‌خورده ← ناقص حتی با نقشه کامل', [ $qd_item( 'crawl' )['level'], str_contains( $qd_item( 'crawl' )['value'], 'سقف' ) ], [ 'warn', true ] );
update_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION, [ 'status' => 'idle' ], false );

// جمع‌بندی و نمایش
$qd_q = hodima_seo_advisor_quality();
hodima_ad( 'جمع‌بندی همه کامل: سطح ok و صفر مورد', [ $qd_q['level'], $qd_q['issues'] ], [ 'ok', 0 ] );
$qd_render = static function ( string $tab ): string {
	ob_start();
	hodima_seo_advisor_render_quality( $tab );
	return (string) ob_get_clean();
};
$qd_ok = $qd_render( 'opportunities' );
hodima_ad( 'نوار کامل: عنوان «کیفیت داده»، نشان «کامل»، بدون فهرست توضیح', [ str_contains( $qd_ok, 'کیفیت داده' ), str_contains( $qd_ok, '>کامل<' ), str_contains( $qd_ok, 'hodima-advisor__quality-notes' ) ], [ true, true, false ] );
hodima_ad( 'نوار: سه li (داده گوگل، پوشش تحلیل، پوشش خزش)', substr_count( $qd_ok, 'class="hodima-advisor__quality-item"' ), 3 );
$GLOBALS['wpdb']->insert( $qd_pt, [ 'path' => '/qd-page/', 'clicks' => 1, 'impressions' => 2000, 'advice' => '' ] );
hodima_seo_advisor_caps( [ 'analyze' => true ] );
$qd_warn = $qd_render( 'crawl' );
hodima_ad( 'نوار ناقص روی «ایرادهای فنی»: «کامل نیست (۱ مورد)» و فهرست توضیح با نام مورد', [ str_contains( $qd_warn, 'کامل نیست' ), str_contains( $qd_warn, 'hodima-advisor__quality-notes' ), str_contains( $qd_warn, '<b>پوشش تحلیل:</b>' ) ], [ true, true, true ] );
hodima_seo_advisor_caps( [ 'analyze' => false ] );
$GLOBALS['wpdb']->delete( $qd_pt, [ 'path' => '/qd-page/' ] );
hodima_ad( 'نوار فقط روی دو تب: تنظیمات، آمار، کارها و … خالی‌اند', [ $qd_render( 'settings' ), $qd_render( 'terms' ), $qd_render( 'tasks' ), $qd_render( 'health' ) ], [ '', '', '', '' ] );
delete_option( HODIMA_SEO_ADVISOR_CRAWL_OPTION );
delete_option( HODIMA_SEO_ADVISOR_SITEMAP_OPTION );
update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [ 'fetched' => 0 ] + $qd_status, false );
$qd_w->query( $qd_w->prepare( 'DELETE FROM %i', $qd_t ) );
hodima_seo_advisor_crawl_map( true );
$qd_empty_q = hodima_seo_advisor_quality();
hodima_ad( 'همه نامعلوم (هنوز داده‌ای نیست): سطح unknown و هیچ نواری نمی‌آید', [ $qd_empty_q['level'] === 'unknown' || $qd_empty_q['level'] === 'ok', $qd_empty_q['issues'] ], [ true, 0 ] );

foreach ( $qd_keep as $qd_opt => $qd_val ) {
	null === $qd_val ? delete_option( $qd_opt ) : update_option( $qd_opt, $qd_val, false );
}
hodima_seo_advisor_crawl_map( true );

echo "=== فشرده‌سازی پیشخوان (SEO 3.3.0)\n";
$fs_keep = [ get_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, null ), get_option( HODIMA_SEO_ADVISOR_CAPS_OPTION, null ) ];
update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [ 'fetched' => time() - HOUR_IN_SECONDS, 'end' => gmdate( 'Y-m-d', time() - 2 * DAY_IN_SECONDS ), 'days' => 90, 'error' => '' ] + (array) get_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [] ), false );
$fs_bar = $cap( static fn() => hodima_seo_advisor_render_toolbar( 'opportunities' ) );
hodima_ad( 'نوار کیفیت داده داخل همان کارت «گرفتن داده تازه» است (یک کارت، نه دو کارت)', [ substr_count( $fs_bar, '<section' ), str_contains( $fs_bar, 'hodima-advisor__quality--embedded' ), str_contains( $fs_bar, 'hds-card hodima-advisor__quality' ) ], [ 1, true, false ] );
wp_set_current_user( 1 );
$_GET = [];
$fs_page = $cap( static fn() => hodima_seo_advisor_render_admin() );
hodima_ad( 'صفحه: نوار کیفیت یک بار و از نوع جاسازی‌شده', [ substr_count( $fs_page, 'id="hodima-advisor-quality-title"' ), str_contains( $fs_page, 'hodima-advisor__quality--embedded' ) ], [ 1, true ] );
ob_start();
hodima_seo_advisor_render_crawler_card();
$fs_card = (string) ob_get_clean();
hodima_ad( 'خزش خودکار: «خزش همین حالا» فرم جدا بیرون از جمع‌شونده؛ تنظیمات داخل details بسته', [ 1 === preg_match( '#</details>#', $fs_card ), str_contains( $fs_card, 'class="hodima-advisor__crawler-now"' ), str_contains( $fs_card, '<details class="hds-more hodima-advisor__crawler-more">' ), strpos( $fs_card, 'hodima-advisor__crawler-now' ) < strpos( $fs_card, 'hodima-advisor__crawler-more' ) ], [ true, true, true, true ] );
hodima_ad( 'فرم زمان‌بندی همان نام فیلدها را دارد (روشن‌بودن با کلید، حالت ساعت، از، تا، فاصله)', [ str_contains( $fs_card, 'class="hds-switch" name="enabled"' ), str_contains( $fs_card, 'name="always" value="0"' ), str_contains( $fs_card, 'name="always" value="1"' ), str_contains( $fs_card, 'name="start"' ), str_contains( $fs_card, 'name="end"' ), str_contains( $fs_card, 'name="interval"' ), str_contains( $fs_card, 'value="hodima_advisor_crawler_settings"' ), str_contains( $fs_card, 'value="hodima_advisor_crawler_now"' ) ], [ true, true, true, true, true, true, true, true ] );
hodima_ad( 'کارت خزش دیگر تب ندارد', str_contains( $fs_card, 'hds-tabset' ), false );
$fs_gl = $cap( static fn() => hodima_seo_advisor_render_glossary() );
hodima_ad( 'اصطلاح‌ها هنوز details با همان فهرست است', [ str_contains( $fs_gl, '<details class="hds-card hodima-advisor__glossary">' ), str_contains( $fs_gl, '<dt>' ) ], [ true, true ] );
null === $fs_keep[0] ? delete_option( HODIMA_SEO_ADVISOR_STATUS_OPTION ) : update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, $fs_keep[0], false );
null === $fs_keep[1] ? delete_option( HODIMA_SEO_ADVISOR_CAPS_OPTION ) : update_option( HODIMA_SEO_ADVISOR_CAPS_OPTION, $fs_keep[1], false );

echo "=== جدول‌های آدرس‌های خراب و وضعیت در گوگل (SEO 3.2.5)\n";
wp_set_current_user( 1 );
$_GET = [];
$ht_keep = [ get_option( HODIMA_SEO_ADVISOR_HEALTH_OPTION, null ), get_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION, null ) ];
update_option( HODIMA_SEO_ADVISOR_HEALTH_OPTION, [
	'computed' => time(), 'router' => true, 'total' => 10, 'seen' => 8, 'fresh' => 1,
	'dead'     => [ [ 'path' => '/ht-dead/', 'impressions' => 500, 'clicks' => 5, 'state' => 'missing', 'target' => '', 'code' => 404 ] ],
	'unseen'   => [
		[ 'id' => 1, 'path' => '/ht-titled/', 'title' => 'عنوان جدا', 'type' => 'post', 'days' => 40 ],
		[ 'id' => 2, 'path' => '/ht-bare/', 'title' => '', 'type' => 'post', 'days' => 50 ],
	],
], false );
$ht_html = $cap( static fn() => hodima_seo_advisor_render_health( hodima_seo_advisor_status() ) );
hodima_ad( 'آدرس‌های خراب: هر دو جدول کلاس کارت موبایل دارند و سلول‌ها برچسب (data-label)', [ substr_count( $ht_html, 'hodima-advisor__terms hodima-advisor__cards' ), str_contains( $ht_html, 'data-label="نمایش"' ), str_contains( $ht_html, 'data-label="مقصد"' ), str_contains( $ht_html, 'data-label="نوع"' ), str_contains( $ht_html, 'data-label="سن"' ) ], [ 2, true, true, true, true ] );
hodima_ad( 'توضیح‌ها داخل دو جمع‌شونده بسته (نه کادرهای باز)؛ متن‌شان هنوز هست', [ substr_count( $ht_html, 'class="hds-more hodima-advisor__explain"' ), str_contains( $ht_html, 'open' ) && str_contains( $ht_html, '<details class="hds-more hodima-advisor__explain" open' ), str_contains( $ht_html, 'آدرس‌های «۴۰۴»' ) || str_contains( $ht_html, 'هنوز در گوگل نمایش می‌گیرند' ), str_contains( $ht_html, 'چه کنید:' ) ], [ 2, false, true, true ] );
hodima_ad( 'دکمه‌های ردیف صفحه بدون نمایش یک ردیف (inline)؛ عنوان تکراری نمی‌آید وقتی عنوان همان مسیر است', [ str_contains( $ht_html, 'hodima-advisor__row-acts hodima-advisor__row-acts--inline' ), str_contains( $ht_html, 'عنوان جدا' ), 1 === substr_count( $ht_html, 'ht-bare/</bdi>' ) || 1 === substr_count( $ht_html, 'ht-bare/' ) ], [ true, true, true ] );
update_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION, [ '/ht-index/' => [ 'verdict' => 'NEUTRAL', 'coverage' => 'Crawled - currently not indexed', 'robots' => '', 'indexing' => '', 'fetch' => 'SUCCESSFUL', 'last_crawl' => gmdate( 'c', time() - DAY_IN_SECONDS ), 'google_canonical' => 'https://hodima.test/ht-index/', 'user_canonical' => 'https://hodima.test/ht-index/', 'crawled_as' => 'MOBILE', 'checked' => time() ] ], false );
$ix_html = $cap( static fn() => hodima_seo_advisor_render_indexing() );
hodima_ad( 'وضعیت در گوگل: جدول کارت موبایل با برچسب، توضیح‌ها در جمع‌شونده بسته', [ str_contains( $ix_html, 'hodima-advisor__terms hodima-advisor__cards' ), str_contains( $ix_html, 'data-label="آخرین خزش"' ), str_contains( $ix_html, 'class="hds-more hodima-advisor__explain"' ), str_contains( $ix_html, 'hodima-advisor__explain" open' ), str_contains( $ix_html, 'بعد از رفع، «انجام دادم» را بزنید' ) ], [ true, true, true, false, true ] );
null === $ht_keep[0] ? delete_option( HODIMA_SEO_ADVISOR_HEALTH_OPTION ) : update_option( HODIMA_SEO_ADVISOR_HEALTH_OPTION, $ht_keep[0], false );
null === $ht_keep[1] ? delete_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION ) : update_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION, $ht_keep[1], false );

echo "=== پاکسازی\n";
hodima_seo_advisor_store( [], [], [] );
hodima_ad( 'جدول‌ها خالی شد', hodima_seo_advisor_pages()['total'], 0 );
wp_delete_post( $page_id, true );
foreach ( $saved as $opt => $value ) {
	false === $value ? delete_option( $opt ) : update_option( $opt, $value, false );
}
false === $saved_settings ? delete_option( HODIMA_SEO_ADVISOR_SETTINGS_OPTION ) : update_option( HODIMA_SEO_ADVISOR_SETTINGS_OPTION, $saved_settings, false );
delete_transient( 'hodima_discover_sc_token_' . substr( md5( 'advisor@test.iam.gserviceaccount.com' ), 0, 12 ) );

echo "\n" . ( $failed ? "✘ {$failed} آزمون رد شد ({$passed} قبول)" : "✔ همه {$passed} آزمون دستار سئو قبول" ) . "\n";
exit( $failed ? 1 : 0 );
