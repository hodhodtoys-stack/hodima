<?php
/**
 * بخش صفحه اصلی: آخرین مقالات وبلاگ («نبض بازار»)
 * Path: home/parts/blog.php
 *
 * فراخوانی: get_template_part( 'home/parts/blog', null, $args ) — از چیدمان
 * «تنظیمات قالب ← صفحه اصلی» یا شورت‌کد قدیمی [section09].
 * تصویر شاخص با شناسه کش می‌شود (srcset و WebP)؛ مقاله بدون تصویر کادر
 * جایگزین با رنگ برند می‌گیرد.
 *
 * @var array{title?:string, limit?:int, more_text?:string} $args
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- قالب داخل تابع (load_template/wc_get_template_part) لود می‌شود؛ متغیرها محلی‌اند، نه سراسری

$args       = wp_parse_args( $args ?? [], hodima_home_section_defaults( 'blog' ) );
$posts_data = array_slice( hodima_home_blog_posts_data(), 0, max( 1, (int) $args['limit'] ) );

if ( ! $posts_data ) {
	return;
}

// لینک صفحه بلاگ: برگه «نوشته‌ها» در «تنظیمات ← خواندن»
$blog_page_id = (int) get_option( 'page_for_posts' );
$blog_url     = $blog_page_id ? (string) get_permalink( $blog_page_id ) : home_url( '/blog/' );
$section_id   = 'arian-blog-title-' . wp_unique_id();
?>
<section class="arian-section section-blog" aria-labelledby="<?php echo esc_attr( $section_id ); ?>">
	<div class="arian-header">
		<div class="arian-title-group">
			<h2 id="<?php echo esc_attr( $section_id ); ?>" class="arian-title">
				<a href="<?php echo esc_url( $blog_url ); ?>"><?php echo esc_html( $args['title'] ); ?></a>
			</h2>
			<div class="arian-line"></div>
		</div>

		<?php if ( '' !== (string) $args['more_text'] ) : ?>
			<a href="<?php echo esc_url( $blog_url ); ?>" class="arian-view-all"><?php echo esc_html( $args['more_text'] ); ?></a>
		<?php endif; ?>
	</div>

	<div class="arian-scroller blog-wrapper">
		<?php foreach ( $posts_data as $index => $post_item ) : ?>
			<article class="modern-blog-card">
				<a href="<?php echo esc_url( $post_item['link'] ); ?>" class="blog-card-img-link">
					<?php if ( ! empty( $post_item['thumbnail_id'] ) ) : ?>
						<?php
						echo wp_get_attachment_image( (int) $post_item['thumbnail_id'], 'medium_large', false, [
							'alt'      => $post_item['title'],
							'decoding' => 'async',
							...( 0 === $index ? [ 'fetchpriority' => 'high' ] : [ 'loading' => 'lazy' ] ),
						] );
						?>
					<?php else : ?>
						<?php // مقاله بدون تصویر شاخص: کادر جایگزین با رنگ برند (فایل جایگزین در قالب نیست) ?>
						<span class="blog-card-img-placeholder" role="img" aria-label="<?php echo esc_attr( $post_item['title'] ); ?>">
							<svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h4"/></svg>
						</span>
					<?php endif; ?>
				</a>

				<div class="blog-card-info">
					<h3 class="blog-title">
						<a href="<?php echo esc_url( $post_item['link'] ); ?>"><?php echo esc_html( $post_item['title'] ); ?></a>
					</h3>
					<a href="<?php echo esc_url( $post_item['link'] ); ?>" class="read-more-btn" aria-label="<?php echo esc_attr( 'مطالعه مقاله ' . $post_item['title'] ); ?>">مشاهده</a>
				</div>
			</article>
		<?php endforeach; ?>
	</div>
</section>
