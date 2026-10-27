<?php
/**
 * داده آزمایشی «لینک‌های این کلمه» (advisor-tests.php و advisor-links-seed.php): یک گروه «برنده روشن» (دسته lk-cat با
 * رقیب /207/ که نامک عددی دارد و داخل همان دسته است)، نوشته‌های دارای لینک درست/اشتباه/خراب، لینک مرتبط دستی (فقط
 * شناسه)، منو، توضیح دسته، و لینک‌های خزش (متنی که پایگاه داده نمی‌بیند + لینک ساختاری فوتر). فقط در ابزار تست.
 */

declare(strict_types=1);

/**
 * @return array<string, mixed> شناسه‌های ساخته‌شده (برای پاک‌کردن با hodima_lk_fixture_clean)
 */
function hodima_lk_fixture(): array {

	global $wpdb;

	$ids = [];
	$cat = wp_insert_term( 'گیره انبری فلزی', 'category', [ 'slug' => 'lk-cat' ] );

	$ids['cat']  = is_wp_error( $cat ) ? 0 : (int) $cat['term_id'];
	$mk          = static fn( string $slug, string $title, string $content ): int => (int) wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => $slug, 'post_content' => $content ] );
	// آدرس عددی مثل /207/ در سایت واقعی هست (درون‌ریزی محصول)؛ ثبت عادی وردپرس/روتر نامک عددی را «-2» می‌کند، پس نامک مستقیم در پایگاه داده گذاشته می‌شود
	$numeric     = static function ( string $slug, string $title ) use ( $wpdb ): int {
		$id = (int) wp_insert_post( [ 'post_type' => 'post', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => 'tmp-' . $slug, 'post_content' => '<p>متن</p>' ] );
		$wpdb->update( $wpdb->posts, [ 'post_name' => $slug ], [ 'ID' => $id ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		clean_post_cache( $id );
		return $id;
	};
	$ids['rival'] = $numeric( '207', 'گیره مو انبری فلزی' );
	$ids['decoy'] = $numeric( '20700', 'صفحه نامربوط' );
	wp_set_post_terms( $ids['rival'], [ $ids['cat'] ], 'category' ); // رقیب داخل دسته برنده است

	$ids['a']    = $mk( 'lk-src-a', 'راهنمای خرید گیره', '<p><a href="' . home_url( '/207/' ) . '">گیره انبری فلزی</a> و <a href="/lk-cat/">گیره انبری فلزی</a> و <a href="/20700/">گیره انبری فلزی</a> و <a href="https://other.example/207/">گیره انبری فلزی</a> و <a href="mailto:a@b.c">گیره انبری فلزی</a></p>' );
	$ids['b']    = $mk( 'lk-src-b', 'مقاله انواع گیره', '<p><a href="/207">گیره‌های انبری فلزی</a> و <a href="/category/lk-cat/">گیره انبری فلزی</a></p>' );
	$ids['c']    = $mk( 'lk-src-c', 'مقاله قدیمی', '<p><a href="/gone-clip-old/">گیره انبری فلزی</a></p>' );
	$ids['d']    = $mk( 'lk-src-d', 'متن بدون لینک', '<p>یک گیره انبری فلزی مشکی را ببینید و بخرید</p>' );
	$ids['rel']  = $mk( 'lk-src-rel', 'محصول با لینک مرتبط', '<p>متن</p>' );
	update_post_meta( $ids['rel'], '_hodima_rl_groups', [ 'products' => [ [ 'kind' => 'post', 'id' => $ids['rival'], 'url' => '', 'title' => 'گیره انبری فلزی', 'img_id' => 0 ] ], 'article' => [] ] );
	$desc        = wp_insert_term( 'دسته لینک', 'category', [ 'slug' => 'lk-desc-cat', 'description' => '<a href="/207/">گیره انبری فلزی</a>' ] );
	$ids['desc'] = is_wp_error( $desc ) ? 0 : (int) $desc['term_id'];
	$ids['menu'] = (int) wp_create_nav_menu( 'lk-menu' );
	$ids['item1'] = (int) wp_update_nav_menu_item( $ids['menu'], 0, [ 'menu-item-title' => 'گیره انبری فلزی', 'menu-item-url' => home_url( '/207/' ), 'menu-item-type' => 'custom', 'menu-item-status' => 'publish' ] );
	$ids['item2'] = (int) wp_update_nav_menu_item( $ids['menu'], 0, [ 'menu-item-title' => 'گیره انبری فلزی', 'menu-item-type' => 'post_type', 'menu-item-object' => 'post', 'menu-item-object-id' => $ids['rival'], 'menu-item-status' => 'publish' ] );

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

	// لینک‌های خزش: یکی تکراری متن نوشته (نباید دوبار شمرده شود)، یکی متنی که پایگاه داده ندارد (شورت‌کد/قالب)، و لینک ساختاری فوتر در ۳ صفحه
	hodima_seo_advisor_ensure_tables();
	$table = hodima_seo_advisor_table( 'crawl_links' );
	$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	foreach ( [
		[ '/lk-src-a/', '/207/', 'content', 'گیره انبری فلزی' ],
		[ '/c-x/', '/207/', 'content', 'گیره انبری فلزی' ],
		[ '/p1/', '/207/', 'footer', 'گیره انبری فلزی' ],
		[ '/p2/', '/207/', 'footer', 'گیره انبری فلزی' ],
		[ '/p3/', '/207/', 'footer', 'گیره انبری فلزی' ],
		[ '/p1/', '/lk-cat/', 'navigation', 'گیره انبری' ],
	] as [ $from, $to, $position, $anchor ] ) {
		$wpdb->insert( $table, [ 'source_path' => $from, 'dest_path' => $to, 'link_position' => $position, 'anchor' => $anchor, 'is_follow' => 1 ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	delete_option( HODIMA_SEO_ADVISOR_IGNORED_OPTION );

	return $ids;
}

/** @param array<string, mixed> $ids خروجی hodima_lk_fixture() */
function hodima_lk_fixture_clean( array $ids ): void {

	global $wpdb;

	foreach ( [ 'rival', 'decoy', 'a', 'b', 'c', 'd', 'rel', 'item1', 'item2' ] as $key ) {
		wp_delete_post( (int) ( $ids[ $key ] ?? 0 ), true );
	}
	wp_delete_nav_menu( (int) ( $ids['menu'] ?? 0 ) );
	! empty( $ids['cat'] ) && wp_delete_term( (int) $ids['cat'], 'category' );
	! empty( $ids['desc'] ) && wp_delete_term( (int) $ids['desc'], 'category' );
	$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', hodima_seo_advisor_table( 'crawl_links' ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	hodima_seo_advisor_store( [], [], [] );
}
