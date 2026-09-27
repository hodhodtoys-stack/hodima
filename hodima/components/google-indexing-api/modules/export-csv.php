<?php
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) exit;

class Hodima_Export_CSV {
    private static function safe_cell( $value ): string {
        $value = (string) $value;
        // تب و carriage return هم در اکسل آغازگر فرمول‌اند
        if ( isset($value[0]) && in_array( $value[0], ['=', '+', '-', '@', "\t", "\r"], true ) ) {
            return "'" . $value;
        }
        return $value;
    }
    private static function safe_row( array $row ): array {
        return array_map( [ __CLASS__, 'safe_cell' ], $row );
    }

    public static function process_export( string $type ): void {
        if ( ! current_user_can('manage_options') ) wp_die('دسترسی غیرمجاز');
        // اعتبارسنجی *قبل از* ارسال هدرهای فایل
        if ( ! in_array( $type, [ 'crawls', 'logs' ], true ) ) wp_die( 'نوع خروجی نامعتبر است.' );

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=hodima-' . $type . '-report-' . date('Ymd') . '.csv');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

        if ( $type === 'crawls' ) {
            fputcsv($out, ['URL Route', 'Total Visits', 'Last Bot Visit', 'API Sync Status']);
            $data = Hodima_Crawler_DB_Queries::get_recent_crawls(1000);
            foreach($data as $row) fputcsv($out, self::safe_row([$row['url_path'], $row['crawl_count'], $row['last_crawled_at'], $row['api_sync_status']]));
        } 
        elseif ( $type === 'logs' ) {
            fputcsv($out, ['Date', 'Status', 'Message', 'Details (URL/JSON)']);
            $data = Hodima_Crawler_DB_Queries::get_system_logs(1000);
            foreach($data as $row) fputcsv($out, self::safe_row([$row['created_at'], $row['status'], $row['message'], $row['log_data']]));
        }

        fclose($out);
        wp_die();
    }
}
