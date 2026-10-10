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

/*
 * دسته محصول: بعد از ذخیره دسته. saved_term بعد از edited_{taxonomy} اجرا
 * می‌شود که کادر Discover در آن ذخیره می‌شود. باگ قبلی (تا SEO 2.1.1):
 * edited_term بود که وردپرس پیش از edited_{taxonomy} اجرا می‌کند؛ برش‌ها از
 * تصویر Discover قبلی ساخته می‌شد و تصویر تازه تا ذخیره دوم برش نداشت.
 * (ساخت دسته تازه هم: تصویر دسته‌ای که ووکامرس هنگام ساخت ذخیره کرده.)
 */
add_action( 'saved_term', static function ( int $term_id, int $tt_id, string $taxonomy ): void {
	if ( in_array( $taxonomy, hodima_seo_discover_taxonomies(), true ) && hodima_seo_discover_for_term( $term_id ) ) {
		$image = hodima_seo_discover_image( $term_id, 'term' );
		if ( null !== $image ) {
			hodima_seo_discover_make_crops( $image['id'] );
		}
	}
}, 30, 3 );

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
		'type'   => $pick['mime'], // نوع خود فایل انتخاب‌شده (برش WebP با اصل JPEG فرق دارد)
		'alt'    => $source['alt'],
	];
}, 10, 2 );

/* =====================================================================
 * دسته محصول: og:title و og:image (فیلترهای ترم سئوباکس)
 * ===================================================================== */

add_filter( 'hodima_seobox_term_social_title', static function ( string $title, int $term_id ): string {
	$discover = hodima_seo_discover_for_term( $term_id ) ? hodima_seo_discover_data( $term_id, 'term' )['title'] : '';
	return '' !== $discover ? $discover : $title;
}, 10, 2 );

/** @param array{url: string, width: int|string, height: int|string, type: string, alt: string} $image */
add_filter( 'hodima_seobox_term_og_image', static function ( array $image, int $term_id ): array {

	if ( ! hodima_seo_discover_for_term( $term_id ) ) {
		return $image;
	}

	$source = hodima_seo_discover_image( $term_id, 'term' );
	$wide   = hodima_seo_discover_wide_image( $term_id, 'term' );

	$pick = match ( true ) {
		null === $source => null,
		null !== $wide && $wide['width'] >= HODIMA_SEO_DISCOVER_MIN_WIDTH => $wide,
		$source['width'] >= HODIMA_SEO_DISCOVER_MIN_WIDTH,
		hodima_seo_discover_data( $term_id, 'term' )['image_id'] === $source['id'] => $source,
		default => null,
	};

	return null === $pick || null === $source ? $image : [
		'url'    => $pick['url'],
		'width'  => $pick['width'],
		'height' => $pick['height'],
		'type'   => $pick['mime'],
		'alt'    => $source['alt'],
	];
}, 10, 2 );

/* =====================================================================
 * اسکیما: عنوان Discover و موضوعات روی نود «#webpage»
 * (نوشته‌ها روی BlogPosting: blog-schema.php)
 * ===================================================================== */

add_filter( 'hodima_schema_webpage_node', static function ( array $node ): array {

	$queried = get_queried_object();

	// دسته محصول
	if ( $queried instanceof WP_Term ) {
		return hodima_seo_discover_for_term( (int) $queried->term_id ) && ! is_paged()
			? hodima_seo_discover_enrich( $node, (int) $queried->term_id, 'term' )
			: $node;
	}

	// برگه و محصول (و هر نوع دیگری غیر از نوشته)
	if ( ! is_singular() || ! $queried instanceof WP_Post || 'post' === $queried->post_type || ! hodima_seo_discover_for_post( (int) $queried->ID ) ) {
		return $node;
	}

	// برگه رمزدار: اطلاعات آن در اسکیما منتشر نشود
	if ( function_exists( 'hodima_post_content_is_visible' ) && ! hodima_post_content_is_visible( $queried ) ) {
		return $node;
	}

	return hodima_seo_discover_enrich( $node, (int) $queried->ID );
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
 * فیدی برای دکمه «دنبال کردن» پیدا نمی‌کرد. فید نوشته‌ها در صفحه اصلی،
 * وبلاگ، مقاله‌ها و دسته‌ها؛ از SEO 2.1.2 فید «محصولات تازه» (و فید هر دسته
 * محصول) هم در فروشگاه، دسته محصول و صفحه محصول تا کاربر بتواند فروشگاه
 * را هم در Discover دنبال کند.
 */
add_action( 'wp_head', static function (): void {

	$link = static function ( string $title, string $href ): void {
		if ( '' !== $href ) {
			printf( '<link rel="alternate" type="application/rss+xml" title="%s" href="%s">' . "\n", esc_attr( $title ), esc_url( $href ) );
		}
	};
	$site = (string) get_bloginfo( 'name' );

	if ( ! current_theme_supports( 'automatic-feed-links' ) && ( is_front_page() || is_home() || is_singular( 'post' ) || is_category() ) ) {
		$link( $site . ' — نوشته‌ها', get_feed_link() );
		if ( is_category() ) {
			$link( single_cat_title( '', false ) ?: '', get_category_feed_link( (int) get_queried_object_id() ) );
		}
	}

	// فروشگاه: نوع نوشته‌ای با بایگانی که Discover دارد (محصول)؛ نه نتیجه جستجو (noindex)
	foreach ( is_search() ? [] : hodima_seo_discover_feed_post_types() as $post_type ) {
		$term = get_queried_object();
		if ( is_post_type_archive( $post_type ) || is_singular( $post_type ) || ( $term instanceof WP_Term && in_array( $term->taxonomy, get_object_taxonomies( $post_type ), true ) && in_array( $term->taxonomy, hodima_seo_discover_taxonomies(), true ) ) ) {
			$link( $site . ' — ' . ( get_post_type_object( $post_type )?->labels->name ?? $post_type ) . ' تازه', (string) get_post_type_archive_feed_link( $post_type ) );
			if ( $term instanceof WP_Term && is_tax() ) {
				$link( $term->name, get_term_feed_link( $term->term_id, $term->taxonomy ) );
			}
		}
	}
}, 3 );

/**
 * نوع‌های نوشته غیر از «post» که فید بایگانی دارند و Discover برایشان روشن
 * است (پیش‌فرض: محصول، اگر ووکامرس صفحه فروشگاه دارد).
 *
 * @return list<string>
 */
function hodima_seo_discover_feed_post_types(): array {
	return array_values( array_filter(
		hodima_seo_discover_post_types(),
		static fn( string $type ): bool => ! in_array( $type, [ 'post', 'page' ], true ) && post_type_exists( $type ) && (bool) get_post_type_object( $type )?->has_archive
	) );
}

/*
 * «بهینه‌سازی بودجه خزش» (Google Indexing، router-pruning.php) همه فیدهای
 * پیش‌فرض را به صفحه اصلی ۳۰۱ می‌کرد؛ یعنی گوگل هیچ فیدی برای «دنبال
 * کردن» نداشت. فید اصلی نوشته‌ها، فید دسته‌ها، فید محصولات و فید دسته
 * محصول باز می‌مانند؛ فید دیدگاه‌ها، تک‌نوشته و بقیه (بی‌مصرف برای خزش)
 * مثل قبل ریدایرکت می‌شوند.
 */
add_filter( 'hodima_gi_redirect_core_feeds', static function ( $redirect ): bool {

	if ( ! is_feed() || is_comment_feed() || is_singular() || is_search() ) {
		return (bool) $redirect;
	}

	// فید اصلی (/feed/): وردپرس در فید is_home را false می‌گذارد؛ پس «نه آرشیو» یا دسته
	$open = ! is_archive() || is_category();

	foreach ( hodima_seo_discover_feed_post_types() as $post_type ) {
		$open = $open || is_post_type_archive( $post_type );
	}

	$taxonomies = hodima_seo_discover_taxonomies();
	$open       = $open || ( $taxonomies && is_tax( $taxonomies ) );

	return (bool) $redirect && ! $open;
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
		esc_attr( $wide['mime'] ),
		absint( $wide['width'] ),
		absint( $wide['height'] )
	);
} );
