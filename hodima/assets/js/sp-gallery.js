/**
 * SP Gallery — گالری سفارشی محصول
 * Vanilla JS — بدون وابستگی
 * ساپورت: Touch Swipe + Keyboard + Lightbox (<dialog>) + Lazy Load
 *
 * نسخه 3.0.0 (نوسازی قالب، مرحله ۴): بدون var/function‌های تو در تو؛ لایت‌باکس
 * <dialog> بومی با showModal() — بقیه صفحه inert (تله فوکوس Tab دستی حذف شد)،
 * Escape با همان انیمیشن بسته شدن (رویداد cancel). رفتار و ظاهر همان قبلی.
 *
 * @package hodima
 * @since   1.0.0
 */
( () => {
	const gallery = document.querySelector( '.sp-gallery' );
	if ( ! gallery ) {
		return;
	}

	const slides = [ ...gallery.querySelectorAll( '.sp-gallery__slide' ) ];
	const thumbs = [ ...gallery.querySelectorAll( '.sp-gallery__thumb' ) ];
	const zoomBtn = gallery.querySelector( '.sp-gallery__zoom' );
	const thumbsTrack = gallery.querySelector( '.sp-gallery__thumbs-track' );
	const mainImage = gallery.querySelector( '.sp-gallery__main-image' );

	const lightbox = document.querySelector( '.sp-lightbox' );
	const lb = {
		img: lightbox?.querySelector( '.sp-lightbox__img' ),
		close: lightbox?.querySelector( '.sp-lightbox__close' ),
		prev: lightbox?.querySelector( '.sp-lightbox__nav--prev' ),
		next: lightbox?.querySelector( '.sp-lightbox__nav--next' ),
		overlay: lightbox?.querySelector( '.sp-lightbox__overlay' ),
		counter: lightbox?.querySelector( '.sp-lightbox__counter' ),
	};

	const total = slides.length;
	if ( 0 === total ) {
		return;
	}

	let current = 0;
	let isLightboxOpen = false;
	let hasSwiped = false;
	let hideTimer = 0;

	// RTL: «بعدی» سمت چپ است (ترتیب تامبنیل‌ها از راست به چپ)
	const isRTL = 'rtl' === ( document.documentElement.getAttribute( 'dir' ) || getComputedStyle( document.documentElement ).direction );

	const wrap = ( index ) => ( ( index % total ) + total ) % total;

	/** src اولیه اسلایدهای ۲ به بعد تامبنیل است؛ تصویر کامل جایگزین می‌شود. */
	const loadFull = ( slide ) => {
		if ( slide?.dataset.src && slide.getAttribute( 'src' ) !== slide.dataset.src ) {
			slide.src = slide.dataset.src;
		}
	};

	/** اسکرول تامبنیل فعال به محدوده دید. */
	const scrollThumbIntoView = ( index ) => {
		const thumb = thumbs[ index ];
		if ( ! thumbsTrack || ! thumb ) {
			return;
		}
		const trackRect = thumbsTrack.getBoundingClientRect();
		const thumbRect = thumb.getBoundingClientRect();

		if ( thumbRect.right > trackRect.right ) {
			thumbsTrack.scrollLeft += thumbRect.right - trackRect.right + 20;
		} else if ( thumbRect.left < trackRect.left ) {
			thumbsTrack.scrollLeft -= trackRect.left - thumbRect.left + 20;
		}
	};

	/** تغییر اسلاید (و تامبنیل فعال)؛ اسلایدهای مجاور پیش‌بارگذاری می‌شوند. */
	const goTo = ( requested ) => {
		const index = wrap( requested );

		loadFull( slides[ index ] );
		slides[ current ].classList.remove( 'is-active' );
		slides[ index ].classList.add( 'is-active' );

		if ( thumbs.length ) {
			thumbs[ current ].classList.remove( 'is-active' );
			thumbs[ current ].setAttribute( 'aria-current', 'false' );
			thumbs[ index ].classList.add( 'is-active' );
			thumbs[ index ].setAttribute( 'aria-current', 'true' );
			scrollThumbIntoView( index );
		}

		current = index;
		loadFull( slides[ wrap( index + 1 ) ] );
		loadFull( slides[ wrap( index - 1 ) ] );
	};

	thumbs.forEach( ( thumb ) => {
		thumb.addEventListener( 'click', () => goTo( Number.parseInt( thumb.dataset.index, 10 ) ) );
	} );

	// ══════════════════════════════════════════
	// Touch Swipe (موبایل)
	// ══════════════════════════════════════════
	if ( mainImage ) {
		let startX = 0;
		let startY = 0;
		let isSwiping = false;

		mainImage.addEventListener( 'touchstart', ( event ) => {
			startX = event.touches[ 0 ].clientX;
			startY = event.touches[ 0 ].clientY;
			isSwiping = true;
			hasSwiped = false;
		}, { passive: true } );

		mainImage.addEventListener( 'touchmove', ( event ) => {
			if ( ! isSwiping ) {
				return;
			}
			const diffX = Math.abs( event.touches[ 0 ].clientX - startX );
			const diffY = Math.abs( event.touches[ 0 ].clientY - startY );
			if ( diffX > diffY && diffX > 10 ) {
				event.preventDefault();
				hasSwiped = true;
			}
		}, { passive: false } );

		mainImage.addEventListener( 'touchend', ( event ) => {
			if ( ! isSwiping ) {
				return;
			}
			isSwiping = false;

			// RTL: swipe چپ = بعدی، swipe راست = قبلی
			const diff = startX - event.changedTouches[ 0 ].clientX;
			if ( diff > 50 ) {
				hasSwiped = true;
				goTo( current + 1 );
			} else if ( diff < -50 ) {
				hasSwiped = true;
				goTo( current - 1 );
			}
		}, { passive: true } );
	}

	// ══════════════════════════════════════════
	// لایت‌باکس (<dialog>)
	// ══════════════════════════════════════════
	const showInLightbox = ( index ) => {
		const slide = slides[ index ];
		if ( lb.img ) {
			lb.img.src = slide.dataset.full || slide.dataset.src || slide.src;
			lb.img.alt = slide.alt || '';
		}
		if ( lb.counter ) {
			lb.counter.textContent = `${ index + 1 } / ${ total }`;
		}
	};

	const openLightbox = ( index ) => {
		if ( ! ( lightbox instanceof HTMLDialogElement ) || ! lb.img ) {
			return;
		}
		isLightboxOpen = true;
		showInLightbox( index );
		clearTimeout( hideTimer );

		if ( ! lightbox.open ) {
			lightbox.showModal();
		}
		// یک فریم بعد تا انیمیشن باز شدن اجرا شود؛ فوکوس به «بستن» یک فریم بعدتر
		// (در فریم اول visibility هنوز hidden است و فوکوس نمی‌گیرد — نسخه قبلی هم)
		requestAnimationFrame( () => {
			lightbox.classList.add( 'is-open' );
			requestAnimationFrame( () => lb.close?.focus() );
		} );
		document.body.style.overflow = 'hidden';
	};

	const closeLightbox = () => {
		if ( ! ( lightbox instanceof HTMLDialogElement ) || ! isLightboxOpen ) {
			return;
		}
		isLightboxOpen = false;
		lightbox.classList.remove( 'is-open' );
		document.body.style.overflow = '';
		// بستن dialog بعد از پایان انیمیشن (فوکوس را خود مرورگر به دکمه/تصویر قبلی برمی‌گرداند)
		hideTimer = setTimeout( () => lightbox.close(), 350 );
	};

	const lightboxGoTo = ( index ) => {
		goTo( index );
		showInLightbox( current );
	};

	zoomBtn?.addEventListener( 'click', () => openLightbox( current ) );

	// کلیک روی تصویر اصلی → لایت‌باکس (فقط اگر swipe نبوده)
	mainImage?.addEventListener( 'click', () => {
		if ( ! hasSwiped ) {
			openLightbox( current );
		}
		hasSwiped = false;
	} );

	lb.close?.addEventListener( 'click', closeLightbox );
	lb.overlay?.addEventListener( 'click', closeLightbox );

	// ناوبری لایت‌باکس (RTL)
	lb.prev?.addEventListener( 'click', () => lightboxGoTo( current + 1 ) );
	lb.next?.addEventListener( 'click', () => lightboxGoTo( current - 1 ) );

	if ( lightbox ) {
		// Escape بومی dialog فورا می‌بندد؛ به‌جایش همان بستن با انیمیشن
		lightbox.addEventListener( 'cancel', ( event ) => {
			event.preventDefault();
			closeLightbox();
		} );

		let lbStartX = 0;
		lightbox.addEventListener( 'touchstart', ( event ) => {
			lbStartX = event.touches[ 0 ].clientX;
		}, { passive: true } );

		lightbox.addEventListener( 'touchend', ( event ) => {
			const diff = lbStartX - event.changedTouches[ 0 ].clientX;
			if ( diff > 50 ) {
				lightboxGoTo( current + 1 );
			} else if ( diff < -50 ) {
				lightboxGoTo( current - 1 );
			}
		}, { passive: true } );
	}

	// ══════════════════════════════════════════
	// کیبورد
	// ══════════════════════════════════════════
	/*
	 * فقط وقتی لایت‌باکس باز است یا فوکوس داخل گالری است. نسخه‌های قدیمی به
	 * کل صفحه گوش می‌دادند: فلش چپ و راست هنگام تایپ نظر یا تعداد، عکس را عوض می‌کرد.
	 */
	const isEditable = ( el ) => !! el && ( [ 'INPUT', 'TEXTAREA', 'SELECT' ].includes( el.tagName ) || el.isContentEditable );

	document.addEventListener( 'keydown', ( event ) => {
		if ( isEditable( event.target ) ) {
			return;
		}
		if ( ! isLightboxOpen && ! gallery.contains( document.activeElement ) ) {
			return;
		}
		if ( 'ArrowRight' !== event.key && 'ArrowLeft' !== event.key ) {
			return;
		}

		const step = ( 'ArrowLeft' === event.key ) === isRTL ? 1 : -1;
		event.preventDefault();

		if ( isLightboxOpen ) {
			lightboxGoTo( current + step );
		} else {
			goTo( current + step );
			thumbs[ current ]?.focus();
		}
	} );

	// پیش‌بارگذاری اولیه اسلایدهای مجاور
	loadFull( slides[ wrap( 1 ) ] );
	loadFull( slides[ wrap( -1 ) ] );
} )();
