<?php
/**
 * کش صفحه اصلی: نسل اسلایدرها و پاک‌سازی هنگام تغییر محصول، دسته و مقاله
 * Path: hodima/home/cache.php
 *
 * 3.1.5: logic.php (۶۷۰ خط، چند کار) به چند فایل با یک کار تقسیم شد؛ نام توابع و هوک‌ها
 * و خروجی صفحه همان است. فهرست فایل‌ها: home/logic.php.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

// ========================================================================
// ۴. پاکسازی خودکار و هوشمند کشِ اسلایدرها هنگام تغییر محصولات
// ========================================================================
add_action( 'save_post_product', 'hodima_home_flush_product_sliders', 10, 1 );
add_action( 'woocommerce_product_deleted', 'hodima_home_flush_product_sliders' );
add_action( 'woocommerce_product_set_stock_status', 'hodima_home_flush_product_sliders' );

/**
 * نسل کش اسلایدرها (گزینه hodima_home_slider_gen).
 *
 * باگ قبلی: پاک کردن فقط با DELETE روی جدول options بود. با کش شیء پایدار
 * (Redis/Memcached، مثلا کش شیء لایت‌اسپید) ترنزینت‌ها اصلا در آن جدول
 * نیستند، پس بعد از ویرایش/ناموجود شدن محصول تا ۱۰ دقیقه محصول و قیمت
 * قدیمی در صفحه اصلی می‌ماند. حالا کلید هر کش نسل را دارد و پاک کردن یعنی
 * بالا بردن نسل — در هر دو حالت فورا کش تازه ساخته می‌شود.
 */
function hodima_home_slider_generation(): int {
	return (int) get_option( 'hodima_home_slider_gen', 0 );
}

/**
 * کش HTML صفحه اصلی را پاک کن (افزونه‌های کش معروف، و فقط صفحه اصلی لایت‌اسپید).
 *
 * فقط صفحه اصلی، نه کل سایت: قبلا purge_all بود و ذخیره هر نوشته یا دسته کش همه صفحه‌ها
 * را خالی می‌کرد. تا 3.1.4 این کد در هر سه پاک‌کننده تکرار شده بود.
 */
function hodima_home_purge_page_cache(): void {

	if ( function_exists( 'rocket_clean_home' ) ) {
		rocket_clean_home();
	}

	if ( function_exists( 'hodima_litespeed_purge_home' ) ) {
		hodima_litespeed_purge_home();
	}
}

if ( ! function_exists( 'hodima_home_flush_product_sliders' ) ) {
	/**
	 * کش اسلایدرها را باطل کن.
	 *
	 * 3.1.3: در یک درخواست (درون‌ریزی گروهی صدها محصول) نسل فقط یک‌بار بالا می‌رود و
	 * پاکسازی ردیف‌های قدیمی (DELETE LIKE) و کش صفحه اصلی لایت‌اسپید فقط یک‌بار در
	 * پایان درخواست (shutdown) انجام می‌شود. قبلا هر ذخیره یک update_option و یک
	 * DELETE جدا می‌زد و با تغییر فقط وضعیت موجودی، کش HTML صفحه اصلی پاک نمی‌شد.
	 */
	function hodima_home_flush_product_sliders(): void {

		// جلوگیری از اجرای بی‌دلیل هنگام ذخیره خودکار (Autosave) وردپرس
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		static $scheduled = false;

		if ( $scheduled ) {
			return;
		}

		$scheduled = true;

		update_option( 'hodima_home_slider_gen', hodima_home_slider_generation() + 1, true );
		add_action( 'shutdown', 'hodima_home_flush_product_sliders_late' );
	}

	/** پایان درخواست: نسل دوباره (تغییرات بعد از اولین فراخوانی)، پاکسازی ردیف‌های قدیمی و کش صفحه اصلی. */
	function hodima_home_flush_product_sliders_late(): void {

		update_option( 'hodima_home_slider_gen', hodima_home_slider_generation() + 1, true );

		// بدون کش شیء: ردیف‌های نسل قبلی را هم از جدول پاک کن (وگرنه تا انقضا می‌مانند)؛ پیشوند arian_ نام قدیمی تا 3.6.3 است
		if ( ! wp_using_ext_object_cache() ) {
			global $wpdb;
			$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_hodima_pslider_%' OR option_name LIKE '_transient_timeout_hodima_pslider_%' OR option_name LIKE '_transient_arian_pslider_%' OR option_name LIKE '_transient_timeout_arian_pslider_%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- پاکسازی گروهی ترنزینت‌ها؛ API وردپرس برای الگو ندارد
		}

		hodima_home_purge_page_cache();
	}
}

// ========================================================================
// کش دسته‌بندی‌های صفحه اصلی
// ========================================================================

/** ترنزینت دسته‌ها را پاک کن. */
function hodima_home_clear_categories_cache(): void {
	// v6: همه دسته‌ها با نامک و تعداد؛ فیلتر و ترتیب هنگام نمایش (home/parts/categories.php). v5 و v4 نسخه‌های قبلی
	delete_transient( 'hodima_home_categories_v6' );
	delete_transient( 'hodima_home_categories_v5' );
	delete_transient( 'arian_categories_hyper_v4' );

	hodima_home_purge_page_cache();
}

// ۱. هوک‌های عمومی تغییرات دسته‌بندی ووکامرس
function hodima_home_categories_cache_on_term( mixed $term_id, mixed $tt_id, mixed $taxonomy ): void {
	if ( 'product_cat' === $taxonomy ) {
		hodima_home_clear_categories_cache();
	}
}
add_action( 'create_term', 'hodima_home_categories_cache_on_term', 10, 3 );
add_action( 'edit_term', 'hodima_home_categories_cache_on_term', 10, 3 );
add_action( 'delete_term', 'hodima_home_categories_cache_on_term', 10, 3 );

// ۲. هوک‌های تغییر متادیتاهای دسته‌بندی (زمانی که فقط تصویر شاخص عوض می‌شود)
function hodima_home_categories_cache_on_meta( mixed $meta_id, mixed $object_id, mixed $meta_key ): void {
	// وقتی تصویر شاخص دسته‌بندی تغییر می‌کند
	if ( 'thumbnail_id' === $meta_key ) {
		hodima_home_clear_categories_cache();
	}
}
add_action( 'added_term_meta', 'hodima_home_categories_cache_on_meta', 10, 3 );
add_action( 'updated_term_meta', 'hodima_home_categories_cache_on_meta', 10, 3 );
add_action( 'deleted_term_meta', 'hodima_home_categories_cache_on_meta', 10, 3 );

// ========================================================================
// کش آخرین مقالات صفحه اصلی
// ========================================================================

/** ترنزینت مقاله‌ها را پاک کن. */
function hodima_home_clear_blog_cache(): void {
	// v3: ۲۰ مقاله آخر؛ تعداد هر بخش هنگام نمایش (home/parts/blog.php). v2 نسخه قبلی
	delete_transient( 'hodima_home_blog_posts_v3' );
	delete_transient( 'arian_latest_blog_posts_hyper_v2' );

	hodima_home_purge_page_cache();
}

// ۱. هوک‌های عمومی تغییرات پست‌ها (انتشار، ویرایش، حذف و انتقال به زباله‌دان)
function hodima_home_blog_cache_on_post( mixed $post_id, mixed $post = null ): void {
	// جلوگیری از اجرای کد هنگام ذخیره خودکار (Autosave) و ریویژن‌ها
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
	if ( wp_is_post_revision( $post_id ) ) return;

	/*
	 * نوع پست. باگ قبلی: «$post ? $post->post_type» — ولی trashed_post (از وردپرس
	 * 6.3) به‌جای شیء نوشته وضعیت قبلی (رشته) را می‌فرستد؛ هر انتقال نوشته به
	 * زباله‌دان هشدار PHP «Attempt to read property on string» می‌داد.
	 */
	$post_type = $post instanceof WP_Post ? $post->post_type : get_post_type( (int) $post_id );

	// فقط اگر تغییرات روی نوشته‌ها (post) بود کش را خالی کن
	if ( 'post' === $post_type ) {
		hodima_home_clear_blog_cache();
	}
}
add_action( 'save_post', 'hodima_home_blog_cache_on_post', 10, 2 );
add_action( 'deleted_post', 'hodima_home_blog_cache_on_post', 10, 2 );
add_action( 'trashed_post', 'hodima_home_blog_cache_on_post', 10, 2 );

// ۲. هوک‌های تغییر متادیتاهای پست (زمانی که فقط تصویر شاخص عوض می‌شود)
function hodima_home_blog_cache_on_meta( mixed $meta_id, mixed $post_id, mixed $meta_key ): void {
	// تصویر شاخص پست‌ها با کلید _thumbnail_id ذخیره می‌شود (دقت کنید آندرلاین دارد)
	if ( '_thumbnail_id' === $meta_key && get_post_type( $post_id ) === 'post' ) {
		hodima_home_clear_blog_cache();
	}
}
add_action( 'added_post_meta', 'hodima_home_blog_cache_on_meta', 10, 3 );
add_action( 'updated_post_meta', 'hodima_home_blog_cache_on_meta', 10, 3 );
add_action( 'deleted_post_meta', 'hodima_home_blog_cache_on_meta', 10, 3 );
