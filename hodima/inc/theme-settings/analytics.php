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

/**
 * شناسه GA4 معتبر برای این بازدید، یا رشته خالی اگر کد آمار نباید چاپ شود.
 */
function hodima_ga_active_id(): string {

	$ga_id = (string) hodima_setting( 'ga_id' );

	if ( '' === $ga_id || ! preg_match( '/^G-[A-Z0-9]{4,16}$/', $ga_id ) ) {
		return '';
	}

	if ( hodima_setting( 'ga_production' ) && 'production' !== wp_get_environment_type() ) {
		return '';
	}

	if ( hodima_setting( 'ga_skip_editors' ) && current_user_can( 'edit_posts' ) ) {
		return '';
	}

	return $ga_id;
}

/**
 * زمان بارگذاری gtag.js: now (پیش‌فرض، مثل قبل) | interaction (پس از اولین لمس/کلیک/اسکرول/کلید).
 * مقدار ناشناخته now حساب می‌شود.
 */
function hodima_ga_load_mode(): string {
	return 'interaction' === (string) hodima_setting( 'ga_load' ) ? 'interaction' : 'now';
}

add_action( 'wp_enqueue_scripts', 'hodima_enqueue_google_analytics', 1 );

function hodima_enqueue_google_analytics(): void {

	$ga_id = hodima_ga_active_id();

	if ( '' === $ga_id || 'now' !== hodima_ga_load_mode() ) {
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

/*
 * حالت «پس از اولین تعامل»: gtag و صف dataLayer همان لحظه ساخته می‌شوند (کدهای دیگر مثل رویداد
 * لینک‌های مرتبط افزونه SEO به window.gtag نیاز دارند و رویدادشان در صف می‌ماند)، ولی فایل سنگین
 * gtag.js (حدود ۱۰۰ کیلوبایت جاوااسکریپت و اتصال به گوگل) فقط وقتی بارگذاری می‌شود که بازدیدکننده
 * چیزی را لمس، کلیک، اسکرول یا تایپ کند؛ اگر تا ۶ ثانیه پس از بارگذاری صفحه هیچ تعاملی نشد هم به
 * هر حال بارگذاری می‌شود تا بازدید ثبت شود. صفحه‌ای که کمتر از ۶ ثانیه بدون هیچ تعاملی بسته شود
 * ثبت نمی‌شود (بازدیدهای فوری ربات‌ها و خروج‌های بی‌تعامل کمتر شمرده می‌شوند).
 */
add_action( 'wp_head', static function (): void {

	$ga_id = hodima_ga_active_id();

	if ( '' === $ga_id || 'interaction' !== hodima_ga_load_mode() ) {
		return;
	}

	$src = 'https://www.googletagmanager.com/gtag/js?id=' . rawurlencode( $ga_id );

	wp_print_inline_script_tag(
		sprintf(
			'window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag("js",new Date());gtag("config",%1$s);'
			. '(function(){var done=false,evs=["pointerdown","keydown","scroll","touchstart"];'
			. 'function go(){if(done)return;done=true;evs.forEach(function(e){removeEventListener(e,go,true);});'
			. 'var s=document.createElement("script");s.async=true;s.src=%2$s;document.head.appendChild(s);}'
			. 'evs.forEach(function(e){addEventListener(e,go,{capture:true,passive:true});});'
			. 'addEventListener("load",function(){setTimeout(go,6000);});})();',
			wp_json_encode( $ga_id ),
			wp_json_encode( $src, JSON_UNESCAPED_SLASHES )
		),
		[ 'id' => 'hodima-gtag-lazy' ]
	);
}, 2 );

add_filter( 'wp_resource_hints', static function ( array $urls, string $relation ): array {
	// در حالت تعاملی اتصال زودهنگام لازم نیست (و خودش جزو «شخص ثالث» بارگذاری اول می‌شد)
	if ( 'preconnect' === $relation && '' !== (string) hodima_setting( 'ga_id' ) && 'now' === hodima_ga_load_mode() ) {
		$urls[] = [ 'href' => 'https://www.googletagmanager.com', 'crossorigin' => 'anonymous' ];
	}
	return $urls;
}, 10, 2 );
