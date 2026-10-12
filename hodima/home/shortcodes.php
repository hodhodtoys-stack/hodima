<?php
/**
 * شورت‌کدهای قدیمی صفحه اصلی: [section03/07/09/10]، [latest-products] و یک شورت‌کد برای هر دسته
 * Path: hodima/home/shortcodes.php
 *
 * 3.1.5: logic.php (۶۷۰ خط، چند کار) به چند فایل با یک کار تقسیم شد؛ نام توابع و هوک‌ها
 * و خروجی صفحه همان است. فهرست فایل‌ها: home/logic.php.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

// ========================================================================
// ۲. تابع لود سکشن‌های فیزیکی (برای section01 تا section20)
// ========================================================================
if ( ! function_exists( 'hodima_home_load_section' ) ) {
	function hodima_home_load_section( string $tag ): string {
		$tag = sanitize_key( $tag );
		if ( empty( $tag ) ) return '';

		$php_file = get_stylesheet_directory() . "/home/{$tag}.php";

		if ( ! file_exists( $php_file ) ) {
			return "<!-- خطای هدیما: فایل {$tag}.php در مسیر {$php_file} پیدا نشد -->";
		}

		ob_start();
		include $php_file;
		return trim( (string) ob_get_clean() ); // strict_types: ob_get_clean ممکن است false باشد
	}
}

// ========================================================================
// ۳. ثبت تمامی شورت‌کدها در یک هوک یکپارچه
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

	$cached = get_transient( 'arian_cat_shortcode_map' );

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

	set_transient( 'arian_cat_shortcode_map', $map, DAY_IN_SECONDS );

	return $map;
}

function hodima_home_clear_category_shortcode_map(): void {
	delete_transient( 'arian_cat_shortcode_map' );
}
add_action( 'created_product_cat', 'hodima_home_clear_category_shortcode_map' );
add_action( 'edited_product_cat',  'hodima_home_clear_category_shortcode_map' );
add_action( 'delete_product_cat',  'hodima_home_clear_category_shortcode_map' );

// ========================================================================
// ۳. ثبت تمامی شورت‌کدها در یک هوک یکپارچه
// ========================================================================
add_action('init', function() {

	// شورت‌کدها فقط جایی لازم‌اند که محتوا رندر می‌شود.
	// کرون هیچ‌وقت شورت‌کد پردازش نمی‌کند.
	if ( wp_doing_cron() ) {
		return;
	}

	// الف) شورت‌کد برای فایل‌های بخش موجود.
	// نسخه قبلی کورکورانه section01 تا section20 را ثبت می‌کرد در حالی که
	// فقط چهار فایل وجود دارد؛ ۱۶ شورت‌کد مرده که خروجی‌شان یک کامنت خطا بود.
	// (array) false = [false] و basename(false) با strict_types خطا می‌داد
	foreach ( glob( get_stylesheet_directory() . '/home/section*.php' ) ?: [] as $section_file ) {

		$tag = basename( $section_file, '.php' );

		if ( ! preg_match( '/^section\d{2}$/', $tag ) ) {
			continue;
		}

		add_shortcode( $tag, function( $atts, $content, $shortcode_tag ) {
			return hodima_home_load_section( $shortcode_tag );
		} );
	}

	// ب) شورت‌کد اختصاصی «جدیدترین محصولات»
	if ( function_exists('hodima_home_product_slider') ) {
		add_shortcode( 'latest-products', function($atts) {
			$atts = shortcode_atts(['limit' => 12], $atts);
			return hodima_home_product_slider([
				'title'    => 'جدیدترین محصولات',
				'link'     => 'latest-products',
				'category' => '',
				'limit'    => $atts['limit'],
				'bg_color' => Hodima_Home_Tone::Third->css(),
			]);
		});
	}

	// ج) شورت‌کد خودکار برای دسته‌بندی‌های ووکامرس (مثل [plasco])
	if ( function_exists('hodima_home_product_slider') ) {

		// سه رنگ برند به نوبت (از پالت: با عوض شدن پالت همراه می‌شود)
		$brand_colors = [
			Hodima_Home_Tone::Primary->css(),
			Hodima_Home_Tone::Secondary->css(),
			Hodima_Home_Tone::Third->css(),
		];

		$color_index = 0;

		foreach ( hodima_home_category_shortcode_map() as $slug => $name ) {

			// شورت‌کدهای ثبت‌شده بالاتر را بازنویسی نکن
			if ( shortcode_exists( $slug ) ) {
				continue;
			}

			$bg_color = $brand_colors[ $color_index % 3 ];
			$color_index++;

			add_shortcode( $slug, function($atts) use ( $slug, $name, $bg_color ) {
				$atts = shortcode_atts([
					'limit'    => 12,
					'title'    => $name,
					'bg_color' => $bg_color
				], $atts);

				return hodima_home_product_slider([
					'title'    => $atts['title'],
					'link'     => $slug,
					'category' => $slug,
					'limit'    => $atts['limit'],
					'bg_color' => $atts['bg_color'],
				]);
			});
		}
	}
});
