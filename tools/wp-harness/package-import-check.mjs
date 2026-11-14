/**
 * آزمون «ورود بسته» Hodima SEO (core/package-import) در Chromium واقعی با ورود مدیر، ووکامرس واقعی و
 * اسکریپت‌های واقعی پیشخوان (TinyMCE، select2). اجرا: tools/wp-harness/package-import-check.sh
 *
 * می‌سنجد: کادر بسته (۵۶px) بالای فرم؛ بسته fixtures/package-103.json ← «بررسی» ← ردیف هر کادر و هشدارها؛
 * قفل آدرس (بسته صفحه دیگر: هیچ دکمه پر کردنی نیست و هیچ خانه‌ای عوض نمی‌شود)؛ «پر کردن فرم» همه کادرها؛
 * «بازگرداندن» دقیقا مقدارهای قبلی؛ پر کردن دوباره و «به‌روزرسانی» ← متاهای ذخیره‌شده (کد ذخیره خود هر کادر)؛
 * هیچ نوشتنی پیش از «به‌روزرسانی»؛ axe روی کادر؛ عکس ۱۰۰۰ و ۳۹۰ هر حالت. خروجی ✔/✘؛ کد خروج ۱ اگر یکی رد شود.
 * عکس‌ها: $HODIMA_HARNESS/package-import.
 */
import fs from 'node:fs';
import path from 'node:path';
import { setup, here, site, box } from './package-import-lib.mjs';

const { log, php, phpJson, newContext, shot, axe, openAndCheck, close } = await setup();
const pkgText = fs.readFileSync( path.join( here, 'fixtures/package-103.json' ), 'utf8' );

/* ---------- داده آزمایشی: مدیر، دسته پیلار، مقاله، مقدار قبلی کادرها ---------- */
php( `
if ( ! username_exists( 'pkgadmin' ) ) { wp_insert_user( [ 'user_login' => 'pkgadmin', 'user_pass' => 'pkg-pass-123', 'role' => 'administrator' ] ); }
update_term_meta( 22, '_hodima_is_pillar', '1' );
delete_post_meta( 23, '_edit_lock' );
delete_transient( '_wc_activation_redirect' ); // نصب تازه ووکامرس اولین صفحه پیشخوان را به راه‌انداز می‌برد
update_post_meta( 23, '_crosssell_ids', [ 28 ] );
update_post_meta( 23, '_upsell_ids', [ 28 ] );
update_post_meta( 23, '_seobox_title', 'عنوان قبلی' );
update_post_meta( 23, '_h_ai_en_entity', 'Old entity' );
update_post_meta( 23, '_h_ai_fa_price_t1', '550000' );
` );
const PRODUCT = 23;
const editUrl = `${ site }/wp-admin/post.php?post=${ PRODUCT }&action=edit`;
let before0 = null;

/** مقدار فعلی همه خانه‌های هدف در فرم (برای مقایسه پیش و پس از پر کردن/بازگرداندن). */
const formState = ( page ) => page.evaluate( () => {
	const v = ( sel ) => document.querySelector( sel )?.value ?? null;
	const ed = window.tinymce?.get( 'content' );
	return {
		content: ed && ! ed.isHidden() ? ed.getContent() : v( '#content' ),
		upsell: [ ...document.querySelectorAll( '#upsell_ids option:checked' ) ].map( ( o ) => o.value ),
		cross: [ ...document.querySelectorAll( '#crosssell_ids option:checked' ) ].map( ( o ) => o.value ),
		table: v( 'input[name="hodima_table_json"]' ),
		faq: [ ...document.querySelectorAll( '[data-hodima-faqs] > [data-hodima-faq]' ) ].map( ( r ) => [ r.querySelector( 'input' ).value, r.querySelector( 'textarea' ).value ] ),
		rl: [ ...document.querySelectorAll( '.hodima-rl-slot' ) ].map( ( s ) => [ s.querySelector( '[data-field="kind"]' ).value, s.querySelector( '[data-field="id"]' ).value ] ),
		tc: [ document.querySelector( 'input[name="hodima_is_pillar"]' )?.checked, [ ...document.querySelectorAll( 'select[name="hodima_pillar_id[]"] option:checked' ) ].map( ( o ) => o.value ) ],
		aeo: [ v( '[name="h_ai_en_entity"]' ), v( '[name="h_ai_fa_price_t1"]' ), v( '[name="h_ai_fa_price_basis"]' ), document.querySelector( '[name="h_ai_biz_model"]:checked' )?.value, v( '#h_ai_en_specs_input' ), v( '#h_ai_faqs_input' ), document.querySelectorAll( '#h_ai_fa_faq_container > div' ).length, v( '[name="h_ai_en_prompts"]' ) ],
		seo: [ v( '#seobox_title' ), v( '#seobox_description' ) ],
	};
} );

const savedMeta = () => phpJson( `
	clean_post_cache( ${ PRODUCT } );
	$t = class_exists( 'Hodima_Dynamic_Table' ) ? Hodima_Dynamic_Table::get_table( ${ PRODUCT }, Hodima_Table_Context::Post ) : null;
	$m = function_exists( 'hodima_media_get_data' ) ? hodima_media_get_data( ${ PRODUCT }, 'post', true ) : [];
	$rl = class_exists( '\\\\Hodima\\\\RelatedLinks\\\\Store' ) ? \\Hodima\\RelatedLinks\\Store::get( ${ PRODUCT }, 'post' ) : null;
	return [
		'content'  => get_post_field( 'post_content', ${ PRODUCT } ),
		'upsell'   => array_values( array_filter( array_map( 'intval', (array) get_post_meta( ${ PRODUCT }, '_upsell_ids', true ) ) ) ),
		'cross'    => array_values( array_filter( array_map( 'intval', (array) get_post_meta( ${ PRODUCT }, '_crosssell_ids', true ) ) ) ),
		'table'    => $t,
		'faq'      => $m['faq'] ?? null,
		'rl'       => $rl ? array_map( static fn( $g ) => array_map( static fn( $i ) => $i['id'], $g ), $rl ) : null,
		'pillar'   => get_post_meta( ${ PRODUCT }, '_hodima_is_pillar', true ),
		'parents'  => get_post_meta( ${ PRODUCT }, '_hodima_pillar_id' ),
		'aeo'      => [ get_post_meta( ${ PRODUCT }, '_h_ai_en_entity', true ), get_post_meta( ${ PRODUCT }, '_h_ai_fa_price_t1', true ), get_post_meta( ${ PRODUCT }, '_h_ai_fa_price_basis', true ), get_post_meta( ${ PRODUCT }, '_h_ai_biz_model', true ), get_post_meta( ${ PRODUCT }, '_h_ai_en_specs', true ), get_post_meta( ${ PRODUCT }, '_h_ai_faqs', true ), get_post_meta( ${ PRODUCT }, '_h_ai_en_prompts', true ) ],
		'seo'      => [ get_post_meta( ${ PRODUCT }, '_seobox_title', true ), get_post_meta( ${ PRODUCT }, '_seobox_description', true ) ],
		'modified' => get_post_field( 'post_modified_gmt', ${ PRODUCT } ),
	];
` );

/* ================= بررسی سمت سرور (بدون مرورگر) ================= */
console.log( 'ورود بسته — بررسی سرور' );
before0 = savedMeta();
const check = ( json ) => phpJson( `wp_set_current_user( get_user_by( 'login', 'pkgadmin' )->ID ); return hodima_seo_pkg_check( ${ JSON.stringify( json ).replace( /\$/g, '\\$' ) }, get_post( ${ PRODUCT } ) );` );
const pkg = JSON.parse( pkgText );
const variant = ( patch ) => JSON.stringify( { ...pkg, ...patch } );
let r = check( '{"format": ' );
log( ! r.ok && /JSON/.test( r.errors[ 0 ] ), 'JSON خراب رد شد', r.errors[ 0 ] );
r = check( variant( { format: 'hodima-package/9' } ) );
log( ! r.ok && /قالب/.test( r.errors.join() ), 'قالب ناشناخته رد شد' );
r = check( variant( { format: 'hodima-package/2' } ) );
log( r.ok, 'محصول با قالب نسخه ۲ هم پذیرفته می‌شود', r.errors.join( ' ' ) );
r = check( variant( { type: 'post' } ) );
log( ! r.ok && /نوع/.test( r.errors.join() ), 'نوع اشتباه رد شد' );
r = check( variant( { url: 'http://www.hodima.test/103' } ) );
log( r.ok && r.lock.match, 'قفل آدرس: http، www و بی‌اسلش همان آدرس است' );
r = check( variant( { url: 'https://hodima.test/1030/' } ) );
log( ! r.ok && r.lock.match === false && ! Object.keys( r.boxes ).length, 'قفل آدرس: آدرس دیگر ← هیچ کادری برنگشت' );
r = check( variant( { boxes: { body: { html: '<a href="tel:+989124093140">a</a><a href="tel:۰۲۱۷۷۳۲۲۶۸۴">b</a><h1>x</h1>' } } } ) );
log( r.ok && ! r.boxes.body.warnings.some( ( w ) => /tel/.test( w ) ) && r.boxes.body.warnings.some( ( w ) => /H1/.test( w ) ), 'شماره‌های سایت (+98 و ارقام فارسی) هشدار نمی‌گیرند؛ H1 می‌گیرد', JSON.stringify( r.boxes?.body?.warnings ) );
r = check( variant( { boxes: { table: [], faq: [], aeo: { biz_model: 'x', tier_a: { price: '۵۵۰٬۰۰۰', fa_basis: 'تن' } } } } ) );
log( r.ok && r.boxes.table.value.rows.length === 0 && r.boxes.faq.value.length === 0, '[] یعنی خالی کردن کادر' );
log( r.boxes?.aeo?.value?.h_ai_biz_model === 'both' && r.boxes.aeo.value.h_ai_fa_price_t1 === '550000' && r.boxes.aeo.value.h_ai_fa_price_basis === 'کیلوگرم' && r.boxes.aeo.warnings.length === 2, 'AEO: پلن و مبنای نامعتبر با هشدار به پیش‌فرض، قیمت فارسی به عدد', JSON.stringify( r.boxes?.aeo?.warnings ) );
log( JSON.stringify( savedMeta() ) === JSON.stringify( before0 ), 'بررسی سرور هیچ متایی را عوض نکرد' );
const onPage = php( `wp_set_current_user( get_user_by( 'login', 'pkgadmin' )->ID ); ob_start(); hodima_seo_pkg_render_box( get_post( 2 ) ); echo strlen( ob_get_clean() );` );
log( onPage === '0', 'کادر روی ویرایش برگه (نوعی که بسته ندارد) نیست' );

/* ================= دسکتاپ ۱۰۰۰ ================= */
console.log( 'ورود بسته — دسکتاپ ۱۰۰۰' );
const before = savedMeta();
const page = await newContext( 1000 );
await page.goto( `${ site }/wp-admin/`, { waitUntil: 'load', timeout: 60000 } ); // هر ریدایرکت یک‌باره نصب تازه همین‌جا مصرف شود
await page.goto( editUrl, { waitUntil: 'load', timeout: 60000 } );
await page.waitForTimeout( 1200 );

const closed = await page.evaluate( ( sel ) => {
	const el = document.querySelector( sel );
	const r = el?.getBoundingClientRect();
	const title = document.getElementById( 'titlediv' )?.getBoundingClientRect();
	return el ? { visible: ! el.hidden, h: Math.round( r.height ), aboveTitle: r.bottom <= title.top + 1 } : null;
}, box );
log( !! closed?.visible, 'کادر روی ویرایش محصول دیده می‌شود' );
log( closed?.aboveTitle, 'کادر بالای عنوان است' );
log( closed && closed.h <= 90, 'حالت بسته کوتاه است', `${ closed?.h }px` );
await shot( page, 'closed-1000' );
const formBefore = await formState( page );

// صفحه‌کلید: باز با Enter، بسته با Space، فوکوس دیدنی
await page.focus( '[data-pkg-toggle]' );
await page.keyboard.press( 'Enter' );
const kbOpen = await page.evaluate( () => ! document.getElementById( 'hodima-pkg-body' ).hidden && document.activeElement?.matches( '[data-pkg-json]' ) );
await page.focus( '[data-pkg-toggle]' );
const ring = await page.evaluate( () => getComputedStyle( document.activeElement ).outlineStyle );
await page.keyboard.press( 'Space' );
const kbClosed = await page.evaluate( () => document.getElementById( 'hodima-pkg-body' ).hidden );
log( kbOpen && kbClosed, 'صفحه‌کلید: باز (Enter، فوکوس به خانه متن) و بسته (Space)' );
log( ring !== 'none', 'فوکوس دکمه دیدنی است', ring );

// قفل آدرس: بسته صفحه دیگر
await openAndCheck( page, pkgText.replace( 'https://hodima.test/103/', 'https://hodima.test/kesh-rangi/' ) );
const lockErr = await page.evaluate( () => ( {
	fillHidden: document.querySelector( '[data-pkg-fillbar]' ).hidden,
	lock: document.querySelector( '[data-pkg-lock]' ).dataset.state,
	pill: document.querySelector( '[data-pkg-pill]' ).textContent,
} ) );
log( lockErr.fillHidden && lockErr.lock === 'error', 'قفل آدرس: بسته صفحه دیگر رد شد و دکمه پر کردن نیست', JSON.stringify( lockErr ) );
await shot( page, 'error-1000' );
log( JSON.stringify( await formState( page ) ) === JSON.stringify( formBefore ), 'قفل آدرس: هیچ خانه‌ای عوض نشد' );

// بسته درست
await page.click( '[data-pkg-edit]' );
await page.fill( '[data-pkg-json]', pkgText );
await page.click( '[data-pkg-check]' );
await page.waitForFunction( () => ! document.querySelector( '[data-pkg-review]' ).hidden, null, { timeout: 30000 } );
const review = await page.evaluate( () => ( {
	rows: [ ...document.querySelectorAll( '.hodima-pkg__row' ) ].map( ( r ) => `${ r.querySelector( '.hodima-pkg__name' ).textContent }=${ r.dataset.state }` ),
	warns: [ ...document.querySelectorAll( '.hodima-pkg__warns li' ) ].map( ( l ) => l.textContent ),
	ignored: [ ...document.querySelectorAll( '[data-pkg-ignored] li' ) ].map( ( l ) => l.textContent ),
	fill: ! document.querySelector( '[data-pkg-fillbar]' ).hidden,
} ) );
console.log( `    ردیف‌ها: ${ review.rows.join( ' | ' ) }` );
log( review.rows.length === 9, 'نه کادر نسخه ۱ در پیش‌نمایش', String( review.rows.length ) );
log( review.fill, 'دکمه «پر کردن فرم» آماده است' );
const has = ( re ) => review.warns.some( ( w ) => re.test( w ) );
log( has( /tel:02100000000/ ), 'هشدار شماره tel: غیر سایت' );
log( has( /H4/ ), 'هشدار سرتیتر H4' );
log( has( /999/ ), 'هشدار آدرس پیدانشده (فقط همان مورد)' );
log( has( /parent_post_url/ ), 'هشدار parent_post_url برای محصول' );
log( has( /sample-1|۳|خانه دارد/ ), 'هشدار لینک‌های مرتبط بیشتر از خانه‌ها' );
log( review.ignored.some( ( t ) => /دیسکاور/.test( t ) ), 'کلید discover نادیده و گزارش شد' );
log( JSON.stringify( savedMeta() ) === JSON.stringify( before ), 'بررسی چیزی ذخیره نکرد' );
await page.click( '.hodima-pkg__row[data-state] .hodima-pkg__row-head:not([disabled]) >> nth=-1' );
await shot( page, 'review-1000' );
await axe( page, 'بررسی‌شده ۱۰۰۰' );

// پر کردن
await page.click( '[data-pkg-fill]' );
await page.waitForTimeout( 500 );
const filled = await formState( page );
const doneRows = await page.evaluate( () => [ ...document.querySelectorAll( '.hodima-pkg__row' ) ].map( ( r ) => r.dataset.state ) );
log( ! doneRows.includes( 'failed' ), 'همه کادرها پر شدند (هیچ «پر نشد»)', doneRows.join( ',' ) );
log( /PKG-BODY-MARKER/.test( filled.content ), 'متن اصلی (TinyMCE) پر شد' );
log( JSON.stringify( filled.upsell ) === '["24","27"]', 'upsell پر شد', JSON.stringify( filled.upsell ) );
log( filled.cross.length === 0, 'cross-sell خالی شد ([])' );
log( /مهمانی و روزمره/.test( filled.table || '' ), 'جدول تکمیلی پر شد' );
log( filled.faq.length === 2 && /حداقل سفارش/.test( filled.faq[ 0 ][ 0 ] ), 'پرسش‌های رسانه پر شد' );
log( filled.rl.filter( ( s ) => s[ 0 ] ).length >= 3, 'لینک‌های مرتبط پر شد', JSON.stringify( filled.rl ) );
log( JSON.stringify( filled.tc ) === '[false,["22"]]', 'خوشه‌بندی: والد دسته پیلار', JSON.stringify( filled.tc ) );
log( filled.aeo[ 0 ] === 'Metal Hair Claw Clip 103' && filled.aeo[ 1 ] === '' && filled.aeo[ 2 ] === 'عدد' && filled.aeo[ 3 ] === 'both', 'پنل AEO: متن، قیمت null=خالی، مبنای «عدد»، پلن', JSON.stringify( filled.aeo.slice( 0, 4 ) ) );
log( filled.aeo[ 6 ] === 1 && /Material/.test( filled.aeo[ 4 ] ), 'پنل AEO: فهرست‌های پرسش و مشخصات از نو کشیده شد' );
log( filled.seo[ 0 ] === 'خرید عمده کلیپس فلزی طرح ۱۰۳ | هدهدلی', 'سئوباکس پر شد' );
log( JSON.stringify( savedMeta() ) === JSON.stringify( before ), 'پر کردن چیزی ذخیره نکرد' );
await shot( page, 'filled-1000' );
await axe( page, 'پر شد ۱۰۰۰' );

// بازگرداندن
await page.click( '[data-pkg-undo]' );
await page.waitForTimeout( 400 );
const undone = await formState( page );
const diff = Object.keys( formBefore ).filter( ( k ) => k !== 'content' && JSON.stringify( formBefore[ k ] ) !== JSON.stringify( undone[ k ] ) );
log( ! diff.length, '«بازگرداندن» همه خانه‌ها را به قبل برگرداند', diff.map( ( k ) => `${ k }: ${ JSON.stringify( formBefore[ k ] ) } ≠ ${ JSON.stringify( undone[ k ] ) }` ).join( ' ؛ ' ) );
log( undone.content.replace( /\s+/g, '' ) === formBefore.content.replace( /\s+/g, '' ), '«بازگرداندن» متن اصلی را برگرداند' );

// پر کردن دوباره و «به‌روزرسانی»
await page.click( '[data-pkg-fill]' );
await page.waitForTimeout( 400 );
page.on( 'dialog', ( d ) => d.accept() );
await Promise.all( [ page.waitForURL( /message=1|post\.php\?post=/, { timeout: 60000 } ), page.click( '#publish' ) ] );
await page.waitForLoadState( 'load' );
const after = savedMeta();
log( /PKG-BODY-MARKER/.test( after.content ) && /\[hodima_table\]/.test( after.content ), 'ذخیره: متن با شورت‌کدها' );
log( JSON.stringify( after.upsell ) === '[24,27]', 'ذخیره: upsell', JSON.stringify( after.upsell ) );
log( after.cross.length === 0, 'ذخیره: cross-sell خالی', JSON.stringify( after.cross ) );
log( after.table?.rows?.length === 3 && after.table.headers[ 1 ] === 'توضیحات', 'ذخیره: جدول تکمیلی', JSON.stringify( after.table ) );
log( after.faq?.length === 2, 'ذخیره: پرسش‌های رسانه', JSON.stringify( after.faq ) );
log( JSON.stringify( after.rl?.products ) === '[24,28]' && JSON.stringify( after.rl?.article ) === '[1]', 'ذخیره: لینک‌های مرتبط (۲ خانه خرید + ۱ مقاله)', JSON.stringify( after.rl ) );
log( JSON.stringify( after.parents ).includes( '22' ), 'ذخیره: والد خوشه', JSON.stringify( after.parents ) );
log( after.aeo[ 0 ] === 'Metal Hair Claw Clip 103' && after.aeo[ 1 ] === '' && after.aeo[ 2 ] === 'عدد' && after.aeo[ 3 ] === 'both' && /Material/.test( after.aeo[ 4 ] ) && after.aeo[ 6 ].split( '\n' ).length === 2, 'ذخیره: پنل AEO', JSON.stringify( after.aeo ) );
log( after.seo[ 0 ] === 'خرید عمده کلیپس فلزی طرح ۱۰۳ | هدهدلی' && after.seo[ 1 ] !== '', 'ذخیره: سئوباکس', JSON.stringify( after.seo ) );
log( ! page.errors.length, 'بدون خطای جاوااسکریپت هدیما', page.errors.join( ' ; ' ) );

// خروجی .md و Offer پنل AEO با مبنای «عدد / Piece» (پلن A) و «کارت / Card» (پلن B)
const md = phpJson( `
	return [
		'fa' => class_exists( 'Hodima_AEO_Generator' ) ? Hodima_AEO_Generator::generate_markdown( ${ PRODUCT }, 'post', 'fa' ) : '',
		'en' => class_exists( 'Hodima_AEO_Generator' ) ? Hodima_AEO_Generator::generate_markdown( ${ PRODUCT }, 'post', 'en' ) : '',
		'basis_t2' => get_post_meta( ${ PRODUCT }, '_h_ai_fa_price_basis_t2', true ),
	];
` );
log( after.aeo[ 2 ] === 'عدد' && md.basis_t2 === 'کارت', 'ذخیره: مبنای پلن A «عدد» و پلن B «کارت»', `${ after.aeo[ 2 ] } / ${ md.basis_t2 }` );
log( /به ازای هر ۱ عدد/.test( md.fa ) && /به ازای هر ۱ کارت/.test( md.fa ), '.md فارسی: «به ازای هر ۱ عدد» و «… ۱ کارت»' );
log( /"unitText":\s*"عدد"/.test( md.fa ) && /"unitText":\s*"کارت"/.test( md.fa ), 'Offer فارسی: unitText «عدد» و «کارت»' );
log( /per 1 Piece/.test( md.en ) && /per 1 Card/.test( md.en ) && /"unitText":\s*"Piece"/.test( md.en ) && /"unitText":\s*"Card"/.test( md.en ), '.md و Offer انگلیسی: Piece و Card' );
if ( ! /به ازای هر ۱ عدد/.test( md.fa ) ) console.log( md.fa.split( '\n' ).filter( ( l ) => /مبنا|unitText/.test( l ) ).join( '\n' ) );

// نسخه ?ver= اسکریپت کادرهای گیرنده «ورود بسته» با هر تغییر فایل عوض شود (کش مرورگر؛ SEO 4.0.1)
const vers = await page.evaluate( () => Object.fromEntries( [ 'hodima-rl-admin-js', 'hodima-tc-admin-js-js', 'hodima-table-admin-js-js', 'hodima-media-admin-js', 'hodima-seo-pkg-js' ].map( ( id ) => [ id, new URL( document.getElementById( id )?.src || 'https://x/?ver=' ).searchParams.get( 'ver' ) ] ) ) );
log( Object.values( vers ).every( ( v ) => /\d{9,}$/.test( v || '' ) ), 'نسخه اسکریپت همه کادرهای گیرنده زمان تغییر فایل را دارد', JSON.stringify( vers ) );

// همان بسته بعد از ذخیره: کادرها «بدون تغییر» (جز متن که وردپرس شکلش را عوض می‌کند)
await openAndCheck( page, pkgText );
const again = await page.evaluate( () => [ ...document.querySelectorAll( '.hodima-pkg__row' ) ].map( ( r ) => `${ r.querySelector( '.hodima-pkg__name' ).textContent }=${ r.dataset.state }` ) );
console.log( `    دوباره: ${ again.join( ' | ' ) }` );
log( again.filter( ( r ) => /=change|=empty/.test( r ) ).length <= 2, 'بعد از ذخیره، همان بسته تقریبا «بدون تغییر» است' );
await page.context().close();

/* ================= موبایل ۳۹۰ ================= */
console.log( 'ورود بسته — موبایل ۳۹۰' );
const m = await newContext( 390 );
await m.goto( editUrl, { waitUntil: 'load', timeout: 60000 } );
await m.waitForTimeout( 1200 );
await shot( m, 'closed-390' );
// دسکتاپ همین بسته را ذخیره کرده؛ برای موبایل دو کادر عوض شوند تا «پر کردن» چیزی داشته باشد
const mobilePkg = pkgText.replace( '| هدهدلی', '| هدهدلی (موبایل)' ).replace( '"pillar": false', '"pillar": true' );
await openAndCheck( m, mobilePkg );
await m.waitForFunction( () => ! document.querySelector( '[data-pkg-review]' ).hidden );
const overflow = await m.evaluate( ( sel ) => {
	const r = document.querySelector( sel ).getBoundingClientRect();
	const small = [ ...document.querySelectorAll( `${ sel } button:not([hidden])` ) ].filter( ( b ) => b.offsetParent && b.getBoundingClientRect().height < 40 ).map( ( b ) => b.textContent.trim().slice( 0, 20 ) );
	return { right: Math.round( r.right ), width: innerWidth, small, scroll: document.documentElement.scrollWidth };
}, box );
log( overflow.right <= overflow.width, 'موبایل: کادر از صفحه بیرون نمی‌زند', JSON.stringify( overflow ) );
log( ! overflow.small.length, 'موبایل: دکمه‌ها ≥ ۴۰px', overflow.small.join( '، ' ) );
await shot( m, 'review-390' );
await axe( m, 'بررسی‌شده ۳۹۰' );
await m.click( '[data-pkg-fill]' );
await m.waitForTimeout( 400 );
await shot( m, 'filled-390' );
await m.context().close();

process.exit( ( await close() ) ? 1 : 0 );
