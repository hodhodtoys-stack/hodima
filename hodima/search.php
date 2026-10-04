<?php
/**
 * نتایج جستجوی سایت (نوشته، برگه، محصول، …)
 * Path: hodima/search.php
 *
 * بازسازی قالب، مرحله ۵. قبلا جستجو با index.php نمایش داده می‌شد: عنوانی
 * برای خود صفحه نبود و هر نتیجه یک H1 جدا داشت (ده‌ها H1)، متن کامل هر
 * نتیجه چاپ می‌شد و پیام «یافت نشد» فرم جستجوی دوباره نداشت.
 * جستجوی فقط محصول (?post_type=product) همچنان قالب فروشگاه ووکامرس است.
 * کارت‌ها همان کارت‌های آرشیو وبلاگ (assets/css/archive-blog.css).
 */

defined( 'ABSPATH' ) || exit;

global $wp_query;

$hodima_query = get_search_query();
$hodima_paged = max( 1, (int) get_query_var( 'paged' ) );
$hodima_total = (int) $wp_query->found_posts;

get_header();
?>

<main id="primary" class="hodima-page-wrapper hodima-search">

	<section class="blog-page-section">
		<div class="blog-page-container">

			<header class="blog-archive-header">
				<h1 class="blog-archive-title">
					<?php echo esc_html( sprintf( 'نتایج جستجو برای «%s»', $hodima_query ) ); ?>
					<?php if ( $hodima_paged > 1 ) : ?>
						<span class="blog-archive-page">— صفحه <?php echo (int) $hodima_paged; ?></span>
					<?php endif; ?>
				</h1>
				<?php if ( $hodima_total > 0 ) : ?>
					<p class="blog-archive-desc"><?php echo esc_html( sprintf( '%s نتیجه', number_format_i18n( $hodima_total ) ) ); ?></p>
				<?php endif; ?>
			</header>

			<?php if ( have_posts() ) : ?>

				<div class="blog-page-grid">
					<?php while ( have_posts() ) : the_post(); ?>
						<a href="<?php the_permalink(); ?>" class="blog-page-card">
							<?php if ( has_post_thumbnail() ) : ?>
								<?php the_post_thumbnail( 'medium', [ 'class' => 'blog-page-thumbnail', 'alt' => get_the_title(), 'loading' => 'lazy' ] ); ?>
							<?php else : ?>
								<div class="blog-page-thumbnail blog-page-thumbnail--empty"><span><?php echo esc_html( get_post_type_object( get_post_type() )->labels->singular_name ?? '' ); ?></span></div>
							<?php endif; ?>

							<div class="blog-page-content">
								<h2 class="blog-page-title"><?php the_title(); ?></h2>
								<div class="blog-page-meta">
									<span class="blog-date"><?php echo esc_html( get_post_type_object( get_post_type() )->labels->singular_name ?? '' ); ?></span>
								</div>
							</div>
						</a>
					<?php endwhile; ?>
				</div>

				<?php
				$hodima_links = paginate_links( [
					'total'     => (int) $wp_query->max_num_pages,
					'current'   => $hodima_paged,
					'mid_size'  => 1,
					'prev_text' => 'قبلی',
					'next_text' => 'بعدی',
				] );
				?>
				<?php if ( $hodima_links ) : ?>
					<nav class="hodima-pagination" aria-label="صفحه‌بندی نتایج جستجو"><?php echo wp_kses_post( $hodima_links ); ?></nav>
				<?php endif; ?>

			<?php else : ?>

				<div class="hodima-search__empty">
					<p>نتیجه‌ای پیدا نشد. با کلمه دیگری جستجو کنید:</p>
					<?php get_search_form(); ?>
				</div>

			<?php endif; ?>

		</div>
	</section>
</main>

<?php
get_footer();
