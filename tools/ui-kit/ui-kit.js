/**
 * کیت UI مشترک پیشخوان هدیما — رفتار اجزا (منبع واحد؛ استایل: ui-kit.css؛ اسکیل hodima-admin-ui)
 *
 * - .hdk-tabset: تب‌ها (ARIA tabs؛ فلش، Home/End؛ فیلد نامعتبر در تب پنهان تبش را باز می‌کند).
 * - [data-hdk-media]: انتخاب‌گر رسانه (کتابخانه وردپرس، برداشتن، پیش‌نمایش از فیلد آدرس).
 * - [data-hdk-repeat]: لیست تکرارشونده (افزودن از <template>، جابه‌جایی، حذف با بازگردانی،
 *   شماره‌گذاری دوباره نام/شناسه/برچسب‌ها).
 * فقط داخل .hdk-ui کار می‌کند. پیشوند نام‌ها در ساخت به پیشوند بسته عوض می‌شود (tools/ui-kit/kit.py؛
 * کپی را دستی ویرایش نکن). رویدادها: input/change، hdk:tab-change و hdk:repeat-change
 * (حباب‌دار). بدون JS تب‌ها پنهان و همه بخش‌ها پشت‌سرهم دیده می‌شوند. زیر ESLint بدون baseline.
 */
( () => {
	'use strict';

	const fire = ( input ) => {
		input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
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
			set.dispatchEvent( new CustomEvent( 'hdk:tab-change', { bubbles: true, detail: { tab } } ) );
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
		const input = box.querySelector( '[data-hdk-media-input]' );
		const img = box.querySelector( '[data-hdk-media-img]' );
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
		const input = box.querySelector( '[data-hdk-media-input]' );
		// بدون کتابخانه رسانه وردپرس (مثل ابزار تست) فقط فیلد آدرس کار می‌کند
		if ( ! input || typeof wp === 'undefined' || ! wp.media ) {
			return;
		}
		const frame = wp.media( {
			title: box.dataset.hdkMediaTitle || 'انتخاب تصویر',
			button: { text: box.dataset.hdkMediaButton || 'استفاده از این تصویر' },
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
		document.querySelectorAll( '.hdk-ui [data-hdk-media]' ).forEach( syncMedia );
		document.addEventListener( 'click', ( e ) => {
			const pick = e.target.closest?.( '[data-hdk-media-pick]' );
			const clear = e.target.closest?.( '[data-hdk-media-clear]' );
			const box = ( pick ?? clear )?.closest( '[data-hdk-media]' );
			if ( ! box?.closest( '.hdk-ui' ) ) {
				return;
			}
			if ( pick ) {
				pickMedia( box );
			} else {
				const input = box.querySelector( '[data-hdk-media-input]' );
				input.value = '';
				fire( input );
				input.focus();
			}
		} );
		document.addEventListener( 'input', ( e ) => {
			if ( e.target.matches?.( '[data-hdk-media-input]' ) ) {
				const box = e.target.closest( '[data-hdk-media]' );
				if ( box?.closest( '.hdk-ui' ) ) {
					syncMedia( box );
				}
			}
		} );
	};

	/* ── لیست تکرارشونده ── */
	const initRepeat = ( root ) => {
		const rows = root.querySelector( '[data-hdk-rows]' );
		const template = root.querySelector( '[data-hdk-row-template]' );
		const addBtn = root.querySelector( '[data-hdk-add]' );
		const empty = root.querySelector( '[data-hdk-empty]' );
		const undoBox = root.querySelector( '[data-hdk-undo]' );
		const count = root.querySelector( '[data-hdk-count]' );
		if ( ! rows || ! template || ! addBtn ) {
			return;
		}
		const max = Number( root.dataset.hdkMax || 99 );
		const name = root.dataset.hdkName ?? '';
		const idPrefix = root.dataset.hdkId ?? '';
		const faNum = ( n ) => new Intl.NumberFormat( 'fa-IR' ).format( n );
		const quote = ( text ) => text.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );
		const nameRe = new RegExp( `^${ quote( name ) }\\[[^\\]]*\\]` );
		const idRe = new RegExp( `^${ quote( idPrefix ) }-[^-]+-` );
		const rowEls = () => [ ...rows.querySelectorAll( ':scope > [data-hdk-row]' ) ];
		const changed = () => root.dispatchEvent( new CustomEvent( 'hdk:repeat-change', { bubbles: true } ) );

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
				row.querySelectorAll( '[data-hdk-n]' ).forEach( ( node ) => {
					node.textContent = n;
				} );
				row.querySelectorAll( '[data-hdk-label]' ).forEach( ( btn ) => {
					btn.setAttribute( 'aria-label', btn.dataset.hdkLabel.replace( '%s', n ) );
				} );
				const up = row.querySelector( '[data-hdk-move="-1"]' );
				const down = row.querySelector( '[data-hdk-move="1"]' );
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
				count.textContent = ( root.dataset.hdkCountText || '%s' ).replace( '%s', faNum( list.length ) );
			}
			addBtn.disabled = list.length >= max;
			addBtn.title = addBtn.disabled ? `حداکثر ${ faNum( max ) }` : '';
		};

		const clearUndo = () => undoBox?.replaceChildren();

		const add = () => {
			if ( rowEls().length >= max ) {
				return;
			}
			const row = template.content.cloneNode( true ).querySelector( '[data-hdk-row]' );
			rows.append( row );
			reindex();
			clearUndo();
			row.querySelector( '[data-hdk-row-title]' )?.focus();
			changed();
		};

		const remove = ( row ) => {
			const index = rowEls().indexOf( row );
			const label = row.querySelector( '[data-hdk-row-title]' )?.value.trim()
				|| `${ root.dataset.hdkItem || 'ردیف' } ${ faNum( index + 1 ) }`;
			row.remove();
			reindex();
			changed();
			if ( ! undoBox ) {
				return;
			}
			// بازگردانی به‌جای پنجره تأیید: ردیف تا ذخیره نوشته از دست نرفته است
			const undo = document.createElement( 'button' );
			undo.type = 'button';
			undo.className = 'button-link hdk-undo-btn';
			undo.textContent = root.dataset.hdkUndoLabel || 'بازگردانی';
			undo.addEventListener( 'click', () => {
				rows.insertBefore( row, rowEls()[ index ] ?? null );
				clearUndo();
				reindex();
				changed();
				row.querySelector( '[data-hdk-row-title]' )?.focus();
			} );
			const message = document.createElement( 'span' );
			message.textContent = `${ ( root.dataset.hdkRemovedText || '«%s» حذف شد.' ).replace( '%s', label ) } `;
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
			const same = row.querySelector( `[data-hdk-move="${ dir }"]` );
			( same.disabled ? row.querySelector( `[data-hdk-move="${ -dir }"]` ) : same ).focus();
			changed();
		};

		addBtn.addEventListener( 'click', add );
		rows.addEventListener( 'click', ( e ) => {
			const btn = e.target.closest( 'button' );
			const row = btn?.closest( '[data-hdk-row]' );
			if ( ! row ) {
				return;
			}
			if ( btn.hasAttribute( 'data-hdk-remove' ) ) {
				remove( row );
			} else if ( btn.hasAttribute( 'data-hdk-move' ) ) {
				move( row, Number( btn.dataset.hdkMove ) );
			}
		} );
		reindex();
	};

	/* ── دکمه «کپی»: متن data-hdk-copy را در کلیپ‌بورد می‌گذارد و چند لحظه «کپی شد» نشان می‌دهد ── */
	const initCopy = () => {
		document.addEventListener( 'click', ( e ) => {
			const btn = e.target.closest?.( '.hdk-ui [data-hdk-copy]' );
			if ( ! btn || ! navigator.clipboard ) {
				return;
			}
			navigator.clipboard.writeText( btn.dataset.hdkCopy ).then( () => {
				const before = btn.textContent;
				btn.textContent = btn.dataset.hdkCopied || 'کپی شد';
				btn.setAttribute( 'aria-live', 'polite' );
				setTimeout( () => {
					btn.textContent = before;
				}, 1600 );
			} ).catch( () => {} );
		} );
	};

	const init = () => {
		initCopy();
		document.querySelectorAll( '.hdk-ui .hdk-tabset' ).forEach( initTabset );
		document.querySelectorAll( '.hdk-ui [data-hdk-repeat]' ).forEach( initRepeat );
		initMedia();
	};

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
