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

/** تاریخ کوتاه شمسی (Core) یا میلادی. */
function hodima_seo_advisor_task_date( int $time ): string {
	return function_exists( 'hodima_jalali_format' ) ? hodima_jalali_format( 'j F Y', $time + (int) ( (float) get_option( 'gmt_offset', 0 ) * HOUR_IN_SECONDS ) ) : (string) wp_date( 'Y/m/d', $time );
}

/** آدرس تغییر وضعیت (GET با nonce؛ کادر ویرایش داخل فرم وردپرس است و فرم تودرتو نمی‌شود). */
function hodima_seo_advisor_task_url( string $path, string $rule, string $term, string $title, string $status, bool $redo = false ): string {
	return wp_nonce_url(
		add_query_arg( [ 'action' => 'hodima_advisor_task', 'path' => $path, 'rule' => $rule, 'term' => $term, 'title' => $title, 'status' => $status ] + ( $redo ? [ 'redo' => 1 ] : [] ), admin_url( 'admin-post.php' ) ),
		'hodima_advisor_task'
	);
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
		printf( '<a class="hodima-advisor-box__link" href="%1$s">برداشتن</a>', esc_url( hodima_seo_advisor_task_url( $path, $rule, $term, $title, '' ) ) );
	}

	echo '</div>';
}

/**
 * جمله اثر یک کار «انجام شد».
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

	$steps   = [ [ 'state' => 'ok', 'label' => 'انجام دادید', 'detail' => hodima_seo_advisor_task_date( $task['at'] ) ] ];
	$crawl   = function_exists( 'hodima_seo_advisor_inspect_after' ) ? hodima_seo_advisor_inspect_after( $task['path'], $task['at'] ) : [ 'state' => 'unknown', 'crawled' => 0, 'checked' => 0 ];
	$steps[] = match ( $crawl['state'] ) {
		'yes'   => [ 'state' => 'ok', 'label' => 'گوگل صفحه را دوباره خواند', 'detail' => hodima_seo_advisor_task_date( $crawl['crawled'] ) ],
		'no'    => [ 'state' => 'wait', 'label' => 'گوگل هنوز تغییر را ندیده', 'detail' => sprintf( 'آخرین بار %s خوانده؛ برای سرعت، در Search Console آدرس را در نوار بالا بزنید و «درخواست ایندکس» را بزنید.', hodima_seo_advisor_task_date( $crawl['crawled'] ) ) ],
		default => [ 'state' => 'wait', 'label' => 'منتظر بررسی ایندکس', 'detail' => 'این آدرس اول صف بررسی ایندکس است؛ با «به‌روزرسانی همه» یا بررسی خودکار بعدی معلوم می‌شود.' ],
	};

	$state   = hodima_seo_advisor_task_state( $task );
	$verdict = (string) ( $task['check']['verdict'] ?? '' );
	$steps[] = match ( true ) {
		'effective' === $verdict => [ 'state' => 'ok', 'label' => 'اثر داشت', 'detail' => hodima_seo_advisor_verify_text( $task ) ],
		'fixed' === $state => [ 'state' => 'ok', 'label' => 'برطرف شد', 'detail' => 'تحلیل دیگر این ایراد را در صفحه پیدا نمی‌کند.' . ( 'ok' === hodima_seo_advisor_task_effect( $task )['state'] ? ' ' . hodima_seo_advisor_task_effect_text( $task ) : '' ) ],
		'no_effect' === $state => [ 'state' => 'bad', 'label' => 'اثر نداشت', 'detail' => hodima_seo_advisor_verify_text( $task ) ],
		'back' === $state => [ 'state' => 'bad', 'label' => 'برگشت', 'detail' => 'ایراد دوباره پیدا شد؛ صفحه را دوباره بررسی کنید.' ],
		'unseen' === $state => [ 'state' => 'bad', 'label' => 'هنوز در صفحه دیده می‌شود', 'detail' => 'کلمه هنوز در عنوان، تیترها یا متن صفحه پیدا نمی‌شود؛ صفحه را بررسی و دوباره ذخیره کنید.' ],
		default => [ 'state' => 'wait', 'label' => 'منتظر نتیجه', 'detail' => '' !== hodima_seo_advisor_verify_text( $task ) ? hodima_seo_advisor_verify_text( $task ) : sprintf( 'وقتی %s روز داده گوگل بعد از تغییر جمع شد، با همان‌قدر روز پیش از آن مقایسه می‌شود و نتیجه همین‌جا می‌آید.', number_format_i18n( HODIMA_SEO_ADVISOR_VERIFY_MIN_DAYS ) ) ],
	};

	return $steps;
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
	<?php if ( in_array( $state, [ 'no_effect', 'back' ], true ) ) : ?>
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
		<button type="submit" name="status" value="" class="hodima-advisor__link-btn">برداشتن</button>
	</form>
	<?php
}

/**
 * محتوای تب‌های «در دست انجام» (`tab=tasks`) و «انجام‌شده» (`tab=done`). SEO 2.9.4: قبلا یک تب «کارهای من».
 *
 * @param string $which doing | done
 */
function hodima_seo_advisor_render_tasks( string $which = 'doing' ): void {

	$all    = hodima_seo_advisor_tasks();
	$n      = static fn( int|float $value ): string => number_format_i18n( $value );
	$done   = 'done' === $which;
	$groups = $done ? [ 'waiting', 'fixed', 'rejected' ] : [ 'open' ];
	$rows   = array_filter( $all, static fn( array $t ): bool => in_array( hodima_seo_advisor_task_group( $t ), $groups, true ) );
	$filter = isset( $_GET['tstatus'] ) ? sanitize_key( (string) $_GET['tstatus'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط فیلتر نمایش
	$filter = $done && in_array( $filter, $groups, true ) ? $filter : '';
	$empty  = ! $rows;
	$count  = static fn( callable $test ): int => count( array_filter( $rows, $test ) );

	if ( $done ) {
		$recrawled = $count( static fn( array $t ): bool => function_exists( 'hodima_seo_advisor_inspect_after' ) && 'yes' === hodima_seo_advisor_inspect_after( $t['path'], $t['at'] )['state'] );
		hodima_seo_ui_stats( [
			[ 'label' => 'انجام‌شده', 'value' => $empty ? '—' : esc_html( $n( $count( static fn( array $t ): bool => 'rejected' !== $t['status'] ) ) ), 'empty' => $empty ],
			[ 'label' => 'گوگل دوباره خوانده', 'value' => $empty ? '—' : esc_html( $n( $recrawled ) ), 'sub' => $empty ? '' : 'صفحه بعد از تغییر شما خزیده شده', 'empty' => $empty ],
			[ 'label' => 'برطرف شد', 'value' => $empty ? '—' : esc_html( $n( $count( static fn( array $t ): bool => 'fixed' === hodima_seo_advisor_task_state( $t ) ) ) ), 'empty' => $empty ],
			[ 'label' => 'منتظر نتیجه', 'value' => $empty ? '—' : esc_html( $n( $count( static fn( array $t ): bool => 'waiting' === hodima_seo_advisor_task_state( $t ) ) ) ), 'sub' => $empty ? '' : 'تا ۱۴ روز داده گوگل بعد از تغییر', 'empty' => $empty ],
		] );
		hodima_seo_ui_card_open( 'انجام‌شده', 'کارهایی که «انجام شد» زده‌اید، هر کدام با سه گام: کی انجام دادید، آیا گوگل صفحه را بعد از آن دوباره خوانده، و نتیجه (برطرف شد، اثر داشت یا اثر نداشت). کاری که اثر نداشت یا برگشت، خودش به «در دست انجام» برمی‌گردد.' );
	} else {
		hodima_seo_ui_stats( [
			[ 'label' => 'کار باز', 'value' => $empty ? '—' : esc_html( $n( count( $rows ) ) ), 'empty' => $empty ],
			[ 'label' => 'در حال انجام', 'value' => $empty ? '—' : esc_html( $n( $count( static fn( array $t ): bool => 'doing' === hodima_seo_advisor_task_state( $t ) ) ) ), 'empty' => $empty ],
			[ 'label' => 'اثر نداشت', 'value' => $empty ? '—' : esc_html( $n( $count( static fn( array $t ): bool => 'no_effect' === hodima_seo_advisor_task_state( $t ) ) ) ), 'sub' => $empty ? '' : 'قدم بعدی کنار هر کدام', 'empty' => $empty ],
			[ 'label' => 'برگشته یا هنوز دیده می‌شود', 'value' => $empty ? '—' : esc_html( $n( $count( static fn( array $t ): bool => in_array( hodima_seo_advisor_task_state( $t ), [ 'back', 'unseen' ], true ) ) ) ), 'empty' => $empty ],
		] );
		hodima_seo_ui_card_open( 'در دست انجام', 'کارهایی که هنوز کار دارند: آن‌هایی که «در حال انجام» زده‌اید، ایراد متنی که هنوز در صفحه دیده می‌شود، کاری که بعد از برطرف شدن برگشته و کاری که اثر نداشته. کنار هر کدام نوشته دقیقا چه کنید؛ بعد از انجام، در کادر «دستار سئو» همان صفحه «انجام شد» بزنید تا به «انجام‌شده» برود.' );
	}

	if ( $empty ) {
		if ( $done ) {
			hodima_seo_ui_empty( 'هنوز کاری انجام نشده', 'بعد از اجرای هر پیشنهاد، در کادر «دستار سئو» داخل ویرایش همان صفحه «انجام شد» را بزنید؛ اینجا می‌آید و نتیجه‌اش سنجیده می‌شود.' );
		} else {
			hodima_seo_ui_empty( 'کار بازی نیست', 'صفحه‌ای را از «فرصت‌های بهتر شدن» با «ویرایش و رفع ایرادها» باز کنید؛ زیر هر پیشنهاد دکمه‌های «در حال انجام»، «انجام شد» و «رد شد» هست.' );
		}
		hodima_seo_ui_card_close();
		return;
	}

	if ( $done ) {
		echo '<nav class="hodima-advisor__views" aria-label="وضعیت کار انجام‌شده">';
		printf( '<a class="hodima-advisor__view" href="%1$s"%2$s>همه <span class="hodima-advisor__count">%3$s</span></a>', esc_url( hodima_seo_advisor_page_url( [ 'tab' => 'done' ] ) ), '' === $filter ? ' aria-current="page"' : '', esc_html( $n( count( $rows ) ) ) );
		foreach ( $groups as $group ) {
			printf( '<a class="hodima-advisor__view" href="%1$s"%2$s>%3$s <span class="hodima-advisor__count">%4$s</span></a>', esc_url( hodima_seo_advisor_page_url( [ 'tab' => 'done', 'tstatus' => $group ] ) ), $group === $filter ? ' aria-current="page"' : '', esc_html( hodima_seo_advisor_task_groups()[ $group ][0] ), esc_html( $n( $count( static fn( array $t ): bool => hodima_seo_advisor_task_group( $t ) === $group ) ) ) );
		}
		echo '</nav>';
		$rows = array_filter( $rows, static fn( array $t ): bool => '' === $filter || hodima_seo_advisor_task_group( $t ) === $filter );
	}

	uasort( $rows, static fn( array $a, array $b ): int => hodima_seo_advisor_task_view( $b )['time'] <=> hodima_seo_advisor_task_view( $a )['time'] );

	echo '<ul class="hodima-advisor__tasks">';

	foreach ( $rows as $task ) {
		$page = hodima_seo_advisor_page( $task['path'] );
		$link = null !== $page ? hodima_seo_advisor_competition_page_link( $page ) : [ home_url( $task['path'] ), 'دیدن در سایت' ];
		$view = hodima_seo_advisor_task_view( $task );
		$hint = $done ? '' : match ( $view['state'] ) {
			'unseen'    => 'کلمه هنوز در عنوان، تیترها یا متن صفحه پیدا نمی‌شود؛ صفحه را بررسی و دوباره ذخیره کنید.',
			'back'      => 'این ایراد بعد از برطرف شدن دوباره پیدا شد؛ صفحه را دوباره بررسی کنید و بعد «دوباره انجام دادم» را بزنید.',
			'no_effect' => hodima_seo_advisor_verify_text( $task ),
			default     => 'وقتی کار تمام شد «انجام شد» را بزنید.',
		};
		?>
		<li class="hodima-advisor__task">
			<div class="hodima-advisor__task-main">
				<strong><?php echo esc_html( '' !== $task['title'] ? $task['title'] : hodima_seo_advisor_rule_label( $task['rule'] ) ); ?></strong>
				<span class="hodima-advisor__task-page">
					<?php echo esc_html( hodima_seo_advisor_breakdown_page_title( $task['path'] ) ); ?>
					<?php echo hodima_seo_advisor_url_link( (string) $task['path'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML ساخته‌شده و escape‌شده در hodima_seo_advisor_url_link ?>
					<a href="<?php echo esc_url( $link[0] ); ?>"><?php echo esc_html( $link[1] ); ?></a>
				</span>
			</div>
			<div class="hodima-advisor__task-state">
				<span class="<?php echo esc_attr( $view['class'] ); ?>"><?php echo esc_html( $view['label'] ); ?></span>
				<small><?php echo esc_html( hodima_seo_advisor_task_date( $view['time'] ) ); ?></small>
			</div>
			<?php if ( $done && 'rejected' !== $task['status'] ) : ?>
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
			<?php elseif ( '' !== $hint ) : ?>
				<p class="hodima-advisor__task-effect"><?php echo esc_html( $hint ); ?></p>
			<?php endif; ?>
			<?php hodima_seo_advisor_render_task_form( $task, $view['state'] ); ?>
		</li>
		<?php
	}

	echo '</ul>';
	hodima_seo_ui_card_close();
}
