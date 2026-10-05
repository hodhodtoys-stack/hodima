/**
 * هدر: هدر چسبان، منوی موبایل، پنجره پشتیبانی
 * نسخه: 3.0.0 (نوسازی قالب، مرحله ۴)
 *
 * اسکریپت در فوتر و با defer لود می‌شود؛ HTML هدر از قبل در صفحه است، پس
 * منتظر DOMContentLoaded نمی‌ماند.
 */
( () => {
	/**
	 * ──────────────────────────────────────────
	 * ۱. هدر چسبان
	 * ──────────────────────────────────────────
	 * قبلا در هر فریم اسکرول ارتفاع هدر خوانده و padding نوشته می‌شد (خواندن و
	 * نوشتن چیدمان پشت سر هم). حالا فقط وقتی وضعیت (چسبان/عادی) عوض می‌شود.
	 */
	const initStickyHeader = () => {
		const header = document.getElementById( 'mainHeader' );
		const wrapper = document.getElementById( 'headerWrapper' );
		if ( ! header || ! wrapper ) {
			return;
		}

		let fixed = false;
		let ticking = false;

		const update = () => {
			ticking = false;
			const shouldFix = window.scrollY > 10;
			if ( shouldFix === fixed ) {
				return;
			}
			fixed = shouldFix;
			// ارتفاع پیش از fixed شدن (جای خالی هدر تا صفحه نپرد)
			wrapper.style.paddingTop = fixed ? `${ header.offsetHeight }px` : '0';
			header.classList.toggle( 'is-fixed', fixed );
		};

		window.addEventListener( 'scroll', () => {
			if ( ! ticking ) {
				ticking = true;
				window.requestAnimationFrame( update );
			}
		}, { passive: true } );

		update();
	};

	/**
	 * ──────────────────────────────────────────
	 * ۲. منوی موبایل
	 * ──────────────────────────────────────────
	 */
	const initMobileMenu = () => {
		const nav = document.getElementById( 'mainNavWrapper' );
		const toggle = document.getElementById( 'mobileMenuTrigger' );
		const path = toggle?.querySelector( 'svg path' );
		if ( ! nav || ! toggle || ! path ) {
			return;
		}

		// همان نقطه شکست CSS منو
		const desktop = window.matchMedia( '(min-width: 992px)' );
		const HAMBURGER = 'M4 6h16M4 12h16m-7 6h7';
		const CLOSE = 'M6 18L18 6M6 6l12 12';

		const setOpen = ( open ) => {
			nav.classList.toggle( 'is-open', open );
			path.setAttribute( 'd', open ? CLOSE : HAMBURGER );
			toggle.setAttribute( 'aria-expanded', String( open ) );
			toggle.setAttribute( 'aria-label', open ? 'بستن منوی موبایل' : 'باز کردن منوی موبایل' );
			nav.setAttribute( 'aria-hidden', String( ! open ) );
			document.body.classList.toggle( 'menu-is-open', open );
		};

		const isOpen = () => nav.classList.contains( 'is-open' );

		toggle.addEventListener( 'click', () => setOpen( ! isOpen() ) );

		// کلیک بیرون منو آن را می‌بندد
		document.addEventListener( 'click', ( event ) => {
			if ( isOpen() && ! nav.contains( event.target ) && ! toggle.contains( event.target ) ) {
				setOpen( false );
			}
		} );

		document.addEventListener( 'keydown', ( event ) => {
			if ( 'Escape' === event.key && isOpen() ) {
				setOpen( false );
				toggle.focus();
			}
		} );

		// زیرمنو در موبایل: کلیک روی والد باز/بسته می‌کند
		nav.addEventListener( 'click', ( event ) => {
			if ( 'none' === window.getComputedStyle( toggle ).display ) {
				return;
			}
			const link = event.target.closest( '.menu-item-has-children > a' );
			if ( link ) {
				event.preventDefault();
				link.parentElement.classList.toggle( 'submenu-active' );
			}
		} );

		// دسکتاپ: منو همیشه برای صفحه‌خوان در دسترس (بدون aria-hidden)
		const onScreenChange = () => {
			if ( desktop.matches ) {
				nav.removeAttribute( 'aria-hidden' );
				if ( isOpen() ) {
					setOpen( false );
				}
				nav.removeAttribute( 'aria-hidden' );
			} else {
				nav.setAttribute( 'aria-hidden', String( ! isOpen() ) );
			}
		};
		desktop.addEventListener( 'change', onScreenChange );
		onScreenChange();
	};

	/**
	 * ──────────────────────────────────────────
	 * ۳. پنجره پشتیبانی (<dialog>)
	 * ──────────────────────────────────────────
	 * قبلا div با aria-hidden، تله فوکوس Tab دستی و Escape دستی. حالا showModal():
	 * بقیه صفحه inert، Escape بومی (رویداد close). کلاس is-active همان انیمیشن
	 * قبلی (header.css).
	 */
	const initSupportDialog = () => {
		const dialog = document.getElementById( 'supportOverlay' );
		const trigger = document.getElementById( 'supportTrigger' );
		const closeButton = document.getElementById( 'supportClose' );
		if ( ! ( dialog instanceof HTMLDialogElement ) || ! trigger || ! closeButton ) {
			return;
		}

		trigger.addEventListener( 'click', () => {
			dialog.showModal();
			dialog.classList.add( 'is-active' );
			// visibility در اولین فریم انیمیشن هنوز hidden است و فوکوس نمی‌گیرد (نسخه
			// قبلی هم به همین دلیل فوکوس را به لینک نمی‌رساند)؛ یک فریم بعد از شروع
			const focusFirst = () => ( dialog.querySelector( '.popup__link' ) ?? dialog.querySelector( '.popup__close' ) )?.focus();
			window.requestAnimationFrame( () => window.requestAnimationFrame( focusFirst ) );
		} );

		closeButton.addEventListener( 'click', () => dialog.close() );

		// کلیک روی پرده تیره (خود dialog، بیرون کادر) می‌بندد
		dialog.addEventListener( 'click', ( event ) => {
			if ( event.target === dialog ) {
				dialog.close();
			}
		} );

		// بستن از هر راه (دکمه، پرده، Escape)
		dialog.addEventListener( 'close', () => {
			dialog.classList.remove( 'is-active' );
			trigger.focus();
		} );
	};

	initStickyHeader();
	initMobileMenu();
	initSupportDialog();
} )();
