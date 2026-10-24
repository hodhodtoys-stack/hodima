<?php
/**
 * پیشخوان لیدهای تلفنی: صفحه «شماره‌های تماس» (فهرست، جزئیات، کارهای گروهی، CSV)
 * Path: components/phone/phone-admin.php
 *
 * به‌جای فهرست پیش‌فرض وردپرس (edit.php) یک صفحه اختصاصی با استاندارد پیشخوان هدیما (نوع «فهرست و جدول»):
 * خلاصه ۴ عددی ← نوار ابزار یک‌ردیفه (کار گروهی، نماها، جستجو، CSV) ← جدول (در موبایل کارت). داده همان پست‌های
 * `hodima_phone_lead` است (عنوان = شماره، متاها بدون تغییر)؛ edit.php و post.php این نوع به همین صفحه می‌روند.
 * اجزا از Core فعلی (hd-wrap، hd-stat، hd-card، hd-opt، hd-num، hd-table-wrap، hd-empty) و بقیه در phone-admin.css/js.
 * زیر PHPCS/PHPStan/ESLint/Stylelint بدون baseline است.
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const HODIMA_PHONE_PAGE            = 'hodima-phone-leads';
const HODIMA_PHONE_CAP             = 'edit_others_posts';
/** فقط برای پاکسازی نسخه ۱.۳.۱ (نگهداری خودکار برداشته شد). */
const HODIMA_PHONE_SETTINGS        = 'hodima_phone_settings';
const HODIMA_PHONE_RETENTION_EVENT = 'hodima_phone_retention_event';

/* =====================================================================
 * کمکی‌ها
 * ===================================================================== */

function hodima_lead_state( int $post_id ): string {
    return get_post_meta( $post_id, 'hodima_seen', true ) ? 'seen' : 'new';
}

/** ارقام فارسی برای نمایش (عدد و زمان). شماره تلفن لاتین می‌ماند تا کپی و گرفتنش راحت باشد. */
function hodima_phone_fa( string|int $text ): string {
    $text = (string) $text;
    return function_exists( 'hodima_persian_digits' )
        ? hodima_persian_digits( $text )
        : strtr( $text, [ '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' ] );
}

/** شماره برای خواندن راحت‌تر: 0912 345 6789 / 021 2233 4455. */
function hodima_phone_pretty( string $phone ): string {
    if ( 1 === preg_match( '/^(09\d{2})(\d{3})(\d{4})$/', $phone, $m ) ) {
        return "{$m[1]} {$m[2]} {$m[3]}";
    }
    if ( 1 === preg_match( '/^(0\d{2})(\d{4})(\d{4})$/', $phone, $m ) ) {
        return "{$m[1]} {$m[2]} {$m[3]}";
    }
    return $phone;
}

/** ساعت محلی سایت به‌صورت timestamp «جابه‌جاشده» (برای قالب‌بندی با منطقه UTC). */
function hodima_phone_local_now(): int {
    return time() + wp_timezone()->getOffset( new DateTimeImmutable( 'now' ) );
}

/**
 * زمان نمایشی: «امروز ۰۷:۵۲» / «دیروز ۱۷:۵۲» / «۳۰ مهر ۲۰:۵۲» و تاریخ کامل شمسی (بدون Core: میلادی).
 *
 * @param int $local_ts timestamp ساعت محلی (strtotime( post_date . ' UTC' )).
 * @return array{0:string,1:string} [زمان نسبی، تاریخ کامل]
 */
function hodima_phone_when( int $local_ts ): array {
    $jalali = function_exists( 'hodima_jalali_format' );
    $fmt    = static fn( string $format ): string => $jalali
        ? hodima_jalali_format( $format, $local_ts, null, true )
        : gmdate( str_replace( [ 'j F Y', 'j F' ], [ 'Y-m-d', 'm-d' ], $format ), $local_ts );

    $clock = $jalali ? $fmt( 'H:i' ) : gmdate( 'H:i', $local_ts );
    $day   = gmdate( 'Y-m-d', $local_ts );
    $now   = hodima_phone_local_now();

    if ( gmdate( 'Y-m-d', $now ) === $day ) {
        $when = 'امروز ' . $clock;
    } elseif ( gmdate( 'Y-m-d', $now - DAY_IN_SECONDS ) === $day ) {
        $when = 'دیروز ' . $clock;
    } else {
        $when = $fmt( 'j F' ) . ' ' . $clock;
    }

    return [ $when, $fmt( 'j F Y' ) ];
}

/**
 * @param array<string,string|int> $args پارامترهای افزوده به نشانی صفحه.
 */
function hodima_phone_page_url( array $args = [] ): string {
    return add_query_arg( array_merge( [ 'page' => HODIMA_PHONE_PAGE ], $args ), admin_url( 'admin.php' ) );
}

/**
 * شمارنده‌های صفحه و نشان منو (یک بار در هر درخواست؛ با $fresh دوباره).
 *
 * @return array{total:int,new:int,seen:int,today:int,week:int,trash:int}
 */
function hodima_phone_counts( bool $fresh = false ): array {
    static $cache = null;
    if ( null !== $cache && ! $fresh ) {
        return $cache;
    }

    global $wpdb;
    $type  = 'hodima_phone_lead';
    $now   = hodima_phone_local_now();
    $today = gmdate( 'Y-m-d 00:00:00', $now );
    $week  = gmdate( 'Y-m-d 00:00:00', $now - 6 * DAY_IN_SECONDS );

    $total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s", $type, 'publish' ) );
    $seen  = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID WHERE p.post_type = %s AND p.post_status = %s AND pm.meta_key = %s",
        $type,
        'publish',
        'hodima_seen'
    ) );
    $new_today = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s AND post_date >= %s", $type, 'publish', $today ) );
    $new_week  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s AND post_date >= %s", $type, 'publish', $week ) );
    $trash     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s", $type, 'trash' ) );

    $cache = [
        'total' => $total,
        'new'   => max( 0, $total - $seen ),
        'seen'  => $seen,
        'today' => $new_today,
        'week'  => $new_week,
        'trash' => $trash,
    ];
    return $cache;
}

/** نمایش فعلی فهرست: all | new | seen | trash. */
function hodima_phone_state(): string {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط انتخاب نما
    $state = sanitize_key( wp_unslash( $_GET['state'] ?? '' ) );
    return in_array( $state, [ 'new', 'seen', 'trash' ], true ) ? $state : 'all';
}

/* =====================================================================
 * منو، دسترسی، ریدایرکت پیشخوان قدیمی
 * ===================================================================== */

add_action( 'admin_menu', static function (): void {
    // بدون نشان عدد کنار نام منو (خواست کاربر ۲ آبان ۱۴۰۵)؛ شمار «جدید» فقط داخل صفحه است
    add_menu_page( 'شماره‌های تماس', 'شماره‌های تماس', HODIMA_PHONE_CAP, HODIMA_PHONE_PAGE, 'hodima_phone_render_page', 'dashicons-phone', 26 );
} );

add_action( 'admin_enqueue_scripts', static function ( string $hook ): void {
    if ( 'toplevel_page_' . HODIMA_PHONE_PAGE !== $hook ) {
        return;
    }

    $css = HODIMA_PHONE_DIR . '/phone-admin.css';
    $js  = HODIMA_PHONE_DIR . '/phone-admin.js';
    wp_enqueue_style( 'hodima-phone-admin', HODIMA_PHONE_URI . '/phone-admin.css', [], is_file( $css ) ? (string) filemtime( $css ) : HODIMA_COMMERCE_VERSION );
    wp_enqueue_script( 'hodima-phone-admin', HODIMA_PHONE_URI . '/phone-admin.js', [], is_file( $js ) ? (string) filemtime( $js ) : HODIMA_COMMERCE_VERSION, [ 'in_footer' => true, 'strategy' => 'defer' ] );
} );

// فهرست و ویرایش پیش‌فرض وردپرس (نشانی‌های ذخیره‌شده، لینک‌های قدیمی) به صفحه تازه می‌روند
add_action( 'load-edit.php', static function (): void {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط ریدایرکت
    if ( 'hodima_phone_lead' === sanitize_key( wp_unslash( $_GET['post_type'] ?? '' ) ) ) {
        wp_safe_redirect( hodima_phone_page_url() );
        exit;
    }
} );

add_action( 'load-post.php', static function (): void {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط ریدایرکت
    $post_id = absint( $_GET['post'] ?? 0 );
    if ( $post_id && 'hodima_phone_lead' === get_post_type( $post_id ) ) {
        wp_safe_redirect( hodima_phone_page_url( [ 'lead' => $post_id ] ) );
        exit;
    }
} );

/* =====================================================================
 * کارها: دیده شد/جدید، زباله‌دان، بازگردانی، حذف همیشگی
 * ===================================================================== */

/**
 * یک کار روی چند لید. فقط لیدهایی که کاربر اجازه‌شان را دارد.
 *
 * @param list<int> $ids
 * @return int تعداد لیدهای تغییرکرده
 */
function hodima_phone_apply_op( string $op, array $ids ): int {
    $changed = 0;

    foreach ( array_unique( array_map( 'intval', $ids ) ) as $id ) {
        if ( $id < 1 || 'hodima_phone_lead' !== get_post_type( $id ) ) {
            continue;
        }

        switch ( $op ) {
            case 'seen':
                if ( current_user_can( 'edit_post', $id ) ) {
                    update_post_meta( $id, 'hodima_seen', 1 );
                    ++$changed;
                }
                break;

            case 'unseen':
                if ( current_user_can( 'edit_post', $id ) ) {
                    delete_post_meta( $id, 'hodima_seen' );
                    ++$changed;
                }
                break;

            case 'trash':
                if ( current_user_can( 'delete_post', $id ) && 'trash' !== get_post_status( $id ) && wp_trash_post( $id ) ) {
                    ++$changed;
                }
                break;

            case 'restore':
                if ( current_user_can( 'delete_post', $id ) && 'trash' === get_post_status( $id ) && wp_untrash_post( $id ) ) {
                    wp_publish_post( $id ); // وردپرس بازگردانی را پیش‌نویس می‌کند؛ لید باید دوباره در فهرست بیاید
                    ++$changed;
                }
                break;

            case 'delete':
                // حذف همیشگی فقط برای لیدی که قبلا در زباله‌دان بوده
                if ( current_user_can( 'delete_post', $id ) && 'trash' === get_post_status( $id ) && wp_delete_post( $id, true ) ) {
                    ++$changed;
                }
                break;
        }
    }

    hodima_phone_counts( true );
    return $changed;
}

add_action( 'admin_post_hodima_phone_leads', static function (): void {
    check_admin_referer( 'hodima_phone_leads' );
    if ( ! current_user_can( HODIMA_PHONE_CAP ) ) {
        wp_die( 'اجازه این کار را ندارید.', 'دسترسی ندارید', [ 'response' => 403 ] );
    }

    $op = sanitize_text_field( wp_unslash( $_POST['op'] ?? '' ) );
    $id = 0;
    $ids = [];

    if ( 'bulk' === $op ) {
        $op  = sanitize_key( wp_unslash( $_POST['bulk_op'] ?? '' ) );
        $raw = isset( $_POST['ids'] ) && is_array( $_POST['ids'] ) ? wp_unslash( $_POST['ids'] ) : [];
        $ids = array_map( 'absint', $raw );
    } elseif ( str_contains( $op, ':' ) ) {
        [ $op, $id ] = explode( ':', $op, 2 );
        $ids         = [ absint( $id ) ];
        $op          = sanitize_key( $op );
    }

    $labels  = [
        'seen'    => '%s شماره «دیده‌شده» شد.',
        'unseen'  => '%s شماره دوباره «جدید» شد.',
        'trash'   => '%s شماره به زباله‌دان رفت (از «زباله‌دان» برمی‌گردد).',
        'restore' => '%s شماره بازگردانده شد.',
        'delete'  => '%s شماره برای همیشه حذف شد.',
    ];
    $changed = isset( $labels[ $op ] ) ? hodima_phone_apply_op( $op, $ids ) : 0;

    if ( $changed > 0 && function_exists( 'hodima_admin_flash' ) ) {
        hodima_admin_flash( sprintf( $labels[ $op ], hodima_phone_fa( $changed ) ) );
    } elseif ( 0 === $changed && function_exists( 'hodima_admin_flash' ) ) {
        hodima_admin_flash( 'موردی انتخاب نشده بود یا اجازه آن را نداشتید.', 'warning' );
    }

    $back = isset( $_POST['back'] ) ? esc_url_raw( wp_unslash( $_POST['back'] ) ) : '';
    $back = wp_validate_redirect( $back, hodima_phone_page_url() );

    // لیدی که از صفحه جزئیاتش به زباله‌دان رفت/حذف شد به فهرست برمی‌گردد
    if ( in_array( $op, [ 'trash', 'delete' ], true ) ) {
        $back = hodima_phone_page_url();
    }

    wp_safe_redirect( $back );
    exit;
} );

/* =====================================================================
 * صفحه
 * ===================================================================== */

function hodima_phone_render_page(): void {
    if ( ! current_user_can( HODIMA_PHONE_CAP ) ) {
        wp_die( 'اجازه دیدن این صفحه را ندارید.', 'دسترسی ندارید', [ 'response' => 403 ] );
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط انتخاب لید برای نمایش
    $lead = absint( $_GET['lead'] ?? 0 );

    echo '<div class="wrap hd-wrap hd-leads">';
    if ( $lead > 0 && 'hodima_phone_lead' === get_post_type( $lead ) ) {
        hodima_phone_render_detail( $lead );
    } else {
        hodima_phone_render_list();
    }
    echo '</div>';
}

/** دکمه آیکون‌دار ردیف/جزئیات. */
function hodima_phone_icon_button( string $icon, string $label, string $attrs = '', string $css = '', string $text = '' ): string {
    return sprintf(
        '<button class="hd-leads__btn %1$s" %2$s aria-label="%3$s" title="%3$s"><span class="dashicons dashicons-%4$s" aria-hidden="true"></span>%5$s</button>',
        esc_attr( $css ),
        $attrs, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ویژگی‌های ثابت و escape‌شده سازنده
        esc_attr( $label ),
        esc_attr( $icon ),
        '' !== $text ? '<span class="hd-leads__btn-text">' . esc_html( $text ) . '</span>' : ''
    );
}

function hodima_phone_render_list(): void {
    $state  = hodima_phone_state();
    $counts = hodima_phone_counts();
    $per    = max( 5, min( 100, (int) apply_filters( 'hodima_phone_per_page', 20 ) ) );

    // phpcs:disable WordPress.Security.NonceVerification.Recommended -- فقط جستجو و صفحه‌بندی
    $paged  = max( 1, absint( $_GET['paged'] ?? 1 ) );
    $search = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) );
    // phpcs:enable

    $digits = hodima_phone_search_digits( $search );
    $args   = [
        'post_type'      => 'hodima_phone_lead',
        'post_status'    => 'trash' === $state ? 'trash' : 'publish',
        'posts_per_page' => $per,
        'paged'          => $paged,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ];
    if ( '' !== $digits ) {
        $args['s'] = $digits;
    }
    if ( 'new' === $state ) {
        $args['meta_query'] = [ [ 'key' => 'hodima_seen', 'compare' => 'NOT EXISTS' ] ]; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- فهرست ادمین
    } elseif ( 'seen' === $state ) {
        $args['meta_query'] = [ [ 'key' => 'hodima_seen', 'compare' => 'EXISTS' ] ]; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- فهرست ادمین
    }
    $query = new WP_Query( $args );

    hodima_admin_header( [
        'title'       => 'شماره‌های تماس',
        'description' => 'کسانی که از فرم «دریافت مشاوره» فوتر سایت شماره گذاشته‌اند.',
        'icon'        => 'dashicons-phone',
        'badge'       => 'مشاوره و کاتالوگ',
    ] );

    $stats = [
        [ 'جدید، هنوز دیده نشده', $counts['new'], true ],
        [ 'درخواست امروز', $counts['today'], false ],
        [ 'درخواست ۷ روز اخیر', $counts['week'], false ],
        [ 'کل شماره‌ها', $counts['total'], false ],
    ];
    echo '<section class="hd-leads__stats" aria-label="خلاصه">';
    foreach ( $stats as [ $label, $value, $accent ] ) {
        printf(
            '<div class="hd-stat%1$s"><span class="hd-stat__label">%2$s</span><strong class="hd-stat__value">%3$s</strong></div>',
            $accent && $value > 0 ? ' hd-leads__stat--new' : '',
            esc_html( $label ),
            esc_html( hodima_phone_fa( $value ) )
        );
    }
    echo '</section>';

    // فیلتر نما و جستجو
    $views = [
        'all'  => [ 'همه', $counts['total'] ],
        'new'  => [ 'جدید', $counts['new'] ],
        'seen' => [ 'دیده‌شده', $counts['seen'] ],
    ];
    if ( $counts['trash'] > 0 || 'trash' === $state ) {
        $views['trash'] = [ 'زباله‌دان', $counts['trash'] ];
    }

    // یک ردیف: کار گروهی ← نماها ← جستجو ← CSV (بدون فضای خالی؛ در عرض کم می‌شکند)
    echo '<div class="hd-leads__toolbar">';
    if ( $query->have_posts() ) {
        hodima_phone_render_bulk( 'trash' === $state );
    }

    echo '<nav class="hd-leads__seg" aria-label="نمایش">';
    foreach ( $views as $key => [ $label, $count ] ) {
        printf(
            '<a class="hd-leads__seg-item%1$s" href="%2$s"%3$s>%4$s <span class="hd-leads__seg-count">%5$s</span></a>',
            $key === $state ? ' is-active' : '',
            esc_url( hodima_phone_page_url( 'all' === $key ? [] : [ 'state' => $key ] ) ),
            $key === $state ? ' aria-current="page"' : '',
            esc_html( $label ),
            esc_html( hodima_phone_fa( $count ) )
        );
    }
    echo '</nav>';
    ?>
    <form class="hd-leads__search" method="get" role="search" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
        <input type="hidden" name="page" value="<?php echo esc_attr( HODIMA_PHONE_PAGE ); ?>">
        <?php if ( 'all' !== $state ) : ?>
            <input type="hidden" name="state" value="<?php echo esc_attr( $state ); ?>">
        <?php endif; ?>
        <label class="screen-reader-text" for="hd-leads-search">جستجوی شماره</label>
        <span class="dashicons dashicons-search" aria-hidden="true"></span>
        <input type="search" id="hd-leads-search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="جستجوی شماره…" inputmode="numeric" autocomplete="off">
    </form>
    <?php
    if ( current_user_can( 'manage_options' ) ) {
        $export = wp_nonce_url( add_query_arg( [ 'export_hodima_phones' => 1 ], hodima_phone_page_url() ), 'hodima_export_csv' );
        echo '<a class="button hd-leads__csv" href="' . esc_url( $export ) . '"><span class="dashicons dashicons-download" aria-hidden="true"></span> دانلود فایل CSV</a>';
    }
    echo '</div>';

    if ( ! $query->have_posts() ) {
        hodima_phone_render_empty( $counts['total'] + $counts['trash'] > 0, $search, $state );
    } else {
        hodima_phone_render_table( $query, $state );
        hodima_phone_render_pager( $query, $state, $search, $paged );
    }
    ?>
    <dialog class="hd-leads__dialog" id="hd-leads-dialog" aria-labelledby="hd-leads-dialog-title">
        <form method="dialog">
            <h2 id="hd-leads-dialog-title">حذف همیشگی</h2>
            <p>این کار قابل بازگشت نیست. ادامه می‌دهید؟</p>
            <div class="hd-leads__dialog-actions">
                <button class="button" value="cancel">انصراف</button>
                <button class="button button-primary hd-leads__danger" value="ok">بله، حذف شود</button>
            </div>
        </form>
    </dialog>
    <div class="screen-reader-text" id="hd-leads-live" role="status" aria-live="polite"></div>
    <?php
}

/** عدد جستجو: ارقام فارسی/عربی لاتین می‌شوند و هر چیز غیر رقم برداشته می‌شود؛ جستجوی بی‌رقم = خالی. */
function hodima_phone_search_digits( string $search ): string {
    $latin = strtr( $search, [
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ] );
    return (string) preg_replace( '/\D+/', '', $latin );
}

function hodima_phone_render_empty( bool $has_any, string $search, string $state ): void {
    echo '<div class="hd-card hd-leads__empty"><div class="hd-empty"><span class="dashicons dashicons-phone" aria-hidden="true"></span>';
    if ( ! $has_any ) {
        echo '<strong>هنوز شماره‌ای ثبت نشده</strong><span>فرم «دریافت مشاوره» در فوتر سایت است؛ هر کس شماره بگذارد اینجا دیده می‌شود.</span>';
    } else {
        echo '<strong>موردی پیدا نشد</strong>';
        if ( '' !== $search || 'all' !== $state ) {
            printf( '<a href="%s">نمایش همه شماره‌ها</a>', esc_url( hodima_phone_page_url() ) );
        }
    }
    echo '</div></div>';
}

/** کار گروهی؛ عنصرها بیرون از <form> جدول‌اند و با ویژگی form به آن وصل می‌شوند (تا در ردیف نوار ابزار بنشینند). */
function hodima_phone_render_bulk( bool $trash ): void {
    ?>
    <div class="hd-leads__bulk">
        <label class="screen-reader-text" for="hd-leads-bulk">کار گروهی روی شماره‌های انتخاب‌شده</label>
        <select name="bulk_op" id="hd-leads-bulk" form="hd-leads-form">
            <?php if ( $trash ) : ?>
                <option value="restore">بازگردانی</option>
                <option value="delete">حذف همیشگی</option>
            <?php else : ?>
                <option value="seen">علامت: دیده شد</option>
                <option value="unseen">علامت: جدید</option>
                <option value="trash">انتقال به زباله‌دان</option>
            <?php endif; ?>
        </select>
        <button type="submit" class="button" name="op" value="bulk" form="hd-leads-form" data-hd-bulk-apply>اجرا</button>
    </div>
    <?php
}

function hodima_phone_render_table( WP_Query $query, string $state ): void {
    $back = hodima_phone_page_url( array_filter( [
        'state' => 'all' === $state ? '' : $state,
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط بازگشت به همین نما
        's'     => sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ),
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط بازگشت به همین صفحه
        'paged' => absint( $_GET['paged'] ?? 0 ) > 1 ? absint( $_GET['paged'] ?? 0 ) : '',
    ] ) );
    $trash = 'trash' === $state;
    ?>
    <form class="hd-leads__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="hd-leads-form">
        <input type="hidden" name="action" value="hodima_phone_leads">
        <input type="hidden" name="back" value="<?php echo esc_url( $back ); ?>">
        <?php wp_nonce_field( 'hodima_phone_leads' ); ?>

        <div class="hd-table-wrap">
            <table class="hd-leads__table">
                <caption class="screen-reader-text">فهرست شماره‌های تماس</caption>
                <thead>
                    <tr>
                        <td class="hd-leads__cb"><input type="checkbox" data-hd-select-all aria-label="انتخاب همه شماره‌های این صفحه"></td>
                        <th scope="col">شماره</th>
                        <th scope="col">صفحه‌ای که فرم را پر کرد</th>
                        <th scope="col">درخواست‌ها</th>
                        <th scope="col">آخرین درخواست</th>
                        <td class="hd-leads__acts"><span class="screen-reader-text">اقدام‌ها</span></td>
                    </tr>
                </thead>
                <tbody>
                <?php
                foreach ( $query->posts as $post ) {
                    if ( $post instanceof WP_Post ) {
                        hodima_phone_render_row( $post, $trash );
                    }
                }
                ?>
                </tbody>
            </table>
        </div>
    </form>
    <?php
}

function hodima_phone_render_row( WP_Post $post, bool $trash ): void {
    $id      = (int) $post->ID;
    $phone   = $post->post_title;
    $pretty  = hodima_phone_pretty( $phone );
    $data    = hodima_phone_lead_data( $id );
    $is_new  = 'new' === hodima_lead_state( $id );
    [ $when, $date ] = hodima_phone_when( (int) strtotime( $post->post_date . ' UTC' ) );
    $title   = '' !== $data['last_title'] ? $data['last_title'] : $data['last_url'];
    $many    = $data['count'] > 1;
    $detail  = hodima_phone_page_url( [ 'lead' => $id ] );
    ?>
    <tr class="<?php echo $is_new ? 'is-new' : 'is-seen'; ?>">
        <td class="hd-leads__cb"><input type="checkbox" name="ids[]" value="<?php echo esc_attr( (string) $id ); ?>" data-hd-row aria-label="<?php echo esc_attr( 'انتخاب ' . $phone ); ?>"></td>
        <th scope="row" class="hd-leads__phone">
            <a href="<?php echo esc_url( $detail ); ?>">
                <span class="hd-leads__dot" aria-hidden="true"></span>
                <bdi class="hd-leads__num" dir="ltr"><?php echo esc_html( $pretty ); ?></bdi>
                <?php if ( $is_new ) : ?><span class="screen-reader-text"> (جدید)</span><?php endif; ?>
            </a>
        </th>
        <td class="hd-leads__page">
            <?php if ( '' !== $data['last_url'] ) : ?>
                <a href="<?php echo esc_url( $data['last_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $title ); ?></a>
            <?php else : ?>
                <span class="hd-leads__none">—</span>
            <?php endif; ?>
        </td>
        <td class="hd-leads__count"><span class="hd-leads__pill<?php echo $many ? ' is-many' : ''; ?>"><?php echo esc_html( hodima_phone_fa( $data['count'] ) . ' بار' ); ?></span></td>
        <td class="hd-leads__time"><strong><?php echo esc_html( $when ); ?></strong><span><?php echo esc_html( $date ); ?></span></td>
        <td class="hd-leads__acts">
            <?php
            echo hodima_phone_icon_button( 'clipboard', 'کپی شماره ' . $phone, 'type="button" data-hd-copy="' . esc_attr( $phone ) . '"', '', 'کپی' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escape در سازنده
            if ( $trash ) {
                echo hodima_phone_icon_button( 'undo', 'بازگردانی ' . $phone, 'type="submit" name="op" value="restore:' . $id . '"', '', 'بازگردانی' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo hodima_phone_icon_button( 'trash', 'حذف همیشگی ' . $phone, 'type="submit" name="op" value="delete:' . $id . '" data-hd-confirm', 'is-danger', 'حذف' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            } else {
                echo hodima_phone_icon_button( $is_new ? 'visibility' : 'hidden', ( $is_new ? 'علامت: دیده شد — ' : 'علامت: جدید — ' ) . $phone, 'type="submit" name="op" value="' . ( $is_new ? 'seen' : 'unseen' ) . ':' . $id . '"', '', $is_new ? 'دیدم' : 'جدید' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo hodima_phone_icon_button( 'trash', 'انتقال به زباله‌دان ' . $phone, 'type="submit" name="op" value="trash:' . $id . '"', 'is-danger', 'حذف' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }
            ?>
        </td>
    </tr>
    <?php
}

function hodima_phone_render_pager( WP_Query $query, string $state, string $search, int $paged ): void {
    $total = (int) $query->max_num_pages;
    $found = (int) $query->found_posts;
    if ( $total < 2 ) {
        printf( '<p class="hd-leads__foot">%s شماره</p>', esc_html( hodima_phone_fa( $found ) ) );
        return;
    }

    $base  = hodima_phone_page_url( array_filter( [ 'state' => 'all' === $state ? '' : $state, 's' => $search ] ) );
    $links = paginate_links( [
        'base'      => add_query_arg( 'paged', '%#%', $base ),
        'format'    => '',
        'current'   => $paged,
        'total'     => $total,
        'type'      => 'array',
        'prev_text' => '<span class="screen-reader-text">صفحه قبل</span><span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>',
        'next_text' => '<span class="screen-reader-text">صفحه بعد</span><span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>',
    ] );

    echo '<nav class="hd-leads__pager" aria-label="صفحه‌بندی"><span class="hd-leads__foot">';
    echo esc_html( sprintf( '%s شماره، صفحه %s از %s', hodima_phone_fa( $found ), hodima_phone_fa( $paged ), hodima_phone_fa( $total ) ) );
    echo '</span><ul>';
    foreach ( is_array( $links ) ? $links : [] as $link ) {
        echo '<li>' . wp_kses_post( $link ) . '</li>';
    }
    echo '</ul></nav>';
}

/* =====================================================================
 * جزئیات یک شماره
 * ===================================================================== */

function hodima_phone_render_detail( int $lead_id ): void {
    $post = get_post( $lead_id );
    if ( ! $post instanceof WP_Post ) {
        return;
    }

    $trashed = 'trash' === $post->post_status;
    if ( ! $trashed && current_user_can( 'edit_post', $lead_id ) ) {
        update_post_meta( $lead_id, 'hodima_seen', 1 ); // دیدن صفحه = دیده شد
        hodima_phone_counts( true );
    }

    $phone  = $post->post_title;
    $data   = hodima_phone_lead_data( $lead_id );
    [ $when, $date ] = hodima_phone_when( (int) strtotime( $post->post_date . ' UTC' ) );
    $back   = hodima_phone_page_url();
    $here   = hodima_phone_page_url( [ 'lead' => $lead_id ] );

    $desc = sprintf( '%s بار درخواست داده؛ آخرین بار %s', hodima_phone_fa( $data['count'] ), $when );
    if ( '' !== $data['last_title'] ) {
        $desc .= ' از صفحه «' . $data['last_title'] . '».';
    } else {
        $desc .= '.';
    }

    hodima_admin_header( [
        'title'       => "\u{2066}" . hodima_phone_pretty( $phone ) . "\u{2069}", // جداساز چپ‌به‌راست؛ بدون آن گروه‌های شماره در متن راست‌به‌چپ برعکس می‌شوند
        'description' => $desc,
        'icon'        => 'dashicons-phone',
        'badge'       => 'شماره تماس',
        'actions'     => '<a class="button" href="' . esc_url( $back ) . '"><span class="dashicons dashicons-' . ( is_rtl() ? 'arrow-right-alt2' : 'arrow-left-alt2' ) . '" aria-hidden="true"></span> همه شماره‌ها</a>',
    ] );
    ?>
    <form class="hd-leads__form hd-card hd-leads__bar-card" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <input type="hidden" name="action" value="hodima_phone_leads">
        <input type="hidden" name="back" value="<?php echo esc_url( $here ); ?>">
        <?php wp_nonce_field( 'hodima_phone_leads' ); ?>
        <span class="hd-pill<?php echo $trashed ? ' hd-pill--error' : ''; ?>"><?php echo $trashed ? 'در زباله‌دان' : 'دیده‌شده'; ?></span>
        <span class="hd-leads__bar-spacer"></span>
        <?php
        echo hodima_phone_icon_button( 'clipboard', 'کپی شماره ' . $phone, 'type="button" data-hd-copy="' . esc_attr( $phone ) . '"', '', 'کپی شماره' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escape در سازنده
        if ( $trashed ) {
            echo hodima_phone_icon_button( 'undo', 'بازگردانی', 'type="submit" name="op" value="restore:' . $lead_id . '"', '', 'بازگردانی' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo hodima_phone_icon_button( 'trash', 'حذف همیشگی', 'type="submit" name="op" value="delete:' . $lead_id . '" data-hd-confirm', 'is-danger', 'حذف همیشگی' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        } else {
            echo hodima_phone_icon_button( 'hidden', 'علامت: جدید', 'type="submit" name="op" value="unseen:' . $lead_id . '"', '', 'علامت: جدید' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            echo hodima_phone_icon_button( 'trash', 'انتقال به زباله‌دان', 'type="submit" name="op" value="trash:' . $lead_id . '"', 'is-danger', 'حذف' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
        ?>
    </form>

    <div class="hd-leads__detail">
        <section class="hd-card" aria-labelledby="hd-leads-hist">
            <div class="hd-card__head">
                <span class="dashicons dashicons-backup" aria-hidden="true"></span>
                <h2 class="hd-card__title" id="hd-leads-hist">تاریخچه درخواست‌ها</h2>
                <p class="hd-card__desc">هر بار که این شماره فرم را پر کرده؛ تا ۱۰ بار آخر نگه داشته می‌شود.</p>
            </div>
            <?php if ( [] === $data['history'] ) : ?>
                <p class="hd-leads__none">تاریخچه‌ای ثبت نشده است.</p>
            <?php else : ?>
                <ol class="hd-leads__timeline">
                    <?php
                    foreach ( array_reverse( $data['history'] ) as $item ) {
                        $ts       = strtotime( $item['time'] . ' UTC' );
                        $when_row = false !== $ts ? hodima_phone_when( $ts )[0] : $item['time'];
                        $label    = '' !== $item['title'] ? $item['title'] : $item['url'];
                        echo '<li><span class="hd-leads__tl-dot" aria-hidden="true"></span><span class="hd-leads__tl-page">';
                        if ( '' !== $item['url'] ) {
                            printf( '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>', esc_url( $item['url'] ), esc_html( $label ) );
                        } else {
                            echo '<span class="hd-leads__none">' . esc_html( '' !== $label ? $label : '—' ) . '</span>';
                        }
                        echo '</span><span class="hd-leads__tl-time">' . esc_html( $when_row ) . '</span></li>';
                    }
                    ?>
                </ol>
            <?php endif; ?>
        </section>

        <section class="hd-card" aria-labelledby="hd-leads-sum">
            <div class="hd-card__head">
                <span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
                <h2 class="hd-card__title" id="hd-leads-sum">خلاصه</h2>
            </div>
            <dl class="hd-leads__facts">
                <div><dt>تعداد درخواست‌ها</dt><dd><?php echo esc_html( hodima_phone_fa( $data['count'] ) . ' بار' ); ?></dd></div>
                <div><dt>آخرین درخواست</dt><dd><?php echo esc_html( $when ); ?></dd></div>
                <div><dt>تاریخ</dt><dd><?php echo esc_html( $date ); ?></dd></div>
            </dl>
        </section>
    </div>
    <div class="screen-reader-text" id="hd-leads-live" role="status" aria-live="polite"></div>
    <dialog class="hd-leads__dialog" id="hd-leads-dialog" aria-labelledby="hd-leads-dialog-title">
        <form method="dialog">
            <h2 id="hd-leads-dialog-title">حذف همیشگی</h2>
            <p>این کار قابل بازگشت نیست. ادامه می‌دهید؟</p>
            <div class="hd-leads__dialog-actions">
                <button class="button" value="cancel">انصراف</button>
                <button class="button button-primary hd-leads__danger" value="ok">بله، حذف شود</button>
            </div>
        </form>
    </dialog>
    <?php
}

/* =====================================================================
 * پاک نشدن خودکار شماره‌ها
 * =====================================================================
 * نگهداری خودکار (۱.۳.۱) به خواست کاربر برداشته شد: شماره‌ها مهم‌اند و مدیر ممکن است اشتباه
 * مدتی بگذارد. سایتی که آن را روشن کرده بود رویداد شبانه و گزینه‌اش را یک بار پاک می‌کنیم
 * (هیچ شماره‌ای پاک نمی‌شود؛ فقط زمان‌بندی برداشته می‌شود).
 */
add_action( 'admin_init', static function (): void {
    if ( false !== wp_next_scheduled( HODIMA_PHONE_RETENTION_EVENT ) ) {
        wp_clear_scheduled_hook( HODIMA_PHONE_RETENTION_EVENT );
        delete_option( HODIMA_PHONE_SETTINGS );
    }
} );

/* =====================================================================
 * خروجی CSV
 * ===================================================================== */

add_action( 'admin_init', 'hodima_export_phones_csv' );

/** سلول CSV: متنی که با = + - @ شروع شود در اکسل فرمول می‌شود؛ با نقل‌قول تک بی‌اثر می‌شود. */
function hodima_phone_csv_cell( string $value ): string {
    return ( '' !== $value && in_array( $value[0], [ '=', '+', '-', '@', "\t", "\r" ], true ) ) ? "'" . $value : $value;
}

function hodima_export_phones_csv(): void {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce پایین بررسی می‌شود
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
