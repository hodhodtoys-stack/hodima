/**
 * Hodima Stories — پخش‌کننده پاپ‌آپ استوری.
 *
 * فهرست پخش از لینک‌های نوار استوری (.story-link) ساخته می‌شود؛ پیمایش با
 * لمس، کشیدن، صفحه‌کلید و نوار پیشرفت؛ ثبت بازدید/اشتراک با admin-ajax.
 */
( () => {
	'use strict';

	const C = window.HS_PLAYER_STR || {};
	const S = C.settings || { autoplayNext: true, loopPlaylist: false, tapNavigation: true, swipeNavigation: true, preloadAdjacent: true };
	const SEL = C.selectors || { storyLink: '.story-link', playlistScope: '[data-story-group], .section-stories' };

	const $ = ( s, r = document ) => r.querySelector( s );
	const $$ = ( s, r = document ) => Array.from( r.querySelectorAll( s ) );
	const t = ( k, fb ) => C[ k ] || fb || '';
	const DAY = 86400000;

	// پیش‌گرم کردن ویدیوی بعدی فقط روی اینترنت سریع و بدون «صرفه‌جویی در داده»
	const canWarmUp = () => {
		const c = navigator.connection;
		if ( ! c ) {
			return true;
		}
		return ! c.saveData && ! /^(slow-2g|2g|3g)$/.test( c.effectiveType || '' );
	};

	// در RTL «بعدی» سمت چپ است؛ همان جهتی که ناحیه‌های لمسی دارند
	const isRTL = () => ( document.documentElement.getAttribute( 'dir' ) || getComputedStyle( document.documentElement ).direction ) === 'rtl';

	// دسترسی امن به حافظه مرورگر (حالت خصوصی یا پر بودن حافظه خطا می‌دهد)
	const store = {
		get: ( k ) => {
			try {
				return localStorage.getItem( k );
			} catch {
				return null;
			}
		},
		set: ( k, v ) => {
			try {
				localStorage.setItem( k, v );
			} catch { /* حافظه در دسترس نیست */ }
		},
	};

	let playlist = [];
	let currentIndex = -1;
	let toastTimer;
	let touchX = 0;
	let touchY = 0;
	let modalOpen = false;
	let lastFocus;
	let prevOverflow;
	let rafId;
	// پاسخ دیررس play() یک استوری قبلی پیام استوری فعلی را بازنویسی نکند
	let openToken = 0;
	// تمیزکاری بستن (۳۰۰ میلی‌ثانیه بعد) اگر استوری تازه‌ای باز شد لغو شود
	let closeTimeoutId = null;
	let historyPushed = false;
	// کاربر خودش صدا را بسته؟ (استوری بعدی هم بی‌صدا بماند)
	let userMuted = false;
	const DOM = {};

	function initDOM() {
		[ 'hs-video-modal', 'hs-modal-video', 'hs-story-title', 'hs-story-position', 'hs-view-count', 'hs-player-message', 'hs-player-toast', 'hs-cta-box', 'hs-cta-text' ].forEach( ( id ) => {
			DOM[ id ] = document.getElementById( id );
		} );

		[ '.hs-player', '.hs-progress-wrap', '.hs-stage', '.hs-modal__content', '.hs-mute-toggle' ].forEach( ( c ) => {
			DOM[ c ] = $( c );
		} );
	}

	const showToast = ( msg, type = 'info' ) => {
		const toast = DOM[ 'hs-player-toast' ];
		if ( ! toast ) {
			return;
		}
		toast.textContent = msg;
		toast.className = `hs-toast is-show is-${ type }`;
		clearTimeout( toastTimer );
		toastTimer = setTimeout( () => {
			toast.className = 'hs-toast';
		}, 2200 );
	};

	/**
	 * درخواست ثبت آمار. اگر nonce صفحه کش‌شده کهنه بود، سرور nonce تازه
	 * می‌فرستد و یک بار دوباره ارسال می‌شود (باگ قبلی: روی صفحه کش‌شده
	 * بعد از حدود یک روز هیچ بازدیدی ثبت نمی‌شد).
	 *
	 * @return {Promise<{ok: boolean, data?: Object}>} نتیجه.
	 */
	const ajax = async ( action, payload = {}, retried = false ) => {
		if ( ! C.ajaxUrl ) {
			return { ok: false };
		}

		const body = new URLSearchParams( { action, ...payload, security: C.nonce || '' } );

		try {
			const res = await fetch( C.ajaxUrl, {
				method: 'POST',
				body,
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			} );
			const json = await res.json();
			const data = json?.data || {};
			if ( ! json?.success && data.code === 'nonce' && data.nonce && ! retried ) {
				C.nonce = data.nonce;
				return ajax( action, payload, true );
			}
			return { ok: !! json?.success, data };
		} catch {
			return { ok: false };
		}
	};

	const updateProgress = () => {
		const v = DOM[ 'hs-modal-video' ];
		if ( ! v || currentIndex < 0 ) {
			return;
		}

		$$( '.hs-progress-fill' ).forEach( ( fill, i ) => {
			let width = '0%';
			if ( i < currentIndex ) {
				width = '100%';
			} else if ( i === currentIndex && v.duration ) {
				width = `${ ( v.currentTime / v.duration ) * 100 }%`;
			}
			fill.style.width = width;
		} );
	};

	const loopProgress = () => {
		updateProgress();
		if ( modalOpen && ! DOM[ 'hs-modal-video' ].paused ) {
			rafId = requestAnimationFrame( loopProgress );
		}
	};

	// وضعیت دکمه صدا = وضعیت واقعی ویدیو
	const syncMute = () => {
		const v = DOM[ 'hs-modal-video' ];
		const btn = DOM[ '.hs-mute-toggle' ];
		if ( ! v || ! btn ) {
			return;
		}
		btn.setAttribute( 'aria-pressed', v.muted ? 'true' : 'false' );
		DOM[ 'hs-video-modal' ].classList.toggle( 'is-muted', v.muted );
	};

	const syncUI = () => {
		const item = playlist[ currentIndex ];
		if ( ! item ) {
			return;
		}

		if ( DOM[ 'hs-story-title' ] ) {
			DOM[ 'hs-story-title' ].textContent = item.title || '';
		}
		if ( DOM[ 'hs-story-position' ] ) {
			DOM[ 'hs-story-position' ].textContent = `${ currentIndex + 1 } / ${ playlist.length }`;
		}
		if ( DOM[ 'hs-view-count' ] ) {
			DOM[ 'hs-view-count' ].textContent = item.views;
		}

		// دکمه محصول فقط وقتی استوری لینک محصول دارد
		const ctaBox = DOM[ 'hs-cta-box' ];
		if ( ctaBox ) {
			if ( item.ctaLink ) {
				ctaBox.href = item.ctaLink;
				if ( DOM[ 'hs-cta-text' ] ) {
					DOM[ 'hs-cta-text' ].textContent = item.ctaText || t( 'ctaDefault', 'مشاهده محصول' );
				}
				ctaBox.hidden = false;
			} else {
				ctaBox.hidden = true;
				ctaBox.removeAttribute( 'href' );
			}
		}

		const btnPrev = $( '.hs-nav--prev' );
		const btnNext = $( '.hs-nav--next' );
		if ( btnPrev ) {
			btnPrev.disabled = currentIndex <= 0 && ! S.loopPlaylist;
		}
		if ( btnNext ) {
			btnNext.disabled = currentIndex >= playlist.length - 1 && ! S.loopPlaylist;
		}

		$$( '.hs-progress-item' ).forEach( ( it, i ) => {
			it.classList.toggle( 'is-active', i === currentIndex );
			it.classList.toggle( 'is-complete', i < currentIndex );
			if ( i === currentIndex ) {
				it.setAttribute( 'aria-current', 'true' );
			} else {
				it.removeAttribute( 'aria-current' );
			}
		} );

		updateProgress();
	};

	const togglePlay = () => {
		const v = DOM[ 'hs-modal-video' ];
		if ( ! v ) {
			return;
		}
		if ( v.paused ) {
			v.play().catch( () => showToast( t( 'playError', 'خطا در پخش' ) ) );
		} else {
			v.pause();
		}
	};

	const toggleMute = () => {
		const v = DOM[ 'hs-modal-video' ];
		if ( ! v ) {
			return;
		}
		v.muted = ! v.muted;
		userMuted = v.muted;
		if ( ! v.muted ) {
			DOM[ 'hs-player-message' ].textContent = '';
			if ( v.paused ) {
				v.play().catch( () => {} );
			}
		}
		syncMute();
	};

	/**
	 * لینک اشتراک: صفحه همین سایت که خودش همان استوری را باز می‌کند
	 * (باگ قبلی: لینک خام فایل mp4 فرستاده می‌شد و گیرنده به سایت نمی‌آمد).
	 */
	const shareUrlFor = ( item ) => {
		if ( item.shareUrl ) {
			return item.shareUrl;
		}
		if ( ! item.storyId ) {
			return item.url;
		}
		return `${ window.location.origin }${ window.location.pathname }${ window.location.search }#story-${ item.storyId }`;
	};

	// ثبت اشتراک فقط یک بار در روز برای هر استوری، و فقط اگر سرور پذیرفت
	const trackShare = async ( item ) => {
		if ( ! item.storyId ) {
			return;
		}
		const key = `hs_shared_${ item.storyId }`;
		const last = parseInt( store.get( key ), 10 );
		if ( last && Date.now() - last <= DAY ) {
			return;
		}
		const res = await ajax( 'hs_track_share', { story_id: item.storyId } );
		if ( res.ok ) {
			store.set( key, String( Date.now() ) );
		}
	};

	const handleShare = async () => {
		const item = playlist[ currentIndex ];
		if ( ! item ) {
			return;
		}
		const url = shareUrlFor( item );

		try {
			if ( navigator.share ) {
				try {
					await navigator.share( { title: item.title, url } );
				} catch ( shareErr ) {
					// بستن پنجره اشتراک توسط کاربر خطا نیست و اشتراک هم حساب نمی‌شود
					if ( shareErr?.name === 'AbortError' ) {
						return;
					}
					throw shareErr;
				}
			} else if ( navigator.clipboard ) {
				await navigator.clipboard.writeText( url );
				showToast( t( 'copySuccess', 'کپی شد' ), 'success' );
			} else {
				throw new Error( 'no share' );
			}
			trackShare( item );
		} catch {
			showToast( t( 'shareError', 'اشتراک‌گذاری انجام نشد.' ), 'error' );
		}
	};

	/**
	 * ثبت بازدید: یک بار در روز برای هر استوری. علامت «دیده‌شده» و عدد +۱
	 * فقط بعد از پذیرش سرور (باگ قبلی: اگر سرور رد می‌کرد، عدد روی صفحه
	 * باز هم بالا می‌رفت و بازدید تا فردا دوباره فرستاده نمی‌شد).
	 */
	const trackView = async ( item ) => {
		const key = `hs_view_${ item.storyId }`;
		const last = parseInt( store.get( key ), 10 );
		if ( last && Date.now() - last <= DAY ) {
			return;
		}
		const res = await ajax( 'hs_track_view', { story_id: item.storyId } );
		if ( ! res.ok ) {
			return;
		}
		store.set( key, String( Date.now() ) );
		if ( res.data?.recorded ) {
			item.views++;
			if ( playlist[ currentIndex ] === item ) {
				syncUI();
			}
		}
	};

	const openStory = async ( idx ) => {
		if ( ! playlist.length || idx < 0 || idx >= playlist.length ) {
			return;
		}

		cancelAnimationFrame( rafId );
		currentIndex = idx;
		const item = playlist[ idx ];
		const myToken = ++openToken;

		item.trackedThisSession = false;

		const v = DOM[ 'hs-modal-video' ];

		DOM[ 'hs-player-message' ].textContent = '';
		DOM[ '.hs-player' ].classList.add( 'is-loading' );

		v.pause();
		v.src = item.url;
		if ( item.poster ) {
			v.setAttribute( 'poster', item.poster );
		} else {
			v.removeAttribute( 'poster' );
		}
		v.load();

		const wrap = DOM[ '.hs-progress-wrap' ];
		if ( wrap.children.length !== playlist.length ) {
			const label = t( 'storyN', 'استوری %s' );
			wrap.innerHTML = playlist.map( ( _, i ) => `<button type="button" class="hs-progress-item" data-index="${ i }" aria-label="${ label.replace( '%s', String( i + 1 ) ) }"><span class="hs-progress-fill"></span></button>` ).join( '' );
		}

		syncUI();

		// پیش‌گرم کردن ویدیوی بعدی (<link rel="preload" as="video"> در مرورگرها پذیرفته نمی‌شد)
		if ( S.preloadAdjacent && playlist[ idx + 1 ] && canWarmUp() ) {
			const warm = document.createElement( 'video' );
			warm.preload = 'metadata';
			warm.muted = true;
			warm.src = playlist[ idx + 1 ].url;
			setTimeout( () => {
				warm.removeAttribute( 'src' );
				warm.load();
			}, 8000 );
		}

		try {
			v.muted = userMuted;
			await v.play();
		} catch {
			try {
				// مرورگر پخش با صدا را بدون لمس کاربر نمی‌پذیرد؛ بی‌صدا، با دکمه صدا
				v.muted = true;
				await v.play();
				if ( myToken === openToken && ! userMuted ) {
					DOM[ 'hs-player-message' ].textContent = t( 'mutedHint', '' );
				}
			} catch {
				if ( myToken === openToken ) {
					DOM[ '.hs-player' ].classList.remove( 'is-loading' );
					DOM[ 'hs-player-message' ].textContent = t( 'playHint', 'برای پخش کلیک کنید.' );
				}
			}
		}
		if ( myToken === openToken ) {
			syncMute();
		}
	};

	const goNext = () => {
		if ( currentIndex < playlist.length - 1 ) {
			openStory( currentIndex + 1 );
		} else if ( S.loopPlaylist ) {
			openStory( 0 );
		} else {
			showToast( t( 'playlistEnd', 'پایان استوری‌ها' ) );
		}
	};

	const goPrev = () => {
		const v = DOM[ 'hs-modal-video' ];
		if ( v && v.currentTime > 3 ) {
			v.currentTime = 0;
		} else if ( currentIndex > 0 ) {
			openStory( currentIndex - 1 );
		} else if ( S.loopPlaylist ) {
			openStory( playlist.length - 1 );
		}
	};

	const FOCUSABLE = 'button, [href], video, [tabindex]:not([tabindex="-1"])';

	const trapFocus = ( e ) => {
		if ( ! modalOpen || e.key !== 'Tab' ) {
			return;
		}
		const scope = DOM[ '.hs-modal__content' ];
		if ( ! scope ) {
			return;
		}

		const focusable = $$( FOCUSABLE, scope ).filter( ( el ) => ! el.disabled && ! el.hidden && el.offsetParent !== null );
		if ( ! focusable.length ) {
			return;
		}

		const first = focusable[ 0 ];
		const last = focusable[ focusable.length - 1 ];

		if ( e.shiftKey && document.activeElement === first ) {
			e.preventDefault();
			last.focus();
		} else if ( ! e.shiftKey && document.activeElement === last ) {
			e.preventDefault();
			first.focus();
		}
	};

	const closeModal = ( fromPopState = false ) => {
		if ( ! modalOpen ) {
			return;
		}

		cancelAnimationFrame( rafId );
		modalOpen = false;

		// ورودی تاریخچه‌ای که هنگام باز شدن اضافه شد برداشته شود
		if ( historyPushed && ! fromPopState ) {
			historyPushed = false;
			try {
				history.back();
			} catch { /* noop */ }
		}
		historyPushed = false;
		DOM[ 'hs-video-modal' ].classList.remove( 'is-open' );
		DOM[ 'hs-video-modal' ].setAttribute( 'aria-hidden', 'true' );

		closeTimeoutId = setTimeout( () => {
			DOM[ 'hs-modal-video' ].pause();
			DOM[ 'hs-modal-video' ].removeAttribute( 'src' );
			DOM[ 'hs-video-modal' ].style.display = 'none';
			document.body.style.overflow = prevOverflow;
			playlist = [];
			currentIndex = -1;
			if ( lastFocus ) {
				lastFocus.focus();
			}
			closeTimeoutId = null;
		}, 300 );
	};

	// باز کردن فهرست پخش از یک لینک نوار استوری
	const openFromLink = ( link ) => {
		// تمیزکاری بستنِ همین الان، وضعیت استوری تازه را پاک نکند
		if ( closeTimeoutId ) {
			clearTimeout( closeTimeoutId );
			closeTimeoutId = null;
		}

		const scope = link.closest( SEL.playlistScope ) || document;

		playlist = $$( SEL.storyLink, scope ).map( ( el ) => ( {
			el,
			storyId: el.dataset.storyId || '',
			url: el.dataset.video || el.href,
			poster: el.dataset.poster || '',
			title: el.dataset.title || el.title || el.textContent.trim(),
			views: parseInt( el.dataset.views, 10 ) || 0,
			shareUrl: el.dataset.shareUrl || '',
			ctaLink: el.dataset.ctaLink || '',
			ctaText: el.dataset.ctaText || '',
			trackedThisSession: false,
		} ) ).filter( ( i ) => i.url );

		currentIndex = playlist.findIndex( ( i ) => i.el === link );
		if ( currentIndex < 0 ) {
			return;
		}

		lastFocus = document.activeElement;
		prevOverflow = document.body.style.overflow;
		DOM[ 'hs-video-modal' ].style.display = 'flex';
		DOM[ 'hs-video-modal' ].setAttribute( 'aria-hidden', 'false' );

		requestAnimationFrame( () => {
			DOM[ 'hs-video-modal' ].classList.add( 'is-open' );
			document.body.style.overflow = 'hidden';
			modalOpen = true;
			// دکمه «برگشت» گوشی باید استوری را ببندد، نه از صفحه خارج شود
			try {
				history.pushState( { hsStory: true }, '' );
				historyPushed = true;
			} catch {
				historyPushed = false;
			}
			openStory( currentIndex );
			const closeBtn = $( '.hs-modal__close' );
			if ( closeBtn ) {
				closeBtn.focus();
			}
		} );
	};

	/*
	 * لینک اشتراک (‎#story-شناسه): همان استوری خودکار باز شود. نشانی پیش از
	 * باز کردن از آدرس برداشته می‌شود تا تازه کردن صفحه دوباره بازش نکند.
	 */
	const openFromHash = () => {
		const m = /^#story-(\d+)$/.exec( window.location.hash );
		if ( ! m || modalOpen ) {
			return;
		}
		const link = $$( SEL.storyLink ).find( ( el ) => el.dataset.storyId === m[ 1 ] && el.dataset.storyPlayer !== 'off' );
		if ( ! link ) {
			return;
		}
		try {
			history.replaceState( history.state, '', `${ window.location.pathname }${ window.location.search }` );
		} catch { /* noop */ }
		openFromLink( link );
	};

	// کلیک ساده چپ (نه تب جدید با Ctrl/Shift/کلیک وسط)
	const isPlainClick = ( e ) => e.button === 0 && ! e.ctrlKey && ! e.metaKey && ! e.shiftKey && ! e.altKey;

	const onCtaClick = ( e, ctaLink ) => {
		/*
		 * فقط کلیک ساده در همین تب ویدیو را به پخش‌کننده کوچک صفحه مقصد
		 * می‌سپارد (باگ قبلی: با Ctrl/کلیک وسط هم رکورد ثبت می‌شد و پخش‌کننده
		 * کوچک بعدا بی‌دلیل در همین تب ظاهر می‌شد).
		 */
		if ( ! isPlainClick( e ) || ! ctaLink.href ) {
			return;
		}
		const item = playlist[ currentIndex ];
		const v = DOM[ 'hs-modal-video' ];
		if ( item ) {
			try {
				sessionStorage.setItem( 'hs_mini_player', JSON.stringify( {
					url: item.url,
					poster: item.poster,
					title: item.title,
					time: v ? v.currentTime : 0,
					ts: Date.now(),
				} ) );
			} catch { /* حافظه در دسترس نیست؛ لینک عادی باز می‌شود */ }
		}
		// ورودی تاریخچه استوری جای خودش را به صفحه محصول بدهد (وگرنه دو بار «برگشت»)
		if ( historyPushed ) {
			e.preventDefault();
			historyPushed = false;
			window.location.replace( ctaLink.href );
		}
	};

	const init = () => {
		initDOM();

		if ( ! DOM[ 'hs-video-modal' ] ) {
			return;
		}

		document.addEventListener( 'visibilitychange', () => {
			if ( document.hidden && modalOpen && ! DOM[ 'hs-modal-video' ].paused ) {
				togglePlay();
			}
		} );

		document.addEventListener( 'click', ( e ) => {
			const link = e.target.closest( SEL.storyLink );
			if ( link && link.dataset.storyPlayer !== 'off' ) {
				e.preventDefault();
				openFromLink( link );
				return;
			}

			if ( ! modalOpen ) {
				return;
			}
			if ( e.target.closest( '.hs-modal__close' ) || e.target.closest( '.hs-modal__overlay' ) ) {
				closeModal();
				return;
			}
			if ( e.target.closest( '.hs-nav--prev' ) ) {
				goPrev();
				return;
			}
			if ( e.target.closest( '.hs-nav--next' ) ) {
				goNext();
				return;
			}
			if ( e.target.closest( '.hs-play-toggle' ) ) {
				togglePlay();
				return;
			}
			if ( e.target.closest( '.hs-mute-toggle' ) ) {
				toggleMute();
				return;
			}
			if ( e.target.closest( '.hs-share-btn' ) ) {
				handleShare();
				return;
			}

			const ctaLink = e.target.closest( '.hs-cta-box' );
			if ( ctaLink ) {
				onCtaClick( e, ctaLink );
				return;
			}

			const prog = e.target.closest( '.hs-progress-item' );
			if ( prog ) {
				openStory( parseInt( prog.dataset.index, 10 ) );
				return;
			}

			const tapZone = e.target.closest( '.hs-tap-zone' );
			if ( tapZone && S.tapNavigation ) {
				if ( tapZone.dataset.tap === 'next' ) {
					goNext();
				} else if ( tapZone.dataset.tap === 'prev' ) {
					goPrev();
				} else {
					togglePlay();
				}
			}
		} );

		const v = DOM[ 'hs-modal-video' ];
		v.addEventListener( 'playing', () => {
			DOM[ '.hs-player' ].classList.remove( 'is-loading' );
			DOM[ 'hs-video-modal' ].classList.remove( 'is-paused' );
			DOM[ 'hs-video-modal' ].classList.add( 'is-playing' );

			cancelAnimationFrame( rafId );
			loopProgress();

			const item = playlist[ currentIndex ];
			if ( item && item.storyId && ! item.trackedThisSession ) {
				item.trackedThisSession = true;
				trackView( item );
			}
		} );

		v.addEventListener( 'pause', () => {
			cancelAnimationFrame( rafId );
			DOM[ 'hs-video-modal' ].classList.remove( 'is-playing' );
			DOM[ 'hs-video-modal' ].classList.add( 'is-paused' );
		} );

		v.addEventListener( 'volumechange', syncMute );
		v.addEventListener( 'waiting', () => DOM[ '.hs-player' ].classList.add( 'is-loading' ) );
		v.addEventListener( 'ended', () => {
			cancelAnimationFrame( rafId );
			if ( S.autoplayNext ) {
				goNext();
			} else {
				updateProgress();
			}
		} );
		v.addEventListener( 'timeupdate', updateProgress );

		if ( S.swipeNavigation && DOM[ '.hs-stage' ] ) {
			DOM[ '.hs-stage' ].addEventListener( 'touchstart', ( e ) => {
				touchX = e.changedTouches[ 0 ].clientX;
				touchY = e.changedTouches[ 0 ].clientY;
			}, { passive: true } );

			DOM[ '.hs-stage' ].addEventListener( 'touchend', ( e ) => {
				const dx = e.changedTouches[ 0 ].clientX - touchX;
				const dy = e.changedTouches[ 0 ].clientY - touchY;
				if ( Math.abs( dx ) > 50 && Math.abs( dy ) < 40 ) {
					if ( dx > 0 ) {
						goNext();
					} else {
						goPrev();
					}
				}
			}, { passive: true } );
		}

		window.addEventListener( 'popstate', () => {
			if ( modalOpen ) {
				closeModal( true );
			}
		} );

		document.addEventListener( 'keydown', ( e ) => {
			if ( ! modalOpen ) {
				return;
			}
			if ( e.key === 'Escape' ) {
				closeModal();
				return;
			}
			// صفحه‌کلید همان جهت ناحیه‌های لمسی (RTL: چپ = بعدی)
			if ( e.key === 'ArrowLeft' ) {
				if ( isRTL() ) {
					goNext();
				} else {
					goPrev();
				}
			}
			if ( e.key === 'ArrowRight' ) {
				if ( isRTL() ) {
					goPrev();
				} else {
					goNext();
				}
			}
			/*
			 * فاصله = پخش/توقف، مگر تمرکز روی دکمه یا لینک باشد (باگ قبلی:
			 * فاصله همیشه پخش را عوض می‌کرد و دکمه‌ها با فاصله فعال نمی‌شدند).
			 */
			if ( e.key === ' ' && ! e.target.closest( 'button, a, input, textarea, select, [contenteditable]' ) ) {
				e.preventDefault();
				togglePlay();
			}
			trapFocus( e );
		} );

		openFromHash();
		window.addEventListener( 'hashchange', openFromHash );
	};

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
