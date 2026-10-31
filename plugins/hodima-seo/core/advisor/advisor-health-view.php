<?php
/**
 * ماژول «دستار سئو» — تب «آدرس‌های خراب»
 * Path: core/advisor/advisor-health-view.php
 *
 * داده از advisor-health.php. کالبد: ۴ کارت آمار ← کارت «آدرس‌هایی که در گوگل‌اند ولی در سایت مرده‌اند»
 * (جدول با وضعیت و دستور) ← کارت «صفحه‌هایی که نمایش نگرفته‌اند». فقط می‌خواند و دستور می‌دهد؛ چیزی را
 * در سایت عوض نمی‌کند. SEO 3.0.2: هر آدرس مرده و صفحه بدون نمایش «انجام شد / رد شد» دارد (advisor-marks.php).
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** ردیف در هر صفحه «صفحه‌هایی که نمایش نگرفته‌اند» و «وضعیت در گوگل». */
const HODIMA_SEO_ADVISOR_HEALTH_PER_PAGE = 25;

/**
 * برچسب و دستور هر وضعیت آدرس مرده.
 *
 * @return array{label: string, class: string, advice: string}
 */
function hodima_seo_advisor_health_state( string $state ): array {

	return [
		'missing'     => [ 'label' => '۴۰۴', 'class' => 'hds-pill hds-pill--warn', 'advice' => 'آدرس‌های «۴۰۴» هنوز در گوگل نمایش می‌گیرند ولی در سایت صفحه‌ای ندارند. اگر صفحه جایگزین مناسبی هست، در «ابزارهای هدیما ← ریدایرکت‌ها» با ریدایرکت ۳۰۱ به آن ببرید تا کلیک و اعتبارش هدر نرود. اگر صفحه عمدی حذف شده و جایگزینی ندارد، ۴۰۴ یا ۴۱۰ می‌ماند و گوگل کم‌کم حذفش می‌کند.' ],
		'rule_broken' => [ 'label' => 'ریدایرکت خراب', 'class' => 'hds-pill hds-pill--warn', 'advice' => 'آدرس‌های «ریدایرکت خراب» قانون ریدایرکت دارند ولی مقصدشان ۴۰۴ است. مقصد قانون را در «ریدایرکت‌ها» درست کنید.' ],
		'gone'        => [ 'label' => 'حذف‌شده (۴۰۴/۴۱۰)', 'class' => 'hds-pill hds-pill--info', 'advice' => 'آدرس‌های «حذف‌شده» با قانون عمدا ۴۰۴ یا ۴۱۰ داده می‌شوند؛ اگر جایگزین مناسب پیدا کردید به آن ریدایرکت کنید، وگرنه همین‌طور خوب است.' ],
		'redirect'    => [ 'label' => 'ریدایرکت ۳۰۱', 'class' => 'hds-pill hds-pill--info', 'advice' => 'آدرس‌های «ریدایرکت ۳۰۱» درست کار می‌کنند؛ گوگل کم‌کم آدرس جدید را جایگزین می‌کند. لینک‌های داخلی سایت را هم فقط به آدرس جدید بدهید.' ],
		'rule'        => [ 'label' => 'ریدایرکت (قانون)', 'class' => 'hds-pill hds-pill--info', 'advice' => 'آدرس‌های «ریدایرکت (قانون)» درست کار می‌کنند؛ گوگل کم‌کم آدرس جدید را جایگزین می‌کند. لینک‌های داخلی سایت را هم فقط به آدرس جدید بدهید.' ],
	][ $state ] ?? [ 'label' => $state, 'class' => 'hds-pill', 'advice' => '' ];
}

/**
 * محتوای تب «آدرس‌های خراب».
 *
 * @param array<string, mixed> $status خروجی hodima_seo_advisor_status()
 */
function hodima_seo_advisor_render_health( array $status ): void {

	$data   = hodima_seo_advisor_health();
	$n      = static fn( int|float $value ): string => number_format_i18n( $value );
	$empty  = (int) ( $data['computed'] ?? 0 ) <= 0;
	$dead   = (array) ( $data['dead'] ?? [] );
	$unseen = (array) ( $data['unseen'] ?? [] );
	$total  = (int) ( $data['total'] ?? 0 );
	$seen   = (int) ( $data['seen'] ?? 0 );
	$fresh  = (int) ( $data['fresh'] ?? 0 );

	// موردهای علامت‌خورده (منتظر، برطرف، رد شد) پنهان، مگر با «نمایش علامت‌خورده‌ها»
	$show        = hodima_seo_advisor_show_marked();
	$hide_dead   = 0;
	$hide_unseen = 0;
	foreach ( $dead as $i => $row ) {
		if ( in_array( $row['state'], HODIMA_SEO_ADVISOR_HEALTH_BAD, true ) && ! $show && null !== hodima_seo_advisor_task_closed( (string) $row['path'], 'health_dead' ) ) {
			unset( $dead[ $i ] );
			++$hide_dead;
		}
	}
	foreach ( $unseen as $i => $row ) {
		if ( ! $show && null !== hodima_seo_advisor_task_closed( (string) $row['path'], 'health_unseen' ) ) {
			unset( $unseen[ $i ] );
			++$hide_unseen;
		}
	}
	$dead   = array_values( $dead );
	$unseen = array_values( $unseen );

	$bad_dead = count( array_filter( $dead, static fn( array $d ): bool => in_array( $d['state'], HODIMA_SEO_ADVISOR_HEALTH_BAD, true ) ) );

	hodima_seo_ui_stats( [
		[ 'label' => 'صفحه بدون نمایش', 'value' => $empty ? '—' : esc_html( $n( count( $unseen ) ) ), 'sub' => $empty ? '' : esc_html( sprintf( 'از %s صفحه منتشرشده', $n( $total ) ) ), 'empty' => $empty ],
		[ 'label' => 'صفحه تازه', 'value' => $empty ? '—' : esc_html( $n( $fresh ) ), 'sub' => $empty ? '' : esc_html( sprintf( 'کمتر از %s روز؛ هنوز زود است', $n( HODIMA_SEO_ADVISOR_HEALTH_FRESH_DAYS ) ) ), 'empty' => $empty ],
		[ 'label' => 'آدرس مرده در گوگل', 'value' => $empty ? '—' : esc_html( $n( $bad_dead ) ), 'sub' => $empty ? '' : esc_html( sprintf( 'از %s آدرس نیازمند بررسی', $n( count( $dead ) ) ) ), 'empty' => $empty ],
		[ 'label' => 'پوشش در گوگل', 'value' => $empty || $total <= 0 ? '—' : esc_html( $n( round( $seen / $total * 100 ) ) . '٪' ), 'sub' => $empty || $total <= 0 ? '' : esc_html( sprintf( '%1$s از %2$s صفحه نمایش گرفته', $n( $seen ), $n( $total ) ) ), 'empty' => $empty || $total <= 0 ],
	] );

	if ( $empty ) {
		hodima_seo_ui_card_open( 'آدرس‌های خراب' );
		hodima_seo_ui_empty( 'هنوز ساخته نشده', 'بعد از «گرفتن داده تازه» این بخش پر می‌شود.' );
		hodima_seo_ui_card_close();
		return;
	}

	// آدرس‌های مرده
	hodima_seo_ui_card_open( 'آدرس‌هایی که در گوگل‌اند ولی در سایت مرده‌اند', 'پرنمایش‌ترین آدرس‌های گوگل (تا ۲۰۰ آدرس) که در خود سایت ۴۰۴ هستند یا ریدایرکت می‌شوند؛ مرتب بر اساس اهمیت و نمایش.' );

	if ( empty( $data['router'] ) ) {
		hodima_seo_ui_empty( 'بررسی انجام نشد', 'ماژول «آدرس تمیز» سایت خاموش است و بدون آن وضعیت آدرس‌ها بررسی نمی‌شود. آن را در «ابزارهای هدیما ← ماژول‌های سئو» روشن کنید.' );
	} elseif ( ! $dead && $hide_dead > 0 ) {
		hodima_seo_ui_empty( 'کار بازی نمانده', sprintf( 'همه آدرس‌های مرده را «انجام دادم» یا «لازم نیست» علامت زده‌اید. %s', hodima_seo_advisor_marked_toggle( $hide_dead, [ 'tab' => 'health' ], 'آدرس' ) ) );
	} elseif ( ! $dead ) {
		hodima_seo_ui_empty( 'آدرس مرده‌ای پیدا نشد', 'همه آدرس‌های پرنمایش گوگل در سایت صفحه سالم دارند.' );
	} else {
		?>
		<div class="hds-table-wrap" tabindex="0" role="region" aria-label="آدرس‌های مرده در گوگل">
			<table class="widefat striped hodima-advisor__terms hodima-advisor__cards">
				<thead><tr><th scope="col">آدرس</th><th scope="col">نمایش</th><th scope="col">کلیک</th><th scope="col">وضعیت</th><th scope="col">مقصد</th><th scope="col">کنش</th></tr></thead>
				<tbody>
					<?php foreach ( $dead as $row ) : ?>
						<?php
						$info = hodima_seo_advisor_health_state( (string) $row['state'] );
						$task = hodima_seo_advisor_task( (string) $row['path'], 'health_dead', '' );
						$bad  = in_array( $row['state'], HODIMA_SEO_ADVISOR_HEALTH_BAD, true );
						?>
						<tr>
							<th scope="row"><?php echo hodima_seo_advisor_url_link( (string) $row['path'], 'hodima-advisor__rival-url' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML ساخته‌شده و escape‌شده در hodima_seo_advisor_url_link ?> <?php echo $bad ? hodima_seo_advisor_task_badge( $task ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML امن‌شده در hodima_seo_advisor_task_badge ?></th>
							<td data-label="نمایش"><?php echo esc_html( $n( $row['impressions'] ) ); ?></td>
							<td data-label="کلیک"><?php echo esc_html( $n( $row['clicks'] ) ); ?></td>
							<td><span class="<?php echo esc_attr( $info['class'] ); ?>"><?php echo esc_html( $info['label'] ); ?></span></td>
							<td data-label="مقصد"><?php echo '' !== (string) $row['target'] ? hodima_seo_advisor_url_link( (string) $row['target'], 'hodima-advisor__rival-url' ) : '—'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML ساخته‌شده و escape‌شده در hodima_seo_advisor_url_link ?></td>
							<td>
								<?php if ( $bad && ( null === $task || ! in_array( hodima_seo_advisor_task_state( $task ), HODIMA_SEO_ADVISOR_TASKS_CLOSED, true ) ) ) : ?>
									<?php hodima_seo_advisor_mark_form( hodima_seo_advisor_mark_id(), 'health', (string) $row['path'], rawurldecode( (string) $row['path'] ), [ 'items[]' => 'dead' ] ); ?>
								<?php elseif ( ! $bad ) : ?>
									<small class="hodima-advisor__muted">درست کار می‌کند</small>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
		$toggle = hodima_seo_advisor_marked_toggle( $hide_dead, [ 'tab' => 'health' ], 'آدرس' );
		if ( '' !== $toggle ) {
			echo '<div class="hodima-advisor__foot"><span>' . $toggle . '</span></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML امن‌شده در hodima_seo_advisor_action
		}
		// توضیح هر وضعیت پشت یک جمع‌شونده (SEO 3.2.5؛ چهار کادر خاکستری زیر جدول جا می‌گرفت)
		echo '<details class="hds-more hodima-advisor__explain"><summary class="hds-more__summary">توضیح هر وضعیت و چه کنید؟</summary><div class="hds-more__body">';
		foreach ( array_unique( array_column( $dead, 'state' ) ) as $state ) {
			echo '<div class="hodima-advisor__advice">' . esc_html( hodima_seo_advisor_health_state( (string) $state )['advice'] ) . '</div>';
		}
		echo '<p class="hodima-advisor__advice">بعد از ریدایرکت یا درست کردن آدرس، «انجام دادم» را بزنید: آدرس همان لحظه در خود سایت دوباره سنجیده می‌شود. «لازم نیست» یعنی عمدا حذف شده و جایگزین ندارد.</p>';
		echo '</div></details>';
	}
	hodima_seo_ui_card_close();

	// صفحه‌های بدون نمایش
	hodima_seo_ui_card_open( 'صفحه‌هایی که نمایش نگرفته‌اند', sprintf( 'نوشته، برگه و محصول منتشرشده (غیر noindex، بدون رمز) که در %s روز اخیر هیچ نمایشی در گوگل نداشته‌اند و دست‌کم %s روز از انتشارشان گذشته؛ قدیمی‌ترین اول.', $n( $status['days'] ), $n( HODIMA_SEO_ADVISOR_HEALTH_FRESH_DAYS ) ) );

	if ( ! $unseen && $hide_unseen > 0 ) {
		hodima_seo_ui_empty( 'کار بازی نمانده', sprintf( 'همه صفحه‌های بدون نمایش را علامت زده‌اید؛ با بررسی ایندکس گوگل سنجیده می‌شوند. %s', hodima_seo_advisor_marked_toggle( $hide_unseen, [ 'tab' => 'health' ], 'صفحه' ) ) );
	} elseif ( ! $unseen ) {
		hodima_seo_ui_empty( 'همه صفحه‌ها دیده شده‌اند', 'هر صفحه منتشرشده‌ای دست‌کم یک نمایش در گوگل گرفته است.' );
	} else {
		// صفحه‌بندی (SEO 3.0.1): تا ۱۰۰ صفحه ذخیره می‌شود ولی قبلا فقط ۳۰ تای اول دیده می‌شد
		$pages = (int) ceil( count( $unseen ) / HODIMA_SEO_ADVISOR_HEALTH_PER_PAGE );
		$paged = min( $pages, max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط صفحه‌بندی نمایش
		?>
		<div class="hds-table-wrap" tabindex="0" role="region" aria-label="صفحه‌های بدون نمایش">
			<table class="widefat striped hodima-advisor__terms hodima-advisor__cards">
				<thead><tr><th scope="col">صفحه</th><th scope="col">نوع</th><th scope="col">سن</th><th scope="col">کنش</th></tr></thead>
				<tbody>
					<?php foreach ( array_slice( $unseen, ( $paged - 1 ) * HODIMA_SEO_ADVISOR_HEALTH_PER_PAGE, HODIMA_SEO_ADVISOR_HEALTH_PER_PAGE ) as $row ) : ?>
						<?php
						$edit  = get_edit_post_link( (int) $row['id'], 'raw' );
						$task  = hodima_seo_advisor_task( (string) $row['path'], 'health_unseen', '' );
						$title = '' !== (string) $row['title'] ? (string) $row['title'] : rawurldecode( (string) $row['path'] );
						?>
						<tr>
							<th scope="row"><?php echo $title !== rawurldecode( (string) $row['path'] ) ? esc_html( $title ) . ' ' : ''; // عنوان فقط وقتی با خود مسیر فرق دارد (وگرنه آدرس دوبار دیده می‌شد) ?><?php echo hodima_seo_advisor_url_link( (string) $row['path'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML ساخته‌شده و escape‌شده در hodima_seo_advisor_url_link ?> <?php echo hodima_seo_advisor_task_badge( $task ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML امن‌شده در hodima_seo_advisor_task_badge ?></th>
							<td data-label="نوع"><?php echo esc_html( hodima_seo_advisor_kind_label( (string) $row['type'] ) ); ?></td>
							<td data-label="سن"><?php echo esc_html( sprintf( '%s روز', $n( $row['days'] ) ) ); ?></td>
							<td>
								<div class="hodima-advisor__row-acts hodima-advisor__row-acts--inline">
									<?php echo is_string( $edit ) && '' !== $edit ? hodima_seo_advisor_action( $edit, 'ویرایش صفحه', 'edit' ) : hodima_seo_advisor_action( hodima_seo_advisor_url( (string) $row['path'] ), 'دیدن در سایت', 'external', true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML امن‌شده در hodima_seo_advisor_action ?>
									<?php if ( null === $task || ! in_array( hodima_seo_advisor_task_state( $task ), HODIMA_SEO_ADVISOR_TASKS_CLOSED, true ) ) : ?>
										<?php hodima_seo_advisor_mark_form( hodima_seo_advisor_mark_id(), 'health', (string) $row['path'], $title, [ 'items[]' => 'unseen' ] ); ?>
									<?php endif; ?>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php $toggle = hodima_seo_advisor_marked_toggle( $hide_unseen, [ 'tab' => 'health' ], 'صفحه' ); ?>
		<?php if ( $pages > 1 || '' !== $toggle ) : ?>
			<div class="hodima-advisor__foot">
				<span>
					<?php echo esc_html( sprintf( 'نمایش %1$s از %2$s صفحه، قدیمی‌ترین اول', $n( count( array_slice( $unseen, ( $paged - 1 ) * HODIMA_SEO_ADVISOR_HEALTH_PER_PAGE, HODIMA_SEO_ADVISOR_HEALTH_PER_PAGE ) ) ), $n( count( $unseen ) ) ) ); ?>
					<?php echo $toggle; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML امن‌شده در hodima_seo_advisor_action ?>
				</span>
				<?php hodima_seo_ui_pager( $paged, $pages, static fn( int $i ): string => hodima_seo_advisor_page_url( [ 'tab' => 'health', 'tasks' => $show ? 'all' : '', 'paged' => $i > 1 ? $i : '' ] ) ); ?>
			</div>
		<?php endif; ?>
		<details class="hds-more hodima-advisor__explain"><summary class="hds-more__summary">چه کنید؟</summary><div class="hds-more__body">
		<div class="hodima-advisor__advice"><strong>چه کنید:</strong> برای هر صفحه بررسی کنید: noindex یا canonical به آدرس دیگر نباشد؛ در سایت‌مپ باشد؛ دست‌کم از ۲ تا ۳ صفحه مرتبط لینک داخلی بگیرد؛ و محتوایش کم یا تکراری نباشد. بعد در Search Console همان آدرس را در «بررسی آدرس» بگذارید و «درخواست ایندکس» بزنید و اینجا «انجام دادم» را بزنید: آدرس اول صف بررسی ایندکس می‌رود؛ نمایش یا ایندکس ← برطرف شد، ۳۰ روز بعد هنوز نه ← برمی‌گردد. اگر صفحه ارزشی ندارد، ادغام یا حذفش با ریدایرکت بهتر است.</div>
		</div></details>
		<?php
	}
	hodima_seo_ui_card_close();
}
