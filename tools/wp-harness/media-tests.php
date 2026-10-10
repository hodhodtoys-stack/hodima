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

if ( function_exists( 'hodima_seo_discover_clickbait_match' ) ) {
	echo "=== Google Discover: طعمه کلیک (کلمه کامل، SEO 2.1.2)\n";
	$bait = static fn( string $t ): bool => hodima_seo_discover_is_clickbait( $t );
	hodima_t( '«موی افشان» طعمه نیست (قبلا: افشا)', $bait( '۱۰ مدل موی افشان برای عروسی' ), false );
	hodima_t( '«شیراز» طعمه نیست (قبلا: راز)', $bait( 'ارسال عمده به شیراز و اصفهان' ), false );
	hodima_t( '«فوریه» طعمه نیست (قبلا: فوری)', $bait( 'حراج فوریه اکسسوری مو' ), false );
	hodima_t( '«شانه جادویی» نام محصول است', $bait( 'شانه جادویی گره‌باز کن' ), false );
	hodima_t( '«افشای» طعمه است', $bait( 'افشای قیمت واقعی کلیپس' ), true );
	hodima_t( '«رازهای» طعمه است', $bait( 'رازهای موی سالم' ), true );
	hodima_t( '«باورتان نمی‌شود» (ادامه آزاد)', $bait( "باورتان نمی\u{200C}شود چقدر ارزان است" ), true );
	hodima_t( '«حتماً» با تنوین = «حتما»', $bait( 'این مدل را حتماً ببینید' ), true );
	hodima_t( '«ي» عربی یکسان می‌شود', $bait( 'تخفیف فوري کلیپس' ), true );
	hodima_t( 'نشانه‌گذاری «!!»', $bait( 'کلیپس جدید!!' ), true );
	hodima_t( 'نام عبارت پیداشده', hodima_seo_discover_clickbait_match( 'رازهای موی سالم' ), 'رازهای' );
	hodima_t( 'فیلتر فهرست', ( static function (): bool {
		add_filter( 'hodima_seo_discover_clickbait_phrases', $f = static fn(): array => [ 'نمونه' ] );
		$out = hodima_seo_discover_clickbait_phrases();
		remove_filter( 'hodima_seo_discover_clickbait_phrases', $f );
		return [ 'نمونه' ] === $out;
	} )(), true );

	echo "=== Google Discover: آمار و فرصت‌ها\n";
	// فرصت‌ها و نمودار فقط در پیشخوان لود می‌شوند
	if ( ! function_exists( 'hodima_seo_discover_opportunities' ) ) {
		require_once WP_PLUGIN_DIR . '/hodima-seo/core/discover/discover-insights.php';
	}
	hodima_t( 'کلید آدرس بدون utm و srsltid', hodima_seo_discover_url_key( 'https://hodima.test/x/?utm_source=a&srsltid=b&p=2' ), '/x?p=2' );
	hodima_t( 'تغییر ۱۵۰ از ۱۰۰ = ۵۰٪', hodima_seo_discover_change( 150, 100 ), 50.0 );
	hodima_t( 'تغییر از صفر = نامعلوم', hodima_seo_discover_change( 5, 0 ), null );
	hodima_t( 'سقف محور ۸۷ ← ۱۰۰', hodima_seo_discover_nice_max( 87 ), 100 );
	hodima_t( 'سقف محور ۱۲۳۴ ← ۲۰۰۰', hodima_seo_discover_nice_max( 1234 ), 2000 );
	hodima_t( 'روزهای بی‌ردیف صفر', array_keys( hodima_seo_discover_daily_filled( [ '2026-01-03' => [ 'clicks' => 1, 'impressions' => 9 ], '2026-01-01' => [ 'clicks' => 0, 'impressions' => 4 ] ] ) ), [ '2026-01-01', '2026-01-02', '2026-01-03' ] );
	$opp_row = static fn( string $key, string $type = 'page', int $ts = 0, int $warn = 0 ): array => [ 'url_key' => $key, 'type' => $type, 'ts' => $ts, 'error' => 0, 'warn' => $warn, 'issues' => [] ];
	$opp     = hodima_seo_discover_opportunities(
		[ $opp_row( '/a' ), $opp_row( '/b', 'page', 0, 1 ), $opp_row( '/c' ), $opp_row( '/new', 'post', time() - 5 * DAY_IN_SECONDS, 1 ) ],
		[
			'fetched' => 1,
			'totals'  => [ 'clicks' => 100, 'impressions' => 2000 ], // میانگین ۵٪
			'rows'    => [ '/a' => [ 'clicks' => 2, 'impressions' => 1000 ], '/b' => [ 'clicks' => 60, 'impressions' => 600 ], '/c' => [ 'clicks' => 10, 'impressions' => 100 ] ],
			'prev'    => [ 'start' => '2026-01-01', 'rows' => [ '/c' => [ 'clicks' => 30, 'impressions' => 900 ] ] ],
		]
	);
	hodima_t( 'کم‌کلیک: فقط /a', array_column( $opp['low_ctr'], 'url_key' ), [ '/a' ] );
	hodima_t( 'کلیک از دست رفته /a ≈ ۴۸', $opp['low_ctr'][0]['lost'] ?? null, 48 );
	hodima_t( 'افت نمایش: /c (۹۰۰ ← ۱۰۰)', array_column( $opp['dropping'], 'url_key' ), [ '/c' ] );
	hodima_t( 'دیده می‌شود ولی آماده نیست: /b', array_column( $opp['not_ready'], 'url_key' ), [ '/b' ] );
	hodima_t( 'مقاله تازه بدون نمایش: /new', array_column( $opp['new_unseen'], 'url_key' ), [ '/new' ] );

	echo "=== Google Discover: فهرست بررسی، کش، متا\n";
	hodima_t( 'متای عنوان Discover در REST ثبت شده', registered_meta_key_exists( 'post', '_hook_discover_title', 'post' ), true );
	hodima_t( 'متای تصویر Discover عدد است', get_registered_meta_keys( 'post', 'post' )['_hook_discover_image_id']['type'] ?? '', 'integer' );
	hodima_t( 'نسخه‌ها برای نوشته روشن', get_registered_meta_keys( 'post', 'post' )['_hook_discover_title']['revisions_enabled'] ?? null, true );

	// تصویر واقعی ۱۶۰۰×۱۰۰۰ در کتابخانه (برش، متن جایگزین، تصویر تکراری)
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$make_image = static function ( string $name ): int {
		$dir  = wp_upload_dir();
		$file = $dir['path'] . '/' . $name;
		$gd   = imagecreatetruecolor( 1600, 1000 );
		imagefill( $gd, 0, 0, (int) imagecolorallocate( $gd, 37, 49, 106 ) );
		imagejpeg( $gd, $file );
		$id = (int) wp_insert_attachment( [ 'post_mime_type' => 'image/jpeg', 'post_title' => $name, 'post_status' => 'inherit' ], $file );
		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file ) );
		return $id;
	};
	$img_a = $make_image( 'discover-test-a.jpg' );
	$img_b = $make_image( 'discover-test-b.jpg' );

	hodima_t( 'سه برش تازه ساخته شد', hodima_seo_discover_make_crops( $img_a ), 3 );
	hodima_t( 'بار دوم برش تازه‌ای لازم نیست', hodima_seo_discover_make_crops( $img_a ), 0 );

	$long = str_repeat( 'کلیپس فلزی برای موی بلند و کوتاه مناسب است. ', 40 );
	$p1   = wp_insert_post( [ 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'راهنمای انتخاب کلیپس فلزی برای موی بلند', 'post_content' => $long ] );
	$p2   = wp_insert_post( [ 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => 'راهنمای انتخاب کلیپس فلزی برای موی بلند', 'post_content' => 'کوتاه' ] );
	set_post_thumbnail( $p1, $img_a );
	set_post_thumbnail( $p2, $img_a );

	$checks = static fn( int $id ): array => array_column( hodima_seo_discover_checks( get_post( $id ) ), 'status', 'key' );
	$c1     = $checks( $p1 );
	hodima_t( 'تصویر ۱۶۰۰ پیکسلی: درست', $c1['image'] ?? '', 'ok' );
	hodima_t( 'بدون متن جایگزین: هشدار', $c1['alt'] ?? '', 'warn' );
	hodima_t( 'تصویر مشترک با نوشته دیگر: هشدار', $c1['unique_image'] ?? '', 'warn' );
	hodima_t( 'عنوان مشترک با نوشته دیگر: هشدار', $c1['unique_title'] ?? '', 'warn' );
	hodima_t( 'مطلب بلند: درست', $c1['length'] ?? '', 'ok' );
	hodima_t( 'مطلب کوتاه: هشدار', $checks( $p2 )['length'] ?? '', 'warn' );
	hodima_t( 'تازه منتشرشده: درست', $c1['fresh'] ?? '', 'ok' );

	update_post_meta( $img_a, '_wp_attachment_image_alt', 'کلیپس فلزی سرمه‌ای' );
	update_post_meta( $p2, '_hook_discover_title', 'کلیپس فلزی سرمه‌ای با فنر محکم برای موی ضخیم' );
	update_post_meta( $p2, '_hook_discover_image_id', $img_b );
	// نمایه همین درخواست کهنه است؛ در درخواست واقعی بعدی تازه ساخته می‌شود
	$c1 = array_column( ( static function () use ( $p1 ): array {
		return hodima_seo_discover_checks( get_post( $p1 ) );
	} )(), 'status', 'key' );
	hodima_t( 'با متن جایگزین: درست', $c1['alt'] ?? '', 'ok' );

	// کش ردیف: ساخته، با تغییر متا پاک، با شماره نسل کهنه
	hodima_seo_discover_forget( 'post', $p1 );
	hodima_t( 'ردیف ساخته و ذخیره شد', is_array( hodima_seo_discover_row( get_post( $p1 ) ) ) && metadata_exists( 'post', $p1, '_hodima_discover_row' ), true );
	update_post_meta( $p1, '_seobox_description', 'توضیح تازه' );
	hodima_t( 'تغییر متا ← ردیف پاک شد', metadata_exists( 'post', $p1, '_hodima_discover_row' ), false );
	hodima_seo_discover_row( get_post( $p1 ) );
	update_post_meta( $img_a, '_wp_attachment_image_alt', 'متن دیگر' );
	hodima_t( 'تغییر متن جایگزین تصویر ← ردیف صفحه‌اش پاک شد', metadata_exists( 'post', $p1, '_hodima_discover_row' ), false );
	hodima_seo_discover_row( get_post( $p1 ) );
	hodima_seo_discover_rows_reset();
	hodima_t( 'شماره نسل تازه ← ردیف کهنه', hodima_seo_discover_row( get_post( $p1 ), false ), null );

	// پیشنهاد عنوان: از چکیده؛ بدون طعمه کلیک و بدون عنوان فعلی
	wp_update_post( [ 'ID' => $p1, 'post_excerpt' => 'کلیپس فلزی سبک است و موی بلند را بدون فشار نگه می‌دارد. جمله دوم.' ] );
	$ideas = hodima_seo_discover_title_ideas( get_post( $p1 ) );
	hodima_t( 'پیشنهاد عنوان از جمله اول چکیده', in_array( 'کلیپس فلزی سبک است و موی بلند را بدون فشار نگه می‌دارد', $ideas, true ), true );
	hodima_t( 'پیشنهادها بی‌طعمه و در بازه طول', array_filter( $ideas, static fn( string $i ): bool => hodima_seo_discover_is_clickbait( $i ) || mb_strlen( $i ) < 30 || mb_strlen( $i ) > 110 ), [] );

	// دسته: برش‌های تصویر Discover تازه در همان ذخیره (باگ قبلی: edited_term پیش از ذخیره کادر)
	register_taxonomy( 'hd_test_cat', 'post' );
	add_filter( 'hodima_seo_discover_taxonomies', $tax = static fn( array $t ): array => [ ...$t, 'hd_test_cat' ] );
	$term = wp_insert_term( 'دسته آزمون Discover', 'hd_test_cat' );
	$tid  = is_array( $term ) ? (int) $term['term_id'] : 0;
	// همان ترتیب واقعی: کادر Discover در edited_{taxonomy} ذخیره می‌شود
	add_action( 'edited_hd_test_cat', $save = static fn( int $id ) => update_term_meta( $id, 'hook_discover_image_id', $img_b ) );
	wp_update_term( $tid, 'hd_test_cat', [ 'description' => 'به‌روز' ] );
	$meta_b = wp_get_attachment_metadata( $img_b );
	hodima_t( 'تصویر Discover تازه دسته در همان ذخیره برش خورد', isset( $meta_b['sizes']['hodima-discover-16x9'] ), true );
	remove_action( 'edited_hd_test_cat', $save );
	remove_filter( 'hodima_seo_discover_taxonomies', $tax );
	wp_delete_term( $tid, 'hd_test_cat' );

	// آمار یک صفحه با دوره قبل
	$saved = get_option( 'hodima_discover_sc_stats' );
	$path  = hodima_seo_discover_url_key( (string) get_permalink( $p1 ) );
	update_option( 'hodima_discover_sc_stats', [ 'fetched' => time(), 'rows' => [ $path => [ 'clicks' => 3, 'impressions' => 40 ] ], 'prev' => [ 'start' => '2026-01-01', 'rows' => [ $path => [ 'clicks' => 1, 'impressions' => 20 ] ] ] ], false );
	hodima_t( 'آمار صفحه با دوره قبل', hodima_seo_discover_post_stats( $p1 ), [ 'clicks' => 3, 'impressions' => 40, 'prev_clicks' => 1, 'prev_impressions' => 20 ] );
	false === $saved ? delete_option( 'hodima_discover_sc_stats' ) : update_option( 'hodima_discover_sc_stats', $saved, false );

	echo "=== گوگل دیسکاور: یک تصویر، کنار گذاشتن، امضای تکراری، کلید (SEO 2.1.5)\n";
	// p1: تصویر شاخص img_a؛ p2: تصویر جدای دیسکاور img_b
	hodima_seo_discover_make_crops( $img_b );
	$feat = hodima_seo_schema_featured_image( $p2 );
	hodima_t( 'تصویر اصلی اسکیما (#primaryimage) = تصویر دیسکاور', $feat[0] ?? '', (string) wp_get_attachment_url( $img_b ) );
	hodima_t( 'بدون تصویر جدا ← تصویر شاخص', hodima_seo_schema_featured_image( $p1 )[0] ?? '', (string) wp_get_attachment_url( $img_a ) );
	$objs = hodima_seo_discover_image_objects( $p2 );
	hodima_t( 'ImageObjectها: خود تصویر + سه برش', count( $objs ), 4 );
	hodima_t( 'اولین ImageObject همان تصویر دیسکاور', $objs[0]['url'] ?? '', (string) wp_get_attachment_url( $img_b ) );
	hodima_t( 'برش ۱۶:۹ در ImageObjectها', in_array( [ 1200, 675 ], array_map( static fn( array $o ): array => [ $o['width'], $o['height'] ], $objs ), true ), true );

	hodima_t( 'نوشته عادی کنار گذاشته نمی‌شود', hodima_seo_discover_skip_reason( get_post( $p1 ) ), '' );
	update_post_meta( $p1, '_hodima_discover_skip', '1' );
	hodima_t( 'انتخاب دستی ← کنار', hodima_seo_discover_skip_reason( get_post( $p1 ) ), 'manual' );
	delete_post_meta( $p1, '_hodima_discover_skip' );
	update_post_meta( $p1, '_seobox_robots', [ 'noindex', 'follow' ] );
	hodima_t( 'noindex ← کنار', hodima_seo_discover_skip_reason( get_post( $p1 ) ), 'noindex' );
	delete_post_meta( $p1, '_seobox_robots' );
	$privacy     = wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'حریم خصوصی آزمون' ] );
	$old_privacy = get_option( 'wp_page_for_privacy_policy' );
	update_option( 'wp_page_for_privacy_policy', $privacy );
	hodima_t( 'برگه حریم خصوصی ← کنار (برگه کاربردی)', hodima_seo_discover_skip_reason( get_post( $privacy ) ), 'utility' );
	hodima_t( 'متای «برای دیسکاور نیست» در REST ثبت شده', registered_meta_key_exists( 'post', '_hodima_discover_skip', 'post' ), true );

	// امضای تکراری: صفحه دیگر تصویر مشترک را عوض کند ← ردیف این صفحه کهنه
	update_post_meta( $p2, '_hook_discover_image_id', $img_a );
	hodima_seo_discover_forget( 'post', $p1 );
	$row = hodima_seo_discover_row( get_post( $p1 ) );
	hodima_t( 'ردیف: تصویر مشترک با p2', in_array( 'unique_image', array_column( (array) ( $row['issues'] ?? [] ), 'key' ), true ), true );
	hodima_t( 'نمایه سایت در ترنزینت ماند', is_array( get_transient( 'hodima_discover_index' ) ), true );
	hodima_t( 'ردیف تازه معتبر است', is_array( hodima_seo_discover_row( get_post( $p1 ), false ) ), true );
	update_post_meta( $p2, '_hook_discover_image_id', $img_b );
	hodima_t( 'p2 تصویرش را عوض کرد ← ردیف p1 کهنه (امضای تکراری)', hodima_seo_discover_row( get_post( $p1 ), false ), null );
	$row = hodima_seo_discover_row( get_post( $p1 ) );
	hodima_t( 'ردیف دوباره: تصویر اختصاصی درست', in_array( 'unique_image', array_column( (array) ( $row['issues'] ?? [] ), 'key' ), true ), false );

	// کلید سرچ کنسول: مستقل از ماژول Google Indexing، کلید جدای دیسکاور مقدم
	hodima_t( 'کلید نامعتبر رد می‌شود', hodima_seo_discover_sc_validate_key( '{"type":"x"}' )['ok'], false );
	$make_key = static function ( string $email ): string {
		$pem = '';
		openssl_pkey_export( openssl_pkey_new( [ 'private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA ] ), $pem );
		return (string) wp_json_encode( [ 'type' => 'service_account', 'client_email' => $email, 'private_key' => $pem, 'token_uri' => 'https://oauth2.googleapis.com/token' ] );
	};
	$own_key   = $make_key( 'discover@test.iam.gserviceaccount.com' );
	$gi_key    = $make_key( 'indexing@test.iam.gserviceaccount.com' );
	$saved_own = get_option( 'hodima_discover_sc_key' );
	$saved_gi  = get_option( 'hodima_gi_json_key' );
	hodima_t( 'کلید درست قبول، با ایمیل', hodima_seo_discover_sc_validate_key( $own_key ), [ 'ok' => true, 'message' => '', 'email' => 'discover@test.iam.gserviceaccount.com' ] );
	delete_option( 'hodima_discover_sc_key' );
	update_option( 'hodima_gi_json_key', $gi_key, false );
	hodima_t( 'بدون کلید جدا ← کلید ماژول Indexing', [ hodima_seo_discover_sc_key_source(), hodima_seo_discover_sc_email() ], [ 'indexing', 'indexing@test.iam.gserviceaccount.com' ] );
	update_option( 'hodima_discover_sc_key', $own_key, false );
	hodima_t( 'کلید جدای دیسکاور مقدم (ایمیل عوض شد)', [ hodima_seo_discover_sc_key_source(), hodima_seo_discover_sc_email() ], [ 'own', 'discover@test.iam.gserviceaccount.com' ] );
	false === $saved_own ? delete_option( 'hodima_discover_sc_key' ) : update_option( 'hodima_discover_sc_key', $saved_own, false );
	false === $saved_gi ? delete_option( 'hodima_gi_json_key' ) : update_option( 'hodima_gi_json_key', $saved_gi, false );

	echo "=== گوگل دیسکاور: نقطه تمرکز، property، تاریخچه و تغییرها (SEO 2.1.6)\n";
	hodima_t( 'نقطه تمرکز «0.3,0.4»', hodima_seo_discover_parse_focus( '0.3,0.4' ), [ 0.3, 0.4 ] );
	hodima_t( 'نقطه تمرکز بیرون از تصویر محدود می‌شود', hodima_seo_discover_parse_focus( '1.5,-1' ), [ 1.0, 0.0 ] );
	hodima_t( 'نقطه تمرکز نامعتبر', hodima_seo_discover_parse_focus( 'abc' ), null );
	hodima_t( 'ناحیه ۱۶:۹ وسط از ۱۶۰۰×۱۰۰۰', hodima_seo_discover_crop_rect( 1600, 1000, 16, 9, 0.5, 0.5 ), [ 'x' => 0, 'y' => 50, 'w' => 1600, 'h' => 900 ] );
	hodima_t( 'ناحیه ۱:۱ با نقطه چپ (محدود به لبه)', hodima_seo_discover_crop_rect( 1600, 1000, 1, 1, 0.1, 0.5 ), [ 'x' => 0, 'y' => 0, 'w' => 1000, 'h' => 1000 ] );
	hodima_t( 'ناحیه ۱:۱ با نقطه ۷۰٪', hodima_seo_discover_crop_rect( 1600, 1000, 1, 1, 0.7, 0.5 ), [ 'x' => 600, 'y' => 0, 'w' => 1000, 'h' => 1000 ] );

	hodima_seo_discover_set_focus( $img_b, '0.50,0.50' );
	hodima_seo_discover_make_crops( $img_b );
	$before_files = array_column( wp_get_attachment_metadata( $img_b )['sizes'] ?? [], 'file', null );
	hodima_t( 'نقطه تازه ذخیره شد', hodima_seo_discover_set_focus( $img_b, '0.2,0.3' ), true );
	hodima_t( 'با نقطه تازه سه برش دوباره ساخته شد', hodima_seo_discover_make_crops( $img_b ), 3 );
	$meta_f = wp_get_attachment_metadata( $img_b );
	hodima_t( 'نام فایل برش نقطه را دارد', str_contains( (string) ( $meta_f['sizes']['hodima-discover-16x9']['file'] ?? '' ), '-f20-30' ), true );
	hodima_t( 'نقطه در اطلاعات برش', $meta_f['sizes']['hodima-discover-1x1']['hodima_focus'] ?? '', '0.20,0.30' );
	$dir_b = dirname( (string) get_attached_file( $img_b ) );
	hodima_t( 'فایل برش‌های وسط قبلی پاک شد', array_values( array_filter( (array) $before_files, static fn( string $f ): bool => is_file( $dir_b . '/' . $f ) && ! in_array( $f, array_column( $meta_f['sizes'], 'file' ), true ) ) ), [] );
	hodima_t( 'بار دوم با همان نقطه برشی ساخته نمی‌شود', hodima_seo_discover_make_crops( $img_b ), 0 );
	hodima_t( 'تکرار همان نقطه = بدون تغییر', hodima_seo_discover_set_focus( $img_b, '0.20,0.30' ), false );
	$old_crop = $dir_b . '/' . $meta_f['sizes']['hodima-discover-16x9']['file'];
	hodima_seo_discover_set_focus( $img_b, '0.5,0.5' );
	hodima_seo_discover_make_crops( $img_b );
	hodima_t( 'برگشت به وسط: نام عادی و فایل نقطه قبلی پاک', [ str_contains( (string) ( wp_get_attachment_metadata( $img_b )['sizes']['hodima-discover-16x9']['file'] ?? '' ), '-f' ), is_file( $old_crop ) ], [ false, false ] );
	hodima_t( 'متای نقطه تمرکز تصویر در REST ثبت شده', registered_meta_key_exists( 'post', '_hodima_discover_focus', 'attachment' ), true );

	hodima_t( 'property: ایمیل رد می‌شود', is_wp_error( hodima_seo_discover_sc_clean_property( 'google-indexing-api@engaged-diode-465907-v7.iam.gserviceaccount.com' ) ), true );
	hodima_t( 'property: آدرس با / آخر', hodima_seo_discover_sc_clean_property( 'https://HodhodLi.com' ), 'https://hodhodli.com/' );
	hodima_t( 'property: دامنه', hodima_seo_discover_sc_clean_property( 'sc-domain:Hodhodli.com' ), 'sc-domain:hodhodli.com' );
	hodima_t( 'property: دامنه خالی ← sc-domain', hodima_seo_discover_sc_clean_property( 'hodhodli.com' ), 'sc-domain:hodhodli.com' );
	hodima_t( 'property: آدرس با نام کاربری رد', is_wp_error( hodima_seo_discover_sc_clean_property( 'https://user@hodhodli.com/' ) ), true );
	$saved_sc    = get_option( 'hodima_discover_sc_settings' );
	$saved_sites = get_option( 'hodima_discover_sc_sites' );
	update_option( 'hodima_discover_sc_settings', [ 'property' => 'https://google-indexing-api@engaged-diode-465907-v7.iam.gserviceaccount.com/' ], false );
	hodima_t( 'property نامعتبر ذخیره‌شده قبلی نادیده گرفته می‌شود', hodima_seo_discover_sc_property(), '' );
	update_option( 'hodima_discover_sc_sites', [ 'https://other.example/', 'sc-domain:hodima.test' ], false );
	hodima_t( 'property خودکار: اول property دامنه همین سایت که حساب به آن دسترسی دارد', hodima_seo_discover_sc_candidates()[0] ?? '', 'sc-domain:hodima.test' );

	// تغییرها: یکی شدن تغییرهای پشت هم و برگشت
	$saved_changes = get_option( 'hodima_discover_changes' );
	delete_option( 'hodima_discover_changes' );
	hodima_seo_discover_log_change( 'post', $p1, 'title', 'الف', 'ب' );
	hodima_seo_discover_log_change( 'post', $p1, 'title', 'ب', 'ج' );
	hodima_t( 'دو تغییر پشت هم یکی شد (الف ← ج)', array_map( static fn( array $c ): string => $c['from'] . '>' . $c['to'], hodima_seo_discover_changes() ), [ 'الف>ج' ] );
	hodima_seo_discover_log_change( 'post', $p1, 'title', 'ج', 'الف' );
	hodima_t( 'برگشت به حالت قبل ← تغییری نماند', hodima_seo_discover_changes(), [] );

	// یک تغییر ۱۰ روز پیش (برای «قبل و بعد») و به‌روزرسانی آمار با سرچ کنسول شبیه‌سازی‌شده
	update_option( 'hodima_discover_changes', [ [ 't' => time() - 10 * DAY_IN_SECONDS, 'ctx' => 'post', 'id' => $p1, 'key' => hodima_seo_discover_url_key( (string) get_permalink( $p1 ) ), 'what' => 'title', 'from' => 'قدیم', 'to' => 'تازه' ] ], false );
	$saved_key   = get_option( 'hodima_discover_sc_key' );
	$saved_hist  = get_option( 'hodima_discover_sc_history' );
	$saved_stats = get_option( 'hodima_discover_sc_stats' );
	delete_option( 'hodima_discover_sc_history' );
	delete_option( 'hodima_discover_sc_settings' );
	update_option( 'hodima_discover_sc_key', $own_key, false );
	$p1_url   = (string) get_permalink( $p1 );
	$change_d = hodima_seo_discover_sc_day( time() - 10 * DAY_IN_SECONDS );
	$calls    = [];
	$mock     = static function ( $pre, array $args, string $url ) use ( &$calls, $p1_url, $change_d ) {
		$json = static fn( array $body ): array => [ 'headers' => [], 'body' => (string) wp_json_encode( $body ), 'response' => [ 'code' => 200, 'message' => 'OK' ], 'cookies' => [] ];
		if ( str_contains( $url, 'oauth2.googleapis.com/token' ) ) {
			return $json( [ 'access_token' => 'test-token', 'expires_in' => 3600 ] );
		}
		if ( str_ends_with( $url, '/webmasters/v3/sites' ) ) {
			return $json( [ 'siteEntry' => [ [ 'siteUrl' => 'sc-domain:hodima.test', 'permissionLevel' => 'siteOwner' ] ] ] );
		}
		if ( ! str_contains( $url, '/searchAnalytics/query' ) ) {
			return $pre;
		}
		$q       = json_decode( (string) $args['body'], true );
		$calls[] = implode( '+', $q['dimensions'] ) . ' ' . $q['startDate'];
		$rows    = [];
		for ( $d = strtotime( $q['startDate'] . ' 12:00 UTC' ); $d <= strtotime( $q['endDate'] . ' 12:00 UTC' ); $d += DAY_IN_SECONDS ) {
			$day = gmdate( 'Y-m-d', $d );
			if ( [ 'date' ] === $q['dimensions'] ) {
				$rows[] = [ 'keys' => [ $day ], 'clicks' => 1, 'impressions' => 10 ];
			} elseif ( [ 'page', 'date' ] === $q['dimensions'] ) {
				$rows[] = [ 'keys' => [ $p1_url, $day ], 'clicks' => 0, 'impressions' => $day > $change_d ? 9 : 3 ];
			}
		}
		if ( [ 'page' ] === $q['dimensions'] ) {
			$rows[] = [ 'keys' => [ $p1_url ], 'clicks' => 4, 'impressions' => 120 ];
		}
		return $json( [ 'rows' => $rows ] );
	};
	add_filter( 'pre_http_request', $mock, 10, 3 );
	hodima_t( 'به‌روزرسانی آمار (شبیه‌سازی‌شده) موفق', hodima_seo_discover_sc_refresh(), true );
	$hist = hodima_seo_discover_history();
	hodima_t( 'property از فهرست حساب انتخاب شد', $hist['property'], 'sc-domain:hodima.test' );
	hodima_t( 'تاریخچه روزانه ۱۶ ماه پر شد', $hist['backfilled'] && count( $hist['daily'] ) > 470, true );
	hodima_t( 'سه ماه کامل صفحه‌ها در دور اول', count( $hist['months'] ), 3 );
	hodima_t( 'ماه به ماه صفحه: ۱۲۰ نمایش', array_values( hodima_seo_discover_page_months( hodima_seo_discover_url_key( $p1_url ), 1 ) )[0]['impressions'] ?? 0, 120 );
	hodima_t( 'روزانه صفحه تغییرکرده ذخیره شد', count( $hist['pages_daily'][ hodima_seo_discover_url_key( $p1_url ) ] ?? [] ) > 60, true );
	$effect = hodima_seo_discover_change_effect( hodima_seo_discover_changes()[0] );
	hodima_t( 'اثر تغییر: نمایش روزانه ۳ ← ۹', [ round( $effect['before']['impressions'] ?? 0, 1 ), round( $effect['after']['impressions'] ?? 0, 1 ), $effect['before_days'] ?? 0 ], [ 3.0, 9.0, 28 ] );
	hodima_t( 'متن اثر تغییر', str_starts_with( hodima_seo_discover_effect_text( $effect ), 'نمایش ' ), true );
	hodima_t( 'جمع ماهانه کل سایت', ( array_values( hodima_seo_discover_history_monthly() )[1]['impressions'] ?? 0 ) > 250, true );
	hodima_t( 'روزانه یک سال از تاریخچه', count( hodima_seo_discover_history_daily( 365 ) ), 365 );
	$calls = [];
	hodima_seo_discover_sc_refresh();
	hodima_t( 'دور دوم: ۱۶ ماه دوباره گرفته نمی‌شود، سه ماه دیگر پر می‌شود', [ count( array_filter( $calls, static fn( string $c ): bool => str_starts_with( $c, 'page ' ) ) ), count( hodima_seo_discover_history()['months'] ) ], [ 5, 6 ] );
	hodima_t( 'نام ماه میلادی فارسی', hodima_seo_discover_month_label( '2026-05' ), 'مه ۲۰۲۶' );
	remove_filter( 'pre_http_request', $mock, 10 );

	foreach ( [ 'hodima_discover_sc_key' => $saved_key, 'hodima_discover_sc_history' => $saved_hist, 'hodima_discover_sc_stats' => $saved_stats, 'hodima_discover_changes' => $saved_changes, 'hodima_discover_sc_settings' => $saved_sc, 'hodima_discover_sc_sites' => $saved_sites ] as $option => $value ) {
		false === $value ? delete_option( $option ) : update_option( $option, $value, false );
	}

	false === $old_privacy ? delete_option( 'wp_page_for_privacy_policy' ) : update_option( 'wp_page_for_privacy_policy', $old_privacy );
	wp_delete_post( $privacy, true );

	foreach ( [ $p1, $p2, $img_a, $img_b ] as $id ) {
		wp_delete_post( $id, true );
	}
}

echo "\n" . ( 0 === $failed ? "✔ همه {$passed} آزمون قبول" : "✘ {$failed} آزمون رد شد ({$passed} قبول)" ) . "\n";
exit( $failed > 0 ? 1 : 0 );
