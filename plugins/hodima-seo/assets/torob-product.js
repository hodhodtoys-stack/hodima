/**
 * پیش‌نمایش زنده نام ترب در تب «ترب» ویرایش محصول (Hodima SEO، inc/torob.php).
 * همان قاعده سمت سرور (hodima_seo_torob_info): نام دلخواه، وگرنه شکل نام با
 * تعداد/واحد دستی یا خودکار — فقط وقتی قیمت ترب مبلغ حداقل سفارش است.
 */
/* global document */
( () => {
	const box = document.querySelector( '[data-hodima-torob]' );
	if ( ! box ) {
		return;
	}

	const out = box.querySelector( '[data-hodima-torob-name]' );
	const field = ( id ) => document.getElementById( id );
	const inputs = {
		name: field( 'title' ),
		qty: field( '_hodima_torob_qty' ),
		unit: field( '_hodima_torob_unit' ),
		custom: field( '_hodima_torob_title' ),
	};

	const persian = ( text ) => String( text ).replace( /\d/g, ( d ) => '۰۱۲۳۴۵۶۷۸۹'[ d ] ).replace( /\./g, '٫' );
	const latin = ( text ) => String( text ).replace( /[۰-۹]/g, ( d ) => '۰۱۲۳۴۵۶۷۸۹'.indexOf( d ) );
	const value = ( input ) => ( input ? input.value.trim() : '' );

	const render = () => {
		const custom = value( inputs.custom );
		const name = value( inputs.name ) || out.textContent;
		const qtyRaw = Number.parseFloat( latin( value( inputs.qty ) ).replace( ',', '.' ) );
		const qty = qtyRaw > 0 ? String( qtyRaw ) : box.dataset.autoQty;
		const unit = value( inputs.unit ) || box.dataset.autoUnit;

		let title = name;
		if ( custom ) {
			title = custom;
		} else if ( '1' === box.dataset.applies && qty ) {
			title = box.dataset.template
				.replaceAll( '{نام}', name ).replaceAll( '{name}', name )
				.replaceAll( '{تعداد}', persian( qty ) ).replaceAll( '{qty}', persian( qty ) )
				.replaceAll( '{واحد}', unit ).replaceAll( '{unit}', unit )
				.replace( /\s+/g, ' ' ).trim();
		}
		out.textContent = title;
	};

	Object.values( inputs ).forEach( ( input ) => input?.addEventListener( 'input', render ) );
	render();
} )();
