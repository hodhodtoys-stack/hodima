/*
 * Hodima Core — پیش‌نمایش زنده لوگوی صفحه ورود
 * Path: plugins/hodima-core/assets/admin-login.js
 *
 * فقط نمایش است؛ ذخیره و اعتبارسنجی اصلی سمت سرور (login.php) است. بدون این
 * اسکریپت صفحه کار می‌کند (پیش‌نمایش بعد از ذخیره عوض می‌شود).
 * هیچ innerHTML ای در کار نیست: فقط src، hidden و textContent.
 */
( () => {
	'use strict';

	const preview = document.querySelector( '[data-hd-login-preview]' );
	const input = document.querySelector( '[data-hd-login-logo]' );
	const height = document.querySelector( '[data-hd-login-height]' );
	const note = document.querySelector( '[data-hd-login-note]' );

	if ( ! preview || ! input ) {
		return;
	}

	const letter = preview.querySelector( '[data-hd-login-letter]' );
	const text = preview.querySelector( '[data-hd-login-text]' );
	const valid = /^https:\/\/[^\s"'()<>\\]+\.(?:png|jpe?g|webp|svg)(?:\?[^\s"'()<>\\]*)?$/i;

	let img = preview.querySelector( '[data-hd-login-img]' );

	const ensureImg = () => {
		if ( img ) {
			return img;
		}
		img = document.createElement( 'img' );
		img.className = 'hd-login-preview__logo';
		img.alt = '';
		img.setAttribute( 'data-hd-login-img', '' );
		img.addEventListener( 'error', () => show( false, 'این آدرس باز نشد؛ حرف اول نام سایت نمایش داده می‌شود.' ) );
		preview.prepend( img );
		return img;
	};

	const show = ( logo, message ) => {
		if ( img ) {
			img.hidden = ! logo;
		}
		if ( letter ) {
			letter.hidden = logo;
		}
		if ( text ) {
			text.hidden = logo;
		}
		if ( note ) {
			note.textContent = message;
		}
	};

	const sizeImg = () => {
		if ( img && height ) {
			const px = Math.max( 24, Math.min( 120, parseInt( height.value, 10 ) || 48 ) );
			img.style.blockSize = px + 'px';
		}
	};

	const update = () => {
		const url = input.value.trim();

		if ( '' === url ) {
			show( false, 'هنوز آدرسی ننوشته‌اید؛ حرف اول نام سایت نمایش داده می‌شود.' );
			return;
		}

		if ( ! valid.test( url ) ) {
			show( false, 'آدرس باید با https شروع شود و به png، jpg، webp یا svg ختم شود.' );
			return;
		}

		const el = ensureImg();
		el.src = url;
		sizeImg();
		show( true, 'اگر آدرس باز نشود، حرف اول نام سایت جایگزین می‌شود.' );
	};

	input.addEventListener( 'input', update );
	if ( height ) {
		height.addEventListener( 'input', sizeImg );
	}
	if ( img ) {
		img.addEventListener( 'error', () => show( false, 'این آدرس باز نشد؛ حرف اول نام سایت نمایش داده می‌شود.' ) );
	}
} )();
