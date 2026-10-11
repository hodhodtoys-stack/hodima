<?php
/**
 * اسکیمای تصویرهای صفحه (ImageObject برای Image Metadata گوگل: سازنده، مجوز، حق نشر)
 * Path: plugins/hodima-seo/schema/imageobject-schema.php
 *
 * منبع تصویرها: تصویر شاخص، تصویرهای چند محصول/نوشته دسته (کش ۱۲ ساعته)،
 * گالری محصول و تصویرهای داخل متن. تنظیمات: «اسکیما ← تصاویر».
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

add_action( 'wp_head', 'hodima_seo_render_imageobject_schema', 1 );

function hodima_seo_render_imageobject_schema(): void {

	if ( is_admin() || '1' !== get_option( 'hodima_schema_image_enable' ) ) {
		return;
	}

	$targets = (array) get_option( 'hodima_schema_image_targets', [ 'product', 'post', 'product_cat', 'category' ] );
	if ( empty( $targets ) ) {
		return;
	}

	$site_url  = trailingslashit( home_url() );
	$site_name = get_bloginfo( 'name' );

	$page_name                = '';
	$page_url                 = '';
	$post_id                  = null;
	$thumb_id                 = null;
	$content                  = null;
	$category_product_thumbs  = [];

	if ( is_singular() ) {
		if ( ! in_array( get_post_type(), $targets, true ) ) {
			return;
		}
		$post_id   = get_the_ID();
		$page_name = get_the_title( $post_id );
		$page_url  = get_permalink( $post_id );
		// تصاویر داخل متن نوشته رمزدار نباید در اسکیما فهرست شوند
		$content  = post_password_required( $post_id ) ? '' : get_post_field( 'post_content', $post_id );
		$thumb_id = get_post_thumbnail_id( $post_id );
	} elseif ( is_tax() || is_category() || is_tag() ) {
		$term = get_queried_object();
		if ( ! $term || ! in_array( $term->taxonomy, $targets, true ) ) {
			return;
		}
		$page_name = single_term_title( '', false );
		$page_url  = get_term_link( $term );
		$thumb_id  = get_term_meta( $term->term_id, 'thumbnail_id', true ) ?: get_term_meta( $term->term_id, 'product_cat_thumbnail_id', true );
		$content   = get_term_meta( $term->term_id, 'hook_content', true ) . ' ' . term_description( $term->term_id );

		$category_product_thumbs = hodima_seo_imageobject_term_thumbs( $term );
	} else {
		return;
	}

	$opt_credit     = sanitize_text_field( get_option( 'hodima_schema_image_credit', 'عکس متعلق به ' . $site_name . ' است.' ) );
	$opt_license    = esc_url( get_option( 'hodima_schema_image_license', $site_url . 'terms/' ) );
	$opt_max_imgs   = (int) get_option( 'hodima_schema_image_max_count', 4 );
	$opt_def_width  = get_option( 'hodima_schema_image_def_width', '500' );
	$opt_def_height = get_option( 'hodima_schema_image_def_height', '500' );

	// سال میلادی با افست ساعت سایت (wp_date('Y') روی این سایت سال شمسی با ارقام فارسی می‌داد)
	$current_year = gmdate( 'Y', time() + (int) ( (float) get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );

	/*
	 * نویسنده و سازنده تصویر: Organization درون‌خطی *بدون* @id. دو تلاش برای
	 * ارجاع به «#organization» خطای «Invalid object type for field creator»
	 * در Image Metadata داد: گوگل نودهای هم‌شناسه را ادغام می‌کند و نوع سازمان
	 * WholesaleStore است که اعتبارسنج برای creator نمی‌پذیرد. @id اضافه نکنید.
	 */
	$org_ref = [
		'@type' => 'Organization',
		'name'  => (string) ( get_option( 'hodima_schema_homepage_org_name' ) ?: $site_name ),
		'url'   => $site_url,
	];

	$image_objects = [];

	$add_schema = static function ( mixed $url, mixed $width, mixed $height, mixed $alt ) use ( &$image_objects, $page_name, $page_url, $current_year, $site_url, $site_name, $opt_credit, $opt_license, $org_ref ): void {
		$url = esc_url_raw( $url );
		if ( ! str_starts_with( $url, 'http' ) && ! str_starts_with( $url, '//' ) ) {
			$url = $site_url . ltrim( $url, '/' );
		}
		if ( empty( $url ) || isset( $image_objects[ $url ] ) ) {
			return;
		}

		$alt = sanitize_text_field( $alt ) ?: $page_name . ' - ' . $site_name;

		$image_objects[ $url ] = [
			'@context'           => 'https://schema.org',
			'@type'              => 'ImageObject',
			'url'                => $url,
			'contentUrl'         => $url,
			'width'              => (string) absint( $width ),
			'height'             => (string) absint( $height ),
			'caption'            => $alt,
			'name'               => $page_name,
			'author'             => $org_ref,
			'creator'            => $org_ref,
			'creditText'         => $opt_credit,
			'copyrightNotice'    => '© ' . $current_year . ' ' . $site_name . '. استفاده بدون کسب اجازه ممنوع است.',
			'license'            => $opt_license,
			'acquireLicensePage' => $page_url,
		];
	};

	$process_id = static function ( mixed $img_id ) use ( $add_schema ): void {
		if ( ! $img_id ) {
			return;
		}
		$data = wp_get_attachment_image_src( $img_id, 'full' );
		if ( $data ) {
			$add_schema( $data[0], $data[1], $data[2], get_post_meta( $img_id, '_wp_attachment_image_alt', true ) );
		}
	};

	if ( isset( $thumb_id ) ) {
		$process_id( $thumb_id );
	}

	foreach ( $category_product_thumbs as $c_thumb ) {
		$process_id( $c_thumb );
	}

	if ( isset( $post_id ) && is_singular( 'product' ) ) {
		$gallery = get_post_meta( $post_id, '_product_image_gallery', true );
		if ( $gallery ) {
			foreach ( explode( ',', $gallery ) as $gid ) {
				$process_id( $gid );
			}
		}
	}

	// سقف تنظیمات فقط روی تصویرهای داخل متن است، نه تصویر شاخص/گالری/دسته
	// (سقف کلی یک بار ImageObjectهای دسته را از ۹ به ۴ رساند).
	if ( ! empty( $content ) ) {
		foreach ( hodima_seo_imageobject_html_images( (string) $content, $opt_max_imgs ) as $img ) {
			$width  = $img['width'];
			$height = $img['height'];

			if ( empty( $width ) || empty( $height ) ) {
				$attachment_id = attachment_url_to_postid( $img['src'] );
				if ( $attachment_id ) {
					$img_data = wp_get_attachment_image_src( $attachment_id, 'full' );
					if ( $img_data ) {
						$width  = $img_data[1];
						$height = $img_data[2];
					}
				}
			}

			$add_schema( $img['src'], ! empty( $width ) ? $width : $opt_def_width, ! empty( $height ) ? $height : $opt_def_height, $img['alt'] );
		}
	}

	if ( ! empty( $image_objects ) ) {
		// عمدا بدون @id (توضیح creator بالا)؛ گراف واحد فقط نسخه *دقیقا* تکراری را حذف می‌کند
		hodima_schema_add( array_values( $image_objects ), 'hodima-seo: imageobject-schema' );
	}
}

/**
 * تصویر شاخص چند نوشته/محصول یک دسته (کش ۱۲ ساعته).
 *
 * @return list<int>
 */
function hodima_seo_imageobject_term_thumbs( WP_Term $term ): array {

	$transient_key = 'hook_cat_imgs_' . $term->term_id;
	$cached_thumbs = get_transient( $transient_key );

	if ( false !== $cached_thumbs ) {
		return (array) $cached_thumbs;
	}

	$cat_posts = get_posts( [
		'post_type'              => 'product_cat' === $term->taxonomy ? 'product' : 'post',
		'posts_per_page'         => 4,
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- فقط ۴ شناسه، با کش ۱۲ ساعته
		'tax_query'              => [
			[
				'taxonomy' => $term->taxonomy,
				'field'    => 'term_id',
				'terms'    => $term->term_id,
			],
		],
		'fields'                 => 'ids',
		'no_found_rows'          => true,
		'update_post_meta_cache' => false,
		'update_post_term_cache' => false,
	] );

	$cached_thumbs = [];
	foreach ( $cat_posts as $pid ) {
		$pid_thumb = get_post_thumbnail_id( $pid );
		if ( $pid_thumb ) {
			$cached_thumbs[] = $pid_thumb;
		}
	}
	set_transient( $transient_key, $cached_thumbs, 12 * HOUR_IN_SECONDS );

	return $cached_thumbs;
}
/**
 * تصویرهای داخل HTML (حداکثر $max تصویر با src واقعی).
 * src از ویژگی‌های رایج lazy-load هم خوانده می‌شود (data-src، data-lazy-src، data-original، data-echo).
 *
 * @return list<array{src: string, alt: string, width: string, height: string}>
 */
function hodima_seo_imageobject_html_images( string $html, int $max ): array {

	$html = wp_specialchars_decode( $html );

	// DOMDocument ورودی را Latin-1 فرض می‌کند؛ نویسه‌های غیر ASCII → موجودیت عددی
	// (mb_convert_encoding با «HTML-ENTITIES» در PHP 8.4 هشدار Deprecated می‌دهد)
	if ( function_exists( 'mb_encode_numericentity' ) ) {
		$html = mb_encode_numericentity( $html, [ 0x80, 0x10FFFF, 0, 0x1FFFFF ], 'UTF-8' );
	}

	$dom      = new DOMDocument();
	$previous = libxml_use_internal_errors( true );
	$dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html, LIBXML_NOERROR | LIBXML_NOWARNING );
	libxml_clear_errors();
	libxml_use_internal_errors( $previous );

	$images = [];

	foreach ( $dom->getElementsByTagName( 'img' ) as $img ) {
		if ( count( $images ) >= $max ) {
			break;
		}

		$src = $img->getAttribute( 'data-src' )
			?: $img->getAttribute( 'data-lazy-src' )
			?: $img->getAttribute( 'data-original' )
			?: $img->getAttribute( 'data-echo' )
			?: $img->getAttribute( 'src' );

		if ( empty( $src ) || str_starts_with( $src, 'data:image' ) ) {
			continue;
		}

		$images[] = [
			'src'    => $src,
			'alt'    => $img->getAttribute( 'alt' ),
			'width'  => $img->getAttribute( 'width' ),
			'height' => $img->getAttribute( 'height' ),
		];
	}

	return $images;
}
