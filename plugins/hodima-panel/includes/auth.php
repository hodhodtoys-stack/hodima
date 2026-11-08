<?php
/**
 * ورود با رمز، ورود/ثبت‌نام با کد پیامکی.
 *
 * @package Hodima\Panel
 */

declare(strict_types=1);

namespace Hodima\Panel;

defined( 'ABSPATH' ) || exit;

/**
 * ورود و ثبت‌نام.
 *
 * پنل قدیمی (گزارش، مورد ۷، ۸ و ۲۱): پیامک ارسال نمی‌شد؛ ثبت‌نام با رمز هیچ
 * تأیید شماره‌ای نداشت (هر کس با شماره دیگری حساب می‌ساخت و صاحب شماره بعدا
 * «این شماره ثبت شده» می‌دید)؛ محدودیت ورود با رمز فقط IP+شماره بود (با عوض
 * کردن IP بی‌نهایت تلاش). حالا ثبت‌نام فقط با کد پیامکی است (حساب تازه بعد از
 * تأیید شماره ساخته می‌شود؛ رمز را بعدا در پروفایل می‌گذارد) و ورود با رمز برای
 * هر شماره هم سقف جدا دارد.
 */
final class Auth {

	public const COOKIE = 'hodima_panel_otp';

	public const NONCE = 'hodima_auth_action';

	/** نام فیلد ضد ربات (پنهان؛ نام عمومی مثل website را پرکن خودکار مرورگر پر می‌کرد). */
	public const TRAP = 'hodima_hp_field';

	private const LOGIN_PER_IP = 5;

	private const LOGIN_PER_MOBILE = 10;

	/**
	 * سقف تلاش رمز از یک IP روی همه شماره‌ها (۱۵ دقیقه).
	 *
	 * سقف LOGIN_PER_IP برای «IP و شماره» با هم است؛ تا 0.2.1 از یک IP می‌شد روی
	 * شماره‌های زیاد بی‌سقف رمز امتحان کرد (رمزهای لورفته سایت‌های دیگر).
	 */
	private const LOGIN_PER_IP_TOTAL = 20;

	/** نما: login|otp_request|otp_verify. */
	public string $view = 'login';

	public string $error = '';

	public string $success = '';

	public string $mobile = '';

	/** ثانیه تا ارسال دوباره. */
	public int $wait = 0;

	private static ?Auth $instance = null;

	/** نمونه (همان که نما می‌خواند). */
	public static function instance(): Auth {
		return self::$instance ??= new Auth();
	}

	/** ثبت هوک. */
	public static function boot(): void {
		add_action(
			'hodima_panel_request',
			static function (): void {
				if ( ! is_user_logged_in() ) {
					self::instance()->handle();
				}
			}
		);
	}

	/** IP درخواست (Core اگر هست). */
	public static function ip(): string {
		$ip = function_exists( 'hodima_get_client_ip' ) ? (string) hodima_get_client_ip() : sanitize_text_field( wp_unslash( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) ) );
		return (string) ( filter_var( trim( $ip ), FILTER_VALIDATE_IP ) ?: '0.0.0.0' );
	}

	/**
	 * شماره موبایل یکدست (۰۹…) یا ''.
	 *
	 * @param string $raw ورودی.
	 */
	public static function normalize_mobile( string $raw ): string {
		$mobile = Profile::digits( sanitize_text_field( $raw ) );
		$mobile = (string) preg_replace( '/^(\+98|0098|98)(?=9\d{9}$)/', '', $mobile );
		if ( preg_match( '/^9\d{9}$/', $mobile ) ) {
			$mobile = '0' . $mobile;
		}
		return preg_match( '/^09\d{9}$/', $mobile ) ? $mobile : '';
	}

	/**
	 * کاربر یک شماره (نام کاربری یا متای mobile).
	 *
	 * @param string $mobile ۰۹…
	 */
	public static function find_user( string $mobile ): ?\WP_User {
		$user = get_user_by( 'login', $mobile );
		if ( $user instanceof \WP_User ) {
			return $user;
		}
		$found = get_users(
			[
				'meta_key'   => 'mobile', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => $mobile, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'number'     => 1,
			]
		);
		return ( $found && $found[0] instanceof \WP_User ) ? $found[0] : null;
	}

	/**
	 * کاربرِ شماره تأییدشده (فقط متای mobile که پنل بعد از کد پیامکی می‌نویسد).
	 *
	 * ورود با کد فقط به این حساب: نام کاربری ۰۹… را هر کسی از ثبت‌نام ووکامرس/وردپرس
	 * می‌توانست بی تأیید شماره بسازد و تا 0.2.1 صاحب واقعی شماره بعد از کد درست وارد
	 * همان حساب می‌شد (سفارش و فیشش را صاحب حساب می‌دید).
	 *
	 * @param string $mobile ۰۹…
	 */
	public static function find_verified( string $mobile ): ?\WP_User {
		$found = get_users(
			[
				'meta_key'   => 'mobile', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => $mobile, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'number'     => 1,
			]
		);
		return ( $found && $found[0] instanceof \WP_User ) ? $found[0] : null;
	}

	/** کلید جلسه کد از کوکی (یا ''). */
	private function token(): string {
		return sanitize_key( wp_unslash( (string) ( $_COOKIE[ self::COOKIE ] ?? '' ) ) );
	}

	/**
	 * کوکی جلسه کد.
	 *
	 * @param string $token مقدار ('' = حذف).
	 */
	private function set_token( string $token ): void {
		$_COOKIE[ self::COOKIE ] = $token;
		if ( headers_sent() ) {
			return;
		}
		setcookie(
			self::COOKIE,
			$token,
			[
				'expires'  => '' === $token ? time() - HOUR_IN_SECONDS : time() + Otp::TTL,
				'path'     => COOKIEPATH,
				'domain'   => (string) COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			]
		);
	}

	/** پردازش فرم‌های ورود (پیش از چاپ صفحه). */
	public function handle(): void {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! isset( $_POST['hodima_auth_nonce'] ) ) {
			// ارسال دوباره بعد از رفرش صفحه: همان شماره، نمای کد
			$pending = Otp::pending_mobile( $this->token() );
			if ( '' !== $pending ) {
				$this->view   = 'otp_verify';
				$this->mobile = $pending;
				$this->wait   = Otp::wait( $pending );
			}
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hodima_auth_nonce'] ) ), self::NONCE ) ) {
			$this->error = 'نشست صفحه منقضی شده است؛ صفحه را دوباره باز کنید.';
			return;
		}
		if ( ! empty( $_POST[ self::TRAP ] ) ) {
			$this->error = 'درخواست پذیرفته نشد.';
			return;
		}
		match ( true ) {
			isset( $_POST['login_with_password'] ) => $this->password_login(),
			isset( $_POST['send_otp'] )            => $this->request_code( self::normalize_mobile( wp_unslash( (string) ( $_POST['otp_mobile'] ?? '' ) ) ) ),
			isset( $_POST['resend_otp'] )          => $this->request_code( Otp::pending_mobile( $this->token() ) ),
			isset( $_POST['verify_otp'] )          => $this->verify_code( sanitize_text_field( wp_unslash( (string) ( $_POST['otp_code'] ?? '' ) ) ) ),
			default                                => null,
		};
	}

	// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce در handle() بررسی شده است.

	/**
	 * ورود با رمز با سقف تلاش برای IP+شماره و جدا برای هر شماره.
	 *
	 * رمز با wp_authenticate() سنجیده می‌شود (نه wp_check_password مستقیم): فیلتر authenticate
	 * و رویداد wp_login_failed اجرا می‌شوند تا افزونه‌های امنیتی (محدودیت ورود، مسدودسازی،
	 * دومرحله‌ای) این فرم را هم ببینند — تا 0.2.0 کاملا دور زده می‌شدند.
	 */
	private function password_login(): void {
		$this->view   = 'login';
		$this->mobile = self::normalize_mobile( wp_unslash( (string) ( $_POST['mobile'] ?? '' ) ) );
		$password     = (string) wp_unslash( $_POST['password'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- رمز پاک‌سازی نمی‌شود.
		if ( '' === $this->mobile || '' === $password ) {
			$this->error = 'شماره موبایل و رمز عبور الزامی است.';
			return;
		}
		$ip_key     = 'hodima_panel_login_' . substr( wp_hash( self::ip() . '|' . $this->mobile ), 0, 24 );
		$mobile_key = 'hodima_panel_loginm_' . substr( wp_hash( $this->mobile ), 0, 24 );
		$total_key  = 'hodima_panel_loginip_' . substr( wp_hash( self::ip() ), 0, 24 );
		if ( (int) get_transient( $ip_key ) >= self::LOGIN_PER_IP || (int) get_transient( $mobile_key ) >= self::LOGIN_PER_MOBILE
			|| (int) get_transient( $total_key ) >= self::LOGIN_PER_IP_TOTAL ) {
			$this->error = 'تلاش بیش از حد؛ ۱۵ دقیقه دیگر دوباره امتحان کنید یا با کد پیامکی وارد شوید.';
			return;
		}
		$found  = self::find_user( $this->mobile );
		$result = wp_authenticate( $found ? $found->user_login : $this->mobile, $password );
		if ( $result instanceof \WP_User ) {
			delete_transient( $ip_key );
			if ( Profile::is_staff( $result ) ) {
				$this->staff_error();
				return;
			}
			$this->login( $result, false, isset( $_POST['remember_me'] ) );
		}
		set_transient( $ip_key, (int) get_transient( $ip_key ) + 1, 15 * MINUTE_IN_SECONDS );
		set_transient( $mobile_key, (int) get_transient( $mobile_key ) + 1, 15 * MINUTE_IN_SECONDS );
		set_transient( $total_key, (int) get_transient( $total_key ) + 1, 15 * MINUTE_IN_SECONDS );
		$this->error = 'شماره موبایل یا رمز عبور اشتباه است.';
	}

	/**
	 * حساب مدیر/کارمند از پنل مشتری وارد نمی‌شود (خواست کاربر).
	 *
	 * ورود با کد پیامکی یا رمزِ این فرم، دومرحله‌ای و قانون‌های ورود wp-login را ندارد؛ با یک
	 * کد پیامکی (یا سیم‌کارت ربوده‌شده) حساب مدیر باز می‌شد. فقط بعد از اثبات مالکیت (رمز
	 * درست یا کد درست) گفته می‌شود، تا مدیر بودن یک شماره به هر کسی لو نرود.
	 */
	private function staff_error(): void {
		$this->error = 'این حساب مدیریتی است و از این صفحه وارد نمی‌شود؛ از صفحه ورود وردپرس (' . wp_login_url() . ') وارد شوید.';
	}

	// phpcs:enable

	/**
	 * درخواست کد (ورود یا ثبت‌نام؛ هر دو یکی است).
	 *
	 * @param string $mobile شماره یکدست‌شده.
	 */
	private function request_code( string $mobile ): void {
		$this->view   = 'otp_request';
		$this->mobile = $mobile;
		if ( '' === $mobile ) {
			$this->error = 'شماره موبایل نامعتبر است (مثلا ۰۹۱۲۳۴۵۶۷۸۹).';
			return;
		}
		$token = $this->token();
		if ( '' === $token || Otp::pending_mobile( $token ) !== $mobile ) {
			$token = strtolower( wp_generate_password( 32, false ) );
		}
		$result     = Otp::issue( $token, $mobile, 'login', self::ip() );
		$this->wait = $result['wait'];
		if ( ! $result['ok'] ) {
			$this->error = $result['error'];
			if ( $result['wait'] > 0 && '' !== Otp::pending_mobile( $token ) ) {
				$this->view = 'otp_verify';
			}
			return;
		}
		$this->set_token( $token );
		$this->success = 'کد تأیید به ' . $mobile . ' پیامک شد.';
		$this->view    = 'otp_verify';
	}

	/**
	 * بررسی کد؛ کاربر موجود وارد می‌شود و شماره تازه حساب می‌گیرد.
	 *
	 * @param string $code کد واردشده.
	 */
	private function verify_code( string $code ): void {
		$this->view   = 'otp_verify';
		$token        = $this->token();
		$this->mobile = Otp::pending_mobile( $token );
		$result       = Otp::check( $token, $code, 'login' );
		if ( ! $result['ok'] ) {
			$this->error = $result['error'];
			$this->wait  = '' !== $this->mobile ? Otp::wait( $this->mobile ) : 0;
			if ( '' === Otp::pending_mobile( $token ) ) {
				$this->view = 'otp_request';
			}
			return;
		}
		$this->set_token( '' );
		$user = self::find_verified( $result['mobile'] );
		if ( ! $user && get_user_by( 'login', $result['mobile'] ) instanceof \WP_User ) {
			$this->view  = 'login';
			$this->error = 'برای این شماره حسابی هست که شماره‌اش با کد تأیید نشده است؛ با رمز عبور وارد شوید یا با پشتیبانی تماس بگیرید.';
			return;
		}
		if ( $user && Profile::is_staff( $user ) ) {
			$this->view = 'login';
			$this->staff_error();
			return;
		}
		if ( ! $user ) {
			$user = $this->create_user( $result['mobile'] );
			if ( ! $user ) {
				$this->view  = 'otp_request';
				$this->error = 'ساخت حساب ناموفق بود؛ دوباره تلاش کنید.';
				return;
			}
			$this->login( $user, true, true );
		}
		$this->login( $user, false, true );
	}

	/**
	 * حساب تازه برای شماره تأییدشده.
	 *
	 * @param string $mobile ۰۹…
	 */
	public static function create_user( string $mobile ): ?\WP_User {
		$host    = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$user_id = wp_insert_user(
			[
				'user_login' => $mobile,
				'user_pass'  => wp_generate_password( 24, true, true ),
				'user_email' => $mobile . '@' . $host,
				'role'       => null !== get_role( 'customer' ) ? 'customer' : (string) get_option( 'default_role', 'subscriber' ),
			]
		);
		if ( is_wp_error( $user_id ) ) {
			return null;
		}
		update_user_meta( $user_id, 'mobile', $mobile );
		update_user_meta( $user_id, 'billing_phone', $mobile );
		$user = get_userdata( $user_id );
		return $user instanceof \WP_User ? $user : null;
	}

	/**
	 * ورود و ریدایرکت.
	 *
	 * @param \WP_User $user     کاربر.
	 * @param bool     $is_new   حساب تازه (به پروفایل).
	 * @param bool     $remember ماندگار.
	 */
	private function login( \WP_User $user, bool $is_new, bool $remember ): never {
		wp_set_current_user( $user->ID, $user->user_login );
		wp_set_auth_cookie( $user->ID, $remember, is_ssl() );
		do_action( 'wp_login', $user->user_login, $user ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- هوک خود وردپرس.
		$redirect = $is_new ? Page::url( [ 'section' => 'profile', 'msg' => 'welcome' ] ) : Page::url();
		if ( ! $is_new && ! empty( $_GET['redirect_to'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط مقصد؛ wp_validate_redirect.
			$redirect = wp_validate_redirect( esc_url_raw( wp_unslash( (string) $_GET['redirect_to'] ) ), $redirect ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		wp_safe_redirect( $redirect );
		exit;
	}
}
