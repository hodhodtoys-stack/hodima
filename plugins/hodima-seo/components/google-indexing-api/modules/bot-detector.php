<?php
/**
 * Googlebot crawl detector
 * Path: components/google-indexing-api/modules/bot-detector.php
 *
 * هر خزش تأییدشده گوگل‌بات ثبت می‌شود تا «زمان واکنش پس از پینگ»
 * (سلامت سئو) محاسبه شود.
 *
 * نکته درباره لایت‌اسپید: درخواستی که از کش صفحه سرو شود به PHP
 * نمی‌رسد و اینجا ثبت نمی‌شود. لایت‌اسپید بعد از پاکسازی، کش را با
 * خزنده خودش دوباره گرم می‌کند؛ پس گوگل‌بات تقریبا همیشه نسخه کش را
 * می‌گیرد و گزارش خالی می‌ماند. راه‌حل در پنل (تب «گزارش خزش») توضیح
 * داده شده: گوگل‌بات در «Do Not Cache User Agents» لایت‌اسپید.
 * وضعیت آخرین بازدید تأییدشده/ردشده در گزینه hodima_gi_bot_diag نگه
 * داشته می‌شود تا پنل بتواند علت خالی بودن گزارش را نشان دهد.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Hodima_Bot_Detector {

	/** فهرست رسمی IPهای گوگل‌بات (بدون نیاز به DNS). */
	private const RANGES_URL    = 'https://developers.google.com/static/search/apis/ipranges/googlebot.json';
	private const RANGES_OPTION = 'hodima_gi_googlebot_ranges';
	public const DIAG_OPTION    = 'hodima_gi_bot_diag';

	private static ?self $instance = null;

	private function __construct() {
		/*
		 * از send_headers به template_redirect منتقل شد.
		 * send_headers قبل از اجرای کوئری است، پس نمی‌شد فهمید درخواست
		 * به یک صفحه واقعی رسیده یا ۴۰۴ است؛ هر آدرس زباله‌ای که
		 * گوگل‌بات امتحان می‌کرد وارد جدول می‌شد.
		 */
		add_action( 'template_redirect', [ $this, 'detect_and_log_bot' ], 99 );
	}

	public static function get_instance(): self {
		return self::$instance ??= new self();
	}

	public function detect_and_log_bot(): void {

		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		// robots.txt و favicon بعد از template_redirect سرو می‌شوند و صفحه نیستند
		if ( is_404() || is_feed() || is_search() || is_preview() || is_robots() || is_favicon() || is_trackback() ) {
			return;
		}

		$method = strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) );
		if ( 'GET' !== $method && 'HEAD' !== $method ) {
			return;
		}

		$agent = strtolower( (string) ( $_SERVER['HTTP_USER_AGENT'] ?? '' ) );
		if ( ! str_contains( $agent, 'googlebot' ) ) {
			return;
		}

		$ip = Hodima_GI_Helper::get_client_ip();

		if ( '' === $ip || ! $this->is_verified_googlebot( $ip ) ) {
			self::note( 'rejected', $ip );
			return;
		}

		self::note( 'verified', $ip );

		$path = (string) wp_parse_url( wp_unslash( (string) ( $_SERVER['REQUEST_URI'] ?? '/' ) ), PHP_URL_PATH );
		$url  = Hodima_GI_Helper::clean_url( rtrim( home_url(), '/' ) . '/' . ltrim( $path, '/' ) );

		Hodima_Crawler_DB_Queries::log_crawl_event( $url );
	}

	/**
	 * تأیید هویت گوگل‌بات.
	 *
	 * ۱. فهرست رسمی IPهای گوگل‌بات (فوری، بدون DNS) — اگر دریافت شده باشد.
	 * ۲. DNS معکوس و مستقیم (روش رسمی دیگر گوگل).
	 *
	 * باگ نسخه قبلی: gethostbyaddr و dns_get_record بدون بررسی صدا زده
	 * می‌شدند. در بسیاری از هاست‌های اشتراکی این توابع در disable_functions
	 * هستند و در PHP 8 صدا زدن تابع غیرفعال خطای کشنده است («@» جلوی Error
	 * را نمی‌گیرد) — یعنی هیچ خزشی ثبت نمی‌شد و گوگل‌بات خطای ۵۰۰ می‌گرفت.
	 *
	 * نتیجه منفی یک ساعت و مثبت یک هفته کش می‌شود.
	 */
	private function is_verified_googlebot( string $ip ): bool {

		$cache_key = 'hodima_gi_bot_verify_' . md5( $ip );
		$cached    = get_transient( $cache_key );

		if ( '1' === $cached ) {
			return true;
		}
		if ( '0' === $cached ) {
			return false;
		}

		$verified = self::ip_in_published_ranges( $ip ) || self::verify_by_dns( $ip );

		/**
		 * برای تست یا سرورهایی که روش دیگری برای تأیید دارند.
		 */
		$verified = (bool) apply_filters( 'hodima_gi_is_verified_googlebot', $verified, $ip );

		set_transient( $cache_key, $verified ? '1' : '0', $verified ? WEEK_IN_SECONDS : HOUR_IN_SECONDS );

		return $verified;
	}

	private static function verify_by_dns( string $ip ): bool {

		if ( ! self::dns_available() ) {
			return false;
		}

		$host = @gethostbyaddr( $ip );

		if ( ! is_string( $host ) || $host === $ip || ! preg_match( '/\.(googlebot|google)\.com$/i', $host ) ) {
			return false;
		}

		$records = @dns_get_record( $host, DNS_A | DNS_AAAA );

		foreach ( (array) $records as $record ) {
			$resolved = $record['ip'] ?? ( $record['ipv6'] ?? '' );
			if ( '' !== $resolved && @inet_pton( $resolved ) === @inet_pton( $ip ) ) {
				return true;
			}
		}

		return false;
	}

	/** توابع DNS در هاست فعال‌اند؟ (disable_functions) */
	public static function dns_available(): bool {
		return function_exists( 'gethostbyaddr' ) && function_exists( 'dns_get_record' );
	}

	/* =================================================================
	 * فهرست رسمی IPهای گوگل‌بات
	 * ================================================================= */

	/** @return list<string> */
	private static function ranges(): array {
		$data = get_option( self::RANGES_OPTION, [] );
		return is_array( $data ) && ! empty( $data['prefixes'] ) && is_array( $data['prefixes'] ) ? array_values( $data['prefixes'] ) : [];
	}

	private static function ip_in_published_ranges( string $ip ): bool {
		foreach ( self::ranges() as $cidr ) {
			if ( self::ip_in_cidr( $ip, (string) $cidr ) ) {
				return true;
			}
		}
		return false;
	}

	private static function ip_in_cidr( string $ip, string $cidr ): bool {

		if ( function_exists( 'hodima_ip_in_cidr' ) ) {
			return hodima_ip_in_cidr( $ip, $cidr );
		}

		[ $subnet, $bits ] = array_pad( explode( '/', $cidr, 2 ), 2, '' );

		$ip_bin     = @inet_pton( $ip );
		$subnet_bin = @inet_pton( $subnet );

		if ( false === $ip_bin || false === $subnet_bin || strlen( $ip_bin ) !== strlen( $subnet_bin ) ) {
			return false;
		}

		$bits = '' === $bits ? strlen( $ip_bin ) * 8 : (int) $bits;
		$full = intdiv( $bits, 8 );
		$rest = $bits % 8;

		if ( $full > 0 && 0 !== strncmp( $ip_bin, $subnet_bin, $full ) ) {
			return false;
		}

		if ( 0 === $rest ) {
			return true;
		}

		$mask = ~( ( 1 << ( 8 - $rest ) ) - 1 ) & 0xFF;

		return ( ord( $ip_bin[ $full ] ) & $mask ) === ( ord( $subnet_bin[ $full ] ) & $mask );
	}

	/**
	 * دریافت فهرست IPها از گوگل — فقط در کرون روزانه و پنل، هرگز در
	 * درخواست بازدیدکننده. هفته‌ای یک بار؛ بعد از شکست، یک روز صبر.
	 */
	public static function maybe_refresh_ranges( bool $force = false ): void {

		$data = get_option( self::RANGES_OPTION, [] );
		$data = is_array( $data ) ? $data : [];

		if ( ! $force ) {
			$fetched = (int) ( $data['fetched'] ?? 0 );
			$failed  = (int) ( $data['failed'] ?? 0 );
			if ( time() - $fetched < WEEK_IN_SECONDS || time() - $failed < DAY_IN_SECONDS ) {
				return;
			}
		}

		$res  = wp_remote_get( self::RANGES_URL, [ 'timeout' => 8 ] );
		$body = is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res )
			? null
			: json_decode( (string) wp_remote_retrieve_body( $res ), true );

		$prefixes = [];
		foreach ( (array) ( $body['prefixes'] ?? [] ) as $row ) {
			$cidr = (string) ( $row['ipv4Prefix'] ?? ( $row['ipv6Prefix'] ?? '' ) );
			if ( preg_match( '#^[0-9a-f:.]+/\d{1,3}$#i', $cidr ) ) {
				$prefixes[] = $cidr;
			}
		}

		if ( empty( $prefixes ) ) {
			// فهرست قبلی (اگر بود) حفظ می‌شود
			$data['failed'] = time();
		} else {
			$data = [ 'fetched' => time(), 'failed' => 0, 'prefixes' => $prefixes ];
		}

		update_option( self::RANGES_OPTION, $data, false );
	}

	/** @return array{count:int, fetched:int} */
	public static function ranges_status(): array {
		$data = get_option( self::RANGES_OPTION, [] );
		return [
			'count'   => count( self::ranges() ),
			'fetched' => is_array( $data ) ? (int) ( $data['fetched'] ?? 0 ) : 0,
		];
	}

	/* =================================================================
	 * عیب‌یابی (برای پنل)
	 * ================================================================= */

	/**
	 * ثبت آخرین بازدید تأییدشده/ردشده. حداکثر هر ۵ دقیقه یک بار نوشته
	 * می‌شود تا خزش پرحجم گوگل‌بات دیتابیس را درگیر نکند.
	 */
	private static function note( string $state, string $ip ): void {

		$diag = get_option( self::DIAG_OPTION, [] );
		$diag = is_array( $diag ) ? $diag : [];

		if ( time() - (int) ( $diag[ $state ] ?? 0 ) < 5 * MINUTE_IN_SECONDS ) {
			return;
		}

		$diag[ $state ] = time();

		if ( 'rejected' === $state ) {
			$diag['rejected_ip'] = $ip;
		}

		update_option( self::DIAG_OPTION, $diag, false );
	}

	/** @return array{verified:int, rejected:int, rejected_ip:string} */
	public static function diagnostics(): array {
		$diag = get_option( self::DIAG_OPTION, [] );
		$diag = is_array( $diag ) ? $diag : [];
		return [
			'verified'    => (int) ( $diag['verified'] ?? 0 ),
			'rejected'    => (int) ( $diag['rejected'] ?? 0 ),
			'rejected_ip' => (string) ( $diag['rejected_ip'] ?? '' ),
		];
	}

	/**
	 * آیا گوگل‌بات در «Do Not Cache User Agents» لایت‌اسپید است؟
	 *
	 * @return bool|null null = لایت‌اسپید نیست یا تنظیمش خوانده نشد
	 */
	public static function litespeed_bypasses_googlebot(): ?bool {

		if ( ! Hodima_GI_Helper::litespeed_active() ) {
			return null;
		}

		$value = get_option( 'litespeed.conf.cache-exc_useragents', null );

		if ( null === $value || false === $value ) {
			return null;
		}

		$list = is_array( $value ) ? implode( "\n", array_map( 'strval', $value ) ) : (string) $value;

		return str_contains( strtolower( $list ), 'googlebot' );
	}
}

Hodima_Bot_Detector::get_instance();
