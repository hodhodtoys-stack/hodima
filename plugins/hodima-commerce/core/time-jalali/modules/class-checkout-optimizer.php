<?php
declare( strict_types=1 );
namespace Hodima\Core\Time_Jalali\Modules;

defined( 'ABSPATH' ) || exit;

final class Checkout_Optimizer {
    public function __construct() {
        add_filter( 'woocommerce_default_address_fields', [ $this, 'reorder_iranian_address_fields' ], 99 );
        add_filter( 'woocommerce_checkout_fields', [ $this, 'reorder_billing_email_phone' ], 99 );
        add_action( 'woocommerce_checkout_process', [ $this, 'validate_iranian_checkout_fields' ] );
    }

    public function reorder_iranian_address_fields( array $fields ): array {
        $priorities = [ 'state' => 40, 'city' => 50, 'address_1' => 60, 'address_2' => 70 ];
        
        foreach ( $priorities as $key => $priority ) {
            if ( isset( $fields[ $key ] ) ) {
                $fields[ $key ]['priority'] = $priority;
            }
        }

        if ( isset( $fields['postcode'] ) ) {
            $fields['postcode']['priority'] = 80;
            $fields['postcode']['class']    = ['form-row-wide'];
            $fields['postcode']['clear']    = true;
        }

        return $fields;
    }

    public function reorder_billing_email_phone( array $fields ): array {
        if ( isset( $fields['billing']['billing_phone'] ) ) {
            $fields['billing']['billing_phone']['priority']    = 90;
            $fields['billing']['billing_phone']['class']       = ['form-row-first'];
            $fields['billing']['billing_phone']['placeholder'] = '09123456789';
        }
        if ( isset( $fields['billing']['billing_email'] ) ) {
            $fields['billing']['billing_email']['priority']    = 100;
            $fields['billing']['billing_email']['class']       = ['form-row-last'];
        }
        return $fields;
    }

    public function validate_iranian_checkout_fields(): void {
        $fa_to_en = [
            '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
            '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9'
        ];

        if ( ! empty( $_POST['billing_phone'] ) ) {
            $phone = sanitize_text_field( strtr( wp_unslash( $_POST['billing_phone'] ), $fa_to_en ) );
            if ( ! preg_match( '/^09\d{9}$/', $phone ) ) {
                wc_add_notice( 'شماره موبایل نامعتبر است. لطفاً یک شماره ۱۱ رقمی همراه با صفر وارد کنید.', 'error' );
            }
        }
        
        if ( ! empty( $_POST['billing_postcode'] ) ) {
            $postcode = sanitize_text_field( strtr( wp_unslash( $_POST['billing_postcode'] ), $fa_to_en ) );
            if ( ! preg_match( '/^\d{10}$/', $postcode ) ) {
                wc_add_notice( 'کد پستی باید دقیقاً ۱۰ رقم لاتین باشد.', 'error' );
            }
        }
    }
}