<?php
/**
 * لینک‌های مرتبط دستی — سه گروه (سه شورت‌کد)
 * Path: plugins/hodima-seo/inc/manual_related_link/includes/group.php
 *
 * پیش از نسخه ۲ فقط یک «ویترین پیشنهادی» با سه خانه وجود داشت و دسته، محصول
 * و مقاله در یک کادر قاطی می‌شدند. حالا هر نوع لینک گروه، شورت‌کد، تعداد و
 * ظاهر خودش را دارد.
 */

declare(strict_types=1);

namespace Hodima\RelatedLinks;

defined( 'ABSPATH' ) || exit;

enum Group: string {

	case Categories = 'categories';
	case Products   = 'products';
	case Article    = 'article';

	/** نام گروه در پیشخوان. */
	public function label(): string {
		return match ( $this ) {
			self::Categories => 'دسته‌بندی‌های مرتبط',
			self::Products   => 'محصولات مکمل',
			self::Article    => 'مقاله پیشنهادی',
		};
	}

	/** عنوان پیش‌فرض بالای کادر در سایت؛ مقاله عمدا هیچ عنوانی ندارد (خواسته کاربر). */
	public function default_title(): string {
		return match ( $this ) {
			self::Categories, self::Products => $this->label(),
			self::Article                    => '',
		};
	}

	public function has_title(): bool {
		return self::Article !== $this;
	}

	/** تعداد پیش‌فرض لینک‌های نمایشی. */
	public function default_count(): int {
		return match ( $this ) {
			self::Categories, self::Products => 2,
			self::Article                    => 1,
		};
	}

	/**
	 * شورت‌کد اصلی گروه.
	 *
	 * «محصولات مکمل» همان نام قدیمی manual_related_products را دارد (خواسته
	 * کاربر، نسخه ۱.۵): شورت‌کدی که از قبل در محتوای سایت است بدون ویرایش
	 * همان کادر محصولات می‌شود و لازم نیست روزی از محتوا پاک شود.
	 */
	public function shortcode(): string {
		return match ( $this ) {
			self::Categories => 'hodima_related_categories',
			self::Products   => 'manual_related_products',
			self::Article    => 'hodima_related_article',
		};
	}

	/**
	 * نام‌های دیگری که همین گروه را نشان می‌دهند:
	 * manual_related_links (هم‌معنی قدیمی) و hodima_complementary_products (نسخه ۱.۴).
	 *
	 * @return list<string>
	 */
	public function aliases(): array {
		return match ( $this ) {
			self::Products => [ 'manual_related_links', 'hodima_complementary_products' ],
			default        => [],
		};
	}

	/** @return list<string> همه نام‌های شورت‌کد این گروه. */
	public function tags(): array {
		return [ $this->shortcode(), ...$this->aliases() ];
	}

	/** راهنمای جستجو در کادر ویرایشگر. */
	public function search_placeholder(): string {
		return match ( $this ) {
			self::Categories => 'جستجوی دسته‌بندی یا چسباندن آدرس…',
			self::Products   => 'جستجوی نام یا کد (SKU) محصول، یا چسباندن آدرس…',
			self::Article    => 'جستجوی مقاله یا برگه، یا چسباندن آدرس…',
		};
	}

	public function icon(): string {
		return match ( $this ) {
			self::Categories => 'dashicons-category',
			self::Products   => 'dashicons-cart',
			self::Article    => 'dashicons-media-text',
		};
	}

	/** برچسب دسترس‌پذیری کادر بدون عنوان (فقط صفحه‌خوان می‌خواند). */
	public function aria_label(): string {
		return match ( $this ) {
			self::Article => 'مطلب پیشنهادی',
			default       => $this->label(),
		};
	}
}
