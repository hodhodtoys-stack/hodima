<?php
/**
 * کارت و باکس مشترک بخش‌های افقی صفحه اصلی و اولویت بارگذاری تصویر
 * Path: hodima/home/cards.php
 *
 * 3.1.5: logic.php (۶۷۰ خط، چند کار) به چند فایل با یک کار تقسیم شد؛ نام توابع و هوک‌ها
 * و خروجی صفحه همان است. فهرست فایل‌ها: home/logic.php.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

// ========================================================================
// ۰. اولویت بارگذاری تصویر بالای صفحه (LCP)
// ========================================================================
if ( ! function_exists( 'hodima_home_claim_priority' ) ) {
	/**
	 * فقط «اولین» تصویر صفحه اصلی fetchpriority=high بگیرد.
	 * باگ قبلی: اولین تصویر هر اسلایدر و مقالات این را می‌گرفت؛ چند تصویر (حتی زیر
	 * صفحه) هم‌زمان «فوری» می‌شد و اولین تصویر مهم (LCP) کند می‌شد. هر بخش پیش از چاپ
	 * تصویر اولش این تابع را صدا می‌زند؛ فقط اولین فراخوانی در هر درخواست true می‌گیرد.
	 */
	function hodima_home_claim_priority(): bool {
		static $claimed = false;

		if ( $claimed ) {
			return false;
		}

		$claimed = true;

		return true;
	}
}

if ( ! function_exists( 'hodima_home_categories_eager_count' ) ) {
	/**
	 * چند تصویر اول بخش «دسته‌بندی کالاها» که بدون lazy چاپ شوند (فقط اولین بخش این نوع در صفحه).
	 *
	 * این بخش معمولا بالای صفحه اصلی است (دو ردیف در موبایل). باگ قبلی: همه تصویرها lazy بودند و
	 * لایت‌اسپید هم تنبلشان می‌کرد؛ در PageSpeed کادرها تا حدود ۴ ثانیه خالی می‌ماندند (Speed Index
	 * صفحه اصلی موبایل ۶٫۹ ثانیه). تصویرهای برگشتی data-no-lazy می‌گیرند (لایت‌اسپید دست نمی‌زند).
	 * بخش دوم به بعد ← ۰. فیلتر hodima_home_categories_eager (پیش‌فرض ۱۰؛ ۰ = همه lazy مثل قبل).
	 */
	function hodima_home_categories_eager_count(): int {
		static $used = false;

		if ( $used ) {
			return 0;
		}

		$used = true;

		return max( 0, (int) apply_filters( 'hodima_home_categories_eager', 10 ) );
	}
}

// ========================================================================
// کارت و باکس مشترک بخش‌های افقی صفحه اصلی (محصولات یک دسته، جدیدترین، آخرین مقالات)
// ------------------------------------------------------------------------
// تا 3.1.4 مقالات مارک‌آپ و CSS جدای خودشان را داشتند (modern-blog-card، blog-wrapper…)
// و کوچک‌تر از کارت محصول درمی‌آمدند. حالا هر دو همین دو تابع و همین CSS (.hodima-home-card،
// .hodima-product-section) را به کار می‌برند؛ تفاوت مقاله فقط نبود قیمت است.
// ========================================================================
if ( ! function_exists( 'hodima_home_card_html' ) ) {
	/**
	 * یک کارت: تصویر مربع (برش مرکزی)، عنوان دو خطی و (اختیاری) قیمت.
	 *
	 * @param array{link:string, title:string, thumbnail_id?:int, price_html?:string, first?:bool, class?:string, size?:string, placeholder?:string} $card
	 *   first = اولین کارت بخش (می‌تواند اولویت بارگذاری تصویر بالای صفحه را بگیرد)،
	 *   class = کلاس اضافه روی article، placeholder = آدرس تصویر جایگزین (خالی ← آیکون برند).
	 */
	function hodima_home_card_html( array $card ): string {

		$card = wp_parse_args( $card, [
			'thumbnail_id' => 0,
			'price_html'   => '',
			'first'        => false,
			'class'        => '',
			'size'         => 'medium_large',
			'placeholder'  => '',
		] );

		$title = (string) $card['title'];
		$link  = (string) $card['link'];

		if ( ! empty( $card['thumbnail_id'] ) ) {
			$image_attrs = [
				'alt'      => $title,
				'decoding' => 'async',
				'class'    => 'hodima-home-card-img',
				// کارت ۱۷۰px (در موبایل ۴۰٪ عرض)؛ بدون این، مرورگر نسخه‌ای به اندازه تمام صفحه می‌گرفت
				'sizes'    => '(max-width: 768px) 40vw, 170px',
			];
			if ( $card['first'] && hodima_home_claim_priority() ) {
				$image_attrs['fetchpriority'] = 'high';
			} else {
				$image_attrs['loading'] = 'lazy';
			}
			$image = wp_get_attachment_image( (int) $card['thumbnail_id'], (string) $card['size'], false, $image_attrs );
		} elseif ( '' !== $card['placeholder'] ) {
			$image = '<img src="' . esc_url( (string) $card['placeholder'] ) . '" alt="' . esc_attr( $title ) . '" width="300" height="300" loading="lazy" decoding="async" class="hodima-home-card-img">';
		} else {
			// بدون تصویر: کادر جایگزین با رنگ برند (فایل جایگزین در قالب نیست)
			$image = '<span class="hodima-home-card-img hodima-home-card-img--placeholder" role="img" aria-label="' . esc_attr( $title ) . '"><svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h4"/></svg></span>';
		}

		$classes = trim( 'hodima-home-card ' . (string) $card['class'] );
		$price   = '' !== (string) $card['price_html'] ? '<div class="hodima-home-card-price">' . wp_kses_post( (string) $card['price_html'] ) . '</div>' : '';

		return '<article class="' . esc_attr( $classes ) . '">'
			. '<a href="' . esc_url( $link ) . '" class="hodima-home-card-img-link">' . $image . '</a>'
			. '<div class="hodima-home-card-info"><h3 class="hodima-home-card-name"><a href="' . esc_url( $link ) . '">' . esc_html( $title ) . '</a></h3>' . $price . '</div>'
			. '</article>';
	}
}

if ( ! function_exists( 'hodima_home_cards_section' ) ) {
	/**
	 * باکس بخش: سرتیتر (با خط گرادیان و لینک «مشاهده همه»)، و اسکرولر افقی کارت‌ها.
	 *
	 * @param array{id:string, title:string, subtitle?:string, title_url?:string, more_url?:string, more_text?:string, bg_color?:string, scroller_class?:string} $args
	 * @param string $cards_html خروجی hodima_home_card_html() (هر کدام escape شده)
	 */
	function hodima_home_cards_section( array $args, string $cards_html ): string {

		$args = wp_parse_args( $args, [
			'subtitle'       => '',
			'title_url'      => '',
			'more_url'       => '',
			'more_text'      => 'مشاهده همه',
			'bg_color'       => 'transparent',
			'scroller_class' => '',
		] );

		$title = esc_html( (string) $args['title'] );
		if ( '' !== (string) $args['title_url'] ) {
			$title = '<a class="hodima-section-title-link" href="' . esc_url( (string) $args['title_url'] ) . '">' . $title . '</a>';
		}

		$subtitle = '' !== (string) $args['subtitle'] ? '<p class="hodima-section-subtitle">' . esc_html( (string) $args['subtitle'] ) . '</p>' : '';

		$more = '' !== (string) $args['more_url'] && '' !== (string) $args['more_text']
			? '<a href="' . esc_url( (string) $args['more_url'] ) . '" class="hodima-view-all">' . esc_html( (string) $args['more_text'] ) . '</a>'
			: '';

		return '<section class="hodima-home-section hodima-product-section" aria-labelledby="' . esc_attr( (string) $args['id'] ) . '" style="--section-bg: ' . esc_attr( (string) $args['bg_color'] ) . ';">'
			. '<div class="hodima-home-header"><div class="hodima-title-group"><h2 id="' . esc_attr( (string) $args['id'] ) . '" class="hodima-section-title">' . $title . '</h2>' . $subtitle . '<div class="hodima-title-line"></div></div>' . $more . '</div>'
			. '<div class="' . esc_attr( trim( 'hodima-scroller ' . (string) $args['scroller_class'] ) ) . '">' . $cards_html . '</div>'
			. '</section>';
	}
}
