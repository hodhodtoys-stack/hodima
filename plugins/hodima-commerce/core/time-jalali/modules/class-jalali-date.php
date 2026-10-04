<?php
declare( strict_types=1 );
namespace Hodima\Core\Time_Jalali\Modules;

use WC_DateTime;
defined( 'ABSPATH' ) || exit;

final class Jalali_Date {
    private array $date_cache = [];
    
    private const PERSIAN_MONTHS = [1=>'فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
    private const PERSIAN_DAYS = ['Saturday'=>'شنبه','Sunday'=>'یکشنبه','Monday'=>'دوشنبه','Tuesday'=>'سه‌شنبه','Wednesday'=>'چهارشنبه','Thursday'=>'پنجشنبه','Friday'=>'جمعه'];

    public function __construct() {
        add_filter( 'wc_format_datetime', [ $this, 'convert_to_jalali_html5' ], 10, 2 );
        add_filter( 'woocommerce_rest_prepare_shop_order_object', [ $this, 'inject_jalali_to_rest_api' ], 10, 3 );
        
        add_filter( 'wp_date', [ $this, 'convert_wp_date_globally' ], 10, 4 );
        add_filter( 'date_i18n', [ $this, 'convert_date_i18n_globally' ], 10, 4 );
        
        add_filter( 'get_comment_date', [ $this, 'convert_comment_date' ], 10, 3 );
        add_filter( 'get_comment_time', [ $this, 'convert_comment_time' ], 10, 5 );
        
        add_filter( 'get_the_date', [ $this, 'convert_post_date' ], 10, 3 );
        add_filter( 'get_the_time', [ $this, 'convert_post_time' ], 10, 3 );
    }

    public function convert_comment_date( string $date, string $format, $comment ): string {
        if ( $this->is_machine_format( $format ) ) {
            return $date;
        }
        if ( isset( $comment->comment_date ) ) {
            try {
                $format = $format ?: get_option( 'date_format' );
                $timestamp = strtotime( $comment->comment_date );
                return $this->to_persian_numbers( $this->format_jalali_date( (int) $timestamp, $format ) );
            } catch ( \Throwable $e ) {}
        }
        return $date;
    }

    public function convert_comment_time( string $time, string $format, bool $gmt, bool $translate, $comment ): string {
        if ( $this->is_machine_format( $format ) ) {
            return $time;
        }
        if ( isset( $comment->comment_date ) ) {
            try {
                $format = $format ?: get_option( 'time_format' );
                $timestamp = strtotime( $comment->comment_date );
                return $this->to_persian_numbers( $this->format_jalali_date( (int) $timestamp, $format ) );
            } catch ( \Throwable $e ) {}
        }
        return $time;
    }

    public function convert_post_date( string $the_date, string $format, $post ): string {
        if ( $this->is_machine_format( $format ) ) {
            return $the_date;
        }
        try {
            $post = get_post( $post );
            if ( $post ) {
                $format = $format ?: get_option( 'date_format' );
                $timestamp = strtotime( $post->post_date );
                return $this->to_persian_numbers( $this->format_jalali_date( (int) $timestamp, $format ) );
            }
        } catch ( \Throwable $e ) {}
        return $the_date;
    }

    public function convert_post_time( string $the_time, string $format, $post ): string {
        if ( $this->is_machine_format( $format ) ) {
            return $the_time;
        }
        try {
            $post = get_post( $post );
            if ( $post ) {
                $format = $format ?: get_option( 'time_format' );
                $timestamp = strtotime( $post->post_date );
                return $this->to_persian_numbers( $this->format_jalali_date( (int) $timestamp, $format ) );
            }
        } catch ( \Throwable $e ) {}
        return $the_time;
    }

    public function convert_wp_date_globally( string $date, string $format, int $timestamp, mixed $timezone ): string {
        if ( $this->is_machine_format( $format ) ) {
            return $date;
        }
        try { 
            /*
             * باگ قبلی: wp_date یک timestamp واقعی (UTC) می‌دهد ولی format_jalali_date
             * با gmdate می‌سازد؛ پس ساعت به وقت UTC (۳:۳۰ عقب‌تر از تهران) و بین
             * ۰۰:۰۰ تا ۰۳:۳۰ روز هم اشتباه بود. حالا اختلاف منطقه زمانی مقصد در همان
             * لحظه اضافه می‌شود. date_i18n هم از همین مسیر با منطقه UTC رد می‌شود
             * (اختلاف صفر)، پس دوبار جابه‌جا نمی‌شود.
             */
            $tz    = $timezone instanceof \DateTimeZone ? $timezone : wp_timezone();
            $local = $timestamp + $tz->getOffset( new \DateTimeImmutable( '@' . $timestamp ) );
            return $this->to_persian_numbers( $this->format_jalali_date( $local, $format ) ); 
        } catch ( \Throwable $e ) { 
            return $date; 
        }
    }

    public function convert_date_i18n_globally( string $date, string $format, int $timestamp, bool $gmt ): string {
        if ( $this->is_machine_format( $format ) ) {
            return $date;
        }
        try { 
            return $this->to_persian_numbers( $this->format_jalali_date( $timestamp, $format ) ); 
        } catch ( \Throwable $e ) { 
            return $date; 
        }
    }

    public function inject_jalali_to_rest_api( $response, $object, $request ) {
        try {
            $data = $response->get_data();
            $date_fields = [ 'date_created', 'date_modified', 'date_completed', 'date_paid' ];
            $format = get_option( 'date_format', 'Y/m/d' );
            
            foreach ( $date_fields as $field ) {
                if ( ! empty( $data[$field] ) ) {
                    $wc_date = new WC_DateTime( $data[$field] );
                    $data[ $field . '_jalali' ] = $this->to_persian_numbers( $this->format_jalali_date( $wc_date->getOffsetTimestamp(), $format ) );
                }
            }
            $response->set_data( $data );
        } catch ( \Throwable $e ) {}
        return $response;
    }

    public function convert_to_jalali_html5( string $formatted_date, mixed $date_obj ): string {
        if ( ! $date_obj instanceof WC_DateTime ) return $formatted_date;
        try {
            $timestamp   = $date_obj->getOffsetTimestamp();
            $format      = get_option( 'date_format', 'Y/m/d' );
            $jalali_date = $this->to_persian_numbers( $this->format_jalali_date( $timestamp, $format ) );
            $iso_date    = gmdate( 'c', $timestamp );

            if ( function_exists( 'is_wc_endpoint_url' ) && ! is_admin() && ! is_wc_endpoint_url() && ! is_checkout() && ! is_account_page() ) {
                return $jalali_date;
            }
            
            return sprintf( 
                '<time datetime="%s" class="hodima-wc-date" data-timestamp="%d">%s</time>', 
                esc_attr( $iso_date ), 
                esc_attr( (string) $timestamp ), 
                esc_html( $jalali_date ) 
            );
        } catch ( \Throwable $e ) { return $formatted_date; }
    }

    /**
     * قالب ماشین‌خوان (timestamp، ISO 8601، RFC) نباید شمسی یا فارسی شود.
     * باگ قبلی: get_the_time( 'U' ) به‌جای عدد timestamp حرف «U» (یا رقم فارسی)
     * برمی‌گرداند؛ ابزارک «فعالیت» پیشخوان وردپرس آن را به gmdate() می‌دهد و با
     * TypeError کل صفحه پیشخوان از کار می‌افتاد. هر کدی که تاریخ را برای مقایسه
     * یا ذخیره می‌خواهد همین قالب‌ها را به کار می‌برد.
     */
    private function is_machine_format( string $format ): bool {
        static $machine = null;
        $machine ??= [ 'U', 'G', 'u', 'v', 'Z', 'c', 'r', DATE_ATOM, DATE_COOKIE, DATE_ISO8601, DATE_RFC822, DATE_RFC850, DATE_RFC1036, DATE_RFC1123, DATE_RFC7231, DATE_RFC2822, DATE_RFC3339, DATE_RFC3339_EXTENDED, DATE_RSS, DATE_W3C ];
        return in_array( $format, $machine, true ) || str_contains( $format, '\\T' );
    }

    private function to_persian_numbers( string $string ): string {
        return strtr( $string, ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹'] );
    }

    private function format_jalali_date( int $timestamp, string $format ): string {
        $cache_key = md5( (string) $timestamp . $format );
        if ( isset( $this->date_cache[ $cache_key ] ) ) return $this->date_cache[ $cache_key ];

        $gy = (int) gmdate( 'Y', $timestamp ); 
        $gm = (int) gmdate( 'm', $timestamp ); 
        $gd = (int) gmdate( 'd', $timestamp );
        $day_name = gmdate( 'l', $timestamp );
        
        [ $jy, $jm, $jd ] = $this->gregorian_to_jalali( $gy, $gm, $gd );

        $replacements = [
            'Y' => $jy, 'y' => substr( (string) $jy, 2, 2 ), 'm' => sprintf( '%02d', $jm ), 'n' => $jm, 'd' => sprintf( '%02d', $jd ), 'j' => $jd,
            'F' => self::PERSIAN_MONTHS[ $jm ] ?? '', 'l' => self::PERSIAN_DAYS[ $day_name ] ?? $day_name,
            'a' => '', 'A' => '', 
            'h' => gmdate( 'H', $timestamp ), 'g' => gmdate( 'G', $timestamp ),
            'H' => gmdate( 'H', $timestamp ), 'i' => gmdate( 'i', $timestamp ), 's' => gmdate( 's', $timestamp ), 'G' => gmdate( 'G', $timestamp ),
        ];

        $output = ''; 
        $length = strlen( $format );
        
        for ( $i = 0; $i < $length; $i++ ) {
            $char = $format[ $i ];
            if ( $char === '\\' ) { 
                $i++; 
                if ( $i < $length ) $output .= $format[ $i ]; 
                continue; 
            }
            $output .= $replacements[ $char ] ?? $char;
        }
        
        $output = trim( preg_replace( '/\s+/', ' ', $output ) );
        $this->date_cache[ $cache_key ] = $output;
        
        return $output;
    }

    private function gregorian_to_jalali( int $gy, int $gm, int $gd ): array {
        $g_d_m = [ 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 ];
        $gy2   = ( $gm > 2 ) ? ( $gy + 1 ) : $gy;
        $days  = 355666 + ( 365 * $gy ) + (int)( ( $gy2 + 3 ) / 4 ) - (int)( ( $gy2 + 99 ) / 100 ) + (int)( ( $gy2 + 399 ) / 400 ) + $gd + $g_d_m[ $gm - 1 ];
        
        $jy    = -1595 + 33 * (int)( $days / 12053 ); 
        $days %= 12053;
        $jy   += 4 * (int)( $days / 1461 ); 
        $days %= 1461;
        
        if ( $days > 365 ) { 
            $jy += (int)( ( $days - 1 ) / 365 ); 
            $days = ( $days - 1 ) % 365; 
        }
        
        $jm = ( $days < 186 ) ? 1 + (int)( $days / 31 ) : 7 + (int)( ( $days - 186 ) / 30 );
        $jd = 1 + ( ( $days < 186 ) ? ( $days % 31 ) : ( ( $days - 186 ) % 30 ) );
        
        return [ $jy, $jm, $jd ];
    }
}