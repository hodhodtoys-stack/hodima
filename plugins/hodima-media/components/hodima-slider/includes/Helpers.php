<?php
declare(strict_types=1);
namespace Hodima\Slider;

if (!defined('ABSPATH')) exit;

final class Helpers {
    public static function validate_image_id(mixed $id): int {
        return (absint($id) > 0 && wp_attachment_is_image(absint($id))) ? absint($id) : 0;
    }

    public static function get_placeholder(): string {
        $text = esc_attr__('بدون تصویر', 'hodima');
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="80" viewBox="0 0 120 80" role="img" aria-label="'.$text.'"><title>'.$text.'</title><rect width="100%" height="100%" fill="#e2e8f0"/><text x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" fill="#64748b">'.$text.'</text></svg>';
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    public static function get_image_url(int $id, string $size = 'thumbnail'): string {
        return wp_get_attachment_image_url($id, $size) ?: self::get_placeholder();
    }

    public static function normalize_items(array $raw): array {
        return array_slice(array_filter(array_map(function($row) {
            $image_id = self::validate_image_id($row['image_id'] ?? 0);
            $mobile_image_id = self::validate_image_id($row['mobile_image_id'] ?? 0);
            
            if (!$image_id) return null;

            return [
                'image_id'        => $image_id,
                'mobile_image_id' => $mobile_image_id,
                'link_url'        => esc_url_raw($row['link_url'] ?? ''),
                'duration'        => max(2, min(20, absint($row['duration'] ?? Core::DEFAULT_DUR))),
                'title'           => sanitize_text_field($row['title'] ?? ''),
                'desc'            => sanitize_textarea_field($row['desc'] ?? ''),
                'btn_text'        => sanitize_text_field($row['btn_text'] ?? ''),
            ];
        }, $raw)), 0, Core::MAX_ITEMS);
    }

    public static function is_allowed_option(string $option): bool {
        return in_array($option, [
            Core::OPTION_S1, Core::OPTION_S2A, Core::OPTION_S2B,
            Core::OPTION_S2C, Core::OPTION_S2D, Core::OPTION_S3,
        ], true);
    }

    public static function get_items(string $option): array {
        if (!self::is_allowed_option($option)) return [];
        $items = get_option($option, []);
        return is_array($items) ? $items : [];
    }

    public static function sanitize_settings(array $raw): array {
        $keys = ['s1', 's2', 's3'];
        $out = [];
        
        // دریافت زمان کش از تنظیمات (پیش‌فرض 12)
        $cache_time = absint($raw['cache_time'] ?? 12);
        $out['cache_time'] = ($cache_time >= 1 && $cache_time <= 72) ? $cache_time : 12;

        foreach ($keys as $k) {
            $s = $raw[$k] ?? [];
            $effect = SliderEffect::sanitize((string)($s['effect'] ?? ''));

            $out[$k] = [
                'w'        => self::num_or_empty($s['w'] ?? ''),
                'h'        => self::num_or_empty($s['h'] ?? ''),
                'w_mobile' => self::num_or_empty($s['w_mobile'] ?? ''),
                'h_mobile' => self::num_or_empty($s['h_mobile'] ?? ''),
                'effect'   => $effect,
            ];
        }
        return $out;
    }

    private static function num_or_empty(mixed $val): string {
        if ($val === '' || $val === null) return '';
        $v = absint($val);
        return $v > 0 ? (string)$v : '';
    }
}