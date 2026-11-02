<?php
/**
 * ماژول «دستار سئو» — تب «ایندکس»
 * Path: core/advisor/advisor-inspect-view.php
 *
 * داده از advisor-inspect.php. کالبد: ۴ کارت آمار ← کارت «وضعیت ایندکس آدرس‌های مهم» (یک کنش اصلی:
 * «بررسی ۲۰ آدرس بعدی»، جدول مشکل‌دارها با وضعیت و آخرین خزش، دستور هر وضعیت). فقط می‌خواند و دستور
 * می‌دهد؛ چیزی را در سایت یا Search Console عوض نمی‌کند. SEO 3.0.2: هر آدرس مشکل‌دار «انجام شد / رد شد» دارد
 * (advisor-marks.php)؛ «انجام دادم» آدرس را اول صف بررسی بعدی می‌گذارد.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * برچسب، رنگ نشان، اولویت و دستور هر وضعیت ایندکس.
 *
 * @return array{label: string, class: string, weight: int, advice: string}
 */
function hodima_seo_advisor_inspect_state_info( string $state ): array {

	$warn = 'hds-pill hds-pill--warn';
	$info = 'hds-pill hds-pill--info';

	return [
		'crawled_not_indexed'    => [ 'label' => 'خزیده شد ولی ایندکس نشد', 'class' => $warn, 'weight' => 0, 'advice' => 'گوگل این صفحه‌ها را دیده ولی ارزش ایندکس ندیده است. محتوا را کامل‌تر و منحصربه‌فردتر کنید، دست‌کم از ۲ تا ۳ صفحه مرتبط لینک داخلی بدهید، صفحه‌های تکراری را ادغام کنید؛ بعد در Search Console «درخواست ایندکس» بزنید.' ],
		'discovered_not_indexed' => [ 'label' => 'کشف شد ولی خزیده نشده', 'class' => $warn, 'weight' => 1, 'advice' => 'گوگل از وجود این صفحه‌ها خبر دارد ولی هنوز سراغشان نرفته. از صفحه‌های قوی سایت لینک داخلی بدهید، مطمئن شوید در سایت‌مپ‌اند و سرعت سرور خوب است؛ کمی صبر هم لازم است.' ],
		'noindex'                => [ 'label' => 'noindex', 'class' => $warn, 'weight' => 2, 'advice' => 'این صفحه‌ها noindex دارند. اگر نباید باشند، گزینه noindex را در سئوباکس یا تنظیمات همان صفحه بردارید.' ],
		'robots'                 => [ 'label' => 'بسته با robots.txt', 'class' => $warn, 'weight' => 2, 'advice' => 'robots.txt اجازه خزش این آدرس‌ها را نمی‌دهد. اگر نباید بسته باشند، قانون را در «robots.txt» بردارید.' ],
		'soft404'                => [ 'label' => '۴۰۴ نرم', 'class' => $warn, 'weight' => 3, 'advice' => 'گوگل این صفحه‌ها را شبیه صفحه خالی یا ۴۰۴ می‌بیند. محتوای واقعی بگذارید، یا اگر ارزشی ندارند ۴۰۴ یا ریدایرکت کنید.' ],
		'not_found'              => [ 'label' => '۴۰۴', 'class' => $warn, 'weight' => 3, 'advice' => 'این آدرس‌ها در سایت ۴۰۴ هستند؛ اگر جایگزین دارند در «ریدایرکت‌ها» با ۳۰۱ ببرید.' ],
		'server_error'           => [ 'label' => 'خطای سرور', 'class' => $warn, 'weight' => 3, 'advice' => 'سرور هنگام خزش خطا داده است. لاگ خطای سرور و سرعت و بار آن را بررسی کنید.' ],
		'duplicate'              => [ 'label' => 'تکراری / canonical دیگر', 'class' => $info, 'weight' => 4, 'advice' => 'گوگل صفحه دیگری را «اصلی» دانسته. اگر درست است، لینک‌های داخلی را به همان بدهید؛ اگر نه، canonical را درست کنید یا دو صفحه را ادغام کنید (تب «صفحه‌های هم‌کلمه»).' ],
		'redirect'               => [ 'label' => 'ریدایرکت', 'class' => $info, 'weight' => 5, 'advice' => 'این آدرس‌ها ریدایرکت می‌شوند؛ لینک‌های داخلی را به آدرس نهایی بدهید.' ],
		'unknown_to_google'      => [ 'label' => 'برای گوگل ناشناخته', 'class' => $warn, 'weight' => 1, 'advice' => 'گوگل هنوز این آدرس را نمی‌شناسد. آن را در سایت‌مپ بگذارید، لینک داخلی بدهید و «درخواست ایندکس» بزنید.' ],
		'other'                  => [ 'label' => 'نیازمند بررسی', 'class' => $info, 'weight' => 6, 'advice' => 'وضعیت این آدرس‌ها را در Search Console با «بررسی آدرس» ببینید.' ],
		'indexed'                => [ 'label' => 'ایندکس شده', 'class' => 'hds-pill', 'weight' => 9, 'advice' => '' ],
	][ $state ] ?? [ 'label' => $state, 'class' => $info, 'weight' => 6, 'advice' => '' ];
}

/**
 * محتوای تب «ایندکس».
 */
function hodima_seo_advisor_render_indexing(): void {

	$records = hodima_seo_advisor_inspections();
	$n       = static fn( int|float $value ): string => number_format_i18n( $value );
	$empty   = ! $records;

	$states   = [];
	$mismatch = 0;
	foreach ( $records as $path => $record ) {
		$states[ $path ] = hodima_seo_advisor_inspect_state( $record );
		if ( hodima_seo_advisor_inspect_canonical_mismatch( $record ) ) {
			++$mismatch;
		}
	}

	$indexed = count( array_filter( $states, static fn( string $s ): bool => 'indexed' === $s ) );
	$problem = count( $states ) - $indexed;
	$pending = hodima_seo_advisor_inspect_pending();

	hodima_seo_ui_stats( [
		[ 'label' => 'بررسی‌شده', 'value' => $empty ? '—' : esc_html( $n( count( $records ) ) ), 'sub' => esc_html( sprintf( '%s منتظر بررسی', $n( $pending ) ) ), 'empty' => $empty ],
		[ 'label' => 'ایندکس‌شده', 'value' => $empty ? '—' : esc_html( $n( $indexed ) ), 'empty' => $empty ],
		[ 'label' => 'نیازمند بررسی', 'value' => $empty ? '—' : esc_html( $n( $problem ) ), 'empty' => $empty ],
		[ 'label' => 'canonical متفاوت', 'value' => $empty ? '—' : esc_html( $n( $mismatch ) ), 'sub' => $empty ? '' : 'گوگل غیر از شما انتخاب کرده', 'empty' => $empty ],
	] );

	hodima_seo_ui_card_open( 'وضعیت ایندکس آدرس‌های مهم', 'از گوگل می‌پرسد هر آدرس ایندکس شده یا نه و اگر نه چرا؛ اول صفحه‌های بدون نمایش. هر آدرس هر ۱۴ روز؛ خودکار روزی دو بار ۲۰ آدرس.' );
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="hodima-advisor__form">
		<input type="hidden" name="action" value="hodima_advisor_inspect_now">
		<?php wp_nonce_field( 'hodima_advisor_inspect_now' ); ?>
		<button type="submit" class="hds-btn"<?php disabled( 0 === $pending ); ?>><span class="dashicons dashicons-search" aria-hidden="true"></span> <?php echo esc_html( $pending > 0 ? sprintf( 'بررسی %s آدرس بعدی', $n( min( HODIMA_SEO_ADVISOR_INSPECT_BATCH, $pending ) ) ) : 'همه آدرس‌های صف بررسی شده‌اند' ); ?></button>
	</form>
	<?php

	if ( $empty ) {
		hodima_seo_ui_empty( 'هنوز آدرسی بررسی نشده', 'دکمه بالا را بزنید یا تا بررسی خودکار بعدی صبر کنید. به داده «گرفتن داده تازه» و property سرچ کنسول نیاز دارد.' );
		hodima_seo_ui_card_close();
		return;
	}

	$problems = array_filter( $records, static fn( array $r, string $path ): bool => 'indexed' !== $states[ $path ], ARRAY_FILTER_USE_BOTH );
	$show     = hodima_seo_advisor_show_marked();
	$hidden   = 0;
	foreach ( array_keys( $problems ) as $path ) {
		if ( ! $show && null !== hodima_seo_advisor_task_closed( (string) $path, 'index_problem' ) ) {
			unset( $problems[ $path ] );
			++$hidden;
		}
	}
	uksort(
		$problems,
		static fn( string $a, string $b ): int => [ hodima_seo_advisor_inspect_state_info( $states[ $a ] )['weight'], -( (int) $problems[ $a ]['checked'] ) ] <=> [ hodima_seo_advisor_inspect_state_info( $states[ $b ] )['weight'], -( (int) $problems[ $b ]['checked'] ) ]
	);

	if ( ! $problems && $hidden > 0 ) {
		hodima_seo_ui_empty( 'کار بازی نمانده', sprintf( 'همه آدرس‌های مشکل‌دار را علامت زده‌اید؛ در بررسی بعدی گوگل سنجیده می‌شوند. %s', hodima_seo_advisor_marked_toggle( $hidden, [ 'tab' => 'indexing' ], 'آدرس' ) ) );
		hodima_seo_ui_card_close();
		return;
	}

	if ( ! $problems ) {
		hodima_seo_ui_empty( 'همه آدرس‌های بررسی‌شده ایندکس‌اند', 'مشکلی در بررسی‌های تا الان پیدا نشد.' );
		hodima_seo_ui_card_close();
		return;
	}

	// صفحه‌بندی (SEO 3.0.1): تا ۶۰۰ آدرس بررسی می‌شود ولی قبلا فقط ۴۰ مشکل اول دیده می‌شد
	$pages = (int) ceil( count( $problems ) / HODIMA_SEO_ADVISOR_HEALTH_PER_PAGE );
	$paged = min( $pages, max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط صفحه‌بندی نمایش
	?>
	<div class="hds-table-wrap" tabindex="0" role="region" aria-label="آدرس‌های نیازمند بررسی ایندکس">
		<table class="widefat striped hodima-advisor__terms hodima-advisor__cards">
			<thead><tr><th scope="col">آدرس</th><th scope="col">وضعیت</th><th scope="col">آخرین خزش</th><th scope="col">canonical گوگل</th><th scope="col">کنش</th></tr></thead>
			<tbody>
				<?php foreach ( array_slice( $problems, ( $paged - 1 ) * HODIMA_SEO_ADVISOR_HEALTH_PER_PAGE, HODIMA_SEO_ADVISOR_HEALTH_PER_PAGE, true ) as $path => $record ) : ?>
					<?php
					$info   = hodima_seo_advisor_inspect_state_info( $states[ $path ] );
					$crawl  = '' !== (string) $record['last_crawl'] ? strtotime( (string) $record['last_crawl'] ) : false;
					$google = hodima_seo_advisor_inspect_canonical_mismatch( $record ) ? hodima_seo_advisor_path_key( (string) $record['google_canonical'] ) : null;
					$task   = hodima_seo_advisor_task( (string) $path, 'index_problem', '' );
					$title  = hodima_seo_advisor_breakdown_page_title( (string) $path );
					?>
					<tr>
						<th scope="row"><?php echo $title !== rawurldecode( (string) $path ) ? esc_html( $title ) . ' ' : ''; // عنوان فقط وقتی با خود مسیر فرق دارد ?><?php echo hodima_seo_advisor_url_link( (string) $path ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML ساخته‌شده و escape‌شده در hodima_seo_advisor_url_link ?> <?php echo hodima_seo_advisor_task_badge( $task ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML امن‌شده در hodima_seo_advisor_task_badge ?></th>
						<td><span class="<?php echo esc_attr( $info['class'] ); ?>"><?php echo esc_html( $info['label'] ); ?></span></td>
						<td data-label="آخرین خزش"><?php echo false !== $crawl ? esc_html( hodima_seo_advisor_task_date( $crawl ) ) : '—'; ?></td>
						<td data-label="canonical گوگل"><?php echo null !== $google ? hodima_seo_advisor_url_link( $google, 'hodima-advisor__rival-url' ) : '—'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML ساخته‌شده و escape‌شده در hodima_seo_advisor_url_link ?></td>
						<td>
							<?php if ( null === $task || ! in_array( hodima_seo_advisor_task_state( $task ), HODIMA_SEO_ADVISOR_TASKS_CLOSED, true ) ) : ?>
								<?php hodima_seo_advisor_mark_form( hodima_seo_advisor_mark_id(), 'indexing', (string) $path, '' !== $title ? $title : rawurldecode( (string) $path ) ); ?>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php $toggle = hodima_seo_advisor_marked_toggle( $hidden, [ 'tab' => 'indexing' ], 'آدرس' ); ?>
	<?php if ( $pages > 1 || '' !== $toggle ) : ?>
		<div class="hodima-advisor__foot">
			<span>
				<?php echo esc_html( sprintf( 'نمایش %1$s از %2$s آدرس نیازمند بررسی', $n( count( array_slice( $problems, ( $paged - 1 ) * HODIMA_SEO_ADVISOR_HEALTH_PER_PAGE, HODIMA_SEO_ADVISOR_HEALTH_PER_PAGE ) ) ), $n( count( $problems ) ) ) ); ?>
				<?php echo $toggle; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML امن‌شده در hodima_seo_advisor_action ?>
			</span>
			<?php hodima_seo_ui_pager( $paged, $pages, static fn( int $i ): string => hodima_seo_advisor_page_url( [ 'tab' => 'indexing', 'tasks' => $show ? 'all' : '', 'paged' => $i > 1 ? $i : '' ] ) ); ?>
		</div>
	<?php endif; ?>
	<details class="hds-more hodima-advisor__explain">
		<summary class="hds-more__summary">توضیح هر وضعیت و چه کنید؟</summary>
		<div class="hds-more__body">
			<p class="hodima-advisor__advice">بعد از رفع، «انجام دادم» را بزنید: آدرس اول صف بررسی بعدی گوگل می‌رود. ایندکس شد ← برطرف؛ گوگل دوباره خواند و هنوز نه ← با دلیلش به «کارها» ← «نیاز به کار» برمی‌گردد.</p>
			<?php
			foreach ( array_unique( array_map( static fn( string $path ): string => $states[ $path ], array_keys( $problems ) ) ) as $state ) {
				$advice = hodima_seo_advisor_inspect_state_info( $state );
				if ( '' !== $advice['advice'] ) {
					echo '<div class="hodima-advisor__advice"><strong>' . esc_html( $advice['label'] ) . ':</strong> ' . esc_html( $advice['advice'] ) . '</div>';
				}
			}
			?>
		</div>
	</details>
	<?php

	hodima_seo_ui_card_close();
}
