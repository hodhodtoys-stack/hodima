<?php
/**
 * صفحه اصلی
 * Path: hodima/front-page.php
 *
 * بازسازی قالب، مرحله ۳. اگر «نمایش ← تنظیمات قالب هدیما ← صفحه اصلی ←
 * ساخت صفحه اصلی از این چیدمان» روشن باشد، بخش‌ها به ترتیب همان چیدمان چاپ
 * می‌شوند (inc/home-layout.php). وگرنه دقیقا مثل قبل: متن برگه صفحه اصلی با
 * شورت‌کدهایش (index.php).
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

// فیلتر frontpage_template (inc/home-layout.php) این فایل را فقط با چیدمان فعال انتخاب می‌کند؛ این فقط محافظ است
if ( ! function_exists( 'hodima_home_builder_active' ) || ! hodima_home_builder_active() ) {
	require ( is_page() ? get_page_template() : '' ) ?: __DIR__ . '/index.php'; // phpcs:ignore PEAR.Files.IncludingFile.BracketsNotRequired -- پرانتز لازم است: ?: روی شرط اعمال شود
	return;
}

get_header();

// پست جاری = برگه صفحه اصلی (شورت‌کدهای رسانه و «متن برگه» از آن می‌خوانند)
if ( have_posts() ) {
	the_post();
}

$hodima_sections = array_values( array_filter( hodima_home_layout(), static fn( array $item ): bool => $item['enabled'] ) );

/*
 * عنوان اصلی (H1): بخش «معرفی» عنوان برگه را H1 می‌کند (وقتی متن معرفی
 * افزونه رسانه هست). بدون آن، صفحه اصلی نباید بی‌H1 بماند: عنوان پنهان
 * برای موتور جستجو و صفحه‌خوان.
 */
$hodima_has_h1 = shortcode_exists( 'hook_intro' ) && in_array( 'intro', array_column( $hodima_sections, 'type' ), true );
?>

<main id="primary" class="site-main hodima-home">
	<div class="hodima-container hodima-page__container">
		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>

			<?php if ( ! $hodima_has_h1 ) : ?>
				<h1 class="hodima-sr-only"><?php echo esc_html( get_the_title() ?: get_bloginfo( 'name' ) ); ?></h1>
			<?php endif; ?>

			<div class="entry-content">
				<?php
				foreach ( $hodima_sections as $hodima_section ) {
					hodima_home_render_section( $hodima_section );
				}
				?>
			</div>
		</article>
	</div>
</main>

<?php
get_footer();
