<?php
/**
 * ماژول اختصاصی مدیریت سبد خرید ووکامرس (hodima)
 * هماهنگ شده برای نمایش نوار پیشرفت، کنترل حداقل سفارش، و اصلاحات UI
 */

if (!defined('ABSPATH')) {
    exit;
}

// ۱. تعریف مبلغ حداقل سفارش (10 میلیون تومان)
define('hodima_MIN_ORDER_AMOUNT', 10000000);

/**
 * بررسی حداقل مبلغ سفارش در سبد خرید و تسویه حساب
 */
add_action('woocommerce_check_cart_items', 'hodima_enforce_min_order_amount');
function hodima_enforce_min_order_amount() {
    if (is_cart() || is_checkout()) {
        $cart_total = WC()->cart->get_subtotal();

        if ($cart_total < hodima_MIN_ORDER_AMOUNT) {
            if (is_checkout()) {
                wc_add_notice(
                    sprintf(
                        '<strong>توجه:</strong> حداقل مبلغ برای ثبت سفارش عمده <strong>%s</strong> می‌باشد. مبلغ فعلی سبد خرید شما <strong>%s</strong> است.',
                        wc_price(hodima_MIN_ORDER_AMOUNT),
                        wc_price($cart_total)
                    ),
                    'error'
                );
            }
            // حذف دکمه تسویه حساب در PHP برای امنیت بیشتر
            remove_action('woocommerce_proceed_to_checkout', 'woocommerce_button_proceed_to_checkout', 20);
        }
    }
}

/**
 * ساخت نوار پیشرفت (Progress Bar) داینامیک
 */
add_action('woocommerce_before_cart_table', 'hodima_cart_progress_bar');
function hodima_cart_progress_bar() {
    $cart_total = WC()->cart->get_subtotal();
    $minimum    = hodima_MIN_ORDER_AMOUNT;
    $percentage = ($cart_total / $minimum) * 100;
    $percentage = $percentage > 100 ? 100 : $percentage;
    $remaining  = $minimum - $cart_total;

    // کانتینر اصلی با ID برای هماهنگی با JS و AJAX
    echo '<div id="hodima-progress-wrapper" data-min="' . esc_attr($minimum) . '" data-total="' . esc_attr($cart_total) . '">';
    
    if ($cart_total < $minimum) {
        ?>
        <div class="hodima-cart-progress-wrapper" style="background: #fff; padding: 25px 20px; border-radius: 15px; margin-bottom: 25px; border: 1px solid #e1e8ed; box-shadow: 0 4px 15px rgba(0,0,0,0.02); text-align: center;">
            <h4 style="color: #2c3e50; margin: 0 0 15px 0; font-size: 1.15rem; font-weight: 800;">
                فقط <span id="hodima-remaining-text" style="color: #FF9800;"><?php echo wc_price($remaining); ?></span> دیگر تا فعال‌سازی سفارش عمده فاصله دارید!
            </h4>
            <div style="background: #EEF5F2; border-radius: 50px; height: 12px; width: 100%; overflow: hidden; position: relative;">
                <div id="hodima-progress-bar" style="background: linear-gradient(90deg, #FF9800, #F57C00, #FFB74D); width: <?php echo $percentage; ?>%; height: 100%; border-radius: 50px; transition: width 0.6s ease;"></div>
            </div>
            <p style="margin: 12px 0 0 0; font-size: 0.9rem; color: #7f8c8d; font-weight: 600;">حداقل خرید عمده: <?php echo wc_price($minimum); ?></p>
        </div>
        <?php
    } else {
        ?>
        <div class="hodima-cart-progress-success" style="background: #f0fdf4; padding: 15px; border-radius: 12px; margin-bottom: 20px; text-align: center; border: 1px solid #bbf7d0;">
            <h3 style="color: #15803d; margin: 0 0 8px 0; font-size: 1.35rem; font-weight: 900;">🎉 تبریک</h3>
            <p style="color: #166534; margin: 0; font-size: 1.05rem;">سبد خرید شما به حد نصاب رسید. می‌توانید سفارش را نهایی کنید.</p>
        </div>
        <?php
    }
    echo '</div>';
}

/**
 * آپدیت ایجکسی نوار پیشرفت با استفاده از WooCommerce Fragments
 */
add_filter('woocommerce_add_to_cart_fragments', 'hodima_update_progress_bar_ajax');
function hodima_update_progress_bar_ajax($fragments) {
    ob_start();
    hodima_cart_progress_bar();
    $fragments['div#hodima-progress-wrapper'] = ob_get_clean();
    return $fragments;
}

/**
 * تزریق CSS و JavaScript به فوتر سایت
 */
add_action('wp_footer', 'hodima_inject_custom_assets');
function hodima_inject_custom_assets() {
    if (!is_cart() && !is_checkout()) return;
    ?>
    

    <script>
    jQuery(document).ready(function($) {
        function updatehodimaUI() {
            var wrapper = $('#hodima-progress-wrapper');
            if (!wrapper.length) return;

            var minAmount = parseInt(wrapper.attr('data-min'));
            var currentTotal = parseInt(wrapper.attr('data-total'));

            if (currentTotal < minAmount) {
                $('body').addClass('min-order-not-met');
            } else {
                $('body').removeClass('min-order-not-met');
            }
        }

        // اجرای اولیه برای بررسی وضعیت دکمه
        updatehodimaUI();

        // آپدیت وضعیت دکمه بعد از درخواست‌های ایجکس ووکامرس
        $(document.body).on('updated_cart_totals', function() {
            updatehodimaUI(); // بررسی مجدد مقادیر پس از جایگزینی ایجکسی فرگمنت
        });
    });
    </script>
    <?php
}
