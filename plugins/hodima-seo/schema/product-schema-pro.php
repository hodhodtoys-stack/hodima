<?php
/**
 * اسکیمای محصول ووکامرس: Product، یا ProductGroup با تنوع‌ها (hasVariant)
 * Path: plugins/hodima-seo/schema/product-schema-pro.php
 *
 * تنها سازنده Product سایت (اسکیمای خود ووکامرس خاموش است). تنظیمات: «اسکیما ← محصولات».
 * واقعیت‌های فروشگاه: قیمت تومان → IRR ×۱۰ (hodima_seo_schema_price)، وزن متریک،
 * قیمت بیرونی فقط از hodima_seo_product_feed_price (inc/product-price.php).
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

if ( '1' === get_option( 'hodima_schema_product_enable', '1' ) ) {
	add_filter( 'woocommerce_structured_data_product', '__return_empty_array' );
	add_action( 'wp_head', 'hodima_seo_render_product_schema', 99 );
}

function hodima_seo_render_product_schema(): void {

	if ( ! is_singular( 'product' ) && ! ( function_exists( 'is_product' ) && is_product() ) ) {
		return;
	}

	$_product = wc_get_product( get_the_ID() );
	if ( ! $_product ) {
		return;
	}

	$product_id = $_product->get_id();
	$name       = $_product->get_name();
	$sku        = $_product->get_sku() ?: 'HOOK-' . $product_id;

	// فروشنده خالی = نام سازمان (همان نام #organization)
	$opt_seller = get_option( 'hodima_schema_product_seller' ) ?: hodima_seo_schema_org_name();

	// ۱. مشخصات از جدول مشخصات محصول (hodima-commerce): additionalProperty و رنگ/جنس/سایز/وزن واقعی (بدون پیش‌فرض)
	$additional_properties = [];
	$schema_facts          = [];

	if ( class_exists( 'Hodima_Product_Specs_Table' ) ) {
		$prepared_data = Hodima_Product_Specs_Table::get_instance()->_prepare_specs_data( $_product );

		if ( ! empty( $prepared_data['schema_properties'] ) ) {
			$additional_properties = $prepared_data['schema_properties'];
		}
		$schema_facts = (array) ( $prepared_data['schema_facts'] ?? [] );
	}

	// ۲. تصاویر: تصویر اصلی + گالری (حداکثر ۱۰؛ گوگل چند تصویر را ترجیح می‌دهد)، بدون تصویر → لوگو
	$images = [];
	foreach ( array_merge( [ (int) $_product->get_image_id() ], array_map( 'intval', (array) ( method_exists( $_product, 'get_gallery_image_ids' ) ? $_product->get_gallery_image_ids() : [] ) ) ) as $img_id ) {
		$img_url = $img_id ? (string) wp_get_attachment_url( $img_id ) : '';
		if ( '' !== $img_url && ! in_array( $img_url, $images, true ) && count( $images ) < 10 ) {
			$images[] = $img_url;
		}
	}
	$image_url = $images[0] ?? hodima_seo_schema_logo_url();

	/*
	 * موجودی از همان تابعی که صفحه محصول نشان می‌دهد (hodima-commerce). قبلا فقط
	 * is_in_stock() خوانده می‌شد و «موجود در انبار چین» در اسکیما InStock با
	 * ارسال ۱ تا ۴ روزه بود (ناهمخوانی با صفحه در Merchant Listings).
	 */
	$stock_status   = function_exists( 'hodima_product_schema_availability' )
		? hodima_product_schema_availability( $_product )
		: ( $_product->is_in_stock() ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock' );
	$stock_location = function_exists( 'hodima_product_stock_location' ) ? hodima_product_stock_location( $_product ) : '';

	// validFrom = آخرین تغییر محصول (قبلا «امروز» در هر رندر بود)
	$modified = $_product->get_date_modified();

	$make_offer = hodima_product_schema_offer_factory(
		[
			/*
			 * پایان سال میلادی بعد (همیشه ۱ تا ۲ سال جلوتر). قبلا «یک سال از امروز» بود
			 * و هر روز عوض می‌شد (اسکیمای همه محصولات هر روز «تغییر» می‌کرد).
			 * خطای «priceValidUntil not in ISO 8601» گوگل: wp_date روی این سایت شمسی می‌شد.
			 */
			'priceValidUntil'         => ( (int) hodima_seo_schema_local_iso( time(), 'Y' ) + 1 ) . '-12-31',
			'validFrom'               => $modified ? $modified->date( 'Y-m-d' ) : current_time( 'Y-m-d' ),
			/*
			 * فروشنده: سازمان درون‌خطی بدون @id. ارجاع به #organization عمدا نیست:
			 * نوع آن WholesaleStore است که اعتبارسنج گوگل برای creator تصویر نپذیرفت.
			 */
			'seller'                  => [ '@type' => 'Organization', 'name' => $opt_seller, 'url' => trailingslashit( home_url() ) ],
			'hasMerchantReturnPolicy' => hodima_seo_schema_return_policy(),
			'shippingDetails'         => hodima_product_schema_shipping_details( $stock_location, $_product ),
		]
	);

	/*
	 * هویت محصول در گراف: «{canonical}#product» وصل به نود صفحه (ItemPage «#webpage»
	 * از homepage-schema.php) که mainEntity اش با فیلتر پایین همین محصول است.
	 * (قبلا Product بدون @id و جدا از صفحه بود.)
	 */
	$page_url = hodima_product_schema_page_url( get_permalink( $product_id ) );

	$schema = [
		'@type'            => 'Product',
		'@id'              => $page_url . '#product',
		'url'              => $page_url,
		'mainEntityOfPage' => [ '@id' => $page_url . '#webpage' ],
		'name'             => $name,
		'image'            => count( $images ) > 1 ? $images : $image_url,
		'description'      => hodima_product_schema_description( $_product ),
		'sku'              => $sku,
		// mpn عمدا نیست: قبلا همیشه برابر SKU فروشگاه بود (داده ساختگی)
		'brand'            => [ '@type' => 'Brand', 'name' => hodima_product_schema_brand( $_product ) ?: get_option( 'hodima_schema_product_brand', 'هدهدلی' ) ],
	];

	$gtin = hodima_seo_schema_gtin( $_product );
	if ( '' !== $gtin ) {
		$schema['gtin'] = $gtin;
	}

	/*
	 * محصول متغیر → ProductGroup با تنوع‌ها. قبلا AggregateOffer (بازه قیمت) بود که
	 * گوگل فقط برای Product snippet می‌پذیرد، نه Merchant listings.
	 */
	$variants = $_product->is_type( 'variable' ) ? hodima_product_schema_variants( $_product, $page_url, $sku, $name, $image_url, $stock_status, $make_offer ) : null;

	if ( null !== $variants ) {
		$schema['@type']          = 'ProductGroup';
		$schema['productGroupID'] = $sku;
		if ( [] !== $variants['varies_by'] ) {
			$schema['variesBy'] = $variants['varies_by'];
		}
		$schema['hasVariant'] = $variants['items'];
		unset( $schema['gtin'] ); // بارکد مال هر تنوع است، نه گروه
	} elseif ( ! $_product->is_type( 'variable' ) ) {
		$offer = $make_offer( $_product, $page_url, $stock_status );
		if ( null !== $offer ) {
			$schema['offers'] = $offer;
		}
	}

	$origin = hodima_product_schema_country_of_origin( $_product );
	if ( '' !== $origin ) {
		$schema['countryOfOrigin'] = [ '@type' => 'Country', 'name' => $origin ];
	}

	/*
	 * نقطه اتصال مشترک additionalProperty: جدول مشخصات (بالا) مرجع است؛ ماژول‌های
	 * دیگر (مثل جدول دستی hodima-table) با اولویت بالاتر فقط نام‌های پر‌نشده را
	 * اضافه می‌کنند. ماژول تازه فقط به همین فیلتر وصل می‌شود.
	 */
	$additional_properties = (array) apply_filters( 'hodima_product_additional_properties', $additional_properties, (int) $product_id, $_product );

	$clean = hodima_product_schema_clean_properties( $additional_properties );

	/*
	 * گروه محصول: مشخصه‌ای که تنوع‌ها بر اساسش فرق دارند (مثلا «رنگ») روی گروه
	 * نمی‌آید؛ مقدار کلی جدول («تک رنگ»/«متنوع») با رنگ هر تنوع تناقض دارد.
	 */
	if ( ! empty( $schema['variesBy'] ) ) {
		$clean = array_values( array_filter( $clean, static fn( array $p ): bool =>
			! in_array( 'https://schema.org/' . hodima_product_schema_variant_property( '', $p['name'] ), (array) $schema['variesBy'], true )
		) );
	}

	if ( ! empty( $clean ) ) {
		$schema['additionalProperty'] = $clean;
	}

	/*
	 * رنگ، جنس و سایز به‌عنوان ویژگی‌های اصلی Product (گوگل additionalProperty را
	 * برای نتایج غنی نمی‌خواند). فقط مقدار واقعی، و در گروه محصول نه ویژگی‌ای که
	 * تنوع‌ها بر اساسش فرق دارند.
	 */
	foreach ( [ 'color', 'material', 'size' ] as $fact ) {
		$fact_value = trim( (string) ( $schema_facts[ $fact ] ?? '' ) );
		if ( '' === $fact_value || isset( $schema[ $fact ] )
			|| in_array( 'https://schema.org/' . $fact, (array) ( $schema['variesBy'] ?? [] ), true ) ) {
			continue;
		}
		$schema[ $fact ] = $fact_value;
	}

	// وزن: عدد + کد واحد (QuantitativeValue)، نه متن «۲۰ گرم»
	if ( ! empty( $schema_facts['weight']['value'] ) && ! empty( $schema_facts['weight']['unitCode'] ) ) {
		$schema['weight'] = [ '@type' => 'QuantitativeValue' ] + array_intersect_key( (array) $schema_facts['weight'], array_flip( [ 'value', 'unitCode', 'unitText' ] ) );
	}

	/*
	 * هر داده یک جا: ردیفی از additionalProperty که propertyID اش همان ویژگی اصلی
	 * ساخته‌شده بالاست (color، material، size، weight، countryOfOrigin) حذف می‌شود.
	 * ردیفی که ویژگی اصلی‌اش ساخته نشده (مثلا رنگ چندگزینه‌ای) می‌ماند.
	 */
	if ( ! empty( $schema['additionalProperty'] ) ) {
		$schema['additionalProperty'] = array_values( array_filter(
			$schema['additionalProperty'],
			static function ( array $p ) use ( $schema ): bool {
				$id = (string) ( $p['propertyID'] ?? '' );
				return ! ( str_starts_with( $id, 'https://schema.org/' ) && isset( $schema[ substr( $id, 19 ) ] ) );
			}
		) );
		if ( [] === $schema['additionalProperty'] ) {
			unset( $schema['additionalProperty'] );
		}
	}

	/*
	 * ویدیو: سازنده واحد سیستم رسانه — همان کاور، تاریخ، پخش‌کننده، فصل‌ها و متن
	 * کامل صفحه؛ فقط وقتی سیستم رسانه برای محصول روشن است. نود مستقل با @id
	 * (validator.schema.org فقط موجودیت‌های سطح‌بالا را فهرست می‌کند).
	 */
	$video_node = function_exists( 'hodima_media_video_node' )
		? hodima_media_video_node( (int) $product_id, 'post', [
			'_name'            => 'فیلم معرفی ' . $name,
			'mainEntityOfPage' => [ '@id' => $page_url . '#webpage' ],
			'about'            => [ '@id' => $page_url . '#product' ],
		] )
		: null;

	if ( null !== $video_node ) {
		$schema['subjectOf'] = [ '@id' => $video_node['@id'] ];
	}

	/*
	 * فقط میانگین امتیاز (خلاصه همه نظرها در یک مورد؛ ستاره‌های نتایج گوگل از همین
	 * است). نظرهای تکی (Review) عمدا نیستند: SEO 2.2.1 پنج نظر تازه را هم اعلام
	 * می‌کرد و گزارش Review snippets گوگل برای هر محصول ۶ مورد هم‌نام نشان می‌داد،
	 * بی‌فایده برای نمایش (خواست کاربر، SEO 2.2.2). دوباره اضافه نکنید.
	 */
	if ( $_product->get_review_count() > 0 ) {
		$schema['aggregateRating'] = [
			'@type'       => 'AggregateRating',
			'ratingValue' => $_product->get_average_rating(),
			'reviewCount' => $_product->get_review_count(),
			'bestRating'  => '5',
			'worstRating' => '1',
		];
	}

	$graph = [ $schema ];
	if ( null !== $video_node ) {
		$graph[] = $video_node;
	}

	hodima_schema_add( [ '@graph' => $graph ], 'hodima-seo: product-schema-pro' );
}

/**
 * برند خود محصول، یا رشته خالی (= برند پیش‌فرض پنل «اسکیما ← محصولات»).
 * منبع: «برندها»ی خود ووکامرس (تکسونومی product_brand، ووکامرس ۹.۶+)، وگرنه
 * ویژگی محصول به نام برند. قبلا همه محصولات برند پیش‌فرض را می‌گرفتند،
 * حتی کالای برند دیگر.
 */
function hodima_product_schema_brand( WC_Product $product ): string {

	if ( taxonomy_exists( 'product_brand' ) ) {
		$terms = get_the_terms( $product->get_id(), 'product_brand' );
		if ( is_array( $terms ) && isset( $terms[0] ) && $terms[0] instanceof WP_Term ) {
			return wp_strip_all_tags( $terms[0]->name );
		}
	}

	foreach ( [ 'pa_brand', 'pa_berand', 'brand', 'برند', 'نشان تجاری' ] as $attr ) {
		// چند مقدار با «, » برمی‌گردد؛ برند اول
		$value = trim( explode( ',', (string) $product->get_attribute( $attr ) )[0] );
		if ( '' !== $value ) {
			return wp_strip_all_tags( $value );
		}
	}

	return '';
}

/**
 * توضیح محصول: متن خود محصول، نه فهرست مشخصات.
 * ترتیب: توضیح کوتاه ← اولین بند (۲۰ نویسه به بالا) متن محصول ← الگوی پنل.
 * (قبلا «ویژگی‌ها: جنس: … | سایز: …» به توضیح چسبانده می‌شد — سومین تکرار همان داده‌ها.)
 */
function hodima_product_schema_description( WC_Product $product ): string {

	$text = trim( wp_strip_all_tags( strip_shortcodes( $product->get_short_description() ) ) );

	if ( '' === $text ) {
		$content_text = trim( (string) preg_replace( "/[ \t]+/u", ' ', wp_strip_all_tags( strip_shortcodes( (string) $product->get_description() ) ) ) );
		foreach ( (array) preg_split( '/\R\s*\R|\R/u', $content_text ) as $paragraph ) {
			if ( mb_strlen( trim( (string) $paragraph ) ) >= 20 ) {
				$text = trim( (string) $paragraph );
				break;
			}
		}
	}

	if ( '' !== $text ) {
		return wp_trim_words( $text, 40 );
	}

	return strtr(
		(string) get_option( 'hodima_schema_product_desc_tpl', 'خرید عمده [product_name] با بهترین قیمت از [site_name].' ),
		[ '[product_name]' => $product->get_name(), '[site_name]' => get_bloginfo( 'name' ) ]
	);
}

/**
 * جزئیات ارسال (OfferShippingDetails) پیشنهادهای محصول.
 *
 * هزینه (مبلغ پنل به تومان → IRR): متغیر (پیش‌فرض) بدون مبلغ یا فقط سقف (maxValue)؛
 * ثابت همان مبلغ (0 = رایگان). باگ قبلی: پیش‌فرض «0» یعنی «ارسال رایگان» به گوگل.
 *
 * زمان تحویل: انبار چین زمان جداگانه خودش را دارد (پنل). زمان دلخواه:
 *   add_filter( 'hodima_product_delivery_days', fn( $d, $loc ) => 'china' === $loc
 *       ? [ 'handling' => [ 2, 5 ], 'transit' => [ 20, 35 ] ] : $d, 10, 2 );
 *
 * @return array<string, mixed>
 */
function hodima_product_schema_shipping_details( string $stock_location, WC_Product $product ): array {

	$shipping_details = [
		'@type'               => 'OfferShippingDetails',
		'shippingDestination' => [
			'@type'          => 'DefinedRegion',
			'addressCountry' => 'IR',
		],
	];

	$shipping_max = hodima_seo_schema_panel_amount_rial( get_option( 'hodima_schema_product_shipping_max', '' ) );

	if ( 'fixed' === get_option( 'hodima_schema_product_shipping_mode', 'customer' ) ) {
		$shipping_details['shippingRate'] = [
			'@type'    => 'MonetaryAmount',
			'value'    => (string) hodima_seo_schema_panel_amount_rial( get_option( 'hodima_schema_product_shipping_cost', '0' ) ),
			'currency' => 'IRR',
		];
	} elseif ( $shipping_max > 0 ) {
		$shipping_details['shippingRate'] = [
			'@type'    => 'MonetaryAmount',
			'maxValue' => $shipping_max,
			'currency' => 'IRR',
		];
	}

	$delivery_default = 'china' === $stock_location
		? [
			'handling' => [ (int) get_option( 'hodima_schema_product_china_handling_min', 2 ), (int) get_option( 'hodima_schema_product_china_handling_max', 5 ) ],
			'transit'  => [ (int) get_option( 'hodima_schema_product_china_transit_min', 15 ), (int) get_option( 'hodima_schema_product_china_transit_max', 30 ) ],
		]
		: [
			'handling' => [ (int) get_option( 'hodima_schema_product_handling_min', 1 ), (int) get_option( 'hodima_schema_product_handling_max', 2 ) ],
			'transit'  => [ (int) get_option( 'hodima_schema_product_transit_min', 1 ), (int) get_option( 'hodima_schema_product_transit_max', 4 ) ],
		];

	$delivery = apply_filters( 'hodima_product_delivery_days', $delivery_default, $stock_location, $product );

	if ( is_array( $delivery ) && isset( $delivery['handling'], $delivery['transit'] ) ) {
		$shipping_details['deliveryTime'] = [
			'@type'        => 'ShippingDeliveryTime',
			'handlingTime' => [ '@type' => 'QuantitativeValue', 'minValue' => (int) $delivery['handling'][0], 'maxValue' => (int) $delivery['handling'][1], 'unitCode' => 'd' ],
			'transitTime'  => [ '@type' => 'QuantitativeValue', 'minValue' => (int) $delivery['transit'][0], 'maxValue' => (int) $delivery['transit'][1], 'unitCode' => 'd' ],
		];
	}

	return $shipping_details;
}

/**
 * سازنده پیشنهاد (Offer) یک محصول یا یک تنوع، با فیلدهای مشترک همه پیشنهادها.
 *
 * قیمت: «قیمت تک» یا «حداقل سفارش» (تنظیمات قالب ← صفحه محصول) → IRR. بدون قیمت →
 * بدون Offer (قبلا "price": "0" یعنی «رایگان»). حداقل سفارش عمدا در اسکیما نیست.
 *
 * @param array{priceValidUntil: string, validFrom: string, seller: array<string, string>, hasMerchantReturnPolicy: array<string, mixed>, shippingDetails: array<string, mixed>} $shared
 */
function hodima_product_schema_offer_factory( array $shared ): Closure {

	return static function ( WC_Product $item, string $offer_url, string $availability ) use ( $shared ): ?array {

		$price = hodima_seo_schema_price( hodima_seo_product_feed_price( $item )['amount'] );

		if ( null === $price ) {
			return null;
		}

		return [
			'@type'                   => 'Offer',
			'url'                     => $offer_url,
			'priceCurrency'           => $price[1],
			'price'                   => $price[0],
			'priceValidUntil'         => $shared['priceValidUntil'],
			'validFrom'               => $shared['validFrom'],
			'availability'            => $availability,
			'itemCondition'           => 'https://schema.org/NewCondition',
			'seller'                  => $shared['seller'],
			'hasMerchantReturnPolicy' => $shared['hasMerchantReturnPolicy'],
			'shippingDetails'         => $shared['shippingDetails'],
		];
	};
}

/**
 * کد ISO کشور سازنده (CN / IR) از ویژگی محصول یا فیلد «کشور سازنده»، یا رشته خالی.
 * همان منابع ردیف «کشور سازنده» جدول مشخصات.
 */
function hodima_product_schema_country_of_origin( WC_Product $product ): string {

	$origin_text = '';
	foreach ( [ 'تولید', 'pa_tolid', 'pa_country-of-origin', 'pa_origin', 'pa_country', 'pa_made-in', 'کشور سازنده', 'کشور', 'ساخت', 'ساخت کشور', 'مبدا' ] as $attr ) {
		$origin_text = trim( (string) $product->get_attribute( $attr ) );
		if ( '' !== $origin_text ) {
			break;
		}
	}
	if ( '' === $origin_text ) {
		$origin_text = (string) get_post_meta( $product->get_id(), '_hodima_country_of_origin', true );
	}

	// کد فیلد (cn/ir) روی متن بدون «ی»؛ نام کشور روی متن اصلی (ی عربی → فارسی) —
	// باگ قبلی: «چین» روی متن بدون «ی» جستجو می‌شد و «ساخت چین» هرگز پیدا نمی‌شد.
	$origin_norm = mb_strtolower( str_replace( [ 'ی', 'ي' ], '', $origin_text ) );
	$origin_fa   = str_replace( 'ي', 'ی', $origin_text );

	return match ( true ) {
		in_array( $origin_norm, [ 'cn', 'چن', 'china' ], true ), str_contains( $origin_fa, 'چین' )  => 'CN',
		in_array( $origin_norm, [ 'ir', 'اران', 'iran' ], true ), str_contains( $origin_fa, 'ایران' ) => 'IR',
		default => '',
	};
}

/**
 * پاک‌سازی نهایی additionalProperty: نام و مقدار تمیز، نام تکراری یک بار، حداکثر ۲۵
 * (گوگل فهرست‌های خیلی بلند را نادیده می‌گیرد؛ مقدار متناقض زیر یک نام سیگنال کیفیت پایین است).
 *
 * عدد (مثلا وزن ۲۰ با unitCode) عدد می‌ماند؛ فهرست چندگزینه‌ای فهرست می‌ماند؛ بقیه متن تمیز.
 * واحد (UN/CEFACT) و شناسه ویژگی اگر ماژول سازنده داده باشد می‌مانند.
 *
 * @param array<mixed> $properties
 * @return list<array<string, mixed>>
 */
function hodima_product_schema_clean_properties( array $properties ): array {

	$clean_value = static fn( mixed $v ): int|float|string => ( is_int( $v ) || is_float( $v ) ) ? $v : trim( wp_strip_all_tags( is_scalar( $v ) ? (string) $v : '' ) );

	$seen  = [];
	$clean = [];

	// نام متغیرها عمدا $prop_name است: نسخه قبلی $name را بازنویسی می‌کرد و نام *محصول* خراب می‌شد
	foreach ( $properties as $property ) {

		$prop_name  = isset( $property['name'] ) ? trim( wp_strip_all_tags( (string) $property['name'] ) ) : '';
		$prop_value = $property['value'] ?? '';

		if ( is_array( $prop_value ) ) {
			$prop_value = array_values( array_filter( array_map( $clean_value, $prop_value ), static fn( $v ): bool => '' !== $v ) );
			$prop_value = match ( count( $prop_value ) ) {
				0       => '',
				1       => $prop_value[0],
				default => $prop_value,
			};
		} else {
			$prop_value = $clean_value( $prop_value );
		}

		if ( '' === $prop_name || '' === $prop_value ) {
			continue;
		}

		$key = mb_strtolower( (string) preg_replace( '/\s+/u', ' ', $prop_name ) );

		if ( isset( $seen[ $key ] ) ) {
			continue;
		}

		$seen[ $key ] = true;

		$entry = [
			'@type' => 'PropertyValue',
			'name'  => $prop_name,
			'value' => $prop_value,
		];

		foreach ( [ 'unitCode', 'unitText', 'propertyID' ] as $extra_key ) {
			if ( isset( $property[ $extra_key ] ) && is_string( $property[ $extra_key ] ) && '' !== trim( $property[ $extra_key ] ) ) {
				$entry[ $extra_key ] = trim( wp_strip_all_tags( $property[ $extra_key ] ) );
			}
		}

		$clean[] = $entry;

		if ( count( $clean ) >= 25 ) {
			break;
		}
	}

	return $clean;
}

/**
 * تنوع‌های یک محصول متغیر برای ProductGroup.hasVariant، یا null اگر هیچ
 * تنوع قیمت‌داری نیست.
 *
 * هر تنوع: نام (نام محصول + مقدار ویژگی‌ها)، SKU خودش (اگر خالی یا همان SKU
 * والد باشد، «SKU والد-شناسه» تا یکتا بماند)، تصویر، GTIN، آدرس با انتخاب
 * همان تنوع (?attribute_pa_color=…) و پیشنهاد جداگانه. ویژگی‌هایی که گوگل
 * برای variesBy می‌شناسد (رنگ، سایز، جنس، طرح) به ویژگی schema.org خودشان
 * می‌روند؛ بقیه additionalProperty.
 *
 * @return array{items: list<array<string, mixed>>, varies_by: list<string>}|null
 */
function hodima_product_schema_variants( WC_Product $product, string $page_url, string $group_sku, string $group_name, string $fallback_image, string $group_availability, callable $make_offer ): ?array {

	$max      = max( 1, (int) apply_filters( 'hodima_product_schema_max_variants', 50 ) );
	$children = array_slice( array_map( 'intval', (array) $product->get_children() ), 0, $max );
	$items    = [];
	$varies   = [];

	// نوشته و متای همه تنوع‌ها با دو کوئری، نه دو کوئری برای *هر* تنوع (wc_get_product پایین)
	_prime_post_caches( $children, false, true );

	foreach ( $children as $variation_id ) {

		$variation = wc_get_product( $variation_id );

		if ( ! $variation || 'publish' !== $variation->get_status() ) {
			continue;
		}

		$props = [];
		$extra = [];
		$parts = [];

		foreach ( (array) $variation->get_attributes() as $key => $value ) {

			$value = (string) $value;
			if ( '' === $value ) {
				continue; // «هر مقدار» — این تنوع روی این ویژگی فرقی ندارد
			}

			$term  = taxonomy_exists( (string) $key ) ? get_term_by( 'slug', $value, (string) $key ) : false;
			$shown = $term ? (string) $term->name : rawurldecode( $value );
			$label = function_exists( 'wc_attribute_label' ) ? (string) wc_attribute_label( (string) $key, $product ) : (string) $key;
			$prop  = hodima_product_schema_variant_property( (string) $key, $label );

			if ( '' !== $prop ) {
				$props[ $prop ]  = $shown;
				$varies[ $prop ] = 'https://schema.org/' . $prop;
			} else {
				$extra[] = [ '@type' => 'PropertyValue', 'name' => wp_strip_all_tags( $label ), 'value' => wp_strip_all_tags( $shown ) ];
			}

			$parts[] = $shown;
		}

		// آدرس تنوع = canonical + همان رشته انتخاب ویژگی ووکامرس؛ نویسه غیر ASCII (فارسی) درصدی
		$query     = (string) preg_replace_callback( '/[^\x21-\x7e]+/', static fn( array $m ): string => rawurlencode( $m[0] ), (string) wp_parse_url( (string) $variation->get_permalink(), PHP_URL_QUERY ) );
		$offer_url = '' !== $query ? $page_url . ( str_contains( $page_url, '?' ) ? '&' : '?' ) . $query : $page_url;

		$availability = $variation->is_in_stock() ? $group_availability : 'https://schema.org/OutOfStock';
		$offer        = $make_offer( $variation, $offer_url, $availability );

		if ( null === $offer ) {
			continue; // تنوع بدون قیمت پیشنهادی ندارد و گوگل آن را رد می‌کند
		}

		$variant_sku = (string) $variation->get_sku();
		if ( '' === $variant_sku || $variant_sku === $group_sku ) {
			$variant_sku = $group_sku . '-' . $variation_id;
		}

		$image_id = (int) $variation->get_image_id();
		$image    = $image_id ? (string) wp_get_attachment_url( $image_id ) : '';

		$item = [
			'@type'                => 'Product',
			'@id'                  => $page_url . '#variant-' . $variation_id,
			'name'                 => [] !== $parts ? $group_name . ' - ' . implode( '، ', $parts ) : $group_name,
			'sku'                  => $variant_sku,
			'inProductGroupWithID' => $group_sku,
			'image'                => '' !== $image ? $image : $fallback_image,
		] + $props;

		$gtin = hodima_seo_schema_gtin( $variation );
		if ( '' !== $gtin ) {
			$item['gtin'] = $gtin;
		}

		if ( [] !== $extra ) {
			$item['additionalProperty'] = $extra;
		}

		$item['offers'] = $offer;
		$items[]        = $item;
	}

	return [] === $items ? null : [ 'items' => $items, 'varies_by' => array_values( $varies ) ];
}

/**
 * ویژگی schema.org یک ویژگی ووکامرس (برای variesBy)، یا رشته خالی.
 * گوگل برای تنوع فقط color، size، material، pattern، suggestedAge و suggestedGender را می‌شناسد.
 */
function hodima_product_schema_variant_property( string $key, string $label ): string {

	$slug  = mb_strtolower( rawurldecode( (string) preg_replace( '/^(attribute_)?(pa_)?/', '', $key ) ) );
	$label = mb_strtolower( trim( $label ) );

	$map = [
		'color'    => [ 'color', 'colour', 'rang', 'رنگ', 'رنگ‌بندی', 'رنگبندی' ],
		'size'     => [ 'size', 'saiz', 'سایز', 'اندازه' ],
		'material' => [ 'material', 'jens', 'جنس', 'متریال' ],
		'pattern'  => [ 'pattern', 'tarh', 'طرح', 'الگو' ],
	];

	foreach ( $map as $prop => $names ) {
		if ( in_array( $slug, $names, true ) || in_array( $label, $names, true ) ) {
			return $prop;
		}
	}

	return '';
}

/**
 * آدرس پایه شناسه‌ها — دقیقا همانی که homepage-schema.php برای «#webpage»
 * استفاده می‌کند، تا ارجاع‌ها به یک موجودیت برسند.
 */
function hodima_product_schema_page_url( mixed $fallback = '' ): string {
	$canonical = function_exists( 'hodima_get_canonical_url' ) ? hodima_get_canonical_url() : '';
	return '' !== $canonical ? $canonical : (string) $fallback;
}

/**
 * ItemPage محصول → mainEntity: #product
 * (ItemPage زیرمجموعه CreativeWork است و mainEntity را می‌پذیرد.)
 */
add_filter( 'hodima_schema_webpage_node', static function ( array $node, string $page_url ): array {

	if ( '1' !== get_option( 'hodima_schema_product_enable', '1' ) || ! is_singular( 'product' ) ) {
		return $node;
	}

	$node['mainEntity'] = [ '@id' => $page_url . '#product' ];

	return $node;
}, 10, 2 );
