/**
 * «ساختار تیترها» زیر ویرایشگر توضیح دسته (Hodima SEO — core/cat-blog، از 2.3.1)
 *
 * قانون: عنوان دسته H1 صفحه است، پس اولین تیتر متن حداکثر H2 و هر تیتر حداکثر یک پله
 * پایین‌تر از تیتر قبلی؛ بالا رفتن آزاد. (PageSpeed «heading-order»: دسته «اکسسوری» h5 بعد از h3.)
 *
 * - فهرست زنده تیترها با سطح، تورفتگی و ایراد هر کدام (حالت «دیداری» و «متن» هر دو).
 * - «مرتب‌سازی خودکار»: فقط نام تگ تیترها عوض می‌شود (متن، شناسه، کلاس و لینک‌ها می‌مانند)؛
 *   در حالت دیداری یک قدم «واگرد» TinyMCE است و «بازگردانی» هم دارد. ذخیره با خود مدیر («به‌روزرسانی»).
 * - کلیک روی هر تیتر فهرست، همان تیتر را در ویرایشگر انتخاب می‌کند.
 * متن کاربر فقط با textContent در فهرست نوشته می‌شود (نه innerHTML).
 */
( () => {
	'use strict';

	const SELECTOR = 'h1, h2, h3, h4, h5, h6';
	const NAMES = { 1: 'عنوان صفحه', 2: 'تیتر بخش', 3: 'زیربخش', 4: 'جزئیات', 5: 'ریزجزئیات', 6: 'ریزترین' };
	// شمارش‌ها با رقم فارسی؛ نام سطح تیتر (H2…) با رقم لاتین، هم‌شکل منوی ویرایشگر
	const fa = ( value ) => String( value ).replace( /\d/g, ( d ) => '۰۱۲۳۴۵۶۷۸۹'[ d ] );
	const levelOf = ( node ) => Number( node.nodeName.charAt( 1 ) );

	/**
	 * سطح درست هر تیتر و توضیح ایرادش.
	 *
	 * @param {Array<{level: number, text: string}>} items
	 * @return {Array<{level: number, text: string, target: number, issue: string, fixable: boolean}>}
	 */
	function analyse( items ) {
		let prevTarget = 1; // H1 = عنوان دسته
		let prevLevel = 1;

		return items.map( ( item, index ) => {
			const target = Math.max( 2, Math.min( item.level, prevTarget + 1 ) );
			let issue = '';

			if ( item.level === 1 ) {
				issue = `H1 مخصوص عنوان دسته است (بالای صفحه خودکار چاپ می‌شود) ← H${ target }`;
			} else if ( target !== item.level && index === 0 ) {
				issue = `اولین تیتر متن باید «تیتر بخش (H2)» باشد ← H${ target }`;
			} else if ( target !== item.level && item.level > prevLevel + 1 ) {
				issue = `پرش از H${ prevLevel } به H${ item.level }؛ ${ fa( item.level - prevLevel - 1 ) } پله جا افتاده ← H${ target }`;
			} else if ( target !== item.level ) {
				issue = `تیتر بالایی یک پله بالا می‌آید، پس این هم ← H${ target }`;
			}

			const empty = '' === item.text;
			if ( empty ) {
				issue = issue ? `${ issue } · تیتر خالی است` : 'تیتر خالی است؛ متن بنویسید یا آن را «پاراگراف» کنید';
			}

			prevTarget = target;
			prevLevel = item.level;

			return { ...item, target, issue, fixable: target !== item.level };
		} );
	}

	function setup( box ) {
		const id = box.dataset.editor;
		const textarea = document.getElementById( id );

		if ( ! id || ! textarea ) {
			return;
		}

		const outline = box.querySelector( '.hodima-hg__outline' );
		const status = box.querySelector( '.hodima-hg__status' );
		const fixBtn = box.querySelector( '.hodima-hg__fix' );
		const undoBtn = box.querySelector( '.hodima-hg__undo' );
		const msg = box.querySelector( '.hodima-hg__msg' );
		let editor = null;
		let signature = '';
		let timer = 0;
		let undoState = null; // { mode: 'visual'|'text', value }: متن پیش از مرتب‌سازی، تا اولین ویرایش بعدی

		const visual = () => Boolean( editor && ! editor.isHidden() );

		/** گره‌های تیتر در حالت دیداری، یا تیترهای HTML متن در حالت «متن». */
		function headings() {
			if ( visual() ) {
				return [ ...editor.getBody().querySelectorAll( SELECTOR ) ];
			}
			const doc = new DOMParser().parseFromString( `<body>${ textarea.value }</body>`, 'text/html' );
			return [ ...doc.body.querySelectorAll( SELECTOR ) ];
		}

		const read = () => headings().map( ( node ) => ( { level: levelOf( node ), text: node.textContent.replace( /\s+/g, ' ' ).trim() } ) );

		function render( items ) {
			const problems = items.filter( ( item ) => item.issue ).length;
			const fixable = items.filter( ( item ) => item.fixable ).length;

			if ( ! items.length ) {
				status.dataset.state = 'empty';
				status.textContent = 'هنوز تیتری ندارد';
			} else if ( problems ) {
				status.dataset.state = 'warn';
				status.textContent = `${ fa( problems ) } مورد نیاز به اصلاح`;
			} else {
				status.dataset.state = 'ok';
				status.textContent = `ساختار درست است · ${ fa( items.length ) } تیتر`;
			}

			fixBtn.disabled = 0 === fixable;
			outline.replaceChildren();

			if ( ! items.length ) {
				const li = document.createElement( 'li' );
				li.className = 'hodima-hg__empty';
				li.textContent = 'برای متن‌های بلند، بخش‌ها را با «تیتر بخش (H2)» از منوی «پاراگراف» ویرایشگر جدا کنید؛ خواندن و سئوی صفحه بهتر می‌شود.';
				outline.append( li );
				return;
			}

			items.forEach( ( item, index ) => {
				const li = document.createElement( 'li' );
				li.className = 'hodima-hg__item' + ( item.issue ? ' is-issue' : '' );
				li.style.setProperty( '--hg-depth', String( Math.max( 0, Math.min( item.level, 6 ) - 2 ) ) );

				const btn = document.createElement( 'button' );
				btn.type = 'button';
				btn.className = 'hodima-hg__jump';
				btn.dataset.index = String( index );

				const badge = document.createElement( 'span' );
				badge.className = 'hodima-hg__badge';
				badge.dataset.level = String( item.level );
				badge.textContent = `H${ item.level }`;
				badge.title = NAMES[ item.level ];

				const text = document.createElement( 'span' );
				text.className = 'hodima-hg__text';
				text.textContent = item.text || '(تیتر بدون متن)';

				btn.append( badge, text );
				li.append( btn );

				if ( item.issue ) {
					const note = document.createElement( 'span' );
					note.className = 'hodima-hg__issue';
					note.textContent = item.issue;
					li.append( note );
				}

				outline.append( li );
			} );
		}

		function refresh( force = false ) {
			const items = analyse( read() );
			const next = ( visual() ? 'v' : 't' ) + JSON.stringify( items );

			if ( force || next !== signature ) {
				signature = next;
				render( items );
			}
		}

		const schedule = () => {
			clearTimeout( timer );
			timer = setTimeout( refresh, 250 );
		};

		/** تگ‌های تیتر در HTML متن به ترتیب (همان ترتیب DOMParser)؛ null اگر شمارش‌ها نخواند. */
		function rewriteText( value, targets ) {
			const opens = value.match( /<h[1-6](?=[\s>/])/gi ) || [];
			if ( opens.length !== targets.length ) {
				return null;
			}
			let index = -1;
			let open = 0;
			return value.replace( /<(\/?)h([1-6])(?=[\s>/])/gi, ( match, slash ) => {
				if ( ! slash ) {
					index++;
					open = targets[ index ];
					return `<h${ open }`;
				}
				const out = open ? `</h${ open }` : match;
				open = 0;
				return out;
			} );
		}

		function fix() {
			const items = analyse( read() );
			const changed = items.filter( ( item ) => item.fixable );

			if ( ! changed.length ) {
				return;
			}

			if ( visual() ) {
				const nodes = headings();
				undoState = { mode: 'visual', value: editor.getContent() };
				editor.undoManager.transact( () => {
					nodes.forEach( ( node, i ) => {
						if ( items[ i ] && items[ i ].fixable ) {
							editor.dom.rename( node, `h${ items[ i ].target }` );
						}
					} );
				} );
				editor.nodeChanged();
				editor.fire( 'change' );
			} else {
				const value = rewriteText( textarea.value, items.map( ( item ) => item.target ) );
				if ( null === value ) {
					msg.textContent = 'در حالت «متن» اصلاح خودکار ممکن نشد (تگ تیتر داخل توضیح HTML یا کد)؛ به حالت «دیداری» بروید و دوباره بزنید.';
					return;
				}
				undoState = { mode: 'text', value: textarea.value };
				textarea.value = value;
				textarea.dispatchEvent( new Event( 'input', { bubbles: true } ) );
			}

			undoBtn.hidden = false;
			msg.textContent = `${ fa( changed.length ) } تیتر مرتب شد. نتیجه را ببینید و برای ذخیره «به‌روزرسانی» را بزنید.`;
			refresh( true );
		}

		/** بعد از اولین ویرایش خود مدیر: «بازگردانی» نوشته‌های تازه‌اش را پاک نکند و پیام کهنه نماند. */
		function dropUndo() {
			undoState = null;
			undoBtn.hidden = true;
			msg.textContent = '';
		}

		function undo() {
			if ( ! undoState ) {
				return;
			}
			if ( 'visual' === undoState.mode && editor ) {
				const before = undoState.value;
				editor.undoManager.transact( () => editor.setContent( before ) );
				editor.nodeChanged();
			} else if ( 'text' === undoState.mode ) {
				textarea.value = undoState.value;
				textarea.dispatchEvent( new Event( 'input', { bubbles: true } ) );
			}
			undoState = null;
			undoBtn.hidden = true;
			msg.textContent = 'تیترها به حالت قبل برگشتند.';
			refresh( true );
		}

		/** انتخاب تیتر در ویرایشگر (دیداری: انتخاب گره؛ متن: انتخاب تگ آغاز). */
		function jump( index ) {
			if ( visual() ) {
				const node = headings()[ index ];
				if ( node ) {
					editor.getContainer().scrollIntoView( { block: 'nearest' } );
					editor.focus();
					editor.selection.select( node, true );
					node.scrollIntoView( { block: 'center' } );
				}
				return;
			}
			const re = /<h[1-6](?=[\s>/])/gi;
			let found = null;
			for ( let i = 0; i <= index; i++ ) {
				found = re.exec( textarea.value );
				if ( ! found ) {
					return;
				}
			}
			textarea.focus();
			textarea.setSelectionRange( found.index, textarea.value.indexOf( '>', found.index ) + 1 || found.index );
		}

		function bindEditor( instance ) {
			if ( ! instance || instance === editor ) {
				return;
			}
			editor = instance;
			editor.on( 'input change keyup SetContent Undo Redo ExecCommand NodeChange', schedule );
			editor.on( 'input ExecCommand Undo Redo', dropUndo );
			editor.on( 'show hide', () => refresh( true ) );
			refresh( true );
		}

		function attach() {
			const instance = window.tinymce && window.tinymce.get( id );
			if ( instance && instance.initialized ) {
				bindEditor( instance );
			} else if ( instance ) {
				instance.on( 'init', () => bindEditor( instance ) );
			}
			refresh();
		}

		if ( window.tinymce && typeof window.tinymce.on === 'function' ) {
			window.tinymce.on( 'AddEditor', ( event ) => {
				if ( event.editor && event.editor.id === id ) {
					event.editor.on( 'init', () => bindEditor( event.editor ) );
				}
			} );
		}

		textarea.addEventListener( 'input', schedule );
		textarea.addEventListener( 'input', ( event ) => {
			if ( event.isTrusted ) {
				dropUndo();
			}
		} );
		// زبانه‌های «دیداری / متن» وردپرس
		document.addEventListener( 'click', ( event ) => {
			if ( event.target instanceof Element && event.target.closest( `#${ id }-tmce, #${ id }-html` ) ) {
				setTimeout( () => refresh( true ), 60 );
			}
		} );

		fixBtn.addEventListener( 'click', fix );
		undoBtn.addEventListener( 'click', undo );
		outline.addEventListener( 'click', ( event ) => {
			const btn = event.target instanceof Element ? event.target.closest( '.hodima-hg__jump' ) : null;
			if ( btn ) {
				jump( Number( btn.dataset.index ) );
			}
		} );

		box.hidden = false;
		attach();
		window.addEventListener( 'load', attach );
	}

	const start = () => document.querySelectorAll( '.hodima-hg[data-editor]' ).forEach( setup );

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
} )();
