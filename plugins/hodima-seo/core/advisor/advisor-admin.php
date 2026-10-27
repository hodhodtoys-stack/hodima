<?php
/**
 * ماژول «دستار سئو» — صفحه پیشخوان
 * Path: core/advisor/advisor-admin.php
 *
 * «ابزارهای هدیما ← دستار سئو» (admin.php?page=hodima-advisor). نوع صفحه «فهرست و جدول»
 * با کالبد ثابت کیت SEO (inc/ui-skeleton.php): سربرگ ← ۴ کارت آمار ← یک کنش اصلی
 * («به‌روزرسانی همه» با انتخاب بازه) ← کارت فرصت‌ها (نوار ابزار یک‌ردیفه: نماها، جستجو،
 * نوع پیشنهاد، خروجی CSV؛ ردیف‌های جمع‌شونده). طرح تأییدشده: Artifact «طرح دستار سئو»
 * (HODIMA-AUDIT.md بخش ۲۵۶). چیزی در سایت عوض نمی‌شود؛ فقط «نادیده گرفتن» یک آدرس و بازه
 * ذخیره می‌شوند. بدون JS کار می‌کند.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

const HODIMA_SEO_ADVISOR_PAGE     = 'hodima-advisor';
const HODIMA_SEO_ADVISOR_PER_PAGE = 15;

/** گزینه‌های فیلتر «حداقل کلیک بالقوه» (۰ = همه). */
const HODIMA_SEO_ADVISOR_MIN_POTENTIAL_CHOICES = [ 0, 50, 100, 500, 1000 ];

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
		? add_submenu_page( $parent, 'دستار سئو', 'دستار سئو', 'manage_options', HODIMA_SEO_ADVISOR_PAGE, 'hodima_seo_advisor_render_admin' )
		: add_menu_page( 'دستار سئو', 'دستار سئو', 'manage_options', HODIMA_SEO_ADVISOR_PAGE, 'hodima_seo_advisor_render_admin', 'dashicons-chart-line', 32 );

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

	$script = HODIMA_SEO_DIR . '/core/advisor/advisor-admin.js';
	wp_enqueue_script( 'hodima-seo-advisor', HODIMA_SEO_URL . '/core/advisor/advisor-admin.js', [ 'hodima-seo-ui' ], is_file( $script ) ? (string) filemtime( $script ) : HODIMA_SEO_VERSION, true );
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
 * @return array{view: string, q: string, rule: string, min: int, tasks: string, handled: array<string, string>, args: array<string, mixed>}
 */
function hodima_seo_advisor_list_filters( array $query ): array {

	$view = sanitize_key( (string) ( $query['view'] ?? 'all' ) );
	$view = in_array( $view, [ 'all', 'ignored', ...array_keys( HODIMA_SEO_ADVISOR_KIND_GROUPS ) ], true ) ? $view : 'all';
	$q    = sanitize_text_field( (string) ( $query['q'] ?? '' ) );
	$rule = sanitize_key( (string) ( $query['rule'] ?? '' ) );
	$min  = absint( $query['min'] ?? 0 );
	$min  = in_array( $min, HODIMA_SEO_ADVISOR_MIN_POTENTIAL_CHOICES, true ) ? $min : 0;
	// tasks=all: آدرس‌هایی که همه ایرادهایشان «انجام شد/رد شد» خورده هم دیده شوند (پیش‌فرض: فقط کارهای باز)
	$tasks = 'all' === ( $query['tasks'] ?? '' ) ? 'all' : '';
	$args  = [ 'orderby' => 'potential', 'order' => 'DESC', 'search' => $q, 'rule' => $rule, 'min_potential' => $min ];

	if ( 'ignored' === $view ) {
		$args['paths'] = hodima_seo_advisor_ignored();
	} else {
		$args['has_advice'] = true;
		$handled            = function_exists( 'hodima_seo_advisor_handled_paths' ) ? hodima_seo_advisor_handled_paths() : [];
		if ( '' === $tasks ) {
			$args['exclude'] = array_keys( $handled );
		}
		if ( isset( HODIMA_SEO_ADVISOR_KIND_GROUPS[ $view ] ) ) {
			$args['kind'] = HODIMA_SEO_ADVISOR_KIND_GROUPS[ $view ];
		}
	}

	return [ 'view' => $view, 'q' => $q, 'rule' => $rule, 'min' => $min, 'tasks' => $tasks, 'handled' => $handled ?? [], 'args' => $args ];
}

/* ── کنش‌ها ── */

/**
 * آدرس کامل صفحه (دامنه + مسیر خوانا) به‌صورت لینک قابل‌کلیک که در زبانه تازه باز می‌شود. HTML امن‌شده برمی‌گرداند.
 * آدرس بلند بریده نمی‌شود؛ هرجا لازم باشد می‌شکند.
 *
 * @param string $path مسیر یکسان‌شده (/x/) یا آدرس کامل
 */
function hodima_seo_advisor_url_link( string $path, string $css_class = 'hodima-advisor__rival-path' ): string {

	$url = str_contains( $path, '://' ) ? $path : hodima_seo_advisor_url( $path );

	return sprintf(
		'<a class="%1$s" href="%2$s" target="_blank" rel="noopener" dir="ltr"><bdi>%3$s</bdi></a>',
		esc_attr( $css_class ),
		esc_url( $url ),
		esc_html( rawurldecode( $url ) )
	);
}

/**
 * دکمه کنش کوچک صفحه (ویرایش صفحه، دیدن در سایت، نادیده گرفتن…): همیشه دکمه کادردار با آیکون، نه کلمه لینکی
 * (SEO 2.9.5؛ خواست کاربر: «این‌ها دکمه داشته باشد»). HTML امن‌شده برمی‌گرداند.
 *
 * @param string $icon نام Dashicon بدون پیشوند (edit، external، hidden، undo…)
 */
function hodima_seo_advisor_action( string $url, string $label, string $icon = '', bool $external = false ): string {
	return sprintf(
		'<a class="hds-btn hds-btn--outline hodima-advisor__act" href="%1$s"%2$s>%3$s%4$s</a>',
		esc_url( $url ),
		$external ? ' target="_blank" rel="noopener"' : '',
		'' !== $icon ? '<span class="dashicons dashicons-' . esc_attr( $icon ) . '" aria-hidden="true"></span>' : '',
		esc_html( $label )
	);
}

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

	$result = hodima_seo_advisor_refresh( true ); // دستی: دستگاه/کشور و روند بدون فاصله ۱۲ ساعته

	if ( is_wp_error( $result ) ) {
		hodima_admin_flash( '<strong>دستار سئو:</strong> ' . esc_html( $result->get_error_message() ), 'error' );
	} else {
		$extra = hodima_seo_advisor_refresh_extras();
		$status = hodima_seo_advisor_status();
		hodima_admin_flash(
			sprintf(
				'<strong>دستار سئو:</strong> داده %1$s روز اخیر گرفته شد؛ %2$s آدرس بررسی و برای %3$s آدرس پیشنهاد ساخته شد.',
				esc_html( number_format_i18n( $status['days'] ) ),
				esc_html( number_format_i18n( $status['pages'] ) ),
				esc_html( number_format_i18n( $status['advised'] ) )
			) . ( hodima_seo_advisor_pipeline_pending() ? ' ' . esc_html( 'ساخت پیشنهادها و بقیه تحلیل‌ها در پس‌زمینه ادامه دارد؛ چند دقیقه بعد صفحه را تازه کنید.' ) : '' )
			. ( $extra ? ' ' . esc_html( implode( ' ', $extra ) ) : '' ),
			'success'
		);
	}

	$tab = isset( $_POST['tab'] ) ? sanitize_key( (string) $_POST['tab'] ) : '';
	wp_safe_redirect( hodima_seo_advisor_page_url( [ 'tab' => in_array( $tab, hodima_seo_advisor_tab_keys(), true ) && 'opportunities' !== $tab ? $tab : '' ] ) );
	exit;
}

/**
 * بقیه بخش‌ها بعد از دریافت دستی (دکمه «به‌روزرسانی همه»): بررسی ایندکس یک دسته در پس‌زمینه و خواندن فایل‌های
 * خزش اگر از آخرین خواندن تازه‌تر و آماده‌اند. جمله‌های کوتاه نتیجه برای پیام.
 *
 * @return list<string>
 */
function hodima_seo_advisor_refresh_extras(): array {

	$out = [];

	if ( function_exists( 'hodima_seo_advisor_inspect_pending' ) && (bool) apply_filters( 'hodima_seo_advisor_inspect_enabled', true ) && hodima_seo_advisor_inspect_pending() > 0 ) {
		if ( ! wp_next_scheduled( HODIMA_SEO_ADVISOR_INSPECT_CRON ) || wp_next_scheduled( HODIMA_SEO_ADVISOR_INSPECT_CRON ) > time() + 60 ) {
			wp_schedule_single_event( time() + 30, HODIMA_SEO_ADVISOR_INSPECT_CRON );
		}
		$out[] = 'بررسی ایندکس یک دسته از آدرس‌ها در پس‌زمینه شروع شد.';
	}

	if ( function_exists( 'hodima_seo_advisor_crawl_import' ) ) {
		$file     = hodima_seo_advisor_crawl_file_state( 'pages' );
		$imported = hodima_seo_advisor_crawl_status()['imported'];
		if ( 'ready' === $file['state'] && $file['mtime'] > $imported ) {
			$crawl = hodima_seo_advisor_crawl_import();
			$out[] = is_wp_error( $crawl ) ? 'فایل خزش تازه خوانده نشد: ' . $crawl->get_error_message() : sprintf( 'فایل خزش تازه خوانده شد (%s صفحه).', number_format_i18n( $crawl['pages'] ) );
		}
	}

	return $out;
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
		wp_safe_redirect( hodima_seo_advisor_page_url( [ 'tab' => 'settings' ] ) );
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
			hodima_admin_flash( '<strong>دستار سئو:</strong> ' . esc_html( $count->get_error_message() ), 'error' );
			wp_safe_redirect( hodima_seo_advisor_page_url( [ 'tab' => 'settings' ] ) );
			exit;
		}
		$message = sprintf( 'تنظیم ذخیره شد؛ پیشنهادها دوباره ساخته شد (%s آدرس پیشنهاد دارد).', esc_html( number_format_i18n( $count ) ) );
	}

	hodima_admin_flash( '<strong>دستار سئو:</strong> ' . $message, 'success' );
	wp_safe_redirect( hodima_seo_advisor_page_url( [ 'tab' => 'settings' ] ) );
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
		$message = [ 'کلید مخصوص دستار سئو برداشته شد؛ دستار سئو از کلید مشترک (دیسکاور یا ایندکس گوگل) می‌خواند.', 'success' ];
	} else {
		$json  = isset( $_POST['key_json'] ) ? trim( wp_unslash( (string) $_POST['key_json'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON کلید است؛ پیش از ذخیره با hodima_seo_sc_validate_key بررسی می‌شود
		$check = hodima_seo_sc_validate_key( $json );

		if ( ! $check['ok'] ) {
			hodima_admin_flash( '<strong>دستار سئو:</strong> ' . esc_html( $check['message'] ), 'error' );
			wp_safe_redirect( hodima_seo_advisor_page_url( [ 'tab' => 'settings' ] ) );
			exit;
		}

		update_option( HODIMA_SEO_SC_ADVISOR_KEY_OPTION, $json, false );
		$message = [ sprintf( 'کلید مخصوص دستار سئو ذخیره شد (%s). این ایمیل را در Search Console با دسترسی «محدود» اضافه کنید، بعد «به‌روزرسانی همه» را بزنید.', esc_html( $check['email'] ) ), 'success' ];
	}

	if ( $old_email !== hodima_seo_sc_email( HODIMA_SEO_SC_ADVISOR ) ) {
		delete_option( HODIMA_SEO_SC_ADVISOR_SITES_OPTION );
		$status = get_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, [] );
		if ( is_array( $status ) ) {
			$status['error'] = '';
			update_option( HODIMA_SEO_ADVISOR_STATUS_OPTION, $status, false );
		}
	}

	hodima_admin_flash( '<strong>دستار سئو:</strong> ' . $message[0], $message[1] );
	wp_safe_redirect( hodima_seo_advisor_page_url( [ 'tab' => 'settings' ] ) );
	exit;
}

function hodima_seo_advisor_handle_clear(): void {

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'اجازه این کار را ندارید.', 'دسترسی ندارید', [ 'response' => 403 ] );
	}

	check_admin_referer( 'hodima_advisor_clear' );

	if ( empty( $_POST['confirm'] ) ) {
		hodima_admin_flash( '<strong>دستار سئو:</strong> برای پاک کردن باید کادر تأیید را علامت بزنید؛ چیزی پاک نشد.', 'warning' );
	} else {
		$result = hodima_seo_advisor_clear_data();
		if ( is_wp_error( $result ) ) {
			hodima_admin_flash( '<strong>دستار سئو:</strong> ' . esc_html( $result->get_error_message() ), 'error' );
		} else {
			hodima_admin_flash( '<strong>دستار سئو:</strong> داده‌های مشاور پاک شد. برای دریافت دوباره، «به‌روزرسانی همه» را بزنید.', 'success' );
		}
	}

	wp_safe_redirect( hodima_seo_advisor_page_url( [ 'tab' => 'settings' ] ) );
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
 * کلید همه تب‌ها.
 *
 * @return list<string>
 */
function hodima_seo_advisor_tab_keys(): array {
	return [ 'opportunities', 'competition', 'terms', 'breakdown', 'trend', 'health', 'crawl', 'indexing', 'tasks', 'done', 'settings' ];
}

/**
 * تب‌های بالای صفحه: فرصت‌ها | رقابت صفحه‌ها | کلمه‌ها | دستگاه و کشور | روند و فصل‌ها | سلامت آدرس‌ها | سلامت خزش | ایندکس | کارهای من.
 */
function hodima_seo_advisor_render_tabs( string $active, bool $with_tabs = true ): void {

	$tabs = [
		'opportunities' => [ 'فرصت‌های بهتر شدن', hodima_seo_advisor_page_url() ],
		'competition'   => [ 'رقابت صفحه‌ها', hodima_seo_advisor_page_url( [ 'tab' => 'competition' ] ) ],
		'terms'         => [ 'کلمه‌ها', hodima_seo_advisor_page_url( [ 'tab' => 'terms' ] ) ],
		'breakdown'     => [ 'دستگاه و کشور', hodima_seo_advisor_page_url( [ 'tab' => 'breakdown' ] ) ],
		'trend'         => [ 'روند و فصل‌ها', hodima_seo_advisor_page_url( [ 'tab' => 'trend' ] ) ],
		'health'        => [ 'سلامت آدرس‌ها', hodima_seo_advisor_page_url( [ 'tab' => 'health' ] ) ],
		'crawl'         => [ 'سلامت خزش', hodima_seo_advisor_page_url( [ 'tab' => 'crawl' ] ) ],
		'indexing'      => [ 'ایندکس', hodima_seo_advisor_page_url( [ 'tab' => 'indexing' ] ) ],
		// «کارهای من» دو تب شد (SEO 2.9.4): کارهای باز و انجام‌شده‌ها با سه گام (انجام ← خزش گوگل ← نتیجه)
		'tasks'         => [ 'در دست انجام', hodima_seo_advisor_page_url( [ 'tab' => 'tasks' ] ) ],
		'done'          => [ 'انجام‌شده', hodima_seo_advisor_page_url( [ 'tab' => 'done' ] ) ],
		// تنظیمات تب خودش است (SEO 2.9.3)؛ قبلا پنلی بود که روی هر تب باز می‌شد
		'settings'      => [ 'تنظیمات', hodima_seo_advisor_page_url( [ 'tab' => 'settings' ] ) ],
	];

	// بدون داده فقط تب اول و «تنظیمات» معنا دارند (بقیه خالی‌اند)
	if ( ! $with_tabs ) {
		$tabs = array_intersect_key( $tabs, [ 'opportunities' => true, 'settings' => true ] );
	}

	echo '<div class="hodima-advisor__tabrow">';
	if ( $tabs ) {
		echo '<nav class="hodima-advisor__views hodima-advisor__tabs" aria-label="بخش‌های دستار سئو">';
		foreach ( $tabs as $key => [ $label, $url ] ) {
			printf(
				'<a class="hodima-advisor__view%1$s" href="%2$s"%3$s>%4$s%5$s</a>',
				'settings' === $key ? ' hodima-advisor__view--settings' : '',
				esc_url( $url ),
				$key === $active ? ' aria-current="page"' : '',
				'settings' === $key ? '<span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>' : '',
				esc_html( $label )
			);
		}
		echo '</nav>';
	}
	echo '</div>';
}

function hodima_seo_advisor_render_admin(): void {

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'اجازه این کار را ندارید.', 'دسترسی ندارید', [ 'response' => 403 ] );
	}

	$has_key = '' !== hodima_seo_sc_key_json( HODIMA_SEO_SC_ADVISOR );
	$status  = hodima_seo_advisor_status();
	$totals  = hodima_seo_advisor_totals();
	$has_data = $status['fetched'] > 0 && $totals['pages'] > 0;

	$tab = isset( $_GET['tab'] ) ? sanitize_key( (string) $_GET['tab'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط تب نمایش
	$tab = ! empty( $_GET['settings'] ) ? 'settings' : $tab; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- پیوند قدیمی settings=1
	$tab = ( $has_data || 'settings' === $tab ) && in_array( $tab, hodima_seo_advisor_tab_keys(), true ) ? $tab : 'opportunities';
	$tab = $has_key ? $tab : 'settings'; // بدون کلید اولین کار وارد کردن کلید است

	hodima_seo_ui_open( [
		'title'       => 'دستار سئو',
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

	// یک ردیف «به‌روزرسانی همه» بالای تب‌ها برای کل صفحه (SEO 2.9.3؛ قبلا در هر تب تکرار می‌شد)
	if ( 'settings' !== $tab ) {
		hodima_seo_advisor_render_toolbar( $tab );
	}
	hodima_seo_advisor_render_tabs( $tab, $has_data );

	if ( 'settings' === $tab ) {
		hodima_seo_advisor_render_settings( $has_data );
		hodima_seo_ui_close();
		return;
	}

	if ( 'opportunities' !== $tab ) {
		match ( $tab ) {
			'terms'     => hodima_seo_advisor_render_terms( $status ),
			'breakdown' => hodima_seo_advisor_render_breakdown( $status ),
			'trend'     => hodima_seo_advisor_render_trend_tab(),
			'health'    => hodima_seo_advisor_render_health( $status ),
			'crawl'     => hodima_seo_advisor_render_crawl(),
			'indexing'  => hodima_seo_advisor_render_indexing(),
			'tasks'     => hodima_seo_advisor_render_tasks( 'doing' ),
			'done'      => hodima_seo_advisor_render_tasks( 'done' ),
			default     => hodima_seo_advisor_render_competition( $status ),
		};
		if ( 'competition' === $tab ) {
			hodima_seo_advisor_render_variants();
		}
		hodima_seo_ui_close();
		return;
	}

	$handled_n = $empty || ! function_exists( 'hodima_seo_advisor_handled_paths' ) ? 0 : count( hodima_seo_advisor_handled_paths() );

	hodima_seo_ui_stats( [
		// فقط صفحه‌هایی که کار باز دارند؛ تمام‌علامت‌خورده‌ها جدا گفته می‌شوند (همان فهرست پیش‌فرض)
		[ 'label' => 'صفحه با فرصت', 'value' => $empty ? '—' : esc_html( $n( max( 0, $totals['advised'] - $handled_n ) ) ), 'sub' => $handled_n > 0 ? esc_html( sprintf( '+ %s صفحه با همه کارها علامت‌خورده', $n( $handled_n ) ) ) : '', 'empty' => $empty ],
		[ 'label' => 'نمایش در گوگل', 'value' => $empty ? '—' : esc_html( $n( $totals['impressions'] ) ), 'sub' => $empty ? '' : esc_html( sprintf( '%s روز اخیر', $n( $status['days'] ) ) ), 'empty' => $empty ],
		[ 'label' => 'کلیک', 'value' => $empty ? '—' : esc_html( $n( $totals['clicks'] ) ), 'empty' => $empty ],
		[ 'label' => 'میانگین رتبه', 'value' => $empty ? '—' : esc_html( number_format_i18n( $totals['position'], 1 ) ), 'empty' => $empty ],
	] );

	if ( ! $has_key ) {
		hodima_seo_advisor_render_no_key();
	} elseif ( ! $has_data ) {
		hodima_seo_ui_card_open( 'هنوز داده‌ای نیست' );
		hodima_seo_ui_empty( 'اولین دریافت داده را بزنید', 'دکمه «به‌روزرسانی همه» را بزنید؛ از این به بعد خودکار هر روز گرفته می‌شود. داده سرچ کنسول ۲ تا ۳ روز تاخیر دارد.' );
		hodima_seo_ui_card_close();
	} else {
		hodima_seo_advisor_render_list( $status );
	}

	hodima_seo_ui_close();
}

/**
 * ردیف «به‌روزرسانی همه»، یک بار بالای تب‌ها برای کل صفحه (جز تب تنظیمات): بازه + وضعیت اتصال + یک دکمه که داده
 * اصلی، دستگاه و کشور، روند، بررسی ایندکس و فایل‌های خزش تازه را با هم به‌روز می‌کند (hodima_seo_advisor_handle_refresh).
 *
 * @param string $tab کلید تب (برای برگشت بعد از دریافت)؛ خالی = تب اول
 */
function hodima_seo_advisor_render_toolbar( string $tab = '' ): void {

	$has_key = '' !== hodima_seo_sc_key_json( HODIMA_SEO_SC_ADVISOR );
	$status  = hodima_seo_advisor_status();
	$days    = hodima_seo_advisor_days();
	$labels  = [ 28 => '۲۸ روز', 90 => '۳ ماه', 180 => '۶ ماه' ];
	?>
	<section class="hds-card hodima-advisor__bar" aria-label="دریافت داده">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="hodima-advisor__form">
			<input type="hidden" name="action" value="hodima_advisor_refresh">
			<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>">
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
				<span class="dashicons dashicons-update" aria-hidden="true"></span> به‌روزرسانی همه
			</button>
		</form>
		<p class="hodima-advisor__bar-help">یک کلیک برای همه بخش‌ها: آمار گوگل، موبایل و کشور، روند ماه‌ها، بررسی ایندکس چند آدرس و فایل‌های خزش تازه. هر روز هم خودکار انجام می‌شود.</p>
	</section>
	<?php
}

/**
 * محتوای تب «تنظیمات» (`tab=settings`): کلید حساب سرویس، حداقل نمایش، پاک کردن داده‌ها. بدون کلید صفحه
 * خودکار روی همین تب باز می‌شود.
 */
function hodima_seo_advisor_render_settings( bool $has_data ): void {

	[ $low, $high ] = HODIMA_SEO_ADVISOR_MIN_IMPRESSIONS_RANGE;
	$min            = hodima_seo_advisor_config()['min_impressions'];
	$source         = hodima_seo_sc_key_source( HODIMA_SEO_SC_ADVISOR );
	$email          = hodima_seo_sc_email( HODIMA_SEO_SC_ADVISOR );
	$has_key        = '' !== hodima_seo_sc_key_json( HODIMA_SEO_SC_ADVISOR );
	$own            = 'advisor_own' === $source;
	$fixed          = 'advisor_config' === $source; // ثابت wp-config: از پیشخوان عوض نمی‌شود
	$source_label   = [
		'advisor_config' => 'ثابت wp-config',
		'advisor_own'    => 'کلید مخصوص دستار سئو',
		'own'            => 'کلید مشترک دیسکاور',
		'indexing'       => 'حساب سرویس ایندکس گوگل',
	][ $source ] ?? '';
	?>
	<section id="hodima-advisor-settings" class="hds-card hodima-advisor__settings" aria-labelledby="hodima-advisor-settings-title">
		<h2 class="hds-card__title" id="hodima-advisor-settings-title"><span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>تنظیمات</h2>
		<p class="hds-card__desc">اتصال به Search Console، آستانه پیشنهادها و پاک کردن داده‌ها.</p>

		<h3 class="hodima-advisor__setting-title">کلید Service Account</h3>
		<div class="hodima-advisor__keyrow">
			<?php if ( $has_key ) : ?>
				<span class="hds-pill"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>کلید فعال</span>
				<?php if ( '' !== $email ) : ?>
					<bdi class="hds-code"><?php echo esc_html( $email ); ?></bdi>
				<?php endif; ?>
				<?php if ( '' !== $source_label ) : ?>
					<span class="hds-pill hds-pill--info"><?php echo esc_html( $source_label ); ?></span>
				<?php endif; ?>
			<?php else : ?>
				<span class="hds-pill hds-pill--warn"><span class="dashicons dashicons-warning" aria-hidden="true"></span>کلیدی تنظیم نشده</span>
				<span class="hodima-advisor__setting-help">بدون کلید داده‌ای از Search Console گرفته نمی‌شود.</span>
			<?php endif; ?>
			<?php if ( ! $fixed ) : ?>
				<span class="hodima-advisor__keyrow-actions">
					<button type="button" class="hds-btn<?php echo $has_key ? ' hds-btn--outline' : ''; ?>" data-hdsa-key-open aria-haspopup="dialog">
						<span class="dashicons <?php echo $has_key ? 'dashicons-update' : 'dashicons-plus-alt2'; ?>" aria-hidden="true"></span> <?php echo esc_html( $has_key ? 'جایگزینی کلید' : 'افزودن کلید' ); ?>
					</button>
					<?php if ( $own ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="hodima-advisor__keyrow-remove" data-hdsa-confirm="کلید مخصوص دستار سئو حذف شود؟ بعد از آن از کلید مشترک (دیسکاور یا ایندکس گوگل) خوانده می‌شود، اگر باشد.">
							<input type="hidden" name="action" value="hodima_advisor_key">
							<?php wp_nonce_field( 'hodima_advisor_key' ); ?>
							<button type="submit" name="remove" value="1" class="hds-btn hds-btn--outline hodima-advisor__danger"><span class="dashicons dashicons-trash" aria-hidden="true"></span> حذف کلید</button>
						</form>
					<?php endif; ?>
				</span>
			<?php endif; ?>
		</div>
		<p class="hodima-advisor__setting-help">
			<?php if ( $fixed ) : ?>
				کلید از ثابت <bdi class="hds-code">HODIMA_SEO_ADVISOR_SERVICE_ACCOUNT_JSON</bdi> در wp-config.php خوانده می‌شود و از اینجا عوض نمی‌شود.
			<?php elseif ( $own ) : ?>
				این کلید فقط برای دستار سئو است. «حذف کلید» = برگشت به کلید مشترک (دیسکاور یا ایندکس گوگل).
			<?php else : ?>
				بهتر است برای دستار سئو یک حساب سرویس جدا بسازید و در Search Console فقط با دسترسی «محدود» (فقط خواندن) به سایت اضافه کنید؛ این‌طور با دیسکاور و ایندکس گوگل قاطی نمی‌شود. تا کلید مخصوص نگذاشته‌اید، همان کلید مشترک کار می‌کند.
			<?php endif; ?>
			امن‌تر: به‌جای ذخیره در پیشخوان، در wp-config.php بنویسید <bdi class="hds-code">define( 'HODIMA_SEO_ADVISOR_SERVICE_ACCOUNT_JSON', '…' );</bdi>
		</p>

		<div class="hodima-advisor__settings-grid">
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
				<h3 class="hodima-advisor__setting-title">داده‌های دستار سئو را پاک کنم؟</h3>
				<p class="hodima-advisor__setting-help">جدول‌های دریافت‌شده از Search Console و فهرست «نادیده گرفتن» پاک می‌شوند. کلید حساب سرویس، تاریخچه هفتگی، «کارهای من» و تنظیمات می‌مانند و با «به‌روزرسانی همه» دوباره گرفته می‌شود. بازگشت ندارد.</p>
				<label class="hodima-advisor__confirm">
					<input type="checkbox" name="confirm" value="1"<?php disabled( ! $has_data ); ?>>
					<span>مطمئنم؛ داده‌ها پاک شود</span>
				</label>
				<button type="submit" class="hds-btn hds-btn--outline hodima-advisor__danger"<?php disabled( ! $has_data ); ?>>پاک کردن داده‌ها</button>
			</form>
		</div>
		<div class="hodima-advisor__note">
			<strong>چرا جمع کلمه‌ها از نمایش صفحه کمتر است؟</strong>
			گوگل کلمه‌های ناشناس را نمی‌دهد و برای هر صفحه فقط ۵۰ کلمه با دست‌کم ۵ نمایش ذخیره می‌شود.
		</div>

		<?php if ( ! $fixed ) : ?>
			<dialog class="hds-dialog" aria-labelledby="hodima-advisor-key-title" data-hdsa-key-dialog>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="hodima_advisor_key">
					<?php wp_nonce_field( 'hodima_advisor_key' ); ?>
					<h2 class="hds-card__title" id="hodima-advisor-key-title"><?php echo esc_html( $has_key ? 'جایگزینی کلید Service Account' : 'افزودن کلید Service Account' ); ?></h2>
					<p class="hodima-advisor__setting-help">
						فایل JSON کلید را از Google Cloud Console بگیرید (<span dir="ltr">IAM &amp; Admin ← Service Accounts ← Keys ← Add key ← JSON</span>). ایمیل این حساب را در Search Console با دسترسی «محدود» به سایت اضافه کنید. کلید ذخیره‌شده دوباره نمایش داده نمی‌شود.
					</p>
					<div class="hds-dialog__pick">
						<button type="button" class="hds-btn" data-hdsa-key-pick><span class="dashicons dashicons-upload" aria-hidden="true"></span> انتخاب فایل JSON</button>
						<input type="file" accept=".json,application/json" hidden data-hdsa-key-file>
						<span class="hds-dialog__file" dir="ltr" data-hdsa-key-name aria-live="polite"></span>
					</div>
					<details class="hds-dialog__paste">
						<summary>یا محتوای فایل را بچسبانید</summary>
						<label class="screen-reader-text" for="hodima-advisor-key">محتوای فایل JSON حساب سرویس</label>
						<textarea id="hodima-advisor-key" name="key_json" class="hds-input" rows="6" dir="ltr" autocomplete="off" spellcheck="false" placeholder='{"type": "service_account", ...}' data-hdsa-key-text></textarea>
					</details>
					<p class="hds-dialog__preview" role="status" data-hdsa-key-preview></p>
					<div class="hds-dialog__actions">
						<button type="submit" class="hds-btn" data-hdsa-key-save disabled><span class="dashicons dashicons-saved" aria-hidden="true"></span> ذخیره کلید</button>
						<button type="button" class="hds-btn hds-btn--outline" data-hdsa-key-cancel>انصراف</button>
					</div>
				</form>
			</dialog>
		<?php endif; ?>
	</section>
	<?php
}

function hodima_seo_advisor_render_no_key(): void {

	hodima_seo_ui_card_open( 'اتصال به Search Console' );
	hodima_seo_ui_empty(
		'کلید حساب سرویس گوگل ثبت نشده است',
		sprintf(
			'فایل JSON حساب سرویس را در تب «تنظیمات» همین صفحه وارد کنید، یا کلید «گوگل دیسکاور» را در <a href="%s">آمار سرچ کنسول</a> بگذارید که مشاور هم از آن استفاده می‌کند. ایمیل حساب را در Search Console (تنظیمات ← کاربران و مجوزها) با دسترسی «محدود» اضافه کنید.',
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
	$handled = $filters['handled'];

	// شمار نماها با همان فهرستی بخواند که دیده می‌شود (آدرس‌های تمام‌علامت‌خورده پنهان‌اند)
	if ( '' === $filters['tasks'] ) {
		$total_advised -= count( $handled );
		foreach ( $handled as $kind ) {
			foreach ( HODIMA_SEO_ADVISOR_KIND_GROUPS as $group => $kinds ) {
				if ( in_array( $kind, $kinds, true ) ) {
					--$counts[ $group ];
				}
			}
		}
	}

	$views = [
		'all'      => [ 'همه', $total_advised ],
		'product'  => [ 'محصول‌ها', $counts['product'] ],
		'category' => [ 'دسته‌ها', $counts['category'] ],
		'content'  => [ 'مقاله و برگه', $counts['content'] ],
	];
	if ( 'ignored' === $view ) {
		$views = [ 'all' => [ 'برگشت به فهرست', $total_advised ], 'ignored' => [ 'نادیده‌گرفته‌شده‌ها', $ignored ] ];
	}

	$keep = [ 'view' => 'all' === $view ? '' : $view, 'q' => $filters['q'], 'rule' => $filters['rule'], 'min' => $filters['min'], 'tasks' => $filters['tasks'] ];

	hodima_seo_ui_card_open( 'فرصت‌های بهتر شدن', sprintf( 'صفحه‌هایی که با داده %s روز اخیر بیشترین کلیک اضافه را دارند، مرتب بر اساس «کلیک بالقوه» (تخمین).', number_format_i18n( $status['days'] ) ) );
	?>
	<form method="get" class="hodima-advisor__toolbar">
		<input type="hidden" name="page" value="<?php echo esc_attr( HODIMA_SEO_ADVISOR_PAGE ); ?>">
		<?php if ( 'all' !== $view ) : ?>
			<input type="hidden" name="view" value="<?php echo esc_attr( $view ); ?>">
		<?php endif; ?>
		<?php if ( '' !== $filters['tasks'] ) : ?>
			<input type="hidden" name="tasks" value="<?php echo esc_attr( $filters['tasks'] ); ?>">
		<?php endif; ?>
		<nav class="hodima-advisor__views" aria-label="نوع صفحه">
			<?php foreach ( $views as $key => [ $label, $count ] ) : ?>
				<a class="hodima-advisor__view" href="<?php echo esc_url( hodima_seo_advisor_page_url( [ 'view' => 'all' === $key ? '' : $key, 'q' => $filters['q'], 'rule' => $filters['rule'], 'min' => $filters['min'], 'tasks' => $filters['tasks'] ] ) ); ?>"<?php echo $key === $view ? ' aria-current="page"' : ''; ?>>
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
			<span class="screen-reader-text">حداقل کلیک بالقوه</span>
			<select name="min" class="hds-input">
				<?php foreach ( HODIMA_SEO_ADVISOR_MIN_POTENTIAL_CHOICES as $choice ) : ?>
					<option value="<?php echo esc_attr( (string) $choice ); ?>"<?php selected( $filters['min'], $choice ); ?>><?php echo esc_html( 0 === $choice ? 'هر کلیک بالقوه' : sprintf( 'بالقوه ≥ %s', number_format_i18n( $choice ) ) ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
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
		// همه کارها علامت خورده: پانویس (و لینکش) دیده نمی‌شود، پس لینک نمایش همین‌جاست (فقط نوع همین نما)
		$handled_here = isset( HODIMA_SEO_ADVISOR_KIND_GROUPS[ $view ] ) ? array_filter( $handled, static fn( string $kind ): bool => in_array( $kind, HODIMA_SEO_ADVISOR_KIND_GROUPS[ $view ], true ) ) : $handled;
		if ( 'ignored' !== $view && '' === $filters['tasks'] && $handled_here ) {
			hodima_seo_ui_empty(
				'کار بازی نمانده',
				sprintf(
					'همه ایرادهای %1$s صفحه را «انجام شد» یا «رد شد» علامت زده‌اید. %2$s',
					esc_html( number_format_i18n( count( $handled_here ) ) ),
					hodima_seo_advisor_action( hodima_seo_advisor_page_url( array_filter( [ 'tasks' => 'all' ] + $keep ) ), 'نمایش این صفحه‌ها', 'visibility' )
				)
			);
			hodima_seo_ui_card_close();
			return;
		}
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
				<?php echo hodima_seo_advisor_action( hodima_seo_advisor_page_url( [ 'view' => 'ignored' ] ), sprintf( '%s آدرس نادیده گرفته شده', number_format_i18n( $ignored ) ), 'hidden' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML امن‌شده در hodima_seo_advisor_action ?>
			<?php endif; ?>
			<?php if ( $handled && 'ignored' !== $view ) : ?>
				<?php echo hodima_seo_advisor_action( hodima_seo_advisor_page_url( array_filter( [ 'tasks' => '' === $filters['tasks'] ? 'all' : '' ] + $keep ) ), '' === $filters['tasks'] ? sprintf( 'نمایش %s صفحه که همه ایرادهایش علامت خورده', number_format_i18n( count( $handled ) ) ) : 'پنهان کردن صفحه‌های تمام‌علامت‌خورده', '' === $filters['tasks'] ? 'visibility' : 'hidden' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML امن‌شده در hodima_seo_advisor_action ?>
			<?php endif; ?>
		</span>
		<?php
		hodima_seo_ui_pager( $paged, $pages, static fn( int $i ): string => hodima_seo_advisor_page_url( array_filter( $keep ) + [ 'paged' => $i > 1 ? $i : '' ] ) );
		?>
	</div>
	<?php
	hodima_seo_ui_card_close();
}

/**
 * یک ردیف فهرست: فقط گزارش تیتری (عنوان صفحه، عددها، همه ایرادها به‌صورت تیتر کوتاه). جزئیات و
 * گام‌های رفع در کادر «دستار سئو» صفحه ویرایش همان آدرس است؛ دکمه ردیف به آنجا می‌برد.
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
				<span class="hodima-advisor__path" dir="ltr"><?php echo hodima_seo_advisor_url_link( (string) $row['path'], 'hodima-advisor__path-link' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML ساخته‌شده و escape‌شده در hodima_seo_advisor_url_link ?></span>
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
						<?php $task = function_exists( 'hodima_seo_advisor_task' ) ? hodima_seo_advisor_task( (string) $row['path'], (string) $item['rule'], (string) $item['term'] ) : null; ?>
						<li data-rule="<?php echo esc_attr( (string) $item['rule'] ); ?>"<?php echo null !== $task ? ' data-task="' . esc_attr( hodima_seo_advisor_task_state( $task ) ) . '"' : ''; ?>>
							<span class="hodima-advisor__issue-text"><?php echo esc_html( (string) $item['title'] ); ?></span>
							<?php if ( null !== $task ) : ?>
								<?php $badge = hodima_seo_advisor_task_view( $task ); ?>
								<span class="<?php echo esc_attr( $badge['class'] ); ?>"><?php echo esc_html( $badge['label'] ); ?></span>
							<?php endif; ?>
						</li>
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
					<a class="hds-btn hds-btn--outline" href="<?php echo esc_url( hodima_seo_advisor_url( (string) $row['path'] ) ); ?>" target="_blank" rel="noopener"><span class="dashicons dashicons-external" aria-hidden="true"></span> دیدن در سایت</a>
				<?php endif; ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="hodima_advisor_ignore">
					<input type="hidden" name="path" value="<?php echo esc_attr( (string) $row['path'] ); ?>">
					<?php if ( $ignored_view ) : ?>
						<input type="hidden" name="restore" value="1">
					<?php endif; ?>
					<?php wp_nonce_field( 'hodima_advisor_ignore' ); ?>
					<button type="submit" class="hds-btn hds-btn--outline hodima-advisor__act"><span class="dashicons dashicons-<?php echo $ignored_view ? 'undo' : 'hidden'; ?>" aria-hidden="true"></span><?php echo $ignored_view ? 'بازگرداندن به فهرست' : 'نادیده گرفتن'; ?></button>
				</form>
			</span>
		</div>
	</div>
	<?php
}
