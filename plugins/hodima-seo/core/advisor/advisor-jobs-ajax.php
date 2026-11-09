<?php
/**
 * ماژول «دستار سئو» — ایجکس «اجرا همین حالا» و وضعیت زنده هر کار در تب «خزش و داده» (SEO 3.4.8)
 * Path: core/advisor/advisor-jobs-ajax.php
 *
 * طرح تأییدشده کاربر (۱۲ آبان ۱۴۰۵؛ Artifact «طرح تازه تب خزش و داده»): دکمه هر ردیف بدون بارگذاری صفحه کار را اجرا می‌کند و
 * نوار پیشرفتش زنده پر می‌شود. دو درخواست هم‌زمان: «اجرا» (تا پایان کار باز می‌ماند) و «وضعیت» (هر ۱٫۵ ثانیه، فقط می‌خواند و
 * عددهای advisor-progress.php را برمی‌گرداند). منطق کار همان کنش‌های فرم‌های admin-post است (hodima_seo_advisor_do_*)؛ دوتا
 * منطق نیست. خزنده با گام ایجکس خودش (hodima_advisor_crawler_step، advisor-crawl-view.php) پیش می‌رود.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

add_action( 'wp_ajax_hodima_advisor_job_run', 'hodima_seo_advisor_ajax_job_run' );
add_action( 'wp_ajax_hodima_advisor_job_status', 'hodima_seo_advisor_ajax_job_status' );

/** کارهایی که این ایجکس اجرا می‌کند ← تابع کنش. */
const HODIMA_SEO_ADVISOR_AJAX_JOBS = [ 'refresh', 'crawler', 'inspect', 'pipeline' ];

/**
 * وضعیت ردیف یک کار (متن‌های «وضعیت اجرا / اجرای بعدی» و نشان) برای به‌روز شدن ردیف بعد از پایان کار.
 *
 * @return array{last: string, expect: string, level: string, pill: string, pill_class: string}
 */
function hodima_seo_advisor_job_facts( string $job ): array {

	$pills = hodima_seo_advisor_cron_pills();

	foreach ( hodima_seo_advisor_cron_health()['jobs'] as $row ) {
		if ( $row['key'] === $job ) {
			$pill = $pills[ $row['level'] ] ?? $pills['ok'];
			return [ 'last' => hodima_seo_advisor_fa_digits( $row['last'] ), 'expect' => hodima_seo_advisor_fa_digits( $row['expect'] ), 'level' => $row['level'], 'pill' => $pill[0], 'pill_class' => $pill[1] ];
		}
	}

	return [ 'last' => '', 'expect' => '', 'level' => 'ok', 'pill' => $pills['ok'][0], 'pill_class' => $pills['ok'][1] ];
}

/** اجرای یک کار (تا پایان باز می‌ماند؛ صفحه هم‌زمان «وضعیت» را می‌پرسد). */
function hodima_seo_advisor_ajax_job_run(): void {

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( [ 'message' => 'دسترسی ندارید' ], 403 );
	}

	check_ajax_referer( 'hodima_advisor_job' );

	$job = isset( $_POST['job'] ) ? sanitize_key( wp_unslash( (string) $_POST['job'] ) ) : '';

	if ( ! in_array( $job, HODIMA_SEO_ADVISOR_AJAX_JOBS, true ) ) {
		wp_send_json_error( [ 'message' => 'کار نامعتبر است.' ], 400 );
	}

	ignore_user_abort( true ); // بستن صفحه وسط کار، کار را نیمه‌کاره نمی‌گذارد
	hodima_seo_advisor_ajax_release_session();

	switch ( $job ) {
		case 'refresh':
			if ( '' === hodima_seo_sc_key_json( HODIMA_SEO_SC_ADVISOR ) ) {
				[ $type, $html ] = [ 'error', '<strong>دستار سئو:</strong> کلید Search Console ثبت نشده؛ اول در «تنظیمات» کلید را بگذارید.' ];
				break;
			}
			hodima_seo_advisor_progress_set( 'refresh', 1, 1, 'در حال شروع', 0 );
			[ $type, $html ] = hodima_seo_advisor_do_refresh( isset( $_POST['days'] ) ? (int) $_POST['days'] : 0 );
			break;
		case 'inspect':
			hodima_seo_advisor_progress_set( 'inspect', 1, 1, 'در حال شروع', 0 );
			[ $type, $html ] = hodima_seo_advisor_do_inspect();
			break;
		case 'pipeline':
			hodima_seo_advisor_progress_set( 'pipeline', 1, 1, 'در حال شروع', 0 );
			[ $type, $html ] = hodima_seo_advisor_do_pipeline();
			break;
		default: // crawler
			hodima_seo_advisor_crawler_start_now();
			[ $type, $html ] = [ 'success', '<strong>خزش خودکار:</strong> خزش همین حالا شروع شد.' ];
	}

	wp_send_json_success( [ 'type' => $type, 'html' => hodima_seo_advisor_fa_digits_html( wp_kses_post( $html ) ), 'facts' => hodima_seo_advisor_job_facts( $job ), 'progress' => hodima_seo_advisor_progress_get( $job ) ] );
}

/** وضعیت زندهٔ یک کار (فقط می‌خواند). */
function hodima_seo_advisor_ajax_job_status(): void {

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( [ 'message' => 'دسترسی ندارید' ], 403 );
	}

	check_ajax_referer( 'hodima_advisor_job' );

	$job = isset( $_POST['job'] ) ? sanitize_key( wp_unslash( (string) $_POST['job'] ) ) : '';

	if ( ! in_array( $job, HODIMA_SEO_ADVISOR_AJAX_JOBS, true ) ) {
		wp_send_json_error( [ 'message' => 'کار نامعتبر است.' ], 400 );
	}

	// پیشرفت تازه بدون کش شیء: اجرای هم‌زمان در درخواست دیگر آن را نوشته است
	wp_cache_delete( HODIMA_SEO_ADVISOR_PROGRESS_OPTION, 'options' );
	wp_cache_delete( 'alloptions', 'options' );

	wp_send_json_success( [ 'progress' => hodima_seo_advisor_progress_get( $job ), 'facts' => hodima_seo_advisor_job_facts( $job ) ] );
}

/** قفل نشست را باز می‌کند تا درخواست «وضعیت» پشت درخواست «اجرا» صف نشود (فقط وقتی نشست PHP باز است). */
function hodima_seo_advisor_ajax_release_session(): void {

	if ( function_exists( 'session_status' ) && PHP_SESSION_ACTIVE === session_status() ) {
		session_write_close();
	}
}
