<?php
/**
 * خوشه موضوعی — تنظیمات («ابزارهای هدیما ← خوشه‌بندی ← تنظیمات»)
 * Path: core/topiccluster/includes/settings.php
 *
 * گزینه hodima_tc_settings (جدید در نسخه ۴). تا مدیر چیزی ذخیره نکند،
 * پیش‌فرض‌ها همان رفتار قبلی را نگه می‌دارند (کادر فقط با شورت‌کد)،
 * به‌جز «والد خودکار» و «هم‌خوشه‌ها» که پیشرفت اصلی این نسخه‌اند.
 */

declare(strict_types=1);

namespace Hodima\TopicCluster;

defined( 'ABSPATH' ) || exit;

final class Settings {

	public const OPTION   = 'hodima_tc_settings';
	public const HEADINGS = [ 'h2', 'h3', 'h4', 'p' ];
	public const LAYOUTS  = [ 'grid', 'list' ];

	/** جاهایی که نمایش خودکار دارند. */
	public const AUTO_PLACES = [
		'post'    => 'نوشته‌ها',
		'page'    => 'برگه‌ها',
		'product' => 'محصولات',
		'term'    => 'دسته‌ها و برچسب‌ها (انتهای توضیح)',
	];

	/** @var array<string, mixed>|null */
	private static ?array $settings = null;

	/** @return array<string, mixed> */
	public static function defaults(): array {
		return [
			'auto_parent'     => true,
			'auto_insert'     => array_fill_keys( array_keys( self::AUTO_PLACES ), false ),
			'paragraph'       => 0,
			'show_children'   => true,
			'show_siblings'   => true,
			'children_limit'  => 0,
			'siblings_limit'  => 6,
			'hide_noindex'    => false,
			'heading'         => 'p',
			'layout'          => 'grid',
			'label_parents'   => '',
			'label_children'  => 'زیرمجموعه‌ها',
			'label_siblings'  => 'مطالب هم‌خوشه',
			'stale_months'    => 12,
			'thin_words'      => 300,
		];
	}

	/** @return array<string, mixed> */
	public static function all(): array {

		if ( null !== self::$settings ) {
			return self::$settings;
		}

		$saved = get_option( self::OPTION, [] );

		return self::$settings = self::sanitize( is_array( $saved ) ? array_merge( self::defaults(), $saved ) : self::defaults() );
	}

	public static function get( string $key ): mixed {
		return self::all()[ $key ] ?? null;
	}

	public static function auto_insert( string $place ): bool {
		return ! empty( self::all()['auto_insert'][ $place ] );
	}

	/**
	 * @param array<string, mixed> $in
	 * @return array<string, mixed>
	 */
	public static function sanitize( array $in ): array {

		$d   = self::defaults();
		$out = [];

		foreach ( [ 'auto_parent', 'show_children', 'show_siblings', 'hide_noindex' ] as $key ) {
			$out[ $key ] = ! empty( $in[ $key ] );
		}

		$auto = (array) ( $in['auto_insert'] ?? [] );
		foreach ( array_keys( self::AUTO_PLACES ) as $place ) {
			$out['auto_insert'][ $place ] = ! empty( $auto[ $place ] );
		}

		$out['paragraph']      = min( 50, max( 0, (int) ( $in['paragraph'] ?? $d['paragraph'] ) ) );
		$out['children_limit'] = min( Graph::MAX_CHILDREN, max( 0, (int) ( $in['children_limit'] ?? $d['children_limit'] ) ) );
		$out['siblings_limit'] = min( 24, max( 1, (int) ( $in['siblings_limit'] ?? $d['siblings_limit'] ) ) );
		$out['stale_months']   = min( 60, max( 1, (int) ( $in['stale_months'] ?? $d['stale_months'] ) ) );
		$out['thin_words']     = min( 5000, max( 0, (int) ( $in['thin_words'] ?? $d['thin_words'] ) ) );

		$heading        = (string) ( $in['heading'] ?? $d['heading'] );
		$out['heading'] = in_array( $heading, self::HEADINGS, true ) ? $heading : $d['heading'];

		$layout        = (string) ( $in['layout'] ?? $d['layout'] );
		$out['layout'] = in_array( $layout, self::LAYOUTS, true ) ? $layout : $d['layout'];

		foreach ( [ 'label_parents', 'label_children', 'label_siblings' ] as $key ) {
			$out[ $key ] = sanitize_text_field( (string) ( $in[ $key ] ?? $d[ $key ] ) );
		}

		return $out;
	}

	/** @param array<string, mixed> $input */
	public static function save( array $input ): void {
		update_option( self::OPTION, self::sanitize( $input ), false );
		self::$settings = null;
		// والد خودکار و ترتیب نمایش روی گراف اثر دارند
		Graph::touch();
	}
}
