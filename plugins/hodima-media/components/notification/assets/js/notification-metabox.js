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
 */
( () => {
	'use strict';

	const init = () => {
		const root = document.querySelector( '[data-hdn]' );
		if ( ! root ) {
			return;
		}
		const field = ( name ) => root.querySelector( `[name="${ name }"]` );
		const value = ( name ) => field( name )?.value ?? '';
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
		const syncDependents = () => {
			const isTransparent = checked( 'hd_notif_bg_transparent' );
			bgField?.classList.toggle( 'is-disabled', isTransparent );
			if ( field( 'hd_notif_bg_color' ) ) {
				field( 'hd_notif_bg_color' ).disabled = isTransparent;
			}
			if ( mobileField ) {
				mobileField.hidden = value( 'hd_notif_position' ) !== 'center';
			}
			const allPages = value( 'hd_notif_target_type' ) === 'all';
			pagesField?.classList.toggle( 'is-disabled', allPages );
			if ( field( 'hd_notif_target_page' ) ) {
				field( 'hd_notif_target_page' ).disabled = allPages;
			}
		};

		/* ── تاریخ شمسی کنار تاریخ میلادی ─────────────────────────── */
		const jalali = ( () => {
			try {
				return new Intl.DateTimeFormat( 'fa-IR-u-ca-persian', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' } );
			} catch {
				return null;
			}
		} )();
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
				// ترتیب format() مرورگرها برای fa «۱۴۰۵ آذر ۳۰, دوشنبه» است؛ از اجزا: «دوشنبه ۳۰ آذر ۱۴۰۵»
				const part = Object.fromEntries( jalali.formatToParts( date ).map( ( p ) => [ p.type, p.value ] ) );
				out.textContent = `شمسی: ${ part.weekday } ${ part.day } ${ part.month } ${ part.year }`;
			} );
		};

		/* ── پیش‌نمایش ───────────────────────────────────────────── */
		const stage = root.querySelector( '[data-hdn-preview]' );
		const el = ( tag, attrs = {}, ...children ) => {
			const node = document.createElement( tag );
			Object.entries( attrs ).forEach( ( [ k, v ] ) => node.setAttribute( k, String( v ) ) );
			node.append( ...children );
			return node;
		};

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
				renderPreview();
			} );
		};
		root.addEventListener( 'input', refresh );
		root.addEventListener( 'change', refresh );

		root.querySelectorAll( '[data-hdn-device]' ).forEach( ( button ) => {
			button.addEventListener( 'click', () => {
				stage.dataset.device = button.dataset.hdnDevice;
				root.querySelectorAll( '[data-hdn-device]' ).forEach( ( b ) => b.setAttribute( 'aria-pressed', String( b === button ) ) );
				renderPreview( true );
			} );
		} );
		root.querySelector( '[data-hdn-replay]' )?.addEventListener( 'click', () => renderPreview( true ) );

		syncDependents();
		syncDates();
		renderPreview();
	};

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
