<?php
/**
 * ماژول «Google Discover» — برش‌ها، Open Graph، فید و اسکیمای برگه‌ها
 * Path: core/discover/discover-front.php
 *
 * آنچه گوگل برای Discover می‌خواهد و این فایل فراهم می‌کند:
 *
 *   ۱. تصویر بزرگ: حداقل ۱۲۰۰ پیکسل عرض، ترجیحا ۱۶:۹، با
 *      max-image-preview:large (سئوباکس پیش‌فرض همین را چاپ می‌کند).
 *      از تصویر Discover (یا تصویر شاخص) هنگام ذخیره سه برش ۱۶:۹، ۴:۳ و
 *      ۱:۱ (عرض ۱۲۰۰) ساخته می‌شود؛ گوگل برای مقاله هر سه نسبت را در
 *      اسکیما توصیه می‌کند (blog-schema.php).
 *   ۲. og:image و og:title: کارت Discover از تگ‌های Open Graph خوانده
 *      می‌شود. «عنوان Discover» فقط og:title / twitter:title می‌شود (و
 *      alternativeHeadline در اسکیما)؛ <title> و h1 همان عنوان اصلی می‌مانند.
 *   ۳. فید: دکمه «دنبال کردن» Discover از فید RSS سایت استفاده می‌کند.
 *   ۴. برگه‌ها: عنوان Discover و موضوعات روی همان نود «#webpage».
 *      باگ قبلی (سیستم رسانه): یک نود WebPage دوم با شناسه «#media-article»
 *      برای همان آدرس ساخته می‌شد؛ گوگل دو صفحه برای یک آدرس می‌دید.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/* =====================================================================
 * برش‌ها
 * ===================================================================== */

// ساخت برش‌ها بعد از ذخیره نوشته (اولویت ۳۰: بعد از ذخیره کادر Discover) …
add_action( 'save_post', static function ( int $post_id ): void {

	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ! hodima_seo_discover_for_post( $post_id ) ) {
		return;
	}

	$image = hodima_seo_discover_image( $post_id );
	if ( null !== $image ) {
		hodima_seo_discover_make_crops( $image['id'] );
	}
}, 30 );

// … و وقتی تصویر شاخص جدا (ویرایشگر بلوکی با REST) عوض می‌شود.
foreach ( [ 'added_post_meta', 'updated_post_meta' ] as $hodima_seo_discover_hook ) {
	add_action( $hodima_seo_discover_hook, static function ( int $meta_id, int $post_id, string $meta_key, mixed $value ): void {
		if ( '_thumbnail_id' === $meta_key && hodima_seo_discover_for_post( $post_id ) ) {
			hodima_seo_discover_make_crops( (int) $value );
		}
	}, 10, 4 );
}
unset( $hodima_seo_discover_hook );

/* =====================================================================
 * Open Graph / توییتر (فیلترهای سئوباکس)
 * ===================================================================== */

/** «عنوان Discover» → og:title و twitter:title. */
add_filter( 'hodima_seobox_social_title', static function ( string $title, int $post_id ): string {

	if ( ! hodima_seo_discover_for_post( $post_id ) ) {
		return $title;
	}

	$discover = hodima_seo_discover_data( $post_id )['title'];

	return '' !== $discover ? $discover : $title;
}, 10, 2 );

/**
 * og:image بزرگ: برش ۱۶:۹ تصویر Discover (یا خود تصویر اگر حداقل ۱۲۰۰ عرض
 * دارد، یا مدیر خودش آن را به عنوان تصویر Discover انتخاب کرده).
 *
 * @param array{url: string, width: int|string, height: int|string, type: string, alt: string} $image
 */
add_filter( 'hodima_seobox_og_image', static function ( array $image, int $post_id ): array {

	if ( ! hodima_seo_discover_for_post( $post_id ) ) {
		return $image;
	}

	$source = hodima_seo_discover_image( $post_id );

	if ( null === $source ) {
		return $image;
	}

	$wide = hodima_seo_discover_wide_image( $post_id );

	$pick = match ( true ) {
		null !== $wide && $wide['width'] >= HODIMA_SEO_DISCOVER_MIN_WIDTH => $wide,
		$source['width'] >= HODIMA_SEO_DISCOVER_MIN_WIDTH,
		hodima_seo_discover_data( $post_id )['image_id'] === $source['id'] => $source,
		default => null,
	};

	return null === $pick ? $image : [
		'url'    => $pick['url'],
		'width'  => $pick['width'],
		'height' => $pick['height'],
		'type'   => $source['mime'],
		'alt'    => $source['alt'],
	];
}, 10, 2 );

/* =====================================================================
 * اسکیمای برگه‌ها (نوشته‌ها: blog-schema.php، محصول: product-schema-pro.php)
 * ===================================================================== */

add_filter( 'hodima_schema_webpage_node', static function ( array $node ): array {

	$post = is_singular() ? get_queried_object() : null;

	if ( ! $post instanceof WP_Post || in_array( $post->post_type, [ 'post', 'product' ], true ) || ! hodima_seo_discover_for_post( (int) $post->ID ) ) {
		return $node;
	}

	// برگه رمزدار: اطلاعات آن در اسکیما منتشر نشود
	if ( function_exists( 'hodima_post_content_is_visible' ) && ! hodima_post_content_is_visible( $post ) ) {
		return $node;
	}

	return hodima_seo_discover_enrich( $node, (int) $post->ID );
}, 15 );

/* =====================================================================
 * نویسنده (E-E-A-T): سمت، تخصص‌ها و پروفایل‌های معتبر روی نود Person
 * ===================================================================== */

add_filter( 'hodima_seo_schema_person_node', static function ( array $node, int $user_id ): array {

	$author = hodima_seo_discover_author( $user_id );

	if ( '' !== $author['job_title'] ) {
		$node['jobTitle'] = $author['job_title'];
	}

	if ( $author['knows_about'] ) {
		$node['knowsAbout'] = $author['knows_about'];
	}

	$same_as = array_values( array_unique( [ ...(array) ( $node['sameAs'] ?? [] ), ...$author['same_as'] ] ) );
	if ( $same_as ) {
		$node['sameAs'] = $same_as;
	}

	return $node;
}, 10, 2 );

/* =====================================================================
 * فید RSS («دنبال کردن» در Discover)
 * ===================================================================== */

/**
 * لینک فید در head. قالب automatic-feed-links را فعال نکرده و
 * feed_links_extra را هم عمدا حذف کرده (فید دیدگاه‌ها لازم نیست)؛ پس گوگل
 * فیدی برای دکمه «دنبال کردن» پیدا نمی‌کرد. فقط فید نوشته‌ها اعلام می‌شود.
 */
add_action( 'wp_head', static function (): void {

	if ( current_theme_supports( 'automatic-feed-links' ) || ! ( is_front_page() || is_home() || is_singular( 'post' ) || is_category() ) ) {
		return;
	}

	printf(
		'<link rel="alternate" type="application/rss+xml" title="%s" href="%s">' . "\n",
		esc_attr( get_bloginfo( 'name' ) . ' — نوشته‌ها' ),
		esc_url( get_feed_link() )
	);

	if ( is_category() ) {
		printf(
			'<link rel="alternate" type="application/rss+xml" title="%s" href="%s">' . "\n",
			esc_attr( single_cat_title( '', false ) ),
			esc_url( get_category_feed_link( (int) get_queried_object_id() ) )
		);
	}
}, 3 );

/*
 * «بهینه‌سازی بودجه خزش» (Google Indexing، router-pruning.php) همه فیدهای
 * پیش‌فرض را به صفحه اصلی ۳۰۱ می‌کرد؛ یعنی گوگل هیچ فیدی برای «دنبال
 * کردن» نداشت. فقط فید اصلی نوشته‌ها و فید دسته‌ها باز می‌ماند؛ فید
 * دیدگاه‌ها و فید تک‌نوشته (بی‌مصرف برای خزش) مثل قبل ریدایرکت می‌شوند.
 */
add_filter( 'hodima_gi_redirect_core_feeds', static function ( $redirect ): bool {
	// فید اصلی (/feed/): وردپرس در فید is_home را false می‌گذارد؛ پس «نه آرشیو، نه جستجو»
	$posts_feed = is_feed() && ! is_comment_feed() && ! is_singular() && ! is_search() && ( ! is_archive() || is_category() );
	return (bool) $redirect && ! $posts_feed;
} );

add_action( 'rss2_ns', static function (): void {
	echo 'xmlns:media="http://search.yahoo.com/mrss/"' . "\n\t";
} );

/** تصویر بزرگ هر نوشته در فید (فید پیش‌فرض وردپرس هیچ تصویری ندارد). */
add_action( 'rss2_item', static function (): void {

	$post_id = (int) get_the_ID();

	if ( ! hodima_seo_discover_for_post( $post_id ) ) {
		return;
	}

	$source = hodima_seo_discover_image( $post_id );

	if ( null === $source ) {
		return;
	}

	$wide = hodima_seo_discover_wide_image( $post_id ) ?? $source;

	printf(
		"\t\t<media:content url=\"%s\" medium=\"image\" type=\"%s\" width=\"%d\" height=\"%d\" />\n",
		esc_url( $wide['url'] ),
		esc_attr( $source['mime'] ),
		absint( $wide['width'] ),
		absint( $wide['height'] )
	);
} );
