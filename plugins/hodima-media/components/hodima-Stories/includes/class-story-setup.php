<?php
/**
 * استوری‌ها — جدول‌های آمار، زمان‌بندی‌ها (cron) و ویجت پیشخوان.
 *
 * @package Hodima_Media
 */

declare(strict_types=1);

namespace Hodima\Stories\Analytics;

defined( 'ABSPATH' ) || exit;

/**
 * نصب و نگهداری جدول‌ها و کارهای زمان‌بندی‌شده آمار استوری.
 */
class WP_Story_Setup {

	/** نسخه ساختار جدول‌ها (در گزینه wp_story_db_version ذخیره می‌شود). */
	public const DB_VERSION = '4.1.0';

	/** نگهداری ردیف‌های تعامل (ماه). */
	public const RETENTION_MONTHS = 6;

	/** بیشترین ردیف صف در هر جمع‌بندی. */
	public const FLUSH_LIMIT = 500;

	/** بیشترین ردیف حذفی در هر دور پاک‌سازی. */
	public const CLEANUP_LIMIT = 5000;

	/** نام گزینه نسخه دیتابیس (عوض نشود). */
	public const VERSION_OPTION = 'wp_story_db_version';

	/** نام رویدادهای cron (عوض نشوند؛ روی سایت‌ها زمان‌بندی شده‌اند). */
	public const FLUSH_EVENT   = 'wp_story_flush_counters_event';
	public const CLEANUP_EVENT = 'wp_story_cleanup_interactions_event';

	/**
	 * هوک‌ها.
	 *
	 * باگ قبلی: زمان‌بندی‌ها با switch_theme پاک می‌شدند (از زمانی که این
	 * بخش جزو قالب بود) و فقط وقتی نسخه دیتابیس بالا می‌رفت دوباره ساخته
	 * می‌شدند؛ پس بعد از هر تعویض قالب، جمع‌بندی روزانه و پاک‌سازی برای
	 * همیشه قطع می‌شد. حالا هر بار بررسی و در صورت نبودن دوباره ساخته
	 * می‌شوند (پاک کردنشان با غیرفعال‌سازی افزونه است، نه قالب).
	 */
	public static function init(): void {
		add_action( 'init', [ self::class, 'check_version' ] );
		add_action( self::FLUSH_EVENT, [ WP_Story_Analytics::class, 'flush_counters' ] );
		add_action( self::CLEANUP_EVENT, [ self::class, 'cleanup_interactions' ] );
		add_action( 'wp_dashboard_setup', [ self::class, 'add_dashboard_widget' ] );
		add_action( 'admin_head-index.php', [ self::class, 'widget_styles' ] );
	}

	/** استایل ویجت، فقط در صفحه پیشخوان. */
	public static function widget_styles(): void {
		echo '<style>
			.hs-widget-container { display: flex; gap: 15px; text-align: center; margin-top: 10px; }
			.hs-widget-box { flex: 1; padding: 15px; background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; }
			.hs-widget-title { margin: 0 0 10px; color: #50575e; }
			.hs-widget-value { font-size: 24px; color: #25316a; font-weight: bold; }
			.hs-widget-footer { text-align: left; margin-top: 15px; margin-bottom: 0; }
		</style>';
	}

	/** ساخت یا به‌روزرسانی جدول‌ها. */
	public static function install_tables(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$p       = $wpdb->prefix;

		dbDelta(
			"CREATE TABLE {$p}hodima_story_stats (
			story_id bigint(20) unsigned NOT NULL,
			views bigint(20) unsigned NOT NULL DEFAULT 0,
			shares bigint(20) unsigned NOT NULL DEFAULT 0,
			status tinyint(1) NOT NULL DEFAULT 1,
			last_updated datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (story_id)
		) $charset;"
		);

		dbDelta(
			"CREATE TABLE {$p}hodima_story_counter_queue (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			story_id bigint(20) unsigned NOT NULL,
			counter_type varchar(20) NOT NULL,
			delta bigint(20) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY story_counter_idx (story_id, counter_type)
		) $charset;"
		);

		dbDelta(
			"CREATE TABLE {$p}hodima_story_interactions (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			story_id bigint(20) unsigned NOT NULL,
			interaction_type varchar(20) NOT NULL,
			actor_hash varchar(64) NOT NULL,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY unique_actor_interaction_idx (story_id, interaction_type, actor_hash),
			KEY cleanup_idx (created_at)
		) $charset;"
		);

		dbDelta(
			"CREATE TABLE {$p}hodima_story_flush_lock (
			lock_name varchar(64) NOT NULL,
			locked_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (lock_name)
		) $charset;"
		);

		$wpdb->query(
			$wpdb->prepare(
				'INSERT IGNORE INTO %i (lock_name, locked_at) VALUES (%s, %s)',
				$p . 'hodima_story_flush_lock',
				'flush_counters',
				'2000-01-01 00:00:00'
			)
		);

		update_option( self::VERSION_OPTION, self::DB_VERSION, false );
		self::ensure_scheduled_events();
	}

	/** ارتقای جدول‌ها در صورت نیاز، و اطمینان از وجود زمان‌بندی‌ها. */
	public static function check_version(): void {
		$installed = (string) get_option( self::VERSION_OPTION, '0' );

		if ( version_compare( $installed, self::DB_VERSION, '<' ) ) {
			// ستون popularity_score فقط در نصب‌های پیش از 4.0.0 بود
			if ( '0' !== $installed && version_compare( $installed, '4.0.0', '<' ) ) {
				global $wpdb;
				$table         = $wpdb->prefix . 'hodima_story_stats';
				$prev_suppress = $wpdb->suppress_errors( true );
				$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i DROP COLUMN popularity_score', $table ) );
				$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i DROP INDEX popularity_idx', $table ) );
				$wpdb->suppress_errors( $prev_suppress );
			}
			self::install_tables();
			return;
		}

		// ارزان: فهرست cron گزینه autoload است و در حافظه.
		self::ensure_scheduled_events();
	}

	/** زمان‌بندی جمع‌بندی و پاک‌سازی روزانه، اگر نباشند یا روزانه نباشند. */
	public static function ensure_scheduled_events(): void {
		if ( ! wp_next_scheduled( self::FLUSH_EVENT ) ) {
			wp_schedule_event( time(), 'daily', self::FLUSH_EVENT );
		} elseif ( 'daily' !== wp_get_schedule( self::FLUSH_EVENT ) ) {
			wp_clear_scheduled_hook( self::FLUSH_EVENT );
			wp_schedule_event( time(), 'daily', self::FLUSH_EVENT );
		}

		if ( ! wp_next_scheduled( self::CLEANUP_EVENT ) ) {
			wp_schedule_event( time(), 'daily', self::CLEANUP_EVENT );
		}
	}

	/** حذف زمان‌بندی‌ها (غیرفعال‌سازی افزونه). */
	public static function clear_scheduled_events(): void {
		wp_clear_scheduled_hook( self::FLUSH_EVENT );
		wp_clear_scheduled_hook( self::CLEANUP_EVENT );
	}

	/** حذف تعامل‌های قدیمی‌تر از دوره نگهداری، در چند دور محدود. */
	public static function cleanup_interactions(): void {
		if ( ! WP_Story_Analytics::tables_ready() ) {
			return;
		}
		global $wpdb;
		$table  = $wpdb->prefix . 'hodima_story_interactions';
		$cutoff = gmdate( 'Y-m-d H:i:s', (int) strtotime( '-' . self::RETENTION_MONTHS . ' months' ) );

		for ( $i = 0; $i < 5; $i++ ) {
			$rows = $wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE created_at < %s LIMIT %d', $table, $cutoff, self::CLEANUP_LIMIT ) );
			if ( ! $rows ) {
				break;
			}
		}
	}

	/** ویجت پیشخوان (فقط مدیر). */
	public static function add_dashboard_widget(): void {
		if ( current_user_can( 'manage_options' ) ) {
			wp_add_dashboard_widget( 'hodima_story_stats_widget', __( 'آمار بازدید استوری‌ها', 'hodima' ), [ self::class, 'render_dashboard_widget' ] );
		}
	}

	/** نمایش ویجت. */
	public static function render_dashboard_widget(): void {
		global $wpdb;

		if ( ! WP_Story_Analytics::tables_ready() ) {
			echo '<p>' . esc_html__( 'دیتابیس استوری‌ها هنوز آماده نیست.', 'hodima' ) . '</p>';
			return;
		}

		$cached_stats = get_transient( 'hodima_story_dashboard_stats' );
		if ( ! is_array( $cached_stats ) ) {
			$stats_table  = $wpdb->prefix . 'hodima_story_stats';
			$cached_stats = [
				'views'  => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT SUM(views) FROM %i', $stats_table ) ),
				'shares' => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT SUM(shares) FROM %i', $stats_table ) ),
			];
			set_transient( 'hodima_story_dashboard_stats', $cached_stats, DAY_IN_SECONDS );
		}
		?>
		<div class="hs-widget-container">
			<div class="hs-widget-box">
				<h4 class="hs-widget-title"><?php esc_html_e( 'کل بازدیدها', 'hodima' ); ?></h4>
				<strong class="hs-widget-value"><?php echo esc_html( number_format_i18n( (int) $cached_stats['views'] ) ); ?></strong>
			</div>
			<div class="hs-widget-box">
				<h4 class="hs-widget-title"><?php esc_html_e( 'اشتراک‌گذاری‌ها', 'hodima' ); ?></h4>
				<strong class="hs-widget-value"><?php echo esc_html( number_format_i18n( (int) $cached_stats['shares'] ) ); ?></strong>
			</div>
		</div>
		<p class="hs-widget-footer"><a href="<?php echo esc_url( admin_url( 'admin.php?page=hodima-stories' ) ); ?>" class="button button-secondary"><?php esc_html_e( 'مدیریت استوری‌ها', 'hodima' ); ?></a></p>
		<?php
	}
}
