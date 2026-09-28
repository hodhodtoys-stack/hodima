<?php
declare( strict_types=1 );
namespace Hodima\Core\Time_Jalali;

defined( 'ABSPATH' ) || exit;

final class Hodima_Localizer_WC {

    private static ?Hodima_Localizer_WC $instance = null;

    public static function get_instance(): self {
        return self::$instance ??= new self();
    }

    private function __construct() {
        // سازگاری HPOS در فایل اصلی افزونه (hodima-commerce.php) اعلام می‌شود؛
        // ووکامرس آن را فقط برای فایل اصلی افزونه می‌پذیرد، نه این فایل.
        $this->load_active_modules();

        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

        if ( is_admin() ) {
            add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
            add_action( 'admin_init', [ $this, 'register_plugin_settings' ] );
        }
    }

    private function __clone() {}

    private function load_active_modules(): void {
        $modules_dir = trailingslashit( dirname( __FILE__ ) ) . 'modules/';

        if ( get_option( 'hodima_wc_jalali_status', 'yes' ) === 'yes' ) {
            require_once $modules_dir . 'class-jalali-date.php';
            new Modules\Jalali_Date();
        }
        if ( get_option( 'hodima_wc_iran_cities_status', 'yes' ) === 'yes' ) {
            require_once $modules_dir . 'class-iran-cities.php';
            new Modules\Iran_Cities();
        }
        if ( get_option( 'hodima_wc_checkout_optimizer_status', 'yes' ) === 'yes' ) {
            require_once $modules_dir . 'class-checkout-optimizer.php';
            new Modules\Checkout_Optimizer();
        }
    }

    public function register_admin_menu(): void {
        add_options_page(
            esc_html__( 'بومی سازی سایت', 'hodima-core' ), 
            esc_html__( 'بومی سازی سایت', 'hodima-core' ), 
            'manage_options',
            'hodima-woocommerce',
            [ $this, 'render_admin_page' ]
        );
    }

    public function register_plugin_settings(): void {
        register_setting( 'hodima_localizer_group', 'hodima_wc_jalali_status' );
        register_setting( 'hodima_localizer_group', 'hodima_wc_iran_cities_status' );
        register_setting( 'hodima_localizer_group', 'hodima_wc_checkout_optimizer_status' );
    }

    public function render_admin_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) return;

        /*
         * هدر، سوییچ‌ها و نوار ذخیره از سیستم طراحی مشترک «ابزارهای هدیما».
         * پیام «ذخیره شد» را خود وردپرس برای صفحه‌های «تنظیمات» چاپ می‌کند
         * (options-head.php) و زیر هدر می‌نشیند؛ پیام تکراری با ایموجی حذف شد.
         */
        $fields = [
            'hodima_wc_jalali_status'             => [ 'تاریخ شمسی', 'فعال‌سازی تاریخ جلالی در سراسر سایت و ووکامرس.', 'dashicons-calendar-alt' ],
            'hodima_wc_iran_cities_status'        => [ 'استان‌ها و شهرهای ایران', 'فهرست محلی استان‌ها و شهرها به جای فیلد متنی در تسویه‌حساب.', 'dashicons-location' ],
            'hodima_wc_checkout_optimizer_status' => [ 'بهینه‌ساز فرم صورتحساب', 'مرتب‌سازی فیلدهای آدرس و اعتبارسنجی موبایل ۱۱ رقمی و کد پستی.', 'dashicons-feedback' ],
        ];
        ?>
        <div class="wrap hd-wrap hodima-loc-admin">
            <?php
            hodima_admin_header( [
                'title'       => 'بومی‌سازی سایت',
                'description' => 'تاریخ شمسی، استان‌ها و شهرهای ایران و بهینه‌سازی فرم تسویه‌حساب ووکامرس.',
                'icon'        => 'dashicons-translation',
            ] );
            ?>

            <form method="post" action="options.php" class="hd-body">
                <?php settings_fields( 'hodima_localizer_group' ); ?>

                <div class="hd-grid">
                    <?php foreach ( $fields as $option => [ $title, $desc, $icon ] ) : ?>
                        <section class="hd-card">
                            <header class="hd-card__head">
                                <?php echo hodima_admin_icon( $icon ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                                <h2 class="hd-card__title" id="<?php echo esc_attr( $option ); ?>-title"><?php echo esc_html( $title ); ?></h2>
                                <input type="checkbox" class="hd-switch" role="switch" name="<?php echo esc_attr( $option ); ?>" value="yes" aria-labelledby="<?php echo esc_attr( $option ); ?>-title" <?php checked( get_option( $option, 'yes' ), 'yes' ); ?> />
                            </header>
                            <p class="hd-text"><?php echo esc_html( $desc ); ?></p>
                        </section>
                    <?php endforeach; ?>
                </div>

                <div class="hd-actions">
                    <button type="submit" name="submit" class="button button-primary">ذخیره تنظیمات</button>
                </div>
            </form>
        </div>
        <?php
    }

    private function get_asset_version( string $path ): string {
        return file_exists( $path ) ? (string) filemtime( $path ) : '1.0.0';
    }

    public function enqueue_assets(): void {
        $css_path = HODIMA_COMMERCE_DIR . '/core/time-jalali/time-jalali.css';
        $js_path  = HODIMA_COMMERCE_DIR . '/core/time-jalali/time-jalali.js';

        wp_enqueue_style( 'hodima-localizer-wc', HODIMA_COMMERCE_URL . '/core/time-jalali/time-jalali.css', [], $this->get_asset_version($css_path) );
        wp_enqueue_script( 'hodima-localizer-wc', HODIMA_COMMERCE_URL . '/core/time-jalali/time-jalali.js', [], $this->get_asset_version($js_path), [
            'in_footer' => true,
            'strategy'  => 'defer'
        ] );
    }

    public function enqueue_admin_assets( string $hook ): void {
        if ( str_contains( $hook, 'hodima-woocommerce' ) || str_contains( $hook, 'woocommerce' ) ) {
            $css_path = HODIMA_COMMERCE_DIR . '/core/time-jalali/time-jalali.css';
            wp_enqueue_style( 'hodima-admin-localizer', HODIMA_COMMERCE_URL . '/core/time-jalali/time-jalali.css', [], $this->get_asset_version($css_path) );
        }

        if ( str_contains( $hook, 'woocommerce' ) && get_option( 'hodima_wc_jalali_status', 'yes' ) === 'yes' ) {
            $js_path = HODIMA_COMMERCE_DIR . '/core/time-jalali/time-jalali.js';
            wp_enqueue_script( 'hodima-admin-localizer-js', HODIMA_COMMERCE_URL . '/core/time-jalali/time-jalali.js', [], $this->get_asset_version($js_path), true );
        }
    }
}
// ماژول خودش را راه‌اندازی می‌کند (مثل بقیه ماژول‌ها)؛ قبلا از functions.php قالب صدا زده می‌شد
Hodima_Localizer_WC::get_instance();
