<?php
/**
 * Media System — اطلاعات ویدیو از خود سرویس (آپارات، یوتیوب، ویمئو)
 * Path: media-system/media-providers.php
 *
 * هنگام ذخیره کادر رسانه، اگر لینک ویدیو تازه است و عنوان، مدت یا کاور خالی
 * است، از خود سرویس پر می‌شود:
 *   آپارات: API عمومی etc/api/video/videohash (عنوان، پوستر بزرگ، مدت)
 *   یوتیوب: oEmbed (عنوان، تصویر؛ مدت ندارد)
 *   ویمئو: oEmbed (عنوان، تصویر، مدت)
 * کاور در کتابخانه رسانه خود سایت ذخیره می‌شود (thumbnailUrl روی دامنه سایت؛
 * تصویر واقعی ویدیو به‌جای تصویر شاخص صفحه).
 *
 * فقط در ذخیره پیشخوان؛ هیچ بازدیدی از سایت درخواست بیرونی نمی‌زند. پاسخ
 * یک روز کش می‌شود. شکست شبکه بی‌صداست (فیلدها دستی پر می‌شوند).
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * اطلاعات ویدیو از سرویس، یا null.
 *
 * @return array{title: string, thumbnail: string, seconds: int}|null
 */
function hodima_media_fetch_video_info( string $url ): ?array {

	$parsed = hodima_media_parse_video_url( $url );

	$endpoint = match ( $parsed['provider'] ) {
		'aparat'  => 'https://www.aparat.com/etc/api/video/videohash/' . rawurlencode( $parsed['id'] ),
		'youtube' => add_query_arg( [ 'url' => rawurlencode( 'https://www.youtube.com/watch?v=' . $parsed['id'] ), 'format' => 'json' ], 'https://www.youtube.com/oembed' ),
		'vimeo'   => add_query_arg( [ 'url' => rawurlencode( 'https://vimeo.com/' . $parsed['id'] ) ], 'https://vimeo.com/api/oembed.json' ),
		default   => '',
	};

	if ( '' === $endpoint ) {
		return null;
	}

	$cache_key = 'hodima_media_vinfo_' . md5( $endpoint );
	$cached    = get_transient( $cache_key );

	if ( is_array( $cached ) ) {
		return $cached ?: null;
	}

	$res  = wp_remote_get( $endpoint, [ 'timeout' => 8 ] );
	$body = ! is_wp_error( $res ) && 200 === (int) wp_remote_retrieve_response_code( $res )
		? json_decode( (string) wp_remote_retrieve_body( $res ), true )
		: null;

	$info = null;

	if ( is_array( $body ) ) {
		$info = 'aparat' === $parsed['provider']
			? [
				'title'     => (string) ( $body['video']['title'] ?? '' ),
				'thumbnail' => (string) ( $body['video']['big_poster'] ?? $body['video']['small_poster'] ?? '' ),
				'seconds'   => (int) ( $body['video']['duration'] ?? 0 ),
			]
			: [
				'title'     => (string) ( $body['title'] ?? '' ),
				// یوتیوب: hqdefault (۴۸۰×۳۶۰) همیشه هست؛ maxresdefault نه
				'thumbnail' => (string) ( $body['thumbnail_url'] ?? '' ),
				'seconds'   => (int) ( $body['duration'] ?? 0 ),
			];

		$info['title']     = sanitize_text_field( $info['title'] );
		$info['thumbnail'] = preg_match( '#^https://#i', $info['thumbnail'] ) ? esc_url_raw( $info['thumbnail'] ) : '';
		$info['seconds']   = max( 0, $info['seconds'] );

		if ( '' === $info['title'] && '' === $info['thumbnail'] && 0 === $info['seconds'] ) {
			$info = null;
		}
	}

	// شکست هم یک ساعت کش می‌شود تا هر ذخیره منتظر شبکه نماند
	set_transient( $cache_key, $info ?? [], null === $info ? HOUR_IN_SECONDS : DAY_IN_SECONDS );

	return $info;
}

/**
 * تصویر بیرونی را در کتابخانه رسانه ذخیره می‌کند؛ شناسه پیوست یا ۰.
 * (تصویری که قبلا از همین آدرس ذخیره شده دوباره دانلود نمی‌شود.)
 */
function hodima_media_sideload_cover( string $image_url, int $post_id, string $title ): int {

	if ( '' === $image_url ) {
		return 0;
	}

	$existing = get_posts( [
		'post_type'      => 'attachment',
		'post_status'    => 'inherit',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'meta_key'       => '_hodima_media_source_url', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- فقط هنگام ذخیره پیشخوان
		'meta_value'     => $image_url, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- همان
	] );

	if ( $existing ) {
		return (int) $existing[0];
	}

	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	// نام فایل: آدرس پوستر آپارات/یوتیوب پسوند ندارد یا یکسان است (hqdefault.jpg)
	$tmp = download_url( $image_url, 15 );

	if ( is_wp_error( $tmp ) ) {
		return 0;
	}

	$type = wp_get_image_mime( $tmp );
	$ext  = [ 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp' ][ $type ] ?? '';

	if ( '' === $ext ) {
		wp_delete_file( $tmp );
		return 0;
	}

	$id = media_handle_sideload(
		[ 'name' => sanitize_file_name( 'video-cover-' . substr( md5( $image_url ), 0, 10 ) . '.' . $ext ), 'tmp_name' => $tmp ],
		$post_id,
		'' !== $title ? $title : null
	);

	if ( is_wp_error( $id ) ) {
		wp_delete_file( $tmp );
		return 0;
	}

	update_post_meta( (int) $id, '_hodima_media_source_url', $image_url );

	return (int) $id;
}
