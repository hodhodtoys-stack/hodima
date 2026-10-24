<?php
/**
 * Hodima Phone Lead Form
 * Path: components/phone/phone-form.php
 *
 * شورت‌کد [hodima_phone_form]، ثبت لید (admin-ajax و بدون جاوااسکریپت)، فهرست لیدها در پیشخوان و CSV.
 * زیر PHPCS/PHPStan/ESLint/Stylelint بدون baseline است (کد تازه باید تمیز باشد).
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const HODIMA_PHONE_URI = HODIMA_COMMERCE_URL . '/components/phone';
const HODIMA_PHONE_DIR = HODIMA_COMMERCE_DIR . '/components/phone';

/** متای داده لید (نام عوض نشود: لیدهای ذخیره‌شده سایت‌ها). */
const HODIMA_PHONE_META = '_hodima_lead_data';

/* =====================================================================
 * دارایی‌ها
 * ===================================================================== */

add_action( 'wp_enqueue_scripts', 'hodima_phone_assets' );

/**
 * فرم در فوتر همه صفحه‌هاست، پس CSS همه‌جا لازم است؛ JS فقط وقتی شورت‌کد اجرا شود (render) لود می‌شود.
 */
function hodima_phone_assets(): void {
    $ver = static fn( string $file ): string => is_file( HODIMA_PHONE_DIR . '/' . $file )
        ? (string) filemtime( HODIMA_PHONE_DIR . '/' . $file )
        : HODIMA_COMMERCE_VERSION;

    wp_enqueue_style( 'hodima-phone-style', HODIMA_PHONE_URI . '/phone-style.css', [], $ver( 'phone-style.css' ) );
    wp_register_script( 'hodima-phone-script', HODIMA_PHONE_URI . '/phone-script.js', [], $ver( 'phone-script.js' ), true );

    wp_localize_script( 'hodima-phone-script', 'hodimaAjax', [
        'ajaxurl' => admin_url( 'admin-ajax.php' ),
        // جاوااسکریپت قبل از ارسال این‌قدر صبر می‌کند تا ارسال «خیلی سریع» (ربات) از انسان جدا شود
        'waitMs'  => ( hodima_phone_min_seconds() * 1000 ) + 300,
    ] );
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
 * شماره
 * ===================================================================== */

/**
 * شماره را یکدست می‌کند: رقم فارسی/عربی، فاصله و خط تیره، پیش‌شماره +98 / 0098 / 98 و صفر اول نیامده.
 * فقط موبایل (09xxxxxxxxx) یا ثابت با کد شهر (0[1-8]xxxxxxxxx، ۱۱ رقم) معتبر است؛ وگرنه رشته خالی.
 * همین قاعده در phone-script.js (normalize) تکرار شده؛ هر تغییر هر دو جا. (قاعده موبایل همان Iran_Input
 * ماژول بومی‌سازی است؛ این ماژول مستقل است و به آن وابسته نیست.)
 */
function hodima_phone_normalize( string $raw ): string {
    $digits = strtr( $raw, [
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ] );

    $digits = (string) preg_replace( '/\D+/', '', $digits );

    if ( str_starts_with( $digits, '0098' ) ) {
        $digits = substr( $digits, 4 );
    } elseif ( str_starts_with( $digits, '98' ) && strlen( $digits ) >= 11 ) {
        $digits = substr( $digits, 2 );
    }

    if ( 10 === strlen( $digits ) && ! str_starts_with( $digits, '0' ) ) {
        $digits = '0' . $digits;
    }

    return 1 === preg_match( '/^(?:09\d{9}|0[1-8]\d{9})$/', $digits ) ? $digits : '';
}

/** مقدار رشته‌ای از $_POST؛ آرایه (مثل phone[]=) رشته خالی می‌شود نه TypeError. */
function hodima_phone_post_string( string $key ): string {
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- فرم عمومی؛ بررسی در hodima_phone_process (توکن امضاشده)
    $value = $_POST[ $key ] ?? '';
    return is_string( $value ) ? wp_unslash( $value ) : '';
}

/**
 * متن‌های پاسخ (یک جا برای ajax و حالت بدون جاوااسکریپت).
 *
 * @return array<string,string>
 */
function hodima_phone_messages(): array {
    return [
        'ok'    => 'شماره شما ثبت شد. همکاران ما به‌زودی تماس می‌گیرند.',
        'bad'   => 'شماره تماس نامعتبر است. موبایل را با ۰۹ و شماره ثابت را با کد شهر وارد کنید.',
        'wait'  => 'لطفا کمی بعد دوباره تلاش کنید.',
        'token' => 'زمان فرم تمام شد؛ دوباره دکمه ثبت را بزنید.',
        'err'   => 'خطا در ثبت درخواست. لطفا دوباره تلاش کنید.',
    ];
}

/* =====================================================================
 * ضد اسپم: توکن امضاشده، محدودیت نرخ، شماره تکراری
 * ===================================================================== */

/** کمترین فاصله (ثانیه) بین گرفتن توکن و ارسال؛ ربات‌های ساده بلافاصله می‌فرستند. */
function hodima_phone_min_seconds(): int {
    return max( 0, (int) apply_filters( 'hodima_phone_min_seconds', 2 ) );
}

/**
 * توکن بی‌حالت: «زمان.امضا». صفحه کش‌شده است و نمی‌توان زمان را در HTML گذاشت؛ جاوااسکریپت
 * با تعامل اول کاربر آن را می‌گیرد. (nonce وردپرس برای بازدیدکننده ناشناس برای همه یکی است و ارزشی نداشت.)
 */
function hodima_phone_issue_token(): string {
    $time = (string) time();
    return $time . '.' . hash_hmac( 'sha256', 'hodima_phone|' . $time, wp_salt( 'nonce' ) );
}

add_action( 'wp_ajax_hodima_phone_nonce', 'hodima_phone_ajax_token' );
add_action( 'wp_ajax_nopriv_hodima_phone_nonce', 'hodima_phone_ajax_token' );

function hodima_phone_ajax_token(): void {
    wp_send_json_success( hodima_phone_issue_token() );
}

/**
 * @return 'ok'|'fast'|'bad' fast = زودتر از حداقل زمان؛ bad = نامعتبر یا منقضی (بیش از ۱۲ ساعت).
 */
function hodima_phone_check_token( string $token ): string {
    [ $time, $mac ] = array_pad( explode( '.', $token, 2 ), 2, '' );
    if ( '' === $mac || ! ctype_digit( $time ) ) {
        return 'bad';
    }

    $expected = hash_hmac( 'sha256', 'hodima_phone|' . $time, wp_salt( 'nonce' ) );
    if ( ! hash_equals( $expected, $mac ) ) {
        return 'bad';
    }

    $age = time() - (int) $time;
    if ( $age > 12 * HOUR_IN_SECONDS || $age < 0 ) {
        return 'bad';
    }

    return $age < hodima_phone_min_seconds() ? 'fast' : 'ok';
}

/** کلید نشانه موقت بازدیدکننده: از IP (فقط پشت Cloudflare واقعی هدرهای IP معتبرند) با هش نمک‌دار. */
function hodima_phone_visitor_key( string $prefix ): string {
    $ip = function_exists( 'hodima_get_client_ip' )
        ? hodima_get_client_ip()
        : (string) filter_var( $_SERVER['REMOTE_ADDR'] ?? '', FILTER_VALIDATE_IP );

    if ( '' === $ip ) {
        $agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : 'unknown';
        $ip    = 'ua:' . $agent;
    }

    return $prefix . wp_hash( $ip );
}

/**
 * یک ارسال معتبر در هر ۱۵ ثانیه و حداکثر ۲۰ ارسال در ساعت برای هر بازدیدکننده.
 *
 * ترنزینت جداگانه هر بازدیدکننده (نه option مشترک): بدون خواندن-تغییر-نوشتن روی wp_options.
 * فقط بعد از معتبر بودن شماره صدا زده می‌شود تا غلط تایپی کسی را ۱۵ ثانیه قفل نکند.
 */
function hodima_phone_rate_limited(): bool {
    $gate = hodima_phone_visitor_key( 'hodima_phone_rl_' );
    if ( false !== get_transient( $gate ) ) {
        return true;
    }
    set_transient( $gate, 1, max( 1, (int) apply_filters( 'hodima_phone_rate_window', 15 ) ) );

    $cap = max( 0, (int) apply_filters( 'hodima_phone_hourly_limit', 20 ) );
    if ( 0 === $cap ) {
        return false;
    }

    $key   = hodima_phone_visitor_key( 'hodima_phone_hr_' );
    $hits  = get_transient( $key );
    $count = is_array( $hits ) ? (int) ( $hits['n'] ?? 0 ) : 0;
    $start = is_array( $hits ) ? (int) ( $hits['at'] ?? 0 ) : 0;
    $start = $start > 0 ? $start : time();

    if ( $count >= $cap ) {
        return true;
    }

    set_transient( $key, [ 'n' => $count + 1, 'at' => $start ], max( 1, HOUR_IN_SECONDS - ( time() - $start ) ) );
    return false;
}

/** دوبار زدن دکمه / ارسال دوباره همان شماره در یک دقیقه یک درخواست حساب می‌شود. false = تکراری. */
function hodima_phone_claim( string $phone ): bool {
    $key = 'hodima_phone_dup_' . wp_hash( $phone );
    if ( false !== get_transient( $key ) ) {
        return false;
    }
    set_transient( $key, 1, max( 1, (int) apply_filters( 'hodima_phone_duplicate_window', 60 ) ) );
    return true;
}

/* =====================================================================
 * داده لید
 * ===================================================================== */

/**
 * شناسه لیدهای یک شماره (شماره همان عنوان پست لید است). `any` وردپرس زباله‌دان را نمی‌بیند؛ پس صریح.
 *
 * @return list<int>
 */
function hodima_phone_lead_ids( string $phone, bool $with_trash = false, int $limit = -1 ): array {
    $statuses = [ 'publish', 'draft', 'pending', 'private' ];
    if ( $with_trash ) {
        $statuses[] = 'trash';
    }

    $ids = get_posts( [
        'post_type'      => 'hodima_phone_lead',
        'title'          => $phone,
        'post_status'    => $statuses,
        'posts_per_page' => $limit,
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ] );

    return array_map( 'intval', $ids );
}

/**
 * داده یک لید با بازگشت به متاهای قدیمی (قبل از _hodima_lead_data). تنها خواننده؛ ستون‌ها، CSV و حریم خصوصی همین را می‌خوانند.
 *
 * @return array{count:int,last_url:string,last_title:string,last_time:string,history:list<array{url:string,title:string,time:string}>}
 */
function hodima_phone_lead_data( int $lead_id ): array {
    $data = get_post_meta( $lead_id, HODIMA_PHONE_META, true );

    if ( ! is_array( $data ) ) {
        $data = [
            'count'      => (int) get_post_meta( $lead_id, 'hodima_request_count', true ),
            'last_url'   => (string) get_post_meta( $lead_id, 'hodima_page_url', true ),
            'last_title' => (string) get_post_meta( $lead_id, 'hodima_page_title', true ),
            'last_time'  => (string) get_post_meta( $lead_id, 'hodima_submit_time', true ),
        ];
    }

    $history = [];
    foreach ( is_array( $data['history'] ?? null ) ? $data['history'] : [] as $row ) {
        if ( is_array( $row ) ) {
            $history[] = [
                'url'   => (string) ( $row['url'] ?? '' ),
                'title' => (string) ( $row['title'] ?? '' ),
                'time'  => (string) ( $row['time'] ?? '' ),
            ];
        }
    }

    return [
        'count'      => max( 1, (int) ( $data['count'] ?? 1 ) ),
        'last_url'   => (string) ( $data['last_url'] ?? '' ),
        'last_title' => (string) ( $data['last_title'] ?? '' ),
        'last_time'  => (string) ( $data['last_time'] ?? '' ),
        'history'    => $history,
    ];
}

/**
 * ثبت یک درخواست: لید تازه یا افزودن به لید همان شماره. شناسه لید یا ۰ اگر ذخیره نشد.
 */
function hodima_phone_save_lead( string $phone, string $page_url, string $page_title ): int {
    $time  = current_time( 'Y-m-d H:i' );
    $entry = [ 'url' => $page_url, 'title' => $page_title, 'time' => $time ];
    $found = hodima_phone_lead_ids( $phone, false, 1 );

    if ( [] !== $found ) {
        $lead_id = $found[0];
        $data    = hodima_phone_lead_data( $lead_id );

        // لید قدیمی که تاریخچه نداشت: آخرین ثبت قبلی هم در تاریخچه بماند
        if ( [] === $data['history'] && '' !== $data['last_time'] ) {
            $data['history'][] = [ 'url' => $data['last_url'], 'title' => $data['last_title'], 'time' => $data['last_time'] ];
        }

        $data['history'][] = $entry;
        $data['history']   = array_slice( $data['history'], -10 );
        ++$data['count'];
        $data['last_url']   = $page_url;
        $data['last_title'] = $page_title;
        $data['last_time']  = $time;

        $updated = wp_update_post( [
            'ID'            => $lead_id,
            'post_date'     => current_time( 'mysql' ),
            'post_date_gmt' => current_time( 'mysql', true ),
        ], true );
        if ( is_wp_error( $updated ) || 0 === $updated ) {
            return 0;
        }

        update_post_meta( $lead_id, HODIMA_PHONE_META, $data );
        delete_post_meta( $lead_id, 'hodima_seen' ); // بازگشت کامل به وضعیت جدید
        return $lead_id;
    }

    $lead_id = wp_insert_post( [
        'post_type'   => 'hodima_phone_lead',
        'post_title'  => $phone,
        'post_status' => 'publish',
    ], true );
    if ( is_wp_error( $lead_id ) || $lead_id < 1 ) {
        return 0;
    }

    update_post_meta( $lead_id, HODIMA_PHONE_META, [
        'history'    => [ $entry ],
        'count'      => 1,
        'last_url'   => $page_url,
        'last_title' => $page_title,
        'last_time'  => $time,
    ] );

    return $lead_id;
}

/* =====================================================================
 * فرم
 * ===================================================================== */

add_shortcode( 'hodima_phone_form', 'hodima_phone_render_form' );

function hodima_phone_render_form(): string {
    static $instance = 0;
    ++$instance;
    $uid = 'hodima-phone-' . $instance;

    wp_enqueue_script( 'hodima-phone-script' );

    // نتیجه ارسال بدون جاوااسکریپت (ریدایرکت با ?hodima_phone=…)
    // phpcs:disable WordPress.Security.NonceVerification.Recommended -- فقط نمایش پیام؛ چیزی ذخیره نمی‌شود
    $status  = sanitize_key( wp_unslash( $_GET['hodima_phone'] ?? '' ) );
    $form_id = sanitize_key( wp_unslash( $_GET['form_id'] ?? '' ) );
    // phpcs:enable WordPress.Security.NonceVerification.Recommended
    $texts   = hodima_phone_messages();
    $message = ( $form_id === $uid && isset( $texts[ $status ] ) ) ? [ 'ok' === $status ? 'success' : 'error', $texts[ $status ] ] : null;

    // یادداشت اختیاری زیر فرم (پیش‌فرض خالی) + لینک سیاست حریم خصوصی اگر در «تنظیمات ← حریم خصوصی» صفحه‌ای انتخاب شده
    $note_text = (string) apply_filters( 'hodima_phone_privacy_note', '' );
    $policy    = function_exists( 'get_privacy_policy_url' ) ? get_privacy_policy_url() : '';
    $has_note  = '' !== $note_text || '' !== $policy;

    $described = $uid . '-title' . ( $has_note ? ' ' . $uid . '-note' : '' ) . ' ' . $uid . '-msg';

    ob_start();
    ?>
    <div class="hodima-phone-wrapper" id="<?php echo esc_attr( $uid ); ?>">
        <p class="hodima-phone-title" id="<?php echo esc_attr( $uid ); ?>-title">
            برای مشاوره و دریافت کاتالوگ شماره تماس وارد کنید
        </p>

        <form class="hodima-phone-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" novalidate>
            <input type="hidden" name="action" value="submit_hodima_phone">
            <input type="hidden" name="form_uid" value="<?php echo esc_attr( $uid ); ?>">

            <label class="hodima-phone-sr" for="<?php echo esc_attr( $uid ); ?>-input">شماره تماس</label>
            <input
                type="tel"
                id="<?php echo esc_attr( $uid ); ?>-input"
                name="phone"
                class="hodima-phone-input"
                placeholder="شماره تماس"
                inputmode="tel"
                enterkeyhint="send"
                autocomplete="tel"
                maxlength="20"
                required
                <?php echo $message && 'bad' === $status ? 'aria-invalid="true"' : ''; ?>
                aria-describedby="<?php echo esc_attr( $described ); ?>"
            >

            <div class="hodima-phone-hp" aria-hidden="true">
                <label for="<?php echo esc_attr( $uid ); ?>-website">وب‌سایت</label>
                <input type="text" id="<?php echo esc_attr( $uid ); ?>-website" name="website" tabindex="-1" autocomplete="off">
            </div>

            <button type="submit" class="hodima-phone-submit">ثبت درخواست</button>
        </form>

        <?php if ( $has_note ) : ?>
            <p class="hodima-phone-note" id="<?php echo esc_attr( $uid ); ?>-note">
                <?php echo esc_html( $note_text ); ?>
                <?php if ( '' !== $policy ) : ?>
                    <a href="<?php echo esc_url( $policy ); ?>">سیاست حریم خصوصی</a>
                <?php endif; ?>
            </p>
        <?php endif; ?>

        <div
            class="hodima-phone-msg<?php echo $message ? ' hodima-msg-' . esc_attr( $message[0] ) : ''; ?>"
            id="<?php echo esc_attr( $uid ); ?>-msg"
            role="status"
            aria-live="polite"
            <?php echo $message ? '' : 'hidden'; ?>
        ><?php echo $message ? esc_html( $message[1] ) : ''; ?></div>
    </div>
    <?php
    return ob_get_clean() ?: '';
}

/* =====================================================================
 * پردازش درخواست
 * ===================================================================== */

add_action( 'wp_ajax_submit_hodima_phone', 'hodima_phone_handle_ajax' );
add_action( 'wp_ajax_nopriv_submit_hodima_phone', 'hodima_phone_handle_ajax' );
add_action( 'admin_post_submit_hodima_phone', 'hodima_phone_handle_nojs' );
add_action( 'admin_post_nopriv_submit_hodima_phone', 'hodima_phone_handle_nojs' );

/**
 * @return string ok | spam (پاسخ موفق دروغین) | bad | wait | token | err
 */
function hodima_phone_process( bool $is_ajax ): string {
    if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
        return 'err';
    }

    // فرم عمومی است و nonce برای بازدیدکننده ناشناس ارزشی ندارد؛ به‌جایش توکن امضاشده با حداقل زمان
    if ( $is_ajax ) {
        $check = hodima_phone_check_token( hodima_phone_post_string( 'security_token' ) );
        if ( 'ok' !== $check ) {
            return 'fast' === $check ? 'wait' : 'token';
        }
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- تله ربات (honeypot)؛ ارزش آرایه هم اسپم است
    if ( ! empty( $_POST['website'] ) ) {
        return 'spam';
    }

    $phone = hodima_phone_normalize( hodima_phone_post_string( 'phone' ) );
    if ( '' === $phone ) {
        return 'bad';
    }

    if ( ! hodima_phone_claim( $phone ) ) {
        return 'ok'; // همین شماره لحظاتی پیش ثبت شد
    }
    if ( hodima_phone_rate_limited() ) {
        delete_transient( 'hodima_phone_dup_' . wp_hash( $phone ) );
        return 'wait';
    }

    $page_url = hodima_phone_post_string( 'page_url' );
    $page_url = esc_url_raw( '' !== $page_url ? $page_url : (string) wp_get_raw_referer(), [ 'http', 'https' ] );
    // این آدرس در پیشخوان لینک‌شدنی است؛ آدرس سایت دیگر (فیشینگ) پذیرفته نمی‌شود.
    if ( wp_parse_url( $page_url, PHP_URL_HOST ) !== wp_parse_url( home_url(), PHP_URL_HOST ) ) {
        $page_url = '';
    }
    $page_title = mb_substr( sanitize_text_field( hodima_phone_post_string( 'page_title' ) ), 0, 200 );

    // ذخیره ناموفق نباید «موفق» گزارش شود؛ شماره از دست می‌رفت
    $lead_id = hodima_phone_save_lead( $phone, $page_url, $page_title );
    if ( $lead_id < 1 ) {
        delete_transient( 'hodima_phone_dup_' . wp_hash( $phone ) );
        return 'err';
    }

    do_action( 'hodima_phone_lead_received', $lead_id, $phone );
    return 'ok';
}

function hodima_phone_handle_ajax(): void {
    $result   = hodima_phone_process( true );
    $messages = hodima_phone_messages();

    if ( 'ok' === $result || 'spam' === $result ) {
        wp_send_json_success( [ 'message' => $messages['ok'] ] );
    }

    wp_send_json_error(
        [ 'code' => $result, 'message' => $messages[ $result ] ?? $messages['err'] ],
        'wait' === $result ? 429 : 400
    );
}

function hodima_phone_handle_nojs(): void {
    $result = hodima_phone_process( false );
    $result = ( 'spam' === $result ) ? 'ok' : $result;

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
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط علامت «مشاهده شد»؛ با دسترسی ویرایش
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
    $data = hodima_phone_lead_data( $post_id );

    switch ( $column ) {
        case 'hodima_state':
            if ( 'seen' !== hodima_lead_state( $post_id ) ) {
                echo '<span class="hodima-dot" title="جدید"><span class="screen-reader-text">جدید</span></span>';
            }
            break;

        case 'hodima_page':
            if ( '' !== $data['last_url'] ) {
                printf(
                    '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
                    esc_url( $data['last_url'] ),
                    esc_html( '' !== $data['last_title'] ? $data['last_title'] : $data['last_url'] )
                );
            }
            break;

        case 'hodima_count':
            echo esc_html( (string) $data['count'] );
            break;

        case 'hodima_time':
            echo esc_html( $data['last_time'] );
            break;
    }
}, 10, 2 );

// حذف کامل اکشن‌های زیر ردیف (شامل تماس و واتس‌اپ)
add_filter( 'post_row_actions', fn( array $actions, WP_Post $post ): array =>
    $post->post_type === 'hodima_phone_lead' ? [] : $actions
, 10, 2 );

add_filter( 'post_class', function ( array $classes, array $css_classes, int $post_id ): array {
    if ( is_admin() && get_post_type( $post_id ) === 'hodima_phone_lead' ) {
        $classes[] = 'hodima-lead-' . hodima_lead_state( $post_id );
    }
    return $classes;
}, 10, 3 );

add_filter( 'views_edit-hodima_phone_lead', function ( array $views ): array {
    global $wpdb;

    $total_seen = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(pm.post_id) FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id WHERE pm.meta_key = %s AND p.post_type = %s",
        'hodima_seen',
        'hodima_phone_lead'
    ) );
    $total_all    = (int) wp_count_posts( 'hodima_phone_lead' )->publish;
    $total_unseen = max( 0, $total_all - $total_seen );

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط انتخاب زبانه نمایش
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

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط فیلتر فهرست
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

/** سلول CSV: متنی که با = + - @ شروع شود در اکسل فرمول می‌شود؛ با نقل‌قول تک بی‌اثر می‌شود. */
function hodima_phone_csv_cell( string $value ): string {
    return ( '' !== $value && in_array( $value[0], [ '=', '+', '-', '@', "\t", "\r" ], true ) ) ? "'" . $value : $value;
}

function hodima_export_phones_csv(): void {
    if ( empty( $_GET['export_hodima_phones'] ) ) return;

    $nonce = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) );
    if ( ! wp_verify_nonce( $nonce, 'hodima_export_csv' ) || ! current_user_can( 'manage_options' ) ) {
        wp_die( 'اجازه دانلود این فایل را ندارید یا لینک منقضی شده است؛ صفحه را تازه کنید و دوباره بزنید.', 'دسترسی ندارید', [ 'response' => 403 ] );
    }

    global $wpdb;

    nocache_headers();
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="hodima-leads-' . gmdate( 'Y-m-d' ) . '.csv"' );

    $out = fopen( 'php://output', 'w' );
    if ( false === $out ) {
        exit;
    }
    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- خروجی جریانی php://output
    fwrite( $out, "\xEF\xBB\xBF" );
    fputcsv( $out, [ 'شماره', 'تعداد درخواست', 'صفحه', 'لینک صفحه', 'زمان آخرین درخواست', 'وضعیت' ], ',', '"', '' );

    // دسته‌دسته (هزاران لید یک‌جا در حافظه نمی‌ماند)
    $batch  = 500;
    $offset = 0;
    $count  = $batch;
    do {
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT ID, post_title FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s ORDER BY post_date DESC, ID DESC LIMIT %d OFFSET %d",
            'hodima_phone_lead',
            'publish',
            $batch,
            $offset
        ) );
        if ( ! is_array( $rows ) || [] === $rows ) {
            break;
        }

        update_meta_cache( 'post', array_map( static fn( $row ): int => (int) $row->ID, $rows ) );

        foreach ( $rows as $row ) {
            $lead_id = (int) $row->ID;
            $data    = hodima_phone_lead_data( $lead_id );

            fputcsv( $out, [
                '="' . preg_replace( '/\D/', '', (string) $row->post_title ) . '"',
                $data['count'],
                hodima_phone_csv_cell( $data['last_title'] ),
                hodima_phone_csv_cell( $data['last_url'] ),
                hodima_phone_csv_cell( $data['last_time'] ),
                'seen' === hodima_lead_state( $lead_id ) ? 'مشاهده شده' : 'جدید',
            ], ',', '"', '' );
        }

        $count   = count( $rows );
        $offset += $batch;
    } while ( $count === $batch );

    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- خروجی جریانی php://output
    fclose( $out );
    exit;
}
