<?php
/**
 * آزمون خودکار سیستم رسانه و ماژول Google Discover روی وردپرس ابزار تست.
 *   HARNESS=1 php tools/wp-harness/media-tests.php      (یا media-tests.sh)
 * خروجی: هر آزمون ✔/✘؛ کد خروج ۱ اگر یکی رد شود. ci-check.sh هم اجرا می‌کند.
 *
 * تجزیه‌گرها (مدت، فصل‌ها، لینک ویدیو، زیرنویس، موضوعات، طعمه کلیک) قانون‌هایی
 * دارند که با هر تغییر کوچک ممکن است بی‌صدا عوض شوند؛ این‌ها همان رفتارهای
 * گزارش‌شده در HODIMA-AUDIT.md (بخش‌های ۳۵ و ۷۶ تا ۸۱) را قفل می‌کنند.
 */

declare(strict_types=1);

$_SERVER['HTTP_HOST'] = $_SERVER['SERVER_NAME'] = 'hodima.test';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['HTTPS']       = 'on';
require rtrim( (string) ( getenv( 'HODIMA_WP' ) ?: '/tmp/hodima-harness/wp' ), '/' ) . '/wp-load.php';

$failed = 0;
$passed = 0;

/** یک آزمون: مقدار واقعی === مقدار مورد انتظار. */
function hodima_t( string $name, mixed $actual, mixed $expected ): void {
	global $failed, $passed;
	if ( $actual === $expected ) {
		++$passed;
		echo "  ✔ {$name}\n";
		return;
	}
	++$failed;
	echo "  ✘ {$name}\n      انتظار: " . var_export( $expected, true ) . "\n      واقعی:  " . var_export( $actual, true ) . "\n";
}

if ( ! function_exists( 'hodima_media_get_data' ) ) {
	echo "✘ سیستم رسانه لود نشده است\n";
	exit( 1 );
}

echo "=== مدت و زمان\n";
hodima_t( 'مدت 2:35', hodima_media_duration_seconds( '2:35' ), 155 );
hodima_t( 'مدت با ارقام فارسی ۱:۰۵:۲۰', hodima_media_duration_seconds( '۱:۰۵:۲۰' ), 3920 );
hodima_t( 'مدت ISO PT2M35S', hodima_media_duration_seconds( 'PT2M35S' ), 155 );
hodima_t( 'مدت نامفهوم «۵ دقیقه» = ۰', hodima_media_duration_seconds( '۵ دقیقه' ), 0 );
hodima_t( 'ISO از 2:35', hodima_media_duration_iso( '2:35' ), 'PT2M35S' );
hodima_t( 'ISO درست دست نمی‌خورد', hodima_media_duration_iso( 'PT1M30.5S' ), 'PT1M30.5S' );
hodima_t( 'ISO از ساعت', hodima_media_duration_iso( '1:05:20' ), 'PT1H5M20S' );
hodima_t( 'نمایش ساعت', hodima_media_clock( 3920 ), '1:05:20' );
hodima_t( 'مدت نامعتبر پاک‌سازی = خالی', hodima_media_sanitize_duration( 'abc' ), '' );

echo "=== فصل‌ها\n";
hodima_t(
	'مرتب، بدون تکرار، خط بی‌زمان رد',
	hodima_media_parse_chapters( "1:20 - رنگ‌بندی\nبدون زمان\n۰:۰۰ معرفی\n1:20 تکراری" ),
	[ [ 'start' => 0, 'title' => 'معرفی' ], [ 'start' => 80, 'title' => 'رنگ‌بندی' ] ]
);

echo "=== لینک ویدیو\n";
$p = static fn( string $u ): string => hodima_media_parse_video_url( $u )['provider'];
hodima_t( 'آپارات', $p( 'https://www.aparat.com/v/abc123' ), 'aparat' );
hodima_t( 'دامنه جعلی fakeaparat.com', $p( 'https://fakeaparat.com/v/abc123' ), 'other' );
hodima_t( 'youtu.be', $p( 'https://youtu.be/dQw4w9WgXcQ' ), 'youtube' );
hodima_t( 'youtube-nocookie', $p( 'https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ' ), 'youtube' );
hodima_t( 'دامنه جعلی evilyoutube.com', $p( 'https://evilyoutube.com/watch?v=dQw4w9WgXcQ' ), 'other' );
hodima_t( 'شورتز عمودی', hodima_media_parse_video_url( 'https://www.youtube.com/shorts/dQw4w9WgXcQ' )['vertical'], true );
hodima_t( 'ویمئو', $p( 'https://player.vimeo.com/video/123' ), 'vimeo' );
hodima_t( 'فایل MP4', $p( 'https://hodima.test/v/a.mp4' ), 'file' );
hodima_t( 'پخش‌کننده آپارات', hodima_media_parse_video_url( 'https://www.aparat.com/v/abc123' )['player'], 'https://www.aparat.com/video/video/embed/videohash/abc123/vt/frame' );
hodima_t( 'فایل صوتی MP3', hodima_media_is_direct_audio( 'https://hodima.test/p.mp3' ), true );
hodima_t( 'صفحه SoundCloud فایل صوتی نیست', hodima_media_is_direct_audio( 'https://soundcloud.com/a/b' ), false );
hodima_t( 'نسبت ۱۰۸۰×۱۹۲۰', hodima_media_nearest_ratio( 1080, 1920 ), '9:16' );

echo "=== زیرنویس\n";
hodima_t(
	'VTT ← متن (بدون زمان، شماره، NOTE، برچسب و تکرار)',
	hodima_media_vtt_to_text( "WEBVTT\n\nNOTE یادداشت\nادامه\n\n1\n00:00:00.000 --> 00:00:02.000\n<v راوی>سلام</v>\n\n2\n00:00:02.000 --> 00:00:03.000\nسلام\n\n3\n00:00:03.000 --> 00:00:04.000\nکلیپس &amp; گلسر\n" ),
	"سلام\nکلیپس & گلسر"
);

echo "=== نمایش بخش‌ها\n";
hodima_t( 'همه بخش‌ها (داده قبلی)', hodima_media_part_shown( [ 'enabled' => 'yes', 'hidden_parts' => [] ], 'faq' ), true );
hodima_t( 'بخش پنهان', hodima_media_part_shown( [ 'enabled' => 'yes', 'hidden_parts' => [ 'faq' ] ], 'faq' ), false );
hodima_t( 'کلید اصلی خاموش', hodima_media_part_shown( [ 'enabled' => 'no', 'hidden_parts' => [] ], 'video' ), false );

echo "=== سازنده واحد VideoObject\n";
$base = [ 'base' => 'https://hodima.test/x/', 'name' => 'n', 'description' => 'd', 'thumbnails' => [ 'https://hodima.test/c.jpg' ], 'upload_date' => '2026-01-01T00:00:00+00:00' ];
$node = hodima_media_video_object( $base + [ 'chapters' => [ [ 'start' => 0, 'title' => 'a' ], [ 'start' => 30, 'title' => 'b' ] ], 'seconds' => 60 ] );
hodima_t( 'فصل‌ها ← Clip با پایان', array_column( $node['hasPart'], 'endOffset' ), [ 30, 60 ] );
hodima_t( 'با فصل SeekToAction ندارد', isset( $node['potentialAction'] ), false );
hodima_t( 'بی‌فصل + پلیر ?t= ← SeekToAction', hodima_media_video_object( $base + [ 'seekable' => true ] )['potentialAction']['@type'] ?? '', 'SeekToAction' );
hodima_t( 'آپارات (بی‌پرش) SeekToAction ندارد', isset( hodima_media_video_object( $base + [ 'seekable' => false ] )['potentialAction'] ), false );
hodima_t( 'زیرنویس ← caption', hodima_media_video_object( $base + [ 'captions' => 'https://hodima.test/a.vtt' ] )['caption']['encodingFormat'] ?? '', 'text/vtt' );
hodima_t( 'شناسه', $node['@id'], 'https://hodima.test/x/#video' );

echo "=== داده واقعی (نوشته آزمایشی)\n";
$post_id = wp_insert_post( [ 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'آزمون رسانه' ] );
foreach ( [
	'_hook_enabled'      => 'yes',
	'_hook_video_url'    => 'https://hodima.test/v/main.mp4',
	'_hook_video_cover'  => 'https://hodima.test/v/c.jpg',
	'_hook_voice_url'    => 'https://hodima.test/p.mp3',
	'_hook_faq'          => [ [ 'q' => 'سوال؟', 'a' => 'پاسخ' ] ],
	'_hook_hidden_parts' => [ 'voice' ],
	'_hook_video_extra'  => [ [ 'url' => 'https://www.aparat.com/v/xyz789', 'title' => 'دومی', 'cover' => 'https://hodima.test/v/c2.jpg', 'duration' => '1:10' ] ],
] as $key => $value ) {
	update_post_meta( $post_id, $key, $value );
}
$GLOBALS['wp_query']     = new WP_Query( [ 'p' => $post_id ] );
$GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
$GLOBALS['wp_query']->the_post();

hodima_t( 'پادکست پنهان ← شورت‌کد خالی', do_shortcode( '[hook_voice]' ), '' );
hodima_t( 'FAQ نمایش داده می‌شود', str_contains( do_shortcode( '[hook_faq]' ), 'hook-faq-item' ), true );
$video_html = do_shortcode( '[hook_video]' );
hodima_t( 'ویدیوهای بیشتر در خروجی', substr_count( $video_html, 'hook-video-card' ) >= 1, true );
$extra_nodes = hodima_media_extra_video_nodes( $post_id, 'post' );
hodima_t( 'نود ویدیوی دوم #video-2', str_ends_with( (string) ( $extra_nodes[0]['@id'] ?? '' ), '#video-2' ), true );
hodima_t( 'ویدیوی دوم embedUrl آپارات', $extra_nodes[0]['embedUrl'] ?? '', 'https://www.aparat.com/video/video/embed/videohash/xyz789/vt/frame' );
update_post_meta( $post_id, '_hook_hidden_parts', [ 'video' ] );
hodima_media_get_data( $post_id, 'post', true );
hodima_t( 'ویدیو پنهان ← بدون VideoObject', hodima_media_video_node( $post_id, 'post' ), null );
hodima_t( 'ویدیو پنهان ← بدون ویدیوی بیشتر', hodima_media_extra_video_nodes( $post_id, 'post' ), [] );
hodima_t( 'پادکست: یک دکمه سرعت', substr_count( hodima_media_speed_html(), '<button' ), 1 );
update_post_meta( $post_id, '_hook_content', '<p>معرفی <strong>کوتاه</strong> [hodima_table] این صفحه.</p>' );
update_post_meta( $post_id, '_hook_hidden_parts', [] );
hodima_media_get_data( $post_id, 'post', true );
if ( function_exists( 'hodima_seo_page_intro_text' ) ) {
	hodima_t( 'متن معرفی ← توضیح ساده (بدون HTML و شورت‌کد)', hodima_seo_page_intro_text( $post_id, 'post' ), 'معرفی کوتاه این صفحه.' );
	update_post_meta( $post_id, '_hook_hidden_parts', [ 'intro' ] );
	hodima_media_get_data( $post_id, 'post', true );
	hodima_t( 'متن معرفی پنهان ← توضیح نیست', hodima_seo_page_intro_text( $post_id, 'post' ), '' );
}
hodima_t( 'متای FAQ در REST ثبت شده', registered_meta_key_exists( 'post', '_hook_faq', 'post' ), true );
hodima_t( 'متای ویدیوهای بیشتر در REST ثبت شده', registered_meta_key_exists( 'post', '_hook_video_extra', 'post' ), true );
wp_delete_post( $post_id, true );

if ( function_exists( 'hodima_seo_discover_entity_items' ) ) {
	echo "=== Google Discover\n";
	hodima_t(
		'موضوعات با ویکی‌داده (تکراری آدرس می‌گیرد)',
		hodima_seo_discover_entity_items( "کلیپس\nگلسر Q456, کلیپس https://www.wikidata.org/wiki/Q123" ),
		[ [ 'name' => 'کلیپس', 'url' => 'https://www.wikidata.org/wiki/Q123' ], [ 'name' => 'گلسر', 'url' => 'https://www.wikidata.org/wiki/Q456' ] ]
	);
	hodima_t( 'آدرس نامعتبر پروفایل', hodima_seo_discover_clean_url( 'not a url' ), '' );
	hodima_t( 'طعمه کلیک با نیم‌فاصله', hodima_seo_discover_is_clickbait( "این باور\u{200C}نکردنی است" ), true );
	hodima_t( 'عنوان عادی', hodima_seo_discover_is_clickbait( 'راهنمای کامل انتخاب کش مو' ), false );
	hodima_t( 'برش ۱۶:۹ از ۱۶۰۰×۱۰۰۰', hodima_seo_discover_crop_size( 1600, 1000, 16, 9 ), [ 1200, 675 ] );
	hodima_t( 'برش خیلی کوچک', hodima_seo_discover_crop_size( 200, 100, 1, 1 ), [ 0, 0 ] );
	hodima_t( 'کلید آدرس Search Console', hodima_seo_discover_url_key( 'https://hodima.test/%D8%AA%D8%B3%D8%AA/' ), '/تست' );
	hodima_t( 'Discover برای محصول روشن', in_array( 'product', hodima_seo_discover_post_types(), true ), true );
	hodima_t( 'Discover برای دسته محصول روشن', in_array( 'product_cat', hodima_seo_discover_taxonomies(), true ), true );
	hodima_t( 'کلید متای ترم', hodima_seo_discover_meta_key( 'title', 'term' ), 'hook_discover_title' );
	hodima_t( 'کلید متای نوشته (همان قبلی)', hodima_seo_discover_meta_key( 'entities', 'post' ), '_hook_key_entities' );
	hodima_t( 'امتیاز آمادگی', hodima_seo_discover_score( [ [ 'status' => 'ok' ], [ 'status' => 'warn' ], [ 'status' => 'ok' ] ] ), [ 2, 3 ] );
	$cat = get_terms( [ 'taxonomy' => 'category', 'hide_empty' => false, 'number' => 1 ] );
	if ( is_array( $cat ) && $cat ) {
		hodima_t( 'دسته وبلاگ Discover ندارد', hodima_seo_discover_for_term( (int) $cat[0]->term_id ), false );
	}
}

echo "\n" . ( 0 === $failed ? "✔ همه {$passed} آزمون قبول" : "✘ {$failed} آزمون رد شد ({$passed} قبول)" ) . "\n";
exit( $failed > 0 ? 1 : 0 );
