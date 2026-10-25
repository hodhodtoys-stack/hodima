<?php
/**
 * فالبک سیستم طراحی پیشخوان (وقتی Hodima Core فعال نیست)
 *
 * هدر، تب‌ها و آیکون مشترک صفحه‌های هدیما را Hodima Core تعریف می‌کند
 * (includes/admin-ui.php). این فایل فقط وقتی لود می‌شود که Core فعال نیست تا صفحه‌های
 * افزونه نه با خطای «تابع تعریف نشده» از کار بیفتند و نه بی‌استایل بمانند:
 *   - همان نشانه‌گذاری هدر/تب Core؛
 *   - کپی استایل و رفتار مشترک Core (ui/core-ui.css و core-ui.js؛ منبع
 *     plugins/hodima-core/assets/admin-ui.*، کپی را bin/build.sh می‌نویسد) فقط روی
 *     صفحه‌های هدیما لود می‌شود. منوها مثل قبل در سطح اول پیشخوان می‌مانند.
 * نسخه‌های یکسان این فایل در هر سه افزونه هست؛ همه با function_exists گارد شده‌اند
 * (دو افزونه بدون Core هم‌زمان فعال باشند، هر کدام یک بار ثبت می‌شود).
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'hodima_admin_icon' ) ) {
	function hodima_admin_icon( string $icon, string $class = '' ): string {
		$icon = str_starts_with( $icon, 'dashicons-' ) ? $icon : 'dashicons-' . ( '' !== $icon ? $icon : 'admin-generic' );
		return sprintf( '<span class="dashicons %1$s %2$s" aria-hidden="true"></span>', esc_attr( $icon ), esc_attr( $class ) );
	}
}

if ( ! function_exists( 'hodima_admin_tabs' ) ) {
	function hodima_admin_tabs( array $tabs, string $current, string $label = 'بخش‌ها' ): void {
		?>
		<nav class="hd-tabs" aria-label="<?php echo esc_attr( $label ); ?>">
			<?php foreach ( $tabs as $key => $tab ) : ?>
				<a class="hd-tabs__item" href="<?php echo esc_url( $tab['url'] ); ?>"<?php echo (string) $key === $current ? ' aria-current="page"' : ''; ?>>
					<?php
					if ( ! empty( $tab['icon'] ) ) {
						echo hodima_admin_icon( $tab['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput
					}
					echo esc_html( $tab['label'] );
					?>
				</a>
			<?php endforeach; ?>
		</nav>
		<?php
	}
}

if ( ! function_exists( 'hodima_admin_header' ) ) {
	function hodima_admin_header( array $args ): void {

		$title       = (string) ( $args['title'] ?? '' );
		$description = (string) ( $args['description'] ?? '' );
		$icon        = (string) ( $args['icon'] ?? 'dashicons-admin-generic' );
		$badge       = (string) ( $args['badge'] ?? 'ابزارهای هدیما' );
		$actions     = (string) ( $args['actions'] ?? '' );
		?>
		<header class="hd-header">
			<?php echo hodima_admin_icon( $icon, 'hd-header__icon' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<div class="hd-header__body">
				<?php if ( '' !== $badge ) : ?>
					<span class="hd-header__badge"><?php echo esc_html( $badge ); ?></span>
				<?php endif; ?>
				<h1 class="hd-header__title"><?php echo esc_html( $title ); ?></h1>
				<?php if ( '' !== $description ) : ?>
					<p class="hd-header__desc"><?php echo esc_html( $description ); ?></p>
				<?php endif; ?>
			</div>
			<?php if ( '' !== $actions ) : ?>
				<div class="hd-header__actions"><?php echo wp_kses_post( $actions ); ?></div>
			<?php endif; ?>
		</header>
		<?php
		if ( ! empty( $args['tabs'] ) ) {
			hodima_admin_tabs( (array) $args['tabs'], (string) ( $args['current'] ?? '' ), (string) ( $args['tabs_label'] ?? 'بخش‌ها' ) );
		}
		echo '<hr class="wp-header-end">';
	}
}

if ( ! function_exists( 'hodima_admin_page_open' ) ) {
	function hodima_admin_page_open( array $args, string $class = '' ): void {
		printf( '<div class="wrap hd-wrap %s">', esc_attr( $class ) );
		hodima_admin_header( $args );
	}
}

if ( ! function_exists( 'hodima_admin_page_close' ) ) {
	function hodima_admin_page_close(): void {
		echo '</div>';
	}
}

if ( ! function_exists( 'hodima_admin_notice' ) ) {
	function hodima_admin_notice( string $message, string $type = 'success', bool $echo = true ): string {
		$type = in_array( $type, [ 'success', 'error', 'warning', 'info' ], true ) ? $type : 'info';
		$html = sprintf(
			'<div class="notice notice-%1$s is-dismissible hd-notice" role="%2$s"><p>%3$s</p></div>',
			esc_attr( $type ),
			'error' === $type ? 'alert' : 'status',
			wp_kses_post( $message )
		);
		if ( $echo ) {
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		return $html;
	}
}

if ( ! function_exists( 'hodima_admin_flash' ) ) {
	/** پیام برای بارگذاری بعدی صفحه (نسخه ساده Core). */
	function hodima_admin_flash( string $message, string $type = 'success' ): void {
		$key   = 'hodima_admin_flash_' . get_current_user_id();
		$queue = get_transient( $key );
		$queue = is_array( $queue ) ? $queue : [];
		$queue[] = [ $type, $message ];
		set_transient( $key, array_slice( $queue, -5 ), 5 * MINUTE_IN_SECONDS );
	}

	add_action( 'admin_notices', static function (): void {
		$key   = 'hodima_admin_flash_' . get_current_user_id();
		$queue = get_transient( $key );
		if ( ! is_array( $queue ) || ! $queue ) {
			return;
		}
		delete_transient( $key );
		foreach ( $queue as [ $type, $message ] ) {
			hodima_admin_notice( (string) $message, (string) $type );
		}
	} );
}

if ( ! function_exists( 'hodima_admin_menu_parent' ) ) {
	/** بدون Core منوی مشترکی نیست؛ رشته خالی یعنی «منوی سطح اول بساز». */
	function hodima_admin_menu_parent(): string {
		return '';
	}
}

/*
 * کپی استایل و رفتار مشترک Core. همه افزونه‌های بدون Core همین دسته را ثبت می‌کنند؛
 * ثبت دوباره یک دسته نادیده گرفته می‌شود، پس مهم نیست کدام افزونه اول باشد (کپی‌ها یکی‌اند).
 */
if ( ! function_exists( 'hodima_fallback_admin_screen' ) ) {

	/** صفحه فعلی جزو پیشخوان هدیما است؟ (همان قاعده Core: page=hodima… یا پست‌تایپ‌های هدیما) */
	function hodima_fallback_admin_screen(): bool {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- فقط تشخیص صفحه
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$type = isset( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '';
		// phpcs:enable
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( '' === $type && $screen ) {
			$type = (string) $screen->post_type;
		}

		return str_starts_with( $page, 'hodima' ) || in_array( $type, [ 'hd_notification', 'video', 'hodima_phone_lead' ], true );
	}

	add_action( 'admin_enqueue_scripts', static function (): void {

		$dir = dirname( __DIR__ ) . '/ui/';
		$url = plugin_dir_url( dirname( __DIR__ ) . '/index.php' ) . 'ui/';

		if ( ! is_file( $dir . 'core-ui.css' ) ) {
			return; // سورس بدون ساخت: کپی در ZIP/link.sh نوشته می‌شود
		}

		if ( ! wp_style_is( 'hodima-admin-ui', 'registered' ) ) {
			wp_register_style( 'hodima-admin-ui', $url . 'core-ui.css', [ 'dashicons' ], (string) filemtime( $dir . 'core-ui.css' ) );
			wp_register_script( 'hodima-admin-ui', $url . 'core-ui.js', [], is_file( $dir . 'core-ui.js' ) ? (string) filemtime( $dir . 'core-ui.js' ) : false, [ 'in_footer' => true, 'strategy' => 'defer' ] );
		}

		if ( hodima_fallback_admin_screen() ) {
			wp_enqueue_style( 'hodima-admin-ui' );
			wp_enqueue_script( 'hodima-admin-ui' );
		}
	}, 5 );

	add_filter( 'admin_body_class', static fn( string $classes ): string => hodima_fallback_admin_screen() && ! str_contains( $classes, 'hodima-admin' ) ? $classes . ' hodima-admin ' : $classes );
}
