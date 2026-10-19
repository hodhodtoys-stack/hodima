/**
 * دیدگاه‌ها و نظرات: دکمه «نمایش همه»
 * Path: assets/js/comments.js
 *
 * فهرست بلند (inc/comments.php) با کلاس is-collapsed فقط چند رشته اول را نشان می‌دهد
 * (comments.css)؛ همه نظرها در HTML هستند. دکمه بقیه را باز می‌کند و فوکوس را به اولین
 * نظر تازه‌دیده می‌برد. آدرس با #comment-N (لینک ایمیل، بعد از ثبت نظر) هم فهرست را باز
 * می‌کند تا همان نظر دیده شود.
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

	const init = () => {
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
