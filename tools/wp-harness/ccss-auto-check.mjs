/**
 * آزمون ساخت خودکار CSS حیاتی (قالب 4.0.0؛ inc/performance/critical-css.php + assets/js/critical-css-admin.js)
 * در Chromium واقعی با ورود واقعی مدیر روی وردپرس ابزار تست (php -S، نه HTML ازپیش‌ساخته).
 *   tools/wp-harness/ccss-auto-check.sh        (این فایل را راه می‌اندازد)
 *
 * می‌سنجد: ساخت خودکار با ورود به داشبورد، اعلان وردپرس، پایان و آزاد شدن قفل، عدم ساخت دوباره وقتی
 * تازه است، ساخت دوباره بعد از کهنه شدن، خاموش بودن (کلید) و صفحه ویرایش، فاصله بعد از خطا، قفل هم‌زمان،
 * و نمایش پیشرفت در قاب صفحه تنظیمات. خروجی: ✔/✘؛ کد خروج ۱ اگر یکی رد شود.
 * محدودیت: وردپرس ابزار تست ووکامرس شبیه‌سازی‌شده دارد؛ و LiteSpeed Cache نیست (پاک‌سازی کش فقط هوک خالی است).
 */
import { chromium } from 'playwright-core';
import { execFileSync } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const port = process.env.CCSS_PORT || '8765';
const origin = `http://127.0.0.1:${ port }`;
const site = 'https://hodima.test';
const ajaxPath = '/wp-admin/admin-ajax.php';

const browser = await chromium.launch( { executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium' } ).catch( () => chromium.launch() );
let failures = 0;
const log = ( ok, name, detail = '' ) => {
	if ( ! ok ) failures++;
	console.log( `  ${ ok ? '✔' : '✘' } ${ name }${ detail ? ` — ${ detail }` : '' }` );
};
const wait = ( ms ) => new Promise( ( r ) => setTimeout( r, ms ) );

/** زمینه تازه (localStorage خالی) که https://hodima.test را به سرور php -S نگاشت می‌کند. */
async function newContext( { width = 1300 } = {} ) {
	const ctx = await browser.newContext( { viewport: { width, height: 900 }, ignoreHTTPSErrors: true } );
	await ctx.route( /^https:\/\/hodima\.test(?:[/?#]|$)/, async ( route ) => {
		const req = route.request();
		const url = req.url().replace( site, origin );
		const headers = { ...req.headers(), host: 'hodima.test' };
		delete headers[ 'content-length' ];
		try {
			const res = await route.fetch( { url, headers, maxRedirects: 0 } );
			const out = { status: res.status(), headers: res.headers(), body: await res.body() };
			// Location مطلق http://127.0.0.1 نباید بیرون برود
			if ( out.headers.location ) out.headers.location = out.headers.location.replace( origin, site );
			await route.fulfill( out );
		} catch ( error ) {
			if ( process.env.CCSS_DEBUG ) console.log( 'ROUTE ERR', url, String( error.message ).split( '\n' )[ 0 ] );
			await route.abort();
		}
	} );
	return ctx;
}

/**
 * ورود واقعی مدیر: فرم wp-login.php مستقیم به سرور فرستاده و کوکی‌های برگشتی به زمینه Chromium داده می‌شود
 * (ورود از راه صفحه ورود در این ابزار به آدرس بیرونی می‌رفت چون ریدایرکت‌ها در ابزار تست بسته‌اند).
 */
async function login( ctx ) {
	const body = new URLSearchParams( { log: 'ccssadmin', pwd: 'ccss-pass-123', 'wp-submit': 'Log In', redirect_to: `${ site }/wp-admin/`, testcookie: '1' } );
	const res = await fetch( `${ origin }/wp-login.php`, { method: 'POST', body, redirect: 'manual', headers: { host: 'hodima.test', cookie: 'wordpress_test_cookie=WP%20Cookie%20check' } } );
	const cookies = res.headers.getSetCookie().filter( ( c ) => /^wordpress_(sec|logged_in)_/.test( c ) ).map( ( c ) => {
		const [ pair, ...attrs ] = c.split( ';' ).map( ( x ) => x.trim() );
		const eq = pair.indexOf( '=' );
		const cpath = attrs.find( ( x ) => x.toLowerCase().startsWith( 'path=' ) )?.slice( 5 ) || '/';
		return { name: pair.slice( 0, eq ), value: pair.slice( eq + 1 ), domain: 'hodima.test', path: cpath, secure: true, httpOnly: true };
	} );
	if ( ! cookies.length ) throw new Error( 'ورود مدیر ناموفق بود' );
	await ctx.addCookies( cookies );
	const page = await ctx.newPage();
	if ( process.env.CCSS_DEBUG ) page.on( 'requestfailed', ( r ) => console.log( 'FAILED', r.url(), r.failure()?.errorText ) );
	return page;
}

/** شمارنده درخواست‌های ajax به تفکیک اکشن. */
function trackAjax( page ) {
	const calls = {};
	page.on( 'request', ( req ) => {
		if ( req.url().includes( ajaxPath ) && req.method() === 'POST' ) {
			const m = /name="action"\r?\n\r?\n([a-z_]+)/.exec( req.postData() || '' ) || /action=([a-z_]+)/.exec( req.postData() || '' );
			if ( m ) calls[ m[ 1 ] ] = ( calls[ m[ 1 ] ] || 0 ) + 1;
		}
	} );
	return calls;
}

/** اجرای PHP روی همان پایگاه داده (فرایند جدا، مثل wp-cli). */
const here = path.dirname( fileURLToPath( import.meta.url ) );
const wpRoot = process.env.HODIMA_WP || path.join( process.env.HODIMA_HARNESS || '/tmp/hodima-harness', 'wp' );
function php( code ) {
	return execFileSync( 'php', [ path.join( here, 'wp-eval.php' ), code ], { env: { ...process.env, HODIMA_WP: wpRoot, HARNESS_WC: '1' } } ).toString().trim();
}

const states = () => JSON.parse( php( '$o=[];foreach(hodima_ccss_types() as $t=>$l){$d=hodima_ccss_get($t);$o[$t]=$d?(hash_equals($d["sig"],hodima_ccss_signature($t))?"ok":"stale"):"none";}echo json_encode($o);' ) );
const buildable = JSON.parse( php( 'echo json_encode(array_values(array_filter(array_keys(hodima_ccss_types()),fn($t)=>(bool)hodima_ccss_sample_urls($t))));' ) );
const onlyBuildable = ( map ) => Object.fromEntries( Object.entries( map ).filter( ( [ t ] ) => buildable.includes( t ) ) );
const lockState = () => JSON.parse( php( 'echo json_encode(hodima_ccss_auto_state());' ) );

/* ---------- آماده‌سازی ---------- */
php( 'if(!username_exists("ccssadmin")){wp_insert_user(["user_login"=>"ccssadmin","user_pass"=>"ccss-pass-123","role"=>"administrator"]);}' );
const setSettings = ( on, auto ) => php( `$s=get_option("hodima_theme_settings",[]);$s["critical_css"]=${ on };$s["critical_css_auto"]=${ auto };update_option("hodima_theme_settings",$s);delete_option("hodima_ccss_auto");` );
const clearData = () => php( 'foreach(hodima_ccss_types() as $t=>$l){delete_option(hodima_ccss_option($t));}delete_option("hodima_ccss_auto");' );

const ctx = await newContext();
const page = await login( ctx );
let calls = trackAjax( page );

/* ---------- ۱) خودکار روشن، چیزی ساخته نشده: ورود به داشبورد ← ساخت ---------- */
console.log( '۱) داشبورد با کلید خودکار روشن و بدون داده' );
setSettings( 'true', 'true' ); clearData();
await page.goto( `${ site }/wp-admin/`, { waitUntil: 'load' } );
const cfg = await page.evaluate( () => !! window.hodimaCcssAuto );
log( cfg, 'متغیر window.hodimaCcssAuto در داشبورد هست' );
const noticeSeen = await page.waitForSelector( '.notice.hodima-x, #wpbody-content .notice-info', { timeout: 20000 } ).then( () => true ).catch( () => false );
log( noticeSeen, 'اعلان «در حال به‌روزرسانی» نشان داده شد' );
const noticeText = noticeSeen ? await page.locator( '#wpbody-content .notice-info p' ).first().innerText() : '';
log( /CSS حیاتی/.test( noticeText ), 'متن اعلان درست است', noticeText.slice( 0, 90 ) );
const done = await page.waitForSelector( '#wpbody-content .notice-success', { timeout: 240000 } ).then( () => true ).catch( () => false );
log( done, 'اعلان «CSS حیاتی سایت به‌روز شد» آمد' );
let st = states();
log( Object.values( onlyBuildable( st ) ).every( ( v ) => v === 'ok' ), `همه ${ buildable.length } نوع قابل‌ساخت ساخته و تازه‌اند (نوع بی‌نمونه، مثل فروشگاه در ابزار تست، ساخته نمی‌شود و چرخه نمی‌سازد)`, JSON.stringify( st ) );
let lock = lockState();
log( lock.lock === 0 && lock.token === '', 'قفل بعد از پایان آزاد شد', JSON.stringify( lock ) );
log( ( calls.hodima_ccss_save || 0 ) === buildable.length, 'هر نوع جدا ذخیره شد', `${ calls.hodima_ccss_save || 0 } ذخیره` );
log( calls.hodima_ccss_auto_end === 1, 'درخواست پایان یک بار رفت' );
const cssSize = JSON.parse( php( 'echo json_encode(array_map(fn($t)=>hodima_ccss_get($t)["bytes"],' + JSON.stringify( buildable ).replace( /\[/, 'array(' ).replace( /\]$/, ')' ).replace( /"/g, "'" ) + '));' ) );
log( cssSize.every( ( b ) => b > 2000 ), 'CSS هر نوع خالی نیست', cssSize.join( ',' ) );

/* ---------- ۲) تازه است: ساخت دوباره نباید باشد ---------- */
console.log( '۲) بازدید دوباره (همه تازه)' );
const ctx2 = await newContext();
const page2 = await login( ctx2 ); // localStorage خالی: فقط سرور باید «تازه» بگوید
calls = trackAjax( page2 );
await page2.goto( `${ site }/wp-admin/`, { waitUntil: 'load' } );
await wait( 6000 );
log( calls.hodima_ccss_auto_claim === 1 && ! calls.hodima_ccss_save, 'یک بار پرسید و چیزی نساخت', JSON.stringify( calls ) );
const again = await page2.evaluate( async () => {
	const f = new FormData(); f.append( 'action', 'hodima_ccss_auto_claim' ); f.append( 'nonce', window.hodimaCcssAuto.nonce );
	return ( await ( await fetch( window.hodimaCcssAuto.ajax, { method: 'POST', body: f } ) ).json() ).data;
} );
log( again.build === false && again.reason === 'fresh', 'پاسخ سرور: تازه', JSON.stringify( again ) );
// فاصله بررسی در همان مرورگر: بازدید دوم بدون پرسش
calls = trackAjax( page2 );
await page2.goto( `${ site }/wp-admin/plugins.php`, { waitUntil: 'load' } );
await wait( 5000 );
log( ! calls.hodima_ccss_auto_claim, 'در همان مرورگر تا ۱۰ دقیقه دوباره نپرسید', JSON.stringify( calls ) );
await ctx2.close();

/* ---------- ۳) کهنه شدن (تغییر تنظیمات) ← ساخت دوباره فقط وقتی لازم ---------- */
console.log( '۳) کهنه شدن بعد از تغییر تنظیمات' );
php( '$s=get_option("hodima_theme_settings",[]);$s["footer_copyright"]="ccss-test-".time();update_option("hodima_theme_settings",$s);' );
st = states();
log( Object.values( onlyBuildable( st ) ).every( ( v ) => v === 'stale' ), 'همه نوع‌های ساخته‌شده کهنه شدند', JSON.stringify( st ) );
const ctx3 = await newContext();
const page3 = await login( ctx3 );
calls = trackAjax( page3 );
await page3.goto( `${ site }/wp-admin/update-core.php`, { waitUntil: 'load' } ); // صفحه به‌روزرسانی‌ها
const done3 = await page3.waitForSelector( '#wpbody-content .notice-success', { timeout: 240000 } ).then( () => true ).catch( () => false );
log( done3, 'ساخت دوباره انجام و تمام شد' );
st = states();
log( Object.values( onlyBuildable( st ) ).every( ( v ) => v === 'ok' ), 'دوباره همه تازه‌اند', JSON.stringify( st ) );
await ctx3.close();

/* ---------- ۴) قفل هم‌زمان و فاصله بعد از خطا (سرور) ---------- */
console.log( '۴) قفل و فاصله بعد از خطا' );
php( '$s=get_option("hodima_theme_settings",[]);$s["footer_copyright"]="ccss-test2-".time();update_option("hodima_theme_settings",$s);delete_option("hodima_ccss_auto");' );
const ajax = ( p, action, extra = {} ) => p.evaluate( async ( { action, extra } ) => {
	const f = new FormData(); f.append( 'action', action ); f.append( 'nonce', window.hodimaCcssAuto.nonce );
	Object.entries( extra ).forEach( ( [ k, v ] ) => f.append( k, v ) );
	return ( await ( await fetch( window.hodimaCcssAuto.ajax, { method: 'POST', body: f } ) ).json() );
}, { action, extra } );
await page.goto( `${ site }/wp-admin/`, { waitUntil: 'domcontentloaded' } );
// صفحه خودش ساخت نکند: قفل را با claim دستی می‌گیریم؛ ساخت صفحه به‌خاطر قفل شروع نمی‌شود
const c1 = await ajax( page, 'hodima_ccss_auto_claim' );
log( c1.data?.build === true && !! c1.data.token && Object.keys( c1.data.types ).length === buildable.length, `نخستین claim: ساخت + نشانه + ${ buildable.length } نوع`, `${ Object.keys( c1.data?.types || {} ).length } نوع` );
const c2 = await ajax( page, 'hodima_ccss_auto_claim' );
log( c2.data?.build === false && c2.data.reason === 'busy', 'claim هم‌زمان دوم: busy', JSON.stringify( c2.data ) );
const bad = await ajax( page, 'hodima_ccss_auto_end', { token: 'wrong', built: 0, failed: 1 } );
const stillBusy = lockState();
log( stillBusy.lock > 0, 'پایان با نشانه اشتباه قفل را آزاد نمی‌کند' );
await ajax( page, 'hodima_ccss_auto_end', { token: c1.data.token, built: 0, failed: 2 } );
lock = lockState();
log( lock.lock === 0 && lock.fail > Date.now() / 1000 + 5 * 3600, 'بعد از خطا قفل آزاد و ۶ ساعت فاصله', JSON.stringify( lock ) );
const c3 = await ajax( page, 'hodima_ccss_auto_claim' );
log( c3.data?.build === false && c3.data.reason === 'backoff', 'claim بعد از خطا: backoff', JSON.stringify( c3.data ) );
// کاربر بدون دسترسی/بدون nonce
const noNonce = await page.evaluate( async ( ajaxUrl ) => {
	const f = new FormData(); f.append( 'action', 'hodima_ccss_auto_claim' );
	return ( await fetch( ajaxUrl, { method: 'POST', body: f } ) ).status;
}, await page.evaluate( () => window.hodimaCcssAuto.ajax ) );
log( noNonce === 403, 'بدون nonce رد می‌شود', String( noNonce ) );

/* ---------- ۵) خاموش‌ها ---------- */
console.log( '۵) خاموش بودن و صفحه ویرایش' );
php( 'delete_option("hodima_ccss_auto");' );
setSettings( 'true', 'false' );
await page.goto( `${ site }/wp-admin/`, { waitUntil: 'load' } );
log( ! ( await page.evaluate( () => !! window.hodimaCcssAuto ) ), 'با کلید خودکار خاموش: اسکریپت و تنظیم در داشبورد نیست' );
setSettings( 'false', 'true' );
await page.goto( `${ site }/wp-admin/`, { waitUntil: 'load' } );
log( ! ( await page.evaluate( () => !! window.hodimaCcssAuto ) ), 'با «CSS حیاتی» خاموش: ساخت خودکار نیست' );
setSettings( 'true', 'true' );
await page.goto( `${ site }/wp-admin/post-new.php`, { waitUntil: 'domcontentloaded' } );
log( ! ( await page.evaluate( () => !! window.hodimaCcssAuto ) ), 'صفحه نوشته تازه: ساخت خودکار نیست' );

/* ---------- ۶) صفحه تنظیمات: پیشرفت در قاب ---------- */
console.log( '۶) صفحه تنظیمات' );
// نوع‌های دیگر تازه بمانند: داده تازه را از ساخت بخش ۳ نگه می‌داریم، پس اول مطمئن می‌شویم همه تازه‌اند
php( 'delete_option("hodima_ccss_auto");' );
if ( Object.values( onlyBuildable( states() ) ).some( ( v ) => v !== 'ok' ) ) {
	const fix = await newContext(); const fp = await login( fix );
	await fp.goto( `${ site }/wp-admin/`, { waitUntil: 'load' } );
	await fp.waitForSelector( '#wpbody-content .notice-success', { timeout: 240000 } ).catch( () => null );
	await fix.close();
}
php( 'delete_option("hodima_ccss_auto");foreach(["home","blog"] as $t){delete_option(hodima_ccss_option($t));}' );
const ctx6 = await newContext();
const page6 = await login( ctx6 );
await page6.goto( `${ site }/wp-admin/admin.php?page=hodima-settings&tab=pagespeed`, { waitUntil: 'load' } );
const badge = page6.locator( 'tr[data-type="home"] .hodima-ccss__state' );
const sawBusy = await page6.waitForFunction( () => document.querySelector( 'tr[data-type="home"] .hodima-ccss__state' )?.dataset.state === 'busy', null, { timeout: 30000 } ).then( () => true ).catch( () => false );
log( sawBusy, 'ردیف «صفحه اصلی» وضعیت «در حال ساخت» گرفت' );
const okRows = await page6.waitForFunction( () => [ 'home', 'blog' ].every( ( t ) => document.querySelector( `tr[data-type="${ t }"] .hodima-ccss__state` )?.dataset.state === 'ok' ), null, { timeout: 120000 } ).then( () => true ).catch( () => false );
log( okRows, 'دو ردیف ساخته‌شده «آماده» شدند' );
const otherRows = await page6.evaluate( ( ok ) => [ ...document.querySelectorAll( 'tr[data-type]' ) ].filter( ( r ) => ok.includes( r.dataset.type ) && ! [ 'home', 'blog' ].includes( r.dataset.type ) ).map( ( r ) => r.querySelector( '.hodima-ccss__state' ).dataset.state ), buildable );
log( otherRows.every( ( s ) => s === 'ok' ), 'نوع‌های تازه دست نخوردند', otherRows.join( ',' ) );
log( ( await page6.locator( '#wpbody-content .notice-info' ).count() ) === 0, 'در صفحه تنظیمات اعلان جدا نیست (پیشرفت در قاب)' );
await page6.waitForFunction( () => /ساخت تمام شد/.test( document.querySelector( '.hodima-ccss__log' )?.textContent || '' ), null, { timeout: 60000 } ).catch( () => null );
const logText = await page6.locator( '.hodima-ccss__log' ).innerText();
log( /ساخت تمام شد/.test( logText ), 'پیام پایان در قاب', logText.slice( 0, 60 ) );
await page6.screenshot( { path: '/tmp/hodima-harness/ccss-auto-settings.png', fullPage: false } );

/* ---------- ۷) رها کردن صفحه وسط ساخت: قفل آزاد شود، خطا حساب نشود ---------- */
console.log( '۷) بستن صفحه وسط ساخت' );
php( '$s=get_option("hodima_theme_settings",[]);$s["footer_copyright"]="ccss-test3-".time();update_option("hodima_theme_settings",$s);delete_option("hodima_ccss_auto");' );
const ctx7 = await newContext();
const page7 = await login( ctx7 );
await page7.goto( `${ site }/wp-admin/`, { waitUntil: 'load' } );
let locked = false;
for ( let i = 0; i < 40 && ! locked; i++ ) { await wait( 500 ); locked = lockState().lock > 0; }
log( locked, 'ساخت شروع شد و قفل گرفته شد' );
await wait( 3000 ); // کمی ساخت پیش برود
// رویداد pagehide را همین‌جا می‌فرستیم (Playwright درخواست ping وسط بستن صفحه را نیمه‌کاره قطع می‌کند و به سرور نمی‌رسد؛
// تحویل beacon هنگام بستن کار خود مرورگر است، آنچه اینجا سنجیده می‌شود منطق ما از صفحه تا سرور است)
await page7.evaluate( () => window.dispatchEvent( new Event( 'pagehide' ) ) );
await wait( 3000 );
lock = lockState();
log( lock.lock === 0 && lock.token === '' && lock.fail === 0, 'قفل آزاد شد و فاصله بعد از خطا گذاشته نشد', JSON.stringify( lock ) );
await page7.goto( 'about:blank' );
await ctx7.close();

await browser.close();
console.log( failures ? `\n✘ ${ failures } بررسی رد شد` : '\n✔ همه بررسی‌ها قبول' );
process.exit( failures ? 1 : 0 );
