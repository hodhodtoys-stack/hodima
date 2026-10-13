/**
 * Hodima Stories — پنل پیشخوان.
 *
 *   - جابه‌جایی ردیف‌ها: کشیدن دستگیره (موس، لمس، قلم) و دکمه‌های بالا/پایین
 *     (صفحه‌کلید). ترتیب فرم = ترتیب ذخیره = ترتیب سایت.
 *   - ردیف تازه از <template> همان ردیف PHP ساخته می‌شود (نسخه دوم دستی نه).
 *   - حذف با «بازگردانی» تا پیش از ذخیره (به‌جای confirm مرورگر)؛ فیلدهای
 *     ردیف حذف‌شده غیرفعال‌اند و فرستاده نمی‌شوند.
 *   - شمار ردیف‌ها جدا فرستاده می‌شود تا سرور فرم ناقص را تشخیص دهد.
 *   - هشدار خروج با تغییرات ذخیره‌نشده.
 */
( () => {
	'use strict';

	const cfg = window.HS_ADMIN;
	if ( ! cfg ) {
		return;
	}

	const S = cfg.strings || {};

	const FIELDS = [
		[ '.hs-uid-input', 'uid' ],
		[ '.hs-title-input', 'title' ],
		[ '.hs-link-input', 'link' ],
		[ '.hs-cover-id', 'cover_id' ],
		[ '.hs-cta-link-input', 'cta_link' ],
		[ '.hs-cta-text-input', 'cta_text' ],
	];

	function init() {
		const list = document.getElementById( 'hodima-stories-list' );
		const addBtn = document.getElementById( 'hs-add-new' );
		const form = document.getElementById( 'hodima-stories-form' );
		const template = document.getElementById( 'hs-row-template' );
		const countInput = document.getElementById( 'hs-row-count' );
		const limitMsg = document.getElementById( 'hs-limit-msg' );

		if ( ! list || ! addBtn || ! form || ! template ) {
			return;
		}

		let dirty = false;
		let frame = null;
		let currentImg = null;
		let currentInput = null;
		let drag = null;

		const markDirty = () => {
			dirty = true;
		};

		window.addEventListener( 'beforeunload', ( e ) => {
			if ( dirty ) {
				e.preventDefault();
				e.returnValue = S.unsaved || '';
			}
		} );
		form.addEventListener( 'submit', () => {
			refresh();
			dirty = false;
		} );
		form.addEventListener( 'input', markDirty );
		form.addEventListener( 'change', markDirty );

		const rows = () => Array.from( list.querySelectorAll( '.hodima-story-row' ) );
		const activeRows = () => rows().filter( ( r ) => ! r.classList.contains( 'is-removed' ) );

		// نام فیلدها، شماره ترتیب، دکمه‌های بالا/پایین و شمار ردیف‌ها
		function refresh() {
			const all = activeRows();
			all.forEach( ( row, i ) => {
				FIELDS.forEach( ( [ sel, key ] ) => {
					const el = row.querySelector( sel );
					if ( el ) {
						el.name = `hodima_stories[${ i }][${ key }]`;
					}
				} );
				const num = row.querySelector( '.hs-order-num' );
				if ( num ) {
					num.textContent = String( i + 1 );
				}
				const up = row.querySelector( '.hs-move-up' );
				const down = row.querySelector( '.hs-move-down' );
				if ( up ) {
					up.disabled = i === 0;
				}
				if ( down ) {
					down.disabled = i === all.length - 1;
				}
			} );
			if ( countInput ) {
				countInput.value = String( all.length );
			}
		}

		function showLimit( show ) {
			if ( limitMsg ) {
				limitMsg.textContent = show ? S.limit || '' : '';
				limitMsg.hidden = ! show;
			}
		}

		function setRemoved( row, removed ) {
			row.classList.toggle( 'is-removed', removed );
			row.querySelectorAll( 'input' ).forEach( ( el ) => {
				el.disabled = removed;
			} );
			const bar = row.querySelector( '.hs-removed-bar' );
			if ( bar ) {
				bar.hidden = ! removed;
			}
			refresh();
			markDirty();
			const focusTarget = row.querySelector( removed ? '.hs-restore-row' : '.hs-remove-row' );
			if ( focusTarget ) {
				focusTarget.focus();
			}
		}

		function flash( row ) {
			row.classList.remove( 'is-moved' );
			void row.offsetWidth;
			row.classList.add( 'is-moved' );
		}

		/* ── ردیف جدید ─────────────────────────────────────────────── */

		addBtn.addEventListener( 'click', () => {
			if ( activeRows().length >= cfg.maxItems ) {
				showLimit( true );
				return;
			}
			showLimit( false );
			const row = template.content.firstElementChild.cloneNode( true );
			list.insertBefore( row, list.firstChild );
			refresh();
			markDirty();
			row.querySelector( '.hs-title-input' )?.focus();
		} );

		/* ── کلیک‌ها: حذف/بازگردانی، کاور، بالا/پایین ─────────────────── */

		list.addEventListener( 'click', ( e ) => {
			const removeBtn = e.target.closest( '.hs-remove-row' );
			if ( removeBtn ) {
				setRemoved( removeBtn.closest( '.hodima-story-row' ), true );
				showLimit( false );
				return;
			}

			const restoreBtn = e.target.closest( '.hs-restore-row' );
			if ( restoreBtn ) {
				if ( activeRows().length >= cfg.maxItems ) {
					showLimit( true );
					return;
				}
				setRemoved( restoreBtn.closest( '.hodima-story-row' ), false );
				return;
			}

			const move = e.target.closest( '.hs-move' );
			if ( move ) {
				const r = move.closest( '.hodima-story-row' );
				const isUp = move.classList.contains( 'hs-move-up' );
				// از روی ردیف حذف‌شده (فقط نوار بازگردانی) بپر؛ وگرنه کلیک ظاهرا کاری نمی‌کرد
				let sibling = isUp ? r.previousElementSibling : r.nextElementSibling;
				while ( sibling && sibling.classList.contains( 'is-removed' ) ) {
					sibling = isUp ? sibling.previousElementSibling : sibling.nextElementSibling;
				}
				if ( sibling ) {
					if ( isUp ) {
						list.insertBefore( r, sibling );
					} else {
						list.insertBefore( sibling, r );
					}
				}
				refresh();
				markDirty();
				flash( r );
				// فوکوس روی همان دکمه بماند تا بشود پشت سر هم جابه‌جا کرد
				const again = r.querySelector( isUp ? '.hs-move-up' : '.hs-move-down' );
				if ( again && ! again.disabled ) {
					again.focus();
				} else {
					move.blur();
				}
				return;
			}

			const selectBtn = e.target.closest( '.hs-select-cover' );
			if ( selectBtn ) {
				e.preventDefault();
				if ( typeof wp === 'undefined' || ! wp.media ) {
					return;
				}

				const wrap = selectBtn.closest( '.hdn-media-container' );
				currentImg = wrap.querySelector( '.hs-cover-preview' );
				currentInput = wrap.querySelector( '.hs-cover-id' );

				if ( ! frame ) {
					frame = wp.media( {
						title: cfg.mediaTitle,
						button: { text: cfg.mediaBtn },
						library: { type: 'image' }, // ویدیو به عنوان کاور رد می‌شد و کاور گم می‌شد
						multiple: false,
					} );
					frame.on( 'select', () => {
						const a = frame.state().get( 'selection' ).first().toJSON();
						if ( currentImg ) {
							currentImg.src = a.sizes?.thumbnail?.url || a.url || currentImg.src;
						}
						if ( currentInput ) {
							currentInput.value = a.id || '';
						}
						markDirty();
					} );
				}
				frame.open();
			}
		} );

		/* ── کشیدن و رها کردن (Pointer Events: موس، لمس، قلم) ───────── */

		list.addEventListener( 'pointerdown', ( e ) => {
			const handle = e.target.closest( '.hs-drag-handle' );
			if ( ! handle || e.button > 0 ) {
				return;
			}

			const row = handle.closest( '.hodima-story-row' );
			const rect = row.getBoundingClientRect();

			const placeholder = document.createElement( 'div' );
			placeholder.className = 'hs-drop-placeholder';
			placeholder.style.height = `${ rect.height }px`;

			row.parentNode.insertBefore( placeholder, row.nextSibling );

			row.classList.add( 'is-dragging' );
			row.style.width = `${ rect.width }px`;
			row.style.top = `${ rect.top }px`;
			row.style.left = `${ rect.left }px`;

			drag = { row, placeholder, offsetY: e.clientY - rect.top, pointerId: e.pointerId };

			handle.setPointerCapture( e.pointerId );
			e.preventDefault();
		} );

		list.addEventListener( 'pointermove', ( e ) => {
			if ( ! drag || e.pointerId !== drag.pointerId ) {
				return;
			}

			drag.row.style.top = `${ e.clientY - drag.offsetY }px`;

			// نزدیک لبه‌های پنجره: اسکرول خودکار
			if ( e.clientY < 60 ) {
				window.scrollBy( 0, -12 );
			} else if ( e.clientY > window.innerHeight - 60 ) {
				window.scrollBy( 0, 12 );
			}

			const target = rows().find( ( r ) => {
				if ( r === drag.row ) {
					return false;
				}
				const b = r.getBoundingClientRect();
				return e.clientY < b.top + b.height / 2;
			} );

			if ( target ) {
				list.insertBefore( drag.placeholder, target );
			} else {
				list.appendChild( drag.placeholder );
			}
		} );

		function endDrag( e ) {
			if ( ! drag || ( e && e.pointerId !== drag.pointerId ) ) {
				return;
			}

			list.insertBefore( drag.row, drag.placeholder );
			drag.placeholder.remove();

			drag.row.classList.remove( 'is-dragging' );
			drag.row.style.width = '';
			drag.row.style.top = '';
			drag.row.style.left = '';

			flash( drag.row );
			drag = null;

			refresh();
			markDirty();
		}

		list.addEventListener( 'pointerup', endDrag );
		list.addEventListener( 'pointercancel', endDrag );

		refresh();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
