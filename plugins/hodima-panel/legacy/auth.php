<?php
/**
 * ورود، ثبت‌نام و ورود با پیامک (منتقل‌شده از پنل قدیمی).
 *
 * باقی‌مانده برای مرحله ۴: ارسال واقعی پیامک (send_sms فعلا هیچ پیامکی
 * نمی‌فرستد)، ثبت‌نام فقط با تأیید پیامکی، دکمه «ارسال مجدد» و فیلد ضد ربات.
 *
 * تغییر این مرحله: پردازش فرم‌ها پیش از چاپ صفحه (اکشن hodima_panel_request)،
 * تا کوکی ورود و ریدایرکت روی هر هاستی کار کنند؛ ساخت ناموفق کاربر در ورود
 * پیامکی دیگر خطای مرگبار نمی‌دهد.
 *
 * @package Hodima\Panel
 */

declare(strict_types=1);

namespace Hodima\Panel\Legacy;

use Hodima\Panel\Page;

defined( 'ABSPATH' ) || exit;

final class Auth {

	private const SMS_COOLDOWN_SECONDS = 120;
	private const OTP_EXPIRY_SECONDS   = 300;
	private const MAX_OTP_ATTEMPTS     = 3;
	private const MAX_LOGIN_ATTEMPTS   = 5;
	private const GLOBAL_IP_LIMIT      = 10;

	public string $view         = 'login';
	public string $error        = '';
	public string $success      = '';
	public string $input_mobile = '';

	private static ?Auth $instance = null;

	public static function instance(): Auth {
		return self::$instance ??= new Auth();
	}

	/** پیش از چاپ صفحه (Page::before_output). */
	public static function boot(): void {
		add_action( 'hodima_panel_request', static function (): void {
			if ( ! is_user_logged_in() ) {
				self::instance()->handle_requests();
			}
		} );
	}

	private function get_real_ip(): string {
		$ip = function_exists( 'hodima_get_client_ip' ) ? (string) hodima_get_client_ip() : (string) ( $_SERVER['REMOTE_ADDR'] ?? '' );
		return filter_var( trim( $ip ), FILTER_VALIDATE_IP ) ?: '0.0.0.0';
	}

	private function sanitize_mobile( string $mobile ): string {
		$mobile = strtr( sanitize_text_field( $mobile ), [
			'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
			'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
		] );
		return preg_match( '/^09[0-9]{9}$/', $mobile ) ? $mobile : '';
	}

	/** پیامک فعلا ارسال نمی‌شود (مرحله ۴: ملی‌پیامک و دو سرویس جایگزین). */
	private function send_sms( string $mobile, string $code ): bool {
		unset( $mobile, $code );
		return true;
	}

	private function find_user( string $mobile ): ?\WP_User {
		$user = get_user_by( 'login', $mobile );
		if ( $user instanceof \WP_User ) {
			return $user;
		}
		$found = get_users( [ 'meta_key' => 'mobile', 'meta_value' => $mobile, 'number' => 1 ] );
		return ( $found && $found[0] instanceof \WP_User ) ? $found[0] : null;
	}

	public function handle_requests(): void {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! isset( $_POST['hodima_auth_nonce'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['hodima_auth_nonce'] ) ), 'hodima_auth_action' ) ) {
			$this->error = 'درخواست نامعتبر است. صفحه را رفرش کنید.';
			return;
		}
		if ( ! empty( $_POST['up_website_url'] ) ) {
			$this->error = 'درخواست مسدود شد.';
			return;
		}
		try {
			match ( true ) {
				isset( $_POST['login_with_password'] )                 => $this->process_password_login(),
				isset( $_POST['do_register'] )                         => $this->process_register(),
				isset( $_POST['send_otp'] ), isset( $_POST['resend_otp'] ) => $this->process_request_otp(),
				isset( $_POST['verify_otp'] )                          => $this->process_verify_otp(),
				default                                                => null,
			};
		} catch ( \RuntimeException $e ) {
			$this->error = $e->getMessage();
		}
	}

	private function process_password_login(): void {
		$this->view         = 'login';
		$this->input_mobile = $this->sanitize_mobile( wp_unslash( (string) ( $_POST['mobile'] ?? '' ) ) );
		$password           = (string) wp_unslash( $_POST['password'] ?? '' );
		$remember           = isset( $_POST['remember_me'] );
		if ( '' === $this->input_mobile || '' === $password ) {
			throw new \RuntimeException( 'شماره موبایل و رمز عبور الزامی است.' );
		}
		$key      = 'login_att_' . md5( $this->get_real_ip() . $this->input_mobile );
		$attempts = (int) get_transient( $key );
		if ( $attempts >= self::MAX_LOGIN_ATTEMPTS ) {
			throw new \RuntimeException( 'حساب موقتاً مسدود شد. ۱۵ دقیقه دیگر تلاش کنید.' );
		}
		$user = $this->find_user( $this->input_mobile );
		if ( $user && wp_check_password( $password, $user->data->user_pass, $user->ID ) ) {
			delete_transient( $key );
			$this->login_user( $user, false, $remember );
		}
		set_transient( $key, $attempts + 1, 15 * MINUTE_IN_SECONDS );
		throw new \RuntimeException( 'شماره موبایل یا رمز عبور اشتباه است.' );
	}

	private function process_register(): void {
		$this->view         = 'register';
		$this->input_mobile = $this->sanitize_mobile( wp_unslash( (string) ( $_POST['reg_mobile'] ?? '' ) ) );
		$password           = (string) wp_unslash( $_POST['reg_password'] ?? '' );
		$password_conf      = (string) wp_unslash( $_POST['reg_password_conf'] ?? '' );
		if ( '' === $this->input_mobile ) {
			throw new \RuntimeException( 'شماره موبایل نامعتبر است.' );
		}
		if ( strlen( $password ) < 8 ) {
			throw new \RuntimeException( 'رمز عبور باید حداقل ۸ کاراکتر باشد.' );
		}
		if ( $password !== $password_conf ) {
			throw new \RuntimeException( 'رمز عبور و تکرار آن مطابقت ندارند.' );
		}
		if ( username_exists( $this->input_mobile ) || $this->find_user( $this->input_mobile ) ) {
			throw new \RuntimeException( 'این شماره قبلاً ثبت شده است.' );
		}
		$this->login_user( $this->create_user( $this->input_mobile, $password ), true );
	}

	private function create_user( string $mobile, string $password ): \WP_User {
		$fake_email = $mobile . '@' . wp_parse_url( home_url(), PHP_URL_HOST );
		$user_id    = wp_create_user( $mobile, $password, $fake_email );
		if ( is_wp_error( $user_id ) ) {
			throw new \RuntimeException( $user_id->get_error_message() );
		}
		update_user_meta( $user_id, 'mobile', $mobile );
		$user = new \WP_User( $user_id );
		$user->set_role( get_option( 'default_role', 'subscriber' ) ?: 'subscriber' );
		return $user;
	}

	private function process_request_otp(): void {
		$this->view = 'otp_request';
		if ( isset( $_POST['otp_mobile'] ) ) {
			$this->input_mobile = $this->sanitize_mobile( wp_unslash( (string) $_POST['otp_mobile'] ) );
		} elseif ( isset( $_COOKIE['otp_session_token'] ) ) {
			$session            = get_transient( 'otp_sess_' . sanitize_key( wp_unslash( $_COOKIE['otp_session_token'] ) ) );
			$this->input_mobile = is_array( $session ) ? (string) ( $session['mobile'] ?? '' ) : '';
		}
		if ( '' === $this->input_mobile ) {
			throw new \RuntimeException( 'شماره موبایل نامعتبر است.' );
		}
		$cooldown_key = 'otp_cool_' . md5( $this->input_mobile );
		$ip_key       = 'otp_ip_' . md5( $this->get_real_ip() );
		$ip_attempts  = (int) get_transient( $ip_key );
		if ( $ip_attempts >= self::GLOBAL_IP_LIMIT ) {
			throw new \RuntimeException( 'مسدود شدید.' );
		}
		if ( get_transient( $cooldown_key ) ) {
			$this->view = 'otp_verify';
			throw new \RuntimeException( 'لطفاً صبر کنید.' );
		}
		$token = wp_generate_password( 32, false );
		$code  = (string) random_int( 10000, 99999 );
		set_transient( 'otp_sess_' . strtolower( $token ), [ 'mobile' => $this->input_mobile, 'code' => wp_hash_password( $code ), 'attempts' => 0 ], self::OTP_EXPIRY_SECONDS );
		set_transient( $cooldown_key, time() + self::SMS_COOLDOWN_SECONDS, self::SMS_COOLDOWN_SECONDS );
		set_transient( $ip_key, $ip_attempts + 1, HOUR_IN_SECONDS );
		setcookie( 'otp_session_token', strtolower( $token ), [ 'expires' => time() + self::OTP_EXPIRY_SECONDS, 'path' => COOKIEPATH, 'domain' => COOKIE_DOMAIN, 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Strict' ] );
		if ( ! $this->send_sms( $this->input_mobile, $code ) ) {
			throw new \RuntimeException( 'خطا در سرور پیامک.' );
		}
		$this->success = 'کد تایید ارسال شد.';
		$this->view    = 'otp_verify';
	}

	private function process_verify_otp(): void {
		$this->view = 'otp_verify';
		$token      = sanitize_key( wp_unslash( $_COOKIE['otp_session_token'] ?? '' ) );
		$code       = sanitize_text_field( wp_unslash( $_POST['otp_code'] ?? '' ) );
		if ( '' === $token || '' === $code ) {
			throw new \RuntimeException( 'اطلاعات ناقص است.' );
		}
		$data = get_transient( 'otp_sess_' . $token );
		if ( ! is_array( $data ) ) {
			throw new \RuntimeException( 'کد منقضی شده است.' );
		}
		$mobile             = (string) $data['mobile'];
		$this->input_mobile = $mobile;
		if ( (int) $data['attempts'] >= self::MAX_OTP_ATTEMPTS ) {
			delete_transient( 'otp_sess_' . $token );
			setcookie( 'otp_session_token', '', time() - 3600, COOKIEPATH, (string) COOKIE_DOMAIN );
			throw new \RuntimeException( 'تلاش بیش از حد. کد جدید بگیرید.' );
		}
		++$data['attempts'];
		set_transient( 'otp_sess_' . $token, $data, self::OTP_EXPIRY_SECONDS );
		if ( ! wp_check_password( $code, (string) $data['code'] ) ) {
			throw new \RuntimeException( 'کد اشتباه است.' );
		}
		delete_transient( 'otp_sess_' . $token );
		delete_transient( 'otp_cool_' . md5( $mobile ) );
		setcookie( 'otp_session_token', '', time() - 3600, COOKIEPATH, (string) COOKIE_DOMAIN );

		$user = $this->find_user( $mobile );
		$new  = null === $user;
		$this->login_user( $user ?? $this->create_user( $mobile, wp_generate_password( 16, true, true ) ), $new, true );
	}

	private function login_user( \WP_User $user, bool $is_new = false, bool $remember = false ): never {
		wp_set_current_user( $user->ID, $user->user_login );
		wp_set_auth_cookie( $user->ID, $remember );
		do_action( 'wp_login', $user->user_login, $user );
		$redirect = $is_new ? Page::url( [ 'section' => 'profile', 'welcome' => 'true' ] ) : Page::url();
		if ( ! empty( $_GET['redirect_to'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$redirect = wp_validate_redirect( esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ), $redirect ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		wp_safe_redirect( $redirect );
		exit;
	}
}

Auth::boot();
