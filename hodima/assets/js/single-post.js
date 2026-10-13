/**
 * صفحه مقاله: کپی لینک، نوار پیشرفت مطالعه و فهرست مطالب جمع‌شده در موبایل
 * Path: assets/js/single-post.js
 *
 * هر بخش فقط وقتی عنصرش در صفحه هست کار می‌کند (کلیدهای «امکانات صفحه مقاله» در تنظیمات
 * قالب). بدون جاوااسکریپت: لینک‌های اشتراک‌گذاری و فهرست مطالب همچنان کار می‌کنند؛
 * فقط «کپی لینک» (مخفی) و نوار پیشرفت نیستند.
 */
( () => {
	// ── کپی لینک ──
	const copyText = async ( text ) => {
		if ( navigator.clipboard?.writeText ) {
			await navigator.clipboard.writeText( text );
			return;
		}
		// مرورگر بدون Clipboard API یا صفحه غیر امن
		const area = document.createElement( 'textarea' );
		area.value = text;
		area.setAttribute( 'readonly', '' );
		area.style.position = 'fixed';
		area.style.opacity = '0';
		document.body.append( area );
		area.select();
		const done = document.execCommand( 'copy' );
		area.remove();
		if ( ! done ) {
			throw new Error( 'copy failed' );
		}
	};

	for ( const button of document.querySelectorAll( '[data-hodima-copy]' ) ) {
		const label = button.textContent;
		const status = button.closest( '.single-post-share' )?.querySelector( '[data-hodima-copy-status]' );
		let timer = 0;

		// بدون جاوااسکریپت این مورد دیده نمی‌شود (hidden در HTML)
		button.closest( 'li' )?.removeAttribute( 'hidden' );

		button.addEventListener( 'click', async ( event ) => {
			event.preventDefault();
			let message;
			try {
				await copyText( button.dataset.hodimaCopy ?? '' );
				message = button.dataset.done ?? 'کپی شد';
			} catch {
				message = 'کپی نشد؛ لینک را از نوار آدرس بردارید';
			}
			button.textContent = message;
			if ( status ) {
				status.textContent = message;
			}
			clearTimeout( timer );
			timer = setTimeout( () => {
				button.textContent = label;
			}, 2000 );
		} );
	}

	// ── نوار پیشرفت مطالعه: از بالای بخش متن تا پایین آن ──
	const bar = document.querySelector( '.reading-progress__bar' );
	const article = document.querySelector( '.section-description' );
	if ( bar && article ) {
		let queued = false;

		const update = () => {
			queued = false;
			const rect = article.getBoundingClientRect();
			const span = rect.height - window.innerHeight * 0.5;
			const progress = span > 0 ? Math.min( 1, Math.max( 0, -rect.top / span ) ) : 0;
			bar.parentElement.style.setProperty( '--reading-progress', progress.toFixed( 3 ) );
		};

		const onScroll = () => {
			if ( ! queued ) {
				queued = true;
				requestAnimationFrame( update );
			}
		};

		window.addEventListener( 'scroll', onScroll, { passive: true } );
		window.addEventListener( 'resize', onScroll, { passive: true } );
		update();
	}

	// ── فهرست مطالب: تیتر داخل بخش جمع‌شده «نمایش بیشتر» ──
	// افزونه Media بخش را باز می‌کند و ۸۰ میلی‌ثانیه بعد (وسط انیمیشن ۰٫۶ ثانیه‌ای) به تیتر
	// می‌رود؛ چون جای تیتر هنوز پایین‌تر می‌رود، بعد از پایان انیمیشن دوباره به آن می‌رویم.
	const reduceMotion = window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	for ( const link of document.querySelectorAll( '.single-post-toc__list a' ) ) {
		link.addEventListener( 'click', () => {
			let target = null;
			try {
				target = document.getElementById( decodeURIComponent( link.hash.slice( 1 ) ) );
			} catch {
				return;
			}
			const wrapper = target?.closest( '[data-expandable-content]' );
			if ( ! target || ! wrapper || wrapper.classList.contains( 'arian-expanded' ) ) {
				return;
			}
			const wait = ( parseFloat( getComputedStyle( wrapper ).transitionDuration ) || 0 ) * 1000 + 150;
			setTimeout( () => {
				target.scrollIntoView( { behavior: reduceMotion ? 'auto' : 'smooth', block: 'start' } );
			}, wait );
		} );
	}

	// ── فهرست مطالب: در موبایل بسته شروع می‌شود تا متن را پایین نبرد ──
	const toc = document.querySelector( '.single-post-toc__box' );
	if ( toc && window.matchMedia( '(max-width: 768px)' ).matches ) {
		toc.removeAttribute( 'open' );
	}
} )();
