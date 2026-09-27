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

    $notice_msg = '<div class="notice notice-success is-dismissible" style="margin-bottom: 20px; border-radius: 10px;"><p style="font-weight: inherit;">تنظیمات اسکیمای بردکرامب با موفقیت به‌روزرسانی شد.</p></div>';
}

// مقادیر فعلی
$current_status     = get_option('hodima_breadcrumb_schema_status', 'on');
$current_home_label = get_option('hodima_breadcrumb_home_label', 'خانه');

// هدر
hodima_view_header(
    'تنظیمات اسکیمای بردکرامب (BreadcrumbList)',
    'این اسکیما به صورت کاملاً خودکار مسیر صفحات (نقشه راهنما) را برای نمایش حرفه‌ای در نتایج گوگل می‌سازد.',
    '🗺️'
);
?>

<div class="h-card">
    <?php echo $notice_msg; ?>

    <!-- اضافه شدن باکس راهنما برای اطلاع‌رسانی آپدیت جدید -->
    <div class="notice notice-info" style="border-right: 4px solid #607bbd; background: #fff; padding: 15px; margin-bottom: 25px; box-shadow: 0 1px 1px rgba(37, 49, 106, 0.04);">
        <h4 style="margin: 0 0 10px 0; color: #25316a; font-weight: inherit; display: flex; align-items: center; gap: 8px;">
            <span style="font-size: 20px;">🚀</span> پوشش هوشمند ووکامرس و آرشیوها
        </h4>
        <p style="margin: 0; font-size: 13px; color: #444; line-height: 1.6; font-weight: inherit;">
            این ماژول اکنون به صورت خودکار برگه اصلی فروشگاه (Shop)، دسته‌بندی‌های عمیق و صفحات آرشیو پست‌تایپ‌های سفارشی را شناسایی کرده و بردکرامب معتبر برای آن‌ها تولید می‌کند. نیازی به تنظیمات اضافه نیست!
        </p>
    </div>

    <form method="post" action="">
        <?php wp_nonce_field('hodima_breadcrumb_schema_nonce'); ?>

        <table class="form-table">
            <tr valign="top">
                <th scope="row" style="font-weight: inherit;">وضعیت اسکیما</th>
                <td>
                    <label style="display:flex;align-items:center;gap:8px;font-size:14px;">
                        <input type="checkbox" name="schema_status" value="on" <?php checked($current_status, 'on'); ?> />
                        <strong style="font-weight: inherit;">فعال بودن اسکیمای بردکرامب در کل سایت</strong>
                    </label>
                    <p class="description">با فعال‌سازی این گزینه، سلسله مراتب لینک‌ها در زیر عنوان سایت شما در گوگل نمایش داده می‌شود.</p>
                </td>
            </tr>

            <tr valign="top">
                <th scope="row" style="font-weight: inherit;">عنوان تب اول (خانه)</th>
                <td>
                    <input type="text" name="home_label" value="<?php echo esc_attr($current_home_label); ?>" placeholder="مثلاً: خانه یا صفحه اصلی" class="regular-text hodima-input" />
                    <p class="description">کلمه‌ای که به عنوان اولین بخش مسیر (Root) به گوگل معرفی می‌شود.</p>
                </td>
            </tr>
        </table>

        <?php hodima_view_form_footer(); ?>
        <input type="hidden" name="hodima_save_breadcrumb_schema" value="1">
    </form>
</div>

<?php hodima_view_footer(); ?>