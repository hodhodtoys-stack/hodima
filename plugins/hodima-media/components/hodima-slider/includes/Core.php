<?php
declare(strict_types=1);
namespace Hodima\Slider;

if (!defined('ABSPATH')) exit;

enum SliderEffect: string {
    case Fade  = 'fade';
    case Slide = 'slide';
    case Zoom  = 'zoom';
    case Flip  = 'flip';

    public static function isValid(string $value): bool {
        return self::tryFrom($value) !== null;
    }

    public static function sanitize(string $value, self $default = self::Fade): string {
        return (self::tryFrom($value) ?? $default)->value;
    }
}

final class Core {
    public const OPTION_S1   = 'hodima_slider1_items';
    public const OPTION_S2A  = 'hodima_slider2a_items';
    public const OPTION_S2B  = 'hodima_slider2b_items';
    public const OPTION_S2C  = 'hodima_slider2c_items';
    public const OPTION_S2D  = 'hodima_slider2d_items';
    public const OPTION_S3   = 'hodima_slider3_items';
    public const OPTION_SETTINGS = 'hodima_slider_settings';
    public const OPTION_S2_MOBILE = 'hodima_slider2_mobile_settings';

    // v3: ساختار HTML جدید (بدون متن پنهان، تنبل‌بار، ابعاد تصویر)
    public const TRANSIENT_PREFIX = 'hodima_slider_cache_v4_';

    public const MAX_ITEMS   = 12;
    public const VERSION     = '3.0.0';
    public const DEFAULT_DUR = 5;

    public static function init(): void {
        add_action('after_setup_theme', function () {
            add_image_size('hodima-slider-1920', 1920, 1080, false); 
            add_image_size('hodima-slider2-main', 800, 800, false); 
        });

        add_action('updated_option', [self::class, 'maybe_clear_cache'], 10, 3);
        add_action('added_option',   [self::class, 'maybe_clear_cache'], 10, 2);
        add_action('deleted_option', [self::class, 'maybe_clear_cache'], 10, 1);

        Admin::init();
        Frontend::init();
    }

    public static function transient_key(string $key): string {
        return self::TRANSIENT_PREFIX . $key;
    }

    public static function asset_url(string $file): string {
        // مسیر قالب اصلی (مثل بقیه قالب)، نه قالب فرزند
        return get_template_directory_uri() . '/components/hodima-slider/assets/' . ltrim($file, '/');
    }

    public static function asset_path(string $file): string {
        return get_template_directory() . '/components/hodima-slider/assets/' . ltrim($file, '/');
    }

    private static array $versionCache = [];

    public static function asset_version(string $file): string {
        if (!isset(self::$versionCache[$file])) {
            $path = self::asset_path($file);
            $mtime = file_exists($path) ? filemtime($path) : false;
            self::$versionCache[$file] = $mtime !== false ? (string)$mtime : self::VERSION;
        }
        return self::$versionCache[$file];
    }

    public static function maybe_clear_cache(string $option, $oldValue = null, $newValue = null): void {
        if ($option === self::OPTION_S1) {
            delete_transient(self::transient_key('s1'));
        } elseif (in_array($option, [self::OPTION_S2A, self::OPTION_S2B, self::OPTION_S2C, self::OPTION_S2D, self::OPTION_S2_MOBILE], true)) {
            delete_transient(self::transient_key('s2'));
        } elseif ($option === self::OPTION_S3) {
            delete_transient(self::transient_key('s3'));
        } elseif ($option === self::OPTION_SETTINGS) {
            delete_transient(self::transient_key('s1'));
            delete_transient(self::transient_key('s2'));
            delete_transient(self::transient_key('s3'));
        }
    }
}