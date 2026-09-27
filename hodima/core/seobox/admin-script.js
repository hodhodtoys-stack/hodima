/* =========================================================================
 * SeoBox — editor script
 * Version: 4.0.0
 * -------------------------------------------------------------------------
 * تغییرات نسبت به نسخه قبلی:
 *
 *   ۱. وابستگی به wp-data و wp-editor حذف شد. اسکریپت از هیچ‌کدام استفاده
 *      نمی‌کرد، ولی همین وابستگی باعث می‌شد روی صفحه ویرایش دسته‌بندی و
 *      صفحه محصول (ویرایشگر کلاسیک) کل بسته ویرایشگر بلوکی — چند مگابایت —
 *      لود شود.
 *
 *   ۲. سه بخش کد مرده حذف شد: پیش‌نمایش، آپلودر تصویر شبکه اجتماعی و دکمه
 *      ویرایشگر اسنیپت به عناصری اشاره می‌کردند که در HTML وجود نداشتند.
 *
 *   ۳. شمارنده حالا طول را به *پیکسل* می‌سنجد، نه کاراکتر. گوگل عنوان را
 *      بر اساس عرض نمایش (حدود ۵۸۰ پیکسل) کوتاه می‌کند؛ ۶۰ کاراکتر فارسی
 *      با حروف پهن ممکن است بریده شود و با حروف باریک جای خالی بماند.
 *
 *   ۴. پیش‌نمایش زنده نتیجه گوگل اضافه شد.
 * ========================================================================= */
(function () {
    'use strict';

    var LIMITS = {
        title:       { px: 580, font: '20px Arial, Tahoma, sans-serif' },
        description: { px: 920, font: '14px Arial, Tahoma, sans-serif' }
    };

    var canvas = document.createElement('canvas');
    var ctx    = canvas.getContext ? canvas.getContext('2d') : null;

    function textWidth(text, font) {
        if (!ctx) return text.length * (font.indexOf('20px') === 0 ? 10 : 7);
        ctx.font = font;
        return ctx.measureText(text).width;
    }

    /** کوتاه کردن به عرض پیکسلی، مثل نمایش گوگل */
    function truncate(text, font, maxPx) {
        if (textWidth(text, font) <= maxPx) return text;
        var lo = 0, hi = text.length;
        while (lo < hi) {
            var mid = Math.ceil((lo + hi) / 2);
            if (textWidth(text.slice(0, mid) + ' …', font) <= maxPx) lo = mid; else hi = mid - 1;
        }
        return text.slice(0, lo).trim() + ' …';
    }

    /** عنوان خود نوشته/ترم از فرم وردپرس، برای جایگزینی %title% */
    function objectTitle() {
        var el = document.getElementById('title') || document.getElementById('name');
        if (el && el.value) return el.value;
        if (window.wp && wp.data && wp.data.select && wp.data.select('core/editor')) {
            try { return wp.data.select('core/editor').getEditedPostAttribute('title') || ''; } catch (e) {}
        }
        return '';
    }

    function expand(text, site) {
        return String(text || '')
            .replace(/%title%/g, objectTitle())
            .replace(/%sitename%/g, site)
            .replace(/%sep%/g, '-')
            .replace(/%sitedesc%/g, '')
            .replace(/%currentyear%/g, String(new Date().getFullYear()))
            .replace(/%[a-z]+%/g, '')
            .replace(/\s{2,}/g, ' ')
            .trim();
    }

    document.addEventListener('DOMContentLoaded', function () {

        var root = document.querySelector('.seobox-wrapper-card');
        if (!root) return;

        /* ── تب‌ها ─────────────────────────────────────────────────── */
        var tabBtns = root.querySelectorAll('.seobox-tab-btn');
        var tabs    = root.querySelectorAll('.seobox-tab-content');

        tabBtns.forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                tabBtns.forEach(function (b) { b.classList.remove('active'); b.setAttribute('aria-selected', 'false'); });
                tabs.forEach(function (t) { t.classList.remove('active'); });
                btn.classList.add('active');
                btn.setAttribute('aria-selected', 'true');
                var target = document.getElementById('seobox-tab-' + btn.getAttribute('data-target'));
                if (target) target.classList.add('active');
            });
        });

        /* ── شمارنده پیکسلی + پیش‌نمایش ────────────────────────────── */
        var serp     = document.getElementById('seobox_serp');
        var site     = serp ? serp.getAttribute('data-site') || '' : '';
        var fallback = serp ? serp.getAttribute('data-fallback') || '' : '';

        var titleIn = document.getElementById('seobox_title');
        var descIn  = document.getElementById('seobox_description');

        function meter(field, value) {
            var cfg     = LIMITS[field];
            var counter = document.getElementById('seobox_' + field + '_counter');
            var bar     = document.getElementById('seobox_' + field + '_meter');
            var px      = Math.round(textWidth(value, cfg.font));
            var pct     = Math.min(100, Math.round((px / cfg.px) * 100));

            if (counter) {
                counter.textContent = value.length + ' کاراکتر · ' + px + ' / ' + cfg.px + ' پیکسل';
                counter.classList.toggle('over-limit', px > cfg.px);
            }
            if (bar) {
                bar.style.width = pct + '%';
                bar.className = px > cfg.px ? 'is-over' : (pct < 50 ? 'is-short' : 'is-good');
            }
        }

        function render() {
            var rawTitle = titleIn && titleIn.value ? titleIn.value : '%title% %sep% %sitename%';
            var title    = expand(rawTitle, site);
            var desc     = descIn && descIn.value ? expand(descIn.value, site) : fallback;

            meter('title', title);
            meter('description', descIn ? expand(descIn.value, site) : '');

            var t = document.getElementById('seobox_serp_title');
            var d = document.getElementById('seobox_serp_desc');
            if (t) t.textContent = truncate(title || site, LIMITS.title.font, LIMITS.title.px);
            if (d) d.textContent = desc ? truncate(desc, LIMITS.description.font, LIMITS.description.px) : '';
        }

        [titleIn, descIn, document.getElementById('title'), document.getElementById('name')].forEach(function (el) {
            if (el) el.addEventListener('input', render);
        });

        // ویرایشگر بلوکی: عنوان خارج از فرم تغییر می‌کند
        if (window.wp && wp.data && wp.data.subscribe) {
            var last = null;
            wp.data.subscribe(function () {
                var now = objectTitle();
                if (now !== last) { last = now; render(); }
            });
        }

        render();
    });
})();
