<?php
/**
 * Hodima Phone Lead Form
 * Path: components/phone/phone-form.php
 * Version: 4.0.0 (PHP 8.4 Optimized)
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'hodima_PHONE_URI', hodima_URI . '/components/phone' );
define( 'hodima_PHONE_DIR', hodima_DIR . '/components/phone' );

/* =====================================================================
 * دارایی‌ها و امنیت
 * ===================================================================== */

add_action( 'wp_enqueue_scripts', 'hodima_phone_assets' );

function hodima_phone_assets(): void {
    $ver = fn( string $f ): string => file_exists( hodima_PHONE_DIR . '/' . $f ) 
        ? (string) filemtime( hodima_PHONE_DIR . '/' . $f ) 
        : '4.0.0';

    wp_enqueue_style( 'hodima-phone-style', hodima_PHONE_URI . '/phone-style.css', [], $ver( 'phone-style.css' ) );
    wp_register_script( 'hodima-phone-script', hodima_PHONE_URI . '/phone-script.js', [], $ver( 'phone-script.js' ), true );

    wp_localize_script( 'hodima-phone-script', 'hodimaAjax', [
        'ajaxurl' => admin_url( 'admin-ajax.php' ),
    ] );
}

add_action( 'wp_ajax_hodima_phone_nonce', 'hodima_generate_phone_nonce' );
add_action( 'wp_ajax_nopriv_hodima_phone_nonce', 'hodima_generate_phone_nonce' );
function hodima_generate_phone_nonce(): void {
    wp_send_json_success( wp_create_nonce( 'hodima_phone_secure' ) );
}

/* =====================================================================
 * نوع پست لیدها
 * ===================================================================== */

add_action( 'init', 'hodima_register_phone_leads_cpt' );

function hodima_register_phone_leads_cpt(): void {
    register_post_type( 'hodima_phone_lead', [
        'labels'              => [
            'name'          => 'شماره‌های تماس',
            'singular_name' => 'شماره تماس',
            'menu_name'     => 'شماره‌های تماس',
        ],
        'public'              => false,
        'show_ui'             => true,
        'show_in_rest'        => false,
        'exclude_from_search' => true,
        'menu_icon'           => 'dashicons-phone',
        'supports'            => [ 'title' ],
        'capabilities'        => [ 'create_posts' => 'do_not_allow' ],
        'map_meta_cap'        => true,
    ] );
}

/* =====================================================================
 * توابع کمکی
 * ===================================================================== */

function hodima_phone_normalize( string $raw ): string {
    // استفاده از mb_trim معرفی شده در PHP 8.4 برای پاکسازی فاصله‌های یونیکد
    $raw = function_exists('mb_trim') ? mb_trim($raw) : trim($raw);
    
    $digits = strtr( $raw, [
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ] );

    $digits = (string) preg_replace( '/\D+/', '', $digits );

    if ( str_starts_with( $digits, '0098' ) ) {
        $digits = '0' . substr( $digits, 4 );
    } elseif ( str_starts_with( $digits, '98' ) && strlen( $digits ) >= 10 ) {
        $digits = '0' . substr( $digits, 2 );
    } elseif ( strlen( $digits ) === 10 && ! str_starts_with( $digits, '0' ) ) {
        $digits = '0' . $digits;
    }

    return (bool) preg_match( '/^0\d{9,10}$/', $digits ) ? $digits : '';
}

/**
 * نسخه قبلی به CF-Connecting-IP و X-Forwarded-For از *هر* درخواستی اعتماد
 * می‌کرد؛ با عوض کردن این هدرها در هر درخواست، محدودیت نرخ کاملا دور زده
 * می‌شد. حالا از تابع مرکزی قالب استفاده می‌شود که فقط پشت Cloudflare
 * واقعی به این هدرها اعتماد می‌کند.
 */
function hodima_get_real_ip(): string {
    return function_exists( 'hodima_get_client_ip' )
        ? hodima_get_client_ip()
        : (string) filter_var( $_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP );
}

/**
 * یک ارسال در هر ۱۵ ثانیه برای هر بازدیدکننده.
 *
 * قبلا همه IPها در یک option مشترک نگه داشته می‌شدند: خواندن-تغییر-نوشتن
 * غیراتمیک (دو درخواست همزمان هر دو رد می‌شدند) و یک UPDATE روی
 * wp_options در هر ارسال. ترنزینت جداگانه هر بازدیدکننده هر دو را حل می‌کند.
 * ($ip برای سازگاری امضای تابع نگه داشته شده است.)
 */
function hodima_is_rate_limited( string $ip ): bool {
    $key = function_exists( 'hodima_rate_limit_key' )
        ? hodima_rate_limit_key( 'hodima_phone_rl_' )
        : 'hodima_phone_rl_' . md5( $ip );

    if ( false !== get_transient( $key ) ) {
        return true;
    }

    set_transient( $key, 1, (int) apply_filters( 'hodima_phone_rate_window', 15 ) );
    return false;
}

/** مقدار رشته‌ای از $_POST؛ آرایه (مثل phone[]=) رشته خالی می‌شود نه TypeError. */
function hodima_phone_post_string( string $key ): string {
    $value = $_POST[ $key ] ?? '';
    return is_string( $value ) ? wp_unslash( $value ) : '';
}

/* =====================================================================
 * فرم
 * ===================================================================== */

add_shortcode( 'hodima_phone_form', 'render_hodima_phone_form' );

function render_hodima_phone_form(): string {
    static $instance = 0;
    $uid = 'hodima-phone-' . ++$instance;

    wp_enqueue_script( 'hodima-phone-script' );

    $status  = sanitize_key( wp_unslash( $_GET['hodima_phone'] ?? '' ) );
    $form_id = sanitize_key( wp_unslash( $_GET['form_id'] ?? '' ) );
    $is_this = ( $form_id === $uid );

    // استفاده از match expression (PHP 8.0+)
    $message = $is_this ? match ( $status ) {
        'ok'   => [ 'success', 'شماره شما ثبت شد. همکاران ما به‌زودی تماس می‌گیرند.' ],
        'bad'  => [ 'error', 'شماره وارد شده نامعتبر است.' ],
        'wait' => [ 'error', 'لطفا کمی بعد دوباره تلاش کنید.' ],
        'err'  => [ 'error', 'خطا در ثبت درخواست. لطفا دوباره تلاش کنید.' ],
        default => null,
    } : null;

    ob_start();
    ?>
    <div class="hodima-phone-wrapper" id="<?= esc_attr( $uid ); ?>">
        <p class="hodima-phone-title" id="<?= esc_attr( $uid ); ?>-title">
            برای مشاوره و دریافت کاتالوگ شماره تماس وارد کنید
        </p>

        <form class="hodima-phone-form" method="post" action="<?= esc_url( admin_url( 'admin-post.php' ) ); ?>" novalidate>
            <input type="hidden" name="action" value="submit_hodima_phone">
            <input type="hidden" name="form_uid" value="<?= esc_attr( $uid ); ?>">
            
            <label class="hodima-phone-sr" for="<?= esc_attr( $uid ); ?>-input">شماره تماس</label>
            <input
                type="tel"
                id="<?= esc_attr( $uid ); ?>-input"
                name="phone"
                class="hodima-phone-input"
                placeholder="شماره تماس"
                inputmode="tel"
                autocomplete="tel"
                maxlength="20"
                required
                aria-describedby="<?= esc_attr( $uid ); ?>-title <?= esc_attr( $uid ); ?>-msg"
            >

            <div class="hodima-phone-hp" aria-hidden="true">
                <label for="<?= esc_attr( $uid ); ?>-website">وب‌سایت</label>
                <input type="text" id="<?= esc_attr( $uid ); ?>-website" name="website" tabindex="-1" autocomplete="off">
            </div>

            <button type="submit" class="hodima-phone-submit">ثبت درخواست</button>
        </form>

        <div
            class="hodima-phone-msg<?= $message ? ' hodima-msg-' . esc_attr( $message[0] ) : ''; ?>"
            id="<?= esc_attr( $uid ); ?>-msg"
            role="status"
            aria-live="polite"
            <?= $message ? '' : 'hidden'; ?>
        ><?= $message ? esc_html( $message[1] ) : ''; ?></div>
    </div>
    <?php
    return ob_get_clean() ?: '';
}

/* =====================================================================
 * پردازش درخواست
 * ===================================================================== */

add_action( 'wp_ajax_submit_hodima_phone', 'handle_hodima_phone_submission' );
add_action( 'wp_ajax_nopriv_submit_hodima_phone', 'handle_hodima_phone_submission' );
add_action( 'admin_post_submit_hodima_phone', 'hodima_phone_handle_nojs' );
add_action( 'admin_post_nopriv_submit_hodima_phone', 'hodima_phone_handle_nojs' );

function hodima_phone_process( bool $is_ajax ): string {
    if ( ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'POST' ) return 'err';

    if ( $is_ajax && ! wp_verify_nonce( hodima_phone_post_string( 'security_token' ), 'hodima_phone_secure' ) ) return 'err';
    if ( ! empty( $_POST['website'] ) ) return 'spam';
    if ( $is_ajax && empty( $_POST['js_time'] ) ) return 'spam';

    $ip = hodima_get_real_ip();
    if ( hodima_is_rate_limited( $ip ) ) return 'wait';

    $phone = hodima_phone_normalize( hodima_phone_post_string( 'phone' ) );
    if ( $phone === '' ) return 'bad';

    $page_url   = hodima_phone_post_string( 'page_url' );
    $page_url   = esc_url_raw( '' !== $page_url ? $page_url : (string) wp_get_raw_referer(), [ 'http', 'https' ] );
    // این آدرس در پیشخوان لینک‌شدنی است؛ آدرس سایت دیگر (فیشینگ) پذیرفته نمی‌شود.
    if ( wp_parse_url( $page_url, PHP_URL_HOST ) !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
        $page_url = '';
    }
    $page_title = mb_substr( sanitize_text_field( hodima_phone_post_string( 'page_title' ) ), 0, 200 );
    $time       = current_time( 'Y-m-d H:i' );

    $existing = get_posts( [
        'post_type'      => 'hodima_phone_lead',
        'title'          => $phone,
        'post_status'    => 'any',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ] );

    if ( ! empty( $existing ) ) {
        $lead_id = (int) $existing[0];

        wp_update_post( [
            'ID'            => $lead_id,
            'post_date'     => current_time( 'mysql' ),
            'post_date_gmt' => current_time( 'mysql', true ),
        ] );

        $data = get_post_meta( $lead_id, '_hodima_lead_data', true );
        if ( ! is_array( $data ) ) {
            $data = [
                'history'   => [],
                'count'     => max( 1, (int) get_post_meta( $lead_id, 'hodima_request_count', true ) ),
                'last_url'  => (string) get_post_meta( $lead_id, 'hodima_page_url', true ),
                'last_time' => (string) get_post_meta( $lead_id, 'hodima_submit_time', true )
            ];
        }

        $data['history'][] = [ 'url' => $page_url, 'title' => $page_title, 'time' => $time ];
        $data['history']   = array_slice( $data['history'], -10 );
        $data['count']     = ( $data['count'] ?? 1 ) + 1;
        $data['last_url']  = $page_url;
        $data['last_title']= $page_title;
        $data['last_time'] = $time;

        update_post_meta( $lead_id, '_hodima_lead_data', $data );
        delete_post_meta( $lead_id, 'hodima_seen' ); // بازگشت کامل به وضعیت جدید

    } else {
        $lead_id = wp_insert_post( [
            'post_type'   => 'hodima_phone_lead',
            'post_title'  => $phone,
            'post_status' => 'publish',
        ], true );

        if ( ! is_wp_error( $lead_id ) ) {
            update_post_meta( $lead_id, '_hodima_lead_data', [
                'history'   => [],
                'count'     => 1,
                'last_url'  => $page_url,
                'last_title'=> $page_title,
                'last_time' => $time
            ] );
        }
    }

    do_action( 'hodima_phone_lead_received', (int) $lead_id, $phone );
    return 'ok';
}

function handle_hodima_phone_submission(): void {
    $result = hodima_phone_process( true );
    if ( $result === 'ok' || $result === 'spam' ) {
        wp_send_json_success( [ 'message' => 'شماره شما ثبت شد. همکاران ما به‌زودی تماس می‌گیرند.' ] );
    }
    
    $messages = [
        'bad'  => 'شماره تماس نامعتبر است.',
        'wait' => 'لطفا کمی بعد دوباره تلاش کنید.',
    ];
    wp_send_json_error( [ 'message' => $messages[ $result ] ?? 'خطا در ثبت درخواست. لطفا دوباره تلاش کنید.' ], $result === 'wait' ? 429 : 400 );
}

function hodima_phone_handle_nojs(): void {
    $result = hodima_phone_process( false );
    $result = ( $result === 'spam' ) ? 'ok' : $result;
    
    $uid  = sanitize_key( hodima_phone_post_string( 'form_uid' ) ) ?: 'hodima-phone-1';
    $back = wp_get_raw_referer() ?: home_url( '/' );
    $back = add_query_arg( [ 'hodima_phone' => $result, 'form_id' => $uid ], remove_query_arg( [ 'hodima_phone', 'form_id' ], $back ) );

    wp_safe_redirect( $back . '#' . $uid );
    exit;
}

/* =====================================================================
 * پیشخوان وردپرس
 * ===================================================================== */

function hodima_lead_state( int $post_id ): string {
    return get_post_meta( $post_id, 'hodima_seen', true ) ? 'seen' : 'new';
}

add_action( 'load-post.php', function (): void {
    $post_id = absint( $_GET['post'] ?? 0 );
    if ( $post_id && get_post_type( $post_id ) === 'hodima_phone_lead' && current_user_can( 'edit_post', $post_id ) ) {
        update_post_meta( $post_id, 'hodima_seen', 1 );
    }
} );

add_filter( 'manage_hodima_phone_lead_posts_columns', function ( array $columns ): array {
    $new = [];
    foreach ( $columns as $key => $title ) {
        if ( $key === 'cb' ) {
            $new[ $key ] = $title;
            $new['hodima_state'] = '<span class="screen-reader-text">وضعیت</span>';
            continue;
        }
        $new[ $key ] = $title;
        if ( $key === 'title' ) {
            $new['hodima_page']  = 'صفحه';
            $new['hodima_count'] = 'تعداد درخواست';
            $new['hodima_time']  = 'آخرین درخواست';
        }
    }
    unset( $new['date'] );
    return $new;
} );

add_action( 'manage_hodima_phone_lead_posts_custom_column', function ( string $column, int $post_id ): void {
    $state = hodima_lead_state( $post_id );
    $data = get_post_meta( $post_id, '_hodima_lead_data', true );
    $is_new_format = is_array($data);

    match ( $column ) {
        'hodima_state' => print( $state !== 'seen' ? '<span class="hodima-dot" title="جدید"><span class="screen-reader-text">جدید</span></span>' : '' ),
        
        'hodima_page' => (function() use ($post_id, $data, $is_new_format) {
            $page_title = $is_new_format ? ( $data['last_title'] ?? '' ) : (string) get_post_meta( $post_id, 'hodima_page_title', true );
            $page_url   = $is_new_format ? ( $data['last_url'] ?? '' ) : (string) get_post_meta( $post_id, 'hodima_page_url', true );
            if ( $page_url !== '' ) {
                printf( '<a href="%s" target="_blank">%s</a>', esc_url( $page_url ), esc_html( $page_title ?: $page_url ) );
            }
        })(),
        
        'hodima_count' => print( (int) ( $is_new_format ? ( $data['count'] ?? 1 ) : max( 1, (int) get_post_meta( $post_id, 'hodima_request_count', true ) ) ) ),
        
        'hodima_time' => print( esc_html( $is_new_format ? ( $data['last_time'] ?? '' ) : (string) get_post_meta( $post_id, 'hodima_submit_time', true ) ) ),
        
        default => null
    };
}, 10, 2 );

// حذف کامل اکشن‌های زیر ردیف (شامل تماس و واتس‌اپ)
add_filter( 'post_row_actions', fn( array $actions, WP_Post $post ): array => 
    $post->post_type === 'hodima_phone_lead' ? [] : $actions
, 10, 2 );

add_filter( 'post_class', function ( array $classes, array $class, int $post_id ): array {
    if ( is_admin() && get_post_type( $post_id ) === 'hodima_phone_lead' ) {
        $classes[] = 'hodima-lead-' . hodima_lead_state( $post_id );
    }
    return $classes;
}, 10, 3 );

add_filter( 'views_edit-hodima_phone_lead', function ( array $views ): array {
    global $wpdb;
    
    $total_seen = (int) $wpdb->get_var("SELECT COUNT(post_id) FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = 'hodima_seen' AND p.post_type = 'hodima_phone_lead'");
    $total_all = (int) wp_count_posts('hodima_phone_lead')->publish;
    $total_unseen = max(0, $total_all - $total_seen);

    $current = sanitize_key( (string) ( $_GET['hodima_state'] ?? '' ) );
    $base    = admin_url( 'edit.php?post_type=hodima_phone_lead' );

    $views['hodima_unseen'] = sprintf(
        '<a href="%s"%s>جدید <span class="count">(%s)</span></a>',
        esc_url( add_query_arg( 'hodima_state', 'unseen', $base ) ),
        $current === 'unseen' ? ' class="current"' : '',
        number_format_i18n( $total_unseen )
    );
    $views['hodima_seen'] = sprintf(
        '<a href="%s"%s>مشاهده‌شده <span class="count">(%s)</span></a>',
        esc_url( add_query_arg( 'hodima_state', 'seen', $base ) ),
        $current === 'seen' ? ' class="current"' : '',
        number_format_i18n( $total_seen )
    );
    return $views;
} );

add_action( 'pre_get_posts', function ( WP_Query $q ): void {
    if ( ! is_admin() || ! $q->is_main_query() || $q->get( 'post_type' ) !== 'hodima_phone_lead' ) return;

    $state = sanitize_key( (string) ( $_GET['hodima_state'] ?? '' ) );
    if ( $state === 'unseen' ) {
        $q->set( 'meta_query', [ [ 'key' => 'hodima_seen', 'compare' => 'NOT EXISTS' ] ] );
    } elseif ( $state === 'seen' ) {
        $q->set( 'meta_query', [ [ 'key' => 'hodima_seen', 'compare' => 'EXISTS' ] ] );
    }

    if ( ! $q->get( 'orderby' ) ) {
        $q->set( 'orderby', 'date' );
        $q->set( 'order', 'DESC' );
    }
} );

add_filter( 'bulk_actions-edit-hodima_phone_lead', function ( array $actions ): array {
    unset( $actions['edit'] );
    return [ 'hodima_mark_seen' => 'علامت: مشاهده شد', 'hodima_mark_unseen' => 'علامت: جدید' ] + $actions;
} );

add_filter( 'handle_bulk_actions-edit-hodima_phone_lead', function ( string $redirect, string $action, array $ids ): string {
    if ( ! in_array( $action, [ 'hodima_mark_seen', 'hodima_mark_unseen' ], true ) ) return $redirect;
    foreach ( $ids as $id ) {
        if ( current_user_can( 'edit_post', (int) $id ) ) {
            if ( $action === 'hodima_mark_seen' ) update_post_meta( (int) $id, 'hodima_seen', 1 );
            else delete_post_meta( (int) $id, 'hodima_seen' );
        }
    }
    return add_query_arg( 'hodima_bulk', count( $ids ), $redirect );
}, 10, 3 );

add_action( 'admin_head-edit.php', function (): void {
    if ( ( get_current_screen()->post_type ?? '' ) !== 'hodima_phone_lead' ) return;
    ?>
    <style>
        .column-hodima_state { width: 40px; text-align: center; }
        .column-hodima_count { width: 100px; text-align: center; }
        .column-hodima_time { width: 150px; }
        .hodima-dot { display: inline-block; width: 12px; height: 12px; margin-top: 5px; border-radius: 50%; background: #2f7a55; box-shadow: 0 0 0 3px rgba(47, 122, 85, 0.18); }
        .hodima-lead-seen .row-title { font-weight: 400; opacity: .6; }
        .hodima-lead-new .row-title { font-weight: 700; }
    </style>
    <?php
} );

/* =====================================================================
 * خروجی CSV 
 * ===================================================================== */

add_action( 'restrict_manage_posts', function ( string $post_type ): void {
    if ( $post_type !== 'hodima_phone_lead' || ! current_user_can( 'manage_options' ) ) return;
    $url = wp_nonce_url( add_query_arg( [ 'export_hodima_phones' => 1 ], admin_url( 'edit.php?post_type=hodima_phone_lead' ) ), 'hodima_export_csv' );
    echo '<a href="' . esc_url( $url ) . '" class="button button-primary">دانلود CSV</a>';
} );

add_action( 'admin_init', 'hodima_export_phones_csv' );

function hodima_export_phones_csv(): void {
    if ( empty( $_GET['export_hodima_phones'] ) ) return;
    $nonce = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) );
    if ( ! wp_verify_nonce( $nonce, 'hodima_export_csv' ) || ! current_user_can( 'manage_options' ) ) return;

    global $wpdb;
    
    nocache_headers();
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename=hodima-leads-' . gmdate( 'Y-m-d' ) . '.csv' );

    $out = fopen( 'php://output', 'w' );
    fwrite( $out, "\xEF\xBB\xBF" );
    fputcsv( $out, [ 'شماره', 'تعداد درخواست', 'صفحه', 'لینک صفحه', 'زمان آخرین درخواست', 'وضعیت' ] );

    $results = $wpdb->get_results( "
        SELECT p.post_title, 
               MAX(CASE WHEN pm.meta_key = '_hodima_lead_data' THEN pm.meta_value END) AS json_data,
               MAX(CASE WHEN pm.meta_key = 'hodima_seen' THEN pm.meta_value END) AS is_seen,
               MAX(CASE WHEN pm.meta_key = 'hodima_request_count' THEN pm.meta_value END) AS old_count,
               MAX(CASE WHEN pm.meta_key = 'hodima_page_title' THEN pm.meta_value END) AS old_title,
               MAX(CASE WHEN pm.meta_key = 'hodima_page_url' THEN pm.meta_value END) AS old_url,
               MAX(CASE WHEN pm.meta_key = 'hodima_submit_time' THEN pm.meta_value END) AS old_time
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
        WHERE p.post_type = 'hodima_phone_lead' AND p.post_status = 'publish'
        GROUP BY p.ID
        ORDER BY p.post_date DESC
    " );

    $safe = fn( string|null $v ): string => ( $v !== '' && $v !== null && in_array( $v[0], ['=','+','-','@',"\t","\r"], true ) ) ? "'" . $v : (string) $v;

    foreach ( $results as $row ) {
        $data = ! empty( $row->json_data ) ? maybe_unserialize( $row->json_data ) : [];
        
        $count = $data['count'] ?? $row->old_count ?? 1;
        $title = $data['last_title'] ?? $row->old_title ?? '';
        $url   = $data['last_url'] ?? $row->old_url ?? '';
        $time  = $data['last_time'] ?? $row->old_time ?? '';
        $state = ! empty( $row->is_seen ) ? 'مشاهده شده' : 'جدید';

        fputcsv( $out, [
            '="' . preg_replace( '/\D/', '', $row->post_title ) . '"',
            $count,
            $safe( $title ),
            $safe( $url ),
            $safe( $time ),
            $state
        ] );
    }

    fclose( $out );
    exit;
}