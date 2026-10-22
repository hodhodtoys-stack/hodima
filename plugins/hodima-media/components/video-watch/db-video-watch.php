<?php
/**
 * آمار ویدیوها (بازدید و اشتراک) — صف سبک + جمع‌بندی دسته‌ای
 * Path: hodima-media/components/video-watch/db-video-watch.php
 *
 * هر تعامل اول در جدول صف (یک ردیف برای هر ویدیو و نوع) جمع می‌شود و هر ۵
 * دقیقه یک بار به جدول آمار اصلی منتقل می‌شود تا در ترافیک بالا هر بازدید یک
 * نوشتن سنگین روی جدول اصلی نباشد.
 *
 * زیر PHPCS/PHPStan بدون baseline (HODIMA-AUDIT.md بخش ۲۱۶). نام جدول‌ها،
 * گزینه video_watch_db_version، رویداد cron و مسیرهای REST عوض نشوند.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

foreach ( [ 'counter', 'setup', 'analytics', 'rest' ] as $hodima_vw_part ) {
	require_once __DIR__ . '/includes/class-video-watch-' . $hodima_vw_part . '.php';
}
unset( $hodima_vw_part );

Hodima_Video_Watch_Setup::init();
Hodima_Video_Watch_Rest::init();
