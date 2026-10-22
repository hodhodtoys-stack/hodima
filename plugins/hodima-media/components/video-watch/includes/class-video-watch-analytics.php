<?php
/**
 * ثبت و خواندن آمار ویدیو (بارگذار: ../db-video-watch.php)
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * ثبت و خواندن آمار.
 */
final class Hodima_Video_Watch_Analytics {

	/**
	 * بیشترین بازدید (یا اشتراک) پذیرفته‌شده از یک IP برای یک ویدیو در روز.
	 *
	 * تشخیص تکراری با «IP + نام مرورگر» است تا چند نفر پشت یک اینترنت مشترک
	 * (خانه، اداره، اپراتور موبایل) جدا شمرده شوند؛ ولی نام مرورگر را هر کسی
	 * می‌تواند در هر درخواست عوض کند. تا Media 2.0.8 این تنها مانع بود و از یک
	 * IP روزی ده‌ها هزار بازدید جعلی ثبت می‌شد (همین عدد در اسکیما به گوگل
	 * می‌رود). این سقف جلوی آن را می‌گیرد؛ فیلتر hodima_video_watch_ip_daily_cap.
	 */
	public const IP_DAILY_CAP = 20;

	/** محدودیت نرخ: بیشترین درخواست ثبت از یک IP در دقیقه. */
	public const RATE_PER_MINUTE = 45;

	/**
	 * تعداد کل بازدید (ثبت‌شده + در صف) — منبع واحد قالب، اسکیما و REST.
	 */
	public static function get_total_views( int $video_id ): int {
		$cache_key = 'hvw_total_views_calc_' . $video_id;
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return (int) $cached;
		}

		global $wpdb;
		$views = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT COALESCE((SELECT views FROM %i WHERE video_id = %d), 0) +
					COALESCE((SELECT SUM(delta) FROM %i WHERE video_id = %d AND counter_type = 'views'), 0)",
				$wpdb->prefix . 'video_watch_stats',
				$video_id,
				$wpdb->prefix . 'video_watch_counter_queue',
				$video_id
			)
		);

		set_transient( $cache_key, $views, 20 );

		return $views;
	}

	/**
	 * کلید ثبت بازدید (جای nonce وردپرس).
	 *
	 * تا Media 2.0.8 nonce وردپرس بود که به کاربر واردشده وابسته است؛ ولی رابط
	 * REST بدون سربرگ X-WP-Nonce کاربر را «مهمان» می‌کند. پس برای هر کاربر
	 * واردشده (مثلا مشتری عمده) کلید صفحه همیشه رد می‌شد و هر بازدید سه درخواست
	 * می‌شد (رد، گرفتن کلید تازه، تکرار). این کلید به کاربر وابسته نیست؛ مثل
	 * nonce هر ۱۲ ساعت عوض می‌شود و تا ۲۴ ساعت معتبر است.
	 *
	 * @param int $tick_offset ۰ = دوره فعلی، ۱ = دوره قبل.
	 */
	public static function token( int $tick_offset = 0 ): string {
		$tick = (int) wp_nonce_tick( 'hvw_track_action' ) - $tick_offset;
		return substr( wp_hash( $tick . '|hvw_track_action', 'nonce' ), -12, 10 );
	}

	public static function verify_token( string $token ): bool {
		if ( '' === $token ) {
			return false;
		}
		return hash_equals( self::token(), $token ) || hash_equals( self::token( 1 ), $token );
	}

	/**
	 * آیا این تعامل شمرده شود؟ (و اگر بله، علامتش را ثبت می‌کند)
	 *
	 * تکراری = همان ویدیو و نوع، از همان IP و مرورگر، در همان روز (به وقت
	 * گرینویچ). علاوه بر آن سقف روزانه هر IP (IP_DAILY_CAP). IP خام ذخیره
	 * نمی‌شود، فقط هش کلید (متن سیاست حریم خصوصی، video-watch.php).
	 */
	public static function accept( int $video_id, Hodima_Video_Watch_Counter $type, string $client_ip ): bool {
		$ua   = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		$day  = gmdate( 'Y-m-d' );
		$seen = 'hvw_seen_' . md5( $video_id . '|' . $type->value . '|' . $client_ip . '|' . $ua . '|' . $day );

		if ( get_transient( $seen ) ) {
			return false;
		}

		$cap_key = 'hvw_ipcap_' . md5( $video_id . '|' . $type->value . '|' . $client_ip . '|' . $day );
		$count   = (int) get_transient( $cap_key );
		$cap     = max( 1, (int) apply_filters( 'hodima_video_watch_ip_daily_cap', self::IP_DAILY_CAP, $video_id, $type->value ) );

		if ( $count >= $cap ) {
			return false;
		}

		set_transient( $seen, 1, DAY_IN_SECONDS );
		set_transient( $cap_key, $count + 1, DAY_IN_SECONDS );
		return true;
	}

	public static function record_interaction( int $video_id, Hodima_Video_Watch_Counter $type ): void {
		global $wpdb;
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				'INSERT INTO %i (video_id, counter_type, delta, created_at) VALUES (%d, %s, 1, %s) ON DUPLICATE KEY UPDATE delta = delta + 1',
				$wpdb->prefix . 'video_watch_counter_queue',
				$video_id,
				$type->value,
				gmdate( 'Y-m-d H:i:s' )
			)
		);
		delete_transient( 'hvw_total_views_calc_' . $video_id );
	}

	/**
	 * انتقال صف به جدول آمار، در یک تراکنش.
	 *
	 * ردیف‌های صف با FOR UPDATE قفل می‌شوند تا ثبت هم‌زمان روی همان ردیف تا
	 * پایان تراکنش منتظر بماند و گم نشود. قفل ۲۹۰ ثانیه‌ای جلوی اجرای پشت سر هم
	 * (cron + مسیر دستی) را می‌گیرد.
	 *
	 * باگ تا Media 2.0.8: خطا با try/catch گرفته می‌شد، ولی $wpdb خطا پرتاب
	 * نمی‌کند (false برمی‌گرداند). اگر نوشتن در جدول آمار شکست می‌خورد، صف باز
	 * هم پاک و تراکنش ثبت می‌شد: آمار آن ۵ دقیقه برای همیشه از دست می‌رفت.
	 * حالا نتیجه هر کوئری بررسی و در صورت خطا همه‌چیز برگردانده می‌شود.
	 */
	public static function flush_counters(): void {
		global $wpdb;
		$lock_table  = $wpdb->prefix . 'video_watch_flush_lock';
		$queue_table = $wpdb->prefix . 'video_watch_counter_queue';
		$stats_table = $wpdb->prefix . 'video_watch_stats';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- جدول‌های خود افزونه؛ کش معنی ندارد
		$fail = static function ( string $step ) use ( $wpdb ): void {
			$error = (string) $wpdb->last_error;
			$wpdb->query( 'ROLLBACK' );
			error_log( '[video-watch] flush_counters failed at ' . $step . ( '' !== $error ? ': ' . $error : '' ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- خطای سرور باید در گزارش خطا دیده شود
		};

		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			$fail( 'start' );
			return;
		}

		$lock = $wpdb->get_row( $wpdb->prepare( 'SELECT locked_at FROM %i WHERE lock_name = %s FOR UPDATE', $lock_table, 'flush_counters' ) );
		if ( ! $lock || ( time() - (int) strtotime( $lock->locked_at . ' UTC' ) < 290 ) ) {
			$wpdb->query( 'ROLLBACK' );
			return;
		}

		$now = gmdate( 'Y-m-d H:i:s' );
		if ( false === $wpdb->query( $wpdb->prepare( 'UPDATE %i SET locked_at = %s WHERE lock_name = %s', $lock_table, $now, 'flush_counters' ) ) ) {
			$fail( 'lock' );
			return;
		}

		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id, video_id, counter_type, delta FROM %i LIMIT 500 FOR UPDATE', $queue_table ), ARRAY_A );
		if ( '' !== $wpdb->last_error ) {
			$fail( 'select' );
			return;
		}
		if ( empty( $rows ) ) {
			$wpdb->query( 'COMMIT' );
			return;
		}

		$grouped = [];
		$ids     = [];
		foreach ( $rows as $row ) {
			$vid  = (int) $row['video_id'];
			$type = Hodima_Video_Watch_Counter::tryFrom( (string) $row['counter_type'] );
			$ids[] = (int) $row['id'];
			if ( $type ) {
				$grouped[ $vid ][ $type->value ] = ( $grouped[ $vid ][ $type->value ] ?? 0 ) + (int) $row['delta'];
			}
		}

		if ( $grouped ) {
			$parts  = [];
			$params = [ $stats_table ];
			foreach ( $grouped as $vid => $counters ) {
				$parts[] = '(%d, %d, %d, %s)';
				array_push( $params, $vid, $counters['views'] ?? 0, $counters['shares'] ?? 0, $now );
			}

			$inserted = $wpdb->query(
				$wpdb->prepare(
					'INSERT INTO %i (video_id, views, shares, last_updated) VALUES ' . implode( ', ', $parts ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $parts فقط placeholder است
					. ' ON DUPLICATE KEY UPDATE views = views + VALUES(views), shares = shares + VALUES(shares), last_updated = VALUES(last_updated)',
					...$params
				)
			);
			if ( false === $inserted ) {
				$fail( 'insert' );
				return;
			}
		}

		$in = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- $in فقط %d است
		if ( false === $wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE id IN (' . $in . ')', $queue_table, ...$ids ) ) ) {
			$fail( 'delete' );
			return;
		}

		if ( false === $wpdb->query( 'COMMIT' ) ) {
			$fail( 'commit' );
		}
		// phpcs:enable
	}
}
