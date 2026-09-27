<?php
/**
 * Main Loader: hodima Media System (Hook Structure)
 * File: media-init.php
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // جلوگیری از دسترسی مستقیم
}

// 1. بارگذاری توابع کمکی و هسته سیستم (آپدیت شده به ساختار جدید)
require_once __DIR__ . '/media-helpers.php';

// 2. بارگذاری رابط کاربری پنل مدیریت (متاباکس‌ها و ذخیره‌سازی اطلاعات)
if ( is_admin() ) {
    require_once __DIR__ . '/media-admin.php';
}

// 3. بارگذاری شورت‌کدهای هوشمند رسانه (ویدیو، پادکست، FAQ و معرفی)
require_once __DIR__ . '/media-shortcodes.php';

// 4. بارگذاری سیستم خودکار اسکیمای سئو (تولید JSON-LD بر اساس متادیتا)
require_once __DIR__ . '/media-schema.php';
