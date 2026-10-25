<?php
/**
 * ماژول «مشاور سئو» — بارگذار
 * Path: core/advisor/advisor-init.php
 *
 * با داده Search Console همین سایت برای هر صفحه می‌گوید چه کاری بیشترین کلیک
 * اضافه را می‌آورد (فقط پیشنهاد؛ چیزی را خودکار عوض نمی‌کند).
 *
 * مراحل ساخت (HODIMA-AUDIT.md بخش ۲۵۵ و ۲۵۶):
 *   ۱ اتصال مشترک (inc/search-console.php)   ← انجام شد
 *   ۳ داده: جدول‌ها و دریافت روزانه (این پوشه) ← این مرحله
 *   ۴ موتور قاعده‌ها، ۵ صفحه پیشخوان، ۶ کادر ویرایش، ۷ هشدار ← بعدا
 *
 * این ماژول به ماژول «گوگل دیسکاور» وابسته نیست؛ اتصال از inc/search-console.php
 * می‌آید و کلید همان «کلید جدای دیسکاور» یا سرویس اکانت Google Indexing است.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

require_once dirname( __DIR__, 2 ) . '/inc/search-console.php';
require_once __DIR__ . '/advisor-data.php';
require_once __DIR__ . '/advisor-refresh.php';
