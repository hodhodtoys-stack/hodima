/**
 * گفتگوی سفارش در صفحه «سفارش‌ها» (JavaScript خالص؛ پنجره <dialog>، مرحله ۵).
 *
 * فقط شناسه سفارش فرستاده می‌شود و سرور مالکیت را بررسی می‌کند (legacy/chat.php).
 * پنجره استاندارد مرورگر: فوکوس داخل پنجره می‌ماند، Esc می‌بندد و فوکوس به دکمه برمی‌گردد.
 * آدرس ?chat=شناسه (از «پیام‌ها و پشتیبانی») همان گفتگو را باز می‌کند.
 */
( function () {
	'use strict';

	const data = window.hodimaPanelChat;
	const dialog = document.getElementById( 'hpn-chat' );
	if ( ! data || ! dialog || 'function' !== typeof dialog.showModal ) {
		return;
	}
	const history = document.getElementById( 'hpn-chat-history' );
	const form = document.getElementById( 'hpn-chat-form' );
	const title = document.getElementById( 'hpn-chat-title' );
	const error = document.getElementById( 'hpn-chat-error' );
	let orderId = 0;
	let opener = null;

	const post = async ( action, fields ) => {
		const body = new FormData();
		body.append( 'action', action );
		body.append( 'nonce', data.nonce );
		Object.entries( fields ).forEach( ( [ key, value ] ) => body.append( key, value ) );
		const response = await fetch( data.ajaxUrl, { method: 'POST', body, credentials: 'same-origin' } );
		if ( ! response.ok ) {
			throw new Error( String( response.status ) );
		}
		return response.json();
	};

	const note = ( text ) => {
		const p = document.createElement( 'p' );
		p.className = 'hpn-muted';
		p.textContent = text;
		history.replaceChildren( p );
	};

	// خطای ارسال داخل پنجره (role=alert)، نه پنجره alert مرورگر که گفتگو را می‌پوشاند.
	const fail = ( text ) => {
		error.textContent = text;
		error.hidden = '' === text;
	};

	const open = async ( button ) => {
		orderId = Number( button.dataset.hpnChat ) || 0;
		opener = button;
		title.textContent = button.dataset.hpnChatTitle || 'گفتگوی سفارش';
		note( 'در حال بارگذاری پیام‌ها…' );
		fail( '' );
		form.hidden = true;
		dialog.showModal();
		try {
			const res = await post( 'hodima_panel_chat_history', { order_id: orderId } );
			if ( ! res.success ) {
				note( ( res.data && res.data.message ) || 'خطا در بارگذاری پیام‌ها' );
				return;
			}
			history.innerHTML = res.data.html; // HTML از سرور با esc_html ساخته شده
			history.scrollTop = history.scrollHeight;
			form.hidden = false;
			form.querySelector( 'textarea' ).focus();
			button.querySelector( '.hpn-badge' )?.remove();
		} catch {
			note( 'ارتباط با سرور برقرار نشد.' );
		}
	};

	document.addEventListener( 'click', ( event ) => {
		const button = event.target.closest( '[data-hpn-chat]' );
		if ( button ) {
			event.preventDefault();
			open( button );
		} else if ( event.target.closest( '[data-hpn-close]' ) || event.target === dialog ) {
			dialog.close();
		}
	} );

	dialog.addEventListener( 'close', () => {
		history.textContent = '';
		opener?.focus();
	} );

	form.addEventListener( 'submit', async ( event ) => {
		event.preventDefault();
		const textarea = form.querySelector( 'textarea' );
		const button = form.querySelector( 'button[type="submit"]' );
		if ( ! textarea.value.trim() ) {
			return;
		}
		button.disabled = true;
		fail( '' );
		try {
			const res = await post( 'hodima_panel_chat_send', { order_id: orderId, message: textarea.value } );
			if ( res.success ) {
				textarea.value = '';
				history.querySelector( '.no-messages' )?.remove();
				history.insertAdjacentHTML( 'beforeend', res.data.html );
				history.scrollTop = history.scrollHeight;
			} else {
				fail( ( res.data && res.data.message ) || 'خطایی رخ داد.' );
			}
		} catch {
			fail( 'خطا در ارسال پیام؛ دوباره بفرستید.' );
		} finally {
			button.disabled = false;
			textarea.focus();
		}
	} );

	const wanted = new URL( window.location.href ).searchParams.get( 'chat' );
	if ( wanted ) {
		const button = document.querySelector( `[data-hpn-chat="${ CSS.escape( wanted ) }"]` );
		if ( button ) {
			open( button );
		}
	}
}() );
