<?php
/**
 * [hook_video id="" context="" heading="h2|h3|…|none" facade="yes|no" priority="high"]
 *
 *   priority="high": ویدیو بالای صفحه است (مثلا بخش معرفی صفحه اصلی)؛ کاور
 *   «نما» بدون lazy و با fetchpriority بالا (بزرگ‌ترین تصویر دید اول = LCP).
 *
 * نسخه ۴:
 *   - آپارات/یوتیوب/ویمئو: پخش‌کننده رسمی از روی آدرس (نه oEmbed وردپرس
 *     که آپارات را نمی‌شناخت)، با «نما» (facade): تا کلیک نشده فقط کاور و
 *     دکمه پخش؛ اسکریپت چندصدکیلوبایتی پخش‌کننده فقط بعد از کلیک.
 *   - ویدیوی عمودی (ریلز/شورتز) و مربع با نسبت درست، نه نوار سیاه بزرگ.
 *   - کاور سبک: اندازه مناسب صفحه با srcset، نه تصویر اصلی چندمگابایتی.
 *   - فصل‌های ویدیو (کلیک = پرش به همان زمان) و متن کامل زیر پلیر.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

add_shortcode( 'hook_video', 'hodima_media_shortcode_video' );

function hodima_media_shortcode_video( mixed $atts ): string {

	if ( is_admin() && ! wp_doing_ajax() ) {
		return '';
	}

	[ $object_id, $context ] = hodima_media_shortcode_context( $atts );
	$atts                    = is_array( $atts ) ? $atts : [];

	$video = $object_id ? hodima_media_video( $object_id, $context ) : null;

	if ( null === $video || ! $video['enabled'] ) {
		return '';
	}

	hodima_media_enqueue_assets();

	$url   = ( is_ssl() && str_starts_with( $video['url'], 'http://' ) ) ? set_url_scheme( $video['url'], 'https' ) : $video['url'];
	$title = $video['title'];
	$label = '' !== $title ? $title : 'ویدیو';

	[ $rw, $rh ] = array_map( 'intval', explode( ':', $video['ratio'] ) );
	$portrait    = $rh > $rw;

	// کاور در اندازه صفحه (نه full): پوستر MP4 و تصویر «نما»
	$cover = hodima_media_video_cover( $object_id, $context, 'large' );

	if ( $video['is_file'] ) {

		/*
		 * فایل مستقیم: خود <video> در HTML می‌ماند (گوگل ویدیو را از همین تگ
		 * پیدا می‌کند). preload="none" با کاور: هیچ بایتی از ویدیو تا کلیک
		 * دانلود نمی‌شود؛ بدون کاور metadata تا فریم اول نمایش داده شود.
		 */
		$media = sprintf(
			'<video class="hook-video-el" controls playsinline preload="%1$s"%2$s width="%3$d" height="%4$d" aria-label="%5$s"><source src="%6$s" type="%7$s">%8$s<p>مرورگر شما از پخش ویدیو پشتیبانی نمی‌کند. <a href="%6$s">دانلود ویدیو</a></p></video>',
			null !== $cover ? 'none' : 'metadata',
			null !== $cover ? ' poster="' . esc_url( set_url_scheme( $cover['url'] ) ) . '"' : '',
			$rw * 80,
			$rh * 80,
			esc_attr( $label ),
			esc_url( $url ),
			esc_attr( $video['mime'] ),
			// زیرنویس (WebVTT)؛ پیش‌فرض خاموش، کاربر از منوی پلیر روشن می‌کند
			'' !== $video['captions']
				? sprintf( '<track kind="captions" src="%s" srclang="%s" label="%s">', esc_url( set_url_scheme( $video['captions'] ) ), esc_attr( substr( (string) get_bloginfo( 'language' ), 0, 2 ) ?: 'fa' ), esc_attr( 'زیرنویس' ) )
				: ''
		);

	} elseif ( in_array( $video['provider'], [ 'aparat', 'youtube', 'vimeo' ], true ) ) {

		$media = 'no' === strtolower( (string) ( $atts['facade'] ?? '' ) )
			? hodima_media_iframe( hodima_media_embed_src( $video, false ), $label )
			: hodima_media_facade( $video, $cover, $label, $url, 'high' === strtolower( (string) ( $atts['priority'] ?? '' ) ) );

	} else {

		// سرویس ناشناخته: oEmbed وردپرس (با کش) یا دکمه رفتن به صفحه ویدیو
		$embed = hodima_media_cached_oembed( $url, [ 'width' => 800 ] );
		$media = '' !== $embed
			? '<div class="hook-oembed-container">' . hodima_media_prepare_iframe( $embed, $label ) . '</div>'
			: sprintf( '<a href="%s" target="_blank" rel="noopener noreferrer" class="button">مشاهده ویدیو</a>', esc_url( $url ) );
	}

	return sprintf(
		'<div class="hook-video-wrapper%1$s" style="--hook-video-ratio: %2$d / %3$d">%4$s<div class="hook-video-container"><div class="hook-video-inner">%5$s</div></div>%6$s%7$s</div>',
		$portrait ? ' hook-video-wrapper--portrait' : '',
		$rw,
		$rh,
		hodima_media_heading( $title, $atts['heading'] ?? 'h2' ),
		$media,
		hodima_media_chapters_html( $video['chapters'] ),
		hodima_media_transcript_html( $video['transcript'], 'متن کامل ویدیو' )
	);
}

/**
 * آدرس پخش‌کننده برای iframe.
 *   autoplay: بعد از کلیک روی «نما» تا کاربر دو بار کلیک نکند.
 *   enablejsapi=1 (یوتیوب): توقف خودکار وقتی ویدیو از دید خارج می‌شود.
 * (پارامتر autoplay آپارات مستند رسمی ندارد؛ اگر نادیده گرفته شود کاربر
 * یک بار دیگر روی پخش‌کننده کلیک می‌کند.)
 */
function hodima_media_embed_src( array $video, bool $autoplay ): string {

	/*
	 * یوتیوب در «حالت حریم خصوصی» (youtube-nocookie.com): تا پخش، کوکی
	 * ردیابی نمی‌گذارد. فقط پخش‌کننده صفحه؛ embedUrl اسکیما همان youtube.com.
	 */
	$player = 'youtube' === $video['provider']
		? str_replace( 'https://www.youtube.com/embed/', 'https://www.youtube-nocookie.com/embed/', $video['player'] )
		: $video['player'];

	$args = match ( $video['provider'] ) {
		'youtube' => [ 'enablejsapi' => 1, 'rel' => 0, 'playsinline' => 1 ] + ( $autoplay ? [ 'autoplay' => 1 ] : [] ),
		'vimeo'   => $autoplay ? [ 'autoplay' => 1 ] : [],
		'aparat'  => $autoplay ? [ 'autoplay' => 'true' ] : [],
		default   => [],
	};

	return add_query_arg( $args, $player );
}

/** iframe پخش‌کننده (حالت بدون «نما» و داخل noscript). */
function hodima_media_iframe( string $src, string $label ): string {
	return sprintf(
		'<div class="hook-oembed-container"><iframe src="%1$s" title="%2$s" loading="lazy" allow="autoplay; encrypted-media; fullscreen; picture-in-picture" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe></div>',
		esc_url( $src ),
		esc_attr( $label )
	);
}

/**
 * «نما»ی ویدیو: کاور + دکمه پخش. media-style.js با کلیک، iframe را جای
 * آن می‌گذارد. بدون جاوااسکریپت، لینک به صفحه ویدیو باز می‌شود.
 */
function hodima_media_facade( array $video, ?array $cover, string $label, string $watch_url, bool $priority = false ): string {

	$image = '';

	if ( null !== $cover ) {
		// بالای صفحه: بدون lazy و با اولویت بالا (LCP)؛ وگرنه lazy
		$loading = $priority ? [ 'loading' => 'eager', 'fetchpriority' => 'high' ] : [ 'loading' => 'lazy' ];
		$image   = $cover['id']
			? (string) wp_get_attachment_image( $cover['id'], 'large', false, [
				'class'    => 'hook-video-facade__img',
				'alt'      => '',
				'decoding' => 'async',
				'sizes'    => '(max-width: 700px) 100vw, 650px',
			] + $loading )
			: sprintf(
				'<img class="hook-video-facade__img" src="%s" alt=""%s decoding="async">',
				esc_url( $cover['url'] ),
				$priority ? ' loading="eager" fetchpriority="high"' : ' loading="lazy"'
			);
	}

	$service = [ 'aparat' => 'آپارات', 'youtube' => 'یوتیوب', 'vimeo' => 'ویمئو' ][ $video['provider'] ] ?? '';

	return sprintf(
		'<div class="hook-video-facade" data-hook-embed="%1$s" data-hook-provider="%2$s" data-hook-title="%3$s">%4$s<a class="hook-video-play" href="%5$s" target="_blank" rel="noopener" aria-label="%6$s"><svg viewBox="0 0 68 48" width="68" height="48" aria-hidden="true" focusable="false"><path class="hook-video-play__bg" d="M66.5 7.7a8.5 8.5 0 0 0-6-6C55.3.3 34 .3 34 .3s-21.3 0-26.5 1.4a8.5 8.5 0 0 0-6 6C.1 12.9.1 24 .1 24s0 11.1 1.4 16.3a8.5 8.5 0 0 0 6 6C12.7 47.7 34 47.7 34 47.7s21.3 0 26.5-1.4a8.5 8.5 0 0 0 6-6C67.9 35.1 67.9 24 67.9 24s0-11.1-1.4-16.3z"/><path class="hook-video-play__icon" d="M27 34V14l18 10z"/></svg></a>%7$s<noscript>%8$s</noscript></div>',
		esc_url( hodima_media_embed_src( $video, true ) ),
		esc_attr( $video['provider'] ),
		esc_attr( $label ),
		$image,
		esc_url( $watch_url ),
		esc_attr( 'پخش ویدیو: ' . $label ),
		'' !== $service ? '<span class="hook-video-facade__service">' . esc_html( $service ) . '</span>' : '',
		hodima_media_iframe( hodima_media_embed_src( $video, false ), $label )
	);
}

/**
 * فهرست فصل‌ها: هر دکمه ویدیو را از همان زمان پخش می‌کند (media-style.js).
 *
 * @param list<array{start: int, title: string}> $chapters
 */
function hodima_media_chapters_html( array $chapters ): string {

	if ( ! $chapters ) {
		return '';
	}

	$items = array_map(
		static fn( array $c ): string => sprintf(
			'<li><button type="button" class="hook-video-chapter" data-hook-seek="%1$d"><span class="hook-video-chapter__time" dir="ltr">%2$s</span> %3$s</button></li>',
			$c['start'],
			esc_html( hodima_media_clock( $c['start'] ) ),
			esc_html( $c['title'] )
		),
		$chapters
	);

	return '<nav class="hook-video-chapters" aria-label="فصل‌های ویدیو"><ol>' . implode( '', $items ) . '</ol></nav>';
}

/**
 * آماده‌سازی iframe کد oEmbed (سرویس‌های ناشناخته): loading="lazy" و
 * title (صفحه‌خوان). فقط ویژگی اضافه می‌شود؛ چیزی حذف نمی‌شود.
 */
function hodima_media_prepare_iframe( string $embed, string $title ): string {
	$attrs = ' loading="lazy" title="' . esc_attr( '' !== $title ? $title : 'ویدیو' ) . '"';
	return (string) preg_replace( '/<iframe\b(?![^>]*\bloading=)/i', '<iframe' . $attrs, $embed );
}
