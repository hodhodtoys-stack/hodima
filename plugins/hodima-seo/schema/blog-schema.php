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
    // پایه همه شناسه‌ها همان آدرس canonical نود «#webpage». قبلا #article و
    // #primaryimage از پیوند یکتا ساخته می‌شدند ولی mainEntityOfPage از
    // canonical؛ با canonical دستی، مقاله از صفحه‌اش جدا می‌افتاد.
    $canonical     = function_exists( 'hodima_get_canonical_url' ) ? hodima_get_canonical_url() : '';
    $post_url      = '' !== $canonical ? $canonical : trailingslashit( (string) get_permalink( $post_id ) );
    $post_title    = get_the_title($post_id);
    
    // بدون چکیده دستی: «متن معرفی» نوشته (سیستم رسانه؛ inc/page-intro.php)، بعد ابتدای متن
    $intro         = function_exists( 'hodima_seo_page_intro_text' ) ? hodima_seo_page_intro_text( (int) $post_id, 'post', 40 ) : '';
    $raw_excerpt   = has_excerpt( $post_id ) ? get_the_excerpt() : ( '' !== $intro ? $intro : ( get_the_excerpt() ?: wp_trim_words(strip_shortcodes($post->post_content), 30, '...') ) );
    $clean_desc    = wp_strip_all_tags(html_entity_decode($raw_excerpt, ENT_QUOTES, 'UTF-8'));
    
    // باگ واقعی و مهم پیدا‌شده (همان خانواده‌ی باگ priceValidUntil/uploadDate):
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
    $default_image_from_admin = function_exists( 'hodima_seo_schema_logo_url' ) ? hodima_seo_schema_logo_url() : ( get_option('hodima_schema_homepage_logo') ?: get_site_url(null, '/wp-content/uploads/2025/06/logo2.png') );
    $image_url = $default_image_from_admin; 
    
    $image_size = null;
    $featured   = function_exists( 'hodima_seo_schema_featured_image' ) ? hodima_seo_schema_featured_image( (int) $post_id ) : null;
    if ( null !== $featured ) {
        [ $image_url, $w, $h ] = $featured;
        $image_size = [ $w, $h ];
    } elseif ( has_post_thumbnail() ) {
        $image_url = get_the_post_thumbnail_url($post_id, 'full');
    }

    // نام جدید سیستم رسانه (Hodima Media 1.2+)، با فالبک نام قدیمی
    $media_data = function_exists('hodima_media_get_data') ? hodima_media_get_data((int) $post_id, 'post')
        : ( function_exists('hook_get_media_data') ? hook_get_media_data($post_id, 'post') : array() );

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
        // نویسنده با شناسه (نود Person پایین؛ صفحه نویسنده ProfilePage همان را
        // mainEntity دارد). قبلا Person بی‌شناسه‌ی جداگانه در هر مقاله بود.
        'author' => function_exists( 'hodima_seo_schema_person_id' )
            ? array( '@id' => hodima_seo_schema_person_id( (int) $post->post_author ) )
            : array( '@type' => 'Person', 'name' => $author_name, 'url' => get_author_posts_url( $post->post_author ) ),
        'image' => array(
            '@type' => 'ImageObject',
            '@id' => $post_url . '#primaryimage',
            'url' => $image_url,
        ),
        'thumbnailUrl' => $image_url,
        'inLanguage' => 'fa-IR',
        'isPartOf' => array( '@id' => $post_url . '#webpage' ),
        'url' => $post_url,
        // باگ رفع‌شده: قبلاً اینجا یک Organization کامل و مستقل (بدون
        // @id) به‌عنوان publisher ساخته می‌شد — یعنی یک نسخه‌ی دوم و جدا از
        // همان کسب‌وکار، غیر از نود اصلی Organization که homepage-schema.php
        // با @id ثابت (#organization) و اطلاعات کامل‌تر (آدرس، تلفن، sameAs)
        // می‌سازد. دقیقاً همان کلاس مشکلی که در بقیه‌ی این پروژه (schema-cleaner)
        // با آن مبارزه شده: چند نمایش جداگانه از یک کسب‌وکار روی یک صفحه.
        // چون homepage-schema.php روی صفحات مقاله هم اجرا می‌شود و همیشه نود
        // Organization را می‌سازد، اینجا فقط باید به همان @id ارجاع داد.
        'publisher' => array( '@id' => $site_url . '#organization' ),
        // باگ رفع‌شده: @id قبلی ($post_url بدون اسلش پایانی و بدون
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
            '@id' => $post_url . '#webpage',
        )
    );

    /*
     * عنوان Discover → alternativeHeadline (ویژگی درست CreativeWork؛ قبلا
     * alternateName بود که یعنی «نام دیگر» مقاله) و موضوعات اصلی → about.
     * ماژول «Google Discover» همین افزونه (core/discover)؛ فالبک: Hodima
     * Media قدیمی (۱.۴ و پایین‌تر) که Discover را خودش داشت.
     */
    if ( function_exists( 'hodima_seo_discover_enrich' ) ) {
        if ( hodima_seo_discover_for_post( (int) $post_id ) ) {
            $blog_posting = hodima_seo_discover_enrich( $blog_posting, (int) $post_id );
        }
    } elseif ( function_exists( 'hodima_media_discover_image' ) ) {
        if ( ! empty( $media_data['discover_title'] ) ) {
            $blog_posting['alternativeHeadline'] = wp_strip_all_tags( $media_data['discover_title'] );
        }
        $about_entities = array_map(
            static fn( string $name ): array => [ '@type' => 'Thing', 'name' => $name ],
            function_exists( 'hodima_media_parse_entities' ) ? hodima_media_parse_entities( $media_data['key_entities'] ?? '' ) : []
        );
        if ( [] !== $about_entities ) {
            $blog_posting['about'] = $about_entities;
        }
    }

    if ( null !== $image_size ) {
        $blog_posting['image']['width']  = $image_size[0];
        $blog_posting['image']['height'] = $image_size[1];
    }

    /*
     * Google Discover / نتایج مقاله: گوگل تصویر با سه نسبت ۱۶:۹، ۴:۳ و ۱:۱
     * (عرض ۱۲۰۰) را توصیه می‌کند. ماژول «Google Discover» این برش‌ها را
     * هنگام ذخیره نوشته از «تصویر Discover» (یا تصویر شاخص) می‌سازد.
     * #primaryimage همان اول فهرست می‌ماند (نود صفحه به آن ارجاع می‌دهد).
     */
    $discover_images_fn = function_exists( 'hodima_seo_discover_images' ) ? 'hodima_seo_discover_images'
        : ( function_exists( 'hodima_media_discover_images' ) ? 'hodima_media_discover_images' : '' );
    if ( '' !== $discover_images_fn ) {
        // همان تصویر اصلی (#primaryimage) دوباره اضافه نشود
        $discover_images = array_values( array_filter(
            $discover_images_fn( (int) $post_id ),
            static fn( array $img ): bool => $img['url'] !== $image_url
        ) );
        if ( [] !== $discover_images ) {
            $blog_posting['image'] = array_merge( [ $blog_posting['image'] ], array_map(
                static fn( array $img ): array => [ '@type' => 'ImageObject', 'url' => $img['url'], 'width' => $img['width'], 'height' => $img['height'] ],
                $discover_images
            ) );
        }
    }

    // تعداد کلمات، بخش (دسته‌ها) و کلیدواژه‌ها (برچسب‌ها) — ویژگی‌های پیشنهادی Article
    $plain = trim( wp_strip_all_tags( strip_shortcodes( (string) $post->post_content ) ) );
    if ( '' !== $plain ) {
        $blog_posting['wordCount'] = count( preg_split( '/\s+/u', $plain, -1, PREG_SPLIT_NO_EMPTY ) );
    }

    $sections = array_values( wp_list_pluck( (array) get_the_category( $post_id ), 'name' ) );
    if ( [] !== $sections ) {
        $blog_posting['articleSection'] = count( $sections ) > 1 ? array_values( $sections ) : (string) $sections[0];
    }

    $tags = get_the_tags( $post_id );
    if ( is_array( $tags ) && [] !== $tags ) {
        $blog_posting['keywords'] = implode( '، ', wp_list_pluck( $tags, 'name' ) );
    }

    $graph = [ $blog_posting ];
    if ( function_exists( 'hodima_seo_schema_person_node' ) ) {
        $graph[] = hodima_seo_schema_person_node( (int) $post->post_author );
    }

    // 3. افزودن به گراف واحد صفحه (hodima-core)
    hodima_schema_add( [ '@graph' => $graph ], 'hodima-seo: blog-schema' );
}