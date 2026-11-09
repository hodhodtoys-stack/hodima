<?php
/**
 * بخش 1 از آزمون‌های دستار سئو (tools/wp-harness/advisor-tests.php آن را به ترتیب و در همان دامنه سراسری می‌خواند):
 * یکسان‌سازی آدرس، جمع‌بندی و ذخیره داده سرچ کنسول، دریافت با پاسخ ساختگی گوگل، قاعده‌های پیشنهاد و نماهای اول.
 * تنهایی اجرا نمی‌شود: متغیرها و توابع کمکی (hodima_ad، $cap، …) از فایل اصلی و بخش‌های قبلی می‌آیند.
 */

declare(strict_types=1);

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
require_once HODIMA_SEO_DIR . '/core/advisor/advisor-detail-view.php';
require_once HODIMA_SEO_DIR . '/core/advisor/advisor-recrawl.php';
require_once HODIMA_SEO_DIR . '/core/advisor/advisor-images.php';
require_once HODIMA_SEO_DIR . '/core/advisor/advisor-overview-view.php';
	require_once HODIMA_SEO_DIR . '/core/advisor/advisor-cron-view.php';
	require_once HODIMA_SEO_DIR . '/core/advisor/advisor-jobs-view.php';
	require_once HODIMA_SEO_DIR . '/core/advisor/advisor-jobs-ajax.php';
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
	require_once HODIMA_SEO_DIR . '/core/advisor/advisor-jobs-view.php';
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
// SEO 3.7.6 (طرح تأییدشده «یک منبع»؛ آزمون‌های قدیمی «پیشنهاد زنده با گام‌ها» عمدا عوض شد): کادر فقط خلاصه است — همان کارهای
// «کارها» برای این آدرس، یک خط برای هر کار، بدون گام/جزئیات/دکمه انجام دادم؛ داده گوگل همان قبلی
$bx_items = hodima_seo_advisor_box_tasks( '/advisor-box-test/' );
$bx_ov    = [];
foreach ( hodima_seo_advisor_overview_build() as $bx_page ) {
	if ( '/advisor-box-test/' === $bx_page['path'] ) {
		$bx_ov = array_column( $bx_page['items'], 'label' );
	}
}
hodima_ad( 'کادر: همان کارهای «کارها» برای همین آدرس (یک منبع)', [ array_column( $bx_items, 'label' ) === $bx_ov, count( $bx_items ) > 0, substr_count( $box, '<li><span class="hodima-advisor-box__dot' ) === count( $bx_items ) ], [ true, true, true ] );
hodima_ad( 'کادر: نشان «N کار»، برچسب «نرخ کلیک کم» در فهرست، کلمه برتر و عددهای گوگل', [ str_contains( $box, 'hodima-advisor-box__pill--warn' ) && str_contains( $box, sprintf( '%s کار', number_format_i18n( count( $bx_items ) ) ) ), str_contains( $box, '<strong>' . hodima_seo_advisor_rule_label( 'low_ctr' ) . '</strong>' ), str_contains( $box, 'پلومریا کلیپس' ), str_contains( $box, '36,492' ) || str_contains( $box, '۳۶٬۴۹۲' ) ], [ true, true, true, true ] );
hodima_ad( 'کادر: بدون گام‌ها، جزئیات، دکمه «انجام دادم/لازم نیست» و «{where}»؛ یک دکمه اصلی به «کارها»', [ str_contains( $box, 'hodima-advisor-box__todo' ), str_contains( $box, 'hodima-advisor-steps' ), str_contains( $box, 'status=done' ), str_contains( $box, '{where}' ), substr_count( $box, 'hodima-advisor-box__go' ) ], [ false, false, false, false, 1 ] );
// «کارها» (SEO 3.7.7، خواست کاربر): دو دکمه در همان ردیف برچسب‌ها؛ کلمه پیشنهاد گوگل نشان جدا دارد (با نام صفحه یکی گرفته نشود)
$ly_get = $_GET;
$_GET   = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'q' => 'advisor-box-test' ];
ob_start();
hodima_seo_advisor_render_overview();
$ly_html = (string) ob_get_clean();
$_GET    = $ly_get;
preg_match_all( '~<div class="hodima-advisor__ov-labels">(.*?)</form>~s', $ly_html, $ly_rows );
hodima_ad(
	'کارها: هر ردیف برچسب، فرم «انجام دادم/لازم نیست» را در خود دارد (یک ردیف)؛ کلمه پیشنهاد گوگل نشان «ov-term» دارد',
	[ count( $ly_rows[0] ) > 0, count( $ly_rows[0] ) === substr_count( $ly_html, 'class="hodima-advisor__ov-item"' ), (bool) array_filter( $ly_rows[1], static fn( string $r ): bool => str_contains( $r, 'hodima-advisor__mark-done' ) && str_contains( $r, 'hodima-advisor__mark-reject' ) ), str_contains( $ly_html, 'hodima-advisor__ov-term' ) ],
	[ true, true, true, true ]
);
hodima_ad( 'کادر: لینک به صفحه مشاور با جستجوی همین آدرس، بدون لینک بازگرداندن', [ str_contains( $box, 'page=hodima-advisor' ) && str_contains( $box, 'q=/advisor-box-test/' ), str_contains( $box, 'restore=1' ) ], [ true, false ] );

hodima_seo_advisor_set_ignored( '/advisor-box-test/', true );
ob_start();
hodima_seo_advisor_render_box( (string) get_permalink( $box_id ) );
$box = (string) ob_get_clean();
hodima_ad( 'کادر: پنل داده با سه خانه هم‌اندازه و جدول کلمه‌ها (بدون ستون کناری)', [ substr_count( $box, 'hodima-advisor-box__value' ), str_contains( $box, 'hodima-advisor-box__table' ), str_contains( $box, 'hodima-advisor-box__aside' ) ], [ 3, true, false ] );
hodima_ad( 'نادیده‌گرفته‌شده: پیشنهادی نیست و لینک بازگرداندن با nonce هست', [ str_contains( $box, 'نادیده گرفته شده' ), str_contains( $box, 'hodima-advisor-box__dot' ), str_contains( $box, 'restore=1' ) && str_contains( $box, '_wpnonce=' ) && str_contains( $box, 'action=hodima_advisor_ignore' ) ], [ true, false, true ] );
hodima_seo_advisor_set_ignored( '/advisor-box-test/', false );

ob_start();
hodima_seo_advisor_render_box( (string) get_permalink( $box_draft ) );
$box = (string) ob_get_clean();
hodima_ad( 'آدرسی که داده ندارد: پیام روشن، بدون پیشنهاد', [ str_contains( $box, 'هنوز داده‌ای در سرچ کنسول ثبت نشده' ), str_contains( $box, 'hodima-advisor-box__item' ) ], [ true, false ] );

// «جزئیات و پیش‌نویس» در «کارها» (SEO 3.7.5): همان گام‌های کادر ویرایش، با باز کردن (admin-ajax) یا بدون JS با ?steps=کلید
$st_sugs = hodima_seo_advisor_analyze( '/advisor-box-test/', true )['suggestions'] ?? [];
$st_one  = null;
foreach ( $st_sugs as $st_s ) {
	if ( isset( HODIMA_SEO_ADVISOR_STEPS_HINTS[ (string) $st_s['rule'] ] ) && ! empty( $st_s['steps'] ) ) {
		$st_one = $st_s;
		break;
	}
}
hodima_ad( 'داده آزمون: کادر ویرایش برای این صفحه دست‌کم یک پیشنهاد با جزئیات دارد', null !== $st_one, true );
if ( null !== $st_one ) {
	$st_rule = (string) $st_one['rule'];
	$st_term = (string) ( $st_one['term'] ?? '' );
	$st_key  = hodima_seo_advisor_steps_key( '/advisor-box-test/', $st_rule, $st_term );
	ob_start();
	hodima_seo_advisor_render_steps_lazy( '/advisor-box-test/', $st_rule, $st_term );
	$st_closed = (string) ob_get_clean();
	$_GET['steps'] = $st_key;
	ob_start();
	hodima_seo_advisor_render_steps_lazy( '/advisor-box-test/', $st_rule, $st_term );
	$st_open = (string) ob_get_clean();
	unset( $_GET['steps'] );
	ob_start();
	hodima_seo_advisor_render_steps_body( (array) $st_one['steps'] );
	$st_body = (string) ob_get_clean();
	hodima_ad(
		'کارها: کادر «جزئیات و پیش‌نویس» بسته و بدون محتوای سنگین (فقط پیوند بدون JS)؛ با ?steps=کلید باز و همان محتوای کادر ویرایش',
		[ str_contains( $st_closed, 'data-hdsa-steps' ), str_contains( $st_closed, ' open' ), str_contains( $st_closed, 'steps=' . $st_key ), str_contains( $st_closed, 'hodima-advisor-steps"' ), str_contains( $st_open, ' open data-loaded="1"' ), str_contains( $st_open, $st_body ) ],
		[ true, false, true, false, true, true ]
	);
	ob_start();
	hodima_seo_advisor_render_steps_lazy( '/advisor-box-test/', 'decline', '' );
	hodima_ad( 'قاعده بدون جزئیات (افت کلیک) کادر خالی نمی‌گیرد', (string) ob_get_clean(), '' );
	ob_start();
	hodima_seo_advisor_render_steps_result( '/advisor-box-test/', $st_rule, 'کلمه‌ای که پیشنهاد نشده' );
	hodima_ad( 'پیشنهادی که با متن فعلی صفحه برقرار نیست: یک خط روشن، نه کادر خالی', str_contains( (string) ob_get_clean(), 'دیگر برقرار نیست' ), true );

	// admin-ajax: مدیر با nonce درست محتوا می‌گیرد؛ بی‌nonce یا غیرمدیر ۴۰۳
	$st_ajax = static function ( array $post ): array {
		$_POST = $post;
		$_REQUEST = $post;
		add_filter( 'wp_doing_ajax', '__return_true' );
		$die = static fn(): callable => static function (): void { throw new RuntimeException( 'die' ); };
		add_filter( 'wp_die_ajax_handler', $die );
		ob_start();
		try {
			hodima_seo_advisor_handle_steps_ajax();
		} catch ( RuntimeException $e ) {
			unset( $e );
		}
		$out = (string) ob_get_clean();
		remove_filter( 'wp_die_ajax_handler', $die );
		remove_filter( 'wp_doing_ajax', '__return_true' );
		$_POST = $_REQUEST = [];
		return (array) json_decode( $out, true );
	};
	$st_post = [ 'action' => 'hodima_advisor_steps', 'path' => '/advisor-box-test/', 'rule' => $st_rule, 'term' => $st_term ];
	wp_set_current_user( 1 );
	$st_ok  = $st_ajax( $st_post + [ 'nonce' => wp_create_nonce( 'hodima_advisor_steps' ) ] );
	$st_bad = $st_ajax( $st_post + [ 'nonce' => 'x' ] );
	wp_set_current_user( 0 );
	$st_anon = $st_ajax( $st_post + [ 'nonce' => wp_create_nonce( 'hodima_advisor_steps' ) ] );
	wp_set_current_user( 1 );
	hodima_ad( 'admin-ajax جزئیات: مدیر با nonce همان محتوا؛ nonce غلط و غیرمدیر رد', [ ! empty( $st_ok['success'] ) && (string) ( $st_ok['data']['html'] ?? '' ) === $st_body, $st_bad['success'] ?? null, $st_anon['success'] ?? null ], [ true, false, false ] );
}

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
