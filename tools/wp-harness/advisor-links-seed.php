<?php
/**
 * داده آزمایشی «لینک‌های این کلمه» برای advisor-links-check.mjs (مرورگر واقعی): یک گروه «برنده روشن» (دسته
 * lk-cat با رقیب /207/ که نامک عددی دارد)، نوشته‌های دارای لینک درست/اشتباه/خراب، منو و توضیح دسته.
 *   HARNESS=1 php tools/wp-harness/advisor-links-seed.php
 */

declare(strict_types=1);

$_SERVER['HTTP_HOST'] = $_SERVER['SERVER_NAME'] = 'hodima.test';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['HTTPS']       = 'on';
require rtrim( (string) ( getenv( 'HODIMA_WP' ) ?: '/tmp/hodima-harness/wp' ), '/' ) . '/wp-load.php';

if ( ! getenv( 'HARNESS' ) ) {
	fwrite( STDERR, "این اسکریپت داده می‌سازد؛ فقط روی ابزار تست: HARNESS=1\n" );
	exit( 2 );
}

if ( ! username_exists( 'advadmin' ) ) {
	wp_insert_user( [ 'user_login' => 'advadmin', 'user_pass' => 'adv-pass-123', 'role' => 'administrator' ] );
}
wp_set_current_user( 1 );

$lk_cat = wp_insert_term( 'گیره انبری فلزی', 'category', [ 'slug' => 'lk-cat' ] );
$lk_cat_id = is_wp_error( $lk_cat ) ? 0 : (int) $lk_cat['term_id'];
$lk_mk = static fn( string $slug, string $title, string $content ): int => (int) wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => $slug, 'post_content' => $content ] );
// آدرس عددی مثل /207/ در سایت واقعی هست (درون‌ریزی محصول)؛ ثبت عادی وردپرس/روتر نامک عددی را «-2» می‌کند، پس نامک مستقیم در پایگاه داده گذاشته می‌شود
$lk_numeric = static function ( string $slug, string $title ): int {
	global $wpdb;
	$id = (int) wp_insert_post( [ 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => 'tmp-' . $slug, 'post_content' => '<p>متن</p>' ] );
	$wpdb->update( $wpdb->posts, [ 'post_name' => $slug ], [ 'ID' => $id ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	clean_post_cache( $id );
	return $id;
};
$lk_rival = $lk_numeric( '207', 'گیره مو انبری فلزی' );
$lk_decoy = $lk_numeric( '20700', 'صفحه نامربوط' );
$lk_a = $lk_mk( 'lk-src-a', 'راهنمای خرید گیره', '<p><a href="' . home_url( '/207/' ) . '">گیره انبری فلزی</a> و <a href="/lk-cat/">گیره انبری فلزی</a> و <a href="/20700/">گیره انبری فلزی</a> و <a href="https://other.example/207/">گیره انبری فلزی</a> و <a href="mailto:a@b.c">گیره انبری فلزی</a></p>' );
$lk_b = $lk_mk( 'lk-src-b', 'مقاله انواع گیره', '<p><a href="/207">گیره‌های انبری فلزی</a> و <a href="/category/lk-cat/">گیره انبری فلزی</a></p>' );
$lk_c = $lk_mk( 'lk-src-c', 'مقاله قدیمی', '<p><a href="/gone-clip-old/">گیره انبری فلزی</a></p>' );
$lk_d = $lk_mk( 'lk-src-d', 'متن بدون لینک', '<p>بهترین گیره انبری فلزی را ببینید</p>' );
$lk_desc = wp_insert_term( 'دسته لینک', 'category', [ 'slug' => 'lk-desc-cat', 'description' => '<a href="/207/">گیره انبری فلزی</a>' ] );
$lk_menu = (int) wp_create_nav_menu( 'lk-menu' );
$lk_item1 = (int) wp_update_nav_menu_item( $lk_menu, 0, [ 'menu-item-title' => 'گیره انبری فلزی', 'menu-item-url' => home_url( '/207/' ), 'menu-item-type' => 'custom', 'menu-item-status' => 'publish' ] );
$lk_item2 = (int) wp_update_nav_menu_item( $lk_menu, 0, [ 'menu-item-title' => 'گیره انبری فلزی', 'menu-item-type' => 'post_type', 'menu-item-object' => 'post', 'menu-item-object-id' => $lk_rival, 'menu-item-status' => 'publish' ] );
hodima_seo_advisor_store(
	[
		'/lk-cat/' => [ 'clicks' => 80, 'impressions' => 4000, 'position' => 5.0 ],
		'/207/'    => [ 'clicks' => 5, 'impressions' => 1000, 'position' => 7.6 ],
		'/c/m/'    => [ 'clicks' => 30, 'impressions' => 2000, 'position' => 7.0 ],
		'/c/n/'    => [ 'clicks' => 28, 'impressions' => 2000, 'position' => 7.5 ],
		'/c/p/'    => [ 'clicks' => 20, 'impressions' => 1500, 'position' => 8.0 ],
	],
	[],
	[
		'/lk-cat/' => [ 'گیره انبری فلزی' => [ 'clicks' => 80, 'impressions' => 4000, 'position' => 5.0 ] ],
		'/207/'    => [ 'گیره انبری فلزی' => [ 'clicks' => 5, 'impressions' => 1000, 'position' => 7.6 ] ],
		'/c/m/'    => [ 'گیره هم‌وزن' => [ 'clicks' => 30, 'impressions' => 2000, 'position' => 7.0 ] ],
		'/c/n/'    => [ 'گیره هم‌وزن' => [ 'clicks' => 28, 'impressions' => 2000, 'position' => 7.5 ] ],
		'/c/p/'    => [ 'گیره هم‌وزن' => [ 'clicks' => 20, 'impressions' => 1500, 'position' => 8.0 ] ],
	]
);

update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [ 'fetched' => time() - 3600, 'property' => 'sc-domain:hodima.test', 'days' => 90, 'pages' => 7, 'queries' => 6, 'advised' => 0, 'error' => '' ] + hodima_seo_advisor_ranges( 90 ), false );
update_option( 'hodima_discover_sc_key', '{}', false );
delete_option( HODIMA_SEO_ADVISOR_IGNORED_OPTION );
echo "ok\n";
