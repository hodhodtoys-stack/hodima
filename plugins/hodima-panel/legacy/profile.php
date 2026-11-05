<?php
/**
 * ذخیره پروفایل با AJAX (منتقل‌شده از پنل قدیمی).
 *
 * باقی‌مانده برای مرحله ۳ و ۴: تغییر رمز و ایمیل با رمز فعلی، بدون خارج شدن از
 * حساب بعد از تغییر رمز، ذخیره آدرس در فیلدهای صورت‌حساب ووکامرس.
 *
 * @package Hodima\Panel
 */

declare(strict_types=1);

namespace Hodima\Panel\Legacy;

defined( 'ABSPATH' ) || exit;

add_action( 'wp_ajax_hodima_panel_profile', static function (): void {
	if ( ! is_user_logged_in() ) {
		wp_send_json_error( [ 'message' => 'دسترسی غیرمجاز' ] );
	}
	if ( ! check_ajax_referer( 'up_update_profile_action', 'nonce', false ) ) {
		wp_send_json_error( [ 'message' => 'خطای امنیتی' ] );
	}

	$user_id  = get_current_user_id();
	$name     = sanitize_text_field( wp_unslash( $_POST['display_name'] ?? '' ) );
	$address  = sanitize_textarea_field( wp_unslash( $_POST['address'] ?? '' ) );
	$postcode = sanitize_text_field( wp_unslash( $_POST['postcode'] ?? '' ) );
	$email    = sanitize_email( wp_unslash( $_POST['user_email'] ?? '' ) );
	$new_pass = (string) wp_unslash( $_POST['new_password'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- رمز عبور پاک‌سازی نمی‌شود.
	$confirm  = (string) wp_unslash( $_POST['confirm_password'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

	if ( '' === $name || '' === $address || '' === $postcode || '' === $email ) {
		wp_send_json_error( [ 'message' => 'لطفا همه فیلدهای ستاره‌دار را پر کنید' ] );
	}
	if ( preg_match( '/^09[0-9]{9}$/', $name ) || str_contains( $name, 'user_' ) ) {
		wp_send_json_error( [ 'message' => 'لطفا نام و نام خانوادگی واقعی خود را وارد کنید.' ] );
	}
	if ( ! preg_match( '/^[0-9]{10}$/', $postcode ) ) {
		wp_send_json_error( [ 'message' => 'کد پستی نامعتبر است' ] );
	}
	if ( ! is_email( $email ) ) {
		wp_send_json_error( [ 'message' => 'فرمت ایمیل نامعتبر است' ] );
	}
	$owner = email_exists( $email );
	if ( $owner && (int) $owner !== $user_id ) {
		wp_send_json_error( [ 'message' => 'این ایمیل قبلاً توسط کاربر دیگری ثبت شده است' ] );
	}
	// رمز پیش از هر ذخیره‌ای بررسی شود (قبلا اطلاعات ذخیره و بعد خطای رمز داده می‌شد)
	if ( '' !== $new_pass && $new_pass !== $confirm ) {
		wp_send_json_error( [ 'message' => 'تکرار رمز عبور درست نیست' ] );
	}

	$result = wp_update_user( [ 'ID' => $user_id, 'display_name' => $name, 'user_email' => $email ] );
	if ( is_wp_error( $result ) ) {
		wp_send_json_error( [ 'message' => $result->get_error_message() ] );
	}

	update_user_meta( $user_id, 'user_address', $address );
	update_user_meta( $user_id, 'user_postcode', $postcode );
	update_user_meta( $user_id, 'up_profile_completed', '1' );

	if ( '' !== $new_pass ) {
		wp_set_password( $new_pass, $user_id );
	}

	wp_send_json_success( [ 'message' => 'اطلاعات با موفقیت ذخیره شد. پنل اکنون برای شما فعال است.' ] );
} );
