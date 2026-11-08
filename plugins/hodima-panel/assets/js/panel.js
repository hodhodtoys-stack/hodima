/**
 * پنل کاربری هدیما — رفتار سمت مرورگر (JavaScript خالص؛ طرح تأییدشده، مرحله ۵).
 *
 * همه فرم‌ها بدون این فایل هم کار می‌کنند (سرور همه‌چیز را می‌سنجد)؛ اینجا فقط:
 * تب‌های ورود، شمارش معکوس کد، نمایش رمز، شمارنده و جمع زنده فرم سفارش،
 * جستجوی محصول، دو بخش «پیام تازه»، ذخیره پروفایل با AJAX، فهرست شهرها، تأیید حذف.
 */
( function () {
	'use strict';

	const data = window.hodimaPanel || {};
	const money = new Intl.NumberFormat( 'fa-IR', { maximumFractionDigits: 0 } );
	const num = new Intl.NumberFormat( 'fa-IR', { maximumFractionDigits: 3 } );
	const toman = ( value ) => `${money.format( Math.round( value ) )  } تومان`;
	const latin = ( text ) => String( text )
		.replace( /[۰-۹]/g, ( d ) => String( '۰۱۲۳۴۵۶۷۸۹'.indexOf( d ) ) )
		.replace( /[٠-٩]/g, ( d ) => String( '٠١٢٣٤٥٦٧٨٩'.indexOf( d ) ) );

	/* ---------- تب‌های ورود (الگوی ARIA tabs) ---------- */
	document.querySelectorAll( '[data-hpn-tabs]' ).forEach( ( list ) => {
		const tabs = [ ...list.querySelectorAll( '[role="tab"]' ) ];
		const select = ( tab, focus ) => {
			tabs.forEach( ( t ) => {
				const on = t === tab;
				t.setAttribute( 'aria-selected', on ? 'true' : 'false' );
				t.tabIndex = on ? 0 : -1;
				const panel = document.getElementById( t.getAttribute( 'aria-controls' ) );
				if ( panel ) {
					panel.hidden = ! on;
				}
			} );
			if ( focus ) {
				tab.focus();
			}
		};
		list.hidden = false;
		select( tabs.find( ( t ) => 'true' === t.getAttribute( 'aria-selected' ) ) || tabs[ 0 ], false );
		tabs.forEach( ( tab, i ) => {
			tab.addEventListener( 'click', () => select( tab, false ) );
			tab.addEventListener( 'keydown', ( e ) => {
				// راست‌به‌چپ: چپ = بعدی
				const step = { ArrowLeft: 1, ArrowRight: -1 }[ e.key ];
				if ( step ) {
					e.preventDefault();
					select( tabs[ ( i + step + tabs.length ) % tabs.length ], true );
				} else if ( 'Home' === e.key || 'End' === e.key ) {
					e.preventDefault();
					select( 'Home' === e.key ? tabs[ 0 ] : tabs[ tabs.length - 1 ], true );
				}
			} );
		} );
	} );

	/* ---------- شمارش معکوس ارسال دوباره کد ---------- */
	document.querySelectorAll( '[data-hpn-timer]' ).forEach( ( box ) => {
		let left = parseInt( box.dataset.hpnTimer, 10 ) || 0;
		const text = box.querySelector( '[data-hpn-timer-text]' );
		const resend = document.querySelector( '[data-hpn-resend]' );
		if ( left <= 0 ) {
			return;
		}
		const tick = () => {
			if ( left <= 0 ) {
				clearInterval( timer );
				box.hidden = true;
				if ( resend ) {
					resend.hidden = false;
				}
				return;
			}
			const m = Math.floor( left / 60 );
			const s = left % 60;
			text.textContent = `${num.format( m )  }:${   s < 10 ? '۰' : ''  }${num.format( s )}`;
			left--;
		};
		const timer = setInterval( tick, 1000 );
		tick();
	} );

	/* ---------- نمایش/پنهان رمز ---------- */
	document.querySelectorAll( '[data-hpn-reveal]' ).forEach( ( btn ) => {
		const input = document.getElementById( btn.dataset.hpnReveal );
		if ( ! input ) {
			return;
		}
		btn.hidden = false;
		btn.addEventListener( 'click', () => {
			const show = 'password' === input.type;
			input.type = show ? 'text' : 'password';
			btn.setAttribute( 'aria-pressed', show ? 'true' : 'false' );
			btn.textContent = show ? 'پنهان' : 'نمایش';
		} );
	} );

	/* ---------- تأیید حذف ---------- */
	document.addEventListener( 'click', ( e ) => {
		const link = e.target.closest( '[data-hpn-confirm]' );
		if ( link && ! window.confirm( link.dataset.hpnConfirm ) ) { // eslint-disable-line no-alert -- تأیید ساده حذف؛ بدون JS پیوند مستقیم با nonce کار می‌کند.
			e.preventDefault();
		}
	} );

	/* ---------- نام فایل فیش انتخاب‌شده ---------- */
	document.querySelectorAll( '.hpn-upload__file' ).forEach( ( input ) => {
		input.addEventListener( 'change', () => {
			const label = input.form.querySelector( '.hpn-upload__label span' );
			if ( label && input.files.length ) {
				label.textContent = input.files[ 0 ].name;
			}
		} );
	} );

	/* ---------- فرم پیش‌خرید / پیش‌فاکتور ---------- */
	document.querySelectorAll( '[data-hpn-order-form]' ).forEach( ( form ) => {
		const cards = [ ...form.querySelectorAll( '[data-hpn-product]' ) ];
		const totalEl = form.querySelector( '[data-hpn-total]' );
		const countEl = form.querySelector( '[data-hpn-count]' );

		const parts = ( card ) => {
			const input = card.querySelector( '.hpn-stepper__input' );
			return {
				input,
				pick: card.querySelector( '[data-hpn-pick]' ),
				line: card.querySelector( '[data-hpn-line]' ),
				msg: card.querySelector( '[data-hpn-msg]' ),
				min: parseFloat( input.min ) || 1,
				step: parseFloat( input.step ) || 1,
				multiple: '1' === input.dataset.multiple,
				price: parseFloat( input.dataset.price ) || 0,
				capacity: parseFloat( input.dataset.capacity ) || 1,
			};
		};
		const qtyOf = ( p ) => parseFloat( latin( p.input.value ) ) || 0;
		const round = ( v ) => Math.round( v * 1000 ) / 1000;

		// همان قاعده Orders::validate_items در سرور
		const fix = ( p ) => {
			let q = qtyOf( p );
			let note = '';
			if ( q < p.min ) {
				q = p.min;
				note = `حداقل سفارش ${  num.format( p.min )  } است.`;
			} else if ( p.multiple && Math.abs( q / p.min - Math.round( q / p.min ) ) > 1e-6 ) {
				q = round( Math.max( p.min, Math.round( q / p.min ) * p.min ) );
				note = `تعداد باید مضربی از ${  num.format( p.min )  } باشد؛ ${  num.format( q )  } گذاشته شد.`;
			}
			p.input.value = String( round( q ) );
			p.msg.textContent = note;
			p.msg.hidden = '' === note;
			return '' === note;
		};

		const update = () => {
			let total = 0;
			let count = 0;
			cards.forEach( ( card ) => {
				const p = parts( card );
				const on = p.pick.checked;
				card.classList.toggle( 'is-selected', on );
				const line = qtyOf( p ) * p.capacity * p.price;
				p.line.textContent = on ? toman( line ) : '';
				if ( on ) {
					total += line;
					count++;
				}
			} );
			totalEl.textContent = count ? `جمع: ${  toman( total )}` : '';
			countEl.textContent = count ? `${num.format( count )  } محصول انتخاب شده` : 'محصولی انتخاب نشده';
		};

		cards.forEach( ( card ) => {
			const p = parts( card );
			card.querySelectorAll( '[data-hpn-step]' ).forEach( ( btn ) => {
				btn.hidden = false;
				btn.addEventListener( 'click', () => {
					const dir = parseInt( btn.dataset.hpnStep, 10 );
					p.input.value = String( round( Math.max( p.min, qtyOf( p ) + dir * p.step ) ) );
					p.pick.checked = true;
					p.msg.hidden = true;
					update();
				} );
			} );
			p.input.addEventListener( 'input', () => {
				p.pick.checked = true;
				update();
			} );
			p.input.addEventListener( 'change', () => {
				fix( p );
				update();
			} );
			p.pick.addEventListener( 'change', update );
		} );

		form.addEventListener( 'submit', ( e ) => {
			const chosen = cards.filter( ( card ) => parts( card ).pick.checked );
			const bad = chosen.filter( ( card ) => ! fix( parts( card ) ) );
			update();
			if ( ! chosen.length || bad.length ) {
				e.preventDefault();
				const first = bad[ 0 ] || cards[ 0 ];
				if ( ! chosen.length ) {
					countEl.textContent = 'اول دست‌کم یک محصول را به سفارش اضافه کنید.';
				}
				first.querySelector( bad.length ? '.hpn-stepper__input' : '[data-hpn-pick]' ).focus();
			}
		} );

		const search = document.querySelector( '[data-hpn-search]' );
		if ( search ) {
			search.closest( '[data-hpn-search-wrap]' ).hidden = false;
			const none = form.querySelector( '[data-hpn-no-match]' );
			search.addEventListener( 'input', () => {
				const term = search.value.trim();
				let shown = 0;
				cards.forEach( ( card ) => {
					const ok = '' === term || card.dataset.name.includes( term );
					card.hidden = ! ok;
					shown += ok ? 1 : 0;
				} );
				none.hidden = shown > 0;
			} );
		}
		update();
	} );

	/* ---------- پیام تازه: مشاوره یا پیگیری سفارش ---------- */
	// بدون JS هر دو بخش دیده می‌شوند و دکمه فشرده‌شده نوع را تعیین می‌کند؛ اینجا فقط بخش
	// گزینه انتخاب‌شده می‌ماند. بخش پنهان disabled می‌شود تا required آن جلوی ارسال را نگیرد.
	document.querySelectorAll( '[data-hpn-topic-form]' ).forEach( ( form ) => {
		const radios = [ ...form.querySelectorAll( 'input[name="topic"]' ) ];
		const panels = [ ...form.querySelectorAll( '[data-hpn-topic-panel]' ) ];
		const update = () => {
			const current = ( radios.find( ( r ) => r.checked ) || radios[ 0 ] ).value;
			radios.forEach( ( r ) => r.closest( '.hpn-topic' ).classList.toggle( 'is-checked', r.checked ) );
			panels.forEach( ( panel ) => {
				const on = panel.dataset.hpnTopicPanel === current;
				panel.hidden = ! on;
				panel.disabled = ! on;
				panel.querySelector( 'legend' ).classList.add( 'screen-reader-text' );
				panel.querySelectorAll( 'textarea' ).forEach( ( t ) => {
					t.required = on;
				} );
			} );
		};
		radios.forEach( ( r ) => r.addEventListener( 'change', update ) );
		update();
	} );

	/* ---------- پروفایل ---------- */
	const profile = document.querySelector( '[data-hpn-profile]' );
	if ( profile && data.ajaxUrl ) {
		const status = profile.querySelector( '[data-hpn-status]' );
		const say = ( text, ok ) => {
			status.textContent = text;
			status.dataset.ok = ok ? '1' : '0';
		};
		const nonce = () => profile.querySelector( '#up_profile_nonce' ).value;
		const post = async ( action, extra ) => {
			const body = extra || new FormData();
			body.set( 'action', action );
			body.set( 'nonce', nonce() );
			const res = await fetch( data.ajaxUrl, { method: 'POST', body, credentials: 'same-origin' } );
			return res.json();
		};

		profile.addEventListener( 'submit', async ( e ) => {
			e.preventDefault();
			const btn = profile.querySelector( 'button[type="submit"]' );
			btn.disabled = true;
			say( 'در حال ذخیره…', true );
			try {
				const res = await post( 'hodima_panel_profile', new FormData( profile ) );
				say( res.data && res.data.message ? res.data.message : 'خطایی رخ داد.', !! res.success );
				if ( res.success && ! res.data.keep ) {
					setTimeout( () => window.location.reload(), 1200 );
				} else if ( res.data && res.data.need_verify ) {
					profile.querySelector( '.hpn-more' ).open = true;
					profile.querySelector( '#current_password' ).focus();
				}
			} catch {
				say( 'ارتباط با سرور برقرار نشد.', false );
			} finally {
				btn.disabled = false;
			}
		} );

		const send = profile.querySelector( '[data-hpn-send-code]' );
		if ( send ) {
			send.addEventListener( 'click', async () => {
				send.disabled = true;
				try {
					const res = await post( 'hodima_panel_profile_code' );
					say( res.data && res.data.message ? res.data.message : 'خطایی رخ داد.', !! res.success );
					const wait = ( res.data && res.data.wait ) || 0;
					setTimeout( () => {
						send.disabled = false;
					}, wait * 1000 );
					if ( res.success ) {
						profile.querySelector( '#verify_code' ).focus();
					}
				} catch {
					send.disabled = false;
					say( 'ارتباط با سرور برقرار نشد.', false );
				}
			} );
		}

		const citiesJson = document.getElementById( 'hpn-cities' );
		const state = profile.querySelector( '[data-hpn-state]' );
		const list = document.getElementById( 'hpn-city-list' );
		if ( citiesJson && state && list ) {
			const cities = JSON.parse( citiesJson.textContent || '{}' );
			const fill = () => {
				list.replaceChildren( ...( cities[ state.value ] || [] ).map( ( name ) => {
					const option = document.createElement( 'option' );
					option.value = name;
					return option;
				} ) );
			};
			state.addEventListener( 'change', fill );
			fill();
		}
	}

	/* ---------- رشته پیام تیکت: آخرین پیام دیده شود ---------- */
	document.querySelectorAll( '[data-hpn-scroll]' ).forEach( ( box ) => {
		box.scrollTop = box.scrollHeight;
	} );
}() );
