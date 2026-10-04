/**
 * ==========================================================
 * footer.js — آکاردئون ستون‌های فوتر در موبایل
 * نسخه: 3.0.0
 *
 * باگ‌های نسخه قبلی:
 *   - هر رویداد resize همه ستون‌ها را می‌بست؛ در موبایل نوار آدرس مرورگر هنگام
 *     اسکرول جمع/باز می‌شود و resize می‌فرستد، پس ستونی که کاربر باز کرده بود
 *     با اسکرول خودبه‌خود بسته می‌شد. حالا فقط عبور از مرز موبایل/دسکتاپ
 *     (matchMedia، همان 48rem فایل CSS) وضعیت را از نو می‌چیند.
 *   - عنوان h2 کلیک‌پذیر بود ولی دکمه نبود: با کیبورد باز نمی‌شد و صفحه‌خوان
 *     باز/بسته بودن را نمی‌فهمید. حالا در موبایل متن عنوان داخل یک <button>
 *     با aria-expanded/aria-controls است (الگوی آکاردئون WAI-ARIA)؛ در دسکتاپ
 *     همان h2 ساده (ستون‌ها همیشه باز). ظاهر عوض نمی‌شود: دکمه همه استایل را
 *     از h2 می‌گیرد (footer.css: .footer-col__toggle).
 * ==========================================================
 */
( () => {
	const container = document.querySelector( '.footer-container' );
	if ( ! container ) {
		return;
	}

	const mobile = window.matchMedia( '(max-width: 48rem)' );
	// ستون فرم مشاوره همیشه باز است و دکمه ندارد
	const columns = [ ...container.querySelectorAll( '.footer-col' ) ];
	const toggles = columns.filter( ( col ) => ! col.classList.contains( 'footer-form-col' ) );

	const setOpen = ( col, open ) => {
		col.classList.toggle( 'active', open );
		col.querySelector( ':scope > h2 > .footer-col__toggle' )?.setAttribute( 'aria-expanded', String( open ) );
	};

	/** متن عنوان را در دکمه بگذار (موبایل) یا دکمه را بردار (دسکتاپ). */
	const enhance = ( on ) => {
		toggles.forEach( ( col, i ) => {
			const heading = col.querySelector( ':scope > h2' );
			const content = col.querySelector( ':scope > .footer-content' );
			if ( ! heading || ! content ) {
				return;
			}
			const button = heading.querySelector( ':scope > .footer-col__toggle' );

			if ( on && ! button ) {
				content.id ||= `footer-col-content-${ i + 1 }`;
				const btn = document.createElement( 'button' );
				btn.type = 'button';
				btn.className = 'footer-col__toggle';
				btn.setAttribute( 'aria-controls', content.id );
				btn.setAttribute( 'aria-expanded', 'false' );
				btn.append( ...heading.childNodes );
				heading.append( btn );
			} else if ( ! on && button ) {
				heading.append( ...button.childNodes );
				button.remove();
			}
		} );
	};

	/** وضعیت اولیه هر حالت: موبایل همه بسته جز فرم؛ دسکتاپ بدون کلاس active. */
	const apply = () => {
		const isMobile = mobile.matches;
		enhance( isMobile );
		toggles.forEach( ( col ) => setOpen( col, false ) );
		container.querySelector( '.footer-form-col' )?.classList.toggle( 'active', isMobile );
	};

	// کلیک روی کل عنوان (فلش ::after هم جزو h2 است، نه دکمه)؛ Enter/Space دکمه هم به اینجا می‌رسد
	container.addEventListener( 'click', ( event ) => {
		const button = event.target.closest( '.footer-col > h2' )?.querySelector( ':scope > .footer-col__toggle' );
		if ( ! button || ! mobile.matches ) {
			return;
		}
		const current = button.closest( '.footer-col' );
		const willOpen = ! current.classList.contains( 'active' );
		// فقط یک ستون باز
		toggles.forEach( ( col ) => setOpen( col, col === current && willOpen ) );
	} );

	mobile.addEventListener( 'change', apply );
	apply();
} )();
