<?php
/**
 * Google Indexing — Queue
 * Path: components/google-indexing-api/core/queue.php
 *
 * ─────────────────────────────────────────────────────────────────────
 * زمان‌بندی
 * ─────────────────────────────────────────────────────────────────────
 * نسخه قبلی یک رویداد تکرارشونده «هر دقیقه» داشت. سه مشکل:
 *
 *   ۱. WP-Cron فقط با بازدیدی اجرا می‌شود که به PHP برسد. با کش صفحه
 *      لایت‌اسپید اکثر بازدیدها اصلا به PHP نمی‌رسند، پس رویداد مدام
 *      عقب می‌افتاد و «سلامت سایت» وردپرس هشدار می‌داد.
 *   ۲. هر اجرا گزینه autoload «cron» را بازنویسی می‌کند — ۱۴۴۰ نوشتن
 *      در روز، حتی وقتی صف خالی است.
 *   ۳. صف خالی هم هر دقیقه یک کوئری و یک قفل MySQL می‌گرفت.
 *
 * حالا هیچ رویداد تکرارشونده‌ای نیست. هر بار که آیتمی وارد صف می‌شود یا
 * پردازشی تمام می‌شود، *یک* رویداد یک‌باره برای زودترین زمان سررسید
 * زمان‌بندی می‌شود. صف خالی = هیچ رویدادی.
 * ─────────────────────────────────────────────────────────────────────
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Hodima_GI_Queue {

	public const HOOK     = 'hodima_gi_process_queue';
	private const LOCK    = 'hodima_gi_queue_mysql_lock';
	private const MAX_TRY = 3;

	public static function init(): void {
		add_action( self::HOOK, [ __CLASS__, 'run_cron' ] );
	}

	/* =================================================================
	 * افزودن
	 * ================================================================= */

	public static function push( string $url, string $type = 'URL_UPDATED', string $source = 'auto' ): bool {

		if ( ! Hodima_GI_Helper::is_valid_url( $url ) ) {
			return false;
		}

		$type = in_array( $type, [ 'URL_UPDATED', 'URL_DELETED' ], true ) ? $type : 'URL_UPDATED';

		global $wpdb;

		$table    = Hodima_Crawler_DB_Schema::get_queue_table();
		$hash     = Hodima_GI_Helper::url_hash( $url );
		$settings = Hodima_GI_Helper::get_settings();
		$delay    = max( 1, (int) ( $settings['queue_debounce_time'] ?? 15 ) ) * MINUTE_IN_SECONDS;
		$run_at   = time() + $delay;

		/*
		 * آخرین نیت برنده است.
		 *
		 * کلید یکتای جدول (url_hash, type) است، پس یک آدرس می‌توانست
		 * همزمان هم UPDATED و هم DELETED در صف داشته باشد (مثلا انتشار و
		 * بلافاصله انتقال به زباله‌دان) و هر دو سیگنال متناقض به گوگل
		 * ارسال می‌شد.
		 */
		$opposite = ( 'URL_UPDATED' === $type ) ? 'URL_DELETED' : 'URL_UPDATED';
		$wpdb->delete( $table, [ 'url_hash' => $hash, 'type' => $opposite ] );

		/*
		 * در ورود مجدد، شمارنده تلاش و زمان تنبیه صفر می‌شوند.
		 * نسخه قبلی این‌ها را نگه می‌داشت؛ آدرسی که دو بار شکست خورده بود
		 * و دوباره ویرایش می‌شد، سه ساعت منتظر می‌ماند و فقط یک فرصت دیگر
		 * داشت.
		 */
		$wpdb->query( $wpdb->prepare(
			"INSERT INTO {$table} (url_hash, url, type, source, execute_at, retries, next_retry)
			 VALUES (%s, %s, %s, %s, %d, 0, 0)
			 ON DUPLICATE KEY UPDATE url = VALUES(url), source = VALUES(source),
			     execute_at = VALUES(execute_at), retries = 0, next_retry = 0",
			$hash,
			esc_url_raw( $url ),
			$type,
			sanitize_key( $source ),
			$run_at
		) );

		Hodima_GI_Logger::log( 'در صف انتشار', [ 'url' => $url, 'type' => $type, 'src' => $source ], 'queue' );

		self::schedule_at( $run_at );

		return true;
	}

	/* =================================================================
	 * زمان‌بندی
	 * ================================================================= */

	/** اطمینان از اینکه رویدادی حداکثر در زمان $timestamp اجرا شود. */
	private static function schedule_at( int $timestamp ): void {

		$timestamp = max( time() + 30, $timestamp );
		$existing  = wp_next_scheduled( self::HOOK );

		if ( false !== $existing && $existing <= $timestamp ) {
			return; // رویدادی زودتر یا هم‌زمان از قبل هست
		}

		if ( false !== $existing ) {
			wp_unschedule_event( $existing, self::HOOK );
		}

		wp_schedule_single_event( $timestamp, self::HOOK );
	}

	/** زمان‌بندی بعدی بر اساس زودترین آیتم سررسید. */
	public static function schedule_next(): void {

		global $wpdb;
		$table = Hodima_Crawler_DB_Schema::get_queue_table();

		$next = $wpdb->get_var( "SELECT MIN(GREATEST(execute_at, next_retry)) FROM {$table}" );

		if ( null === $next ) {
			wp_clear_scheduled_hook( self::HOOK );
			return;
		}

		$next = (int) $next;

		// اگر سهمیه تمام است، زودتر از شروع روز بعد گوگل فایده ندارد
		if ( Hodima_GI_Helper::quota_remaining() <= 0 ) {
			$next = max( $next, Hodima_GI_Helper::next_quota_reset() + 60 );
		}

		self::schedule_at( $next );
	}

	/* =================================================================
	 * پردازش
	 * ================================================================= */

	public static function run_cron(): void {
		self::process_batch();
	}

	/**
	 * @param bool $force_all بدون در نظر گرفتن زمان سررسید (دکمه دستی پنل)
	 * @return array{sent:int, failed:int, quota:bool, locked:bool}
	 */
	public static function process_batch( bool $force_all = false ): array {

		global $wpdb;

		$result = [ 'sent' => 0, 'failed' => 0, 'quota' => false, 'locked' => false ];

		if ( ! $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', self::LOCK, $force_all ? 2 : 0 ) ) ) {
			$result['locked'] = true;
			return $result;
		}

		try {

			$table     = Hodima_Crawler_DB_Schema::get_queue_table();
			$now       = time();
			$remaining = Hodima_GI_Helper::quota_remaining();

			if ( $remaining <= 0 ) {
				$result['quota'] = true;
				return $result;
			}

			$limit = min( $force_all ? 50 : 20, $remaining );

			$items = $force_all
				? $wpdb->get_results( $wpdb->prepare(
					"SELECT * FROM {$table} ORDER BY execute_at ASC LIMIT %d", $limit
				), ARRAY_A )
				: $wpdb->get_results( $wpdb->prepare(
					"SELECT * FROM {$table} WHERE execute_at <= %d AND next_retry <= %d ORDER BY execute_at ASC LIMIT %d",
					$now, $now, $limit
				), ARRAY_A );

			$result = array_merge( $result, self::process_items( (array) $items ) );

		} finally {
			$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', self::LOCK ) );
			self::schedule_next();
		}

		return $result;
	}

	/**
	 * پردازش مجموعه‌ای از آیتم‌های صف.
	 * هم کرون و هم دکمه‌های پنل از همین مسیر استفاده می‌کنند.
	 *
	 * @param array<int, array<string, mixed>> $items
	 * @return array{sent:int, failed:int, quota:bool}
	 */
	public static function process_items( array $items ): array {

		global $wpdb;

		$table    = Hodima_Crawler_DB_Schema::get_queue_table();
		$settings = Hodima_GI_Helper::get_settings();
		$penalty  = max( 1, (int) ( $settings['queue_penalty_time'] ?? 3 ) ) * HOUR_IN_SECONDS;

		$out = [ 'sent' => 0, 'failed' => 0, 'quota' => false ];

		foreach ( $items as $item ) {

			$status = Hodima_Google_API::publish( (string) $item['url'], (string) $item['type'] );

			switch ( $status ) {

				case Hodima_Google_API::OK:
					if ( 'URL_UPDATED' === $item['type'] ) {
						Hodima_Crawler_DB_Queries::upsert_url_data( (string) $item['url'], [
							'api_sync_status'  => 1,
							'last_modified_at' => current_time( 'mysql' ),
							'reaction_time'    => null,
						] );
					}
					$wpdb->delete( $table, [ 'id' => (int) $item['id'] ] );
					$out['sent']++;
					break;

				case Hodima_Google_API::QUOTA:
					// این آدرس مقصر نیست — شمارنده تلاشش را مصرف نکن و دسته را متوقف کن
					$out['quota'] = true;
					break 2;

				case Hodima_Google_API::DISABLED:
					// ارسال خاموش است؛ آیتم در صف می‌ماند تا دوباره روشن شود
					break 2;

				case Hodima_Google_API::PERMANENT:
					Hodima_GI_Logger::log( 'از صف حذف شد: گوگل درخواست را رد کرد', (string) $item['url'], 'error' );
					$wpdb->delete( $table, [ 'id' => (int) $item['id'] ] );
					$out['failed']++;
					break;

				case Hodima_Google_API::RETRY:
				default:
					if ( (int) $item['retries'] < self::MAX_TRY ) {
						$wpdb->update( $table, [
							'retries'    => (int) $item['retries'] + 1,
							'next_retry' => time() + $penalty,
						], [ 'id' => (int) $item['id'] ] );
					} else {
						// نسخه قبلی بی‌صدا حذف می‌کرد
						Hodima_GI_Logger::log( 'از صف حذف شد: ' . self::MAX_TRY . ' تلاش ناموفق', (string) $item['url'], 'error' );
						$wpdb->delete( $table, [ 'id' => (int) $item['id'] ] );
					}
					$out['failed']++;
					break;
			}
		}

		return $out;
	}

	/* =================================================================
	 * خواندن برای پنل
	 * ================================================================= */

	public static function count(): int {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . Hodima_Crawler_DB_Schema::get_queue_table() );
	}

	public static function get_all(): array {
		global $wpdb;
		$table = Hodima_Crawler_DB_Schema::get_queue_table();
		return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY execute_at ASC LIMIT 1000", ARRAY_A ) ?: [];
	}

	public static function get_all_paginated( int $page = 1, int $per_page = 15 ): array {
		global $wpdb;
		$table    = Hodima_Crawler_DB_Schema::get_queue_table();
		$page     = max( 1, $page );
		$per_page = max( 1, $per_page );
		$total    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		$items    = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} ORDER BY execute_at ASC LIMIT %d OFFSET %d",
			$per_page,
			( $page - 1 ) * $per_page
		), ARRAY_A ) ?: [];

		return [ 'items' => $items, 'total' => $total, 'max_pages' => (int) ceil( $total / $per_page ) ];
	}
}
