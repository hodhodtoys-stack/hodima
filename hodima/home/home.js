/**
 * Arian Home – Ultra Optimized JS
 */

document.addEventListener('DOMContentLoaded', () => {

    // =============================
    // 1. Drag Scroll (Desktop only)
    // =============================
    const initDragScroll = () => {

        // موبایل + کاربرانی که Motion ممنوع شده → Drag غیر فعال
        if (window.innerWidth < 900 || 
            window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }

        const scrollers = document.querySelectorAll('.arian-scroller');

        scrollers.forEach(el => {
            let isDown = false;
            let startX = 0;
            let scrollLeft = 0;

            const onMouseDown = (e) => {
                isDown = true;
                el.classList.add('active');
                startX = e.pageX - el.getBoundingClientRect().left;
                scrollLeft = el.scrollLeft;
            };

            const onMouseMove = (e) => {
                if (!isDown) return;
                e.preventDefault();
                const x = e.pageX - el.getBoundingClientRect().left;
                const walk = (x - startX) * 2;
                el.scrollLeft = scrollLeft - walk;
            };

            const endDrag = () => {
                isDown = false;
                el.classList.remove('active');
            };

            el.addEventListener('mousedown', onMouseDown, { passive: true });
            el.addEventListener('mousemove', onMouseMove);
            el.addEventListener('mouseleave', endDrag);
            el.addEventListener('mouseup', endDrag);

        });
    };


    // =============================
    // 2. Lazy Load Enhancer (Optional)
    // =============================
    const initLazyLoad = () => {
        // اگر LiteSpeed فعال است، LazyLoad اصلی را خودش انجام می‌دهد
        if (window.lsjs) return;

        // حداقل بهبود برای تصاویر arian-lazy
        const images = document.querySelectorAll('img.arian-lazy');

        const obs = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (!entry.isIntersecting) return;

                const img = entry.target;
                img.src = img.dataset.src;
                img.removeAttribute('data-src');
                img.classList.remove('arian-lazy');
                observer.unobserve(img);
            });
        }, { rootMargin: '200px' });

        images.forEach(img => obs.observe(img));
    };


    // اجرا
    initDragScroll();
    initLazyLoad();
});
