/**
 * Video Watch — پلیر صفحه ویدیو (Hodima Media)
 *
 * زیر ESLint بدون baseline (HODIMA-AUDIT.md بخش ۲۱۸). نشانه‌گذاری: template-video.php.
 *
 *   - پرش با ?t= : اسکیمای صفحه (SeekToAction و Clip) به گوگل اعلام می‌کند که
 *     ?t=120 از ثانیه ۱۲۰ پخش می‌کند؛ لینک‌های «لحظه‌های کلیدی» همین را می‌زنند.
 *   - بازدید فقط با تماشای واقعی (countWatched؛ بخش ۲۱۶).
 *   - صفحه‌کلید: Space/K پخش، F تمام‌صفحه، M صدا، P تصویر در تصویر، ←/→ پنج ثانیه،
 *     Home/End. روی دکمه یا لینک، Space و Enter کار خود همان دکمه است (تا Media
 *     2.1.0 هر Space ویدیو را پخش/متوقف می‌کرد، روی دکمه صدا و منوی سرعت هم).
 *   - کنترل‌ها هنگام پخش بعد از ۲.۵ ثانیه محو می‌شوند، مگر فوکوس صفحه‌کلید یا منوی
 *     باز داخلشان باشد (تا 2.1.0 دکمه فوکوس‌دار نامرئی می‌شد؛ WCAG 2.4.7).
 *   - منوها (سرعت، بیشتر، اشتراک): aria-expanded، Esc می‌بندد و فوکوس برمی‌گردد،
 *     ↑/↓ بین گزینه‌ها.
 *   - نوار پیشرفت با کشیدن (موس و لمس)، نه فقط کلیک.
 *   - نشانگر بارگذاری و پیام خطا؛ با خطا، دکمه پخش دوباره فایل را بار می‌کند.
 *   - راه‌اندازی مستقل از DOMContentLoaded (سازگار با Delay JS لایت‌اسپید).
 *   - دکمه «متن» نوار زیر پلیر، باکس متن کامل را باز/بسته می‌کند (initTranscripts؛
 *     مستقل از پلیر، ویدیوی بدون فایل هم دارد؛ بخش ۲۲۲).
 */
( () => {
	'use strict';

	const SKIP = 5;
	const IDLE_MS = 2500;
	const LOADER_DELAY_MS = 300;
	const VIEW_RATIO = 0.15;
	const VIEW_MAX_SECONDS = 30;
	const ICON_PLAY = 'M8 5v14l11-7z';
	const ICON_PAUSE = 'M6 19h4V5H6v14zm8-14v14h4V5h-4z';
	const ICON_SOUND = '<polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><path d="M19.07 4.93a10 10 0 0 1 0 14.14M15.54 8.46a5 5 0 0 1 0 7.07"></path>';
	const ICON_MUTED = '<polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><line x1="23" y1="9" x2="17" y2="15"></line><line x1="17" y1="9" x2="23" y2="15"></line>';

	const formatTime = ( t ) => {
		if ( ! Number.isFinite( t ) ) {
			return '0:00';
		}
		const h = Math.floor( t / 3600 );
		const m = Math.floor( ( t % 3600 ) / 60 );
		const ss = String( Math.floor( t % 60 ) ).padStart( 2, '0' );
		return h > 0 ? `${ h }:${ String( m ).padStart( 2, '0' ) }:${ ss }` : `${ m }:${ ss }`;
	};

	/** ?t=90 ، ?t=1:30 ، ?t=1m30s ، #t=90 → ثانیه */
	const startTimeFromUrl = () => {
		let raw;
		try {
			raw = new URLSearchParams( location.search ).get( 't' );
		} catch {
			raw = null;
		}
		if ( ! raw && /(^|[#&])t=/.test( location.hash ) ) {
			raw = location.hash.replace( /^.*[#&]t=([^&]*).*$/, '$1' );
		}
		if ( ! raw ) {
			return 0;
		}

		raw = String( raw ).trim();
		if ( /^\d+(\.\d+)?$/.test( raw ) ) {
			return parseFloat( raw );
		}

		const parts = raw.split( ':' );
		if ( parts.length > 1 && parts.every( ( p ) => /^\d+$/.test( p ) ) ) {
			return parts.reduce( ( acc, p ) => ( acc * 60 ) + parseInt( p, 10 ), 0 );
		}

		const m = raw.match( /^(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s)?$/i );
		return m ? ( parseInt( m[ 1 ] || 0, 10 ) * 3600 ) + ( parseInt( m[ 2 ] || 0, 10 ) * 60 ) + parseInt( m[ 3 ] || 0, 10 ) : 0;
	};

	/** فوکوس صفحه‌کلید (نه کلیک موس) داخل el است؟ */
	const hasKeyboardFocus = ( el ) => {
		try {
			return !! el.querySelector( ':focus-visible' );
		} catch {
			return el.contains( document.activeElement );
		}
	};

	const swallow = () => {};

	class HodVideoPlayer {
		constructor( container ) {
			this.container = container;
			this.video = container.querySelector( '.hs-main-video' );
			if ( ! this.video ) {
				return;
			}

			const q = ( s ) => container.querySelector( s );

			this.videoId = this.video.getAttribute( 'data-id' );
			this.videoTitle = this.video.getAttribute( 'data-title' );
			this.poster = this.video.getAttribute( 'poster' );

			this.centerBtn = q( '.hs-play-toggle' );
			this.smallPlayBtn = q( '.hs-play-pause-small' );
			this.playBtns = container.querySelectorAll( '.hs-play-btn, .hs-play-pause-small' );
			this.playIconPaths = container.querySelectorAll( '.hs-play-icon-path' );
			this.progressWrap = q( '.hs-progress-wrap' );
			this.progressBar = q( '.hs-progress-fill' );
			this.currentTimeEl = q( '.hs-current-time' );
			this.durationEl = q( '.hs-duration' );
			this.muteBtn = q( '.hs-mute-btn' );
			this.volumeSlider = q( '.hs-volume-slider' );
			this.speedBtn = q( '.hs-speed-btn' );
			this.speedMenu = q( '.hs-speed-menu' );
			this.speedOptions = container.querySelectorAll( '.hs-speed-menu .hs-menu-option' );
			this.fullscreenBtn = q( '.hs-fullscreen-btn' );
			this.pipBtn = q( '.hs-pip-btn' );
			this.moreBtn = q( '.hs-more-options-btn' );
			this.moreMenu = q( '.hs-more-options-container .hs-more-menu:not(.hs-speed-menu)' );
			this.messageEl = q( '.hs-player-message' );

			// دکمه‌های اشتراک و شمارنده در نوار زیر پلیرند (خواهر .hs-player، نه فرزند)
			const scope = container.closest( '.hs-video-col' ) || container.parentElement || container;
			this.shareBtn = scope.querySelector( '.hs-share-btn' );
			this.shareMenu = scope.querySelector( '.hs-share-menu' );
			this.shareStatus = scope.querySelector( '.hs-share-status' );
			this.copyLinkBtn = scope.querySelector( '.hs-copy-link' );
			this.viewCounterEl = scope.querySelector( '.hs-live-view-count' );

			this.menus = [
				[ this.speedBtn, this.speedMenu ],
				[ this.moreBtn, this.moreMenu ],
				[ this.shareBtn, this.shareMenu ],
			].filter( ( [ btn, menu ] ) => btn && menu );

			this.viewTracked = false;
			this.watched = 0; // ثانیه‌های واقعا پخش‌شده (بدون پرش)
			this.lastPos = null; // جای پخش در timeupdate قبلی
			this.lastVolume = 1;
			this.idleTimer = null;
			this.loaderTimer = null;
			this.ignoreNextClick = false;
			this.dragging = false;
			this.pendingStart = startTimeFromUrl();

			this.bindEvents();
			this.bindMenus();
			this.setupMediaSession();
			this.setupKeyboard();
			this.syncPlayState( false );
			// خطای فایل پیش از اجرای این اسکریپت (مثلا با «تأخیر JS» لایت‌اسپید): رویداد error از دست رفته
			if ( this.video.error ) {
				this.showError();
			}
			this.applyStartTime();
		}

		/* ── پرش به ?t= ─────────────────────────────────────────────── */

		applyStartTime() {
			const t = this.pendingStart;
			if ( ! ( t > 0 ) ) {
				return;
			}

			const seek = () => {
				if ( ! Number.isFinite( this.video.duration ) || t >= this.video.duration ) {
					return;
				}
				this.video.currentTime = t;
				this.updateProgress();
			};

			if ( this.video.readyState >= 1 ) {
				seek();
			} else {
				// با preload="metadata" اطلاعات فایل معمولا بدون کلیک هم بارگذاری می‌شود
				this.video.addEventListener( 'loadedmetadata', seek, { once: true } );
			}

			// کاربر مستقیم از نتیجه گوگل آمده: پلیر در دید باشد
			requestAnimationFrame( () => this.container.scrollIntoView( { block: 'center', behavior: 'auto' } ) );
		}

		/* ── رویدادها ───────────────────────────────────────────────── */

		bindEvents() {
			const v = this.video;
			const c = this.container;

			c.addEventListener( 'mousemove', () => this.showControls() );
			c.addEventListener( 'touchstart', () => this.showControls(), { passive: true } );
			c.addEventListener( 'click', () => this.showControls() );
			c.addEventListener( 'focusin', () => this.showControls() );
			c.addEventListener( 'mouseleave', () => {
				if ( ! v.paused ) {
					this.hideControls();
				}
			} );

			v.addEventListener( 'play', () => this.onPlay() );
			v.addEventListener( 'pause', () => this.onPause() );
			v.addEventListener( 'loadedmetadata', () => {
				if ( this.durationEl ) {
					this.durationEl.textContent = formatTime( v.duration );
				}
				this.updateProgress();
			} );
			v.addEventListener( 'timeupdate', () => this.onTimeUpdate() );
			v.addEventListener( 'seeking', () => {
				this.lastPos = null;
			} );
			v.addEventListener( 'volumechange', () => this.syncMuteState() );
			v.addEventListener( 'click', ( e ) => {
				if ( this.ignoreNextClick ) {
					e.preventDefault();
					return;
				}
				this.togglePlayState();
			} );

			// بارگذاری: نشانگر فقط اگر انتظار بیش از LOADER_DELAY_MS طول بکشد (بدون چشمک)
			v.addEventListener( 'waiting', () => this.setLoading( true ) );
			[ 'playing', 'canplay', 'pause', 'error', 'emptied' ].forEach( ( ev ) => v.addEventListener( ev, () => this.setLoading( false ) ) );
			v.addEventListener( 'error', () => this.showError() );

			this.playBtns.forEach( ( btn ) => btn.addEventListener( 'click', () => this.togglePlayState() ) );

			this.bindProgress();

			if ( this.muteBtn ) {
				this.muteBtn.addEventListener( 'click', ( e ) => {
					e.stopPropagation();
					this.toggleMute();
				} );
			}
			if ( this.volumeSlider ) {
				this.volumeSlider.addEventListener( 'input', ( e ) => {
					const vol = parseFloat( e.target.value );
					v.volume = vol;
					v.muted = vol === 0;
					if ( vol > 0 ) {
						this.lastVolume = vol;
					}
				} );
			}

			this.speedOptions.forEach( ( option ) => {
				option.addEventListener( 'click', () => {
					const speed = parseFloat( option.getAttribute( 'data-speed' ) );
					v.playbackRate = speed;
					if ( this.speedBtn ) {
						this.speedBtn.textContent = `${ speed }x`;
						// نام دکمه متن دیدنی را در خود دارد (WCAG 2.5.3)
						this.speedBtn.setAttribute( 'aria-label', `${ speed }x سرعت پخش` );
					}
					this.speedOptions.forEach( ( o ) => {
						o.classList.toggle( 'is-active', o === option );
						o.setAttribute( 'aria-pressed', o === option ? 'true' : 'false' );
					} );
					this.closeMenus( this.speedBtn );
				} );
			} );

			if ( this.shareBtn ) {
				this.shareBtn.addEventListener( 'click', ( e ) => {
					e.stopPropagation();
					if ( navigator.share ) {
						navigator.share( { title: document.title, url: this.shareUrl() } )
							.then( () => this.trackInteraction( 'shares' ) )
							.catch( swallow );
					} else if ( this.shareMenu ) {
						this.toggleMenu( this.shareBtn, this.shareMenu, e.detail === 0 );
					}
				} );
			}

			if ( this.copyLinkBtn ) {
				this.copyLinkBtn.addEventListener( 'click', ( e ) => {
					e.preventDefault();
					this.copyText( this.shareUrl() ).then( () => {
						const original = this.copyLinkBtn.textContent;
						const done = this.copyLinkBtn.getAttribute( 'data-label-done' ) || '';
						this.copyLinkBtn.textContent = `✓ ${ done }`.trim();
						if ( this.shareStatus ) {
							this.shareStatus.textContent = done;
						}
						this.trackInteraction( 'shares' );
						setTimeout( () => {
							this.copyLinkBtn.textContent = original;
							if ( this.shareStatus ) {
								this.shareStatus.textContent = '';
							}
							this.closeMenus( this.shareMenu?.contains( document.activeElement ) ? this.shareBtn : null );
						}, 2000 );
					} ).catch( swallow );
				} );
			}

			if ( this.fullscreenBtn ) {
				this.fullscreenBtn.addEventListener( 'click', ( e ) => {
					e.stopPropagation();
					this.toggleFullscreen();
				} );
			}
			const onFullscreenChange = () => this.syncFullscreenState();
			document.addEventListener( 'fullscreenchange', onFullscreenChange );
			document.addEventListener( 'webkitfullscreenchange', onFullscreenChange );

			if ( this.pipBtn && document.pictureInPictureEnabled ) {
				this.pipBtn.addEventListener( 'click', ( e ) => {
					e.stopPropagation();
					( document.pictureInPictureElement !== v ? v.requestPictureInPicture() : document.exitPictureInPicture() ).catch( swallow );
				} );
			} else if ( this.pipBtn ) {
				this.pipBtn.hidden = true;
			}
		}

		/** نوار پیشرفت: کلیک و کشیدن (pointer events؛ موس، لمس و قلم). */
		bindProgress() {
			const wrap = this.progressWrap;
			if ( ! wrap ) {
				return;
			}

			const end = ( e ) => {
				if ( ! this.dragging ) {
					return;
				}
				this.dragging = false;
				this.container.classList.remove( 'hs-is-scrubbing' );
				try {
					wrap.releasePointerCapture( e.pointerId );
				} catch {
					// اشاره‌گر از قبل آزاد شده
				}
				this.showControls();
			};

			wrap.addEventListener( 'pointerdown', ( e ) => {
				if ( e.button !== 0 || ! Number.isFinite( this.video.duration ) ) {
					return;
				}
				e.preventDefault();
				this.dragging = true;
				this.container.classList.add( 'hs-is-scrubbing' );
				try {
					wrap.setPointerCapture( e.pointerId );
				} catch {
					// بدون capture هم pointermove روی خود نوار کار می‌کند
				}
				this.seekToPointer( e );
			} );
			wrap.addEventListener( 'pointermove', ( e ) => {
				if ( this.dragging ) {
					this.seekToPointer( e );
				}
			} );
			wrap.addEventListener( 'pointerup', end );
			wrap.addEventListener( 'pointercancel', end );
			wrap.addEventListener( 'lostpointercapture', end );
		}

		seekToPointer( e ) {
			const v = this.video;
			const rect = this.progressWrap.getBoundingClientRect();
			// نوار LTR است (مثل همه پلیرها) حتی در صفحه راست‌به‌چپ
			const pct = Math.min( Math.max( ( e.clientX - rect.left ) / rect.width, 0 ), 1 );
			if ( Number.isFinite( v.duration ) ) {
				v.currentTime = pct * v.duration;
				this.updateProgress();
				this.showControls();
			}
		}

		/* ── منوها ──────────────────────────────────────────────────── */

		bindMenus() {
			this.menus.forEach( ( [ btn, menu ] ) => {
				if ( btn === this.shareBtn ) {
					return; // دکمه اشتراک خودش تصمیم می‌گیرد (اشتراک‌گذاری سیستم یا منو)
				}
				btn.addEventListener( 'click', ( e ) => {
					e.stopPropagation();
					// e.detail === 0: کلیک از صفحه‌کلید (Enter/Space) ← فوکوس به گزینه فعال یا اول
					this.toggleMenu( btn, menu, e.detail === 0 );
				} );
			} );

			// کلیک بیرون منو می‌بندد؛ دکمه «متن» هم بیرون حساب می‌شود (تا 2.1.4 همه جعبه‌های نوار مستثنا بودند)
			document.addEventListener( 'click', ( e ) => {
				if ( ! e.target.closest( '.hs-more-options-container' ) && ! e.target.closest( '.hs-action-share' ) ) {
					this.closeMenus();
				}
			} );

			// مرحله capture: پیش از کلیدهای خود پلیر (فلش‌ها داخل منو پرش نکنند)
			document.addEventListener( 'keydown', ( e ) => {
				const open = this.menus.find( ( [ , menu ] ) => menu.classList.contains( 'is-open' ) );
				if ( ! open ) {
					return;
				}
				const [ btn, menu ] = open;
				if ( e.key === 'Escape' ) {
					e.preventDefault();
					this.closeMenus( btn );
					return;
				}
				if ( menu.contains( document.activeElement ) && [ 'ArrowDown', 'ArrowUp', 'Home', 'End' ].includes( e.key ) ) {
					e.preventDefault();
					e.stopPropagation();
					const items = [ ...menu.querySelectorAll( 'button, a[href]' ) ];
					const i = items.indexOf( document.activeElement );
					const next = {
						ArrowDown: ( i + 1 ) % items.length,
						ArrowUp: ( i - 1 + items.length ) % items.length,
						Home: 0,
						End: items.length - 1,
					}[ e.key ];
					items[ next ]?.focus();
				}
			}, true );

			// فوکوس از منو و دکمه‌اش بیرون رفت (Tab) ← بستن بی‌صدا
			this.menus.forEach( ( [ btn, menu ] ) => {
				const onFocusOut = ( e ) => {
					const to = e.relatedTarget;
					if ( menu.classList.contains( 'is-open' ) && to && ! menu.contains( to ) && to !== btn ) {
						this.setMenuOpen( btn, menu, false );
					}
				};
				menu.addEventListener( 'focusout', onFocusOut );
				btn.addEventListener( 'focusout', onFocusOut );
			} );
		}

		setMenuOpen( btn, menu, open ) {
			menu.classList.toggle( 'is-open', open );
			btn.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		}

		toggleMenu( btn, menu, focusFirst ) {
			const open = menu.classList.contains( 'is-open' );
			this.closeMenus();
			if ( open ) {
				return;
			}
			this.setMenuOpen( btn, menu, true );
			this.showControls();
			if ( focusFirst ) {
				( menu.querySelector( '.is-active' ) || menu.querySelector( 'button, a[href]' ) )?.focus();
			}
		}

		/** همه منوها را می‌بندد؛ اگر returnTo داده شود فوکوس به آن برمی‌گردد. */
		closeMenus( returnTo = null ) {
			this.menus.forEach( ( [ btn, menu ] ) => this.setMenuOpen( btn, menu, false ) );
			returnTo?.focus();
		}

		menuOpen() {
			return this.menus.some( ( [ , menu ] ) => menu.classList.contains( 'is-open' ) );
		}

		/* ── اشتراک و تمام‌صفحه ─────────────────────────────────────── */

		/** لینک اشتراک بدون ?t= (پرش کاربر قبلی به گیرنده منتقل نشود) */
		shareUrl() {
			try {
				const u = new URL( location.href );
				u.searchParams.delete( 't' );
				u.hash = '';
				return u.toString();
			} catch {
				return location.href;
			}
		}

		copyText( text ) {
			if ( navigator.clipboard && window.isSecureContext ) {
				return navigator.clipboard.writeText( text );
			}
			// اتصال غیرامن یا مرورگر قدیمی
			return new Promise( ( resolve, reject ) => {
				const ta = document.createElement( 'textarea' );
				ta.value = text;
				ta.setAttribute( 'readonly', '' );
				ta.style.position = 'fixed';
				ta.style.opacity = '0';
				document.body.appendChild( ta );
				ta.select();
				try {
					if ( document.execCommand( 'copy' ) ) {
						resolve();
					} else {
						reject( new Error( 'copy' ) );
					}
				} catch ( err ) {
					reject( err );
				}
				ta.remove();
			} );
		}

		isFullscreen() {
			return !! ( document.fullscreenElement || document.webkitFullscreenElement );
		}

		toggleFullscreen() {
			const c = this.container;
			if ( ! this.isFullscreen() ) {
				const req = c.requestFullscreen || c.webkitRequestFullscreen;
				if ( req ) {
					Promise.resolve( req.call( c ) ).then( () => {
						screen.orientation?.lock?.( 'landscape' )?.catch( swallow );
					} ).catch( swallow );
				} else if ( this.video.webkitEnterFullscreen ) {
					// Safari آیفون فقط تمام‌صفحه خود عنصر ویدیو را پشتیبانی می‌کند
					this.video.webkitEnterFullscreen();
				}
			} else {
				const exit = document.exitFullscreen || document.webkitExitFullscreen;
				if ( exit ) {
					Promise.resolve( exit.call( document ) ).catch( swallow );
				}
			}
		}

		/** نام دکمه و قفل جهت صفحه؛ خروج با Esc هم از همین‌جا (تا 2.1.0 قفل افقی می‌ماند). */
		syncFullscreenState() {
			const on = this.isFullscreen();
			if ( this.fullscreenBtn ) {
				const label = this.fullscreenBtn.getAttribute( on ? 'data-label-exit' : 'data-label-enter' );
				if ( label ) {
					this.fullscreenBtn.setAttribute( 'aria-label', label );
				}
			}
			if ( ! on ) {
				try {
					screen.orientation?.unlock?.();
				} catch {
					// مرورگر قفل جهت را پشتیبانی نمی‌کند
				}
			}
		}

		/* ── Media Session ──────────────────────────────────────────── */

		setupMediaSession() {
			if ( ! ( 'mediaSession' in navigator ) ) {
				return;
			}

			const v = this.video;
			const ext = ( ( this.poster || '' ).split( '.' ).pop() || '' ).split( /[?#]/ )[ 0 ].toLowerCase();
			const mime = { jpg: 'image/jpeg', jpeg: 'image/jpeg', png: 'image/png', webp: 'image/webp', gif: 'image/gif', avif: 'image/avif' }[ ext ] || 'image/jpeg';

			try {
				navigator.mediaSession.metadata = new MediaMetadata( {
					title: this.videoTitle || document.title,
					artwork: this.poster ? [ { src: this.poster, type: mime } ] : [],
				} );
			} catch {
				return;
			}

			const set = ( action, fn ) => {
				try {
					navigator.mediaSession.setActionHandler( action, fn );
				} catch {
					// مرورگر این کار را نمی‌شناسد
				}
			};

			set( 'play', () => v.play().catch( swallow ) );
			set( 'pause', () => v.pause() );
			set( 'seekbackward', ( d ) => {
				v.currentTime = Math.max( v.currentTime - ( d.seekOffset || 10 ), 0 );
			} );
			set( 'seekforward', ( d ) => {
				if ( Number.isFinite( v.duration ) ) {
					v.currentTime = Math.min( v.currentTime + ( d.seekOffset || 10 ), v.duration );
				}
			} );
			set( 'seekto', ( d ) => {
				if ( d.fastSeek && 'fastSeek' in v ) {
					v.fastSeek( d.seekTime );
				} else {
					v.currentTime = d.seekTime;
				}
			} );
		}

		/* ── صفحه‌کلید ──────────────────────────────────────────────── */

		setupKeyboard() {
			const v = this.video;

			this.container.addEventListener( 'keydown', ( e ) => {
				const t = e.target;
				if ( e.altKey || e.ctrlKey || e.metaKey || t.closest( 'input, textarea, select' ) ) {
					return;
				}
				const onControl = !! t.closest( 'button, a[href]' );
				const inMenu = !! t.closest( '.hs-more-menu' );
				const seekable = Number.isFinite( v.duration );

				switch ( e.key ) {
					case ' ':
						if ( onControl ) {
							return; // Space کار خود دکمه است
						}
						e.preventDefault();
						this.togglePlayState();
						break;
					case 'k':
					case 'K':
						e.preventDefault();
						this.togglePlayState();
						break;
					case 'f':
					case 'F':
						e.preventDefault();
						this.toggleFullscreen();
						break;
					case 'm':
					case 'M':
						e.preventDefault();
						this.toggleMute();
						break;
					case 'p':
					case 'P':
						e.preventDefault();
						if ( this.pipBtn && ! this.pipBtn.hidden ) {
							this.pipBtn.click();
						}
						break;
					// نوار پیشرفت LTR است (مثل همه پلیرها)؛ فلش راست = جلو
					case 'ArrowRight':
					case 'ArrowLeft':
						if ( inMenu || ! seekable ) {
							return; // تا 2.1.0 پیش از بارگذاری اطلاعات فایل، ویدیو را به ثانیه صفر می‌برد
						}
						e.preventDefault();
						v.currentTime = e.key === 'ArrowRight' ? Math.min( v.currentTime + SKIP, v.duration ) : Math.max( v.currentTime - SKIP, 0 );
						this.showControls();
						break;
					case 'Home':
					case 'End':
						if ( inMenu || ! seekable ) {
							return;
						}
						e.preventDefault();
						v.currentTime = e.key === 'Home' ? 0 : v.duration;
						break;
				}
			} );
		}

		/* ── وضعیت ──────────────────────────────────────────────────── */

		showControls() {
			if ( this.container.classList.contains( 'hs-controls-hidden' ) ) {
				this.container.classList.remove( 'hs-controls-hidden' );
				this.ignoreNextClick = true;
				setTimeout( () => {
					this.ignoreNextClick = false;
				}, 400 );
			}
			clearTimeout( this.idleTimer );
			if ( ! this.video.paused ) {
				this.idleTimer = setTimeout( () => this.hideControls(), IDLE_MS );
			}
		}

		hideControls() {
			if ( this.video.paused || this.dragging ) {
				return;
			}
			// فوکوس صفحه‌کلید یا منوی باز: کنترل‌ها می‌مانند و دوباره بررسی می‌شود
			if ( hasKeyboardFocus( this.container ) || this.menuOpen() ) {
				clearTimeout( this.idleTimer );
				this.idleTimer = setTimeout( () => this.hideControls(), IDLE_MS );
				return;
			}
			this.container.classList.add( 'hs-controls-hidden' );
			this.closeMenus();
		}

		onPlay() {
			this.syncPlayState( true );
			this.hideError();
			this.showControls();
		}

		onPause() {
			this.lastPos = null;
			this.syncPlayState( false );
			this.container.classList.remove( 'hs-controls-hidden' );
			clearTimeout( this.idleTimer );
		}

		setLoading( on ) {
			clearTimeout( this.loaderTimer );
			if ( on ) {
				this.loaderTimer = setTimeout( () => this.container.classList.add( 'hs-is-loading' ), LOADER_DELAY_MS );
			} else {
				this.container.classList.remove( 'hs-is-loading' );
			}
		}

		showError() {
			if ( ! this.video.error ) {
				return;
			}
			const hadFocus = document.activeElement === this.centerBtn;
			this.container.classList.add( 'hs-has-error' );
			if ( hadFocus ) {
				this.smallPlayBtn?.focus(); // دکمه وسط در حالت خطا پنهان است
			}
			if ( this.messageEl ) {
				this.messageEl.textContent = this.messageEl.getAttribute( 'data-error' ) || '';
				this.messageEl.classList.add( 'is-visible' );
			}
			this.syncPlayState( false );
		}

		hideError() {
			this.container.classList.remove( 'hs-has-error' );
			if ( this.messageEl ) {
				this.messageEl.textContent = '';
				this.messageEl.classList.remove( 'is-visible' );
			}
		}

		updateProgress() {
			const v = this.video;
			if ( ! Number.isFinite( v.duration ) || ! v.duration ) {
				return;
			}
			if ( this.progressBar ) {
				this.progressBar.style.width = `${ ( v.currentTime / v.duration ) * 100 }%`;
			}
			if ( this.currentTimeEl ) {
				this.currentTimeEl.textContent = formatTime( v.currentTime );
			}
			if ( this.progressWrap ) {
				this.progressWrap.setAttribute( 'aria-valuemax', String( Math.floor( v.duration ) ) );
				this.progressWrap.setAttribute( 'aria-valuenow', String( Math.floor( v.currentTime ) ) );
				this.progressWrap.setAttribute( 'aria-valuetext', `${ formatTime( v.currentTime ) } / ${ formatTime( v.duration ) }` );
			}
		}

		onTimeUpdate() {
			const v = this.video;
			if ( ! v.duration ) {
				return;
			}

			requestAnimationFrame( () => this.updateProgress() );

			if ( 'mediaSession' in navigator && navigator.mediaSession.setPositionState && Number.isFinite( v.duration ) ) {
				try {
					navigator.mediaSession.setPositionState( { duration: v.duration, playbackRate: v.playbackRate, position: v.currentTime } );
				} catch {
					// مقدار نامعتبر لحظه‌ای (مثلا وسط پرش)
				}
			}

			this.countWatched();

			if ( ! this.viewTracked && this.watched >= Math.min( v.duration * VIEW_RATIO, VIEW_MAX_SECONDS ) ) {
				this.viewTracked = true;
				this.trackInteraction( 'views' );
			}
		}

		/** فقط پیشروی عادی پخش جمع می‌شود؛ پرش (نوار، ?t=، فلش‌ها) جهش بزرگ است و حساب نمی‌شود. */
		countWatched() {
			const v = this.video;
			if ( v.paused || v.seeking ) {
				this.lastPos = null;
				return;
			}
			const now = v.currentTime;
			if ( this.lastPos !== null ) {
				const step = now - this.lastPos;
				if ( step > 0 && step < 2 * Math.max( 1, v.playbackRate ) ) {
					this.watched += step;
				}
			}
			this.lastPos = now;
		}

		togglePlayState() {
			const v = this.video;
			if ( v.error ) {
				// بعد از خطا: یک بار دیگر از اول بارگذاری
				this.hideError();
				v.load();
			}
			if ( v.paused ) {
				v.play().catch( swallow );
			} else {
				v.pause();
			}
		}

		syncPlayState( isPlaying ) {
			this.playBtns.forEach( ( btn ) => {
				const label = btn.getAttribute( isPlaying ? 'data-label-pause' : 'data-label-play' );
				if ( label ) {
					btn.setAttribute( 'aria-label', label );
				}
			} );
			this.playIconPaths.forEach( ( path ) => path.setAttribute( 'd', isPlaying ? ICON_PAUSE : ICON_PLAY ) );

			// دکمه بزرگ وسط هنگام پخش نامرئی است: از ترتیب Tab و صفحه‌خوان بیرون (تا 2.1.0 نامرئی فوکوس می‌گرفت)
			if ( this.centerBtn ) {
				const hadFocus = document.activeElement === this.centerBtn;
				this.centerBtn.classList.toggle( 'is-hidden', isPlaying );
				this.centerBtn.tabIndex = isPlaying ? -1 : 0;
				if ( isPlaying ) {
					this.centerBtn.setAttribute( 'aria-hidden', 'true' );
				} else {
					this.centerBtn.removeAttribute( 'aria-hidden' );
				}
				if ( isPlaying && hadFocus ) {
					this.smallPlayBtn?.focus();
				}
			}
		}

		toggleMute() {
			const v = this.video;
			if ( v.muted || v.volume === 0 ) {
				v.muted = false;
				// صدا صفر بود (نوار تا آخر پایین): «وصل صدا» باید واقعا صدا بدهد (تا 2.1.0 بی‌صدا می‌ماند)
				if ( v.volume === 0 ) {
					v.volume = this.lastVolume > 0 ? this.lastVolume : 1;
				}
			} else {
				if ( v.volume > 0 ) {
					this.lastVolume = v.volume;
				}
				v.muted = true;
			}
		}

		/** آیکون، نام دکمه صدا و نوار صدا از وضعیت واقعی ویدیو (رویداد volumechange). */
		syncMuteState() {
			const v = this.video;
			const muted = v.muted || v.volume === 0;
			if ( this.muteBtn ) {
				this.muteBtn.innerHTML = `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">${ muted ? ICON_MUTED : ICON_SOUND }</svg>`;
				const label = this.muteBtn.getAttribute( muted ? 'data-label-unmute' : 'data-label-mute' );
				if ( label ) {
					this.muteBtn.setAttribute( 'aria-label', label );
				}
			}
			if ( this.volumeSlider ) {
				const value = muted ? 0 : v.volume;
				this.volumeSlider.value = String( value );
				const pct = value * 100;
				this.volumeSlider.style.background = `linear-gradient(to right, var(--hs-primary) 0%, var(--hs-primary) ${ pct }%, rgba(255,255,255,0.3) ${ pct }%, rgba(255,255,255,0.3) 100%)`;
			}
		}

		/* ── آمار ───────────────────────────────────────────────────── */

		trackInteraction( actionType, isRetry = false ) {
			const data = window.hvwData;
			if ( ! this.videoId || ! data ) {
				return;
			}

			fetch( data.restUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json', 'X-HVW-Nonce': data.nonce },
				body: JSON.stringify( { id: this.videoId, action: actionType } ),
			} )
				.then( ( res ) => {
					// صفحه از کش سرو شده و کلید منقضی است: یک بار کلید تازه و تکرار
					if ( res.status === 403 && ! isRetry && data.nonceUrl ) {
						return fetch( data.nonceUrl, { cache: 'no-store', credentials: 'same-origin' } )
							.then( ( r ) => r.json() )
							.then( ( fresh ) => {
								if ( fresh?.nonce ) {
									data.nonce = fresh.nonce;
									this.trackInteraction( actionType, true );
								}
								return null;
							} );
					}
					return res.ok ? res.json() : null;
				} )
				.then( ( json ) => {
					if ( json?.success && actionType === 'views' && this.viewCounterEl && typeof json.views === 'number' ) {
						this.viewCounterEl.textContent = new Intl.NumberFormat( 'fa-IR' ).format( json.views );
					}
				} )
				.catch( swallow );
		}
	}

	/**
	 * باکس متن کامل ویدیو (template-video.php).
	 * بسته = hidden="until-found" (جستجوی مرورگر بازش می‌کند و رویداد beforematch
	 * می‌دهد)؛ باز کردن فوکوس را به تیتر باکس می‌برد، «بستن متن» و Esc آن را به دکمه برمی‌گرداند.
	 */
	const initTranscripts = () => {
		const reduceMotion = window.matchMedia?.( '(prefers-reduced-motion: reduce)' ).matches;
		document.querySelectorAll( '.hs-transcript-btn[aria-controls]' ).forEach( ( btn ) => {
			const panel = document.getElementById( btn.getAttribute( 'aria-controls' ) );
			if ( ! panel || btn.dataset.hvwReady ) {
				return;
			}
			btn.dataset.hvwReady = '1';
			const title = panel.querySelector( '.hs-transcript-title' );

			const close = ( returnFocus ) => {
				panel.setAttribute( 'hidden', 'until-found' );
				btn.setAttribute( 'aria-expanded', 'false' );
				if ( returnFocus ) {
					btn.focus();
				}
			};
			const open = () => {
				panel.removeAttribute( 'hidden' );
				btn.setAttribute( 'aria-expanded', 'true' );
				title?.focus( { preventScroll: true } );
				panel.scrollIntoView( { block: 'nearest', behavior: reduceMotion ? 'auto' : 'smooth' } );
			};

			btn.addEventListener( 'click', () => ( panel.hasAttribute( 'hidden' ) ? open() : close( false ) ) );
			panel.querySelector( '.hs-transcript-close' )?.addEventListener( 'click', () => close( true ) );
			panel.addEventListener( 'keydown', ( e ) => {
				if ( e.key === 'Escape' ) {
					e.preventDefault();
					close( true );
				}
			} );
			// جستجوی مرورگر باکس را خودش باز کرد
			panel.addEventListener( 'beforematch', () => btn.setAttribute( 'aria-expanded', 'true' ) );
		} );
	};

	const boot = () => {
		document.querySelectorAll( '[data-player-instance]' ).forEach( ( c ) => new HodVideoPlayer( c ) );
		initTranscripts();
	};

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
