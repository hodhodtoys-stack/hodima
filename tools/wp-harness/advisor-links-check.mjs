/**
 * آزمون «بررسی لینک‌ها» زیر هر گروه رقابت دستار سئو (SEO 2.9.7؛ core/advisor/advisor-links*.php + advisor-admin.js/.css)
 * در Chromium واقعی با ورود واقعی مدیر روی وردپرس ابزار تست (php -S).
 *   tools/wp-harness/advisor-links-check.sh        (این فایل را راه می‌اندازد)
 *
 * می‌سنجد: دکمه «بررسی لینک‌ها» فقط برای گروه «برنده روشن»، کلیک ← admin-ajax با nonce واقعی صفحه ← جدول با
 * لینک اشتباه/خراب/بدون‌لینک و نشان‌ها، «بررسی دوباره»، دکمه ویرایش هر ردیف، بدون خطای کنسول، بدون سرریز افقی در
 * ۳۹۰ و تبدیل جدول به کارت، و عکس ۱۳۰۰/۳۹۰. خروجی: ✔/✘؛ کد خروج ۱ اگر یکی رد شود. عکس‌ها: $HODIMA_HARNESS/advisor-links.
 */
import { chromium } from 'playwright-core';
import fs from 'node:fs';
import path from 'node:path';

const port = process.env.CCSS_PORT || '8767';
const origin = `http://127.0.0.1:${ port }`;
const site = 'https://hodima.test';
const harness = process.env.HODIMA_HARNESS || '/tmp/hodima-harness';
const shots = path.join( harness, 'advisor-links' );
fs.mkdirSync( shots, { recursive: true } );

const browser = await chromium.launch( { executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium' } ).catch( () => chromium.launch() );
let failures = 0;
const log = ( ok, name, detail = '' ) => {
	if ( ! ok ) failures++;
	console.log( `  ${ ok ? '✔' : '✘' } ${ name }${ detail ? ` — ${ detail }` : '' }` );
};

async function newContext( width ) {
	const ctx = await browser.newContext( { viewport: { width, height: 900 }, ignoreHTTPSErrors: true, locale: 'fa-IR' } );
	await ctx.route( /^https:\/\/hodima\.test(?:[/?#]|$)/, async ( route ) => {
		const req = route.request();
		const headers = { ...req.headers(), host: 'hodima.test' };
		delete headers[ 'content-length' ];
		try {
			const res = await route.fetch( { url: req.url().replace( site, origin ), headers, maxRedirects: 0 } );
			const out = { status: res.status(), headers: res.headers(), body: await res.body() };
			if ( out.headers.location ) {
				const to = JSON.stringify( out.headers.location.replace( origin, site ) );
				await route.fulfill( { status: 200, contentType: 'text/html', body: `<!doctype html><script>location.replace(${ to })</script>` } );
				return;
			}
			await route.fulfill( out );
		} catch {
			await route.abort();
		}
	} );
	return ctx;
}

async function login( ctx ) {
	const body = new URLSearchParams( { log: 'advadmin', pwd: 'adv-pass-123', 'wp-submit': 'Log In', redirect_to: `${ site }/wp-admin/`, testcookie: '1' } );
	const res = await fetch( `${ origin }/wp-login.php`, { method: 'POST', body, redirect: 'manual', headers: { host: 'hodima.test', cookie: 'wordpress_test_cookie=WP%20Cookie%20check' } } );
	const cookies = res.headers.getSetCookie().filter( ( c ) => /^wordpress_(sec|logged_in)_/.test( c ) ).map( ( c ) => {
		const [ pair, ...attrs ] = c.split( ';' ).map( ( x ) => x.trim() );
		const eq = pair.indexOf( '=' );
		const cpath = attrs.find( ( x ) => x.toLowerCase().startsWith( 'path=' ) )?.slice( 5 ) || '/';
		return { name: pair.slice( 0, eq ), value: pair.slice( eq + 1 ), domain: 'hodima.test', path: cpath, secure: true, httpOnly: true };
	} );
	if ( ! cookies.length ) throw new Error( 'ورود مدیر ناموفق بود' );
	await ctx.addCookies( cookies );
	return ctx.newPage();
}

for ( const width of [ 1300, 390 ] ) {
	console.log( `=== عرض ${ width }` );
	const ctx = await newContext( width );
	const pg = await login( ctx );
	const errors = [];
	pg.on( 'pageerror', ( e ) => { if ( process.env.DEBUG_STACK ) console.log( e.stack ); if ( ! /^wp is not defined$/.test( e.message ) ) errors.push( String( e.message ) ); } ); // خطای wp.i18n داخلی هسته در ابزار تست (اسکریپت پیش از wp-i18n)
	pg.on( 'console', ( m ) => { if ( 'error' === m.type() && ! /Failed to load resource/.test( m.text() ) ) errors.push( m.text() ); } );

	await pg.goto( `${ site }/wp-admin/admin.php?page=hodima-advisor&tab=competition`, { waitUntil: 'load' } );
	const boxes = pg.locator( '[data-hdsa-links]' );
	log( 1 === await boxes.count(), 'بلوک «لینک‌های این کلمه» فقط برای گروه «برنده روشن» (گروه هم‌وزن ندارد)', `${ await boxes.count() } بلوک` );

	await pg.locator( 'details.hodima-advisor__group' ).first().evaluate( ( el ) => { el.open = true; } );
	await pg.screenshot( { path: path.join( shots, `before-${ width }.png` ), fullPage: true } );

	const run = boxes.first().locator( '[data-hdsa-links-run]' ).first();
	await run.click();
	await pg.waitForSelector( '.hodima-advisor__lk-table', { timeout: 30000 } );
	const box = boxes.first();
	const sums = ( await box.locator( '.hodima-advisor__lk-sums .hds-pill' ).allTextContents() ).map( ( t ) => t.replace( /\s+/g, ' ' ).trim() );
	log( 3 === sums.length && /8/.test( sums[ 0 ] ) && /1/.test( sums[ 1 ] ) && /2/.test( sums[ 2 ] ), 'نشان‌های خلاصه: ۸ لینک به رقیب، ۱ خراب، ۲ درست', sums.join( ' | ' ) );
	log( await box.locator( '[data-hdsa-links-hint]' ).isHidden(), 'متن راهنمای پیش از بررسی پنهان می‌شود' );
	log( /در\s*[3۳]\s*صفحه/.test( await box.evaluate( ( el ) => el.textContent ) ), 'لینک ساختاری فوتر: یک ردیف «در ۳ صفحه» (آخرین ردیف، پشت «همه ردیف‌ها»)' );

	const inv = await box.locator( '.is-inventory' ).innerText();
	log( inv.includes( 'برنده' ) && inv.includes( 'رقیب' ) && /5/.test( inv ), 'جدول «لینک‌های ورودی هر صفحه» با جمع خزش ۵ برای رقیب' );
	log( await box.locator( '.hodima-advisor__lk-rel' ).count() === 1, 'جمله رابطه (رقیب داخل دسته برنده است)' );

	const links = box.locator( '.hodima-advisor__lk-table:not(.is-inventory):not(.is-opps) tbody tr' );
	const n = await links.count();
	log( n >= 9, 'ردیف‌های جدول لینک‌ها (۸ باز و بقیه پشت «همه ردیف‌ها»)', `${ n } ردیف` );
	const edits = await box.locator( '.hodima-advisor__lk-table a.hodima-advisor__act' ).evaluateAll( ( as ) => as.filter( ( a ) => /post\.php|term\.php|nav-menus\.php/.test( a.href ) ).length );
	log( edits >= 8, 'دکمه ویرایش به صفحه ویرایش هر ردیف می‌رود', `${ edits } دکمه` );
	const text = await box.locator( '.hodima-advisor__lk-table:not(.is-inventory):not(.is-opps)' ).first().innerText();
	log( text.includes( '/207/' ) && text.includes( '/lk-cat/' ) && ! text.includes( '/20700/' ), 'مقصد فعلی /207/ و مقصد درست /lk-cat/؛ /20700/ قاطی نمی‌شود' );
	log( text.includes( 'لینک‌های مرتبط' ) && text.includes( 'منو' ) && text.includes( 'متن نوشته' ), 'ستون «جای ذخیره»: متن نوشته، لینک‌های مرتبط، منو' );
	const opps = await box.locator( '.is-opps' ).innerText();
	log( opps.includes( 'گیره انبری فلزی' ) && opps.includes( 'لینک کنید' ), 'فرصت لینک: تکه‌ای از متن و پیشنهاد' );

	await boxes.first().locator( '[data-force]' ).click();
	await pg.waitForFunction( () => ! document.querySelector( '[data-hdsa-links-out]' )?.hasAttribute( 'aria-busy' ) );
	log( await boxes.first().locator( '.hodima-advisor__lk-table' ).count() >= 1, '«بررسی دوباره» نتیجه را دوباره می‌آورد' );

	const overflow = await pg.evaluate( () => document.documentElement.scrollWidth - document.documentElement.clientWidth );
	log( overflow <= 0, 'بدون سرریز افقی صفحه', `${ overflow }px` );
	if ( 390 === width ) {
		const display = await pg.locator( '.hodima-advisor__lk-table tr' ).nth( 1 ).evaluate( ( el ) => getComputedStyle( el ).display );
		log( 'grid' === display, 'موبایل: جدول به کارت تبدیل می‌شود', display );
	}
	await boxes.first().scrollIntoViewIfNeeded();
	await pg.screenshot( { path: path.join( shots, `after-${ width }.png` ), fullPage: true } );
	log( 0 === errors.length, 'بدون خطای جاوااسکریپت', errors.join( ' | ' ) );
	await ctx.close();
}

await browser.close();
console.log( failures ? `\n✘ ${ failures } مورد رد شد` : '\n✔ همه موردها قبول' );
process.exit( failures ? 1 : 0 );
