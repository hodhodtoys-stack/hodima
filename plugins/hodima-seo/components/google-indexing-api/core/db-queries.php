<?php
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) exit;

class Hodima_Crawler_DB_Queries {
    public static function upsert_url_data( string $url_path, array $data ): void {
        global $wpdb; $table = Hodima_Crawler_DB_Schema::get_table_name();
        // کلید نرمال: هگز حروف کوچک وردپرس و حروف بزرگ گوگل‌بات یک ردیف می‌شوند
        $data['url_hash'] = Hodima_GI_Helper::url_hash( $url_path );
        $data['url_path'] = substr( $url_path, 0, 500 );

        $columns = array_keys( $data );
        $placeholders = [];
        $update_parts = [];
        $values = [];

        foreach ( $data as $col => $value ) {
            if ( $value === null ) {
                $placeholders[] = 'NULL';
            } else {
                $placeholders[] = is_int( $value ) ? '%d' : ( is_float( $value ) ? '%f' : '%s' );
                $values[] = $value;
            }
            if ( ! in_array( $col, [ 'url_hash', 'url_path' ], true ) ) {
                $update_parts[] = "`{$col}` = VALUES(`{$col}`)";
            }
        }

        $col_list   = '`' . implode( '`, `', $columns ) . '`';
        $ph_list    = implode( ', ', $placeholders );
        $update_sql = ! empty( $update_parts ) ? implode( ', ', $update_parts ) : 'url_path = VALUES(url_path)';

        $sql = "INSERT INTO {$table} ({$col_list}) VALUES ({$ph_list}) ON DUPLICATE KEY UPDATE {$update_sql}";
        
        if ( ! empty( $values ) ) {
            $wpdb->query( $wpdb->prepare( $sql, $values ) );
        } else {
            $wpdb->query( $sql );
        }
    }

    public static function get_last_known_url( int $object_id, string $object_type ): ?string {
        global $wpdb; $table = Hodima_Crawler_DB_Schema::get_table_name();
        $url = $wpdb->get_var( $wpdb->prepare(
            "SELECT url_path FROM {$table} WHERE object_id = %d AND object_type = %s ORDER BY id DESC LIMIT 1",
            $object_id, $object_type
        ) );
        return $url ?: null;
    }

    public static function log_crawl_event( string $url_path ): void {
        global $wpdb; 
        $table = Hodima_Crawler_DB_Schema::get_table_name();
        $hash = Hodima_GI_Helper::url_hash( $url_path );
        $now = current_time( 'mysql' );
        
        // This intelligent query checks if api_sync_status = 1 and calculates the exact reaction time, 
        // then freezes it by setting api_sync_status to 2 so future crawls don't inflate the average!
        $sql = "INSERT INTO {$table} (url_hash, url_path, crawl_count, last_crawled_at) 
                VALUES (%s, %s, 1, %s) 
                ON DUPLICATE KEY UPDATE 
                crawl_count = crawl_count + 1, 
                reaction_time = IF(api_sync_status = 1 AND reaction_time IS NULL AND last_modified_at IS NOT NULL, TIMESTAMPDIFF(MINUTE, last_modified_at, %s), reaction_time),
                api_sync_status = IF(api_sync_status = 1 AND last_modified_at IS NOT NULL, 2, api_sync_status),
                last_crawled_at = %s";
                
        $wpdb->query( $wpdb->prepare( $sql, $hash, substr($url_path, 0, 500), $now, $now, $now ) );
    }
    
    // Original methods kept for CSV exports and backward compatibility
    public static function get_recent_crawls( int $limit = 50 ): array {
        global $wpdb; $table = Hodima_Crawler_DB_Schema::get_table_name();
        return $wpdb->get_results( $wpdb->prepare( "SELECT url_path, crawl_count, last_crawled_at, api_sync_status FROM {$table} WHERE crawl_count > 0 ORDER BY last_crawled_at DESC LIMIT %d", $limit ), ARRAY_A ) ?? [];
    }

    // Paginated Methods
    public static function get_recent_crawls_paginated( int $page = 1, int $per_page = 15 ): array {
        global $wpdb; $table = Hodima_Crawler_DB_Schema::get_table_name();
        $offset = ($page - 1) * $per_page;
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE crawl_count > 0");
        // زمان آخرین ارسال (last_modified_at) و زمان واکنش هم برای ستون‌های پنل
        $items = $wpdb->get_results( $wpdb->prepare( "SELECT url_path, crawl_count, last_crawled_at, api_sync_status, last_modified_at, reaction_time FROM {$table} WHERE crawl_count > 0 ORDER BY last_crawled_at DESC LIMIT %d OFFSET %d", $per_page, $offset ), ARRAY_A ) ?? [];
        return [ 'items' => $items, 'total' => $total, 'max_pages' => (int) ceil($total / max(1, $per_page)) ];
    }
    
    public static function clear_crawls(): void {
        global $wpdb; 
        $wpdb->query("TRUNCATE TABLE " . Hodima_Crawler_DB_Schema::get_table_name());
    }

    /**
     * آمار «سلامت سئو»: زمان واکنش گوگل پس از پینگ.
     *
     * تغییرات:
     *   - میانه به جای میانگین. یک آدرس که گوگل سه هفته بعد خزیده،
     *     میانگین را چند برابر می‌کرد و عدد بی‌معنی می‌شد.
     *   - داده بیش از ۱۴ روز کنار گذاشته می‌شود (به احتمال زیاد خزش عادی
     *     بوده نه واکنش به پینگ).
     *   - تعداد نمونه هم برگردانده می‌شود تا پنل بتواند «هنوز داده‌ای
     *     نیست» را از «واکنش صفر دقیقه‌ای» تشخیص دهد. نسخه قبلی هر دو را
     *     «در حال ارزیابی» نشان می‌داد.
     *
     * @return array{median:int, avg:int, samples:int}
     */
    public static function get_seo_health_detail(): array {

        global $wpdb;
        $table = Hodima_Crawler_DB_Schema::get_table_name();

        $values = array_map( 'intval', (array) $wpdb->get_col(
            "SELECT reaction_time FROM {$table}
             WHERE reaction_time IS NOT NULL AND reaction_time BETWEEN 0 AND 20160
             ORDER BY reaction_time ASC LIMIT 5000"
        ) );

        $n = count( $values );

        if ( 0 === $n ) {
            return [ 'median' => 0, 'avg' => 0, 'samples' => 0 ];
        }

        $mid    = intdiv( $n, 2 );
        $median = ( $n % 2 ) ? $values[ $mid ] : intdiv( $values[ $mid - 1 ] + $values[ $mid ], 2 );

        return [
            'median'  => $median,
            'avg'     => (int) round( array_sum( $values ) / $n ),
            'samples' => $n,
        ];
    }

    /** سازگاری با کد قدیمی. */
    public static function get_seo_health_stats(): int {
        return self::get_seo_health_detail()['median'];
    }

    public static function prune_old_records( int $days = 60 ): int {
        global $wpdb; $table = Hodima_Crawler_DB_Schema::get_table_name();
        $days = max( 7, $days );

        /*
         * ردیف‌هایی که object_id دارند حذف نمی‌شوند: تنها منبع «آخرین
         * آدرس شناخته‌شده» یک پست‌اند. نسخه قبلی آن‌ها را هم حذف می‌کرد و
         * اگر نامک یک پست قدیمی عوض می‌شد، تغییر تشخیص داده نمی‌شد و آدرس
         * قبلی هرگز به گوگل برای حذف اعلام نمی‌شد.
         */
        $deleted = (int) $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$table}
             WHERE object_id IS NULL
               AND last_crawled_at < DATE_SUB(NOW(), INTERVAL %d DAY)
               AND (last_modified_at IS NULL OR last_modified_at < DATE_SUB(NOW(), INTERVAL %d DAY))",
            $days, $days
        ) );

        $deleted += (int) $wpdb->query( $wpdb->prepare(
            "DELETE FROM " . Hodima_Crawler_DB_Schema::get_logs_table() . " WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ) );

        self::trim_logs();

        return $deleted;
    }

    /**
     * شرط مشترک «محتوای راکد».
     *
     * اصلاح‌ها نسبت به نسخه قبلی:
     *   - صفحه اصلی، صفحه نوشته‌ها و برگه‌های سیستمی ووکامرس (فروشگاه، سبد،
     *     تسویه، حساب کاربری) کنار گذاشته می‌شوند؛ این‌ها «محتوا» نیستند و
     *     فهرست را پر می‌کردند.
     *   - صفحه‌های noindex (سئوباکس) و رمزدار کنار گذاشته می‌شوند؛ گوگل
     *     نباید آن‌ها را ببیند.
     *   - مقایسه با post_modified_gmt و مرز محاسبه‌شده در PHP؛ NOW() ساعت
     *     سرور دیتابیس است و با post_modified (ساعت سایت) چند ساعت فرق داشت.
     *   - نوع‌های پست با placeholder، نه چسباندن رشته.
     *
     * @return array{0:string, 1:array<int, mixed>}|null
     */
    private static function stale_where( int $days ): ?array {
        global $wpdb;

        $types = array_values( array_filter( array_map( 'sanitize_key', (array) ( Hodima_GI_Helper::get_settings()['google_post_types'] ?? [ 'post', 'product' ] ) ) ) );
        if ( empty( $types ) ) {
            return null;
        }

        $exclude = [ (int) get_option( 'page_on_front' ), (int) get_option( 'page_for_posts' ) ];
        foreach ( [ 'shop', 'cart', 'checkout', 'myaccount' ] as $wc_page ) {
            $exclude[] = (int) get_option( "woocommerce_{$wc_page}_page_id" );
        }
        $exclude = array_values( array_filter( array_map( 'intval', (array) apply_filters( 'hodima_gi_stale_exclude_ids', $exclude ) ) ) ) ?: [ 0 ];

        $cutoff = gmdate( 'Y-m-d H:i:s', time() - max( 1, $days ) * DAY_IN_SECONDS );

        $sql = "p.post_status = 'publish' AND p.post_password = ''"
            . ' AND p.post_type IN (' . implode( ',', array_fill( 0, count( $types ), '%s' ) ) . ')'
            . ' AND p.ID NOT IN (' . implode( ',', array_fill( 0, count( $exclude ), '%d' ) ) . ')'
            . ' AND p.post_modified_gmt < %s'
            . " AND NOT EXISTS (SELECT 1 FROM {$wpdb->postmeta} m WHERE m.post_id = p.ID AND m.meta_key = '_seobox_robots' AND m.meta_value LIKE '%%noindex%%')"; // %% = درصد واقعی در prepare

        return [ $sql, array_merge( $types, $exclude, [ $cutoff ] ) ];
    }

    public static function get_stale_posts( int $days = 60 ): array {
        return self::get_stale_posts_paginated( $days, 1, 100 )['items'];
    }

    public static function get_stale_posts_paginated( int $days = 60, int $page = 1, int $per_page = 15 ): array {
        global $wpdb;

        $where = self::stale_where( $days );
        if ( null === $where ) {
            return [ 'items' => [], 'total' => 0, 'max_pages' => 0 ];
        }

        [ $sql, $args ] = $where;
        $page     = max( 1, $page );
        $per_page = max( 1, $per_page );

        $total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} p WHERE {$sql}", $args ) );
        $items = $wpdb->get_results( $wpdb->prepare(
            "SELECT p.ID, p.post_title, p.post_type, p.post_modified, p.post_modified_gmt FROM {$wpdb->posts} p WHERE {$sql} ORDER BY p.post_modified_gmt ASC LIMIT %d OFFSET %d",
            array_merge( $args, [ $per_page, ( $page - 1 ) * $per_page ] )
        ), ARRAY_A ) ?? [];

        return [ 'items' => $items, 'total' => $total, 'max_pages' => (int) ceil( $total / $per_page ) ];
    }

    /**
     * آخرین بازدید گوگل‌بات برای چند آدرس (کلید: همان url_hash جدول).
     *
     * @param list<string> $urls
     * @return array<string, string> url_hash => last_crawled_at
     */
    public static function get_last_crawls_for_urls( array $urls ): array {
        global $wpdb;

        $hashes = array_values( array_unique( array_map( [ 'Hodima_GI_Helper', 'url_hash' ], array_filter( $urls ) ) ) );
        if ( empty( $hashes ) ) {
            return [];
        }

        $table = Hodima_Crawler_DB_Schema::get_table_name();
        $in    = implode( ',', array_fill( 0, count( $hashes ), '%s' ) );
        $rows  = $wpdb->get_results( $wpdb->prepare(
            "SELECT url_hash, last_crawled_at FROM {$table} WHERE url_hash IN ({$in}) AND last_crawled_at IS NOT NULL",
            $hashes
        ), ARRAY_A ) ?? [];

        return array_column( $rows, 'last_crawled_at', 'url_hash' );
    }

    public static function insert_log( string $url, string $message, string $status, string $data ): void {
        global $wpdb;
        $table = Hodima_Crawler_DB_Schema::get_logs_table();
        $url_clean = substr($url, 0, 500);

        if ( ($status === 'success' || strpos($status, 'error') !== false) && !empty($url_clean) ) {
            $existing_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE url = %s AND status = 'queue' ORDER BY id DESC LIMIT 1", $url_clean));
            
            if ( $existing_id ) {
                $wpdb->update($table, [
                    'message'    => substr($message, 0, 255),
                    'status'     => $status,
                    'created_at' => current_time('mysql')
                ], ['id' => $existing_id]);
                return;
            }
        }

        $wpdb->insert( $table, [
            'url' => $url_clean, 'message' => substr($message, 0, 255),
            'status' => $status, 'log_data' => $data, 'created_at' => current_time('mysql')
        ]);
        
        /*
         * نسخه قبلی این DELETE با زیرکوئری را در *هر* ثبت لاگ اجرا می‌کرد —
         * یعنی هر بار که آیتمی وارد صف می‌شد. حالا فقط گاهی (حدود یک در
         * بیست) و در هرس روزانه اجرا می‌شود؛ نتیجه نهایی یکسان است.
         */
        if ( 1 === wp_rand( 1, 20 ) ) {
            self::trim_logs();
        }
    }

    /** نگه‌داشتن فقط آخرین N لاگ. */
    public static function trim_logs(): void {
        global $wpdb;
        $table    = Hodima_Crawler_DB_Schema::get_logs_table();
        $max_logs = max( 50, (int) ( Hodima_GI_Helper::get_settings()['max_logs_count'] ?? 200 ) );

        $threshold = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$table} ORDER BY id DESC LIMIT 1 OFFSET %d", $max_logs
        ) );

        if ( null !== $threshold ) {
            $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id <= %d", (int) $threshold ) );
        }
    }

    public static function get_system_logs( int $limit = 50 ): array {
        global $wpdb; $table = Hodima_Crawler_DB_Schema::get_logs_table();
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d", $limit), ARRAY_A) ?? [];
    }

    public static function get_system_logs_paginated( int $page = 1, int $per_page = 15 ): array {
        global $wpdb; $table = Hodima_Crawler_DB_Schema::get_logs_table();
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        $offset = ($page - 1) * $per_page;
        $items = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d", $per_page, $offset), ARRAY_A) ?? [];
        return [ 'items' => $items, 'total' => $total, 'max_pages' => (int) ceil($total / max(1, $per_page)) ];
    }
}
