<?php
/**
 * بخش صفحه اصلی: دسته‌بندی کالاها (استوری‌وار، با تصویر دسته)
 * Path: home/parts/categories.php
 *
 * فراخوانی: get_template_part( 'home/parts/categories', null, $args ) — از
 * چیدمان «تنظیمات قالب ← صفحه اصلی» یا شورت‌کد قدیمی [section03].
 *
 * @var array{title?:string, exclude?:list<string>} $args  exclude = نامک دسته‌ها
 */

defined( 'ABSPATH' ) || exit;

if ( ! taxonomy_exists( 'product_cat' ) ) {
	return;
}

$args     = wp_parse_args( $args ?? [], hodima_home_section_defaults( 'categories' ) );
$excluded = array_map( 'strval', (array) $args['exclude'] );

$categories_data = array_values( array_filter(
	hodima_home_categories_data(),
	static fn( array $cat ): bool => ! in_array( $cat['slug'], $excluded, true )
) );

if ( ! $categories_data ) {
	return;
}

$section_id = 'arian-cat-title-' . wp_unique_id();
?>
<section class="arian-section cat-story-container" aria-labelledby="<?php echo esc_attr( $section_id ); ?>" data-nosnippet>
	<header class="arian-header">
		<div class="arian-title-group">
			<h2 id="<?php echo esc_attr( $section_id ); ?>" class="arian-title"><?php echo esc_html( $args['title'] ); ?></h2>
			<div class="arian-line"></div>
		</div>
	</header>
	<div class="arian-scroller cat-story-wrapper">
		<?php foreach ( $categories_data as $cat ) : ?>
			<a href="<?php echo esc_url( $cat['link'] ); ?>" class="cat-story-item" aria-label="<?php echo esc_attr( $cat['name'] ); ?>">
				<div class="cat-img-box">
					<?php
					if ( ! empty( $cat['thumbnail_id'] ) ) {
						echo wp_get_attachment_image( (int) $cat['thumbnail_id'], 'woocommerce_thumbnail', false, [
							'alt'      => $cat['name'],
							'loading'  => 'lazy',
							'decoding' => 'async',
						] );
					} elseif ( function_exists( 'wc_placeholder_img_src' ) ) {
						echo '<img src="' . esc_url( wc_placeholder_img_src() ) . '" alt="' . esc_attr( $cat['name'] ) . '" width="150" height="150" loading="lazy" decoding="async">';
					}
					?>
				</div>
				<span class="cat-story-name"><?php echo esc_html( $cat['name'] ); ?></span>
			</a>
		<?php endforeach; ?>
	</div>
</section>
