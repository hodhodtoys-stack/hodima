<?php
/**
 * Cache tools (Cloudflare purge / preload)
 * Path: components/google-indexing-api/modules/tools.php
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Hodima_GI_Tools {

	public static function cloudflare_purge( string $url ): bool {

		$settings = Hodima_GI_Helper::get_settings();
		$token    = (string) ( $settings['cf_token'] ?? '' );
		$zone     = strtolower( (string) ( $settings['cf_zone_id'] ?? '' ) );

		if ( '' === $token || '' === $zone ) {
			return false;
		}

		// شناسه Zone کلادفلر ۳۲ کاراکتر هگز است؛ قبل از قرار گرفتن در آدرس API بررسی می‌شود
		if ( ! preg_match( '/^[a-f0-9]{32}$/', $zone ) ) {
			return false;
		}

		$res = wp_remote_post( "https://api.cloudflare.com/client/v4/zones/{$zone}/purge_cache", [
			'timeout' => 8,
			'headers' => [
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
			],
			'body'    => (string) wp_json_encode( [ 'files' => [ Hodima_GI_Helper::clean_url( $url ) ] ] ),
		] );

		return ! is_wp_error( $res ) && 200 === (int) wp_remote_retrieve_response_code( $res );
	}

	public static function preload_cache( string $url ): void {
		wp_remote_get( Hodima_GI_Helper::clean_url( $url ), [
			'timeout'   => 0.5,
			'blocking'  => false,
			'sslverify' => true,
			'headers'   => [ 'User-Agent' => 'Hodima-Smart-Preloader/1.0' ],
		] );
	}
}
