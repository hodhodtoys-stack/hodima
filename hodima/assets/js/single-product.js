/**
 * Single product — quantity rules
 * Version: 3.0.0 (نوسازی قالب، مرحله ۴: بدون var، همان قانون‌ها)
 *
 * دو قانون حداقل در این قالب وجود دارد:
 *   ۱. حداقل *مبلغ* سفارش (فیلد «حداقل سفارش» محصول) — سمت سرور در
 *      افزونه Hodima Commerce اعمال و به صورت ویژگی min روی فیلد تعداد چاپ می‌شود؛
 *      هنگام افزودن به سبد و در خود سبد هم بررسی می‌شود.
 *   ۲. «حداقل خرید» از جدول توضیح کوتاه — تعداد و گام (مضرب‌ها).
 *
 * نسخه 1.x قانون ۲ را بعد از بارگذاری صفحه اعمال می‌کرد و min سرور را
 * *بازنویسی* می‌کرد؛ اگر عدد جدول کمتر بود، حداقل پایین‌تر از حداقل
 * واقعی سرور نمایش داده می‌شد و مشتری بعد از کلیک خطا می‌گرفت. حالا
 * حداقل نهایی = بیشترینِ دو قانون، و گام از جدول.
 */
( () => {
	/** ارقام فارسی و عربی به لاتین. */
	const toLatin = ( str ) => String( str )
		.replace( /[۰-۹]/g, ( d ) => String( '۰۱۲۳۴۵۶۷۸۹'.indexOf( d ) ) )
		.replace( /[٠-٩]/g, ( d ) => String( '٠١٢٣٤٥٦٧٨٩'.indexOf( d ) ) );

	/** عدد ردیف «حداقل خرید» جدول توضیح کوتاه (آخرین ردیف منطبق)، یا ۰. */
	const tableMinQty = () => {
		let found = 0;
		document.querySelectorAll( '.woocommerce-product-details__short-description table tr' ).forEach( ( row ) => {
			const cells = row.querySelectorAll( 'td, th' );
			if ( cells.length < 2 || ! cells[ 0 ].textContent.includes( 'حداقل خرید' ) ) {
				return;
			}
			const m = toLatin( cells[ 1 ].textContent ).match( /\d+/ );
			if ( m ) {
				found = Number.parseInt( m[ 0 ], 10 );
			}
		} );
		return found;
	};

	const init = () => {
		const qty = document.querySelector( 'form.cart .qty, .product-add-to-cart-box .qty' );
		if ( ! qty ) {
			return;
		}

		const serverMin = Number.parseInt( qty.getAttribute( 'min' ), 10 ) || 1;
		const tableMin = tableMinQty();
		const step = tableMin > 1 ? tableMin : ( Number.parseInt( qty.getAttribute( 'step' ), 10 ) || 1 );
		let min = Math.max( serverMin, tableMin || 1 );

		// حداقل باید مضربی از گام باشد
		if ( step > 1 && 0 !== min % step ) {
			min = Math.ceil( min / step ) * step;
		}

		if ( min <= 1 && step <= 1 ) {
			return;
		}

		qty.min = String( min );
		qty.step = String( step );
		if ( ( Number.parseInt( qty.value, 10 ) || 0 ) < min ) {
			qty.value = String( min );
		}

		qty.addEventListener( 'change', () => {
			let v = Number.parseInt( toLatin( qty.value ), 10 );
			if ( Number.isNaN( v ) || v < min ) {
				v = min;
			}
			if ( step > 1 && 0 !== v % step ) {
				const r = v % step;
				v = v - r + ( r >= step / 2 ? step : 0 );
				if ( v < min ) {
					v = min;
				}
			}
			qty.value = String( v );
		} );
	};

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
