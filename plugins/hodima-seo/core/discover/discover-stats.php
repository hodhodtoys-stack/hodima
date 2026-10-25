<?php
/**
 * ماژول «گوگل دیسکاور» — آمار واقعی دیسکاور از سرچ کنسول
 * Path: core/discover/discover-stats.php
 *
 * گزارش «دیسکاور» سرچ کنسول (searchAnalytics با type=discover): کلیک و
 * نمایش هر صفحه در ۲۸ روز آخر و ۲۸ روز پیش از آن، و روند روزانه ۹۰ روز
 * (از SEO 2.1.2؛ قبلا فقط ۲۸ روز آخر و هر روز آمار قبلی جایش را می‌گرفت).
 * روزی یک بار (WP-Cron) و با دکمه «به‌روزرسانی»
 * گرفته می‌شود و در گزینه hodima_discover_sc_stats می‌ماند؛ هیچ بازدیدی از
 * سایت به گوگل درخواست نمی‌زند.
 *
 * از SEO 2.5.0 اتصال سرچ کنسول (کلید، property، توکن، کوئری) در
 * inc/search-console.php است و این فایل فقط دریافت و ذخیره آمار دیسکاور را دارد؛
 * نام‌های قدیمی توابع/ثابت‌ها همین‌جا مانده و به آن فایل می‌سپارند.
 *
 * کلید (از SEO 2.1.5): «کلید جدای دیسکاور» که مدیر در تب «آمار سرچ کنسول»
 * می‌چسباند؛ وگرنه همان «سرویس اکانت» ماژول Google Indexing — از گزینه و
 * ثابت wp-config همان ماژول، پس با خاموش بودن آن ماژول هم کار می‌کند. باگ
 * قبلی (تا SEO 2.1.4): کلید فقط از کلاس آن ماژول خوانده می‌شد؛ با خاموش
 * کردنش آمار بی‌صدا قطع می‌شد و پیام «کلید تنظیم نشده» می‌داد، و عوض کردن
 * حساب (ایمیل) فقط از تنظیمات Indexing ممکن بود. دسترسی فقط‌خواندنی جدا
 * (webmasters.readonly؛ توکن جدا برای هر حساب). ایمیل حساب سرویس باید در
 * سرچ کنسول کاربر همان property باشد.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

require_once dirname( __DIR__, 2 ) . '/inc/search-console.php'; // اتصال مشترک به سرچ کنسول (SEO 2.5.0؛ قبلا همین‌جا بود)

/*
 * کلید، property، توکن و کوئری سرچ کنسول به inc/search-console.php رفتند تا
 * ماژول‌های دیگر (مشاور سئو) هم بدون روشن بودن دیسکاور از آن‌ها بخوانند.
 * نام ثابت‌ها و توابع قدیمی دیسکاور می‌ماند و فقط به همان‌جا می‌سپارد.
 */

/** نام گزینه تنظیمات (property). */
const HODIMA_SEO_DISCOVER_SC_OPTION = HODIMA_SEO_SC_OPTION;

/** نام گزینه «کلید جدای دیسکاور» (JSON حساب سرویس؛ autoload خاموش). */
const HODIMA_SEO_DISCOVER_SC_KEY_OPTION = HODIMA_SEO_SC_KEY_OPTION;

/** گزینه propertyهایی که حساب سرویس در سرچ کنسول به آن‌ها دسترسی دارد. */
const HODIMA_SEO_DISCOVER_SC_SITES_OPTION = HODIMA_SEO_SC_SITES_OPTION;

/** رویداد روزانه دریافت آمار. */
const HODIMA_SEO_DISCOVER_SC_CRON = 'hodima_discover_sc_refresh';

/** منبع کلید فعلی: own، indexing یا رشته خالی. */
function hodima_seo_discover_sc_key_source(): string {
	return hodima_seo_sc_key_source();
}

/** JSON سرویس اکانت (کلید جدای دیسکاور، وگرنه ماژول Google Indexing)، یا رشته خالی. */
function hodima_seo_discover_sc_key_json(): string {
	return hodima_seo_sc_key_json();
}

/**
 * بررسی JSON حساب سرویس پیش از ذخیره.
 *
 * @return array{ok: bool, message: string, email: string}
 */
function hodima_seo_discover_sc_validate_key( string $json ): array {
	return hodima_seo_sc_validate_key( $json );
}

/** ایمیل سرویس اکانت (برای راهنمای افزودن کاربر در سرچ کنسول). */
function hodima_seo_discover_sc_email(): string {
	return hodima_seo_sc_email();
}

/** property انتخاب‌شده در تنظیمات (خالی = خودکار). */
function hodima_seo_discover_sc_property(): string {
	return hodima_seo_sc_property();
}

/** property واردشده مدیر ← شکل سرچ کنسول، یا خطای فارسی. */
function hodima_seo_discover_sc_clean_property( string $raw ): string|WP_Error {
	return hodima_seo_sc_clean_property( $raw );
}

/**
 * propertyهای ذخیره‌شده از آخرین اتصال.
 *
 * @return list<string>
 */
function hodima_seo_discover_sc_sites(): array {
	return hodima_seo_sc_sites();
}

/**
 * propertyهایی که به ترتیب امتحان می‌شوند.
 *
 * @return list<string>
 */
function hodima_seo_discover_sc_candidates(): array {
	return hodima_seo_sc_candidates();
}

/**
 * یک درخواست گزارش دیسکاور برای یک property.
 *
 * @param list<string> $dimensions page یا date
 * @return array{rows: list<array<string, mixed>>}|WP_Error
 */
function hodima_seo_discover_sc_query( string $property, string $token, string $start, string $end, array $dimensions = [ 'page' ], int $row_limit = 5000 ): array|WP_Error {
	return hodima_seo_sc_query( $property, $token, $start, $end, $dimensions, $row_limit, 'discover' );
}

/**
 * ردیف‌های پاسخ ← [ کلید ← کلیک/نمایش ] و جمع. کلید صفحه با
 * hodima_seo_discover_url_key (آدرس‌های هم‌مسیر و با پارامتر ردیابی یکی می‌شوند)،
 * کلید تاریخ همان «Y-m-d».
 *
 * @param list<array<string, mixed>> $rows
 * @return array{rows: array<string, array{clicks: int, impressions: int}>, totals: array{clicks: int, impressions: int}}
 */
function hodima_seo_discover_sc_rows( array $rows, bool $by_page = true ): array {

	$out    = [];
	$totals = [ 'clicks' => 0, 'impressions' => 0 ];

	foreach ( $rows as $row ) {
		$raw = (string) ( $row['keys'][0] ?? '' );
		if ( '' === $raw ) {
			continue;
		}
		$clicks      = (int) round( (float) ( $row['clicks'] ?? 0 ) );
		$impressions = (int) round( (float) ( $row['impressions'] ?? 0 ) );
		$key         = $by_page ? hodima_seo_discover_url_key( $raw ) : $raw;

		$out[ $key ] = [
			'clicks'      => ( $out[ $key ]['clicks'] ?? 0 ) + $clicks,
			'impressions' => ( $out[ $key ]['impressions'] ?? 0 ) + $impressions,
		];
		$totals['clicks']      += $clicks;
		$totals['impressions'] += $impressions;
	}

	if ( ! $by_page ) {
		ksort( $out );
	}

	return [ 'rows' => $out, 'totals' => $totals ];
}

/** تعداد روزهای نمودار روزانه. */
const HODIMA_SEO_DISCOVER_SC_DAILY_DAYS = 90;

/**
 * بازه‌ها به وقت اقیانوس آرام (سرچ کنسول روزها را به همین وقت می‌شمارد؛
 * قبلا UTC بود و مرز روزها چند ساعت جابه‌جا می‌شد). پایان: ۲ روز پیش (داده
 * دیسکاور با تاخیر می‌رسد).
 *
 * @return array{start: string, end: string, prev_start: string, prev_end: string, daily_start: string}
 */
function hodima_seo_discover_sc_ranges(): array {

	$end = new DateTimeImmutable( 'today -2 days', new DateTimeZone( 'America/Los_Angeles' ) );
	$day = static fn( int $back ): string => $end->modify( "-{$back} days" )->format( 'Y-m-d' );

	return [
		'start'       => $day( 27 ),
		'end'         => $end->format( 'Y-m-d' ),
		'prev_start'  => $day( 55 ),
		'prev_end'    => $day( 28 ),
		'daily_start' => $day( HODIMA_SEO_DISCOVER_SC_DAILY_DAYS - 1 ),
	];
}

/**
 * دریافت آمار و ذخیره: ۲۸ روز آخر به تفکیک صفحه، ۲۸ روز پیش از آن (مقایسه)،
 * و ۹۰ روز روزانه کل سایت (نمودار). سه درخواست برای یک property؛ اگر
 * درخواست اصلی موفق باشد و یکی از دو درخواست دیگر نه، داده قبلی همان بخش
 * می‌ماند.
 *
 * @return true|WP_Error
 */
function hodima_seo_discover_sc_refresh(): bool|WP_Error {

	$token = hodima_seo_sc_token();

	if ( is_wp_error( $token ) ) {
		hodima_seo_discover_sc_store_error( $token->get_error_message() );
		return $token;
	}

	// propertyهای در دسترس حساب (برای انتخاب خودکار و پیام خطا)؛ شکستش مانع آمار نیست
	$sites = hodima_seo_sc_list_sites( $token );
	if ( is_array( $sites ) ) {
		update_option( HODIMA_SEO_DISCOVER_SC_SITES_OPTION, $sites, false );
	}

	$range = hodima_seo_discover_sc_ranges();
	$last  = null;

	// یک درخواست، با یک بار توکن تازه اگر منقضی بود (۴۰۱)
	$query = static function ( string $property, string $start, string $end, array $dims, int $limit = 5000 ) use ( &$token ): array|WP_Error {
		$result = hodima_seo_discover_sc_query( $property, (string) $token, $start, $end, $dims, $limit );
		if ( is_wp_error( $result ) && 401 === (int) ( $result->get_error_data()['status'] ?? 0 ) ) {
			$fresh = hodima_seo_sc_token( true );
			if ( is_wp_error( $fresh ) ) {
				return $fresh;
			}
			$token  = $fresh;
			$result = hodima_seo_discover_sc_query( $property, $token, $start, $end, $dims, $limit );
		}
		return $result;
	};

	foreach ( hodima_seo_discover_sc_candidates() as $property ) {

		$result = $query( $property, $range['start'], $range['end'], [ 'page' ] );

		if ( is_wp_error( $result ) ) {
			if ( hodima_seo_sc_is_key_error( $result ) ) {
				hodima_seo_discover_sc_store_error( $result->get_error_message() ); // خطای توکن
				return $result;
			}
			$last = $result;
			continue;
		}

		$old     = get_option( HODIMA_SEO_DISCOVER_STATS_OPTION, [] );
		$old     = is_array( $old ) && ( $old['property'] ?? '' ) === $property ? $old : [];
		$current = hodima_seo_discover_sc_rows( $result['rows'] );
		$prev    = $query( $property, $range['prev_start'], $range['prev_end'], [ 'page' ] );
		$daily   = $query( $property, $range['daily_start'], $range['end'], [ 'date' ] );
		// تفکیک دستگاه و کشور ۲۸ روز آخر (SEO 2.1.8)
		$devices = $query( $property, $range['start'], $range['end'], [ 'device' ] );
		$country = $query( $property, $range['start'], $range['end'], [ 'country' ], 250 );

		update_option( HODIMA_SEO_DISCOVER_STATS_OPTION, [
			'property' => $property,
			'fetched'  => time(),
			'start'    => $range['start'],
			'end'      => $range['end'],
			'totals'   => $current['totals'],
			'rows'     => $current['rows'],
			'prev'     => is_wp_error( $prev )
				? ( $old['prev'] ?? [] )
				: [ 'start' => $range['prev_start'], 'end' => $range['prev_end'] ] + hodima_seo_discover_sc_rows( $prev['rows'] ),
			'daily'    => is_wp_error( $daily ) ? ( $old['daily'] ?? [] ) : hodima_seo_discover_sc_rows( $daily['rows'], false )['rows'],
			'devices'  => is_wp_error( $devices ) ? ( $old['devices'] ?? [] ) : hodima_seo_discover_sc_rows( $devices['rows'], false )['rows'],
			'countries' => is_wp_error( $country ) ? ( $old['countries'] ?? [] ) : hodima_seo_discover_sc_rows( $country['rows'], false )['rows'],
			'error'    => '',
		], false );

		// تاریخچه بلندمدت و روزانه صفحه‌های تغییرکرده (discover-history.php؛ شکستش آمار اصلی را خراب نمی‌کند)
		if ( function_exists( 'hodima_seo_discover_history_update' ) ) {
			hodima_seo_discover_history_update( $property, $query, $range, $daily );
		}

		// هشدار افت و صفحه‌های تازه در دیسکاور (discover-alerts.php)
		if ( function_exists( 'hodima_seo_discover_alerts_update' ) ) {
			hodima_seo_discover_alerts_update();
		}

		return true;
	}

	$message = hodima_seo_sc_failure_message( $last );

	hodima_seo_discover_sc_store_error( $message );

	return new WP_Error( 'hodima_discover_sc_failed', $message );
}

/** ثبت خطا بدون پاک کردن آخرین آمار موفق. */
function hodima_seo_discover_sc_store_error( string $message ): void {
	$stored          = get_option( HODIMA_SEO_DISCOVER_STATS_OPTION, [] );
	$stored          = is_array( $stored ) ? $stored : [];
	$stored['error'] = $message;
	update_option( HODIMA_SEO_DISCOVER_STATS_OPTION, $stored, false );
}

/* ── زمان‌بندی روزانه (فقط وقتی کلید هست) ── */
add_action( HODIMA_SEO_DISCOVER_SC_CRON, static function (): void {
	if ( '' !== hodima_seo_discover_sc_key_json() ) {
		hodima_seo_discover_sc_refresh();
	}
} );

add_action( 'admin_init', static function (): void {
	$has_key   = '' !== hodima_seo_discover_sc_key_json();
	$scheduled = (bool) wp_next_scheduled( HODIMA_SEO_DISCOVER_SC_CRON );
	if ( $has_key && ! $scheduled ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', HODIMA_SEO_DISCOVER_SC_CRON );
	} elseif ( ! $has_key && $scheduled ) {
		wp_clear_scheduled_hook( HODIMA_SEO_DISCOVER_SC_CRON ); // کلید برداشته شد: رویداد بی‌کار نماند
	}
} );
