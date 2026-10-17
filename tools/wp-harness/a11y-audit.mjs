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
import { targetSize } from './target-size.mjs';
import { textScaling } from './text-scaling.mjs';

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


/*
 * axe نمی‌تواند کنتراست روی گرادیان/تصویر را حساب کند («incomplete»). برای آن عنصرها متن را شفاف
 * می‌کنیم، عکس کل صفحه می‌گیریم و بدترین کنتراست رنگ متن با پیکسل‌های پشت خط‌های متن را می‌سنجیم
 * (آستانه ۳ برای متن بزرگ ≥۲۴px یا ≥۱۸٫۶۶px پررنگ، وگرنه ۴٫۵). رنگ متن نیمه‌شفاف روی بدترین پیکسل ترکیب می‌شود.
 */
async function sampleGradients( page, targets ) {
	const info = await page.evaluate( ( list ) => {
		const out = [];
		const seen = new Set();
		const describe = ( e ) => e.tagName.toLowerCase() + ( e.id ? `#${ e.id }` : '' ) + ( e.classList.length ? `.${ [ ...e.classList ].slice( 0, 3 ).join( '.' ) }` : '' );
		for ( const t of list ) {
			let all = [];
			try { all = [ ...document.querySelectorAll( t ) ]; } catch { /* انتخابگر ناسازگار */ }
			for ( const el of all ) {
				// رنگ و اندازه از والد مستقیم هر گره متنی (عنصر ریشه ممکن است فرزندهای رنگی دیگری داشته باشد)
				const walker = document.createTreeWalker( el, NodeFilter.SHOW_TEXT );
				const byParent = new Map();
				for ( let n = walker.nextNode(); n; n = walker.nextNode() ) {
					if ( ! n.textContent.trim() ) continue;
					const par = n.parentElement;
					if ( ! par || seen.has( par ) ) continue;
					if ( ! par.checkVisibility( { checkOpacity: true, checkVisibilityCSS: true } ) ) continue; // پنهان (منوی بسته، opacity: 0)
					const r = document.createRange();
					r.selectNodeContents( n );
					for ( const q of r.getClientRects() ) {
						if ( q.width <= 1 || q.height <= 1 || q.right < 0 || q.bottom < 0 ) continue;
						if ( ! byParent.has( par ) ) byParent.set( par, [] );
						byParent.get( par ).push( { x: q.x + scrollX, y: q.y + scrollY, w: q.width, h: q.height } );
					}
				}
				for ( const [ par, rects ] of byParent ) {
					seen.add( par );
					const cs = getComputedStyle( par );
					// متن با گرادیان (background-clip: text) یا پر‌شدن شفاف را نمی‌شود با رنگ عادی سنجید
					if ( cs.webkitTextFillColor === 'rgba(0, 0, 0, 0)' || cs.backgroundClip === 'text' || cs.webkitBackgroundClip === 'text' ) continue;
					par.setAttribute( 'data-a11y-c', String( out.length ) );
					out.push( { t: describe( par ), color: cs.color, size: parseFloat( cs.fontSize ), bold: parseInt( cs.fontWeight, 10 ) >= 700, rects: [] } );
				}
			}
		}
		return out;
	}, targets );
	if ( ! info.length ) return [];
	await page.addStyleTag( { content: '*{color:transparent!important;-webkit-text-fill-color:transparent!important;text-shadow:none!important}' } );
	const res = [];
	for ( let i = 0; i < info.length; i++ ) {
		// هر عنصر در نمای واقعی مرورگر (نه fullPage: هدر fixed آن‌جا جابه‌جا می‌شود)
		const geo = await page.evaluate( ( idx ) => {
			const par = document.querySelector( `[data-a11y-c="${ idx }"]` );
			if ( ! par ) return null;
			par.scrollIntoView( { block: 'center', inline: 'nearest' } );
			const rects = [];
			const walker = document.createTreeWalker( par, NodeFilter.SHOW_TEXT );
			for ( let n = walker.nextNode(); n; n = walker.nextNode() ) {
				if ( ! n.textContent.trim() || n.parentElement !== par ) continue;
				const r = document.createRange();
				r.selectNodeContents( n );
				for ( const q of r.getClientRects() ) if ( q.width > 1 && q.height > 1 ) rects.push( { x: q.x, y: q.y, w: q.width, h: q.height } );
			}
			return rects;
		}, i );
		if ( ! geo || ! geo.length ) continue;
		const x0 = Math.max( 0, Math.floor( Math.min( ...geo.map( ( q ) => q.x ) ) ) ), y0 = Math.max( 0, Math.floor( Math.min( ...geo.map( ( q ) => q.y ) ) ) );
		const x1 = Math.min( page.viewportSize().width, Math.ceil( Math.max( ...geo.map( ( q ) => q.x + q.w ) ) ) ), y1 = Math.min( page.viewportSize().height, Math.ceil( Math.max( ...geo.map( ( q ) => q.y + q.h ) ) ) );
		if ( x1 - x0 < 1 || y1 - y0 < 1 ) continue;
		const b64 = ( await page.screenshot( { clip: { x: x0, y: y0, width: x1 - x0, height: y1 - y0 } } ) ).toString( 'base64' );
		const item = info[ i ];
		const one = await page.evaluate( async ( [ data, rects, ox, oy, color ] ) => {
			const img = await createImageBitmap( await ( await fetch( `data:image/png;base64,${ data }` ) ).blob() );
			const cv = new OffscreenCanvas( img.width, img.height );
			const ctx = cv.getContext( '2d', { willReadFrequently: true } );
			ctx.drawImage( img, 0, 0 );
			const lum = ( r, g, b ) => { const f = ( c ) => { c /= 255; return c <= 0.03928 ? c / 12.92 : ( ( c + 0.055 ) / 1.055 ) ** 2.4; }; return 0.2126 * f( r ) + 0.7152 * f( g ) + 0.0722 * f( b ); };
			const m = color.match( /rgba?\(([^)]+)\)/ );
			if ( ! m ) return null;
			const [ r, g, b, a = 1 ] = m[ 1 ].split( /[ ,/]+/ ).filter( Boolean ).map( Number );
			let worst = 99, worstBg = '';
			for ( const q of rects ) {
				const x = Math.max( 0, Math.floor( q.x - ox ) ), y = Math.max( 0, Math.floor( q.y - oy ) );
				const w = Math.min( img.width - x, Math.ceil( q.w ) ), h = Math.min( img.height - y, Math.ceil( q.h ) );
				if ( w < 1 || h < 1 ) continue;
				const d = ctx.getImageData( x, y, w, h ).data;
				for ( let k = 0; k < d.length; k += 16 ) {
					const fr = r * a + d[ k ] * ( 1 - a ), fg = g * a + d[ k + 1 ] * ( 1 - a ), fb = b * a + d[ k + 2 ] * ( 1 - a );
					const l1 = lum( fr, fg, fb ), l2 = lum( d[ k ], d[ k + 1 ], d[ k + 2 ] );
					const ratio = ( Math.max( l1, l2 ) + 0.05 ) / ( Math.min( l1, l2 ) + 0.05 );
					if ( ratio < worst ) { worst = ratio; worstBg = `rgb(${ d[ k ] },${ d[ k + 1 ] },${ d[ k + 2 ] })`; }
				}
			}
			return { worst, worstBg };
		}, [ b64, geo, x0, y0, item.color ] );
		if ( ! one ) continue;
		const large = item.size >= 24 || ( item.size >= 18.66 && item.bold );
		res.push( { target: item.t, ratio: Math.round( one.worst * 100 ) / 100, need: large ? 3 : 4.5, fg: item.color, bg: one.worstBg, size: item.size } );
	}
	return res;
}

/*
 * بازچینی (WCAG 1.4.10): در عرض ۳۲۰px (برابر بزرگنمایی ۴۰۰٪ روی ۱۲۸۰) نباید اسکرول افقی صفحه پیدا شود.
 * عنصرهایی که از لبه راست/چپ بیرون می‌زنند و داخل کانتینر اسکرول‌شونده (overflow auto/scroll/hidden
 * مثل جدول یا اسلایدر) نیستند گزارش می‌شوند.
 */
async function reflow( browser, htmlFile ) {
	const ctx = await browser.newContext( { viewport: { width: 320, height: 640 }, locale: 'fa-IR', reducedMotion: 'reduce' } );
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
	await page.waitForTimeout( 300 );
	const res = await page.evaluate( () => {
		const w = document.documentElement.clientWidth;
		const scrollW = Math.max( document.documentElement.scrollWidth, document.body.scrollWidth );
		const bad = [];
		const clipped = ( el ) => { for ( let p = el.parentElement; p && p !== document.body; p = p.parentElement ) { const o = getComputedStyle( p ).overflowX; if ( o !== 'visible' ) return true; } return false; };
		for ( const el of document.querySelectorAll( 'body *' ) ) {
			const r = el.getBoundingClientRect();
			if ( r.width < 1 || r.height < 1 ) continue;
			if ( ( r.right > w + 1 || r.left < -1 ) && ! clipped( el ) && getComputedStyle( el ).position !== 'fixed' ) bad.push( el.tagName.toLowerCase() + ( el.id ? `#${ el.id }` : '' ) + ( el.classList.length ? `.${ [ ...el.classList ].slice( 0, 3 ).join( '.' ) }` : '' ) + ` [${ Math.round( r.left ) },${ Math.round( r.right ) }]` );
		}
		return { w, scrollW, bad: bad.slice( 0, 6 ) };
	} );
	await ctx.close();
	return res;
}

/** صفحه رندرشده را (مثل audit) در نمای داده‌شده باز می‌کند؛ برای تست‌های بزرگ‌نمایی/فاصله متن. */
async function openHtml( browser, htmlFile, vp ) {
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
	await page.waitForTimeout( 250 );
	return { ctx, page };
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
	const axe = await page.evaluate( async ( best ) => {
		const res = await window.axe.run( document, { runOnly: { type: 'tag', values: best ? [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice' ] : [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa' ] }, resultTypes: [ 'violations', 'incomplete' ] } );
		const pick = ( list ) => list.map( ( r ) => ( { id: r.id, impact: r.impact, help: r.help, nodes: r.nodes.map( ( n ) => ( { target: n.target.join( ' ' ), html: n.html.slice( 0, 160 ), msg: ( n.any[ 0 ] || n.all[ 0 ] || n.none[ 0 ] || {} ).message || '' } ) ) } ) );
		return { violations: pick( res.violations ), incomplete: pick( res.incomplete ) };
	}, !! process.env.A11Y_BEST );

	const gradTargets = [ ...new Set( axe.incomplete.filter( ( v ) => v.id === 'color-contrast' ).flatMap( ( v ) => v.nodes.map( ( n ) => n.target ) ) ) ].slice( 0, 400 );
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
	const gradient = await sampleGradients( page, gradTargets );
	const targets = await targetSize( page );
	await ctx.close();
	return { axe, keyboard, gradient, targets };
}

const browser = await chromium.launch( { executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium' } ).catch( () => chromium.launch() );
const pages = fs.readdirSync( outDir ).filter( ( f ) => f.endsWith( '.html' ) ).map( ( f ) => f.slice( 0, -5 ) ).filter( ( p ) => ! only.length || only.includes( p ) ).sort();
const report = {};
const reflowReport = {};
const textReport = {};
for ( const p of pages ) {
	for ( const vp of VIEWPORTS ) {
		report[ `${ p }@${ vp.name }` ] = await audit( browser, path.join( outDir, `${ p }.html` ), vp );
	}
	reflowReport[ p ] = await reflow( browser, path.join( outDir, `${ p }.html` ) );
	if ( ! process.env.A11Y_SKIP_TEXT ) textReport[ p ] = await textScaling( ( vp ) => openHtml( browser, path.join( outDir, `${ p }.html` ), vp ) );
}
await browser.close();
fs.writeFileSync( reportFile, JSON.stringify( { ...report, __reflow: reflowReport, __text: textReport }, null, 1 ) );

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
const grad = {};
for ( const [ key, r ] of Object.entries( report ) ) {
	for ( const g of r.gradient ) {
		if ( g.ratio >= g.need ) continue;
		( grad[ g.target ] ??= { ...g, pages: new Set() } ).pages.add( key );
	}
}
const gradRows = Object.values( grad ).sort( ( a, b ) => a.ratio - b.ratio );
console.log( `\nکنتراست روی گرادیان/تصویر (نمونه‌برداری پیکسلی): ${ gradRows.length } عنصر زیر آستانه` );
for ( const g of gradRows.slice( 0, 40 ) ) console.log( `   ${ g.ratio } < ${ g.need }  ${ g.target.slice( 0, 70 ) }  متن ${ g.fg } روی ${ g.bg } (${ g.size }px، ${ g.pages.size } نما)` );
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

const tgt = {};
for ( const [ key, r ] of Object.entries( report ) ) {
	for ( const t of r.targets ) ( tgt[ t.label ] ??= { ...t, pages: new Set() } ).pages.add( key );
}
const tgtRows = Object.values( tgt ).sort( ( a, b ) => b.pages.size - a.pages.size );
console.log( `\nاندازه هدف لمسی (۲٫۵٫۸، کمتر از ۲۴px و بدون استثنای فاصله): ${ tgtRows.length } کنترل` );
for ( const t of tgtRows.slice( 0, 40 ) ) console.log( `   ${ t.w }×${ t.h }  ${ t.label.slice( 0, 70 ) }  ← نزدیک ${ t.near.slice( 0, 40 ) } (${ t.state }؛ ${ t.pages.size } نما)\n        داخل: ${ t.in }` );

const over = Object.entries( reflowReport ).filter( ( [ , r ] ) => r.scrollW > r.w + 1 || r.bad.length );
console.log( `\nبازچینی ۳۲۰px (اسکرول افقی): ${ over.length } صفحه از ${ Object.keys( reflowReport ).length } مشکل دارند` );
for ( const [ k, r ] of over.slice( 0, 12 ) ) console.log( `   ${ k }: عرض محتوا ${ r.scrollW } > ${ r.w }؛ ${ r.bad.slice( 0, 3 ).join( ' | ' ) }` );

// فاصله متن (1.4.12) و بزرگ‌نمایی متن (1.4.4): فقط مشکل تازه نسبت به حالت پایه همان صفحه
const txt = {};
for ( const [ pg, rows ] of Object.entries( textReport ) ) {
	for ( const r of rows ) {
		const g = ( txt[ `${ r.mode }|${ r.kind }|${ r.label }` ] ??= { ...r, pages: new Set() } );
		g.pages.add( `${ pg }@${ r.vp }` );
	}
}
for ( const mode of [ 'spacing', 'text200' ] ) {
	const list = Object.values( txt ).filter( ( g ) => g.mode === mode ).sort( ( a, b ) => b.pages.size - a.pages.size );
	console.log( `\n${ mode === 'spacing' ? 'فاصله متن (۱٫۴٫۱۲)' : 'متن ۲۰۰٪ (۱٫۴٫۴)' }: ${ list.length } مورد تازه نسبت به حالت پایه` );
	for ( const g of list.slice( 0, 40 ) ) console.log( `   ${ g.kind }  ${ g.label.slice( 0, 70 ) }  ${ g.detail } (${ g.state }؛ ${ g.pages.size } نما؛ مثلا ${ [ ...g.pages ][ 0 ] })${ process.env.A11Y_TEXT_DEBUG ? `\n        ${ g.html ?? '' }` : '' }` );
}
