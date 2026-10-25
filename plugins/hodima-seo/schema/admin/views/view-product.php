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

        // نحوه هزینه ارسال و مرجوعی (فقط مقدارهای مجاز؛ بقیه = پیش‌فرض)
        $pick = static fn( string $key, array $allowed ): string => in_array( $v = sanitize_key( wp_unslash( $_POST[ $key ] ?? '' ) ), $allowed, true ) ? $v : $allowed[0];
        update_option('hodima_schema_product_shipping_mode', $pick( 'hodima_schema_product_shipping_mode', [ 'customer', 'fixed' ] ));
        /*
         * مبلغ‌های ارسال به تومان (مثل خود فروشگاه)؛ اسکیما برای گوگل به ریال
         * (IRR) تبدیل می‌کند. قبلا ریال بود؛ hodima_schema_product_amount_unit
         * نشان می‌دهد مقدار ذخیره‌شده تومان است (مقدار قدیمی تا این ذخیره
         * همان ریال خوانده می‌شود). ارقام فارسی/عربی هم پذیرفته می‌شوند.
         */
        $digits = static fn( string $key ): string => (string) preg_replace( '/[^0-9]/', '', strtr( (string) wp_unslash( $_POST[ $key ] ?? '' ), array_combine( [ '۰','۱','۲','۳','۴','۵','۶','۷','۸','۹','٠','١','٢','٣','٤','٥','٦','٧','٨','٩' ], [ '0','1','2','3','4','5','6','7','8','9','0','1','2','3','4','5','6','7','8','9' ] ) ) );
        update_option('hodima_schema_product_shipping_cost', $digits( 'hodima_schema_product_shipping_cost' ) ?: '0');
        update_option('hodima_schema_product_shipping_max', $digits( 'hodima_schema_product_shipping_max' ));
        update_option('hodima_schema_product_amount_unit', 'toman');
        update_option('hodima_schema_product_return_fees', $pick( 'hodima_schema_product_return_fees', [ 'customer', 'free' ] ));
        update_option('hodima_schema_product_return_method', $pick( 'hodima_schema_product_return_method', [ 'mail', 'store', 'none' ] ));
        update_option('hodima_schema_product_refund_type', $pick( 'hodima_schema_product_refund_type', [ 'none', 'full', 'exchange', 'credit' ] ));

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
// پیش‌فرض‌ها همان مقدارهایی که اسکیما واقعا چاپ می‌کند (قبلا پنل «هدهدلی (hodima)»
// و «بازرگانی هدیما» نشان می‌داد ولی خروجی چیز دیگری بود).
$brand         = get_option('hodima_schema_product_brand', 'هدهدلی');
$seller        = get_option('hodima_schema_product_seller') ?: ( function_exists( 'hodima_seo_schema_org_name' ) ? hodima_seo_schema_org_name() : get_bloginfo( 'name' ) );
$desc_tpl      = get_option('hodima_schema_product_desc_tpl', 'خرید عمده [product_name] با بهترین قیمت از [site_name].');
$return_days   = get_option('hodima_schema_product_return_days', '7');
$shipping_cost = get_option('hodima_schema_product_shipping_cost', '0');
$shipping_mode = get_option('hodima_schema_product_shipping_mode', 'customer');
$shipping_max  = get_option('hodima_schema_product_shipping_max', '');
// مقدار قدیمی ریالی → نمایش به تومان (بعد از ذخیره، تومان ذخیره می‌شود)
if ( 'toman' !== get_option( 'hodima_schema_product_amount_unit', 'rial' ) ) {
    $shipping_cost = (string) intdiv( (int) preg_replace( '/[^0-9]/', '', (string) $shipping_cost ), 10 );
    $shipping_max  = '' === (string) $shipping_max ? '' : (string) intdiv( (int) $shipping_max, 10 );
}
$return_fees   = get_option('hodima_schema_product_return_fees', 'customer');
$return_method = get_option('hodima_schema_product_return_method', 'mail');
$refund_type   = get_option('hodima_schema_product_refund_type', 'none');
$handling_min  = get_option('hodima_schema_product_handling_min', '1');
$handling_max  = get_option('hodima_schema_product_handling_max', '2');
$transit_min   = get_option('hodima_schema_product_transit_min', '1');
$transit_max   = get_option('hodima_schema_product_transit_max', '4');
$china_handling_min = get_option('hodima_schema_product_china_handling_min', '2');
$china_handling_max = get_option('hodima_schema_product_china_handling_max', '5');
$china_transit_min  = get_option('hodima_schema_product_china_transit_min', '15');
$china_transit_max  = get_option('hodima_schema_product_china_transit_max', '30');

// خلاصه برای کارت‌های آمار
$ship_label = 'fixed' === $shipping_mode
    ? ( (int) $shipping_cost > 0 ? number_format_i18n( (int) $shipping_cost ) . ' تومان' : 'رایگان' )
    : 'متغیر';

hodima_view_header(
    'اسکیمای محصولات (Product Pro)',
    'اطلاعات ساختاریافته محصولات ووکامرس: برند، فروشنده، سیاست مرجوعی و زمان ارسال (Merchant Listing).',
    'dashicons-products'
);
?>

<?php if (!empty($message)) echo $message; // phpcs:ignore WordPress.Security.EscapeOutput -- پیام ثابت همین فایل ?>

<div class="hds-ui"><div class="hds-page__body">

<?php
hodima_seo_ui_stats( [
    [
        'label' => 'اسکیمای حرفه‌ای',
        'value' => '1' === (string) $is_enabled ? '<span class="hds-pill">فعال</span>' : '<span class="hds-pill hds-pill--warn">غیرفعال</span>',
        'sub'   => '1' === (string) $is_enabled ? 'جایگزین اسکیمای ووکامرس' : 'اسکیمای پیش‌فرض ووکامرس کار می‌کند',
    ],
    [
        'label' => 'برند پیش‌فرض',
        'value' => esc_html( $brand ),
        'sub'   => 'فروشنده: ' . esc_html( $seller ),
    ],
    [
        'label' => 'مهلت مرجوعی',
        'value' => esc_html( number_format_i18n( (int) $return_days ) ) . ' روز',
        'sub'   => (int) $return_days > 0 ? ( 'free' === $return_fees ? 'برگشت رایگان' : 'هزینه برگشت با مشتری' ) : 'مرجوعی پذیرفته نمی‌شود',
    ],
    [
        'label' => 'هزینه ارسال',
        'value' => esc_html( $ship_label ),
        'sub'   => esc_html( sprintf( 'پردازش %1$s تا %2$s روز، راه %3$s تا %4$s روز', number_format_i18n( (int) $handling_min ), number_format_i18n( (int) $handling_max ), number_format_i18n( (int) $transit_min ), number_format_i18n( (int) $transit_max ) ) ),
    ],
] );

/** یک بازه «حداقل تا حداکثر روز» (دو فیلد number با برچسب پنهان برای صفحه‌خوان). */
$hodima_range = static function ( string $min_name, $min, string $max_name, $max, string $label ): void {
    printf(
        '<input type="number" class="hds-input" name="%1$s" value="%2$s" min="0" aria-label="%5$s — حداقل"><span class="hds-range__txt">تا</span><input type="number" class="hds-input" name="%3$s" value="%4$s" min="0" aria-label="%5$s — حداکثر"><span class="hds-range__txt">روز</span>',
        esc_attr( $min_name ),
        esc_attr( (string) $min ),
        esc_attr( $max_name ),
        esc_attr( (string) $max ),
        esc_attr( $label )
    );
};
?>

<form method="post" action="">
    <?php wp_nonce_field('hodima_save_product_schema', 'hodima_product_schema_nonce'); ?>
    <input type="hidden" name="submit_product_schema" value="1">

    <section class="hds-card">
    <?php
    hodima_seo_ui_tabs( 'hodima-product', 'بخش‌های تنظیمات اسکیمای محصول', [
        'base'   => [
            'label' => 'پایه',
            'html'  => static function () use ( $is_enabled, $brand, $seller, $desc_tpl ): void {
                ?>
                <label class="hds-toggle">
                    <input type="checkbox" class="hds-switch" role="switch" name="hodima_schema_product_enable" value="1" <?php checked( $is_enabled, '1' ); ?>>
                    <span>فعال‌سازی اسکیمای حرفه‌ای محصولات</span>
                </label>
                <p class="hds-field__help">اسکیمای پیش‌فرض ووکامرس غیرفعال و اسکیمای هدیما جایگزین آن می‌شود.</p>
                <div class="hds-fields">
                    <div class="hds-field">
                        <label class="hds-field__label" for="hodima_schema_product_brand">نام برند پیش‌فرض (Brand)</label>
                        <input type="text" class="hds-input" name="hodima_schema_product_brand" id="hodima_schema_product_brand" value="<?php echo esc_attr( $brand ); ?>">
                        <p class="hds-field__help">بارکد (GTIN) هر محصول و هر تنوع از فیلد «GTIN, UPC, EAN یا ISBN» خود ووکامرس (تب «انبار» محصول) خودکار خوانده می‌شود؛ فقط ۸، ۱۲، ۱۳ یا ۱۴ رقم.</p>
                    </div>
                    <div class="hds-field">
                        <label class="hds-field__label" for="hodima_schema_product_seller">نام فروشنده (Seller / Organization)</label>
                        <input type="text" class="hds-input" name="hodima_schema_product_seller" id="hodima_schema_product_seller" value="<?php echo esc_attr( $seller ); ?>">
                    </div>
                    <div class="hds-field hds-field--wide">
                        <label class="hds-field__label" for="hodima_schema_product_desc_tpl">الگوی توضیحات کوتاه</label>
                        <textarea class="hds-input" name="hodima_schema_product_desc_tpl" id="hodima_schema_product_desc_tpl" rows="3"><?php echo esc_textarea( $desc_tpl ); ?></textarea>
                        <p class="hds-field__help">اگر محصول توضیح کوتاه نداشته باشد این متن جایگزین می‌شود. متغیرها: <code class="hds-code">[product_name]</code> نام محصول، <code class="hds-code">[site_name]</code> نام سایت.</p>
                    </div>
                </div>
                <?php
            },
        ],
        'return' => [
            'label' => 'مرجوعی',
            'html'  => static function () use ( $return_days, $return_fees, $return_method, $refund_type ): void {
                ?>
                <h3 class="hds-card__title">سیاست مرجوعی</h3>
                <p class="hds-card__desc">برای نمایش کامل محصول در Google Shopping و گرفتن برچسب‌های ویژه لازم است.</p>
                <div class="hds-fields">
                    <div class="hds-field">
                        <label class="hds-field__label" for="hodima_schema_product_return_days">مهلت مرجوعی کالا</label>
                        <div class="hds-unit">
                            <input type="number" class="hds-input" name="hodima_schema_product_return_days" id="hodima_schema_product_return_days" value="<?php echo esc_attr( $return_days ); ?>" min="0">
                            <span>روز</span>
                        </div>
                        <p class="hds-field__help">مثال: ۷ یعنی ۷ روز ضمانت بازگشت وجه. ۰ یعنی مرجوعی پذیرفته نمی‌شود.</p>
                    </div>
                    <div class="hds-field">
                        <label class="hds-field__label" for="hodima_schema_product_return_fees">هزینه برگشت کالا</label>
                        <select class="hds-input" name="hodima_schema_product_return_fees" id="hodima_schema_product_return_fees">
                            <option value="customer" <?php selected( $return_fees, 'customer' ); ?>>با مشتری</option>
                            <option value="free" <?php selected( $return_fees, 'free' ); ?>>رایگان (با فروشگاه)</option>
                        </select>
                    </div>
                    <div class="hds-field">
                        <label class="hds-field__label" for="hodima_schema_product_return_method">روش برگشت کالا</label>
                        <select class="hds-input" name="hodima_schema_product_return_method" id="hodima_schema_product_return_method">
                            <option value="mail" <?php selected( $return_method, 'mail' ); ?>>ارسال (پست یا باربری)</option>
                            <option value="store" <?php selected( $return_method, 'store' ); ?>>تحویل حضوری در فروشگاه/انبار</option>
                            <option value="none" <?php selected( $return_method, 'none' ); ?>>ذکر نشود</option>
                        </select>
                    </div>
                    <div class="hds-field">
                        <label class="hds-field__label" for="hodima_schema_product_refund_type">نوع بازپرداخت کالای مرجوعی</label>
                        <select class="hds-input" name="hodima_schema_product_refund_type" id="hodima_schema_product_refund_type">
                            <option value="none" <?php selected( $refund_type, 'none' ); ?>>ذکر نشود</option>
                            <option value="full" <?php selected( $refund_type, 'full' ); ?>>بازگشت کامل وجه</option>
                            <option value="exchange" <?php selected( $refund_type, 'exchange' ); ?>>تعویض کالا</option>
                            <option value="credit" <?php selected( $refund_type, 'credit' ); ?>>اعتبار خرید (کیف پول/اعتبار فروشگاه)</option>
                        </select>
                        <p class="hds-field__help">گوگل بدون این فیلد هشدار غیرمهم «Missing field refundType» می‌دهد. گزینه‌ای را انتخاب کنید که واقعا در فروشگاه انجام می‌شود.</p>
                    </div>
                </div>
                <?php
            },
        ],
        'ship'   => [
            'label' => 'ارسال و تحویل',
            'html'  => static function () use ( $shipping_mode, $shipping_cost, $shipping_max, $handling_min, $handling_max, $transit_min, $transit_max, $china_handling_min, $china_handling_max, $china_transit_min, $china_transit_max, $hodima_range ): void {
                ?>
                <h3 class="hds-card__title">هزینه ارسال</h3>
                <div class="hds-fields">
                    <div class="hds-field">
                        <label class="hds-field__label" for="hodima_schema_product_shipping_mode">هزینه ارسال</label>
                        <select class="hds-input" name="hodima_schema_product_shipping_mode" id="hodima_schema_product_shipping_mode">
                            <option value="customer" <?php selected( $shipping_mode, 'customer' ); ?>>با مشتری، متغیر (بسته به حجم بار)</option>
                            <option value="fixed" <?php selected( $shipping_mode, 'fixed' ); ?>>مبلغ ثابت</option>
                        </select>
                        <p class="hds-field__help">«متغیر»: مبلغی به گوگل اعلام نمی‌شود (یا فقط سقف، اگر پر شود). اعلام «ارسال رایگان» وقتی رایگان نیست خلاف قوانین Merchant است.</p>
                    </div>
                    <?php // فقط فیلد مربوط به حالت دیده می‌شود (JS)؛ مقدار پنهان‌شده هم ذخیره می‌شود و بدون JS هر دو دیده می‌شوند ?>
                    <div class="hds-field" data-hds-when="hodima_schema_product_shipping_mode=fixed">
                        <label class="hds-field__label" for="hodima_schema_product_shipping_cost">مبلغ ثابت ارسال</label>
                        <div class="hds-unit">
                            <input type="text" class="hds-input" name="hodima_schema_product_shipping_cost" id="hodima_schema_product_shipping_cost" dir="ltr" value="<?php echo esc_attr( $shipping_cost ); ?>">
                            <span>تومان</span>
                        </div>
                        <p class="hds-field__help">۰ یعنی ارسال رایگان.</p>
                    </div>
                    <div class="hds-field" data-hds-when="hodima_schema_product_shipping_mode=customer">
                        <label class="hds-field__label" for="hodima_schema_product_shipping_max">سقف هزینه ارسال (اختیاری)</label>
                        <div class="hds-unit">
                            <input type="text" class="hds-input" name="hodima_schema_product_shipping_max" id="hodima_schema_product_shipping_max" dir="ltr" inputmode="numeric" value="<?php echo esc_attr( $shipping_max ); ?>">
                            <span>تومان</span>
                        </div>
                        <p class="hds-field__help">خالی = اعلام نشود. مبلغ‌ها به تومان‌اند؛ گوگل فقط کد رسمی ریال (IRR) را می‌پذیرد، پس اسکیما خودکار ×۱۰ می‌کند.</p>
                    </div>
                </div>

                <div class="hds-section">
                    <h3 class="hds-card__title">زمان تحویل</h3>
                    <p class="hds-card__desc">پردازش = بسته‌بندی و تحویل به پست؛ در راه = رساندن بسته به مشتری.</p>
                    <div class="hds-fields">
                        <div class="hds-field">
                            <span class="hds-field__label">زمان پردازش سفارش (Handling Time)</span>
                            <div class="hds-range"><?php $hodima_range( 'hodima_schema_product_handling_min', $handling_min, 'hodima_schema_product_handling_max', $handling_max, 'زمان پردازش سفارش' ); ?></div>
                        </div>
                        <div class="hds-field">
                            <span class="hds-field__label">زمان در راه بودن (Transit Time)</span>
                            <div class="hds-range"><?php $hodima_range( 'hodima_schema_product_transit_min', $transit_min, 'hodima_schema_product_transit_max', $transit_max, 'زمان در راه بودن' ); ?></div>
                        </div>
                        <div class="hds-field hds-field--wide">
                            <span class="hds-field__label">زمان ارسال کالای «موجود در انبار چین»</span>
                            <div class="hds-range">
                                <span class="hds-range__txt">پردازش</span>
                                <?php $hodima_range( 'hodima_schema_product_china_handling_min', $china_handling_min, 'hodima_schema_product_china_handling_max', $china_handling_max, 'پردازش کالای انبار چین' ); ?>
                                <span class="hds-range__txt">، در راه</span>
                                <?php $hodima_range( 'hodima_schema_product_china_transit_min', $china_transit_min, 'hodima_schema_product_china_transit_max', $china_transit_max, 'در راه بودن کالای انبار چین' ); ?>
                            </div>
                            <p class="hds-field__help">محصولات با وضعیت «موجود در انبار چین» این زمان را به‌عنوان deliveryTime به گوگل اعلام می‌کنند. اعداد پیش‌فرض تقریبی‌اند؛ با زمان واقعی تامین جایگزین کنید.</p>
                        </div>
                    </div>
                </div>
                <?php
            },
        ],
    ] );
    ?>
        <div class="hds-foot">
            <button type="submit" class="hds-btn">ذخیره تنظیمات</button>
            <span class="hds-field__help">همه سه بخش با هم ذخیره می‌شوند.</span>
        </div>
    </section>
</form>

</div></div>

<?php hodima_view_footer(); ?>
