<?php
/**
 * Googlebot crawl detector
 * Path: components/google-indexing-api/modules/bot-detector.php
 *
 * هر خزش تأییدشده گوگل‌بات ثبت می‌شود تا «زمان واکنش پس از پینگ»
 * (سلامت سئو) محاسبه شود.
 *
 * نکته درباره لایت‌اسپید: درخواستی که از کش صفحه سرو شود به PHP
 * نمی‌رسد و اینجا ثبت نمی‌شود. خوشبختانه لایت‌اسپید با ویرایش پست
 * صفحه را پاک می‌کند، پس *اولین* خزش پس از پینگ — دقیقا همان چیزی
 * که زمان واکنش می‌سنجد — معمولا از کش نیست و ثبت می‌شود. خزش‌های
 * بعدی ممکن است ثبت نشوند؛ ستون «تعداد بازدید» یک کران پایین است.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Hodima_Bot_Detector {

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

		if ( is_404() || is_feed() || is_search() || is_preview() ) {
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
		if ( '' === $ip ) {
			return;
		}

		if ( ! $this->is_verified_googlebot( $ip ) ) {
			return;
		}

		$path = (string) wp_parse_url( wp_unslash( (string) ( $_SERVER['REQUEST_URI'] ?? '/' ) ), PHP_URL_PATH );
		$url  = Hodima_GI_Helper::clean_url( rtrim( home_url(), '/' ) . '/' . ltrim( $path, '/' ) );

		Hodima_Crawler_DB_Queries::log_crawl_event( $url );
	}

	/**
	 * تأیید هویت گوگل‌بات با DNS معکوس و مستقیم (روش رسمی گوگل).
	 *
	 * تغییرات:
	 *   - gethostbyname فقط IPv4 برمی‌گرداند؛ گوگل‌بات روی IPv6 هم می‌خزد
	 *     و آن خزش‌ها همیشه «جعلی» تشخیص داده می‌شدند. حالا رکوردهای A و
	 *     AAAA هر دو بررسی می‌شوند.
	 *   - نتیجه منفی یک ساعت کش می‌شود نه هفت روز. یک خطای موقت DNS
	 *     دیگر یک IP واقعی گوگل را یک هفته از آمار حذف نمی‌کند.
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

		$verified = false;
		$host     = @gethostbyaddr( $ip );

		if ( is_string( $host ) && $host !== $ip && preg_match( '/\.(googlebot|google)\.com$/i', $host ) ) {

			$records = @dns_get_record( $host, DNS_A | DNS_AAAA );

			foreach ( (array) $records as $record ) {
				$resolved = $record['ip'] ?? ( $record['ipv6'] ?? '' );
				if ( '' !== $resolved && inet_pton( $resolved ) === inet_pton( $ip ) ) {
					$verified = true;
					break;
				}
			}
		}

		set_transient( $cache_key, $verified ? '1' : '0', $verified ? WEEK_IN_SECONDS : HOUR_IN_SECONDS );

		return $verified;
	}
}

Hodima_Bot_Detector::get_instance();
