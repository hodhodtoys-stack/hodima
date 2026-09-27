<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

add_action('save_post_hd_notification', 'hodima_save_notification_data');
add_action('deleted_post', 'hodima_clear_notification_cache');
add_action('trashed_post', 'hodima_clear_notification_cache');
// بازگردانی از زباله‌دان هم فهرست فعال‌ها را عوض می‌کند
add_action('untrashed_post', 'hodima_clear_notification_cache');

function hodima_clear_notification_cache(int $post_id): void {
    if (get_post_type($post_id) === 'hd_notification') {
        delete_transient('hd_active_notifications');
    }
}

function hodima_save_notification_data(int $post_id): void
{
    if (
        !isset($_POST['hodima_notification_meta_nonce']) ||
        !wp_verify_nonce(
            sanitize_text_field(wp_unslash($_POST['hodima_notification_meta_nonce'])),
            'hodima_save_notification_data'
        )
    ) {
        return;
    }

    if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($post_id)) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $fields = [
        'hd_notif_priority'           => 'priority_format', // نوع فیلتر سفارشی برای اولویت
        'hd_notif_main_link'          => 'esc_url_raw',
        'hd_notif_close_color'        => 'sanitize_hex_color',
        /*
         * رنگ پس‌زمینه. فیلد در متاباکس بود ولی در این فهرست نبود؛ رنگ
         * انتخاب‌شده هرگز ذخیره نمی‌شد و همه پاپ‌آپ‌ها سفید می‌ماندند.
         */
        'hd_notif_bg_color'           => 'sanitize_hex_color',
        'hd_notif_bg_image'           => 'esc_url_raw',
        'hd_notif_start_date'         => 'date',
        'hd_notif_end_date'           => 'date',
        'hd_notif_show_count'         => 'absint',
        'hd_notif_delay'              => 'absint',
        'hd_notif_auto_close_time'    => 'absint',
        'hd_notif_width'              => 'absint',
        'hd_notif_height'             => 'absint',
        'hd_notif_trigger_scroll'     => 'absint',
        'hd_notif_trigger_inactivity' => 'absint',
        'hd_notif_target_utm'         => 'sanitize_text_field',
        'hd_notif_target_referrer'    => 'sanitize_text_field',
    ];

    foreach ($fields as $field => $sanitize_type) {
        $raw_value = $_POST[$field] ?? '';
        $raw_value = wp_unslash($raw_value);

        $clean_value = match ($sanitize_type) {
            'esc_url_raw'        => esc_url_raw($raw_value),
            'sanitize_hex_color' => (string) (sanitize_hex_color((string) $raw_value) ?? ''),
            // فقط تاریخ معتبر YYYY-MM-DD (مقایسه تاریخ در نمایش رشته‌ای است)
            'date'               => (1 === preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $raw_value) && checkdate((int) substr((string) $raw_value, 5, 2), (int) substr((string) $raw_value, 8, 2), (int) substr((string) $raw_value, 0, 4))) ? (string) $raw_value : '',
            'absint'             => absint($raw_value),
            // تبدیل عدد به فرمت دو رقمی مثل 01 یا 09 یا 99
            'priority_format'    => str_pad((string) min(99, max(1, absint($raw_value))), 2, '0', STR_PAD_LEFT),
            default              => sanitize_text_field($raw_value),
        };

        update_post_meta($post_id, '_' . $field, $clean_value);
    }

    $select_fields = [
        'hd_notif_target_type' => ['all', 'specific', 'exclude'],
        'hd_notif_device' => ['all', 'desktop', 'mobile'],
        'hd_notif_user_status' => ['all', 'logged_in', 'logged_out'],
        'hd_notif_position' => ['center', 'top_bar', 'bottom_bar', 'bottom_right', 'bottom_left', 'middle_right', 'middle_left'],
        'hd_notif_anim_in' => ['fade_in', 'slide_up', 'bounce', 'zoom_in'],
        'hd_notif_anim_out' => ['fade_out', 'slide_down'],
        'hd_notif_target_role' => ['all', 'administrator', 'subscriber', 'customer'],
        'hd_notif_target_post_type' => ['all', 'post', 'page', 'product'],
    ];

    foreach ($select_fields as $field => $allowed_values) {
        $raw_value = $_POST[$field] ?? '';
        $raw_value = sanitize_text_field(wp_unslash($raw_value));
        $clean_value = in_array($raw_value, $allowed_values, true) ? $raw_value : $allowed_values[0];
        update_post_meta($post_id, '_' . $field, $clean_value);
    }

    $target_type = get_post_meta($post_id, '_hd_notif_target_type', true);
    $target_page_raw = $_POST['hd_notif_target_page'] ?? '';
    $target_page_raw = wp_unslash($target_page_raw);

    $lines = preg_split('/\r\n|\r|\n/', (string) $target_page_raw);
    $lines = is_array($lines) ? $lines : [];
    $lines = array_map('trim', $lines);
    $lines = array_filter($lines, static fn($line) => $line !== '');
    $lines = array_slice($lines, 0, 50);
    $sanitized_lines = array_map(static function ($line) { return sanitize_text_field($line); }, $lines);

    if ($target_type === 'all') {
        update_post_meta($post_id, '_hd_notif_target_page', '');
    } elseif ($target_type === 'specific' || $target_type === 'exclude') {
        update_post_meta($post_id, '_hd_notif_target_page', implode("\n", $sanitized_lines));
    } else {
        update_post_meta($post_id, '_hd_notif_target_page', '');
    }

    $checkboxes = [
        'hd_notif_is_active',
        'hd_notif_close_btn',
        'hd_notif_trigger_exit',
        'hd_notif_bg_transparent',
    ];

    foreach ($checkboxes as $cb) {
        $val = isset($_POST[$cb]) ? '1' : '0';
        update_post_meta($post_id, '_' . $cb, $val);
    }
    
    delete_transient('hd_active_notifications');
}