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
 *   ۳ داده: جدول‌ها و دریافت روزانه (advisor-data.php، advisor-refresh.php) ← انجام شد
 *   ۴ موتور قاعده‌ها (advisor-rules.php) ← انجام شد
 *   ۵ صفحه پیشخوان (advisor-admin.php، advisor-admin.css) ← انجام شد
 *   ۶ کادر داخل ویرایش (advisor-box.php، advisor-box.css) ← انجام شد
 *   ۷ هشدار ← بعدا
 *
 * این ماژول به ماژول «گوگل دیسکاور» وابسته نیست؛ اتصال از inc/search-console.php
 * می‌آید و کلید همان «کلید جدای دیسکاور» یا سرویس اکانت Google Indexing است.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

require_once dirname( __DIR__, 2 ) . '/inc/search-console.php';
require_once __DIR__ . '/advisor-data.php';
require_once __DIR__ . '/advisor-history.php';
require_once __DIR__ . '/advisor-competition.php';
require_once __DIR__ . '/advisor-rules.php';
require_once __DIR__ . '/advisor-steps.php';
require_once __DIR__ . '/advisor-refresh.php';

if ( is_admin() ) {
	require_once __DIR__ . '/advisor-steps-view.php';
	require_once __DIR__ . '/advisor-admin.php';
	require_once __DIR__ . '/advisor-competition-view.php';
	require_once __DIR__ . '/advisor-box.php';
}
