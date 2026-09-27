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
// ==============================================================
add_action('admin_menu', 'hodima_register_schema_menus');

function hodima_register_schema_menus() {
    $emoji = '🧩';
    $menu_icon = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><text x="0" y="16" font-size="16">' . $emoji . '</text></svg>');

    add_menu_page(
        'مدیریت اسکیمای حرفه‌ای',
        'اسکیما',
        'manage_options',
        'hodima-schema',
        'hodima_schema_dashboard_callback',
        $menu_icon,
        41
    );

    add_submenu_page('hodima-schema', 'پیشخوان هدیما', 'پیشخوان', 'manage_options', 'hodima-schema', 'hodima_schema_dashboard_callback');
    add_submenu_page('hodima-schema', 'اسکیمای صفحه اصلی', 'صفحه اصلی', 'manage_options', 'hodima-schema-homepage', 'hodima_schema_homepage_callback');
    add_submenu_page('hodima-schema', 'اسکیمای بردکرامب', 'بردکرامب', 'manage_options', 'hodima-schema-breadcrumb', 'hodima_schema_breadcrumb_callback');
    add_submenu_page('hodima-schema', 'اسکیمای بلاگ', 'بلاگ', 'manage_options', 'hodima-schema-blog', 'hodima_schema_blog_callback');
    add_submenu_page('hodima-schema', 'اسکیمای دسته‌بندی', 'دسته‌بندی ', 'manage_options', 'hodima-schema-category', 'hodima_schema_category_callback');
    add_submenu_page('hodima-schema', 'اسکیمای محصولات', 'محصولات ', 'manage_options', 'hodima-schema-product', 'hodima_schema_product_callback');
    add_submenu_page('hodima-schema', 'اسکیمای تصاویر', 'تصاویر', 'manage_options', 'hodima-schema-image', 'hodima_schema_image_callback');
    add_submenu_page('hodima-schema', 'اسکیمای برگه‌ها', 'برگه‌ها ', 'manage_options', 'hodima-schema-page', 'hodima_schema_page_callback');
    add_submenu_page('hodima-schema', 'نقشه سایت (XML)', 'نقشه سایت', 'manage_options', 'hodima-sitemap', 'hodima_sitemap_callback');
    add_submenu_page('hodima-schema', 'فید پادکست (RSS)', 'فید پادکست', 'manage_options', 'hodima-podcast', 'hodima_podcast_callback');
    add_submenu_page('hodima-schema', 'جداول هدیما', 'جداول هدیما', 'manage_options', 'hodima-schema-tables', 'hodima_schema_tables_callback'); 
    add_submenu_page('hodima-schema', 'هوش مصنوعی (LLMs)', 'هوش مصنوعی', 'manage_options', 'hodima-llms', 'hodima_llms_callback');
    add_submenu_page('hodima-schema', 'پاکسازی هوشمند اسکیما', 'پاکسازی اسکیما', 'manage_options', 'hodima-schema-cleaner', 'hodima_schema_cleaner_callback');
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