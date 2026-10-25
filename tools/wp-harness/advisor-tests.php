<?php
/**
 * آزمون خودکار ماژول «مشاور سئو» (SEO، core/advisor): یکسان‌سازی آدرس، جمع‌بندی ردیف‌های
 * سرچ کنسول، ذخیره و خواندن، سه درخواست دریافت (با پاسخ ساختگی گوگل)، شکست بدون خراب
 * کردن داده قبلی، بازه‌ها.
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
	echo "✘ ماژول مشاور سئو لود نشده است\n";
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
foreach ( [ 'hodima_discover_sc_key', 'hodima_gi_json_key', 'hodima_discover_sc_settings', 'hodima_discover_sc_sites', HODIMA_SEO_ADVISOR_STATUS_OPTION ] as $opt ) {
	$saved[ $opt ] = get_option( $opt );
}
delete_transient( 'hodima_discover_sc_token_' . substr( md5( 'advisor@test.iam.gserviceaccount.com' ), 0, 12 ) );
update_option( 'hodima_discover_sc_key', $key_json, false );
delete_option( 'hodima_gi_json_key' );
delete_option( 'hodima_discover_sc_settings' );
update_option( 'hodima_discover_sc_sites', [ 'sc-domain:' . $host ], false );

// صفحه واقعی برای شناسایی نوع شیء
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

echo "=== پاکسازی\n";
hodima_seo_advisor_store( [], [], [] );
hodima_ad( 'جدول‌ها خالی شد', hodima_seo_advisor_pages()['total'], 0 );
wp_delete_post( $page_id, true );
foreach ( $saved as $opt => $value ) {
	false === $value ? delete_option( $opt ) : update_option( $opt, $value, false );
}
false === $saved_settings ? delete_option( HODIMA_SEO_ADVISOR_SETTINGS_OPTION ) : update_option( HODIMA_SEO_ADVISOR_SETTINGS_OPTION, $saved_settings, false );
delete_transient( 'hodima_discover_sc_token_' . substr( md5( 'advisor@test.iam.gserviceaccount.com' ), 0, 12 ) );

echo "\n" . ( $failed ? "✘ {$failed} آزمون رد شد ({$passed} قبول)" : "✔ همه {$passed} آزمون مشاور سئو قبول" ) . "\n";
exit( $failed ? 1 : 0 );
