/**
 * ساخت «CSS حیاتی» در مرورگر مدیر (تنظیمات قالب ← پیج اسپید؛ inc/performance/critical-css.php)
 *
 * برای هر نوع صفحه: نمونه‌ها در قاب پنهان هم‌دامنه (?hodima_ccss_build=1، بدون نوار مدیریت) در
 * ۳۹۰×۸۴۴ و ۱۳۰۰×۹۰۰ باز می‌شوند؛ قانون‌های stylesheetهای <link> که با عنصرهای «بالای صفحه»
 * جور درمی‌آیند جدا و با admin-ajax ذخیره می‌شوند.
 *
 * «بالای صفحه» = هر عنصری که لبه بالایش تا ۱٫۵ برابر ارتفاع پنجره است (حتی بیرون از عرض پنجره:
 * کشوی منو)، و هر عنصر پنهان (display:none) که نزدیک‌ترین والد دارای جعبه‌اش بالای صفحه است؛
 * وگرنه قانون پنهان‌کننده منوی موبایل/پنجره‌ها در CSS حیاتی نبود و تا رسیدن CSS کامل دیده می‌شدند.
 * قانون‌های @media همه نگه داشته می‌شوند (نه فقط عرض ساخت) تا تبلت هم درست باشد؛ از فهرست انتخابگر
 * فقط بخش‌های لازم؛ @font-face فقط وزن‌هایی که بالای صفحه به کار رفته؛
 * @keyframes فقط اگر نامش به کار رفته. url() نسبی نسبت به آدرس فایل CSS مطلق می‌شود (CSS درون صفحه).
 */
( () => {
	'use strict';

	const root = document.querySelector( '[data-hodima-ccss]' );
	if ( ! root ) {
		return;
	}

	let config;
	try {
		config = JSON.parse( root.dataset.hodimaCcss );
	} catch {
		return;
	}

	const buildBtn = root.querySelector( '[data-hodima-ccss-build]' );
	const clearBtn = root.querySelector( '[data-hodima-ccss-clear]' );
	const progressBox = root.querySelector( '.hodima-ccss__progress' );
	const progress = progressBox.querySelector( 'progress' );
	const log = root.querySelector( '.hodima-ccss__log' );
	const stage = root.querySelector( '.hodima-ccss__stage' );

	const VIEWPORTS = [ [ 390, 844 ], [ 1300, 900 ] ];
	const FOLD = 1.5; // ۱٫۵ برابر ارتفاع: تبلت عمودی (۸۲۰×۱۱۸۰) بلندتر از دو اندازه ساخت است
	const LOAD_TIMEOUT = 45000;
	const fa = ( value ) => String( value ).replace( /\d/g, ( d ) => '۰۱۲۳۴۵۶۷۸۹'[ d ] );
	const wait = ( ms ) => new Promise( ( resolve ) => setTimeout( resolve, ms ) );

	const PSEUDO_ELEMENT = /::?(?:before|after|first-line|first-letter|placeholder|marker|selection|backdrop|file-selector-button|-webkit-[\w-]+|-moz-[\w-]+)(?:\([^)]*\))?/gi;

	/** بخش‌های فهرست انتخابگر (ویرگول سطح بالا، نه داخل پرانتز :is()/:not()). */
	function splitSelectors( text ) {
		const parts = [];
		let depth = 0;
		let start = 0;
		for ( let i = 0; i < text.length; i++ ) {
			const c = text[ i ];
			if ( '(' === c || '[' === c ) {
				depth++;
			} else if ( ')' === c || ']' === c ) {
				depth--;
			} else if ( ',' === c && 0 === depth ) {
				parts.push( text.slice( start, i ) );
				start = i + 1;
			}
		}
		parts.push( text.slice( start ) );
		return parts.map( ( s ) => s.trim() ).filter( Boolean );
	}

	/** عنصرهای بالای صفحه (همراه html و body). */
	function aboveFold( doc, height ) {
		const limit = height * FOLD;
		const set = new Set( [ doc.documentElement, doc.body ] );

		for ( const el of doc.body.querySelectorAll( '*' ) ) {
			if ( el.getClientRects().length ) {
				if ( el.getBoundingClientRect().top <= limit ) {
					set.add( el );
				}
				continue;
			}
			// پنهان: فقط بیرونی‌ترین عنصر پنهان (فرزندانش با او پنهان‌اند)، اگر والد جعبه‌دارش بالای صفحه است
			const parent = el.parentElement;
			if ( ! parent || ! parent.getClientRects().length ) {
				continue;
			}
			if ( parent.getBoundingClientRect().top <= limit ) {
				set.add( el );
			}
		}
		return set;
	}

	/**
	 * بخش‌هایی از فهرست انتخابگر که به عنصری از مجموعه می‌خورند (بقیه فهرست حذف می‌شود؛
	 * هر بخش وزن خودش را دارد، پس حذف بخش‌های بی‌مصرف اثری روی بقیه ندارد).
	 */
	function matchingSelectors( doc, selectorText, set ) {
		return splitSelectors( selectorText ).filter( ( part ) => {
			const base = part.replace( PSEUDO_ELEMENT, '' ).trim() || '*';
			let nodes;
			try {
				nodes = doc.querySelectorAll( base );
			} catch {
				return false; // انتخابگر ناشناخته این مرورگر (پیشوند فروشنده)
			}
			for ( const node of nodes ) {
				if ( set.has( node ) ) {
					return true;
				}
			}
			return false;
		} );
	}

	/** وزن‌های فونتی که عنصرهای بالای صفحه به کار می‌برند (فقط @font-face همین وزن‌ها). */
	function usedWeights( doc, set ) {
		const view = doc.defaultView;
		const weights = new Set();
		for ( const el of set ) {
			if ( el.getClientRects().length ) {
				weights.add( String( view.getComputedStyle( el ).fontWeight ) );
			}
		}
		return weights;
	}

	/** آیا وزن (یا بازه وزن) یک @font-face در فهرست وزن‌های به‌کاررفته هست. */
	function fontFaceUsed( rule, weights ) {
		const value = rule.style.getPropertyValue( 'font-weight' ).trim() || '400';
		const named = { normal: '400', bold: '700' };
		const [ from, to = from ] = value.split( /\s+/ ).map( ( w ) => Number( named[ w ] ?? w ) );
		for ( const w of weights ) {
			const n = Number( w );
			if ( n >= from && n <= to ) {
				return true;
			}
		}
		return false;
	}

	/** url() نسبی ← مطلق (نسبت به فایل CSS). */
	function absolutize( css, base ) {
		return css.replace( /url\(\s*(['"]?)([^'")]+)\1\s*\)/g, ( match, quote, url ) => {
			if ( /^(?:data:|https?:|\/\/|\/|#)/i.test( url ) ) {
				return match;
			}
			try {
				return `url("${ new URL( url, base ).href }")`;
			} catch {
				return match;
			}
		} );
	}

	/**
	 * قانون‌های لازم یک stylesheet؛ خروجی آرایه [مسیر، متن] به ترتیب منبع.
	 * مسیر = شماره قانون در هر سطح (برای ادغام چند نمونه به همان ترتیب).
	 */
	function collect( doc, rules, set, path, out, base, weights ) {
		Array.from( rules ).forEach( ( rule, index ) => {
			const here = [ ...path, index ];
			const name = rule.constructor.name;

			if ( 'CSSStyleRule' === name ) {
				const parts = matchingSelectors( doc, rule.selectorText, set );
				if ( parts.length ) {
					// قانون تودرتو (nesting): همان متن کامل؛ وگرنه فقط انتخابگرهای لازم
					const text = rule.cssRules?.length ? rule.cssText : `${ parts.join( ',' ) }{${ rule.style.cssText }}`;
					out.push( [ here, absolutize( text, base ) ] );
				}
			} else if ( 'CSSFontFaceRule' === name ) {
				if ( fontFaceUsed( rule, weights ) ) {
					out.push( [ here, absolutize( rule.cssText, base ) ] );
				}
			} else if ( 'CSSKeyframesRule' === name ) {
				out.push( [ here, rule.cssText, rule.name ] );
			} else if ( 'CSSMediaRule' === name || 'CSSSupportsRule' === name || 'CSSContainerRule' === name ) {
				const inner = [];
				collect( doc, rule.cssRules, set, here, inner, base, weights );
				const kind = { CSSMediaRule: '@media', CSSSupportsRule: '@supports', CSSContainerRule: '@container' }[ name ];
				for ( const [ p, text, keyframe ] of inner ) {
					out.push( [ p, `${ kind } ${ rule.conditionText }{${ text }}`, keyframe ] );
				}
			}
			// @import، @page، @layer، @namespace: لازم نیست
		} );
	}

	/** بارگذاری یک آدرس در قاب پنهان با اندازه داده‌شده. */
	function loadFrame( url, width, height ) {
		return new Promise( ( resolve, reject ) => {
			const frame = document.createElement( 'iframe' );
			frame.className = 'hodima-ccss__frame';
			frame.style.width = `${ width }px`;
			frame.style.height = `${ height }px`;
			frame.setAttribute( 'tabindex', '-1' );
			frame.setAttribute( 'title', 'ساخت CSS حیاتی' );
			const timer = setTimeout( () => reject( new Error( 'زمان بارگذاری تمام شد' ) ), LOAD_TIMEOUT );
			frame.addEventListener( 'load', async () => {
				clearTimeout( timer );
				try {
					const doc = frame.contentDocument;
					if ( ! doc || ! doc.body ) {
						throw new Error( 'صفحه در قاب باز نشد' );
					}
					await doc.fonts?.ready;
					await wait( 700 );
					resolve( frame );
				} catch ( error ) {
					reject( error );
				}
			}, { once: true } );
			const target = new URL( url, location.href );
			target.searchParams.set( 'hodima_ccss_build', '1' );
			frame.src = target.href;
			stage.replaceChildren( frame );
		} );
	}

	/** CSS حیاتی یک نوع از همه نمونه‌ها و هر دو اندازه. */
	async function buildType( type, urls, report ) {
		const sheets = []; // ترتیب فایل‌ها (اولین دیده‌شدن)
		const found = new Map(); // کلید ← { order, text, keyframe }

		for ( const url of urls ) {
			const [ w0, h0 ] = VIEWPORTS[ 0 ];
			const frame = await loadFrame( url, w0, h0 );

			for ( const [ width, height ] of VIEWPORTS ) {
				frame.style.width = `${ width }px`;
				frame.style.height = `${ height }px`;
				await wait( 400 );
				const doc = frame.contentDocument;
				doc.defaultView.scrollTo( 0, 0 );
				const set = aboveFold( doc, height );
				const weights = usedWeights( doc, set );

				for ( const sheet of doc.styleSheets ) {
					// فقط فایل‌های <link>؛ <style>های درون‌خطی در صفحه می‌مانند
					if ( ! sheet.href || ! ( sheet.ownerNode instanceof doc.defaultView.HTMLLinkElement ) ) {
						continue;
					}
					let rules;
					try {
						rules = sheet.cssRules;
					} catch {
						continue; // دامنه دیگر
					}
					const file = sheet.href.split( '?' )[ 0 ];
					if ( ! sheets.includes( file ) ) {
						sheets.push( file );
					}
					const out = [];
					collect( doc, rules, set, [], out, sheet.href, weights );
					// media خود پیوند (مثلا smallscreen ووکامرس فقط تا ۷۶۸px): قانون‌ها داخل همان شرط
					const media = sheet.media?.mediaText || '';
					const wrap = media && 'all' !== media ? ( text ) => `@media ${ media }{${ text }}` : ( text ) => text;
					for ( const [ p, raw, keyframe ] of out ) {
						const text = wrap( raw );
						const key = `${ file }|${ p.join( '.' ) }`;
						if ( ! found.has( key ) ) {
							found.set( key, { order: [ sheets.indexOf( file ), ...p ], text, keyframe } );
						}
					}
				}
			}
			report();
		}

		const sorted = [ ...found.values() ].sort( ( a, b ) => {
			for ( let i = 0; i < Math.max( a.order.length, b.order.length ); i++ ) {
				const d = ( a.order[ i ] ?? -1 ) - ( b.order[ i ] ?? -1 );
				if ( d ) {
					return d;
				}
			}
			return 0;
		} );

		/*
		 * قانون تکراری (همان متن در دو فایل): فقط *آخرین* نسخه می‌ماند، نه اولی. باگ پیدا‌شده در آزمون:
		 * product-card.css و taxonomy-product_cat.css هر دو «@media (max-width:768px){…repeat(2…)}» داشتند؛
		 * با نگه‌داشتن اولی، «۹۹۲px ← ۳ ستون» فایل دوم بعد از آن می‌آمد و فروشگاه موبایل تا رسیدن CSS کامل
		 * سه‌ستونه بود (CLS ۰٫۲۲). حذف نسخه زودتر بی‌خطر است: نسخه بعدی همان را با ترتیب درست‌تر دارد.
		 */
		const seen = new Set();
		const items = [];
		for ( let i = sorted.length - 1; i >= 0; i-- ) {
			if ( ! seen.has( sorted[ i ].text ) ) {
				seen.add( sorted[ i ].text );
				items.unshift( sorted[ i ] );
			}
		}

		const body = items.filter( ( item ) => ! item.keyframe ).map( ( item ) => item.text ).join( '' );
		const frames = items.filter( ( item ) => item.keyframe && body.includes( item.keyframe ) ).map( ( item ) => item.text ).join( '' );
		stage.replaceChildren();

		return body + frames;
	}

	async function post( action, data ) {
		const form = new FormData();
		form.append( 'action', action );
		form.append( 'nonce', config.nonce );
		for ( const [ key, value ] of Object.entries( data ) ) {
			if ( Array.isArray( value ) ) {
				value.forEach( ( v ) => form.append( `${ key }[]`, v ) );
			} else {
				form.append( key, value );
			}
		}
		const response = await fetch( config.ajax, { method: 'POST', body: form, credentials: 'same-origin' } );
		const json = await response.json().catch( () => null );
		if ( ! response.ok || ! json?.success ) {
			throw new Error( json?.data?.message || `خطای سرور (${ response.status })` );
		}
		return json.data;
	}

	function setRow( type, state, text, meta ) {
		const row = root.querySelector( `tr[data-type="${ type }"]` );
		const badge = row?.querySelector( '.hodima-ccss__state' );
		if ( ! badge ) {
			return;
		}
		badge.dataset.state = state;
		badge.textContent = text;
		let metaEl = row.querySelector( '.hodima-ccss__meta' );
		if ( meta ) {
			if ( ! metaEl ) {
				metaEl = document.createElement( 'span' );
				metaEl.className = 'hodima-ccss__meta';
				badge.after( metaEl );
			}
			metaEl.textContent = meta;
		} else {
			metaEl?.remove();
		}
	}

	async function build() {
		const entries = Object.entries( config.types ).filter( ( [ , t ] ) => t.urls.length );
		const total = entries.reduce( ( sum, [ , t ] ) => sum + t.urls.length, 0 ) || 1;
		let done = 0;
		let failed = 0;

		buildBtn.disabled = clearBtn.disabled = true;
		progressBox.hidden = false;
		progress.value = 0;

		for ( const [ type, info ] of entries ) {
			log.textContent = `در حال ساخت: ${ info.label }…`;
			setRow( type, 'busy', 'در حال ساخت…' );
			try {
				const css = await buildType( type, info.urls, () => {
					done++;
					progress.value = Math.round( ( done / total ) * 100 );
				} );
				if ( ! css ) {
					throw new Error( 'قانونی پیدا نشد' );
				}
				const result = await post( 'hodima_ccss_save', { type, css, urls: info.urls } );
				setRow( type, 'ok', 'آماده', result.meta );
			} catch ( error ) {
				failed++;
				setRow( type, 'error', `خطا: ${ error.message }` );
			}
		}

		try {
			await post( 'hodima_ccss_finish', {} );
		} catch {
			// پاک‌کردن کش اختیاری است
		}

		progress.value = 100;
		log.textContent = failed
			? `ساخت تمام شد؛ ${ fa( failed ) } نوع صفحه خطا داشت (ردیف قرمز). بقیه آماده‌اند و کش لایت‌اسپید پاک شد.`
			: 'ساخت تمام شد؛ همه نوع‌ها آماده‌اند و کش لایت‌اسپید پاک شد. حالا لینک «آزمون» را در PageSpeed ببینید یا کلید پایین را روشن کنید.';
		buildBtn.disabled = clearBtn.disabled = false;
	}

	async function clear() {
		// eslint-disable-next-line no-alert -- تأیید ساده پیش از حذف همه
		if ( ! window.confirm( 'CSS حیاتی همه نوع‌های صفحه حذف شود؟ سایت مثل قبل (بدون CSS حیاتی) نمایش داده می‌شود.' ) ) {
			return;
		}
		buildBtn.disabled = clearBtn.disabled = true;
		try {
			await post( 'hodima_ccss_finish', { clear: '1' } );
			for ( const type of Object.keys( config.types ) ) {
				setRow( type, 'none', 'ساخته نشده' );
			}
			progressBox.hidden = false;
			progress.value = 0;
			log.textContent = 'همه حذف شد و کش لایت‌اسپید پاک شد.';
		} catch ( error ) {
			progressBox.hidden = false;
			log.textContent = `خطا: ${ error.message }`;
		}
		buildBtn.disabled = clearBtn.disabled = false;
	}

	buildBtn.addEventListener( 'click', build );
	clearBtn.addEventListener( 'click', clear );
} )();
