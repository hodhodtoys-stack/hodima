<?php
/**
 * بخش صفحه اصلی: مزایا (کاشی‌های کوتاه)
 * Path: home/parts/features.php
 *
 * هر مورد «عنوان | توضیح اختیاری» است. مستقل از ستون وسط بخش «معرفی و ویدیو».
 *
 * @var array{title?:string, subtitle?:string, items?:array<int, string>, tone?:string} $args
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- قالب داخل تابع لود می‌شود؛ متغیرها محلی‌اند

$args  = wp_parse_args( $args ?? [], hodima_home_section_defaults( 'features' ) );
$items = array_slice( array_filter( (array) $args['items'] ), 0, 12 );

if ( ! $items ) {
	return;
}

$section_id = wp_unique_id( 'hodima-features-title-' );
?>
<section class="hodima-home-section hodima-features" aria-labelledby="<?php echo esc_attr( $section_id ); ?>" style="--section-bg: <?php echo esc_attr( hodima_home_tones()[ $args['tone'] ]['css'] ?? 'transparent' ); ?>;">
	<div class="hodima-home-header">
		<div class="hodima-title-group">
			<h2 id="<?php echo esc_attr( $section_id ); ?>" class="hodima-section-title"><?php echo esc_html( (string) $args['title'] ); ?></h2>
			<?php if ( '' !== (string) $args['subtitle'] ) : ?>
				<p class="hodima-section-subtitle"><?php echo esc_html( (string) $args['subtitle'] ); ?></p>
			<?php endif; ?>
			<div class="hodima-title-line"></div>
		</div>
	</div>
	<ul class="hodima-features__grid">
		<?php foreach ( $items as $item ) :
			[ $head, $text ] = array_pad( array_map( 'trim', explode( '|', (string) $item, 2 ) ), 2, '' );
			?>
			<li class="hodima-features__item">
				<strong class="hodima-features__title"><?php echo esc_html( $head ); ?></strong>
				<?php if ( '' !== $text ) : ?>
					<span class="hodima-features__text"><?php echo esc_html( $text ); ?></span>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ul>
</section>
