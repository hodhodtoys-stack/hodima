<?php
/**
 * اتصال مشترک به Search Console (فقط‌خواندنی)
 * Path: plugins/hodima-seo/inc/search-console.php
 *
 * کلید حساب سرویس، property، توکن و کوئری searchAnalytics فقط اینجا نوشته
 * می‌شوند و هر ماژول SEO که داده سرچ کنسول لازم دارد (گوگل دیسکاور؛ مشاور
 * سئو) از همین‌جا می‌خواند. تا SEO 2.4.9 همه این‌ها داخل ماژول دیسکاور
 * (core/discover/discover-stats.php) بود و با خاموش شدن آن ماژول، ماژول
 * دیگری به سرچ کنسول دسترسی نداشت.
 *
 * نام گزینه‌ها و ترنزینت (hodima_discover_sc_*) عمدا همان قبلی مانده تا
 * کلید و تنظیمات ذخیره‌شده سایت‌ها بدون مهاجرت کار کند (قانون داده سایت).
 * دیسکاور نام‌های قدیمی توابعش (hodima_seo_discover_sc_*) را نگه داشته و
 * فقط به این فایل می‌سپارد.
 *
 * کلید: «کلید جدای دیسکاور» (گزینه hodima_discover_sc_key)، وگرنه «سرویس
 * اکانت» ماژول Google Indexing (روشن یا خاموش). مشاور سئو (پارامتر
 * $consumer = HODIMA_SEO_SC_ADVISOR) اول کلید مخصوص خودش را می‌خواند (ثابت
 * HODIMA_SEO_ADVISOR_SERVICE_ACCOUNT_JSON در wp-config، وگرنه گزینه
 * hodima_advisor_sc_key) تا فقط‌خواندنی و جدا از دیسکاور و Indexing باشد؛
 * اگر نبود همان ترتیب بالا (سایتی که هنوز کلید مخصوص نساخته خراب نشود). دسترسی فقط‌خواندنی
 * (webmasters.readonly؛ توکن جدا برای هر حساب). ایمیل حساب سرویس باید در
 * سرچ کنسول کاربر همان property باشد. هیچ بازدیدی از سایت به گوگل درخواست
 * نمی‌زند؛ فراخوانی‌ها فقط از WP-Cron و پیشخوان‌اند.
 *
 * کدهای خطا: hodima_sc_no_key / _sign / _http / _token (مشکل کلید و توکن)،
 * hodima_sc_api_{وضعیت} (پاسخ ناموفق API؛ داده خطا: status)،
 * hodima_sc_sites، hodima_sc_property*.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** نام گزینه تنظیمات (property). */
const HODIMA_SEO_SC_OPTION = 'hodima_discover_sc_settings';

/** نام گزینه «کلید جدای دیسکاور» (JSON حساب سرویس؛ autoload خاموش). */
const HODIMA_SEO_SC_KEY_OPTION = 'hodima_discover_sc_key';

/** گزینه propertyهایی که حساب سرویس در سرچ کنسول به آن‌ها دسترسی دارد (از آخرین اتصال). */
const HODIMA_SEO_SC_SITES_OPTION = 'hodima_discover_sc_sites';

/** مصرف‌کننده‌ای که کلید و فهرست property مخصوص خودش را دارد (مشاور سئو). خالی = اتصال مشترک دیسکاور. */
const HODIMA_SEO_SC_ADVISOR = 'advisor';

/** «کلید مخصوص مشاور» (JSON حساب سرویس؛ autoload خاموش). ثابت wp-config با همین نام‌گذاری امن‌تر است و اولویت دارد. */
const HODIMA_SEO_SC_ADVISOR_KEY_OPTION = 'hodima_advisor_sc_key';

/** propertyهای در دسترس «کلید مخصوص مشاور» (جدا از فهرست مشترک تا با دیسکاور قاطی نشود). */
const HODIMA_SEO_SC_ADVISOR_SITES_OPTION = 'hodima_advisor_sc_sites';

/**
 * منبع کلید فعلی: advisor_config (ثابت HODIMA_SEO_ADVISOR_SERVICE_ACCOUNT_JSON در wp-config)،
 * advisor_own (کلید مخصوص مشاور در پیشخوان) — این دو فقط برای مصرف‌کننده advisor — own (کلید
 * جدای دیسکاور)، indexing (ماژول Google Indexing یا ثابت HODIMA_GI_SERVICE_ACCOUNT_JSON در
 * wp-config)، یا رشته خالی.
 */
function hodima_seo_sc_key_source( string $consumer = '' ): string {

	if ( HODIMA_SEO_SC_ADVISOR === $consumer ) {
		if ( defined( 'HODIMA_SEO_ADVISOR_SERVICE_ACCOUNT_JSON' ) && '' !== trim( (string) constant( 'HODIMA_SEO_ADVISOR_SERVICE_ACCOUNT_JSON' ) ) ) {
			return 'advisor_config';
		}
		if ( '' !== trim( (string) get_option( HODIMA_SEO_SC_ADVISOR_KEY_OPTION, '' ) ) ) {
			return 'advisor_own';
		}
	}

	if ( '' !== trim( (string) get_option( HODIMA_SEO_SC_KEY_OPTION, '' ) ) ) {
		return 'own';
	}

	return '' !== hodima_seo_sc_indexing_json() ? 'indexing' : '';
}

/** JSON حساب سرویس ماژول Google Indexing (روشن یا خاموش)، یا رشته خالی. */
function hodima_seo_sc_indexing_json(): string {

	if ( class_exists( 'Hodima_GI_Helper' ) ) {
		return Hodima_GI_Helper::service_account_json();
	}

	if ( defined( 'HODIMA_GI_SERVICE_ACCOUNT_JSON' ) && '' !== (string) HODIMA_GI_SERVICE_ACCOUNT_JSON ) {
		return (string) HODIMA_GI_SERVICE_ACCOUNT_JSON;
	}

	return (string) get_option( 'hodima_gi_json_key', '' ); // همان HODIMA_GI_OPTION_JSON؛ ماژول خاموش ثابتش را تعریف نکرده
}

/**
 * JSON سرویس اکانت، یا رشته خالی. برای مشاور اول کلید مخصوص خودش، بعد همان ترتیب مشترک
 * (کلید جدای دیسکاور، وگرنه ماژول Google Indexing) تا سایتی که هنوز کلید مخصوص نساخته خراب نشود.
 */
function hodima_seo_sc_key_json( string $consumer = '' ): string {

	return match ( hodima_seo_sc_key_source( $consumer ) ) {
		'advisor_config' => trim( (string) constant( 'HODIMA_SEO_ADVISOR_SERVICE_ACCOUNT_JSON' ) ),
		'advisor_own'    => trim( (string) get_option( HODIMA_SEO_SC_ADVISOR_KEY_OPTION, '' ) ),
		'own'            => trim( (string) get_option( HODIMA_SEO_SC_KEY_OPTION, '' ) ),
		'indexing'       => hodima_seo_sc_indexing_json(),
		default          => '',
	};
}

/**
 * بررسی JSON حساب سرویس پیش از ذخیره (همان قاعده ماژول Google Indexing).
 *
 * @return array{ok: bool, message: string, email: string}
 */
function hodima_seo_sc_validate_key( string $json ): array {

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
function hodima_seo_sc_email( string $consumer = '' ): string {
	$key = json_decode( hodima_seo_sc_key_json( $consumer ), true );
	return is_array( $key ) ? sanitize_email( (string) ( $key['client_email'] ?? '' ) ) : '';
}

/** property انتخاب‌شده در تنظیمات (خالی = خودکار). مقدار نامعتبر ذخیره‌شده (مثلا ایمیل، تا SEO 2.1.5) نادیده گرفته می‌شود. */
function hodima_seo_sc_property(): string {
	$settings = get_option( HODIMA_SEO_SC_OPTION, [] );
	$property = hodima_seo_sc_clean_property( is_array( $settings ) ? (string) ( $settings['property'] ?? '' ) : '' );
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
function hodima_seo_sc_clean_property( string $raw ): string|WP_Error {

	$raw = trim( $raw );

	if ( '' === $raw ) {
		return '';
	}

	$host_ok = static fn( string $host ): bool => 1 === preg_match( '/^(?=.{4,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z][a-z0-9-]{1,62}$/', $host );

	if ( str_contains( $raw, '@' ) ) {
		return new WP_Error( 'hodima_sc_property_email', 'در فیلد property ایمیل نوشته شده است. ایمیل حساب سرویس جای دیگری لازم است (کاربر سرچ کنسول)؛ property آدرس سایت است، مثل ' . trailingslashit( home_url() ) . ' یا sc-domain:' . preg_replace( '/^www\./', '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) . '. خالی هم بگذارید خودکار پیدا می‌شود.' );
	}

	// property دامنه: «sc-domain:example.com» یا فقط «example.com»
	if ( str_starts_with( strtolower( $raw ), 'sc-domain:' ) || ! str_contains( $raw, '/' ) ) {
		$host = strtolower( trim( (string) preg_replace( '/^sc-domain:/i', '', $raw ) ) );
		return $host_ok( $host ) ? 'sc-domain:' . $host : new WP_Error( 'hodima_sc_property', 'property نامعتبر است؛ مثلا sc-domain:example.com یا https://example.com/' );
	}

	$parts = wp_parse_url( $raw );
	$host  = strtolower( (string) ( $parts['host'] ?? '' ) );

	if ( ! is_array( $parts ) || ! in_array( strtolower( (string) ( $parts['scheme'] ?? '' ) ), [ 'http', 'https' ], true ) || isset( $parts['user'] ) || ! $host_ok( $host ) ) {
		return new WP_Error( 'hodima_sc_property', 'property نامعتبر است؛ آدرس کامل با https:// (مثل ' . trailingslashit( home_url() ) . ') یا sc-domain:دامنه بنویسید.' );
	}

	return strtolower( (string) $parts['scheme'] ) . '://' . $host . ( isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '' ) . trailingslashit( (string) ( $parts['path'] ?? '/' ) );
}

/**
 * propertyهای در دسترس حساب سرویس (sites.list سرچ کنسول)، یا خطا.
 *
 * @return list<string>|WP_Error
 */
function hodima_seo_sc_list_sites( string $token ): array|WP_Error {

	$res = wp_remote_get( 'https://searchconsole.googleapis.com/webmasters/v3/sites', [
		'timeout' => 15,
		'headers' => [ 'Authorization' => 'Bearer ' . $token ],
	] );

	if ( is_wp_error( $res ) ) {
		return $res;
	}

	$body = json_decode( (string) wp_remote_retrieve_body( $res ), true );

	if ( 200 !== (int) wp_remote_retrieve_response_code( $res ) || ! is_array( $body ) ) {
		return new WP_Error( 'hodima_sc_sites', (string) ( is_array( $body ) ? ( $body['error']['message'] ?? '' ) : '' ) );
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

/** گزینه فهرست propertyها: کلید مخصوص مشاور فهرست خودش را دارد، بقیه فهرست مشترک. */
function hodima_seo_sc_sites_option( string $consumer = '' ): string {
	return in_array( hodima_seo_sc_key_source( $consumer ), [ 'advisor_config', 'advisor_own' ], true ) ? HODIMA_SEO_SC_ADVISOR_SITES_OPTION : HODIMA_SEO_SC_SITES_OPTION;
}

/**
 * propertyهای ذخیره‌شده از آخرین اتصال.
 *
 * @return list<string>
 */
function hodima_seo_sc_sites( string $consumer = '' ): array {
	$sites = get_option( hodima_seo_sc_sites_option( $consumer ), [] );
	return is_array( $sites ) ? array_values( array_map( 'strval', $sites ) ) : [];
}

/**
 * propertyهایی که به ترتیب امتحان می‌شوند: انتخاب مدیر؛ وگرنه propertyهای
 * در دسترس حساب که مال همین سایت‌اند (آدرس سایت، بعد دامنه)، و در آخر
 * حدس آدرس سایت و دامنه.
 *
 * @return list<string>
 */
function hodima_seo_sc_candidates( string $consumer = '' ): array {

	$chosen = hodima_seo_sc_property();

	if ( '' !== $chosen ) {
		return [ $chosen ];
	}

	$host   = preg_replace( '/^www\./', '', strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) );
	$guess  = [ trailingslashit( home_url() ), 'sc-domain:' . $host ];
	$owned  = array_filter( hodima_seo_sc_sites( $consumer ), static function ( string $site ) use ( $host ): bool {
		$site_host = str_starts_with( $site, 'sc-domain:' ) ? substr( $site, 10 ) : (string) wp_parse_url( $site, PHP_URL_HOST );
		return preg_replace( '/^www\./', '', strtolower( $site_host ) ) === $host;
	} );

	return array_values( array_unique( [ ...array_intersect( $guess, $owned ), ...$owned, ...$guess ] ) );
}

/**
 * توکن دسترسی فقط‌خواندنی سرچ کنسول، یا پیام خطا (WP_Error).
 */
function hodima_seo_sc_token( bool $force = false, string $consumer = '' ): string|WP_Error {

	$key = json_decode( hodima_seo_sc_key_json( $consumer ), true );

	if ( ! is_array( $key ) || empty( $key['client_email'] ) || empty( $key['private_key'] ) || empty( $key['token_uri'] ) ) {
		return new WP_Error( 'hodima_sc_no_key', 'کلید حساب سرویس گوگل تنظیم نشده است؛ در ' . ( HODIMA_SEO_SC_ADVISOR === $consumer ? '«مشاور سئو ← تنظیمات مشاور»' : '«گوگل دیسکاور ← آمار سرچ کنسول»' ) . ' فایل JSON حساب سرویس را وارد کنید.' );
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
		return new WP_Error( 'hodima_sc_sign', 'کلید خصوصی سرویس اکانت نامعتبر است.' );
	}

	$res = wp_remote_post( (string) $key['token_uri'], [
		'timeout' => 15,
		'body'    => [
			'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
			'assertion'  => $header . '.' . $claim . '.' . $enc( $signature ),
		],
	] );

	if ( is_wp_error( $res ) ) {
		return new WP_Error( 'hodima_sc_http', 'اتصال به گوگل برقرار نشد: ' . $res->get_error_message() );
	}

	$body  = json_decode( (string) wp_remote_retrieve_body( $res ), true );
	$token = is_array( $body ) ? (string) ( $body['access_token'] ?? '' ) : '';

	if ( 200 !== (int) wp_remote_retrieve_response_code( $res ) || '' === $token ) {
		return new WP_Error( 'hodima_sc_token', 'گوگل توکن نداد (کلید یا دسترسی سرویس اکانت را بررسی کنید).' );
	}

	set_transient( $cache, $token, max( 60, (int) ( $body['expires_in'] ?? 3600 ) - 300 ) );

	return $token;
}

/**
 * آیا خطای این فراخوانی از کلید/توکن است (نه پاسخ ناموفق API یک property)؟
 * خطای کلید با امتحان property دیگر درست نمی‌شود.
 */
function hodima_seo_sc_is_key_error( WP_Error $error ): bool {
	$code = (string) $error->get_error_code();
	return str_starts_with( $code, 'hodima_sc_' ) && ! str_starts_with( $code, 'hodima_sc_api_' );
}

/**
 * یک درخواست گزارش searchAnalytics برای یک property.
 *
 * @param list<string> $dimensions page، query، date، device، country
 * @param string       $type       discover، web، …
 * @param int          $start_row  شروع صفحه‌بندی (هر درخواست حداکثر ۲۵ هزار ردیف)
 * @return array{rows: list<array<string, mixed>>}|WP_Error
 */
function hodima_seo_sc_query( string $property, string $token, string $start, string $end, array $dimensions = [ 'page' ], int $row_limit = 5000, string $type = 'web', int $start_row = 0 ): array|WP_Error {

	$res = wp_remote_post(
		'https://searchconsole.googleapis.com/webmasters/v3/sites/' . rawurlencode( $property ) . '/searchAnalytics/query',
		[
			'timeout' => 20,
			'headers' => [ 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json' ],
			'body'    => (string) wp_json_encode( [
				'startDate'  => $start,
				'endDate'    => $end,
				'type'       => $type,
				'dimensions' => $dimensions,
				'rowLimit'   => max( 1, min( 25000, $row_limit ) ), // سقف خود سرچ کنسول ۲۵ هزار
			] + ( $start_row > 0 ? [ 'startRow' => $start_row ] : [] ) ),
		]
	);

	if ( is_wp_error( $res ) ) {
		return $res;
	}

	$code = (int) wp_remote_retrieve_response_code( $res );
	$body = json_decode( (string) wp_remote_retrieve_body( $res ), true );

	if ( 200 !== $code ) {
		$message = is_array( $body ) ? (string) ( $body['error']['message'] ?? '' ) : '';
		return new WP_Error( 'hodima_sc_api_' . $code, $message, [ 'status' => $code ] );
	}

	return [ 'rows' => is_array( $body ) && is_array( $body['rows'] ?? null ) ? array_values( $body['rows'] ) : [] ];
}

/**
 * بررسی ایندکس یک آدرس (URL Inspection API؛ فقط‌خواندنی، همان دسترسی webmasters.readonly).
 * سهمیه گوگل حدود ۲۰۰۰ بررسی در روز برای هر property و ۶۰۰ در دقیقه است. کد خطای سهمیه: hodima_sc_api_429.
 *
 * @return array<string, mixed>|WP_Error بخش inspectionResult پاسخ
 */
function hodima_seo_sc_inspect_url( string $property, string $url, string $consumer = '' ): array|WP_Error {

	$send = static function ( string $token ) use ( $property, $url ): array|WP_Error {
		$res = wp_remote_post( 'https://searchconsole.googleapis.com/v1/urlInspection/index:inspect', [
			'timeout' => 30,
			'headers' => [ 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json' ],
			'body'    => (string) wp_json_encode( [ 'inspectionUrl' => $url, 'siteUrl' => $property, 'languageCode' => 'en-US' ] ),
		] );

		if ( is_wp_error( $res ) ) {
			return $res;
		}

		$code = (int) wp_remote_retrieve_response_code( $res );
		$body = json_decode( (string) wp_remote_retrieve_body( $res ), true );

		if ( 200 !== $code ) {
			return new WP_Error( 'hodima_sc_api_' . $code, is_array( $body ) ? (string) ( $body['error']['message'] ?? '' ) : '', [ 'status' => $code ] );
		}

		return is_array( $body ) && is_array( $body['inspectionResult'] ?? null ) ? $body['inspectionResult'] : [];
	};

	$token = hodima_seo_sc_token( false, $consumer );

	if ( is_wp_error( $token ) ) {
		return $token;
	}

	$result = $send( $token );

	if ( is_wp_error( $result ) && 401 === (int) ( $result->get_error_data()['status'] ?? 0 ) ) {
		$token = hodima_seo_sc_token( true, $consumer );
		return is_wp_error( $token ) ? $token : $send( $token );
	}

	return $result;
}

/**
 * کوئری با توکن خودکار: توکن را خودش می‌گیرد و اگر منقضی بود (۴۰۱) یک بار با
 * توکن تازه تکرار می‌کند.
 *
 * @param list<string> $dimensions
 * @return array{rows: list<array<string, mixed>>}|WP_Error
 */
function hodima_seo_sc_request( string $property, string $start, string $end, array $dimensions, int $row_limit = 5000, string $type = 'web', int $start_row = 0, string $consumer = '' ): array|WP_Error {

	$token = hodima_seo_sc_token( false, $consumer );

	if ( is_wp_error( $token ) ) {
		return $token;
	}

	$result = hodima_seo_sc_query( $property, $token, $start, $end, $dimensions, $row_limit, $type, $start_row );

	if ( is_wp_error( $result ) && 401 === (int) ( $result->get_error_data()['status'] ?? 0 ) ) {
		$token = hodima_seo_sc_token( true, $consumer );
		if ( is_wp_error( $token ) ) {
			return $token;
		}
		$result = hodima_seo_sc_query( $property, $token, $start, $end, $dimensions, $row_limit, $type, $start_row );
	}

	return $result;
}

/**
 * پیام فارسی شکست دریافت داده (همه propertyها خطا دادند).
 *
 * @param WP_Error|null $last آخرین خطای API؛ null = property برای امتحان نبود
 */
function hodima_seo_sc_failure_message( ?WP_Error $last, string $consumer = '' ): string {

	$status = null !== $last ? (int) ( $last->get_error_data()['status'] ?? 0 ) : 0;
	$known  = hodima_seo_sc_sites( $consumer );
	$hint   = $known
		? ' propertyهایی که این حساب به آن‌ها دسترسی دارد: ' . implode( '، ', $known ) . '.'
		: ' این حساب هنوز به هیچ property دسترسی ندارد.';
	$where  = HODIMA_SEO_SC_ADVISOR === $consumer ? ' در «مشاور سئو ← تنظیمات مشاور»' : ' در «گوگل دیسکاور ← آمار سرچ کنسول»';

	return match ( true ) {
		403 === $status => 'سرچ کنسول اجازه نداد: ایمیل حساب سرویس (' . hodima_seo_sc_email( $consumer ) . ') را در سرچ کنسول ← تنظیمات ← کاربران و مجوزها به property سایت اضافه کنید، یا property درست را' . $where . ' انتخاب کنید.' . $hint,
		404 === $status => 'این property در سرچ کنسول پیدا نشد؛ property درست را' . $where . ' انتخاب کنید (مثلا https://example.com/ یا sc-domain:example.com).' . $hint,
		400 === $status => 'سرچ کنسول property را نپذیرفت (' . $last?->get_error_message() . '). property درست را' . $where . ' انتخاب کنید یا خالی بگذارید.' . $hint,
		null !== $last  => 'خطای سرچ کنسول: ' . $last->get_error_message(),
		default         => 'property برای امتحان وجود ندارد.',
	};
}
