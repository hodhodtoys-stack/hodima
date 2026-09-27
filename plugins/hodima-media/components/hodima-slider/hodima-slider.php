<?php
/**
 * Hodima Slider - Multi Shortcodes (PHP 8.4, HTML5, Modern CSS, Vanilla JS)
 * @version 2.1.0 (Refactored)
 */

declare(strict_types=1);

namespace Hodima\Slider;

if (!defined('ABSPATH')) exit;

// Autoloader ساده برای بارگذاری فایل‌های داخل پوشه includes
spl_autoload_register(function ($class) {
    $prefix = 'Hodima\\Slider\\';
    $base_dir = __DIR__ . '/includes/';
    $len = strlen($prefix);

    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// راه‌اندازی افزونه
Core::init();