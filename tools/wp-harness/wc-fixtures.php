<?php
// نصب تازه + داده آزمایشی با ووکامرس *واقعی* (ابزار wc-*؛ نوسازی قالب مرحله ۶).
// Usage: HODIMA_WP=/tmp/hodima-harness-wc/wp php wc-fixtures.php
// محصول ساده کامل (گالری ۴ تصویر، جدول «حداقل خرید»، ویژگی‌ها، نظرها، رسانه، مرتبط/مکمل)،
// محصول متغیر، ناموجود و حراج، دسته با زیردسته و توضیح، فروشگاه دوصفحه‌ای، برگه‌های سبد/پرداخت.
$_SERVER['HTTP_HOST'] = 'hodima.test'; $_SERVER['REQUEST_URI'] = '/'; $_SERVER['HTTPS'] = 'on';
$harness_wp = rtrim( getenv( 'HODIMA_WP' ) ?: '/tmp/hodima-harness-wc/wp', '/' );
// دو مرحله در دو اجرای جدا: ۱) نصب وردپرس و فعال‌سازی افزونه‌ها؛ ۲) با ووکامرس *لودشده*
// (هوک‌های woocommerce_init و ثبت نوع‌ها اجرا شده‌اند) جدول‌ها و داده. wc-run.sh هر دو را صدا می‌زند.
$phase = $argv[1] ?? 'install';
if ( 'install' === $phase ) {
	define( 'WP_INSTALLING', true );
}
require "$harness_wp/wp-load.php";
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
if ( 'install' === $phase ) {
add_filter( 'pre_wp_mail', '__return_false' );
wp_install( 'هدهدلی', 'admin', 'admin@hodima.test', true, '', 'pass' );
update_option( 'permalink_structure', '/%postname%/' );
update_option( 'WPLANG', 'fa_IR' ); update_option( 'timezone_string', 'Asia/Tehran' );
update_option( 'blogdescription', 'پخش عمده اکسسوری مو' );
switch_theme( 'hodima' );

// ووکامرس اول (افزونه‌های هدیما با class_exists به آن نگاه می‌کنند)؛ نصب جدول‌هایش در مرحله ۲
update_option( 'active_plugins', [ 'woocommerce/woocommerce.php', 'hodima-core/hodima-core.php', 'hodima-commerce/hodima-commerce.php', 'hodima-media/hodima-media.php', 'hodima-seo/hodima-seo.php' ] );
	echo "installed\n";
	return;
}

if ( class_exists( 'WC_Install' ) ) {
	WC_Install::install();
	WC_Install::create_pages();
	// CSS قالب (cart-page.css) برای سبد/پرداخت کلاسیک (شورت‌کد) است، نه بلوک‌های ووکامرس ۹
	wp_update_post( [ 'ID' => wc_get_page_id( 'cart' ), 'post_content' => '[woocommerce_cart]' ] );
	wp_update_post( [ 'ID' => wc_get_page_id( 'checkout' ), 'post_content' => '[woocommerce_checkout]' ] );
}
update_option( 'woocommerce_currency', 'IRT' );
update_option( 'woocommerce_price_num_decimals', '0' );
update_option( 'woocommerce_default_country', 'IR:THR' );
update_option( 'woocommerce_coming_soon', 'no' );
update_option( 'woocommerce_onboarding_profile', [ 'skipped' => true ] );
update_option( 'woocommerce_enable_reviews', 'yes' );
update_option( 'woocommerce_enable_review_rating', 'yes' );
update_option( 'hodima_schema_image_enable', '1' );

function att( $name, $w = 800, $h = 800 ) {
	$id = wp_insert_post( [ 'post_type' => 'attachment', 'post_title' => $name, 'post_status' => 'inherit', 'post_mime_type' => 'image/jpeg', 'guid' => "https://hodima.test/wp-content/uploads/2025/01/$name.jpg" ] );
	update_post_meta( $id, '_wp_attached_file', "2025/01/$name.jpg" );
	update_post_meta( $id, '_wp_attachment_metadata', [ 'width' => $w, 'height' => $h, 'file' => "2025/01/$name.jpg", 'sizes' => [] ] );
	update_post_meta( $id, '_wp_attachment_image_alt', "alt $name" );
	return $id;
}
function page( $title, $slug, $content = '' ) {
	return wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => $slug, 'post_content' => $content ] );
}
$imgs = array_map( 'att', [ 'p1', 'p2', 'p3', 'p4', 'p5', 'p6', 'p7', 'p8', 'cat', 'sub' ] );

$faq = [ [ 'q' => 'حداقل سفارش چقدر است؟', 'a' => 'یک بسته' ], [ 'q' => 'ارسال چند روزه است؟', 'a' => 'دو تا چهار روز' ] ];
$media = [ '_hook_enabled' => 'yes', '_hook_faq' => $faq, '_hook_video_url' => 'https://hodima.test/v/intro.mp4', '_hook_video_cover' => 'https://hodima.test/v/cover.jpg', '_hook_video_duration' => '02:30', '_hook_video_date' => '2025-02-01T10:00:00+00:00', '_hook_voice_url' => 'https://hodima.test/a/voice.mp3', '_hook_voice_date' => '2025-02-02T10:00:00+00:00' ];

$home = page( 'خانه', 'home', '<p>سلام</p>' );
update_option( 'show_on_front', 'page' ); update_option( 'page_on_front', $home );

// ویژگی‌های سراسری (جدول مشخصات woo-table از pa_* می‌خواند)
$attr = [];
foreach ( [ 'color' => 'رنگ', 'material' => 'جنس', 'size' => 'سایز', 'tolid' => 'تولید' ] as $slug => $label ) {
	if ( ! taxonomy_exists( "pa_$slug" ) ) {
		wc_create_attribute( [ 'name' => $label, 'slug' => $slug ] );
		register_taxonomy( "pa_$slug", 'product', [ 'hierarchical' => false ] );
	}
	$attr[ $slug ] = "pa_$slug";
}
$term = static fn( $tax, $name, $slug ) => wp_insert_term( $name, $tax, [ 'slug' => $slug ] )['term_id'];
$red = $term( 'pa_color', 'قرمز', 'red' ); $blue = $term( 'pa_color', 'آبی', 'blue' ); $gold = $term( 'pa_color', 'طلایی', 'gold' );
$metal = $term( 'pa_material', 'فلز', 'metal' ); $big = $term( 'pa_size', 'بزرگ', 'big' ); $china = $term( 'pa_tolid', 'چین', 'china' );
$global_attr = static function ( $tax, array $ids, $variation = false, $pos = 0 ) {
	$a = new WC_Product_Attribute();
	$a->set_id( wc_attribute_taxonomy_id_by_name( $tax ) );
	$a->set_name( $tax ); $a->set_options( $ids ); $a->set_position( $pos );
	$a->set_visible( true ); $a->set_variation( $variation );
	return $a;
};

$cat = wp_insert_term( 'اکسسوری مو', 'product_cat', [ 'slug' => 'hair', 'description' => '<p>توضیح دسته اکسسوری مو با <strong>متن پررنگ</strong> و یک بند دیگر.</p><p>بند دوم توضیح دسته.</p>' ] )['term_id'];
update_term_meta( $cat, 'thumbnail_id', $imgs[8] );
foreach ( [ 'hook_enabled' => 'yes', 'hook_faq' => $faq, 'hook_video_url' => 'https://hodima.test/v/cat.mp4', 'hook_video_cover' => 'https://hodima.test/v/catc.jpg' ] as $k => $v ) update_term_meta( $cat, $k, $v );
$sub = wp_insert_term( 'کلیپس', 'product_cat', [ 'slug' => 'clips', 'parent' => $cat, 'description' => 'زیردسته' ] )['term_id'];
update_term_meta( $sub, 'thumbnail_id', $imgs[9] );
$tag = wp_insert_term( 'پرفروش', 'product_tag', [ 'slug' => 'best' ] )['term_id'];

$short = '<table><tbody><tr><td>حداقل خرید</td><td>۱۲ عدد</td></tr><tr><td>بسته‌بندی</td><td>کارتن</td></tr></tbody></table><p>کلیپس فلزی با روکش طلایی.</p>';
$make = static function ( $cls, $name, $slug, array $set ) use ( $cat, $sub ) {
	$p = new $cls();
	$p->set_name( $name ); $p->set_slug( $slug ); $p->set_status( 'publish' );
	$p->set_category_ids( $set['cats'] ?? [ $cat, $sub ] );
	foreach ( $set as $k => $v ) {
		if ( 'cats' !== $k && 'meta' !== $k && method_exists( $p, "set_$k" ) ) $p->{"set_$k"}( $v );
	}
	$id = $p->save();
	foreach ( $set['meta'] ?? [] as $k => $v ) update_post_meta( $id, $k, $v );
	return $id;
};

// محصول اصلی: همه بخش‌های صفحه محصول
$main = $make( 'WC_Product_Simple', 'کلیپس فلزی طرح ۱۰۳', '103', [
	'regular_price' => '250000', 'sku' => 'CL-103', 'weight' => '0.5', 'stock_status' => 'instock', 'tag_ids' => [ $tag ],
	'description' => '<h2>معرفی محصول</h2><p>توضیح کامل کلیپس فلزی. ' . str_repeat( 'متن نمونه برای توضیح محصول. ', 30 ) . '</p><ul><li>مورد یک</li><li>مورد دو</li></ul>',
	'short_description' => $short, 'image_id' => $imgs[0], 'gallery_image_ids' => [ $imgs[1], $imgs[2], $imgs[3] ],
	'attributes' => [ $global_attr( 'pa_color', [ $gold ] ), $global_attr( 'pa_material', [ $metal ], false, 1 ), $global_attr( 'pa_size', [ $big ], false, 2 ), $global_attr( 'pa_tolid', [ $china ], false, 3 ) ],
	'meta' => $media + [ '_stock_location_status' => 'iran_stock', '_wholesale_price' => '3000000' ],
] );
// متغیر
$var = $make( 'WC_Product_Variable', 'کش مو رنگی', 'kesh-rangi', [
	'sku' => 'KS-1', 'short_description' => '<p>کش رنگی</p>', 'description' => '<p>کش</p>', 'image_id' => $imgs[4],
	'attributes' => [ $global_attr( 'pa_color', [ $red, $blue ], true ) ], 'meta' => [ '_stock_location_status' => 'china_stock' ],
] );
foreach ( [ [ 'red', '120000' ], [ 'blue', '150000' ] ] as [ $c, $price ] ) {
	$v = new WC_Product_Variation();
	$v->set_parent_id( $var ); $v->set_attributes( [ 'pa_color' => $c ] ); $v->set_regular_price( $price ); $v->set_status( 'publish' ); $v->save();
}
WC_Product_Variable::sync( $var );
// ناموجود، حراج و چند محصول دیگر برای فهرست‌ها، مرتبط‌ها و صفحه‌بندی
$out = $make( 'WC_Product_Simple', 'گلسر ناموجود', 'pin-out', [ 'regular_price' => '90000', 'sku' => 'PN-1', 'stock_status' => 'outofstock', 'image_id' => $imgs[5], 'short_description' => '<p>ناموجود</p>', 'meta' => [ '_stock_location_status' => 'out_of_stock' ] ] );
$sale = $make( 'WC_Product_Simple', 'تل مو حراج با نام خیلی بلند برای آزمودن دو خط شدن عنوان کارت', 'tel-sale', [ 'regular_price' => '200000', 'sale_price' => '150000', 'sku' => 'TL-1', 'image_id' => $imgs[6], 'tag_ids' => [ $tag ] ] );
$more = [];
for ( $i = 1; $i <= 8; $i++ ) {
	$more[] = $make( 'WC_Product_Simple', "محصول نمونه $i", "sample-$i", [ 'regular_price' => (string) ( 50000 + $i * 10000 ), 'sku' => "SM-$i", 'image_id' => $imgs[ $i % 8 ], 'date_created' => "2025-0" . ( 1 + $i % 8 ) . "-01 10:00:00" ] );
}
$main_p = wc_get_product( $main );
$main_p->set_upsell_ids( [ $sale, $more[0] ] ); $main_p->set_cross_sell_ids( [ $more[1] ] ); $main_p->save();

// نظرها با امتیاز
foreach ( [ [ 'مریم', 5, 'کیفیت عالی بود.' ], [ 'علی', 4, 'ارسال سریع، بسته‌بندی خوب.' ] ] as $i => [ $who, $rate, $text ] ) {
	$cid = wp_insert_comment( [ 'comment_post_ID' => $main, 'comment_author' => $who, 'comment_author_email' => "u$i@hodima.test", 'comment_content' => $text, 'comment_type' => 'review', 'comment_approved' => 1, 'comment_date' => "2025-03-0" . ( $i + 1 ) . ' 10:00:00' ] );
	update_comment_meta( $cid, 'rating', $rate ); update_comment_meta( $cid, 'verified', 0 );
}
WC_Comments::clear_transients( $main );

// تعداد در صفحه فروشگاه کم تا صفحه‌بندی دیده شود (تنظیمات قالب ← فروشگاه)
update_option( 'hodima_theme_settings', array_merge( (array) get_option( 'hodima_theme_settings', [] ), [ 'shop_per_page' => 8 ] ) );
if ( $o = getenv( 'HARNESS_OPTS' ) ) { eval( $o ); }
flush_rewrite_rules( true );
file_put_contents( dirname( $harness_wp ) . '/ids.json', json_encode( compact( 'home', 'main', 'var', 'out', 'sale', 'cat', 'sub', 'tag' ) ) );
echo "installed\n";
