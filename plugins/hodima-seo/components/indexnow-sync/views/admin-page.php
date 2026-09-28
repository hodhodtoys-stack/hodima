<?php
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) exit;
/** @var string $active_tab */
?>
<div class="wrap hd-wrap hodima-wrap">

    <?php
    // هدر مشترک + تب‌ها زیر هدر (آدرس تب‌ها همان ?page=hodima-core&tab=… قبلی است)
    $hodima_in_tabs = [
        'dashboard'    => [ 'داشبورد آماری', 'dashicons-chart-bar' ],
        'ai-shield'    => [ 'سپر ربات‌های هوش مصنوعی', 'dashicons-shield' ],
        'bing-sync'    => [ 'همگام‌سازی بینگ (IndexNow)', 'dashicons-update' ],
        'aeo-exporter' => [ 'استخراج‌گر AEO', 'dashicons-download' ],
        'search-bots'  => [ 'رادار موتورهای جستجو', 'dashicons-visibility' ],
        'settings'     => [ 'تنظیمات سیستم', 'dashicons-admin-settings' ],
    ];
    $hodima_tabs = [];
    foreach ( $hodima_in_tabs as $key => [ $label, $icon ] ) {
        $hodima_tabs[ $key ] = [ 'label' => $label, 'icon' => $icon, 'url' => admin_url( 'admin.php?page=hodima-core&tab=' . $key ) ];
    }
    hodima_admin_header( [
        'title'       => 'ایندکس جهانی (AEO و GEO)',
        'description' => 'ماشین‌خوان‌ها، خزنده‌های هوشمند، سپر ربات‌های هوش مصنوعی و همگام‌سازی با بینگ.',
        'icon'        => 'dashicons-shield',
        'current'     => $active_tab,
        'tabs_label'  => 'بخش‌های ایندکس جهانی',
        'tabs'        => $hodima_tabs,
    ] );
    ?>

    <?php
    /*
     * پیام نتیجه با نشانه‌گذاری استاندارد (زیر هدر، رنگ موفق/خطا، دکمه X).
     * قبلا نوع نداشت و پیام خطای بینگ هم با ظاهر موفق نمایش داده می‌شد.
     */
    if ( isset( $_GET['msg'] ) || isset( $_GET['updated'] ) || isset( $_GET['synced'] ) || isset( $_GET['queued'] ) || isset( $_GET['test_status'] ) ) {
        $hodima_in_msg = match ( true ) {
            isset( $_GET['updated'] )                          => [ 'success', 'تنظیمات با موفقیت ذخیره شد.' ],
            isset( $_GET['synced'] )                           => [ 'success', 'درخواست همگام‌سازی با بینگ با موفقیت ارسال شد.' ],
            isset( $_GET['queued'] )                           => [ 'success', 'آدرس با موفقیت به صف انتظار افزوده شد.' ],
            ( $_GET['msg'] ?? '' ) === 'cleared'               => [ 'success', 'عملیات پاکسازی لاگ‌ها با موفقیت انجام شد.' ],
            ( $_GET['msg'] ?? '' ) === 'unbanned'              => [ 'success', 'آی‌پی مورد نظر از لیست سیاه خارج شد.' ],
            ( $_GET['test_status'] ?? '' ) === 'success'       => [ 'success', 'اتصال به API بینگ موفقیت‌آمیز بود.' ],
            ( $_GET['test_status'] ?? '' ) === 'failed'        => [ 'error', 'خطا در برقراری ارتباط با بینگ: ' . esc_html( sanitize_text_field( wp_unslash( $_GET['test_error'] ?? '' ) ) ) ],
            default                                            => [ 'success', 'عملیات با موفقیت انجام شد.' ],
        };
        hodima_admin_notice( $hodima_in_msg[1], $hodima_in_msg[0] );
    }
    ?>

    <div class="hodima-panel">

    <?php if ( $active_tab === 'dashboard' ) : ?>
        <div class="hodima-cards">
            <div class="hodima-card"><span><?php echo (int) $ai_summary['logs']; ?></span><div class="hodima-card-title">ترافیک ربات‌های هوش مصنوعی</div></div>
            <div class="hodima-card"><span><?php echo (int) $ai_summary['banned']; ?></span><div class="hodima-card-title">آی‌پی‌های مسدود شده</div></div>
            <div class="hodima-card"><span><?php echo (int) ( $queue_stats->pending ?? 0 ); ?></span><div class="hodima-card-title">صف انتظار بینگ</div></div>
            <div class="hodima-card"><span><?php echo (int) ( $queue_stats->synced ?? 0 ); ?></span><div class="hodima-card-title">درخواست‌های موفق</div></div>
            <div class="hodima-card"><span><?php echo (int) ( $queue_stats->failed ?? 0 ); ?></span><div class="hodima-card-title">درخواست‌های ناموفق</div></div>
        </div>
        <h3 style="color:#25316a; margin-top:30px;">روند فعالیت ربات‌های هوش مصنوعی (۷ روز گذشته)</h3>
        <div id="hodimaAiChart" style="min-height:120px;"
            data-labels="<?php echo esc_attr( wp_json_encode( wp_list_pluck( $ai_chart_7d, 'label' ) ) ); ?>"
            data-values="<?php echo esc_attr( wp_json_encode( wp_list_pluck( $ai_chart_7d, 'count' ) ) ); ?>"></div>
        <br>
        <?php if ( $module_status === 'disabled' ) : ?>
            <p class="hodima-warn">توجه: ماژول همگام‌سازی بینگ غیرفعال است. جهت ارسال داده‌ها آن را از تب تنظیمات روشن کنید.</p>
        <?php endif; ?>
        <?php if ( $is_locked ) : ?>
            <p class="hodima-warn">وضعیت صف: صف پردازش به دلیل محدودیت نرخ ارسال قفل شده است و به زودی آزاد خواهد شد.</p>
        <?php endif; ?>

    <?php elseif ( $active_tab === 'ai-shield' ) : ?>
        <div class="hodima-toolbar">
            <a class="hodima-btn hodima-btn-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=hodima_export_ai_logs' ), 'hodima_export_ai' ) ); ?>">دانلود لاگ‌ها (CSV)</a>
            <a class="hodima-btn hodima-btn-outline" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=hodima_clear_ai_logs' ), 'hodima_clear_ai' ) ); ?>" onclick="return confirm('آیا از پاکسازی تمامی لاگ‌های ثبت شده اطمینان دارید؟');">پاکسازی کامل لاگ‌ها</a>
        </div>
        <h3 style="color:#25316a;">ترافیک به تفکیک عامل (User-Agent)</h3>
        <table class="widefat">
            <thead><tr><th>شناسه عامل (Bot Name)</th><th>تعداد درخواست‌ها</th></tr></thead>
            <tbody>
            <?php foreach ( $ai_stats as $row ) : ?>
                <tr><td><strong><?php echo esc_html( $row['bot_name'] ); ?></strong></td><td><?php echo (int) $row['hits']; ?></td></tr>
            <?php endforeach; ?>
            <?php if ( empty( $ai_stats ) ) : ?><tr><td colspan="2" style="text-align:center;">داده‌ای برای نمایش وجود ندارد.</td></tr><?php endif; ?>
            </tbody>
        </table>

        <h3 style="color:#25316a; margin-top:40px;">فهرست سیاه آی‌پی‌ها</h3>
        <table class="widefat">
            <thead><tr><th>آدرس آی‌پی</th><th>علت مسدودی</th><th>زمان ثبت</th><th>عملیات</th></tr></thead>
            <tbody>
            <?php foreach ( $banned_ips as $b ) : ?>
                <tr>
                    <td dir="ltr" style="font-family: inherit; font-weight:bold; color:#25316a;"><?php echo esc_html( $b['ip_address'] ); ?></td>
                    <td><?php echo esc_html( $b['reason'] ?: '—' ); ?></td>
                    <td dir="ltr" style="text-align:right;"><?php echo esc_html( $b['created_at'] ); ?></td>
                    <td>
                        <a class="hodima-btn hodima-btn-secondary" style="height:30px; padding:0 12px; font-size:12px;" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=hodima_unban_ip&ip=' . urlencode( $b['ip_address'] ) ), 'hodima_unban_ip' ) ); ?>" onclick="return confirm('آیا این آدرس از فهرست سیاه خارج شود؟');">آزادسازی</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ( empty( $banned_ips ) ) : ?><tr><td colspan="4" style="text-align:center;">هیچ آدرسی در فهرست سیاه قرار ندارد.</td></tr><?php endif; ?>
            </tbody>
        </table>

    <?php elseif ( $active_tab === 'bing-sync' ) : ?>
        <div class="hodima-toolbar">
            <form method="post" style="display:inline; margin:0;">
                <?php wp_nonce_field( 'hodima_core_action' ); ?>
                <input type="hidden" name="hodima_action" value="sync_all">
                <input type="hidden" name="active_tab" value="bing-sync">
                <button type="submit" class="hodima-btn hodima-btn-primary">پردازش فوری صف</button>
            </form>
            <a class="hodima-btn hodima-btn-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=hodima_export_queue' ), 'hodima_export_queue' ) ); ?>">خروجی صف (CSV)</a>
            <a class="hodima-btn hodima-btn-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=hodima_export_history' ), 'hodima_export_history' ) ); ?>">خروجی تاریخچه (CSV)</a>
            
            <a class="hodima-btn hodima-btn-outline" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=hodima_clear_in_history' ), 'hodima_clear_inh' ) ); ?>" onclick="return confirm('تاریخچه ارسال‌ها به صورت کامل حذف خواهد شد. ادامه می‌دهید؟');">حذف تاریخچه</a>
            <a class="hodima-btn hodima-btn-outline" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=hodima_clear_in_queue' ), 'hodima_clear_inq' ) ); ?>" onclick="return confirm('تمام لینک‌های منتظر در صف حذف خواهند شد. ادامه می‌دهید؟');">لغو و تخلیه صف</a>
        </div>
        
        <form method="post" class="hodima-inline-form">
            <?php wp_nonce_field( 'hodima_core_action' ); ?>
            <input type="hidden" name="hodima_action" value="manual_sync">
            <input type="hidden" name="active_tab" value="bing-sync">
            <strong style="color:#25316a; white-space:nowrap;">ثبت لینک دستی:</strong>
            <input type="url" name="manual_url" placeholder="https://hodhodli.com/fa/..." required style="width:400px" dir="ltr">
            <button type="submit" class="hodima-btn hodima-btn-primary">افزودن به صف</button>
        </form>

        <h3 style="color:#25316a; margin-top:30px;">وضعیت صف انتظار (۱۰۰ آدرس اخیر)</h3>
        <table class="widefat">
            <thead><tr><th>مسیر (URL)</th><th>نوع پردازش</th><th>وضعیت سیستم</th><th>زمان ثبت</th></tr></thead>
            <tbody>
            <?php foreach ( $queue_pending as $row ) : ?>
                <tr>
                    <td dir="ltr" style="text-align:left; font-family: inherit;"><a href="<?php echo esc_url($row->url_path); ?>" target="_blank" style="color:#607bbd; text-decoration:none;"><?php echo esc_html( $row->url_path ); ?></a></td>
                    <td><span style="background:rgba(193, 200, 236, 0.3); color:#25316a; padding:3px 8px; border-radius:4px; font-weight:bold; font-size:12px; border:1px solid #b6c2f3;"><?php echo esc_html( $row->action ); ?></span></td>
                    <td><span style="color:#607bbd; font-weight:bold;">در حال انتظار (<?php echo esc_html( $row->status ); ?>)</span></td>
                    <td dir="ltr" style="text-align:right;"><?php echo esc_html( $row->updated_at ); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ( empty( $queue_pending ) ) : ?><tr><td colspan="4" style="text-align:center;">صف سیستم خالی است.</td></tr><?php endif; ?>
            </tbody>
        </table>

        <h3 style="color:#25316a; margin-top:40px;">سوابق پردازش (۱۰۰ رکورد اخیر)</h3>
        <table class="widefat">
            <thead><tr><th>مسیر (URL)</th><th>نوع پردازش</th><th>نتیجه عملیات</th><th>جزئیات سرور</th><th>زمان اتمام</th></tr></thead>
            <tbody>
            <?php foreach ( $queue_history as $row ) : ?>
                <tr>
                    <td dir="ltr" style="text-align:left; font-family: inherit; color:#25316a;"><?php echo esc_html( $row->url_path ); ?></td>
                    <td><span style="background:rgba(193, 200, 236, 0.3); color:#25316a; padding:3px 8px; border-radius:4px; font-weight:bold; font-size:12px; border:1px solid #b6c2f3;"><?php echo esc_html( $row->action ); ?></span></td>
                    <td>
                        <?php if($row->status === 'synced'): ?>
                            <span style="color:#25316a; font-weight:bold;">موفقیت‌آمیز</span>
                        <?php else: ?>
                            <span style="color:#607bbd; font-weight:bold;">ناموفق (<?php echo esc_html( $row->status ); ?>)</span>
                        <?php endif; ?>
                    </td>
                    <td dir="ltr" style="color:#607bbd; text-align:right; font-size:12px;"><?php echo esc_html( $row->error_message ?: 'بدون خطا' ); ?></td>
                    <td dir="ltr" style="text-align:right; font-size:13px;"><?php echo esc_html( $row->updated_at ); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ( empty( $queue_history ) ) : ?><tr><td colspan="5" style="text-align:center;">سابقه پردازشی یافت نشد.</td></tr><?php endif; ?>
            </tbody>
        </table>

    <?php elseif ( $active_tab === 'aeo-exporter' ) : ?>
        <h3 style="color:#25316a; font-size:20px; margin-bottom:5px;">استخراج‌گر و موتور کوئری (AEO Generator)</h3>
        <p style="color: #607bbd; font-size: 13px; margin-bottom: 20px;">
            جهت تولید نقشه لینک‌های نشانه‌گذاری شده (Markdown / JSON) برای ارسال به موتورهای جستجو از این ابزار استفاده نمایید.
        </p>
        
        <div class="hodima-aeo-box">
            
            <div class="hodima-aeo-row">
                <select id="hodima-aeo-filter-mode" class="hodima-aeo-select" onchange="hodimaToggleAeoInputs()">
                    <option value="24h">تغییرات ۲۴ ساعت اخیر</option>
                    <option value="limit">تعداد آخرین آپدیت‌ها</option>
                    <option value="date_range">بازه زمانی مشخص</option>
                    <option value="all">استخراج پایگاه داده (کامل)</option>
                </select>

                <input type="number" id="hodima-aeo-limit-count" class="hodima-aeo-input" placeholder="تعداد (پیش‌فرض: 50)" style="display: none;" min="1" value="50" dir="ltr">

                <div id="hodima-aeo-date-inputs" class="hodima-aeo-date-wrap" style="display: none;">
                    <span>از تاریخ:</span>
                    <input type="date" id="hodima-aeo-date-from" class="hodima-aeo-input">
                    <span>تا تاریخ:</span>
                    <input type="date" id="hodima-aeo-date-to" class="hodima-aeo-input">
                </div>

                <button type="button" class="hodima-btn hodima-btn-primary" style="margin-right: auto;" onclick="hodimaFetchAeoLinks()">
                    اجرای کوئری و استخراج
                </button>
            </div>

            <div class="hodima-aeo-options-row">
                <div class="hodima-aeo-options-group">
                    <strong>انتخاب زبان:</strong>
                    <label><input type="checkbox" id="hodima-aeo-lang-fa" checked> شناسه FA</label>
                    <label><input type="checkbox" id="hodima-aeo-lang-en" checked> شناسه EN</label>
                </div>
                <div class="hodima-aeo-options-group">
                    <strong>پسوند و نوع فایل:</strong>
                    <label><input type="checkbox" id="hodima-aeo-type-md" checked> مارک‌داون (.md)</label>
                    <label><input type="checkbox" id="hodima-aeo-type-llms" checked> نقشه متنی (llms.txt)</label>
                    <label><input type="checkbox" id="hodima-aeo-type-feed"> فید زنده (ai-feed.json)</label>
                </div>
            </div>

        </div>

        <div class="hodima-aeo-textarea-wrap">
            <textarea id="hodima-aeo-export-textarea" class="hodima-aeo-textarea" readonly placeholder="پارامترها را تنظیم کرده و روی دکمه استخراج کلیک کنید..."></textarea>
            <span id="hodima-aeo-loading" class="hodima-aeo-loading">
                در حال پردازش پایگاه داده...
            </span>
        </div>

        <div class="hodima-aeo-footer">
            <button type="button" class="hodima-btn hodima-btn-secondary" onclick="hodimaCopyAeoLinks()">انتقال نتایج به کلیپ‌بورد</button>
            <span id="hodima-aeo-copy-msg" class="hodima-aeo-msg-success">لینک‌ها با موفقیت کپی شدند.</span>
            <span id="hodima-aeo-count-msg" class="hodima-aeo-msg-count"></span>
        </div>

        <input type="hidden" id="hodima_aeo_nonce" value="<?php echo wp_create_nonce('hodima_aeo_export_nonce'); ?>">

    <?php elseif ( $active_tab === 'search-bots' ) : ?>
        <div class="hodima-toolbar">
            <a class="hodima-btn hodima-btn-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=hodima_export_search_bots' ), 'hodima_export_search_bots' ) ); ?>">دانلود گزارش CSV</a>
        </div>
        <div class="hodima-notice">
            این جدول صرفاً شامل ربات‌های موتورهای جستجو (مانند Bing و Yandex) است که احراز هویت آن‌ها از طریق Reverse DNS در سرور با موفقیت تایید شده است.
        </div>
        <table class="widefat">
            <thead><tr><th>شناسه رسمی خزنده</th><th>تعداد درخواست‌های تایید شده</th><th>زمان آخرین فعالیت</th></tr></thead>
            <tbody>
            <?php foreach ( $search_bot_logs as $row ) : ?>
                <tr>
                    <td><strong style="color:#25316a;"><?php echo esc_html( $row['bot_name'] ); ?></strong></td>
                    <td><span style="background:rgba(193, 200, 236, 0.2); padding:4px 10px; border-radius:4px; font-weight:bold; border:1px solid #b6c2f3;"><?php echo (int) $row['visit_count']; ?></span></td>
                    <td dir="ltr" style="text-align:right; font-family: inherit;"><?php echo esc_html( $row['last_visit'] ); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ( empty( $search_bot_logs ) ) : ?><tr><td colspan="3" style="text-align:center;">رکوردی یافت نشد.</td></tr><?php endif; ?>
            </tbody>
        </table>

    <?php elseif ( $active_tab === 'settings' ) : ?>
        <form method="post" class="hodima-settings-form">
            <?php
            /*
             * دکمه پیش‌فرض فرم (Enter در یک فیلد) اولین دکمه submit است. قبلا
             * «تست اتصال» بود و Enter به جای ذخیره، بینگ را تست می‌کرد. این
             * دکمه ذخیره نامرئی (نه display:none) اولین دکمه فرم است.
             */
            ?>
            <button type="submit" class="screen-reader-text" tabindex="-1" aria-hidden="true">اعمال تنظیمات</button>
            <?php wp_nonce_field( 'hodima_core_action' ); ?>
            <input type="hidden" name="hodima_action" value="save_settings">
            <input type="hidden" name="active_tab" value="settings">

            <div style="background:#ffffff; border:1px solid #b6c2f3; padding:25px; border-radius:8px; margin-bottom:25px; box-shadow:0 2px 15px rgba(37, 47, 106, 0.02);">
                <h3 style="margin-top:0; color:#25316a; border-bottom:1px dashed #b6c2f3; padding-bottom:12px;">پیکربندی IndexNow (مایکروسافت / یاندکس)</h3>
                <table class="form-table">
                    <tr>
                        <th style="width:250px;">وضعیت ماژول</th>
                        <td>
                            <select name="module_status" style="width:200px;">
                                <option value="enabled"  <?php selected( $module_status, 'enabled' ); ?>>روشن (فعال)</option>
                                <option value="disabled" <?php selected( $module_status, 'disabled' ); ?>>خاموش (غیرفعال)</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>کلید اتصال (API Key)</th>
                        <td>
                            <div style="display:flex; gap:10px; align-items:center;">
                                <input type="text" name="bing_api_key" value="<?php echo esc_attr( $bing_key ); ?>" style="width:400px; font-family: inherit;" dir="ltr">
                                <button type="submit" name="hodima_action" value="test_bing" formnovalidate class="hodima-btn hodima-btn-secondary">تست اتصال (Ping)</button>
                            </div>
                            <p class="description" style="margin-top:8px;">موقعیت فایل تاییدیه در سرور: <code dir="ltr" style="background:rgba(193, 200, 236, 0.2); padding:3px 6px; border-radius:4px; border:1px solid #b6c2f3;"><?php echo esc_html( home_url( '/' . $bing_key . '.txt' ) ); ?></code></p>
                        </td>
                    </tr>
                    <tr>
                        <th>ساختارهای محتوایی مجاز</th>
                        <td>
                            <div style="display:flex; gap:15px; flex-wrap:wrap;">
                            <?php foreach ( $content_types as $slug => $label ) : ?>
                                <label style="background:rgba(193, 200, 236, 0.1); border:1px solid #b6c2f3; padding:6px 12px; border-radius:6px; cursor:pointer; font-weight:bold;">
                                    <input type="checkbox" name="allowed_content[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $selected_content, true ) ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                </label>
                            <?php endforeach; ?>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>

            <div style="background:#ffffff; border:1px solid #b6c2f3; padding:25px; border-radius:8px; margin-bottom:25px; box-shadow:0 2px 15px rgba(37, 47, 106, 0.02);">
                <h3 style="margin-top:0; color:#25316a; border-bottom:1px dashed #b6c2f3; padding-bottom:12px;">پیکربندی ارزی و B2B</h3>
                <table class="form-table">
                    <tr>
                        <th style="width:250px;">نرخ تبدیل پایه (تومان به دلار)</th>
                        <td>
                            <input type="number" name="usd_exchange_rate" value="<?php echo esc_attr( $usd_rate ); ?>" style="width:200px;" min="1" dir="ltr">
                            <p class="description" style="margin-top:8px;">این متغیر جهت محاسبه خودکار قیمت‌های (USD) در دیتابیس ماشین‌خوان انگلیسی (EN) کاربرد دارد.</p>
                        </td>
                    </tr>
                </table>
            </div>

            <div style="background:#ffffff; border:1px solid #b6c2f3; padding:25px; border-radius:8px; margin-bottom:25px; box-shadow:0 2px 15px rgba(37, 47, 106, 0.02);">
                <h3 style="margin-top:0; color:#25316a; border-bottom:1px dashed #b6c2f3; padding-bottom:12px;">تنظیمات امنیتی و دسترسی API</h3>
                <table class="form-table">
                    <tr>
                        <th style="width:250px;">محدودیت نرخ پردازش (Rate Limit)</th>
                        <td>
                            <div style="display:flex; gap:10px; align-items:center;">
                                <input type="number" name="ai_rl_limit" value="<?php echo esc_attr( $ai_rl_limit ); ?>" min="1" style="width:100px; text-align:center;" dir="ltr">
                                <span style="color:#607bbd; font-size:13px; font-weight:bold;">درخواست مجاز در دقیقه (به‌ازای هر IP)</span>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th>شناسه امنیتی (Bearer Token)</th>
                        <td><input type="text" name="api_token" value="<?php echo esc_attr( $api_token ); ?>" style="width:400px; font-family: inherit;" dir="ltr"></td>
                    </tr>
                    <tr>
                        <th>لیست سفید خزنده‌ها (Whitelisted)</th>
                        <td>
                            <div style="display:flex; gap:15px; flex-wrap:wrap; margin-top:5px;">
                            <?php foreach ( Hodima_Bot_Shield::BOTS as $sig => $label ) : ?>
                                <label style="background:rgba(193, 200, 236, 0.1); border:1px solid #b6c2f3; padding:6px 12px; border-radius:6px; cursor:pointer; font-weight:bold;">
                                    <input type="checkbox" name="bot_<?php echo esc_attr( $sig ); ?>" <?php checked( ( $ai_bot_settings[ $sig ] ?? '1' ) === '1' ); ?>>
                                    <span style="color:#25316a;"><?php echo esc_html( $label ); ?></span> 
                                    <span style="color:#607bbd; font-size:11px; direction:ltr; display:inline-block; font-family: inherit;">(<?php echo esc_html($sig); ?>)</span>
                                </label>
                            <?php endforeach; ?>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>

            <div style="background:#ffffff; border:1px solid #b6c2f3; padding:25px; border-radius:8px; margin-bottom:25px; box-shadow:0 2px 15px rgba(37, 47, 106, 0.02);">
                <h3 style="margin-top:0; color:#25316a; border-bottom:1px dashed #b6c2f3; padding-bottom:12px;">پیکربندی رادار شبکه</h3>
                <table class="form-table">
                    <tr>
                        <th style="width:250px;">احراز هویت سرور مبدا</th>
                        <td>
                            <label style="font-weight:bold; color:#25316a; cursor:pointer;">
                                <input type="checkbox" name="verify_search_bots" <?php checked( $verify_bots ); ?>> فعال‌سازی پروتکل Reverse DNS
                            </label>
                            <p class="description" style="margin-top:8px;">این ماژول از جعل عنوان توسط خزنده‌های نامعتبر جلوگیری می‌کند.</p>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="hd-actions">
                <button type="submit" class="button button-primary">اعمال تنظیمات</button>
            </div>
        </form>
    <?php endif; ?>

    </div>
</div>