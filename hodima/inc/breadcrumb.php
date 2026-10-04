<?php
/**
 * مسیر راهنمای دیده‌شده — مقاله‌ها و آرشیو وبلاگ
 * Path: hodima/inc/breadcrumb.php
 *
 * قبلا single-post.php و archive-blog.php هر کدام مسیر خودشان را دستی
 * می‌ساختند («خانه / وبلاگ > عنوان» با جداکننده ناهمسان و استایل inline، و یک
 * شاخه yoast_breadcrumb برای افزونه‌ای که روی سایت نیست). مسیر اسکیما
 * (BreadcrumbList افزونه Hodima SEO) مسیر دیگری بود: «خانه › دسته اصلی › عنوان».
 * گوگل مسیر دیده‌شده و اسکیما را با هم می‌سنجد؛ حالا هر دو از یک منبع‌اند:
 * hodima_breadcrumb_items() افزونه SEO. بدون افزونه (یا با بردکرامب خاموش در
 * «اسکیما ← بردکرامب») همان مسیر ساده قبلی ساخته می‌شود.
 *
 * صفحه‌های ووکامرس woocommerce_breadcrumb() خودشان را دارند (افزونه SEO دسته
 * اصلی آن را هم یکی می‌کند).
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * پله‌های مسیر صفحه جاری؛ آخرین پله صفحه فعلی است.
 *
 * @return list<array{name: string, url: string}>
 */
function hodima_breadcrumb_trail(): array {

	$items = function_exists( 'hodima_breadcrumb_items' ) ? hodima_breadcrumb_items() : [];

	if ( $items ) {
		return array_values( array_map(
			static fn( array $item ): array => [
				'name' => (string) ( $item['name'] ?? '' ),
				'url'  => (string) ( $item['item'] ?? '' ),
			],
			$items
		) );
	}

	// فالبک: همان مسیر قبلی قالب
	$trail = [ [ 'name' => 'خانه', 'url' => home_url( '/' ) ] ];

	if ( is_singular( 'post' ) ) {
		$trail[] = [ 'name' => hodima_blog_name(), 'url' => hodima_blog_url() ];
		$trail[] = [ 'name' => wp_strip_all_tags( get_the_title() ), 'url' => '' ];
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$trail[] = [ 'name' => (string) single_term_title( '', false ), 'url' => '' ];
	} else {
		$trail[] = [ 'name' => is_home() ? hodima_blog_name() : wp_strip_all_tags( get_the_archive_title() ), 'url' => '' ];
	}

	return $trail;
}

/**
 * HTML مسیر راهنما: <nav aria-label> + فهرست مرتب؛ پله آخر بدون لینک و با
 * aria-current="page". جداکننده «/» را CSS می‌گذارد (style.css) و صفحه‌خوان آن را
 * نمی‌خواند.
 */
function hodima_breadcrumb_html(): string {

	$trail = hodima_breadcrumb_trail();
	$last  = array_key_last( $trail );
	$html  = '';

	foreach ( $trail as $index => $step ) {
		$name  = esc_html( $step['name'] );
		$html .= match ( true ) {
			$index === $last    => '<li><span aria-current="page">' . $name . '</span></li>',
			'' !== $step['url'] => '<li><a href="' . esc_url( $step['url'] ) . '">' . $name . '</a></li>',
			default             => '<li><span>' . $name . '</span></li>',
		};
	}

	return '<nav class="hodima-breadcrumb" aria-label="مسیر راهنما"><ol class="hodima-breadcrumb__list">' . $html . '</ol></nav>';
}
