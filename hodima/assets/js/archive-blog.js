/**
 * بارگذاری خودکار مقاله‌های بعدی با اسکرول (صفحه وبلاگ و آرشیو دسته‌ها)
 * Path: assets/js/archive-blog.js
 *
 * فقط وقتی «تنظیمات قالب ← وبلاگ ← بارگذاری خودکار» روشن است بارگذاری می‌شود؛
 * لینک‌های صفحه‌بندی واقعی (/page/2/ ... برای موتورهای جستجو) در HTML می‌مانند و
 * همین‌ها دنبال می‌شوند؛ آدرس صفحه جدیدی ساخته نمی‌شود.
 *
 * از 3.3.0: آدرس نوار مرورگر با اسکرول به /page/N/ همان بخش عوض می‌شود (کپی/اشتراک
 * همان جای صفحه را باز می‌کند)، خطای شبکه پیام و دکمه «تلاش دوباره» دارد (قبلا فقط
 * console.error و بارگذاری برای همیشه می‌ایستاد) و بارگذاری به صفحه‌خوان اعلام می‌شود.
 */
( () => {
	const nextLink = document.querySelector( '.hodima-pagination .next' );
	const loader = document.getElementById( 'infinite-scroll-loader' );
	const grid = document.getElementById( 'blog-grid' );
	if ( ! nextLink || ! loader || ! grid ) {
		return;
	}

	const loaderText = loader.querySelector( '.loader-text' );
	const loadingLabel = loaderText ? loaderText.textContent : '';
	let nextUrl = nextLink.href;
	let isFetching = false;
	let failed = false;

	// اعلام بارگذاری به صفحه‌خوان
	const live = document.createElement( 'p' );
	live.className = 'screen-reader-text';
	live.setAttribute( 'role', 'status' );
	live.setAttribute( 'aria-live', 'polite' );
	grid.after( live );

	const retry = document.createElement( 'button' );
	retry.type = 'button';
	retry.className = 'loader-retry';
	retry.textContent = 'تلاش دوباره';
	retry.hidden = true;
	loader.append( retry );

	// آدرس نوار مرورگر = آدرس همان بخشی که وسط صفحه است
	const urlObserver = new IntersectionObserver( ( entries ) => {
		for ( const entry of entries ) {
			const pageUrl = entry.isIntersecting ? entry.target.dataset.pageUrl : '';
			if ( pageUrl && pageUrl !== location.href ) {
				history.replaceState( history.state, '', pageUrl );
			}
		}
	}, { rootMargin: '-50% 0px -50% 0px' } );

	// همه کارت‌ها دیده‌بانی می‌شوند (نه فقط اول هر بخش) تا با اسکرول به بالا هم آدرس درست بماند
	const trackPage = ( cards, url ) => {
		for ( const card of cards ) {
			card.dataset.pageUrl = url;
			urlObserver.observe( card );
		}
	};
	trackPage( grid.querySelectorAll( '.blog-page-card' ), location.href );

	const setFailed = ( state ) => {
		failed = state;
		retry.hidden = ! state;
		if ( loaderText ) {
			loaderText.textContent = state ? 'بارگذاری مقاله‌ها انجام نشد.' : loadingLabel;
		}
	};

	const loadNextPage = async () => {
		isFetching = true;
		grid.setAttribute( 'aria-busy', 'true' );
		const pageUrl = nextUrl;
		try {
			const response = await fetch( pageUrl, { credentials: 'same-origin' } );
			if ( ! response.ok ) {
				throw new Error( `HTTP ${ response.status }` );
			}
			const html = new DOMParser().parseFromString( await response.text(), 'text/html' );
			const cards = [ ...html.querySelectorAll( '#blog-grid .blog-page-card' ) ];

			grid.append( ...cards );
			trackPage( cards, pageUrl );
			live.textContent = `${ cards.length.toLocaleString( 'fa' ) } مقاله دیگر بارگذاری شد.`;

			const newNextLink = html.querySelector( '.hodima-pagination .next' );
			if ( newNextLink ) {
				nextUrl = newNextLink.href;
			} else {
				nextUrl = '';
				loader.hidden = true;
				observer.disconnect();
			}
		} catch ( error ) {
			console.error( 'Error loading next page:', error );
			setFailed( true );
		}
		grid.removeAttribute( 'aria-busy' );
		isFetching = false;
	};

	const observer = new IntersectionObserver( ( entries ) => {
		if ( entries[ 0 ].isIntersecting && ! isFetching && ! failed && nextUrl ) {
			loadNextPage();
		}
	}, { rootMargin: '200px' } );

	retry.addEventListener( 'click', () => {
		setFailed( false );
		if ( ! isFetching && nextUrl ) {
			loadNextPage();
		}
	} );

	observer.observe( loader );
	loader.hidden = false;
} )();
