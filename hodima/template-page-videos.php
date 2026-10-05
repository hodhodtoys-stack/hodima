<?php
/**
 * Template Name: صفحه ویدئوها
 * File: template-page-videos.php
 * Version: 2.0.0
 *
 * ─────────────────────────────────────────────────────────────────────
 * صفحه‌بندی واقعی به جای اسکرول بی‌نهایت
 * ─────────────────────────────────────────────────────────────────────
 * نسخه قبلی فقط ۱۲ ویدئوی اول را در HTML داشت و بقیه را با اسکرول از
 * AJAX می‌آورد. گوگل اسکرول نمی‌کند و روی چیزی کلیک نمی‌کند؛ ویدئوهای
 * بعد از دوازدهمی از این صفحه هرگز کشف نمی‌شدند. $paged هم در کد ثابت
 * «۱» بود.
 *
 * حالا هر صفحه آدرس مستقل دارد (/مدیا/page/2/) با canonical خودش (موتور
 * canonical قالب /page/N/ را اضافه می‌کند) و لینک‌های صفحه‌بندی <a href>
 * واقعی‌اند. ۲۴ ویدئو در هر صفحه: بر ۶، ۴، ۳ و ۲ ستون شبکه بخش‌پذیر است،
 * پس هر صفحه در همه اندازه‌های صفحه‌نمایش با ردیف کامل تمام می‌شود.
 * ─────────────────────────────────────────────────────────────────────
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) exit;

/*
 * صفحه‌بندی: /video/page/2/ (مثل دسته‌بندی‌ها).
 * پیشوند /video/ با آدرس‌های نوع پست ویدئو مشترک است؛ یک قانون بازنویسی
 * اختصاصی با اولویت بالا (video-watch/vid-w-schema.php) این آدرس را به همین
 * برگه با paged=N می‌رساند.
 */
$hodima_vp_per_page = max( 1, (int) apply_filters( 'hodima_videos_per_page', 24 ) );
// /video/page/N/ — قانون بازنویسی اختصاصی در video-watch/vid-w-schema.php
$hodima_vp_paged    = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );

/** ارقام فارسی و عربی → انگلیسی */
$hodima_vp_latin = static fn( $v ): string => strtr( (string) $v, [
	'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
	'۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
	'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
	'٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
] );

/*
 * کوئری از افزونه Hodima Media (components/video-watch/videos-page.php)؛ همان
 * کوئری که ItemList اسکیمای این صفحه را می‌سازد (قبلا اسکیما همین‌جا بود —
 * بازسازی قالب، مرحله ۲). بدون افزونه، همان کوئری اینجا.
 */
$hodima_vp_video_query = function_exists( 'hodima_media_videos_page_query' ) ? hodima_media_videos_page_query() : new WP_Query( [
	'post_type'           => 'video',
	'post_status'         => 'publish',
	'posts_per_page'      => $hodima_vp_per_page,
	'paged'               => $hodima_vp_paged,
	'ignore_sticky_posts' => true,
] );

/*
 * صفحه‌ای بزرگ‌تر از تعداد کل صفحات → ۴۰۴ واقعی، *پیش از* چاپ هر چیزی.
 * بدون این، /مدیا/page/999/ یک صفحه خالی با وضعیت ۲۰۰ برمی‌گرداند
 * (soft 404) که گوگل آن را صفحه بی‌کیفیت حساب می‌کند.
 */
if ( $hodima_vp_paged > 1 && $hodima_vp_paged > (int) $hodima_vp_video_query->max_num_pages ) {
	global $wp_query;
	$wp_query->set_404();
	status_header( 404 );
	nocache_headers();
	$hodima_vp_404 = get_404_template();
	if ( $hodima_vp_404 ) {
		include $hodima_vp_404;
	}
	exit;
}

add_action( 'wp_enqueue_scripts', static function (): void {
	hodima_enqueue_asset( 'hodima-template-videos', 'assets/css/template-page-videos.css' );
} );

/*
 * عنوان سند صفحات ۲ به بعد.
 * سئوباکس عنوان را خودش می‌سازد و شماره صفحه را اضافه نمی‌کند؛ بدون این،
 * /مدیا/ و /مدیا/page/2/ عنوان یکسان داشتند (خطای «عنوان تکراری» سرچ
 * کنسول). اگر سئوباکس عنوانی برنگرداند، وردپرس خودش شماره را اضافه می‌کند.
 */
if ( $hodima_vp_paged > 1 ) {
	add_filter( 'pre_get_document_title', static function ( $title ) use ( $hodima_vp_paged ) {
		return '' === (string) $title ? $title : $title . ' - صفحه ' . $hodima_vp_paged;
	}, 10000 ); // بعد از سئوباکس (اولویت ۹۹۹۹)

	// canonical: موتور canonical قالب خودش /page/N/ را اضافه می‌کند
}

$hodima_vp_page_url   = (string) get_permalink();
$hodima_vp_page_title = (string) get_the_title();

get_header(); ?>

<main class="hodima-page-wrapper">

	<?php
	/*
	 * مسیر راهنما.
	 * شاخه‌های Rank Math و Yoast حذف شدند؛ هیچ‌کدام نصب نیستند و مسیر
	 * راهنمای ساختاریافته را schema/breadcrumb-schema.php می‌سازد.
	 *
	 * صفحه قبلا هیچ <h1>ی نداشت. عنوان صفحه اینجا h1 است و با کلاس
	 * section-breadcrumb__title دقیقا مثل متن معمولی مسیر راهنما نمایش
	 * داده می‌شود، پس ظاهر تغییری نمی‌کند.
	 */
	?>
	<nav class="section-breadcrumb" aria-label="مسیر راهنما">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>">خانه</a> /
		<?php if ( $hodima_vp_paged > 1 ) : ?>
			<?php // h1 همچنان موضوع صفحه است (نه فقط «صفحه ۲») و به صفحه اول لینک می‌دهد ?>
			<h1 class="section-breadcrumb__title"><a href="<?php echo esc_url( $hodima_vp_page_url ); ?>"><?php echo esc_html( $hodima_vp_page_title ); ?></a></h1> /
			<span aria-current="page">صفحه <span class="hvp-num"><?php echo (int) $hodima_vp_paged; ?></span></span>
		<?php else : ?>
			<h1 class="section-breadcrumb__title" aria-current="page"><?php echo esc_html( $hodima_vp_page_title ); ?></h1>
		<?php endif; ?>
	</nav>

	<section class="video-page-section">
		<div class="videos-page-container">

			<?php if ( $hodima_vp_video_query->have_posts() ) : ?>

				<div class="videos-page-grid" id="videos-grid">
					<?php
					$hodima_vp_index    = 0;

					while ( $hodima_vp_video_query->have_posts() ) :
						$hodima_vp_video_query->the_post();

						$hodima_vp_post_id = (int) get_the_ID();
						$hodima_vp_url     = (string) get_permalink();
						$hodima_vp_title   = (string) get_the_title();

						// کاور ویدئو، مثل صفحه تماشا؛ نسخه قبلی فقط تصویر شاخص را نشان می‌داد
						$hodima_vp_cover_url = (string) get_post_meta( $hodima_vp_post_id, '_hod_video_thumbnail', true );
						$hodima_vp_cover_id  = $hodima_vp_cover_url ? (int) attachment_url_to_postid( $hodima_vp_cover_url ) : 0;
						$hodima_vp_eager     = ( 1 === $hodima_vp_paged && $hodima_vp_index++ < 6 );
						$hodima_vp_img_attrs = [
							'class'    => 'videos-page-thumbnail',
							'alt'      => $hodima_vp_title,
							'loading'  => $hodima_vp_eager ? 'eager' : 'lazy',
							'decoding' => 'async',
						];
						?>
						<a href="<?php echo esc_url( $hodima_vp_url ); ?>" class="videos-page-card">

							<?php if ( $hodima_vp_cover_id ) : ?>
								<?php echo wp_get_attachment_image( $hodima_vp_cover_id, 'medium', false, $hodima_vp_img_attrs ); ?>
							<?php elseif ( has_post_thumbnail() ) : ?>
								<?php the_post_thumbnail( 'medium', $hodima_vp_img_attrs ); ?>
							<?php elseif ( $hodima_vp_cover_url ) : ?>
								<img src="<?php echo esc_url( $hodima_vp_cover_url ); ?>" class="videos-page-thumbnail" alt="<?php echo esc_attr( $hodima_vp_title ); ?>" loading="<?php echo $hodima_vp_eager ? 'eager' : 'lazy'; ?>" decoding="async" width="300" height="169">
							<?php else : ?>
								<div class="videos-page-thumbnail videos-page-thumbnail--empty"><span>بدون تصویر</span></div>
							<?php endif; ?>

							<div class="videos-page-content">
								<?php
								// عنوان کامل در HTML؛ کوتاه کردن با «...» را CSS انجام می‌دهد.
								// نسخه قبلی در PHP به ۲۲ کاراکتر می‌برید و موتورهای جستجو
								// عنوان‌های ناقص با «...» می‌دیدند.
								?>
								<h2 class="videos-page-title" title="<?php echo esc_attr( $hodima_vp_title ); ?>"><?php echo esc_html( $hodima_vp_title ); ?></h2>
							</div>
						</a>
					<?php endwhile; ?>
				</div>

				<?php
				$hodima_vp_links = paginate_links( [
					'base'               => user_trailingslashit( trailingslashit( $hodima_vp_page_url ) . 'page/%#%', 'paged' ),
					'format'             => '',
					'current'            => $hodima_vp_paged,
					'total'              => (int) $hodima_vp_video_query->max_num_pages,
					'mid_size'           => 1,
					'end_size'           => 1,
					'prev_text'          => '<span aria-hidden="true">&rsaquo;</span> قبلی',
					'next_text'          => 'بعدی <span aria-hidden="true">&lsaquo;</span>',
					'type'               => 'list',
					// اعداد در عنصر جدا تا CSS فونت لاتین برایشان بگذارد
					'before_page_number' => '<span class="hvp-num">',
					'after_page_number'  => '</span>',
				] );

				if ( $hodima_vp_links ) :
					/*
					 * اعداد انگلیسی. paginate_links شماره‌ها را با
					 * number_format_i18n() می‌سازد که با زبان فارسی رقم فارسی
					 * برمی‌گرداند.
					 */
					$hodima_vp_links = $hodima_vp_latin( $hodima_vp_links );

					// صفحه ۱ بدون /page/1/ (آدرس تکراری صفحه اول)
					$hodima_vp_links = str_replace( trailingslashit( $hodima_vp_page_url ) . 'page/1/', $hodima_vp_page_url, $hodima_vp_links );
					?>
					<nav class="videos-pagination" aria-label="صفحه‌بندی ویدئوها">
						<?php echo wp_kses_post( $hodima_vp_links ); ?>
					</nav>
				<?php endif; ?>

				<?php // ItemList ویدئوهای این صفحه: Hodima Media (components/video-watch/videos-page.php) ?>

			<?php else : ?>
				<p class="videos-page-empty">هیچ ویدئویی یافت نشد.</p>
			<?php endif; ?>

			<?php wp_reset_postdata(); ?>
		</div>
	</section>
</main>

<?php get_footer();
