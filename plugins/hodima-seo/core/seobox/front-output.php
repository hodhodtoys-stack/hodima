<?php
/**
 * SeoBox — front-end output (title, description, robots, canonical, Open Graph, Twitter)
 * Path: core/seobox/front-output.php
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =====================================================================
 * ۱. تشخیص صفحه
 * ===================================================================== */

/**
 * تشخیص یک‌باره نوع صفحه و جایی که تنظیمات سئوی آن ذخیره شده.
 *
 * - kind: front | home | shop | singular | term | search | 404 | author | date | archive | other
 * - id / meta_type: شیئی که متای _seobox_* از آن خوانده می‌شود (۰ / '' یعنی ندارد)
 *
 * باگ رفع‌شده: برگه «وبلاگ» (is_home) و برگه «فروشگاه» (آرشیو محصول) نه
 * is_singular هستند نه ترم؛ شناسه صفحه صفر می‌شد و عنوان، توضیحات،
 * noindex و canonical که مدیر در کادر سئوی همین برگه‌ها ذخیره کرده بود
 * روی سایت نادیده گرفته می‌شد.
 *
 * object_id هرگز به get_the_ID() فالبک نمی‌کند، چون در آرشیوها آن مقدار
 * به اولین نوشته لوپ اشاره می‌کند نه به خود صفحه.
 *
 * @return array{kind: string, id: int, meta_type: string, object_id: int, is_term: bool, is_singular: bool}
 */
function seobox_get_query_context(): array {

	static $memo = null;

	if ( null !== $memo ) {
		return $memo;
	}

	$page_id = static fn( mixed $id ): int => ( (int) $id > 0 && get_post( (int) $id ) instanceof WP_Post ) ? (int) $id : 0;

	[ $kind, $id, $type ] = match ( true ) {
		is_404()                                       => [ '404', 0, '' ],
		is_search()                                    => [ 'search', 0, '' ],
		is_front_page()                                => 'page' === get_option( 'show_on_front' )
			? [ 'front', $page_id( get_option( 'page_on_front' ) ), 'post' ]
			: [ 'front', 0, '' ],
		is_home()                                      => [ 'home', $page_id( get_option( 'page_for_posts' ) ), 'post' ],
		is_category() || is_tag() || is_tax()          => [ 'term', (int) get_queried_object_id(), 'term' ],
		function_exists( 'is_shop' ) && is_shop()      => [ 'shop', $page_id( function_exists( 'wc_get_page_id' ) ? wc_get_page_id( 'shop' ) : 0 ), 'post' ],
		is_singular()                                  => [ 'singular', (int) get_queried_object_id(), 'post' ],
		is_post_type_archive()                         => [ 'archive', 0, '' ],
		is_author()                                    => [ 'author', 0, '' ],
		is_date()                                      => [ 'date', 0, '' ],
		default                                        => [ 'other', 0, '' ],
	};

	if ( $id <= 0 ) {
		$type = '';
	}

	$context = [
		'kind'        => $kind,
		'id'          => $id,
		'meta_type'   => $type,
		// کلیدهای قدیمی برای سازگاری
		'object_id'   => $id,
		'is_term'     => 'term' === $kind,
		'is_singular' => in_array( $kind, [ 'singular', 'front' ], true ) && $id > 0,
	];

	// پیش از اجرای کوئری اصلی (هوک wp) نتیجه موقت است و ذخیره نمی‌شود
	if ( did_action( 'wp' ) ) {
		$memo = $context;
	}

	return $context;
}

/** متای سئوباکس صفحه فعلی. */
function seobox_context_meta( array $context, string $key ): string {
	return '' === $context['meta_type'] ? '' : (string) get_metadata( $context['meta_type'], $context['id'], '_seobox_' . $key, true );
}

/** متن ساده از خروجی فیلترهای وردپرس (wptexturize/esc_html نهادهای HTML می‌سازند). */
function seobox_decode( string $text ): string {
	return trim( wp_strip_all_tags( html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
}

/** عنوان یک نوشته بدون پیشوند «محافظت‌شده:» / «خصوصی:» (همان روش عنوان سند وردپرس). */
function seobox_post_title( int $post_id ): string {
	$post = get_post( $post_id );
	return $post instanceof WP_Post ? seobox_decode( (string) apply_filters( 'single_post_title', $post->post_title, $post ) ) : '';
}

/* =====================================================================
 * ۲. عنوان
 * ===================================================================== */

/** نام خود صفحه (مقدار %title%). */
function seobox_context_name( array $context ): string {

	$site = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );

	$name = match ( $context['kind'] ) {
		'404'      => 'صفحه پیدا نشد',
		'search'   => 'نتایج جستجو برای: ' . get_search_query( false ),
		'front'    => $site,
		'home', 'shop', 'singular' => $context['id'] > 0 ? seobox_post_title( $context['id'] ) : '',
		'term'     => seobox_decode( (string) single_term_title( '', false ) ),
		'archive'  => seobox_decode( (string) post_type_archive_title( '', false ) ),
		'author'   => (string) get_the_author_meta( 'display_name', (int) get_queried_object_id() ),
		// get_the_archive_title() پیشوند را در <span> می‌پیچد؛ قبلا تگ خام در عنوان چاپ می‌شد
		'date'     => seobox_decode( (string) get_the_archive_title() ),
		default    => '',
	};

	return '' !== $name ? $name : $site;
}

/**
 * عنوان نهایی صفحه.
 *
 * شماره صفحه: اگر عنوان %title% دارد، «صفحه N» به نام صفحه اضافه می‌شود
 * (مثل قبل: «اخبار - صفحه ۲ - هدهدلی»)؛ اگر عنوان سفارشی %title% ندارد
 * به آخر عنوان. قبلا عنوان سفارشی بدون %title% در همه صفحه‌های آرشیو
 * یکسان بود. متغیر %page% جای شماره را دستی تعیین می‌کند.
 */
function seobox_get_frontend_title(): string {

	static $memo = null;

	if ( null !== $memo ) {
		return $memo;
	}

	$context = seobox_get_query_context();
	$custom  = seobox_context_meta( $context, 'title' );
	$pattern = '' !== $custom ? $custom : seobox_default_title_pattern( 'front' === $context['kind'] );
	$name    = seobox_context_name( $context );
	$page    = seobox_page_label();

	if ( '' !== $page && ! str_contains( $pattern, '%page%' ) ) {
		if ( str_contains( $pattern, '%title%' ) ) {
			$name .= ' ' . seobox_separator() . ' ' . $page;
		} else {
			$pattern .= ' %sep% %page%';
		}
	}

	$title = seobox_parse_variables( $pattern, $context['id'], 'term' === $context['meta_type'] ? 'term' : 'post', $name );
	$title = '' !== $title ? $title : wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );

	if ( did_action( 'wp' ) ) {
		$memo = $title;
	}

	return $title;
}

/*
 * PHP_INT_MAX: قالب در اولویت ۱۰۰۰۰ برای صفحه‌های ۲ به بعد «صفحه N» را به
 * عنوان سند اضافه می‌کند؛ سئوباکس خودش شماره صفحه را دارد و باید آخرین
 * حرف را بزند تا wp_get_document_title() همان <title> چاپ‌شده باشد.
 */
add_filter( 'pre_get_document_title', static fn(): string => seobox_get_frontend_title(), PHP_INT_MAX );

add_action( 'init', static function (): void {
	remove_action( 'wp_head', '_wp_render_title_tag', 1 );
	remove_action( 'wp_head', 'rel_canonical' );
}, 9999 );

/* =====================================================================
 * ۳. توضیحات
 * ===================================================================== */

/**
 * توضیحات جایگزین وقتی مدیر توضیحات دستی ننوشته.
 * نوشته/برگه: خلاصه ← متن (رمزدار هیچ) · ترم: توضیح ترم · صفحه اصلی: شعار سایت.
 */
function seobox_fallback_description( array $context ): string {

	$text = '';

	if ( 'post' === $context['meta_type'] ) {
		$post = get_post( $context['id'] );
		$text = $post instanceof WP_Post ? seobox_post_fallback_description( $post ) : '';
	} elseif ( 'term' === $context['meta_type'] ) {
		$term = get_term( $context['id'] );
		$text = $term instanceof WP_Term ? seobox_term_fallback_description( $term ) : '';
	}

	if ( '' === $text && 'front' === $context['kind'] ) {
		$text = seobox_plain_text( wp_specialchars_decode( (string) get_bloginfo( 'description' ), ENT_QUOTES ) );
	}

	return $text;
}

/** توضیحات نهایی صفحه (صفحه ۲ به بعد: «- صفحه N» تا توضیحات تکراری نباشد). */
function seobox_get_frontend_description(): string {

	$context = seobox_get_query_context();

	if ( in_array( $context['kind'], [ '404', 'search' ], true ) ) {
		return '';
	}

	$desc = seobox_context_meta( $context, 'description' );

	if ( '' !== $desc ) {
		$desc = seobox_parse_variables( $desc, $context['id'], 'term' === $context['meta_type'] ? 'term' : 'post', seobox_context_name( $context ) );
	}

	/*
	 * نسخه قدیمی فقط وقتی متاتگ description و og:description چاپ می‌کرد که
	 * مدیر دستی توضیحات نوشته بود؛ پیش‌نمایش لینک در تلگرام و واتساپ فقط عنوان بود.
	 */
	if ( '' === $desc ) {
		$desc = seobox_fallback_description( $context );
	}

	$page = seobox_page_label();

	return ( '' !== $desc && '' !== $page ) ? $desc . ' - ' . $page : $desc;
}

/* =====================================================================
 * ۴. ربات‌ها — یک متاتگ، از طریق wp_robots
 * ===================================================================== */

/**
 * وضعیت index/follow صفحه فعلی.
 *
 * @return array{index: bool, follow: bool}
 */
function seobox_frontend_robots(): array {

	$context = seobox_get_query_context();
	$flags   = seobox_object_robots( $context['id'], '' === $context['meta_type'] ? 'post' : $context['meta_type'] );

	// صفحات بدون canonical پایدار
	if ( in_array( $context['kind'], [ '404', 'search' ], true ) ) {
		$flags['index'] = false;
	}

	return [ 'index' => $flags['index'], 'follow' => $flags['follow'] ];
}

/**
 * دستورات سئوباکس در همان متاتگ robots وردپرس.
 *
 * باگ رفع‌شده: سئوباکس متاتگ robots جدای خودش را چاپ می‌کرد و متاتگ
 * وردپرس هم می‌ماند — در جستجو دو متاتگ، و وقتی «از موتورهای جستجو بخواه
 * ایندکس نکنند» روشن بود یا در سبد خرید/تسویه/حساب کاربری ووکامرس،
 * وردپرس «noindex, nofollow» و سئوباکس «index, follow» می‌گفت. حالا یک
 * متاتگ؛ محدودیتی که وردپرس یا افزونه دیگر گذاشته (noindex/nofollow)
 * همیشه حفظ می‌شود و سئوباکس فقط می‌تواند محدودتر کند.
 *
 * دستورات پیش‌نمایش (max-*) فقط برای صفحه ایندکس‌پذیر: پیش‌فرض‌ها همان
 * توصیه گوگل‌اند (Discover تصویر بزرگ می‌خواهد) و مقدار ذخیره‌شده دوباره
 * اعتبارسنجی می‌شود چون مستقیم در متاتگ قرار می‌گیرد.
 *
 * @param array<string, bool|string> $robots
 * @return array<string, bool|string>
 */
function seobox_filter_wp_robots( array $robots ): array {

	// صفحه ورود و هر جایی بیرون از کوئری اصلی سایت
	if ( ! did_action( 'wp' ) ) {
		return $robots;
	}

	$flags    = seobox_frontend_robots();
	$noindex  = ! $flags['index'] || ! empty( $robots['noindex'] );
	$nofollow = ! $flags['follow'] || ! empty( $robots['nofollow'] );

	unset( $robots['index'], $robots['noindex'], $robots['follow'], $robots['nofollow'], $robots['max-snippet'], $robots['max-video-preview'], $robots['max-image-preview'] );

	$head = [
		$noindex ? 'noindex' : 'index'    => true,
		$nofollow ? 'nofollow' : 'follow' => true,
	];

	if ( ! $noindex ) {
		$context = seobox_get_query_context();
		$snippet = seobox_context_meta( $context, 'adv_snippet' );
		$video   = seobox_context_meta( $context, 'adv_video' );
		$image   = seobox_context_meta( $context, 'adv_image' );

		$head['max-snippet']       = ctype_digit( $snippet ) ? (string) (int) $snippet : '-1';
		$head['max-video-preview'] = ctype_digit( $video ) ? (string) (int) $video : '-1';
		$head['max-image-preview'] = in_array( $image, [ 'none', 'standard' ], true ) ? $image : 'large';
	}

	return $head + $robots;
}
add_filter( 'wp_robots', 'seobox_filter_wp_robots', 999 );

/* ============================================================
 * هدر X-Robots-Tag
 * ------------------------------------------------------------
 * روی template_redirect: اولین هوکی که هم کوئری کامل اجرا شده و هم
 * هنوز خروجی ارسال نشده. (قبلا روی wp_headers بود که پیش از کوئری
 * اجرا می‌شود و هدر هرگز ارسال نمی‌شد.)
 * فقط دستور خود صفحه (نه جستجو/۴۰۴ که متاتگ وردپرس دارند).
 * ============================================================ */
add_action( 'template_redirect', 'seobox_send_x_robots_tag', 20 );

function seobox_send_x_robots_tag(): void {

	if ( headers_sent() ) {
		return;
	}

	$context = seobox_get_query_context();

	if ( '' === $context['meta_type'] ) {
		return;
	}

	$flags      = seobox_object_robots( $context['id'], $context['meta_type'] );
	$directives = array_keys( array_filter( [ 'noindex' => ! $flags['index'], 'nofollow' => ! $flags['follow'] ] ) );

	if ( $directives ) {
		header( 'X-Robots-Tag: ' . implode( ', ', $directives ), true );
	}
}

/* =====================================================================
 * ۵. canonical
 * ===================================================================== */

/**
 * از موتور مشترک Core (hodima_get_canonical_url) تا دقیقا همان آدرسی باشد
 * که اسکیما در @id می‌نویسد؛ canonical دستی و صفحه‌بندی در آن لحاظ شده.
 * بدون Core: canonical دستی یا آدرس خود شیء.
 */
function seobox_get_frontend_canonical(): string {

	if ( function_exists( 'hodima_get_canonical_url' ) ) {
		return hodima_get_canonical_url();
	}

	$context = seobox_get_query_context();
	$custom  = seobox_context_meta( $context, 'canonical' );

	if ( '' !== $custom ) {
		return $custom;
	}

	return match ( true ) {
		'front' === $context['kind'] => home_url( '/' ),
		'post' === $context['meta_type'] => (string) get_permalink( $context['id'] ),
		'term' === $context['meta_type'] => is_wp_error( $link = get_term_link( $context['id'] ) ) ? '' : (string) $link,
		default => '',
	};
}

/* =====================================================================
 * ۶. تصویر شبکه‌های اجتماعی
 * ===================================================================== */

/**
 * @return array{url: string, width: int|string, height: int|string, type: string, alt: string}
 */
function seobox_attachment_image( int $attachment_id, string $alt = '' ): array {

	$src  = $attachment_id > 0 ? wp_get_attachment_image_src( $attachment_id, 'full' ) : false;
	$mime = $attachment_id > 0 ? (string) get_post_mime_type( $attachment_id ) : '';

	// SVG در og:image پشتیبانی نمی‌شود (فیسبوک، تلگرام، واتساپ)
	if ( ! is_array( $src ) || 'image/svg+xml' === $mime ) {
		return [ 'url' => '', 'width' => '', 'height' => '', 'type' => '', 'alt' => '' ];
	}

	$own_alt = trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );

	return [
		'url'    => (string) $src[0],
		'width'  => (int) $src[1] ?: '',
		'height' => (int) $src[2] ?: '',
		'type'   => $mime,
		'alt'    => '' !== $own_alt ? $own_alt : $alt,
	];
}

/**
 * تصویر صفحه: تصویر شاخص ← تصویر ترم ← لوگوی اسکیما ← آیکون سایت.
 *
 * تصویر ترم (thumbnail ووکامرس برای دسته/برچسب محصول، تصویر «دسته‌های
 * وبلاگ») اضافه شد؛ قبلا همه صفحه‌های دسته لوگو نشان می‌دادند.
 *
 * @return array{url: string, width: int|string, height: int|string, type: string, alt: string}
 */
function seobox_social_image( array $context, string $title ): array {

	$image = [ 'url' => '', 'width' => '', 'height' => '', 'type' => '', 'alt' => '' ];

	if ( 'post' === $context['meta_type'] && has_post_thumbnail( $context['id'] ) ) {
		$image = seobox_attachment_image( (int) get_post_thumbnail_id( $context['id'] ), $title );
	} elseif ( 'term' === $context['meta_type'] ) {
		foreach ( [ 'thumbnail_id', 'category_image_id' ] as $key ) {
			$image = seobox_attachment_image( (int) get_term_meta( $context['id'], $key, true ), $title );
			if ( '' !== $image['url'] ) {
				break;
			}
		}
	}

	/*
	 * لوگوی مرکزی اسکیما (پنل «اسکیما ← صفحه اصلی»). شناسه پیوست از آدرس
	 * یک روز کش می‌شود — قبلا روی هر صفحه بدون تصویر یک کوئری جدا بود.
	 * کلید همان کلید schema-helpers.php است.
	 */
	if ( '' === $image['url'] ) {
		$logo = (string) get_option( 'hodima_schema_homepage_logo', '' );

		if ( '' !== $logo && ! str_ends_with( strtolower( (string) wp_parse_url( $logo, PHP_URL_PATH ) ), '.svg' ) ) {
			$key     = 'hodima_logo_id_' . md5( $logo );
			$logo_id = get_transient( $key );

			if ( false === $logo_id ) {
				$logo_id = (int) attachment_url_to_postid( $logo );
				set_transient( $key, $logo_id, DAY_IN_SECONDS );
			}

			$image        = $logo_id ? seobox_attachment_image( (int) $logo_id, $title ) : $image;
			$image['url'] = '' !== $image['url'] ? $image['url'] : $logo;
		}
	}

	if ( '' === $image['url'] ) {
		$image = seobox_attachment_image( (int) get_option( 'site_icon' ), $title );
	}

	return $image;
}

/* =====================================================================
 * ۷. ابزارهای محصول و ویدیو
 * ===================================================================== */

/**
 * قیمت برای نمایش و برای Open Graph.
 *
 * برچسب نمایشی با واحد واقعی فروشگاه ساخته می‌شود. برای Open Graph کد
 * ISO 4217 لازم است؛ «IRT» کد رسمی نیست، پس تومان به ریال (×۱۰) و IRR
 * تبدیل می‌شود.
 *
 * @return array{label:string, amount:string, currency:string}
 */
function seobox_product_price_meta( string $price ): array {

	if ( '' === $price || ! is_numeric( $price ) || (float) $price <= 0 ) {
		return [ 'label' => '', 'amount' => '', 'currency' => '' ];
	}

	$currency = function_exists( 'get_woocommerce_currency' ) ? strtoupper( (string) get_woocommerce_currency() ) : 'IRR';
	$amount   = (float) $price;

	$names = [ 'IRR' => 'ریال', 'IRT' => 'تومان', 'IRHR' => 'هزار ریال', 'IRHT' => 'هزار تومان' ];
	$label = number_format( $amount ) . ' ' . ( $names[ $currency ] ?? $currency );

	// تبدیل به ریال برای کد ISO
	$to_rial = [ 'IRR' => 1, 'IRT' => 10, 'IRHR' => 1000, 'IRHT' => 10000 ];

	if ( isset( $to_rial[ $currency ] ) ) {
		return [
			'label'    => $label,
			'amount'   => (string) (int) round( $amount * $to_rial[ $currency ] ),
			'currency' => 'IRR',
		];
	}

	return [ 'label' => $label, 'amount' => (string) $amount, 'currency' => $currency ];
}

/** نوع MIME ویدیو، یا رشته خالی اگر آدرس فایل مستقیم ویدیو نیست. */
function seobox_video_mime( string $url ): string {

	if ( '' === $url ) {
		return '';
	}

	$ext = strtolower( pathinfo( (string) wp_parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );

	return [ 'mp4' => 'video/mp4', 'm4v' => 'video/mp4', 'webm' => 'video/webm', 'mov' => 'video/quicktime', 'ogv' => 'video/ogg' ][ $ext ] ?? '';
}

/** آدرس ویدیوی نوشته از سیستم رسانه (نام جدید، با فالبک نام قدیمی) یا متای video_url. */
function seobox_post_video_url( int $post_id ): string {

	$getter = match ( true ) {
		function_exists( 'hodima_media_get_data' ) => 'hodima_media_get_data',
		function_exists( 'hook_get_media_data' )   => 'hook_get_media_data',
		default                                    => '',
	};

	$url = '' !== $getter ? ( $getter( $post_id, 'post' )['video_url'] ?? '' ) : '';

	if ( ! is_string( $url ) || '' === $url ) {
		$url = get_post_meta( $post_id, 'video_url', true );
	}

	return is_string( $url ) ? trim( $url ) : '';
}

/** دسته اصلی نوشته (_hodima_primary_category، همان بردکرامب و اسکیما) یا اولین دسته. */
function seobox_post_section( int $post_id ): string {

	$primary = (int) get_post_meta( $post_id, '_hodima_primary_category', true );

	if ( $primary > 0 && has_term( $primary, 'category', $post_id ) ) {
		$term = get_term( $primary, 'category' );
		if ( $term instanceof WP_Term ) {
			return $term->name;
		}
	}

	$categories = get_the_category( $post_id );

	return $categories ? $categories[0]->name : '';
}

/* =====================================================================
 * ۸. خروجی head
 * ===================================================================== */

function seobox_output_front_meta(): void {

	$context   = seobox_get_query_context();
	$post_id   = 'post' === $context['meta_type'] ? $context['id'] : 0;
	$singular  = $post_id > 0 && in_array( $context['kind'], [ 'singular', 'front', 'home', 'shop' ], true );
	$site_name = wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES );
	$title     = seobox_get_frontend_title();
	$desc      = seobox_get_frontend_description();
	$canonical = seobox_get_frontend_canonical();

	/*
	 * og:type: نوشته «article» و محصول «product»؛ صفحه اصلی، وبلاگ،
	 * فروشگاه و آرشیوها «website». قبلا صفحه اصلی ثابت (یک برگه) article
	 * می‌گرفت، با article:author و article:published_time.
	 */
	$og_type = match ( true ) {
		'singular' === $context['kind'] && 'product' === get_post_type( $post_id ) => 'product',
		'singular' === $context['kind'] && $post_id > 0                            => 'article',
		default                                                                    => 'website',
	};

	$image = seobox_social_image( $context, $title );

	/*
	 * تصویر و عنوان شبکه‌های اجتماعی برای افزونه‌های دیگر (مثلا Discover
	 * سیستم رسانه: تصویر بزرگ ۱۶:۹ و «عنوان Discover» برای نوشته‌ها).
	 * <title> صفحه دست نمی‌خورد؛ فقط og:title و twitter:title.
	 */
	$social_title = $title;

	$term_id = 'term' === $context['meta_type'] ? (int) $context['id'] : 0;

	if ( $singular || $term_id > 0 ) {
		// نوشته/برگه/محصول، یا دسته (مثلا Discover دسته‌های محصول)
		$social_title = $singular
			? (string) apply_filters( 'hodima_seobox_social_title', $title, $post_id )
			: (string) apply_filters( 'hodima_seobox_term_social_title', $title, $term_id );
		$filtered     = $singular
			? apply_filters( 'hodima_seobox_og_image', $image, $post_id )
			: apply_filters( 'hodima_seobox_term_og_image', $image, $term_id );
		if ( is_array( $filtered ) && ! empty( $filtered['url'] ) ) {
			$image = [
				'url'    => (string) $filtered['url'],
				'width'  => $filtered['width'] ?? '',
				'height' => $filtered['height'] ?? '',
				'type'   => (string) ( $filtered['type'] ?? '' ),
				'alt'    => (string) ( $filtered['alt'] ?? '' ),
			];
		}
	}

	$published = '';
	$modified  = '';

	if ( 'article' === $og_type || 'product' === $og_type ) {
		// get_post_datetime در خطا false برمی‌گرداند (نه null)
		$published = ( get_post_datetime( $post_id, 'date' ) ?: null )?->format( 'c' ) ?? '';
		$modified  = ( get_post_datetime( $post_id, 'modified' ) ?: null )?->format( 'c' ) ?? '';
	}

	// محصول: قیمت و موجودی
	$social_desc = $desc;
	$price       = [ 'label' => '', 'amount' => '', 'currency' => '' ];
	$price_name  = 'قیمت';
	$in_stock    = false;
	$stock_label = '';

	if ( 'product' === $og_type && function_exists( 'wc_get_product' ) ) {

		$product = wc_get_product( $post_id );

		if ( $product instanceof WC_Product ) {

			// «قیمت تک» یا «حداقل سفارش» (تنظیمات قالب ← صفحه محصول؛ inc/product-price.php)
			$feed        = hodima_seo_product_feed_price( $product );
			$price       = seobox_product_price_meta( $feed['amount'] );
			$price_name  = $feed['min_order'] ? 'حداقل سفارش' : 'قیمت';
			$in_stock    = $product->is_in_stock();
			$stock_label = $in_stock ? 'موجود' : 'ناموجود';

			if ( '' !== $price['label'] ) {
				// بدون توضیحات، « - » آویزان آخر متن نمی‌ماند
				$badge       = '[' . ( $in_stock ? '✅' : '❌' ) . ' ' . $stock_label . ' | ' . ( $feed['min_order'] ? $price_name . ' ' : '' ) . $price['label'] . ']';
				$social_desc = '' !== $social_desc ? $badge . ' - ' . $social_desc : $badge;
			}
		}
	}

	/*
	 * og:video فقط برای فایل مستقیم ویدیو. نسخه قدیمی برای هر آدرسی (از
	 * جمله صفحه آپارات یا یوتیوب) نوع را video/mp4 اعلام می‌کرد و
	 * پیش‌نمایش در پلتفرم‌ها خراب می‌شد.
	 */
	$video_url  = $singular ? seobox_post_video_url( $post_id ) : '';
	$video_mime = seobox_video_mime( $video_url );

	$tags = [];
	$meta = static function ( string $attr, string $name, string $content ) use ( &$tags ): void {
		if ( '' !== $content ) {
			$tags[] = sprintf( '<meta %s="%s" content="%s">', $attr, esc_attr( $name ), esc_attr( $content ) );
		}
	};
	$url = static fn( string $u ): string => esc_url_raw( $u );

	$tags[] = '<title>' . esc_html( $title ) . '</title>';
	$meta( 'name', 'description', $desc );
	if ( '' !== $canonical ) {
		$tags[] = '<link rel="canonical" href="' . esc_url( $canonical ) . '">';
	}
	$meta( 'name', 'theme-color', '#25316a' );

	// og:locale قالب «fa_IR» می‌خواهد؛ get_locale همین شکل است
	$meta( 'property', 'og:locale', get_locale() );
	$meta( 'property', 'og:type', $og_type );
	$meta( 'property', 'og:title', $social_title );
	$meta( 'property', 'og:description', $social_desc );
	$meta( 'property', 'og:url', $url( $canonical ) );
	$meta( 'property', 'og:site_name', $site_name );
	$meta( 'property', 'og:updated_time', $modified );

	if ( '' !== $image['url'] ) {
		$meta( 'property', 'og:image', $url( $image['url'] ) );
		if ( str_starts_with( $image['url'], 'https://' ) ) {
			$meta( 'property', 'og:image:secure_url', $url( $image['url'] ) );
		}
		$meta( 'property', 'og:image:width', (string) $image['width'] );
		$meta( 'property', 'og:image:height', (string) $image['height'] );
		$meta( 'property', 'og:image:alt', $image['alt'] );
		$meta( 'property', 'og:image:type', $image['type'] );
	}

	if ( '' !== $video_mime ) {
		$meta( 'property', 'og:video', $url( $video_url ) );
		if ( str_starts_with( $video_url, 'https://' ) ) {
			$meta( 'property', 'og:video:secure_url', $url( $video_url ) );
		}
		$meta( 'property', 'og:video:type', $video_mime );
	}

	if ( 'article' === $og_type ) {
		$meta( 'property', 'article:published_time', $published );
		$meta( 'property', 'article:modified_time', $modified );
		$meta( 'property', 'article:author', (string) get_the_author_meta( 'display_name', (int) get_post_field( 'post_author', $post_id ) ) );
		if ( 'post' === get_post_type( $post_id ) ) {
			$meta( 'property', 'article:section', seobox_post_section( $post_id ) );
		}
	}

	if ( 'product' === $og_type && '' !== $price['amount'] ) {
		$meta( 'property', 'product:price:amount', $price['amount'] );
		$meta( 'property', 'product:price:currency', $price['currency'] );
		$meta( 'property', 'product:availability', $in_stock ? 'instock' : 'outofstock' );
	}

	/*
	 * کارت «player» توییتر یک صفحه HTML قابل‌جاسازی روی HTTPS می‌خواهد و
	 * باید توسط توییتر تأیید شود؛ summary_large_image همیشه کار می‌کند
	 * (بدون تصویر: summary).
	 */
	$meta( 'name', 'twitter:card', '' !== $image['url'] ? 'summary_large_image' : 'summary' );
	$meta( 'name', 'twitter:title', $social_title );
	$meta( 'name', 'twitter:description', $social_desc );
	if ( '' !== $image['url'] ) {
		$meta( 'name', 'twitter:image', $url( $image['url'] ) );
	}

	if ( 'product' === $og_type && '' !== $price['label'] ) {
		$meta( 'name', 'twitter:label1', $price_name );
		$meta( 'name', 'twitter:data1', $price['label'] );
		$meta( 'name', 'twitter:label2', 'دسترسی' );
		$meta( 'name', 'twitter:data2', $stock_label );
	}

	echo "\n<!-- SEOBOX UNIVERSAL META -->\n" . implode( "\n", $tags ) . "\n<!-- /SEOBOX UNIVERSAL META -->\n"; // phpcs:ignore WordPress.Security.EscapeOutput -- هر مقدار بالا escape شده
}
add_action( 'wp_head', 'seobox_output_front_meta', 1 );
