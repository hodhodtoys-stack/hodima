<?php
/**
 * استوری‌ها — توابع کمکی (اعتبار لینک، کاور، محدودیت نرخ).
 *
 * @package Hodima_Media
 */

declare(strict_types=1);

namespace Hodima\Stories;

defined( 'ABSPATH' ) || exit;

/**
 * توابع کمکی.
 */
class Helpers {

	/**
	 * پسوندهای مجاز ویدیو.
	 *
	 * @return list<string>
	 */
	public static function allowed_video_exts(): array {
		return [ 'mp4' ];
	}

	/** لینک ویدیو معتبر است؟ (http/https و فایل mp4) */
	public static function is_valid_video_url( string $url ): bool {
		$url = trim( $url );
		if ( '' === $url || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return false;
		}
		$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
		if ( ! in_array( $scheme, [ 'http', 'https' ], true ) ) {
			return false;
		}
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$ext  = strtolower( (string) wp_check_filetype( $path )['ext'] );
		return in_array( $ext, self::allowed_video_exts(), true );
	}

	/** شکل یکسان لینک برای تشخیص تکراری (بدون query و fragment، دامنه کوچک). */
	public static function normalize_url( string $url ): string {
		$parts = wp_parse_url( esc_url_raw( $url ) );
		if ( ! is_array( $parts ) ) {
			return esc_url_raw( $url );
		}
		$scheme = strtolower( $parts['scheme'] ?? 'https' );
		$host   = strtolower( $parts['host'] ?? '' );
		$port   = isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '';
		$path   = $parts['path'] ?? '';
		return "{$scheme}://{$host}{$port}{$path}";
	}

	/**
	 * مشکل ردیف‌ها: لینک نامعتبر یا ویدیوی تکراری (فقط اولی نمایش داده می‌شود).
	 *
	 * پنل، ذخیره و سایت همه از همین یک تابع می‌خوانند تا «چه چیزی نمایش
	 * داده می‌شود» یک قاعده داشته باشد.
	 *
	 * @param list<array<string, mixed>> $items استوری‌ها.
	 * @return array<int, 'invalid'|'duplicate'> شماره ردیف (از صفر) ← مشکل.
	 */
	public static function problems( array $items ): array {
		$seen = [];
		$out  = [];
		foreach ( $items as $i => $item ) {
			$link = (string) ( $item['link'] ?? '' );
			if ( ! self::is_valid_video_url( $link ) ) {
				$out[ $i ] = 'invalid';
				continue;
			}
			$norm = self::normalize_url( $link );
			if ( isset( $seen[ $norm ] ) ) {
				$out[ $i ] = 'duplicate';
				continue;
			}
			$seen[ $norm ] = true;
		}
		return $out;
	}

	/**
	 * شناسه تازه یکتا برای استوری.
	 *
	 * @param array<int, true> $used شناسه‌های گرفته‌شده.
	 */
	public static function new_uid( array $used ): int {
		do {
			$uid = wp_rand( 1, 4294967295 );
		} while ( isset( $used[ $uid ] ) );
		return $uid;
	}

	/** شناسه کاور فقط اگر تصویر باشد. */
	public static function validate_cover_id( mixed $id ): int {
		$id = absint( $id );
		return ( $id > 0 && wp_attachment_is_image( $id ) ) ? $id : 0;
	}

	/** لینک دکمه محصول: اختیاری (خالی معتبر است)؛ فقط http/https. */
	public static function is_valid_link_url( string $url ): bool {
		$url = trim( $url );
		if ( '' === $url ) {
			return true;
		}
		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return false;
		}
		$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
		return in_array( $scheme, [ 'http', 'https' ], true );
	}

	/** تصویر جایگزین وقتی کاور ندارد (SVG درون‌خطی). */
	public static function get_placeholder_cover(): string {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120"><rect width="100%" height="100%" fill="#f2f2f2"/><path d="M20 90l25-30 20 24 15-18 20 24" fill="none" stroke="#bbb" stroke-width="6"/><circle cx="42" cy="40" r="10" fill="#ccc"/></svg>';
		return 'data:image/svg+xml,' . rawurlencode( $svg );
	}

	/** آدرس کاور در اندازه خواسته‌شده، یا تصویر جایگزین. */
	public static function get_cover_url( int $id, string $size = 'thumbnail' ): string {
		$url = $id ? wp_get_attachment_image_url( $id, $size ) : '';
		return $url ? $url : self::get_placeholder_cover();
	}

	/**
	 * پاک‌سازی آدرس تصویر برای خروجی؛ data: هم مجاز است.
	 *
	 * باگ قبلی: esc_url پروتکل data: را نمی‌پذیرد، پس تصویر جایگزین استوری
	 * بی‌کاور src خالی می‌گرفت.
	 */
	public static function esc_img_src( string $url ): string {
		return esc_url( $url, [ 'http', 'https', 'data' ] );
	}

	/**
	 * محدودیت نرخ ساده برای ثبت آمار (هر IP واقعی، پنجره چرخان).
	 * پشت کلادفلر IP از تابع Core خوانده می‌شود، نه REMOTE_ADDR.
	 */
	public static function check_rate_limit( int $limit = 30, int $window = 60 ): bool {
		$key = function_exists( 'hodima_rate_limit_key' )
			? hodima_rate_limit_key( 'hs_rl_' )
			: 'hs_rl_' . md5( isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown' );

		$count = (int) get_transient( $key );
		if ( $count >= $limit ) {
			return false;
		}

		set_transient( $key, $count + 1, $window );
		return true;
	}
}
