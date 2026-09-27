<?php
/**
 * فالبک گراف واحد اسکیما
 *
 * hodima_schema_add() را Hodima Core تعریف می‌کند (includes/schema-graph.php):
 * همه JSON-LDهای صفحه در یک @graph ادغام و یک بار در فوتر چاپ می‌شوند.
 * این فایل فقط وقتی لود می‌شود که Hodima Core فعال نباشد؛ در آن حالت هر
 * payload مثل قبل در یک تگ جداگانه چاپ می‌شود تا هیچ اسکیمایی گم نشود.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'hodima_schema_add' ) ) {
	function hodima_schema_add( array $payload, string $source = '' ): void {
		if ( empty( $payload ) ) {
			return;
		}
		if ( array_is_list( $payload ) ) {
			$payload = [ '@context' => 'https://schema.org', '@graph' => $payload ];
		} elseif ( ! isset( $payload['@context'] ) ) {
			$payload = [ '@context' => 'https://schema.org' ] + $payload;
		}
		echo '<script type="application/ld+json">' . wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP ) . "</script>\n";
	}
}
