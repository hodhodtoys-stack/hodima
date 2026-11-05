<?php
/**
 * پروفایل مشتری: کامل بودن، خواندن و ذخیره در فیلدهای صورت‌حساب ووکامرس، قفل پنل.
 *
 * @package Hodima\Panel
 */

declare(strict_types=1);

namespace Hodima\Panel;

defined( 'ABSPATH' ) || exit;

/**
 * پروفایل.
 *
 * پنل قدیمی (گزارش، مورد ۲۲):
 * - آدرس و کد پستی را در متاهای اختصاصی user_address/user_postcode می‌گذاشت؛ صفحه
 *   پرداخت ووکامرس و فاکتورها آن را نمی‌دیدند و مشتری باید دوباره می‌نوشت.
 * - «کامل بودن» یک پرچم (up_profile_completed) بود که فقط فرم پنل می‌زد؛ پس مشتری
 *   قدیمی ووکامرس با آدرس کامل و حتی مدیر سایت هم قفل می‌شدند.
 * حالا داده در همان فیلدهای ووکامرس (billing_*) است و کامل بودن از خود داده حساب می‌شود.
 */
final class Profile {

	/** بخش‌هایی که با پروفایل ناقص هم باز است (پشتیبانی: مشتری گیرکرده بتواند بپرسد). */
	public const OPEN_SECTIONS = [ 'profile', 'support' ];

	/** ثبت هوک‌ها. */
	public static function boot(): void {
		add_action( 'hodima_panel_request', [ self::class, 'enforce_lock' ], 1 );
		add_action( 'wp_ajax_hodima_panel_profile', [ self::class, 'ajax_save' ] );
		add_action( 'wp_ajax_hodima_panel_profile_code', [ self::class, 'ajax_code' ] );
	}

	/**
	 * رقم فارسی/عربی ← لاتین و حذف فاصله و خط تیره (همان قاعده Iran_Input در Commerce).
	 *
	 * @param string $value ورودی.
	 */
	public static function digits( string $value ): string {
		$value = strtr(
			trim( $value ),
			[
				'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
				'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
			]
		);
		return (string) preg_replace( '/[\s\-]/u', '', $value );
	}

	/**
	 * شماره موبایل کاربر (متای mobile، صورت‌حساب، یا نام کاربری ۰۹…).
	 *
	 * @param \WP_User $user کاربر.
	 */
	public static function mobile( \WP_User $user ): string {
		foreach ( [ (string) get_user_meta( $user->ID, 'mobile', true ), (string) get_user_meta( $user->ID, 'billing_phone', true ), $user->user_login ] as $candidate ) {
			$candidate = self::digits( $candidate );
			if ( preg_match( '/^09\d{9}$/', $candidate ) ) {
				return $candidate;
			}
		}
		return '';
	}

	/**
	 * ایمیل ساختگی ثبت‌نام با موبایل (۰۹…@دامنه) واقعی نیست.
	 *
	 * @param string $email ایمیل.
	 */
	public static function is_placeholder_email( string $email ): bool {
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		return '' === $email || (bool) preg_match( '/^09\d{9}@' . preg_quote( $host, '/' ) . '$/i', $email );
	}

	/**
	 * نام واقعی؟ (نه شماره موبایل و نه نام پیش‌فرض user_…)
	 *
	 * @param string $name نام.
	 */
	public static function is_real_name( string $name ): bool {
		$name = trim( $name );
		return mb_strlen( $name ) >= 2 && ! preg_match( '/^\+?\d[\d\s\-]*$/u', self::digits( $name ) ) && ! str_starts_with( $name, 'user_' );
	}

	/**
	 * مقدار فیلدهای فرم پروفایل.
	 *
	 * @param \WP_User $user کاربر.
	 * @return array{name: string, email: string, address: string, postcode: string, mobile: string}
	 */
	public static function fields( \WP_User $user ): array {
		$first = (string) get_user_meta( $user->ID, 'billing_first_name', true );
		$last  = (string) get_user_meta( $user->ID, 'billing_last_name', true );
		$name  = trim( $first . ' ' . $last );
		if ( '' === $name ) {
			$name = (string) $user->display_name;
		}
		$email = (string) get_user_meta( $user->ID, 'billing_email', true );
		if ( '' === $email ) {
			$email = (string) $user->user_email;
		}
		return [
			'name'     => self::is_real_name( $name ) ? $name : '',
			'email'    => self::is_placeholder_email( $email ) ? '' : $email,
			'address'  => (string) get_user_meta( $user->ID, 'billing_address_1', true ),
			'postcode' => (string) get_user_meta( $user->ID, 'billing_postcode', true ),
			'mobile'   => self::mobile( $user ),
		];
	}

	/**
	 * پروفایل برای کار با پنل کامل است؟
	 *
	 * @param \WP_User $user کاربر.
	 */
	public static function is_complete( \WP_User $user ): bool {
		$f = self::fields( $user );
		return '' !== $f['name'] && is_email( $f['email'] ) && mb_strlen( trim( $f['address'] ) ) >= 10 && (bool) preg_match( '/^\d{10}$/', self::digits( $f['postcode'] ) );
	}

	/**
	 * کارکنان سایت قفل نمی‌شوند (مدیر و مدیر فروشگاه).
	 *
	 * @param \WP_User $user کاربر.
	 */
	public static function is_staff( \WP_User $user ): bool {
		return user_can( $user, 'manage_options' ) || user_can( $user, 'manage_woocommerce' ); // phpcs:ignore WordPress.WP.Capabilities.Unknown -- دسترسی ووکامرس.
	}

	/**
	 * پیش از چاپ صفحه: پروفایل ناقص ← بخش پروفایل.
	 *
	 * @param string $section بخش درخواستی.
	 */
	public static function enforce_lock( string $section ): void {
		$user = wp_get_current_user();
		if ( ! $user->exists() || in_array( $section, self::OPEN_SECTIONS, true ) || self::is_staff( $user ) || self::is_complete( $user ) ) {
			return;
		}
		wp_safe_redirect( Page::url( [ 'section' => 'profile', 'msg' => 'profile_locked' ] ) );
		exit;
	}

	/**
	 * اعتبارسنجی و ذخیره.
	 *
	 * @param \WP_User             $user کاربر.
	 * @param array<string, string> $in   name، email، address، postcode (wp_unslash شده).
	 * @return string پیام خطا، یا '' برای موفقیت.
	 */
	public static function save( \WP_User $user, array $in ): string {
		$name     = sanitize_text_field( $in['name'] ?? '' );
		$email    = sanitize_email( $in['email'] ?? '' );
		$address  = sanitize_textarea_field( $in['address'] ?? '' );
		$postcode = self::digits( sanitize_text_field( $in['postcode'] ?? '' ) );

		if ( ! self::is_real_name( $name ) ) {
			return 'لطفا نام و نام خانوادگی واقعی خود را وارد کنید.';
		}
		if ( ! is_email( $email ) || self::is_placeholder_email( $email ) ) {
			return 'ایمیل معتبر وارد کنید.';
		}
		$owner = email_exists( $email );
		if ( $owner && (int) $owner !== $user->ID ) {
			return 'این ایمیل قبلاً توسط کاربر دیگری ثبت شده است.';
		}
		if ( mb_strlen( trim( $address ) ) < 10 ) {
			return 'آدرس کامل وارد کنید.';
		}
		if ( ! preg_match( '/^\d{10}$/', $postcode ) ) {
			return 'کد پستی باید ۱۰ رقم باشد.';
		}

		$parts = preg_split( '/\s+/u', trim( $name ), 2 ) ?: [ $name ];
		$first = (string) $parts[0];
		$last  = (string) ( $parts[1] ?? '' );

		// ایمیل ساختگی ثبت‌نام (۰۹…@دامنه) صندوقی ندارد؛ اعلان «ایمیل شما عوض شد» به آن برگشت می‌خورد
		$quiet = self::is_placeholder_email( (string) $user->user_email );
		if ( $quiet ) {
			add_filter( 'send_email_change_email', '__return_false' );
		}
		$result = wp_update_user(
			[
				'ID'           => $user->ID,
				'display_name' => $name,
				'first_name'   => $first,
				'last_name'    => $last,
				'user_email'   => $email,
			]
		);
		if ( $quiet ) {
			remove_filter( 'send_email_change_email', '__return_false' );
		}
		if ( is_wp_error( $result ) ) {
			return $result->get_error_message();
		}

		// همان کلیدهای صورت‌حساب ووکامرس (WC_Customer هم همین متاها را می‌خواند)
		$meta = [
			'billing_first_name' => $first,
			'billing_last_name'  => $last,
			'billing_email'      => $email,
			'billing_address_1'  => $address,
			'billing_postcode'   => $postcode,
		];
		if ( '' === (string) get_user_meta( $user->ID, 'billing_country', true ) ) {
			$meta['billing_country'] = 'IR';
		}
		$mobile = self::mobile( $user );
		if ( '' !== $mobile && '' === (string) get_user_meta( $user->ID, 'billing_phone', true ) ) {
			$meta['billing_phone'] = $mobile;
		}
		foreach ( $meta as $key => $value ) {
			update_user_meta( $user->ID, $key, $value );
		}
		return '';
	}

	/** ارسال کد پیامکی برای تأیید تغییر رمز/ایمیل (به موبایل حساب). */
	public static function ajax_code(): void {
		if ( ! is_user_logged_in() || ! check_ajax_referer( 'up_update_profile_action', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => 'نشست شما منقضی شده است؛ صفحه را دوباره باز کنید.' ] );
		}
		$user   = wp_get_current_user();
		$mobile = self::mobile( $user );
		if ( '' === $mobile ) {
			wp_send_json_error( [ 'message' => 'شماره موبایلی برای این حساب ثبت نیست؛ رمز فعلی را وارد کنید.' ] );
		}
		$result = Otp::issue( 'user-' . $user->ID, $mobile, 'profile', Auth::ip() );
		if ( ! $result['ok'] ) {
			wp_send_json_error( [ 'message' => $result['error'], 'wait' => $result['wait'] ] );
		}
		wp_send_json_success( [ 'message' => 'کد به ' . Sms::mask( $mobile ) . ' پیامک شد.', 'wait' => $result['wait'] ] );
	}

	/** ذخیره پروفایل با AJAX. */
	public static function ajax_save(): void {
		if ( ! is_user_logged_in() ) {
			wp_send_json_error( [ 'message' => 'دسترسی غیرمجاز' ] );
		}
		if ( ! check_ajax_referer( 'up_update_profile_action', 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => 'نشست شما منقضی شده است؛ صفحه را دوباره باز کنید.' ] );
		}
		// نسخه تازه از پایگاه داده (نه شیء کاربر جاری که ممکن است ایمیل قدیمی را نگه دارد)
		$user = get_userdata( get_current_user_id() );
		if ( ! $user instanceof \WP_User ) {
			wp_send_json_error( [ 'message' => 'دسترسی غیرمجاز' ] );
		}

		// رمز پیش از هر ذخیره‌ای بررسی شود (پنل قدیمی اطلاعات را ذخیره و بعد خطای رمز می‌داد)
		$new_pass = (string) wp_unslash( $_POST['new_password'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- رمز پاک‌سازی نمی‌شود.
		$confirm  = (string) wp_unslash( $_POST['confirm_password'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( '' !== $new_pass && ( $new_pass !== $confirm || strlen( $new_pass ) < 8 ) ) {
			wp_send_json_error( [ 'message' => 'رمز تازه باید دست‌کم ۸ حرف باشد و با تکرارش یکی باشد.' ] );
		}

		// تغییر رمز یا ایمیل واقعی فقط با رمز فعلی یا کد پیامکی (گزارش، مورد ۹: با نشست ربوده‌شده
		// حساب برای همیشه تصاحب می‌شد). اولین ایمیل واقعی به‌جای ایمیل ساختگی ثبت‌نام آزاد است.
		$email     = sanitize_email( wp_unslash( (string) ( $_POST['user_email'] ?? '' ) ) );
		$sensitive = '' !== $new_pass || ( strtolower( $email ) !== strtolower( (string) $user->user_email ) && ! self::is_placeholder_email( (string) $user->user_email ) );
		if ( $sensitive ) {
			$current = (string) wp_unslash( $_POST['current_password'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$code    = sanitize_text_field( wp_unslash( (string) ( $_POST['verify_code'] ?? '' ) ) );
			$ok      = '' !== $current && wp_check_password( $current, $user->user_pass, $user->ID );
			if ( ! $ok && '' !== $code ) {
				$check = Otp::check( 'user-' . $user->ID, $code, 'profile' );
				if ( ! $check['ok'] ) {
					wp_send_json_error( [ 'message' => $check['error'] ] );
				}
				$ok = true;
			}
			if ( ! $ok ) {
				wp_send_json_error( [ 'message' => 'برای تغییر رمز یا ایمیل، رمز فعلی را وارد کنید یا کد پیامکی بگیرید.', 'need_verify' => true ] );
			}
		}

		$error = self::save(
			$user,
			[
				'name'     => (string) wp_unslash( $_POST['display_name'] ?? '' ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- در save() پاک‌سازی می‌شود.
				'email'    => (string) wp_unslash( $_POST['user_email'] ?? '' ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				'address'  => (string) wp_unslash( $_POST['address'] ?? '' ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				'postcode' => (string) wp_unslash( $_POST['postcode'] ?? '' ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			]
		);
		if ( '' !== $error ) {
			wp_send_json_error( [ 'message' => $error ] );
		}
		if ( '' !== $new_pass ) {
			wp_set_password( $new_pass, $user->ID );
			// wp_set_password همه نشست‌ها را می‌بندد؛ پنل قدیمی کاربر را بی‌خبر بیرون می‌انداخت
			wp_set_current_user( $user->ID );
			wp_set_auth_cookie( $user->ID, true, is_ssl() );
		}
		wp_send_json_success( [ 'message' => 'اطلاعات با موفقیت ذخیره شد. پنل اکنون برای شما فعال است.' ] );
	}
}
