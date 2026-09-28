<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if (!function_exists('hodima_gi_render_pagination')) {
    function hodima_gi_render_pagination($current, $max, $param) {
        if ($max <= 1) return;
        $links = paginate_links([
            'base' => add_query_arg( $param, '%#%' ),
            'format' => '',
            'prev_text' => 'قبلی',
            'next_text' => 'بعدی',
            'total' => $max,
            'current' => $current,
            'type' => 'array'
        ]);
        if ( is_array( $links ) ) {
            echo '<div class="in-pagination">';
            foreach ( $links as $link ) {
                $link = str_replace("page-numbers", "in-page-link", $link);
                $link = str_replace("current", "active", $link);
                echo $link;
            }
            echo '</div>';
        }
    }
}

$settings = Hodima_GI_Helper::get_settings(); 
$stale_days = $settings['stale_content_days'] ?? 60;
$quota_used      = Hodima_GI_Helper::get_quota_usage();
$quota_max       = Hodima_GI_Helper::get_quota_limit();
$quota_remaining = Hodima_GI_Helper::quota_remaining();
$quota_exhausted = ( 0 === $quota_remaining );
$quota_pct       = min( 100, ( $quota_used / max( 1, $quota_max ) ) * 100 );
$quota_reset_in  = max( 0, Hodima_GI_Helper::next_quota_reset() - time() );

$health          = Hodima_Crawler_DB_Queries::get_seo_health_detail();
$account_email   = Hodima_GI_Helper::configured_account_email();

$next_run        = wp_next_scheduled( Hodima_GI_Queue::HOOK );
$queue_count     = Hodima_GI_Queue::count();
$wp_cron_off     = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;

// Pagination logic
$paged_cr = isset($_GET['paged_cr']) ? max(1, (int)$_GET['paged_cr']) : 1;
$crawls_data = Hodima_Crawler_DB_Queries::get_recent_crawls_paginated($paged_cr, 15);
$crawls = $crawls_data['items'];
$crawls_pages = $crawls_data['max_pages'];

$paged_st = isset($_GET['paged_st']) ? max(1, (int)$_GET['paged_st']) : 1;
$stale_data = Hodima_Crawler_DB_Queries::get_stale_posts_paginated($stale_days, $paged_st, 15);
$stale_posts = $stale_data['items'];
$stale_pages = $stale_data['max_pages'];

// آدرس هر مورد راکد و آخرین بازدید گوگل‌بات از آن (فقط ۱۵ مورد همین صفحه)
$stale_urls = [];
foreach ( $stale_posts as $p ) {
    $stale_urls[ (int) $p['ID'] ] = Hodima_GI_Helper::clean_url( (string) get_permalink( (int) $p['ID'] ) );
}
$stale_crawls = Hodima_Crawler_DB_Queries::get_last_crawls_for_urls( array_values( $stale_urls ) );

/*
 * نمایش تاریخ به وقت سایت (و جلالی، اگر ماژول تاریخ جلالی روشن باشد).
 * نسخه قبلی تاریخ خام دیتابیس را نشان می‌داد و ستون «زمان اجرا»ی صف با
 * date() به وقت UTC و بدون تاریخ بود.
 */
$hodima_gi_ts = static function ( ?string $mysql, bool $gmt = false ): int {
    if ( null === $mysql || '' === $mysql || str_starts_with( $mysql, '0000' ) ) {
        return 0;
    }
    try {
        return ( new DateTimeImmutable( $mysql, $gmt ? new DateTimeZone( 'UTC' ) : wp_timezone() ) )->getTimestamp();
    } catch ( Exception $e ) {
        return 0;
    }
};
// date_i18n با زمان محلی (نه wp_date): فیلتر wp_date ماژول جلالی فعلا ساعت را به وقت UTC نشان می‌دهد
$hodima_gi_date = static fn( int $ts ): string => $ts > 0
    ? date_i18n( 'Y/m/d H:i', $ts + wp_timezone()->getOffset( new DateTimeImmutable( '@' . $ts ) ) )
    : '—';
$hodima_gi_ago  = static fn( int $ts ): string => $ts > 0 ? human_time_diff( $ts, time() ) . ' پیش' : '';
$hodima_gi_minutes = static function ( int $minutes ): string {
    if ( $minutes < 60 ) {
        return number_format_i18n( $minutes ) . ' دقیقه';
    }
    if ( $minutes < 2880 ) {
        $hours = round( $minutes / 60, 1 );
        return number_format_i18n( $hours, floor( $hours ) === $hours ? 0 : 1 ) . ' ساعت';
    }
    return number_format_i18n( (int) round( $minutes / 1440 ) ) . ' روز';
};

// عیب‌یابی گزارش خزش
$bot_diag     = Hodima_Bot_Detector::diagnostics();
$bot_ranges   = Hodima_Bot_Detector::ranges_status();
$bot_dns      = Hodima_Bot_Detector::dns_available();
$ls_active    = Hodima_GI_Helper::litespeed_active();
$ls_bypass    = Hodima_Bot_Detector::litespeed_bypasses_googlebot();
$key_in_config = defined( 'HODIMA_GI_SERVICE_ACCOUNT_JSON' ) && '' !== (string) HODIMA_GI_SERVICE_ACCOUNT_JSON;

$paged_q = isset($_GET['paged_q']) ? max(1, (int)$_GET['paged_q']) : 1;
$queue_data = Hodima_GI_Queue::get_all_paginated($paged_q, 15);
$active_queue = $queue_data['items'];
$queue_pages = $queue_data['max_pages'];

$paged_l = isset($_GET['paged_l']) ? max(1, (int)$_GET['paged_l']) : 1;
$logs_data = Hodima_Crawler_DB_Queries::get_system_logs_paginated($paged_l, 15);
$logs = $logs_data['items'];
$logs_pages = $logs_data['max_pages'];

?>
<div class="wrap hd-wrap"><div class="in-admin">
    <?php
    hodima_admin_header( [
        'title'       => 'ایندکس گوگل (Indexing API)',
        'description' => 'ارسال خودکار صفحات به گوگل با صف و سهمیه روزانه، گزارش خزش ربات‌ها و احیای محتوای راکد.',
        'icon'        => 'dashicons-google',
    ] );
    ?>


    <form id="hodima-form">
        <div class="in-tabs">
            <button type="button" class="in-tab-btn" data-tab="g">Google API</button>
            <button type="button" class="in-tab-btn" data-tab="cr">گزارش خزش ربات‌ها</button>
            <button type="button" class="in-tab-btn" data-tab="st">محتوای راکد</button>
            <button type="button" class="in-tab-btn" data-tab="b">عملیات</button>
            <button type="button" class="in-tab-btn" data-tab="q">صف انتظار</button>
            <button type="button" class="in-tab-btn" data-tab="l">تاریخچه عملیات</button>
            <button type="button" class="in-tab-btn" data-tab="c">تنظیمات</button>
        </div>
        <?php // پیام نتیجه ذخیره زیر تب‌ها (مثل بقیه صفحه‌های هدیما) ?>
        <div id="hodima-notices" class="hd-notices"></div>
        
        <div class="in-tab-content" id="in-tab-g">
            <div class="in-section">
                <h2 class="in-section-title">سهمیه روزانه Indexing API (<?php echo esc_html( number_format_i18n( $quota_max ) ); ?> لینک)</h2>
                <div class="in-quota-wrap<?php echo $quota_exhausted ? ' is-exhausted' : ''; ?>">
                    <div class="in-quota-head">
                        <span>مصرف امروز: <?php echo esc_html( number_format_i18n( $quota_used ) ); ?> لینک · باقی‌مانده: <?php echo esc_html( number_format_i18n( $quota_remaining ) ); ?></span>
                        <span><?php echo esc_html( number_format_i18n( (int) round( $quota_pct ) ) ); ?>٪</span>
                    </div>
                    <div class="in-quota-track">
                        <div class="in-quota-bar" style="width:<?php echo esc_attr( (string) round( $quota_pct, 1 ) ); ?>%;"></div>
                    </div>
                    <p class="in-hint">
                        سهمیه در نیمه‌شب به وقت اقیانوس آرام (ساعت گوگل) صفر می‌شود —
                        حدود <?php echo esc_html( human_time_diff( time(), time() + $quota_reset_in ) ); ?> دیگر.
                        <?php if ( $quota_exhausted ) : ?>
                            <strong>سهمیه امروز تمام شده؛ صف خودکار پس از صفر شدن ادامه می‌یابد.</strong>
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <div class="in-section">
                <h2 class="in-section-title">وضعیت زمان‌بندی صف</h2>
                <div class="in-quota-wrap">
                    <p class="in-hint">
                        آیتم‌های صف: <strong><?php echo esc_html( number_format_i18n( $queue_count ) ); ?></strong> ·
                        اجرای بعدی:
                        <strong><?php echo $next_run ? esc_html( wp_date( 'Y/m/d H:i', (int) $next_run ) ) : ( $queue_count ? 'زمان‌بندی نشده' : 'نیازی نیست (صف خالی)' ); ?></strong>
                    </p>
                    <?php if ( ! $wp_cron_off && Hodima_GI_Helper::litespeed_active() ) : ?>
                        <p class="in-hint in-hint--warn">
                            کش صفحه لایت‌اسپید فعال است و WP-Cron فقط با بازدیدهایی اجرا می‌شود که به PHP برسند؛
                            برای اجرای دقیق صف، cron واقعی سرور را جایگزین کنید (راهنما در انتهای همین صفحه).
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="in-section">
                <h2 class="in-section-title">کلید Service Account</h2>
                <?php
                /*
                 * نسخه قبلی کل فایل JSON — شامل کلید خصوصی — را در هر بار باز
                 * شدن این صفحه داخل textarea چاپ می‌کرد. حالا فقط ایمیل حساب
                 * نمایش داده می‌شود.
                 *
                 * کادر خالی و «Choose file / No file chosen» که همیشه زیر این
                 * بخش بودند برداشته شدند: دکمه «افزودن کلید» پنجره‌ای باز می‌کند
                 * که فایل را انتخاب (یا متنش را بچسبانید) و با دکمه خودش ذخیره
                 * می‌شود — نه با «ذخیره پیکربندی» پایین صفحه.
                 */
                ?>
                <div class="in-key-card">
                    <?php if ( '' !== $account_email ) : ?>
                        <span class="hd-pill hd-pill--ok"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>کلید فعال</span>
                        <code class="in-key-email" dir="ltr"><?php echo esc_html( $account_email ); ?></code>
                        <span class="in-key-actions">
                            <button type="button" class="in-btn in-btn-outline" id="g-key-open" aria-haspopup="dialog"><span class="dashicons dashicons-update" aria-hidden="true"></span> جایگزینی کلید</button>
                            <?php if ( ! $key_in_config ) : ?>
                                <button type="button" class="in-btn in-btn-danger" id="g-key-remove"><span class="dashicons dashicons-trash" aria-hidden="true"></span> حذف کلید</button>
                            <?php endif; ?>
                        </span>
                    <?php else : ?>
                        <span class="hd-pill hd-pill--error"><span class="dashicons dashicons-warning" aria-hidden="true"></span>کلیدی تنظیم نشده</span>
                        <span class="in-key-note">بدون کلید هیچ لینکی به گوگل ارسال نمی‌شود.</span>
                        <span class="in-key-actions">
                            <button type="button" class="in-btn in-btn-primary" id="g-key-open" aria-haspopup="dialog"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> افزودن کلید</button>
                        </span>
                    <?php endif; ?>
                </div>
                <?php if ( $key_in_config ) : ?>
                    <p class="in-hint">کلید در <code>wp-config.php</code> تعریف شده و بر کلید ذخیره‌شده در پنل مقدم است.</p>
                <?php endif; ?>
            </div>

            <div class="in-section">
                <h2 class="in-section-title">محتوای مجاز برای ارسال</h2>
                <div class="in-checkbox-row">
                    <?php foreach ( get_post_types(['public'=>true],'objects') as $t ) : ?>
                        <label class="in-checkbox-label">
                            <input type="checkbox" class="g-pt" value="<?php echo esc_attr($t->name); ?>" <?php checked(in_array($t->name, $settings['google_post_types'] ?? [])); ?>>
                            <span style="white-space: nowrap;"><?php echo esc_html($t->labels->singular_name); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <label class="in-toggle-label" style="margin-top:20px; display:inline-block; font-weight: bold; background: #fff; padding: 10px 20px; border-radius: 8px; border: 1px solid var(--in-border);">
                    <input type="checkbox" id="g-en" <?php checked(!empty($settings['enable_google'])); ?>>
                    <span>فعال‌سازی ارسال هوشمند به گوگل</span>
                </label>
            </div>
            <button type="button" class="in-btn in-btn-outline" id="t-api">تست اتصال گوگل</button>
        </div>
        
        <div class="in-tab-content" id="in-tab-cr">
            <div class="in-section">
                <div class="in-quota-wrap" style="background:var(--hodima-bg-gray, #eef2f8); border-color:var(--in-primary);">
                    <h3 class="in-health-title">سلامت سئو (زمان واکنش گوگل پس از پینگ)</h3>
                    <?php if ( $health['samples'] > 0 ) : ?>
                        <p class="in-health-value">
                            میانه: <strong><?php echo esc_html( number_format_i18n( $health['median'] ) ); ?> دقیقه</strong>
                            · میانگین: <?php echo esc_html( number_format_i18n( $health['avg'] ) ); ?> دقیقه
                            · تعداد نمونه: <?php echo esc_html( number_format_i18n( $health['samples'] ) ); ?>
                        </p>
                    <?php else : ?>
                        <p class="in-health-value">
                            هنوز نمونه‌ای ثبت نشده. پس از اولین پینگ موفق و اولین بازدید تأییدشده گوگل‌بات از
                            همان صفحه، اینجا پر می‌شود.
                        </p>
                    <?php endif; ?>
                    <p class="in-hint">
                        میانه ملاک است نه میانگین؛ یک صفحه که گوگل هفته‌ها بعد خزیده، میانگین را بی‌معنی می‌کند.
                        بازدیدهایی که از کش لایت‌اسپید سرو شوند به PHP نمی‌رسند و شمرده نمی‌شوند.
                    </p>
                </div>
                <?php
                /*
                 * چرا گزارش خالی می‌ماند؟ نسخه قبلی هیچ توضیحی نمی‌داد. سه علت
                 * رایج اینجا بررسی و نمایش داده می‌شوند: کش لایت‌اسپید (گوگل‌بات
                 * اصلا به PHP نمی‌رسد)، نبود روش تأیید هویت در هاست، و ردشدن
                 * درخواست‌ها (IP اشتباه پشت Cloudflare یا ربات جعلی).
                 */
                ?>
                <div class="in-crawl-status">
                    <?php if ( $ls_active && true !== $ls_bypass ) : ?>
                        <div class="hd-callout hd-callout--warning">
                            <span class="dashicons dashicons-warning" aria-hidden="true"></span>
                            <div>
                                <p><strong>کش لایت‌اسپید جلوی ثبت خزش را می‌گیرد<?php echo null === $ls_bypass ? ' (احتمالا)' : ''; ?></strong></p>
                                <p>
                                    گوگل‌بات صفحه‌ها را از کش لایت‌اسپید می‌گیرد و درخواستش اصلا به وردپرس نمی‌رسد؛ پس اینجا چیزی ثبت نمی‌شود.
                                    برای ثبت دقیق: <strong>LiteSpeed Cache ← Cache ← Excludes ← Do Not Cache User Agents</strong>
                                    یک خط <code dir="ltr">Googlebot</code> اضافه و ذخیره کنید.
                                </p>
                                <p>هزینه: صفحه‌ها برای گوگل‌بات هر بار ساخته می‌شوند (کمی کندتر برای ربات؛ بازدیدکننده‌ها همچنان از کش استفاده می‌کنند).</p>
                            </div>
                        </div>
                    <?php elseif ( $ls_active ) : ?>
                        <div class="hd-callout hd-callout--success">
                            <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                            <p>گوگل‌بات از کش لایت‌اسپید عبور می‌کند و هر بازدیدش ثبت می‌شود.</p>
                        </div>
                    <?php endif; ?>

                    <?php if ( ! $bot_dns && 0 === $bot_ranges['count'] ) : ?>
                        <div class="hd-callout hd-callout--danger">
                            <span class="dashicons dashicons-dismiss" aria-hidden="true"></span>
                            <p>
                                <strong>هیچ روشی برای تأیید گوگل‌بات در دسترس نیست</strong>
                                توابع DNS در این هاست غیرفعال‌اند و فهرست رسمی IPهای گوگل هم هنوز دریافت نشده (اتصال سایت به developers.google.com).
                                تا رفع یکی از این دو، هیچ خزشی ثبت نمی‌شود.
                            </p>
                        </div>
                    <?php endif; ?>

                    <ul class="in-diag">
                        <li>
                            آخرین بازدید تأییدشده گوگل‌بات:
                            <strong><?php echo $bot_diag['verified'] ? esc_html( $hodima_gi_date( $bot_diag['verified'] ) . ' (' . $hodima_gi_ago( $bot_diag['verified'] ) . ')' ) : 'هنوز ثبت نشده'; ?></strong>
                        </li>
                        <?php if ( $bot_diag['rejected'] ) : ?>
                            <li>
                                آخرین درخواست با نام گوگل‌بات که تأیید نشد:
                                <strong><?php echo esc_html( $hodima_gi_ago( $bot_diag['rejected'] ) ); ?></strong>
                                <?php if ( '' !== $bot_diag['rejected_ip'] ) : ?>
                                    از IP <code dir="ltr"><?php echo esc_html( $bot_diag['rejected_ip'] ); ?></code>
                                <?php endif; ?>
                                — ربات جعلی، یا اگر همیشه همین است، IP واقعی بازدیدکننده پشت Cloudflare/پراکسی درست خوانده نمی‌شود.
                            </li>
                        <?php endif; ?>
                        <li>
                            روش تأیید:
                            فهرست رسمی IP گوگل
                            <strong><?php echo $bot_ranges['count'] ? esc_html( sprintf( '(%s محدوده، به‌روزرسانی %s)', number_format_i18n( $bot_ranges['count'] ), $hodima_gi_ago( $bot_ranges['fetched'] ) ) ) : '(دریافت نشده)'; ?></strong>
                            · DNS معکوس: <strong><?php echo $bot_dns ? 'فعال' : 'در هاست غیرفعال'; ?></strong>
                        </li>
                    </ul>
                </div>

                <div class="in-toolbar">
                    <h2 class="in-section-title">گزارش بازدید ربات‌های گوگل</h2>
                    <div class="in-toolbar__actions">
                        <button type="button" class="in-btn in-btn-outline btn-export" data-type="crawls"><span class="dashicons dashicons-download" aria-hidden="true"></span> خروجی CSV</button>
                        <button type="button" class="in-btn in-btn-danger" id="c-crawls">پاکسازی گزارش خزش</button>
                    </div>
                </div>

                <div class="in-table-wrap">
                    <table class="in-table">
                        <thead><tr><th class="col-url">آدرس</th><th>بازدیدها</th><th>آخرین بازدید گوگل‌بات</th><th>آخرین ارسال به گوگل</th><th>واکنش گوگل</th></tr></thead>
                        <tbody>
                            <?php if ( empty( $crawls ) ) : ?>
                                <tr><td colspan="5" class="in-empty">هنوز هیچ بازدید تأییدشده‌ای از گوگل‌بات ثبت نشده. علت‌های احتمالی در کادر بالا آمده است.</td></tr>
                            <?php else : foreach ( $crawls as $c ) :
                                $crawled_ts = $hodima_gi_ts( $c['last_crawled_at'] );
                                $pinged_ts  = $hodima_gi_ts( $c['last_modified_at'] ?? null );
                                $sync       = (int) ( $c['api_sync_status'] ?? 0 );
                                ?>
                                <tr>
                                    <td class="in-cell-url"><?php echo esc_html( rawurldecode( (string) $c['url_path'] ) ); ?></td>
                                    <td class="in-cell-center"><span class="in-badge in-badge-queue"><?php echo esc_html( number_format_i18n( (int) $c['crawl_count'] ) ); ?></span></td>
                                    <td><?php echo esc_html( $hodima_gi_date( $crawled_ts ) ); ?><small class="in-ago"><?php echo esc_html( $hodima_gi_ago( $crawled_ts ) ); ?></small></td>
                                    <td><?php echo esc_html( $hodima_gi_date( $pinged_ts ) ); ?></td>
                                    <td>
                                        <?php if ( 2 === $sync && null !== $c['reaction_time'] ) : ?>
                                            <span class="hd-pill hd-pill--ok"><?php echo esc_html( $hodima_gi_minutes( (int) $c['reaction_time'] ) ); ?> پس از ارسال</span>
                                        <?php elseif ( 1 === $sync ) : ?>
                                            <span class="hd-pill hd-pill--warn">ارسال شد، منتظر خزش</span>
                                        <?php else : ?>
                                            <span class="in-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php hodima_gi_render_pagination($paged_cr, $crawls_pages, 'paged_cr'); ?>
            </div>
        </div>

        <div class="in-tab-content" id="in-tab-st">
            <div class="in-section">
                <h2 class="in-section-title">احیای محتوای راکد (Stale Content)</h2>
                <p>
                    مطالب و محصولاتی که <strong>بیش از <?php echo esc_html( number_format_i18n( (int) $stale_days ) ); ?> روز</strong> به‌روزرسانی نشده‌اند
                    (<?php echo esc_html( number_format_i18n( (int) $stale_data['total'] ) ); ?> مورد). برای حفظ رتبه آن‌ها را به‌روز کنید.
                    صفحه اصلی، برگه‌های سیستمی ووکامرس و صفحه‌های noindex در این فهرست نیستند.
                </p>
                <ul class="in-hint in-legend">
                    <li><strong>ویرایش و احیا:</strong> ویرایشگر با تیک «ارسال سیگنال به گوگل» باز می‌شود؛ محتوا را به‌روز و ذخیره کنید تا نسخه جدید به گوگل اعلام شود.</li>
                    <li><strong>ارسال به گوگل:</strong> بدون ویرایش، آدرس به صف گوگل اضافه می‌شود (از سهمیه روزانه کم می‌کند).</li>
                </ul>
                <?php if ( ! empty( $stale_posts ) ) : ?>
                    <div class="in-toolbar">
                        <button type="button" class="in-btn in-btn-primary" id="st-queue-sel"><span class="dashicons dashicons-google" aria-hidden="true"></span> ارسال موارد انتخابی به گوگل</button>
                    </div>
                <?php endif; ?>
                <div class="in-table-wrap">
                    <table class="in-table">
                        <thead><tr><th class="in-col-check"><input type="checkbox" id="st-sel-all" aria-label="انتخاب همه"></th><th>عنوان محتوا</th><th>نوع</th><th>آخرین به‌روزرسانی</th><th>آخرین بازدید گوگل‌بات</th><th>عملیات</th></tr></thead>
                        <tbody>
                            <?php if ( empty( $stale_posts ) ) : ?>
                                <tr><td colspan="6" class="in-empty in-empty--ok">عالی! هیچ محتوای راکدی یافت نشد.</td></tr>
                            <?php else : foreach ( $stale_posts as $p ) :
                                $pid        = (int) $p['ID'];
                                $type_obj   = get_post_type_object( (string) $p['post_type'] );
                                $mod_ts     = $hodima_gi_ts( $p['post_modified_gmt'] ?? null, true ) ?: $hodima_gi_ts( $p['post_modified'] );
                                $crawl_hash = Hodima_GI_Helper::url_hash( $stale_urls[ $pid ] ?? '' );
                                $crawl_ts   = $hodima_gi_ts( $stale_crawls[ $crawl_hash ] ?? null );
                                $edit_link  = get_edit_post_link( $pid, 'raw' );
                                ?>
                                <tr>
                                    <td><input type="checkbox" class="st-chk" value="<?php echo esc_attr( (string) $pid ); ?>" aria-label="<?php echo esc_attr( 'انتخاب ' . $p['post_title'] ); ?>"></td>
                                    <td><a href="<?php echo esc_url( get_permalink( $pid ) ); ?>" target="_blank" rel="noopener"><strong><?php echo esc_html( '' !== $p['post_title'] ? $p['post_title'] : '(بدون عنوان)' ); ?></strong></a></td>
                                    <td class="in-cell-center"><span class="in-badge in-badge-queue"><?php echo esc_html( $type_obj ? $type_obj->labels->singular_name : $p['post_type'] ); ?></span></td>
                                    <td><?php echo esc_html( $hodima_gi_date( $mod_ts ) ); ?><small class="in-ago"><?php echo esc_html( $hodima_gi_ago( $mod_ts ) ); ?></small></td>
                                    <td><?php echo $crawl_ts ? esc_html( $hodima_gi_ago( $crawl_ts ) ) : '<span class="in-muted">ثبت نشده</span>'; ?></td>
                                    <td class="in-cell-actions">
                                        <?php if ( $edit_link ) : ?>
                                            <a href="<?php echo esc_url( add_query_arg( 'hodima_gi_revive', '1', $edit_link ) ); ?>" target="_blank" class="in-btn in-btn-primary">ویرایش و احیا</a>
                                        <?php endif; ?>
                                        <button type="button" class="in-btn in-btn-outline st-queue-one" data-id="<?php echo esc_attr( (string) $pid ); ?>">ارسال به گوگل</button>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php hodima_gi_render_pagination($paged_st, $stale_pages, 'paged_st'); ?>
            </div>
        </div>
        
        <div class="in-tab-content" id="in-tab-b">
            <div class="in-section">
                <h2 class="in-section-title">اجرای عملیات روی لینک‌ها</h2>
                <textarea id="b-url" rows="8" placeholder="https://..."></textarea>
                <select id="b-act" style="width:100%; max-width:600px; padding:10px; border-radius:6px; border:1px solid var(--in-border); margin:15px 0;">
                    <option value="google_update">ارسال به گوگل</option>
                    <option value="google_delete">حذف از گوگل</option>
                    <?php // برچسب قبلی «و ارسال به گوگل» می‌گفت ولی این گزینه فقط کش کلادفلر را پاک می‌کند ?>
                    <option value="cf_purge">پاکسازی کش Cloudflare</option>
                </select>
                <div><button type="button" class="in-btn in-btn-primary" id="s-bulk">اجرا</button></div>
            </div>
            <div class="in-section" style="margin-top:20px;">
                <h2 class="in-section-title">مدیریت دیتابیس</h2>
                <p>سیستم به صورت خودکار هر شب رکوردهای قدیمی‌تر از ۶۰ روز را هرس می‌کند. در صورت نیاز به انجام فوری، از این دکمه استفاده کنید.</p>
                <?php // دکمه دستی همیشه با «معیار روزهای محتوای راکد» کار می‌کرد ولی برچسبش ثابت «۶۰ روز» بود ?>
                <button type="button" class="in-btn in-btn-outline" id="m-prune">هرس دستی دیتابیس (<?php echo esc_html( number_format_i18n( max( 7, (int) $stale_days ) ) ); ?> روز)</button>
            </div>
        </div>
        
        <div class="in-tab-content" id="in-tab-q">
            <div class="in-section">
                <h2 class="in-section-title" style="margin-bottom: 15px;">لینک‌های در انتظار ارسال به گوگل (صف فعلی)</h2>
                <div style="display:flex; gap:10px; margin-bottom:15px; align-items: center; flex-wrap: wrap;">
                    <button type="button" class="in-btn in-btn-primary" id="q-process-sel">پردازش موارد انتخابی</button>
                    <button type="button" class="in-btn in-btn-danger" id="q-delete-sel">حذف از صف</button>
                    <span style="border-left:1px solid var(--in-border); height:20px; display: inline-block;"></span>
                    <button type="button" class="in-btn in-btn-outline" id="f-q">اجرای کل صف (بدون تیک)</button>
                </div>
                <div style="overflow-x: auto;">
                    <table class="in-table">
                        <thead><tr><th style="width:30px;"><input type="checkbox" id="q-sel-all"></th><th class="col-url">لینک URL</th><th>نوع</th><th>منبع</th><th style="width: 170px;">زمان ارسال</th></tr></thead>
                        <tbody>
                            <?php if(empty($active_queue)): ?>
                                <tr><td colspan="5" style="text-align:center; font-weight: bold; color: green; padding: 20px;">عالی! در حال حاضر صف خالی است.</td></tr>
                            <?php else: foreach($active_queue as $item): ?>
                                <tr>
                                    <td><input type="checkbox" class="q-chk" value="<?php echo esc_attr($item['id']); ?>"></td>
                                    <td class="in-cell-url"><?php echo esc_html($item['url']); ?></td>
                                    <td><span class="in-badge in-badge-auto"><?php echo esc_html($item['type']); ?></span></td>
                                    <td><span class="in-badge in-badge-queue"><?php echo esc_html($item['source']); ?></span></td>
                                    <td class="in-muted">
                                        <?php echo esc_html( $hodima_gi_date( max( (int) $item['execute_at'], (int) $item['next_retry'] ) ) ); ?>
                                        <?php if ( (int) $item['retries'] > 0 ) : ?>
                                            <small class="in-ago"><?php echo esc_html( sprintf( 'تلاش مجدد %s', number_format_i18n( (int) $item['retries'] ) ) ); ?></small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php hodima_gi_render_pagination($paged_q, $queue_pages, 'paged_q'); ?>
            </div>
        </div>

        <div class="in-tab-content" id="in-tab-l">
            <div class="in-section">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 15px;">
                    <h2 class="in-section-title" style="margin:0;">تاریخچه عملیات انجام شده</h2>
                    <div>
                        <button type="button" class="in-btn in-btn-outline btn-export" data-type="logs" style="margin-right: 10px;">خروجی CSV</button>
                        <button type="button" class="in-btn in-btn-danger" id="c-l">پاکسازی تاریخچه</button>
                    </div>
                </div>
                <div style="overflow-x: auto;">
                    <table class="in-table">
                        <thead><tr><th style="width:150px;">زمان ثبت</th><th>پیام سیستم</th><th>وضعیت</th><th class="col-url">لینک / جزئیات</th></tr></thead>
                        <tbody>
                        <?php if ( empty( $logs ) ) : ?>
                            <tr><td colspan="4" class="in-empty">تاریخچه‌ای ثبت نشده است.</td></tr>
                        <?php endif; ?>
                        <?php foreach($logs as $l): ?>
                            <tr>
                                <td><?php echo esc_html( $hodima_gi_date( $hodima_gi_ts( $l['created_at'] ) ) ); ?></td>
                                <td style="font-weight:bold;"><?php echo esc_html($l['message']); ?></td>
                                <?php // خطاها قبلا هم‌رنگ آیتم‌های عادی (خاکستری) بودند ?>
                                <td><span class="in-badge <?php echo str_contains( (string) $l['status'], 'error' ) ? 'in-badge-error' : ( 'success' === $l['status'] ? 'in-badge-success' : 'in-badge-auto' ); ?>"><?php echo esc_html($l['status']); ?></span></td>
                                <td class="in-cell-url">
                                    <?php 
                                        $dec = json_decode($l['log_data'], true); 
                                        if(is_array($dec) && !empty($dec['url'])) {
                                            echo esc_html($dec['url']);
                                            if (!empty($dec['src'])) {
                                                echo ' <span style="color:var(--hodima-text-muted, #5d6785); font-size: 11px;">(' . esc_html($dec['src']) . ')</span>';
                                            }
                                        } else {
                                            echo '<code>' . esc_html($l['log_data']) . '</code>';
                                        }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php hodima_gi_render_pagination($paged_l, $logs_pages, 'paged_l'); ?>
            </div>
        </div>
        
        <div class="in-tab-content" id="in-tab-c">
            <div class="in-section">
                <h2 class="in-section-title">تنظیمات استاتیک سیستم</h2>
                <label class="in-field-label">معیار روزهای محتوای راکد (Stale Content):</label>
                <input type="number" id="c-stale" min="10" max="3650" value="<?php echo esc_attr($stale_days); ?>" style="width:100%; max-width:600px; border:1px solid var(--in-border); padding:10px; border-radius:6px; margin-bottom:15px;">

                <h2 class="in-section-title" style="margin-top:30px;">تنظیمات صف (Queue)</h2>
                <label class="in-field-label">زمان تنفس (دقیقه):</label>
                <input type="number" id="c-db" min="1" max="1440" value="<?php echo esc_attr($settings['queue_debounce_time'] ?? 15); ?>" style="width:100%; max-width:600px; border:1px solid var(--in-border); padding:10px; border-radius:6px; margin-bottom:15px;">
                <label class="in-field-label">زمان جریمه خطا (ساعت):</label>
                <input type="number" id="c-pn" min="1" max="72" value="<?php echo esc_attr($settings['queue_penalty_time'] ?? 3); ?>" style="width:100%; max-width:600px; border:1px solid var(--in-border); padding:10px; border-radius:6px;">
                
                <h2 class="in-section-title" style="margin-top:30px;">تنظیمات Cloudflare</h2>
                <label class="in-field-label">Token:</label>
                <input type="password" id="cf-tok" value="<?php echo esc_attr($settings['cf_token'] ?? ''); ?>" style="width:100%; max-width:600px; border:1px solid var(--in-border); padding:10px; border-radius:6px; margin-bottom:15px;">
                <label class="in-field-label">Zone ID:</label>
                <input type="text" id="cf-zon" value="<?php echo esc_attr($settings['cf_zone_id'] ?? ''); ?>" style="width:100%; max-width:600px; border:1px solid var(--in-border); padding:10px; border-radius:6px;">
            </div>

            <div class="in-section">
                <h2 class="in-section-title">اجرای دقیق صف: cron واقعی سرور</h2>
                <p class="in-hint">
                    WP-Cron فقط وقتی اجرا می‌شود که بازدیدی به PHP برسد. با کش صفحه لایت‌اسپید بیشتر بازدیدها
                    از کش سرو می‌شوند، پس رویدادها عقب می‌افتند و «سلامت سایت» وردپرس هشدار می‌دهد.
                    راه‌حل استاندارد، سپردن اجرا به cron خود سرور است:
                </p>
                <p class="in-hint"><strong>۱.</strong> در <code>wp-config.php</code> بالای خط «That's all, stop editing» اضافه کنید:</p>
                <pre class="in-code" dir="ltr">define( 'DISABLE_WP_CRON', true );</pre>
                <p class="in-hint"><strong>۲.</strong> در کنترل‌پنل هاست (Cron Jobs) یک کار هر ۵ دقیقه با این دستور بسازید:</p>
                <pre class="in-code" dir="ltr">wget -q -O /dev/null "<?php echo esc_html( site_url( 'wp-cron.php?doing_wp_cron' ) ); ?>" &gt;/dev/null 2&gt;&amp;1</pre>
                <p class="in-hint">
                    وضعیت فعلی:
                    <strong><?php echo $wp_cron_off ? 'WP-Cron داخلی غیرفعال است (cron سرور باید تنظیم باشد)' : 'WP-Cron داخلی فعال است'; ?></strong>
                </p>
            </div>
        </div>

        <!-- Inline script to restore active tab without flickering -->
        <script>
            (function() {
                var activeTab = 'g';
                try { activeTab = localStorage.getItem('hodima_active_tab') || 'g'; } catch (e) {}
                // تب ذخیره‌شده‌ای که دیگر وجود ندارد صفحه را کاملا خالی نشان می‌داد
                if (!document.getElementById('in-tab-' + activeTab)) activeTab = 'g';
                var btn = document.querySelector('.in-tab-btn[data-tab="' + activeTab + '"]');
                var content = document.getElementById('in-tab-' + activeTab);
                if(btn) btn.classList.add('active');
                if(content) content.classList.add('active');
            })();
        </script>
        
        <button type="submit" class="in-btn in-btn-primary" id="save-all" style="width:100%; max-width:400px; margin-top:20px; padding: 12px;">ذخیره پیکربندی</button>
    </form>

    <?php // بیرون از فرم تنظیمات: Enter در این پنجره پیکربندی را ذخیره نکند ?>
    <dialog class="hd-dialog in-key-dialog" id="g-key-dialog" aria-labelledby="g-key-title">
        <h2 class="in-section-title" id="g-key-title"><?php echo '' !== $account_email ? 'جایگزینی کلید Service Account' : 'افزودن کلید Service Account'; ?></h2>
        <p class="in-hint">
            فایل JSON کلید را از Google Cloud Console بگیرید
            (<span dir="ltr">IAM &amp; Admin ← Service Accounts ← Keys ← Add key ← JSON</span>).
            ایمیل این حساب باید در سرچ کنسول دسترسی Owner داشته باشد.
        </p>

        <div class="in-key-pick">
            <button type="button" class="in-btn in-btn-primary" id="g-key-pick"><span class="dashicons dashicons-upload" aria-hidden="true"></span> انتخاب فایل JSON</button>
            <input type="file" id="g-up" accept=".json,application/json" hidden>
            <span class="in-key-file" id="g-key-file" aria-live="polite"></span>
        </div>

        <details class="in-key-paste">
            <summary>یا محتوای فایل را بچسبانید</summary>
            <textarea id="g-json" rows="6" autocomplete="off" spellcheck="false" dir="ltr" placeholder='{"type": "service_account", ...}'></textarea>
        </details>

        <p class="in-key-preview" id="g-key-preview" role="status"></p>

        <div class="in-key-actions">
            <button type="button" class="in-btn in-btn-primary" id="g-key-save" disabled><span class="dashicons dashicons-saved" aria-hidden="true"></span> ذخیره کلید</button>
            <button type="button" class="in-btn in-btn-outline" id="g-key-cancel">انصراف</button>
        </div>
    </dialog>
</div></div>
