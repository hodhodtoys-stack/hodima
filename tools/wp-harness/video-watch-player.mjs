/**
 * آزمون پلیر صفحه ویدیو (Hodima Media، video-watch.front.js) در Chromium واقعی.
 *   node tools/wp-harness/video-watch-player.mjs [root]
 * صفحه /video/hairpin-video/ ابزار تست را با PHP همین کد می‌سازد، فایل ویدیو را با
 * یک webm بیست‌ثانیه‌ای (ffmpeg) عوض می‌کند و درخواست‌های ثبت آمار را می‌شمارد.
 *
 * باگ تا Media 2.0.8 (HODIMA-AUDIT.md بخش ۲۱۶): «رسیدن جای پخش به ۱۵٪» بازدید بود،
 * پس پرش با ?t= (لحظه‌های کلیدی گوگل) یا کلیک روی نوار بدون تماشا بازدید می‌شد.
 * حالا فقط تماشای واقعی: ۱۵٪ مدت (اینجا ۳ ثانیه)، حداکثر ۳۰ ثانیه.
 * خروجی: هر بررسی ✔ یا ✘؛ کد خروج ۱ اگر یکی رد شود.
 */
import { chromium } from 'playwright-core';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const here = path.dirname( fileURLToPath( import.meta.url ) );
const root = path.resolve( process.argv[ 2 ] || path.join( here, '../..' ) );
const wp = process.env.HODIMA_WP || path.join( process.env.HODIMA_HARNESS || '/tmp/hodima-harness', 'wp' );

const webm = path.join( os.tmpdir(), 'hodima-video-watch-20s.webm' );
if ( ! fs.existsSync( webm ) ) {
	execFileSync( 'ffmpeg', [ '-v', 'error', '-f', 'lavfi', '-i', 'color=c=0x25316a:s=320x180:d=20:r=10', '-c:v', 'libvpx', '-b:v', '50k', '-y', webm ] );
}

const html = execFileSync( 'php', [ path.join( here, 'render.php' ), '/video/hairpin-video/', 'full' ], {
	env: { ...process.env, HARNESS: '1', HODIMA_WP: wp },
	maxBuffer: 32 * 1024 * 1024,
} ).toString();
if ( ! html.includes( 'data-player-instance' ) ) {
	console.error( '✘ صفحه ویدیو ساخته نشد (پلیر در HTML نیست)' );
	process.exit( 1 );
}

const localFile = ( url ) => {
	const u = new URL( url );
	if ( u.hostname !== 'hodima.test' ) return null;
	const p = decodeURIComponent( u.pathname );
	const m = p.match( /^\/wp-content\/plugins\/(hodima-[a-z]+)\/(.*)$/ );
	if ( m ) return path.join( root, 'plugins', m[ 1 ], m[ 2 ] );
	if ( p.startsWith( '/wp-content/themes/hodima/' ) ) return path.join( root, 'hodima', p.slice( '/wp-content/themes/hodima/'.length ) );
	if ( p.startsWith( '/wp-includes/' ) ) return path.join( wp, p );
	return null;
};
const MIME = { '.css': 'text/css', '.js': 'text/javascript', '.woff2': 'font/woff2' };

const browser = await chromium.launch( { executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium', args: [ '--autoplay-policy=no-user-gesture-required' ] } )
	.catch( () => chromium.launch() );
let failures = 0;
const log = ( ok, name, detail = '' ) => {
	if ( ! ok ) failures++;
	console.log( `  ${ ok ? '✔' : '✘' } ${ name }${ detail ? ` — ${ detail }` : '' }` );
};

/** صفحه را باز می‌کند؛ tracks = بدنه درخواست‌های ثبت آمار. */
async function open( query = '' ) {
	const ctx = await browser.newContext( { viewport: { width: 1300, height: 900 } } );
	const page = await ctx.newPage();
	const tracks = [];
	const errors = [];
	page.on( 'pageerror', ( e ) => errors.push( e.message ) );
	await page.route( '**/*', async ( route ) => {
		const req = route.request();
		const url = req.url();
		if ( url.startsWith( 'https://hodima.test/video/hairpin-video/' ) ) return route.fulfill( { status: 200, contentType: 'text/html; charset=utf-8', body: html } );
		if ( url.includes( '/v/pin.mp4' ) ) {
			// پاسخ Range لازم است؛ بدون آن Chromium فایل را «قابل پرش» نمی‌داند و currentTime صفر می‌ماند
			const data = fs.readFileSync( webm );
			const m = /bytes=(\d+)-(\d*)/.exec( req.headers().range || '' );
			const start = m ? Number( m[ 1 ] ) : 0;
			const end = m && m[ 2 ] ? Number( m[ 2 ] ) : data.length - 1;
			return route.fulfill( {
				status: m ? 206 : 200,
				contentType: 'video/webm',
				headers: { 'accept-ranges': 'bytes', ...( m ? { 'content-range': `bytes ${ start }-${ end }/${ data.length }` } : {} ) },
				body: data.subarray( start, end + 1 ),
			} );
		}
		if ( url.includes( '/video-watch/v1/track' ) ) {
			tracks.push( JSON.parse( req.postData() || '{}' ) );
			return route.fulfill( { status: 200, contentType: 'application/json', body: JSON.stringify( { success: true, counted: true, views: 1234 } ) } );
		}
		const file = localFile( url );
		if ( file && fs.existsSync( file ) && fs.statSync( file ).isFile() ) return route.fulfill( { status: 200, contentType: MIME[ path.extname( file ) ] || 'application/octet-stream', body: fs.readFileSync( file ) } );
		return route.fulfill( { status: 404, body: '' } );
	} );
	await page.goto( 'https://hodima.test/video/hairpin-video/' + query );
	await page.waitForFunction( () => document.querySelector( '.hs-main-video' )?.readyState >= 1, null, { timeout: 10000 } );
	return { ctx, page, tracks, errors };
}

const views = ( tracks ) => tracks.filter( ( t ) => t.action === 'views' ).length;

console.log( '=== پرش با ?t= بدون پخش: بازدید نیست' );
{
	const { ctx, page, tracks, errors } = await open( '?t=12' );
	await page.waitForTimeout( 1500 );
	const pos = await page.evaluate( () => document.querySelector( '.hs-main-video' ).currentTime );
	log( Math.round( pos ) === 12, 'پلیر به ثانیه ۱۲ رفت', `currentTime=${ pos }` );
	log( views( tracks ) === 0, 'هیچ درخواست بازدید', `${ views( tracks ) } درخواست` );
	await page.click( '.hs-progress-wrap', { position: { x: 300, y: 10 } } );
	await page.waitForTimeout( 800 );
	const pos2 = await page.evaluate( () => document.querySelector( '.hs-main-video' ).currentTime );
	log( Math.abs( pos2 - pos ) > 1, 'کلیک روی نوار جای پخش را عوض کرد', `currentTime=${ pos2 }` );
	log( views( tracks ) === 0, 'کلیک روی نوار پیشرفت هم بازدید نیست', `${ views( tracks ) } درخواست` );
	log( errors.length === 0, 'بدون خطای JS', errors.join( ' | ' ) );
	await ctx.close();
}

console.log( '=== پرش و بعد کمتر از ۳ ثانیه پخش: بازدید نیست' );
{
	const { ctx, page, tracks } = await open( '?t=15' );
	await page.evaluate( () => { const v = document.querySelector( '.hs-main-video' ); v.muted = true; return v.play(); } );
	await page.waitForTimeout( 1500 );
	await page.evaluate( () => document.querySelector( '.hs-main-video' ).pause() );
	await page.waitForTimeout( 400 );
	log( views( tracks ) === 0, '۱.۵ ثانیه تماشا از ثانیه ۱۵: هنوز بازدید نیست', `${ views( tracks ) } درخواست` );
	await ctx.close();
}

console.log( '=== تماشای واقعی: یک بازدید' );
{
	const { ctx, page, tracks, errors } = await open();
	await page.evaluate( () => { const v = document.querySelector( '.hs-main-video' ); v.muted = true; return v.play(); } );
	await page.waitForTimeout( 2000 );
	log( views( tracks ) === 0, '۲ ثانیه تماشا (زیر ۱۵٪): هنوز نه', `${ views( tracks ) } درخواست` );
	// پرش وسط تماشا به جمع ثانیه‌ها اضافه نمی‌شود، ولی ثانیه‌های تماشاشده می‌مانند
	const before = await page.evaluate( () => document.querySelector( '.hs-main-video' ).currentTime );
	await page.evaluate( () => { document.querySelector( '.hs-main-video' ).currentTime += 8; } );
	await page.waitForTimeout( 400 );
	const after = await page.evaluate( () => document.querySelector( '.hs-main-video' ).currentTime );
	log( after - before >= 8, 'پرش ۸ ثانیه‌ای وسط پخش انجام شد', `${ before.toFixed( 1 ) } → ${ after.toFixed( 1 ) }` );
	log( views( tracks ) === 0, 'خود پرش جزو تماشا حساب نشد', `${ views( tracks ) } درخواست` );
	await page.waitForTimeout( 1800 );
	log( views( tracks ) === 1, 'بعد از ۳ ثانیه تماشای واقعی: یک درخواست', `${ views( tracks ) } درخواست` );
	await page.waitForTimeout( 1500 );
	log( views( tracks ) === 1, 'ادامه پخش: درخواست دوم نیست', `${ views( tracks ) } درخواست` );
	const shown = await page.textContent( '.hs-live-view-count' );
	log( shown === '۱٬۲۳۴', 'عدد پاسخ سرور در صفحه', shown );
	const body = tracks[ 0 ] || {};
	log( body.action === 'views' && String( body.id ) !== '', 'بدنه درخواست: شناسه و نوع', JSON.stringify( body ) );
	log( errors.length === 0, 'بدون خطای JS', errors.join( ' | ' ) );
	await ctx.close();
}

await browser.close();
console.log( failures ? `\n✘ ${ failures } بررسی رد شد` : '\n✔ همه بررسی‌ها قبول' );
process.exit( failures ? 1 : 0 );
