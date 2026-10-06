<?php
/**
 * برگه‌ها (درباره ما، تماس، راهنما، …)
 * Path: hodima/page.php
 *
 * بازسازی قالب، مرحله ۵. قبلا برگه‌ها با index.php نمایش داده می‌شدند:
 *   - بدون H1 (عنوان برگه هیچ‌جا چاپ نمی‌شد)؛
 *   - کادر «رسانه» برگه (معرفی، ویدیو، پادکست، سوالات متداول) نمایش داده
 *     نمی‌شد، در حالی که اسکیمای FAQPage/VideoObject/AudioObject همان برگه
 *     چاپ می‌شد — داده ساختاریافته برای محتوای نادیده، خلاف راهنمای گوگل.
 * حالا: H1 (مگر متن برگه خودش H1 داشته باشد)، بخش‌های رسانه مثل مقاله‌ها
 * (همان کلاس‌ها و single-post.css)، و استایل‌های inline به style.css رفت.
 *
 * صفحه اصلی (بدون چیدمان «تنظیمات قالب ← صفحه اصلی») عمدا مثل قبل از
 * index.php: H1 و ویدیو آن از شورت‌کدهای داخل متن برگه می‌آید.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

if ( is_front_page() ) {
	require __DIR__ . '/index.php';
	return;
}

get_header();

while ( have_posts() ) :
	the_post();

	$hodima_page_id = (int) get_the_ID();

	ob_start();
	the_content();
	$hodima_content = (string) ob_get_clean();

	// سبد خرید، تسویه‌حساب و حساب کاربری ووکامرس عنوان جدا نمی‌گیرند (طراحی خودشان)
	$hodima_wc_page = function_exists( 'is_cart' ) && ( is_cart() || is_checkout() || is_account_page() );
	$hodima_has_h1  = $hodima_wc_page || (bool) preg_match( '/<h1[\s>]/i', $hodima_content );

	// کادر «رسانه» برگه (افزونه Hodima Media)
	$hodima_media = function_exists( 'hodima_media_is_enabled' ) && hodima_media_is_enabled( $hodima_page_id, 'post' );
	$hodima_atts  = [ 'id' => $hodima_page_id, 'context' => 'post' ];
	$hodima_intro = $hodima_media ? hodima_theme_media_html( 'intro', $hodima_atts ) : '';
	$hodima_video = $hodima_media ? hodima_theme_media_html( 'video', $hodima_atts ) : '';
	$hodima_voice = $hodima_media ? hodima_theme_media_html( 'voice', $hodima_atts ) : '';
	$hodima_faq   = $hodima_media ? hodima_theme_media_html( 'faq', $hodima_atts ) : '';
	?>

	<main id="primary" class="site-main hodima-page">
		<div class="hodima-container hodima-page__container">
			<article id="post-<?php the_ID(); ?>" <?php post_class( 'hodima-page__article' ); ?>>

				<?php if ( ! $hodima_has_h1 ) : ?>
					<header class="entry-header">
						<?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
					</header>
				<?php endif; ?>

				<?php
				// معرفی + ویدیو و پادکست + FAQ: همان بخش‌های مقاله (template-parts/media/)
				get_template_part( 'template-parts/media/intro-media', null, [ 'layout' => 'page', 'intro' => $hodima_intro, 'video' => $hodima_video ] );
				?>

				<div class="entry-content">
					<?php echo $hodima_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- خروجی the_content ?>
				</div>

				<?php get_template_part( 'template-parts/media/voice-faq', null, [ 'layout' => 'post', 'voice' => $hodima_voice, 'faq' => $hodima_faq ] ); ?>

				<?php
				wp_link_pages( [
					'before' => '<nav class="hodima-pagination" aria-label="صفحه‌های این برگه">',
					'after'  => '</nav>',
				] );
				?>
			</article>

			<?php if ( comments_open() || get_comments_number() ) : ?>
				<section class="hodima-section-box section-comments">
					<div class="comments-wrapper"><?php comments_template(); ?></div>
				</section>
			<?php endif; ?>
		</div>
	</main>

	<?php
endwhile;

get_footer();
