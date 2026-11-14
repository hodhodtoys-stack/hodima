<?php
/**
 * ابزار تست: صفحه راست‌به‌چپ مثل سایت فارسی (سایت ابزار تست انگلیسی است و فایل زبان فارسی ندارد).
 * فقط وقتی متغیر محیطی HODIMA_RTL=1 باشد؛ بدون آن هیچ اثری ندارد.
 */

if ( getenv( 'HODIMA_RTL' ) ) {
	add_action( 'after_setup_theme', static function (): void {
		global $wp_locale;
		if ( $wp_locale instanceof WP_Locale ) {
			$wp_locale->text_direction = 'rtl';
		}
	}, 0 );
}
