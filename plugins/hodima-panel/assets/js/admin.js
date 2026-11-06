/**
 * پیشخوان پنل مشتریان ← سفارش‌ها و تیکت‌ها (JavaScript خالص؛ همه فرم‌ها بدون آن هم کار می‌کنند).
 *
 * - [data-hpa-all]: انتخاب همه ردیف‌های فهرست.
 * - [data-hpa-confirm]: تأیید پیش از حذف گروهی یا بستن تیکت (بدون انتخاب ردیف، حذف گروهی فرستاده نمی‌شود).
 * - [data-hpa-require]: «ارسال پاسخ» با متن خالی فرستاده نشود (فرم دکمه «بستن» هم دارد، پس textarea اجباری نیست).
 * - [data-hpa-scroll]: رشته پیام از آخرین پیام شروع شود.
 */
( function () {
	'use strict';

	document.querySelectorAll( '[data-hpa-all]' ).forEach( ( all ) => {
		all.addEventListener( 'change', () => {
			document.querySelectorAll( 'input[name="order_ids[]"]' ).forEach( ( box ) => {
				box.checked = all.checked;
			} );
		} );
	} );

	document.addEventListener( 'click', ( event ) => {
		const button = event.target.closest( '[data-hpa-confirm], [data-hpa-require]' );
		if ( ! button ) {
			return;
		}
		const need = button.dataset.hpaRequire && document.getElementById( button.dataset.hpaRequire );
		if ( need && ! need.value.trim() ) {
			event.preventDefault();
			need.focus();
			return;
		}
		if ( 'hpa-orders-form' === button.getAttribute( 'form' ) ) {
			const picked = document.querySelectorAll( 'input[name="order_ids[]"]:checked' ).length;
			const action = document.getElementById( 'hpa-bulk' );
			if ( ! picked || ! action || ! action.value ) {
				event.preventDefault();
				( action && ! action.value ? action : document.querySelector( 'input[name="order_ids[]"]' ) )?.focus();
				return;
			}
		}
		if ( button.dataset.hpaConfirm && ! window.confirm( button.dataset.hpaConfirm ) ) { // eslint-disable-line no-alert -- تأیید ساده پیش از کار برگشت‌ناپذیر.
			event.preventDefault();
		}
	} );

	document.querySelectorAll( '[data-hpa-scroll]' ).forEach( ( box ) => {
		box.scrollTop = box.scrollHeight;
	} );
}() );
