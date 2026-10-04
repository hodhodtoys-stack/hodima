<?php
/**
 * Theme functions and definitions for hodima Theme
 *
 * @package hodima
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'hodima_VERSION', wp_get_theme()->get( 'Version' ) ?: '1.0.0' );
define( 'hodima_URI', get_template_directory_uri() );
define( 'hodima_DIR', get_template_directory() );

/* ==========================================================
   فونت Vazirmatn پیشخوان به افزونه Hodima Core منتقل شد
   (includes/admin-font.php) — بازسازی قالب، مرحله ۲: فونت پیشخوان
   به قالب سایت ربطی ندارد و با عوض شدن قالب نباید از بین برود.
========================================================== */

/* ============================================================
 * 1. Home Modules
 * ============================================================ */

$home_modules = [
    'home/logic.php',
];

foreach ( $home_modules as $module ) {
    // استفاده از get_stylesheet_directory برای اطمینان از صحت آدرس در سرور
    $filepath = get_stylesheet_directory() . '/' . $module;

    if ( is_file( $filepath ) ) {
        require_once $filepath;
    }
}


/* ============================================================
 * 2. Core Theme Files (inc/)
 * ============================================================ */

$inc_files = [
    'inc/helpers.php',
    'inc/theme-settings/theme-settings.php', // برند، تماس، فوتر و Google Analytics
    'inc/home-layout.php',                   // چیدمان صفحه اصلی (تب «صفحه اصلی» تنظیمات قالب)
    'inc/setup.php',
    'inc/enqueue.php',
    'inc/header.php',
    'inc/footer.php',
    // inc/category-box.php (کادر فرم دسته‌ها در پیشخوان) → Hodima Core: includes/admin-term-box.php
];

foreach ( $inc_files as $file ) {

    $filepath = hodima_DIR . '/' . $file;

    if ( is_file( $filepath ) ) {
        require_once $filepath;
    }

}

/* ============================================================
 * 3. Performance Modules
 * ============================================================ */

// استفاده از wp_normalize_path برای جلوگیری از باگ تداخل مسیر در سرور و ویندوز
// مسیر اصلاح شد: اضافه شدن /inc/
$performance_dir = wp_normalize_path( hodima_DIR . '/inc/performance/' );

if ( is_dir( $performance_dir ) ) {

    $perf_files = glob( $performance_dir . '*.php' );
    
    // اطمینان از اینکه آرایه خالی نیست
    if ( is_array( $perf_files ) && ! empty( $perf_files ) ) {
        foreach ( $perf_files as $module ) {
            require_once $module;
        }
    }

}

/* ============================================================
 * 4. WooCommerce Modules (Auto Load)
 * ============================================================ */

$woo_dir = hodima_DIR . '/inc/woocommerce/';

// بدون ووکامرس این ماژول‌ها فقط Fatal Error تولید می‌کنند
if ( hodima_wc_active() && is_dir( $woo_dir ) ) {

    foreach ( glob( $woo_dir . '*.php' ) as $module ) {
        require_once $module;
    }

}

/* ============================================================
 * 5. user-panel
 * ============================================================ */
// ماژول پنل کاربری اختیاری است و در نسخه عمومی قالب همراه نیست.
// فقط وقتی پوشه user-panel وجود داشته باشد لود می‌شود؛ برای خاموش کردن
// دستی: add_filter( 'hodima_enable_user_panel', '__return_false' );
$user_panel_loader = hodima_DIR . '/user-panel/loader.php';

define(
    'HODIMA_USER_PANEL_ENABLED',
    is_file( $user_panel_loader ) && (bool) apply_filters( 'hodima_enable_user_panel', true )
);

if ( HODIMA_USER_PANEL_ENABLED ) {
    require_once $user_panel_loader;
} else {
    // بدون ماژول، تمپلیت «User Panel» در فهرست قالب‌های برگه نمایش داده نشود.
    add_filter( 'theme_page_templates', static function ( array $templates ): array {
        unset( $templates['page-user-panel.php'] );
        return $templates;
    } );
}

/* ============================================================
 * 6. Google Analytics Code (GA4)
 * ------------------------------------------------------------
 * شناسه قبلا اینجا ثابت نوشته شده بود و برای مدیران و روی استیجینگ هم
 * بارگذاری می‌شد. حالا از «نمایش ← تنظیمات هدیما» خوانده می‌شود
 * (inc/theme-settings/theme-settings.php).
 * ============================================================ */

/* ============================================================
 * 7. قالب آرشیو وبلاگ
 * ============================================================ */
add_filter( 'template_include', 'hodima_route_blog_archive_template', 99 );

function hodima_route_blog_archive_template( $template ) {
    // بررسی می‌کنیم که آیا کاربر در صفحه اصلی وبلاگ یا آرشیو استاندارد پست‌ها (دسته‌بندی/برچسب) است
    if ( is_home() || ( is_archive() && get_post_type() === 'post' ) ) {
        $custom_template = locate_template( 'archive-blog.php' );
        
        // اگر فایل پیدا شد، آن را به جای فایل پیش‌فرض لود کن
        if ( ! empty( $custom_template ) ) {
            return $custom_template;
        }
    }
    
    return $template;
}

/* ============================================================
 * 8. افزونه‌های هدیما
 * ------------------------------------------------------------
 * سئو، فروشگاه و رسانه قبلا داخل قالب بودند و با تعویض قالب داده‌ها و
 * امکاناتشان از کار می‌افتاد. حالا در چهار افزونه جدا هستند و قالب
 * فقط نمایش را بر عهده دارد:
 *   Hodima Core (پیش‌نیاز) · Hodima SEO · Hodima Commerce · Hodima Media
 * قالب بدون آن‌ها هم بدون خطا کار می‌کند؛ فقط امکانات مربوط نمایش داده
 * نمی‌شوند. به مدیر یادآوری می‌شود کدام‌ها فعال نیستند.
 * ============================================================ */
add_action( 'admin_notices', static function (): void {

    if ( ! current_user_can( 'activate_plugins' ) ) {
        return;
    }

    $screen = get_current_screen();
    if ( ! $screen || ! in_array( $screen->id, [ 'dashboard', 'themes', 'plugins', 'update-core' ], true ) ) {
        return;
    }

    /*
     * کمترین نسخه هر افزونه برای این نسخه قالب. از قالب 2.3.0 بخشی از منطق قبلی
     * قالب (اسکیمای صفحه اصلی/فروشگاه/وبلاگ/ویدیوها، بستن XML-RPC، فونت پیشخوان،
     * مرتب‌سازی کاتالوگ، …) در این نسخه‌های افزونه است؛ با افزونه قدیمی‌تر سایت
     * خطا نمی‌دهد ولی آن امکانات تا به‌روزرسانی افزونه غایب‌اند.
     */
    $required = [
        'Hodima Core'     => [ 'HODIMA_CORE_PLUGIN_VERSION', '1.2.0' ],
        'Hodima SEO'      => [ 'HODIMA_SEO_VERSION', '1.11.0' ],
        'Hodima Commerce' => [ 'HODIMA_COMMERCE_VERSION', '1.2.0' ],
        'Hodima Media'    => [ 'HODIMA_MEDIA_VERSION', '1.3.2' ],
    ];

    $missing  = [];
    $outdated = [];

    foreach ( $required as $name => [ $constant, $min ] ) {
        if ( ! defined( $constant ) ) {
            $missing[] = $name;
        } elseif ( version_compare( (string) constant( $constant ), $min, '<' ) ) {
            $outdated[] = "{$name} {$min}";
        }
    }

    if ( $missing ) {
        printf(
            '<div class="notice notice-info"><p><strong>%1$s</strong> %2$s <code>%3$s</code></p></div>',
            esc_html( 'قالب هدیما:' ),
            esc_html( 'این افزونه‌های همراه قالب فعال نیستند و امکاناتشان (سئو، فروشگاه، رسانه) در دسترس نیست:' ),
            esc_html( implode( '، ', $missing ) )
        );
    }

    if ( $outdated ) {
        printf(
            '<div class="notice notice-warning"><p><strong>%1$s</strong> %2$s <code>%3$s</code></p></div>',
            esc_html( 'قالب هدیما:' ),
            esc_html( 'بخشی از امکانات قبلی قالب (اسکیما، امنیت XML-RPC، فونت پیشخوان، مرتب‌سازی محصولات) به افزونه‌ها منتقل شده است. این افزونه‌ها را دست‌کم به این نسخه به‌روز کنید:' ),
            esc_html( implode( '، ', $outdated ) )
        );
    }
} );
