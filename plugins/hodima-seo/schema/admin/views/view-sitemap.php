<?php
/**
 * Admin View: XML Sitemap Settings
 * Path: wp-content/plugins/hodima-seo/schema/admin/views/view-sitemap.php
 */

if ( ! defined( 'ABSPATH' ) ) exit;
// دفاع در عمق: علاوه بر گیت‌وی متد add_submenu_page، دسترسی هم اینجا مستقیماً بررسی می‌شود
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'شما به این بخش دسترسی ندارید.' ) );
}


$message = '';

// ۱. ذخیره تنظیمات عمومی و ویدیو
if ( isset($_POST['hodima_save_sitemap_settings']) && check_admin_referer('hodima_sitemap_nonce_action', 'hodima_sitemap_nonce') ) {

    $status = isset($_POST['sitemap_status']) ? '1' : '0';
    update_option('hodima_sitemap_status', $status);

    $post_types = isset($_POST['sitemap_post_types']) ? array_map('sanitize_text_field', wp_unslash( (array) $_POST['sitemap_post_types'] )) : [];
    update_option('hodima_sitemap_post_types', $post_types);

    $taxonomies = isset($_POST['sitemap_taxonomies']) ? array_map('sanitize_text_field', wp_unslash( (array) $_POST['sitemap_taxonomies'] )) : [];
    update_option('hodima_sitemap_taxonomies', $taxonomies);

    $limit = isset($_POST['sitemap_links_limit']) ? absint($_POST['sitemap_links_limit']) : 1000;
    if ( $limit < 100 ) $limit = 100;
    if ( $limit > 5000 ) $limit = 5000;
    update_option('hodima_sitemap_links_limit', $limit);

    if ( function_exists('hodima_sitemap_init_rules') ) {
        hodima_sitemap_init_rules();
        flush_rewrite_rules();
    }

    if ( function_exists('hodima_sitemap_clear_cache') ) {
        hodima_sitemap_clear_cache();
    }

    $message = '<div class="notice notice-success is-dismissible"><p>تنظیمات نقشه سایت ذخیره شد، کش بازسازی گردید و رول‌های آدرس به‌روزرسانی شدند.</p></div>';
}

// ۲. پاکسازی کش نقشه سایت‌ها
if ( isset($_POST['hodima_clear_sitemap_cache']) && check_admin_referer('hodima_sitemap_nonce_action', 'hodima_sitemap_nonce') ) {
    if ( function_exists('hodima_sitemap_clear_cache') ) {
        hodima_sitemap_clear_cache();
    }
    $message = '<div class="notice notice-success is-dismissible"><p>کش تمامی نقشه‌های سایت با موفقیت پاکسازی شد.</p></div>';
}

$status        = get_option('hodima_sitemap_status', '1');
$saved_pts     = get_option('hodima_sitemap_post_types', ['post', 'page', 'product', 'video']);
$saved_taxs    = get_option('hodima_sitemap_taxonomies', ['category', 'product_cat']);
$limit         = get_option('hodima_sitemap_links_limit', '1000');

$all_post_types = get_post_types(['public' => true], 'objects');
$all_taxonomies = get_taxonomies(['public' => true], 'objects');

$sitemap_url = site_url('/sitemap.xml');

hodima_view_header(
    'نقشه سایت اختصاصی (XML Sitemap)',
    'نقشه سایت یکپارچه با صفحه‌بندی، تصاویر، ویدیو و متادیتای پیشرفته، منطبق با به‌روزرسانی‌های ایندکس گوگل.',
    'dashicons-networking'
);
?>

<?php echo $message; ?>

<section class="hd-card hd-card--accent">
    <header class="hd-card__head">
        <?php echo hodima_admin_icon( 'dashicons-admin-links' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
        <h2 class="hd-card__title">آدرس نقشه سایت برای معرفی به گوگل</h2>
    </header>
    <div class="hd-code">
        <span><?php echo esc_html($sitemap_url); ?></span>
        <a href="<?php echo esc_url($sitemap_url); ?>" target="_blank" rel="noopener">مشاهده</a>
    </div>
    <p class="description">ویدیوها و مقالات به‌صورت ساختاریافته درون همین نقشه یکپارچه (تگ‌های &lt;video:video&gt;) معرفی می‌شوند؛ ثبت آدرس جداگانه لازم نیست.</p>
</section>

<form method="post" action="" class="hd-body">
    <?php wp_nonce_field('hodima_sitemap_nonce_action', 'hodima_sitemap_nonce'); ?>
    <input type="hidden" name="hodima_save_sitemap_settings" value="1">

    <section class="hd-card">
        <header class="hd-card__head">
            <?php echo hodima_admin_icon( 'dashicons-admin-settings' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
            <h2 class="hd-card__title">تنظیمات عمومی</h2>
        </header>

        <div class="hd-fields">
            <div class="hd-field">
                <label class="hd-toggle">
                    <input type="checkbox" class="hd-switch" role="switch" name="sitemap_status" value="1" <?php checked($status, '1'); ?>>
                    <span>فعال‌سازی موتور نقشه سایت هدیما</span>
                </label>
            </div>

            <div class="hd-field">
                <label class="hd-field__label" for="sitemap_links_limit">تعداد لینک در هر نقشه سایت (صفحه‌بندی)</label>
                <input type="number" name="sitemap_links_limit" id="sitemap_links_limit" value="<?php echo esc_attr($limit); ?>" min="100" max="5000">
                <p class="hd-field__help">برای جلوگیری از فشار به سرور لینک‌ها صفحه‌بندی می‌شوند. مقدار پیشنهادی: 1000</p>
            </div>
        </div>
    </section>

    <section class="hd-card">
        <header class="hd-card__head">
            <?php echo hodima_admin_icon( 'dashicons-admin-post' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
            <h2 class="hd-card__title">پست‌تایپ‌ها (Post Types)</h2>
            <p class="hd-card__desc">موارد noindex یا دارای رمز عبور خودکار کنار گذاشته می‌شوند.</p>
        </header>
        <div class="hd-choices">
            <?php foreach ( $all_post_types as $pt ) : ?>
                <?php if ( $pt->name === 'attachment' ) continue; ?>
                <label>
                    <input type="checkbox" name="sitemap_post_types[]" value="<?php echo esc_attr($pt->name); ?>" <?php checked(in_array($pt->name, $saved_pts, true)); ?>>
                    <?php echo esc_html($pt->labels->name); ?>
                    <code><?php echo esc_html($pt->name); ?></code>
                </label>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="hd-card">
        <header class="hd-card__head">
            <?php echo hodima_admin_icon( 'dashicons-category' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
            <h2 class="hd-card__title">دسته‌بندی‌ها و طبقه‌بندی‌ها (Taxonomies)</h2>
            <p class="hd-card__desc">طبقه‌بندی‌های انتخاب‌شده به‌صورت صفحه‌بندی‌شده همراه با رسانه‌هایشان در سایت‌مپ می‌آیند.</p>
        </header>
        <div class="hd-choices">
            <?php foreach ( $all_taxonomies as $tax ) : ?>
                <label>
                    <input type="checkbox" name="sitemap_taxonomies[]" value="<?php echo esc_attr($tax->name); ?>" <?php checked(in_array($tax->name, $saved_taxs, true)); ?>>
                    <?php echo esc_html($tax->labels->name); ?>
                    <code><?php echo esc_html($tax->name); ?></code>
                </label>
            <?php endforeach; ?>
        </div>
    </section>

    <div class="hd-actions">
        <?php // دکمه ذخیره اول می‌آید تا Enter در فرم، ذخیره کند نه پاک‌سازی کش ?>
        <button type="submit" class="button button-primary">ذخیره تنظیمات</button>
        <button
            type="submit"
            name="hodima_clear_sitemap_cache"
            class="button"
            onclick="return confirm('آیا از پاک کردن کش تمام نسخه‌های نقشه سایت مطمئن هستید؟');"
        >
            <?php echo hodima_admin_icon( 'dashicons-update' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
            پاک‌سازی دستی کش سایت‌مپ
        </button>
    </div>
</form>

<?php hodima_view_footer(); ?>