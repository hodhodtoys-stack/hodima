<?php
/**
 * ماژول «دستار سئو» — تحلیل دوباره یک صفحه همان لحظه ذخیره
 * Path: core/advisor/advisor-resave.php
 *
 * تا SEO 2.8.7 فهرست «فرصت‌های بهتر شدن» نتیجه ذخیره‌شده دریافت روزانه را نشان می‌داد: مدیر کلمه را
 * به تیتر اضافه می‌کرد، کادر ویرایش (تحلیل زنده) دیگر ایرادی نمی‌دید ولی همان ایراد تا اجرای شبانه در
 * فهرست می‌ماند. حالا با ذخیره نوشته/برگه/محصول یا دسته، ردیف(های) همان شیء در جدول با متن تازه دوباره
 * تحلیل و ذخیره می‌شوند (hodima_seo_advisor_save_advice؛ همان منطق تحلیل کامل، منطق دوم نه).
 *
 * فقط ایرادهایی که به متن صفحه بسته‌اند (کلمه در تیتر/متن نیست) فورا می‌روند؛ ایرادهای آماری (نرخ کلیک،
 * رتبه، افت) با داده گوگل بعدی عوض می‌شوند. بیرون از is_admin لود می‌شود: ویرایشگر بلوکی با REST ذخیره می‌کند.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

// wp_after_insert_post: بعد از متاهای REST و کادرهای کلاسیک (سئوباکس روی save_post ۱۰)
add_action( 'wp_after_insert_post', static function ( int $post_id, WP_Post $post ): void {
	if ( ! wp_is_post_revision( $post ) && ! wp_is_post_autosave( $post ) ) {
		hodima_seo_advisor_resave_object( $post->post_type, $post_id );
	}
}, 99, 2 );

// saved_term بعد از edited_{taxonomy} اجرا می‌شود که سئوباکس دسته در آن ذخیره می‌شود (edited_term پیش از آن است)
add_action( 'saved_term', static function ( int $term_id, int $tt_id, string $taxonomy ): void {
	hodima_seo_advisor_resave_object( $taxonomy, $term_id );
}, 99, 3 );

/**
 * همه آدرس‌های ذخیره‌شده یک شیء را دوباره تحلیل می‌کند (معمولا یکی؛ آدرس قدیمی و تازه بعد از تغییر نامک دو تا).
 *
 * @return int شمار ردیف‌های تحلیل‌شده
 */
function hodima_seo_advisor_resave_object( string $kind, int $id ): int {

	global $wpdb;

	// بدون دریافت داده جدولی هم شاید نباشد؛ پرسش بی‌جا نزن
	if ( $id <= 0 || '' === $kind || hodima_seo_advisor_status()['fetched'] <= 0 ) {
		return 0;
	}

	$paths = (array) $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare( 'SELECT path FROM %i WHERE kind = %s AND object_id = %d', hodima_seo_advisor_table( 'pages' ), $kind, $id )
	);

	foreach ( $paths as $path ) {
		hodima_seo_advisor_save_advice( (string) $path );
	}

	return count( $paths );
}
