<?php
/**
 * HOOK MASTER SCHEMA SYSTEM PRO - Global Knowledge Graph V2
 * Path: wp-content/plugins/hodima-seo/schema/homepage-schema.php
 * Description: تولید گراف دانش یکپارچه برای کل سایت (سازمان، وب‌سایت و صفحه جاری) با پشتیبانی از AI GEO Signals
 */

if (!defined('ABSPATH')) exit;

// اولویت ۰: نودهای هویتی (Organization، WebSite، صفحه) اولین نودهای گراف
// واحد باشند و پرچم hodima_schema_webpage_emitted() پیش از همه سازنده‌ها
// (قبلا ۵ بود؛ هیچ سازنده‌ای پیش از ۵ به این پرچم نیاز نداشت) ست شود.
add_action('wp_head', 'hook_render_master_schema', 0);

function hook_render_master_schema() {
    if (is_admin()) return;

    $global_enabled = get_option('hodima_schema_homepage_enable', '1') === '1';
    if (!$global_enabled) return;

    $site_url = trailingslashit(home_url());

    /* ── آدرس صفحه ───────────────────────────────────────────────────
     * از موتور مشترک inc/helpers.php می‌آید — همان آدرسی که
     * core/seobox/front-output.php در تگ canonical چاپ می‌کند.
     *
     * نسخه قبلی منطق خودش را داشت و در دو نقطه با کنونیکال فرق می‌کرد:
     *   ۱. trailingslashit() را دستی اعمال می‌کرد. اگر ساختار پیوند
     *      یکتا بدون اسلش پایانی باشد، @id یک اسلش اضافه می‌گرفت و با
     *      canonical یکی نمی‌شد.
     *   ۲. صفحه‌بندی را نادیده می‌گرفت، پس @id صفحه دوم آرشیو با
     *      @id صفحه اول یکی بود.
     *
     * برای صفحات ۴۰۴ و جستجو هم قبلا آدرس خانه به عنوان @id نوشته
     * می‌شد — یعنی صفحه ۴۰۴ و صفحه اصلی یک شناسه مشترک داشتند و گوگل
     * آن‌ها را یک موجودیت می‌دید. حالا روی این صفحات نود WebPage اصلا
     * تولید نمی‌شود.
     * ---------------------------------------------------------------- */
    $has_identity = function_exists('hodima_page_has_schema_identity')
        ? hodima_page_has_schema_identity()
        : ( ! is_404() && ! is_search() );

    $canonical = function_exists('hodima_get_canonical_url') ? hodima_get_canonical_url() : '';

    $current_url = $canonical !== '' ? $canonical : $site_url;

    // نود سازمان از سازنده مشترک (schema-helpers.php) — همان نودی که برگه‌های
    // درباره‌ما/تماس هم چاپ می‌کنند. قبلا این فایل و corporate-schema.php هر کدام
    // نسخه خودشان را می‌ساختند و در برگه‌های درباره‌ما/تماس سازمان ساعت کاری،
    // knowsAbout و شعار نداشت.
    $org_name            = hodima_seo_schema_org_name();
    $organization_schema = hodima_seo_schema_organization_node();

    // 2. ساخت هسته وب‌سایت (WebSite)
    $website_schema = [
        '@type'     => 'WebSite',
        '@id'       => $site_url . '#website',
        'url'       => $site_url,
        'name'      => $org_name,
        'publisher' => [ '@id' => $site_url . '#organization' ],
        'inLanguage'=> 'fa-IR',
        'potentialAction' => [
            '@type' => 'SearchAction',
            'target' => [
                '@type' => 'EntryPoint',
                'urlTemplate' => $site_url . '?s={search_term_string}'
            ],
            'query-input' => 'required name=search_term_string'
        ]
    ];

    // 3. ساخت هسته صفحه فعلی (WebPage) به صورت کاملا داینامیک
    $page_type = 'WebPage';
    $about_slug = get_option('hodima_corp_about_slug', 'about-us');
    $contact_slug = get_option('hodima_corp_contact_slug', 'contact-us');

    // تمام صفحات فهرستی (دسته‌بندی، برچسب، فروشگاه، آرشیو) CollectionPage هستند.
    // نسخه قبلی فقط صفحه اصلی و وبلاگ را CollectionPage می‌کرد؛ دسته‌بندی‌ها
    // WebPage معمولی می‌گرفتند و category-schema-pro.php یک CollectionPage
    // *دوم* با شناسه #collection برای همان آدرس می‌ساخت.
    if (is_front_page() || is_home() || is_archive() || is_page_template('template-page-videos.php')) {
        $page_type = 'CollectionPage';
    } elseif (is_page($about_slug)) {
        $page_type = 'AboutPage';
    } elseif (is_page($contact_slug)) {
        $page_type = 'ContactPage';
    } elseif (is_singular('product')) {
        $page_type = 'ItemPage';
    }

    $page_title = hodima_schema_page_name();

    // این صفحه توسط corporate-schema.php هم پردازش می‌شود (اگر برگه درباره‌ما/تماس
    // باشد)؛ آن فایل خودش یک نود Organization (#organization) و یک نود صفحه
    // (#webpage) کامل‌تر با همین دقیقاً همین @id می‌سازد. اگر اینجا هم همان دو نود
    // ساخته شوند، دو گراف جداگانه با @id یکسان ولی محتوای متفاوت چاپ می‌شود که
    // برای گوگل به عنوان تداخل/تکرار (Duplicate node) دیده می‌شود.
    $is_corporate_page = in_array( $page_type, [ 'AboutPage', 'ContactPage' ], true );

    // دریافت توضیحات اصولی و بدون باگ برای انواع صفحات
    $page_desc = '';
    if (is_front_page() || is_home()) {
        $page_desc = get_bloginfo('description');
    } elseif (is_singular()) {
        // باگ قبلی: اول کوتاه می‌شد، بعد شورت‌کد حذف می‌شد؛ شورت‌کدی که وسط
        // برش افتاده بود («[video src=…») در توضیح می‌ماند.
        $page_desc = has_excerpt() ? get_the_excerpt() : wp_trim_words(strip_shortcodes((string) get_post_field('post_content', get_queried_object_id())), 20);
    } elseif (is_archive() || is_tax()) {
        $page_desc = get_the_archive_description();
    }
    $page_desc = wp_strip_all_tags(strip_shortcodes($page_desc));

    $webpage_schema = [
        '@type'       => $page_type,
        '@id'         => $current_url . '#webpage',
        'url'         => $current_url,
        'name'        => $page_title,
        'isPartOf'    => [ '@id' => $site_url . '#website' ],
        'about'       => [ '@id' => $site_url . '#organization' ],
        'inLanguage'  => 'fa-IR',
    ];

    if (!empty($page_desc)) {
        $webpage_schema['description'] = $page_desc;
    }

    /*
     * نقطه غنی‌سازی نود صفحه.
     *
     * این فایل *تنها سازنده* نود «#webpage» است. ماژول‌های دیگر به جای
     * ساختن یک نود صفحه دوم برای همان آدرس، از طریق این فیلتر ویژگی
     * اضافه می‌کنند:
     *
     *   category-schema-pro.php      → نام/توضیح/تصویر دسته، OfferCatalog، ویدیو
     *   inc/enqueue.php              → فهرست محصولات برگه فروشگاه
     *   product-schema-pro.php       → mainEntity → #product
     *   breadcrumb-schema.php        → breadcrumb → #breadcrumb
     *   core/topiccluster            → isPartOf والدها، hasPart فرزندان
     *
     * نتیجه: یک موجودیت صفحه برای هر آدرس، با همه ویژگی‌ها در یک جا.
     */
    if ( $has_identity && ! $is_corporate_page ) {
        $webpage_schema = (array) apply_filters( 'hodima_schema_webpage_node', $webpage_schema, $current_url );
        $GLOBALS['hodima_schema_webpage_emitted'] = true;
    }

    // تجمیع گراف نهایی: در برگه‌های درباره‌ما/تماس، فقط نود WebSite چاپ می‌شود
    // (چون هیچ فایل دیگری آن را نمی‌سازد)؛ نودهای Organization و صفحه را
    // corporate-schema.php با جزئیات کامل‌تر (foundingDate، contactPoint، sameAs)
    // خودش می‌سازد تا از تداخل @id جلوگیری شود.
    // صفحات بدون هویت کنونیکال (۴۰۴ و جستجو) نباید نود WebPage بگیرند.
    // در غیر این صورت @id آن‌ها با @id صفحه اصلی یکی می‌شد.
    if ( $is_corporate_page ) {
        $graph_nodes = [ $website_schema ];
    } elseif ( $has_identity ) {
        $graph_nodes = [ $organization_schema, $website_schema, $webpage_schema ];
    } else {
        $graph_nodes = [ $organization_schema, $website_schema ];
    }

    // گراف واحد (hodima-core): این سه نود اولین نودهای گراف صفحه‌اند و
    // بقیه سازنده‌ها با @id به آن‌ها ارجاع می‌دهند یا ادغام می‌شوند.
    hodima_schema_add( [ '@graph' => $graph_nodes ], 'hodima-seo: homepage-schema' );
}

/**
 * نام موجودیت صفحه جاری.
 *
 * باگ نسخه قبلی: get_the_title() بدون آرگومان از $post سراسری می‌خواند و
 * روی صفحات آرشیو، وردپرس آن را روی *اولین پست لوپ* ست کرده است. یعنی نود
 * صفحه دسته‌بندی «اکسسوری» نام یکی از محصولاتش را می‌گرفت.
 */
function hodima_schema_page_name(): string {

    if ( is_front_page() ) {
        return (string) get_bloginfo( 'name' );
    }

    if ( is_home() ) {
        $blog_id = (int) get_option( 'page_for_posts' );
        return $blog_id ? (string) get_the_title( $blog_id ) : (string) get_bloginfo( 'name' );
    }

    if ( function_exists( 'is_shop' ) && is_shop() ) {
        $shop_id = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'shop' ) : 0;
        return $shop_id > 0 ? (string) get_the_title( $shop_id ) : (string) get_bloginfo( 'name' );
    }

    if ( is_category() || is_tag() || is_tax() ) {
        return (string) single_term_title( '', false );
    }

    if ( is_post_type_archive() ) {
        return (string) post_type_archive_title( '', false );
    }

    if ( is_author() ) {
        return (string) get_the_author_meta( 'display_name', (int) get_queried_object_id() );
    }

    if ( is_singular() ) {
        return (string) get_the_title( (int) get_queried_object_id() );
    }

    return wp_strip_all_tags( (string) get_the_archive_title() );
}

/** آیا نود صفحه توسط این فایل چاپ شده است؟ (برای فالبک ماژول‌های دیگر) */
function hodima_schema_webpage_emitted(): bool {
    return ! empty( $GLOBALS['hodima_schema_webpage_emitted'] );
}
