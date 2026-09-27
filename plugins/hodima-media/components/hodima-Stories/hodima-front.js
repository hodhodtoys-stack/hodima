(function() {
    'use strict';

    function throttle(func, limit) {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        }
    }

    function revealContainer(container) {
        container.classList.remove('hs-loading');
        container.classList.add('is-ready');
    }

    function handleScrollIndicator(wrapper, container) {
        const checkScroll = () => {
            const scrollLeft = Math.abs(wrapper.scrollLeft);
            const maxScroll = wrapper.scrollWidth - wrapper.clientWidth;
            if (scrollLeft < (maxScroll - 5)) {
                container.classList.remove('is-scrolled');
            } else {
                container.classList.add('is-scrolled');
            }
        };

        checkScroll();
        const throttledScroll = throttle(checkScroll, 100);

        wrapper.addEventListener('scroll', throttledScroll, { passive: true });
        window.addEventListener('resize', throttledScroll, { passive: true });
    }

    // New: implements the "seen stories move to the end" idea. Stories are
    // now rendered server-side in a stable order (admin order, newest
    // first — see the shuffle() removal in hodima-Stories.php). Here we
    // read the same 'hs_view_<id>' localStorage keys the player already
    // writes on every view, and use their mere presence (regardless of the
    // stored timestamp) as "this visitor has watched this story before" to
    // push already-seen stories to the end and dim their ring.
    function getSeenStoryIds() {
        const seen = new Set();
        try {
            for (let i = 0; i < localStorage.length; i++) {
                const key = localStorage.key(i);
                if (key && key.indexOf('hs_view_') === 0) {
                    seen.add(key.slice('hs_view_'.length));
                }
            }
        } catch (e) {
            // localStorage unavailable (privacy mode, quota, etc.) — skip reordering.
        }
        return seen;
    }

    function reorderBySeen(wrapper) {
        const seen = getSeenStoryIds();
        if (!seen.size) return;

        const items = Array.from(wrapper.querySelectorAll('.story-item'));
        const unseen = [];
        const watched = [];

        items.forEach(item => {
            const link = item.querySelector('.story-link');
            const id = link ? link.dataset.storyId : '';
            if (id && seen.has(id)) {
                item.classList.add('is-seen');
                watched.push(item);
            } else {
                unseen.push(item);
            }
        });

        if (!watched.length) return;

        const frag = document.createDocumentFragment();
        unseen.concat(watched).forEach(item => frag.appendChild(item));
        wrapper.appendChild(frag);
    }

    function setupContainer(container) {
        const wrapper = container.querySelector('.story-wrapper');
        if (wrapper) {
            /*
             * جابه‌جایی دیده‌شده‌ها فقط وقتی گزینه‌اش در پنل روشن باشد.
             * نسخه قبلی همیشه این کار را می‌کرد، پس ترتیبی که در پنل چیده
             * می‌شد برای بازدیدکننده تکراری رعایت نمی‌شد.
             */
            if (container.dataset.seenToEnd === '1') {
                reorderBySeen(wrapper);
            } else {
                markSeen(wrapper);
            }
            handleScrollIndicator(wrapper, container);
        }
        revealContainer(container);
    }

    // فقط علامت «دیده‌شده» (حلقه کم‌رنگ)، بدون تغییر ترتیب
    function markSeen(wrapper) {
        const seen = getSeenStoryIds();
        if (!seen.size) return;
        wrapper.querySelectorAll('.story-link').forEach(link => {
            if (seen.has(link.dataset.storyId || '')) {
                link.closest('.story-item').classList.add('is-seen');
            }
        });
    }

    /*
     * راه‌اندازی فوری، بدون انتظار برای ورود به دید.
     * نسخه قبلی نوار را تا اجرای JS و ورود به دید پنهان نگه می‌داشت، و فقط
     * به DOMContentLoaded گوش می‌داد؛ با حالت Delay JS لایت‌اسپید این
     * رویداد قبل از اجرای اسکریپت رخ داده بود و نوار هرگز نمایش داده نمی‌شد.
     * جابه‌جایی دیده‌شده‌ها هم اگر بعد از ورود به دید انجام می‌شد، جلوی
     * چشم کاربر ترتیب را به هم می‌ریخت.
     */
    function processStories() {
        document.querySelectorAll('.section-stories').forEach(setupContainer);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', processStories);
    } else {
        processStories();
    }
})();
