<?php
/**
 * Product archive — Shop page, product tags, product search
 * Version: 2.0.0
 *
 * هوک‌های پیش از کوئری (۳۶ محصول در صفحه، عنوان صفحات بعدی، اعداد انگلیسی
 * صفحه‌بندی) در inc/woocommerce/category-archive.php هستند و روی فروشگاه،
 * برچسب‌ها و دسته‌ها مشترک‌اند.
 */

defined( 'ABSPATH' ) || exit;

$hodima_paged     = max( 1, (int) get_query_var( 'paged' ) );
$hodima_is_search = is_search();
$hodima_is_shop   = is_shop() && ! $hodima_is_search;

/*
 * wrapper پیش‌فرض ووکامرس حذف می‌شود.
 * نسخه قبلی woocommerce_after_main_content را صدا می‌زد (که </main></div>
 * ووکامرس را چاپ می‌کند) ولی before_main_content را نه: <main> خود صفحه
 * زودتر بسته می‌شد و یک </div> سرگردان باقی می‌ماند.
 */
remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );
remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );

// شمارش و مرتب‌سازی پیش‌فرض ووکامرس: نوار مرتب‌سازی اختصاصی جایگزین است
remove_action( 'woocommerce_before_shop_loop', 'woocommerce_result_count', 20 );
remove_action( 'woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30 );

/*
 * عنوان h1.
 * این قالب برای فروشگاه، *برچسب‌های محصول* و *جستجوی محصول* استفاده
 * می‌شود. نسخه قبلی h1 ثابت «فروشگاه بازرگانی هدهد» داشت؛ صفحه برچسب
 * «گل سر» یا نتیجه جستجوی «کش مو» هم همین h1 را داشت.
 */
if ( $hodima_is_search ) {
	$hodima_h1 = sprintf( 'نتایج جستجو برای «%s»', get_search_query() );
} elseif ( $hodima_is_shop ) {
	$hodima_h1 = 'فروشگاه بازرگانی هدهد';
} else {
	$hodima_h1 = woocommerce_page_title( false );
}

get_header( 'shop' );
?>

<main id="main-content" class="hodima-page-wrapper">

	<?php do_action( 'woocommerce_before_main_content' ); ?>

	<?php // woocommerce_breadcrumb() خودش <nav aria-label> چاپ می‌کند ?>
	<div class="section-breadcrumb">
		<?php woocommerce_breadcrumb(); ?>
	</div>

	<?php if ( $hodima_is_shop && 1 === $hodima_paged ) : ?>

		<section class="hodima-section-box shop-custom-header">
			<div class="shop-header-content">
				<h1><?php echo esc_html( $hodima_h1 ); ?></h1>
				<p>عرضه مستقیم کالاهای وارداتی بدون واسطه</p>
				<ul class="shop-features" aria-label="ویژگی‌های فروشگاه">
					<li>اصالت کالا</li>
					<li>قیمت رقابتی</li>
					<li>ارسال سریع</li>
				</ul>
			</div>
		</section>

	<?php else : ?>

		<?php
		/*
		 * صفحات ۲ به بعد، برچسب‌ها و جستجو: سربرگ فشرده.
		 * سربرگ کامل فروشگاه فقط در صفحه اول؛ تکرار عینی آن روی /shop/page/2/
		 * و بعدی‌ها صفحات هم‌محتوا می‌ساخت.
		 */
		?>
		<header class="hodima-section-box shop-custom-header shop-custom-header--compact">
			<h1 class="shop-compact-title">
				<?php echo esc_html( $hodima_h1 ); ?>
				<?php if ( $hodima_paged > 1 ) : ?>
					<span class="shop-compact-page">صفحه <span class="hodima-num"><?php echo (int) $hodima_paged; ?></span></span>
				<?php endif; ?>
			</h1>
		</header>

	<?php endif; ?>

	<?php
	/*
	 * section-products: همان کلاس صفحات دسته‌بندی، تا نوار مرتب‌سازی،
	 * صفحه‌بندی و اسکریپت مرتب‌سازی (taxonomy-product_cat.js) مشترک باشند.
	 */
	?>
	<section class="hodima-section-box shop-content-area section-products product-card-scope" aria-label="محصولات">

		<?php if ( woocommerce_product_loop() ) : ?>

			<?php do_action( 'woocommerce_before_shop_loop' ); ?>

			<div class="hodima-custom-sort-wrapper">

				<div class="sort-box sort-title-box" aria-hidden="true">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="21" y1="10" x2="3" y2="10"></line><line x1="21" y1="6" x2="3" y2="6"></line><line x1="21" y1="14" x2="3" y2="14"></line><line x1="21" y1="18" x2="3" y2="18"></line></svg>
				</div>

				<?php // دکمه (نه لینک) عمدا: نسخه‌های مرتب‌شده (?orderby=) نباید خزیده شوند ?>
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

			<?php if ( wc_get_loop_prop( 'total' ) ) : ?>
				<?php while ( have_posts() ) : the_post(); ?>
					<?php
					do_action( 'woocommerce_shop_loop' );
					wc_get_template_part( 'content', 'product' );
					?>
				<?php endwhile; ?>
			<?php endif; ?>

			<?php woocommerce_product_loop_end(); ?>

			<?php do_action( 'woocommerce_after_shop_loop' ); ?>

		<?php else : ?>

			<?php do_action( 'woocommerce_no_products_found' ); ?>

		<?php endif; ?>

	</section>

	<?php
	/*
	 * متن برگه فروشگاه (یا توضیح برچسب) — فقط صفحه اول (خود ووکامرس این را
	 * بررسی می‌کند). قبلا هرگز نمایش داده نمی‌شد: متنی که در ویرایشگر برگه
	 * «فروشگاه» نوشته می‌شد، جایی روی سایت نداشت.
	 */
	ob_start();
	do_action( 'woocommerce_archive_description' );
	$hodima_archive_desc = trim( (string) ob_get_clean() );
	?>
	<?php if ( '' !== $hodima_archive_desc ) : ?>
		<section class="hodima-section-box section-description shop-description" aria-label="درباره فروشگاه">
			<?php
			// مستقیم (شورت‌کد جعبه بازشونده فقط در صفحه دسته‌بندی خروجی دارد)
			echo $hodima_archive_desc; // phpcs:ignore — خروجی خود ووکامرس
			?>
		</section>
	<?php endif; ?>

	<?php do_action( 'woocommerce_after_main_content' ); ?>

</main>

<?php get_footer( 'shop' );
