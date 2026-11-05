<?php
/**
 * قالب صفحه پنل کاربری: سربرگ و پانویس قالب سایت + محتوای پنل.
 *
 * @package Hodima\Panel
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

get_header();
?>
<main id="primary" class="hodima-panel">
	<?php \Hodima\Panel\Front::render(); ?>
</main>
<?php
get_footer();
