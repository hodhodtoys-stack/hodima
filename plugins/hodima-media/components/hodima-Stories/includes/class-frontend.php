<?php
/**
 * استوری‌ها — شورت‌کد [hodima-Stories] (نوار استوری سایت).
 *
 * @package Hodima_Media
 */

declare(strict_types=1);

namespace Hodima\Stories;

use Hodima\Stories\Analytics\WP_Story_Analytics;

defined( 'ABSPATH' ) || exit;

/**
 * نمایش نوار استوری.
 */
class Frontend {

	/** هوک‌ها. */
	public static function init(): void {
		add_action( 'wp_enqueue_scripts', [ self::class, 'register_scripts' ] );
		add_shortcode( 'hodima-Stories', [ self::class, 'render_shortcode' ] );
	}

	/** ثبت فایل‌ها (فقط وقتی شورت‌کد اجرا شد بارگذاری می‌شوند). */
	public static function register_scripts(): void {
		[ $css_url, $css_ver ] = Core::asset( 'hodima-front.css' );
		[ $js_url, $js_ver ]   = Core::asset( 'hodima-front.js' );
		wp_register_style( 'hodima-stories-front', $css_url, [], $css_ver );
		wp_register_script(
			'hodima-stories-front',
			$js_url,
			[],
			$js_ver,
			[
				'in_footer' => true,
				'strategy'  => 'defer',
			]
		);
	}

	/**
	 * خروجی شورت‌کد.
	 *
	 * ترتیب = ترتیب پنل (shuffle قبلی با کش کامل صفحه یک ترتیب «تصادفی»
	 * ثابت می‌ساخت). استوری با لینک نامعتبر یا ویدیوی تکراری نمایش داده
	 * نمی‌شود (پنل برایش هشدار نشان می‌دهد).
	 *
	 * @param array<string, string>|string $atts ویژگی‌های شورت‌کد (استفاده نمی‌شود).
	 */
	public static function render_shortcode( array|string $atts = [] ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- امضای شورت‌کد
		wp_enqueue_style( 'hodima-stories-front' );
		wp_enqueue_script( 'hodima-stories-front' );
		StoryPlayer\Core::enable();

		$items    = Core::items();
		$problems = Helpers::problems( $items );
		$items    = array_values( array_diff_key( $items, $problems ) );
		if ( ! $items ) {
			return '';
		}

		$db_stats    = class_exists( WP_Story_Analytics::class ) ? WP_Story_Analytics::get_stats_bulk( array_column( $items, 'uid' ) ) : [];
		$seen_to_end = Core::settings()['seen_to_end'];
		$thumb_size  = function_exists( 'wc_get_image_size' ) ? 'woocommerce_thumbnail' : 'thumbnail';

		ob_start();
		/*
		 * کلاس is-ready از ابتدا: نوار تا اجرای JS پنهان نمی‌ماند (LCP، و
		 * Delay JS لایت‌اسپید که نوار را هرگز نشان نمی‌داد).
		 * نشانگر فلش «استوری‌های بیشتر» به خواست کاربر حذف شد (۱۴۰۵).
		 * شناسه ثابت story-container حذف شد: با دو شورت‌کد در یک صفحه تکراری می‌شد.
		 */
		?>
		<section class="arian-section section-stories is-ready" data-nosnippet data-seen-to-end="<?php echo $seen_to_end ? '1' : '0'; ?>">
			<div class="arian-scroller story-wrapper">
				<?php
				foreach ( $items as $position => $story ) {
					$uid      = absint( $story['uid'] ?? 0 );
					$title    = (string) ( $story['title'] ?? '' );
					$link     = (string) ( $story['link'] ?? '' );
					$cover_id = Helpers::validate_cover_id( $story['cover_id'] ?? 0 );
					$cta_link = (string) ( $story['cta_link'] ?? '' );
					$cta_text = (string) ( $story['cta_text'] ?? '' );
					$label    = '' !== $title ? $title : __( 'مشاهده', 'hodima' );
					// چند کاور اول در دید اولیه‌اند؛ lazy آن‌ها را دیرتر از لازم نمایش می‌داد
					$eager = $position < 6;
					?>
					<div class="story-item">
						<a href="<?php echo esc_url( $link ); ?>" class="story-link" rel="nofollow noopener"
							data-video="<?php echo esc_url( $link ); ?>"
							data-poster="<?php echo Helpers::esc_img_src( Helpers::get_cover_url( $cover_id, 'large' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_url در esc_img_src ?>"
							data-story-id="<?php echo esc_attr( (string) $uid ); ?>"
							data-views="<?php echo esc_attr( (string) ( $db_stats[ $uid ]['views'] ?? 0 ) ); ?>"
							data-title="<?php echo esc_attr( $title ); ?>"
							<?php if ( '' !== $cta_link ) : ?>
							data-cta-link="<?php echo esc_url( $cta_link ); ?>"
							data-cta-text="<?php echo esc_attr( $cta_text ); ?>"
							<?php endif; ?>>
							<div class="story-ring">
								<img src="<?php echo Helpers::esc_img_src( Helpers::get_cover_url( $cover_id, $thumb_size ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- esc_url در esc_img_src ?>" alt="<?php echo esc_attr( $label ); ?>" loading="<?php echo $eager ? 'eager' : 'lazy'; ?>" width="75" height="75" decoding="async">
							</div>
							<span class="story-text"><?php echo esc_html( $label ); ?></span>
						</a>
					</div>
					<?php
				}
				?>
			</div>
		</section>
		<?php
		return (string) ob_get_clean();
	}
}
