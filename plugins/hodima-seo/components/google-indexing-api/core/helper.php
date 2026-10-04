<?php
/**
 * Google Indexing — Shared Helpers
 * Path: components/google-indexing-api/core/helper.php
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Hodima_GI_Helper {

	/** سهمیه پیش‌فرض Indexing API گوگل برای درخواست‌های publish در روز. */
	public const DEFAULT_DAILY_QUOTA = 200;

	public static function get_default_settings(): array {
		return [
			'init_done'           => 1,
			'google_post_types'   => [ 'post', 'page', 'product' ],
			'enable_google'       => 1,
			'cf_token'            => '',
			'cf_zone_id'          => '',
			'queue_debounce_time' => 15,
			'queue_penalty_time'  => 3,
			'stale_content_days'  => 60,
			'max_logs_count'      => 200,
			'daily_quota'         => self::DEFAULT_DAILY_QUOTA,
		];
	}

	public static function get_settings(): array {
		$settings = get_option( HODIMA_GI_OPTION_SETTINGS, [] );
		return wp_parse_args( is_array( $settings ) ? $settings : [], self::get_default_settings() );
	}

	public static function is_valid_url( string $url ): bool {
		return (bool) filter_var( esc_url_raw( trim( $url ) ), FILTER_VALIDATE_URL );
	}

	/* =================================================================
	 * آدرس
	 * ================================================================= */

	/**
	 * آدرس بدون پایه، هماهنگ با ماژول «آدرس تمیز» (router).
	 * باگ قبلی: وقتی روتر خاموش بود /product/ را از آدرس واقعی محصول حذف می‌کرد
	 * و آدرس ۴۰۴ به گوگل فرستاده می‌شد.
	 */
	public static function clean_url( string $url ): string {
		return function_exists( 'hodima_router_clean_url' ) ? hodima_router_clean_url( $url ) : $url;
	}

	/**
	 * شکل نرمال یک آدرس برای مقایسه و ساخت کلید.
	 *
	 * این تابع ریشه باگ «سلامت سئو» را حل می‌کند. وردپرس نامک فارسی را
	 * با هگز حروف کوچک می‌سازد (%d8%a7) ولی گوگل‌بات و مرورگرها طبق
	 * RFC 3986 حروف بزرگ می‌فرستند (%D8%A7). هر دو یک آدرس‌اند ولی md5
	 * آن‌ها متفاوت است — پس ردیف پینگ و ردیف خزش هرگز به هم نمی‌رسیدند و
	 * زمان واکنش هیچ‌وقت محاسبه نمی‌شد.
	 *
	 * خروجی: scheme و host با حروف کوچک، مسیر decode شده، بدون رشته
	 * کوئری و fragment.
	 */
	public static function normalize_url( string $url ): string {

		$parts = wp_parse_url( trim( $url ) );

		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return trim( $url );
		}

		$scheme = strtolower( $parts['scheme'] ?? 'https' );
		$host   = strtolower( $parts['host'] );
		$port   = isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '';
		$path   = rawurldecode( $parts['path'] ?? '/' );

		return $scheme . '://' . $host . $port . ( '' === $path ? '/' : $path );
	}

	/** کلید یکتای یک آدرس در جدول‌های ماژول. */
	public static function url_hash( string $url ): string {
		return md5( self::normalize_url( $url ) );
	}

	/* =================================================================
	 * سهمیه
	 * ================================================================= */

	public static function get_quota_limit(): int {
		$limit = (int) ( self::get_settings()['daily_quota'] ?? self::DEFAULT_DAILY_QUOTA );
		return max( 1, (int) apply_filters( 'hodima_gi_daily_quota', $limit ) );
	}

	/**
	 * روز سهمیه طبق ساعت گوگل.
	 *
	 * سهمیه Indexing API در نیمه‌شب به وقت اقیانوس آرام (America/Los_Angeles)
	 * صفر می‌شود، نه به وقت تهران. نسخه قبلی با current_time() کار می‌کرد
	 * که روز را حدود ۱۱ ساعت زودتر از گوگل صفر می‌کرد؛ یعنی شمارنده محلی
	 * می‌گفت سهمیه تازه است در حالی که گوگل هنوز ۴۲۹ برمی‌گرداند.
	 */
	public static function quota_day(): string {
		return ( new DateTimeImmutable( 'now', new DateTimeZone( 'America/Los_Angeles' ) ) )->format( 'Y-m-d' );
	}

	/** یونیکس‌تایم شروع روز سهمیه بعدی. */
	public static function next_quota_reset(): int {
		$tz = new DateTimeZone( 'America/Los_Angeles' );
		return ( new DateTimeImmutable( 'tomorrow', $tz ) )->getTimestamp();
	}

	public static function increment_quota(): void {
		$day   = self::quota_day();
		$quota = get_option( 'hodima_gi_daily_quota', [ 'date' => $day, 'count' => 0 ] );

		if ( ! is_array( $quota ) || ( $quota['date'] ?? '' ) !== $day ) {
			$quota = [ 'date' => $day, 'count' => 0 ];
		}

		$quota['count'] = (int) $quota['count'] + 1;
		update_option( 'hodima_gi_daily_quota', $quota, false );
	}

	public static function get_quota_usage(): int {
		$quota = get_option( 'hodima_gi_daily_quota', [] );
		return ( is_array( $quota ) && ( $quota['date'] ?? '' ) === self::quota_day() ) ? (int) $quota['count'] : 0;
	}

	/**
	 * گوگل با ۴۲۹ اعلام کرده سهمیه تمام شده است.
	 * این پرچم مستقل از شمارنده محلی است، چون ممکن است همان کلید در
	 * جای دیگری هم استفاده شود و گوگل زودتر از ۲۰۰ درخواست ما قطع کند.
	 */
	public static function mark_quota_exhausted(): void {
		set_transient( 'hodima_gi_quota_exhausted', self::quota_day(), max( 60, self::next_quota_reset() - time() ) );
	}

	public static function quota_remaining(): int {

		if ( get_transient( 'hodima_gi_quota_exhausted' ) === self::quota_day() ) {
			return 0;
		}

		return max( 0, self::get_quota_limit() - self::get_quota_usage() );
	}

	/* =================================================================
	 * شبکه
	 * ================================================================= */

	/**
	 * IP واقعی کاربر.
	 *
	 * نسخه قبلی بدون هیچ شرطی به X-Real-IP و X-Forwarded-For اعتماد
	 * می‌کرد. هر کسی می‌توانست با ارسال User-Agent گوگل‌بات و یک
	 * X-Forwarded-For برابر با IP واقعی گوگل، تأیید هویت را دور بزند و
	 * آمار «سلامت سئو» را آلوده کند. حالا از تابع مرکزی قالب استفاده
	 * می‌شود که فقط وقتی اتصال واقعا از Cloudflare آمده به هدر اعتماد
	 * می‌کند.
	 */
	public static function get_client_ip(): string {

		if ( function_exists( 'hodima_get_client_ip' ) ) {
			return hodima_get_client_ip();
		}

		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? trim( (string) $_SERVER['REMOTE_ADDR'] ) : '';
		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	/**
	 * اعتبارسنجی فایل JSON حساب سرویس.
	 *
	 * @return array{ok:bool, message:string, email:string}
	 */
	public static function validate_service_account( string $json ): array {

		$key = json_decode( $json, true );

		if ( ! is_array( $key ) ) {
			return [ 'ok' => false, 'message' => 'فایل JSON معتبر نیست.', 'email' => '' ];
		}

		foreach ( [ 'type', 'client_email', 'private_key', 'token_uri' ] as $field ) {
			if ( empty( $key[ $field ] ) || ! is_string( $key[ $field ] ) ) {
				return [ 'ok' => false, 'message' => "فیلد «{$field}» در فایل وجود ندارد.", 'email' => '' ];
			}
		}

		if ( 'service_account' !== $key['type'] ) {
			return [ 'ok' => false, 'message' => 'این فایل مربوط به Service Account نیست.', 'email' => '' ];
		}

		if ( ! str_contains( $key['private_key'], 'PRIVATE KEY' ) ) {
			return [ 'ok' => false, 'message' => 'کلید خصوصی در فایل معتبر نیست.', 'email' => '' ];
		}

		// کلید خراب (مثلا \n حذف‌شده هنگام کپی) قبلا ذخیره می‌شد و فقط هنگام
		// اولین ارسال با «امضای JWT ناموفق» در تاریخچه معلوم می‌شد.
		if ( function_exists( 'openssl_pkey_get_private' ) && false === @openssl_pkey_get_private( $key['private_key'] ) ) {
			return [ 'ok' => false, 'message' => 'کلید خصوصی فایل قابل خواندن نیست؛ فایل JSON را دوباره از Google Cloud دانلود کنید.', 'email' => '' ];
		}

		if ( ! is_email( $key['client_email'] ) ) {
			return [ 'ok' => false, 'message' => 'ایمیل حساب سرویس (client_email) معتبر نیست.', 'email' => '' ];
		}

		return [ 'ok' => true, 'message' => '', 'email' => sanitize_email( $key['client_email'] ) ];
	}

	/**
	 * JSON حساب سرویس گوگل.
	 *
	 * کلید خصوصی در دیتابیس (wp_options) به‌صورت متن ساده ذخیره می‌شد و با
	 * هر نشت یا بکاپ دیتابیس لو می‌رفت. اگر در wp-config.php تعریف شود،
	 * همان استفاده می‌شود و مقدار دیتابیس نادیده گرفته می‌شود:
	 *     define( 'HODIMA_GI_SERVICE_ACCOUNT_JSON', '{"type":"service_account",...}' );
	 */
	public static function service_account_json(): string {
		if ( defined( 'HODIMA_GI_SERVICE_ACCOUNT_JSON' ) && '' !== (string) HODIMA_GI_SERVICE_ACCOUNT_JSON ) {
			return (string) HODIMA_GI_SERVICE_ACCOUNT_JSON;
		}
		return (string) get_option( HODIMA_GI_OPTION_JSON, '' );
	}

	/**
	 * توکن و Zone کلادفلر؛ ثابت‌های wp-config.php بر تنظیمات پنل مقدم‌اند:
	 *     define( 'HODIMA_CF_API_TOKEN', '...' ); define( 'HODIMA_CF_ZONE_ID', '...' );
	 *
	 * @return array{token:string, zone:string}
	 */
	public static function cloudflare_credentials(): array {
		$settings = self::get_settings();
		return [
			'token' => defined( 'HODIMA_CF_API_TOKEN' ) ? (string) HODIMA_CF_API_TOKEN : (string) ( $settings['cf_token'] ?? '' ),
			'zone'  => defined( 'HODIMA_CF_ZONE_ID' ) ? (string) HODIMA_CF_ZONE_ID : (string) ( $settings['cf_zone_id'] ?? '' ),
		];
	}

	/** ایمیل حساب سرویس ذخیره‌شده، برای نمایش بدون افشای کلید. */
	public static function configured_account_email(): string {
		$key = json_decode( self::service_account_json(), true );
		return is_array( $key ) && ! empty( $key['client_email'] ) ? sanitize_email( (string) $key['client_email'] ) : '';
	}

	public static function litespeed_active(): bool {
		// Hodima Core 1.2+ (قبلا فقط از قالب)؛ فالبک: تابع قالب
		return ( function_exists( 'hodima_core_litespeed_active' ) && hodima_core_litespeed_active() )
			|| ( function_exists( 'hodima_litespeed_active' ) && hodima_litespeed_active() );
	}
}
