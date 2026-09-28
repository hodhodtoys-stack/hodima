<?php
if ( ! defined( 'ABSPATH' ) ) exit;
// دفاع در عمق: علاوه بر گیت‌وی متد add_submenu_page، دسترسی هم اینجا مستقیماً بررسی می‌شود
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'شما به این بخش دسترسی ندارید.' ) );
}


$notice_msg = '';

if ( isset($_POST['hodima_save_category_schema']) ) {
    check_admin_referer('hodima_category_schema_nonce');

    update_option('hodima_cat_status', isset($_POST['cat_status']) ? 'on' : 'off');
    update_option('hodima_cat_include_post_category', isset($_POST['cat_include_post_category']) ? 'on' : 'off');
    update_option('hodima_cat_name_template', isset($_POST['cat_name_template']) ? sanitize_text_field( wp_unslash( $_POST['cat_name_template'] ) ) : '');
    update_option('hodima_cat_desc_template', isset($_POST['cat_desc_template']) ? sanitize_textarea_field( wp_unslash( $_POST['cat_desc_template'] ) ) : '');
    update_option('hodima_cat_catalog_template', isset($_POST['cat_catalog_template']) ? sanitize_text_field( wp_unslash( $_POST['cat_catalog_template'] ) ) : '');
    update_option('hodima_cat_audience', isset($_POST['cat_audience']) ? sanitize_text_field( wp_unslash( $_POST['cat_audience'] ) ) : '');
    update_option('hodima_cat_video_title', isset($_POST['cat_video_title']) ? sanitize_text_field( wp_unslash( $_POST['cat_video_title'] ) ) : '');
    update_option('hodima_cat_video_desc', isset($_POST['cat_video_desc']) ? sanitize_text_field( wp_unslash( $_POST['cat_video_desc'] ) ) : '');

    $notice_msg = '<div class="notice notice-success is-dismissible"><p>تنظیمات اسکیمای دسته‌بندی B2B با موفقیت ذخیره شد.</p></div>';
}

$status      = get_option('hodima_cat_status', 'on');
$include_post_category = get_option('hodima_cat_include_post_category', 'on');
$name_tpl    = get_option('hodima_cat_name_template', 'پخش عمده [category]');
$desc_tpl    = get_option('hodima_cat_desc_template', 'مرجع تخصصی واردات و پخش عمده [category] با رقابتی‌ترین قیمت بازار در [site_name].');
$catalog_tpl = get_option('hodima_cat_catalog_template', 'کاتالوگ محصولات [category]');
$audience    = get_option('hodima_cat_audience', 'خریداران عمده، همکاران و B2B');
$video_title = get_option('hodima_cat_video_title', 'معرفی دسته‌بندی: [category]');
$video_desc  = get_option('hodima_cat_video_desc', 'ویدیوی معرفی و راهنمای خرید عمده [category]');

hodima_view_header(
    'اسکیمای پیشرفته دسته‌بندی (B2B)',
    'متون پیش‌فرض، کاتالوگ محصولات و تنظیمات ویدیو برای صفحات دسته‌بندی.',
    'dashicons-category'
);
?>

<?php echo $notice_msg; ?>

<div class="hd-callout">
    <?php echo hodima_admin_icon( 'dashicons-lightbulb' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
    <div>
        <strong>راهنمای متغیرها</strong>
        <p>در همه فیلدهای زیر می‌توانید از <code>[category]</code> (نام دسته‌بندی) و <code>[site_name]</code> (نام سایت) استفاده کنید.</p>
    </div>
</div>

<form method="post" action="" class="hd-card">
    <?php wp_nonce_field('hodima_category_schema_nonce'); ?>

    <table class="form-table" role="presentation">
        <tr>
            <th scope="row">وضعیت اسکیما</th>
            <td>
                <label class="hd-toggle">
                    <input type="checkbox" class="hd-switch" role="switch" name="cat_status" value="on" <?php checked($status, 'on'); ?> />
                    <span>فعال‌سازی CollectionPage در دسته‌بندی‌ها</span>
                </label>
            </td>
        </tr>

        <tr>
            <th scope="row">دسته‌بندی نوشته‌ها (Category)</th>
            <td>
                <label class="hd-toggle">
                    <input type="checkbox" class="hd-switch" role="switch" name="cat_include_post_category" value="on" <?php checked($include_post_category, 'on'); ?> />
                    <span>این اسکیما روی دسته‌بندی نوشته‌های وبلاگ هم اعمال شود</span>
                </label>
                <p class="description">اگر خاموش باشد، فقط روی دسته‌بندی محصولات ووکامرس (product_cat) اجرا می‌شود.</p>
            </td>
        </tr>

        <tr>
            <th scope="row"><label for="hodima-cat-name">الگوی عنوان (Collection Name)</label></th>
            <td><input type="text" id="hodima-cat-name" name="cat_name_template" class="regular-text" value="<?php echo esc_attr($name_tpl); ?>" /></td>
        </tr>

        <tr>
            <th scope="row"><label for="hodima-cat-desc">توضیحات سئو (Description)</label></th>
            <td>
                <textarea id="hodima-cat-desc" name="cat_desc_template" rows="3" class="large-text"><?php echo esc_textarea($desc_tpl); ?></textarea>
                <p class="description">این متن به عنوان توضیحات اسکیما به گوگل ارائه می‌شود.</p>
            </td>
        </tr>

        <tr>
            <th scope="row"><label for="hodima-cat-audience">مخاطب هدف (Audience)</label></th>
            <td>
                <input type="text" id="hodima-cat-audience" name="cat_audience" class="regular-text" value="<?php echo esc_attr($audience); ?>" />
                <p class="description">مثال: همکاران، خریداران عمده</p>
            </td>
        </tr>

        <tr>
            <th scope="row"><label for="hodima-cat-catalog">عنوان کاتالوگ (OfferCatalog)</label></th>
            <td><input type="text" id="hodima-cat-catalog" name="cat_catalog_template" class="regular-text" value="<?php echo esc_attr($catalog_tpl); ?>" /></td>
        </tr>
    </table>

    <h2 class="hd-section-title"><?php echo hodima_admin_icon( 'dashicons-video-alt3' ); // phpcs:ignore WordPress.Security.EscapeOutput ?> ویدیوی دسته‌بندی</h2>
    <p class="description">این تنظیمات وقتی اعمال می‌شود که برای دسته‌بندی ویدیو تعریف کرده باشید.</p>

    <table class="form-table" role="presentation">
        <tr>
            <th scope="row"><label for="hodima-cat-video-title">الگوی عنوان ویدیو</label></th>
            <td><input type="text" id="hodima-cat-video-title" name="cat_video_title" class="regular-text" value="<?php echo esc_attr($video_title); ?>" /></td>
        </tr>

        <tr>
            <th scope="row"><label for="hodima-cat-video-desc">الگوی توضیحات ویدیو</label></th>
            <td><input type="text" id="hodima-cat-video-desc" name="cat_video_desc" class="large-text" value="<?php echo esc_attr($video_desc); ?>" /></td>
        </tr>
    </table>

    <?php hodima_view_form_footer(); ?>
    <input type="hidden" name="hodima_save_category_schema" value="1">
</form>

<?php hodima_view_footer(); ?>