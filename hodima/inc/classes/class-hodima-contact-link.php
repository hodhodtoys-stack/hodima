<?php
/**
 * یک کانال تماس پیکربندی‌شده (کانال + مقدار تنظیم) برای پنجره «پشتیبانی» هدر.
 * Path: hodima/inc/classes/class-hodima-contact-link.php
 *
 * قبلا آرایه ['key','url','label','aria']؛ حالا شیء تغییرناپذیر (readonly) که
 * آدرس و متن صفحه‌خوان را یک بار از enum Hodima_Contact_Channel می‌سازد.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

final readonly class Hodima_Contact_Link {

	public string $url;
	public string $aria;

	public function __construct(
		public Hodima_Contact_Channel $channel,
		string $value,
	) {
		$this->url  = $channel->url( $value );
		$this->aria = $channel->aria( $value );
	}
}
