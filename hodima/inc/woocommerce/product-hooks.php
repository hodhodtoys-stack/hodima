<?php
/**
 * WooCommerce Hooks & Filters — نسخه نهایی اصلاح‌شده
 *
 * گالری ۱۰۰% سفارشی + بازگرداندن بخش دیدگاه‌ها و ستاره‌ها
 *
 * @package hodima
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* ==========================================================
   ۱. اصلاحات آرشیو و کارت محصولات
   ========================================================== */

add_filter( 'woocommerce_loop_add_to_cart_link', '__return_empty_string' );
add_filter( 'woocommerce_product_add_to_cart_text', '__return_empty_string' );

/* ==========================================================
   ۲. پاکسازی صفحات آرشیو
   ========================================================== */

function hodima_remove_archive_clutter() {
    if ( is_product_category() || is_product_tag() ) {
        remove_action( 'woocommerce_before_shop_loop', 'woocommerce_result_count',    20 );
        remove_action( 'woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30 );
    }
}
add_action( 'wp', 'hodima_remove_archive_clutter' );

/* ==========================================================
   ۳. پاکسازی صفحه محصول و بازگرداندن دیدگاه‌ها
   ========================================================== */

remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_title',   5  );
remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20 );
remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta',    40 );

function hodima_cleanup_single_product() {
    if ( ! is_product() ) {
        return;
    }
    
    // حذف تب‌ها، محصولات مرتبط و سایدبار
    remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_product_data_tabs', 10 );
    remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_related_products',  20 );
    remove_action( 'woocommerce_sidebar',                      'woocommerce_get_sidebar',              10 );

    // ✅ بازگرداندن بخش دیدگاه‌ها به زیر محصول (چون تب‌ها حذف شده بودند)
    add_action( 'woocommerce_after_single_product_summary', 'comments_template', 30 );
}
add_action( 'wp', 'hodima_cleanup_single_product' );

/* ==========================================================
   ۴. حذف کامل ساپورت گالری پیش‌فرض
   ========================================================== */

function hodima_remove_woo_gallery_support() {
    remove_theme_support( 'wc-product-gallery-zoom' );
    remove_theme_support( 'wc-product-gallery-lightbox' );
    remove_theme_support( 'wc-product-gallery-slider' );
}
add_action( 'after_setup_theme', 'hodima_remove_woo_gallery_support', 100 );

remove_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_images', 20 );
add_action(    'woocommerce_before_single_product_summary', 'hodima_custom_product_gallery',  20 );

/* ==========================================================
   ۵. گالری سفارشی (بدون تغییر)
   ========================================================== */

function hodima_custom_product_gallery() {
    global $product;
    if ( ! $product ) return;

    $all_image_ids = array_values( array_filter( array_merge(
        [ (int) $product->get_image_id() ],
        array_map( 'intval', (array) $product->get_gallery_image_ids() )
    ) ) );

    if ( empty( $all_image_ids ) ) {
        echo '<div class="sp-gallery"><div class="sp-gallery__main"><div class="sp-gallery__main-image">'
            . '<img src="' . esc_url( wc_placeholder_img_src( 'woocommerce_single' ) ) . '" alt="' . esc_attr( $product->get_name() ) . '" width="600" height="600" />'
            . '</div></div></div>';
        return;
    }

    $total = count( $all_image_ids );
    $name  = $product->get_name();
    $sizes = function_exists( 'hodima_gallery_sizes' ) ? hodima_gallery_sizes() : '(max-width: 768px) 100vw, 600px';
    ?>
    <div class="sp-gallery" data-total="<?php echo esc_attr( $total ); ?>" role="region" aria-roledescription="گالری" aria-label="<?php echo esc_attr( 'تصاویر ' . $name ); ?>">
        <div class="sp-gallery__main">
            <div class="sp-gallery__main-image">
                <?php foreach ( $all_image_ids as $index => $image_id ) :
                    $meta   = wp_get_attachment_image_src( $image_id, 'woocommerce_single' );
                    $full   = wp_get_attachment_image_url( $image_id, 'full' );
                    $srcset = wp_get_attachment_image_srcset( $image_id, 'woocommerce_single' );
                    $alt    = get_post_meta( $image_id, '_wp_attachment_image_alt', true ) ?: $name;
                    if ( ! $meta ) continue;

                    /*
                     * هیچ src خالی (نسخه قبلی: src="" که HTML نامعتبر است و
                     * گوگل ایمیجز ۴ تصویر از ۵ تصویر را از HTML کشف نمی‌کرد).
                     *
                     *   اسلاید اول (LCP): تصویر اصلی + srcset + fetchpriority="high"
                     *     — همان srcset که در head پیش‌بارگذاری شده.
                     *   بقیه: src = همان تامبنیلی که نوار تامبنیل‌ها *از قبل*
                     *     بارگذاری می‌کند (درخواست اضافه صفر، از کش)، و data-src
                     *     تصویر کامل که JS هنگام نمایش جایگزین می‌کند.
                     *     loading="lazy" کافی نبود: اسلایدهای پنهان با opacity:0
                     *     روی هم و *داخل* دید هستند و مرورگر همه را بارگذاری می‌کرد.
                     * width/height جای تصویر را رزرو می‌کند (بدون CLS).
                     */
                    $first = ( 0 === $index );
                    $thumb = wp_get_attachment_image_url( $image_id, 'woocommerce_gallery_thumbnail' ) ?: $meta[0];
                    ?>
                    <img class="sp-gallery__slide <?php echo $first ? 'is-active' : ''; ?>"
                         src="<?php echo esc_url( $first ? $meta[0] : $thumb ); ?>"
                         data-src="<?php echo esc_url( $meta[0] ); ?>"
                         data-full="<?php echo esc_url( (string) $full ); ?>"
                         <?php if ( $first && $srcset ) : ?>srcset="<?php echo esc_attr( $srcset ); ?>" sizes="<?php echo esc_attr( $sizes ); ?>"<?php endif; ?>
                         width="<?php echo (int) $meta[1]; ?>" height="<?php echo (int) $meta[2]; ?>"
                         alt="<?php echo esc_attr( $alt ); ?>"
                         <?php echo $first ? 'fetchpriority="high"' : 'loading="lazy"'; ?> decoding="async"
                         data-index="<?php echo (int) $index; ?>" />
                <?php endforeach; ?>
            </div>
            <button type="button" class="sp-gallery__zoom" aria-label="بزرگنمایی تصویر">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line><line x1="11" y1="8" x2="11" y2="14"></line><line x1="8" y1="11" x2="14" y2="11"></line></svg>
            </button>
        </div>
        <?php if ( $total > 1 ) : ?>
        <div class="sp-gallery__thumbs">
            <div class="sp-gallery__thumbs-track">
                <?php foreach ( $all_image_ids as $index => $image_id ) :
                    $thumb = wp_get_attachment_image_src( $image_id, 'woocommerce_gallery_thumbnail' );
                    if ( ! $thumb ) continue;
                ?>
                    <?php
                    /*
                     * نام دکمه از aria-label؛ تصویر داخلش تزئینی است (alt خالی)،
                     * چون همان تصویر اسلاید اصلی است. قبلا alt="thumb" بود و
                     * صفحه‌خوان پنج بار «thumb، دکمه» می‌خواند.
                     */
                    ?>
                    <button type="button" class="sp-gallery__thumb <?php echo 0 === $index ? 'is-active' : ''; ?>" data-index="<?php echo (int) $index; ?>"
                            aria-label="<?php echo esc_attr( sprintf( 'تصویر %d از %d', $index + 1, $total ) ); ?>"
                            aria-current="<?php echo 0 === $index ? 'true' : 'false'; ?>">
                        <img src="<?php echo esc_url( $thumb[0] ); ?>" alt="" width="<?php echo (int) $thumb[1]; ?>" height="<?php echo (int) $thumb[2]; ?>" loading="lazy" decoding="async" />
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php
    /*
     * لایت‌باکس: dialog واقعی. قبلا aria-hidden="true" بود ولی دکمه‌های
     * فوکوس‌پذیر داشت؛ hidden هم فوکوس و هم صفحه‌خوان را درست قطع می‌کند.
     * img بدون src (قبلا src="" نامعتبر) تا JS مقدار بدهد.
     */
    ?>
    <div class="sp-lightbox" role="dialog" aria-modal="true" aria-label="<?php echo esc_attr( 'نمایش بزرگ ' . $name ); ?>" aria-hidden="true" hidden>
        <div class="sp-lightbox__overlay"></div>
        <div class="sp-lightbox__content">
            <button type="button" class="sp-lightbox__close" aria-label="بستن">×</button>
            <button type="button" class="sp-lightbox__nav sp-lightbox__nav--prev" aria-label="تصویر قبلی">‹</button>
            <img class="sp-lightbox__img" alt="" />
            <button type="button" class="sp-lightbox__nav sp-lightbox__nav--next" aria-label="تصویر بعدی">›</button>
            <span class="sp-lightbox__counter" aria-live="polite"></span>
        </div>
    </div>
    <?php
}

/* ==========================================================
   ۶. اصلاح بخش اسکریپت‌ها (فعال کردن مجدد اسکریپت ستاره‌ها)
   ========================================================== */

function hodima_dequeue_woo_heavy_assets() {
    if ( is_product() ) {
        // حذف فقط اسکریپت‌های گالری
        wp_dequeue_script( 'zoom' );
        wp_deregister_script( 'zoom' );
        wp_dequeue_script( 'flexslider' );
        wp_deregister_script( 'flexslider' );
        wp_dequeue_script( 'photoswipe' );
        wp_deregister_script( 'photoswipe' );
        wp_dequeue_script( 'photoswipe-ui-default' );
        wp_deregister_script( 'photoswipe-ui-default' );

        // ✅✅ اسکریپت زیر برای تبدیل باکس به ستاره الزامی است و نباید حذف شود:
        // wp_dequeue_script( 'wc-single-product' ); 

        wp_dequeue_style( 'photoswipe' );
        wp_dequeue_style( 'photoswipe-default-skin' );
    }
    wp_dequeue_style( 'wc-blocks-style' );
}
add_action( 'wp_enqueue_scripts', 'hodima_dequeue_woo_heavy_assets', 99 );

/* ==========================================================
   ۷. jQuery
   ----------------------------------------------------------
   این بخش jQuery را deregister و یک handle خالی جایش ثبت می‌کرد.
   هر افزونه‌ای (درگاه پرداخت، فرم تماس، چت آنلاین) که در صفحه
   اصلی، فروشگاه، دسته‌بندی یا وبلاگ به jQuery نیاز داشت بی‌صدا از
   کار می‌افتاد. حذف امن در inc/performance/woo-optimizer.php انجام
   می‌شود: jQuery فقط وقتی بارگذاری نمی‌شود که هیچ اسکریپتی به آن
   وابسته نباشد. اسکریپت‌های خود قالب همه Vanilla JS هستند.
   ========================================================== */

/* ==========================================================
   ۸. حذف photoswipe HTML
   ========================================================== */

add_action( 'wp_footer', function() {
    if ( is_product() ) {
        remove_action( 'wp_footer', 'woocommerce_photoswipe', 15 );
    }
}, 1 );



/* ==========================================================
   9.   تغییر عنوان بخش Upsells در صفحه محصول
   ========================================================== */
add_filter( 'woocommerce_product_upsells_products_heading', 'hodima_custom_upsells_heading' );

function hodima_custom_upsells_heading( $heading ) {
    return 'محصولات مشابه';
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
   آخرین ردیف جدول مشخصات («کشور سازنده») و countryOfOrigin در اسکیما.
   کد کشور ذخیره می‌شود (IR / CN) و نام فارسی فقط در نمایش ساخته می‌شود.
   ========================================================== */

function hodima_country_of_origin_options(): array {
    return (array) apply_filters( 'hodima_country_of_origin_options', [
        'IR' => [ 'choice' => 'ایرانی', 'label' => 'ایران' ],
        'CN' => [ 'choice' => 'چینی',  'label' => 'چین' ],
    ] );
}

add_action( 'woocommerce_product_options_general_product_data', static function (): void {

    $choices = [ '' => 'انتخاب کنید' ];
    foreach ( hodima_country_of_origin_options() as $code => $opt ) {
        $choices[ $code ] = $opt['choice'];
    }

    echo '<div class="options_group">';
    woocommerce_wp_select( [
        'id'          => '_hodima_country_of_origin',
        'label'       => 'کشور سازنده',
        'description' => 'آخرین ردیف جدول مشخصات و «کشور مبدا» در داده ساختاریافته گوگل.',
        'desc_tip'    => true,
        'options'     => $choices,
    ] );
    echo '</div>';
} );

add_action( 'woocommerce_process_product_meta', static function ( $post_id ): void {

    if ( ! isset( $_POST['_hodima_country_of_origin'] ) ) {
        return;
    }

    $code = strtoupper( sanitize_key( wp_unslash( $_POST['_hodima_country_of_origin'] ) ) );

    isset( hodima_country_of_origin_options()[ $code ] )
        ? update_post_meta( (int) $post_id, '_hodima_country_of_origin', $code )
        : delete_post_meta( (int) $post_id, '_hodima_country_of_origin' );
} );
