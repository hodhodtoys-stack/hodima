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
        ?>
        <div class="wrap hodima-loc-admin">
            <!-- ترفند جلوگیری از انتقال نوتیفیکیشن پیش‌فرض وردپرس به داخل هدر -->
            <h1 style="display: none;"></h1>

            <header class="hodima-loc-header">
                <h2 class="hodima-title">تنظیمات</h2>
                <span class="hodima-version-badge">نسخه 1.1.4</span>
            </header>
            
            <div style="margin-block-start: 15px;">
                <?php 
                $settings_errors = get_settings_errors();
                if ( ! empty( $settings_errors ) ) {
                    foreach ( $settings_errors as $error ) {
                        $icon = in_array( $error['type'], ['success', 'updated'], true ) ? '✅' : '⚠️';
                        ?>
                        <div class="hodima-loc-notice" role="alert">
                            <span><?= $icon . ' ' . esc_html( $error['message'] ); ?></span>
                            <button type="button" class="hodima-loc-notice-close" aria-label="بستن" onclick="this.parentElement.style.display='none';">&times;</button>
                        </div>
                        <?php
                    }
                    global $wp_settings_errors;
                    $wp_settings_errors = [];
                }
                ?>
            </div>

            <section class="hodima-loc-card">
                <form method="post" action="options.php">
                    <?php settings_fields( 'hodima_localizer_group' ); ?>
                    
                    <div class="hodima-loc-table-wrapper" style="margin-block-end: 25px;">
                        <table class="hodima-loc-table">
                            <tbody>
                                <tr>
                                    <td style="inline-size: 280px; font-weight: 600;">تغییر تاریخ به شمسی:</td>
                                    <td>
                                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                            <input type="checkbox" name="hodima_wc_jalali_status" value="yes" <?php checked( get_option( 'hodima_wc_jalali_status', 'yes' ), 'yes' ); ?> />
                                            <span>فعال‌سازی الگوریتم تاریخ جلالی در سراسر سایت</span>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="font-weight: 600;">استان‌ها و شهرهای ایران:</td>
                                    <td>
                                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                            <input type="checkbox" name="hodima_wc_iran_cities_status" value="yes" <?php checked( get_option( 'hodima_wc_iran_cities_status', 'yes' ), 'yes' ); ?> />
                                            <span>جایگزینی دیتابیس لوکال شهرها (حذف فیلد متنی)</span>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="font-weight: 600;">بهینه‌ساز فرم صورتحساب:</td>
                                    <td>
                                        <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                            <input type="checkbox" name="hodima_wc_checkout_optimizer_status" value="yes" <?php checked( get_option( 'hodima_wc_checkout_optimizer_status', 'yes' ), 'yes' ); ?> />
                                            <span>مرتب‌سازی آدرس و اعتبارسنجی ۱۱ رقمی موبایل</span>
                                        </label>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    
                    <button type="submit" name="submit" class="hodima-loc-btn hodima-loc-btn-primary">ذخیره یکپارچه تنظیمات</button>
                </form>
            </section>
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
