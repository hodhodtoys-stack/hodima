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
function hodima_seo_advisor_task_url( string $path, string $rule, string $term, string $title, string $status ): string {
	return wp_nonce_url(
		add_query_arg( [ 'action' => 'hodima_advisor_task', 'path' => $path, 'rule' => $rule, 'term' => $term, 'title' => $title, 'status' => $status ], admin_url( 'admin-post.php' ) ),
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
		printf(
			'<span class="hodima-advisor-box__pill%1$s">%2$s · %3$s</span>',
			'done' === $task['status'] ? ' hodima-advisor-box__pill--ok' : ( 'rejected' === $task['status'] ? '' : ' hodima-advisor-box__pill--warn' ),
			esc_html( hodima_seo_advisor_task_statuses()[ $task['status'] ] ),
			esc_html( hodima_seo_advisor_task_date( $task['at'] ) )
		);
	}

	foreach ( hodima_seo_advisor_task_statuses() as $status => $label ) {
		if ( null === $task || $task['status'] !== $status ) {
			printf( '<a class="button button-small" href="%1$s">%2$s</a>', esc_url( hodima_seo_advisor_task_url( $path, $rule, $term, $title, $status ) ), esc_html( $label ) );
		}
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
 * محتوای تب «کارهای من».
 */
function hodima_seo_advisor_render_tasks(): void {

	$counts = hodima_seo_advisor_task_counts();
	$all    = hodima_seo_advisor_tasks();
	$n      = static fn( int|float $value ): string => number_format_i18n( $value );
	$filter = isset( $_GET['tstatus'] ) ? sanitize_key( (string) $_GET['tstatus'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط فیلتر نمایش
	$filter = isset( hodima_seo_advisor_task_statuses()[ $filter ] ) ? $filter : '';
	$empty  = ! $all;

	$positive = 0;
	foreach ( $all as $task ) {
		if ( 'done' === $task['status'] ) {
			$effect = hodima_seo_advisor_task_effect( $task );
			// اثر مثبت = بهتر از روند کل سایت؛ بدون داده سایت، فقط بیشتر شدن کلیک
			if ( 'ok' === $effect['state'] && ( null !== ( $effect['site'] ?? null ) ? 'above' === $effect['site']['verdict'] : $effect['effect']['after']['clicks'] > $effect['effect']['before']['clicks'] ) ) {
				++$positive;
			}
		}
	}

	hodima_seo_ui_stats( [
		[ 'label' => 'در حال انجام', 'value' => $empty ? '—' : esc_html( $n( $counts['doing'] ) ), 'empty' => $empty ],
		[ 'label' => 'انجام شد', 'value' => $empty ? '—' : esc_html( $n( $counts['done'] ) ), 'empty' => $empty ],
		[ 'label' => 'رد شد', 'value' => $empty ? '—' : esc_html( $n( $counts['rejected'] ) ), 'empty' => $empty ],
		[ 'label' => 'با اثر مثبت', 'value' => $empty ? '—' : esc_html( $n( $positive ) ), 'sub' => $empty ? '' : 'کلیک هفتگی بهتر از روند سایت', 'empty' => $empty ],
	] );

	hodima_seo_ui_card_open( 'کارهای من', 'هر پیشنهاد را که اجرا کردید («انجام شد»)، در حال انجام است یا قبول ندارید («رد شد»)، از کادر «دستار سئو» داخل ویرایش صفحه علامت بزنید. اثر کارهای «انجام شد» اینجا با کلیک هفتگی اندازه می‌شود. این فهرست فقط یادداشت شماست و چیزی را در سایت عوض نمی‌کند.' );

	if ( $empty ) {
		hodima_seo_ui_empty( 'هنوز کاری علامت نخورده', 'صفحه‌ای را از «فرصت‌های بهتر شدن» با «ویرایش و رفع ایرادها» باز کنید؛ زیر هر پیشنهاد دکمه‌های «در حال انجام»، «انجام شد» و «رد شد» هست.' );
		hodima_seo_ui_card_close();
		return;
	}

	echo '<nav class="hodima-advisor__views" aria-label="وضعیت کار">';
	printf( '<a class="hodima-advisor__view" href="%1$s"%2$s>همه <span class="hodima-advisor__count">%3$s</span></a>', esc_url( hodima_seo_advisor_page_url( [ 'tab' => 'tasks' ] ) ), '' === $filter ? ' aria-current="page"' : '', esc_html( $n( count( $all ) ) ) );
	foreach ( hodima_seo_advisor_task_statuses() as $status => $label ) {
		printf( '<a class="hodima-advisor__view" href="%1$s"%2$s>%3$s <span class="hodima-advisor__count">%4$s</span></a>', esc_url( hodima_seo_advisor_page_url( [ 'tab' => 'tasks', 'tstatus' => $status ] ) ), $status === $filter ? ' aria-current="page"' : '', esc_html( $label ), esc_html( $n( $counts[ $status ] ) ) );
	}
	echo '</nav>';

	$rows = array_filter( $all, static fn( array $t ): bool => '' === $filter || $t['status'] === $filter );
	uasort( $rows, static fn( array $a, array $b ): int => $b['at'] <=> $a['at'] );

	echo '<ul class="hodima-advisor__tasks">';

	foreach ( $rows as $task ) {
		$page   = hodima_seo_advisor_page( $task['path'] );
		$link   = null !== $page ? hodima_seo_advisor_competition_page_link( $page ) : [ home_url( $task['path'] ), 'دیدن در سایت' ];
		$class  = [ 'done' => 'hds-pill', 'doing' => 'hds-pill hds-pill--warn', 'rejected' => 'hds-pill hds-pill--info' ][ $task['status'] ];
		$status = hodima_seo_advisor_task_statuses()[ $task['status'] ];
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
				<span class="<?php echo esc_attr( $class ); ?>"><?php echo esc_html( $status ); ?></span>
				<small><?php echo esc_html( hodima_seo_advisor_task_date( $task['at'] ) ); ?></small>
			</div>
			<?php if ( 'done' === $task['status'] ) : ?>
				<p class="hodima-advisor__task-effect"><?php echo esc_html( hodima_seo_advisor_task_effect_text( $task ) ); ?></p>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="hodima-advisor__task-actions">
				<input type="hidden" name="action" value="hodima_advisor_task">
				<?php wp_nonce_field( 'hodima_advisor_task' ); ?>
				<input type="hidden" name="path" value="<?php echo esc_attr( $task['path'] ); ?>">
				<input type="hidden" name="rule" value="<?php echo esc_attr( $task['rule'] ); ?>">
				<input type="hidden" name="term" value="<?php echo esc_attr( $task['term'] ); ?>">
				<input type="hidden" name="title" value="<?php echo esc_attr( $task['title'] ); ?>">
				<?php foreach ( hodima_seo_advisor_task_statuses() as $key => $label ) : ?>
					<?php if ( $key !== $task['status'] ) : ?>
						<button type="submit" name="status" value="<?php echo esc_attr( $key ); ?>" class="hds-btn hds-btn--outline"><?php echo esc_html( $label ); ?></button>
					<?php endif; ?>
				<?php endforeach; ?>
				<button type="submit" name="status" value="" class="hodima-advisor__link-btn">برداشتن</button>
			</form>
		</li>
		<?php
	}

	echo '</ul>';
	hodima_seo_ui_card_close();
}
