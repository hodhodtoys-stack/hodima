#!/usr/bin/env node
/**
 * مرحله ساخت JavaScript (پرفورمنس مرحله ۲، بخش ۱۷۴)
 *
 *   node bin/build-js.mjs <پوشه> [<پوشه> …]      (bin/build.sh روی کپی قالب و افزونه‌ها صدا می‌زند)
 *
 * سورس JS در مخزن خوانا می‌ماند؛ فقط *کپی* بسته در dist/ فایل‌های .js را با esbuild کوچک
 * می‌کند (فاصله، نام متغیرهای محلی، ساده‌سازی دستور) و ZIP نصبی همین خروجی را دارد.
 * نام فایل‌ها عوض نمی‌شود، پس PHP (enqueue) دست نمی‌خورد.
 *
 * - بدون پایین‌آوردن نگارش (target = esnext): JS قالب عمدا برای مرورگر قدیمی پایین آورده
 *   نمی‌شود (اسکیل hodima-theme)، پس esbuild هم نگارشی اضافه یا حذف نمی‌کند.
 * - فرمت مشخص نمی‌شود: esbuild نام‌های سطح بالای فایل (متغیرها و توابع سراسری) را عوض نمی‌کند؛
 *   اسکریپت‌های کلاسیک به هم با همین نام‌ها و با window.* وصل‌اند.
 * - توضیح‌های حقوقی (کامنت‌هایی که با علامت تعجب یا @license شروع می‌شوند) نگه داشته می‌شوند؛ بقیه کامنت‌ها (فارسی) حذف.
 * - فایل‌های *.min.js از قبل کوچک‌اند و vendor/node_modules دست نمی‌خورند.
 * - اگر فایلی نتواند ساخته شود، خروج با خطا (ZIP ساخته نمی‌شود).
 */
import { readFileSync, writeFileSync, readdirSync, statSync } from 'node:fs';
import path from 'node:path';
import { transformSync } from 'esbuild';

const dirs = process.argv.slice( 2 ).map( ( d ) => path.resolve( d ) );
if ( ! dirs.length || dirs.some( ( d ) => ! statSync( d, { throwIfNoEntry: false } )?.isDirectory() ) ) {
	console.error( 'استفاده: node bin/build-js.mjs <پوشه> [<پوشه> …]' );
	process.exit( 2 );
}

const SKIP = /(^|[\\/])(node_modules|vendor)[\\/]|\.min\.js$/;

const jsFiles = ( base ) => readdirSync( base, { recursive: true } )
	.map( ( rel ) => path.join( base, String( rel ) ) )
	.filter( ( file ) => file.endsWith( '.js' ) && ! SKIP.test( file ) && statSync( file ).isFile() )
	.sort();

let before = 0;
let after = 0;
let count = 0;
let failed = 0;

for ( const dir of dirs ) {
	for ( const file of jsFiles( dir ) ) {
		const source = readFileSync( file, 'utf8' );
		try {
			const { code } = transformSync( source, {
				loader: 'js',
				minify: true,
				legalComments: 'inline',
				charset: 'utf8', // متن فارسی خام بماند، نه \uXXXX (کوچک‌تر و خواناتر در خطایابی)
				target: 'esnext',
			} );
			writeFileSync( file, code );
			before += Buffer.byteLength( source );
			after += Buffer.byteLength( code );
			count++;
		} catch ( error ) {
			failed++;
			console.error( `✘ ${ path.relative( dir, file ) }: ${ String( error.message ).split( '\n' )[ 0 ] }` );
		}
	}
}

if ( failed ) {
	process.exit( 1 );
}
console.log( `✔ JS کوچک شد: ${ count } فایل، ${ Math.round( before / 1024 ) }KB → ${ Math.round( after / 1024 ) }KB` );
