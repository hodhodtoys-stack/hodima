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

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- قالب داخل تابع (load_template/wc_get_template_part) لود می‌شود؛ متغیرها محلی‌اند، نه سراسری

if ( ! taxonomy_exists( 'product_cat' ) ) {
	return;
}

$args     = wp_parse_args( $args ?? [], hodima_home_section_defaults( 'categories' ) );
$excluded = array_map( 'strval', (array) $args['exclude'] );

$categories_data = array_values( array_filter(
	hodima_home_categories_data(),
	static fn( array $category ): bool => ! in_array( $category['slug'], $excluded, true )
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
		<?php foreach ( $categories_data as $category ) : ?>
			<a href="<?php echo esc_url( $category['link'] ); ?>" class="cat-story-item" aria-label="<?php echo esc_attr( $category['name'] ); ?>">
				<div class="cat-img-box">
					<?php
					if ( ! empty( $category['thumbnail_id'] ) ) {
						echo wp_get_attachment_image( (int) $category['thumbnail_id'], 'woocommerce_thumbnail', false, [
							'alt'      => $category['name'],
							'loading'  => 'lazy',
							'decoding' => 'async',
						] );
					} elseif ( function_exists( 'wc_placeholder_img_src' ) ) {
						echo '<img src="' . esc_url( wc_placeholder_img_src() ) . '" alt="' . esc_attr( $category['name'] ) . '" width="150" height="150" loading="lazy" decoding="async">';
					}
					?>
				</div>
				<span class="cat-story-name"><?php echo esc_html( $category['name'] ); ?></span>
			</a>
		<?php endforeach; ?>
	</div>
</section>
