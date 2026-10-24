<?php
/**
 * تب «شبکه‌های اجتماعی» در تنظیمات قالب: فهرست پویا (افزودن، حذف، ترتیب)
 * Path: hodima/inc/theme-settings/social-admin.php
 *
 * هر ردیف: نام، آدرس و آیکون (از کتابخانه رسانه). داده و پاک‌سازی: theme-settings.php
 * (hodima_social_links / hodima_social_sanitize؛ گزینه جدا hodima_social_links، همین فرم).
 * رفتار سمت مرورگر: admin.js (initSocials)، ظاهر: admin.css (.hodima-socials-admin).
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

add_action( 'admin_init', static function (): void {
	register_setting( 'hodima_theme_settings_group', HODIMA_SOCIAL_OPTION, [
		'type'              => 'array',
		'sanitize_callback' => 'hodima_social_sanitize',
		'default'           => [],
		'show_in_rest'      => false,
	] );
} );

/** قاب «شبکه‌ها» (بالای قاب «نمایش در صفحه‌ها»). */
function hodima_social_admin_render(): void {

	$items = hodima_social_links();
	?>
	<section class="hodima-panel hodima-socials-admin" aria-labelledby="hodima-panel-social_list-title" data-hodima-socials>
		<?php
		hodima_settings_panel_head(
			'hodima-panel-social_list-title',
			'شبکه‌ها',
			'با «افزودن شبکه» یک ردیف تازه می‌آید؛ نام، آدرس و آیکون را خودتان پر کنید. به ترتیب از راست به چپ در نوار بالای فوتر دیده می‌شوند و با دکمه‌های بالا/پایین جابه‌جا می‌شوند. آیکون مربعی و شفاف (SVG یا PNG) بهترین نتیجه را دارد؛ بدون آیکون، نام شبکه نمایش داده می‌شود.',
			'dashicons-share',
			'',
			true
		);
		?>
		<div class="hodima-panel__body">
			<input type="hidden" name="<?php echo esc_attr( HODIMA_SOCIAL_OPTION ); ?>[_sent]" value="1">

			<ol class="hodima-socials-admin__list" data-hodima-social-list>
				<?php foreach ( $items as $item ) : ?>
					<?php hodima_social_admin_item( $item ); ?>
				<?php endforeach; ?>
			</ol>

			<p class="hodima-socials-admin__empty" data-hodima-social-empty <?php echo $items ? 'hidden' : ''; ?>>هنوز شبکه‌ای اضافه نکرده‌اید؛ از پایین اضافه کنید. تا شبکه‌ای نباشد، نوار نمایش داده نمی‌شود.</p>

			<div class="hodima-socials-admin__toolbar">
				<button type="button" class="button" data-hodima-social-add data-max="<?php echo (int) HODIMA_SOCIAL_MAX; ?>">
					<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> افزودن شبکه
				</button>
			</div>

			<template data-hodima-social-template>
				<?php hodima_social_admin_item( [ 'id' => '__UID__', 'label' => '', 'url' => '', 'icon_id' => 0 ] ); ?>
			</template>
		</div>
	</section>
	<?php
}

/**
 * یک ردیف شبکه.
 *
 * @param array{id:string, label:string, url:string, icon_id:int} $item
 */
function hodima_social_admin_item( array $item ): void {

	$base    = HODIMA_SOCIAL_OPTION . '[' . $item['id'] . ']';
	$icon_id = (int) $item['icon_id'];
	?>
	<li class="hodima-socials-admin__item" data-hodima-social-item>
		<span class="hodima-socials-admin__move">
			<button type="button" class="hodima-socials-admin__btn" data-hodima-social-move="up" aria-label="انتقال به بالا"><span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span></button>
			<button type="button" class="hodima-socials-admin__btn" data-hodima-social-move="down" aria-label="انتقال به پایین"><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></button>
		</span>

		<?php // بدون برچسب بالای کادرها: نام کادر همان متن داخلش است (placeholder + aria-label)؛ راهنمای قاب آدرس را توضیح می‌دهد ?>
		<input type="text" class="hodima-socials-admin__name" name="<?php echo esc_attr( $base ); ?>[label]" value="<?php echo esc_attr( $item['label'] ); ?>" maxlength="40" placeholder="نام شبکه" aria-label="نام شبکه" data-hodima-social-label>

		<input type="text" class="hodima-socials-admin__url" inputmode="url" name="<?php echo esc_attr( $base ); ?>[url]" value="<?php echo esc_attr( $item['url'] ); ?>" dir="ltr" placeholder="آدرس صفحه (بدون آدرس ذخیره نمی‌شود)" aria-label="آدرس صفحه" autocomplete="off" spellcheck="false" data-hodima-social-url>

		<span class="hodima-socials-admin__icon">
			<span class="hodima-media hodima-media--compact" data-hodima-media>
				<input type="hidden" name="<?php echo esc_attr( $base ); ?>[icon_id]" value="<?php echo esc_attr( (string) $icon_id ); ?>" data-hodima-media-input>
				<span class="hodima-media__preview<?php echo $icon_id ? '' : ' is-empty'; ?>" data-hodima-media-preview>
					<?php echo $icon_id ? wp_get_attachment_image( $icon_id, 'thumbnail', false, [ 'alt' => '' ] ) : ''; ?>
					<span class="hodima-media__empty" aria-hidden="true"><span class="dashicons dashicons-format-image"></span></span>
				</span>
				<span class="hodima-media__buttons">
					<button type="button" class="button" data-hodima-media-select data-hodima-media-label="آیکون" data-title="آیکون شبکه">آیکون</button>
					<button type="button" class="button-link hodima-media__remove" data-hodima-media-remove aria-label="حذف آیکون" <?php echo $icon_id ? '' : 'hidden'; ?>>حذف</button>
				</span>
			</span>
		</span>

		<button type="button" class="hodima-socials-admin__btn hodima-socials-admin__btn--remove" data-hodima-social-remove aria-label="حذف این شبکه"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button>
	</li>
	<?php
}
