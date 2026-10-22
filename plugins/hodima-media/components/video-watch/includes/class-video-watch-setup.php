<?php
/**
 * جدول‌ها و زمان‌بندی آمار ویدیو (بارگذار: ../db-video-watch.php)
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * ساخت و ارتقای جدول‌ها و زمان‌بندی جمع‌بندی.
 */
final class Hodima_Video_Watch_Setup {

	public const DB_VERSION     = '1.5.0';
	public const VERSION_OPTION = 'video_watch_db_version';
	public const FLUSH_EVENT    = 'video_watch_flush_counters_event';

	/** رویداد روزانه «امتیاز محبوبیت» تا DB 1.2.0؛ فقط برای پاک کردن از نصب‌های قدیمی. */
	public const LEGACY_DECAY_EVENT = 'video_watch_decay_popularity_event';

	public static function init(): void {
		add_filter( 'cron_schedules', [ self::class, 'add_cron_schedules' ] ); // phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval -- صف سبک عمدا هر ۵ دقیقه جمع‌بندی می‌شود
		add_action( 'admin_init', [ self::class, 'check_version' ] );
		add_action( self::FLUSH_EVENT, [ Hodima_Video_Watch_Analytics::class, 'flush_counters' ] );
		add_action( self::FLUSH_EVENT, [ Hodima_Video_Watch_Limits::class, 'cleanup' ] );
		add_action( 'delete_post', [ self::class, 'cleanup_on_delete_post' ], 10, 2 );
		// پاک کردن زمان‌بندی‌ها با غیرفعال‌سازی افزونه است (hodima-media.php)؛ تا Media 2.0.8
		// با «تعویض قالب» پاک می‌شدند که بی‌ربط بود (همان باگ استوری، بخش ۱۲۷).
	}

	public static function install_tables(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$p       = $wpdb->prefix;

		dbDelta(
			"CREATE TABLE {$p}video_watch_stats (
			video_id bigint(20) unsigned NOT NULL,
			views bigint(20) unsigned NOT NULL DEFAULT 0,
			shares bigint(20) unsigned NOT NULL DEFAULT 0,
			last_updated datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (video_id)
			) $charset;"
		);

		dbDelta(
			"CREATE TABLE {$p}video_watch_counter_queue (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			video_id bigint(20) unsigned NOT NULL,
			counter_type varchar(20) NOT NULL,
			delta bigint(20) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY video_counter_idx (video_id, counter_type)
			) $charset;"
		);

		// شمارنده‌های کوتاه‌مدت (تکراری، سقف IP، محدودیت نرخ) — از DB 1.4.0 به‌جای transient
		dbDelta(
			"CREATE TABLE {$p}video_watch_seen (
			seen_key char(32) NOT NULL,
			hits int(10) unsigned NOT NULL DEFAULT 0,
			expires_at int(10) unsigned NOT NULL,
			PRIMARY KEY (seen_key),
			KEY expires_idx (expires_at)
			) $charset;"
		);

		dbDelta(
			"CREATE TABLE {$p}video_watch_flush_lock (
			lock_name varchar(64) NOT NULL,
			locked_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (lock_name)
			) $charset;"
		);

		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'INSERT IGNORE INTO %i (lock_name, locked_at) VALUES (%s, %s)',
				$p . 'video_watch_flush_lock',
				'flush_counters',
				'2000-01-01 00:00:00'
			)
		);

		update_option( self::VERSION_OPTION, self::DB_VERSION, false );
	}

	/**
	 * ارتقای جدول‌ها در صورت نیاز، و اطمینان از وجود زمان‌بندی
	 * (خودترمیم در برابر پاک شدن جدول cron توسط میزبان یا انتقال سرور).
	 */
	public static function check_version(): void {
		$installed = (string) get_option( self::VERSION_OPTION, '0' );

		if ( version_compare( $installed, self::DB_VERSION, '<' ) ) {
			self::upgrade( $installed );
		}

		self::ensure_scheduled_events();
	}

	/**
	 * ساخت جدول‌ها وقتی نوشتن شکست خورد و نسخه جدول کهنه است.
	 *
	 * ارتقا معمولا در پیشخوان است (check_version)؛ ولی بعد از به‌روزرسانی
	 * خودکار، تا مدیری پیشخوان را باز نکرده جدول تازه نیست و ثبت بازدید
	 * شکست می‌خورد. فقط بعد از خطا صدا زده می‌شود (هزینه‌ای برای حالت عادی
	 * ندارد) و در هر درخواست حداکثر یک بار.
	 *
	 * @return bool true اگر جدول‌ها همین الان ساخته/ارتقا شدند (ارزش تکرار دارد).
	 */
	public static function repair_tables(): bool {
		static $tried = false;
		if ( $tried ) {
			return false;
		}
		$tried = true;

		$installed = (string) get_option( self::VERSION_OPTION, '0' );
		if ( ! version_compare( $installed, self::DB_VERSION, '<' ) ) {
			return false;
		}
		self::upgrade( $installed );
		return true;
	}

	/**
	 * ستون‌های قدیمی که dbDelta حذف نمی‌کند، بعد ساخت/ارتقای جدول‌ها.
	 *
	 * @param string $installed نسخه جدول نصب‌شده ('0' = نصب تازه).
	 */
	private static function upgrade( string $installed ): void {
		if ( '0' !== $installed && version_compare( $installed, '1.3.0', '<' ) ) {
			self::drop_popularity();
		}
		if ( '0' !== $installed && version_compare( $installed, '1.5.0', '<' ) ) {
			self::drop_status_column();
		}
		self::install_tables();
	}

	/**
	 * حذف «امتیاز محبوبیت» (تا DB 1.2.0).
	 *
	 * هیچ جای سایت آن را نمی‌خواند ولی هر جمع‌بندی حسابش می‌کرد و هر روز یک
	 * رویداد cron کاهشش می‌داد؛ حلقه همان رویداد با بیش از ۱۰ هزار ویدیو همان
	 * ردیف‌ها را پشت سر هم کم می‌کرد. dbDelta ستون را حذف نمی‌کند، پس اینجا.
	 */
	private static function drop_popularity(): void {
		global $wpdb;
		$table         = $wpdb->prefix . 'video_watch_stats';
		$prev_suppress = $wpdb->suppress_errors( true );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- ارتقای یک‌باره جدول خود افزونه
		$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i DROP INDEX popularity_idx', $table ) );
		$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i DROP COLUMN popularity_score', $table ) );
		// phpcs:enable
		$wpdb->suppress_errors( $prev_suppress );
		wp_clear_scheduled_hook( self::LEGACY_DECAY_EVENT );
	}

	/**
	 * حذف ستون «status» جدول آمار (تا DB 1.4.0).
	 *
	 * از نسخه اول ساخته می‌شد (همیشه ۱) ولی هیچ کدی نمی‌خواندش؛ فقط در شاخص
	 * امتیاز محبوبیت بود که در DB 1.3.0 حذف شد. بعد از drop_popularity (شاخص
	 * popularity_idx همین ستون را داشت).
	 */
	private static function drop_status_column(): void {
		global $wpdb;
		$prev_suppress = $wpdb->suppress_errors( true );
		$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i DROP COLUMN status', $wpdb->prefix . 'video_watch_stats' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange -- ارتقای یک‌باره جدول خود افزونه
		$wpdb->suppress_errors( $prev_suppress );
	}

	/**
	 * فاصله «هر ۵ دقیقه» برای جمع‌بندی.
	 *
	 * @param array<string, array{interval: int, display: string}> $schedules فاصله‌های موجود.
	 * @return array<string, array{interval: int, display: string}>
	 */
	public static function add_cron_schedules( array $schedules ): array {
		$schedules['five_minutes'] = [
			'interval' => 300,
			'display'  => 'Every 5 Minutes',
		];
		return $schedules;
	}

	private static function ensure_scheduled_events(): void {
		if ( ! wp_next_scheduled( self::FLUSH_EVENT ) ) {
			wp_schedule_event( time(), 'five_minutes', self::FLUSH_EVENT );
		}
	}

	/** برای غیرفعال‌سازی افزونه (hodima-media.php). */
	public static function clear_scheduled_events(): void {
		wp_clear_scheduled_hook( self::FLUSH_EVENT );
		wp_clear_scheduled_hook( self::LEGACY_DECAY_EVENT );
	}

	public static function cleanup_on_delete_post( int $post_id, ?WP_Post $post = null ): void {
		$post = $post ?: get_post( $post_id );
		if ( ! $post || 'video' !== $post->post_type ) {
			return;
		}

		global $wpdb;
		foreach ( [ 'video_watch_stats', 'video_watch_counter_queue' ] as $t ) {
			$wpdb->delete( $wpdb->prefix . $t, [ 'video_id' => $post_id ], [ '%d' ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		}
		Hodima_Video_Watch_Analytics::forget_views( $post_id );
	}
}
