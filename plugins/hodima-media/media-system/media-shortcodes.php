<?php
/**
 * Media System — Shortcodes loader
 * Path: media-system/media-shortcodes.php
 *
 * نام شورت‌کدها ([hook_video]، [hook_voice]، [hook_faq]، [hook_intro]) و
 * کلاس‌های CSS «hook-*» عمدا عوض نشده‌اند: در محتوای سایت، قالب و CSS
 * سفارشی استفاده شده‌اند.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

add_action( 'wp_enqueue_scripts', 'hodima_media_register_assets' );

function hodima_media_register_assets(): void {

	$url = HODIMA_MEDIA_URL . '/media-system';

	wp_register_style( 'hook-media-css', $url . '/css/media-style.css', [], hodima_media_asset_version( 'css/media-style.css' ) );
	wp_register_script( 'hook-media-js', $url . '/js/media-style.js', [], hodima_media_asset_version( 'js/media-style.js' ), [
		'in_footer' => true,
		'strategy'  => 'defer',
	] );

	/*
	 * بارگذاری زودهنگام در head برای صفحه‌ای که سیستم رسانه‌اش فعال است؛
	 * بدون آن استایل فقط هنگام اجرای شورت‌کد (وسط body) صف می‌شد و بلوک‌ها
	 * اول بی‌استایل نمایش داده و بعد جابه‌جا می‌شدند (CLS).
	 */
	$queried = get_queried_object();

	[ $id, $context ] = match ( true ) {
		$queried instanceof WP_Post => [ (int) $queried->ID, 'post' ],
		$queried instanceof WP_Term => [ (int) $queried->term_id, 'term' ],
		default                     => [ 0, 'post' ],
	};

	if ( $id && hodima_media_is_enabled( $id, $context ) ) {
		hodima_media_enqueue_assets();
	}
}

/** فالبک: شورت‌کد روی صفحه‌ای که در head تشخیص داده نشده بود. */
function hodima_media_enqueue_assets(): void {
	wp_enqueue_style( 'hook-media-css' );
	wp_enqueue_script( 'hook-media-js' );
}

/**
 * تشخیص شیء و context شورت‌کد.
 *
 * @return array{0: int, 1: string} [ شناسه (۰ = هیچ), context ]
 */
function hodima_media_shortcode_context( mixed $atts ): array {

	$atts      = shortcode_atts( [ 'id' => 0, 'context' => '' ], is_array( $atts ) ? $atts : [] );
	$object_id = absint( $atts['id'] );
	$context   = sanitize_key( (string) $atts['context'] );

	if ( ! $object_id ) {
		$queried = get_queried_object();
		if ( $queried instanceof WP_Post ) {
			[ $object_id, $context ] = [ (int) $queried->ID, 'post' ];
		} elseif ( $queried instanceof WP_Term ) {
			[ $object_id, $context ] = [ (int) $queried->term_id, 'term' ];
		}
	}

	$context = hodima_media_context( $context );

	/*
	 * بلوک رسانه نوشته رمزدار، پیش‌نویس یا خصوصی نباید بدون دسترسی نمایش
	 * داده شود؛ شناسه دلخواه در شورت‌کد هم نباید آن را دور بزند.
	 * بدون Hodima Core، حداقل بررسی وردپرس (منتشرشده + بدون رمز) انجام می‌شود.
	 */
	if ( $object_id && 'post' === $context ) {
		$visible = function_exists( 'hodima_post_content_is_visible' )
			? hodima_post_content_is_visible( $object_id )
			: ! post_password_required( $object_id ) && ( 'publish' === get_post_status( $object_id ) || current_user_can( 'read_post', $object_id ) );
		if ( ! $visible ) {
			return [ 0, 'post' ];
		}
	}

	return [ $object_id, $context ];
}

/** سطح عنوان بلوک رسانه (پیش‌فرض h2؛ «none» یعنی بدون عنوان). */
function hodima_media_heading( string $text, mixed $level = 'h2' ): string {

	$level = strtolower( is_scalar( $level ) ? (string) $level : 'h2' );

	if ( '' === $text || 'none' === $level ) {
		return '';
	}

	$tag = in_array( $level, [ 'h2', 'h3', 'h4', 'h5', 'h6', 'p' ], true ) ? $level : 'h2';

	return sprintf( '<%1$s class="hook-media-title">%2$s</%1$s>', $tag, esc_html( $text ) );
}

/** کد جاسازی با کش (wp_oembed_get در هر بازدید درخواست HTTP می‌زند). */
function hodima_media_cached_oembed( string $url, array $args = [] ): string {

	$cache_key = 'hook_oembed_' . md5( $url . wp_json_encode( $args ) );
	$cached    = get_transient( $cache_key );

	if ( false !== $cached ) {
		return (string) $cached;
	}

	$embed = wp_oembed_get( $url, $args );
	$embed = is_string( $embed ) ? $embed : '';

	set_transient( $cache_key, $embed, '' === $embed ? HOUR_IN_SECONDS : WEEK_IN_SECONDS );

	return $embed;
}

/**
 * «متن کامل» زیر پلیر (ویدیو یا صوت) — برای ناشنوایان، جستجو و
 * کسی که نمی‌تواند صدا را پخش کند. بسته است تا صفحه را بلند نکند.
 */
function hodima_media_transcript_html( string $text, string $label ): string {

	$text = trim( $text );

	return '' === $text ? '' : sprintf(
		'<details class="hook-media-transcript"><summary>%1$s</summary><div class="hook-media-transcript__text">%2$s</div></details>',
		esc_html( $label ),
		wpautop( esc_html( $text ) )
	);
}

foreach ( [ 'video', 'voice', 'faq', 'intro' ] as $hodima_media_shortcode ) {
	require_once __DIR__ . '/shortcodes/' . $hodima_media_shortcode . '.php';
}
unset( $hodima_media_shortcode );
