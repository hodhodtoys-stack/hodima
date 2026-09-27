<?php
/**
 * Category Schema Pro — B2B CollectionPage Schema
 * Path: wp-content/plugins/hodima-seo/schema/category-schema-pro.php
 *
 * تولید اسکیمای CollectionPage/OfferCatalog برای صفحات آرشیو دسته‌بندی محصولات
 * (product_cat) و دسته‌بندی مقالات (category)، مطابق با تنظیمات پنل ادمین
 * (admin/views/view-category.php: hodima_cat_* options).
 *
 * تاریخچه: پیش از این، این فایل به اشتباه حاوی منطق اسکیمای «درباره ما/تماس با ما»
 * بود (اکنون در corporate-schema.php مستقل شده). این نسخه، طبق مجوز صریح شما،
 * منطق واقعی CollectionPage دسته‌بندی را — که پنل ادمین از قبل برایش طراحی شده
 * بود ولی هرگز پیاده‌سازی نشده بود — پیاده‌سازی می‌کند.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/*
 * از نسخه جدید، این فایل نود صفحه *دوم* نمی‌سازد.
 *
 * قبلا روی هر دسته‌بندی دو موجودیت صفحه وجود داشت:
 *   homepage-schema.php     → WebPage         «/اکسسوری/#webpage»
 *   category-schema-pro.php → CollectionPage  «/اکسسوری/#collection»
 *
 * حالا فیلدهای این فایل (نام و توضیح از قالب پنل، تصویر، مخاطب، کاتالوگ،
 * ویدیو) مستقیما به همان نود «#webpage» اضافه می‌شوند. اگر ماژول گراف
 * اصلی در پنل خاموش باشد، این فایل همان نود را خودش کامل چاپ می‌کند.
 */
add_filter( 'hodima_schema_webpage_node', 'hodima_category_enrich_webpage_node', 10, 2 );
add_action( 'wp_head', 'hook_render_category_schema', 10 );

function hodima_category_enrich_webpage_node( array $node, string $page_url ): array {

    $fields = hodima_category_schema_fields();

    if ( null === $fields ) {
        return $node;
    }

    return array_merge( $node, $fields );
}

/** فالبک: فقط وقتی گراف اصلی نود صفحه را چاپ نکرده باشد. */
function hook_render_category_schema() {

    if ( function_exists( 'hodima_schema_webpage_emitted' ) && hodima_schema_webpage_emitted() ) {
        return;
    }

    $fields = hodima_category_schema_fields();

    if ( null === $fields ) {
        return;
    }

    $site_url  = trailingslashit( home_url() );
    $page_url  = $fields['url'];

    $node = array_merge( [
        '@id'        => $page_url . '#webpage',
        'isPartOf'   => [ '@id' => $site_url . '#website' ],
        'about'      => [ '@id' => $site_url . '#organization' ],
        'inLanguage' => 'fa-IR',
    ], $fields );

    hodima_schema_add( $node, 'hodima-seo: category-schema-pro (fallback)' );
}

/**
 * فیلدهای اسکیمای دسته‌بندی جاری، یا null اگر این صفحه دسته‌بندی نیست.
 * نتیجه در همان درخواست کش می‌شود چون هم فیلتر و هم فالبک صدایش می‌زنند.
 */
function hodima_category_schema_fields(): ?array {

    static $memo = false;

    if ( false !== $memo ) {
        return $memo;
    }

    $memo = null;

    if ( get_option( 'hodima_cat_status', 'on' ) !== 'on' ) {
        return $memo;
    }

    // فقط در آرشیو دسته‌بندی محصولات (WooCommerce) یا دسته‌بندی مقالات
    // (دسته‌بندی مقالات طبق گزینه‌ی «hodima_cat_include_post_category» در پنل ادمین قابل کنترل است)
    $is_product_cat = is_tax( 'product_cat' );
    $is_post_cat    = ( get_option( 'hodima_cat_include_post_category', 'on' ) === 'on' ) && is_category();

    if ( ! $is_product_cat && ! $is_post_cat ) {
        return $memo;
    }

    $term = get_queried_object();
    if ( ! $term || is_wp_error( $term ) || empty( $term->term_id ) ) {
        return $memo;
    }

    // آدرس از موتور مشترک تا @id دقیقا با تگ canonical یکی باشد
    // (شامل صفحه‌بندی؛ نسخه قبلی صفحه دوم را با شناسه صفحه اول می‌نوشت).
    $term_link = function_exists( 'hodima_get_canonical_url' ) ? hodima_get_canonical_url() : '';
    if ( $term_link === '' ) {
        $term_link = get_term_link( $term );
    }
    if ( is_wp_error( $term_link ) ) {
        return $memo;
    }

    $site_url  = trailingslashit( home_url() );
    $site_name = get_bloginfo( 'name' );

    // --- خواندن تنظیمات از پنل ادمین (view-category.php) ---
    // تصویر پیش‌فرض دیگر گزینه‌ی جداگانه‌ای در این ماژول نیست؛ طبق درخواست شما،
    // تنها منبع لوگو/تصویر مرکزی همان تنظیمات صفحه اصلی (Homepage) است.
    $default_img  = get_option( 'hodima_schema_homepage_logo' ) ?: get_site_url( null, '/wp-content/uploads/2025/06/logo2.png' );
    $name_tpl     = get_option( 'hodima_cat_name_template', 'پخش عمده [category]' );
    $desc_tpl     = get_option( 'hodima_cat_desc_template', 'مرجع تخصصی واردات و پخش عمده [category] با رقابتی‌ترین قیمت بازار در [site_name].' );
    $catalog_tpl  = get_option( 'hodima_cat_catalog_template', 'کاتالوگ محصولات [category]' );
    $audience_txt = get_option( 'hodima_cat_audience', 'خریداران عمده، همکاران و B2B' );
    $video_title_tpl = get_option( 'hodima_cat_video_title', 'معرفی دسته‌بندی: [category]' );
    $video_desc_tpl  = get_option( 'hodima_cat_video_desc', 'ویدیوی معرفی و راهنمای خرید عمده [category]' );

    // --- جایگزینی شورت‌کدها ---
    $category_name = wp_strip_all_tags( $term->name );
    $replace_vars   = [ '[category]' => $category_name, '[site_name]' => $site_name ];

    $final_name    = strtr( $name_tpl, $replace_vars );
    $final_desc    = wp_strip_all_tags( strtr( $desc_tpl, $replace_vars ) );
    $final_catalog = strtr( $catalog_tpl, $replace_vars );

    // --- تصویر دسته‌بندی (بدون کوئری اضافه؛ فقط term meta) ---
    $thumb_id = get_term_meta( $term->term_id, 'thumbnail_id', true ) ?: get_term_meta( $term->term_id, 'product_cat_thumbnail_id', true );
    $image_url = $thumb_id ? wp_get_attachment_url( (int) $thumb_id ) : $default_img;
    if ( empty( $image_url ) ) {
        $image_url = $default_img;
    }

    // --- تعداد محصولات/نوشته‌های این دسته: از شمارگر ترم (بدون کوئری اضافه به دیتابیس) ---
    $item_count = isset( $term->count ) ? (int) $term->count : 0;

    $collection_page = [
        '@type'       => 'CollectionPage',
        'url'         => (string) $term_link,
        'name'        => $final_name,
        'description' => $final_desc,
        'audience'    => [
            '@type'        => 'Audience',
            'audienceType' => $audience_txt,
        ],
        'mainEntity'  => [
            '@type'         => 'OfferCatalog',
            '@id'           => (string) $term_link . '#catalog',
            'name'          => $final_catalog,
            'numberOfItems' => $item_count,
        ],
    ];

    if ( ! empty( $image_url ) ) {
        $collection_page['image'] = $image_url;
    }

    // --- لیست آیتم‌های همین صفحه ---
    // OfferCatalog در schema.org زیرمجموعه ItemList است، پس محصولات صفحه
    // داخل همین نود قرار می‌گیرند به جای ساخت یک نود ItemList جداگانه.
    // (نسخه قبلی inc/enqueue.php دو ItemList مستقل روی همین صفحه چاپ می‌کرد.)
    $list_items = function_exists( 'hodima_get_archive_itemlist_elements' )
        ? hodima_get_archive_itemlist_elements()
        : [];

    if ( ! empty( $list_items ) ) {
        $collection_page['mainEntity']['itemListElement'] = $list_items;
    }

    // --- ویدیوی اختصاصی دسته‌بندی (در صورت وجود در term meta) ---
    $video_url = get_term_meta( $term->term_id, '_hod_video_url', true ) ?: get_term_meta( $term->term_id, '_hook_video_url', true );

    if ( ! empty( $video_url ) ) {
        $video_thumb = get_term_meta( $term->term_id, '_hod_video_thumbnail', true ) ?: get_term_meta( $term->term_id, '_hook_video_thumb', true );
        $video_date  = get_term_meta( $term->term_id, '_hod_video_date', true ) ?: get_term_meta( $term->term_id, '_hook_video_date', true );

        // 🛠️ دو باگ رفع‌شده:
        // ۱) date('c') از تایم‌زون سرور استفاده می‌کرد نه تایم‌زون واقعی سایت
        //    (همان کلاس باگی که در sitemap-core.php هم بود)؛ جایگزین شد با
        //    wp_date('c', ...) که تایم‌زون تنظیمات وردپرس را رعایت می‌کند.
        // ۲) اگر $video_date یک رشته‌ی نامعتبر بود (نه خالی، ولی قابل‌فهم
        //    برای strtotime هم نبود)، strtotime() مقدار false برمی‌گرداند و
        //    date('c', false) آن را صفر تفسیر می‌کرد یعنی تاریخ آپلود ویدیو
        //    در اسکیما می‌شد ۱ ژانویه ۱۹۷۰ — یک تاریخ کاملاً غلط و گمراه‌کننده
        //    در Search Console. حالا نتیجه‌ی strtotime بررسی می‌شود.
        $video_ts    = $video_date ? strtotime( $video_date ) : false;
        // 🛠️ باگ واقعی و تأییدشده (گزارش‌شده توسط ابزار Rich Results گوگل):
        // در اصلاح قبلی، date('c') با wp_date('c') جایگزین شد تا تایم‌زون
        // سایت رعایت شود — ولی wp_date() از لایه‌ی locale/i18n خود وردپرس
        // (همان date_i18n) عبور می‌کند. همان‌طور که در sitemap-core.php این
        // پروژه از قبل صراحتاً کامنت شده («بدون wp_date برای فرار از تاریخ
        // شمسی»)، این سایت یک مبدل تقویم شمسی روی خروجی تاریخ فارسی وردپرس
        // فعال دارد؛ یعنی wp_date('c', ...) به‌جای «۲۰۲۶-۱۰-۱۹» چیزی مثل
        // «۱۴۰۵-۰۷-۲۷» (تاریخ شمسی/اعداد فارسی) برمی‌گرداند که نه ISO 8601
        // است و نه گوگل آن را قبول می‌کند. راه‌حل درست: DateTime بومی PHP با
        // wp_timezone() — این هم تایم‌زون سایت را رعایت می‌کند و هم هرگز از
        // لایه‌ی locale/i18n وردپرس (و در نتیجه از تبدیل شمسی) عبور نمی‌کند.
        $upload_date = null;
        try {
            $ts = $video_ts ?: strtotime( '-1 month' );
            $dt = new DateTime( '@' . $ts );
            $dt->setTimezone( wp_timezone() );
            $upload_date = $dt->format( 'c' );
        } catch ( Exception $e ) {
            $upload_date = gmdate( 'c', $video_ts ?: strtotime( '-1 month' ) );
        }

        $collection_page['subjectOf'] = [
            '@type'        => 'VideoObject',
            'name'         => strtr( $video_title_tpl, $replace_vars ),
            'description'  => strtr( $video_desc_tpl, $replace_vars ),
            'thumbnailUrl' => [ esc_url( $video_thumb ?: $image_url ) ],
            'uploadDate'   => $upload_date,
            'contentUrl'   => esc_url( $video_url ),
        ];
    }

    return $memo = $collection_page;
}
