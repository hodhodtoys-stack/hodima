<?php
/**
 * Google Indexing API client
 * Path: components/google-indexing-api/modules/google-api.php
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Hodima_Google_API {

	/* نتیجه یک ارسال — صف بر اساس این تصمیم می‌گیرد */
	public const OK        = 'ok';
	public const QUOTA     = 'quota';      // ۴۲۹ یا سهمیه محلی تمام — تا فردا صبر کن
	public const RETRY     = 'retry';      // خطای موقت (شبکه، ۵xx) — بعدا دوباره
	public const PERMANENT = 'permanent';  // ۴xx دیگر — تلاش دوباره فایده ندارد
	public const DISABLED  = 'disabled';   // ارسال خاموش یا کلید تنظیم نشده

	private const ENDPOINT = 'https://indexing.googleapis.com/v3/urlNotifications:publish';

	/**
	 * توکن دسترسی.
	 *
	 * تغییر: scope فقط indexing است. نسخه قبلی webmasters.readonly را
	 * هم درخواست می‌کرد که هیچ‌جای ماژول استفاده نمی‌شد — اصل حداقل
	 * دسترسی.
	 *
	 * @return string|false
	 */
	public static function get_token( bool $force = false ) {

		if ( ! $force ) {
			$cached = get_transient( 'hodima_gi_token' );
			if ( is_string( $cached ) && '' !== $cached ) {
				return $cached;
			}
		}

		$key = json_decode( (string) get_option( HODIMA_GI_OPTION_JSON, '' ), true );

		if ( ! is_array( $key ) || empty( $key['client_email'] ) || empty( $key['private_key'] ) || empty( $key['token_uri'] ) ) {
			return false;
		}

		$enc = static fn( string $d ): string => rtrim( strtr( base64_encode( $d ), '+/', '-_' ), '=' );
		$now = time();

		$header = $enc( (string) wp_json_encode( [ 'alg' => 'RS256', 'typ' => 'JWT' ] ) );
		$claim  = $enc( (string) wp_json_encode( [
			'iss'   => $key['client_email'],
			'scope' => 'https://www.googleapis.com/auth/indexing',
			'aud'   => $key['token_uri'],
			'iat'   => $now,
			'exp'   => $now + 3600,
		] ) );

		$signature = '';
		if ( ! openssl_sign( $header . '.' . $claim, $signature, $key['private_key'], 'SHA256' ) ) {
			Hodima_GI_Logger::log( 'امضای JWT ناموفق بود (کلید خصوصی نامعتبر)', [], 'error' );
			return false;
		}

		$res = wp_remote_post( $key['token_uri'], [
			'timeout' => 10,
			'body'    => [
				'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
				'assertion'  => $header . '.' . $claim . '.' . $enc( $signature ),
			],
		] );

		if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
			Hodima_GI_Logger::log( 'دریافت توکن از گوگل ناموفق بود', [], 'error' );
			return false;
		}

		$body  = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		$token = is_array( $body ) ? (string) ( $body['access_token'] ?? '' ) : '';

		if ( '' === $token ) {
			return false;
		}

		$ttl = max( 60, (int) ( $body['expires_in'] ?? 3600 ) - 300 );
		set_transient( 'hodima_gi_token', $token, $ttl );

		return $token;
	}

	/**
	 * ارسال یک آدرس به گوگل.
	 *
	 * @return string یکی از ثابت‌های OK / QUOTA / RETRY / PERMANENT / DISABLED
	 */
	public static function publish( string $url, string $type = 'URL_UPDATED', bool $retry_auth = true ): string {

		if ( empty( Hodima_GI_Helper::get_settings()['enable_google'] ) ) {
			return self::DISABLED;
		}

		/*
		 * سهمیه *قبل از* ارسال بررسی می‌شود.
		 *
		 * نسخه قبلی فقط بعد از موفقیت شمارنده را بالا می‌برد و هرگز قبل
		 * از ارسال چک نمی‌کرد. بعد از درخواست دویستم گوگل ۴۲۹ برمی‌گرداند،
		 * کد آن را «شکست آدرس» حساب می‌کرد، سه بار با فاصله سه ساعت
		 * دوباره تلاش و سپس آدرس را بی‌صدا از صف حذف می‌کرد.
		 */
		if ( Hodima_GI_Helper::quota_remaining() <= 0 ) {
			return self::QUOTA;
		}

		$type  = in_array( $type, [ 'URL_UPDATED', 'URL_DELETED' ], true ) ? $type : 'URL_UPDATED';
		$token = self::get_token();

		if ( ! $token ) {
			return self::DISABLED;
		}

		$url = Hodima_GI_Helper::clean_url( $url );

		$res = wp_remote_post( self::ENDPOINT, [
			'timeout' => 15,
			'headers' => [
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
			],
			'body'    => (string) wp_json_encode( [ 'url' => $url, 'type' => $type ] ),
		] );

		if ( is_wp_error( $res ) ) {
			Hodima_GI_Logger::log( 'خطای شبکه: ' . $res->get_error_message(), $url, 'error' );
			return self::RETRY;
		}

		$code = (int) wp_remote_retrieve_response_code( $res );

		if ( 401 === $code && $retry_auth ) {
			delete_transient( 'hodima_gi_token' );
			return self::publish( $url, $type, false );
		}

		if ( 429 === $code ) {
			Hodima_GI_Helper::mark_quota_exhausted();
			Hodima_GI_Logger::log( 'سهمیه روزانه گوگل تمام شد (۴۲۹) — ادامه بعد از نیمه‌شب به وقت اقیانوس آرام', $url, 'error' );
			return self::QUOTA;
		}

		if ( $code >= 200 && $code < 300 ) {
			Hodima_GI_Helper::increment_quota();
			Hodima_GI_Logger::log( 'URL_DELETED' === $type ? 'درخواست حذف ارسال شد' : 'منتشر شد', $url, 'success' );
			return self::OK;
		}

		if ( $code >= 500 ) {
			Hodima_GI_Logger::log( "خطای موقت گوگل ({$code})", $url, 'error' );
			return self::RETRY;
		}

		// ۴۰۳ معمولا یعنی حساب سرویس مالک دامنه در سرچ کنسول نیست
		$hint = ( 403 === $code ) ? ' — ایمیل حساب سرویس باید Owner سرچ کنسول باشد' : '';
		Hodima_GI_Logger::log( "رد شد ({$code}){$hint}", $url, 'error' );

		return self::PERMANENT;
	}

	/**
	 * سازگاری با کد قدیمی که فقط true/false انتظار دارد.
	 */
	public static function ping( string $url, string $type = 'URL_UPDATED', bool $retry = true ): bool {
		return self::OK === self::publish( $url, $type, $retry );
	}

	/** تست اتصال (از سهمیه publish کم نمی‌کند). */
	public static function get_status( string $url ): array {

		$token = self::get_token( true );

		if ( ! $token ) {
			return [ 'success' => false, 'message' => 'خطا: کلید حساب سرویس تنظیم نشده یا نامعتبر است.' ];
		}

		$res = wp_remote_get(
			'https://indexing.googleapis.com/v3/urlNotifications/metadata?url=' . rawurlencode( $url ),
			[ 'timeout' => 10, 'headers' => [ 'Authorization' => 'Bearer ' . $token ] ]
		);

		if ( is_wp_error( $res ) ) {
			return [ 'success' => false, 'message' => 'خطای شبکه در ارتباط با سرورهای گوگل.' ];
		}

		$code = (int) wp_remote_retrieve_response_code( $res );

		if ( 403 === $code ) {
			return [ 'success' => false, 'message' => 'خطای ۴۰۳: ایمیل حساب سرویس باید در سرچ کنسول دسترسی Owner داشته باشد.' ];
		}

		if ( 401 === $code ) {
			return [ 'success' => false, 'message' => 'خطای ۴۰۱: توکن دسترسی نامعتبر است.' ];
		}

		// ۴۰۴ یعنی «این آدرس هنوز اعلام نشده» — اتصال سالم است
		if ( 404 === $code || ( $code >= 200 && $code < 300 ) ) {
			return [
				'success' => true,
				'message' => sprintf(
					'ارتباط با گوگل سالم است. سهمیه باقی‌مانده امروز: %s از %s',
					number_format_i18n( Hodima_GI_Helper::quota_remaining() ),
					number_format_i18n( Hodima_GI_Helper::get_quota_limit() )
				),
			];
		}

		return [ 'success' => false, 'message' => 'خطای نامشخص با کد: ' . $code ];
	}
}
