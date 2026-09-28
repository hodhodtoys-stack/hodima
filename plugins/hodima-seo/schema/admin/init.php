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
    // فقط صفحه‌های تنظیمات اسکیما. قبلا روی هر صفحه‌ای که «hodima» در نامش بود
    // (اسلایدر، استوری، ریدایرکت، بومی‌سازی...) و حتی ویرایش نوشته و دسته لود
    // می‌شد و با :root و قوانین سراسری .form-table ظاهر آن‌ها را به هم می‌ریخت.
    if ( '' !== hodima_schema_current_admin_page() ) {
        $schema_uri  = HODIMA_SEO_URL . '/schema';
        $schema_path = HODIMA_SCHEMA_PATH;

        $css_file = $schema_path . '/admin/assets/css/init.css';

        // نسخه‌دهی واقعی بر اساس زمان آخرین تغییر فایل (نه هر بار time())
        // این کار کش مرورگر را فقط زمانی باطل می‌کند که فایل واقعاً تغییر کرده باشد
        $css_ver = file_exists( $css_file ) ? filemtime( $css_file ) : false;

        wp_enqueue_style('hodima-init-css', $schema_uri . '/admin/assets/css/init.css', wp_style_is( 'hodima-admin-ui', 'registered' ) ? array( 'hodima-admin-ui' ) : array(), $css_ver);
    }
}

// ==============================================================
// ۲. توابع کمکی برای قالب‌بندی صفحات (Header & Footer)
// ==============================================================
/**
 * هدر یکسان صفحه‌های اسکیما: هدر ← نوار صفحه‌ها (زیر هدر) ← اعلان‌ها ← محتوا.
 *
 * باگ قبلی: نوار ناوبری ۱۳ صفحه *بالای* هدر چاپ می‌شد و اعلان‌های وردپرس
 * داخل هدر رنگی می‌افتادند. آیکون‌ها ایموجی بودند که اسکریپت ایموجی وردپرس
 * از CDN خارجی تصویرشان را می‌گرفت؛ حالا Dashicons داخلی.
 *
 * @param string $icon نام Dashicon (مثلا dashicons-admin-home)؛ خالی = آیکون همان صفحه
 */
function hodima_view_header($title, $desc, $icon = '') {
    $current = hodima_schema_current_admin_page();
    $pages   = hodima_schema_admin_pages();

    if ( ! is_string( $icon ) || ! str_starts_with( $icon, 'dashicons-' ) ) {
        $icon = $pages[ $current ][3] ?? 'dashicons-editor-code';
    }

    $tabs = array();
    foreach ( $pages as $slug => $page ) {
        $tabs[ $slug ] = array(
            'label' => $page[1],
            'url'   => admin_url( 'admin.php?page=' . $slug ),
            'icon'  => $page[3] ?? '',
        );
    }

    echo '<div class="wrap hd-wrap hodima-wrapper">';
    hodima_admin_header( array(
        'title'       => $title,
        'description' => $desc,
        'icon'        => $icon,
        'tabs'        => '' !== $current ? $tabs : array(),
        'current'     => $current,
        'tabs_label'  => 'صفحه‌های اسکیما',
    ) );
    echo '<div class="hd-body h-card-container">';
}

function hodima_view_footer() {
    echo '</div></div>';
}

/** نوار ذخیره پایین فرم (چسبان به پایین صفحه). */
function hodima_view_form_footer() {
    ?>
    <div class="hd-actions">
        <?php submit_button('ذخیره تنظیمات', 'primary', 'submit', false); ?>
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
 * صفحه‌های تنظیمات اسکیما: اسلاگ => [عنوان صفحه، عنوان کوتاه، callback، آیکون Dashicons].
 * اولی صفحه اصلی (پیشخوان اسکیما) است.
 */
function hodima_schema_admin_pages(): array {
    return [
        'hodima-schema'            => [ 'پیشخوان اسکیما', 'پیشخوان', 'hodima_schema_dashboard_callback', 'dashicons-dashboard' ],
        'hodima-schema-homepage'   => [ 'اسکیمای صفحه اصلی', 'صفحه اصلی', 'hodima_schema_homepage_callback', 'dashicons-admin-home' ],
        'hodima-schema-breadcrumb' => [ 'اسکیمای بردکرامب', 'بردکرامب', 'hodima_schema_breadcrumb_callback', 'dashicons-editor-ol' ],
        'hodima-schema-blog'       => [ 'اسکیمای بلاگ', 'بلاگ', 'hodima_schema_blog_callback', 'dashicons-edit-page' ],
        'hodima-schema-category'   => [ 'اسکیمای دسته‌بندی', 'دسته‌بندی', 'hodima_schema_category_callback', 'dashicons-category' ],
        'hodima-schema-product'    => [ 'اسکیمای محصولات', 'محصولات', 'hodima_schema_product_callback', 'dashicons-products' ],
        'hodima-schema-image'      => [ 'اسکیمای تصاویر', 'تصاویر', 'hodima_schema_image_callback', 'dashicons-format-image' ],
        'hodima-schema-page'       => [ 'اسکیمای برگه‌ها', 'برگه‌ها', 'hodima_schema_page_callback', 'dashicons-admin-page' ],
        'hodima-sitemap'           => [ 'نقشه سایت (XML)', 'نقشه سایت', 'hodima_sitemap_callback', 'dashicons-networking' ],
        'hodima-podcast'           => [ 'فید پادکست (RSS)', 'فید پادکست', 'hodima_podcast_callback', 'dashicons-microphone' ],
        'hodima-schema-tables'     => [ 'جداول هدیما', 'جداول', 'hodima_schema_tables_callback', 'dashicons-editor-table' ],
        'hodima-llms'              => [ 'هوش مصنوعی (LLMs)', 'هوش مصنوعی', 'hodima_llms_callback', 'dashicons-lightbulb' ],
        'hodima-schema-cleaner'    => [ 'پاکسازی هوشمند اسکیما', 'پاکسازی', 'hodima_schema_cleaner_callback', 'dashicons-shield' ],
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

// نوار ناوبری صفحه‌های اسکیما حالا تب‌های زیر هدر است (hodima_view_header).

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