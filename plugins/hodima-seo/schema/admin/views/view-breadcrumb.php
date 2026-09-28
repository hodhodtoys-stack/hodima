<?php
if ( ! defined( 'ABSPATH' ) ) exit;
// دفاع در عمق: علاوه بر گیت‌وی متد add_submenu_page، دسترسی هم اینجا مستقیماً بررسی می‌شود
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'شما به این بخش دسترسی ندارید.' ) );
}


$notice_msg = '';

// ذخیره فرم
if ( isset($_POST['hodima_save_breadcrumb_schema']) ) {
    check_admin_referer('hodima_breadcrumb_schema_nonce');

    $status     = isset($_POST['schema_status']) ? 'on' : 'off';
    $home_label = isset($_POST['home_label']) ? sanitize_text_field( wp_unslash( $_POST['home_label'] ) ) : 'خانه';

    update_option('hodima_breadcrumb_schema_status', $status);
    update_option('hodima_breadcrumb_home_label', $home_label);

    $notice_msg = '<div class="notice notice-success is-dismissible"><p>تنظیمات اسکیمای بردکرامب با موفقیت به‌روزرسانی شد.</p></div>';
}

// مقادیر فعلی
$current_status     = get_option('hodima_breadcrumb_schema_status', 'on');
$current_home_label = get_option('hodima_breadcrumb_home_label', 'خانه');

// هدر
hodima_view_header(
    'اسکیمای بردکرامب (BreadcrumbList)',
    'مسیر صفحات (نقشه راهنما) به‌صورت خودکار برای نمایش حرفه‌ای در نتایج گوگل ساخته می‌شود.',
    'dashicons-editor-ol'
);
?>

<?php echo $notice_msg; ?>

<div class="hd-callout">
    <?php echo hodima_admin_icon( 'dashicons-info-outline' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
    <div>
        <strong>پوشش هوشمند ووکامرس و آرشیوها</strong>
        <p>برگه اصلی فروشگاه (Shop)، دسته‌بندی‌های عمیق و آرشیو پست‌تایپ‌های سفارشی خودکار شناسایی می‌شوند و بردکرامب معتبر برایشان ساخته می‌شود؛ تنظیم اضافه‌ای لازم نیست.</p>
    </div>
</div>

<form method="post" action="" class="hd-card">
    <?php wp_nonce_field('hodima_breadcrumb_schema_nonce'); ?>

    <table class="form-table" role="presentation">
        <tr>
            <th scope="row">وضعیت اسکیما</th>
            <td>
                <label class="hd-toggle">
                    <input type="checkbox" class="hd-switch" role="switch" name="schema_status" value="on" <?php checked($current_status, 'on'); ?> />
                    <span>فعال بودن اسکیمای بردکرامب در کل سایت</span>
                </label>
                <p class="description">سلسله‌مراتب لینک‌ها زیر عنوان سایت شما در گوگل نمایش داده می‌شود.</p>
            </td>
        </tr>

        <tr>
            <th scope="row"><label for="hodima-home-label">عنوان تب اول (خانه)</label></th>
            <td>
                <input type="text" id="hodima-home-label" name="home_label" value="<?php echo esc_attr($current_home_label); ?>" placeholder="مثلاً: خانه یا صفحه اصلی" class="regular-text" />
                <p class="description">کلمه‌ای که به عنوان اولین بخش مسیر (Root) به گوگل معرفی می‌شود.</p>
            </td>
        </tr>
    </table>

    <?php hodima_view_form_footer(); ?>
    <input type="hidden" name="hodima_save_breadcrumb_schema" value="1">
</form>

<?php hodima_view_footer(); ?>