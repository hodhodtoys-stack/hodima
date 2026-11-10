<?php
/**
 * بخش 2 از آزمون‌های دستار سئو (tools/wp-harness/advisor-tests.php آن را به ترتیب و در همان دامنه سراسری می‌خواند):
 * ذخیره امن (تراکنش، قفل)، رندر پیشخوان و کادر ویرایش، قاعده‌ها و فیلترها.
 * تنهایی اجرا نمی‌شود: متغیرها و توابع کمکی (hodima_ad، $cap، …) از فایل اصلی و بخش‌های قبلی می‌آیند.
 */

declare(strict_types=1);

echo "=== مرحله ۱: ذخیره امن (تراکنش، کلمه تکراری، قفل)\n";
// کلمه‌هایی که فقط در ی/ک عربی، حروف بزرگ و اعراب فرق دارند یکی می‌شوند (کلید اصلی جدول آن‌ها را یکی می‌دید)
$dedup = hodima_seo_advisor_aggregate_queries( [
	[ 'keys' => [ 'https://hodima.test/safe/', 'کلیپس پلومريا' ], 'clicks' => 3, 'impressions' => 100, 'position' => 4.0 ],
	[ 'keys' => [ 'https://hodima.test/safe/', 'کلیپس پلومریا' ], 'clicks' => 1, 'impressions' => 100, 'position' => 6.0 ],
	[ 'keys' => [ 'https://hodima.test/safe/', 'Clips' ], 'clicks' => 0, 'impressions' => 50, 'position' => 2.0 ],
	[ 'keys' => [ 'https://hodima.test/safe/', 'clips' ], 'clicks' => 0, 'impressions' => 50, 'position' => 4.0 ],
] );
hodima_ad( 'کلمه‌های هم‌ارز یکی می‌شوند (جمع نمایش، رتبه با وزن)', [ count( $dedup['/safe/'] ), array_sum( array_column( $dedup['/safe/'], 'impressions' ) ), array_values( $dedup['/safe/'] )[0]['position'] ], [ 2, 300, 5.0 ] );

$stored = hodima_seo_advisor_store( [ '/safe/a/' => [ 'clicks' => 5, 'impressions' => 900, 'position' => 3.0 ] ], [], [ '/safe/a/' => [ 'اول' => [ 'clicks' => 1, 'impressions' => 50, 'position' => 3.0 ] ] ] );
hodima_ad( 'ذخیره موفق: شمارش و بدون ردیف رد‌شده', is_array( $stored ) ? [ $stored['pages'], $stored['queries'], $stored['skipped'] ] : 'error', [ 1, 1, 0 ] );

$rolled = hodima_seo_advisor_transaction( static function (): WP_Error {
	hodima_seo_advisor_store( [ '/safe/new/' => [ 'clicks' => 1, 'impressions' => 10, 'position' => 1.0 ] ], [], [] );
	return new WP_Error( 'x', 'شکست عمدی' );
} );
hodima_ad( 'خطا وسط کار ← تراکنش برمی‌گردد و داده قبلی سالم می‌ماند', [ is_wp_error( $rolled ), hodima_seo_advisor_pages()['total'], null !== hodima_seo_advisor_page( '/safe/a/' ), null === hodima_seo_advisor_page( '/safe/new/' ), count( hodima_seo_advisor_queries( '/safe/a/' ) ) ], [ true, 1, true, true, 1 ] );

$thrown = hodima_seo_advisor_transaction( static function (): never {
	hodima_seo_advisor_store( [], [], [] );
	throw new RuntimeException( 'boom' );
} );
hodima_ad( 'استثنا وسط کار ← WP_Error و داده قبلی سالم', [ is_wp_error( $thrown ), hodima_seo_advisor_pages()['total'] ], [ true, 1 ] );

$nested = hodima_seo_advisor_transaction( static fn(): string => hodima_seo_advisor_transaction( static fn(): string => 'inner' ) . '+outer' );
hodima_ad( 'تراکنش تودرتو کار می‌کند', $nested, 'inner+outer' );

delete_option( HODIMA_SEO_ADVISOR_LOCK_OPTION );
$l1 = hodima_seo_advisor_lock();
$l2 = hodima_seo_advisor_lock();
$busy = hodima_seo_advisor_refresh();
hodima_ad( 'قفل: اولی می‌گیرد، دومی نه، دریافت داده «در حال انجام» می‌گوید', [ $l1, $l2, is_wp_error( $busy ) ? $busy->get_error_code() : 'ok' ], [ true, false, 'hodima_advisor_busy' ] );
hodima_seo_advisor_unlock();
hodima_ad( 'بعد از آزاد کردن دوباره قابل‌گرفتن است', hodima_seo_advisor_lock(), true );
update_option( HODIMA_SEO_ADVISOR_LOCK_OPTION, time() - HODIMA_SEO_ADVISOR_LOCK_TTL - 5, false );
hodima_ad( 'قفل مانده از اجرای مرده بعد از مهلت آزاد می‌شود', hodima_seo_advisor_lock(), true );
hodima_seo_advisor_unlock();

// قفل مالک دارد (SEO 3.0.9): آزادسازی توسط غیرمالک بی‌اثر؛ جایگزینی قفل کهنه با UPDATE شرطی
update_option( HODIMA_SEO_ADVISOR_LOCK_OPTION, time() . '|foreign', false );
hodima_seo_advisor_unlock();
hodima_ad( 'کسی که قفلی نگرفته قفل دیگری را آزاد نمی‌کند', get_option( HODIMA_SEO_ADVISOR_LOCK_OPTION, false ), time() . '|foreign' );
update_option( HODIMA_SEO_ADVISOR_LOCK_OPTION, ( time() - HODIMA_SEO_ADVISOR_LOCK_TTL - 5 ) . '|dead', false );
$lock_taken = hodima_seo_advisor_lock();
hodima_ad( 'قفل کهنه قالب تازه (زمان|توکن) هم جایگزین می‌شود و مقدار تازه مال ماست', [ $lock_taken, str_contains( (string) get_option( HODIMA_SEO_ADVISOR_LOCK_OPTION, '' ), '|dead' ) ], [ true, false ] );
hodima_seo_advisor_unlock();
hodima_ad( 'مالک قفل را آزاد می‌کند', get_option( HODIMA_SEO_ADVISOR_LOCK_OPTION, false ), false );

echo "=== تنظیمات و پاک کردن داده\n";
$before_cfg = hodima_seo_advisor_config()['min_impressions'];
$cfg_settings = get_option( HODIMA_SEO_ADVISOR_SETTINGS_OPTION, [] );
update_option( HODIMA_SEO_ADVISOR_SETTINGS_OPTION, [ 'min_impressions' => 250, 'days' => 28 ], false );
hodima_ad( 'حداقل نمایش از تنظیمات خوانده می‌شود', hodima_seo_advisor_config()['min_impressions'], 250 );
hodima_ad( 'محدوده مجاز حداقل نمایش', HODIMA_SEO_ADVISOR_MIN_IMPRESSIONS_RANGE, [ 10, 100000 ] );
ob_start();
hodima_seo_advisor_render_settings( true, true );
$settings_html = (string) ob_get_clean();
hodima_ad( 'کارت تنظیمات: مقدار فعلی در فیلد و هر دو فرم با nonce', [ str_contains( $settings_html, 'value="250"' ), str_contains( $settings_html, 'name="action" value="hodima_advisor_settings"' ), str_contains( $settings_html, 'name="action" value="hodima_advisor_clear"' ), substr_count( $settings_html, 'name="_wpnonce"' ) ], [ true, true, true, 3 ] );
ob_start();
hodima_seo_advisor_render_settings( false, true );
hodima_ad( 'بدون داده دکمه پاک کردن غیرفعال است', substr_count( (string) ob_get_clean(), 'disabled' ) >= 2, true );

hodima_seo_advisor_store( [ '/clear-me/' => [ 'clicks' => 1, 'impressions' => 5000, 'position' => 6.0 ] ], [], [] );
update_option( HODIMA_SEO_ADVISOR_IGNORED_OPTION, [ '/clear-me/' ], false );
update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [ 'fetched' => time(), 'pages' => 1 ], false );
update_option( 'hodima_discover_sc_key', 'KEEP-KEY', false );
update_option( HODIMA_SEO_ADVISOR_LOCK_OPTION, time(), false );
$busy_clear = hodima_seo_advisor_clear_data();
hodima_ad( 'پاک کردن وقتی دریافت داده در حال اجراست رد می‌شود و چیزی پاک نمی‌شود', [ is_wp_error( $busy_clear ) ? $busy_clear->get_error_code() : 'ok', hodima_seo_advisor_pages()['total'] ], [ 'hodima_advisor_busy', 1 ] );
delete_option( HODIMA_SEO_ADVISOR_LOCK_OPTION ); // قفل را دستی گذاشتیم، پس مالکش نیستیم
hodima_ad( 'پاک کردن داده موفق', hodima_seo_advisor_clear_data(), true );
hodima_ad( 'بعد از پاک کردن: جدول‌ها، وضعیت و نادیده‌ها خالی، قفل آزاد', [ hodima_seo_advisor_pages()['total'], hodima_seo_advisor_status()['fetched'], hodima_seo_advisor_ignored(), get_option( HODIMA_SEO_ADVISOR_LOCK_OPTION, false ) ], [ 0, 0, [], false ] );
hodima_ad( 'کلید حساب سرویس و تنظیمات می‌مانند', [ get_option( 'hodima_discover_sc_key' ), hodima_seo_advisor_config()['min_impressions'], hodima_seo_advisor_days() ], [ 'KEEP-KEY', 250, 28 ] );
delete_option( 'hodima_discover_sc_key' );
update_option( HODIMA_SEO_ADVISOR_SETTINGS_OPTION, $cfg_settings, false );

echo "=== تاریخچه هفتگی\n";
update_option( 'hodima_discover_sc_key', $key_json, false );
$week_ends = hodima_seo_advisor_history_week_ends();
hodima_ad( 'هشت یکشنبه پشت‌سرهم، تازه‌ترین اول، همه یکشنبه و دست‌کم ۲ روز پیش', [ count( $week_ends ), count( array_unique( $week_ends ) ), array_unique( array_map( static fn( string $d ): string => ( new DateTimeImmutable( $d ) )->format( 'w' ), $week_ends ) ), $week_ends[0] <= hodima_seo_advisor_ranges( 1 )['end'], $week_ends[0] > $week_ends[1] ], [ 8, 8, [ '0' ], true, true ] );

$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', hodima_seo_advisor_table( 'history' ) ) );
$calls = [];
$mode  = 'ok';
$sync  = hodima_seo_advisor_history_sync( 'sc-domain:' . $host );
hodima_ad( 'همگام‌سازی اول: ۸ هفته با ۸ درخواست جدا (هر هفته ۷ روز)', [ $sync, count( $calls ), count( array_unique( array_map( static fn( array $b ): string => $b['startDate'] . '|' . $b['endDate'], $calls ) ) ), array_unique( array_map( static fn( array $b ): int => ( new DateTimeImmutable( $b['endDate'] ) )->diff( new DateTimeImmutable( $b['startDate'] ) )->days, $calls ) ) ], [ [ 'added' => 8, 'failed' => 0 ], 8, 8, [ 6 ] ] );
hodima_ad( 'هفته‌های ذخیره‌شده', hodima_seo_advisor_history_known_weeks(), $week_ends );

$calls = [];
$sync2 = hodima_seo_advisor_history_sync( 'sc-domain:' . $host );
hodima_ad( 'همگام‌سازی دوم: همه ذخیره‌اند، درخواستی نمی‌رود', [ $sync2, count( $calls ) ], [ [ 'added' => 0, 'failed' => 0 ], 0 ] );

$trend = hodima_seo_advisor_history( '/accessory/hair-clips/', 12 );
hodima_ad( 'روند یک آدرس: ۸ هفته، قدیمی‌ترین اول، با عدد هفته', [ count( $trend ), $trend[0]['week'] === $week_ends[7], $trend[7]['week'] === $week_ends[0], $trend[7]['clicks'] > 0 ], [ 8, true, true, true ] );
$none = hodima_seo_advisor_history( '/not-in-history/', 12 );
hodima_ad( 'آدرسی که در عکس‌ها نیست: همان هفته‌ها با صفر', [ count( $none ), array_sum( array_column( $none, 'clicks' ) ), array_sum( array_column( $none, 'impressions' ) ) ], [ 8, 0, 0 ] );

$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE week_end = %s', hodima_seo_advisor_table( 'history' ), $week_ends[0] ) );
$calls = [];
$mode  = 'denied';
$sync3 = hodima_seo_advisor_history_sync( 'sc-domain:' . $host );
$mode  = 'ok';
hodima_ad( 'شکست یک هفته: خطا شمرده می‌شود و داده دیگر هفته‌ها سالم می‌ماند', [ $sync3['failed'] >= 1, count( hodima_seo_advisor_history_known_weeks() ) ], [ true, 7 ] );

$tiny = [ '/few/' => [ 'clicks' => 1, 'impressions' => HODIMA_SEO_ADVISOR_HISTORY_MIN_IMPRESSIONS - 1, 'position' => 3.0 ], '/enough/' => [ 'clicks' => 1, 'impressions' => HODIMA_SEO_ADVISOR_HISTORY_MIN_IMPRESSIONS, 'position' => 3.0 ] ];
hodima_ad( 'کم‌نمایش (زیر ۱۰) ذخیره نمی‌شود', [ hodima_seo_advisor_history_store_week( '2020-01-05', $tiny ), array_sum( array_column( hodima_seo_advisor_history( '/few/', 400 ), 'impressions' ) ) ], [ 1, 0 ] );
hodima_seo_advisor_history_prune();
hodima_ad( 'هفته بسیار قدیمی (بیش از ۱۰۴ هفته) پاک می‌شود', in_array( '2020-01-05', hodima_seo_advisor_history_known_weeks( 400 ), true ), false );

// پاک کردن داده‌های مشاور تاریخچه را نگه می‌دارد
hodima_seo_advisor_store( [ '/hist-keep/' => [ 'clicks' => 1, 'impressions' => 5000, 'position' => 6.0 ] ], [], [] );
hodima_seo_advisor_clear_data();
hodima_ad( 'پاک کردن داده‌های مشاور، تاریخچه هفتگی را نگه می‌دارد', count( hodima_seo_advisor_history_known_weeks() ), 7 );

$h = [
	[ 'week' => '2026-09-06', 'clicks' => 10, 'impressions' => 1000, 'position' => 8.0 ],
	[ 'week' => '2026-09-13', 'clicks' => 20, 'impressions' => 1000, 'position' => 8.0 ],
	[ 'week' => '2026-09-20', 'clicks' => 30, 'impressions' => 1000, 'position' => 8.0 ],
	[ 'week' => '2026-09-27', 'clicks' => 40, 'impressions' => 1000, 'position' => 7.0 ],
	[ 'week' => '2026-10-04', 'clicks' => 60, 'impressions' => 2000, 'position' => 6.0 ],
];
$ba = hodima_seo_advisor_before_after( $h, '2026-09-23' );
hodima_ad( 'قبل/بعد ویرایش وسط هفته: هفته‌ای که ویرایش وسطش است (۲۷ شهریور) شمرده نمی‌شود', [ $ba['before']['weeks'] ?? 0, $ba['before']['clicks'] ?? 0, $ba['after']['weeks'] ?? 0, $ba['after']['clicks'] ?? 0, $ba['after']['position'] ?? 0 ], [ 3, 20.0, 1, 60.0, 6.0 ] );
hodima_ad( 'قبل/بعد: بدون هفته بعد از ویرایش، یا بدون تاریخ ← null', [ hodima_seo_advisor_before_after( $h, '2026-10-02' ), hodima_seo_advisor_before_after( $h, '' ), hodima_seo_advisor_before_after( $h, '2026-09-01' ) ], [ null, null, null ] );
$modified_post = (int) wp_insert_post( [ 'post_title' => 'modified test', 'post_status' => 'publish', 'post_type' => 'page' ] );
hodima_ad( 'تاریخ ویرایش: نوشته واقعی، و خالی برای دسته/ناشناس', [ preg_match( '/^\d{4}-\d{2}-\d{2}$/', hodima_seo_advisor_object_modified( 'page', $modified_post ) ), hodima_seo_advisor_object_modified( 'product_cat', 1 ), hodima_seo_advisor_object_modified( '', 0 ) ], [ 1, '', '' ] );
// کارت روند در کادر ویرایش: ۸ هفته عکس برای یک آدرس + تاریخ ویرایش در وسط
$trend_ends = array_reverse( hodima_seo_advisor_history_week_ends() );
foreach ( $trend_ends as $i => $trend_end ) {
	hodima_seo_advisor_history_store_week( $trend_end, [ '/trend-page/' => [ 'clicks' => $i < 5 ? 20 : 60, 'impressions' => 1000, 'position' => $i < 5 ? 8.0 : 6.0 ] ] );
}
$edit_day = ( new DateTimeImmutable( $trend_ends[4] ) )->modify( '+3 days' )->format( 'Y-m-d' ); // وسط هفته ششم (۱۲ ام شمرده نمی‌شود)
$wpdb->update( $wpdb->posts, [ 'post_modified' => $edit_day . ' 10:00:00' ], [ 'ID' => $modified_post ] );
clean_post_cache( $modified_post );
ob_start();
hodima_seo_advisor_render_trend( [ 'path' => '/trend-page/', 'kind' => 'page', 'object_id' => $modified_post ] );
$trend_html = (string) ob_get_clean();
hodima_ad( 'کارت روند: ۸ میله، ۲ هفته کامل بعد از ویرایش سبز، جمله قبل/بعد', [ substr_count( $trend_html, 'hodima-advisor-box__bar-label' ), substr_count( $trend_html, 'class="is-after"' ), str_contains( $trend_html, 'hodima-advisor-box__compare' ), str_contains( $trend_html, sprintf( 'از %s به %s رسیده', number_format_i18n( 20, 1 ), number_format_i18n( 60, 1 ) ) ) ], [ 8, 2, true, true ] );
ob_start();
hodima_seo_advisor_render_trend( [ 'path' => '/no-history/', 'kind' => 'page', 'object_id' => $modified_post ] );
hodima_ad( 'آدرس بدون نمایش در تاریخچه: کارت روند نمی‌آید', ob_get_clean(), '' );
ob_start();
hodima_seo_advisor_render_trend( [ 'path' => '/trend-page/', 'kind' => 'product_cat', 'object_id' => 1 ] );
$cat_html = (string) ob_get_clean();
hodima_ad( 'دسته (بدون تاریخ ویرایش): نمودار هست، جمله قبل/بعد نیست', [ str_contains( $cat_html, 'hodima-advisor-box__bars' ), str_contains( $cat_html, 'hodima-advisor-box__compare' ) ], [ true, false ] );
wp_delete_post( $modified_post, true );
$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', hodima_seo_advisor_table( 'history' ) ) );
delete_option( 'hodima_discover_sc_key' );

echo "=== کلید مخصوص مشاور\n";
$own_key = (string) wp_json_encode( [ 'type' => 'service_account', 'client_email' => 'advisor-only@test.iam.gserviceaccount.com', 'private_key' => $pem, 'token_uri' => 'https://oauth2.googleapis.com/token' ] );
delete_option( HODIMA_SEO_SC_ADVISOR_KEY_OPTION );
delete_option( HODIMA_SEO_SC_ADVISOR_SITES_OPTION );
update_option( 'hodima_discover_sc_key', $key_json, false );
hodima_ad( 'بدون کلید مخصوص: مشاور همان کلید مشترک را می‌خواند', [ hodima_seo_sc_key_source( HODIMA_SEO_SC_ADVISOR ), hodima_seo_sc_email( HODIMA_SEO_SC_ADVISOR ), hodima_seo_sc_sites_option( HODIMA_SEO_SC_ADVISOR ) ], [ 'own', 'advisor@test.iam.gserviceaccount.com', 'hodima_discover_sc_sites' ] );
update_option( HODIMA_SEO_SC_ADVISOR_KEY_OPTION, $own_key, false );
hodima_ad( 'کلید مخصوص مشاور اولویت دارد؛ دیسکاور (بدون پارامتر) همان قبلی می‌ماند', [ hodima_seo_sc_key_source( HODIMA_SEO_SC_ADVISOR ), hodima_seo_sc_email( HODIMA_SEO_SC_ADVISOR ), hodima_seo_sc_key_source(), hodima_seo_sc_email(), hodima_seo_sc_sites_option( HODIMA_SEO_SC_ADVISOR ), hodima_seo_sc_sites_option() ], [ 'advisor_own', 'advisor-only@test.iam.gserviceaccount.com', 'own', 'advisor@test.iam.gserviceaccount.com', 'hodima_advisor_sc_sites', 'hodima_discover_sc_sites' ] );

delete_transient( 'hodima_discover_sc_token_' . substr( md5( 'advisor-only@test.iam.gserviceaccount.com' ), 0, 12 ) );
delete_transient( 'hodima_discover_sc_token_' . substr( md5( 'advisor@test.iam.gserviceaccount.com' ), 0, 12 ) );
$shared_sites_before = hodima_seo_sc_sites();
update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [], false );
$mode = 'ok';
hodima_ad( 'دریافت با کلید مخصوص موفق است', hodima_seo_advisor_refresh(), true );
hodima_ad( 'دریافت کامل در سایت کوچک: نوبت‌ها همان درخواست تمام می‌شوند و رویدادی نمی‌ماند', [ hodima_seo_advisor_pipeline_pending(), false !== wp_next_scheduled( HODIMA_SEO_ADVISOR_STAGE_CRON ) ], [ false, false ] );
$mb_ok = hodima_seo_advisor_refresh( true, 0.0 ); // مثل دکمه «گرفتن داده تازه» وقتی بودجه همان‌جا تمام شود
hodima_ad( 'دکمه دستی: داده ذخیره و نوبت‌ها در پس‌زمینه ادامه دارند (رویداد ادامه زمان‌بندی شد)', [ $mb_ok, hodima_seo_advisor_pipeline_pending(), false !== wp_next_scheduled( HODIMA_SEO_ADVISOR_STAGE_CRON ), get_option( HODIMA_SEO_ADVISOR_LOCK_OPTION, false ) ], [ true, true, true, false ] );
wp_clear_scheduled_hook( HODIMA_SEO_ADVISOR_STAGE_CRON );
do_action( HODIMA_SEO_ADVISOR_STAGE_CRON );
hodima_ad( 'کرون ادامه همه نوبت‌ها را تمام می‌کند', [ hodima_seo_advisor_pipeline_pending(), wp_next_scheduled( HODIMA_SEO_ADVISOR_STAGE_CRON ) ], [ false, false ] );
hodima_ad( 'توکن با ایمیل کلید مخصوص ساخته شد (نه کلید مشترک)', [ is_string( get_transient( 'hodima_discover_sc_token_' . substr( md5( 'advisor-only@test.iam.gserviceaccount.com' ), 0, 12 ) ) ), get_transient( 'hodima_discover_sc_token_' . substr( md5( 'advisor@test.iam.gserviceaccount.com' ), 0, 12 ) ) ], [ true, false ] );
hodima_ad( 'فهرست property مشاور جدا نوشته شد و فهرست مشترک دیسکاور دست‌نخورده', [ hodima_seo_sc_sites( HODIMA_SEO_SC_ADVISOR ) !== [], hodima_seo_sc_sites() ], [ true, $shared_sites_before ] );

// ردیف کلید مدرن (پیش از تعریف ثابت wp-config که منبع را برای همیشه ثابت می‌کند)
$kcap = static function ( callable $fn ): string { ob_start(); $fn(); return (string) ob_get_clean(); };
wp_set_current_user( 1 );
$_GET = [];
update_option( HODIMA_SEO_SC_ADVISOR_KEY_OPTION, $own_key, false );
$bar_own = $kcap( static fn() => hodima_seo_advisor_render_settings( true, true ) );
hodima_ad( 'ردیف کلید مدرن: کلید فعال، ایمیل، منبع، جایگزینی و حذف (کلید مخصوص)، پنجره با انتخاب فایل', [ str_contains( $bar_own, 'کلید فعال' ), str_contains( $bar_own, 'advisor-only@test.iam.gserviceaccount.com' ), str_contains( $bar_own, 'data-hdsa-key-open' ), str_contains( $bar_own, 'name="remove"' ), str_contains( $bar_own, 'data-hdsa-key-dialog' ), str_contains( $bar_own, 'data-hdsa-key-file' ) ], [ true, true, true, true, true, true ] );
delete_option( HODIMA_SEO_SC_ADVISOR_KEY_OPTION );
update_option( 'hodima_discover_sc_key', $key_json, false );
$bar_shared = $kcap( static fn() => hodima_seo_advisor_render_settings( true, true ) );
hodima_ad( 'کلید مشترک: «جایگزینی» هست ولی «برداشتن کلید» نیست', [ str_contains( $bar_shared, 'data-hdsa-key-open' ), str_contains( $bar_shared, 'name="remove"' ) ], [ true, false ] );
delete_option( 'hodima_discover_sc_key' );
$bar_none = $kcap( static fn() => hodima_seo_advisor_render_settings( true, true ) );
hodima_ad( 'بدون کلید: «افزودن کلید» و پنل باز است', [ str_contains( $bar_none, 'افزودن کلید' ), 1 === preg_match( '/id="hodima-advisor-settings"[^>]*\\shidden/', $bar_none ) ], [ true, false ] );
delete_option( HODIMA_SEO_SC_ADVISOR_KEY_OPTION );

define( 'HODIMA_SEO_ADVISOR_SERVICE_ACCOUNT_JSON', $key_json );
hodima_ad( 'ثابت wp-config از کلید پیشخوان جلوتر است', [ hodima_seo_sc_key_source( HODIMA_SEO_SC_ADVISOR ), hodima_seo_sc_email( HODIMA_SEO_SC_ADVISOR ) ], [ 'advisor_config', 'advisor@test.iam.gserviceaccount.com' ] );

// صفحه: کارت کلید و منبع
ob_start();
hodima_seo_advisor_render_settings( true, true );
$key_html = (string) ob_get_clean();
hodima_ad( 'کارت: منبع «کلید ثابت wp-config» نشان داده می‌شود و فیلد چسباندن نیست', [ str_contains( $key_html, 'ثابت wp-config' ), str_contains( $key_html, 'name="key_json"' ) ], [ true, false ] );

hodima_ad( 'اعتبارسنجی: JSON خراب رد می‌شود', hodima_seo_sc_validate_key( '{"a":1}' )['ok'], false );
hodima_ad( 'اعتبارسنجی: کلید درست قبول و ایمیل برمی‌گردد', [ hodima_seo_sc_validate_key( $own_key )['ok'], hodima_seo_sc_validate_key( $own_key )['email'] ], [ true, 'advisor-only@test.iam.gserviceaccount.com' ] );

delete_option( HODIMA_SEO_SC_ADVISOR_KEY_OPTION );
delete_option( HODIMA_SEO_SC_ADVISOR_SITES_OPTION );
delete_transient( 'hodima_discover_sc_token_' . substr( md5( 'advisor-only@test.iam.gserviceaccount.com' ), 0, 12 ) );
delete_option( 'hodima_discover_sc_key' );

echo "=== رقابت صفحه‌ها (گزارش کل سایت)\n";
hodima_seo_advisor_store(
	[
		'/c/a/' => [ 'clicks' => 80, 'impressions' => 4000, 'position' => 6.0 ],
		'/c/b/' => [ 'clicks' => 10, 'impressions' => 1000, 'position' => 9.0 ],
		'/c/m/' => [ 'clicks' => 30, 'impressions' => 2000, 'position' => 7.0 ],
		'/c/n/' => [ 'clicks' => 28, 'impressions' => 2000, 'position' => 7.5 ],
		'/c/p/' => [ 'clicks' => 20, 'impressions' => 1500, 'position' => 8.0 ],
		'/c/page/2/' => [ 'clicks' => 1, 'impressions' => 900, 'position' => 8.0 ],
		'/c/lone/' => [ 'clicks' => 5, 'impressions' => 900, 'position' => 8.0 ],
	],
	[],
	[
		'/c/a/' => [ 'کلیپس پلومریا' => [ 'clicks' => 80, 'impressions' => 4000, 'position' => 6.0 ] ],
		'/c/b/' => [ 'کلیپس پلومریا' => [ 'clicks' => 10, 'impressions' => 1000, 'position' => 9.0 ] ],
		'/c/m/' => [ 'گیره مو' => [ 'clicks' => 30, 'impressions' => 2000, 'position' => 7.0 ] ],
		'/c/n/' => [ 'گیره مو' => [ 'clicks' => 28, 'impressions' => 2000, 'position' => 7.5 ] ],
		'/c/p/' => [ 'گیره مو' => [ 'clicks' => 20, 'impressions' => 1500, 'position' => 8.0 ], 'هدهدلی کلیپس' => [ 'clicks' => 20, 'impressions' => 1500, 'position' => 1.0 ] ],
		'/c/page/2/' => [ 'کلیپس پلومریا' => [ 'clicks' => 1, 'impressions' => 900, 'position' => 8.0 ] ],
		'/c/lone/' => [ 'تنها' => [ 'clicks' => 5, 'impressions' => 900, 'position' => 8.0 ] ],
	]
);
update_option( HODIMA_SEO_ADVISOR_IGNORED_OPTION, [], false );
$comp = hodima_seo_advisor_competition();
$by   = array_column( $comp['rows'], null, 'term' );
hodima_ad( 'فقط کلمه‌هایی که دو صفحه یا بیشتر دارند؛ تک‌صفحه، صفحه‌بندی آرشیو و کلمه برندی کنار می‌رود', ( static function ( array $k ): array { sort( $k ); return $k; } )( array_keys( $by ) ), [ 'کلیپس پلومریا', 'گیره مو' ] );
hodima_ad( 'برنده بیشترین کلیک است و سهم‌ها جمع ۱', [ $by['کلیپس پلومریا']['pages'][0]['path'], $by['کلیپس پلومریا']['count'], round( array_sum( array_column( $by['کلیپس پلومریا']['pages'], 'share' ) ), 2 ), round( $by['کلیپس پلومریا']['pages'][0]['share'], 2 ) ], [ '/c/a/', 2, 1.0, 0.8 ] );
hodima_ad( 'سهم ۸۰٪ ← «برنده روشن» (strengthen)؛ سه صفحه هم‌وزن ← «یکی را اصلی کنید» (choose)', [ $by['کلیپس پلومریا']['advice']['type'], $by['گیره مو']['advice']['type'], $by['گیره مو']['count'] ], [ 'strengthen', 'choose', 3 ] );
hodima_ad( 'کلیک بالقوه رفع رقابت مثبت و فهرست مرتب بر اساس آن', [ $by['کلیپس پلومریا']['potential'] > 0, array_column( $comp['rows'], 'potential' ) === ( static function ( array $p ): array { rsort( $p ); return $p; } )( array_column( $comp['rows'], 'potential' ) ) ], [ true, true ] );
hodima_ad( 'جمع‌ها: گروه‌ها، صفحه‌های درگیر', [ $comp['total'], $comp['pages'] ], [ 2, 5 ] );
update_option( HODIMA_SEO_ADVISOR_IGNORED_OPTION, [ '/c/b/' ], false );
hodima_ad( 'صفحه نادیده‌گرفته‌شده از گروه می‌رود و گروهی که یک صفحه‌اش ماند حذف می‌شود', array_column( hodima_seo_advisor_competition()['rows'], 'term' ), [ 'گیره مو' ] );
update_option( HODIMA_SEO_ADVISOR_IGNORED_OPTION, [], false );
hodima_ad( 'جستجو در کلمه و آدرس + فیلتر نوع دستور', [ array_column( hodima_seo_advisor_competition( [ 'search' => 'گیره' ] )['rows'], 'term' ), array_column( hodima_seo_advisor_competition( [ 'search' => '/c/b/' ] )['rows'], 'term' ), array_column( hodima_seo_advisor_competition( [ 'type' => 'strengthen' ] )['rows'], 'term' ) ], [ [ 'گیره مو' ], [ 'کلیپس پلومریا' ], [ 'کلیپس پلومریا' ] ] );
hodima_ad( 'صفحه‌بندی: limit/offset', [ count( hodima_seo_advisor_competition( [ 'limit' => 1 ] )['rows'] ), hodima_seo_advisor_competition( [ 'limit' => 1, 'offset' => 1 ] )['rows'][0]['term'] ?? '' ], [ 1, array_column( $comp['rows'], 'term' )[1] ] );

// تب رقابت در پیشخوان
require_once HODIMA_SEO_DIR . '/core/advisor/advisor-competition-view.php';
require_once HODIMA_SEO_DIR . '/core/advisor/advisor-links-view.php';
require_once HODIMA_SEO_DIR . '/core/advisor/advisor-insights-view.php';
require_once HODIMA_SEO_DIR . '/core/advisor/advisor-breakdown-view.php';
require_once HODIMA_SEO_DIR . '/core/advisor/advisor-trend-view.php';
require_once HODIMA_SEO_DIR . '/core/advisor/advisor-tasks-view.php';
require_once HODIMA_SEO_DIR . '/core/advisor/advisor-health-view.php';
require_once HODIMA_SEO_DIR . '/core/advisor/advisor-crawl-view.php';
require_once dirname( __DIR__ ) . '/advisor-crawl-fixture.php'; // آزمون‌ها با همین خواننده CSV نمونه داده خزش می‌سازند (خزنده داخلی داده واقعی را می‌نویسد)
require_once HODIMA_SEO_DIR . '/core/advisor/advisor-score-view.php';
require_once HODIMA_SEO_DIR . '/core/advisor/advisor-inspect-view.php';
update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [ 'fetched' => time() - 3600, 'property' => 'sc-domain:' . $host, 'days' => 90, 'pages' => 7, 'queries' => 6, 'advised' => 0, 'error' => '' ] + hodima_seo_advisor_ranges( 90 ), false );
update_option( 'hodima_discover_sc_key', $key_json, false );
wp_set_current_user( 1 );
$_GET = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'competition' ];
ob_start();
hodima_seo_advisor_render_admin();
$tab_html = (string) ob_get_clean();
hodima_ad( 'تب رقابت: دو تب، ۴ کارت آمار، دو گروه با دستور و برنده، جدول بدون <details> فرصت‌ها', [ substr_count( $tab_html, 'hodima-advisor__tabs' ), substr_count( $tab_html, 'class="hds-stat"' ), substr_count( $tab_html, 'class="hodima-advisor__group"' ), str_contains( $tab_html, 'برنده پیشنهادی' ), str_contains( $tab_html, 'کلیپس پلومریا' ), str_contains( $tab_html, 'name="tab" value="competition"' ), str_contains( $tab_html, 'action=hodima_advisor_competition_csv' ) && str_contains( $tab_html, '_wpnonce=' ), str_contains( $tab_html, 'hodima-advisor__table' ) ], [ 1, 4, 2, true, true, true, true, false ] );
$_GET = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'competition', 'ctype' => 'choose' ];
ob_start();
hodima_seo_advisor_render_admin();
$tab_filtered = (string) ob_get_clean();
hodima_ad( 'تب رقابت: فیلتر «هم‌وزن» فقط یک گروه (گیره مو) را نشان می‌دهد', [ substr_count( $tab_filtered, 'class="hodima-advisor__group"' ), str_contains( $tab_filtered, 'گیره مو' ), str_contains( $tab_filtered, 'کلیپس پلومریا' ) ], [ 1, true, false ] );
$_GET = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'opportunities' ];
ob_start();
hodima_seo_advisor_render_admin();
$main_html = (string) ob_get_clean();
hodima_ad( 'تب فرصت‌ها پیش‌فرض است و تب رقابت کنارش لینک دارد', [ str_contains( $main_html, 'name="rule"' ), str_contains( $main_html, 'برگشت به فهرست کارها' ), str_contains( $main_html, 'hodima-advisor__group' ) ], [ true, true, false ] );
hodima_ad( 'ردیف تب چهار تب دارد: «کارها»، «کارهای انجام‌شده»، «خزش و داده» و «تنظیمات»؛ تب «سلامت سایت» حذف شد (SEO 3.5.8)', [ preg_match( '~<nav class="hodima-advisor__tabs".*?</nav>~s', $main_html, $tabs_nav ) ? substr_count( $tabs_nav[0], '<a ' ) : 0, str_contains( $main_html, '>کارها</a>' ), str_contains( $main_html, '>تنظیمات</a>' ), str_contains( $main_html, 'id="hodima-advisor-workview"' ) ], [ 4, true, true, false ] );
delete_option( 'hodima_discover_sc_key' );
$_GET = [];
update_option( 'hodima_discover_sc_key', $key_json, false );
update_option( HODIMA_SEO_ADVISOR_VARIANTS_OPTION, [ [ 'path' => '/a/', 'impressions' => 1250, 'clicks' => 11, 'urls' => [ [ 'url' => 'https://' . $host . '/a/', 'impressions' => 1000, 'clicks' => 10 ], [ 'url' => 'https://www.' . $host . '/a/', 'impressions' => 250, 'clicks' => 1 ] ], 'causes' => [ 'host' ], 'advice' => 'نمونه' ] ], false );
$_GET = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'competition' ];
ob_start();
hodima_seo_advisor_render_admin();
$with_variants = (string) ob_get_clean();
hodima_ad( 'تب رقابت: کارت آدرس‌های تکراری با آدرس‌ها و علت', [ str_contains( $with_variants, 'آدرس‌های تکراری یک صفحه' ), str_contains( $with_variants, 'دامنه یا پروتکل' ), str_contains( $with_variants, 'بیشترین نمایش' ) ], [ true, true, true ] );
delete_option( HODIMA_SEO_ADVISOR_VARIANTS_OPTION );
ob_start();
hodima_seo_advisor_render_admin();
hodima_ad( 'بدون آدرس تکراری: یک خط «پیدا نشد»', str_contains( (string) ob_get_clean(), 'آدرس تکراری پیدا نشد' ), true );
$_GET = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'terms' ];
ob_start();
hodima_seo_advisor_render_admin();
$terms_html = (string) ob_get_clean();
hodima_ad( 'تب «آمار جستجو»: ۴ کارت آمار، ستون‌های هم‌ردیف و جدول غیربرندی', [ substr_count( $terms_html, 'hodima-advisor__tabs' ), substr_count( $terms_html, 'class="hds-stat"' ), str_contains( $terms_html, 'hodima-advisor__cols' ), str_contains( $terms_html, 'hodima-advisor__terms' ), str_contains( $terms_html, 'کلیپس پلومریا' ) ], [ 1, 4, true, true, true ] );
$_GET = [ 'page' => HODIMA_SEO_ADVISOR_PAGE ];
ob_start();
hodima_seo_advisor_render_admin();
$overview_html = (string) ob_get_clean();
hodima_ad( 'کارت صفحه (SEO 3.8.9، طرح تأییدشده): سربرگ داخل کارت با نام و آدرس در یک گروه، نشان «N کار»، «گزارش» در ردیف برچسب‌ها و بی پیشوند «گزارش:» در متن', [ str_contains( $overview_html, '<div class="hodima-advisor__ov-id">' ), str_contains( $overview_html, 'hodima-advisor__ov-fact--count' ), substr_count( $overview_html, 'class="hodima-advisor__ov-report"' ) === substr_count( $overview_html, 'class="hodima-advisor__ov-item"' ), str_contains( $overview_html, '<strong>گزارش:</strong>' ) ], [ true, true, true, false ] );
hodima_ad( 'صفحه «کارها»: هر صفحه یک ردیف، هر ایراد یک خط با «انجام دادم / لازم نیست»؛ بدون دکمه گرفتن داده تازه (SEO 3.7.4: فقط در «خزش و داده»)، کارت آمار و کارت دریافت داده', [ substr_count( $overview_html, 'class="hodima-advisor__ov-row"' ) >= 1, substr_count( $overview_html, 'class="hodima-advisor__ov-item"' ) >= 1, str_contains( $overview_html, 'value="done" class="hds-btn hds-btn--outline hodima-advisor__mark-done"' ), str_contains( $overview_html, 'hodima-advisor__strip' ), substr_count( $overview_html, 'value="hodima_advisor_refresh"' ), str_contains( $overview_html, 'class="hds-stat"' ), str_contains( $overview_html, 'aria-label="نوع ایراد"' ), str_contains( $overview_html, 'tab=tasks' ) ], [ true, true, true, false, 0, false, true, true ] );
hodima_ad( 'تب ناشناس به فهرست «کارها» برمی‌گردد', [ ( static function (): bool { $_GET = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'x' ]; ob_start(); hodima_seo_advisor_render_admin(); return str_contains( (string) ob_get_clean(), 'hodima-advisor__ov' ); } )() ], [ true ] );
hodima_ad( 'تفسیر سهم برندی: سه حالت', [ str_contains( hodima_seo_advisor_brand_reading( 0.8 ), 'غیربرندی' ), str_contains( hodima_seo_advisor_brand_reading( 0.4 ), 'تعادل' ), str_contains( hodima_seo_advisor_brand_reading( 0.1 ), 'نام سایت کم جستجو' ) ], [ true, true, true ] );
delete_option( 'hodima_discover_sc_key' );
$_GET = [];

$cat_advice = hodima_seo_advisor_competition_advice( 'گیره', [ [ 'kind' => 'product', 'path' => '/p/' ], [ 'kind' => 'product_cat', 'path' => '/c/' ] ], 0.9 );
hodima_ad( 'برنده محصول و رقیب دسته ← «دسته را اصلی کنید»', $cat_advice['type'], 'category' );
hodima_seo_advisor_store( [], [], [] );

echo "=== تحلیل‌های کل سایت: آدرس تکراری و برندی\n";
$h  = 'https://' . $host;
$vr = static fn( string $url, int $clicks, int $impressions ): array => [ 'keys' => [ $url ], 'clicks' => $clicks, 'impressions' => $impressions, 'position' => 5.0 ];
$variants = hodima_seo_advisor_find_variants( [
	$vr( $h . '/a/', 10, 1000 ),
	$vr( 'https://www.' . $host . '/a/', 1, 200 ),
	$vr( $h . '/a/?utm_source=x', 0, 50 ),
	$vr( $h . '/b/', 5, 300 ),
	$vr( $h . '/B', 1, 40 ),
	$vr( $h . '/c/', 3, 90 ),
	$vr( $h . '/c/#toc-2', 0, 10 ),
	$vr( $h . '/d/', 3, 90 ),
	$vr( 'https://other-site.test/a/', 99, 99999 ),
] );
$vp = array_column( $variants, null, 'path' );
hodima_ad( 'آدرس‌های تکراری: فقط /a/ و /b/ (بخش # و دامنه دیگر حساب نمی‌شود)، پرنمایش‌ترین اول', array_column( $variants, 'path' ), [ '/a/', '/b/' ] );
hodima_ad( 'علت /a/: دامنه (www) و پارامتر؛ /b/: حروف بزرگ و اسلش', [ $vp['/a/']['causes'], $vp['/b/']['causes'], count( $vp['/a/']['urls'] ), $vp['/a/']['impressions'] ], [ [ 'host', 'query' ], [ 'case', 'slash' ], 3, 1250 ] );
hodima_ad( 'دستور مناسب علت (ریدایرکت ۳۰۱ و canonical)', [ str_contains( $vp['/a/']['advice'], '۳۰۱' ), str_contains( $vp['/a/']['advice'], 'canonical' ), str_contains( $vp['/b/']['advice'], 'اسلش' ) ], [ true, true, true ] );

hodima_seo_advisor_store(
	[ '/s/1/' => [ 'clicks' => 60, 'impressions' => 3000, 'position' => 5.0 ], '/s/2/' => [ 'clicks' => 40, 'impressions' => 2000, 'position' => 6.0 ] ],
	[],
	[
		'/s/1/' => [ 'هدهدلی کلیپس' => [ 'clicks' => 50, 'impressions' => 1000, 'position' => 1.0 ], 'کلیپس پلومریا' => [ 'clicks' => 10, 'impressions' => 1500, 'position' => 6.0 ] ],
		'/s/2/' => [ 'کلیپس پلومریا' => [ 'clicks' => 30, 'impressions' => 1000, 'position' => 7.0 ], 'گیره مو' => [ 'clicks' => 10, 'impressions' => 500, 'position' => 8.0 ] ],
	]
);
add_filter( 'hodima_seo_advisor_brand_terms', $brand_filter2 = static fn(): array => [ 'هدهدلی' ] );
$split = hodima_seo_advisor_brand_split();
hodima_ad( 'سهم برندی: ۵۰ کلیک و ۱۰۰۰ نمایش از یک کلمه؛ غیربرندی ۲ کلمه', [ $split['brand']['clicks'], $split['brand']['impressions'], $split['brand']['terms'], $split['other']['clicks'], $split['other']['impressions'], $split['other']['terms'] ], [ 50, 1000, 1, 50, 3000, 2 ] );
hodima_ad( 'برترین غیربرندی: «کلیپس پلومریا» با ۲ صفحه و رتبه وزنی ۶٫۴؛ پوشش ۸۰٪', [ $split['other_top'][0]['term'], $split['other_top'][0]['pages'], $split['other_top'][0]['position'], round( $split['coverage'], 2 ) ], [ 'کلیپس پلومریا', 2, 6.4, 0.8 ] );
remove_filter( 'hodima_seo_advisor_brand_terms', $brand_filter2 );
update_option( HODIMA_SEO_ADVISOR_VARIANTS_OPTION, $variants, false );
hodima_ad( 'فهرست تکراری ذخیره و خوانده می‌شود؛ «پاک کردن داده‌های دستار» آن را پاک می‌کند', [ count( hodima_seo_advisor_variants() ), hodima_seo_advisor_clear_data(), hodima_seo_advisor_variants() ], [ 2, true, [] ] );

echo "=== بخش‌بندی: دستگاه، کشور، تصویر/ویدیو، نوع نمایش\n";
remove_filter( 'hodima_seo_advisor_breakdown_enabled', '__return_false' );
$dev = static fn( string $path, string $device, int $clicks, int $impressions, float $position ): array => [ 'keys' => [ 'https://' . $host . $path, $device ], 'clicks' => $clicks, 'impressions' => $impressions, 'position' => $position ];
$device_rows = [
	$dev( '/g/a/', 'MOBILE', 20, 3000, 11.0 ), $dev( '/g/a/', 'DESKTOP', 30, 1000, 6.0 ), $dev( '/g/a/', 'TABLET', 1, 100, 7.0 ),
	$dev( '/g/b/', 'MOBILE', 10, 2000, 7.0 ), $dev( '/g/b/', 'DESKTOP', 12, 900, 6.0 ),
	$dev( '/g/c/', 'MOBILE', 5, 300, 20.0 ), $dev( '/g/c/', 'DESKTOP', 5, 400, 5.0 ),
	$dev( '/g/d/', 'MOBILE', 5, 900, 18.0 ),
];
$bd = hodima_seo_advisor_breakdown_devices( $device_rows );
hodima_ad( 'سهم دستگاه‌ها: موبایل ۶۲۰۰ نمایش و ۴۰ کلیک (رتبه وزنی)', [ $bd['devices']['mobile']['impressions'], $bd['devices']['mobile']['clicks'], $bd['devices']['desktop']['impressions'], $bd['devices']['tablet']['impressions'] ], [ 6200, 40, 2300, 100 ] );
hodima_ad( 'فاصله رتبه موبایل: فقط /g/a/ (۵ پله، نمایش کافی)؛ /g/b/ کم‌فاصله، /g/c/ کم‌نمایش، /g/d/ بدون دسکتاپ', [ array_column( $bd['gaps'], 'path' ), $bd['gaps'][0]['diff'] ?? 0 ], [ [ '/g/a/' ], 5.0 ] );

// همگام‌سازی با پاسخ ساختگی
update_option( 'hodima_discover_sc_key', $key_json, false );
delete_option( HODIMA_SEO_ADVISOR_BREAKDOWN_OPTION );
$bd_calls = [];
add_filter(
	'pre_http_request',
	$bd_filter = static function ( mixed $pre, array $args, string $url ) use ( &$bd_calls, $device_rows ): mixed {
		if ( ! str_contains( $url, '/searchAnalytics/query' ) ) {
			return $pre;
		}
		$body = json_decode( (string) $args['body'], true );
		$dims = $body['dimensions'];
		$json = static fn( array $rows ): array => [ 'response' => [ 'code' => 200 ], 'body' => (string) wp_json_encode( [ 'rows' => $rows ] ) ];
		if ( [ 'page', 'device' ] === $dims ) {
			$bd_calls[] = 'device';
			return $json( $device_rows );
		}
		if ( [ 'country' ] === $dims ) {
			$bd_calls[] = 'country';
			return $json( [ [ 'keys' => [ 'irn' ], 'clicks' => 900, 'impressions' => 90000, 'position' => 8.0 ], [ 'keys' => [ 'afg' ], 'clicks' => 60, 'impressions' => 4000, 'position' => 12.0 ], [ 'keys' => [ 'are' ], 'clicks' => 40, 'impressions' => 3000, 'position' => 9.0 ] ] );
		}
		if ( [ 'searchAppearance' ] === $dims ) {
			$bd_calls[] = 'appearance';
			return [ 'response' => [ 'code' => 'video' === ( $GLOBALS['bd_mode'] ?? '' ) ? 500 : 200 ], 'body' => (string) wp_json_encode( [ 'rows' => [ [ 'keys' => [ 'PRODUCT_SNIPPETS' ], 'clicks' => 120, 'impressions' => 9000, 'position' => 6.0 ] ] ] ) ];
		}
		if ( in_array( $body['type'] ?? '', [ 'image', 'video' ], true ) ) {
			$bd_calls[] = $body['type'];
			return $json( 'image' === $body['type'] ? [ [ 'keys' => [ 'https://' . $GLOBALS['host_for_bd'] . '/i/2/' ], 'clicks' => 30, 'impressions' => 900, 'position' => 4.0 ] ] : [] );
		}
		return $pre;
	},
	20,
	3
);
$GLOBALS['host_for_bd'] = $host;
$sync_range = hodima_seo_advisor_ranges( 90 );
$bs = hodima_seo_advisor_breakdown_sync( 'sc-domain:' . $host, $sync_range, 90 );
$stored_bd = hodima_seo_advisor_breakdown();
hodima_ad( 'همگام‌سازی (از ۳.۱.۳ فقط دستگاه): یک درخواست، بدون خطا', [ $bs, $bd_calls ], [ [ 'fetched' => 1, 'errors' => [] ], [ 'device' ] ] );
hodima_ad( 'ذخیره‌شده: فقط دستگاه و بازه؛ کلیدهای قدیمی (کشور، تصویر…) نوشته نمی‌شوند', [ isset( $stored_bd['device']['devices']['mobile'] ), isset( $stored_bd['country'] ), isset( $stored_bd['image'] ), $stored_bd['days'] ], [ true, false, false, 90 ] );
$bd_calls = [];
hodima_ad( 'دوباره زود است (کمتر از ۱۲ ساعت) ← null و درخواستی نمی‌رود', [ hodima_seo_advisor_breakdown_sync( 'sc-domain:' . $host, $sync_range, 90 ), $bd_calls ], [ null, [] ] );
hodima_ad( 'بازه عوض شود (۲۸ روز) ← دوباره می‌گیرد؛ با force هم', [ hodima_seo_advisor_breakdown_sync( 'sc-domain:' . $host, hodima_seo_advisor_ranges( 28 ), 28 )['fetched'] ?? 0, hodima_seo_advisor_breakdown_sync( 'sc-domain:' . $host, $sync_range, 28, true )['fetched'] ?? 0 ], [ 1, 1 ] );
update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [ 'fetched' => time() - 3600, 'property' => 'sc-domain:' . $host, 'days' => 90, 'pages' => 1, 'queries' => 0, 'advised' => 0, 'error' => '' ] + hodima_seo_advisor_ranges( 90 ), false );
hodima_seo_advisor_store( [ '/g/a/' => [ 'clicks' => 51, 'impressions' => 4100, 'position' => 9.0 ] ], [], [] );
wp_set_current_user( 1 );
$_GET = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'terms' ];
ob_start();
hodima_seo_advisor_render_admin();
$bd_html = (string) ob_get_clean();
hodima_ad( 'کارت موبایل داخل «آمار جستجو»: فاصله رتبه، آدرس، بدون کشور و نوع نمایش', [ str_contains( $bd_html, 'در موبایل بدتر رتبه می‌گیرند' ), str_contains( $bd_html, '/g/a/' ), str_contains( $bd_html, 'افغانستان' ), str_contains( $bd_html, 'ریچ‌اسنیپت محصول' ) ], [ true, true, false, false ] );
$_GET = [];
remove_filter( 'pre_http_request', $bd_filter, 20 );
hodima_ad( 'پاک کردن داده‌های مشاور بخش‌بندی را هم پاک می‌کند', [ hodima_seo_advisor_clear_data(), hodima_seo_advisor_breakdown() ], [ true, [] ] );
delete_option( 'hodima_discover_sc_key' );

echo "=== روند ماهانه و کلمه‌های تازه/از دست رفته\n";
remove_filter( 'hodima_seo_advisor_trend_enabled', '__return_false' );
$day = static fn( string $date, int $clicks, int $impressions ): array => [ 'keys' => [ $date ], 'clicks' => $clicks, 'impressions' => $impressions, 'position' => 6.0 ];
$date_rows = [];
// ۲۸ روز مهر ۱۴۰۴ (۲۰۲۵-۰۹-۲۳ تا ۲۰۲۵-۱۰-۲۰ ≈ مهر)، یک ماه کامل مهر ۱۴۰۵ و یک ماه ناقص
foreach ( range( 0, 29 ) as $i ) {
	$date_rows[] = $day( ( new DateTimeImmutable( '2025-09-23' ) )->modify( "+{$i} days" )->format( 'Y-m-d' ), 10, 1000 ); // مهر ۱۴۰۴
	$date_rows[] = $day( ( new DateTimeImmutable( '2026-09-23' ) )->modify( "+{$i} days" )->format( 'Y-m-d' ), 20, 1500 ); // مهر ۱۴۰۵
}
$date_rows[] = $day( '2026-11-01', 5, 100 ); // آبان ۱۴۰۵: فقط یک روز ← ناقص
$months = hodima_seo_advisor_trend_months( $date_rows );
$by_key = array_column( $months, null, 'key' );
hodima_ad( 'ماه‌های شمسی: فقط ۳ ماه؛ مهر ۱۴۰۴ و مهر ۱۴۰۵ کامل، آبان ۱۴۰۵ ناقص، قدیمی‌ترین اول', [ array_column( $months, 'key' ), $by_key[ 140407 ]['partial'] ?? null, $by_key[ 140507 ]['partial'] ?? null, $by_key[ 140508 ]['partial'] ?? null ], [ [ 140407, 140507, 140508 ], false, false, true ] );
$yoy = hodima_seo_advisor_trend_year_over_year( $months );
hodima_ad( 'مقایسه با سال قبل: آخرین ماه کامل (مهر ۱۴۰۵) ← +۱۰۰٪', [ $yoy['clicks'] ?? 0, $yoy['prev_clicks'] ?? 0, $yoy['change'] ?? 0 ], [ 600, 300, 100.0 ] );
hodima_ad( 'بدون ماه کامل سال قبل ← null', hodima_seo_advisor_trend_year_over_year( array_slice( $months, 2 ) ), null );

$qrow = static fn( string $term, int $clicks, int $impressions, float $position = 8.0 ): array => [ 'keys' => [ $term ], 'clicks' => $clicks, 'impressions' => $impressions, 'position' => $position ];
$change = hodima_seo_advisor_trend_terms(
	[ $qrow( 'کلیپس جدید', 5, 400 ), $qrow( 'ثابت', 10, 900 ), $qrow( 'کم', 0, 50 ), $qrow( 'هدهدلی تازه', 9, 500 ), $qrow( 'برگشتی', 2, 300 ) ],
	[ $qrow( 'ثابت', 11, 950 ), $qrow( 'کلیپس قدیمی', 8, 800 ), $qrow( 'کم‌نمایش قدیمی', 1, 100 ), $qrow( 'برگشتی', 1, 5 ), $qrow( 'هدهدلی قدیمی', 9, 600 ) ]
);
hodima_ad( 'کلمه تازه: «کلیپس جدید» (≥۱۰۰ نمایش و قبلا نبود) و «برگشتی» (قبلا کمتر از ۱۰)؛ ثابت و کم‌نمایش نه', array_column( $change['new'], 'term' ), [ 'کلیپس جدید', 'برگشتی' ] );
hodima_ad( 'کلمه از دست رفته: فقط «کلیپس قدیمی» (≥۱۵۰ نمایش قبلا و حالا نیست)', array_column( $change['lost'], 'term' ), [ 'کلیپس قدیمی' ] );
add_filter( 'hodima_seo_advisor_brand_terms', $brand_filter3 = static fn(): array => [ 'هدهدلی' ] );
$change2 = hodima_seo_advisor_trend_terms( [ $qrow( 'هدهدلی تازه', 9, 500 ) ], [ $qrow( 'هدهدلی قدیمی', 9, 600 ) ] );
remove_filter( 'hodima_seo_advisor_brand_terms', $brand_filter3 );
hodima_ad( 'کلمه برندی در هیچ‌کدام نمی‌آید', [ $change2['new'], $change2['lost'] ], [ [], [] ] );

update_option( 'hodima_discover_sc_key', $key_json, false );
delete_option( HODIMA_SEO_ADVISOR_TREND_OPTION );
$tr_calls = [];
add_filter(
	'pre_http_request',
	$tr_filter = static function ( mixed $pre, array $args, string $url ) use ( &$tr_calls, $date_rows, $qrow ): mixed {
		if ( ! str_contains( $url, '/searchAnalytics/query' ) ) {
			return $pre;
		}
		$body = json_decode( (string) $args['body'], true );
		$json = static fn( array $rows ): array => [ 'response' => [ 'code' => 200 ], 'body' => (string) wp_json_encode( [ 'rows' => $rows ] ) ];
		if ( [ 'date' ] === $body['dimensions'] ) {
			$tr_calls[] = [ 'date', $body['startDate'], $body['endDate'] ];
			return $json( $date_rows );
		}
		if ( [ 'query' ] === $body['dimensions'] ) {
			$tr_calls[] = [ 'query', $body['startDate'], $body['endDate'] ];
			return $json( 1 === count( array_filter( $tr_calls, static fn( array $c ): bool => 'query' === $c[0] ) ) ? [ $qrow( 'تازه ۱', 1, 300 ) ] : [] ); // اولی = ۲۸ روز اخیر، دومی = قبلش
		}
		return $pre;
	},
	20,
	3
);
$ts = hodima_seo_advisor_trend_sync( 'sc-domain:' . $host );
$trend = hodima_seo_advisor_trend();
hodima_ad( 'همگام‌سازی: ۳ درخواست (تاریخ ۱۶ ماه، کلمه ۲۸ روز اخیر، کلمه ۲۸ روز قبل) و دو بخش ذخیره', [ $ts, array_column( $tr_calls, 0 ), count( $trend['months'] ), array_column( $trend['new'], 'term' ), $trend['lost'] ], [ [ 'fetched' => 2, 'errors' => [] ], [ 'date', 'query', 'query' ], 3, [ 'تازه ۱' ], [] ] );
$span = ( new DateTimeImmutable( $tr_calls[0][1] ) )->diff( new DateTimeImmutable( $tr_calls[0][2] ) );
hodima_ad( 'بازه روند حدود ۱۶ ماه (۴۸۰ تا ۴۹۰ روز) و پنجره کلمه‌ها ۲۸ روز', [ $span->days >= 480 && $span->days <= 490, ( new DateTimeImmutable( $tr_calls[1][1] ) )->diff( new DateTimeImmutable( $tr_calls[1][2] ) )->days ], [ true, 27 ] );
$tr_calls = [];
hodima_ad( 'دوباره زود است ← null و بدون درخواست؛ با force می‌گیرد', [ hodima_seo_advisor_trend_sync( 'sc-domain:' . $host ), count( $tr_calls ), hodima_seo_advisor_trend_sync( 'sc-domain:' . $host, true )['fetched'] ?? 0 ], [ null, 0, 2 ] );
remove_filter( 'pre_http_request', $tr_filter, 20 );
update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [ 'fetched' => time() - 3600, 'property' => 'sc-domain:' . $host, 'days' => 90, 'pages' => 1, 'queries' => 0, 'advised' => 0, 'error' => '' ] + hodima_seo_advisor_ranges( 90 ), false );
hodima_seo_advisor_store( [ '/t/a/' => [ 'clicks' => 51, 'impressions' => 4100, 'position' => 9.0 ] ], [], [ '/t/a/' => [ 'تازه ۱' => [ 'clicks' => 1, 'impressions' => 300, 'position' => 8.0 ] ] ] );
wp_set_current_user( 1 );
$_GET = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'terms' ];
ob_start();
hodima_seo_advisor_render_admin();
$trend_html = (string) ob_get_clean();
hodima_ad( 'کارت روند داخل «آمار جستجو»: ۳ ستون ماه (یکی ناقص)، کلمه تازه، ماه کامل', [ substr_count( $trend_html, 'hodima-advisor__months-bar' ), substr_count( $trend_html, 'class="is-partial"' ), str_contains( $trend_html, 'تازه ۱' ), str_contains( $trend_html, 'پرکلیک‌ترین ماه کامل' ) ], [ 3, 1, true, true ] );
$_GET = [];
hodima_ad( 'پاک کردن داده‌های مشاور روند را هم پاک می‌کند', [ hodima_seo_advisor_clear_data(), hodima_seo_advisor_trend() ], [ true, [] ] );
delete_option( 'hodima_discover_sc_key' );
add_filter( 'hodima_seo_advisor_trend_enabled', '__return_false' );

echo "=== کارهای من (وضعیت دستی) و فیلتر حداقل کلیک بالقوه\n";
$saved_tasks = get_option( HODIMA_SEO_ADVISOR_TASKS_OPTION, false );
delete_option( HODIMA_SEO_ADVISOR_TASKS_OPTION );
hodima_ad( 'کار تازه: ثبت، خواندن، کلید با کلمه هم‌ارز (ی/ک عربی) یکی', [ hodima_seo_advisor_task_set( '/k/a/', 'near_first', 'كليپس', 'کلمه نزدیک صفحه اول', 'doing' ), hodima_seo_advisor_task( '/k/a/', 'near_first', 'کلیپس' )['status'] ?? '', hodima_seo_advisor_task( '/k/a/', 'low_ctr', '' ) ], [ true, 'doing', null ] );
$doing_at = hodima_seo_advisor_task( '/k/a/', 'near_first', 'کلیپس' )['at'];
hodima_seo_advisor_task_set( '/k/a/', 'near_first', 'کلیپس', '', 'doing' );
hodima_ad( 'ثبت دوباره همان وضعیت: تاریخ و عنوان قبلی می‌ماند', [ hodima_seo_advisor_task( '/k/a/', 'near_first', 'کلیپس' )['at'], hodima_seo_advisor_task( '/k/a/', 'near_first', 'کلیپس' )['title'] ], [ $doing_at, 'کلمه نزدیک صفحه اول' ] );
hodima_ad( 'وضعیت نامعتبر، قاعده نامعتبر و آدرس خالی رد می‌شوند', [ hodima_seo_advisor_task_set( '/k/a/', 'low_ctr', '', 't', 'bad' ), hodima_seo_advisor_task_set( '/k/a/', 'BAD RULE', '', 't', 'done' ), hodima_seo_advisor_task_set( '', 'low_ctr', '', 't', 'done' ) ], [ false, false, false ] );
hodima_seo_advisor_task_set( '/k/b/', 'low_ctr', '', 'عنوان و متا', 'done' );
hodima_seo_advisor_task_set( '/k/c/', 'decline', '', 'افت', 'rejected' );
hodima_ad( 'شمار گروه‌های چرخه عمر: در حال انجام ← نیاز به کار، انجام شد (آماری) ← منتظر گوگل', hodima_seo_advisor_task_counts(), [ 'open' => 1, 'waiting' => 1, 'fixed' => 0, 'rejected' => 1 ] );
hodima_seo_advisor_task_set( '/k/c/', 'decline', '', '', '' );
hodima_ad( 'وضعیت خالی = برداشتن از فهرست', [ hodima_seo_advisor_task( '/k/c/', 'decline', '' ), count( hodima_seo_advisor_tasks() ) ], [ null, 2 ] );

// سقف ۵۰۰: «لازم نیست»ها اول می‌روند
$many = [];
foreach ( range( 1, HODIMA_SEO_ADVISOR_TASKS_MAX + 2 ) as $i ) {
	$many[ 'k' . $i ] = [ 'path' => "/m/{$i}/", 'rule' => 'low_ctr', 'term' => '', 'title' => 't', 'status' => 1 === $i % 2 ? 'rejected' : 'doing', 'at' => 1000 + $i ];
}
update_option( HODIMA_SEO_ADVISOR_TASKS_OPTION, $many, false );
hodima_seo_advisor_task_set( '/k/z/', 'low_ctr', '', 'تازه', 'doing' );
$capped = hodima_seo_advisor_tasks();
hodima_ad( 'سقف ۵۰۰ کار؛ تازه می‌ماند و قدیمی‌ترین «لازم نیست» اول می‌رود', [ count( $capped ), isset( $capped[ hodima_seo_advisor_task_key( '/k/z/', 'low_ctr', '' ) ] ), isset( $capped['k1'] ), isset( $capped['k2'] ) ], [ HODIMA_SEO_ADVISOR_TASKS_MAX, true, false, true ] );
delete_option( HODIMA_SEO_ADVISOR_TASKS_OPTION );

// اثر: کار «انجام دادم» بیش از ۳ هفته پیش با تاریخچه
$we = array_reverse( hodima_seo_advisor_history_week_ends() );
foreach ( $we as $i => $w ) {
	hodima_seo_advisor_history_store_week( $w, [ '/k/eff/' => [ 'clicks' => $i < 4 ? 20 : 60, 'impressions' => 1000, 'position' => $i < 4 ? 8.0 : 6.0 ] ] );
}
$done_day = ( new DateTimeImmutable( $we[3] ) )->modify( '+3 days' )->format( 'Y-m-d' ); // وسط هفته پنجم (اندیس ۴)
$old_task = [ 'path' => '/k/eff/', 'rule' => 'low_ctr', 'term' => '', 'title' => 'عنوان', 'status' => 'done', 'at' => (int) strtotime( $done_day . ' 12:00:00 UTC' ) ];
$eff = hodima_seo_advisor_task_effect( $old_task );
hodima_ad( 'اثر: انجام‌شده وسط هفته ۵ ← قبل ۴ هفته (۲۰) ← بعد ۳ هفته (۶۰)', [ $eff['state'], $eff['effect']['before']['clicks'] ?? 0, $eff['effect']['after']['clicks'] ?? 0 ], [ 'ok', 20.0, 60.0 ] );
hodima_ad( 'اثر: کار همین الان انجام‌شده ← «هنوز زود است»؛ آدرس بی‌تاریخچه ← «تاریخچه کافی نیست»', [ hodima_seo_advisor_task_effect( [ 'path' => '/k/eff/', 'status' => 'done', 'at' => time() ] )['state'], hodima_seo_advisor_task_effect( [ 'path' => '/k/none/', 'status' => 'done', 'at' => time() - 90 * DAY_IN_SECONDS ] )['state'] ], [ 'wait', 'none' ] );
$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', hodima_seo_advisor_table( 'history' ) ) );

// نما: کنترل‌های کادر ویرایش و تب «کارهای من»
hodima_seo_advisor_task_set( '/k/b/', 'low_ctr', '', 'عنوان و متا را بازنویسی کنید', 'done' );
hodima_seo_advisor_task_set( '/k/a/', 'near_first', 'کلیپس', 'کلمه نزدیک صفحه اول', 'doing' );
// کنترل‌های «انجام دادم/لازم نیست» کادر ویرایش با SEO 3.7.6 برداشته شد (فقط در «کارها»)؛ آزمونش هم
update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [ 'fetched' => time() - 3600, 'property' => 'sc-domain:' . $host, 'days' => 90, 'pages' => 1, 'queries' => 0, 'advised' => 0, 'error' => '' ] + hodima_seo_advisor_ranges( 90 ), false );
hodima_seo_advisor_store( [ '/k/b/' => [ 'clicks' => 5, 'impressions' => 3000, 'position' => 8.0 ] ], [], [] );
update_option( 'hodima_discover_sc_key', $key_json, false );
wp_set_current_user( 1 );
$_GET = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'tasks' ];
ob_start();
hodima_seo_advisor_render_admin();
$tasks_html = (string) ob_get_clean();
hodima_ad( 'تب «کارها» (پیش‌فرض «نیاز به کار»): فقط کار باز، فرم با nonce؛ کار «انجام دادم» اینجا نیست', [ str_contains( $tasks_html, 'aria-label="وضعیت کار"' ), substr_count( $tasks_html, 'class="hodima-advisor__task"' ), str_contains( $tasks_html, 'name="action" value="hodima_advisor_task"' ), str_contains( $tasks_html, 'کلمه نزدیک صفحه اول' ), str_contains( $tasks_html, 'عنوان و متا را بازنویسی کنید</strong>' ) ], [ true, 1, true, true, false ] );
hodima_ad(
	'تب «کارهای انجام‌شده» (SEO 3.3.4): چهار تب (از 3.5.8) و این یکی فعال، بدون خط به‌روزرسانی و پیوند برگشت، فیلتر دکمه‌ای (نه منو)، عنوان کارت',
	[
		preg_match( '~<nav class="hodima-advisor__tabs".*?</nav>~s', $tasks_html, $tt_nav ) ? substr_count( $tt_nav[0], '<a ' ) : 0,
		str_contains( $tasks_html, 'tab=tasks" aria-current="page"' ),
		substr_count( $tasks_html, 'aria-current="page"' ),
		substr_count( $tasks_html, 'value="hodima_advisor_refresh"' ),
		str_contains( $tasks_html, 'برگشت به فهرست کارها' ),
		str_contains( $tasks_html, '<select' ),
		str_contains( $tasks_html, 'class="hodima-advisor__chip"' ),
		str_contains( $tasks_html, '<h2 class="hds-card__title">کارهای انجام‌شده</h2>' ),
	],
	[ 4, true, 1, 0, false, false, true, true ]
);
$_GET = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'tasks', 'tstatus' => 'waiting' ];
ob_start();
hodima_seo_advisor_render_admin();
$done_html = (string) ob_get_clean();
hodima_ad( 'کنش‌ها دکمه‌اند نه کلمه لینکی: «ویرایش/دیدن در سایت» با کلاس دکمه؛ دکمه وضعیت کار هم کلاس دکمه دارد (SEO 3.5.5: «برگردان به فهرست» بی‌آیکون)', [ substr_count( $done_html, 'hds-btn hds-btn--outline hodima-advisor__act' ) >= 1, str_contains( $done_html, 'hodima-advisor__link-btn' ), (bool) preg_match( '/<button[^>]*class="hds-btn[^"]*"[^>]*>(برگردان به فهرست|تمام شد، پاک کن|انجام دادم|دوباره انجام دادم|لازم نیست|پاک کردن)</u', $done_html ) ], [ true, false, true ] );
hodima_ad( 'راهنما: فهرست «اصطلاح‌ها به زبان ساده» پایین تب با H1 و نرخ کلیک؛ «؟» با popover و نام قابل‌خواندن', [ str_contains( $done_html, 'hodima-advisor__glossary' ) && str_contains( $done_html, 'H1 (تیتر اصلی)' ) && str_contains( $done_html, '<dt>نرخ کلیک</dt>' ), 1 === preg_match( '#<button type="button" class="hodima-advisor-help" popovertarget="(hdsa-help-\d+)" aria-label="«رتبه» یعنی چه؟">؟</button><span id="\1" popover#u', hodima_seo_advisor_help( 'position' ) ), hodima_seo_advisor_help( 'nope' ) ], [ true, true, '' ] );
hodima_ad( 'هر قاعده «چرا مهم است» و «از کجا بفهمم درست شد» دارد؛ متنی ← همان لحظه ذخیره، آماری ← ۱۴ روز و تب «کارهای انجام‌شده» (SEO 3.7.1؛ نتیجه سنجش آنجا می‌آید)', [ array_filter( array_map( static fn( string $r ): bool => '' === hodima_seo_advisor_rule_guide( $r )['why'] || '' === hodima_seo_advisor_rule_guide( $r )['verify'], [ 'low_ctr', 'near_first', 'missing_headings', 'missing_content', 'weak_query', 'cannibal', 'decline' ] ) ), str_contains( hodima_seo_advisor_rule_guide( 'missing_content' )['verify'], 'همان لحظه ذخیره' ), str_contains( hodima_seo_advisor_rule_guide( 'low_ctr' )['verify'], '«کارهای انجام‌شده»' ) ], [ [], true, true ] );
hodima_ad( 'راهنمای «کارها»: آدرس وسط «چه دیدیم» و گام‌ها لینک چپ‌به‌راست جدا (bdi) است، نه متن ساده که اسلشش جابه‌جا شود (SEO 3.7.1)', ( static function (): array { ob_start(); hodima_seo_advisor_render_guide( [ 'seen' => 'بیشترین سهم با https://e.test/a-b/ (۶۰٪) است.', 'need' => 'rec', 'steps' => [ 'به https://e.test/c/ لینک بدهید.' ], 'verify' => '', 'why' => '' ] ); $h = (string) ob_get_clean(); return [ substr_count( $h, 'dir="ltr"><bdi>' ), str_contains( $h, '</a> (۶۰٪)' ) ]; } )(), [ 2, true ] );
hodima_ad( '«کارهای انجام‌شده» ← «منتظر نتیجه»: بدون پیوند برگشت (خودش تب است)، کار «انجام دادم» با سه گام (انجام دادید، منتظر بررسی ایندکس، منتظر نتیجه با سنجش ۱۴ روزه)', [ ! str_contains( $done_html, 'برگشت به فهرست کارها' ), substr_count( $done_html, 'class="hodima-advisor__task"' ), substr_count( $done_html, 'class="hodima-advisor__step"' ), str_contains( $done_html, 'منتظر بررسی ایندکس' ), str_contains( $done_html, 'همان‌قدر روز پیش از آن مقایسه می‌شود' ), str_contains( $done_html, 'کلمه نزدیک صفحه اول' ) ], [ true, 1, 3, true, true, false ] );
hodima_ad(
	'کارت «کارهای انجام‌شده» (SEO 3.9.0، طرح تأییدشده): نوار سربرگ صفحه، عنوان H3 و نشان کوتاه کهربایی، گام‌شمار (✓ انجام، «الان اینجاست» با aria-current، بعدی شماره‌دار با نوار روزشمار)، ارقام فارسی، دکمه آبی کم‌رنگ و «پاک کردن» بی‌قاب؛ بی نارنجی پر',
	[
		substr_count( $done_html, 'class="hodima-advisor__task-bar"' ),
		(bool) preg_match( '~<h3 class="hodima-advisor__task-title">[^<]+</h3>~u', $done_html ),
		(bool) preg_match( '~data-tone="wait">منتظر داده گوگل · ۰ از ۱۴ روز</span>~u', $done_html ),
		str_contains( $done_html, 'انجام شد · منتظر' ),
		[ substr_count( $done_html, 'data-mode="ok"' ), substr_count( $done_html, 'data-mode="now" aria-current="step"' ), substr_count( $done_html, 'data-mode="next"' ) ],
		substr_count( $done_html, 'class="hodima-advisor__step-bar"' ),
		str_contains( $done_html, 'hodima-advisor__task-btn--reject' ) && str_contains( $done_html, 'hodima-advisor__task-btn--quiet' ) && str_contains( $done_html, 'dashicons-trash' ),
		str_contains( $done_html, 'hodima-advisor__mark-reject' ),
	],
	[ 1, true, true, false, [ 1, 1, 1 ], 1, true, false ]
);
hodima_ad(
	'نشان کوتاه و رنگ وضعیت: «انجام شد · » و «؛ قدم بعدی را ببینید» حذف، پرانتز پیشرفت با « · »؛ منتظر کهربایی، بی‌اثر قرمز، برطرف سبز',
	[ hodima_seo_advisor_task_short_label( 'انجام شد · منتظر داده گوگل (3 از 14 روز)' ), hodima_seo_advisor_task_short_label( 'اثر نداشت؛ قدم بعدی را ببینید' ), hodima_seo_advisor_task_short_label( 'انجام شد؛ هنوز دیده می‌شود' ), hodima_seo_advisor_task_short_label( 'برطرف شد' ), hodima_seo_advisor_task_tone( 'waiting' ), hodima_seo_advisor_task_tone( 'no_effect' ), hodima_seo_advisor_task_tone( 'fixed' ), hodima_seo_advisor_task_tone( 'rejected' ) ],
	[ 'منتظر داده گوگل · 3 از 14 روز', 'اثر نداشت', 'هنوز دیده می‌شود', 'برطرف شد', 'wait', 'bad', 'ok', 'info' ]
);
$kb_at   = hodima_seo_advisor_task( '/k/b/', 'low_ctr', '' )['at'];
$ins_old = get_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION, false );
update_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION, [ '/k/b/' => [ 'verdict' => 'PASS', 'coverage' => 'Submitted and indexed', 'last_crawl' => gmdate( 'c', $kb_at - 5 * DAY_IN_SECONDS ), 'checked' => time() - 3 * DAY_IN_SECONDS ] ], false );
hodima_ad( 'خزش گوگل پیش از تغییر: «no» و آدرس کار اول صف بررسی ایندکس (حتی با بررسی ۳ روز پیش)', [ hodima_seo_advisor_inspect_after( '/k/b/', $kb_at )['state'], hodima_seo_advisor_inspect_queue( 5 )[0] ?? '' ], [ 'no', '/k/b/' ] );
update_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION, [ '/k/b/' => [ 'verdict' => 'PASS', 'coverage' => 'Submitted and indexed', 'last_crawl' => gmdate( 'c', $kb_at - 5 * DAY_IN_SECONDS ), 'checked' => time() - 3600 ] ], false );
hodima_ad( 'تازه بررسی شده (کمتر از ۲ روز): دوباره در صف نیست', in_array( '/k/b/', hodima_seo_advisor_inspect_queue( 50 ), true ), false );
update_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION, [ '/k/b/' => [ 'verdict' => 'PASS', 'coverage' => 'Submitted and indexed', 'last_crawl' => gmdate( 'c', $kb_at + DAY_IN_SECONDS ), 'checked' => time() - 3 * DAY_IN_SECONDS ] ], false );
$tl = hodima_seo_advisor_task_timeline( hodima_seo_advisor_task( '/k/b/', 'low_ctr', '' ) );
hodima_ad( 'خزش بعد از تغییر: «yes»، گام دوم «گوگل صفحه را دوباره خواند» سبز و آدرس دیگر اول صف نیست', [ hodima_seo_advisor_inspect_after( '/k/b/', $kb_at )['state'], $tl[1]['label'], $tl[1]['state'], ( hodima_seo_advisor_inspect_queue( 5 )[0] ?? '' ) === '/k/b/' ], [ 'yes', 'گوگل صفحه را دوباره خواند', 'ok', false ] );
false === $ins_old ? delete_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION ) : update_option( HODIMA_SEO_ADVISOR_INSPECT_OPTION, $ins_old, false );

// فیلتر حداقل کلیک بالقوه
$_GET = [];
hodima_ad( 'فیلتر حداقل کلیک بالقوه: فقط مقدار مجاز قبول می‌شود', [ hodima_seo_advisor_list_filters( [ 'min' => '100' ] )['min'], hodima_seo_advisor_list_filters( [ 'min' => '77' ] )['min'], hodima_seo_advisor_list_filters( [ 'min' => '100' ] )['args']['min_potential'] ], [ 100, 0, 100 ] );
hodima_seo_advisor_store( [ '/p/hi/' => [ 'clicks' => 5, 'impressions' => 9000, 'position' => 7.0 ], '/p/lo/' => [ 'clicks' => 5, 'impressions' => 1200, 'position' => 7.0 ] ], [], [] );
$wpdb->update( hodima_seo_advisor_table( 'pages' ), [ 'potential' => 600, 'advice_count' => 1 ], [ 'path' => '/p/hi/' ] );
$wpdb->update( hodima_seo_advisor_table( 'pages' ), [ 'potential' => 60, 'advice_count' => 1 ], [ 'path' => '/p/lo/' ] );
hodima_ad( 'صفحه‌ها با حداقل بالقوه ۱۰۰ و ۵۰۰ و ۱۰۰۰', [ array_column( hodima_seo_advisor_pages( [ 'has_advice' => true, 'min_potential' => 100 ] )['rows'], 'path' ), array_column( hodima_seo_advisor_pages( [ 'has_advice' => true, 'min_potential' => 500 ] )['rows'], 'path' ), hodima_seo_advisor_pages( [ 'has_advice' => true, 'min_potential' => 1000 ] )['total'], hodima_seo_advisor_pages( [ 'has_advice' => true ] )['total'] ], [ [ '/p/hi/' ], [ '/p/hi/' ], 0, 2 ] );
delete_option( 'hodima_discover_sc_key' );
false === $saved_tasks ? delete_option( HODIMA_SEO_ADVISOR_TASKS_OPTION ) : update_option( HODIMA_SEO_ADVISOR_TASKS_OPTION, $saved_tasks, false );
$_GET = [];

echo "=== سلامت آدرس‌ها: بدون نمایش و آدرس مرده\n";
$mk_page = static function ( string $slug, string $title, int $days_old ): int {
	$id = (int) wp_insert_post( [ 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => $slug ] );
	if ( $days_old > 0 ) {
		$date = gmdate( 'Y-m-d H:i:s', time() - $days_old * DAY_IN_SECONDS );
		wp_update_post( [ 'ID' => $id, 'post_date' => $date, 'post_date_gmt' => $date, 'edit_date' => true ] );
	}
	return $id;
};
foreach ( [ 'hl-seen', 'hl-old', 'hl-new', 'hl-secret' ] as $slug ) {
	foreach ( get_posts( [ 'post_type' => 'page', 'name' => $slug, 'post_status' => 'any', 'numberposts' => -1 ] ) as $left ) {
		wp_delete_post( $left->ID, true );
	}
}
$h_seen = $mk_page( 'hl-seen', 'دیده‌شده', 200 );
$h_old  = $mk_page( 'hl-old', 'قدیمی بدون نمایش', 120 );
$h_new  = $mk_page( 'hl-new', 'تازه بدون نمایش', 3 );
$h_sec  = $mk_page( 'hl-secret', 'رمزدار', 120 );
wp_update_post( [ 'ID' => $h_sec, 'post_password' => 'x' ] );
hodima_seo_advisor_store(
	[
		hodima_seo_advisor_path_key( (string) get_permalink( $h_seen ) ) => [ 'clicks' => 5, 'impressions' => 500, 'position' => 8.0 ],
		'/hl-missing-page/' => [ 'clicks' => 3, 'impressions' => 800, 'position' => 9.0 ],
		'/hl-low-views/'      => [ 'clicks' => 0, 'impressions' => 10, 'position' => 9.0 ],
		'/'                  => [ 'clicks' => 3, 'impressions' => 900, 'position' => 9.0 ],
	],
	[],
	[]
);
$unseen = hodima_seo_advisor_health_unseen();
$un_ids = array_column( $unseen['unseen'], 'id' );
hodima_ad( 'بدون نمایش: صفحه قدیمی در فهرست، تازه جدا شمرده، دیده‌شده و رمزدار نه', [ in_array( $h_old, $un_ids, true ), in_array( $h_new, $un_ids, true ), in_array( $h_seen, $un_ids, true ), in_array( $h_sec, $un_ids, true ), $unseen['fresh'] >= 1, $unseen['seen'] >= 1 ], [ true, false, false, false, true, true ] );
$old_entry = array_values( array_filter( $unseen['unseen'], static fn( array $u ): bool => $u['id'] === $h_old ) )[0] ?? [];
hodima_ad( 'سن صفحه قدیمی حدود ۱۲۰ روز و نوع page؛ فهرست مرتب بر سن (قدیمی‌ترین اول)', [ abs( ( $old_entry['days'] ?? 0 ) - 120 ) <= 1, $old_entry['type'] ?? '', array_column( $unseen['unseen'], 'days' ) === ( static function ( array $d ): array { rsort( $d ); return $d; } )( array_column( $unseen['unseen'], 'days' ) ) ], [ true, 'page', true ] );

$dead = hodima_seo_advisor_health_dead_urls();
$dead_by = array_column( $dead, null, 'path' );
hodima_ad( 'آدرس مرده: /hl-missing-page/ با ۸۰۰ نمایش ← missing؛ صفحه اصلی، کم‌نمایش (<۲۰) و صفحه سالم نمی‌آیند', [ $dead_by['/hl-missing-page/']['state'] ?? '', isset( $dead_by['/'] ), isset( $dead_by['/hl-low-views/'] ), isset( $dead_by[ hodima_seo_advisor_path_key( (string) get_permalink( $h_seen ) ) ?? '' ] ) ], [ 'missing', false, false, false ] );

remove_filter( 'hodima_seo_advisor_health_enabled', '__return_false' );
hodima_seo_advisor_health_sync();
$hs = hodima_seo_advisor_health();
hodima_ad( 'همگام‌سازی: گزینه ساخته می‌شود با جمع‌ها و فهرست‌ها', [ isset( $hs['computed'] ), $hs['router'] ?? null, ( $hs['total'] ?? 0 ) >= 3, count( $hs['unseen'] ?? [] ) >= 1, count( $hs['dead'] ?? [] ) >= 1 ], [ true, true, true, true, true ] );
add_filter( 'hodima_seo_advisor_health_enabled', '__return_false' );
update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [ 'fetched' => time() - 3600, 'property' => 'sc-domain:' . $host, 'days' => 90, 'pages' => 4, 'queries' => 0, 'advised' => 0, 'error' => '' ] + hodima_seo_advisor_ranges( 90 ), false );
update_option( 'hodima_discover_sc_key', $key_json, false );
wp_set_current_user( 1 );
$_GET = [ 'page' => HODIMA_SEO_ADVISOR_PAGE, 'tab' => 'health' ];
ob_start();
hodima_seo_advisor_render_admin();
$health_html = (string) ob_get_clean();
hodima_ad( 'تب سلامت: ۴ کارت آمار، جدول آدرس مرده با «۴۰۴» و دستور، جدول صفحه بدون نمایش', [ substr_count( $health_html, 'class="hds-stat"' ), str_contains( $health_html, '/hl-missing-page/' ), str_contains( $health_html, 'ریدایرکت ۳۰۱ به آن ببرید' ), str_contains( $health_html, 'قدیمی بدون نمایش' ), str_contains( $health_html, 'برگشت به فهرست کارها' ) ], [ 4, true, true, true, true ] );
delete_option( 'hodima_discover_sc_key' );
$_GET = [];
hodima_ad( 'پاک کردن داده‌های مشاور سلامت را هم پاک می‌کند', [ hodima_seo_advisor_clear_data(), hodima_seo_advisor_health() ], [ true, [] ] );
foreach ( [ $h_seen, $h_old, $h_new, $h_sec ] as $hid ) {
	wp_delete_post( $hid, true );
}
