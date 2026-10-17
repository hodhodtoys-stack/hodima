<?php
/**
 * حریم خصوصی فرم لید تلفنی: خروجی و حذف داده و متن پیشنهادی سیاست حریم خصوصی
 * Path: components/phone/phone-privacy.php
 *
 * ابزارهای «خروجی داده شخصی» و «حذف داده شخصی» وردپرس (ابزارها ← خروجی/حذف داده شخصی) فقط با ایمیل کار
 * می‌کنند، ولی لید فقط شماره دارد. پس شماره‌های همان ایمیل را از حساب کاربری و سفارش‌های ووکامرس
 * (تلفن صورتحساب و ارسال) پیدا می‌کنیم و لیدهای همان شماره‌ها را می‌دهیم/حذف می‌کنیم. کسی که فقط
 * فرم تلفن را پر کرده و حساب یا سفارشی ندارد از این راه پیدا نمی‌شود؛ مدیر او را از «شماره‌های تماس» با
 * جستجوی شماره پیدا و حذف می‌کند (متن سیاست پیشنهادی همین را می‌گوید).
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * شماره‌های یکدست‌شده مربوط به یک ایمیل: حساب کاربری و سفارش‌های ووکامرس.
 *
 * @return list<string>
 */
function hodima_phone_privacy_phones( string $email ): array {
    $email = sanitize_email( $email );
    if ( '' === $email || ! function_exists( 'hodima_phone_normalize' ) ) {
        return [];
    }

    $raw  = [];
    $user = get_user_by( 'email', $email );
    if ( $user instanceof WP_User ) {
        foreach ( [ 'billing_phone', 'shipping_phone' ] as $key ) {
            $raw[] = (string) get_user_meta( $user->ID, $key, true );
        }
    }

    if ( function_exists( 'wc_get_orders' ) ) {
        $orders = wc_get_orders( [
            'billing_email' => $email,
            'limit'         => 500,
            'return'        => 'objects',
        ] );
        foreach ( is_array( $orders ) ? $orders : [] as $order ) {
            if ( $order instanceof WC_Order ) {
                $raw[] = $order->get_billing_phone();
                $raw[] = $order->get_shipping_phone();
            }
        }
    }

    $phones = [];
    foreach ( $raw as $value ) {
        $phone = hodima_phone_normalize( $value );
        if ( '' !== $phone ) {
            $phones[ $phone ] = $phone;
        }
    }

    /**
     * شماره‌های دیگری که باید با این ایمیل یکی حساب شوند (مثلا از افزونه‌ای دیگر).
     *
     * @param list<string> $phones شماره‌های یکدست‌شده.
     * @param string       $email  ایمیل درخواست.
     */
    $phones = apply_filters( 'hodima_privacy_lead_phones', array_values( $phones ), $email );

    return array_values( array_unique( array_filter( array_map( 'strval', (array) $phones ) ) ) );
}

/**
 * شناسه لیدهای یک شماره (شماره همان عنوان پست لید است).
 *
 * @return list<int>
 */
function hodima_phone_privacy_lead_ids( string $phone ): array {
    $ids = get_posts( [
        'post_type'      => 'hodima_phone_lead',
        'title'          => $phone,
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ] );

    return array_map( 'intval', $ids );
}

/**
 * داده ذخیره‌شده یک لید؛ با بازگشت به متاهای قدیمی (همان الگوی hodima_phone_process).
 *
 * @return array{count:int,last_url:string,last_title:string,last_time:string,history:list<array<string,string>>}
 */
function hodima_phone_privacy_lead_data( int $lead_id ): array {
    $data = get_post_meta( $lead_id, '_hodima_lead_data', true );
    if ( ! is_array( $data ) ) {
        $data = [
            'count'     => (int) get_post_meta( $lead_id, 'hodima_request_count', true ),
            'last_url'  => (string) get_post_meta( $lead_id, 'hodima_page_url', true ),
            'last_time' => (string) get_post_meta( $lead_id, 'hodima_submit_time', true ),
        ];
    }

    return [
        'count'      => max( 1, (int) ( $data['count'] ?? 1 ) ),
        'last_url'   => (string) ( $data['last_url'] ?? '' ),
        'last_title' => (string) ( $data['last_title'] ?? '' ),
        'last_time'  => (string) ( $data['last_time'] ?? '' ),
        'history'    => is_array( $data['history'] ?? null ) ? array_values( $data['history'] ) : [],
    ];
}

add_filter( 'wp_privacy_personal_data_exporters', static function ( array $exporters ): array {
    $exporters['hodima-phone-leads'] = [
        'exporter_friendly_name' => 'شماره‌های تماس فرم مشاوره (هدیما)',
        'callback'               => 'hodima_phone_privacy_export',
    ];
    return $exporters;
} );

add_filter( 'wp_privacy_personal_data_erasers', static function ( array $erasers ): array {
    $erasers['hodima-phone-leads'] = [
        'eraser_friendly_name' => 'شماره‌های تماس فرم مشاوره (هدیما)',
        'callback'             => 'hodima_phone_privacy_erase',
    ];
    return $erasers;
} );

/**
 * خروجی داده شخصی (wp_privacy_personal_data_exporters).
 *
 * @return array{data:list<array<string,mixed>>,done:bool}
 */
function hodima_phone_privacy_export( string $email_address, int $page = 1 ): array {
    $export = [];

    foreach ( hodima_phone_privacy_phones( $email_address ) as $phone ) {
        foreach ( hodima_phone_privacy_lead_ids( $phone ) as $lead_id ) {
            $lead    = hodima_phone_privacy_lead_data( $lead_id );
            $history = [];
            foreach ( $lead['history'] as $row ) {
                $history[] = trim( ( $row['time'] ?? '' ) . ' — ' . ( $row['title'] ?? '' ) . ' — ' . ( $row['url'] ?? '' ), ' —' );
            }

            $export[] = [
                'group_id'    => 'hodima-phone-leads',
                'group_label' => 'شماره‌های تماس فرم مشاوره',
                'item_id'     => 'hodima-phone-lead-' . $lead_id,
                'data'        => array_values( array_filter( [
                    [ 'name' => 'شماره تماس', 'value' => $phone ],
                    [ 'name' => 'تعداد دفعات ثبت', 'value' => (string) $lead['count'] ],
                    [ 'name' => 'آخرین صفحه ثبت', 'value' => trim( $lead['last_title'] . ' ' . $lead['last_url'] ) ],
                    [ 'name' => 'زمان آخرین ثبت', 'value' => $lead['last_time'] ],
                    [ 'name' => 'تاریخچه ثبت‌ها', 'value' => implode( "\n", $history ) ],
                ], static fn( array $item ): bool => '' !== $item['value'] ) ),
            ];
        }
    }

    return [ 'data' => $export, 'done' => true ];
}

/**
 * حذف داده شخصی (wp_privacy_personal_data_erasers): لیدهای شماره‌های این ایمیل پاک می‌شوند.
 *
 * @return array{items_removed:bool,items_retained:bool,messages:list<string>,done:bool}
 */
function hodima_phone_privacy_erase( string $email_address, int $page = 1 ): array {
    $removed = 0;

    foreach ( hodima_phone_privacy_phones( $email_address ) as $phone ) {
        foreach ( hodima_phone_privacy_lead_ids( $phone ) as $lead_id ) {
            if ( wp_delete_post( $lead_id, true ) ) {
                ++$removed;
            }
        }
    }

    return [
        'items_removed'  => $removed > 0,
        'items_retained' => false,
        'messages'       => $removed > 0 ? [ sprintf( '%d شماره تماس از فهرست «شماره‌های تماس» حذف شد.', $removed ) ] : [],
        'done'           => true,
    ];
}

/** متن پیشنهادی صفحه سیاست حریم خصوصی (ابزارها ← راهنمای سیاست حریم خصوصی). */
add_action( 'admin_init', 'hodima_phone_privacy_policy_text' );

function hodima_phone_privacy_policy_text(): void {
    if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
        return;
    }

    $text  = '<h3>' . esc_html( 'فرم «شماره تماس» (مشاوره و کاتالوگ)' ) . '</h3>';
    $text .= '<p class="privacy-policy-tutorial">' . esc_html( 'این متن پیشنهادی است؛ مطابق وضع کسب‌وکار خود ویرایش کنید.' ) . '</p>';
    $text .= '<p><strong>' . esc_html( 'چه چیزی ذخیره می‌شود:' ) . '</strong> ' . esc_html( 'شماره تماسی که بازدیدکننده در فرم وارد می‌کند، آدرس و عنوان صفحه‌ای که فرم را در آن پر کرده، زمان ثبت، و تعداد دفعات ثبت همان شماره (تاریخچه ۱۰ ثبت آخر). نشانی IP در فهرست شماره‌ها ذخیره نمی‌شود؛ فقط برای محدودیت یک ثبت در هر ۱۵ ثانیه، یک نشانه رمزشده موقت نگه داشته می‌شود.' ) . '</p>';
    $text .= '<p><strong>' . esc_html( 'چرا:' ) . '</strong> ' . esc_html( 'تماس برای مشاوره و ارسال کاتالوگ به درخواست خود بازدیدکننده.' ) . '</p>';
    $text .= '<p><strong>' . esc_html( 'تا کی:' ) . '</strong> ' . esc_html( 'تا زمانی که مدیر سایت آن شماره را از «شماره‌های تماس» در پیشخوان حذف کند. مدت نگهداری را خودتان بنویسید.' ) . '</p>';
    $text .= '<p><strong>' . esc_html( 'درخواست خروجی یا حذف:' ) . '</strong> ' . esc_html( 'اگر درخواست‌دهنده حساب کاربری یا سفارش با همان شماره دارد، ابزارهای وردپرس (ابزارها ← خروجی/حذف داده شخصی) با ایمیل او شماره را هم پیدا می‌کنند. اگر فقط فرم را پر کرده است، شماره را در «شماره‌های تماس» جستجو و حذف کنید.' ) . '</p>';

    wp_add_privacy_policy_content( 'هدیما — فرم شماره تماس', wp_kses_post( $text ) );
}
