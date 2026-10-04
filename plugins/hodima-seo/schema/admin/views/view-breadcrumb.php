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

    // دسته‌های مستثنا از مسیر راهنمای محصول (قبلا نامک ثابت در قالب)
    if ( isset( $_POST['hodima_bc_exclude_sent'] ) && defined( 'HODIMA_BREADCRUMB_EXCLUDE_OPTION' ) ) {
        $bc_exclude = array_map( 'sanitize_title', (array) wp_unslash( $_POST['hodima_bc_exclude'] ?? [] ) );
        update_option( HODIMA_BREADCRUMB_EXCLUDE_OPTION, array_values( array_unique( array_filter( $bc_exclude ) ) ), false );
    }

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

        <?php if ( function_exists( 'hodima_breadcrumb_excluded_slugs' ) && taxonomy_exists( 'product_cat' ) ) : ?>
            <?php $bc_terms = get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false, 'orderby' => 'name' ] ); ?>
            <?php $bc_selected = hodima_breadcrumb_excluded_slugs(); ?>
            <tr>
                <th scope="row">دسته‌هایی که دسته اصلی مسیر نمی‌شوند</th>
                <td>
                    <input type="hidden" name="hodima_bc_exclude_sent" value="1">
                    <?php if ( is_array( $bc_terms ) && $bc_terms ) : ?>
                        <fieldset class="hodima-bc-exclude">
                            <legend class="screen-reader-text">دسته‌هایی که دسته اصلی مسیر نمی‌شوند</legend>
                            <?php foreach ( $bc_terms as $bc_term ) : ?>
                                <label>
                                    <input type="checkbox" name="hodima_bc_exclude[]" value="<?php echo esc_attr( $bc_term->slug ); ?>" <?php checked( in_array( $bc_term->slug, $bc_selected, true ) ); ?>>
                                    <?php echo esc_html( $bc_term->name ); ?>
                                </label>
                            <?php endforeach; ?>
                        </fieldset>
                        <style>.hd-wrap .hodima-bc-exclude { display: grid; grid-template-columns: repeat(auto-fill, minmax(12rem, 1fr)); gap: .5rem 1rem; border: 0; padding: 0; }</style>
                    <?php endif; ?>
                    <p class="description">دسته‌های عمومی مثل «جدیدترین محصولات» که موضوع محصول نیستند. اگر محصولی دسته دیگری هم داشته باشد، مسیر راهنما (هم در سایت و هم در اسکیما) از آن دسته ساخته می‌شود. دسته اصلی که در ویرایش محصول دستی انتخاب شده، تغییر نمی‌کند.</p>
                </td>
            </tr>
        <?php endif; ?>
    </table>

    <?php hodima_view_form_footer(); ?>
    <input type="hidden" name="hodima_save_breadcrumb_schema" value="1">
</form>

<?php hodima_view_footer(); ?>