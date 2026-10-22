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

/** پاک کردن شمارنده‌های کوتاه‌مدت (تکراری، سقف IP، محدودیت نرخ) و کش عدد. */
function hodima_vw_reset_transients(): void {
	global $wpdb;
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_%hvw\_%' OR option_name LIKE '\_transient\_timeout\_%hvw\_%'" );
	$wpdb->query( 'DELETE FROM ' . Hodima_Video_Watch_Limits::table() );
	wp_cache_flush();
}

/** تعداد ردیف‌های wp_options. */
function hodima_vw_options_rows(): int {
	global $wpdb;
	return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options}" );
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
hodima_vw( 'ستون بی‌استفاده status حذف شد', in_array( 'status', $cols, true ), false );
hodima_vw( 'آمار قبلی ماند', (int) $wpdb->get_var( "SELECT views FROM {$stats} WHERE video_id = 999" ), 7 );
hodima_vw( 'نسخه جدول 1.5.0', get_option( Hodima_Video_Watch_Setup::VERSION_OPTION ), Hodima_Video_Watch_Setup::DB_VERSION );
hodima_vw( 'رویداد روزانه قدیمی پاک شد', wp_next_scheduled( Hodima_Video_Watch_Setup::LEGACY_DECAY_EVENT ), false );
hodima_vw( 'جدول شمارنده‌های کوتاه‌مدت ساخته شد', Hodima_Video_Watch_Limits::table() === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', Hodima_Video_Watch_Limits::table() ) ), true );
hodima_vw( 'جمع‌بندی ۵ دقیقه‌ای زمان‌بندی شد', 'five_minutes' === wp_get_schedule( Hodima_Video_Watch_Setup::FLUSH_EVENT ), true );
$wpdb->delete( $stats, [ 'video_id' => 999 ] );

echo "=== ارتقای جدول از DB 1.4.0: حذف ستون status\n";
$wpdb->query( "DROP TABLE IF EXISTS {$stats}" );
$wpdb->query(
	"CREATE TABLE {$stats} (
	video_id bigint(20) unsigned NOT NULL,
	views bigint(20) unsigned NOT NULL DEFAULT 0,
	shares bigint(20) unsigned NOT NULL DEFAULT 0,
	status tinyint(1) NOT NULL DEFAULT 1,
	last_updated datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (video_id)
	)"
);
$wpdb->insert( $stats, [ 'video_id' => 998, 'views' => 12, 'shares' => 3 ] );
update_option( Hodima_Video_Watch_Setup::VERSION_OPTION, '1.4.0', false );
Hodima_Video_Watch_Setup::check_version();
$row = (array) $wpdb->get_row( "SELECT * FROM {$stats} WHERE video_id = 998", ARRAY_A );
hodima_vw( '1.4.0: ستون status حذف شد', array_key_exists( 'status', $row ), false );
hodima_vw( '1.4.0: بازدید و اشتراک ماند', [ (int) ( $row['views'] ?? 0 ), (int) ( $row['shares'] ?? 0 ) ], [ 12, 3 ] );
hodima_vw( '1.4.0: نسخه جدول به‌روز شد', get_option( Hodima_Video_Watch_Setup::VERSION_OPTION ), Hodima_Video_Watch_Setup::DB_VERSION );
$wpdb->delete( $stats, [ 'video_id' => 998 ] );

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

echo "=== کارایی: ثبت بازدید در wp_options نمی‌نویسد (مرحله ۲)\n";
hodima_vw_reset_transients();
$before = hodima_vw_options_rows();
$_SERVER['HTTP_USER_AGENT'] = 'PerfBrowser/1.0';
[ , $data ] = hodima_vw_track( $pub, 'views' );
hodima_vw( 'بازدید تازه شمرده شد', is_array( $data ) ? $data['counted'] : null, true );
hodima_vw_track( $pub, 'views' );
hodima_vw_track( $pub, 'shares' );
Hodima_Video_Watch_Analytics::get_total_views( $pub );
hodima_vw( 'سه درخواست + خواندن عدد: صفر ردیف تازه در wp_options', hodima_vw_options_rows() - $before, 0 );
hodima_vw( 'هیچ transient آمار ویدیو', (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE '\_transient%hvw\_%' AND option_name NOT LIKE '%hvw\_videos\_page\_id'" ), 0 );
$limits = Hodima_Video_Watch_Limits::table();
// seen + ipcap برای بازدید، seen + ipcap برای اشتراک، یک ردیف نرخ دقیقه
hodima_vw( 'ردیف‌های جدول شمارنده‌ها', (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$limits}" ), 5 );
hodima_vw( 'شمارنده نرخ این دقیقه = ۳', (int) $wpdb->get_var( "SELECT hits FROM {$limits} WHERE expires_at = " . Hodima_Video_Watch_Limits::end_of_minute() ), 3 );
hodima_vw( 'کلیدها فقط هش (بدون IP خام)', (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$limits} WHERE seen_key LIKE '%203.0.113%'" ), 0 );
hodima_vw( 'انقضای روزانه = نیمه‌شب گرینویچ', (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$limits} WHERE expires_at = " . Hodima_Video_Watch_Limits::end_of_day() ), 4 );

$wpdb->insert( $limits, [ 'seen_key' => md5( 'old' ), 'hits' => 3, 'expires_at' => time() - 10 ] );
Hodima_Video_Watch_Limits::cleanup();
hodima_vw( 'پاک‌سازی: منقضی حذف شد', (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$limits} WHERE seen_key = %s", md5( 'old' ) ) ), 0 );
hodima_vw( 'پاک‌سازی: معتبرها ماندند', (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$limits}" ), 5 );
hodima_vw( 'پاک‌سازی به جمع‌بندی ۵ دقیقه‌ای وصل است', (bool) has_action( Hodima_Video_Watch_Setup::FLUSH_EVENT, [ Hodima_Video_Watch_Limits::class, 'cleanup' ] ), true );

echo "=== به‌روزرسانی خودکار بدون باز شدن پیشخوان: جدول تازه خودش ساخته می‌شود\n";
$wpdb->query( "DROP TABLE {$limits}" );
update_option( Hodima_Video_Watch_Setup::VERSION_OPTION, '1.3.0', false );
$_SERVER['HTTP_USER_AGENT'] = 'AfterUpdate/1.0';
$prev = $wpdb->suppress_errors( true );
[ $status, $data ] = hodima_vw_track( $pub, 'views' );
$wpdb->suppress_errors( $prev );
hodima_vw( 'درخواست سالم', $status, 200 );
hodima_vw( 'بازدید شمرده شد', is_array( $data ) ? $data['counted'] : null, true );
hodima_vw( 'جدول ساخته شد', $limits === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $limits ) ), true );
hodima_vw( 'نسخه جدول به‌روز شد', get_option( Hodima_Video_Watch_Setup::VERSION_OPTION ), Hodima_Video_Watch_Setup::DB_VERSION );

echo "=== کاور پلیر در اندازه مناسب (مرحله ۲)\n";
$upload = wp_get_upload_dir();
$att    = wp_insert_attachment( [ 'post_title' => 'کاور', 'post_mime_type' => 'image/jpeg', 'guid' => $upload['baseurl'] . '/2026/10/cover.jpg' ], '2026/10/cover.jpg' );
update_post_meta( $att, '_wp_attached_file', '2026/10/cover.jpg' );
wp_update_attachment_metadata(
	$att,
	[
		'width'  => 4000,
		'height' => 2250,
		'file'   => '2026/10/cover.jpg',
		'sizes'  => [
			'medium'    => [ 'file' => 'cover-300x169.jpg', 'width' => 300, 'height' => 169, 'mime-type' => 'image/jpeg' ],
			'large'     => [ 'file' => 'cover-1024x576.jpg', 'width' => 1024, 'height' => 576, 'mime-type' => 'image/jpeg' ],
			'1536x1536' => [ 'file' => 'cover-1536x864.jpg', 'width' => 1536, 'height' => 864, 'mime-type' => 'image/jpeg' ],
			'2048x2048' => [ 'file' => 'cover-2048x1152.jpg', 'width' => 2048, 'height' => 1152, 'mime-type' => 'image/jpeg' ],
		],
	]
);
$full = $upload['baseurl'] . '/2026/10/cover.jpg';
update_post_meta( $pub, '_hod_video_thumbnail', $full );
hodima_vw( 'کاور پیوست: اندازه ۱۵۳۶ به‌جای اصلی ۴۰۰۰', basename( hodima_video_watch_poster_url( $pub ) ), 'cover-1536x864.jpg' );
hodima_vw( 'شناسه پیوست با امضای آدرس نگه داشته شد', get_post_meta( $pub, '_hod_video_thumbnail_id', true ), md5( $full ) . ':' . $att );
$queries = $wpdb->num_queries;
hodima_video_watch_poster_id( $pub );
hodima_vw( 'بار دوم: بدون جست‌وجوی آدرس در متاها', $wpdb->num_queries - $queries, 0 );
update_post_meta( $pub, '_hod_video_thumbnail', 'https://cdn.example.com/c.jpg' );
hodima_vw( 'آدرس بیرونی: همان آدرس', hodima_video_watch_poster_url( $pub ), 'https://cdn.example.com/c.jpg' );
hodima_vw( 'آدرس عوض شد ← امضا تازه', get_post_meta( $pub, '_hod_video_thumbnail_id', true ), md5( 'https://cdn.example.com/c.jpg' ) . ':0' );
delete_post_meta( $pub, '_hod_video_thumbnail' );
set_post_thumbnail( $pub, $att );
hodima_vw( 'بدون کاور ← تصویر شاخص در همان اندازه', basename( hodima_video_watch_poster_url( $pub ) ), 'cover-1536x864.jpg' );
wp_delete_attachment( $att, true );

echo "=== مدت ویدیو (مرحله ۴)\n";
foreach ( [ 'PT1M10S' => 70, 'PT1M10.5S' => 70, 'PT1H2M3S' => 3723, '1:10' => 70, '۱:۰۲:۰۳' => 3723, '' => 0 ] as $in => $want ) {
	hodima_vw( "ثانیه «{$in}»", hod_video_duration_seconds( (string) $in ), $want );
}
hodima_vw( 'فرم: «۲:۳۵» ← ISO', hod_video_sanitize_duration( '۲:۳۵' ), 'PT2M35S' );
hodima_vw( 'فرم: ISO اعشاری دست‌نخورده', hod_video_sanitize_duration( 'PT1M30.5S' ), 'PT1M30.5S' );
hodima_vw( 'فرم: نمایش «PT1M30.5S» همان ISO (ذخیره دوباره اعشار را نمی‌برد)', hod_video_iso_to_clock( 'PT1M30.5S' ), 'PT1M30.5S' );

echo "=== کادر ویرایش (مرحله ۵): رفت‌وبرگشت فرم بدون تغییر داده\n";
/** فرم رندرشده را مثل مرورگر می‌فرستد (بیرون از <template>، کلید بدون name نه). @return array<string, mixed> */
function hodima_vw_form_post( string $html ): array {
	$doc = new DOMDocument();
	libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8"?><div>' . $html . '</div>' );
	libxml_clear_errors();
	$pairs = [];
	foreach ( ( new DOMXPath( $doc ) )->query( '//*[self::input or self::textarea][@name][not(ancestor::template)]' ) as $node ) {
		if ( ! $node instanceof DOMElement || $node->hasAttribute( 'disabled' ) ) {
			continue;
		}
		if ( in_array( $node->getAttribute( 'type' ), [ 'checkbox', 'radio' ], true ) && ! $node->hasAttribute( 'checked' ) ) {
			continue;
		}
		$pairs[] = rawurlencode( $node->getAttribute( 'name' ) ) . '=' . rawurlencode( 'textarea' === $node->tagName ? $node->textContent : $node->getAttribute( 'value' ) );
	}
	parse_str( implode( '&', $pairs ), $out );
	return $out;
}
$box_keys = [ '_hod_video_url', '_hod_video_thumbnail', '_hod_video_duration', '_hod_video_chapters', '_hod_video_transcript', '_hod_related_product_url', '_hod_related_links' ];
$box_meta = [
	'کامل'        => [
		'_hod_video_url'           => 'https://hodima.test/v/pin.mp4',
		'_hod_video_thumbnail'     => 'https://hodima.test/v/pin.jpg',
		'_hod_video_duration'      => 'PT1M10.5S',
		'_hod_video_chapters'      => "0:00 شروع\n0:30 جنس \"ویژه\"",
		'_hod_video_transcript'    => "سلام، این «گلسر» است.\n\nپاراگراف دوم با 'کوتیشن' و \\ بک‌اسلش.",
		'_hod_related_product_url' => 'https://hodima.test/product/x/',
		'_hod_related_links'       => [
			[ 'title' => 'گلسر "ویژه" و \'جدید\'', 'url' => 'https://hodima.test/a/', 'cover' => 'https://hodima.test/c.jpg' ],
			[ 'title' => 'کش مو', 'url' => 'https://hodima.test/b/', 'cover' => '' ],
		],
	],
	'مدت ساده'    => [ '_hod_video_url' => 'https://hodima.test/v/a.mp4', '_hod_video_duration' => 'PT2M35S' ],
	'خالی'        => [],
];
foreach ( $box_meta as $label => $meta ) {
	$box_id = wp_insert_post( [ 'post_type' => 'video', 'post_title' => 'کادر ' . $label, 'post_status' => 'publish' ] );
	foreach ( $meta as $k => $v ) {
		update_post_meta( $box_id, $k, wp_slash( $v ) );
	}
	$snapshot = static fn(): array => array_map( static fn( string $k ): mixed => get_post_meta( $box_id, $k, true ), array_combine( $box_keys, $box_keys ) );
	$before   = $snapshot();
	ob_start();
	hod_video_watch_render_meta_box( get_post( $box_id ) );
	$html = (string) ob_get_clean();
	$form = hodima_vw_form_post( $html );
	if ( 'کامل' === $label ) {
		hodima_vw( 'کادر: نام همه فیلدهای قبلی فرستاده می‌شود', array_values( array_diff( [ 'hod_video_url', 'hod_video_thumbnail', 'hod_video_duration', 'hod_video_chapters', 'hod_video_transcript', 'hod_related_product_url', 'hod_related_links', 'hod_video_watch_meta_nonce' ], array_keys( $form ) ) ), [] );
		hodima_vw( 'کادر: الگوی کارت تازه (<template>) فرستاده نمی‌شود', array_keys( (array) $form['hod_related_links'] ), [ 0, 1 ] );
		hodima_vw( 'کادر: فیلد آدرس ویدیو همان جعبه متن لینک (بدون کتابخانه رسانه)', (bool) preg_match( '/<input type="url" id="hod_video_url" name="hod_video_url"/', $html ), true );
		hodima_vw( 'کادر: هر کارت برچسب وصل به فیلد دارد', substr_count( $html, 'for="hvw-link-0-' ) + substr_count( $html, 'for="hvw-link-1-' ), 6 );
		hodima_vw( 'کادر: کلید دکمه محصول روشن (لینک دارد) و بدون name', (bool) preg_match( '/<input type="checkbox"[^>]*id="hvw-product-switch"(?![^>]*name=)[^>]*checked/', $html ), true );
	}
	$_POST = wp_slash( $form );
	hod_video_watch_save_meta_boxes( $box_id );
	$_POST = [];
	hodima_vw( "کادر ({$label}): ذخیره بدون تغییر هیچ متایی", array_keys( array_filter( $snapshot(), static fn( mixed $v, string $k ): bool => $v !== $before[ $k ], ARRAY_FILTER_USE_BOTH ) ), [] );
	wp_delete_post( $box_id, true );
}

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
