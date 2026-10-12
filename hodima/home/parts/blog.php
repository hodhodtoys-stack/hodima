<?php
/**
 * بخش صفحه اصلی: آخرین مقالات وبلاگ («نبض بازار»)
 * Path: home/parts/blog.php
 *
 * فراخوانی: get_template_part( 'home/parts/blog', null, $args ) — از چیدمان
 * «تنظیمات قالب ← صفحه اصلی» یا شورت‌کد قدیمی [section09].
 * کارت و باکس همان «محصولات یک دسته» است (hodima_home_card_html و
 * hodima_home_cards_section در logic.php)؛ تصویر مربع با برش مرکزی، مقاله بدون
 * تصویر کادر جایگزین با رنگ برند می‌گیرد.
 *
 * @var array{title?:string, subtitle?:string, limit?:int, more_text?:string, tone?:string} $args
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- قالب داخل تابع (load_template/wc_get_template_part) لود می‌شود؛ متغیرها محلی‌اند، نه سراسری

$args       = wp_parse_args( $args ?? [], hodima_home_section_defaults( 'blog' ) );
$posts_data = array_slice( hodima_home_blog_posts_data(), 0, max( 1, (int) $args['limit'] ) );

if ( ! $posts_data ) {
	return;
}

// لینک صفحه بلاگ: برگه «نوشته‌ها» در «تنظیمات ← خواندن»
$blog_page_id = (int) get_option( 'page_for_posts' );
$blog_url     = $blog_page_id ? (string) get_permalink( $blog_page_id ) : home_url( '/blog/' );

// همان کارت و باکس «محصولات یک دسته» (logic.php)؛ فقط بدون قیمت
$hodima_cards = '';
foreach ( $posts_data as $index => $post_item ) {
	$hodima_cards .= hodima_home_card_html( [
		'link'         => $post_item['link'],
		'title'        => $post_item['title'],
		'thumbnail_id' => (int) $post_item['thumbnail_id'],
		'first'        => 0 === $index,
		'size'         => 'medium_large',
	] );
}

echo hodima_home_cards_section( [ // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- خروجی ساخته‌شده و escape‌شده در logic.php
	'id'        => wp_unique_id( 'arian-blog-title-' ),
	'title'     => (string) $args['title'],
	'subtitle'  => (string) $args['subtitle'],
	'title_url' => $blog_url,
	'more_url'  => '' !== (string) $args['more_text'] ? $blog_url : '',
	'more_text' => (string) $args['more_text'],
	'bg_color'  => hodima_home_tones()[ $args['tone'] ]['css'] ?? 'transparent',
], $hodima_cards );
