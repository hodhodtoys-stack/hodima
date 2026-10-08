<?php
/**
 * HOOK XML SITEMAP PRO - Ultimate Engine V8 (Fixed Product Indexing & SEOBox Logic)
 * Path: wp-content/plugins/hodima-seo/schema/sitemap-core.php
 */

declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ==========================================
// ۱. تنظیم Rewrite Rules
// ==========================================
add_action( 'init', 'hodima_sitemap_init_rules' );
function hodima_sitemap_init_rules() {
    if ( get_option('hodima_sitemap_status', '1') !== '1' ) return;
    
    add_rewrite_rule( '^sitemap\.xml$', 'index.php?hodima_sitemap=index', 'top' );
    add_rewrite_rule( '^sitemap\.xsl$', 'index.php?hodima_sitemap=xsl', 'top' );
    add_rewrite_rule( '^([a-z0-9_-]+)-sitemap-([0-9]+)\.xml$', 'index.php?hodima_sitemap=$matches[1]&hodima_smpage=$matches[2]', 'top' );
    add_rewrite_rule( '^([a-z0-9_-]+)-sitemap\.xml$', 'index.php?hodima_sitemap=$matches[1]&hodima_smpage=1', 'top' );
}

add_filter( 'query_vars', 'hodima_sitemap_query_vars' );
function hodima_sitemap_query_vars( $vars ) {
    $vars[] = 'hodima_sitemap';
    $vars[] = 'hodima_smpage';
    return $vars;
}

// ==========================================
// توابع فیلتر فوق‌عمیق Noindex (یکپارچه با SEOBox)
// ==========================================
function hodima_get_noindex_post_ids() {
    global $wpdb;

    // بهینه‌سازی: این کوئری روی meta_key با کارَکتر % ابتدایی اجرا می‌شد که
    // ایندکس جدول wp_postmeta را کاملاً بی‌اثر می‌کرد و باعث اسکن کامل جدول
    // در هر بار اجرا می‌شد. حالا نتیجه، به همراه بقیه‌ی کش سایت‌مپ، پشت یک
    // transient اختصاصی قرار می‌گیرد که فقط با تغییر نسخه‌ی کش (ذخیره/حذف
    // پست یا ترم) باطل می‌شود؛ یعنی این اسکن سنگین به‌جای اجرا در هر درخواستِ
    // هر صفحه از سایت‌مپ، فقط یک‌بار در هر چرخه‌ی کش اجرا می‌شود.
    $cache_version = get_option( 'hodima_sitemap_cache_ver', 1 );
    $cache_key     = 'hodima_noindex_ids_' . md5( (string) $cache_version );

    $cached = get_transient( $cache_key );
    if ( is_array( $cached ) ) {
        return $cached;
    }

    // باگ مهم رفع‌شده (باعث افت ناگهانی شمارش سایت‌مپ می‌شد):
    // نسخه‌ی قبلی از «meta_key LIKE '%noindex%'» استفاده می‌کرد؛ یعنی هر
    // متاکی‌ای در کل جدول wp_postmeta که کلمه‌ی noindex در هر جایی از اسمش
    // بود (نه فقط فیلدهای واقعی سئو)، با هر مقدار '1'/'yes'/'true'/'on'
    // باعث می‌شد آن پست به‌کل از سایت‌مپ حذف شود — حتی اگر آن متاکی هیچ
    // ربطی به noindex واقعی نداشت (مثلاً یک فیلد اسپکس محصول یا متای یک
    // پلاگین دیگر که تصادفاً همین ساب‌استرینگ را در اسمش داشت). حالا فقط
    // کلیدهای شناخته‌شده‌ی noindex (خود سیستم + سئوباکس + یواست + AIOSEO)
    // یا کلیدهایی که دقیقاً با «_noindex»/«noindex» تمام می‌شوند
    // بررسی می‌شوند، و مقایسه‌ی مقدار case-insensitive است تا با تابع مشابه
    // برای ترم‌ها (hodima_is_term_noindex) هم‌راستا بماند.
    $ids = $wpdb->get_col("
        SELECT post_id FROM {$wpdb->postmeta} 
        WHERE 
            (
                (
                    meta_key = 'noindex'
                    OR meta_key LIKE '%\\_noindex'
                )
                AND LOWER(meta_value) IN ('1', 'yes', 'true', 'on')
            )
            OR (meta_key = '_seobox_robots' AND meta_value LIKE '%noindex%')
            OR (meta_key = '_yoast_wpseo_meta-robots-noindex' AND meta_value = '1')
            OR (meta_key = '_aioseo_robots_noindex' AND meta_value = '1')
    ");

    $ids = !empty($ids) ? array_unique(array_map('intval', $ids)) : [];

    set_transient( $cache_key, $ids, 12 * HOUR_IN_SECONDS );

    return $ids;
}

function hodima_is_term_noindex( $term_id ) {
    // تشخیص واحد Core (همان قاعده زیر)؛ این بدنه فقط فالبک بدون Core است
    if ( function_exists( 'hodima_is_noindex' ) ) {
        return hodima_is_noindex( (int) $term_id, 'term' );
    }
    // باگ رفع‌شده: این تابع همان باگ hodima_get_noindex_post_ids() را
    // داشت (که در همین فایل رفع شد) ولی خودش هیچ‌وقت اصلاح نشده بود —
    // str_contains($key, 'noindex') یعنی هر متاکیِ ترم که این ساب‌استرینگ
    // را هر جای اسمش داشت (نه فقط فیلد واقعی noindex)، آن دسته‌بندی را از
    // سایت‌مپ حذف می‌کرد. حالا فقط کلیدهای شناخته‌شده یا کلیدهایی که دقیقاً
    // با «_noindex»/«noindex» تمام می‌شوند بررسی می‌شوند — هم‌راستا با
    // نسخه‌ی رفع‌شده‌ی همین منطق برای پست‌ها.
    $meta = get_term_meta( $term_id );
    // rank_math_robots برداشته شد: Rank Math از سایت حذف شده و داده‌اش پاکسازی می‌شود
    $known_exact_keys = [ '_seobox_robots', '_yoast_wpseo_meta-robots-noindex', '_aioseo_robots_noindex' ];

    foreach ( $meta as $key => $values ) {
        $key_lower  = strtolower( (string) $key );
        $is_anchored = ( $key_lower === 'noindex' || str_ends_with( $key_lower, '_noindex' ) );
        $is_known    = in_array( $key, $known_exact_keys, true );

        if ( ! $is_anchored && ! $is_known ) {
            continue;
        }

        foreach ( $values as $v ) {
            $v_lower = strtolower( (string) $v );
            if ( in_array( $v_lower, [ '1', 'yes', 'true', 'on' ], true ) ) return true;
            if ( $is_known && is_string( $v ) && str_contains( $v_lower, 'noindex' ) ) return true; // سئوباکس مقدار را داخل یک رشته/آرایه سریالایز می‌کند
        }
    }
    return false;
}

/**
 * آیا canonical دستی (سئوباکس) این صفحه به آدرس دیگری اشاره می‌کند؟
 *
 * سایت‌مپ فقط باید آدرس‌های canonical را داشته باشد. قبلا نوشته/دسته‌ای
 * که canonical آن به صفحه دیگری تنظیم شده بود هم فهرست می‌شد؛ گوگل در
 * Search Console آن را «Alternate page with proper canonical tag» و سیگنال
 * متناقض می‌بیند.
 */
function hodima_sitemap_canonical_elsewhere( string $own_url, string $override ): bool {

    $override = trim( $override );

    if ( '' === $override ) {
        return false;
    }

    $norm = static fn( string $u ): string => untrailingslashit( strtolower( (string) preg_replace( '#^https?://(www\.)?#i', '', rawurldecode( $u ) ) ) );

    return $norm( $override ) !== $norm( $own_url );
}

/**
 * آدرس مطلق تصویر (سایت‌مپ آدرس نسبی را نمی‌پذیرد)، یا رشته خالی.
 * قبلا <img src="/wp-content/…"> نسبی همان‌طور در image:loc چاپ می‌شد.
 */
function hodima_sitemap_absolute_url( string $url ): string {

    $url = trim( $url );

    if ( '' === $url || str_starts_with( $url, 'data:' ) ) {
        return '';
    }

    if ( str_starts_with( $url, '//' ) ) {
        return 'https:' . $url;
    }

    if ( str_starts_with( $url, '/' ) ) {
        return home_url( $url );
    }

    return 1 === preg_match( '#^https?://#i', $url ) ? $url : '';
}

/**
 * ویدیوی سیستم رسانه برای سایت‌مپ، از همان سازنده واحد (hodima_media_video).
 *
 * @return array|null|false آرایه = ورودی سایت‌مپ؛ null = ویدیو ندارد یا سیستم
 *   رسانه برای این صفحه خاموش است؛ false = Hodima Media قدیمی یا داده خیلی
 *   قدیمی «_hod_video_url» (مسیر قبلی همین فایل).
 */
function hodima_sitemap_media_video( int $object_id, string $context, string $title ): array|null|false {

    if ( ! function_exists( 'hodima_media_video' ) ) {
        return false;
    }

    $legacy = 'term' === $context ? get_term_meta( $object_id, '_hod_video_url', true ) : get_post_meta( $object_id, '_hod_video_url', true );
    if ( ! empty( $legacy ) ) {
        return false;
    }

    $video = hodima_media_video( $object_id, $context );

    // دسته‌ای که قالب بخش رسانه‌اش را نشان نمی‌دهد (دسته وبلاگ): ویدیو روی صفحه نیست
    if ( null === $video || ! $video['enabled']
        || ( function_exists( 'hodima_media_is_displayed' ) && ! hodima_media_is_displayed( $object_id, $context ) ) ) {
        return null;
    }

    return [
        'url'      => hodima_sitemap_clean_url( $video['url'] ),
        'title'    => '' !== $video['title'] ? $video['title'] : $title,
        'thumb'    => hodima_sitemap_clean_url( (string) ( $video['cover']['url'] ?? '' ) ),
        'duration' => $video['seconds'],
        'date'     => $video['date'],
    ];
}

/**
 * برچسب مکان ویدیو: فایل مستقیم → video:content_loc، صفحه آپارات/یوتیوب →
 * video:player_loc با آدرس *پخش‌کننده* (hodima_video_player_url در Core).
 *
 * باگ قبلی: الگوی «\.m(?:p4|3u8|kv|ebm)» فایل webm را نمی‌شناخت («mebm»)
 * و mov/m4v را هم؛ و صفحه تماشای آپارات به‌عنوان player_loc می‌رفت که
 * گوگل آن را پخش‌کننده نمی‌داند.
 *
 * @return array{0: string, 1: string} [ نام برچسب, آدرس ]
 */
function hodima_sitemap_video_loc( string $url ): array {

    $path = (string) wp_parse_url( $url, PHP_URL_PATH );

    if ( 1 === preg_match( '/\.(mp4|m4v|webm|mov|ogv|m3u8)$/i', $path ) ) {
        return [ 'video:content_loc', $url ];
    }

    return [ 'video:player_loc', function_exists( 'hodima_video_player_url' ) ? hodima_video_player_url( $url ) : $url ];
}

// ==========================================
// توابع اختصاصی و جدید کمکی ویدیو
// ==========================================
function hodima_sitemap_clean_url( $url ) {
    $url = trim( (string) $url );
    if ( '' === $url ) return '';
    $url = str_replace( array( '‑', '–', '—' ), '-', $url );
    $url = str_replace( array( '/v/%20', '/v/ ' ), '/v/', $url );
    $url = preg_replace( '/\s+/', '%20', $url );
    return esc_url_raw( $url );
}

function hodima_sitemap_cdata( $text ) {
    return str_replace( ']]>', ']]]]><![CDATA[>', (string) $text );
}

function hodima_sitemap_duration_seconds( $duration ) {
    $duration = trim( (string) $duration );
    if ( '' === $duration ) return 0;
    if ( is_numeric( $duration ) ) return absint( $duration );
    $parts = array_map( 'absint', explode( ':', $duration ) );
    $count = count( $parts );
    if ( 2 === $count ) return ( $parts[0] * 60 ) + $parts[1];
    if ( 3 === $count ) return ( $parts[0] * 3600 ) + ( $parts[1] * 60 ) + $parts[2];
    return 0;
}

function hodima_sitemap_jalali_to_gregorian( $jy, $jm, $jd ) {
    $jy += 1595;
    $days = -355668 + ( 365 * $jy ) + (int) ( $jy / 33 ) * 8 + (int) ( ( ( $jy % 33 ) + 3 ) / 4 ) + $jd;
    if ( $jm < 7 ) {
        $days += ( $jm - 1 ) * 31;
    } else {
        $days += ( ( $jm - 7 ) * 30 ) + 186;
    }
    $gy = 400 * (int) ( $days / 146097 );
    $days %= 146097;
    if ( $days > 36524 ) {
        $gy += 100 * (int) ( --$days / 36524 );
        $days %= 36524;
        if ( $days >= 365 ) $days++;
    }
    $gy += 4 * (int) ( $days / 1461 );
    $days %= 1461;
    if ( $days > 365 ) {
        $gy += (int) ( ( $days - 1 ) / 365 );
        $days = ( $days - 1 ) % 365;
    }
    $gd = $days + 1;
    $months = array(0, 31, ( ( 0 === $gy % 4 && 0 !== $gy % 100 ) || ( 0 === $gy % 400 ) ) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31);
    $gm = 1;
    for ( $i = 1; $i <= 12; $i++ ) {
        if ( $gd <= $months[ $i ] ) {
            $gm = $i;
            break;
        }
        $gd -= $months[ $i ];
    }
    return array( $gy, $gm, $gd );
}

function hodima_sitemap_normalize_iso_date( $date, $post_id = 0 ) {
    $date = trim( wp_strip_all_tags( (string) $date ) );

    // 1. جلوگیری از ترجمه شمسی و گرفتن تاریخ خام به عنوان Fallback
    $fallback_date = $post_id ? get_post_time( 'c', false, $post_id, false ) : date( 'c' );

    if ( '' === $date ) return $fallback_date;

    // 2. تبدیل اعداد فارسی/عربی به انگلیسی و اسلش به خط تیره
    $persian_digits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    $arabic_digits  = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
    $english_digits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    $date = str_replace( array_merge($persian_digits, $arabic_digits), $english_digits, $date );
    $date = str_replace( '/', '-', $date );

    // 3. بررسی رجکس تاریخ (پشتیبانی از فرمت‌های مختلف)
    if ( preg_match( '/^(\d{4})-(\d{1,2})-(\d{1,2})(?:[T\s](\d{1,2}):(\d{1,2})(?::(\d{1,2}))?)?(?:([+-]\d{2}:\d{2}|Z))?$/', $date, $m ) ) {
        $year   = (int) $m[1];
        $month  = (int) $m[2];
        $day    = (int) $m[3];
        $hour   = ! empty( $m[4] ) ? (int) $m[4] : 0;
        $minute = ! empty( $m[5] ) ? (int) $m[5] : 0;
        $second = ! empty( $m[6] ) ? (int) $m[6] : 0;

        // اگر تاریخ شمسی بود (سال کمتر از 1700)، آن را میلادی کن
        if ( $year < 1700 ) {
            list( $gy, $gm, $gd ) = hodima_sitemap_jalali_to_gregorian( $year, $month, $day );
            try {
                $dt = new DateTime( 'now', wp_timezone() );
                $dt->setDate( $gy, $gm, $gd );
                $dt->setTime( $hour, $minute, $second );
                return $dt->format( 'c' );
            } catch (Exception $e) {
                return $fallback_date;
            }
        }
    }
    
    // اگر فرمت تاریخ چیز دیگری بود، از strtotime و date استفاده می‌کنیم (بدون wp_date برای فرار از تاریخ شمسی)
    $timestamp = strtotime( $date );
    return $timestamp ? date( 'c', $timestamp ) : $fallback_date;
}

// ==========================================
// موتور استخراج عمیق (عکس و فیلم + متادیتاها)
// ==========================================
function hodima_sitemap_deep_radar( $object_id, $type = 'post' ) {
    $data = [ 'images' => [], 'videos' => [] ];
    $content = '';
    $title = '';

    if ( $type === 'post' ) {
        $title     = get_the_title( $object_id );
        $post      = get_post( $object_id );
        $content   = $post ? $post->post_content : '';
        $post_type = get_post_type( $object_id );

        if ( has_post_thumbnail( $object_id ) ) {
            $data['images'][] = get_the_post_thumbnail_url( $object_id, 'full' );
        }

        $attached_videos = get_attached_media( 'video', $object_id );
        if ( !empty($attached_videos) ) {
            foreach ( $attached_videos as $att ) {
                $data['videos'][] = [
                    'url'      => wp_get_attachment_url( $att->ID ),
                    'title'    => $att->post_title ?: $title,
                    'thumb'    => '',
                    'duration' => 0,
                    // ترفند مهم: چهارمین پارامتر 'false' باعث می‌شود افزونه‌های شمسی ساز این خط را فیلتر نکنند
                    'date'     => get_post_time( 'c', false, $att->ID, false ) 
                ];
            }
        }
        
        // گالری محصول (تصاویر گالری معمولا پیوست خود محصول نیستند و قبلا جا می‌ماندند)
        foreach ( array_filter( array_map( 'intval', explode( ',', (string) get_post_meta( $object_id, '_product_image_gallery', true ) ) ) ) as $gallery_id ) {
            $data['images'][] = wp_get_attachment_url( $gallery_id );
        }

        $attached_images = get_attached_media( 'image', $object_id );
        if ( !empty($attached_images) ) {
            foreach ( $attached_images as $att ) {
                $data['images'][] = wp_get_attachment_url( $att->ID );
            }
        }

        /*
         * ویدیوی سیستم رسانه از همان سازنده واحد اسکیما (Hodima Media 1.2+):
         * کاور، عنوان، مدت و تاریخ یکسان با VideoObject صفحه، و فقط وقتی
         * سیستم رسانه برای این صفحه روشن است (ویدیوی خاموش روی صفحه نیست).
         * false = Hodima Media قدیمی یا داده خیلی قدیمی «_hod_video_*» → مسیر قبلی.
         */
        $media_entry = hodima_sitemap_media_video( (int) $object_id, 'post', (string) $title );

        if ( is_array( $media_entry ) ) {
            $data['videos'][] = $media_entry;
        } elseif ( false === $media_entry ) {
            $v_url      = get_post_meta( $object_id, '_hod_video_url', true ) ?: get_post_meta( $object_id, '_hook_video_url', true );
            $v_thumb    = get_post_meta( $object_id, '_hod_video_thumbnail', true ) ?: get_post_meta( $object_id, '_hook_video_thumb', true );
            $v_title    = get_post_meta( $object_id, '_hod_video_title', true ) ?: get_post_meta( $object_id, '_hook_video_title', true );
            $v_duration = get_post_meta( $object_id, '_hod_video_duration', true ) ?: get_post_meta( $object_id, '_hook_video_duration', true );
            $v_date     = get_post_meta( $object_id, '_hod_video_date', true ) ?: get_post_meta( $object_id, '_hook_video_date', true );
        
            /*
             * ویدیوی سیستم رسانه: عنوان، مدت، کاور و تاریخ خودش.
             * باگ قبلی: hook_get_media_data( $id, $post_type ) — زمینه باید «post»
             * باشد نه نوع نوشته («product»)؛ برای محصول کلیدها با پیشوند ترم
             * خوانده می‌شد و ویدیو پیدا نمی‌شد. عنوان/مدت/تاریخ هم خوانده نمی‌شد.
             */
            if ( function_exists('hook_get_media_data') ) {
                $media = hook_get_media_data( (int) $object_id, 'post' );
                if ( empty($v_url) && !empty($media['video_url']) ) {
                    $v_url = $media['video_url'];
                }
                if ( $v_url && $v_url === ($media['video_url'] ?? '') ) {
                    if (empty($v_thumb) && !empty($media['video_thumb'])) $v_thumb = $media['video_thumb'];
                    if (empty($v_title) && !empty($media['video_title'])) $v_title = $media['video_title'];
                    if (empty($v_duration) && !empty($media['video_duration'])) $v_duration = $media['video_duration'];
                    if (empty($v_date) && !empty($media['video_date'])) $v_date = $media['video_date'];
                }
            }
        
            if ( $v_url ) {
                $data['videos'][] = [
                    'url'      => hodima_sitemap_clean_url($v_url),
                    'title'    => $v_title ?: $title,
                    'thumb'    => hodima_sitemap_clean_url($v_thumb),
                    'duration' => hodima_sitemap_duration_seconds($v_duration),
                    'date'     => hodima_sitemap_normalize_iso_date($v_date, $object_id)
                ];
            }
        }

    } else {
        $term = get_term( $object_id );
        if ( $term && !is_wp_error($term) ) {
            $title = $term->name;
            $content = $term->description;

            $thumb_id = get_term_meta( $object_id, 'thumbnail_id', true ) ?: get_term_meta( $object_id, 'product_cat_thumbnail_id', true );
            if ( $thumb_id ) {
                $img_url = wp_get_attachment_url( (int) $thumb_id );
                if ( $img_url ) $data['images'][] = $img_url;
            }

            $media_entry = hodima_sitemap_media_video( (int) $object_id, 'term', (string) $title );

            if ( is_array( $media_entry ) ) {
                $data['videos'][] = $media_entry;
            } elseif ( false === $media_entry ) {
                $v_url      = get_term_meta( $object_id, '_hod_video_url', true ) ?: get_term_meta( $object_id, '_hook_video_url', true );
                $v_thumb    = get_term_meta( $object_id, '_hod_video_thumbnail', true ) ?: get_term_meta( $object_id, '_hook_video_thumb', true );
                $v_title    = get_term_meta( $object_id, '_hod_video_title', true ) ?: get_term_meta( $object_id, '_hook_video_title', true );
                $v_duration = get_term_meta( $object_id, '_hod_video_duration', true ) ?: get_term_meta( $object_id, '_hook_video_duration', true );
                $v_date     = get_term_meta( $object_id, '_hod_video_date', true ) ?: get_term_meta( $object_id, '_hook_video_date', true );

                // این بخش عیناً معادل fallback موجود برای پست‌ها است که برای ترم‌ها اصلاً
                // وجود نداشت — همان چیزی که باعث می‌شد ویدیوی دسته‌بندی خوانده نشود
                // در حالی که همین تابع برای محصولات (پست‌ها) کاملاً درست کار می‌کرد.
                if ( function_exists('hook_get_media_data') ) {
                    $media = hook_get_media_data( (int) $object_id, 'term' );
                    if ( empty($v_url) && !empty($media['video_url']) ) {
                        $v_url = $media['video_url'];
                    }
                    if ( $v_url && $v_url === ($media['video_url'] ?? '') ) {
                        if (empty($v_thumb) && !empty($media['video_thumb'])) $v_thumb = $media['video_thumb'];
                        if (empty($v_title) && !empty($media['video_title'])) $v_title = $media['video_title'];
                        if (empty($v_duration) && !empty($media['video_duration'])) $v_duration = $media['video_duration'];
                        // همان تاریخ پایدار اسکیمای سیستم رسانه (uploadDate)؛ قبلا تاریخ آخرین
                        // محتوای دسته جایش می‌رفت که با هر ویرایش محصولی عوض می‌شد
                        if (empty($v_date) && function_exists('hook_media_stable_date')) $v_date = hook_media_stable_date( $media, 'video', (int) $object_id, 'term' );
                    }
                }

                if ( $v_url ) {
                    $data['videos'][] = [
                        'url'      => hodima_sitemap_clean_url($v_url),
                        'title'    => $v_title ?: $title,
                        'thumb'    => hodima_sitemap_clean_url($v_thumb),
                        'duration' => hodima_sitemap_duration_seconds($v_duration),
                        // بدون تاریخ ثبت‌شده، خالی (اختیاری در استاندارد) — نه «همین الان»
                        'date'     => '' !== trim( (string) $v_date ) ? hodima_sitemap_normalize_iso_date($v_date, 0) : ''
                    ];
                }
            }
        }
    }

    if ( !empty($content) ) {
        $clean_content = str_replace('\/', '/', $content);
        if ( preg_match_all( '/https?:\/\/[^\s"\'<>]+\.(?:jpg|jpeg|png|gif|webp)[^\s"\'<>]*/i', $clean_content, $img_matches ) ) {
            $data['images'] = array_merge($data['images'], $img_matches[0]);
        }
        if ( preg_match_all( '/<img[^>]+src=["\']([^"\']+)["\']/i', $clean_content, $img_tags ) ) {
            $data['images'] = array_merge($data['images'], $img_tags[1]);
        }
        if ( preg_match_all( '/https?:\/\/[^\s"\'<>]+\.(?:mp4|m3u8|webm)[^\s"\'<>]*/i', $clean_content, $vid_matches ) ) {
             foreach ( $vid_matches[0] as $url ) {
                $data['videos'][] = [ 'url' => hodima_sitemap_clean_url($url), 'title' => $title, 'thumb' => '', 'duration' => 0, 'date' => '' ];
            }
        }
        if ( preg_match_all( '/(?:<video[^>]+src=["\']|<source[^>]+src=["\']|\[video[^\]]+(?:src|mp4)=["\']|<iframe[^>]+src=["\'])([^"\']+(?:mp4|m3u8|aparat\.com|youtube\.com|vimeo\.com)[^"\']*)/i', $clean_content, $iframe_matches ) ) {
            foreach ( $iframe_matches[1] as $url ) {
                $data['videos'][] = [ 'url' => hodima_sitemap_clean_url($url), 'title' => $title, 'thumb' => '', 'duration' => 0, 'date' => '' ];
            }
        }
    }

    $data['images'] = array_values( array_unique( array_filter( array_map( static fn( $u ) => hodima_sitemap_absolute_url( (string) $u ), $data['images'] ) ) ) );
    
    if ( ! empty( $data['images'] ) ) {
        $data['images'] = array_slice( $data['images'], 0, 10 ); // تصویر اصلی + گالری (قبلا ۵)
    }
    
    $unique_videos = [];
    foreach ( array_filter( $data['videos'] ) as $v ) {
        if ( !empty($v['url']) && !isset($unique_videos[$v['url']]) ) {
            $unique_videos[$v['url']] = $v;
        }
    }
    $data['videos'] = array_values( $unique_videos );

    return $data;
}

// ==========================================
// رندر خروجی نقشه‌های ساختاریافته (Sitemap Core)
// ==========================================
add_action( 'template_redirect', 'hodima_sitemap_render' );
function hodima_sitemap_render() {
    $sitemap_type = get_query_var( 'hodima_sitemap' );
    if ( ! $sitemap_type ) return;

    if ( $sitemap_type === 'xsl' ) {
        header( 'Content-Type: text/xsl; charset=' . get_option( 'blog_charset' ), true );
        echo '<?xml version="1.0" encoding="UTF-8"?>';
        ?>
        <xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" xmlns:sitemap="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1" xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">
            <xsl:output method="html" indent="yes" encoding="UTF-8"/>
            <xsl:template match="/">
                <html dir="rtl" lang="fa-IR">
                    <head>
                        <title>نقشه سایت هدیما (XML Sitemap)</title>
                        <style>
                            <?php // فونت محلی همراه Hodima Core (فالبک: فونت قالب)، نه Tahoma/Arial؛ این صفحه XSL استایل قالب را ندارد ?>
                            @font-face { font-family: 'Vazirmatn'; src: url('<?php echo esc_url( function_exists( 'hodima_core_font_url' ) ? hodima_core_font_url( 'Regular' ) : get_template_directory_uri() . '/assets/fonts/Vazirmatn-Regular.woff2' ); ?>') format('woff2'); font-weight: 400; font-display: swap; }
                            @font-face { font-family: 'Vazirmatn'; src: url('<?php echo esc_url( function_exists( 'hodima_core_font_url' ) ? hodima_core_font_url( 'Bold' ) : get_template_directory_uri() . '/assets/fonts/Vazirmatn-Bold.woff2' ); ?>') format('woff2'); font-weight: 700; font-display: swap; }
                            body { font-family: 'Vazirmatn', sans-serif; background-color: #f8fafc; color: #1e293b; margin: 0; padding: 20px; line-height: 1.5; }
                            .container { max-width: 1000px; margin: 0 auto; background: #fff; padding: 20px; border: 1px solid #e2e8f0; }
                            .header { border-bottom: 2px solid #25316a; padding-bottom: 15px; margin-bottom: 20px; }
                            .header h1 { margin: 0 0 10px 0; font-size: 22px; color: #25316a; font-weight: normal; }
                            .summary-text { font-size: 13px; color: #475569; margin-bottom: 15px; padding: 10px; background: #f1f5f9; border-right: 3px solid #607bbd; }
                            table { width: 100%; border-collapse: collapse; table-layout: fixed; }
                            th, td { padding: 10px; text-align: right; border-bottom: 1px solid #e2e8f0; font-size: 13px; word-wrap: break-word; }
                            th { background: #f8fafc; color: #25316a; font-weight: normal; }
                            tr:hover { background-color: #f1f5f9; }
                            a { color: #607bbd; text-decoration: none; direction: ltr; display: inline-block; }
                            a:hover { color: #25316a; text-decoration: underline; }
                            .media-badge { display: inline-block; background: #f1f5f9; color: #475569; padding: 2px 6px; border-radius: 4px; font-size: 11px; margin-left: 5px; border: 1px solid #cbd5e1; }
                        </style>
                    </head>
                    <body>
                        <div class="container">
                            <div class="header"><h1>نقشه سایت (XML Sitemap)</h1></div>
                            <xsl:if test="sitemap:sitemapindex">
                                <div class="summary-text">تعداد سایت‌مپ‌ها: <xsl:value-of select="count(sitemap:sitemapindex/sitemap:sitemap)"/></div>
                                <table>
                                    <tr><th style="width: 200px;">آخرین بروزرسانی</th><th style="text-align: left;">آدرس سایت‌مپ (Sitemap URL)</th></tr>
                                    <xsl:for-each select="sitemap:sitemapindex/sitemap:sitemap">
                                        <tr>
                                            <td dir="ltr" style="text-align: right; color: #64748b;"><xsl:value-of select="concat(substring(sitemap:lastmod,1,10), ' ', substring(sitemap:lastmod,12,5), ' ', substring(sitemap:lastmod,20))"/></td>
                                            <td dir="ltr" style="text-align: left;"><xsl:variable name="itemURL"><xsl:value-of select="sitemap:loc"/></xsl:variable><a href="{$itemURL}"><xsl:value-of select="sitemap:loc"/></a></td>
                                        </tr>
                                    </xsl:for-each>
                                </table>
                            </xsl:if>
                            <xsl:if test="sitemap:urlset">
                                <div class="summary-text">تعداد لینک‌ها: <xsl:value-of select="count(sitemap:urlset/sitemap:url)"/></div>
                                <table>
                                    <tr><th style="width: 180px;">آخرین بروزرسانی</th><th style="width: 140px;">رسانه</th><th style="text-align: left;">آدرس صفحه (URL)</th></tr>
                                    <xsl:for-each select="sitemap:urlset/sitemap:url">
                                        <tr>
                                            <td dir="ltr" style="text-align: right; color: #64748b;">
                                                <xsl:choose><xsl:when test="sitemap:lastmod"><xsl:value-of select="concat(substring(sitemap:lastmod,1,10), ' ', substring(sitemap:lastmod,12,5), ' ', substring(sitemap:lastmod,20))"/></xsl:when><xsl:otherwise>-</xsl:otherwise></xsl:choose>
                                            </td>
                                            <td style="text-align: right;">
                                                <xsl:if test="image:image"><span class="media-badge"><xsl:value-of select="count(image:image)"/> تصویر</span></xsl:if>
                                                <xsl:if test="video:video"><span class="media-badge"><xsl:value-of select="count(video:video)"/> ویدیو</span></xsl:if>
                                            </td>
                                            <td dir="ltr" style="text-align: left;"><xsl:variable name="itemURL"><xsl:value-of select="sitemap:loc"/></xsl:variable><a href="{$itemURL}"><xsl:value-of select="sitemap:loc"/></a></td>
                                        </tr>
                                    </xsl:for-each>
                                </table>
                            </xsl:if>
                        </div>
                    </body>
                </html>
            </xsl:template>
        </xsl:stylesheet>
        <?php
        exit;
    }

    $paged = max( 1, absint( get_query_var( 'hodima_smpage' ) ) ?: 1 );
    $limit = (int) get_option('hodima_sitemap_links_limit', 1000);
    // باگ رفع‌شده: اگر این تنظیم به هر دلیلی (مقدار دستی در دیتابیس،
    // باگ در فرم ذخیره و...) صفر یا منفی شود، تمام محاسبات ceil(x/$limit)
    // پایین‌تر در PHP 8 با DivisionByZeroError کرش می‌کنند و کل سایت‌مپ
    // (و هر صفحه‌ای که آن را include می‌کند) سفید می‌شود. فرم ادمین همین
    // الان هم بین ۱۰۰ تا ۵۰۰۰ محدودش می‌کند، ولی این خط، خودِ این فایل را
    // هم مستقل از فرم ادمین ایمن می‌کند.
    if ( $limit < 1 ) $limit = 1000;

    header( 'Content-Type: text/xml; charset=' . get_option( 'blog_charset' ), true );
    header( 'X-Robots-Tag: noindex, follow', true );

    // FORCE LOAD PRODUCT POST TYPE 
    $enabled_post_types = get_option('hodima_sitemap_post_types', ['post', 'page', 'product']);
    if (!is_array($enabled_post_types)) $enabled_post_types = ['post', 'page', 'product'];
    if (!in_array('product', $enabled_post_types)) $enabled_post_types[] = 'product';

    $enabled_taxonomies = get_option('hodima_sitemap_taxonomies', ['category', 'product_cat']);
    if (!is_array($enabled_taxonomies)) $enabled_taxonomies = ['category', 'product_cat'];
    if (!in_array('product_cat', $enabled_taxonomies)) $enabled_taxonomies[] = 'product_cat';
    if (!in_array('category', $enabled_taxonomies)) $enabled_taxonomies[] = 'category'; // اجباری: دسته‌بندی نوشته‌ها دیگر هرگز از سایت‌مپ حذف نمی‌شود

    // نوع/تکسونومی که روی سایت وجود ندارد (مثلا product بدون ووکامرس) سایت‌مپ خالی نمی‌گیرد
    $enabled_post_types = array_values( array_filter( $enabled_post_types, 'post_type_exists' ) );
    $enabled_taxonomies = array_values( array_filter( $enabled_taxonomies, 'taxonomy_exists' ) );

    /*
     * نوع ناشناخته (/foo-sitemap.xml) → ۴۰۴ واقعی قالب.
     * قبلا با کد ۲۰۰ فقط اعلان XML بدون محتوا برمی‌گشت (XML نامعتبر).
     */
    if ( 'index' !== $sitemap_type && ! in_array( $sitemap_type, $enabled_post_types, true ) && ! in_array( $sitemap_type, $enabled_taxonomies, true ) ) {
        hodima_sitemap_not_found();
        return;
    }

    $cache_version = get_option('hodima_sitemap_cache_ver', 1);
    // کلید کش (V9: canonical دستی، ویدیوی پخش‌کننده، گالری، بدون سایت‌مپ خالی)
    $cache_key = 'hodima_sitemap_v9_pro_' . md5( (string)$cache_version . '_' . $sitemap_type . '_' . $paged . '_' . $limit );
    
    $cached_xml = get_transient( $cache_key );
    if ( $cached_xml ) { echo $cached_xml; exit; }

    $noindex_post_ids = hodima_get_noindex_post_ids();
    // تصویر پیش‌فرض دیگر گزینه‌ی جداگانه‌ای در این ماژول نیست؛ طبق درخواست شما،
    // تنها منبع لوگو/تصویر مرکزی همان تنظیمات صفحه اصلی (Homepage) است.
    // همان لوگوی اسکیما (لوگوی قالب ← تنظیم لوگو)؛ قبلا فالبک به logo.png ناموجود
    $fallback_logo = esc_url( function_exists( 'hodima_seo_schema_logo_url' ) ? hodima_seo_schema_logo_url() : ( get_option('hodima_schema_homepage_logo') ?: home_url('/wp-content/uploads/2025/06/logo2.png') ) );

    ob_start();
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<?xml-stylesheet type="text/xsl" href="' . esc_url( home_url( '/sitemap.xsl' ) ) . '"?>' . "\n";

    // ==========================================
    // ایندکس اصلی نقشه سایت
    // ==========================================
    if ( $sitemap_type === 'index' ) {
        echo '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        /*
         * lastmod هر زیرسایت‌مپ = زمان آخرین ویرایش واقعی محتوای همان بخش.
         * قبلا برای همه «همین الان» چاپ می‌شد؛ گوگل lastmod‌ای را که همیشه
         * تغییر می‌کند غیرقابل‌اعتماد می‌داند و کلا نادیده می‌گیرد.
         */

        foreach ( $enabled_post_types as $pt ) {
            $query_args = ['post_type' => $pt, 'post_status' => 'publish', 'has_password' => false, 'posts_per_page' => 1, 'fields' => 'ids', 'no_found_rows' => false, 'update_post_meta_cache' => false, 'update_post_term_cache' => false];
            if (!empty($noindex_post_ids)) $query_args['post__not_in'] = $noindex_post_ids;
            
            $count_query = new WP_Query($query_args);
            $found = (int) $count_query->found_posts;

            // صفحه اصلی «آخرین نوشته‌ها» (بدون برگه) در سایت‌مپ برگه‌ها می‌آید
            if ( 'page' === $pt && 'posts' === get_option( 'show_on_front' ) ) $found++;

            // سایت‌مپ خالی در فهرست نمی‌آید (قبلا همیشه حداقل یک صفحه خالی)
            if ( $found < 1 ) continue;

            $pages = (int) ceil( $found / $limit );

            for ( $i = 1; $i <= $pages; $i++ ) {
                $chunk_newest = hodima_sitemap_newest_post( [
                    'post_type'    => $pt,
                    'offset'       => ( $i - 1 ) * $limit,
                    'post__not_in' => $noindex_post_ids,
                ] );
                echo "\t<sitemap>\n\t\t<loc>" . esc_url( home_url( "/{$pt}-sitemap-{$i}.xml" ) ) . "</loc>\n" . hodima_sitemap_lastmod_tag( $chunk_newest, "\t\t" ) . "\t</sitemap>\n";
            }
        }

        foreach ( $enabled_taxonomies as $tax ) {
            $terms = get_terms(['taxonomy' => $tax, 'hide_empty' => true, 'fields' => 'ids']);
            $term_count = 0;
            if ( ! is_wp_error( $terms ) ) {
                // هم‌راستا با حلقه‌ی واقعی رندر (که ترم‌های noindex را رد می‌کند)؛
                // قبلاً اینجا شمارش بدون فیلتر noindex بود و باعث می‌شد تعداد
                // صفحات ایندکس با تعداد واقعی آیتم‌های چاپ‌شده هماهنگ نباشد.
                foreach ( $terms as $t_id ) {
                    if ( ! hodima_is_term_noindex( $t_id ) ) $term_count++;
                }
            }
            if ( $term_count < 1 ) continue; // بدون دسته قابل‌ایندکس، سایت‌مپ خالی نمی‌سازد

            $pages = (int) ceil( $term_count / $limit );

            // آخرین محتوای ویرایش‌شده در هر ترمی از این تکسونومی
            $tax_newest = hodima_sitemap_newest_post( [
                'post_type' => 'any',
                'tax_query' => [ [ 'taxonomy' => $tax, 'operator' => 'EXISTS' ] ],
            ] );

            for ( $i = 1; $i <= $pages; $i++ ) {
                echo "\t<sitemap>\n\t\t<loc>" . esc_url( home_url( "/{$tax}-sitemap-{$i}.xml" ) ) . "</loc>\n" . hodima_sitemap_lastmod_tag( $tax_newest, "\t\t" ) . "\t</sitemap>\n";
            }
        }
        echo '</sitemapindex>';
    } 
    // ==========================================
    // پردازش نوشته‌ها / محصولات / ویدیوها
    // ==========================================
    elseif ( in_array( $sitemap_type, $enabled_post_types, true ) ) {
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1" xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">' . "\n";
        
        $query_args = [
            'post_type'      => $sitemap_type, 
            'post_status'    => 'publish', 
            'has_password'   => false, 
            'posts_per_page' => $limit, 
            'paged'          => $paged, 
            'fields'         => 'ids', 
            'orderby'        => 'modified', 
            'order'          => 'DESC'
        ];
        if (!empty($noindex_post_ids)) $query_args['post__not_in'] = $noindex_post_ids;
        
        $printed_urls = [];
        $posts_list = get_posts($query_args);

        // صفحه اصلی «آخرین نوشته‌ها»: برگه‌ای ندارد و قبلا در هیچ سایت‌مپی نبود
        if ( 'page' === $sitemap_type && 1 === $paged && 'posts' === get_option( 'show_on_front' ) ) {
            $home_newest = hodima_sitemap_newest_post( [ 'post_type' => 'post', 'post__not_in' => $noindex_post_ids ] );
            echo "\t<url>\n\t\t<loc>" . esc_url( home_url( '/' ) ) . "</loc>\n" . hodima_sitemap_lastmod_tag( $home_newest, "\t\t" ) . "\t</url>\n";
            $printed_urls[ home_url( '/' ) ] = true;
        }

        // صفحه‌ای بیرون از بازه (post-sitemap-99.xml) → ۴۰۴، نه urlset خالی
        if ( empty( $posts_list ) && $paged > 1 ) {
            ob_end_clean();
            hodima_sitemap_not_found();
            return;
        }

        if (!empty($posts_list)) {
            update_meta_cache( 'post', $posts_list ); // _seobox_canonical همه در یک کوئری
            foreach ( $posts_list as $pid ) {
                $url = get_permalink($pid);
                if ( !$url || isset( $printed_urls[ $url ] ) ) continue;
                if ( hodima_sitemap_canonical_elsewhere( (string) $url, (string) get_post_meta( $pid, '_seobox_canonical', true ) ) ) continue;
                $printed_urls[ $url ] = true;

                // آپدیت مهم برای جلوگیری از تاریخ شمسی
                echo "\t<url>\n\t\t<loc>" . esc_url($url) . "</loc>\n\t\t<lastmod>" . esc_html(get_post_modified_time('c', false, $pid, false)) . "</lastmod>\n";

                $radar = hodima_sitemap_deep_radar($pid, 'post');

                foreach ( $radar['images'] as $img_url ) {
                    echo "\t\t<image:image>\n\t\t\t<image:loc>" . esc_url($img_url) . "</image:loc>\n\t\t</image:image>\n";
                }

                foreach ( $radar['videos'] as $vid ) {
                    $v_thumb = $vid['thumb'] ?: (!empty($radar['images']) ? reset($radar['images']) : $fallback_logo);
                    [ $loc_tag, $loc_url ] = hodima_sitemap_video_loc( (string) $vid['url'] );
                    
                    $raw_content = get_post_field('post_excerpt', $pid) ?: get_post_field('post_content', $pid);
                    $v_desc = wp_trim_words( wp_strip_all_tags( strip_shortcodes($raw_content) ), 30, '...' );
                    if(empty($v_desc)) $v_desc = 'ویدیوی معرفی محصول ' . $vid['title'];
                    
                    // آپدیت مهم برای جلوگیری از تاریخ شمسی
                    $v_date = !empty($vid['date']) ? $vid['date'] : get_post_modified_time('c', false, $pid, false);

                    echo "\t\t<video:video>\n";
                    echo "\t\t\t<video:thumbnail_loc>" . esc_url($v_thumb) . "</video:thumbnail_loc>\n";
                    echo "\t\t\t<video:title><![CDATA[" . hodima_sitemap_cdata( $vid['title'] ) . "]]></video:title>\n";
                    echo "\t\t\t<video:description><![CDATA[" . hodima_sitemap_cdata( trim($v_desc) ) . "]]></video:description>\n";
                    echo "\t\t\t<{$loc_tag}>" . esc_url($loc_url) . "</{$loc_tag}>\n";
                    echo "\t\t\t<video:publication_date>" . esc_html($v_date) . "</video:publication_date>\n";
                    if ( $vid['duration'] > 0 ) echo "\t\t\t<video:duration>" . esc_html($vid['duration']) . "</video:duration>\n";
                    echo "\t\t\t<video:family_friendly>yes</video:family_friendly>\n";
                    echo "\t\t\t<video:live>no</video:live>\n";
                    echo "\t\t</video:video>\n";
                }
                echo "\t</url>\n";
            }
        }
        echo '</urlset>';
    }
    // ==========================================
    // پردازش دسته‌بندی‌ها و Taxonomyها
    // ==========================================
    elseif ( in_array( $sitemap_type, $enabled_taxonomies, true ) ) {
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1" xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">' . "\n";
        
        $terms = get_terms(['taxonomy' => $sitemap_type, 'hide_empty' => true, 'number' => $limit, 'offset' => ($paged - 1) * $limit]);
        $printed_urls = [];

        if ( ( is_wp_error( $terms ) || empty( $terms ) ) && $paged > 1 ) {
            ob_end_clean();
            hodima_sitemap_not_found();
            return;
        }

        if ( ! is_wp_error($terms) && !empty($terms) ) {
            foreach ( $terms as $term ) {
                if ( hodima_is_term_noindex( $term->term_id ) ) continue;

                $url = get_term_link($term);
                if ( is_wp_error($url) || isset( $printed_urls[ $url ] ) ) continue;
                if ( hodima_sitemap_canonical_elsewhere( (string) $url, (string) get_term_meta( $term->term_id, '_seobox_canonical', true ) ) ) continue;
                $printed_urls[ $url ] = true;

                echo "\t<url>\n\t\t<loc>" . esc_url($url) . "</loc>\n";

                // بهینه‌سازی: با fields=>ids کوئری همچنان به‌صورت پیش‌فرض کش متا و کش
                // ترم را برای نتیجه گرم می‌کند که اینجا اصلاً استفاده نمی‌شود؛ خاموش
                // کردن آن‌ها سربار هر تک‌کوئری را (که به ازای هر ترم این حلقه اجرا
                // می‌شود) کاهش می‌دهد. نتیجه‌ی نهایی هم پشت کش ۱۲ ساعته‌ی کل صفحه است.
                $latest_post = get_posts([
                    'post_type'               => 'any',
                    'tax_query'               => [[ 'taxonomy' => $sitemap_type, 'field' => 'term_id', 'terms' => $term->term_id ]],
                    'posts_per_page'          => 1,
                    'orderby'                 => 'modified',
                    'order'                   => 'DESC',
                    'fields'                  => 'ids',
                    'no_found_rows'           => true,
                    'update_post_meta_cache'  => false,
                    'update_post_term_cache'  => false,
                ]);
                
                // ترم بدون محتوای منتشرشده تاریخ واقعی ندارد؛ lastmod چاپ نمی‌شود
                // (قبلا «همین الان» چاپ می‌شد که در هر بار ساخت سایت‌مپ عوض می‌شد).
                $lastmod_date = ! empty( $latest_post ) ? (string) get_post_modified_time( 'c', false, $latest_post[0], false ) : '';
                if ( '' !== $lastmod_date ) {
                    echo "\t\t<lastmod>" . esc_html( $lastmod_date ) . "</lastmod>\n";
                }

                $radar = hodima_sitemap_deep_radar($term->term_id, 'term');

                foreach ( $radar['images'] as $img_url ) {
                    echo "\t\t<image:image>\n\t\t\t<image:loc>" . esc_url($img_url) . "</image:loc>\n\t\t</image:image>\n";
                }

                foreach ( $radar['videos'] as $vid ) {
                    $v_thumb = $vid['thumb'] ?: (!empty($radar['images']) ? reset($radar['images']) : $fallback_logo);
                    [ $loc_tag, $loc_url ] = hodima_sitemap_video_loc( (string) $vid['url'] );
                    
                    $v_desc = wp_trim_words( wp_strip_all_tags( strip_shortcodes($term->description) ), 30, '...' );
                    if(empty($v_desc)) $v_desc = 'ویدیو دسته‌بندی ' . $vid['title'];
                    
                    // بدون تاریخ ثبت‌شده، بدون publication_date (قبلا تاریخ آخرین محصول دسته
                    // که با هر ویرایش عوض می‌شد)
                    $v_date = (string) ( $vid['date'] ?? '' );

                    echo "\t\t<video:video>\n";
                    echo "\t\t\t<video:thumbnail_loc>" . esc_url($v_thumb) . "</video:thumbnail_loc>\n";
                    echo "\t\t\t<video:title><![CDATA[" . hodima_sitemap_cdata( $vid['title'] ) . "]]></video:title>\n";
                    echo "\t\t\t<video:description><![CDATA[" . hodima_sitemap_cdata( trim($v_desc) ) . "]]></video:description>\n";
                    echo "\t\t\t<{$loc_tag}>" . esc_url($loc_url) . "</{$loc_tag}>\n";
                    if ( '' !== (string) $v_date ) echo "\t\t\t<video:publication_date>" . esc_html($v_date) . "</video:publication_date>\n"; // اختیاری در استاندارد
                    if ( $vid['duration'] > 0 ) echo "\t\t\t<video:duration>" . esc_html($vid['duration']) . "</video:duration>\n";
                    echo "\t\t\t<video:family_friendly>yes</video:family_friendly>\n";
                    echo "\t\t\t<video:live>no</video:live>\n";
                    echo "\t\t</video:video>\n";
                }
                echo "\t</url>\n";
            }
        }
        echo '</urlset>';
    }

    $final_xml = ob_get_clean();
    set_transient( $cache_key, $final_xml, 12 * HOUR_IN_SECONDS );
    echo $final_xml;
    exit;
}

// ==========================================
// ۴. باطل کردن کش هنگام تغییرات نوشته و ترم‌ها
// ==========================================
add_action('save_post', 'hodima_sitemap_clear_cache');
// حذف/بازگردانی از زباله‌دان هم فهرست را عوض می‌کند (قبلا کش ۱۲ ساعته می‌ماند)
add_action('trashed_post', 'hodima_sitemap_clear_cache');
add_action('untrashed_post', 'hodima_sitemap_clear_cache');
add_action('deleted_post', 'hodima_sitemap_clear_cache');
add_action('edit_term', 'hodima_sitemap_clear_cache');
add_action('delete_term', 'hodima_sitemap_clear_cache');
add_action('created_term', 'hodima_sitemap_clear_cache');

function hodima_sitemap_clear_cache() {
    update_option('hodima_sitemap_cache_ver', time(), false);
}

/** ۴۰۴ واقعی قالب برای آدرس سایت‌مپ نامعتبر. */
function hodima_sitemap_not_found(): void {
    global $wp_query;
    header_remove( 'Content-Type' );
    header_remove( 'X-Robots-Tag' );
    $wp_query->set_404();
    status_header( 404 );
    nocache_headers();
}// ==========================================
// ۵. lastmod واقعی برای ایندکس سایت‌مپ
// ==========================================

/**
 * شناسه تازه‌ترین نوشته منتشرشده با شرط‌های داده‌شده (مرتب بر اساس ویرایش).
 *
 * @param array<string, mixed> $args آرگومان‌های اضافه WP_Query.
 */
function hodima_sitemap_newest_post( array $args ): int {

	if ( empty( $args['post__not_in'] ) ) {
		unset( $args['post__not_in'] );
	}

	$ids = get_posts( [
		...$args,
		'post_status'            => 'publish',
		'has_password'           => false,
		'posts_per_page'         => 1,
		'orderby'                => 'modified',
		'order'                  => 'DESC',
		'fields'                 => 'ids',
		'no_found_rows'          => true,
		'update_post_meta_cache' => false,
		'update_post_term_cache' => false,
	] );

	return (int) ( $ids[0] ?? 0 );
}

/** تگ <lastmod>؛ بدون محتوا، رشته خالی (تاریخ ساختگی چاپ نمی‌شود). */
function hodima_sitemap_lastmod_tag( int $post_id, string $indent = '' ): string {

	if ( $post_id <= 0 ) {
		return '';
	}

	$date = (string) get_post_modified_time( 'c', false, $post_id, false );

	return '' === $date ? '' : $indent . '<lastmod>' . esc_html( $date ) . "</lastmod>\n";
}

// ==========================================
// ۶. یک سایت‌مپ، نه دو تا
// ==========================================
// وقتی سایت‌مپ قالب فعال است، سایت‌مپ داخلی وردپرس (wp-sitemap.xml) هم
// همزمان فعال بود: دو فهرست موازی با قواعد متفاوت (مثلا بدون فیلتر noindex
// و بدون تصاویر). سایت‌مپ داخلی خاموش و آدرس‌های قدیمی‌اش — که ممکن است
// در Search Console ثبت شده باشند — با ۳۰۱ به سایت‌مپ قالب هدایت می‌شوند.

add_filter( 'wp_sitemaps_enabled', static function ( bool $enabled ): bool {
	return '1' === (string) get_option( 'hodima_sitemap_status', '1' ) ? false : $enabled;
} );

add_action( 'template_redirect', static function (): void {

	if ( '1' !== (string) get_option( 'hodima_sitemap_status', '1' ) ) {
		return;
	}

	$path = (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH );

	if ( preg_match( '#/wp-sitemap(?:-[a-z0-9_-]+)?\.(?:xml|xsl)$#i', $path ) ) {
		wp_safe_redirect( home_url( '/sitemap.xml' ), 301, 'Hodima Sitemap' );
		exit;
	}
}, 0 );
