<?php
/**
 * ماژول «مشاور سئو» — تب «رقابت صفحه‌ها» در پیشخوان
 * Path: core/advisor/advisor-competition-view.php
 *
 * نوع صفحه «فهرست و جدول» با کالبد کیت SEO: ۴ کارت آمار ← کارت فهرست (نوار ابزار یک‌ردیفه:
 * نوع دستور، جستجو، CSV) ← ردیف جمع‌شونده برای هر کلمه که صفحه‌های رقیب، برنده، سهم نمایش و
 * دستور را نشان می‌دهد. طرح تأییدشده: Artifact «طرح رقابت صفحه‌ها (مشاور سئو)». فقط می‌خواند؛
 * چیزی را در سایت عوض نمی‌کند. بدون JS کار می‌کند (details بومی).
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
	return '' !== $title ? $title : (string) $page['path'];
}

/**
 * آدرس ویرایش صفحه (در صورت شناخته‌شدن)، وگرنه آدرس دیدن در سایت.
 *
 * @param array<string, mixed> $page
 * @return array{0: string, 1: string} آدرس و برچسب
 */
function hodima_seo_advisor_competition_page_link( array $page ): array {

	$kind = (string) $page['kind'];
	$id   = (int) $page['object_id'];

	if ( $id > 0 && post_type_exists( $kind ) && current_user_can( 'edit_post', $id ) ) {
		$url = get_edit_post_link( $id, 'raw' );
		if ( is_string( $url ) && '' !== $url ) {
			return [ $url, 'ویرایش صفحه' ];
		}
	}

	if ( $id > 0 && taxonomy_exists( $kind ) ) {
		$url = get_edit_term_link( $id, $kind );
		if ( is_string( $url ) && '' !== $url ) {
			return [ $url, 'ویرایش دسته' ];
		}
	}

	return [ home_url( (string) $page['path'] ), 'دیدن در سایت' ];
}

/**
 * محتوای تب «رقابت صفحه‌ها» (بعد از نوار تب‌ها).
 *
 * @param array<string, mixed> $status خروجی hodima_seo_advisor_status()
 */
function hodima_seo_advisor_render_competition( array $status ): void {

	$filters = hodima_seo_advisor_competition_filters( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط فیلتر نمایش؛ مقدارها پاک‌سازی می‌شوند
	$paged   = max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$all     = hodima_seo_advisor_competition();
	$result  = hodima_seo_advisor_competition( [ 'search' => $filters['q'], 'type' => $filters['type'], 'limit' => HODIMA_SEO_ADVISOR_COMPETITION_PER_PAGE, 'offset' => ( $paged - 1 ) * HODIMA_SEO_ADVISOR_COMPETITION_PER_PAGE ] );
	$n       = static fn( int|float $value ): string => number_format_i18n( $value );

	$counts = array_fill_keys( array_keys( hodima_seo_advisor_competition_types() ), 0 );
	foreach ( $all['rows'] as $group ) {
		++$counts[ $group['advice']['type'] ];
	}

	$empty = 0 === $all['total'];

	hodima_seo_ui_stats( [
		[ 'label' => 'کلمه با رقابت', 'value' => $empty ? '—' : esc_html( $n( $all['total'] ) ), 'empty' => $empty ],
		[ 'label' => 'صفحه درگیر', 'value' => $empty ? '—' : esc_html( $n( $all['pages'] ) ), 'empty' => $empty ],
		[ 'label' => 'کلیک بالقوه', 'value' => $empty ? '—' : esc_html( $n( $all['potential'] ) ), 'sub' => $empty ? '' : 'تخمین رفع رقابت', 'empty' => $empty ],
		[ 'label' => 'برنده روشن', 'value' => $empty ? '—' : esc_html( $n( $counts['strengthen'] ) ), 'empty' => $empty ],
	] );

	hodima_seo_ui_card_open( 'رقابت صفحه‌ها', sprintf( 'کلمه‌هایی که چند صفحه همین سایت برایشان نمایش می‌گیرند (داده %s روز اخیر)؛ مرتب بر اساس «کلیک بالقوه» رفع رقابت (تخمین).', $n( $status['days'] ) ) );

	if ( $empty ) {
		hodima_seo_ui_empty( 'رقابتی پیدا نشد', 'در این بازه هیچ کلمه‌ای نیست که دو صفحه برایش دست‌کم ۱۰۰ نمایش داشته باشند. بعد از دریافت داده تازه دوباره ببینید.' );
		hodima_seo_ui_card_close();
		return;
	}

	$keep = [ 'tab' => 'competition', 'ctype' => $filters['type'], 'q' => $filters['q'] ];
	?>
	<form method="get" class="hodima-advisor__toolbar">
		<input type="hidden" name="page" value="<?php echo esc_attr( HODIMA_SEO_ADVISOR_PAGE ); ?>">
		<input type="hidden" name="tab" value="competition">
		<?php if ( '' !== $filters['type'] ) : ?>
			<input type="hidden" name="ctype" value="<?php echo esc_attr( $filters['type'] ); ?>">
		<?php endif; ?>
		<nav class="hodima-advisor__views" aria-label="نوع دستور">
			<a class="hodima-advisor__view" href="<?php echo esc_url( hodima_seo_advisor_page_url( [ 'tab' => 'competition', 'q' => $filters['q'] ] ) ); ?>"<?php echo '' === $filters['type'] ? ' aria-current="page"' : ''; ?>>
				همه <span class="hodima-advisor__count"><?php echo esc_html( $n( $all['total'] ) ); ?></span>
			</a>
			<?php foreach ( hodima_seo_advisor_competition_types() as $type => $label ) : ?>
				<a class="hodima-advisor__view" href="<?php echo esc_url( hodima_seo_advisor_page_url( [ 'tab' => 'competition', 'ctype' => $type, 'q' => $filters['q'] ] ) ); ?>"<?php echo $type === $filters['type'] ? ' aria-current="page"' : ''; ?>>
					<?php echo esc_html( $label ); ?> <span class="hodima-advisor__count"><?php echo esc_html( $n( $counts[ $type ] ) ); ?></span>
				</a>
			<?php endforeach; ?>
		</nav>
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
		<span><?php echo esc_html( sprintf( 'نمایش %1$s از %2$s کلمه، مرتب‌شده بر اساس کلیک بالقوه رفع رقابت', $n( count( $result['rows'] ) ), $n( $result['total'] ) ) ); ?></span>
		<?php
		if ( $pages > 1 ) {
			echo '<nav class="hodima-advisor__pager" aria-label="صفحه‌بندی">';
			for ( $i = 1; $i <= $pages; $i++ ) {
				printf(
					'<a href="%1$s"%2$s>%3$s</a>',
					esc_url( hodima_seo_advisor_page_url( $keep + [ 'paged' => $i > 1 ? $i : '' ] ) ),
					$i === $paged ? ' aria-current="page"' : '',
					esc_html( $n( $i ) )
				);
			}
			echo '</nav>';
		}
		?>
	</div>
	<?php
	hodima_seo_ui_card_close();
}

/**
 * یک گروه رقابت: ردیف جمع‌شونده با جدول صفحه‌ها و کادر «چه کنید».
 *
 * @param array<string, mixed> $group
 */
function hodima_seo_advisor_render_competition_group( array $group ): void {

	$n      = static fn( int|float $value ): string => number_format_i18n( $value );
	$advice = $group['advice'];
	$label  = hodima_seo_advisor_competition_types()[ $advice['type'] ] ?? '';
	$class  = [ 'strengthen' => 'hds-pill', 'choose' => 'hds-pill hds-pill--warn', 'category' => 'hds-pill hds-pill--info' ][ $advice['type'] ] ?? 'hds-pill';
	?>
	<details class="hodima-advisor__group">
		<summary class="hodima-advisor__group-head">
			<strong class="hodima-advisor__group-term"><?php echo esc_html( (string) $group['term'] ); ?></strong>
			<span class="hodima-advisor__group-meta">
				<span><?php echo esc_html( sprintf( '%s نمایش', $n( $group['impressions'] ) ) ); ?></span>
				<span><?php echo esc_html( sprintf( '%s صفحه', $n( $group['count'] ) ) ); ?></span>
				<?php if ( $group['potential'] > 0 ) : ?>
					<span class="hodima-advisor__group-potential"><?php echo esc_html( sprintf( 'کلیک بالقوه ≈ %s', $n( $group['potential'] ) ) ); ?></span>
				<?php endif; ?>
			</span>
			<span class="<?php echo esc_attr( $class ); ?>"><?php echo esc_html( $label ); ?></span>
		</summary>
		<div class="hodima-advisor__group-body">
			<ul class="hodima-advisor__rivals">
				<?php foreach ( $group['pages'] as $i => $page ) : ?>
					<?php [ $url, $link_label ] = hodima_seo_advisor_competition_page_link( $page ); ?>
					<li class="hodima-advisor__rival<?php echo 0 === $i ? ' is-winner' : ''; ?>">
						<div class="hodima-advisor__rival-name">
							<strong><?php echo esc_html( hodima_seo_advisor_competition_page_title( $page ) ); ?></strong>
							<?php if ( 0 === $i ) : ?>
								<span class="hds-pill">برنده پیشنهادی</span>
							<?php endif; ?>
							<bdi class="hodima-advisor__rival-path"><?php echo esc_html( (string) $page['path'] ); ?></bdi>
						</div>
						<div class="hodima-advisor__rival-share">
							<span class="hodima-advisor__share-bar" aria-hidden="true"><span style="inline-size: <?php echo esc_attr( (string) (int) round( $page['share'] * 100 ) ); ?>%"></span></span>
							<span><?php echo esc_html( sprintf( '%s٪ نمایش', $n( round( $page['share'] * 100 ) ) ) ); ?></span>
						</div>
						<span class="hodima-advisor__rival-stat"><?php echo esc_html( sprintf( '%s کلیک', $n( $page['clicks'] ) ) ); ?></span>
						<span class="hodima-advisor__rival-stat"><?php echo esc_html( sprintf( 'رتبه %s', number_format_i18n( $page['position'], 1 ) ) ); ?></span>
						<a class="hodima-advisor__rival-link" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $link_label ); ?></a>
					</li>
				<?php endforeach; ?>
			</ul>
			<?php if ( $group['count'] > count( $group['pages'] ) ) : ?>
				<p class="hodima-advisor__more"><?php echo esc_html( sprintf( 'و %s صفحه دیگر با نمایش کمتر.', $n( $group['count'] - count( $group['pages'] ) ) ) ); ?></p>
			<?php endif; ?>
			<div class="hodima-advisor__advice">
				<strong><?php echo esc_html( $advice['title'] ); ?>:</strong>
				<?php echo esc_html( $advice['detail'] ); ?>
			</div>
		</div>
	</details>
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
		$pages = array_map( static fn( array $p ): string => $p['path'] . ' (' . round( $p['share'] * 100 ) . '%)', $group['pages'] );
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
				hodima_seo_advisor_csv_cell( (string) ( $group['pages'][0]['path'] ?? '' ) ),
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
