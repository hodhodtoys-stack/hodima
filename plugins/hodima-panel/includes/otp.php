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
	 * بیشترین ارسال کل سایت در ساعت (فیلتر hodima_panel_otp_site_hourly).
	 *
	 * سقف شماره و IP به‌تنهایی جلوی کسی را که IP عوض می‌کند و به هزاران شماره کد
	 * می‌فرستد نمی‌گرفت (هزینه پیامک از حساب سایت). مشتریان عمده‌فروشی در ساعت خیلی
	 * کمتر از این کد می‌خواهند؛ با رسیدن به سقف، ورود با رمز هنوز باز است.
	 */
	public const SITE_HOUR = 60;

	private const SITE_KEY = 'hodima_panel_otpsite';

	/** قفل بررسی کد کهنه (پردازشی که وسط کار از کار افتاده) بعد از این چند ثانیه آزاد می‌شود. */
	private const LOCK_STALE = 30;

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
		if ( self::site_count() >= self::site_limit() ) {
			return [ 'ok' => false, 'error' => 'ارسال پیامک موقتا محدود شده است؛ با رمز عبور وارد شوید یا کمی بعد دوباره تلاش کنید.', 'wait' => 0 ];
		}

		$code = str_pad( (string) random_int( 0, ( 10 ** self::LENGTH ) - 1 ), self::LENGTH, '0', STR_PAD_LEFT );
		$sent = Sms::send_code( $mobile, $code );
		// شمارنده‌ها حتی برای ارسال ناموفق: درخواست پشت‌سرهم با خطای سرویس هم محدود شود
		set_transient( $mobile_key, (int) get_transient( $mobile_key ) + 1, HOUR_IN_SECONDS );
		set_transient( $ip_key, (int) get_transient( $ip_key ) + 1, HOUR_IN_SECONDS );
		self::bump_site();
		if ( ! $sent['ok'] ) {
			return [ 'ok' => false, 'error' => 'ارسال پیامک ناموفق بود؛ چند دقیقه دیگر دوباره تلاش کنید.', 'wait' => 0 ];
		}

		set_transient(
			self::store_key( $key ),
			[ 'mobile' => $mobile, 'purpose' => $purpose, 'hash' => wp_hash_password( $code ), 'attempts' => 0, 'expires' => time() + self::TTL ],
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
		// شمارنده تلاش‌ها خواندن-نوشتن است: بی قفل، صدها حدس هم‌زمان همه «تلاش صفرم» را
		// می‌دیدند و سقف ۳ تلاش عملا بی‌اثر بود (بازبینی امنیتی 0.2.2).
		if ( ! self::lock( $store ) ) {
			return [ 'ok' => false, 'error' => 'کد در حال بررسی است؛ چند ثانیه دیگر دوباره بزنید.', 'mobile' => '' ];
		}
		try {
			return self::check_locked( $store, $code, $purpose );
		} finally {
			self::unlock( $store );
		}
	}

	/**
	 * بررسی کد (داخل قفل).
	 *
	 * @param string $store   کلید ترنزینت.
	 * @param string $code    کد واردشده.
	 * @param string $purpose هدف مورد انتظار.
	 * @return array{ok: bool, error: string, mobile: string}
	 */
	private static function check_locked( string $store, string $code, string $purpose ): array {
		$data = get_transient( $store );
		if ( ! is_array( $data ) || $data['purpose'] !== $purpose ) {
			return [ 'ok' => false, 'error' => 'کد منقضی شده است؛ کد تازه بگیرید.', 'mobile' => '' ];
		}
		$mobile = (string) $data['mobile'];
		if ( (int) $data['attempts'] >= self::MAX_ATTEMPTS ) {
			delete_transient( $store );
			return [ 'ok' => false, 'error' => 'تلاش بیش از حد؛ کد تازه بگیرید.', 'mobile' => $mobile ];
		}
		++$data['attempts'];
		// عمر کد با هر تلاش تمدید نشود (تا 0.2.1 هر حدس اشتباه کد را ۵ دقیقه دیگر زنده نگه می‌داشت)
		set_transient( $store, $data, max( 1, (int) ( $data['expires'] ?? time() + self::TTL ) - time() ) );

		$code = Profile::digits( $code );
		if ( ! preg_match( '/^\d{' . self::LENGTH . '}$/', $code ) || ! wp_check_password( $code, (string) $data['hash'] ) ) {
			return [ 'ok' => false, 'error' => 'کد اشتباه است.', 'mobile' => $mobile ];
		}
		delete_transient( $store );
		delete_transient( self::counter_key( 'otpcool', $mobile ) );
		return [ 'ok' => true, 'error' => '', 'mobile' => $mobile ];
	}

	/** سقف ارسال کل سایت در ساعت. */
	public static function site_limit(): int {
		return max( 1, (int) apply_filters( 'hodima_panel_otp_site_hourly', self::SITE_HOUR ) );
	}

	/** شمار ارسال‌های کل سایت در ساعت جاری. */
	public static function site_count(): int {
		$data = get_transient( self::SITE_KEY );
		return is_array( $data ) ? (int) ( $data['n'] ?? 0 ) : 0;
	}

	/** یک ارسال به شمارنده ساعتی کل سایت (عمر از اولین ارسال همان ساعت، نه از آخرین). */
	private static function bump_site(): void {
		$data = get_transient( self::SITE_KEY );
		$data = is_array( $data ) ? $data : [ 'n' => 0, 'until' => time() + HOUR_IN_SECONDS ];
		$data['n'] = (int) ( $data['n'] ?? 0 ) + 1;
		set_transient( self::SITE_KEY, $data, max( 1, (int) ( $data['until'] ?? 0 ) - time() ) );
	}

	/**
	 * قفل یک جلسه کد با درج یکتای ردیف در wp_options.
	 *
	 * ترنزینت (و کش شیء) درج اتمی ندارد؛ INSERT IGNORE روی کلید یکتای option_name
	 * فقط برای یک درخواست موفق می‌شود. ردیف مستقیم نوشته و پاک می‌شود و هرگز از
	 * get_option خوانده نمی‌شود (بی‌اثر روی کش گزینه‌ها).
	 *
	 * @param string $store کلید ترنزینت جلسه.
	 */
	private static function lock( string $store ): bool {
		global $wpdb;
		$name = '_' . $store . '_lock';
		if ( self::insert_lock( $name ) ) {
			return true;
		}
		// قفل ماندگار از پردازشی که وسط کار از کار افتاد
		$held = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT option_value FROM %i WHERE option_name = %s', $wpdb->options, $name ) );
		if ( $held > 0 && time() - $held > self::LOCK_STALE ) {
			$wpdb->delete( $wpdb->options, [ 'option_name' => $name ], [ '%s' ] );
			return self::insert_lock( $name );
		}
		return false;
	}

	/**
	 * درج ردیف قفل؛ true فقط برای درخواستی که واقعا درج کرد.
	 *
	 * @param string $name نام ردیف.
	 */
	private static function insert_lock( string $name ): bool {
		global $wpdb;
		return 1 === (int) $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO %i (option_name, option_value, autoload) VALUES (%s, %s, 'off')", $wpdb->options, $name, (string) time() ) );
	}

	/**
	 * آزاد کردن قفل.
	 *
	 * @param string $store کلید ترنزینت جلسه.
	 */
	private static function unlock( string $store ): void {
		global $wpdb;
		$wpdb->delete( $wpdb->options, [ 'option_name' => '_' . $store . '_lock' ], [ '%s' ] );
	}
}
