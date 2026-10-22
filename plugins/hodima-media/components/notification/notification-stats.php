<?php
/**
 * آمار نوتیفیکیشن‌ها (نمایش، کلیک، بستن)، کپی گرفتن و متن حریم خصوصی.
 *
 * شمارنده‌ها عمومی‌اند (پاپ‌آپ در صفحه کش‌شده لایت‌اسپید است و nonce در
 * HTML کش‌شده کهنه می‌شود)؛ محافظت: فقط نوتیفیکیشن منتشرشده، رد ربات‌های
 * جستجو، محدودیت نرخ با هش IP (خود IP ذخیره نمی‌شود) و در مرورگر هر رویداد
 * یک بار در هر بازدید صفحه. هیچ داده شخصی ذخیره نمی‌شود: فقط سه عدد روی
 * هر نوتیفیکیشن.
 *
 * @package Hodima\Media
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/notification-settings.php';

/** رویدادهای شمرده‌شده ← کلید متا. */
const HODIMA_NOTIFICATION_EVENTS = [
	'view'  => '_hd_notif_stats_views',
	'click' => '_hd_notif_stats_clicks',
	'close' => '_hd_notif_stats_closes',
];

add_action( 'wp_ajax_hodima_notif_event', 'hodima_notification_track_event' );
add_action( 'wp_ajax_nopriv_hodima_notif_event', 'hodima_notification_track_event' );

/** ثبت یک رویداد از مرورگر (sendBeacon به admin-ajax). */
function hodima_notification_track_event(): void {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- شمارنده عمومی صفحه کش‌شده؛ محافظت بالا توضیح داده شده
	$id    = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
	$event = isset( $_POST['event'] ) ? sanitize_key( wp_unslash( $_POST['event'] ) ) : '';
	// phpcs:enable

	$ok = isset( HODIMA_NOTIFICATION_EVENTS[ $event ] )
		&& HODIMA_NOTIFICATION_POST_TYPE === get_post_type( $id )
		&& 'publish' === get_post_status( $id )
		&& ! ( function_exists( 'hodima_is_search_bot' ) && hodima_is_search_bot() )
		&& hodima_notification_rate_ok();

	if ( $ok ) {
		hodima_notification_stat_increment( $id, $event );
	}
	nocache_headers();
	wp_send_json_success( [ 'recorded' => $ok ] );
}

/** هر بازدیدکننده حداکثر ۶۰ رویداد در دقیقه (کلید با هش IP، یک دقیقه). */
function hodima_notification_rate_ok(): bool {
	$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$key = function_exists( 'hodima_rate_limit_key' ) ? hodima_rate_limit_key( 'hd_notif_rl_' ) : 'hd_notif_rl_' . md5( $ip );
	$n   = (int) get_transient( $key );
	if ( $n >= 60 ) {
		return false;
	}
	set_transient( $key, $n + 1, MINUTE_IN_SECONDS );
	return true;
}

/** افزودن یک واحد به شمارنده (اتمی در پایگاه داده؛ دو درخواست هم‌زمان هر دو شمرده شوند). */
function hodima_notification_stat_increment( int $post_id, string $event ): void {
	global $wpdb;
	$key = HODIMA_NOTIFICATION_EVENTS[ $event ];
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- افزایش اتمی؛ کش متا پایین پاک می‌شود
	$updated = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_value = meta_value + 1 WHERE post_id = %d AND meta_key = %s", $post_id, $key ) );
	if ( ! $updated ) {
		add_post_meta( $post_id, $key, '1', true );
		if ( '' === (string) get_post_meta( $post_id, '_hd_notif_stats_since', true ) ) {
			add_post_meta( $post_id, '_hd_notif_stats_since', current_time( 'Y-m-d' ), true );
		}
	}
	wp_cache_delete( $post_id, 'post_meta' );
}

/**
 * آمار یک نوتیفیکیشن.
 *
 * @return array{views: int, clicks: int, closes: int, ctr: float, since: string}
 */
function hodima_notification_stats( int $post_id ): array {
	$views  = (int) get_post_meta( $post_id, HODIMA_NOTIFICATION_EVENTS['view'], true );
	$clicks = (int) get_post_meta( $post_id, HODIMA_NOTIFICATION_EVENTS['click'], true );
	return [
		'views'  => $views,
		'clicks' => $clicks,
		'closes' => (int) get_post_meta( $post_id, HODIMA_NOTIFICATION_EVENTS['close'], true ),
		'ctr'    => $views > 0 ? round( 100 * $clicks / $views, 1 ) : 0.0,
		'since'  => (string) get_post_meta( $post_id, '_hd_notif_stats_since', true ),
	];
}

/** صفر کردن آمار (از کادر ویرایش). */
function hodima_notification_stats_reset( int $post_id ): void {
	foreach ( HODIMA_NOTIFICATION_EVENTS as $key ) {
		delete_post_meta( $post_id, $key );
	}
	update_post_meta( $post_id, '_hd_notif_stats_since', current_time( 'Y-m-d' ) );
}

/* ─── ستون آمار در فهرست ──────────────────────────────────────────── */

add_filter(
	'manage_hd_notification_posts_columns',
	static function ( array $columns ): array {
		$columns['notif_stats'] = 'آمار';
		return $columns;
	},
	20
);

add_action(
	'manage_hd_notification_posts_custom_column',
	static function ( string $column, int $post_id ): void {
		if ( 'notif_stats' !== $column ) {
			return;
		}
		$s = hodima_notification_stats( $post_id );
		if ( 0 === $s['views'] ) {
			echo '<span class="hd-muted">هنوز نمایشی نیست</span>';
			return;
		}
		printf(
			'%1$s نمایش · %2$s کلیک<br><small class="hd-muted">نرخ کلیک %3$s٪ · %4$s بستن</small>',
			esc_html( number_format_i18n( $s['views'] ) ),
			esc_html( number_format_i18n( $s['clicks'] ) ),
			esc_html( number_format_i18n( $s['ctr'], 1 ) ),
			esc_html( number_format_i18n( $s['closes'] ) )
		);
	},
	10,
	2
);

/* ─── کپی گرفتن ──────────────────────────────────────────────────── */

add_filter(
	'post_row_actions',
	static function ( array $actions, WP_Post $post ): array {
		if ( HODIMA_NOTIFICATION_POST_TYPE === $post->post_type && current_user_can( 'edit_post', $post->ID ) ) {
			$url                      = wp_nonce_url( admin_url( 'admin-post.php?action=hodima_notif_duplicate&post=' . $post->ID ), 'hodima_notif_duplicate_' . $post->ID );
			$actions['hd_duplicate'] = sprintf( '<a href="%s" aria-label="%s">کپی</a>', esc_url( $url ), esc_attr( 'کپی گرفتن از «' . $post->post_title . '»' ) );
		}
		return $actions;
	},
	10,
	2
);

add_action( 'admin_post_hodima_notif_duplicate', 'hodima_notification_duplicate' );

/** یک کپی پیش‌نویس با همه تنظیمات (بدون آمار). */
function hodima_notification_duplicate(): void {
	$source = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
	check_admin_referer( 'hodima_notif_duplicate_' . $source );
	if ( HODIMA_NOTIFICATION_POST_TYPE !== get_post_type( $source ) || ! current_user_can( 'edit_post', $source ) || ! current_user_can( 'edit_posts' ) ) {
		wp_die( esc_html( 'اجازه کپی این نوتیفیکیشن را ندارید.' ), 403 );
	}

	$copy = wp_insert_post(
		[
			'post_type'   => HODIMA_NOTIFICATION_POST_TYPE,
			'post_status' => 'draft',
			'post_title'  => get_the_title( $source ) . ' (کپی)',
		],
		true
	);
	if ( is_wp_error( $copy ) ) {
		wp_die( esc_html( $copy->get_error_message() ) );
	}
	foreach ( array_keys( hodima_notification_fields() ) as $key ) {
		$value = get_post_meta( $source, '_' . $key, true );
		if ( '' !== $value ) {
			update_post_meta( $copy, '_' . $key, $value );
		}
	}
	if ( function_exists( 'hodima_admin_flash' ) ) {
		hodima_admin_flash( 'کپی پیش‌نویس ساخته شد؛ بعد از بررسی منتشرش کنید.' );
	}
	wp_safe_redirect( (string) get_edit_post_link( $copy, 'url' ) );
	exit;
}

/* ─── حریم خصوصی ─────────────────────────────────────────────────── */

add_action( 'admin_init', 'hodima_notification_privacy_text' );

/** متن پیشنهادی صفحه سیاست حریم خصوصی (ابزارها ← راهنمای سیاست حریم خصوصی). */
function hodima_notification_privacy_text(): void {
	if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
		return;
	}
	$text  = '<h3>' . esc_html( 'پاپ‌آپ‌های اطلاع‌رسانی' ) . '</h3>';
	$text .= '<p class="privacy-policy-tutorial">' . esc_html( 'این متن پیشنهادی است؛ مطابق وضع کسب‌وکار خود ویرایش کنید.' ) . '</p>';
	$text .= '<p>' . esc_html( 'برای اینکه پاپ‌آپ‌ها بیش از حد تکرار نشوند، مرورگر شما (نه سرور ما) این‌ها را نگه می‌دارد: منبع تبلیغ (utm) و سایتی که از آن آمده‌اید تا پایان همان بازدید، تعداد صفحه‌های دیده‌شده در همان بازدید، تاریخ اولین بازدید، و اینکه هر پاپ‌آپ چند بار نمایش داده یا بسته شده است. این اطلاعات به سرور فرستاده نمی‌شود و با پاک کردن داده‌های سایت در مرورگر پاک می‌شود.' ) . '</p>';
	$text .= '<p>' . esc_html( 'سرور فقط تعداد کل نمایش، کلیک و بستن هر پاپ‌آپ را می‌شمارد، بدون اینکه بداند چه کسی بوده است. برای جلوگیری از شمارش ساختگی، یک نشانه یک‌طرفه (هش) از نشانی IP حداکثر یک دقیقه نگه داشته می‌شود؛ خود IP ذخیره نمی‌شود.' ) . '</p>';
	wp_add_privacy_policy_content( 'هدیما — پاپ‌آپ‌ها', wp_kses_post( $text ) );
}
