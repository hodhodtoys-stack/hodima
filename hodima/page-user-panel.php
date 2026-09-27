<?php
/* Template Name: User Panel */

if ( ! defined( 'ABSPATH' ) ) exit;

get_header();
?>

<div class="user-panel-wrapper">
    <?php require_once get_template_directory() . '/user-panel/index.php'; ?>
</div>

<?php wp_footer(); ?>
</body>
</html>
