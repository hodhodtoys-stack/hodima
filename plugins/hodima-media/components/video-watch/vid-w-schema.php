<?php
/**
 * Video Watch — JSON-LD
 * Path: components/video-watch/vid-w-schema.php
 * Version: 2.0.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) exit;

/**
 * آدرس پایه شناسه‌ها — همان موتور canonical که گراف اصلی
 * (schema/homepage-schema.php) برای «#webpage» استفاده می‌کند. نسخه قبلی
 * get_permalink() را به کار می‌برد؛ با canonical دستی، ارجاع‌ها به نودی
 * می‌رسیدند که وجود نداشت.
 */
function hod_video_schema_page_url(int $post_id): string {
    $canonical = function_exists('hodima_get_canonical_url') ? hodima_get_canonical_url() : '';
    return '' !== $canonical ? $canonical : (string) get_permalink($post_id);
}

/**
 * صفحه فهرست ویدئوها: برگه‌ای که قالب template-page-videos.php دارد
 * (در این سایت «مدیا»)، یا null.
 *
 * باگ «لینک مدیا گاهی ارور می‌دهد»: نسخه قبلی نتیجه را یک روز کش
 * می‌کرد — *حتی وقتی برگه پیدا نشده بود*. در آن حالت آدرس آرشیو نوع پست
 * (/videos/) کش می‌شد و تا یک روز همه لینک‌ها به آن می‌رفتند؛ آدرسی که
 * بسته به قوانین روتر قالب می‌تواند خطا بدهد. حالا فقط شناسه برگه کش
 * می‌شود و هر بار با وضعیت واقعی برگه بررسی می‌شود.
 */
function hod_video_listing_page(): ?WP_Post {

    $id = (int) get_transient('hvw_videos_page_id');

    if (!$id) {
        $pages = get_posts([
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'meta_key'       => '_wp_page_template',
            'meta_value'     => 'template-page-videos.php',
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ]);
        $id = (int) ($pages[0] ?? 0);
        if ($id) {
            set_transient('hvw_videos_page_id', $id, WEEK_IN_SECONDS);
        }
    }

    $page = $id ? get_post($id) : null;

    if (!($page instanceof WP_Post) || 'publish' !== $page->post_status) {
        delete_transient('hvw_videos_page_id');
        return null;
    }

    return $page;
}

/** آدرس صفحه فهرست ویدئوها. */
function hod_video_archive_url(): string {
    $page = hod_video_listing_page();
    return $page ? (string) get_permalink($page) : (string) get_post_type_archive_link('video');
}

/** عنوان همان صفحه (مثلا «مدیا») — در مسیر راهنما همین نمایش داده می‌شود. */
function hod_video_archive_label(): string {
    $page = hod_video_listing_page();
    return $page ? (string) get_the_title($page) : __('ویدئوها', 'hod-video');
}

// هر تغییری در برگه‌ها یا قالبشان → جستجوی دوباره
add_action('save_post_page', static function (): void { delete_transient('hvw_videos_page_id'); });
add_action('trashed_post', static function (): void { delete_transient('hvw_videos_page_id'); });
add_action('updated_post_meta', static function ($meta_id, $post_id, $key): void {
    if ('_wp_page_template' === $key) delete_transient('hvw_videos_page_id');
}, 10, 3);

/* =====================================================================
 * صفحه‌بندی برگه فهرست: /video/page/2/ (مثل دسته‌بندی‌ها)
 * ---------------------------------------------------------------------
 * پیشوند /video/ مال نوع پست ویدئو هم هست؛ قانون وردپرس برای نوع پست
 * /video/page/2/ را «ویدئویی با نامک page» می‌خواند. یک قانون اختصاصی با
 * اولویت top پیش از آن تطبیق داده می‌شود و مستقیم به همان برگه با
 * paged=N می‌رسد. قانون از روی آدرس واقعی برگه ساخته می‌شود و اگر آن
 * عوض شود، قوانین بازنویسی یک بار خودکار به‌روز می‌شوند.
 * ===================================================================== */
add_action('init', static function (): void {

    $page = hod_video_listing_page();
    if (!$page) {
        return;
    }

    $path  = trim((string) get_page_uri($page), '/');
    $regex = '^' . preg_quote($path, '#') . '/page/?([0-9]{1,})/?$';
    add_rewrite_rule($regex, 'index.php?page_id=' . (int) $page->ID . '&paged=$matches[1]', 'top');

    // فقط وقتی قانون تغییر کرده، یک بار (نه در هر درخواست)
    $sig = md5($regex . '|' . $page->ID);
    if (get_option('hvw_rewrite_sig') !== $sig) {
        update_option('hvw_rewrite_sig', $sig, true);
        add_action('wp_loaded', static function (): void { flush_rewrite_rules(false); });
    }
}, 20);

/** وردپرس نباید /page/N/ را از برگه فهرست حذف کند. */
add_filter('redirect_canonical', static function ($redirect, $requested) {
    $page = hod_video_listing_page();
    return ($page && is_page($page->ID) && (int) get_query_var('paged') > 1) ? false : $redirect;
}, 20, 2);


/*
 * ریدایرکت‌ها در کد نیستند و از بخش «ریدایرکت‌ها» پیشخوان مدیریت می‌شوند
 * (/videos/ ← برگه فهرست، در صورت نیاز).
 */

/**
 * «۱:۲۵» یا «۱:۰۲:۱۰» → ثانیه، یا null.
 */
function hod_video_time_to_seconds(string $value): ?int {

    $value = strtr(trim($value), ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9']);

    if (!preg_match('/^(?:(\d{1,2}):)?(\d{1,3}):(\d{2})$/', $value, $m)) {
        return null;
    }

    return ((int) $m[1]) * 3600 + ((int) $m[2]) * 60 + (int) $m[3];
}

/**
 * بخش‌بندی ویدیو: هر خط «زمان عنوان»، مثلا «۱:۲۵ معرفی رنگ‌بندی».
 *
 * @return array<int, array{start:int, name:string}>
 */
function hod_video_parse_chapters(string $text): array {

    $out = [];

    foreach (preg_split('/\R/u', $text) ?: [] as $line) {
        $line = trim($line);
        if ('' === $line || !preg_match('/^([۰-۹0-9:]+)\s*[-–—|:]?\s*(.+)$/u', $line, $m)) {
            continue;
        }
        $start = hod_video_time_to_seconds($m[1]);
        $name  = trim(wp_strip_all_tags($m[2]));
        if (null !== $start && '' !== $name) {
            $out[$start] = ['start' => $start, 'name' => $name];
        }
    }

    ksort($out);
    return array_values($out);
}

/* =====================================================================
 * مسیر راهنما: «خانه › ویدئوها › عنوان»
 * ---------------------------------------------------------------------
 * صفحات ویدیو در شاخه عمومی breadcrumb-schema.php فقط «خانه › عنوان»
 * می‌گرفتند. پله «ویدئوها» اینجا از طریق فیلتر همان ماژول اضافه می‌شود،
 * نه با یک BreadcrumbList موازی (که قبلا دو نود با شناسه یکسان می‌ساخت).
 * ===================================================================== */
add_filter('hodima_breadcrumb_items', static function (array $items): array {

    if (!is_singular('video') || count($items) < 2) {
        return $items;
    }

    $archive = hod_video_archive_url();
    if ('' === $archive) {
        return $items;
    }

    foreach ($items as $item) {
        if (isset($item['item']) && untrailingslashit((string) $item['item']) === untrailingslashit($archive)) {
            return $items; // از قبل هست
        }
    }

    $last = array_pop($items);
    $items[] = [
        '@type'    => 'ListItem',
        'position' => 0,
        'name'     => hod_video_archive_label(),
        'item'     => esc_url_raw($archive),
    ];
    $items[] = $last;

    foreach ($items as $i => &$item) {
        $item['position'] = $i + 1;
    }
    unset($item);

    return $items;
});

/* =====================================================================
 * نود صفحه: ویدیو موضوع اصلی صفحه ویدیو است
 * ===================================================================== */
add_filter('hodima_schema_webpage_node', static function (array $node, string $page_url): array {
    if (is_singular('video') && '' !== (string) get_post_meta((int) get_queried_object_id(), '_hod_video_url', true)) {
        $node['mainEntity'] = ['@id' => $page_url . '#video'];
    }
    return $node;
}, 10, 2);

/* =====================================================================
 * VideoObject
 * ===================================================================== */
add_action('wp_head', static function (): void {

    if (!is_singular('video')) return;

    $post_id = (int) get_queried_object_id();

    $raw_video_url = (string) get_post_meta($post_id, '_hod_video_url', true);
    if ('' === $raw_video_url) return;

    $page_url  = hod_video_schema_page_url($post_id);
    $video_url = esc_url_raw((string) preg_replace('/\s+/', '%20', trim($raw_video_url)));

    /*
     * تصویر ویدیو (الزامی). نسخه قبلی در نبود کاور به
     * «/wp-content/uploads/default-video.jpg» برمی‌گشت که احتمالا وجود
     * ندارد — یعنی thumbnailUrl به یک ۴۰۴. حالا اگر تصویری نیست، نود
     * چاپ نمی‌شود (VideoObject بدون تصویر برای گوگل نامعتبر است).
     */
    $raw_thumb = (string) (get_post_meta($post_id, '_hod_video_thumbnail', true) ?: get_the_post_thumbnail_url($post_id, 'full'));
    if ('' === $raw_thumb) return;
    $video_thumb = esc_url_raw((string) preg_replace('/\s+/', '%20', trim($raw_thumb)));

    $views      = class_exists('Video_Watch_Analytics') ? Video_Watch_Analytics::get_total_views($post_id) : 0;
    $transcript = (string) get_post_meta($post_id, '_hod_video_transcript', true);
    $duration   = (string) get_post_meta($post_id, '_hod_video_duration', true);
    $product    = (string) get_post_meta($post_id, '_hod_related_product_url', true);
    $chapters   = hod_video_parse_chapters((string) get_post_meta($post_id, '_hod_video_chapters', true));

    // مدت به ثانیه (پایان آخرین فصل)
    $total = ('' !== $duration && preg_match('/^PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?$/', $duration, $d))
        ? ((int) ($d[1] ?? 0)) * 3600 + ((int) ($d[2] ?? 0)) * 60 + (int) ($d[3] ?? 0)
        : 0;

    $tags     = get_the_tags($post_id);
    $keywords = $tags ? implode(', ', wp_list_pluck($tags, 'name')) : '';

    // تاریخ به وقت گرینویچ. نسخه قبلی post_date *محلی* را با date() قالب‌بندی
    // می‌کرد که منطقه زمانی +00:00 می‌چسباند — ۳.۵ ساعت اختلاف.
    $published = (string) get_post_time('c', true, $post_id);
    $modified  = (string) get_post_modified_time('c', true, $post_id);

    /*
     * سازنده واحد VideoObject (inc/video-object.php) — همان که ویدیوی
     * نوشته/محصول/دسته را می‌سازد. نکته‌های قبلی همین فایل حفظ شده‌اند:
     *   - فقط contentUrl: embedUrl باید پلیر *قابل‌جاسازی* باشد، نه خود صفحه تماشا.
     *   - تاریخ‌ها به وقت گرینویچ (get_post_time با gmt).
     *   - ناشر = همان «…/#organization» گراف اصلی (با اسلش؛ نسخه خیلی قدیمی
     *     بدون اسلش یک سازمان تکراری می‌ساخت).
     *   - بخش‌بندی ← Clip؛ بدون آن SeekToAction (پلیر صفحه ?t= را اجرا می‌کند).
     */
    $mime = (string) (wp_check_filetype((string) wp_parse_url($video_url, PHP_URL_PATH), wp_get_mime_types())['type'] ?: 'video/mp4');

    $schema = hodima_media_video_object([
        'base'          => $page_url,
        'page_url'      => $page_url,
        'name'          => get_the_title($post_id),
        'description'   => wp_strip_all_tags((string) (get_the_excerpt($post_id) ?: get_the_title($post_id))),
        'thumbnails'    => [$video_thumb],
        'upload_date'   => $published,
        'date_modified' => $modified,
        'content_url'   => $video_url,
        'mime'          => $mime,
        'duration'      => $duration,
        'seconds'       => $total,
        'transcript'    => $transcript,
        'keywords'      => $keywords,
        'chapters'      => array_map(static fn(array $c): array => ['start' => $c['start'], 'title' => $c['name']], $chapters),
        'seekable'      => true,
        'views'         => $views,
        'publisher'     => true,
    ]);

    $schema['mainEntityOfPage'] = ['@id' => $page_url . '#webpage'];
    $schema['datePublished']    = $published;
    $schema['isFamilyFriendly'] = true;

    if ('' !== trim($transcript)) {
        $schema['accessibilityFeature'] = ['transcript'];
    }

    /*
     * محصول مرتبط: فقط ارجاع.
     * نسخه قبلی یک Product با فقط url می‌ساخت. اعتبارسنج محصول گوگل
     * Productهای تودرتو را هم بررسی می‌کند و بدون name و offers در سرچ
     * کنسول خطا گزارش می‌شد. نود کامل محصول روی صفحه خود محصول با همین
     * شناسه «#product» وجود دارد (product-schema-pro.php).
     */
    if ('' !== $product) {
        $schema['about'] = ['@id' => trailingslashit(esc_url_raw($product)) . '#product'];
    }

    hodima_schema_add($schema, 'hodima-media: video-watch');
}, 20);
