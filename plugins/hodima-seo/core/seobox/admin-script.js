/* =========================================================================
 * SeoBox — editor script (نسخه ۵)
 * -------------------------------------------------------------------------
 *   - تب‌های دسترس‌پذیر: فلش چپ/راست، Home/End، aria-selected و hidden.
 *     بدون جاوااسکریپت هر دو بخش دیده می‌شوند (hidden را فقط همین اسکریپت می‌گذارد).
 *   - پیش‌نمایش گوگل با مقادیر سرور (data-seobox): سال/ماه شمسی، سال
 *     میلادی، شعار و جداکننده. قبلا سال میلادی مرورگر (2026) به جای
 *     سال شمسی روی سایت (۱۴۰۵) نشان داده می‌شد و %currentmonth% و %gyear% ناپدید می‌شدند.
 *   - شمارنده به *پیکسل* («۴۵۳ از ۵۸۰ پیکسل»): گوگل عنوان را با عرض نمایش
 *     (حدود ۵۸۰ پیکسل) کوتاه می‌کند، نه با تعداد نویسه. فونت مرجع وزیرمتن و سقف‌ها
 *     مشترک با دستار سئو (SEO 3.5.4، seobox-metrics.php).
 *   - تب «ایندکس / نوایندکس» با انتخاب، زنده سبز یا قرمز می‌شود.
 *   - دکمه‌های متغیر، متغیر را در آخرین فیلد فعال (عنوان/توضیحات) درج می‌کنند.
 * ========================================================================= */
(() => {
    'use strict';

    /* خط‌کش مشترک با «دستار سئو» (seobox-metrics.php): سقف‌ها و آدرس فونت از سرور می‌آیند، نه عدد جدا در اینجا.
     * فونت مرجع وزیرمتن ۴۰۰ است تا روی هر دستگاه یک عدد بدهد (تا SEO 3.5.3: Arial/Tahoma هر دستگاه). */
    const PX    = window.hodimaSeoPx || {};
    const FAMILY = PX.family || 'HodimaSeoRef';
    const LIMITS = {
        title:       { px: PX.title?.max ?? 580, min: PX.title?.min_ratio ?? 0.5, size: PX.title?.font_px ?? 20 },
        description: { px: PX.description?.max ?? 920, min: PX.description?.min_ratio ?? 0.4, size: PX.description?.font_px ?? 14 }
    };

    /* وضعیت فونت: loading = هنوز نیامده، ready = دقیق، failed = تخمین با فونت سیستم */
    let fontState = PX.url && window.FontFace ? 'loading' : 'failed';
    const fontOf = (field) => `400 ${LIMITS[field].size}px ${fontState === 'ready' ? `'${FAMILY}'` : 'Arial, Tahoma, sans-serif'}`;
    const listeners = new Set();

    if (fontState === 'loading') {
        try {
            const face = new FontFace(FAMILY, `url(${PX.url})`, { weight: '400' });
            face.load().then(() => { document.fonts.add(face); fontState = 'ready'; })
                .catch(() => { fontState = 'failed'; })
                .finally(() => listeners.forEach((fn) => fn()));
        } catch { fontState = 'failed'; }
    }

    const ctx = document.createElement('canvas').getContext?.('2d') ?? null;

    const textWidth = (text, field) => {
        if (!ctx) return text.length * (field === 'title' ? 10 : 7);
        ctx.font = fontOf(field);
        return ctx.measureText(text).width;
    };

    /** کوتاه کردن به عرض پیکسلی، مثل نمایش گوگل */
    const truncate = (text, field, maxPx) => {
        if (textWidth(text, field) <= maxPx) return text;
        let lo = 0, hi = text.length;
        while (lo < hi) {
            const mid = Math.ceil((lo + hi) / 2);
            if (textWidth(text.slice(0, mid) + ' …', field) <= maxPx) lo = mid; else hi = mid - 1;
        }
        return text.slice(0, lo).trim() + ' …';
    };

    /** عنوان خود نوشته/ترم: فرم کلاسیک، فرم ترم یا ویرایشگر بلوکی */
    const objectTitle = () => {
        const el = document.getElementById('title') || document.querySelector('#edittag #name');
        if (el && el.value) return el.value;
        try {
            return window.wp?.data?.select?.('core/editor')?.getEditedPostAttribute?.('title') || '';
        } catch {
            return '';
        }
    };

    const escapeRe = (s) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

    function init(root) {

        let cfg = {};
        try { cfg = JSON.parse(root.dataset.seobox || '{}'); } catch { cfg = {}; }

        const vars    = cfg.vars || {};
        const sep     = (vars['%sep%'] || '-').trim();
        const titleIn = root.querySelector('#seobox_title');
        const descIn  = root.querySelector('#seobox_description');

        /** همان seobox_parse_variables + seobox_tidy_text سرور */
        const expand = (text) => {
            let out = String(text || '');
            const map = { ...vars, '%title%': cfg.title ?? objectTitle(), '%page%': '' };
            for (const [key, value] of Object.entries(map)) out = out.split(key).join(value ?? '');
            out = out.replace(/\s+/g, ' ').trim();
            if (sep) {
                const q = escapeRe(sep);
                out = out.replace(new RegExp('\\s' + q + '(?:\\s+' + q + ')+\\s', 'g'), ' ' + sep + ' ')
                         .replace(new RegExp('^' + q + '\\s+'), '')
                         .replace(new RegExp('\\s+' + q + '$'), '')
                         .trim();
            }
            return out;
        };

        /* ── تب‌ها ─────────────────────────────────────────────────── */
        const tabs = [...root.querySelectorAll('[role="tab"]')];

        const select = (tab, focus = false) => {
            tabs.forEach((t) => {
                const on    = t === tab;
                const panel = document.getElementById(t.getAttribute('aria-controls'));
                t.setAttribute('aria-selected', String(on));
                t.tabIndex = on ? 0 : -1;
                if (panel) panel.hidden = !on;
            });
            if (focus) tab.focus();
        };

        if (tabs.length) {
            root.classList.add('seobox--js');
            select(tabs[0]);
            tabs.forEach((tab, i) => {
                tab.addEventListener('click', () => select(tab));
                tab.addEventListener('keydown', (e) => {
                    // راست‌به‌چپ: فلش چپ = تب بعدی
                    const rtl  = getComputedStyle(root).direction === 'rtl';
                    const next = { ArrowLeft: rtl ? 1 : -1, ArrowRight: rtl ? -1 : 1 }[e.key];
                    let target = null;
                    if (next) target = tabs[(i + next + tabs.length) % tabs.length];
                    if (e.key === 'Home') target = tabs[0];
                    if (e.key === 'End') target = tabs[tabs.length - 1];
                    if (target) { e.preventDefault(); select(target, true); }
                });
            });
        }

        /* ── شمارنده پیکسلی + پیش‌نمایش ────────────────────────────── */
        const meter = (field, value) => {
            const limit   = LIMITS[field];
            const counter = root.querySelector(`[data-seobox-counter="${field}"]`);
            const bar     = root.querySelector(`[data-seobox-meter="${field}"]`);
            const loading = fontState === 'loading';
            const px      = Math.round(textWidth(value, field));
            const ratio   = loading ? 0 : px / limit.px;

            if (counter) {
                // فونت مرجع هنوز نیامده: عدد غلط نشان نده؛ نیامد (آفلاین): عدد «حدود» با برچسب تقریبی
                counter.textContent = loading ? 'در حال اندازه‌گیری…'
                    : (fontState === 'failed' ? `حدود ${px} از ${limit.px} پیکسل (تقریبی)` : `${px} از ${limit.px} پیکسل`);
                counter.classList.toggle('is-over', ratio > 1);
            }
            if (bar) {
                bar.style.inlineSize = Math.min(100, Math.round(ratio * 100)) + '%';
                bar.className = loading ? '' : (ratio > 1 ? 'is-over' : (ratio < limit.min ? 'is-short' : 'is-good'));
            }
        };

        const out = (name) => root.querySelector(`[data-seobox-out="${name}"]`);

        const render = () => {
            const title = expand(titleIn?.value || cfg.pattern || '%title% %sep% %sitename%');
            const desc  = descIn?.value ? expand(descIn.value) : '';
            const shown = desc || cfg.fallback || '';

            meter('title', title);
            meter('description', desc || cfg.fallback || '');

            const t = out('title');
            const d = out('description');
            if (t) t.textContent = truncate(title || vars['%sitename%'] || '', 'title', LIMITS.title.px);
            if (d) {
                d.textContent = shown ? truncate(shown, 'description', LIMITS.description.px) : 'گوگل توضیح را خودش از متن صفحه انتخاب می‌کند.';
                d.classList.toggle('is-placeholder', !shown);
            }
        };

        [titleIn, descIn, document.getElementById('title'), document.querySelector('#edittag #name')]
            .forEach((el) => el?.addEventListener('input', render));

        // ویرایشگر بلوکی: عنوان خارج از فرم تغییر می‌کند
        if (window.wp?.data?.subscribe && !document.getElementById('title')) {
            let last = objectTitle();
            window.wp.data.subscribe(() => {
                const now = objectTitle();
                if (now !== last) { last = now; render(); }
            });
        }

        /* ── رنگ تب «ایندکس / نوایندکس»: سبز = ایندکس، قرمز = نوایندکس ──
         * noindex قدیمی (افزونه قبلی) تا وقتی تیک حذفش نخورده، صفحه را
         * نوایندکس نگه می‌دارد؛ تب هم قرمز می‌ماند. */
        const stateTab = root.querySelector('.seobox__tab--state');
        if (stateTab) {
            const external = root.dataset.seoboxExternal === '1';
            const clear    = root.querySelector('[data-seobox-clear-legacy]');
            const icon     = stateTab.querySelector('.dashicons');
            const syncState = () => {
                const chosen  = root.querySelector('input[name="seobox_robot_index"]:checked')?.value;
                const noindex = chosen === 'noindex' || (external && !clear?.checked);
                stateTab.dataset.state = noindex ? 'noindex' : 'index';
                icon?.classList.toggle('dashicons-hidden', noindex);
                icon?.classList.toggle('dashicons-visibility', !noindex);
            };
            root.querySelectorAll('input[name="seobox_robot_index"]').forEach((el) => el.addEventListener('change', syncState));
            clear?.addEventListener('change', syncState);
            syncState();
        }

        /* ── درج متغیر ─────────────────────────────────────────────── */
        let target = titleIn;
        [titleIn, descIn].forEach((el) => el?.addEventListener('focus', () => { target = el; }));

        root.querySelectorAll('[data-seobox-var]').forEach((btn) => {
            btn.addEventListener('click', () => {
                if (!target) return;
                const token = btn.dataset.seoboxVar;
                const start = target.selectionStart ?? target.value.length;
                const end   = target.selectionEnd ?? target.value.length;
                const before = target.value.slice(0, start);
                const pad    = before && !/\s$/.test(before) ? ' ' : '';
                target.setRangeText(pad + token, start, end, 'end');
                target.focus();
                target.dispatchEvent(new Event('input', { bubbles: true }));
            });
        });

        listeners.add(render); // پس از بارگذاری فونت دوباره می‌سنجد
        render();
    }

    const boot = () => document.querySelectorAll('.seobox[data-seobox]').forEach(init);

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();
})();
