/**
 * ابزار مشترک آزمون‌های «ورود بسته» (package-import-check.mjs برای محصول و package-import-types-check.mjs برای
 * دسته، مقاله و ویدیو): ورود واقعی مدیر در Chromium روی وردپرس ابزار تست (php -S، نگاشت https://hodima.test)،
 * اجرای PHP روی همان نصب، عکس کادر، axe و چسباندن + «بررسی بسته».
 */
import { chromium } from 'playwright-core';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';

const require = createRequire( import.meta.url );
const axeSource = fs.readFileSync( require.resolve( 'axe-core/axe.min.js' ), 'utf8' );

export const here = path.dirname( fileURLToPath( import.meta.url ) );
export const site = 'https://hodima.test';
export const box = '[data-hodima-pkg]';

export async function setup() {
	const port = process.env.PKG_PORT || '8777';
	const origin = `http://127.0.0.1:${ port }`;
	const harness = process.env.HODIMA_HARNESS || '/tmp/hodima-harness-wc';
	const wpRoot = process.env.HODIMA_WP || path.join( harness, 'wp' );
	const shots = path.join( harness, 'package-import' );
	fs.mkdirSync( shots, { recursive: true } );

	const browser = await chromium.launch( { executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium' } ).catch( () => chromium.launch() );
	const state = { failures: 0 };
	const log = ( ok, name, detail = '' ) => {
		if ( ! ok ) state.failures++;
		console.log( `  ${ ok ? '✔' : '✘' } ${ name }${ detail ? ` — ${ detail }` : '' }` );
	};
	const php = ( code ) => execFileSync( 'php', [ path.join( here, 'wp-eval.php' ), code ], { env: { ...process.env, HODIMA_WP: wpRoot } } ).toString().trim();
	const phpJson = ( code ) => JSON.parse( php( `echo wp_json_encode( ( function () { ${ code } } )(), JSON_UNESCAPED_UNICODE );` ) || 'null' );

	async function newContext( width ) {
		const ctx = await browser.newContext( { viewport: { width, height: 900 }, ignoreHTTPSErrors: true, locale: 'fa-IR', deviceScaleFactor: 1 } );
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
		const body = new URLSearchParams( { log: 'pkgadmin', pwd: 'pkg-pass-123', 'wp-submit': 'Log In', redirect_to: `${ site }/wp-admin/`, testcookie: '1' } );
		const res = await fetch( `${ origin }/wp-login.php`, { method: 'POST', body, redirect: 'manual', headers: { host: 'hodima.test', cookie: 'wordpress_test_cookie=WP%20Cookie%20check' } } );
		const cookies = res.headers.getSetCookie().filter( ( c ) => /^wordpress_(sec|logged_in)_/.test( c ) ).map( ( c ) => {
			const [ pair, ...attrs ] = c.split( ';' ).map( ( x ) => x.trim() );
			const eq = pair.indexOf( '=' );
			return { name: pair.slice( 0, eq ), value: pair.slice( eq + 1 ), domain: 'hodima.test', path: attrs.find( ( x ) => x.toLowerCase().startsWith( 'path=' ) )?.slice( 5 ) || '/', secure: true, httpOnly: true };
		} );
		if ( ! cookies.length ) throw new Error( 'ورود مدیر ناموفق بود' );
		await ctx.addCookies( cookies );
		const page = await ctx.newPage();
		page.errors = [];
		page.on( 'pageerror', ( e ) => /\/plugins\/hodima-/.test( e.stack || '' ) && page.errors.push( e.message ) );
		return page;
	}

	/** عکس کادر در پنجره بلند بی‌اسکرول تا نوار چسبان پیشخوان روی کادر نیفتد. */
	async function shot( page, name ) {
		const size = page.viewportSize();
		await page.setViewportSize( { width: size.width, height: 4000 } );
		// جدول کادر Media در ۳۹۰ صفحه را افقی اسکرول‌پذیر می‌کند (از قبل)؛ در صفحه راست‌به‌چپ برش عکس جابه‌جا می‌شد
		await page.addStyleTag( { content: 'html,body{overflow-x:hidden}' } );
		await page.locator( box ).scrollIntoViewIfNeeded();
		await page.waitForTimeout( 150 );
		await page.locator( box ).screenshot( { path: path.join( shots, `${ name }.png` ) } );
		await page.setViewportSize( size );
	}

	async function axe( page, name ) {
		await page.addScriptTag( { content: axeSource } );
		const res = await page.evaluate( async ( sel ) => ( await window.axe.run( sel, { runOnly: [ 'wcag2a', 'wcag2aa', 'wcag21aa', 'wcag22aa' ] } ) ).violations.map( ( v ) => `${ v.id }(${ v.nodes.length })` ), box );
		log( ! res.length, `axe ${ name }`, res.join( ' ' ) );
	}

	async function openAndCheck( page, text ) {
		if ( await page.locator( '[data-pkg-toggle][aria-expanded="false"]' ).count() ) {
			await page.click( '[data-pkg-toggle]' );
		}
		await page.fill( '[data-pkg-json]', text );
		await page.click( '[data-pkg-check]' );
		await page.waitForFunction( () => ! document.querySelector( '[data-pkg-loaded]' ).hidden || ! document.querySelector( '[data-pkg-paste-error]' ).hidden, null, { timeout: 30000 } );
	}

	const close = async () => {
		await browser.close();
		console.log( state.failures ? `✘ ${ state.failures } مورد رد شد` : '✔ همه موردها قبول' );
		console.log( `عکس‌ها: ${ shots }` );
		return state.failures;
	};

	return { browser, log, php, phpJson, newContext, shot, axe, openAndCheck, close, state, shots };
}
