<?php
/**
 * SeoBox — shared core (loaded on admin and front-end)
 * Path: core/seobox/core-variables.php
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * نوع‌های پستی که سئوباکس دارند.
 * نسخه قبلی این فهرست را در چهار جای مختلف هاردکد داشت و ناهماهنگ بود:
 * متاباکس «video» را داشت ولی فیلتر و ستون فهرست نه.
 */
function seobox_post_types(): array {
	return (array) apply_filters( 'seobox_post_types', [ 'post', 'page', 'product', 'video' ] );
}

function seobox_taxonomies(): array {
	return (array) apply_filters( 'seobox_taxonomies', [ 'category', 'post_tag', 'product_cat' ] );
}

/**
 * متغیرهای قابل استفاده در عنوان و توضیحات.
 */
function seobox_parse_variables( string $text, int $object_id, string $object_type = 'post' ): string {

	if ( ! str_contains( $text, '%' ) ) {
		return $text;
	}

	$title = '';

	if ( 'term' === $object_type ) {
		$term = get_term( $object_id );
		if ( $term instanceof WP_Term ) {
			$title = $term->name;
		}
	} else {
		$post = get_post( $object_id );
		if ( $post instanceof WP_Post ) {
			$title = $post->post_title;
		}
	}

	/*
	 * %currentyear% و %currentmonth% از wp_date() می‌آیند که در این سایت از
	 * مبدل تقویم شمسی عبور می‌کند — برای عنوان فارسی همین مطلوب است.
	 * %gyear% سال میلادی است و عمدا با DateTime بومی ساخته می‌شود تا از
	 * آن مبدل عبور نکند.
	 */
	$replacements = [
		'%title%'        => $title,
		'%sitename%'     => (string) get_bloginfo( 'name' ),
		'%sitedesc%'     => (string) get_bloginfo( 'description' ),
		'%sep%'          => (string) apply_filters( 'document_title_separator', '-' ),
		'%currentyear%'  => (string) wp_date( 'Y' ),
		'%currentmonth%' => (string) wp_date( 'F' ),
		'%gyear%'        => ( new DateTimeImmutable( 'now', wp_timezone() ) )->format( 'Y' ),
	];

	return str_replace( array_keys( $replacements ), array_values( $replacements ), $text );
}

/**
 * دستورات ربات ذخیره‌شده، همیشه به شکل آرایه.
 *
 * نسخه قبلی مقدار را مستقیم به in_array() می‌داد. اگر ردیف قدیمی به صورت
 * رشته ذخیره شده بود ("noindex,follow")، با declare(strict_types=1)
 * خطای کشنده TypeError رخ می‌داد و متاباکس سئو باز نمی‌شد.
 */
function seobox_normalize_robots( $raw ): array {

	if ( is_string( $raw ) ) {
		$raw = preg_split( '/[\s,]+/', strtolower( $raw ), -1, PREG_SPLIT_NO_EMPTY );
	}

	$raw = array_map( 'strval', (array) $raw );

	return [
		'index'  => ! in_array( 'noindex', $raw, true ),
		'follow' => ! in_array( 'nofollow', $raw, true ),
	];
}

/* =====================================================================
 * ذخیره مشترک پست و ترم
 * ===================================================================== */

/**
 * @param string $type 'post' یا 'term'
 */
function seobox_save_fields( int $object_id, string $type ): void {

	$update = ( 'term' === $type ) ? 'update_term_meta' : 'update_post_meta';
	$delete = ( 'term' === $type ) ? 'delete_term_meta' : 'delete_post_meta';

	$post = static function ( string $key ): ?string {
		return isset( $_POST[ $key ] ) ? (string) wp_unslash( $_POST[ $key ] ) : null;
	};

	/*
	 * هر فیلد اعتبارسنجی و اگر خالی یا برابر پیش‌فرض بود *حذف* می‌شود.
	 *
	 * نسخه قبلی برای هر پست و ترمی که ذخیره می‌شد هشت ردیف متا
	 * می‌نوشت — حتی بدون هیچ تنظیم سئو — از جمله _seobox_robots =
	 * ['index','follow'] و _seobox_adv_snippet = '-1'. روی یک فروشگاه با
	 * هزاران محصول یعنی ده‌ها هزار ردیف بی‌مصرف.
	 */
	$values = [];

	$raw = $post( 'seobox_title' );
	if ( null !== $raw ) {
		$values['title'] = sanitize_text_field( $raw );
	}

	$raw = $post( 'seobox_description' );
	if ( null !== $raw ) {
		// توضیحات متا یک خط است؛ خط جدید در اسنیپت نمایش داده نمی‌شود
		$values['description'] = sanitize_text_field( $raw );
	}

	$raw = $post( 'seobox_canonical' );
	if ( null !== $raw ) {
		// نسخه قبلی sanitize_text_field می‌زد که آدرس را اعتبارسنجی نمی‌کند
		$url = esc_url_raw( trim( $raw ), [ 'http', 'https' ] );
		$values['canonical'] = ( '' !== $url && filter_var( $url, FILTER_VALIDATE_URL ) ) ? $url : '';
	}

	/*
	 * این مقادیر مستقیما در متاتگ robots قرار می‌گیرند. نسخه قبلی هیچ
	 * اعتبارسنجی نداشت؛ مقدار «5, noindex» در فیلد اسنیپت یک دستور
	 * noindex پنهان در خروجی می‌ساخت.
	 */
	$raw = $post( 'seobox_adv_snippet' );
	if ( null !== $raw ) {
		$n = is_numeric( $raw ) ? (int) $raw : -1;
		$values['adv_snippet'] = ( $n >= 0 ) ? (string) $n : '';
	}

	$raw = $post( 'seobox_adv_video' );
	if ( null !== $raw ) {
		$n = is_numeric( $raw ) ? (int) $raw : -1;
		$values['adv_video'] = ( $n >= 0 ) ? (string) $n : '';
	}

	$raw = $post( 'seobox_adv_image' );
	if ( null !== $raw ) {
		$raw = sanitize_key( $raw );
		// «large» پیش‌فرض است؛ فقط انحراف از پیش‌فرض ذخیره می‌شود
		$values['adv_image'] = in_array( $raw, [ 'none', 'standard' ], true ) ? $raw : '';
	}

	foreach ( $values as $field => $value ) {
		'' === $value
			? $delete( $object_id, '_seobox_' . $field )
			: $update( $object_id, '_seobox_' . $field, $value );
	}

	// ربات‌ها: فقط وقتی با پیش‌فرض (index, follow) فرق دارد
	if ( isset( $_POST['seobox_robot_index'], $_POST['seobox_robot_follow'] ) ) {

		$index  = 'noindex' !== sanitize_key( wp_unslash( (string) $_POST['seobox_robot_index'] ) );
		$follow = 'nofollow' !== sanitize_key( wp_unslash( (string) $_POST['seobox_robot_follow'] ) );

		if ( $index && $follow ) {
			$delete( $object_id, '_seobox_robots' );
		} else {
			$update( $object_id, '_seobox_robots', [ $index ? 'index' : 'noindex', $follow ? 'follow' : 'nofollow' ] );
		}
	}

	// فیلد «کلمه کلیدی کانونی» در رابط وجود ندارد و ذخیره نمی‌شود؛
	// مقدار قدیمی (اگر باشد) دست‌نخورده می‌ماند.
}
