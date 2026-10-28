<?php
/**
 * ماژول «دستار سئو» — «منتظر داده گوگل»: سنجش اثر هر کار فقط با داده بعد از تاریخ تغییر
 * Path: core/advisor/advisor-verify.php
 *
 * پیشنهادهای آماری (نرخ کلیک، کلمه نزدیک صفحه اول، کلمه بدون صفحه اختصاصی، افت) از جمع ۹۰ روز ساخته می‌شوند؛
 * بعد از اصلاح، این جمع هنوز روزهای پیش از تغییر را دارد و ایراد تا ماه‌ها می‌ماند. اینجا برای هر کار «انجام شد»
 * از سرچ کنسول دو بازه هم‌اندازه گرفته می‌شود: N روز بعد از روز تغییر و N روز پیش از آن (N = روزهای موجود بعد از
 * تغییر، حداقل ۱۴ و حداکثر ۲۸؛ فیلتر همان صفحه)، و حکم می‌شود:
 *   effective  اثر داشت ← کار «برطرف شد» و پیشنهاد تا وقتی بازه تحلیل روزهای پیش از تغییر را دارد ساخته نمی‌شود
 *              (بعد از آن، اگر داده تازه باز همان ایراد را نشان دهد، چرخه عمر «برگشت» می‌دهد)
 *   no_effect  اثر نداشت ← کار دوباره «نیاز به کار» با قدم بعدی مشخص؛ تا ۲۸ روز هر هفته دوباره سنجیده می‌شود
 *   low_data   داده کافی نیست ← همان «منتظر داده گوگل»
 * نوبت `verify` آخر دریافت روزانه است (advisor-refresh.php)؛ هر بار حداکثر چند آدرس. چیزی را در سایت عوض نمی‌کند.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** قاعده‌هایی که با داده بعد از تغییر سنجیده می‌شوند (متنی‌ها با متن صفحه، رقابت داخلی فقط اطلاع است). */
const HODIMA_SEO_ADVISOR_VERIFY_RULES = [ 'low_ctr', 'near_first', 'weak_query', 'decline' ];

/** کم‌ترین و بیشترین روز داده بعد از تغییر برای حکم؛ فاصله سنجش دوباره حکم موقت. */
const HODIMA_SEO_ADVISOR_VERIFY_MIN_DAYS = 14;
const HODIMA_SEO_ADVISOR_VERIFY_MAX_DAYS = 28;
const HODIMA_SEO_ADVISOR_VERIFY_RECHECK  = 7 * DAY_IN_SECONDS;

/** حداکثر گروه (آدرس + روز تغییر) در هر نوبت. */
const HODIMA_SEO_ADVISOR_VERIFY_BATCH = 8;

/** حداقل نمایش بعد از تغییر برای حکم: صفحه و کلمه. */
const HODIMA_SEO_ADVISOR_VERIFY_MIN_IMPRESSIONS      = 100;
const HODIMA_SEO_ADVISOR_VERIFY_MIN_TERM_IMPRESSIONS = 30;

/**
 * روز تغییر، آخرین روز داده و دو بازه هم‌اندازه قبل/بعد.
 *
 * @param array{at: int} $task
 * @return array{day: string, end: string, after_days: int, window: int, before: array{0: string, 1: string}, after: array{0: string, 1: string}}
 */
function hodima_seo_advisor_verify_dates( array $task ): array {

	$day   = new DateTimeImmutable( wp_date( 'Y-m-d', $task['at'] ) ?: gmdate( 'Y-m-d', $task['at'] ) );
	$end   = new DateTimeImmutable( hodima_seo_advisor_ranges( 1 )['end'] );
	$after = $end > $day ? (int) $day->diff( $end )->days : 0;
	$n     = max( 1, min( $after, HODIMA_SEO_ADVISOR_VERIFY_MAX_DAYS ) );
	$fmt   = static fn( DateTimeImmutable $d ): string => $d->format( 'Y-m-d' );

	return [
		'day'        => $fmt( $day ),
		'end'        => $fmt( $end ),
		'after_days' => $after,
		'window'     => min( $after, HODIMA_SEO_ADVISOR_VERIFY_MAX_DAYS ),
		'before'     => [ $fmt( $day->modify( "-{$n} days" ) ), $fmt( $day->modify( '-1 day' ) ) ],
		'after'      => [ $fmt( $day->modify( '+1 day' ) ), $fmt( $day->modify( "+{$n} days" ) ) ],
	];
}

/**
 * برچسب «منتظر داده گوگل» با پیشرفت.
 *
 * @param array{rule: string, at: int, check?: array<string, mixed>} $task
 */
function hodima_seo_advisor_verify_wait_label( array $task ): string {

	if ( ! in_array( $task['rule'], HODIMA_SEO_ADVISOR_VERIFY_RULES, true ) ) {
		return 'انجام شد · منتظر داده گوگل';
	}

	if ( 'low_data' === ( $task['check']['verdict'] ?? '' ) ) {
		return 'انجام شد · منتظر داده گوگل (داده هنوز کم است)';
	}

	$days = hodima_seo_advisor_verify_dates( $task )['after_days'];

	return $days < HODIMA_SEO_ADVISOR_VERIFY_MIN_DAYS
		? sprintf( 'انجام شد · منتظر داده گوگل (%1$s از %2$s روز)', number_format_i18n( $days ), number_format_i18n( HODIMA_SEO_ADVISOR_VERIFY_MIN_DAYS ) )
		: 'انجام شد · منتظر داده گوگل (سنجش در دریافت بعدی)';
}

/**
 * این کار حالا باید سنجیده شود؟
 *
 * @param array{rule: string, status: string, at: int, check: array<string, mixed>} $task
 */
function hodima_seo_advisor_verify_due( array $task ): bool {

	if ( 'done' !== $task['status'] || ! in_array( $task['rule'], HODIMA_SEO_ADVISOR_VERIFY_RULES, true ) ) {
		return false;
	}

	$dates = hodima_seo_advisor_verify_dates( $task );

	if ( $dates['after_days'] < HODIMA_SEO_ADVISOR_VERIFY_MIN_DAYS ) {
		return false;
	}

	$check = $task['check'];

	if ( [] === $check ) {
		return true;
	}

	// حکم «اثر داشت» نهایی است؛ حکم موقت تا رسیدن به ۲۸ روز هر هفته دوباره
	return 'effective' !== ( $check['verdict'] ?? '' )
		&& (int) ( $check['window'] ?? 0 ) < HODIMA_SEO_ADVISOR_VERIFY_MAX_DAYS
		&& time() - (int) ( $check['at'] ?? 0 ) >= HODIMA_SEO_ADVISOR_VERIFY_RECHECK;
}

/**
 * جمع داده یک آدرس در یک بازه از سرچ کنسول: کل صفحه و (اختیاری) هر کلمه.
 *
 * @return array{page: array{clicks: int, impressions: int, ctr: float, position: float}, terms: array<string, array{clicks: int, impressions: int, ctr: float, position: float}>}|WP_Error
 */
function hodima_seo_advisor_verify_fetch( string $property, string $path, string $start, string $end, bool $with_terms ): array|WP_Error {

	// فیلتر «شامل» مسیر کدگذاری‌شده (آدرس خام سرچ کنسول ممکن است www/http/اسلش متفاوت داشته باشد)؛ ردیف‌ها بعد با مسیر یکسان‌شده دقیق جدا می‌شوند
	$filter = [ [ 'dimension' => 'page', 'operator' => '/' === $path ? 'equals' : 'contains', 'expression' => '/' === $path ? hodima_seo_advisor_url( '/' ) : implode( '/', array_map( 'rawurlencode', explode( '/', $path ) ) ) ] ];
	$sum    = static function ( array $rows, callable $pick ): array {
		$out = [];
		foreach ( $rows as $row ) {
			$key = $pick( (array) ( $row['keys'] ?? [] ) );
			if ( null === $key ) {
				continue;
			}
			$imp                  = (int) ( $row['impressions'] ?? 0 );
			$out[ $key ]        ??= [ 'clicks' => 0, 'impressions' => 0, 'weighted' => 0.0 ];
			$out[ $key ]['clicks']      += (int) ( $row['clicks'] ?? 0 );
			$out[ $key ]['impressions'] += $imp;
			$out[ $key ]['weighted']    += (float) ( $row['position'] ?? 0 ) * $imp;
		}
		return array_map(
			static fn( array $m ): array => [
				'clicks'      => $m['clicks'],
				'impressions' => $m['impressions'],
				'ctr'         => $m['impressions'] > 0 ? round( $m['clicks'] / $m['impressions'] * 100, 2 ) : 0.0,
				'position'    => $m['impressions'] > 0 ? round( $m['weighted'] / $m['impressions'], 1 ) : 0.0,
			],
			$out
		);
	};
	$mine   = static fn( array $keys ): bool => hodima_seo_advisor_path_key( (string) ( $keys[0] ?? '' ) ) === $path;

	$pages = hodima_seo_sc_request( $property, $start, $end, [ 'page' ], 1000, 'web', 0, HODIMA_SEO_SC_ADVISOR, $filter );
	if ( is_wp_error( $pages ) ) {
		return $pages;
	}

	$page = $sum( $pages['rows'], static fn( array $keys ): ?string => $mine( $keys ) ? 'page' : null )['page'] ?? [ 'clicks' => 0, 'impressions' => 0, 'ctr' => 0.0, 'position' => 0.0 ];

	if ( ! $with_terms ) {
		return [ 'page' => $page, 'terms' => [] ];
	}

	$queries = hodima_seo_sc_request( $property, $start, $end, [ 'page', 'query' ], 5000, 'web', 0, HODIMA_SEO_SC_ADVISOR, $filter );
	if ( is_wp_error( $queries ) ) {
		return $queries;
	}

	return [ 'page' => $page, 'terms' => $sum( $queries['rows'], static fn( array $keys ): ?string => $mine( $keys ) ? hodima_seo_advisor_term_key( (string) ( $keys[1] ?? '' ) ) : null ) ];
}

/**
 * حکم یک کار از داده قبل/بعد.
 *
 * @param array{rule: string, term: string} $task
 * @param array{page: array<string, int|float>, terms: array<string, array<string, int|float>>} $before
 * @param array{page: array<string, int|float>, terms: array<string, array<string, int|float>>} $after
 * @return array{verdict: string, metric: string, before: float, after: float}
 */
function hodima_seo_advisor_verify_judge( array $task, array $before, array $after ): array {

	$b = $before['page'];
	$a = $after['page'];

	switch ( $task['rule'] ) {
		case 'low_ctr':
			if ( $a['impressions'] < HODIMA_SEO_ADVISOR_VERIFY_MIN_IMPRESSIONS ) {
				return [ 'verdict' => 'low_data', 'metric' => 'ctr', 'before' => (float) $b['ctr'], 'after' => (float) $a['ctr'] ];
			}
			// ۱۵٪ بهتر از پیش از تغییر، یا رسیدن به نرخ معمول رتبه فعلی
			$ok = ( $a['ctr'] > $b['ctr'] && $a['ctr'] >= $b['ctr'] * 1.15 ) || $a['ctr'] >= hodima_seo_advisor_expected_ctr( (float) $a['position'] );
			return [ 'verdict' => $ok ? 'effective' : 'no_effect', 'metric' => 'ctr', 'before' => (float) $b['ctr'], 'after' => (float) $a['ctr'] ];

		case 'decline':
			if ( $a['impressions'] < HODIMA_SEO_ADVISOR_VERIFY_MIN_IMPRESSIONS ) {
				return [ 'verdict' => 'low_data', 'metric' => 'clicks', 'before' => (float) $b['clicks'], 'after' => (float) $a['clicks'] ];
			}
			$ok = $a['clicks'] >= $b['clicks'] * 1.15 && $a['clicks'] - $b['clicks'] >= 5;
			return [ 'verdict' => $ok ? 'effective' : 'no_effect', 'metric' => 'clicks', 'before' => (float) $b['clicks'], 'after' => (float) $a['clicks'] ];

		default: // near_first، weak_query: رتبه همان کلمه
			$key = hodima_seo_advisor_term_key( $task['term'] );
			$bt  = $before['terms'][ $key ] ?? null;
			$at  = $after['terms'][ $key ] ?? null;
			if ( null === $at || $at['impressions'] < HODIMA_SEO_ADVISOR_VERIFY_MIN_TERM_IMPRESSIONS ) {
				return [ 'verdict' => 'low_data', 'metric' => 'position', 'before' => (float) ( $bt['position'] ?? 0 ), 'after' => (float) ( $at['position'] ?? 0 ) ];
			}
			// دو پله بهتر، یا رسیدن به هدف قاعده (نزدیک صفحه اول ← بالای صفحه اول؛ بدون صفحه اختصاصی ← صفحه دوم)
			$target = 'near_first' === $task['rule'] ? 7.0 : 20.0;
			$ok     = ( null !== $bt && $bt['position'] > 0 && $at['position'] <= $bt['position'] - 2 ) || $at['position'] <= $target;
			return [ 'verdict' => $ok ? 'effective' : 'no_effect', 'metric' => 'position', 'before' => (float) ( $bt['position'] ?? 0 ), 'after' => (float) $at['position'] ];
	}
}

/**
 * یک نوبت سنجش: کارهای سررسیده، گروه‌شده با آدرس و روز تغییر، هر گروه دو بازه از سرچ کنسول. خطای سهمیه یا کلید
 * نوبت را می‌بندد (فردا ادامه). کار «اثر داشت» همان لحظه «برطرف شد» می‌گیرد و پیشنهادهای آدرسش دوباره ساخته می‌شوند.
 *
 * @return int شمار کارهای سنجیده‌شده
 */
function hodima_seo_advisor_verify_batch( string $property, float $deadline ): int {

	if ( '' === $property || ! (bool) apply_filters( 'hodima_seo_advisor_verify_enabled', true ) ) {
		return 0;
	}

	$groups = [];
	foreach ( hodima_seo_advisor_tasks() as $key => $task ) {
		if ( hodima_seo_advisor_verify_due( $task ) ) {
			$groups[ $task['path'] . '|' . hodima_seo_advisor_verify_dates( $task )['day'] ][] = $key;
		}
	}

	$checked = 0;
	$paths   = [];

	foreach ( array_slice( $groups, 0, HODIMA_SEO_ADVISOR_VERIFY_BATCH, true ) as $keys ) {
		if ( microtime( true ) >= $deadline ) {
			break;
		}

		$tasks = hodima_seo_advisor_tasks();
		$first = $tasks[ $keys[0] ];
		$dates = hodima_seo_advisor_verify_dates( $first );
		$terms = (bool) array_filter( $keys, static fn( string $k ): bool => in_array( $tasks[ $k ]['rule'], [ 'near_first', 'weak_query' ], true ) );

		$before = hodima_seo_advisor_verify_fetch( $property, $first['path'], $dates['before'][0], $dates['before'][1], $terms );
		$after  = is_wp_error( $before ) ? $before : hodima_seo_advisor_verify_fetch( $property, $first['path'], $dates['after'][0], $dates['after'][1], $terms );

		if ( is_wp_error( $after ) || is_wp_error( $before ) ) {
			break; // سهمیه/کلید/شبکه: بقیه فردا
		}

		$patch  = [];
		$expect = [];

		foreach ( $keys as $key ) {
			$judged         = hodima_seo_advisor_verify_judge( $tasks[ $key ], $before, $after );
			$patch[ $key ]  = [ 'check' => $judged + [ 'window' => $dates['window'], 'at' => time() ] ];
			$expect[ $key ] = [ 'at' => $tasks[ $key ]['at'], 'status' => $tasks[ $key ]['status'] ];
			if ( 'effective' === $judged['verdict'] && 0 === $tasks[ $key ]['fixed'] ) {
				$patch[ $key ]['fixed'] = time();
				$patch[ $key ]['back']  = 0;
			}
			++$checked;
		}

		// درخواست‌های سرچ کنسول چند ثانیه طول کشیدند؛ حکم روی نسخه تازه کارها می‌نشیند (کار تغییرکرده توسط مدیر دست نمی‌خورد)
		hodima_seo_advisor_tasks_patch( $patch, $expect );
		$paths[ $first['path'] ] = true;
	}

	// پیشنهاد کار «اثر داشت» کنار برود و «اثر نداشت» با قدم بعدی بماند
	foreach ( array_keys( $paths ) as $path ) {
		if ( null !== hodima_seo_advisor_page( $path ) ) {
			hodima_seo_advisor_save_advice( $path );
		}
	}

	return $checked;
}

/**
 * پیشنهادهایی که کارشان با داده بعد از تغییر «اثر داشت» کنار می‌روند، تا وقتی بازه تحلیل فعلی روزهای پیش از تغییر
 * را هم دارد (جمع ۹۰ روزه هنوز ایراد قدیمی را نشان می‌دهد). بعد از آن تصمیم با داده تازه است.
 *
 * @param list<array<string, mixed>> $suggestions
 * @return list<array<string, mixed>>
 */
function hodima_seo_advisor_verify_filter( string $path, array $suggestions ): array {

	$tasks = hodima_seo_advisor_tasks();
	$start = hodima_seo_advisor_status()['start'];

	if ( ! $tasks || '' === $start ) {
		return $suggestions;
	}

	return array_values(
		array_filter(
			$suggestions,
			static function ( array $item ) use ( $tasks, $path, $start ): bool {
				$task = $tasks[ hodima_seo_advisor_task_key( $path, (string) ( $item['rule'] ?? '' ), (string) ( $item['term'] ?? '' ) ) ] ?? null;
				return null === $task
					|| 'effective' !== ( $task['check']['verdict'] ?? '' )
					|| $start > hodima_seo_advisor_verify_dates( $task )['day'];
			}
		)
	);
}

/**
 * جمله نتیجه سنجش برای تب «کارهای من» (با قدم بعدی وقتی اثر نداشت)؛ بدون سنجش رشته خالی.
 *
 * @param array{rule: string, term: string, check: array<string, mixed>} $task
 */
function hodima_seo_advisor_verify_text( array $task ): string {

	$check = $task['check'];

	if ( [] === $check || ! isset( $check['verdict'] ) ) {
		return '';
	}

	$n      = static fn( float $v, int $d = 0 ): string => number_format_i18n( $v, $d );
	$before = (float) ( $check['before'] ?? 0 );
	$after  = (float) ( $check['after'] ?? 0 );
	$change = match ( (string) ( $check['metric'] ?? '' ) ) {
		'ctr'      => sprintf( 'نرخ کلیک %1$s٪ ← %2$s٪', $n( $before, 2 ), $n( $after, 2 ) ),
		'clicks'   => sprintf( 'کلیک %1$s ← %2$s', $n( $before ), $n( $after ) ),
		default    => sprintf( 'رتبه «%1$s» %2$s ← %3$s', $task['term'], $before > 0 ? $n( $before, 1 ) : '—', $after > 0 ? $n( $after, 1 ) : '—' ),
	};
	$head   = sprintf( 'سنجش با %1$s روز داده بعد از تغییر در برابر همان‌قدر پیش از آن: %2$s. ', $n( (float) ( $check['window'] ?? 0 ) ), $change );

	return $head . match ( (string) $check['verdict'] ) {
		'effective' => 'اثر داشت؛ کار برطرف شد.',
		'low_data'  => 'داده هنوز کم است؛ هفته بعد دوباره سنجیده می‌شود.',
		default     => 'اثر نداشت. قدم بعدی: ' . match ( $task['rule'] ) {
			'low_ctr' => 'عنوان را دوباره بنویسید: کلمه اصلی در ابتدای عنوان، یک مزیت روشن (قیمت عمده، تنوع، ارسال) و توضیح متایی که با دعوت به اقدام تمام شود. بعد «دوباره انجام دادم» را بزنید.',
			'decline' => '«آدرس‌های خراب» و «ایندکس» همین آدرس را ببینید، و در «صفحه‌های هم‌کلمه» بررسی کنید صفحه دیگری جای این صفحه را برای کلمه‌های اصلی نگرفته باشد.',
			default   => 'از ۲ تا ۳ صفحه مرتبط با همین عبارت به این صفحه لینک متنی بدهید و برای این کلمه یک بخش با تیتر H2 و دست‌کم دو پاراگراف بنویسید. بعد «دوباره انجام دادم» را بزنید.',
		},
	};
}
