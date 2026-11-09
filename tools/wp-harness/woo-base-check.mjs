/**
 * woo-base.css قالب در برابر CSS کامل ووکامرس (قالب 4.1.3؛ HODIMA-AUDIT.md بخش ۴۱۴).
 * اجرا با woo-base-check.sh — بعد از هر تغییر woo-base.css یا ارتقای بزرگ ووکامرس.
 *
 *   node woo-base-check.mjs <out-dir> <repo> [page ...]
 *
 * هر HTML ساخته‌شده (wc-run.sh؛ woo-base.css به‌جای سه فایل ووکامرس) راست‌به‌چپ مثل سایت باز
 * می‌شود؛ طرف «ref» همان HTML با سه فایل RTL خود ووکامرس (layout، smallscreen، general) به‌جای
 * woo-base است. استایل محاسبه‌شده همه عنصرها (و ::before/::after) در ۳۹۰/۸۲۰/۱۳۰۰ مقایسه می‌شود،
 * یک بار صفحه خام و یک بار با حالت‌های پویا (پیام‌های ووکامرس، blockUI، دکمه غیرفعال محصول متغیر).
 * تفاوت‌های عمدی (همان گزارش بخش ۴۱۴): رنگ‌های برند به‌جای رنگ ووکامرس، float/clear کارت‌های
 * داخل شبکه (grid؛ بی‌اثر)، text-align: start به‌جای right (در RTL یکی)، و از 4.1.4 ::before ستاره‌ها
 * (content/فونت/ماسک/اندازه کسری پیکسل: ستاره SVG به‌جای فونت؛ عکس قبل/بعد با visual-compare.sh --wc).
 * هر تفاوت دیگر بررسی شود.
 */
import { chromium } from 'playwright-core';
import fs from 'node:fs';
import path from 'node:path';

const [ outDir, repo, ...only ] = process.argv.slice( 2 );
const wpRoot = path.join( process.env.HODIMA_HARNESS_WC || '/tmp/hodima-harness-wc', 'wp' );
const pages = only.length ? only : [ 'product', 'product-var', 'product-out', 'product-sale', 'cat', 'subcat', 'shop', 'tag', 'search' ];
const MIME = { '.css': 'text/css', '.js': 'text/javascript', '.woff2': 'font/woff2', '.woff': 'font/woff', '.svg': 'image/svg+xml' };
const GREY = Buffer.from( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mN4+P/ffwAJpAPqf4/yxwAAAABJRU5ErkJggg==', 'base64' );
const WC = 'https://hodima.test/wp-content/plugins/woocommerce/assets/css/';
const SKIP = /^--wc-|^--woocommerce$|^--hodima-woo-loader$|^(perspective-origin|transform-origin|background-position)$/;
const DYNAMIC = `(() => {
	const w = document.querySelector( '.woocommerce-notices-wrapper' );
	if ( w ) w.innerHTML = '<div class="woocommerce-message" role="alert"><a href="#" class="button wc-forward">سبد</a> به سبد اضافه شد.</div><ul class="woocommerce-error" role="alert"><li>گزینه را انتخاب کنید.</li></ul><div class="woocommerce-info">اطلاع</div>';
	const f = document.querySelector( 'form.cart' );
	if ( f ) {
		const d = document.createElement( 'div' ); d.className = 'blockUI blockOverlay'; d.style.cssText = 'position:absolute;inset:0'; f.append( d );
		f.querySelector( '.single_add_to_cart_button' )?.classList.add( 'disabled', 'wc-variation-selection-needed' );
		const r = f.querySelector( '.reset_variations' ); if ( r ) r.style.visibility = 'visible';
	}
})()`;

function localFile( url ) {
	const u = new URL( url );
	if ( u.hostname !== 'hodima.test' ) return null;
	const p = decodeURIComponent( u.pathname );
	if ( p.startsWith( '/wp-content/themes/hodima/' ) ) return path.join( repo, 'hodima', p.slice( '/wp-content/themes/hodima/'.length ) );
	const m = p.match( /^\/wp-content\/plugins\/(hodima-[a-z]+)\/(.*)$/ );
	if ( m ) return path.join( repo, 'plugins', m[ 1 ], m[ 2 ] );
	if ( p.startsWith( '/wp-includes/' ) || p.startsWith( '/wp-content/plugins/' ) ) return path.join( wpRoot, p );
	return null;
}

/** HTML کار ← راست‌به‌چپ؛ ref: woo-base (و style چسبیده‌اش) ← سه فایل RTL ووکامرس در همان جا. */
function variant( html, ref ) {
	html = html.replace( /<html lang="[^"]*"/, '<html dir="rtl" lang="fa-IR"' );
	if ( ! ref ) return html;
	const links = `<link rel='stylesheet' id='woocommerce-layout-css' href='${ WC }woocommerce-layout-rtl.css' media='all' />\n`
		+ `<link rel='stylesheet' id='woocommerce-smallscreen-css' href='${ WC }woocommerce-smallscreen-rtl.css' media='only screen and (max-width: 768px)' />\n`
		+ `<link rel='stylesheet' id='woocommerce-general-css' href='${ WC }woocommerce-rtl.css' media='all' />\n`;
	const out = html.replace( /<link[^>]*id='hodima-woo-base-css'[^>]*>\n?(<style id='hodima-woo-base-inline-css'>[\s\S]*?<\/style>\n?)?/, links );
	if ( out === html ) throw new Error( 'woo-base.css در HTML نیست (wc-run.sh با کد فعلی؟)' );
	return out;
}

async function styles( browser, html, width, action ) {
	const ctx = await browser.newContext( { viewport: { width, height: 900 }, reducedMotion: 'reduce' } );
	const page = await ctx.newPage();
	await page.route( '**/*', async ( route ) => {
		const url = route.request().url();
		if ( url === 'https://hodima.test/__page__' ) return route.fulfill( { status: 200, contentType: 'text/html; charset=utf-8', body: html } );
		const file = localFile( url );
		if ( file && fs.existsSync( file ) && fs.statSync( file ).isFile() ) return route.fulfill( { status: 200, contentType: MIME[ path.extname( file ) ] || 'application/octet-stream', body: fs.readFileSync( file ) } );
		if ( route.request().resourceType() === 'image' ) return route.fulfill( { status: 200, contentType: 'image/png', body: GREY } );
		return route.fulfill( { status: 404, body: '' } );
	} );
	await page.goto( 'https://hodima.test/__page__', { waitUntil: 'load' } );
	await page.addStyleTag( { content: '*,*::before,*::after{animation:none!important;transition:none!important}' } );
	await page.evaluate( () => document.fonts.ready );
	if ( action ) await page.evaluate( action );
	const data = await page.evaluate( () => [ ...document.querySelectorAll( 'body, body *' ) ].flatMap( ( el, i ) => {
		const key = `${ i } ${ el.tagName.toLowerCase() }${ el.id ? '#' + el.id : '' }${ typeof el.className === 'string' && el.className.trim() ? '.' + el.className.trim().split( /\s+/ ).slice( 0, 3 ).join( '.' ) : '' }`;
		return [ null, '::before', '::after' ].map( ( pseudo ) => {
			const cs = getComputedStyle( el, pseudo );
			if ( pseudo && ( cs.content === 'none' || cs.content === 'normal' ) ) return [ key + pseudo, null ];
			const o = {};
			for ( let j = 0; j < cs.length; j++ ) o[ cs[ j ] ] = cs.getPropertyValue( cs[ j ] );
			return [ key + ( pseudo || '' ), o ];
		} );
	} ) );
	await ctx.close();
	return data;
}

const browser = await chromium.launch( { executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium' } ).catch( () => chromium.launch() );
let total = 0;
for ( const pg of pages ) {
	const raw = fs.readFileSync( path.join( outDir, `${ pg }.html` ), 'utf8' );
	for ( const [ label, action ] of [ [ 'خام', '' ], [ 'پویا', DYNAMIC ] ] ) {
		for ( const width of [ 390, 820, 1300 ] ) {
			const a = await styles( browser, variant( raw, true ), width, action );
			const b = await styles( browser, variant( raw, false ), width, action );
			const lines = [];
			for ( let i = 0; i < a.length; i++ ) {
				const [ key, x ] = a[ i ];
				const y = b[ i ]?.[ 1 ];
				if ( ! x || ! y ) {
					if ( !! x !== !! y ) lines.push( `${ key }: ${ x ? 'دارد' : '—' } → ${ y ? 'دارد' : '—' }` );
					continue;
				}
				const d = Object.keys( x ).filter( ( p ) => ! SKIP.test( p ) && x[ p ] !== y[ p ] && ! /^(border-(top|bottom|left|right|block-end|inline-start|inline-end)-color|row-rule-color|column-rule-color|outline-color|caret-color|text-decoration-color|text-emphasis-color|-webkit-)/.test( p ) );
				if ( d.length ) lines.push( `${ key }: ${ d.slice( 0, 6 ).map( ( p ) => `${ p } ${ x[ p ] } → ${ y[ p ] }` ).join( ' | ' ) }` );
			}
			total += lines.length;
			console.log( `${ lines.length ? '•' : '✔' } ${ pg } ${ label } ${ width }: ${ lines.length } تفاوت` );
			lines.slice( 0, 15 ).forEach( ( l ) => console.log( `    ${ l }` ) );
		}
	}
}
await browser.close();
console.log( `\nجمع: ${ total } تفاوت (عمدی‌ها در سرآغاز همین فایل؛ بقیه را بررسی کن)` );
