<?php
/**
 * ماژول «دستار سئو» — وضعیت دستی پیشنهادها («کارهای من») و اندازه‌گیری اثر
 * Path: core/advisor/advisor-tasks.php
 *
 * مشاور چیزی را خودکار عوض نمی‌کند؛ مدیر هر پیشنهاد را خودش اجرا می‌کند. این فایل فقط
 * «یادداشت» او را نگه می‌دارد: برای هر پیشنهاد (آدرس + نوع + کلمه) یکی از وضعیت‌های «در حال انجام»،
 * «انجام دادم» یا «لازم نیست». برای «انجام دادم» با تاریخچه هفتگی (advisor-history.php) اثرش اندازه می‌شود:
 * میانگین کلیک هفتگی تا ۳ هفته کامل قبل از آن روز با تا ۳ هفته کامل بعدش.
 *
 * داده: گزینه hodima_advisor_tasks (autoload خاموش، حداکثر ۱۰۰۰ مورد؛ از پرشدن، قدیمی‌ترین «لازم نیست»ها
 * اول می‌روند). «پاک کردن داده‌های مشاور» آن را پاک نمی‌کند (یادداشت مدیر است، نه داده گوگل).
 *
 * SEO 3.0.2: کار فقط پیشنهاد کادر ویرایش نیست؛ هر تب (ایرادهای فنی، هم‌کلمه، آدرس‌های خراب، وضعیت در گوگل)
 * کار خودش را با قاعده جدا می‌نویسد (hodima_seo_advisor_task_source، advisor-marks.php) و سنجش هر نوع همان‌جاست.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

const HODIMA_SEO_ADVISOR_TASKS_OPTION = 'hodima_advisor_tasks';
const HODIMA_SEO_ADVISOR_TASKS_MAX    = 1000; // تا SEO 3.0.1: ۵۰۰ (فقط پیشنهادها)؛ حالا ایرادهای فنی هر ایراد یک کار است

add_action( 'admin_post_hodima_advisor_task', 'hodima_seo_advisor_handle_task' );

/**
 * چرخه عمر هر کار (SEO 2.9.1): وضعیت دستی مدیر (doing/done/rejected) + آنچه تحلیل بعدی دید.
 *   doing    در حال انجام                       (باز)
 *   waiting  انجام شد؛ ایراد آماری منتظر داده گوگل (بسته)
 *   unseen   انجام شد؛ ایراد متنی هنوز در صفحه   (باز: تغییر دیده نشد)
 *   fixed    برطرف شد: تحلیل دیگر ایراد متنی را نمی‌سازد، یا سنجش داده بعد از تغییر «اثر داشت» گفت (بسته؛ ناپدید شدن پیشنهاد آماری به‌تنهایی کافی نیست — SEO 3.0.3)
 *   back     برگشت: بعد از برطرف شدن دوباره پیدا شد (باز)
 *   no_effect اثر نداشت: داده گوگل بعد از تغییر بهتر نشد (باز؛ SEO 2.9.2، advisor-verify.php)
 *   still    انجام شد؛ سنجش نوع خودش نشان داد هنوز مانده (باز؛ SEO 3.0.2، advisor-marks.php)
 *   rejected رد شد                               (بسته)
 * «برطرف شد/برگشت» را فقط hodima_seo_advisor_tasks_sync() بعد از تحلیل واقعی یک آدرس می‌نویسد؛ «اثر داشت/نداشت»
 * را hodima_seo_advisor_verify_task() از داده سرچ کنسولِ فقط بعد از تاریخ تغییر (فیلد check).
 */
const HODIMA_SEO_ADVISOR_TASKS_CLOSED = [ 'waiting', 'fixed', 'rejected' ];

/** پیشنهادهای متنی: با ذخیره صفحه همان لحظه دوباره سنجیده می‌شوند (advisor-resave.php)؛ بقیه به داده بعدی گوگل بسته‌اند. */
const HODIMA_SEO_ADVISOR_TEXT_RULES = [ 'missing_headings', 'missing_content' ];

/**
 * نام nonce لینک تغییر وضعیت: وابسته به خود کار و وضعیت. لینک GET با nonce عمومی برای هر کار و وضعیتی قابل‌استفاده بود
 * (لینکی که در تاریخچه مرورگر یا سربرگ Referer بماند هر کاری را برای آن کاربر ثبت می‌کرد).
 */
function hodima_seo_advisor_task_nonce_action( string $path, string $rule, string $term, string $status, bool $redo ): string {
	return 'hodima_advisor_task:' . md5( implode( '|', [ sanitize_text_field( $path ), sanitize_key( $rule ), sanitize_text_field( $term ), sanitize_key( $status ), $redo ? '1' : '0' ] ) );
}

/** @return array<string, string> وضعیت ← برچسب */
function hodima_seo_advisor_task_statuses(): array {
	return [ 'doing' => 'در حال انجام', 'done' => 'انجام دادم', 'rejected' => 'لازم نیست' ];
}

/** کلید یکتای یک پیشنهاد. */
function hodima_seo_advisor_task_key( string $path, string $rule, string $term ): string {
	return substr( md5( $path . '|' . $rule . '|' . hodima_seo_advisor_normalize( $term ) ), 0, 16 );
}

/**
 * همه کارها (کلید ← کار).
 *
 * @return array<string, array{path: string, rule: string, term: string, title: string, status: string, at: int, fixed: int, back: int, check: array<string, mixed>}>
 */
function hodima_seo_advisor_tasks(): array {

	// نتیجه تا وقتی گزینه همان است دوباره ساخته نمی‌شود: فهرست‌ها برای هر ردیف کار را می‌پرسند (۱۰۰۰ کار × ده‌ها ردیف)
	static $raw = null, $cache = [];

	$stored = get_option( HODIMA_SEO_ADVISOR_TASKS_OPTION, [] );

	if ( $stored === $raw ) {
		return $cache;
	}

	$out = [];

	foreach ( is_array( $stored ) ? $stored : [] as $key => $task ) {
		if ( is_string( $key ) && is_array( $task ) && isset( hodima_seo_advisor_task_statuses()[ (string) ( $task['status'] ?? '' ) ] ) ) {
			$out[ $key ] = [
				'path'   => (string) ( $task['path'] ?? '' ),
				'rule'   => (string) ( $task['rule'] ?? '' ),
				'term'   => (string) ( $task['term'] ?? '' ),
				'title'  => (string) ( $task['title'] ?? '' ),
				'status' => (string) $task['status'],
				'at'     => (int) ( $task['at'] ?? 0 ),
				'fixed'  => (int) ( $task['fixed'] ?? 0 ), // زمان «برطرف شد» (صفر = نه)
				'back'   => (int) ( $task['back'] ?? 0 ),  // زمان «برگشت» (صفر = نه)
				'check'  => is_array( $task['check'] ?? null ) ? $task['check'] : [], // سنجش بعد از تغییر (advisor-verify.php، advisor-marks.php)
			];
		}
	}

	$raw   = $stored;
	$cache = $out;

	return $out;
}

/**
 * تغییر فیلدهای چند کار را روی نسخه تازه گزینه می‌نشاند، نه روی نسخه‌ای که پیش از درخواست‌های کند (سرچ کنسول) خوانده شد.
 * تا SEO 3.0.2 نوبت سنجش کل آرایه را بعد از چند ثانیه می‌نوشت و «انجام دادم» که مدیر وسط آن می‌زد بی‌صدا پاک می‌شد.
 * کاری که مدیر در این فاصله عوضش کرد (وضعیت یا زمان `at` فرق دارد) یا برداشت دست نمی‌خورد.
 *
 * @param array<string, array<string, mixed>> $patch  کلید کار ← فیلدهای تازه (fixed، back، check)
 * @param array<string, array{at: int, status: string}> $expect وضعیت کار هنگام خواندن
 * @return int شمار کارهای نوشته‌شده
 */
function hodima_seo_advisor_tasks_patch( array $patch, array $expect ): int {

	wp_cache_delete( HODIMA_SEO_ADVISOR_TASKS_OPTION, 'options' ); // کش شیء این پردازش کهنه است
	$fresh   = hodima_seo_advisor_tasks();
	$written = 0;

	foreach ( $patch as $key => $fields ) {
		if ( isset( $fresh[ $key ], $expect[ $key ] ) && $fresh[ $key ]['at'] === $expect[ $key ]['at'] && $fresh[ $key ]['status'] === $expect[ $key ]['status'] ) {
			$fresh[ $key ] = $fields + $fresh[ $key ];
			++$written;
		}
	}

	if ( $written > 0 ) {
		update_option( HODIMA_SEO_ADVISOR_TASKS_OPTION, $fresh, false );
	}

	return $written;
}

/**
 * کار یک پیشنهاد، یا null.
 *
 * @return array{path: string, rule: string, term: string, title: string, status: string, at: int, fixed: int, back: int, check: array<string, mixed>}|null
 */
function hodima_seo_advisor_task( string $path, string $rule, string $term ): ?array {
	return hodima_seo_advisor_tasks()[ hodima_seo_advisor_task_key( $path, $rule, $term ) ] ?? null;
}

/**
 * وضعیت یک پیشنهاد را می‌گذارد؛ وضعیت خالی = برداشتن از فهرست. `$restart` («دوباره انجام دادم»): همان وضعیت
 * ولی تاریخ تازه و سنجش از نو (کار «اثر نداشت» یا «برگشت» که مدیر دوباره روی آن کار کرده).
 */
function hodima_seo_advisor_task_set( string $path, string $rule, string $term, string $title, string $status, bool $restart = false ): bool {

	if ( '' !== $status && ! isset( hodima_seo_advisor_task_statuses()[ $status ] ) ) {
		return false;
	}

	if ( '' === $path || 1 !== preg_match( '/^[a-z0-9_]+$/', $rule ) ) { // رقم برای crawl_no_h1 و crawl_dup_h1
		return false;
	}

	$tasks = hodima_seo_advisor_tasks();
	$key   = hodima_seo_advisor_task_key( $path, $rule, $term );

	if ( '' === $status ) {
		unset( $tasks[ $key ] );
	} else {
		$prev          = $tasks[ $key ] ?? null;
		$old           = $restart ? null : $prev; // «دوباره انجام دادم»: مثل کار تازه، فقط عنوان قبلی می‌ماند
		$tasks[ $key ] = [
			'path'   => $path,
			'rule'   => $rule,
			'term'   => $term,
			'title'  => mb_substr( $title, 0, 200 ) ?: ( $prev['title'] ?? '' ),
			'status' => $status,
			'at'     => null !== $old && $old['status'] === $status ? $old['at'] : time(), // تاریخ همان وضعیت می‌ماند
			// وضعیت تازه = تصمیم تازه مدیر؛ «برطرف شد/برگشت» قبلی را تحلیل بعدی دوباره می‌سنجد
			'fixed'  => null !== $old && $old['status'] === $status ? $old['fixed'] : 0,
			'back'   => null !== $old && $old['status'] === $status ? $old['back'] : 0,
			'check'  => null !== $old && $old['status'] === $status ? $old['check'] : [],
		];
	}

	if ( count( $tasks ) > HODIMA_SEO_ADVISOR_TASKS_MAX ) {
		uasort( $tasks, static fn( array $a, array $b ): int => [ 'rejected' === $a['status'], -$a['at'] ] <=> [ 'rejected' === $b['status'], -$b['at'] ] ); // اول غیر«لازم نیست»ها، تازه‌ترین‌ها
		$tasks = array_slice( $tasks, 0, HODIMA_SEO_ADVISOR_TASKS_MAX, true );
	}

	update_option( HODIMA_SEO_ADVISOR_TASKS_OPTION, $tasks, false );

	return true;
}

/**
 * وضعیت چرخه عمر یک کار (بالای فایل).
 *
 * @param array{rule: string, status: string, fixed: int, back: int, check?: array<string, mixed>} $task
 */
function hodima_seo_advisor_task_state( array $task ): string {

	if ( 'rejected' === $task['status'] ) {
		return 'rejected';
	}
	// سنجش مستقیم بعد از تغییر بر جمع ۹۰ روزه مقدم است: پیشنهادی که از جمع افتاده ولی داده بعد از تغییر بهتر نشده «برطرف» نیست
	if ( 'done' === $task['status'] && 'no_effect' === ( $task['check']['verdict'] ?? '' ) ) {
		return 'no_effect';
	}
	if ( $task['fixed'] > 0 ) {
		return 'fixed';
	}
	if ( $task['back'] > 0 ) {
		return 'back';
	}
	if ( 'doing' === $task['status'] ) {
		return 'doing';
	}
	if ( 'still' === ( $task['check']['verdict'] ?? '' ) ) {
		return 'still';
	}

	return in_array( $task['rule'], HODIMA_SEO_ADVISOR_TEXT_RULES, true ) ? 'unseen' : 'waiting';
}

/**
 * برچسب، کلاس نشان و زمان هر وضعیت چرخه عمر.
 *
 * @param array{path: string, rule: string, status: string, at: int, fixed: int, back: int, check?: array<string, mixed>} $task
 * @return array{state: string, label: string, class: string, time: int}
 */
function hodima_seo_advisor_task_view( array $task ): array {

	$state = hodima_seo_advisor_task_state( $task );

	[ $label, $class ] = match ( $state ) {
		'doing'   => [ 'در حال انجام', 'hds-pill hds-pill--warn' ],
		'waiting' => [ hodima_seo_advisor_task_wait_label( $task ), 'hds-pill' ],
		'still'   => [ hodima_seo_advisor_task_still_label( $task['rule'] ), 'hds-pill hds-pill--warn' ],
		'no_effect' => [ 'اثر نداشت؛ قدم بعدی را ببینید', 'hds-pill hds-pill--warn' ],
		'unseen'  => [ 'انجام شد؛ هنوز در صفحه دیده نمی‌شود', 'hds-pill hds-pill--warn' ],
		'fixed'   => [ 'برطرف شد', 'hds-pill' ],
		'back'    => [ 'برگشت؛ دوباره پیدا شد', 'hds-pill hds-pill--error' ],
		default   => [ 'لازم نیست', 'hds-pill hds-pill--info' ],
	};

	return [ 'state' => $state, 'label' => $label, 'class' => $class, 'time' => match ( $state ) { 'fixed' => $task['fixed'], 'back' => $task['back'], default => $task['at'] } ];
}

/**
 * بعد از تحلیل واقعی یک آدرس: کارهای باز آن (در حال انجام / انجام شد) با پیشنهادهای تازه سنجیده می‌شوند.
 * پیشنهادی که دیگر ساخته نمی‌شود ← «برطرف شد»؛ پیشنهاد برطرف‌شده‌ای که دوباره آمده ← «برگشت».
 * آدرس نادیده‌گرفته‌شده سنجیده نمی‌شود (پیشنهاد ندارد ولی چیزی برطرف نشده).
 *
 * @param list<array<string, mixed>> $suggestions پیشنهادهای تازه همان آدرس
 * @return int شمار کارهایی که وضعیتشان عوض شد
 */
function hodima_seo_advisor_tasks_sync( string $path, array $suggestions ): int {

	$tasks = hodima_seo_advisor_tasks();

	if ( ! $tasks || in_array( $path, hodima_seo_advisor_ignored(), true ) ) {
		return 0;
	}

	$present = [];
	foreach ( $suggestions as $item ) {
		$present[ hodima_seo_advisor_task_key( $path, (string) ( $item['rule'] ?? '' ), (string) ( $item['term'] ?? '' ) ) ] = true;
	}

	$changed = 0;
	$now     = time();
	$patch   = [];
	$expect  = [];

	foreach ( $tasks as $key => $task ) {
		// فقط کار پیشنهادها؛ کار تب‌های دیگر (ایراد فنی همین آدرس و…) سنجش خودش را دارد و اینجا «برطرف» نمی‌شد
		if ( $task['path'] !== $path || 'rejected' === $task['status'] || 'opportunities' !== hodima_seo_advisor_task_source( $task['rule'] ) ) {
			continue;
		}
		// پیشنهاد آماری ممکن است فقط چون داده یا آستانه عوض شد از فهرست برود (بازه ۹۰ روزه جابه‌جا شد، کلمه زیر ۳۰۰ نمایش افتاد)؛
		// آن را «برطرف شد» نشماریم. برطرف‌شدنش فقط با سنجش داده بعد از تغییر است (advisor-verify.php)
		if ( ! isset( $present[ $key ] ) && 0 === $task['fixed'] && ! in_array( $task['rule'], HODIMA_SEO_ADVISOR_VERIFY_RULES, true ) ) {
			$patch[ $key ] = [ 'fixed' => $now, 'back' => 0 ];
		} elseif ( isset( $present[ $key ] ) && $task['fixed'] > 0 ) {
			$patch[ $key ] = [ 'fixed' => 0, 'back' => $now ];
		} else {
			continue;
		}

		$expect[ $key ] = [ 'at' => $task['at'], 'status' => $task['status'] ];
		++$changed;
	}

	if ( $patch ) {
		hodima_seo_advisor_tasks_patch( $patch, $expect ); // نسخه تازه گزینه، نه کپی ابتدای تحلیل
	}

	return $changed;
}

/**
 * آدرس‌هایی که همه پیشنهادهای فعلی‌شان «انجام دادم» یا «لازم نیست» خورده‌اند (مسیر ← نوع شیء). فهرست فرصت‌ها
 * پیش‌فرض این‌ها را پنهان می‌کند تا فقط کارهای باز بماند؛ پیشنهاد تازه‌ای که بعدا برای همان آدرس ساخته شود
 * علامت ندارد و آدرس خودبه‌خود به فهرست برمی‌گردد.
 *
 * @return array<string, string>
 */
function hodima_seo_advisor_handled_paths(): array {

	$tasks = hodima_seo_advisor_tasks();
	$paths = [];

	foreach ( $tasks as $task ) {
		if ( 'opportunities' === hodima_seo_advisor_task_source( $task['rule'] ) && in_array( hodima_seo_advisor_task_state( $task ), HODIMA_SEO_ADVISOR_TASKS_CLOSED, true ) ) {
			$paths[ $task['path'] ] = true;
		}
	}

	if ( ! $paths ) {
		return [];
	}

	$out = [];

	foreach ( hodima_seo_advisor_pages( [ 'paths' => array_keys( $paths ), 'has_advice' => true, 'limit' => HODIMA_SEO_ADVISOR_TASKS_MAX ] )['rows'] as $row ) {
		$path = (string) $row['path'];
		$open = array_filter(
			(array) $row['advice'],
			static function ( array $item ) use ( $tasks, $path ): bool {
				$task = $tasks[ hodima_seo_advisor_task_key( $path, (string) $item['rule'], (string) $item['term'] ) ] ?? null;
				return null === $task || ! in_array( hodima_seo_advisor_task_state( $task ), HODIMA_SEO_ADVISOR_TASKS_CLOSED, true );
			}
		);
		if ( ! $open ) {
			$out[ $path ] = (string) $row['kind'];
		}
	}

	return $out;
}

/**
 * گروه‌های تب «کارهای من» (چرخه عمر): گروه ← [برچسب، وضعیت‌ها].
 *
 * @return array<string, array{0: string, 1: list<string>}>
 */
function hodima_seo_advisor_task_groups(): array {
	return [
		'open'     => [ 'نیاز به کار', [ 'doing', 'unseen', 'back', 'no_effect', 'still' ] ],
		'waiting'  => [ 'منتظر نتیجه', [ 'waiting' ] ],
		'fixed'    => [ 'برطرف شد', [ 'fixed' ] ],
		'rejected' => [ 'لازم نیست', [ 'rejected' ] ],
	];
}

/**
 * گروه چرخه عمر یک کار (کلید hodima_seo_advisor_task_groups).
 *
 * @param array{rule: string, status: string, fixed: int, back: int, check?: array<string, mixed>} $task
 */
function hodima_seo_advisor_task_group( array $task ): string {

	$state = hodima_seo_advisor_task_state( $task );

	foreach ( hodima_seo_advisor_task_groups() as $group => [ , $states ] ) {
		if ( in_array( $state, $states, true ) ) {
			return $group;
		}
	}

	return 'open';
}

/**
 * شمار کارها به تفکیک گروه چرخه عمر.
 *
 * @return array<string, int>
 */
function hodima_seo_advisor_task_counts(): array {

	$counts = array_fill_keys( array_keys( hodima_seo_advisor_task_groups() ), 0 );

	foreach ( hodima_seo_advisor_tasks() as $task ) {
		++$counts[ hodima_seo_advisor_task_group( $task ) ];
	}

	return $counts;
}

/**
 * اثر یک کار «انجام دادم» روی کلیک هفتگی صفحه.
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

	$path   = sanitize_text_field( wp_unslash( (string) ( $_REQUEST['path'] ?? '' ) ) );
	$rule   = sanitize_key( (string) ( $_REQUEST['rule'] ?? '' ) );
	$term   = sanitize_text_field( wp_unslash( (string) ( $_REQUEST['term'] ?? '' ) ) );
	$title  = sanitize_text_field( wp_unslash( (string) ( $_REQUEST['title'] ?? '' ) ) );
	$status = sanitize_key( (string) ( $_REQUEST['status'] ?? '' ) );
	$redo   = ! empty( $_REQUEST['redo'] );

	// فرم POST تب «کارها» nonce عمومی دارد؛ لینک GET (کادر ویرایش) فقط برای همین کار و همین وضعیت معتبر است
	check_admin_referer( empty( $_POST ) ? hodima_seo_advisor_task_nonce_action( $path, $rule, $term, $status, $redo ) : 'hodima_advisor_task' );

	if ( hodima_seo_advisor_task_set( $path, $rule, $term, $title, $status, $redo ) ) {
		$note = '';
		// کار تب‌های دیگر (ایراد فنی، آدرس خراب…) با «انجام دادم»/«دوباره انجام دادم» همان لحظه سنجیده می‌شود
		if ( 'done' === $status && 'opportunities' !== hodima_seo_advisor_task_source( $rule ) ) {
			$note = hodima_seo_advisor_mark_result_text( hodima_seo_advisor_mark_check_now( hodima_seo_advisor_task_source( $rule ), $path, [ [ 'rule' => $rule, 'term' => $term ] ] ) );
		}
		hodima_admin_flash( ( '' === $status ? 'کار از فهرست شما برداشته شد.' : sprintf( 'وضعیت کار: «%s» ثبت شد.', hodima_seo_advisor_task_statuses()[ $status ] ) ) . ( '' !== $note ? ' ' . $note : '' ), 'success' );
	} else {
		hodima_admin_flash( 'وضعیت ثبت نشد؛ درخواست نامعتبر بود.', 'error' );
	}

	$back = wp_get_referer();
	wp_safe_redirect( is_string( $back ) && '' !== $back ? $back : admin_url( 'admin.php?page=' . HODIMA_SEO_ADVISOR_PAGE . '&tab=tasks' ) );
	exit;
}
