/**
 * آزمون رفتار کادر «تنظیمات ویدیو» در ویرایش ویدیو (پیشخوان؛ meta-boxes.php + video-watch.admin.js) در Chromium.
 *   node tools/wp-harness/video-watch-admin.mjs [root]
 * کادر را با PHP همین کد (admin-render-screen.php، حساب مدیر) برای ویدیوی آزمایشی می‌سازد
 * و خلاصه زنده، وضعیت آدرس، «آنچه گوگل می‌بیند»، کلید دکمه محصول و کارت‌های مرتبط
 * (افزودن، جابه‌جایی، حذف و بازگردانی، نام فیلدها) را می‌سنجد. ظاهر و axe: admin-shot.mjs.
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
	.replace( /<html([^>]*) dir="ltr"/, '<html$1 dir="rtl"' );
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
const summary = () => q( () => ( { shown: ! document.querySelector( '[data-hvw-summary]' ).hidden, text: document.querySelector( '[data-hvw-summary-text]' ).textContent, warns: [ ...document.querySelectorAll( '[data-hvw-summary-warn] .hd-pill' ) ].map( ( p ) => p.textContent ) } ) );
const typeInto = async ( sel, value ) => {
	await page.fill( sel, value );
	await page.waitForTimeout( 50 );
};
const names = () => q( () => [ ...document.querySelectorAll( '[data-hvw-rows] [name^="hod_related_links"]' ) ].map( ( i ) => `${ i.name }=${ i.value }` ) );

console.log( '=== خلاصه زنده' );
let s = await summary();
log( s.shown && s.text.includes( 'ویدیو آماده نمایش است' ) && s.text.includes( '۲ لحظه کلیدی' ) && s.text.includes( '۲ کارت مرتبط' ), 'جمله خلاصه از داده فعلی', s.text );
log( s.warns.some( ( w ) => w.includes( 'عکس ندارد' ) ), 'هشدار کارت بدون عکس', s.warns.join( ' | ' ) );
log( ( await q( () => document.querySelector( '[data-hvw-url-status]' ).textContent ) ).includes( 'آدرس درست است' ), 'وضعیت آدرس ویدیو: درست' );

await typeInto( '#hod_video_url', '' );
s = await summary();
log( s.text.includes( 'هنوز آماده نمایش نیست' ) && s.warns.some( ( w ) => w.includes( 'بدون آدرس' ) ), 'آدرس خالی: «هنوز آماده نمایش نیست» + هشدار', s.warns.join( ' | ' ) );
await typeInto( '#hod_video_url', 'ftp://x/a.mp4' );
log( ( await q( () => document.querySelector( '[data-hvw-url-status]' ).textContent ) ).includes( 'https://' ), 'آدرس بدون https: هشدار' );
await typeInto( '#hod_video_url', 'https://hodima.test/v/a.avi' );
log( ( await q( () => document.querySelector( '[data-hvw-url-status]' ).textContent ) ).includes( 'avi' ), 'پسوند avi: هشدار' );
await typeInto( '#hod_video_url', 'https://hodima.test/v/pin.mp4' );

console.log( '=== آنچه گوگل می‌بیند (بخش‌ها)' );
await typeInto( '#hod_video_chapters', '0:00 شروع\n0:30 جنس\nبدون زمان\n2:00 بعد از پایان' );
const ch = await q( () => ( { items: [ ...document.querySelectorAll( '[data-hvw-chapters-list] li' ) ].map( ( li ) => li.textContent ), bad: document.querySelectorAll( '[data-hvw-chapters-list] li.is-bad' ).length, status: document.querySelector( '[data-hvw-chapters-status]' ).textContent } ) );
log( 3 === ch.items.length && ch.items[ 0 ].includes( 'تا 0:30' ) && ch.items[ 1 ].includes( 'تا 1:10' ), 'فهرست با پایان هر بخش (پایان آخری = مدت ویدیو)', ch.items.join( ' | ' ) );
log( 1 === ch.bad, 'بخش بعد از پایان ویدیو علامت می‌خورد (سرور نادیده‌اش می‌گیرد)' );
log( ch.status.includes( 'خط ۳ خوانده نشد' ), 'خط بدون زمان گزارش می‌شود', ch.status );
log( ( await summary() ).warns.some( ( w ) => w.includes( 'نادیده گرفته می‌شود' ) ), 'هشدار در خلاصه' );
await typeInto( '#hod_video_chapters', '0:00 شروع\n0:30 جنس' );

console.log( '=== متن کامل و دکمه محصول' );
log( ( await q( () => document.querySelector( '[data-hvw-transcript-count]' ).textContent ) ) === '۵ کلمه · ۲ پاراگراف', 'شمارش کلمه و پاراگراف' );
await page.click( '#hvw-product-switch' );
await page.waitForTimeout( 100 );
let prod = await q( () => ( { v: document.querySelector( '#hod_related_product_url' ).value, inert: document.querySelector( '.hd-opt__body' ).inert } ) );
log( '' === prod.v && prod.inert, 'کلید خاموش: لینک خالی (دکمه نمایش داده نمی‌شود) و بدنه غیرفعال', JSON.stringify( prod ) );
log( ( await summary() ).text.includes( 'نمایش داده نمی‌شود' ), 'خلاصه: دکمه محصول نمایش داده نمی‌شود' );
await page.click( '#hvw-product-switch' );
await page.waitForTimeout( 100 );
prod = await q( () => ( { v: document.querySelector( '#hod_related_product_url' ).value, inert: document.querySelector( '.hd-opt__body' ).inert } ) );
log( 'https://hodima.test/product/x/' === prod.v && ! prod.inert, 'کلید روشن: همان لینک قبلی برگشت', JSON.stringify( prod ) );

console.log( '=== کارت‌های مرتبط' );
await page.click( '[data-hvw-add]' );
await page.waitForTimeout( 100 );
const added = await q( () => ( { count: document.querySelectorAll( '[data-hvw-rows] > [data-hvw-row]' ).length, focus: document.activeElement?.name, label: document.querySelector( `label[for="${ document.activeElement?.id }"]` )?.textContent.trim(), pill: document.querySelector( '[data-hvw-related-count]' ).textContent } ) );
log( 3 === added.count && 'hod_related_links[2][title]' === added.focus && added.label === 'عنوان کارت ۳' && added.pill === '۳ کارت', 'افزودن: کارت سوم، فوکوس روی عنوانش، برچسب وصل', JSON.stringify( added ) );
await page.keyboard.type( 'سوم' );
await page.fill( '[name="hod_related_links[2][url]"]', 'https://hodima.test/c/' );
await page.click( '[data-hvw-rows] > [data-hvw-row]:nth-child(3) [data-hvw-move="-1"]' );
await page.waitForTimeout( 50 );
let n = await names();
log( n.includes( 'hod_related_links[1][title]=سوم' ) && n.includes( 'hod_related_links[2][title]=دوم' ), 'جابه‌جایی بالا: نام فیلدها به ترتیب تازه', n.filter( ( x ) => x.includes( 'title' ) ).join( ' | ' ) );
log( ( await q( () => document.activeElement?.getAttribute( 'aria-label' ) ) ) === 'کارت ۲ یکی بالاتر', 'فوکوس روی همان دکمه با نام تازه' );

await page.click( '[data-hvw-rows] > [data-hvw-row]:nth-child(1) [data-hvw-remove]' );
await page.waitForTimeout( 50 );
n = await names();
const undo = await q( () => ( { text: document.querySelector( '[data-hvw-undo]' ).textContent, focus: document.activeElement?.textContent } ) );
log( n[ 0 ] === 'hod_related_links[0][title]=سوم' && n.length === 6, 'حذف کارت اول: بقیه از ۰ شماره‌گذاری شدند', n.filter( ( x ) => x.includes( 'title' ) ).join( ' | ' ) );
log( undo.text.includes( '«اول» حذف شد' ) && undo.focus === 'بازگردانی', 'پیام حذف با دکمه «بازگردانی» (فوکوس روی آن؛ بدون پنجره تأیید)', JSON.stringify( undo ) );
await page.keyboard.press( 'Enter' );
await page.waitForTimeout( 50 );
n = await names();
log( n[ 0 ] === 'hod_related_links[0][title]=اول' && n.length === 9 && n.includes( 'hod_related_links[0][cover]=https://hodima.test/c.jpg' ), 'بازگردانی: همان کارت با عکسش سر جای قبلی', n.filter( ( x ) => x.includes( 'title' ) ).join( ' | ' ) );

for ( let i = 0; i < 3; i++ ) {
	await page.click( '[data-hvw-rows] > [data-hvw-row]:nth-child(1) [data-hvw-remove]' );
}
await page.waitForTimeout( 50 );
log( await q( () => ! document.querySelector( '[data-hvw-empty]' ).hidden && document.querySelector( '[data-hvw-related-count]' ).textContent === '۰ کارت' ), 'همه حذف: پیام «هنوز کارتی اضافه نشده»' );
log( ( await summary() ).text.includes( 'کارت مرتبطی زیر ویدیو نیست' ), 'خلاصه: کارت مرتبطی نیست' );

console.log( '=== کاور' );
await page.click( '[data-hvw-clear]' );
await page.waitForTimeout( 50 );
let cover = await q( () => ( { v: document.querySelector( '#hod_video_thumbnail' ).value, empty: document.querySelector( '[data-hvw-cover]' ).classList.contains( 'is-empty' ), focus: document.activeElement?.id } ) );
log( '' === cover.v && cover.empty && 'hod_video_thumbnail' === cover.focus, 'برداشتن کاور: فیلد خالی و فوکوس روی آن', JSON.stringify( cover ) );
log( ( await summary() ).warns.some( ( w ) => w.includes( 'بدون کاور' ) ), 'هشدار بدون کاور در خلاصه' );
await typeInto( '#hod_video_thumbnail', 'https://hodima.test/v/new.jpg' );
cover = await q( () => document.querySelector( '[data-hvw-cover] [data-hvw-thumb]' ).getAttribute( 'src' ) );
log( 'https://hodima.test/v/new.jpg' === cover, 'آدرس تازه: پیش‌نمایش عوض شد' );

log( 0 === errors.length, 'بدون خطای JS', errors.join( ' | ' ) );
await browser.close();
console.log( failures ? `\n✘ ${ failures } بررسی رد شد` : '\n✔ همه بررسی‌ها قبول' );
process.exit( failures ? 1 : 0 );
