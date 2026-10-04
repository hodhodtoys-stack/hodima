/**
 * صفحه اصلی: کشیدن اسلایدرها با ماوس (دسکتاپ).
 * «Lazy Load Enhancer» برای img.arian-lazy حذف شد: هیچ تصویری این کلاس را نداشت (کد مرده).
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


    // اجرا
    initDragScroll();
});
