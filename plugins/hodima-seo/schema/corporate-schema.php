<?php
/**
 * Corporate & Contact Schema (E-E-A-T Booster for Wholesale)
 * Path: /wp-content/themes/hodima/schema/corporate-schema.php
 *
 * توضیح مهم (رفع تکرار کد): این فایل دیگر مجموعه‌ی جداگانه‌ای از گزینه‌های
 * hodima_corp_* (نام شرکت، لوگو، تلفن‌ها، آدرس، شبکه‌های اجتماعی) نمی‌سازد.
 * تمام این اطلاعات از همان گزینه‌های واقعی که در «سیستم یکپارچه گراف دانش
 * گوگل و AI GEO» (admin/views/view-homepage.php) مدیریت می‌شوند خوانده می‌شود
 * (hodima_schema_homepage_*, hodima_schema_geo_*) — دقیقاً همان منبع داده‌ای
 * که homepage-schema.php هم برای نود Organization استفاده می‌کند. تنها دو
 * گزینه‌ی مخصوص همین فایل، hodima_corp_about_slug و hodima_corp_contact_slug
 * هستند که آن‌ها هم از قبل در همان صفحه‌ی view-homepage.php قابل ویرایش‌اند —
 * پس هیچ صفحه‌ی تنظیمات جدید یا گزینه‌ی تکراری لازم نیست.
 *
 * این اسکیما اعتبار قانونی و راه‌های ارتباطی سایت هدهد را به گوگل معرفی می‌کند.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'wp_head', 'hook_render_corporate_schema' );

function hook_render_corporate_schema() {

    // همان کلید فعال/غیرفعال‌سازی که «سیستم یکپارچه گراف دانش» استفاده می‌کند؛
    // یک سوییچ جداگانه برای این صفحه نگه‌داری نمی‌شود تا تکراری ایجاد نشود.
    if ( get_option( 'hodima_schema_homepage_enable', '1' ) !== '1' ) {
        return;
    }

    // 👈 خواندن اسلاگ برگه‌ها از همان تنظیمات مشترک (view-homepage.php)
    $about_slug   = get_option( 'hodima_corp_about_slug', 'about-us' );
    $contact_slug = get_option( 'hodima_corp_contact_slug', 'contact-us' );

    $is_about   = is_page( $about_slug );
    $is_contact = is_page( $contact_slug );

    if ( ! $is_about && ! $is_contact ) {
        return;
    }

    // --- توابع کمکی (هم‌راستا با همان‌هایی که در homepage-schema.php استفاده می‌شود) ---
    $sanitize_multiline = function ( $raw_text ) {
        if ( empty( $raw_text ) ) return [];
        $lines = array_map( 'trim', explode( "\n", $raw_text ) );
        return array_values( array_filter( $lines ) );
    };

    $normalize_phone = function ( $tel ) {
        $cleaned = preg_replace( '/[^0-9+]/', '', $tel );
        if ( strpos( $cleaned, '0' ) === 0 ) {
            return '+98' . substr( $cleaned, 1 );
        }
        return $cleaned;
    };

    $site_url = trailingslashit( home_url() );

    // --- خواندن اطلاعات واقعی از همان منبع مشترک (بدون هیچ گزینه‌ی جدید) ---
    $company_name   = get_option( 'hodima_schema_homepage_org_name' ) ?: get_bloginfo( 'name' ) ?: 'بازرگانی هدهد';
    $org_type       = get_option( 'hodima_schema_homepage_org_type', 'WholesaleStore' );
    $corp_desc      = get_option( 'hodima_schema_geo_description', get_bloginfo( 'description' ) ) ?: 'واردات مستقیم و پخش عمده تخصصی اکسسوری مو، خرج‌کار و ملزومات بسته‌بندی';

    $custom_logo_id = get_theme_mod( 'custom_logo' );
    $logo_url       = $custom_logo_id ? wp_get_attachment_image_url( $custom_logo_id, 'full' ) : get_option( 'hodima_schema_homepage_logo' );
    if ( empty( $logo_url ) ) $logo_url = $site_url . 'wp-content/uploads/2025/06/logo2.png';

    $alt_names = $sanitize_multiline( get_option( 'hodima_schema_geo_alt_names', "عمده فروشی هدهد\nبازرگانی هدهد\nhodima" ) );

    $address = [
        '@type'           => 'PostalAddress',
        'addressCountry'  => 'IR',
        'streetAddress'   => get_option( 'hodima_schema_homepage_street_address', 'تهرانپارس، خیابان احسان، پلاک ۸۴' ),
        'addressLocality' => get_option( 'hodima_schema_homepage_address_locality', 'تهران' ),
        // 🛠️ همان باگ homepage-schema.php: قبلاً اینجا هم دوباره همان
        // متغیر شهر خوانده می‌شد. حالا از گزینه‌ی جداگانه‌ی استان می‌خواند.
        'addressRegion'   => get_option( 'hodima_schema_homepage_address_region', 'تهران' ),
        'postalCode'      => get_option( 'hodima_schema_homepage_postal_code', '1657883361' ),
    ];

    $raw_telephones = $sanitize_multiline( get_option( 'hodima_schema_geo_telephones', "+989124093140\n02177322684" ) );
    $telephones     = array_map( $normalize_phone, $raw_telephones );
    $primary_phone  = ! empty( $telephones ) ? $telephones[0] : '+989124093140';
    $price_range    = get_option( 'hodima_schema_homepage_price_range' ) ?: 'IRR';

    $social_links = $sanitize_multiline( get_option( 'hodima_schema_geo_socials', "https://instagram.com/hodima\nhttps://t.me/hodimaaccessory\nhttps://wa.me/989124093140" ) );

    // --- ساخت ContactPoint برای هر شماره (بدون برچسب اختصاصی؛ چون سیستم مشترک فقط لیست شماره‌ها را نگه می‌دارد) ---
    $contact_points = [];
    foreach ( $telephones as $i => $tel ) {
        $contact_points[] = [
            '@type'             => 'ContactPoint',
            'telephone'         => $tel,
            'contactType'       => $i === 0 ? 'sales' : 'customer support',
            'areaServed'        => 'IR',
            'availableLanguage' => [ 'Persian' ],
        ];
    }

    $page_type = $is_about ? 'AboutPage' : 'ContactPage';
    // هم‌راستا با بقیه گراف: شناسه «#webpage» از موتور canonical مشترک
    $page_url  = function_exists( 'hodima_get_canonical_url' ) && hodima_get_canonical_url() !== ''
        ? hodima_get_canonical_url()
        : get_permalink();
    $page_name = get_the_title();

    $organization_node = [
        '@type'         => $org_type,
        '@id'           => $site_url . '#organization',
        'name'          => $company_name,
        'url'           => $site_url,
        // این دو فیلد قبلاً غایب بودند و گوگل در Rich Results Test آن‌ها را
        // به‌عنوان "Missing field" روی برگه‌های درباره‌ما/تماس گزارش می‌کرد؛
        // homepage-schema.php از قبل هر دو را در نود Organization دارد،
        // اینجا هم برای هماهنگی کامل اضافه شد.
        'telephone'     => $primary_phone,
        'priceRange'    => $price_range,
        // نکته: چون نود Organization در homepage-schema.php عمداً در همین صفحات
        // چاپ نمی‌شود (برای جلوگیری از تداخل @id)، لوگو باید اینجا کامل و
        // مستقل تعریف شود؛ صرفاً ارجاع {'@id': ...} به نودی که هرگز در همین
        // صفحه ساخته نمی‌شود، یک ارجاع معلق و نامعتبر می‌سازد.
        'logo'          => [
            '@type' => 'ImageObject',
            '@id'   => $site_url . '#logo',
            'url'   => $logo_url,
        ],
        'image'         => [ '@id' => $site_url . '#logo' ],
        'description'   => $corp_desc,
        'address'       => $address,
        'contactPoint'  => $contact_points,
        'sameAs'        => array_values( $social_links ),
    ];
    if ( ! empty( $alt_names ) ) {
        $organization_node['alternateName'] = $alt_names;
    }

    $schema = [
        '@context' => 'https://schema.org',
        '@graph'   => [
            $organization_node,
            [
                '@type'       => $page_type,
                '@id'         => $page_url . '#webpage', // بدون trailingslashit — دقیقا مثل homepage-schema
                'url'         => $page_url,
                'name'        => $page_name,
                'isPartOf'    => [ '@id' => $site_url . '#website' ],
                'about'       => [ '@id' => $site_url . '#organization' ],
                'description' => get_the_excerpt(),
            ],
        ],
    ];

    echo "\n<!-- Corporate & Contact Schema for hodima.com -->\n";
    echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP ) . "</script>\n";
}
