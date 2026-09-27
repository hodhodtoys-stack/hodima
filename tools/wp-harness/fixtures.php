<?php
// Fresh install + fixture content. Usage: HODIMA_WP=… HARNESS_WC=1 php fixtures.php
// Optional HARNESS_OPTS: PHP code run after the fixtures (e.g. switch an option off).
define( 'WP_INSTALLING', true );
$_SERVER['HTTP_HOST'] = 'hodima.test'; $_SERVER['REQUEST_URI'] = '/'; $_SERVER['HTTPS'] = 'on';
$harness_wp = rtrim( getenv( 'HODIMA_WP' ) ?: '/tmp/hodima-harness/wp', '/' );
require "$harness_wp/wp-load.php";
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

wp_install( 'هدهدلی', 'admin', 'admin@hodima.test', true, '', 'pass' );
update_option( 'permalink_structure', '/%postname%/' );
update_option( 'WPLANG', 'fa_IR' ); update_option( 'timezone_string', 'Asia/Tehran' );
update_option( 'blogdescription', 'پخش عمده اکسسوری مو' );
switch_theme( 'hodima' );
update_option( 'active_plugins', [ 'hodima-core/hodima-core.php', 'hodima-commerce/hodima-commerce.php', 'hodima-media/hodima-media.php', 'hodima-seo/hodima-seo.php' ] );
update_option( 'hodima_schema_image_enable', '1' );
update_option( 'hodima_schema_homepage_org_name', 'بازرگانی هدهد' );

function att( $name, $w = 800, $h = 600 ) {
	$id = wp_insert_post( [ 'post_type' => 'attachment', 'post_title' => $name, 'post_status' => 'inherit', 'post_mime_type' => 'image/jpeg', 'guid' => "https://hodima.test/wp-content/uploads/2025/01/$name.jpg" ] );
	update_post_meta( $id, '_wp_attached_file', "2025/01/$name.jpg" );
	update_post_meta( $id, '_wp_attachment_metadata', [ 'width' => $w, 'height' => $h, 'file' => "2025/01/$name.jpg", 'sizes' => [] ] );
	update_post_meta( $id, '_wp_attachment_image_alt', "alt $name" );
	return $id;
}
function p( $args, $meta = [] ) {
	$args += [ 'post_status' => 'publish', 'post_author' => 1, 'post_date' => '2025-03-01 10:00:00', 'post_date_gmt' => '2025-03-01 06:30:00' ];
	$id = wp_insert_post( $args );
	foreach ( $meta as $k => $v ) update_post_meta( $id, $k, $v );
	return $id;
}
$img1 = att( 'hero' ); $img2 = att( 'post' ); $img3 = att( 'cat' ); $img4 = att( 'prod' ); $img5 = att( 'gal' );

$faq = [ [ 'q' => 'حداقل سفارش چقدر است؟', 'a' => 'یک بسته' ], [ 'q' => 'ارسال چند روزه است؟', 'a' => 'دو تا چهار روز' ] ];
$media = static fn( $extra = [] ) => array_merge( [ '_hook_enabled' => 'yes', '_hook_faq' => $faq, '_hook_video_url' => 'https://hodima.test/v/intro.mp4', '_hook_video_cover' => 'https://hodima.test/v/cover.jpg', '_hook_video_duration' => '02:30', '_hook_video_date' => '2025-02-01T10:00:00+00:00', '_hook_voice_url' => 'https://hodima.test/a/voice.mp3', '_hook_voice_date' => '2025-02-02T10:00:00+00:00' ], $extra );

$home = p( [ 'post_type' => 'page', 'post_title' => 'خانه', 'post_name' => 'home', 'post_content' => '<p>سلام</p><img src="https://hodima.test/wp-content/uploads/2025/01/hero.jpg" alt="x">' ], $media() );
set_post_thumbnail( $home, $img1 );
$blog = p( [ 'post_type' => 'page', 'post_title' => 'وبلاگ', 'post_name' => 'blog' ] );
update_option( 'show_on_front', 'page' ); update_option( 'page_on_front', $home ); update_option( 'page_for_posts', $blog );
$about = p( [ 'post_type' => 'page', 'post_title' => 'درباره ما', 'post_name' => 'about-us', 'post_excerpt' => 'معرفی شرکت' ] );
$contact = p( [ 'post_type' => 'page', 'post_title' => 'تماس با ما', 'post_name' => 'contact-us' ] );
$plain = p( [ 'post_type' => 'page', 'post_title' => 'راهنمای خرید', 'post_name' => 'guide', 'post_content' => 'متن راهنما [hodima_table]' ], $media( [ '_hook_discover_title' => 'راهنمای کامل خرید عمده', '_hook_ai_summary' => 'خلاصه هوش مصنوعی', '_hook_key_entities' => 'کلیپس, گلسر', '_h_ai_faqs' => wp_json_encode( [ [ 'q' => 'حداقل سفارش چقدر است؟', 'a' => 'یک بسته' ], [ 'q' => 'پرداخت چگونه است؟', 'a' => 'کارت به کارت' ] ], JSON_UNESCAPED_UNICODE ) ] ) );
update_post_meta( $plain, '_hodima_table_data', [ 'headers' => [ 'ویژگی', 'مقدار' ], 'rows' => [ [ 'جنس', 'فلز' ], [ 'رنگ', 'طلایی' ] ] ] );
$videos = p( [ 'post_type' => 'page', 'post_title' => 'ویدئوها', 'post_name' => 'videos' ], [ '_wp_page_template' => 'template-page-videos.php' ] );

$cat = wp_insert_term( 'اخبار', 'category', [ 'slug' => 'news', 'description' => 'اخبار بازار' ] )['term_id'];
$tag = wp_insert_term( 'کلیپس', 'post_tag', [ 'slug' => 'clips' ] )['term_id'];
$pillar = p( [ 'post_title' => 'راهنمای جامع کلیپس', 'post_name' => 'clips-guide', 'post_content' => '<p>پیلار</p><img src="https://hodima.test/wp-content/uploads/2025/01/post.jpg" width="640" height="480" alt="p">', 'post_excerpt' => 'خلاصه پیلار', 'post_category' => [ $cat ], 'tags_input' => [ 'کلیپس' ] ], $media( [ '_hook_ai_summary' => 'چکیده', '_hook_key_entities' => 'کلیپس، گلسر', '_h_ai_text' => 'متن AEO' ] ) );
set_post_thumbnail( $pillar, $img2 );
$child = p( [ 'post_title' => 'انواع کلیپس فلزی', 'post_name' => 'metal-clips', 'post_content' => 'فرزند', 'post_category' => [ $cat ], 'post_date' => '2025-03-02 10:00:00', 'post_date_gmt' => '2025-03-02 06:30:00' ], [ '_h_ai_text' => 'متن هوش مصنوعی فرزند' ] );
update_term_meta( $cat, '_hodima_is_pillar', '1' );
add_post_meta( $child, '_hodima_pillar_id', $cat );
update_post_meta( $plain, '_hodima_is_pillar', '1' );
$gchild = p( [ 'post_type' => 'page', 'post_title' => 'زیرراهنما', 'post_name' => 'guide-child', 'post_content' => 'فرزند برگه' ], [ '_hodima_pillar_id' => $plain ] );

$vid = p( [ 'post_type' => 'video', 'post_title' => 'ویدیو معرفی گلسر', 'post_name' => 'hairpin-video', 'post_excerpt' => 'معرفی گلسر' ], [ '_hod_video_url' => 'https://hodima.test/v/pin.mp4', '_hod_video_thumbnail' => 'https://hodima.test/v/pin.jpg', '_hod_video_duration' => 'PT1M10S', '_hod_video_chapters' => "00:00 شروع\n00:30 جنس" ] );

// WooCommerce side
$pcat = wp_insert_term( 'اکسسوری مو', 'product_cat', [ 'slug' => 'hair', 'description' => 'اکسسوری' ] )['term_id'];
update_term_meta( $pcat, 'thumbnail_id', $img3 );
foreach ( [ 'hook_enabled' => 'yes', 'hook_faq' => $faq, 'hook_video_url' => 'https://hodima.test/v/cat.mp4', 'hook_video_cover' => 'https://hodima.test/v/catc.jpg', '_h_ai_faqs' => wp_json_encode( [ [ 'q' => 'سوال دسته', 'a' => 'جواب' ] ], JSON_UNESCAPED_UNICODE ) ] as $k => $v ) update_term_meta( $pcat, $k, $v );
$ptag = wp_insert_term( 'پرفروش', 'product_tag', [ 'slug' => 'best' ] )['term_id'];
$shop = p( [ 'post_type' => 'page', 'post_title' => 'فروشگاه', 'post_name' => 'shop' ] );
update_option( 'woocommerce_shop_page_id', $shop );
$prod = p( [ 'post_type' => 'product', 'post_title' => 'کلیپس فلزی طرح ۱۰۳', 'post_name' => '103', 'post_content' => 'توضیح <img src="https://hodima.test/wp-content/uploads/2025/01/prod.jpg" alt="c">', 'post_excerpt' => 'کوتاه' ], array_merge( $media( [ '_hook_video_url' => 'https://www.aparat.com/v/abc' ] ), [ '_sku' => 'CL-103', '_price' => '250000', '_regular_price' => '250000', '_stock_status' => 'instock', '_product_image_gallery' => (string) $img5, '_wc_review_count' => 2, '_wc_average_rating' => '4.50' ] ) );
set_post_thumbnail( $prod, $img4 );
wp_set_object_terms( $prod, [ $pcat ], 'product_cat' ); wp_set_object_terms( $prod, [ $ptag ], 'product_tag' );
$prod2 = p( [ 'post_type' => 'product', 'post_title' => 'گلسر ساده', 'post_name' => 'pin-simple', 'post_content' => 'ساده' ], [ '_sku' => 'PN-1', '_price' => '90000', '_stock_status' => 'outofstock' ] );
wp_set_object_terms( $prod2, [ $pcat ], 'product_cat' ); wp_set_object_terms( $prod2, [ $ptag ], 'product_tag' );

update_option( 'posts_per_page', 1 );
if ( $o = getenv( 'HARNESS_OPTS' ) ) { eval( $o ); }
flush_rewrite_rules( true );
file_put_contents( dirname( $harness_wp ) . '/ids.json', json_encode( compact( 'gchild', 'home', 'blog', 'about', 'contact', 'plain', 'videos', 'pillar', 'child', 'vid', 'prod', 'prod2', 'shop', 'cat', 'tag', 'pcat', 'ptag' ) ) );
echo "installed\n";
