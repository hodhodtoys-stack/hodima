<?php
/**
 * [hook_video id="" context="" heading="h2|h3|…|none"]
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_shortcode( 'hook_video', 'hook_render_shortcode_video' );

function hook_render_shortcode_video( $atts ) {

	if ( is_admin() && ! wp_doing_ajax() ) {
		return '';
	}

	[ $object_id, $context ] = hook_get_shortcode_context( $atts );

	if ( ! $object_id ) {
		return '';
	}

	$data = hook_get_media_data( $object_id, $context );

	if ( 'yes' !== ( $data['enabled'] ?? '' ) || empty( $data['video_url'] ) ) {
		return '';
	}

	hook_enqueue_media_assets();

	$url   = esc_url_raw( (string) $data['video_url'] );
	$url   = ( is_ssl() && str_starts_with( $url, 'http://' ) ) ? set_url_scheme( $url, 'https' ) : $url;
	$title = (string) ( $data['video_title'] ?? '' );
	$atts  = (array) $atts;

	if ( hook_is_direct_video_file( $url ) ) {

		// video_thumb در hook_get_media_data به کاور برمی‌گردد
		$poster = (string) ( $data['video_thumb'] ?? '' );
		$mime   = wp_check_filetype( (string) wp_parse_url( $url, PHP_URL_PATH ), wp_get_mime_types() )['type'] ?: 'video/mp4';

		/*
		 * preload="none" وقتی کاور هست.
		 * preload="metadata" برای MP4 که اطلاعاتش (moov) انتهای فایل است،
		 * مرورگر را وادار می‌کند بخش بزرگی از فایل را قبل از هر کلیک
		 * دانلود کند — روی هر بازدید صفحه محصول. با کاور، چیزی برای نمایش
		 * پیش از پخش لازم نیست.
		 */
		$media = sprintf(
			'<video class="hook-video-el" controls playsinline preload="%1$s"%2$s aria-label="%3$s"><source src="%4$s" type="%5$s"><p>مرورگر شما از پخش ویدیو پشتیبانی نمی‌کند. <a href="%4$s">دانلود ویدیو</a></p></video>',
			'' !== $poster ? 'none' : 'metadata',
			'' !== $poster ? ' poster="' . esc_url( $poster ) . '"' : '',
			esc_attr( '' !== $title ? $title : 'ویدیو' ),
			esc_url( $url ),
			esc_attr( $mime )
		);

	} else {

		$embed = hook_get_cached_oembed( $url, [ 'width' => 800 ] );

		if ( '' !== $embed ) {
			$media = '<div class="hook-oembed-container">' . hook_prepare_embed_iframe( $embed, $title ) . '</div>';
		} else {
			$media = sprintf( '<a href="%s" target="_blank" rel="noopener noreferrer" class="button">مشاهده ویدیو</a>', esc_url( $url ) );
		}
	}

	return sprintf(
		'<div class="hook-video-wrapper">%s<div class="hook-video-container"><div class="hook-video-inner">%s</div></div></div>',
		hook_media_heading( $title, $atts['heading'] ?? 'h2' ),
		$media
	);
}

/**
 * آماده‌سازی iframe آپارات / یوتیوب.
 *
 *   loading="lazy"  — نسخه قبلی iframe را فورا بارگذاری می‌کرد؛ یعنی اسکریپت
 *                     سنگین پلیر آپارات یا یوتیوب (صدها کیلوبایت) روی *هر*
 *                     بازدید صفحه محصول، حتی وقتی ویدیو داخل جعبه جمع‌شده
 *                     توضیحات بود و کاربر هرگز به آن نمی‌رسید.
 *   title           — لازم برای صفحه‌خوان (iframe بدون عنوان خطای دسترسی‌پذیری است)
 *   enablejsapi=1   — بدون آن، دستور توقف یوتیوب از media-style.js (وقتی
 *                     ویدیو از دید خارج می‌شود) بی‌اثر بود
 */
function hook_prepare_embed_iframe( string $embed, string $title ): string {

	/*
	 * فقط افزودن ویژگی به خود iframe؛ هیچ چیزی حذف نمی‌شود.
	 * کد جاسازی آپارات یک قاب دارد که ارتفاعش از padding درون‌خطی یک
	 * <span> می‌آید؛ حذف style از کل HTML آن قاب را صفر و پلیر را نامرئی
	 * می‌کرد. اندازه‌دهی با CSS کانتینر انجام می‌شود (مثل نسخه اصلی).
	 */
	$attrs = ' loading="lazy" title="' . esc_attr( '' !== $title ? $title : 'ویدیو' ) . '"';
	$embed = (string) preg_replace( '/<iframe\b(?![^>]*\bloading=)/i', '<iframe' . $attrs, $embed );

	return (string) preg_replace_callback(
		'/(<iframe[^>]+src=")([^"]*(?:youtube\.com|youtube-nocookie\.com)[^"]*)(")/i',
		static fn( array $m ): string => $m[1] . ( str_contains( $m[2], 'enablejsapi=' ) ? $m[2] : $m[2] . ( str_contains( $m[2], '?' ) ? '&' : '?' ) . 'enablejsapi=1' ) . $m[3],
		$embed
	);
}
