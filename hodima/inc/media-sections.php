<?php
/**
 * بخش‌های «رسانه» (افزونه Hodima Media) در قالب‌ها
 * Path: hodima/inc/media-sections.php
 *
 * نوسازی قالب، مرحله ۷ (HODIMA-AUDIT.md بخش ۶۴). قبلا هر پنج قالب
 * (مقاله، برگه، دسته محصول، محصول، صفحه اصلی) شورت‌کدهای hook_intro،
 * hook_video، hook_voice و hook_faq را هر کدام به روش خودش می‌خواند
 * (do_shortcode خام، با/بدون trim، با/بدون shortcode_exists) و دو بخش
 * «معرفی + ویدیو» و «پادکست + سوالات متداول» در سه قالب جدا تکرار شده بود.
 * حالا:
 *   - خواندن: فقط hodima_theme_media_html() (بدون افزونه رشته خالی، نه متن خام شورت‌کد)؛
 *   - نمایش: template-parts/media/intro-media.php و voice-faq.php
 *     (چیدمان هر قالب با کلید layout؛ همان کلاس‌ها و HTML قبلی).
 * پیشوند hodima_theme_media_ (نه hodima_media_): آن پیشوند مال توابع افزونه است.
 * نام شورت‌کدها مال افزونه است و عوض نمی‌شود.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * بخش رسانه‌ای که افزونه (با همین نام شورت‌کد) ارائه می‌دهد.
 *
 * @param string $part intro | video | voice | faq
 */
function hodima_theme_media_available( string $part ): bool {
	return shortcode_exists( 'hook_' . $part );
}

/**
 * HTML یک بخش رسانه (trim‌شده)؛ بدون افزونه یا بدون محتوا رشته خالی.
 *
 * @param string                $part intro | video | voice | faq
 * @param array<string, scalar> $atts مثلا [ 'id' => …, 'context' => 'post' ]
 */
function hodima_theme_media_html( string $part, array $atts = [] ): string {
	return hodima_shortcode( 'hook_' . $part, $atts );
}
