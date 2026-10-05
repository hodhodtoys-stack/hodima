/**
 * صفحه اصلی: کشیدن اسلایدرها با ماوس (دسکتاپ).
 * «Lazy Load Enhancer» برای img.arian-lazy در 2.8.0 حذف شد: هیچ تصویری این کلاس را نداشت.
 * نسخه 3.0.0 (نوسازی قالب، مرحله ۴): بدون انتظار DOMContentLoaded (اسکریپت در فوتر).
 */
( () => {
	// موبایل (لمس بومی) و کاربرانی که حرکت را کم کرده‌اند → بدون کشیدن با ماوس
	if ( window.innerWidth < 900 || window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ) {
		return;
	}

	document.querySelectorAll( '.arian-scroller' ).forEach( ( el ) => {
		let isDown = false;
		let startX = 0;
		let scrollLeft = 0;

		el.addEventListener( 'mousedown', ( event ) => {
			isDown = true;
			el.classList.add( 'active' );
			startX = event.pageX - el.getBoundingClientRect().left;
			scrollLeft = el.scrollLeft;
		}, { passive: true } );

		el.addEventListener( 'mousemove', ( event ) => {
			if ( ! isDown ) {
				return;
			}
			event.preventDefault();
			const x = event.pageX - el.getBoundingClientRect().left;
			el.scrollLeft = scrollLeft - ( x - startX ) * 2;
		} );

		const endDrag = () => {
			isDown = false;
			el.classList.remove( 'active' );
		};
		el.addEventListener( 'mouseleave', endDrag );
		el.addEventListener( 'mouseup', endDrag );
	} );
} )();
