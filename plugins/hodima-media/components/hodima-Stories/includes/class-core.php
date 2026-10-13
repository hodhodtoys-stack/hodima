<?php
/**
 * استوری‌ها — ثابت‌ها، داده ذخیره‌شده و راه‌اندازی.
 *
 * @package Hodima_Media
 */

declare(strict_types=1);

namespace Hodima\Stories;

defined( 'ABSPATH' ) || exit;

/**
 * هسته ماژول استوری.
 */
class Core {

	/** گزینه فهرست استوری‌ها (عوض نشود). */
	public const OPTION_NAME = 'hodima_stories_items';

	/** گزینه تنظیمات نمایش (عوض نشود). */
	public const SETTINGS_NAME = 'hodima_stories_settings';

	/** بیشترین تعداد استوری. */
	public const MAX_ITEMS = 50;

	/** نسخه ماژول (نشان پیشخوان و نسخه پشتیبان فایل‌ها). */
	public const VERSION = '3.1.0';

	/**
	 * تنظیمات نمایش.
	 *
	 * Seen_to_end: استوری‌های دیده‌شده به انتهای ردیف بروند؛ پیش‌فرض خاموش
	 * (ترتیب سایت دقیقا ترتیب پنل).
	 *
	 * @return array{seen_to_end: bool}
	 */
	public static function settings(): array {
		$saved = get_option( self::SETTINGS_NAME, [] );
		return [ 'seen_to_end' => is_array( $saved ) && ! empty( $saved['seen_to_end'] ) ];
	}

	/**
	 * فهرست استوری‌ها، همیشه آرایه‌ای از آرایه‌ها (داده خراب از مهاجرت یا
	 * بکاپ هشدار foreach نمی‌دهد).
	 *
	 * @return list<array<string, mixed>>
	 */
	public static function items(): array {
		$items = get_option( self::OPTION_NAME, [] );
		if ( ! is_array( $items ) ) {
			return [];
		}
		return array_values( array_filter( $items, 'is_array' ) );
	}

	/**
	 * آدرس و نسخه یک فایل (نسخه = زمان تغییر؛ نسخه ثابت به‌روزرسانی را پنهان می‌کرد).
	 *
	 * @return array{0: string, 1: string}
	 */
	public static function asset( string $relative ): array {
		$path = HODIMA_MEDIA_DIR . '/components/hodima-Stories/' . $relative;
		return [
			HODIMA_MEDIA_URL . '/components/hodima-Stories/' . $relative,
			file_exists( $path ) ? (string) filemtime( $path ) : self::VERSION,
		];
	}

	/** راه‌اندازی همه بخش‌ها. */
	public static function init(): void {
		Analytics\WP_Story_Setup::init();
		Admin::init();
		Frontend::init();
		Analytics_API::init();
		StoryPlayer\Core::init();
	}
}
