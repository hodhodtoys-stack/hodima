/**
 * Media System — front-end coordinator
 * Path: media-system/js/media-style.js
 * Version: 3.0.0
 *
 *   - فقط یک رسانه همزمان پخش شود
 *   - وقتی رسانه کاملا از دید خارج شد، متوقف شود
 *
 * تغییر اصلی: راه‌اندازی مستقل از DOMContentLoaded. حالت «Delay JS»
 * لایت‌اسپید اسکریپت را *بعد از* این رویداد اجرا می‌کند و نسخه قبلی
 * هرگز ناظر توقف خودکار را وصل نمی‌کرد.
 */
(function () {
    'use strict';

    // یک رسانه همزمان — روی کل سند، پس پلیرهای دیگر (استوری، صفحه ویدیو) را هم پوشش می‌دهد
    document.addEventListener('play', function (e) {
        var t = e.target;
        if (t.tagName !== 'AUDIO' && t.tagName !== 'VIDEO') return;
        document.querySelectorAll('audio, video').forEach(function (m) {
            if (m !== t && !m.paused) m.pause();
        });
    }, true);

    // پایان ویدیو: بازگشت به کاور (load() پوستر را دوباره نمایش می‌دهد)
    document.addEventListener('ended', function (e) {
        if (e.target.tagName === 'VIDEO' && e.target.hasAttribute('poster')) {
            e.target.load();
        }
    }, true);

    function pauseIframe(frame) {
        if (!frame.contentWindow) return;
        try {
            frame.contentWindow.postMessage('{"event":"command","func":"pauseVideo","args":""}', '*'); // YouTube (enablejsapi=1)
            frame.contentWindow.postMessage('{"method":"pause"}', '*');                                 // Vimeo و سازگارها
        } catch (err) { /* پلیری که پیام را نمی‌پذیرد */ }
    }

    function init() {

        if (!('IntersectionObserver' in window)) return;

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) return;
                var el = entry.target;
                if (el.tagName === 'IFRAME') {
                    pauseIframe(el);
                } else if (!el.paused && !el.ended) {
                    el.pause();
                }
            });
        }, { threshold: 0 });

        document.querySelectorAll('.hook-video-el, .hook-audio-el, .hook-oembed-container iframe').forEach(function (m) {
            observer.observe(m);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
