/**
 * Hodima Live Search — v3.0.0
 * -------------------------------------------------------------------------
 * تغییرات نسبت به ۱.۵.۴:
 *
 *   - چند جعبه جستجو در یک صفحه (مثلا هدر دسکتاپ و موبایل) پشتیبانی
 *     می‌شود. نسخه قبلی فقط اولی را راه‌اندازی می‌کرد.
 *   - کش sessionStorage حالا ۱۰ دقیقه انقضا دارد. نسخه قبلی تا پایان
 *     روز نگه می‌داشت؛ قیمت یا موجودی تغییرکرده در همان نشست قدیمی
 *     نمایش داده می‌شد.
 *   - ARIA طبق الگوی combobox نسخه ۱.۲: aria-expanded و
 *     aria-activedescendant روی خود input، aria-selected روی گزینه‌ها.
 *   - Enter بدون گزینه فعال، فرم را به صورت بومی ارسال می‌کند (بدون JS
 *     هم کار می‌کند).
 *   - پاسخ ۴۲۹ (محدودیت نرخ) پیام مناسب نشان می‌دهد نه «خطای سرور».
 */
;(function () {
    'use strict';

    if (typeof wooLiveSearch === 'undefined') return;

    var DEBOUNCE_MS = 300;
    var MIN_CHARS   = 2;
    var CACHE_TTL   = 10 * 60 * 1000;
    var CACHE_NS    = 'wls3:';

    function cacheGet(term) {
        try {
            var raw = sessionStorage.getItem(CACHE_NS + term);
            if (!raw) return null;
            var entry = JSON.parse(raw);
            if (!entry || Date.now() - entry.t > CACHE_TTL) {
                sessionStorage.removeItem(CACHE_NS + term);
                return null;
            }
            return entry.d;
        } catch (e) { return null; }
    }

    function cacheSet(term, data) {
        try { sessionStorage.setItem(CACHE_NS + term, JSON.stringify({ t: Date.now(), d: data })); } catch (e) {}
    }

    function el(tag, attrs, text) {
        var node = document.createElement(tag);
        Object.keys(attrs || {}).forEach(function (k) {
            if (k === 'className') node.className = attrs[k];
            else node.setAttribute(k, attrs[k]);
        });
        if (text) node.textContent = text;
        return node;
    }

    function init(wrapper) {

        var input    = wrapper.querySelector('.woo-live-search__input');
        var results  = wrapper.querySelector('.woo-search-results');
        var clearBtn = wrapper.querySelector('.woo-live-search__clear');

        if (!input || !results) return;

        var listId = results.id || ('woo-sr-' + Math.random().toString(36).slice(2));
        results.id = listId;

        var timer = null, abortCtrl = null, activeIndex = -1, items = [];

        function open() {
            results.classList.add('is-open');
            input.setAttribute('aria-expanded', 'true');
        }

        function close() {
            results.classList.remove('is-open');
            input.setAttribute('aria-expanded', 'false');
            input.removeAttribute('aria-activedescendant');
            activeIndex = -1;
            items.forEach(function (i) { i.classList.remove('is-active'); i.setAttribute('aria-selected', 'false'); });
        }

        function setLoading(state) { wrapper.classList.toggle('is-loading', state); }

        function setActive(idx) {
            items.forEach(function (item, i) {
                var on = i === idx;
                item.classList.toggle('is-active', on);
                item.setAttribute('aria-selected', on ? 'true' : 'false');
            });
            if (idx >= 0 && items[idx]) {
                items[idx].scrollIntoView({ block: 'nearest' });
                input.setAttribute('aria-activedescendant', items[idx].id);
            } else {
                input.removeAttribute('aria-activedescendant');
            }
        }

        function message(text, isError) {
            results.textContent = '';
            items = [];
            results.appendChild(el('div', {
                className: 'woo-search-results__message' + (isError ? ' is-error' : ''),
                role: 'status'
            }, text));
            open();
        }

        function allUrl(term) {
            return wooLiveSearch.allUrl.replace('{term}', encodeURIComponent(term));
        }

        function render(data, term) {
            results.textContent = '';
            activeIndex = -1;

            if (!Array.isArray(data) || !data.length) {
                message('محصولی یافت نشد');
                return;
            }

            data.forEach(function (p, i) {
                var a = el('a', { href: p.link, className: 'woo-search-results__item', role: 'option', id: listId + '-o' + i, 'aria-selected': 'false' });
                a.appendChild(el('img', { src: p.image, alt: '', className: 'woo-search-results__img', loading: 'lazy', decoding: 'async', width: '50', height: '50' }));
                a.appendChild(el('span', { className: 'woo-search-results__title' }, p.title));
                if (p.price) a.appendChild(el('span', { className: 'woo-search-results__price' }, p.price));
                results.appendChild(a);
            });

            results.appendChild(el('a', { href: allUrl(term), className: 'woo-search-results__all' }, 'مشاهده همه نتایج'));

            items = Array.prototype.slice.call(results.querySelectorAll('.woo-search-results__item'));
            open();
        }

        function search(term) {

            var cached = cacheGet(term);
            if (cached) {
                render(cached, term);
                setLoading(false);
                return;
            }

            if (abortCtrl) abortCtrl.abort();
            abortCtrl = new AbortController();

            var url = wooLiveSearch.ajaxurl + '?' + new URLSearchParams({ action: 'woo_live_search', term: term }).toString();

            fetch(url, { signal: abortCtrl.signal, credentials: 'same-origin' })
                .then(function (res) {
                    if (res.status === 429) {
                        var err = new Error('rate'); err.rate = true; throw err;
                    }
                    if (!res.ok) throw new Error('HTTP ' + res.status);
                    return res.json();
                })
                .then(function (data) {
                    cacheSet(term, data);
                    render(data, term);
                })
                .catch(function (err) {
                    if (err.name === 'AbortError') return;
                    message(err.rate ? 'تعداد جستجوها زیاد بود؛ چند ثانیه دیگر دوباره تلاش کنید.' : 'خطا در ارتباط با سرور. لطفا دوباره تلاش کنید.', true);
                })
                .finally(function () { setLoading(false); });
        }

        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                input.value = '';
                wrapper.classList.remove('has-text');
                setLoading(false);
                if (abortCtrl) abortCtrl.abort();
                clearTimeout(timer);
                close();
                results.textContent = '';
                input.focus();
            });
        }

        input.addEventListener('input', function () {
            var term = input.value.trim();
            wrapper.classList.toggle('has-text', term.length > 0);
            clearTimeout(timer);

            if (term.length < MIN_CHARS) {
                setLoading(false);
                if (abortCtrl) abortCtrl.abort();
                close();
                results.textContent = '';
                return;
            }

            setLoading(true);
            timer = setTimeout(function () { search(term); }, DEBOUNCE_MS);
        });

        input.addEventListener('keydown', function (e) {

            var isOpen = results.classList.contains('is-open') && items.length;

            switch (e.key) {
                case 'ArrowDown':
                    if (!isOpen) return;
                    e.preventDefault();
                    activeIndex = (activeIndex + 1) % items.length;
                    setActive(activeIndex);
                    break;
                case 'ArrowUp':
                    if (!isOpen) return;
                    e.preventDefault();
                    activeIndex = (activeIndex - 1 + items.length) % items.length;
                    setActive(activeIndex);
                    break;
                case 'Enter':
                    // با گزینه فعال → همان محصول. بدون آن → ارسال بومی فرم
                    if (isOpen && activeIndex >= 0 && items[activeIndex]) {
                        e.preventDefault();
                        window.location.href = items[activeIndex].href;
                    }
                    break;
                case 'Escape':
                    e.preventDefault();
                    close();
                    break;
            }
        });

        // ارسال فرم با عبارت کوتاه‌تر از حداقل بی‌معناست
        wrapper.addEventListener('submit', function (e) {
            if (input.value.trim().length < MIN_CHARS) e.preventDefault();
        });

        document.addEventListener('click', function (e) {
            if (!wrapper.contains(e.target)) close();
        });

        input.addEventListener('focus', function () {
            if (items.length && input.value.trim().length >= MIN_CHARS) open();
        });
    }

    function boot() {
        document.querySelectorAll('.woo-live-search').forEach(init);
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();
})();
