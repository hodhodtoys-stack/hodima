<?php
/**
 * Hodima Phone Lead Form
 * Path: components/phone/phone-form.php
 *
 * شورت‌کد [hodima_phone_form] و ثبت لید (admin-ajax و بدون جاوااسکریپت). پیشخوان: phone-admin.php.
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
        'show_in_menu'        => false, // منو و فهرست را phone-admin.php می‌سازد (صفحه hodima-phone-leads)
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
