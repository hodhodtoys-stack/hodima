<?php
/**
 * Topic Cluster — Bootstrap
 * Path: core/topiccluster/topiccluster-init.php
 * Version: 4.0.0
 *
 *   includes/ref.php        نوع گره (enum) و ارجاع (Ref)
 *   includes/settings.php   تنظیمات ماژول
 *   includes/graph.php      مدل: والد مؤثر/خودکار، فرزند، هم‌خوشه، کش، ذخیره
 *   includes/migrate.php    مهاجرت داده نسخه‌های قبلی
 *   includes/render.php     شورت‌کد، نمایش خودکار، توضیح دسته
 *   includes/schema.php     isPartOf / hasPart / about
 *   includes/links.php      فهرست لینک‌های داخلی متن (جدول hodima_tc_links)
 *   includes/health.php     یتیم‌ها و گزارش سلامت خوشه‌ها
 *   helper.php              Hodima_TC_Helper — رابط سازگاری نسخه ۳
 *   admin/editor.php        کادر ویرایشگر، فیلد دسته، جستجو و ذخیره
 *   admin/lists.php         ستون «خوشه»، فیلتر و ویرایش گروهی فهرست‌ها
 *   admin/pages.php         «ابزارهای هدیما ← خوشه‌بندی» (یتیم، نقشه، سلامت، تنظیمات)
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'TOPICCLUSTER_VERSION' ) ) {

	define( 'TOPICCLUSTER_DIR', HODIMA_SEO_DIR . '/core/topiccluster/' );
	define( 'TOPICCLUSTER_URL', HODIMA_SEO_URL . '/core/topiccluster/' );
	define( 'TOPICCLUSTER_VERSION', '4.0.0' );

	require_once TOPICCLUSTER_DIR . 'includes/ref.php';
	require_once TOPICCLUSTER_DIR . 'includes/settings.php';
	require_once TOPICCLUSTER_DIR . 'includes/graph.php';
	require_once TOPICCLUSTER_DIR . 'includes/migrate.php';
	require_once TOPICCLUSTER_DIR . 'includes/render.php';
	require_once TOPICCLUSTER_DIR . 'includes/schema.php';
	require_once TOPICCLUSTER_DIR . 'includes/links.php';
	require_once TOPICCLUSTER_DIR . 'includes/health.php';
	require_once TOPICCLUSTER_DIR . 'helper.php';

	\Hodima\TopicCluster\Graph::init();
	\Hodima\TopicCluster\Render::init();
	\Hodima\TopicCluster\Schema::init();
	\Hodima\TopicCluster\Links::init();
	\Hodima\TopicCluster\Health::init();

	if ( is_admin() ) {
		require_once TOPICCLUSTER_DIR . 'admin/editor.php';
		require_once TOPICCLUSTER_DIR . 'admin/lists.php';
		require_once TOPICCLUSTER_DIR . 'admin/pages.php';

		\Hodima\TopicCluster\Migrate::init();
		\Hodima\TopicCluster\Admin\Editor::init();
		\Hodima\TopicCluster\Admin\Lists::init();
		\Hodima\TopicCluster\Admin\Pages::init();
	}
}
