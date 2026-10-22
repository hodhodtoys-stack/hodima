/**
 * کادر «تنظیمات ویدیو» در ویرایش ویدیو (پیشخوان) — نشانه‌گذاری: meta-boxes.php
 *
 * استاندارد طراحی پیشخوان (اسکیل hodima-admin-ui؛ HODIMA-AUDIT.md بخش ۲۲۰):
 *   - خلاصه زنده بالای کادر + هشدار کمبودها (بدون آدرس ویدیو، بدون کاور…)
 *   - وضعیت آدرس ویدیو، پیش‌نمایش کاور، شمارش متن کامل
 *   - «آنچه گوگل می‌بیند»: بخش‌ها همان‌طور که سرور می‌خواند (hod_video_parse_chapters)
 *   - کارت‌های مرتبط: افزودن، جابه‌جایی، حذف با بازگردانی (بدون alert/confirm)
 * فقط ظاهر و کمک است؛ نام و مقدار فیلدها همان است و بدون JS فرم کار می‌کند.
 * زیر ESLint بدون baseline.
 */
( () => {
	'use strict';

	const FA_DIGITS = '۰۱۲۳۴۵۶۷۸۹';
	const latin = ( s ) => String( s ).replace( /[۰-۹]/g, ( d ) => String( FA_DIGITS.indexOf( d ) ) );
	const faNum = ( n ) => new Intl.NumberFormat( 'fa-IR' ).format( n );
	const fire = ( input ) => {
		input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
		input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
	};

	/** «۱:۲۵» یا «۱:۰۲:۱۰» → ثانیه، یا null (همان hod_video_time_to_seconds سرور). */
	const clockToSeconds = ( value ) => {
		const m = latin( value ).trim().match( /^(?:(\d{1,2}):)?(\d{1,3}):(\d{2})$/ );
		return m ? ( Number( m[ 1 ] || 0 ) * 3600 ) + ( Number( m[ 2 ] ) * 60 ) + Number( m[ 3 ] ) : null;
	};

	/** مدت (ISO، ساعت:دقیقه:ثانیه، یا فقط ثانیه) → ثانیه، یا null. */
	const durationSeconds = ( value ) => {
		const v = latin( value ).trim().toUpperCase();
		if ( '' === v ) {
			return null;
		}
		const iso = v.match( /^PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)(?:\.\d+)?S)?$/ );
		if ( iso && v.length > 2 ) {
			return ( Number( iso[ 1 ] || 0 ) * 3600 ) + ( Number( iso[ 2 ] || 0 ) * 60 ) + Number( iso[ 3 ] || 0 );
		}
		if ( /^\d+$/.test( v ) ) {
			return Number( v );
		}
		const m = v.match( /^(\d{1,2}):(\d{1,2}):(\d{1,2})$/ ) || v.match( /^()(\d{1,3}):(\d{1,2})$/ );
		return m ? ( Number( m[ 1 ] || 0 ) * 3600 ) + ( Number( m[ 2 ] ) * 60 ) + Number( m[ 3 ] ) : null;
	};

	const clock = ( s ) => {
		const h = Math.floor( s / 3600 );
		const m = Math.floor( ( s % 3600 ) / 60 );
		const ss = String( s % 60 ).padStart( 2, '0' );
		return h > 0 ? `${ h }:${ String( m ).padStart( 2, '0' ) }:${ ss }` : `${ m }:${ ss }`;
	};

	/** بخش‌ها مثل hod_video_parse_chapters سرور: زمان تکراری آخری را نگه می‌دارد. */
	const parseChapters = ( text ) => {
		const items = new Map();
		const bad = [];
		text.split( /\r?\n/ ).forEach( ( raw, i ) => {
			const line = raw.trim();
			if ( '' === line ) {
				return;
			}
			const m = line.match( /^([۰-۹0-9:]+)\s*[-–—|:]?\s*(.+)$/u );
			const start = m ? clockToSeconds( m[ 1 ] ) : null;
			const name = m ? m[ 2 ].replace( /<[^>]*>/g, '' ).trim() : '';
			if ( null === start || '' === name ) {
				bad.push( { line: i + 1, text: line } );
				return;
			}
			items.set( start, { start, name } );
		} );
		return { items: [ ...items.values() ].sort( ( a, b ) => a.start - b.start ), bad };
	};

	const el = ( tag, className, text ) => {
		const node = document.createElement( tag );
		if ( className ) {
			node.className = className;
		}
		if ( undefined !== text ) {
			node.textContent = text;
		}
		return node;
	};

	/** انتخاب عکس از کتابخانه رسانه وردپرس؛ بدون wp.media (ابزار تست) کاری نمی‌کند. */
	const pickImage = ( onSelect ) => {
		if ( typeof wp === 'undefined' || ! wp.media ) {
			return;
		}
		const frame = wp.media( {
			title: 'انتخاب تصویر',
			button: { text: 'استفاده از این تصویر' },
			library: { type: 'image' },
			multiple: false,
		} );
		frame.on( 'select', () => onSelect( frame.state().get( 'selection' ).first().toJSON().url ) );
		frame.open();
	};

	/** پیش‌نمایش عکس از فیلد آدرس. */
	const syncThumb = ( input, img, box ) => {
		const url = input.value.trim();
		const ok = /^https?:\/\//i.test( url );
		if ( ok ) {
			img.src = url;
		} else {
			img.removeAttribute( 'src' );
		}
		img.hidden = ! ok;
		box?.classList.toggle( 'is-empty', ! ok );
		box?.classList.toggle( 'is-no-cover', ! ok );
	};

	const init = ( root ) => {
		const $ = ( s ) => root.querySelector( s );
		const urlInput = $( '#hod_video_url' );
		const urlStatus = $( '[data-hvw-url-status]' );
		const coverBox = $( '[data-hvw-cover]' );
		const coverInput = $( '[data-hvw-cover-input]' );
		const coverImg = coverBox?.querySelector( '[data-hvw-thumb]' );
		const duration = $( '#hod_video_duration' );
		const chaptersInput = $( '#hod_video_chapters' );
		const chaptersBox = $( '[data-hvw-chapters]' );
		const chaptersList = $( '[data-hvw-chapters-list]' );
		const chaptersStatus = $( '[data-hvw-chapters-status]' );
		const transcript = $( '#hod_video_transcript' );
		const transcriptCount = $( '[data-hvw-transcript-count]' );
		const product = $( '#hod_related_product_url' );
		const summary = $( '[data-hvw-summary]' );
		const summaryText = $( '[data-hvw-summary-text]' );
		const summaryWarn = $( '[data-hvw-summary-warn]' );
		const related = $( '[data-hvw-related]' );
		const rows = $( '[data-hvw-rows]' );
		const template = $( '[data-hvw-row-template]' );
		const addBtn = $( '[data-hvw-add]' );
		const empty = $( '[data-hvw-empty]' );
		const undoBox = $( '[data-hvw-undo]' );
		const countPill = $( '[data-hvw-related-count]' );
		const max = Number( related?.dataset.hvwMax || 99 );

		/* ── آدرس ویدیو ── */
		const urlState = () => {
			const url = urlInput.value.trim();
			if ( '' === url ) {
				return { level: 'warn', text: 'بدون آدرس، به‌جای پلیر «ویدیو هنوز آماده نیست» نمایش داده می‌شود.' };
			}
			if ( ! /^https?:\/\//i.test( url ) ) {
				return { level: 'warn', text: 'آدرس باید با https:// شروع شود.' };
			}
			const ext = ( url.split( /[?#]/ )[ 0 ].match( /\.([a-z0-9]+)$/i ) || [] )[ 1 ];
			if ( ext && ! [ 'mp4', 'm4v', 'webm', 'mov', 'ogv' ].includes( ext.toLowerCase() ) ) {
				return { level: 'warn', text: `پسوند فایل «${ ext }» است؛ پلیر فایل MP4 را پخش می‌کند.` };
			}
			return { level: 'ok', text: 'آدرس درست است.' };
		};

		/* ── بخش‌ها ── */
		const renderChapters = () => {
			const text = chaptersInput.value;
			chaptersBox.hidden = '' === text.trim();
			if ( chaptersBox.hidden ) {
				return { count: 0, bad: 0 };
			}
			const total = durationSeconds( duration.value );
			const { items, bad } = parseChapters( text );
			chaptersList.replaceChildren();
			// مثل سازنده اسکیما (inc/video-object.php): بخش بعد از پایان ویدیو کنار می‌رود و پایان بخش قبلی نمی‌شود
			const inside = items.filter( ( item ) => ! total || item.start < total );
			let late = 0;
			items.forEach( ( item ) => {
				const next = inside[ inside.indexOf( item ) + 1 ];
				const end = inside.includes( item ) ? ( next?.start ?? total ) : item.start;
				const li = el( 'li', 'hvw-chapters__item' );
				li.append( el( 'span', 'hvw-chapters__time', clock( item.start ) ), el( 'span', 'hvw-chapters__name', item.name ) );
				if ( null !== end && end > 0 && end <= item.start ) {
					late++;
					li.classList.add( 'is-bad' );
					li.append( el( 'span', 'hvw-chapters__end', 'بعد از پایان ویدیو؛ نادیده گرفته می‌شود' ) );
				} else if ( null !== end && end > 0 ) {
					li.append( el( 'span', 'hvw-chapters__end', `تا ${ clock( end ) }` ) );
				}
				chaptersList.append( li );
			} );
			const parts = [ items.length ? `${ faNum( items.length - late ) } بخش شناخته شد.` : 'هنوز بخشی شناخته نشد.' ];
			bad.slice( 0, 3 ).forEach( ( b ) => parts.push( `خط ${ faNum( b.line ) } خوانده نشد («${ b.text.slice( 0, 30 ) }»): اول زمان مثل 1:25 بعد عنوان.` ) );
			chaptersStatus.textContent = parts.join( ' ' );
			chaptersStatus.classList.toggle( 'is-warn', bad.length > 0 || late > 0 );
			return { count: items.length - late, bad: bad.length + late };
		};

		/* ── متن کامل ── */
		const transcriptStats = () => {
			const text = transcript.value.trim();
			const words = text ? text.split( /\s+/u ).length : 0;
			const paras = text ? text.split( /\n\s*\n/ ).filter( ( p ) => p.trim() ).length : 0;
			transcriptCount.textContent = words ? `${ faNum( words ) } کلمه · ${ faNum( paras ) } پاراگراف` : 'خالی: بخش «متن کامل ویدیو» در صفحه نمایش داده نمی‌شود.';
			return words;
		};

		/* ── کارت‌های مرتبط ── */
		const rowEls = () => [ ...rows.querySelectorAll( ':scope > [data-hvw-row]' ) ];

		/** نام، شناسه، برچسب و aria-label هر کارت به ترتیب فعلی (ذخیره همان ترتیب آرایه است). */
		const reindex = () => {
			const list = rowEls();
			list.forEach( ( row, i ) => {
				const n = faNum( i + 1 );
				row.querySelectorAll( '[name^="hod_related_links["]' ).forEach( ( input ) => {
					input.name = input.name.replace( /^hod_related_links\[[^\]]*\]/, `hod_related_links[${ i }]` );
				} );
				row.querySelectorAll( '[id^="hvw-link-"]' ).forEach( ( node ) => {
					node.id = node.id.replace( /^hvw-link-[^-]+-/, `hvw-link-${ i }-` );
				} );
				row.querySelectorAll( 'label[for^="hvw-link-"]' ).forEach( ( label ) => {
					label.htmlFor = label.htmlFor.replace( /^hvw-link-[^-]+-/, `hvw-link-${ i }-` );
				} );
				row.querySelectorAll( '[data-hvw-n]' ).forEach( ( node ) => {
					node.textContent = n;
				} );
				row.querySelectorAll( '[data-hvw-label]' ).forEach( ( btn ) => {
					btn.setAttribute( 'aria-label', btn.dataset.hvwLabel.replace( '%s', n ) );
				} );
				row.querySelector( '[data-hvw-move="-1"]' ).disabled = 0 === i;
				row.querySelector( '[data-hvw-move="1"]' ).disabled = i === list.length - 1;
			} );
			empty.hidden = list.length > 0;
			countPill.textContent = `${ faNum( list.length ) } کارت`;
			addBtn.disabled = list.length >= max;
			addBtn.title = addBtn.disabled ? `حداکثر ${ faNum( max ) } کارت` : '';
		};

		const bindRow = ( row ) => {
			const cover = row.querySelector( '[data-hvw-row-cover]' );
			const img = row.querySelector( '[data-hvw-thumb]' );
			cover.addEventListener( 'input', () => syncThumb( cover, img, row ) );
		};

		const addRow = () => {
			if ( rowEls().length >= max ) {
				return;
			}
			const fragment = template.content.cloneNode( true );
			const row = fragment.querySelector( '[data-hvw-row]' );
			rows.append( row );
			bindRow( row );
			reindex();
			undoBox.replaceChildren();
			row.querySelector( '[data-hvw-row-title]' ).focus();
			update();
		};

		const removeRow = ( row ) => {
			const list = rowEls();
			const index = list.indexOf( row );
			const title = row.querySelector( '[data-hvw-row-title]' ).value.trim() || `کارت ${ faNum( index + 1 ) }`;
			row.remove();
			reindex();
			update();

			// بازگردانی به‌جای پنجره تأیید: کارت تا ذخیره نوشته از دست نرفته است
			const undo = el( 'button', 'button-link hvw-undo-btn', 'بازگردانی' );
			undo.type = 'button';
			undo.addEventListener( 'click', () => {
				rows.insertBefore( row, rowEls()[ index ] || null );
				undoBox.replaceChildren();
				reindex();
				update();
				row.querySelector( '[data-hvw-row-title]' ).focus();
			} );
			undoBox.replaceChildren( el( 'span', '', `«${ title }» حذف شد. ` ), undo );
			undo.focus();
		};

		const moveRow = ( row, dir ) => {
			const list = rowEls();
			const i = list.indexOf( row );
			const j = i + dir;
			if ( j < 0 || j >= list.length ) {
				return;
			}
			if ( dir < 0 ) {
				rows.insertBefore( row, list[ j ] );
			} else {
				rows.insertBefore( list[ j ], row );
			}
			reindex();
			// فوکوس روی همان دکمه بماند تا چند بار پشت سر هم جابه‌جا شود (اگر هنوز فعال است)
			const same = row.querySelector( `[data-hvw-move="${ dir }"]` );
			( same.disabled ? row.querySelector( `[data-hvw-move="${ -dir }"]` ) : same ).focus();
		};

		/* ── خلاصه ── */
		const update = () => {
			const parts = [];
			const warns = [];
			const url = urlState();

			urlStatus.textContent = url.text;
			urlStatus.classList.toggle( 'is-ok', 'ok' === url.level );
			urlStatus.classList.toggle( 'is-warn', 'warn' === url.level );

			const hasCover = /^https?:\/\//i.test( coverInput.value.trim() );
			const secs = durationSeconds( duration.value );
			const ch = renderChapters();
			const words = transcriptStats();
			const hasProduct = '' !== product.value.trim();
			const cards = rowEls();
			const noCover = cards.filter( ( r ) => ! /^https?:\/\//i.test( r.querySelector( '[data-hvw-row-cover]' ).value.trim() ) );
			const incomplete = cards.filter( ( r ) => ! r.querySelector( '[data-hvw-row-title]' ).value.trim() || ! r.querySelector( 'input[type="url"]' ).value.trim() );

			if ( 'ok' === url.level ) {
				parts.push( 'ویدیو آماده نمایش است' );
			} else {
				parts.push( 'ویدیو هنوز آماده نمایش نیست' );
				warns.push( url.text );
			}
			parts.push( hasCover ? 'کاور دارد' : 'کاور ندارد' );
			if ( ! hasCover ) {
				warns.push( 'بدون کاور، گوگل این ویدیو را در نتایج ویدیویی نشان نمی‌دهد.' );
			}
			if ( null !== secs && secs > 0 ) {
				parts.push( `${ clock( secs ) } ${ secs >= 3600 ? 'ساعت' : 'دقیقه' }${ ch.count ? ` با ${ faNum( ch.count ) } لحظه کلیدی برای گوگل` : '' }` );
			} else if ( '' !== duration.value.trim() ) {
				warns.push( 'مدت ویدیو خوانده نشد؛ مثل ۲:۳۵ بنویسید.' );
			}
			if ( ch.bad ) {
				warns.push( `${ faNum( ch.bad ) } خط از بخش‌ها نادیده گرفته می‌شود.` );
			}
			parts.push( words ? 'متن کامل زیر پلیر دیده می‌شود' : 'متن کامل ندارد' );
			parts.push( hasProduct ? 'دکمه «مشاهده محصول» روشن است' : 'دکمه «مشاهده محصول» نمایش داده نمی‌شود' );
			parts.push( cards.length ? `${ faNum( cards.length ) } کارت مرتبط زیر ویدیو می‌آید` : 'کارت مرتبطی زیر ویدیو نیست' );
			if ( incomplete.length ) {
				warns.push( `${ faNum( incomplete.length ) } کارت عنوان یا لینک ندارد و ذخیره نمی‌شود.` );
			}
			if ( noCover.length ) {
				warns.push( `${ faNum( noCover.length ) } کارت عکس ندارد (جای عکس خالی می‌ماند).` );
			}

			summaryText.textContent = `${ parts.join( '، ' ) }.`;
			summaryWarn.replaceChildren( ...warns.map( ( w ) => el( 'span', 'hd-pill hd-pill--warn', w ) ) );
			summary.hidden = false;
		};

		/* ── رویدادها ── */
		root.addEventListener( 'input', update );
		root.addEventListener( 'change', update );

		coverInput.addEventListener( 'input', () => syncThumb( coverInput, coverImg, coverBox ) );
		$( '[data-hvw-cover] [data-hvw-pick]' )?.addEventListener( 'click', () => pickImage( ( url ) => {
			coverInput.value = url;
			fire( coverInput );
		} ) );
		$( '[data-hvw-clear]' )?.addEventListener( 'click', () => {
			coverInput.value = '';
			fire( coverInput );
			coverInput.focus();
		} );

		rowEls().forEach( bindRow );
		addBtn.addEventListener( 'click', addRow );
		rows.addEventListener( 'click', ( e ) => {
			const btn = e.target.closest( 'button' );
			const row = btn?.closest( '[data-hvw-row]' );
			if ( ! row ) {
				return;
			}
			if ( btn.hasAttribute( 'data-hvw-remove' ) ) {
				removeRow( row );
			} else if ( btn.hasAttribute( 'data-hvw-move' ) ) {
				moveRow( row, Number( btn.dataset.hvwMove ) );
			} else if ( btn.hasAttribute( 'data-hvw-pick' ) ) {
				const cover = row.querySelector( '[data-hvw-row-cover]' );
				pickImage( ( url ) => {
					cover.value = url;
					fire( cover );
				} );
			}
		} );

		reindex();
		update();
	};

	const boot = () => document.querySelectorAll( '[data-hvw]' ).forEach( init );

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
