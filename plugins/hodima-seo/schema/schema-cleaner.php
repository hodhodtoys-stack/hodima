<?php
/**
 * پاک‌سازی اسکیما و داده‌های قدیمی
 * Path: plugins/hodima-seo/schema/schema-cleaner.php
 *
 * - حذف اسکیمای پیش‌فرض ووکامرس (جایگزین: product-schema-pro.php)
 * - حذف کلاس hentry
 * - پاک‌سازی یک‌باره داده‌های باقی‌مانده Rank Math
 *
 * «مهار Rank Math» (فیلتر rank_math/json_ld و گزینه hodima_cleaner_rm) و
 * «نگهبان سراسری تکرار اسکیما» (بافر <head> و حذف regex؛ گزینه
 * hodima_cleaner_dedupe_guard) حذف شدند: Rank Math از سایت پاک شده و جای
 * نگهبان را گراف واحد hodima-core گرفته که نودهای هم‌شناسه را *ادغام* می‌کند
 * (نگهبان FAQPage «#faq» دوم را کامل دور می‌انداخت و فوتر را نمی‌دید).
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/*
 * حذف اسکیمای پیش‌فرض ووکامرس — فقط وقتی جایگزین (اسکیمای محصول) واقعا فعال است.
 * باگ قبلی: فقط به سوییچ «hodima_cleaner_woo» وابسته بود؛ با خاموش بودن
 * اسکیمای محصول، صفحه محصول هیچ Product/Offer نداشت.
 */
if ( 'yes' === get_option( 'hodima_cleaner_woo', 'yes' ) ) {
	add_action( 'init', static function (): void {
		if (
			'1' === get_option( 'hodima_schema_product_enable', '1' )
			// @phpstan-ignore isset.property (پیش از راه‌افتادن ووکامرس هنوز ساخته نشده است)
			&& class_exists( 'WooCommerce' ) && function_exists( 'WC' ) && WC() && isset( WC()->structured_data )
		) {
			remove_action( 'wp_footer', [ WC()->structured_data, 'output_structured_data' ], 10 );
			remove_action( 'woocommerce_email_order_details', [ WC()->structured_data, 'output_email_structured_data' ], 30 );
		}
	}, 20 );
}

// حذف کلاس hentry
if ( 'yes' === get_option( 'hodima_cleaner_hentry', 'yes' ) ) {
	add_filter( 'post_class', static function ( array $classes ): array {
		$key = array_search( 'hentry', $classes, true );
		if ( false !== $key ) {
			unset( $classes[ $key ] );
		}
		return $classes;
	} );
}

/*
 * پاک‌سازی یک‌باره داده‌های باقی‌مانده Rank Math (اولین بازدید مدیر از پیشخوان
 * بعد از به‌روزرسانی): متای نوشته/دسته/کاربر (rank_math_*)، گزینه‌ها، جدول‌ها و
 * کارهای زمان‌بندی‌شده.
 *
 * پیش از حذف، «دسته اصلی» Rank Math (rank_math_primary_{taxonomy}) به
 * «_hodima_primary_{taxonomy}» منتقل می‌شود (breadcrumb-schema.php همین را
 * می‌خواند؛ مسیر راهنمای محصولات عوض نمی‌شود). اگر Rank Math هنوز نصب است،
 * کاری انجام نمی‌شود. نتیجه: گزینه hodima_seo_rankmath_cleanup (صفحه «اسکیما ← پاکسازی»).
 */
add_action( 'admin_init', 'hodima_seo_rankmath_cleanup' );

function hodima_seo_rankmath_cleanup(): void {

	if ( wp_doing_ajax() || wp_doing_cron() || false !== get_option( 'hodima_seo_rankmath_cleanup', false ) ) {
		return;
	}

	if ( defined( 'RANK_MATH_VERSION' ) || is_dir( WP_PLUGIN_DIR . '/seo-by-rank-math' ) ) {
		return; // هنوز نصب است؛ داده‌اش مال خودش است
	}

	// قفل ساده: دو درخواست هم‌زمان پیشخوان دو بار اجرا نکنند
	if ( ! add_option( 'hodima_seo_rankmath_cleanup_lock', time(), '', false ) ) {
		if ( time() - (int) get_option( 'hodima_seo_rankmath_cleanup_lock', 0 ) < 10 * MINUTE_IN_SECONDS ) {
			return;
		}
		update_option( 'hodima_seo_rankmath_cleanup_lock', time(), false );
	}

	global $wpdb;

	$like   = $wpdb->esc_like( 'rank_math_' ) . '%';
	$result = [ 'time' => time(), 'primary' => 0, 'meta' => 0, 'options' => 0, 'tables' => [], 'cron' => 0 ];

	// ۱. انتقال دسته اصلی (فقط اگر مقدار معتبر است و قبلا منتقل نشده)
	$primary_like = $wpdb->esc_like( 'rank_math_primary_' ) . '%';
	$rows         = $wpdb->get_results( $wpdb->prepare( "SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_key LIKE %s", $primary_like ) );

	foreach ( (array) $rows as $row ) {
		$taxonomy = substr( (string) $row->meta_key, strlen( 'rank_math_primary_' ) );
		$term_id  = (int) $row->meta_value;
		if ( '' !== $taxonomy && $term_id > 0 && '' === (string) get_post_meta( (int) $row->post_id, "_hodima_primary_{$taxonomy}", true ) ) {
			update_post_meta( (int) $row->post_id, "_hodima_primary_{$taxonomy}", $term_id );
			++$result['primary'];
		}
	}

	// ۲. متای نوشته، دسته و کاربر (نام جدول‌ها از خود $wpdb است)
	foreach ( [ $wpdb->postmeta, $wpdb->termmeta, $wpdb->usermeta ] as $table ) {
		$result['meta'] += (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE meta_key LIKE %s", $like ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	// ۳. گزینه‌ها و کش‌های موقت (rank_math_*، rank-math-*، _transient_rank_math_*)
	$result['options'] = (int) $wpdb->query( $wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		'%' . $wpdb->esc_like( 'rank_math' ) . '%',
		'%' . $wpdb->esc_like( 'rank-math' ) . '%'
	) );

	// ۴. جدول‌های اختصاصی ({prefix}rank_math_*: ریدایرکت‌ها، لاگ ۴۰۴، لینک‌های داخلی، آمار)
	$tables = (array) $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix . 'rank_math_' ) . '%' ) );
	foreach ( $tables as $table ) {
		if ( str_starts_with( (string) $table, $wpdb->prefix . 'rank_math_' ) && 1 === preg_match( '/^[A-Za-z0-9_]+$/', (string) $table ) ) {
			$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB.PreparedSQL -- نام جدول بالا اعتبارسنجی شد
			$result['tables'][] = (string) $table;
		}
	}

	// ۵. کارهای زمان‌بندی‌شده بی‌صاحب (rank_math/…، rank_math_…)
	foreach ( (array) _get_cron_array() as $events ) {
		foreach ( array_keys( (array) $events ) as $hook ) {
			if ( str_starts_with( (string) $hook, 'rank_math' ) ) {
				$result['cron'] += max( 0, (int) wp_unschedule_hook( (string) $hook ) );
			}
		}
	}

	// گزینه قدیمی خود هدیما (کلید «مهار Rank Math» در پنل پاکسازی)
	delete_option( 'hodima_cleaner_rm' );

	// noindex قدیمی Rank Math دیگر خوانده نمی‌شود؛ سایت‌مپ از نو ساخته شود
	if ( function_exists( 'hodima_sitemap_clear_cache' ) ) {
		hodima_sitemap_clear_cache();
	}

	update_option( 'hodima_seo_rankmath_cleanup', $result, false );
	delete_option( 'hodima_seo_rankmath_cleanup_lock' );
}
