<?php
/**
 * Hodima Core — مدیریت ماژول‌های افزونه‌های هدیما
 * Path: plugins/hodima-core/includes/modules.php
 *
 * هر افزونه (SEO، Commerce، Media) ماژول‌هایش را اینجا ثبت می‌کند. مدیر
 * می‌تواند هر ماژول را از «هدیما ← ماژول‌ها» روشن یا خاموش کند؛ ماژول
 * خاموش اصلا بارگذاری نمی‌شود (نه منو، نه هوک، نه کوئری).
 *
 * داده‌های ماژول خاموش (جدول‌ها، تنظیمات، متاها) پاک نمی‌شوند و با روشن
 * کردن دوباره همه‌چیز برمی‌گردد.
 */

declare(strict_types=1);

namespace Hodima\Core;

defined( 'ABSPATH' ) || exit;

/**
 * تعریف یک ماژول.
 */
final class Module {

	/**
	 * آیا پیش‌نیازهای اجرای ماژول (مثل ووکامرس) فراهم است؟
	 * (property hook در PHP 8.4)
	 */
	public bool $available {
		get => ! $this->requires_wc || class_exists( 'WooCommerce' );
	}

	/**
	 * @param string       $id           شناسه یکتا در همان افزونه.
	 * @param string       $title        عنوان نمایشی.
	 * @param string       $description  توضیح کوتاه کارکرد.
	 * @param list<string> $files        فایل‌های لودر، نسبت به پوشه افزونه.
	 * @param bool         $requires_wc  بدون ووکامرس اجرا نمی‌شود.
	 * @param string       $settings     آدرس نسبی صفحه تنظیمات خود ماژول در پیشخوان.
	 * @param string       $warning      هشدار هنگام خاموش کردن (اثرات جدی روی سایت).
	 * @param list<string> $recommends   شناسه ماژول‌هایی که کنار این ماژول بهتر کار می‌کنند.
	 * @param string       $icon         Dashicon.
	 * @param bool         $default      وضعیت پیش‌فرض قبل از اولین ذخیره.
	 */
	public function __construct(
		public private(set) string $id,
		public private(set) string $title,
		public private(set) string $description,
		public private(set) array $files,
		public private(set) bool $requires_wc = false,
		public private(set) string $settings = '',
		public private(set) string $warning = '',
		public private(set) array $recommends = [],
		public private(set) string $icon = 'dashicons-admin-generic',
		public private(set) bool $default = true,
	) {}
}

/**
 * ثبت، وضعیت و بارگذاری ماژول‌ها.
 */
final class Modules {

	/** @var array<string, array{title:string, description:string, dir:string, version:string, file:string, modules:array<string, Module>}> */
	private static array $plugins = [];

	/** @var array<string, list<string>> ماژول‌هایی که واقعا بارگذاری شدند */
	private static array $loaded = [];

	public static function option_name( string $plugin ): string {
		return 'hodima_modules_' . $plugin;
	}

	/**
	 * ثبت یک افزونه و ماژول‌هایش.
	 *
	 * @param string $plugin      شناسه کوتاه (seo، commerce، media).
	 * @param string $file        فایل اصلی افزونه (برای لینک «تنظیمات» در فهرست افزونه‌ها).
	 */
	public static function register( string $plugin, string $title, string $description, string $file, string $version, Module ...$modules ): void {
		self::$plugins[ $plugin ] = [
			'title'       => $title,
			'description' => $description,
			'dir'         => dirname( $file ),
			'file'        => $file,
			'version'     => $version,
			'modules'     => array_column( array_map( static fn( Module $m ): array => [ $m->id, $m ], $modules ), 1, 0 ),
		];
	}

	/**
	 * افزونه‌های ثبت‌شده به ترتیب ثابت (سئو، فروشگاه، رسانه)، نه ترتیب بارگذاری.
	 *
	 * @return array<string, array{title:string, description:string, dir:string, version:string, file:string, modules:array<string, Module>}>
	 */
	public static function plugins(): array {
		$order   = [ 'seo' => 1, 'commerce' => 2, 'media' => 3 ];
		$plugins = self::$plugins;
		uksort( $plugins, static fn( string $a, string $b ): int => ( $order[ $a ] ?? 99 ) <=> ( $order[ $b ] ?? 99 ) );
		return $plugins;
	}

	/** وضعیت ذخیره‌شده (یا پیش‌فرض) یک ماژول. */
	public static function is_enabled( string $plugin, string $id ): bool {

		$module = self::$plugins[ $plugin ]['modules'][ $id ] ?? null;
		if ( ! $module ) {
			return false;
		}

		$stored = get_option( self::option_name( $plugin ), [] );

		return is_array( $stored ) && array_key_exists( $id, $stored )
			? (bool) $stored[ $id ]
			: $module->default;
	}

	/** آیا ماژول در این درخواست بارگذاری شده است؟ */
	public static function is_loaded( string $plugin, string $id ): bool {
		return in_array( $id, self::$loaded[ $plugin ] ?? [], true );
	}

	/** بارگذاری ماژول‌های روشن و قابل اجرا، به ترتیب ثبت. */
	public static function load( string $plugin ): void {

		$data = self::$plugins[ $plugin ] ?? null;
		if ( ! $data ) {
			return;
		}

		foreach ( $data['modules'] as $id => $module ) {

			if ( ! $module->available || ! self::is_enabled( $plugin, $id ) ) {
				continue;
			}

			foreach ( $module->files as $relative ) {
				$path = $data['dir'] . '/' . ltrim( $relative, '/' );
				if ( is_file( $path ) ) {
					require_once $path;
				}
			}

			self::$loaded[ $plugin ][] = $id;
		}

		/**
		 * بعد از بارگذاری ماژول‌های یک افزونه.
		 *
		 * @param list<string> $loaded شناسه ماژول‌های بارگذاری‌شده.
		 */
		do_action( "hodima_modules_loaded_{$plugin}", self::$loaded[ $plugin ] ?? [] );
	}

	/**
	 * پاک‌سازی مقدار ذخیره‌شده: فقط شناسه‌های شناخته‌شده، همه بولی.
	 *
	 * @param mixed $input
	 * @return array<string, bool>
	 */
	public static function sanitize( string $plugin, mixed $input ): array {

		$input = is_array( $input ) ? $input : [];
		$clean = [];

		foreach ( array_keys( self::$plugins[ $plugin ]['modules'] ?? [] ) as $id ) {
			$clean[ $id ] = ! empty( $input[ $id ] );
		}

		return $clean;
	}
}
