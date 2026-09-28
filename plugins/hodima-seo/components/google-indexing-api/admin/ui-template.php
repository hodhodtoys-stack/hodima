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

    <div id="hodima-notices"></div>

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
                 * شدن این صفحه داخل textarea چاپ می‌کرد. هر افزونه مرورگر،
                 * اشتراک صفحه، یا کش پیشخوان به آن دسترسی داشت. حالا فقط ایمیل
                 * حساب نمایش داده می‌شود و کادر برای *جایگزینی* خالی است.
                 */
                ?>
                <?php if ( '' !== $account_email ) : ?>
                    <p class="in-key-status">
                        کلید تنظیم شده برای: <code><?php echo esc_html( $account_email ); ?></code>
                        <button type="button" class="in-btn in-btn-danger in-btn-sm" id="g-key-remove">حذف کلید</button>
                    </p>
                <?php else : ?>
                    <p class="in-key-status in-key-status--missing">هنوز کلیدی تنظیم نشده است.</p>
                <?php endif; ?>
                <textarea id="g-json" rows="6" autocomplete="off" spellcheck="false" placeholder="<?php echo esc_attr( '' !== $account_email ? 'برای جایگزینی، فایل JSON جدید را اینجا قرار دهید یا بارگذاری کنید' : 'محتوای فایل JSON حساب سرویس' ); ?>"></textarea>
                <div class="in-upload-row"><input type="file" id="g-up" accept=".json,application/json"></div>
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
                <div style="margin-top:20px; display:flex; justify-content:space-between; align-items:center;">
                    <h2 class="in-section-title" style="margin:0;">گزارش زنده بازدید ربات‌های گوگل</h2>
                    <div>
                        <button type="button" class="in-btn in-btn-outline btn-export" data-type="crawls" style="margin-left: 10px;">خروجی CSV</button>
                        <button type="button" class="in-btn in-btn-danger" id="c-crawls">پاکسازی گزارش خزش</button>
                    </div>
                </div>
                
                <div style="overflow-x: auto;">
                    <table class="in-table" style="margin-top:15px;">
                        <thead><tr><th class="col-url">URL مسیر</th><th>تعداد بازدید کل</th><th>آخرین بازدید ربات</th></tr></thead>
                        <tbody>
                            <?php if(empty($crawls)): ?>
                                <tr><td colspan="3" style="text-align:center;">داده ای موجود نیست.</td></tr>
                            <?php else: foreach($crawls as $c): ?>
                                <tr>
                                    <td class="in-cell-url"><?php echo esc_html($c['url_path']); ?></td>
                                    <td style="text-align:center;"><span class="in-badge in-badge-queue"><?php echo esc_html($c['crawl_count']); ?></span></td>
                                    <td class="in-cell-light-right"><?php echo esc_html($c['last_crawled_at']); ?></td>
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
                <p>لیست زیر شامل مطالب یا محصولاتی است که <strong>بیش از <?php echo esc_html($stale_days); ?> روز</strong> به‌روزرسانی نشده‌اند و ممکن است گوگل به آن‌ها سر نزند. برای حفظ رتبه، آن‌ها را آپدیت کنید.</p>
                <div style="overflow-x: auto;">
                    <table class="in-table" style="margin-top:15px;">
                        <thead><tr><th>عنوان محتوا</th><th>نوع</th><th>آخرین بروزرسانی</th><th>عملیات</th></tr></thead>
                        <tbody>
                            <?php if(empty($stale_posts)): ?>
                                <tr><td colspan="4" style="text-align:center; color:var(--hodima-state-success, #2f7a55); font-weight:bold;">عالی! هیچ محتوای راکدی یافت نشد.</td></tr>
                            <?php else: foreach($stale_posts as $p): ?>
                                <tr>
                                    <td><strong><?php echo esc_html($p['post_title']); ?></strong></td>
                                    <td style="text-align:center;"><span class="in-badge in-badge-queue"><?php echo esc_html($p['post_type']); ?></span></td>
                                    <td style="direction:ltr; text-align:right;"><?php echo esc_html($p['post_modified']); ?></td>
                                    <td style="text-align:center;">
                                        <a href="<?php echo esc_url(get_edit_post_link((int)$p['ID'])); ?>" target="_blank" class="in-btn in-btn-primary" style="text-decoration:none;">ویرایش و احیا</a>
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
                    <option value="cf_purge">پاکسازی کش Cloudflare و ارسال به گوگل</option>
                </select>
                <div><button type="button" class="in-btn in-btn-primary" id="s-bulk">اجرا</button></div>
            </div>
            <div class="in-section" style="margin-top:20px;">
                <h2 class="in-section-title">مدیریت دیتابیس</h2>
                <p>سیستم به صورت خودکار هر شب رکوردهای قدیمی‌تر از ۶۰ روز را هرس می‌کند. در صورت نیاز به انجام فوری، از این دکمه استفاده کنید.</p>
                <button type="button" class="in-btn in-btn-outline" id="m-prune">هرس دستی دیتابیس (۶۰ روز)</button>
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
                        <thead><tr><th style="width:30px;"><input type="checkbox" id="q-sel-all"></th><th class="col-url">لینک URL</th><th>نوع</th><th>منبع</th><th style="width: 160px;">زمان اجرا</th></tr></thead>
                        <tbody>
                            <?php if(empty($active_queue)): ?>
                                <tr><td colspan="5" style="text-align:center; font-weight: bold; color: green; padding: 20px;">عالی! در حال حاضر صف خالی است.</td></tr>
                            <?php else: foreach($active_queue as $item): ?>
                                <tr>
                                    <td><input type="checkbox" class="q-chk" value="<?php echo esc_attr($item['id']); ?>"></td>
                                    <td class="in-cell-url"><?php echo esc_html($item['url']); ?></td>
                                    <td><span class="in-badge in-badge-auto"><?php echo esc_html($item['type']); ?></span></td>
                                    <td><span class="in-badge in-badge-queue"><?php echo esc_html($item['source']); ?></span></td>
                                    <td dir="ltr" style="text-align:right; color: var(--hodima-text-muted, #5d6785);"><?php echo date('H:i:s', (int)$item['execute_at']); ?></td>
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
                        <?php foreach($logs as $l): ?>
                            <tr>
                                <td class="in-cell-light-right"><?php echo esc_html($l['created_at']); ?></td>
                                <td style="font-weight:bold;"><?php echo esc_html($l['message']); ?></td>
                                <td><span class="in-badge <?php echo strpos($l['status'],'error')!==false?'in-badge-queue':'in-badge-auto'; ?>"><?php echo esc_html($l['status']); ?></span></td>
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
                <input type="number" id="c-stale" value="<?php echo esc_attr($stale_days); ?>" style="width:100%; max-width:600px; border:1px solid var(--in-border); padding:10px; border-radius:6px; margin-bottom:15px;">

                <h2 class="in-section-title" style="margin-top:30px;">تنظیمات صف (Queue)</h2>
                <label class="in-field-label">زمان تنفس (دقیقه):</label>
                <input type="number" id="c-db" value="<?php echo esc_attr($settings['queue_debounce_time'] ?? 15); ?>" style="width:100%; max-width:600px; border:1px solid var(--in-border); padding:10px; border-radius:6px; margin-bottom:15px;">
                <label class="in-field-label">زمان جریمه خطا (ساعت):</label>
                <input type="number" id="c-pn" value="<?php echo esc_attr($settings['queue_penalty_time'] ?? 3); ?>" style="width:100%; max-width:600px; border:1px solid var(--in-border); padding:10px; border-radius:6px;">
                
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
                var activeTab = localStorage.getItem('hodima_active_tab') || 'g';
                var btn = document.querySelector('.in-tab-btn[data-tab="' + activeTab + '"]');
                var content = document.getElementById('in-tab-' + activeTab);
                if(btn) btn.classList.add('active');
                if(content) content.classList.add('active');
            })();
        </script>
        
        <button type="submit" class="in-btn in-btn-primary" id="save-all" style="width:100%; max-width:400px; margin-top:20px; padding: 12px;">ذخیره پیکربندی</button>
    </form>
</div></div>
