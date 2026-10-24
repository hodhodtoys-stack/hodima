/**
 * Hodima Core — رفتار اجزای فرم پیشخوان (admin-ui.css؛ اسکیل hodima-admin-ui)
 *
 * - .hd-num: دکمه‌های − و + کنار فیلد عدد (بدون JS پنهان‌اند و خود فیلد کار می‌کند).
 * - .hd-opt: کارت گزینه. بدنه کارتِ خاموش inert می‌شود (فقط تماشا). اگر کلید
 *   name ندارد ([data-hd-opt-switch])، فیلد [data-hd-opt-value] بدنه را بین «مقدار
 *   خاموش» (data-hd-opt-off، پیش‌فرض 0) و آخرین مقدار جابه‌جا می‌کند؛ پس داده
 *   همان عدد قبلی است («۰ = خاموش») و فقط ظاهر عوض شده است.
 *
 * هر تغییر رویداد input/change حباب‌دار می‌فرستد تا JS صفحه (خلاصه، پیش‌نمایش)
 * خبردار شود.
 */
( () => {
	'use strict';

	const fire = ( input ) => {
		input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	};

	const initNum = ( box ) => {
		const input = box.querySelector( 'input[type="number"]' );
		if ( ! input ) {
			return;
		}
		box.querySelectorAll( '[data-hd-step]' ).forEach( ( button ) => {
			button.hidden = false;
			button.addEventListener( 'click', () => {
				if ( input.disabled || input.readOnly || input.closest( '[inert]' ) ) {
					return;
				}
				if ( '' === input.value ) {
					input.value = input.min || '0';
				}
				if ( Number( button.dataset.hdStep ) > 0 ) {
					input.stepUp();
				} else {
					input.stepDown();
				}
				fire( input );
			} );
		} );
	};

	const initOpt = ( card ) => {
		const toggle = card.querySelector( '.hd-opt__switch' );
		const body = card.querySelector( '.hd-opt__body' );
		if ( ! toggle ) {
			return;
		}
		const input = toggle.hasAttribute( 'data-hd-opt-switch' ) ? card.querySelector( '[data-hd-opt-value]' ) : null;
		const off = input?.dataset.hdOptOff ?? '0';
		const isOn = () => !! input && '' !== input.value && Number( input.value ) !== Number( off );
		let last = isOn() ? input.value : '';
		const sync = () => {
			if ( body ) {
				body.inert = ! toggle.checked;
			}
		};
		if ( input ) {
			toggle.checked = isOn();
			input.addEventListener( 'input', () => {
				if ( isOn() ) {
					last = input.value;
				}
			} );
			// مقدار صفر/خالی تایپ‌شده یعنی خاموش؛ کلید هم با آن هماهنگ شود
			input.addEventListener( 'change', () => {
				if ( toggle.checked && ! isOn() ) {
					toggle.checked = false;
					input.value = off;
					sync();
				}
			} );
		}
		toggle.addEventListener( 'change', () => {
			if ( input ) {
				input.value = toggle.checked ? last || input.dataset.hdOptDefault || off : off;
				fire( input );
			}
			sync();
		} );
		sync();
	};

	const init = () => {
		document.querySelectorAll( '.hd-num' ).forEach( initNum );
		document.querySelectorAll( '.hd-opt' ).forEach( initOpt );
	};

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
