<?php
/**
 * Video Watch — admin meta box
 * Path: components/video-watch/meta-boxes.php
 * Version: 2.0.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

function hod_video_watch_register_meta_boxes(): void
{
    add_meta_box('hod-video-watch-meta', 'تنظیمات پیشرفته ویدئو (سئو و فروش)', 'hod_video_watch_render_meta_box', 'video', 'normal', 'high');
}
add_action('add_meta_boxes', 'hod_video_watch_register_meta_boxes');

/** ISO 8601 → «۲:۳۵» برای نمایش در فرم. */
function hod_video_iso_to_clock(string $iso): string
{
    if (!preg_match('/^PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?$/', $iso, $m)) {
        return $iso;
    }
    $h = (int) ($m[1] ?? 0);
    $i = (int) ($m[2] ?? 0);
    $s = (int) ($m[3] ?? 0);
    return $h > 0 ? sprintf('%d:%02d:%02d', $h, $i, $s) : sprintf('%d:%02d', $i, $s);
}

/**
 * «۲:۳۵» یا «PT2M35S» → ISO 8601، یا رشته خالی.
 * نسخه قبلی فقط ISO خام را می‌پذیرفت و هر چیز دیگری را بی‌صدا دور
 * می‌ریخت؛ کسی «PT0H2M35S» تایپ نمی‌کند.
 */
function hod_video_sanitize_duration(string $value): string
{
    $value = trim($value);

    if ('' === $value) {
        return '';
    }

    if (function_exists('hook_format_duration_iso')) {
        return hook_format_duration_iso($value);
    }

    if (preg_match('/^PT(?:\d+H)?(?:\d+M)?(?:\d+S)?$/i', $value) && strlen($value) > 2) {
        return strtoupper($value);
    }

    $seconds = function_exists('hod_video_time_to_seconds') ? hod_video_time_to_seconds($value) : null;
    return null === $seconds ? '' : sprintf('PT%dH%dM%dS', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
}

function hod_video_watch_render_meta_box(WP_Post $post): void
{
    wp_nonce_field('hod_video_watch_save_meta', 'hod_video_watch_meta_nonce');

    $video_url           = (string) get_post_meta($post->ID, '_hod_video_url', true);
    $video_thumbnail     = (string) get_post_meta($post->ID, '_hod_video_thumbnail', true);
    $video_duration      = hod_video_iso_to_clock((string) get_post_meta($post->ID, '_hod_video_duration', true));
    $chapters            = (string) get_post_meta($post->ID, '_hod_video_chapters', true);
    $transcript          = (string) get_post_meta($post->ID, '_hod_video_transcript', true);
    $related_product_url = (string) get_post_meta($post->ID, '_hod_related_product_url', true);
    $related_links       = get_post_meta($post->ID, '_hod_related_links', true) ?: [];
    ?>
    <div class="hvw-metabox">
        <div class="hvw-card">

            <h3>رسانه و استریم</h3>
            <div class="hvw-grid">
                <div class="hvw-field hvw-field--full">
                    <label class="hvw-label" for="hod_video_url">آدرس ویدئو (فرمت MP4)</label>
                    <input type="url" id="hod_video_url" name="hod_video_url" class="hvw-input" dir="ltr" value="<?php echo esc_attr($video_url); ?>">
                </div>
                <div class="hvw-field hvw-field--full">
                    <label class="hvw-label" for="hod_video_thumbnail">کاور ویدئو (Thumbnail)</label>
                    <div class="hvw-inline">
                        <input type="url" id="hod_video_thumbnail" name="hod_video_thumbnail" class="hvw-input" dir="ltr" value="<?php echo esc_attr($video_thumbnail); ?>">
                        <button type="button" class="button hvw-btn-action hvw-media-upload-btn">انتخاب عکس</button>
                    </div>
                </div>
            </div>

            <?php /* بخش «صدای چندزبانه» (انگلیسی و عربی) حذف شد. */ ?>
            <h3>سئو ویدئو</h3>
            <div class="hvw-grid">
                <div class="hvw-field hvw-field--full">
                    <label class="hvw-label" for="hod_video_duration">مدت زمان</label>
                    <input type="text" id="hod_video_duration" name="hod_video_duration" class="hvw-input hvw-input--short" dir="ltr" inputmode="numeric" value="<?php echo esc_attr($video_duration); ?>" placeholder="2:35">
                    <p class="description">به صورت دقیقه:ثانیه (مثلا ۲:۳۵) یا ساعت:دقیقه:ثانیه (۱:۰۵:۲۰).</p>
                </div>
                <div class="hvw-field hvw-field--full">
                    <label class="hvw-label" for="hod_video_chapters">بخش‌بندی ویدئو (Chapters / لحظه‌های کلیدی گوگل)</label>
                    <textarea id="hod_video_chapters" name="hod_video_chapters" class="hvw-textarea" placeholder="0:00 مقدمه&#10;1:25 معرفی رنگ‌بندی&#10;3:10 نحوه سفارش عمده"><?php echo esc_textarea($chapters); ?></textarea>
                    <p class="description">هر بخش در یک خط: زمان شروع و سپس عنوان. گوگل این‌ها را به صورت «لحظه‌های کلیدی» زیر ویدئو در نتایج جستجو نشان می‌دهد و هر کدام مستقیم به همان ثانیه لینک می‌شود.</p>
                </div>
                <div class="hvw-field hvw-field--full">
                    <label class="hvw-label" for="hod_video_transcript">متن کامل ویدئو (Transcript برای گوگل)</label>
                    <textarea id="hod_video_transcript" name="hod_video_transcript" class="hvw-textarea hvw-textarea--tall"><?php echo esc_textarea($transcript); ?></textarea>
                </div>
            </div>

            <h3>لینک مشاهده محصول</h3>
            <div class="hvw-grid">
                <div class="hvw-field hvw-field--full">
                    <label class="hvw-label" for="hod_related_product_url">لینک دکمه «مشاهده محصول»</label>
                    <input type="url" id="hod_related_product_url" name="hod_related_product_url" class="hvw-input" dir="ltr" value="<?php echo esc_attr($related_product_url); ?>">
                    <p class="description">اگر خالی بماند، دکمه محصول نمایش داده نمی‌شود و بقیه دکمه‌ها جایش را پر می‌کنند.</p>
                </div>
            </div>

            <h4 class="hvw-subtitle">محصولات/لینک‌های مرتبط (نمایش داینامیک همراه با عکس کاور)</h4>
            <div id="hvw-links-repeater-container">
                <div class="hvw-repeater-rows">
                    <?php if (!empty($related_links) && is_array($related_links)) :
                        foreach (array_values($related_links) as $index => $link) : ?>
                            <div class="hvw-repeater-row">
                                <div class="hvw-field hvw-flex-1">
                                    <label class="hvw-label">عنوان لینک</label>
                                    <input type="text" name="hod_related_links[<?php echo (int) $index; ?>][title]" class="hvw-input" value="<?php echo esc_attr((string) ($link['title'] ?? '')); ?>">
                                </div>
                                <div class="hvw-field hvw-flex-1">
                                    <label class="hvw-label">آدرس لینک</label>
                                    <input type="url" name="hod_related_links[<?php echo (int) $index; ?>][url]" class="hvw-input" dir="ltr" value="<?php echo esc_url((string) ($link['url'] ?? '')); ?>">
                                </div>
                                <div class="hvw-field hvw-flex-15">
                                    <label class="hvw-label">آدرس عکس کاور</label>
                                    <div class="hvw-inline">
                                        <input type="url" name="hod_related_links[<?php echo (int) $index; ?>][cover]" class="hvw-input" dir="ltr" value="<?php echo esc_url((string) ($link['cover'] ?? '')); ?>">
                                        <button type="button" class="button hvw-btn-action hvw-media-upload-btn">انتخاب</button>
                                    </div>
                                </div>
                                <button type="button" class="hvw-remove-row" aria-label="حذف"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
                            </div>
                        <?php endforeach;
                    endif; ?>
                </div>
                <button type="button" id="hvw-add-link-btn" class="button hvw-btn-action hvw-add-btn">+ افزودن لینک جدید</button>
            </div>
        </div>
    </div>
    <?php
}

function hod_video_watch_save_meta_boxes(int $post_id): void
{
    if (!isset($_POST['hod_video_watch_meta_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['hod_video_watch_meta_nonce'])), 'hod_video_watch_save_meta')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    // بازنگری‌ها شناسه جدا دارند؛ نسخه قبلی متا را روی آن‌ها هم می‌نوشت
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return;
    if ('video' !== get_post_type($post_id)) return;
    if (!current_user_can('edit_post', $post_id)) return;

    $post = static fn(string $key): string => isset($_POST[$key]) ? (string) wp_unslash($_POST[$key]) : '';

    $fields = [
        '_hod_video_url'           => esc_url_raw(trim($post('hod_video_url')), ['http', 'https']),
        '_hod_video_thumbnail'     => esc_url_raw(trim($post('hod_video_thumbnail')), ['http', 'https']),
        '_hod_video_duration'      => hod_video_sanitize_duration($post('hod_video_duration')),
        '_hod_video_chapters'      => sanitize_textarea_field($post('hod_video_chapters')),
        '_hod_video_transcript'    => sanitize_textarea_field($post('hod_video_transcript')),
        '_hod_related_product_url' => esc_url_raw(trim($post('hod_related_product_url')), ['http', 'https']),
    ];

    foreach ($fields as $meta_key => $meta_value) {
        ('' === $meta_value || '0' === $meta_value)
            ? delete_post_meta($post_id, $meta_key)
            : update_post_meta($post_id, $meta_key, $meta_value);
    }

    // صدای انگلیسی و عربی حذف شد؛ داده باقی‌مانده این ویدئو پاک شود
    delete_post_meta($post_id, '_hod_audio_url_en');
    delete_post_meta($post_id, '_hod_audio_url_ar');

    $links = [];
    foreach ((array) ($_POST['hod_related_links'] ?? []) as $link) {
        if (!is_array($link) || count($links) >= 99) {
            continue;
        }
        // wp_unslash: بدون آن عنوانی که کوتیشن داشت با بک‌اسلش ذخیره می‌شد
        $link  = wp_unslash($link);
        $title = sanitize_text_field((string) ($link['title'] ?? ''));
        $url   = esc_url_raw(trim((string) ($link['url'] ?? '')), ['http', 'https']);
        if ('' !== $title && '' !== $url) {
            $links[] = [
                'title' => $title,
                'url'   => $url,
                'cover' => esc_url_raw(trim((string) ($link['cover'] ?? '')), ['http', 'https']),
            ];
        }
    }

    empty($links) ? delete_post_meta($post_id, '_hod_related_links') : update_post_meta($post_id, '_hod_related_links', $links);

    delete_transient('hvw_total_views_calc_' . $post_id);
}
add_action('save_post', 'hod_video_watch_save_meta_boxes');

/**
 * پاکسازی یک‌باره داده صدای انگلیسی و عربی از همه ویدئوها.
 */
add_action('admin_init', static function (): void {
    if (get_option('hod_video_audio_meta_purged')) {
        return;
    }
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('_hod_audio_url_en', '_hod_audio_url_ar')");
    update_option('hod_video_audio_meta_purged', 1, false);
});
