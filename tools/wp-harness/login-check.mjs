/**
 * آزمون «ورود و امنیت» Core (includes/login.php، admin-login.php، assets/login.css) در Chromium واقعی
 * روی وردپرس ابزار تست (php -S، نه HTML ازپیش‌ساخته).
 *   tools/wp-harness/login-check.sh        (این فایل را راه می‌اندازد)
 *
 * می‌سنجد: ظاهر قبل (ظاهر خاموش) و بعد (دسکتاپ ۱۳۰۰ و موبایل ۳۹۰) با عکس؛ نوار کناری فقط دسکتاپ؛ حرف اول
 * نام سایت و لوگوی آدرسی؛ بدون سرریز افقی؛ اندازه فیلد و دکمه؛ فوکوس صفحه‌کلید؛ axe (WCAG 2.2 AA)؛ پیام خطای
 * یکسان برای کاربر نامعتبر و رمز غلط؛ ?author=1 و /wp-json/wp/v2/users؛ هدر nosniff؛ صفحه تنظیمات (پیش‌نمایش
 * زنده، ذخیره، آدرس نادرست = لوگوی قبلی نگه داشته شود، کلید خاموش) و نبودن رنگ بنفش.
 * خروجی: ✔/✘؛ کد خروج ۱ اگر یکی رد شود. عکس‌ها: $HODIMA_HARNESS/login.
 */
import { chromium } from 'playwright-core';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';

const require = createRequire( import.meta.url );
const axeSource = fs.readFileSync( require.resolve( 'axe-core/axe.min.js' ), 'utf8' );
const port = process.env.CCSS_PORT || '8767';
const origin = `http://127.0.0.1:${ port }`;
const site = 'https://hodima.test';
const here = path.dirname( fileURLToPath( import.meta.url ) );
const harness = process.env.HODIMA_HARNESS || '/tmp/hodima-harness';
const wpRoot = process.env.HODIMA_WP || path.join( harness, 'wp' );
const shots = path.join( harness, 'login' );
fs.mkdirSync( shots, { recursive: true } );

const browser = await chromium.launch( { executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium' } ).catch( () => chromium.launch() );
let failures = 0;
const log = ( ok, name, detail = '' ) => {
	if ( ! ok ) failures++;
	console.log( `  ${ ok ? '✔' : '✘' } ${ name }${ detail ? ` — ${ detail }` : '' }` );
};

function php( code ) {
	return execFileSync( 'php', [ path.join( here, 'wp-eval.php' ), code ], { env: { ...process.env, HODIMA_WP: wpRoot } } ).toString().trim();
}

/** «wp is not defined»: اسکریپت‌های درون‌خطی وردپرس (wp.i18n…) که در ابزار تست بسته جاوااسکریپت وردپرس نیست؛ روی صفحه ورود پیش‌فرض هم هست. */
const real = ( errors ) => errors.filter( ( e ) => ! /wp is not defined/.test( e ) );

const LOGO = 'https://logo.test/logo.svg';
const svg = '<svg xmlns="http://www.w3.org/2000/svg" width="240" height="96" viewBox="0 0 240 96"><rect width="240" height="96" rx="16" fill="#25316a"/><text x="120" y="58" font-size="34" text-anchor="middle" fill="#fff" font-family="sans-serif">LOGO</text></svg>';

async function newContext( width = 1300 ) {
	const ctx = await browser.newContext( { viewport: { width, height: 900 }, ignoreHTTPSErrors: true, locale: 'fa-IR' } );
	await ctx.route( LOGO, ( route ) => route.fulfill( { status: 200, contentType: 'image/svg+xml', body: svg } ) );
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

async function adminLogin( ctx ) {
	const body = new URLSearchParams( { log: 'logincheck', pwd: 'login-pass-123', 'wp-submit': 'Log In', redirect_to: `${ site }/wp-admin/`, testcookie: '1' } );
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

const axe = async ( page, selector, name ) => {
	await page.addScriptTag( { content: axeSource } );
	const res = await page.evaluate( async ( sel ) => window.axe.run( document.querySelector( sel ), { runOnly: [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa' ] } ), selector );
	log( res.violations.length === 0, `axe بدون نقض: ${ name }`, res.violations.map( ( v ) => `${ v.id }(${ v.nodes.length })` ).join( ' ' ) );
};

const setSettings = ( enabled, logo = '', height = 48 ) => php( `update_option(HODIMA_CORE_LOGIN_OPTION,["enabled"=>${ enabled ? 'true' : 'false' },"logo_url"=>${ JSON.stringify( logo ) },"logo_height"=>${ height }]);echo "ok";` );

/* ---------- آماده‌سازی ---------- */
php( 'if(!username_exists("logincheck")){wp_insert_user(["user_login"=>"logincheck","user_pass"=>"login-pass-123","role"=>"administrator"]);} update_option("blogname","هدهدلی"); echo "ok";' );

/** رنگ بنفش (تُن ۲۶۰ تا ۳۲۰ با اشباع معنادار) روی عنصرهای دیده‌شده؟ */
const purpleScan = ( page ) => page.evaluate( () => {
	const hue = ( c ) => {
		const m = c.match( /rgba?\((\d+)[, ]+(\d+)[, ]+(\d+)/ );
		if ( ! m ) return null;
		const [ r, g, b ] = [ +m[ 1 ] / 255, +m[ 2 ] / 255, +m[ 3 ] / 255 ];
		const max = Math.max( r, g, b ), min = Math.min( r, g, b ), d = max - min;
		if ( d < 0.18 || max < 0.2 ) return null;
		let h = max === r ? ( ( g - b ) / d ) % 6 : max === g ? ( b - r ) / d + 2 : ( r - g ) / d + 4;
		h = ( h * 60 + 360 ) % 360;
		return h;
	};
	const bad = [];
	for ( const el of document.querySelectorAll( 'body *' ) ) {
		const cs = getComputedStyle( el );
		if ( cs.display === 'none' ) continue;
		for ( const prop of [ 'color', 'backgroundColor', 'borderTopColor' ] ) {
			const h = hue( cs[ prop ] );
			if ( h !== null && h >= 268 && h <= 320 ) bad.push( `${ el.tagName }.${ el.className } ${ prop }=${ cs[ prop ] }` );
		}
	}
	return bad.slice( 0, 5 );
} );

const metrics = ( page ) => page.evaluate( () => {
	const box = ( sel ) => { const e = document.querySelector( sel ); if ( ! e ) return null; const r = e.getBoundingClientRect(); return { w: Math.round( r.width ), h: Math.round( r.height ), x: Math.round( r.x ) }; };
	const aside = document.querySelector( '.hodima-login-aside' );
	return {
		overflow: document.documentElement.scrollWidth - window.innerWidth,
		bodyClass: document.body.className,
		dir: document.documentElement.dir,
		user: box( '#user_login' ), pass: box( '#user_pass' ), submit: box( '#wp-submit' ), card: box( '#loginform' ),
		asideShown: !! aside && getComputedStyle( aside ).display !== 'none',
		logoBg: getComputedStyle( document.querySelector( '#login h1 a' ) ).backgroundImage,
		logoH: Math.round( document.querySelector( '#login h1 a' ).getBoundingClientRect().height ),
		letter: getComputedStyle( document.querySelector( '#login h1 a' ), '::before' ).content,
		font: getComputedStyle( document.body ).fontFamily,
	};
} );

/* ---------- ۱) صفحه ورود: قبل (ظاهر خاموش) ---------- */
console.log( '۱) صفحه ورود: قبل (ظاهر خاموش = وردپرس پیش‌فرض)' );
setSettings( false );
for ( const width of [ 1300, 390 ] ) {
	const ctx = await newContext( width );
	const pg = await ctx.newPage();
	await pg.goto( `${ site }/wp-login.php`, { waitUntil: 'load' } );
	const m = await metrics( pg );
	log( ! m.bodyClass.includes( 'hodima-login' ), `ظاهر خاموش: کلاس هدیما نیست (${ width })` );
	await pg.screenshot( { path: path.join( shots, `before-${ width }.png` ), fullPage: true } );
	await ctx.close();
}

/* ---------- ۲) صفحه ورود: بعد (بدون لوگو = حرف اول) ---------- */
console.log( '۲) صفحه ورود: بعد، بدون لوگو' );
setSettings( true );
for ( const width of [ 1300, 390 ] ) {
	const ctx = await newContext( width );
	const pg = await ctx.newPage();
	const errors = [];
	pg.on( 'pageerror', ( e ) => errors.push( e.message ) );
	const res = await pg.goto( `${ site }/wp-login.php`, { waitUntil: 'load' } );
	await pg.evaluate( () => document.fonts.ready );
	const m = await metrics( pg );
	const w = `(${ width })`;
	log( m.bodyClass.includes( 'hodima-login' ) && m.bodyClass.includes( 'hodima-login--letter' ), `کلاس‌های بدنه ${ w }`, m.bodyClass );
	log( m.dir === 'rtl', `صفحه راست‌به‌چپ ${ w }`, m.dir );
	log( m.overflow <= 0, `بدون سرریز افقی ${ w }`, `${ m.overflow }px` );
	log( ( m.user?.h ?? 0 ) >= 44 && ( m.pass?.h ?? 0 ) >= 44, `فیلدها دست‌کم ۴۴px ${ w }`, `${ m.user?.h }/${ m.pass?.h }` );
	log( ( m.submit?.h ?? 0 ) >= 48, `دکمه ورود دست‌کم ۴۸px ${ w }`, `${ m.submit?.h }` );
	log( m.asideShown === ( width >= 1024 ), `نوار کناری فقط دسکتاپ ${ w }`, String( m.asideShown ) );
	log( m.letter.includes( 'ه' ), `حرف اول نام سایت ${ w }`, m.letter );
	log( m.font.includes( 'Vazirmatn' ), `فونت وزیرمتن ${ w }`, m.font );
	log( ( res?.headers()[ 'x-content-type-options' ] ?? '' ) === 'nosniff', `هدر nosniff روی صفحه ورود ${ w }` );
	log( ( await pg.title() ).includes( 'WordPress' ) === false, `عنوان صفحه بی‌نام وردپرس ${ w }`, await pg.title() );
	const purple = await purpleScan( pg );
	log( purple.length === 0, `بدون رنگ بنفش ${ w }`, purple.join( ' | ' ) );
	log( real( errors ).length === 0, `بدون خطای جاوااسکریپت ${ w }`, real( errors ).join( ' | ' ) );
	await axe( pg, 'body', `صفحه ورود بدون لوگو ${ w }` );
	await pg.screenshot( { path: path.join( shots, `after-${ width }.png` ), fullPage: true } );

	// فوکوس صفحه‌کلید: اولین Tab روی نام کاربری، حلقه فوکوس دیده شود
	await pg.mouse.click( 5, 5 );
	await pg.keyboard.press( 'Tab' );
	const focus = await pg.evaluate( () => { const e = document.activeElement; const cs = getComputedStyle( e ); return { id: e.id, outline: cs.outlineStyle, w: cs.outlineWidth }; } );
	log( focus.outline !== 'none' && parseFloat( focus.w ) >= 2, `حلقه فوکوس صفحه‌کلید ${ w }`, `${ focus.id } ${ focus.outline } ${ focus.w }` );
	await ctx.close();
}

/* ---------- ۳) لوگو از آدرس ---------- */
console.log( '۳) لوگو از آدرس' );
setSettings( true, LOGO, 72 );
for ( const width of [ 1300, 390 ] ) {
	const ctx = await newContext( width );
	const pg = await ctx.newPage();
	await pg.goto( `${ site }/wp-login.php`, { waitUntil: 'load' } );
	await pg.waitForTimeout( 300 );
	const m = await metrics( pg );
	log( m.bodyClass.includes( 'hodima-login--logo' ), `کلاس لوگو (${ width })` );
	log( m.logoBg.includes( 'logo.test/logo.svg' ), `لوگو پس‌زمینه سربرگ (${ width })`, m.logoBg );
	log( m.logoH === 72, `ارتفاع لوگو ۷۲px (${ width })`, String( m.logoH ) );
	log( m.overflow <= 0, `بدون سرریز افقی (${ width })`, `${ m.overflow }px` );
	await axe( pg, 'body', `صفحه ورود با لوگو (${ width })` );
	await pg.screenshot( { path: path.join( shots, `after-logo-${ width }.png` ), fullPage: true } );
	await ctx.close();
}

/* ---------- ۴) پیام خطای یکسان ---------- */
console.log( '۴) پیام خطای ورود' );
setSettings( true );
const failText = async ( user, pass, width = 1300 ) => {
	const ctx = await newContext( width );
	const pg = await ctx.newPage();
	await pg.goto( `${ site }/wp-login.php`, { waitUntil: 'load' } );
	await pg.fill( '#user_login', user );
	await pg.fill( '#user_pass', pass );
	await Promise.all( [ pg.waitForLoadState( 'load' ), pg.click( '#wp-submit' ) ] );
	await pg.waitForSelector( '#login_error', { timeout: 15000 } );
	const text = ( await pg.locator( '#login_error' ).innerText() ).trim();
	if ( width === 1300 && user === 'logincheck' ) {
		await axe( pg, 'body', 'صفحه ورود با پیام خطا' );
		await pg.screenshot( { path: path.join( shots, 'after-error-1300.png' ), fullPage: true } );
	}
	await ctx.close();
	return text;
};
const e1 = await failText( 'nobody-here', 'x-wrong-123' );
const e2 = await failText( 'logincheck', 'x-wrong-123' );
const e3 = await failText( 'logincheck@invalid.test', 'x-wrong-123' );
log( e1 === e2 && e2 === e3, 'کاربر نامعتبر، رمز غلط و ایمیل نامعتبر: یک پیام', JSON.stringify( [ e1, e2, e3 ] ) );
log( ! e1.includes( 'nobody-here' ) && ! e2.includes( 'logincheck' ), 'پیام نام کاربری را تکرار نمی‌کند' );

/* ---------- ۵) راه‌های شناسایی نام کاربری ---------- */
console.log( '۵) ?author=1 و فهرست کاربران REST' );
const raw = ( p ) => fetch( `${ origin }${ p }`, { redirect: 'manual', headers: { host: 'hodima.test' } } );
const a = await raw( '/?author=1' );
log( a.status === 301 && ( a.headers.get( 'location' ) || '' ).replace( /\/$/, '' ).endsWith( 'hodima.test' ), '?author=1 به صفحه اصلی می‌رود (۳۰۱)', `${ a.status } ${ a.headers.get( 'location' ) }` );
const au = await raw( '/?author=1&x=1' );
log( ! ( au.headers.get( 'location' ) || '' ).includes( '/author/' ), 'آدرس /author/نام/ لو نمی‌رود', au.headers.get( 'location' ) || '' );
const r1 = await raw( '/?rest_route=/wp/v2/users' );
const rj = await r1.json().catch( () => ( {} ) );
log( r1.status === 404 && rj.code === 'rest_no_route', 'فهرست کاربران REST برای بازدیدکننده بسته است', `${ r1.status } ${ rj.code }` );
const r2 = await raw( '/?rest_route=/wp/v2/posts' );
log( r2.status === 200, 'مسیرهای دیگر REST باز است', String( r2.status ) );

/* ---------- ۶) صفحه تنظیمات ---------- */
console.log( '۶) صفحه تنظیمات «ورود و امنیت»' );
setSettings( true );
for ( const width of [ 1300, 390 ] ) {
	const ctx = await newContext( width );
	const pg = await adminLogin( ctx );
	const errors = [];
	pg.on( 'pageerror', ( e ) => errors.push( e.message ) );
	await pg.goto( `${ site }/wp-admin/admin.php?page=hodima-login`, { waitUntil: 'load' } );
	const w = `(${ width })`;
	log( ( await pg.locator( '.hd-login-page' ).count() ) === 1 && ( await pg.locator( '.hd-stat' ).count() ) === 3, `کالبد: سربرگ، سه کارت وضعیت ${ w }` );
	log( ( await pg.locator( '#hd-login-logo' ).count() ) === 1 && ( await pg.locator( 'button.button-primary' ).count() ) === 1, `فیلد آدرس لوگو و یک کنش اصلی ${ w }` );
	const over = await pg.evaluate( () => document.documentElement.scrollWidth - window.innerWidth );
	log( over <= 0, `بدون سرریز افقی ${ w }`, `${ over }px` );
	const purple = await purpleScan( pg );
	log( purple.length === 0, `بدون رنگ بنفش ${ w }`, purple.join( ' | ' ) );

	// پیش‌نمایش زنده
	await pg.fill( '#hd-login-logo', LOGO );
	await pg.waitForTimeout( 300 );
	log( await pg.locator( '[data-hd-login-img]' ).isVisible() && ! ( await pg.locator( '[data-hd-login-letter]' ).isVisible() ), `پیش‌نمایش زنده: لوگو جای حرف ${ w }` );
	await pg.fill( '#hd-login-logo', 'http://insecure.test/logo.png' );
	await pg.waitForTimeout( 100 );
	log( ! ( await pg.locator( '[data-hd-login-img]' ).isVisible() ) && ( await pg.locator( '[data-hd-login-note]' ).innerText() ).includes( 'https' ), `پیش‌نمایش زنده: آدرس ناامن رد می‌شود ${ w }` );
	await pg.fill( '#hd-login-logo', '' );
	await axe( pg, '.hd-login-page', `صفحه تنظیمات ${ w }` );
	await pg.locator( '.hd-login-page' ).screenshot( { path: path.join( shots, `settings-${ width }.png` ) } );

	if ( width === 1300 ) {
		const save = async () => { await Promise.all( [ pg.waitForLoadState( 'load' ), pg.click( 'button.button-primary' ) ] ); await pg.waitForLoadState( 'load' ); };
		// آدرس درست ذخیره می‌شود
		await pg.fill( '#hd-login-logo', LOGO );
		await pg.fill( '#hd-login-height', '80' );
		await save();
		await pg.waitForSelector( '.notice', { timeout: 10000 } );
		log( php( 'echo json_encode(hodima_core_login_settings());' ).includes( 'logo.test' ) && php( 'echo hodima_core_login_settings()["logo_height"];' ) === '80', 'آدرس و ارتفاع درست ذخیره شد' );
		// آدرس نادرست: لوگوی قبلی می‌ماند و هشدار می‌آید
		await pg.fill( '#hd-login-logo', 'javascript:alert(1)' );
		await save();
		await pg.waitForSelector( '.notice-warning', { timeout: 10000 } );
		log( php( 'echo hodima_core_login_settings()["logo_url"];' ) === LOGO, 'آدرس نادرست: لوگوی قبلی نگه داشته شد و هشدار آمد' );
		// خالی‌گذاشتن عمدی لوگو را برمی‌دارد
		await pg.fill( '#hd-login-logo', '' );
		await save();
		log( php( 'echo hodima_core_login_settings()["logo_url"];' ) === '', 'خالی‌گذاشتن لوگو را برمی‌دارد' );
		// کلید خاموش
		await pg.locator( 'input.hd-switch' ).uncheck();
		await save();
		log( php( 'echo hodima_core_login_settings()["enabled"] ? "on" : "off";' ) === 'off', 'کلید خاموش ذخیره شد' );
		const ctx2 = await newContext( 1300 );
		const p2 = await ctx2.newPage();
		await p2.goto( `${ site }/wp-login.php`, { waitUntil: 'load' } );
		log( ! ( await p2.evaluate( () => document.body.className ) ).includes( 'hodima-login' ), 'کلید خاموش: صفحه ورود پیش‌فرض وردپرس برگشت' );
		await ctx2.close();
	}
	log( real( errors ).length === 0, `بدون خطای جاوااسکریپت ${ w }`, real( errors ).join( ' | ' ) );
	await ctx.close();
}

// برگرداندن داده آزمایشی
php( 'delete_option(HODIMA_CORE_LOGIN_OPTION);echo "ok";' );
await browser.close();
console.log( failures ? `\n✘ ${ failures } آزمون رد شد` : '\n✔ همه آزمون‌ها قبول' );
process.exit( failures ? 1 : 0 );
