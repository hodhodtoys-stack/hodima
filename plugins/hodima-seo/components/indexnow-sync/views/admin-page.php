<?php
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) exit;
/**
 * صفحه «ایندکس جهانی» — بازطراحی ۱.۱.۷
 *
 * همه بخش‌ها با اجزای مشترک هدیما (hd-card، hd-stat، hd-field، hd-switch،
 * hd-table-wrap، hd-pill) و بدون استایل درون‌خطی. هر تب فرم و دکمه ذخیره
 * خودش را دارد. متغیرها از Hodima_Admin::render() می‌آیند.
 *
 * @var string $active_tab
 */

$hodima_tab_url = static fn( string $tab, array $args = [] ): string
    => add_query_arg( array_merge( [ 'page' => 'hodima-core', 'tab' => $tab ], $args ), admin_url( 'admin.php' ) );

// زمان به وقت سایت (و شمسی با ماژول تاریخ جلالی)
$hodima_date = static fn( int $ts ): string => $ts > 0
    ? date_i18n( 'Y/m/d H:i', $ts + wp_timezone()->getOffset( new DateTimeImmutable( '@' . $ts ) ) )
    : '—';
$hodima_ago = static fn( int $ts ): string => $ts > 0 ? human_time_diff( $ts, time() ) . ' پیش' : '';

$hodima_action_label = static fn( string $a ): string => match ( $a ) {
    'new'    => 'جدید',
    'update' => 'به‌روزرسانی',
    'del'    => 'حذف از ایندکس',
    default  => $a,
};

/** صفحه‌بندی جدول‌های صف و تاریخچه */
$hodima_pagination = static function ( array $view, string $param, string $tab, array $args = [] ) use ( $hodima_tab_url ): void {
    if ( $view['pages'] <= 1 ) {
        return;
    }
    $links = paginate_links( [
        'base'      => $hodima_tab_url( $tab, array_merge( $args, [ $param => '%#%' ] ) ),
        'format'    => '',
        'current'   => $view['page'],
        'total'     => $view['pages'],
        'prev_text' => 'قبلی',
        'next_text' => 'بعدی',
        'type'      => 'array',
    ] );
    if ( $links ) {
        echo '<nav class="hodima-pagination" aria-label="صفحه‌بندی">' . implode( '', $links ) . '</nav>'; // phpcs:ignore — خروجی paginate_links
    }
};

$hodima_tabs = [];
foreach ( Hodima_Admin::tabs() as $key => [ $label, $icon ] ) {
    $hodima_tabs[ $key ] = [ 'label' => $label, 'icon' => $icon, 'url' => $hodima_tab_url( $key ) ];
}

$hodima_nonce_field = static function (): void {
    wp_nonce_field( 'hodima_core_action' );
};
?>
<div class="wrap hd-wrap hodima-wrap">

    <?php
    hodima_admin_header( [
        'title'       => 'ایندکس جهانی (AEO و GEO)',
        'description' => 'ارسال به بینگ و یاندکس (IndexNow)، ربات‌های هوش مصنوعی، رادار موتورهای جستجو و نسخه‌های ماشین‌خوان.',
        'icon'        => 'dashicons-shield',
        'current'     => $active_tab,
        'tabs_label'  => 'بخش‌های ایندکس جهانی',
        'tabs'        => $hodima_tabs,
    ] );

    if ( isset( $_GET['msg'] ) || isset( $_GET['updated'] ) || isset( $_GET['synced'] ) || isset( $_GET['queued'] ) || isset( $_GET['test_status'] ) ) {
        $hodima_in_msg = match ( true ) {
            isset( $_GET['updated'] )                    => [ 'success', 'تنظیمات با موفقیت ذخیره شد.' ],
            isset( $_GET['synced'] )                     => [ 'success', 'صف پردازش شد؛ نتیجه هر آدرس در «تاریخچه عملیات» آمده است.' ],
            isset( $_GET['queued'] )                     => [ 'success', 'آدرس به صف انتظار افزوده شد.' ],
            ( $_GET['msg'] ?? '' ) === 'cleared'         => [ 'success', 'پاکسازی با موفقیت انجام شد.' ],
            ( $_GET['msg'] ?? '' ) === 'unbanned'        => [ 'success', 'آی‌پی مورد نظر از فهرست سیاه خارج شد.' ],
            ( $_GET['msg'] ?? '' ) === 'foreign_url'     => [ 'error', 'فقط آدرس‌های همین سایت را می‌توان به IndexNow فرستاد؛ آدرس دامنه دیگر کل دسته ارسال را در بینگ رد می‌کند.' ],
            ( $_GET['msg'] ?? '' ) === 'ls_ok'           => [ 'success', 'ربات‌ها به «Do Not Cache User Agents» لایت‌اسپید اضافه شدند و قانون .htaccess به‌روز شد. از این به بعد بازدیدشان ثبت می‌شود.' ],
            ( $_GET['msg'] ?? '' ) === 'ls_error'        => [ 'error', 'لایت‌اسپید تغییر را ذخیره نکرد. فهرست را دستی در LiteSpeed Cache ← Cache ← Excludes ← Do Not Cache User Agents وارد کنید.' ],
            ( $_GET['msg'] ?? '' ) === 'ls_unavailable'  => [ 'error', 'افزونه LiteSpeed Cache در دسترس نیست؛ فهرست را دستی وارد کنید.' ],
            ( $_GET['msg'] ?? '' ) === 'bad_key'         => [ 'error', 'تنظیمات ذخیره شد، ولی کلید API معتبر نبود و کلید قبلی حفظ شد. کلید IndexNow باید ۸ تا ۱۲۸ نویسه از حروف انگلیسی، عدد و «-» باشد.' ],
            ( $_GET['test_status'] ?? '' ) === 'success' => [ 'success', 'اتصال به IndexNow بینگ موفق بود.' ],
            ( $_GET['test_status'] ?? '' ) === 'failed'  => [ 'error', 'خطا در ارتباط با بینگ: ' . esc_html( sanitize_text_field( wp_unslash( $_GET['test_error'] ?? '' ) ) ) ],
            default                                      => [ 'success', 'عملیات با موفقیت انجام شد.' ],
        };
        hodima_admin_notice( $hodima_in_msg[1], $hodima_in_msg[0] );
    }
    ?>

    <div class="hd-body">

    <?php if ( 'dashboard' === $active_tab ) : ?>
        <?php
        $hodima_uncached = is_array( Hodima_Bot_Shield::bots_served_from_cache() ) ? count( Hodima_Bot_Shield::bots_served_from_cache() ) : 0;
        $hodima_key_ok   = (bool) preg_match( '/^[A-Za-z0-9-]{8,128}$/', $bing_key );
        $hodima_checks   = [
            [ 'enabled' === $module_status, 'ارسال به IndexNow روشن است', 'ارسال به IndexNow خاموش است', 'settings' ],
            [ $hodima_key_ok, 'کلید IndexNow معتبر است', 'کلید IndexNow نامعتبر یا خالی است', 'settings' ],
            [ 0 === (int) ( $queue_stats->failed ?? 0 ), 'ارسال ناموفقی وجود ندارد', sprintf( '%s ارسال ناموفق', number_format_i18n( (int) ( $queue_stats->failed ?? 0 ) ) ), 'history' ],
            [ ! $cache_diag['active'] || 0 === $hodima_uncached, 'ربات‌های هوش مصنوعی از کش لایت‌اسپید عبور می‌کنند', sprintf( '%s ربات هنوز از کش لایت‌اسپید جواب می‌گیرد', number_format_i18n( $hodima_uncached ) ), 'settings' ],
            [ ! $is_locked, 'صف ارسال آزاد است', 'صف به دلیل محدودیت بینگ موقتا قفل است (خودکار آزاد می‌شود)', 'queue' ],
        ];
        ?>
        <div class="hd-grid hd-grid--stats">
            <div class="hd-stat"><span class="hd-stat__label">بازدید ربات‌های هوش مصنوعی (۳۰ روز)</span><span class="hd-stat__value"><?php echo esc_html( number_format_i18n( (int) $ai_summary['logs'] ) ); ?></span></div>
            <div class="hd-stat"><span class="hd-stat__label">آی‌پی‌های مسدود</span><span class="hd-stat__value"><?php echo esc_html( number_format_i18n( (int) $ai_summary['banned'] ) ); ?></span></div>
            <div class="hd-stat"><span class="hd-stat__label">صف انتظار</span><span class="hd-stat__value"><?php echo esc_html( number_format_i18n( (int) ( $queue_stats->pending ?? 0 ) ) ); ?></span></div>
            <div class="hd-stat hodima-stat--ok"><span class="hd-stat__label">ارسال موفق</span><span class="hd-stat__value"><?php echo esc_html( number_format_i18n( (int) ( $queue_stats->synced ?? 0 ) ) ); ?></span></div>
            <div class="hd-stat hodima-stat--error"><span class="hd-stat__label">ارسال ناموفق</span><span class="hd-stat__value"><?php echo esc_html( number_format_i18n( (int) ( $queue_stats->failed ?? 0 ) ) ); ?></span></div>
        </div>

        <section class="hd-card">
                <div class="hd-card__head">
                    <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                    <h2 class="hd-card__title">وضعیت سلامت</h2>
                </div>
                <ul class="hodima-checks">
                    <?php foreach ( $hodima_checks as [ $ok, $ok_text, $bad_text, $tab ] ) : ?>
                        <li class="<?php echo $ok ? 'is-ok' : 'is-bad'; ?>">
                            <span class="dashicons <?php echo $ok ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>" aria-hidden="true"></span>
                            <span><?php echo esc_html( $ok ? $ok_text : $bad_text ); ?></span>
                            <?php if ( ! $ok ) : ?>
                                <a href="<?php echo esc_url( $hodima_tab_url( $tab ) . ( 'settings' === $tab && str_contains( $bad_text, 'لایت‌اسپید' ) ? '#hodima-cache' : '' ) ); ?>">رفع</a>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
        </section>

        <?php // تمام‌عرض: نمودار SVG با viewBox ثابت در کارت نیم‌عرض، برچسب‌های ۶ پیکسلی داشت ?>
        <section class="hd-card">
                <div class="hd-card__head">
                    <span class="dashicons dashicons-chart-area" aria-hidden="true"></span>
                    <h2 class="hd-card__title">بازدید ربات‌های هوش مصنوعی (۷ روز گذشته)</h2>
                </div>
                <div id="hodimaAiChart" class="hodima-chart"
                    data-labels="<?php echo esc_attr( wp_json_encode( wp_list_pluck( $ai_chart_7d, 'label' ) ) ); ?>"
                    data-values="<?php echo esc_attr( wp_json_encode( wp_list_pluck( $ai_chart_7d, 'count' ) ) ); ?>"></div>
        </section>

    <?php elseif ( 'queue' === $active_tab ) : ?>
        <section class="hd-card">
            <div class="hd-card__head">
                <span class="dashicons dashicons-plus-alt" aria-hidden="true"></span>
                <h2 class="hd-card__title">افزودن و پردازش</h2>
                <p class="hd-card__desc">هر نوشته، محصول یا دسته‌ای که منتشر یا ویرایش شود خودکار وارد صف می‌شود. آدرس‌ها ۳۰ دقیقه بعد از آخرین تغییر، در اجرای ساعتی بعدی به بینگ و یاندکس ارسال می‌شوند و نتیجه‌شان به «تاریخچه عملیات» می‌رود.</p>
            </div>
            <form method="post" class="hodima-row">
                <?php $hodima_nonce_field(); ?>
                <input type="hidden" name="hodima_action" value="manual_sync">
                <input type="hidden" name="active_tab" value="queue">
                <label class="screen-reader-text" for="hodima-manual-url">آدرس صفحه</label>
                <input type="url" id="hodima-manual-url" name="manual_url" placeholder="<?php echo esc_attr( home_url( '/…' ) ); ?>" required dir="ltr">
                <button type="submit" class="button">افزودن دستی به صف</button>
            </form>
            <div class="hodima-toolbar">
                <form method="post">
                    <?php $hodima_nonce_field(); ?>
                    <input type="hidden" name="hodima_action" value="sync_all">
                    <input type="hidden" name="active_tab" value="queue">
                    <button type="submit" class="button button-primary"><span class="dashicons dashicons-update" aria-hidden="true"></span> پردازش فوری صف</button>
                </form>
                <a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=hodima_export_queue' ), 'hodima_export_queue' ) ); ?>"><span class="dashicons dashicons-download" aria-hidden="true"></span> خروجی CSV</a>
                <a class="button hd-button--danger hodima-toolbar__end" data-confirm="تمام آدرس‌های منتظر در صف حذف می‌شوند و ارسال نمی‌شوند. ادامه می‌دهید؟" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=hodima_clear_in_queue' ), 'hodima_clear_inq' ) ); ?>"><span class="dashicons dashicons-trash" aria-hidden="true"></span> تخلیه صف</a>
            </div>
        </section>

        <section class="hd-card">
            <div class="hd-card__head">
                <span class="dashicons dashicons-clock" aria-hidden="true"></span>
                <h2 class="hd-card__title">صف انتظار</h2>
                <span class="hd-pill hd-pill--info"><?php echo esc_html( number_format_i18n( $queue_view['total'] ) ); ?> آدرس</span>
            </div>
            <?php if ( empty( $queue_view['rows'] ) ) : ?>
                <p class="hd-empty"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>صف خالی است؛ همه آدرس‌ها ارسال شده‌اند.</p>
            <?php else : ?>
                <div class="hd-table-wrap">
                    <table class="widefat striped">
                        <thead><tr><th>آدرس</th><th>عملیات</th><th>زمان ثبت</th></tr></thead>
                        <tbody>
                        <?php foreach ( $queue_view['rows'] as $row ) : ?>
                            <tr>
                                <td class="hodima-url"><a href="<?php echo esc_url( $row->url_path ); ?>" target="_blank" rel="noopener" dir="ltr"><?php echo esc_html( rawurldecode( (string) $row->url_path ) ); ?></a></td>
                                <td><span class="hd-pill hd-pill--info"><?php echo esc_html( $hodima_action_label( (string) $row->action ) ); ?></span></td>
                                <td><?php echo esc_html( $hodima_date( (int) $row->ts ) ); ?> <small class="hd-muted"><?php echo esc_html( $hodima_ago( (int) $row->ts ) ); ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php $hodima_pagination( $queue_view, 'qpage', 'queue' ); ?>
            <?php endif; ?>
        </section>

    <?php elseif ( 'history' === $active_tab ) : ?>
        <section class="hd-card">
            <div class="hd-card__head">
                <span class="dashicons dashicons-backup" aria-hidden="true"></span>
                <h2 class="hd-card__title">تاریخچه عملیات</h2>
                <span class="hd-pill hd-pill--info"><?php echo esc_html( number_format_i18n( $history_view['total'] ) ); ?> مورد</span>
                <p class="hd-card__desc">نتیجه ارسال هر آدرس از صف انتظار. ارسال ناموفق تا ۳ بار دوباره تلاش می‌شود (۱، ۴ و ۲۴ ساعت بعد). موارد موفق بعد از ۳۰ روز خودکار پاک می‌شوند.</p>
            </div>
            <div class="hodima-toolbar">
                <nav class="hodima-filter" aria-label="فیلتر نتیجه">
                    <?php foreach ( [ 'done' => 'همه', 'synced' => 'موفق', 'failed' => 'ناموفق' ] as $hodima_f => $hodima_f_label ) : ?>
                        <a href="<?php echo esc_url( $hodima_tab_url( 'history', 'done' === $hodima_f ? [] : [ 'status' => $hodima_f ] ) ); ?>" <?php echo $history_filter === $hodima_f ? 'aria-current="page"' : ''; ?>><?php echo esc_html( $hodima_f_label ); ?></a>
                    <?php endforeach; ?>
                </nav>
                <a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=hodima_export_history' ), 'hodima_export_history' ) ); ?>"><span class="dashicons dashicons-download" aria-hidden="true"></span> خروجی CSV</a>
                <a class="button hd-button--danger hodima-toolbar__end" data-confirm="تاریخچه ارسال‌ها به‌طور کامل حذف می‌شود. ادامه می‌دهید؟" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=hodima_clear_in_history' ), 'hodima_clear_inh' ) ); ?>"><span class="dashicons dashicons-trash" aria-hidden="true"></span> حذف تاریخچه</a>
            </div>
            <?php if ( empty( $history_view['rows'] ) ) : ?>
                <p class="hd-empty"><span class="dashicons dashicons-backup" aria-hidden="true"></span>هنوز عملیاتی ثبت نشده است.</p>
            <?php else : ?>
                <div class="hd-table-wrap">
                    <table class="widefat striped">
                        <thead><tr><th>آدرس</th><th>عملیات</th><th>نتیجه</th><th>جزئیات</th><th>زمان</th></tr></thead>
                        <tbody>
                        <?php foreach ( $history_view['rows'] as $row ) : ?>
                            <tr>
                                <td class="hodima-url" dir="ltr"><?php echo esc_html( rawurldecode( (string) $row->url_path ) ); ?></td>
                                <td><span class="hd-pill hd-pill--info"><?php echo esc_html( $hodima_action_label( (string) $row->action ) ); ?></span></td>
                                <td>
                                    <?php if ( 'synced' === $row->status ) : ?>
                                        <span class="hd-pill hd-pill--ok">موفق</span>
                                    <?php else : ?>
                                        <span class="hd-pill hd-pill--error">ناموفق</span>
                                        <?php if ( (int) $row->retries <= 3 ) : ?>
                                            <small class="hd-muted">تلاش <?php echo esc_html( number_format_i18n( (int) $row->retries ) ); ?> از ۳</small>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="hodima-detail"><?php echo esc_html( $row->error_message ?: '—' ); ?></td>
                                <td><?php echo esc_html( $hodima_date( (int) $row->ts ) ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php $hodima_pagination( $history_view, 'hpage', 'history', 'done' === $history_filter ? [] : [ 'status' => $history_filter ] ); ?>
            <?php endif; ?>
        </section>

    <?php elseif ( 'ai-shield' === $active_tab ) : ?>
        <?php
        $hodima_ls_list  = $cache_diag['active'] ? $cache_diag['agents'] : null;
        $hodima_tools    = function_exists( 'hodima_robots_blocked_tool_bots' ) ? hodima_robots_blocked_tool_bots() : [];
        ?>
        <form method="post">
            <?php $hodima_nonce_field(); ?>
            <input type="hidden" name="hodima_action" value="save_bots">
            <input type="hidden" name="active_tab" value="ai-shield">

            <section class="hd-card">
                <div class="hd-card__head">
                    <span class="dashicons dashicons-shield" aria-hidden="true"></span>
                    <h2 class="hd-card__title">ربات‌های هوش مصنوعی</h2>
                    <a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=hodima_export_ai_logs' ), 'hodima_export_ai' ) ); ?>"><span class="dashicons dashicons-download" aria-hidden="true"></span> خروجی CSV</a>
                    <a class="button hd-button--danger" data-confirm="همه لاگ‌های بازدید ربات‌ها حذف می‌شود. ادامه می‌دهید؟" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=hodima_clear_ai_logs' ), 'hodima_clear_ai' ) ); ?>"><span class="dashicons dashicons-trash" aria-hidden="true"></span> پاکسازی لاگ‌ها</a>
                    <p class="hd-card__desc">«مجاز»: در robots.txt اجازه دیدن کل سایت و نسخه‌های ماشین‌خوان (llms.txt، .md، فید) را می‌گیرد. «مسدود»: در robots.txt بسته و درخواستش با ۴۰۳ رد می‌شود. بازدیدها ۳۰ روز نگه داشته می‌شوند.</p>
                </div>
                <div class="hd-table-wrap">
                    <table class="widefat striped hodima-bots">
                        <thead><tr>
                            <th>ربات</th><th>شرکت</th><th>بازدید</th><th>آخرین بازدید</th>
                            <?php if ( null !== $hodima_ls_list ) : ?><th>کش لایت‌اسپید</th><?php endif; ?>
                            <th>مجاز</th>
                        </tr></thead>
                        <tbody>
                        <?php foreach ( Hodima_Bot_Shield::bots_by_company() as $sig ) :
                            $hodima_label   = Hodima_Bot_Shield::BOTS[ $sig ];
                            $hodima_visit   = $bot_visits[ $hodima_label ] ?? [ 'hits' => 0, 'ts' => 0 ];
                            $hodima_robots  = in_array( $sig, Hodima_Bot_Shield::ROBOTS_ONLY, true );
                            $hodima_allowed = ( $ai_bot_settings[ $sig ] ?? '1' ) === '1';
                            ?>
                            <tr class="<?php echo $hodima_allowed ? '' : 'is-blocked'; ?>">
                                <td>
                                    <strong><?php echo esc_html( $hodima_label ); ?></strong>
                                    <code dir="ltr"><?php echo esc_html( $sig ); ?></code>
                                    <?php if ( $hodima_robots ) : ?>
                                        <span class="hd-pill hd-pill--info" title="در بازدید دیده نمی‌شود؛ این کلید فقط robots.txt را تعیین می‌کند">فقط robots.txt</span>
                                    <?php elseif ( in_array( $sig, $hodima_tools, true ) ) : ?>
                                        <span class="hd-pill hd-pill--warn" title="در robots.txt همیشه در گروه ابزارهای سئو بسته است">در robots.txt بسته</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html( Hodima_Bot_Shield::COMPANIES[ $sig ] ?? '—' ); ?></td>
                                <td><?php echo $hodima_robots ? '—' : esc_html( number_format_i18n( $hodima_visit['hits'] ) ); ?></td>
                                <td><?php echo $hodima_visit['ts'] ? esc_html( $hodima_ago( $hodima_visit['ts'] ) ) : '<span class="hd-muted">—</span>'; ?></td>
                                <?php if ( null !== $hodima_ls_list ) : ?>
                                    <td>
                                        <?php if ( $hodima_robots ) : ?>
                                            <span class="hd-muted">—</span>
                                        <?php elseif ( Hodima_Bot_Shield::bot_bypasses_cache( $sig, $hodima_ls_list ) ) : ?>
                                            <span class="hd-pill hd-pill--ok">عبور از کش</span>
                                        <?php else : ?>
                                            <a class="hd-pill hd-pill--warn" href="<?php echo esc_url( $hodima_tab_url( 'settings' ) . '#hodima-cache' ); ?>">از کش</a>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                                <td>
                                    <label class="hd-toggle">
                                        <input type="checkbox" class="hd-switch" name="bot_<?php echo esc_attr( $sig ); ?>" <?php checked( $hodima_allowed ); ?>>
                                        <span class="screen-reader-text"><?php echo esc_html( 'مجاز بودن ' . $hodima_label ); ?></span>
                                    </label>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="hd-card">
                <div class="hd-card__head">
                    <span class="dashicons dashicons-dashboard" aria-hidden="true"></span>
                    <h2 class="hd-card__title">محدودیت نرخ</h2>
                </div>
                <div class="hd-fields">
                    <div class="hd-field">
                        <label class="hd-field__label" for="hodima-rl">درخواست مجاز در دقیقه (برای هر ربات از هر IP)</label>
                        <input type="number" id="hodima-rl" name="ai_rl_limit" value="<?php echo esc_attr( (string) $ai_rl_limit ); ?>" min="1" max="10000" dir="ltr">
                        <p class="hd-field__help">بیش از این، فقط همان درخواست با «۴۲۹، یک دقیقه بعد» رد می‌شود؛ IP مسدود نمی‌شود. فقط روی ربات‌هایی اجرا می‌شود که از کش لایت‌اسپید عبور کنند.</p>
                    </div>
                </div>
            </section>

            <div class="hd-actions">
                <button type="submit" class="button button-primary">ذخیره ربات‌ها</button>
            </div>
        </form>

        <section class="hd-card">
            <div class="hd-card__head">
                <span class="dashicons dashicons-lock" aria-hidden="true"></span>
                <h2 class="hd-card__title">فهرست سیاه IP</h2>
                <p class="hd-card__desc">IPهایی که به تله ربات‌ها (هانی‌پات) رسیده‌اند؛ مسدودیت پس از یک ساعت خودکار برداشته می‌شود.</p>
            </div>
            <?php if ( empty( $banned_ips ) ) : ?>
                <p class="hd-empty"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>هیچ آدرسی در فهرست سیاه نیست.</p>
            <?php else : ?>
                <div class="hd-table-wrap">
                    <table class="widefat striped">
                        <thead><tr><th>آدرس IP</th><th>علت</th><th>زمان ثبت</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ( $banned_ips as $b ) : ?>
                            <tr>
                                <td dir="ltr"><code><?php echo esc_html( $b['ip_address'] ); ?></code></td>
                                <td><?php echo esc_html( $b['reason'] ?: '—' ); ?></td>
                                <td><?php echo esc_html( $b['created_at'] ); ?></td>
                                <td><a class="button button-small" data-confirm="این آدرس از فهرست سیاه خارج شود؟" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=hodima_unban_ip&ip=' . rawurlencode( $b['ip_address'] ) ), 'hodima_unban_ip' ) ); ?>">آزادسازی</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

    <?php elseif ( 'search-bots' === $active_tab ) : ?>
        <section class="hd-card">
            <div class="hd-card__head">
                <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
                <h2 class="hd-card__title">رادار موتورهای جستجو</h2>
                <a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=hodima_export_search_bots' ), 'hodima_export_search_bots' ) ); ?>"><span class="dashicons dashicons-download" aria-hidden="true"></span> خروجی CSV</a>
                <p class="hd-card__desc">بازدیدهای تأییدشده Bingbot، YandexBot و SeznamBot. گوگل‌بات در صفحه «ایندکس گوگل» گزارش می‌شود.</p>
            </div>
            <?php if ( empty( $search_bot_logs ) ) : ?>
                <p class="hd-empty"><span class="dashicons dashicons-visibility" aria-hidden="true"></span>هنوز بازدید تأییدشده‌ای ثبت نشده است.</p>
            <?php else : ?>
                <div class="hd-table-wrap">
                    <table class="widefat striped">
                        <thead><tr><th>موتور جستجو</th><th>بازدیدهای تأییدشده</th><th>آخرین بازدید</th></tr></thead>
                        <tbody>
                        <?php foreach ( $search_bot_logs as $row ) : ?>
                            <tr>
                                <td><strong><?php echo esc_html( $row['bot_name'] ); ?></strong></td>
                                <td><?php echo esc_html( number_format_i18n( (int) $row['visit_count'] ) ); ?></td>
                                <td><?php echo esc_html( $row['last_visit'] ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <form method="post" class="hd-card">
            <?php $hodima_nonce_field(); ?>
            <input type="hidden" name="hodima_action" value="save_radar">
            <input type="hidden" name="active_tab" value="search-bots">
            <div class="hd-card__head">
                <span class="dashicons dashicons-admin-network" aria-hidden="true"></span>
                <h2 class="hd-card__title">احراز هویت ربات‌ها</h2>
            </div>
            <label class="hd-toggle">
                <input type="checkbox" class="hd-switch" name="verify_search_bots" <?php checked( $verify_bots ); ?>>
                تأیید با DNS معکوس (فقط ربات‌های واقعی شمرده شوند، نه جعلی)
            </label>
            <div class="hd-actions">
                <button type="submit" class="button button-primary">ذخیره</button>
            </div>
        </form>

    <?php elseif ( 'aeo-exporter' === $active_tab ) : ?>
        <section class="hd-card">
            <div class="hd-card__head">
                <span class="dashicons dashicons-media-code" aria-hidden="true"></span>
                <h2 class="hd-card__title">استخراج لینک‌های ماشین‌خوان</h2>
                <p class="hd-card__desc">فهرست آدرس‌های llms.txt، فایل‌های .md و فید زنده برای ارسال به موتورهای جستجو و ابزارهای هوش مصنوعی.</p>
            </div>
            <div class="hodima-row">
                <select id="hodima-aeo-filter-mode" onchange="hodimaToggleAeoInputs()" aria-label="بازه">
                    <option value="24h">تغییرات ۲۴ ساعت اخیر</option>
                    <option value="limit">تعداد آخرین به‌روزرسانی‌ها</option>
                    <option value="date_range">بازه زمانی مشخص</option>
                    <option value="all">همه محتوا</option>
                </select>
                <input type="number" id="hodima-aeo-limit-count" class="hodima-hidden" placeholder="تعداد" min="1" value="50" dir="ltr" aria-label="تعداد">
                <span id="hodima-aeo-date-inputs" class="hodima-row hodima-hidden">
                    <label>از <input type="date" id="hodima-aeo-date-from"></label>
                    <label>تا <input type="date" id="hodima-aeo-date-to"></label>
                </span>
                <button type="button" class="button button-primary" onclick="hodimaFetchAeoLinks()">استخراج</button>
            </div>
            <div class="hodima-row hodima-options">
                <span class="hd-muted">زبان:</span>
                <label><input type="checkbox" id="hodima-aeo-lang-fa" checked> فارسی (fa)</label>
                <label><input type="checkbox" id="hodima-aeo-lang-en" checked> انگلیسی (en)</label>
                <span class="hd-muted">نوع:</span>
                <label><input type="checkbox" id="hodima-aeo-type-md" checked> مارک‌داون (.md)</label>
                <label><input type="checkbox" id="hodima-aeo-type-llms" checked> llms.txt</label>
                <label><input type="checkbox" id="hodima-aeo-type-feed"> فید زنده (ai-feed.json)</label>
            </div>
            <div class="hodima-aeo-textarea-wrap">
                <textarea id="hodima-aeo-export-textarea" readonly rows="12" dir="ltr" placeholder="پارامترها را تنظیم و «استخراج» را بزنید..."></textarea>
                <span id="hodima-aeo-loading" class="hodima-aeo-loading">در حال پردازش...</span>
            </div>
            <div class="hodima-row">
                <button type="button" class="button" onclick="hodimaCopyAeoLinks()"><span class="dashicons dashicons-clipboard" aria-hidden="true"></span> کپی</button>
                <span id="hodima-aeo-copy-msg" class="hd-pill hd-pill--ok hodima-hidden">کپی شد</span>
                <span id="hodima-aeo-count-msg" class="hd-muted"></span>
            </div>
            <input type="hidden" id="hodima_aeo_nonce" value="<?php echo esc_attr( wp_create_nonce( 'hodima_aeo_export_nonce' ) ); ?>">
        </section>

        <form method="post" class="hd-card">
            <?php $hodima_nonce_field(); ?>
            <input type="hidden" name="hodima_action" value="save_aeo">
            <input type="hidden" name="active_tab" value="aeo-exporter">
            <div class="hd-card__head">
                <span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>
                <h2 class="hd-card__title">تنظیمات خروجی‌ها</h2>
            </div>
            <div class="hd-fields">
                <div class="hd-field">
                    <label class="hd-field__label" for="hodima-usd">نرخ تبدیل تومان به دلار</label>
                    <input type="number" id="hodima-usd" name="usd_exchange_rate" value="<?php echo esc_attr( (string) $usd_rate ); ?>" min="1" dir="ltr">
                    <p class="hd-field__help">قیمت دلاری نسخه‌های انگلیسی (.md و فید) با این نرخ حساب می‌شود؛ با ذخیره، همه نسخه‌ها از نو ساخته می‌شوند.</p>
                </div>
                <div class="hd-field">
                    <label class="hd-field__label" for="hodima-token">توکن گزارش آماری (Bearer Token)</label>
                    <input type="text" id="hodima-token" name="api_token" value="<?php echo esc_attr( $api_token ); ?>" dir="ltr" autocomplete="off">
                    <p class="hd-field__help">فقط برای ابزار بیرونی که آمار ربات‌ها را از <code dir="ltr">/ai-analytics.json</code> می‌خواند. اگر استفاده نمی‌کنید خالی بماند.</p>
                </div>
            </div>
            <div class="hd-actions">
                <button type="submit" class="button button-primary">ذخیره</button>
            </div>
        </form>

    <?php elseif ( 'settings' === $active_tab ) : ?>
        <form method="post" class="hd-card">
            <?php
            /* دکمه ذخیره نامرئی اول فرم: Enter در فیلد کلید = ذخیره (نه تست اتصال) */
            ?>
            <button type="submit" class="screen-reader-text" tabindex="-1" aria-hidden="true">ذخیره</button>
            <?php $hodima_nonce_field(); ?>
            <input type="hidden" name="hodima_action" value="save_settings">
            <input type="hidden" name="active_tab" value="settings">
            <div class="hd-card__head">
                <span class="dashicons dashicons-update" aria-hidden="true"></span>
                <h2 class="hd-card__title">IndexNow (بینگ و یاندکس)</h2>
            </div>
            <div class="hd-fields">
                <div class="hd-field hd-field--wide">
                    <label class="hd-toggle">
                        <input type="checkbox" class="hd-switch" name="module_enabled" <?php checked( 'enabled' === $module_status ); ?>>
                        ارسال خودکار به IndexNow
                    </label>
                </div>
                <div class="hd-field hd-field--wide">
                    <label class="hd-field__label" for="hodima-key">کلید API</label>
                    <div class="hodima-row">
                        <input type="text" id="hodima-key" name="bing_api_key" value="<?php echo esc_attr( $bing_key ); ?>" dir="ltr" class="hodima-grow">
                        <button type="submit" name="hodima_action" value="test_bing" formnovalidate class="button">تست اتصال</button>
                    </div>
                    <p class="hd-field__help">فایل تأیید: <a href="<?php echo esc_url( home_url( '/' . $bing_key . '.txt' ) ); ?>" target="_blank" rel="noopener" dir="ltr"><?php echo esc_html( home_url( '/' . $bing_key . '.txt' ) ); ?></a></p>
                </div>
                <div class="hd-field hd-field--wide">
                    <span class="hd-field__label">محتوای ارسالی</span>
                    <div class="hd-choices">
                        <?php foreach ( $content_types as $slug => $label ) : ?>
                            <label><input type="checkbox" name="allowed_content[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $selected_content, true ) ); ?>> <?php echo esc_html( $label ); ?></label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="hd-actions">
                <button type="submit" class="button button-primary">ذخیره تنظیمات</button>
            </div>
        </form>

        <?php
        /*
         * کادر کش لایت‌اسپید — به درخواست کاربر پایین آخرین تب (قبلا بالای تب
         * ربات‌ها بود و بقیه صفحه را پایین می‌راند). همیشه نمایش داده می‌شود و
         * هر حالت را صریح می‌گوید (بخش ۲۴ گزارش).
         */
        $hodima_uncached = Hodima_Bot_Shield::bots_served_from_cache() ?? [];
        $hodima_bad      = $hodima_uncached || $cache_diag['misplaced'];
        $hodima_ver      = defined( 'HODIMA_SEO_VERSION' ) ? HODIMA_SEO_VERSION : '';
        ?>
        <section class="hd-card" id="hodima-cache">
            <div class="hd-card__head">
                <span class="dashicons dashicons-performance" aria-hidden="true"></span>
                <h2 class="hd-card__title">کش لایت‌اسپید و ربات‌ها</h2>
                <?php if ( $cache_diag['active'] ) : ?>
                    <span class="hd-pill <?php echo $hodima_bad ? 'hd-pill--warn' : 'hd-pill--ok'; ?>"><?php echo $hodima_bad ? 'نیاز به تنظیم' : 'درست'; ?></span>
                <?php endif; ?>
                <p class="hd-card__desc">رباتی که از کش صفحه لایت‌اسپید جواب بگیرد به وردپرس نمی‌رسد: بازدیدش شمرده نمی‌شود و مسدودسازی و محدودیت نرخ رویش اجرا نمی‌شود.</p>
            </div>

            <?php if ( ! $cache_diag['active'] ) : ?>
                <p class="hd-text">افزونه LiteSpeed Cache روی این سایت پیدا نشد. اگر کش صفحه از طرف هاست یا Cloudflare است، ربات‌ها را در تنظیمات همان سرویس از کش مستثنا کنید.</p>
            <?php else : ?>
                <p class="hd-text">
                    فهرست فعلی <strong dir="ltr">Do Not Cache User Agents</strong>:
                    <?php if ( $cache_diag['agents'] ) : ?>
                        <code dir="ltr"><?php echo esc_html( implode( ' · ', $cache_diag['agents'] ) ); ?></code>
                    <?php else : ?>
                        <strong>خالی</strong>
                    <?php endif; ?>
                </p>
                <?php foreach ( $cache_diag['misplaced'] as $hodima_box => $hodima_items ) : ?>
                    <div class="hd-callout hd-callout--danger">
                        <span class="dashicons dashicons-warning" aria-hidden="true"></span>
                        <p><strong>کادر اشتباه</strong> <code dir="ltr"><?php echo esc_html( implode( ' · ', $hodima_items ) ); ?></code> در کادر <strong dir="ltr"><?php echo esc_html( $hodima_box ); ?></strong> نوشته شده و آنجا روی ربات‌ها اثری ندارد؛ از آنجا پاک و در <strong dir="ltr">Do Not Cache User Agents</strong> بنویسید.</p>
                    </div>
                <?php endforeach; ?>
                <?php if ( $hodima_uncached && class_exists( '\\LiteSpeed\\Conf' ) ) : ?>
                    <form method="post" class="hodima-row">
                        <?php $hodima_nonce_field(); ?>
                        <input type="hidden" name="hodima_action" value="litespeed_add_bots">
                        <input type="hidden" name="active_tab" value="settings">
                        <button type="submit" class="button button-primary"><span class="dashicons dashicons-yes" aria-hidden="true"></span> افزودن خودکار به لایت‌اسپید</button>
                        <span class="hd-field__help">همان کاری که ذخیره تنظیمات خود لایت‌اسپید انجام می‌دهد؛ فقط اضافه می‌کند و خطوط فعلی دست نمی‌خورند.</span>
                    </form>
                <?php endif; ?>
                <?php if ( $hodima_uncached ) : ?>
                    <p class="hd-text">یا دستی: این <?php echo esc_html( number_format_i18n( count( $hodima_uncached ) ) ); ?> ربات هنوز از کش جواب می‌گیرند. در <strong>LiteSpeed Cache ← Cache ← Excludes ← Do Not Cache User Agents</strong> («عامل‌های کاربر را کش نکنید»؛ نه «نقش‌ها» یا «دسته‌ها») اضافه و ذخیره کنید:</p>
                    <div class="hodima-copy">
                        <pre class="hd-code" id="hodima-cache-list" dir="ltr"><?php echo esc_html( implode( "\n", $hodima_uncached ) ); ?></pre>
                        <button type="button" class="button" data-copy="hodima-cache-list"><span class="dashicons dashicons-clipboard" aria-hidden="true"></span> کپی فهرست</button>
                    </div>
                    <p class="hd-field__help">هزینه: صفحه‌ها برای این ربات‌ها هر بار ساخته می‌شوند؛ محدودیت نرخ (تب ربات‌ها) فشار ربات‌های پرحجم را کنترل می‌کند. بازدیدکننده‌ها همچنان از کش استفاده می‌کنند.</p>
                <?php else : ?>
                    <p class="hd-text">همه ربات‌های هوش مصنوعی از کش عبور می‌کنند؛ هر بازدیدشان ثبت و تنظیمات مسدودسازی رویشان اجرا می‌شود.</p>
                <?php endif; ?>
            <?php endif; ?>
            <p class="hd-field__help">LiteSpeed Cache <?php echo esc_html( $cache_diag['version'] ?: '—' ); ?> · Hodima SEO <?php echo esc_html( $hodima_ver ); ?></p>
        </section>
    <?php endif; ?>

    </div>

    <dialog class="hd-dialog" id="hodima-confirm" aria-labelledby="hodima-confirm-text">
        <p id="hodima-confirm-text"></p>
        <div class="hodima-row hodima-row--end">
            <button type="button" class="button" value="cancel" data-close>انصراف</button>
            <a class="button hd-button--danger" id="hodima-confirm-go" href="#">تأیید</a>
        </div>
    </dialog>
</div>