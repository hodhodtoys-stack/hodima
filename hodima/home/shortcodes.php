<?php
/**
 * شورت‌کدهای صفحه اصلی: [latest-products] و یک شورت‌کد برای هر دسته
 * (3.5.2: [section03/07/09/10] و نام‌های قدیمی arian_* حذف شدند؛ بخش‌ها از «تنظیمات قالب ← صفحه اصلی» می‌آیند)
 * Path: hodima/home/shortcodes.php
 *
 * 3.1.5: logic.php (۶۷۰ خط، چند کار) به چند فایل با یک کار تقسیم شد؛ نام توابع و هوک‌ها
 * و خروجی صفحه همان است. فهرست فایل‌ها: home/logic.php.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

// ========================================================================
// ۲. ثبت تمامی شورت‌کدها در یک هوک یکپارچه
// ========================================================================
/**
 * فهرست دسته‌بندی‌های محصول برای ثبت شورت‌کد، با کش.
 *
 * نسخه قبلی get_terms() را مستقیما داخل هوک init صدا می‌زد. init روی
 * *هر* درخواست اجرا می‌شود — فرانت، پنل مدیریت، admin-ajax و کرون —
 * یعنی یک کوئری روی جدول ترم‌ها در هر بارگذاری صفحه، فقط برای اینکه
 * چند شورت‌کد ثبت شود که شاید در آن صفحه اصلا استفاده نشوند.
 *
 * هوک‌های create_term / edit_term / delete_term از قبل در همین فایل
 * وجود دارند و حالا این کش را هم باطل می‌کنند.
 */
/** @return array<string, string> نامک => نام دسته */
function hodima_home_category_shortcode_map(): array {

	$cached = get_transient( 'hodima_cat_shortcode_map' );

	if ( is_array( $cached ) ) {
		return $cached;
	}

	$map = [];

	if ( taxonomy_exists( 'product_cat' ) ) {

		// fields پیش‌فرض شیء کامل WP_Term برمی‌گرداند که هم slug دارد هم name،
		// پس نیازی به get_term() داخل حلقه نیست (آن یک N+1 بود).
		$categories = get_terms( [
			'taxonomy'               => 'product_cat',
			'hide_empty'             => true,
			'update_term_meta_cache' => false,
		] );

		if ( ! is_wp_error( $categories ) ) {
			foreach ( $categories as $term ) {
				if ( $term instanceof WP_Term ) {
					$map[ $term->slug ] = $term->name;
				}
			}
		}
	}

	set_transient( 'hodima_cat_shortcode_map', $map, DAY_IN_SECONDS );

	return $map;
}

function hodima_home_clear_category_shortcode_map(): void {
	delete_transient( 'hodima_cat_shortcode_map' );
	delete_transient( 'arian_cat_shortcode_map' ); // نام قدیمی تا 3.6.3
}
add_action( 'created_product_cat', 'hodima_home_clear_category_shortcode_map' );
add_action( 'edited_product_cat',  'hodima_home_clear_category_shortcode_map' );
add_action( 'delete_product_cat',  'hodima_home_clear_category_shortcode_map' );

// ========================================================================
// ۳. ثبت تمامی شورت‌کدها در یک هوک یکپارچه
// ========================================================================
add_action( 'init', static function (): void {

	// شورت‌کدها فقط جایی لازم‌اند که محتوا رندر می‌شود.
	// کرون هیچ‌وقت شورت‌کد پردازش نمی‌کند.
	if ( wp_doing_cron() ) {
		return;
	}

	// الف) شورت‌کد اختصاصی «جدیدترین محصولات»
	if ( function_exists('hodima_home_product_slider') ) {
		add_shortcode( 'latest-products', static function ( mixed $atts ): string {
			$atts = shortcode_atts( [ 'limit' => 12 ], $atts );
			return hodima_home_product_slider([
				'title'    => 'جدیدترین محصولات',
				'link'     => 'latest-products',
				'category' => '',
				'limit'    => $atts['limit'],
				'bg_color' => Hodima_Home_Tone::Third->css(),
			]);
		});
	}
	// ب) شورت‌کد هر دسته‌بندی (مثل [plasco]): دیگر هنگام init برای همه دسته‌ها ثبت نمی‌شود؛ فقط وقتی
	// متنی از آن استفاده کند (hodima_home_ensure_category_shortcodes پایین)
} );

// ========================================================================
// شورت‌کد هر دسته‌بندی (مثل [plasco])، تنبل
// ------------------------------------------------------------------------
// تا 3.1.7 هر درخواست (فرانت، پیشخوان، ajax) برای همه دسته‌ها یک شورت‌کد ثبت می‌کرد، حتی در
// صفحه‌ای که هیچ‌کدام در آن نیست. حالا فقط شورت‌کدهای به‌کاررفته در متن همان لحظه ثبت می‌شوند.
// ========================================================================

/**
 * رنگ زمینه شورت‌کد هر دسته: سه رنگ برند به نوبت، به ترتیب فهرست دسته‌ها (همان منطق قبلی)؛ دسته‌ای
 * که نامکش شورت‌کد دیگری است نوبت نمی‌گیرد. در هر درخواست یک‌بار محاسبه می‌شود.
 *
 * @return array<string, string> نامک ← مقدار CSS
 */
function hodima_home_category_shortcode_colors(): array {

	static $colors = null;

	if ( null !== $colors ) {
		return $colors;
	}

	$cycle  = [ Hodima_Home_Tone::Primary->css(), Hodima_Home_Tone::Secondary->css(), Hodima_Home_Tone::Third->css() ];
	$colors = [];
	$index  = 0;

	foreach ( array_keys( hodima_home_category_shortcode_map() ) as $slug ) {
		$slug = (string) $slug;
		if ( shortcode_exists( $slug ) ) {
			continue;
		}
		$colors[ $slug ] = $cycle[ $index % 3 ];
		++$index;
	}

	return $colors;
}

/**
 * شورت‌کد دسته‌هایی که در این متن آمده‌اند را ثبت کن (پیش از do_shortcode).
 */
function hodima_home_ensure_category_shortcodes( string $text ): void {

	if ( ! function_exists( 'hodima_home_product_slider' ) || ! str_contains( $text, '[' ) || ! preg_match_all( '/\[([^\s\]\/<>&=\[]+)/u', $text, $found ) ) {
		return;
	}

	$map  = hodima_home_category_shortcode_map();
	$tags = array_intersect( array_unique( $found[1] ), array_map( 'strval', array_keys( $map ) ) );

	if ( ! $tags ) {
		return;
	}

	$colors = hodima_home_category_shortcode_colors();

	foreach ( $tags as $slug ) {

		// شورت‌کدی که قبلا ثبت شده (از جمله همین‌جا) یا نامک بی‌رنگ (شورت‌کد دیگری است) را دوباره ثبت نکن
		if ( ! isset( $colors[ $slug ] ) || shortcode_exists( $slug ) ) {
			continue;
		}

		$name     = (string) $map[ $slug ];
		$bg_color = $colors[ $slug ];

		add_shortcode( $slug, static function ( mixed $atts ) use ( $slug, $name, $bg_color ): string {
			$atts = shortcode_atts( [
				'limit'    => 12,
				'title'    => $name,
				'bg_color' => $bg_color,
			], is_array( $atts ) ? $atts : [] );

			return hodima_home_product_slider( [
				'title'    => $atts['title'],
				'link'     => $slug,
				'category' => $slug,
				'limit'    => $atts['limit'],
				'bg_color' => $atts['bg_color'],
			] );
		} );
	}
}

// پیش از do_shortcode (اولویت ۱۱) و wpautop (۱۰) محتوا
add_filter( 'the_content', static function ( mixed $content ): mixed {

	if ( is_string( $content ) ) {
		hodima_home_ensure_category_shortcodes( $content );
	}

	return $content;
}, 9 );
