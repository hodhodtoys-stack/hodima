/**
 * صفحه «آمار» نوتیفیکیشن‌ها (پیشخوان)
 *
 * - راهنمای ستون‌های نمودار با ماوس، لمس و صفحه‌کلید (فلش‌ها روی نمودار)؛
 *   همه عددها در «جدول روز به روز» هم هست، راهنما فقط کمک است.
 * - انتخاب نوتیفیکیشن بدون دکمه «نمایش» فیلتر می‌کند.
 * - صفر کردن آمار با <dialog> تأیید (نه confirm مرورگر).
 */
( () => {
	'use strict';

	const root = document.querySelector( '.hdn-stats-page' );
	if ( ! root ) {
		return;
	}
	root.classList.add( 'hdn-js' );

	root.querySelector( '[data-hdn-autosubmit]' )?.addEventListener( 'change', ( e ) => e.target.form.submit() );

	/* ── راهنمای نمودار ─────────────────────────────────────────── */
	root.querySelectorAll( '.hdn-chart' ).forEach( ( chart ) => {
		const plot = chart.querySelector( '.hdn-chart__plot' );
		const svg = plot.querySelector( 'svg' );
		const tip = plot.querySelector( '.hdn-chart__tip' );
		const cols = [ ...svg.querySelectorAll( '.hdn-chart__col' ) ];
		const label = chart.querySelector( '.hdn-chart__title' )?.textContent || '';
		let current = -1;

		const show = ( index ) => {
			if ( index < 0 || index >= cols.length ) {
				return;
			}
			cols[ current ]?.classList.remove( 'is-active' );
			current = index;
			const col = cols[ index ];
			col.classList.add( 'is-active' );
			tip.replaceChildren();
			const value = document.createElement( 'strong' );
			value.textContent = col.dataset.value;
			tip.append( value, `${ label.replace( / روزانه$/, '' ) } · ${ col.dataset.day }` );
			tip.hidden = false;
			const hit = col.querySelector( '.hdn-chart__hit' ).getBoundingClientRect();
			const box = plot.getBoundingClientRect();
			const half = tip.offsetWidth / 2;
			const center = Math.min( Math.max( hit.left + ( hit.width / 2 ) - box.left, half ), box.width - half );
			tip.style.left = `${ center }px`;
		};
		const hide = () => {
			cols[ current ]?.classList.remove( 'is-active' );
			current = -1;
			tip.hidden = true;
		};

		cols.forEach( ( col, i ) => {
			col.addEventListener( 'pointerenter', () => show( i ) );
			col.addEventListener( 'pointerdown', () => show( i ) );
		} );
		svg.addEventListener( 'pointerleave', hide );

		// صفحه‌کلید: نمودار یک توقف Tab؛ فلش‌ها روز به روز (راست‌به‌چپ: ← روز بعد)
		svg.setAttribute( 'tabindex', '0' );
		svg.addEventListener( 'focus', () => show( current < 0 ? 0 : current ) );
		svg.addEventListener( 'blur', hide );
		svg.addEventListener( 'keydown', ( e ) => {
			const step = { ArrowLeft: 1, ArrowRight: -1, Home: -cols.length, End: cols.length }[ e.key ];
			if ( step !== undefined ) {
				e.preventDefault();
				show( Math.min( Math.max( current + step, 0 ), cols.length - 1 ) );
			}
		} );
	} );

	/* ── تأیید صفر کردن ─────────────────────────────────────────── */
	const dialog = root.querySelector( '[data-hdn-dialog]' );
	let pending = null;
	root.querySelectorAll( 'form[data-hdn-confirm]' ).forEach( ( form ) => {
		form.addEventListener( 'submit', ( e ) => {
			if ( form.dataset.confirmed === '1' || typeof dialog?.showModal !== 'function' ) {
				return;
			}
			e.preventDefault();
			pending = form;
			dialog.querySelector( '[data-hdn-dialog-text]' ).textContent = form.dataset.hdnConfirm;
			dialog.showModal();
			dialog.querySelector( '[data-hdn-dialog-cancel]' ).focus();
		} );
	} );
	dialog?.querySelector( '[data-hdn-dialog-cancel]' ).addEventListener( 'click', () => dialog.close() );
	dialog?.querySelector( '[data-hdn-dialog-ok]' ).addEventListener( 'click', () => {
		dialog.close();
		if ( pending ) {
			pending.dataset.confirmed = '1';
			pending.requestSubmit();
		}
	} );
} )();
