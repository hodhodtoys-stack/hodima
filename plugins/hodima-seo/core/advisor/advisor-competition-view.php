<?php
/**
 * ماژول «دستار سئو» — تب «صفحه‌های هم‌کلمه» در پیشخوان
 * Path: core/advisor/advisor-competition-view.php
 *
 * نوع صفحه «فهرست و جدول» با کالبد کیت SEO: ۴ کارت آمار ← کارت فهرست (نوار ابزار یک‌ردیفه:
 * نوع دستور، جستجو، CSV) ← ردیف جمع‌شونده برای هر کلمه که صفحه‌های رقیب، برنده، سهم نمایش و
 * دستور را نشان می‌دهد. طرح تأییدشده: Artifact «طرح رقابت صفحه‌ها (دستار سئو)». فقط می‌خواند؛
 * چیزی را در سایت عوض نمی‌کند. بدون JS کار می‌کند (details بومی).
 * SEO 3.0.2: هر کلمه «انجام شد / رد شد» دارد (یک کار برای کل گروه، advisor-marks.php)؛ کلمه علامت‌خورده پنهان می‌شود.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

const HODIMA_SEO_ADVISOR_COMPETITION_PER_PAGE = 15;

add_action( 'admin_post_hodima_advisor_competition_csv', 'hodima_seo_advisor_handle_competition_csv' );

/** @return array<string, string> نوع دستور ← برچسب کوتاه */
function hodima_seo_advisor_competition_types(): array {
	return [
		'strengthen' => 'برنده روشن',
		'choose'     => 'هم‌وزن',
		'category'   => 'دسته اصلی شود',
	];
}

/**
 * فیلترهای فهرست از آدرس صفحه (فقط نمایش؛ پاک‌سازی‌شده).
 *
 * @param array<mixed> $query
 * @return array{type: string, q: string}
 */
function hodima_seo_advisor_competition_filters( array $query ): array {
	$type = sanitize_key( (string) ( $query['ctype'] ?? '' ) );
	return [
		'type' => isset( hodima_seo_advisor_competition_types()[ $type ] ) ? $type : '',
		'q'    => sanitize_text_field( (string) ( $query['q'] ?? '' ) ),
	];
}

/**
 * عنوان نمایشی یک صفحه رقیب: نام فعلی شیء، وگرنه عنوان ذخیره‌شده، وگرنه آدرس.
 *
 * @param array<string, mixed> $page
 */
function hodima_seo_advisor_competition_page_title( array $page ): string {
	$title = hodima_seo_advisor_row_title( [ 'kind' => $page['kind'], 'object_id' => $page['object_id'], 'title' => $page['title'] ] );
	return '' !== $title ? $title : ( '/' === (string) $page['path'] ? 'صفحه اصلی' : rawurldecode( (string) $page['path'] ) );
}

/**
 * آدرس ویرایش صفحه (در صورت شناخته‌شدن)، وگرنه آدرس دیدن در سایت.
 *
 * @param array<string, mixed> $page
 * @return array{0: string, 1: string, 2: string} آدرس، برچسب و Dashicon دکمه
 */
function hodima_seo_advisor_competition_page_link( array $page ): array {

	$kind = (string) $page['kind'];
	$id   = (int) $page['object_id'];

	if ( $id > 0 && post_type_exists( $kind ) && current_user_can( 'edit_post', $id ) ) {
		$url = get_edit_post_link( $id, 'raw' );
		if ( is_string( $url ) && '' !== $url ) {
			return [ $url, 'ویرایش صفحه', 'edit' ];
		}
	}

	if ( $id > 0 && taxonomy_exists( $kind ) ) {
		$url = get_edit_term_link( $id, $kind );
		if ( is_string( $url ) && '' !== $url ) {
			return [ $url, 'ویرایش دسته', 'edit' ];
		}
	}

	return [ hodima_seo_advisor_url( (string) $page['path'] ), 'دیدن در سایت', 'external' ]; // مسیر ذخیره‌شده پیشوند زیرپوشه را دارد؛ home_url() آن را دو بار می‌گذاشت
}

/**
 * محتوای تب «صفحه‌های هم‌کلمه» (بعد از نوار تب‌ها).
 *
 * @param array<string, mixed> $status خروجی hodima_seo_advisor_status()
 */
function hodima_seo_advisor_render_competition( array $status ): void {

	$filters = hodima_seo_advisor_competition_filters( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط فیلتر نمایش؛ مقدارها پاک‌سازی می‌شوند
	$paged   = max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$settled = ! empty( $_GET['resolved'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط نمای «حل‌شده‌ها»
	$base    = $settled ? [ 'resolved' => true ] : [];
	$built   = hodima_seo_advisor_competition_groups( $settled ); // یک بار برای شمارها و صفحه فعلی
	$hidden  = 0;
	if ( ! $settled ) {
		[ $built['groups'], $hidden ] = hodima_seo_advisor_competition_open_groups( $built['groups'], hodima_seo_advisor_show_marked() );
	}
	$all     = hodima_seo_advisor_competition_select( $built, $base );
	$result  = hodima_seo_advisor_competition_select( $built, $base + [ 'search' => $filters['q'], 'type' => $settled ? '' : $filters['type'], 'limit' => HODIMA_SEO_ADVISOR_COMPETITION_PER_PAGE, 'offset' => ( $paged - 1 ) * HODIMA_SEO_ADVISOR_COMPETITION_PER_PAGE ] );
	$n       = static fn( int|float $value ): string => number_format_i18n( $value );

	$counts = array_fill_keys( array_keys( hodima_seo_advisor_competition_types() ), 0 );
	foreach ( $all['rows'] as $group ) {
		if ( isset( $counts[ $group['advice']['type'] ] ) ) { // نمای حل‌شده‌ها نوع «settled» دارد (قبلا هشدار کلید ناموجود PHP)
			++$counts[ $group['advice']['type'] ];
		}
	}

	$empty = 0 === $all['total'];

	hodima_seo_ui_stats( [
		[ 'label' => 'کلمه با رقابت', 'value' => $empty ? '—' : esc_html( $n( $all['total'] ) ), 'empty' => $empty ],
		[ 'label' => 'صفحه درگیر', 'value' => $empty ? '—' : esc_html( $n( $all['pages'] ) ), 'empty' => $empty ],
		[ 'label' => 'کلیک بالقوه', 'value' => $empty ? '—' : esc_html( $n( $all['potential'] ) ), 'sub' => $empty ? '' : ( $settled ? 'ساختگی؛ اقدام لازم نیست' : 'تخمین رفع رقابت' ), 'empty' => $empty ],
		[ 'label' => 'برنده روشن', 'value' => $empty ? '—' : esc_html( $n( $counts['strengthen'] ) ), 'empty' => $empty ],
	] );

	hodima_seo_ui_card_open(
		$settled ? 'رقابت‌های حل‌شده' : 'صفحه‌های هم‌کلمه',
		$settled
			? 'کلمه‌هایی که ظاهرا چند صفحه برایشان نمایش می‌گیرند ولی یکی از صفحه‌ها در سایت ریدایرکت، ۴۰۴، noindex یا صفحه اصلی است؛ پس رقابت واقعی نیستند.'
			: sprintf( 'کلمه‌هایی که چند صفحه همین سایت برایشان در گوگل نمایش می‌گیرند (داده %s روز اخیر). وقتی چند صفحه برای یک کلمه رقابت کنند، گوگل نمی‌داند کدام را بالا ببرد؛ زیر هر کلمه نوشته کدام صفحه اصلی بماند و با بقیه چه کنید. مرتب بر اساس «کلیک بالقوه» (تخمین).', $n( $status['days'] ) )
	);

	if ( $settled ) {
		echo '<p class="hodima-advisor__advice">' . hodima_seo_advisor_action( hodima_seo_advisor_page_url( [ 'tab' => 'competition' ] ), 'برگشت به رقابت‌های واقعی', 'arrow-right-alt' ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML امن‌شده در hodima_seo_advisor_action
	} elseif ( $all['resolved']['groups'] > 0 ) {
		printf(
			'<p class="hodima-advisor__advice">%1$s گروه رقابت از این فهرست کنار رفت (حدود %2$s «کلیک بالقوه» ساختگی)، چون یکی از صفحه‌هایشان ریدایرکت، ۴۰۴، noindex یا صفحه اصلی است. <a href="%3$s">دیدن آن‌ها</a></p>',
			esc_html( $n( $all['resolved']['groups'] ) ),
			esc_html( $n( $all['resolved']['potential'] ) ),
			esc_url( hodima_seo_advisor_page_url( [ 'tab' => 'competition', 'resolved' => 1 ] ) )
		);
	}

	if ( $empty && $hidden > 0 ) {
		hodima_seo_ui_empty( 'کار بازی نمانده', sprintf( 'همه کلمه‌های رقابتی را «انجام دادم» یا «لازم نیست» علامت زده‌اید؛ با داده تازه گوگل سنجیده می‌شوند. %s', hodima_seo_advisor_marked_toggle( $hidden, [ 'tab' => 'competition' ], 'کلمه' ) ) );
		hodima_seo_ui_card_close();
		return;
	}

	if ( $empty ) {
		hodima_seo_ui_empty( 'رقابتی پیدا نشد', 'در این بازه هیچ کلمه‌ای نیست که دو صفحه برایش دست‌کم ۱۰۰ نمایش داشته باشند. بعد از دریافت داده تازه دوباره ببینید.' );
		hodima_seo_ui_card_close();
		return;
	}

	$keep = [ 'tab' => 'competition', 'ctype' => $settled ? '' : $filters['type'], 'q' => $filters['q'], 'resolved' => $settled ? 1 : '', 'tasks' => hodima_seo_advisor_show_marked() ? 'all' : '' ];
	?>
	<form method="get" class="hodima-advisor__toolbar">
		<input type="hidden" name="page" value="<?php echo esc_attr( HODIMA_SEO_ADVISOR_PAGE ); ?>">
		<input type="hidden" name="tab" value="competition">
		<?php if ( '' !== $filters['type'] ) : ?>
			<input type="hidden" name="ctype" value="<?php echo esc_attr( $filters['type'] ); ?>">
		<?php endif; ?>
		<?php if ( $settled ) : ?>
			<input type="hidden" name="resolved" value="1">
		<?php endif; ?>
		<?php if ( hodima_seo_advisor_show_marked() ) : ?>
			<input type="hidden" name="tasks" value="all">
		<?php endif; ?>
		<?php if ( ! $settled ) : ?>
		<?php
		$type_items = [ '' => [ 'همه', (int) $all['total'], hodima_seo_advisor_page_url( [ 'tab' => 'competition', 'q' => $filters['q'] ] ) ] ];
		foreach ( hodima_seo_advisor_competition_types() as $type => $label ) {
			$type_items[ $type ] = [ $label, (int) $counts[ $type ], hodima_seo_advisor_page_url( [ 'tab' => 'competition', 'ctype' => $type, 'q' => $filters['q'] ] ) ];
		}
		hodima_seo_advisor_filter_select( 'نوع دستور', $type_items, (string) $filters['type'] );
		?>
		<?php endif; ?>
		<div class="hodima-advisor__search">
			<label class="screen-reader-text" for="hodima-advisor-cq">جستجو در کلمه یا آدرس صفحه</label>
			<input type="search" id="hodima-advisor-cq" name="q" class="hds-input" value="<?php echo esc_attr( $filters['q'] ); ?>" placeholder="جستجو در کلمه یا آدرس…">
			<button type="submit" class="hodima-advisor__go" aria-label="اعمال جستجو"><span class="dashicons dashicons-search" aria-hidden="true"></span></button>
		</div>
		<a class="hds-btn hds-btn--outline" href="<?php echo esc_url( wp_nonce_url( add_query_arg( [ 'action' => 'hodima_advisor_competition_csv' ] + array_filter( [ 'ctype' => $filters['type'], 'q' => $filters['q'] ] ), admin_url( 'admin-post.php' ) ), 'hodima_advisor_competition_csv' ) ); ?>">خروجی CSV</a>
	</form>
	<?php

	if ( ! $result['rows'] ) {
		hodima_seo_ui_empty( 'اینجا چیزی نیست', 'با این فیلتر گروه رقابتی پیدا نشد. فیلتر را عوض کنید.' );
		hodima_seo_ui_card_close();
		return;
	}

	echo '<div class="hodima-advisor__groups">';
	foreach ( $result['rows'] as $group ) {
		hodima_seo_advisor_render_competition_group( $group );
	}
	echo '</div>';

	$pages = (int) ceil( $result['total'] / HODIMA_SEO_ADVISOR_COMPETITION_PER_PAGE );
	?>
	<div class="hodima-advisor__foot">
		<span>
			<?php echo esc_html( sprintf( 'نمایش %1$s از %2$s کلمه، مرتب‌شده بر اساس کلیک بالقوه رفع رقابت', $n( count( $result['rows'] ) ), $n( $result['total'] ) ) ); ?>
			<?php echo $settled ? '' : hodima_seo_advisor_marked_toggle( $hidden, array_diff_key( $keep, [ 'tasks' => true ] ), 'کلمه' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML امن‌شده در hodima_seo_advisor_action ?>
		</span>
		<?php hodima_seo_ui_pager( $paged, $pages, static fn( int $i ): string => hodima_seo_advisor_page_url( $keep + [ 'paged' => $i > 1 ? $i : '' ] ) ); ?>
	</div>
	<?php
	hodima_seo_ui_card_close();
}

/**
 * کلمه‌های علامت‌خورده (منتظر داده، برطرف، رد شد) پنهان، مگر با «نمایش علامت‌خورده‌ها». هر گروه کارش را همراه دارد.
 *
 * @param list<array<string, mixed>> $groups
 * @return array{0: list<array<string, mixed>>, 1: int}
 */
function hodima_seo_advisor_competition_open_groups( array $groups, bool $show ): array {

	$tasks  = hodima_seo_advisor_competition_tasks();
	$hidden = 0;
	$out    = [];

	foreach ( $groups as $group ) {
		$task   = $tasks[ hodima_seo_advisor_normalize( (string) $group['term'] ) ] ?? null;
		$closed = null !== $task && in_array( hodima_seo_advisor_task_state( $task ), HODIMA_SEO_ADVISOR_TASKS_CLOSED, true );
		if ( $closed && ! $show ) {
			++$hidden;
			continue;
		}
		$out[] = [ 'task' => $task, 'closed' => $closed ] + $group;
	}

	return [ $out, $hidden ];
}

/**
 * یک گروه رقابت: ردیف جمع‌شونده با جدول صفحه‌ها و کادر «چه کنید».
 *
 * @param array<string, mixed> $group
 */
function hodima_seo_advisor_render_competition_group( array $group ): void {

	$n      = static fn( int|float $value ): string => number_format_i18n( $value );
	$advice = $group['advice'];
	$label  = hodima_seo_advisor_competition_types()[ $advice['type'] ] ?? ( 'settled' === $advice['type'] ? 'حل‌شده' : '' );
	$class  = [ 'strengthen' => 'hds-pill', 'choose' => 'hds-pill hds-pill--warn', 'category' => 'hds-pill hds-pill--info', 'settled' => 'hds-pill hds-pill--info' ][ $advice['type'] ] ?? 'hds-pill';
	$task   = $group['task'] ?? null;
	$mark   = 'settled' !== $advice['type'] && empty( $group['closed'] ) && isset( $group['pages'][0]['path'] );
	?>
	<?php // دکمه‌ها بیرون از summary (عنصر کنش‌پذیر داخل summary تودرتو و نامعتبر است) ولی با CSS روی سربرگ گروه ?>
	<div class="hodima-advisor__group-wrap<?php echo $mark ? ' has-mark' : ''; ?>">
	<details class="hodima-advisor__group">
		<summary class="hodima-advisor__group-head">
			<strong class="hodima-advisor__group-term"><?php echo esc_html( (string) $group['term'] ); ?></strong>
			<span class="hodima-advisor__group-meta">
				<span><?php echo esc_html( sprintf( '%s نمایش', $n( $group['impressions'] ) ) ); ?></span>
				<span><?php echo esc_html( sprintf( '%s صفحه', $n( $group['count'] ) ) ); ?></span>
				<?php if ( $group['potential'] > 0 && 'settled' !== $advice['type'] ) : ?>
					<span class="hodima-advisor__group-potential"><?php echo esc_html( sprintf( 'کلیک بالقوه ≈ %s', $n( $group['potential'] ) ) ); ?></span>
				<?php endif; ?>
			</span>
			<span class="<?php echo esc_attr( $class ); ?>"><?php echo esc_html( $label ); ?></span>
			<?php echo hodima_seo_advisor_task_badge( $task ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML امن‌شده در hodima_seo_advisor_task_badge ?>
		</summary>
		<div class="hodima-advisor__group-body">
			<ul class="hodima-advisor__rivals">
				<?php foreach ( $group['pages'] as $i => $page ) : ?>
					<?php [ $url, $link_label, $link_icon ] = hodima_seo_advisor_competition_page_link( $page ); ?>
					<li class="hodima-advisor__rival<?php echo 0 === $i ? ' is-winner' : ''; ?>">
						<div class="hodima-advisor__rival-name">
							<strong><?php echo esc_html( hodima_seo_advisor_competition_page_title( $page ) ); ?></strong>
							<?php if ( '' !== (string) $page['kind'] ) : ?>
								<span class="hds-pill hds-pill--info"><?php echo esc_html( hodima_seo_advisor_kind_label( (string) $page['kind'] ) ); ?></span>
							<?php endif; ?>
							<?php if ( ! empty( $page['resolved'] ) ) : ?>
								<span class="hds-pill hds-pill--warn"><?php echo esc_html( hodima_seo_advisor_resolved_label( (string) $page['resolved'] ) ); ?></span>
							<?php elseif ( 0 === $i && 'settled' !== $advice['type'] ) : ?>
								<span class="hds-pill">برنده پیشنهادی</span>
							<?php endif; ?>
							<?php if ( isset( $page['term_links'] ) ) : ?>
								<small class="hodima-advisor__rival-links"><?php echo esc_html( sprintf( '%1$s لینک با متن «%2$s» · %3$s لینک ورودی', $n( $page['term_links'] ), $group['term'], $n( (int) ( $page['links_total'] ?? 0 ) ) ) ); ?></small>
							<?php endif; ?>
							<?php echo hodima_seo_advisor_url_link( (string) $page['path'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML ساخته‌شده و escape‌شده در hodima_seo_advisor_url_link ?>
						</div>
						<div class="hodima-advisor__rival-share">
							<span class="hodima-advisor__share-bar" aria-hidden="true"><span style="inline-size: <?php echo esc_attr( (string) (int) round( $page['share'] * 100 ) ); ?>%"></span></span>
							<span><?php echo esc_html( sprintf( '%s٪ نمایش', $n( round( $page['share'] * 100 ) ) ) ); ?></span>
						</div>
						<span class="hodima-advisor__rival-stat"><?php echo esc_html( sprintf( '%s کلیک', $n( $page['clicks'] ) ) ); ?></span>
						<span class="hodima-advisor__rival-stat"><?php echo esc_html( sprintf( 'رتبه %s', number_format_i18n( $page['position'], 1 ) ) ); ?></span>
						<?php echo hodima_seo_advisor_action( $url, $link_label, $link_icon, 'external' === $link_icon ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML امن‌شده در hodima_seo_advisor_action ?>
					</li>
				<?php endforeach; ?>
			</ul>
			<?php if ( $group['count'] > count( $group['pages'] ) ) : ?>
				<p class="hodima-advisor__more"><?php echo esc_html( sprintf( 'و %s صفحه دیگر با نمایش کمتر.', $n( $group['count'] - count( $group['pages'] ) ) ) ); ?></p>
			<?php endif; ?>
			<div class="hodima-advisor__advice">
				<strong><?php echo esc_html( $advice['title'] ); ?>:</strong>
				<?php echo hodima_seo_advisor_text_html( (string) $advice['detail'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML امن‌شده در hodima_seo_advisor_text_html (آدرس رقیب داخل یادداشت لینک‌ها) ?>
			</div>
			<?php
			// جهت لینک‌دهی فقط وقتی روشن است که برنده یا دسته هدف مشخص باشد (دستور «هم‌وزن» جهت ندارد)
			if ( in_array( $advice['type'], [ 'strengthen', 'category' ], true ) ) {
				$link_target = $group['pages'][0];
				foreach ( 'category' === $advice['type'] ? $group['pages'] : [] as $candidate ) {
					if ( in_array( (string) $candidate['kind'], [ 'product_cat', 'category' ], true ) ) {
						$link_target = $candidate;
						break;
					}
				}
				hodima_seo_advisor_render_links_box( (string) $group['term'], hodima_seo_advisor_competition_page_title( $link_target ) );
			}
			?>
		</div>
	</details>
	<?php if ( $mark ) : ?>
		<?php hodima_seo_advisor_mark_form( hodima_seo_advisor_mark_id(), 'competition', (string) $group['pages'][0]['path'], (string) $group['term'], [ 'term' => (string) $group['term'] ] ); ?>
	<?php endif; ?>
	</div>
	<?php
}

function hodima_seo_advisor_handle_competition_csv(): void {

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'اجازه این کار را ندارید.', 'دسترسی ندارید', [ 'response' => 403 ] );
	}

	check_admin_referer( 'hodima_advisor_competition_csv' );

	$filters = hodima_seo_advisor_competition_filters( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce بالا بررسی شد؛ مقدارها در همین تابع پاک‌سازی می‌شوند
	$result  = hodima_seo_advisor_competition( [ 'search' => $filters['q'], 'type' => $filters['type'] ] );

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="hodima-advisor-competition-' . gmdate( 'Y-m-d' ) . '.csv"' );

	$out = fopen( 'php://output', 'w' );
	if ( false === $out ) {
		exit;
	}

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- خروجی جریانی php://output
	fwrite( $out, "\xEF\xBB\xBF" ); // BOM: اکسل فارسی را درست باز می‌کند

	fputcsv( $out, [ 'کلمه', 'نمایش کل', 'کلیک کل', 'بهترین رتبه', 'تعداد صفحه', 'دستور', 'کلیک بالقوه', 'برنده', 'صفحه‌ها (آدرس و سهم نمایش)' ], ',', '"', '' );

	foreach ( $result['rows'] as $group ) {
		$full  = static fn( string $path ): string => rawurldecode( hodima_seo_advisor_url( $path ) ); // آدرس کامل، نه فقط مسیر
		$pages = array_map( static fn( array $p ): string => $full( $p['path'] ) . ' (' . round( $p['share'] * 100 ) . '%)', $group['pages'] );
		fputcsv(
			$out,
			[
				hodima_seo_advisor_csv_cell( (string) $group['term'] ),
				(string) $group['impressions'],
				(string) $group['clicks'],
				(string) $group['position'],
				(string) $group['count'],
				hodima_seo_advisor_csv_cell( (string) $group['advice']['title'] ),
				(string) $group['potential'],
				hodima_seo_advisor_csv_cell( isset( $group['pages'][0]['path'] ) ? $full( (string) $group['pages'][0]['path'] ) : '' ),
				hodima_seo_advisor_csv_cell( implode( ' | ', $pages ) ),
			],
			',',
			'"',
			''
		);
	}

	// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- خروجی جریانی php://output
	fclose( $out );
	exit;
}
