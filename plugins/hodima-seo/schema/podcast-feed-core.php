<?php
/**
 * HOOK PODCAST RSS ENGINE - Apple & Google Podcast Standard
 * Path: wp-content/plugins/hodima-seo/schema/podcast-feed-core.php
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ۱. ثبت آدرس فید پادکست (yourdomain.com/feed/podcast/)
add_action( 'init', 'hodima_register_podcast_feed' );
function hodima_register_podcast_feed() {
    if ( get_option('hodima_podcast_status', '1') === '1' ) {
        add_feed( 'podcast', 'hodima_render_podcast_feed' );
    }
}

// ۱ب. فلاش خودکار یک‌باره (Self-Healing)
// -----------------------------------------------------------------
// add_feed() فقط تابع callback را ثبت می‌کند؛ خودِ rewrite rule واقعی
// «/feed/podcast/» تا زمانی که پرمالینک‌ها flush نشوند در دیتابیس نوشته
// نمی‌شود، وگرنه وردپرس آدرس را نمی‌شناسد و آن را به صفحه اصلی هدایت
// می‌کند. به‌جای وابستگی به اینکه کاربر حتماً یک‌بار فرم تنظیمات پادکست
// را ذخیره کند، این کد خودش در همان اولین بارگذاری هر صفحه (فرانت یا
// ادمین) بعد از دیپلوی این آپدیت، یک‌بار flush را انجام می‌دهد.
add_action( 'init', 'hodima_maybe_flush_podcast_rewrite', 20 );
function hodima_maybe_flush_podcast_rewrite() {
    $current_ver = '2'; // هر بار منطق ثبت فید تغییر کرد، این عدد را افزایش دهید
    if ( get_option( 'hodima_podcast_rewrite_ver' ) !== $current_ver ) {
        flush_rewrite_rules( false );
        update_option( 'hodima_podcast_rewrite_ver', $current_ver, false );
    }
}

// ۱ج. توابع کمکی
// -----------------------------------------------------------------
// باگ رفع‌شده: قبلاً عنوان/توضیحات/محتوا مستقیماً و بدون هیچ محافظتی
// داخل <![CDATA[ ... ]]> چاپ می‌شدند. اگر متن پست یا تنظیمات پادکست
// (عنوان، توضیح کانال، خلاصه‌ی مطلب و...) به هر دلیلی شامل دنباله‌ی
// «]]>» باشد (کپی از یک HTML دیگر، کد نمونه، و…)، آن دنباله عملاً همان
// بسته‌ی CDATA را زودتر از موعد می‌بندد و XML خروجی از آن نقطه به بعد
// نامعتبر/خراب می‌شود — یعنی کل فید پادکست در Apple/Google Podcasts و
// هر ریدر RSS دیگری خطا می‌دهد. این تابع دقیقاً همان راهکار استاندارد
// XML را پیاده می‌کند: «]]>» را می‌شکند و CDATA را دوباره باز می‌کند.
function hodima_podcast_cdata( $text ) {
    return str_replace( ']]>', ']]]]><![CDATA[>', (string) $text );
}

// باگ رفع‌شده: نوع فایل صوتی همیشه ثابت 'audio/mpeg' فرض می‌شد، حتی
// اگر فایل واقعی m4a/wav/ogg بود. اپلیکیشن‌های پادکست به این مقدار برای
// تصمیم پخش/دانلود اعتماد می‌کنند؛ مقدار غلط می‌تواند باعث خطای پخش شود.
function hodima_podcast_mime_from_url( $url ) {
    $ext = strtolower( pathinfo( wp_parse_url( (string) $url, PHP_URL_PATH ) ?: '', PATHINFO_EXTENSION ) );
    $map = [
        'mp3'  => 'audio/mpeg',
        'm4a'  => 'audio/mp4',
        'mp4'  => 'audio/mp4',
        'wav'  => 'audio/wav',
        'ogg'  => 'audio/ogg',
        'oga'  => 'audio/ogg',
        'flac' => 'audio/flac',
        'aac'  => 'audio/aac',
    ];
    return $map[ $ext ] ?? 'audio/mpeg'; // پیش‌فرض امن اگر پسوند شناخته‌شده نبود
}

// ۲. تولید خروجی استاندارد RSS 2.0
function hodima_render_podcast_feed() {
    header( 'Content-Type: application/rss+xml; charset=' . get_option( 'blog_charset' ), true );
    
    // دریافت تنظیمات از پنل
    $title       = get_option('hodima_podcast_title', get_bloginfo('name') . ' - پادکست');
    $desc        = get_option('hodima_podcast_desc', get_bloginfo('description'));
    // تصویر کاور دیگر گزینه‌ی جداگانه‌ای در این ماژول نیست؛ طبق درخواست شما،
    // تنها منبع لوگو/تصویر مرکزی همان تنظیمات صفحه اصلی (Homepage) است.
    $cover       = get_option('hodima_schema_homepage_logo') ?: site_url('/wp-content/uploads/logo.png');
    $author      = get_option('hodima_podcast_author', 'بازرگانی هدهد');
    $category    = get_option('hodima_podcast_category', 'Business');
    $explicit    = get_option('hodima_podcast_explicit', 'clean');
    $post_types  = get_option('hodima_podcast_post_types', ['post', 'product']);

    echo '<?xml version="1.0" encoding="' . get_option('blog_charset') . '"?>' . "\n";
    ?>
    <rss version="2.0" 
        xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd" 
        xmlns:content="http://purl.org/rss/1.0/modules/content/" 
        xmlns:atom="http://www.w3.org/2005/Atom">
        
        <channel>
            <title><![CDATA[<?php echo hodima_podcast_cdata($title); ?>]]></title>
            <link><?php echo esc_url(home_url()); ?></link>
            <description><![CDATA[<?php echo hodima_podcast_cdata($desc); ?>]]></description>
            <language>fa-IR</language>
            <atom:link href="<?php echo esc_url(site_url('/feed/podcast/')); ?>" rel="self" type="application/rss+xml" />
            <itunes:author><![CDATA[<?php echo hodima_podcast_cdata($author); ?>]]></itunes:author>
            <itunes:summary><![CDATA[<?php echo hodima_podcast_cdata($desc); ?>]]></itunes:summary>
            <itunes:owner>
                <itunes:name><![CDATA[<?php echo hodima_podcast_cdata($author); ?>]]></itunes:name>
                <itunes:email><?php echo esc_html(get_option('admin_email')); ?></itunes:email>
            </itunes:owner>
            <itunes:explicit><?php echo esc_html($explicit); ?></itunes:explicit>
            <itunes:category text="<?php echo esc_attr($category); ?>" />
            <itunes:image href="<?php echo esc_url($cover); ?>" />
            <image>
                <url><?php echo esc_url($cover); ?></url>
                <title><![CDATA[<?php echo hodima_podcast_cdata($title); ?>]]></title>
                <link><?php echo esc_url(home_url()); ?></link>
            </image>

            <?php
            // کوئری برای پیدا کردن پست‌هایی که فایل صوتی دارند
            $args = [
                'post_type'      => $post_types,
                'post_status'    => 'publish',
                'has_password'   => false, // فایل صوتی نوشته رمزدار نباید در فید عمومی بیاید
                'posts_per_page' => 50,
                'meta_query'     => [
                    [
                        'key'     => '_hook_audio_url', // کلید متای فایل صوتی
                        'value'   => '',
                        'compare' => '!=',
                    ]
                ]
            ];
            $podcast_query = new WP_Query($args);

            if ( $podcast_query->have_posts() ) :
                while ( $podcast_query->have_posts() ) : $podcast_query->the_post();
                    
                    $audio_url = get_post_meta(get_the_ID(), '_hook_audio_url', true);
                    if ( empty($audio_url) ) continue;

                    $audio_size = get_post_meta(get_the_ID(), '_hook_audio_size', true) ?: '1024000'; // حجم به بایت
                    $audio_type = hodima_podcast_mime_from_url( $audio_url ); // تشخیص واقعی نوع فایل از پسوند URL
                    
                    $post_thumb = has_post_thumbnail() ? get_the_post_thumbnail_url(get_the_ID(), 'full') : $cover;
            ?>
            <item>
                <title><![CDATA[<?php echo hodima_podcast_cdata( get_the_title() ); ?>]]></title>
                <link><?php the_permalink(); ?></link>
                <pubDate><?php echo mysql2date('D, d M Y H:i:s +0000', get_post_time('Y-m-d H:i:s', true), false); ?></pubDate>
                <guid isPermaLink="false"><?php the_guid(); ?></guid>
                <description><![CDATA[<?php echo hodima_podcast_cdata( wp_trim_words(get_the_excerpt(), 40, '...') ); ?>]]></description>
                <content:encoded><![CDATA[<?php echo hodima_podcast_cdata( get_the_excerpt() ); ?>]]></content:encoded>
                <enclosure url="<?php echo esc_url($audio_url); ?>" length="<?php echo esc_attr($audio_size); ?>" type="<?php echo esc_attr($audio_type); ?>" />
                <itunes:image href="<?php echo esc_url($post_thumb); ?>" />
                <itunes:author><![CDATA[<?php echo hodima_podcast_cdata($author); ?>]]></itunes:author>
                <itunes:explicit><?php echo esc_html($explicit); ?></itunes:explicit>
            </item>
            <?php
                endwhile;
                wp_reset_postdata();
            endif;
            ?>
        </channel>
    </rss>
    <?php
    exit;
}