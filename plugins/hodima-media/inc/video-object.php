<?php
/**
 * سازنده واحد نود VideoObject
 * Path: inc/video-object.php
 *
 * تا Media 1.5.0 دو سازنده جدا بود: سیستم رسانه (hodima_media_video_node،
 * ویدیوی نوشته/محصول/دسته) و ماژول «ویدیوها» (صفحه تماشای هر ویدیو). هر
 * کدام چیزی داشت که دیگری نداشت: آمار بازدید و SeekToAction فقط در ماژول
 * ویدیوها، embedUrl آپارات/یوتیوب و inLanguage سایت فقط در سیستم رسانه.
 * حالا هر دو داده خودشان را یکسان می‌کنند و نود را همین تابع می‌سازد.
 *
 * بیرون از ماژول‌ها و همیشه لود می‌شود (hodima-media.php) تا هر دو ماژول،
 * هر کدام روشن باشد، از آن استفاده کنند.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

if ( function_exists( 'hodima_media_video_object' ) ) {
	return;
}

/**
 * نود VideoObject با شناسه «{base}#video».
 *
 * @param array{
 *   base: string,
 *   name: string,
 *   description: string,
 *   thumbnails: list<string>,
 *   upload_date: string,
 *   date_modified?: string,
 *   page_url?: string,
 *   content_url?: string,
 *   embed_url?: string,
 *   mime?: string,
 *   duration?: string,
 *   seconds?: int,
 *   transcript?: string,
 *   keywords?: string,
 *   chapters?: list<array{start: int, title: string}>,
 *   captions?: string,
 *   seekable?: bool,
 *   views?: int|null,
 *   publisher?: bool,
 *   language?: string
 * } $v
 *   base: آدرس صفحه (پایه @id و آدرس فصل‌ها).
 *   seekable: پلیر صفحه «?t=ثانیه» را اجرا می‌کند (فایل، یوتیوب، ویمئو؛ آپارات نه)؛
 *     بدون فصل، SeekToAction تا گوگل خودش «لحظه‌های کلیدی» را پیدا کند.
 * @return array<string, mixed>
 */
function hodima_media_video_object( array $v ): array {

	$base = esc_url_raw( $v['base'] );

	$node = [
		'@type'        => 'VideoObject',
		'@id'          => $base . '#video',
		'isPartOf'     => [ '@id' => $base . '#webpage' ],
		'name'         => $v['name'],
		'description'  => $v['description'],
		'thumbnailUrl' => array_values( array_map( 'esc_url_raw', $v['thumbnails'] ) ),
		'uploadDate'   => $v['upload_date'],
		'inLanguage'   => (string) ( $v['language'] ?? ( get_bloginfo( 'language' ) ?: 'fa-IR' ) ),
	];

	if ( ! empty( $v['page_url'] ) ) {
		$node['url'] = esc_url_raw( $v['page_url'] );
	}

	if ( ! empty( $v['date_modified'] ) ) {
		$node['dateModified'] = $v['date_modified'];
	}

	if ( ! empty( $v['content_url'] ) ) {
		$node['contentUrl'] = $v['content_url'];
		if ( ! empty( $v['mime'] ) ) {
			$node['encodingFormat'] = $v['mime'];
		}
	}

	if ( ! empty( $v['embed_url'] ) ) {
		$node['embedUrl'] = esc_url_raw( $v['embed_url'] );
	}

	if ( ! empty( $v['duration'] ) ) {
		$node['duration'] = $v['duration'];
	}

	// ناشر = همان سازمان یکتای سایت (homepage-schema.php)
	if ( ! empty( $v['publisher'] ) ) {
		$node['publisher'] = [ '@id' => trailingslashit( home_url() ) . '#organization' ];
	}

	if ( '' !== trim( (string) ( $v['transcript'] ?? '' ) ) ) {
		$node['transcript'] = wp_strip_all_tags( (string) $v['transcript'] );
	}

	if ( ! empty( $v['keywords'] ) ) {
		$node['keywords'] = $v['keywords'];
	}

	// زیرنویس WebVTT (دسترس‌پذیری؛ گوگل متن گفتار را هم می‌خواند)
	if ( ! empty( $v['captions'] ) ) {
		$node['caption'] = [
			'@type'          => 'MediaObject',
			'contentUrl'     => esc_url_raw( $v['captions'] ),
			'encodingFormat' => 'text/vtt',
			'inLanguage'     => (string) ( $v['language'] ?? ( get_bloginfo( 'language' ) ?: 'fa-IR' ) ),
		];
		$node['accessibilityFeature'] = [ 'captions' ];
	}

	if ( isset( $v['views'] ) ) {
		$node['interactionStatistic'] = [
			'@type'                => 'InteractionCounter',
			'interactionType'      => [ '@type' => 'WatchAction' ],
			'userInteractionCount' => (int) $v['views'],
		];
	}

	/*
	 * «لحظه‌های کلیدی» گوگل: هر فصل یک Clip با آدرسی که ویدیو را از همان
	 * زمان شروع می‌کند (?t=ثانیه؛ media-style.js و پلیر صفحه ویدیو اجرا
	 * می‌کنند). پایان هر فصل = شروع فصل بعد؛ آخرین = مدت ویدیو (اگر معلوم است).
	 */
	$chapters = $v['chapters'] ?? [];
	$seconds  = (int) ( $v['seconds'] ?? 0 );
	$clips    = [];

	foreach ( $chapters as $i => $chapter ) {
		$end = $chapters[ $i + 1 ]['start'] ?? $seconds;
		if ( $end > 0 && $end <= $chapter['start'] ) {
			continue;
		}
		$clip = [
			'@type'       => 'Clip',
			'name'        => $chapter['title'],
			'startOffset' => $chapter['start'],
			'url'         => add_query_arg( 't', $chapter['start'], $base ),
		];
		if ( $end > 0 ) {
			$clip['endOffset'] = $end;
		}
		$clips[] = $clip;
	}

	if ( $clips ) {
		$node['hasPart'] = $clips;
	} elseif ( ! empty( $v['seekable'] ) ) {
		$node['potentialAction'] = [
			'@type'             => 'SeekToAction',
			'target'            => $base . '?t={seek_to_second_number}',
			'startOffset-input' => 'required name=seek_to_second_number',
		];
	}

	return $node;
}
