<?php
/**
 * ماژول «مشاور سئو» — صفحه پیشخوان
 * Path: core/advisor/advisor-admin.php
 *
 * «ابزارهای هدیما ← مشاور سئو» (admin.php?page=hodima-advisor). نوع صفحه «فهرست و جدول»
 * با کالبد ثابت کیت SEO (inc/ui-skeleton.php): سربرگ ← ۴ کارت آمار ← یک کنش اصلی
 * («به‌روزرسانی داده» با انتخاب بازه) ← کارت فرصت‌ها (نوار ابزار یک‌ردیفه: نماها، جستجو،
 * نوع پیشنهاد، خروجی CSV؛ ردیف‌های جمع‌شونده). طرح تأییدشده: Artifact «طرح مشاور سئو»
 * (HODIMA-AUDIT.md بخش ۲۵۶). چیزی در سایت عوض نمی‌شود؛ فقط «نادیده گرفتن» یک آدرس و بازه
 * ذخیره می‌شوند. بدون JS کار می‌کند.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

const HODIMA_SEO_ADVISOR_PAGE     = 'hodima-advisor';
const HODIMA_SEO_ADVISOR_PER_PAGE = 15;

/** حداکثر ردیف خروجی CSV (در دسته‌های ۵۰۰ تایی خوانده می‌شود). */
const HODIMA_SEO_ADVISOR_CSV_MAX = 5000;

require_once HODIMA_SEO_DIR . '/inc/ui-skeleton.php'; // کالبد کیت SEO؛ صفحه بدون ماژول‌های اسکیما/آدرس تمیز هم کامل است

add_action( 'admin_menu', 'hodima_seo_advisor_admin_menu', 30 );
add_action( 'admin_post_hodima_advisor_refresh', 'hodima_seo_advisor_handle_refresh' );
add_action( 'admin_post_hodima_advisor_ignore', 'hodima_seo_advisor_handle_ignore' );
add_action( 'admin_post_hodima_advisor_csv', 'hodima_seo_advisor_handle_csv' );
add_action( 'admin_post_hodima_advisor_settings', 'hodima_seo_advisor_handle_settings' );
add_action( 'admin_post_hodima_advisor_clear', 'hodima_seo_advisor_handle_clear' );
add_action( 'admin_post_hodima_advisor_key', 'hodima_seo_advisor_handle_key' );

function hodima_seo_advisor_admin_menu(): void {

	$parent = function_exists( 'hodima_admin_menu_parent' ) ? hodima_admin_menu_parent() : '';

	$hook = '' !== $parent
		? add_submenu_page( $parent, 'مشاور سئو', 'مشاور سئو', 'manage_options', HODIMA_SEO_ADVISOR_PAGE, 'hodima_seo_advisor_render_admin' )
		: add_menu_page( 'مشاور سئو', 'مشاور سئو', 'manage_options', HODIMA_SEO_ADVISOR_PAGE, 'hodima_seo_advisor_render_admin', 'dashicons-chart-line', 32 );

	if ( is_string( $hook ) && '' !== $hook ) {
		add_action( 'load-' . $hook, static function (): void {
			add_action( 'admin_enqueue_scripts', 'hodima_seo_advisor_admin_assets', 20 );
		} );
	}
}

function hodima_seo_advisor_admin_assets(): void {

	hodima_seo_ui_enqueue();
	hodima_seo_advisor_enqueue_steps_css();

	$file = HODIMA_SEO_DIR . '/core/advisor/advisor-admin.css';
	wp_enqueue_style( 'hodima-seo-advisor', HODIMA_SEO_URL . '/core/advisor/advisor-admin.css', [ 'hodima-seo-ui' ], is_file( $file ) ? (string) filemtime( $file ) : HODIMA_SEO_VERSION );
}

/**
 * آدرس صفحه با پارامترها.
 *
 * @param array<string, string|int|null> $args
 */
function hodima_seo_advisor_page_url( array $args = [] ): string {
	return add_query_arg( array_filter( [ 'page' => HODIMA_SEO_ADVISOR_PAGE ] + $args, static fn( mixed $v ): bool => '' !== $v && null !== $v ), admin_url( 'admin.php' ) );
}

/** نام فارسی قاعده. */
function hodima_seo_advisor_rule_label( string $rule ): string {
	return match ( $rule ) {
		'low_ctr'          => 'نرخ کلیک کم',
		'near_first'       => 'کلمه نزدیک صفحه اول',
		'missing_headings' => 'کلمه در تیترها نیست',
		'missing_content'  => 'کلمه در متن نیست',
		'cannibal'         => 'رقابت داخلی',
		'weak_query'       => 'کلمه بدون صفحه اختصاصی',
		'decline'          => 'افت کلیک',
		default            => $rule,
	};
}

/** نام فارسی نوع شیء. */
function hodima_seo_advisor_kind_label( string $kind ): string {
	return match ( $kind ) {
		'product'     => 'محصول',
		'product_cat' => 'دسته محصول',
		'category'    => 'دسته نوشته',
		'post'        => 'نوشته',
		'page'        => 'برگه',
		'home'        => 'صفحه اصلی',
		default       => 'صفحه',
	};
}

/**
 * فیلترهای فهرست از آدرس (نما، جستجو، نوع پیشنهاد) ← آرگومان‌های hodima_seo_advisor_pages().
 *
 * @param array<string, mixed> $query
 * @return array{view: string, q: string, rule: string, args: array<string, mixed>}
 */
function hodima_seo_advisor_list_filters( array $query ): array {

	$view = sanitize_key( (string) ( $query['view'] ?? 'all' ) );
	$view = in_array( $view, [ 'all', 'ignored', ...array_keys( HODIMA_SEO_ADVISOR_KIND_GROUPS ) ], true ) ? $view : 'all';
	$q    = sanitize_text_field( (string) ( $query['q'] ?? '' ) );
	$rule = sanitize_key( (string) ( $query['rule'] ?? '' ) );
	$args = [ 'orderby' => 'potential', 'order' => 'DESC', 'search' => $q, 'rule' => $rule ];

	if ( 'ignored' === $view ) {
		$args['paths'] = hodima_seo_advisor_ignored();
	} else {
		$args['has_advice'] = true;
		if ( isset( HODIMA_SEO_ADVISOR_KIND_GROUPS[ $view ] ) ) {
			$args['kind'] = HODIMA_SEO_ADVISOR_KIND_GROUPS[ $view ];
		}
	}

	return [ 'view' => $view, 'q' => $q, 'rule' => $rule, 'args' => $args ];
}

/* ── کنش‌ها ── */

function hodima_seo_advisor_back_url(): string {
	$back = wp_get_referer();
	return is_string( $back ) && '' !== $back ? $back : hodima_seo_advisor_page_url();
}

function hodima_seo_advisor_handle_refresh(): void {

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'اجازه این کار را ندارید.', 'دسترسی ندارید', [ 'response' => 403 ] );
	}

	check_admin_referer( 'hodima_advisor_refresh' );

	$days = isset( $_POST['days'] ) ? (int) $_POST['days'] : 0;
	if ( in_array( $days, HODIMA_SEO_ADVISOR_DAYS, true ) ) {
		$settings         = get_option( HODIMA_SEO_ADVISOR_SETTINGS_OPTION, [] );
		$settings         = is_array( $settings ) ? $settings : [];
		$settings['days'] = $days;
		update_option( HODIMA_SEO_ADVISOR_SETTINGS_OPTION, $settings, false );
	}

	if ( function_exists( 'set_time_limit' ) ) {
		set_time_limit( 180 ); // سایت بزرگ: سه درخواست ۲۵ هزار ردیفی و تحلیل همه آدرس‌ها
	}

	$result = hodima_seo_advisor_refresh();

	if ( is_wp_error( $result ) ) {
		hodima_admin_flash( '<strong>مشاور سئو:</strong> ' . esc_html( $result->get_error_message() ), 'error' );
	} else {
		$status = hodima_seo_advisor_status();
		hodima_admin_flash(
			sprintf(
				'<strong>مشاور سئو:</strong> داده %1$s روز اخیر گرفته شد؛ %2$s آدرس بررسی و برای %3$s آدرس پیشنهاد ساخته شد.',
				esc_html( number_format_i18n( $status['days'] ) ),
				esc_html( number_format_i18n( $status['pages'] ) ),
				esc_html( number_format_i18n( $status['advised'] ) )
			),
			'success'
		);
	}

	wp_safe_redirect( hodima_seo_advisor_page_url() );
	exit;
}

/** کمترین و بیشترین «حداقل نمایش» قابل ذخیره. */
const HODIMA_SEO_ADVISOR_MIN_IMPRESSIONS_RANGE = [ 10, 100000 ];

function hodima_seo_advisor_handle_settings(): void {

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'اجازه این کار را ندارید.', 'دسترسی ندارید', [ 'response' => 403 ] );
	}

	check_admin_referer( 'hodima_advisor_settings' );

	[ $low, $high ] = HODIMA_SEO_ADVISOR_MIN_IMPRESSIONS_RANGE;
	$value          = isset( $_POST['min_impressions'] ) ? absint( $_POST['min_impressions'] ) : 0;

	if ( $value < $low || $value > $high ) {
		hodima_admin_flash( sprintf( 'حداقل نمایش باید بین %1$s و %2$s باشد؛ تنظیم قبلی ماند.', esc_html( number_format_i18n( $low ) ), esc_html( number_format_i18n( $high ) ) ), 'error' );
		wp_safe_redirect( hodima_seo_advisor_page_url() );
		exit;
	}

	$settings                    = get_option( HODIMA_SEO_ADVISOR_SETTINGS_OPTION, [] );
	$settings                    = is_array( $settings ) ? $settings : [];
	$settings['min_impressions'] = $value;
	update_option( HODIMA_SEO_ADVISOR_SETTINGS_OPTION, $settings, false );

	// پیشنهادها از داده ذخیره‌شده ساخته می‌شوند؛ بدون درخواست تازه به گوگل همین حالا با آستانه جدید دوباره ساخته شوند
	$message = 'تنظیم ذخیره شد.';
	if ( hodima_seo_advisor_totals()['pages'] > 0 ) {
		$count = hodima_seo_advisor_analyze_all();
		if ( is_wp_error( $count ) ) {
			hodima_admin_flash( '<strong>مشاور سئو:</strong> ' . esc_html( $count->get_error_message() ), 'error' );
			wp_safe_redirect( hodima_seo_advisor_page_url() );
			exit;
		}
		$message = sprintf( 'تنظیم ذخیره شد؛ پیشنهادها دوباره ساخته شد (%s آدرس پیشنهاد دارد).', esc_html( number_format_i18n( $count ) ) );
	}

	hodima_admin_flash( '<strong>مشاور سئو:</strong> ' . $message, 'success' );
	wp_safe_redirect( hodima_seo_advisor_page_url() );
	exit;
}

/**
 * ذخیره یا برداشتن «کلید مخصوص مشاور». کلید پیش از ذخیره بررسی می‌شود؛ بعد از هر تغییر توکن و
 * فهرست propertyهای قبلی پاک می‌شوند (حساب عوض شده) و خطای قدیمی دریافت داده می‌رود.
 */
function hodima_seo_advisor_handle_key(): void {

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'اجازه این کار را ندارید.', 'دسترسی ندارید', [ 'response' => 403 ] );
	}

	check_admin_referer( 'hodima_advisor_key' );

	$old_email = hodima_seo_sc_email( HODIMA_SEO_SC_ADVISOR );

	if ( ! empty( $_POST['remove'] ) ) {
		delete_option( HODIMA_SEO_SC_ADVISOR_KEY_OPTION );
		$message = [ 'کلید مخصوص مشاور برداشته شد؛ مشاور از کلید مشترک (دیسکاور یا ایندکس گوگل) می‌خواند.', 'success' ];
	} else {
		$json  = isset( $_POST['key_json'] ) ? trim( wp_unslash( (string) $_POST['key_json'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON کلید است؛ پیش از ذخیره با hodima_seo_sc_validate_key بررسی می‌شود
		$check = hodima_seo_sc_validate_key( $json );

		if ( ! $check['ok'] ) {
			hodima_admin_flash( '<strong>مشاور سئو:</strong> ' . esc_html( $check['message'] ), 'error' );
			wp_safe_redirect( hodima_seo_advisor_page_url() );
			exit;
		}

		update_option( HODIMA_SEO_SC_ADVISOR_KEY_OPTION, $json, false );
		$message = [ sprintf( 'کلید مخصوص مشاور ذخیره شد (%s). این ایمیل را در Search Console با دسترسی «محدود» اضافه کنید، بعد «به‌روزرسانی داده» را بزنید.', esc_html( $check['email'] ) ), 'success' ];
	}

	if ( $old_email !== hodima_seo_sc_email( HODIMA_SEO_SC_ADVISOR ) ) {
		delete_option( HODIMA_SEO_SC_ADVISOR_SITES_OPTION );
		$status = get_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [] );
		if ( is_array( $status ) ) {
			$status['error'] = '';
			update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, $status, false );
		}
	}

	hodima_admin_flash( '<strong>مشاور سئو:</strong> ' . $message[0], $message[1] );
	wp_safe_redirect( hodima_seo_advisor_page_url() );
	exit;
}

function hodima_seo_advisor_handle_clear(): void {

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'اجازه این کار را ندارید.', 'دسترسی ندارید', [ 'response' => 403 ] );
	}

	check_admin_referer( 'hodima_advisor_clear' );

	if ( empty( $_POST['confirm'] ) ) {
		hodima_admin_flash( '<strong>مشاور سئو:</strong> برای پاک کردن باید کادر تأیید را علامت بزنید؛ چیزی پاک نشد.', 'warning' );
	} else {
		$result = hodima_seo_advisor_clear_data();
		if ( is_wp_error( $result ) ) {
			hodima_admin_flash( '<strong>مشاور سئو:</strong> ' . esc_html( $result->get_error_message() ), 'error' );
		} else {
			hodima_admin_flash( '<strong>مشاور سئو:</strong> داده‌های مشاور پاک شد. برای دریافت دوباره، «به‌روزرسانی داده» را بزنید.', 'success' );
		}
	}

	wp_safe_redirect( hodima_seo_advisor_page_url() );
	exit;
}

function hodima_seo_advisor_handle_ignore(): void {

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'اجازه این کار را ندارید.', 'دسترسی ندارید', [ 'response' => 403 ] );
	}

	check_admin_referer( 'hodima_advisor_ignore' );

	// فرم فهرست POST می‌فرستد؛ لینک «بازگرداندن» کادر ویرایش (که داخل فرم خود وردپرس است و فرم تودرتو نمی‌شود) GET با nonce
	$path    = isset( $_REQUEST['path'] ) ? sanitize_text_field( wp_unslash( (string) $_REQUEST['path'] ) ) : '';
	$restore = ! empty( $_REQUEST['restore'] );

	if ( null !== hodima_seo_advisor_page( $path ) ) {
		hodima_seo_advisor_set_ignored( $path, ! $restore );
		hodima_admin_flash( $restore ? 'آدرس به فهرست پیشنهادها برگشت.' : 'آدرس نادیده گرفته شد. از نمای «نادیده‌گرفته‌شده‌ها» برش می‌گردانید.', 'success' );
	}

	wp_safe_redirect( hodima_seo_advisor_back_url() );
	exit;
}

/**
 * مقدار سلول CSV: ورودی که با = + - @ شروع شود در اکسل فرمول می‌شود، پس یک ' جلویش.
 */
function hodima_seo_advisor_csv_cell( string $value ): string {
	return 1 === preg_match( '/^[=+\-@\t\r]/', $value ) ? "'" . $value : $value;
}

/**
 * عنوان نمایشی یک ردیف: نام فعلی شیء (تازه، نه نسخه ذخیره‌شده)، وگرنه عنوان ذخیره‌شده؛ خالی اگر معلوم نیست.
 *
 * @param array<string, mixed> $row
 */
function hodima_seo_advisor_row_title( array $row ): string {
	$live = hodima_seo_advisor_object_title( (string) $row['kind'], (int) $row['object_id'] );
	return '' !== $live ? $live : (string) $row['title'];
}

/**
 * ردیف‌های CSV (همه آدرس‌های فیلترشده تا ۵۰۰۰).
 *
 * @param array<string, mixed> $args آرگومان‌های hodima_seo_advisor_pages()
 * @return list<list<string>>
 */
function hodima_seo_advisor_csv_rows( array $args ): array {

	$rows = [ [ 'عنوان', 'آدرس', 'نوع', 'نمایش', 'کلیک', 'نرخ کلیک (٪)', 'رتبه', 'کلیک بالقوه', 'مهم‌ترین پیشنهاد', 'تعداد پیشنهاد' ] ];

	$pages = [];
	for ( $offset = 0; $offset < HODIMA_SEO_ADVISOR_CSV_MAX; $offset += 500 ) {
		$part = hodima_seo_advisor_pages( [ 'limit' => 500, 'offset' => $offset ] + $args )['rows'];
		array_push( $pages, ...$part );
		if ( count( $part ) < 500 ) {
			break;
		}
	}

	foreach ( $pages as $row ) {
		$rows[] = [
			hodima_seo_advisor_csv_cell( hodima_seo_advisor_row_title( $row ) ),
			hodima_seo_advisor_csv_cell( $row['path'] ),
			hodima_seo_advisor_kind_label( $row['kind'] ),
			(string) $row['impressions'],
			(string) $row['clicks'],
			$row['impressions'] > 0 ? (string) round( $row['clicks'] / $row['impressions'] * 100, 2 ) : '0',
			(string) $row['position'],
			(string) $row['potential'],
			hodima_seo_advisor_csv_cell( (string) ( $row['advice'][0]['title'] ?? '' ) ),
			(string) $row['advice_count'],
		];
	}

	return $rows;
}

function hodima_seo_advisor_handle_csv(): void {

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'اجازه این کار را ندارید.', 'دسترسی ندارید', [ 'response' => 403 ] );
	}

	check_admin_referer( 'hodima_advisor_csv' );

	$filters = hodima_seo_advisor_list_filters( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce بالا بررسی شد؛ مقدارها در همین تابع پاک‌سازی می‌شوند

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="hodima-advisor-' . gmdate( 'Y-m-d' ) . '.csv"' );

	$out = fopen( 'php://output', 'w' );
	if ( false === $out ) {
		exit;
	}

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- خروجی جریانی php://output
	fwrite( $out, "\xEF\xBB\xBF" ); // BOM: اکسل فارسی را درست باز می‌کند

	foreach ( hodima_seo_advisor_csv_rows( $filters['args'] ) as $line ) {
		fputcsv( $out, $line, ',', '"', '' );
	}

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- خروجی جریانی php://output
	fclose( $out );
	exit;
}

/* ── صفحه ── */

/**
 * تب‌های بالای صفحه: فرصت‌های بهتر شدن | رقابت صفحه‌ها.
 */
function hodima_seo_advisor_render_tabs( string $active ): void {

	$tabs = [
		'opportunities' => [ 'فرصت‌های بهتر شدن', hodima_seo_advisor_page_url() ],
		'competition'   => [ 'رقابت صفحه‌ها', hodima_seo_advisor_page_url( [ 'tab' => 'competition' ] ) ],
	];

	echo '<nav class="hodima-advisor__views hodima-advisor__tabs" aria-label="بخش‌های مشاور">';
	foreach ( $tabs as $key => [ $label, $url ] ) {
		printf( '<a class="hodima-advisor__view" href="%1$s"%2$s>%3$s</a>', esc_url( $url ), $key === $active ? ' aria-current="page"' : '', esc_html( $label ) );
	}
	echo '</nav>';
}

function hodima_seo_advisor_render_admin(): void {

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'اجازه این کار را ندارید.', 'دسترسی ندارید', [ 'response' => 403 ] );
	}

	$has_key = '' !== hodima_seo_sc_key_json( HODIMA_SEO_SC_ADVISOR );
	$status  = hodima_seo_advisor_status();
	$totals  = hodima_seo_advisor_totals();
	$has_data = $status['fetched'] > 0 && $totals['pages'] > 0;

	hodima_seo_ui_open( [
		'title'       => 'مشاور سئو',
		'description' => 'با داده Search Console همین سایت می‌گوید هر صفحه چه کاری بیشترین کلیک اضافه را می‌آورد. فقط پیشنهاد می‌دهد؛ چیزی را خودکار عوض نمی‌کند.',
		'icon'        => 'dashicons-chart-line',
		'class'       => 'hodima-advisor',
	] );

	if ( '' !== $status['error'] ) {
		hodima_admin_notice( '<strong>آخرین دریافت داده ناموفق بود:</strong> ' . esc_html( $status['error'] ), 'error' );
	}

	hodima_seo_ui_body_open();

	$n     = static fn( int|float $value ): string => number_format_i18n( $value );
	$empty = ! $has_data;
	$tab   = $has_data && 'competition' === ( isset( $_GET['tab'] ) ? sanitize_key( (string) $_GET['tab'] ) : '' ) ? 'competition' : 'opportunities'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط تب نمایش

	if ( $has_data ) {
		hodima_seo_advisor_render_tabs( $tab );
	}

	if ( 'competition' === $tab ) {
		hodima_seo_advisor_render_competition( $status );
		hodima_seo_advisor_render_settings( $has_data );
		hodima_seo_ui_close();
		return;
	}

	hodima_seo_ui_stats( [
		[ 'label' => 'صفحه با فرصت', 'value' => $empty ? '—' : esc_html( $n( $totals['advised'] ) ), 'empty' => $empty ],
		[ 'label' => 'نمایش در گوگل', 'value' => $empty ? '—' : esc_html( $n( $totals['impressions'] ) ), 'sub' => $empty ? '' : esc_html( sprintf( '%s روز اخیر', $n( $status['days'] ) ) ), 'empty' => $empty ],
		[ 'label' => 'کلیک', 'value' => $empty ? '—' : esc_html( $n( $totals['clicks'] ) ), 'empty' => $empty ],
		[ 'label' => 'میانگین رتبه', 'value' => $empty ? '—' : esc_html( number_format_i18n( $totals['position'], 1 ) ), 'empty' => $empty ],
	] );

	hodima_seo_advisor_render_action( $has_key, $status );

	if ( ! $has_key ) {
		hodima_seo_advisor_render_no_key();
	} elseif ( ! $has_data ) {
		hodima_seo_ui_card_open( 'هنوز داده‌ای نیست' );
		hodima_seo_ui_empty( 'اولین دریافت داده را بزنید', 'دکمه «به‌روزرسانی داده» را بزنید؛ از این به بعد خودکار هر روز گرفته می‌شود. داده سرچ کنسول ۲ تا ۳ روز تاخیر دارد.' );
		hodima_seo_ui_card_close();
	} else {
		hodima_seo_advisor_render_list( $status );
	}

	hodima_seo_advisor_render_settings( $has_data );

	hodima_seo_ui_close();
}

/**
 * کنش اصلی: انتخاب بازه + «به‌روزرسانی داده».
 *
 * @param array<string, mixed> $status خروجی hodima_seo_advisor_status()
 */
function hodima_seo_advisor_render_action( bool $has_key, array $status ): void {

	$days   = hodima_seo_advisor_days();
	$labels = [ 28 => '۲۸ روز', 90 => '۳ ماه', 180 => '۶ ماه' ];
	?>
	<section class="hds-card hodima-advisor__bar" aria-label="دریافت داده">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="hodima-advisor__form">
			<input type="hidden" name="action" value="hodima_advisor_refresh">
			<?php wp_nonce_field( 'hodima_advisor_refresh' ); ?>
			<fieldset class="hodima-advisor__seg">
				<legend class="screen-reader-text">بازه داده</legend>
				<?php foreach ( $labels as $value => $label ) : ?>
					<label class="hodima-advisor__seg-opt">
						<input type="radio" name="days" value="<?php echo esc_attr( (string) $value ); ?>"<?php checked( $days, $value ); ?>>
						<span><?php echo esc_html( $label ); ?></span>
					</label>
				<?php endforeach; ?>
			</fieldset>
			<?php if ( $has_key && $status['fetched'] > 0 && '' === $status['error'] ) : ?>
				<span class="hds-pill"><?php echo esc_html( sprintf( 'متصل به %1$s · آخرین دریافت %2$s پیش', $status['property'], human_time_diff( $status['fetched'] ) ) ); ?></span>
			<?php elseif ( $has_key ) : ?>
				<span class="hds-pill hds-pill--info">کلید ثبت شده؛ داده هنوز گرفته نشده</span>
			<?php else : ?>
				<span class="hds-pill hds-pill--warn">به Search Console وصل نیست</span>
			<?php endif; ?>
			<button type="submit" class="hds-btn hodima-advisor__refresh"<?php disabled( ! $has_key ); ?>>
				<span class="dashicons dashicons-update" aria-hidden="true"></span> به‌روزرسانی داده
			</button>
		</form>
	</section>
	<?php
}

/**
 * کارت اختیاری «تنظیمات مشاور» (جمع‌شده): حداقل نمایش، پاک کردن داده‌ها و دو یادداشت.
 * طرح تأییدشده: Artifact «طرح کارت تنظیمات مشاور سئو» (۴ آبان ۱۴۰۵).
 */
function hodima_seo_advisor_render_settings( bool $has_data ): void {

	[ $low, $high ] = HODIMA_SEO_ADVISOR_MIN_IMPRESSIONS_RANGE;
	$min            = hodima_seo_advisor_config()['min_impressions'];
	?>
	<section class="hds-card hodima-advisor__settings" aria-label="تنظیمات مشاور">
		<details class="hds-more"<?php echo '' === hodima_seo_sc_key_json( HODIMA_SEO_SC_ADVISOR ) ? ' open' : ''; // بدون کلید، کارت باز است: اولین کار وارد کردن کلید ?>>
			<summary class="hds-more__summary">تنظیمات مشاور <span class="hds-more__hint">کلید گوگل · حداقل نمایش · پاک کردن داده‌ها</span></summary>
			<div class="hds-more__body hodima-advisor__settings-grid">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="hodima-advisor__setting">
					<input type="hidden" name="action" value="hodima_advisor_settings">
					<?php wp_nonce_field( 'hodima_advisor_settings' ); ?>
					<h3 class="hodima-advisor__setting-title"><label for="hodima-advisor-min">از چند نمایش به بالا پیشنهاد بدهم؟</label></h3>
					<div class="hds-unit">
						<input type="number" id="hodima-advisor-min" name="min_impressions" class="hds-input" min="<?php echo esc_attr( (string) $low ); ?>" max="<?php echo esc_attr( (string) $high ); ?>" step="10" value="<?php echo esc_attr( (string) $min ); ?>">
						<span>نمایش در بازه</span>
					</div>
					<p class="hodima-advisor__setting-help">پیش‌فرض ۱٬۰۰۰. عدد کمتر یعنی صفحه‌های کم‌بازدیدتر هم پیشنهاد می‌گیرند. بعد از ذخیره، پیشنهادها همین حالا با داده موجود دوباره ساخته می‌شوند.</p>
					<button type="submit" class="hds-btn">ذخیره</button>
				</form>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="hodima-advisor__setting">
					<input type="hidden" name="action" value="hodima_advisor_clear">
					<?php wp_nonce_field( 'hodima_advisor_clear' ); ?>
					<h3 class="hodima-advisor__setting-title">داده‌های مشاور را پاک کنم؟</h3>
					<p class="hodima-advisor__setting-help">جدول‌های دریافت‌شده از Search Console و فهرست «نادیده گرفتن» پاک می‌شوند. کلید حساب سرویس و تنظیمات دیگر می‌مانند و با «به‌روزرسانی داده» دوباره گرفته می‌شود. بازگشت ندارد.</p>
					<label class="hodima-advisor__confirm">
						<input type="checkbox" name="confirm" value="1"<?php disabled( ! $has_data ); ?>>
						<span>مطمئنم؛ داده‌ها پاک شود</span>
					</label>
					<button type="submit" class="hds-btn hds-btn--outline hodima-advisor__danger"<?php disabled( ! $has_data ); ?>>پاک کردن داده‌ها</button>
				</form>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="hodima-advisor__setting hodima-advisor__setting--wide">
					<input type="hidden" name="action" value="hodima_advisor_key">
					<?php wp_nonce_field( 'hodima_advisor_key' ); ?>
					<h3 class="hodima-advisor__setting-title"><label for="hodima-advisor-key">کلید حساب سرویس مخصوص مشاور</label></h3>
					<?php
					$source = hodima_seo_sc_key_source( HODIMA_SEO_SC_ADVISOR );
					$email  = hodima_seo_sc_email( HODIMA_SEO_SC_ADVISOR );
					$labels = [
						'advisor_config' => [ 'از کلید ثابت wp-config', '' ],
						'advisor_own'    => [ 'از کلید مخصوص مشاور', '' ],
						'own'            => [ 'از کلید مشترک دیسکاور', 'hds-pill--info' ],
						'indexing'       => [ 'از حساب سرویس ایندکس گوگل', 'hds-pill--info' ],
					];
					[ $source_label, $source_class ] = $labels[ $source ] ?? [ 'کلیدی تنظیم نشده', 'hds-pill--warn' ];
					?>
					<p class="hodima-advisor__setting-help">
						<span class="hds-pill <?php echo esc_attr( $source_class ); ?>"><?php echo esc_html( $source_label ); ?></span>
						<?php if ( '' !== $email ) : ?>
							<bdi class="hds-code"><?php echo esc_html( $email ); ?></bdi>
						<?php endif; ?>
					</p>
					<p class="hodima-advisor__setting-help">بهتر است برای مشاور یک حساب سرویس جدا بسازید و در Search Console فقط با دسترسی «محدود» (فقط خواندن) به سایت اضافه کنید؛ این‌طور با دیسکاور و ایندکس گوگل قاطی نمی‌شود. تا کلید مخصوص نگذاشته‌اید، همان کلید مشترک کار می‌کند.</p>
					<?php if ( 'advisor_config' === $source ) : ?>
						<p class="hodima-advisor__setting-help">کلید از ثابت <bdi class="hds-code">HODIMA_SEO_ADVISOR_SERVICE_ACCOUNT_JSON</bdi> در wp-config.php خوانده می‌شود و از اینجا عوض نمی‌شود.</p>
					<?php else : ?>
						<textarea id="hodima-advisor-key" name="key_json" class="hds-input hodima-advisor__key" rows="4" dir="ltr" autocomplete="off" spellcheck="false" placeholder="کل محتوای فایل JSON حساب سرویس را اینجا بچسبانید"></textarea>
						<p class="hodima-advisor__setting-help">امن‌تر: به‌جای چسباندن اینجا، در wp-config.php بنویسید <bdi class="hds-code">define( 'HODIMA_SEO_ADVISOR_SERVICE_ACCOUNT_JSON', '…' );</bdi> (کلید پیشخوان در دیتابیس و بکاپ دیده می‌شود). کلید ذخیره‌شده دوباره نمایش داده نمی‌شود.</p>
						<div class="hodima-advisor__setting-actions">
							<button type="submit" class="hds-btn">ذخیره کلید</button>
							<?php if ( 'advisor_own' === $source ) : ?>
								<button type="submit" name="remove" value="1" class="hds-btn hds-btn--outline hodima-advisor__danger">برداشتن کلید مخصوص</button>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</form>
				<div class="hodima-advisor__note">
					<strong>چرا جمع کلمه‌ها از نمایش صفحه کمتر است؟</strong>
					گوگل کلمه‌های ناشناس را نمی‌دهد و برای هر صفحه فقط ۵۰ کلمه با دست‌کم ۵ نمایش ذخیره می‌شود.

				</div>
			</div>
		</details>
	</section>
	<?php
}

function hodima_seo_advisor_render_no_key(): void {

	hodima_seo_ui_card_open( 'اتصال به Search Console' );
	hodima_seo_ui_empty(
		'کلید حساب سرویس گوگل ثبت نشده است',
		sprintf(
			'فایل JSON حساب سرویس را در پایین همین صفحه (کارت «تنظیمات مشاور») وارد کنید، یا کلید «گوگل دیسکاور» را در <a href="%s">آمار سرچ کنسول</a> بگذارید که مشاور هم از آن استفاده می‌کند. ایمیل حساب را در Search Console (تنظیمات ← کاربران و مجوزها) با دسترسی «محدود» اضافه کنید.',
			esc_url( admin_url( 'admin.php?page=hodima-discover&tab=settings' ) )
		)
	);
	hodima_seo_ui_card_close();
}

/**
 * کارت فرصت‌ها: نوار ابزار + ردیف‌ها + صفحه‌بندی.
 *
 * @param array<string, mixed> $status خروجی hodima_seo_advisor_status()
 */
function hodima_seo_advisor_render_list( array $status ): void {

	$filters = hodima_seo_advisor_list_filters( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط فیلتر نمایش؛ مقدارها پاک‌سازی می‌شوند
	$view    = $filters['view'];
	$paged   = max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$result  = hodima_seo_advisor_pages( $filters['args'] + [ 'limit' => HODIMA_SEO_ADVISOR_PER_PAGE, 'offset' => ( $paged - 1 ) * HODIMA_SEO_ADVISOR_PER_PAGE ] );
	$counts  = hodima_seo_advisor_group_counts();
	$ignored = count( hodima_seo_advisor_ignored() );
	$max     = max( 1, hodima_seo_advisor_max_potential() );
	$total_advised = hodima_seo_advisor_totals()['advised'];

	$views = [
		'all'      => [ 'همه', $total_advised ],
		'product'  => [ 'محصول‌ها', $counts['product'] ],
		'category' => [ 'دسته‌ها', $counts['category'] ],
		'content'  => [ 'مقاله و برگه', $counts['content'] ],
	];
	if ( 'ignored' === $view ) {
		$views = [ 'all' => [ 'برگشت به فهرست', $total_advised ], 'ignored' => [ 'نادیده‌گرفته‌شده‌ها', $ignored ] ];
	}

	$keep = [ 'view' => 'all' === $view ? '' : $view, 'q' => $filters['q'], 'rule' => $filters['rule'] ];

	hodima_seo_ui_card_open( 'فرصت‌های بهتر شدن', sprintf( 'صفحه‌هایی که با داده %s روز اخیر بیشترین کلیک اضافه را دارند، مرتب بر اساس «کلیک بالقوه» (تخمین).', number_format_i18n( $status['days'] ) ) );
	?>
	<form method="get" class="hodima-advisor__toolbar">
		<input type="hidden" name="page" value="<?php echo esc_attr( HODIMA_SEO_ADVISOR_PAGE ); ?>">
		<?php if ( 'all' !== $view ) : ?>
			<input type="hidden" name="view" value="<?php echo esc_attr( $view ); ?>">
		<?php endif; ?>
		<nav class="hodima-advisor__views" aria-label="نوع صفحه">
			<?php foreach ( $views as $key => [ $label, $count ] ) : ?>
				<a class="hodima-advisor__view" href="<?php echo esc_url( hodima_seo_advisor_page_url( [ 'view' => 'all' === $key ? '' : $key, 'q' => $filters['q'], 'rule' => $filters['rule'] ] ) ); ?>"<?php echo $key === $view ? ' aria-current="page"' : ''; ?>>
					<?php echo esc_html( $label ); ?> <span class="hodima-advisor__count"><?php echo esc_html( number_format_i18n( $count ) ); ?></span>
				</a>
			<?php endforeach; ?>
		</nav>
		<div class="hodima-advisor__search">
			<label class="screen-reader-text" for="hodima-advisor-q">جستجو در عنوان یا آدرس صفحه</label>
			<input type="search" id="hodima-advisor-q" name="q" class="hds-input" value="<?php echo esc_attr( $filters['q'] ); ?>" placeholder="جستجو در عنوان یا آدرس…">
			<button type="submit" class="hodima-advisor__go" aria-label="اعمال جستجو و فیلتر"><span class="dashicons dashicons-search" aria-hidden="true"></span></button>
		</div>
		<label class="hodima-advisor__rule">
			<span class="screen-reader-text">نوع پیشنهاد</span>
			<select name="rule" class="hds-input">
				<option value="">همه پیشنهادها</option>
				<?php foreach ( [ 'low_ctr', 'near_first', 'missing_headings', 'missing_content', 'weak_query', 'cannibal', 'decline' ] as $rule ) : ?>
					<option value="<?php echo esc_attr( $rule ); ?>"<?php selected( $filters['rule'], $rule ); ?>><?php echo esc_html( hodima_seo_advisor_rule_label( $rule ) ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<a class="hds-btn hds-btn--outline" href="<?php echo esc_url( wp_nonce_url( add_query_arg( [ 'action' => 'hodima_advisor_csv' ] + array_filter( $keep ), admin_url( 'admin-post.php' ) ), 'hodima_advisor_csv' ) ); ?>">خروجی CSV</a>
	</form>
	<?php

	if ( ! $result['rows'] ) {
		hodima_seo_ui_empty( 'اینجا چیزی نیست', 'ignored' === $view ? 'هیچ آدرسی نادیده گرفته نشده است.' : 'با این فیلتر آدرسی پیشنهاد ندارد. فیلتر را عوض کنید یا بعد از دریافت داده تازه دوباره ببینید.' );
		hodima_seo_ui_card_close();
		return;
	}
	?>
	<div class="hodima-advisor__table">
		<div class="hodima-advisor__head" aria-hidden="true">
			<span>صفحه</span><span>نمایش</span><span>نرخ کلیک</span><span>رتبه</span><span>ایرادها</span><span>کلیک بالقوه</span><span></span>
		</div>
		<?php foreach ( $result['rows'] as $row ) : ?>
			<?php hodima_seo_advisor_render_row( $row, $max, 'ignored' === $view ); ?>
		<?php endforeach; ?>
	</div>
	<?php

	$pages = (int) ceil( $result['total'] / HODIMA_SEO_ADVISOR_PER_PAGE );
	?>
	<div class="hodima-advisor__foot">
		<span>
			<?php echo esc_html( sprintf( 'نمایش %1$s از %2$s آدرس، مرتب‌شده بر اساس کلیک بالقوه', number_format_i18n( count( $result['rows'] ) ), number_format_i18n( $result['total'] ) ) ); ?>
			<?php if ( $ignored > 0 && 'ignored' !== $view ) : ?>
				· <a href="<?php echo esc_url( hodima_seo_advisor_page_url( [ 'view' => 'ignored' ] ) ); ?>"><?php echo esc_html( sprintf( '%s آدرس نادیده گرفته شده', number_format_i18n( $ignored ) ) ); ?></a>
			<?php endif; ?>
		</span>
		<?php
		if ( $pages > 1 ) {
			echo '<nav class="hodima-advisor__pager" aria-label="صفحه‌بندی">';
			echo wp_kses_post( (string) paginate_links( [
				'base'      => add_query_arg( 'paged', '%#%', hodima_seo_advisor_page_url( array_filter( $keep ) ) ),
				'format'    => '',
				'current'   => $paged,
				'total'     => $pages,
				'prev_text' => 'قبلی',
				'next_text' => 'بعدی',
			] ) );
			echo '</nav>';
		}
		?>
	</div>
	<?php
	hodima_seo_ui_card_close();
}

/**
 * یک ردیف فهرست: فقط گزارش تیتری (عنوان صفحه، عددها، همه ایرادها به‌صورت تیتر کوتاه). جزئیات و
 * گام‌های رفع در کادر «مشاور سئو» صفحه ویرایش همان آدرس است؛ دکمه ردیف به آنجا می‌برد.
 *
 * @param array<string, mixed> $row
 */
function hodima_seo_advisor_render_row( array $row, int $max, bool $ignored_view ): void {

	$impressions = (int) $row['impressions'];
	$title       = hodima_seo_advisor_row_title( $row );
	$ctr         = $impressions > 0 ? $row['clicks'] / $impressions * 100 : 0.0;
	$expected    = hodima_seo_advisor_expected_ctr( (float) $row['position'] );
	$advice      = (array) $row['advice'];
	$edit        = $row['object_id'] > 0 ? hodima_seo_advisor_edit_link( (string) $row['kind'], (int) $row['object_id'] ) : '';
	?>
	<div class="hodima-advisor__item">
		<div class="hodima-advisor__grid">
			<span class="hodima-advisor__page">
				<?php if ( '' !== $title ) : ?>
					<strong class="hodima-advisor__name"><?php echo esc_html( $title ); ?></strong>
				<?php endif; ?>
				<span class="hodima-advisor__path" dir="ltr"><?php echo esc_html( rawurldecode( (string) $row['path'] ) ); ?></span>
				<span class="hds-pill hds-pill--info"><?php echo esc_html( hodima_seo_advisor_kind_label( (string) $row['kind'] ) ); ?></span>
			</span>
			<span class="hodima-advisor__num" data-label="نمایش"><?php echo esc_html( number_format_i18n( $impressions ) ); ?></span>
			<span class="hodima-advisor__num" data-label="نرخ کلیک">
				<strong class="<?php echo $ctr < $expected ? 'hodima-advisor__low' : ''; ?>"><?php echo esc_html( number_format_i18n( $ctr, 2 ) ); ?>٪</strong>
				<small>انتظار ≈ <?php echo esc_html( number_format_i18n( $expected, 1 ) ); ?>٪</small>
			</span>
			<span class="hodima-advisor__num" data-label="رتبه"><?php echo esc_html( number_format_i18n( (float) $row['position'], 1 ) ); ?></span>
			<?php if ( $advice ) : ?>
				<ul class="hodima-advisor__issues">
					<?php foreach ( $advice as $item ) : ?>
						<li data-rule="<?php echo esc_attr( (string) $item['rule'] ); ?>"><?php echo esc_html( (string) $item['title'] ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<span class="hodima-advisor__issues"><small>نادیده گرفته شده</small></span>
			<?php endif; ?>
			<span class="hodima-advisor__potential">
				<?php if ( $row['potential'] > 0 ) : ?>
					<strong>≈ <?php echo esc_html( number_format_i18n( (int) $row['potential'] ) ); ?></strong>
					<span class="hodima-advisor__bar-track" aria-hidden="true"><span style="inline-size: <?php echo esc_attr( (string) max( 4, (int) round( $row['potential'] / $max * 100 ) ) ); ?>%"></span></span>
				<?php else : ?>
					<small>—</small>
				<?php endif; ?>
			</span>
			<span class="hodima-advisor__actions">
				<?php if ( ! $ignored_view && '' !== $edit ) : ?>
					<a class="hds-btn" href="<?php echo esc_url( $edit ); ?>">ویرایش و رفع ایرادها</a>
				<?php elseif ( ! $ignored_view ) : ?>
					<a class="hds-btn hds-btn--outline" href="<?php echo esc_url( hodima_seo_advisor_url( (string) $row['path'] ) ); ?>" target="_blank" rel="noopener">دیدن در سایت</a>
				<?php endif; ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="hodima_advisor_ignore">
					<input type="hidden" name="path" value="<?php echo esc_attr( (string) $row['path'] ); ?>">
					<?php if ( $ignored_view ) : ?>
						<input type="hidden" name="restore" value="1">
					<?php endif; ?>
					<?php wp_nonce_field( 'hodima_advisor_ignore' ); ?>
					<button type="submit" class="hodima-advisor__ignore"><?php echo $ignored_view ? 'بازگرداندن به فهرست' : 'نادیده گرفتن'; ?></button>
				</form>
			</span>
		</div>
	</div>
	<?php
}
