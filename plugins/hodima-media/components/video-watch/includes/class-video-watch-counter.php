<?php
/**
 * نوع شمارنده آمار ویدیو (بارگذار: ../db-video-watch.php)
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * نوع شمارنده قابل‌ثبت برای هر ویدیو (مقدار = نام ستون جدول آمار).
 */
enum Hodima_Video_Watch_Counter: string {
	case Views  = 'views';
	case Shares = 'shares';
}
