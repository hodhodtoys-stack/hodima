<?php
/**
 * ماژول «دستار سئو» — رقابت صفحه‌ها (گزارش کل سایت)
 * Path: core/advisor/advisor-competition.php
 *
 * قاعده cannibal هر صفحه فقط تا ۳ کلمه رقابتی همان صفحه را می‌گوید. این فایل همان داده
 * (جدول کلمه‌ها) را از دید کل سایت می‌چیند: هر کلمه‌ای که دو صفحه یا بیشتر برایش نمایش
 * می‌گیرند یک «گروه رقابت» است با صفحه‌ها، سهم هر صفحه، برنده پیشنهادی و یک دستور روشن.
 * فقط می‌خواند؛ چیزی را در سایت عوض نمی‌کند (مدیر اجرا می‌کند).
 *
 * دستورها (type):
 *   strengthen  یک صفحه ≥ ۷۰٪ نمایش را دارد ← همان صفحه اصلی کلمه؛ رقیب‌ها را با لینک داخلی به آن وصل کنید
 *   choose      نمایش بین صفحه‌ها پخش است ← یک صفحه را اصلی کنید؛ اگر محتوا یکی است ادغام (۳۰۱)، وگرنه موضوع‌ها را جدا کنید
 *   category    برنده یک محصول است ولی صفحه دسته هم رقیب است ← برای کلمه عمومی دسته را اصلی کنید
 *
 * برنده: بیشترین کلیک؛ برابر ← رتبه بهتر؛ باز هم برابر ← نمایش بیشتر. «کلیک بالقوه رفع رقابت» تخمین
 * است (مثل بقیه قاعده‌ها): نمایش کل × (نرخ معمول رتبه هدف برنده − نرخ فعلی کل گروه).
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** سهم نمایشی که یک صفحه باید داشته باشد تا «برنده روشن» حساب شود. */
const HODIMA_SEO_ADVISOR_COMPETITION_DOMINANT = 0.7;

/** حداکثر صفحه نشان‌داده‌شده در هر گروه. */
const HODIMA_SEO_ADVISOR_COMPETITION_MAX_PAGES = 5;

/** حداکثر گروه خوانده‌شده برای یک گزارش. */
const HODIMA_SEO_ADVISOR_COMPETITION_MAX_GROUPS = 300;

/**
 * دستور و دلیل برای یک گروه رقابت.
 *
 * @param list<array<string, mixed>> $pages صفحه‌ها، برنده اول
 * @return array{type: string, title: string, detail: string}
 */
function hodima_seo_advisor_competition_advice( string $term, array $pages, float $top_share ): array {

	$winner = $pages[0];
	$others = array_slice( $pages, 1 );
	$cats   = array_values( array_filter( $others, static fn( array $p ): bool => in_array( (string) $p['kind'], [ 'product_cat', 'category' ], true ) ) );

	if ( ! in_array( (string) $winner['kind'], [ 'product_cat', 'category' ], true ) && $cats ) {
		return [
			'type'   => 'category',
			'title'  => 'دسته را صفحه اصلی کلمه کنید',
			'detail' => sprintf( 'برای کلمه عمومی «%s» صفحه دسته معمولا مناسب‌تر از یک محصول است. از محصول‌های رقیب با متن لینک «%s» به صفحه دسته لینک بدهید و کلمه را از عنوان محصول‌ها بردارید.', $term, $term ),
		];
	}

	if ( $top_share >= HODIMA_SEO_ADVISOR_COMPETITION_DOMINANT ) {
		return [
			'type'   => 'strengthen',
			'title'  => 'برنده روشن است؛ بقیه را به آن وصل کنید',
			'detail' => sprintf( 'این صفحه بیشتر نمایش «%s» را می‌گیرد. از صفحه‌های رقیب با متن لینک «%s» به آن لینک بدهید و کلمه را از عنوان و تیتر H2 رقیب‌ها بردارید یا موضوعشان را عوض کنید.', $term, $term ),
		];
	}

	return [
		'type'   => 'choose',
		'title'  => 'یک صفحه را اصلی کنید',
		'detail' => sprintf( 'نمایش «%s» بین چند صفحه پخش است و گوگل نمی‌داند کدام را نشان دهد. اگر محتوای صفحه‌ها یکی است آن‌ها را ادغام و بقیه را ۳۰۱ کنید؛ اگر نه، موضوع هر صفحه را جدا کنید و فقط برنده را با کلمه تقویت کنید.', $term ),
	];
}

/**
 * گروه‌های رقابت کل سایت، پرنمایش‌ترین اول.
 *
 * هر کلمه‌ای که دست‌کم دو صفحه (غیرصفحه‌بندی) هرکدام دست‌کم `cannibal_min_impressions` نمایش برایش
 * دارند؛ کلمه‌های برندی و آدرس‌های نادیده‌گرفته‌شده کنار می‌روند.
 *
 * رقابت «حل‌شده»: اگر بعد از کنار گذاشتن صفحه‌هایی که ریدایرکت، ۴۰۴ یا noindex/canonical‌اند (طبق خزش SF یا
 * بررسی روتر؛ hodima_seo_advisor_resolved_paths) کمتر از دو صفحه بماند، گروه از فهرست اصلی کنار می‌رود و
 * `resolved` آن را می‌شمارد؛ با `resolved => true` فقط همین گروه‌ها (با دلیل هر صفحه) برمی‌گردند.
 *
 * @param array{limit?: int, offset?: int, search?: string, type?: string, resolved?: bool} $args
 * @return array{rows: list<array<string, mixed>>, total: int, pages: int, potential: int, resolved: array{groups: int, potential: int}}
 */
function hodima_seo_advisor_competition( array $args = [] ): array {

	global $wpdb;

	$min     = hodima_seo_advisor_config()['cannibal_min_impressions'];
	$queries = hodima_seo_advisor_table( 'queries' );
	$ignored = hodima_seo_advisor_ignored();

	// گروه‌بندی با term_key (کلمه یکسان‌شده): املای متفاوت یک کلمه در آدرس‌های مختلف (ي/ی، اعراب، حروف) یک گروه می‌شود
	// سقف ۳۰۰ گروه پرنمایش؛ صفحه‌بندی آرشیو و نادیده‌گرفته‌ها بعدا در PHP کنار می‌روند (REGEXP روی همه پایگاه‌ها یکسان نیست)
	$keys = (array) $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			'SELECT term_key FROM (SELECT term_key, COUNT(*) AS n, SUM(impressions) AS s FROM %i WHERE impressions >= %d GROUP BY term_key) AS t WHERE n >= 2 ORDER BY s DESC LIMIT %d', // HAVING با تجمیع در مترجم SQLite ابزار تست خراب می‌شود؛ زیرپرسش روی هر دو کار می‌کند (path + term_key یکتاست، پس COUNT(*) = شمار صفحه)
			$queries,
			$min,
			HODIMA_SEO_ADVISOR_COMPETITION_MAX_GROUPS
		)
	);

	$keys = array_values( array_filter( array_map( 'strval', $keys ), static fn( string $k ): bool => ! hodima_seo_advisor_is_brand( $k ) ) );

	if ( ! $keys ) {
		return [ 'rows' => [], 'total' => 0, 'pages' => 0, 'potential' => 0, 'resolved' => [ 'groups' => 0, 'potential' => 0 ] ];
	}

	$pages_table = hodima_seo_advisor_table( 'pages' );
	$found       = (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQL.NotPrepared -- تعداد %s با array_fill هم‌اندازه کلمه‌هاست
			'SELECT q.term, q.term_key, q.path, q.clicks, q.impressions, q.avg_position, p.title, p.kind, p.object_id FROM %i q LEFT JOIN %i p ON p.path = q.path WHERE q.impressions >= %d AND q.term_key IN (' . implode( ',', array_fill( 0, count( $keys ), '%s' ) ) . ')',
			$queries,
			$pages_table,
			$min,
			...$keys
		),
		ARRAY_A
	);

	$by_term = [];
	$labels  = []; // کلید ← املای پرنمایش‌ترین (برای نمایش)
	$best    = [];
	foreach ( $found as $r ) {
		if ( in_array( (string) $r['path'], $ignored, true ) || 1 === preg_match( '#/page/\d+/?$#', (string) $r['path'] ) ) { // صفحه‌بندی آرشیو رقیب حساب نمی‌شود
			continue;
		}
		$key = (string) $r['term_key'];
		if ( (int) $r['impressions'] > ( $best[ $key ] ?? -1 ) ) {
			$best[ $key ]   = (int) $r['impressions'];
			$labels[ $key ] = (string) $r['term'];
		}
		$by_term[ $key ][] = [
			'path'        => (string) $r['path'],
			'title'       => (string) $r['title'],
			'kind'        => (string) $r['kind'],
			'object_id'   => (int) $r['object_id'],
			'clicks'      => (int) $r['clicks'],
			'impressions' => (int) $r['impressions'],
			'position'    => (float) $r['avg_position'],
		];
	}

	$resolved     = hodima_seo_advisor_resolved_paths();
	$want_settled = ! empty( $args['resolved'] );
	$settled_n    = 0;
	$settled_gain = 0;
	$groups       = [];
	foreach ( $keys as $key ) {
		$pages = $by_term[ $key ] ?? [];
		$term  = $labels[ $key ] ?? $key;
		if ( count( $pages ) < 2 ) {
			continue;
		}

		usort(
			$pages,
			static fn( array $a, array $b ): int => [ $b['clicks'], $a['position'], $b['impressions'] ] <=> [ $a['clicks'], $b['position'], $a['impressions'] ]
		);

		$live    = array_values( array_filter( $pages, static fn( array $p ): bool => ! isset( $resolved[ $p['path'] ] ) ) );
		$settled = count( $live ) < 2;

		if ( $settled ) {
			foreach ( $pages as $i => $page ) {
				$pages[ $i ]['resolved'] = $resolved[ $page['path'] ] ?? '';
			}
		} else {
			$pages = $live; // صفحه حل‌شده رقیب حساب نمی‌شود
		}

		$impressions = array_sum( array_column( $pages, 'impressions' ) );
		$clicks      = array_sum( array_column( $pages, 'clicks' ) );
		$best        = min( array_column( $pages, 'position' ) );
		$ctr         = $impressions > 0 ? $clicks / $impressions * 100 : 0.0;
		$potential   = (int) max( 0, round( $impressions * ( hodima_seo_advisor_expected_ctr( hodima_seo_advisor_target_position( $best ) ) - $ctr ) / 100 ) );

		foreach ( $pages as $i => $page ) {
			$pages[ $i ]['share'] = $impressions > 0 ? $page['impressions'] / $impressions : 0.0;
		}

		if ( $settled ) {
			++$settled_n;
			$settled_gain += $potential;
		}

		if ( $settled !== $want_settled ) {
			continue;
		}

		$top_share = max( array_column( $pages, 'share' ) );
		$advice    = $settled
			? [ 'type' => 'settled', 'title' => 'رقابت حل‌شده', 'detail' => in_array( 'home', array_column( $pages, 'resolved' ), true )
				? 'صفحه اصلی هدف کلمه مشخصی نیست و با صفحه دیگر رقابت واقعی ندارد؛ گوگل آن را فقط به‌خاطر لینک‌هایش گاهی برای این کلمه نشان می‌دهد. اقدامی لازم نیست.'
				: 'یکی از این صفحه‌ها در سایت ریدایرکت می‌شود، noindex است یا ۴۰۴ می‌دهد؛ پس دیگر با صفحه دیگر رقابت نمی‌کند و گوگل کم‌کم آن را کنار می‌گذارد. فقط لینک‌های داخلی را مستقیم به صفحه مقصد بدهید.' ]
			: hodima_seo_advisor_competition_advice( $term, $pages, $top_share );

		if ( '' !== (string) ( $args['type'] ?? '' ) && $advice['type'] !== $args['type'] ) {
			continue;
		}

		$groups[] = [
			'term'        => $term,
			'impressions' => $impressions,
			'clicks'      => $clicks,
			'position'    => $best,
			'count'       => count( $pages ),
			'pages'       => array_slice( $pages, 0, HODIMA_SEO_ADVISOR_COMPETITION_MAX_PAGES ),
			'potential'   => $potential,
			'advice'      => $advice,
		];
	}

	$search = trim( (string) ( $args['search'] ?? '' ) );
	if ( '' !== $search ) {
		$needle = hodima_seo_advisor_normalize( $search );
		$groups = array_values( array_filter(
			$groups,
			static function ( array $g ) use ( $needle ): bool {
				if ( str_contains( hodima_seo_advisor_normalize( $g['term'] ), $needle ) ) {
					return true;
				}
				foreach ( $g['pages'] as $p ) {
					if ( str_contains( strtolower( $p['path'] ), strtolower( $needle ) ) ) {
						return true;
					}
				}
				return false;
			}
		) );
	}

	usort( $groups, static fn( array $a, array $b ): int => [ $b['potential'], $b['impressions'] ] <=> [ $a['potential'], $a['impressions'] ] );

	$page_set = [];
	foreach ( $groups as $g ) {
		foreach ( $g['pages'] as $p ) {
			$page_set[ $p['path'] ] = true;
		}
	}

	$rows = array_slice( $groups, max( 0, (int) ( $args['offset'] ?? 0 ) ), isset( $args['limit'] ) ? max( 1, (int) $args['limit'] ) : null );

	// شواهد لینک داخلی فقط برای گروه‌های نمایش‌داده‌شده (هزینه پرسش پایگاه داده)؛ بدون limit (CSV) نیست
	if ( isset( $args['limit'] ) && ! $want_settled ) {
		foreach ( $rows as $i => $group ) {
			foreach ( $group['pages'] as $j => $page ) {
				$info = hodima_seo_advisor_crawl_links_info( $page['path'] );
				$rows[ $i ]['pages'][ $j ] += [ 'term_links' => null === $info ? null : hodima_seo_advisor_crawl_term_links( $info['anchors'], $group['term'] ), 'links_total' => $info['total'] ?? null ];
			}
			$rows[ $i ]['advice']['detail'] .= hodima_seo_advisor_competition_links_note( $group['term'], $rows[ $i ]['pages'] );
		}
	}

	return [
		'rows'      => $rows,
		'total'     => count( $groups ),
		'pages'     => count( $page_set ),
		'potential' => (int) array_sum( array_column( $groups, 'potential' ) ),
		'resolved'  => [ 'groups' => $settled_n, 'potential' => $settled_gain ],
	];
}
