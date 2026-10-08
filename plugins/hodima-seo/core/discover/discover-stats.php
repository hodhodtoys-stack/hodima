<?php
/**
 * ماژول «Google Discover» — آمار واقعی Discover از Search Console
 * Path: core/discover/discover-stats.php
 *
 * گزارش «Discover» سرچ کنسول (searchAnalytics با type=discover): کلیک و
 * نمایش هر صفحه در ۲۸ روز آخر. روزی یک بار (WP-Cron) و با دکمه «به‌روزرسانی»
 * گرفته می‌شود و در گزینه hodima_discover_sc_stats می‌ماند؛ هیچ بازدیدی از
 * سایت به گوگل درخواست نمی‌زند.
 *
 * کلید: همان «سرویس اکانت» ماژول Google Indexing (Hodima_GI_Helper)، با
 * دسترسی فقط‌خواندنی جدا (webmasters.readonly؛ توکن جدا). ماژول Indexing
 * عمدا فقط دسترسی indexing می‌گیرد (اصل حداقل دسترسی). ایمیل سرویس اکانت
 * باید در Search Console کاربر همان property باشد (برای Indexing API هم لازم
 * است، پس معمولا هست).
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** نام گزینه تنظیمات (property). */
const HODIMA_SEO_DISCOVER_SC_OPTION = 'hodima_discover_sc_settings';

/** رویداد روزانه دریافت آمار. */
const HODIMA_SEO_DISCOVER_SC_CRON = 'hodima_discover_sc_refresh';

/** JSON سرویس اکانت (ماژول Google Indexing یا ثابت wp-config)، یا رشته خالی. */
function hodima_seo_discover_sc_key_json(): string {

	if ( class_exists( 'Hodima_GI_Helper' ) ) {
		return Hodima_GI_Helper::service_account_json();
	}

	return defined( 'HODIMA_GI_SERVICE_ACCOUNT_JSON' ) ? (string) HODIMA_GI_SERVICE_ACCOUNT_JSON : '';
}

/** ایمیل سرویس اکانت (برای راهنمای افزودن کاربر در Search Console). */
function hodima_seo_discover_sc_email(): string {
	$key = json_decode( hodima_seo_discover_sc_key_json(), true );
	return is_array( $key ) ? sanitize_email( (string) ( $key['client_email'] ?? '' ) ) : '';
}

/** property انتخاب‌شده در تنظیمات (خالی = خودکار). */
function hodima_seo_discover_sc_property(): string {
	$settings = get_option( HODIMA_SEO_DISCOVER_SC_OPTION, [] );
	return is_array( $settings ) ? trim( (string) ( $settings['property'] ?? '' ) ) : '';
}

/**
 * propertyهایی که به ترتیب امتحان می‌شوند: انتخاب مدیر، وگرنه آدرس سایت
 * (پیشوند URL) و بعد دامنه (sc-domain:).
 *
 * @return list<string>
 */
function hodima_seo_discover_sc_candidates(): array {

	$chosen = hodima_seo_discover_sc_property();

	if ( '' !== $chosen ) {
		return [ $chosen ];
	}

	$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );

	return array_values( array_unique( [ trailingslashit( home_url() ), 'sc-domain:' . preg_replace( '/^www\./', '', $host ) ] ) );
}

/**
 * توکن دسترسی فقط‌خواندنی Search Console، یا پیام خطا (WP_Error).
 */
function hodima_seo_discover_sc_token( bool $force = false ): string|WP_Error {

	if ( ! $force ) {
		$cached = get_transient( 'hodima_discover_sc_token' );
		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}
	}

	$key = json_decode( hodima_seo_discover_sc_key_json(), true );

	if ( ! is_array( $key ) || empty( $key['client_email'] ) || empty( $key['private_key'] ) || empty( $key['token_uri'] ) ) {
		return new WP_Error( 'hodima_discover_no_key', 'کلید سرویس اکانت گوگل تنظیم نشده است (ماژول Google Indexing ← تنظیمات).' );
	}

	$enc = static fn( string $d ): string => rtrim( strtr( base64_encode( $d ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- base64url استاندارد JWT گوگل
	$now = time();

	$header = $enc( (string) wp_json_encode( [ 'alg' => 'RS256', 'typ' => 'JWT' ] ) );
	$claim  = $enc( (string) wp_json_encode( [
		'iss'   => $key['client_email'],
		'scope' => 'https://www.googleapis.com/auth/webmasters.readonly',
		'aud'   => $key['token_uri'],
		'iat'   => $now,
		'exp'   => $now + 3600,
	] ) );

	$signature = '';
	if ( ! openssl_sign( $header . '.' . $claim, $signature, (string) $key['private_key'], 'SHA256' ) ) {
		return new WP_Error( 'hodima_discover_sign', 'کلید خصوصی سرویس اکانت نامعتبر است.' );
	}

	$res = wp_remote_post( (string) $key['token_uri'], [
		'timeout' => 15,
		'body'    => [
			'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
			'assertion'  => $header . '.' . $claim . '.' . $enc( $signature ),
		],
	] );

	if ( is_wp_error( $res ) ) {
		return new WP_Error( 'hodima_discover_http', 'اتصال به گوگل برقرار نشد: ' . $res->get_error_message() );
	}

	$body  = json_decode( (string) wp_remote_retrieve_body( $res ), true );
	$token = is_array( $body ) ? (string) ( $body['access_token'] ?? '' ) : '';

	if ( 200 !== (int) wp_remote_retrieve_response_code( $res ) || '' === $token ) {
		return new WP_Error( 'hodima_discover_token', 'گوگل توکن نداد (کلید یا دسترسی سرویس اکانت را بررسی کنید).' );
	}

	set_transient( 'hodima_discover_sc_token', $token, max( 60, (int) ( $body['expires_in'] ?? 3600 ) - 300 ) );

	return $token;
}

/**
 * یک درخواست گزارش Discover برای یک property.
 *
 * @return array{rows: list<array<string, mixed>>}|WP_Error
 */
function hodima_seo_discover_sc_query( string $property, string $token, string $start, string $end ): array|WP_Error {

	$res = wp_remote_post(
		'https://searchconsole.googleapis.com/webmasters/v3/sites/' . rawurlencode( $property ) . '/searchAnalytics/query',
		[
			'timeout' => 20,
			'headers' => [ 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json' ],
			'body'    => (string) wp_json_encode( [
				'startDate'  => $start,
				'endDate'    => $end,
				'type'       => 'discover',
				'dimensions' => [ 'page' ],
				'rowLimit'   => 5000,
			] ),
		]
	);

	if ( is_wp_error( $res ) ) {
		return $res;
	}

	$code = (int) wp_remote_retrieve_response_code( $res );
	$body = json_decode( (string) wp_remote_retrieve_body( $res ), true );

	if ( 200 !== $code ) {
		$message = is_array( $body ) ? (string) ( $body['error']['message'] ?? '' ) : '';
		return new WP_Error( 'hodima_discover_sc_' . $code, $message, [ 'status' => $code ] );
	}

	return [ 'rows' => is_array( $body ) && is_array( $body['rows'] ?? null ) ? array_values( $body['rows'] ) : [] ];
}

/**
 * دریافت آمار ۲۸ روز آخر (تا ۲ روز پیش؛ داده Discover با تاخیر می‌رسد) و ذخیره.
 *
 * @return true|WP_Error
 */
function hodima_seo_discover_sc_refresh(): bool|WP_Error {

	$token = hodima_seo_discover_sc_token();

	if ( is_wp_error( $token ) ) {
		hodima_seo_discover_sc_store_error( $token->get_error_message() );
		return $token;
	}

	$end   = gmdate( 'Y-m-d', time() - 2 * DAY_IN_SECONDS );
	$start = gmdate( 'Y-m-d', time() - 29 * DAY_IN_SECONDS );
	$last  = null;

	foreach ( hodima_seo_discover_sc_candidates() as $property ) {

		$result = hodima_seo_discover_sc_query( $property, $token, $start, $end );

		// توکن منقضی: یک بار توکن تازه
		if ( is_wp_error( $result ) && 401 === (int) ( $result->get_error_data()['status'] ?? 0 ) ) {
			$token = hodima_seo_discover_sc_token( true );
			if ( is_wp_error( $token ) ) {
				hodima_seo_discover_sc_store_error( $token->get_error_message() );
				return $token;
			}
			$result = hodima_seo_discover_sc_query( $property, $token, $start, $end );
		}

		if ( is_wp_error( $result ) ) {
			$last = $result;
			continue;
		}

		$rows   = [];
		$totals = [ 'clicks' => 0, 'impressions' => 0 ];

		foreach ( $result['rows'] as $row ) {
			$url = (string) ( $row['keys'][0] ?? '' );
			if ( '' === $url ) {
				continue;
			}
			$clicks      = (int) round( (float) ( $row['clicks'] ?? 0 ) );
			$impressions = (int) round( (float) ( $row['impressions'] ?? 0 ) );
			$key         = hodima_seo_discover_url_key( $url );

			$rows[ $key ] = [
				'clicks'      => ( $rows[ $key ]['clicks'] ?? 0 ) + $clicks,
				'impressions' => ( $rows[ $key ]['impressions'] ?? 0 ) + $impressions,
			];
			$totals['clicks']      += $clicks;
			$totals['impressions'] += $impressions;
		}

		update_option( HODIMA_SEO_DISCOVER_STATS_OPTION, [
			'property' => $property,
			'fetched'  => time(),
			'start'    => $start,
			'end'      => $end,
			'totals'   => $totals,
			'rows'     => $rows,
			'error'    => '',
		], false );

		return true;
	}

	$status  = null !== $last ? (int) ( $last->get_error_data()['status'] ?? 0 ) : 0;
	$message = match ( true ) {
		403 === $status => 'Search Console اجازه نداد: ایمیل سرویس اکانت (' . hodima_seo_discover_sc_email() . ') را در Search Console ← تنظیمات ← کاربران و مجوزها به همین property اضافه کنید، یا property درست را پایین وارد کنید.',
		404 === $status => 'این property در Search Console پیدا نشد؛ آدرس دقیق property را پایین وارد کنید (مثلا https://example.com/ یا sc-domain:example.com).',
		null !== $last  => 'خطای Search Console: ' . $last->get_error_message(),
		default         => 'property برای امتحان وجود ندارد.',
	};

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
	if ( '' !== hodima_seo_discover_sc_key_json() && ! wp_next_scheduled( HODIMA_SEO_DISCOVER_SC_CRON ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', HODIMA_SEO_DISCOVER_SC_CRON );
	}
} );
