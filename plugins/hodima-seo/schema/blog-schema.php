<?php
/**
 * HOOK BLOG POSTING SCHEMA - GRAPH LINKED & AI EDITION
 * Path: wp-content/plugins/hodima-seo/schema/blog-schema.php
 */

if (!defined('ABSPATH')) exit;

add_action('wp_head', 'hook_render_blog_schema', 5);

function hook_render_blog_schema() {
    
    // 1. خواندن وضعیت فعال بودن از پنل ادمین
    $schema_status = get_option('hodima_blog_schema_status', 'on');
    if ( $schema_status !== 'on' ) {
        return; // اگر ادمین خاموش کرده بود، اسکیما چاپ نشود
    }

    if ( ! is_single() || get_post_type() !== 'post' ) {
        return;
    }

    global $post;
    $post_id = get_the_ID();

    $site_url      = trailingslashit(home_url());
    $post_url      = get_permalink($post_id);
    $post_title    = get_the_title($post_id);
    
    $raw_excerpt   = get_the_excerpt() ? get_the_excerpt() : wp_trim_words(strip_shortcodes($post->post_content), 30, '...');
    $clean_desc    = wp_strip_all_tags(html_entity_decode($raw_excerpt, ENT_QUOTES, 'UTF-8'));
    
    // 🛠️ باگ واقعی و مهم پیدا‌شده (همان خانواده‌ی باگ priceValidUntil/uploadDate):
    // get_the_date() و get_the_modified_date() هسته‌ی وردپرس، داخلشان با
    // پارامتر translate=true صدا زده می‌شوند که یعنی از wp_date() عبور
    // می‌کنند — همان تابعی که روی این سایت از مبدل تقویم شمسی رد می‌شود.
    // یعنی datePublished/dateModified مقاله‌ها هم به‌جای ISO 8601 میلادی،
    // تاریخ شمسی با اعداد فارسی چاپ می‌کردند. get_post_time()/
    // get_post_modified_time() با translate=false (که مقدار پیش‌فرضشان
    // هم همین است) این لایه را دور می‌زنند و امن‌اند — دقیقاً همان تابعی که
    // خود همین پروژه در sitemap-core.php از قبل برای همین دلیل استفاده می‌کند.
    $published_at  = get_post_time( 'c', false, $post_id, false );
    $modified_at   = get_post_modified_time( 'c', false, $post_id, false );
    $author_name   = get_the_author_meta('display_name', $post->post_author);
    
    // 2. جایگزین کردن عکس هاردکد شده با عکسی که از پنل ادمین می‌آید
    // تصویر پیش‌فرض دیگر یک گزینه‌ی جداگانه در این ماژول نیست؛ طبق درخواست شما،
    // تنها منبع لوگو/تصویر مرکزی همان تنظیمات صفحه اصلی (Homepage) است.
    $default_image_from_admin = get_option('hodima_schema_homepage_logo') ?: get_site_url(null, '/wp-content/uploads/2025/06/logo2.png');
    $image_url = $default_image_from_admin; 
    
    if ( has_post_thumbnail() ) {
        $image_url = get_the_post_thumbnail_url($post_id, 'full');
    }

    $media_data = function_exists('hook_get_media_data') ? hook_get_media_data($post_id, 'post') : array();

    // گوگل هدلاین بیش از ۱۱۰ کاراکتر را در سرچ کنسول به عنوان خطا/هشدار علامت می‌زند؛
    // فیلد name کامل باقی می‌ماند، فقط headline کوتاه می‌شود.
    $headline = mb_strlen($post_title) > 110 ? mb_substr($post_title, 0, 109) . '…' : $post_title;

    // ساختن آرایه BlogPosting
    $blog_posting = array(
        '@type' => 'BlogPosting',
        '@id' => $post_url . '#article',
        'headline' => $headline,
        'name' => $post_title,
        'description' => $clean_desc,
        'datePublished' => $published_at,
        'dateModified' => $modified_at,
        'author' => array(
            '@type' => 'Person',
            'name' => $author_name,
            'url' => get_author_posts_url($post->post_author)
        ),
        'image' => array(
            '@type' => 'ImageObject',
            '@id' => $post_url . '#primaryimage',
            'url' => $image_url,
        ),
        'url' => $post_url,
        // 🛠️ باگ رفع‌شده: قبلاً اینجا یک Organization کامل و مستقل (بدون
        // @id) به‌عنوان publisher ساخته می‌شد — یعنی یک نسخه‌ی دوم و جدا از
        // همان کسب‌وکار، غیر از نود اصلی Organization که homepage-schema.php
        // با @id ثابت (#organization) و اطلاعات کامل‌تر (آدرس، تلفن، sameAs)
        // می‌سازد. دقیقاً همان کلاس مشکلی که در بقیه‌ی این پروژه (schema-cleaner)
        // با آن مبارزه شده: چند نمایش جداگانه از یک کسب‌وکار روی یک صفحه.
        // چون homepage-schema.php روی صفحات مقاله هم اجرا می‌شود و همیشه نود
        // Organization را می‌سازد، اینجا فقط باید به همان @id ارجاع داد.
        'publisher' => array( '@id' => $site_url . '#organization' ),
        // 🛠️ باگ رفع‌شده: @id قبلی ($post_url بدون اسلش پایانی و بدون
        // #webpage) به هیچ نود واقعی‌ای در گراف اشاره نمی‌کرد — نه فرمتش با
        // نود WebPage واقعی (که homepage-schema.php با
        // trailingslashit($post_url).'#webpage' می‌سازد) یکی بود، نه اصلاً
        // چنین نودی با همین @id دقیق در جایی تعریف شده بود. یعنی یک ارجاع
        // «آویزان» به هیچ‌کجا. حالا دقیقاً همان @id واقعی صفحه استفاده می‌شود.
        'mainEntityOfPage' => array(
            // همان آدرسی که homepage-schema.php برای «#webpage» می‌سازد.
            // نسخه قبلی trailingslashit( get_permalink() ) بود؛ اگر برای نوشته
            // canonical دستی تنظیم شده بود یا نوشته صفحه‌بندی داشت، ارجاع به
            // نودی می‌رسید که وجود نداشت.
            '@id' => ( function_exists( 'hodima_get_canonical_url' ) && hodima_get_canonical_url() !== ''
                ? hodima_get_canonical_url()
                : trailingslashit( $post_url ) ) . '#webpage',
        )
    );

    // افزودن اطلاعات دیسکاور و هوش مصنوعی در صورت وجود
    if ( ! empty( $media_data['discover_title'] ) ) {
        $blog_posting['alternateName'] = wp_strip_all_tags( $media_data['discover_title'] );
    }

    if ( ! empty( $media_data['ai_summary'] ) ) {
        $blog_posting['abstract'] = wp_strip_all_tags( $media_data['ai_summary'] );
    }

    if ( ! empty( $media_data['key_entities'] ) ) {
        $about_entities = array();
        // جدا کردن کلمات با ویرگول انگلیسی یا فارسی
        $entities_array = explode( ',', str_replace( '،', ',', $media_data['key_entities'] ) );
        foreach ( $entities_array as $entity ) {
            $entity = trim( $entity );
            if ( ! empty( $entity ) ) {
                $about_entities[] = array(
                    '@type' => 'Thing',
                    'name'  => $entity
                );
            }
        }
        if ( ! empty( $about_entities ) ) {
            $blog_posting['about'] = $about_entities;
        }
    }

    // 3. افزودن به گراف واحد صفحه (hodima-core)
    hodima_schema_add( $blog_posting, 'hodima-seo: blog-schema' );
}