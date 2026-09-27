<?php
// جلوگیری از دسترسی مستقیم
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ۰. تعریف مسیر ریشه افزونه (پوشه schema) برای فایل‌های بیرون از admin
if ( ! defined( 'HODIMA_SCHEMA_PATH' ) ) {
    define( 'HODIMA_SCHEMA_PATH', dirname( __DIR__ ) );
}

// ==============================================================
// ۰. لود کردن هسته‌های پردازشی (Core Files)
// ==============================================================
// الف) فراخوانی فایل موتور سایت‌مپ داینامیک
$sitemap_core_path = HODIMA_SCHEMA_PATH . '/sitemap-core.php';
if ( file_exists( $sitemap_core_path ) ) {
    require_once $sitemap_core_path;
}

// ب) فراخوانی موتور فید پادکست
$podcast_core_path = HODIMA_SCHEMA_PATH . '/podcast-feed-core.php';
if ( file_exists( $podcast_core_path ) ) {
    require_once $podcast_core_path;
}

// ج) فراخوانی موتور فایل هوش مصنوعی (LLMs)
// llms-core.php حذف شد: فقط دو تابع خالی نمایشی داشت.
// پیاده‌سازی واقعی llms.txt در components/indexnow-sync/includes/aeo/ است.

// د) فراخوانی موتور اسکیمای اختصاصی برگه‌ها (جایگزین فید قدیمی ویدئو)
$page_schema_path = HODIMA_SCHEMA_PATH . '/page-schema-pro.php';
if ( file_exists( $page_schema_path ) ) {
    require_once $page_schema_path;
}

// نکته: اگر سایر فایل‌های schema/*.php از طریق functions.php قالب (خارج از این
// ZIP) لود می‌شوند، خطوط زیر تکراری اما کاملاً بی‌خطر هستند (require_once).
// این تضمین می‌کند که حتی اگر آن مکانیزم به هر دلیلی فایل جدیدی مثل
// corporate-schema.php را نمی‌شناسد، در سایت زنده هیچ اسکیمایی گم نشود.
$hodima_all_schema_files = [
    'blog-schema.php',
    'breadcrumb-schema.php',
    'category-schema-pro.php',
    'corporate-schema.php',
    'homepage-schema.php',
    'imageobject-schema.php',
    'product-schema-pro.php',
    'schema-cleaner.php',
];
foreach ( $hodima_all_schema_files as $hodima_schema_file ) {
    $hodima_schema_file_path = HODIMA_SCHEMA_PATH . '/' . $hodima_schema_file;
    if ( file_exists( $hodima_schema_file_path ) ) {
        require_once $hodima_schema_file_path;
    }
}

// ==============================================================
// ۱. لود کردن فایل‌های استایل (CSS) + JS
// ==============================================================
add_action('admin_enqueue_scripts', 'hodima_schema_assets');

function hodima_schema_assets($hook) {
    $allowed_pages = array('post.php', 'post-new.php', 'edit-tags.php', 'term.php');

    if (strpos($hook, 'hodima') !== false || in_array($hook, $allowed_pages)) {
        $schema_uri  = HODIMA_SEO_URL . '/schema';
        $schema_path = HODIMA_SCHEMA_PATH;

        $css_file = $schema_path . '/admin/assets/css/init.css';

        // نسخه‌دهی واقعی بر اساس زمان آخرین تغییر فایل (نه هر بار time())
        // این کار کش مرورگر را فقط زمانی باطل می‌کند که فایل واقعاً تغییر کرده باشد
        $css_ver = file_exists( $css_file ) ? filemtime( $css_file ) : false;

        wp_enqueue_style('hodima-init-css', $schema_uri . '/admin/assets/css/init.css', array(), $css_ver);
    }
}

// ==============================================================
// ۲. توابع کمکی برای قالب‌بندی صفحات (Header & Footer)
// ==============================================================
function hodima_view_header($title, $desc, $icon = '🚀') {
    ?>
    <div class="hodima-wrapper">
        <?php hodima_schema_admin_nav(); ?>
        <div class="h-header">
            <div class="h-icon"><?php echo esc_html($icon); ?></div>
            <div>
                <h1 style="font-weight: inherit;"><?php echo esc_html($title); ?></h1>
                <p style="font-weight: inherit;"><?php echo esc_html($desc); ?></p>
            </div>
        </div>
        <div class="h-card-container">
    <?php
}

function hodima_view_footer() {
    echo '</div></div>';
}

function hodima_view_form_footer() {
    ?>
    <div class="h-form-footer">
        <div class="h-submit-wrapper">
            <?php submit_button('ذخیره تنظیمات', 'primary', 'submit', false); ?>
        </div>
    </div>
    <?php
}

// ==============================================================
// ۳. ثبت منوها و زیرمنوها
// --------------------------------------------------------------
// «اسکیما» دیگر آیکون جداگانه در منوی اصلی پیشخوان ندارد؛ یک زیرمنو
// در پنل «هدیما» (hodima-core) است، کنار «ماژول‌های سئو».
//
// سیزده صفحه تنظیمات همان آدرس‌های قبلی (admin.php?page=…) را دارند —
// لینک‌ها و بوک‌مارک‌ها خراب نمی‌شوند — ولی فقط «اسکیما» در منو دیده
// می‌شود؛ بقیه از کارت‌های پیشخوان اسکیما و نوار بالای هر صفحه باز
// می‌شوند. اگر Hodima Core فعال نباشد، منوی سطح بالای قبلی ساخته می‌شود.
// ==============================================================

/**
 * صفحه‌های تنظیمات اسکیما: اسلاگ => [عنوان صفحه، عنوان کوتاه، callback].
 * اولی صفحه اصلی (پیشخوان اسکیما) است.
 */
function hodima_schema_admin_pages(): array {
    return [
        'hodima-schema'            => [ 'پیشخوان اسکیما', 'پیشخوان', 'hodima_schema_dashboard_callback' ],
        'hodima-schema-homepage'   => [ 'اسکیمای صفحه اصلی', 'صفحه اصلی', 'hodima_schema_homepage_callback' ],
        'hodima-schema-breadcrumb' => [ 'اسکیمای بردکرامب', 'بردکرامب', 'hodima_schema_breadcrumb_callback' ],
        'hodima-schema-blog'       => [ 'اسکیمای بلاگ', 'بلاگ', 'hodima_schema_blog_callback' ],
        'hodima-schema-category'   => [ 'اسکیمای دسته‌بندی', 'دسته‌بندی', 'hodima_schema_category_callback' ],
        'hodima-schema-product'    => [ 'اسکیمای محصولات', 'محصولات', 'hodima_schema_product_callback' ],
        'hodima-schema-image'      => [ 'اسکیمای تصاویر', 'تصاویر', 'hodima_schema_image_callback' ],
        'hodima-schema-page'       => [ 'اسکیمای برگه‌ها', 'برگه‌ها', 'hodima_schema_page_callback' ],
        'hodima-sitemap'           => [ 'نقشه سایت (XML)', 'نقشه سایت', 'hodima_sitemap_callback' ],
        'hodima-podcast'           => [ 'فید پادکست (RSS)', 'فید پادکست', 'hodima_podcast_callback' ],
        'hodima-schema-tables'     => [ 'جداول هدیما', 'جداول', 'hodima_schema_tables_callback' ],
        'hodima-llms'              => [ 'هوش مصنوعی (LLMs)', 'هوش مصنوعی', 'hodima_llms_callback' ],
        'hodima-schema-cleaner'    => [ 'پاکسازی هوشمند اسکیما', 'پاکسازی', 'hodima_schema_cleaner_callback' ],
    ];
}

/** والد منو: پنل «هدیما» اگر Hodima Core فعال است، وگرنه منوی سطح بالای «اسکیما». */
function hodima_schema_menu_parent(): string {
    return defined( 'Hodima\Core\Admin\HUB_SLUG' ) ? \Hodima\Core\Admin\HUB_SLUG : 'hodima-schema';
}

/** آیا صفحه فعلی پیشخوان یکی از صفحه‌های تنظیمات اسکیما است؟ */
function hodima_schema_current_admin_page(): string {
    $page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط تشخیص صفحه
    return isset( hodima_schema_admin_pages()[ $page ] ) ? $page : '';
}

add_action( 'admin_menu', 'hodima_register_schema_menus' );

function hodima_register_schema_menus() {
    $parent = hodima_schema_menu_parent();
    $pages  = hodima_schema_admin_pages();

    if ( 'hodima-schema' === $parent ) {
        // بدون Hodima Core: منوی سطح بالای قبلی
        add_menu_page( 'مدیریت اسکیمای حرفه‌ای', 'اسکیما', 'manage_options', 'hodima-schema', 'hodima_schema_dashboard_callback', 'dashicons-networking', 41 );
    }

    foreach ( $pages as $slug => [ $title, $menu_title, $callback ] ) {
        add_submenu_page(
            $parent,
            $title,
            'hodima-schema' === $slug && 'hodima-schema' !== $parent ? 'اسکیما' : $menu_title,
            'manage_options',
            $slug,
            $callback
        );
    }
}

/*
 * زیر پنل «هدیما» فقط «اسکیما» در منو بماند.
 *
 * زمان‌بندی مهم است: وردپرس دسترسی به صفحه، هوک صفحه و عنوان آن را از
 * همین فهرست زیرمنو پیدا می‌کند. اگر در admin_menu حذف شوند، صفحه‌ها
 * «اجازه دسترسی ندارید» می‌دهند. admin_head بعد از همه این‌ها و درست
 * پیش از چاپ منو اجرا می‌شود؛ پس فقط از *نمایش* منو برداشته می‌شوند
 * تا پنل هدیما با ۱۳ زیرمنو شلوغ نشود.
 */
add_action( 'admin_head', static function (): void {
    $parent = hodima_schema_menu_parent();
    if ( 'hodima-schema' === $parent ) {
        return;
    }
    foreach ( array_keys( hodima_schema_admin_pages() ) as $slug ) {
        if ( 'hodima-schema' !== $slug ) {
            remove_submenu_page( $parent, $slug );
        }
    }
} );

// روی صفحه‌های پنهان، «هدیما ← اسکیما» در منو فعال دیده شود
add_filter( 'parent_file', static function ( $parent_file ) {
    return '' !== hodima_schema_current_admin_page() ? hodima_schema_menu_parent() : $parent_file;
} );

add_filter( 'submenu_file', static function ( $submenu_file ) {
    return '' !== hodima_schema_current_admin_page() ? 'hodima-schema' : $submenu_file;
} );

/** نوار ناوبری بین صفحه‌های اسکیما (بالای هر صفحه). */
function hodima_schema_admin_nav(): void {
    $current = hodima_schema_current_admin_page();
    if ( '' === $current ) {
        return;
    }
    echo '<nav class="h-schema-nav" aria-label="صفحه‌های اسکیما">';
    foreach ( hodima_schema_admin_pages() as $slug => [ , $menu_title ] ) {
        printf(
            '<a href="%s"%s>%s</a>',
            esc_url( admin_url( 'admin.php?page=' . $slug ) ),
            $slug === $current ? ' aria-current="page"' : '',
            esc_html( $menu_title )
        );
    }
    echo '</nav>';
}

// ==============================================================
// ۴. توابع Callbacks برای منوها (همه از پوشه views لود می‌شوند)
// ==============================================================
function hodima_schema_dashboard_callback() { include __DIR__ . '/views/view-dashboard.php'; }
function hodima_schema_homepage_callback()  { include __DIR__ . '/views/view-homepage.php'; }
function hodima_schema_blog_callback()      { include __DIR__ . '/views/view-blog.php'; }
function hodima_schema_breadcrumb_callback(){ include __DIR__ . '/views/view-breadcrumb.php'; }
function hodima_schema_category_callback()  { include __DIR__ . '/views/view-category.php'; }
function hodima_schema_image_callback()     { include __DIR__ . '/views/view-image.php'; }
function hodima_schema_product_callback()   { include __DIR__ . '/views/view-product.php'; }
function hodima_schema_page_callback()      { include __DIR__ . '/views/view-page-schema.php'; } // جایگزین شد
function hodima_sitemap_callback()          { include __DIR__ . '/views/view-sitemap.php'; }
function hodima_podcast_callback()          { include __DIR__ . '/views/view-podcast.php'; } 
function hodima_schema_tables_callback()    { include __DIR__ . '/views/view-tables.php'; } 
function hodima_llms_callback()             { include __DIR__ . '/views/view-llms.php'; }
function hodima_schema_cleaner_callback()   { include __DIR__ . '/views/view-cleaner.php'; }