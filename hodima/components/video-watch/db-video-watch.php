<?php
declare(strict_types=1);

/**
 * Video Watch Analytics System — High Performance Queue DB
 *
 * معماری صف‌محور: تعاملات (بازدید/اشتراک) ابتدا در یک جدول صف سبک ثبت می‌شوند
 * و هر ۵ دقیقه یک‌بار به‌صورت Batch در جدول آمار نهایی تجمیع می‌شوند تا فشار
 * نوشتن روی دیتابیس در ترافیک بالا به حداقل برسد.
 */
defined('ABSPATH') || exit;

define('VIDEO_WATCH_DB_VERSION', '1.2.0');
define('VIDEO_WATCH_CLEANUP_LIMIT', 5000);
define('VIDEO_WATCH_DECAY_BATCH_LIMIT', 10000);

/**
 * نوع شمارنده‌ی قابل‌ثبت برای هر ویدئو.
 * استفاده از Enum به‌جای رشته‌ی آزاد، اعتبارسنجی را در سطح نوع تضمین می‌کند
 * و از ورود مقادیر ناخواسته به لایه‌ی دیتابیس جلوگیری می‌کند.
 */
enum Video_Watch_Counter_Type: string {
    case Views  = 'views';
    case Shares = 'shares';
}

final class Video_Watch_Setup {
    private const FLUSH_EVENT = 'video_watch_flush_counters_event';
    private const DECAY_EVENT = 'video_watch_decay_popularity_event';

    public static function init(): void {
        $className = __CLASS__;
        add_filter('cron_schedules', [$className, 'add_cron_schedules']);
        add_action('admin_init', [$className, 'check_version']);
        add_action(self::FLUSH_EVENT, ['Video_Watch_Analytics', 'flush_counters']);
        add_action(self::DECAY_EVENT, [$className, 'decay_popularity']);
        add_action('delete_post', [$className, 'cleanup_on_delete_post'], 10, 2);
        // رفع نشتی کرون: در صورت خروج تم از سرویس، رویدادهای زمان‌بندی‌شده پاک‌سازی شوند
        add_action('switch_theme', [$className, 'clear_scheduled_events']);
    }

    public static function install_tables(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $p       = $wpdb->prefix;

        dbDelta("CREATE TABLE {$p}video_watch_stats (
            video_id bigint(20) unsigned NOT NULL,
            views bigint(20) unsigned NOT NULL DEFAULT 0,
            shares bigint(20) unsigned NOT NULL DEFAULT 0,
            popularity_score double NOT NULL DEFAULT 0,
            status tinyint(1) NOT NULL DEFAULT 1,
            last_updated datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (video_id),
            KEY popularity_idx (popularity_score, status)
        ) $charset;");

        dbDelta("CREATE TABLE {$p}video_watch_counter_queue (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            video_id bigint(20) unsigned NOT NULL,
            counter_type varchar(20) NOT NULL,
            delta bigint(20) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY video_counter_idx (video_id, counter_type)
        ) $charset;");

        dbDelta("CREATE TABLE {$p}video_watch_flush_lock (
            lock_name varchar(64) NOT NULL,
            locked_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (lock_name)
        ) $charset;");

        $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$p}video_watch_flush_lock (lock_name, locked_at) VALUES (%s, %s)",
            'flush_counters', '2000-01-01 00:00:00'
        ));

        update_option('video_watch_db_version', VIDEO_WATCH_DB_VERSION, false);
    }

    /**
     * علاوه بر بروزرسانی جداول در صورت تغییر نسخه، وجود هر دو رویداد Cron را
     * در هر بارگذاری پنل ادمین تضمین می‌کند (خودترمیم‌شونده در برابر پاک شدن
     * دستی جدول کرون توسط میزبان یا مهاجرت سرور).
     */
    public static function check_version(): void {
        if (version_compare(get_option('video_watch_db_version', '0'), VIDEO_WATCH_DB_VERSION, '<')) {
            self::install_tables();
        }
        self::ensure_scheduled_events();
    }

    public static function add_cron_schedules(array $schedules): array {
        $schedules['five_minutes'] = ['interval' => 300, 'display' => 'Every 5 Minutes'];
        return $schedules;
    }

    private static function ensure_scheduled_events(): void {
        if (!wp_next_scheduled(self::FLUSH_EVENT)) {
            wp_schedule_event(time(), 'five_minutes', self::FLUSH_EVENT);
        }
        // رفع باگ: این رویداد قبلاً هوک می‌شد اما هرگز زمان‌بندی نمی‌شد،
        // در نتیجه popularity_score هیچ‌وقت افت (decay) نمی‌کرد.
        if (!wp_next_scheduled(self::DECAY_EVENT)) {
            wp_schedule_event(time(), 'daily', self::DECAY_EVENT);
        }
    }

    public static function clear_scheduled_events(): void {
        foreach ([self::FLUSH_EVENT, self::DECAY_EVENT] as $hook) {
            $timestamp = wp_next_scheduled($hook);
            if ($timestamp) {
                wp_unschedule_event($timestamp, $hook);
            }
        }
    }

    public static function decay_popularity(): void {
        global $wpdb;
        $table = $wpdb->prefix . 'video_watch_stats';

        do {
            $affected = $wpdb->query($wpdb->prepare(
                "UPDATE {$table} SET popularity_score = ROUND(popularity_score * 0.95, 4) WHERE popularity_score > 0.01 LIMIT %d",
                VIDEO_WATCH_DECAY_BATCH_LIMIT
            ));
        } while ($affected === VIDEO_WATCH_DECAY_BATCH_LIMIT);

        $wpdb->query("UPDATE {$table} SET popularity_score = 0 WHERE popularity_score <= 0.01");
    }

    public static function cleanup_on_delete_post(int $post_id, ?WP_Post $post = null): void {
        $post = $post ?: get_post($post_id);
        if (!$post || $post->post_type !== 'video') {
            return;
        }

        global $wpdb;
        $p = $wpdb->prefix;
        foreach (['video_watch_stats', 'video_watch_counter_queue'] as $t) {
            $wpdb->delete($p . $t, ['video_id' => $post_id], ['%d']);
        }
    }
}
Video_Watch_Setup::init();

final class Video_Watch_Analytics {
    /**
     * تعداد کل بازدید (ثبت‌شده + در صف).
     * منبع واحد برای قالب، اسکیما و REST؛ نسخه قبلی همین کوئری را در سه
     * فایل جداگانه تکرار می‌کرد.
     */
    public static function get_total_views(int $video_id): int {

        $cache_key = 'hvw_total_views_calc_' . $video_id;
        $cached    = get_transient($cache_key);

        if (false !== $cached) {
            return (int) $cached;
        }

        global $wpdb;
        $stats_table = $wpdb->prefix . 'video_watch_stats';
        $queue_table = $wpdb->prefix . 'video_watch_counter_queue';

        $views = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE((SELECT views FROM {$stats_table} WHERE video_id = %d), 0) +
                    COALESCE((SELECT SUM(delta) FROM {$queue_table} WHERE video_id = %d AND counter_type = 'views'), 0)",
            $video_id, $video_id
        ));

        set_transient($cache_key, $views, 20);

        return $views;
    }

    /**
     * آیا این بازدیدکننده امروز همین تعامل را قبلا ثبت کرده؟
     *
     * نسخه قبلی هیچ تشخیص تکراری نداشت: هر بار بارگذاری مجدد صفحه و پخش
     * ۱۵٪ ویدیو یک بازدید جدید بود. همین عدد به عنوان userInteractionCount
     * در اسکیما به گوگل گزارش می‌شد.
     */
    public static function is_duplicate(int $video_id, Video_Watch_Counter_Type $type, string $client_ip): bool {

        $ua  = isset($_SERVER['HTTP_USER_AGENT']) ? (string) wp_unslash($_SERVER['HTTP_USER_AGENT']) : '';
        $key = 'hvw_seen_' . md5($video_id . '|' . $type->value . '|' . $client_ip . '|' . $ua . '|' . gmdate('Y-m-d'));

        if (get_transient($key)) {
            return true;
        }

        set_transient($key, 1, DAY_IN_SECONDS);
        return false;
    }

    public static function record_interaction(int $video_id, Video_Watch_Counter_Type $type): void {
        global $wpdb;
        $table = $wpdb->prefix . 'video_watch_counter_queue';
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table} (video_id, counter_type, delta, created_at) VALUES (%d, %s, 1, %s) ON DUPLICATE KEY UPDATE delta = delta + 1",
            $video_id, $type->value, gmdate('Y-m-d H:i:s')
        ));
    }

    public static function flush_counters(): void {
        global $wpdb;
        $lock_table  = $wpdb->prefix . 'video_watch_flush_lock';
        $queue_table = $wpdb->prefix . 'video_watch_counter_queue';
        $stats_table = $wpdb->prefix . 'video_watch_stats';

        $wpdb->query('START TRANSACTION');
        try {
            $lock = $wpdb->get_row($wpdb->prepare("SELECT locked_at FROM {$lock_table} WHERE lock_name = %s FOR UPDATE", 'flush_counters'));
            if (!$lock || (time() - strtotime($lock->locked_at . ' UTC') < 290)) {
                $wpdb->query('ROLLBACK');
                return;
            }

            $now = gmdate('Y-m-d H:i:s');
            $wpdb->query($wpdb->prepare("UPDATE {$lock_table} SET locked_at = %s WHERE lock_name = %s", $now, 'flush_counters'));

            // رفع باگ Race Condition: بدون FOR UPDATE، اگر بین SELECT و DELETE یک
            // درخواست ثبت تعامل جدید دقیقاً روی همان ردیف صف (ON DUPLICATE KEY UPDATE)
            // اجرا می‌شد، delta آن به‌طور بی‌صدا حذف می‌شد و در آمار نهایی منظور نمی‌گشت.
            // با قفل کردن ردیف‌های انتخابی در همین تراکنش، چنین نوشتنی تا COMMIT معطل می‌ماند.
            $rows = $wpdb->get_results("SELECT id, video_id, counter_type, delta FROM {$queue_table} LIMIT 500 FOR UPDATE", ARRAY_A);
            if (empty($rows)) {
                $wpdb->query('COMMIT');
                return;
            }

            $grouped = [];
            $ids     = [];
            foreach ($rows as $row) {
                $grouped[(int) $row['video_id']][(string) $row['counter_type']] =
                    ($grouped[(int) $row['video_id']][(string) $row['counter_type']] ?? 0) + (int) $row['delta'];
                $ids[] = (int) $row['id'];
            }

            $value_parts  = [];
            $value_params = [];
            foreach ($grouped as $vid => $counters) {
                $v   = $counters[Video_Watch_Counter_Type::Views->value] ?? 0;
                $s   = $counters[Video_Watch_Counter_Type::Shares->value] ?? 0;
                $pop = ($v * 4.0) + ($s * 2.0);
                $value_parts[] = '(%d, %d, %d, %f, %s)';
                array_push($value_params, $vid, $v, $s, $pop, $now);
            }

            $wpdb->query($wpdb->prepare(
                "INSERT INTO {$stats_table} (video_id, views, shares, popularity_score, last_updated) VALUES " . implode(', ', $value_parts) . "
                ON DUPLICATE KEY UPDATE
                    views = views + VALUES(views), shares = shares + VALUES(shares), popularity_score = popularity_score + (VALUES(views) * 4.0) + (VALUES(shares) * 2.0), last_updated = VALUES(last_updated)",
                ...$value_params
            ));

            $wpdb->query($wpdb->prepare("DELETE FROM {$queue_table} WHERE id IN (" . rtrim(str_repeat('%d,', count($ids)), ',') . ")", ...$ids));
            $wpdb->query('COMMIT');
        } catch (Throwable $e) {
            $wpdb->query('ROLLBACK');
            // رفع مشکل مشاهده‌پذیری: خطای Flush قبلاً کاملاً بی‌صدا نادیده گرفته می‌شد
            error_log('[video-watch] flush_counters failed: ' . $e->getMessage());
        }
        // رفع باگ مهم: بلوک finally قبلی، صرف‌نظر از موفقیت یا شکست، locked_at را
        // به تاریخ خیلی قدیمی (۲۰۰۰) بازمی‌گرداند. این کار عملاً کل مکانیزم قفل/کول‌داون
        // ۲۹۰ ثانیه‌ای بالا (خط ۱۶۹) را بی‌اثر می‌کرد: بلافاصله بعد از هر Flush موفق،
        // فراخوانی بعدی (مثلاً از طریق REST endpoint دستی /cron) بدون هیچ تأخیری دوباره
        // اجرا می‌شد. با حذف این ریست، در صورت موفقیت مقدار locked_at همان $now
        // (که در خط ۱۷۵ درون تراکنش ثبت و Commit شده) باقی می‌ماند، و در صورت شکست،
        // ROLLBACK به‌خودی‌خود مقدار قبلی را بازمی‌گرداند — نیازی به ریست دستی نیست.
    }
}

add_action('rest_api_init', function (): void {
    register_rest_route('video-watch/v1', '/track', [
        'methods'             => 'POST',
        'callback'            => function (WP_REST_Request $request) {
            $nonce = (string) $request->get_header('X-HVW-Nonce');
            if (!wp_verify_nonce($nonce, 'hvw_track_action')) {
                return new WP_Error('forbidden', __('دسترسی غیرمجاز. توکن امنیتی معتبر نیست.', 'hod-video'), ['status' => 403]);
            }

            /*
             * IP واقعی با تابع مرکزی قالب.
             * نسخه قبلی به هدرهای CF-Connecting-IP و X-Forwarded-For از *هر*
             * درخواستی اعتماد می‌کرد؛ این‌ها هدرهای عادی‌اند که هر کلاینتی
             * می‌تواند بفرستد. با عوض کردن مقدارشان در هر درخواست، محدودیت نرخ
             * دور زده و تعداد بازدید به دلخواه بالا برده می‌شد.
             */
            $client_ip = function_exists('hodima_get_client_ip')
                ? hodima_get_client_ip()
                : sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? ''));

            if ('' !== $client_ip) {
                $rate_key = function_exists('hodima_rate_limit_key') ? hodima_rate_limit_key('hvw_rate_') : 'hvw_rate_' . md5($client_ip);
                $request_count = (int) get_transient($rate_key);
                if ($request_count > 45) {
                    return new WP_Error('rate_limit_exceeded', __('تعداد درخواست‌ها بیش از حد مجاز است.', 'hod-video'), ['status' => 429]);
                }
                set_transient($rate_key, $request_count + 1, 60);
            }

            $video_id     = (int) $request->get_param('id');
            $counter_type = Video_Watch_Counter_Type::tryFrom((string) $request->get_param('action'));

            if (!$video_id || get_post_type($video_id) !== 'video' || !$counter_type) {
                return new WP_Error('invalid_data', __('درخواست نامعتبر است.', 'hod-video'), ['status' => 422]);
            }

            if (!Video_Watch_Analytics::is_duplicate($video_id, $counter_type, $client_ip)) {
                Video_Watch_Analytics::record_interaction($video_id, $counter_type);
                delete_transient('hvw_total_views_calc_' . $video_id);
            }

            $total_views = Video_Watch_Analytics::get_total_views($video_id);

            return rest_ensure_response(['success' => true, 'views' => $total_views]);
        },
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('video-watch/v1', '/nonce', [
        'methods'             => 'GET',
        'callback'            => function () {
            /*
             * این پاسخ نباید کش شود. لایت‌اسپید به صورت پیش‌فرض پاسخ‌های GET
             * رابط REST را کش می‌کند؛ نانس کش‌شده پس از انقضا به همه
             * بازدیدکنندگان داده می‌شد و ثبت بازدید با ۴۰۳ شکست می‌خورد.
             */
            nocache_headers();
            do_action('litespeed_control_set_nocache', 'video-watch nonce');
            $response = rest_ensure_response(['nonce' => wp_create_nonce('hvw_track_action')]);
            $response->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            return $response;
        },
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('video-watch/v1', '/cron', [
        'methods'             => 'GET',
        'callback'            => function (WP_REST_Request $request) {
            // در صورت تعریف کلید امنیتی در wp-config.php، فراخوانی بدون کلید معتبر رد می‌شود (اختیاری و سازگار با نسخه قبل)
            if (defined('HOD_VIDEO_WATCH_CRON_KEY') && HOD_VIDEO_WATCH_CRON_KEY !== '') {
                if (!hash_equals((string) HOD_VIDEO_WATCH_CRON_KEY, (string) $request->get_param('key'))) {
                    return new WP_Error('forbidden', __('دسترسی غیرمجاز.', 'hod-video'), ['status' => 403]);
                }
            }
            Video_Watch_Analytics::flush_counters();
            return rest_ensure_response(['success' => true, 'message' => 'Queue processed.']);
        },
        'permission_callback' => '__return_true',
    ]);
});
