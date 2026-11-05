/**
 * گفتگوی سفارش در صفحه «سفارش‌ها» پنل کاربری (JavaScript خالص).
 *
 * پنل قدیمی دو اسکریپت داشت (یکی با jQuery داخل صفحه) و شناسه گفتگو را از
 * فرم می‌فرستاد؛ حالا فقط شناسه سفارش فرستاده می‌شود و سرور مالکیت را بررسی می‌کند.
 * بازطراحی ظاهر و پنجره <dialog>: مرحله ۵.
 */
( function () {
	'use strict';

	const data = window.hodimaPanelChat;
	const modal = document.getElementById( 'global-chat-modal' );
	if ( ! data || ! modal ) {
		return;
	}
	const history = document.getElementById( 'global-chat-history' );
	const form = document.getElementById( 'global-chat-form' );
	const title = document.getElementById( 'global-chat-title' );
	let orderId = 0;
	let opener = null;

	const post = async ( action, fields = {} ) => {
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

	const showError = ( text ) => {
		const p = document.createElement( 'p' );
		p.className = 'chat-error';
		p.textContent = text;
		history.replaceChildren( p );
	};

	const clearRowBadge = ( id ) => {
		const row = document.querySelector( `.open-chat-modal[data-order-id="${ id }"]` )?.closest( 'tr' );
		row?.classList.remove( 'row-unread' );
		row?.querySelectorAll( '.unread-badge' ).forEach( ( el ) => el.remove() );
	};

	const open = async ( button ) => {
		orderId = Number( button.dataset.orderId ) || 0;
		opener = button;
		title.textContent = `گفتگوی سفارش #${ orderId }`;
		history.textContent = 'در حال بارگذاری پیام‌ها…';
		form.hidden = true;
		modal.hidden = false;
		modal.classList.add( 'active' );
		document.body.style.overflow = 'hidden';
		try {
			const res = await post( 'hodima_panel_chat_history', { order_id: orderId } );
			if ( ! res.success ) {
				showError( res.data?.message || 'خطا در بارگذاری پیام‌ها' );
				return;
			}
			history.innerHTML = res.data.html; // HTML از سرور با esc_html ساخته شده
			history.scrollTop = history.scrollHeight;
			form.hidden = false;
			form.querySelector( 'textarea' )?.focus();
			clearRowBadge( orderId );
		} catch {
			showError( 'ارتباط با سرور برقرار نشد.' );
		}
	};

	const close = () => {
		modal.classList.remove( 'active' );
		modal.hidden = true;
		document.body.style.overflow = '';
		history.textContent = '';
		opener?.focus();
	};

	document.addEventListener( 'click', ( event ) => {
		const button = event.target.closest( '.open-chat-modal' );
		if ( button ) {
			event.preventDefault();
			open( button );
		} else if ( event.target.closest( '.close-chat-modal' ) || event.target === modal ) {
			close();
		}
	} );

	document.addEventListener( 'keydown', ( event ) => {
		if ( 'Escape' === event.key && ! modal.hidden ) {
			close();
		}
	} );

	form.addEventListener( 'submit', async ( event ) => {
		event.preventDefault();
		const textarea = form.querySelector( 'textarea' );
		const button = form.querySelector( 'button[type="submit"]' );
		if ( ! textarea.value.trim() ) {
			return;
		}
		button.disabled = true;
		try {
			const res = await post( 'hodima_panel_chat_send', { order_id: orderId, message: textarea.value } );
			if ( res.success ) {
				textarea.value = '';
				history.querySelector( '.no-messages' )?.remove();
				history.insertAdjacentHTML( 'beforeend', res.data.html );
				history.scrollTop = history.scrollHeight;
			} else {
				window.showToastMessage?.( res.data?.message || 'خطایی رخ داد.', 'up-alert-error' );
			}
		} catch {
			window.showToastMessage?.( 'خطا در ارسال پیام.', 'up-alert-error' );
		} finally {
			button.disabled = false;
			textarea.focus();
		}
	} );
}() );
