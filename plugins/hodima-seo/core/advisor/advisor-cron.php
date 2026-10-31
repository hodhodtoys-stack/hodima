<?php
/**
 * ماژول «دستار سئو» — سلامت کارهای خودکار (کرون) (SEO 3.2.0)
 * Path: core/advisor/advisor-cron.php
 *
 * کرون وردپرس ساعت ندارد و فقط وقتی کسی سایت را باز کند اجرا می‌شود؛ در سایت کم‌بازدید (به‌خصوص شب‌ها، پنجره خزنده)
 * یا وقتی DISABLE_WP_CRON روشن است و کرون سرور ساخته نشده، دریافت داده، خزنده و بررسی ایندکس بی‌صدا عقب می‌افتند و
 * مدیر عدد کهنه را درست فرض می‌کند. این فایل فقط می‌سنجد (چیزی را در سایت یا هاست عوض نمی‌کند): هر کار خودکار
 * یکی از چهار حالت ok / warn («دیر شده») / bad («متوقف») / off («لازم نیست یا خاموش») می‌گیرد.
 *
 * داده فقط از گزینه‌هایی است که پیش از این هم نوشته می‌شدند (زمان آخرین دریافت، پایان دور خزش، زمان‌بندی رویدادها)
 * به‌اضافه `tick` (زمان آخرین نوبت) در وضعیت خزنده. نمایش: advisor-cron-view.php.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * کارهای خودکار و حالتشان.
 *
 * @param int|null $now زمان (آزمون)؛ پیش‌فرض الان
 * @return array{jobs: list<array{key: string, label: string, desc: string, last: string, expect: string, level: string}>, problems: int, level: string, server_cron: bool}
 */
function hodima_seo_advisor_cron_health( ?int $now = null ): array {

	$now      = $now ?? time();
	$has_key  = '' !== hodima_seo_sc_key_json( HODIMA_SEO_SC_ADVISOR );
	$ago      = static fn( int $seconds ): string => sprintf( '%s پیش', human_time_diff( $now - $seconds, $now ) );
	$late     = static fn( int $seconds ): string => sprintf( '%s از موعدش گذشته', human_time_diff( $now - $seconds, $now ) );
	$level_of = static fn( int $age, int $ok, int $warn ): string => $age <= $ok ? 'ok' : ( $age <= $warn ? 'warn' : 'bad' );
	$jobs     = [];

	// ۱) دریافت روزانه داده Search Console
	$fetched = hodima_seo_advisor_status()['fetched'];
	$due     = (int) wp_next_scheduled( HODIMA_SEO_ADVISOR_CRON );
	if ( ! $has_key ) {
		$job = [ 'last' => 'کلید Search Console ثبت نشده', 'level' => 'off' ];
	} elseif ( $fetched > 0 ) {
		$job = [ 'last' => $ago( $now - $fetched ), 'level' => $level_of( $now - $fetched, 2 * DAY_IN_SECONDS, 3 * DAY_IN_SECONDS ) ];
	} else {
		$job = [ 'last' => 'هنوز دریافتی نشده', 'level' => $due > 0 && $now - $due > DAY_IN_SECONDS ? 'warn' : 'ok' ];
	}
	$jobs[] = [ 'key' => 'refresh', 'label' => 'دریافت داده گوگل', 'desc' => 'هر روز، خودکار', 'expect' => 'دست‌کم هر ۴۸ ساعت' ] + $job;

	// ۲) خزنده داخلی
	$jobs[] = hodima_seo_advisor_cron_crawler_job( $now, $ago );

	// ۳) بررسی ایندکس (رویداد دو بار در روز؛ رویداد تکرارشونده بعد از هر اجرا جلو می‌رود، پس «گذشته از موعد» یعنی کرون اجرایش نکرده)
	$next = (int) wp_next_scheduled( HODIMA_SEO_ADVISOR_INSPECT_CRON );
	if ( ! $has_key ) {
		$job = [ 'last' => 'کلید Search Console ثبت نشده', 'level' => 'off' ];
	} elseif ( $next <= 0 ) {
		$job = [ 'last' => 'هنوز زمان‌بندی نشده', 'level' => 'ok' ];
	} else {
		$over = $now - $next;
		$job  = [ 'last' => $over > 0 ? $late( $over ) : 'طبق برنامه', 'level' => $over <= 12 * HOUR_IN_SECONDS ? 'ok' : ( $over <= 60 * HOUR_IN_SECONDS ? 'warn' : 'bad' ) ];
	}
	$jobs[] = [ 'key' => 'inspect', 'label' => 'بررسی ایندکس گوگل', 'desc' => 'دو بار در روز', 'expect' => 'هر ۱۲ ساعت' ] + $job;

	// ۴) ساخت پیشنهادها در پس‌زمینه
	$stage = (int) wp_next_scheduled( HODIMA_SEO_ADVISOR_STAGE_CRON );
	if ( ! hodima_seo_advisor_pipeline_pending() ) {
		$job = [ 'last' => 'بی‌کار', 'level' => 'ok' ];
	} elseif ( $stage <= 0 ) {
		$job = [ 'last' => 'در انتظار زمان‌بندی', 'level' => 'ok' ];
	} else {
		$over = $now - $stage;
		$job  = [ 'last' => $over > 0 ? $late( $over ) : 'در حال کار', 'level' => $over <= 30 * MINUTE_IN_SECONDS ? 'ok' : ( $over <= 6 * HOUR_IN_SECONDS ? 'warn' : 'bad' ) ];
	}
	$jobs[] = [ 'key' => 'pipeline', 'label' => 'ساخت پیشنهادها', 'desc' => 'پس‌زمینه، بعد از هر دریافت', 'expect' => 'بلافاصله بعد از دریافت' ] + $job;

	$levels   = array_column( $jobs, 'level' );
	$problems = count( array_filter( $levels, static fn( string $level ): bool => 'warn' === $level || 'bad' === $level ) );

	return [
		'jobs'        => $jobs,
		'problems'    => $problems,
		'level'       => in_array( 'bad', $levels, true ) ? 'bad' : ( $problems > 0 ? 'warn' : 'ok' ),
		'server_cron' => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
	];
}

/**
 * حالت خزنده: خاموش ← off؛ دور باز ← فاصله از آخرین نوبت نسبت به بیرون بودن از پنجره شبانه؛ بیکار ← فاصله از پایان
 * آخرین دور نسبت به «فاصله دورها + ۱ روز».
 *
 * @param callable(int): string $ago
 * @return array{key: string, label: string, desc: string, last: string, expect: string, level: string}
 */
function hodima_seo_advisor_cron_crawler_job( int $now, callable $ago ): array {

	$settings = hodima_seo_advisor_crawler_settings();
	$state    = hodima_seo_advisor_crawler_state();
	$running  = 'running' === $state['status'];
	$window   = $settings['always'] || $settings['start'] === $settings['end']
		? 24
		: ( $settings['start'] < $settings['end'] ? $settings['end'] - $settings['start'] : 24 - $settings['start'] + $settings['end'] );
	$desc     = $settings['always'] ? 'هر ۵ دقیقه، همه ساعت‌ها' : sprintf( 'هر ۵ دقیقه، ساعت %1$s تا %2$s', number_format_i18n( $settings['start'] ), number_format_i18n( $settings['end'] ) );
	$base     = [ 'key' => 'crawler', 'label' => 'خزنده داخلی', 'desc' => $desc, 'expect' => 'دور بعد خودکار' ];

	if ( ! $settings['enabled'] && ! ( $running && $state['manual'] ) ) {
		return $base + [ 'last' => 'خزش خودکار خاموش است', 'level' => 'off' ];
	}

	if ( $running ) {
		// بیرون از پنجره شبانه (تا ۲۴ - طول پنجره ساعت) نوبتی نمی‌آید؛ همان فاصله + ۶ ساعت حاشیه عادی است
		$allowed = $state['manual'] ? HOUR_IN_SECONDS : ( 24 - $window + ( $window >= 24 ? 0 : 6 ) ) * HOUR_IN_SECONDS;
		$allowed = max( $allowed, 2 * HOUR_IN_SECONDS );
		$tick    = $state['tick'] > 0 ? $state['tick'] : $state['started'];
		$age     = $now - $tick;
		$label   = sprintf( 'آخرین نوبت %s (دور باز، %s صفحه)', $ago( $age ), number_format_i18n( $state['done'] ) );

		return [ 'expect' => 'ادامه دور در ساعت خزش', 'last' => $label, 'level' => $age <= $allowed ? 'ok' : ( $age <= 2 * $allowed ? 'warn' : 'bad' ) ] + $base;
	}

	if ( $state['finished'] <= 0 ) {
		$due = (int) wp_next_scheduled( HODIMA_SEO_ADVISOR_CRAWLER_CRON );
		return $base + [ 'last' => 'هنوز دوری انجام نشده', 'level' => $due > 0 && $now - $due > DAY_IN_SECONDS ? 'warn' : 'ok' ];
	}

	$age   = $now - $state['finished'];
	$limit = ( $settings['interval'] + 1 ) * DAY_IN_SECONDS;

	return [ 'expect' => sprintf( 'هر %s روز یک دور', number_format_i18n( $settings['interval'] ) ), 'last' => sprintf( 'آخرین دور %s تمام شد', $ago( $age ) ), 'level' => $age <= $limit ? 'ok' : ( $age <= 2 * $limit ? 'warn' : 'bad' ) ] + $base;
}

/** دستور کرون واقعی سرور (هر ۵ دقیقه صدا زدن wp-cron.php همین سایت). */
function hodima_seo_advisor_cron_command(): string {
	return '*/5 * * * * wget -q -O - ' . site_url( 'wp-cron.php' ) . '?doing_wp_cron >/dev/null 2>&1';
}
