<?php
/**
 * ماژول «مشاور سئو» — تاریخچه هفتگی هر آدرس
 * Path: core/advisor/advisor-history.php
 *
 * جدول داده اصلی (pages/queries) با هر دریافت کامل جایگزین می‌شود، پس روند در آن نیست. این فایل
 * هر هفته یک «عکس» از هر آدرس نگه می‌دارد: هفته دوشنبه تا یکشنبه (به وقت اقیانوس آرام، مثل خود
 * سرچ کنسول) که دو روز از پایانش گذشته باشد. هفته‌ها روی هم نمی‌افتند، پس مقایسه‌شان درست است.
 * بار اول ۸ هفته اخیر هم یک‌جا گرفته می‌شود (هر هفته یک درخواست)؛ بعد از آن هر هفته فقط هفته
 * تازه. فقط آدرس‌های با دست‌کم ۱۰ نمایش در آن هفته و حداکثر ۵۰۰۰ آدرس برتر هر هفته ذخیره
 * می‌شود؛ ۱۰۴ هفته (دو سال) می‌ماند.
 *
 * «پاک کردن داده‌های مشاور» (پیشخوان) تاریخچه را پاک نمی‌کند: بازسازی‌اش ممکن نیست مگر برای
 * ۸ هفته اخیر. شکست دریافت تاریخچه هرگز دریافت اصلی را خراب یا رد نمی‌کند.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** چند هفته اخیر در هر دریافت بررسی می‌شود (هفته‌هایی که ذخیره نیستند گرفته می‌شوند). */
const HODIMA_SEO_ADVISOR_HISTORY_WEEKS = 8;

/** چند هفته نگه داشته شود. */
const HODIMA_SEO_ADVISOR_HISTORY_KEEP_WEEKS = 104;

/** کم‌ترین نمایش یک آدرس در یک هفته برای ذخیره. */
const HODIMA_SEO_ADVISOR_HISTORY_MIN_IMPRESSIONS = 10;

/** حداکثر آدرس ذخیره‌شده برای هر هفته (برترین‌ها بر اساس نمایش). */
const HODIMA_SEO_ADVISOR_HISTORY_MAX_ROWS = 5000;

/**
 * یکشنبه‌های پایان هفته‌های کامل (تازه‌ترین اول).
 *
 * @return list<string> تاریخ Y-m-d
 */
function hodima_seo_advisor_history_week_ends( int $count = HODIMA_SEO_ADVISOR_HISTORY_WEEKS ): array {

	$base   = new DateTimeImmutable( hodima_seo_advisor_ranges( 1 )['end'], new DateTimeZone( 'America/Los_Angeles' ) ); // امروز منهای ۲ روز
	$sunday = $base->modify( '-' . $base->format( 'w' ) . ' days' ); // w: یکشنبه ۰

	$ends = [];
	for ( $i = 0; $i < $count; $i++ ) {
		$ends[] = $sunday->modify( '-' . ( 7 * $i ) . ' days' )->format( 'Y-m-d' );
	}

	return $ends;
}

/**
 * هفته‌هایی که عکسشان ذخیره است (تازه‌ترین اول).
 *
 * @return list<string>
 */
function hodima_seo_advisor_history_known_weeks( int $limit = 200 ): array {

	global $wpdb;

	$weeks = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare( 'SELECT DISTINCT week_end FROM %i ORDER BY week_end DESC LIMIT %d', hodima_seo_advisor_table( 'history' ), $limit )
	);

	return array_map( 'strval', (array) $weeks );
}

/**
 * عکس یک هفته را ذخیره می‌کند (جایگزین عکس قبلی همان هفته).
 *
 * @param array<string, array{clicks: int, impressions: int, position: float}> $pages
 * @return int|WP_Error تعداد آدرس ذخیره‌شده
 */
function hodima_seo_advisor_history_store_week( string $week_end, array $pages ): int|WP_Error {

	global $wpdb;

	$pages = array_filter( $pages, static fn( array $s ): bool => $s['impressions'] >= HODIMA_SEO_ADVISOR_HISTORY_MIN_IMPRESSIONS );
	uasort( $pages, static fn( array $a, array $b ): int => $b['impressions'] <=> $a['impressions'] );
	$pages = array_slice( $pages, 0, HODIMA_SEO_ADVISOR_HISTORY_MAX_ROWS, true );

	$rows = [];
	foreach ( $pages as $path => $s ) {
		$rows[] = [ $path, $week_end, $s['clicks'], $s['impressions'], $s['position'] ];
	}

	$table = hodima_seo_advisor_table( 'history' );

	return hodima_seo_advisor_transaction( static function () use ( $wpdb, $table, $week_end, $rows ): int|WP_Error {

		$fail = static fn(): WP_Error => new WP_Error( 'hodima_advisor_history', 'ذخیره تاریخچه هفتگی ناموفق بود: ' . $wpdb->last_error );

		if ( false === $wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE week_end = %s', $table, $week_end ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return $fail();
		}

		foreach ( array_chunk( $rows, 200 ) as $chunk ) {
			if ( false === hodima_seo_advisor_insert( $table, 'path, week_end, clicks, impressions, avg_position', '(%s,%s,%d,%d,%f)', $chunk ) ) {
				return $fail();
			}
		}

		return count( $rows );
	} );
}

/**
 * هفته‌های کامل را که ذخیره نیستند از سرچ کنسول می‌گیرد و ذخیره می‌کند، و هفته‌های بسیار قدیمی را پاک می‌کند.
 * هر هفته یک درخواست جدا؛ شکست یک هفته بقیه را متوقف نمی‌کند (غیر از خطای کلید).
 *
 * @return array{added: int, failed: int}
 */
function hodima_seo_advisor_history_sync( string $property ): array {

	hodima_seo_advisor_ensure_tables();

	$known  = hodima_seo_advisor_history_known_weeks();
	$result = [ 'added' => 0, 'failed' => 0 ];

	foreach ( hodima_seo_advisor_history_week_ends() as $end ) {

		if ( in_array( $end, $known, true ) ) {
			continue;
		}

		$start = ( new DateTimeImmutable( $end ) )->modify( '-6 days' )->format( 'Y-m-d' );
		$part  = hodima_seo_sc_request( $property, $start, $end, [ 'page' ], 25000 );

		if ( is_wp_error( $part ) ) {
			++$result['failed'];
			if ( hodima_seo_sc_is_key_error( $part ) ) {
				break; // با هفته دیگر هم درست نمی‌شود
			}
			continue;
		}

		$saved = hodima_seo_advisor_history_store_week( $end, hodima_seo_advisor_aggregate_pages( $part['rows'] ) );

		if ( is_wp_error( $saved ) ) {
			++$result['failed'];
			continue;
		}

		++$result['added'];
	}

	hodima_seo_advisor_history_prune();

	return $result;
}

/** عکس‌های قدیمی‌تر از ۱۰۴ هفته. */
function hodima_seo_advisor_history_prune(): void {

	global $wpdb;

	$oldest = ( new DateTimeImmutable( hodima_seo_advisor_history_week_ends( 1 )[0] ) )->modify( '-' . ( 7 * HODIMA_SEO_ADVISOR_HISTORY_KEEP_WEEKS ) . ' days' )->format( 'Y-m-d' );

	$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE week_end < %s', hodima_seo_advisor_table( 'history' ), $oldest ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

/**
 * روند هفتگی یک آدرس (قدیمی‌ترین اول). هفته‌ای که عکسش ذخیره است ولی این آدرس در آن نبوده، صفر است.
 *
 * @return list<array{week: string, clicks: int, impressions: int, position: float}>
 */
function hodima_seo_advisor_history( string $path, int $weeks = 12 ): array {

	global $wpdb;

	$table = hodima_seo_advisor_table( 'history' );
	$ends  = array_reverse( hodima_seo_advisor_history_known_weeks( max( 1, $weeks ) ) );

	if ( ! $ends ) {
		return [];
	}

	$found = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare( 'SELECT week_end, clicks, impressions, avg_position FROM %i WHERE path = %s AND week_end >= %s', $table, $path, $ends[0] ),
		ARRAY_A
	);

	$by_week = [];
	foreach ( (array) $found as $row ) {
		$by_week[ (string) $row['week_end'] ] = $row;
	}

	$out = [];
	foreach ( $ends as $end ) {
		$row   = $by_week[ $end ] ?? null;
		$out[] = [
			'week'        => $end,
			'clicks'      => (int) ( $row['clicks'] ?? 0 ),
			'impressions' => (int) ( $row['impressions'] ?? 0 ),
			'position'    => (float) ( $row['avg_position'] ?? 0 ),
		];
	}

	return $out;
}

/**
 * تاریخ آخرین ویرایش شیء (Y-m-d)؛ خالی برای دسته/صفحه اصلی که تاریخ ویرایش ندارند.
 */
function hodima_seo_advisor_object_modified( string $kind, int $id ): string {

	if ( $id <= 0 || '' === $kind || ! post_type_exists( $kind ) ) {
		return '';
	}

	$post = get_post( $id );

	return $post instanceof WP_Post ? substr( $post->post_modified, 0, 10 ) : '';
}

/**
 * مقایسه «قبل و بعد از آخرین ویرایش»: میانگین هفتگی تا ۳ هفته کامل قبل از ویرایش با تا ۳ هفته
 * کامل بعد از آن. هفته‌ای که ویرایش وسطش افتاده شمرده نمی‌شود. null اگر یکی از دو طرف هفته ندارد.
 * اگر ویرایش خیلی قدیمی باشد (قبل از ۳ هفته آخر)، هفته‌های «قبل» ممکن است ذخیره نشده باشند.
 *
 * @param list<array{week: string, clicks: int, impressions: int, position: float}> $history
 * @return array{modified: string, before: array{weeks: int, clicks: float, impressions: float, position: float}, after: array{weeks: int, clicks: float, impressions: float, position: float}}|null
 */
function hodima_seo_advisor_before_after( array $history, string $modified ): ?array {

	if ( '' === $modified ) {
		return null;
	}

	$before = [];
	$after  = [];

	foreach ( $history as $row ) {
		$start = ( new DateTimeImmutable( $row['week'] ) )->modify( '-6 days' )->format( 'Y-m-d' );
		if ( $row['week'] < $modified ) {
			$before[] = $row;
		} elseif ( $start > $modified ) {
			$after[] = $row;
		}
	}

	$before = array_slice( $before, -3 );
	$after  = array_slice( $after, 0, 3 );

	if ( ! $before || ! $after ) {
		return null;
	}

	$avg = static function ( array $rows ): array {
		$n    = count( $rows );
		$imps = array_sum( array_column( $rows, 'impressions' ) );
		$pos  = 0.0;
		foreach ( $rows as $r ) {
			$pos += $r['position'] * $r['impressions'];
		}
		return [
			'weeks'       => $n,
			'clicks'      => round( array_sum( array_column( $rows, 'clicks' ) ) / $n, 1 ),
			'impressions' => round( $imps / $n, 1 ),
			'position'    => $imps > 0 ? round( $pos / $imps, 1 ) : 0.0,
		];
	};

	return [ 'modified' => $modified, 'before' => $avg( $before ), 'after' => $avg( $after ) ];
}
