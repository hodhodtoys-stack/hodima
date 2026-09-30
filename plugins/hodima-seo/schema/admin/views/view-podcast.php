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

        $message = '<div class="notice notice-success is-dismissible"><p>تنظیمات فید پادکست با موفقیت ذخیره شد.</p></div>';
    }
}

// مقادیر فعلی (دقیقاً هم‌راستا با fallback های داخل podcast-feed-core.php)
$is_enabled   = get_option('hodima_podcast_status', '1');
$title        = get_option('hodima_podcast_title', get_bloginfo('name') . ' - پادکست');
$desc         = get_option('hodima_podcast_desc', get_bloginfo('description'));
// خالی = نام سازمان (همان که فید چاپ می‌کند)
$author       = get_option('hodima_podcast_author') ?: ( function_exists( 'hodima_seo_schema_org_name' ) ? hodima_seo_schema_org_name() : get_bloginfo( 'name' ) );
$category     = get_option('hodima_podcast_category', 'Business');
$explicit     = get_option('hodima_podcast_explicit', 'clean');
$saved_types  = get_option('hodima_podcast_post_types', ['post', 'product']);

$all_post_types = get_post_types(['public' => true], 'objects');
$feed_url        = site_url('/feed/podcast/');
$permalink_structure = get_option('permalink_structure');

hodima_view_header(
    'فید پادکست (Apple & Google Podcast)',
    'خروجی استاندارد RSS 2.0 از فایل‌های صوتی (پادکست) نوشته‌ها، محصولات و دسته‌ها، سازگار با اپل پادکست و گوگل پادکست.',
    'dashicons-microphone'
);
?>

<?php echo $message; ?>

<?php if ( empty( $permalink_structure ) ) : ?>
    <div class="hd-callout hd-callout--danger">
        <?php echo hodima_admin_icon( 'dashicons-warning' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
        <div>
            <strong>ساختار پیوند یکتای سایت روی «ساده» (Plain) است</strong>
            <p>در این حالت آدرس‌هایی مثل <code>/feed/podcast/</code> شناخته نمی‌شوند و به صفحه اصلی هدایت می‌شوید؛ ربطی به تنظیمات این بخش ندارد.</p>
            <p>راه‌حل: در «تنظیمات ← پیوندهای یکتا» هر گزینه‌ای غیر از «ساده» را انتخاب و ذخیره کنید. تا آن زمان از این آدرس استفاده کنید:</p>
            <div class="hd-code"><?php echo esc_html( site_url( '/?feed=podcast' ) ); ?></div>
        </div>
    </div>
<?php endif; ?>

<section class="hd-card hd-card--accent">
    <header class="hd-card__head">
        <?php echo hodima_admin_icon( 'dashicons-rss' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
        <h2 class="hd-card__title">آدرس فید پادکست</h2>
    </header>
    <div class="hd-code">
        <span><?php echo esc_html($feed_url); ?></span>
        <a href="<?php echo esc_url($feed_url); ?>" target="_blank" rel="noopener">مشاهده</a>
    </div>
    <?php if ( ! empty( $permalink_structure ) ) : ?>
        <p class="description">اگر همچنان به صفحه اصلی هدایت می‌شوید، یک بار در «تنظیمات ← پیوندهای یکتا» بدون تغییر ذخیره کنید تا قوانین بازنویسی دوباره ساخته شوند.</p>
    <?php endif; ?>
</section>

<form method="post" action="" class="hd-body">
    <?php wp_nonce_field('hodima_save_podcast_schema', 'hodima_podcast_schema_nonce'); ?>

    <section class="hd-card">
        <header class="hd-card__head">
            <?php echo hodima_admin_icon( 'dashicons-admin-settings' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
            <h2 class="hd-card__title">تنظیمات عمومی فید</h2>
        </header>

        <div class="hd-fields">
            <div class="hd-field hd-field--wide">
                <label class="hd-toggle">
                    <input type="checkbox" class="hd-switch" role="switch" name="hodima_podcast_status" value="1" <?php checked($is_enabled, '1'); ?>>
                    <span>فعال‌سازی فید پادکست (<code>/feed/podcast/</code>)</span>
                </label>
                <p class="hd-field__help">اگر خاموش باشد، آدرس فید ثبت نمی‌شود و ۴۰۴ برمی‌گرداند.</p>
            </div>

            <div class="hd-field">
                <label class="hd-field__label" for="hodima_podcast_title">عنوان پادکست</label>
                <input type="text" name="hodima_podcast_title" id="hodima_podcast_title" value="<?php echo esc_attr($title); ?>">
            </div>

            <div class="hd-field hd-field--wide">
                <label class="hd-field__label" for="hodima_podcast_desc">توضیحات پادکست</label>
                <textarea name="hodima_podcast_desc" id="hodima_podcast_desc" rows="3"><?php echo esc_textarea($desc); ?></textarea>
            </div>
        </div>
    </section>

    <section class="hd-card">
        <header class="hd-card__head">
            <?php echo hodima_admin_icon( 'dashicons-playlist-audio' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
            <h2 class="hd-card__title">تنظیمات iTunes / Apple Podcasts</h2>
        </header>

        <div class="hd-fields">
            <div class="hd-field">
                <label class="hd-field__label" for="hodima_podcast_author">نام سازنده (Author)</label>
                <input type="text" name="hodima_podcast_author" id="hodima_podcast_author" value="<?php echo esc_attr($author); ?>">
            </div>

            <div class="hd-field">
                <label class="hd-field__label" for="hodima_podcast_category">دسته‌بندی iTunes</label>
                <input type="text" name="hodima_podcast_category" id="hodima_podcast_category" class="ltr" dir="ltr" value="<?php echo esc_attr($category); ?>" placeholder="Business">
                <p class="hd-field__help">باید دقیقا یکی از دسته‌های رسمی Apple Podcasts باشد (مثال: Business, Technology).</p>
            </div>

            <div class="hd-field">
                <label class="hd-field__label" for="hodima_podcast_explicit">محتوای صریح (Explicit)</label>
                <select name="hodima_podcast_explicit" id="hodima_podcast_explicit">
                    <option value="clean" <?php selected($explicit, 'clean'); ?>>Clean (خانواده‌پسند)</option>
                    <option value="explicit" <?php selected($explicit, 'explicit'); ?>>Explicit</option>
                </select>
            </div>

            <fieldset class="hd-field hd-field--wide">
                <legend class="hd-field__label">پست‌تایپ‌های دارای فایل صوتی</legend>
                <div class="hd-choices">
                    <?php foreach ($all_post_types as $pt) : if ($pt->name === 'attachment') continue; ?>
                        <label>
                            <input type="checkbox" name="hodima_podcast_post_types[]" value="<?php echo esc_attr($pt->name); ?>" <?php checked(in_array($pt->name, $saved_types, true)); ?>>
                            <?php echo esc_html($pt->labels->name); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <p class="hd-field__help">هر نوشته/محصول/برگه‌ای از این نوع‌ها که در کادر «سیستم رسانه» ویرایشگرش «لینک فایل صوتی» دارد یک قسمت پادکست است، با عنوان صوت، خلاصه همان صفحه، مدت و تصویر خودش. با تیک «محصول» دسته‌های محصول و با «نوشته» دسته‌های نوشته هم (اگر صوت دارند) می‌آیند.</p>
            </fieldset>
        </div>
    </section>

    <?php hodima_view_form_footer(); ?>
</form>

<?php hodima_view_footer(); ?>
