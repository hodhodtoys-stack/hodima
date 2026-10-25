<?php
/**
 * ماژول «مشاور سئو» — موتور قاعده‌ها
 * Path: core/advisor/advisor-rules.php
 *
 * از داده ذخیره‌شده سرچ کنسول (advisor-data.php) برای هر آدرس فهرست پیشنهاد می‌سازد.
 * فقط پیشنهاد؛ چیزی را در سایت عوض نمی‌کند. قاعده‌ها:
 *
 *   low_ctr           نمایش زیاد ولی نرخ کلیک کمتر از حد معمول رتبه (عنوان و متا)
 *   near_first        کلمه پرنمایشی که رتبه ۸ تا ۲۰ دارد (تقویت در تیتر/متن، لینک داخلی)
 *   missing_headings  کلمه پرنمایش که در عنوان و تیترهای صفحه نیست ولی در متن هست
 *   missing_content   کلمه پرنمایش که در متن صفحه هم نیست
 *   cannibal          چند آدرس برای یک کلمه نمایش می‌گیرند (رقابت داخلی؛ فقط اطلاع)
 *   decline           کلیک نسبت به بازه قبلی افت کرده
 *
 * «کلیک بالقوه» (potential) تخمین کلیکی است که در همین بازه اضافه می‌شد:
 *   نرخ کلیک: نمایش × (نرخ معمول رتبه − نرخ فعلی)؛ کلمه‌ها: همین حساب برای نمایش آن کلمه با
 *   فرض رسیدن به رتبه ۷ (یا همان رتبه فعلی اگر بهتر است)؛ افت: کلیکی که از دست رفته.
 *   قاعده‌ها روی هم می‌افتند، پس عدد صفحه «بزرگ‌ترین پیشنهاد» است، نه جمع.
 *
 * نرخ معمول هر رتبه (٪؛ ≤۲: ۲۵، ≤۳: ۱۴، ≤۵: ۹، ≤۷: ۵٫۵، ≤۱۰: ۳٫۵، بالاتر: ۱٫۵) و آستانه‌ها
 * (حداقل نمایش ۱٬۰۰۰ برای آدرس، ۳۰۰ برای کلمه، …) با فیلترهای hodima_seo_advisor_ctr_table
 * و hodima_seo_advisor_config عوض می‌شوند. حدس‌اند نه ادعای گوگل: برند و کلمه‌های
 * «نام سایت» نرخ کلیک بسیار بالاتر دارند و این جدول را بالا می‌برند.
 *
 * نتیجه همه آدرس‌ها بعد از هر دریافت داده یک‌جا ساخته و در جدول می‌ماند
 * (hodima_seo_advisor_analyze_all) تا فهرست پیشخوان با SQL فیلتر و مرتب شود؛ کادر
 * ویرایش یک صفحه با hodima_seo_advisor_analyze() دوباره و زنده می‌سازد (متن تازه صفحه).
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** حداکثر پیشنهاد یک آدرس. */
const HODIMA_SEO_ADVISOR_MAX_SUGGESTIONS = 6;

/** حداکثر پیشنهاد از هر قاعده کلمه‌محور برای یک آدرس. */
const HODIMA_SEO_ADVISOR_MAX_PER_QUERY_RULE = 2;

/** حداکثر آدرسی که در یک بار تحلیل کامل بررسی می‌شود. */
const HODIMA_SEO_ADVISOR_ANALYZE_LIMIT = 1500;

/**
 * آستانه‌ها (گزینه پیشخوان: فقط min_impressions؛ بقیه با فیلتر).
 *
 * @return array{min_impressions: int, query_min_impressions: int, near_first_min: float, near_first_max: float, decline_min_clicks: int, decline_ratio: float, cannibal_min_impressions: int}
 */
function hodima_seo_advisor_config(): array {

	$settings = get_option( HODIMA_SEO_ADVISOR_SETTINGS_OPTION, [] );
	$config   = (array) apply_filters( 'hodima_seo_advisor_config', [
		'min_impressions'          => is_array( $settings ) && (int) ( $settings['min_impressions'] ?? 0 ) > 0 ? (int) $settings['min_impressions'] : 1000,
		'query_min_impressions'    => 300,
		'near_first_min'           => 8.0,
		'near_first_max'           => 20.0,
		'decline_min_clicks'       => 50,
		'decline_ratio'            => 0.7,
		'cannibal_min_impressions' => 100,
	] );

	return [
		'min_impressions'          => max( 1, (int) ( $config['min_impressions'] ?? 1000 ) ),
		'query_min_impressions'    => max( 1, (int) ( $config['query_min_impressions'] ?? 300 ) ),
		'near_first_min'           => (float) ( $config['near_first_min'] ?? 8.0 ),
		'near_first_max'           => (float) ( $config['near_first_max'] ?? 20.0 ),
		'decline_min_clicks'       => max( 1, (int) ( $config['decline_min_clicks'] ?? 50 ) ),
		'decline_ratio'            => (float) ( $config['decline_ratio'] ?? 0.7 ),
		'cannibal_min_impressions' => max( 1, (int) ( $config['cannibal_min_impressions'] ?? 100 ) ),
	];
}

/** نرخ کلیک معمول (٪) برای یک رتبه. */
function hodima_seo_advisor_expected_ctr( float $position ): float {

	$table = (array) apply_filters( 'hodima_seo_advisor_ctr_table', [ 2 => 25.0, 3 => 14.0, 5 => 9.0, 7 => 5.5, 10 => 3.5 ] ); // بیشینه رتبه ← نرخ
	ksort( $table );

	foreach ( $table as $max => $ctr ) {
		if ( $position <= (float) $max ) {
			return (float) $ctr;
		}
	}

	return 1.5;
}

/* ── متن ── */

/** متن برای مقایسه: ی/ک عربی، نیم‌فاصله، اعراب، کشیده، ارقام عربی/فارسی و حروف بزرگ یکسان می‌شوند. */
function hodima_seo_advisor_normalize( string $text ): string {

	$text = html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$text = strtr( $text, [
		'ي' => 'ی', 'ك' => 'ک', 'ۀ' => 'ه', 'ة' => 'ه', 'أ' => 'ا', 'إ' => 'ا', 'ؤ' => 'و',
		'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
		'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
		"\u{200C}" => ' ', "\u{200D}" => '', "\u{0640}" => '',
	] );
	$text = (string) preg_replace( '/[\x{064B}-\x{065F}\x{0670}]/u', '', $text );

	return trim( (string) preg_replace( '/\s+/u', ' ', mb_strtolower( $text ) ) );
}

/** آیا همه کلمه‌های عبارت در متن (از پیش یکسان‌شده) هستند؟ عبارت بدون کلمه قابل‌سنجش «هست» حساب می‌شود (پیشنهاد نمی‌دهیم). */
function hodima_seo_advisor_text_has( string $haystack, string $term ): bool {

	$tokens = array_filter( explode( ' ', hodima_seo_advisor_normalize( $term ) ), static fn( string $t ): bool => mb_strlen( $t ) >= 2 );

	foreach ( $tokens as $token ) {
		if ( ! str_contains( $haystack, $token ) ) {
			return false;
		}
	}

	return true;
}

/**
 * عنوان، تیترها و متن یک آدرس (یکسان‌شده)، یا null اگر شیء پیدا نشد (صفحه اصلی، آدرس ناشناخته).
 *
 * @param array{kind: string, object_id: int} $row
 * @return array{title_headings: string, content: string}|null
 */
function hodima_seo_advisor_page_text( array $row ): ?array {

	$id   = $row['object_id'];
	$kind = $row['kind'];

	if ( $id <= 0 || '' === $kind ) {
		return null;
	}

	if ( taxonomy_exists( $kind ) ) {
		$term = get_term( $id, $kind );
		if ( ! $term instanceof WP_Term ) {
			return null;
		}
		$name    = $term->name;
		$html    = $term->description;
		$custom  = (string) get_term_meta( $id, '_seobox_title', true );
		$meta    = (string) get_term_meta( $id, '_seobox_description', true );
		$context = 'term';
	} elseif ( post_type_exists( $kind ) ) {
		$post = get_post( $id );
		if ( ! $post instanceof WP_Post ) {
			return null;
		}
		$name    = $post->post_title;
		$html    = $post->post_content . ' ' . $post->post_excerpt;
		$custom  = (string) get_post_meta( $id, '_seobox_title', true );
		$meta    = (string) get_post_meta( $id, '_seobox_description', true );
		$context = 'post';
	} else {
		return null;
	}

	$intro = function_exists( 'hodima_seo_page_intro_text' ) ? hodima_seo_page_intro_text( $id, $context ) : '';
	$html  = strip_shortcodes( $html );

	preg_match_all( '#<h[1-6][^>]*>(.*?)</h[1-6]>#isu', $html, $found );

	return [
		'title_headings' => hodima_seo_advisor_normalize( $custom . ' ' . $name . ' ' . implode( ' ', $found[1] ) ),
		'content'        => hodima_seo_advisor_normalize( $custom . ' ' . $name . ' ' . $meta . ' ' . $html . ' ' . $intro ),
	];
}

/** عدد با ارقام زبان سایت. */
function hodima_seo_advisor_num( float $number, int $decimals = 0 ): string {
	return number_format_i18n( $number, $decimals );
}

/* ── قاعده‌ها ── */

/**
 * پیشنهادهای یک آدرس (تابع خالص: بدون خواندن پایگاه داده).
 *
 * @param array{path: string, clicks: int, impressions: int, position: float, prev_clicks: int} $row
 * @param list<array{term: string, clicks: int, impressions: int, position: float}> $queries پرنمایش‌ترین اول
 * @param array{title_headings: string, content: string}|null $text null = متن صفحه معلوم نیست؛ قاعده‌های متن اجرا نمی‌شوند
 * @param array<string, list<array{path: string, impressions: int}>> $competitors کلمه ← آدرس‌های دیگری که برایش نمایش می‌گیرند
 * @return list<array{rule: string, term: string, title: string, detail: string, potential: int}>
 */
function hodima_seo_advisor_suggestions( array $row, array $queries, ?array $text, array $competitors = [] ): array {

	if ( 1 === preg_match( '#/page/\d+/?$#', $row['path'] ) ) {
		return []; // صفحه‌بندی آرشیو: پیشنهاد جدا ندارد
	}

	$cfg  = hodima_seo_advisor_config();
	$list = [];
	$top  = $queries[0]['term'] ?? '';
	$ctr  = static fn( int $clicks, int $impressions ): float => $impressions > 0 ? $clicks / $impressions * 100 : 0.0;

	// ۱. نرخ کلیک کمتر از حد معمول رتبه
	$position = $row['position'];
	if ( $row['impressions'] >= $cfg['min_impressions'] && $position > 0 && $position <= $cfg['near_first_max'] ) {
		$now      = $ctr( $row['clicks'], $row['impressions'] );
		$expected = hodima_seo_advisor_expected_ctr( $position );
		$gain     = (int) round( $row['impressions'] * ( $expected - $now ) / 100 );
		if ( $gain > 0 ) {
			$list[] = [
				'rule'      => 'low_ctr',
				'term'      => '',
				'title'     => 'عنوان و توضیح متا را بازنویسی کنید',
				'detail'    => sprintf(
					'این صفحه %1$s بار نمایش گرفته ولی فقط %2$s٪ کلیک شده؛ برای رتبه %3$s معمولا حدود %4$s٪ انتظار می‌رود.%5$s',
					hodima_seo_advisor_num( $row['impressions'] ),
					hodima_seo_advisor_num( $now, 2 ),
					hodima_seo_advisor_num( $position, 1 ),
					hodima_seo_advisor_num( $expected, 1 ),
					'' !== $top ? ' مهم‌ترین کلمه این صفحه «' . $top . '» است؛ آن را ابتدای عنوان بیاورید.' : ''
				),
				'potential' => $gain,
			];
		}
	}

	// ۲ و ۳. کلمه‌ها
	$near    = [];
	$missing = [];
	foreach ( $queries as $query ) {
		if ( $query['impressions'] < $cfg['query_min_impressions'] ) {
			continue;
		}
		$gain = (int) round( $query['impressions'] * ( hodima_seo_advisor_expected_ctr( min( $query['position'], 7.0 ) ) - $ctr( $query['clicks'], $query['impressions'] ) ) / 100 );
		$info = sprintf( '%1$s نمایش با رتبه %2$s.', hodima_seo_advisor_num( $query['impressions'] ), hodima_seo_advisor_num( $query['position'], 1 ) );

		if ( $gain <= 0 ) {
			continue;
		}

		if ( null !== $text && $query['position'] <= $cfg['near_first_max'] && ! hodima_seo_advisor_text_has( $text['title_headings'], $query['term'] ) ) {
			$in_content = hodima_seo_advisor_text_has( $text['content'], $query['term'] );
			$missing[]  = [
				'rule'      => $in_content ? 'missing_headings' : 'missing_content',
				'term'      => $query['term'],
				'title'     => $in_content ? 'کلمه «' . $query['term'] . '» در عنوان و تیترها نیست' : 'کلمه «' . $query['term'] . '» در متن صفحه نیست',
				'detail'    => $info . ( $in_content ? ' آن را در عنوان یا یک تیتر (H2) بیاورید.' : ' آن را در عنوان، یک تیتر و پاراگراف اول بیاورید، یا بخش پرسش‌های متداول برایش بنویسید.' ),
				'potential' => $gain,
			];
		}

		if ( $query['position'] >= $cfg['near_first_min'] && $query['position'] <= $cfg['near_first_max'] ) {
			$near[] = [
				'rule'      => 'near_first',
				'term'      => $query['term'],
				'title'     => 'کلمه «' . $query['term'] . '» نزدیک صفحه اول است',
				'detail'    => $info . ' آن را در عنوان، یک تیتر و اولین پاراگراف تقویت کنید و از ۲ تا ۳ صفحه مرتبط به این صفحه لینک بدهید.',
				'potential' => $gain,
			];
		}
	}

	// کلمه‌ای که هم «در تیتر/متن نیست» و هم «نزدیک صفحه اول» است یک کارت می‌گیرد (فهرست لینک‌دهی تکراری نشود)
	$near_terms = array_column( $near, 'term' );
	$near       = array_values( array_filter( $near, static fn( array $n ): bool => ! in_array( $n['term'], array_column( $missing, 'term' ), true ) ) );
	foreach ( $missing as $i => $item ) {
		if ( in_array( $item['term'], $near_terms, true ) ) {
			$missing[ $i ]['detail'] .= ' رتبه این کلمه به صفحه اول نزدیک است؛ لینک داخلی از صفحه‌های مرتبط هم کمک می‌کند.';
		}
	}

	foreach ( [ $missing, $near ] as $group ) {
		usort( $group, static fn( array $a, array $b ): int => $b['potential'] <=> $a['potential'] );
		array_push( $list, ...array_slice( $group, 0, HODIMA_SEO_ADVISOR_MAX_PER_QUERY_RULE ) );
	}

	// ۴. رقابت داخلی (فقط اطلاع؛ بدون کلیک بالقوه)
	foreach ( $queries as $query ) {
		$others = $competitors[ $query['term'] ] ?? [];
		if ( $query['impressions'] >= $cfg['cannibal_min_impressions'] && $others ) {
			$list[] = [
				'rule'      => 'cannibal',
				'term'      => $query['term'],
				'title'     => 'چند صفحه برای «' . $query['term'] . '» رقابت می‌کنند',
				'detail'    => 'علاوه بر این صفحه، ' . implode( '، ', array_map( static fn( array $o ): string => $o['path'], array_slice( $others, 0, 3 ) ) ) . ' هم برای این کلمه نمایش می‌گیرد. یکی را صفحه اصلی کلمه کنید و بقیه را با لینک داخلی به آن وصل کنید.',
				'potential' => 0,
			];
			break; // یک مورد کافی است؛ پرنمایش‌ترین کلمه
		}
	}

	// ۵. افت کلیک
	if ( $row['prev_clicks'] >= $cfg['decline_min_clicks'] && $row['clicks'] < $row['prev_clicks'] * $cfg['decline_ratio'] ) {
		$list[] = [
			'rule'      => 'decline',
			'term'      => '',
			'title'     => 'کلیک این صفحه افت کرده',
			'detail'    => sprintf(
				'کلیک از %1$s به %2$s رسیده (%3$s٪ کمتر از بازه قبل). تغییر اخیر عنوان، آدرس، لینک‌های داخلی یا رتبه کلمه‌های اصلی را بررسی کنید.',
				hodima_seo_advisor_num( $row['prev_clicks'] ),
				hodima_seo_advisor_num( $row['clicks'] ),
				hodima_seo_advisor_num( ( 1 - $row['clicks'] / $row['prev_clicks'] ) * 100 )
			),
			'potential' => $row['prev_clicks'] - $row['clicks'],
		];
	}

	usort( $list, static fn( array $a, array $b ): int => $b['potential'] <=> $a['potential'] );

	return array_slice( $list, 0, HODIMA_SEO_ADVISOR_MAX_SUGGESTIONS );
}

/**
 * آدرس‌های دیگری که برای همان کلمه‌های این آدرس نمایش می‌گیرند.
 *
 * @param list<array{term: string, impressions: int}> $queries
 * @return array<string, list<array{path: string, impressions: int}>>
 */
function hodima_seo_advisor_competitors( string $path, array $queries ): array {

	global $wpdb;

	$min   = hodima_seo_advisor_config()['cannibal_min_impressions'];
	$terms = array_values( array_map( static fn( array $q ): string => $q['term'], array_filter( $queries, static fn( array $q ): bool => $q['impressions'] >= $min ) ) );

	if ( ! $terms ) {
		return [];
	}

	$rows = (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			'SELECT term, path, impressions FROM %i WHERE term IN (' . implode( ',', array_fill( 0, count( $terms ), '%s' ) ) . ') AND path <> %s AND impressions >= %d ORDER BY impressions DESC', // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- تعداد placeholderها با array_fill هم‌اندازه کلمه‌هاست
			hodima_seo_advisor_table( 'queries' ),
			...$terms,
			...[ $path, $min ]
		),
		ARRAY_A
	);

	$out = [];
	foreach ( $rows as $row ) {
		if ( 1 !== preg_match( '#/page/\d+/?$#', (string) $row['path'] ) ) {
			$out[ (string) $row['term'] ][] = [ 'path' => (string) $row['path'], 'impressions' => (int) $row['impressions'] ];
		}
	}

	return $out;
}

/**
 * تحلیل زنده یک آدرس از داده ذخیره‌شده و متن فعلی صفحه.
 *
 * @return array{row: array<string, mixed>, suggestions: list<array<string, mixed>>, potential: int}|null
 */
function hodima_seo_advisor_analyze( string $path ): ?array {

	$row = hodima_seo_advisor_page( $path );

	if ( null === $row ) {
		return null;
	}

	$queries     = hodima_seo_advisor_queries( $row['path'], HODIMA_SEO_ADVISOR_QUERIES_PER_PAGE );
	$suggestions = hodima_seo_advisor_enrich(
		hodima_seo_advisor_suggestions( $row, $queries, hodima_seo_advisor_page_text( $row ), hodima_seo_advisor_competitors( $row['path'], $queries ) ),
		$row,
		$queries
	);

	return [
		'row'         => $row,
		'suggestions' => $suggestions,
		'potential'   => (int) max( [ 0, ...array_column( $suggestions, 'potential' ) ] ),
	];
}

/**
 * تحلیل یک آدرس و ذخیره نتیجه در ردیف جدول (آدرس نادیده‌گرفته‌شده: بدون پیشنهاد).
 * true = پیشنهاد دارد.
 */
function hodima_seo_advisor_save_advice( string $path ): bool {

	global $wpdb;

	$result = in_array( $path, hodima_seo_advisor_ignored(), true ) ? null : hodima_seo_advisor_analyze( $path );
	$found  = null !== $result && [] !== $result['suggestions'];

	$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		hodima_seo_advisor_table( 'pages' ),
		$found
			? [ 'potential' => $result['potential'], 'advice_count' => count( $result['suggestions'] ), 'advice' => (string) wp_json_encode( $result['suggestions'], JSON_UNESCAPED_UNICODE ) ]
			: [ 'potential' => 0, 'advice_count' => 0, 'advice' => '' ],
		[ 'path' => $path ],
		[ '%d', '%d', '%s' ],
		[ '%s' ]
	);

	return $found;
}

/**
 * نادیده گرفتن یک آدرس (یا بازگرداندنش). بازگرداندن همان لحظه دوباره تحلیلش می‌کند.
 */
function hodima_seo_advisor_set_ignored( string $path, bool $ignore ): void {

	$ignored = array_values( array_diff( hodima_seo_advisor_ignored(), [ $path ] ) );

	if ( $ignore ) {
		$ignored[] = $path;
	}

	update_option( HODIMA_SEO_ADVISOR_IGNORED_OPTION, array_values( array_unique( $ignored ) ), false );
	hodima_seo_advisor_save_advice( $path );
}

/**
 * تحلیل همه آدرس‌های قابل‌بررسی و ذخیره نتیجه در جدول؛ بعد از هر دریافت داده.
 * قابل‌بررسی: نمایش ≥ حداقل، یا کلیک بازه قبل ≥ حداقل افت. تعداد آدرس دارای پیشنهاد را برمی‌گرداند.
 */
function hodima_seo_advisor_analyze_all(): int {

	global $wpdb;

	$cfg   = hodima_seo_advisor_config();
	$table = hodima_seo_advisor_table( 'pages' );

	$wpdb->query( $wpdb->prepare( 'UPDATE %i SET potential = 0, advice_count = 0, advice = %s', $table, '' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

	$paths = (array) $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare( 'SELECT path FROM %i WHERE impressions >= %d OR prev_clicks >= %d ORDER BY impressions DESC LIMIT %d', $table, $cfg['min_impressions'], $cfg['decline_min_clicks'], HODIMA_SEO_ADVISOR_ANALYZE_LIMIT )
	);

	$advised = 0;

	foreach ( $paths as $path ) {
		if ( hodima_seo_advisor_save_advice( (string) $path ) ) {
			++$advised;
		}
	}

	return $advised;
}
