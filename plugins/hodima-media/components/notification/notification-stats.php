<?php
/**
 * آمار نوتیفیکیشن‌ها (نمایش، کلیک، بستن)، کپی گرفتن و متن حریم خصوصی.
 *
 * شمارنده‌ها عمومی‌اند (پاپ‌آپ در صفحه کش‌شده لایت‌اسپید است و nonce در
 * HTML کش‌شده کهنه می‌شود)؛ محافظت: فقط نوتیفیکیشن منتشرشده، رد ربات‌های
 * جستجو، محدودیت نرخ با هش IP (خود IP ذخیره نمی‌شود) و در مرورگر هر رویداد
 * یک بار در هر بازدید صفحه. هیچ داده شخصی ذخیره نمی‌شود: فقط سه عدد روی
 * هر نوتیفیکیشن (کل) و سه عدد برای هر روز (جدول hodima_notif_daily، از 2.0.5؛
 * ۴۰۰ روز نگه داشته می‌شود).
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

/** نسخه ساختار جدول روزانه (گزینه hodima_notif_daily_db). */
const HODIMA_NOTIFICATION_DAILY_DB = '1';

/** نگهداری آمار روزانه (روز). */
const HODIMA_NOTIFICATION_DAILY_KEEP = 400;

/** ستون جدول روزانه هر رویداد. */
const HODIMA_NOTIFICATION_DAILY_COLUMNS = [
	'view'  => 'views',
	'click' => 'clicks',
	'close' => 'closes',
];

/** نام جدول آمار روزانه. */
function hodima_notification_daily_table(): string {
	global $wpdb;
	return $wpdb->prefix . 'hodima_notif_daily';
}

/*
 * ساخت/به‌روزرسانی جدول. admin_init در admin-ajax هم اجرا می‌شود، پس پیش از
 * اولین ثبت رویداد (sendBeacon به admin-ajax) جدول ساخته شده است.
 */
add_action( 'admin_init', 'hodima_notification_daily_install' );

/** ساخت جدول آمار روزانه (یک بار برای هر نسخه ساختار). */
function hodima_notification_daily_install(): void {
	if ( HODIMA_NOTIFICATION_DAILY_DB === get_option( 'hodima_notif_daily_db' ) ) {
		return;
	}
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$table   = hodima_notification_daily_table();
	$charset = $wpdb->get_charset_collate();
	dbDelta(
		"CREATE TABLE {$table} (
		post_id bigint(20) unsigned NOT NULL,
		day date NOT NULL,
		views int(10) unsigned NOT NULL DEFAULT 0,
		clicks int(10) unsigned NOT NULL DEFAULT 0,
		closes int(10) unsigned NOT NULL DEFAULT 0,
		PRIMARY KEY  (post_id,day),
		KEY day (day)
	) {$charset};"
	);
	update_option( 'hodima_notif_daily_db', HODIMA_NOTIFICATION_DAILY_DB, false );
}

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
	hodima_notification_daily_increment( $post_id, $event );
}

/**
 * افزودن یک واحد به آمار امروز (روز در منطقه زمانی سایت). اتمی بدون
 * ON DUPLICATE KEY: اول UPDATE؛ اگر ردیف امروز نبود INSERT IGNORE؛ اگر درخواست
 * هم‌زمانی همان لحظه ردیف را ساخته بود، دوباره UPDATE.
 */
function hodima_notification_daily_increment( int $post_id, string $event ): void {
	global $wpdb;
	$table = hodima_notification_daily_table();
	$col   = HODIMA_NOTIFICATION_DAILY_COLUMNS[ $event ];
	$day   = current_time( 'Y-m-d' );
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- نام جدول و ستون ثابت‌اند؛ شمارنده اتمی بدون کش
	$update = static fn(): int => (int) $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET {$col} = {$col} + 1 WHERE post_id = %d AND day = %s", $post_id, $day ) );
	if ( $update() > 0 ) {
		return;
	}
	$inserted = (int) $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$table} (post_id, day, {$col}) VALUES (%d, %s, 1)", $post_id, $day ) );
	// phpcs:enable
	if ( $inserted < 1 ) {
		$update();
	}
}

/**
 * آمار روز به روز در یک بازه (روزهای بدون رویداد صفر).
 *
 * @param list<int> $ids  نوتیفیکیشن‌ها؛ خالی = همه.
 * @param string    $from روز اول (Y-m-d).
 * @param string    $to   روز آخر (Y-m-d).
 * @return array<string, array{views: int, clicks: int, closes: int}>
 */
function hodima_notification_daily( array $ids, string $from, string $to ): array {
	global $wpdb;
	$table = hodima_notification_daily_table();
	$where = $ids ? ' AND post_id IN (' . implode( ',', array_map( 'intval', $ids ) ) . ')' : '';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- جدول ثابت، شناسه‌ها intval
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT day, SUM(views) AS views, SUM(clicks) AS clicks, SUM(closes) AS closes FROM {$table} WHERE day BETWEEN %s AND %s{$where} GROUP BY day", $from, $to ), ARRAY_A );

	$out    = [];
	$cursor = new DateTimeImmutable( $from );
	$last   = new DateTimeImmutable( $to );
	while ( $cursor <= $last ) {
		$out[ $cursor->format( 'Y-m-d' ) ] = [ 'views' => 0, 'clicks' => 0, 'closes' => 0 ];
		$cursor                           = $cursor->modify( '+1 day' );
	}
	foreach ( (array) $rows as $row ) {
		$day = substr( (string) $row['day'], 0, 10 );
		if ( isset( $out[ $day ] ) ) {
			$out[ $day ] = [ 'views' => (int) $row['views'], 'clicks' => (int) $row['clicks'], 'closes' => (int) $row['closes'] ];
		}
	}
	return $out;
}

/**
 * جمع هر نوتیفیکیشن در یک بازه.
 *
 * @return array<int, array{views: int, clicks: int, closes: int}>
 */
function hodima_notification_range_totals( string $from, string $to ): array {
	global $wpdb;
	$table = hodima_notification_daily_table();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- جدول ثابت
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT post_id, SUM(views) AS views, SUM(clicks) AS clicks, SUM(closes) AS closes FROM {$table} WHERE day BETWEEN %s AND %s GROUP BY post_id", $from, $to ), ARRAY_A );
	$out  = [];
	foreach ( (array) $rows as $row ) {
		$out[ (int) $row['post_id'] ] = [ 'views' => (int) $row['views'], 'clicks' => (int) $row['clicks'], 'closes' => (int) $row['closes'] ];
	}
	return $out;
}

/** اولین روزی که آمار روزانه دارد ('' = هیچ). */
function hodima_notification_daily_first_day(): string {
	global $wpdb;
	$table = hodima_notification_daily_table();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- جدول ثابت
	return substr( (string) $wpdb->get_var( "SELECT MIN(day) FROM {$table}" ), 0, 10 );
}

/** پاک کردن آمار روزانه قدیمی‌تر از دوره نگهداری (هنگام باز شدن صفحه آمار). */
function hodima_notification_daily_cleanup(): void {
	global $wpdb;
	$table  = hodima_notification_daily_table();
	$before = wp_date( 'Y-m-d', time() - HODIMA_NOTIFICATION_DAILY_KEEP * DAY_IN_SECONDS );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- جدول ثابت
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE day < %s", $before ) );
}

/** حذف آمار روزانه یک نوتیفیکیشن (صفر کردن یا حذف کامل). */
function hodima_notification_daily_delete( int $post_id ): void {
	global $wpdb;
	$wpdb->delete( hodima_notification_daily_table(), [ 'post_id' => $post_id ], [ '%d' ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- جدول خود ماژول
}

add_action(
	'deleted_post',
	static function ( int $post_id, WP_Post $post ): void {
		if ( HODIMA_NOTIFICATION_POST_TYPE === $post->post_type ) {
			hodima_notification_daily_delete( $post_id );
		}
	},
	10,
	2
);

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

/** صفر کردن آمار یک نوتیفیکیشن (کل و روزانه). */
function hodima_notification_stats_reset( int $post_id ): void {
	foreach ( HODIMA_NOTIFICATION_EVENTS as $key ) {
		delete_post_meta( $post_id, $key );
	}
	update_post_meta( $post_id, '_hd_notif_stats_since', current_time( 'Y-m-d' ) );
	hodima_notification_daily_delete( $post_id );
}

/* ─── ستون آمار در فهرست ──────────────────────────────────────────── */

add_filter(
	'manage_hd_notification_posts_columns',
	static function ( array $columns ): array {
		// پیش از ستون تاریخ
		$date = $columns['date'] ?? null;
		unset( $columns['date'] );
		$columns['notif_stats'] = 'آمار';
		if ( null !== $date ) {
			$columns['date'] = $date;
		}
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
		// خلاصه کوتاه؛ جزئیات و نمودار در تب «آمار»
		$s = hodima_notification_stats( $post_id );
		if ( 0 === $s['views'] ) {
			echo '<span class="hd-muted">هنوز نمایشی نیست</span>';
			return;
		}
		$url = function_exists( 'hodima_notification_stats_url' ) ? hodima_notification_stats_url( [ 'notif' => $post_id ] ) : '';
		printf(
			'%1$s نمایش · %2$s کلیک<br><small class="hd-muted">نرخ کلیک %3$s٪</small>%4$s',
			esc_html( number_format_i18n( $s['views'] ) ),
			esc_html( number_format_i18n( $s['clicks'] ) ),
			esc_html( number_format_i18n( $s['ctr'], 1 ) ),
			'' !== $url ? sprintf( ' <a href="%1$s" class="hdn-stats-link">جزئیات<span class="screen-reader-text"> آمار «%2$s»</span></a>', esc_url( $url ), esc_html( get_the_title( $post_id ) ) ) : ''
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
