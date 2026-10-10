<?php
/**
 * ابزارهای مشترک سازنده‌های اسکیما
 * Path: wp-content/plugins/hodima-seo/schema/schema-helpers.php
 *
 * چرا: لوگو، نود سازمان و تاریخ ویدیو در چند فایل جداگانه و هر بار کمی
 * متفاوت ساخته می‌شد:
 *   - سازمان در برگه‌های درباره‌ما/تماس (corporate-schema.php) نسخه دوم و
 *     ناقص‌تری از همان #organization بود (بدون ساعت کاری، knowsAbout، شعار،
 *     areaServed و با نوع شماره تماس متفاوت) — یک کسب‌وکار، دو تعریف.
 *   - تصویر پیش‌فرض محصول به دامنه اشتباه (hodima.com) اشاره می‌کرد و
 *     فقط صفحه اصلی لوگوی سفارشی قالب را می‌شناخت.
 *   - ویدیوی محصول/دسته بدون تاریخ، «یک ماه پیش از امروز» می‌گرفت؛ تاریخی
 *     که هر روز عوض می‌شد.
 * این فایل پیش از بقیه فایل‌های ماژول اسکیما لود می‌شود.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** آدرس لوگوی سازمان: لوگوی سفارشی قالب ← تنظیم «لوگو» پنل اسکیما ← مسیر پیش‌فرض همین سایت. */
function hodima_seo_schema_logo_url(): string {

	static $memo = null;

	if ( null !== $memo ) {
		return $memo;
	}

	$logo_id = (int) get_theme_mod( 'custom_logo' );
	$logo    = $logo_id ? (string) wp_get_attachment_image_url( $logo_id, 'full' ) : '';

	if ( '' === $logo ) {
		$logo = (string) get_option( 'hodima_schema_homepage_logo', '' );
	}

	if ( '' === $logo ) {
		$logo = trailingslashit( home_url() ) . 'wp-content/uploads/2025/06/logo2.png';
	}

	return $memo = $logo;
}

/**
 * نود لوگو (#logo) با ابعاد. گوگل لوگوی حداقل ۱۱۲×۱۱۲ می‌خواهد و بدون
 * width/height نمی‌تواند اندازه را بدون دانلود بسنجد (قبلا فقط url بود).
 * عمدا بدون contentUrl/creator: نود لوگو نباید در Image Metadata بررسی شود.
 */
function hodima_seo_schema_logo_node(): array {

	$url  = hodima_seo_schema_logo_url();
	$node = [
		'@type'   => 'ImageObject',
		'@id'     => trailingslashit( home_url() ) . '#logo',
		'url'     => $url,
		'caption' => hodima_seo_schema_org_name(),
	];

	$logo_id = (int) get_theme_mod( 'custom_logo' );

	if ( ! $logo_id ) {
		// شناسه پیوست از آدرس (یک کوئری)؛ یک روز کش — با تغییر آدرس کلید عوض می‌شود
		$key     = 'hodima_logo_id_' . md5( $url );
		$logo_id = get_transient( $key );
		if ( false === $logo_id ) {
			$logo_id = (int) attachment_url_to_postid( $url );
			set_transient( $key, $logo_id, DAY_IN_SECONDS );
		}
		$logo_id = (int) $logo_id;
	}

	$src = $logo_id ? wp_get_attachment_image_src( $logo_id, 'full' ) : false;

	if ( is_array( $src ) && ! empty( $src[1] ) && ! empty( $src[2] ) ) {
		$node['width']  = (int) $src[1];
		$node['height'] = (int) $src[2];
	}

	return $node;
}

/** نام سازمان (همان نام نود #organization). */
function hodima_seo_schema_org_name(): string {
	return (string) ( get_option( 'hodima_schema_homepage_org_name' ) ?: get_bloginfo( 'name' ) ?: 'بازرگانی هدهد' );
}

/** فهرست چندخطی تنظیمات → آرایه بدون خط خالی. */
function hodima_seo_schema_lines( mixed $raw ): array {

	if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
		return [];
	}

	return array_values( array_filter( array_map( 'trim', explode( "\n", $raw ) ), 'strlen' ) );
}

/** شماره تلفن به قالب بین‌المللی (۰۲۱… → +9821…). */
function hodima_seo_schema_phone( string $tel ): string {

	$cleaned = (string) preg_replace( '/[^0-9+]/', '', $tel );

	return str_starts_with( $cleaned, '0' ) ? '+98' . substr( $cleaned, 1 ) : $cleaned;
}

/**
 * «بازه قیمت» سازمان، یا رشته خالی.
 *
 * باگ قبلی: مقدار پیش‌فرض «IRR» بود — کد ارز، نه بازه قیمت (مثل «$$» یا
 * «۱۰۰ هزار تا ۵۰ میلیون تومان»). کد ارز تنها (سه حرف بزرگ) چاپ نمی‌شود.
 */
function hodima_seo_schema_price_range(): string {

	$range = trim( (string) get_option( 'hodima_schema_homepage_price_range', '' ) );

	return ( '' === $range || 1 === preg_match( '/^[A-Z]{3}$/', $range ) ) ? '' : $range;
}

/**
 * نود کامل سازمان (#organization) — تنها سازنده آن.
 *
 * homepage-schema.php (همه صفحه‌ها) و corporate-schema.php (درباره‌ما/تماس)
 * هر دو همین را چاپ می‌کنند، پس سازمان در همه صفحه‌ها یکسان است.
 */
function hodima_seo_schema_organization_node(): array {

	$site_url = trailingslashit( home_url() );

	$telephones = array_values( array_filter( array_map(
		'hodima_seo_schema_phone',
		hodima_seo_schema_lines( get_option( 'hodima_schema_geo_telephones', "+989124093140\n02177322684" ) )
	), 'strlen' ) );

	$node = [
		'@type'       => get_option( 'hodima_schema_homepage_org_type', 'WholesaleStore' ) ?: 'WholesaleStore',
		'@id'         => $site_url . '#organization',
		'name'        => hodima_seo_schema_org_name(),
		'url'         => $site_url,
		'logo'        => hodima_seo_schema_logo_node(),
		'image'       => [ '@id' => $site_url . '#logo' ],
		'description' => get_option( 'hodima_schema_geo_description', get_bloginfo( 'description' ) ) ?: 'پخش عمده اکسسوری مو',
		'telephone'   => $telephones[0] ?? '+989124093140',
		'address'     => [
			'@type'           => 'PostalAddress',
			'addressCountry'  => 'IR',
			'streetAddress'   => get_option( 'hodima_schema_homepage_street_address', 'تهرانپارس، خیابان احسان، پلاک ۸۴' ),
			'addressLocality' => get_option( 'hodima_schema_homepage_address_locality', 'تهران' ),
			'addressRegion'   => get_option( 'hodima_schema_homepage_address_region', 'تهران' ),
			'postalCode'      => get_option( 'hodima_schema_homepage_postal_code', '1657883361' ),
		],
	];

	$price_range = hodima_seo_schema_price_range();
	if ( '' !== $price_range ) {
		$node['priceRange'] = $price_range;
	}

	$alt_names = hodima_seo_schema_lines( get_option( 'hodima_schema_geo_alt_names', "عمده فروشی هدهد\nبازرگانی هدهد\nhodima" ) );
	if ( [] !== $alt_names ) {
		$node['alternateName'] = $alt_names;
	}

	$knows_about = array_values( array_unique( array_merge(
		hodima_seo_schema_lines( get_option( 'hodima_schema_homepage_knows_about' ) ),
		hodima_seo_schema_lines( get_option( 'hodima_schema_geo_knows_about', "واردات اکسسوری مو\nپخش عمده کلیپس\nفروش عمده کش مو\nتولید و پخش گلسر\nلوازم خرازی و خرج‌کار" ) )
	) ) );
	if ( [] !== $knows_about ) {
		$node['knowsAbout'] = $knows_about;
	}

	$social_links = array_values( array_unique( hodima_seo_schema_lines( get_option( 'hodima_schema_geo_socials', "https://instagram.com/hodima\nhttps://t.me/hodimaaccessory\nhttps://wa.me/989124093140" ) ) ) );
	if ( [] !== $social_links ) {
		$node['sameAs'] = $social_links;
	}

	$slogan = get_option( 'hodima_schema_homepage_slogan' );
	if ( ! empty( $slogan ) ) {
		$node['slogan'] = sanitize_text_field( (string) $slogan );
	}

	$catalog_name = get_option( 'hodima_schema_homepage_catalog_name' );
	if ( ! empty( $catalog_name ) ) {
		$node['hasOfferCatalog'] = [
			'@type' => 'OfferCatalog',
			'name'  => sanitize_text_field( (string) $catalog_name ),
		];
	}

	$area_countries = hodima_seo_schema_lines( get_option( 'hodima_schema_ai_countries' ) );
	if ( [] !== $area_countries ) {
		$node['areaServed'] = array_map( static fn( string $country ): array => [ '@type' => 'Country', 'name' => $country ], $area_countries );
	}

	$lang_raw = (string) get_option( 'hodima_schema_ai_languages', '' );
	if ( '' !== $lang_raw ) {
		$languages = array_values( array_filter( array_map( 'trim', explode( ',', $lang_raw ) ), 'strlen' ) );
		if ( [] !== $languages ) {
			$node['knowsLanguage'] = $languages;
		}
	}

	// نوع شماره‌ها از نسخه برگه تماس (دقیق‌تر): اولی فروش، بقیه پشتیبانی.
	// قبلا صفحه اصلی همه را «customer service» و فقط با بیش از یک شماره می‌نوشت.
	if ( [] !== $telephones ) {
		$node['contactPoint'] = array_map( static fn( string $tel, int $i ): array => [
			'@type'             => 'ContactPoint',
			'telephone'         => $tel,
			'contactType'       => 0 === $i ? 'sales' : 'customer support',
			'areaServed'        => 'IR',
			'availableLanguage' => [ 'Persian' ],
		], $telephones, array_keys( $telephones ) );
	}

	$node['openingHoursSpecification'] = [
		[
			'@type'     => 'OpeningHoursSpecification',
			'dayOfWeek' => [ 'Saturday', 'Sunday', 'Monday', 'Tuesday', 'Wednesday' ],
			'opens'     => get_option( 'hodima_schema_geo_weekday_open', '09:00' ),
			'closes'    => get_option( 'hodima_schema_geo_weekday_close', '17:30' ),
		],
		[
			'@type'     => 'OpeningHoursSpecification',
			'dayOfWeek' => [ 'Thursday' ],
			'opens'     => get_option( 'hodima_schema_geo_thursday_open', '09:00' ),
			'closes'    => get_option( 'hodima_schema_geo_thursday_close', '13:00' ),
		],
	];

	// سیاست مرجوعی پیش‌فرض کل فروشگاه (گوگل از ۲۰۲۴ روی سازمان هم می‌خواند؛
	// روی پیشنهاد هر محصول هم می‌ماند). همان تنظیم پنل «اسکیما ← محصولات».
	if ( hodima_seo_schema_products_enabled() ) {
		$node['hasMerchantReturnPolicy'] = hodima_seo_schema_return_policy();
	}

	// اتصال به ماژول AI GEO
	$node = (array) apply_filters( 'wpgi_ai_geo_schema_data', $node );

	// audience روی Organization را اعتبارسنج گوگل نامعتبر گزارش می‌کرد
	unset( $node['audience'] );

	return $node;
}

/**
 * تاریخ ISO 8601 از رشته ذخیره‌شده (ارقام فارسی هم)، یا رشته خالی.
 * بدون wp_date(): روی این سایت از مبدل تقویم شمسی عبور می‌کند.
 */
function hodima_seo_schema_iso_date( mixed $raw ): string {

	if ( function_exists( 'hodima_media_iso_date' ) ) {
		return hodima_media_iso_date( $raw );
	}

	$raw = trim( strtr( (string) $raw, [ '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9' ] ) );

	if ( '' === $raw ) {
		return '';
	}

	$ts = ctype_digit( $raw ) ? (int) $raw : strtotime( $raw );

	return ( $ts && $ts > 0 ) ? gmdate( 'c', $ts ) : '';
}

/**
 * تاریخ انتشار ویدیوی یک نوشته/محصول: تاریخ ذخیره‌شده ← تاریخ انتشار نوشته.
 * باگ قبلی: «یک ماه پیش از امروز» که هر روز عوض می‌شد.
 */
function hodima_seo_schema_post_video_date( mixed $stored, int $post_id ): string {

	$date = hodima_seo_schema_iso_date( $stored );

	if ( '' !== $date ) {
		return $date;
	}

	$gmt = (string) get_post_field( 'post_date_gmt', $post_id );

	if ( '' !== $gmt && ! str_starts_with( $gmt, '0000' ) ) {
		return gmdate( 'c', (int) strtotime( $gmt . ' UTC' ) );
	}

	return gmdate( 'c' );
}

/**
 * تاریخ انتشار ویدیوی یک دسته: تاریخ ذخیره‌شده، وگرنه اولین بار ثبت و
 * از آن به بعد ثابت (دسته تاریخ انتشار ندارد).
 *   - تاریخ خالی: در همان کلید «_hod_video_date» (که sitemap-core.php هم
 *     می‌خواند) ثبت می‌شود.
 *   - تاریخ نامفهوم: مقدار ادمین بازنویسی نمی‌شود؛ تاریخ اولین مشاهده در
 *     کلید جداگانه نگه داشته می‌شود.
 */
function hodima_seo_schema_term_video_date( mixed $stored, int $term_id ): string {

	$date = hodima_seo_schema_iso_date( $stored );

	if ( '' !== $date ) {
		return $date;
	}

	$is_empty = '' === trim( (string) $stored );
	$key      = $is_empty ? '_hod_video_date' : '_hodima_video_first_seen';

	if ( ! $is_empty ) {
		$date = hodima_seo_schema_iso_date( get_term_meta( $term_id, $key, true ) );
		if ( '' !== $date ) {
			return $date;
		}
	}

	$date = gmdate( 'c' );
	update_term_meta( $term_id, $key, $date );

	return $date;
}

/**
 * آدرس ویدیو برای JSON-LD: فایل مستقیم → contentUrl، صفحه آپارات/یوتیوب → embedUrl.
 * esc_url_raw (نه esc_url که «&» را به «&#038;» تبدیل می‌کند و آدرس را در JSON خراب می‌کند).
 *
 * @return array{0: string, 1: string} [ نام ویژگی, آدرس ]
 */
function hodima_seo_schema_video_url( string $url ): array {

	$url = esc_url_raw( (string) preg_replace( '/\s+/', '%20', trim( $url ) ) );

	$is_file = function_exists( 'hodima_media_is_direct_video' )
		? hodima_media_is_direct_video( $url )
		: 1 === preg_match( '/\.(mp4|m4v|webm|mov|ogv|ogg)$/i', (string) wp_parse_url( $url, PHP_URL_PATH ) );

	if ( $is_file ) {
		return [ 'contentUrl', $url ];
	}

	// صفحه آپارات/یوتیوب → آدرس خود پخش‌کننده (گوگل برای embedUrl آن را می‌خواهد)
	return [ 'embedUrl', function_exists( 'hodima_video_player_url' ) ? hodima_video_player_url( $url ) : $url ];
}

/* =====================================================================
 * قیمت و واحد پول
 * ===================================================================== */

/**
 * ضریب تبدیل واحدهای پول ایران (ووکامرس فارسی) به ریال.
 * گوگل فقط کد ISO 4217 را می‌پذیرد؛ «تومان» (IRT) کد رسمی نیست، پس قیمت
 * تومانی ×۱۰ و با IRR اعلام می‌شود. عدد روی صفحه همان تومان می‌ماند.
 */
const HODIMA_SEO_SCHEMA_TO_RIAL = [ 'IRR' => 1, 'IRT' => 10, 'IRHR' => 1000, 'IRHT' => 10000 ];

/** واحد پول فروشگاه (ووکامرس)، پیش‌فرض تومان. */
function hodima_seo_schema_store_currency(): string {
	return function_exists( 'get_woocommerce_currency' ) ? strtoupper( (string) get_woocommerce_currency() ) : 'IRT';
}

/**
 * قیمت فروشگاه → [ price, priceCurrency ] برای اسکیما، یا null اگر قیمت نیست.
 * ریال عدد صحیح است (بدون «.0» و بدون نویز اعشاری ضرب فلوت).
 *
 * @return array{0: string, 1: string}|null
 */
function hodima_seo_schema_price( mixed $amount ): ?array {

	if ( ! is_numeric( $amount ) || (float) $amount <= 0 ) {
		return null;
	}

	$currency = hodima_seo_schema_store_currency();

	if ( isset( HODIMA_SEO_SCHEMA_TO_RIAL[ $currency ] ) ) {
		return [ (string) (int) round( (float) $amount * HODIMA_SEO_SCHEMA_TO_RIAL[ $currency ] ), 'IRR' ];
	}

	return [ rtrim( rtrim( number_format( (float) $amount, 2, '.', '' ), '0' ), '.' ), $currency ];
}

/**
 * مبلغ‌های پنل اسکیمای محصول (هزینه/سقف ارسال) به ریال.
 *
 * پنل از نسخه ۱.۲.۰ مبلغ را به تومان می‌گیرد (مثل خود فروشگاه). مقدار
 * ذخیره‌شده قبل از آن ریال بود؛ تا اولین ذخیره پنل (که
 * hodima_schema_product_amount_unit = toman می‌نویسد) همان ریال خوانده می‌شود.
 */
function hodima_seo_schema_panel_amount_rial( mixed $stored ): int {

	$value = (int) preg_replace( '/[^0-9]/', '', (string) $stored );

	return 'toman' === get_option( 'hodima_schema_product_amount_unit', 'rial' ) ? $value * 10 : $value;
}

/**
 * سیاست مرجوعی از پنل «اسکیما ← محصولات» — روی هر پیشنهاد محصول و روی
 * سازمان (سیاست پیش‌فرض کل فروشگاه؛ گوگل از ۲۰۲۴ در سطح سازمان هم می‌خواند).
 * مهلت ۰ روز = مرجوعی پذیرفته نمی‌شود.
 */
function hodima_seo_schema_return_policy(): array {

	$days = (int) get_option( 'hodima_schema_product_return_days', 7 );

	if ( $days <= 0 ) {
		return [
			'@type'                => 'MerchantReturnPolicy',
			'applicableCountry'    => 'IR',
			'returnPolicyCategory' => 'https://schema.org/MerchantReturnNotPermitted',
		];
	}

	$policy = [
		'@type'                => 'MerchantReturnPolicy',
		'applicableCountry'    => 'IR',
		'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
		'merchantReturnDays'   => $days,
	];

	$method = match ( get_option( 'hodima_schema_product_return_method', 'mail' ) ) {
		'store' => 'https://schema.org/ReturnInStore',
		'none'  => '',
		default => 'https://schema.org/ReturnByMail',
	};

	if ( '' !== $method ) {
		$policy['returnMethod'] = $method;
	}

	$policy['returnFees'] = 'free' === get_option( 'hodima_schema_product_return_fees', 'customer' )
		? 'https://schema.org/FreeReturn'
		: 'https://schema.org/ReturnFeesCustomerResponsibility';

	/*
	 * نوع بازپرداخت (refundType) — Rich Results Test بدون آن هشدار غیرمهم
	 * «Missing field refundType» می‌دهد. پیش‌فرض «ذکر نشود»: ادعای
	 * «بازگشت کامل وجه» وقتی سیاست فروشگاه چیز دیگری است نادرست است؛
	 * مدیر در «اسکیما ← محصولات» انتخاب می‌کند.
	 */
	$refund = match ( get_option( 'hodima_schema_product_refund_type', 'none' ) ) {
		'full'     => 'https://schema.org/FullRefund',
		'exchange' => 'https://schema.org/ExchangeRefund',
		'credit'   => 'https://schema.org/StoreCreditRefund',
		default    => '',
	};

	if ( '' !== $refund ) {
		$policy['refundType'] = $refund;
	}

	return $policy;
}

/** آیا اسکیمای محصول فعال است و فروشگاه وجود دارد؟ */
function hodima_seo_schema_products_enabled(): bool {
	return '1' === get_option( 'hodima_schema_product_enable', '1' ) && class_exists( 'WooCommerce' );
}

/**
 * GTIN (بارکد) واقعی محصول، یا رشته خالی.
 * از فیلد «GTIN, UPC, EAN یا ISBN» خود ووکامرس (نسخه ۹.۲+). فقط ۸، ۱۲، ۱۳
 * یا ۱۴ رقم پذیرفته می‌شود؛ مقدار ساختگی/ناقص چاپ نمی‌شود.
 */
function hodima_seo_schema_gtin( object $product ): string {

	$gtin = method_exists( $product, 'get_global_unique_id' )
		? (string) $product->get_global_unique_id()
		: (string) get_post_meta( (int) $product->get_id(), '_global_unique_id', true );

	$gtin = (string) apply_filters( 'hodima_product_gtin', preg_replace( '/[^0-9]/', '', $gtin ), $product );

	return in_array( strlen( $gtin ), [ 8, 12, 13, 14 ], true ) ? $gtin : '';
}

/* =====================================================================
 * نویسنده و تصویر اصلی صفحه
 * ===================================================================== */

/** شناسه نود نویسنده — صفحه نویسنده + «#person». */
function hodima_seo_schema_person_id( int $user_id ): string {
	return get_author_posts_url( $user_id ) . '#person';
}

/**
 * نود Person نویسنده. مقاله با @id به آن ارجاع می‌دهد و صفحه نویسنده
 * (ProfilePage) آن را mainEntity دارد؛ قبلا نویسنده هر مقاله یک Person
 * جداگانه بدون شناسه بود و گوگل نمی‌توانست مقاله‌های یک نویسنده را به هم وصل کند.
 */
function hodima_seo_schema_person_node( int $user_id ): array {

	$node = [
		'@type' => 'Person',
		'@id'   => hodima_seo_schema_person_id( $user_id ),
		'name'  => (string) get_the_author_meta( 'display_name', $user_id ),
		'url'   => get_author_posts_url( $user_id ),
	];

	$bio = trim( wp_strip_all_tags( (string) get_the_author_meta( 'description', $user_id ) ) );
	if ( '' !== $bio ) {
		$node['description'] = $bio;
	}

	// وب‌سایت پروفایل؛ آدرس خود همین سایت (پیش‌فرض وردپرس برای مدیر) هویت بیرونی نیست
	$website = esc_url_raw( (string) get_the_author_meta( 'user_url', $user_id ) );
	if ( '' !== $website && wp_parse_url( $website, PHP_URL_HOST ) !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
		$node['sameAs'] = [ $website ];
	}

	/*
	 * غنی‌سازی (مثلا ماژول Google Discover: سمت، تخصص‌ها و پروفایل‌های
	 * معتبر نویسنده — E-E-A-T). @id و نام عوض نشود.
	 */
	return (array) apply_filters( 'hodima_seo_schema_person_node', $node, $user_id );
}

/**
 * تصویر اصلی یک نوشته/محصول در اسکیما (#primaryimage): [ آدرس, عرض, ارتفاع ] یا null.
 *
 * اگر ماژول «گوگل دیسکاور» برای این نوشته روشن است، تصویر دیسکاور (تصویر
 * جدای دیسکاور، وگرنه همان تصویر شاخص). باگ قبلی (تا SEO 2.1.4): og:image
 * تصویر دیسکاور بود ولی تصویر اصلی اسکیما تصویر شاخص؛ گوگل برای یک صفحه دو
 * تصویر متفاوت می‌دید و برش‌های تصویر دیسکاور کنار تصویر شاخص می‌آمدند.
 *
 * @return array{0: string, 1: int, 2: int}|null
 */
function hodima_seo_schema_featured_image( int $post_id ): ?array {

	if ( function_exists( 'hodima_seo_discover_for_post' ) && hodima_seo_discover_for_post( $post_id ) ) {
		$discover = hodima_seo_discover_image( $post_id );
		if ( null !== $discover ) {
			return [ $discover['url'], $discover['width'], $discover['height'] ];
		}
	}

	$thumb_id = (int) get_post_thumbnail_id( $post_id );
	$src      = $thumb_id ? wp_get_attachment_image_src( $thumb_id, 'full' ) : false;

	return is_array( $src ) && ! empty( $src[0] ) ? [ (string) $src[0], (int) $src[1], (int) $src[2] ] : null;
}
