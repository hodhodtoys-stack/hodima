<?php
/**
 * بخش صفحه اصلی: معرفی، «اهداف و مزایا» و ویدیو
 * Path: home/parts/intro.php
 *
 * فراخوانی: get_template_part( 'home/parts/intro', null, $args ) — از چیدمان
 * «تنظیمات قالب ← صفحه اصلی» (home/builder.php) یا شورت‌کد قدیمی [section07].
 * متن معرفی و ویدیو از کادر «رسانه» همین برگه (شورت‌کدهای hook_intro و
 * hook_video افزونه Hodima Media، با inc/media-sections.php)؛ بدون افزونه بخش
 * چاپ نمی‌شود. چیدمان سه‌ستونه (با «اهداف و مزایا») مال صفحه اصلی است، پس
 * template-parts/media/intro-media.php را به کار نمی‌برد؛ ستون‌ها با بودن
 * افزونه چاپ می‌شوند (H1 صفحه اصلی همین‌جاست — front-page.php).
 *
 * @var array{features_title?:string, features?:list<string>, video_title?:string} $args
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- قالب داخل تابع (load_template/wc_get_template_part) لود می‌شود؛ متغیرها محلی‌اند، نه سراسری

$args = wp_parse_args( $args ?? [], hodima_home_section_defaults( 'intro' ) );

if ( ! hodima_theme_media_available( 'intro' ) && ! hodima_theme_media_available( 'video' ) ) {
	return;
}

// عنوان هوشمند (بدون ووکامرس هم نباید Fatal Error بدهد)
$hodima_wc  = function_exists( 'hodima_wc_active' ) && hodima_wc_active();
$page_title = match ( true ) {
	$hodima_wc && is_shop() && ! is_search() => get_the_title( wc_get_page_id( 'shop' ) ),
	is_page() || is_front_page()             => get_the_title(),
	$hodima_wc                               => (string) woocommerce_page_title( false ),
	default                                  => wp_strip_all_tags( get_the_archive_title() ),
};

$features = array_values( array_filter( array_map( 'trim', (array) $args['features'] ) ) );
?>
<section class="arian-section section-intro-media" aria-labelledby="arian-section01-title">

	<div class="intro-media-wrapper">

		<?php if ( hodima_theme_media_available( 'intro' ) ) : ?>
			<div class="intro-content-box">
				<h1 id="arian-section01-title" class="section-col-title"><?php echo esc_html( $page_title ); ?></h1>
				<div class="intro-hook-content">
					<?php echo hodima_theme_media_html( 'intro' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- خروجی افزونه Hodima Media ?>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( '' !== (string) $args['features_title'] || $features ) : ?>
			<div class="intro-middle-space">
				<div class="about-features-inline">
					<?php if ( '' !== (string) $args['features_title'] ) : ?>
						<h2 class="section-col-title"><?php echo esc_html( $args['features_title'] ); ?></h2>
					<?php endif; ?>
					<?php if ( $features ) : ?>
						<ul class="features-list">
							<?php foreach ( $features as $feature ) : ?>
								<li><?php echo esc_html( $feature ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( hodima_theme_media_available( 'video' ) ) : ?>
			<div class="intro-video-box" aria-label="<?php echo esc_attr( (string) $args['video_title'] ?: 'ویدیو' ); ?>">
				<?php if ( '' !== (string) $args['video_title'] ) : ?>
					<h2 class="section-col-title"><?php echo esc_html( $args['video_title'] ); ?></h2>
				<?php endif; ?>
				<div class="video-content-wrapper">
					<?php echo hodima_theme_media_html( 'video' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- خروجی افزونه Hodima Media ?>
				</div>
			</div>
		<?php endif; ?>

	</div>

</section>
