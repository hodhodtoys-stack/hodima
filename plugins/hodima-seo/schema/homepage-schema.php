<?php
/**
 * HOOK MASTER SCHEMA SYSTEM PRO - Global Knowledge Graph V2
 * Path: wp-content/themes/hodima/schema/homepage-schema.php
 * Description: تولید گراف دانش یکپارچه برای کل سایت (سازمان، وب‌سایت و صفحه جاری) با پشتیبانی از AI GEO Signals
 */

if (!defined('ABSPATH')) exit;

add_action('wp_head', 'hook_render_master_schema', 5);

function hook_render_master_schema() {
    if (is_admin()) return;

    $global_enabled = get_option('hodima_schema_homepage_enable', '1') === '1';
    if (!$global_enabled) return;

    // توابع کمکی
    $sanitize_multiline = function($raw_text) {
        if (empty($raw_text)) return [];
        $lines = explode("\n", $raw_text);
        $lines = array_map('trim', $lines);
        return array_values(array_filter($lines));
    };

    $normalize_phone = function($tel) {
        $cleaned = preg_replace('/[^0-9+]/', '', $tel);
        if (strpos($cleaned, '0') === 0) {
            return '+98' . substr($cleaned, 1);
        }
        return $cleaned;
    };

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

    // دریافت اطلاعات پایه
    $org_name = get_option('hodima_schema_homepage_org_name') ?: get_bloginfo('name') ?: 'شرکت بازرگانی هدهد';
    
    $custom_logo_id = get_theme_mod('custom_logo');
    $logo_url = $custom_logo_id ? wp_get_attachment_image_url($custom_logo_id, 'full') : get_option('hodima_schema_homepage_logo');
    if (empty($logo_url)) $logo_url = $site_url . 'wp-content/uploads/2025/06/logo2.png';
    
    $image_url = get_option('hodima_schema_homepage_image') ?: $logo_url;

    $alt_names_raw = get_option('hodima_schema_geo_alt_names', "عمده فروشی هدهد\nبازرگانی هدهد\nhodima");
    $alt_names     = $sanitize_multiline($alt_names_raw);

    $knows_home  = $sanitize_multiline(get_option('hodima_schema_homepage_knows_about'));
    $knows_geo   = $sanitize_multiline(get_option('hodima_schema_geo_knows_about', "واردات اکسسوری مو\nپخش عمده کلیپس\nفروش عمده کش مو\nتولید و پخش گلسر\nلوازم خرازی و خرج‌کار"));
    $knows_about = array_unique(array_merge($knows_home, $knows_geo));

    $socials_geo  = $sanitize_multiline(get_option('hodima_schema_geo_socials', "https://instagram.com/hodima\nhttps://t.me/hodimaaccessory\nhttps://wa.me/989124093140"));
    $social_links = array_unique($socials_geo);

    $raw_telephones = $sanitize_multiline(get_option('hodima_schema_geo_telephones', "+989124093140\n02177322684"));
    $telephones     = array_map($normalize_phone, $raw_telephones);
    $primary_phone  = !empty($telephones) ? $telephones[0] : '+989124093140';

    $price_range = get_option('hodima_schema_homepage_price_range') ?: 'IRR';
    
    $address_schema = [
        '@type'           => 'PostalAddress',
        'addressCountry'  => 'IR',
        'streetAddress'   => get_option('hodima_schema_homepage_street_address', 'تهرانپارس، خیابان احسان، پلاک ۸۴'),
        'addressLocality' => get_option('hodima_schema_homepage_address_locality', 'تهران'),
        // 🛠️ باگ رفع‌شده: قبلاً این خط هم از get_option('..._address_locality')
        // می‌خواند، یعنی addressRegion همیشه دقیقاً همان مقدار addressLocality
        // بود. حالا از گزینه‌ی جداگانه‌ی «استان» می‌خواند.
        'addressRegion'   => get_option('hodima_schema_homepage_address_region', 'تهران'),
        'postalCode'      => get_option('hodima_schema_homepage_postal_code', '1657883361')
    ];

    $org_type = get_option('hodima_schema_homepage_org_type', 'WholesaleStore');
    $org_desc = get_option('hodima_schema_geo_description', get_bloginfo('description')) ?: 'پخش عمده اکسسوری مو';

    // 1. ساخت هسته سازمان (Organization)
    $organization_schema = [
        '@type'         => $org_type,
        '@id'           => $site_url . '#organization',
        'name'          => $org_name,
        'url'           => $site_url,
        'logo'          => [
            '@type' => 'ImageObject',
            '@id'   => $site_url . '#logo',
            'url'   => $logo_url
        ],
        'image'         => ['@id' => $site_url . '#logo'],
        'description'   => $org_desc,
        'priceRange'    => $price_range,
        'telephone'     => $primary_phone,
        'address'       => $address_schema
    ];

    if (!empty($alt_names)) $organization_schema['alternateName'] = $alt_names;
    if (!empty($knows_about)) $organization_schema['knowsAbout'] = array_values($knows_about);
    if (!empty($social_links)) $organization_schema['sameAs'] = array_values($social_links);

    // 🛠️ باگ رفع‌شده: پنل ادمین (view-homepage.php) این چهار فیلد را ذخیره
    // می‌کرد («شعار تجاری»، «نام کاتالوگ خدمات»، «کشورهای تحت پوشش
    // areaServed» و «زبان‌های قابل پشتیبانی knowsLanguage») ولی هیچ‌کدام
    // در خروجی JSON-LD خوانده نمی‌شدند — یعنی هرچه ادمین در این فیلدها
    // وارد می‌کرد، عملاً بی‌اثر بود. حالا واقعاً در گراف سازمان درج می‌شوند.
    $slogan = get_option('hodima_schema_homepage_slogan');
    if (!empty($slogan)) $organization_schema['slogan'] = sanitize_text_field($slogan);

    $catalog_name = get_option('hodima_schema_homepage_catalog_name');
    if (!empty($catalog_name)) {
        $organization_schema['hasOfferCatalog'] = [
            '@type' => 'OfferCatalog',
            'name'  => sanitize_text_field($catalog_name)
        ];
    }

    $area_countries = $sanitize_multiline(get_option('hodima_schema_ai_countries'));
    if (!empty($area_countries)) {
        $organization_schema['areaServed'] = array_map(function($country) {
            return ['@type' => 'Country', 'name' => $country];
        }, $area_countries);
    }

    $lang_raw = get_option('hodima_schema_ai_languages');
    if (!empty($lang_raw)) {
        $languages = array_values(array_filter(array_map('trim', explode(',', $lang_raw))));
        if (!empty($languages)) $organization_schema['knowsLanguage'] = $languages;
    }

    // نکته: فیلد «نوع مخاطب تجاری» (hodima_schema_ai_audience) عمداً به
    // نود Organization اضافه نمی‌شود. طبق کامنت «FIX ERROR» که پیش‌تر در
    // همین فایل بود، خاصیت audience روی Organization توسط اعتبارسنج گوگل
    // به‌عنوان فیلد نامعتبر/غیرمنتظره گزارش می‌شد و به‌صورت دستی حذف شده
    // بود؛ برای جلوگیری از بازگشت همان خطا، این فیلد اینجا استفاده نمی‌شود.
    // (audience واقعی و معتبر همان چیزی است که category-schema-pro.php از
    // طریق hodima_cat_audience روی CollectionPage هر دسته تولید می‌کند.)

    if (count($telephones) > 1) {
        $contact_points = [];
        foreach ($telephones as $tel) {
            $contact_points[] = [
                '@type'             => 'ContactPoint',
                'telephone'         => $tel,
                'contactType'       => 'customer service',
                'areaServed'        => 'IR',
                'availableLanguage' => ['Persian']
            ];
        }
        $organization_schema['contactPoint'] = $contact_points;
    }

    $organization_schema['openingHoursSpecification'] = [
        [
            '@type'     => 'OpeningHoursSpecification',
            'dayOfWeek' => ['Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday'],
            'opens'     => get_option('hodima_schema_geo_weekday_open', '09:00'),
            'closes'    => get_option('hodima_schema_geo_weekday_close', '17:30')
        ],
        [
            '@type'     => 'OpeningHoursSpecification',
            'dayOfWeek' => ['Thursday'],
            'opens'     => get_option('hodima_schema_geo_thursday_open', '09:00'),
            'closes'    => get_option('hodima_schema_geo_thursday_close', '13:00')
        ]
    ];

    // =========================================================================
    // اتصال جادویی به ماژول AI GEO
    // =========================================================================
    $organization_schema = apply_filters('wpgi_ai_geo_schema_data', $organization_schema);

    // FIX ERROR: حذف اتوماتیک ویژگی audience در صورتی که توسط فیلتر بالا به استور تزریق شده باشد
    if (isset($organization_schema['audience'])) {
        unset($organization_schema['audience']);
    }

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
        $page_desc = has_excerpt() ? get_the_excerpt() : wp_trim_words(get_post_field('post_content', get_queried_object_id()), 20);
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

    $final_graph = [
        '@context' => 'https://schema.org',
        '@graph'   => $graph_nodes
    ];

    echo "\n<!-- HOOK MASTER SCHEMA GRAPH (Validated) -->\n";
    echo '<script type="application/ld+json" id="hook-master-graph">' . wp_json_encode($final_graph, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . "</script>\n\n";
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
