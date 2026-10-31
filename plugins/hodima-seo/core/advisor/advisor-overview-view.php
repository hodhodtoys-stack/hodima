<?php
/**
 * ماژول «دستار سئو» — صفحه «کارها»: یک فهرست واحد همه ایرادها (SEO 3.3.0)
 * Path: core/advisor/advisor-overview-view.php
 *
 * هر صفحه یک ردیف؛ زیر آن هر ایراد یک خط با نوع، «چه کنید» و دکمه‌های «انجام دادم / لازم نیست». شش منبع در یک فهرست:
 * ایراد فنی خزنده، پیشنهاد گوگل، هم‌کلمه، آدرس خراب، صفحه بدون نمایش، وضعیت ایندکس. علامت‌ها همان کارهای قبلی‌اند
 * (hodima_advisor_mark، advisor-marks.php)؛ پس سنجش و تاریخچه «کارهای انجام‌شده» همان می‌ماند.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** ردیف (صفحه) در هر صفحه فهرست «کارها». */
const HODIMA_SEO_ADVISOR_OVERVIEW_PER_PAGE = 20;

/** نوع ایراد ← برچسب فیلتر و چیپ ردیف. */
const HODIMA_SEO_ADVISOR_OVERVIEW_SOURCES = [
	'crawl'  => 'ایراد فنی',
	'opp'    => 'پیشنهاد گوگل',
	'comp'   => 'هم‌کلمه',
	'dead'   => 'آدرس خراب',
	'unseen' => 'بدون نمایش',
	'index'  => 'ایندکس',
];

/**
 * ایرادهای باز هر صفحه از همه منبع‌ها (مواردی که «انجام دادم / لازم نیست» خورده‌اند نمی‌آیند).
 *
 * @return list<array{path: string, title: string, impressions: int, items: list<array<string, mixed>>}>
 */
function hodima_seo_advisor_overview_build(): array {

	$pages     = [];
	$comp_done = hodima_seo_advisor_competition_tasks();

	$add = static function ( string $path, array $item ) use ( &$pages ): void {
		$pages[ $path ]['items'][] = $item;
	};
	$imp = static function ( string $path, int $value ) use ( &$pages ): void {
		$pages[ $path ]['impressions'] = max( (int) ( $pages[ $path ]['impressions'] ?? 0 ), $value );
	};

	// ایرادهای فنی خزنده
	foreach ( hodima_seo_advisor_crawl_issues() as $row ) {
		$path = $row['path'];
		$imp( $path, (int) $row['impressions'] );
		foreach ( $row['issues'] as $issue ) {
			$code = (string) $issue['code'];
			$info = hodima_seo_advisor_crawl_issue_info( $code );
			if ( '' === $info['fix'] || null !== hodima_seo_advisor_task_closed( $path, 'crawl_' . $code ) ) {
				continue;
			}
			$add( $path, [ 'src' => 'crawl', 'label' => $info['label'], 'text' => '' !== (string) $issue['detail'] ? $issue['detail'] . ' ' . $info['fix'] : $info['fix'], 'level' => $info['level'], 'source' => 'crawl', 'mark' => $code, 'term' => '' ] );
		}
	}

	// پیشنهادهای گوگل
	foreach ( hodima_seo_advisor_pages( [ 'has_advice' => true, 'limit' => 500 ] )['rows'] as $row ) {
		$path = (string) $row['path'];
		$imp( $path, (int) $row['impressions'] );
		$pages[ $path ]['title'] = (string) $row['title'];
		foreach ( $row['advice'] as $advice ) {
			$rule = (string) ( $advice['rule'] ?? '' );
			$term = (string) ( $advice['term'] ?? '' );
			if ( '' === $rule || null !== hodima_seo_advisor_task_closed( $path, $rule, $term ) ) {
				continue;
			}
			$add( $path, [ 'src' => 'opp', 'label' => hodima_seo_advisor_rule_label( $rule ), 'text' => (string) ( $advice['detail'] ?? '' ), 'level' => 'warn', 'source' => 'opportunities', 'mark' => $rule . '|' . $term, 'term' => '' ] );
		}
	}

	// صفحه‌های هم‌کلمه
	foreach ( hodima_seo_advisor_competition_groups()['groups'] as $group ) {
		$term = (string) $group['term'];
		$done = $comp_done[ hodima_seo_advisor_normalize( $term ) ] ?? null;
		if ( null !== $done && in_array( hodima_seo_advisor_task_state( $done ), HODIMA_SEO_ADVISOR_TASKS_CLOSED, true ) ) {
			continue;
		}
		foreach ( (array) ( $group['pages'] ?? [] ) as $page ) {
			$path = (string) ( $page['path'] ?? '' );
			if ( '' === $path ) {
				continue;
			}
			$add( $path, [ 'src' => 'comp', 'label' => sprintf( 'رقابت برای «%s»', $term ), 'text' => (string) ( $group['detail'] ?? 'چند صفحه برای یک کلمه رقابت می‌کنند. صفحه برنده را روشن کنید و بقیه را با لینک داخلی به آن وصل کنید.' ), 'level' => 'warn', 'source' => 'competition', 'mark' => '', 'term' => $term ] );
		}
	}

	// آدرس‌های خراب
	foreach ( hodima_seo_advisor_health_dead_urls() as $row ) {
		$path = (string) $row['path'];
		if ( ! in_array( $row['state'], [ 'missing', 'gone', 'rule_broken' ], true ) || null !== hodima_seo_advisor_task_closed( $path, 'health_dead' ) ) {
			continue;
		}
		$imp( $path, (int) $row['impressions'] );
		$add( $path, [ 'src' => 'dead', 'label' => 'آدرس خراب در گوگل', 'text' => 'rule_broken' === $row['state'] ? 'این آدرس هنوز در گوگل است ولی مقصد ریدایرکتش خراب است؛ مقصد را اصلاح کنید.' : 'این آدرس هنوز در گوگل است ولی در سایت پیدا نمی‌شود؛ در «ریدایرکت‌ها» یک ۳۰۱ به نزدیک‌ترین صفحه درست بگذارید.', 'level' => 'bad', 'source' => 'health', 'mark' => 'dead', 'term' => '' ] );
	}

	// صفحه‌های بدون نمایش
	foreach ( (array) ( hodima_seo_advisor_health_unseen()['unseen'] ?? [] ) as $row ) {
		$path = (string) $row['path'];
		if ( null !== hodima_seo_advisor_task_closed( $path, 'health_unseen' ) ) {
			continue;
		}
		$pages[ $path ]['title'] ??= (string) $row['title'];
		$add( $path, [ 'src' => 'unseen', 'label' => 'بدون نمایش در گوگل', 'text' => sprintf( 'این صفحه %s روز است در گوگل نمایش نگرفته. از صفحه‌های مرتبط به آن لینک بدهید و عنوان و متنش را به یک جستجوی مشخص نزدیک کنید.', number_format_i18n( (int) $row['days'] ) ), 'level' => 'warn', 'source' => 'health', 'mark' => 'unseen', 'term' => '' ] );
	}

	// وضعیت ایندکس
	foreach ( hodima_seo_advisor_inspections() as $path => $record ) {
		$state = hodima_seo_advisor_inspect_state( $record );
		if ( 'indexed' === $state || null !== hodima_seo_advisor_task_closed( (string) $path, 'index_problem' ) ) {
			continue;
		}
		$info = hodima_seo_advisor_inspect_state_info( $state );
		$add( (string) $path, [ 'src' => 'index', 'label' => 'ایندکس: ' . $info['label'], 'text' => $info['advice'], 'level' => 'warn', 'source' => 'indexing', 'mark' => '', 'term' => '' ] );
	}

	$out = [];
	foreach ( $pages as $path => $data ) {
		if ( empty( $data['items'] ) ) {
			continue;
		}
		$out[] = [
			'path'        => (string) $path,
			'title'       => (string) ( $data['title'] ?? '' ),
			'impressions' => (int) ( $data['impressions'] ?? 0 ),
			'items'       => $data['items'],
		];
	}

	usort( $out, static fn( array $a, array $b ): int => [ $b['impressions'], count( $b['items'] ), $a['path'] ] <=> [ $a['impressions'], count( $a['items'] ), $b['path'] ] );

	return $out;
}

/**
 * شمار صفحه‌های دارای ایراد هر نوع (برای منوی فیلتر).
 *
 * @param list<array{items: list<array<string, mixed>>}> $pages
 * @return array<string, int>
 */
function hodima_seo_advisor_overview_counts( array $pages ): array {
	$counts = array_fill_keys( array_keys( HODIMA_SEO_ADVISOR_OVERVIEW_SOURCES ), 0 );
	foreach ( $pages as $page ) {
		foreach ( array_unique( array_column( $page['items'], 'src' ) ) as $src ) {
			++$counts[ $src ];
		}
	}
	return $counts;
}

/**
 * دکمه «ویرایش صفحه» (اگر نوشته/محصول شناخته شد و اجازه ویرایش هست)، وگرنه «دیدن در سایت».
 *
 * @return array{0: string, 1: string, 2: string} آدرس، برچسب، Dashicon
 */
function hodima_seo_advisor_overview_link( string $kind, int $id, string $path ): array {

	if ( $id > 0 && post_type_exists( $kind ) && current_user_can( 'edit_post', $id ) ) {
		$url = get_edit_post_link( $id, 'raw' );
		if ( is_string( $url ) && '' !== $url ) {
			return [ $url, 'ویرایش صفحه', 'edit' ];
		}
	}

	return [ hodima_seo_advisor_url_full( $path ), 'دیدن در سایت', 'external' ];
}

/** خط کوچک «گرفتن داده تازه» (به‌جای کارت بزرگ بالای صفحه)؛ از SEO 3.3.1 داخل ردیف تب‌ها (hodima_seo_advisor_render_tabs). */
function hodima_seo_advisor_render_refresh_strip(): void {

	$has_key = '' !== hodima_seo_sc_key_json( HODIMA_SEO_SC_ADVISOR );
	$status  = hodima_seo_advisor_status();
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="hodima-advisor__strip">
		<input type="hidden" name="action" value="hodima_advisor_refresh">
		<input type="hidden" name="tab" value="overview">
		<input type="hidden" name="days" value="<?php echo esc_attr( (string) hodima_seo_advisor_days() ); ?>">
		<?php wp_nonce_field( 'hodima_advisor_refresh' ); ?>
		<span class="hodima-advisor__strip-text"><?php echo esc_html( $status['fetched'] > 0 ? sprintf( 'آخرین دریافت داده %s پیش', human_time_diff( $status['fetched'] ) ) : 'داده‌ای گرفته نشده' ); ?></span>
		<button type="submit" class="hds-btn hds-btn--outline"<?php disabled( ! $has_key ); ?>><span class="dashicons dashicons-update" aria-hidden="true"></span> گرفتن داده تازه</button>
	</form>
	<?php
}

/** صفحه «کارها». */
function hodima_seo_advisor_render_overview(): void {

	$n      = static fn( int|float $value ): string => number_format_i18n( $value );
	$pages  = hodima_seo_advisor_overview_build();
	$counts = hodima_seo_advisor_overview_counts( $pages );
	$src    = isset( $_GET['src'] ) ? sanitize_key( (string) $_GET['src'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط فیلتر نمایش
	$src    = isset( HODIMA_SEO_ADVISOR_OVERVIEW_SOURCES[ $src ] ) ? $src : '';
	$q      = isset( $_GET['q'] ) ? trim( sanitize_text_field( wp_unslash( (string) $_GET['q'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$paged  = max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	$rows = [];
	foreach ( $pages as $page ) {
		if ( '' !== $q && ! str_contains( mb_strtolower( rawurldecode( $page['path'] ) . ' ' . $page['title'] ), mb_strtolower( $q ) ) ) {
			continue;
		}
		if ( '' !== $src ) {
			$page['items'] = array_values( array_filter( $page['items'], static fn( array $item ): bool => $src === $item['src'] ) );
		}
		if ( $page['items'] ) {
			$rows[] = $page;
		}
	}

	hodima_seo_ui_card_open( 'کارها', sprintf( '%s ایراد باز روی %s صفحه، به ترتیب نمایش در گوگل. هر ایراد را رفع کنید و «انجام دادم» را بزنید؛ اگر لازم نیست، «لازم نیست».', $n( array_sum( array_map( static fn( array $p ): int => count( $p['items'] ), $pages ) ) ), $n( count( $pages ) ) ) );

	$items = [ '' => [ 'همه ایرادها', count( $pages ), hodima_seo_advisor_page_url( [ 'q' => $q ] ) ] ];
	foreach ( HODIMA_SEO_ADVISOR_OVERVIEW_SOURCES as $key => $label ) {
		if ( $counts[ $key ] > 0 || $key === $src ) {
			$items[ $key ] = [ $label, $counts[ $key ], hodima_seo_advisor_page_url( [ 'src' => $key, 'q' => $q ] ) ];
		}
	}
	?>
	<form method="get" class="hodima-advisor__toolbar">
		<input type="hidden" name="page" value="<?php echo esc_attr( HODIMA_SEO_ADVISOR_PAGE ); ?>">
		<?php if ( '' !== $src ) : ?>
			<input type="hidden" name="src" value="<?php echo esc_attr( $src ); ?>">
		<?php endif; ?>
		<?php hodima_seo_advisor_filter_chips( 'نوع ایراد', $items, $src ); ?>
		<div class="hodima-advisor__search">
			<label class="screen-reader-text" for="hodima-advisor-ov-q">جستجو در عنوان یا آدرس صفحه</label>
			<input type="search" id="hodima-advisor-ov-q" name="q" class="hds-input" value="<?php echo esc_attr( $q ); ?>" placeholder="جستجو در عنوان یا آدرس…">
			<button type="submit" class="hodima-advisor__go" aria-label="اعمال جستجو"><span class="dashicons dashicons-search" aria-hidden="true"></span></button>
		</div>
	</form>
	<?php

	if ( ! $rows ) {
		hodima_seo_ui_empty( $pages ? 'اینجا چیزی نیست' : 'کار بازی نمانده', $pages ? 'با این فیلتر ایرادی نیست. فیلتر را عوض کنید.' : 'همه ایرادها رفع یا علامت زده شده‌اند. بعد از خزش شبانه یا «گرفتن داده تازه» دوباره ببینید.' );
		hodima_seo_advisor_render_overview_foot( 0, $n );
		hodima_seo_ui_card_close();
		return;
	}

	$total = count( $rows );
	$last  = max( 1, (int) ceil( $total / HODIMA_SEO_ADVISOR_OVERVIEW_PER_PAGE ) );
	$paged = min( $paged, $last );
	$slice = array_slice( $rows, ( $paged - 1 ) * HODIMA_SEO_ADVISOR_OVERVIEW_PER_PAGE, HODIMA_SEO_ADVISOR_OVERVIEW_PER_PAGE );
	?>
	<div class="hodima-advisor__ov">
		<?php foreach ( $slice as $page ) : ?>
			<?php
			$row  = hodima_seo_advisor_page( $page['path'] );
			$link = hodima_seo_advisor_overview_link( (string) ( $row['kind'] ?? '' ), (int) ( $row['object_id'] ?? 0 ), $page['path'] );
			$name = '' !== $page['title'] ? $page['title'] : rawurldecode( $page['path'] );
			?>
			<article class="hodima-advisor__ov-row">
				<div class="hodima-advisor__ov-head">
					<strong><?php echo esc_html( $name ); ?></strong>
					<?php echo hodima_seo_advisor_url_copy( $page['path'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML امن‌شده ?>
					<?php if ( $page['impressions'] > 0 ) : ?>
						<span class="hodima-advisor__ov-meta"><?php echo esc_html( sprintf( '%s نمایش', $n( $page['impressions'] ) ) ); ?></span>
					<?php endif; ?>
					<?php echo hodima_seo_advisor_action( $link[0], $link[1], $link[2] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML امن‌شده ?>
				</div>
				<ul class="hodima-advisor__ov-list">
					<?php foreach ( $page['items'] as $item ) : ?>
						<li class="hodima-advisor__ov-item">
							<div class="hodima-advisor__ov-what">
								<span class="hds-pill <?php echo 'bad' === $item['level'] ? 'hds-pill--error' : 'hds-pill--warn'; ?>"><?php echo esc_html( $item['label'] ); ?></span>
								<p><?php echo esc_html( $item['text'] ); ?></p>
							</div>
							<?php
							$hidden = [ 'term' => (string) $item['term'] ];
							if ( '' !== (string) $item['mark'] ) {
								$hidden['items[]'] = (string) $item['mark'];
							}
							hodima_seo_advisor_mark_form( hodima_seo_advisor_mark_id(), (string) $item['source'], $page['path'], $name . ': ' . $item['label'], $hidden );
							?>
						</li>
					<?php endforeach; ?>
				</ul>
			</article>
		<?php endforeach; ?>
	</div>
	<?php
	hodima_seo_advisor_render_overview_foot( $total, $n, $paged, $last, $src, $q );
	hodima_seo_ui_card_close();
}

/** پای فهرست: شمار، پیوند «کارهای انجام‌شده» و صفحه‌بندی. */
function hodima_seo_advisor_render_overview_foot( int $total, callable $n, int $paged = 1, int $last = 1, string $src = '', string $q = '' ): void {
	?>
	<div class="hodima-advisor__foot">
		<span>
			<?php echo esc_html( $total > 0 ? sprintf( 'نمایش %s صفحه', $n( $total ) ) : '' ); ?>
			<?php echo hodima_seo_advisor_action( hodima_seo_advisor_page_url( [ 'tab' => 'tasks' ] ), 'کارهای انجام‌شده و نتیجه‌شان', 'yes-alt' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML امن‌شده ?>
		</span>
		<?php if ( $last > 1 ) : ?>
			<?php hodima_seo_ui_pager( $paged, $last, static fn( int $i ): string => hodima_seo_advisor_page_url( array_filter( [ 'src' => $src, 'q' => $q, 'paged' => $i > 1 ? $i : '' ] ) ) ); ?>
		<?php endif; ?>
	</div>
	<?php
}
