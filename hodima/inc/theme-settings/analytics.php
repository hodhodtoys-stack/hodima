<?php
/**
 * Theme Settings — Google Analytics 4
 * Path: hodima/inc/theme-settings/analytics.php
 *
 * از theme-settings.php جدا شد (3.6.3، بخش ۱۶۱ HODIMA-AUDIT.md): کد بدون تغییر، فقط جابه‌جا؛
 * ترتیب بارگذاری (و ثبت هوک‌ها) همان ترتیب قبلی فایل است (فهرست: theme-settings.php).
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =========================================================================
 * ۶. Google Analytics 4
 * ========================================================================= */

add_action( 'wp_enqueue_scripts', 'hodima_enqueue_google_analytics', 1 );

function hodima_enqueue_google_analytics(): void {

	$ga_id = (string) hodima_setting( 'ga_id' );

	if ( '' === $ga_id || ! preg_match( '/^G-[A-Z0-9]{4,16}$/', $ga_id ) ) {
		return;
	}

	if ( hodima_setting( 'ga_production' ) && 'production' !== wp_get_environment_type() ) {
		return;
	}

	if ( hodima_setting( 'ga_skip_editors' ) && current_user_can( 'edit_posts' ) ) {
		return;
	}

	wp_enqueue_script(
		'hodima-gtag',
		'https://www.googletagmanager.com/gtag/js?id=' . rawurlencode( $ga_id ),
		[],
		null, // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion -- پارامتر نسخه به آدرس گوگل اضافه نشود
		[ 'in_footer' => false, 'strategy' => 'async' ]
	);

	wp_add_inline_script(
		'hodima-gtag',
		sprintf(
			'window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag("js",new Date());gtag("config",%s);',
			wp_json_encode( $ga_id )
		),
		'before'
	);
}

add_filter( 'wp_resource_hints', static function ( array $urls, string $relation ): array {
	if ( 'preconnect' === $relation && '' !== (string) hodima_setting( 'ga_id' ) ) {
		$urls[] = [ 'href' => 'https://www.googletagmanager.com', 'crossorigin' => 'anonymous' ];
	}
	return $urls;
}, 10, 2 );
