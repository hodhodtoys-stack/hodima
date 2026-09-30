<?php
/**
 * Corporate & Contact Schema (E-E-A-T Booster for Wholesale)
 * Path: wp-content/plugins/hodima-seo/schema/corporate-schema.php
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

    // خواندن اسلاگ برگه‌ها از همان تنظیمات مشترک (view-homepage.php)
    $about_slug   = get_option( 'hodima_corp_about_slug', 'about-us' );
    $contact_slug = get_option( 'hodima_corp_contact_slug', 'contact-us' );

    $is_about   = is_page( $about_slug );
    $is_contact = is_page( $contact_slug );

    if ( ! $is_about && ! $is_contact ) {
        return;
    }

    $site_url = trailingslashit( home_url() );

    $page_type = $is_about ? 'AboutPage' : 'ContactPage';
    // هم‌راستا با بقیه گراف: شناسه «#webpage» از موتور canonical مشترک
    $page_url  = function_exists( 'hodima_get_canonical_url' ) && hodima_get_canonical_url() !== ''
        ? hodima_get_canonical_url()
        : get_permalink();
    $page_name = get_the_title();

    // همان نود سازمان صفحه‌های دیگر (schema-helpers.php). قبلا این فایل نسخه
    // جداگانه‌ای می‌ساخت که ساعت کاری، knowsAbout، شعار و areaServed نداشت،
    // پس گوگل برای یک کسب‌وکار دو تعریف متفاوت می‌دید.
    $organization_node = hodima_seo_schema_organization_node();

    $webpage_node = [
        '@type'       => $page_type,
        '@id'         => $page_url . '#webpage', // بدون trailingslashit — دقیقا مثل homepage-schema
        'url'         => $page_url,
        'name'        => $page_name,
        'isPartOf'    => [ '@id' => $site_url . '#website' ],
        'about'       => [ '@id' => $site_url . '#organization' ],
        'inLanguage'  => 'fa-IR',
    ];

    // توضیح خالی ("") ویژگی بی‌معنا است؛ فقط وقتی متنی هست
    $excerpt = wp_strip_all_tags( (string) get_the_excerpt() );
    if ( '' !== $excerpt ) {
        $webpage_node['description'] = $excerpt;
    }

    /*
     * همان نقطه غنی‌سازی homepage-schema.php. قبلا نود صفحه درباره‌ما/تماس
     * از این فیلتر رد نمی‌شد، پس بردکرامب همین صفحه (#breadcrumb) به هیچ
     * نودی وصل نبود و رابطه‌های خوشه محتوایی به صورت نود جزئی جداگانه
     * چاپ می‌شد.
     */
    $webpage_node = (array) apply_filters( 'hodima_schema_webpage_node', $webpage_node, $page_url );
    $GLOBALS['hodima_schema_webpage_emitted'] = true;

    hodima_schema_add( [ '@graph' => [ $organization_node, $webpage_node ] ], 'hodima-seo: corporate-schema' );
}
