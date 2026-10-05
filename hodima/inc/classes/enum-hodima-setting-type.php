<?php
/**
 * نوع فیلدهای «نمایش ← تنظیمات قالب هدیما».
 * Path: hodima/inc/classes/enum-hodima-setting-type.php
 *
 * قبلا نوع هر فیلد یک رشته آزاد بود و رفتارش در چند جای جدا تصمیم گرفته
 * می‌شد (match پاک‌سازی، in_array عرض کامل، match نوع input، in_array چپ‌به‌راست).
 * نوع اشتباه بی‌صدا مثل «text» رفتار می‌کرد. حالا enum: نوع ناشناخته همان‌جا
 * خطا می‌دهد و همه رفتار هر نوع کنار هم است. مقدارها همان رشته‌های قبلی‌اند
 * (کلید type آرایه فیلدها و کلاس CSS hodima-field--{value}).
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

enum Hodima_Setting_Type: string {

	case Text     = 'text';
	case Textarea = 'textarea';
	case Toggle   = 'toggle';
	case Image    = 'image';
	case Url      = 'url';
	case Tel      = 'tel';
	case Ga       = 'ga';
	case UrlList  = 'urllist';
	case HostList = 'hostlist';
	case Number   = 'number';
	case Select   = 'select';
	case Color    = 'color';

	/** فیلد در شبکه تنظیمات کل عرض را می‌گیرد (مگر داخل زیرگروه). */
	public function is_wide(): bool {
		return in_array( $this, [ self::Textarea, self::UrlList, self::HostList, self::Image ], true );
	}

	/** فهرست چندخطی آدرس/دامنه (textarea چپ‌به‌راست). */
	public function is_list(): bool {
		return self::UrlList === $this || self::HostList === $this;
	}

	/** نوع input برای فیلدهای تک‌خطی. */
	public function input_type(): string {
		return match ( $this ) {
			self::Url => 'url',
			self::Tel => 'tel',
			default   => 'text',
		};
	}

	/** مقدار لاتین (آدرس، تلفن، شناسه) در کادر چپ‌به‌راست. */
	public function is_ltr(): bool {
		return in_array( $this, [ self::Url, self::Tel, self::Ga ], true );
	}

	/**
	 * پاک‌سازی و اعتبارسنجی مقدار ارسالی. مقدار نامعتبر رد و به کاربر اطلاع
	 * داده می‌شود (توابع hodima_settings_sanitize_*).
	 *
	 * @param array<string, mixed> $field تعریف فیلد (min/max/options/default).
	 */
	public function sanitize( string $key, array $field, mixed $raw ): mixed {
		return match ( $this ) {
			self::Toggle   => ! empty( $raw ),
			self::Image    => hodima_settings_sanitize_image( $raw ),
			self::Url      => hodima_settings_sanitize_url( $key, $raw ),
			self::Tel      => hodima_settings_sanitize_phone( $key, $raw ),
			self::Ga       => hodima_settings_sanitize_ga( $raw ),
			self::UrlList  => hodima_settings_sanitize_url_list( $raw ),
			self::HostList => hodima_settings_sanitize_host_list( $key, $raw ),
			self::Textarea => sanitize_textarea_field( is_string( $raw ) ? $raw : '' ),
			// عدد در بازه min/max؛ ورودی خالی/نامعتبر = پیش‌فرض
			self::Number   => is_numeric( $raw ) ? min( (int) ( $field['max'] ?? PHP_INT_MAX ), max( (int) ( $field['min'] ?? 0 ), (int) $raw ) ) : (int) $field['default'],
			self::Select   => isset( $field['options'][ (string) ( is_scalar( $raw ) ? $raw : '' ) ] ) ? (string) $raw : (string) $field['default'],
			// #abc → #aabbcc (input type=color فقط شش رقمی می‌پذیرد)
			self::Color    => hodima_settings_sanitize_color( $raw, (string) $field['default'] ),
			self::Text     => sanitize_text_field( is_string( $raw ) ? $raw : '' ),
		};
	}
}
