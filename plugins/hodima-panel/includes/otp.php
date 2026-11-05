<?php
/**
 * کد یک‌بار مصرف پیامکی: ساخت، محدودیت ارسال، بررسی.
 *
 * @package Hodima\Panel
 */

declare(strict_types=1);

namespace Hodima\Panel;

defined( 'ABSPATH' ) || exit;

/**
 * کد تأیید.
 *
 * هر کد به یک «کلید» بسته است (برای ورود: توکن تصادفی کوکی؛ برای پروفایل:
 * شناسه کاربر) و یک «هدف» (login یا profile) تا کد یک کار برای کار دیگر پذیرفته
 * نشود. خود کد فقط هش‌شده ذخیره می‌شود؛ کلید ترنزینت هم هش توکن است.
 */
final class Otp {

	public const LENGTH = 5;

	public const TTL = 5 * MINUTE_IN_SECONDS;

	public const COOLDOWN = 120;

	public const MAX_ATTEMPTS = 3;

	/** بیشترین ارسال به یک شماره در ساعت. */
	public const PER_MOBILE_HOUR = 5;

	/** بیشترین ارسال از یک IP در ساعت. */
	public const PER_IP_HOUR = 10;

	/**
	 * کلید ترنزینت یک جلسه کد.
	 *
	 * @param string $key کلید جلسه.
	 */
	private static function store_key( string $key ): string {
		return 'hodima_panel_otp_' . substr( hash( 'sha256', $key . '|' . wp_salt( 'nonce' ) ), 0, 40 );
	}

	/**
	 * کلید شمارنده با هش (شماره و IP خام در گزینه‌ها ذخیره نشوند).
	 *
	 * @param string $kind  نوع.
	 * @param string $value مقدار.
	 */
	private static function counter_key( string $kind, string $value ): string {
		return 'hodima_panel_' . $kind . '_' . substr( wp_hash( $value ), 0, 24 );
	}

	/**
	 * ثانیه‌های باقی‌مانده تا ارسال دوباره به این شماره.
	 *
	 * @param string $mobile شماره.
	 */
	public static function wait( string $mobile ): int {
		$until = (int) get_transient( self::counter_key( 'otpcool', $mobile ) );
		return max( 0, $until - time() );
	}

	/**
	 * ساخت و ارسال کد.
	 *
	 * @param string $key     کلید جلسه.
	 * @param string $mobile  شماره ۰۹…
	 * @param string $purpose login|profile.
	 * @param string $ip      IP درخواست‌کننده.
	 * @return array{ok: bool, error: string, wait: int}
	 */
	public static function issue( string $key, string $mobile, string $purpose, string $ip ): array {
		if ( ! Sms::ready() ) {
			return [ 'ok' => false, 'error' => 'ارسال پیامک فعلا فعال نیست؛ با رمز عبور وارد شوید یا با پشتیبانی تماس بگیرید.', 'wait' => 0 ];
		}
		$wait = self::wait( $mobile );
		if ( $wait > 0 ) {
			return [ 'ok' => false, 'error' => 'کد قبلی هنوز معتبر است؛ کمی صبر کنید.', 'wait' => $wait ];
		}
		$mobile_key = self::counter_key( 'otpmob', $mobile );
		$ip_key     = self::counter_key( 'otpip', $ip );
		if ( (int) get_transient( $mobile_key ) >= self::PER_MOBILE_HOUR || (int) get_transient( $ip_key ) >= self::PER_IP_HOUR ) {
			return [ 'ok' => false, 'error' => 'درخواست کد بیش از حد مجاز بود؛ یک ساعت دیگر تلاش کنید.', 'wait' => 0 ];
		}

		$code = str_pad( (string) random_int( 0, ( 10 ** self::LENGTH ) - 1 ), self::LENGTH, '0', STR_PAD_LEFT );
		$sent = Sms::send_code( $mobile, $code );
		// شمارنده‌ها حتی برای ارسال ناموفق: درخواست پشت‌سرهم با خطای سرویس هم محدود شود
		set_transient( $mobile_key, (int) get_transient( $mobile_key ) + 1, HOUR_IN_SECONDS );
		set_transient( $ip_key, (int) get_transient( $ip_key ) + 1, HOUR_IN_SECONDS );
		if ( ! $sent['ok'] ) {
			return [ 'ok' => false, 'error' => 'ارسال پیامک ناموفق بود؛ چند دقیقه دیگر دوباره تلاش کنید.', 'wait' => 0 ];
		}

		set_transient(
			self::store_key( $key ),
			[ 'mobile' => $mobile, 'purpose' => $purpose, 'hash' => wp_hash_password( $code ), 'attempts' => 0 ],
			self::TTL
		);
		set_transient( self::counter_key( 'otpcool', $mobile ), time() + self::COOLDOWN, self::COOLDOWN );
		return [ 'ok' => true, 'error' => '', 'wait' => self::COOLDOWN ];
	}

	/**
	 * شماره‌ای که برای این کلید کد گرفته (برای نمایش و ارسال دوباره).
	 *
	 * @param string $key کلید جلسه.
	 */
	public static function pending_mobile( string $key ): string {
		$data = get_transient( self::store_key( $key ) );
		return is_array( $data ) ? (string) $data['mobile'] : '';
	}

	/**
	 * بررسی کد.
	 *
	 * @param string $key     کلید جلسه.
	 * @param string $code    کد واردشده.
	 * @param string $purpose هدف مورد انتظار.
	 * @return array{ok: bool, error: string, mobile: string}
	 */
	public static function check( string $key, string $code, string $purpose ): array {
		$store = self::store_key( $key );
		$data  = get_transient( $store );
		if ( ! is_array( $data ) || $data['purpose'] !== $purpose ) {
			return [ 'ok' => false, 'error' => 'کد منقضی شده است؛ کد تازه بگیرید.', 'mobile' => '' ];
		}
		$mobile = (string) $data['mobile'];
		if ( (int) $data['attempts'] >= self::MAX_ATTEMPTS ) {
			delete_transient( $store );
			return [ 'ok' => false, 'error' => 'تلاش بیش از حد؛ کد تازه بگیرید.', 'mobile' => $mobile ];
		}
		++$data['attempts'];
		set_transient( $store, $data, self::TTL );

		$code = Profile::digits( $code );
		if ( ! preg_match( '/^\d{' . self::LENGTH . '}$/', $code ) || ! wp_check_password( $code, (string) $data['hash'] ) ) {
			return [ 'ok' => false, 'error' => 'کد اشتباه است.', 'mobile' => $mobile ];
		}
		delete_transient( $store );
		delete_transient( self::counter_key( 'otpcool', $mobile ) );
		return [ 'ok' => true, 'error' => '', 'mobile' => $mobile ];
	}
}
