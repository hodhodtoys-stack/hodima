<?php
/**
 * بخش «پادکست + سوالات متداول» (کادر رسانه افزونه Hodima Media)
 * Path: template-parts/media/voice-faq.php
 *
 * فراخوانی: get_template_part( 'template-parts/media/voice-faq', null, $args )
 * از single-post.php، page.php و woocommerce/taxonomy-product_cat.php.
 * هر دو خالی = بدون بخش. HTML هر چیدمان همان HTML قبلی همان قالب است:
 *   post     — مقاله و برگه (single-post.css)؛
 *   category — دسته محصول (taxonomy-product_cat.css).
 * محصول پادکست را کنار دکمه خرید و FAQ را داخل توضیحات می‌گذارد (چیدمان
 * دیگر)، پس فقط hodima_theme_media_html() را به کار می‌برد.
 *
 * @var array{layout?:string, voice?:string, faq?:string} $args
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

$hodima_args = wp_parse_args(
	$args ?? [],
	[
		'layout' => 'post',
		'voice'  => '',
		'faq'    => '',
	]
);

$hodima_voice = (string) $hodima_args['voice'];
$hodima_faq   = (string) $hodima_args['faq'];

if ( '' === $hodima_voice . $hodima_faq ) {
	return;
}

$hodima_category = 'category' === $hodima_args['layout'];
?>
<?php if ( $hodima_category ) : ?>
	<section class="hodima-section-box section-voice section-faq" aria-label="پادکست و سوالات متداول">
<?php else : ?>
	<section class="hodima-section-box section-voice-faq">
		<div class="voice-faq-wrapper">
<?php endif; ?>
		<?php if ( '' !== $hodima_voice ) : ?>
			<div class="<?php echo $hodima_category ? 'voice-inner-wrapper' : 'voice-content'; ?>"><?php echo $hodima_voice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- خروجی افزونه Hodima Media ?></div>
		<?php endif; ?>
		<?php if ( '' !== $hodima_faq ) : ?>
			<div class="<?php echo $hodima_category ? 'faq-inner-wrapper' : 'faq-content'; ?>"><?php echo $hodima_faq; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- خروجی افزونه Hodima Media ?></div>
		<?php endif; ?>
<?php if ( ! $hodima_category ) : ?>
		</div>
<?php endif; ?>
	</section>
