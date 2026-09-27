<?php
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) exit;

class Hodima_Core_Schema {

    public static function create_tables(): void {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta( "CREATE TABLE {$wpdb->prefix}hodima_ai_bot_logs (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            bot_name varchar(50) NOT NULL,
            ip_address varchar(45) NOT NULL,
            user_agent varchar(255) NOT NULL,
            url_path varchar(500) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY bot_name (bot_name),
            KEY ip_time (ip_address, created_at)
        ) {$charset};" );

        dbDelta( "CREATE TABLE {$wpdb->prefix}hodima_banned_ips (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            ip_address varchar(45) NOT NULL,
            reason varchar(255) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY ip_address (ip_address)
        ) {$charset};" );

        dbDelta( "CREATE TABLE {$wpdb->prefix}hodima_indexnow_queue (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            url_path text NOT NULL,
            url_hash char(32) NOT NULL,
            action varchar(10) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            error_message varchar(255) DEFAULT NULL,
            retries int(11) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY hash_key (url_hash),
            KEY status_retries (status, retries)
        ) {$charset};" );

        dbDelta( "CREATE TABLE {$wpdb->prefix}hodima_search_bot_logs (
            bot_name varchar(50) NOT NULL,
            visit_count bigint(20) unsigned NOT NULL DEFAULT 1,
            last_visit datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (bot_name)
        ) {$charset};" );
    }
}