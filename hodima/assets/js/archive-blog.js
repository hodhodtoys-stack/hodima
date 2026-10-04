/**
 * بارگذاری خودکار مقاله‌های بعدی با اسکرول (صفحه وبلاگ و آرشیو دسته‌ها)
 * Path: assets/js/archive-blog.js
 *
 * قبلا اسکریپت inline داخل archive-blog.php بود. فقط وقتی «تنظیمات قالب ←
 * وبلاگ ← بارگذاری خودکار» روشن است بارگذاری می‌شود؛ لینک‌های صفحه‌بندی
 * واقعی (برای موتورهای جستجو) در HTML می‌مانند و همین‌ها دنبال می‌شوند.
 */
document.addEventListener("DOMContentLoaded", function() {
    let nextLink = document.querySelector('.hodima-pagination .next');
    if (!nextLink) return;

    const loader = document.getElementById('infinite-scroll-loader');
    const grid = document.getElementById('blog-grid');
    let isFetching = false;

    const observer = new IntersectionObserver((entries) => {
        if (entries[0].isIntersecting && !isFetching && nextLink) {
            loadNextPage();
        }
    }, { rootMargin: "200px" });

    observer.observe(loader);
    loader.style.display = 'block';

    async function loadNextPage() {
        isFetching = true;
        let url = nextLink.href;
        
        try {
            const response = await fetch(url);
            const text = await response.text();
            const parser = new DOMParser();
            const html = parser.parseFromString(text, 'text/html');
            
            const newItems = html.querySelectorAll('.blog-page-card');
            newItems.forEach(item => {
                grid.appendChild(item);
            });

            const newNextLink = html.querySelector('.hodima-pagination .next');
            if (newNextLink) {
                nextLink.href = newNextLink.href;
            } else {
                nextLink = null;
                loader.style.display = 'none';
                observer.disconnect();
            }
        } catch (error) {
            console.error('Error loading next page:', error);
        }
        
        isFetching = false;
    }
});
