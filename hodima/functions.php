<?php
/**
 * Theme functions and definitions for hodima Theme
 *
 * @package hodima
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// نام ثابت‌ها با حروف کوچک از نسخه‌های اول قالب است و افزونه Hodima SEO
// (google-indexing-api/etag-handler.php) hodima_VERSION را می‌خواند؛ عوض نشوند.
define( 'hodima_VERSION', wp_get_theme()->get( 'Version' ) ?: '1.0.0' ); // phpcs:ignore Generic.NamingConventions.UpperCaseConstantName.ConstantNotUpperCase -- نام قدیمی، افزونه SEO می‌خواند
define( 'hodima_URI', get_template_directory_uri() ); // phpcs:ignore Generic.NamingConventions.UpperCaseConstantName.ConstantNotUpperCase -- نام قدیمی
define( 'hodima_DIR', get_template_directory() ); // phpcs:ignore Generic.NamingConventions.UpperCaseConstantName.ConstantNotUpperCase -- نام قدیمی

/* ==========================================================
   فونت Vazirmatn پیشخوان به افزونه Hodima Core منتقل شد
   (includes/admin-font.php) — بازسازی قالب، مرحله ۲: فونت پیشخوان
   به قالب سایت ربطی ندارد و با عوض شدن قالب نباید از بین برود.
========================================================== */

/**
 * بارگذاری فایل‌های قالب، به ترتیب.
 *
 * قبلا با متغیرهای سراسری ($home_modules، $inc_files، $perf_files، …) در
 * فضای سراسری PHP؛ هر افزونه‌ای با همان نام‌ها مقدارشان را عوض می‌کرد یا
 * برعکس. حالا داخل یک تابع (متغیرها محلی‌اند). ترتیب همان قبلی است — ترتیب
 * ثبت هوک‌ها (و در نتیجه ترتیب CSSها در صفحه) به آن بستگی دارد.
 */
function hodima_load_theme_files(): void {

	$files = [
		// ۰. نوع‌ها (enum و کلاس‌های PHP 8.4) — پیش از هر چیز
		'inc/classes/class-hodima-theme-asset.php',
		'inc/classes/enum-hodima-contact-channel.php',
		'inc/classes/class-hodima-contact-link.php',
		'inc/classes/enum-hodima-setting-type.php',
		'inc/classes/enum-hodima-home-tone.php',

		// ۱. صفحه اصلی
		'home/logic.php',
		'home/legacy.php',                       // نام‌های قدیمی arian_* (سازگاری)

		// ۲. هسته قالب
		'inc/helpers.php',
		'inc/media-sections.php',                // بخش‌های «رسانه» (معرفی، ویدیو، پادکست، FAQ) در قالب‌ها
		'inc/theme-settings/theme-settings.php', // برند، تماس، فوتر و Google Analytics
		'inc/home-layout.php',                   // چیدمان صفحه اصلی (تب «صفحه اصلی» تنظیمات قالب)
		'inc/blog.php',                          // نام وبلاگ و مقالات مرتبط (تب «وبلاگ»)
		'inc/breadcrumb.php',                    // مسیر راهنمای مقاله‌ها و آرشیو وبلاگ (یکی با اسکیما)
		'inc/setup.php',
		'inc/enqueue.php',
		'inc/header.php',
		'inc/footer.php',
		// inc/category-box.php (کادر فرم دسته‌ها در پیشخوان) → Hodima Core: includes/admin-term-box.php
	];

	// ۳. بهینه‌سازی (به ترتیب الفبا؛ 00-litespeed.php اول)
	$files = [ ...$files, ...hodima_theme_dir_files( 'inc/performance' ) ];

	// ۴. ووکامرس — بدون ووکامرس این ماژول‌ها فقط Fatal Error تولید می‌کنند.
	// hodima_wc_active در inc/helpers.php (یا Hodima Core) است و همین بالاتر لود شد.
	foreach ( $files as $file ) {
		$path = hodima_DIR . '/' . $file;
		if ( is_file( $path ) ) {
			require_once $path;
		}
	}

	if ( hodima_wc_active() ) {
		foreach ( hodima_theme_dir_files( 'inc/woocommerce' ) as $file ) {
			require_once hodima_DIR . '/' . $file;
		}
	}
}

/**
 * فایل‌های PHP یک پوشه قالب (مسیر نسبی)، به ترتیب الفبا.
 *
 * @return list<string>
 */
function hodima_theme_dir_files( string $dir ): array {

	// wp_normalize_path: جلوگیری از تداخل جداکننده مسیر در ویندوز
	$found = glob( wp_normalize_path( hodima_DIR . '/' . $dir . '/' ) . '*.php' ) ?: [];
	sort( $found );

	return array_map( static fn( string $path ): string => $dir . '/' . basename( $path ), $found );
}

hodima_load_theme_files();

/* ============================================================
 * ۵. user-panel
 * ============================================================ */
// ماژول پنل کاربری اختیاری است و در نسخه عمومی قالب همراه نیست.
// فقط وقتی پوشه user-panel وجود داشته باشد لود می‌شود (عمدا در فضای سراسری،
// مثل قبل)؛ برای خاموش کردن دستی: add_filter( 'hodima_enable_user_panel', '__return_false' );
define(
	'HODIMA_USER_PANEL_ENABLED',
	is_file( hodima_DIR . '/user-panel/loader.php' ) && (bool) apply_filters( 'hodima_enable_user_panel', true )
);

if ( HODIMA_USER_PANEL_ENABLED ) {
	require_once hodima_DIR . '/user-panel/loader.php';
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

function hodima_route_blog_archive_template( mixed $template ): mixed {
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
