<?php
if ( ! defined( 'ABSPATH' ) ) exit;
// دفاع در عمق: علاوه بر گیت‌وی متد add_submenu_page، دسترسی هم اینجا مستقیماً بررسی می‌شود
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'شما به این بخش دسترسی ندارید.' ) );
}


$notice_msg = '';

if ( isset($_POST['hodima_save_blog_schema']) ) {
    check_admin_referer('hodima_blog_schema_nonce');

    $status = isset($_POST['schema_status']) ? 'on' : 'off';

    update_option('hodima_blog_schema_status', $status);

    $notice_msg = '<div class="notice notice-success is-dismissible" style="margin-bottom: 20px; border-radius: 8px;"><p style="font-weight: inherit;">تنظیمات اسکیمای بلاگ با موفقیت ذخیره شد.</p></div>';
}

$current_status = get_option('hodima_blog_schema_status', 'on');

hodima_view_header(
    'تنظیمات اسکیمای بلاگ (BlogPosting)',
    'در این بخش می‌توانید تنظیمات عمومی مربوط به اسکیمای مقالات سایت را مدیریت کنید.',
    '📝'
);
?>

<div class="h-card">
    <?php echo $notice_msg; ?>

    <form method="post" action="">
        <?php wp_nonce_field('hodima_blog_schema_nonce'); ?>

        <table class="form-table">
            <tr valign="top">
                <th scope="row" style="font-weight: inherit;">وضعیت اسکیمای بلاگ</th>
                <td>
                    <label style="display:flex;align-items:center;gap:8px;">
                        <input type="checkbox" name="schema_status" value="on" <?php checked($current_status, 'on'); ?> />
                        <span style="font-weight: inherit;">فعال بودن اسکیما در مقالات</span>
                    </label>
                    <p class="description">اگر این تیک را بردارید، تولید اسکیمای بلاگ در کل سایت متوقف می‌شود.</p>
                </td>
            </tr>

        </table>

        <?php hodima_view_form_footer(); ?>
        <input type="hidden" name="hodima_save_blog_schema" value="1">
    </form>
</div>

<?php hodima_view_footer(); ?>