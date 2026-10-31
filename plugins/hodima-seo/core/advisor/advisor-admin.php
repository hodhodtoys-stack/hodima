<?php
/**
 * ماژول «دستار سئو» — صفحه پیشخوان
 * Path: core/advisor/advisor-admin.php
 *
 * «ابزارهای هدیما ← دستار سئو» (admin.php?page=hodima-advisor). نوع صفحه «فهرست و جدول»
 * با کالبد ثابت کیت SEO (inc/ui-skeleton.php): سربرگ ← ۴ کارت آمار ← یک کنش اصلی
 * («گرفتن داده تازه» با انتخاب بازه) ← کارت فرصت‌ها (نوار ابزار یک‌ردیفه: نماها، جستجو،
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
		add_action( 'load-' . $hook, 'hodima_seo_advisor_legacy_tab_redirect' );
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
	// «بررسی لینک‌ها» زیر هر گروه رقابت (advisor-links.php)
	wp_add_inline_script( 'hodima-seo-advisor', 'window.hodimaAdvisorLinks = ' . wp_json_encode( [ 'ajax' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'hodima_advisor_links' ) ] ) . ';', 'before' );
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
		set_time_limit( 180 ); // سایت بزرگ: سه درخواست ۲۵ هزار ردیفی
	}

	// دستی: دستگاه/کشور و روند بدون فاصله ۱۲ ساعته؛ بعد از ذخیره داده فقط چند ثانیه نوبت‌ها، بقیه در پس‌زمینه (صفحه منتظر نماند)
	$result = hodima_seo_advisor_refresh( true, (float) HODIMA_SEO_ADVISOR_MANUAL_BUDGET );

	if ( is_wp_error( $result ) ) {
		hodima_admin_flash( '<strong>دستار سئو:</strong> ' . esc_html( $result->get_error_message() ), 'error' );
	} else {
		$extra   = hodima_seo_advisor_refresh_extras();
		$status  = hodima_seo_advisor_status();
		$pending = hodima_seo_advisor_pipeline_pending();
		hodima_admin_flash(
			// تا پیشنهادها در پس‌زمینه ساخته نشده‌اند، شمار «پیشنهاددار» هنوز عدد دریافت قبلی است و گفته نمی‌شود
			( $pending
				? sprintf(
					'<strong>دستار سئو:</strong> داده %1$s روز اخیر گرفته شد (%2$s آدرس). %3$s',
					esc_html( number_format_i18n( $status['days'] ) ),
					esc_html( number_format_i18n( $status['pages'] ) ),
					hodima_seo_advisor_cron_stalled()
						? 'ساخت پیشنهادها و بقیه تحلیل‌ها هنوز کامل نشده و کرون سایت اجرا نمی‌کند؛ در تب «تنظیمات» کارت «کارهای خودکار» را باز کنید و «اجرا همین حالا» را بزنید.'
						: 'ساخت پیشنهادها و بقیه تحلیل‌ها در پس‌زمینه ادامه دارد؛ چند دقیقه بعد صفحه را تازه کنید.'
				)
				: sprintf( '<strong>دستار سئو:</strong> داده %1$s روز اخیر گرفته شد؛ %2$s آدرس بررسی و برای %3$s آدرس پیشنهاد ساخته شد.', esc_html( number_format_i18n( $status['days'] ) ), esc_html( number_format_i18n( $status['pages'] ) ), esc_html( number_format_i18n( $status['advised'] ) ) )
			) . ( $extra ? ' ' . esc_html( implode( ' ', $extra ) ) : '' ),
			'success'
		);
	}

	$tab = isset( $_POST['tab'] ) ? sanitize_key( (string) $_POST['tab'] ) : '';
	wp_safe_redirect( hodima_seo_advisor_page_url( [ 'tab' => in_array( $tab, hodima_seo_advisor_tab_keys(), true ) && 'opportunities' !== $tab ? $tab : '' ] ) );
	exit;
}

/**
 * بقیه بخش‌ها بعد از دریافت دستی (دکمه «گرفتن داده تازه»): بررسی ایندکس یک دسته در پس‌زمینه و خواندن فایل‌های
 * خزش اگر از آخرین خواندن تازه‌تر و آماده‌اند. جمله‌های کوتاه نتیجه برای پیام.
 *
 * @return list<string>
 */
function hodima_seo_advisor_refresh_extras(): array {

	$out = [];

	if ( function_exists( 'hodima_seo_advisor_inspect_pending' ) && (bool) apply_filters( 'hodima_seo_advisor_inspect_enabled', true ) && hodima_seo_advisor_inspect_pending() > 0 ) {
		$next = (int) wp_next_scheduled( HODIMA_SEO_ADVISOR_INSPECT_CRON );

		if ( $next > 0 && $next < time() - 5 * MINUTE_IN_SECONDS ) {
			// رویداد بررسی ایندکس از موعدش گذشته = کرون سایت اجرا نمی‌کند؛ «شروع شد» نگوییم و چند آدرس را همین‌جا بررسی کنیم (۳.۲.۶)
			$summary = hodima_seo_advisor_inspect_batch( 5 );
			$out[]   = '' === $summary['error']
				? sprintf( 'کرون سایت اجرا نمی‌کند؛ %s آدرس همین‌جا از نظر ایندکس بررسی شد (بقیه را در تب «تنظیمات» با «اجرا همین حالا» ادامه دهید).', number_format_i18n( $summary['checked'] ) )
				: sprintf( 'کرون سایت اجرا نمی‌کند و بررسی ایندکس هم نشد: %s', $summary['error'] );
		} else {
			if ( ! $next || $next > time() + 60 ) {
				wp_schedule_single_event( time() + 30, HODIMA_SEO_ADVISOR_INSPECT_CRON );
			}
			$out[] = 'بررسی ایندکس یک دسته از آدرس‌ها در پس‌زمینه شروع شد.';
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

	// پیشنهادها از داده ذخیره‌شده ساخته می‌شوند؛ بدون درخواست تازه به گوگل با آستانه جدید دوباره ساخته شوند: چند ثانیه همین‌جا
	// و بقیه در پس‌زمینه (تا SEO 2.9.8 همه آدرس‌ها بدون سقف زمان در همین درخواست؛ سایت بزرگ به سقف زمان PHP می‌خورد)
	$message = 'تنظیم ذخیره شد.';
	if ( hodima_seo_advisor_totals()['pages'] > 0 ) {
		hodima_seo_advisor_request_reanalyze();
		if ( hodima_seo_advisor_lock() ) {
			try {
				hodima_seo_advisor_pipeline_run( (float) HODIMA_SEO_ADVISOR_MANUAL_BUDGET );
			} finally {
				hodima_seo_advisor_unlock();
			}
		}
		$message = hodima_seo_advisor_pipeline_pending()
			? 'تنظیم ذخیره شد؛ پیشنهادها در پس‌زمینه با آستانه تازه دوباره ساخته می‌شوند. چند دقیقه بعد صفحه را تازه کنید.'
			: sprintf( 'تنظیم ذخیره شد؛ پیشنهادها دوباره ساخته شد (%s آدرس پیشنهاد دارد).', esc_html( number_format_i18n( hodima_seo_advisor_advised_count() ) ) );
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
		$message = [ sprintf( 'کلید مخصوص دستار سئو ذخیره شد (%s). این ایمیل را در Search Console با دسترسی «محدود» اضافه کنید، بعد «گرفتن داده تازه» را بزنید.', esc_html( $check['email'] ) ), 'success' ];
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
			hodima_admin_flash( '<strong>دستار سئو:</strong> داده‌های مشاور پاک شد. برای دریافت دوباره، «گرفتن داده تازه» را بزنید.', 'success' );
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
			hodima_seo_advisor_csv_cell( rawurldecode( hodima_seo_advisor_url( $row['path'] ) ) ), // آدرس کامل قابل‌کلیک در اکسل، نه فقط مسیر
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
	return [ 'opportunities', 'overview', 'competition', 'terms', 'health', 'crawl', 'indexing', 'tasks', 'settings' ];
}

/**
 * تب‌های برداشته‌شده (تا SEO 3.1.2) ← تب تازه و پارامتر پیش‌فرضش؛ پیوند و علامت‌گذاری مرورگر خراب نشود.
 * «انجام‌شده» به «کارها» با فیلتر «منتظر نتیجه»؛ «موبایل و کشور» و «روند ماه‌به‌ماه» به «آمار جستجو».
 */
const HODIMA_SEO_ADVISOR_LEGACY_TABS = [
	'done'      => [ 'tasks', [ 'tstatus' => 'waiting' ] ],
	'breakdown' => [ 'terms', [] ],
	'trend'     => [ 'terms', [] ],
];

/** ریدایرکت پیوند تب قدیمی (پیش از خروجی صفحه). */
function hodima_seo_advisor_legacy_tab_redirect(): void {

	$tab = isset( $_GET['tab'] ) ? sanitize_key( (string) $_GET['tab'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط تب نمایش

	if ( ! isset( HODIMA_SEO_ADVISOR_LEGACY_TABS[ $tab ] ) ) {
		return;
	}

	[ $new, $extra ] = HODIMA_SEO_ADVISOR_LEGACY_TABS[ $tab ];

	foreach ( [ 'tstatus', 'tsource', 'paged' ] as $key ) {
		if ( isset( $_GET[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$extra[ $key ] = sanitize_key( (string) $_GET[ $key ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
	}

	wp_safe_redirect( hodima_seo_advisor_page_url( [ 'tab' => $new ] + $extra ) );
	exit;
}

/** تب‌های بالای صفحه: «کارها»، «کارهای انجام‌شده» (SEO 3.3.4) و «تنظیمات». */
function hodima_seo_advisor_render_tabs( string $active, bool $with_tabs = true ): void {

	// دو تب بیشتر نیست (SEO 3.3.0): «کارها» (همه ایرادها در یک فهرست) و «تنظیمات»؛ نماهای قدیمی با ?tab= هنوز باز می‌شوند
	unset( $with_tabs );
	$tabs = [
		'overview' => [ 'کارها', hodima_seo_advisor_page_url() ],
		'tasks'    => [ 'کارهای انجام‌شده', hodima_seo_advisor_page_url( [ 'tab' => 'tasks' ] ) ],
		'settings' => [ 'تنظیمات', hodima_seo_advisor_page_url( [ 'tab' => 'settings' ] ) ],
	];
	$icons = [ 'overview' => 'dashicons-list-view', 'tasks' => 'dashicons-yes-alt', 'settings' => 'dashicons-admin-generic' ];
	$open  = function_exists( 'hodima_seo_advisor_task_counts' ) ? (int) ( hodima_seo_advisor_task_counts()['open'] ?? 0 ) : 0; // کار انجام‌شده‌ای که برگشته یا بی‌اثر است؛ صفر = شمار نشان داده نمی‌شود

	// تب‌های زیرخط‌دار (نه دکمه پر) تا با چیپ‌های فیلتر اشتباه نشوند؛ در «کارها» خط «گرفتن داده تازه» همین ردیف است (SEO 3.3.1)
	echo '<div class="hodima-advisor__tabrow">';
	echo '<nav class="hodima-advisor__tabs" aria-label="بخش‌های دستار سئو">';
	foreach ( $tabs as $key => [ $label, $url ] ) {
		// تب «کارها» برای تب‌های قدیمی هم فعال می‌ماند (آدرس‌های ?tab= هنوز باز می‌شوند)
		$current = 'overview' === $key ? ! in_array( $active, [ 'tasks', 'settings' ], true ) : $key === $active;
		printf(
			'<a class="hodima-advisor__tab" href="%1$s"%2$s><span class="dashicons %3$s" aria-hidden="true"></span>%4$s%5$s</a>',
			esc_url( $url ),
			$current ? ' aria-current="page"' : '',
			esc_attr( $icons[ $key ] ),
			esc_html( $label ),
			'tasks' === $key && $open > 0 ? sprintf( ' <span class="hodima-advisor__tab-n" title="کار انجام‌شده‌ای که برگشته یا بی‌اثر بوده">%s</span>', esc_html( number_format_i18n( $open ) ) ) : ''
		);
	}
	echo '</nav>';
	if ( 'overview' === $active ) {
		hodima_seo_advisor_render_refresh_strip();
	}
	echo '</div>';

	if ( ! in_array( $active, [ 'overview', 'tasks', 'settings' ], true ) ) {
		printf( '<p class="hodima-advisor__back"><a href="%1$s">برگشت به فهرست کارها</a></p>', esc_url( hodima_seo_advisor_page_url() ) );
	}
}

/**
 * منوی کشویی فیلتر به‌جای ردیف چیپ (SEO 3.2.8): هر گزینه آدرس خودش را دارد و با انتخاب می‌رود؛ بدون جاوااسکریپت
 * همان چیپ‌های قبلی (پیوند ساده) داخل noscript می‌آیند.
 *
 * @param string                                      $label   برچسب منو (مثلا «نوع ایراد»)
 * @param array<string, array{0:string,1:int,2:string}> $items   کلید ← [متن، شمار، آدرس]
 * @param string                                      $current کلید گزینه فعلی
 */
function hodima_seo_advisor_filter_select( string $label, array $items, string $current ): void {
	?>
	<label class="hodima-advisor__filter">
		<span class="screen-reader-text"><?php echo esc_html( $label ); ?></span>
		<select class="hds-input" aria-label="<?php echo esc_attr( $label ); ?>" onchange="window.location.href=this.value">
			<?php foreach ( $items as $key => [ $text, $count, $url ] ) : ?>
				<option value="<?php echo esc_url( $url ); ?>"<?php selected( (string) $key, $current ); ?>><?php echo esc_html( sprintf( '%1$s (%2$s)', $text, number_format_i18n( $count ) ) ); ?></option>
			<?php endforeach; ?>
		</select>
	</label>
	<noscript>
		<nav class="hodima-advisor__views" aria-label="<?php echo esc_attr( $label ); ?>">
			<?php foreach ( $items as $key => [ $text, $count, $url ] ) : ?>
				<a class="hodima-advisor__view" href="<?php echo esc_url( $url ); ?>"<?php echo (string) $key === $current ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $text ); ?> <span class="hodima-advisor__count"><?php echo esc_html( number_format_i18n( $count ) ); ?></span></a>
			<?php endforeach; ?>
		</nav>
	</noscript>
	<?php
}

/**
 * ردیف دکمه‌های فیلتر به‌جای منوی کشویی (SEO 3.3.1): هر نوع یک پیوند با شمار؛ بدون جاوااسکریپت هم کار می‌کند.
 * فقط صفحه «کارها» از آن استفاده می‌کند؛ فیلترهای تب‌های قدیمی هنوز منو دارند (hodima_seo_advisor_filter_select).
 *
 * @param string                                      $label   برچسب خوانشگر صفحه (مثلا «نوع ایراد»)
 * @param array<string, array{0:string,1:int,2:string}> $items   کلید ← [متن، شمار، آدرس]
 * @param string                                      $current کلید گزینه فعلی
 * @param array{small?: bool, caption?: string}       $opts    small = دکمه‌های کوچک‌تر (ردیف دوم)؛ caption = برچسب دیدنی اول ردیف
 */
function hodima_seo_advisor_filter_chips( string $label, array $items, string $current, array $opts = [] ): void {
	$small   = ! empty( $opts['small'] );
	$caption = (string) ( $opts['caption'] ?? '' );
	?>
	<nav class="hodima-advisor__chips<?php echo $small ? ' hodima-advisor__chips--sm' : ''; ?>" aria-label="<?php echo esc_attr( $label ); ?>">
		<?php if ( '' !== $caption ) : ?>
			<span class="hodima-advisor__chips-caption" aria-hidden="true"><?php echo esc_html( $caption ); ?></span>
		<?php endif; ?>
		<?php foreach ( $items as $key => [ $text, $count, $url ] ) : ?>
			<a class="hodima-advisor__chip" href="<?php echo esc_url( $url ); ?>"<?php echo (string) $key === $current ? ' aria-current="true"' : ''; ?>>
				<span><?php echo esc_html( $text ); ?></span>
				<span class="hodima-advisor__chip-n"><?php echo esc_html( number_format_i18n( $count ) ); ?></span>
			</a>
		<?php endforeach; ?>
	</nav>
	<?php
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
	$tab = HODIMA_SEO_ADVISOR_LEGACY_TABS[ $tab ][0] ?? $tab; // پیوند قدیمی بدون ریدایرکت (فراخوانی مستقیم)؛ در پیشخوان ریدایرکت پیش‌تر انجام شده
	$tab = '' === $tab ? 'overview' : $tab; // صفحه شروع: فهرست واحد «کارها» (SEO 3.3.0)
	$tab = ( $has_data || 'settings' === $tab ) && in_array( $tab, hodima_seo_advisor_tab_keys(), true ) ? $tab : ( $has_data ? 'overview' : 'opportunities' );
	$tab = $has_key ? $tab : 'settings'; // بدون کلید اولین کار وارد کردن کلید است

	hodima_seo_ui_open( [
		'title'       => 'دستار سئو',
		'description' => 'با داده Search Console همین سایت می‌گوید هر صفحه چه کاری بیشترین کلیک اضافه را می‌آورد. فقط پیشنهاد می‌دهد؛ چیزی را خودکار عوض نمی‌کند.',
		'icon'        => 'dashicons-chart-line',
		'class'       => 'hodima-advisor',
	] );

	if ( '' !== $status['error'] ) {
		wp_admin_notice( '<strong>آخرین دریافت داده ناموفق بود:</strong> ' . esc_html( $status['error'] ), [ 'type' => 'error', 'additional_classes' => [ 'inline' ] ] ); // بدون وابستگی به Core
	}

	hodima_seo_advisor_cron_notice( $tab ); // فقط وقتی کرون عقب است (تب‌های پیشنهادها و ایرادهای فنی)

	hodima_seo_ui_body_open();

	$n     = static fn( int|float $value ): string => number_format_i18n( $value );
	$empty = ! $has_data;

	// یک ردیف «گرفتن داده تازه» بالای تب‌ها برای کل صفحه (SEO 2.9.3؛ قبلا در هر تب تکرار می‌شد)
	hodima_seo_advisor_render_tabs( $tab, $has_data );
	if ( ! in_array( $tab, [ 'overview', 'tasks', 'settings' ], true ) ) {
		hodima_seo_advisor_render_toolbar( $tab );
	}

	if ( 'settings' === $tab ) {
		if ( $has_key ) {
			hodima_seo_advisor_render_toolbar( 'settings' ); // بازه داده، گرفتن داده تازه و کیفیت داده فقط اینجاست (SEO 3.3.0)
			hodima_seo_advisor_render_cron_card(); // بدون کلید اول کار وارد کردن کلید است؛ کارت بعد از آن می‌آید
		}
		hodima_seo_advisor_render_settings( $has_data );
		if ( ! $has_key ) {
			hodima_seo_advisor_render_cron_card();
		}
		hodima_seo_ui_close();
		return;
	}

	if ( 'opportunities' !== $tab ) {
		match ( $tab ) {
			'terms'     => hodima_seo_advisor_render_terms( $status ),
			'health'    => hodima_seo_advisor_render_health( $status ),
			'crawl'     => hodima_seo_advisor_render_crawl(),
			'overview'  => hodima_seo_advisor_render_overview(),
			'indexing'  => hodima_seo_advisor_render_indexing(),
			'tasks'     => hodima_seo_advisor_render_tasks(),
			default     => hodima_seo_advisor_render_competition( $status ),
		};
		if ( 'competition' === $tab ) {
			hodima_seo_advisor_render_variants();
		}
		hodima_seo_advisor_render_glossary();
		hodima_seo_ui_close();
		return;
	}

	$handled_n = $empty || ! function_exists( 'hodima_seo_advisor_handled_paths' ) ? 0 : count( hodima_seo_advisor_handled_paths() );

	hodima_seo_ui_stats( [
		// فقط صفحه‌هایی که کار باز دارند؛ تمام‌علامت‌خورده‌ها جدا گفته می‌شوند (همان فهرست پیش‌فرض)
		[ 'label' => 'صفحه با فرصت', 'value' => $empty ? '—' : esc_html( $n( max( 0, $totals['advised'] - $handled_n ) ) ), 'sub' => $handled_n > 0 ? esc_html( sprintf( '+ %s صفحه با همه کارها علامت‌خورده', $n( $handled_n ) ) ) : '', 'empty' => $empty ],
		[ 'label' => 'نمایش در گوگل', 'value' => $empty ? '—' : esc_html( $n( $totals['impressions'] ) ), 'sub' => $empty ? '' : esc_html( sprintf( '%s روز اخیر', $n( $status['days'] ) ) ), 'empty' => $empty ],
		[ 'label' => 'کلیک', 'value' => $empty ? '—' : esc_html( $n( $totals['clicks'] ) ), 'sub' => $empty ? '' : esc_html( sprintf( '%s روز اخیر', $n( $status['days'] ) ) ), 'empty' => $empty ],
		[ 'label' => 'میانگین رتبه', 'value' => $empty ? '—' : esc_html( number_format_i18n( $totals['position'], 1 ) ), 'sub' => $empty ? '' : 'عدد کمتر بهتر است', 'empty' => $empty ],
	] );

	if ( ! $has_key ) {
		hodima_seo_advisor_render_no_key();
	} elseif ( ! $has_data ) {
		hodima_seo_ui_card_open( 'هنوز داده‌ای نیست' );
		hodima_seo_ui_empty( 'اولین دریافت داده را بزنید', 'دکمه «گرفتن داده تازه» را بزنید؛ از این به بعد خودکار هر روز گرفته می‌شود. داده سرچ کنسول ۲ تا ۳ روز تاخیر دارد.' );
		hodima_seo_ui_card_close();
	} else {
		hodima_seo_advisor_render_list( $status );
	}

	hodima_seo_advisor_render_glossary();
	hodima_seo_ui_close();
}

/**
 * ردیف «گرفتن داده تازه»، یک بار بالای تب‌ها برای کل صفحه (جز تب تنظیمات): بازه + وضعیت اتصال + یک دکمه که داده
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
			<?php if ( hodima_seo_advisor_pipeline_pending() ) : ?>
				<span class="hds-pill hds-pill--info" role="status">ساخت پیشنهادها در پس‌زمینه ادامه دارد</span>
			<?php endif; ?>
			<button type="submit" class="hds-btn hodima-advisor__refresh"<?php disabled( ! $has_key ); ?>>
				<span class="dashicons dashicons-update" aria-hidden="true"></span> گرفتن داده تازه
			</button>
		</form>
		<p class="hodima-advisor__bar-help">یک کلیک برای همه بخش‌ها (آمار گوگل، ایندکس، خزش)؛ هر روز هم خودکار انجام می‌شود.</p>
		<?php hodima_seo_advisor_render_quality( $tab, true ); // نوار «کیفیت داده» داخل همین کارت (SEO 3.3.0) ?>
	</section>
	<?php
}

/**
 * محتوای تب «تنظیمات» (`tab=settings`): کلید حساب سرویس، حداقل نمایش، پاک کردن داده‌های دستار. بدون کلید صفحه
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
		<p class="hds-card__desc">اتصال به Search Console، آستانه پیشنهادها و پاک کردن داده‌های دستار.</p>

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
							<button type="submit" name="remove" value="1" class="hds-btn hds-btn--outline hodima-advisor__danger"><span class="dashicons dashicons-trash" aria-hidden="true"></span> برداشتن کلید</button>
						</form>
					<?php endif; ?>
				</span>
			<?php endif; ?>
		</div>
		<p class="hodima-advisor__setting-help">
			<?php if ( $fixed ) : ?>
				کلید از ثابت <bdi class="hds-code">HODIMA_SEO_ADVISOR_SERVICE_ACCOUNT_JSON</bdi> در wp-config.php خوانده می‌شود و از اینجا عوض نمی‌شود.
			<?php elseif ( $own ) : ?>
				این کلید فقط برای دستار سئو است. «برداشتن کلید» = برگشت به کلید مشترک (دیسکاور یا ایندکس گوگل).
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
				<p class="hodima-advisor__setting-help">جدول‌های دریافت‌شده از Search Console و فهرست «نادیده گرفتن» پاک می‌شوند. کلید حساب سرویس، تاریخچه هفتگی، فهرست «کارها» و تنظیمات می‌مانند و با «گرفتن داده تازه» دوباره گرفته می‌شود. بازگشت ندارد.</p>
				<label class="hodima-advisor__confirm">
					<input type="checkbox" name="confirm" value="1"<?php disabled( ! $has_data ); ?>>
					<span>مطمئنم؛ داده‌ها پاک شود</span>
				</label>
				<button type="submit" class="hds-btn hds-btn--outline hodima-advisor__danger"<?php disabled( ! $has_data ); ?>>پاک کردن داده‌های دستار</button>
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

	hodima_seo_ui_card_open( 'پیشنهادها', sprintf( 'صفحه‌هایی که با کمی کار کلیک بیشتری از گوگل می‌گیرند (داده %s روز اخیر). «بررسی و رفع» چرا و چطور رفع کردن هر مورد را در ویرایش صفحه نشان می‌دهد.', number_format_i18n( $status['days'] ) ) );
	?>
	<form method="get" class="hodima-advisor__toolbar">
		<input type="hidden" name="page" value="<?php echo esc_attr( HODIMA_SEO_ADVISOR_PAGE ); ?>">
		<?php if ( 'all' !== $view ) : ?>
			<input type="hidden" name="view" value="<?php echo esc_attr( $view ); ?>">
		<?php endif; ?>
		<?php if ( '' !== $filters['tasks'] ) : ?>
			<input type="hidden" name="tasks" value="<?php echo esc_attr( $filters['tasks'] ); ?>">
		<?php endif; ?>
		<?php
		$view_items = [];
		foreach ( $views as $key => [ $label, $count ] ) {
			$view_items[ $key ] = [ $label, $count, hodima_seo_advisor_page_url( [ 'view' => 'all' === $key ? '' : $key, 'q' => $filters['q'], 'rule' => $filters['rule'], 'min' => $filters['min'], 'tasks' => $filters['tasks'] ] ) ];
		}
		hodima_seo_advisor_filter_select( 'نوع صفحه', $view_items, $view );
		?>
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
					'همه ایرادهای %1$s صفحه را «انجام دادم» یا «لازم نیست» علامت زده‌اید. %2$s',
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
			<span>صفحه</span><span>نمایش</span><span>نرخ کلیک</span><span>رتبه</span><span>کلیک بالقوه</span><span></span>
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
 * یک ردیف فهرست (SEO 3.1.1، طرح تأییدشده کاربر ۸ آبان ۱۴۰۵): یک ردیف فشرده (صفحه، عددها، کلیک بالقوه، «بررسی و رفع» و منوی «⋯»)
 * و زیرش یک خط خلاصه پیشنهادها که با «جزئیات» باز می‌شود (تیک هر مورد و دکمه‌های گروهی فقط آنجا). قبلا هر ایراد با تیکش در ستون
 * باریک می‌شکست و ردیف ≈ ۴۳۰px بود. گام‌های رفع در کادر «دستار سئو» صفحه ویرایش همان آدرس است؛ «بررسی و رفع» به آنجا می‌برد.
 * بدون JS هم کار می‌کند (details بومی)؛ دکمه‌های منو با ویژگی form به فرم‌های بی‌دکمه ردیف وصل‌اند.
 *
 * @param array<string, mixed> $row
 */
function hodima_seo_advisor_render_row( array $row, int $max, bool $ignored_view ): void {

	static $ignore_nonce = null;
	$ignore_nonce ??= wp_create_nonce( 'hodima_advisor_ignore' );

	$impressions = (int) $row['impressions'];
	$title       = hodima_seo_advisor_row_title( $row );
	$ctr         = $impressions > 0 ? $row['clicks'] / $impressions * 100 : 0.0;
	$expected    = hodima_seo_advisor_expected_ctr( (float) $row['position'] );
	$advice      = array_values( (array) $row['advice'] );
	$edit        = $row['object_id'] > 0 ? hodima_seo_advisor_edit_link( (string) $row['kind'], (int) $row['object_id'] ) : '';
	$site        = hodima_seo_advisor_url( (string) $row['path'] );
	$form        = hodima_seo_advisor_mark_id();
	$ignore_form = $form . '-ignore';
	$context     = '' !== $title ? $title : rawurldecode( (string) $row['path'] );
	$open        = 0; // ایرادهای بی‌علامت یا هنوز باز: فقط با آن‌ها «انجام دادم / لازم نیست» معنا دارد

	// فهرست ایرادها (در بخش جزئیات)؛ شمار بازها همین‌جا معلوم می‌شود تا دکمه‌های منو درست ساخته شوند
	$items = '';
	foreach ( $advice as $item ) {
		$task   = hodima_seo_advisor_task( (string) $row['path'], (string) $item['rule'], (string) $item['term'] );
		$closed = null !== $task && in_array( hodima_seo_advisor_task_state( $task ), HODIMA_SEO_ADVISOR_TASKS_CLOSED, true );
		$text   = '<span class="hodima-advisor__issue-text">' . esc_html( (string) $item['title'] ) . '</span>' . ( null !== $task ? ' ' . hodima_seo_advisor_task_badge( $task ) : '' );
		if ( $closed || $ignored_view ) {
			$inner = '<span class="hodima-advisor__mark-text">' . $text . '</span>';
		} else {
			++$open;
			$inner = hodima_seo_advisor_mark_check( $form, (string) $item['rule'] . '|' . (string) $item['term'], $text );
		}
		$items .= '<li data-rule="' . esc_attr( (string) $item['rule'] ) . '"' . ( null !== $task ? ' data-task="' . esc_attr( hodima_seo_advisor_task_state( $task ) ) . '"' : '' ) . '>' . $inner . '</li>';
	}
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
			<span class="hodima-advisor__potential">
				<?php if ( $row['potential'] > 0 ) : ?>
					<strong>≈ <?php echo esc_html( number_format_i18n( (int) $row['potential'] ) ); ?></strong>
					<span class="hodima-advisor__bar-track" aria-hidden="true"><span style="inline-size: <?php echo esc_attr( (string) max( 4, (int) round( $row['potential'] / $max * 100 ) ) ); ?>%"></span></span>
				<?php else : ?>
					<small>—</small>
				<?php endif; ?>
			</span>
			<span class="hodima-advisor__actions">
				<?php if ( $ignored_view ) : ?>
					<button type="submit" form="<?php echo esc_attr( $ignore_form ); ?>" class="hds-btn hds-btn--outline hodima-advisor__act"><span class="dashicons dashicons-undo" aria-hidden="true"></span>برگردان به فهرست</button>
				<?php else : ?>
					<?php if ( '' !== $edit ) : ?>
						<a class="hds-btn hodima-advisor__primary" href="<?php echo esc_url( $edit ); ?>">بررسی و رفع</a>
					<?php else : ?>
						<a class="hds-btn hds-btn--outline hodima-advisor__primary" href="<?php echo esc_url( $site ); ?>" target="_blank" rel="noopener"><span class="dashicons dashicons-external" aria-hidden="true"></span> دیدن در سایت</a>
					<?php endif; ?>
					<details class="hodima-advisor__menu">
						<summary class="hds-btn hds-btn--outline hodima-advisor__menu-btn" aria-label="<?php echo esc_attr( 'کارهای بیشتر برای ' . $context ); ?>"><span class="dashicons dashicons-ellipsis" aria-hidden="true"></span></summary>
						<div class="hodima-advisor__menu-list">
							<?php if ( '' !== $edit ) : ?>
								<a class="hodima-advisor__menu-item" href="<?php echo esc_url( $site ); ?>" target="_blank" rel="noopener">دیدن در سایت</a>
							<?php endif; ?>
							<?php if ( $open > 0 ) : ?>
								<button type="submit" form="<?php echo esc_attr( $form ); ?>" name="status" value="done" class="hodima-advisor__menu-item hodima-advisor__menu-item--ok">همه را انجام دادم</button>
								<button type="submit" form="<?php echo esc_attr( $form ); ?>" name="status" value="rejected" class="hodima-advisor__menu-item">لازم نیست</button>
							<?php endif; ?>
							<button type="submit" form="<?php echo esc_attr( $ignore_form ); ?>" class="hodima-advisor__menu-item hodima-advisor__menu-item--muted">این صفحه را نادیده بگیر</button>
						</div>
					</details>
				<?php endif; ?>
			</span>
		</div>

		<?php if ( $advice ) : ?>
			<details class="hodima-advisor__more">
				<summary class="hodima-advisor__more-sum">
					<span class="hds-pill hds-pill--warn"><?php echo esc_html( number_format_i18n( count( $advice ) ) ); ?> پیشنهاد</span>
					<span class="hodima-advisor__more-first"><?php echo esc_html( (string) $advice[0]['title'] ); ?></span>
					<span class="hodima-advisor__more-toggle"><span class="hodima-advisor__more-show">جزئیات</span><span class="hodima-advisor__more-hide">بستن</span></span>
				</summary>
				<div class="hodima-advisor__more-body">
					<ul class="hodima-advisor__issues">
						<?php echo $items; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- اجزا escape شدند ?>
					</ul>
					<?php if ( $open > 0 ) : ?>
						<div class="hodima-advisor__more-acts">
							<button type="submit" form="<?php echo esc_attr( $form ); ?>" name="status" value="done" class="hds-btn hds-btn--outline hodima-advisor__mark-done" aria-label="<?php echo esc_attr( 'انجام دادم: ' . $context ); ?>"><span class="dashicons dashicons-yes" aria-hidden="true"></span>تیک‌خورده‌ها را انجام دادم</button>
							<button type="submit" form="<?php echo esc_attr( $form ); ?>" name="status" value="rejected" class="hds-btn hds-btn--outline hodima-advisor__mark-reject" aria-label="<?php echo esc_attr( 'لازم نیست: ' . $context ); ?>">لازم نیست</button>
							<span class="hodima-advisor__muted">تیک هر موردی را که انجام نمی‌دهید بردارید.</span>
						</div>
					<?php endif; ?>
				</div>
			</details>
		<?php else : ?>
			<p class="hodima-advisor__more-none"><small>نادیده گرفته شده</small></p>
		<?php endif; ?>

		<?php
		if ( $open > 0 && ! $ignored_view ) {
			hodima_seo_advisor_mark_form( $form, 'opportunities', (string) $row['path'], $context, [], false );
		}
		?>
		<form id="<?php echo esc_attr( $ignore_form ); ?>" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="hodima-advisor__ignore" hidden>
			<input type="hidden" name="action" value="hodima_advisor_ignore">
			<input type="hidden" name="path" value="<?php echo esc_attr( (string) $row['path'] ); ?>">
			<?php if ( $ignored_view ) : ?>
				<input type="hidden" name="restore" value="1">
			<?php endif; ?>
			<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $ignore_nonce ); ?>">
		</form>
	</div>
	<?php
}
