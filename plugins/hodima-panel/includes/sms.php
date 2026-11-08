<?php
/**
 * پیامک: سه سرویس قابل تعویض (ملی‌پیامک، کاوه‌نگار، sms.ir) با پشتیبان خودکار.
 *
 * @package Hodima\Panel
 */

declare(strict_types=1);

namespace Hodima\Panel;

defined( 'ABSPATH' ) || exit;

/**
 * ارسال کد تأیید با الگوی (پترن) تأییدشده هر سرویس.
 *
 * پنل قدیمی تابع ارسال نداشت (فقط return true) و کاربر پیام «کد ارسال شد» را
 * می‌دید بی آنکه پیامکی برسد. حالا:
 * - یک سرویس «اصلی» و یک «پشتیبان» اختیاری (اگر اصلی خطا داد، همان کد با پشتیبان)؛
 * - کلید و رمز هر سرویس در گزینه hodima_panel_sms (نه در کد)؛ یا ثابت‌های
 *   wp-config (HODIMA_PANEL_SMS_{سرویس}_{فیلد}) که بر گزینه مقدم‌اند؛
 * - گزارش ۳۰ ارسال آخر با شماره نیمه‌پنهان (نه کد، نه کلید) برای عیب‌یابی.
 * سرویس تازه = یک ردیف در providers() و یک شاخه در call()؛ بقیه کد دست نمی‌خورد.
 */
final class Sms {

	public const OPTION = 'hodima_panel_sms';

	public const LOG_OPTION = 'hodima_panel_sms_log';

	private const LOG_SIZE = 30;

	/**
	 * مهلت هر درخواست (ثانیه).
	 *
	 * تا 0.2.3 هر سرویس ۱۲ ثانیه، یعنی با پشتیبان تا ۲۴ ثانیه انتظار مشتری و یک پردازش PHP
	 * درگیر. پاسخ عادی سرویس‌ها زیر ۲ ثانیه است.
	 */
	public const TIMEOUT = 8;

	/** بیشترین انتظار کل یک ارسال (اصلی + پشتیبان). */
	public const BUDGET = 14;

	/** پشتیبان با کمتر از این مهلت باقی‌مانده امتحان نمی‌شود. */
	private const MIN_TIMEOUT = 3;

	/**
	 * سرویس‌ها: نامک => [نام، فیلدهای تنظیم (کلید => برچسب)].
	 *
	 * @return array<string, array{label: string, fields: array<string, string>}>
	 */
	public static function providers(): array {
		return [
			'melipayamak' => [
				'label'  => 'ملی‌پیامک',
				'fields' => [
					'username' => 'نام کاربری وب‌سرویس',
					'password' => 'رمز وب‌سرویس (کلید API)',
					'body_id'  => 'کد الگو (bodyId)',
					'notify'   => 'کد الگوی خبر پاسخ پشتیبانی (اختیاری)',
				],
			],
			'kavenegar'   => [
				'label'  => 'کاوه‌نگار',
				'fields' => [
					'api_key'  => 'کلید API',
					'template' => 'نام الگو (template)',
					'notify'   => 'نام الگوی خبر پاسخ پشتیبانی (اختیاری)',
				],
			],
			'smsir'       => [
				'label'  => 'sms.ir',
				'fields' => [
					'api_key'     => 'کلید API',
					'template_id' => 'شناسه الگو (templateId)',
					'param'       => 'نام متغیر کد در الگو',
					'notify'      => 'شناسه الگوی خبر پاسخ پشتیبانی (اختیاری)',
				],
			],
		];
	}

	/**
	 * فیلدهای اختیاری (در «کامل بودن» سرویس شمرده نمی‌شوند).
	 *
	 * notify = الگوی دوم برای خبر «پاسخ تازه پشتیبانی» (از 0.2.8، خواست کاربر)؛ خالی = آن سرویس
	 * خبر پیامکی نمی‌فرستد (ایمیل می‌رود).
	 */
	public const OPTIONAL = [ 'notify' ];

	/** فیلد الگوی هر سرویس که notify جایش می‌نشیند (همان متغیر یک‌تایی الگو). */
	private const TEMPLATE_FIELD = [
		'melipayamak' => 'body_id',
		'kavenegar'   => 'template',
		'smsir'       => 'template_id',
	];

	/**
	 * تنظیم‌ها با ثابت‌های wp-config روی آن.
	 *
	 * @return array{primary: string, fallback: string, providers: array<string, array<string, string>>}
	 */
	public static function settings(): array {
		$saved = get_option( self::OPTION, [] );
		$saved = is_array( $saved ) ? $saved : [];
		$out   = [
			'primary'   => (string) ( $saved['primary'] ?? '' ),
			'fallback'  => (string) ( $saved['fallback'] ?? '' ),
			'providers' => [],
		];
		foreach ( self::providers() as $id => $spec ) {
			foreach ( array_keys( $spec['fields'] ) as $field ) {
				$constant = 'HODIMA_PANEL_SMS_' . strtoupper( $id . '_' . $field );
				$value    = defined( $constant ) ? (string) constant( $constant ) : (string) ( $saved['providers'][ $id ][ $field ] ?? '' );
				if ( 'param' === $field && '' === $value ) {
					$value = 'CODE';
				}
				$out['providers'][ $id ][ $field ] = trim( $value );
			}
		}
		if ( ! isset( self::providers()[ $out['primary'] ] ) ) {
			$out['primary'] = '';
		}
		if ( ! isset( self::providers()[ $out['fallback'] ] ) || $out['fallback'] === $out['primary'] ) {
			$out['fallback'] = '';
		}
		return $out;
	}

	/**
	 * یک سرویس همه فیلدهای لازمش را دارد؟
	 *
	 * @param string $id نامک سرویس.
	 */
	public static function is_configured( string $id ): bool {
		$config = array_diff_key( self::settings()['providers'][ $id ] ?? [], array_flip( self::OPTIONAL ) );
		return [] !== $config && ! in_array( '', $config, true );
	}

	/** پیامک قابل ارسال است؟ (سرویس اصلی انتخاب و کامل) */
	public static function ready(): bool {
		$primary = self::settings()['primary'];
		return '' !== $primary && self::is_configured( $primary );
	}

	/**
	 * فرستادن کد تأیید: اصلی، و اگر خطا داد پشتیبان.
	 *
	 * @param string $mobile شماره ۰۹…
	 * @param string $code   کد.
	 * @return array{ok: bool, provider: string, error: string}
	 */
	public static function send_code( string $mobile, string $code ): array {
		$settings = self::settings();
		$result   = [ 'ok' => false, 'provider' => '', 'error' => 'هیچ سرویس پیامکی تنظیم نشده است.' ];
		$start    = microtime( true );
		foreach ( array_filter( [ $settings['primary'], $settings['fallback'] ] ) as $id ) {
			if ( ! self::is_configured( $id ) ) {
				continue;
			}
			$left = (int) floor( self::BUDGET - ( microtime( true ) - $start ) );
			if ( $left < self::MIN_TIMEOUT ) {
				break; // سرویس اصلی همه مهلت را گرفت؛ مشتری بیشتر منتظر نماند
			}
			$result = self::send_with( $id, $mobile, $code, min( self::TIMEOUT, $left ) );
			if ( $result['ok'] ) {
				break;
			}
		}
		return $result;
	}

	/**
	 * خبر «پاسخ تازه پشتیبانی» با الگوی notify: اصلی، و اگر خطا داد پشتیبان (فقط سرویسی که الگو دارد).
	 *
	 * @param string $mobile شماره ۰۹…
	 * @param string $token  تنها متغیر الگو (شماره سفارش یا کد پیگیری تیکت؛ بی فاصله).
	 * @return array{ok: bool, provider: string, error: string}
	 */
	public static function send_notice( string $mobile, string $token ): array {
		$settings = self::settings();
		$result   = [ 'ok' => false, 'provider' => '', 'error' => 'الگوی خبر پاسخ پشتیبانی تنظیم نشده است.' ];
		$start    = microtime( true );
		foreach ( array_filter( [ $settings['primary'], $settings['fallback'] ] ) as $id ) {
			$config = $settings['providers'][ $id ] ?? [];
			if ( ! self::is_configured( $id ) || '' === ( $config['notify'] ?? '' ) ) {
				continue;
			}
			$left = (int) floor( self::BUDGET - ( microtime( true ) - $start ) );
			if ( $left < self::MIN_TIMEOUT ) {
				break;
			}
			$config[ self::TEMPLATE_FIELD[ $id ] ] = $config['notify'];
			$error  = self::call( $id, $config, $mobile, $token, min( self::TIMEOUT, $left ) );
			$result = [ 'ok' => '' === $error, 'provider' => $id, 'error' => $error ];
			self::log( $result, $mobile );
			if ( $result['ok'] ) {
				break;
			}
		}
		return $result;
	}

	/**
	 * ارسال با یک سرویس مشخص (پشتیبان را امتحان نمی‌کند؛ برای «پیامک آزمایشی»).
	 *
	 * @param string $id     نامک سرویس.
	 * @param string $mobile شماره.
	 * @param string $code    کد.
	 * @param int    $timeout مهلت درخواست (ثانیه).
	 * @return array{ok: bool, provider: string, error: string}
	 */
	public static function send_with( string $id, string $mobile, string $code, int $timeout = self::TIMEOUT ): array {
		$config = self::settings()['providers'][ $id ] ?? [];
		$error  = self::call( $id, $config, $mobile, $code, $timeout );
		$result = [ 'ok' => '' === $error, 'provider' => $id, 'error' => $error ];
		self::log( $result, $mobile );
		return $result;
	}

	/**
	 * درخواست به سرویس و تفسیر پاسخ.
	 *
	 * @param string                $id     نامک.
	 * @param array<string, string> $c      تنظیم سرویس.
	 * @param string                $mobile شماره ۰۹…
	 * @param string                $code    کد.
	 * @param int                   $timeout مهلت (ثانیه).
	 * @return string خطا ('' = موفق).
	 */
	private static function call( string $id, array $c, string $mobile, string $code, int $timeout ): string {
		$response = match ( $id ) {
			// وب‌سرویس REST ملی‌پیامک، ارسال با خط خدماتی و الگو
			'melipayamak' => wp_remote_post(
				'https://rest.payamak-panel.com/api/SendSMS/BaseServiceNumber',
				[
					'timeout' => $timeout,
					'body'    => [ 'username' => $c['username'], 'password' => $c['password'], 'text' => $code, 'to' => $mobile, 'bodyId' => $c['body_id'] ],
				]
			),
			'kavenegar'   => wp_remote_post(
				'https://api.kavenegar.com/v1/' . rawurlencode( $c['api_key'] ) . '/verify/lookup.json',
				[
					'timeout' => $timeout,
					'body'    => [ 'receptor' => $mobile, 'token' => $code, 'template' => $c['template'] ],
				]
			),
			'smsir'       => wp_remote_post(
				'https://api.sms.ir/v1/send/verify',
				[
					'timeout' => $timeout,
					'headers' => [ 'X-API-KEY' => $c['api_key'], 'Content-Type' => 'application/json', 'Accept' => 'application/json' ],
					'body'    => (string) wp_json_encode( [ 'mobile' => $mobile, 'templateId' => (int) $c['template_id'], 'parameters' => [ [ 'name' => $c['param'], 'value' => $code ] ] ] ),
				]
			),
			default       => new \WP_Error( 'unknown', 'سرویس ناشناخته' ),
		};

		if ( is_wp_error( $response ) ) {
			return 'ارتباط با سرویس برقرار نشد: ' . $response->get_error_message();
		}
		$http = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$body = is_array( $body ) ? $body : [];

		return match ( $id ) {
			// RetStatus=1 و Value شناسه ارسال (عدد بلند)؛ Value کوتاه = کد خطای ملی‌پیامک
			'melipayamak' => ( 1 === (int) ( $body['RetStatus'] ?? 0 ) && strlen( (string) ( $body['Value'] ?? '' ) ) > 5 )
				? '' : 'ملی‌پیامک: ' . (string) ( $body['StrRetStatus'] ?? $body['Value'] ?? ( 'HTTP ' . $http ) ),
			'kavenegar'   => 200 === (int) ( $body['return']['status'] ?? 0 )
				? '' : 'کاوه‌نگار: ' . (string) ( $body['return']['message'] ?? ( 'HTTP ' . $http ) ),
			'smsir'       => 1 === (int) ( $body['status'] ?? 0 )
				? '' : 'sms.ir: ' . (string) ( $body['message'] ?? ( 'HTTP ' . $http ) ),
			default       => 'سرویس ناشناخته',
		};
	}

	/**
	 * شماره نیمه‌پنهان برای گزارش (۰۹۱۲***۶۷۸۹).
	 *
	 * @param string $mobile شماره.
	 */
	public static function mask( string $mobile ): string {
		return strlen( $mobile ) === 11 ? substr( $mobile, 0, 4 ) . '***' . substr( $mobile, -4 ) : '***';
	}

	/**
	 * افزودن به گزارش ارسال‌ها (بدون کد و کلید).
	 *
	 * @param array{ok: bool, provider: string, error: string} $result نتیجه.
	 * @param string                                          $mobile شماره.
	 */
	private static function log( array $result, string $mobile ): void {
		$log = get_option( self::LOG_OPTION, [] );
		$log = is_array( $log ) ? $log : [];
		array_unshift( $log, [ 'time' => time(), 'provider' => $result['provider'], 'to' => self::mask( $mobile ), 'ok' => $result['ok'], 'error' => mb_substr( $result['error'], 0, 200 ) ] );
		update_option( self::LOG_OPTION, array_slice( $log, 0, self::LOG_SIZE ), false );
	}

	/**
	 * گزارش ارسال‌ها (تازه‌ترین اول).
	 *
	 * @return list<array{time: int, provider: string, to: string, ok: bool, error: string}>
	 */
	public static function log_entries(): array {
		$log = get_option( self::LOG_OPTION, [] );
		return is_array( $log ) ? array_values( $log ) : [];
	}
}
