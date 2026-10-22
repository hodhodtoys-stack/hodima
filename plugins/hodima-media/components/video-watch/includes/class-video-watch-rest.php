<?php
/**
 * مسیرهای REST آمار ویدیو (بارگذار: ../db-video-watch.php)
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * مسیرهای REST: ثبت تعامل، کلید تازه برای صفحه کش‌شده، جمع‌بندی دستی.
 */
final class Hodima_Video_Watch_Rest {

	public static function init(): void {
		add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
	}

	public static function register_routes(): void {
		register_rest_route(
			'video-watch/v1',
			'/track',
			[
				'methods'             => 'POST',
				'callback'            => [ self::class, 'track' ],
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			'video-watch/v1',
			'/nonce',
			[
				'methods'             => 'GET',
				'callback'            => [ self::class, 'nonce' ],
				'permission_callback' => '__return_true',
			]
		);

		register_rest_route(
			'video-watch/v1',
			'/cron',
			[
				'methods'             => 'GET',
				'callback'            => [ self::class, 'cron' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	public static function track( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		if ( ! Hodima_Video_Watch_Analytics::verify_token( (string) $request->get_header( 'X-HVW-Nonce' ) ) ) {
			return new WP_Error( 'forbidden', __( 'دسترسی غیرمجاز. توکن امنیتی معتبر نیست.', 'hodima' ), [ 'status' => 403 ] );
		}

		/*
		 * IP واقعی با تابع مرکزی Core: به هدرهای CF-Connecting-IP و
		 * X-Forwarded-For فقط پشت Cloudflare اعتماد می‌شود (هر کلاینتی می‌تواند
		 * آن‌ها را بفرستد و محدودیت نرخ را دور بزند). سایت فعلا پشت CDN نیست؛ اگر
		 * CDN دیگری (آروان، QUIC.cloud…) اضافه شد، همه بازدیدکنندگان IP همان CDN را
		 * می‌گیرند و سقف روزانه IP و محدودیت نرخ برای کل سایت یکی می‌شود: اول
		 * hodima_get_client_ip را برای آن CDN درست کنید.
		 */
		$client_ip = function_exists( 'hodima_get_client_ip' )
			? hodima_get_client_ip()
			: (string) filter_var( sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) ), FILTER_VALIDATE_IP );

		$rate_key = function_exists( 'hodima_rate_limit_key' ) ? hodima_rate_limit_key( 'hvw_rate_' ) : 'hvw_rate_' . md5( $client_ip );
		$requests = (int) get_transient( $rate_key );
		if ( $requests >= Hodima_Video_Watch_Analytics::RATE_PER_MINUTE ) {
			return new WP_Error( 'rate_limit_exceeded', __( 'تعداد درخواست‌ها بیش از حد مجاز است.', 'hodima' ), [ 'status' => 429 ] );
		}
		set_transient( $rate_key, $requests + 1, MINUTE_IN_SECONDS );

		$video_id = (int) $request->get_param( 'id' );
		$type     = Hodima_Video_Watch_Counter::tryFrom( (string) $request->get_param( 'action' ) );

		// فقط ویدیوی منتشرشده؛ تا Media 2.0.8 پیش‌نویس، خصوصی و زباله‌دان هم بازدید می‌گرفت
		if ( ! $video_id || ! $type || 'video' !== get_post_type( $video_id ) || 'publish' !== get_post_status( $video_id ) ) {
			return new WP_Error( 'invalid_data', __( 'درخواست نامعتبر است.', 'hodima' ), [ 'status' => 422 ] );
		}

		$counted = Hodima_Video_Watch_Analytics::accept( $video_id, $type, $client_ip );
		if ( $counted ) {
			Hodima_Video_Watch_Analytics::record_interaction( $video_id, $type );
		}

		return rest_ensure_response(
			[
				'success' => true,
				'counted' => $counted,
				'views'   => Hodima_Video_Watch_Analytics::get_total_views( $video_id ),
			]
		);
	}

	/**
	 * کلید تازه برای صفحه‌ای که از کش آمده و کلیدش کهنه است.
	 *
	 * این پاسخ نباید کش شود: لایت‌اسپید پاسخ‌های GET رابط REST را کش می‌کند و
	 * کلید کهنه به همه داده می‌شد.
	 */
	public static function nonce(): WP_REST_Response {
		nocache_headers();
		do_action( 'litespeed_control_set_nocache', 'video-watch nonce' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- هوک لایت‌اسپید
		$response = new WP_REST_Response( [ 'nonce' => Hodima_Video_Watch_Analytics::token() ] );
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
		return $response;
	}

	/**
	 * جمع‌بندی دستی برای کران بیرونی سرور (WP-Cron خودش هر ۵ دقیقه اجرا می‌کند).
	 *
	 * یکی از این دو لازم است:
	 *   ۱. کلید در wp-config.php: define( 'HOD_VIDEO_WATCH_CRON_KEY', 'رشته-طولانی-تصادفی' );
	 *      و ‎/wp-json/video-watch/v1/cron?key=...
	 *   ۲. مدیر واردشده. REST بدون سربرگ X-WP-Nonce کاربر را مهمان می‌کند، پس
	 *      تا Media 2.0.8 باز کردن همین آدرس در مرورگر مدیر هم ۴۰۳ می‌داد؛ حالا
	 *      کوکی ورود مستقیم بررسی می‌شود. (اجرای ناخواسته بی‌خطر است: جمع‌بندی
	 *      تکرارپذیر است و قفل ۲۹۰ ثانیه‌ای دارد.)
	 */
	public static function cron( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$key     = defined( 'HOD_VIDEO_WATCH_CRON_KEY' ) ? (string) constant( 'HOD_VIDEO_WATCH_CRON_KEY' ) : '';
		$has_key = '' !== $key && hash_equals( $key, (string) $request->get_param( 'key' ) );

		$cookie_user = (int) wp_validate_auth_cookie( '', 'logged_in' );
		$is_admin    = current_user_can( 'manage_options' ) || ( $cookie_user && user_can( $cookie_user, 'manage_options' ) );

		if ( ! $has_key && ! $is_admin ) {
			return new WP_Error( 'forbidden', __( 'دسترسی غیرمجاز.', 'hodima' ), [ 'status' => 403 ] );
		}

		Hodima_Video_Watch_Analytics::flush_counters();
		return rest_ensure_response(
			[
				'success' => true,
				'message' => 'Queue processed.',
			]
		);
	}
}
