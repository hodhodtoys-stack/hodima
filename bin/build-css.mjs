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
const kb = ( n ) => `${ ( n / 1024 ).toFixed( 0 ) }KB`;
console.log( `✔ CSS قالب ${ check ? 'بررسی شد' : 'ساخته شد' }: ${ kb( before ) } → ${ kb( after ) }` );
