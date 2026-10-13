<?php
/**
 * آرشیو یک دسته وبلاگ (/blog/نام-دسته/)
 * Path: hodima/category.php
 *
 * بدنه مشترک آرشیو وبلاگ: template-parts/blog/archive.php
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) exit;

get_header();

get_template_part( 'template-parts/blog/archive' );

get_footer();
