/**
 * کادر «پیش‌خرید و پیش‌فاکتور» محصول: پیش‌نمایش زنده «مشتری در پنل این را می‌بیند».
 * همان جمله Admin_Product::rule_text() در سرور؛ بدون JS پیش‌نمایش همان مقدار ذخیره‌شده است.
 */
( function () {
	'use strict';

	const box = document.querySelector( '[data-hpp]' );
	if ( ! box ) {
		return;
	}
	const $ = ( sel ) => box.querySelector( sel );
	const unit = $( '[data-hpp-unit]' );
	const capacity = $( '[data-hpp-capacity]' );
	const min = $( '[data-hpp-min]' );
	const prebuy = $( '[data-hpp-prebuy]' );
	const preinvoice = $( '[data-hpp-preinvoice]' );
	const fa = ( n ) => Number( n ).toLocaleString( 'fa-IR', { maximumFractionDigits: 3 } );
	const num = ( input ) => Number( String( input.value ).replace( /[۰-۹]/g, ( d ) => '۰۱۲۳۴۵۶۷۸۹'.indexOf( d ) ) ) || 0;

	const update = () => {
		const name = unit.value;
		box.querySelectorAll( '[data-hpp-unit-name]' ).forEach( ( el ) => {
			el.textContent = name || 'واحد';
		} );
		const cap = Math.trunc( num( capacity ) );
		const least = num( min ) > 0 ? num( min ) : 1;
		const parts = [];
		if ( cap > 1 ) {
			parts.push( `هر ${ name } ${ fa( cap ) } عدد` );
		}
		parts.push( `حداقل ${ fa( least ) } ${ name }` );
		$( '[data-hpp-rule]' ).textContent = name ? parts.join( ' · ' ) : 'واحد فروش انتخاب نشده';
		$( '[data-hpp-check-unit]' ).dataset.state = name && cap > 0 ? 'ok' : 'warn';

		const on = [ prebuy.checked && 'پیش‌خرید', preinvoice.checked && 'پیش‌فاکتور' ].filter( Boolean );
		const status = $( '[data-hpp-status]' );
		status.textContent = on.length ? `در پنل: ${ on.join( ' و ' ) }` : 'در پنل نیست';
		status.classList.toggle( 'hpp__off', ! on.length );
	};

	[ unit, capacity, min, prebuy, preinvoice ].forEach( ( el ) => el.addEventListener( 'input', update ) );
	[ unit, prebuy, preinvoice ].forEach( ( el ) => el.addEventListener( 'change', update ) );
}() );
