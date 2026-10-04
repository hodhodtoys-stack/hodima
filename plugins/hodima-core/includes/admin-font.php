<?php
/**
 * فونت Vazirmatn در کل پیشخوان
 * Path: hodima-core/includes/admin-font.php
 *
 * از functions.php قالب منتقل شد — بازسازی قالب، مرحله ۲. فونت پیشخوان به
 * قالب سایت ربطی ندارد؛ با عوض شدن قالب، پیشخوان نباید به فونت پیش‌فرض
 * برگردد. فایل‌های فونت هم حالا همراه همین افزونه‌اند (assets/fonts) و
 * hodima_core_font_url() برای نقشه سایت XSL افزونه SEO هم استفاده می‌شود.
 *
 * خاموش کردن: add_filter( 'hodima_admin_font', '__return_false' );
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** آدرس فایل فونت همراه Core؛ $weight یکی از Regular، Medium، Bold، Black. */
function hodima_core_font_url( string $weight = 'Regular' ): string {
	return HODIMA_CORE_PLUGIN_URL . '/assets/fonts/Vazirmatn-' . rawurlencode( $weight ) . '.woff2';
}

add_action( 'admin_head', 'hodima_core_admin_font', 5 );

function hodima_core_admin_font(): void {

	// قالب قبل از 2.3.0 همین استایل را خودش چاپ می‌کند
	if ( hodima_theme_has_legacy_logic() || ! apply_filters( 'hodima_admin_font', true ) ) {
		return;
	}

	$faces = '';
	foreach ( [ 'Regular' => 400, 'Medium' => 500, 'Bold' => 700, 'Black' => 900 ] as $name => $weight ) {
		$faces .= sprintf(
			"@font-face{font-family:'Vazirmatn';src:url('%s') format('woff2');font-weight:%d;font-style:normal;font-display:swap}",
			esc_url( hodima_core_font_url( $name ) ),
			$weight
		);
	}

	// همان انتخابگرهای قبلی قالب: قفل فونت روی ساختار پیشخوان و نوار مدیریت
	$targets = 'body,#wpbody,.wp-core-ui,#wpadminbar *,.wp-admin input,.wp-admin select,.wp-admin textarea,.wp-admin button,.wp-admin a,.wp-admin p,.wp-admin h1,.wp-admin h2,.wp-admin h3,.wp-admin h4,.wp-admin h5,.wp-admin h6';

	printf(
		'<style id="hodima-admin-font">%s%s{font-family:\'Vazirmatn\',system-ui,-apple-system,sans-serif!important}</style>' . "\n",
		$faces, // phpcs:ignore — فقط آدرس‌های esc_url شده
		$targets
	);
}
