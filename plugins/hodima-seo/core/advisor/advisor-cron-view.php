<?php
/**
 * ماژول «دستار سئو» — کنش‌ها و نوار هشدار سلامت کارهای خودکار (SEO 3.2.0؛ کارت‌ها از ۳.۳.۹ در advisor-jobs-view.php)
 * Path: core/advisor/advisor-cron-view.php
 *
 * طرح تأییدشده کاربر (۹ آبان ۱۴۰۵): کارت «کارهای خودکار به موقع اجرا می‌شوند؟» بالای تب «تنظیمات» (مشکل‌دارها اول
 * و کامل، سالم‌ها یک‌خطی؛ همه سالم = یک خط وضعیت و جزئیات جمع‌شده) و یک نوار هشدار فقط روی تب‌های «پیشنهادها» و
 * «ایرادهای فنی» و فقط وقتی کاری دیر شده یا متوقف است. حالت سالم ساکت است. محاسبه در advisor-cron.php.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** تب‌هایی که نوار هشدار دارند (عددشان مستقیم به تصمیم مدیر می‌رسد). */
const HODIMA_SEO_ADVISOR_CRON_NOTICE_TABS = [ 'opportunities', 'overview' ];

/**
 * برچسب و کلاس نشان هر حالت.
 *
 * @return array<string, array{0: string, 1: string}> [برچسب، کلاس نشان]
 */
function hodima_seo_advisor_cron_pills(): array {
	return [
		'ok'   => [ 'سالم', 'hds-pill' ],
		'warn' => [ 'دیر شده', 'hds-pill hds-pill--warn' ],
		'bad'  => [ 'متوقف', 'hds-pill hds-pill--error' ],
		'off'  => [ 'غیرفعال', 'hds-pill hds-pill--info' ],
	];
}

/**
 * تب بازگشت بعد از یک کنش فرم (فیلد پنهان tab): «خزش و داده»، «تنظیمات»، «کارها» یا «سلامت سایت»، وگرنه `$fallback`. nonce را خود
 * کنش‌ها بررسی کرده‌اند. (SEO 3.4.9: «کارها» و «سلامت سایت» هم می‌توانند خزش را شروع کنند و ادمین همان‌جا می‌ماند.)
 */
function hodima_seo_advisor_back_tab( string $fallback ): string {

	$tab = isset( $_POST['tab'] ) ? sanitize_key( wp_unslash( (string) $_POST['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce در کنش بررسی شده

	return in_array( $tab, [ 'jobs', 'settings', 'overview', 'crawl' ], true ) ? ( 'crawl' === $tab ? 'overview' : $tab ) : $fallback; // «crawl»: فرم‌های قدیمی؛ تب حذف شد (SEO 3.5.8)
}

add_action( 'admin_post_hodima_advisor_pipeline_now', 'hodima_seo_advisor_handle_pipeline_now' );

/**
 * «اجرا همین حالا» برای ساخت پیشنهادها (نوبت‌های پس‌زمینه): چند ثانیه نوبت‌ها را همین‌جا پیش می‌برد، بی‌نیاز از کرون.
 *
 * @return array{0: string, 1: string} [نوع پیام (success|error)، HTML پیام]
 */
function hodima_seo_advisor_do_pipeline(): array {

	if ( ! hodima_seo_advisor_pipeline_pending() ) {
		hodima_seo_advisor_progress_finish( 'pipeline', 'کاری برای ادامه نیست.' );
		return [ 'success', '<strong>ساخت پیشنهادها:</strong> کاری برای ادامه نیست.' ];
	}

	if ( ! hodima_seo_advisor_lock() ) {
		return [ 'error', '<strong>ساخت پیشنهادها:</strong> هم‌اکنون کار دیگری از دستار در حال اجراست؛ چند دقیقه بعد دوباره امتحان کنید.' ];
	}

	try {
		hodima_seo_advisor_pipeline_run( (float) HODIMA_SEO_ADVISOR_MANUAL_BUDGET );
	} finally {
		hodima_seo_advisor_unlock();
	}

	return [
		'success',
		hodima_seo_advisor_pipeline_pending()
			? '<strong>ساخت پیشنهادها:</strong> چند نوبت انجام شد و بقیه مانده؛ دوباره «اجرا همین حالا» را بزنید.'
			: '<strong>ساخت پیشنهادها:</strong> همه نوبت‌ها تمام شد.',
	];
}

function hodima_seo_advisor_handle_pipeline_now(): void {

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'اجازه این کار را ندارید.', 'دسترسی ندارید', [ 'response' => 403 ] );
	}

	check_admin_referer( 'hodima_advisor_pipeline_now' );

	[ $type, $html ] = hodima_seo_advisor_do_pipeline();
	hodima_admin_flash( $html, $type );

	wp_safe_redirect( hodima_seo_advisor_page_url( [ 'tab' => hodima_seo_advisor_back_tab( 'jobs' ) ] ) . '#hodima-advisor-cron' );
	exit;
}

/**
 * فرم «اجرا همین حالا» یک کار دیرشده: به همان کنش دستی خودش (دکمه تب خودش) می‌رود و به کارت برمی‌گردد.
 */
function hodima_seo_advisor_cron_run_form( string $key, bool $running = false ): string {

	$map = [
		'refresh'  => [ 'hodima_advisor_refresh', 'hodima_advisor_refresh' ],
		'inspect'  => [ 'hodima_advisor_inspect_now', 'hodima_advisor_inspect_now' ],
		'crawler'  => [ 'hodima_advisor_crawler_now', 'hodima_advisor_crawler_now' ],
		'pipeline' => [ 'hodima_advisor_pipeline_now', 'hodima_advisor_pipeline_now' ],
	];

	if ( ! isset( $map[ $key ] ) ) {
		return '';
	}

	return sprintf(
		'<form method="post" action="%1$s" class="hodima-advisor__cron-run" data-hdsa-job-form="%4$s"><input type="hidden" name="action" value="%2$s"><input type="hidden" name="_wpnonce" value="%3$s"><input type="hidden" name="tab" value="jobs"><button type="submit" class="hds-btn hds-btn--outline hodima-advisor__act"%5$s><span class="dashicons dashicons-controls-play" aria-hidden="true"></span> <span data-hdsa-run-label%6$s>%7$s</span></button></form>',
		esc_url( admin_url( 'admin-post.php' ) ),
		esc_attr( $map[ $key ][0] ),
		esc_attr( wp_create_nonce( $map[ $key ][1] ) ),
		esc_attr( $key ),
		$running ? ' disabled' : '',
		$running ? ' data-was="اجرا همین حالا"' : '',
		$running ? 'در حال اجرا…' : 'اجرا همین حالا'
	);
}

/**
 * نوار هشدار زیر سربرگ، فقط روی تب‌های HODIMA_SEO_ADVISOR_CRON_NOTICE_TABS و فقط وقتی کاری دیر شده یا متوقف است.
 */
function hodima_seo_advisor_cron_notice( string $tab ): void {

	if ( ! in_array( $tab, HODIMA_SEO_ADVISOR_CRON_NOTICE_TABS, true ) ) {
		return;
	}

	$health = hodima_seo_advisor_cron_health();

	if ( 'ok' === $health['level'] ) {
		return;
	}

	$late = [];
	foreach ( $health['jobs'] as $job ) {
		if ( 'warn' === $job['level'] || 'bad' === $job['level'] ) {
			$late[] = sprintf( '%1$s: %2$s', $job['label'], $job['last'] );
		}
	}

	wp_admin_notice(
		sprintf(
			'<strong>کارهای خودکار دستار عقب‌اند.</strong> %1$s؛ عددهای این صفحه ممکن است کهنه باشند. <a href="%2$s">دیدن راه‌حل</a>',
			esc_html( implode( '، ', $late ) ),
			esc_url( hodima_seo_advisor_page_url( [ 'tab' => 'jobs' ] ) . '#hodima-advisor-cron' )
		),
		[ 'type' => 'bad' === $health['level'] ? 'error' : 'warning', 'additional_classes' => [ 'inline' ] ] // اعلان خود وردپرس: صفحه به Core وابسته نشود
	);
}
