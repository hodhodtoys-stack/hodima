<?php
/**
 * Media System — JSON-LD
 * Path: media-system/media-schema.php
 *
 * ویدیو (media-video.php)، صوت و FAQ به گراف واحد صفحه (hodima_schema_add
 * در hodima-core) اضافه می‌شوند.
 *
 * Discover (عنوان و موضوعات برگه‌ها) از نسخه 1.5.0 در ماژول «Google
 * Discover» افزونه سئو است و روی همان نود «#webpage» اضافه می‌شود؛ ادغام
 * Yoast (wpseo_schema_article) هم برداشته شد (سایت Yoast ندارد).
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/* =====================================================================
 * ساخت نودها
 * ===================================================================== */

/**
 * یک نوع نود را می‌سازد و به گراف می‌دهد (هر نوع برای هر شیء یک بار).
 *
 * @param string $type video | video_extra | audio | faq
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
	$title   = hodima_media_object_title( $object_id, $context );
	$brand   = (string) ( get_option( 'hodima_brand_name', '' ) ?: get_bloginfo( 'name' ) );

	$schema = match ( $type ) {
		'video'        => hodima_media_video_node( $object_id, $context ),
		'audio'        => hodima_media_audio_node( $data, $object_id, $context, $base, $title, $brand ),
		'faq'          => hodima_media_faq_node( $data, $base ),
		// ویدیوهای بیشتر: چند نود در یک گراف
		'video_extra'  => hodima_media_extra_video_graph( $object_id, $context ),
		default        => null,
	};

	if ( empty( $schema ) ) {
		return;
	}

	// گراف واحد صفحه؛ نودهای هم‌شناسه (مثلا FAQPage «#faq» ماژول AEO) ادغام می‌شوند.
	hodima_schema_add( $schema, 'hodima-media: media-schema (' . $type . ')' );
}

/**
 * ویدیوهای بیشتر به شکل گراف، یا null.
 *
 * @return array{'@graph': list<array<string, mixed>>}|null
 */
function hodima_media_extra_video_graph( int $object_id, string $context ): ?array {
	$nodes = hodima_media_extra_video_nodes( $object_id, $context );
	return $nodes ? [ '@graph' => $nodes ] : null;
}

/** AudioObject («#audio») یا null. */
function hodima_media_audio_node( array $data, int $object_id, string $context, string $base, string $title, string $brand ): ?array {

	$url = trim( (string) ( $data['voice_url'] ?? '' ) );

	if ( '' === $url ) {
		return null;
	}

	$url    = esc_url_raw( (string) preg_replace( '/\s+/', '%20', $url ) );
	$date   = hodima_media_stable_date( $data, 'voice', $object_id, $context );
	$direct = hodima_media_is_direct_audio( $url );
	$mime   = $direct ? (string) ( wp_check_filetype( (string) wp_parse_url( $url, PHP_URL_PATH ), wp_get_mime_types() )['type'] ?: '' ) : '';

	$node = [
		'@type'         => 'AudioObject',
		'@id'           => $base . '#audio',
		'isPartOf'      => [ '@id' => $base . '#webpage' ],
		'name'          => ! empty( $data['voice_title'] ) ? sanitize_text_field( (string) $data['voice_title'] ) : 'پادکست اختصاصی: ' . $title,
		'description'   => hodima_media_summary( $object_id, $context, 'توضیحات صوتی اختصاصی ' . $brand . ' برای ' . $title ),
		'datePublished' => $date,
		'uploadDate'    => $date,
		'inLanguage'    => hodima_media_language(),
	];

	/*
	 * contentUrl فقط آدرس خود فایل صوتی است. باگ قبلی: لینک صفحه یک سرویس
	 * (SoundCloud، Castbox) هم contentUrl می‌شد؛ حالا آن آدرس «url» است.
	 */
	if ( $direct ) {
		$node['contentUrl'] = $url;
		if ( '' !== $mime ) {
			$node['encodingFormat'] = $mime;
		}
	} else {
		$node['url'] = $url;
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
	$is_product = 'post' === $context && 'product' === get_post_type( $object_id );

	if ( 'yes' !== ( $data['enabled'] ?? '' ) || ! hodima_media_schema_page_shows_media( $object_id, $context ) ) {
		return;
	}

	// محصولات: product-schema-pro.php همین VideoObject واحد را با ارجاع به محصول می‌سازد.
	if ( ! $is_product ) {
		hodima_media_print_schema( 'video', $data, $object_id, $context );
	}

	// صوت و FAQ: هنگام نمایش واقعی (hodima_media_schema_on_render)، نه اینجا.
}

/**
 * آیا صفحه جاری بخش‌های رسانه این شیء را نشان می‌دهد؟
 *
 *   - دسته‌ای که قالب بخش رسانه‌اش را نمایش نمی‌دهد (دسته وبلاگ): خیر.
 *   - صفحه ۲ به بعد آرشیو دسته: خیر؛ قالب بخش‌ها را فقط در صفحه اول نشان
 *     می‌دهد. باگ قبلی: قالب برای حذف اسکیما در صفحه ۲ به بعد نام قدیمی
 *     تابع (hook_auto_inject_head_schema) را remove_action می‌کرد که دیگر
 *     وجود نداشت؛ FAQ و ویدیوی دسته روی همه صفحه‌های صفحه‌بندی اعلام می‌شد.
 */
function hodima_media_schema_page_shows_media( int $object_id, string $context ): bool {
	return hodima_media_is_displayed( $object_id, $context )
		&& ! ( 'term' === hodima_media_context( $context ) && is_paged() );
}

/**
 * اسکیمای صوت و FAQ فقط وقتی همان بخش واقعا در صفحه نمایش داده شده
 * (شورت‌کد اجرا شده و خروجی داشته) و شیء همان نوشته/ترم صفحه جاری است.
 *
 * باگ قبلی: FAQ و صوت در head بر اساس داده چاپ می‌شدند، جدا از اینکه قالب
 * آن‌ها را نشان می‌دهد یا نه: صفحه ۲ دسته‌ها، دسته‌های وبلاگ، محصولی که
 * «سوالات متداول» آن در تنظیمات قالب خاموش است. گوگل اسکیمای محتوای
 * دیده‌نشده را خلاف قانون می‌داند.
 *
 * نود تا فوتر نگه داشته و پیش از چاپ گراف واحد (اولویت ۹۹۹۹ Core) اضافه
 * می‌شود؛ بدون Core هم فالبک تگ جدا در فوتر چاپ می‌شود، نه وسط قالب.
 */
function hodima_media_schema_on_render( string $type, int $object_id, string $context, array $data ): void {

	if ( is_admin() || is_feed() || ! hodima_media_is_queried( $object_id, $context ) ) {
		return;
	}

	$queue = &hodima_media_schema_queue();
	$queue[ $type . ':' . $context . ':' . $object_id ] = [ $type, $data, $object_id, $context ];
}

/** صف نودهای نمایش‌داده‌شده (با ارجاع). */
function &hodima_media_schema_queue(): array {
	static $queue = [];
	return $queue;
}

add_action( 'wp_footer', static function (): void {
	$queue = &hodima_media_schema_queue();
	foreach ( $queue as [ $type, $data, $object_id, $context ] ) {
		hodima_media_print_schema( $type, $data, $object_id, $context );
	}
	$queue = [];
}, 1 );
