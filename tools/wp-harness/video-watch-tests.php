<?php
/**
 * آزمون خودکار آمار ماژول ویدیوها (Hodima Media، components/video-watch) روی وردپرس ابزار تست.
 *   HARNESS=1 php tools/wp-harness/video-watch-tests.php
 * خروجی: هر آزمون ✔/✘؛ کد خروج ۱ اگر یکی رد شود. ci-check.sh هم اجرا می‌کند.
 * رفتار پلیر (شمارش فقط با تماشای واقعی): video-watch-player.mjs.
 *
 * باگ‌های رفع‌شده در HODIMA-AUDIT.md بخش ۲۱۶ را قفل می‌کند: بازدید ویدیوی
 * منتشرنشده، جعل بازدید با عوض کردن نام مرورگر، کلید ردشده کاربر واردشده،
 * از دست رفتن صف در خطای دیتابیس، زمان‌بندی‌ها و حذف «امتیاز محبوبیت».
 */

declare(strict_types=1);

$_SERVER['HTTP_HOST']       = 'hodima.test';
$_SERVER['SERVER_NAME']     = 'hodima.test';
$_SERVER['REQUEST_URI']     = '/';
$_SERVER['HTTPS']           = 'on';
$_SERVER['REMOTE_ADDR']     = '203.0.113.20';
$_SERVER['HTTP_USER_AGENT'] = 'HarnessBrowser/1.0';
require rtrim( (string) ( getenv( 'HODIMA_WP' ) ?: '/tmp/hodima-harness/wp' ), '/' ) . '/wp-load.php';

$failed = 0;
$passed = 0;

/** یک آزمون: مقدار واقعی === مقدار مورد انتظار. */
function hodima_vw( string $name, mixed $actual, mixed $expected ): void {
	global $failed, $passed;
	if ( $actual === $expected ) {
		++$passed;
		echo "  ✔ {$name}\n";
		return;
	}
	++$failed;
	echo "  ✘ {$name}\n      انتظار: " . var_export( $expected, true ) . "\n      واقعی:  " . var_export( $actual, true ) . "\n";
}

if ( ! class_exists( Hodima_Video_Watch_Analytics::class ) ) {
	echo "✘ ماژول ویدیوها لود نشده است\n";
	exit( 1 );
}

global $wpdb;
$stats = $wpdb->prefix . 'video_watch_stats';
$queue = $wpdb->prefix . 'video_watch_counter_queue';
$lock  = $wpdb->prefix . 'video_watch_flush_lock';

/** درخواست ثبت تعامل. @return array{0: int, 1: mixed} وضعیت و داده پاسخ. */
function hodima_vw_track( int $id, string $action, ?string $token = null ): array {
	$req = new WP_REST_Request( 'POST', '/video-watch/v1/track' );
	$req->set_header( 'X-HVW-Nonce', $token ?? Hodima_Video_Watch_Analytics::token() );
	$req->set_param( 'id', $id );
	$req->set_param( 'action', $action );
	$res = rest_do_request( $req );
	return [ $res->get_status(), $res->get_data() ];
}

/** پاک کردن شمارنده‌های موقت (تکراری، سقف IP، محدودیت نرخ، کش عدد). */
function hodima_vw_reset_transients(): void {
	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_%hvw\_%' OR option_name LIKE '\_transient\_timeout\_%hvw\_%'" );
	wp_cache_flush();
}

// قفل جمع‌بندی SELECT … FOR UPDATE است که SQLite ابزار تست ندارد (سایت MySQL است): فقط همان عبارت برداشته می‌شود
$no_lock = static fn( string $q ): string => str_replace( ' FOR UPDATE', '', $q );

echo "=== ارتقای جدول از DB 1.2.0: حذف «امتیاز محبوبیت»\n";
$wpdb->query( "DROP TABLE IF EXISTS {$stats}" );
$wpdb->query(
	"CREATE TABLE {$stats} (
	video_id bigint(20) unsigned NOT NULL,
	views bigint(20) unsigned NOT NULL DEFAULT 0,
	shares bigint(20) unsigned NOT NULL DEFAULT 0,
	popularity_score double NOT NULL DEFAULT 0,
	status tinyint(1) NOT NULL DEFAULT 1,
	last_updated datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (video_id),
	KEY popularity_idx (popularity_score, status)
	)"
);
$wpdb->insert( $stats, [ 'video_id' => 999, 'views' => 7, 'shares' => 1, 'popularity_score' => 30 ] );
update_option( Hodima_Video_Watch_Setup::VERSION_OPTION, '1.2.0', false );
wp_clear_scheduled_hook( Hodima_Video_Watch_Setup::FLUSH_EVENT );
wp_schedule_event( time(), 'daily', Hodima_Video_Watch_Setup::LEGACY_DECAY_EVENT );
Hodima_Video_Watch_Setup::check_version();
$cols = array_keys( (array) $wpdb->get_row( "SELECT * FROM {$stats} WHERE video_id = 999", ARRAY_A ) );
hodima_vw( 'ستون popularity_score حذف شد', in_array( 'popularity_score', $cols, true ), false );
hodima_vw( 'آمار قبلی ماند', (int) $wpdb->get_var( "SELECT views FROM {$stats} WHERE video_id = 999" ), 7 );
hodima_vw( 'نسخه جدول 1.3.0', get_option( Hodima_Video_Watch_Setup::VERSION_OPTION ), Hodima_Video_Watch_Setup::DB_VERSION );
hodima_vw( 'رویداد روزانه قدیمی پاک شد', wp_next_scheduled( Hodima_Video_Watch_Setup::LEGACY_DECAY_EVENT ), false );
hodima_vw( 'جمع‌بندی ۵ دقیقه‌ای زمان‌بندی شد', 'five_minutes' === wp_get_schedule( Hodima_Video_Watch_Setup::FLUSH_EVENT ), true );
$wpdb->delete( $stats, [ 'video_id' => 999 ] );

echo "=== زمان‌بندی: تعویض قالب پاکش نمی‌کند، غیرفعال‌سازی افزونه پاک می‌کند\n";
do_action( 'switch_theme', 'x', new WP_Theme( 'x', get_theme_root() ), new WP_Theme( 'y', get_theme_root() ) );
hodima_vw( 'بعد از تعویض قالب هنوز هست', (bool) wp_next_scheduled( Hodima_Video_Watch_Setup::FLUSH_EVENT ), true );
wp_schedule_event( time(), 'daily', Hodima_Video_Watch_Setup::LEGACY_DECAY_EVENT );
$rewrite = get_option( 'rewrite_rules' );
do_action( 'deactivate_' . plugin_basename( HODIMA_MEDIA_FILE ) );
update_option( 'rewrite_rules', $rewrite );
hodima_vw( 'غیرفعال‌سازی: جمع‌بندی پاک شد', wp_next_scheduled( Hodima_Video_Watch_Setup::FLUSH_EVENT ), false );
hodima_vw( 'غیرفعال‌سازی: رویداد قدیمی پاک شد', wp_next_scheduled( Hodima_Video_Watch_Setup::LEGACY_DECAY_EVENT ), false );
Hodima_Video_Watch_Setup::check_version();
hodima_vw( 'پیشخوان زمان‌بندی را دوباره می‌سازد', (bool) wp_next_scheduled( Hodima_Video_Watch_Setup::FLUSH_EVENT ), true );

$wpdb->query( "DELETE FROM {$queue}" );
$wpdb->query( "DELETE FROM {$stats}" );
hodima_vw_reset_transients();
wp_set_current_user( 0 );

$pub   = wp_insert_post( [ 'post_type' => 'video', 'post_title' => 'آزمون آمار', 'post_status' => 'publish' ] );
$draft = wp_insert_post( [ 'post_type' => 'video', 'post_title' => 'پیش‌نویس', 'post_status' => 'draft' ] );
$priv  = wp_insert_post( [ 'post_type' => 'video', 'post_title' => 'خصوصی', 'post_status' => 'private' ] );
$page  = wp_insert_post( [ 'post_type' => 'page', 'post_title' => 'برگه', 'post_status' => 'publish' ] );

echo "=== فقط ویدیوی منتشرشده\n";
hodima_vw( 'پیش‌نویس رد می‌شود', hodima_vw_track( $draft, 'views' )[0], 422 );
hodima_vw( 'خصوصی رد می‌شود', hodima_vw_track( $priv, 'views' )[0], 422 );
hodima_vw( 'برگه رد می‌شود', hodima_vw_track( $page, 'views' )[0], 422 );
hodima_vw( 'نوع ناشناخته رد می‌شود', hodima_vw_track( $pub, 'likes' )[0], 422 );
hodima_vw( 'صفی برای این‌ها ساخته نشد', (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$queue}" ), 0 );

echo "=== کلید ثبت مستقل از کاربر\n";
wp_set_current_user( 1 );
$page_token = Hodima_Video_Watch_Analytics::token();
$wp_nonce   = wp_create_nonce( 'hvw_track_action' );
wp_set_current_user( 0 ); // REST بدون X-WP-Nonce کاربر را مهمان می‌کند
[ $status, $data ] = hodima_vw_track( $pub, 'views', $page_token );
hodima_vw( 'کلید صفحه کاربر واردشده پذیرفته می‌شود', $status, 200 );
hodima_vw( 'بازدید اول شمرده شد', is_array( $data ) ? $data['counted'] : null, true );
hodima_vw( 'عدد بازدید = ۱', is_array( $data ) ? $data['views'] : null, 1 );
hodima_vw( 'nonce قدیمی وردپرس (کاربر واردشده) رد می‌شود', hodima_vw_track( $pub, 'views', $wp_nonce )[0], 403 );
hodima_vw( 'کلید خالی رد می‌شود', hodima_vw_track( $pub, 'views', '' )[0], 403 );
hodima_vw( 'کلید دوره قبل هنوز معتبر', Hodima_Video_Watch_Analytics::verify_token( Hodima_Video_Watch_Analytics::token( 1 ) ), true );
hodima_vw( 'کلید دو دوره قبل منقضی', Hodima_Video_Watch_Analytics::verify_token( Hodima_Video_Watch_Analytics::token( 2 ) ), false );

$nonce_res = rest_do_request( new WP_REST_Request( 'GET', '/video-watch/v1/nonce' ) );
$fresh     = (array) $nonce_res->get_data();
hodima_vw( 'مسیر کلید تازه: کلید معتبر', Hodima_Video_Watch_Analytics::verify_token( (string) ( $fresh['nonce'] ?? '' ) ), true );
hodima_vw( 'مسیر کلید تازه: کش نمی‌شود', str_contains( (string) ( $nonce_res->get_headers()['Cache-Control'] ?? '' ), 'no-store' ), true );

echo "=== تکراری و سقف روزانه هر IP\n";
[ , $data ] = hodima_vw_track( $pub, 'views' );
hodima_vw( 'همان IP و مرورگر: تکراری', is_array( $data ) ? $data['counted'] : null, false );
$counted = 1;
for ( $i = 0; $i < 30; $i++ ) {
	$_SERVER['HTTP_USER_AGENT'] = 'FakeBrowser/' . $i;
	[ , $data ]                 = hodima_vw_track( $pub, 'views' );
	$counted                   += ( is_array( $data ) && true === $data['counted'] ) ? 1 : 0;
}
hodima_vw( 'عوض کردن نام مرورگر: حداکثر سقف روزانه', $counted, Hodima_Video_Watch_Analytics::IP_DAILY_CAP );
// IP در هر درخواست یک بار خوانده و نگه داشته می‌شود (hodima_get_client_ip)؛ IP دیگر مستقیم
hodima_vw( 'IP دیگر جدا شمرده می‌شود', Hodima_Video_Watch_Analytics::accept( $pub, Hodima_Video_Watch_Counter::Views, '203.0.113.21' ), true );
[ , $data ] = hodima_vw_track( $pub, 'shares' );
hodima_vw( 'اشتراک شمارنده جدا دارد', is_array( $data ) ? $data['counted'] : null, true );
hodima_vw( 'صف: بازدید', (int) $wpdb->get_var( $wpdb->prepare( "SELECT delta FROM {$queue} WHERE video_id = %d AND counter_type = 'views'", $pub ) ), Hodima_Video_Watch_Analytics::IP_DAILY_CAP );

echo "=== محدودیت نرخ\n";
hodima_vw_reset_transients();
$codes = [];
for ( $i = 0; $i < Hodima_Video_Watch_Analytics::RATE_PER_MINUTE + 1; $i++ ) {
	$codes[] = hodima_vw_track( $pub, 'views' )[0];
}
hodima_vw( 'درخواست‌های مجاز', count( array_filter( $codes, static fn( int $c ): bool => 200 === $c ) ), Hodima_Video_Watch_Analytics::RATE_PER_MINUTE );
hodima_vw( 'درخواست اضافه: ۴۲۹', end( $codes ), 429 );
// بعد از پاک شدن شمارنده‌ها همان بازدیدکننده (روز تازه) یک بار دیگر شمرده شد: سقف + ۱

echo "=== جمع‌بندی: خطای دیتابیس صف را پاک نمی‌کند\n";
$queued = (int) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(delta) FROM {$queue} WHERE video_id = %d", $pub ) );
$wpdb->query( "UPDATE {$lock} SET locked_at = '2000-01-01 00:00:00'" );
$break_insert = static fn( string $q ): string => str_starts_with( ltrim( $q ), 'INSERT INTO `' . $GLOBALS['wpdb']->prefix . 'video_watch_stats`' )
	? str_replace( 'video_watch_stats`', 'video_watch_stats_missing`', $q )
	: $q;
add_filter( 'query', $no_lock );
add_filter( 'query', $break_insert );
$prev = $wpdb->suppress_errors( true );
$log  = ini_set( 'error_log', '/dev/null' );
Hodima_Video_Watch_Analytics::flush_counters();
ini_set( 'error_log', (string) $log );
$wpdb->suppress_errors( $prev );
remove_filter( 'query', $break_insert );
hodima_vw( 'صف دست‌نخورده ماند', (int) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(delta) FROM {$queue} WHERE video_id = %d", $pub ) ), $queued );
hodima_vw( 'قفل برگشت (اجرای بعدی منتظر نمی‌ماند)', (string) $wpdb->get_var( "SELECT locked_at FROM {$lock}" ), '2000-01-01 00:00:00' );

Hodima_Video_Watch_Analytics::flush_counters();
hodima_vw( 'جمع‌بندی موفق: صف خالی شد', (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$queue}" ), 0 );
hodima_vw( 'جمع‌بندی موفق: بازدید به جدول آمار رفت', (int) $wpdb->get_var( $wpdb->prepare( "SELECT views FROM {$stats} WHERE video_id = %d", $pub ) ), Hodima_Video_Watch_Analytics::IP_DAILY_CAP + 1 );
hodima_vw( 'جمع‌بندی موفق: اشتراک', (int) $wpdb->get_var( $wpdb->prepare( "SELECT shares FROM {$stats} WHERE video_id = %d", $pub ) ), 1 );
delete_transient( 'hvw_total_views_calc_' . $pub );
hodima_vw( 'عدد کل همان قبل از جمع‌بندی', Hodima_Video_Watch_Analytics::get_total_views( $pub ), Hodima_Video_Watch_Analytics::IP_DAILY_CAP + 1 );

Hodima_Video_Watch_Analytics::record_interaction( $pub, Hodima_Video_Watch_Counter::Views );
Hodima_Video_Watch_Analytics::flush_counters();
hodima_vw( 'اجرای دوباره زیر ۲۹۰ ثانیه: صبر (صف ماند)', (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$queue}" ), 1 );
remove_filter( 'query', $no_lock );

echo "=== جمع‌بندی دستی (/cron)\n";
$cron = rest_do_request( new WP_REST_Request( 'GET', '/video-watch/v1/cron' ) );
hodima_vw( 'مهمان بدون کلید: ۴۰۳', $cron->get_status(), 403 );

echo "=== حذف ویدیو آمارش را پاک می‌کند\n";
wp_delete_post( $pub, true );
hodima_vw( 'آمار حذف شد', (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$stats} WHERE video_id = %d", $pub ) ), 0 );
hodima_vw( 'صف حذف شد', (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$queue} WHERE video_id = %d", $pub ) ), 0 );

foreach ( [ $draft, $priv, $page ] as $id ) {
	wp_delete_post( $id, true );
}
hodima_vw_reset_transients();

echo "\n" . ( $failed ? "✘ {$failed} آزمون رد شد ({$passed} قبول)" : "✔ همه {$passed} آزمون قبول" ) . "\n";
exit( $failed ? 1 : 0 );
