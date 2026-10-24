/**
 * کادر «تنظیمات ویدیو» در ویرایش ویدیو (پیشخوان) — نشانه‌گذاری: meta-boxes.php
 *
 * استاندارد طراحی پیشخوان، نوع «ورود محتوا» (اسکیل hodima-admin-ui؛ HODIMA-AUDIT.md بخش ۲۲۴):
 *   - نوار وضعیت (یک جمله + نشان «N مورد برای بررسی»)، چک‌لیست آمادگی، پیش‌نمایش کارت ویدیو
 *   - وضعیت آدرس ویدیو، شمارش متن کامل، شمارنده روی تب «زیر ویدیو»
 *   - «آنچه گوگل می‌بیند»: بخش‌ها همان‌طور که سرور می‌خواند (hod_video_parse_chapters)
 * تب‌ها، انتخاب‌گر کاور و لیست کارت‌های مرتبط (افزودن، جابه‌جایی، حذف با بازگردانی)
 * رفتارشان در video-watch.ui.js (کیت محلی همین ماژول) است؛ اینجا فقط به رویدادهایشان گوش می‌دهیم.
 * فقط ظاهر و کمک است؛ نام و مقدار فیلدها همان است و بدون JS فرم کار می‌کند.
 * زیر ESLint بدون baseline.
 */
( () => {
	'use strict';

	const FA_DIGITS = '۰۱۲۳۴۵۶۷۸۹';
	const latin = ( s ) => String( s ).replace( /[۰-۹]/g, ( d ) => String( FA_DIGITS.indexOf( d ) ) );
	const faNum = ( n ) => new Intl.NumberFormat( 'fa-IR' ).format( n );

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

	/** ردیف‌های چک‌لیست: [کلید، حالت، برچسب، توضیح]. */
	const STATES = { ok: 'انجام شد', warn: 'لازم', opt: 'اختیاری' };

	const init = ( root ) => {
		const $ = ( s ) => root.querySelector( s );
		const urlInput = $( '#hod_video_url' );
		const urlStatus = $( '[data-hvw-url-status]' );
		const coverInput = $( '#hod_video_thumbnail' );
		const duration = $( '#hod_video_duration' );
		const chaptersInput = $( '#hod_video_chapters' );
		const chaptersBox = $( '[data-hvw-chapters]' );
		const chaptersList = $( '[data-hvw-chapters-list]' );
		const chaptersStatus = $( '[data-hvw-chapters-status]' );
		const transcript = $( '#hod_video_transcript' );
		const transcriptCount = $( '[data-hvw-transcript-count]' );
		const product = $( '#hod_related_product_url' );
		const summary = $( '[data-hvw-summary]' );
		const pill = $( '[data-hvw-pill]' );
		const pillWrap = $( '[data-hvw-pill-wrap]' );
		const tabCount = $( '[data-hvw-tab-count]' );
		const side = root.querySelectorAll( '[data-hvw-pv], [data-hvw-ck]' );
		const pvImg = $( '[data-hvw-pv-img]' );
		const pvDur = $( '[data-hvw-pv-dur]' );
		const pvChips = $( '[data-hvw-pv-chips]' );
		const pvProduct = $( '[data-hvw-pv-product]' );
		const rows = $( '[data-hd-rows]' );

		/* ── آدرس ویدیو ── */
		const urlState = () => {
			const url = urlInput.value.trim();
			if ( '' === url ) {
				return { level: 'warn', empty: true, text: 'بدون آدرس، به‌جای پلیر «ویدیو هنوز آماده نیست» نمایش داده می‌شود.' };
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
			chaptersStatus.dataset.level = bad.length > 0 || late > 0 ? 'warn' : 'ok';
			return { count: items.length - late, bad: bad.length + late };
		};

		/* ── متن کامل ── */
		const transcriptStats = () => {
			const text = transcript.value.trim();
			const words = text ? text.split( /\s+/u ).length : 0;
			const paras = text ? text.split( /\n\s*\n/ ).filter( ( p ) => p.trim() ).length : 0;
			transcriptCount.textContent = words ? ` · ${ faNum( words ) } کلمه · ${ faNum( paras ) } پاراگراف` : ' · خالی';
			return words;
		};

		/* ── چک‌لیست ── */
		const setCheck = ( key, state, tag, note = '' ) => {
			const item = $( `[data-hvw-check="${ key }"]` );
			if ( ! item ) {
				return;
			}
			item.dataset.state = state;
			item.querySelector( '.hd-checklist__tag' ).textContent = tag || STATES[ state ];
			item.querySelector( '.hd-checklist__note' ).textContent = note;
		};

		/* ── به‌روزرسانی همه‌چیز ── */
		const update = () => {
			const url = urlState();
			urlStatus.textContent = url.text;
			urlStatus.dataset.level = url.level;

			const coverUrl = coverInput.value.trim();
			const hasCover = /^https?:\/\//i.test( coverUrl );
			const secs = durationSeconds( duration.value );
			const ch = renderChapters();
			const words = transcriptStats();
			const hasProduct = '' !== product.value.trim();
			const cards = [ ...rows.querySelectorAll( ':scope > [data-hd-row]' ) ];
			const incomplete = cards.filter( ( r ) => ! r.querySelector( '[data-hd-row-title]' ).value.trim() || ! r.querySelector( 'input[type="url"]' ).value.trim() );
			const noCover = cards.filter( ( r ) => ! /^https?:\/\//i.test( r.querySelector( '[data-hd-media-input]' ).value.trim() ) );
			cards.forEach( ( r ) => r.classList.toggle( 'is-warn', incomplete.includes( r ) ) );

			let warns = 0;
			const flag = ( key, state, tag, note ) => {
				if ( 'warn' === state ) {
					warns++;
				}
				setCheck( key, state, tag, note );
			};
			if ( 'ok' === url.level ) {
				flag( 'url', 'ok' );
			} else {
				flag( 'url', 'warn', url.empty ? 'لازم' : 'بررسی کنید', url.text );
			}
			flag( 'cover', hasCover ? 'ok' : 'warn', hasCover ? '' : 'لازم', hasCover ? '' : 'بدون کاور، گوگل این ویدیو را در نتایج ویدیویی نشان نمی‌دهد.' );
			if ( null !== secs && secs > 0 ) {
				flag( 'duration', 'ok' );
			} else if ( '' !== duration.value.trim() ) {
				flag( 'duration', 'warn', 'بررسی کنید', 'مدت ویدیو خوانده نشد؛ مثل ۲:۳۵ بنویسید.' );
			} else {
				flag( 'duration', 'opt', 'پیشنهادی' );
			}
			if ( ch.bad ) {
				flag( 'chapters', 'warn', 'بررسی کنید', `${ faNum( ch.bad ) } خط از بخش‌ها نادیده گرفته می‌شود.` );
			} else {
				flag( 'chapters', ch.count ? 'ok' : 'opt' );
			}
			flag( 'transcript', words ? 'ok' : 'opt' );
			flag( 'product', hasProduct ? 'ok' : 'opt' );
			if ( incomplete.length ) {
				flag( 'related', 'warn', 'بررسی کنید', `${ faNum( incomplete.length ) } کارت عنوان یا لینک ندارد و ذخیره نمی‌شود.` );
			} else {
				flag( 'related', cards.length ? 'ok' : 'opt', '', noCover.length ? `${ faNum( noCover.length ) } کارت عکس ندارد (جای عکس خالی می‌ماند).` : '' );
			}

			// نوار وضعیت: یک جمله ساده
			const parts = [ 'ok' === url.level ? 'ویدیو آماده نمایش است' : 'ویدیو هنوز آماده نمایش نیست' ];
			if ( null !== secs && secs > 0 ) {
				parts.push( `${ clock( secs ) } ${ secs >= 3600 ? 'ساعت' : 'دقیقه' }${ ch.count ? ` با ${ faNum( ch.count ) } لحظه کلیدی` : '' }` );
			}
			if ( ! hasCover ) {
				parts.push( 'کاور ندارد' );
			}
			summary.textContent = `${ parts.join( '، ' ) }.`;
			summary.hidden = false;
			pill.textContent = warns ? `${ faNum( warns ) } مورد برای بررسی` : 'آماده نمایش';
			pill.className = `hd-pill ${ warns ? 'hd-pill--warn' : 'hd-pill--ok' }`;
			pillWrap.hidden = false;

			// شمارنده تب «زیر ویدیو»
			tabCount.textContent = faNum( cards.length );
			tabCount.hidden = 0 === cards.length;

			// پیش‌نمایش
			pvImg.hidden = ! hasCover;
			if ( hasCover ) {
				pvImg.src = coverUrl;
			} else {
				pvImg.removeAttribute( 'src' );
			}
			pvDur.hidden = ! ( secs > 0 );
			pvDur.textContent = secs > 0 ? clock( secs ) : '';
			pvChips.replaceChildren();
			if ( ch.count ) {
				pvChips.append( el( 'span', 'hvw-pv__chip', `${ faNum( ch.count ) } لحظه کلیدی` ) );
			}
			if ( words ) {
				pvChips.append( el( 'span', 'hvw-pv__chip', 'متن کامل' ) );
			}
			pvProduct.hidden = ! hasProduct;
			side.forEach( ( node ) => {
				node.hidden = false;
			} );
		};

		/* ── رویدادها (تغییر فیلد، لیست کارت‌ها، انتخاب کاور؛ همه حباب‌دار) ── */
		root.addEventListener( 'input', update );
		root.addEventListener( 'change', update );
		root.addEventListener( 'hd:repeat-change', update );

		update();
	};

	const boot = () => document.querySelectorAll( '[data-hvw]' ).forEach( init );

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
