<?php
declare( strict_types=1 );
namespace Hodima\Core\Time_Jalali\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * استان‌ها و شهرهای ایران در فرم آدرس ووکامرس (تسویه‌حساب و «حساب کاربری ← ویرایش آدرس»).
 *
 * کد استان‌ها (ALZ، ARD، QZV، QOM و …) با کد خود ووکامرس برای ۴ استان فرق دارد ولی
 * همین کدها در سفارش‌ها و حساب مشتری‌ها ذخیره شده‌اند؛ عوض نشوند (تصمیم کاربر، بخش ۱۰۰).
 */
final class Iran_Cities {

    /** مقدار گزینه «شهر من در فهرست نیست»؛ جاوااسکریپت آن را به کادر متنی تبدیل می‌کند. */
    public const OTHER = '__other__';

    private const PROVINCES = [
        'ALZ' => 'البرز', 'ARD' => 'اردبیل', 'BHR' => 'بوشهر', 'CHB' => 'چهارمحال و بختیاری',
        'EAZ' => 'آذربایجان شرقی', 'FRS' => 'فارس', 'GIL' => 'گیلان', 'GLS' => 'گلستان',
        'HDN' => 'همدان', 'HRZ' => 'هرمزگان', 'ILM' => 'ایلام', 'ESF' => 'اصفهان',
        'KRN' => 'کرمان', 'KRH' => 'کرمانشاه', 'KHZ' => 'خوزستان', 'KBD' => 'کهگیلویه و بویراحمد',
        'KRD' => 'کردستان', 'LRS' => 'لرستان', 'MKZ' => 'مرکزی', 'MZN' => 'مازندران',
        'NKH' => 'خراسان شمالی', 'QZV' => 'قزوین', 'QOM' => 'قم', 'RKH' => 'خراسان رضوی',
        'SMN' => 'سمنان', 'SBN' => 'سیستان و بلوچستان', 'SKH' => 'خراسان جنوبی', 'THR' => 'تهران',
        'WAZ' => 'آذربایجان غربی', 'YZD' => 'یزد', 'ZJN' => 'زنجان',
    ];

    /** @var array{cities: array<string, list<string>>, aliases: array<string, array<string, string>>}|null */
    private static ?array $data = null;

    public function __construct() {
        add_filter( 'woocommerce_states', [ $this, 'add_iran_provinces' ] );
        add_filter( 'woocommerce_default_address_fields', [ $this, 'modify_city_field_type' ] );
        add_filter( 'woocommerce_form_field_args', [ $this, 'city_field_args' ], 10, 3 );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_cities_script' ], 20 );
        add_action( 'woocommerce_checkout_process', [ $this, 'validate_checkout_city' ] );
        add_action( 'woocommerce_after_save_address_validation', [ $this, 'validate_account_city' ], 10, 2 );
    }

    /**
     * فهرست کامل شهرها (data/iran-cities.php، ساخته tools/iran-cities/build.py).
     *
     * @return array{cities: array<string, list<string>>, aliases: array<string, array<string, string>>}
     */
    public static function data(): array {
        return self::$data ??= require dirname( __DIR__ ) . '/data/iran-cities.php';
    }

    /**
     * شهرهای یک استان (مرکز استان اول).
     *
     * @param string $state کد استان.
     * @return list<string>
     */
    public static function cities( string $state ): array {
        return self::data()['cities'][ $state ] ?? [];
    }

    /** کلید مقایسه نام شهر: بی‌فاصله، ی/ک فارسی، آ = ا (مثل جستجوی جاوااسکریپت). */
    public static function name_key( string $name ): string {
        return str_replace(
            [ ' ', "\u{200c}", 'ي', 'ى', 'ك', 'آ', 'أ', 'إ', 'ئ', 'ء' ],
            [ '', '', 'ی', 'ی', 'ک', 'ا', 'ا', 'ا', 'ی', '' ],
            trim( $name )
        );
    }

    /**
     * استان‌های ایران با کدهای ذخیره‌شده سایت.
     *
     * @param array<string, mixed> $states استان‌های همه کشورها.
     * @return array<string, mixed>
     */
    public function add_iran_provinces( array $states ): array {
        $states['IR'] = self::PROVINCES;
        return $states;
    }

    /**
     * فیلد شهر کشویی (گزینه‌ها را city_field_args می‌سازد).
     *
     * @param array<string, mixed> $fields فیلدهای پیش‌فرض آدرس.
     * @return array<string, mixed>
     */
    public function modify_city_field_type( array $fields ): array {
        if ( isset( $fields['city'] ) ) {
            $fields['city']['type']    = 'select';
            $fields['city']['options'] = [ '' => 'ابتدا استان را انتخاب کنید' ];
        }
        return $fields;
    }

    /**
     * گزینه‌های شهر را همان‌جا در سرور می‌سازد.
     * باگ قبلی: فهرست سرور فقط گزینه خالی داشت و جاوااسکریپت هنگام باز شدن صفحه آن را از نو
     * می‌ساخت؛ شهر ذخیره‌شده مشتری هر بار پاک می‌شد و بدون جاوااسکریپت شهری برای انتخاب نبود.
     * کشور غیر از ایران ← کادر متنی معمولی ووکامرس (قبلا فهرست خالی و سفارش ناممکن بود).
     *
     * @param array<string, mixed> $args  تنظیمات فیلد.
     * @param string               $key   نام فیلد.
     * @param mixed                $value مقدار فعلی.
     * @return array<string, mixed>
     */
    public function city_field_args( array $args, string $key, mixed $value ): array {
        if ( ! preg_match( '/^(billing|shipping)_city$/', $key, $m ) ) {
            return $args;
        }
        $type    = $m[1];
        $country = $this->address_value( $type, 'country' );
        if ( '' === $country && function_exists( 'WC' ) && WC()->countries ) {
            $country = WC()->countries->get_base_country();
        }
        if ( 'IR' !== $country ) {
            $args['type'] = 'text';
            unset( $args['options'] );
            return $args;
        }

        $state   = $this->address_value( $type, 'state' );
        $current = is_string( $value ) ? trim( $value ) : '';
        $cities  = self::cities( $state );
        $options = [ '' => $cities ? 'انتخاب شهر' : 'ابتدا استان را انتخاب کنید' ];
        $found   = false;
        foreach ( $cities as $city ) {
            // همان شهر با املای دیگر (مثلا «نجف‌آباد» فهرست قدیمی): مقدار ذخیره‌شده انتخاب‌شده بماند
            if ( '' !== $current && ! $found && self::name_key( $city ) === self::name_key( $current ) ) {
                $found              = true;
                $options[ $current ] = $city;
                continue;
            }
            $options[ $city ] = $city;
        }
        // شهر ذخیره‌شده‌ای که در فهرست نیست (از فهرست قدیمی یا نوشته مشتری) گم نشود
        if ( '' !== $current && ! $found && self::OTHER !== $current ) {
            $options[ $current ] = $current;
        }
        if ( $cities ) {
            $options[ self::OTHER ] = 'شهر من در فهرست نیست';
        }

        $args['type']        = 'select';
        $args['options']     = $options;
        $args['input_class'] = array_merge( (array) ( $args['input_class'] ?? [] ), [ 'hodima-city-select' ] );
        return $args;
    }

    /** مقدار فعلی یک فیلد آدرس: فرم ارسال‌شده، وگرنه حساب مشتری. */
    private function address_value( string $type, string $field ): string {
        $key = "{$type}_{$field}";
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- فقط برای انتخاب گزینه‌های نمایشی؛ ذخیره با nonce خود ووکامرس
        if ( isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ) {
            return wc_clean( wp_unslash( $_POST[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        }
        if ( function_exists( 'WC' ) && WC()->customer ) {
            $getter = "get_{$key}";
            if ( is_callable( [ WC()->customer, $getter ] ) ) {
                return (string) WC()->customer->$getter();
            }
        }
        return '';
    }

    public function enqueue_cities_script(): void {
        if ( ! function_exists( 'is_checkout' ) || ! ( is_checkout() || is_account_page() ) ) {
            return;
        }
        $path = dirname( __DIR__ ) . '/iran-cities.js';
        wp_enqueue_script(
            'hodima-iran-cities',
            HODIMA_COMMERCE_URL . '/core/time-jalali/iran-cities.js',
            // selectWoo ووکامرس هم defer است؛ بدون وابستگی، این فایل زودتر اجرا می‌شد و جستجو نداشت
            array_values( array_filter( [ 'jquery', 'selectWoo' ], static fn( string $handle ): bool => wp_script_is( $handle, 'registered' ) ) ),
            is_file( $path ) ? (string) filemtime( $path ) : HODIMA_COMMERCE_VERSION,
            [ 'in_footer' => true, 'strategy' => 'defer' ]
        );
        // ظاهر دکمه «انتخاب از فهرست شهرها» (همان فایل ماژول بهینه‌ساز فرم؛ دوبار لود نمی‌شود)
        $css = dirname( __DIR__ ) . '/checkout-fields.css';
        wp_enqueue_style( 'hodima-checkout-fields', HODIMA_COMMERCE_URL . '/core/time-jalali/checkout-fields.css', [], is_file( $css ) ? (string) filemtime( $css ) : HODIMA_COMMERCE_VERSION );
        $data          = self::data();
        $data['other'] = self::OTHER;
        wp_add_inline_script( 'hodima-iran-cities', 'window.hodimaCitiesData = ' . wp_json_encode( $data, JSON_UNESCAPED_UNICODE ) . ';', 'before' );
    }

    /** بدون جاوااسکریپت، گزینه «در فهرست نیست» جای نام شهر ذخیره نشود. */
    public function validate_checkout_city(): void {
        foreach ( [ 'billing', 'shipping' ] as $type ) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing -- ووکامرس nonce تسویه‌حساب را پیش از این هوک بررسی کرده است
            if ( isset( $_POST[ "{$type}_city" ] ) && self::OTHER === $_POST[ "{$type}_city" ] ) {
                wc_add_notice( 'نام شهر را بنویسید.', 'error' );
                return;
            }
        }
    }

    public function validate_account_city( int $user_id, string $address_type ): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- ووکامرس nonce فرم آدرس را پیش از این هوک بررسی کرده است
        if ( isset( $_POST[ "{$address_type}_city" ] ) && self::OTHER === $_POST[ "{$address_type}_city" ] ) {
            wc_add_notice( 'نام شهر را بنویسید.', 'error' );
        }
    }
}
