/**
 * آزمون صفحه «شماره‌های تماس» پیشخوان (Commerce 1.3.1؛ components/phone/phone-admin.php + .css + .js)
 * در Chromium واقعی با ورود واقعی مدیر روی وردپرس ابزار تست (php -S، نه HTML ازپیش‌ساخته).
 *   tools/wp-harness/phone-admin-check.sh        (این فایل را راه می‌اندازد)
 *
 * می‌سنجد: فهرست (آمار، ۲۰ ردیف، منو با نشان «جدید»)، انتخاب همه و فعال شدن «اجرا»، «دیده شد» ردیف،
 * کار گروهی «زباله‌دان»، بازگردانی، حذف همیشگی با پنجره تأیید (انصراف و تأیید)، کپی شماره، جزئیات (دیده شد خودکار)،
 * ریدایرکت نشانی‌های قدیمی (edit.php و post.php)، نوار ابزار یک‌ردیفه، کادر هم‌شکل نماها، نبودن بنفش و کارت نگهداری، دانلود CSV،
 * و axe + عکس در ۱۳۰۰ و ۳۹۰. خروجی: ✔/✘؛ کد خروج ۱ اگر یکی رد شود. عکس‌ها: $HODIMA_HARNESS/phone-admin.
 */
import { chromium } from 'playwright-core';
import { execFileSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';

const require = createRequire( import.meta.url );
const axeSource = fs.readFileSync( require.resolve( 'axe-core/axe.min.js' ), 'utf8' );
const port = process.env.CCSS_PORT || '8766';
const origin = `http://127.0.0.1:${ port }`;
const site = 'https://hodima.test';
const here = path.dirname( fileURLToPath( import.meta.url ) );
const harness = process.env.HODIMA_HARNESS || '/tmp/hodima-harness';
const wpRoot = process.env.HODIMA_WP || path.join( harness, 'wp' );
const shots = path.join( harness, 'phone-admin' );
fs.mkdirSync( shots, { recursive: true } );

const browser = await chromium.launch( { executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium' } ).catch( () => chromium.launch() );
let failures = 0;
const log = ( ok, name, detail = '' ) => {
	if ( ! ok ) failures++;
	console.log( `  ${ ok ? '✔' : '✘' } ${ name }${ detail ? ` — ${ detail }` : '' }` );
};

function php( code ) {
	return execFileSync( 'php', [ path.join( here, 'wp-eval.php' ), code ], { env: { ...process.env, HODIMA_WP: wpRoot, HARNESS_WC: '1' } } ).toString().trim();
}

async function newContext( width = 1300 ) {
	const ctx = await browser.newContext( { viewport: { width, height: 900 }, ignoreHTTPSErrors: true, locale: 'fa-IR' } );
	await ctx.route( /^https:\/\/hodima\.test(?:[/?#]|$)/, async ( route ) => {
		const req = route.request();
		const headers = { ...req.headers(), host: 'hodima.test' };
		delete headers[ 'content-length' ];
		try {
			const res = await route.fetch( { url: req.url().replace( site, origin ), headers, maxRedirects: 0 } );
			const out = { status: res.status(), headers: res.headers(), body: await res.body() };
			if ( out.headers.location ) {
				// پاسخ ریدایرکت route.fulfill را Chromium از proxy می‌گیرد؛ به‌جایش صفحه‌ای که خودش به آدرس تازه می‌رود
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
	const body = new URLSearchParams( { log: 'phoneadmin', pwd: 'phone-pass-123', 'wp-submit': 'Log In', redirect_to: `${ site }/wp-admin/`, testcookie: '1' } );
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


/** کلیک/کنشی که ارسال فرم + ریدایرکت دارد: دو بار load (صفحه میانی ریدایرکت و مقصد). */
async function act( pg, fn, loads = 2 ) {
	let n = 0;
	const h = () => { n++; };
	pg.on( 'load', h );
	await fn();
	const t = Date.now();
	while ( n < loads && Date.now() - t < 20000 ) await pg.waitForTimeout( 50 );
	pg.off( 'load', h );
	await pg.waitForTimeout( 100 );
}
/** goto روی نشانی‌ای که ریدایرکت می‌شود. */
const gotoFollow = ( pg, url ) => act( pg, () => pg.goto( url, { waitUntil: 'load' } ) );

/* ---------- آماده‌سازی: ۴۵ لید (۱۵ جدید) + یک لید قدیمی ---------- */
php( 'if(!username_exists("phoneadmin")){wp_insert_user(["user_login"=>"phoneadmin","user_pass"=>"phone-pass-123","role"=>"administrator"]);}' );
php( `
global $wpdb; $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%hodima_phone%'");
foreach(get_posts(["post_type"=>"hodima_phone_lead","post_status"=>"any","posts_per_page"=>-1,"fields"=>"ids"]) as $i){wp_delete_post($i,true);}
$titles=["خرید و قیمت عمده اکسسوری مو وارداتی | بیش از 510 کالای متنوع","شرکت بازرگانی هدهد","سبد خرید","کلیپس مو"];
for($i=0;$i<45;$i++){
  $ts=time()-3600*($i*5+1);
  $id=wp_insert_post(["post_type"=>"hodima_phone_lead","post_title"=>sprintf("0912%07d",1000000+$i*7919),"post_status"=>"publish","post_date"=>wp_date("Y-m-d H:i:s",$ts),"post_date_gmt"=>gmdate("Y-m-d H:i:s",$ts)]);
  $c=($i%7==0)?3:1;$h=[];for($k=0;$k<$c;$k++)$h[]=["url"=>home_url("/hair/"),"title"=>$titles[($i+$k)%4],"time"=>wp_date("Y-m-d H:i",$ts-3600*$k*9)];
  update_post_meta($id,HODIMA_PHONE_META,["count"=>$c,"last_url"=>home_url("/hair/"),"last_title"=>$titles[$i%4],"last_time"=>wp_date("Y-m-d H:i",$ts),"history"=>array_reverse($h)]);
  if($i%3!==0) update_post_meta($id,"hodima_seen",1);
}
echo "ok";` );
const db = ( code ) => php( code );
const state = () => JSON.parse( db( 'echo json_encode(hodima_phone_counts(true));' ) );
const idByPhone = ( phone ) => Number( db( `$p=get_posts(["post_type"=>"hodima_phone_lead","post_status"=>["publish","draft","pending","private","trash"],"title"=>"${ phone }","fields"=>"ids","posts_per_page"=>1]);echo $p[0]??0;` ) );
const statusOf = ( id ) => db( `echo get_post_status(${ id }) ?: "none";` );
const seenOf = ( id ) => db( `echo get_post_meta(${ id },"hodima_seen",true) ? "seen":"new";` );

const axe = async ( page, selector, name ) => {
	await page.addScriptTag( { content: axeSource } );
	const res = await page.evaluate( async ( sel ) => window.axe.run( document.querySelector( sel ), { runOnly: [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa' ] } ), selector );
	log( res.violations.length === 0, `axe بدون نقض: ${ name }`, res.violations.map( ( v ) => `${ v.id }(${ v.nodes.length })` ).join( ' ' ) );
};

const ctx = await newContext();
await ctx.grantPermissions( [ 'clipboard-read', 'clipboard-write' ], { origin: site } );
const page = await login( ctx );
const errors = [];
page.on( 'pageerror', ( e ) => errors.push( e.message ) );
if ( process.env.PHONE_DEBUG ) page.on( 'response', ( r ) => r.url().includes( 'admin-post' ) && console.log( 'RESP', r.status(), r.headers().location ) );
const list = `${ site }/wp-admin/admin.php?page=hodima-phone-leads`;

/* ---------- ۱) فهرست ---------- */
console.log( '۱) فهرست' );
const before = state();
await page.goto( list, { waitUntil: 'load' } );
log( ( await page.locator( 'tbody tr' ).count() ) === 20, 'صفحه اول ۲۰ ردیف دارد' );
log( before.total === 45 && before.new === 15, 'شمارنده‌ها: ۴۵ کل، ۱۵ جدید', JSON.stringify( before ) );
log( ( await page.locator( '.hd-stat' ).count() ) === 4, 'چهار کادر خلاصه' );
log( ( await page.locator( '#toplevel_page_hodima-phone-leads .awaiting-mod' ).count() ) === 1, 'منوی سطح اول با نشان «جدید»' );
log( ( await page.locator( '[data-hd-bulk-apply]' ).isDisabled() ), 'دکمه «اجرا» بدون انتخاب غیرفعال است' );
await axe( page, '.hd-leads', 'فهرست ۱۳۰۰' );
await page.locator( '.hd-leads' ).screenshot( { path: path.join( shots, 'list-1300.png' ) } );

/* ---------- ۲) انتخاب همه ---------- */
console.log( '۲) انتخاب' );
await page.locator( '[data-hd-select-all]' ).check();
log( ( await page.locator( '[data-hd-row]:checked' ).count() ) === 20, 'انتخاب همه: ۲۰ ردیف' );
log( ! ( await page.locator( '[data-hd-bulk-apply]' ).isDisabled() ), 'دکمه «اجرا» فعال شد' );
log( /۲۰/.test( await page.locator( '[data-hd-bulk-apply]' ).textContent() ), 'شمار انتخاب روی دکمه «اجرا (۲۰)»' );
await page.locator( '[data-hd-select-all]' ).uncheck();
log( ( await page.locator( '[data-hd-row]:checked' ).count() ) === 0, 'برداشتن همه' );

/* ---------- ۳) دیده شد روی یک ردیف ---------- */
console.log( '۳) دیده شد' );
const newRow = page.locator( 'tbody tr.is-new' ).first();
const phone1 = ( await newRow.locator( '.hd-leads__num' ).textContent() ).replace( /\s/g, '' );
const id1 = idByPhone( phone1 );
await act( page, () => newRow.locator( 'button[value^="seen:"]' ).click() );
log( seenOf( id1 ) === 'seen', 'لید در پایگاه داده «دیده‌شده» شد', phone1 );
log( await page.locator( '.notice' ).filter( { hasText: 'شماره' } ).count() > 0, 'اعلان نتیجه نمایش داده شد' );
log( state().new === 14, 'شمارنده جدید ۱۴' );

/* ---------- ۴) کار گروهی: زباله‌دان ---------- */
console.log( '۴) کار گروهی' );
const rows = page.locator( '[data-hd-row]' );
const bulkPhones = [];
for ( let i = 0; i < 2; i++ ) {
	await rows.nth( i ).check();
	bulkPhones.push( ( await page.locator( 'tbody tr' ).nth( i ).locator( '.hd-leads__num' ).textContent() ).replace( /\s/g, '' ) );
}
await page.selectOption( '#hd-leads-bulk', 'trash' );
await act( page, () => page.locator( '[data-hd-bulk-apply]' ).click() );
log( bulkPhones.every( ( p ) => statusOf( idByPhone( p ) ) === 'trash' ), 'هر دو لید به زباله‌دان رفتند' );
log( ( await page.locator( '.hd-leads__seg-item', { hasText: 'زباله‌دان' } ).count() ) === 1, 'زبانه «زباله‌دان» پیدا شد' );

/* ---------- ۵) زباله‌دان: بازگردانی و حذف همیشگی با تأیید ---------- */
console.log( '۵) زباله‌دان' );
await page.goto( `${ list }&state=trash`, { waitUntil: 'load' } );
log( ( await page.locator( 'tbody tr' ).count() ) === 2, 'دو ردیف در زباله‌دان' );
await page.locator( 'tbody tr' ).first().locator( 'button[value^="delete:"]' ).click();
log( await page.locator( '#hd-leads-dialog' ).evaluate( ( d ) => d.open ), 'پنجره تأیید باز شد' );
await page.locator( '#hd-leads-dialog button[value="cancel"]' ).click();
log( statusOf( idByPhone( bulkPhones[ 0 ] ) ) === 'trash' || statusOf( idByPhone( bulkPhones[ 1 ] ) ) === 'trash', 'با انصراف چیزی حذف نشد' );
const delPhone = ( await page.locator( 'tbody tr' ).first().locator( '.hd-leads__num' ).textContent() ).replace( /\s/g, '' );
await page.locator( 'tbody tr' ).first().locator( 'button[value^="delete:"]' ).click();
await act( page, () => page.locator( '#hd-leads-dialog button[value="ok"]' ).click() );
log( idByPhone( delPhone ) === 0, 'بعد از تأیید برای همیشه حذف شد', delPhone );
await page.goto( `${ list }&state=trash`, { waitUntil: 'load' } );
await act( page, () => page.locator( 'tbody tr' ).first().locator( 'button[value^="restore:"]' ).click() );
log( state().trash === 0 && state().total === 44, 'بازگردانی: زباله‌دان خالی و ۴۴ لید', JSON.stringify( state() ) );

/* ---------- ۶) کپی شماره ---------- */
console.log( '۶) کپی' );
await page.goto( list, { waitUntil: 'load' } );
const firstPhone = ( await page.locator( 'tbody tr' ).first().locator( '.hd-leads__num' ).textContent() ).replace( /\s/g, '' );
await page.locator( 'tbody tr' ).first().locator( '[data-hd-copy]' ).click();
await page.waitForTimeout( 300 );
log( ( await page.evaluate( () => navigator.clipboard.readText() ) ) === firstPhone, 'شماره (لاتین، بدون فاصله) در کلیپ‌بورد', firstPhone );
log( /کپی شد/.test( await page.locator( '#hd-leads-live' ).textContent() ), 'اعلام صفحه‌خوان «کپی شد»' );

/* ---------- ۷) جزئیات ---------- */
console.log( '۷) جزئیات' );
const newOne = page.locator( 'tbody tr.is-new' ).first();
const pn = ( await newOne.locator( '.hd-leads__num' ).textContent() ).replace( /\s/g, '' );
const idn = idByPhone( pn );
await newOne.locator( '.hd-leads__phone a' ).click();
await page.waitForLoadState( 'load' );
log( page.url().includes( `lead=${ idn }` ), 'آدرس جزئیات', page.url() );
log( seenOf( idn ) === 'seen', 'باز کردن جزئیات = دیده شد' );
log( ( await page.locator( '.hd-leads__timeline li' ).count() ) >= 1, 'تاریخچه نمایش داده می‌شود' );
log( ( await page.locator( 'h1' ).first().textContent() ).replace( /[\s⁦⁩]/g, '' ) === pn, 'عنوان همان شماره است' );
await axe( page, '.hd-leads', 'جزئیات ۱۳۰۰' );
await page.locator( '.hd-leads' ).screenshot( { path: path.join( shots, 'detail-1300.png' ) } );
await act( page, () => page.locator( 'button[value^="unseen:"]' ).click() );
// بعد از «علامت: جدید» به همان جزئیات برمی‌گردد و دوباره دیده می‌شود؛ پس فقط ریدایرکت درست را می‌سنجیم
log( page.url().includes( `lead=${ idn }` ), 'عملیات از جزئیات به همان جزئیات برمی‌گردد' );

/* ---------- ۸) نشانی‌های قدیمی ---------- */
console.log( '۸) ریدایرکت پیشخوان قدیمی' );
await gotoFollow( page, `${ site }/wp-admin/edit.php?post_type=hodima_phone_lead` );
log( page.url().includes( 'page=hodima-phone-leads' ), 'edit.php ← صفحه تازه', page.url() );
await gotoFollow( page, `${ site }/wp-admin/post.php?post=${ idn }&action=edit` );
log( page.url().includes( `lead=${ idn }` ), 'post.php ← جزئیات', page.url() );

/* ---------- ۹) نوار ابزار یک‌ردیفه و طراحی هم‌شکل ---------- */
console.log( '۹) نوار ابزار و طراحی' );
await page.goto( list, { waitUntil: 'load' } );
const tops = await page.evaluate( () => [ '.hd-leads__bulk', '.hd-leads__seg', '.hd-leads__search', '.hd-leads__csv' ].map( ( sel ) => {
	const r = document.querySelector( sel ).getBoundingClientRect();
	return Math.round( r.top + r.height / 2 );
} ) );
log( Math.max( ...tops ) - Math.min( ...tops ) <= 4, 'کار گروهی، نماها، جستجو و CSV در یک ردیف', tops.join( ',' ) );
log( ( await page.locator( '.hd-leads .hd-header__actions' ).count() ) === 0, 'CSV در هدر نیست' );
const borders = await page.evaluate( () => [ ...document.querySelectorAll( '.hd-leads__seg-item' ) ].map( ( e ) => `${ getComputedStyle( e ).borderTopWidth }|${ getComputedStyle( e ).borderTopLeftRadius }` ) );
log( borders.length >= 3 && new Set( borders ).size === 1 && ! borders[ 0 ].startsWith( '0px' ), 'همه نماها کادر هم‌شکل دارند', borders.join( ' ' ) );
const purple = await page.evaluate( () => {
	let n = 0;
	document.querySelectorAll( '.hd-leads *' ).forEach( ( e ) => {
		if ( e.closest( '.hd-header' ) ) return; // هدر مشترک Core (گرادیان) خارج از این صفحه است
		const c = getComputedStyle( e );
		for ( const k of [ 'color', 'backgroundColor', 'borderTopColor', 'borderInlineStartColor', 'boxShadow' ] ) {
			if ( /106, 43, 154|163, 65, 200/.test( c[ k ] ) ) n++;
		}
	} );
	return n;
} );
log( purple === 0, 'هیچ عنصری بنفش نیست (جدیدها سبز)', String( purple ) );
const greenDot = await page.locator( 'tr.is-new .hd-leads__dot' ).first().evaluate( ( e ) => getComputedStyle( e ).backgroundColor );
log( greenDot === 'rgb(31, 122, 77)', 'نقطه «جدید» سبز است', greenDot );
log( ( await page.locator( '.hd-leads__ret, .hd-leads details' ).count() ) === 0, 'کارت «نگهداری شماره‌ها» نیست' );
log( ( await page.locator( 'body' ).innerText() ).includes( 'نگهداری شماره' ) === false, 'متنی از نگهداری خودکار در صفحه نیست' );

/* ---------- ۱۰) CSV ---------- */
console.log( '۱۰) CSV' );
const csvHref = await page.locator( '.hd-leads__csv' ).getAttribute( 'href' );
const cookie = ( await ctx.cookies() ).map( ( c ) => `${ c.name }=${ c.value }` ).join( '; ' );
const csv = await fetch( csvHref.replace( site, origin ), { headers: { host: 'hodima.test', cookie } } );
const csvText = await csv.text();
log( csv.status === 200 && ( csv.headers.get( 'content-type' ) || '' ).includes( 'text/csv' ), 'دانلود CSV (۲۰۰، text/csv)', `${ csv.status }` );
log( csvText.split( '\n' ).filter( Boolean ).length === 45, 'CSV: سرستون + ۴۴ شماره', String( csvText.split( '\n' ).filter( Boolean ).length ) );

/* ---------- ۱۱) موبایل ---------- */
console.log( '۱۱) موبایل ۳۹۰' );
const mctx = await newContext( 390 );
const mpage = await login( mctx );
await mpage.goto( list, { waitUntil: 'load' } );
log( ( await mpage.evaluate( () => document.documentElement.scrollWidth <= window.innerWidth ) ), 'بدون اسکرول افقی صفحه' );
const rowDisplay = await mpage.locator( 'tbody tr' ).first().evaluate( ( el ) => getComputedStyle( el ).display );
log( rowDisplay === 'grid', 'ردیف‌ها کارت (grid) شده‌اند', rowDisplay );
const small = await mpage.evaluate( () => [ ...document.querySelectorAll( '.hd-leads button, .hd-leads a.hd-leads__seg-item, .hd-leads input[type=search], .hd-leads select, .hd-leads .hd-leads__phone a, .hd-leads .page-numbers' ) ].filter( ( el ) => el.offsetParent && ( el.getBoundingClientRect().height < 39.5 || el.getBoundingClientRect().width < 39.5 ) ).map( ( el ) => `${ el.tagName }.${ el.className }:${ Math.round( el.getBoundingClientRect().width ) }x${ Math.round( el.getBoundingClientRect().height ) }` ) );
log( small.length === 0, 'هدف‌های لمسی ≥ ۴۰px', small.join( ' ' ) );
await axe( mpage, '.hd-leads', 'فهرست ۳۹۰' );
await mpage.locator( '.hd-leads' ).screenshot( { path: path.join( shots, 'list-390.png' ) } );
await mpage.locator( 'tbody tr' ).first().locator( '.hd-leads__phone a' ).click();
await mpage.waitForLoadState( 'load' );
await axe( mpage, '.hd-leads', 'جزئیات ۳۹۰' );
log( ( await mpage.evaluate( () => document.documentElement.scrollWidth <= window.innerWidth ) ), 'جزئیات: بدون اسکرول افقی' );
await mpage.locator( '.hd-leads' ).screenshot( { path: path.join( shots, 'detail-390.png' ) } );

// اسکریپت‌های درون‌خطی خود وردپرس بدون بسته wp-admin ابزار تست خطا می‌دهند؛ فقط خطای کد هدیما شمرده می‌شود
const own = errors.filter( ( e ) => ! /wp is not defined|jQuery is not defined|\$ is not defined/.test( e ) );
log( own.length === 0, 'بدون خطای جاوااسکریپت (غیر از اسکریپت‌های درون‌خطی وردپرس)', own.join( ' | ' ) );

php( 'foreach(get_posts(["post_type"=>"hodima_phone_lead","post_status"=>"any","posts_per_page"=>-1,"fields"=>"ids"]) as $i){wp_delete_post($i,true);}' );
await browser.close();
console.log( failures ? `✘ ${ failures } رد شد` : '✔ همه قبول' );
process.exit( failures ? 1 : 0 );
