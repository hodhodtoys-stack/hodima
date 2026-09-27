<?php
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) exit;

class Hodima_Crawler_DB_Schema {
    public static function get_table_name(): string { global $wpdb; return $wpdb->prefix . 'hodima_crawl_index'; }
    public static function get_logs_table(): string { global $wpdb; return $wpdb->prefix . 'hodima_gi_logs'; }
    public static function get_queue_table(): string { global $wpdb; return $wpdb->prefix . 'hodima_gi_queue'; }
    
    public static function build_table(): void {
        global $wpdb; $charset = $wpdb->get_charset_collate();
        
        $t_main = self::get_table_name();
        $sql1 = "CREATE TABLE {$t_main} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT, url_hash char(32) NOT NULL, url_path varchar(500) NOT NULL,
            object_id bigint(20) unsigned DEFAULT NULL, object_type varchar(20) DEFAULT 'post', cluster_role varchar(30) DEFAULT 'standard',
            is_indexable tinyint(1) NOT NULL DEFAULT 1, http_status smallint(3) NOT NULL DEFAULT 200, content_hash char(32) DEFAULT NULL,
            last_modified_at datetime DEFAULT NULL, crawl_count int(11) unsigned NOT NULL DEFAULT 0, last_crawled_at datetime DEFAULT NULL,
            api_sync_status tinyint(1) NOT NULL DEFAULT 0, reaction_time int(11) DEFAULT NULL, extra_data json DEFAULT NULL,
            PRIMARY KEY (id), UNIQUE KEY url_hash (url_hash), KEY object_identifier (object_id, object_type), KEY api_sync (api_sync_status)
        ) $charset;";

        $t_logs = self::get_logs_table();
        $sql2 = "CREATE TABLE {$t_logs} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT, url varchar(500) DEFAULT NULL,
            message varchar(255) NOT NULL, status varchar(50) NOT NULL, log_data text, created_at datetime NOT NULL,
            PRIMARY KEY (id), KEY created_at (created_at)
        ) $charset;";

        $t_queue = self::get_queue_table();
        $sql3 = "CREATE TABLE {$t_queue} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT, url_hash char(32) NOT NULL, url varchar(500) NOT NULL,
            type varchar(50) NOT NULL, source varchar(50) NOT NULL, retries tinyint(2) NOT NULL DEFAULT 0,
            execute_at int(11) unsigned NOT NULL, next_retry int(11) unsigned NOT NULL DEFAULT 0, created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id), UNIQUE KEY url_hash_type (url_hash, type)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql1 ); dbDelta( $sql2 ); dbDelta( $sql3 );
    }
}
