<?php
/**
 * سخت‌سازی امنیتی پایه وردپرس (مستقل از قالب)
 * Path: hodima-core/includes/hardening.php
 *
 * از قالب (inc/performance/wp-cleanup.php، بخش ۵) منتقل شد — بازسازی قالب،
 * مرحله ۲. بستن XML-RPC تصمیم امنیتی سایت است؛ با عوض شدن قالب نباید
 * بی‌صدا دوباره باز شود.
 *
 * XML-RPC راه رایج حمله حدس رمز (صدها رمز در یک درخواست system.multicall)
 * و سوءاستفاده پینگ‌بک (DDoS بازتابی) است و این سایت از آن استفاده نمی‌کند.
 * برای اپلیکیشن موبایل وردپرس یا Jetpack که به آن نیاز دارند:
 *     add_filter( 'hodima_disable_xmlrpc', '__return_false' );
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

// فیلتر هنگام اجرا خوانده می‌شود (نه هنگام لود افزونه) تا functions.php قالب هم بتواند آن را عوض کند
add_filter( 'xmlrpc_enabled', static fn( $enabled ) => hodima_core_xmlrpc_disabled() ? false : $enabled );

// هدر X-Pingback آدرس xmlrpc.php را به هر بازدیدکننده‌ای اعلام می‌کرد
add_filter( 'wp_headers', 'hodima_core_remove_pingback_header' );

function hodima_core_xmlrpc_disabled(): bool {
	return (bool) apply_filters( 'hodima_disable_xmlrpc', true );
}

function hodima_core_remove_pingback_header( array $headers ): array {
	if ( hodima_core_xmlrpc_disabled() ) {
		unset( $headers['X-Pingback'] );
	}
	return $headers;
}
