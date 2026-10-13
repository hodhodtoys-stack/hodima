/**
 * Hodima Stories — پخش‌کننده کوچک شناور.
 *
 * راه‌انداز درون‌خطی (hodima-StoryPlayer.php) فقط وقتی این فایل را لود می‌کند
 * که بازدیدکننده همین الان روی لینک محصول یک استوری کلیک کرده باشد؛
 * hodima-player.js پیش از رفتن به صفحه، رکورد «ادامه این ویدیو» را در
 * sessionStorage گذاشته است. اینجا یک بار خوانده و پاک می‌شود (تازه کردن
 * صفحه یا رفتن به صفحه بعد دوباره نشانش نمی‌دهد).
 */
( () => {
	'use strict';

	const STORAGE_KEY = 'hs_mini_player';
	const MAX_AGE_MS = 10 * 60 * 1000; // رکورد کهنه‌تر از ۱۰ دقیقه نادیده
	const STR = window.HS_MINI_STR || {};

	let raw;
	try {
		raw = sessionStorage.getItem( STORAGE_KEY );
		sessionStorage.removeItem( STORAGE_KEY );
	} catch {
		return; // حافظه در دسترس نیست
	}
	if ( ! raw ) {
		return;
	}

	let data;
	try {
		data = JSON.parse( raw );
	} catch {
		return;
	}
	if ( ! data || ! data.url || ( Date.now() - ( data.ts || 0 ) ) > MAX_AGE_MS ) {
		return;
	}

	function render() {
		const wrap = document.createElement( 'div' );
		wrap.className = 'hs-mini-player';

		const closeBtn = document.createElement( 'button' );
		closeBtn.type = 'button';
		closeBtn.className = 'hs-mini-player__close';
		closeBtn.setAttribute( 'aria-label', STR.close || 'بستن' );
		closeBtn.textContent = '×';

		const video = document.createElement( 'video' );
		video.className = 'hs-mini-player__video';
		video.setAttribute( 'playsinline', '' );
		video.setAttribute( 'webkit-playsinline', '' );
		if ( data.poster ) {
			video.setAttribute( 'poster', data.poster );
		}
		video.src = data.url;

		const muteIcon = document.createElement( 'span' );
		muteIcon.className = 'hs-mini-player__mute';
		muteIcon.setAttribute( 'aria-hidden', 'true' );
		muteIcon.textContent = '🔇';
		muteIcon.hidden = true;

		wrap.append( video, muteIcon, closeBtn );
		document.body.appendChild( wrap );

		if ( data.time ) {
			video.addEventListener( 'loadedmetadata', () => {
				try {
					video.currentTime = data.time;
				} catch { /* noop */ }
			}, { once: true } );
		}

		// اول با صدا (کلیک روی لینک محصول ژست کاربر بود)؛ اگر مرورگر نپذیرفت بی‌صدا با نشانه
		video.muted = false;
		video.play().catch( () => {
			video.muted = true;
			muteIcon.hidden = false;
			video.play().catch( () => { /* روی تصویر کاور می‌ماند؛ لمس پخش می‌کند */ } );
		} );

		video.addEventListener( 'click', () => {
			if ( video.paused ) {
				video.play().catch( () => {} );
			} else {
				video.muted = ! video.muted;
				muteIcon.hidden = ! video.muted;
			}
		} );

		const close = () => {
			try {
				video.pause();
			} catch { /* noop */ }
			wrap.remove();
		};

		closeBtn.addEventListener( 'click', close );
		video.addEventListener( 'ended', close );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', render );
	} else {
		render();
	}
} )();
