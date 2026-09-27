<?php
/**
 * Media System — JSON-LD
 * Path: media-system/media-schema.php
 * Version: 3.0.0
 *
 * توابع زمان و تشخیص فایل به media-helpers.php منتقل شدند (بخشی از
 * قرارداد عمومی ماژول‌اند و آنجا یک بار تعریف می‌شوند).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =====================================================================
 * ادغام با افزونه‌های سئو (فقط اگر نصب باشند؛ در غیر این صورت بی‌اثر)
 * ===================================================================== */

/** فیلدهای مشترک خلاصه هوش مصنوعی و موجودیت‌ها برای یک نود Article. */
function hook_media_article_enrichment( array $entity, array $data ): array {

	if ( ! empty( $data['discover_title'] ) ) {
		$entity['headline']      = sanitize_text_field( $data['discover_title'] );
		$entity['alternateName'] = sanitize_text_field( $data['discover_title'] );
	}

	if ( ! empty( $data['ai_summary'] ) ) {
		$summary               = wp_strip_all_tags( (string) $data['ai_summary'] );
		$entity['abstract']    = $summary;
		$entity['description'] = $summary;
	}

	$about = array_map(
		static fn( string $name ): array => [ '@type' => 'Thing', 'name' => $name ],
		hook_parse_key_entities( $data['key_entities'] ?? '' )
	);

	if ( ! empty( $about ) ) {
		$entity['about'] = $about;
	}

	return $entity;
}

add_filter( 'rank_math/snippet/rich_snippet_article_entity', static function ( $entity ) {
	$id   = (int) get_queried_object_id();
	$data = $id ? hook_get_media_data( $id, 'post' ) : [];
	return ( 'yes' === ( $data['enabled'] ?? '' ) ) ? hook_media_article_enrichment( (array) $entity, $data ) : $entity;
} );

add_filter( 'wpseo_schema_article', static function ( $entity, $context = null ) {
	$id   = isset( $context->id ) ? (int) $context->id : (int) get_queried_object_id();
	$data = $id ? hook_get_media_data( $id, 'post' ) : [];
	return ( 'yes' === ( $data['enabled'] ?? '' ) ) ? hook_media_article_enrichment( (array) $entity, $data ) : $entity;
}, 10, 2 );

/* =====================================================================
 * چاپ نودها
 * ===================================================================== */

/**
 * تاریخ پایدار انتشار رسانه.
 *
 * ترتیب: تاریخ ثبت‌شده هنگام ذخیره آدرس فایل → برای نوشته، تاریخ
 * *انتشار* (نه آخرین ویرایش) → برای ترم، ثبت یک‌باره همین لحظه.
 *
 * نسخه قبلی برای ترم gmdate('c') می‌داد — در *هر* بازدید یک تاریخ
 * جدید. گوگل ویدیوی دسته‌بندی را هر بار «تازه آپلودشده» می‌دید.
 */
function hook_media_stable_date( array $data, string $kind, int $object_id, string $context ): string {

	$stored = hook_normalize_iso_date( $data[ "{$kind}_date" ] ?? '' );
	if ( '' !== $stored ) {
		return $stored;
	}

	if ( 'post' === $context ) {
		$gmt = (string) get_post_field( 'post_date_gmt', $object_id );
		if ( '' !== $gmt && ! str_starts_with( $gmt, '0000' ) ) {
			return gmdate( 'c', (int) strtotime( $gmt . ' UTC' ) );
		}
	}

	// ثبت یک‌باره تا از این به بعد ثابت بماند (داده‌های قبل از این نسخه)
	$now = gmdate( 'c' );
	'post' === $context
		? update_post_meta( $object_id, "_hook_{$kind}_date", $now )
		: update_term_meta( $object_id, "hook_{$kind}_date", $now );
	hook_get_media_data( $object_id, $context, true );

	return $now;
}

function hook_print_schema( $type, $data, $object_id, $context ) {

	static $printed = [];

	$object_id = (int) $object_id;
	$key       = $type . '_' . $context . '_' . $object_id;

	if ( isset( $printed[ $key ] ) ) {
		return;
	}
	$printed[ $key ] = true;

	$brand = (string) ( get_option( 'hodima_brand_name', '' ) ?: 'هدهدلی' );

	if ( 'post' === $context ) {
		$title = (string) get_the_title( $object_id );
	} else {
		$term  = get_term( $object_id );
		$title = $term instanceof WP_Term ? $term->name : '';
	}

	$page_url = hook_media_page_url( $object_id, $context );

	if ( '' === $page_url ) {
		return;
	}

	/*
	 * esc_url_raw به جای esc_url در همه شناسه‌ها و آدرس‌ها.
	 * esc_url برای خروجی HTML است و «&» را به «&#038;» تبدیل می‌کند؛
	 * داخل JSON یعنی آدرس خراب برای هر آدرسی که رشته کوئری دارد.
	 */
	$base    = esc_url_raw( $page_url );
	$webpage = [ '@id' => $base . '#webpage' ];
	$schema  = [];

	switch ( $type ) {

		case 'seo_discover':

			if ( empty( $data['discover_title'] ) && empty( $data['ai_summary'] ) && empty( $data['key_entities'] ) ) {
				return;
			}

			if ( 'post' === $context && ( defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) ) ) {
				return;
			}

			$schema = hook_media_article_enrichment( [
				'@type'            => ( 'post' === $context ) ? 'Article' : 'WebPage',
				'@id'              => $base . '#media-article',
				'headline'         => $title,
				'isPartOf'         => $webpage,
				'mainEntityOfPage' => $webpage,
			], $data );
			break;

		case 'video':

			if ( empty( $data['video_url'] ) ) {
				return;
			}

			// کاوری که مدیر انتخاب کرده (video_thumb در hook_get_media_data به آن برمی‌گردد)
			$thumb = (string) ( $data['video_thumb'] ?? '' );

			if ( '' === $thumb ) {
				$thumb = ( 'post' === $context )
					? (string) get_the_post_thumbnail_url( $object_id, 'full' )
					: (string) wp_get_attachment_url( (int) get_term_meta( $object_id, 'thumbnail_id', true ) );
			}

			if ( '' === $thumb ) {
				return; // thumbnailUrl برای ویدیو الزامی است
			}

			$video_url = esc_url_raw( (string) preg_replace( '/\s+/', '%20', trim( (string) $data['video_url'] ) ) );

			/*
			 * بدون mainEntityOfPage.
			 * نسخه قبلی آن را به صورت رشته آدرس صفحه می‌گذاشت، یعنی ادعا
			 * می‌کرد ویدیو *موضوع اصلی* صفحه است. روی صفحه دسته‌بندی موضوع
			 * اصلی کاتالوگ محصولات است و روی نوشته خود مقاله؛ ویدیو بخشی
			 * از صفحه است (isPartOf).
			 */
			$schema = [
				'@type'        => 'VideoObject',
				'@id'          => $base . '#video',
				'isPartOf'     => $webpage,
				'name'         => ! empty( $data['video_title'] ) ? sanitize_text_field( $data['video_title'] ) : 'ویدیوی معرفی: ' . $title,
				'description'  => 'بررسی و نمایش ویدیویی ' . $title . ' توسط ' . $brand,
				'thumbnailUrl' => [ esc_url_raw( $thumb ) ],
				'uploadDate'   => hook_media_stable_date( $data, 'video', $object_id, $context ),
			];

			$schema[ hook_is_direct_video_file( $video_url ) ? 'contentUrl' : 'embedUrl' ] = $video_url;

			$duration = hook_format_duration_iso( $data['video_duration'] ?? '' );
			if ( '' !== $duration ) {
				$schema['duration'] = $duration;
			}

			if ( ! empty( $data['video_keywords'] ) ) {
				$schema['keywords'] = sanitize_text_field( $data['video_keywords'] );
			}
			break;

		case 'audio':

			if ( empty( $data['voice_url'] ) ) {
				return;
			}

			$date = hook_media_stable_date( $data, 'voice', $object_id, $context );

			$schema = [
				'@type'         => 'AudioObject',
				'@id'           => $base . '#audio',
				'isPartOf'      => $webpage,
				'name'          => ! empty( $data['voice_title'] ) ? sanitize_text_field( $data['voice_title'] ) : 'پادکست اختصاصی: ' . $title,
				'description'   => 'توضیحات صوتی اختصاصی ' . $brand . ' برای ' . $title,
				'contentUrl'    => esc_url_raw( (string) preg_replace( '/\s+/', '%20', trim( (string) $data['voice_url'] ) ) ),
				'datePublished' => $date,
				'uploadDate'    => $date,
			];

			$duration = hook_format_duration_iso( $data['voice_duration'] ?? '' );
			if ( '' !== $duration ) {
				$schema['duration'] = $duration;
			}

			if ( ! empty( $data['voice_keywords'] ) ) {
				$schema['keywords'] = sanitize_text_field( $data['voice_keywords'] );
			}
			break;

		case 'faq':

			$questions = [];

			foreach ( (array) ( $data['faq'] ?? [] ) as $item ) {
				if ( empty( $item['q'] ) || empty( $item['a'] ) ) {
					continue;
				}
				$questions[] = [
					'@type'          => 'Question',
					'name'           => wp_strip_all_tags( (string) $item['q'] ),
					'acceptedAnswer' => [ '@type' => 'Answer', 'text' => wp_kses_post( (string) $item['a'] ) ],
				];
			}

			if ( empty( $questions ) ) {
				return;
			}

			$schema = [
				'@type'      => 'FAQPage',
				'@id'        => $base . '#faq',
				'isPartOf'   => $webpage,
				'mainEntity' => $questions,
			];
			break;
	}

	if ( empty( $schema ) ) {
		return;
	}

	echo "\n" . '<script type="application/ld+json">'
		. wp_json_encode( [ '@context' => 'https://schema.org' ] + $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP )
		. "</script>\n";
}

/* =====================================================================
 * تزریق خودکار در head
 * ===================================================================== */

add_action( 'wp_head', 'hook_auto_inject_head_schema' );

function hook_auto_inject_head_schema() {

	if ( is_admin() ) {
		return;
	}

	$queried = get_queried_object();

	if ( $queried instanceof WP_Post && in_array( $queried->post_type, hook_get_supported_post_types(), true ) ) {
		// FAQ و متن رسانه نوشته رمزدار نباید در اسکیما منتشر شود.
		if ( function_exists( 'hodima_post_content_is_visible' ) && ! hodima_post_content_is_visible( $queried ) ) {
			return;
		}
		$object_id = (int) $queried->ID;
		$context   = 'post';
	} elseif ( $queried instanceof WP_Term && in_array( $queried->taxonomy, hook_get_supported_taxonomies(), true ) ) {
		$object_id = (int) $queried->term_id;
		$context   = 'term';
	} else {
		return;
	}

	$data = hook_get_media_data( $object_id, $context );

	if ( 'yes' !== ( $data['enabled'] ?? '' ) ) {
		return;
	}

	$post_type  = ( 'post' === $context ) ? (string) get_post_type( $object_id ) : '';
	$is_product = ( 'product' === $post_type );

	/*
	 * نوشته‌ها: blog-schema.php خلاصه و موجودیت‌ها را به BlogPosting اضافه
	 * می‌کند. محصولات: product-schema-pro.php ویدیو را به عنوان نود #video
	 * می‌سازد. چاپ دوباره اینجا موجودیت تکراری می‌ساخت.
	 */
	// فقط جایی که «سئو مدرن» فعال است (برگه). روی دسته‌بندی یک WebPage دوم
	// برای همان آدرس می‌ساخت؛ نوشته را blog-schema.php پوشش می‌دهد.
	if ( ! $is_product && 'post' !== $post_type && hook_modern_seo_enabled( $context, $object_id ) ) {
		hook_print_schema( 'seo_discover', $data, $object_id, $context );
	}

	if ( ! $is_product ) {
		hook_print_schema( 'video', $data, $object_id, $context );
	}

	hook_print_schema( 'audio', $data, $object_id, $context );
	hook_print_schema( 'faq', $data, $object_id, $context );
}
