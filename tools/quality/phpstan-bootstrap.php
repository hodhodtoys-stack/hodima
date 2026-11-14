<?php
/**
 * ثابت‌هایی که PHPStan هنگام تحلیل قالب باید بشناسد (فقط برای تحلیل؛
 * هیچ‌وقت روی سایت لود نمی‌شود). مقدارها مهم نیستند، فقط نوعشان.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || define( 'ABSPATH', '/tmp/wordpress/' );

// قالب: functions.php
define( 'hodima_VERSION', '0.0.0' );
define( 'hodima_URI', 'https://example.test/wp-content/themes/hodima' );
define( 'hodima_DIR', '/tmp/wordpress/wp-content/themes/hodima' );
define( 'HODIMA_USER_PANEL_ENABLED', false );

// Hodima Core: hodima-core.php (ورود و امنیت)
define( 'HODIMA_CORE_PLUGIN_URL', 'https://example.test/wp-content/plugins/hodima-core' );
define( 'HODIMA_CORE_PLUGIN_DIR', '/tmp/wordpress/wp-content/plugins/hodima-core' );
define( 'HODIMA_CORE_PLUGIN_VERSION', '0.0.0' );

// Hodima Media: hodima-media.php (ماژول استوری، بخش ۱۲۷)
define( 'HODIMA_MEDIA_URL', 'https://example.test/wp-content/plugins/hodima-media' );
define( 'HODIMA_MEDIA_DIR', '/tmp/wordpress/wp-content/plugins/hodima-media' );

// Hodima Commerce: hodima-commerce.php (ماژول تاریخ شمسی/شهرها، بخش ۱۰۰)
define( 'HODIMA_COMMERCE_VERSION', '0.0.0' );
define( 'HODIMA_COMMERCE_URL', 'https://example.test/wp-content/plugins/hodima-commerce' );
define( 'HODIMA_COMMERCE_DIR', '/tmp/wordpress/wp-content/plugins/hodima-commerce' );

// Hodima SEO: hodima-seo.php (ثابت‌های افزونه، صفحه پیشخوان)
define( 'HODIMA_SEO_VERSION', '0.0.0' );
define( 'HODIMA_SEO_URL', 'https://example.test/wp-content/plugins/hodima-seo' );
define( 'HODIMA_SEO_DIR', '/tmp/wordpress/wp-content/plugins/hodima-seo' );

// Hodima Panel: hodima-panel.php
define( 'HODIMA_PANEL_FILE', '/tmp/wordpress/wp-content/plugins/hodima-panel/hodima-panel.php' );
define( 'HODIMA_PANEL_DIR', '/tmp/wordpress/wp-content/plugins/hodima-panel' );
define( 'HODIMA_PANEL_URL', 'https://example.test/wp-content/plugins/hodima-panel' );

// ثابت‌های کوکی وردپرس (wp-includes/default-constants.php؛ Hodima Panel)
defined( 'COOKIEPATH' ) || define( 'COOKIEPATH', '/' );
defined( 'COOKIE_DOMAIN' ) || define( 'COOKIE_DOMAIN', '' );
