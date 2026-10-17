/**
 * بررسی دسترس‌پذیری (WCAG 2.2 AA) روی صفحه‌های رندرشده ابزار تست — اسکیل hodima-theme.
 *
 *   node a11y-audit.mjs <out-dir> <root> <report.json> [page ...]
 *
 * هر HTML ساخته‌شده توسط run.sh / wc-run.sh در Chromium (دسکتاپ ۱۳۰۰ و موبایل ۳۹۰) باز می‌شود
 * (مثل visual-compare.mjs: فایل‌های قالب/افزونه از <root>، wp-includes از وردپرس ابزار تست)، و:
 *   ۱. axe-core با برچسب‌های wcag2a/aa، wcag21a/aa و wcag22aa اجرا می‌شود (شامل کنتراست رنگ
 *      و اندازه هدف لمسی ۲۴px)؛
 *   ۲. بررسی سفارشی صفحه‌کلید: با Tab روی عنصرهای قابل‌فوکوس می‌رویم؛ اگر هنگام فوکوس کیبورد
 *      هیچ نشانه‌ای (outline یا box-shadow یا تغییر پس‌زمینه/حاشیه) عوض نشود «فوکوس نامرئی»
 *      و اگر مرکز عنصر زیر عنصر دیگری (مثل هدر چسبان) باشد «فوکوس پوشیده» (۲٫۴٫۱۱).
 * خروجی: گزارش JSON و خلاصه گروه‌شده بر اساس قاعده. محدودیت: HTML ثابت است (تعاملی‌های
 * وابسته به سرور مثل ajax کار نمی‌کنند) و axe بخشی از مقایسه‌های کنتراست (روی تصویر/گرادیان) را
 * «ناتمام» می‌گذارد که جدا شمرده می‌شود.
 */
import { chromium } from 'playwright-core';
import fs from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';

const require = createRequire( import.meta.url );
const axePath = require.resolve( 'axe-core/axe.min.js' );
const [ outDir, root, reportFile, ...only ] = process.argv.slice( 2 );
if ( ! reportFile ) {
	console.error( 'usage: a11y-audit.mjs <out-dir> <root> <report.json> [page ...]' );
	process.exit( 2 );
}
const wpRoot = path.join( process.env.HODIMA_HARNESS_WP || process.env.HODIMA_HARNESS || '/tmp/hodima-harness', 'wp' );
const VIEWPORTS = [ { name: 'desktop', width: 1300, height: 900 }, { name: 'mobile', width: 390, height: 844 } ];
const MIME = { '.css': 'text/css', '.js': 'text/javascript', '.woff2': 'font/woff2', '.woff': 'font/woff', '.svg': 'image/svg+xml', '.png': 'image/png', '.jpg': 'image/jpeg', '.webp': 'image/webp' };
const GREY = Buffer.from( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mN4+P/ffwAJpAPqf4/yxwAAAABJRU5ErkJggg==', 'base64' );

function localFile( url ) {
	const u = new URL( url );
	if ( u.hostname !== 'hodima.test' ) return null;
	const p = decodeURIComponent( u.pathname );
	if ( p.startsWith( '/wp-content/themes/hodima/' ) ) return path.join( root, 'hodima', p.slice( '/wp-content/themes/hodima/'.length ) );
	const m = p.match( /^\/wp-content\/plugins\/(hodima-[a-z]+)\/(.*)$/ );
	if ( m ) return path.join( root, 'plugins', m[ 1 ], m[ 2 ] );
	if ( p.startsWith( '/wp-includes/' ) || p.startsWith( '/wp-content/plugins/' ) ) return path.join( wpRoot, p );
	return null;
}

async function audit( browser, htmlFile, vp ) {
	const ctx = await browser.newContext( { viewport: { width: vp.width, height: vp.height }, locale: 'fa-IR', reducedMotion: 'reduce' } );
	const page = await ctx.newPage();
	await page.route( '**/*', async ( route ) => {
		const url = route.request().url();
		if ( url === 'https://hodima.test/__page__' ) return route.fulfill( { status: 200, contentType: 'text/html; charset=utf-8', body: fs.readFileSync( htmlFile ) } );
		const file = localFile( url );
		if ( file && fs.existsSync( file ) && fs.statSync( file ).isFile() ) return route.fulfill( { status: 200, contentType: MIME[ path.extname( file ) ] || 'application/octet-stream', body: fs.readFileSync( file ) } );
		if ( route.request().resourceType() === 'image' ) return route.fulfill( { status: 200, contentType: 'image/png', body: GREY } );
		return route.fulfill( { status: 404, body: '' } );
	} );
	await page.goto( 'https://hodima.test/__page__', { waitUntil: 'load' } );
	await page.evaluate( async () => { await document.fonts.ready; document.querySelectorAll( 'img[loading="lazy"]' ).forEach( ( i ) => i.setAttribute( 'loading', 'eager' ) ); } );
	await page.waitForTimeout( 300 );
	await page.addScriptTag( { path: axePath } );
	const axe = await page.evaluate( async () => {
		const res = await window.axe.run( document, { runOnly: { type: 'tag', values: [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa' ] }, resultTypes: [ 'violations', 'incomplete' ] } );
		const pick = ( list ) => list.map( ( r ) => ( { id: r.id, impact: r.impact, help: r.help, nodes: r.nodes.map( ( n ) => ( { target: n.target.join( ' ' ), html: n.html.slice( 0, 160 ), msg: ( n.any[ 0 ] || n.all[ 0 ] || n.none[ 0 ] || {} ).message || '' } ) ) } ) );
		return { violations: pick( res.violations ), incomplete: pick( res.incomplete ) };
	} );

	// کیبورد: فوکوس نامرئی (استایل با/بدون فوکوس یکی است) و فوکوس پوشیده
	const keyboard = [];
	const snap = ( el ) => {
		const cs = getComputedStyle( el );
		return [ cs.outlineStyle, cs.outlineWidth, cs.outlineColor, cs.boxShadow, cs.backgroundColor, cs.borderTopColor, cs.borderTopWidth, cs.color, cs.textDecorationLine, cs.opacity, cs.transform ].join( '|' );
	};
	const total = await page.evaluate( ( snapSrc ) => {
		const snapFn = new Function( 'el', `return (${ snapSrc })(el)` );
		window.__snap = snapFn;
		const els = [ ...document.querySelectorAll( 'a[href],button,input:not([type=hidden]),select,textarea,summary,[tabindex]:not([tabindex="-1"])' ) ];
		window.__base = new Map();
		els.forEach( ( el, i ) => { el.setAttribute( 'data-a11y', String( i ) ); window.__base.set( String( i ), window.__snap( el ) ); } );
		window.scrollTo( 0, 0 );
		return els.length;
	}, snap.toString() );
	for ( let i = 0; i < Math.min( total, 140 ); i++ ) {
		await page.keyboard.press( 'Tab' );
		await page.waitForTimeout( 40 );
		const info = await page.evaluate( () => {
			const el = document.activeElement;
			if ( ! el || el === document.body || ! el.hasAttribute( 'data-a11y' ) ) return null;
			const cs = getComputedStyle( el );
			const r = el.getBoundingClientRect();
			const visible = r.width > 0 && r.height > 0 && cs.visibility !== 'hidden' && cs.display !== 'none';
			if ( ! visible ) return null;
			const txt = ( el.getAttribute( 'aria-label' ) || el.textContent || el.getAttribute( 'href' ) || '' ).trim().replace( /\s+/g, ' ' ).slice( 0, 30 );
			const label = el.tagName.toLowerCase() + ( el.id ? `#${ el.id }` : '' ) + ( el.classList.length ? `.${ [ ...el.classList ].slice( 0, 3 ).join( '.' ) }` : '' ) + ( txt ? ` «${ txt }»` : '' );
			const changed = window.__snap( el ) !== window.__base.get( el.getAttribute( 'data-a11y' ) );
			el.scrollIntoView( { block: 'center' } );
			const r2 = el.getBoundingClientRect();
			const top = document.elementFromPoint( r2.left + r2.width / 2, r2.top + r2.height / 2 );
			const covered = top && top !== el && ! el.contains( top ) && ! top.contains( el );
			return { label, visible, changed, covered: !! covered, coveredBy: covered ? top.tagName.toLowerCase() + ( top.id ? `#${ top.id }` : '' ) + ( typeof top.className === 'string' && top.className ? `.${ top.className.split( ' ' )[ 0 ] }` : '' ) : '',
				outline: `${ cs.outlineStyle } ${ cs.outlineWidth } ${ cs.outlineColor }`, shadow: cs.boxShadow };
		} );
		if ( info ) keyboard.push( info );
	}
	await ctx.close();
	return { axe, keyboard };
}

const browser = await chromium.launch( { executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium' } ).catch( () => chromium.launch() );
const pages = fs.readdirSync( outDir ).filter( ( f ) => f.endsWith( '.html' ) ).map( ( f ) => f.slice( 0, -5 ) ).filter( ( p ) => ! only.length || only.includes( p ) ).sort();
const report = {};
for ( const p of pages ) {
	for ( const vp of VIEWPORTS ) {
		report[ `${ p }@${ vp.name }` ] = await audit( browser, path.join( outDir, `${ p }.html` ), vp );
	}
}
await browser.close();
fs.writeFileSync( reportFile, JSON.stringify( report, null, 1 ) );

// خلاصه گروه‌شده
const group = {};
for ( const [ key, r ] of Object.entries( report ) ) {
	for ( const kind of [ 'violations', 'incomplete' ] ) {
		for ( const v of r.axe[ kind ] ) {
			const g = ( group[ `${ kind }:${ v.id }` ] ??= { kind, id: v.id, help: v.help, impact: v.impact, pages: new Set(), nodes: 0, samples: [] } );
			g.pages.add( key ); g.nodes += v.nodes.length;
			for ( const n of v.nodes.slice( 0, 2 ) ) if ( g.samples.length < 3 && ! g.samples.some( ( s ) => s.target === n.target ) ) g.samples.push( n );
		}
	}
}
for ( const g of Object.values( group ).sort( ( a, b ) => ( a.kind === b.kind ? b.nodes - a.nodes : a.kind.localeCompare( b.kind ) ) ) ) {
	console.log( `${ g.kind === 'violations' ? '✘' : '?' } ${ g.id } [${ g.impact ?? '-' }] ${ g.nodes } مورد در ${ g.pages.size } نما — ${ g.help }` );
	for ( const s of g.samples ) console.log( `      ${ s.target.slice( 0, 90 ) } | ${ s.msg.slice( 0, 110 ) }` );
}
const inv = {}; const cov = {};
for ( const [ key, r ] of Object.entries( report ) ) {
	for ( const k of r.keyboard ) {
		if ( ! k.changed ) ( inv[ k.label ] ??= new Set() ).add( key );
		if ( k.covered ) ( cov[ `${ k.label } ← ${ k.coveredBy }` ] ??= new Set() ).add( key );
	}
}
console.log( `\nکیبورد: ${ Object.keys( inv ).length } عنصر هنگام فوکوس هیچ تغییر استایلی ندارند (فوکوس نامرئی)، ${ Object.keys( cov ).length } عنصر پوشیده` );
for ( const [ k, v ] of Object.entries( inv ).slice( 0, 40 ) ) console.log( `   نامرئی: ${ k } (${ v.size } نما)` );
for ( const [ k, v ] of Object.entries( cov ).slice( 0, 15 ) ) console.log( `   پوشیده: ${ k } (${ v.size } نما)` );
