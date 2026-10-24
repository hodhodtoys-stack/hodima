<?php
/**
 * ماژول «ویدیوها» (Hodima Media): بارگذار، دارایی‌ها، قالب صفحه ویدیو
 * Path: components/video-watch/video-watch.php
 *
 * همه فایل‌های ماژول زیر PHPCS/PHPStan/ESLint/Stylelint بدون baseline
 * (HODIMA-AUDIT.md بخش ۲۲۱). نام‌های قدیمی hod_video_* و HOD_VIDEO_WATCH_*
 * برای سازگاری (کد بیرونی، آزمون‌ها) عوض نشده‌اند.
 *
 * phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- نام‌های قدیمی hod_video_* / HOD_VIDEO_WATCH_*
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

define( 'HOD_VIDEO_WATCH_PATH', trailingslashit( __DIR__ ) );
define( 'HOD_VIDEO_WATCH_URL', trailingslashit( HODIMA_MEDIA_URL . '/components/video-watch' ) );

/** نسخه دارایی = زمان تغییر فایل (نسخه ثابت «1.2.2» به‌روزرسانی را پنهان می‌کرد). */
function hod_video_watch_ver( string $file ): string {
	$path = HOD_VIDEO_WATCH_PATH . $file;
	return file_exists( $path ) ? (string) filemtime( $path ) : HODIMA_MEDIA_VERSION;
}

/** نسخه فایل کیت UI = زمان تغییرش (مثل hod_video_watch_ver). */
function hod_video_watch_kit_ver( string $path ): string {
	return file_exists( $path ) ? (string) filemtime( $path ) : HODIMA_MEDIA_VERSION;
}

foreach ( [ 'post-type.php', 'meta-boxes.php', 'vid-w-schema.php', 'db-video-watch.php', 'videos-page.php' ] as $hodima_vw_file ) {
	require_once HOD_VIDEO_WATCH_PATH . $hodima_vw_file;
}
unset( $hodima_vw_file );

/**
 * CSS و JS صفحه ویدیو.
 * فقط صفحه تک ویدیو؛ تا Media 2.1.3 «آرشیو نوع ویدیو» هم شرط بود که از Media 1.x
 * خاموش است (فهرست ویدیوها یک برگه است، hodima-media.php).
 */
function hod_video_watch_front_assets(): void {
	if ( ! is_singular( 'video' ) ) {
		return;
	}

	wp_enqueue_style( 'hod-video-watch-front', HOD_VIDEO_WATCH_URL . 'video-watch.front.css', [], hod_video_watch_ver( 'video-watch.front.css' ) );
	wp_enqueue_script( 'hod-video-watch-front', HOD_VIDEO_WATCH_URL . 'video-watch.front.js', [], hod_video_watch_ver( 'video-watch.front.js' ), true );

	// آدرس ثبت آمار و کلید (به‌همراه آدرس کلید تازه برای صفحه‌های کش‌شده)
	wp_localize_script(
		'hod-video-watch-front',
		'hvwData',
		[
			'restUrl'  => esc_url_raw( rest_url( 'video-watch/v1/track' ) ),
			'nonceUrl' => esc_url_raw( rest_url( 'video-watch/v1/nonce' ) ),
			// کلید مستقل از کاربر (db-video-watch.php)؛ nonce وردپرس برای کاربر واردشده همیشه رد می‌شد
			'nonce'    => class_exists( 'Hodima_Video_Watch_Analytics' ) ? Hodima_Video_Watch_Analytics::token() : '',
		]
	);
}
add_action( 'wp_enqueue_scripts', 'hod_video_watch_front_assets' );

/** متن پیشنهادی صفحه سیاست حریم خصوصی (ابزارها ← راهنمای سیاست حریم خصوصی). */
function hod_video_watch_privacy_policy_text(): void {
	if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
		return;
	}

	$text  = '<h3>' . esc_html( 'شمارنده بازدید ویدیوها' ) . '</h3>';
	$text .= '<p class="privacy-policy-tutorial">' . esc_html( 'این متن پیشنهادی است؛ مطابق وضع کسب‌وکار خود ویرایش کنید.' ) . '</p>';
	$text .= '<p>' . esc_html( 'برای اینکه یک بازدیدکننده در یک روز چند بار شمرده نشود و بازدید جعلی ثبت نشود، دو نشانه یک‌طرفه (هش) — یکی از نشانی IP، نام مرورگر و تاریخ همان روز و دیگری از نشانی IP و تاریخ — به‌صورت موقت (حداکثر یک روز) نگه داشته می‌شود؛ نشانی IP ذخیره نمی‌شود.' ) . '</p>';
	wp_add_privacy_policy_content( 'هدیما — ویدیو', wp_kses_post( $text ) );
}
add_action( 'admin_init', 'hod_video_watch_privacy_policy_text' );

/** پیش‌بارگذاری کاور (بزرگ‌ترین عنصر بالای صفحه ویدیو = LCP). */
function hod_video_watch_preload_poster(): void {
	if ( ! is_singular( 'video' ) ) {
		return;
	}

	$post_id = (int) get_queried_object_id();
	// همان آدرسی که پلیر در poster دارد (includes/poster.php)؛ وگرنه دو بار دانلود می‌شد
	$video_thumb = function_exists( 'hodima_video_watch_poster_url' )
		? hodima_video_watch_poster_url( $post_id )
		: (string) ( get_post_meta( $post_id, '_hod_video_thumbnail', true ) ?: get_the_post_thumbnail_url( $post_id, 'full' ) );

	if ( '' !== $video_thumb ) {
		echo '<link rel="preload" as="image" fetchpriority="high" href="' . esc_url( $video_thumb ) . '">' . "\n";
	}
}
add_action( 'wp_head', 'hod_video_watch_preload_poster', 1 );

/**
 * CSS/JS کادر «تنظیمات ویدیو».
 * فقط صفحه افزودن/ویرایش ویدیو؛ تا Media 2.1.3 فهرست ویدیوها هم کتابخانه
 * رسانه (wp_enqueue_media) و اسکریپت کادر را بی‌دلیل لود می‌کرد.
 */
function hod_video_watch_admin_assets(): void {
	$screen = get_current_screen();
	if ( ! $screen || 'video' !== $screen->post_type || 'post' !== $screen->base ) {
		return;
	}
	wp_enqueue_media();
	// کیت UI مشترک پیشخوان (tools/ui-kit؛ تب، رسانه، لیست تکرارشونده، …) با پیشوند hdm-؛ کپی آن را bin/build.sh (و link.sh) در ui/ می‌نویسد
	$kit_path = HODIMA_MEDIA_DIR . '/ui/';
	$kit_url  = HODIMA_MEDIA_URL . '/ui/';
	wp_enqueue_style( 'hod-video-watch-ui', $kit_url . 'ui-kit.css', [], hod_video_watch_kit_ver( $kit_path . 'ui-kit.css' ) );
	wp_enqueue_script( 'hod-video-watch-ui', $kit_url . 'ui-kit.js', [], hod_video_watch_kit_ver( $kit_path . 'ui-kit.js' ), true );
	wp_enqueue_style( 'hod-video-watch-admin', HOD_VIDEO_WATCH_URL . 'video-watch.admin.css', [ 'hod-video-watch-ui' ], hod_video_watch_ver( 'video-watch.admin.css' ) );
	wp_enqueue_script( 'hod-video-watch-admin', HOD_VIDEO_WATCH_URL . 'video-watch.admin.js', [ 'hod-video-watch-ui' ], hod_video_watch_ver( 'video-watch.admin.js' ), true );
}
add_action( 'admin_enqueue_scripts', 'hod_video_watch_admin_assets' );

/**
 * قالب صفحه ویدیو.
 * قالب سایت می‌تواند single-video.php خودش را داشته باشد (تا Media 2.1.3
 * همیشه قالب افزونه به‌زور جایگزین می‌شد)؛ قالب هدیما ندارد و همین قالب افزونه لود می‌شود.
 */
function hod_video_watch_template_include( string $template ): string {
	if ( ! is_singular( 'video' ) || '' !== locate_template( [ 'single-video.php' ] ) ) {
		return $template;
	}
	return HOD_VIDEO_WATCH_PATH . 'template-video.php';
}
add_filter( 'template_include', 'hod_video_watch_template_include' );
