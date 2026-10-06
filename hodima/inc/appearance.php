<?php
/**
 * ظاهر عمومی: گردی گوشه‌ها، سبک دکمه‌ها و عرض محتوا (تب «ظاهر عمومی» تنظیمات قالب)
 * Path: hodima/inc/appearance.php
 *
 * از 2.9.7. پیش از آن عرض محتوا (۱۴۴۰px / 90rem) در ۱۲ جای CSS جدا نوشته شده
 * بود و گردی گوشه‌ها و رنگ دکمه‌ها فقط با ویرایش CSS عوض می‌شد. حالا همه
 * توکن‌های tokens.css‌اند (--hodima-radius-scale، --hodima-container،
 * --hodima-button-bg*) و این فایل مثل پالت فقط مقدار تغییرکرده را چاپ می‌کند.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * ضریب گردی هر گزینه.
 *
 * @return array<string, string>
 */
function hodima_appearance_radius_scales(): array {
	return [ 'sharp' => '0', 'soft' => '0.5', 'default' => '1', 'round' => '1.5' ];
}

/**
 * پس‌زمینه دکمه (عادی، زیر نشانگر) هر سبک.
 *
 * @return array<string, array{0:string, 1:string}>
 */
function hodima_appearance_button_styles(): array {
	return [
		'gradient'  => [ 'var(--hodima-gradient-main)', 'var(--hodima-gradient-alt)' ],
		'solid'     => [ 'var(--hodima-primary)', 'var(--hodima-primary-hover)' ],
		'secondary' => [ 'var(--hodima-secondary)', 'var(--hodima-primary)' ],
		'accent'    => [ 'var(--hodima-gradient-accent)', 'linear-gradient(135deg, var(--hodima-accent-light) 0%, var(--hodima-accent) 100%)' ],
	];
}

/**
 * متغیرهای :root که با پیش‌فرض فرق دارند.
 *
 * @return array<string, string>
 */
function hodima_appearance_vars(): array {

	$vars = [];

	$radius = (string) hodima_setting( 'radius_style' );
	$scales = hodima_appearance_radius_scales();
	if ( 'default' !== $radius && isset( $scales[ $radius ] ) ) {
		$vars['--hodima-radius-scale'] = $scales[ $radius ];
	}

	$button = (string) hodima_setting( 'button_style' );
	$styles = hodima_appearance_button_styles();
	if ( 'gradient' !== $button && isset( $styles[ $button ] ) ) {
		[ $vars['--hodima-button-bg'], $vars['--hodima-button-bg-hover'] ] = $styles[ $button ];
	}

	$width = (int) hodima_setting( 'container_width' );
	if ( 1440 !== $width && $width >= 960 && $width <= 1920 ) {
		$vars['--hodima-container'] = $width . 'px';
	}

	return $vars;
}

/** CSS تنظیمات ظاهر عمومی، یا رشته خالی با تنظیمات پیش‌فرض. */
function hodima_appearance_css(): string {

	$css = '';
	foreach ( hodima_appearance_vars() as $name => $value ) {
		$css .= $name . ':' . $value . ';';
	}

	return '' !== $css ? ':root{' . $css . '}' : '';
}

/** بعد از tokens.css (مثل پالت و تایپوگرافی). */
function hodima_print_appearance(): void {
	$css = hodima_appearance_css();
	if ( '' !== $css && wp_style_is( 'hodima-tokens', 'enqueued' ) ) {
		wp_add_inline_style( 'hodima-tokens', $css );
	}
}
add_action( 'wp_enqueue_scripts', 'hodima_print_appearance', 21 );

/** نمونه زنده تب «ظاهر عمومی» (admin.js با هر تغییر متغیرهای ظرف را عوض می‌کند). */
function hodima_appearance_preview(): void {

	$vars = '';
	foreach ( hodima_appearance_vars() as $name => $value ) {
		$vars .= $name . ':' . $value . ';';
	}
	?>
	<div
		class="hodima-look-preview"
		data-hodima-look-preview
		data-scales="<?php echo esc_attr( (string) wp_json_encode( hodima_appearance_radius_scales() ) ); ?>"
		data-buttons="<?php echo esc_attr( (string) wp_json_encode( hodima_appearance_button_styles() ) ); ?>"
		style="<?php echo esc_attr( $vars ); ?>"
		aria-hidden="true"
	>
		<div class="hodima-look-preview__card">
			<span class="hodima-look-preview__image"></span>
			<strong class="hodima-look-preview__title">عنوان نمونه محصول</strong>
			<span class="hodima-look-preview__field">تعداد</span>
			<span class="hodima-look-preview__button">افزودن به سبد</span>
		</div>
		<p class="hodima-look-preview__width">عرض محتوا: <bdi data-hodima-look-width><?php echo esc_html( hodima_fa_digits( (int) hodima_setting( 'container_width' ) ) ); ?></bdi> پیکسل</p>
	</div>
	<?php
}
