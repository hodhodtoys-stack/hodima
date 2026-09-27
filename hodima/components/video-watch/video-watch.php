<?php
declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('HOD_VIDEO_WATCH_PATH', trailingslashit(__DIR__));
define('HOD_VIDEO_WATCH_URL', trailingslashit(get_template_directory_uri() . '/components/video-watch'));

/** نسخه دارایی = زمان تغییر فایل (نسخه ثابت «1.2.2» به‌روزرسانی را پنهان می‌کرد). */
function hod_video_watch_ver(string $file): string
{
    $path = HOD_VIDEO_WATCH_PATH . $file;
    return file_exists($path) ? (string) filemtime($path) : '2.0.0';
}

$hod_video_watch_files = [
    'post-type.php',
    'meta-boxes.php',
    'vid-w-schema.php',
    'db-video-watch.php',
];

foreach ($hod_video_watch_files as $hod_video_watch_file) {
    $hod_video_watch_file_path = HOD_VIDEO_WATCH_PATH . $hod_video_watch_file;
    if (file_exists($hod_video_watch_file_path)) {
        require_once $hod_video_watch_file_path;
    }
}

function hod_video_watch_front_assets(): void
{
    if (!is_singular('video') && !is_post_type_archive('video')) {
        return;
    }

    wp_enqueue_style('hod-video-watch-front', HOD_VIDEO_WATCH_URL . 'video-watch.front.css', [], hod_video_watch_ver('video-watch.front.css'));
    wp_enqueue_script('hod-video-watch-front', HOD_VIDEO_WATCH_URL . 'video-watch.front.js', [], hod_video_watch_ver('video-watch.front.js'), true);
    
    // تزریق Nonce امنیتی به جاوااسکریپت (به‌همراه آدرس بازیابی نانس تازه برای صفحات کش‌شده)
    wp_localize_script('hod-video-watch-front', 'hvwData', [
        'restUrl'  => esc_url_raw(rest_url('video-watch/v1/track')),
        'nonceUrl' => esc_url_raw(rest_url('video-watch/v1/nonce')),
        'nonce'    => wp_create_nonce('hvw_track_action')
    ]);
}
add_action('wp_enqueue_scripts', 'hod_video_watch_front_assets');

function hod_video_watch_preload_poster(): void
{
    if (!is_singular('video')) {
        return;
    }

    $post_id = (int) get_queried_object_id();
    $video_thumb = get_post_meta($post_id, '_hod_video_thumbnail', true) ?: get_the_post_thumbnail_url($post_id, 'full');

    if ($video_thumb) {
        // کاور، بزرگ‌ترین عنصر بالای صفحه ویدئو (LCP) است؛ fetchpriority آن را جلو می‌اندازد
        echo '<link rel="preload" as="image" fetchpriority="high" href="' . esc_url((string) $video_thumb) . '">' . "\n";
    }
}
add_action('wp_head', 'hod_video_watch_preload_poster', 1);

function hod_video_watch_admin_assets(string $hook_suffix): void
{
    $screen = get_current_screen();
    if (!$screen || 'video' !== $screen->post_type) {
        return;
    }
    wp_enqueue_media();
    wp_enqueue_style('hod-video-watch-admin', HOD_VIDEO_WATCH_URL . 'video-watch.admin.css', [], hod_video_watch_ver('video-watch.admin.css'));
    wp_enqueue_script('hod-video-watch-admin', HOD_VIDEO_WATCH_URL . 'video-watch.admin.js', [], hod_video_watch_ver('video-watch.admin.js'), true);
}
add_action('admin_enqueue_scripts', 'hod_video_watch_admin_assets');

function hod_video_watch_template_include(string $template): string
{
    if (is_singular('video')) {
        $video_template = HOD_VIDEO_WATCH_PATH . 'template-video.php';
        if (file_exists($video_template)) {
            return $video_template;
        }
    }
    return $template;
}
add_filter('template_include', 'hod_video_watch_template_include');