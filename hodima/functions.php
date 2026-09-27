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
   Admin Font (Vazirmatn)
========================================================== */
add_action('admin_head', 'hodima_custom_admin_font');

function hodima_custom_admin_font() {
    // مسیردهی دقیق با استفاده از ثابتی که در بالای فایل تعریف کردید
    $font_path = esc_url( hodima_URI . '/assets/fonts/' );
    ?>
    <style>
        /* لود فونت‌ها در بک‌اند */
        @font-face { font-family: 'Vazirmatn'; src: url('<?php echo $font_path; ?>Vazirmatn-Regular.woff2') format('woff2'); font-weight: 400; font-style: normal; font-display: swap; }
        @font-face { font-family: 'Vazirmatn'; src: url('<?php echo $font_path; ?>Vazirmatn-Medium.woff2') format('woff2'); font-weight: 500; font-style: normal; font-display: swap; }
        @font-face { font-family: 'Vazirmatn'; src: url('<?php echo $font_path; ?>Vazirmatn-Bold.woff2') format('woff2'); font-weight: 700; font-style: normal; font-display: swap; }
        @font-face { font-family: 'Vazirmatn'; src: url('<?php echo $font_path; ?>Vazirmatn-Black.woff2') format('woff2'); font-weight: 900; font-style: normal; font-display: swap; }

        /* قفل کردن فونت روی تمام ساختار پنل مدیریت */
        body, 
        #wpbody, 
        .wp-core-ui, 
        #wpadminbar *, 
        .wp-admin input, 
        .wp-admin select, 
        .wp-admin textarea, 
        .wp-admin button,
        .wp-admin a,
        .wp-admin p,
        .wp-admin h1, .wp-admin h2, .wp-admin h3, .wp-admin h4, .wp-admin h5, .wp-admin h6 {
            font-family: 'Vazirmatn', system-ui, -apple-system, sans-serif !important;
        }
    </style>
    <?php
}

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
    'inc/setup.php',
    'inc/enqueue.php',
    'inc/header.php',
    'inc/footer.php',
    'inc/category-box.php',
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
 * 5. Components Load (Manifest Based)
 * ============================================================ */
$components_dir = hodima_DIR . '/components/';

/**
 * نقشه صریح کامپوننت‌ها: هر کلید نام پوشه و هر مقدار فایل‌های لودر آن است.
 * حدس زدن نام فایل حذف شد تا کامپوننت چندفایلی (notification) خطای کاذب ندهد.
 */
$active_components = [
    'expandable-boxes'    => [ 'expandable-boxes.php' ],
    'hodima-slider'       => [ 'hodima-slider.php' ],
    'hodima-Stories'      => [ 'hodima-Stories.php' ],
    'notification'        => [ 'notification-cpt.php', 'notification-metabox.php', 'notification-front.php' ],
    'phone'               => [ 'phone-form.php' ],
    'search'              => [ 'search.php' ],
    'video-watch'         => [ 'video-watch.php' ],
    'google-indexing-api' => [ 'main.php' ],
    'indexnow-sync'       => [ 'indexnow-sync.php' ],
];

foreach ( $active_components as $component => $component_files ) {

    foreach ( $component_files as $component_file ) {

        $loader_file = $components_dir . $component . '/' . $component_file;

        if ( is_file( $loader_file ) ) {
            require_once $loader_file;
            continue;
        }

        // فقط در حالت دیباگ لاگ بزن تا error_log هاست در حالت عادی پر نشود
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'Hodima Theme: component file not found -> ' . $component . '/' . $component_file );
        }
    }
}

/* ============================================================
 * 6. Schema Loader
 * ============================================================ */

$schema_dir = hodima_DIR . '/schema/';

if ( is_dir( $schema_dir ) ) {

    // 1. لود کردن کدهای اسکیمای شما (فایل‌های داخل مسیر اصلی schema)
    foreach ( glob( $schema_dir . '*.php' ) as $file ) {
        require_once $file;
    }

}

// 2. لود کردن فایل تنظیمات پنل ادمین اسکیما
$admin_schema_init = $schema_dir . 'admin/init.php';
if ( file_exists( $admin_schema_init ) ) {
    require_once $admin_schema_init;
}

/* ============================================================
 * 8 .media-system
 * ============================================================ */
// Load Custom Media System (Video, Voice, FAQ, AI/Discover)
require_once get_stylesheet_directory() . '/media-system/media-init.php';


/* ============================================================
 * 9 .user-panel
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
 * 11 .Manual Related Links Module
 * ============================================================ */
$manual_related_link_file = hodima_DIR . '/inc/manual_related_link/manual_related_link.php';

if ( file_exists( $manual_related_link_file ) ) {
    require_once $manual_related_link_file;
}

/* ============================================================
 * 12 .hodima-table  .hodima-woo-table
 * ============================================================ */
   require_once get_template_directory() . '/inc/hodima-table/hodima-table.php';
   if ( hodima_wc_active() ) {
       require_once get_theme_file_path( 'inc/hodima-woo-table/woo-table.php' );
   }

/* ============================================================
 * 13 .video-watch
 * ============================================================ */
   add_filter('upload_mimes', function ($mimes) {
    $mimes['vtt'] = 'text/vtt';
    return $mimes;
});

/* ============================================================
 * 14 .Google Analytics Code (GA4)
 * ------------------------------------------------------------
 * شناسه قبلا اینجا ثابت نوشته شده بود و برای مدیران و روی استیجینگ هم
 * بارگذاری می‌شد. حالا از «نمایش ← تنظیمات هدیما» خوانده می‌شود
 * (inc/theme-settings/theme-settings.php).
 * ============================================================ */

/* ============================================================
 * 15 . برسی وبلاگ برای مدیا
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

/* =========================================================
 * تنظیمات پست‌تایپ ویدئوها (سازگار با برگه اختصاصی)
 * ========================================================= */
add_filter( 'register_post_type_args', 'hodima_disable_video_archive_support', 99, 2 );
function hodima_disable_video_archive_support( $args, $post_type ) {
    // چون از "برگه" برای لیست ویدئوها استفاده کردیم، آرشیو پیش‌فرض را غیرفعال می‌کنیم
    // تا با آدرس (Slug) برگه تداخل پیدا نکند و خطای 404 ندهد.
    if ( 'video' === $post_type ) {
        $args['public']      = true;
        $args['has_archive'] = false; // این گزینه حتما باید false باشد
    }
    return $args;
}

/* ============================================================
 * 16. core
 * ============================================================ */
require_once get_template_directory() . '/core/router/router.php';
require_once get_theme_file_path( 'core/redirects/init.php' );

$seobox_init_path = get_template_directory() . '/core/seobox/seobox-init.php';

if ( file_exists( $seobox_init_path ) ) {
    require_once $seobox_init_path;
}


// Load Topic Cluster Module
$topiccluster_path = get_template_directory() . '/core/topiccluster/topiccluster-init.php';
if ( file_exists( $topiccluster_path ) ) {
    require_once $topiccluster_path;
}




 // Load Hodima Core: Unified WooCommerce Engine
$hodima_localizer_path = get_theme_file_path( 'core/time-jalali/time-jalali.php' );

if ( hodima_wc_active() && file_exists( $hodima_localizer_path ) ) {
    require_once $hodima_localizer_path;
    \Hodima\Core\Time_Jalali\Hodima_Localizer_WC::get_instance();
}




// افزودن امکانات به دسته‌بندی‌های وبلاگ
$cat_blog_file = get_template_directory() . '/core/cat-blog/cat-blog.php';
if ( file_exists( $cat_blog_file ) ) {
    require_once $cat_blog_file;
}







