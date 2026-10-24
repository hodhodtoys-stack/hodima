/**
 * Hodima Core — رفتار اجزای فرم پیشخوان (admin-ui.css؛ اسکیل hodima-admin-ui)
 *
 * - .hd-num: دکمه‌های − و + کنار فیلد عدد (بدون JS پنهان‌اند و خود فیلد کار می‌کند).
 * - .hd-opt: کارت گزینه. بدنه کارتِ خاموش inert می‌شود (فقط تماشا). اگر کلید
 *   name ندارد ([data-hd-opt-switch])، فیلد [data-hd-opt-value] بدنه را بین «مقدار
 *   خاموش» (data-hd-opt-off، پیش‌فرض 0) و آخرین مقدار جابه‌جا می‌کند؛ پس داده
 *   همان عدد قبلی است («۰ = خاموش») و فقط ظاهر عوض شده است.
 *
 * - .hd-tabset: تب‌ها (ARIA tabs؛ فلش، Home/End؛ فیلد نامعتبر در تب پنهان تبش را باز می‌کند).
 * - [data-hd-media]: انتخاب‌گر رسانه (کتابخانه وردپرس، برداشتن، پیش‌نمایش از فیلد آدرس).
 * - [data-hd-repeat]: لیست تکرارشونده (افزودن از <template>، جابه‌جایی، حذف با بازگردانی،
 *   شماره‌گذاری دوباره نام/شناسه/برچسب‌ها).
 *
 * هر تغییر رویداد input/change حباب‌دار می‌فرستد تا JS صفحه (خلاصه، پیش‌نمایش)
 * خبردار شود؛ تب و لیست هم hd:tab-change و hd:repeat-change می‌فرستند.
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

	/* ── تب‌ها ── */
	const initTabset = ( set ) => {
		const list = set.querySelector( '[role="tablist"]' );
		const tabs = [ ...set.querySelectorAll( '[role="tab"]' ) ];
		if ( ! list || ! tabs.length ) {
			return;
		}
		const panelOf = ( tab ) => document.getElementById( tab.getAttribute( 'aria-controls' ) );
		const select = ( tab, focus ) => {
			tabs.forEach( ( item ) => {
				const on = item === tab;
				item.setAttribute( 'aria-selected', String( on ) );
				item.tabIndex = on ? 0 : -1;
				const panel = panelOf( item );
				if ( panel ) {
					panel.hidden = ! on;
				}
			} );
			if ( focus ) {
				tab.focus();
			}
			set.dispatchEvent( new CustomEvent( 'hd:tab-change', { bubbles: true, detail: { tab } } ) );
		};

		list.hidden = false;
		select( tabs.find( ( tab ) => 'true' === tab.getAttribute( 'aria-selected' ) ) ?? tabs[ 0 ], false );

		tabs.forEach( ( tab, i ) => {
			tab.addEventListener( 'click', () => select( tab, false ) );
			tab.addEventListener( 'keydown', ( e ) => {
				// راست‌به‌چپ: فلش چپ = تب بعدی
				const rtl = 'rtl' === getComputedStyle( list ).direction;
				const next = rtl ? 'ArrowLeft' : 'ArrowRight';
				const prev = rtl ? 'ArrowRight' : 'ArrowLeft';
				let target = null;
				if ( e.key === next ) {
					target = tabs[ ( i + 1 ) % tabs.length ];
				} else if ( e.key === prev ) {
					target = tabs[ ( i - 1 + tabs.length ) % tabs.length ];
				} else if ( 'Home' === e.key ) {
					target = tabs[ 0 ];
				} else if ( 'End' === e.key ) {
					target = tabs[ tabs.length - 1 ];
				}
				if ( target ) {
					e.preventDefault();
					select( target, true );
				}
			} );
		} );

		// فیلد نامعتبر در تب پنهان: تبش باز شود تا مرورگر بتواند نشانش بدهد
		set.addEventListener( 'invalid', ( e ) => {
			const panel = e.target.closest?.( '[role="tabpanel"]' );
			if ( panel?.hidden ) {
				const tab = tabs.find( ( item ) => item.getAttribute( 'aria-controls' ) === panel.id );
				if ( tab ) {
					select( tab, false );
				}
			}
		}, true );
	};

	/* ── انتخاب‌گر رسانه ── */
	const syncMedia = ( box ) => {
		const input = box.querySelector( '[data-hd-media-input]' );
		const img = box.querySelector( '[data-hd-media-img]' );
		if ( ! input ) {
			return;
		}
		const ok = /^https?:\/\//i.test( input.value.trim() );
		if ( img ) {
			if ( ok ) {
				img.src = input.value.trim();
			} else {
				img.removeAttribute( 'src' );
			}
			img.hidden = ! ok;
		}
		box.classList.toggle( 'is-empty', ! ok );
	};

	const pickMedia = ( box ) => {
		const input = box.querySelector( '[data-hd-media-input]' );
		// بدون کتابخانه رسانه وردپرس (مثل ابزار تست) فقط فیلد آدرس کار می‌کند
		if ( ! input || typeof wp === 'undefined' || ! wp.media ) {
			return;
		}
		const frame = wp.media( {
			title: box.dataset.hdMediaTitle || 'انتخاب تصویر',
			button: { text: box.dataset.hdMediaButton || 'استفاده از این تصویر' },
			library: { type: 'image' },
			multiple: false,
		} );
		frame.on( 'select', () => {
			input.value = frame.state().get( 'selection' ).first().toJSON().url;
			fire( input );
		} );
		frame.open();
	};

	const initMedia = () => {
		document.querySelectorAll( '[data-hd-media]' ).forEach( syncMedia );
		document.addEventListener( 'click', ( e ) => {
			const pick = e.target.closest?.( '[data-hd-media-pick]' );
			const clear = e.target.closest?.( '[data-hd-media-clear]' );
			const box = ( pick ?? clear )?.closest( '[data-hd-media]' );
			if ( ! box ) {
				return;
			}
			if ( pick ) {
				pickMedia( box );
			} else {
				const input = box.querySelector( '[data-hd-media-input]' );
				input.value = '';
				fire( input );
				input.focus();
			}
		} );
		document.addEventListener( 'input', ( e ) => {
			if ( e.target.matches?.( '[data-hd-media-input]' ) ) {
				const box = e.target.closest( '[data-hd-media]' );
				if ( box ) {
					syncMedia( box );
				}
			}
		} );
	};

	/* ── لیست تکرارشونده ── */
	const initRepeat = ( root ) => {
		const rows = root.querySelector( '[data-hd-rows]' );
		const template = root.querySelector( '[data-hd-row-template]' );
		const addBtn = root.querySelector( '[data-hd-add]' );
		const empty = root.querySelector( '[data-hd-empty]' );
		const undoBox = root.querySelector( '[data-hd-undo]' );
		const count = root.querySelector( '[data-hd-count]' );
		if ( ! rows || ! template || ! addBtn ) {
			return;
		}
		const max = Number( root.dataset.hdMax || 99 );
		const name = root.dataset.hdName ?? '';
		const idPrefix = root.dataset.hdId ?? '';
		const faNum = ( n ) => new Intl.NumberFormat( 'fa-IR' ).format( n );
		const quote = ( text ) => text.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );
		const nameRe = new RegExp( `^${ quote( name ) }\\[[^\\]]*\\]` );
		const idRe = new RegExp( `^${ quote( idPrefix ) }-[^-]+-` );
		const rowEls = () => [ ...rows.querySelectorAll( ':scope > [data-hd-row]' ) ];
		const changed = () => root.dispatchEvent( new CustomEvent( 'hd:repeat-change', { bubbles: true } ) );

		/** نام، شناسه، برچسب و aria-label هر ردیف به ترتیب فعلی (ذخیره همان ترتیب آرایه است). */
		const reindex = () => {
			const list = rowEls();
			list.forEach( ( row, i ) => {
				const n = faNum( i + 1 );
				row.querySelectorAll( `[name^="${ name }["]` ).forEach( ( input ) => {
					input.name = input.name.replace( nameRe, `${ name }[${ i }]` );
				} );
				row.querySelectorAll( `[id^="${ idPrefix }-"]` ).forEach( ( node ) => {
					node.id = node.id.replace( idRe, `${ idPrefix }-${ i }-` );
				} );
				row.querySelectorAll( `label[for^="${ idPrefix }-"]` ).forEach( ( label ) => {
					label.htmlFor = label.htmlFor.replace( idRe, `${ idPrefix }-${ i }-` );
				} );
				row.querySelectorAll( '[data-hd-n]' ).forEach( ( node ) => {
					node.textContent = n;
				} );
				row.querySelectorAll( '[data-hd-label]' ).forEach( ( btn ) => {
					btn.setAttribute( 'aria-label', btn.dataset.hdLabel.replace( '%s', n ) );
				} );
				const up = row.querySelector( '[data-hd-move="-1"]' );
				const down = row.querySelector( '[data-hd-move="1"]' );
				if ( up ) {
					up.disabled = 0 === i;
				}
				if ( down ) {
					down.disabled = i === list.length - 1;
				}
			} );
			if ( empty ) {
				empty.hidden = list.length > 0;
			}
			if ( count ) {
				count.textContent = ( root.dataset.hdCountText || '%s' ).replace( '%s', faNum( list.length ) );
			}
			addBtn.disabled = list.length >= max;
			addBtn.title = addBtn.disabled ? `حداکثر ${ faNum( max ) }` : '';
		};

		const clearUndo = () => undoBox?.replaceChildren();

		const add = () => {
			if ( rowEls().length >= max ) {
				return;
			}
			const row = template.content.cloneNode( true ).querySelector( '[data-hd-row]' );
			rows.append( row );
			reindex();
			clearUndo();
			row.querySelector( '[data-hd-row-title]' )?.focus();
			changed();
		};

		const remove = ( row ) => {
			const index = rowEls().indexOf( row );
			const label = row.querySelector( '[data-hd-row-title]' )?.value.trim()
				|| `${ root.dataset.hdItem || 'ردیف' } ${ faNum( index + 1 ) }`;
			row.remove();
			reindex();
			changed();
			if ( ! undoBox ) {
				return;
			}
			// بازگردانی به‌جای پنجره تأیید: ردیف تا ذخیره نوشته از دست نرفته است
			const undo = document.createElement( 'button' );
			undo.type = 'button';
			undo.className = 'button-link hd-undo-btn';
			undo.textContent = root.dataset.hdUndoLabel || 'بازگردانی';
			undo.addEventListener( 'click', () => {
				rows.insertBefore( row, rowEls()[ index ] ?? null );
				clearUndo();
				reindex();
				changed();
				row.querySelector( '[data-hd-row-title]' )?.focus();
			} );
			const message = document.createElement( 'span' );
			message.textContent = `${ ( root.dataset.hdRemovedText || '«%s» حذف شد.' ).replace( '%s', label ) } `;
			undoBox.replaceChildren( message, undo );
			undo.focus();
		};

		const move = ( row, dir ) => {
			const list = rowEls();
			const i = list.indexOf( row );
			const j = i + dir;
			if ( j < 0 || j >= list.length ) {
				return;
			}
			rows.insertBefore( dir < 0 ? row : list[ j ], dir < 0 ? list[ j ] : row );
			reindex();
			// فوکوس روی همان دکمه بماند تا چند بار پشت سر هم جابه‌جا شود (اگر هنوز فعال است)
			const same = row.querySelector( `[data-hd-move="${ dir }"]` );
			( same.disabled ? row.querySelector( `[data-hd-move="${ -dir }"]` ) : same ).focus();
			changed();
		};

		addBtn.addEventListener( 'click', add );
		rows.addEventListener( 'click', ( e ) => {
			const btn = e.target.closest( 'button' );
			const row = btn?.closest( '[data-hd-row]' );
			if ( ! row ) {
				return;
			}
			if ( btn.hasAttribute( 'data-hd-remove' ) ) {
				remove( row );
			} else if ( btn.hasAttribute( 'data-hd-move' ) ) {
				move( row, Number( btn.dataset.hdMove ) );
			}
		} );
		reindex();
	};

	const init = () => {
		document.querySelectorAll( '.hd-num' ).forEach( initNum );
		document.querySelectorAll( '.hd-opt' ).forEach( initOpt );
		document.querySelectorAll( '.hd-tabset' ).forEach( initTabset );
		document.querySelectorAll( '[data-hd-repeat]' ).forEach( initRepeat );
		initMedia();
	};

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
