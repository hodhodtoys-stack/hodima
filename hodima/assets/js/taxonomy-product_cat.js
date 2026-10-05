/**
 * Product category — sorting
 * Version: 3.0.0 (نوسازی قالب، مرحله ۴: async/await، بدون var)
 *
 * تغییرات (2.0.0):
 *   - مرتب‌سازی به صفحه ۱ برمی‌گردد. شماره صفحه در *مسیر* است
 *     (/cat/page/3/) نه در ?paged=؛ نسخه قبلی فقط ?paged را حذف می‌کرد و
 *     مرتب‌سازی روی صفحه ۳، صفحه ۳ ترتیب جدید را می‌آورد (یا ۴۰۴).
 *   - دکمه برگشت مرورگر: نسخه قبلی آدرس را عوض می‌کرد ولی محصولات را نه.
 *   - صفحه‌بندی همراه محصولات جایگزین می‌شود (حتی اگر قبلا نبود یا حالا نیست).
 *   - درخواست‌های پشت سر هم: قبلی لغو می‌شود.
 *   - aria-pressed / aria-expanded و Escape برای منوی قیمت.
 *   - راه‌اندازی مستقل از DOMContentLoaded (Delay JS لایت‌اسپید).
 * 3.0.0: نشانه «در حال بارگذاری» با لغو درخواست قبلی برداشته نمی‌شود (فقط
 * وقتی آخرین درخواست تمام شد).
 */
( () => {
	const init = () => {
		const scope = document.querySelector( '.section-products' );
		const group = document.querySelector( '.hodima-custom-sort-wrapper' );
		if ( ! scope || ! group ) {
			return;
		}

		const priceWrap = group.querySelector( '.price-sort-wrapper' );
		const priceBtn = group.querySelector( '[data-sort-type="price-group"]' );
		let ctrl = null;

		const stripPage = ( url ) => {
			url.pathname = url.pathname.replace( /\/page\/\d+\/?$/, '/' );
			url.searchParams.delete( 'paged' );
			url.searchParams.delete( 'product-page' );
			return url;
		};

		const markActive = ( orderby ) => {
			group.querySelectorAll( '.hodima-sort-btn, .hodima-sort-sub-btn' ).forEach( ( b ) => {
				b.classList.remove( 'active' );
				if ( b.hasAttribute( 'aria-pressed' ) ) {
					b.setAttribute( 'aria-pressed', 'false' );
				}
			} );

			let target = null;
			if ( 'date' === orderby || 'popularity' === orderby ) {
				target = group.querySelector( `[data-sort-type="${ orderby }"]` );
			} else if ( 'price' === orderby || 'price-desc' === orderby ) {
				target = group.querySelector( `[data-orderby="${ orderby }"]` );
				priceBtn?.classList.add( 'active' );
			}
			if ( target ) {
				target.classList.add( 'active' );
				target.setAttribute( 'aria-pressed', 'true' );
			}
		};

		const setMenu = ( open ) => {
			if ( ! priceWrap || ! priceBtn ) {
				return;
			}
			priceWrap.classList.toggle( 'open', open );
			priceBtn.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		};

		/** محصولات و صفحه‌بندی را از HTML صفحه دیگر جایگزین کن. */
		const swap = ( doc ) => {
			const fresh = doc.querySelector( '.section-products ul.products' );
			const now = scope.querySelector( 'ul.products' );
			if ( ! fresh || ! now ) {
				return false;
			}

			now.replaceWith( fresh );

			const oldNav = scope.querySelector( '.woocommerce-pagination' );
			const newNav = doc.querySelector( '.section-products .woocommerce-pagination' );
			if ( oldNav && newNav ) {
				oldNav.replaceWith( newNav );
			} else if ( oldNav ) {
				oldNav.remove();
			} else if ( newNav ) {
				fresh.insertAdjacentElement( 'afterend', newNav );
			}

			return true;
		};

		const load = async ( url, push ) => {
			ctrl?.abort();
			const mine = new AbortController();
			ctrl = mine;

			scope.classList.add( 'is-loading' );
			scope.setAttribute( 'aria-busy', 'true' );

			try {
				const res = await fetch( url.toString(), { signal: mine.signal, credentials: 'same-origin' } );
				if ( ! res.ok ) {
					throw new Error( `HTTP ${ res.status }` );
				}
				const doc = new DOMParser().parseFromString( await res.text(), 'text/html' );
				if ( ! swap( doc ) ) {
					throw new Error( 'no products' );
				}

				if ( push ) {
					history.pushState( { hodimaSort: true }, '', url.toString() );
				}
				markActive( url.searchParams.get( 'orderby' ) );

				const top = scope.getBoundingClientRect().top + window.scrollY - 100;
				if ( top < window.scrollY ) {
					window.scrollTo( { top, behavior: 'smooth' } );
				}
			} catch ( err ) {
				if ( 'AbortError' !== err?.name ) {
					window.location.href = url.toString(); // بازگشت امن: بارگذاری کامل
				}
			} finally {
				// درخواست لغوشده نشانه درخواست تازه‌تر را برنمی‌دارد
				if ( ctrl === mine ) {
					scope.classList.remove( 'is-loading' );
					scope.removeAttribute( 'aria-busy' );
				}
			}
		};

		const applySort = ( orderby ) => {
			const url = stripPage( new URL( window.location.href ) );
			url.searchParams.set( 'orderby', orderby );
			setMenu( false );
			load( url, true );
		};

		group.addEventListener( 'click', ( event ) => {
			const sub = event.target.closest( '.hodima-sort-sub-btn' );
			if ( sub ) {
				event.preventDefault();
				applySort( sub.getAttribute( 'data-orderby' ) );
				return;
			}

			const btn = event.target.closest( '.hodima-sort-btn' );
			if ( ! btn ) {
				return;
			}
			event.preventDefault();

			const type = btn.getAttribute( 'data-sort-type' );
			if ( 'price-group' === type ) {
				setMenu( ! priceWrap.classList.contains( 'open' ) );
			} else {
				applySort( type );
			}
		} );

		document.addEventListener( 'click', ( event ) => {
			if ( priceWrap && ! priceWrap.contains( event.target ) ) {
				setMenu( false );
			}
		} );

		document.addEventListener( 'keydown', ( event ) => {
			if ( 'Escape' === event.key && priceWrap?.classList.contains( 'open' ) ) {
				setMenu( false );
				priceBtn?.focus();
			}
		} );

		// دکمه برگشت/جلو مرورگر
		window.addEventListener( 'popstate', () => load( new URL( window.location.href ), false ) );

		markActive( new URL( window.location.href ).searchParams.get( 'orderby' ) );
	};

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
