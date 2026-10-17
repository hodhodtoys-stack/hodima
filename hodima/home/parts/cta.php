<?php
/**
 * بخش صفحه اصلی: دعوت به اقدام (عنوان، متن کوتاه و دکمه)
 * Path: home/parts/cta.php
 *
 * @var array{title?:string, text?:string, button_text?:string, link?:string} $args
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- قالب داخل تابع لود می‌شود؛ متغیرها محلی‌اند

$args = wp_parse_args( $args ?? [], hodima_home_section_defaults( 'cta' ) );

if ( '' === trim( (string) $args['title'] ) ) {
	return;
}

$button_url = hodima_home_link_url( (string) $args['link'] );
?>
<section class="hodima-home-section hodima-cta">
	<div class="hodima-cta__box">
		<h2 class="hodima-cta__title"><?php echo esc_html( (string) $args['title'] ); ?></h2>
		<?php if ( '' !== (string) $args['text'] ) : ?>
			<p class="hodima-cta__text"><?php echo esc_html( (string) $args['text'] ); ?></p>
		<?php endif; ?>
		<?php if ( '' !== $button_url && '' !== (string) $args['button_text'] ) : ?>
			<a class="hodima-cta__button" href="<?php echo esc_url( $button_url ); ?>"><?php echo esc_html( (string) $args['button_text'] ); ?></a>
		<?php endif; ?>
	</div>
</section>
