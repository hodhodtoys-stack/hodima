<?php
/**
 * آزمون خودکار ماژول «مشاور سئو» (SEO، core/advisor): یکسان‌سازی آدرس، جمع‌بندی ردیف‌های
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

echo "=== قاعده‌ها: نرخ کلیک معمول و متن\n";
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
hodima_ad( 'تحلیل آدرس ناشناخته ← null', hodima_seo_advisor_analyze( '/no-such-page/' ), null );
hodima_ad( 'تحلیل دوباره قبلی‌ها را پاک می‌کند (نه روی هم)', [ hodima_seo_advisor_analyze_all(), hodima_seo_advisor_pages( [ 'has_advice' => true ] )['total'] ], [ 4, 4 ] );

echo "=== صفحه پیشخوان: خلاصه، فیلتر، نادیده گرفتن، CSV، نمایش\n";
// صفحه پیشخوان فقط در پیشخوان لود می‌شود؛ آزمون خط فرمان خودش لودش می‌کند
if ( ! function_exists( 'hodima_seo_advisor_render_admin' ) ) {
	require_once HODIMA_SEO_DIR . '/core/advisor/advisor-admin.php';
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
hodima_ad( 'CSV: سربرگ + ۴ آدرس، اولی بیشترین کلیک بالقوه', [ count( $csv ), $csv[0][0], $csv[1][0], $csv[1][6] ], [ 5, 'آدرس', '/rules/a/', '1511' ] );
hodima_ad( 'CSV: ورودی شبیه فرمول بی‌اثر می‌شود', [ hodima_seo_advisor_csv_cell( '=HYPERLINK("x")' ), hodima_seo_advisor_csv_cell( '+1' ), hodima_seo_advisor_csv_cell( '@x' ), hodima_seo_advisor_csv_cell( '/ok/' ) ], [ "'=HYPERLINK(\"x\")", "'+1", "'@x", '/ok/' ] );

update_option( 'hodima_discover_sc_key', $key_json, false );
update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [ 'fetched' => time() - 3600, 'property' => 'sc-domain:' . $host, 'days' => 90, 'pages' => 5, 'queries' => 2, 'advised' => 4, 'error' => '' ] + hodima_seo_advisor_ranges( 90 ), false );
wp_set_current_user( 1 );
ob_start();
hodima_seo_advisor_render_admin();
$html = (string) ob_get_clean();
hodima_ad( 'صفحه: سربرگ، ۴ کارت آمار، ردیف‌ها، دکمه به‌روزرسانی و نادیده گرفتن', [ str_contains( $html, 'hds-hero__title' ), substr_count( $html, 'class="hds-stat"' ), substr_count( $html, 'hodima-advisor__item' ) >= 4, str_contains( $html, 'name="action" value="hodima_advisor_refresh"' ), str_contains( $html, 'نادیده گرفتن این آدرس' ) ], [ true, 4, true, true, true ] );
hodima_ad( 'صفحه: بازه فعلی انتخاب‌شده و خروجی CSV با nonce', [ str_contains( $html, 'value="90" checked' ), str_contains( $html, 'action=hodima_advisor_csv' ) && str_contains( $html, '_wpnonce=' ) ], [ true, true ] );
delete_option( 'hodima_discover_sc_key' );
ob_start();
hodima_seo_advisor_render_admin();
$html = (string) ob_get_clean();
hodima_ad( 'بدون کلید: راهنمای اتصال، دکمه غیرفعال، بدون جدول', [ str_contains( $html, 'کلید حساب سرویس گوگل ثبت نشده است' ), str_contains( $html, 'hodima-advisor__refresh" disabled' ) || str_contains( $html, "hodima-advisor__refresh\"  disabled='disabled'" ) || str_contains( $html, 'disabled' ), str_contains( $html, 'hodima-advisor__item' ) ], [ true, true, false ] );
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
wp_delete_post( $box_id, true );
wp_delete_post( $box_draft, true );
wp_set_current_user( 0 );

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
