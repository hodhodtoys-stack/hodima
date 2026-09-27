/**
 * Single product — quantity rules
 * Version: 2.0.0
 *
 * دو قانون حداقل در این قالب وجود دارد:
 *   ۱. حداقل *مبلغ* سفارش (فیلد «حداقل سفارش» محصول) — سمت سرور در
 *      product-hooks.php اعمال و به صورت ویژگی min روی فیلد تعداد چاپ می‌شود؛
 *      هنگام افزودن به سبد و در خود سبد هم بررسی می‌شود.
 *   ۲. «حداقل خرید» از جدول توضیح کوتاه — تعداد و گام (مضرب‌ها).
 *
 * نسخه قبلی قانون ۲ را بعد از بارگذاری صفحه اعمال می‌کرد و min سرور را
 * *بازنویسی* می‌کرد؛ اگر عدد جدول کمتر بود، حداقل پایین‌تر از حداقل
 * واقعی سرور نمایش داده می‌شد و مشتری بعد از کلیک خطا می‌گرفت. حالا
 * حداقل نهایی = بیشترینِ دو قانون، و گام از جدول.
 */
(function () {
    'use strict';

    function toLatin(str) {
        return String(str).replace(/[۰-۹]/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'.indexOf(d); })
                          .replace(/[٠-٩]/g, function (d) { return '٠١٢٣٤٥٦٧٨٩'.indexOf(d); });
    }

    function tableMinQty() {
        var rows = document.querySelectorAll('.woocommerce-product-details__short-description table tr');
        var found = 0;
        rows.forEach(function (row) {
            var cells = row.querySelectorAll('td, th');
            if (cells.length < 2) return;
            if (cells[0].textContent.indexOf('حداقل خرید') === -1) return;
            var m = toLatin(cells[1].textContent).match(/\d+/);
            if (m) found = parseInt(m[0], 10);
        });
        return found;
    }

    function init() {

        var qty = document.querySelector('form.cart .qty, .product-add-to-cart-box .qty');
        if (!qty) return;

        var serverMin = parseInt(qty.getAttribute('min'), 10) || 1;
        var tableMin  = tableMinQty();
        var step      = tableMin > 1 ? tableMin : (parseInt(qty.getAttribute('step'), 10) || 1);
        var min       = Math.max(serverMin, tableMin || 1);

        // حداقل باید مضربی از گام باشد
        if (step > 1 && min % step !== 0) min = Math.ceil(min / step) * step;

        if (min <= 1 && step <= 1) return;

        qty.min  = String(min);
        qty.step = String(step);
        if ((parseInt(qty.value, 10) || 0) < min) qty.value = String(min);

        qty.addEventListener('change', function () {
            var v = parseInt(toLatin(qty.value), 10);
            if (isNaN(v) || v < min) v = min;
            if (step > 1 && v % step !== 0) {
                var r = v % step;
                v = v - r + (r >= step / 2 ? step : 0);
                if (v < min) v = min;
            }
            qty.value = String(v);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
