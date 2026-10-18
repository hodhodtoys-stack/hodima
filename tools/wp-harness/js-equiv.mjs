/**
 * هم‌ارزی JS سورس و JS کوچک‌شده ZIP (پرفورمنس مرحله ۲) — اسکیل hodima-theme.
 *
 *   node js-equiv.mjs <out-dir> <root-سورس> <root-ساخته‌شده> [page …]
 *
 * <root-ساخته‌شده> پوشه‌ای با hodima/ و plugins/hodima-*‌ است که از ZIPهای dist/ باز شده
 * (bin/build.sh ساخته؛ مثال در HODIMA-AUDIT.md بخش ۱۷۴). هر صفحه HTML ساخته‌شده ابزار تست دو بار در
 * Chromium (موبایل ۳۹۰ و دسکتاپ ۱۳۰۰) اجرا می‌شود: یک بار با JS سورس و یک بار با JS کوچک‌شده
 * (CSS و بقیه فایل‌ها هر دو از سورس تا فقط JS فرق کند). بعد از بارگذاری، یک اسکرول تا پایین،
 * کلیک روی همبرگر و باز کردن زیرمنوها، DOM نهایی (outerHTML بدون نویز زمان/تصادفی) و خطاهای
 * کنسول/صفحه مقایسه می‌شود. کنترل: دو اجرای سورس با هم هم مقایسه می‌شوند تا نویس اندازه‌گیری روشن باشد.
 * محدودیت: فقط راه‌اندازی و تعاملات ساده؛ رفتار عمیق (استوری، جستجو، اعلان…) با a11y-dynamic.mjs.
 */
import { chromium } from 'playwright-core';
import fs from 'node:fs';
import path from 'node:path';

const [ outDir, srcRoot, builtRoot, ...only ] = process.argv.slice( 2 );
if ( ! builtRoot ) {
	console.error( 'usage: js-equiv.mjs <out-dir> <src-root> <built-root> [page ...]' );
	process.exit( 2 );
}
const wpRoot = path.join( process.env.HODIMA_HARNESS_WP || process.env.HODIMA_HARNESS || '/tmp/hodima-harness', 'wp' );
const MIME = { '.css': 'text/css', '.js': 'text/javascript', '.woff2': 'font/woff2', '.woff': 'font/woff', '.svg': 'image/svg+xml', '.png': 'image/png' };
const GREY = Buffer.from( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mN4+P/ffwAJpAPqf4/yxwAAAABJRU5ErkJggg==', 'base64' );

function localFile( url, jsRoot ) {
	const u = new URL( url );
	if ( u.hostname !== 'hodima.test' ) return null;
	const p = decodeURIComponent( u.pathname );
	const root = p.endsWith( '.js' ) ? jsRoot : srcRoot;
	if ( p.startsWith( '/wp-content/themes/hodima/' ) ) return path.join( root, 'hodima', p.slice( '/wp-content/themes/hodima/'.length ) );
	const m = p.match( /^\/wp-content\/plugins\/(hodima-[a-z]+)\/(.*)$/ );
	if ( m ) return path.join( root, 'plugins', m[ 1 ], m[ 2 ] );
	// وردپرس ابزار تست بدون فایل‌های jQuery هسته است؛ بدون آن اسکریپت‌های ووکامرس اجرا نمی‌شوند
	if ( p === '/wp-includes/js/jquery/jquery.min.js' && ! fs.existsSync( path.join( wpRoot, p ) ) ) return process.env.HODIMA_JQUERY || '/usr/share/javascript/jquery/jquery.min.js';
	if ( p.startsWith( '/wp-includes/' ) || p.startsWith( '/wp-content/plugins/' ) ) return path.join( wpRoot, p );
	return null;
}

const pages = fs.readdirSync( outDir ).filter( ( f ) => f.endsWith( '.html' ) && ( ! only.length || only.includes( f.replace( /\.html$/, '' ) ) ) ).sort();
// HODIMA_JS_EQUIV_HTML2: پوشه HTML دیگر برای اجرای سوم (مثلا HTML بعد از تغییر PHP که چاپ اسکریپت‌ها را عوض می‌کند)
const html2 = process.env.HODIMA_JS_EQUIV_HTML2 || outDir;
const browser = await chromium.launch( { executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium' } ).catch( () => chromium.launch() );

// DOM نهایی بدون چیزهایی که هر اجرا فرق می‌کنند (زمان، شناسه تصادفی، سبک‌های انیمیشنی)
const snapshot = () => {
	const clone = document.documentElement.cloneNode( true );
	clone.querySelectorAll( 'script, noscript' ).forEach( ( n ) => n.remove() );
	return clone.outerHTML
		.replace( /\sstyle="[^"]*"/g, '' )
		.replace( /woo-sr-[a-z0-9]+/g, 'woo-sr-X' )
		.replace( /\?ver=[^"'&]+/g, '' )
		.replace( /\d{4}-\d\d-\d\dT[\d:+]+/g, 'T' ); // زمان انتشار/به‌روزرسانی در متا
};

async function run( file, width, jsRoot, htmlDir = outDir ) {
	const ctx = await browser.newContext( { viewport: { width, height: 800 }, locale: 'fa-IR', reducedMotion: 'reduce' } );
	const page = await ctx.newPage();
	const errors = [];
	page.on( 'pageerror', ( e ) => errors.push( e.message.split( '\n' )[ 0 ] ) );
	page.on( 'console', ( m ) => m.type() === 'error' && ! /Failed to load resource/.test( m.text() ) && errors.push( m.text().split( '\n' )[ 0 ] ) );
	await page.route( '**/*', async ( route ) => {
		const url = route.request().url();
		if ( url === 'https://hodima.test/__page__' ) return route.fulfill( { status: 200, contentType: 'text/html; charset=utf-8', body: fs.readFileSync( path.join( htmlDir, file ) ) } );
		const f = localFile( url, jsRoot );
		if ( f && fs.existsSync( f ) && fs.statSync( f ).isFile() ) return route.fulfill( { status: 200, contentType: MIME[ path.extname( f ) ] || 'application/octet-stream', body: fs.readFileSync( f ) } );
		if ( route.request().resourceType() === 'image' ) return route.fulfill( { status: 200, contentType: 'image/png', body: GREY } );
		return route.fulfill( { status: 404, body: '' } );
	} );
	await page.goto( 'https://hodima.test/__page__', { waitUntil: 'load' } );
	await page.waitForTimeout( 400 );
	await page.evaluate( () => window.scrollTo( 0, document.body.scrollHeight ) );
	await page.waitForTimeout( 200 );
	await page.evaluate( () => window.scrollTo( 0, 0 ) );
	// تعامل ساده: همبرگر، زیرمنوها، عنوان ستون‌های فوتر (آکاردئون موبایل)
	for ( const sel of [ '#mobileMenuTrigger', '.submenu-toggle', '.footer-col__toggle', '.hodima-scroller-arrow', '.h-dot:nth-child(2)' ] ) {
		for ( const el of await page.locator( sel ).all() ) {
			if ( await el.isVisible().catch( () => false ) ) await el.click( { timeout: 500, force: true } ).catch( () => {} );
			await page.waitForTimeout( 120 );
		}
	}
	await page.waitForTimeout( 300 );
	const html = await page.evaluate( snapshot );
	await ctx.close();
	return { html, errors };
}

let bad = 0;
let noisy = 0;
for ( const file of pages ) {
	for ( const width of [ 390, 1300 ] ) {
		const a = await run( file, width, srcRoot );
		const c = await run( file, width, srcRoot ); // کنترل نویز
		const b = await run( file, width, builtRoot, html2 );
		const control = a.html === c.html;
		const same = a.html === b.html && JSON.stringify( a.errors ) === JSON.stringify( b.errors );
		if ( ! control ) noisy++;
		if ( ! same && control ) {
			bad++;
			let i = 0;
			while ( i < a.html.length && a.html[ i ] === b.html[ i ] ) i++;
			console.log( `✘ ${ file }@${ width }: تفاوت DOM/خطا؛ نزدیک «${ a.html.slice( Math.max( 0, i - 60 ), i + 80 ).replace( /\s+/g, ' ' ) }» ← «${ b.html.slice( Math.max( 0, i - 60 ), i + 80 ).replace( /\s+/g, ' ' ) }» | خطا سورس=${ a.errors.length } ساخته=${ b.errors.length } ${ JSON.stringify( b.errors.filter( ( e ) => ! a.errors.includes( e ) ) ) }` );
		} else if ( ! control ) {
			console.log( `~ ${ file }@${ width }: کنترل خودش پایدار نبود (نویز)؛ مقایسه نشد` );
		}
	}
}
await browser.close();
console.log( `${ bad ? '✘' : '✔' } ${ pages.length } صفحه × ۲ عرض — ${ bad } تفاوت، ${ noisy } مورد ناپایدار` );
process.exit( bad ? 1 : 0 );
