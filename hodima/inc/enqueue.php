<?php
/**
 * Enqueue scripts and styles
 * مدیریت هوشمند بارگذاری استایل‌ها بر اساس نام‌گذاری جدید
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * نسخه‌دهی امن برای فایل‌های استاتیک.
 * اگر فایل وجود نداشته باشد، به جای PHP Warning ناشی از filemtime()
 * به نسخه قالب برمی‌گردد.
 *
 * @param string $rel_path مسیر نسبی از ریشه قالب، مثلا: assets/css/header.css
 */
function hodima_asset_version( string $rel_path ): string {
    static $cache = [];

    if ( isset( $cache[ $rel_path ] ) ) {
        return $cache[ $rel_path ];
    }

    $abs_path = hodima_DIR . '/' . ltrim( $rel_path, '/' );
    $fallback = defined( 'hodima_VERSION' ) ? (string) hodima_VERSION : '1.0.0';

    $cache[ $rel_path ] = is_file( $abs_path ) ? (string) filemtime( $abs_path ) : $fallback;

    return $cache[ $rel_path ];
}

/**
 * true اگر فایل استاتیک واقعا روی دیسک وجود داشته باشد.
 */
function hodima_asset_exists( string $rel_path ): bool {
    return is_file( hodima_DIR . '/' . ltrim( $rel_path, '/' ) );
}

function hodima_enqueue_scripts() {

    // توکن‌های طراحی باید قبل از هر استایل دیگری لود شوند تا متغیرها
    // هنگام رسیدن به CSS ماژول‌ها تعریف شده باشند.
    wp_enqueue_style(
        'hodima-tokens',
        hodima_URI . '/assets/css/tokens.css',
        array(),
        hodima_asset_version( 'assets/css/tokens.css' )
    );
    // از تعریف hodima_VERSION در functions.php استفاده کنید، اگر تعریف شده است.
    // در غیر این صورت، این خط را اضافه کنید یا از filemtime استفاده کنید.
    $theme_version = hodima_asset_version( 'style.css' );

    // 1. استایل اصلی سایت
    wp_enqueue_style( 'hodima-style', get_template_directory_uri() . '/style.css', array(), $theme_version );

    /*
     * صفحات فروشگاهی.
     * توابع is_shop() و مشابه را ووکامرس تعریف می‌کند؛ بدون گارد، غیرفعال
     * شدن ووکامرس کل سایت را با Fatal Error از کار می‌انداخت.
     */
    $wc          = hodima_wc_active();
    $is_product  = $wc && is_product();
    $is_shop     = $wc && is_shop();
    $is_cat_tag  = $wc && ( is_product_category() || is_product_tag() );
    $is_prod_src = is_search() && 'product' === get_query_var( 'post_type' );

    $theme_uri = get_template_directory_uri();

    // 2. کارت محصول (همه صفحات فروشگاهی و نتایج جستجو)
    if ( $is_shop || $is_cat_tag || $is_product || is_search() ) {
        wp_enqueue_style( 'hodima-product-card', $theme_uri . '/assets/css/product-card.css', [], hodima_asset_version( 'assets/css/product-card.css' ) );
    }

    // 3. صفحه اصلی فروشگاه
    if ( $is_shop && ! $is_cat_tag ) {
        wp_enqueue_style( 'hodima-shop-page', $theme_uri . '/assets/css/shop-page.css', [], hodima_asset_version( 'assets/css/shop-page.css' ) );
    }

    // 4. دسته، برچسب، فروشگاه و جستجوی محصول: نوار مرتب‌سازی و صفحه‌بندی مشترک
    // اسکریپت Vanilla JS است و به jQuery وابستگی ندارد.
    if ( $is_cat_tag || $is_shop || $is_prod_src ) {
        wp_enqueue_style( 'hodima-taxonomy-cat', $theme_uri . '/assets/css/taxonomy-product_cat.css', [], hodima_asset_version( 'assets/css/taxonomy-product_cat.css' ) );
        wp_enqueue_script( 'hodima-taxonomy-js', $theme_uri . '/assets/js/taxonomy-product_cat.js', [], hodima_asset_version( 'assets/js/taxonomy-product_cat.js' ), [ 'in_footer' => true ] );
    }

    // 5. صفحه محصول
    if ( $is_product ) {
        wp_enqueue_style( 'hodima-single-product', $theme_uri . '/assets/css/single-product.css', [], hodima_asset_version( 'assets/css/single-product.css' ) );
        wp_enqueue_script( 'hodima-single-product-js', $theme_uri . '/assets/js/single-product.js', [], hodima_asset_version( 'assets/js/single-product.js' ), [ 'in_footer' => true ] );
    }

    // 6. سبد خرید (اسکریپت Vanilla JS)
    if ( $wc && is_cart() ) {
        wp_enqueue_style( 'hodima-cart-page', $theme_uri . '/assets/css/cart-page.css', [], hodima_asset_version( 'assets/css/cart-page.css' ) );
        wp_enqueue_script( 'hodima-cart-js', $theme_uri . '/assets/js/cart-page.js', [], hodima_asset_version( 'assets/js/cart-page.js' ), [ 'in_footer' => true ] );
    }

    // 7. استایل اختصاصی صفحه نوشته‌های وبلاگ (Single Post)
    if ( is_singular( 'post' ) ) {
        wp_enqueue_style( 
            'hodima-single-post', 
            get_template_directory_uri() . '/assets/css/single-post.css', 
            array(), 
            hodima_asset_version( 'assets/css/single-post.css' ) 
        );
    }

    // 8. استایل صفحه ۴۰۴
    // (فایل assets/css/404.css از قبل وجود داشت ولی خالی بود و هرگز ثبت نشده بود)
    if ( is_404() ) {
        wp_enqueue_style(
            'hodima-404',
            hodima_URI . '/assets/css/404.css',
            array( 'hodima-tokens' ),
            hodima_asset_version( 'assets/css/404.css' )
        );
    }

}
add_action( 'wp_enqueue_scripts', 'hodima_enqueue_scripts', 20 ); // عدد 20 برای اولویت بالاتر در صورت لزوم

/* -------------------------------------------------------------
 * بقیه توابع (Placeholder, Custom Product Gallery, ...)
 * نیازی به تغییر ندارند مگر اینکه بخواهید آنها را هم در تابع hodima_enqueue_scripts ادغام کنید.
 * اما برای خوانایی و جداسازی منطق، بهتر است آنها را جدا نگه دارید.
 * ------------------------------------------------------------- */


/**
 * افزودن Placeholder به فیلدهای فرم نظرات ووکامرس
 * بدون دست زدن به بخش ستاره‌های امتیازدهی
 */

// ─── ۱. فیلدهای نام و ایمیل (اینا مشکلی ندارن) ───
add_filter('woocommerce_product_review_comment_form_args', 'custom_review_form_placeholders', 20);
function custom_review_form_placeholders($comment_form) {

    $commenter = wp_get_current_commenter();

    $comment_form['fields']['author'] = '<p class="comment-form-author">'
        . '<input id="author" name="author" type="text" '
        . 'placeholder="نام *" value="' . esc_attr($commenter['comment_author']) . '" '
        . 'size="30" required /></p>';

    $comment_form['fields']['email'] = '<p class="comment-form-email">'
        . '<input id="email" name="email" type="email" '
        . 'placeholder="ایمیل *" value="' . esc_attr($commenter['comment_author_email']) . '" '
        . 'size="30" required /></p>';

    $comment_form['comment_notes_before'] = '<p class="comment-notes-before">'
        . '<span class="email-notes">نشانی ایمیل شما منتشر نخواهد شد. '
        . 'بخش‌های موردنیاز علامت‌گذاری شده‌اند <span class="required">*</span></span></p>';

    return $comment_form;
}

// ─── ۲. اضافه کردن placeholder به textarea بدون حذف ستاره‌ها ───
add_filter('comment_form_field_comment', 'add_placeholder_to_comment_textarea', 20);
function add_placeholder_to_comment_textarea($comment_field) {

    $comment_field = preg_replace(
        '/<label[^>]*for=["\']comment["\'][^>]*>.*?<\/label>/is',
        '',
        $comment_field
    );

    if (strpos($comment_field, 'placeholder=') === false) {
        $comment_field = str_replace(
            '<textarea',
            '<textarea placeholder="دیدگاه شما *"',
            $comment_field
        );
    }

    return $comment_field;
}



/* ============================================================
 * Custom Product Gallery (SP Gallery)
 * ============================================================ */
add_action( 'wp_enqueue_scripts', 'suspended_enqueue_custom_gallery', 20 );
function suspended_enqueue_custom_gallery() {
    if ( ! hodima_wc_active() || ! is_product() ) {
        return;
    }

    // نکته: استایل گالری داخل assets/css/single-product.css قرار دارد،
    // بنابراین فایل مستقل single-product-gallery.css حذف شد (کد مرده بود).
    if ( hodima_asset_exists( 'assets/js/sp-gallery.js' ) ) {
        wp_enqueue_script(
            'sp-gallery-js',
            hodima_URI . '/assets/js/sp-gallery.js',
            array(),
            hodima_asset_version( 'assets/js/sp-gallery.js' ),
            true
        );
    }
}




/* ============================================================
 * منطق منتقل‌شده به افزونه‌ها — بازسازی قالب، مرحله ۲
 * ------------------------------------------------------------
 * این فایل فقط بارگذاری CSS/JS و ظاهر فرم‌ها را دارد. بقیه (که با عوض شدن
 * قالب نباید از بین برود) به افزونه‌ها رفت:
 *   - مرتب‌سازی کاتالوگ (جدیدترین/محبوب‌ترین/ارزان‌ترین/گران‌ترین)
 *       → Hodima Commerce: inc/woocommerce/catalog-sorting.php
 *   - اسکیمای فهرست محصولات فروشگاه و برچسب محصول
 *       → Hodima SEO: schema/collection-lists-schema.php
 *   - ویرایشگر پیشرفته توضیحات دسته محصول و مجوز HTML آن
 *       → Hodima SEO: core/cat-blog/cat-blog.php (ماژول «دسته‌های وبلاگ و محصول»)
 *   - دسته «جدیدترین محصولات» در مسیر راهنما (قبلا نامک ثابت)
 *       → Hodima SEO: schema/breadcrumb-schema.php (تنظیم در «اسکیما ← بردکرامب»)
 *   - «بارگذاری ویدیوی بیشتر» (AJAX بلااستفاده) در نسخه 2.2.3 حذف شد.
 * ============================================================ */

/* ============================================================
 * توکن‌های طراحی در پنل مدیریت
 * ------------------------------------------------------------
 * تا پیش از این هیچ استایل مشترکی در ادمین لود نمی‌شد و هر ماژول
 * رنگ‌ها را جداگانه هاردکد می‌کرد. اولویت ۱ تضمین می‌کند متغیرها
 * قبل از CSS ماژول‌ها تعریف شده باشند.
 * ============================================================ */
add_action( 'admin_enqueue_scripts', 'hodima_enqueue_admin_tokens', 1 );

function hodima_enqueue_admin_tokens() {
    wp_enqueue_style(
        'hodima-tokens',
        hodima_URI . '/assets/css/tokens.css',
        array(),
        hodima_asset_version( 'assets/css/tokens.css' )
    );
}
