<?php
/**
 * Template: taxonomy-product_cat
 *
 * بازنویسی قالب taxonomy-product-cat.php ووکامرس (که فقط archive-product.php
 * را صدا می‌زند). ووکامرس هر دو نام (با _ و -) را جستجو می‌کند.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package Hodima\WooCommerce
 * @version 4.7.0
 *
 * هوک‌های پیش از کوئری (تعداد در صفحه، عنوان صفحات بعدی، اعداد انگلیسی
 * صفحه‌بندی، اسکیمای صفحه اول) در inc/woocommerce/category-archive.php هستند.
 */

defined( 'ABSPATH' ) || exit;

$hodima_paged    = max( 1, (int) get_query_var( 'paged' ) );
$hodima_is_first = ( 1 === $hodima_paged );
$hodima_term     = get_queried_object();
$hodima_term_id  = $hodima_term instanceof WP_Term ? (int) $hodima_term->term_id : 0;

/*
 * wrapper پیش‌فرض ووکامرس حذف می‌شود.
 *
 * نسخه قبلی do_action('woocommerce_before_main_content') را *داخل* بخش
 * مسیر راهنما صدا می‌زد. ووکامرس روی این هوک <div id="primary"><main>
 * باز می‌کند (قالب wrapper-start را بازنویسی نکرده بود) و روی
 * woocommerce_after_main_content در انتهای صفحه </main></div> می‌بست:
 *
 *   <section breadcrumb> <div><main> … </section>   ← مرورگر به زور می‌بندد
 *   <main محصولات> … </main>                          ← main دوم
 *   … </main></div>                                  ← تگ‌های سرگردان
 *
 * دو عنصر <main> و یک </div> سرگردان که می‌توانست پوشش صفحه را زودتر
 * ببندد. مرورگر بی‌صدا تعمیرش می‌کرد ولی موتور جستجو و صفحه‌خوان ساختار
 * شکسته می‌دیدند. مسیر راهنما حالا مستقیم چاپ می‌شود.
 */
remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );
remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );

get_header( 'shop' );
?>

<div class="hodima-page-wrapper">

	<?php do_action( 'woocommerce_before_main_content' ); ?>

	<?php // woocommerce_breadcrumb() خودش <nav aria-label> چاپ می‌کند؛ اینجا فقط ظرف ?>
	<div class="hodima-section-box section-breadcrumb">
		<?php woocommerce_breadcrumb(); ?>
	</div>

	<?php
	/*
	 * بخش معرفی و ویدیو فقط در صفحه اول.
	 *
	 * قبلا معرفی، ویدیو، توضیحات، صوت، FAQ و باکس هوش مصنوعی روی
	 * /cat/page/2/ و /cat/page/3/ عینا تکرار می‌شدند — چند صفحه با محتوای
	 * متنی یکسان که برای گوگل رقیب صفحه اول می‌شدند. صفحات بعدی فقط عنوان
	 * و محصولات دارند.
	 *
	 * h1 همیشه چاپ می‌شود؛ قبلا فقط وقتی شورت‌کد hook_intro وجود داشت.
	 */
	$hodima_title = woocommerce_page_title( false );
	?>
	<?php if ( $hodima_is_first ) : ?>

		<section class="hodima-section-box section-intro-video">
			<div class="intro-box">
				<h1 class="category-title"><?php echo esc_html( $hodima_title ); ?></h1>
				<?php if ( shortcode_exists( 'hook_intro' ) ) : ?>
					<?php echo do_shortcode( '[hook_intro]' ); ?>
				<?php endif; ?>
			</div>

			<?php if ( shortcode_exists( 'hook_video' ) ) : ?>
				<?php $hodima_video = do_shortcode( '[hook_video]' ); ?>
				<?php if ( '' !== trim( $hodima_video ) ) : ?>
					<div class="video-box">
						<?php echo $hodima_video; // phpcs:ignore — خروجی شورت‌کد ?>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</section>

	<?php else : ?>

		<header class="hodima-section-box section-paged-head">
			<h1 class="category-title">
				<?php echo esc_html( $hodima_title ); ?>
				<span class="category-title__page">صفحه <span class="hodima-num"><?php echo (int) $hodima_paged; ?></span></span>
			</h1>
		</header>

	<?php endif; ?>

	<?php
	// ووکامرس ۸.۶+: افزونه‌ها بالای فهرست محصولات (خروجی پیش‌فرض در category-archive.php حذف شده)
	do_action( 'woocommerce_shop_loop_header' );
	?>

	<main id="main-content" class="hodima-section-box section-products product-card-scope">

		<?php if ( woocommerce_product_loop() ) : ?>

			<?php do_action( 'woocommerce_before_shop_loop' ); ?>

			<div class="hodima-custom-sort-wrapper">

				<div class="sort-box sort-title-box" aria-hidden="true">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="21" y1="10" x2="3" y2="10"></line><line x1="21" y1="6" x2="3" y2="6"></line><line x1="21" y1="14" x2="3" y2="14"></line><line x1="21" y1="18" x2="3" y2="18"></line></svg>
				</div>

				<?php
				/*
				 * دکمه (نه لینک) عمدا: نسخه‌های مرتب‌شده (?orderby=) نباید خزیده
				 * شوند — canonical همه‌شان صفحه اصلی دسته است و لینک به آن‌ها
				 * بودجه خزش را هدر می‌دهد.
				 */
				?>
				<div class="sort-box sort-options-box" role="group" aria-label="مرتب‌سازی">
					<button type="button" class="hodima-sort-btn" data-sort-type="popularity" aria-pressed="false">محبوب‌ترین‌ها</button>
					<button type="button" class="hodima-sort-btn" data-sort-type="date" aria-pressed="false">جدیدترین‌ها</button>

					<div class="price-sort-wrapper">
						<button type="button" class="hodima-sort-btn has-dropdown" data-sort-type="price-group" aria-haspopup="true" aria-expanded="false">
							قیمت
							<svg class="dropdown-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"></polyline></svg>
						</button>
						<div class="sort-sub-options-box dropdown-menu">
							<button type="button" class="hodima-sort-sub-btn" data-orderby="price" aria-pressed="false">ارزان‌ترین</button>
							<button type="button" class="hodima-sort-sub-btn" data-orderby="price-desc" aria-pressed="false">گران‌ترین</button>
						</div>
					</div>
				</div>

			</div>

			<?php woocommerce_product_loop_start(); ?>

			<?php while ( have_posts() ) : the_post(); ?>
				<?php
				do_action( 'woocommerce_shop_loop' );
				wc_get_template_part( 'content', 'product' );
				?>
			<?php endwhile; ?>

			<?php woocommerce_product_loop_end(); ?>

			<?php do_action( 'woocommerce_after_shop_loop' ); ?>

		<?php else : ?>

			<?php do_action( 'woocommerce_no_products_found' ); ?>

		<?php endif; ?>

	</main>

	<?php if ( $hodima_is_first ) : ?>

		<?php if ( shortcode_exists( 'expand_category_description' ) ) : ?>
			<section class="hodima-section-box section-description" aria-label="توضیحات دسته">
				<?php echo do_shortcode( '[expand_category_description]' ); ?>
			</section>
		<?php endif; ?>

		<?php
		$hodima_voice = shortcode_exists( 'hook_voice' ) ? do_shortcode( '[hook_voice]' ) : '';
		$hodima_faq   = shortcode_exists( 'hook_faq' ) ? do_shortcode( '[hook_faq]' ) : '';
		?>
		<?php if ( '' !== trim( $hodima_voice . $hodima_faq ) ) : ?>
			<section class="hodima-section-box section-voice section-faq" aria-label="پادکست و سوالات متداول">
				<?php if ( '' !== trim( $hodima_voice ) ) : ?>
					<div class="voice-inner-wrapper"><?php echo $hodima_voice; // phpcs:ignore ?></div>
				<?php endif; ?>
				<?php if ( '' !== trim( $hodima_faq ) ) : ?>
					<div class="faq-inner-wrapper"><?php echo $hodima_faq; // phpcs:ignore ?></div>
				<?php endif; ?>
			</section>
		<?php endif; ?>

		<?php if ( shortcode_exists( 'hook_ai_box' ) && $hodima_term_id ) : ?>
			<?php $hodima_ai = do_shortcode( '[hook_ai_box id="' . $hodima_term_id . '" context="term"]' ); ?>
			<?php if ( '' !== trim( $hodima_ai ) ) : ?>
				<section class="hodima-section-box section-ai-box" aria-label="خلاصه هوش مصنوعی">
					<?php echo $hodima_ai; // phpcs:ignore ?>
				</section>
			<?php endif; ?>
		<?php endif; ?>

	<?php endif; ?>

	<?php do_action( 'woocommerce_after_main_content' ); ?>

</div>

<?php get_footer( 'shop' );
