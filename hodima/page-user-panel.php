<?php
/* Template Name: User Panel */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) exit;

// ماژول پنل کاربری نصب یا فعال نیست: برگه مثل یک برگه معمولی نمایش داده شود.
if ( ! defined( 'HODIMA_USER_PANEL_ENABLED' ) || ! HODIMA_USER_PANEL_ENABLED ) {
    require get_template_directory() . '/index.php';
    return;
}

get_header();
?>

<div class="user-panel-wrapper">
    <?php require_once get_template_directory() . '/user-panel/index.php'; ?>
</div>

<?php wp_footer(); ?>
</body>
</html>
