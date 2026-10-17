<?php
/**
 * Theme Settings — پالت رنگ برند (تب «برند و رنگ‌ها»)
 * Path: hodima/inc/theme-settings/palette.php
 *
 * از theme-settings.php جدا شد (3.6.3، بخش ۱۶۱ HODIMA-AUDIT.md): کد بدون تغییر، فقط جابه‌جا؛
 * ترتیب بارگذاری (و ثبت هوک‌ها) همان ترتیب قبلی فایل است (فهرست: theme-settings.php).
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =========================================================================
 * ۵. پالت رنگ برند (تب «برند و رنگ‌ها»)
 * ------------------------------------------------------------------------
 * فقط رنگ‌هایی که با پیش‌فرض فرق دارند به‌صورت :root{…} بعد از tokens.css
 * چاپ می‌شوند (سایت با پالت پیش‌فرض هیچ CSS اضافه‌ای ندارد). مشتق‌ها هم
 * دوباره ساخته می‌شوند: «R, G, B» برای رنگ‌های نیمه‌شفاف، نسخه تیره رنگ
 * اصلی (hover؛ ×۰٫۷۳ همان نسبت #1b244d به #25316a) و نقطه میانی گرادیان.
 * ========================================================================= */

/**
 * «#rrggbb» → [r, g, b]
 *
 * @return array{int, int, int}
 */
function hodima_hex_rgb( string $hex ): array {
	$hex = ltrim( $hex, '#' );
	if ( 3 === strlen( $hex ) ) {
		$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
	}
	return array_map( 'hexdec', str_split( substr( str_pad( $hex, 6, '0' ), 0, 6 ), 2 ) );
}

/**
 * [r, g, b] → «#rrggbb»
 *
 * @param array<int, int|float> $rgb
 */
function hodima_rgb_hex( array $rgb ): string {
	return '#' . implode( '', array_map( static fn( int|float $c ): string => str_pad( dechex( max( 0, min( 255, (int) round( $c ) ) ) ), 2, '0', STR_PAD_LEFT ), $rgb ) );
}

/** CSS پالت تنظیم‌شده، یا رشته خالی برای پالت پیش‌فرض. */
function hodima_palette_css(): string {

	$fields = hodima_settings_fields();
	$vars   = [];
	$map    = [
		'color_primary'      => 'primary',
		'color_secondary'    => 'secondary',
		'color_third'        => 'third',
		'color_accent'       => 'accent',
		'color_accent_light' => 'accent-light',
	];
	$changed = [];

	foreach ( $map as $key => $token ) {
		$value = strtolower( (string) hodima_setting( $key ) );
		if ( $value !== strtolower( (string) $fields[ $key ]['default'] ) && sanitize_hex_color( $value ) ) {
			$changed[ $token ]            = $value;
			$vars[ "--hodima-{$token}" ]     = $value;
			$vars[ "--hodima-{$token}-rgb" ] = implode( ', ', hodima_hex_rgb( $value ) );
		}
	}

	if ( ! $changed ) {
		return '';
	}

	$primary   = hodima_hex_rgb( (string) hodima_setting( 'color_primary' ) );
	$secondary = hodima_hex_rgb( (string) hodima_setting( 'color_secondary' ) );

	if ( isset( $changed['primary'] ) ) {
		$vars['--hodima-primary-dark'] = hodima_rgb_hex( array_map( static fn( int|float $c ): float => $c * 0.73, $primary ) );
	}

	if ( isset( $changed['primary'] ) || isset( $changed['secondary'] ) ) {
		$vars['--hodima-gradient-mid']  = hodima_rgb_hex( array_map( static fn( int|float $a, int|float $b ): float => $a + ( $b - $a ) * 0.4, $primary, $secondary ) );
		$vars['--hodima-gradient-deep'] = hodima_rgb_hex( array_map( static fn( int|float $a, int|float $b ): float => $a + ( $b - $a ) * 0.26, $primary, $secondary ) );
	}

	$css = '';
	foreach ( $vars as $name => $value ) {
		$css .= $name . ':' . $value . ';';
	}

	return ':root{' . $css . '}';
}

/** بعد از tokens.css در سایت و پیشخوان (همان handle). */
function hodima_print_palette(): void {
	$css = hodima_palette_css();
	if ( '' !== $css && wp_style_is( 'hodima-tokens', 'enqueued' ) ) {
		wp_add_inline_style( 'hodima-tokens', $css );
	}
}
add_action( 'wp_enqueue_scripts', 'hodima_print_palette', 21 );
add_action( 'admin_enqueue_scripts', 'hodima_print_palette', 2 );
