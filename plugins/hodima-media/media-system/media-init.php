<?php
/**
 * Main Loader: Hodima Media System
 * Path: media-system/media-init.php
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

// ۱. داده، تبدیل‌ها و قرارداد عمومی
require_once __DIR__ . '/media-helpers.php';

// ۲. ویدیوی یکسان‌شده و VideoObject واحد (پخش‌کننده، اسکیما، سایت‌مپ)
require_once __DIR__ . '/media-video.php';

// ۲.۱ ثبت متاها (REST، نسخه‌های ذخیره‌شده)
require_once __DIR__ . '/media-rest.php';

// ۳. پیشخوان: کادر «تنظیمات رسانه» و ذخیره (+ اطلاعات خودکار از آپارات/یوتیوب/ویمئو)
if ( is_admin() ) {
	require_once __DIR__ . '/media-providers.php';
	require_once __DIR__ . '/media-admin.php';
}

// ۴. شورت‌کدها (ویدیو، پادکست، FAQ، معرفی)
require_once __DIR__ . '/media-shortcodes.php';

// ۵. اسکیما (JSON-LD) در گراف واحد صفحه
require_once __DIR__ . '/media-schema.php';

// Google Discover از نسخه 1.5.0 ماژول مستقل افزونه سئو است (core/discover).

// ۶. نام‌های قدیمی hook_* (فقط اگر تعریف نشده باشند)
require_once __DIR__ . '/media-legacy.php';
