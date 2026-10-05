<?php
/**
 * رنگ زمینه بخش‌های محصول صفحه اصلی (تب «صفحه اصلی» تنظیمات قالب).
 * Path: hodima/inc/classes/enum-hodima-home-tone.php
 *
 * همان چهار رنگ قبلی hodima_home_tones() (و اسلایدرهای شورت‌کدی). مقدار
 * (value) در گزینه hodima_home_layout ذخیره است و عوض نمی‌شود.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

enum Hodima_Home_Tone: string {

	case Primary   = 'primary';
	case Secondary = 'secondary';
	case Third     = 'third';
	case None      = 'none';

	public function label(): string {
		return match ( $this ) {
			self::Primary   => 'سرمه‌ای کم‌رنگ',
			self::Secondary => 'آبی کم‌رنگ',
			self::Third     => 'آبی روشن',
			self::None      => 'بدون رنگ',
		};
	}

	/** مقدار CSS زمینه (همان مقدار ثابت قبلی؛ در HTML ذخیره‌شده و کش صفحه هم آمده). */
	public function css(): string {
		return match ( $this ) {
			self::Primary   => 'rgba(37, 49, 106, 0.08)',
			self::Secondary => 'rgba(96, 123, 189, 0.1)',
			self::Third     => 'rgba(182, 194, 243, 0.25)',
			self::None      => 'transparent',
		};
	}
}
