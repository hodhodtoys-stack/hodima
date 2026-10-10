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

	foreach ( [ $p1, $p2, $img_a, $img_b ] as $id ) {
		wp_delete_post( $id, true );
	}
}

echo "\n" . ( 0 === $failed ? "✔ همه {$passed} آزمون قبول" : "✘ {$failed} آزمون رد شد ({$passed} قبول)" ) . "\n";
exit( $failed > 0 ? 1 : 0 );
