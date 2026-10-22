/**
 * اندازه هدف لمسی (WCAG 2.2 — 2.5.8) برای کنترل‌هایی که در صفحه‌های رندرشده ابزار تست نیستند —
 * اسکیل hodima-theme.
 *
 *   node target-size-fixtures.mjs <root>
 *
 * پنجره پشتیبانی هدر (فقط با کانال تماس تنظیم‌شده)، نقطه‌های اسلایدر صفحه اصلی، مینی‌پلیر و
 * پنجره پخش استوری (با JS ساخته می‌شوند) و پاپ‌آپ اعلان فقط وقتی پنل‌ها فعال و باز باشند رندر
 * می‌شوند؛ a11y-audit.mjs آن‌ها را نمی‌بیند. اینجا نشانه‌گذاری هرکدام از قالب PHP/JS همان
 * کنترل (همان کلاس‌ها و ویژگی‌ها) در یک صفحه کوچک با CSS واقعی قالب/افزونه ساخته، در حالت باز
 * (dialog.showModal، .is-open، .hd-show) در ۳۲۰، ۳۹۰ و ۱۳۰۰px سنجیده می‌شود.
 * محدودیت: اگر نشانه‌گذاری PHP/JS عوض شود، این نمونه‌ها باید با آن هماهنگ شوند.
 */
import { chromium } from 'playwright-core';
import fs from 'node:fs';
import path from 'node:path';
import { measureTargets } from './target-size.mjs';

const [ root ] = process.argv.slice( 2 );
if ( ! root ) {
	console.error( 'usage: target-size-fixtures.mjs <root>' );
	process.exit( 2 );
}
const MIME = { '.css': 'text/css', '.js': 'text/javascript', '.woff2': 'font/woff2', '.svg': 'image/svg+xml', '.png': 'image/png' };
const THEME = [ 'assets/css/tokens.css', 'style.css', 'assets/css/page-layout.css', 'assets/css/header.css', 'assets/css/footer.css' ];
const PLUGIN = 'wp-content/plugins/hodima-media/components';

const links = ( theme, plugin = [] ) => [
	...( theme ? THEME.map( ( f ) => `/wp-content/themes/hodima/${ f }` ) : [] ),
	...plugin.map( ( f ) => `/${ PLUGIN }/${ f }` ),
].map( ( h ) => `<link rel="stylesheet" href="${ h }">` ).join( '' );

const page = ( title, css, body ) => `<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>${ title }</title>${ css }</head><body>${ body }</body></html>`;

const svg = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 18v-6a9 9 0 0 1 18 0v6"/></svg>';
const FIXTURES = {
	// header.php: <dialog> پشتیبانی با سه کانال
	'support-dialog': {
		html: page( 'support', links( true ), `<dialog class="overlay" id="supportOverlay" aria-labelledby="supportTitle"><article class="popup"><button class="popup__close" id="supportClose" type="button" aria-label="بستن پنجره پشتیبانی">&times;</button><h3 class="popup__title" id="supportTitle">ارتباط با ما</h3>${ [ 'whatsapp', 'telegram', 'phone' ].map( ( c ) => `<a href="#" class="popup__link popup__link--${ c }" aria-label="${ c }">${ svg } ${ c }</a>` ).join( '' ) }</article></dialog><button class="btn-support" id="supportTrigger" type="button" aria-label="باز کردن پنجره پشتیبانی" aria-haspopup="dialog">${ svg }<span>پشتیبانی</span></button>` ),
		open: () => document.getElementById( 'supportOverlay' ).showModal(),
	},
	// hodima-slider/includes/Frontend.php: نقطه‌های اسلایدر
	'slider-dots': {
		html: page( 'slider', links( false, [ 'hodima-slider/assets/front.css' ] ), `<div class="h-slider" style="min-height:200px;position:relative"><div class="h-slide active">1</div><div class="h-controls"><div class="h-dots">${ [ 0, 1, 2, 3, 4 ].map( ( i ) => `<button type="button" class="h-dot${ i ? '' : ' active' }" aria-label="رفتن به اسلاید ${ i + 1 }" aria-current="${ i ? 'false' : 'true' }" data-index="${ i }"></button>` ).join( '' ) }</div></div></div>` ),
	},
	// hodima-mini-player.js
	'mini-player': {
		html: page( 'mini', links( false, [ 'hodima-Stories/hodima-StoryPlayer/hodima-mini-player.css' ] ), '<div class="hs-mini-player"><video class="hs-mini-player__video" playsinline></video><span class="hs-mini-player__mute" aria-hidden="true">🔇</span><button type="button" class="hs-mini-player__close" aria-label="بستن">×</button></div>' ),
	},
	// hodima-StoryPlayer.php + hodima-player.js (نوار پیشرفت ۵ استوری)
	'story-modal': {
		html: page( 'story', links( false, [ 'hodima-Stories/hodima-StoryPlayer/hodima-player.css' ] ), `<div id="hs-video-modal" class="hs-modal is-open" aria-hidden="false" role="dialog" aria-modal="true" aria-label="پخش کننده استوری"><div class="hs-modal__overlay" data-close="1"></div><div class="hs-modal__content" role="document" tabindex="-1"><button class="hs-modal__close hs-flex-center" type="button" aria-label="بستن"><span aria-hidden="true">&times;</span></button><div class="hs-player"><div class="hs-topbar"><div class="hs-progress-wrap" role="group" aria-label="پیشرفت استوری‌ها">${ [ 1, 2, 3, 4, 5 ].map( ( i ) => `<button type="button" class="hs-progress-item" data-index="${ i }" aria-label="استوری ${ i }"><span class="hs-progress-fill"></span></button>` ).join( '' ) }</div><div class="hs-meta"><button class="hs-mute-toggle hs-flex-center" type="button" aria-pressed="false" aria-label="بی‌صدا"><svg class="hs-icon-sound" viewBox="0 0 24 24" width="20" height="20"><path d="M11 5 6 9H2v6h4l5 4z"/></svg></button><div class="hs-meta__text"><div class="hs-title">عنوان</div><div class="hs-subtitle">۱ از ۵</div></div></div></div><div class="hs-stage"><video playsinline></video><button class="hs-nav hs-flex-center hs-nav--prev" type="button" aria-label="استوری قبلی"><span aria-hidden="true">&#10094;</span></button><button class="hs-nav hs-flex-center hs-nav--next" type="button" aria-label="استوری بعدی"><span aria-hidden="true">&#10095;</span></button><div class="hs-center-control hs-flex-center"><button class="hs-play-toggle hs-flex-center" type="button" aria-label="پخش / توقف"><span class="hs-play-icon hs-icon-play" aria-hidden="true">▶</span></button></div></div><div class="hs-bottombar"><div class="hs-actions hs-flex-center"><button class="hs-action-item hs-flex-center hs-share-btn" type="button" aria-label="اشتراک گذاری"><span>اشتراک</span></button><a href="#" id="hs-cta-box" class="hs-cta-box"><span class="hs-cta-text">مشاهده</span></a></div></div></div></div></div>` ),
	},
	// notification-front.php (نسخه ۴، <dialog>): پاپ‌آپ وسط، گوشه پایین
	'notification-center': {
		html: page( 'notif', links( false, [ 'notification/assets/css/notification-front.css' ] ), '<dialog open class="hd-notification-popup hd-pos-center" aria-label="اطلاعیه" style="--hd-notif-w:400px"><button type="button" class="hd-notif-close" aria-label="بستن"><span aria-hidden="true">&times;</span></button><div class="hd-notif-main-area"><a class="hd-notif-half-link" href="#"><img alt="اطلاعیه" src="data:image/gif;base64,R0lGODlhAQABAAAAACw=" width="400" height="300"></a></div></dialog>' ),
	},
	'notification-corner': {
		html: page( 'notif', links( false, [ 'notification/assets/css/notification-front.css' ] ), '<dialog open class="hd-notification-popup hd-pos-bottom_right" aria-label="اطلاعیه" style="--hd-notif-w:350px"><button type="button" class="hd-notif-close" aria-label="بستن"><span aria-hidden="true">&times;</span></button><div class="hd-notif-main-area"><a class="hd-notif-half-link" href="#"><img alt="اطلاعیه" src="data:image/gif;base64,R0lGODlhAQABAAAAACw=" width="350" height="150"></a></div></dialog>' ),
	},
};

const localFile = ( url ) => {
	const u = new URL( url );
	if ( u.hostname !== 'hodima.test' ) return null;
	const p = decodeURIComponent( u.pathname );
	if ( p.startsWith( '/wp-content/themes/hodima/' ) ) return path.join( root, 'hodima', p.slice( '/wp-content/themes/hodima/'.length ) );
	const m = p.match( /^\/wp-content\/plugins\/(hodima-[a-z]+)\/(.*)$/ );
	return m ? path.join( root, 'plugins', m[ 1 ], m[ 2 ] ) : null;
};

const browser = await chromium.launch( { executablePath: process.env.CHROMIUM || '/opt/pw-browsers/chromium' } ).catch( () => chromium.launch() );
const rows = [];
for ( const [ name, fx ] of Object.entries( FIXTURES ) ) {
	for ( const width of [ 320, 390, 1300 ] ) {
		const ctx = await browser.newContext( { viewport: { width, height: 800 }, locale: 'fa-IR', reducedMotion: 'reduce' } );
		const pg = await ctx.newPage();
		await pg.route( '**/*', async ( route ) => {
			const url = route.request().url();
			if ( url === 'https://hodima.test/__page__' ) return route.fulfill( { status: 200, contentType: 'text/html; charset=utf-8', body: fx.html } );
			const file = localFile( url );
			if ( file && fs.existsSync( file ) && fs.statSync( file ).isFile() ) return route.fulfill( { status: 200, contentType: MIME[ path.extname( file ) ] || 'application/octet-stream', body: fs.readFileSync( file ) } );
			return route.fulfill( { status: 404, body: '' } );
		} );
		await pg.goto( 'https://hodima.test/__page__', { waitUntil: 'load' } );
		if ( fx.open ) await pg.evaluate( fx.open );
		await pg.waitForTimeout( 400 );
		const found = await pg.evaluate( measureTargets );
		const total = await pg.evaluate( () => document.querySelectorAll( 'a[href],button,input,select,textarea' ).length );
		console.log( `${ found.length ? '✘' : '✔' } ${ name }@${ width } — ${ total } کنترل، ${ found.length } نقض` );
		for ( const f of found ) console.log( `     ${ f.w }×${ f.h }  ${ f.label }  ← نزدیک ${ f.near }` );
		rows.push( ...found.map( ( f ) => ( { name, width, ...f } ) ) );
		await ctx.close();
	}
}
await browser.close();
process.exit( rows.length ? 1 : 0 );
