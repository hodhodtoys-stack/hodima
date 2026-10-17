/**
 * اندازه هدف لمسی (WCAG 2.2 — 2.5.8) برای ابزار تست: a11y-audit.mjs (صفحه‌های رندرشده) و
 * target-size-fixtures.mjs (کنترل‌هایی که در صفحه‌های آزمایشی نیستند: پنجره پشتیبانی، اسلایدر،
 * پخش‌کننده استوری، اعلان). تابع measureTargets داخل صفحه اجرا می‌شود (page.evaluate).
 */

/*
 * اندازه هدف لمسی (WCAG 2.2 — 2.5.8، حداقل ۲۴×۲۴px): axe فقط حالت اولیه صفحه را می‌بیند. این بخش
 *   - هر کنترل دیدنی را می‌سنجد و برای کنترل‌های کوچک‌تر از ۲۴px «ناحیه لمس واقعی» را با نمونه‌برداری
 *     elementFromPoint می‌گیرد (ناحیه ::before/::after نامرئی هم حساب می‌شود؛ برچسب چک‌باکس هم)؛
 *   - استثنای فاصله (دایره ۲۴px دور هدف با هدف دیگر یا دایره هدف کوچک دیگر تلاقی نکند) و لینک
 *     داخل متن (inline) را اعمال می‌کند؛
 *   - کنترل‌های بسته‌ی aria-expanded=false (منو، جستجو، پاپ‌آپ) را یکی‌یکی باز می‌کند و دوباره می‌سنجد
 *     تا کنترل‌های داخل حالت‌های باز هم دیده شوند.
 * خروجی: فهرست کنترل‌هایی که نقض‌اند (کوچک، بدون استثنا).
 */
export function measureTargets() {
	const SEL = 'a[href],button,input:not([type=hidden]),select,textarea,summary,[role=button],[role=link],[role=checkbox],[role=radio],[role=tab],[role=menuitem],[role=slider],[tabindex]:not([tabindex="-1"])';
	const MIN = 24;
	const describe = ( e ) => e.tagName.toLowerCase() + ( e.id ? `#${ e.id }` : '' ) + ( typeof e.className === 'string' && e.className ? `.${ e.className.trim().split( /\s+/ ).slice( 0, 3 ).join( '.' ) }` : '' );
	const shown = ( e ) => e.checkVisibility( { checkOpacity: true, checkVisibilityCSS: true } ) && ! e.closest( '[inert],[aria-hidden="true"]' );
	const all = [ ...document.querySelectorAll( SEL ) ].filter( ( e ) => shown( e ) && e.getBoundingClientRect().width > 0 && e.getBoundingClientRect().height > 0 );
	const small = all.filter( ( e ) => { const r = e.getBoundingClientRect(); return r.width < MIN - 0.5 || r.height < MIN - 0.5; } );
	const inlineLink = ( e ) => {
		if ( e.tagName !== 'A' || getComputedStyle( e ).display !== 'inline' ) return false;
		for ( const n of e.parentNode.childNodes ) if ( n !== e && n.nodeType === 3 && n.textContent.trim().length > 1 ) return true;
		return false;
	};
	// ناحیه لمس هر کنترل کوچک = جعبه + فاصله تا لبه‌های ناحیه‌ای که ضربه به آن می‌رسد (::before/::after
	// نامرئی هم حساب می‌شود). به‌صورت فاصله از جعبه ذخیره می‌شود تا با جابه‌جایی اسکرول عوض نشود.
	const offs = new Map();
	for ( const el of small ) {
		if ( inlineLink( el ) ) continue;
		el.scrollIntoView( { block: 'center', inline: 'nearest' } );
		const r = el.getBoundingClientRect();
		const labels = el.labels ? [ ...el.labels ] : [];
		const own = ( hit ) => hit && ( el === hit || el.contains( hit ) || labels.some( ( l ) => l === hit || l.contains( hit ) ) );
		let x0 = 1e9, y0 = 1e9, x1 = -1e9, y1 = -1e9;
		for ( let x = Math.floor( r.left ) - 16; x <= r.right + 16; x += 2 ) {
			for ( let y = Math.floor( r.top ) - 16; y <= r.bottom + 16; y += 2 ) {
				if ( x < 0 || y < 0 || x >= innerWidth || y >= innerHeight ) continue;
				if ( own( document.elementFromPoint( x, y ) ) ) { x0 = Math.min( x0, x ); y0 = Math.min( y0, y ); x1 = Math.max( x1, x + 2 ); y1 = Math.max( y1, y + 2 ); }
			}
		}
		if ( x1 < x0 ) { x0 = r.left; y0 = r.top; x1 = r.right; y1 = r.bottom; }
		offs.set( el, { l: Math.max( 0, r.left - x0 ), t: Math.max( 0, r.top - y0 ), r: Math.max( 0, x1 - r.right ), b: Math.max( 0, y1 - r.bottom ) } );
	}
	const box = ( e ) => {
		const r = e.getBoundingClientRect(), o = offs.get( e ) || { l: 0, t: 0, r: 0, b: 0 };
		return { left: r.left - o.l, right: r.right + o.r, top: r.top - o.t, bottom: r.bottom + o.b };
	};
	const undersized = ( e ) => { const q = box( e ); return q.right - q.left < MIN - 0.5 || q.bottom - q.top < MIN - 0.5; };
	const covered = ( e ) => {
		const q = box( e );
		const t = document.elementFromPoint( Math.min( Math.max( ( q.left + q.right ) / 2, 0 ), innerWidth - 1 ), Math.min( Math.max( ( q.top + q.bottom ) / 2, 0 ), innerHeight - 1 ) );
		return t && t !== e && ! e.contains( t ) && ! ( e.labels && [ ...e.labels ].some( ( l ) => l === t || l.contains( t ) ) );
	};
	const res = [];
	for ( const el of offs.keys() ) {
		// استثنای فاصله: دایره ۲۴px دور مرکز ناحیه لمس نباید با هدف دیگر یا دایره هدف کوچک دیگر
		// تلاقی کند. هدرهای fixed/sticky با اسکرول جابه‌جا می‌شوند، پس هر هدف را دوباره به نما می‌آوریم
		// و مختصات همه را همان لحظه می‌خوانیم.
		el.scrollIntoView( { block: 'center', inline: 'nearest' } );
		const t = box( el );
		if ( ! undersized( el ) ) continue;
		if ( covered( el ) ) continue; // زیر لایه دیگر (پنل منوی باز، پوشش مودال): در این حالت قابل لمس نیست
		const cx = ( t.left + t.right ) / 2, cy = ( t.top + t.bottom ) / 2, rad = MIN / 2;
		let clash = '';
		for ( const o of all ) {
			if ( o === el || o.contains( el ) || el.contains( o ) ) continue;
			const q = box( o );
			if ( q.right <= q.left || q.bottom <= q.top || covered( o ) ) continue; // همسایه نامرئی یا زیر لایه دیگر
			if ( undersized( o ) ) {
				if ( Math.hypot( ( q.left + q.right ) / 2 - cx, ( q.top + q.bottom ) / 2 - cy ) < MIN ) { clash = describe( o ); break; }
				continue;
			}
			const nx = Math.max( q.left, Math.min( cx, q.right ) ), ny = Math.max( q.top, Math.min( cy, q.bottom ) );
			if ( ( nx - cx ) ** 2 + ( ny - cy ) ** 2 < rad * rad ) { clash = describe( o ); break; }
		}
		if ( ! clash ) continue;
		const txt = ( el.getAttribute( 'aria-label' ) || el.textContent || el.getAttribute( 'href' ) || '' ).trim().replace( /\s+/g, ' ' ).slice( 0, 24 );
		const up = []; for ( let a = el.parentElement; a && up.length < 3; a = a.parentElement ) up.push( describe( a ) );
		res.push( { in: up.join( ' < ' ), label: describe( el ) + ( txt ? ` «${ txt }»` : '' ), w: Math.round( ( t.right - t.left ) * 10 ) / 10, h: Math.round( ( t.bottom - t.top ) * 10 ) / 10, near: clash } );
	}
	return res;
}

export async function targetSize( page ) {
	const found = new Map();
	const run = async ( state ) => {
		for ( const r of await page.evaluate( measureTargets ) ) if ( ! found.has( r.label ) ) found.set( r.label, { ...r, state } );
	};
	await run( 'اولیه' );
	const n = await page.evaluate( () => {
		const list = [ ...document.querySelectorAll( 'button[aria-expanded="false"],[role=button][aria-expanded="false"],button[aria-haspopup="dialog"],summary:not([open])' ) ];
		list.forEach( ( e, i ) => e.setAttribute( 'data-a11y-t', String( i ) ) );
		return list.length;
	} );
	for ( let i = 0; i < Math.min( n, 20 ); i++ ) {
		const name = await page.evaluate( ( idx ) => {
			const e = document.querySelector( `[data-a11y-t="${ idx }"]` );
			if ( ! e ) return '';
			e.click();
			return e.tagName.toLowerCase() + ( e.id ? `#${ e.id }` : '' ) + ( typeof e.className === 'string' && e.className ? `.${ e.className.trim().split( /\s+/ )[ 0 ] }` : '' );
		}, i );
		if ( ! name ) continue;
		await page.waitForTimeout( 80 );
		await run( `پس از باز کردن ${ name }` );
	}
	return [ ...found.values() ];
}
