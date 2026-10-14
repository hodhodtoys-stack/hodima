<?php
/**
 * امکانات صفحه مقاله: فهرست مطالب، برچسب‌ها، اشتراک‌گذاری، کادر نویسنده، مقاله قبلی/بعدی،
 * و تعداد مقاله در هر صفحه آرشیو
 * Path: hodima/inc/blog-post.php
 *
 * تنظیمات: «تنظیمات قالب هدیما ← وبلاگ ← امکانات صفحه مقاله». همه فقط نمایش‌اند
 * (داده‌ای ذخیره نمی‌کنند)؛ استایل: single-post.css، رفتار (کپی لینک، پیشرفت): single-post.js.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * به تیترهای H2 و H3 متن شناسه می‌دهد و فهرست مطالب را می‌سازد.
 * تیتر با id خودش (مثلا از ویرایشگر) همان را نگه می‌دارد؛ بقیه «toc-N».
 * کمتر از $min تیتر ← فهرستی ساخته نمی‌شود (متن بی‌تغییر).
 *
 * @return array{content: string, toc: string}
 */
function hodima_post_toc( string $html, int $min = 3 ): array {

	$items = [];

	$content = (string) preg_replace_callback(
		'~<h([23])\b([^>]*)>(.*?)</h\1>~isu',
		static function ( array $heading ) use ( &$items ): string {

			$text = trim( html_entity_decode( wp_strip_all_tags( $heading[3] ), ENT_QUOTES, 'UTF-8' ) );
			if ( '' === $text ) {
				return $heading[0];
			}

			$attrs = $heading[2];
			if ( preg_match( '~\bid=("|\')(.+?)\1~i', $attrs, $id_match ) ) {
				$id = $id_match[2];
			} else {
				$id     = 'toc-' . ( count( $items ) + 1 );
				$attrs .= ' id="' . $id . '"';
			}

			$items[] = [ 'level' => (int) $heading[1], 'id' => $id, 'text' => $text ];

			return '<h' . $heading[1] . $attrs . '>' . $heading[3] . '</h' . $heading[1] . '>';
		},
		$html
	);

	if ( count( $items ) < max( 1, (int) apply_filters( 'hodima_toc_min_headings', $min ) ) ) { // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- پیشوند hodima_ دارد
		return [ 'content' => $html, 'toc' => '' ];
	}

	// H3 زیر H2 قبلی تو در تو می‌شود؛ H3 بدون H2 قبلی در سطح اول می‌ماند
	$list      = '';
	$open_sub  = false;
	$has_group = false;

	foreach ( $items as $item ) {
		$link = '<a href="#' . esc_attr( $item['id'] ) . '">' . esc_html( $item['text'] ) . '</a>';

		if ( 3 === $item['level'] && $has_group ) {
			$list     .= ( $open_sub ? '' : '<ol>' ) . '<li>' . $link . '</li>';
			$open_sub  = true;
			continue;
		}

		$list     .= ( $open_sub ? '</ol></li>' : ( $has_group ? '</li>' : '' ) ) . '<li>' . $link;
		$open_sub  = false;
		$has_group = true;
	}

	$list .= $open_sub ? '</ol></li>' : '</li>';

	$toc = '<nav class="single-post-toc" aria-label="فهرست مطالب"><details class="single-post-toc__box" open>'
		. '<summary class="single-post-toc__title">فهرست مطالب</summary>'
		. '<ol class="single-post-toc__list">' . $list . '</ol></details></nav>';

	return [ 'content' => $content, 'toc' => $toc ];
}

/** برچسب‌های مقاله (دکمه‌های لینک‌دار)؛ بدون برچسب یا کلید خاموش ← رشته خالی. */
function hodima_post_tags_html( int $post_id ): string {

	if ( ! hodima_setting( 'blog_tags' ) ) {
		return '';
	}

	$tags = get_the_tags( $post_id );
	if ( ! is_array( $tags ) || [] === $tags ) {
		return '';
	}

	$html = '';
	foreach ( $tags as $tag ) {
		$link = get_tag_link( $tag );
		if ( '' === $link ) {
			continue;
		}
		$html .= '<li><a class="hodima-chip" rel="tag" href="' . esc_url( $link ) . '">' . esc_html( $tag->name ) . '</a></li>';
	}

	return '' !== $html ? '<div class="single-post-tags"><span class="single-post-tags__label">برچسب‌ها:</span><ul class="single-post-tags__list">' . $html . '</ul></div>' : '';
}

/** اشتراک‌گذاری: کپی لینک (single-post.js)، تلگرام و واتساپ. */
function hodima_post_share_html( int $post_id ): string {

	if ( ! hodima_setting( 'blog_share' ) ) {
		return '';
	}

	$url   = (string) get_permalink( $post_id );
	$title = html_entity_decode( get_the_title( $post_id ), ENT_QUOTES, 'UTF-8' );

	if ( '' === $url ) {
		return '';
	}

	$telegram = 'https://t.me/share/url?url=' . rawurlencode( $url ) . '&text=' . rawurlencode( $title );
	$whatsapp = 'https://wa.me/?text=' . rawurlencode( $title . ' ' . $url );

	return '<div class="single-post-share"><span class="single-post-share__label">اشتراک‌گذاری:</span><ul class="single-post-share__list">'
		// لینک (نه <button>: دکمه‌های سایت با !important سفید می‌شوند)؛ بدون جاوااسکریپت مخفی می‌ماند
		. '<li hidden><a class="hodima-chip" href="' . esc_url( $url ) . '" role="button" data-hodima-copy="' . esc_url( $url ) . '" data-done="لینک کپی شد">کپی لینک</a></li>'
		. '<li><a class="hodima-chip" href="' . esc_url( $telegram ) . '" target="_blank" rel="noopener noreferrer">تلگرام</a></li>'
		. '<li><a class="hodima-chip" href="' . esc_url( $whatsapp ) . '" target="_blank" rel="noopener noreferrer">واتساپ</a></li>'
		. '</ul><span class="screen-reader-text" role="status" data-hodima-copy-status></span></div>';
}

/** کادر نویسنده: فقط وقتی نویسنده «معرفی» (bio) نوشته باشد. */
function hodima_post_author_box_html( int $post_id ): string {

	if ( ! hodima_setting( 'blog_author_box' ) ) {
		return '';
	}

	$author_id = (int) get_post_field( 'post_author', $post_id );
	$bio       = trim( (string) get_the_author_meta( 'description', $author_id ) );
	$name      = (string) get_the_author_meta( 'display_name', $author_id );

	if ( '' === $bio || '' === $name ) {
		return '';
	}

	return '<aside class="single-post-author" aria-label="نویسنده">'
		. get_avatar( $author_id, 64, '', '', [ 'class' => 'single-post-author__avatar', 'loading' => 'lazy' ] )
		. '<div class="single-post-author__body"><a class="single-post-author__name" href="' . esc_url( get_author_posts_url( $author_id ) ) . '">' . esc_html( $name ) . '</a>'
		. '<p class="single-post-author__bio">' . esc_html( $bio ) . '</p></div></aside>';
}

/** مقاله قبلی و بعدی؛ اول از همان دسته، اگر نبود از کل وبلاگ. */
function hodima_post_nav_html( int $post_id ): string {

	if ( ! hodima_setting( 'blog_prev_next' ) ) {
		return '';
	}

	$post = get_post( $post_id );
	if ( ! $post instanceof WP_Post ) {
		return '';
	}

	// get_adjacent_post نسبت به $GLOBALS['post'] کار می‌کند (در حلقه مقاله همین است)
	$links = '';
	foreach ( [ 'prev' => true, 'next' => false ] as $rel => $previous ) {

		$adjacent = get_adjacent_post( true, '', $previous );
		if ( ! $adjacent instanceof WP_Post ) {
			$adjacent = get_adjacent_post( false, '', $previous );
		}
		if ( ! $adjacent instanceof WP_Post ) {
			continue;
		}

		// فلش تزئینی است و صفحه‌خوان آن را نمی‌خواند
		$label  = 'prev' === $rel ? '<span aria-hidden="true">‹</span> مقاله قبلی' : 'مقاله بعدی <span aria-hidden="true">›</span>';
		$links .= '<a class="single-post-nav__link single-post-nav__link--' . $rel . '" rel="' . $rel . '" href="' . esc_url( (string) get_permalink( $adjacent ) ) . '">'
			. '<span class="single-post-nav__label">' . $label . '</span>'
			. '<span class="single-post-nav__title">' . esc_html( get_the_title( $adjacent ) ) . '</span></a>';
	}

	return '' !== $links ? '<nav class="single-post-nav" aria-label="مقاله قبلی و بعدی">' . $links . '</nav>' : '';
}

/**
 * تعداد مقاله در هر صفحه آرشیو (وبلاگ، دسته، برچسب، نویسنده، تاریخ) از تنظیمات قالب؛
 * ۰ = تنظیم وردپرس. جستجو و بقیه نوع‌های نوشته دست نمی‌خورند.
 */
add_action( 'pre_get_posts', 'hodima_blog_per_page' );

function hodima_blog_per_page( mixed $query ): void {

	if ( ! $query instanceof WP_Query || is_admin() || ! $query->is_main_query() || $query->is_search() ) {
		return;
	}

	if ( ! ( $query->is_home() || $query->is_category() || $query->is_tag() || $query->is_author() || $query->is_date() ) ) {
		return;
	}

	$per_page = (int) hodima_setting( 'blog_per_page' );
	if ( $per_page > 0 ) {
		$query->set( 'posts_per_page', $per_page );
	}
}
