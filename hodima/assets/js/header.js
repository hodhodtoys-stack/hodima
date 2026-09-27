'use strict';

/**
 * ──────────────────────────────────────────
 * ماژول ۱: هدر چسبان (Sticky Header)
 * ──────────────────────────────────────────
 */
const StickyHeaderModule = (() => {
    let header = null;
    let wrapper = null;
    let ticking = false;

    const onScroll = () => {
        if (!ticking) {
            window.requestAnimationFrame(() => {
                if (window.scrollY > 10) {
                    header.classList.add('is-fixed');
                    wrapper.style.paddingTop = `${header.offsetHeight}px`;
                } else {
                    header.classList.remove('is-fixed');
                    wrapper.style.paddingTop = '0';
                }
                ticking = false;
            });
            ticking = true;
        }
    };

    const init = () => {
        header  = document.getElementById('mainHeader');
        wrapper = document.getElementById('headerWrapper');

        if (header && wrapper) {
            window.addEventListener('scroll', onScroll, { passive: true });
        }
    };

    return { init };
})();

/**
 * ──────────────────────────────────────────
 * ماژول ۲: منوی موبایل (نسخه سئو شده و داینامیک)
 * ──────────────────────────────────────────
 */
const MobileMenuModule = (() => {
    let mainNav = null;
    let toggleBtn = null;
    let svgPath = null;
    
    // 🔴 نکته: اگر نقطه شکست منوی شما در CSS عدد دیگری است (مثلا 768px)، این عدد را تغییر دهید
    const desktopBreakpoint = window.matchMedia('(min-width: 992px)'); 
    
    const HAMBURGER_D = 'M4 6h16M4 12h16m-7 6h7';
    const CLOSE_D = 'M6 18L18 6M6 6l12 12';

    const toggleMenu = () => {
        const isOpen = mainNav.classList.toggle('is-open');
        svgPath.setAttribute('d', isOpen ? CLOSE_D : HAMBURGER_D);
        toggleBtn.setAttribute('aria-expanded', String(isOpen));
        mainNav.setAttribute('aria-hidden', String(!isOpen));
        toggleBtn.setAttribute('aria-label', isOpen ? 'بستن منوی موبایل' : 'باز کردن منوی موبایل');

        document.body.classList.toggle('menu-is-open', isOpen);
    };

    const closeMenu = () => {
        if (!mainNav.classList.contains('is-open')) return;
        mainNav.classList.remove('is-open');
        svgPath.setAttribute('d', HAMBURGER_D);
        toggleBtn.setAttribute('aria-expanded', 'false');
        mainNav.setAttribute('aria-hidden', 'true');
        toggleBtn.setAttribute('aria-label', 'باز کردن منوی موبایل');

        document.body.classList.remove('menu-is-open');
    };

    const handleScreenChange = (e) => {
        if (e.matches) {
            // در دسکتاپ: حذف aria-hidden برای خوانایی سئو
            mainNav.removeAttribute('aria-hidden');
            if (mainNav.classList.contains('is-open')) {
                closeMenu();
            }
        } else {
            // در موبایل: کنترل داینامیک وضعیت بر اساس باز یا بسته بودن
            mainNav.setAttribute('aria-hidden', String(!mainNav.classList.contains('is-open')));
        }
    };

    const onDocumentClick = (e) => {
        if (
            mainNav.classList.contains('is-open') &&
            !mainNav.contains(e.target) &&
            !toggleBtn.contains(e.target)
        ) {
            closeMenu();
        }
    };

    const onKeyDown = (e) => {
        if (e.key === 'Escape' && mainNav.classList.contains('is-open')) {
            closeMenu();
            toggleBtn.focus();
        }
    };

    const handleSubmenuToggle = (e) => {
        if (window.getComputedStyle(toggleBtn).display === 'none') return;
        
        const link = e.target.closest('.menu-item-has-children > a');
        if (link) {
            e.preventDefault();
            link.parentElement.classList.toggle('submenu-active');
        }
    };

    const init = () => {
        mainNav = document.getElementById('mainNavWrapper');
        toggleBtn = document.getElementById('mobileMenuTrigger');

        if (!mainNav || !toggleBtn) return;

        svgPath = toggleBtn.querySelector('svg path');
        if (!svgPath) return;

        toggleBtn.addEventListener('click', toggleMenu);
        document.addEventListener('click', onDocumentClick);
        document.addEventListener('keydown', onKeyDown);
        mainNav.addEventListener('click', handleSubmenuToggle);

        // اعمال منطق سئو
        handleScreenChange(desktopBreakpoint);
        desktopBreakpoint.addEventListener('change', handleScreenChange);
    };

    return { init };
})();

/**
 * ──────────────────────────────────────────
 * ماژول ۳: پاپ‌آپ پشتیبانی
 * ──────────────────────────────────────────
 */
const SupportPopupModule = (() => {
    let overlay = null, triggerBtn = null, closeBtn = null;

    const open = () => {
        overlay.classList.add('is-active');
        overlay.setAttribute('aria-hidden', 'false');
        overlay.querySelector('.popup__link')?.focus();
    };

    const close = () => {
        overlay.classList.remove('is-active');
        overlay.setAttribute('aria-hidden', 'true');
        triggerBtn?.focus();
    };

    const onOverlayClick = (e) => { if (e.target === overlay) close(); };
    const onKeyDown = (e) => { if (e.key === 'Escape' && overlay.classList.contains('is-active')) close(); };

    const trapFocus = (e) => {
        if (!overlay.classList.contains('is-active')) return;
        const focusables = overlay.querySelectorAll('button, a[href]');
        if (focusables.length === 0) return;
        const first = focusables[0];
        const last = focusables[focusables.length - 1];
        if (e.key === 'Tab') {
            if (e.shiftKey) {
                if (document.activeElement === first) { e.preventDefault(); last.focus(); }
            } else {
                if (document.activeElement === last) { e.preventDefault(); first.focus(); }
            }
        }
    };

    const init = () => {
        overlay = document.getElementById('supportOverlay');
        triggerBtn = document.getElementById('supportTrigger');
        closeBtn = document.getElementById('supportClose');
        if (!overlay || !triggerBtn || !closeBtn) return;
        triggerBtn.addEventListener('click', open);
        closeBtn.addEventListener('click', close);
        overlay.addEventListener('click', onOverlayClick);
        document.addEventListener('keydown', onKeyDown);
        document.addEventListener('keydown', trapFocus);
    };

    return { init };
})();

document.addEventListener('DOMContentLoaded', () => {
    StickyHeaderModule.init();
    MobileMenuModule.init();
    SupportPopupModule.init();
});