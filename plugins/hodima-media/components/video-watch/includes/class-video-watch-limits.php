<?php
/**
 * شمارنده‌های کوتاه‌مدت آمار ویدیو: تکراری، سقف روزانه IP، محدودیت نرخ (بارگذار: ../db-video-watch.php)
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * شمارنده‌های پنجره‌ای در جدول video_watch_seen.
 *
 * تا Media 2.0.9 این‌ها transient بودند: بدون کش شیء (object cache) هر
 * درخواست ثبت بازدید چند ردیف در wp_options می‌نوشت و می‌خواند (۲ درخواست =
 * ۱۱ ردیف در ابزار تست) و ردیف‌های منقضی تا پاک‌سازی روزانه وردپرس می‌ماندند.
 * حالا یک جدول کوچک با کلید اصلی: هر کلید = هش (پنجره زمانی داخل خود هش)،
 * شمارش اتمی با ON DUPLICATE KEY، و پاک‌سازی ردیف‌های منقضی در هر جمع‌بندی ۵ دقیقه‌ای.
 * داده خام (IP، نام مرورگر) ذخیره نمی‌شود، فقط md5 کلید.
 */
final class Hodima_Video_Watch_Limits {

	/** بیشترین ردیف منقضی که هر جمع‌بندی پاک می‌کند. */
	public const CLEANUP_LIMIT = 5000;

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'video_watch_seen';
	}

	/** پایان پنجره روزانه (نیمه‌شب گرینویچ). */
	public static function end_of_day(): int {
		return ( intdiv( time(), DAY_IN_SECONDS ) + 1 ) * DAY_IN_SECONDS;
	}

	/** پایان پنجره یک‌دقیقه‌ای. */
	public static function end_of_minute(): int {
		return ( intdiv( time(), MINUTE_IN_SECONDS ) + 1 ) * MINUTE_IN_SECONDS;
	}

	/**
	 * یک بار در پنجره؟ true = بار اول (و حالا ثبت شد)، false = از قبل بود، null = خطای دیتابیس.
	 *
	 * @param string $material متن کلید؛ پنجره زمانی باید داخلش باشد (مثلا تاریخ روز).
	 */
	public static function first( string $material, int $expires_at ): ?bool {
		global $wpdb;
		$run = static fn(): int|bool => $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( 'INSERT IGNORE INTO %i (seen_key, hits, expires_at) VALUES (%s, 1, %d)', self::table(), md5( $material ), $expires_at )
		);

		$affected = $run();
		if ( false === $affected && Hodima_Video_Watch_Setup::repair_tables() ) {
			$affected = $run();
		}
		return false === $affected ? null : 1 === (int) $affected;
	}

	/**
	 * شمارنده +۱ و مقدار تازه‌اش؛ ۰ = خطای دیتابیس.
	 *
	 * @param string $material متن کلید؛ پنجره زمانی باید داخلش باشد.
	 */
	public static function hit( string $material, int $expires_at ): int {
		global $wpdb;
		$key = md5( $material );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- شمارنده اتمی جدول خود افزونه
		$run = static fn(): int|bool => $wpdb->query(
			$wpdb->prepare(
				'INSERT INTO %i (seen_key, hits, expires_at) VALUES (%s, 1, %d) ON DUPLICATE KEY UPDATE hits = hits + 1',
				self::table(),
				$key,
				$expires_at
			)
		);

		$ok = $run();
		if ( false === $ok && Hodima_Video_Watch_Setup::repair_tables() ) {
			$ok = $run();
		}
		if ( false === $ok ) {
			return 0;
		}
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT hits FROM %i WHERE seen_key = %s', self::table(), $key ) );
		// phpcs:enable
	}

	/** پاک کردن ردیف‌های منقضی (هر جمع‌بندی ۵ دقیقه‌ای؛ رویداد cron همان). */
	public static function cleanup(): void {
		global $wpdb;
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( 'DELETE FROM %i WHERE expires_at <= %d LIMIT %d', self::table(), time(), self::CLEANUP_LIMIT )
		);
	}
}
