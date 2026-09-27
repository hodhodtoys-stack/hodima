<?php
/**
 * Admin View: Product Schema Pro Settings
 */

if (!defined('ABSPATH')) exit;
// دفاع در عمق: علاوه بر گیت‌وی متد add_submenu_page، دسترسی هم اینجا مستقیماً بررسی می‌شود
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'شما به این بخش دسترسی ندارید.' ) );
}


$message = '';

// بررسی ارسال فرم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hodima_product_schema_nonce'])) {
    if (wp_verify_nonce($_POST['hodima_product_schema_nonce'], 'hodima_save_product_schema')) {
        
        update_option('hodima_schema_product_enable', isset($_POST['hodima_schema_product_enable']) ? '1' : '0');
        update_option('hodima_schema_product_brand', isset($_POST['hodima_schema_product_brand']) ? sanitize_text_field( wp_unslash( $_POST['hodima_schema_product_brand'] ) ) : '');
        update_option('hodima_schema_product_seller', isset($_POST['hodima_schema_product_seller']) ? sanitize_text_field( wp_unslash( $_POST['hodima_schema_product_seller'] ) ) : '');
        update_option('hodima_schema_product_desc_tpl', isset($_POST['hodima_schema_product_desc_tpl']) ? sanitize_textarea_field( wp_unslash( $_POST['hodima_schema_product_desc_tpl'] ) ) : '');
        
        // تنظیمات فروش و ارسال
        update_option('hodima_schema_product_return_days', isset($_POST['hodima_schema_product_return_days']) ? absint($_POST['hodima_schema_product_return_days']) : 7);
        update_option('hodima_schema_product_shipping_cost', isset($_POST['hodima_schema_product_shipping_cost']) ? sanitize_text_field( wp_unslash( $_POST['hodima_schema_product_shipping_cost'] ) ) : '0');

        // 🛠️ باگ رفع‌شده: قبلاً حداقل/حداکثر روزهای آماده‌سازی و ارسال بدون هیچ
        // بررسی نسبت به هم ذخیره می‌شدند. اگر ادمین به‌اشتباه مقدار حداقل را
        // بزرگ‌تر از حداکثر وارد می‌کرد (مثلاً حداقل=۵، حداکثر=۲)، این بازه‌ی
        // معکوس مستقیماً وارد QuantitativeValue در JSON-LD می‌شد — که هم برای
        // گوگل بی‌معنی است و هم به مشتری تاریخ تحویل غلط نشان می‌دهد. حالا اگر
        // حداقل از حداکثر بزرگ‌تر باشد، خودکار جابه‌جا می‌شوند.
        $h_min = isset($_POST['hodima_schema_product_handling_min']) ? absint($_POST['hodima_schema_product_handling_min']) : 1;
        $h_max = isset($_POST['hodima_schema_product_handling_max']) ? absint($_POST['hodima_schema_product_handling_max']) : 2;
        if ( $h_min > $h_max ) { $tmp = $h_min; $h_min = $h_max; $h_max = $tmp; }
        update_option('hodima_schema_product_handling_min', $h_min);
        update_option('hodima_schema_product_handling_max', $h_max);

        $t_min = isset($_POST['hodima_schema_product_transit_min']) ? absint($_POST['hodima_schema_product_transit_min']) : 1;
        $t_max = isset($_POST['hodima_schema_product_transit_max']) ? absint($_POST['hodima_schema_product_transit_max']) : 4;
        if ( $t_min > $t_max ) { $tmp = $t_min; $t_min = $t_max; $t_max = $tmp; }
        update_option('hodima_schema_product_transit_min', $t_min);
        update_option('hodima_schema_product_transit_max', $t_max);

        // زمان ارسال کالای «موجود در انبار چین» (deliveryTime جداگانه در اسکیما)
        foreach ( [ 'handling' => [ 2, 5 ], 'transit' => [ 15, 30 ] ] as $kind => $defaults ) {
            $c_min = isset($_POST["hodima_schema_product_china_{$kind}_min"]) ? absint($_POST["hodima_schema_product_china_{$kind}_min"]) : $defaults[0];
            $c_max = isset($_POST["hodima_schema_product_china_{$kind}_max"]) ? absint($_POST["hodima_schema_product_china_{$kind}_max"]) : $defaults[1];
            if ( $c_min > $c_max ) { $tmp = $c_min; $c_min = $c_max; $c_max = $tmp; }
            update_option("hodima_schema_product_china_{$kind}_min", $c_min);
            update_option("hodima_schema_product_china_{$kind}_max", $c_max);
        }
        
        $message = '<div class="notice notice-success is-dismissible"><p style="font-weight: inherit;">تنظیمات اسکیمای محصولات با موفقیت ذخیره شد.</p></div>';
    }
}

// مقادیر فعلی با Fallback به مقادیر اصلی شما
$is_enabled    = get_option('hodima_schema_product_enable', '1');
$brand         = get_option('hodima_schema_product_brand', 'هدهدلی (hodima)');
$seller        = get_option('hodima_schema_product_seller', 'بازرگانی هدیما');
$desc_tpl      = get_option('hodima_schema_product_desc_tpl', 'خرید عمده [product_name] با بهترین قیمت از [site_name].');
$return_days   = get_option('hodima_schema_product_return_days', '7');
$shipping_cost = get_option('hodima_schema_product_shipping_cost', '0');
$handling_min  = get_option('hodima_schema_product_handling_min', '1');
$handling_max  = get_option('hodima_schema_product_handling_max', '2');
$transit_min   = get_option('hodima_schema_product_transit_min', '1');
$transit_max   = get_option('hodima_schema_product_transit_max', '4');
$china_handling_min = get_option('hodima_schema_product_china_handling_min', '2');
$china_handling_max = get_option('hodima_schema_product_china_handling_max', '5');
$china_transit_min  = get_option('hodima_schema_product_china_transit_min', '15');
$china_transit_max  = get_option('hodima_schema_product_china_transit_max', '30');

// لود هدر یکپارچه پنل
hodima_view_header(
    'تنظیمات اسکیمای محصولات (Product Pro)', 
    'مدیریت کامل اطلاعات ساختاریافته محصولات ووکامرس، شامل سیاست‌های مرجوعی، ارسال و هوش مصنوعی.'
);
?>

<div class="h-card">
    <?php
    // چاپ پیام موفقیت (در صورت وجود)
    if (!empty($message)) {
        echo $message;
    }
    ?>

    <!-- باکس راهنمای متغیرها -->
    <div class="notice notice-info" style="border-right: 4px solid #607bbd; background: #fff; padding: 12px; margin-bottom: 20px; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
        <p style="margin:0 0 8px;"><span style="font-weight: inherit;">راهنمای متغیرها (Shortcodes):</span></p>
        <ul style="margin:0; padding-right:20px; list-style-type: disc;">
            <li><code>[product_name]</code> : نام محصول</li>
            <li><code>[site_name]</code> : نام سایت شما</li>
        </ul>
    </div>

    <form method="post" action="">
        <?php wp_nonce_field('hodima_save_product_schema', 'hodima_product_schema_nonce'); ?>
        <input type="hidden" name="submit_product_schema" value="1">

        <!-- کارت اول: تنظیمات پایه محصول -->
        <div class="h-card" style="margin-bottom: 20px;">
            <h3 style="font-weight: inherit; font-size: 16px; margin-top: 0;">تنظیمات پایه و عمومی</h3>
            
            <div class="h-form-group">
                <label class="h-checkbox-label">
                    <input type="checkbox" name="hodima_schema_product_enable" value="1" <?php checked($is_enabled, '1'); ?>>
                    <span style="font-weight: inherit;">فعال‌سازی اسکیمای حرفه‌ای محصولات</span>
                </label>
                <p class="description">با فعال‌سازی این گزینه، اسکیمای پیش‌فرض ووکامرس غیرفعال شده و اسکیمای هدیما جایگزین آن می‌شود.</p>
            </div>

            <div class="h-separator dashed" style="border-top: 1px dashed #e2e8f0; margin: 15px 0;"></div>

            <div class="h-form-group">
                <label for="hodima_schema_product_brand" style="display:block; margin-bottom: 5px;">نام برند پیش‌فرض (Brand)</label>
                <input type="text" name="hodima_schema_product_brand" id="hodima_schema_product_brand" class="regular-text hodima-input" value="<?php echo esc_attr($brand); ?>">
            </div>

            <div class="h-separator dashed" style="border-top: 1px dashed #e2e8f0; margin: 15px 0;"></div>

            <div class="h-form-group">
                <label for="hodima_schema_product_seller" style="display:block; margin-bottom: 5px;">نام فروشنده (Seller / Organization)</label>
                <input type="text" name="hodima_schema_product_seller" id="hodima_schema_product_seller" class="regular-text hodima-input" value="<?php echo esc_attr($seller); ?>">
            </div>

            <div class="h-separator dashed" style="border-top: 1px dashed #e2e8f0; margin: 15px 0;"></div>

            <div class="h-form-group">
                <label for="hodima_schema_product_desc_tpl" style="display:block; margin-bottom: 5px;">الگوی توضیحات کوتاه</label>
                <textarea name="hodima_schema_product_desc_tpl" id="hodima_schema_product_desc_tpl" class="large-text hodima-textarea" rows="3"><?php echo esc_textarea($desc_tpl); ?></textarea>
                <p class="description">اگر محصول توضیحات کوتاه نداشته باشد و هوش مصنوعی هم خلاصه‌ای تولید نکرده باشد، این متن جایگزین می‌شود.</p>
            </div>
        </div>

        <!-- کارت دوم: سیاست‌های فروش و ارسال (Merchant Listing) -->
        <div class="h-card">
            <h3 style="font-weight: inherit; font-size: 16px; margin-top: 0;">سیاست‌های فروش و ارسال (Merchant Listing)</h3>
            <p class="description" style="margin-bottom: 20px;">این تنظیمات برای نمایش کامل محصول در تب <span style="font-weight: inherit;">Google Shopping</span> و گرفتن تگ‌های طلایی بسیار ضروری است.</p>

            <div class="h-form-group">
                <label for="hodima_schema_product_return_days" style="display:block; margin-bottom: 5px;">مهلت مرجوعی کالا (روز)</label>
                <input type="number" name="hodima_schema_product_return_days" id="hodima_schema_product_return_days" class="small-text hodima-input" value="<?php echo esc_attr($return_days); ?>">
                <span class="description">مثال: 7 (نشان‌دهنده ۷ روز ضمانت بازگشت وجه)</span>
            </div>

            <div class="h-separator dashed" style="border-top: 1px dashed #e2e8f0; margin: 15px 0;"></div>

            <div class="h-form-group">
                <label for="hodima_schema_product_shipping_cost" style="display:block; margin-bottom: 5px;">هزینه ارسال پیش‌فرض (ریال)</label>
                <input type="text" name="hodima_schema_product_shipping_cost" id="hodima_schema_product_shipping_cost" class="regular-text hodima-input" value="<?php echo esc_attr($shipping_cost); ?>">
                <p class="description">عدد <span style="font-weight: inherit;">0</span> به معنای <span style="font-weight: inherit;">ارسال رایگان</span> (Free Shipping) است و توسط گوگل به شدت مورد توجه قرار می‌گیرد.</p>
            </div>

            <div class="h-separator dashed" style="border-top: 1px dashed #e2e8f0; margin: 15px 0;"></div>

            <div class="h-form-group">
                <label style="display:block; margin-bottom: 5px;">زمان پردازش سفارش (Handling Time)</label>
                <div style="display: flex; gap: 20px; align-items: center; margin-top: 8px;">
                    <div>
                        <span style="display:block; margin-bottom:4px; font-size:12px; color:#666;">حداقل (روز)</span>
                        <input type="number" name="hodima_schema_product_handling_min" class="small-text hodima-input" value="<?php echo esc_attr($handling_min); ?>"> 
                    </div>
                    <div>
                        <span style="display:block; margin-bottom:4px; font-size:12px; color:#666;">حداکثر (روز)</span>
                        <input type="number" name="hodima_schema_product_handling_max" class="small-text hodima-input" value="<?php echo esc_attr($handling_max); ?>">
                    </div>
                </div>
                <p class="description" style="margin-top: 10px;">زمانی که طول می‌کشد تا سفارش بسته‌بندی و تحویل پست شود.</p>
            </div>

            <div class="h-separator dashed" style="border-top: 1px dashed #e2e8f0; margin: 15px 0;"></div>

            <div class="h-form-group">
                <label style="display:block; margin-bottom: 5px;">زمان در راه بودن (Transit Time)</label>
                <div style="display: flex; gap: 20px; align-items: center; margin-top: 8px;">
                    <div>
                        <span style="display:block; margin-bottom:4px; font-size:12px; color:#666;">حداقل (روز)</span>
                        <input type="number" name="hodima_schema_product_transit_min" class="small-text hodima-input" value="<?php echo esc_attr($transit_min); ?>"> 
                    </div>
                    <div>
                        <span style="display:block; margin-bottom:4px; font-size:12px; color:#666;">حداکثر (روز)</span>
                        <input type="number" name="hodima_schema_product_transit_max" class="small-text hodima-input" value="<?php echo esc_attr($transit_max); ?>">
                    </div>
                </div>
                <p class="description" style="margin-top: 10px;">زمانی که طول می‌کشد تا اداره پست بسته را به دست مشتری برساند.</p>
            </div>

            <div class="h-separator dashed" style="border-top: 1px dashed #e2e8f0; margin: 15px 0;"></div>

            <div class="h-form-group">
                <label style="display:block; margin-bottom: 5px;">زمان ارسال کالای «موجود در انبار چین»</label>
                <div style="display: flex; gap: 20px; align-items: center; margin-top: 8px; flex-wrap: wrap;">
                    <div>
                        <span style="display:block; margin-bottom:4px; font-size:12px; color:#666;">پردازش — حداقل (روز)</span>
                        <input type="number" name="hodima_schema_product_china_handling_min" class="small-text hodima-input" value="<?php echo esc_attr($china_handling_min); ?>">
                    </div>
                    <div>
                        <span style="display:block; margin-bottom:4px; font-size:12px; color:#666;">پردازش — حداکثر (روز)</span>
                        <input type="number" name="hodima_schema_product_china_handling_max" class="small-text hodima-input" value="<?php echo esc_attr($china_handling_max); ?>">
                    </div>
                    <div>
                        <span style="display:block; margin-bottom:4px; font-size:12px; color:#666;">در راه — حداقل (روز)</span>
                        <input type="number" name="hodima_schema_product_china_transit_min" class="small-text hodima-input" value="<?php echo esc_attr($china_transit_min); ?>">
                    </div>
                    <div>
                        <span style="display:block; margin-bottom:4px; font-size:12px; color:#666;">در راه — حداکثر (روز)</span>
                        <input type="number" name="hodima_schema_product_china_transit_max" class="small-text hodima-input" value="<?php echo esc_attr($china_transit_max); ?>">
                    </div>
                </div>
                <p class="description" style="margin-top: 10px;">محصولاتی که وضعیت موجودی‌شان «موجود در انبار چین» است، این زمان را به عنوان deliveryTime به گوگل اعلام می‌کنند (به جای زمان ارسال انبار ایران). اعداد پیش‌فرض تقریبی‌اند؛ با زمان واقعی تامین جایگزین کنید.</p>
            </div>
        </div>

        <?php hodima_view_form_footer(); ?>
    </form>
</div>

<?php 
// لود فوتر یکپارچه پنل
hodima_view_footer(); 
?>