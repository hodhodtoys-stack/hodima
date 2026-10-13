<?php
/**
 * Template Name: Blog Archive
 * Description: صفحه وبلاگ (برگه «نوشته‌ها») و آرشیوهای نویسنده/تاریخ؛ دسته و برچسب: category.php و tag.php
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) exit;

get_header();

get_template_part( 'template-parts/blog/archive' );

get_footer();
