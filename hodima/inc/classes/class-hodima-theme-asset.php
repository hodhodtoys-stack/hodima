<?php
/**
 * یک فایل CSS یا JS قالب برای بارگذاری در صفحه.
 * Path: hodima/inc/classes/class-hodima-theme-asset.php
 *
 * قبلا هر جای قالب فایلش را با روش خودش ثبت می‌کرد: hodima_asset_version()
 * در enqueue.php، filemtime مستقیم (با بررسی جدا) در inc/header.php،
 * inc/footer.php و home/logic.php، و get_template_directory_uri() یا hodima_URI
 * به‌جای هم. حالا یک راه: hodima_enqueue_asset( handle، مسیر نسبی ).
 *
 * PHP 8.4: مسیر، آدرس، نسخه و نوع فایل «property hook» مجازی‌اند (فقط get؛
 * هیچ‌جا ذخیره نمی‌شوند و همیشه از مسیر نسبی ساخته می‌شوند).
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

final class Hodima_Theme_Asset {

	/**
	 * @param string                    $handle نام ثبت در وردپرس.
	 * @param string                    $path   مسیر نسبی از پوشه قالب، مثلا assets/css/header.css.
	 * @param list<string>              $deps   وابستگی‌ها.
	 * @param array<string, string|bool> $args  اسکریپت: in_footer/strategy؛ استایل: media.
	 */
	public function __construct(
		public readonly string $handle,
		public readonly string $path,
		public readonly array $deps = [],
		public readonly array $args = [],
	) {}

	/** مسیر کامل فایل روی دیسک. */
	public string $file {
		get => hodima_DIR . '/' . ltrim( $this->path, '/' );
	}

	/** آدرس فایل در سایت. */
	public string $url {
		get => hodima_URI . '/' . ltrim( $this->path, '/' );
	}

	/** نسخه = زمان تغییر فایل (تغییر CSS/JS فورا در کش مرورگرها دیده می‌شود). */
	public string $version {
		get => hodima_asset_version( $this->path );
	}

	public bool $is_script {
		get => str_ends_with( $this->path, '.js' );
	}

	public bool $exists {
		get => is_file( $this->file );
	}

	/** در صف بارگذاری؛ فایل ناموجود (مثلا حذف‌شده در نسخه بعد) بی‌صدا رد می‌شود. */
	public function enqueue(): bool {

		if ( ! $this->exists ) {
			return false;
		}

		if ( $this->is_script ) {
			wp_enqueue_script( $this->handle, $this->url, $this->deps, $this->version, $this->args );
		} else {
			wp_enqueue_style( $this->handle, $this->url, $this->deps, $this->version, (string) ( $this->args['media'] ?? 'all' ) );
		}

		return true;
	}
}
