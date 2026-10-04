/**
 * بارگذاری خودکار مقاله‌های بعدی با اسکرول (صفحه وبلاگ و آرشیو دسته‌ها)
 * Path: assets/js/archive-blog.js
 *
 * فقط وقتی «تنظیمات قالب ← وبلاگ ← بارگذاری خودکار» روشن است بارگذاری می‌شود؛
 * لینک‌های صفحه‌بندی واقعی (برای موتورهای جستجو) در HTML می‌مانند و همین‌ها
 * دنبال می‌شوند. لودر با ویژگی hidden (قبلا style inline).
 */
( () => {
	let nextLink = document.querySelector( '.hodima-pagination .next' );
	const loader = document.getElementById( 'infinite-scroll-loader' );
	const grid = document.getElementById( 'blog-grid' );
	if ( ! nextLink || ! loader || ! grid ) {
		return;
	}

	let isFetching = false;

	const loadNextPage = async () => {
		isFetching = true;
		try {
			const response = await fetch( nextLink.href );
			const html = new DOMParser().parseFromString( await response.text(), 'text/html' );

			grid.append( ...html.querySelectorAll( '.blog-page-card' ) );

			const newNextLink = html.querySelector( '.hodima-pagination .next' );
			if ( newNextLink ) {
				nextLink.href = newNextLink.href;
			} else {
				nextLink = null;
				loader.hidden = true;
				observer.disconnect();
			}
		} catch ( error ) {
			console.error( 'Error loading next page:', error );
		}
		isFetching = false;
	};

	const observer = new IntersectionObserver( ( entries ) => {
		if ( entries[ 0 ].isIntersecting && ! isFetching && nextLink ) {
			loadNextPage();
		}
	}, { rootMargin: '200px' } );

	observer.observe( loader );
	loader.hidden = false;
} )();
