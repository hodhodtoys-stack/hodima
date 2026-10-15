<?php
/**
 * CSS و JS صفحه اصلی
 * Path: hodima/home/assets.php
 *
 * 3.1.5: logic.php (۶۷۰ خط، چند کار) به چند فایل با یک کار تقسیم شد؛ نام توابع و هوک‌ها
 * و خروجی صفحه همان است. فهرست فایل‌ها: home/logic.php.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * لود هوشمند دارایی‌ها (CSS و JS)
 */
add_action( 'wp_enqueue_scripts', static function (): void {

	// فقط صفحه اصلی (قبلا برگه وبلاگ هم — is_home — بی‌دلیل این CSS/JS را می‌گرفت)
	if ( ! is_front_page() ) {
		return;
	}

	hodima_enqueue_asset( 'hodima-home-style', 'home/home.css' );
	hodima_enqueue_asset( 'hodima-home-js', 'home/home.js', [], [ 'in_footer' => true, 'strategy' => 'defer' ] ); // Vanilla JS؛ بدون jQuery
}, 20 );

// فلش اسکرولرها در «تنظیمات قالب ← صفحه اصلی» خاموش شده باشد، home.js و CSS آن‌ها را نشان نمی‌دهند
add_filter( 'body_class', static function ( array $classes ): array {

	if ( is_front_page() && function_exists( 'hodima_setting' ) && ! hodima_setting( 'home_scroll_arrows' ) ) {
		$classes[] = 'hodima-no-scroll-arrows';
	}

	return $classes;
} );
