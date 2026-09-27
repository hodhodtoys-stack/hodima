<?php
/**
 * Hodima Database
 * Hodima Stories Analytics System - Integrated Theme Module
 */

declare(strict_types=1);

namespace Hodima\Stories\Analytics;

defined('ABSPATH') || exit;

defined('WP_STORY_DB_VERSION')                    || define('WP_STORY_DB_VERSION', '4.1.0');
defined('WP_STORY_INTERACTION_RETENTION_MONTHS') || define('WP_STORY_INTERACTION_RETENTION_MONTHS', 6);
defined('WP_STORY_COUNTER_FLUSH_LIMIT')          || define('WP_STORY_COUNTER_FLUSH_LIMIT', 500);
defined('WP_STORY_CLEANUP_LIMIT')                || define('WP_STORY_CLEANUP_LIMIT', 5000);

class WP_Story_Setup {
    public static function init(): void {
        add_action('init', [__CLASS__, 'check_version']);
        add_action('wp_story_flush_counters_event', [WP_Story_Analytics::class, 'flush_counters']);
        add_action('wp_story_cleanup_interactions_event', [__CLASS__, 'cleanup_interactions']);
        add_action('wp_dashboard_setup', [__CLASS__, 'add_dashboard_widget']);
        add_action('admin_head', [__CLASS__, 'widget_styles']);
        // Bug fix: scheduled events were never cleared on theme switch (same class of
        // cron-leak bug already fixed in the Video Watch module) — now cleaned up here too.
        add_action('switch_theme', [__CLASS__, 'clear_scheduled_events']);
    }

    public static function widget_styles(): void {
        echo '<style>
            .hs-widget-container { display: flex; gap: 15px; text-align: center; margin-top: 10px; }
            .hs-widget-box { flex: 1; padding: 15px; background: #fff; border: 1px solid #c3c4c7; border-radius: 8px; }
            .hs-widget-title { margin: 0 0 10px; color: #50575e; }
            .hs-widget-value { font-size: 24px; color: #25316a; font-weight: bold; }
            .hs-widget-footer { text-align: left; margin-top: 15px; margin-bottom: 0; }
        </style>';
    }

    public static function install_tables(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $p       = $wpdb->prefix;

        dbDelta("CREATE TABLE {$p}hodima_story_stats (
            story_id bigint(20) unsigned NOT NULL,
            views bigint(20) unsigned NOT NULL DEFAULT 0,
            shares bigint(20) unsigned NOT NULL DEFAULT 0,
            status tinyint(1) NOT NULL DEFAULT 1,
            last_updated datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (story_id)
        ) $charset;");

        dbDelta("CREATE TABLE {$p}hodima_story_counter_queue (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            story_id bigint(20) unsigned NOT NULL,
            counter_type varchar(20) NOT NULL,
            delta bigint(20) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY story_counter_idx (story_id, counter_type)
        ) $charset;");

        dbDelta("CREATE TABLE {$p}hodima_story_interactions (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            story_id bigint(20) unsigned NOT NULL,
            interaction_type varchar(20) NOT NULL,
            actor_hash varchar(64) NOT NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY unique_actor_interaction_idx (story_id, interaction_type, actor_hash),
            KEY cleanup_idx (created_at)
        ) $charset;");

        dbDelta("CREATE TABLE {$p}hodima_story_flush_lock (
            lock_name varchar(64) NOT NULL,
            locked_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (lock_name)
        ) $charset;");

        $wpdb->query(
            $wpdb->prepare(
                "INSERT IGNORE INTO {$p}hodima_story_flush_lock (lock_name, locked_at) VALUES (%s, %s)",
                'flush_counters',
                '2000-01-01 00:00:00'
            )
        );

        update_option('wp_story_db_version', WP_STORY_DB_VERSION, false);
        self::ensure_scheduled_events();
    }

    public static function check_version(): void {
        $installed = get_option('wp_story_db_version', '0');
        if (version_compare($installed, WP_STORY_DB_VERSION, '<')) {
            // Bug fix: this legacy column/index drop used to run unconditionally
            // on every future version bump forever (even for sites already past
            // it), wasting a query each time. It's now gated to installs that
            // predate the version where popularity_score was removed.
            if ($installed !== '0' && version_compare($installed, '4.0.0', '<')) {
                global $wpdb;
                // Bug fix: show_errors() used to be called unconditionally,
                // which would force error output back ON even on sites that
                // had it deliberately turned off (e.g. production with
                // WP_DEBUG_DISPLAY off). suppress_errors() returns the
                // previous state so it can be restored correctly instead.
                $prev_suppress = $wpdb->suppress_errors(true);
                $wpdb->query("ALTER TABLE {$wpdb->prefix}hodima_story_stats DROP COLUMN popularity_score");
                $wpdb->query("ALTER TABLE {$wpdb->prefix}hodima_story_stats DROP INDEX popularity_idx");
                $wpdb->suppress_errors($prev_suppress);
            }
            self::install_tables();
        }
    }

    private static function ensure_scheduled_events(): void {
        if (!wp_next_scheduled('wp_story_flush_counters_event')) {
            wp_schedule_event(time(), 'daily', 'wp_story_flush_counters_event');
        } else {
            $schedule = wp_get_schedule('wp_story_flush_counters_event');
            if ($schedule !== 'daily') {
                wp_clear_scheduled_hook('wp_story_flush_counters_event');
                wp_schedule_event(time(), 'daily', 'wp_story_flush_counters_event');
            }
        }

        if (!wp_next_scheduled('wp_story_cleanup_interactions_event')) {
            wp_schedule_event(time(), 'daily', 'wp_story_cleanup_interactions_event');
        }
    }

    public static function clear_scheduled_events(): void {
        wp_clear_scheduled_hook('wp_story_flush_counters_event');
        wp_clear_scheduled_hook('wp_story_cleanup_interactions_event');
    }

    public static function cleanup_interactions(): void {
        if (!WP_Story_Analytics::tables_ready()) return;
        global $wpdb;
        $table  = $wpdb->prefix . 'hodima_story_interactions';
        $months = absint(WP_STORY_INTERACTION_RETENTION_MONTHS) ?: 6;
        $limit  = absint(WP_STORY_CLEANUP_LIMIT);
        $cutoff = gmdate('Y-m-d H:i:s', strtotime("-{$months} months"));

        for ($i = 0; $i < 5; $i++) {
            $rows = $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE created_at < %s LIMIT %d", $cutoff, $limit));
            if (!$rows) break;
        }
    }

    public static function add_dashboard_widget(): void {
        if ( current_user_can( 'manage_options' ) ) {
            wp_add_dashboard_widget('hodima_story_stats_widget', __('آمار بازدید استوری‌ها', 'hodima'), [__CLASS__, 'render_dashboard_widget']);
        }
    }

    public static function render_dashboard_widget(): void {
        global $wpdb;
        $stats_table = $wpdb->prefix . 'hodima_story_stats';

        if (!WP_Story_Analytics::tables_ready()) {
            echo '<p>' . esc_html__( 'دیتابیس استوری‌ها هنوز آماده نیست.', 'hodima' ) . '</p>';
            return;
        }

        $cached_stats = get_transient('hodima_story_dashboard_stats');
        if (false === $cached_stats) {
            $total_views  = (int) $wpdb->get_var("SELECT SUM(views) FROM {$stats_table}");
            $total_shares = (int) $wpdb->get_var("SELECT SUM(shares) FROM {$stats_table}");
            $cached_stats = [ 'views' => $total_views, 'shares' => $total_shares ];
            set_transient('hodima_story_dashboard_stats', $cached_stats, DAY_IN_SECONDS);
        }
        ?>
        <div class="hs-widget-container">
            <div class="hs-widget-box">
                <h4 class="hs-widget-title"><?php esc_html_e('کل بازدیدها', 'hodima'); ?></h4>
                <strong class="hs-widget-value"><?php echo number_format_i18n($cached_stats['views']); ?></strong>
            </div>
            <div class="hs-widget-box">
                <h4 class="hs-widget-title"><?php esc_html_e('اشتراک‌گذاری‌ها', 'hodima'); ?></h4>
                <strong class="hs-widget-value"><?php echo number_format_i18n($cached_stats['shares']); ?></strong>
            </div>
        </div>
        <p class="hs-widget-footer"><a href="<?php echo admin_url('admin.php?page=hodima-stories'); ?>" class="button button-secondary"><?php esc_html_e('مدیریت استوری‌ها', 'hodima'); ?></a></p>
        <?php
    }
}

WP_Story_Setup::init();

class WP_Story_Analytics {
    private const CACHE_GROUP = 'wp_story_analytics';
    private const CACHE_TTL   = 86400; // 24 Hours

    public static function tables_ready(): bool {
        static $ready = null;
        if ($ready !== null) return $ready;
        $ready = (bool) get_option('wp_story_db_version');
        return $ready;
    }

    public static function cleanup_deleted_stories(array $active_uids): void {
        if (!self::tables_ready()) return;
        global $wpdb;
        $p = $wpdb->prefix;

        $tables = [
            $p . 'hodima_story_stats',
            $p . 'hodima_story_counter_queue',
            $p . 'hodima_story_interactions'
        ];

        if (empty($active_uids)) {
            foreach ($tables as $t) { $wpdb->query("TRUNCATE TABLE {$t}"); }
            return;
        }

        $active_uids = array_map('absint', $active_uids);
        $placeholders = implode(',', array_fill(0, count($active_uids), '%d'));

        foreach ($tables as $t) {
            $wpdb->query($wpdb->prepare("DELETE FROM {$t} WHERE story_id NOT IN ($placeholders)", ...$active_uids));
        }
    }

    /**
     * Bug fix: record_view()/record_share() used to increment the visible
     * counter unconditionally on every AJAX call, regardless of whether the
     * interaction-dedup insert actually succeeded. The counter now only
     * moves when insert_interaction() confirms this is a genuinely new
     * (story, type, actor, day) combination.
     */
    public static function record_view(int $story_id): bool {
        $is_new = self::insert_interaction($story_id, 'view');
        if ($is_new) {
            self::increment_counter($story_id, 'views');
        }
        return $is_new;
    }

    public static function record_share(int $story_id): bool {
        $is_new = self::insert_interaction($story_id, 'share');
        if ($is_new) {
            self::increment_counter($story_id, 'shares');
        }
        return $is_new;
    }

    public static function get_stats_bulk(array $story_ids): array {
        if (empty($story_ids) || !self::tables_ready()) return [];
        $story_ids = array_values(array_unique(array_map('absint', $story_ids)));
        $story_ids = array_filter($story_ids);
        if (empty($story_ids)) return [];

        $cached_data = wp_cache_get_multiple($story_ids, self::CACHE_GROUP);
        $cached = [];
        $missing = [];

        foreach ($story_ids as $id) {
            if (!empty($cached_data[$id])) {
                $cached[$id] = $cached_data[$id];
            } else {
                $missing[] = $id;
            }
        }

        if (empty($missing)) return $cached;

        /*
         * دو کوئری جدا به جای FROM stats LEFT JOIN queue.
         *
         * سطر جدول stats فقط در فلاش *روزانه* ساخته می‌شود. نسخه قبلی از
         * stats شروع می‌کرد، پس استوری جدیدی که فقط در صف بازدید داشت اصلا
         * در نتیجه نبود؛ به عنوان «خالی» کش می‌شد و تا فلاش بعدی «۰ بازدید»
         * نشان می‌داد.
         */
        global $wpdb;
        $stats_table  = $wpdb->prefix . 'hodima_story_stats';
        $queue_table  = $wpdb->prefix . 'hodima_story_counter_queue';
        $placeholders = rtrim(str_repeat('%d,', count($missing)), ',');

        $stat_rows = $wpdb->get_results(
            $wpdb->prepare("SELECT story_id, views, shares, status, last_updated FROM {$stats_table} WHERE story_id IN ($placeholders)", ...$missing),
            ARRAY_A
        );

        $queue_rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT story_id,
                        SUM(CASE WHEN counter_type = 'views'  THEN delta ELSE 0 END) AS pending_views,
                        SUM(CASE WHEN counter_type = 'shares' THEN delta ELSE 0 END) AS pending_shares
                 FROM {$queue_table} WHERE story_id IN ($placeholders) GROUP BY story_id",
                ...$missing
            ),
            ARRAY_A
        );

        $merged = [];
        foreach ((array) $stat_rows as $r) {
            $merged[(int) $r['story_id']] = $r + ['pending_views' => 0, 'pending_shares' => 0];
        }
        foreach ((array) $queue_rows as $q) {
            $sid = (int) $q['story_id'];
            $merged[$sid] = ($merged[$sid] ?? ['story_id' => $sid, 'views' => 0, 'shares' => 0, 'status' => 1, 'last_updated' => null]);
            $merged[$sid]['pending_views']  = (int) $q['pending_views'];
            $merged[$sid]['pending_shares'] = (int) $q['pending_shares'];
        }
        $rows = array_values($merged);

        $result = $cached;
        $found = [];
        $to_cache = [];

        foreach ((array) $rows as $row) {
            $id = (int) $row['story_id'];
            $views = (int) $row['views'] + (int) $row['pending_views'];
            $shares = (int) $row['shares'] + (int) $row['pending_shares'];

            $data = [
                'story_id' => $id,
                'views' => $views,
                'shares' => $shares,
                'status' => (int) $row['status'],
                'last_updated' => $row['last_updated'],
            ];
            $to_cache[$id] = $data;
            $result[$id] = $data;
            $found[] = $id;
        }

        if (!empty($to_cache)) wp_cache_set_multiple($to_cache, self::CACHE_GROUP, self::CACHE_TTL);

        // Perf fix: stories with no views/shares yet (no row in the stats
        // table) used to be recomputed with a full DB query on every single
        // page load forever, since only "found" rows were cached. Caching
        // the empty result too is safe: increment_counter() already deletes
        // this cache key the moment a real view/share comes in.
        $unmatched = array_diff($missing, $found);
        if (!empty($unmatched)) {
            $empty_to_cache = [];
            foreach ($unmatched as $id) {
                $empty = self::empty_stats($id);
                $result[$id] = $empty;
                $empty_to_cache[$id] = $empty;
            }
            wp_cache_set_multiple($empty_to_cache, self::CACHE_GROUP, self::CACHE_TTL);
        }

        return $result;
    }

    private static function increment_counter(int $story_id, string $type): void {
        if (!self::tables_ready() || !in_array($type, ['views', 'shares'], true)) return;
        global $wpdb;
        $table = $wpdb->prefix . 'hodima_story_counter_queue';
        $wpdb->query(
            $wpdb->prepare(
                "INSERT INTO {$table} (story_id, counter_type, delta, created_at)
                 VALUES (%d, %s, 1, %s)
                 ON DUPLICATE KEY UPDATE delta = delta + 1",
                $story_id, $type, gmdate('Y-m-d H:i:s')
            )
        );

        // Bug fix: previously the per-story object-cache entry set by
        // get_stats_bulk() was only ever invalidated once a day inside
        // flush_counters(). On sites with a persistent object cache this
        // meant a freshly-cached view count could stay stale for up to 24h.
        // A single cheap cache_delete here keeps counts accurate immediately
        // (get_stats_bulk already merges queued deltas on a cache miss).
        wp_cache_delete($story_id, self::CACHE_GROUP);
    }

    public static function flush_counters(): void {
        if (!self::tables_ready()) return;
        global $wpdb;
        $lock_table = $wpdb->prefix . 'hodima_story_flush_lock';
        $queue_table = $wpdb->prefix . 'hodima_story_counter_queue';
        $stats_table = $wpdb->prefix . 'hodima_story_stats';

        $wpdb->query('START TRANSACTION');
        $lock = $wpdb->get_row($wpdb->prepare("SELECT locked_at FROM {$lock_table} WHERE lock_name = %s FOR UPDATE", 'flush_counters'));
        if (!$lock) { $wpdb->query('ROLLBACK'); return; }

        if ((time() - strtotime($lock->locked_at . ' UTC')) < 1700) { $wpdb->query('ROLLBACK'); return; }

        $now = gmdate('Y-m-d H:i:s');
        $wpdb->query($wpdb->prepare("UPDATE {$lock_table} SET locked_at = %s WHERE lock_name = %s", $now, 'flush_counters'));
        $wpdb->query('COMMIT');

        try {
            $rows = $wpdb->get_results($wpdb->prepare("SELECT id, story_id, counter_type, delta FROM {$queue_table} WHERE counter_type IN ('views', 'shares') ORDER BY id ASC LIMIT %d", absint(WP_STORY_COUNTER_FLUSH_LIMIT)), ARRAY_A);
            if (empty($rows)) return;

            $grouped = [];
            $ids = [];

            foreach ($rows as $row) {
                $sid = (int) $row['story_id'];
                $type = (string) $row['counter_type'];
                if (in_array($type, ['views', 'shares'], true)) {
                    $grouped[$sid][$type] = ($grouped[$sid][$type] ?? 0) + (int) $row['delta'];
                    $ids[] = (int) $row['id'];
                }
            }

            if (empty($grouped) || empty($ids)) return;

            $value_parts = [];
            $value_params = [];
            foreach ($grouped as $sid => $counters) {
                $v = $counters['views'] ?? 0;
                $s = $counters['shares'] ?? 0;
                $value_parts[] = '(%d, %d, %d, %s)';
                array_push($value_params, $sid, $v, $s, $now);
            }

            $placeholders = implode(', ', $value_parts);

            // Bug fix: the stats-table update and the queue-row cleanup used
            // to run as two independent, untransacted queries. If execution
            // was interrupted between them (timeout, fatal error, server
            // restart), the stats would already be updated but the queue
            // rows would survive and get counted AGAIN on the next flush —
            // silent double-counting. Wrapping both in one transaction makes
            // them succeed or fail together.
            $wpdb->query('START TRANSACTION');

            $insert_ok = $wpdb->query($wpdb->prepare(
                "INSERT INTO {$stats_table} (story_id, views, shares, last_updated)
                 VALUES {$placeholders}
                 ON DUPLICATE KEY UPDATE
                    views = views + VALUES(views), shares = shares + VALUES(shares),
                    last_updated = VALUES(last_updated)",
                ...$value_params
            ));

            if ($insert_ok === false) {
                $wpdb->query('ROLLBACK');
                return;
            }

            $id_placeholders = rtrim(str_repeat('%d,', count($ids)), ',');
            $delete_ok = $wpdb->query($wpdb->prepare("DELETE FROM {$queue_table} WHERE id IN ($id_placeholders)", ...$ids));

            if ($delete_ok === false) {
                $wpdb->query('ROLLBACK');
                return;
            }

            $wpdb->query('COMMIT');
            delete_transient('hodima_story_dashboard_stats');
            foreach (array_keys($grouped) as $sid) { wp_cache_delete($sid, self::CACHE_GROUP); }

        } finally {
            $wpdb->query($wpdb->prepare("UPDATE {$lock_table} SET locked_at = %s WHERE lock_name = %s", '2000-01-01 00:00:00', 'flush_counters'));
        }
    }

    /**
     * Bug fix: actor_hash used to be hash(sha256, uniqid(random)) — a fresh
     * random value on every call, so the unique index on
     * (story_id, interaction_type, actor_hash) could never collide and
     * dedup was a no-op. It now derives from IP + User-Agent + the current
     * UTC date, so the same visitor genuinely collides once per day per
     * story/interaction-type — matching the 24h window already used for the
     * client-side localStorage dedup.
     *
     * Note: only REMOTE_ADDR is used (not X-Forwarded-For) since that header
     * is trivially spoofable. If the site sits behind a trusted proxy/CDN
     * (e.g. Cloudflare), this should be extended to read the proxy's
     * verified client-IP header instead.
     */
    private static function get_actor_signature(): string {
        /*
         * IP واقعی بازدیدکننده پشت کلادفلر.
         * با REMOTE_ADDR همه کاربرانی که یک مدل گوشی و مرورگر داشتند و از
         * یک سرور کلادفلر وارد می‌شدند، یک امضای یکسان می‌گرفتند و روزانه
         * فقط *یک* بازدید برای همه‌شان ثبت می‌شد.
         */
        $ip = function_exists('hodima_get_client_ip')
            ? hodima_get_client_ip()
            : (isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '');
        $ua = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
        return hash('sha256', $ip . '|' . $ua . '|' . gmdate('Y-m-d'));
    }

    /**
     * Inserts an interaction row and returns whether it was genuinely new
     * (true) or a duplicate blocked by the unique key (false). The former
     * $unique parameter was accepted but never used — removed as dead code.
     */
    private static function insert_interaction(int $story_id, string $type): bool {
        if (!self::tables_ready() || !in_array($type, ['view', 'share'], true)) return false;
        global $wpdb;

        // A duplicate-key hit here is an expected, routine outcome (same
        // actor viewing the same story again the same day), not a real
        // error — suppress it so it doesn't spam debug.log on busy sites,
        // and restore whatever the previous suppression state was.
        $prev_suppress = $wpdb->suppress_errors(true);
        $inserted = $wpdb->insert(
            $wpdb->prefix . 'hodima_story_interactions',
            [
                'story_id'         => $story_id,
                'interaction_type' => $type,
                'actor_hash'       => self::get_actor_signature(),
                'created_at'       => gmdate('Y-m-d H:i:s'),
            ],
            ['%d', '%s', '%s', '%s']
        );
        $wpdb->suppress_errors($prev_suppress);

        return (bool) $inserted;
    }

    private static function empty_stats(int $story_id): array {
        return ['story_id' => $story_id, 'views' => 0, 'shares' => 0, 'status' => 1, 'last_updated' => null];
    }
}
