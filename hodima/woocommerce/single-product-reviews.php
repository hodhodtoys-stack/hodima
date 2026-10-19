<?php
/**
 * Display single product reviews (comments)
 *
 * بازنویسی قالب single-product-reviews.php ووکامرس (از قالب 3.9.0). شماره @version
 * همان نسخه قالب اصلی است که این فایل بر پایه آن است؛ «ووکامرس ← وضعیت ← قالب‌ها»
 * با تغییر آن در آینده هشدار می‌دهد.
 *
 * تفاوت‌های عمدی با قالب اصلی (بقیه، از جمله شناسه‌ها، کلاس‌ها، متن‌ها و فرم، همان است):
 *   - عنوان همیشه از فیلتر woocommerce_reviews_title (inc/woocommerce/product-page.php:
 *     «تنظیمات قالب ← صفحه محصول ← نظرات کاربران»)، با نظر یا بی‌نظر؛ قبلا محصول
 *     بی‌نظر «Reviews» ثابت ووکامرس را می‌گرفت.
 *   - فهرست با hodima_comments_list(): بعد از چند نظر اول «نمایش همه نظرات» و برچسب
 *     «فروشگاه» (inc/comments.php). فرم همیشه دیدنی است (قبلا با فهرست در کادر
 *     جمع‌شونده افزونه Media بود).
 *   - (3.9.1) خلاصه امتیازها زیر عنوان: hodima_reviews_summary().
 *   - (3.9.2) فرم: «امتیاز شما» از بالای کادر متن به بعد از ایمیل، و ردیف آخر چک‌باکس «ذخیره
 *     نام و ایمیل در مرورگر» کنار دکمه «ثبت» (در خود HTML، نه با order در CSS، تا ترتیب Tab با
 *     ترتیب دیدن یکی باشد)؛ یادداشت «نشانی ایمیل شما منتشر نخواهد شد…» حذف (inc/enqueue.php).
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package Hodima\WooCommerce
 * @version 9.7.0
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- هوک‌های خود ووکامرس در قالب ووکامرس (باید همین نام‌ها اجرا شوند)
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- قالب داخل تابع (comments_template) لود می‌شود؛ متغیرها محلی‌اند، نه سراسری
// phpcs:disable WordPress.WP.I18n.TextDomainMismatch -- متن‌های خود ووکامرس با ترجمه فارسی ووکامرس (همان متن قالب اصلی)

global $product;

if ( ! comments_open() || ! $product instanceof WC_Product ) {
	return;
}

$count = $product->get_review_count();
?>
<div id="reviews" class="woocommerce-Reviews">
	<div id="comments">
		<h2 class="woocommerce-Reviews-title">
			<?php
			/* translators: 1: reviews count 2: product name */
			$reviews_title = $count && wc_review_ratings_enabled() ? sprintf( esc_html( _n( '%1$s review for %2$s', '%1$s reviews for %2$s', $count, 'woocommerce' ) ), esc_html( (string) $count ), '<span>' . esc_html( get_the_title() ) . '</span>' ) : esc_html__( 'Reviews', 'woocommerce' );
			echo wp_kses_post( (string) apply_filters( 'woocommerce_reviews_title', $reviews_title, $count, $product ) );
			?>
		</h2>

		<?php hodima_reviews_summary( $product ); ?>

		<?php if ( have_comments() ) : ?>
			<?php hodima_comments_list( (array) apply_filters( 'woocommerce_product_review_list_args', [ 'callback' => 'woocommerce_comments' ] ), 'commentlist', 'نظرات' ); ?>

			<?php
			if ( get_comment_pages_count() > 1 && get_option( 'page_comments' ) ) :
				echo '<nav class="woocommerce-pagination">';
				paginate_comments_links(
					apply_filters(
						'woocommerce_comment_pagination_args',
						[
							'prev_text' => is_rtl() ? '&rarr;' : '&larr;',
							'next_text' => is_rtl() ? '&larr;' : '&rarr;',
							'type'      => 'list',
						]
					)
				);
				echo '</nav>';
			endif;
			?>
		<?php else : ?>
			<p class="woocommerce-noreviews"><?php esc_html_e( 'There are no reviews yet.', 'woocommerce' ); ?></p>
		<?php endif; ?>
	</div>

	<?php if ( get_option( 'woocommerce_review_rating_verification_required' ) === 'no' || wc_customer_bought_product( '', get_current_user_id(), $product->get_id() ) ) : ?>
		<div id="review_form_wrapper">
			<div id="review_form">
				<?php
				$commenter    = wp_get_current_commenter();
				$comment_form = [
					/* translators: %s is product title */
					'title_reply'         => have_comments() ? esc_html__( 'Add a review', 'woocommerce' ) : sprintf( esc_html__( 'Be the first to review &ldquo;%s&rdquo;', 'woocommerce' ), get_the_title() ),
					/* translators: %s is product title */
					'title_reply_to'      => esc_html__( 'Leave a Reply to %s', 'woocommerce' ),
					'title_reply_before'  => '<span id="reply-title" class="comment-reply-title" role="heading" aria-level="3">',
					'title_reply_after'   => '</span>',
					'comment_notes_after' => '',
					'label_submit'        => esc_html__( 'Submit', 'woocommerce' ),
					'logged_in_as'        => '',
					'comment_field'       => '',
				];

				$name_email_required = (bool) get_option( 'require_name_email', 1 );
				$fields              = [
					'author' => [
						'label'        => __( 'Name', 'woocommerce' ),
						'type'         => 'text',
						'value'        => $commenter['comment_author'],
						'required'     => $name_email_required,
						'autocomplete' => 'name',
					],
					'email'  => [
						'label'        => __( 'Email', 'woocommerce' ),
						'type'         => 'email',
						'value'        => $commenter['comment_author_email'],
						'required'     => $name_email_required,
						'autocomplete' => 'email',
					],
				];

				$comment_form['fields'] = [];

				foreach ( $fields as $key => $field ) {
					$field_html  = '<p class="comment-form-' . esc_attr( $key ) . '">';
					$field_html .= '<label for="' . esc_attr( $key ) . '">' . esc_html( $field['label'] );

					if ( $field['required'] ) {
						$field_html .= '&nbsp;<span class="required">*</span>';
					}

					$field_html .= '</label><input id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" type="' . esc_attr( $field['type'] ) . '" autocomplete="' . esc_attr( $field['autocomplete'] ) . '" value="' . esc_attr( $field['value'] ) . '" size="30" ' . ( $field['required'] ? 'required' : '' ) . ' /></p>';

					$comment_form['fields'][ $key ] = $field_html;
				}

				$account_page_url = wc_get_page_permalink( 'myaccount' );
				if ( $account_page_url ) {
					/* translators: %s opening and closing link tags respectively */
					$comment_form['must_log_in'] = '<p class="must-log-in">' . sprintf( esc_html__( 'You must be %1$slogged in%2$s to post a review.', 'woocommerce' ), '<a href="' . esc_url( $account_page_url ) . '">', '</a>' ) . '</p>';
				}

				// همان چک‌باکس وردپرس (همان name و شرط)، با متن کوتاه‌تر؛ جایش ردیف آخر کنار «ثبت» (submit_field
				// پایین). کلید خالی در fields لازم است، وگرنه comment_form() نسخه خودش را بعد از ایمیل اضافه می‌کند.
				$cookies_field = '';
				if ( get_option( 'show_comments_cookies_opt_in' ) ) {
					$cookies_field = '<p class="comment-form-cookies-consent"><input id="wp-comment-cookies-consent" name="wp-comment-cookies-consent" type="checkbox" value="yes"' . ( '' === $commenter['comment_author_email'] ? '' : ' checked="checked"' ) . ' /> <label for="wp-comment-cookies-consent">ذخیره نام و ایمیل در مرورگر</label></p>';
				}
				$comment_form['fields']['cookies'] = '';

				$rating_field = '';
				if ( wc_review_ratings_enabled() ) {
					$rating_field = '<div class="comment-form-rating"><label for="rating" id="comment-form-rating-label">' . esc_html__( 'Your rating', 'woocommerce' ) . ( wc_review_ratings_required() ? '&nbsp;<span class="required">*</span>' : '' ) . '</label><select name="rating" id="rating" required>
						<option value="">' . esc_html__( 'Rate&hellip;', 'woocommerce' ) . '</option>
						<option value="5">' . esc_html__( 'Perfect', 'woocommerce' ) . '</option>
						<option value="4">' . esc_html__( 'Good', 'woocommerce' ) . '</option>
						<option value="3">' . esc_html__( 'Average', 'woocommerce' ) . '</option>
						<option value="2">' . esc_html__( 'Not that bad', 'woocommerce' ) . '</option>
						<option value="1">' . esc_html__( 'Very poor', 'woocommerce' ) . '</option>
					</select></div>';
				}

				// بعد از ایمیل: امتیاز؛ ردیف آخر: چک‌باکس کنار دکمه ثبت (%1$s دکمه، %2$s فیلدهای پنهان وردپرس)
				$comment_form['submit_field'] = str_replace( '%', '%%', $rating_field ) . '<div class="hodima-review-submit">' . str_replace( '%', '%%', $cookies_field ) . '<p class="form-submit">%1$s %2$s</p></div>';

				$comment_form['comment_field'] .= '<p class="comment-form-comment"><label for="comment">' . esc_html__( 'Your review', 'woocommerce' ) . '&nbsp;<span class="required">*</span></label><textarea id="comment" name="comment" cols="45" rows="8" required></textarea></p>';

				comment_form( apply_filters( 'woocommerce_product_review_comment_form_args', $comment_form ) );
				?>
			</div>
		</div>
	<?php else : ?>
		<p class="woocommerce-verification-required"><?php esc_html_e( 'Only logged in customers who have purchased this product may leave a review.', 'woocommerce' ); ?></p>
	<?php endif; ?>

	<div class="clear"></div>
</div>
