/**
 * SP Gallery — گالری سفارشی محصول
 * Vanilla JS — بدون وابستگی
 * ساپورت: Touch Swipe + Keyboard + Lightbox + Lazy Load
 *
 * @package hodima
 * @since   1.0.0
 */
;(function () {
    'use strict';

    // ── بررسی وجود گالری ──
    var gallery = document.querySelector('.sp-gallery');
    if (!gallery) return;

    // ── المان‌ها ──
    var slides      = gallery.querySelectorAll('.sp-gallery__slide');
    var thumbs      = gallery.querySelectorAll('.sp-gallery__thumb');
    var zoomBtn     = gallery.querySelector('.sp-gallery__zoom');
    var thumbsTrack = gallery.querySelector('.sp-gallery__thumbs-track');

    // ── لایتباکس ──
    var lightbox  = document.querySelector('.sp-lightbox');
    var lbImg     = lightbox ? lightbox.querySelector('.sp-lightbox__img')        : null;
    var lbClose   = lightbox ? lightbox.querySelector('.sp-lightbox__close')      : null;
    var lbPrev    = lightbox ? lightbox.querySelector('.sp-lightbox__nav--prev')  : null;
    var lbNext    = lightbox ? lightbox.querySelector('.sp-lightbox__nav--next')  : null;
    var lbOverlay = lightbox ? lightbox.querySelector('.sp-lightbox__overlay')    : null;
    var lbCounter = lightbox ? lightbox.querySelector('.sp-lightbox__counter')    : null;

    var total = slides.length;
    var current = 0;
    var isLightboxOpen = false;
    var hasSwiped = false;
    var lastFocus = null;
    var hideTimer = null;

    // RTL: «بعدی» سمت چپ است (ترتیب تامبنیل‌ها از راست به چپ)
    var isRTL = (document.documentElement.getAttribute('dir') || getComputedStyle(document.documentElement).direction) === 'rtl';

    if (total === 0) return;

    // ══════════════════════════════════════════
    // تابع اصلی: تغییر اسلاید
    // ══════════════════════════════════════════
    function goTo(index) {
        if (index < 0) index = total - 1;
        if (index >= total) index = 0;

        // Lazy load تصویر
        var targetSlide = slides[index];
        // src اولیه اسلایدهای ۲ به بعد تامبنیل است؛ تصویر کامل جایگزین می‌شود
        if (targetSlide.dataset.src && targetSlide.getAttribute('src') !== targetSlide.dataset.src) {
            targetSlide.src = targetSlide.dataset.src;
        }

        // تعویض کلاس فعال — اسلایدها
        slides[current].classList.remove('is-active');
        targetSlide.classList.add('is-active');

        // تعویض کلاس فعال — تامبنیل‌ها
        if (thumbs.length > 0) {
            thumbs[current].classList.remove('is-active');
            thumbs[current].setAttribute('aria-current', 'false');
            thumbs[index].classList.add('is-active');
            thumbs[index].setAttribute('aria-current', 'true');
            scrollThumbIntoView(index);
        }

        current = index;

        // Preload اسلایدهای مجاور
        preloadAdjacent(index);
    }

    // ══════════════════════════════════════════
    // Preload تصاویر مجاور
    // ══════════════════════════════════════════
    function preloadAdjacent(index) {
        var next = (index + 1) % total;
        var prev = (index - 1 + total) % total;

        [next, prev].forEach(function (i) {
            var s = slides[i];
            if (s && s.dataset.src && s.getAttribute('src') !== s.dataset.src) {
                s.src = s.dataset.src;
            }
        });
    }

    // ══════════════════════════════════════════
    // اسکرول تامبنیل فعال به محدوده دید
    // ══════════════════════════════════════════
    function scrollThumbIntoView(index) {
        if (!thumbsTrack || !thumbs[index]) return;

        var thumb = thumbs[index];
        var trackRect = thumbsTrack.getBoundingClientRect();
        var thumbRect = thumb.getBoundingClientRect();

        if (thumbRect.right > trackRect.right) {
            thumbsTrack.scrollLeft += (thumbRect.right - trackRect.right + 20);
        } else if (thumbRect.left < trackRect.left) {
            thumbsTrack.scrollLeft -= (trackRect.left - thumbRect.left + 20);
        }
    }

    // ══════════════════════════════════════════
    // کلیک تامبنیل
    // ══════════════════════════════════════════
    thumbs.forEach(function (thumb) {
        thumb.addEventListener('click', function () {
            var index = parseInt(this.dataset.index, 10);
            goTo(index);
        });
    });

    // ══════════════════════════════════════════
    // Touch Swipe (موبایل)
    // ══════════════════════════════════════════
    var touchStartX = 0;
    var touchStartY = 0;
    var isSwiping = false;

    var mainImage = gallery.querySelector('.sp-gallery__main-image');
    if (mainImage) {
        mainImage.addEventListener('touchstart', function (e) {
            touchStartX = e.touches[0].clientX;
            touchStartY = e.touches[0].clientY;
            isSwiping = true;
            hasSwiped = false;
        }, { passive: true });

        mainImage.addEventListener('touchmove', function (e) {
            if (!isSwiping) return;
            var diffX = Math.abs(e.touches[0].clientX - touchStartX);
            var diffY = Math.abs(e.touches[0].clientY - touchStartY);
            if (diffX > diffY && diffX > 10) {
                e.preventDefault();
                hasSwiped = true;
            }
        }, { passive: false });

        mainImage.addEventListener('touchend', function (e) {
            if (!isSwiping) return;
            isSwiping = false;

            var touchEndX = e.changedTouches[0].clientX;
            var diff = touchStartX - touchEndX;
            var threshold = 50;

            // RTL: swipe چپ = بعدی، swipe راست = قبلی
            if (diff > threshold) {
                hasSwiped = true;
                goTo(current + 1);
            } else if (diff < -threshold) {
                hasSwiped = true;
                goTo(current - 1);
            }
        }, { passive: true });
    }

    // ══════════════════════════════════════════
    // لایتباکس
    // ══════════════════════════════════════════
    function openLightbox(index) {
        if (!lightbox || !lbImg) return;

        isLightboxOpen = true;
        var slide = slides[index];
        var fullSrc = slide.dataset.full || slide.dataset.src || slide.src;

        lbImg.src = fullSrc;
        lbImg.alt = slide.alt || '';

        if (lbCounter) {
            lbCounter.textContent = (index + 1) + ' / ' + total;
        }

        clearTimeout(hideTimer);
        lastFocus = document.activeElement;
        lightbox.hidden = false;
        // یک فریم بعد تا انیمیشن باز شدن اجرا شود
        requestAnimationFrame(function () { lightbox.classList.add('is-open'); });
        lightbox.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        if (lbClose) lbClose.focus();
    }

    function closeLightbox() {
        if (!lightbox) return;

        isLightboxOpen = false;
        lightbox.classList.remove('is-open');
        lightbox.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
        // hidden بعد از پایان انیمیشن بسته شدن
        hideTimer = setTimeout(function () { lightbox.hidden = true; }, 350);
        if (lastFocus && typeof lastFocus.focus === 'function') lastFocus.focus();
    }

    function lightboxGoTo(index) {
        if (index < 0) index = total - 1;
        if (index >= total) index = 0;

        goTo(index);

        var slide = slides[index];
        var fullSrc = slide.dataset.full || slide.dataset.src || slide.src;
        if (lbImg) {
            lbImg.src = fullSrc;
            lbImg.alt = slide.alt || '';
        }

        if (lbCounter) {
            lbCounter.textContent = (index + 1) + ' / ' + total;
        }
    }

    // دکمه زوم
    if (zoomBtn) {
        zoomBtn.addEventListener('click', function () {
            openLightbox(current);
        });
    }

    // کلیک روی تصویر اصلی → لایتباکس (فقط اگر swipe نبوده)
    if (mainImage) {
        mainImage.addEventListener('click', function (e) {
            if (!hasSwiped) {
                openLightbox(current);
            }
            hasSwiped = false;
        });
    }

    // بستن لایتباکس
    if (lbClose) {
        lbClose.addEventListener('click', closeLightbox);
    }
    if (lbOverlay) {
        lbOverlay.addEventListener('click', closeLightbox);
    }

    // ناوبری لایتباکس (RTL)
    if (lbPrev) {
        lbPrev.addEventListener('click', function () {
            lightboxGoTo(current + 1);
        });
    }
    if (lbNext) {
        lbNext.addEventListener('click', function () {
            lightboxGoTo(current - 1);
        });
    }

    // Touch swipe لایتباکس
    if (lightbox) {
        var lbTouchStartX = 0;

        lightbox.addEventListener('touchstart', function (e) {
            lbTouchStartX = e.touches[0].clientX;
        }, { passive: true });

        lightbox.addEventListener('touchend', function (e) {
            var diff = lbTouchStartX - e.changedTouches[0].clientX;
            if (diff > 50) {
                lightboxGoTo(current + 1);
            } else if (diff < -50) {
                lightboxGoTo(current - 1);
            }
        }, { passive: true });
    }

    // ══════════════════════════════════════════
    // کیبورد
    // ══════════════════════════════════════════
    /*
     * کیبورد — فقط وقتی لایت‌باکس باز است یا فوکوس داخل گالری است.
     * نسخه قبلی به *کل صفحه* گوش می‌داد: فلش چپ و راست هنگام تایپ نظر،
     * تغییر تعداد یا وارد کردن شماره تلفن، عکس محصول را عوض می‌کرد.
     */
    function isEditable(el) {
        if (!el) return false;
        var tag = el.tagName;
        return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || el.isContentEditable;
    }

    document.addEventListener('keydown', function (e) {

        if (isEditable(e.target)) return;

        var inGallery = gallery.contains(document.activeElement);
        if (!isLightboxOpen && !inGallery) return;

        if (e.key === 'Escape' && isLightboxOpen) {
            closeLightbox();
            return;
        }

        if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') return;

        var step = (e.key === 'ArrowLeft') === isRTL ? 1 : -1;
        e.preventDefault();

        if (isLightboxOpen) {
            lightboxGoTo(current + step);
        } else {
            goTo(current + step);
            if (thumbs[current]) thumbs[current].focus();
        }
    });

    // تله فوکوس داخل لایت‌باکس (Tab از دیالوگ بیرون نرود)
    if (lightbox) {
        lightbox.addEventListener('keydown', function (e) {
            if (e.key !== 'Tab' || !isLightboxOpen) return;
            var f = lightbox.querySelectorAll('button');
            if (!f.length) return;
            var first = f[0], last = f[f.length - 1];
            if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
            else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
        });
    }

    // ══════════════════════════════════════════
    // Preload اولیه
    // ══════════════════════════════════════════
    preloadAdjacent(0);

})();
