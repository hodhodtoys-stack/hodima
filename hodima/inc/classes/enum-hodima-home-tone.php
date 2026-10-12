<?php
/**
 * رنگ زمینه بخش‌های محصول صفحه اصلی (تب «صفحه اصلی» تنظیمات قالب).
 * Path: hodima/inc/classes/enum-hodima-home-tone.php
 *
 * همان چهار رنگ قبلی hodima_home_tones() (و اسلایدرهای شورت‌کدی). مقدار
 * (value) در گزینه hodima_home_layout ذخیره است و عوض نمی‌شود.
 *
 * از 3.1.3 رنگ از پالت برند می‌آید (rgba(var(--hodima-…-rgb))): با عوض کردن پالت در
 * «تنظیمات قالب ← برند» زمینه بخش‌ها هم عوض می‌شود. تا 3.1.2 اعداد پالت پیش‌فرض ثابت بود.
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

	/** مقدار CSS زمینه (با پالت برند؛ با پالت پیش‌فرض همان اعداد قبلی). */
	public function css(): string {
		return match ( $this ) {
			self::Primary   => 'rgba(var(--hodima-primary-rgb, 37, 49, 106), 0.08)',
			self::Secondary => 'rgba(var(--hodima-secondary-rgb, 96, 123, 189), 0.1)',
			self::Third     => 'rgba(var(--hodima-third-rgb, 182, 194, 243), 0.25)',
			self::None      => 'transparent',
		};
	}

	/**
	 * مقدار ثابت قدیمی: فقط برای شناختن bg_color شورت‌کدهای قدیمی متن برگه هنگام
	 * خواندن چیدمان (hodima_home_layout_from_content)؛ به خروجی صفحه ربطی ندارد.
	 */
	public function legacy_css(): string {
		return match ( $this ) {
			self::Primary   => 'rgba(37, 49, 106, 0.08)',
			self::Secondary => 'rgba(96, 123, 189, 0.1)',
			self::Third     => 'rgba(182, 194, 243, 0.25)',
			self::None      => 'transparent',
		};
	}
}
