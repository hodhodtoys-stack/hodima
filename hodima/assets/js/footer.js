/**
 * ==========================================================
 * footer.js — ماژول آکاردئون فوتر
 * نسخه: 2.0.0 (بازطراحی با Event Delegation و Debounce)
 * ==========================================================
 */
document.addEventListener('DOMContentLoaded', () => {

    const MOBILE_BREAKPOINT = 768;
    const footerContainer = document.querySelector('.footer-container');
    const formColumn = document.querySelector('.footer-form-col');

    if (!footerContainer) return;

    // تابع برای بررسی وضعیت موبایل
    const isMobile = () => window.innerWidth <= MOBILE_BREAKPOINT;

    /**
     * تابع Debounce برای بهینه‌سازی رویداد resize
     * از اجرای مکرر تابع در حین تغییر اندازه پنجره جلوگیری می‌کند.
     * @param {Function} func تابعی که باید اجرا شود
     * @param {number} delay مدت زمان تاخیر (میلی‌ثانیه)
     * @returns {Function}
     */
    const debounce = (func, delay = 250) => {
        let timeoutId;
        return (...args) => {
            clearTimeout(timeoutId);
            timeoutId = setTimeout(() => {
                func.apply(this, args);
            }, delay);
        };
    };

    /**
     * وضعیت آکاردئون را بر اساس اندازه صفحه به‌روز می‌کند.
     */
    const updateAccordionState = () => {
        const columns = footerContainer.querySelectorAll('.footer-col');
        if (isMobile()) {
            // در حالت موبایل، همه را ببند به جز فرم
            columns.forEach(col => col.classList.remove('active'));
            if (formColumn) {
                formColumn.classList.add('active');
            }
        } else {
            // در حالت دسکتاپ، همه کلاس‌های active را حذف کن
            columns.forEach(col => col.classList.remove('active'));
        }
    };

    /**
     * مدیریت کلیک روی هدرهای آکاردئون با Event Delegation
     * @param {Event} event
     */
    const handleAccordionToggle = (event) => {
        if (!isMobile()) return;

        // پیدا کردن نزدیک‌ترین والد h2 که روی آن کلیک شده
        const heading = event.target.closest('h2');
        if (!heading) return;

        // پیدا کردن ستون والد
        const currentColumn = heading.closest('.footer-col');
        if (!currentColumn || currentColumn.classList.contains('footer-form-col')) {
            return; // اگر ستون فرم بود یا ستونی پیدا نشد، خارج شو
        }

        const isActive = currentColumn.classList.contains('active');
        
        // بستن تمام ستون‌های باز
        footerContainer.querySelectorAll('.footer-col').forEach(col => {
            col.classList.remove('active');
        });
        
        // اگر ستون فعلی بسته بود، آن را باز کن
        if (!isActive) {
            currentColumn.classList.add('active');
        }

        // اطمینان از باز ماندن ستون فرم
        if (formColumn) {
            formColumn.classList.add('active');
        }
    };

    // --- راه‌اندازی اولیه ---

    // افزودن یک Event Listener به والد اصلی
    footerContainer.addEventListener('click', handleAccordionToggle);

    // به‌روزرسانی وضعیت در هنگام تغییر اندازه صفحه (با debounce)
    window.addEventListener('resize', debounce(updateAccordionState));

    // تنظیم اولیه وضعیت آکاردئون در زمان بارگذاری صفحه
    updateAccordionState();
});
