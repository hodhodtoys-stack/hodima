<?php
/**
 * ماژول «دستار سئو» — «انجام شد / رد شد» در همه تب‌ها و سنجش هر نوع کار
 * Path: core/advisor/advisor-marks.php
 *
 * تا SEO 3.0.1 فقط پیشنهادهای کادر ویرایش «انجام دادم» داشتند؛ مدیر ایراد فنی یا آدرس خراب را درست می‌کرد و ردیف تا
 * خزش یا دریافت بعدی همان‌جا می‌ماند. حالا هر ردیف تب‌ها (پیشنهادها، ایرادهای فنی، صفحه‌های هم‌کلمه، آدرس‌های خراب،
 * وضعیت در گوگل) فرم خودش را دارد و کارش با همان مدل advisor-tasks.php ثبت می‌شود؛ قاعده کار منبعش را می‌گوید:
 *   پیشنهادها        قاعده تحلیل (low_ctr، …)        همان سنجش قبلی (ذخیره صفحه، داده ۱۴ روزه گوگل)
 *   ایرادهای فنی     crawl_<کد ایراد>               متنی‌ها (عنوان، متا، H1، تکراری، noindex) و خطای ۴۰۴/۵۰۰: همان لحظه
 *                                                  صفحه از سایت خوانده می‌شود؛ کند، لینک ورودی و ریدایرکت: خزش بعدی
 *   صفحه‌های هم‌کلمه competition (مسیر برنده، کلمه)  داده تازه گوگل: یک صفحه ≥ ۷۰٪ نمایش یا گروه رفته ← برطرف؛
 *                                                  ۱۴ روز بعد هنوز رقابت ← «هنوز مانده»
 *   آدرس‌های خراب    health_dead                    همان لحظه با روتر؛ سالم یا ریدایرکت ← برطرف
 *                    health_unseen                  اول صف بررسی ایندکس؛ نمایش یا ایندکس ← برطرف؛ ۳۰ روز بعد نه ← مانده
 *   وضعیت در گوگل    index_problem                  اول صف بررسی ایندکس؛ ایندکس ← برطرف؛ خزیده و هنوز نه ← مانده
 * حکم «هنوز مانده» کار را با وضعیت still به «در دست انجام» برمی‌گرداند (باز)؛ کار برطرف‌شده‌ای که دوباره پیدا شود
 * «برگشت» می‌گیرد. چیزی را در سایت عوض نمی‌کند؛ فقط می‌سنجد.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** ایرادهای خزش که با خواندن دوباره همان صفحه سنجیده می‌شوند؛ بقیه (کند، لینک ورودی، ریدایرکت) فقط با خزش بعدی. */
const HODIMA_SEO_ADVISOR_MARK_LIVE_CODES = [ 'no_meta', 'no_snippet', 'no_h1', 'no_title', 'title_short', 'title_long', 'dup_title', 'dup_h1', 'noindex' ];

/** روزهای انتظار پیش از حکم «هنوز مانده» (رقابت با داده ۹۰ روزه دیر عوض می‌شود؛ صفحه تازه ایندکس دیر نمایش می‌گیرد). */
const HODIMA_SEO_ADVISOR_MARK_COMPETITION_DAYS = 14;
const HODIMA_SEO_ADVISOR_MARK_UNSEEN_DAYS      = 30;

/** سهم نمایش صفحه اول گروه که رقابت را «حل» می‌داند. */
const HODIMA_SEO_ADVISOR_MARK_COMPETITION_SHARE = 0.7;

add_action( 'admin_post_hodima_advisor_mark', 'hodima_seo_advisor_handle_mark' );

/** @return array<string, string> منبع (کلید تب) ← برچسب */
function hodima_seo_advisor_task_sources(): array {
	return [
		'opportunities' => 'پیشنهادها',
		'crawl'         => 'ایرادهای فنی',
		'competition'   => 'صفحه‌های هم‌کلمه',
		'health'        => 'آدرس‌های خراب',
		'indexing'      => 'وضعیت در گوگل',
	];
}

/** منبع یک کار از قاعده‌اش (کلید تب). */
function hodima_seo_advisor_task_source( string $rule ): string {
	return match ( true ) {
		str_starts_with( $rule, 'crawl_' )                          => 'crawl',
		'competition' === $rule                                     => 'competition',
		in_array( $rule, [ 'health_dead', 'health_unseen' ], true ) => 'health',
		'index_problem' === $rule                                   => 'indexing',
		default                                                     => 'opportunities',
	};
}

/**
 * برچسب «منتظر نتیجه» هر نوع کار.
 *
 * @param array{rule: string, at: int, check?: array<string, mixed>} $task
 */
function hodima_seo_advisor_task_wait_label( array $task ): string {

	$rule = $task['rule'];

	return match ( hodima_seo_advisor_task_source( $rule ) ) {
		'crawl'       => 'انجام شد · منتظر خزش تازه',
		'competition' => (int) floor( ( time() - $task['at'] ) / DAY_IN_SECONDS ) < HODIMA_SEO_ADVISOR_MARK_COMPETITION_DAYS
			? sprintf( 'انجام شد · منتظر داده گوگل (%1$s از %2$s روز)', number_format_i18n( max( 0, (int) floor( ( time() - $task['at'] ) / DAY_IN_SECONDS ) ) ), number_format_i18n( HODIMA_SEO_ADVISOR_MARK_COMPETITION_DAYS ) )
			: 'انجام شد · منتظر داده گوگل (سنجش در دریافت بعدی)',
		'health'      => 'health_dead' === $rule ? 'انجام شد · منتظر بررسی بعدی' : 'انجام شد · منتظر نمایش یا ایندکس',
		'indexing'    => 'انجام شد · منتظر بررسی دوباره گوگل',
		default       => function_exists( 'hodima_seo_advisor_verify_wait_label' ) ? hodima_seo_advisor_verify_wait_label( $task ) : 'انجام شد · منتظر داده گوگل',
	};
}

/** برچسب «هنوز مانده» هر نوع کار. */
function hodima_seo_advisor_task_still_label( string $rule ): string {
	return match ( $rule ) {
		'competition'   => 'هنوز رقابت دارد',
		'health_dead'   => 'هنوز خراب است',
		'health_unseen' => 'هنوز نمایش ندارد',
		'index_problem' => 'گوگل هنوز ایندکس نکرده',
		default         => 'انجام شد؛ هنوز دیده می‌شود',
	};
}

/**
 * حکم سنجش برای کارهای یک منبع. `$judge` برای هر کار می‌گوید: fixed (ایراد رفته)، still (هنوز هست و وقت حکم است)
 * یا wait (هنوز نمی‌شود گفت). برطرف ← «برطرف شد»؛ برطرف‌شده‌ای که هنوز هست ← «برگشت»؛ «انجام دادم» که هنوز هست ← still.
 * کار «لازم نیست» سنجیده نمی‌شود.
 *
 * @param callable(array<string, mixed>): string $judge
 * @param list<string>|null                      $paths فقط این آدرس‌ها (null = همه)
 * @param string|null                            $rule  فقط این قاعده
 * @return int شمار کارهایی که عوض شدند
 */
function hodima_seo_advisor_tasks_judge( string $source, callable $judge, ?array $paths = null, ?string $rule = null ): int {

	$tasks   = hodima_seo_advisor_tasks();
	$scope   = null === $paths ? null : array_flip( $paths );
	$changed = 0;
	$now     = time();
	$patch   = [];
	$expect  = [];

	foreach ( $tasks as $key => $task ) {
		if ( 'rejected' === $task['status'] || hodima_seo_advisor_task_source( $task['rule'] ) !== $source
			|| ( null !== $scope && ! isset( $scope[ $task['path'] ] ) ) || ( null !== $rule && $rule !== $task['rule'] ) ) {
			continue;
		}

		$verdict = $judge( $task );

		if ( 'fixed' === $verdict && 0 === $task['fixed'] ) {
			$patch[ $key ] = [ 'fixed' => $now, 'back' => 0, 'check' => [ 'verdict' => 'fixed', 'at' => $now ] ];
		} elseif ( 'still' === $verdict && $task['fixed'] > 0 ) {
			$patch[ $key ] = [ 'fixed' => 0, 'back' => $now, 'check' => [] ];
		} elseif ( 'still' === $verdict && 'done' === $task['status'] && 0 === $task['back'] && 'still' !== ( $task['check']['verdict'] ?? '' ) ) {
			$patch[ $key ] = [ 'check' => [ 'verdict' => 'still', 'at' => $now ] ];
		} else {
			continue;
		}

		$expect[ $key ] = [ 'at' => $task['at'], 'status' => $task['status'] ];
		++$changed;
	}

	// فقط فیلدهای حکم روی نسخه تازه کارها می‌نشینند (کار تغییرکرده توسط مدیر در این فاصله دست نمی‌خورد)
	if ( $patch ) {
		hodima_seo_advisor_tasks_patch( $patch, $expect );
	}

	return $changed;
}

/** کاری از این منبع ثبت شده؟ (سنجش‌های سنگین بی‌کار اجرا نشوند) */
function hodima_seo_advisor_tasks_has( string $source ): bool {
	foreach ( hodima_seo_advisor_tasks() as $task ) {
		if ( 'rejected' !== $task['status'] && hodima_seo_advisor_task_source( $task['rule'] ) === $source ) {
			return true;
		}
	}
	return false;
}

/**
 * کارهای ایرادهای فنی با ایرادهای فعلی جدول خزش. بعد از خواندن خزش تازه همه کدها؛ بعد از خواندن یک صفحه از سایت
 * (advisor-live.php) فقط `$codes` همان آدرس. آدرسی که در خزش نیست حکم ندارد (شاید خزنده به آن نرسیده).
 *
 * @param list<string>|null $codes
 */
function hodima_seo_advisor_tasks_sync_crawl( ?string $path = null, ?array $codes = null ): int {

	if ( ! hodima_seo_advisor_tasks_has( 'crawl' ) ) {
		return 0;
	}

	$present = [];
	foreach ( hodima_seo_advisor_crawl_issues() as $row ) {
		if ( null === $path || $row['path'] === $path ) {
			foreach ( $row['issues'] as $issue ) {
				$present[ $row['path'] . '|' . $issue['code'] ] = true;
			}
		}
	}

	return hodima_seo_advisor_tasks_judge(
		'crawl',
		static function ( array $task ) use ( $present, $codes ): string {
			$code = substr( $task['rule'], 6 );
			if ( ( null !== $codes && ! in_array( $code, $codes, true ) ) || null === hodima_seo_advisor_crawl_row( $task['path'] ) ) {
				return 'wait';
			}
			return isset( $present[ $task['path'] . '|' . $code ] ) ? 'still' : 'fixed';
		},
		null === $path ? null : [ $path ]
	);
}

/**
 * کار «صفحه بدون نمایش»: از فهرست بدون نمایش رفته یا گوگل بعد از «انجام دادم» ایندکس‌شده گفته ← برطرف؛ ۳۰ روز بعد هنوز
 * بدون نمایش ← مانده.
 *
 * @param array{path: string, at: int}          $task
 * @param array<string, bool>                   $unseen مسیر ← true
 * @param array<string, array<string, mixed>>   $records بررسی‌های ایندکس
 */
function hodima_seo_advisor_task_unseen_verdict( array $task, array $unseen, array $records ): string {

	$record = $records[ $task['path'] ] ?? null;

	if ( ! isset( $unseen[ $task['path'] ] ) || ( null !== $record && (int) ( $record['checked'] ?? 0 ) >= $task['at'] && 'indexed' === hodima_seo_advisor_inspect_state( $record ) ) ) {
		return 'fixed';
	}

	return time() - $task['at'] >= HODIMA_SEO_ADVISOR_MARK_UNSEEN_DAYS * DAY_IN_SECONDS ? 'still' : 'wait';
}

/** کارهای تب «آدرس‌های خراب» با نتیجه ذخیره‌شده (بعد از هر ساخت آن، advisor-health.php). */
function hodima_seo_advisor_tasks_sync_health(): int {

	$health = hodima_seo_advisor_health();

	if ( (int) ( $health['computed'] ?? 0 ) <= 0 || ! hodima_seo_advisor_tasks_has( 'health' ) ) {
		return 0;
	}

	$bad = [];
	foreach ( (array) ( $health['dead'] ?? [] ) as $row ) {
		if ( in_array( (string) $row['state'], HODIMA_SEO_ADVISOR_HEALTH_BAD, true ) ) {
			$bad[ (string) $row['path'] ] = true;
		}
	}
	$unseen  = array_fill_keys( array_map( 'strval', array_column( (array) ( $health['unseen'] ?? [] ), 'path' ) ), true );
	$router  = ! empty( $health['router'] );
	$records = hodima_seo_advisor_inspections();

	return hodima_seo_advisor_tasks_judge(
		'health',
		static function ( array $task ) use ( $bad, $unseen, $router, $records ): string {
			if ( 'health_dead' === $task['rule'] ) {
				return ! $router ? 'wait' : ( isset( $bad[ $task['path'] ] ) ? 'still' : 'fixed' );
			}
			return hodima_seo_advisor_task_unseen_verdict( $task, $unseen, $records );
		}
	);
}

/**
 * کارهای «مشکل ایندکس» (و «صفحه بدون نمایش») با بررسی‌های ایندکس. ایندکس‌شده بعد از «انجام دادم» ← برطرف؛ گوگل
 * بعد از آن دوباره خزیده و هنوز ایندکس نکرده ← مانده؛ هنوز نخزیده ← منتظر (پیش از خزش، حکم «مانده» بی‌انصافی بود).
 */
function hodima_seo_advisor_tasks_sync_inspected(): int {

	$changed = 0;

	if ( hodima_seo_advisor_tasks_has( 'indexing' ) ) {
		$records  = hodima_seo_advisor_inspections();
		$changed += hodima_seo_advisor_tasks_judge(
			'indexing',
			static function ( array $task ) use ( $records ): string {
				$record = $records[ $task['path'] ] ?? null;
				if ( null === $record || (int) ( $record['checked'] ?? 0 ) < $task['at'] ) {
					return 'wait';
				}
				if ( 'indexed' === hodima_seo_advisor_inspect_state( $record ) ) {
					return 'fixed';
				}
				return 'yes' === hodima_seo_advisor_inspect_after( $task['path'], $task['at'] )['state'] ? 'still' : 'wait';
			}
		);
	}

	return $changed + hodima_seo_advisor_tasks_sync_health();
}

/** کارهای صفحه‌های هم‌کلمه با گروه‌های داده فعلی (نوبت verify دریافت روزانه). */
function hodima_seo_advisor_tasks_sync_competition(): int {

	if ( ! hodima_seo_advisor_tasks_has( 'competition' ) ) {
		return 0;
	}

	$shares = [];
	foreach ( hodima_seo_advisor_competition_groups()['groups'] as $group ) {
		$shares[ hodima_seo_advisor_normalize( (string) $group['term'] ) ] = (float) max( array_column( $group['pages'], 'share' ) );
	}

	return hodima_seo_advisor_tasks_judge(
		'competition',
		static function ( array $task ) use ( $shares ): string {
			$share = $shares[ hodima_seo_advisor_normalize( $task['term'] ) ] ?? null;
			if ( null === $share || $share >= HODIMA_SEO_ADVISOR_MARK_COMPETITION_SHARE ) {
				return 'fixed';
			}
			return time() - $task['at'] >= HODIMA_SEO_ADVISOR_MARK_COMPETITION_DAYS * DAY_IN_SECONDS ? 'still' : 'wait';
		}
	);
}

/**
 * کار بسته (منتظر نتیجه، برطرف، رد شد) یک مورد، وگرنه null. فهرست تب‌ها این‌ها را پنهان می‌کنند.
 *
 * @return array<string, mixed>|null
 */
function hodima_seo_advisor_task_closed( string $path, string $rule, string $term = '' ): ?array {
	$task = hodima_seo_advisor_tasks()[ hodima_seo_advisor_task_key( $path, $rule, $term ) ] ?? null;
	return null !== $task && in_array( hodima_seo_advisor_task_state( $task ), HODIMA_SEO_ADVISOR_TASKS_CLOSED, true ) ? $task : null;
}

/**
 * کارهای صفحه‌های هم‌کلمه با کلید کلمه یکسان‌شده (مسیر برنده ممکن است بعدا عوض شود).
 *
 * @return array<string, array<string, mixed>>
 */
function hodima_seo_advisor_competition_tasks(): array {
	$out = [];
	foreach ( hodima_seo_advisor_tasks() as $task ) {
		if ( 'competition' === $task['rule'] ) {
			$out[ hodima_seo_advisor_normalize( $task['term'] ) ] = $task;
		}
	}
	return $out;
}

/**
 * موردهای فرم یک ردیف ← کارها (قاعده، کلمه، عنوان). عنوان از داده خود سایت، نه از فرم.
 *
 * @param list<string> $items
 * @return list<array{rule: string, term: string, title: string}>
 */
function hodima_seo_advisor_mark_items( string $source, string $path, array $items, string $term ): array {

	$out = [];

	switch ( $source ) {
		case 'opportunities':
			$row = hodima_seo_advisor_page( $path );
			foreach ( null !== $row ? (array) $row['advice'] : [] as $item ) {
				if ( in_array( (string) $item['rule'] . '|' . (string) $item['term'], $items, true ) ) {
					$out[] = [ 'rule' => (string) $item['rule'], 'term' => (string) $item['term'], 'title' => (string) $item['title'] ];
				}
			}
			break;
		case 'crawl':
			foreach ( array_unique( $items ) as $code ) {
				$info = hodima_seo_advisor_crawl_issue_info( $code );
				if ( '' !== $info['fix'] ) {
					$out[] = [ 'rule' => 'crawl_' . $code, 'term' => '', 'title' => $info['label'] ];
				}
			}
			break;
		case 'competition':
			if ( '' !== $term ) {
				$out[] = [ 'rule' => 'competition', 'term' => $term, 'title' => sprintf( 'رقابت صفحه‌ها برای «%s»', $term ) ];
			}
			break;
		case 'health':
			if ( in_array( 'dead', $items, true ) ) {
				$out[] = [ 'rule' => 'health_dead', 'term' => '', 'title' => 'آدرس مرده در گوگل' ];
			} elseif ( in_array( 'unseen', $items, true ) ) {
				$out[] = [ 'rule' => 'health_unseen', 'term' => '', 'title' => 'صفحه بدون نمایش در گوگل' ];
			}
			break;
		case 'indexing':
			$record = hodima_seo_advisor_inspections()[ $path ] ?? null;
			if ( null !== $record ) {
				$state  = hodima_seo_advisor_inspect_state( $record );
				$label  = function_exists( 'hodima_seo_advisor_inspect_state_info' ) ? hodima_seo_advisor_inspect_state_info( $state )['label'] : $state;
				$out[]  = [ 'rule' => 'index_problem', 'term' => '', 'title' => sprintf( 'ایندکس: %s', $label ) ];
			}
			break;
	}

	return $out;
}

/**
 * سنجش فوری بعد از «انجام دادم»: هر منبع آنچه همین الان سنجیدنی است (بالای فایل). کارهای همان موردها را تازه برمی‌گرداند.
 *
 * @param list<array{rule: string, term: string}> $marks
 * @return list<array<string, mixed>>
 */
function hodima_seo_advisor_mark_check_now( string $source, string $path, array $marks ): array {

	switch ( $source ) {
		case 'crawl':
			$codes  = array_map( static fn( array $m ): string => substr( $m['rule'], 6 ), $marks );
			$live   = array_intersect( $codes, HODIMA_SEO_ADVISOR_MARK_LIVE_CODES );
			$broken = in_array( 'broken', $codes, true );
			if ( $live || $broken ) {
				// ۲۰۰: متنی‌ها و خطا هر دو سنجیدنی‌اند (live_fetch خودش می‌سنجد)؛ ریدایرکت/خطا: فقط خطای ۴۰۴/۵۰۰ (صفحه متن ندارد)
				$code = hodima_seo_advisor_live_fetch( $path, $broken );
				if ( $code > 200 ) {
					hodima_seo_advisor_tasks_sync_crawl( $path, [ 'broken' ] );
				}
			}
			break;
		case 'health':
			if ( in_array( 'health_dead', array_column( $marks, 'rule' ), true ) && hodima_seo_advisor_health_router_ready() ) {
				$check  = hodima_seo_advisor_health_dead_state( $path );
				$health = hodima_seo_advisor_health();
				// فهرست ذخیره‌شده هم همان لحظه درست شود (تا دریافت بعدی آدرس سالم «مرده» نماند)
				foreach ( (array) ( $health['dead'] ?? [] ) as $i => $row ) {
					if ( (string) $row['path'] === $path ) {
						$health['dead'][ $i ] = [ 'state' => $check['state'], 'target' => $check['target'], 'code' => $check['code'] ] + $row;
						if ( '' === $check['state'] ) {
							unset( $health['dead'][ $i ] );
						}
					}
				}
				$health['dead'] = array_values( (array) ( $health['dead'] ?? [] ) );
				update_option( HODIMA_SEO_ADVISOR_HEALTH_OPTION, $health, false );
				hodima_seo_advisor_tasks_judge( 'health', static fn(): string => in_array( $check['state'], HODIMA_SEO_ADVISOR_HEALTH_BAD, true ) ? 'still' : 'fixed', [ $path ], 'health_dead' );
			} else {
				hodima_seo_advisor_tasks_sync_health(); // صفحه‌ای که همین حالا نمایش گرفته یا ایندکس شده
			}
			break;
		case 'indexing':
			hodima_seo_advisor_tasks_sync_inspected();
			break;
	}

	$tasks = hodima_seo_advisor_tasks();

	return array_values( array_filter( array_map( static fn( array $m ): ?array => $tasks[ hodima_seo_advisor_task_key( $path, $m['rule'], $m['term'] ) ] ?? null, $marks ) ) );
}

/**
 * جمله نتیجه سنجش فوری برای پیام بالای صفحه (HTML امن).
 *
 * @param list<array<string, mixed>> $tasks
 */
function hodima_seo_advisor_mark_result_text( array $tasks ): string {

	$n      = static fn( int $v ): string => number_format_i18n( $v );
	$counts = [ 'fixed' => 0, 'still' => 0, 'waiting' => 0 ];
	$wait   = [];

	foreach ( $tasks as $task ) {
		$state = hodima_seo_advisor_task_state( $task );
		if ( 'fixed' === $state ) {
			++$counts['fixed'];
		} elseif ( in_array( $state, [ 'still', 'back' ], true ) ) {
			++$counts['still'];
		} elseif ( 'waiting' === $state || 'unseen' === $state ) {
			++$counts['waiting'];
			$wait[] = match ( $task['rule'] ) {
				'competition'                    => 'با داده تازه گوگل تا ۱۴ روز دیگر سنجیده می‌شود',
				'health_unseen', 'index_problem' => 'آدرس اول صف بررسی ایندکس گوگل رفت',
				'health_dead'                    => 'با دریافت بعدی سنجیده می‌شود',
				default                          => str_starts_with( $task['rule'], 'crawl_' )
					? ( in_array( substr( $task['rule'], 6 ), [ ...HODIMA_SEO_ADVISOR_MARK_LIVE_CODES, 'broken' ], true ) ? 'صفحه همین حالا از سایت خوانده نشد؛ با خزش تازه سنجیده می‌شود' : 'کند بودن، لینک ورودی و ریدایرکت با خزش تازه سنجیده می‌شوند' )
					: 'با ذخیره صفحه یا داده بعدی گوگل سنجیده می‌شود',
			};
		}
	}

	$parts = [];
	if ( $counts['fixed'] > 0 ) {
		$parts[] = sprintf( '%s برطرف شد', $n( $counts['fixed'] ) );
	}
	if ( $counts['still'] > 0 ) {
		$parts[] = sprintf( '%s هنوز دیده می‌شود (به «کارها» ← «نیاز به کار» رفت)', $n( $counts['still'] ) );
	}
	if ( $counts['waiting'] > 0 ) {
		$parts[] = sprintf( '%1$s منتظر نتیجه (%2$s)', $n( $counts['waiting'] ), implode( '؛ ', array_unique( $wait ) ) );
	}

	return $parts ? esc_html( implode( '، ', $parts ) . '.' ) : '';
}

/**
 * فرم «انجام شد / رد شد» یک ردیف (POST). items[]: موردهای تیک‌خورده (پیشنهاد: «قاعده|کلمه»، خزش: کد ایراد).
 */
function hodima_seo_advisor_handle_mark(): void {

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'اجازه این کار را ندارید.', 'دسترسی ندارید', [ 'response' => 403 ] );
	}

	check_admin_referer( 'hodima_advisor_mark' );

	// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce بالا بررسی شد
	$source = sanitize_key( (string) ( $_POST['source'] ?? '' ) );
	$path   = sanitize_text_field( wp_unslash( (string) ( $_POST['path'] ?? '' ) ) );
	$term   = sanitize_text_field( wp_unslash( (string) ( $_POST['term'] ?? '' ) ) );
	$status = sanitize_key( (string) ( $_POST['status'] ?? '' ) );
	$items  = array_map( static fn( mixed $v ): string => sanitize_text_field( wp_unslash( (string) $v ) ), is_array( $_POST['items'] ?? null ) ? $_POST['items'] : [] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- هر مورد در array_map پاک‌سازی می‌شود
	// phpcs:enable WordPress.Security.NonceVerification.Missing

	$back = wp_get_referer();
	$back = is_string( $back ) && '' !== $back ? $back : hodima_seo_advisor_page_url( [ 'tab' => $source ] );
	$done = hodima_seo_advisor_page_url( [ 'tab' => 'tasks', 'tstatus' => 'waiting' ] );

	$marks = isset( hodima_seo_advisor_task_sources()[ $source ] ) && in_array( $status, [ 'done', 'rejected' ], true ) && '' !== $path
		? hodima_seo_advisor_mark_items( $source, $path, $items, $term )
		: [];

	if ( ! $marks ) {
		hodima_admin_flash( 'چیزی ثبت نشد: هیچ موردی تیک نخورده بود یا ردیف دیگر وجود ندارد.', 'warning' );
		wp_safe_redirect( $back );
		exit;
	}

	// رقابت یک کلمه فقط یک کار دارد؛ اگر برنده عوض شده بود، کار قبلی همان کلمه جای خود را به این می‌دهد
	if ( 'competition' === $source ) {
		foreach ( hodima_seo_advisor_tasks() as $task ) {
			if ( 'competition' === $task['rule'] && $task['path'] !== $path && hodima_seo_advisor_normalize( $task['term'] ) === hodima_seo_advisor_normalize( $term ) ) {
				hodima_seo_advisor_task_set( $task['path'], 'competition', $task['term'], '', '' );
			}
		}
	}

	foreach ( $marks as $mark ) {
		hodima_seo_advisor_task_set( $path, $mark['rule'], $mark['term'], $mark['title'], $status, true );
	}

	$n = number_format_i18n( count( $marks ) );

	if ( 'done' === $status ) {
		$result = 'opportunities' === $source ? '' : hodima_seo_advisor_mark_result_text( hodima_seo_advisor_mark_check_now( $source, $path, $marks ) );
		hodima_admin_flash( sprintf( '<strong>%1$s مورد «انجام دادم» ثبت شد.</strong> %2$s <a href="%3$s">دیدن در «کارها»</a>', esc_html( $n ), $result, esc_url( $done ) ), 'success' );
	} else {
		hodima_admin_flash( sprintf( '<strong>%1$s مورد «لازم نیست» ثبت شد</strong> و از این فهرست کنار رفت. <a href="%2$s">دیدن در «کارها»</a>', esc_html( $n ), esc_url( add_query_arg( 'tstatus', 'rejected', $done ) ) ), 'success' );
	}

	wp_safe_redirect( $back );
	exit;
}
