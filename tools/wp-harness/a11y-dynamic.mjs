/**
 * تست پویای دسترس‌پذیری (WCAG 2.2 AA) — اسکیل hodima-theme.
 *
 *   node a11y-dynamic.mjs <out-dir> <root> [سناریو …]
 *
 * a11y-audit.mjs فقط HTML ثابت را می‌سنجد؛ اینجا با JS و CSS واقعی قالب/افزونه‌ها صفحه اصلی
 * ساخته‌شده ابزار تست را باز می‌کنیم و واقعا Tab / Shift+Tab / Esc / Enter / فلش می‌زنیم و رفتار را
 * می‌سنجیم: منوی موبایل و زیرمنو، جستجوی زنده (combobox)، پنجره پشتیبانی (<dialog>)، پخش‌کننده
 * استوری و پاپ‌آپ اعلان (تله فوکوس، Esc، برگشت فوکوس، aria-hidden، بقیه صفحه).
 * نشانه‌گذاری پنجره پشتیبانی، استوری و اعلان در صفحه آزمایشی ابزار نیست؛ از PHP همان بخش‌ها
 * (header.php، hodima-StoryPlayer.php، notification-front.php) بازنویسی و به صفحه اصلی واقعی
 * تزریق می‌شود (اگر آن PHP عوض شود این نمونه‌ها باید هماهنگ شوند). ajax جستجو شبیه‌سازی می‌شود و
 * ویدیوی استوری یک webm کوچک ساخته‌شده با ffmpeg است.
 * خروجی: هر بررسی ✔ یا ✘ با شرح؛ کد خروج ۱ اگر نقضی باشد.
 */
import { chromium } from 'playwright-core';
import fs from 'node:fs';
import path from 'node:path';
import os from 'node:os';
import { execFileSync } from 'node:child_process';
import { createRequire } from 'node:module';

const axePath = createRequire( import.meta.url ).resolve( 'axe-core/axe.min.js' );

const [ outDir, root, ...only ] = process.argv.slice( 2 );
if ( ! outDir || ! root ) {
	console.error( 'usage: a11y-dynamic.mjs <out-dir> <root> [scenario ...]' );
	process.exit( 2 );
}
const wpRoot = path.join( process.env.HODIMA_HARNESS_WP || process.env.HODIMA_HARNESS || '/tmp/hodima-harness', 'wp' );
const MIME = { '.css': 'text/css', '.js': 'text/javascript', '.woff2': 'font/woff2', '.woff': 'font/woff', '.svg': 'image/svg+xml', '.png': 'image/png', '.webm': 'video/webm' };
const GREY = Buffer.from( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mN4+P/ffwAJpAPqf4/yxwAAAABJRU5ErkJggg==', 'base64' );

// ویدیوی کوتاه برای پخش‌کننده استوری
const webm = path.join( os.tmpdir(), 'hodima-a11y-dyn.webm' );
if ( ! fs.existsSync( webm ) ) {
	try {
		execFileSync( 'ffmpeg', [ '-v', 'error', '-f', 'lavfi', '-i', 'color=c=0x25316a:s=160x284:d=4:r=10', '-c:v', 'libvpx', '-b:v', '60k', '-y', webm ] );
	} catch { /* بدون ffmpeg: پخش شکست می‌خورد ولی فوکوس همچنان سنجیده می‌شود */ }
}

const localFile = ( url ) => {
	const u = new URL( url );
	if ( u.hostname !== 'hodima.test' ) return null;
	const p = decodeURIComponent( u.pathname );
	if ( p === '/v.webm' ) return webm;
	if ( p.startsWith( '/wp-content/themes/hodima/' ) ) return path.join( root, 'hodima', p.slice( '/wp-content/themes/hodima/'.length ) );
	const m = p.match( /^\/wp-content\/plugins\/(hodima-[a-z]+)\/(.*)$/ );
	if ( m ) return path.join( root, 'plugins', m[ 1 ], m[ 2 ] );
	if ( p.startsWith( '/wp-includes/' ) || p.startsWith( '/wp-content/plugins/' ) ) return path.join( wpRoot, p );
	return null;
};

const MEDIA = 'wp-content/plugins/hodima-media/components';
const SVG = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 18v-6a9 9 0 0 1 18 0v6"/></svg>';

// ── نشانه‌گذاری تزریقی (از PHP همان بخش‌ها) ──
const SUPPORT_TRIGGER = `<button class="btn-support" id="supportTrigger" type="button" aria-label="باز کردن پنجره پشتیبانی" aria-haspopup="dialog">${ SVG }<span>پشتیبانی</span></button>`;
const SUPPORT_DIALOG = `<dialog class="overlay" id="supportOverlay" aria-labelledby="supportTitle"><article class="popup"><button class="popup__close" id="supportClose" type="button" aria-label="بستن پنجره پشتیبانی">&times;</button><h3 class="popup__title" id="supportTitle">ارتباط با ما</h3>${ [ 'whatsapp', 'telegram', 'phone' ].map( ( c ) => `<a href="#" class="popup__link popup__link--${ c }" aria-label="${ c }">${ SVG } ${ c }</a>` ).join( '' ) }</article></dialog>`;
const STORY_BAR = `<section class="section-stories" style="padding:16px"><div class="stories-wrap">${ [ 1, 2, 3 ].map( ( i ) => `<div class="story-item"><a href="/v.webm" class="story-link" rel="nofollow noopener" data-video="https://hodima.test/v.webm" data-story-id="${ i }" data-views="${ i * 10 }" data-title="استوری ${ i }"${ i === 2 ? ' data-cta-link="https://hodima.test/shop/" data-cta-text="مشاهده محصول"' : '' }><div class="story-ring"><img src="data:image/gif;base64,R0lGODlhAQABAAAAACw=" alt="استوری ${ i }" width="75" height="75"></div><span class="story-text">استوری ${ i }</span></a></div>` ).join( '' ) }</div></section>`;
const STORY_MODAL = `<div id="hs-video-modal" class="hs-modal" aria-hidden="true" role="dialog" aria-modal="true" aria-label="پخش کننده استوری"><div class="hs-modal__overlay" data-close="1"></div><div class="hs-modal__content" role="document" tabindex="-1"><button class="hs-modal__close hs-flex-center" type="button" aria-label="بستن"><span aria-hidden="true">&times;</span></button><div class="hs-player is-loading"><div class="hs-loader hs-flex-center" aria-hidden="true"></div><div class="hs-topbar"><div class="hs-progress-wrap" role="group" aria-label="پیشرفت استوری‌ها"></div><div class="hs-meta"><button class="hs-mute-toggle hs-flex-center" type="button" aria-pressed="false" aria-label="بی‌صدا"><svg class="hs-icon-sound" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path d="M11 5 6 9H2v6h4l5 4z"/></svg><svg class="hs-icon-muted" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path d="M11 5 6 9H2v6h4l5 4z"/></svg></button><div class="hs-meta__text"><div class="hs-title" id="hs-story-title"></div><div class="hs-subtitle" id="hs-story-position"></div></div></div></div><div class="hs-stage"><video id="hs-modal-video" playsinline preload="metadata"></video><button class="hs-nav hs-flex-center hs-nav--prev" type="button" aria-label="استوری قبلی"><span aria-hidden="true">&#10094;</span></button><button class="hs-nav hs-flex-center hs-nav--next" type="button" aria-label="استوری بعدی"><span aria-hidden="true">&#10095;</span></button><div class="hs-tap-zone hs-tap-zone--left" data-tap="next" aria-hidden="true"></div><div class="hs-tap-zone hs-tap-zone--center" data-tap="toggle" aria-hidden="true"></div><div class="hs-tap-zone hs-tap-zone--right" data-tap="prev" aria-hidden="true"></div><div class="hs-center-control hs-flex-center"><button class="hs-play-toggle hs-flex-center" type="button" aria-label="پخش / توقف"><span class="hs-play-icon hs-icon-play" aria-hidden="true">▶</span><span class="hs-play-icon hs-icon-pause" aria-hidden="true">❚❚</span></button></div></div><div class="hs-bottombar"><div class="hs-message" id="hs-player-message" aria-live="polite"></div><div class="hs-actions hs-flex-center"><div class="hs-action-item hs-flex-center hs-view-stat" title="بازدید"><span id="hs-view-count">0</span></div><button class="hs-action-item hs-flex-center hs-share-btn" type="button" aria-label="اشتراک گذاری"><span class="hs-icon hs-share-icon" aria-hidden="true">⤴</span></button><a href="#" id="hs-cta-box" class="hs-cta-box" hidden><span class="hs-cta-text" id="hs-cta-text"></span><span class="hs-cta-arrow" aria-hidden="true">&#8592;</span></a></div></div></div></div><div class="hs-toast" id="hs-player-toast" aria-live="polite" aria-atomic="true"></div></div>`;
const STR = { playHint: 'برای پخش کلیک کنید.', playError: 'خطا', playlistEnd: 'پایان', ctaDefault: 'مشاهده محصول', storyN: 'استوری %s', selectors: { storyLink: '.story-link', playlistScope: '[data-story-group], .section-stories' }, settings: { autoplayNext: false, loopPlaylist: false, tapNavigation: true, swipeNavigation: true, preloadAdjacent: false } };
// همان خروجی notification-front.php نسخه ۴ (<dialog>)؛ mobileMode «corner» = پنجره وسط در موبایل جعبه گوشه
const notif = ( position, mobileMode = 'modal' ) => `<dialog id="hd-notification-7" class="hd-notification-popup hd-pos-${ position }" aria-label="اطلاعیه" tabindex="-1" style="--hd-notif-w:300px;--hd-notif-bg:#ffffff;--hd-notif-close:#ff0000;" data-id="7" data-delay="1000" data-auto-close="0" data-max-count="0" data-anim-in="fade_in" data-anim-out="fade_out" data-trigger-exit="0" data-trigger-scroll="0" data-trigger-inactivity="0" data-position="${ position }" data-mobile-mode="${ mobileMode }" data-device="all" data-start="0" data-end="0" data-utm="" data-referrer="" data-skip-search="0"><button type="button" class="hd-notif-close" aria-label="بستن"><span aria-hidden="true">&times;</span></button><div class="hd-notif-main-area"><a class="hd-notif-half-link" href="#promo"><img data-src="data:image/gif;base64,R0lGODlhAQABAAAAACw=" width="300" height="200" alt="اطلاعیه" decoding="async"></a></div></dialog>`;

const base = fs.readFileSync( path.join( outDir, 'home.html' ), 'utf8' );
const MEDIA_LINK = ( f ) => `<link rel="stylesheet" href="https://hodima.test/${ MEDIA }/${ f }">`;
function build( { support, story, notification, mobileMode } ) {
	let html = base;
	let head = '';
	let tail = '';
	if ( support ) {
		html = html.replace( /(<div class="header__left">)/, `$1${ SUPPORT_TRIGGER }` );
		tail += SUPPORT_DIALOG;
	}
	if ( story ) {
		head += MEDIA_LINK( 'hodima-Stories/hodima-StoryPlayer/hodima-player.css' );
		html = html.replace( /(<main[^>]*>)/, `$1${ STORY_BAR }` );
		tail += `${ STORY_MODAL }<script>window.HS_PLAYER_STR=${ JSON.stringify( STR ) };</script><script src="https://hodima.test/${ MEDIA }/hodima-Stories/hodima-StoryPlayer/hodima-player.js"></script>`;
	}
	if ( notification ) {
		head += MEDIA_LINK( 'notification/assets/css/notification-front.css' );
		tail += `${ notif( notification, mobileMode ) }<script src="https://hodima.test/${ MEDIA }/notification/assets/js/notification-front.js"></script>`;
	}
	return html.replace( '</head>', `${ head }</head>` ).replace( /<\/body>/, `${ tail }</body>` );
}

const SEARCH_DATA = [ 1, 2, 3 ].map( ( i ) => ( { link: `https://hodima.test/product/${ i }/`, title: `گل‌سر نمونه ${ i }`, image: 'https://hodima.test/i.png', price: `${ i }00 تومان` } ) );

const browser = await chromium.launch( { executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium' } ).catch( () => chromium.launch() );
let failures = 0;
const log = ( ok, id, detail = '' ) => {
	if ( ! ok ) failures++;
	console.log( `  ${ ok ? '✔' : '✘' } ${ id }${ detail ? ` — ${ detail }` : '' }` );
};

async function open( html, width, height = 800 ) {
	const ctx = await browser.newContext( { viewport: { width, height }, locale: 'fa-IR', reducedMotion: 'reduce' } );
	const page = await ctx.newPage();
	const errors = [];
	page.on( 'pageerror', ( e ) => errors.push( e.message ) );
	await page.route( '**/*', async ( route ) => {
		const url = route.request().url();
		if ( url === 'https://hodima.test/__page__' ) return route.fulfill( { status: 200, contentType: 'text/html; charset=utf-8', body: html } );
		if ( url.includes( 'admin-ajax.php' ) && url.includes( 'woo_live_search' ) ) return route.fulfill( { status: 200, contentType: 'application/json', body: JSON.stringify( SEARCH_DATA ) } );
		const file = localFile( url );
		if ( file && fs.existsSync( file ) && fs.statSync( file ).isFile() ) return route.fulfill( { status: 200, contentType: MIME[ path.extname( file ) ] || 'application/octet-stream', body: fs.readFileSync( file ) } );
		if ( route.request().resourceType() === 'image' ) return route.fulfill( { status: 200, contentType: 'image/png', body: GREY } );
		return route.fulfill( { status: 404, body: '' } );
	} );
	await page.goto( 'https://hodima.test/__page__', { waitUntil: 'load' } );
	await page.waitForTimeout( 300 );
	return { ctx, page, errors };
}

// توصیف عنصر فوکوس‌شده؛ inside: انتخابگر ظرفی که باید فوکوس در آن بماند
const where = ( page ) => page.evaluate( () => {
	const e = document.activeElement;
	if ( ! e || e === document.body ) return 'body';
	return e.tagName.toLowerCase() + ( e.id ? `#${ e.id }` : '' ) + ( e.classList.length ? `.${ [ ...e.classList ].slice( 0, 2 ).join( '.' ) }` : '' );
} );
const isIn = ( page, sel ) => page.evaluate( ( s ) => !! document.activeElement?.closest( s ), sel );

// Tab می‌زند تا n بار و فهرست عنصرهای فوکوس‌شده را برمی‌گرداند
async function tabs( page, n, shift = false ) {
	const seq = [];
	for ( let i = 0; i < n; i++ ) {
		await page.keyboard.press( shift ? 'Shift+Tab' : 'Tab' );
		seq.push( await where( page ) );
	}
	return seq;
}

const SCENARIOS = {
	// ───────────── منوی موبایل و زیرمنو ─────────────
	async 'mobile-menu'() {
		const { ctx, page, errors } = await open( build( {} ), 390, 844 );
		log( errors.length === 0, 'mobile-menu: بدون خطای JS', errors.join( '|' ) );

		// ۱. منوی بسته: هیچ لینکی از آن نباید با Tab قابل رسیدن باشد (aria-hidden=true)
		await page.focus( '#mobileMenuTrigger' );
		const closed = await page.evaluate( () => ( {
			hidden: document.getElementById( 'mainNavWrapper' ).getAttribute( 'aria-hidden' ),
			expanded: document.getElementById( 'mobileMenuTrigger' ).getAttribute( 'aria-expanded' ),
		} ) );
		log( closed.hidden === 'true' && closed.expanded === 'false', 'mobile-menu: وضعیت بسته (aria-hidden=true، aria-expanded=false)', JSON.stringify( closed ) );
		const seqClosed = await tabs( page, 6 );
		const leaked = seqClosed.filter( ( s ) => /menu-item|submenu-toggle|main-nav|main-menu/.test( s ) || s.startsWith( 'a' ) && false );
		const reached = await page.evaluate( () => {
			const nav = document.getElementById( 'mainNavWrapper' );
			return [ ...nav.querySelectorAll( 'a[href], button' ) ].filter( ( el ) => el.checkVisibility( { visibilityProperty: true } ) && getComputedStyle( el ).visibility !== 'hidden' ).length;
		} );
		log( reached === 0, 'mobile-menu: منوی بسته عنصر قابل‌فوکوس/قابل‌دیدن ندارد (وگرنه Tab روی عنصر aria-hidden می‌رود)', `${ reached } عنصر؛ ترتیب Tab: ${ seqClosed.join( ' ← ' ) }${ leaked.length ? ' | نشت: ' + leaked : '' }` );

		// ۲. باز کردن با Enter
		await page.focus( '#mobileMenuTrigger' );
		await page.keyboard.press( 'Enter' );
		await page.waitForTimeout( 350 );
		const open1 = await page.evaluate( () => ( {
			hidden: document.getElementById( 'mainNavWrapper' ).getAttribute( 'aria-hidden' ),
			expanded: document.getElementById( 'mobileMenuTrigger' ).getAttribute( 'aria-expanded' ),
			label: document.getElementById( 'mobileMenuTrigger' ).getAttribute( 'aria-label' ),
			cls: document.getElementById( 'mainNavWrapper' ).classList.contains( 'is-open' ),
		} ) );
		log( open1.hidden === 'false' && open1.expanded === 'true' && open1.cls, 'mobile-menu: باز با Enter — aria-hidden=false، aria-expanded=true', JSON.stringify( open1 ) );

		// ۳. Tab بعدی باید به اولین لینک منو برود (نه به لوگو یا جای دیگر پشت منو)
		const seqOpen = await tabs( page, 3 );
		const firstInNav = await page.evaluate( () => !! document.activeElement.closest( '#mainNavWrapper' ) );
		log( seqOpen[ 0 ] !== 'body', 'mobile-menu: بعد از باز شدن، Tab به عنصر قابل‌فوکوس می‌رسد', seqOpen.join( ' ← ' ) );
		await page.focus( '#mobileMenuTrigger' );
		await page.keyboard.press( 'Tab' );
		const afterToggle = await where( page );
		log( await isIn( page, '#mainNavWrapper' ), 'mobile-menu: Tab بعد از دکمه همبرگر وارد منوی باز می‌شود (ترتیب منطقی فوکوس، ۲٫۴٫۳)', afterToggle );

		// ۴. زیرمنو با Enter روی دکمه‌اش
		const sub = page.locator( '#mainNavWrapper .submenu-toggle' ).first();
		await sub.focus();
		await page.keyboard.press( 'Enter' );
		await page.waitForTimeout( 250 );
		const subState = await page.evaluate( () => {
			const b = document.querySelector( '#mainNavWrapper .submenu-toggle' );
			const ul = b.parentElement.querySelector( ':scope > .sub-menu' );
			return { expanded: b.getAttribute( 'aria-expanded' ), controls: b.getAttribute( 'aria-controls' ), id: ul?.id, visible: ul?.checkVisibility( { visibilityProperty: true } ) };
		} );
		log( subState.expanded === 'true' && subState.visible && subState.controls === subState.id, 'mobile-menu: زیرمنو با Enter — aria-expanded=true، دیده می‌شود، aria-controls درست', JSON.stringify( subState ) );
		await page.keyboard.press( 'Tab' );
		log( await isIn( page, '.sub-menu' ), 'mobile-menu: Tab بعد از دکمه زیرمنوی باز، به لینک‌های زیرمنو می‌رود', await where( page ) );

		// ۵. زیرمنوی بسته: لینک‌هایش نباید قابل‌فوکوس باشند
		const hiddenSub = await page.evaluate( () => {
			const items = [ ...document.querySelectorAll( '#mainNavWrapper .menu-item-has-children' ) ].filter( ( li ) => ! li.classList.contains( 'submenu-active' ) );
			return items.flatMap( ( li ) => [ ...li.querySelectorAll( ':scope > .sub-menu a' ) ] ).filter( ( a ) => a.checkVisibility( { visibilityProperty: true } ) ).length;
		} );
		log( hiddenSub === 0, 'mobile-menu: لینک زیرمنوهای بسته پنهان‌اند (قابل‌فوکوس نیستند)', `${ hiddenSub } لینک` );

		// ۶. Esc: بستن + برگشت فوکوس به همبرگر
		await page.keyboard.press( 'Escape' );
		await page.waitForTimeout( 350 );
		const esc = await page.evaluate( () => ( { expanded: document.getElementById( 'mobileMenuTrigger' ).getAttribute( 'aria-expanded' ), hidden: document.getElementById( 'mainNavWrapper' ).getAttribute( 'aria-hidden' ), active: document.activeElement.id } ) );
		log( esc.expanded === 'false' && esc.hidden === 'true' && esc.active === 'mobileMenuTrigger', 'mobile-menu: Esc منو را می‌بندد و فوکوس به همبرگر برمی‌گردد', JSON.stringify( esc ) );

		// ۷. Tab از آخرین لینک منوی باز: به کجا می‌رود؟ (منوی تمام‌صفحه نباید فوکوس را زیر خودش رها کند)
		await page.focus( '#mobileMenuTrigger' );
		await page.keyboard.press( 'Enter' );
		await page.waitForTimeout( 350 );
		const navCount = await page.evaluate( () => [ ...document.querySelectorAll( '#mainNavWrapper a[href], #mainNavWrapper button' ) ].filter( ( el ) => el.checkVisibility( { visibilityProperty: true } ) ).length );
		const seq = await tabs( page, navCount + 2 );
		const outsideAfter = seq.slice( navCount ).filter( ( s ) => true );
		const coversPage = await page.evaluate( () => {
			const r = document.getElementById( 'mainNavWrapper' ).getBoundingClientRect();
			return { w: Math.round( r.width ), h: Math.round( r.height ), vw: innerWidth, vh: innerHeight, bodyClass: document.body.className };
		} );
		console.log( `     ℹ پس از ${ navCount } لینک منو، Tab به: ${ outsideAfter.join( ' ← ' ) } | اندازه منو ${ coversPage.w }×${ coversPage.h } از ${ coversPage.vw }×${ coversPage.vh }` );

		// ۸. تغییر به دسکتاپ با منوی باز: حالت پاک شود
		await page.setViewportSize( { width: 1300, height: 800 } );
		await page.waitForTimeout( 400 );
		const desk = await page.evaluate( () => ( { open: document.getElementById( 'mainNavWrapper' ).classList.contains( 'is-open' ), hidden: document.getElementById( 'mainNavWrapper' ).getAttribute( 'aria-hidden' ), body: document.body.classList.contains( 'menu-is-open' ) } ) );
		log( ! desk.open && desk.hidden === null && ! desk.body, 'mobile-menu: عبور به دسکتاپ با منوی باز — حالت بسته و aria-hidden برداشته می‌شود', JSON.stringify( desk ) );
		await ctx.close();
	},

	// ───────────── منوی دسکتاپ با صفحه‌کلید ─────────────
	async 'desktop-menu'() {
		const { ctx, page } = await open( build( {} ), 1300, 800 );
		await page.focus( '#mainNavWrapper a' );
		const trace = [];
		for ( let i = 0; i < 9; i++ ) {
			const info = await page.evaluate( () => {
				const e = document.activeElement;
				const r = e.getBoundingClientRect();
				const visible = e.checkVisibility( { visibilityProperty: true, opacityProperty: true } ) && r.width > 0 && r.height > 0 && r.bottom > 0 && r.top < innerHeight;
				const top = document.elementFromPoint( r.left + r.width / 2, r.top + r.height / 2 );
				return { name: e.tagName.toLowerCase() + ( e.className ? '.' + String( e.className ).split( ' ' )[ 0 ] : '' ) + ':' + ( e.textContent.trim().slice( 0, 12 ) || e.getAttribute( 'aria-label' ) || '' ), visible, covered: top && top !== e && ! e.contains( top ) && ! top.contains( e ) };
			} );
			trace.push( info );
			await page.keyboard.press( 'Tab' );
		}
		const bad = trace.filter( ( t ) => ! t.visible || t.covered );
		log( bad.length === 0, 'desktop-menu: هر عنصر فوکوس‌شده دیده می‌شود و پوشیده نیست', bad.map( ( b ) => `${ b.name } ${ ! b.visible ? '(نامرئی)' : '(پوشیده)' }` ).join( ' | ' ) );
		// زیرمنو با فوکوس باز می‌شود؟
		await page.focus( '.main-menu > .menu-item-has-children > a' );
		await page.waitForTimeout( 300 );
		await page.keyboard.press( 'Tab' ); // دکمه زیرمنو (دسکتاپ پنهان) یا لینک اول
		await page.keyboard.press( 'Tab' );
		const sub = await page.evaluate( () => {
			const e = document.activeElement;
			return { insideSub: !! e.closest( '.sub-menu' ), visible: e.checkVisibility( { visibilityProperty: true, opacityProperty: true } ), name: e.textContent.trim().slice( 0, 15 ) };
		} );
		log( sub.insideSub ? sub.visible : true, 'desktop-menu: لینک زیرمنو با Tab به دید می‌آید (آشکارشدن با focus-within)', JSON.stringify( sub ) );
		await ctx.close();
	},

	// ───────────── جستجوی زنده ─────────────
	async 'search'() {
		const { ctx, page, errors } = await open( build( {} ), 1300, 800 );
		log( errors.length === 0, 'search: بدون خطای JS', errors.join( '|' ) );
		const input = page.locator( '.woo-live-search__input' ).first();
		await input.focus();
		await page.keyboard.type( 'گل', { delay: 30 } );
		await page.waitForSelector( '.woo-search-results.is-open', { timeout: 3000 } ).catch( () => {} );
		const st = await page.evaluate( () => {
			const i = document.querySelector( '.woo-live-search__input' );
			const r = document.querySelector( '.woo-search-results' );
			return { expanded: i.getAttribute( 'aria-expanded' ), open: r.classList.contains( 'is-open' ), options: r.querySelectorAll( '[role=option]' ).length, children: [ ...r.children ].map( ( c ) => c.getAttribute( 'role' ) || c.tagName.toLowerCase() ) };
		} );
		log( st.open && st.expanded === 'true' && st.options === 4, 'search: نتایج باز می‌شوند و aria-expanded=true', JSON.stringify( st.children ) );
		const nonOptions = st.children.filter( ( c ) => c !== 'option' );
		log( nonOptions.length === 0, 'search: همه فرزندان listbox گزینه‌اند (۴٫۱٫۲ — «مشاهده همه نتایج» نباید داخل listbox بدون نقش option باشد)', nonOptions.join( ',' ) );
		const live = await page.evaluate( () => document.querySelector( '.woo-live-search [role=status]' )?.textContent.trim() ?? null );
		log( !! live, 'search: تعداد نتایج برای صفحه‌خوان اعلام می‌شود (۴٫۱٫۳ — ناحیه role=status بیرون از listbox)', live ?? 'هیچ ناحیه اعلام وضعیتی نیست' );
		await page.addScriptTag( { path: axePath } );
		const axeRun = async () => ( await page.evaluate( () => axe.run( '.woo-live-search', { runOnly: [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice' ] } ) ) ).violations.map( ( v ) => `${ v.id }(${ v.nodes.length })` );
		const v1 = await axeRun();
		log( v1.length === 0, 'search: axe روی جستجوی باز با نتایج', v1.join( ',' ) );

		await page.keyboard.press( 'ArrowDown' );
		await page.keyboard.press( 'ArrowDown' );
		const ad = await page.evaluate( () => {
			const i = document.querySelector( '.woo-live-search__input' );
			const id = i.getAttribute( 'aria-activedescendant' );
			const t = id && document.getElementById( id );
			return { id, exists: !! t, selected: t?.getAttribute( 'aria-selected' ), focusStill: document.activeElement === i, activeCount: document.querySelectorAll( '.woo-search-results__item.is-active' ).length };
		} );
		log( ad.exists && ad.selected === 'true' && ad.focusStill && ad.activeCount === 1, 'search: ArrowDown — aria-activedescendant به گزینه موجود اشاره می‌کند، aria-selected=true، فوکوس روی ورودی', JSON.stringify( ad ) );
		await page.keyboard.press( 'ArrowUp' );
		await page.keyboard.press( 'ArrowUp' );
		await page.keyboard.press( 'ArrowUp' );
		const wrap = await page.evaluate( () => document.querySelector( '.woo-live-search__input' ).getAttribute( 'aria-activedescendant' ) );
		log( !! wrap, 'search: ArrowUp چرخشی کار می‌کند', wrap );

		// Tab از ورودی: به دکمه پاک‌کردن یا به نتایج؟
		await page.keyboard.press( 'Tab' );
		const t1 = await where( page );
		const t1open = await page.evaluate( () => document.querySelector( '.woo-search-results' ).classList.contains( 'is-open' ) );
		await page.keyboard.press( 'Tab' );
		const t2 = await where( page );
		console.log( `     ℹ Tab از ورودی: ${ t1 } (لیست ${ t1open ? 'باز' : 'بسته' }) ← ${ t2 }` );

		// Esc: بستن، فوکوس روی ورودی
		await input.focus();
		await page.keyboard.press( 'Escape' );
		const esc = await page.evaluate( () => ( { expanded: document.querySelector( '.woo-live-search__input' ).getAttribute( 'aria-expanded' ), ad: document.querySelector( '.woo-live-search__input' ).hasAttribute( 'aria-activedescendant' ), focus: document.activeElement.className } ) );
		log( esc.expanded === 'false' && ! esc.ad && /woo-live-search__input/.test( esc.focus ), 'search: Esc لیست را می‌بندد و فوکوس روی ورودی می‌ماند', JSON.stringify( esc ) );

		// فوکوس دوباره: لیست دوباره باز می‌شود؟
		await page.locator( '#mobileMenuTrigger' ).evaluate( ( el ) => el.focus() ).catch( () => {} );
		await input.focus();
		const refocus = await page.evaluate( () => document.querySelector( '.woo-live-search__input' ).getAttribute( 'aria-expanded' ) );
		console.log( `     ℹ بعد از Esc و فوکوس دوباره: aria-expanded=${ refocus }` );

		// نتیجه خالی: پیام دیداری + اعلام وضعیت، و axe
		await page.route( '**/admin-ajax.php*', ( r ) => r.fulfill( { status: 200, contentType: 'application/json', body: '[]' } ) );
		await input.fill( '' );
		await page.keyboard.type( 'قق', { delay: 30 } );
		await page.waitForSelector( '.woo-search-results__message', { timeout: 3000 } ).catch( () => {} );
		const empty = await page.evaluate( () => document.querySelector( '.woo-live-search [role=status]' )?.textContent.trim() );
		log( empty === 'محصولی یافت نشد', 'search: نتیجه خالی اعلام می‌شود', empty );
		const v2 = await axeRun();
		log( v2.length === 0, 'search: axe روی حالت «محصولی یافت نشد»', v2.join( ',' ) );
		await ctx.close();
	},

	// ───────────── پنجره پشتیبانی ─────────────
	async 'support-dialog'() {
		for ( const width of [ 1300, 390 ] ) {
			const { ctx, page, errors } = await open( build( { support: true } ), width, 800 );
			const tag = `support-dialog@${ width }`;
			log( errors.length === 0, `${ tag }: بدون خطای JS`, errors.join( '|' ) );
			const trig = page.locator( '#supportTrigger' );
			await trig.focus();
			await page.keyboard.press( 'Enter' );
			await page.waitForTimeout( 500 );
			const o = await page.evaluate( () => ( { open: document.getElementById( 'supportOverlay' ).open, active: document.activeElement.className, inDialog: !! document.activeElement.closest( 'dialog' ) } ) );
			log( o.open && o.inDialog, `${ tag }: باز با Enter و فوکوس داخل پنجره`, `${ o.active }` );
			// <dialog> بومی: Tab از آخرین عنصر به رابط مرورگر (در ابزار تست: body) می‌رود و برمی‌گردد؛ استاندارد است
			const seq = await tabs( page, 8 );
			const leakedFwd = seq.filter( ( x ) => x !== 'body' && ! /popup__/.test( x ) );
			log( leakedFwd.length === 0, `${ tag }: ۸ بار Tab — فوکوس به صفحه پشت پنجره نمی‌رود`, seq.join( ' ← ' ) );
			const back = await tabs( page, 8, true );
			log( back.every( ( x ) => x === 'body' || /popup__/.test( x ) ), `${ tag }: ۸ بار Shift+Tab — به صفحه پشت پنجره نمی‌رود`, back.join( ' ← ' ) );
			await page.keyboard.press( 'Escape' );
			await page.waitForTimeout( 200 );
			const c = await page.evaluate( () => ( { open: document.getElementById( 'supportOverlay' ).open, active: document.activeElement.id } ) );
			log( ! c.open && c.active === 'supportTrigger', `${ tag }: Esc می‌بندد و فوکوس به دکمه پشتیبانی برمی‌گردد`, JSON.stringify( c ) );
			// دوباره باز و بستن با دکمه ×
			await page.keyboard.press( 'Enter' );
			await page.waitForTimeout( 400 );
			await page.click( '#supportClose' );
			const c2 = await page.evaluate( () => ( { open: document.getElementById( 'supportOverlay' ).open, active: document.activeElement.id } ) );
			log( ! c2.open && c2.active === 'supportTrigger', `${ tag }: دکمه × می‌بندد و فوکوس برمی‌گردد`, JSON.stringify( c2 ) );
			await ctx.close();
		}
	},

	// ───────────── پخش‌کننده استوری ─────────────
	async 'story-player'() {
		for ( const width of [ 390, 1300 ] ) {
			const { ctx, page, errors } = await open( build( { story: true } ), width, 800 );
			const tag = `story@${ width }`;
			log( errors.length === 0, `${ tag }: بدون خطای JS`, errors.join( '|' ) );
			const m0 = await page.evaluate( () => ( { hidden: document.getElementById( 'hs-video-modal' ).getAttribute( 'aria-hidden' ), display: getComputedStyle( document.getElementById( 'hs-video-modal' ) ).display, vis: getComputedStyle( document.getElementById( 'hs-video-modal' ) ).visibility } ) );
			const hiddenButFocusable = await page.evaluate( () => [ ...document.querySelectorAll( '#hs-video-modal button, #hs-video-modal a[href]' ) ].filter( ( e ) => e.checkVisibility( { visibilityProperty: true } ) ).length );
			log( m0.hidden === 'true' && hiddenButFocusable === 0, `${ tag }: بسته — aria-hidden=true و هیچ کنترل قابل‌دیدنی داخلش نیست`, JSON.stringify( m0 ) + ` قابل‌دیدن=${ hiddenButFocusable }` );

			const link = page.locator( '.story-link' ).nth( 1 );
			await link.focus();
			await page.keyboard.press( 'Enter' );
			await page.waitForTimeout( 900 );
			const o = await page.evaluate( () => ( { hidden: document.getElementById( 'hs-video-modal' ).getAttribute( 'aria-hidden' ), open: document.getElementById( 'hs-video-modal' ).classList.contains( 'is-open' ), active: document.activeElement.className, inModal: !! document.activeElement.closest( '#hs-video-modal' ), title: document.getElementById( 'hs-story-title' ).textContent, pos: document.getElementById( 'hs-story-position' ).textContent, overflow: document.body.style.overflow } ) );
			log( o.open && o.hidden === 'false' && o.inModal, `${ tag }: باز با Enter روی لینک استوری — aria-hidden=false و فوکوس داخل پنجره`, `${ o.active } | ${ o.title } ${ o.pos }` );

			// تله فوکوس
			const fwd = await tabs( page, 25 );
			const outF = fwd.filter( ( s ) => ! /hs-|^video|^a#hs/.test( s ) );
			log( outF.length === 0, `${ tag }: ۲۵ بار Tab — فوکوس هرگز از پنجره بیرون نمی‌رود`, outF.length ? `بیرون رفت: ${ [ ...new Set( outF ) ].join( ', ' ) }` : [ ...new Set( fwd ) ].join( ' ← ' ) );
			const bwd = await tabs( page, 25, true );
			const outB = bwd.filter( ( s ) => ! /hs-|^video|^a#hs/.test( s ) );
			log( outB.length === 0, `${ tag }: ۲۵ بار Shift+Tab — داخل پنجره می‌ماند`, outB.length ? `بیرون رفت: ${ [ ...new Set( outB ) ].join( ', ' ) }` : '' );

			// دکمه‌ای که غیرفعال می‌شود وقتی فوکوس رویش است (آخرین استوری ← «بعدی»)
			await page.evaluate( () => document.querySelector( '.hs-nav--next' ).focus() );
			await page.keyboard.press( 'Enter' ); // استوری ۲ ← ۳ (آخر)
			await page.waitForTimeout( 500 );
			const dis = await page.evaluate( () => ( { disabled: document.querySelector( '.hs-nav--next' ).disabled, active: document.activeElement === document.body ? 'body' : document.activeElement.className } ) );
			log( ! ( dis.disabled && dis.active === 'body' ), `${ tag }: فوکوس روی «بعدی» که در آخرین استوری غیرفعال می‌شود گم نمی‌شود`, JSON.stringify( dis ) );
			if ( dis.active === 'body' ) {
				const after = await tabs( page, 3 );
				log( after.every( ( s ) => /hs-|^video|^a#hs/.test( s ) ), `${ tag }: بعد از گم‌شدن فوکوس، Tab باز هم داخل پنجره می‌ماند`, after.join( ' ← ' ) );
			}
			// پیکان‌ها و فاصله
			await page.evaluate( () => document.querySelector( '.hs-modal__close' ).focus() );
			// Esc
			await page.keyboard.press( 'Escape' );
			await page.waitForTimeout( 600 );
			const c = await page.evaluate( () => ( { hidden: document.getElementById( 'hs-video-modal' ).getAttribute( 'aria-hidden' ), display: document.getElementById( 'hs-video-modal' ).style.display, active: document.activeElement.className, back: document.activeElement.dataset.storyId, overflow: document.body.style.overflow } ) );
			log( c.hidden === 'true' && c.display === 'none' && c.back === '2', `${ tag }: Esc می‌بندد، aria-hidden=true و فوکوس به لینک استوری برمی‌گردد`, JSON.stringify( c ) );

			// بازکردن دوباره و بستن با ×، سپس باز با کلیک
			await link.click();
			await page.waitForTimeout( 700 );
			await page.keyboard.press( 'Tab' );
			const insideAfterClick = await isIn( page, '#hs-video-modal' );
			log( insideAfterClick, `${ tag }: باز با کلیک موس — Tab داخل پنجره است`, await where( page ) );
			await page.click( '.hs-modal__close' );
			await page.waitForTimeout( 600 );
			const c2 = await page.evaluate( () => ( { hidden: document.getElementById( 'hs-video-modal' ).getAttribute( 'aria-hidden' ), back: document.activeElement.dataset.storyId } ) );
			log( c2.hidden === 'true' && c2.back === '2', `${ tag }: دکمه × می‌بندد و فوکوس برمی‌گردد`, JSON.stringify( c2 ) );
			await ctx.close();
		}
	},

	// ───────────── پاپ‌آپ اعلان (<dialog> بومی؛ نسخه ۴) ─────────────
	async 'notification'() {
		const state = () => {
			const d = document.querySelector( 'dialog.hd-notification-popup' );
			const r = d.getBoundingClientRect();
			const a = document.activeElement;
			return {
				open: d.hasAttribute( 'open' ),
				modal: d.matches( ':modal' ),
				// واقعا دیده می‌شود (تا 2.0.1 با «کاهش حرکت» opacity صفر می‌ماند)
				visible: getComputedStyle( d ).opacity === '1' && r.width > 50 && r.height > 50,
				inside: !! a?.closest( '.hd-notification-popup' ),
				active: a === document.body ? 'body' : `${ a.tagName.toLowerCase() }.${ a.className }`.slice( 0, 40 ),
			};
		};
		for ( const [ pos, width, mobileMode ] of [ [ 'center', 1300, 'modal' ], [ 'center', 390, 'modal' ], [ 'center', 390, 'corner' ], [ 'bottom_right', 1300, 'modal' ] ] ) {
			const { ctx, page, errors } = await open( build( { notification: pos, mobileMode } ), width, 800 );
			const tag = `notification-${ pos }${ mobileMode === 'corner' ? '(موبایل: گوشه)' : '' }@${ width }`;
			const modal = pos === 'center' && ! ( mobileMode === 'corner' && width <= 768 );
			log( errors.length === 0, `${ tag }: بدون خطای JS`, errors.join( '|' ) );
			await page.locator( 'a[href]' ).first().focus();
			await page.waitForTimeout( 1600 );
			const s = await page.evaluate( state );
			log( s.open && s.visible, `${ tag }: باز و واقعا دیده می‌شود (با «کاهش حرکت»)`, JSON.stringify( s ) );
			if ( modal ) {
				log( s.modal && s.inside, `${ tag }: پنجره مودال (showModal) و فوکوس داخل آن`, s.active );
				// <dialog> بومی: Tab از آخرین عنصر به رابط مرورگر (در ابزار تست: body) می‌رود و برمی‌گردد؛ استاندارد است
				const ok = ( x ) => x === 'body' || /hd-notif/.test( x );
				const seq = await tabs( page, 10 );
				log( seq.every( ok ), `${ tag }: ۱۰ بار Tab — فوکوس به صفحه پشت پنجره نمی‌رود`, [ ...new Set( seq ) ].join( ' ← ' ) );
				const bwd = await tabs( page, 10, true );
				log( bwd.every( ok ), `${ tag }: ۱۰ بار Shift+Tab — داخل می‌ماند`, [ ...new Set( bwd ) ].join( ' ← ' ) );
				const locked = await page.evaluate( () => document.documentElement.classList.contains( 'hd-notif-lock' ) );
				log( locked, `${ tag }: اسکرول صفحه پشت پنجره قفل است` );
			} else {
				log( ! s.modal && ! s.inside, `${ tag }: غیرمسدودکننده (فوکوس به آن نمی‌رود)`, s.active );
			}
			await page.keyboard.press( 'Escape' );
			await page.waitForTimeout( 700 );
			const c = await page.evaluate( state );
			const unlocked = await page.evaluate( () => ! document.documentElement.classList.contains( 'hd-notif-lock' ) );
			log( ! c.open && unlocked, `${ tag }: Esc می‌بندد`, JSON.stringify( c ) );
			if ( modal ) {
				log( c.active !== 'body', `${ tag }: فوکوس بعد از بستن به جای قبلی برمی‌گردد (نه body)`, c.active );
			}
			await ctx.close();
		}
	},
};

const names = only.length ? only : Object.keys( SCENARIOS );
for ( const name of names ) {
	if ( ! SCENARIOS[ name ] ) {
		console.error( `سناریوی ناشناخته: ${ name } (${ Object.keys( SCENARIOS ).join( ', ' ) })` );
		process.exit( 2 );
	}
	console.log( `\n■ ${ name }` );
	try {
		await SCENARIOS[ name ]();
	} catch ( e ) {
		failures++;
		console.log( `  ✘ ${ name }: خطای اجرا — ${ e.message.split( '\n' )[ 0 ] }` );
	}
}
await browser.close();
console.log( `\n${ failures ? `✘ ${ failures } نقض/خطا` : '✔ بدون نقض' }` );
process.exit( failures ? 1 : 0 );
