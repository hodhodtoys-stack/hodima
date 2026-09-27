/**
 * Hodima Notifications — v3.0.0
 *
 * تغییرات:
 *   - قوانین وابسته به بازدیدکننده و زمان در مرورگر (صفحه کش‌شده برای همه
 *     یکسان است): تاریخ شروع/پایان، UTM، ارجاع‌دهنده، دستگاه. UTM و
 *     ارجاع‌دهنده قبلا ذخیره می‌شدند ولی هیچ‌جا بررسی نمی‌شدند. تطبیق در
 *     طول نشست باقی می‌ماند (کاربری که از کمپین آمده، در صفحه دوم هم).
 *   - بستن به خاطر سپرده می‌شود (در همان نشست دوباره نمایش داده نمی‌شود).
 *     قبلا با «تعداد نمایش = ۰» پاپ‌آپ در *هر* صفحه دوباره باز می‌شد.
 *   - تصویر پیش از نمایش بارگذاری می‌شود (نه در هر بازدید صفحه).
 *   - Escape، فوکوس داخل پنجره و بازگشت فوکوس؛ بستن دوباره ایمن
 *     (تایمر بستن خودکار بعد از بستن دستی، صف را خراب می‌کرد).
 */
(function () {
    'use strict';

    var SESSION_KEY = 'hd_notif_session';

    function store(kind) {
        try { return window[kind]; } catch (e) { return null; }
    }
    var ls = store('localStorage');
    var ss = store('sessionStorage');

    function sessionData() {
        try { return JSON.parse((ss && ss.getItem(SESSION_KEY)) || '{}') || {}; } catch (e) { return {}; }
    }
    function saveSession(d) {
        try { if (ss) ss.setItem(SESSION_KEY, JSON.stringify(d)); } catch (e) {}
    }

    /** UTM و ارجاع‌دهنده اولین ورود به نشست را نگه دار */
    var session = sessionData();
    (function captureLanding() {
        var changed = false;
        try {
            var params = new URLSearchParams(location.search);
            var utm = [];
            params.forEach(function (v, k) { if (k.indexOf('utm_') === 0 && v) utm.push(v.toLowerCase()); });
            if (utm.length) { session.utm = utm.join(' '); changed = true; }
        } catch (e) {}
        if (!session.refChecked) {
            session.refChecked = 1;
            try {
                var host = document.referrer ? new URL(document.referrer).hostname.toLowerCase() : '';
                if (host && host !== location.hostname.toLowerCase()) session.ref = host;
            } catch (e) {}
            changed = true;
        }
        if (changed) saveSession(session);
    })();

    var Queue = {
        items: [], busy: false,
        add: function (fn) { this.items.push(fn); this.run(); },
        run: function () {
            if (this.busy || !this.items.length) return;
            this.busy = true;
            this.items.shift()();
        },
        done: function () {
            var self = this;
            this.busy = false;
            setTimeout(function () { self.run(); }, 500);
        }
    };

    function passesClientRules(p) {
        var now = Date.now();
        var start = parseInt(p.dataset.start, 10) || 0;
        var end = parseInt(p.dataset.end, 10) || 0;
        if (start && now < start) return false;
        if (end && now > end) return false;

        var mobile = window.matchMedia ? window.matchMedia('(max-width: 768px)').matches : window.innerWidth <= 768;
        var device = p.dataset.device || 'all';
        if (device === 'mobile' && !mobile) return false;
        if (device === 'desktop' && mobile) return false;

        var utm = p.dataset.utm || '';
        if (utm && (session.utm || '').indexOf(utm) === -1) return false;

        var ref = p.dataset.referrer || '';
        if (ref && (session.ref || '').indexOf(ref.replace(/^https?:\/\//, '').replace(/\/.*$/, '')) === -1) return false;

        var dismissed = session.dismissed || {};
        if (dismissed[p.dataset.id]) return false;

        return true;
    }

    function loadImage(p) {
        return new Promise(function (resolve) {
            var img = p.querySelector('img[data-src]');
            if (!img) { resolve(); return; }
            var finished = false;
            var finish = function () { if (!finished) { finished = true; resolve(); } };
            img.addEventListener('load', finish, { once: true });
            img.addEventListener('error', finish, { once: true });
            img.src = img.dataset.src;
            img.removeAttribute('data-src');
            if (img.complete) finish();
            setTimeout(finish, 2500);   // شبکه کند: بعد از ۲.۵ ثانیه به هر حال نمایش
        });
    }

    function setup(p) {

        var wrapper = p.closest('.hd-notif-wrapper');
        if (!wrapper || !passesClientRules(p)) return;

        var id = p.dataset.id;
        var delay = parseInt(p.dataset.delay, 10) || 0;
        var autoClose = parseInt(p.dataset.autoClose, 10) || 0;
        var maxCount = parseInt(p.dataset.maxCount, 10) || 0;
        var animIn = p.dataset.animIn || 'fade_in';
        var animOut = p.dataset.animOut || 'fade_out';
        var position = p.dataset.position || 'center';
        var isModal = position === 'center';
        var trigExit = p.dataset.triggerExit === '1';
        var trigScroll = parseInt(p.dataset.triggerScroll, 10) || 0;
        var trigInact = parseInt(p.dataset.triggerInactivity, 10) || 0;
        var countKey = 'hd_notif_count_' + id;

        var count = 0;
        try { count = parseInt(ls && ls.getItem(countKey), 10) || 0; } catch (e) {}
        if (maxCount > 0 && count >= maxCount) return;

        var state = 'idle';   // idle → scheduled → shown → closed
        var lastFocus = null, autoTimer = null;
        var cleanups = [];

        function listen(target, type, fn, opts) {
            target.addEventListener(type, fn, opts);
            cleanups.push(function () { target.removeEventListener(type, fn, opts); });
        }
        function stopTriggers() { cleanups.forEach(function (f) { f(); }); cleanups = []; }

        function onKey(e) {
            if (e.key === 'Escape') { close(); return; }
            if (e.key === 'Tab' && isModal) {
                var f = p.querySelectorAll('a[href], button');
                if (!f.length) return;
                var first = f[0], last = f[f.length - 1];
                if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
                else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
            }
        }

        function show() {
            if (state !== 'scheduled') return;
            Queue.add(function () {
                loadImage(p).then(function () {
                    state = 'shown';
                    lastFocus = document.activeElement;
                    wrapper.style.display = isModal ? 'flex' : 'block';
                    requestAnimationFrame(function () {
                        wrapper.classList.add('hd-show');
                        p.classList.add('anim-in-' + animIn);
                        if (isModal) {
                            var btn = p.querySelector('.hd-notif-close');
                            (btn || p).focus({ preventScroll: true });
                        }
                    });
                    document.addEventListener('keydown', onKey);
                    if (maxCount > 0) {
                        try { if (ls) ls.setItem(countKey, String(count + 1)); } catch (e) {}
                    }
                    if (autoClose > 0) autoTimer = setTimeout(close, autoClose);
                });
            });
        }

        function schedule() {
            if (state !== 'idle') return;
            state = 'scheduled';
            stopTriggers();
            setTimeout(show, delay);
        }

        function close() {
            // فقط یک بار (قبلا تایمر بستن خودکار بعد از بستن دستی دوباره اجرا می‌شد)
            if (state !== 'shown') return;
            state = 'closed';
            clearTimeout(autoTimer);
            document.removeEventListener('keydown', onKey);

            p.classList.remove('anim-in-' + animIn);
            p.classList.add('anim-out-' + animOut);
            if (isModal) wrapper.style.backgroundColor = 'transparent';

            // در این نشست دوباره نمایش داده نشود
            session.dismissed = session.dismissed || {};
            session.dismissed[id] = 1;
            saveSession(session);

            setTimeout(function () {
                wrapper.classList.remove('hd-show');
                wrapper.style.display = 'none';
                if (lastFocus && typeof lastFocus.focus === 'function') lastFocus.focus({ preventScroll: true });
                Queue.done();
            }, 400);
        }

        var hasTriggers = trigExit || trigScroll > 0 || trigInact > 0;

        if (!hasTriggers) {
            schedule();
        } else {
            if (trigExit) {
                listen(document, 'mouseleave', function (e) { if (e.clientY < 0) schedule(); });
            }
            if (trigScroll > 0) {
                var checkScroll = function () {
                    var d = document.documentElement.scrollHeight, c = window.innerHeight;
                    if (d <= c || (window.scrollY / Math.max(1, d - c)) * 100 >= trigScroll) schedule();
                };
                listen(window, 'scroll', checkScroll, { passive: true });
                checkScroll();
            }
            if (trigInact > 0) {
                var inactTimer = null;
                var reset = function () {
                    clearTimeout(inactTimer);
                    inactTimer = setTimeout(schedule, trigInact * 1000);
                };
                ['mousemove', 'keydown', 'scroll', 'touchstart'].forEach(function (evt) {
                    listen(document, evt, reset, { passive: true });
                });
                cleanups.push(function () { clearTimeout(inactTimer); });
                reset();
            }
        }

        var closeBtn = p.querySelector('.hd-notif-close');
        if (closeBtn) closeBtn.addEventListener('click', close);

        // کلیک روی لینک پاپ‌آپ هم یعنی «دیده شد»؛ در صفحه بعد دوباره باز نشود
        var link = p.querySelector('.hd-notif-half-link');
        if (link) {
            link.addEventListener('click', function () {
                session.dismissed = session.dismissed || {};
                session.dismissed[id] = 1;
                saveSession(session);
            });
        }

        if (isModal) {
            wrapper.addEventListener('click', function (e) { if (e.target === wrapper) close(); });
        }
    }

    function boot() {
        document.querySelectorAll('.hd-notification-popup').forEach(setup);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
