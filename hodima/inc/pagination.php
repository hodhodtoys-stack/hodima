<?php
/**
 * صفحه‌بندی مشترک: متن‌ها و ارقام (وبلاگ، دسته، برچسب، جستجو، دسته و فروشگاه ووکامرس)
 * Path: hodima/inc/pagination.php
 *
 * ظاهر: assets/css/pagination.css. تا 3.3.4 فقط دسته‌های محصول (category-archive.php)
 * «‹ قبلی / بعدی ›»، ارقام انگلیسی و دکمه‌های کامل داشتند؛ وبلاگ ساده‌تر بود.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * آرگومان‌های مشترک paginate_links().
 *
 * @return array<string, mixed>
 */
function hodima_pagination_args(): array {
	return [
		'mid_size'           => 1,
		'end_size'           => 1,
		'prev_text'          => '<span aria-hidden="true">&lsaquo;</span> قبلی',
		'next_text'          => 'بعدی <span aria-hidden="true">&rsaquo;</span>',
		// عدد در عنصر جدا تا CSS فونت لاتین برایش بگذارد
		'before_page_number' => '<span class="hodima-num">',
		'after_page_number'  => '</span>',
	];
}

/** رقم‌های فارسی و عربی → انگلیسی (paginate_links با زبان فارسی رقم فارسی می‌سازد). */
function hodima_latin_digits( string $html ): string {
	return strtr( $html, [
		'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
		'۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
		'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
		'٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
	] );
}

/**
 * لینک‌های صفحه‌بندی با ظاهر و ارقام مشترک؛ خروجی HTML ساخته‌شده وردپرس (بدون لینک = رشته خالی).
 *
 * @param array<string, mixed> $args آرگومان‌های paginate_links (total، current، base…)
 */
function hodima_paginate_links( array $args ): string {
	return hodima_latin_digits( (string) paginate_links( array_merge( hodima_pagination_args(), $args ) ) );
}

/*
 * وردپرس برای صفحه فعلی <span aria-label="Page 1" aria-current="page" class="page-numbers current"> می‌سازد؛
 * aria-label روی span بدون نقش مجاز نیست (ARIA: aria-prohibited-attr) و خوانش‌گرها آن را نادیده
 * می‌گیرند یا مبهم می‌خوانند. خود شماره نام قابل‌خواندن است و aria-current="page" وضعیت را می‌گوید.
 * روی خروجی همه paginate_links (وبلاگ، جستجو، دسته و فروشگاه ووکامرس؛ برچسب را خود ووکامرس هم اضافه می‌کند) اعمال می‌شود.
 */
add_filter( 'paginate_links_output', static function ( mixed $html ): mixed {

	if ( ! is_string( $html ) || ! str_contains( $html, 'page-numbers current' ) ) {
		return $html;
	}

	return (string) preg_replace_callback(
		'/<span\b[^>]*\bclass="page-numbers current"[^>]*>/',
		static fn( array $found ): string => (string) preg_replace( '/\saria-label="[^"]*"/', '', $found[0] ),
		$html
	);
}, 20 ); // اولویت ۲۰: ووکامرس (wc_add_aria_label_to_pagination_numbers) این aria-label را در اولویت ۱۰ اضافه می‌کند
