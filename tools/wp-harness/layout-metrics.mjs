/**
 * اندازه‌گیری قاعده‌های چیدمان عددی پیشخوان (اسکیل hodima-admin-ui، بخش «قاعده‌های چیدمان») روی یک صفحه رندرشده.
 * فقط می‌خواند؛ هیچ چیز را عوض نمی‌کند. توسط admin-shot.mjs (HODIMA_MEASURE=<فایل jsonl>) روی هر عرض اجرا می‌شود
 * و layout-rules.sh نتیجه را با layout-rules-report.mjs به جدول قبول/هشدار تبدیل می‌کند.
 *
 * خروجی هر عرض: { height, cards, primaryTotal, primaryMaxPerCard, purple[], purpleCount, smallTargets[], bigEmpty, tabs }
 */

/** کد این تابع داخل صفحه اجرا می‌شود (page.evaluate)؛ به بیرون وابسته نیست. */
export function measureInPage() {
	const root = document.querySelector( '#wpbody-content' ) || document.body;
	const visible = ( el ) => {
		const r = el.getBoundingClientRect();
		const s = getComputedStyle( el );
		return r.width > 0 && r.height > 0 && 'hidden' !== s.visibility && 'none' !== s.display;
	};
	const name = ( el ) => {
		const cls = ( el.getAttribute( 'class' ) || '' ).split( /\s+/ ).filter( Boolean ).slice( 0, 2 ).join( '.' );
		return el.tagName.toLowerCase() + ( el.id ? '#' + el.id : '' ) + ( cls ? '.' + cls : '' );
	};

	// ارتفاع محتوا = ارتفاع جریان عادی .wrap (عنصرهای absolute/fixed مثل حباب ؟ و متن فقط‌صفحه‌خوان آن را بزرگ نمی‌کنند)
	const wrap = root.querySelector( '.wrap' ) || root;
	const height = Math.round( wrap.getBoundingClientRect().height );

	// کارت هم‌سطح (تودرتو شمرده نمی‌شود)
	const cardSel = '.hd-card, .hodima-panel, .hdt-card, .hds-card, .hdm-card, .hdc-card, .hd-body > section';
	// کارت جمع‌شونده بسته (details اختیاری) کارت دیدنی نیست
	const cards = [ ...root.querySelectorAll( cardSel ) ].filter( ( el ) => visible( el ) && ! el.parentElement.closest( cardSel ) && ! ( 'DETAILS' === el.tagName && ! el.open ) );

	// دکمه پر در هر کارت (primary)
	const primarySel = '.button-primary, .hd-button--primary, button[type="submit"].button-primary, .hodima-settings__save';
	const primaries = [ ...root.querySelectorAll( primarySel ) ].filter( visible );
	let primaryMaxPerCard = 0;
	cards.forEach( ( c ) => {
		primaryMaxPerCard = Math.max( primaryMaxPerCard, [ ...c.querySelectorAll( primarySel ) ].filter( visible ).length );
	} );

	// بنفش (۶a2b9a = 106,43,154؛ a341c8 = 163,65,200) در رنگ‌های محاسبه‌شده
	const purpleRe = /rgba?\(\s*(106\s*,\s*43\s*,\s*154|163\s*,\s*65\s*,\s*200)/;
	const purple = [];
	root.querySelectorAll( '*' ).forEach( ( el ) => {
		if ( ! visible( el ) ) {
			return;
		}
		const s = getComputedStyle( el );
		const bits = [ s.color, s.backgroundColor, s.backgroundImage, s.borderTopColor, s.outlineColor, s.boxShadow ];
		if ( bits.some( ( v ) => purpleRe.test( v ) ) ) {
			purple.push( name( el ) + ( el.closest( '[data-hodima-palette-preview], .hodima-palette-preview, .hodima-panel--swatches' ) ? ' [پالت برند/پیش‌نمایش]' : '' ) );
		}
	} );

	// هدف لمسی ≥ ۲۴px (WCAG 2.2): دکمه، فیلد و summary؛ لینک داخل متن حساب نمی‌شود
	const targetSel = 'button, input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]), select, textarea, summary, a.button, [role="button"]';
	const smallTargets = [ ...root.querySelectorAll( targetSel ) ].filter( ( el ) => {
		// فیلد فایل پنهان‌شده پشت برچسب دکمه و دکمه‌های شکل‌لینک (button-link) داخل متن هدف مستقل نیستند
		if ( ! visible( el ) || el.classList.contains( 'screen-reader-text' ) || el.classList.contains( 'button-link' ) || 'file' === el.type ) {
			return false;
		}
		const r = el.getBoundingClientRect();
		return r.width < 24 || r.height < 24;
	} ).map( name );

	// حالت خالی بزرگ (> ۱۶۰px)
	const bigEmpty = [ ...root.querySelectorAll( '.hd-empty, .hodima-empty, [class*="__empty"]' ) ].filter( ( el ) => visible( el ) && el.getBoundingClientRect().height > 160 ).length;

	const tabs = root.querySelectorAll( '[role="tablist"], .nav-tab-wrapper, .hd-tabs, .hodima-settings__nav' ).length;

	return { height, cards: cards.length, primaryTotal: primaries.length, primaryMaxPerCard, purple: [ ...new Set( purple ) ].slice( 0, 6 ), purpleCount: purple.length, smallTargets: [ ...new Set( smallTargets ) ].slice( 0, 6 ), smallCount: smallTargets.length, bigEmpty, tabs };
}
