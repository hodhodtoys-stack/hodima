<?php
/**
 * The template to display the reviewers meta data (name, verified owner, review date)
 *
 * بازنویسی قالب single-product/review-meta.php ووکامرس (از قالب 3.9.0) فقط برای برچسب
 * «فروشگاه» کنار نام پاسخ‌دهنده‌ای که از کارکنان فروشگاه است (hodima_comment_badge در
 * inc/comments.php)؛ بقیه همان قالب اصلی. شماره @version همان نسخه قالب اصلی است.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package Hodima\WooCommerce
 * @version 3.4.0
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- قالب داخل تابع (wc_get_template) لود می‌شود؛ متغیرها محلی‌اند، نه سراسری
// phpcs:disable WordPress.WP.I18n.TextDomainMismatch -- متن‌های خود ووکامرس با ترجمه فارسی ووکامرس (همان متن قالب اصلی)

global $comment;

if ( ! $comment instanceof WP_Comment ) {
	return;
}

$verified = wc_review_is_from_verified_owner( (int) $comment->comment_ID );

if ( '0' === $comment->comment_approved ) { ?>

	<p class="meta">
		<em class="woocommerce-review__awaiting-approval">
			<?php esc_html_e( 'Your review is awaiting approval', 'woocommerce' ); ?>
		</em>
	</p>

<?php } else { ?>

	<p class="meta">
		<strong class="woocommerce-review__author"><?php comment_author(); ?> </strong>
		<?php
		echo wp_kses_post( hodima_comment_badge( $comment ) );

		if ( 'yes' === get_option( 'woocommerce_review_rating_verification_label' ) && $verified ) {
			echo '<em class="woocommerce-review__verified verified">(' . esc_attr__( 'verified owner', 'woocommerce' ) . ')</em> ';
		}

		?>
		<span class="woocommerce-review__dash">&ndash;</span> <time class="woocommerce-review__published-date" datetime="<?php echo esc_attr( get_comment_date( 'c' ) ); ?>"><?php echo esc_html( get_comment_date( wc_date_format() ) ); ?></time>
	</p>

	<?php
}
