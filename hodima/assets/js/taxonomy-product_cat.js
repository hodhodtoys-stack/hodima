/**
 * Product category — sorting
 * Version: 2.0.0
 *
 * تغییرات:
 *   - مرتب‌سازی به صفحه ۱ برمی‌گردد. شماره صفحه در *مسیر* است
 *     (/cat/page/3/) نه در ?paged=؛ نسخه قبلی فقط ?paged را حذف می‌کرد و
 *     مرتب‌سازی روی صفحه ۳، صفحه ۳ ترتیب جدید را می‌آورد (یا ۴۰۴).
 *   - دکمه برگشت مرورگر: نسخه قبلی آدرس را عوض می‌کرد ولی محصولات را نه.
 *   - صفحه‌بندی همراه محصولات جایگزین می‌شود (حتی اگر قبلا نبود یا حالا نیست).
 *   - درخواست‌های پشت سر هم: قبلی لغو می‌شود.
 *   - aria-pressed / aria-expanded و Escape برای منوی قیمت.
 *   - راه‌اندازی مستقل از DOMContentLoaded (Delay JS لایت‌اسپید).
 */
(function () {
    'use strict';

    function init() {

        var scope = document.querySelector('.section-products');
        var group = document.querySelector('.hodima-custom-sort-wrapper');
        if (!scope || !group) return;

        var priceWrap = group.querySelector('.price-sort-wrapper');
        var priceBtn  = group.querySelector('[data-sort-type="price-group"]');
        var ctrl      = null;

        function stripPage(url) {
            url.pathname = url.pathname.replace(/\/page\/\d+\/?$/, '/');
            url.searchParams.delete('paged');
            url.searchParams.delete('product-page');
            return url;
        }

        function markActive(orderby) {
            group.querySelectorAll('.hodima-sort-btn, .hodima-sort-sub-btn').forEach(function (b) {
                b.classList.remove('active');
                if (b.hasAttribute('aria-pressed')) b.setAttribute('aria-pressed', 'false');
            });

            var target = null;
            if (orderby === 'date' || orderby === 'popularity') {
                target = group.querySelector('[data-sort-type="' + orderby + '"]');
            } else if (orderby === 'price' || orderby === 'price-desc') {
                target = group.querySelector('[data-orderby="' + orderby + '"]');
                if (priceBtn) priceBtn.classList.add('active');
            }
            if (target) {
                target.classList.add('active');
                target.setAttribute('aria-pressed', 'true');
            }
        }

        function setMenu(open) {
            if (!priceWrap || !priceBtn) return;
            priceWrap.classList.toggle('open', open);
            priceBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        }

        /** محصولات و صفحه‌بندی را از HTML صفحه دیگر جایگزین کن */
        function swap(doc) {
            var fresh = doc.querySelector('.section-products ul.products');
            var now   = scope.querySelector('ul.products');
            if (!fresh || !now) return false;

            now.replaceWith(fresh);

            var oldNav = scope.querySelector('.woocommerce-pagination');
            var newNav = doc.querySelector('.section-products .woocommerce-pagination');
            if (oldNav && newNav) oldNav.replaceWith(newNav);
            else if (oldNav) oldNav.remove();
            else if (newNav) fresh.insertAdjacentElement('afterend', newNav);

            return true;
        }

        function load(url, push) {

            if (ctrl) ctrl.abort();
            ctrl = new AbortController();

            scope.classList.add('is-loading');
            scope.setAttribute('aria-busy', 'true');

            return fetch(url.toString(), { signal: ctrl.signal, credentials: 'same-origin' })
                .then(function (res) {
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    return res.text();
                })
                .then(function (html) {
                    var doc = new DOMParser().parseFromString(html, 'text/html');
                    if (!swap(doc)) throw new Error('no products');

                    if (push) history.pushState({ hodimaSort: true }, '', url.toString());
                    markActive(url.searchParams.get('orderby'));

                    var top = scope.getBoundingClientRect().top + window.pageYOffset - 100;
                    if (top < window.pageYOffset) window.scrollTo({ top: top, behavior: 'smooth' });
                })
                .catch(function (err) {
                    if (err && err.name === 'AbortError') return;
                    window.location.href = url.toString();   // بازگشت امن: بارگذاری کامل
                })
                .then(function () {
                    scope.classList.remove('is-loading');
                    scope.removeAttribute('aria-busy');
                });
        }

        function applySort(orderby) {
            var url = stripPage(new URL(window.location.href));
            url.searchParams.set('orderby', orderby);
            setMenu(false);
            load(url, true);
        }

        group.addEventListener('click', function (e) {

            var sub = e.target.closest('.hodima-sort-sub-btn');
            if (sub) { e.preventDefault(); applySort(sub.getAttribute('data-orderby')); return; }

            var btn = e.target.closest('.hodima-sort-btn');
            if (!btn) return;
            e.preventDefault();

            var type = btn.getAttribute('data-sort-type');
            if (type === 'price-group') setMenu(!priceWrap.classList.contains('open'));
            else applySort(type);
        });

        document.addEventListener('click', function (e) {
            if (priceWrap && !priceWrap.contains(e.target)) setMenu(false);
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && priceWrap && priceWrap.classList.contains('open')) {
                setMenu(false);
                if (priceBtn) priceBtn.focus();
            }
        });

        // دکمه برگشت/جلو مرورگر
        window.addEventListener('popstate', function () {
            load(new URL(window.location.href), false);
        });

        markActive(new URL(window.location.href).searchParams.get('orderby'));
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
