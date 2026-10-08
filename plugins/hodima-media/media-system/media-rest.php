<?php
/**
 * Media System — ثبت متاها در وردپرس (REST و نسخه‌های ذخیره‌شده)
 * Path: media-system/media-rest.php
 *
 * تا نسخه 1.7 متاهای رسانه فقط با کادر پیشخوان خوانده و نوشته می‌شدند:
 * REST (ویرایشگر بلوکی، اپ موبایل وردپرس، ابزارهای درون‌ریزی و هوش مصنوعی)
 * آن‌ها را نمی‌دید و با «بازگردانی نسخه» نوشته، رسانه‌اش برنمی‌گشت.
 * حالا همه کلیدهای hodima_media_meta_keys() با نوع و دسترسی ثبت می‌شوند:
 *   - show_in_rest با schema (آرایه‌ها: FAQ، ویدیوهای بیشتر، بخش‌های پنهان)
 *   - دسترسی: همان ویرایش نوشته/ترم (کلیدها با «_» محافظت‌شده‌اند)
 *   - revisions_enabled برای نوع‌هایی که نسخه دارند (وردپرس 6.4+)
 * نام کلیدها عوض نشده (قانون ۵).
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * نوع و schema هر کلید برای register_meta.
 *
 * @return array{type: string, show_in_rest: bool|array<string, mixed>}
 */
function hodima_media_meta_schema( string $key ): array {

	$string_list = [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ];

	return match ( $key ) {
		'video_cover_id' => [ 'type' => 'integer', 'show_in_rest' => true ],
		'faq'            => [ 'type' => 'array', 'show_in_rest' => [ 'schema' => [
			'type'  => 'array',
			'items' => [ 'type' => 'object', 'properties' => [ 'q' => [ 'type' => 'string' ], 'a' => [ 'type' => 'string' ] ], 'additionalProperties' => false ],
		] ] ],
		'video_extra'    => [ 'type' => 'array', 'show_in_rest' => [ 'schema' => [
			'type'  => 'array',
			'items' => [
				'type'                 => 'object',
				'properties'           => [
					'url'      => [ 'type' => 'string' ],
					'title'    => [ 'type' => 'string' ],
					'duration' => [ 'type' => 'string' ],
					'cover'    => [ 'type' => 'string' ],
					'cover_id' => [ 'type' => 'integer' ],
					'date'     => [ 'type' => 'string' ],
				],
				'additionalProperties' => false,
			],
		] ] ],
		'hidden_parts'   => [ 'type' => 'array', 'show_in_rest' => [ 'schema' => $string_list ] ],
		default          => [ 'type' => 'string', 'show_in_rest' => true ],
	};
}

add_action( 'init', static function (): void {

	// کلیدهای خیلی قدیمی بی‌پیشوند ثبت نمی‌شوند (ممکن است مال افزونه دیگری باشند)
	$keys = hodima_media_meta_keys();

	foreach ( hodima_media_post_types() as $post_type ) {

		if ( ! post_type_exists( $post_type ) ) {
			continue;
		}

		$revisions = post_type_supports( $post_type, 'revisions' );

		foreach ( $keys as $key ) {
			register_post_meta( $post_type, hodima_media_meta_prefix( 'post' ) . $key, hodima_media_meta_schema( $key ) + [
				'single'            => true,
				'revisions_enabled' => $revisions,
				'auth_callback'     => static fn( bool $allowed, string $meta_key, int $post_id ): bool => current_user_can( 'edit_post', $post_id ),
			] );
		}
	}

	foreach ( hodima_media_taxonomies() as $taxonomy ) {

		if ( ! taxonomy_exists( $taxonomy ) ) {
			continue;
		}

		foreach ( $keys as $key ) {
			register_term_meta( $taxonomy, hodima_media_meta_prefix( 'term' ) . $key, hodima_media_meta_schema( $key ) + [
				'single'        => true,
				'auth_callback' => static fn( bool $allowed, string $meta_key, int $term_id ): bool => current_user_can( 'edit_term', $term_id ),
			] );
		}
	}
}, 20 );
