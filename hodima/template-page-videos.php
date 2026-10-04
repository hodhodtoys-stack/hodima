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

if ( ! defined( 'ABSPATH' ) ) exit;

/*
 * صفحه‌بندی: /video/page/2/ (مثل دسته‌بندی‌ها).
 * پیشوند /video/ با آدرس‌های نوع پست ویدئو مشترک است؛ یک قانون بازنویسی
 * اختصاصی با اولویت بالا (video-watch/vid-w-schema.php) این آدرس را به همین
 * برگه با paged=N می‌رساند.
 */
$hvp_per_page = max( 1, (int) apply_filters( 'hodima_videos_per_page', 24 ) );
// /video/page/N/ — قانون بازنویسی اختصاصی در video-watch/vid-w-schema.php
$hvp_paged    = max( 1, (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );

/** ارقام فارسی و عربی → انگلیسی */
$hvp_latin = static fn( $v ): string => strtr( (string) $v, [
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
$video_query = function_exists( 'hodima_media_videos_page_query' ) ? hodima_media_videos_page_query() : new WP_Query( [
	'post_type'           => 'video',
	'post_status'         => 'publish',
	'posts_per_page'      => $hvp_per_page,
	'paged'               => $hvp_paged,
	'ignore_sticky_posts' => true,
] );

/*
 * صفحه‌ای بزرگ‌تر از تعداد کل صفحات → ۴۰۴ واقعی، *پیش از* چاپ هر چیزی.
 * بدون این، /مدیا/page/999/ یک صفحه خالی با وضعیت ۲۰۰ برمی‌گرداند
 * (soft 404) که گوگل آن را صفحه بی‌کیفیت حساب می‌کند.
 */
if ( $hvp_paged > 1 && $hvp_paged > (int) $video_query->max_num_pages ) {
	global $wp_query;
	$wp_query->set_404();
	status_header( 404 );
	nocache_headers();
	$hvp_404 = get_404_template();
	if ( $hvp_404 ) {
		include $hvp_404;
	}
	exit;
}

add_action( 'wp_enqueue_scripts', static function (): void {
	$rel  = 'assets/css/template-page-videos.css';
	$path = get_theme_file_path( $rel );
	wp_enqueue_style( 'hodima-template-videos', get_theme_file_uri( $rel ), [], file_exists( $path ) ? (string) filemtime( $path ) : '2.0.0' );
} );

/*
 * عنوان سند صفحات ۲ به بعد.
 * سئوباکس عنوان را خودش می‌سازد و شماره صفحه را اضافه نمی‌کند؛ بدون این،
 * /مدیا/ و /مدیا/page/2/ عنوان یکسان داشتند (خطای «عنوان تکراری» سرچ
 * کنسول). اگر سئوباکس عنوانی برنگرداند، وردپرس خودش شماره را اضافه می‌کند.
 */
if ( $hvp_paged > 1 ) {
	add_filter( 'pre_get_document_title', static function ( $title ) use ( $hvp_paged ) {
		return '' === (string) $title ? $title : $title . ' - صفحه ' . $hvp_paged;
	}, 10000 ); // بعد از سئوباکس (اولویت ۹۹۹۹)

	// canonical: موتور canonical قالب خودش /page/N/ را اضافه می‌کند
}

$hvp_page_url   = (string) get_permalink();
$hvp_page_title = (string) get_the_title();

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
		<?php if ( $hvp_paged > 1 ) : ?>
			<?php // h1 همچنان موضوع صفحه است (نه فقط «صفحه ۲») و به صفحه اول لینک می‌دهد ?>
			<h1 class="section-breadcrumb__title"><a href="<?php echo esc_url( $hvp_page_url ); ?>"><?php echo esc_html( $hvp_page_title ); ?></a></h1> /
			<span aria-current="page">صفحه <span class="hvp-num"><?php echo (int) $hvp_paged; ?></span></span>
		<?php else : ?>
			<h1 class="section-breadcrumb__title" aria-current="page"><?php echo esc_html( $hvp_page_title ); ?></h1>
		<?php endif; ?>
	</nav>

	<section class="video-page-section">
		<div class="videos-page-container">

			<?php if ( $video_query->have_posts() ) : ?>

				<div class="videos-page-grid" id="videos-grid">
					<?php
					$hvp_index    = 0;

					while ( $video_query->have_posts() ) :
						$video_query->the_post();

						$post_id = (int) get_the_ID();
						$url     = (string) get_permalink();
						$title   = (string) get_the_title();

						// کاور ویدئو، مثل صفحه تماشا؛ نسخه قبلی فقط تصویر شاخص را نشان می‌داد
						$cover_url = (string) get_post_meta( $post_id, '_hod_video_thumbnail', true );
						$cover_id  = $cover_url ? (int) attachment_url_to_postid( $cover_url ) : 0;
						$eager     = ( 1 === $hvp_paged && $hvp_index++ < 6 );
						$img_attrs = [
							'class'    => 'videos-page-thumbnail',
							'alt'      => $title,
							'loading'  => $eager ? 'eager' : 'lazy',
							'decoding' => 'async',
						];
						?>
						<a href="<?php echo esc_url( $url ); ?>" class="videos-page-card">

							<?php if ( $cover_id ) : ?>
								<?php echo wp_get_attachment_image( $cover_id, 'medium', false, $img_attrs ); ?>
							<?php elseif ( has_post_thumbnail() ) : ?>
								<?php the_post_thumbnail( 'medium', $img_attrs ); ?>
							<?php elseif ( $cover_url ) : ?>
								<img src="<?php echo esc_url( $cover_url ); ?>" class="videos-page-thumbnail" alt="<?php echo esc_attr( $title ); ?>" loading="<?php echo $eager ? 'eager' : 'lazy'; ?>" decoding="async" width="300" height="169">
							<?php else : ?>
								<div class="videos-page-thumbnail videos-page-thumbnail--empty"><span>بدون تصویر</span></div>
							<?php endif; ?>

							<div class="videos-page-content">
								<?php
								// عنوان کامل در HTML؛ کوتاه کردن با «...» را CSS انجام می‌دهد.
								// نسخه قبلی در PHP به ۲۲ کاراکتر می‌برید و موتورهای جستجو
								// عنوان‌های ناقص با «...» می‌دیدند.
								?>
								<h2 class="videos-page-title" title="<?php echo esc_attr( $title ); ?>"><?php echo esc_html( $title ); ?></h2>
							</div>
						</a>
					<?php endwhile; ?>
				</div>

				<?php
				$hvp_links = paginate_links( [
					'base'               => user_trailingslashit( trailingslashit( $hvp_page_url ) . 'page/%#%', 'paged' ),
					'format'             => '',
					'current'            => $hvp_paged,
					'total'              => (int) $video_query->max_num_pages,
					'mid_size'           => 1,
					'end_size'           => 1,
					'prev_text'          => '<span aria-hidden="true">&rsaquo;</span> قبلی',
					'next_text'          => 'بعدی <span aria-hidden="true">&lsaquo;</span>',
					'type'               => 'list',
					// اعداد در عنصر جدا تا CSS فونت لاتین برایشان بگذارد
					'before_page_number' => '<span class="hvp-num">',
					'after_page_number'  => '</span>',
				] );

				if ( $hvp_links ) :
					/*
					 * اعداد انگلیسی. paginate_links شماره‌ها را با
					 * number_format_i18n() می‌سازد که با زبان فارسی رقم فارسی
					 * برمی‌گرداند.
					 */
					$hvp_links = $hvp_latin( $hvp_links );

					// صفحه ۱ بدون /page/1/ (آدرس تکراری صفحه اول)
					$hvp_links = str_replace( trailingslashit( $hvp_page_url ) . 'page/1/', $hvp_page_url, $hvp_links );
					?>
					<nav class="videos-pagination" aria-label="صفحه‌بندی ویدئوها">
						<?php echo wp_kses_post( $hvp_links ); ?>
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
