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

    $notice_msg = '<div class="notice notice-success is-dismissible" style="margin-bottom: 20px; border-radius: 10px;"><p style="font-weight: inherit;">تنظیمات اسکیمای دسته‌بندی B2B با موفقیت ذخیره شد.</p></div>';
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
    'مدیریت متون پیش‌فرض، کاتالوگ محصولات و تنظیمات ویدیو برای صفحات دسته‌بندی.',
    '📂'
);
?>

<div class="h-card">
    <?php echo $notice_msg; ?>

    <!-- بخش راهنمای متغیرها -->
    <div style="background: #eceef9; border-right: 4px solid #25316a; padding: 15px; border-radius: 8px; margin-bottom: 25px;">
        <h4 style="margin: 0 0 10px 0; color: #25316a; display: flex; align-items: center; gap: 8px; font-weight: inherit;">
            <span style="font-size: 20px;">💡</span> راهنمای استفاده از متغیرها
        </h4>
        <p style="margin: 0; font-size: 13px; color: #444; line-height: 1.6; font-weight: inherit;">
            شما می‌توانید در تمامی فیلدهای زیر از شورت‌کدهای مقابل استفاده کنید: 
            <code style="background: #fff; padding: 2px 6px; border: 1px solid #ccc; border-radius: 4px; color: #607bbd;">[category]</code> (نام دسته‌بندی) و 
            <code style="background: #fff; padding: 2px 6px; border: 1px solid #ccc; border-radius: 4px; color: #607bbd;">[site_name]</code> (نام سایت).
        </p>
    </div>

    <form method="post" action="">
        <?php wp_nonce_field('hodima_category_schema_nonce'); ?>

        <table class="form-table">
            <tr>
                <th scope="row" style="font-weight: inherit;">وضعیت اسکیما</th>
                <td>
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" name="cat_status" value="on" <?php checked($status, 'on'); ?> />
                        <span style="font-weight: inherit;">فعال‌سازی CollectionPage در دسته‌بندی‌ها</span>
                    </label>
                </td>
            </tr>

            <tr>
                <th scope="row" style="font-weight: inherit;">دسته‌بندی نوشته‌ها (Category)</th>
                <td>
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" name="cat_include_post_category" value="on" <?php checked($include_post_category, 'on'); ?> />
                        <span style="font-weight: inherit;">علاوه بر دسته‌بندی محصولات، این اسکیما روی دسته‌بندی نوشته‌های وبلاگ هم اعمال شود</span>
                    </label>
                    <p class="description">در صورت غیرفعال بودن، این اسکیما فقط روی دسته‌بندی محصولات ووکامرس (product_cat) اجرا می‌شود.</p>
                </td>
            </tr>

            <tr>
                <th scope="row" style="font-weight: inherit;">الگوی عنوان (Collection Name)</th>
                <td>
                    <input type="text" name="cat_name_template" class="regular-text hodima-input" value="<?php echo esc_attr($name_tpl); ?>" />
                </td>
            </tr>

            <tr>
                <th scope="row" style="font-weight: inherit;">توضیحات سئو (Description)</th>
                <td>
                    <textarea name="cat_desc_template" rows="3" class="large-text hodima-textarea" style="padding:12px;"><?php echo esc_textarea($desc_tpl); ?></textarea>
                    <p class="description">این متن به عنوان توضیحات اسکیما به گوگل ارائه می‌شود.</p>
                </td>
            </tr>

            <tr>
                <th scope="row" style="font-weight: inherit;">مخاطب هدف (Audience)</th>
                <td>
                    <input type="text" name="cat_audience" class="regular-text hodima-input" value="<?php echo esc_attr($audience); ?>" />
                    <p class="description">مثال: همکاران، خریداران عمده</p>
                </td>
            </tr>

            <tr>
                <th scope="row" style="font-weight: inherit;">عنوان کاتالوگ (OfferCatalog)</th>
                <td>
                    <input type="text" name="cat_catalog_template" class="regular-text hodima-input" value="<?php echo esc_attr($catalog_tpl); ?>" />
                </td>
            </tr>

            <tr style="border-top: 2px dashed #eee;">
                <th scope="row" style="padding-top:30px; font-weight: inherit;"><span>🎥 تنظیمات ویدیوی دسته‌بندی</span></th>
                <td style="padding-top:30px;">
                    <p class="description">این تنظیمات زمانی اعمال می‌شود که برای دسته‌بندی ویدیو تعریف کرده باشید.</p>
                </td>
            </tr>

            <tr>
                <th scope="row" style="font-weight: inherit;">الگوی عنوان ویدیو</th>
                <td>
                    <input type="text" name="cat_video_title" class="regular-text hodima-input" value="<?php echo esc_attr($video_title); ?>" />
                </td>
            </tr>

            <tr>
                <th scope="row" style="font-weight: inherit;">الگوی توضیحات ویدیو</th>
                <td>
                    <input type="text" name="cat_video_desc" class="large-text hodima-input" value="<?php echo esc_attr($video_desc); ?>" />
                </td>
            </tr>
        </table>

        <?php hodima_view_form_footer(); ?>
        <input type="hidden" name="hodima_save_category_schema" value="1">
    </form>
</div>

<?php hodima_view_footer(); ?>