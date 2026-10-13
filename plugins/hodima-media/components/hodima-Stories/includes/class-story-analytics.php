<?php
/**
 * استوری‌ها — ثبت و خواندن آمار بازدید و اشتراک.
 *
 * طرح: هر تعامل تازه (بازدیدکننده + استوری + نوع + روز) در جدول تعامل‌ها
 * ثبت می‌شود و فقط اگر تکراری نبود، شمارنده صف یک واحد بالا می‌رود؛ صف
 * روزانه در جدول اصلی جمع می‌شود (جلوگیری از نوشتن پیاپی روی یک ردیف).
 *
 * @package Hodima_Media
 */

declare(strict_types=1);

namespace Hodima\Stories\Analytics;

defined( 'ABSPATH' ) || exit;

/**
 * آمار استوری‌ها.
 */
class WP_Story_Analytics {

	private const CACHE_GROUP = 'wp_story_analytics';
	private const CACHE_TTL   = DAY_IN_SECONDS;

	/** جدول‌ها ساخته شده‌اند؟ */
	public static function tables_ready(): bool {
		static $ready = null;
		if ( null === $ready ) {
			$ready = (bool) get_option( WP_Story_Setup::VERSION_OPTION );
		}
		return $ready;
	}

	/**
	 * حذف آمار استوری‌های مشخص (فقط همان‌هایی که مدیر حذف کرده).
	 *
	 * باگ قبلی: تابع قبلی «همه به‌جز این فهرست» را پاک می‌کرد و با فهرست
	 * خالی کل جدول‌ها را TRUNCATE می‌کرد؛ یک ذخیره ناقص یا ردیف‌هایی که
	 * بی‌صدا کنار گذاشته می‌شدند، آمار همه یا بخشی از استوری‌ها را پاک می‌کرد.
	 *
	 * @param list<int> $story_ids شناسه استوری‌های حذف‌شده.
	 */
	public static function delete_stats( array $story_ids ): void {
		$story_ids = array_values( array_filter( array_unique( array_map( 'absint', $story_ids ) ) ) );
		if ( ! $story_ids || ! self::tables_ready() ) {
			return;
		}
		global $wpdb;

		foreach ( [ 'hodima_story_stats', 'hodima_story_counter_queue', 'hodima_story_interactions' ] as $table ) {
			$wpdb->query(
				$wpdb->prepare(
					'DELETE FROM %i WHERE story_id IN (' . implode( ',', array_fill( 0, count( $story_ids ), '%d' ) ) . ')',
					array_merge( [ $wpdb->prefix . $table ], $story_ids )
				)
			);
		}

		foreach ( $story_ids as $id ) {
			wp_cache_delete( $id, self::CACHE_GROUP );
		}
		delete_transient( 'hodima_story_dashboard_stats' );
	}

	/** ثبت بازدید؛ true اگر تازه بود (نه تکرار همان روز). */
	public static function record_view( int $story_id ): bool {
		$is_new = self::insert_interaction( $story_id, 'view' );
		if ( $is_new ) {
			self::increment_counter( $story_id, 'views' );
		}
		return $is_new;
	}

	/** ثبت اشتراک؛ true اگر تازه بود. */
	public static function record_share( int $story_id ): bool {
		$is_new = self::insert_interaction( $story_id, 'share' );
		if ( $is_new ) {
			self::increment_counter( $story_id, 'shares' );
		}
		return $is_new;
	}

	/**
	 * آمار چند استوری (جدول اصلی + صف جمع‌نشده).
	 *
	 * @param array<int|string> $story_ids شناسه‌ها.
	 * @return array<int, array{story_id: int, views: int, shares: int, status: int, last_updated: ?string}>
	 */
	public static function get_stats_bulk( array $story_ids ): array {
		if ( ! $story_ids || ! self::tables_ready() ) {
			return [];
		}
		$story_ids = array_values( array_filter( array_unique( array_map( 'absint', $story_ids ) ) ) );
		if ( ! $story_ids ) {
			return [];
		}

		$cached_data = wp_cache_get_multiple( $story_ids, self::CACHE_GROUP );
		$result      = [];
		$missing     = [];

		foreach ( $story_ids as $id ) {
			if ( ! empty( $cached_data[ $id ] ) && is_array( $cached_data[ $id ] ) ) {
				$result[ $id ] = $cached_data[ $id ];
			} else {
				$missing[] = $id;
			}
		}

		if ( ! $missing ) {
			return $result;
		}

		/*
		 * دو کوئری جدا به جای FROM stats LEFT JOIN queue: سطر جدول stats فقط
		 * در جمع‌بندی روزانه ساخته می‌شود و استوری تازه‌ای که فقط در صف بازدید
		 * داشت، با JOIN تا فردا «۰ بازدید» نشان می‌داد.
		 */
		global $wpdb;
		$in = implode( ',', array_fill( 0, count( $missing ), '%d' ) );

		$stat_rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT story_id, views, shares, status, last_updated FROM %i WHERE story_id IN (' . $in . ')', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $in فقط %d است
				array_merge( [ $wpdb->prefix . 'hodima_story_stats' ], $missing )
			),
			ARRAY_A
		);

		$queue_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT story_id,
					SUM(CASE WHEN counter_type = 'views' THEN delta ELSE 0 END) AS pending_views,
					SUM(CASE WHEN counter_type = 'shares' THEN delta ELSE 0 END) AS pending_shares
				FROM %i WHERE story_id IN (" . $in . ') GROUP BY story_id', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $in فقط %d است
				array_merge( [ $wpdb->prefix . 'hodima_story_counter_queue' ], $missing )
			),
			ARRAY_A
		);

		$to_cache = [];
		foreach ( $missing as $id ) {
			$to_cache[ $id ] = self::empty_stats( $id );
		}
		foreach ( (array) $stat_rows as $row ) {
			$id = (int) $row['story_id'];
			if ( isset( $to_cache[ $id ] ) ) {
				$to_cache[ $id ]['views']        = (int) $row['views'];
				$to_cache[ $id ]['shares']       = (int) $row['shares'];
				$to_cache[ $id ]['status']       = (int) $row['status'];
				$to_cache[ $id ]['last_updated'] = (string) $row['last_updated'];
			}
		}
		foreach ( (array) $queue_rows as $row ) {
			$id = (int) $row['story_id'];
			if ( isset( $to_cache[ $id ] ) ) {
				$to_cache[ $id ]['views']  += (int) $row['pending_views'];
				$to_cache[ $id ]['shares'] += (int) $row['pending_shares'];
			}
		}

		// استوری بی‌بازدید هم کش می‌شود؛ increment_counter کلیدش را با اولین بازدید پاک می‌کند.
		wp_cache_set_multiple( $to_cache, self::CACHE_GROUP, self::CACHE_TTL );

		return $result + $to_cache;
	}

	/** یک واحد به صف شمارنده. */
	private static function increment_counter( int $story_id, string $type ): void {
		if ( ! self::tables_ready() || ! in_array( $type, [ 'views', 'shares' ], true ) ) {
			return;
		}
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				'INSERT INTO %i (story_id, counter_type, delta, created_at) VALUES (%d, %s, 1, %s)
				ON DUPLICATE KEY UPDATE delta = delta + 1',
				$wpdb->prefix . 'hodima_story_counter_queue',
				$story_id,
				$type,
				gmdate( 'Y-m-d H:i:s' )
			)
		);

		// با کش پایدار (Redis)، عدد کش‌شده تا جمع‌بندی بعدی کهنه می‌ماند
		wp_cache_delete( $story_id, self::CACHE_GROUP );
	}

	/** جمع کردن صف در جدول اصلی (cron روزانه؛ با قفل تا دو اجرا هم‌زمان نشوند). */
	public static function flush_counters(): void {
		if ( ! self::tables_ready() ) {
			return;
		}
		global $wpdb;
		$lock_table  = $wpdb->prefix . 'hodima_story_flush_lock';
		$queue_table = $wpdb->prefix . 'hodima_story_counter_queue';
		$stats_table = $wpdb->prefix . 'hodima_story_stats';

		$wpdb->query( 'START TRANSACTION' );
		$lock = $wpdb->get_row( $wpdb->prepare( 'SELECT locked_at FROM %i WHERE lock_name = %s FOR UPDATE', $lock_table, 'flush_counters' ) );
		if ( ! $lock || ( time() - (int) strtotime( $lock->locked_at . ' UTC' ) ) < 1700 ) {
			$wpdb->query( 'ROLLBACK' );
			return;
		}

		$now = gmdate( 'Y-m-d H:i:s' );
		$wpdb->query( $wpdb->prepare( 'UPDATE %i SET locked_at = %s WHERE lock_name = %s', $lock_table, $now, 'flush_counters' ) );
		$wpdb->query( 'COMMIT' );

		try {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, story_id, counter_type, delta FROM %i WHERE counter_type IN ('views', 'shares') ORDER BY id ASC LIMIT %d",
					$queue_table,
					self::flush_limit()
				),
				ARRAY_A
			);
			if ( ! $rows ) {
				return;
			}

			$grouped = [];
			$ids     = [];
			foreach ( $rows as $row ) {
				$sid  = (int) $row['story_id'];
				$type = (string) $row['counter_type'];
				$grouped[ $sid ][ $type ] = ( $grouped[ $sid ][ $type ] ?? 0 ) + (int) $row['delta'];
				$ids[] = (int) $row['id'];
			}

			$values = [];
			foreach ( $grouped as $sid => $counters ) {
				array_push( $values, $sid, $counters['views'] ?? 0, $counters['shares'] ?? 0, $now );
			}

			// به‌روزرسانی جدول اصلی و حذف صف با هم، تا قطع شدن وسط کار دوبار شمردن نیاورد
			$wpdb->query( 'START TRANSACTION' );

			$rows_sql  = implode( ', ', array_fill( 0, count( $grouped ), '(%d, %d, %d, %s)' ) );
			$insert_ok = $wpdb->query(
				$wpdb->prepare(
					'INSERT INTO %i (story_id, views, shares, last_updated) VALUES ' . $rows_sql // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $rows_sql فقط placeholder است
					. ' ON DUPLICATE KEY UPDATE views = views + VALUES(views), shares = shares + VALUES(shares), last_updated = VALUES(last_updated)',
					array_merge( [ $stats_table ], $values )
				)
			);
			if ( false === $insert_ok ) {
				$wpdb->query( 'ROLLBACK' );
				return;
			}

			$delete_ok = $wpdb->query(
				$wpdb->prepare(
					'DELETE FROM %i WHERE id IN (' . implode( ',', array_fill( 0, count( $ids ), '%d' ) ) . ')',
					array_merge( [ $queue_table ], $ids )
				)
			);
			if ( false === $delete_ok ) {
				$wpdb->query( 'ROLLBACK' );
				return;
			}

			$wpdb->query( 'COMMIT' );
			delete_transient( 'hodima_story_dashboard_stats' );
			foreach ( array_keys( $grouped ) as $sid ) {
				wp_cache_delete( $sid, self::CACHE_GROUP );
			}
		} finally {
			$wpdb->query( $wpdb->prepare( 'UPDATE %i SET locked_at = %s WHERE lock_name = %s', $lock_table, '2000-01-01 00:00:00', 'flush_counters' ) );
		}
	}

	/** سقف ردیف‌های هر جمع‌بندی. */
	private static function flush_limit(): int {
		return WP_Story_Setup::FLUSH_LIMIT;
	}

	/**
	 * امضای روزانه بازدیدکننده: IP واقعی (پشت کلادفلر هم، با تابع Core) +
	 * مرورگر + تاریخ روز. یک بازدیدکننده در هر روز برای هر استوری یک بار.
	 */
	private static function get_actor_signature(): string {
		$ip = function_exists( 'hodima_get_client_ip' )
			? hodima_get_client_ip()
			: ( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '' );
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		return hash( 'sha256', $ip . '|' . $ua . '|' . gmdate( 'Y-m-d' ) );
	}

	/** ثبت یک تعامل؛ true اگر تازه بود، false اگر کلید یکتا جلویش را گرفت. */
	private static function insert_interaction( int $story_id, string $type ): bool {
		if ( ! self::tables_ready() || ! in_array( $type, [ 'view', 'share' ], true ) ) {
			return false;
		}
		global $wpdb;

		// تکراری بودن نتیجه عادی است (همان بازدیدکننده همان روز)، نه خطا
		$prev_suppress = $wpdb->suppress_errors( true );
		$inserted      = $wpdb->insert(
			$wpdb->prefix . 'hodima_story_interactions',
			[
				'story_id'         => $story_id,
				'interaction_type' => $type,
				'actor_hash'       => self::get_actor_signature(),
				'created_at'       => gmdate( 'Y-m-d H:i:s' ),
			],
			[ '%d', '%s', '%s', '%s' ]
		);
		$wpdb->suppress_errors( $prev_suppress );

		return (bool) $inserted;
	}

	/**
	 * آمار خالی.
	 *
	 * @return array{story_id: int, views: int, shares: int, status: int, last_updated: ?string}
	 */
	private static function empty_stats( int $story_id ): array {
		return [
			'story_id'     => $story_id,
			'views'        => 0,
			'shares'       => 0,
			'status'       => 1,
			'last_updated' => null,
		];
	}
}
