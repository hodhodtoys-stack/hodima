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
