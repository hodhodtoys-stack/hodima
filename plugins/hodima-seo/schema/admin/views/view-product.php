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

        // باگ رفع‌شده: قبلاً حداقل/حداکثر روزهای آماده‌سازی و ارسال بدون هیچ
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
        
        $message = '<div class="notice notice-success is-dismissible"><p>تنظیمات اسکیمای محصولات با موفقیت ذخیره شد.</p></div>';
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
    'اسکیمای محصولات (Product Pro)',
    'اطلاعات ساختاریافته محصولات ووکامرس: برند، فروشنده، سیاست مرجوعی و زمان ارسال (Merchant Listing).',
    'dashicons-products'
);
?>

<?php if (!empty($message)) echo $message; ?>

<div class="hd-callout">
    <?php echo hodima_admin_icon( 'dashicons-editor-code' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
    <div>
        <strong>متغیرهای قابل استفاده</strong>
        <p><code>[product_name]</code> نام محصول — <code>[site_name]</code> نام سایت</p>
    </div>
</div>

<form method="post" action="" class="hd-body">
    <?php wp_nonce_field('hodima_save_product_schema', 'hodima_product_schema_nonce'); ?>
    <input type="hidden" name="submit_product_schema" value="1">

    <section class="hd-card">
        <header class="hd-card__head">
            <?php echo hodima_admin_icon( 'dashicons-admin-settings' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
            <h2 class="hd-card__title">تنظیمات پایه و عمومی</h2>
        </header>

        <div class="hd-fields">
            <div class="hd-field hd-field--wide">
                <label class="hd-toggle">
                    <input type="checkbox" class="hd-switch" role="switch" name="hodima_schema_product_enable" value="1" <?php checked($is_enabled, '1'); ?>>
                    <span>فعال‌سازی اسکیمای حرفه‌ای محصولات</span>
                </label>
                <p class="hd-field__help">اسکیمای پیش‌فرض ووکامرس غیرفعال و اسکیمای هدیما جایگزین آن می‌شود.</p>
            </div>

            <div class="hd-field">
                <label class="hd-field__label" for="hodima_schema_product_brand">نام برند پیش‌فرض (Brand)</label>
                <input type="text" name="hodima_schema_product_brand" id="hodima_schema_product_brand" value="<?php echo esc_attr($brand); ?>">
            </div>

            <div class="hd-field">
                <label class="hd-field__label" for="hodima_schema_product_seller">نام فروشنده (Seller / Organization)</label>
                <input type="text" name="hodima_schema_product_seller" id="hodima_schema_product_seller" value="<?php echo esc_attr($seller); ?>">
            </div>

            <div class="hd-field hd-field--wide">
                <label class="hd-field__label" for="hodima_schema_product_desc_tpl">الگوی توضیحات کوتاه</label>
                <textarea name="hodima_schema_product_desc_tpl" id="hodima_schema_product_desc_tpl" rows="3"><?php echo esc_textarea($desc_tpl); ?></textarea>
                <p class="hd-field__help">اگر محصول توضیح کوتاه نداشته باشد و خلاصه هوش مصنوعی هم نباشد، این متن جایگزین می‌شود.</p>
            </div>
        </div>
    </section>

    <section class="hd-card">
        <header class="hd-card__head">
            <?php echo hodima_admin_icon( 'dashicons-car' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
            <h2 class="hd-card__title">سیاست‌های فروش و ارسال (Merchant Listing)</h2>
            <p class="hd-card__desc">برای نمایش کامل محصول در Google Shopping و گرفتن برچسب‌های ویژه ضروری است.</p>
        </header>

        <div class="hd-fields">
            <div class="hd-field">
                <label class="hd-field__label" for="hodima_schema_product_return_days">مهلت مرجوعی کالا (روز)</label>
                <input type="number" name="hodima_schema_product_return_days" id="hodima_schema_product_return_days" value="<?php echo esc_attr($return_days); ?>">
                <p class="hd-field__help">مثال: 7 (یعنی ۷ روز ضمانت بازگشت وجه)</p>
            </div>

            <div class="hd-field">
                <label class="hd-field__label" for="hodima_schema_product_shipping_cost">هزینه ارسال پیش‌فرض (ریال)</label>
                <input type="text" name="hodima_schema_product_shipping_cost" id="hodima_schema_product_shipping_cost" class="ltr" dir="ltr" value="<?php echo esc_attr($shipping_cost); ?>">
                <p class="hd-field__help">عدد 0 یعنی ارسال رایگان (Free Shipping).</p>
            </div>

            <div class="hd-field">
                <span class="hd-field__label">زمان پردازش سفارش (Handling Time)</span>
                <div class="hd-inline">
                    <label>حداقل (روز)
                        <input type="number" name="hodima_schema_product_handling_min" class="small-text" value="<?php echo esc_attr($handling_min); ?>">
                    </label>
                    <label>حداکثر (روز)
                        <input type="number" name="hodima_schema_product_handling_max" class="small-text" value="<?php echo esc_attr($handling_max); ?>">
                    </label>
                </div>
                <p class="hd-field__help">زمان بسته‌بندی و تحویل سفارش به پست.</p>
            </div>

            <div class="hd-field">
                <span class="hd-field__label">زمان در راه بودن (Transit Time)</span>
                <div class="hd-inline">
                    <label>حداقل (روز)
                        <input type="number" name="hodima_schema_product_transit_min" class="small-text" value="<?php echo esc_attr($transit_min); ?>">
                    </label>
                    <label>حداکثر (روز)
                        <input type="number" name="hodima_schema_product_transit_max" class="small-text" value="<?php echo esc_attr($transit_max); ?>">
                    </label>
                </div>
                <p class="hd-field__help">زمانی که طول می‌کشد پست بسته را به مشتری برساند.</p>
            </div>

            <div class="hd-field hd-field--wide">
                <span class="hd-field__label">زمان ارسال کالای «موجود در انبار چین»</span>
                <div class="hd-inline">
                    <label>پردازش — حداقل (روز)
                        <input type="number" name="hodima_schema_product_china_handling_min" class="small-text" value="<?php echo esc_attr($china_handling_min); ?>">
                    </label>
                    <label>پردازش — حداکثر (روز)
                        <input type="number" name="hodima_schema_product_china_handling_max" class="small-text" value="<?php echo esc_attr($china_handling_max); ?>">
                    </label>
                    <label>در راه — حداقل (روز)
                        <input type="number" name="hodima_schema_product_china_transit_min" class="small-text" value="<?php echo esc_attr($china_transit_min); ?>">
                    </label>
                    <label>در راه — حداکثر (روز)
                        <input type="number" name="hodima_schema_product_china_transit_max" class="small-text" value="<?php echo esc_attr($china_transit_max); ?>">
                    </label>
                </div>
                <p class="hd-field__help">محصولات با وضعیت «موجود در انبار چین» این زمان را به‌عنوان deliveryTime به گوگل اعلام می‌کنند. اعداد پیش‌فرض تقریبی‌اند؛ با زمان واقعی تامین جایگزین کنید.</p>
            </div>
        </div>
    </section>

    <?php hodima_view_form_footer(); ?>
</form>

<?php hodima_view_footer(); ?>
