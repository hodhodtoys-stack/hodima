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

/**
 * آزمون‌های قدیمی طول را با نویسه می‌دادند؛ از SEO 3.5.4 خزنده پیکسل می‌سنجد (title_px/meta_px، ۰ = اندازه‌گیری‌نشده).
 * این دو تابع نویسه آزمون را به پیکسل تقریبی همان اندازه برمی‌گردانند (عنوان ۲۰px ≈ ۱۰ پیکسل به ازای هر نویسه، متا ۱۴px ≈ ۶٫۶).
 */
function hodima_ad_tpx( int $len ): int {
	return $len * 10;
}
function hodima_ad_mpx( int $len ): int {
	return (int) round( $len * 6.6 );
}

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

// آزمون‌ها در ۸ بخش جدا (SEO 3.8.7؛ قبلا یک فایل ۵۱۰۰ خطی): به همین ترتیب و در همین دامنه سراسری اجرا می‌شوند؛
// هر بخش به متغیرهای بخش‌های قبلی تکیه دارد، پس ترتیب را عوض نکنید.
require __DIR__ . '/advisor-tests/01-store-fetch.php';
require __DIR__ . '/advisor-tests/02-store-safety-rules.php';
require __DIR__ . '/advisor-tests/03-inspect-terms-tasks.php';
require __DIR__ . '/advisor-tests/04-live-marks-verify.php';
require __DIR__ . '/advisor-tests/05-tasks-crawler.php';
require __DIR__ . '/advisor-tests/06-crawl-issues-admin.php';
require __DIR__ . '/advisor-tests/07-score-overview.php';
require __DIR__ . '/advisor-tests/08-pixels-guides.php';

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
