<?php
/**
 * ماژول «دستار سئو» — نمای «کارهای من»: دکمه‌های وضعیت در کادر ویرایش و تب فهرست کارها
 * Path: core/advisor/advisor-tasks-view.php
 *
 * داده از advisor-tasks.php. تب «کارهای من»: ۴ کارت آمار ← کارت با نوار نماها (وضعیت با شمار) ←
 * ردیف هر کار (پیشنهاد، صفحه، وضعیت و تاریخ شمسی، اثر اندازه‌گرفته‌شده، دکمه‌های تغییر وضعیت).
 * فقط یادداشت مدیر است؛ چیزی را در سایت عوض نمی‌کند.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** کار در هر صفحه تب‌های «در دست انجام» و «انجام‌شده». */
const HODIMA_SEO_ADVISOR_TASKS_PER_PAGE = 25;

/** تاریخ کوتاه شمسی (Core) یا میلادی. */
function hodima_seo_advisor_task_date( int $time ): string {
	return function_exists( 'hodima_jalali_format' ) ? hodima_jalali_format( 'j F Y', $time + (int) ( (float) get_option( 'gmt_offset', 0 ) * HOUR_IN_SECONDS ) ) : (string) wp_date( 'Y/m/d', $time );
}

/** آدرس تغییر وضعیت (GET با nonce؛ کادر ویرایش داخل فرم وردپرس است و فرم تودرتو نمی‌شود). */
function hodima_seo_advisor_task_url( string $path, string $rule, string $term, string $title, string $status, bool $redo = false ): string {
	return wp_nonce_url(
		add_query_arg( [ 'action' => 'hodima_advisor_task', 'path' => $path, 'rule' => $rule, 'term' => $term, 'title' => $title, 'status' => $status ] + ( $redo ? [ 'redo' => 1 ] : [] ), admin_url( 'admin-post.php' ) ),
		hodima_seo_advisor_task_nonce_action( $path, $rule, $term, $status, $redo )
	);
}

/* ── فرم «انجام شد / رد شد» ردیف تب‌ها (SEO 3.0.2؛ طرح تأییدشده کاربر ۶ آبان ۱۴۰۵) ──
 * هر ردیف یک فرم POST جدا (admin-post: hodima_advisor_mark، advisor-marks.php). تیک هر ایراد داخل خانه ایرادها و
 * دکمه‌ها در خانه کنش است؛ تیک‌ها با ویژگی form به فرم همان ردیف وصل‌اند (فرم دور ردیف جدول مجاز نیست). بدون JS کار
 * می‌کند. تیک‌ها از اول زده‌اند (معمولا همه ایرادهای یک صفحه با هم درست می‌شوند)؛ تیک کار انجام‌نشده برداشته می‌شود. */

/** شناسه یکتای فرم ردیف (تیک‌ها پیش از خود فرم چاپ می‌شوند و شناسه را لازم دارند). */
function hodima_seo_advisor_mark_id(): string {
	static $n = 0;
	return 'hodima-advisor-mark-' . ( ++$n );
}

/**
 * تیک یک مورد که به فرم ردیف وصل است.
 *
 * @param string $label_html متن برچسب (HTML امن)؛ فقط متن و نشان، نه پیوند یا دکمه (عنصر برچسب‌پذیر داخل label نامعتبر است)
 */
function hodima_seo_advisor_mark_check( string $form, string $value, string $label_html, string $extra_class = '' ): string {
	return sprintf(
		'<label class="hodima-advisor__mark-item%1$s"><input type="checkbox" name="items[]" value="%2$s" form="%3$s" checked><span class="hodima-advisor__mark-text">%4$s</span></label>',
		'' !== $extra_class ? ' ' . esc_attr( $extra_class ) : '',
		esc_attr( $value ),
		esc_attr( $form ),
		$label_html
	);
}

/**
 * فرم دکمه‌های «انجام دادم» و «لازم نیست» یک ردیف.
 *
 * @param array<string, string> $hidden فیلدهای پنهان دیگر (term، items[] برای ردیف تک‌کاره)
 * @param string                $context نام ردیف برای صفحه‌خوان («انجام شد: …»)
 * @param bool                  $buttons false: فرم بی‌دکمه و پنهان؛ دکمه‌ها جای دیگر (منوی «⋯» ردیف) با ویژگی form به آن وصل‌اند (SEO 3.1.1)
 */
function hodima_seo_advisor_mark_form( string $id, string $source, string $path, string $context, array $hidden = [], bool $buttons = true ): void {

	static $nonce = null;
	$nonce ??= wp_create_nonce( 'hodima_advisor_mark' ); // یک بار؛ wp_nonce_field در ده‌ها فرم شناسه تکراری _wpnonce می‌ساخت
	?>
	<form id="<?php echo esc_attr( $id ); ?>" class="hodima-advisor__mark" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"<?php echo $buttons ? '' : ' hidden'; ?>>
		<input type="hidden" name="action" value="hodima_advisor_mark">
		<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( $nonce ); ?>">
		<?php wp_referer_field(); ?>
		<input type="hidden" name="source" value="<?php echo esc_attr( $source ); ?>">
		<input type="hidden" name="path" value="<?php echo esc_attr( $path ); ?>">
		<?php foreach ( $hidden as $name => $value ) : ?>
			<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>">
		<?php endforeach; ?>
		<?php if ( $buttons ) : ?>
		<button type="submit" name="status" value="done" class="hds-btn hds-btn--outline hodima-advisor__mark-done" aria-label="<?php echo esc_attr( 'انجام دادم: ' . $context ); ?>"><span class="dashicons dashicons-yes" aria-hidden="true"></span>انجام دادم</button>
		<button type="submit" name="status" value="rejected" class="hds-btn hds-btn--outline hodima-advisor__mark-reject" aria-label="<?php echo esc_attr( 'لازم نیست: ' . $context ); ?>">لازم نیست</button>
		<?php endif; ?>
	</form>
	<?php
}

/** فهرست، موردهای علامت‌خورده را هم نشان دهد؟ (`tasks=all`؛ همان پارامتر تب پیشنهادها) */
function hodima_seo_advisor_show_marked(): bool {
	return 'all' === ( isset( $_GET['tasks'] ) ? sanitize_key( (string) $_GET['tasks'] ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط نمای فهرست
}

/**
 * پیوند «نمایش N مورد علامت‌خورده» / «پنهان کردن…» پای فهرست.
 *
 * @param array<string, mixed> $keep پارامترهای فعلی نما (بدون tasks و paged)
 */
function hodima_seo_advisor_marked_toggle( int $hidden, array $keep, string $unit = 'مورد' ): string {

	$show = hodima_seo_advisor_show_marked();

	if ( ! $show && $hidden < 1 ) {
		return '';
	}

	return hodima_seo_advisor_action(
		hodima_seo_advisor_page_url( array_filter( [ 'tasks' => $show ? '' : 'all' ] + $keep ) ),
		$show ? 'پنهان کردن علامت‌خورده‌ها' : sprintf( 'نمایش %1$s %2$s علامت‌خورده', number_format_i18n( $hidden ), $unit ),
		$show ? 'hidden' : 'visibility'
	);
}

/**
 * نشان وضعیت کار کنار یک مورد (اگر کاری ثبت شده).
 *
 * @param array<string, mixed>|null $task
 */
function hodima_seo_advisor_task_badge( ?array $task ): string {
	if ( null === $task ) {
		return '';
	}
	$view = hodima_seo_advisor_task_view( $task );
	return sprintf( '<span class="%1$s">%2$s · %3$s</span>', esc_attr( $view['class'] ), esc_html( $view['label'] ), esc_html( hodima_seo_advisor_task_date( $view['time'] ) ) );
}

/**
 * دکمه‌های وضعیت زیر هر پیشنهاد در کادر ویرایش.
 *
 * @param array<string, mixed> $item پیشنهاد (rule، term، title)
 */
function hodima_seo_advisor_render_task_controls( string $path, array $item ): void {

	$rule  = (string) $item['rule'];
	$term  = (string) $item['term'];
	$title = (string) $item['title'];
	$task  = hodima_seo_advisor_task( $path, $rule, $term );

	echo '<div class="hodima-advisor-box__task">';

	if ( null !== $task ) {
		$view = hodima_seo_advisor_task_view( $task );
		printf(
			'<span class="hodima-advisor-box__pill%1$s">%2$s · %3$s</span>',
			match ( $view['state'] ) { 'waiting', 'fixed' => ' hodima-advisor-box__pill--ok', 'rejected' => '', default => ' hodima-advisor-box__pill--warn' },
			esc_html( $view['label'] ),
			esc_html( hodima_seo_advisor_task_date( $view['time'] ) )
		);
	}

	foreach ( hodima_seo_advisor_task_statuses() as $status => $label ) {
		if ( null === $task || $task['status'] !== $status ) {
			printf( '<a class="button button-small" href="%1$s">%2$s</a>', esc_url( hodima_seo_advisor_task_url( $path, $rule, $term, $title, $status ) ), esc_html( $label ) );
		}
	}

	if ( null !== $task && in_array( hodima_seo_advisor_task_state( $task ), [ 'no_effect', 'back' ], true ) ) {
		printf( '<a class="button button-small" href="%1$s">دوباره انجام دادم</a>', esc_url( hodima_seo_advisor_task_url( $path, $rule, $term, $title, 'done', true ) ) );
	}

	if ( null !== $task ) {
		printf( '<a class="button button-small" href="%1$s">برگردان به فهرست</a>', esc_url( hodima_seo_advisor_task_url( $path, $rule, $term, $title, '' ) ) );
	}

	echo '</div>';
}

/**
 * جمله اثر یک کار «انجام دادم».
 *
 * @param array{path: string, status: string, at: int} $task
 */
function hodima_seo_advisor_task_effect_text( array $task ): string {

	$result = hodima_seo_advisor_task_effect( $task );
	$n      = static fn( int|float $value, int $decimals = 0 ): string => number_format_i18n( $value, $decimals );

	if ( 'wait' === $result['state'] ) {
		return 'هنوز زود است؛ اثر بعد از دست‌کم یک هفته کامل (دوشنبه تا یکشنبه) بعد از این کار دیده می‌شود.';
	}

	if ( 'ok' !== $result['state'] ) {
		return 'تاریخچه هفتگی کافی برای اندازه‌گیری نیست (از دریافت‌های بعدی پر می‌شود).';
	}

	$effect = $result['effect'];
	$vs     = hodima_seo_advisor_effect_vs_site_text( $result['site'] ?? null );

	return sprintf(
		'میانگین کلیک هفتگی از %1$s به %2$s %3$s (%4$s هفته قبل ← %5$s هفته بعد)؛ رتبه از %6$s به %7$s. ' . ( '' !== $vs ? $vs . ' باز هم ممکن است کار دیگری اثر گذاشته باشد.' : 'تغییر می‌تواند از فصل یا کار دیگری هم باشد.' ),
		$n( $effect['before']['clicks'], 1 ),
		$n( $effect['after']['clicks'], 1 ),
		$effect['after']['clicks'] >= $effect['before']['clicks'] ? 'رسیده' : 'افتاده',
		$n( $effect['before']['weeks'] ),
		$n( $effect['after']['weeks'] ),
		$n( $effect['before']['position'], 1 ),
		$n( $effect['after']['position'], 1 )
	);
}

/**
 * سه گام «انجام‌شده» برای یک کار: انجام دادید ← گوگل صفحه را بعد از آن دوباره خواند ← نتیجه.
 *
 * @param array{path: string, rule: string, term: string, status: string, at: int, fixed: int, back: int, check: array<string, mixed>} $task
 * @return list<array{state: string, label: string, detail: string}> state: ok، wait، bad
 */
function hodima_seo_advisor_task_timeline( array $task ): array {

	$source = hodima_seo_advisor_task_source( $task['rule'] );
	$state  = hodima_seo_advisor_task_state( $task );
	$steps  = [ [ 'state' => 'ok', 'label' => 'انجام دادید', 'detail' => hodima_seo_advisor_task_date( $task['at'] ) ] ];
	$judged = (int) ( $task['check']['at'] ?? 0 );

	// گام دوم: سنجش هر نوع کار (پیشنهادها و ایندکس: آیا گوگل بعد از تغییر دوباره خواند؛ بقیه: سنجش خودشان)
	if ( in_array( $source, [ 'opportunities', 'indexing' ], true ) || 'health_unseen' === $task['rule'] ) {
		$crawl   = function_exists( 'hodima_seo_advisor_inspect_after' ) ? hodima_seo_advisor_inspect_after( $task['path'], $task['at'] ) : [ 'state' => 'unknown', 'crawled' => 0, 'checked' => 0 ];
		$steps[] = match ( $crawl['state'] ) {
			'yes'   => [ 'state' => 'ok', 'label' => 'گوگل صفحه را دوباره خواند', 'detail' => hodima_seo_advisor_task_date( $crawl['crawled'] ) ],
			'no'    => [ 'state' => 'wait', 'label' => 'گوگل هنوز تغییر را ندیده', 'detail' => sprintf( 'آخرین بار %s خوانده؛ برای سرعت، در Search Console آدرس را در نوار بالا بزنید و «درخواست ایندکس» را بزنید.', hodima_seo_advisor_task_date( $crawl['crawled'] ) ) ],
			default => [ 'state' => 'wait', 'label' => 'منتظر بررسی ایندکس', 'detail' => 'این آدرس اول صف بررسی ایندکس است؛ با «گرفتن داده تازه» یا بررسی خودکار بعدی معلوم می‌شود.' ],
		};
	} elseif ( 'crawl' === $source ) {
		$live    = in_array( substr( $task['rule'], 6 ), [ ...HODIMA_SEO_ADVISOR_MARK_LIVE_CODES, ...HODIMA_SEO_ADVISOR_MARK_RECRAWL_CODES, 'broken' ], true );
		$steps[] = $judged > 0
			? [ 'state' => 'ok', 'label' => $live ? 'صفحه دوباره از سایت خوانده شد' : 'خزش تازه خوانده شد', 'detail' => hodima_seo_advisor_task_date( $judged ) ]
			: [ 'state' => 'wait', 'label' => 'منتظر خزش تازه', 'detail' => $live ? 'صفحه همان لحظه از سایت خوانده نشد (شبکه یا هاست)؛ با ذخیره دوباره صفحه یا خروجی خزش بعدی سنجیده می‌شود.' : 'کند بودن، لینک ورودی و ریدایرکت از یک صفحه سنجیده نمی‌شوند؛ با خواندن فایل‌های خزش بعدی نتیجه می‌گیرند.' ];
	} elseif ( 'competition' === $source ) {
		$days    = (int) floor( ( time() - $task['at'] ) / DAY_IN_SECONDS );
		$steps[] = $days >= HODIMA_SEO_ADVISOR_MARK_COMPETITION_DAYS || $judged > 0
			? [ 'state' => 'ok', 'label' => 'داده تازه گوگل رسید', 'detail' => sprintf( '%s روز داده بعد از تغییر', number_format_i18n( $days ) ) ]
			: [ 'state' => 'wait', 'label' => 'منتظر داده تازه گوگل', 'detail' => sprintf( '%1$s از %2$s روز؛ نمایش این کلمه از روز بعد از تغییر جمع می‌شود.', number_format_i18n( max( 0, $days ) ), number_format_i18n( HODIMA_SEO_ADVISOR_MARK_COMPETITION_DAYS ) ) ];
	} else {
		$steps[] = $judged > 0
			? [ 'state' => 'ok', 'label' => 'آدرس دوباره در سایت بررسی شد', 'detail' => hodima_seo_advisor_task_date( $judged ) ]
			: [ 'state' => 'wait', 'label' => 'منتظر بررسی بعدی', 'detail' => 'با دریافت بعدی داده، آدرس در خود سایت دوباره سنجیده می‌شود.' ];
	}

	$verdict = (string) ( $task['check']['verdict'] ?? '' );
	$steps[] = match ( true ) {
		'effective' === $verdict => [ 'state' => 'ok', 'label' => 'اثر داشت', 'detail' => hodima_seo_advisor_verify_text( $task ) ],
		'fixed' === $state => [ 'state' => 'ok', 'label' => 'برطرف شد', 'detail' => hodima_seo_advisor_task_fixed_text( $task ) . ( 'opportunities' === $source && 'ok' === hodima_seo_advisor_task_effect( $task )['state'] ? ' ' . hodima_seo_advisor_task_effect_text( $task ) : '' ) ],
		'no_effect' === $state => [ 'state' => 'bad', 'label' => 'اثر نداشت', 'detail' => hodima_seo_advisor_verify_text( $task ) ],
		'back' === $state => [ 'state' => 'bad', 'label' => 'برگشت', 'detail' => 'ایراد دوباره پیدا شد؛ صفحه را دوباره بررسی کنید.' ],
		'still' === $state => [ 'state' => 'bad', 'label' => hodima_seo_advisor_task_still_label( $task['rule'] ), 'detail' => hodima_seo_advisor_task_still_text( $task['rule'] ) ],
		'unseen' === $state => [ 'state' => 'bad', 'label' => 'هنوز در صفحه دیده می‌شود', 'detail' => 'کلمه هنوز در عنوان، تیترها یا متن صفحه پیدا نمی‌شود؛ صفحه را بررسی و دوباره ذخیره کنید.' ],
		default => [ 'state' => 'wait', 'label' => 'منتظر نتیجه', 'detail' => hodima_seo_advisor_task_wait_text( $task ) ],
	};

	return $steps;
}

/**
 * جمله «برطرف شد» هر نوع کار.
 *
 * @param array{rule: string} $task
 */
function hodima_seo_advisor_task_fixed_text( array $task ): string {
	return match ( hodima_seo_advisor_task_source( $task['rule'] ) ) {
		'crawl'       => 'خواندن دوباره صفحه یا خزش تازه این ایراد را نشان نمی‌دهد. اگر خزش بعدی دوباره پیدایش کند، با نشان «برگشت» به «نیاز به کار» برمی‌گردد.',
		'competition' => 'یک صفحه سهم اصلی نمایش این کلمه را گرفت (یا دیگر چند صفحه برایش نمایش نمی‌گیرند).',
		'health'      => 'health_dead' === $task['rule'] ? 'آدرس دیگر خراب نیست (ریدایرکت یا صفحه سالم)؛ گوگل در چند هفته آدرس کهنه را جایگزین می‌کند.' : 'صفحه حالا در گوگل نمایش می‌گیرد یا گوگل آن را ایندکس‌شده می‌داند.',
		'indexing'    => 'گوگل حالا این آدرس را ایندکس‌شده می‌داند.',
		default       => 'تحلیل دیگر این ایراد را در صفحه پیدا نمی‌کند.',
	};
}

/** جمله «هنوز مانده» و قدم بعدی هر نوع کار. */
function hodima_seo_advisor_task_still_text( string $rule ): string {
	return match ( $rule ) {
		'competition'   => sprintf( 'بعد از %s روز داده تازه، هنوز چند صفحه نمایش این کلمه را بین خود پخش کرده‌اند (هیچ‌کدام ۷۰٪ نیست). دستور همین کلمه را در «صفحه‌های هم‌کلمه» دوباره ببینید و بعد «دوباره انجام دادم» را بزنید.', number_format_i18n( HODIMA_SEO_ADVISOR_MARK_COMPETITION_DAYS ) ),
		'health_dead'   => 'آدرس هنوز ۴۰۴ است یا ریدایرکتش به مقصد خراب می‌رود. در «ابزارهای هدیما ← ریدایرکت‌ها» قانون ۳۰۱ بگذارید و «دوباره انجام دادم» را بزنید.',
		'health_unseen' => sprintf( '%s روز بعد هنوز نمایشی نگرفته. noindex، سایت‌مپ، لینک داخلی و کامل بودن محتوا را دوباره بررسی و در Search Console درخواست ایندکس کنید.', number_format_i18n( HODIMA_SEO_ADVISOR_MARK_UNSEEN_DAYS ) ),
		'index_problem' => 'گوگل صفحه را بعد از تغییر شما دوباره خواند ولی هنوز ایندکس نکرده؛ دستور همان وضعیت را در «وضعیت در گوگل» ببینید و بعد «دوباره انجام دادم» را بزنید.',
		default         => 'صفحه بعد از «انجام دادم» دوباره سنجیده شد و این ایراد هنوز هست. شاید تغییر ذخیره نشده یا افزونه یا قالب دیگری همان بخش را می‌نویسد؛ درستش کنید و «دوباره انجام دادم» را بزنید.',
	};
}

/**
 * جمله «منتظر نتیجه» هر نوع کار.
 *
 * @param array{rule: string, term: string, check: array<string, mixed>} $task
 */
function hodima_seo_advisor_task_wait_text( array $task ): string {

	$verify = hodima_seo_advisor_verify_text( $task );

	return match ( hodima_seo_advisor_task_source( $task['rule'] ) ) {
		'crawl'       => 'با خروجی خزش بعدی (یا ذخیره دوباره صفحه برای عنوان، متا و تیترها) نتیجه همین‌جا می‌آید.',
		'competition' => 'وقتی ۱۴ روز داده بعد از تغییر جمع شد: یک صفحه دست‌کم ۷۰٪ نمایش ← برطرف شد؛ وگرنه «هنوز رقابت دارد» و برمی‌گردد.',
		'health'      => 'health_dead' === $task['rule'] ? 'با دریافت بعدی آدرس در خود سایت دوباره سنجیده می‌شود.' : sprintf( 'صفحه نمایش بگیرد یا گوگل ایندکسش کند ← برطرف شد؛ %s روز بعد هنوز نه ← برمی‌گردد.', number_format_i18n( HODIMA_SEO_ADVISOR_MARK_UNSEEN_DAYS ) ),
		'indexing'    => 'ایندکس شد ← برطرف؛ گوگل دوباره خواند و هنوز ایندکس نکرد ← با دلیلش برمی‌گردد.',
		default       => '' !== $verify ? $verify : sprintf( 'وقتی %s روز داده گوگل بعد از تغییر جمع شد، با همان‌قدر روز پیش از آن مقایسه می‌شود و نتیجه همین‌جا می‌آید.', number_format_i18n( HODIMA_SEO_ADVISOR_VERIFY_MIN_DAYS ) ),
	};
}

/**
 * فرم دکمه‌های وضعیت یک کار (تب‌های «در دست انجام» و «انجام‌شده»).
 *
 * @param array{path: string, rule: string, term: string, title: string, status: string} $task
 */
function hodima_seo_advisor_render_task_form( array $task, string $state ): void {
	$hidden = static function () use ( $task ): void {
		wp_nonce_field( 'hodima_advisor_task' );
		foreach ( [ 'path', 'rule', 'term', 'title' ] as $field ) {
			printf( '<input type="hidden" name="%1$s" value="%2$s">', esc_attr( $field ), esc_attr( $task[ $field ] ) );
		}
	};
	?>
	<?php if ( in_array( $state, [ 'no_effect', 'back', 'still' ], true ) ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="hodima-advisor__task-actions">
			<input type="hidden" name="action" value="hodima_advisor_task">
			<?php $hidden(); ?>
			<input type="hidden" name="status" value="done">
			<input type="hidden" name="redo" value="1">
			<button type="submit" class="hds-btn">دوباره انجام دادم</button>
		</form>
	<?php endif; ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="hodima-advisor__task-actions">
		<input type="hidden" name="action" value="hodima_advisor_task">
		<?php $hidden(); ?>
		<?php foreach ( hodima_seo_advisor_task_statuses() as $key => $label ) : ?>
			<?php if ( $key !== $task['status'] ) : ?>
				<button type="submit" name="status" value="<?php echo esc_attr( $key ); ?>" class="hds-btn hds-btn--outline"><?php echo esc_html( $label ); ?></button>
			<?php endif; ?>
		<?php endforeach; ?>
		<button type="submit" name="status" value="" class="hds-btn hds-btn--outline hodima-advisor__act"><span class="dashicons dashicons-undo" aria-hidden="true"></span>برگردان به فهرست</button>
	</form>
	<?php
}

/**
 * محتوای تب «کارها» (SEO 3.1.3؛ تا ۳.۱.۲ دو تب «در دست انجام» و «انجام‌شده»): فیلتر وضعیت (نیاز به کار، منتظر نتیجه،
 * برطرف شد، لازم نیست)، فیلتر «از کدام تب» و فهرست کارها. کار انجام‌شده سه گام (انجام ← سنجش ← نتیجه) دارد؛ کار باز قدم
 * بعدی. پیش‌فرض: «نیاز به کار»، وگرنه اولین وضعیتی که کار دارد.
 */
function hodima_seo_advisor_render_tasks(): void {

	$all    = hodima_seo_advisor_tasks();
	$n      = static fn( int|float $value ): string => number_format_i18n( $value );
	$counts = hodima_seo_advisor_task_counts();
	$groups = hodima_seo_advisor_task_groups();
	$filter = isset( $_GET['tstatus'] ) ? sanitize_key( (string) $_GET['tstatus'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط فیلتر نمایش
	$from   = isset( $_GET['tsource'] ) ? sanitize_key( (string) $_GET['tsource'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط فیلتر نمایش
	$from   = isset( hodima_seo_advisor_task_sources()[ $from ] ) ? $from : '';
	$tab    = 'tasks';

	if ( ! isset( $groups[ $filter ] ) ) {
		$filter = 'open';
		foreach ( array_keys( $groups ) as $group ) {
			if ( $counts[ $group ] > 0 ) {
				$filter = $group;
				break;
			}
		}
	}

	hodima_seo_ui_card_open( 'کارها', 'هر چه «انجام دادم» زده‌اید، با سنجش و نتیجه‌اش. کاری که مانده، برگشته یا بی‌اثر است در «نیاز به کار» می‌آید.' );

	if ( ! $all ) {
		hodima_seo_ui_empty( 'هنوز کاری ثبت نشده', 'بعد از رفع هر مورد، «انجام دادم» را در همان تب یا کادر «دستار سئو» صفحه ویرایش بزنید؛ اینجا می‌آید و نتیجه‌اش سنجیده می‌شود.' );
		hodima_seo_ui_card_close();
		return;
	}

	echo '<div class="hodima-advisor__filters">';
	$status_items = [];
	foreach ( $groups as $group => [ $label ] ) {
		$status_items[ $group ] = [ $label, (int) $counts[ $group ], hodima_seo_advisor_page_url( [ 'tab' => $tab, 'tstatus' => $group, 'tsource' => $from ] ) ];
	}
	hodima_seo_advisor_filter_select( 'وضعیت کار', $status_items, (string) $filter );

	$rows  = array_filter( $all, static fn( array $t ): bool => hodima_seo_advisor_task_group( $t ) === $filter );
	$count = static fn( callable $test ): int => count( array_filter( $rows, $test ) );

	// از کدام تب (SEO 3.0.2): کارهای همه تب‌ها اینجا جمع می‌شوند
	$sources = array_filter( hodima_seo_advisor_task_sources(), static fn( string $label, string $key ): bool => (bool) $count( static fn( array $t ): bool => hodima_seo_advisor_task_source( $t['rule'] ) === $key ), ARRAY_FILTER_USE_BOTH );
	if ( count( $sources ) > 1 ) {
		$source_items = [ '' => [ 'از همه بخش‌ها', count( $rows ), hodima_seo_advisor_page_url( [ 'tab' => $tab, 'tstatus' => $filter ] ) ] ];
		foreach ( $sources as $key => $label ) {
			$source_items[ $key ] = [ $label, $count( static fn( array $t ): bool => hodima_seo_advisor_task_source( $t['rule'] ) === $key ), hodima_seo_advisor_page_url( [ 'tab' => $tab, 'tstatus' => $filter, 'tsource' => $key ] ) ];
		}
		hodima_seo_advisor_filter_select( 'از کدام بخش', $source_items, $from );
	}
	echo '</div>';
	if ( '' !== $from ) {
		$rows = array_filter( $rows, static fn( array $t ): bool => hodima_seo_advisor_task_source( $t['rule'] ) === $from );
	}

	if ( ! $rows ) {
		hodima_seo_ui_empty( 'کاری در این وضعیت نیست', 'وضعیت یا تب دیگری را انتخاب کنید.' );
		hodima_seo_ui_card_close();
		return;
	}

	uasort( $rows, static fn( array $a, array $b ): int => hodima_seo_advisor_task_view( $b )['time'] <=> hodima_seo_advisor_task_view( $a )['time'] );

	// صفحه‌بندی (SEO 2.9.9): تا ۵۰۰ کار هر کدام با چند پرسش (صفحه، تاریخچه هفتگی، ایندکس) یک‌جا ساخته می‌شد
	$total = count( $rows );
	$pages = max( 1, (int) ceil( $total / HODIMA_SEO_ADVISOR_TASKS_PER_PAGE ) );
	$paged = min( $pages, max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط صفحه‌بندی نمایش
	$rows  = array_slice( $rows, ( $paged - 1 ) * HODIMA_SEO_ADVISOR_TASKS_PER_PAGE, HODIMA_SEO_ADVISOR_TASKS_PER_PAGE, true );

	echo '<ul class="hodima-advisor__tasks">';

	foreach ( $rows as $task ) {
		$page   = hodima_seo_advisor_page( $task['path'] );
		$source = hodima_seo_advisor_task_source( $task['rule'] );
		$link   = null !== $page ? hodima_seo_advisor_competition_page_link( $page ) : [ hodima_seo_advisor_url( $task['path'] ), 'دیدن در سایت', 'external' ];
		$title  = null !== $page ? hodima_seo_advisor_row_title( $page ) : ''; // همان ردیف؛ پرسش دوم برای عنوان نه
		if ( null === $page && 'crawl' === $source ) { // ایراد فنی صفحه‌ای که داده گوگل ندارد: ویرایش از روتر
			$target = hodima_seo_advisor_crawl_row_target( $task['path'] );
			$title  = $target['title'];
			$link   = '' !== $target['edit'] ? [ $target['edit'], 'ویرایش صفحه', 'edit' ] : $link;
		}
		$view = hodima_seo_advisor_task_view( $task );
		$hint = 'open' !== $filter ? '' : match ( $view['state'] ) {
			'unseen'    => 'کلمه هنوز در عنوان، تیترها یا متن صفحه پیدا نمی‌شود؛ صفحه را بررسی و دوباره ذخیره کنید.',
			'back'      => 'این ایراد بعد از برطرف شدن دوباره پیدا شد؛ صفحه را دوباره بررسی کنید و بعد «دوباره انجام دادم» را بزنید.',
			'no_effect' => hodima_seo_advisor_verify_text( $task ),
			'still'     => hodima_seo_advisor_task_still_text( $task['rule'] ),
			default     => 'وقتی کار تمام شد «انجام دادم» را بزنید.',
		};
		?>
		<li class="hodima-advisor__task">
			<div class="hodima-advisor__task-main">
				<span class="hodima-advisor__task-head">
					<span class="hds-pill hds-pill--info"><?php echo esc_html( hodima_seo_advisor_task_sources()[ $source ] ); ?></span>
					<strong><?php echo esc_html( '' !== $task['title'] ? $task['title'] : hodima_seo_advisor_rule_label( $task['rule'] ) ); ?></strong>
				</span>
				<span class="hodima-advisor__task-page">
					<?php echo esc_html( $title ); // بدون عنوان فقط خود آدرس (قبلا مسیر دو بار می‌آمد) ?>
					<?php echo hodima_seo_advisor_url_link( (string) $task['path'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML ساخته‌شده و escape‌شده در hodima_seo_advisor_url_link ?>
					<?php echo hodima_seo_advisor_action( $link[0], $link[1], $link[2], 'external' === $link[2] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML امن‌شده در hodima_seo_advisor_action ?>
				</span>
			</div>
			<div class="hodima-advisor__task-state">
				<span class="<?php echo esc_attr( $view['class'] ); ?>"><?php echo esc_html( $view['label'] ); ?></span>
				<small><?php echo esc_html( hodima_seo_advisor_task_date( $view['time'] ) ); ?></small>
			</div>
			<?php if ( 'done' === $task['status'] ) : ?>
				<ol class="hodima-advisor__timeline">
					<?php foreach ( hodima_seo_advisor_task_timeline( $task ) as $step ) : ?>
						<li class="hodima-advisor__step" data-state="<?php echo esc_attr( $step['state'] ); ?>">
							<strong><?php echo esc_html( $step['label'] ); ?></strong>
							<?php if ( '' !== $step['detail'] ) : ?>
								<span><?php echo esc_html( $step['detail'] ); ?></span>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ol>
			<?php endif; ?>
			<?php if ( '' !== $hint ) : ?>
				<p class="hodima-advisor__task-effect"><?php echo esc_html( $hint ); ?></p>
			<?php endif; ?>
			<?php hodima_seo_advisor_render_task_form( $task, $view['state'] ); ?>
		</li>
		<?php
	}

	echo '</ul>';

	if ( $pages > 1 ) {
		echo '<div class="hodima-advisor__foot"><span>' . esc_html( sprintf( 'نمایش %1$s از %2$s کار، تازه‌ترین اول', $n( count( $rows ) ), $n( $total ) ) ) . '</span>';
		hodima_seo_ui_pager( $paged, $pages, static fn( int $i ): string => hodima_seo_advisor_page_url( [ 'tab' => $tab, 'tstatus' => $filter, 'tsource' => $from, 'paged' => $i > 1 ? $i : '' ] ) );
		echo '</div>';
	}

	hodima_seo_ui_card_close();
}
