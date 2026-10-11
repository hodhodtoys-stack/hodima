<?php
declare( strict_types=1 );
namespace Hodima\Core\Time_Jalali\Modules;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-iran-input.php';

/**
 * فرم آدرس ایرانی: ترتیب فیلدها، یکدست‌سازی و اعتبارسنجی موبایل و کد پستی، و کد ملی اختیاری.
 * در تسویه‌حساب و «حساب کاربری ← ویرایش آدرس» یکسان (قبلا فقط تسویه‌حساب و فقط کد پستی صورتحساب).
 */
final class Checkout_Optimizer {

    public function __construct() {
        add_filter( 'woocommerce_default_address_fields', [ $this, 'reorder_iranian_address_fields' ], 99 );
        add_filter( 'woocommerce_checkout_fields', [ $this, 'reorder_billing_email_phone' ], 99 );
        if ( get_option( 'hodima_wc_national_id_status', 'yes' ) === 'yes' ) {
            add_filter( 'woocommerce_billing_fields', [ $this, 'add_national_id_field' ], 99 );
            add_filter( 'woocommerce_admin_billing_fields', [ $this, 'admin_national_id_field' ] );
            add_filter( 'woocommerce_customer_meta_fields', [ $this, 'profile_national_id_field' ] );
            add_filter( 'woocommerce_email_customer_details_fields', [ $this, 'email_national_id' ], 10, 3 );
        }

        // مقدار یکدست‌شده ذخیره شود (قبلا شماره با رقم فارسی بررسی می‌شد ولی فارسی ذخیره می‌شد)
        $normalizers = [
            'billing_phone'       => [ Iran_Input::class, 'mobile' ],
            'billing_postcode'    => [ Iran_Input::class, 'postcode' ],
            'shipping_postcode'   => [ Iran_Input::class, 'postcode' ],
            'shipping_phone'      => [ Iran_Input::class, 'latin_digits' ],
            'billing_national_id' => [ Iran_Input::class, 'national_id' ],
        ];
        foreach ( $normalizers as $key => $callback ) {
            $filter = static fn( mixed $value ): mixed => is_string( $value ) ? $callback( $value ) : $value;
            add_filter( "woocommerce_process_checkout_field_{$key}", $filter );
            add_filter( "woocommerce_process_myaccount_field_{$key}", $filter );
        }

        add_action( 'woocommerce_after_checkout_validation', [ $this, 'validate_checkout' ], 10, 2 );
        add_action( 'woocommerce_after_save_address_validation', [ $this, 'validate_account_address' ], 10, 2 );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ], 20 );
    }

    /**
     * ترتیب فیلدهای آدرس ایرانی (استان، شهر، آدرس، کد پستی).
     *
     * @param array<string, mixed> $fields فیلدها.
     * @return array<string, mixed>
     */
    public function reorder_iranian_address_fields( array $fields ): array {
        $priorities = [ 'state' => 40, 'city' => 50, 'address_1' => 60, 'address_2' => 70 ];

        foreach ( $priorities as $key => $priority ) {
            if ( isset( $fields[ $key ] ) ) {
                $fields[ $key ]['priority'] = $priority;
            }
        }

        if ( isset( $fields['postcode'] ) ) {
            $fields['postcode']['priority'] = 80;
            // باگ قبلی: class کامل جایگزین می‌شد و «address-field» ووکامرس می‌رفت؛ آن‌وقت با نوشتن
            // کد پستی هزینه ارسال دوباره حساب نمی‌شد و تنظیمات کشوری روی این فیلد اثر نداشت.
            $classes = array_diff( (array) ( $fields['postcode']['class'] ?? [] ), [ 'form-row-first', 'form-row-last' ] );
            $fields['postcode']['class']    = array_values( array_unique( [ 'form-row-wide', ...$classes, 'address-field' ] ) );
            $fields['postcode']['clear']    = true;
        }

        return $fields;
    }

    /**
     * موبایل و ایمیل کنار هم بعد از آدرس.
     *
     * @param array<string, mixed> $fields فیلدها.
     * @return array<string, mixed>
     */
    public function reorder_billing_email_phone( array $fields ): array {
        if ( isset( $fields['billing']['billing_phone'] ) ) {
            $fields['billing']['billing_phone']['priority']    = 90;
            $fields['billing']['billing_phone']['class']       = [ 'form-row-first' ];
            $fields['billing']['billing_phone']['placeholder'] = '09123456789';
        }
        if ( isset( $fields['billing']['billing_email'] ) ) {
            $fields['billing']['billing_email']['priority'] = 100;
            $fields['billing']['billing_email']['class']    = [ 'form-row-last' ];
        }
        return $fields;
    }

    /**
     * کد ملی اختیاری برای فاکتور رسمی؛ ووکامرس آن را در متای سفارش `_billing_national_id` و حساب مشتری ذخیره می‌کند.
     *
     * @param array<string, mixed> $fields فیلدهای صورتحساب.
     * @return array<string, mixed>
     */
    public function add_national_id_field( array $fields ): array {
        $fields['billing_national_id'] = [
            'label'             => 'کد ملی',
            'required'          => false,
            'type'              => 'text',
            'class'             => [ 'form-row-wide' ],
            'priority'          => 110,
            'autocomplete'      => 'off',
            'description'       => 'اختیاری؛ برای صدور فاکتور رسمی.',
            'custom_attributes' => [ 'inputmode' => 'numeric', 'maxlength' => '10', 'dir' => 'ltr' ],
        ];
        return $fields;
    }

    /**
     * نمایش و ویرایش در صفحه سفارش پیشخوان (ووکامرس همان متای `_billing_national_id` را می‌خواند).
     *
     * @param array<string, mixed> $fields فیلدهای صورتحساب پیشخوان.
     * @return array<string, mixed>
     */
    public function admin_national_id_field( array $fields ): array {
        $fields['national_id'] = [ 'label' => 'کد ملی', 'show' => true ];
        return $fields;
    }

    /**
     * فیلد کد ملی در صفحه ویرایش کاربر پیشخوان.
     *
     * @param array<string, mixed> $fields بخش‌های پروفایل مشتری.
     * @return array<string, mixed>
     */
    public function profile_national_id_field( array $fields ): array {
        if ( isset( $fields['billing']['fields'] ) ) {
            $fields['billing']['fields']['billing_national_id'] = [ 'label' => 'کد ملی', 'description' => '' ];
        }
        return $fields;
    }

    /**
     * فقط در ایمیل مدیر فروشگاه (اطلاعات شخصی در ایمیل مشتری تکرار نشود).
     *
     * @param array<string, mixed> $fields        فیلدهای مشتری در ایمیل.
     * @param bool                 $sent_to_admin ایمیل مدیر.
     * @param mixed                $order         سفارش.
     * @return array<string, mixed>
     */
    public function email_national_id( array $fields, bool $sent_to_admin, mixed $order ): array {
        if ( $sent_to_admin && $order instanceof \WC_Order ) {
            $id = (string) $order->get_meta( '_billing_national_id' );
            if ( '' !== $id ) {
                $fields['billing_national_id'] = [ 'label' => 'کد ملی', 'value' => $id ];
            }
        }
        return $fields;
    }

    /**
     * $data مقدار یکدست‌شده است (فیلترهای process بالا). فقط آدرس ایران؛ آدرس گیرنده فقط اگر جدا باشد.
     * باگ قبلی: کد پستی گیرنده فقط در مرورگر بررسی می‌شد و در سرور نه.
     *
     * @param array<string, mixed> $data   داده ارسالی تسویه‌حساب.
     * @param \WP_Error            $errors خطاهای ووکامرس.
     */
    public function validate_checkout( array $data, \WP_Error $errors ): void {
        $types = [ 'billing' ];
        if ( ! empty( $data['ship_to_different_address'] ) ) {
            $types[] = 'shipping';
        }
        foreach ( $types as $type ) {
            foreach ( $this->address_errors( $type, $data ) as $key => $message ) {
                $errors->add( 'validation', $message, [ 'id' => $key ] );
            }
        }
    }

    /** «حساب کاربری ← ویرایش آدرس» (قبلا اصلا بررسی نمی‌شد). */
    public function validate_account_address( int $user_id, string $address_type ): void {
        $data = [];
        foreach ( [ 'country', 'phone', 'postcode', 'national_id' ] as $field ) {
            $key = "{$address_type}_{$field}";
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- ووکامرس nonce فرم آدرس را پیش از این هوک بررسی کرده است
            $raw          = isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? wc_clean( wp_unslash( $_POST[ $key ] ) ) : '';
            // همان فیلتر خود ووکامرس (یکدست‌سازی بالا) تا بررسی روی مقداری باشد که ذخیره می‌شود
            $data[ $key ] = (string) apply_filters( "woocommerce_process_myaccount_field_{$key}", $raw ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- هوک ووکامرس
        }
        foreach ( $this->address_errors( $address_type, $data ) as $key => $message ) {
            wc_add_notice( $message, 'error', [ 'id' => $key ] );
        }
    }

    /**
     * خطاهای یک آدرس.
     *
     * @param string               $type billing یا shipping.
     * @param array<string, mixed> $data مقدارهای یکدست‌شده.
     * @return array<string, string> کلید فیلد ← پیام خطا
     */
    private function address_errors( string $type, array $data ): array {
        $country = (string) ( $data[ "{$type}_country" ] ?? '' );
        if ( '' !== $country && 'IR' !== $country ) {
            return [];
        }
        $errors   = [];
        $phone    = (string) ( $data[ "{$type}_phone" ] ?? '' );
        $postcode = (string) ( $data[ "{$type}_postcode" ] ?? '' );
        $nid      = (string) ( $data[ "{$type}_national_id" ] ?? '' );
        if ( 'billing' === $type && '' !== $phone && ! Iran_Input::is_mobile( $phone ) ) {
            $errors[ "{$type}_phone" ] = 'شماره موبایل نامعتبر است. لطفاً یک شماره ۱۱ رقمی که با ۰۹ شروع می‌شود وارد کنید.';
        }
        if ( '' !== $postcode && ! Iran_Input::is_postcode( $postcode ) ) {
            $errors[ "{$type}_postcode" ] = 'shipping' === $type ? 'کد پستی گیرنده باید ۱۰ رقم باشد.' : 'کد پستی باید ۱۰ رقم باشد.';
        }
        if ( '' !== $nid && ! Iran_Input::is_national_id( $nid ) ) {
            $errors[ "{$type}_national_id" ] = 'کد ملی معتبر نیست. ۱۰ رقم کد ملی را بدون خط تیره وارد کنید.';
        }
        return $errors;
    }

    public function enqueue_assets(): void {
        if ( ! function_exists( 'is_checkout' ) || ! ( is_checkout() || is_account_page() ) ) {
            return;
        }
        $dir = dirname( __DIR__ );
        $ver = static fn( string $file ): string => is_file( "{$dir}/{$file}" ) ? (string) filemtime( "{$dir}/{$file}" ) : HODIMA_COMMERCE_VERSION;
        wp_enqueue_style( 'hodima-checkout-fields', HODIMA_COMMERCE_URL . '/core/time-jalali/checkout-fields.css', [], $ver( 'checkout-fields.css' ) );
        wp_enqueue_script( 'hodima-checkout-fields', HODIMA_COMMERCE_URL . '/core/time-jalali/checkout-fields.js', [], $ver( 'checkout-fields.js' ), [ 'in_footer' => true, 'strategy' => 'defer' ] );
    }
}
