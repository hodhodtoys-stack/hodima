<?php
/**
 * دیدگاه‌های مقاله/برگه و نظرات محصول: فهرست، «نمایش همه»، برچسب فروشگاه/نویسنده،
 * و برای محصول خلاصه امتیازها و نشان «خریدار این محصول»
 * Path: hodima/inc/comments.php
 *
 * از 3.9.0. تا پیش از آن فهرست و فرم نظرات صفحه محصول فقط از شورت‌کد
 * expand_product_reviews افزونه Hodima Media چاپ می‌شد (بدون افزونه کل بخش، حتی فرم،
 * حذف می‌شد) و مقاله‌ها با expand_blog_comments؛ هر دو کل بخش را *همراه فرم* در
 * کادر جمع‌شونده می‌گذاشتند. حالا قالب خودش چاپ می‌کند (comments.php و
 * woocommerce/single-product-reviews.php) و فقط فهرست جمع می‌شود: چند رشته اول
 * دیده می‌شوند، بقیه با دکمه «نمایش همه» (assets/js/comments.js). همه نظرها در
 * HTML می‌مانند (گوگل و اسکیمای امتیاز همان را می‌بینند).
 * تنظیمات (3.9.1): «صفحه محصول ← نظرات کاربران / نمایش نظرات» و «وبلاگ ← دیدگاه‌ها».
 * «مرتب‌سازی نظرها» (3.9.1) در 3.9.2 به خواست کاربر حذف شد.
 * استایل: assets/css/comments.css (+ single-post.css و single-product.css).
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** پیش‌فرض تعداد رشته‌های سطح اول پیش از «نمایش همه» (فیلدهای product_reviews_visible و blog_comments_visible). */
const HODIMA_COMMENTS_VISIBLE = 3;

/** آیا این برگه صفحه محصول است (نظرات ووکامرس)؟ */
function hodima_comments_is_product(): bool {
	return function_exists( 'is_product' ) && is_product();
}

/** تعداد رشته‌های سطح اول پیش از «نمایش همه» برای همین صفحه (تنظیمات قالب). */
function hodima_comments_visible(): int {
	$value = (int) hodima_setting( hodima_comments_is_product() ? 'product_reviews_visible' : 'blog_comments_visible' );
	return $value > 0 ? $value : HODIMA_COMMENTS_VISIBLE;
}

/**
 * قانون جمع شدن فهرست با عدد تنظیمات (کنار comments.css چاپ می‌شود، inc/enqueue.php).
 * nth-child عدد ثابت می‌خواهد؛ قبلا (3.9.0) خود comments.css «n + 4» ثابت داشت.
 */
function hodima_comments_collapse_css(): string {
	return sprintf( '.hodima-comments.is-collapsed>li:nth-child(n+%d){display:none}', hodima_comments_visible() + 1 );
}

/** آیا تصویر پروفایل کنار نظرها چاپ شود (تنظیمات قالب و «نمایش آواتار» وردپرس)؟ */
function hodima_comments_avatars(): bool {
	return (bool) get_option( 'show_avatars' ) && (bool) hodima_setting( hodima_comments_is_product() ? 'product_reviews_avatars' : 'blog_comments_avatars' );
}

// نظرات محصول بی‌تصویر پروفایل: قالب review.php ووکامرس آن را با این هوک چاپ می‌کند
add_action(
	'wp',
	static function (): void {
		if ( hodima_comments_is_product() && ! hodima_setting( 'product_reviews_avatars' ) ) {
			remove_action( 'woocommerce_review_before', 'woocommerce_review_display_gravatar', 10 );
		}
	}
);

/**
 * رشته‌های سطح اول همین صفحه. با صفحه‌بندی دیدگاه‌ها (تنظیمات ← گفت‌وگو) ۰: هر صفحه
 * خودش کوتاه است و جمع شدن فقط همان صفحه را می‌دید.
 * پاسخ بی‌والد (والد تأییدنشده) را وردپرس سطح اول چاپ می‌کند و اینجا شمرده نمی‌شود؛
 * پس شمارش هرگز بیشتر از واقعی نیست و چیزی بی‌دکمه پنهان نمی‌ماند.
 */
function hodima_comments_top_level_count(): int {

	global $wp_query;

	if ( get_option( 'page_comments' ) && get_comment_pages_count() > 1 ) {
		return 0;
	}

	$threaded = (bool) get_option( 'thread_comments' );

	return count(
		array_filter(
			(array) ( $wp_query->comments ?? [] ),
			static fn( mixed $comment ): bool => $comment instanceof WP_Comment && ( ! $threaded || 0 === (int) $comment->comment_parent )
		)
	);
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

/** نشان «خریدار این محصول» (review-meta.php)، یا رشته خالی اگر خاموش یا خریدار نیست. */
function hodima_review_verified_badge( WP_Comment $comment ): string {
	if ( ! hodima_setting( 'product_reviews_verified' ) || ! wc_review_is_from_verified_owner( (int) $comment->comment_ID ) ) {
		return '';
	}
	return ' <span class="hodima-comment-badge hodima-comment-badge--buyer">خریدار این محصول</span>';
}

/**
 * خلاصه امتیازها بالای نظرات محصول، در یک ردیف: «۴٫۵ ★★★★★ از ۲۰ نظر».
 * همان داده ووکامرس که اسکیمای aggregateRating (افزونه SEO) می‌خواند؛ گوگل فقط دیده‌شدن
 * میانگین و تعداد را می‌خواهد. نوارهای هر امتیاز (۵ تا ۱ ستاره، 3.9.1) در 3.9.3 به خواست
 * کاربر حذف شد: در موبایل جای زیادی می‌گرفت و برای سئو اثری نداشت.
 * ارقام فارسی و جداکننده «٫»: number_format_i18n روی سایت «4,0» لاتین می‌داد.
 */
function hodima_reviews_summary( WC_Product $product ): void {

	if ( ! hodima_setting( 'product_reviews_summary' ) || ! wc_review_ratings_enabled() ) {
		return;
	}

	$total = array_sum( array_map( 'intval', (array) $product->get_rating_counts() ) );

	if ( $total < 1 ) {
		return;
	}

	$average = (float) $product->get_average_rating();
	$score   = hodima_fa_digits( number_format( $average, 1, '٫', '' ) );
	?>
	<div class="hodima-rating-summary">
		<span class="hodima-rating-summary__average" aria-hidden="true"><?php echo esc_html( $score ); ?></span>
		<span class="hodima-rating-summary__stars" aria-hidden="true"><?php echo wp_kses_post( wc_get_rating_html( $average, $total ) ); ?></span>
		<span class="hodima-rating-summary__total">
			<span class="screen-reader-text"><?php echo esc_html( sprintf( 'میانگین امتیاز %s از ۵،', $score ) ); ?></span>
			<?php echo esc_html( sprintf( 'از %s نظر', hodima_fa_digits( (string) $total ) ) ); ?>
		</span>
	</div>
	<?php
}

/**
 * فهرست دیدگاه‌ها با دکمه «نمایش همه» (اگر رشته‌ها بیشتر از hodima_comments_visible() باشند).
 *
 * @param array<string, mixed> $args       آرگومان‌های wp_list_comments
 * @param string               $list_class کلاس ol (مقاله: comment-list؛ ووکامرس: commentlist)
 * @param string               $noun       «دیدگاه‌ها» یا «نظرات» برای متن دکمه
 */
function hodima_comments_list( array $args, string $list_class, string $noun ): void {

	$top      = hodima_comments_top_level_count();
	$collapse = $top > hodima_comments_visible();
	$list_id  = wp_unique_id( 'hodima-comments-' );
	$classes  = $list_class . ' hodima-comments' . ( $collapse ? ' is-collapsed' : '' ) . ( hodima_comments_avatars() ? '' : ' hodima-comments--no-avatars' );

	// برچسب کنار نام، فقط در همین فهرست (ابزارک «آخرین دیدگاه‌ها» و پیشخوان دست نمی‌خورند)
	$badge = static function ( mixed $link, mixed $author = '', mixed $comment_id = 0 ): string {
		$comment = get_comment( is_numeric( $comment_id ) ? (int) $comment_id : 0 );
		return (string) $link . ( $comment instanceof WP_Comment ? hodima_comment_badge( $comment ) : '' );
	};
	add_filter( 'get_comment_author_link', $badge, 10, 3 );

	echo '<ol id="' . esc_attr( $list_id ) . '" class="' . esc_attr( $classes ) . '" data-visible="' . (int) hodima_comments_visible() . '">';
	wp_list_comments( $args );
	echo '</ol>';

	remove_filter( 'get_comment_author_link', $badge, 10 );

	if ( $collapse ) {
		printf(
			'<button type="button" class="hodima-comments-more" data-hodima-comments-more aria-controls="%1$s" aria-expanded="false">%2$s</button>',
			esc_attr( $list_id ),
			esc_html( sprintf( 'نمایش همه %1$s (%2$s)', $noun, number_format_i18n( (int) get_comments_number() ) ) )
		);
	}

	// فقط وقتی دکمه هست؛ در پایان صفحه چاپ می‌شود (in_footer)
	if ( $collapse ) {
		hodima_enqueue_asset( 'hodima-comments', 'assets/js/comments.js', [], [ 'in_footer' => true, 'strategy' => 'defer' ] );
	}
}
