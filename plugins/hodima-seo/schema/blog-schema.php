<?php
/**
 * اسکیمای مقاله (BlogPosting) و نویسنده آن (Person)
 * Path: plugins/hodima-seo/schema/blog-schema.php
 *
 * همه ارجاع‌ها به نودهای گراف واحد: صفحه (#webpage)، سازمان (#organization)
 * و نویسنده (#person صفحه نویسنده). قبلا ناشر یک Organization کامل و بی‌شناسه
 * (نسخه دوم همان کسب‌وکار) و نویسنده یک Person بی‌شناسه در هر مقاله بود.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

add_action( 'wp_head', 'hodima_seo_render_blog_schema', 5 );

function hodima_seo_render_blog_schema(): void {

	if ( 'on' !== get_option( 'hodima_blog_schema_status', 'on' ) || ! is_single() || 'post' !== get_post_type() ) {
		return;
	}

	$post = get_post();
	if ( ! $post instanceof WP_Post ) {
		return;
	}

	$post_id  = (int) get_the_ID();
	$site_url = trailingslashit( home_url() );

	// پایه همه شناسه‌ها همان canonical نود «#webpage» (با canonical دستی، مقاله از صفحه‌اش جدا نیفتد)
	$canonical  = function_exists( 'hodima_get_canonical_url' ) ? hodima_get_canonical_url() : '';
	$post_url   = '' !== $canonical ? $canonical : trailingslashit( (string) get_permalink( $post_id ) );
	$post_title = get_the_title( $post_id );

	// بدون چکیده دستی: «متن معرفی» نوشته (سیستم رسانه؛ inc/page-intro.php)، بعد ابتدای متن
	$intro       = function_exists( 'hodima_seo_page_intro_text' ) ? hodima_seo_page_intro_text( $post_id, 'post', 40 ) : '';
	$raw_excerpt = has_excerpt( $post_id ) ? get_the_excerpt() : ( '' !== $intro ? $intro : ( get_the_excerpt() ?: wp_trim_words( strip_shortcodes( $post->post_content ), 30, '...' ) ) );

	// تصویر: تصویر اصلی صفحه (#primaryimage؛ تصویر دیسکاور یا شاخص)، وگرنه لوگوی سازمان
	$image_url  = hodima_seo_schema_logo_url();
	$image_size = null;
	$featured   = hodima_seo_schema_featured_image( $post_id );
	if ( null !== $featured ) {
		[ $image_url, $w, $h ] = $featured;
		$image_size            = [ $w, $h ];
	} elseif ( has_post_thumbnail() ) {
		$image_url = get_the_post_thumbnail_url( $post_id, 'full' );
	}

	$blog_posting = [
		'@type'            => 'BlogPosting',
		'@id'              => $post_url . '#article',
		// گوگل headline بیش از ۱۱۰ نویسه را هشدار می‌دهد؛ name کامل می‌ماند
		'headline'         => mb_strlen( $post_title ) > 110 ? mb_substr( $post_title, 0, 109 ) . '…' : $post_title,
		'name'             => $post_title,
		'description'      => wp_strip_all_tags( html_entity_decode( $raw_excerpt, ENT_QUOTES, 'UTF-8' ) ),
		// get_post_time بدون ترجمه: get_the_date() از wp_date رد می‌شود و روی این سایت تاریخ شمسی می‌داد
		'datePublished'    => get_post_time( 'c', false, $post_id, false ),
		'dateModified'     => get_post_modified_time( 'c', false, $post_id, false ),
		'author'           => [ '@id' => hodima_seo_schema_person_id( (int) $post->post_author ) ],
		'image'            => [
			'@type' => 'ImageObject',
			'@id'   => $post_url . '#primaryimage',
			'url'   => $image_url,
		],
		'thumbnailUrl'     => $image_url,
		'inLanguage'       => 'fa-IR',
		'isPartOf'         => [ '@id' => $post_url . '#webpage' ],
		'url'              => $post_url,
		'publisher'        => [ '@id' => $site_url . '#organization' ],
		'mainEntityOfPage' => [ '@id' => $post_url . '#webpage' ],
	];

	// عنوان دیسکاور → alternativeHeadline و موضوعات اصلی → about (ماژول «Google Discover»)
	if ( function_exists( 'hodima_seo_discover_enrich' ) && hodima_seo_discover_for_post( $post_id ) ) {
		$blog_posting = hodima_seo_discover_enrich( $blog_posting, $post_id );
	}

	if ( null !== $image_size ) {
		$blog_posting['image']['width']  = $image_size[0];
		$blog_posting['image']['height'] = $image_size[1];
	}

	/*
	 * برش‌های ۱۶:۹، ۴:۳ و ۱:۱ تصویر (توصیه گوگل برای Discover و نتایج مقاله)؛
	 * ماژول «Google Discover» هنگام ذخیره می‌سازد. #primaryimage اول فهرست می‌ماند.
	 */
	if ( function_exists( 'hodima_seo_discover_images' ) ) {
		$discover_images = array_values( array_filter(
			hodima_seo_discover_images( $post_id ),
			static fn( array $img ): bool => $img['url'] !== $image_url
		) );
		if ( [] !== $discover_images ) {
			$blog_posting['image'] = array_merge( [ $blog_posting['image'] ], array_map(
				static fn( array $img ): array => [ '@type' => 'ImageObject', 'url' => $img['url'], 'width' => $img['width'], 'height' => $img['height'] ],
				$discover_images
			) );
		}
	}

	// تعداد کلمات، بخش (دسته‌ها) و کلیدواژه‌ها (برچسب‌ها) — ویژگی‌های پیشنهادی Article
	$plain = trim( wp_strip_all_tags( strip_shortcodes( (string) $post->post_content ) ) );
	if ( '' !== $plain ) {
		$blog_posting['wordCount'] = count( (array) preg_split( '/\s+/u', $plain, -1, PREG_SPLIT_NO_EMPTY ) );
	}

	$sections = array_values( wp_list_pluck( (array) get_the_category( $post_id ), 'name' ) );
	if ( [] !== $sections ) {
		$blog_posting['articleSection'] = count( $sections ) > 1 ? $sections : (string) $sections[0];
	}

	$tags = get_the_tags( $post_id );
	if ( is_array( $tags ) && [] !== $tags ) {
		$blog_posting['keywords'] = implode( '، ', wp_list_pluck( $tags, 'name' ) );
	}

	hodima_schema_add( [ '@graph' => [ $blog_posting, hodima_seo_schema_person_node( (int) $post->post_author ) ] ], 'hodima-seo: blog-schema' );
}
