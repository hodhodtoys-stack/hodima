/**
 * آزمون «ورود بسته» نسخه ۲ (hodima-package/2) برای دسته محصول، مقاله و ویدیو در Chromium واقعی با ورود
 * مدیر (ویرایشگر بلوکی خود وردپرس برای مقاله و ویدیو؛ فرم ترم برای دسته). اجرا: package-import-check.sh
 *
 * برای هر نوع: کادر در جای درست؛ «بررسی» (ردیف هر کادر، هشدارها، کلیدهای بیرون از نوع)؛ قفل آدرس؛ «پر کردن فرم»
 * همه کادرها؛ «بازگرداندن» دقیق؛ پر کردن دوباره و ذخیره خود صفحه ← متاهای ذخیره‌شده با کد ذخیره خود هر کادر؛
 * پیش از ذخیره هیچ نوشتنی؛ axe؛ عکس ۱۰۰۰ و ۳۹۰. خروجی ✔/✘؛ کد خروج ۱ اگر یکی رد شود.
 */
import { setup, site, box } from './package-import-lib.mjs';

const { log, php, phpJson, newContext, shot, axe, openAndCheck, close } = await setup();

/* ---------- داده آزمایشی ---------- */
const ids = phpJson( `
	if ( ! username_exists( 'pkgadmin' ) ) { wp_insert_user( [ 'user_login' => 'pkgadmin', 'user_pass' => 'pkg-pass-123', 'role' => 'administrator' ] ); }
	delete_transient( '_wc_activation_redirect' );
	update_term_meta( 22, '_hodima_is_pillar', '1' );
	update_term_meta( 1, '_hodima_is_pillar', '1' ); // دسته «Uncategorized» نوشته‌ها: پیلار والد مقاله
	$page = get_page_by_path( 'pkg-pillar-guide' );
	$page_id = $page ? $page->ID : wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'راهنمای جامع اکسسوری مو', 'post_name' => 'pkg-pillar-guide' ] );
	update_post_meta( $page_id, '_hodima_is_pillar', '1' );
	$video = get_page_by_path( 'cherry-hair-tie', OBJECT, 'video' );
	$video_id = $video ? $video->ID : wp_insert_post( [ 'post_type' => 'video', 'post_status' => 'publish', 'post_title' => 'کش موی گیلاسی', 'post_name' => 'cherry-hair-tie', 'post_content' => '<p>متن قبلی ویدیو</p>' ] );
	update_post_meta( $video_id, '_hod_video_url', 'https://hodima.test/wp-content/uploads/old.mp4' );
	update_post_meta( $video_id, '_hod_related_links', [ [ 'title' => 'کارت قبلی', 'url' => 'https://hodima.test/old/', 'cover' => '' ] ] );
	foreach ( [ 1, $video_id ] as $p ) { delete_post_meta( $p, '_edit_lock' ); }
	update_post_meta( 1, '_seobox_title', 'عنوان قبلی مقاله' );
	update_term_meta( 23, '_seobox_title', 'عنوان قبلی دسته' );
	wp_update_term( 23, 'product_cat', [ 'description' => '<p>توضیح قبلی دسته</p>' ] );
	$link = static fn( $t ) => is_string( $l = get_term_link( $t ) ) ? $l : '';
	return [
		'cat' => $link( 23 ), 'catParent' => $link( 22 ), 'postCat' => $link( 1 ),
		'post' => get_permalink( 1 ), 'page' => get_permalink( $page_id ), 'pageId' => $page_id,
		'video' => get_permalink( $video_id ), 'videoId' => $video_id,
		'p24' => get_permalink( 24 ), 'p28' => get_permalink( 28 ),
	];
` );
console.log( `    آدرس‌ها: دسته ${ ids.cat }، مقاله ${ ids.post }، ویدیو ${ ids.video }` );

const tel = '<a href="tel:09124093140">تماس</a>';
const packages = {
	category: {
		format: 'hodima-package/2', url: ids.cat, type: 'category', date: '2026-11-14', note: 'بسته آزمایشی دسته',
		boxes: {
			intro_html: `<details><summary>معرفی کلیپس‌ها PKG-INTRO</summary><p>کلیپس‌های عمده. ${ tel }</p></details>`,
			description_html: '<h2>راهنمای خرید PKG-DESC</h2>\n<p>توضیح تازه با <a href="https://example.org/x">لینک بیرونی</a>.</p>\n[hodima_table]\n[hodima_topic_cluster]',
			video: { url: 'https://www.aparat.com/v/abc123', title: 'ویدیوی معرفی کلیپس' },
			podcast: { url: 'https://hodima.test/wp-content/uploads/clips.mp3', title: 'پادکست کلیپس' },
			faq: [ { q: 'حداقل سفارش؟', a: 'یک کارتن.' }, { q: 'حداقل سفارش؟', a: 'تکراری عمدی.' } ],
			table: { headers: [ 'ویژگی', 'توضیحات' ], rows: [ [ 'جنس', 'فلز' ], [ 'جنس', 'فلز' ] ] },
			related_links: { products: [ ids.p24 ], articles: [ ids.post ] },
			topic_cluster: { pillar: false, exclude: false, parent_term: ids.catParent, parent_post_url: ids.page },
			aeo: { en_entity: 'Hair Clips Category', fa_context: 'دسته کلیپس‌ها', biz_model: 'tier_a', tier_a: { fa_basis: 'عدد' } },
			seobox: { title: 'خرید عمده کلیپس مو | هدهدلی', description: 'انواع کلیپس مو را عمده بخرید.' },
			discover: { title: 'نباید باشد' },
		},
	},
	article: {
		format: 'hodima-package/2', url: ids.post, type: 'article', date: '2026-11-14', note: 'بسته آزمایشی مقاله',
		boxes: {
			body: '<!-- wp:paragraph --><p>اول</p><!-- /wp:paragraph -->',
			intro_html: '<p>معرفی مقاله PKG-AINTRO</p>',
			video: { url: 'https://www.aparat.com/v/art999', title: 'ویدیوی مقاله', chapters: [ '0:00 شروع', '0:30 ادامه' ] },
			podcast: [],
			faq: [ { q: 'این مقاله درباره چیست؟', a: 'اکسسوری مو.' } ],
			table: { headers: [ 'مورد', 'مقدار' ], rows: [ [ 'زمان مطالعه', '۵ دقیقه' ] ] },
			related_links: { products: [ ids.p24, ids.p28 ], articles: [] },
			topic_cluster: { pillar: false, exclude: false, parent_term: ids.postCat, parent_post_url: ids.page },
			discover: { title: 'راهنمای کامل کلیپس مو', topics: [ 'کلیپس مو', 'اکسسوری مو https://www.wikidata.org/wiki/Q1' ], skip: false },
			aeo: { en_entity: 'Hair Clip Guide', fa_context: 'راهنمای کلیپس' },
			seobox: { title: 'راهنمای خرید کلیپس مو | هدهدلی', description: 'هر آنچه درباره کلیپس مو باید بدانید.' },
		},
	},
	video: {
		format: 'hodima-package/2', url: ids.video, type: 'video', date: '2026-11-14', note: 'بسته آزمایشی ویدیو',
		boxes: {
			body: '<p>توضیح تازه ویدیو PKG-VIDEO</p>',
			video: { url: 'https://hodima.test/wp-content/uploads/cherry.mp4', duration: '1:20', chapters: '0:00 معرفی\n0:40 بستن مو', transcript: 'متن کامل ویدیو' },
			suggestions: [ { title: 'کش موی ساده', url: ids.p24 }, { title: 'گلسر', url: ids.p28, cover: 'https://hodima.test/wp-content/uploads/c.jpg' } ],
			product_button: ids.p24,
			seobox: { title: 'ویدیوی کش موی گیلاسی | هدهدلی', description: 'ببینید کش موی گیلاسی چطور بسته می‌شود.' },
			discover: { title: 'نباید باشد' },
			download_url: 'https://x/y.mp4',
		},
	},
};
// بدنه مقاله با شورت‌کد و HTML ساده (نه بلوک) — باید بی‌تغییر بماند
packages.article.boxes.body = '<h2>راهنمای کلیپس PKG-ARTICLE</h2>\n<p>متن مقاله.</p>\n[hook_faq]';

/* ---------- کمک‌ها ---------- */
async function waitEditor( page ) {
	await page.waitForFunction( () => !! window.wp?.data?.select( 'core/editor' )?.getCurrentPostId?.(), null, { timeout: 60000 } );
	await page.evaluate( () => window.wp.data.dispatch( 'core/preferences' )?.set?.( 'core/edit-post', 'welcomeGuide', false ) );
	// پنجره «Welcome to the block editor» نصب تازه روی کادرها می‌افتد
	for ( let i = 0; i < 5 && await page.locator( '.components-modal__screen-overlay' ).count(); i++ ) {
		await page.keyboard.press( 'Escape' );
		await page.waitForTimeout( 300 );
	}
	await page.waitForTimeout( 500 );
}

/** ذخیره در ویرایشگر بلوکی: نوشته (REST) و بعد فرم کادرهای قدیمی (post.php?meta-box-loader=1). */
async function saveBlock( page ) {
	const metaBoxes = page.waitForResponse( ( r ) => r.url().includes( 'meta-box-loader=1' ), { timeout: 90000 } );
	await page.evaluate( () => window.wp.data.dispatch( 'core/editor' ).savePost() );
	await metaBoxes;
	await page.waitForFunction( () => ! window.wp.data.select( 'core/edit-post' ).isSavingMetaBoxes() && ! window.wp.data.select( 'core/editor' ).isSavingPost(), null, { timeout: 60000 } );
	await page.waitForTimeout( 800 );
}

const rowsOf = ( page ) => page.evaluate( () => [ ...document.querySelectorAll( '.hodima-pkg__row' ) ].map( ( r ) => `${ r.querySelector( '.hodima-pkg__name' ).textContent }=${ r.dataset.state }` ) );
const warnsOf = ( page ) => page.evaluate( () => [ ...document.querySelectorAll( '.hodima-pkg__warns li, [data-pkg-ignored] li' ) ].map( ( l ) => l.textContent ) );

async function reviewFillUndo( page, name, pkg, snapshotFn ) {
	await openAndCheck( page, JSON.stringify( pkg ) );
	const ok = await page.evaluate( () => ! document.querySelector( '[data-pkg-review]' ).hidden );
	log( ok, `${ name }: بسته بررسی شد`, ok ? '' : await page.evaluate( () => document.querySelector( '[data-pkg-errors]' ).textContent ) );
	const rows = await rowsOf( page );
	console.log( `    ردیف‌ها: ${ rows.join( ' | ' ) }` );
	const warns = await warnsOf( page );
	await shot( page, `${ name }-review-1000` );
	await axe( page, `${ name } بررسی‌شده` );
	const before = await snapshotFn();
	await page.click( '[data-pkg-fill]' );
	await page.waitForTimeout( 700 );
	const states = await page.evaluate( () => [ ...document.querySelectorAll( '.hodima-pkg__row' ) ].map( ( r ) => r.dataset.state ) );
	log( ! states.includes( 'failed' ) && ! states.includes( 'missing' ), `${ name }: همه کادرها پر شدند`, states.join( ',' ) );
	const filled = await snapshotFn();
	await shot( page, `${ name }-filled-1000` );
	await page.click( '[data-pkg-undo]' );
	await page.waitForTimeout( 700 );
	const undone = await snapshotFn();
	// بدنه ویرایشگر بلوکی: متن کلاسیک بی‌بلوک بعد از هر ویرایش بدون <p> سریال می‌شود (وردپرس هنگام نمایش برمی‌گرداند)؛
	// همان مقایسه خود ابزار (htmlKey): بدون <p>، <br> و فاصله
	const norm = ( k, v ) => ( 'body' === k && 'string' === typeof v ? v.replace( /<\/?p>|<br\s*\/?>/giu, '' ).replace( /\s+/gu, '' ) : JSON.stringify( v ) );
	const diff = Object.keys( before ).filter( ( k ) => norm( k, before[ k ] ) !== norm( k, undone[ k ] ) );
	log( ! diff.length, `${ name }: «بازگرداندن» همه خانه‌ها را برگرداند`, diff.map( ( k ) => `${ k }: ${ JSON.stringify( before[ k ] ).slice( 0, 120 ) } ≠ ${ JSON.stringify( undone[ k ] ).slice( 0, 120 ) }` ).join( ' ؛ ' ) );
	await page.click( '[data-pkg-fill]' );
	await page.waitForTimeout( 700 );
	return { rows, filled, warns };
}

/* ================= دسته محصول ================= */
console.log( 'ورود بسته — دسته محصول (فرم ترم)' );
{
	const page = await newContext( 1000 );
	await page.goto( `${ site }/wp-admin/`, { waitUntil: 'load', timeout: 60000 } );
	await page.goto( `${ site }/wp-admin/term.php?taxonomy=product_cat&tag_ID=23&post_type=product`, { waitUntil: 'load', timeout: 60000 } );
	await page.waitForTimeout( 1200 );
	const place = await page.evaluate( ( sel ) => {
		const el = document.querySelector( sel );
		const form = document.getElementById( 'edittag' );
		const table = form?.querySelector( '.form-table' );
		return { inForm: !! el && form?.contains( el ), aboveTable: !! el && !! table && ( el.compareDocumentPosition( table ) & Node.DOCUMENT_POSITION_FOLLOWING ) > 0, visible: !! el && ! el.hidden };
	}, box );
	log( place.inForm && place.aboveTable && place.visible, 'کادر داخل فرم دسته و بالای جدول است', JSON.stringify( place ) );

	const metaBefore = php( 'echo md5( serialize( get_term_meta( 23 ) ) . get_term_field( "description", 23 ) );' );
	const snap = () => page.evaluate( () => {
		const v = ( s ) => document.querySelector( s )?.value ?? null;
		const ed = ( id ) => { const e = window.tinymce?.get( id ); return e && ! e.isHidden() ? e.getContent() : v( `#${ id }` ); };
		return {
			intro: ed( 'hookcontent' ), desc: ed( 'category_description_edit' ),
			video: v( '[name="hodima_media[video_url]"]' ), voice: v( '[name="hodima_media[voice_url]"]' ),
			faq: [ ...document.querySelectorAll( '[data-hodima-faqs] > [data-hodima-faq] input' ) ].map( ( i ) => i.value ),
			table: v( 'input[name="hodima_table_json"]' ),
			rl: [ ...document.querySelectorAll( '.hodima-rl-slot [data-field="id"]' ) ].map( ( i ) => i.value ),
			tc: [ ...document.querySelectorAll( 'select[name="hodima_pillar_id[]"] option:checked' ) ].map( ( o ) => o.value ),
			aeo: v( '[name="h_ai_en_entity"]' ), seo: v( '#seobox_title' ),
		};
	} );
	const { rows, filled, warns } = await reviewFillUndo( page, 'category', packages.category, snap );
	log( rows.length === 10, 'دسته: ده کادر نوع دسته', String( rows.length ) );
	const has = ( re ) => warns.some( ( w ) => re.test( w ) );
	log( has( /example\.org/ ), 'دسته: هشدار لینک بیرونی' );
	log( has( /تکرار/ ) && has( /ردیف ۲ جدول تکرار|ردیف 2 جدول تکرار/ ), 'دسته: هشدار پرسش و ردیف تکراری' );
	log( has( /اطلاعات فروش/ ), 'دسته: AEO بخش فروش ندارد (هشدار)' );
	log( has( /والد مقاله‌ای/ ), 'دسته: parent_post_url برای دسته هشدار' );
	log( has( /discover|دیسکاور/ ), 'دسته: کلید discover بیرون از نوع دسته گزارش شد' );
	log( /<details>/.test( filled.intro ) && /PKG-INTRO/.test( filled.intro ), 'دسته: متن معرفی با <details> در ویرایشگر', filled.intro?.slice( 0, 80 ) );
	log( /PKG-DESC/.test( filled.desc ) && /\[hodima_table\]/.test( filled.desc ), 'دسته: توضیحات با شورت‌کد' );
	log( filled.video === 'https://www.aparat.com/v/abc123' && /clips\.mp3$/.test( filled.voice ), 'دسته: ویدیو و پادکست' );
	log( filled.faq.length === 2 && /جنس/.test( filled.table ) && filled.rl.filter( Boolean ).length >= 2 && filled.tc.join() === '22', 'دسته: پرسش، جدول، لینک‌ها، والد خوشه', JSON.stringify( { rl: filled.rl, tc: filled.tc } ) );
	log( filled.aeo === 'Hair Clips Category' && filled.seo === 'خرید عمده کلیپس مو | هدهدلی', 'دسته: AEO و سئوباکس' );
	log( metaBefore === php( 'echo md5( serialize( get_term_meta( 23 ) ) . get_term_field( "description", 23 ) );' ), 'دسته: بررسی و پر کردن چیزی ذخیره نکرد' );

	page.on( 'dialog', ( d ) => d.accept() );
	await Promise.all( [ page.waitForEvent( 'load', { timeout: 60000 } ), page.click( '#edittag input[type="submit"].button-primary, #edittag #submit' ) ] );
	await page.waitForTimeout( 1500 );
	const saved = phpJson( `
		clean_term_cache( 23, 'product_cat' );
		$m = hodima_media_get_data( 23, 'term', true );
		$t = Hodima_Dynamic_Table::get_table( 23, Hodima_Table_Context::Term );
		$rl = \\Hodima\\RelatedLinks\\Store::get( 23, 'term' );
		return [
			'intro' => $m['content'] ?? '', 'desc' => get_term_field( 'description', 23, 'product_cat', 'raw' ),
			'video' => $m['video_url'] ?? '', 'voice' => $m['voice_url'] ?? '', 'faq' => count( (array) ( $m['faq'] ?? [] ) ),
			'table' => count( $t['rows'] ), 'rl' => array_map( static fn( $g ) => array_column( $g, 'id' ), $rl ),
			'parents' => get_term_meta( 23, '_hodima_pillar_id' ), 'aeo' => get_term_meta( 23, '_h_ai_en_entity', true ),
			'seo' => get_term_meta( 23, '_seobox_title', true ), 'seod' => get_term_meta( 23, '_seobox_description', true ),
		];
	` );
	log( /<details>/.test( saved.intro ) && /PKG-INTRO/.test( saved.intro ), 'دسته ذخیره: متن معرفی با <details>', saved.intro.slice( 0, 90 ) );
	log( /PKG-DESC/.test( saved.desc ) && /\[hodima_table\]/.test( saved.desc ) && /\[hodima_topic_cluster\]/.test( saved.desc ), 'دسته ذخیره: توضیحات با شورت‌کدها' );
	log( saved.video === 'https://www.aparat.com/v/abc123' && /clips\.mp3$/.test( saved.voice ) && saved.faq === 2, 'دسته ذخیره: ویدیو، پادکست، پرسش‌ها', JSON.stringify( [ saved.video, saved.voice, saved.faq ] ) );
	log( saved.table === 2 && JSON.stringify( saved.rl?.products ) === '[24]' && JSON.stringify( saved.rl?.article ) === '[1]', 'دسته ذخیره: جدول و لینک‌های مرتبط', JSON.stringify( [ saved.table, saved.rl ] ) );
	log( JSON.stringify( saved.parents ).includes( '22' ) && saved.aeo === 'Hair Clips Category' && saved.seo === 'خرید عمده کلیپس مو | هدهدلی', 'دسته ذخیره: والد خوشه، AEO، سئوباکس', JSON.stringify( [ saved.parents, saved.aeo, saved.seo ] ) );
	log( ! page.errors.length, 'دسته: بدون خطای جاوااسکریپت هدیما', page.errors.join( ' ; ' ) );

	// قفل آدرس روی دسته
	await openAndCheck( page, JSON.stringify( { ...packages.category, url: ids.catParent } ) );
	const locked = await page.evaluate( () => document.querySelector( '[data-pkg-lock]' ).dataset.state === 'error' && document.querySelector( '[data-pkg-fillbar]' ).hidden );
	log( locked, 'دسته: قفل آدرس (آدرس دسته دیگر رد شد)' );
	await page.context().close();
}

/* ================= مقاله (ویرایشگر بلوکی) ================= */
console.log( 'ورود بسته — مقاله (ویرایشگر بلوکی)' );
{
	const page = await newContext( 1000 );
	await page.goto( `${ site }/wp-admin/post.php?post=1&action=edit`, { waitUntil: 'load', timeout: 90000 } );
	await waitEditor( page );
	const editor = await page.evaluate( () => window.hodimaPkg?.editor );
	log( 'block' === editor, 'مقاله: ویرایشگر بلوکی شناخته شد', String( editor ) );
	log( await page.locator( `#hodima_pkg_box ${ box }` ).count() === 1, 'مقاله: کادر «ورود بسته» در ناحیه کادرهای ویرایشگر بلوکی' );
	const snap = () => page.evaluate( () => {
		const v = ( s ) => document.querySelector( s )?.value ?? null;
		const ed = ( id ) => { const e = window.tinymce?.get( id ); return e && ! e.isHidden() ? e.getContent() : v( `#${ id }` ); };
		return {
			body: window.wp.data.select( 'core/editor' ).getEditedPostContent(),
			intro: ed( 'hookcontent' ), video: v( '[name="hodima_media[video_url]"]' ), chapters: v( '[name="hodima_media[video_chapters]"]' ),
			faq: [ ...document.querySelectorAll( '[data-hodima-faqs] > [data-hodima-faq] input' ) ].map( ( i ) => i.value ),
			table: v( 'input[name="hodima_table_json"]' ),
			rl: [ ...document.querySelectorAll( '.hodima-rl-slot [data-field="id"]' ) ].map( ( i ) => i.value ),
			tc: [ ...document.querySelectorAll( 'select[name="hodima_pillar_id[]"] option:checked' ) ].map( ( o ) => o.value ),
			tcPost: [ ...document.querySelectorAll( 'select[name="hodima_pillar_post_id[]"] option:checked' ) ].map( ( o ) => o.value ),
			dcTitle: v( '[name="hodima_discover[title]"]' ), dcTopics: v( '[data-hodima-dc-entities]' ),
			dcChips: document.querySelectorAll( '.hodima-dc__chip' ).length,
			aeo: v( '[name="h_ai_en_entity"]' ), seo: v( '#seobox_title' ),
		};
	} );
	const { rows, filled } = await reviewFillUndo( page, 'article', packages.article, snap );
	log( rows.length === 11, 'مقاله: یازده کادر نوع مقاله', String( rows.length ) );
	log( /PKG-ARTICLE/.test( filled.body ) && /\[hook_faq\]/.test( filled.body ), 'مقاله: بدنه در ویرایشگر بلوکی (شورت‌کد ماند)', filled.body.slice( 0, 90 ) );
	log( /PKG-AINTRO/.test( filled.intro ) && filled.video === 'https://www.aparat.com/v/art999' && filled.chapters === '0:00 شروع\n0:30 ادامه', 'مقاله: متن معرفی، ویدیو و فصل‌ها' );
	log( filled.tc.join() === '1' && filled.tcPost.join() === String( ids.pageId ), 'مقاله: والد دسته پیلار و «محتوای ستون» (مقاله/برگه پیلار)', JSON.stringify( [ filled.tc, filled.tcPost ] ) );
	log( filled.dcTitle === 'راهنمای کامل کلیپس مو' && filled.dcChips === 2 && /Q1/.test( filled.dcTopics ), 'مقاله: دیسکاور (عنوان و دو موضوع)', JSON.stringify( [ filled.dcTitle, filled.dcChips ] ) );
	log( filled.rl.filter( Boolean ).length >= 2 && filled.faq.length === 1 && /زمان مطالعه/.test( filled.table ), 'مقاله: لینک‌ها، پرسش، جدول' );
	await saveBlock( page );
	const saved = phpJson( `
		clean_post_cache( 1 );
		$m = hodima_media_get_data( 1, 'post', true );
		$d = hodima_seo_discover_data( 1, 'post' );
		return [
			'body' => get_post_field( 'post_content', 1 ), 'intro' => $m['content'] ?? '', 'video' => $m['video_url'] ?? '', 'voice' => $m['voice_url'] ?? '',
			'chapters' => $m['video_chapters'] ?? '', 'faq' => count( (array) ( $m['faq'] ?? [] ) ),
			'parents' => get_post_meta( 1, '_hodima_pillar_id' ), 'post_parents' => get_post_meta( 1, '_hodima_pillar_post_id' ),
			'dc' => [ $d['title'] ?? '', count( (array) ( $d['entity_items'] ?? [] ) ) ],
			'aeo' => get_post_meta( 1, '_h_ai_en_entity', true ), 'seo' => get_post_meta( 1, '_seobox_title', true ),
		];
	` );
	log( /PKG-ARTICLE/.test( saved.body ) && /\[hook_faq\]/.test( saved.body ), 'مقاله ذخیره: بدنه', saved.body.slice( 0, 90 ) );
	log( /PKG-AINTRO/.test( saved.intro ) && saved.video === 'https://www.aparat.com/v/art999' && saved.voice === '' && saved.faq === 1, 'مقاله ذخیره: متن معرفی، ویدیو، پادکست خالی ([])، پرسش', JSON.stringify( [ saved.video, saved.voice, saved.faq ] ) );
	log( JSON.stringify( saved.parents ).includes( '1' ) && JSON.stringify( saved.post_parents ).includes( String( ids.pageId ) ), 'مقاله ذخیره: والدهای خوشه', JSON.stringify( [ saved.parents, saved.post_parents ] ) );
	log( saved.dc[ 0 ] === 'راهنمای کامل کلیپس مو' && saved.dc[ 1 ] === 2 && saved.aeo === 'Hair Clip Guide' && saved.seo === 'راهنمای خرید کلیپس مو | هدهدلی', 'مقاله ذخیره: دیسکاور، AEO، سئوباکس', JSON.stringify( [ saved.dc, saved.aeo, saved.seo ] ) );
	log( ! page.errors.length, 'مقاله: بدون خطای جاوااسکریپت هدیما', page.errors.join( ' ; ' ) );
	await page.context().close();
}

/* ================= ویدیو (ویرایشگر بلوکی) ================= */
console.log( 'ورود بسته — ویدیو (ویرایشگر بلوکی)' );
{
	const page = await newContext( 1000 );
	await page.goto( `${ site }/wp-admin/post.php?post=${ ids.videoId }&action=edit`, { waitUntil: 'load', timeout: 90000 } );
	await waitEditor( page );
	const snap = () => page.evaluate( () => {
		const v = ( s ) => document.querySelector( s )?.value ?? null;
		return {
			body: window.wp.data.select( 'core/editor' ).getEditedPostContent(),
			url: v( '[name="hod_video_url"]' ), duration: v( '[name="hod_video_duration"]' ), chapters: v( '[name="hod_video_chapters"]' ),
			cards: [ ...document.querySelectorAll( '[data-hdm-repeat] [data-hdm-row]' ) ].map( ( r ) => [ r.querySelector( 'input[name$="[title]"]' )?.value, r.querySelector( 'input[name$="[url]"]' )?.value, r.querySelector( 'input[name$="[title]"]' )?.name ] ),
			product: v( '#hod_related_product_url' ), sw: document.getElementById( 'hvw-product-switch' )?.checked,
			seo: v( '#seobox_title' ),
		};
	} );
	const { rows, filled, warns } = await reviewFillUndo( page, 'video', packages.video, snap );
	log( rows.length === 5, 'ویدیو: پنج کادر نوع ویدیو', String( rows.length ) );
	log( warns.some( ( w ) => /download_url/.test( w ) ) && warns.some( ( w ) => /discover/.test( w ) ), 'ویدیو: download_url و discover بیرون از نوع گزارش شدند' );
	log( /PKG-VIDEO/.test( filled.body ) && /cherry\.mp4$/.test( filled.url ) && filled.duration === '1:20' && /بستن مو/.test( filled.chapters ), 'ویدیو: بدنه و کادر «تنظیمات ویدیو»' );
	log( filled.cards.length === 2 && filled.cards[ 1 ][ 0 ] === 'گلسر' && filled.cards[ 1 ][ 2 ] === 'hod_related_links[1][title]', 'ویدیو: دو کارت با نام‌گذاری کیت', JSON.stringify( filled.cards ) );
	log( filled.sw === true && filled.product === ids.p24, 'ویدیو: کلید و لینک دکمه محصول' );
	await saveBlock( page );
	const saved = phpJson( `
		clean_post_cache( ${ ids.videoId } );
		return [
			'body' => get_post_field( 'post_content', ${ ids.videoId } ), 'url' => get_post_meta( ${ ids.videoId }, '_hod_video_url', true ),
			'cards' => get_post_meta( ${ ids.videoId }, '_hod_related_links', true ), 'product' => get_post_meta( ${ ids.videoId }, '_hod_related_product_url', true ),
			'chapters' => get_post_meta( ${ ids.videoId }, '_hod_video_chapters', true ), 'seo' => get_post_meta( ${ ids.videoId }, '_seobox_title', true ),
		];
	` );
	log( /PKG-VIDEO/.test( saved.body ) && /cherry\.mp4$/.test( saved.url ) && /بستن مو/.test( saved.chapters ), 'ویدیو ذخیره: بدنه، آدرس، فصل‌ها' );
	log( Array.isArray( saved.cards ) && saved.cards.length === 2 && saved.cards[ 1 ].title === 'گلسر' && saved.product === ids.p24 && saved.seo === 'ویدیوی کش موی گیلاسی | هدهدلی', 'ویدیو ذخیره: کارت‌ها، دکمه محصول، سئوباکس', JSON.stringify( [ saved.cards, saved.product ] ) );
	log( ! page.errors.length, 'ویدیو: بدون خطای جاوااسکریپت هدیما', page.errors.join( ' ; ' ) );
	await page.context().close();
}

/* ================= موبایل ۳۹۰ (دسته) ================= */
{
	const m = await newContext( 390 );
	await m.goto( `${ site }/wp-admin/term.php?taxonomy=product_cat&tag_ID=23&post_type=product`, { waitUntil: 'load', timeout: 60000 } );
	await m.waitForTimeout( 1000 );
	await openAndCheck( m, JSON.stringify( { ...packages.category, boxes: { seobox: { title: 'عنوان موبایل', description: '' } } } ) );
	const r = await m.evaluate( ( sel ) => { const b = document.querySelector( sel ).getBoundingClientRect(); return { left: Math.round( b.left ), right: Math.round( b.right ), w: innerWidth }; }, box );
	log( r.left >= 0 && r.right <= r.w, 'دسته ۳۹۰: کادر داخل صفحه', JSON.stringify( r ) );
	await shot( m, 'category-review-390' );
	await axe( m, 'دسته ۳۹۰' );
	await m.context().close();
}

process.exit( ( await close() ) ? 1 : 0 );
