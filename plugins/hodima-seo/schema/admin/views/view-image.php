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

        $message = '<div class="notice notice-success is-dismissible" style="border-radius:8px; border-right: 4px solid #25316a; background: #fff;"><p style="font-weight: inherit;">تنظیمات اسکیمای تصاویر با موفقیت ذخیره شد.</p></div>';
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
    'تنظیمات اسکیمای تصاویر', 
    'این ماژول تصاویر شاخص، گالری محصولات و تصاویر داخل متن را شناسایی کرده و برای نتایج جستجوی عکس گوگل (Google Images) بهینه می‌کند.'
);
?>

<div class="h-card">
    <?php if (!empty($message)) echo $message; ?>

    <form method="post" action="">
        <?php wp_nonce_field('hodima_save_image_schema', 'hodima_image_schema_nonce'); ?>
        <input type="hidden" name="submit_image_schema" value="1">

        <!-- کارت اول: تنظیمات عمومی -->
        <div class="h-card" style="margin-bottom: 20px;">
            <h3 style="font-weight: inherit; font-size: 16px; margin-top: 0; color: #25316a;">تنظیمات عمومی تصویر</h3>
            
            <div class="h-form-group">
                <label class="h-checkbox-label">
                    <input type="checkbox" name="hodima_schema_image_enable" value="1" <?php checked($is_enabled, '1'); ?>>
                    <span style="font-weight: inherit;">فعال‌سازی استخراج خودکار اسکیمای تصاویر</span>
                </label>
                <p class="description">با فعال‌سازی این گزینه، سیستم جستجوگر تصاویر در صفحاتی که در زیر انتخاب می‌کنید فعال می‌شود.</p>
            </div>

            <!-- بخش جدید: انتخاب صفحات -->
            <div class="h-separator dashed" style="border-top: 1px dashed #e2e8f0; margin: 15px 0;"></div>
            
            <div class="h-form-group">
                <label style="display:block; margin-bottom: 10px; font-weight: inherit; color: #444;">فعال‌سازی در صفحات زیر (Targeting):</label>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 10px; background: #f8fafc; padding: 15px; border-radius: 6px; border: 1px solid #e2e8f0;">
                    
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="hodima_schema_image_targets[]" value="product" <?php checked(in_array('product', $targets)); ?>>
                        محصولات (ووکامرس)
                    </label>
                    
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="hodima_schema_image_targets[]" value="post" <?php checked(in_array('post', $targets)); ?>>
                        نوشته‌ها (وبلاگ)
                    </label>
                    
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="hodima_schema_image_targets[]" value="page" <?php checked(in_array('page', $targets)); ?>>
                        برگه‌ها (Pages)
                    </label>
                    
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="hodima_schema_image_targets[]" value="video" <?php checked(in_array('video', $targets)); ?>>
                        ویدئوها (Custom Post Type)
                    </label>
                    
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="hodima_schema_image_targets[]" value="product_cat" <?php checked(in_array('product_cat', $targets)); ?>>
                        دسته‌بندی محصولات
                    </label>

                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="hodima_schema_image_targets[]" value="category" <?php checked(in_array('category', $targets)); ?>>
                        دسته‌بندی نوشته‌ها
                    </label>
                    
                </div>
            </div>

            <div class="h-separator dashed" style="border-top: 1px dashed #e2e8f0; margin: 15px 0;"></div>

            <div class="h-form-group">
                <label for="hodima_schema_image_credit" style="display:block; margin-bottom: 5px;">متن اعتبار تصویر (Credit)</label>
                <input type="text" name="hodima_schema_image_credit" id="hodima_schema_image_credit" class="regular-text hodima-input" value="<?php echo esc_attr($credit); ?>">
                <p class="description">متنی که در بخش <code>creditText</code> اسکیما نمایش داده می‌شود (نشان‌دهنده مالکیت اثر).</p>
            </div>

            <div class="h-separator dashed" style="border-top: 1px dashed #e2e8f0; margin: 15px 0;"></div>

            <div class="h-form-group">
                <label for="hodima_schema_image_license" style="display:block; margin-bottom: 5px;">لینک صفحه قوانین یا لایسنس</label>
                <input type="url" name="hodima_schema_image_license" id="hodima_schema_image_license" class="regular-text ltr hodima-input" dir="ltr" value="<?php echo esc_url($license); ?>">
                <p class="description">آدرس صفحه‌ای که شرایط استفاده از تصاویر در آن درج شده است. (جهت دریافت تگ Licensable).</p>
            </div>
        </div>

        <!-- کارت دوم: تنظیمات فنی -->
        <div class="h-card">
            <h3 style="font-weight: inherit; font-size: 16px; margin-top: 0; color: #25316a;">تنظیمات فنی استخراج</h3>
            
            <div class="h-form-group">
                <label for="hodima_schema_image_max_count" style="display:block; margin-bottom: 5px;">حداکثر تصاویر استخراجی</label>
                <input type="number" name="hodima_schema_image_max_count" id="hodima_schema_image_max_count" class="small-text hodima-input" value="<?php echo esc_attr($max_count); ?>" min="1" max="10">
                <p class="description">تعداد تصاویری که ربات از محتوا، گالری محصول و محصولات دسته‌بندی استخراج می‌کند (توصیه: ۴ تصویر برای فعال‌سازی اسلایدر نتایج گوگل).</p>
            </div>

            <div class="h-separator dashed" style="border-top: 1px dashed #e2e8f0; margin: 15px 0;"></div>

            <div class="h-form-group">
                <label style="display:block; margin-bottom: 5px;">ابعاد پیش‌فرض تصاویر</label>
                <div style="display: flex; gap: 20px; margin-top: 8px; align-items: center;">
                    <div>
                        <span style="display:block; margin-bottom:4px; font-size:12px; color:#666;">عرض (Width)</span>
                        <input type="number" name="hodima_schema_image_def_width" class="small-text hodima-input" value="<?php echo esc_attr($def_width); ?>" placeholder="مثلا 800"> 
                    </div>
                    <div>
                        <span style="display:block; margin-bottom:4px; font-size:12px; color:#666;">ارتفاع (Height)</span>
                        <input type="number" name="hodima_schema_image_def_height" class="small-text hodima-input" value="<?php echo esc_attr($def_height); ?>" placeholder="مثلا 800">
                    </div>
                </div>
                <p class="description" style="margin-top: 10px;">اگر تصویری فاقد اندازه مشخص باشد، این ابعاد به عنوان جایگزین درج می‌شود تا خطای سرچ کنسول رفع گردد.</p>
            </div>
        </div>

        <?php hodima_view_form_footer(); ?>
    </form>
</div>

<?php hodima_view_footer(); ?>