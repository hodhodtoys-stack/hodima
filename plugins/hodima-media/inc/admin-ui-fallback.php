<?php
/**
 * فالبک سیستم طراحی پیشخوان
 *
 * هدر، تب‌ها و آیکون مشترک صفحه‌های هدیما را Hodima Core تعریف می‌کند
 * (includes/admin-ui.php). این فایل فقط وقتی لود می‌شود که Core فعال نباشد
 * تا صفحه‌های افزونه با خطای «تابع تعریف نشده» از کار نیفتند؛ خروجی ساده
 * است (بدون استایل مشترک) و منوها مثل قبل در سطح اول پیشخوان می‌مانند.
 * نسخه‌های یکسان این فایل در هر سه افزونه هست؛ همه با function_exists گارد شده‌اند.
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
		echo '<nav class="nav-tab-wrapper hd-tabs" aria-label="' . esc_attr( $label ) . '">';
		foreach ( $tabs as $key => $tab ) {
			printf(
				'<a class="nav-tab hd-tabs__item%1$s" href="%2$s"%3$s>%4$s</a>',
				(string) $key === $current ? ' nav-tab-active' : '',
				esc_url( $tab['url'] ),
				(string) $key === $current ? ' aria-current="page"' : '',
				esc_html( $tab['label'] )
			);
		}
		echo '</nav>';
	}
}

if ( ! function_exists( 'hodima_admin_header' ) ) {
	function hodima_admin_header( array $args ): void {
		echo '<h1 class="wp-heading-inline">' . esc_html( (string) ( $args['title'] ?? '' ) ) . '</h1>';
		if ( ! empty( $args['description'] ) ) {
			echo '<p class="description">' . esc_html( (string) $args['description'] ) . '</p>';
		}
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
		$html = sprintf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $type ), wp_kses_post( $message ) );
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
