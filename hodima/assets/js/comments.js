/**
 * دیدگاه‌ها و نظرات: دکمه «نمایش همه» و (3.9.1) «مرتب‌سازی نظرها»ی محصول
 * Path: assets/js/comments.js
 *
 * فهرست بلند (inc/comments.php) با کلاس is-collapsed فقط چند رشته اول را نشان می‌دهد
 * (comments.css)؛ همه نظرها در HTML هستند. دکمه بقیه را باز می‌کند و فوکوس را به اولین
 * نظر تازه‌دیده می‌برد. آدرس با #comment-N (لینک ایمیل، بعد از ثبت نظر) هم فهرست را باز
 * می‌کند تا همان نظر دیده شود.
 *
 * مرتب‌سازی فقط ترتیب رشته‌های سطح اول همان فهرست را در صفحه عوض می‌کند (پاسخ‌ها با
 * نظر خودشان می‌مانند؛ بدون درخواست به سرور، پس با کش صفحه سازگار). امتیاز و زمان از
 * data-rating/data-time روی p.meta همان نظر (review-meta.php). وقتی فهرست جمع است، بعد از
 * مرتب‌سازی همان چند نظر اول ترتیب تازه دیده می‌شوند (قانون nth-child کنار comments.css، inc/comments.php).
 */
( () => {
	const expand = ( list ) => {
		if ( ! list.classList.contains( 'is-collapsed' ) ) {
			return null;
		}
		list.classList.remove( 'is-collapsed' );
		document.querySelectorAll( `[data-hodima-comments-more][aria-controls="${ list.id }"]` ).forEach( ( button ) => button.remove() );
		return list.children[ Number.parseInt( list.dataset.visible, 10 ) || 0 ] || null;
	};

	const showHashTarget = () => {
		const id = decodeURIComponent( window.location.hash.slice( 1 ) );
		const target = id ? document.getElementById( id ) : null;
		const list = target?.closest( '.hodima-comments.is-collapsed' );
		if ( list ) {
			expand( list );
			target.scrollIntoView();
		}
	};

	/** امتیاز و زمان خود نظر (نه پاسخ‌های زیرش). */
	const reviewData = ( item ) => {
		const meta = item.querySelector( ':scope > .comment_container [data-time]' );
		return {
			rating: Number( meta?.dataset.rating ) || 0,
			time: Number( meta?.dataset.time ) || 0,
		};
	};

	const ORDERS = {
		newest: ( a, b ) => b.time - a.time,
		oldest: ( a, b ) => a.time - b.time,
		highest: ( a, b ) => b.rating - a.rating || b.time - a.time,
		lowest: ( a, b ) => a.rating - b.rating || b.time - a.time,
	};

	const initSort = ( box ) => {
		const select = box.querySelector( 'select' );
		const list = select ? document.getElementById( select.getAttribute( 'aria-controls' ) ) : null;
		if ( ! list ) {
			return;
		}
		box.hidden = false;
		select.addEventListener( 'change', () => {
			const compare = ORDERS[ select.value ];
			if ( ! compare ) {
				return;
			}
			const items = [ ...list.children ].map( ( item ) => ( { item, ...reviewData( item ) } ) );
			items.sort( compare ).forEach( ( { item } ) => list.append( item ) );
		} );
	};

	const init = () => {
		document.querySelectorAll( '[data-hodima-comments-sort]' ).forEach( initSort );

		document.querySelectorAll( '[data-hodima-comments-more]' ).forEach( ( button ) => {
			button.addEventListener( 'click', () => {
				const list = document.getElementById( button.getAttribute( 'aria-controls' ) );
				const first = list ? expand( list ) : null;
				if ( first ) {
					first.setAttribute( 'tabindex', '-1' );
					first.focus();
				}
			} );
		} );

		showHashTarget();
		window.addEventListener( 'hashchange', showHashTarget );
	};

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
