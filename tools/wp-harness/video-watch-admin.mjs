/**
 * آزمون رفتار کادر «تنظیمات ویدیو» در ویرایش ویدیو (پیشخوان؛ meta-boxes.php + video-watch.admin.js + اجزای Core: تب، رسانه، لیست تکرارشونده) در Chromium.
 *   node tools/wp-harness/video-watch-admin.mjs [root]
 * کادر را با PHP همین کد (admin-render-screen.php، حساب مدیر) برای ویدیوی آزمایشی می‌سازد
 * و تب‌ها، نوار وضعیت، چک‌لیست، پیش‌نمایش، وضعیت آدرس، «آنچه گوگل می‌بیند»، کلید دکمه محصول،
 * کارت‌های مرتبط (افزودن، جابه‌جایی، حذف و بازگردانی، نام فیلدها)، کاور و چیدمان ۳۹۰ را می‌سنجد. ظاهر و axe: admin-shot.mjs.
 * HODIMA-AUDIT.md بخش ۲۲۰. خروجی ✔/✘؛ کد خروج ۱ اگر یکی رد شود.
 */
import { chromium } from 'playwright-core';
import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const here = path.dirname( fileURLToPath( import.meta.url ) );
const root = path.resolve( process.argv[ 2 ] || path.join( here, '../..' ) );
const wp = process.env.HODIMA_WP || path.join( process.env.HODIMA_HARNESS || '/tmp/hodima-harness', 'wp' );
const env = { ...process.env, HARNESS: '1', HARNESS_USER: '1', HODIMA_WP: wp };

const id = execFileSync( 'php', [ path.join( here, 'wp-eval.php' ), `$v = get_page_by_path( 'harness-admin-box', OBJECT, 'video' ); $id = $v ? $v->ID : wp_insert_post( [ 'post_type' => 'video', 'post_title' => 'کادر آزمایشی', 'post_name' => 'harness-admin-box', 'post_status' => 'publish' ] );
foreach ( [ '_hod_video_url' => 'https://hodima.test/v/pin.mp4', '_hod_video_thumbnail' => 'https://hodima.test/v/pin.jpg', '_hod_video_duration' => 'PT1M10S', '_hod_video_chapters' => "0:00 شروع\\n0:30 جنس", '_hod_video_transcript' => "یک دو سه.\\n\\nچهار پنج.", '_hod_related_product_url' => 'https://hodima.test/product/x/' ] as $k => $val ) { update_post_meta( $id, $k, $val ); }
update_post_meta( $id, '_hod_related_links', [ [ 'title' => 'اول', 'url' => 'https://hodima.test/a/', 'cover' => 'https://hodima.test/c.jpg' ], [ 'title' => 'دوم', 'url' => 'https://hodima.test/b/', 'cover' => '' ] ] );
echo $id;` ], { env } ).toString().trim();
const html = execFileSync( 'php', [ path.join( here, 'admin-render-screen.php' ), 'post.php', `post=${ id }&action=edit` ], { env, maxBuffer: 64 * 1024 * 1024 } ).toString()
	// ویرایشگر بلوکی کادرها را با React جابه‌جا می‌کند؛ اینجا همان HTML سرور دیده شود
	.replace( 'id="metaboxes" class="hidden"', 'id="metaboxes" class=""' )
	.replace( /<html([^>]*) dir="ltr"/, '<html$1 dir="rtl"' )
	.replace( /<html(?![^>]*\sdir=)/, '<html dir="rtl"' );
if ( ! html.includes( 'data-hvw' ) ) {
	console.error( '✘ کادر تنظیمات ویدیو در HTML نیست' );
	process.exit( 1 );
}

const browser = await chromium.launch( { executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium' } ).catch( () => chromium.launch() );
let failures = 0;
const log = ( ok, name, detail = '' ) => {
	if ( ! ok ) failures++;
	console.log( `  ${ ok ? '✔' : '✘' } ${ name }${ detail ? ` — ${ detail }` : '' }` );
};

const page = await browser.newPage( { viewport: { width: 1300, height: 900 } } );
const errors = [];
// فقط خطای فایل‌های خود کادر (اسکریپت‌های هسته وردپرس در ابزار تست لود نمی‌شوند: wp/jQuery تعریف‌نشده)
page.on( 'pageerror', ( e ) => {
	if ( /video-watch\.admin\.js|admin-ui\.js/.test( e.stack || '' ) ) errors.push( e.message );
} );
await page.route( '**/*', async ( route ) => {
	const u = new URL( route.request().url() );
	if ( u.pathname === '/__page' ) return route.fulfill( { contentType: 'text/html; charset=utf-8', body: html } );
	const m = u.pathname.match( /^\/wp-content\/plugins\/(hodima-[a-z]+)\/(.*)$/ );
	const f = m ? path.join( root, 'plugins', m[ 1 ], m[ 2 ] ) : null;
	if ( f && /\.(js|css)$/.test( f ) && fs.existsSync( f ) ) return route.fulfill( { contentType: f.endsWith( '.js' ) ? 'text/javascript' : 'text/css', body: fs.readFileSync( f ) } );
	return route.fulfill( { status: 404, body: '' } );
} );
await page.goto( 'https://hodima.test/__page' );
await page.waitForTimeout( 300 );

const q = ( fn, arg ) => page.evaluate( fn, arg );
const summary = () => q( () => ( { shown: ! document.querySelector( '[data-hvw-summary]' ).hidden, text: document.querySelector( '[data-hvw-summary]' ).textContent, pill: document.querySelector( '[data-hvw-pill]' ).textContent, pillCls: document.querySelector( '[data-hvw-pill]' ).className } ) );
const check = ( key ) => q( ( k ) => { const li = document.querySelector( `[data-hvw-check="${ k }"]` ); return { state: li.dataset.state, tag: li.querySelector( '.hd-checklist__tag' ).textContent, note: li.querySelector( '.hd-checklist__note' ).textContent }; }, key );
const typeInto = async ( sel, value ) => {
	await page.fill( sel, value );
	await page.waitForTimeout( 50 );
};
const names = () => q( () => [ ...document.querySelectorAll( '[data-hd-rows] [name^="hod_related_links"]' ) ].map( ( i ) => `${ i.name }=${ i.value }` ) );
const openTab = async ( id ) => {
	await page.click( `#hvw-tab-${ id }` );
	await page.waitForTimeout( 50 );
};

console.log( '=== تب‌ها (Core)' );
let tabs = await q( () => ( { listShown: ! document.querySelector( '#hod-video-watch-meta .hd-tablist' ).hidden, selected: [ ...document.querySelectorAll( '#hod-video-watch-meta [role="tab"]' ) ].map( ( t ) => t.getAttribute( 'aria-selected' ) ), panels: [ ...document.querySelectorAll( '#hod-video-watch-meta [role="tabpanel"]' ) ].map( ( p ) => ! p.hidden ), count: document.querySelector( '[data-hvw-tab-count]' ).textContent } ) );
log( tabs.listShown && tabs.selected.join() === 'true,false,false' && tabs.panels.join() === 'true,false,false', 'نوار تب دیده می‌شود، فقط تب اول باز', JSON.stringify( tabs ) );
log( '۲' === tabs.count, 'شمارنده روی تب «زیر ویدیو» = تعداد کارت‌ها', tabs.count );
await page.focus( '#hvw-tab-video' );
await page.keyboard.press( 'ArrowLeft' );
tabs = await q( () => ( { focus: document.activeElement?.id, selected: [ ...document.querySelectorAll( '#hod-video-watch-meta [role="tab"]' ) ].map( ( t ) => t.getAttribute( 'aria-selected' ) ) } ) );
log( 'hvw-tab-time' === tabs.focus && tabs.selected.join() === 'false,true,false', 'راست‌به‌چپ: فلش چپ ← تب بعدی با فوکوس', JSON.stringify( tabs ) );
await page.keyboard.press( 'End' );
log( 'hvw-tab-below' === ( await q( () => document.activeElement?.id ) ), 'End ← آخرین تب' );
await page.keyboard.press( 'Home' );
log( 'hvw-tab-video' === ( await q( () => document.activeElement?.id ) ), 'Home ← اولین تب' );

console.log( '=== نوار وضعیت، چک‌لیست و پیش‌نمایش' );
let s = await summary();
log( s.shown && s.text.includes( 'ویدیو آماده نمایش است' ) && s.text.includes( '۲ لحظه کلیدی' ), 'جمله وضعیت از داده فعلی', s.text );
log( s.pill.includes( 'آماده' ) && s.pillCls.includes( 'hd-pill--ok' ), 'نشان: آماده نمایش', s.pill );
log( ( await check( 'url' ) ).state === 'ok' && ( await check( 'cover' ) ).state === 'ok' && ( await check( 'chapters' ) ).state === 'ok' && ( await check( 'related' ) ).state === 'ok', 'چک‌لیست: آدرس، کاور، بخش‌ها، کارت‌ها انجام شد' );
log( ( await check( 'related' ) ).note.includes( '۱ کارت عکس ندارد' ), 'کارت بدون عکس: یادداشت (هشدار نیست)', ( await check( 'related' ) ).note );
log( ( await check( 'duration' ) ).state === 'ok' && ( await check( 'transcript' ) ).state === 'ok', 'مدت و متن کامل انجام شد' );
log( await q( () => ! document.querySelector( '[data-hvw-pv]' ).hidden && ! document.querySelector( '[data-hvw-ck]' ).hidden && document.querySelector( '[data-hvw-pv-dur]' ).textContent === '1:10' && document.querySelector( '[data-hvw-pv-chips]' ).textContent.includes( '۲ لحظه کلیدی' ) ), 'پیش‌نمایش: مدت و نشان لحظه‌های کلیدی' );
log( ( await q( () => document.querySelector( '[data-hvw-url-status]' ).textContent ) ).includes( 'آدرس درست است' ), 'وضعیت آدرس ویدیو: درست' );

await typeInto( '#hod_video_url', '' );
s = await summary();
const u1 = await check( 'url' );
log( s.text.includes( 'هنوز آماده نمایش نیست' ) && u1.state === 'warn' && u1.tag === 'لازم' && u1.note.includes( 'بدون آدرس' ), 'آدرس خالی: «هنوز آماده نیست» + چک‌لیست «لازم»', s.text );
log( s.pill.includes( '۱ مورد برای بررسی' ) && s.pillCls.includes( 'hd-pill--warn' ), 'نشان: ۱ مورد برای بررسی', s.pill );
await typeInto( '#hod_video_url', 'ftp://x/a.mp4' );
log( ( await q( () => document.querySelector( '[data-hvw-url-status]' ).textContent ) ).includes( 'https://' ), 'آدرس بدون https: هشدار' );
await typeInto( '#hod_video_url', 'https://hodima.test/v/a.avi' );
log( ( await q( () => document.querySelector( '[data-hvw-url-status]' ).textContent ) ).includes( 'avi' ), 'پسوند avi: هشدار' );
await typeInto( '#hod_video_url', 'https://hodima.test/v/pin.mp4' );

console.log( '=== آنچه گوگل می‌بیند (بخش‌ها)' );
await openTab( 'time' );
await typeInto( '#hod_video_chapters', '0:00 شروع\n0:30 جنس\nبدون زمان\n2:00 بعد از پایان' );
const ch = await q( () => ( { items: [ ...document.querySelectorAll( '[data-hvw-chapters-list] li' ) ].map( ( li ) => li.textContent ), bad: document.querySelectorAll( '[data-hvw-chapters-list] li.is-bad' ).length, status: document.querySelector( '[data-hvw-chapters-status]' ).textContent } ) );
log( 3 === ch.items.length && ch.items[ 0 ].includes( 'تا 0:30' ) && ch.items[ 1 ].includes( 'تا 1:10' ), 'فهرست با پایان هر بخش (پایان آخری = مدت ویدیو)', ch.items.join( ' | ' ) );
log( 1 === ch.bad, 'بخش بعد از پایان ویدیو علامت می‌خورد (سرور نادیده‌اش می‌گیرد)' );
log( ch.status.includes( 'خط ۳ خوانده نشد' ), 'خط بدون زمان گزارش می‌شود', ch.status );
log( ( await check( 'chapters' ) ).state === 'warn' && ( await check( 'chapters' ) ).note.includes( 'نادیده گرفته می‌شود' ), 'چک‌لیست بخش‌ها: «بررسی کنید» + توضیح' );
await typeInto( '#hod_video_chapters', '0:00 شروع\n0:30 جنس' );

console.log( '=== متن کامل (جمع‌شونده) و دکمه محصول' );
log( ( await q( () => document.querySelector( '[data-hvw-transcript-count]' ).textContent ) ).includes( '۵ کلمه · ۲ پاراگراف' ), 'شمارش کلمه و پاراگراف روی عنوان جمع‌شونده' );
log( await q( () => document.querySelector( '.hd-more' ).open ), 'متن دارد ← جمع‌شونده باز است' );
await typeInto( '#hod_video_transcript', '' );
log( ( await check( 'transcript' ) ).state === 'opt', 'متن خالی ← اختیاری' );
await typeInto( '#hod_video_transcript', 'یک دو سه.\n\nچهار پنج.' );
await openTab( 'below' );
await page.click( '#hvw-product-switch' );
await page.waitForTimeout( 100 );
let prod = await q( () => ( { v: document.querySelector( '#hod_related_product_url' ).value, inert: document.querySelector( '.hd-opt__body' ).inert, btn: ! document.querySelector( '[data-hvw-pv-product]' ).hidden } ) );
log( '' === prod.v && prod.inert && ! prod.btn, 'کلید خاموش: لینک خالی (دکمه نمایش داده نمی‌شود)، بدنه غیرفعال، دکمه پیش‌نمایش پنهان', JSON.stringify( prod ) );
log( ( await check( 'product' ) ).state === 'opt', 'چک‌لیست: دکمه محصول اختیاری' );
await page.click( '#hvw-product-switch' );
await page.waitForTimeout( 100 );
prod = await q( () => ( { v: document.querySelector( '#hod_related_product_url' ).value, inert: document.querySelector( '.hd-opt__body' ).inert, btn: ! document.querySelector( '[data-hvw-pv-product]' ).hidden } ) );
log( 'https://hodima.test/product/x/' === prod.v && ! prod.inert && prod.btn, 'کلید روشن: همان لینک قبلی برگشت و دکمه در پیش‌نمایش', JSON.stringify( prod ) );

console.log( '=== کارت‌های مرتبط (لیست تکرارشونده Core)' );
await page.click( '[data-hd-add]' );
await page.waitForTimeout( 100 );
const added = await q( () => ( { count: document.querySelectorAll( '[data-hd-rows] > [data-hd-row]' ).length, focus: document.activeElement?.name, label: document.querySelector( `label[for="${ document.activeElement?.id }"]` )?.textContent.trim(), pill: document.querySelector( '[data-hd-count]' ).textContent, tab: document.querySelector( '[data-hvw-tab-count]' ).textContent } ) );
log( 3 === added.count && 'hod_related_links[2][title]' === added.focus && added.label === 'عنوان کارت ۳' && added.pill === '۳ کارت' && '۳' === added.tab, 'افزودن: کارت سوم، فوکوس روی عنوانش، برچسب وصل، شمارنده تب', JSON.stringify( added ) );
log( ( await check( 'related' ) ).state === 'warn' && ( await q( () => document.querySelector( '[data-hd-rows] > [data-hd-row]:nth-child(3)' ).classList.contains( 'is-warn' ) ) ), 'کارت ناقص: ردیف و چک‌لیست هشدار' );
await page.keyboard.type( 'سوم' );
await page.fill( '[name="hod_related_links[2][url]"]', 'https://hodima.test/c/' );
await page.click( '[data-hd-rows] > [data-hd-row]:nth-child(3) [data-hd-move="-1"]' );
await page.waitForTimeout( 50 );
let n = await names();
log( n.includes( 'hod_related_links[1][title]=سوم' ) && n.includes( 'hod_related_links[2][title]=دوم' ), 'جابه‌جایی بالا: نام فیلدها به ترتیب تازه', n.filter( ( x ) => x.includes( 'title' ) ).join( ' | ' ) );
log( ( await q( () => document.activeElement?.getAttribute( 'aria-label' ) ) ) === 'کارت ۲ یکی بالاتر', 'فوکوس روی همان دکمه با نام تازه' );

await page.click( '[data-hd-rows] > [data-hd-row]:nth-child(1) [data-hd-remove]' );
await page.waitForTimeout( 50 );
n = await names();
const undo = await q( () => ( { text: document.querySelector( '[data-hd-undo]' ).textContent, focus: document.activeElement?.textContent } ) );
log( n[ 0 ] === 'hod_related_links[0][title]=سوم' && n.length === 6, 'حذف کارت اول: بقیه از ۰ شماره‌گذاری شدند', n.filter( ( x ) => x.includes( 'title' ) ).join( ' | ' ) );
log( undo.text.includes( '«اول» حذف شد' ) && undo.focus === 'بازگردانی', 'پیام حذف با دکمه «بازگردانی» (فوکوس روی آن؛ بدون پنجره تأیید)', JSON.stringify( undo ) );
await page.keyboard.press( 'Enter' );
await page.waitForTimeout( 50 );
n = await names();
log( n[ 0 ] === 'hod_related_links[0][title]=اول' && n.length === 9 && n.includes( 'hod_related_links[0][cover]=https://hodima.test/c.jpg' ), 'بازگردانی: همان کارت با عکسش سر جای قبلی', n.filter( ( x ) => x.includes( 'title' ) ).join( ' | ' ) );

for ( let i = 0; i < 3; i++ ) {
	await page.click( '[data-hd-rows] > [data-hd-row]:nth-child(1) [data-hd-remove]' );
}
await page.waitForTimeout( 50 );
log( await q( () => ! document.querySelector( '[data-hd-empty]' ).hidden && document.querySelector( '[data-hd-count]' ).textContent === '۰ کارت' && document.querySelector( '[data-hvw-tab-count]' ).hidden ), 'همه حذف: پیام «هنوز کارتی اضافه نشده» و شمارنده تب پنهان' );
log( ( await check( 'related' ) ).state === 'opt', 'چک‌لیست: ویدیوهای مرتبط اختیاری' );

console.log( '=== کاور (انتخاب‌گر رسانه Core)' );
await openTab( 'video' );
await page.click( '[data-hd-media]:not([data-hd-row]) [data-hd-media-clear]' );
await page.waitForTimeout( 50 );
let cover = await q( () => ( { v: document.querySelector( '#hod_video_thumbnail' ).value, empty: document.querySelector( '[data-hd-media]:not([data-hd-row])' ).classList.contains( 'is-empty' ), focus: document.activeElement?.id, img: document.querySelector( '[data-hvw-pv-img]' ).hidden } ) );
log( '' === cover.v && cover.empty && 'hod_video_thumbnail' === cover.focus && cover.img, 'برداشتن کاور: فیلد خالی، فوکوس روی آن، پیش‌نمایش بدون عکس', JSON.stringify( cover ) );
const c1 = await check( 'cover' );
log( c1.state === 'warn' && c1.tag === 'لازم' && ( await summary() ).text.includes( 'کاور ندارد' ), 'چک‌لیست و جمله: کاور لازم است' );
await typeInto( '#hod_video_thumbnail', 'https://hodima.test/v/new.jpg' );
cover = await q( () => ( { src: document.querySelector( '[data-hd-media]:not([data-hd-row]) [data-hd-media-img]' ).getAttribute( 'src' ), pv: document.querySelector( '[data-hvw-pv-img]' ).getAttribute( 'src' ) } ) );
log( 'https://hodima.test/v/new.jpg' === cover.src && cover.src === cover.pv, 'آدرس تازه: پیش‌نمایش کاور و پیش‌نمایش سایت عوض شد' );

console.log( '=== موبایل ۳۹۰: بدون سرریز، ترتیب منطقی' );
await page.setViewportSize( { width: 390, height: 900 } );
await page.waitForTimeout( 150 );
const mob = await q( () => {
	const box = document.querySelector( '[data-hvw]' ).getBoundingClientRect();
	const out = [ ...document.querySelectorAll( '[data-hvw] *' ) ].filter( ( e ) => { const r = e.getBoundingClientRect(); return r.width > 0 && ( r.right > box.right + 1 || r.left < box.left - 1 ) && ! e.closest( '[hidden], template' ); } ).map( ( e ) => e.className || e.tagName );
	const y = ( sel ) => document.querySelector( sel ).getBoundingClientRect().top;
	return { out: out.slice( 0, 5 ), order: y( '[data-hvw-pv]' ) < y( '#hvw-panel-video' ) && y( '#hvw-panel-video' ) < y( '[data-hvw-ck]' ) };
} );
log( 0 === mob.out.length, 'هیچ عنصری از جعبه بیرون نمی‌زند', mob.out.join( ' | ' ) );
log( mob.order, 'ترتیب: پیش‌نمایش ← فرم ← چک‌لیست' );

log( 0 === errors.length, 'بدون خطای JS', errors.join( ' | ' ) );
await browser.close();
console.log( failures ? `\n✘ ${ failures } بررسی رد شد` : '\n✔ همه بررسی‌ها قبول' );
process.exit( failures ? 1 : 0 );
