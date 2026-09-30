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

// ۱د. قسمت‌ها (episodes)
// -----------------------------------------------------------------
/*
 * باگ اصلی رفع‌شده: فید دنبال متای «_hook_audio_url» می‌گشت، ولی سیستم رسانه
 * فایل صوتی را در «_hook_voice_url» (نوشته/محصول) و «hook_voice_url» (دسته)
 * ذخیره می‌کند. نتیجه: فید هیچ قسمتی نداشت و برای همه فقط همان متن ثابت
 * کانال (عنوان و توضیح پادکست) نمایش داده می‌شد.
 *
 * حالا هر نوشته/محصول/دسته‌ای که صوت دارد یک قسمت است، با داده‌های خودش:
 * عنوان صوت، خلاصه همان صفحه، تاریخ انتشار صوت، مدت، تصویر و حجم واقعی فایل.
 * «_hook_audio_url» قدیمی هم هنوز خوانده می‌شود.
 */
function hodima_podcast_episodes(): array {

    $post_types = (array) get_option( 'hodima_podcast_post_types', [ 'post', 'product' ] );
    $cache_key  = 'hodima_podcast_eps_' . md5( wp_json_encode( [ $post_types, get_option( 'hodima_podcast_cache_ver', 1 ) ] ) );
    $cached     = get_transient( $cache_key );

    if ( is_array( $cached ) ) {
        return $cached;
    }

    $cover    = hodima_podcast_cover();
    $episodes = [];

    $media = static fn( int $id, string $context ): array => function_exists( 'hook_get_media_data' ) ? hook_get_media_data( $id, $context ) : [];

    // نوشته‌ها و محصولات
    $post_types = array_values( array_filter( $post_types, 'post_type_exists' ) );

    if ( [] !== $post_types ) {

        $ids = get_posts( [
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'has_password'   => false, // صوت نوشته رمزدار نباید در فید عمومی بیاید
            'posts_per_page' => 100,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            'meta_query'     => [
                'relation' => 'OR',
                [ 'key' => '_hook_voice_url', 'value' => '', 'compare' => '!=' ],
                [ 'key' => '_hook_audio_url', 'value' => '', 'compare' => '!=' ],
            ],
        ] );

        foreach ( $ids as $post_id ) {

            $post_id = (int) $post_id;
            $data    = $media( $post_id, 'post' );
            $url     = (string) ( $data['voice_url'] ?? '' ) ?: (string) get_post_meta( $post_id, '_hook_voice_url', true ) ?: (string) get_post_meta( $post_id, '_hook_audio_url', true );

            if ( '' === trim( $url ) ) {
                continue;
            }

            $post  = get_post( $post_id );
            $text  = '' !== trim( (string) $post->post_excerpt ) ? (string) $post->post_excerpt : ( (string) ( $data['ai_summary'] ?? '' ) ?: (string) $post->post_content );
            $image = (string) get_the_post_thumbnail_url( $post_id, 'full' ) ?: (string) ( $data['video_thumb'] ?? '' ) ?: $cover;
            $date  = hodima_podcast_timestamp( (string) ( $data['voice_date'] ?? '' ) ) ?: (int) strtotime( (string) $post->post_date_gmt . ' UTC' );

            $episodes[] = [
                'title'    => (string) ( $data['voice_title'] ?? '' ) ?: get_the_title( $post_id ),
                'link'     => (string) get_permalink( $post_id ),
                'guid'     => (string) get_the_guid( $post_id ),
                'summary'  => hodima_podcast_text( $text ),
                'date'     => $date,
                'url'      => $url,
                'length'   => hodima_podcast_file_size( $url, (string) get_post_meta( $post_id, '_hook_audio_size', true ) ),
                'duration' => hodima_podcast_duration( (string) ( $data['voice_duration'] ?? '' ) ),
                'image'    => $image,
            ];
        }
    }

    // دسته‌ها (دسته محصول و دسته نوشته که صوت دارند)
    $taxonomies = array_values( array_filter( [
        in_array( 'product', $post_types, true ) ? 'product_cat' : '',
        in_array( 'post', $post_types, true ) ? 'category' : '',
    ], static fn( string $tax ): bool => '' !== $tax && taxonomy_exists( $tax ) ) );

    if ( [] !== $taxonomies ) {

        $terms = get_terms( [
            'taxonomy'   => $taxonomies,
            'hide_empty' => false,
            'number'     => 100,
            'meta_query' => [ [ 'key' => 'hook_voice_url', 'value' => '', 'compare' => '!=' ] ],
        ] );

        foreach ( is_array( $terms ) ? $terms : [] as $term ) {

            $data = $media( (int) $term->term_id, 'term' );
            $url  = (string) ( $data['voice_url'] ?? '' );
            $link = get_term_link( $term );

            if ( '' === trim( $url ) || is_wp_error( $link ) ) {
                continue;
            }

            $thumb_id = (int) get_term_meta( $term->term_id, 'thumbnail_id', true );
            $date     = function_exists( 'hook_media_stable_date' ) ? hook_media_stable_date( $data, 'voice', (int) $term->term_id, 'term' ) : (string) ( $data['voice_date'] ?? '' );

            $episodes[] = [
                'title'    => (string) ( $data['voice_title'] ?? '' ) ?: $term->name,
                'link'     => (string) $link,
                'guid'     => (string) $link . '#podcast',
                'summary'  => hodima_podcast_text( (string) $term->description ),
                'date'     => hodima_podcast_timestamp( $date ) ?: time(),
                'url'      => $url,
                'length'   => hodima_podcast_file_size( $url, '' ),
                'duration' => hodima_podcast_duration( (string) ( $data['voice_duration'] ?? '' ) ),
                'image'    => ( $thumb_id ? (string) wp_get_attachment_url( $thumb_id ) : '' ) ?: (string) ( $data['video_thumb'] ?? '' ) ?: $cover,
            ];
        }
    }

    // تازه‌ترین قسمت اول؛ حداکثر ۵۰
    usort( $episodes, static fn( array $a, array $b ): int => $b['date'] <=> $a['date'] );
    $episodes = array_slice( $episodes, 0, 50 );

    set_transient( $cache_key, $episodes, HOUR_IN_SECONDS );

    return $episodes;
}

// کش قسمت‌ها با هر ذخیره نوشته/محصول/دسته باطل می‌شود
foreach ( [ 'save_post', 'trashed_post', 'deleted_post', 'edited_term', 'delete_term' ] as $hodima_podcast_hook ) {
    add_action( $hodima_podcast_hook, static fn() => update_option( 'hodima_podcast_cache_ver', time(), false ) );
}
unset( $hodima_podcast_hook );

/** تصویر کانال: همان لوگوی اسکیما (قبلا فالبک به logo.png ناموجود). */
function hodima_podcast_cover(): string {
    return function_exists( 'hodima_seo_schema_logo_url' )
        ? hodima_seo_schema_logo_url()
        : ( (string) get_option( 'hodima_schema_homepage_logo' ) ?: home_url( '/wp-content/uploads/2025/06/logo2.png' ) );
}

/** متن ساده کوتاه (بدون شورت‌کد و HTML). */
function hodima_podcast_text( string $text ): string {
    return trim( wp_trim_words( wp_strip_all_tags( strip_shortcodes( $text ) ), 60, '…' ) );
}

/** رشته تاریخ (ISO، ارقام فارسی) → timestamp، یا ۰. */
function hodima_podcast_timestamp( string $date ): int {
    $date = trim( strtr( $date, [ '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9' ] ) );
    $ts   = '' === $date ? false : ( ctype_digit( $date ) ? (int) $date : strtotime( $date ) );
    return ( $ts && $ts > 0 ) ? (int) $ts : 0;
}

/** مدت («۱۲:۴۰»، «1:05:20»، ثانیه) → HH:MM:SS برای itunes:duration، یا رشته خالی. */
function hodima_podcast_duration( string $duration ): string {

    $duration = trim( strtr( $duration, [ '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9' ] ) );

    if ( ctype_digit( $duration ) ) {
        $seconds = (int) $duration;
    } elseif ( preg_match( '/^(?:(\d{1,2}):)?(\d{1,2}):(\d{1,2})$/', $duration, $m ) ) {
        $seconds = (int) $m[1] * 3600 + (int) $m[2] * 60 + (int) $m[3];
    } else {
        return '';
    }

    return $seconds > 0 ? sprintf( '%02d:%02d:%02d', intdiv( $seconds, 3600 ), intdiv( $seconds % 3600, 60 ), $seconds % 60 ) : '';
}

/**
 * حجم واقعی فایل به بایت (enclosure length).
 * باگ قبلی: بدون متای حجم، عدد ساختگی ۱۰۲۴۰۰۰ برای همه فایل‌ها. حالا حجم
 * فایل آپلودشده در کتابخانه رسانه؛ برای فایل بیرونی ۰ (مجاز در RSS وقتی
 * حجم معلوم نیست).
 */
function hodima_podcast_file_size( string $url, string $stored ): int {

    if ( ctype_digit( $stored ) && (int) $stored > 0 ) {
        return (int) $stored;
    }

    $attachment = attachment_url_to_postid( $url );
    $file       = $attachment ? get_attached_file( $attachment ) : '';

    return ( $file && is_readable( $file ) ) ? (int) filesize( $file ) : 0;
}

// ۲. تولید خروجی استاندارد RSS 2.0
function hodima_render_podcast_feed() {
    header( 'Content-Type: application/rss+xml; charset=UTF-8', true );

    $org_name = function_exists( 'hodima_seo_schema_org_name' ) ? hodima_seo_schema_org_name() : get_bloginfo( 'name' );

    // دریافت تنظیمات از پنل
    $title    = get_option( 'hodima_podcast_title', get_bloginfo( 'name' ) . ' - پادکست' );
    $desc     = get_option( 'hodima_podcast_desc', get_bloginfo( 'description' ) );
    $cover    = hodima_podcast_cover();
    $author   = get_option( 'hodima_podcast_author' ) ?: $org_name;
    $category = get_option( 'hodima_podcast_category', 'Business' );
    // اپل از ۲۰۲۲ فقط true/false می‌پذیرد (clean/explicit/yes منسوخ)
    $explicit = 'explicit' === get_option( 'hodima_podcast_explicit', 'clean' ) ? 'true' : 'false';
    $episodes = hodima_podcast_episodes();
    $updated  = [] !== $episodes ? max( array_column( $episodes, 'date' ) ) : time();
    $rfc      = static fn( int $ts ): string => gmdate( 'D, d M Y H:i:s +0000', $ts );

    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    ?>
<rss version="2.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <title><![CDATA[<?php echo hodima_podcast_cdata( $title ); ?>]]></title>
        <link><?php echo esc_url( home_url( '/' ) ); ?></link>
        <description><![CDATA[<?php echo hodima_podcast_cdata( $desc ); ?>]]></description>
        <language>fa</language>
        <copyright><![CDATA[© <?php echo esc_html( gmdate( 'Y' ) . ' ' ); echo hodima_podcast_cdata( $org_name ); ?>]]></copyright>
        <lastBuildDate><?php echo esc_html( $rfc( (int) $updated ) ); ?></lastBuildDate>
        <atom:link href="<?php echo esc_url( home_url( '/feed/podcast/' ) ); ?>" rel="self" type="application/rss+xml" />
        <itunes:type>episodic</itunes:type>
        <itunes:author><![CDATA[<?php echo hodima_podcast_cdata( $author ); ?>]]></itunes:author>
        <itunes:summary><![CDATA[<?php echo hodima_podcast_cdata( $desc ); ?>]]></itunes:summary>
        <itunes:owner>
            <itunes:name><![CDATA[<?php echo hodima_podcast_cdata( $author ); ?>]]></itunes:name>
            <itunes:email><?php echo esc_html( get_option( 'admin_email' ) ); ?></itunes:email>
        </itunes:owner>
        <itunes:explicit><?php echo esc_html( $explicit ); ?></itunes:explicit>
        <itunes:category text="<?php echo esc_attr( $category ); ?>" />
        <itunes:image href="<?php echo esc_url( $cover ); ?>" />
        <image>
            <url><?php echo esc_url( $cover ); ?></url>
            <title><![CDATA[<?php echo hodima_podcast_cdata( $title ); ?>]]></title>
            <link><?php echo esc_url( home_url( '/' ) ); ?></link>
        </image>
<?php foreach ( $episodes as $episode ) : ?>
        <item>
            <title><![CDATA[<?php echo hodima_podcast_cdata( $episode['title'] ); ?>]]></title>
            <itunes:title><![CDATA[<?php echo hodima_podcast_cdata( $episode['title'] ); ?>]]></itunes:title>
            <link><?php echo esc_url( $episode['link'] ); ?></link>
            <guid isPermaLink="false"><?php echo esc_html( $episode['guid'] ); ?></guid>
            <pubDate><?php echo esc_html( $rfc( (int) $episode['date'] ) ); ?></pubDate>
            <description><![CDATA[<?php echo hodima_podcast_cdata( $episode['summary'] ?: $episode['title'] ); ?>]]></description>
            <itunes:summary><![CDATA[<?php echo hodima_podcast_cdata( $episode['summary'] ?: $episode['title'] ); ?>]]></itunes:summary>
            <content:encoded><![CDATA[<?php echo hodima_podcast_cdata( '<p>' . esc_html( $episode['summary'] ?: $episode['title'] ) . '</p><p><a href="' . esc_url( $episode['link'] ) . '">' . esc_html( $episode['title'] ) . '</a></p>' ); ?>]]></content:encoded>
            <enclosure url="<?php echo esc_url( $episode['url'] ); ?>" length="<?php echo (int) $episode['length']; ?>" type="<?php echo esc_attr( hodima_podcast_mime_from_url( $episode['url'] ) ); ?>" />
<?php if ( '' !== $episode['duration'] ) : ?>
            <itunes:duration><?php echo esc_html( $episode['duration'] ); ?></itunes:duration>
<?php endif; ?>
            <itunes:episodeType>full</itunes:episodeType>
            <itunes:image href="<?php echo esc_url( $episode['image'] ); ?>" />
            <itunes:explicit><?php echo esc_html( $explicit ); ?></itunes:explicit>
        </item>
<?php endforeach; ?>
    </channel>
</rss>
    <?php
    exit;
}