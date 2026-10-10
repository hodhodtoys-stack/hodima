<?php
/**
 * Plugin Name:       Hodima SEO
 * Plugin URI:        https://hodima.com
 * Description:       سئوی فنی هدیما: متاباکس سئو، اسکیمای JSON-LD، سایت‌مپ XML، robots.txt، ریدایرکت‌ها، آدرس تمیز بدون پایه، خوشه‌های موضوعی، لینک‌سازی داخلی، IndexNow، Google Indexing API، گوگل دیسکاور و نسخه‌های ماشین‌خوان (llms.txt).
 * Version:           2.1.7
 * Requires at least: 6.5
 * Requires PHP:      8.4
 * Requires Plugins:  hodima-core
 * Author:            آرین فتحی
 * Author URI:        https://hodima.com
 * License:           GPL-2.0-or-later
 * Text Domain:       hodima-seo
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

const HODIMA_SEO_VERSION = '2.1.7';
define( 'HODIMA_SEO_FILE', __FILE__ );
define( 'HODIMA_SEO_DIR', __DIR__ );
define( 'HODIMA_SEO_URL', untrailingslashit( plugin_dir_url( __FILE__ ) ) );

/**
 * ماژول‌های افزونه (به ترتیب بارگذاری functions.php قالب قبلی).
 * کلیدهای هر آرایه همان پارامترهای Hodima\Core\Module هستند.
 *
 * @return array<string, array<string, mixed>>
 */
function hodima_seo_modules(): array {

	$schema_admin = 'schema/admin/init.php'; // پنل «پیشخوان هدیما» مشترک اسکیما، سایت‌مپ و پادکست

	return [
		'google-indexing' => [
			'title'       => 'Google Indexing API',
			'description' => 'ارسال خودکار صفحات جدید و تغییرکرده به گوگل با صف و سهمیه روزانه، سایت‌مپ خوشه‌ها، ETag و هرس آدرس‌های زباله (replytocom، پیوست‌ها، فیدها).',
			'files'       => [ 'components/google-indexing-api/main.php' ],
			'settings'    => 'admin.php?page=hodima-google',
			'recommends'  => [ 'topic-cluster' ],
			'icon'        => 'dashicons-google',
		],
		'indexnow' => [
			'title'       => 'IndexNow، AEO و ربات‌گیر',
			'description' => 'ارسال آدرس‌ها به بینگ و Yandex، نسخه‌های ماشین‌خوان (llms.txt، Markdown، ai-feed) و کنترل و محدودسازی ربات‌های هوش مصنوعی.',
			'files'       => [ 'components/indexnow-sync/indexnow-sync.php' ],
			'settings'    => 'admin.php?page=hodima-core',
			'recommends'  => [ 'router', 'topic-cluster' ],
			'icon'        => 'dashicons-shield',
		],
		'schema' => [
			'title'       => 'اسکیما (JSON-LD)',
			'description' => 'داده ساختاریافته صفحه اصلی، سازمان، محصول، دسته، بلاگ، برگه، تصاویر و بردکرامب، به‌همراه حذف اسکیمای تکراری.',
			'files'       => [
				'schema/schema-helpers.php', // ابزارهای مشترک (لوگو، سازمان، تاریخ ویدیو) — پیش از بقیه
				'schema/blog-schema.php',
				'schema/breadcrumb-schema.php',
				'schema/category-schema-pro.php',
				'schema/corporate-schema.php',
				'schema/homepage-schema.php',
				'schema/imageobject-schema.php',
				'schema/page-schema-pro.php',
				'schema/product-schema-pro.php',
				'schema/schema-cleaner.php',
				'schema/front-page-extra-schema.php',   // از قالب (home/logic.php) — بازسازی قالب، مرحله ۲
				'schema/collection-lists-schema.php',   // از قالب (فروشگاه/برچسب و آرشیو وبلاگ)
				$schema_admin,
			],
			'settings'    => 'admin.php?page=hodima-schema',
			'warning'     => 'داده‌های ساختاریافته همه صفحات حذف می‌شوند و نتایج غنی (قیمت، امتیاز، بردکرامب) در گوگل از بین می‌رود.',
			'recommends'  => [ 'seobox' ],
			'icon'        => 'dashicons-editor-code',
		],
		'podcast' => [
			'title'       => 'فید پادکست',
			'description' => 'فید RSS پادکست در /feed/podcast/ از فایل‌های صوتی نوشته‌ها، محصولات و دسته‌ها (سیستم رسانه).',
			'files'       => [ 'schema/podcast-feed-core.php', $schema_admin ],
			'settings'    => 'admin.php?page=hodima-podcast',
			'icon'        => 'dashicons-microphone',
		],
		'sitemap' => [
			'title'       => 'نقشه سایت XML',
			'description' => 'sitemap.xml با تصاویر و ویدیوها، lastmod واقعی و حذف صفحات noindex. سایت‌مپ داخلی وردپرس را خاموش می‌کند.',
			'files'       => [ 'schema/sitemap-core.php', $schema_admin ],
			'settings'    => 'admin.php?page=hodima-sitemap',
			'warning'     => 'آدرس sitemap.xml از کار می‌افتد و سایت‌مپ داخلی وردپرس (wp-sitemap.xml) دوباره فعال می‌شود؛ سایت‌مپ ثبت‌شده در سرچ کنسول را به‌روز کنید.',
			'icon'        => 'dashicons-networking',
		],
		'manual-links' => [
			'title'       => 'لینک‌های مرتبط دستی',
			'description' => 'محصولات مکمل و مقاله پیشنهادی با جستجوی زنده در ویرایشگر، نمایش خودکار یا شورت‌کد، relatedLink در اسکیما و گزارش سلامت لینک‌ها.',
			'files'       => [ 'inc/manual_related_link/manual_related_link.php' ],
			'settings'    => 'admin.php?page=hodima-related-links',
			'icon'        => 'dashicons-admin-links',
		],
		'router' => [
			'title'       => 'آدرس تمیز (بدون پایه)',
			'description' => 'حذف /product/ و /product-category/ و /category/ از آدرس‌ها؛ هر صفحه یک آدرس دارد و شکل‌های قدیمی و تکراری با ۳۰۱ به آن می‌روند. جلوگیری از نامک تکراری بین محصول، نوشته، برگه و دسته‌ها و گزارش تداخل‌های موجود.',
			'files'       => [ 'core/router/router.php' ],
			'settings'    => 'admin.php?page=hodima-router',
			'warning'     => 'آدرس همه محصولات و دسته‌ها به حالت پیش‌فرض (با /product/ و /product-category/) برمی‌گردد؛ لینک‌های فعلی و ایندکس‌شده گوگل ۴۰۴ می‌شوند.',
			'icon'        => 'dashicons-admin-site-alt3',
		],
		'redirects' => [
			'title'       => 'ریدایرکت‌ها',
			'description' => 'مدیریت ریدایرکت‌های ۳۰۱/۳۰۲/۴۱۰ و ثبت خودکار ریدایرکت هنگام تغییر نامک.',
			'files'       => [ 'core/redirects/init.php' ],
			'settings'    => 'admin.php?page=hodima-redirects',
			'warning'     => 'همه ریدایرکت‌های تعریف‌شده غیرفعال می‌شوند و آدرس‌های قدیمی ۴۰۴ می‌دهند.',
			'icon'        => 'dashicons-randomize',
		],
		'seobox' => [
			'title'       => 'متاباکس سئو',
			'description' => 'عنوان، توضیحات، canonical، robots و تگ‌های Open Graph و Twitter برای نوشته‌ها، برگه‌ها، محصولات، دسته‌ها و برچسب‌ها، با پیش‌نمایش گوگل و فیلتر Index/Noindex فهرست‌ها.',
			'files'       => [ 'core/seobox/seobox-init.php' ],
			'warning'     => 'عنوان سفارشی، توضیحات متا، canonical و تگ‌های شبکه‌های اجتماعی همه صفحات حذف می‌شوند.',
			'icon'        => 'dashicons-search',
		],
		'discover' => [
			'title'       => 'گوگل دیسکاور',
			'description' => 'عنوان و تصویر دیسکاور نوشته‌ها، برگه‌ها، محصولات و دسته‌های محصول، سه برش ۱۶:۹ / ۴:۳ / ۱:۱ (عرض ۱۲۰۰)، og:image و og:title بزرگ، فید RSS نوشته‌ها و محصولات با تصویر برای «دنبال کردن»، موضوعات با ویکی‌داده و معرفی نویسنده در اسکیما، گزارش آمادگی همه صفحه‌ها با ساخت برش برای همه، فرصت‌های بهتر شدن، و آمار واقعی دیسکاور از سرچ کنسول با نمودار روزانه، مقایسه ۲۸ روز قبل، تاریخچه بلندمدت ماه به ماه، نقطه تمرکز برش‌ها و ثبت اثر تغییر عنوان و تصویر کارت.',
			'files'       => [ 'core/discover/discover-init.php' ],
			'settings'    => 'admin.php?page=hodima-discover',
			'warning'     => 'کادر گوگل دیسکاور از ویرایش نوشته برداشته می‌شود و عنوان/تصویر دیسکاور، برش‌های تصویر و تصویر فید دیگر اعمال نمی‌شوند (اطلاعات پاک نمی‌شود).',
			'recommends'  => [ 'seobox', 'schema' ],
			'icon'        => 'dashicons-visibility',
		],
		'robots' => [
			'title'       => 'robots.txt هوشمند',
			'description' => 'ساخت کامل robots.txt هماهنگ با ووکامرس، سایت‌مپ و تنظیمات ربات‌گیر (هر گروه ربات قوانین پایه را هم دارد).',
			'files'       => [ 'core/seobox/robots-txt.php' ],
			'recommends'  => [ 'indexnow', 'sitemap' ],
			'icon'        => 'dashicons-privacy',
		],
		'topic-cluster' => [
			'title'       => 'خوشه‌های موضوعی',
			'description' => 'صفحات ستون (Pillar) و زیرمجموعه‌ها با والد خودکار از دسته اصلی، کادر خوشه با مطالب هم‌خوشه، نقشه درختی، محتوای یتیم، فهرست لینک‌های داخلی متن و گزارش سلامت خوشه‌ها.',
			'files'       => [ 'core/topiccluster/topiccluster-init.php' ],
			'settings'    => 'admin.php?page=hodima-tc-orphans',
			'icon'        => 'dashicons-networking',
		],
		'cat-blog' => [
			'title'       => 'دسته‌های وبلاگ و محصول',
			'description' => 'ویرایشگر پیشرفته توضیحات (HTML، تصویر، تیتر) برای دسته نوشته‌ها و دسته محصولات، و تصویر شاخص دسته نوشته‌ها.',
			'files'       => [ 'core/cat-blog/cat-blog.php' ],
			'settings'    => 'edit-tags.php?taxonomy=category',
			'icon'        => 'dashicons-category',
		],
	];
}

/*
 * ماژول‌ها در plugins_loaded لود می‌شوند، نه هنگام include این فایل:
 * افزونه‌ها به ترتیب الفبا لود می‌شوند و Hodima Core (پیش‌نیاز) و ووکامرس
 * ممکن است هنوز لود نشده باشند.
 */
add_action( 'plugins_loaded', static function (): void {

	if ( function_exists( 'hodima_legacy_theme_active' ) && hodima_legacy_theme_active() ) {
		hodima_legacy_theme_notice( 'Hodima SEO' );
		return;
	}

	// بدون Hodima Core، اسکیما مثل قبل در تگ جداگانه چاپ می‌شود
	if ( ! function_exists( 'hodima_schema_add' ) ) {
		require_once __DIR__ . '/inc/schema-fallback.php';
	}

	// قیمت محصول برای اسکیما، Open Graph و AEO (گزینه «قیمت تک / حداقل سفارش» تنظیمات قالب)
	require_once __DIR__ . '/inc/product-price.php';

	// «متن معرفی» سیستم رسانه به‌عنوان توضیح صفحه (اسکیما، AEO) وقتی صفحه خلاصه ندارد
	require_once __DIR__ . '/inc/page-intro.php';

	// بدون Hodima Core، هدر/تب مشترک پیشخوان نسخه ساده می‌گیرد
	if ( is_admin() && ! function_exists( 'hodima_admin_header' ) ) {
		require_once __DIR__ . '/inc/admin-ui-fallback.php';
	}

	$specs = hodima_seo_modules();

	// مسیر عادی: ثبت در Hodima Core و بارگذاری ماژول‌های روشن
	if ( class_exists( \Hodima\Core\Modules::class ) ) {
		\Hodima\Core\Modules::register(
			'seo',
			'سئو',
			'متاباکس سئو، اسکیما، سایت‌مپ، robots.txt، ریدایرکت‌ها، آدرس تمیز، خوشه‌های موضوعی، گوگل دیسکاور، IndexNow و Google Indexing.',
			HODIMA_SEO_FILE,
			HODIMA_SEO_VERSION,
			...array_map(
				static fn( string $id, array $spec ): \Hodima\Core\Module => new \Hodima\Core\Module( $id, ...$spec ),
				array_keys( $specs ),
				$specs
			)
		);
		\Hodima\Core\Modules::load( 'seo' );

		// ماژول دیسکاور خاموش: رویداد روزانه آمار سرچ کنسول یتیم نماند
		if ( is_admin() && ! \Hodima\Core\Modules::is_loaded( 'seo', 'discover' ) && wp_next_scheduled( 'hodima_discover_sc_refresh' ) ) {
			wp_clear_scheduled_hook( 'hodima_discover_sc_refresh' );
		}
		return;
	}

	// بدون Hodima Core (نباید رخ دهد؛ Requires Plugins): همه ماژول‌ها مثل قبل
	foreach ( array_unique( array_merge( ...array_column( $specs, 'files' ) ) ) as $file ) {
		require_once HODIMA_SEO_DIR . '/' . $file;
	}
}, 5 );

/*
 * قوانین بازنویسی (سایت‌مپ، robots، llms.txt، فید پادکست)
 * با فعال/غیرفعال شدن افزونه باید از نو ساخته شوند.
 */
register_activation_hook( __FILE__, static function (): void {
	delete_option( 'rewrite_rules' ); // وردپرس در اولین درخواست بعدی از نو می‌سازد
} );

register_deactivation_hook( __FILE__, static function (): void {
	delete_option( 'rewrite_rules' );

	// رویدادهای کرون Google Indexing بدون افزونه یتیم می‌ماندند (همان مشکل
	// wpgi_google_process_queue قدیمی در «سلامت سایت»). بعد از فعال‌سازی دوباره،
	// هرس روزانه در init و صف با ورود آیتم جدید یا باز شدن پنل زمان‌بندی می‌شوند.
	wp_clear_scheduled_hook( 'hodima_gi_process_queue' );
	wp_clear_scheduled_hook( 'hodima_gi_daily_pruning' );
	// IndexNow (ساعتی) و پاکسازی روزانه لاگ‌ها؛ با فعال‌سازی دوباره خودکار زمان‌بندی می‌شوند
	wp_clear_scheduled_hook( 'hodima_core_hourly_sync' );
	wp_clear_scheduled_hook( 'hodima_core_daily_cleanup' );
	// آمار روزانه گوگل دیسکاور (با فعال‌سازی دوباره در اولین بازدید پیشخوان زمان‌بندی می‌شود)
	wp_clear_scheduled_hook( 'hodima_discover_sc_refresh' );
} );
