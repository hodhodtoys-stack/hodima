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
 * فلش اسکرولرها (3.1.6؛ بازنویسی 3.1.7). دکمه‌ها با JS ساخته می‌شوند تا HTML و کش صفحه عوض نشود.
 *  - محصولات و مقالات: دو دکمه گرد «قبلی/بعدی» فقط برای ماوس (از «تنظیمات قالب ← صفحه اصلی» خاموش می‌شود).
 *  - دسته‌ها: فقط همان فلش قدیمی لبه چپ (از 3.1.8 رو به راست: ❯) (که قبلا فقط یک نشانه CSS بود)؛ حالا کلیک‌پذیر است
 *    (کلیک ← اسکرول به جلو) و وقتی اسکرول لازم نیست یا به آخر رسیده‌ایم پنهان می‌شود. در لمس هم هست.
 */
( () => {
	const fine = window.matchMedia( '(hover: hover) and (pointer: fine)' ).matches;
	const circles = fine && ! document.body.classList.contains( 'hodima-no-scroll-arrows' );
	const chevron = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M15 6l-6 6 6 6"/></svg>';
	const edge = 4; // تلورانس پیکسل: گردشدن مرورگر یا snap نباید «اسکرول شده» حساب شود

	document.querySelectorAll( '.arian-scroller' ).forEach( ( el ) => {
		const host = el.parentElement;
		const story = el.classList.contains( 'cat-story-wrapper' );
		if ( ! host || ( ! story && ! circles ) ) {
			return;
		}

		const make = ( kind, label, html ) => {
			const button = document.createElement( 'button' );
			button.type = 'button';
			button.className = story ? 'cat-scroll-hint' : `arian-scroll-btn arian-scroll-btn--${ kind }`;
			button.setAttribute( 'aria-label', label );
			button.innerHTML = html;
			return button;
		};
		const next = make( 'next', 'بعدی', story ? '<span aria-hidden="true">\u276F</span>' : chevron );
		const prev = story ? null : make( 'prev', 'قبلی', chevron );
		host.append( ...( prev ? [ prev, next ] : [ next ] ) );

		// scrollLeft در راست‌به‌چپ صفر یا منفی است؛ فاصله از ابتدا = قدر مطلق
		const update = () => {
			const max = el.scrollWidth - el.clientWidth;
			const pos = Math.abs( el.scrollLeft );
			next.hidden = max <= edge || pos >= max - edge;
			if ( prev ) {
				prev.hidden = max <= edge || pos <= edge;
				const top = `${ el.offsetTop + el.offsetHeight / 2 }px`;
				prev.style.insetBlockStart = top;
				next.style.insetBlockStart = top;
			}
		};
		const go = ( towardEnd ) => {
			const rtl = window.getComputedStyle( el ).direction === 'rtl';
			el.scrollBy( { left: ( towardEnd ? 1 : -1 ) * ( rtl ? -1 : 1 ) * el.clientWidth * 0.8 } );
		};

		next.addEventListener( 'click', () => go( true ) );
		prev?.addEventListener( 'click', () => go( false ) );
		el.addEventListener( 'scroll', update, { passive: true } );
		new ResizeObserver( update ).observe( el );
		update();
	} );
} )();
