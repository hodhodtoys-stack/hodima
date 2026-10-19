<?php
/**
 * دیدگاه‌های مقاله/برگه و نظرات محصول: فهرست، «نمایش همه»، برچسب فروشگاه/نویسنده
 * Path: hodima/inc/comments.php
 *
 * از 3.9.0. تا پیش از آن فهرست و فرم نظرات صفحه محصول فقط از شورت‌کد
 * expand_product_reviews افزونه Hodima Media چاپ می‌شد (بدون افزونه کل بخش، حتی فرم،
 * حذف می‌شد) و مقاله‌ها با expand_blog_comments؛ هر دو کل بخش را *همراه فرم* در
 * کادر جمع‌شونده می‌گذاشتند. حالا قالب خودش چاپ می‌کند (comments.php و
 * woocommerce/single-product-reviews.php) و فقط فهرست جمع می‌شود: چند رشته اول
 * دیده می‌شوند، بقیه با دکمه «نمایش همه» (assets/js/comments.js). همه نظرها در
 * HTML می‌مانند (گوگل و اسکیمای امتیاز همان را می‌بینند).
 * استایل: assets/css/comments.css (+ single-post.css و single-product.css).
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** تعداد رشته‌های سطح اول که پیش از «نمایش همه» دیده می‌شوند (همان عدد nth-child در comments.css). */
const HODIMA_COMMENTS_VISIBLE = 3;

/**
 * تعداد رشته‌های سطح اول همین صفحه، اگر از HODIMA_COMMENTS_VISIBLE بیشتر باشد؛ وگرنه ۰ (جمع نشود).
 * با صفحه‌بندی دیدگاه‌ها (تنظیمات ← گفت‌وگو) جمع نمی‌شود: هر صفحه خودش کوتاه است.
 * پاسخ بی‌والد (والد تأییدنشده) را وردپرس سطح اول چاپ می‌کند و اینجا شمرده نمی‌شود؛
 * پس شمارش هرگز بیشتر از واقعی نیست و چیزی بی‌دکمه پنهان نمی‌ماند.
 */
function hodima_comments_collapse_count(): int {

	global $wp_query;

	if ( get_option( 'page_comments' ) && get_comment_pages_count() > 1 ) {
		return 0;
	}

	$threaded = (bool) get_option( 'thread_comments' );
	$top      = count(
		array_filter(
			(array) ( $wp_query->comments ?? [] ),
			static fn( mixed $comment ): bool => $comment instanceof WP_Comment && ( ! $threaded || 0 === (int) $comment->comment_parent )
		)
	);

	return $top > HODIMA_COMMENTS_VISIBLE ? $top : 0;
}

/**
 * برچسب نویسنده یک دیدگاه: «فروشگاه» برای کارکنانی که محصول را ویرایش می‌کنند،
 * «نویسنده» برای نویسنده همان مقاله، «مدیر سایت» برای بقیه کسانی که مقاله را ویرایش می‌کنند.
 */
function hodima_comment_badge_label( WP_Comment $comment ): string {

	$user_id = (int) $comment->user_id;
	$post    = get_post( (int) $comment->comment_post_ID );

	if ( ! $user_id || ! $post instanceof WP_Post ) {
		return '';
	}

	$staff = user_can( $user_id, 'edit_post', $post->ID );

	if ( 'product' === $post->post_type ) {
		return $staff ? 'فروشگاه' : '';
	}

	if ( (int) $post->post_author === $user_id ) {
		return 'نویسنده';
	}

	return $staff ? 'مدیر سایت' : '';
}

/** HTML برچسب، یا رشته خالی. */
function hodima_comment_badge( WP_Comment $comment ): string {
	$label = hodima_comment_badge_label( $comment );
	return '' === $label ? '' : ' <span class="hodima-comment-badge">' . esc_html( $label ) . '</span>';
}

/**
 * فهرست دیدگاه‌ها با دکمه «نمایش همه» (اگر رشته‌ها بیشتر از HODIMA_COMMENTS_VISIBLE باشند).
 *
 * @param array<string, mixed> $args       آرگومان‌های wp_list_comments
 * @param string               $list_class کلاس ol (مقاله: comment-list؛ ووکامرس: commentlist)
 * @param string               $noun       «دیدگاه‌ها» یا «نظرات» برای متن دکمه
 */
function hodima_comments_list( array $args, string $list_class, string $noun ): void {

	$collapse = hodima_comments_collapse_count();
	$list_id  = wp_unique_id( 'hodima-comments-' );

	// برچسب کنار نام، فقط در همین فهرست (ابزارک «آخرین دیدگاه‌ها» و پیشخوان دست نمی‌خورند)
	$badge = static function ( mixed $link, mixed $author = '', mixed $comment_id = 0 ): string {
		$comment = get_comment( is_numeric( $comment_id ) ? (int) $comment_id : 0 );
		return (string) $link . ( $comment instanceof WP_Comment ? hodima_comment_badge( $comment ) : '' );
	};
	add_filter( 'get_comment_author_link', $badge, 10, 3 );

	printf(
		'<ol id="%1$s" class="%2$s"%3$s>',
		esc_attr( $list_id ),
		esc_attr( $list_class . ( $collapse ? ' hodima-comments is-collapsed' : '' ) ),
		$collapse ? ' data-visible="' . (int) HODIMA_COMMENTS_VISIBLE . '"' : ''
	);
	wp_list_comments( $args );
	echo '</ol>';

	remove_filter( 'get_comment_author_link', $badge, 10 );

	if ( ! $collapse ) {
		return;
	}

	printf(
		'<button type="button" class="hodima-comments-more" data-hodima-comments-more aria-controls="%1$s" aria-expanded="false">%2$s</button>',
		esc_attr( $list_id ),
		esc_html( sprintf( 'نمایش همه %1$s (%2$s)', $noun, number_format_i18n( (int) get_comments_number() ) ) )
	);

	// فقط وقتی دکمه هست؛ در پایان صفحه چاپ می‌شود (in_footer)
	hodima_enqueue_asset( 'hodima-comments', 'assets/js/comments.js', [], [ 'in_footer' => true, 'strategy' => 'defer' ] );
}
