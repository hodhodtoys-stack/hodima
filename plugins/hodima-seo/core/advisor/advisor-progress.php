<?php
/**
 * ماژول «دستار سئو» — پیشرفت زندهٔ «اجرا همین حالا» هر کار (SEO 3.4.8)
 * Path: core/advisor/advisor-progress.php
 *
 * هر کار خودکار (دریافت داده گوگل، بررسی ایندکس، ساخت پیشنهادها) در نقطه‌های مشخص «گام فعلی» را اینجا می‌نویسد و صفحه
 * «خزش و داده» هر ۱٫۵ ثانیه آن را می‌خواند و نوار پیشرفت را پر می‌کند. عددها واقعی‌اند: گام‌های دریافت، شمار آدرس‌های
 * پرسیده‌شده در دسته ایندکس، آدرس‌های تحلیل‌شده از صف. خزنده شمارنده خودش را دارد (advisor-jobs-view.php).
 * نوشتن بی‌خطر است؛ کرون هم همین را می‌نویسد (چند نوشتن کوچک برای هر اجرا) و هیچ تصمیمی به آن وابسته نیست.
 *
 * داده: گزینه hodima_advisor_progress (autoload خاموش): کلید کار ← [running، step، total، label، percent، at، text، error].
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

const HODIMA_SEO_ADVISOR_PROGRESS_OPTION = 'hodima_advisor_progress';

/** پیشرفتی که بیش از این ثانیه به‌روز نشده «در جریان» حساب نمی‌شود (اجرای قطع‌شده). */
const HODIMA_SEO_ADVISOR_PROGRESS_STALE = 180;

/**
 * کار فعلی نوبت‌ها (برای زیرگام «تحلیل آدرس‌ها» داخل نوبت analyze): [کلید کار، گام فعلی، شمار گام‌ها]؛ خالی = کسی نمی‌شنود.
 *
 * @param array{0: string, 1: int, 2: int}|null|false $set false = فقط بخوان
 * @return array{0: string, 1: int, 2: int}|null
 */
function hodima_seo_advisor_progress_context( array|null|false $set = false ): ?array {

	static $context = null;

	if ( false !== $set ) {
		$context = $set;
	}

	return $context;
}

/**
 * گام فعلی یک کار را می‌نویسد.
 *
 * @param int|null $percent null = از گام حساب می‌شود ((گام−۱) ÷ شمار گام‌ها)
 */
function hodima_seo_advisor_progress_set( string $job, int $step, int $total, string $label, ?int $percent = null ): void {

	if ( '' === $job || $total < 1 ) {
		return;
	}

	$all = get_option( HODIMA_SEO_ADVISOR_PROGRESS_OPTION, [] );
	$all = is_array( $all ) ? $all : [];

	$all[ $job ] = [
		'running' => true,
		'step'    => $step,
		'total'   => $total,
		'label'   => $label,
		'percent' => (int) max( 0, min( 99, $percent ?? round( ( $step - 1 ) / $total * 100 ) ) ),
		'at'      => time(),
		'text'    => '',
		'error'   => false,
	];

	update_option( HODIMA_SEO_ADVISOR_PROGRESS_OPTION, $all, false );
}

/** پایان یک کار (موفق یا با خطا) با جمله کوتاه نتیجه. */
function hodima_seo_advisor_progress_finish( string $job, string $text, bool $error = false ): void {

	if ( '' === $job ) {
		return;
	}

	$all = get_option( HODIMA_SEO_ADVISOR_PROGRESS_OPTION, [] );
	$all = is_array( $all ) ? $all : [];

	$all[ $job ] = [
		'running' => false,
		'step'    => 0,
		'total'   => 0,
		'label'   => '',
		'percent' => $error ? 0 : 100,
		'at'      => time(),
		'text'    => $text,
		'error'   => $error,
	];

	update_option( HODIMA_SEO_ADVISOR_PROGRESS_OPTION, $all, false );
}

/**
 * زیرگام داخل گام فعلی کار (مثلا «۶۲۰ از ۱٬۵۰۰ آدرس تحلیل شد»)؛ فقط وقتی نوبت‌ها برای یک کار می‌دوند.
 *
 * @param float $fraction ۰ تا ۱ از همین گام
 */
function hodima_seo_advisor_progress_substep( string $label, float $fraction ): void {

	$context = hodima_seo_advisor_progress_context();

	if ( null === $context ) {
		return;
	}

	[ $job, $step, $total ] = $context;

	hodima_seo_advisor_progress_set( $job, $step, $total, $label, (int) round( ( $step - 1 + max( 0.0, min( 1.0, $fraction ) ) ) / $total * 100 ) );
}

/**
 * وضعیت پیشرفت یک کار.
 *
 * @return array{running: bool, step: int, total: int, label: string, percent: int, at: int, text: string, error: bool}
 */
function hodima_seo_advisor_progress_get( string $job ): array {

	$all   = get_option( HODIMA_SEO_ADVISOR_PROGRESS_OPTION, [] );
	$entry = is_array( $all ) && is_array( $all[ $job ] ?? null ) ? $all[ $job ] : [];
	$at    = (int) ( $entry['at'] ?? 0 );

	return [
		'running' => ! empty( $entry['running'] ) && time() - $at <= HODIMA_SEO_ADVISOR_PROGRESS_STALE,
		'step'    => (int) ( $entry['step'] ?? 0 ),
		'total'   => (int) ( $entry['total'] ?? 0 ),
		'label'   => (string) ( $entry['label'] ?? '' ),
		'percent' => (int) ( $entry['percent'] ?? 0 ),
		'at'      => $at,
		'text'    => (string) ( $entry['text'] ?? '' ),
		'error'   => ! empty( $entry['error'] ),
	];
}

/** نام فارسی نوبت‌های ساخت پیشنهادها (برچسب نوار پیشرفت). */
function hodima_seo_advisor_progress_stage_label( string $stage ): string {
	return [
		'ctr'       => 'ساخت جدول نرخ کلیک',
		'health'    => 'بررسی سلامت آدرس‌ها',
		'analyze'   => 'تحلیل آدرس‌ها',
		'breakdown' => 'بخش‌بندی موبایل و کشور',
		'trend'     => 'روند ماهانه',
		'history'   => 'تاریخچه هفتگی',
		'verify'    => 'سنجش اثر کارها',
	][ $stage ] ?? $stage;
}
