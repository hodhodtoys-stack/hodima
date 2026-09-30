<?php
declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) exit;

final class Hodima_Core_Helpers {

    private static function cf_ranges(): array {
        $ranges = [
            '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
            '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
            '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
            '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
        ];
        return apply_filters( 'hodima_cf_ip_ranges', $ranges );
    }

    private static function is_ip_in_cidr( string $ip, string $cidr ): bool {
        if ( strpos( $cidr, '/' ) === false ) return false;
        [ $subnet, $mask ] = explode( '/', $cidr );
        $mask = (int) $mask;
        if ( $mask < 0 || $mask > 32 ) return false;
        $ip_long     = ip2long( $ip );
        $subnet_long = ip2long( $subnet );
        if ( $ip_long === false || $subnet_long === false ) return false;
        $mask_long = $mask === 0 ? 0 : ( -1 << ( 32 - $mask ) );
        return ( $ip_long & $mask_long ) === ( $subnet_long & $mask_long );
    }

    private static function is_cloudflare_ip( string $ip ): bool {
        foreach ( self::cf_ranges() as $cidr ) {
            if ( self::is_ip_in_cidr( $ip, $cidr ) ) return true;
        }
        return false;
    }

    public static function get_client_ip(): string {
        // منبع واحد حقیقت در inc/helpers.php (با اعتبارسنجی فرمت IP)
        if ( function_exists( 'hodima_get_client_ip' ) ) {
            return hodima_get_client_ip();
        }
        $real_ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if ( $real_ip && self::is_cloudflare_ip( $real_ip ) ) {
            if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
                return sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
            }
            if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
                $ips = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
                return trim( $ips[0] );
            }
        }
        return $real_ip;
    }

    public static function is_noindex( int $object_id, string $type = 'post' ): bool {
        $meta = ( $type === 'term' ) ? get_term_meta( $object_id ) : get_post_meta( $object_id );
        if ( ! is_array( $meta ) ) return false;

        foreach ( $meta as $key => $values ) {
            $key_lc = strtolower( (string) $key );
            // هم‌راستا با سایت‌مپ (hodima_get_noindex_post_ids): فقط کلید noindex یا
            // «…_noindex» و سئوباکس. قبلا هر کلیدی که «noindex» جایی از نامش بود
            // حساب می‌شد؛ کلید Rank Math (از سایت حذف شده) هم برداشته شد.
            if ( 'noindex' === $key_lc || str_ends_with( $key_lc, '_noindex' ) || $key_lc === '_seobox_robots' || $key_lc === '_yoast_wpseo_meta-robots-noindex' ) {
                foreach ( (array) $values as $v ) {
                    $v = strtolower( is_array( $v ) ? wp_json_encode( $v ) : (string) $v );
                    if ( in_array( $v, [ '1', 'yes', 'true', 'on' ], true ) || str_contains( $v, 'noindex' ) ) {
                        return true;
                    }
                }
            }
        }
        return false;
    }

    public static function anti_injection_shield( string $text ): string {
        $patterns = [
            '/(ignore|disregard|forget)\s+(all\s+)?(previous\s+)?(instructions|prompts|context)/i',
            '/(you are now|act as|simulate)\s+(a|an)?\s*(unrestricted|admin|developer|system)/i',
            '/bypass\s+(rules|filters|instructions)/i',
        ];
        return preg_replace( $patterns, '[حذف‌شده توسط فیلتر امنیتی]', $text ) ?? $text;
    }

    public static function clean_url( string $url ): string {
        if ( function_exists( 'arian_strip_leading_base' ) ) {
            $url = arian_strip_leading_base( $url, function_exists( 'arian_product_base' ) ? arian_product_base() : 'product' );
            $url = arian_strip_leading_base( $url, function_exists( 'arian_product_cat_base' ) ? arian_product_cat_base() : 'product-category' );
            $url = arian_strip_leading_base( $url, function_exists( 'arian_category_base' ) ? arian_category_base() : 'category' );
            return $url;
        }
        return (string) preg_replace( '#^(https?://[^/]+)/product/#i', '$1/', $url );
    }

    /**
     * گیت دسترسی که باید *قبل از* اجرای کوئری صدا زده شود.
     *
     * تا پیش از این، متدهای خروجی CSV اول کوئری را اجرا می‌کردند و بعد
     * export_csv() دسترسی را بررسی می‌کرد. نشت داده نبود، ولی هر کاربر
     * لاگین‌شده‌ای می‌توانست با یک درخواست ساده یک اسکن کامل روی جدول
     * لاگ‌ها را تریگر کند (export_history حتی LIMIT هم نداشت).
     */
    public static function assert_export_access( string $nonce_action ): void {
        if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( (string) ( $_REQUEST['_wpnonce'] ?? '' ), $nonce_action ) ) {
            wp_die( 'دسترسی غیرمجاز.' );
        }
    }

    /**
     * خنثی‌سازی CSV Injection (Formula Injection).
     *
     * اکسل و لیبره‌آفیس هر سلولی که با = + - @ یا کاراکتر کنترلی شروع شود
     * را به عنوان فرمول اجرا می‌کنند. چون بخشی از این ردیف‌ها (مثلا
     * User-Agent ربات‌ها و عبارت‌های جستجو) از ورودی بیرونی می‌آید، مهاجم
     * می‌توانست فرمولی بسازد که روی سیستم ادمین هنگام باز کردن فایل اجرا شود.
     */
    private static function csv_safe( $value ): string {

        $value = (string) $value;

        if ( '' === $value ) {
            return $value;
        }

        if ( in_array( $value[0], [ '=', '+', '-', '@', "\t", "\r" ], true ) ) {
            return "'" . $value;
        }

        return $value;
    }

    public static function export_csv( string $nonce_action, array $header, array $rows, string $filename_prefix ): void {
        if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $_REQUEST['_wpnonce'] ?? '', $nonce_action ) ) {
            wp_die( 'دسترسی غیرمجاز.' );
        }
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename=' . $filename_prefix . '-' . date( 'Y-m-d' ) . '.csv' );
        $output = fopen( 'php://output', 'w' );
        fputs( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );
        fputcsv( $output, array_map( [ __CLASS__, 'csv_safe' ], $header ) );
        foreach ( $rows as $row ) {
            fputcsv( $output, array_map( [ __CLASS__, 'csv_safe' ], (array) $row ) );
        }
        fclose( $output );
        exit;
    }
}