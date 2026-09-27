<?php
/**
 * Admin View: Podcast RSS Feed Settings
 * توضیح: این فایل قبلاً به اشتباه نسخه‌ی تکراری از تنظیمات محصول (view-product.php)
 * بود و منوی «فید پادکست» عملاً هیچ رابط تنظیماتی واقعی نداشت. این نسخه اصلاح‌شده
 * دقیقاً همان کلیدهای گزینه‌ای را می‌خواند/می‌نویسد که podcast-feed-core.php مصرف می‌کند.
 */

if (!defined('ABSPATH')) exit;
// دفاع در عمق: علاوه بر گیت‌وی متد add_submenu_page، دسترسی هم اینجا مستقیماً بررسی می‌شود
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'شما به این بخش دسترسی ندارید.' ) );
}


$message = '';

// بررسی ارسال فرم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hodima_podcast_schema_nonce'])) {
    if (wp_verify_nonce($_POST['hodima_podcast_schema_nonce'], 'hodima_save_podcast_schema')) {

        update_option('hodima_podcast_status', isset($_POST['hodima_podcast_status']) ? '1' : '0');
        update_option('hodima_podcast_title', isset($_POST['hodima_podcast_title']) ? sanitize_text_field( wp_unslash( $_POST['hodima_podcast_title'] ) ) : '');
        update_option('hodima_podcast_desc', isset($_POST['hodima_podcast_desc']) ? sanitize_textarea_field( wp_unslash( $_POST['hodima_podcast_desc'] ) ) : '');
        update_option('hodima_podcast_author', isset($_POST['hodima_podcast_author']) ? sanitize_text_field( wp_unslash( $_POST['hodima_podcast_author'] ) ) : '');
        update_option('hodima_podcast_category', isset($_POST['hodima_podcast_category']) ? sanitize_text_field( wp_unslash( $_POST['hodima_podcast_category'] ) ) : '');

        $explicit = isset($_POST['hodima_podcast_explicit']) ? sanitize_text_field( wp_unslash( $_POST['hodima_podcast_explicit'] ) ) : 'clean';
        update_option('hodima_podcast_explicit', in_array($explicit, ['clean', 'explicit'], true) ? $explicit : 'clean');

        // پست‌تایپ‌هایی که فید پادکست از آن‌ها فایل صوتی استخراج می‌کند
        $post_types = isset($_POST['hodima_podcast_post_types']) && is_array($_POST['hodima_podcast_post_types'])
            ? array_map('sanitize_text_field', wp_unslash( (array) $_POST['hodima_podcast_post_types'] ))
            : [];
        update_option('hodima_podcast_post_types', $post_types);

        // بسیار مهم: add_feed('podcast', ...) فقط تابع callback را ثبت می‌کند؛
        // خودِ rewrite rule واقعی «/feed/podcast/» تا زمانی که پرمالینک‌ها
        // flush نشوند، در دیتابیس نوشته نمی‌شود. بدون این خط، وردپرس آدرس را
        // نمی‌شناسد و آن را با redirect_canonical() به صفحه اصلی هدایت می‌کند —
        // دقیقاً همان مشکلی که گزارش داده بودید.
        flush_rewrite_rules();

        $message = '<div class="notice notice-success is-dismissible" style="border-right: 4px solid #25316a; background: #fff;"><p style="font-weight: inherit;">تنظیمات فید پادکست با موفقیت ذخیره شد.</p></div>';
    }
}

// مقادیر فعلی (دقیقاً هم‌راستا با fallback های داخل podcast-feed-core.php)
$is_enabled   = get_option('hodima_podcast_status', '1');
$title        = get_option('hodima_podcast_title', get_bloginfo('name') . ' - پادکست');
$desc         = get_option('hodima_podcast_desc', get_bloginfo('description'));
$author       = get_option('hodima_podcast_author', 'بازرگانی هدهد');
$category     = get_option('hodima_podcast_category', 'Business');
$explicit     = get_option('hodima_podcast_explicit', 'clean');
$saved_types  = get_option('hodima_podcast_post_types', ['post', 'product']);

$all_post_types = get_post_types(['public' => true], 'objects');
$feed_url        = site_url('/feed/podcast/');
$permalink_structure = get_option('permalink_structure');

hodima_view_header(
    'تنظیمات فید پادکست (Apple & Google Podcast)',
    'مدیریت خروجی استاندارد RSS 2.0 برای فایل‌های صوتی متصل به نوشته‌ها یا محصولات، سازگار با اپل پادکست و گوگل پادکست.',
    '🎙️'
);
?>

<div class="h-card">
    <?php echo $message; ?>

    <?php if ( empty( $permalink_structure ) ) : ?>
        <!-- هشدار حیاتی: پرمالینک روی «Plain» است -->
        <div class="notice notice-error" style="border-right: 4px solid #dc2626; background: #fef2f2; padding: 15px; margin-bottom: 20px; border-left: none; border-top: none; border-bottom: none;">
            <h3 style="margin-top: 0; color: #b91c1c; font-size: 15px; font-weight: inherit;">⚠️ ساختار پیوند یکتای سایت روی «Plain» (ساده) تنظیم شده</h3>
            <p style="font-weight: inherit;">
                در این حالت، آدرس‌های زیبا مثل <code dir="ltr">/feed/podcast/</code> اصلاً توسط وردپرس شناخته نمی‌شوند و
                به صفحه اصلی هدایت می‌شوید — این دقیقاً همان چیزی است که تجربه می‌کنید، و ربطی به تنظیمات این افزونه ندارد.
            </p>
            <p style="font-weight: inherit;">
                راه‌حل: به <strong>Settings → Permalinks</strong> بروید، هر گزینه‌ای غیر از «Plain» را انتخاب کنید (مثلاً «Post name»)
                و «Save Changes» را بزنید. تا آن زمان، می‌توانید موقتاً از این آدرس جایگزین استفاده کنید:
                <br>
                <code style="direction: ltr; display:inline-block; margin-top:6px; background:#fff; padding:4px 8px; border-radius:4px; border:1px solid #fecaca;"><?php echo esc_html( site_url( '/?feed=podcast' ) ); ?></code>
            </p>
        </div>
    <?php endif; ?>

    <!-- نمایش آدرس فید -->
    <div class="notice notice-info" style="border-right: 4px solid #25316a; background: #fff; padding: 15px; margin-bottom: 20px; border-left: none; border-top: none; border-bottom: none;">
        <h3 style="margin-top: 0; color: #25316a; font-size: 15px; font-weight: inherit;">آدرس فید پادکست:</h3>
        <code style="font-size: 14px; padding: 6px 10px; display: inline-block; background: #f8fafc; border-radius: 4px; border: 1px solid #c1c9ec; color: #25316a; direction: ltr;">
            <a href="<?php echo esc_url($feed_url); ?>" target="_blank" style="text-decoration: none; color: inherit;"><?php echo esc_html($feed_url); ?></a>
        </code>
        <?php if ( ! empty( $permalink_structure ) ) : ?>
            <p class="description" style="margin-top: 10px;">
                اگر همچنان به صفحه اصلی هدایت می‌شوید، یک‌بار به Settings → Permalinks بروید و بدون تغییر چیزی «Save Changes» را بزنید
                (این کار قوانین بازنویسی آدرس را دوباره می‌سازد).
            </p>
        <?php endif; ?>
    </div>

    <form method="post" action="">
        <?php wp_nonce_field('hodima_save_podcast_schema', 'hodima_podcast_schema_nonce'); ?>

        <div class="h-card" style="margin-bottom: 20px;">
            <h3 style="font-weight: inherit; font-size: 16px; margin-top: 0; color: #25316a;">تنظیمات عمومی فید</h3>

            <div class="h-form-group">
                <label class="h-checkbox-label">
                    <input type="checkbox" name="hodima_podcast_status" value="1" <?php checked($is_enabled, '1'); ?>>
                    <span style="font-weight: inherit;">فعال‌سازی فید پادکست (<code dir="ltr">/feed/podcast/</code>)</span>
                </label>
                <p class="description">در صورت غیرفعال بودن، آدرس فید ثبت نمی‌شود و ۴۰۴ برمی‌گرداند.</p>
            </div>

            <div class="h-separator dashed" style="border-top: 1px dashed #e2e8f0; margin: 15px 0;"></div>

            <div class="h-form-group">
                <label for="hodima_podcast_title" style="display:block; margin-bottom: 5px;">عنوان پادکست</label>
                <input type="text" name="hodima_podcast_title" id="hodima_podcast_title" class="regular-text hodima-input" value="<?php echo esc_attr($title); ?>">
            </div>

            <div class="h-separator dashed" style="border-top: 1px dashed #e2e8f0; margin: 15px 0;"></div>

            <div class="h-form-group">
                <label for="hodima_podcast_desc" style="display:block; margin-bottom: 5px;">توضیحات پادکست</label>
                <textarea name="hodima_podcast_desc" id="hodima_podcast_desc" class="large-text hodima-textarea" rows="3"><?php echo esc_textarea($desc); ?></textarea>
            </div>
        </div>

        <div class="h-card">
            <h3 style="font-weight: inherit; font-size: 16px; margin-top: 0; color: #25316a;">تنظیمات iTunes / Apple Podcasts</h3>

            <div class="h-form-group">
                <label for="hodima_podcast_author" style="display:block; margin-bottom: 5px;">نام سازنده (Author)</label>
                <input type="text" name="hodima_podcast_author" id="hodima_podcast_author" class="regular-text hodima-input" value="<?php echo esc_attr($author); ?>">
            </div>

            <div class="h-separator dashed" style="border-top: 1px dashed #e2e8f0; margin: 15px 0;"></div>

            <div class="h-form-group">
                <label for="hodima_podcast_category" style="display:block; margin-bottom: 5px;">دسته‌بندی iTunes</label>
                <input type="text" name="hodima_podcast_category" id="hodima_podcast_category" class="regular-text ltr hodima-input" dir="ltr" value="<?php echo esc_attr($category); ?>" placeholder="Business">
                <p class="description">باید دقیقاً منطبق با یکی از دسته‌های رسمی Apple Podcasts باشد (مثال: Business, Technology).</p>
            </div>

            <div class="h-separator dashed" style="border-top: 1px dashed #e2e8f0; margin: 15px 0;"></div>

            <div class="h-form-group">
                <label style="display:block; margin-bottom: 5px;">محتوای صریح (Explicit)</label>
                <select name="hodima_podcast_explicit" class="hodima-input" style="min-width:150px;">
                    <option value="clean" <?php selected($explicit, 'clean'); ?>>Clean (خانواده‌پسند)</option>
                    <option value="explicit" <?php selected($explicit, 'explicit'); ?>>Explicit</option>
                </select>
            </div>

            <div class="h-separator dashed" style="border-top: 1px dashed #e2e8f0; margin: 15px 0;"></div>

            <div class="h-form-group">
                <label style="display:block; margin-bottom: 10px; font-weight: inherit;">پست‌تایپ‌های دارای فایل صوتی</label>
                <div style="display: flex; flex-wrap: wrap; gap: 15px; background: #f8fafc; padding: 15px; border: 1px dashed #c1c9ec; border-radius: 6px;">
                    <?php foreach ($all_post_types as $pt) : if ($pt->name === 'attachment') continue; ?>
                        <label style="display: flex; align-items: center; gap: 5px; min-width: 170px; cursor: pointer;">
                            <input type="checkbox" name="hodima_podcast_post_types[]" value="<?php echo esc_attr($pt->name); ?>" <?php checked(in_array($pt->name, $saved_types, true)); ?>>
                            <span style="font-weight: inherit; color: #25316a;"><?php echo esc_html($pt->labels->name); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <p class="description" style="margin-top: 10px;">فقط نوشته‌هایی که متای <code>_hook_audio_url</code> برایشان مقداردهی شده باشد، وارد فید می‌شوند.</p>
            </div>
        </div>

        <?php hodima_view_form_footer(); ?>
    </form>
</div>

<?php hodima_view_footer(); ?>
