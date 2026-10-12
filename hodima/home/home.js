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
		let moved = false; // بعد از کشیدن واقعی، رها کردن روی کارت نباید لینک را باز کند
		let startX = 0;
		let scrollLeft = 0;

		el.addEventListener( 'mousedown', ( event ) => {
			isDown = true;
			moved = false;
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
			moved = moved || Math.abs( x - startX ) > 5;
			el.scrollLeft = scrollLeft - ( x - startX ) * 2;
		} );

		// فاز capture: پیش از لینک کارت اجرا می‌شود
		el.addEventListener( 'click', ( event ) => {
			if ( moved ) {
				event.preventDefault();
				event.stopPropagation();
				moved = false;
			}
		}, true );

		const endDrag = () => {
			isDown = false;
			el.classList.remove( 'active' );
		};
		el.addEventListener( 'mouseleave', endDrag );
		el.addEventListener( 'mouseup', endDrag );
	} );
} )();

/**
 * فلش قبلی/بعدی اسکرولرها (3.1.6): فقط برای ماوس (دسکتاپ)؛ لمس و کیبورد مثل قبل.
 * دکمه‌ها با JS ساخته می‌شوند تا HTML و کش صفحه عوض نشود؛ از «تنظیمات قالب ← صفحه اصلی» خاموش می‌شود.
 */
( () => {
	if ( document.body.classList.contains( 'hodima-no-scroll-arrows' ) || ! window.matchMedia( '(hover: hover) and (pointer: fine)' ).matches ) {
		return;
	}

	const icon = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M15 6l-6 6 6 6"/></svg>';

	document.querySelectorAll( '.arian-scroller' ).forEach( ( el ) => {
		const host = el.parentElement;
		if ( ! host ) {
			return;
		}

		const make = ( kind, label ) => {
			const button = document.createElement( 'button' );
			button.type = 'button';
			button.className = `arian-scroll-btn arian-scroll-btn--${ kind }`;
			button.setAttribute( 'aria-label', label );
			button.innerHTML = icon;
			return button;
		};
		const prev = make( 'prev', 'قبلی' );
		const next = make( 'next', 'بعدی' );
		host.append( prev, next );

		// scrollLeft در راست‌به‌چپ صفر یا منفی است؛ فاصله از ابتدا = قدر مطلق
		const update = () => {
			const max = el.scrollWidth - el.clientWidth;
			const pos = Math.abs( el.scrollLeft );
			prev.hidden = max <= 1 || pos <= 1;
			next.hidden = max <= 1 || pos >= max - 1;
			const top = `${ el.offsetTop + el.offsetHeight / 2 }px`;
			prev.style.insetBlockStart = top;
			next.style.insetBlockStart = top;
		};
		const go = ( towardEnd ) => {
			const rtl = window.getComputedStyle( el ).direction === 'rtl';
			el.scrollBy( { left: ( towardEnd ? 1 : -1 ) * ( rtl ? -1 : 1 ) * el.clientWidth * 0.8 } );
		};

		prev.addEventListener( 'click', () => go( false ) );
		next.addEventListener( 'click', () => go( true ) );
		el.addEventListener( 'scroll', update, { passive: true } );
		new ResizeObserver( update ).observe( el );
		update();
	} );
} )();
