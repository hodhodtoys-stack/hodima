#!/usr/bin/env node
/**
 * مرحله ساخت CSS قالب (نوسازی قالب، مرحله ۵ — تصمیم کاربر «مدرن کن»، بخش ۵۷.۴)
 *
 *   node bin/build-css.mjs <پوشه قالب کپی‌شده>      (bin/build.sh صدا می‌زند)
 *   node bin/build-css.mjs --check                 (فقط بررسی: سورس قالب ساخته می‌شود ولی چیزی نوشته نمی‌شود)
 *
 * سورس CSS در مخزن مدرن نوشته می‌شود (nesting، نگارش بازه‌ای @media مثل
 * `width <= 768px`، color-mix با رنگ ثابت، …) و همین سورس مستقیم در ابزار
 * تست و مرورگرهای جدید کار می‌کند. این اسکریپت روی *کپی* قالب در dist/ هر
 * فایل CSS را با Lightning CSS برای مرورگرهای فهرست «browserslist» در
 * package.json پایین می‌آورد (مرورگر قدیمی کل قانون nested را نادیده می‌گیرد)
 * و minify می‌کند؛ ZIP نصبی همین خروجی را دارد. نام فایل‌ها عوض نمی‌شود، پس
 * PHP (hodima_enqueue_asset) دست نمی‌خورد.
 *
 * style.css: سربرگ قالب (Theme Name، Version، …) که وردپرس از خود فایل
 * می‌خواند، دست‌نخورده بالای خروجی می‌ماند.
 *
 * مرحله ۸: CSS مشترک همه صفحه‌ها (HODIMA_COMMON_CSS در inc/enqueue.php) در
 * ZIP یک فایل هم می‌شود (assets/css/hodima-common.css)؛ فایل‌های جدا هم می‌مانند.
 */
import { readFileSync, writeFileSync, readdirSync, statSync } from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { transform, browserslistToTargets, Features } from 'lightningcss';
import browserslist from 'browserslist';

const root = path.resolve( path.dirname( fileURLToPath( import.meta.url ) ), '..' );
const check = process.argv[ 2 ] === '--check';
const dir = check ? path.join( root, 'hodima' ) : path.resolve( process.argv[ 2 ] ?? '' );

if ( ! process.argv[ 2 ] || ! statSync( dir, { throwIfNoEntry: false } )?.isDirectory() ) {
	console.error( 'استفاده: node bin/build-css.mjs <پوشه قالب> | --check' );
	process.exit( 2 );
}

// مرورگرهای هدف از package.json → browserslist
const targets = browserslistToTargets( browserslist( undefined, { path: root } ) );

/*
 * تبدیل‌هایی که specificity انتخابگر را عوض می‌کنند ممنوع: خصوصیات منطقی
 * (inset-inline-start، border-end-start-radius…) برای مرورگر قدیمی به
 * :lang(fa…)/:dir() و :is() به :-webkit-any() تبدیل می‌شوند و انتخابگر
 * سنگین‌تر در مرورگرهای *جدید* هم قانون دیگری را می‌برد (ظاهر عوض می‌شد).
 * مرورگری که این‌ها را نشناسد همان قانون را نادیده می‌گیرد، مثل قبل از ساخت.
 */
const exclude = Features.LogicalProperties | Features.IsSelector | Features.DirSelector | Features.LangSelectorList;

/** همه فایل‌های .css پوشه (بازگشتی). */
const cssFiles = ( base ) => readdirSync( base, { recursive: true } )
	.map( ( rel ) => path.join( base, String( rel ) ) )
	.filter( ( file ) => file.endsWith( '.css' ) && statSync( file ).isFile() )
	.sort();

let before = 0;
let after = 0;
let failed = 0;
const built = new Map(); // مسیر نسبی → CSS ساخته‌شده (بدون سربرگ style.css)

for ( const file of cssFiles( dir ) ) {
	const rel = path.relative( dir, file );
	const source = readFileSync( file, 'utf8' );

	// سربرگ style.css قالب: اولین کامنت، همان‌طور که هست
	const header = 'style.css' === rel ? ( source.match( /^\s*\/\*[\s\S]*?\*\// )?.[ 0 ].trim() ?? '' ) : '';

	/*
	 * بازه اکید (`width < 783px`) برای مرورگر قدیمی به `not (min-width: 783px)`
	 * تبدیل می‌شود که Chrome زیر ۱۰۴ نمی‌فهمد (کل @media نادیده)؛ فقط <= و >=
	 * که به max-width/min-width ساده تبدیل می‌شوند.
	 */
	const strict = source.match( /@media[^{]*\(\s*(?:width|height)\s*[<>](?!=)[^)]*\)/ );
	if ( strict ) {
		failed++;
		console.error( `✘ ${ rel }: «${ strict[ 0 ] }» — به‌جای < و > از <= و >= استفاده کنید (مثلا width <= 782px)` );
		continue;
	}

	/*
	 * @layer پایین آورده نمی‌شود: Safari زیر ۱۵.۴ و Chrome زیر ۹۹ (در فهرست
	 * مرورگرهای هدف) کل بلوک را نادیده می‌گیرند. به‌علاوه CSS بدون لایه
	 * ووکامرس و افزونه‌ها بر CSS لایه‌دار قالب غالب می‌شود (HODIMA-AUDIT.md ۵۷.۳، ۶۲).
	 */
	if ( /@layer\b/.test( source.replace( /\/\*[\s\S]*?\*\//g, '' ) ) ) {
		failed++;
		console.error( `✘ ${ rel }: @layer در CSS قالب فعلا ممنوع است (مرورگرهای هدف قدیمی کل بلوک را نادیده می‌گیرند)` );
		continue;
	}

	let output;
	try {
		const result = transform( {
			filename: rel,
			code: Buffer.from( header ? source.slice( source.indexOf( header ) + header.length ) : source ),
			minify: true,
			targets,
			exclude,
			errorRecovery: false,
		} );
		for ( const warning of result.warnings ) {
			console.warn( `⚠ ${ rel }:${ warning.loc.line } ${ warning.message }` );
		}
		built.set( rel.split( path.sep ).join( '/' ), result.code.toString() );
		output = ( header ? `${ header }\n` : '' ) + result.code.toString();
	} catch ( error ) {
		failed++;
		console.error( `✘ ${ rel }:${ error.loc?.line ?? '?' } ${ error.message }` );
		continue;
	}

	before += Buffer.byteLength( source );
	after += Buffer.byteLength( output );
	if ( ! check ) {
		writeFileSync( file, output );
	}
}

if ( failed ) {
	console.error( `✘ ساخت CSS: ${ failed } فایل خطا داشت` );
	process.exit( 1 );
}

/*
 * CSS مشترک همه صفحه‌ها در یک فایل (نوسازی قالب، مرحله ۸؛ HODIMA-AUDIT.md بخش ۶۵).
 * فهرست و ترتیب از HODIMA_COMMON_CSS و نام فایل از HODIMA_COMMON_CSS_BUNDLE در
 * inc/enqueue.php خوانده می‌شود (یک منبع)؛ PHP اگر این فایل باشد فقط همین را لود
 * می‌کند. آدرس‌های نسبی (فونت‌های style.css) نسبت به پوشه فایل تازه بازنویسی می‌شوند.
 */
const enqueuePhp = readFileSync( path.join( dir, 'inc/enqueue.php' ), 'utf8' );
const commonList = [ ...( enqueuePhp.match( /const HODIMA_COMMON_CSS = \[([\s\S]*?)\];/ )?.[ 1 ] ?? '' ).matchAll( /=>\s*'([^']+\.css)'/g ) ].map( ( m ) => m[ 1 ] );
const bundleRel = enqueuePhp.match( /const HODIMA_COMMON_CSS_BUNDLE = '([^']+)'/ )?.[ 1 ];
if ( commonList.length < 2 || ! bundleRel || commonList.some( ( rel ) => ! built.has( rel ) ) ) {
	console.error( `✘ CSS مشترک: فهرست HODIMA_COMMON_CSS در inc/enqueue.php خوانده نشد یا فایلی ندارد (${ commonList.join( '، ' ) })` );
	process.exit( 1 );
}
const rebase = ( css, fromRel ) => css.replace( /url\(\s*(['"]?)([^'")]+)\1\s*\)/g, ( all, quote, url ) => {
	if ( /^(?:[a-z]+:|\/|#)/i.test( url ) ) {
		return all;
	}
	const target = path.posix.normalize( path.posix.join( path.posix.dirname( fromRel ), url ) );
	return `url(${ quote }${ path.posix.relative( path.posix.dirname( bundleRel ), target ) }${ quote })`;
} );
const bundle = commonList.map( ( rel ) => rebase( built.get( rel ), rel ) ).join( '\n' );
if ( ! check ) {
	writeFileSync( path.join( dir, bundleRel ), bundle );
}
console.log( `✔ CSS مشترک (${ commonList.length } فایل → ${ bundleRel }): ${ ( Buffer.byteLength( bundle ) / 1024 ).toFixed( 0 ) }KB` );
const kb = ( n ) => `${ ( n / 1024 ).toFixed( 0 ) }KB`;
console.log( `✔ CSS قالب ${ check ? 'بررسی شد' : 'ساخته شد' }: ${ kb( before ) } → ${ kb( after ) }` );
