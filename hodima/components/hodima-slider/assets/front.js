/**
 * Hodima Slider — v3.0.0
 *
 *   - اسلایدهای بعدی تنبل‌بار: تصویر هر اسلاید درست پیش از نمایش (و اسلاید
 *     بعدی پیشاپیش) بارگذاری می‌شود. قبلا همه بنرها همزمان با تصویر LCP.
 *   - توقف: hover موس، فوکوس کیبورد، خارج شدن از دید، پنهان شدن تب؛ بدون
 *     پخش خودکار برای prefers-reduced-motion.
 *   - کشیدن با موس و سوایپ لمسی (Pointer Events، مثل سایت‌های بزرگ): اسلاید
 *     هنگام کشیدن کمی دنبال انگشت/موس می‌آید و با رها کردن جابه‌جا می‌شود.
 *   - کلیدهای فلش راست‌به‌چپ.
 *   - راه‌اندازی مستقل از DOMContentLoaded (Delay JS لایت‌اسپید).
 *   - بدون beforeunload (مانع bfcache در فایرفاکس بود و کاری نمی‌کرد).
 */
(function () {
    'use strict';

    var STR = window.HodimaSliderFront || {};
    var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var isRTL = (document.documentElement.getAttribute('dir') || getComputedStyle(document.documentElement).direction) === 'rtl';

    /** بارگذاری تصویر اسلادی که هنوز data-src دارد */
    function hydrate(slide) {
        if (!slide || slide.dataset.hydrated) return;
        slide.querySelectorAll('source[data-srcset]').forEach(function (s) {
            s.srcset = s.dataset.srcset;
            s.removeAttribute('data-srcset');
        });
        slide.querySelectorAll('img[data-src]').forEach(function (img) {
            if (img.dataset.srcset) { img.srcset = img.dataset.srcset; img.removeAttribute('data-srcset'); }
            img.src = img.dataset.src;
            img.removeAttribute('data-src');
        });
        slide.dataset.hydrated = '1';
    }

    /* سازگاری با HTML کش‌شده نسخه قبلی (اندازه سفارشی با data-h-*) */
    function legacySizeVars(slider) {
        var d = slider.dataset;
        if (d.hW)  slider.style.setProperty('--h-w', d.hW + 'px');
        if (d.hH)  slider.style.setProperty('--h-h', d.hH + 'px');
        if (d.hWm) slider.style.setProperty('--h-w-mobile', d.hWm + 'px');
        if (d.hHm) slider.style.setProperty('--h-h-mobile', d.hHm + 'px');
    }

    function init(slider) {

        if (slider.dataset.hReady) return;
        slider.dataset.hReady = '1';

        legacySizeVars(slider);

        var slides = slider.querySelectorAll('.h-slide');
        if (slides.length < 2) return;

        var dots  = slider.querySelector('.h-dots');
        var current = 0, timer = null;
        var hovered = false, focused = false, visible = true, dragging = false;

        function setDot(i, on) {
            if (!dots || !dots.children[i]) return;
            dots.children[i].classList.toggle('active', on);
            dots.children[i].setAttribute('aria-current', on ? 'true' : 'false');
        }

        function setSlide(i, on) {
            var s = slides[i];
            s.classList.toggle('active', on);
            if (on) s.removeAttribute('aria-hidden'); else s.setAttribute('aria-hidden', 'true');
            s.querySelectorAll('a, button').forEach(function (el) {
                if (on) el.removeAttribute('tabindex'); else el.setAttribute('tabindex', '-1');
            });
        }

        function running() {
            return !reduce && !hovered && !focused && !dragging && visible && !document.hidden;
        }

        function schedule() {
            clearTimeout(timer);
            if (!running()) return;
            timer = setTimeout(function () { show(current + 1); }, parseInt(slides[current].dataset.dur || '5000', 10));
        }

        function show(idx) {
            var next = (idx + slides.length) % slides.length;
            if (next === current) { schedule(); return; }
            hydrate(slides[next]);
            setSlide(current, false); setDot(current, false);
            current = next;
            setSlide(current, true); setDot(current, true);
            hydrate(slides[(current + 1) % slides.length]);   // اسلاید بعدی پیشاپیش
            schedule();
        }

        // اسلاید دوم پیشاپیش، پس از بارگذاری کامل صفحه (رقابتی با LCP ندارد)
        if (document.readyState === 'complete') hydrate(slides[1]);
        else window.addEventListener('load', function () { hydrate(slides[1]); }, { once: true });

        if (dots) {
            dots.addEventListener('click', function (e) {
                var d = e.target.closest('.h-dot');
                if (d) show(parseInt(d.dataset.index, 10));
            });
        }

        slider.addEventListener('mouseenter', function () { hovered = true; schedule(); });
        slider.addEventListener('mouseleave', function () { hovered = false; schedule(); });
        slider.addEventListener('focusin', function () { focused = true; schedule(); });
        slider.addEventListener('focusout', function (e) {
            if (!slider.contains(e.relatedTarget)) { focused = false; schedule(); }
        });

        // کلیدهای فلش وقتی فوکوس داخل اسلایدر است (RTL: چپ = بعدی)
        slider.addEventListener('keydown', function (e) {
            if (e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') return;
            e.preventDefault();
            var forward = (e.key === 'ArrowLeft') === isRTL;
            show(current + (forward ? 1 : -1));
        });

        if ('IntersectionObserver' in window) {
            new IntersectionObserver(function (entries) {
                visible = entries[0].isIntersecting;
                schedule();
            }, { threshold: 0.2 }).observe(slider);
        }

        document.addEventListener('visibilitychange', schedule);

        /*
         * کشیدن / سوایپ با Pointer Events — موس، لمس و قلم با یک کد.
         * RTL: کشیدن به راست = بعدی. touch-action: pan-y (CSS) اسکرول عمودی
         * صفحه را آزاد می‌گذارد؛ اگر مرورگر حرکت را اسکرول تشخیص دهد
         * pointercancel می‌فرستد و اسلاید سر جایش برمی‌گردد.
         */
        var startX = 0, startY = 0, dx = 0, pid = null, moved = false, axis = '';

        function activeSlide() { return slides[current]; }

        slider.addEventListener('pointerdown', function (e) {
            if (e.pointerType === 'mouse' && e.button !== 0) return;
            if (e.target.closest('.h-controls')) return;
            pid = e.pointerId; startX = e.clientX; startY = e.clientY; dx = 0; moved = false; axis = '';
            dragging = true;
            schedule();
        });

        slider.addEventListener('pointermove', function (e) {
            if (e.pointerId !== pid) return;
            dx = e.clientX - startX;
            var dy = e.clientY - startY;
            if (!axis && (Math.abs(dx) > 8 || Math.abs(dy) > 8)) {
                axis = Math.abs(dx) > Math.abs(dy) ? 'x' : 'y';
                if (axis === 'x') {
                    try { slider.setPointerCapture(pid); } catch (err) {}
                    slider.classList.add('is-dragging');
                }
            }
            if (axis === 'x') {
                moved = true;
                // دنبال کردن ملایم انگشت/موس (یک‌سوم فاصله، حداکثر ۱۲۰ پیکسل)
                var shift = Math.max(-120, Math.min(120, dx / 3));
                activeSlide().style.transform = 'translateX(' + shift + 'px)';
            }
        });

        function release(e, cancelled) {
            if (e.pointerId !== pid) return;
            var slide = activeSlide();
            slide.style.transform = '';
            slider.classList.remove('is-dragging');
            if (!cancelled && axis === 'x' && Math.abs(dx) > 50) {
                var forward = isRTL ? dx > 0 : dx < 0;
                show(current + (forward ? 1 : -1));
            }
            pid = null; dragging = false; axis = '';
            schedule();
        }
        slider.addEventListener('pointerup', function (e) { release(e, false); });
        slider.addEventListener('pointercancel', function (e) { release(e, true); });

        // بعد از کشیدن، کلیک روی لینک اسلاید اجرا نشود
        slider.addEventListener('click', function (e) {
            if (moved) { e.preventDefault(); e.stopPropagation(); moved = false; }
        }, true);

        // جلوگیری از کشیدن پیش‌فرض تصویر توسط مرورگر
        slider.addEventListener('dragstart', function (e) { e.preventDefault(); });

        schedule();
    }

    function boot() {
        document.querySelectorAll('.h-slider').forEach(init);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
