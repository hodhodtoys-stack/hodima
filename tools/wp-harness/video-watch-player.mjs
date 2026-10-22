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
import { createRequire } from 'node:module';

const here = path.dirname( fileURLToPath( import.meta.url ) );
const axePath = createRequire( import.meta.url ).resolve( 'axe-core/axe.min.js' );
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

/**
 * صفحه را باز می‌کند؛ tracks = بدنه درخواست‌های ثبت آمار.
 * broken = فایل ویدیو ۴۰۴؛ body = HTML دیگر (مثلا ویدیوی بدون آدرس).
 */
async function open( query = '', { broken = false, body = html, wait = true } = {} ) {
	const ctx = await browser.newContext( { viewport: { width: 1300, height: 900 } } );
	const page = await ctx.newPage();
	const tracks = [];
	const errors = [];
	page.on( 'pageerror', ( e ) => errors.push( e.message ) );
	await page.route( '**/*', async ( route ) => {
		const req = route.request();
		const url = req.url();
		if ( url.startsWith( 'https://hodima.test/video/hairpin-video/' ) ) return route.fulfill( { status: 200, contentType: 'text/html; charset=utf-8', body } );
		if ( url.includes( '/v/pin.mp4' ) && broken ) return route.fulfill( { status: 404, body: '' } );
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
	if ( wait ) {
		await page.waitForFunction( () => document.querySelector( '.hs-main-video' )?.readyState >= 1, null, { timeout: 10000 } );
	}
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

/** با Tab (مثل کاربر صفحه‌کلید) به عنصر می‌رسد؛ true اگر رسید. */
async function tabTo( page, selector, max = 60 ) {
	for ( let i = 0; i < max; i++ ) {
		await page.keyboard.press( 'Tab' );
		if ( await page.evaluate( ( s ) => document.activeElement?.matches( s ), selector ) ) return true;
	}
	return false;
}
const state = ( page ) => page.evaluate( () => {
	const v = document.querySelector( '.hs-main-video' );
	const a = document.activeElement;
	return { paused: v.paused, muted: v.muted, volume: v.volume, time: v.currentTime, rate: v.playbackRate, duration: v.duration, active: a ? `${ a.className }` : '' };
} );
const attr = ( page, sel, name ) => page.getAttribute( sel, name );

console.log( '=== صفحه‌کلید: Space روی دکمه کار همان دکمه است (مرحله ۳)' );
{
	const { ctx, page, errors } = await open();
	log( await tabTo( page, '.hs-mute-btn' ), 'Tab به دکمه صدا می‌رسد' );
	log( await attr( page, '.hs-mute-btn', 'aria-label' ) === 'قطع صدا', 'نام دکمه: «قطع صدا»' );
	await page.keyboard.press( ' ' );
	await page.waitForTimeout( 300 );
	let st = await state( page );
	log( st.muted && st.paused, 'Space روی دکمه صدا: بی‌صدا شد و ویدیو پخش نشد', JSON.stringify( { muted: st.muted, paused: st.paused } ) );
	log( await attr( page, '.hs-mute-btn', 'aria-label' ) === 'وصل صدا', 'نام دکمه: «وصل صدا»' );
	await page.keyboard.press( 'Enter' );
	await page.waitForTimeout( 200 );
	st = await state( page );
	log( ! st.muted && st.paused, 'Enter روی دکمه صدا: صدا برگشت', JSON.stringify( { muted: st.muted, paused: st.paused } ) );

	// صدای صفر با نوار، بعد «وصل صدا» باید واقعا صدا بدهد
	await page.evaluate( () => { const s = document.querySelector( '.hs-volume-slider' ); s.value = '0'; s.dispatchEvent( new Event( 'input', { bubbles: true } ) ); } );
	await page.click( '.hs-mute-btn' );
	await page.waitForTimeout( 200 );
	st = await state( page );
	log( ! st.muted && st.volume > 0, 'نوار صدا روی صفر + «وصل صدا»: صدا برگشت', `volume=${ st.volume }` );

	log( await tabTo( page, '.hs-progress-wrap' ), 'Tab به نوار پیشرفت' );
	await page.keyboard.press( 'ArrowRight' );
	await page.waitForTimeout( 200 );
	st = await state( page );
	log( Math.round( st.time ) === 5, 'فلش راست روی نوار: ۵ ثانیه جلو', `time=${ st.time }` );
	await page.keyboard.press( ' ' );
	await page.waitForTimeout( 400 );
	st = await state( page );
	log( ! st.paused, 'Space روی نوار (دکمه نیست): پخش', `paused=${ st.paused }` );
	log( await attr( page, '.hs-play-pause-small', 'aria-label' ) === 'توقف', 'نام دکمه پخش هنگام پخش: «توقف»' );
	log( await page.evaluate( () => { const b = document.querySelector( '.hs-play-toggle' ); return b.tabIndex === -1 && b.getAttribute( 'aria-hidden' ) === 'true'; } ), 'دکمه بزرگ وسط هنگام پخش از ترتیب Tab بیرون است' );

	console.log( '=== کنترل‌ها با فوکوس صفحه‌کلید محو نمی‌شوند' );
	await page.waitForTimeout( 3500 );
	log( ! await page.evaluate( () => document.querySelector( '.hs-player' ).classList.contains( 'hs-controls-hidden' ) ), 'بعد از ۳.۵ ثانیه پخش با فوکوس روی نوار: کنترل‌ها دیده می‌شوند' );
	await page.mouse.click( 5, 5 ); // فوکوس بیرون (کلیک موس)
	await page.mouse.move( 900, 300 );
	await page.mouse.move( 905, 305 );
	await page.waitForTimeout( 3500 );
	log( await page.evaluate( () => document.querySelector( '.hs-player' ).classList.contains( 'hs-controls-hidden' ) ), 'بدون فوکوس صفحه‌کلید: محو می‌شوند (مثل قبل)' );
	await page.evaluate( () => document.querySelector( '.hs-main-video' ).pause() );
	log( errors.length === 0, 'بدون خطای JS', errors.join( ' | ' ) );
	await ctx.close();
}

console.log( '=== منوی سرعت با صفحه‌کلید' );
{
	const { ctx, page, errors } = await open();
	log( await tabTo( page, '.hs-speed-btn' ), 'Tab به دکمه سرعت' );
	log( await attr( page, '.hs-speed-btn', 'aria-expanded' ) === 'false', 'بسته: aria-expanded=false' );
	await page.keyboard.press( 'Enter' );
	await page.waitForTimeout( 300 );
	log( await attr( page, '.hs-speed-btn', 'aria-expanded' ) === 'true', 'Enter باز می‌کند: aria-expanded=true' );
	let st = await state( page );
	log( /is-active/.test( st.active ), 'فوکوس روی گزینه فعلی (1x)', st.active );
	await page.keyboard.press( 'ArrowDown' );
	st = await state( page );
	log( await page.evaluate( () => document.activeElement?.getAttribute( 'data-speed' ) ) === '1.5', 'فلش پایین: گزینه بعد (1.5x)' );
	log( Math.round( st.time ) === 0, 'فلش داخل منو ویدیو را جابه‌جا نمی‌کند', `time=${ st.time }` );
	await page.keyboard.press( 'Enter' );
	await page.waitForTimeout( 300 );
	st = await state( page );
	log( st.rate === 1.5, 'Enter: سرعت 1.5', `rate=${ st.rate }` );
	log( await attr( page, '.hs-speed-btn', 'aria-expanded' ) === 'false' && /hs-speed-btn/.test( st.active ), 'منو بسته شد و فوکوس به دکمه سرعت برگشت', st.active );
	log( await page.getAttribute( '.hs-menu-option[data-speed="1.5"]', 'aria-pressed' ) === 'true', 'گزینه انتخاب‌شده aria-pressed=true' );
	await page.keyboard.press( 'Enter' );
	await page.waitForTimeout( 200 );
	await page.keyboard.press( 'Escape' );
	await page.waitForTimeout( 200 );
	st = await state( page );
	log( await attr( page, '.hs-speed-btn', 'aria-expanded' ) === 'false' && /hs-speed-btn/.test( st.active ), 'Esc می‌بندد و فوکوس برمی‌گردد', st.active );
	await page.keyboard.press( 'Enter' );
	await page.waitForTimeout( 200 );
	await page.keyboard.press( 'Tab' );
	await page.keyboard.press( 'Tab' );
	await page.keyboard.press( 'Tab' );
	await page.keyboard.press( 'Tab' );
	await page.keyboard.press( 'Tab' );
	await page.waitForTimeout( 200 );
	log( await attr( page, '.hs-speed-btn', 'aria-expanded' ) === 'false', 'Tab به بیرون منو: بسته می‌شود' );

	// axe روی پلیر با منوی باز
	await page.addScriptTag( { path: axePath } );
	await page.click( '.hs-more-options-btn' );
	const axe = await page.evaluate( async () => ( await window.axe.run( '.hs-video-col', { runOnly: [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa' ] } ) ).violations.map( ( v ) => `${ v.id } (${ v.nodes.length })` ) );
	log( axe.length === 0, 'axe (WCAG 2.2 AA) روی پلیر و نوار زیرش، منوی «بیشتر» باز', axe.join( ', ' ) );
	log( errors.length === 0, 'بدون خطای JS', errors.join( ' | ' ) );
	await ctx.close();
}

console.log( '=== کشیدن نوار پیشرفت' );
{
	const { ctx, page, tracks } = await open();
	const box = await page.locator( '.hs-progress-wrap' ).boundingBox();
	await page.mouse.move( box.x + ( box.width * 0.1 ), box.y + ( box.height / 2 ) );
	await page.mouse.down();
	await page.mouse.move( box.x + ( box.width * 0.4 ), box.y + ( box.height / 2 ), { steps: 5 } );
	await page.mouse.move( box.x + ( box.width * 0.7 ), box.y + ( box.height / 2 ), { steps: 5 } );
	await page.mouse.up();
	await page.waitForTimeout( 300 );
	const st = await state( page );
	log( Math.abs( ( st.time / st.duration ) - 0.7 ) < 0.05, 'کشیدن تا ۷۰٪: جای پخش ۷۰٪', `${ st.time.toFixed( 1 ) } از ${ st.duration }` );
	const fill = await page.evaluate( () => parseFloat( document.querySelector( '.hs-progress-fill' ).style.width ) );
	log( Math.abs( fill - 70 ) < 5, 'نوار پر شده هم ۷۰٪', `${ fill }%` );
	log( views( tracks ) === 0, 'کشیدن بازدید نیست', `${ views( tracks ) } درخواست` );
	await ctx.close();
}

console.log( '=== بارگذاری و خطا' );
{
	const { ctx, page } = await open();
	await page.evaluate( () => document.querySelector( '.hs-main-video' ).dispatchEvent( new Event( 'waiting' ) ) );
	await page.waitForTimeout( 100 );
	log( ! await page.evaluate( () => document.querySelector( '.hs-player' ).classList.contains( 'hs-is-loading' ) ), 'انتظار کوتاه: نشانگر چشمک نمی‌زند' );
	await page.waitForTimeout( 400 );
	log( await page.evaluate( () => document.querySelector( '.hs-player' ).classList.contains( 'hs-is-loading' ) && getComputedStyle( document.querySelector( '.hs-loader' ) ).display === 'block' ), 'انتظار طولانی: نشانگر بارگذاری دیده می‌شود' );
	await page.evaluate( () => document.querySelector( '.hs-main-video' ).dispatchEvent( new Event( 'playing' ) ) );
	log( ! await page.evaluate( () => document.querySelector( '.hs-player' ).classList.contains( 'hs-is-loading' ) ), 'پخش شروع شد: نشانگر رفت' );
	await ctx.close();
}
{
	const { ctx, page, errors } = await open( '', { broken: true, wait: false } );
	await page.waitForFunction( () => document.querySelector( '.hs-player-message' )?.classList.contains( 'is-visible' ), null, { timeout: 10000 } ).catch( () => {} );
	const msg = await page.evaluate( () => { const m = document.querySelector( '.hs-player-message' ); return { text: m.textContent, role: m.getAttribute( 'role' ), shown: getComputedStyle( m ).display !== 'none' }; } );
	log( msg.shown && msg.text.includes( 'پخش ویدیو ممکن نشد' ) && msg.role === 'status', 'فایل ویدیو در دسترس نیست: پیام خطا (role=status)', JSON.stringify( msg ) );
	log( errors.length === 0, 'بدون خطای JS', errors.join( ' | ' ) );
	await ctx.close();
}

console.log( '=== ویدیوی بدون آدرس' );
{
	execFileSync( 'php', [ path.join( here, 'wp-eval.php' ), "if ( ! get_page_by_path( 'harness-no-url', OBJECT, 'video' ) ) { wp_insert_post( [ 'post_type' => 'video', 'post_title' => 'ویدیوی بدون فایل', 'post_name' => 'harness-no-url', 'post_status' => 'publish' ] ); }" ], { env: { ...process.env, HARNESS: '1', HODIMA_WP: wp } } );
	const empty = execFileSync( 'php', [ path.join( here, 'render.php' ), '/video/harness-no-url/', 'full' ], { env: { ...process.env, HARNESS: '1', HODIMA_WP: wp }, maxBuffer: 32 * 1024 * 1024 } ).toString();
	log( ! /<video\b/.test( empty ), 'بدون عنصر <video> (قبلا src خالی و دانلود دوباره خود صفحه)' );
	log( ! /download class="hs-menu-option"/.test( empty ), 'بدون لینک «دانلود ویدئو»' );
	log( empty.includes( 'hs-player--empty' ) && empty.includes( 'ویدیو هنوز آماده نیست' ), 'پیام «ویدیو هنوز آماده نیست»' );
	const { ctx, page, errors } = await open( '', { body: empty, wait: false } );
	await page.waitForTimeout( 500 );
	log( errors.length === 0, 'بدون خطای JS', errors.join( ' | ' ) );
	await ctx.close();
}

await browser.close();
console.log( failures ? `\n✘ ${ failures } بررسی رد شد` : '\n✔ همه بررسی‌ها قبول' );
process.exit( failures ? 1 : 0 );
