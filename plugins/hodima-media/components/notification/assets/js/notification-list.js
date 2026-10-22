/**
 * فهرست نوتیفیکیشن‌ها (پیشخوان): کلید روشن/خاموش در خود فهرست.
 *
 * بدون JS کلید پنهان است (روشن/خاموش از صفحه ویرایش). با کلیک، admin-ajax
 * hodima_notif_toggle؛ برچسب وضعیت همان سطر با پاسخ سرور به‌روز می‌شود و
 * اگر نشد کلید به حالت قبل برمی‌گردد و پیام می‌دهد.
 */
( () => {
	'use strict';

	const config = window.hodimaNotifList || {};
	const live = document.createElement( 'div' );
	live.className = 'screen-reader-text';
	live.setAttribute( 'role', 'status' );
	document.body.append( live );

	document.querySelectorAll( '[data-hdn-toggle]' ).forEach( ( button ) => {
		button.hidden = false;
		button.addEventListener( 'click', async () => {
			if ( button.getAttribute( 'aria-busy' ) === 'true' ) {
				return;
			}
			const before = button.getAttribute( 'aria-checked' ) === 'true';
			button.setAttribute( 'aria-checked', String( ! before ) );
			button.setAttribute( 'aria-busy', 'true' );
			const body = new FormData();
			body.append( 'action', 'hodima_notif_toggle' );
			body.append( 'nonce', config.nonce || '' );
			body.append( 'id', button.dataset.hdnToggle );
			body.append( 'active', before ? '0' : '1' );
			try {
				const res = await fetch( config.ajax, { method: 'POST', body, credentials: 'same-origin' } );
				const json = await res.json();
				if ( ! json.success ) {
					throw new Error( json.data?.message || 'خطا' );
				}
				const cell = button.closest( 'td' );
				const pill = cell.querySelector( '.hd-pill' );
				if ( pill ) {
					pill.className = `hd-pill hd-pill--${ json.data.type }`;
					pill.textContent = json.data.label;
				}
				const note = cell.querySelector( 'small' );
				if ( note ) {
					note.textContent = json.data.note;
				}
				live.textContent = `${ json.data.active ? 'روشن شد' : 'خاموش شد' }: ${ json.data.label }`;
			} catch ( e ) {
				button.setAttribute( 'aria-checked', String( before ) );
				live.textContent = `ذخیره نشد: ${ e.message }`;
			} finally {
				button.removeAttribute( 'aria-busy' );
			}
		} );
	} );
} )();
