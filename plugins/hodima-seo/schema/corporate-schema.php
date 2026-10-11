<?php
/**
 * اسکیمای برگه‌های درباره‌ما (AboutPage) و تماس (ContactPage)
 * Path: plugins/hodima-seo/schema/corporate-schema.php
 *
 * سازمان همان نود صفحه‌های دیگر است (schema-helpers.php؛ گزینه‌های
 * hodima_schema_homepage_* و hodima_schema_geo_*) — قبلا این فایل نسخه دوم
 * و ناقص‌تری از #organization می‌ساخت. تنها گزینه‌های خودش نامک دو برگه‌اند
 * (hodima_corp_about_slug / hodima_corp_contact_slug، در همان صفحه تنظیمات).
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

add_action( 'wp_head', 'hodima_seo_render_corporate_schema' );

function hodima_seo_render_corporate_schema(): void {

	// همان سوییچ «سیستم یکپارچه گراف دانش»؛ سوییچ جداگانه‌ای ندارد
	if ( '1' !== get_option( 'hodima_schema_homepage_enable', '1' ) ) {
		return;
	}

	$is_about   = is_page( get_option( 'hodima_corp_about_slug', 'about-us' ) );
	$is_contact = is_page( get_option( 'hodima_corp_contact_slug', 'contact-us' ) );

	if ( ! $is_about && ! $is_contact ) {
		return;
	}

	$site_url = trailingslashit( home_url() );

	// شناسه «#webpage» از موتور canonical مشترک، دقیقا مثل homepage-schema.php (بدون trailingslashit)
	$page_url = function_exists( 'hodima_get_canonical_url' ) && '' !== hodima_get_canonical_url()
		? hodima_get_canonical_url()
		: get_permalink();

	// پیش از فیلتر صفحه ساخته می‌شود (ترتیب قبلی اجرای فیلترها)
	$organization_node = hodima_seo_schema_organization_node();

	$webpage_node = [
		'@type'      => $is_about ? 'AboutPage' : 'ContactPage',
		'@id'        => $page_url . '#webpage',
		'url'        => $page_url,
		'name'       => get_the_title(),
		'isPartOf'   => [ '@id' => $site_url . '#website' ],
		'about'      => [ '@id' => $site_url . '#organization' ],
		'inLanguage' => 'fa-IR',
	];

	// توضیح خالی ("") ویژگی بی‌معنا است
	$excerpt = wp_strip_all_tags( (string) get_the_excerpt() );
	if ( '' !== $excerpt ) {
		$webpage_node['description'] = $excerpt;
	}

	// همان نقطه غنی‌سازی homepage-schema.php (بردکرامب، خوشه موضوعی، تاریخ‌ها)
	$webpage_node = (array) apply_filters( 'hodima_schema_webpage_node', $webpage_node, $page_url );
	hodima_schema_webpage_emitted( true );

	hodima_schema_add( [ '@graph' => [ $organization_node, $webpage_node ] ], 'hodima-seo: corporate-schema' );
}
