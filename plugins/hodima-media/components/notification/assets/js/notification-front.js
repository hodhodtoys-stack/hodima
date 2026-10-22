/**
 * Hodima Notifications — نمایش در سایت (نسخه ۴)
 *
 * - هر اعلان یک <dialog> بومی: پنجره وسط با showModal() (بقیه صفحه inert،
 *   Escape بومی با رویداد cancel، پرده ::backdrop)، جعبه گوشه با ویژگی open
 *   (فوکوس صفحه را نمی‌گیرد). مرورگر بدون <dialog> (Safari زیر 15.4): ویژگی
 *   open + کلاس hd-notif-fallback و تله فوکوس/Escape دستی.
 * - قوانین وابسته به بازدیدکننده و زمان در مرورگر (صفحه کش‌شده برای همه یکسان
 *   است): تاریخ شروع/پایان، UTM، ارجاع‌دهنده، دستگاه، «تازه از جستجو».
 *   UTM و ارجاع‌دهنده در طول نشست می‌مانند.
 * - پنجره وسط در موبایل با گزینه «جعبه گوشه» غیرمسدودکننده می‌شود (گوگل
 *   پنجره‌ای را که در موبایل محتوا را می‌پوشاند جریمه می‌کند).
 * - بستن در همان نشست به خاطر سپرده می‌شود؛ عکس فقط پیش از نمایش دانلود می‌شود.
 */
( () => {
	'use strict';

	const SESSION_KEY = 'hd_notif_session';
	const MOBILE = '(max-width: 768px)';
	// نتیجه جستجو: گوگل (همه دامنه‌ها)، بینگ، یاهو، داک‌داک‌گو، یاندکس، ...
	const SEARCH_HOST = /(^|\.)(google|bing|yahoo|duckduckgo|yandex|ecosia|baidu|qwant)\.[a-z.]+$/;

	const storage = ( kind ) => {
		try {
			const s = window[ kind ];
			s.getItem( SESSION_KEY );
			return s;
		} catch {
			return null; // حالت خصوصی/کوکی بسته: بدون حافظه کار کند
		}
	};
	const ls = storage( 'localStorage' );
	const ss = storage( 'sessionStorage' );

	const session = ( () => {
		try {
			return JSON.parse( ss?.getItem( SESSION_KEY ) || '{}' ) || {};
		} catch {
			return {};
		}
	} )();
	const saveSession = () => {
		try {
			ss?.setItem( SESSION_KEY, JSON.stringify( session ) );
		} catch {
			/* پر یا بسته: نادیده */
		}
	};

	/* ورود به نشست: شمار صفحه، UTM و ارجاع‌دهنده اولین ورود */
	session.pv = ( session.pv || 0 ) + 1;
	try {
		const utm = [];
		new URLSearchParams( location.search ).forEach( ( value, key ) => {
			if ( key.startsWith( 'utm_' ) && value ) {
				utm.push( value.toLowerCase() );
			}
		} );
		if ( utm.length ) {
			session.utm = utm.join( ' ' );
		}
	} catch {
		/* آدرس نامعتبر */
	}
	if ( ! session.refChecked ) {
		session.refChecked = 1;
		try {
			const host = document.referrer ? new URL( document.referrer ).hostname.toLowerCase() : '';
			if ( host && host !== location.hostname.toLowerCase() ) {
				session.ref = host;
			}
		} catch {
			/* ارجاع نامعتبر */
		}
	}
	saveSession();

	const isMobile = () => window.matchMedia?.( MOBILE ).matches ?? window.innerWidth <= 768;
	const reducedMotion = () => window.matchMedia?.( '(prefers-reduced-motion: reduce)' ).matches ?? false;

	const dismiss = ( id ) => {
		session.dismissed = session.dismissed || {};
		session.dismissed[ id ] = 1;
		saveSession();
	};

	/** صف: هر بار فقط یک اعلان باز است. */
	const queue = {
		items: [],
		busy: false,
		add( fn ) {
			this.items.push( fn );
			this.run();
		},
		run() {
			if ( this.busy || ! this.items.length ) {
				return;
			}
			this.busy = true;
			this.items.shift()();
		},
		done() {
			this.busy = false;
			setTimeout( () => this.run(), 500 );
		},
	};

	const passesClientRules = ( d ) => {
		const now = Date.now();
		const start = Number( d.dataset.start ) || 0;
		const end = Number( d.dataset.end ) || 0;
		if ( ( start && now < start ) || ( end && now > end ) ) {
			return false;
		}

		const device = d.dataset.device || 'all';
		if ( ( device === 'mobile' && ! isMobile() ) || ( device === 'desktop' && isMobile() ) ) {
			return false;
		}

		const utm = d.dataset.utm || '';
		if ( utm && ! ( session.utm || '' ).includes( utm ) ) {
			return false;
		}

		const ref = ( d.dataset.referrer || '' ).replace( /^https?:\/\//, '' ).replace( /\/.*$/, '' );
		if ( ref && ! ( session.ref || '' ).includes( ref ) ) {
			return false;
		}

		// همین الان از نتیجه جستجو آمده (صفحه اول نشست)
		if ( d.dataset.skipSearch === '1' && session.pv === 1 && SEARCH_HOST.test( session.ref || '' ) ) {
			return false;
		}

		return ! session.dismissed?.[ d.dataset.id ];
	};

	/** عکس فقط پیش از نمایش دانلود می‌شود (data-src). */
	const loadImage = ( d ) => new Promise( ( resolve ) => {
		const img = d.querySelector( 'img[data-src]' );
		if ( ! img ) {
			resolve();
			return;
		}
		let finished = false;
		const finish = () => {
			if ( ! finished ) {
				finished = true;
				resolve();
			}
		};
		img.addEventListener( 'load', finish, { once: true } );
		img.addEventListener( 'error', finish, { once: true } );
		if ( img.dataset.srcset ) {
			img.sizes = img.dataset.sizes || '';
			img.srcset = img.dataset.srcset;
		}
		img.src = img.dataset.src;
		img.removeAttribute( 'data-src' );
		if ( img.complete ) {
			finish();
		}
		setTimeout( finish, 2500 ); // شبکه کند: بعد از ۲٫۵ ثانیه به هر حال نمایش
	} );

	const setup = ( d ) => {
		if ( ! passesClientRules( d ) ) {
			return;
		}

		const id = d.dataset.id;
		const delay = Number( d.dataset.delay ) || 0;
		const autoClose = Number( d.dataset.autoClose ) || 0;
		const maxCount = Number( d.dataset.maxCount ) || 0;
		const animIn = d.dataset.animIn || 'fade_in';
		const animOut = d.dataset.animOut || 'fade_out';
		const position = d.dataset.position || 'center';
		const trigExit = d.dataset.triggerExit === '1';
		const trigScroll = Number( d.dataset.triggerScroll ) || 0;
		const trigInact = Number( d.dataset.triggerInactivity ) || 0;
		const countKey = `hd_notif_count_${ id }`;
		const native = typeof d.showModal === 'function';

		let count = 0;
		try {
			count = Number( ls?.getItem( countKey ) ) || 0;
		} catch {
			/* بدون حافظه */
		}
		if ( maxCount > 0 && count >= maxCount ) {
			return;
		}

		let state = 'idle'; // idle ← scheduled ← shown ← closing ← closed
		let modal = false;
		let lastFocus = null;
		let autoTimer = 0;
		let cleanups = [];

		const listen = ( target, type, fn, opts ) => {
			target.addEventListener( type, fn, opts );
			cleanups.push( () => target.removeEventListener( type, fn, opts ) );
		};
		const stopTriggers = () => {
			cleanups.forEach( ( f ) => f() );
			cleanups = [];
		};

		/* پایان بستن (بعد از انیمیشن، یا وقتی مرورگر خودش dialog را بست) */
		const finish = () => {
			if ( state === 'closed' ) {
				return;
			}
			state = 'closed';
			clearTimeout( autoTimer );
			dismiss( id ); // در این نشست دوباره نمایش داده نشود (از هر راهی بسته شود)
			document.removeEventListener( 'keydown', onKey );
			document.removeEventListener( 'click', onOutside, true );
			// مرورگر بدون <dialog> ویژگی d.open ندارد
			if ( d.hasAttribute( 'open' ) ) {
				if ( native ) {
					d.close();
				} else {
					d.removeAttribute( 'open' );
				}
			}
			d.classList.remove( `anim-in-${ animIn }`, `anim-out-${ animOut }`, 'hd-notif-fallback' );
			document.documentElement.classList.remove( 'hd-notif-lock' );
			// فوکوس برگردد (در جعبه گوشه فقط اگر کاربر داخلش بود)
			if ( lastFocus?.focus && ( modal || d.contains( document.activeElement ) || document.activeElement === document.body ) ) {
				lastFocus.focus( { preventScroll: true } );
			}
			queue.done();
		};

		const close = () => {
			if ( state !== 'shown' ) {
				return;
			}
			state = 'closing';
			clearTimeout( autoTimer );
			d.classList.remove( `anim-in-${ animIn }` );
			d.classList.add( `anim-out-${ animOut }` );
			setTimeout( finish, reducedMotion() ? 0 : 400 );
		};

		/* Escape (جعبه گوشه و مرورگر بدون dialog) و تله فوکوس دستی فقط در حالت جایگزین */
		function onKey( e ) {
			if ( e.key === 'Escape' && ( ! modal || ! native ) ) {
				close();
				return;
			}
			if ( e.key === 'Tab' && modal && ! native ) {
				const f = d.querySelectorAll( 'a[href], button' );
				if ( ! f.length ) {
					return;
				}
				const first = f[ 0 ];
				const last = f[ f.length - 1 ];
				if ( e.shiftKey && document.activeElement === first ) {
					e.preventDefault();
					last.focus();
				} else if ( ! e.shiftKey && document.activeElement === last ) {
					e.preventDefault();
					first.focus();
				}
			}
		}

		/* کلیک بیرون پنجره وسط در حالت جایگزین (پرده سایه است و کلیک نمی‌گیرد) */
		function onOutside( e ) {
			if ( ! d.contains( e.target ) ) {
				close();
			}
		}

		const armAutoClose = () => {
			if ( autoClose <= 0 ) {
				return;
			}
			// تا وقتی ماوس روی پاپ‌آپ است (و در جعبه گوشه، تا وقتی فوکوس داخلش است) صبر می‌کند
			const arm = () => {
				clearTimeout( autoTimer );
				autoTimer = setTimeout( close, autoClose );
			};
			const busy = () => d.matches( ':hover' ) || ( ! modal && d.contains( document.activeElement ) );
			const release = () => {
				if ( state === 'shown' && ! busy() ) {
					arm();
				}
			};
			d.addEventListener( 'mouseenter', () => clearTimeout( autoTimer ) );
			d.addEventListener( 'mouseleave', release );
			if ( ! modal ) {
				d.addEventListener( 'focusin', () => clearTimeout( autoTimer ) );
				d.addEventListener( 'focusout', () => setTimeout( release, 0 ) );
			}
			release();
		};

		const open = () => {
			// پنجره وسط در موبایل با گزینه «جعبه گوشه» غیرمسدودکننده است
			const asCorner = position === 'center' && d.dataset.mobileMode === 'corner' && isMobile();
			modal = position === 'center' && ! asCorner;
			d.classList.toggle( 'hd-as-corner', asCorner );
			lastFocus = document.activeElement;

			if ( modal && native ) {
				d.showModal();
			} else {
				d.setAttribute( 'open', '' ); // جعبه گوشه: فوکوس صفحه را نمی‌گیرد
			}
			if ( modal ) {
				document.documentElement.classList.add( 'hd-notif-lock' );
				if ( ! native ) {
					d.classList.add( 'hd-notif-fallback' );
					setTimeout( () => document.addEventListener( 'click', onOutside, true ), 0 );
				}
				/*
				 * فوکوس روی خود پنجره (صفحه‌خوان عنوانش را می‌خواند، Tab به دکمه
				 * بستن می‌رسد). showModal فوکوس را به دکمه بستن می‌داد و چون پنجره
				 * خودکار و بی‌دخالت کاربر باز می‌شود، حلقه فوکوس دورش دیده می‌شد.
				 */
				d.focus( { preventScroll: true } );
			}
			d.classList.add( `anim-in-${ animIn }` );
			state = 'shown';
			document.addEventListener( 'keydown', onKey );

			if ( maxCount > 0 ) {
				try {
					ls?.setItem( countKey, String( count + 1 ) );
				} catch {
					/* بدون حافظه */
				}
			}
			armAutoClose();
		};

		const show = () => {
			if ( state !== 'scheduled' ) {
				return;
			}
			queue.add( () => loadImage( d ).then( open ) );
		};

		const schedule = () => {
			if ( state !== 'idle' ) {
				return;
			}
			state = 'scheduled';
			stopTriggers();
			setTimeout( show, delay );
		};

		// Escape بومی پنجره وسط: با انیمیشن ببند (نه بستن ناگهانی مرورگر)
		d.addEventListener( 'cancel', ( e ) => {
			e.preventDefault();
			close();
		} );
		// مرورگر گاهی بدون cancel می‌بندد (Escape دوم): پاک‌سازی همان‌جا
		d.addEventListener( 'close', finish );

		// کلیک روی پرده (خود dialog، بیرون کادر عکس)
		d.addEventListener( 'click', ( e ) => {
			if ( ! modal || e.target !== d ) {
				return;
			}
			const r = d.getBoundingClientRect();
			if ( e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom ) {
				close();
			}
		} );

		d.querySelector( '.hd-notif-close' )?.addEventListener( 'click', close );

		// کلیک روی لینک یعنی «دیده شد»: در صفحه بعد دوباره باز نشود
		d.querySelector( '.hd-notif-half-link' )?.addEventListener( 'click', () => dismiss( id ) );

		if ( ! trigExit && trigScroll <= 0 && trigInact <= 0 ) {
			schedule();
			return;
		}
		if ( trigExit ) {
			listen( document, 'mouseleave', ( e ) => {
				if ( e.clientY < 0 ) {
					schedule();
				}
			} );
		}
		if ( trigScroll > 0 ) {
			const checkScroll = () => {
				const total = document.documentElement.scrollHeight;
				const view = window.innerHeight;
				if ( total <= view || ( window.scrollY / Math.max( 1, total - view ) ) * 100 >= trigScroll ) {
					schedule();
				}
			};
			listen( window, 'scroll', checkScroll, { passive: true } );
			checkScroll();
		}
		if ( trigInact > 0 ) {
			let timer = 0;
			const reset = () => {
				clearTimeout( timer );
				timer = setTimeout( schedule, trigInact * 1000 );
			};
			[ 'mousemove', 'keydown', 'scroll', 'touchstart' ].forEach( ( type ) => listen( document, type, reset, { passive: true } ) );
			cleanups.push( () => clearTimeout( timer ) );
			reset();
		}
	};

	const boot = () => document.querySelectorAll( 'dialog.hd-notification-popup' ).forEach( setup );

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
