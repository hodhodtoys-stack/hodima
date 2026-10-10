<?php
/**
 * ماژول «گوگل دیسکاور» — تنظیمات قابل تغییر از پیشخوان
 * Path: core/discover/discover-options.php
 *
 * SEO 2.1.8. تا 2.1.7 این مقدارها ثابت‌های کد بودند و فقط با تغییر کد عوض
 * می‌شدند (حداقل کلمه، مرز تازگی، عبارت‌های طعمه کلیک، آستانه‌های فرصت‌ها).
 * حالا در «ابزارهای هدیما ← گوگل دیسکاور ← تنظیمات» (discover-settings.php)؛
 * ثابت‌ها پیش‌فرض‌اند. همیشه لود می‌شود (فهرست آمادگی در REST هم اجرا می‌شود).
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** گزینه تنظیمات (autoload خاموش). */
const HODIMA_SEO_DISCOVER_SETTINGS_OPTION = 'hodima_discover_settings';

/**
 * مقدارهای پیش‌فرض.
 *
 * @return array{min_words: int, stale_days: int, clickbait: list<string>|null, opp_min_impressions: int, opp_ctr_ratio: int, opp_drop_ratio: int, alerts: bool, alert_drop: int, digest: bool, digest_email: string}
 */
function hodima_seo_discover_option_defaults(): array {
	return [
		'min_words'           => 300,   // کمتر = «مطلب کوتاه»
		'stale_days'          => 365,   // به‌روز نشده بیشتر از این = هشدار «تازگی»
		'clickbait'           => null,  // null = فهرست پیش‌فرض HODIMA_SEO_DISCOVER_CLICKBAIT
		'opp_min_impressions' => 0,     // ۰ = خودکار به اندازه سایت (hodima_seo_discover_opp_min_impressions)
		'opp_ctr_ratio'       => 60,    // ٪ میانگین نرخ کلیک سایت که کمتر از آن «کم‌کلیک» است
		'opp_drop_ratio'      => 50,    // نمایش کمتر از این ٪ دوره قبل = «افت»
		'alerts'              => true,  // هشدار افت و ورود صفحه تازه در پیشخوان
		'alert_drop'          => 40,    // افت نمایش ۷ روز آخر نسبت به ۷ روز قبل (٪) برای هشدار
		'digest'              => false, // خلاصه هفتگی با ایمیل
		'digest_email'        => '',    // خالی = ایمیل مدیر سایت
	];
}

/**
 * تنظیمات فعلی (با پیش‌فرض‌ها و محدوده‌های مجاز).
 *
 * @return array{min_words: int, stale_days: int, clickbait: list<string>|null, opp_min_impressions: int, opp_ctr_ratio: int, opp_drop_ratio: int, alerts: bool, alert_drop: int, digest: bool, digest_email: string}
 */
function hodima_seo_discover_options(): array {
	$saved = get_option( HODIMA_SEO_DISCOVER_SETTINGS_OPTION, [] );
	return hodima_seo_discover_sanitize_options( is_array( $saved ) ? $saved : [] );
}

/**
 * پاک‌سازی و محدود کردن (هم ذخیره، هم خواندن).
 *
 * @param array<string, mixed> $in
 * @return array{min_words: int, stale_days: int, clickbait: list<string>|null, opp_min_impressions: int, opp_ctr_ratio: int, opp_drop_ratio: int, alerts: bool, alert_drop: int, digest: bool, digest_email: string}
 */
function hodima_seo_discover_sanitize_options( array $in ): array {

	$d     = hodima_seo_discover_option_defaults();
	$int   = static fn( string $key, int $min, int $max ): int => isset( $in[ $key ] ) && is_numeric( $in[ $key ] ) ? max( $min, min( $max, (int) $in[ $key ] ) ) : $d[ $key ];
	$bool  = static fn( string $key ): bool => array_key_exists( $key, $in ) ? (bool) $in[ $key ] : $d[ $key ];
	$bait  = $in['clickbait'] ?? null;
	$email = sanitize_email( (string) ( $in['digest_email'] ?? '' ) );

	if ( is_array( $bait ) ) {
		$bait = array_values( array_unique( array_filter( array_map( static fn( mixed $p ): string => trim( sanitize_text_field( is_scalar( $p ) ? (string) $p : '' ) ), $bait ), static fn( string $p ): bool => '' !== trim( $p, " *\t" ) && mb_strlen( $p ) <= 60 ) ) );
	}

	return [
		'min_words'           => $int( 'min_words', 50, 5000 ),
		'stale_days'          => $int( 'stale_days', 30, 3650 ),
		'clickbait'           => is_array( $bait ) ? array_slice( $bait, 0, 300 ) : null,
		'opp_min_impressions' => $int( 'opp_min_impressions', 0, 100000 ),
		'opp_ctr_ratio'       => $int( 'opp_ctr_ratio', 10, 100 ),
		'opp_drop_ratio'      => $int( 'opp_drop_ratio', 10, 90 ),
		'alerts'              => $bool( 'alerts' ),
		'alert_drop'          => $int( 'alert_drop', 10, 90 ),
		'digest'              => $bool( 'digest' ),
		'digest_email'        => is_email( $email ) ? $email : '',
	];
}

/**
 * یک تنظیم.
 *
 * @return int|bool|string|list<string>|null
 */
function hodima_seo_discover_option( string $key ): int|bool|string|array|null {
	return hodima_seo_discover_options()[ $key ] ?? null;
}

/**
 * کمترین نمایش یک صفحه برای «کم‌کلیک» و «افت» در فرصت‌ها.
 *
 * «خودکار» (۰، پیش‌فرض از SEO 2.1.8): نصف میانه نمایش صفحه‌های دیده‌شده، بین
 * ۲۰ و ۲۰۰. باگ قبلی: عدد ثابت ۲۰۰ بود و در سایتی که صفحه‌هایش چند ده نمایش
 * دارند این دو گروه همیشه خالی می‌ماندند.
 *
 * @param array<string, mixed> $stats hodima_seo_discover_stats()
 */
function hodima_seo_discover_opp_min_impressions( array $stats ): int {

	$fixed = (int) hodima_seo_discover_option( 'opp_min_impressions' );

	return $fixed > 0 ? $fixed : hodima_seo_discover_opp_min_impressions_auto( $stats );
}

/**
 * مقدار «خودکار» کمترین نمایش (برای نمایش در تنظیمات هم).
 *
 * @param array<string, mixed> $stats
 */
function hodima_seo_discover_opp_min_impressions_auto( array $stats ): int {

	$values = array_values( array_filter( array_map( static fn( mixed $r ): int => (int) ( is_array( $r ) ? ( $r['impressions'] ?? 0 ) : 0 ), (array) ( $stats['rows'] ?? [] ) ) ) );

	if ( ! $values ) {
		return 200;
	}

	sort( $values );
	$mid    = intdiv( count( $values ), 2 );
	$median = count( $values ) % 2 ? $values[ $mid ] : intdiv( $values[ $mid - 1 ] + $values[ $mid ], 2 );

	return max( 20, min( 200, intdiv( $median, 2 ) ) );
}
