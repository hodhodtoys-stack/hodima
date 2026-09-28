<?php
/**
 * Hodima Core — به‌روزرسانی خودکار قالب و افزونه‌ها از گیت‌هاب
 * Path: plugins/hodima-core/includes/updates.php
 *
 * کتابخانه: Plugin Update Checker 5.6 (YahnisElsts، مجوز MIT) در
 * vendor/plugin-update-checker — استاندارد رایج برای افزونه/قالب‌هایی که
 * بیرون از مخزن wordpress.org منتشر می‌شوند؛ ترجمه فارسی هم دارد.
 *
 * منبع: پوشه release/ مخزن عمومی گیت‌هاب. برای هر بسته یک فایل JSON
 * (نسخه، آدرس ZIP، تغییرات) را bin/build.sh می‌سازد. وردپرس هر ۱۲ ساعت
 * JSON را می‌خواند و اگر نسخه بالاتر بود، در «پیشخوان ← به‌روزرسانی‌ها» و
 * فهرست افزونه‌ها/پوسته‌ها دکمه «به‌روزرسانی» نشان می‌دهد — مثل افزونه‌های
 * wordpress.org، برای هر کسی که قالب و افزونه‌ها را نصب کرده باشد.
 *
 * آدرس HEAD همیشه به شاخه پیش‌فرض مخزن اشاره می‌کند؛ پس نسخه جدید وقتی
 * منتشر می‌شود که تغییرات در شاخه پیش‌فرض ادغام شود.
 *
 * تغییر منبع (مثلا سرور اختصاصی): ثابت HODIMA_UPDATE_BASE_URL در wp-config.php
 * یا فیلتر hodima_update_base_url. خاموش کردن: فیلتر hodima_updates_enabled.
 */

declare(strict_types=1);

namespace Hodima\Core\Updates;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

defined( 'ABSPATH' ) || exit;

const DEFAULT_BASE_URL = 'https://raw.githubusercontent.com/hodhodtoys-stack/hodima/HEAD/release/';

/** آدرس پوشه فایل‌های JSON و ZIP (با اسلش پایانی). */
function base_url(): string {
	$base = defined( 'HODIMA_UPDATE_BASE_URL' ) ? (string) HODIMA_UPDATE_BASE_URL : DEFAULT_BASE_URL;
	return trailingslashit( (string) apply_filters( 'hodima_update_base_url', $base ) );
}

/**
 * بسته‌هایی که به‌روزرسانی می‌گیرند: نامک => مسیر فایل اصلی.
 * هر افزونه فقط وقتی فعال است (ثابت فایلش تعریف شده) ثبت می‌شود.
 *
 * @return array<string, string>
 */
function packages(): array {

	$packages = [ 'hodima-core' => HODIMA_CORE_PLUGIN_FILE ];

	foreach ( [
		'hodima-seo'      => 'HODIMA_SEO_FILE',
		'hodima-commerce' => 'HODIMA_COMMERCE_FILE',
		'hodima-media'    => 'HODIMA_MEDIA_FILE',
	] as $slug => $constant ) {
		if ( defined( $constant ) ) {
			$packages[ $slug ] = (string) constant( $constant );
		}
	}

	return $packages;
}

/** آیا قالب فعال (والد) همین قالب هدیما است؟ نامک پوشه باید hodima باشد. */
function theme_is_hodima(): bool {
	return 'hodima' === get_template() && 'Hodima' === wp_get_theme( get_template() )->get( 'Name' );
}

/** @var array<string, object> چکرهای ساخته‌شده (برای صفحه وضعیت) */
$GLOBALS['hodima_update_checkers'] = [];

/**
 * ثبت چکرها. در plugins_loaded با اولویت دیر اجرا می‌شود تا ثابت‌های فایل
 * افزونه‌های دیگر (که به ترتیب الفبا لود می‌شوند) تعریف شده باشند.
 */
add_action( 'plugins_loaded', static function (): void {

	if ( ! apply_filters( 'hodima_updates_enabled', true ) ) {
		return;
	}

	// نسخه نصب‌شده روی سایت ممکن است کتابخانه را نداشته باشد (نصب ناقص)
	$loader = HODIMA_CORE_PLUGIN_DIR . '/vendor/plugin-update-checker/plugin-update-checker.php';
	if ( ! is_file( $loader ) ) {
		return;
	}
	require_once $loader;

	if ( ! class_exists( PucFactory::class ) ) {
		return;
	}

	$base = base_url();

	foreach ( packages() as $slug => $file ) {
		$GLOBALS['hodima_update_checkers'][ $slug ] = PucFactory::buildUpdateChecker( $base . $slug . '.json', $file, $slug );
	}

	// قالب: از داخل Core ثبت می‌شود تا کتابخانه یک بار (نه در قالب و افزونه) لود شود
	if ( theme_is_hodima() ) {
		$GLOBALS['hodima_update_checkers']['hodima'] = PucFactory::buildUpdateChecker( $base . 'hodima.json', get_template_directory() . '/functions.php', 'hodima' );
	}
}, 20 );

/**
 * وضعیت برای صفحه «ابزارهای هدیما ← وضعیت».
 *
 * @return array{enabled:bool, base:string, count:int}
 */
function status(): array {
	return [
		'enabled' => ! empty( $GLOBALS['hodima_update_checkers'] ),
		'base'    => base_url(),
		'count'   => count( $GLOBALS['hodima_update_checkers'] ),
	];
}
