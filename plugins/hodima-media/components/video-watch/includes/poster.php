<?php
/**
 * کاور پلیر صفحه ویدیو در اندازه مناسب (بارگذار: ../db-video-watch.php)
 *
 * تا Media 2.0.9 کاور همیشه در اندازه اصلی آپلود (full) هم در پلیر و هم در
 * پیش‌بارگذاری (preload، بزرگ‌ترین عنصر بالای صفحه = LCP) بود؛ عکس ۴۰۰۰px
 * چند مگابایتی برای ستونی که حداکثر حدود ۷۰۰px است، روی موبایل هم. ویژگی
 * poster ویدیو srcset ندارد، پس یک اندازه: کوچک‌ترین اندازه وردپرس که دست‌کم
 * ۱۲۸۰×۷۲۰ است (صفحه ۲x دسکتاپ و ۳x موبایل)، وگرنه اصلی. اسکیما همان
 * اندازه اصلی را می‌گیرد (vid-w-schema.php؛ گوگل تصویر بزرگ می‌خواهد).
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * شناسه پیوست کاور ویدیو، یا ۰ (آدرس بیرونی یا بدون کاور).
 *
 * کاور ویدیو آدرس است (متای _hod_video_thumbnail)، نه شناسه. پیدا کردن
 * پیوست از روی آدرس (attachment_url_to_postid) جست‌وجوی سنگینی در متاهاست،
 * پس نتیجه یک بار با امضای همان آدرس در _hod_video_thumbnail_id نگه داشته
 * می‌شود («md5:شناسه»)؛ با عوض شدن آدرس، خودکار دوباره پیدا می‌شود.
 */
function hodima_video_watch_poster_id( int $post_id ): int {
	$url = trim( (string) get_post_meta( $post_id, '_hod_video_thumbnail', true ) );
	if ( '' === $url ) {
		return (int) get_post_thumbnail_id( $post_id );
	}

	$sig    = md5( $url );
	$stored = (string) get_post_meta( $post_id, '_hod_video_thumbnail_id', true );
	if ( str_starts_with( $stored, $sig . ':' ) ) {
		return (int) substr( $stored, strlen( $sig ) + 1 );
	}

	$id = (int) attachment_url_to_postid( $url );
	update_post_meta( $post_id, '_hod_video_thumbnail_id', $sig . ':' . $id );
	return $id;
}

/** آدرس کاور پلیر (و پیش‌بارگذاری آن)، یا رشته خالی. */
function hodima_video_watch_poster_url( int $post_id ): string {
	$id = hodima_video_watch_poster_id( $post_id );

	if ( $id ) {
		$size = apply_filters( 'hodima_video_watch_poster_size', [ 1280, 720 ], $post_id );
		$src  = wp_get_attachment_image_src( $id, is_string( $size ) || is_array( $size ) ? $size : [ 1280, 720 ] );
		if ( $src ) {
			return (string) $src[0];
		}
	}

	return trim( (string) get_post_meta( $post_id, '_hod_video_thumbnail', true ) );
}
