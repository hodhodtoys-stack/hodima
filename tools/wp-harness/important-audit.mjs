#!/usr/bin/env node
/**
 * کدام !important قالب واقعا لازم است؟ (نوسازی قالب، مرحله ۶)
 *
 *   node tools/wp-harness/important-audit.mjs [--apply] <out-dir>=<wp-root> ...
 *   (معمولا با important-audit.sh: صفحه‌های ابزار تست اصلی + ووکامرس واقعی)
 *
 * هر صفحه ساخته‌شده (HTML) در Chromium با CSS واقعی همه منبع‌ها (قالب، افزونه‌ها،
 * ووکامرس، وردپرس) و در چند عرض باز می‌شود. برای هر اعلان !important قالب و هر
 * عنصری که به آن می‌خورد (دو بار: حالت‌های :hover/:focus/:active روشن و خاموش)، همه اعلان‌های
 * دیگری که همان خصوصیت را روی همان عنصر می‌گذارند جمع می‌شود و آبشار (importance،
 * specificity، ترتیب؛ style درون‌خطی) یک بار با !important و یک بار بدون آن حساب
 * می‌شود. اعلانی «قابل حذف» است که:
 *   - دست‌کم در یک صفحه/عرض به عنصری بخورد (بدون عنصر = نامعلوم → می‌ماند)،
 *   - هدفش کلاس خود قالب باشد (نه کلاس ووکامرس/وردپرس که CSS افزونه‌های سایت
 *     واقعی — که در ابزار تست نیستند — ممکن است هدف بگیرند)،
 *   - و حذف هم‌زمان همه موارد قابل حذف برنده هیچ عنصر/خصوصیتی را عوض نکند.
 * --apply: «!important» همان اعلان‌ها را از سورس برمی‌دارد (بقیه فایل دست نمی‌خورد).
 */
import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright-core';
import postcss from 'postcss';
import Specificity from '@bramus/specificity';

const args = process.argv.slice( 2 );
const apply = args.includes( '--apply' );
const pairs = args.filter( ( a ) => a.includes( '=' ) ).map( ( a ) => a.split( '=' ) );
const repo = path.resolve( path.dirname( new URL( import.meta.url ).pathname ), '../..' );
const theme = path.join( repo, 'hodima' );
const WIDTHS = ( process.env.HODIMA_AUDIT_WIDTHS || '1300,1100,1000,800,600,390' ).split( ',' ).map( Number );

// ── ۱. شناسه برای هر !important قالب (نشانگر --hximp-ID: خصوصیت کنار همان اعلان) ──
const cssFiles = fs.readdirSync( theme, { recursive: true } ).map( String )
	.filter( ( f ) => f.endsWith( '.css' ) && ! f.startsWith( 'inc/theme-settings' ) );
const decls = new Map(); // id → { file, line, prop, selector }
const served = new Map(); // مسیر نسبی → CSS با نشانگر
const splitList = ( sel ) => {
	const out = []; let d = 0; let cur = '';
	for ( const ch of sel ) {
		if ( '(' === ch || '[' === ch ) d++;
		if ( ')' === ch || ']' === ch ) d--;
		if ( ',' === ch && 0 === d ) { out.push( cur.trim() ); cur = ''; } else cur += ch;
	}
	if ( cur.trim() ) out.push( cur.trim() );
	return out;
};
// انتخابگر کامل اعلان (nesting باز شده، مثل مرورگر)
const ruleSelectors = ( node ) => {
	const chain = [];
	for ( let p = node.parent; p && 'root' !== p.type; p = p.parent ) {
		if ( 'rule' === p.type ) chain.unshift( p.selector );
	}
	let parents = null;
	for ( const sel of chain ) {
		parents = splitList( sel ).map( ( part ) => {
			if ( ! parents ) return part.replace( /\s+/g, ' ' );
			const rel = part.includes( '&' ) ? part : `& ${ part }`;
			return rel.replaceAll( '&', 1 === parents.length ? parents[ 0 ] : `:is(${ parents.join( ', ' ) })` ).replace( /\s+/g, ' ' );
		} );
	}
	return parents ?? [];
};
cssFiles.forEach( ( rel, fi ) => {
	const root = postcss.parse( fs.readFileSync( path.join( theme, rel ), 'utf8' ) );
	let n = 0;
	root.walkDecls( ( d ) => {
		if ( ! d.important || d.prop.startsWith( '--' ) ) return;
		const id = `${ fi }_${ n++ }`;
		decls.set( id, { file: rel, line: d.source.start.line, prop: d.prop, selectors: ruleSelectors( d ) } );
		d.after( postcss.decl( { prop: `--hximp-${ id }`, value: d.prop } ) );
	} );
	served.set( rel, root.toString() );
} );

// ── ۲. در مرورگر: موارد رقابت هر اعلان !important قالب ──
function collect( stateOn ) {
	const STATE = /:(hover|focus-visible|focus-within|focus|active)\b/g;
	const PSEUDO_EL = /::?(before|after|placeholder|marker|selection|backdrop|-webkit-[a-z-]+)\b/;
	const split = ( sel ) => {
		const out = []; let d = 0; let cur = '';
		for ( const ch of sel ) {
			if ( '(' === ch || '[' === ch ) d++;
			if ( ')' === ch || ']' === ch ) d--;
			if ( ',' === ch && 0 === d ) { out.push( cur.trim() ); cur = ''; } else cur += ch;
		}
		if ( cur.trim() ) out.push( cur.trim() );
		return out;
	};
	const resolve = ( sel, parents ) => split( sel ).map( ( part ) => {
		if ( ! parents ) return part;
		const rel = part.includes( '&' ) ? part : `& ${ part }`;
		return rel.replaceAll( '&', 1 === parents.length ? parents[ 0 ] : `:is(${ parents.join( ', ' ) })` );
	} );
	const rules = [];
	let order = 0;
	const walk = ( list, parents, ok, sheet ) => {
		for ( const r of list ) {
			if ( r instanceof CSSStyleRule ) {
				const sels = resolve( r.selectorText, parents );
				rules.push( { sels, style: r.style, ok, order: order++, sheet } );
				walk( r.cssRules, sels, ok, sheet );
			} else if ( 'CSSNestedDeclarations' in window && r instanceof CSSNestedDeclarations ) {
				rules.push( { sels: parents, style: r.style, ok, order: order++, sheet } );
			} else if ( r instanceof CSSMediaRule ) {
				walk( r.cssRules, parents, ok && matchMedia( r.conditionText ).matches, sheet );
			} else if ( r instanceof CSSSupportsRule ) {
				walk( r.cssRules, parents, ok && CSS.supports( r.conditionText ), sheet );
			} else if ( r.cssRules && ! ( r instanceof CSSKeyframesRule ) ) {
				walk( r.cssRules, parents, ok, sheet );
			}
		}
	};
	[ ...document.styleSheets ].forEach( ( s, i ) => {
		try { walk( s.cssRules, null, ! s.media.mediaText || matchMedia( s.media.mediaText ).matches, i ); } catch { /* cross-origin */ }
	} );

	// longhandهای یک خصوصیت (shorthand → اجزا)
	const probe = document.createElement( 'i' );
	const longhands = ( prop ) => {
		probe.style.cssText = '';
		probe.style.setProperty( prop, 'initial' );
		const out = [];
		for ( let i = 0; i < probe.style.length; i++ ) out.push( probe.style[ i ] );
		return out.length ? out : [ prop ];
	};
	// «جایگاه» فیزیکی یک longhand برای جهت عنصر (فقط نوشتار افقی)
	const slot = ( name, rtl ) => {
		const side = { 'inline-start': rtl ? 'right' : 'left', 'inline-end': rtl ? 'left' : 'right', 'block-start': 'top', 'block-end': 'bottom' };
		const size = { 'inline-size': 'width', 'block-size': 'height', 'min-inline-size': 'min-width', 'max-inline-size': 'max-width', 'min-block-size': 'min-height', 'max-block-size': 'max-height' };
		if ( size[ name ] ) return size[ name ];
		const inset = name.match( /^inset-(inline|block)-(start|end)$/ );
		if ( inset ) return side[ `${ inset[ 1 ] }-${ inset[ 2 ] }` ];
		return name.replace( /-(inline|block)-(start|end)(?=-|$)/, ( m, a, e ) => `-${ side[ `${ a }-${ e }` ] }` );
	};
	// نشانگرهای هر قانون: longhand → شناسه اعلان !important قالب
	const markers = ( style ) => {
		const map = new Map();
		for ( let i = 0; i < style.length; i++ ) {
			const p = style[ i ];
			if ( p.startsWith( '--hximp-' ) ) {
				for ( const l of longhands( style.getPropertyValue( p ).trim() ) ) map.set( l, p.slice( 8 ) );
			}
		}
		return map;
	};
	// دو حالت جدا: کاربر روی عنصر (hover/focus روشن) و نه (قانون‌های حالت‌دار حذف)
	const STATEFUL = /:(hover|focus-visible|focus-within|focus|active)\b/;
	const matches = ( el, sel, pseudo ) => {
		const pe = ( sel.match( PSEUDO_EL ) || [ '' ] )[ 0 ].replace( /^:+/, '::' );
		if ( pe !== pseudo ) return false;
		if ( ! stateOn && STATEFUL.test( sel ) ) return false;
		try {
			const plain = sel.replace( PSEUDO_EL, '' ).replace( STATE, '' ).trim() || '*';
			return el.matches( plain.replace( /([>+~]\s*)$/, '$1*' ) );
		} catch { return false; }
	};

	// نمایه: نام خصوصیت → قانون‌ها
	const byName = new Map();
	for ( const r of rules ) {
		r.marks = markers( r.style );
		for ( let i = 0; i < r.style.length; i++ ) {
			const n = r.style[ i ];
			if ( n.startsWith( '--' ) ) continue;
			if ( ! byName.has( n ) ) byName.set( n, [] );
			byName.get( n ).push( { r, i } );
		}
	}
	const names = [ ...byName.keys() ];
	const cases = [];
	const seen = new Set();
	const elIndex = new Map( [ ...document.querySelectorAll( '*' ) ].map( ( e, i ) => [ e, i ] ) );
	for ( const r of rules ) {
		if ( ! r.ok || ! r.marks.size ) continue;
		for ( const sel of r.sels ) {
			if ( ! stateOn && STATEFUL.test( sel ) ) continue;
			const pseudo = ( sel.match( PSEUDO_EL ) || [ '' ] )[ 0 ].replace( /^:+/, '::' );
			let els = [];
			try { els = document.querySelectorAll( sel.replace( PSEUDO_EL, '' ).replace( STATE, '' ).trim() || '*' ); } catch { continue; }
			for ( const el of els ) {
				const rtl = 'rtl' === getComputedStyle( el ).direction;
				for ( const [ l ] of r.marks ) {
					if ( ! r.style.getPropertyPriority( l ) ) continue;
					const s = slot( l, rtl );
					const key = `${ elIndex.get( el ) }|${ pseudo }|${ s }`;
					if ( seen.has( key ) ) continue;
					seen.add( key );
					const cands = [];
					for ( const n of names ) {
						if ( slot( n, rtl ) !== s ) continue;
						for ( const { r: c, i } of byName.get( n ) ) {
							if ( ! c.ok ) continue;
							const hit = c.sels.filter( ( cs ) => matches( el, cs, pseudo ) );
							if ( ! hit.length ) continue;
							cands.push( { sels: hit, imp: !! c.style.getPropertyPriority( n ), order: [ c.sheet, c.order, i ], id: c.marks.get( n ) ?? null, value: c.style.getPropertyValue( n ) } );
						}
					}
					if ( ! pseudo && el.style ) {
						for ( let i = 0; i < el.style.length; i++ ) {
							const n = el.style[ i ];
							if ( slot( n, rtl ) === s ) cands.push( { inline: true, sels: [], imp: !! el.style.getPropertyPriority( n ), order: [ 1e9, 0, i ], id: null, value: el.style.getPropertyValue( n ) } );
						}
					}
					cases.push( { key, cands } );
				}
			}
		}
	}
	return cases;
}

// ── ۳. اجرا روی همه صفحه‌ها و عرض‌ها ──
const MIME = { '.css': 'text/css', '.js': 'text/javascript', '.woff2': 'font/woff2', '.svg': 'image/svg+xml' };
const GREY = Buffer.from( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mN4+P/ffwAJpAPqf4/yxwAAAABJRU5ErkJggg==', 'base64' );
const browser = await chromium.launch();
const allCases = [];
const matched = new Set();
for ( const [ outDir, wpRoot ] of pairs ) {
	for ( const f of fs.readdirSync( outDir ).filter( ( x ) => x.endsWith( '.html' ) ).sort() ) {
		for ( const width of WIDTHS ) {
			const page = await browser.newPage( { viewport: { width, height: 900 } } );
			await page.route( '**/*', ( route ) => {
				const u = new URL( route.request().url() );
				const p = decodeURIComponent( u.pathname );
				if ( '/__page__' === p ) return route.fulfill( { contentType: 'text/html; charset=utf-8', body: fs.readFileSync( path.join( outDir, f ) ) } );
				if ( p.startsWith( '/wp-content/themes/hodima/' ) ) {
					const rel = p.slice( 26 );
					if ( served.has( rel ) ) return route.fulfill( { contentType: 'text/css', body: served.get( rel ) } );
					const file = path.join( theme, rel );
					if ( fs.existsSync( file ) ) return route.fulfill( { contentType: MIME[ path.extname( file ) ] || 'application/octet-stream', body: fs.readFileSync( file ) } );
				}
				const m = p.match( /^\/wp-content\/plugins\/(hodima-[a-z]+)\/(.*)$/ );
				const file = m ? path.join( repo, 'plugins', m[ 1 ], m[ 2 ] ) : ( /^\/wp-(includes|content\/plugins)\//.test( p ) ? path.join( wpRoot, p ) : null );
				if ( file && fs.existsSync( file ) && fs.statSync( file ).isFile() ) return route.fulfill( { contentType: MIME[ path.extname( file ) ] || 'application/octet-stream', body: fs.readFileSync( file ) } );
				if ( 'image' === route.request().resourceType() ) return route.fulfill( { contentType: 'image/png', body: GREY } );
				return route.fulfill( { status: 404, body: '' } );
			} );
			await page.goto( 'https://hodima.test/__page__', { waitUntil: 'load' } );
			const cases = [ ...await page.evaluate( collect, true ), ...await page.evaluate( collect, false ) ];
			for ( const c of cases ) {
				c.where = `${ path.basename( outDir ) }/${ f }@${ width }`;
				for ( const x of c.cands ) if ( x.id ) matched.add( x.id );
				allCases.push( c );
			}
			await page.close();
		}
		process.stderr.write( '.' );
	}
}
await browser.close();
process.stderr.write( '\n' );

// ── ۴. آبشار قبل/بعد و مجموعه پایدار قابل حذف ──
const specCache = new Map();
const spec = ( sel ) => {
	if ( ! specCache.has( sel ) ) {
		let v = [ 9, 9, 9 ];
		try { v = Specificity.calculate( sel )[ 0 ].toArray(); } catch { /* ناشناخته: بدبینانه بالا */ }
		specCache.set( sel, v );
	}
	return specCache.get( sel );
};
const cmp = ( a, b ) => {
	for ( let i = 0; i < a.length; i++ ) if ( a[ i ] !== b[ i ] ) return a[ i ] - b[ i ];
	return 0;
};
const weight = ( c, removed ) => {
	const imp = c.imp && ! ( c.id && removed.has( c.id ) ) ? 1 : 0;
	const sp = c.inline ? [ 1e6, 0, 0 ] : c.sels.map( spec ).sort( cmp ).pop();
	return [ imp, ...sp, ...c.order ];
};
const winner = ( cands, removed ) => cands.reduce( ( best, c ) => ( ! best || cmp( weight( c, removed ), weight( best, removed ) ) > 0 ? c : best ), null );

// هدف کلاس خود قالب؟ (کلاس‌های ووکامرس/وردپرس را CSS افزونه‌های سایت واقعی هم هدف می‌گیرند)
const FOREIGN = /^(woocommerce|wc-|wp-|wc_|product|products|price|amount|star-rating|button|onsale|added_to_cart|cart|cart_totals|cart-collaterals|checkout|quantity|qty|single_add_to_cart_button|comment|commentlist|children|reply|page-numbers|current|dots|related|up-sells|upsells|summary|images|tabs|alignfull|entry-|menu-item|sub-menu|current-menu|screen-reader|is-|has-|active|open|selected|stars|meta)/;
const themeOwned = ( selectors ) => selectors.length > 0 && selectors.every( ( full ) => {
	const subject = full.trim().split( /\s*[\s>+~]\s*(?![^(]*\))/ ).pop().replace( /::?[a-z-]+(\([^)]*\))?/g, '' );
	return [ ...subject.matchAll( /[.#]([\w-]+)/g ) ].some( ( m ) => ! FOREIGN.test( m[ 1 ] ) );
} );

const removed = new Set( [ ...decls.keys() ].filter( ( id ) => matched.has( id ) && themeOwned( decls.get( id ).selectors ) ) );
const reasons = new Map();
for ( let pass = 0; pass < 50; pass++ ) {
	let changed = false;
	for ( const c of allCases ) {
		const before = winner( c.cands, new Set() );
		const after = winner( c.cands, removed );
		/*
		 * برنده عوض شد → لازم. مقدار برابر فقط وقتی استثناست که هر دو مقدار واقعی
		 * باشند: longhandهای shorthand دارای var() در CSSOM خالی‌اند (''؛ مقدار تا
		 * زمان محاسبه معلوم نیست) و «'' === ''» قبلا اشتباها «بی‌تفاوت» حساب می‌شد.
		 */
		const sameValue = '' !== before?.value && before?.value === after?.value;
		if ( before !== after && ! sameValue && before.id && removed.has( before.id ) ) {
			removed.delete( before.id );
			reasons.set( before.id, `${ c.where }: ${ after?.sels?.[ 0 ] ?? ( after?.inline ? 'style درون‌خطی' : '?' ) }` );
			changed = true;
		}
	}
	if ( ! changed ) break;
}

const byFile = new Map();
for ( const [ id, d ] of decls ) {
	const s = byFile.get( d.file ) ?? { total: 0, removable: 0, unmatched: 0, foreign: 0, needed: 0 };
	s.total++;
	if ( removed.has( id ) ) s.removable++;
	else if ( ! matched.has( id ) ) s.unmatched++;
	else if ( ! themeOwned( d.selectors ) ) s.foreign++;
	else s.needed++;
	byFile.set( d.file, s );
}
console.log( 'فایل'.padEnd( 34 ), 'کل', 'حذف', 'بی‌عنصر', 'ووکامرس', 'لازم' );
for ( const [ f, s ] of [ ...byFile ].sort() ) console.log( f.padEnd( 36 ), String( s.total ).padStart( 3 ), String( s.removable ).padStart( 4 ), String( s.unmatched ).padStart( 6 ), String( s.foreign ).padStart( 6 ), String( s.needed ).padStart( 5 ) );
if ( process.env.HODIMA_AUDIT_VERBOSE ) for ( const [ id, why ] of reasons ) console.log( `  لازم ${ decls.get( id ).file }:${ decls.get( id ).line } ${ decls.get( id ).prop } ← ${ why }` );

if ( apply ) {
	cssFiles.forEach( ( rel, fi ) => {
		const file = path.join( theme, rel );
		const root = postcss.parse( fs.readFileSync( file, 'utf8' ) );
		let n = 0;
		let touched = 0;
		root.walkDecls( ( d ) => {
			if ( ! d.important || d.prop.startsWith( '--' ) ) return;
			if ( removed.has( `${ fi }_${ n++ }` ) ) {
				d.important = false;
				delete d.raws.important;
				touched++;
			}
		} );
		if ( touched ) fs.writeFileSync( file, root.toString() );
	} );
	console.log( `\n✔ ${ removed.size } !important برداشته شد` );
} else {
	console.log( `\n${ removed.size } از ${ decls.size } !important قابل حذف (--apply برای برداشتن)` );
}
