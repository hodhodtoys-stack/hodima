<?php
/**
 * «متن معرفی» صفحه (سیستم رسانه) برای توضیح صفحه در اسکیما و AEO
 * Path: inc/page-intro.php
 *
 * متن معرفی ([hook_intro]، کادر «تنظیمات رسانه») پاراگرافی است که مدیر برای
 * معرفی همان صفحه نوشته و در خود صفحه دیده می‌شود. وقتی صفحه خلاصه (چکیده)
 * ندارد، همین متن توضیح صفحه در اطلاعات ساختاریافته (WebPage، BlogPosting،
 * CollectionPage) و فایل‌های ماشین‌خوان (AEO / llms.txt) می‌شود؛ قبلا
 * ابتدای خام متن یا یک جمله قالبی می‌رفت.
 *
 * فقط وقتی متن واقعا در صفحه نمایش داده می‌شود: سیستم رسانه و بخش «متن
 * معرفی» روشن، نوشته رمزدار نه، و قالب آن را نشان می‌دهد (محصول ندارد؛
 * دسته وبلاگ ندارد). همیشه لود می‌شود (hodima-seo.php)؛ بدون Hodima Media خالی.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * متن ساده معرفی صفحه، یا رشته خالی.
 *
 * @param int    $words حداکثر تعداد کلمه (۰ = همه)
 */
function hodima_seo_page_intro_text( int $object_id, string $context = 'post', int $words = 0 ): string {

	if ( $object_id <= 0 || ! function_exists( 'hodima_media_get_data' ) ) {
		return '';
	}

	$context = 'term' === $context ? 'term' : 'post';

	// محصول: قالب «متن معرفی» را نشان نمی‌دهد (توضیحات خود محصول جایش است)
	if ( 'post' === $context && ( 'product' === get_post_type( $object_id ) || post_password_required( $object_id ) ) ) {
		return '';
	}

	if ( function_exists( 'hodima_media_is_displayed' ) && ! hodima_media_is_displayed( $object_id, $context ) ) {
		return '';
	}

	$data  = hodima_media_get_data( $object_id, $context );
	$shown = function_exists( 'hodima_media_part_shown' )
		? hodima_media_part_shown( $data, 'intro' )
		: 'yes' === ( $data['enabled'] ?? '' );

	if ( ! $shown ) {
		return '';
	}

	$text = trim( (string) preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( strip_shortcodes( (string) ( $data['content'] ?? '' ) ) ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );

	return ( $words > 0 && '' !== $text ) ? wp_trim_words( $text, $words, '…' ) : $text;
}
