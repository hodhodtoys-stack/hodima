<?php
/**
 * Hodima Live Search
 * Path: components/search/search.php
 * Version: 3.0.0
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * ۳.۰ به جای ۲.۵: تغییر نوع ستون‌ها (VARCHAR(255) → TEXT) و قالب محتوای
 * قابل‌جستجو، هر دو نیازمند بازسازی کامل ایندکس‌اند.
 */
define( 'HODIMA_SEARCH_DB_VERSION', '3.0' );
define( 'HODIMA_SEARCH_ASSET_VERSION', '3.0.0' );

function hodima_search_table(): string {
	global $wpdb;
	return $wpdb->prefix . 'hodima_search_index';
}

/* ==================================================================
 * ۱. استانداردسازی متن فارسی
 * ================================================================== */

/**
 * متن را برای ایندکس و جستجو یکسان‌سازی می‌کند.
 *
 * تغییرات نسبت به نسخه قبلی:
 *   - اعداد فارسی و عربی به لاتین تبدیل می‌شوند. کاربر «۲۱۷» تایپ
 *     می‌کند ولی SKU «217» ذخیره شده؛ نسخه قبلی هیچ نتیجه‌ای نمی‌داد —
 *     برای فروشگاه عمده که مشتری با کد محصول جستجو می‌کند، مهم است.
 *   - کشیده (ـ) حذف می‌شود؛ «کـتاب» و «کتاب» یکی‌اند.
 *   - «ۀ» و «ئ» یکسان‌سازی شدند.
 */
function hodima_normalize_persian_text( $string ): string {

	$string = (string) $string;

	if ( '' === $string ) {
		return '';
	}

	$string = wp_strip_all_tags( $string );

	$string = strtr( $string, [
		'ي' => 'ی', 'ى' => 'ی', 'ئ' => 'ی', 'ك' => 'ک',
		'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
		'ؤ' => 'و', 'ة' => 'ه', 'ۀ' => 'ه',
		"\u{200C}" => ' ', "\u{200D}" => '', 'ـ' => '',
		'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
		'۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
		'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
		'٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
	] );

	$string = (string) preg_replace( '/[^\p{L}\p{N}\s]/u', ' ', $string );
	$string = (string) preg_replace( '/\s+/u', ' ', $string );

	return mb_strtolower( trim( $string ) );
}

/**
 * ترکیب کلمات مجاور بدون فاصله: «گل سر مو» → «گلسر سرمو».
 *
 * نسخه قبلی *کل* محتوا را بدون فاصله به یک رشته تبدیل می‌کرد. FULLTEXT
 * آن را یک توکن غول‌آسا می‌دید؛ جستجوی «گلسر*» فقط وقتی پیدا می‌شد که
 * محتوا دقیقا با آن شروع شود، و اگر طول آن از innodb_ft_max_token_size
 * (۸۴ کاراکتر) بیشتر بود اصلا ایندکس نمی‌شد. حالا هر جفت مجاور یک توکن
 * جدا و قابل‌جستجوست.
 */
function hodima_search_joined_pairs( string $normalized ): string {

	$words = array_values( array_filter( explode( ' ', $normalized ), 'strlen' ) );
	$pairs = [];

	for ( $i = 0, $n = count( $words ) - 1; $i < $n; $i++ ) {
		$pairs[] = $words[ $i ] . $words[ $i + 1 ];
	}

	return implode( ' ', array_unique( $pairs ) );
}

/* ==================================================================
 * ۲. دیتابیس
 * ================================================================== */

function hodima_setup_search_database(): void {

	if ( version_compare( (string) get_option( 'hodima_search_db_version', '0' ), HODIMA_SEARCH_DB_VERSION, '>=' ) ) {
		return;
	}

	global $wpdb;
	$table   = hodima_search_table();
	$collate = $wpdb->get_charset_collate();

	/*
	 * permalink و image_url از VARCHAR(255) به TEXT تغییر کردند.
	 * آدرس یک نامک فارسی کدگذاری‌شده است و هر حرف فارسی شش کاراکتر
	 * (%D8%A7) می‌شود؛ نامک ۴۷ حرفی = آدرس ۲۶۴ کاراکتری. با strict mode
	 * (پیش‌فرض MySQL 5.7+) کل INSERT رد می‌شد و محصول *اصلا ایندکس نمی‌شد*؛
	 * بدون آن آدرس بریده و لینک نتیجه ۴۰۴ می‌شد.
	 */
	$sql = "CREATE TABLE {$table} (
		product_id BIGINT(20) UNSIGNED NOT NULL,
		title VARCHAR(255) NOT NULL,
		sku VARCHAR(191) NOT NULL DEFAULT '',
		permalink TEXT NOT NULL,
		image_url TEXT NOT NULL,
		price_html VARCHAR(255) NOT NULL DEFAULT '',
		searchable_content MEDIUMTEXT NOT NULL,
		in_stock TINYINT(1) NOT NULL DEFAULT 1,
		PRIMARY KEY  (product_id),
		KEY in_stock_idx (in_stock),
		KEY sku_idx (sku),
		FULLTEXT KEY title_idx (title),
		FULLTEXT KEY searchable_idx (searchable_content)
	) {$collate};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );

	update_option( 'hodima_search_db_version', HODIMA_SEARCH_DB_VERSION, true );

	// زمان‌بندی قدیمی «monthly» هرگز ثبت نشده بود (بخش ۶ را ببینید)
	wp_clear_scheduled_hook( 'hodima_monthly_search_cleanup' );

	hodima_search_schedule_full_reindex();
}
add_action( 'admin_init', 'hodima_setup_search_database' );

/* ==================================================================
 * ۳. ایندکس‌گذاری
 * ================================================================== */

/**
 * بازسازی کامل در دسته‌های ۵۰ تایی.
 * نسخه قبلی برای هر محصول یک کار جدا در Action Scheduler می‌ساخت؛ برای
 * ۵۰۰۰ محصول ۵۰۰۰ ردیف در جدول کارها.
 */
function hodima_search_schedule_full_reindex(): void {

	if ( ! function_exists( 'wc_get_products' ) ) {
		return;
	}

	$ids = wc_get_products( [ 'status' => 'publish', 'limit' => -1, 'return' => 'ids' ] );

	foreach ( array_chunk( array_map( 'intval', (array) $ids ), 50 ) as $chunk ) {
		hodima_search_enqueue( 'hodima_index_product_batch_job', [ 'ids' => $chunk ] );
	}
}

/**
 * صف کار پس‌زمینه با Action Scheduler ووکامرس؛ اگر در دسترس نبود،
 * WP-Cron. نسخه قبلی بدون Action Scheduler بی‌صدا هیچ کاری نمی‌کرد.
 */
function hodima_search_enqueue( string $hook, array $args ): void {

	if ( function_exists( 'as_enqueue_async_action' ) ) {
		// کار تکراری برای همان محصول ساخته نمی‌شود (ذخیره‌های پشت سر هم)
		if ( function_exists( 'as_has_scheduled_action' ) && as_has_scheduled_action( $hook, $args, 'hodima_search' ) ) {
			return;
		}
		as_enqueue_async_action( $hook, $args, 'hodima_search' );
		return;
	}

	if ( ! wp_next_scheduled( $hook, [ $args ] ) ) {
		wp_schedule_single_event( time() + 5, $hook, [ $args ] );
	}
}

add_action( 'hodima_index_product_batch_job', static function ( $ids ): void {
	foreach ( (array) ( $ids['ids'] ?? $ids ) as $id ) {
		hodima_process_product_index( (int) $id, false );
	}
	// یک بار برای کل دسته، نه ۵۰ بار
	hodima_search_bump_cache();
} );

// نام قدیمی حفظ شد: کارهایی که قبلا در صف بوده‌اند هنوز اجرا می‌شوند
add_action( 'hodima_index_single_product_job', static function ( $product_id ): void {
	hodima_process_product_index( (int) ( is_array( $product_id ) ? ( $product_id['product_id'] ?? 0 ) : $product_id ) );
}, 10, 1 );

// کار قدیمی بازسازی کامل
add_action( 'hodima_trigger_full_background_indexing', 'hodima_search_schedule_full_reindex' );

function hodima_process_product_index( int $product_id, bool $bump = true ): void {

	global $wpdb;

	if ( $product_id <= 0 || ! function_exists( 'wc_get_product' ) ) {
		return;
	}

	$table   = hodima_search_table();
	$product = wc_get_product( $product_id );

	if ( ! $product
		|| 'publish' !== $product->get_status()
		|| in_array( $product->get_catalog_visibility(), [ 'hidden', 'catalog' ], true )
	) {
		$wpdb->delete( $table, [ 'product_id' => $product_id ], [ '%d' ] );
		if ( $bump ) {
			hodima_search_bump_cache();
		}
		return;
	}

	$skus = [];
	if ( $product->get_sku() ) {
		$skus[] = (string) $product->get_sku();
	}
	if ( $product->is_type( 'variable' ) ) {
		foreach ( $product->get_children() as $variation_id ) {
			$variation = wc_get_product( $variation_id );
			if ( $variation && $variation->get_sku() ) {
				$skus[] = (string) $variation->get_sku();
			}
		}
	}

	$terms = [];
	foreach ( [ 'product_cat', 'product_tag' ] as $taxonomy ) {
		$list = get_the_terms( $product_id, $taxonomy );
		if ( is_array( $list ) ) {
			foreach ( $list as $term ) {
				$terms[] = $term->name;
			}
		}
	}

	$title_norm = hodima_normalize_persian_text( $product->get_name() );
	$terms_norm = hodima_normalize_persian_text( implode( ' ', $terms ) );

	$searchable = implode( ' ', array_filter( [
		$title_norm,
		hodima_normalize_persian_text( implode( ' ', $skus ) ),
		$terms_norm,
		hodima_normalize_persian_text( $product->get_short_description() ),
		hodima_normalize_persian_text( strip_shortcodes( $product->get_description() ) ),
		// جفت‌های بدون فاصله فقط برای بخش‌های کوتاه و مهم
		hodima_search_joined_pairs( $title_norm ),
		hodima_search_joined_pairs( $terms_norm ),
	] ) );

	$image_id  = (int) $product->get_image_id();
	$image_url = $image_id ? (string) wp_get_attachment_image_url( $image_id, 'thumbnail' ) : '';
	if ( '' === $image_url && function_exists( 'wc_placeholder_img_src' ) ) {
		$image_url = (string) wc_placeholder_img_src( 'thumbnail' );
	}

	$price_html = (string) $product->get_price_html();
	if ( '' !== $price_html ) {
		$price_html = str_replace( [ '&nbsp;', "\u{00A0}" ], ' ', $price_html );
		$price_html = trim( html_entity_decode( wp_strip_all_tags( $price_html ), ENT_QUOTES, 'UTF-8' ) );
		$price_html = mb_substr( (string) preg_replace( '/\s+/u', ' ', $price_html ), 0, 250 );
	}

	$wpdb->replace(
		$table,
		[
			'product_id'         => $product_id,
			'title'              => mb_substr( (string) $product->get_name(), 0, 250 ),
			'sku'                => mb_substr( hodima_normalize_persian_text( implode( ' ', $skus ) ), 0, 190 ),
			'permalink'          => (string) $product->get_permalink(),
			'image_url'          => $image_url,
			'price_html'         => $price_html,
			'searchable_content' => $searchable,
			'in_stock'           => $product->is_in_stock() ? 1 : 0,
		],
		[ '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%d' ]
	);

	if ( $bump ) {
		hodima_search_bump_cache();
	}
}

/* ── رویدادهایی که ایندکس را تغییر می‌دهند ────────────────────────── */

add_action( 'woocommerce_new_product', static fn( $id ) => hodima_search_enqueue( 'hodima_index_single_product_job', [ 'product_id' => (int) $id ] ) );
add_action( 'woocommerce_update_product', static fn( $id ) => hodima_search_enqueue( 'hodima_index_single_product_job', [ 'product_id' => (int) $id ] ) );

/*
 * بازگرداندن از زباله‌دان و تغییر وضعیت انتشار.
 * این مسیرها $product->save() را صدا نمی‌زنند و woocommerce_update_product
 * اجرا نمی‌شود؛ نسخه قبلی محصولی را که از زباله‌دان برمی‌گشت هرگز دوباره
 * ایندکس نمی‌کرد.
 */
add_action( 'transition_post_status', static function ( string $new, string $old, WP_Post $post ): void {
	if ( 'product' === $post->post_type && $new !== $old && ( 'publish' === $new || 'publish' === $old ) ) {
		hodima_search_enqueue( 'hodima_index_single_product_job', [ 'product_id' => (int) $post->ID ] );
	}
}, 10, 3 );

/*
 * وضعیت موجودی.
 * برای واریاسیون، ردیف ایندکس مال محصول والد است؛ نسخه قبلی با شناسه
 * واریاسیون به‌روزرسانی می‌کرد که هیچ ردیفی پیدا نمی‌کرد.
 */
add_action( 'woocommerce_product_set_stock_status', static function ( $product_id, $stock_status ): void {

	global $wpdb;

	$product_id = (int) $product_id;
	if ( 'product_variation' === get_post_type( $product_id ) ) {
		$product_id = (int) wp_get_post_parent_id( $product_id );
	}

	if ( $product_id > 0 ) {
		// برای والد متغیر، وضعیت کلی از خود محصول خوانده می‌شود
		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
		$in      = $product ? ( $product->is_in_stock() ? 1 : 0 ) : ( 'instock' === $stock_status ? 1 : 0 );

		$wpdb->update( hodima_search_table(), [ 'in_stock' => $in ], [ 'product_id' => $product_id ], [ '%d' ], [ '%d' ] );
		hodima_search_bump_cache();
	}
}, 10, 2 );

function hodima_remove_product_from_index( $post_id ): void {
	if ( 'product' === get_post_type( (int) $post_id ) ) {
		global $wpdb;
		$wpdb->delete( hodima_search_table(), [ 'product_id' => (int) $post_id ], [ '%d' ] );
		hodima_search_bump_cache();
	}
}
add_action( 'wp_trash_post', 'hodima_remove_product_from_index' );
add_action( 'before_delete_post', 'hodima_remove_product_from_index' );

/*
 * تغییر نام دسته یا برچسب در محتوای قابل‌جستجوی همه محصولات آن ترم
 * اثر دارد.
 */
add_action( 'edited_term', static function ( $term_id, $tt_id = 0, $taxonomy = '' ): void {
	if ( in_array( $taxonomy, [ 'product_cat', 'product_tag' ], true ) ) {
		$ids = get_objects_in_term( (int) $term_id, $taxonomy );
		if ( is_array( $ids ) ) {
			foreach ( array_chunk( array_map( 'intval', array_slice( $ids, 0, 2000 ) ), 50 ) as $chunk ) {
				hodima_search_enqueue( 'hodima_index_product_batch_job', [ 'ids' => $chunk ] );
			}
		}
	}
}, 10, 3 );

/* ==================================================================
 * ۴. کش نتایج
 * ================================================================== */

/**
 * شماره نسل کش.
 *
 * نسخه قبلی نتیجه هر عبارت را ۲۴ ساعت کش می‌کرد و با به‌روزرسانی ایندکس
 * هیچ‌کدام باطل نمی‌شد. قیمت تغییرکرده، محصول ناموجودشده، یا عنوان
 * ویرایش‌شده تا یک روز در جستجوی زنده همان‌طور قدیمی نمایش داده می‌شد —
 * برای فروشگاه عمده یعنی قیمت اشتباه جلوی مشتری. حالا هر نوشتن در ایندکس
 * شماره را بالا می‌برد و همه کلیدهای قبلی منقضی می‌شوند.
 */
function hodima_search_cache_gen(): int {
	return (int) get_option( 'hodima_search_cache_gen', 1 );
}

function hodima_search_bump_cache(): void {
	update_option( 'hodima_search_cache_gen', hodima_search_cache_gen() + 1, true );
}

/* ==================================================================
 * ۵. موتور جستجو
 * ================================================================== */

/**
 * شناسه‌ها و داده‌های محصولات منطبق، مرتب بر اساس ارتباط.
 * هم جستجوی زنده و هم صفحه «مشاهده همه نتایج» از همین استفاده می‌کنند.
 *
 * @return array<int, object>
 */
function hodima_search_run( string $raw_term, int $limit ): array {

	global $wpdb;

	$term = mb_substr( hodima_normalize_persian_text( $raw_term ), 0, 60 );

	if ( mb_strlen( $term ) < 2 ) {
		return [];
	}

	$table   = hodima_search_table();
	$words   = array_values( array_filter( explode( ' ', $term ), 'strlen' ) );
	$long    = array_values( array_filter( $words, static fn( $w ) => mb_strlen( $w ) >= 3 ) );
	$short   = array_values( array_filter( $words, static fn( $w ) => mb_strlen( $w ) < 3 ) );
	$exact   = '%' . $wpdb->esc_like( $term ) . '%';
	$joined  = '%' . $wpdb->esc_like( str_replace( ' ', '', $term ) ) . '%';

	$stock_sql = apply_filters( 'hodima_search_include_outofstock', false ) ? '1=1' : 'in_stock = 1';

	$fields = 'product_id, title, permalink, image_url, price_html';

	/*
	 * کلمات کوتاه‌تر از ۳ حرف در ایندکس FULLTEXT اینودی‌بی ذخیره نمی‌شوند
	 * (innodb_ft_min_token_size = 3). نسخه قبلی آن‌ها را هم با «+» الزامی
	 * می‌کرد؛ رفتار MySQL با عبارت الزامیِ خارج از ایندکس به نسخه و
	 * پیکربندی سرور بستگی دارد و قابل اتکا نیست. حالا کلمات بلند با
	 * FULLTEXT و کلمات کوتاه با LIKE شرط می‌شوند — در هر پیکربندی یکسان.
	 */
	if ( empty( $long ) ) {

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT {$fields} FROM {$table}
			 WHERE {$stock_sql} AND ( title LIKE %s OR searchable_content LIKE %s OR searchable_content LIKE %s OR sku = %s )
			 ORDER BY ( sku = %s ) DESC, ( title LIKE %s ) DESC, CHAR_LENGTH( title ) ASC
			 LIMIT %d",
			$exact, $exact, $joined, $term, $term, $exact, $limit
		) );

		return is_array( $rows ) ? $rows : [];
	}

	$boolean = implode( ' ', array_map( static fn( $w ) => '+' . $w . '*', $long ) );

	$short_sql  = '';
	$short_args = [];
	foreach ( $short as $w ) {
		$short_sql   .= ' AND searchable_content LIKE %s';
		$short_args[] = '%' . $wpdb->esc_like( $w ) . '%';
	}

	$args = array_merge(
		[ $term, $term, $term, $exact, $boolean ],
		$short_args,
		[ $limit ]
	);

	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT {$fields},
		        ( MATCH(title) AGAINST(%s) * 15
		        + MATCH(searchable_content) AGAINST(%s)
		        + ( sku = %s ) * 100
		        + ( title LIKE %s ) * 20 ) AS score
		 FROM {$table}
		 WHERE {$stock_sql}
		   AND MATCH(searchable_content) AGAINST(%s IN BOOLEAN MODE)
		   {$short_sql}
		 ORDER BY score DESC
		 LIMIT %d",
		...$args
	) );

	return is_array( $rows ) ? $rows : [];
}

/* ── AJAX ─────────────────────────────────────────────────────────── */

function hodima_woo_live_search(): void {

	$raw = isset( $_GET['term'] ) ? (string) wp_unslash( $_GET['term'] ) : '';

	if ( mb_strlen( hodima_normalize_persian_text( $raw ) ) < 2 ) {
		wp_send_json( [] );
	}

	// محدودیت نرخ: ۲۰ جستجو در دقیقه برای هر بازدیدکننده
	if ( function_exists( 'hodima_rate_limit_key' ) ) {
		$rate_key = hodima_rate_limit_key( 'hodima_srch_rl_' );
		$hits     = (int) get_transient( $rate_key );

		if ( $hits >= (int) apply_filters( 'hodima_search_rate_limit', 20 ) ) {
			status_header( 429 );
			wp_send_json( [] );
		}
		set_transient( $rate_key, $hits + 1, MINUTE_IN_SECONDS );
	}

	$term  = mb_substr( hodima_normalize_persian_text( $raw ), 0, 60 );
	$limit = max( 1, min( 20, (int) apply_filters( 'hodima_search_result_limit', 8 ) ) );
	$key   = 'hodima_s3_' . hodima_search_cache_gen() . '_' . md5( $term . '|' . $limit );

	$results = get_transient( $key );

	if ( ! is_array( $results ) ) {

		$results = [];

		foreach ( hodima_search_run( $term, $limit ) as $row ) {
			$results[] = [
				'title' => (string) $row->title,
				'link'  => esc_url_raw( (string) $row->permalink ),
				'image' => esc_url_raw( (string) $row->image_url ),
				'price' => (string) $row->price_html,
			];
		}

		// کوتاه‌تر از قبل (۲۴ ساعت)؛ با شماره نسل هم خودکار باطل می‌شود
		set_transient( $key, $results, 6 * HOUR_IN_SECONDS );
	}

	wp_send_json( $results );
}
add_action( 'wp_ajax_woo_live_search', 'hodima_woo_live_search' );
add_action( 'wp_ajax_nopriv_woo_live_search', 'hodima_woo_live_search' );

/* ── صفحه «مشاهده همه نتایج» ─────────────────────────────────────── */

/*
 * نسخه قبلی لینک «مشاهده همه» را به جستجوی پیش‌فرض وردپرس می‌فرستاد —
 * یک موتور کاملا متفاوت (LIKE روی عنوان و متن، بدون یکسان‌سازی فارسی،
 * بدون SKU). کاربر در منوی کشویی ۸ نتیجه می‌دید و در صفحه کامل نتایج
 * دیگری، یا هیچ. حالا صفحه کامل هم از همین ایندکس و همین ترتیب ارتباط
 * استفاده می‌کند.
 */
add_filter( 'posts_search', static function ( string $search, WP_Query $query ): string {

	if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() ) {
		return $search;
	}

	$post_type = $query->get( 'post_type' );
	if ( 'product' !== $post_type && ! ( is_array( $post_type ) && [ 'product' ] === array_values( $post_type ) ) ) {
		return $search;
	}

	if ( version_compare( (string) get_option( 'hodima_search_db_version', '0' ), HODIMA_SEARCH_DB_VERSION, '<' ) ) {
		return $search; // ایندکس هنوز ساخته نشده؛ جستجوی پیش‌فرض
	}

	global $wpdb;

	$ids = array_map( static fn( $row ) => (int) $row->product_id, hodima_search_run( (string) $query->get( 's' ), 500 ) );

	$query->set( 'hodima_search_ids', $ids );

	return empty( $ids )
		? ' AND 1=0 '
		: " AND {$wpdb->posts}.ID IN (" . implode( ',', $ids ) . ') ';
}, 20, 2 );

add_filter( 'posts_orderby', static function ( string $orderby, WP_Query $query ): string {

	$ids = $query->get( 'hodima_search_ids' );

	// فقط وقتی کاربر مرتب‌سازی دیگری انتخاب نکرده
	if ( empty( $ids ) || ! empty( $_GET['orderby'] ) ) {
		return $orderby;
	}

	global $wpdb;
	return "FIELD({$wpdb->posts}.ID, " . implode( ',', array_map( 'intval', $ids ) ) . ')';
}, 20, 2 );

/* ==================================================================
 * ۶. پاکسازی
 * ------------------------------------------------------------------
 * رویداد «hodima_monthly_search_cleanup» حذف شد. زمان‌بندی «monthly» در
 * وردپرس وجود ندارد و در قالب هم ثبت نشده بود؛ wp_schedule_event شکست
 * می‌خورد و این کد سطح‌بالا روی *هر* درخواست دوباره تلاش می‌کرد. خود
 * وردپرس ترنزینت‌های منقضی را روزانه پاک می‌کند (delete_expired_transients)،
 * و با شماره نسل، کلیدهای قدیمی هرگز خوانده نمی‌شوند.
 * ================================================================== */

/* ==================================================================
 * ۷. دارایی‌ها و شورت‌کد
 * ================================================================== */

function hodima_woo_live_search_assets(): void {

	$base = get_template_directory_uri() . '/components/search/';

	wp_enqueue_style( 'hodima-woo-live-search', $base . 'search.css', [], HODIMA_SEARCH_ASSET_VERSION );
	wp_enqueue_script( 'hodima-woo-live-search', $base . 'search.js', [], HODIMA_SEARCH_ASSET_VERSION, true );

	wp_localize_script( 'hodima-woo-live-search', 'wooLiveSearch', [
		'ajaxurl' => admin_url( 'admin-ajax.php' ),
		'allUrl'  => home_url( '/?s={term}&post_type=product' ),
	] );
}
// جعبه جستجو در header.php روی همه صفحات است، پس دارایی‌ها سراسری‌اند
add_action( 'wp_enqueue_scripts', 'hodima_woo_live_search_assets' );

function hodima_woo_live_search_shortcode(): string {

	static $instance = 0;
	$instance++;
	$uid = 'woo-search-results-' . $instance;

	ob_start();
	?>
	<form class="woo-live-search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
		<span class="woo-live-search__icon" aria-hidden="true">
			<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
				<circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line>
			</svg>
		</span>
		<input type="search" name="s" class="woo-live-search__input" placeholder="جستجو"
			autocomplete="off" spellcheck="false" enterkeyhint="search"
			role="combobox" aria-label="جستجوی محصولات" aria-autocomplete="list"
			aria-expanded="false" aria-controls="<?php echo esc_attr( $uid ); ?>">
		<input type="hidden" name="post_type" value="product">
		<button type="button" class="woo-live-search__clear" aria-label="پاک کردن">✕</button>
		<span class="woo-live-search__spinner" aria-hidden="true"></span>
		<div class="woo-search-results" id="<?php echo esc_attr( $uid ); ?>" role="listbox" aria-label="نتایج جستجو"></div>
	</form>
	<?php
	return (string) ob_get_clean();
}
add_shortcode( 'woo_live_search', 'hodima_woo_live_search_shortcode' );
