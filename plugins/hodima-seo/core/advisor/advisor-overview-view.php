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
 * @return list<array{path: string, title: string, impressions: int, items: list<array<string, mixed>>, sales: int, score: float}>
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
			$add( $path, [ 'src' => 'crawl', 'label' => $info['label'], 'text' => '' !== (string) $issue['detail'] ? $issue['detail'] . ' ' . $info['fix'] : $info['fix'], 'detail' => (string) $issue['detail'], 'images' => (array) ( $issue['images'] ?? [] ), 'total' => (int) ( $issue['total'] ?? 0 ), 'targets' => (array) ( $issue['items'] ?? [] ), 'level' => $info['level'], 'source' => 'crawl', 'mark' => $code, 'term' => '' ] );
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
			$add( $path, [ 'src' => 'opp', 'label' => hodima_seo_advisor_rule_label( $rule ), 'text' => (string) ( $advice['detail'] ?? '' ), 'level' => 'warn', 'source' => 'opportunities', 'mark' => $rule . '|' . $term, 'term' => '', 'potential' => (int) ( $advice['potential'] ?? 0 ) ] );
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
	// نتیجه ذخیره‌شده دریافت روزانه (SEO 3.4.3): تا ۳.۴.۲ هر بازدید «کارها» ۲۰۰ آدرس را با روتر و ۳٬۰۰۰ نوشته را از نو می‌سنجید
	$health = hodima_seo_advisor_health();
	foreach ( (array) ( $health['dead'] ?? [] ) as $row ) {
		$path = (string) $row['path'];
		if ( ! in_array( $row['state'], [ 'missing', 'gone', 'rule_broken' ], true ) || null !== hodima_seo_advisor_task_closed( $path, 'health_dead' ) ) {
			continue;
		}
		$imp( $path, (int) $row['impressions'] );
		$add( $path, [ 'src' => 'dead', 'label' => 'آدرس خراب در گوگل', 'text' => 'rule_broken' === $row['state'] ? 'این آدرس هنوز در گوگل است ولی مقصد ریدایرکتش خراب است؛ مقصد را اصلاح کنید.' : 'این آدرس هنوز در گوگل است ولی در سایت پیدا نمی‌شود؛ در «ریدایرکت‌ها» یک ۳۰۱ به نزدیک‌ترین صفحه درست بگذارید.', 'level' => 'bad', 'source' => 'health', 'mark' => 'dead', 'term' => '' ] );
	}

	// صفحه‌های بدون نمایش
	foreach ( (array) ( $health['unseen'] ?? [] ) as $row ) {
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

	return hodima_seo_advisor_priority_rank( $out ); // به ترتیب اولویت: بیشترین اثر با کمترین زحمت اول (SEO 3.4.0؛ قبلا فقط نمایش گوگل)
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

/** شناسه ردیف صفحه (پیوند «دیدن و رفع» کادر «۵ کار مهم»). */
function hodima_seo_advisor_row_anchor( string $path ): string {
	return 'hodima-ov-' . substr( md5( $path ), 0, 10 );
}

/** نشان کوچک اولویت (بالا، متوسط، کم). */
function hodima_seo_advisor_priority_chip( string $band ): string {

	$label = [ 'high' => 'اولویت بالا', 'mid' => 'اولویت متوسط', 'low' => 'اولویت کم' ][ $band ] ?? '';

	return '' === $label ? '' : sprintf( '<span class="hodima-advisor__prio hodima-advisor__prio--%1$s">%2$s</span>', esc_attr( $band ), esc_html( $label ) );
}

/**
 * کادر «۵ کار مهم امروز»: بیشترین اثر با کمترین زحمت، هر کدام با دلیل کوتاه؛ «کار کلی» (ایراد تکراری روی چند صفحه) یک بار با
 * پیوند «نمایش N صفحه». فقط نمایش؛ چیزی ذخیره نمی‌شود.
 *
 * @param list<array<string, mixed>> $tasks خروجی top_tasks
 */
function hodima_seo_advisor_render_top_tasks( array $tasks, callable $n ): void {

	if ( ! $tasks ) {
		return;
	}

	$effort = [ 1 => 'چند دقیقه', 2 => 'ویرایش محتوا یا لینک', 3 => 'کار بزرگ‌تر' ];
	?>
	<section class="hds-card hodima-advisor__top" aria-labelledby="hodima-advisor-top-title">
		<div>
			<h2 class="hds-card__title" id="hodima-advisor-top-title">۵ کار مهم امروز</h2>
			<p class="hds-card__desc">از بالا شروع کنید: بیشترین اثر با کمترین زحمت، بر پایه نمایش گوگل، فروش ۹۰ روز اخیر و سختی کار.</p>
		</div>
		<ol class="hodima-advisor__top-list">
			<?php foreach ( $tasks as $i => $task ) : ?>
				<?php $group = 'group' === $task['kind']; ?>
				<li class="hodima-advisor__top-item">
					<span class="hodima-advisor__top-rank" aria-hidden="true"><?php echo esc_html( $n( $i + 1 ) ); ?></span>
					<div class="hodima-advisor__top-main">
						<div class="hodima-advisor__top-title">
							<strong><?php echo esc_html( $group ? sprintf( '%1$s در %2$s صفحه', $task['title'], $n( (int) $task['pages'] ) ) : $task['title'] ); ?></strong>
							<?php if ( $group ) : ?>
								<span class="hodima-advisor__bulk">کار کلی</span>
							<?php endif; ?>
							<?php echo hodima_seo_advisor_priority_chip( (string) $task['band'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML امن‌شده ?>
						</div>
						<div class="hodima-advisor__top-page"><?php echo esc_html( $group ? 'یک الگوی تکراری؛ همه با یک نوع کار درست می‌شوند' : (string) $task['name'] ); ?></div>
						<ul class="hodima-advisor__top-why">
							<?php if ( $task['impressions'] > 0 ) : ?>
								<li><b><?php echo esc_html( $n( (int) $task['impressions'] ) ); ?></b> <?php echo esc_html( $group ? 'نمایش (جمع)' : 'نمایش' ); ?></li>
							<?php endif; ?>
							<?php if ( $task['sales'] > 0 ) : ?>
								<li>فروش ۹۰ روز: <b><?php echo esc_html( hodima_seo_advisor_money_label( (int) $task['sales'] ) ); ?></b></li>
							<?php endif; ?>
							<?php if ( $task['potential'] > 0 ) : ?>
								<li>پیشنهاد گوگل: حدود <b>+<?php echo esc_html( $n( (int) $task['potential'] ) ); ?></b> کلیک (تخمین)</li>
							<?php endif; ?>
							<li>رفع: <?php echo esc_html( $effort[ (int) $task['effort'] ] ?? $effort[2] ); ?></li>
						</ul>
					</div>
					<div class="hodima-advisor__top-act">
						<?php if ( $group ) : ?>
							<a class="hds-btn hds-btn--outline" href="<?php echo esc_url( hodima_seo_advisor_page_url( [ 'src' => 'crawl', 'code' => (string) $task['code'] ] ) ); ?>"><?php echo esc_html( sprintf( 'نمایش %s صفحه', $n( (int) $task['pages'] ) ) ); ?></a>
							<?php
							// SEO 3.4.9: خزش هدفمند فقط همین صفحه‌ها (نه کل سایت)؛ دوری در جریان است ← دکمه خاموش
							echo hodima_seo_advisor_recrawl_form( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML امن‌شده در recrawl_form
								[ 'scope' => 'code', 'code' => (string) $task['code'] ],
								sprintf( 'بازخوانی این %s صفحه', $n( (int) $task['pages'] ) ),
								sprintf( '%s؛ پیشرفت بالای صفحه', hodima_seo_advisor_crawler_duration( (int) $task['pages'] ) ),
								'running' === hodima_seo_advisor_crawler_state()['status'],
								true
							);
							?>
						<?php else : ?>
							<a class="hds-btn hds-btn--outline" href="#<?php echo esc_attr( hodima_seo_advisor_row_anchor( (string) $task['path'] ) ); ?>">دیدن و رفع</a>
						<?php endif; ?>
					</div>
				</li>
			<?php endforeach; ?>
		</ol>
		<details class="hodima-advisor__explain">
			<summary>اولویت چطور حساب می‌شود؟</summary>
			<div class="hodima-advisor__explain-body">
				<p><b>اهمیت ایراد</b> (قرمز ۳، کهربایی ۲، آبی ۱) × <b>ارزش صفحه</b> (بیشتر از نمایش گوگل یا فروش ۹۰ روز اخیر ووکامرس؛ فقط خواندن) × <b>اطمینان</b> (ایراد فنی ۱۰۰٪؛ پیشنهادهای گوگل چون تخمین‌اند کمتر) ÷ <b>سختی رفع</b> (چند دقیقه ۱، ویرایش محتوا ۲، کار بزرگ ۳).</p>
				<p>اگر یک ایراد روی ۵ صفحه یا بیشتر تکرار شود، «کار کلی» حساب می‌شود و یک بار در این فهرست می‌آید.</p>
			</div>
		</details>
	</section>
	<?php
}

/** صفحه «کارها». */
function hodima_seo_advisor_render_overview(): void {

	$n      = static fn( int|float $value ): string => number_format_i18n( $value );
	$pages  = hodima_seo_advisor_overview_build();
	$counts = hodima_seo_advisor_overview_counts( $pages );
	$src    = isset( $_GET['src'] ) ? sanitize_key( (string) $_GET['src'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط فیلتر نمایش
	$src    = isset( HODIMA_SEO_ADVISOR_OVERVIEW_SOURCES[ $src ] ) ? $src : '';
	$code   = isset( $_GET['code'] ) ? sanitize_key( (string) $_GET['code'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط فیلتر نمایش (پیوند «کار کلی»؛ SEO 3.4.0)
	$code   = isset( hodima_seo_advisor_issue_catalog()[ $code ] ) ? $code : '';
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
		if ( '' !== $code ) {
			$page['items'] = array_values( array_filter( $page['items'], static fn( array $item ): bool => 'crawl' === $item['src'] && $code === $item['mark'] ) );
		}
		if ( $page['items'] ) {
			$rows[] = $page;
		}
	}

	if ( '' === $src && '' === $q && '' === $code && 1 === $paged ) {
		hodima_seo_advisor_render_top_tasks( hodima_seo_advisor_top_tasks( $pages ), $n ); // «۵ کار مهم امروز» (SEO 3.4.0)
	}

	hodima_seo_ui_card_open( 'کارها', sprintf( '%s ایراد باز روی %s صفحه، به ترتیب اولویت (بیشترین اثر با کمترین زحمت). هر ایراد را رفع کنید و «انجام دادم» را بزنید؛ اگر لازم نیست، «لازم نیست».', $n( array_sum( array_map( static fn( array $p ): int => count( $p['items'] ), $pages ) ) ), $n( count( $pages ) ) ) );

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
		<?php if ( '' !== $code ) : ?>
			<input type="hidden" name="code" value="<?php echo esc_attr( $code ); ?>">
		<?php endif; ?>
		<?php hodima_seo_advisor_filter_chips( 'نوع ایراد', $items, $src ); ?>
		<div class="hodima-advisor__search">
			<label class="screen-reader-text" for="hodima-advisor-ov-q">جستجو در عنوان یا آدرس صفحه</label>
			<input type="search" id="hodima-advisor-ov-q" name="q" class="hds-input" value="<?php echo esc_attr( $q ); ?>" placeholder="جستجو در عنوان یا آدرس…">
			<button type="submit" class="hodima-advisor__go" aria-label="اعمال جستجو"><span class="dashicons dashicons-search" aria-hidden="true"></span></button>
		</div>
	</form>
	<?php

	if ( '' !== $code ) {
		printf(
			'<p class="hodima-advisor__back">%1$s <a href="%2$s">%3$s</a></p>',
			esc_html( sprintf( 'فقط نمایش: «%s»', hodima_seo_advisor_issue_catalog()[ $code ]['label'] ) ),
			esc_url( hodima_seo_advisor_page_url( array_filter( [ 'src' => $src, 'q' => $q ] ) ) ),
			esc_html( 'برداشتن فیلتر' )
		);
	}

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
			// دستور ایراد خزنده با نام «ویرایش» همین نوع صفحه (محصول، دسته…)؛ فقط برای ردیف‌های همین صفحه ساخته می‌شود (SEO 3.3.5)
			$where = hodima_seo_advisor_issue_where( $page['path'], (string) ( $row['kind'] ?? '' ) );
			?>
			<article class="hodima-advisor__ov-row" id="<?php echo esc_attr( hodima_seo_advisor_row_anchor( $page['path'] ) ); ?>">
				<div class="hodima-advisor__ov-head">
					<strong><?php echo esc_html( $name ); ?></strong>
					<?php echo hodima_seo_advisor_url_copy( $page['path'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML امن‌شده ?>
					<?php if ( $page['impressions'] > 0 || $page['sales'] > 0 ) : ?>
						<span class="hodima-advisor__ov-meta"><?php echo esc_html( implode( ' · ', array_filter( [ $page['impressions'] > 0 ? sprintf( '%s نمایش', $n( $page['impressions'] ) ) : '', $page['sales'] > 0 ? 'فروش ' . hodima_seo_advisor_money_label( $page['sales'] ) : '' ] ) ) ); ?></span>
					<?php endif; ?>
					<?php echo hodima_seo_advisor_action( $link[0], $link[1], $link[2] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML امن‌شده ?>
				</div>
				<ul class="hodima-advisor__ov-list">
					<?php foreach ( $page['items'] as $item ) : ?>
						<li class="hodima-advisor__ov-item">
							<div class="hodima-advisor__ov-what">
								<span class="hodima-advisor__ov-labels"><span class="hds-pill <?php echo 'bad' === $item['level'] ? 'hds-pill--error' : 'hds-pill--warn'; ?>"><?php echo esc_html( $item['label'] ); ?></span><?php echo hodima_seo_advisor_priority_chip( (string) $item['band'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML امن‌شده ?></span>
								<p><?php echo esc_html( 'crawl' === $item['src'] ? trim( (string) ( $item['detail'] ?? '' ) . ' ' . hodima_seo_advisor_issue_fix( (string) $item['mark'], $where ) ) : $item['text'] ); ?></p>
							<?php hodima_seo_advisor_render_issue_detail( $item, $page['path'] ); // پیش‌نویس، فهرست تصویر و لینک (SEO 3.3.6) ?>
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
