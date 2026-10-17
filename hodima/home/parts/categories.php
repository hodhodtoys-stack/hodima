<?php
/**
 * بخش صفحه اصلی: دسته‌بندی کالاها (استوری‌وار، با تصویر دسته)
 * Path: home/parts/categories.php
 *
 * فراخوانی: get_template_part( 'home/parts/categories', null, $args ) — از
 * چیدمان «تنظیمات قالب ← صفحه اصلی» یا شورت‌کد قدیمی [section03].
 *
 * @var array{title?:string, subtitle?:string, mode?:string, exclude?:list<string>, order?:string, limit?:int} $args
 *      exclude = نامک دسته‌های تیک‌خورده (معنی: mode = exclude «به‌جز» / include «فقط»)
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- قالب داخل تابع (load_template/wc_get_template_part) لود می‌شود؛ متغیرها محلی‌اند، نه سراسری

if ( ! taxonomy_exists( 'product_cat' ) ) {
	return;
}

$args     = wp_parse_args( $args ?? [], hodima_home_section_defaults( 'categories' ) );
$selected = array_map( 'strval', (array) $args['exclude'] ); // نام کلید ذخیره‌شده؛ معنایش را «حالت» تعیین می‌کند
$only     = 'include' === $args['mode'];

// حالت «به‌جز»: همه دسته‌های موجود مگر تیک‌خورده‌ها؛ حالت «فقط»: همان تیک‌خورده‌ها (دسته تازه خودکار اضافه نمی‌شود)
$categories_data = array_values( array_filter(
	hodima_home_categories_data(),
	static fn( array $category ): bool => $only === in_array( $category['slug'], $selected, true )
) );

if ( 'count' === $args['order'] ) {
	usort( $categories_data, static fn( array $a, array $b ): int => ( $b['count'] ?? 0 ) <=> ( $a['count'] ?? 0 ) );
}

if ( (int) $args['limit'] > 0 ) {
	$categories_data = array_slice( $categories_data, 0, (int) $args['limit'] );
}

if ( ! $categories_data ) {
	return;
}

$section_id = 'hodima-cat-title-' . wp_unique_id();
?>
<section class="hodima-home-section cat-story-container" aria-labelledby="<?php echo esc_attr( $section_id ); ?>" data-nosnippet>
	<header class="hodima-home-header">
		<div class="hodima-title-group">
			<h2 id="<?php echo esc_attr( $section_id ); ?>" class="hodima-section-title"><?php echo esc_html( $args['title'] ); ?></h2>
			<?php if ( '' !== (string) $args['subtitle'] ) : ?>
				<p class="hodima-section-subtitle"><?php echo esc_html( $args['subtitle'] ); ?></p>
			<?php endif; ?>
			<div class="hodima-title-line"></div>
		</div>
	</header>
	<div class="hodima-scroller cat-story-wrapper">
		<?php foreach ( $categories_data as $category ) : ?>
			<a href="<?php echo esc_url( $category['link'] ); ?>" class="cat-story-item" aria-label="<?php echo esc_attr( $category['name'] ); ?>">
				<div class="cat-img-box">
					<?php
					if ( ! empty( $category['thumbnail_id'] ) ) {
						// مربع ۸۰px (۷۲ در موبایل): نسخه ۱۵۰px کافی است؛ تا 3.1.4 نسخه ۳۰۰px گرفته می‌شد
						echo wp_get_attachment_image( (int) $category['thumbnail_id'], 'thumbnail', false, [
							'alt'      => $category['name'],
							'loading'  => 'lazy',
							'decoding' => 'async',
							'sizes'    => '(max-width: 768px) 72px, 80px',
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
