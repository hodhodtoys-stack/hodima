<?php
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) exit;

class Hodima_GI_Logger {
    public static function log( string $message, $data = [], string $status = 'info' ): void {
        if ( ! is_array( $data ) ) $data = [ 'url' => $data ];
        $url = $data['url'] ?? ($data['value'] ?? '');
        
        Hodima_Crawler_DB_Queries::insert_log( (string)$url, $message, $status, wp_json_encode($data) );
    }
}
