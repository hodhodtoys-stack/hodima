<?php
/**
 * Hodima Commerce — فیلدهای محصول و قواعد سفارش عمده
 * Path: plugins/hodima-commerce/inc/woocommerce/product-fields.php
 *
 * منتقل‌شده از قالب (inc/woocommerce/product-hooks.php و product-page.php):
 * فیلدهای سفارشی محصول و قواعد تجاری مستقل از ظاهر قالب هستند و با
 * تعویض قالب نباید از بین بروند.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* =====================================================================
 * ۱. موجودی
 * ===================================================================== */

/**
 * وضعیت انبار: 'iran' | 'china' | 'out' | ''
 * ترکیب فیلد سفارشی «وضعیت موجودی» و موجودی خود ووکامرس.
 */
function hodima_product_stock_location( WC_Product $product ): string {

	$meta = (string) get_post_meta( $product->get_id(), '_stock_location_status', true );

	if ( 'out_of_stock' === $meta || ! $product->is_in_stock() ) {
		return 'out';
	}

	return [ 'iran_stock' => 'iran', 'china_stock' => 'china' ][ $meta ] ?? '';
}

/**
 * availability در اسکیما — دقیقا همان چیزی که بازدیدکننده می‌بیند.
 *
 * نسخه قبلی فقط is_in_stock() ووکامرس را می‌خواند. روی همین سایت محصولی
 * با «موجود در انبار چین» در اسکیما InStock با ارسال ۱ تا ۴ روزه بود.
 * ناهمخوانی availability بین صفحه و داده ساختاریافته از دلایل رد شدن
 * در Merchant Listings گوگل است.
 *
 *   انبار چین → BackOrder (موجود، ولی با تاخیر ارسال می‌شود)
 */
function hodima_product_schema_availability( WC_Product $product ): string {
	$map = [
		'out'   => 'OutOfStock',
		'china' => 'BackOrder',
	];

	return 'https://schema.org/' . ( $map[ hodima_product_stock_location( $product ) ] ?? 'InStock' );
}

/* ==========================================================
   10.   حداقل سفارش
   ========================================================== */
// 1. Add a custom field to the Product Data General tab
add_action( 'woocommerce_product_options_general_product_data', 'add_wholesale_price_custom_field' );
function add_wholesale_price_custom_field() {
    echo '<div class="options_group">';
    woocommerce_wp_text_input(
        array(
            'id'                => '_wholesale_price',
            'label'             => __( 'حداقل سفارش  (تومان)', 'woocommerce' ),
            'placeholder'       => 'قیمت عمده را وارد کنید',
            'desc_tip'          => 'true',
            'description'       => __( 'این قیمت زیر قیمت اصلی محصول نمایش داده خواهد شد.', 'woocommerce' ),
            'type'              => 'number',
            'custom_attributes' => array(
                'step' => 'any',
                'min'  => '0',
            ),
        )
    );
    echo '</div>';
}

// 2. Save the custom field value
add_action( 'woocommerce_process_product_meta', 'save_wholesale_price_custom_field' );
function save_wholesale_price_custom_field( $post_id ) {
    // عدد پاک (نه esc_attr که برای خروجی HTML است)؛ مقدار خالی حذف می‌شود
    $raw   = isset( $_POST['_wholesale_price'] ) ? wc_clean( wp_unslash( $_POST['_wholesale_price'] ) ) : '';
    $value = is_numeric( $raw ) && (float) $raw > 0 ? (string) (float) $raw : '';
    '' === $value ? delete_post_meta( $post_id, '_wholesale_price' ) : update_post_meta( $post_id, '_wholesale_price', $value );
}








/* ==========================================================
   11.   حداقل تعداد در صفحه محصول
   ========================================================== */
/* حداقل مبلغ سفارش بر اساس فیلد _wholesale_price */

// 1) حداقل تعداد در صفحه محصول (بر اساس حداقل مبلغ)
add_filter( 'woocommerce_quantity_input_args', function( $args, $product ) {
    if ( ! $product || ! is_a( $product, 'WC_Product' ) ) return $args;

    // فقط برای محصولات ساده
    if ( ! $product->is_type( 'simple' ) ) return $args;

    $min_amount = (float) get_post_meta( $product->get_id(), '_wholesale_price', true );
    $price      = (float) $product->get_price();

    if ( $min_amount > 0 && $price > 0 ) {
        $min_qty = (int) ceil( $min_amount / $price );
        if ( $min_qty < 1 ) $min_qty = 1;

        $args['min_value'] = $min_qty;

        // اگر مقدار پیش‌فرض کمتر بود، آن را هم اصلاح کن
        if ( empty( $args['input_value'] ) || (int)$args['input_value'] < $min_qty ) {
            $args['input_value'] = $min_qty;
        }
    }

    return $args;
}, 10, 2 );

// 2) اعتبارسنجی هنگام افزودن به سبد خرید
add_filter( 'woocommerce_add_to_cart_validation', function( $passed, $product_id, $quantity ) {
    $product = wc_get_product( $product_id );
    if ( ! $product || ! $product->is_type( 'simple' ) ) return $passed;

    $min_amount = (float) get_post_meta( $product_id, '_wholesale_price', true );
    $price      = (float) $product->get_price();

    if ( $min_amount > 0 && $price > 0 ) {
        $line_total = $price * $quantity;
        if ( $line_total < $min_amount ) {
            $min_qty = (int) ceil( $min_amount / $price );
            wc_add_notice( 'حداقل مبلغ سفارش این محصول ' . wc_price( $min_amount ) .
                ' است. حداقل تعداد لازم: ' . $min_qty . ' عدد.', 'error' );
            return false;
        }
    }
    return $passed;
}, 10, 3 );

// 3) اعتبارسنجی داخل سبد خرید (اگر کاربر مقدار را کم کرد)
add_action( 'woocommerce_check_cart_items', function() {
    foreach ( WC()->cart->get_cart() as $cart_item ) {
        $product = $cart_item['data'];
        if ( ! $product || ! $product->is_type( 'simple' ) ) continue;

        $min_amount = (float) get_post_meta( $product->get_id(), '_wholesale_price', true );
        $price      = (float) $product->get_price();

        if ( $min_amount > 0 && $price > 0 ) {
            $line_total = $price * $cart_item['quantity'];
            if ( $line_total < $min_amount ) {
                $min_qty = (int) ceil( $min_amount / $price );
                wc_add_notice(
                    'برای محصول "' . $product->get_name() . '" حداقل مبلغ سفارش ' .
                    wc_price( $min_amount ) . ' است. حداقل تعداد: ' . $min_qty . ' عدد.',
                    'error'
                );
            }
        }
    }
});





/* ==========================================================
   11.   وضعیت موجودی سفارشی
   ========================================================== */

// 1. افزودن فیلد انتخاب وضعیت موجودی در تب عمومی محصول
add_action( 'woocommerce_product_options_general_product_data', 'add_stock_location_custom_field' );

function add_stock_location_custom_field() {

    echo '<div class="options_group">';

    woocommerce_wp_select( array(
        'id'          => '_stock_location_status',
        'label'       => 'وضعیت موجودی',
        'description' => 'وضعیت انبار محصول را انتخاب کنید.',
        'desc_tip'    => true,
        'options'     => array(
            ''             => 'انتخاب کنید',
            'iran_stock'   => 'موجود در انبار ایران',
            'china_stock'  => 'موجود در انبار چین',
            'out_of_stock' => 'اتمام موجودی',
        ),
    ) );

    echo '</div>';
}


// 2. ذخیره مقدار فیلد
add_action( 'woocommerce_process_product_meta', 'save_stock_location_custom_field' );

function save_stock_location_custom_field( $post_id ) {

    if ( isset( $_POST['_stock_location_status'] ) ) {
        $value = sanitize_text_field( wp_unslash( $_POST['_stock_location_status'] ) );
        update_post_meta( $post_id, '_stock_location_status', $value );
    }
}


// 3. اعلان وضعیت انبار در خود قالب content-single-product.php چاپ می‌شود.
//    تابع قبلی به هوک woocommerce_single_product_summary وصل بود که این قالب
//    هرگز اجرا نمی‌کند (کد مرده) و حذف شد.


/* ==========================================================
   12.   کشور سازنده
   ----------------------------------------------------------
   فیلد انتخابی «کشور سازنده» (ایرانی/چینی) در تب عمومی «اطلاعات محصول»
   حذف شد: تکراری بود، چون کشور با ویژگی محصول «تولید» (تب ویژگی‌ها) ثبت
   می‌شود و ردیف «تولید» جدول مشخصات و countryOfOrigin اسکیما اول همان
   ویژگی را می‌خوانند.
   داده پاک نمی‌شود: متای قبلی `_hodima_country_of_origin` فقط برای
   محصولی که ویژگی «تولید» ندارد آخرین منبع جدول و اسکیما می‌ماند؛ با
   افزودن ویژگی «تولید» به محصول، همان ویژگی جایش را می‌گیرد.
   ========================================================== */

/** کد کشور ذخیره‌شده در متای قدیمی → نام فارسی (جدول مشخصات). */
function hodima_country_of_origin_options(): array {
    return (array) apply_filters( 'hodima_country_of_origin_options', [
        'IR' => [ 'choice' => 'ایرانی', 'label' => 'ایران' ],
        'CN' => [ 'choice' => 'چینی',  'label' => 'چین' ],
    ] );
}
