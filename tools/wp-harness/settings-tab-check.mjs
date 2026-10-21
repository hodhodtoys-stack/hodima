/**
 * بررسی یک تب تنظیمات قالب در Chromium — اسکیل hodima-theme («طراحی پیشخوان تنظیمات»). فراخوانی: settings-tab-check.sh
 *
 *   node settings-tab-check.mjs <tab> <outdir> <wp-root> <work-src> [ref-src]
 *
 * <outdir>/<tab>-work.html (و -ref.html) از admin-render.php آمده؛ CSS/JS قالب و افزونه‌ها از <work-src>/<ref-src>،
 * بقیه (wp-includes…) از وردپرس ابزار تست. خروجی: عکس ۱۳۰۰ و ۳۹۰ (نوار ذخیره چسبان برداشته می‌شود)،
 * axe (wcag2a/aa، 21a/aa، 22aa، best-practice) و برای تب دارای قانون «توضیح فقط داخل ؟» آزمون رفتار.
 */
import { chromium } from 'playwright-core';
import fs from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';

const require = createRequire( import.meta.url );
const axePath = require.resolve( 'axe-core/axe.min.js' );
const [ tab, out, wpRoot, workSrc, refSrc ] = process.argv.slice( 2 );
const mime = { '.css': 'text/css', '.js': 'application/javascript', '.woff2': 'font/woff2', '.woff': 'font/woff', '.svg': 'image/svg+xml', '.png': 'image/png', '.gif': 'image/gif', '.jpg': 'image/jpeg' };

/** وردپرس ابزار تست فونت dashicons ندارد (فقط dashicons.svg): هر نماد با mask رسم می‌شود تا ؟ و آیکون‌ها در عکس دیده شوند. */
const dashiconsCss = () => {
	const svgFile = path.join( wpRoot, 'wp-includes/fonts/dashicons.svg' );
	let css = '.dashicons{display:inline-block;width:20px;height:20px;line-height:1;font-style:normal;text-align:center}\n';
	if ( ! fs.existsSync( svgFile ) ) {
		return css;
	}
	for ( const [ , name, body ] of fs.readFileSync( svgFile, 'utf8' ).matchAll( /<symbol[^>]*id="([^"]+)"[^>]*>(.*?)<\/symbol>/gs ) ) {
		const url = 'data:image/svg+xml,' + encodeURIComponent( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">' + body.replace( /<title>.*?<\/title>/s, '' ) + '</svg>' );
		css += `.dashicons-${ name }::before{content:"";display:block;width:1em;height:1em;margin:auto;background:currentColor;-webkit-mask:url("${ url }") center/contain no-repeat;mask:url("${ url }") center/contain no-repeat}\n`;
	}
	return css;
};
const shim = dashiconsCss();

/** مسیر فایل یک آدرس: قالب/افزونه از src (کار یا مرجع)، بقیه از وردپرس ابزار تست. */
const fileFor = ( src, pathname ) => {
	const p = decodeURIComponent( pathname );
	let m = p.match( /^\/wp-content\/themes\/hodima\/(.*)$/ );
	if ( m ) {
		return path.join( src, 'hodima', m[ 1 ] );
	}
	m = p.match( /^\/wp-content\/plugins\/(hodima-[a-z]+)\/(.*)$/ );
	return m ? path.join( src, 'plugins', m[ 1 ], m[ 2 ] ) : path.join( wpRoot, p );
};

const open = async ( browser, htmlFile, src, width ) => {
	const ctx = await browser.newContext( { viewport: { width, height: 900 } } );
	const page = await ctx.newPage();
	await page.route( '**/*', async ( route ) => {
		const u = new URL( route.request().url() );
		if ( u.hostname !== 'hodima.test' ) {
			return route.abort();
		}
		if ( u.pathname === '/wp-admin/admin.php' ) {
			return route.fulfill( { contentType: 'text/html; charset=utf-8', body: fs.readFileSync( htmlFile ) } );
		}
		if ( u.pathname === '/wp-admin/load-scripts.php' ) {
			return route.fulfill( { contentType: 'application/javascript', body: '' } ); // jQuery/wp.media در وردپرس ابزار تست نیست؛ admin.js بدون آن‌ها هم راه می‌افتد
		}
		const f = fileFor( src, u.pathname );
		if ( ! f.endsWith( '.php' ) && fs.existsSync( f ) && fs.statSync( f ).isFile() ) {
			return route.fulfill( { contentType: mime[ path.extname( f ) ] || 'application/octet-stream', body: fs.readFileSync( f ) } );
		}
		return route.fulfill( { status: 404, body: '' } );
	} );
	await page.goto( 'https://hodima.test/wp-admin/admin.php?page=hodima-settings' );
	await page.addStyleTag( { content: shim + '\n.hodima-settings__actions{position:static!important}' } );
	await page.waitForTimeout( 500 );
	return { ctx, page };
};

let fails = 0;
const ok = ( name, cond, extra = '' ) => {
	fails += cond ? 0 : 1;
	console.log( ( cond ? '  قبول  ' : '  رد    ' ) + name + ( extra ? '  ← ' + extra : '' ) );
};

const browser = await chromium.launch( { executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium' } ).catch( () => chromium.launch() );
const section = '.hodima-settings__section:not([hidden])';

for ( const width of [ 1300, 390 ] ) {
	console.log( `\n== عرض ${ width } ==` );
	for ( const [ tag, src ] of [ [ 'work', workSrc ], [ 'ref', refSrc ] ] ) {
		const html = path.join( out, `${ tab }-${ tag }.html` );
		if ( ! src || ! fs.existsSync( html ) ) {
			continue;
		}
		const { ctx, page } = await open( browser, html, src, width );
		await ( await page.$( section ) ).screenshot( { path: path.join( out, `${ tab }-${ tag }-${ width }.png` ) } );

		if ( 'work' !== tag ) {
			await ctx.close();
			continue;
		}

		await page.addScriptTag( { path: axePath } );
		const axe = await page.evaluate( ( sel ) => globalThis.axe.run( document.querySelector( sel ), { runOnly: { type: 'tag', values: [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice' ] } } ), section );
		ok( 'axe: بدون تخلف', 0 === axe.violations.length, axe.violations.map( ( v ) => `${ v.id }×${ v.nodes.length }` ).join( ' ' ) );

		const tips = await page.$$eval( `${ section } .hodima-panel__heading--tip`, ( a ) => a.length );
		if ( tips ) {
			// ۱) هیچ توضیح دیدنی نمانده (فیلد زیرگروه ردیف جدول و توضیح درون‌خطی «unsaved» استثنا و فقط گزارش می‌شود)
			const left = await page.$$eval( `${ section } .hodima-field__help, ${ section } .hodima-panel__help`, ( a ) => a.map( ( e ) => e.textContent.trim().slice( 0, 40 ) ) );
			ok( 'توضیح دیدنی (field__help / panel__help) نمانده', 0 === left.length, left.join( ' | ' ) );
			// ۲) سرتیتر قاب‌ها هم‌ارتفاع
			const heights = await page.$$eval( `${ section } .hodima-panel__head`, ( a ) => [ ...new Set( a.map( ( e ) => Math.round( e.getBoundingClientRect().height ) ) ) ] );
			ok( 'ارتفاع سرتیتر همه قاب‌ها یکی است', 1 === heights.length, heights.join( ',' ) );
			// ۳) هاور روی ؟ سرتیتر: حباب با کل متن، داخل صفحه؛ Esc می‌بندد
			const bubbleOpen = async ( sel ) => page.$eval( sel, ( e ) => 'visible' === getComputedStyle( e ).visibility && Number( getComputedStyle( e ).opacity ) > 0.9 );
			const head = `${ section } .hodima-panel__heading--tip .hodima-tip`;
			if ( await page.$( head ) ) {
				await page.$eval( head, ( e ) => e.scrollIntoView( { block: 'center' } ) );
				await page.waitForTimeout( 200 );
				await page.hover( head + ' .hodima-tip__button' );
				await page.waitForTimeout( 300 );
				ok( 'هاور روی ؟ سرتیتر: حباب دیده می‌شود', await bubbleOpen( head + ' .hodima-tip__bubble' ) );
				const box = await page.$eval( head + ' .hodima-tip__bubble', ( e ) => { const r = e.getBoundingClientRect(); return { l: r.left, r: r.right, t: r.top, b: r.bottom, w: innerWidth, h: innerHeight }; } );
				ok( 'حباب داخل صفحه است', box.l >= 0 && box.r <= box.w && box.t >= 0 && box.b <= box.h, JSON.stringify( box ) );
				await page.keyboard.press( 'Escape' );
				await page.mouse.move( 2, 2 );
				await page.waitForTimeout( 300 );
				ok( 'Esc: حباب بسته می‌شود', ! ( await bubbleOpen( head + ' .hodima-tip__bubble' ) ) );
			}
			// ۴) هر کاشی کلید با ؟
			const ids = await page.$$eval( `${ section } .hodima-toggle--tip input`, ( a ) => a.map( ( e ) => e.id ) );
			let tileFails = 0;
			for ( const id of ids ) {
				const tile = `.hodima-field--toggle:has(#${ id })`;
				await page.$eval( tile, ( e ) => e.scrollIntoView( { block: 'center' } ) );
				await page.waitForTimeout( 150 );
				const before = await page.$eval( `#${ id }`, ( e ) => e.checked );
				const name = await page.$eval( `#${ id }`, ( e ) => e.labels[ 0 ]?.textContent.trim() ?? '' );
				const label = await page.$eval( tile + ' .hodima-toggle__label', ( e ) => e.textContent.trim() );
				const inner = await page.$$eval( tile + ' label button, ' + tile + ' label .hodima-tip', ( a ) => a.length );
				const described = await page.$eval( `#${ id }`, ( e ) => !! document.getElementById( e.getAttribute( 'aria-describedby' ) )?.textContent.trim() );
				await page.click( tile + ' .hodima-tip__button' );
				const afterTip = await page.$eval( `#${ id }`, ( e ) => e.checked );
				await page.keyboard.press( 'Escape' );
				await page.mouse.move( 2, 2 );
				await page.waitForTimeout( 200 );
				await page.click( tile + ' .hodima-toggle__label' );
				const afterLabel = await page.$eval( `#${ id }`, ( e ) => e.checked );
				const c = await page.$eval( tile + ' .hodima-toggle__track', ( e ) => { const r = e.getBoundingClientRect(); return { x: r.left + r.width / 2, y: r.top + r.height / 2 }; } );
				await page.mouse.click( c.x, c.y ); // کلیک روی خود کلید (track) باید برگرداند
				const afterTrack = await page.$eval( `#${ id }`, ( e ) => e.checked );
				if ( name !== label || inner || ! described || afterTip !== before || afterLabel === before || afterTrack !== before ) {
					tileFails++;
					console.log( `    کاشی ${ id }: نام="${ name }" داخل‌label=${ inner } توضیح=${ described } ؟نزد=${ afterTip === before } برچسب=${ afterLabel !== before } کلید=${ afterTrack === before }` );
				}
			}
			ok( `کاشی‌های کلید با ؟ (${ ids.length }): نام، توضیح، کلیک ؟/برچسب/کلید`, 0 === tileFails );
			if ( ids.length ) {
				const hs = await page.$$eval( `${ section } .hodima-toggle--tip`, ( a ) => a.map( ( e ) => Math.round( e.getBoundingClientRect().height ) ) );
				console.log( '    ارتفاع کاشی‌ها (px):', hs.join( ',' ) );
			}
		}
		await ctx.close();
	}
}

await browser.close();
console.log( fails ? `\n${ fails } مورد رد شد` : '\nهمه آزمون‌ها قبول' );
console.log( `عکس‌ها: ${ out }/${ tab }-(work|ref)-(1300|390).png` );
process.exit( fails ? 1 : 0 );
