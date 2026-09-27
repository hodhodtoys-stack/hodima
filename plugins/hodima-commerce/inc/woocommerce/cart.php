<?php
/**
 * Hodima Commerce — حداقل مبلغ سفارش و نوار پیشرفت سبد خرید
 * Path: plugins/hodima-commerce/inc/woocommerce/cart.php
 *
 * بازنویسی نسخه قالب:
 *   - مبلغ حداقل سفارش قبلا ثابت (۱۰ میلیون) در کد بود؛ حالا در
 *     «ووکامرس ← پیکربندی ← عمومی» قابل تنظیم است (صفر = غیرفعال).
 *   - اسکریپت jQuery حذف شد؛ پنهان کردن دکمه تسویه حساب با CSS
 *     (:has) و ویژگی data-met انجام می‌شود و بعد از به‌روزرسانی AJAX
 *     سبد هم خودبه‌خود درست است. بدون JavaScript.
 *   - استایل‌های inline به فایل CSS با پالت سازمانی منتقل شد و نوار از
 *     المان بومی <progress> استفاده می‌کند.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const HODIMA_MIN_ORDER_OPTION = 'hodima_min_order_amount';

/** حداقل مبلغ سفارش (به واحد پول فروشگاه). صفر یعنی غیرفعال. */
function hodima_min_order_amount(): float {
    $amount = get_option( HODIMA_MIN_ORDER_OPTION, 10000000 );
    return max( 0.0, (float) apply_filters( 'hodima_min_order_amount', is_numeric( $amount ) ? (float) $amount : 0.0 ) );
}

/** جمع جزء سبد خرید فعلی، یا null اگر سبد در دسترس نباشد. */
function hodima_cart_subtotal(): ?float {
    return ( function_exists( 'WC' ) && WC()->cart ) ? (float) WC()->cart->get_subtotal() : null;
}

/* =========================================================================
 * ۱. تنظیم در پیکربندی ووکامرس
 * ========================================================================= */

add_filter( 'woocommerce_general_settings', static function ( array $settings ): array {

    $field = [
        [
            'title' => 'سفارش عمده',
            'type'  => 'title',
            'id'    => 'hodima_min_order_section',
        ],
        [
            'title'             => 'حداقل مبلغ سفارش',
            'desc'              => 'بدون رسیدن به این مبلغ، دکمه تسویه حساب نمایش داده نمی‌شود. صفر یعنی بدون محدودیت.',
            'id'                => HODIMA_MIN_ORDER_OPTION,
            'type'              => 'number',
            'default'           => '10000000',
            'custom_attributes' => [ 'min' => '0', 'step' => '1' ],
            'desc_tip'          => true,
        ],
        [
            'type' => 'sectionend',
            'id'   => 'hodima_min_order_section',
        ],
    ];

    return [ ...$settings, ...$field ];
} );

/* =========================================================================
 * ۲. اعمال سمت سرور
 * ========================================================================= */

add_action( 'woocommerce_check_cart_items', 'hodima_enforce_min_order_amount' );

function hodima_enforce_min_order_amount(): void {

    $minimum = hodima_min_order_amount();
    $total   = hodima_cart_subtotal();

    if ( $minimum <= 0 || null === $total || $total >= $minimum || ! ( is_cart() || is_checkout() ) ) {
        return;
    }

    if ( is_checkout() ) {
        wc_add_notice(
            sprintf(
                '<strong>توجه:</strong> حداقل مبلغ برای ثبت سفارش عمده <strong>%1$s</strong> است. مبلغ فعلی سبد خرید شما <strong>%2$s</strong> است.',
                wc_price( $minimum ),
                wc_price( $total )
            ),
            'error'
        );
    }

    // دکمه تسویه حساب سمت سرور هم حذف می‌شود، نه فقط با CSS
    remove_action( 'woocommerce_proceed_to_checkout', 'woocommerce_button_proceed_to_checkout', 20 );
}

/* =========================================================================
 * ۳. نوار پیشرفت
 * ========================================================================= */

add_action( 'woocommerce_before_cart_table', 'hodima_cart_progress_bar' );

function hodima_cart_progress_bar(): void {

    $minimum = hodima_min_order_amount();
    $total   = hodima_cart_subtotal();

    if ( $minimum <= 0 || null === $total ) {
        return;
    }

    $met     = $total >= $minimum;
    $percent = min( 100, (int) floor( $total / $minimum * 100 ) );
    ?>
    <div id="hodima-progress-wrapper" class="hodima-cart-progress" data-met="<?php echo $met ? 'true' : 'false'; ?>">
        <?php if ( $met ) : ?>
            <p class="hodima-cart-progress__done" role="status">
                <strong>سبد خرید شما به حد نصاب رسید.</strong>
                می‌توانید سفارش را نهایی کنید.
            </p>
        <?php else : ?>
            <p class="hodima-cart-progress__title" id="hodima-cart-progress-label">
                فقط <strong class="hodima-cart-progress__remaining"><?php echo wp_kses_post( wc_price( $minimum - $total ) ); ?></strong>
                دیگر تا فعال‌سازی سفارش عمده فاصله دارید.
            </p>
            <progress class="hodima-cart-progress__bar" max="100" value="<?php echo esc_attr( (string) $percent ); ?>" aria-labelledby="hodima-cart-progress-label">
                <?php echo esc_html( $percent . '%' ); ?>
            </progress>
            <p class="hodima-cart-progress__note">
                حداقل خرید عمده: <?php echo wp_kses_post( wc_price( $minimum ) ); ?>
            </p>
        <?php endif; ?>
    </div>
    <?php
}

/** به‌روزرسانی نوار بعد از افزودن محصول با AJAX (fragments ووکامرس). */
add_filter( 'woocommerce_add_to_cart_fragments', static function ( array $fragments ): array {
    ob_start();
    hodima_cart_progress_bar();
    $fragments['div#hodima-progress-wrapper'] = (string) ob_get_clean();
    return $fragments;
} );

add_action( 'wp_enqueue_scripts', static function (): void {

    if ( ! ( is_cart() || is_checkout() ) || hodima_min_order_amount() <= 0 ) {
        return;
    }

    $rel = 'assets/css/cart-progress.css';
    wp_enqueue_style( 'hodima-cart-progress', HODIMA_COMMERCE_URL . '/' . $rel, [], hodima_commerce_asset_version( $rel ) );
} );
