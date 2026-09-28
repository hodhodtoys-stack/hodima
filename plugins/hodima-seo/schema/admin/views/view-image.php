<?php
/**
 * Admin View: ImageObject Schema Settings (Advanced Targeting)
 */

if (!defined('ABSPATH')) exit;
// دفاع در عمق: علاوه بر گیت‌وی متد add_submenu_page، دسترسی هم اینجا مستقیماً بررسی می‌شود
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'شما به این بخش دسترسی ندارید.' ) );
}


$message = '';

// بررسی ارسال فرم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hodima_image_schema_nonce'])) {
    if (wp_verify_nonce($_POST['hodima_image_schema_nonce'], 'hodima_save_image_schema')) {
        
        update_option('hodima_schema_image_enable', isset($_POST['hodima_schema_image_enable']) ? '1' : '0');
        update_option('hodima_schema_image_credit', isset($_POST['hodima_schema_image_credit']) ? sanitize_text_field( wp_unslash( $_POST['hodima_schema_image_credit'] ) ) : '');
        update_option('hodima_schema_image_license', isset($_POST['hodima_schema_image_license']) ? esc_url_raw( wp_unslash( $_POST['hodima_schema_image_license'] ) ) : '');
        update_option('hodima_schema_image_max_count', isset($_POST['hodima_schema_image_max_count']) ? absint($_POST['hodima_schema_image_max_count']) : 4);
        update_option('hodima_schema_image_def_width', isset($_POST['hodima_schema_image_def_width']) ? absint($_POST['hodima_schema_image_def_width']) : 800);
        update_option('hodima_schema_image_def_height', isset($_POST['hodima_schema_image_def_height']) ? absint($_POST['hodima_schema_image_def_height']) : 800);
        
        // ذخیره انواع صفحات انتخاب شده
        $allowed_targets = isset($_POST['hodima_schema_image_targets']) && is_array($_POST['hodima_schema_image_targets']) ? array_map('sanitize_text_field', wp_unslash( (array) $_POST['hodima_schema_image_targets'] )) : [];
        update_option('hodima_schema_image_targets', $allowed_targets);

        $message = '<div class="notice notice-success is-dismissible"><p>تنظیمات اسکیمای تصاویر با موفقیت ذخیره شد.</p></div>';
    }
}

// مقادیر فعلی
$is_enabled  = get_option('hodima_schema_image_enable', '0');
$credit      = get_option('hodima_schema_image_credit', 'عکس متعلق به ' . get_bloginfo('name') . ' است.');
$license     = get_option('hodima_schema_image_license', home_url('/terms/'));
$max_count   = get_option('hodima_schema_image_max_count', '4'); // پیش‌فرض ۴ (حاشیه امنیت برای اسلایدر نتایج گوگل)
$def_width   = get_option('hodima_schema_image_def_width', '800');
$def_height  = get_option('hodima_schema_image_def_height', '800');
$targets     = get_option('hodima_schema_image_targets', ['product', 'post', 'product_cat', 'category']); // پیش‌فرض: محصولات و نوشته‌ها

// لود هدر یکپارچه پنل
hodima_view_header(
    'اسکیمای تصاویر (ImageObject)',
    'تصاویر شاخص، گالری محصولات و تصاویر داخل متن شناسایی و برای جستجوی تصویر گوگل (Google Images) بهینه می‌شوند.',
    'dashicons-format-image'
);

$hodima_targets = [
    'product'     => 'محصولات (ووکامرس)',
    'post'        => 'نوشته‌ها (وبلاگ)',
    'page'        => 'برگه‌ها',
    'video'       => 'ویدیوها',
    'product_cat' => 'دسته‌بندی محصولات',
    'category'    => 'دسته‌بندی نوشته‌ها',
];
?>

<?php if (!empty($message)) echo $message; ?>

<form method="post" action="" class="hd-body">
    <?php wp_nonce_field('hodima_save_image_schema', 'hodima_image_schema_nonce'); ?>
    <input type="hidden" name="submit_image_schema" value="1">

    <section class="hd-card">
        <header class="hd-card__head">
            <?php echo hodima_admin_icon( 'dashicons-admin-settings' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
            <h2 class="hd-card__title">تنظیمات عمومی تصویر</h2>
        </header>

        <div class="hd-fields">
            <div class="hd-field hd-field--wide">
                <label class="hd-toggle">
                    <input type="checkbox" class="hd-switch" role="switch" name="hodima_schema_image_enable" value="1" <?php checked($is_enabled, '1'); ?>>
                    <span>فعال‌سازی استخراج خودکار اسکیمای تصاویر</span>
                </label>
                <p class="hd-field__help">با فعال‌سازی، جستجوگر تصاویر در صفحه‌هایی که پایین انتخاب می‌کنید فعال می‌شود.</p>
            </div>

            <fieldset class="hd-field hd-field--wide">
                <legend class="hd-field__label">فعال‌سازی در صفحه‌های زیر (Targeting)</legend>
                <div class="hd-choices">
                    <?php foreach ( $hodima_targets as $value => $label ) : ?>
                        <label>
                            <input type="checkbox" name="hodima_schema_image_targets[]" value="<?php echo esc_attr( $value ); ?>" <?php checked(in_array($value, $targets)); ?>>
                            <?php echo esc_html( $label ); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <div class="hd-field">
                <label class="hd-field__label" for="hodima_schema_image_credit">متن اعتبار تصویر (Credit)</label>
                <input type="text" name="hodima_schema_image_credit" id="hodima_schema_image_credit" value="<?php echo esc_attr($credit); ?>">
                <p class="hd-field__help">مقدار <code>creditText</code> اسکیما (نشان‌دهنده مالکیت اثر).</p>
            </div>

            <div class="hd-field">
                <label class="hd-field__label" for="hodima_schema_image_license">لینک صفحه قوانین یا لایسنس</label>
                <input type="url" name="hodima_schema_image_license" id="hodima_schema_image_license" class="ltr" dir="ltr" value="<?php echo esc_url($license); ?>">
                <p class="hd-field__help">صفحه‌ای که شرایط استفاده از تصاویر در آن آمده (برای برچسب Licensable).</p>
            </div>
        </div>
    </section>

    <section class="hd-card">
        <header class="hd-card__head">
            <?php echo hodima_admin_icon( 'dashicons-admin-tools' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
            <h2 class="hd-card__title">تنظیمات فنی استخراج</h2>
        </header>

        <div class="hd-fields">
            <div class="hd-field">
                <label class="hd-field__label" for="hodima_schema_image_max_count">حداکثر تصاویر استخراجی</label>
                <input type="number" name="hodima_schema_image_max_count" id="hodima_schema_image_max_count" value="<?php echo esc_attr($max_count); ?>" min="1" max="10">
                <p class="hd-field__help">تعداد تصاویری که از محتوا، گالری محصول و محصولات دسته‌بندی استخراج می‌شود (توصیه: ۴ تصویر برای اسلایدر نتایج گوگل).</p>
            </div>

            <div class="hd-field">
                <span class="hd-field__label">ابعاد پیش‌فرض تصاویر</span>
                <div class="hd-inline">
                    <label>عرض (Width)
                        <input type="number" name="hodima_schema_image_def_width" class="small-text" value="<?php echo esc_attr($def_width); ?>" placeholder="800">
                    </label>
                    <label>ارتفاع (Height)
                        <input type="number" name="hodima_schema_image_def_height" class="small-text" value="<?php echo esc_attr($def_height); ?>" placeholder="800">
                    </label>
                </div>
                <p class="hd-field__help">اگر تصویری اندازه مشخص نداشته باشد، این ابعاد جایگزین می‌شود تا خطای سرچ کنسول رفع شود.</p>
            </div>
        </div>
    </section>

    <?php hodima_view_form_footer(); ?>
</form>

<?php hodima_view_footer(); ?>