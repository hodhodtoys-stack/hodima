<?php
/**
 * Google Indexing API — Bootstrap
 * Path: components/google-indexing-api/main.php
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'HODIMA_GI_VERSION', '11.1.0' );
define( 'HODIMA_GI_OPTION_JSON', 'hodima_gi_json_key' );
define( 'HODIMA_GI_OPTION_SETTINGS', 'hodima_gi_settings' );
define( 'HODIMA_GI_DIR', __DIR__ );

// Core
require_once HODIMA_GI_DIR . '/core/db-schema.php';
require_once HODIMA_GI_DIR . '/core/db-queries.php';
require_once HODIMA_GI_DIR . '/core/helper.php';
require_once HODIMA_GI_DIR . '/core/logger.php';
require_once HODIMA_GI_DIR . '/core/queue.php';
require_once HODIMA_GI_DIR . '/core/hooks.php';

// Modules
require_once HODIMA_GI_DIR . '/modules/router-pruning.php';
require_once HODIMA_GI_DIR . '/modules/etag-handler.php';
require_once HODIMA_GI_DIR . '/modules/bot-detector.php';
require_once HODIMA_GI_DIR . '/modules/cluster-sitemap.php';
require_once HODIMA_GI_DIR . '/modules/google-api.php';
require_once HODIMA_GI_DIR . '/modules/tools.php';
require_once HODIMA_GI_DIR . '/modules/export-csv.php';

Hodima_GI_Queue::init();
Hodima_GI_Hooks::init();

if ( is_admin() ) {
	require_once HODIMA_GI_DIR . '/admin/admin-ui.php';
	new Hodima_GI_Admin_UI();
}

/* =====================================================================
 * زمان‌بندی‌های cron
 * ---------------------------------------------------------------------
 * «minute» هنوز ثبت می‌شود فقط برای اینکه رویداد تکرارشونده قدیمی — تا
 * زمانی که پاک شود — خطای «زمان‌بندی نامعتبر» ندهد. صف دیگر از آن
 * استفاده نمی‌کند.
 * ===================================================================== */
add_filter( 'cron_schedules', static function ( $schedules ) {
	$schedules = (array) $schedules;
	if ( ! isset( $schedules['minute'] ) ) {
		$schedules['minute'] = [ 'interval' => 60, 'display' => 'Every Minute' ];
	}
	return $schedules;
} );

/* =====================================================================
 * نصب و ارتقا — فقط در پیشخوان، فقط یک بار برای هر نسخه
 * ===================================================================== */
add_action( 'admin_init', 'hodima_gi_maybe_upgrade' );

function hodima_gi_maybe_upgrade(): void {

	if ( get_option( 'hodima_gi_db_version' ) === HODIMA_GI_VERSION ) {
		return;
	}

	Hodima_Crawler_DB_Schema::build_table();

	/*
	 * رویدادهای یتیم نسخه‌های قبلی.
	 *
	 * «wpgi_google_process_queue» همان رویدادی است که «سلامت سایت»
	 * وردپرس گزارش می‌داد. هوک آن در کد فعلی وجود ندارد — از یک نسخه
	 * قدیمی ماژول در گزینه cron باقی مانده، هیچ کاری نمی‌کند، ولی چون
	 * هر دقیقه‌ای بود و با کش لایت‌اسپید مدام عقب می‌افتاد، وردپرس آن را
	 * به عنوان رویداد ناموفق نشان می‌داد.
	 *
	 * رویداد تکرارشونده hodima_gi_process_queue هم پاک می‌شود؛ صف حالا
	 * فقط رویداد یک‌باره و به‌هنگام زمان‌بندی می‌کند.
	 */
	foreach ( [ 'wpgi_google_process_queue', 'wpgi_process_queue', 'wpgi_daily_cleanup', Hodima_GI_Queue::HOOK ] as $legacy_hook ) {
		wp_clear_scheduled_hook( $legacy_hook );
	}

	hodima_gi_rehash_queue();

	// اگر صف کار دارد، رویداد یک‌باره مناسب زمان‌بندی شود
	Hodima_GI_Queue::schedule_next();

	// قوانین بازنویسی سایت‌مپ خوشه‌ها — بعد از ثبت همه قوانین (پایین‌تر)
	update_option( 'hodima_gi_needs_flush', 1, true );

	update_option( 'hodima_gi_db_version', HODIMA_GI_VERSION, true );
}

/* =====================================================================
 * بازنویسی قوانین
 * ---------------------------------------------------------------------
 * نسخه قبلی flush_rewrite_rules() را روی after_setup_theme صدا می‌زد —
 * *قبل از* init، یعنی قبل از اینکه ووکامرس نوع پست محصول و روتر قالب
 * قوانین سفارشی‌اش را ثبت کنند. قوانین ساخته‌شده ناقص بودند و تا ذخیره
 * دستی پیوندهای یکتا همان‌طور می‌ماندند. حالا در انتهای init، بعد از
 * ثبت همه قوانین.
 * ===================================================================== */
add_action( 'init', static function (): void {
	if ( get_option( 'hodima_gi_needs_flush' ) ) {
		delete_option( 'hodima_gi_needs_flush' );
		flush_rewrite_rules( false );
	}
}, 999 );

/* =====================================================================
 * هرس روزانه — ساعت ۲ بامداد به وقت سایت (نه سرور)
 * ===================================================================== */
add_action( 'init', static function (): void {

	if ( wp_next_scheduled( 'hodima_gi_daily_pruning' ) ) {
		return;
	}

	$run = new DateTimeImmutable( 'tomorrow 02:00', wp_timezone() );
	wp_schedule_event( $run->getTimestamp(), 'daily', 'hodima_gi_daily_pruning' );
}, 20 );

/*
 * خاموش شدن ارسال → رویداد صف هم پاک شود.
 * روشن شدن → اگر صف کار دارد، زمان‌بندی شود.
 */
add_action( 'update_option_' . HODIMA_GI_OPTION_SETTINGS, static function ( $old, $new ): void {
	if ( empty( $new['enable_google'] ) ) {
		wp_clear_scheduled_hook( Hodima_GI_Queue::HOOK );
	} else {
		Hodima_GI_Queue::schedule_next();
	}
}, 10, 2 );

/**
 * بازسازی کلید آیتم‌های موجود صف با الگوریتم نرمال جدید.
 *
 * بدون این، آدرسی که قبلا در صف بود و دوباره وارد می‌شد با کلید جدید
 * ردیف دوم می‌ساخت و دو بار به گوگل ارسال می‌شد. صف کوچک است، پس یک
 * بار در ارتقا کل آن پردازش می‌شود.
 */
function hodima_gi_rehash_queue(): void {

	global $wpdb;
	$table = Hodima_Crawler_DB_Schema::get_queue_table();
	$rows  = $wpdb->get_results( "SELECT id, url, type FROM {$table} ORDER BY execute_at DESC LIMIT 5000", ARRAY_A );
	$seen  = [];

	foreach ( (array) $rows as $row ) {

		$hash = Hodima_GI_Helper::url_hash( (string) $row['url'] );
		$key  = $hash . '|' . $row['type'];

		if ( isset( $seen[ $key ] ) ) {
			$wpdb->delete( $table, [ 'id' => (int) $row['id'] ] );
			continue;
		}

		$seen[ $key ] = true;

		// ردیف دیگری که هنوز پردازش نشده ممکن است همین کلید را داشته باشد
		// (آدرس لاتین که کلیدش تغییر نمی‌کند). تداخل کلید یکتا = تکراری.
		$other = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$table} WHERE url_hash = %s AND type = %s AND id <> %d LIMIT 1",
			$hash, $row['type'], (int) $row['id']
		) );

		if ( $other ) {
			$wpdb->delete( $table, [ 'id' => (int) $other ] );
		}

		$wpdb->update( $table, [ 'url_hash' => $hash ], [ 'id' => (int) $row['id'] ] );
	}
}
