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




// ۱. معرفی مقادیر مرتب‌سازی به ووکامرس تا آنها را معتبر بداند
add_filter( 'woocommerce_default_catalog_orderby_options', 'hodima_add_custom_sorting_options' );
add_filter( 'woocommerce_catalog_orderby', 'hodima_add_custom_sorting_options' );
function hodima_add_custom_sorting_options( $options ) {
    $options['date']       = 'جدیدترین';
    $options['popularity'] = 'محبوب‌ترین';
    $options['price']      = 'ارزان‌ترین';
    $options['price-desc'] = 'گران‌ترین';
    return $options;
}

// ۲. اعمال منطق مرتب‌سازی روی کوئری محصولات
add_filter( 'woocommerce_get_catalog_ordering_args', 'hodima_custom_catalog_ordering_args', 999 );
function hodima_custom_catalog_ordering_args( $args ) {
    if ( ! isset( $_GET['orderby'] ) ) {
        return $args;
    }

    $orderby_value = sanitize_text_field( $_GET['orderby'] );

    switch ( $orderby_value ) {
        case 'date':
            $args['orderby']  = 'date ID';
            $args['order']    = 'DESC';
            break;
        case 'popularity':
            $args['orderby']  = 'meta_value_num';
            $args['order']    = 'DESC';
            $args['meta_key'] = 'total_sales';
            break;
        case 'price':
            $args['orderby']  = 'meta_value_num';
            $args['order']    = 'ASC';
            $args['meta_key'] = '_price';
            break;
        case 'price-desc':
            $args['orderby']  = 'meta_value_num';
            $args['order']    = 'DESC';
            $args['meta_key'] = '_price';
            break;
    }

    return $args;
}












/* ============================================================
 * همه ویدئو های مدیا در یک صفحه (پشتیبانی از اسکرول بی‌نهایت)
 * ============================================================ */

add_action( 'wp_ajax_hodima_load_more_videos', 'hodima_load_more_videos' );
add_action( 'wp_ajax_nopriv_hodima_load_more_videos', 'hodima_load_more_videos' );

function hodima_load_more_videos() {
    // 1. بررسی امنیتی Nonce (دلیل اصلی مسدود شدن در هاست اصلی)
    if ( ! isset( $_POST['security'] ) || ! wp_verify_nonce( $_POST['security'], 'hodima_load_videos_nonce' ) ) {
        wp_send_json_error( 'درخواست نامعتبر است (خطای امنیتی).' );
        wp_die();
    }

    $page = isset( $_POST['page'] ) ? max( 1, absint( $_POST['page'] ) ) : 1;
    $posts_per_page = 12;

    $video_query = new WP_Query( array(
        'post_type'      => 'video',
        'posts_per_page' => $posts_per_page,
        'paged'          => $page,
        'post_status'    => 'publish' // اطمینان از دریافت فقط پست‌های منتشر شده
    ) );

    ob_start();

    if ( $video_query->have_posts() ) {
        while ( $video_query->have_posts() ) {
            $video_query->the_post();

            $title = get_the_title();
            $url   = get_permalink();
            ?>
            <a href="<?php echo esc_url( $url ); ?>" class="videos-page-card">
                <?php if ( has_post_thumbnail() ) : ?>
                    <?php the_post_thumbnail( 'medium', array(
                        'class' => 'videos-page-thumbnail',
                        'alt'   => $title,
                    ) ); ?>
                <?php else : ?>
                    <div class="videos-page-thumbnail videos-page-thumbnail--empty">
                        <span>بدون تصویر</span>
                    </div>
                <?php endif; ?>

                <div class="videos-page-content">
                    <h2 class="videos-page-title" title="<?php echo esc_attr( $title ); ?>">
                        <?php
                        if ( mb_strlen( $title, 'UTF-8' ) > 22 ) {
                            echo esc_html( mb_substr( $title, 0, 22, 'UTF-8' ) . '...' );
                        } else {
                            echo esc_html( $title );
                        }
                        ?>
                    </h2>
                </div>
            </a>
            <?php
        }
    }

    wp_reset_postdata();

    wp_send_json_success( array(
        'html'      => ob_get_clean(),
        'has_more'  => $page < (int) $video_query->max_num_pages,
        'next_page' => $page + 1,
        'max_pages' => (int) $video_query->max_num_pages,
    ) );
    
    wp_die();
}















/* ============================================================
 * اسکیمای برگه فروشگاه و برچسب محصول
 * ------------------------------------------------------------
 * قبلا دو تابع جداگانه (hodima_shop_itemlist_schema و
 * hodima_shop_collection_itemlist_schema) هر دو روی wp_footer با اولویت ۲۰
 * ثبت شده بودند و لیست یکسانی از محصولات را دو بار چاپ می‌کردند. همزمان
 * schema/category-schema-pro.php هم روی صفحات دسته‌بندی یک CollectionPage
 * کامل‌تر می‌ساخت؛ یعنی روی هر صفحه دسته‌بندی سه نود همپوشان چاپ می‌شد.
 *
 * حالا:
 *   - تابع تکراری hodima_shop_itemlist_schema حذف شد.
 *   - این تابع فقط برگه فروشگاه و برچسب محصول را پوشش می‌دهد؛ دسته‌بندی‌ها
 *     کامل در اختیار category-schema-pro.php است.
 *   - نود خروجی به جای شناور بودن، با @id و isPartOf/about به گراف اصلی
 *     (homepage-schema.php) وصل می‌شود.
 * ============================================================ */
/*
 * از نسخه جدید، نود صفحه دوم («#collection») ساخته نمی‌شود.
 * schema/homepage-schema.php تنها سازنده نود «#webpage» است و آن را روی
 * فروشگاه و برچسب محصول از قبل CollectionPage تعریف می‌کند؛ اینجا فقط
 * فهرست محصولات به عنوان mainEntity آن اضافه می‌شود. اگر گراف اصلی در
 * پنل خاموش باشد، همان نود کامل در فوتر چاپ می‌شود.
 */
add_filter( 'hodima_schema_webpage_node', 'hodima_shop_enrich_webpage_node', 10, 2 );
add_action( 'wp_footer', 'hodima_shop_collection_itemlist_schema', 20 );

/** فهرست محصولات صفحه فروشگاه/برچسب، یا null. */
function hodima_shop_itemlist_node(): ?array {

    static $memo = false;
    if ( false !== $memo ) {
        return $memo;
    }
    $memo = null;

    if ( ! function_exists( 'is_shop' ) || ! function_exists( 'hodima_get_archive_itemlist_elements' ) ) {
        return $memo;
    }

    // دسته‌بندی‌ها را category-schema-pro.php پوشش می‌دهد
    if ( is_product_category() || is_category() ) {
        return $memo;
    }

    if ( ! is_shop() && ! is_product_tag() ) {
        return $memo;
    }

    $items = hodima_get_archive_itemlist_elements();

    if ( empty( $items ) ) {
        return $memo;
    }

    return $memo = [
        '@type'           => 'ItemList',
        'numberOfItems'   => count( $items ),
        'itemListElement' => $items,
    ];
}

function hodima_shop_enrich_webpage_node( array $node, string $page_url ): array {

    $list = hodima_shop_itemlist_node();

    if ( null !== $list ) {
        $node['mainEntity'] = $list;
    }

    return $node;
}

/** فالبک: فقط وقتی گراف اصلی نود صفحه را چاپ نکرده باشد. */
function hodima_shop_collection_itemlist_schema() {

    if ( function_exists( 'hodima_schema_webpage_emitted' ) && hodima_schema_webpage_emitted() ) {
        return;
    }

    $list = hodima_shop_itemlist_node();

    if ( null === $list ) {
        return;
    }

    $page_url = function_exists( 'hodima_get_canonical_url' ) ? hodima_get_canonical_url() : '';

    if ( '' === $page_url ) {
        return;
    }

    $site_url = trailingslashit( home_url() );

    $schema = array(
        '@context' => 'https://schema.org',
        '@graph'   => array( array(
            '@type'      => 'CollectionPage',
            '@id'        => $page_url . '#webpage',
            'url'        => $page_url,
            'name'       => function_exists( 'hodima_schema_page_name' ) ? hodima_schema_page_name() : '',
            'inLanguage' => 'fa-IR',
            'isPartOf'   => array( '@id' => $site_url . '#website' ),
            'about'      => array( '@id' => $site_url . '#organization' ),
            'mainEntity' => $list,
        ) ),
    );

    echo "\n<!-- Hodima Shop CollectionPage Schema -->\n";
    echo '<script type="application/ld+json" id="hodima-shop-collection">' . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP ) . "</script>\n";
}




/* ============================================================
 * فعال‌سازی ویرایشگر پیشرفته (TinyMCE) برای توضیحات دسته‌بندی محصولات ووکامرس
 * ============================================================ */

// ۱. حذف فیلترهای محدودکننده وردپرس برای اجازه دادن به تگ‌های HTML در توضیحات
/* ============================================================
 * اجازه HTML در توضیحات دسته‌بندی — فقط برای کاربران مجاز
 * ------------------------------------------------------------
 * نسخه قبلی این دو فیلتر را بدون هیچ شرطی و به صورت سراسری حذف می‌کرد.
 * نتیجه: هر نقشی که بتواند دسته‌بندی را ویرایش کند (مثلا shop_manager یا
 * editor) می‌توانست <script> داخل توضیحات ترم تزریق کند و آن اسکریپت روی
 * صفحه دسته‌بندی برای همه بازدیدکنندگان اجرا می‌شد.
 *
 * حالا فیلتر ورودی فقط برای کاربری حذف می‌شود که قابلیت unfiltered_html
 * را دارد (به صورت پیش‌فرض فقط administrator در نصب تک‌سایتی).
 * فیلتر خروجی (term_description) همچنان حذف می‌شود تا HTML ذخیره‌شده
 * توسط ادمین در فرانت‌اند رندر شود.
 * ============================================================ */
add_action( 'init', 'hodima_allow_term_description_html' );

function hodima_allow_term_description_html() {

    // خروجی: HTML ذخیره‌شده در فرانت‌اند رندر شود
    remove_filter( 'term_description', 'wp_kses_data' );
    remove_filter( 'pre_term_description', 'wp_filter_kses' );

    // ورودی: اگر کاربر جاری مجاز نیست، فیلتر امنیتی را برگردان
    if ( ! current_user_can( 'unfiltered_html' ) ) {
        add_filter( 'pre_term_description', 'wp_filter_kses' );
    }
}

// ۲. اضافه کردن ویرایشگر به فرم "افزودن" دسته‌بندی جدید
add_action( 'product_cat_add_form_fields', 'custom_product_cat_add_description_editor', 10, 1 );
function custom_product_cat_add_description_editor( $taxonomy ) {
    $settings = array(
        'textarea_name' => 'description', // اتصال دقیق به دیتابیس وردپرس
        'textarea_rows' => 10,
        'teeny'         => false,
        'media_buttons' => true // فعال‌سازی دکمه افزودن پرونده چندرسانه‌ای
    );
    ?>
    <div class="form-field form-required term-description-wrap-custom">
        <label for="description">توضیحات حرفه‌ای دسته‌بندی</label>
        <?php wp_editor( '', 'category_description_add', $settings ); ?>
        <p class="description">محتوای سئو شده و جذاب برای محصولات این دسته را اینجا وارد کنید. (امکان استفاده از تصاویر و تیترها)</p>
    </div>
    <?php
}

// ۳. اضافه کردن ویرایشگر به فرم "ویرایش" دسته‌بندی‌های موجود
add_action( 'product_cat_edit_form_fields', 'custom_product_cat_edit_description_editor', 10, 2 );
function custom_product_cat_edit_description_editor( $term, $taxonomy ) {
    $settings = array(
        'textarea_name' => 'description',
        'textarea_rows' => 15,
        'teeny'         => false,
        'media_buttons' => true
    );
    ?>
    <tr class="form-field term-description-wrap-custom">
        <th scope="row" valign="top"><label for="description">توضیحات حرفه‌ای دسته‌بندی</label></th>
        <td>
            <?php wp_editor( htmlspecialchars_decode( $term->description ), 'category_description_edit', $settings ); ?>
            <p class="description">محتوای غنی و سئو شده برای این دسته‌بندی را وارد کنید.</p>
        </td>
    </tr>
    <?php
}

// ۴. مخفی کردن باکس متنی ساده (پیش‌فرض) وردپرس با CSS
add_action( 'admin_head', 'hide_default_product_cat_description_css' );
function hide_default_product_cat_description_css() {
    global $current_screen;
    
    // اطمینان از اینکه دقیقاً در صفحات مدیریت دسته‌بندی محصولات هستیم
    if ( isset( $current_screen->id ) && $current_screen->id === 'edit-product_cat' ) {
        echo '<style>
            /* مخفی کردن فیلد دیفالت وردپرس */
            .term-description-wrap { display: none !important; }
            /* نمایش مطمئن فیلد سفارشی ما */
            .term-description-wrap-custom { display: block !important; }
            tr.term-description-wrap-custom { display: table-row !important; }
        </style>';
    }
}









/**
 * نادیده گرفتن دسته "جدیدترین محصولات" در نوار پیمایش ووکامرس
 * قالب اختصاصی hodima
 */
add_filter( 'woocommerce_breadcrumb_main_term', 'hodima_exclude_latest_products_breadcrumb', 10, 2 );

function hodima_exclude_latest_products_breadcrumb( $main_term, $terms ) {
    
    // نامک (Slug) دسته‌ای که نباید در بریدکرامب (نوار پیمایش) نمایش داده شود
    $excluded_slugs = array( 'latest-products' ); 

    // اگر دسته‌ای که ووکامرس انتخاب کرده، جزء دسته "جدیدترین‌ها" بود:
    if ( $main_term && in_array( $main_term->slug, $excluded_slugs ) ) {
        
        // در بین سایر دسته‌بندی‌های این محصول بگرد
        foreach ( $terms as $term ) {
            // اولین دسته‌ای که "جدیدترین‌ها" نیست (مثلاً پلاسکو) را پیدا کن و نمایش بده
            if ( ! in_array( $term->slug, $excluded_slugs ) ) {
                return $term; 
            }
        }
    }
    
    return $main_term;
}

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
