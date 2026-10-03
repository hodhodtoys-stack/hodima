<?php
/**
 * Media System — JSON-LD
 * Path: media-system/media-schema.php
 *
 * ویدیو (media-video.php)، صوت، FAQ و غنی‌سازی Discover برگه‌ها به گراف
 * واحد صفحه (hodima_schema_add در hodima-core) اضافه می‌شوند.
 *
 * «خلاصه هوش مصنوعی» (ai_summary) از نسخه ۴ هیچ‌جا استفاده نمی‌شود: نه
 * abstract، نه description. داده ذخیره‌شده پاک نمی‌شود.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/* =====================================================================
 * ادغام با Yoast (فقط اگر نصب باشد؛ در غیر این صورت بی‌اثر)
 * ===================================================================== */

/** عنوان Discover و موجودیت‌ها برای یک نود Article/WebPage. */
function hodima_media_article_enrichment( array $entity, array $data ): array {

	if ( ! empty( $data['discover_title'] ) ) {
		// عنوان جایگزین مقاله؛ headline همان عنوان اصلی صفحه می‌ماند
		$entity['alternativeHeadline'] = sanitize_text_field( (string) $data['discover_title'] );
	}

	$about = array_map(
		static fn( string $name ): array => [ '@type' => 'Thing', 'name' => $name ],
		hodima_media_parse_entities( $data['key_entities'] ?? '' )
	);

	if ( $about ) {
		$entity['about'] = $about;
	}

	return $entity;
}

add_filter( 'wpseo_schema_article', static function ( $entity, $context = null ) {
	$id   = isset( $context->id ) ? (int) $context->id : (int) get_queried_object_id();
	$data = $id ? hodima_media_get_data( $id, 'post' ) : [];
	return ( 'yes' === ( $data['enabled'] ?? '' ) ) ? hodima_media_article_enrichment( (array) $entity, $data ) : $entity;
}, 10, 2 );

/* =====================================================================
 * ساخت نودها
 * ===================================================================== */

/**
 * یک نوع نود را می‌سازد و به گراف می‌دهد (هر نوع برای هر شیء یک بار).
 *
 * @param string $type video | audio | faq | seo_discover
 */
function hodima_media_print_schema( string $type, array $data, int $object_id, string $context ): void {

	static $printed = [];

	$context = hodima_media_context( $context );
	$key     = $type . '_' . $context . '_' . $object_id;

	if ( isset( $printed[ $key ] ) ) {
		return;
	}
	$printed[ $key ] = true;

	$page_url = hodima_media_page_url( $object_id, $context );

	if ( '' === $page_url ) {
		return;
	}

	// esc_url_raw (نه esc_url که «&» را «&#038;» می‌کند و آدرس را در JSON خراب می‌کند)
	$base    = esc_url_raw( $page_url );
	$webpage = [ '@id' => $base . '#webpage' ];
	$title   = hodima_media_object_title( $object_id, $context );
	$brand   = (string) ( get_option( 'hodima_brand_name', '' ) ?: get_bloginfo( 'name' ) );

	$schema = match ( $type ) {
		'video'        => hodima_media_video_node( $object_id, $context ),
		'audio'        => hodima_media_audio_node( $data, $object_id, $context, $base, $title, $brand ),
		'faq'          => hodima_media_faq_node( $data, $base ),
		'seo_discover' => hodima_media_discover_node( $data, $context, $base, $title ),
		default        => null,
	};

	if ( empty( $schema ) ) {
		return;
	}

	// گراف واحد صفحه؛ نودهای هم‌شناسه (مثلا FAQPage «#faq» ماژول AEO) ادغام می‌شوند.
	hodima_schema_add( $schema, 'hodima-media: media-schema (' . $type . ')' );
}

/** AudioObject («#audio») یا null. */
function hodima_media_audio_node( array $data, int $object_id, string $context, string $base, string $title, string $brand ): ?array {

	$url = trim( (string) ( $data['voice_url'] ?? '' ) );

	if ( '' === $url ) {
		return null;
	}

	$url  = esc_url_raw( (string) preg_replace( '/\s+/', '%20', $url ) );
	$date = hodima_media_stable_date( $data, 'voice', $object_id, $context );
	$mime = (string) ( wp_check_filetype( (string) wp_parse_url( $url, PHP_URL_PATH ), wp_get_mime_types() )['type'] ?: '' );

	$node = [
		'@type'         => 'AudioObject',
		'@id'           => $base . '#audio',
		'isPartOf'      => [ '@id' => $base . '#webpage' ],
		'name'          => ! empty( $data['voice_title'] ) ? sanitize_text_field( (string) $data['voice_title'] ) : 'پادکست اختصاصی: ' . $title,
		'description'   => hodima_media_summary( $object_id, $context, 'توضیحات صوتی اختصاصی ' . $brand . ' برای ' . $title ),
		'contentUrl'    => $url,
		'datePublished' => $date,
		'uploadDate'    => $date,
		'inLanguage'    => hodima_media_language(),
	];

	if ( '' !== $mime ) {
		$node['encodingFormat'] = $mime;
	}

	$duration = hodima_media_duration_iso( $data['voice_duration'] ?? '' );
	if ( '' !== $duration ) {
		$node['duration'] = $duration;
	}

	if ( function_exists( 'hodima_seo_schema_organization_node' ) ) {
		$node['publisher'] = [ '@id' => trailingslashit( home_url() ) . '#organization' ];
	}

	$transcript = trim( (string) ( $data['voice_transcript'] ?? '' ) );
	if ( '' !== $transcript ) {
		$node['transcript'] = wp_strip_all_tags( $transcript );
	}

	if ( ! empty( $data['voice_keywords'] ) ) {
		$node['keywords'] = sanitize_text_field( (string) $data['voice_keywords'] );
	}

	return $node;
}

/** FAQPage («#faq») یا null. */
function hodima_media_faq_node( array $data, string $base ): ?array {

	$questions = [];

	foreach ( (array) ( $data['faq'] ?? [] ) as $item ) {
		if ( ! is_array( $item ) || empty( $item['q'] ) || empty( $item['a'] ) ) {
			continue;
		}
		$questions[] = [
			'@type'          => 'Question',
			'name'           => wp_strip_all_tags( (string) $item['q'] ),
			'acceptedAnswer' => [ '@type' => 'Answer', 'text' => wp_kses_post( (string) $item['a'] ) ],
		];
	}

	return $questions ? [
		'@type'      => 'FAQPage',
		'@id'        => $base . '#faq',
		'isPartOf'   => [ '@id' => $base . '#webpage' ],
		'mainEntity' => $questions,
	] : null;
}

/** غنی‌سازی Discover برای برگه (نوشته را blog-schema.php پوشش می‌دهد)، یا null. */
function hodima_media_discover_node( array $data, string $context, string $base, string $title ): ?array {

	if ( empty( $data['discover_title'] ) && empty( $data['key_entities'] ) ) {
		return null;
	}

	// اگر Yoast نصب شود، همان فیلتر wpseo_schema_article بالا کار را انجام می‌دهد
	if ( 'post' === $context && defined( 'WPSEO_VERSION' ) ) {
		return null;
	}

	$webpage = [ '@id' => $base . '#webpage' ];

	return hodima_media_article_enrichment( [
		'@type'            => 'post' === $context ? 'Article' : 'WebPage',
		'@id'              => $base . '#media-article',
		'headline'         => $title,
		'isPartOf'         => $webpage,
		'mainEntityOfPage' => $webpage,
	], $data );
}

/* =====================================================================
 * تزریق خودکار
 * ===================================================================== */

add_action( 'wp_head', 'hodima_media_auto_inject_schema' );

function hodima_media_auto_inject_schema(): void {

	if ( is_admin() ) {
		return;
	}

	$queried = get_queried_object();

	if ( $queried instanceof WP_Post && in_array( $queried->post_type, hodima_media_post_types(), true ) ) {
		// FAQ و رسانه نوشته رمزدار نباید در اسکیما منتشر شود.
		if ( function_exists( 'hodima_post_content_is_visible' ) && ! hodima_post_content_is_visible( $queried ) ) {
			return;
		}
		$object_id = (int) $queried->ID;
		$context   = 'post';
	} elseif ( $queried instanceof WP_Term && in_array( $queried->taxonomy, hodima_media_taxonomies(), true ) ) {
		$object_id = (int) $queried->term_id;
		$context   = 'term';
	} else {
		return;
	}

	$data       = hodima_media_get_data( $object_id, $context );
	$post_type  = 'post' === $context ? (string) get_post_type( $object_id ) : '';
	$is_product = 'product' === $post_type;

	/*
	 * Discover برگه‌ها (عنوان Discover و موضوعات) — مثل نوشته‌ها به کلید
	 * «نمایش رسانه» وابسته نیست. نوشته‌ها را blog-schema.php پوشش می‌دهد.
	 */
	if ( ! $is_product && 'post' !== $post_type && hodima_media_discover_enabled( $context, $object_id ) ) {
		hodima_media_print_schema( 'seo_discover', $data, $object_id, $context );
	}

	if ( 'yes' !== ( $data['enabled'] ?? '' ) ) {
		return;
	}

	// محصولات: product-schema-pro.php همین VideoObject واحد را با ارجاع به محصول می‌سازد.
	if ( ! $is_product ) {
		hodima_media_print_schema( 'video', $data, $object_id, $context );
	}

	hodima_media_print_schema( 'audio', $data, $object_id, $context );
	hodima_media_print_schema( 'faq', $data, $object_id, $context );
}
