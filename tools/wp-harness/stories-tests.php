<?php
/**
 * آزمون خودکار ماژول استوری (Hodima Media) روی وردپرس ابزار تست.
 *   HARNESS=1 php tools/wp-harness/stories-tests.php
 * خروجی: هر آزمون ✔/✘؛ کد خروج ۱ اگر یکی رد شود. ci-check.sh هم اجرا می‌کند.
 *
 * باگ‌های رفع‌شده در HODIMA-AUDIT.md بخش ۱۲۷ را قفل می‌کند: ذخیره بدون حذف
 * بی‌صدای ردیف‌ها، حذف آمار فقط برای استوری حذف‌شده، فرم ناقص، nonce کهنه
 * صفحه کش‌شده، بازسازی زمان‌بندی‌ها، و خروجی نوار استوری.
 */

declare(strict_types=1);

use Hodima\Stories\Admin;
use Hodima\Stories\Analytics\WP_Story_Analytics;
use Hodima\Stories\Analytics\WP_Story_Setup;
use Hodima\Stories\Analytics_API;
use Hodima\Stories\Core;
use Hodima\Stories\Frontend;
use Hodima\Stories\Helpers;

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
function hodima_st( string $name, mixed $actual, mixed $expected ): void {
	global $failed, $passed;
	if ( $actual === $expected ) {
		++$passed;
		echo "  ✔ {$name}\n";
		return;
	}
	++$failed;
	echo "  ✘ {$name}\n      انتظار: " . var_export( $expected, true ) . "\n      واقعی:  " . var_export( $actual, true ) . "\n";
}

if ( ! class_exists( Core::class ) ) {
	echo "✘ ماژول استوری لود نشده است\n";
	exit( 1 );
}

/** توقف ریدایرکت/wp_die بدون exit. */
final class Hodima_Harness_Stop extends Exception {}

add_filter(
	'wp_redirect',
	static function ( string $location ): string {
		throw new Hodima_Harness_Stop( $location );
	}
);

wp_set_current_user( 1 );
WP_Story_Setup::install_tables();

// سیستم پیام مشترک Core فقط در پیشخوان (is_admin) لود می‌شود
if ( ! function_exists( 'hodima_admin_flash' ) ) {
	require_once WP_PLUGIN_DIR . '/hodima-core/includes/admin-ui.php';
}

/** پیام‌های flash ثبت‌شده (و پاک کردنشان). @return list<array{0: string, 1: string}> */
function hodima_st_flash(): array {
	$key   = 'hodima_admin_flash_' . get_current_user_id();
	$queue = get_transient( $key );
	delete_transient( $key );
	return is_array( $queue ) ? array_values( $queue ) : [];
}

/**
 * ذخیره فرم پنل.
 *
 * @param list<array<string, string>> $rows ردیف‌ها.
 */
function hodima_st_save( array $rows, ?int $count = null ): void {
	$_POST = [
		'hodima_stories_nonce' => wp_create_nonce( 'hodima_stories_save' ),
		'hodima_stories'       => $rows,
		'hodima_stories_count' => (string) ( $count ?? count( $rows ) ),
	];
	try {
		Admin::handle_save();
	} catch ( Hodima_Harness_Stop ) {
		// ریدایرکت پایان عادی ذخیره است
	}
	$_POST = [];
}

/** @return list<string> */
function hodima_st_types( array $flash ): array {
	return array_map( static fn( array $f ): string => (string) $f[0], $flash );
}

$v = static fn( string $name ): string => "https://hodima.test/wp-content/uploads/{$name}";

echo "=== تشخیص ردیف مشکل‌دار\n";
hodima_st(
	'لینک غیر mp4 = invalid، ویدیوی تکراری (با query متفاوت) = duplicate',
	Helpers::problems(
		[
			[ 'link' => $v( 'a.mp4' ) ],
			[ 'link' => $v( 'b.webm' ) ],
			[ 'link' => $v( 'a.mp4?x=1' ) ],
			[ 'link' => $v( 'c.mp4' ) ],
			[ 'link' => '' ],
		]
	),
	[
		1 => 'invalid',
		2 => 'duplicate',
		4 => 'invalid',
	]
);
hodima_st( 'تصویر جایگزین data: پس از پاک‌سازی خالی نمی‌شود', str_starts_with( Helpers::esc_img_src( Helpers::get_placeholder_cover() ), 'data:image/svg+xml,' ), true );

echo "=== ذخیره: هیچ ردیفی بی‌صدا حذف نمی‌شود\n";
delete_option( Core::OPTION_NAME );
hodima_st_flash();
hodima_st_save(
	[
		[ 'uid' => '', 'title' => 'A', 'link' => $v( 'a.mp4' ), 'cover_id' => '', 'cta_link' => '', 'cta_text' => '' ],
		[ 'uid' => '', 'title' => 'B', 'link' => $v( 'b.webm' ), 'cover_id' => '', 'cta_link' => '', 'cta_text' => '' ],
		[ 'uid' => '', 'title' => 'C', 'link' => $v( 'a.mp4' ), 'cover_id' => '', 'cta_link' => '', 'cta_text' => '' ],
		[ 'uid' => '', 'title' => '', 'link' => '', 'cover_id' => '', 'cta_link' => '', 'cta_text' => '' ],
		[ 'uid' => '', 'title' => 'D', 'link' => $v( 'd.mp4' ), 'cover_id' => '', 'cta_link' => 'javascript:alert(1)', 'cta_text' => 'خرید' ],
	]
);
$items = Core::items();
$uids  = array_column( $items, 'uid' );
hodima_st( 'ردیف نامعتبر و تکراری می‌مانند، ردیف خالی نه', array_column( $items, 'title' ), [ 'A', 'B', 'C', 'D' ] );
hodima_st( 'شناسه‌ها تازه، یکتا و غیرصفر', count( array_unique( array_filter( $uids ) ) ), 4 );
hodima_st( 'لینک محصول نامعتبر خالی شد', $items[3]['cta_link'], '' );
$flash = hodima_st_flash();
hodima_st( 'پیام‌ها: موفق + هشدار لینک نامعتبر + تکراری + لینک محصول', hodima_st_types( $flash ), [ 'success', 'warning', 'warning', 'warning' ] );
hodima_st( 'هشدار شماره ردیف درست را می‌گوید', str_contains( (string) $flash[1][1], number_format_i18n( 2 ) ), true );

echo "=== فرم ناقص: هیچ چیز ذخیره نمی‌شود\n";
hodima_st_save( [ [ 'uid' => (string) $uids[0], 'title' => 'A', 'link' => $v( 'a.mp4' ) ] ], 10 );
hodima_st( 'فهرست دست نخورد', count( Core::items() ), 4 );
hodima_st( 'پیام خطا', hodima_st_types( hodima_st_flash() ), [ 'error' ] );

echo "=== آمار: ثبت، تکرار همان روز، صف و جمع‌بندی\n";
[ $uid_a, $uid_b, $uid_c, $uid_d ] = $uids;
hodima_st( 'بازدید اول ثبت شد', WP_Story_Analytics::record_view( $uid_a ), true );
hodima_st( 'بازدید دوم همان بازدیدکننده همان روز ثبت نشد', WP_Story_Analytics::record_view( $uid_a ), false );
$_SERVER['HTTP_USER_AGENT'] = 'OtherBrowser/2.0';
hodima_st( 'بازدیدکننده دیگر ثبت شد', WP_Story_Analytics::record_view( $uid_a ), true );
hodima_st( 'بازدید استوری D', WP_Story_Analytics::record_view( $uid_d ), true );
$_SERVER['HTTP_USER_AGENT'] = 'HarnessBrowser/1.0';
hodima_st( 'آمار از صف (پیش از جمع‌بندی)', WP_Story_Analytics::get_stats_bulk( [ $uid_a ] )[ $uid_a ]['views'], 2 );
global $wpdb;
// قفل جمع‌بندی SELECT … FOR UPDATE است که SQLite ابزار تست ندارد (سایت MySQL است): فقط همان عبارت برداشته می‌شود، بقیه منطق واقعی اجرا می‌شود
$hodima_no_lock = static fn( string $q ): string => str_replace( ' FOR UPDATE', '', $q );
add_filter( 'query', $hodima_no_lock );
WP_Story_Analytics::flush_counters();
remove_filter( 'query', $hodima_no_lock );
hodima_st( 'جمع‌بندی: صف خالی شد', (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}hodima_story_counter_queue" ), 0 );
hodima_st( 'جمع‌بندی: عدد به جدول اصلی رفت', (int) $wpdb->get_var( $wpdb->prepare( "SELECT views FROM {$wpdb->prefix}hodima_story_stats WHERE story_id = %d", $uid_a ) ), 2 );
hodima_st( 'جمع‌بندی: آمار نمایشی همان ماند', WP_Story_Analytics::get_stats_bulk( [ $uid_a ] )[ $uid_a ]['views'], 2 );
hodima_st( 'جمع‌بندی: قفل آزاد شد', (string) $wpdb->get_var( "SELECT locked_at FROM {$wpdb->prefix}hodima_story_flush_lock" ), '2000-01-01 00:00:00' );

echo "=== حذف: فقط آمار استوری حذف‌شده پاک می‌شود\n";
hodima_st_save(
	[
		[ 'uid' => (string) $uid_a, 'title' => 'A', 'link' => $v( 'a.mp4' ) ],
		[ 'uid' => (string) $uid_b, 'title' => 'B', 'link' => $v( 'b.webm' ) ],
		[ 'uid' => (string) $uid_c, 'title' => 'C', 'link' => $v( 'a.mp4' ) ],
	]
);
hodima_st_flash();
hodima_st( 'شناسه‌ها حفظ شدند', array_column( Core::items(), 'uid' ), [ $uid_a, $uid_b, $uid_c ] );
hodima_st( 'آمار A ماند', WP_Story_Analytics::get_stats_bulk( [ $uid_a ] )[ $uid_a ]['views'], 2 );
hodima_st( 'آمار D پاک شد', WP_Story_Analytics::get_stats_bulk( [ $uid_d ] )[ $uid_d ]['views'], 0 );

echo "=== زمان‌بندی‌ها خودشان دوباره ساخته می‌شوند\n";
WP_Story_Setup::clear_scheduled_events();
WP_Story_Setup::check_version();
hodima_st( 'جمع‌بندی روزانه', (bool) wp_next_scheduled( WP_Story_Setup::FLUSH_EVENT ), true );
hodima_st( 'پاک‌سازی روزانه', (bool) wp_next_scheduled( WP_Story_Setup::CLEANUP_EVENT ), true );
hodima_st( 'تعویض قالب زمان‌بندی را پاک نمی‌کند', has_action( 'switch_theme', [ WP_Story_Setup::class, 'clear_scheduled_events' ] ), false );

echo "=== ثبت بازدید از سایت (admin-ajax)\n";
add_filter( 'wp_doing_ajax', '__return_true' );
add_filter(
	'wp_die_ajax_handler',
	static fn(): Closure => static function (): void {
		throw new Hodima_Harness_Stop( 'die' );
	}
);
/** @return array<string, mixed> */
$ajax = static function ( array $post ): array {
	$_POST = $post;
	ob_start();
	try {
		Analytics_API::track_view();
	} catch ( Hodima_Harness_Stop ) {
		// پایان عادی wp_send_json
	}
	$_POST = [];
	return (array) json_decode( (string) ob_get_clean(), true );
};
wp_set_current_user( 0 );
$res = $ajax( [ 'security' => 'stale-nonce', 'story_id' => (string) $uid_b ] );
hodima_st( 'nonce کهنه: رد با کد nonce', [ $res['success'] ?? null, $res['data']['code'] ?? null ], [ false, 'nonce' ] );
hodima_st( 'nonce کهنه: nonce تازه معتبر برمی‌گردد', (bool) wp_verify_nonce( (string) ( $res['data']['nonce'] ?? '' ), Analytics_API::NONCE_ACTION ), true );
$res = $ajax( [ 'security' => (string) ( $res['data']['nonce'] ?? '' ), 'story_id' => (string) $uid_b ] );
hodima_st( 'با nonce تازه بازدید ثبت شد', [ $res['success'] ?? null, $res['data']['recorded'] ?? null ], [ true, true ] );
remove_all_filters( 'wp_doing_ajax' );
wp_set_current_user( 1 );

echo "=== نوار استوری سایت\n";
$html = Frontend::render_shortcode();
hodima_st( 'فقط استوری‌های معتبر و غیرتکراری نمایش داده می‌شوند', substr_count( $html, 'class="story-item"' ), 1 );
hodima_st( 'نشانگر فلش حذف شد', str_contains( $html, 'hs-scroll-indicator' ), false );
hodima_st( 'شناسه ثابت story-container نیست', str_contains( $html, 'id="story-container"' ), false );
hodima_st( 'کاور جایگزین src خالی نمی‌گیرد', (bool) preg_match( '/<img src="data:image\/svg\+xml,/', $html ), true );
ob_start();
\Hodima\Stories\StoryPlayer\Core::render_modal_html();
$modal = (string) ob_get_clean();
hodima_st( 'پنجره پخش دکمه صدا دارد', str_contains( $modal, 'hs-mute-toggle' ), true );
hodima_st( 'دکمه محصول با hidden پنهان است (نه style)', str_contains( $modal, 'id="hs-cta-box" class="hs-cta-box" hidden' ), true );

echo "\n" . ( $failed ? "✘ {$failed} آزمون رد شد، {$passed} قبول" : "✔ همه {$passed} آزمون استوری قبول" ) . "\n";
exit( $failed ? 1 : 0 );
