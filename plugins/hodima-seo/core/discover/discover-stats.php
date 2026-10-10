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

/** نام گزینه تنظیمات (property). */
const HODIMA_SEO_DISCOVER_SC_OPTION = 'hodima_discover_sc_settings';

/** نام گزینه «کلید جدای دیسکاور» (JSON حساب سرویس؛ autoload خاموش). */
const HODIMA_SEO_DISCOVER_SC_KEY_OPTION = 'hodima_discover_sc_key';

/** رویداد روزانه دریافت آمار. */
const HODIMA_SEO_DISCOVER_SC_CRON = 'hodima_discover_sc_refresh';

/**
 * منبع کلید فعلی: own (کلید جدای دیسکاور)، indexing (ماژول Google Indexing یا
 * ثابت HODIMA_GI_SERVICE_ACCOUNT_JSON در wp-config)، یا رشته خالی.
 */
function hodima_seo_discover_sc_key_source(): string {

	if ( '' !== trim( (string) get_option( HODIMA_SEO_DISCOVER_SC_KEY_OPTION, '' ) ) ) {
		return 'own';
	}

	return '' !== hodima_seo_discover_sc_indexing_json() ? 'indexing' : '';
}

/** JSON حساب سرویس ماژول Google Indexing (روشن یا خاموش)، یا رشته خالی. */
function hodima_seo_discover_sc_indexing_json(): string {

	if ( class_exists( 'Hodima_GI_Helper' ) ) {
		return Hodima_GI_Helper::service_account_json();
	}

	if ( defined( 'HODIMA_GI_SERVICE_ACCOUNT_JSON' ) && '' !== (string) HODIMA_GI_SERVICE_ACCOUNT_JSON ) {
		return (string) HODIMA_GI_SERVICE_ACCOUNT_JSON;
	}

	return (string) get_option( 'hodima_gi_json_key', '' ); // همان HODIMA_GI_OPTION_JSON؛ ماژول خاموش ثابتش را تعریف نکرده
}

/** JSON سرویس اکانت (کلید جدای دیسکاور، وگرنه ماژول Google Indexing)، یا رشته خالی. */
function hodima_seo_discover_sc_key_json(): string {
	$own = trim( (string) get_option( HODIMA_SEO_DISCOVER_SC_KEY_OPTION, '' ) );
	return '' !== $own ? $own : hodima_seo_discover_sc_indexing_json();
}

/**
 * بررسی JSON حساب سرویس پیش از ذخیره (همان قاعده ماژول Google Indexing).
 *
 * @return array{ok: bool, message: string, email: string}
 */
function hodima_seo_discover_sc_validate_key( string $json ): array {

	$key  = json_decode( $json, true );
	$fail = static fn( string $message ): array => [ 'ok' => false, 'message' => $message, 'email' => '' ];

	if ( ! is_array( $key ) ) {
		return $fail( 'متن واردشده JSON معتبر نیست؛ کل محتوای فایل JSON حساب سرویس را بچسبانید.' );
	}

	foreach ( [ 'type', 'client_email', 'private_key', 'token_uri' ] as $field ) {
		if ( empty( $key[ $field ] ) || ! is_string( $key[ $field ] ) ) {
			return $fail( "فیلد «{$field}» در فایل وجود ندارد." );
		}
	}

	if ( 'service_account' !== $key['type'] ) {
		return $fail( 'این فایل مربوط به حساب سرویس (Service Account) نیست.' );
	}

	if ( ! is_email( $key['client_email'] ) ) {
		return $fail( 'ایمیل حساب سرویس (client_email) معتبر نیست.' );
	}

	if ( function_exists( 'openssl_pkey_get_private' ) && false === @openssl_pkey_get_private( $key['private_key'] ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- کلید خراب فقط پیام فارسی بدهد
		return $fail( 'کلید خصوصی فایل قابل خواندن نیست؛ فایل JSON را دوباره از Google Cloud دانلود کنید.' );
	}

	return [ 'ok' => true, 'message' => '', 'email' => sanitize_email( $key['client_email'] ) ];
}

/** ایمیل سرویس اکانت (برای راهنمای افزودن کاربر در سرچ کنسول). */
function hodima_seo_discover_sc_email(): string {
	$key = json_decode( hodima_seo_discover_sc_key_json(), true );
	return is_array( $key ) ? sanitize_email( (string) ( $key['client_email'] ?? '' ) ) : '';
}

/** property انتخاب‌شده در تنظیمات (خالی = خودکار). مقدار نامعتبر ذخیره‌شده (مثلا ایمیل، تا SEO 2.1.5) نادیده گرفته می‌شود. */
function hodima_seo_discover_sc_property(): string {
	$settings = get_option( HODIMA_SEO_DISCOVER_SC_OPTION, [] );
	$property = hodima_seo_discover_sc_clean_property( is_array( $settings ) ? (string) ( $settings['property'] ?? '' ) : '' );
	return is_string( $property ) ? $property : '';
}

/**
 * property واردشده مدیر ← شکل سرچ کنسول، یا خطای فارسی.
 *
 *   https://example.com/ یا آدرس با مسیر  ← property «پیشوند آدرس» (با / آخر)
 *   sc-domain:example.com یا example.com  ← property «دامنه»
 *   خالی                                  ← خودکار
 *
 * باگ قبلی (تا SEO 2.1.5): هر متنی آدرس فرض می‌شد؛ ایمیل حساب سرویس
 * «https://name@project.iam.gserviceaccount.com/» ذخیره و به سرچ کنسول
 * فرستاده می‌شد («is not a valid Search Console site URL»).
 */
function hodima_seo_discover_sc_clean_property( string $raw ): string|WP_Error {

	$raw = trim( $raw );

	if ( '' === $raw ) {
		return '';
	}

	$host_ok = static fn( string $host ): bool => 1 === preg_match( '/^(?=.{4,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z][a-z0-9-]{1,62}$/', $host );

	if ( str_contains( $raw, '@' ) ) {
		return new WP_Error( 'hodima_discover_property_email', 'در فیلد property ایمیل نوشته شده است. ایمیل حساب سرویس جای دیگری لازم است (کاربر سرچ کنسول)؛ property آدرس سایت است، مثل ' . trailingslashit( home_url() ) . ' یا sc-domain:' . preg_replace( '/^www\./', '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) . '. خالی هم بگذارید خودکار پیدا می‌شود.' );
	}

	// property دامنه: «sc-domain:example.com» یا فقط «example.com»
	if ( str_starts_with( strtolower( $raw ), 'sc-domain:' ) || ! str_contains( $raw, '/' ) ) {
		$host = strtolower( trim( (string) preg_replace( '/^sc-domain:/i', '', $raw ) ) );
		return $host_ok( $host ) ? 'sc-domain:' . $host : new WP_Error( 'hodima_discover_property', 'property نامعتبر است؛ مثلا sc-domain:example.com یا https://example.com/' );
	}

	$parts = wp_parse_url( $raw );
	$host  = strtolower( (string) ( $parts['host'] ?? '' ) );

	if ( ! is_array( $parts ) || ! in_array( strtolower( (string) ( $parts['scheme'] ?? '' ) ), [ 'http', 'https' ], true ) || isset( $parts['user'] ) || ! $host_ok( $host ) ) {
		return new WP_Error( 'hodima_discover_property', 'property نامعتبر است؛ آدرس کامل با https:// (مثل ' . trailingslashit( home_url() ) . ') یا sc-domain:دامنه بنویسید.' );
	}

	return strtolower( (string) $parts['scheme'] ) . '://' . $host . ( isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '' ) . trailingslashit( (string) ( $parts['path'] ?? '/' ) );
}

/** گزینه propertyهایی که حساب سرویس در سرچ کنسول به آن‌ها دسترسی دارد (از آخرین اتصال). */
const HODIMA_SEO_DISCOVER_SC_SITES_OPTION = 'hodima_discover_sc_sites';

/**
 * propertyهای در دسترس حساب سرویس (sites.list سرچ کنسول)، یا خطا.
 *
 * @return list<string>|WP_Error
 */
function hodima_seo_discover_sc_list_sites( string $token ): array|WP_Error {

	$res = wp_remote_get( 'https://searchconsole.googleapis.com/webmasters/v3/sites', [
		'timeout' => 15,
		'headers' => [ 'Authorization' => 'Bearer ' . $token ],
	] );

	if ( is_wp_error( $res ) ) {
		return $res;
	}

	$body = json_decode( (string) wp_remote_retrieve_body( $res ), true );

	if ( 200 !== (int) wp_remote_retrieve_response_code( $res ) || ! is_array( $body ) ) {
		return new WP_Error( 'hodima_discover_sc_sites', (string) ( is_array( $body ) ? ( $body['error']['message'] ?? '' ) : '' ) );
	}

	$sites = [];
	foreach ( (array) ( $body['siteEntry'] ?? [] ) as $entry ) {
		if ( is_array( $entry ) && '' !== (string) ( $entry['siteUrl'] ?? '' ) && 'siteUnverifiedUser' !== ( $entry['permissionLevel'] ?? '' ) ) {
			$sites[] = (string) $entry['siteUrl'];
		}
	}

	sort( $sites );

	return $sites;
}

/**
 * propertyهای ذخیره‌شده از آخرین اتصال.
 *
 * @return list<string>
 */
function hodima_seo_discover_sc_sites(): array {
	$sites = get_option( HODIMA_SEO_DISCOVER_SC_SITES_OPTION, [] );
	return is_array( $sites ) ? array_values( array_map( 'strval', $sites ) ) : [];
}

/**
 * propertyهایی که به ترتیب امتحان می‌شوند: انتخاب مدیر؛ وگرنه propertyهای
 * در دسترس حساب که مال همین سایت‌اند (آدرس سایت، بعد دامنه)، و در آخر
 * حدس آدرس سایت و دامنه.
 *
 * @return list<string>
 */
function hodima_seo_discover_sc_candidates(): array {

	$chosen = hodima_seo_discover_sc_property();

	if ( '' !== $chosen ) {
		return [ $chosen ];
	}

	$host   = preg_replace( '/^www\./', '', strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) );
	$guess  = [ trailingslashit( home_url() ), 'sc-domain:' . $host ];
	$owned  = array_filter( hodima_seo_discover_sc_sites(), static function ( string $site ) use ( $host ): bool {
		$site_host = str_starts_with( $site, 'sc-domain:' ) ? substr( $site, 10 ) : (string) wp_parse_url( $site, PHP_URL_HOST );
		return preg_replace( '/^www\./', '', strtolower( $site_host ) ) === $host;
	} );

	return array_values( array_unique( [ ...array_intersect( $guess, $owned ), ...$owned, ...$guess ] ) );
}

/**
 * توکن دسترسی فقط‌خواندنی سرچ کنسول، یا پیام خطا (WP_Error).
 */
function hodima_seo_discover_sc_token( bool $force = false ): string|WP_Error {

	$key = json_decode( hodima_seo_discover_sc_key_json(), true );

	if ( ! is_array( $key ) || empty( $key['client_email'] ) || empty( $key['private_key'] ) || empty( $key['token_uri'] ) ) {
		return new WP_Error( 'hodima_discover_no_key', 'کلید حساب سرویس گوگل تنظیم نشده است؛ در «گوگل دیسکاور ← آمار سرچ کنسول» فایل JSON حساب سرویس را وارد کنید.' );
	}

	// توکن هر حساب جدا: با عوض شدن کلید (ایمیل)، توکن حساب قبلی استفاده نمی‌شود
	$cache = 'hodima_discover_sc_token_' . substr( md5( (string) $key['client_email'] ), 0, 12 );

	if ( ! $force ) {
		$cached = get_transient( $cache );
		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}
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

	set_transient( $cache, $token, max( 60, (int) ( $body['expires_in'] ?? 3600 ) - 300 ) );

	return $token;
}

/**
 * یک درخواست گزارش دیسکاور برای یک property.
 *
 * @param list<string> $dimensions page یا date
 * @return array{rows: list<array<string, mixed>>}|WP_Error
 */
function hodima_seo_discover_sc_query( string $property, string $token, string $start, string $end, array $dimensions = [ 'page' ], int $row_limit = 5000 ): array|WP_Error {

	$res = wp_remote_post(
		'https://searchconsole.googleapis.com/webmasters/v3/sites/' . rawurlencode( $property ) . '/searchAnalytics/query',
		[
			'timeout' => 20,
			'headers' => [ 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json' ],
			'body'    => (string) wp_json_encode( [
				'startDate'  => $start,
				'endDate'    => $end,
				'type'       => 'discover',
				'dimensions' => $dimensions,
				'rowLimit'   => max( 1, min( 25000, $row_limit ) ), // سقف خود سرچ کنسول ۲۵ هزار
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

	$token = hodima_seo_discover_sc_token();

	if ( is_wp_error( $token ) ) {
		hodima_seo_discover_sc_store_error( $token->get_error_message() );
		return $token;
	}

	// propertyهای در دسترس حساب (برای انتخاب خودکار و پیام خطا)؛ شکستش مانع آمار نیست
	$sites = hodima_seo_discover_sc_list_sites( $token );
	if ( is_array( $sites ) ) {
		update_option( HODIMA_SEO_DISCOVER_SC_SITES_OPTION, $sites, false );
	}

	$range = hodima_seo_discover_sc_ranges();
	$last  = null;

	// یک درخواست، با یک بار توکن تازه اگر منقضی بود (۴۰۱)
	$query = static function ( string $property, string $start, string $end, array $dims, int $limit = 5000 ) use ( &$token ): array|WP_Error {
		$result = hodima_seo_discover_sc_query( $property, (string) $token, $start, $end, $dims, $limit );
		if ( is_wp_error( $result ) && 401 === (int) ( $result->get_error_data()['status'] ?? 0 ) ) {
			$fresh = hodima_seo_discover_sc_token( true );
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
			if ( str_starts_with( (string) $result->get_error_code(), 'hodima_discover_' ) && ! str_starts_with( (string) $result->get_error_code(), 'hodima_discover_sc_' ) ) {
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
			'error'    => '',
		], false );

		// تاریخچه بلندمدت و روزانه صفحه‌های تغییرکرده (discover-history.php؛ شکستش آمار اصلی را خراب نمی‌کند)
		if ( function_exists( 'hodima_seo_discover_history_update' ) ) {
			hodima_seo_discover_history_update( $property, $query, $range, $daily );
		}

		return true;
	}

	$status  = null !== $last ? (int) ( $last->get_error_data()['status'] ?? 0 ) : 0;
	$known   = hodima_seo_discover_sc_sites();
	$hint    = $known
		? ' propertyهایی که این حساب به آن‌ها دسترسی دارد: ' . implode( '، ', $known ) . '.'
		: ' این حساب هنوز به هیچ property دسترسی ندارد.';
	$message = match ( true ) {
		403 === $status => 'سرچ کنسول اجازه نداد: ایمیل حساب سرویس (' . hodima_seo_discover_sc_email() . ') را در سرچ کنسول ← تنظیمات ← کاربران و مجوزها به property سایت اضافه کنید، یا property درست را پایین انتخاب کنید.' . $hint,
		404 === $status => 'این property در سرچ کنسول پیدا نشد؛ property درست را پایین انتخاب کنید (مثلا https://example.com/ یا sc-domain:example.com).' . $hint,
		400 === $status => 'سرچ کنسول property را نپذیرفت (' . $last?->get_error_message() . '). property درست را پایین انتخاب کنید یا خالی بگذارید.' . $hint,
		null !== $last  => 'خطای سرچ کنسول: ' . $last->get_error_message(),
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
	$has_key   = '' !== hodima_seo_discover_sc_key_json();
	$scheduled = (bool) wp_next_scheduled( HODIMA_SEO_DISCOVER_SC_CRON );
	if ( $has_key && ! $scheduled ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', HODIMA_SEO_DISCOVER_SC_CRON );
	} elseif ( ! $has_key && $scheduled ) {
		wp_clear_scheduled_hook( HODIMA_SEO_DISCOVER_SC_CRON ); // کلید برداشته شد: رویداد بی‌کار نماند
	}
} );
