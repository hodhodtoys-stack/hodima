/**
 * Expandable Boxes — v3.1.0 (Modern Vanilla JS - Dynamic Transition)
 */
(() => {
    'use strict';

    const config = window.ArianExpandableBoxesData || {};
    const sel    = config.selectors || {};

    const SELECTORS = {
        container: sel.container || '[data-expandable-container]',
        content:   sel.content   || '[data-expandable-content]',
        toggle:    sel.toggle    || '[data-expandable-toggle]',
        inner:     sel.inner     || '.arian-expandable-inner'
    };

    const DEFAULT_MORE  = config.defaultMoreText || 'نمایش بیشتر';
    const DEFAULT_LESS  = config.defaultLessText || 'نمایش کمتر';
    const DEFAULT_OFFSET = Number(config.defaultScrollOffset || 120);
    const HASH_EXPAND   = config.hashExpand !== false;

    const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

    const debounce = (fn, ms) => {
        let t;
        return (...args) => {
            clearTimeout(t);
            t = setTimeout(() => fn(...args), ms);
        };
    };

    const num = (v, fallback) => {
        const n = parseInt(v, 10);
        return isNaN(n) ? fallback : n;
    };

    const parts = (container) => {
        if (!container) return null;
        const wrapper = container.querySelector(SELECTORS.content);
        const button  = container.querySelector(SELECTORS.toggle);
        if (!wrapper || !button) return null;
        return { container, wrapper, button, inner: wrapper.querySelector(SELECTORS.inner) };
    };

    const collapsedHeight = (w) => num(w.dataset.height, 600);
    const fullHeight = (p) => p.inner ? p.inner.scrollHeight : p.wrapper.scrollHeight;
    const isExpanded = (p) => p.wrapper.classList.contains('arian-expanded');

    const setButton = (p, expanded) => {
        const text = expanded ? (p.wrapper.dataset.less || DEFAULT_LESS) : (p.wrapper.dataset.more || DEFAULT_MORE);
        const span = p.button.querySelector('.arian-btn-text');
        if (span) span.textContent = text; else p.button.textContent = text;
        p.button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    };

    const finish = (p) => {
        p.wrapper.classList.remove('arian-animating');
        if (isExpanded(p)) p.wrapper.style.maxHeight = 'none';
    };

    // تابع جدید برای استخراج زمان ترنزیشن از CSS
    const getTransitionDuration = (element) => {
        const computedStyle = window.getComputedStyle(element);
        const duration = computedStyle.transitionDuration || '0s';
        // تبدیل فرمت‌هایی مثل '0.6s' یا '600ms' به میلی‌ثانیه
        const ms = duration.includes('ms') ? parseFloat(duration) : parseFloat(duration) * 1000;
        return isNaN(ms) ? 600 : ms; // مقدار پیش‌فرض در صورت خطا
    };

    const setState = (p, expanded, opts = {}) => {
        const from = p.wrapper.getBoundingClientRect().height;
        const to   = expanded ? fullHeight(p) : collapsedHeight(p.wrapper);

        p.wrapper.classList.toggle('arian-expanded', expanded);
        setButton(p, expanded);

        if (reducedMotion) {
            p.wrapper.style.maxHeight = expanded ? 'none' : `${to}px`;
        } else {
            p.wrapper.classList.add('arian-animating');
            p.wrapper.style.maxHeight = `${from}px`;
            
            // خواندن پویا زمان ترنزیشن از CSS
            const dynamicTransitionMs = getTransitionDuration(p.wrapper);
            
            requestAnimationFrame(() => {
                requestAnimationFrame(() => { p.wrapper.style.maxHeight = `${to}px`; });
            });
            // استفاده از زمان پویای محاسبه شده
            setTimeout(() => finish(p), dynamicTransitionMs + 60); 
        }

        if (!expanded && !opts.skipScroll) {
            const top = p.wrapper.getBoundingClientRect().top + window.pageYOffset - num(p.wrapper.dataset.scrollOffset, DEFAULT_OFFSET);
            if (top < window.pageYOffset) {
                window.scrollTo({ top: Math.max(0, top), behavior: reducedMotion ? 'auto' : 'smooth' });
            }
            try { p.button.focus({ preventScroll: true }); } catch (e) { p.button.focus(); }
        }
    };

    const refresh = (container) => {
        const p = parts(container);
        if (!p) return;

        const collapsed = collapsedHeight(p.wrapper);
        const full      = fullHeight(p);
        const smart     = (p.wrapper.dataset.smart || 'yes') === 'yes';

        if (full <= collapsed + (smart ? 40 : 0)) {
            p.button.hidden = true;
            p.wrapper.classList.add('arian-expanded');
            p.wrapper.style.maxHeight = 'none';
            setButton(p, false);
            return;
        }

        p.button.hidden = false;

        if (isExpanded(p)) {
            p.wrapper.style.maxHeight = 'none';
            setButton(p, true);
        } else {
            p.wrapper.style.maxHeight = `${collapsed}px`;
            setButton(p, false);
        }
    };

    const refreshAll = () => document.querySelectorAll(SELECTORS.container).forEach(refresh);

    const revealTarget = (el, opts) => {
        if (!el) return false;

        const wrapper = el.closest(SELECTORS.content);
        if (!wrapper) return false;

        const p = parts(wrapper.closest(SELECTORS.container));
        if (!p || isExpanded(p) || p.button.hidden) return false;

        setState(p, true, { skipScroll: true });

        if (opts?.scroll) {
            setTimeout(() => {
                el.scrollIntoView({ behavior: reducedMotion ? 'auto' : 'smooth', block: 'start' });
            }, reducedMotion ? 0 : 80);
        }
        return true;
    };

    const revealHash = () => {
        if (!HASH_EXPAND || !location.hash || location.hash.length < 2) return;
        let id;
        try { id = decodeURIComponent(location.hash.slice(1)); } catch (e) { id = location.hash.slice(1); }
        revealTarget(document.getElementById(id), { scroll: true });
    };

    const observe = () => {
        if ('ResizeObserver' in window) {
            const ro = new ResizeObserver(debounce((entries) => {
                const done = new Set();
                entries.forEach(entry => {
                    const c = entry.target.closest(SELECTORS.container);
                    if (c && !done.has(c)) { done.add(c); refresh(c); }
                });
            }, 120));

            document.querySelectorAll(SELECTORS.container).forEach(c => {
                const p = parts(c);
                if (p?.inner) ro.observe(p.inner);
            });
        }

        document.querySelectorAll(`${SELECTORS.content} img, ${SELECTORS.content} iframe, ${SELECTORS.content} video`).forEach(m => {
            if (m.tagName === 'IMG' && m.complete) return;
            const c = m.closest(SELECTORS.container);
            const r = debounce(() => refresh(c), 100);
            m.addEventListener('load', r, { passive: true });
            m.addEventListener('error', r, { passive: true });
        });

        if ('MutationObserver' in window) {
            document.querySelectorAll(SELECTORS.container).forEach(c => {
                new MutationObserver(debounce(() => refresh(c), 250))
                    .observe(c, { childList: true, subtree: true });
            });
        }
    };

    const init = () => {
        document.addEventListener('click', e => {
            const btn = e.target.closest(SELECTORS.toggle);
            if (btn) {
                const p = parts(btn.closest(SELECTORS.container));
                if (p) setState(p, !isExpanded(p));
                return;
            }

            const a = e.target.closest('a[href*="#"]');
            if (a?.hash && a.pathname === location.pathname && a.hash === location.hash) {
                revealHash();
            }
        });

        document.addEventListener('focusin', e => revealTarget(e.target, { scroll: false }));

        window.addEventListener('hashchange', revealHash);
        window.addEventListener('resize', debounce(refreshAll, 180));
        window.addEventListener('load', refreshAll);

        if (document.fonts?.ready) {
            document.fonts.ready.then(refreshAll).catch(() => {});
        }

        refreshAll();
        observe();
        revealHash();

        window.ArianExpandableBoxes = {
            refresh: refreshAll,
            expand: id => {
                const w = document.getElementById(id);
                const p = w && parts(w.closest(SELECTORS.container));
                if (p) setState(p, true, { skipScroll: true });
            },
            collapse: id => {
                const w = document.getElementById(id);
                const p = w && parts(w.closest(SELECTORS.container));
                if (p) setState(p, false);
            }
        };
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();