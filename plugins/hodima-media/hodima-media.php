<?php
/**
 * Plugin Name:       Hodima Media
 * Plugin URI:        https://hodima.com
 * Description:       رسانه و محتوای تعاملی هدیما: استوری، ویدیو با شمارش بازدید، اسلایدر، سیستم رسانه (ویدیو، پادکست، FAQ، Google Discover)، اعلان‌ها، باکس‌های بازشونده و جدول داینامیک.
 * Version:           1.4.0
 * Requires at least: 6.5
 * Requires PHP:      8.4
 * Requires Plugins:  hodima-core
 * Author:            آرین فتحی
 * Author URI:        https://hodima.com
 * License:           GPL-2.0-or-later
 * Text Domain:       hodima-media
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

const HODIMA_MEDIA_VERSION = '1.4.0';
define( 'HODIMA_MEDIA_FILE', __FILE__ );
define( 'HODIMA_MEDIA_DIR', __DIR__ );
define( 'HODIMA_MEDIA_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );

/**
 * ماژول‌های افزونه. کلیدهای هر آرایه همان پارامترهای Hodima\Core\Module هستند.
 *
 * @return array<string, array<string, mixed>>
 */
function hodima_media_modules(): array {
	return [
		'expandable-boxes' => [
			'title'       => 'باکس‌های بازشونده',
			'description' => 'کوتاه کردن متن‌های بلند با دکمه «نمایش بیشتر» در محصول، دسته و نوشته.',
			'files'       => [ 'components/expandable-boxes/expandable-boxes.php' ],
			'icon'        => 'dashicons-editor-expand',
		],
		'slider' => [
			'title'       => 'اسلایدر',
			'description' => 'اسلایدرهای صفحه اصلی با preload تصویر اول (شورت‌کدهای hodima-slider).',
			'files'       => [ 'components/hodima-slider/hodima-slider.php' ],
			'settings'    => 'admin.php?page=hodima-slider',
			'icon'        => 'dashicons-images-alt2',
		],
		'stories' => [
			'title'       => 'استوری',
			'description' => 'استوری‌های ویدیویی با پخش‌کننده، آمار بازدید و اشتراک‌گذاری.',
			'files'       => [ 'components/hodima-Stories/hodima-Stories.php' ],
			'settings'    => 'admin.php?page=hodima-stories',
			'icon'        => 'dashicons-format-gallery',
		],
		'notifications' => [
			'title'       => 'اعلان‌ها',
			'description' => 'نوار و پنجره اعلان با زمان‌بندی و هدف‌گیری (صفحه، UTM، ارجاع‌دهنده).',
			'files'       => [
				'components/notification/notification-cpt.php',
				'components/notification/notification-metabox.php',
				'components/notification/notification-front.php',
			],
			'settings'    => 'edit.php?post_type=hd_notification',
			'icon'        => 'dashicons-megaphone',
		],
		'video' => [
			'title'       => 'ویدیوها',
			'description' => 'پست‌تایپ ویدیو با پخش‌کننده، زیرنویس، شمارش بازدید و اسکیمای VideoObject.',
			'files'       => [ 'components/video-watch/video-watch.php' ],
			'settings'    => 'edit.php?post_type=video',
			'recommends'  => [ 'media-system' ],
			'icon'        => 'dashicons-video-alt3',
		],
		'media-system' => [
			'title'       => 'سیستم رسانه',
			'description' => 'ویدیو، پادکست، FAQ و متن معرفی برای محصولات، دسته‌ها و نوشته‌ها (شورت‌کدهای hook_*)، و بخش Google Discover نوشته‌ها.',
			'files'       => [ 'media-system/media-init.php' ],
			'warning'     => 'ویدیو، پادکست، سوالات متداول و متن معرفی از صفحه‌های محصول، دسته و نوشته برداشته می‌شوند و تنظیمات Discover نوشته‌ها هم اثری نخواهند داشت (اطلاعات پاک نمی‌شود).',
			'icon'        => 'dashicons-format-video',
		],
		'dynamic-table' => [
			'title'       => 'جدول داینامیک',
			'description' => 'جدول مشخصات قابل ویرایش برای نوشته، برگه، محصول و دسته با اسکیمای Table و مشخصات محصول (شورت‌کد hodima_table).',
			'files'       => [ 'inc/hodima-table/hodima-table.php' ],
			'icon'        => 'dashicons-grid-view',
		],
	];
}

add_action( 'plugins_loaded', static function (): void {

	if ( function_exists( 'hodima_legacy_theme_active' ) && hodima_legacy_theme_active() ) {
		hodima_legacy_theme_notice( 'Hodima Media' );
		return;
	}

	// بدون Hodima Core، اسکیما مثل قبل در تگ جداگانه چاپ می‌شود
	if ( ! function_exists( 'hodima_schema_add' ) ) {
		require_once __DIR__ . '/inc/schema-fallback.php';
	}

	// بدون Hodima Core، هدر/تب مشترک پیشخوان نسخه ساده می‌گیرد
	if ( is_admin() && ! function_exists( 'hodima_admin_header' ) ) {
		require_once __DIR__ . '/inc/admin-ui-fallback.php';
	}

	$specs = hodima_media_modules();

	if ( class_exists( \Hodima\Core\Modules::class ) ) {
		\Hodima\Core\Modules::register(
			'media',
			'رسانه',
			'استوری، ویدیو، اسلایدر، سیستم رسانه، اعلان‌ها، باکس‌های بازشونده و جدول داینامیک.',
			HODIMA_MEDIA_FILE,
			HODIMA_MEDIA_VERSION,
			...array_map(
				static fn( string $id, array $spec ): \Hodima\Core\Module => new \Hodima\Core\Module( $id, ...$spec ),
				array_keys( $specs ),
				$specs
			)
		);
		\Hodima\Core\Modules::load( 'media' );
		$video_loaded = \Hodima\Core\Modules::is_loaded( 'media', 'video' );
	} else {
		foreach ( array_merge( ...array_column( $specs, 'files' ) ) as $file ) {
			require_once HODIMA_MEDIA_DIR . '/' . $file;
		}
		$video_loaded = true;
	}

	if ( $video_loaded ) {
		hodima_media_video_tweaks();
	}
}, 5 );

/**
 * پست‌تایپ «ویدیو» (منتقل‌شده از functions.php قالب)؛ فقط وقتی ماژول ویدیو روشن است.
 */
function hodima_media_video_tweaks(): void {

	// زیرنویس ویدیو (WebVTT)
	add_filter( 'upload_mimes', static function ( array $mimes ): array {
		$mimes['vtt'] = 'text/vtt';
		return $mimes;
	} );

	// فهرست ویدیوها یک «برگه» است؛ آرشیو پیش‌فرض با نامک آن برگه تداخل داشت و ۴۰۴ می‌داد
	add_filter( 'register_post_type_args', static function ( array $args, string $post_type ): array {
		if ( 'video' === $post_type ) {
			$args['public']      = true;
			$args['has_archive'] = false;
		}
		return $args;
	}, 99, 2 );
}

register_activation_hook( __FILE__, static fn() => delete_option( 'rewrite_rules' ) );
register_deactivation_hook( __FILE__, static fn() => delete_option( 'rewrite_rules' ) );
