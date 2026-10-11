<?php
/**
 * صفحه «اسکیما ← سلامت»: همه منطق و نمایش در schema/schema-health.php (زیر PHPCS/PHPStan).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once HODIMA_SCHEMA_PATH . '/schema-health.php';
hodima_seo_schema_health_page();
