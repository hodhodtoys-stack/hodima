/**
 * جدول قبول/هشدار قاعده‌های چیدمان عددی از خروجی jsonl اندازه‌گیری (layout-metrics.mjs).
 *
 *   node layout-rules-report.mjs <فایل.jsonl> [--strict]
 *
 * قاعده‌ها (اسکیل hodima-admin-ui، «قاعده‌های چیدمان»):
 *   ۱ طول ≤ ۱۳۵۰px (دسکتاپ ۱۳۰۰)       ۲ کارت هم‌سطح دیدنی ≤ ۳      ۴ حداکثر یک دکمه پر در هر کارت
 *   ۶ حالت خالی ≤ ۱۶۰px               بنفش پیشخوان: صفر (پالت برند/پیش‌نمایش حساب نمی‌شود)
 *   هدف لمسی ≥ ۲۴px (WCAG 2.2)
 * فقط گزارش است؛ با --strict کد خروج ۱ اگر هشداری باشد. موبایل (۳۹۰) فقط هدف لمسی و بنفش را می‌سنجد.
 */
import fs from 'node:fs';

const [ file, flag ] = process.argv.slice( 2 );
const rows = fs.readFileSync( file, 'utf8' ).split( '\n' ).filter( Boolean ).map( ( l ) => JSON.parse( l ) );
const MAX_HEIGHT = 1350;
const pad = ( v, n ) => String( v ).padEnd( n );
let warns = 0;
const out = [];
const labels = [ ...new Set( rows.map( ( r ) => r.label ) ) ];

for ( const label of labels ) {
	const desk = rows.find( ( r ) => r.label === label && r.width >= 1000 );
	const mob = rows.find( ( r ) => r.label === label && r.width < 1000 );
	const issues = [];
	if ( desk ) {
		if ( desk.height > MAX_HEIGHT ) {
			issues.push( `طول ${ desk.height } > ${ MAX_HEIGHT }` );
		}
		if ( desk.cards > 3 ) {
			issues.push( `${ desk.cards } کارت دیدنی (بیش از ۳)` );
		}
		if ( desk.primaryMaxPerCard > 1 ) {
			issues.push( `${ desk.primaryMaxPerCard } دکمه پر در یک کارت` );
		}
		if ( desk.bigEmpty > 0 ) {
			issues.push( `${ desk.bigEmpty } حالت خالی بزرگ` );
		}
	}
	for ( const [ tag, r ] of [ [ '۱۳۰۰', desk ], [ '۳۹۰', mob ] ] ) {
		if ( ! r ) {
			continue;
		}
		const real = r.purple.filter( ( p ) => ! p.includes( '[پالت' ) );
		if ( real.length ) {
			issues.push( `بنفش (${ tag }): ${ real.slice( 0, 3 ).join( '، ' ) }` );
		}
		if ( r.smallCount > 0 ) {
			issues.push( `هدف کوچک‌تر از ۲۴px (${ tag }): ${ r.smallCount } — ${ r.smallTargets.slice( 0, 2 ).join( '، ' ) }` );
		}
	}
	warns += issues.length ? 1 : 0;
	out.push( `${ issues.length ? '⚠' : '✔' } ${ pad( label, 30 ) } ${ desk ? `طول ${ pad( desk.height, 5 ) } کارت ${ pad( desk.cards, 2 ) } پر ${ pad( desk.primaryMaxPerCard, 2 ) }` : '' }${ issues.length ? '  ← ' + issues.join( ' | ' ) : '' }` );
}

console.log( out.join( '\n' ) );
console.log( `\n${ labels.length } صفحه؛ ${ labels.length - warns } بدون هشدار، ${ warns } با هشدار (فقط گزارش)` );
process.exit( '--strict' === flag && warns ? 1 : 0 );
