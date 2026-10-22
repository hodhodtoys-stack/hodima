/**
 * کادر تنظیمات نوتیفیکیشن (پیشخوان) — نسخه ۴
 *
 * - انتخاب عکس از کتابخانه رسانه (آدرس + شناسه پیوست برای srcset)؛ عکس PNG
 *   حالت «پس‌زمینه شفاف» را روشن می‌کند.
 * - فیلدهای وابسته: رنگ کادر فقط بدون شفافیت، «پنجره وسط در موبایل» فقط برای
 *   پنجره وسط، فهرست آدرس‌ها فقط وقتی «همه آدرس‌ها» نیست.
 * - تاریخ شمسی کنار هر تاریخ (مقدار فیلد همیشه میلادی می‌ماند).
 * - پیش‌نمایش زنده با همان کلاس‌ها و CSS سایت؛ با DOM ساخته می‌شود (نسخه قبلی
 *   مقدار فیلدها را بدون escape در innerHTML می‌گذاشت).
 * - نسخه ۵: بخش‌ها در تب (فلش، Home/End؛ آخرین تب در همان نشست به خاطر سپرده
 *   می‌شود؛ فیلد نامعتبر هنگام ذخیره تب خودش را باز می‌کند) و پیش‌نمایش (تب
 *   آخر) یک صفحه مجازی ۱۲۸۰×۸۰۰ (موبایل ۳۹۰×۷۸۰) با اندازه‌های واقعی که کوچک
 *   می‌شود تا در عرض تب جا شود؛ با باز شدن تب انیمیشن دوباره اجرا می‌شود.
 * - نسخه ۶ (اسکیل hodima-admin-ui): خلاصه رفتار در یک جمله بالای تب‌ها (با
 *   همان منطق سایت: محرک‌ها «هر کدام زودتر»، تأخیر = مکث *بعد از* محرک)،
 *   حالت «یک بار / چند بار / بی‌محدودیت» برای دفعات نمایش (مقدار همان
 *   hd_notif_show_count)، و چند فیلد رادیویی شدند (value() رادیوی انتخاب‌شده
 *   را می‌خواند). کلید کارت‌ها و دکمه‌های +/− در admin-ui.js هسته‌اند.
 */
( () => {
	'use strict';

	const init = () => {
		const root = document.querySelector( '[data-hdn]' );
		if ( ! root ) {
			return;
		}
		/* ── تب‌ها ───────────────────────────────────────────────── */
		const tablist = root.querySelector( '[data-hdn-tabs]' );
		const tabs = tablist ? [ ...tablist.querySelectorAll( '[role="tab"]' ) ] : [];
		const panels = tabs.map( ( t ) => document.getElementById( t.getAttribute( 'aria-controls' ) ) );
		let onPreviewTab = null; // پایین‌تر، بعد از ساخت پیش‌نمایش
		const selectTab = ( index, focus = false ) => {
			tabs.forEach( ( tab, i ) => {
				const on = i === index;
				tab.setAttribute( 'aria-selected', String( on ) );
				tab.tabIndex = on ? 0 : -1;
				panels[ i ].hidden = ! on;
			} );
			if ( panels[ index ]?.querySelector( '[data-hdn-preview]' ) ) {
				onPreviewTab?.();
			}
			if ( focus ) {
				tabs[ index ].focus();
			}
			try {
				sessionStorage.setItem( 'hdn-tab', tabs[ index ].id );
			} catch {
				/* بدون حافظه */
			}
		};
		if ( tabs.length ) {
			tablist.hidden = false;
			root.classList.add( 'hdn-has-tabs' );
			tabs.forEach( ( tab, i ) => tab.addEventListener( 'click', () => selectTab( i ) ) );
			// راست‌به‌چپ: ← تب بعدی، → تب قبلی
			tablist.addEventListener( 'keydown', ( e ) => {
				const i = tabs.indexOf( document.activeElement );
				const next = { ArrowLeft: i + 1, ArrowRight: i - 1, Home: 0, End: tabs.length - 1 }[ e.key ];
				if ( i < 0 || next === undefined ) {
					return;
				}
				e.preventDefault();
				selectTab( ( next + tabs.length ) % tabs.length, true );
			} );
			let start = 0;
			try {
				start = Math.max( 0, tabs.findIndex( ( t ) => t.id === sessionStorage.getItem( 'hdn-tab' ) ) );
			} catch {
				/* بدون حافظه */
			}
			selectTab( start );
			// فیلد نامعتبر در تب پنهان: مرورگر نمی‌تواند نشانش دهد؛ تب خودش باز شود
			root.addEventListener( 'invalid', ( e ) => {
				const panel = e.target.closest( '[data-hdn-panel]' );
				const index = panels.indexOf( panel );
				if ( index >= 0 && panel.hidden ) {
					selectTab( index );
				}
			}, true );
		}

		const field = ( name ) => root.querySelector( `[name="${ name }"]` );
		// رادیو: مقدار گزینه انتخاب‌شده (چند فیلد از select به دکمه‌های کنار هم رسیدند)
		const value = ( name ) => {
			for ( const input of root.querySelectorAll( `[name="${ name }"]` ) ) {
				if ( input.type !== 'radio' || input.checked ) {
					return input.value;
				}
			}
			return '';
		};
		const checked = ( name ) => !! field( name )?.checked;

		/* ── عکس ─────────────────────────────────────────────────── */
		const media = root.querySelector( '[data-hdn-media]' );
		const urlInput = root.querySelector( '[data-hdn-image-url]' );
		const idInput = root.querySelector( '[data-hdn-image-id]' );
		const thumb = root.querySelector( '[data-hdn-thumb]' );
		const transparent = root.querySelector( '[data-hdn-transparent]' );
		let frame = null;

		const setImage = ( url, id, preview ) => {
			urlInput.value = url;
			idInput.value = id ? String( id ) : '0';
			if ( preview ) {
				thumb.src = preview;
			} else {
				thumb.removeAttribute( 'src' );
			}
			media.classList.toggle( 'is-empty', ! url );
			root.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		};

		root.querySelector( '[data-hdn-pick]' )?.addEventListener( 'click', () => {
			if ( ! window.wp?.media ) {
				return;
			}
			frame = frame || wp.media( { title: 'انتخاب عکس پاپ‌آپ', button: { text: 'انتخاب' }, library: { type: 'image' }, multiple: false } );
			frame.off( 'select' ).on( 'select', () => {
				const a = frame.state().get( 'selection' ).first().toJSON();
				// PNG معمولا پس‌زمینه ندارد: بدون کادر سفید نمایش داده شود
				if ( a.subtype === 'png' && transparent ) {
					transparent.checked = true;
				}
				setImage( a.url, a.id, a.sizes?.medium?.url || a.url );
			} );
			frame.open();
		} );
		root.querySelector( '[data-hdn-remove]' )?.addEventListener( 'click', () => setImage( '', 0, '' ) );

		/* ── فیلدهای وابسته ───────────────────────────────────────── */
		const bgField = root.querySelector( '[data-hdn-bg-field]' );
		const mobileField = root.querySelector( '[data-hdn-mobile-field]' );
		const pagesField = root.querySelector( '[data-hdn-pages-field]' );
		// فیلد وابسته فقط وقتی معنا دارد دیده می‌شود (پنهان، نه disabled: مقدارش ذخیره بماند)
		const syncDependents = () => {
			const isTransparent = checked( 'hd_notif_bg_transparent' );
			if ( bgField ) {
				bgField.hidden = isTransparent;
			}
			if ( mobileField ) {
				mobileField.hidden = value( 'hd_notif_position' ) !== 'center';
			}
			if ( pagesField ) {
				pagesField.hidden = value( 'hd_notif_target_type' ) === 'all';
			}
		};

		/* ── دفعات نمایش: یک بار / چند بار / بی‌محدودیت ───────────────── */
		const countMode = root.querySelector( '[data-hdn-count-mode]' );
		const countField = root.querySelector( '[data-hdn-count-field]' );
		const countInput = field( 'hd_notif_show_count' );
		if ( countMode && countField && countInput ) {
			let lastSome = Number( countInput.value ) > 1 ? countInput.value : '3';
			const applyMode = ( mode ) => {
				countField.hidden = mode !== 'some';
				// پنهان با min=2 جلوی ذخیره را می‌گرفت (فیلد نامعتبر پنهان)
				countInput.min = mode === 'some' ? '2' : '0';
				if ( mode === 'once' ) {
					countInput.value = '1';
				} else if ( mode === 'all' ) {
					countInput.value = '0';
				} else if ( Number( countInput.value ) < 2 ) {
					countInput.value = lastSome;
				}
			};
			countMode.hidden = false;
			countMode.addEventListener( 'change', ( e ) => applyMode( e.target.value ) );
			countInput.addEventListener( 'input', () => {
				if ( Number( countInput.value ) > 1 ) {
					lastSome = countInput.value;
				}
			} );
			applyMode( value( 'hdn_count_mode' ) || 'all' );
		}

		/* ── تاریخ شمسی کنار تاریخ میلادی ─────────────────────────── */
		const el = ( tag, attrs = {}, ...children ) => {
			const node = document.createElement( tag );
			Object.entries( attrs ).forEach( ( [ k, v ] ) => node.setAttribute( k, String( v ) ) );
			node.append( ...children );
			return node;
		};
		const jalali = ( () => {
			try {
				return new Intl.DateTimeFormat( 'fa-IR-u-ca-persian', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' } );
			} catch {
				return null;
			}
		} )();
		// «دوشنبه ۳۰ آذر ۱۴۰۵» (ترتیب format() مرورگرها برای fa «۱۴۰۵ آذر ۳۰, دوشنبه» است؛ از اجزا)
		const jalaliText = ( ymd, weekday = false ) => {
			const date = new Date( `${ ymd }T12:00:00Z` );
			if ( ! jalali || Number.isNaN( date.getTime() ) ) {
				return ymd;
			}
			const part = Object.fromEntries( jalali.formatToParts( date ).map( ( p ) => [ p.type, p.value ] ) );
			return `${ weekday ? `${ part.weekday } ` : '' }${ part.day } ${ part.month } ${ part.year }`;
		};
		const syncDates = () => {
			root.querySelectorAll( '[data-hdn-date]' ).forEach( ( input ) => {
				const out = input.parentElement.querySelector( '[data-hdn-jalali]' );
				if ( ! out ) {
					return;
				}
				const date = input.value ? new Date( `${ input.value }T12:00:00Z` ) : null;
				if ( ! date || ! jalali || Number.isNaN( date.getTime() ) ) {
					out.textContent = 'خالی = بدون محدودیت';
					return;
				}
				out.textContent = `شمسی: ${ jalaliText( input.value, true ) }`;
			} );
		};

		/* ── خلاصه به زبان ساده (همان منطق notification-front.js) ─────── */
		const summary = root.querySelector( '[data-hdn-summary]' );
		const summaryText = root.querySelector( '[data-hdn-summary-text]' );
		const summaryWarn = root.querySelector( '[data-hdn-summary-warn]' );
		const triggerCount = root.querySelector( '[data-hdn-trigger-count]' );
		const fa = ( n ) => Number( n ).toLocaleString( 'fa-IR' );
		const num = ( name ) => Number( value( name ) ) || 0;
		const list = ( items ) => ( items.length < 2 ? items.join( '' ) : `${ items.slice( 0, -1 ).join( '، ' ) } یا ${ items.at( -1 ) }` );
		const lineCount = ( name ) => value( name ).split( '\n' ).filter( ( line ) => line.trim() ).length;

		const summarize = () => {
			const warn = [];
			if ( ! urlInput?.value ) {
				warn.push( 'بدون عکس در سایت نمایش داده نمی‌شود' );
			}
			const end = value( 'hd_notif_end_date' );
			if ( end && end < new Date().toISOString().slice( 0, 10 ) ) {
				warn.push( 'تاریخ پایان گذشته است' );
			}
			if ( ! checked( 'hd_notif_is_active' ) ) {
				return { text: 'خاموش است: ذخیره می‌ماند ولی در سایت به کسی نشان داده نمی‌شود.', warn, triggers: 0 };
			}

			// کی و کجا: محرک‌ها (هر کدام زودتر) و بعد مکث
			const triggers = [];
			if ( num( 'hd_notif_trigger_scroll' ) > 0 ) {
				triggers.push( `بعد از خواندن ${ fa( num( 'hd_notif_trigger_scroll' ) ) }٪ صفحه` );
			}
			if ( num( 'hd_notif_trigger_inactivity' ) > 0 ) {
				triggers.push( `بعد از ${ fa( num( 'hd_notif_trigger_inactivity' ) ) } ثانیه بی‌کاری` );
			}
			if ( checked( 'hd_notif_trigger_exit' ) ) {
				triggers.push( 'هنگام ترک صفحه در دسکتاپ' );
			}
			const delay = num( 'hd_notif_delay' );
			let when = triggers.length ? list( triggers ) + ( triggers.length > 1 ? ' (هر کدام زودتر)' : '' ) : 'همان لحظه باز شدن صفحه';
			if ( delay > 0 ) {
				when = triggers.length ? `${ when } و ${ fa( delay ) } ثانیه بعد از آن` : `${ fa( delay ) } ثانیه بعد از باز شدن صفحه`;
			}
			const position = value( 'hd_notif_position' ) || 'center';
			const place = { center: 'وسط صفحه', bottom_right: 'گوشه پایین راست', bottom_left: 'گوشه پایین چپ' }[ position ] || 'وسط صفحه';
			let first = `پاپ‌آپ ${ when }، ${ place }${ position === 'center' && value( 'hd_notif_mobile_mode' ) === 'corner' ? ' (در موبایل جعبه گوشه)' : '' } باز می‌شود`;
			if ( num( 'hd_notif_auto_close_time' ) > 0 ) {
				first += ` و بعد از ${ fa( num( 'hd_notif_auto_close_time' ) ) } ثانیه خودش بسته می‌شود`;
			}

			// به چه کسی و کجا
			const who = [];
			const pick = ( name, map ) => map[ value( name ) ] && who.push( map[ value( name ) ] );
			pick( 'hd_notif_device', { desktop: 'فقط دسکتاپ', mobile: 'فقط موبایل' } );
			pick( 'hd_notif_user_status', { logged_in: 'کاربران واردشده', logged_out: 'مهمان‌ها' } );
			pick( 'hd_notif_target_role', { customer: 'مشتری‌ها', subscriber: 'مشترک‌ها', administrator: 'مدیران' } );
			pick( 'hd_notif_visitor', { new: 'اولین بازدید از سایت', returning: 'بازدیدکننده‌های برگشتی' } );
			if ( num( 'hd_notif_min_pageviews' ) > 1 ) {
				who.push( `از صفحه ${ fa( num( 'hd_notif_min_pageviews' ) ) } بازدید به بعد` );
			}
			pick( 'hd_notif_target_post_type', { post: 'فقط مقاله‌ها', page: 'فقط برگه‌ها', product: 'فقط محصولات' } );
			const pages = lineCount( 'hd_notif_target_page' );
			if ( value( 'hd_notif_target_type' ) === 'specific' ) {
				who.push( pages ? `فقط ${ fa( pages ) } آدرس انتخاب‌شده` : 'فقط آدرس‌هایی که هنوز نوشته نشده' );
				if ( ! pages ) {
					warn.push( 'آدرسی برای «فقط این آدرس‌ها» نوشته نشده' );
				}
			} else if ( value( 'hd_notif_target_type' ) === 'exclude' && pages ) {
				who.push( `همه صفحه‌ها به‌جز ${ fa( pages ) } آدرس` );
			}
			const cats = root.querySelectorAll( '[name="hd_notif_target_product_cat[]"]:checked' ).length;
			if ( cats ) {
				who.push( `${ fa( cats ) } دسته محصول` );
			}
			if ( value( 'hd_notif_target_utm' ).trim() ) {
				who.push( `آمده از تبلیغ «${ value( 'hd_notif_target_utm' ).trim() }»` );
			}
			if ( value( 'hd_notif_target_referrer' ).trim() ) {
				who.push( `آمده از ${ value( 'hd_notif_target_referrer' ).trim() }` );
			}
			if ( checked( 'hd_notif_skip_search' ) ) {
				who.push( 'نه برای تازه‌واردِ جستجو' );
			}
			const second = who.length ? `مخاطب: ${ who.join( '، ' ) }` : 'به همه بازدیدکننده‌ها در همه صفحه‌ها نشان داده می‌شود';

			// چند بار
			const count = num( 'hd_notif_show_count' );
			const cooldown = num( 'hd_notif_cooldown_days' );
			let third = 0 === count ? 'تعداد نمایش محدود نیست' : `به هر نفر ${ 1 === count ? 'فقط یک بار' : `حداکثر ${ fa( count ) } بار` }`;
			third += cooldown > 0 ? `؛ بعد از بستن یا کلیک تا ${ fa( cooldown ) } روز دیده نمی‌شود` : '؛ بعد از بستن تا آخر همان بازدید دیده نمی‌شود';

			const start = value( 'hd_notif_start_date' );
			const dates = [ start && `از ${ jalaliText( start ) }`, end && `تا ${ jalaliText( end ) }` ].filter( Boolean ).join( ' ' );

			return { text: `${ [ first, second, third, dates ].filter( Boolean ).join( '. ' ) }.`, warn, triggers: triggers.length };
		};

		const renderSummary = () => {
			if ( ! summary ) {
				return;
			}
			const { text, warn, triggers } = summarize();
			summary.hidden = false;
			if ( summaryText.textContent !== text ) {
				summaryText.textContent = text;
			}
			summaryWarn.replaceChildren( ...warn.map( ( w ) => el( 'span', { class: 'hd-pill hd-pill--warn' }, w ) ) );
			if ( triggerCount ) {
				triggerCount.hidden = false;
				triggerCount.textContent = triggers ? `${ fa( triggers ) } محرک روشن` : 'بدون محرک';
			}
		};

		/* ── پیش‌نمایش ───────────────────────────────────────────── */
		const stage = root.querySelector( '[data-hdn-preview]' );
		const previewBox = root.querySelector( '[data-hdn-frame]' );
		const SCREENS = { desktop: [ 1280, 800 ], mobile: [ 390, 780 ] };
		// صفحه مجازی با اندازه واقعی، کوچک‌شده تا عرض ستون پر شود (موبایل حداکثر اندازه واقعی)
		const zoom = root.querySelector( '[data-hdn-zoom-dialog]' );
		const zoomBox = root.querySelector( '[data-hdn-zoom-frame]' );
		// قاب فعلی: ستون کنار فرم، یا پنجره بزرگ‌نمایی وقتی باز است
		const box = () => ( zoom?.open ? zoomBox : previewBox );
		const fit = () => {
			const frame = box();
			if ( ! stage || ! frame ) {
				return;
			}
			const [ w, h ] = SCREENS[ stage.dataset.device ] || SCREENS.desktop;
			// در پنجره بزرگ ارتفاع صفحه هم محدود است
			const maxH = zoom?.open ? window.innerHeight * 0.75 : Infinity;
			const scale = Math.min( 1, frame.clientWidth / w, maxH / h );
			stage.style.width = `${ w }px`;
			stage.style.height = `${ h }px`;
			stage.style.transform = `scale(${ scale })`;
			frame.style.height = `${ Math.round( h * scale ) }px`;
		};
		if ( previewBox && 'ResizeObserver' in window ) {
			const observer = new ResizeObserver( fit );
			observer.observe( previewBox );
			if ( zoomBox ) {
				observer.observe( zoomBox );
			}
		}
		// ستون باریک: نمای دسکتاپ خیلی کوچک می‌شود؛ پیش‌فرض موبایل
		if ( stage && previewBox && previewBox.clientWidth > 0 && previewBox.clientWidth < 480 ) {
			stage.dataset.device = 'mobile';
			root.querySelectorAll( '[data-hdn-device]' ).forEach( ( b ) => b.setAttribute( 'aria-pressed', String( b.dataset.hdnDevice === 'mobile' ) ) );
		}
		// بزرگ‌نمایی: همان صفحه مجازی به پنجره می‌رود و با بستن برمی‌گردد
		root.querySelector( '[data-hdn-zoom]' )?.addEventListener( 'click', () => {
			if ( typeof zoom?.showModal !== 'function' ) {
				return;
			}
			zoomBox.append( stage );
			zoom.showModal();
			// هدف بزرگ‌نمایی دیدن نمای دسکتاپ است؛ با بستن نمای قبلی برمی‌گردد
			zoom.dataset.device = stage.dataset.device;
			setDevice( 'desktop' );
		} );
		root.querySelector( '[data-hdn-zoom-close]' )?.addEventListener( 'click', () => zoom.close() );
		zoom?.addEventListener( 'close', () => {
			previewBox.append( stage );
			setDevice( zoom.dataset.device || stage.dataset.device );
			root.querySelector( '[data-hdn-zoom]' )?.focus();
		} );
		const renderPreview = ( animate = false ) => {
			if ( ! stage ) {
				return;
			}
			const mobile = stage.dataset.device === 'mobile';
			const position = value( 'hd_notif_position' ) || 'center';
			const asCorner = mobile && position === 'center' && value( 'hd_notif_mobile_mode' ) === 'corner';
			const isTransparent = checked( 'hd_notif_bg_transparent' );
			const height = Number( value( 'hd_notif_height' ) ) || 0;
			const width = Number( value( 'hd_notif_width' ) ) || 400;

			const classes = [ 'hd-notification-popup', `hd-pos-${ position }` ];
			if ( isTransparent ) {
				classes.push( 'hd-notif-transparent' );
			}
			if ( height > 0 ) {
				classes.push( 'hd-notif-fixed-height' );
			}
			if ( asCorner ) {
				classes.push( 'hd-as-corner' );
			}
			if ( animate ) {
				classes.push( `anim-in-${ value( 'hd_notif_anim_in' ) || 'zoom_in' }` );
			}

			const popup = el( 'div', { class: classes.join( ' ' ), open: '' } );
			popup.style.setProperty( '--hd-notif-w', `${ width }px` );
			if ( height > 0 ) {
				popup.style.setProperty( '--hd-notif-h', `${ height }px` );
			}
			if ( ! isTransparent ) {
				popup.style.setProperty( '--hd-notif-bg', value( 'hd_notif_bg_color' ) || '#ffffff' );
			}
			popup.style.setProperty( '--hd-notif-close', value( 'hd_notif_close_color' ) || '#ff0000' );

			if ( checked( 'hd_notif_close_btn' ) ) {
				popup.append( el( 'button', { type: 'button', class: 'hd-notif-close', 'aria-label': 'بستن (پیش‌نمایش)', tabindex: '-1' }, el( 'span', { 'aria-hidden': 'true' }, '×' ) ) );
			}
			const url = urlInput?.value || '';
			const visual = url
				? el( 'img', { src: url, alt: '' } )
				: el( 'div', { class: 'hdn-preview__empty' }, 'عکسی انتخاب نشده' );
			const area = el( 'div', { class: 'hd-notif-main-area' } );
			area.append( value( 'hd_notif_main_link' ) ? el( 'span', { class: 'hd-notif-half-link' }, visual ) : visual );
			popup.append( area );

			stage.classList.toggle( 'is-modal', position === 'center' && ! asCorner );
			stage.querySelector( '.hd-notification-popup' )?.remove();
			stage.append( popup );
		};

		let raf = 0;
		const refresh = () => {
			cancelAnimationFrame( raf );
			raf = requestAnimationFrame( () => {
				syncDependents();
				syncDates();
				renderSummary();
				renderPreview();
			} );
		};
		root.addEventListener( 'input', refresh );
		root.addEventListener( 'change', refresh );

		// دکمه‌های دسکتاپ/موبایل ستون کنار و پنجره بزرگ‌نمایی با هم
		function setDevice( device ) {
			stage.dataset.device = device;
			root.querySelectorAll( '[data-hdn-device]' ).forEach( ( b ) => b.setAttribute( 'aria-pressed', String( b.dataset.hdnDevice === device ) ) );
			fit();
			renderPreview( true );
		}
		root.querySelectorAll( '[data-hdn-device]' ).forEach( ( button ) => {
			button.addEventListener( 'click', () => setDevice( button.dataset.hdnDevice ) );
		} );
		root.querySelector( '[data-hdn-replay]' )?.addEventListener( 'click', () => renderPreview( true ) );

		// تب پیش‌نمایش: اندازه قاب تازه حساب شود (پنهان بود) و انیمیشن ورود دیده شود
		onPreviewTab = () => {
			fit();
			renderPreview( true );
		};

		syncDependents();
		syncDates();
		renderSummary();
		fit();
		renderPreview();
	};

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
