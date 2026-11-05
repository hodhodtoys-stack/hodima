<?php
/**
 * برگه پنل در سایت: تشخیص، آدرس، قالب صفحه، کش و فایل‌های CSS/JS.
 *
 * @package Hodima\Panel
 */

declare(strict_types=1);

namespace Hodima\Panel;

defined( 'ABSPATH' ) || exit;

/**
 * برگه پنل کاربری.
 *
 * یک برگه وردپرس (پیش‌فرض با نامک user-panel) که افزونه هنگام فعال‌سازی می‌سازد
 * و شناسه‌اش را نگه می‌دارد. پنل قدیمی به قالب (تمپلیت page-user-panel.php) وابسته
 * بود و آدرس /user-panel را در ده‌ها جا ثابت نوشته بود؛ حالا همه آدرس‌ها از url() می‌آیند.
 */
final class Page {

	public const OPTION = 'hodima_panel_page_id';

	public const SLUG = 'user-panel';

	/** بخش‌های پنل (مقدار ?section=). */
	public const SECTIONS = [ 'dashboard', 'prebuy', 'preinvoice', 'orders', 'support', 'profile' ];

	/** ثبت هوک‌ها. */
	public static function boot(): void {
		add_action( 'template_redirect', [ self::class, 'before_output' ], 5 );
		add_filter( 'template_include', [ self::class, 'template' ], 99 );
		add_action( 'wp_enqueue_scripts', [ self::class, 'assets' ] );
		add_filter( 'wp_robots', [ self::class, 'robots' ] );
		add_filter( 'hodima_robots_private_paths', [ self::class, 'private_paths' ] );
		add_filter( 'display_post_states', [ self::class, 'post_state' ], 10, 2 );
	}

	/** برگه پنل را اگر نیست بساز (یا برگه موجود با نامک user-panel را بردار). */
	public static function ensure(): int {
		$id = self::id();
		if ( $id > 0 ) {
			return $id;
		}

		$existing = get_page_by_path( self::SLUG );
		if ( $existing instanceof \WP_Post && 'trash' !== $existing->post_status ) {
			$id = (int) $existing->ID;
		} else {
			$id = (int) wp_insert_post(
				[
					'post_type'      => 'page',
					'post_status'    => 'publish',
					'post_title'     => 'پنل کاربری',
					'post_name'      => self::SLUG,
					'post_content'   => '',
					'comment_status' => 'closed',
					'ping_status'    => 'closed',
				]
			);
		}

		if ( $id > 0 ) {
			update_option( self::OPTION, $id );
		}
		return $id;
	}

	/** شناسه برگه پنل (۰ = نیست یا منتشر نشده). */
	public static function id(): int {
		$id   = (int) get_option( self::OPTION, 0 );
		$post = $id > 0 ? get_post( $id ) : null;
		return ( $post instanceof \WP_Post && 'page' === $post->post_type && 'publish' === $post->post_status ) ? $id : 0;
	}

	/**
	 * آدرس پنل، با پارامترهای دلخواه (مثلا section).
	 *
	 * @param array<string, string|int> $args پارامترهای آدرس.
	 */
	public static function url( array $args = [] ): string {
		$id   = self::id();
		$base = $id > 0 ? (string) get_permalink( $id ) : home_url( '/' . self::SLUG . '/' );
		return $args ? add_query_arg( $args, $base ) : $base;
	}

	/** آیا درخواست فعلی برگه پنل است؟ */
	public static function is_panel(): bool {
		$id = self::id();
		return $id > 0 && ! is_admin() && is_page( $id );
	}

	/** بخش فعلی پنل (ناشناخته = داشبورد). */
	public static function section(): string {
		$section = isset( $_GET['section'] ) ? sanitize_key( wp_unslash( $_GET['section'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط انتخاب نما.
		return in_array( $section, self::SECTIONS, true ) ? $section : 'dashboard';
	}

	/**
	 * کارهای پیش از چاپ صفحه.
	 *
	 * پنل قدیمی اول سربرگ سایت را چاپ می‌کرد و بعد ورود، قفل پروفایل و فرم‌ها را
	 * پردازش می‌کرد؛ کوکی ورود و ریدایرکت بعد از چاپ خروجی فقط روی هاستی کار می‌کرد
	 * که خروجی را بافر کند. هر پردازشی که کوکی/ریدایرکت دارد به اکشن hodima_panel_request
	 * وصل می‌شود که اینجا، پیش از هر خروجی، اجرا می‌شود.
	 */
	public static function before_output(): void {
		if ( ! self::is_panel() ) {
			return;
		}

		// صفحه شخصی است: نه در کش مرورگر و پراکسی، نه در کش لایت‌اسپید
		// (فرم ورود کش‌شده nonce کهنه دارد و صفحه واردشده اطلاعات یک مشتری را به دیگری نشان می‌داد).
		nocache_headers();
		do_action( 'litespeed_control_set_nocache', 'hodima-panel' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- هوک لایت‌اسپید

		do_action( 'hodima_panel_request', self::section() );
	}

	/**
	 * قالب صفحه پنل (مستقل از قالب سایت).
	 *
	 * @param string $template قالبی که وردپرس انتخاب کرده.
	 */
	public static function template( string $template ): string {
		return self::is_panel() ? HODIMA_PANEL_DIR . '/templates/panel.php' : $template;
	}

	/** CSS/JS پنل فقط روی برگه پنل. */
	public static function assets(): void {
		if ( ! self::is_panel() ) {
			return;
		}

		$url = HODIMA_PANEL_URL . '/assets/';

		// یک CSS و یک JS برای همه نماها (ورود هم)؛ گفتگوی سفارش فقط در «سفارش‌ها».
		wp_enqueue_style( 'hodima-panel', $url . 'css/panel.css', [], asset_version( 'assets/css/panel.css' ) );
		wp_enqueue_script( 'hodima-panel-js', $url . 'js/panel.js', [], asset_version( 'assets/js/panel.js' ), [ 'strategy' => 'defer' ] );
		wp_localize_script( 'hodima-panel-js', 'hodimaPanel', [ 'ajaxUrl' => admin_url( 'admin-ajax.php' ) ] );

		if ( is_user_logged_in() && 'orders' === self::section() ) {
			wp_enqueue_script( 'hodima-panel-chat', $url . 'js/chat.js', [], asset_version( 'assets/js/chat.js' ), [ 'strategy' => 'defer' ] );
			wp_localize_script(
				'hodima-panel-chat',
				'hodimaPanelChat',
				[
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'hodima_panel_chat' ),
				]
			);
		}
	}

	/**
	 * پنل در نتایج جستجو نیاید.
	 *
	 * @param array<string, bool|string> $robots دستورهای robots.
	 * @return array<string, bool|string>
	 */
	public static function robots( array $robots ): array {
		if ( self::is_panel() ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
		}
		return $robots;
	}

	/**
	 * مسیر پنل در robots.txt ماژول سئوباکس (مسیرهای خصوصی).
	 *
	 * @param mixed $paths مسیرها.
	 * @return array<int, string>
	 */
	public static function private_paths( mixed $paths ): array {
		$paths = is_array( $paths ) ? $paths : [];
		$id    = self::id();
		$uri   = $id > 0 ? get_page_uri( $id ) : self::SLUG;
		if ( is_string( $uri ) && '' !== $uri ) {
			$paths[] = '/' . trim( $uri, '/' ) . '/';
		}
		return $paths;
	}

	/**
	 * برچسب «پنل کاربری» کنار نام برگه در فهرست برگه‌های پیشخوان.
	 *
	 * @param array<string, string> $states برچسب‌ها.
	 * @param \WP_Post              $post   برگه.
	 * @return array<string, string>
	 */
	public static function post_state( array $states, \WP_Post $post ): array {
		if ( (int) $post->ID === self::id() ) {
			$states['hodima_panel'] = 'برگه پنل کاربری (Hodima Panel)';
		}
		return $states;
	}
}
