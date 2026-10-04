<?php
/**
 * Manual Related Links — پیشنهاد خرید و مقاله پیشنهادی
 * Path: plugins/hodima-seo/inc/manual_related_link/manual_related_link.php
 *
 * نسخه ۲: «ویترین پیشنهادی» تک‌شورت‌کدی (سه خانه آدرس متنی) به سه گروه با
 * شورت‌کد، تعداد و ظاهر جدا تبدیل شد؛ لینک‌ها با شناسه مقصد ذخیره می‌شوند و
 * داده نسخه ۱ بدون از دست رفتن منتقل می‌شود. هر شورت‌کد مستقل است و
 * [manual_related_products] خود کادر «محصولات مکمل» است (۲.۱). «دسته‌بندی‌های
 * مرتبط» در ۲.۲ حذف شد؛ ۲.۳: کادر ویرایشگر ساده (پیشنهاد خرید | مقاله در یک ردیف).
 * ۲.۴: عنوان سایت «پیشنهاد خرید»، خانه‌های هم‌اندازه، بدون تصویر دلخواه.
 * جزئیات: HODIMA-AUDIT.md بخش‌های ۳۱ تا ۳۳، ۴۴ و ۴۵.
 *
 *   includes/group.php       دو گروه (enum)
 *   includes/store.php       تنظیمات، ذخیره، مهاجرت، حل آدرس و مشکلات
 *   includes/front.php       شورت‌کدها، نمایش خودکار، توضیح دسته، relatedLink اسکیما
 *   includes/admin.php       کادر ویرایشگر، جستجوی زنده، ذخیره
 *   includes/admin-page.php  «ابزارهای هدیما ← لینک‌های مرتبط»: تنظیمات و گزارش سلامت
 *
 * @version 2.4.0
 */

declare(strict_types=1);

namespace Hodima\RelatedLinks;

defined( 'ABSPATH' ) || exit;

if ( ! defined( __NAMESPACE__ . '\VERSION' ) ) {

	define( __NAMESPACE__ . '\VERSION', '2.4.0' );

	require_once __DIR__ . '/includes/group.php';
	require_once __DIR__ . '/includes/store.php';
	require_once __DIR__ . '/includes/front.php';

	Front::init();

	if ( is_admin() ) {
		require_once __DIR__ . '/includes/admin.php';
		require_once __DIR__ . '/includes/admin-page.php';
		Admin::init();
		AdminPage::init();
	}
}
