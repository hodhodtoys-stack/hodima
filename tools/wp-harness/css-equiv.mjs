#!/usr/bin/env node
/**
 * هم‌ارزی دو نسخه CSS قالب، قانون‌به‌قانون (مستقل از HTML صفحه‌ها)
 *   node tools/wp-harness/css-equiv.mjs <پوشه قالب الف> <پوشه قالب ب> [فایل.css ...]
 *   (معمولا با css-equiv.sh: نسخه commit‌شده در برابر کار فعلی یا CSS ساخته‌شده)
 *
 * چرا: صفحه‌های ووکامرس در ابزار تست فقط head/footer دارند، پس عکس و computed
 * style بیشتر single-product.css و … را نمی‌بیند. این ابزار هر فایل را در
 * Chromium باز می‌کند و از CSSOM مرورگر برای هر «زمینه (@media/@supports/
 * @layer) + انتخابگر تکی + خصوصیت بلند (longhand)» مقدار نهایی را درمی‌آورد
 * (nesting باز می‌شود، shorthandها را خود مرورگر به longhand می‌شکند، رنگ‌ها و
 * عددها را مرورگر یکدست می‌نویسد، `width <= 768px` = `max-width: 768px`).
 * دو نسخه هم‌ارزند اگر این نقشه‌ها یکی باشند: مثلا minify و پایین‌آوردن
 * Lightning CSS، یا بازنویسی با nesting در مرحله ۶.
 *
 * محدودیت: ترتیب نسبی قانون‌های *متفاوت* با specificity برابر مقایسه نمی‌شود
 * (فقط مقدار نهایی هر انتخابگر)؛ تغییر عمدی (مثلا خصوصیت فیزیکی → منطقی)
 * تفاوت نشان می‌دهد و باید با visual-compare.sh دیده شود.
 */
import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright-core';

const [ dirA, dirB, ...only ] = process.argv.slice( 2 );
if ( ! dirB ) {
	console.error( 'usage: css-equiv.mjs <dir-a> <dir-b> [file.css ...]' );
	process.exit( 2 );
}

const list = ( base ) => fs.readdirSync( base, { recursive: true } )
	.map( String ).filter( ( rel ) => rel.endsWith( '.css' ) ).sort();
const files = [ ...new Set( [ ...list( dirA ), ...list( dirB ) ] ) ]
	.filter( ( rel ) => ! only.length || only.some( ( o ) => rel.endsWith( o ) ) );

/** در مرورگر: CSS → [کلید، مقدار، important] به ترتیب سورس. */
function extract( css ) {
	const sheet = new CSSStyleSheet();
	sheet.replaceSync( css );
	const probe = new CSSStyleSheet();
	const out = [];

	const split = ( sel ) => {
		const parts = [];
		let depth = 0;
		let cur = '';
		for ( const ch of sel ) {
			if ( '(' === ch || '[' === ch ) depth++;
			if ( ')' === ch || ']' === ch ) depth--;
			if ( ',' === ch && 0 === depth ) {
				parts.push( cur.trim() );
				cur = '';
			} else {
				cur += ch;
			}
		}
		if ( cur.trim() ) parts.push( cur.trim() );
		return parts;
	};
	// نگارش یکدست مرورگر برای یک انتخابگر
	const canon = ( sel ) => {
		try {
			probe.replaceSync( `${ sel }{}` );
			return probe.cssRules[ 0 ].selectorText;
		} catch {
			return sel;
		}
	};
	const resolve = ( sel, parents ) => {
		const res = [];
		for ( const part of split( sel ) ) {
			if ( ! parents ) {
				res.push( canon( part ) );
				continue;
			}
			// مثل nesting بومی: والد چندتایی = :is(فهرست) (specificity بیشینه)
			const rel = part.includes( '&' ) ? part : `& ${ part }`;
			const parent = 1 === parents.length ? parents[ 0 ] : `:is(${ parents.join( ', ' ) })`;
			res.push( canon( rel.replaceAll( '&', parent ) ) );
		}
		return res;
	};
	// rem در @media همیشه از اندازه پیش‌فرض مرورگر (۱۶px) است، نه فونت html: 48rem = 768px
	const media = ( text ) => text.toLowerCase().replace( /\s+/g, ' ' )
		.replace( /([\d.]+)rem\b/g, ( m, n ) => `${ Number( n ) * 16 }px` )
		.replace( /\(\s*([^()<>]+?)\s*<=\s*width\s*<=\s*([^()<>]+?)\s*\)/g, '(min-width: $1) and (max-width: $2)' )
		.replace( /\(\s*width\s*<=\s*([^)]+?)\s*\)/g, '(max-width: $1)' )
		.replace( /\(\s*width\s*>=\s*([^)]+?)\s*\)/g, '(min-width: $1)' )
		.replace( /\(\s*([a-z-]+)\s*:\s*/g, '($1: ' ).replace( /\b0?\.(\d)/g, '0.$1' ).trim();
	// رنگ‌ها به نگارش محاسبه‌شده مرورگر (color-mix، transparent، hex …)
	const swatch = document.body.appendChild( document.createElement( 'i' ) );
	const color = ( prop, value ) => {
		if ( ! /color$/.test( prop ) || /var\(|^(currentcolor|initial|inherit|unset)$/i.test( value ) ) return value;
		swatch.style.color = '';
		swatch.style.color = value;
		return swatch.style.color ? getComputedStyle( swatch ).color : value;
	};
	const decls = ( style, ctx, selectors ) => {
		for ( let i = 0; i < style.length; i++ ) {
			const prop = style[ i ];
			const value = color( prop, style.getPropertyValue( prop ).trim() );
			for ( const sel of selectors ) out.push( [ `${ ctx } ${ sel } { ${ prop }`, value, style.getPropertyPriority( prop ) ] );
		}
	};
	const walk = ( rules, ctx, parents ) => {
		for ( const rule of rules ) {
			if ( rule instanceof CSSStyleRule ) {
				const selectors = resolve( rule.selectorText, parents );
				decls( rule.style, ctx, selectors );
				walk( rule.cssRules, ctx, selectors );
			} else if ( 'CSSNestedDeclarations' in window && rule instanceof CSSNestedDeclarations ) {
				decls( rule.style, ctx, parents ?? [ ':root' ] );
			} else if ( rule instanceof CSSMediaRule ) {
				walk( rule.cssRules, `${ ctx } @media ${ media( rule.conditionText ) }`, parents );
			} else if ( rule instanceof CSSSupportsRule ) {
				walk( rule.cssRules, `${ ctx } @supports ${ rule.conditionText.replace( /\s+/g, ' ' ) }`, parents );
			} else if ( 'CSSContainerRule' in window && rule instanceof CSSContainerRule ) {
				walk( rule.cssRules, `${ ctx } @container ${ rule.containerName } ${ media( rule.containerQuery ) }`, parents );
			} else if ( 'CSSLayerBlockRule' in window && rule instanceof CSSLayerBlockRule ) {
				walk( rule.cssRules, `${ ctx } @layer ${ rule.name }`, parents );
			} else if ( 'CSSLayerStatementRule' in window && rule instanceof CSSLayerStatementRule ) {
				out.push( [ `${ ctx } @layer-order`, rule.nameList.join( ',' ), '' ] );
			} else if ( rule instanceof CSSKeyframesRule ) {
				for ( const frame of rule.cssRules ) decls( frame.style, `${ ctx } @keyframes ${ rule.name } ${ frame.keyText }`, [ '' ] );
			} else if ( rule instanceof CSSFontFaceRule ) {
				decls( rule.style, `${ ctx } @font-face ${ rule.style.getPropertyValue( 'font-family' ) } ${ rule.style.getPropertyValue( 'font-weight' ) }`, [ '' ] );
			} else {
				out.push( [ `${ ctx } @other`, rule.cssText, '' ] );
			}
		}
	};
	walk( sheet.cssRules, '', null );
	return out;
}

/** متغیرهای CSS متن خام دارند: #ffffff = #fff، 0.1 = .1، فاصله‌ها. */
const hex2 = ( n ) => Math.round( Number( n ) ).toString( 16 ).padStart( 2, '0' );
const normVar = ( v ) => v.toLowerCase().replaceAll( "'", '"' )
	// rgb()/rgba() با عدد ثابت = hex (minify)
	.replace( /rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)\s*(?:,\s*([\d.]+)\s*)?\)/g, ( m, r, g, b, a ) => `#${ hex2( r ) }${ hex2( g ) }${ hex2( b ) }${ undefined === a || 1 === Number( a ) ? '' : hex2( Number( a ) * 255 ) }` )
	.replace( /#([0-9a-f])\1([0-9a-f])\2([0-9a-f])\3(?:([0-9a-f])\4)?\b/g, '#$1$2$3$4' )
	.replace( /\s*([,()/])\s*/g, '$1' ).replace( /\s+/g, ' ' )
	.replace( /(\.\d*?)0+(?!\d)/g, '$1' ).replace( /(^|[^\d.])0\.(\d)/g, '$1.$2' ).trim();

/*
 * نگارش‌های هم‌معنا (minify این‌ها را کوتاه می‌کند): transparent = rgba(0,0,0,0)،
 * initial = مقدار اولیه (background: transparent → background: 0 0)،
 * translateX(a) = translate(a)، stroke-width: 2 = 2px.
 */
const INITIAL = { 'background-color': 'transparent', 'background-position-x': '0%', 'background-position-y': '0%', 'background-image': 'none' };
const WEIGHT = { normal: '400', bold: '700' };
const norm = ( prop, raw ) => {
	// متغیر CSS یا مقدار دارای var(): مرورگر متن خام نگه می‌دارد
	if ( prop.startsWith( '--' ) || raw.includes( 'var(' ) ) return normVar( raw );
	let v = raw.replace( /color\(srgb ([\d.]+) ([\d.]+) ([\d.]+)(?: \/ ([\d.]+))?\)/g, ( m, r, g, b, a ) => {
		const rgb = [ r, g, b ].map( ( c ) => Math.round( c * 255 ) ).join( ', ' );
		return undefined === a ? `rgb(${ rgb })` : `rgba(${ rgb }, ${ a })`;
	} ).replace( /rgba\(0, 0, 0, 0\)/g, 'transparent' ).replace( /translateX\(([^)]+)\)/g, 'translate($1)' );
	v = v.replace( /invert\(\)/g, 'invert(1)' ).replace( /^calc\(([\d.]+[a-z%]*)\)$/, '$1' ).replace( /gradient\(to right,/g, 'gradient(90deg,' );
	if ( 'initial' === v && INITIAL[ prop ] ) v = INITIAL[ prop ];
	if ( 'initial' === v && /^border-.*-color$/.test( prop ) ) v = 'currentcolor';
	if ( 'font-weight' === prop && WEIGHT[ v ] ) v = WEIGHT[ v ];
	if ( /^background-position-[xy]$/.test( prop ) && '0px' === v ) v = '0%';
	if ( 'stroke-width' === prop && /^[\d.]+$/.test( v ) ) v += 'px';
	return v;
};

/** مقدار نهایی هر کلید: important برنده، وگرنه آخرین. */
const finalMap = ( rows ) => {
	const map = new Map();
	for ( const [ key, raw, prio ] of rows ) {
		const value = norm( key.slice( key.lastIndexOf( '{ ' ) + 2 ), raw );
		const prev = map.get( key );
		if ( ! prev || prio || ! prev.prio ) map.set( key, { value, prio } );
	}
	return map;
};

const browser = await chromium.launch();
const page = await browser.newPage();
/*
 * توکن‌های assets/css/tokens.css هر طرف (متغیرهای :root) در همان طرف جایگزین
 * می‌شوند: «border-radius: 8px» و «border-radius: var(--hodima-radius-md)» هم‌ارزند
 * (مرورگر var() را در CSSOM حل نمی‌کند). تغییر مقدار یک توکن در مصرف‌کننده‌هایش
 * دیده می‌شود. فقط برای مقایسه است؛ رنگ‌های پیشخوان (پالت) را در نظر نمی‌گیرد.
 */
const tokenMap = ( dir ) => {
	const file = path.join( dir, 'assets/css/tokens.css' );
	const map = new Map();
	if ( fs.existsSync( file ) ) {
		const css = fs.readFileSync( file, 'utf8' ).replace( /\/\*[\s\S]*?\*\//g, '' );
		for ( const m of css.matchAll( /(--hodima-[\w-]+)\s*:\s*([^;}]+)[;}]/g ) ) map.set( m[ 1 ], m[ 2 ].trim() );
	}
	return map;
};
const VAR = /var\(\s*(--[\w-]+)\s*(?:,\s*((?:[^()]|\([^()]*\))*))?\)/g;
const resolveTokens = ( css, map ) => {
	for ( let pass = 0; pass < 8; pass++ ) {
		const next = css.replace( VAR, ( m, name ) => map.get( name ) ?? m );
		if ( next === css ) break;
		css = next;
	}
	return css;
};
const [ tokensA, tokensB ] = [ tokenMap( dirA ), tokenMap( dirB ) ];

let bad = 0;
for ( const rel of files ) {
	const read = ( dir, map ) => ( fs.existsSync( path.join( dir, rel ) ) ? resolveTokens( fs.readFileSync( path.join( dir, rel ), 'utf8' ), map ) : null );
	const [ a, b ] = [ read( dirA, tokensA ), read( dirB, tokensB ) ];
	if ( null === a || null === b ) {
		bad++;
		console.log( `✘ ${ rel }: فقط در ${ null === a ? 'ب' : 'الف' }` );
		continue;
	}
	const [ ma, mb ] = [ finalMap( await page.evaluate( extract, a ) ), finalMap( await page.evaluate( extract, b ) ) ];
	const diffs = [];
	for ( const key of new Set( [ ...ma.keys(), ...mb.keys() ] ) ) {
		const [ x, y ] = [ ma.get( key ), mb.get( key ) ];
		// shorthand «border» (minify) border-image را هم initial می‌کند؛ قالب جایی border-image ندارد
		if ( /\{ border-image-/.test( key ) && 'initial' === ( x ?? y ).value && ! ( x ?? y ).prio && ( ! x || ! y ) ) continue;
		if ( x?.value !== y?.value || x?.prio !== y?.prio ) {
			diffs.push( `${ key.trim() }: ${ x ? `${ x.value }${ x.prio ? ' !important' : '' }` : '∅' } → ${ y ? `${ y.value }${ y.prio ? ' !important' : '' }` : '∅' }` );
		}
	}
	if ( diffs.length ) bad++;
	console.log( `${ diffs.length ? '✘' : '✔' } ${ rel.padEnd( 40 ) } ${ ma.size } اعلان${ diffs.length ? ` — ${ diffs.length } تفاوت` : ' — یکسان' }` );
	for ( const d of diffs.slice( 0, Number( process.env.CSS_EQUIV_MAX ) || 15 ) ) console.log( `      ${ d }` );
}
await browser.close();
console.log( bad ? `\n${ bad } فایل متفاوت` : '\nهمه فایل‌های CSS هم‌ارز' );
process.exit( bad ? 1 : 0 );
