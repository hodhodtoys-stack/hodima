<?php
/**
 * صفحه اصلی: بارگذاری فایل‌های منطق (کارت، اسلایدر، شورت‌کد قدیمی، کش، دارایی‌ها)
 * Path: hodima/home/logic.php
 *
 * تا 3.1.4 همه‌چیز در همین فایل بود (۶۷۰ خط). حالا هر فایل یک کار دارد؛ نام توابع و هوک‌ها
 * عوض نشد. جایی که چیزی را می‌خواهید پیدا کنید:
 *
 *   cards.php      کارت و باکس مشترک بخش‌های افقی، اولویت بارگذاری تصویر بالای صفحه (LCP)
 *   slider.php     اسلایدر محصولات (کش) و داده کش‌شده «دسته‌ها» و «آخرین مقالات»
 *   shortcodes.php شورت‌کدهای قدیمی ([section03/07/09/10]، [latest-products]، یکی برای هر دسته)
 *   cache.php      نسل کش اسلایدرها، پاک‌سازی هنگام تغییر محصول/دسته/مقاله، پاک‌سازی کش صفحه
 *   assets.php     CSS و JS صفحه اصلی
 *
 * اسکیمای صفحه اصلی (فهرست دسته‌ها، تصاویر، ویدیوی hero) در افزونه Hodima SEO است:
 * schema/front-page-extra-schema.php — «اسکیما ← صفحه اصلی».
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

foreach ( [ 'cards', 'slider', 'shortcodes', 'cache', 'assets' ] as $hodima_home_part ) {
	require_once __DIR__ . '/' . $hodima_home_part . '.php';
}

unset( $hodima_home_part );
