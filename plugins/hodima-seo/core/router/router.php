<?php
/**
 * Arian Clean Router - Bootstrap
 * نقطه ورود واحد. هیچ فایل دیگری نباید router.php را require کند.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'ARIAN_ROUTER_DIR', __DIR__ );
define( 'ARIAN_ROUTER_VER', '1.0.2' ); // برای تریگر شدن کش می‌توانید این را بالا ببرید

require_once ARIAN_ROUTER_DIR . '/permalinks.php';
require_once ARIAN_ROUTER_DIR . '/rewrites.php';
require_once ARIAN_ROUTER_DIR . '/smart-parser.php';

/**
 * فلاش امن مخصوص قالب (بدون activation hook پلاگین).
 */
add_action( 'init', function () {
    if ( get_option( 'arian_router_flushed' ) !== ARIAN_ROUTER_VER ) {
        flush_rewrite_rules( false );
        update_option( 'arian_router_flushed', ARIAN_ROUTER_VER );
    }
}, 99 );

add_action( 'after_switch_theme', function () {
    flush_rewrite_rules();
    update_option( 'arian_router_flushed', ARIAN_ROUTER_VER );
} );