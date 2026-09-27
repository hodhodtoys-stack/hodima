<?php
declare(strict_types=1);

if (!defined('ABSPATH')) exit;

add_action('init', function () {
    $labels = [
        'name'          => __('ویدئوها', 'hod-video'),
        'singular_name' => __('ویدئو', 'hod-video'),
        'menu_name'     => __('ویدئوها', 'hod-video'),
        'add_new'       => __('افزودن ویدئو', 'hod-video'),
        'add_new_item'  => __('افزودن ویدئو جدید', 'hod-video'),
        'edit_item'     => __('ویرایش ویدئو', 'hod-video'),
        'new_item'      => __('ویدئو جدید', 'hod-video'),
        'view_item'     => __('مشاهده ویدئو', 'hod-video'),
        'search_items'  => __('جستجوی ویدئو', 'hod-video'),
        'not_found'     => __('ویدئویی پیدا نشد', 'hod-video'),
        'all_items'     => __('همه ویدئوها', 'hod-video'),
    ];

    $args = [
        'labels'        => $labels,
        'public'        => true,
        'has_archive'   => 'videos',
        'rewrite'       => ['slug' => 'video'],
        'menu_icon'     => 'dashicons-video-alt3',
        'supports'      => ['title', 'editor', 'thumbnail', 'excerpt'],
        'show_in_rest'  => true,
        'hierarchical'  => false,
    ];

    register_post_type('video', $args);
});

add_filter('manage_video_posts_columns', function($columns) {
    $new_columns = [];
    foreach($columns as $key => $title) {
        $new_columns[$key] = $title;
        if ($key === 'title') {
            $new_columns['video_views']  = __('تعداد بازدید', 'hod-video');
            $new_columns['video_shares'] = __('تعداد اشتراک', 'hod-video');
        }
    }
    return $new_columns;
});

/**
 * رفع مشکل N+1: به‌جای یک کوئری جداگانه برای هر ردیف/هر ستون (تا ۲ کوئری در هر سطر لیست)،
 * آمار تمام ویدئوهای صفحه‌ی جاری در یک کوئری Bulk واحد پیش‌بارگذاری و در یک کش استاتیک نگه داشته می‌شود.
 */
add_filter('the_posts', function (array $posts, WP_Query $query) {
    if (is_admin() && $query->is_main_query() && $query->get('post_type') === 'video' && !empty($posts)) {
        Video_Watch_Admin_Stats_Cache::prime(wp_list_pluck($posts, 'ID'));
    }
    return $posts;
}, 10, 2);

final class Video_Watch_Admin_Stats_Cache {
    private static ?array $rows = null;

    public static function prime(array $post_ids): void {
        if (self::$rows === null) {
            self::$rows = [];
        }
        $post_ids = array_values(array_diff(array_map('intval', $post_ids), array_keys(self::$rows)));
        if (empty($post_ids)) {
            return;
        }

        global $wpdb;
        $stats_table = $wpdb->prefix . 'video_watch_stats';
        $placeholders = implode(',', array_fill(0, count($post_ids), '%d'));
        $results = $wpdb->get_results(
            $wpdb->prepare("SELECT video_id, views, shares FROM {$stats_table} WHERE video_id IN ({$placeholders})", ...$post_ids),
            ARRAY_A
        );

        foreach ($post_ids as $id) {
            self::$rows[$id] = ['views' => 0, 'shares' => 0];
        }
        foreach ((array) $results as $row) {
            self::$rows[(int) $row['video_id']] = ['views' => (int) $row['views'], 'shares' => (int) $row['shares']];
        }
    }

    public static function get(int $post_id, string $field): int {
        if (self::$rows === null || !isset(self::$rows[$post_id])) {
            self::prime([$post_id]);
        }
        return self::$rows[$post_id][$field] ?? 0;
    }
}

add_action('manage_video_posts_custom_column', function($column, $post_id) {
    if ($column === 'video_views') {
        $views = Video_Watch_Admin_Stats_Cache::get($post_id, 'views');
        echo esc_html($views ? number_format_i18n($views) : '0');
    }
    if ($column === 'video_shares') {
        $shares = Video_Watch_Admin_Stats_Cache::get($post_id, 'shares');
        echo esc_html($shares ? number_format_i18n($shares) : '0');
    }
}, 10, 2);

add_filter('manage_edit-video_sortable_columns', function($columns) {
    $columns['video_views']  = 'video_views';
    $columns['video_shares'] = 'video_shares';
    return $columns;
});

add_filter('posts_clauses', function($clauses, $query) {
    global $wpdb;
    if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== 'video') return $clauses;
    
    $orderby = $query->get('orderby');
    if (in_array($orderby, ['video_views', 'video_shares'])) {
        $stats_table = $wpdb->prefix . 'video_watch_stats';
        $clauses['join'] .= " LEFT JOIN {$stats_table} hs_stats ON {$wpdb->posts}.ID = hs_stats.video_id ";
        $sort_col = $orderby === 'video_views' ? 'views' : 'shares';
        $order = $query->get('order') ?: 'DESC';
        $clauses['orderby'] = "COALESCE(hs_stats.{$sort_col}, 0) {$order}";
    }
    return $clauses;
}, 10, 2);