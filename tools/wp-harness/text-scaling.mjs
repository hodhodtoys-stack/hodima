/**
 * فاصله متن (WCAG 1.4.12) و بزرگ‌نمایی متن (1.4.4) برای ابزار تست — a11y-audit.mjs.
 *
 *   ۱. «spacing»: CSS استاندارد معیار (line-height ۱٫۵، فاصله بند ۲× اندازه فونت، letter-spacing ۰٫۱۲em،
 *      word-spacing ۰٫۱۶em) روی همه عنصرها؛
 *   ۲. «text200»: اندازه ریشه ۲۰۰٪ (بزرگ‌کردن فقط متن؛ بزرگ‌نمایی مرورگر جدا با بازچینی ۳۲۰px سنجیده می‌شود).
 * هر حالت با حالت پایه همان صفحه مقایسه می‌شود و فقط مشکل *تازه* گزارش می‌شود:
 *   - «بریده»: عنصر overflow hidden/clip که محتوایش بیشتر از جعبه‌اش شد (متن از دید می‌رود)؛
 *   - «بیرون‌زده»: متن مستقیم عنصر از جعبه‌اش بیرون زد (روی عنصر دیگر می‌افتد)؛
 *   - «بیرون از صفحه»: عنصر دیدنی از لبه نما بیرون زد (بریده‌شدن محتوا، چون html/body افقی hidden است).
 * کنترل‌های بسته‌ی aria-expanded هم یک بار باز و دوباره سنجیده می‌شوند (منو، پنجره‌ها).
 * محدودیت: تشخیص «بیرون‌زده» فقط با هندسه است؛ تلاقی دو عنصر هم‌سطح را نمی‌بیند.
 */

const SPACING_CSS = '*{line-height:1.5!important;letter-spacing:.12em!important;word-spacing:.16em!important}p,li,h1,h2,h3,h4,h5,h6{margin-bottom:2em!important}p{margin-bottom:2em!important}';
const TEXT200_CSS = 'html{font-size:200%!important}';

/** داخل صفحه: فهرست مشکل‌های فعلی (کلید پایدار با data-a11y-s). */
function detect() {
	const describe = ( e ) => e.tagName.toLowerCase() + ( e.id ? `#${ e.id }` : '' ) + ( typeof e.className === 'string' && e.className ? `.${ e.className.trim().split( /\s+/ ).slice( 0, 3 ).join( '.' ) }` : '' );
	const out = [];
	const seen = new Set();
	let n = 0;
	for ( const el of document.body.querySelectorAll( '*' ) ) {
		if ( ! el.hasAttribute( 'data-a11y-s' ) ) el.setAttribute( 'data-a11y-s', `n${ n++ }-${ Math.random().toString( 36 ).slice( 2, 6 ) }` );
	}
	for ( const el of document.body.querySelectorAll( '*' ) ) {
		if ( ! el.checkVisibility( { checkOpacity: true, checkVisibilityCSS: true } ) ) continue;
		const cs = getComputedStyle( el );
		const id = el.getAttribute( 'data-a11y-s' );
		const direct = [ ...el.childNodes ].some( ( c ) => c.nodeType === 3 && c.textContent.trim() );
		// بریده: کادر بسته که محتوایش جا نمی‌شود
		const clips = cs.overflowX === 'hidden' || cs.overflowX === 'clip' || cs.overflowY === 'hidden' || cs.overflowY === 'clip';
		// -webkit-line-clamp: کوتاه‌کردن عمدی با «…» (در حالت عادی هم هست)، نه بریدگی ناخواسته
		const clamp = cs.getPropertyValue( '-webkit-line-clamp' );
		if ( clips && ( ! clamp || clamp === 'none' ) && el.textContent.trim() && ( el.scrollHeight > el.clientHeight + 1 || el.scrollWidth > el.clientWidth + 1 ) && el !== document.body && el !== document.documentElement ) {
			out.push( { id, kind: 'بریده', label: describe( el ), detail: `${ el.scrollWidth }×${ el.scrollHeight } در ${ el.clientWidth }×${ el.clientHeight }`, html: el.outerHTML.slice( 0, 150 ) } );
			seen.add( el );
		}
		// بیرون‌زده: متن مستقیم از جعبه عنصر بیرون می‌زند
		if ( direct && ! seen.has( el ) && ( ! clamp || clamp === 'none' ) ) {
			const box = el.getBoundingClientRect();
			if ( box.width < 1 || box.height < 1 ) continue;
			let l = 1e9, t = 1e9, r = -1e9, b = -1e9;
			for ( const c of el.childNodes ) {
				if ( c.nodeType !== 3 || ! c.textContent.trim() ) continue;
				const rg = document.createRange();
				rg.selectNodeContents( c );
				for ( const q of rg.getClientRects() ) { if ( q.width < 1 ) continue; l = Math.min( l, q.left ); t = Math.min( t, q.top ); r = Math.max( r, q.right ); b = Math.max( b, q.bottom ); }
			}
			if ( l > r ) continue;
			// افقی: بیش از ۴px؛ عمودی: بیش از ۰٫۳۵em (فونت وزیرمتن جعبه متن را حدود ۱٫۶em می‌گیرد؛ در line-height ≤ ۱ بیرون‌زدگی تا حدود ۰٫۳em عادی و بی‌اثر است)
			const fs = parseFloat( cs.fontSize ) || 16;
			const over = Math.max( box.left - l, r - box.right, ( box.top - t ) - 0.35 * fs, ( b - box.bottom ) - 0.35 * fs );
			if ( over > 4 && cs.display !== 'inline' ) out.push( { id, kind: 'بیرون‌زده', label: describe( el ), detail: `${ Math.round( over ) }px بیرون جعبه`, html: el.outerHTML.slice( 0, 150 ) } );
		}
	}
	// بیرون از صفحه: عنصر دیدنی و غیرثابت که از لبه نما بیرون می‌زند و داخل کانتینر اسکرول‌شونده/پنهان نیست
	// (پنل منوی بسته و فیلد تله ربات عمدا بیرون‌اند: visibility/aria-hidden)
	const w = document.documentElement.clientWidth;
	const clipped = ( el ) => { for ( let p = el.parentElement; p && p !== document.body; p = p.parentElement ) { if ( getComputedStyle( p ).overflowX !== 'visible' ) return true; } return false; };
	for ( const el of document.body.querySelectorAll( '*' ) ) {
		const r = el.getBoundingClientRect();
		if ( r.width < 1 || r.height < 1 || ( r.right <= w + 1 && r.left >= -1 ) ) continue;
		if ( ! el.checkVisibility( { checkOpacity: true, checkVisibilityCSS: true } ) || el.closest( '[aria-hidden="true"],[inert]' ) || clipped( el ) || getComputedStyle( el ).position === 'fixed' ) continue;
		if ( ! el.textContent.trim() && ! [ 'IMG', 'INPUT', 'BUTTON', 'SELECT', 'TEXTAREA', 'SVG', 'VIDEO' ].includes( el.tagName.toUpperCase() ) ) continue;
		out.push( { id: el.getAttribute( 'data-a11y-s' ), kind: 'بیرون از صفحه', label: describe( el ), detail: `${ Math.round( r.left ) }..${ Math.round( r.right ) } در عرض ${ w }`, html: el.outerHTML.slice( 0, 150 ) } );
	}
	return out;
}

/** باز کردن کنترل‌های aria-expanded=false (حداکثر ۱۰) تا منو/پنجره‌ها هم سنجیده شوند. */
async function openToggles( page ) {
	await page.evaluate( () => {
		[ ...document.querySelectorAll( 'button[aria-expanded="false"],[role=button][aria-expanded="false"]' ) ].slice( 0, 10 ).forEach( ( e ) => { try { e.click(); } catch { /* noop */ } } );
	} );
	await page.waitForTimeout( 120 );
}

/**
 * @param {( vp: {width:number,height:number} ) => Promise<{ctx: any, page: any}>} open  باز کردن صفحه در نمای داده‌شده
 * @returns {Promise<Array<{mode:string,vp:string,state:string,id:string,kind:string,label:string,detail:string}>>}
 */
export async function textScaling( open ) {
	const rows = [];
	for ( const vp of [ { name: 'desktop', width: 1300, height: 900 }, { name: 'mobile', width: 390, height: 844 } ] ) {
		for ( const [ mode, css ] of [ [ 'spacing', SPACING_CSS ], [ 'text200', TEXT200_CSS ] ] ) {
			for ( const opened of [ false, true ] ) {
				const { ctx, page } = await open( vp );
				if ( opened ) await openToggles( page );
				const base = new Set( ( await page.evaluate( detect ) ).map( ( x ) => `${ x.id }|${ x.kind }` ) );
				await page.addStyleTag( { content: css } );
				await page.waitForTimeout( 150 );
				for ( const x of await page.evaluate( detect ) ) {
					if ( ! base.has( `${ x.id }|${ x.kind }` ) ) rows.push( { mode, vp: vp.name, state: opened ? 'منوها باز' : 'اولیه', ...x } );
				}
				await ctx.close();
			}
		}
	}
	return rows;
}
