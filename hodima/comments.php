<?php
/**
 * دیدگاه‌های مقاله و برگه (comments_template)
 *
 * از 3.9.0. تا پیش از آن قالب comments.php نداشت و وردپرس قالب منسوخ
 * wp-includes/theme-compat/comments.php را می‌گذاشت (هشدار deprecated در هر بار):
 * عنوان h3 انگلیسی پیش از عنوان h2 فرم، ol.commentlist که قانون‌های .comment-list
 * single-post.css به آن نمی‌خورد، دو نوار خالی صفحه‌بندی، و «says:» کنار هر نام.
 * صفحه محصول این فایل را نمی‌گیرد: ووکامرس woocommerce/single-product-reviews.php را می‌گذارد.
 * فهرست و «نمایش همه»: inc/comments.php.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

if ( post_password_required() ) {
	return;
}

$hodima_comment_count = (int) get_comments_number();
?>
<div id="comments" class="comments-area">

	<?php if ( have_comments() ) : ?>
		<h2 class="comments-title">دیدگاه‌ها <span class="comments-title__count">(<?php echo esc_html( number_format_i18n( $hodima_comment_count ) ); ?>)</span></h2>

		<?php
		hodima_comments_list(
			[
				'style'       => 'ol',
				'short_ping'  => true,
				'avatar_size' => hodima_comments_avatars() ? 40 : 0, // ۰ = بدون تصویر (تنظیمات قالب ← وبلاگ ← دیدگاه‌ها)
			],
			'comment-list',
			'دیدگاه‌ها'
		);

		the_comments_navigation(
			[
				'prev_text' => 'دیدگاه‌های قدیمی‌تر',
				'next_text' => 'دیدگاه‌های تازه‌تر',
			]
		);
		?>
	<?php endif; ?>

	<?php if ( ! comments_open() && $hodima_comment_count ) : ?>
		<p class="no-comments">ارسال دیدگاه برای این مطلب بسته شده است.</p>
	<?php endif; ?>

	<?php comment_form(); ?>

</div>
