<?php
/**
 * ماژول «دستار سئو» — وضعیت دستی پیشنهادها («کارهای من») و اندازه‌گیری اثر
 * Path: core/advisor/advisor-tasks.php
 *
 * مشاور چیزی را خودکار عوض نمی‌کند؛ مدیر هر پیشنهاد را خودش اجرا می‌کند. این فایل فقط
 * «یادداشت» او را نگه می‌دارد: برای هر پیشنهاد (آدرس + نوع + کلمه) یکی از وضعیت‌های «در حال انجام»،
 * «انجام شد» یا «رد شد». برای «انجام شد» با تاریخچه هفتگی (advisor-history.php) اثرش اندازه می‌شود:
 * میانگین کلیک هفتگی تا ۳ هفته کامل قبل از آن روز با تا ۳ هفته کامل بعدش.
 *
 * داده: گزینه hodima_advisor_tasks (autoload خاموش، حداکثر ۵۰۰ مورد؛ از پرشدن، قدیمی‌ترین «رد شد»ها
 * اول می‌روند). «پاک کردن داده‌های مشاور» آن را پاک نمی‌کند (یادداشت مدیر است، نه داده گوگل).
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

const HODIMA_SEO_ADVISOR_TASKS_OPTION = 'hodima_advisor_tasks';
const HODIMA_SEO_ADVISOR_TASKS_MAX    = 500;

add_action( 'admin_post_hodima_advisor_task', 'hodima_seo_advisor_handle_task' );

/** @return array<string, string> وضعیت ← برچسب */
function hodima_seo_advisor_task_statuses(): array {
	return [ 'doing' => 'در حال انجام', 'done' => 'انجام شد', 'rejected' => 'رد شد' ];
}

/** کلید یکتای یک پیشنهاد. */
function hodima_seo_advisor_task_key( string $path, string $rule, string $term ): string {
	return substr( md5( $path . '|' . $rule . '|' . hodima_seo_advisor_normalize( $term ) ), 0, 16 );
}

/**
 * همه کارها (کلید ← کار).
 *
 * @return array<string, array{path: string, rule: string, term: string, title: string, status: string, at: int}>
 */
function hodima_seo_advisor_tasks(): array {

	$stored = get_option( HODIMA_SEO_ADVISOR_TASKS_OPTION, [] );
	$out    = [];

	foreach ( is_array( $stored ) ? $stored : [] as $key => $task ) {
		if ( is_string( $key ) && is_array( $task ) && isset( hodima_seo_advisor_task_statuses()[ (string) ( $task['status'] ?? '' ) ] ) ) {
			$out[ $key ] = [
				'path'   => (string) ( $task['path'] ?? '' ),
				'rule'   => (string) ( $task['rule'] ?? '' ),
				'term'   => (string) ( $task['term'] ?? '' ),
				'title'  => (string) ( $task['title'] ?? '' ),
				'status' => (string) $task['status'],
				'at'     => (int) ( $task['at'] ?? 0 ),
			];
		}
	}

	return $out;
}

/**
 * کار یک پیشنهاد، یا null.
 *
 * @return array{path: string, rule: string, term: string, title: string, status: string, at: int}|null
 */
function hodima_seo_advisor_task( string $path, string $rule, string $term ): ?array {
	return hodima_seo_advisor_tasks()[ hodima_seo_advisor_task_key( $path, $rule, $term ) ] ?? null;
}

/**
 * وضعیت یک پیشنهاد را می‌گذارد؛ وضعیت خالی = برداشتن از فهرست.
 */
function hodima_seo_advisor_task_set( string $path, string $rule, string $term, string $title, string $status ): bool {

	if ( '' !== $status && ! isset( hodima_seo_advisor_task_statuses()[ $status ] ) ) {
		return false;
	}

	if ( '' === $path || 1 !== preg_match( '/^[a-z_]+$/', $rule ) ) {
		return false;
	}

	$tasks = hodima_seo_advisor_tasks();
	$key   = hodima_seo_advisor_task_key( $path, $rule, $term );

	if ( '' === $status ) {
		unset( $tasks[ $key ] );
	} else {
		$old           = $tasks[ $key ] ?? null;
		$tasks[ $key ] = [
			'path'   => $path,
			'rule'   => $rule,
			'term'   => $term,
			'title'  => mb_substr( $title, 0, 200 ) ?: ( $old['title'] ?? '' ),
			'status' => $status,
			'at'     => null !== $old && $old['status'] === $status ? $old['at'] : time(), // تاریخ همان وضعیت می‌ماند
		];
	}

	if ( count( $tasks ) > HODIMA_SEO_ADVISOR_TASKS_MAX ) {
		uasort( $tasks, static fn( array $a, array $b ): int => [ 'rejected' === $a['status'], -$a['at'] ] <=> [ 'rejected' === $b['status'], -$b['at'] ] ); // اول غیر«رد شد»ها، تازه‌ترین‌ها
		$tasks = array_slice( $tasks, 0, HODIMA_SEO_ADVISOR_TASKS_MAX, true );
	}

	update_option( HODIMA_SEO_ADVISOR_TASKS_OPTION, $tasks, false );

	return true;
}

/**
 * شمار کارها به تفکیک وضعیت.
 *
 * @return array<string, int>
 */
function hodima_seo_advisor_task_counts(): array {

	$counts = array_fill_keys( array_keys( hodima_seo_advisor_task_statuses() ), 0 );

	foreach ( hodima_seo_advisor_tasks() as $task ) {
		++$counts[ $task['status'] ];
	}

	return $counts;
}

/**
 * اثر یک کار «انجام شد» روی کلیک هفتگی صفحه.
 *
 * @param array{path: string, status: string, at: int} $task
 * @return array{state: string, effect?: array<string, mixed>, site?: array<string, mixed>|null} state: wait (هنوز زود است)، none (تاریخچه کافی نیست)، ok؛ site = مقایسه با روند کل سایت (hodima_seo_advisor_effect_vs_site)
 */
function hodima_seo_advisor_task_effect( array $task ): array {

	$history = hodima_seo_advisor_history( $task['path'], 26 );
	$day     = wp_date( 'Y-m-d', $task['at'] ) ?: gmdate( 'Y-m-d', $task['at'] );
	$effect  = hodima_seo_advisor_before_after( $history, $day );

	if ( null !== $effect ) {
		return [ 'state' => 'ok', 'effect' => $effect, 'site' => hodima_seo_advisor_effect_vs_site( $effect ) ];
	}

	// هنوز هفته کاملی بعد از آن روز نیست؟
	$last_week = $history ? (string) end( $history )['week'] : '';
	if ( '' === $last_week || $last_week <= $day || ( new DateTimeImmutable( $last_week ) )->modify( '-6 days' )->format( 'Y-m-d' ) <= $day ) {
		return [ 'state' => 'wait' ];
	}

	return [ 'state' => 'none' ];
}

/**
 * ثبت وضعیت از لینک/فرم (admin-post)؛ GET با nonce از کادر ویرایش، POST از تب «کارهای من».
 */
function hodima_seo_advisor_handle_task(): void {

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'اجازه این کار را ندارید.', 'دسترسی ندارید', [ 'response' => 403 ] );
	}

	check_admin_referer( 'hodima_advisor_task' );

	$path   = sanitize_text_field( wp_unslash( (string) ( $_REQUEST['path'] ?? '' ) ) );
	$rule   = sanitize_key( (string) ( $_REQUEST['rule'] ?? '' ) );
	$term   = sanitize_text_field( wp_unslash( (string) ( $_REQUEST['term'] ?? '' ) ) );
	$title  = sanitize_text_field( wp_unslash( (string) ( $_REQUEST['title'] ?? '' ) ) );
	$status = sanitize_key( (string) ( $_REQUEST['status'] ?? '' ) );

	if ( hodima_seo_advisor_task_set( $path, $rule, $term, $title, $status ) ) {
		hodima_admin_flash( '' === $status ? 'پیشنهاد از «کارهای من» برداشته شد.' : sprintf( 'وضعیت پیشنهاد: «%s» ثبت شد.', hodima_seo_advisor_task_statuses()[ $status ] ), 'success' );
	} else {
		hodima_admin_flash( 'وضعیت ثبت نشد؛ درخواست نامعتبر بود.', 'error' );
	}

	$back = wp_get_referer();
	wp_safe_redirect( is_string( $back ) && '' !== $back ? $back : admin_url( 'admin.php?page=' . HODIMA_SEO_ADVISOR_PAGE . '&tab=tasks' ) );
	exit;
}
