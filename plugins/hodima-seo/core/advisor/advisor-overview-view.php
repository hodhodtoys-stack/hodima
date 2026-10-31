<?php
/**
 * ماژول «دستار سئو» — نمای «همه ایرادها» (SEO 3.2.9)
 * Path: core/advisor/advisor-overview-view.php
 *
 * یک فهرست واحد: هر صفحه یک بار، با همه ایرادهایش از چهار منبع (ایرادهای فنی خزنده، پیشنهادهای گوگل،
 * صفحه‌های هم‌کلمه، آدرس‌های خراب)، اولین کار «چه کنید» و دکمه رفتن به نمای دقیق همان صفحه. فقط می‌خواند؛ علامت
 * «انجام شد / رد شد» در نمای دقیق هر منبع ثبت می‌شود (همان‌جا که گام‌ها هست).
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** ردیف در هر صفحه نمای «همه ایرادها». */
const HODIMA_SEO_ADVISOR_OVERVIEW_PER_PAGE = 25;

/** منبع‌ها ← برچسب فیلتر. */
const HODIMA_SEO_ADVISOR_OVERVIEW_SOURCES = [
	'crawl' => 'ایراد فنی',
	'opp'   => 'پیشنهاد گوگل',
	'comp'  => 'هم‌کلمه',
	'dead'  => 'آدرس خراب',
];

/**
 * ایرادهای هر صفحه از همه منبع‌ها.
 *
 * @return list<array{path: string, title: string, impressions: int, potential: int, items: list<array{src: string, label: string, fix: string, level: string, url: string}>}>
 */
function hodima_seo_advisor_overview_build(): array {

	$pages = [];
	$add   = static function ( string $path, string $src, string $label, string $fix, string $level, string $url ) use ( &$pages ): void {
		$pages[ $path ]['items'][] = [ 'src' => $src, 'label' => $label, 'fix' => $fix, 'level' => $level, 'url' => $url ];
	};

	foreach ( hodima_seo_advisor_crawl_issues() as $row ) {
		$pages[ $row['path'] ]['impressions'] = max( (int) ( $pages[ $row['path'] ]['impressions'] ?? 0 ), (int) $row['impressions'] );
		foreach ( $row['issues'] as $issue ) {
			$info = hodima_seo_advisor_crawl_issue_info( (string) $issue['code'] );
			$add( $row['path'], 'crawl', $info['label'], $info['fix'], $info['level'], hodima_seo_advisor_page_url( [ 'tab' => 'crawl', 'q' => $row['path'] ] ) );
		}
	}

	foreach ( hodima_seo_advisor_pages( [ 'has_advice' => true, 'limit' => 500 ] )['rows'] as $row ) {
		$path                       = (string) $row['path'];
		$pages[ $path ]['impressions'] = max( (int) ( $pages[ $path ]['impressions'] ?? 0 ), (int) $row['impressions'] );
		$pages[ $path ]['potential']   = (int) $row['potential'];
		$pages[ $path ]['title']       = (string) $row['title'];
		foreach ( $row['advice'] as $advice ) {
			$add( $path, 'opp', hodima_seo_advisor_rule_label( (string) ( $advice['rule'] ?? '' ) ), 'ویرایش صفحه را باز کنید؛ گام‌های رفع در کادر «دستار سئو» است.', 'warn', hodima_seo_advisor_page_url( [ 'q' => $path ] ) );
		}
	}

	foreach ( hodima_seo_advisor_competition_groups()['groups'] as $group ) {
		foreach ( (array) ( $group['pages'] ?? [] ) as $page ) {
			$path = (string) ( $page['path'] ?? '' );
			if ( '' !== $path ) {
				$add( $path, 'comp', sprintf( 'رقابت برای «%s»', (string) $group['term'] ), 'صفحه برنده را روشن کنید؛ دستور هر کلمه در نمای «صفحه‌های هم‌کلمه» است.', 'warn', hodima_seo_advisor_page_url( [ 'tab' => 'competition', 'q' => (string) $group['term'] ] ) );
			}
		}
	}

	foreach ( hodima_seo_advisor_health_dead_urls() as $row ) {
		if ( ! in_array( $row['state'], [ 'missing', 'gone', 'rule_broken' ], true ) ) {
			continue;
		}
		$pages[ $row['path'] ]['impressions'] = max( (int) ( $pages[ $row['path'] ]['impressions'] ?? 0 ), (int) $row['impressions'] );
		$add( $row['path'], 'dead', 'آدرس خراب در گوگل', 'rule_broken' === $row['state'] ? 'مقصد ریدایرکت این آدرس خراب است؛ اصلاحش کنید.' : 'ریدایرکت ۳۰۱ به نزدیک‌ترین صفحه درست بگذارید.', 'bad', hodima_seo_advisor_page_url( [ 'tab' => 'health' ] ) );
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
			'potential'   => (int) ( $data['potential'] ?? 0 ),
			'items'       => $data['items'],
		];
	}

	usort( $out, static fn( array $a, array $b ): int => [ $b['impressions'], count( $b['items'] ), $a['path'] ] <=> [ $a['impressions'], count( $a['items'] ), $b['path'] ] );

	return $out;
}

/**
 * شمار صفحه‌های دارای ایراد هر منبع (برای منوی فیلتر).
 *
 * @param list<array{items: list<array{src: string}>}> $pages
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

/** نمای «همه ایرادها». */
function hodima_seo_advisor_render_overview(): void {

	$n      = static fn( int|float $value ): string => number_format_i18n( $value );
	$pages  = hodima_seo_advisor_overview_build();
	$counts = hodima_seo_advisor_overview_counts( $pages );
	$src    = isset( $_GET['src'] ) ? sanitize_key( (string) $_GET['src'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط فیلتر نمایش
	$src    = isset( HODIMA_SEO_ADVISOR_OVERVIEW_SOURCES[ $src ] ) ? $src : '';
	$q      = isset( $_GET['q'] ) ? trim( sanitize_text_field( wp_unslash( (string) $_GET['q'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$paged  = max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	$rows = array_values( array_filter(
		$pages,
		static fn( array $page ): bool => ( '' === $src || in_array( $src, array_column( $page['items'], 'src' ), true ) )
			&& ( '' === $q || str_contains( mb_strtolower( rawurldecode( $page['path'] ) . ' ' . $page['title'] ), mb_strtolower( $q ) ) )
	) );

	hodima_seo_ui_card_open( 'همه ایرادها', 'هر صفحه یک بار، با همه ایرادهایش از چهار منبع. «دیدن گام‌ها» شما را به نمای دقیق همان ایراد می‌برد؛ «انجام شد / لازم نیست» همان‌جا زده می‌شود.' );

	$items = [ '' => [ 'همه منبع‌ها', count( $pages ), hodima_seo_advisor_page_url( [ 'tab' => 'overview', 'q' => $q ] ) ] ];
	foreach ( HODIMA_SEO_ADVISOR_OVERVIEW_SOURCES as $key => $label ) {
		$items[ $key ] = [ $label, $counts[ $key ], hodima_seo_advisor_page_url( [ 'tab' => 'overview', 'src' => $key, 'q' => $q ] ) ];
	}
	?>
	<form method="get" class="hodima-advisor__toolbar">
		<input type="hidden" name="page" value="<?php echo esc_attr( HODIMA_SEO_ADVISOR_PAGE ); ?>">
		<input type="hidden" name="tab" value="overview">
		<?php if ( '' !== $src ) : ?>
			<input type="hidden" name="src" value="<?php echo esc_attr( $src ); ?>">
		<?php endif; ?>
		<?php hodima_seo_advisor_filter_select( 'منبع ایراد', $items, $src ); ?>
		<div class="hodima-advisor__search">
			<label class="screen-reader-text" for="hodima-advisor-ov-q">جستجو در عنوان یا آدرس صفحه</label>
			<input type="search" id="hodima-advisor-ov-q" name="q" class="hds-input" value="<?php echo esc_attr( $q ); ?>" placeholder="جستجو در عنوان یا آدرس…">
			<button type="submit" class="hodima-advisor__go" aria-label="اعمال جستجو"><span class="dashicons dashicons-search" aria-hidden="true"></span></button>
		</div>
	</form>
	<?php

	if ( ! $rows ) {
		hodima_seo_ui_empty( 'اینجا چیزی نیست', $pages ? 'با این فیلتر صفحه‌ای ایراد ندارد. فیلتر را عوض کنید.' : 'ایراد بازی پیدا نشد. بعد از خزش یا دریافت داده تازه دوباره ببینید.' );
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
			$first = $page['items'][0];
			foreach ( $page['items'] as $item ) {
				if ( 'bad' === $item['level'] ) {
					$first = $item; // اولین کار: جدی‌ترین ایراد
					break;
				}
			}
			$shown = array_slice( $page['items'], 0, 4 );
			$more  = count( $page['items'] ) - count( $shown );
			?>
			<article class="hodima-advisor__ov-row">
				<div class="hodima-advisor__ov-head">
					<strong><?php echo esc_html( '' !== $page['title'] ? $page['title'] : rawurldecode( $page['path'] ) ); ?></strong>
					<?php echo hodima_seo_advisor_url_copy( $page['path'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML امن‌شده ?>
					<?php if ( $page['impressions'] > 0 ) : ?>
						<span class="hodima-advisor__ov-meta"><?php echo esc_html( sprintf( '%s نمایش در گوگل', $n( $page['impressions'] ) ) ); ?></span>
					<?php endif; ?>
				</div>
				<p class="hodima-advisor__ov-tags">
					<?php foreach ( $shown as $item ) : ?>
						<span class="hds-pill<?php echo 'bad' === $item['level'] ? ' hds-pill--error' : ' hds-pill--warn'; ?>"><?php echo esc_html( $item['label'] ); ?></span>
					<?php endforeach; ?>
					<?php if ( $more > 0 ) : ?>
						<span class="hds-pill"><?php echo esc_html( sprintf( '+%s', $n( $more ) ) ); ?></span>
					<?php endif; ?>
				</p>
				<p class="hodima-advisor__ov-fix"><strong>اولین کار:</strong> <?php echo esc_html( $first['fix'] ); ?></p>
				<p class="hodima-advisor__ov-act"><?php echo hodima_seo_advisor_action( $first['url'], 'دیدن گام‌ها', 'visibility' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML امن‌شده ?></p>
			</article>
		<?php endforeach; ?>
	</div>
	<div class="hodima-advisor__foot">
		<span><?php echo esc_html( sprintf( '%1$s صفحه با ایراد باز، بر اساس نمایش در گوگل', $n( $total ) ) ); ?></span>
		<?php hodima_seo_ui_pager( $paged, $last, static fn( int $i ): string => hodima_seo_advisor_page_url( array_filter( [ 'tab' => 'overview', 'src' => $src, 'q' => $q, 'paged' => $i > 1 ? $i : '' ] ) ) ); ?>
	</div>
	<?php
	hodima_seo_ui_card_close();
}
