<?php
/**
 * بخش «معرفی + ویدیو» (کادر رسانه افزونه Hodima Media)
 * Path: template-parts/media/intro-media.php
 *
 * فراخوانی: get_template_part( 'template-parts/media/intro-media', null, $args )
 * از single-post.php، page.php و woocommerce/taxonomy-product_cat.php.
 * HTML هر چیدمان همان HTML قبلی همان قالب است (CSS به این کلاس‌ها وابسته است):
 *   post     — H1 مقاله + دو ستون معرفی/ویدیو؛ هر دو ستون همیشه چاپ می‌شوند
 *              (ستون خالی هم نصف عرض را نگه می‌دارد، مثل قبل)؛
 *   page     — بدون H1؛ فقط ستون‌های دارای محتوا؛ هر دو خالی = بدون بخش؛
 *   category — H1 دسته داخل ستون معرفی؛ ستون ویدیو فقط با محتوا.
 * محتوا را قالب فراخوان با hodima_theme_media_html() می‌خواند (inc/media-sections.php).
 *
 * meta (فقط post): HTML اطلاعات زیر عنوان مقاله (hodima_post_meta_html، inc/blog.php).
 *
 * @var array{layout?:string, title?:string, intro?:string, video?:string, meta?:string} $args
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

$hodima_args = wp_parse_args(
	$args ?? [],
	[
		'layout' => 'post',
		'title'  => '', // HTML عنوان (H1)؛ با wp_kses_post چاپ می‌شود
		'intro'  => '',
		'video'  => '',
		'meta'   => '',
	]
);

$hodima_layout = (string) $hodima_args['layout'];
$hodima_title  = (string) $hodima_args['title'];
$hodima_intro  = (string) $hodima_args['intro'];
$hodima_video  = (string) $hodima_args['video'];

if ( 'post' !== $hodima_layout && 'category' !== $hodima_layout && '' === $hodima_intro . $hodima_video ) {
	return;
}
?>
<?php if ( 'category' === $hodima_layout ) : ?>
	<section class="hodima-section-box section-intro-video">
		<div class="intro-box">
			<h1 class="category-title"><?php echo wp_kses_post( $hodima_title ); ?></h1>
			<?php echo $hodima_intro; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- خروجی افزونه Hodima Media ?>
		</div>
		<?php if ( '' !== $hodima_video ) : ?>
			<div class="video-box"><?php echo $hodima_video; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- خروجی افزونه Hodima Media ?></div>
		<?php endif; ?>
	</section>
<?php else : ?>
	<section class="hodima-section-box section-intro-media">
		<?php if ( 'post' === $hodima_layout ) : ?>
			<h1 class="single-post-title"><?php echo wp_kses_post( $hodima_title ); ?></h1>
			<?php echo (string) $hodima_args['meta']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escape‌شده در hodima_post_meta_html() ?>
		<?php endif; ?>
		<div class="<?php echo 'post' === $hodima_layout ? 'video-thumbnail-wrapper single-post-media' : 'video-thumbnail-wrapper'; ?>">
			<?php if ( 'post' === $hodima_layout || '' !== $hodima_intro ) : ?>
				<div class="intro-content"><?php echo $hodima_intro; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- خروجی افزونه Hodima Media ?></div>
			<?php endif; ?>
			<?php if ( 'post' === $hodima_layout || '' !== $hodima_video ) : ?>
				<div class="video-content"><?php echo $hodima_video; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- خروجی افزونه Hodima Media یا تصویر شاخص مقاله ?></div>
			<?php endif; ?>
		</div>
	</section>
<?php endif; ?>
