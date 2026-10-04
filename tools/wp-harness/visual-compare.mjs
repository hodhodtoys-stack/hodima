/**
 * مقایسه ظاهر دو نسخه (قبل/بعد) روی صفحه‌های رندرشده ابزار تست — برای مرحله‌های
 * بازنویسی CSS/JS (بخش ۵۷ HODIMA-AUDIT.md). اجرا با visual-compare.sh.
 *
 *   node visual-compare.mjs <out-ref> <root-ref> <out-work> <root-work> <report-dir> [page ...]
 *
 * هر HTML از run.sh در Chromium باز می‌شود؛ درخواست‌های https://hodima.test/wp-content/
 * از همان پوشه (ref یا work) و wp-includes از وردپرس ابزار تست جواب داده می‌شوند،
 * پس هر طرف CSS/JS خودش را دارد. برای هر صفحه و هر عرض (دسکتاپ ۱۳۰۰، موبایل ۳۹۰):
 *   ۱. استایل محاسبه‌شده (getComputedStyle) همه عنصرها به ترتیب DOM — بازنویسی
 *      هم‌ارز (nesting، خصوصیات منطقی، color-mix) باید دقیقا «صفر تفاوت» بدهد؛
 *   ۲. عکس کل صفحه و شمار پیکسل‌های متفاوت (عکس‌ها در report-dir).
 * تصویرهای آپلود در ابزار تست وجود ندارند و با یک PNG خاکستری ثابت جایگزین می‌شوند.
 */
import { chromium } from 'playwright-core';
import fs from 'node:fs';
import path from 'node:path';

const [ outRef, rootRef, outWork, rootWork, reportDir, ...only ] = process.argv.slice( 2 );
if ( ! reportDir ) {
	console.error( 'usage: visual-compare.mjs <out-ref> <root-ref> <out-work> <root-work> <report-dir> [page ...]' );
	process.exit( 2 );
}
const wpRoot = path.join( process.env.HODIMA_HARNESS || '/tmp/hodima-harness', 'wp' );
fs.mkdirSync( reportDir, { recursive: true } );

const VIEWPORTS = [ { name: 'desktop', width: 1300, height: 900 }, { name: 'mobile', width: 390, height: 844 } ];
const MIME = { '.css': 'text/css', '.js': 'text/javascript', '.woff2': 'font/woff2', '.woff': 'font/woff', '.svg': 'image/svg+xml', '.png': 'image/png', '.jpg': 'image/jpeg', '.jpeg': 'image/jpeg', '.webp': 'image/webp', '.gif': 'image/gif' };
// PNG خاکستری ۱×۱ برای تصویرهای ناموجود (هر دو طرف یکسان)
const GREY = Buffer.from( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mN4+P/ffwAJpAPqf4/yxwAAAABJRU5ErkJggg==', 'base64' );
// بدون انیمیشن و چشمک مکان‌نما تا دو عکس از یک حالت گرفته شوند
const FREEZE = '*,*::before,*::after{animation:none!important;transition:none!important;caret-color:transparent!important}';

function localFile( url, root ) {
	const u = new URL( url );
	if ( u.hostname !== 'hodima.test' ) return null;
	const p = decodeURIComponent( u.pathname );
	if ( p.startsWith( '/wp-content/themes/hodima/' ) ) return path.join( root, 'hodima', p.slice( '/wp-content/themes/hodima/'.length ) );
	const m = p.match( /^\/wp-content\/plugins\/(hodima-[a-z]+)\/(.*)$/ );
	if ( m ) return path.join( root, 'plugins', m[ 1 ], m[ 2 ] );
	if ( p.startsWith( '/wp-includes/' ) || p.startsWith( '/wp-content/plugins/' ) ) return path.join( wpRoot, p );
	return null;
}

async function snapshot( browser, htmlFile, root, vp, shotPath ) {
	const ctx = await browser.newContext( { viewport: { width: vp.width, height: vp.height }, deviceScaleFactor: 1, reducedMotion: 'reduce', locale: 'fa-IR' } );
	const page = await ctx.newPage();
	await page.route( '**/*', async ( route ) => {
		const url = route.request().url();
		if ( url === 'https://hodima.test/__page__' ) {
			return route.fulfill( { status: 200, contentType: 'text/html; charset=utf-8', body: fs.readFileSync( htmlFile ) } );
		}
		const file = localFile( url, root );
		if ( file && fs.existsSync( file ) && fs.statSync( file ).isFile() ) {
			return route.fulfill( { status: 200, contentType: MIME[ path.extname( file ) ] || 'application/octet-stream', body: fs.readFileSync( file ) } );
		}
		if ( route.request().resourceType() === 'image' ) return route.fulfill( { status: 200, contentType: 'image/png', body: GREY } );
		// ویدیو/صوت در ابزار تست نیست؛ خطای فوری تا چرخنده «در حال بارگذاری» در عکس نماند
		if ( route.request().resourceType() === 'media' ) return route.abort();
		return route.fulfill( { status: 404, body: '' } );
	} );
	await page.goto( 'https://hodima.test/__page__', { waitUntil: 'load' } );
	await page.addStyleTag( { content: FREEZE } );
	await page.evaluate( async () => {
		await document.fonts.ready;
		// تصویرهای lazy هم بارگذاری شوند (هر دو طرف یکسان)
		document.querySelectorAll( 'img[loading="lazy"]' ).forEach( ( img ) => img.setAttribute( 'loading', 'eager' ) );
		window.scrollTo( 0, 0 );
	} );
	// تا هر ویدیو/صوت به وضعیت پایدار (خطا یا metadata) برسد
	await page.waitForFunction( () => [ ...document.querySelectorAll( 'video, audio' ) ].every( ( m ) => m.error || m.readyState > 0 || m.networkState === 3 || ! m.currentSrc ), null, { timeout: 5000 } ).catch( () => {} );
	await page.waitForTimeout( 300 );

	const styles = await page.evaluate( () => {
		const out = [];
		const skip = new Set( [ 'SCRIPT', 'STYLE', 'LINK', 'META', 'TITLE', 'HEAD', 'NOSCRIPT', 'TEMPLATE' ] );
		const describe = ( el ) => el.tagName.toLowerCase() + ( el.id ? `#${ el.id }` : '' ) + ( el.classList.length ? `.${ [ ...el.classList ].join( '.' ) }` : '' );
		for ( const el of document.querySelectorAll( 'body, body *' ) ) {
			if ( skip.has( el.tagName ) ) continue;
			const cs = getComputedStyle( el );
			const props = {};
			for ( let i = 0; i < cs.length; i++ ) props[ cs[ i ] ] = cs.getPropertyValue( cs[ i ] );
			for ( const pseudo of [ '::before', '::after' ] ) {
				const ps = getComputedStyle( el, pseudo );
				if ( ps.content && ps.content !== 'none' && ps.content !== 'normal' ) {
					for ( let i = 0; i < ps.length; i++ ) props[ pseudo + ps[ i ] ] = ps.getPropertyValue( ps[ i ] );
				}
			}
			out.push( { el: describe( el ), props } );
		}
		return out;
	} );
	await page.screenshot( { path: shotPath, fullPage: true } );
	await ctx.close();
	return styles;
}

async function pixelDiff( browser, a, b, diffPath ) {
	const page = await browser.newPage();
	const res = await page.evaluate( async ( [ da, db ] ) => {
		const load = ( src ) => new Promise( ( ok ) => { const i = new Image(); i.onload = () => ok( i ); i.src = src; } );
		const [ ia, ib ] = await Promise.all( [ load( da ), load( db ) ] );
		const w = Math.max( ia.width, ib.width ), h = Math.max( ia.height, ib.height );
		const draw = ( img ) => { const c = new OffscreenCanvas( w, h ); const x = c.getContext( '2d' ); x.fillStyle = '#f0f'; x.fillRect( 0, 0, w, h ); x.drawImage( img, 0, 0 ); return x.getImageData( 0, 0, w, h ).data; };
		const pa = draw( ia ), pb = draw( ib );
		const c = new OffscreenCanvas( w, h ); const x = c.getContext( '2d' ); const out = x.createImageData( w, h );
		let n = 0;
		for ( let i = 0; i < pa.length; i += 4 ) {
			const same = pa[ i ] === pb[ i ] && pa[ i + 1 ] === pb[ i + 1 ] && pa[ i + 2 ] === pb[ i + 2 ] && pa[ i + 3 ] === pb[ i + 3 ];
			if ( ! same ) n++;
			out.data[ i ] = same ? pa[ i ] * 0.3 + 178 : 255; out.data[ i + 1 ] = same ? pa[ i + 1 ] * 0.3 + 178 : 0; out.data[ i + 2 ] = same ? pa[ i + 2 ] * 0.3 + 178 : 0; out.data[ i + 3 ] = 255;
		}
		x.putImageData( out, 0, 0 );
		const blob = await c.convertToBlob( { type: 'image/png' } );
		const buf = new Uint8Array( await blob.arrayBuffer() );
		let bin = ''; for ( const byte of buf ) bin += String.fromCharCode( byte );
		return { n, w, h, sizeA: [ ia.width, ia.height ], sizeB: [ ib.width, ib.height ], png: btoa( bin ) };
	}, [ `data:image/png;base64,${ fs.readFileSync( a ).toString( 'base64' ) }`, `data:image/png;base64,${ fs.readFileSync( b ).toString( 'base64' ) }` ] );
	await page.close();
	if ( res.n ) fs.writeFileSync( diffPath, Buffer.from( res.png, 'base64' ) );
	return res;
}

function styleDiff( ref, work ) {
	const diffs = [];
	if ( ref.length !== work.length ) diffs.push( `ساختار DOM فرق دارد: ${ ref.length } → ${ work.length } عنصر` );
	const n = Math.min( ref.length, work.length );
	for ( let i = 0; i < n && diffs.length < 40; i++ ) {
		if ( ref[ i ].el !== work[ i ].el ) { diffs.push( `عنصر ${ i }: ${ ref[ i ].el } → ${ work[ i ].el } (DOM فرق دارد؛ مقایسه متوقف شد)` ); break; }
		const a = ref[ i ].props, b = work[ i ].props;
		const keys = new Set( [ ...Object.keys( a ), ...Object.keys( b ) ] );
		const changed = [ ...keys ].filter( ( k ) => a[ k ] !== b[ k ] );
		if ( changed.length ) diffs.push( `${ ref[ i ].el }: ${ changed.slice( 0, 6 ).map( ( k ) => `${ k }: ${ a[ k ] ?? '∅' } → ${ b[ k ] ?? '∅' }` ).join( ' | ' ) }${ changed.length > 6 ? ` (+${ changed.length - 6 })` : '' }` );
	}
	return diffs;
}

const pages = fs.readdirSync( outWork ).filter( ( f ) => f.endsWith( '.html' ) && fs.existsSync( path.join( outRef, f ) ) )
	.map( ( f ) => f.slice( 0, -5 ) ).filter( ( p ) => ! only.length || only.includes( p ) ).sort();

const browser = await chromium.launch();
let bad = 0;
for ( const p of pages ) {
	for ( const vp of VIEWPORTS ) {
		const tag = `${ p }-${ vp.name }`;
		const shotA = path.join( reportDir, `${ tag }-ref.png` ), shotB = path.join( reportDir, `${ tag }-work.png` );
		const sa = await snapshot( browser, path.join( outRef, `${ p }.html` ), rootRef, vp, shotA );
		const sb = await snapshot( browser, path.join( outWork, `${ p }.html` ), rootWork, vp, shotB );
		const sd = styleDiff( sa, sb );
		const px = await pixelDiff( browser, shotA, shotB, path.join( reportDir, `${ tag }-diff.png` ) );
		const same = ! sd.length && ! px.n;
		if ( same ) { fs.rmSync( shotA ); fs.rmSync( shotB ); }
		else bad++;
		console.log( `${ same ? '✔' : '✘' } ${ tag.padEnd( 22 ) } style: ${ sd.length ? `${ sd.length } تفاوت` : 'یکسان' }  pixels: ${ px.n }${ px.sizeA.join( 'x' ) !== px.sizeB.join( 'x' ) ? ` (اندازه ${ px.sizeA.join( 'x' ) } → ${ px.sizeB.join( 'x' ) })` : '' }` );
		for ( const d of sd.slice( 0, 12 ) ) console.log( `      ${ d }` );
	}
}
await browser.close();
console.log( bad ? `\n${ bad } نما متفاوت — عکس‌ها و *-diff.png (قرمز = پیکسل متفاوت) در ${ reportDir }` : '\nهمه نماها یکسان' );
process.exit( bad ? 1 : 0 );
