<?php
/**
 * ماژول «دستار سئو» — تب «ایرادهای فنی»
 * Path: core/advisor/advisor-crawl-view.php
 *
 * داده از advisor-crawl.php. کالبد کیت SEO: ۴ کارت آمار ← کارت «فایل‌های خزش» (یک کنش اصلی:
 * خواندن فایل‌های خزش) ← کارت «ایرادهای صفحه‌ها» (چیپ‌های فیلتر + جستجو + جدول مرتب با نمایش گوگل).
 * فقط می‌خواند و دستور می‌دهد؛ چیزی را در سایت عوض نمی‌کند. (طرح تأییدشده کاربر ۴ آبان ۱۴۰۵)
 * SEO 3.0.2: هر ایراد تیک و هر ردیف «انجام شد / رد شد» دارد (advisor-marks.php)؛ ایراد علامت‌خورده پنهان می‌شود.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

add_action( 'admin_post_hodima_advisor_crawler_now', 'hodima_seo_advisor_handle_crawler_now' );
add_action( 'admin_post_hodima_advisor_crawler_settings', 'hodima_seo_advisor_handle_crawler_settings' );
add_action( 'wp_ajax_hodima_advisor_crawler_step', 'hodima_seo_advisor_ajax_crawler_step' );

/** دکمه «خزش همین حالا»: دور تازه را بی‌توجه به ساعت شروع می‌کند (همان سرعت آهسته)؛ پیشرفت را صفحه ادامه می‌دهد. */
function hodima_seo_advisor_handle_crawler_now(): void {

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'اجازه این کار را ندارید.', 'دسترسی ندارید', [ 'response' => 403 ] );
	}

	check_admin_referer( 'hodima_advisor_crawler_now' );
	hodima_seo_advisor_crawler_start_now();
	hodima_admin_flash( '<strong>خزش خودکار:</strong> خزش همین حالا شروع شد؛ صفحه را باز نگه دارید تا پیشرفت را ببینید (بسته هم باشد، با کرون ادامه می‌یابد).', 'success' );

	$back = hodima_seo_advisor_back_tab( 'overview' );
	wp_safe_redirect( hodima_seo_advisor_page_url( [ 'tab' => $back ] ) );
	exit;
}

/** ذخیره تنظیم خزنده: روشن/خاموش، همه ساعت‌ها یا بازه، ساعت شروع و پایان (تا ۲۴)، فاصله دورها. */
function hodima_seo_advisor_handle_crawler_settings(): void {

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'اجازه این کار را ندارید.', 'دسترسی ندارید', [ 'response' => 403 ] );
	}

	check_admin_referer( 'hodima_advisor_crawler_settings' );

	$hour     = static fn( string $key, int $fallback, int $max ): int => max( 0, min( $max, isset( $_POST[ $key ] ) ? absint( wp_unslash( $_POST[ $key ] ) ) : $fallback ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce بالا بررسی شد
	$interval = isset( $_POST['interval'] ) ? absint( wp_unslash( $_POST['interval'] ) ) : 7; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce بالا بررسی شد

	update_option(
		HODIMA_SEO_ADVISOR_CRAWLER_SETTINGS,
		[
			'enabled'  => ! empty( $_POST['enabled'] ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce بالا بررسی شد
			'always'   => ! empty( $_POST['always'] ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce بالا بررسی شد
			'start'    => $hour( 'start', 2, 23 ),
			'end'      => $hour( 'end', 8, 24 ),
			'interval' => in_array( $interval, HODIMA_SEO_ADVISOR_CRAWLER_INTERVALS, true ) ? $interval : 7,
		],
		false
	);
	hodima_admin_flash( '<strong>خزش خودکار:</strong> تنظیم ذخیره شد.', 'success' );

	wp_safe_redirect( hodima_seo_advisor_page_url( [ 'tab' => hodima_seo_advisor_back_tab( 'jobs' ) ] ) );
	exit;
}

/**
 * وضعیت خزنده برای نمایش: سطح رنگ، برچسب و جمله توضیح.
 *
 * @return array{0: string, 1: string, 2: string, 3: bool} [سطح، برچسب، توضیح، در حال کار؟]
 */
function hodima_seo_advisor_crawler_status_view(): array {

	$settings = hodima_seo_advisor_crawler_settings();
	$status   = hodima_seo_advisor_crawler_status();
	$n        = static fn( int|float $value ): string => number_format_i18n( $value );
	$running  = 'running' === $status['state'];
	$queued   = $n( $status['queued'] + $status['seeding'] );
	$eta      = hodima_seo_advisor_crawler_eta( $status );
	$progress = sprintf( '%1$s صفحه خوانده شد، %2$s در صف', $n( $status['done'] ), $queued ) . ( '' !== $eta ? '؛ ' . $eta : '' ); // SEO 3.4.9: زمان تقریبی مانده

	$view = match ( $status['state'] ) {
		'off'     => [ 'warn', 'خاموش', 'خزش خودکار خاموش است؛ فقط «خزش همین حالا» کار می‌کند.' ],
		'paused'  => [ 'warn', 'مکث موقت', sprintf( 'سایت کند پاسخ داد؛ خزنده تا %s ساکت است تا سایت سبک بماند.', wp_date( 'H:i', $status['pause_until'] ) ) ],
		'running' => [ 'info', $status['manual'] ? 'خزش همین حالا' : ( 'quick' === $status['mode'] ? 'دور سریع' : 'در حال خزش' ), $progress . ( $status['manual'] || hodima_seo_advisor_crawler_in_window() ? '.' : '؛ ادامه در ساعت خزش بعدی.' ) ],
		default   => $status['finished'] > 0
			? ( $status['capped']
				? [ 'warn', 'سقف پر شد', sprintf( 'آخرین دور %1$s پیش تمام شد؛ سایت بیش از %2$s صفحه دارد و بقیه خزیده نشد. صفحه‌های قدیمی پاک نمی‌شوند تا داده از دست نرود.', human_time_diff( $status['finished'] ), $n( HODIMA_SEO_ADVISOR_CRAWLER_MAX_PAGES ) ) ]
				: [ 'ok', 'آماده', sprintf( 'آخرین دور %1$s پیش تمام شد (%2$s صفحه)؛ دور بعد خودکار.', human_time_diff( $status['finished'] ), $n( $status['pages'] ) ) ] )
			: [ 'info', 'منتظر اولین دور', $settings['enabled'] ? 'اولین دور در ساعت خزش شروع می‌شود؛ یا «خزش همین حالا» را بزنید.' : 'هنوز خزشی انجام نشده.' ],
	};

	return [ ...$view, $running && ( $status['manual'] || hodima_seo_advisor_crawler_in_window() ) ];
}

/**
 * این گام ایجکس خودش نوبت خزش بزند؟ «خزش همین حالا» بله؛ دور خودکار فقط وقتی داخل پنجره است و کرون ۱۰ دقیقه نوبتی نزده (SEO 3.4.4).
 *
 * @param array<string, mixed> $state hodima_seo_advisor_crawler_state()
 */
function hodima_seo_advisor_crawler_step_drives( array $state, ?int $now = null ): bool {

	$now = $now ?? time();

	return (bool) $state['manual'] || ( hodima_seo_advisor_crawler_in_window( $now ) && $now - max( (int) $state['tick'], (int) $state['started'] ) > 10 * MINUTE_IN_SECONDS );
}

/**
 * یک گام خزش از پیشخوان (JS صفحه پشت‌سرهم صدا می‌زند تا خزش همین حالا حتی با کرون کند هم پیش برود). فقط وقتی دوری در جریان است.
 */
function hodima_seo_advisor_ajax_crawler_step(): void {

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( [ 'message' => 'دسترسی ندارید' ], 403 );
	}

	check_ajax_referer( 'hodima_advisor_crawler_step' );

	$state    = hodima_seo_advisor_crawler_state();
	$finished = false;

	// SEO 3.4.4: دور خودکار را کرون پیش می‌برد؛ هر تب باز پیشخوان هم یک نوبت ۲۰ ثانیه‌ای می‌زد و یک PHP worker را پشت‌سرهم می‌گرفت.
	// حالا نوبت فقط برای «خزش همین حالا» یا وقتی کرون ۱۰ دقیقه است نوبتی نزده می‌آید؛ وگرنه فقط وضعیت خوانده می‌شود.
	$drive = hodima_seo_advisor_crawler_step_drives( $state );

	if ( 'running' === $state['status'] && $drive ) {
		$finished = hodima_seo_advisor_crawler_tick( true )['finished'];
	}

	[ $level, $label, $info, $running ] = hodima_seo_advisor_crawler_status_view();

	wp_send_json_success( [ 'level' => $level, 'label' => hodima_seo_advisor_fa_digits( $label ), 'text' => hodima_seo_advisor_fa_digits( $info ), 'running' => $running, 'finished' => $finished, 'delay' => $drive ? 1500 : 8000, 'percent' => hodima_seo_advisor_crawler_percent( hodima_seo_advisor_crawler_status() ) ] );
}

/**
 * نام و آدرس ویرایش صفحه یک ردیف خزش: از ردیف داده گوگل (شناسایی‌شده هنگام دریافت)، وگرنه با روتر. خالی = ناشناخته.
 *
 * @return array{title: string, edit: string}
 */
function hodima_seo_advisor_crawl_row_target( string $path ): array {

	$row   = hodima_seo_advisor_page( $path );
	$class = null !== $row && (int) $row['object_id'] > 0 ? [ 'kind' => (string) $row['kind'], 'id' => (int) $row['object_id'] ] : hodima_seo_advisor_classify( $path );

	if ( $class['id'] <= 0 || '' === $class['kind'] ) {
		return [ 'title' => null !== $row ? (string) $row['title'] : '', 'edit' => '' ];
	}

	$title = hodima_seo_advisor_object_title( $class['kind'], $class['id'] );
	$edit  = taxonomy_exists( $class['kind'] ) ? (string) get_edit_term_link( $class['id'], $class['kind'] ) : ( current_user_can( 'edit_post', $class['id'] ) ? (string) get_edit_post_link( $class['id'], 'raw' ) : '' );

	return [ 'title' => $title, 'edit' => $edit ];
}
