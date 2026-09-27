<?php
/**
 * Plugin Name:       Hodima SEO
 * Plugin URI:        https://hodima.com
 * Description:       سئوی فنی هدیما: متاباکس سئو، اسکیمای JSON-LD، سایت‌مپ XML، robots.txt، ریدایرکت‌ها، آدرس تمیز بدون پایه، خوشه‌های موضوعی، لینک‌سازی داخلی، IndexNow، Google Indexing API و نسخه‌های ماشین‌خوان (llms.txt).
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      8.4
 * Requires Plugins:  hodima-core
 * Author:            آرین فتحی
 * Author URI:        https://hodima.com
 * License:           GPL-2.0-or-later
 * Text Domain:       hodima-seo
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

const HODIMA_SEO_VERSION = '1.0.0';
define( 'HODIMA_SEO_FILE', __FILE__ );
define( 'HODIMA_SEO_DIR', __DIR__ );
define( 'HODIMA_SEO_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );

/*
 * ماژول‌ها در plugins_loaded لود می‌شوند، نه هنگام include این فایل:
 * افزونه‌ها به ترتیب الفبا لود می‌شوند و Hodima Core (پیش‌نیاز) و ووکامرس
 * ممکن است هنوز لود نشده باشند.
 */
add_action( 'plugins_loaded', static function (): void {

	if ( function_exists( 'hodima_legacy_theme_active' ) && hodima_legacy_theme_active() ) {
		hodima_legacy_theme_notice( 'Hodima SEO' );
		return;
	}

	$modules = [
		// همان ترتیب بارگذاری functions.php قالب قبلی
		'components/google-indexing-api/main.php',
		'components/indexnow-sync/indexnow-sync.php',
		...array_map(
			static fn( string $file ): string => 'schema/' . basename( $file ),
			glob( HODIMA_SEO_DIR . '/schema/*.php' ) ?: []
		),
		'schema/admin/init.php',
		'inc/manual_related_link/manual_related_link.php',
		'core/router/router.php',
		'core/redirects/init.php',
		'core/seobox/seobox-init.php',
		'core/topiccluster/topiccluster-init.php',
		'core/cat-blog/cat-blog.php',
	];

	foreach ( $modules as $module ) {
		$path = HODIMA_SEO_DIR . '/' . $module;
		if ( is_file( $path ) ) {
			require_once $path;
		}
	}
}, 5 );

/*
 * قوانین بازنویسی (سایت‌مپ، robots، آدرس‌های بدون پایه، llms.txt، فید پادکست)
 * با فعال/غیرفعال شدن افزونه باید از نو ساخته شوند.
 */
register_activation_hook( __FILE__, static function (): void {
	delete_option( 'arian_router_flushed' ); // روتر در init بعدی flush می‌کند
	delete_option( 'rewrite_rules' );
} );

register_deactivation_hook( __FILE__, static function (): void {
	delete_option( 'rewrite_rules' );
} );
