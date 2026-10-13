<?php
/**
 * استوری‌ها — درخواست‌های ثبت بازدید و اشتراک (admin-ajax).
 *
 * @package Hodima_Media
 */

declare(strict_types=1);

namespace Hodima\Stories;

use Hodima\Stories\Analytics\WP_Story_Analytics;

defined( 'ABSPATH' ) || exit;

/**
 * ثبت آمار از سمت بازدیدکننده.
 */
class Analytics_API {

	/** نام عمل nonce (در hodima-player.js هم). */
	public const NONCE_ACTION = 'hs_track_nonce';

	/** هوک‌ها. */
	public static function init(): void {
		add_action( 'wp_ajax_hs_track_view', [ self::class, 'track_view' ] );
		add_action( 'wp_ajax_nopriv_hs_track_view', [ self::class, 'track_view' ] );
		add_action( 'wp_ajax_hs_track_share', [ self::class, 'track_share' ] );
		add_action( 'wp_ajax_nopriv_hs_track_share', [ self::class, 'track_share' ] );
	}

	/**
	 * بررسی درخواست؛ شناسه استوری یا پایان با خطا.
	 *
	 * باگ قبلی: nonce داخل HTML صفحه است و لایت‌اسپید صفحه را بیشتر از
	 * عمر nonce (۱۲ تا ۲۴ ساعت) نگه می‌دارد؛ check_ajax_referer آن را رد
	 * می‌کرد و همه بازدیدهای صفحه کش‌شده بی‌صدا ثبت نمی‌شدند. حالا nonce
	 * کهنه با یک nonce تازه جواب می‌گیرد و اسکریپت یک بار دوباره می‌فرستد
	 * (همان راه ماژول ویدیوها). محافظت واقعی شمارنده عمومی، محدودیت نرخ و
	 * «هر بازدیدکننده روزی یک بار» است، نه nonce.
	 */
	private static function verify_request(): int {
		$nonce = isset( $_POST['security'] ) ? sanitize_text_field( wp_unslash( $_POST['security'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			nocache_headers();
			wp_send_json_error(
				[
					'code'     => 'nonce',
					'nonce'    => wp_create_nonce( self::NONCE_ACTION ),
					'recorded' => false,
				],
				403
			);
		}

		if ( ! Helpers::check_rate_limit() ) {
			wp_send_json_error(
				[
					'code'     => 'rate',
					'recorded' => false,
				],
				429
			);
		}

		$story_id = isset( $_POST['story_id'] ) ? absint( $_POST['story_id'] ) : 0;
		if ( $story_id <= 0 || ! class_exists( WP_Story_Analytics::class ) ) {
			wp_send_json_error(
				[
					'code'     => 'invalid',
					'recorded' => false,
				],
				400
			);
		}
		return $story_id;
	}

	/** ثبت بازدید. */
	public static function track_view(): void {
		$id = self::verify_request();
		wp_send_json_success( [ 'recorded' => WP_Story_Analytics::record_view( $id ) ] );
	}

	/** ثبت اشتراک. */
	public static function track_share(): void {
		$id = self::verify_request();
		wp_send_json_success( [ 'recorded' => WP_Story_Analytics::record_share( $id ) ] );
	}
}
