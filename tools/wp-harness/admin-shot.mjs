/**
 * عکس و axe یک صفحه پیشخوان رندرشده (admin-render.php / admin-render-screen.php) با CSS/JS واقعی.
 *
 *   [HODIMA_RTL=1] node admin-shot.mjs <html-file> <out-prefix> <wp-root> <src-root> [عرض‌ها=1300,390] [انتخابگر]
 *
 * HODIMA_CLICK='#tab-a|#tab-b': انتخابگرهایی که پیش از عکس و axe به ترتیب کلیک می‌شوند (باز کردن تب/جمع‌شونده؛
 * هر تب جدا عکس و axe می‌خواهد چون بخش‌های بسته در axe دیده نمی‌شوند).
 *
 * خروجی: <out-prefix>-<عرض>.png (کل صفحه یا فقط انتخابگر) و خلاصه axe (wcag2a/aa، 21a/aa، 22aa) فقط برای
 * داخل انتخابگر (پیش‌فرض #wpbody-content). CSS/JS قالب و افزونه‌ها از <src-root>، بقیه از وردپرس ابزار تست.
 * CSS خود پیشخوان (load-styles.php) که بسته playground ندارد یک بار از مخزن رسمی وردپرس 6.7 در
 * ~/.cache/hodima/wp-core-6.7 گرفته می‌شود. سایت ابزار تست انگلیسی و چپ‌به‌راست است؛ با HODIMA_RTL=1 صفحه و
 * CSS وردپرس راست‌به‌چپ می‌شوند (مثل پیشخوان فارسی سایت). jQuery و wp.media (load-scripts.php) نیستند، پس فقط
 * اسکریپت‌هایی که بدون آن‌ها راه می‌افتند اجرا می‌شوند؛ خطای اسکریپت‌های درون‌خطی خود وردپرس شمرده نمی‌شود.
 * HODIMA_MEASURE=<فایل jsonl> (+ HODIMA_LABEL=<نام صفحه>): اندازه‌گیری قاعده‌های چیدمان عددی هم انجام و یک خط JSON
 * اضافه می‌شود (layout-metrics.mjs؛ فقط گزارش، کد خروج را عوض نمی‌کند؛ ابزار: layout-rules.sh).
 * کد خروج ۱ اگر axe نقضی در محدوده پیدا کند یا JS هدیما خطا بدهد.
 */
import { chromium } from 'playwright-core';
import fs from 'node:fs';
import path from 'node:path';
import os from 'node:os';
import { execFileSync } from 'node:child_process';
import { createRequire } from 'node:module';
import { measureInPage } from './layout-metrics.mjs';

const require = createRequire( import.meta.url );
const axePath = require.resolve( 'axe-core/axe.min.js' );
const [ htmlFile, outPrefix, wpRoot, src, widthsArg = '1300,390', selector = '' ] = process.argv.slice( 2 );
const mime = { '.css': 'text/css', '.js': 'application/javascript', '.woff2': 'font/woff2', '.woff': 'font/woff', '.svg': 'image/svg+xml', '.png': 'image/png', '.gif': 'image/gif', '.jpg': 'image/jpeg', '.webp': 'image/webp' };

/** فونت dashicons در ابزار تست نیست: هر نماد با mask از dashicons.svg (مثل settings-tab-check.mjs). */
const shim = ( () => {
	const svgFile = path.join( wpRoot, 'wp-includes/fonts/dashicons.svg' );
	let css = '.dashicons,.dashicons-before::before{display:inline-block;width:20px;height:20px;line-height:1;font-style:normal;text-align:center}\n';
	if ( ! fs.existsSync( svgFile ) ) {
		return css;
	}
	for ( const [ , name, body ] of fs.readFileSync( svgFile, 'utf8' ).matchAll( /<symbol[^>]*id="([^"]+)"[^>]*>(.*?)<\/symbol>/gs ) ) {
		const url = 'data:image/svg+xml,' + encodeURIComponent( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">' + body.replace( /<title>.*?<\/title>/s, '' ) + '</svg>' );
		css += `.dashicons-${ name }::before{content:"";display:block;width:1em;height:1em;margin:auto;background:currentColor;-webkit-mask:url("${ url }") center/contain no-repeat;mask:url("${ url }") center/contain no-repeat}\n`;
	}
	return css;
} )();

/**
 * وردپرس ابزار تست (بسته playground) CSS پیشخوان (wp-admin/css) را ندارد: یک بار از مخزن رسمی وردپرس
 * (شاخه 6.7، با curl و پراکسی محیط) در ~/.cache/hodima/wp-core-6.7 گرفته و نگه داشته می‌شود.
 */
const cacheDir = path.join( os.homedir(), '.cache/hodima/wp-core-6.7' );
const coreFile = ( rel ) => {
	const local = path.join( wpRoot, rel );
	if ( fs.existsSync( local ) ) {
		return local;
	}
	const cached = path.join( cacheDir, rel );
	if ( fs.existsSync( cached ) ) {
		return fs.statSync( cached ).size ? cached : null;
	}
	fs.mkdirSync( path.dirname( cached ), { recursive: true } );
	try {
		execFileSync( 'curl', [ '-sSfL', '--max-time', '20', '-o', cached, `https://raw.githubusercontent.com/WordPress/WordPress/6.7/${ rel }` ], { stdio: 'ignore' } );
	} catch {
		fs.writeFileSync( cached, '' ); // نیست (یا شبکه نیست): دوباره درخواست نشود
	}
	return fs.statSync( cached ).size ? cached : null;
};

/** load-styles.php وردپرس: handleها (load[chunk_0]=common,forms,…) ← فایل‌های CSS همان نسخه (با dir=rtl نسخه راست‌به‌چپ). */
const wpStyles = ( params ) => {
	const handles = [ ...params.entries() ].filter( ( [ k ] ) => k.startsWith( 'load' ) ).flatMap( ( [ , v ] ) => v.split( ',' ) );
	return handles.map( ( h ) => {
		const rtl = 'rtl' === params.get( 'dir' );
		for ( const dir of [ 'wp-admin/css', 'wp-includes/css' ] ) {
			for ( const name of rtl ? [ `${ h }-rtl.min.css`, `${ h }.min.css` ] : [ `${ h }.min.css` ] ) {
				const f = coreFile( `${ dir }/${ name }` );
				if ( f ) {
					return fs.readFileSync( f, 'utf8' ).replace( /url\((['"]?)\.\.\//g, `url($1/${ dir.split( '/' )[ 0 ] }/` );
				}
			}
		}
		return '';
	} ).join( '\n' );
};

const fileFor = ( pathname ) => {
	const p = decodeURIComponent( pathname );
	let m = p.match( /^\/wp-content\/themes\/hodima\/(.*)$/ );
	if ( m ) {
		return path.join( src, 'hodima', m[ 1 ] );
	}
	m = p.match( /^\/wp-content\/plugins\/(hodima-[a-z]+)\/(.*)$/ );
	return m ? path.join( src, 'plugins', m[ 1 ], m[ 2 ] ) : path.join( wpRoot, p );
};

const browser = await chromium.launch( { executablePath: process.env.CHROME || undefined } );
let fails = 0;
for ( const width of widthsArg.split( ',' ).map( Number ) ) {
	const ctx = await browser.newContext( { viewport: { width, height: 900 }, locale: 'fa-IR' } );
	const page = await ctx.newPage();
	const errors = [];
	// فقط خطای اسکریپت‌های هدیما (اسکریپت‌های درون‌خطی وردپرس بدون jQuery/wp ابزار تست خطا می‌دهند)
	page.on( 'pageerror', ( e ) => /\/(plugins\/hodima-|themes\/hodima\/)/.test( e.stack || '' ) && errors.push( e.message ) );
	await page.route( '**/*', async ( route ) => {
		const u = new URL( route.request().url() );
		if ( u.hostname !== 'hodima.test' ) {
			return route.abort();
		}
		if ( u.pathname === '/__page' ) {
			let html = fs.readFileSync( htmlFile, 'utf8' );
			// HODIMA_RTL=1: پیشخوان راست‌به‌چپ مثل سایت فارسی (وردپرس ابزار تست انگلیسی است)
			if ( process.env.HODIMA_RTL ) {
				html = html.replace( /<html([^>]*) dir="ltr"/, '<html$1 dir="rtl"' ).replace( /<html(?![^>]*dir=)/, '<html dir="rtl"' ).replaceAll( 'dir=ltr', 'dir=rtl' ).replace( /<body class="/, '<body class="rtl ' );
			}
			return route.fulfill( { contentType: 'text/html; charset=utf-8', body: html } );
		}
		if ( u.pathname.endsWith( '/load-styles.php' ) ) {
			return route.fulfill( { contentType: 'text/css', body: wpStyles( u.searchParams ) } );
		}
		if ( u.pathname.endsWith( '/load-scripts.php' ) ) {
			return route.fulfill( { contentType: 'application/javascript', body: '' } );
		}
		let f = fileFor( u.pathname );
		if ( ! fs.existsSync( f ) && /^\/wp-(admin|includes)\/.+\.(css|png|gif|svg|woff2?)$/.test( u.pathname ) ) {
			f = coreFile( u.pathname.slice( 1 ) ) || f;
		}
		if ( ! f.endsWith( '.php' ) && fs.existsSync( f ) && fs.statSync( f ).isFile() ) {
			return route.fulfill( { contentType: mime[ path.extname( f ) ] || 'application/octet-stream', body: fs.readFileSync( f ) } );
		}
		return route.fulfill( { status: 404, body: '' } );
	} );
	await page.goto( 'https://hodima.test/__page' );
	await page.addStyleTag( { content: shim } );
	await page.waitForTimeout( 400 );
	for ( const sel of ( process.env.HODIMA_CLICK || '' ).split( '|' ).filter( Boolean ) ) {
		await page.click( sel );
		await page.waitForTimeout( 150 );
	}
	const file = `${ outPrefix }-${ width }.png`;
	if ( selector ) {
		await page.locator( selector ).first().screenshot( { path: file } );
	} else {
		await page.screenshot( { path: file, fullPage: true } );
	}
	if ( process.env.HODIMA_MEASURE ) {
		const m = await page.evaluate( measureInPage );
		fs.appendFileSync( process.env.HODIMA_MEASURE, JSON.stringify( { label: process.env.HODIMA_LABEL || path.basename( htmlFile ), width, ...m } ) + '\n' );
	}
	await page.addScriptTag( { path: axePath } );
	const res = await page.evaluate( async ( sel ) => window.axe.run( { include: [ sel || '#wpbody-content' ] }, { runOnly: { type: 'tag', values: [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa' ] } } ), selector );
	const violations = res.violations.map( ( v ) => `${ v.id } (${ v.nodes.length }): ${ v.nodes.slice( 0, 3 ).map( ( n ) => n.target.join( ' ' ) ).join( ' | ' ) }` );
	fails += violations.length + errors.length;
	console.log( `${ width }px: ${ file } — axe ${ violations.length ? '✘ ' + violations.join( '; ' ) : '✔ بدون نقض' }${ errors.length ? ' — JS ✘ ' + errors.join( ' | ' ) : '' }` );
	await ctx.close();
}
await browser.close();
process.exit( fails ? 1 : 0 );
