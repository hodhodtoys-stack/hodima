<?php
/**
 * ماژول «دستار سئو» — موتور قاعده‌ها
 * Path: core/advisor/advisor-rules.php
 *
 * از داده ذخیره‌شده سرچ کنسول (advisor-data.php) برای هر آدرس فهرست پیشنهاد می‌سازد.
 * فقط پیشنهاد؛ چیزی را در سایت عوض نمی‌کند. قاعده‌ها:
 *
 *   low_ctr           نمایش زیاد ولی نرخ کلیک کمتر از حد معمول رتبه (عنوان و متا)
 *   near_first        کلمه پرنمایشی که رتبه ۸ تا ۲۰ دارد (تقویت در تیتر/متن، لینک داخلی)
 *   missing_headings  کلمه پرنمایش که در عنوان و تیترهای صفحه نیست ولی در متن هست
 *   missing_content   کلمه پرنمایش که در متن صفحه هم نیست
 *   weak_query        کلمه پرنمایشی که رتبه ۲۱ تا ۶۰ دارد (صفحه یا بخش اختصاصی لازم است)
 *   cannibal          چند آدرس برای یک کلمه نمایش می‌گیرند (رقابت داخلی؛ فقط اطلاع)
 *   decline           کلیک نسبت به بازه قبلی افت کرده؛ علت (رتبه / نمایش / نرخ کلیک) را می‌گوید و اگر
 *                     کل سایت هم افت کرده، فقط سهم مخصوص همین صفحه را «کلیک بالقوه» می‌شمارد
 *
 * «کلیک بالقوه» (potential) تخمین کلیکی است که در همین بازه اضافه می‌شد:
 *   نرخ کلیک: نمایش × (نرخ معمول رتبه − نرخ فعلی)؛ کلمه‌ها: همین حساب برای نمایش آن کلمه با
 *   فرض رسیدن به رتبه‌ای ۴ پله بهتر، بین ۷ و ۱۰ (hodima_seo_advisor_target_position)؛ کلمه‌های
 *   برندی (نام سایت) کنار گذاشته می‌شوند؛ افت: کلیکی که از دست رفته.
 *   قاعده‌ها روی هم می‌افتند، پس عدد صفحه «بزرگ‌ترین پیشنهاد» است، نه جمع.
 *
 * نرخ معمول هر رتبه (٪؛ ≤۲: ۲۵، ≤۳: ۱۴، ≤۵: ۹، ≤۷: ۵٫۵، ≤۱۰: ۳٫۵، بالاتر: ۱٫۵) با داده خود سایت
 * تنظیم می‌شود (hodima_seo_advisor_ctr_learn: کلمه‌های غیربرندی هر سطل رتبه، با وزن به نسبت حجم نمایش) و آستانه‌ها
 * (حداقل نمایش ۱٬۰۰۰ برای آدرس، ۳۰۰ برای کلمه، …) با فیلترهای hodima_seo_advisor_ctr_table
 * و hodima_seo_advisor_config عوض می‌شوند. حدس‌اند نه ادعای گوگل: برند و کلمه‌های
 * «نام سایت» نرخ کلیک بسیار بالاتر دارند و این جدول را بالا می‌برند.
 *
 * نتیجه همه آدرس‌ها بعد از هر دریافت داده نوبت‌به‌نوبت ساخته و در جدول می‌ماند
 * (hodima_seo_advisor_analyze_batch) تا فهرست پیشخوان با SQL فیلتر و مرتب شود؛ کادر
 * ویرایش یک صفحه با hodima_seo_advisor_analyze( $path, true ) دوباره و زنده می‌سازد (متن تازه صفحه
 * و «دقیقا چه کنید»). گام‌ها (advisor-steps.php) فقط در کادر ساخته می‌شوند، نه در تحلیل همه آدرس‌ها:
 * تا SEO 2.9.8 برای هر آدرس چند جستجوی LIKE روی متن همه نوشته‌ها می‌زد و حدود ۹۸٪ زمان تحلیل بود.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** حداکثر پیشنهاد یک آدرس. */
const HODIMA_SEO_ADVISOR_MAX_SUGGESTIONS = 6;

/** حداکثر پیشنهاد از هر قاعده کلمه‌محور برای یک آدرس. */
const HODIMA_SEO_ADVISOR_MAX_PER_QUERY_RULE = 2;

/** حداکثر کلمه رقابت داخلی در گزارش یک آدرس (جدا از سقف بالا؛ هر کلمه یک ردیف). */
const HODIMA_SEO_ADVISOR_MAX_CANNIBAL = 3;

/** حداکثر آدرسی که در یک بار تحلیل کامل بررسی می‌شود. */
const HODIMA_SEO_ADVISOR_ANALYZE_LIMIT = 1500;

/** وقتی خزش نشان می‌دهد طول عنوان و متا سالم است، «کلیک بالقوه» نرخ کلیک کم‌اطمینان‌تر است و در این ضریب ضرب می‌شود. */
const HODIMA_SEO_ADVISOR_LOW_CTR_HEALTHY_FACTOR = 0.4;

/**
 * آستانه‌ها (گزینه پیشخوان: فقط min_impressions؛ بقیه با فیلتر).
 *
 * @return array{min_impressions: int, query_min_impressions: int, near_first_min: float, near_first_max: float, decline_min_clicks: int, decline_ratio: float, cannibal_min_impressions: int, weak_max_position: float}
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
		'weak_max_position'        => 60.0,
	] );

	return [
		'min_impressions'          => max( 1, (int) ( $config['min_impressions'] ?? 1000 ) ),
		'query_min_impressions'    => max( 1, (int) ( $config['query_min_impressions'] ?? 300 ) ),
		'near_first_min'           => (float) ( $config['near_first_min'] ?? 8.0 ),
		'near_first_max'           => (float) ( $config['near_first_max'] ?? 20.0 ),
		'decline_min_clicks'       => max( 1, (int) ( $config['decline_min_clicks'] ?? 50 ) ),
		'decline_ratio'            => (float) ( $config['decline_ratio'] ?? 0.7 ),
		'cannibal_min_impressions' => max( 1, (int) ( $config['cannibal_min_impressions'] ?? 100 ) ),
		'weak_max_position'        => max( 21.0, (float) ( $config['weak_max_position'] ?? 60.0 ) ),
	];
}

/** گزینه جدول نرخ کلیک آموخته‌شده از داده سایت (autoload خاموش). */
const HODIMA_SEO_ADVISOR_CTR_OPTION = 'hodima_advisor_ctr';

/** کم‌ترین نمایش یک سطل رتبه برای استفاده از آن؛ نمایشی که وزن را به بیشینه می‌رساند؛ و بیشینه وزن داده سایت. */
const HODIMA_SEO_ADVISOR_CTR_MIN_IMPRESSIONS  = 2000;
const HODIMA_SEO_ADVISOR_CTR_FULL_IMPRESSIONS = 20000;
const HODIMA_SEO_ADVISOR_CTR_MAX_WEIGHT       = 0.8;

/**
 * جدول عمومی نرخ کلیک (٪): بیشینه رتبه هر سطل ← نرخ. حدس‌اند نه ادعای گوگل؛ سطل «تا رتبه ۲۰» همان ۱٫۵ ثابت قبلی است.
 *
 * @return array<int, float>
 */
function hodima_seo_advisor_ctr_default(): array {
	return [ 2 => 25.0, 3 => 14.0, 5 => 9.0, 7 => 5.5, 10 => 3.5, 20 => 1.5 ];
}

/**
 * جدول نرخ کلیک را از داده خود سایت می‌سازد و ذخیره می‌کند: برای هر سطل رتبه، نرخ کلیک واقعی کلمه‌های غیربرندی
 * (کلیک ÷ نمایش) با جدول عمومی ترکیب می‌شود؛ وزن داده سایت = (نمایش سطل ÷ ۲۰٬۰۰۰، حداکثر ۱) × ۰٫۸، و سطلی
 * که کمتر از ۲٬۰۰۰ نمایش دارد فقط جدول عمومی را می‌گیرد. آخر سر رتبه بهتر هرگز نرخ کمتر از رتبه بدتر نمی‌گیرد.
 * کلمه‌های برندی (واژه برند داخل کلمه) کنار می‌روند چون نرخشان بالاست.
 *
 * @return array{computed: int, impressions: int, buckets: array<int, array{impressions: int, clicks: int, weight: float, ctr: float}>, table: array<int, float>}
 */
function hodima_seo_advisor_ctr_learn(): array {

	global $wpdb;

	$generic = hodima_seo_advisor_ctr_default();
	ksort( $generic );
	$keys = array_keys( $generic );
	$max  = (float) max( $keys );

	$case = 'CASE';
	foreach ( $keys as $key ) {
		$case .= sprintf( ' WHEN avg_position <= %F THEN %d', (float) $key, (int) $key ); // فقط عدد؛ ورودی کاربر نیست
	}
	$case .= ' END';

	$brand_sql  = '';
	$brand_args = [];
	foreach ( hodima_seo_advisor_brand_terms() as $brand ) {
		if ( '' !== $brand ) {
			$brand_sql   .= ' AND term_key NOT LIKE %s';
			$brand_args[] = '%' . $wpdb->esc_like( $brand ) . '%';
		}
	}

	$rows = (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare( // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- تعداد placeholderها: ۳ ثابت + یکی برای هر واژه برند
			'SELECT ' . $case . ' AS bucket, SUM(clicks) AS clicks, SUM(impressions) AS impressions FROM %i WHERE avg_position > 0 AND avg_position <= %f AND impressions >= %d' . $brand_sql . ' GROUP BY bucket', // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- CASE فقط از کلیدهای عددی جدول ساخته شده
			hodima_seo_advisor_table( 'queries' ),
			$max,
			HODIMA_SEO_ADVISOR_MIN_QUERY_IMPRESSIONS,
			...$brand_args
		),
		ARRAY_A
	);

	$by_bucket = [];
	foreach ( $rows as $row ) {
		$by_bucket[ (int) $row['bucket'] ] = [ 'impressions' => (int) $row['impressions'], 'clicks' => (int) $row['clicks'] ];
	}

	$table   = [];
	$buckets = [];
	$total   = 0;
	$prev    = INF;

	foreach ( $generic as $key => $base ) {
		$impressions = $by_bucket[ $key ]['impressions'] ?? 0;
		$clicks      = $by_bucket[ $key ]['clicks'] ?? 0;
		$weight      = $impressions >= HODIMA_SEO_ADVISOR_CTR_MIN_IMPRESSIONS ? min( 1.0, $impressions / HODIMA_SEO_ADVISOR_CTR_FULL_IMPRESSIONS ) * HODIMA_SEO_ADVISOR_CTR_MAX_WEIGHT : 0.0;
		$observed    = $impressions > 0 ? $clicks / $impressions * 100 : 0.0;
		$value       = min( $prev, $weight * $observed + ( 1 - $weight ) * $base );

		$table[ $key ]   = round( $value, 2 );
		$buckets[ $key ] = [ 'impressions' => $impressions, 'clicks' => $clicks, 'weight' => round( $weight, 2 ), 'ctr' => round( $observed, 2 ) ];
		$total          += $impressions;
		$prev            = $value;
	}

	$learned = [ 'computed' => time(), 'impressions' => $total, 'buckets' => $buckets, 'table' => $table ];
	update_option( HODIMA_SEO_ADVISOR_CTR_OPTION, $learned, false );

	return $learned;
}

/**
 * جدول آموخته‌شده ذخیره‌شده (بیشینه رتبه ← نرخ)، یا آرایه خالی اگر هنوز ساخته نشده است.
 *
 * @return array<int, float>
 */
function hodima_seo_advisor_ctr_learned(): array {

	$stored = get_option( HODIMA_SEO_ADVISOR_CTR_OPTION, [] );
	$table  = is_array( $stored ) && is_array( $stored['table'] ?? null ) ? $stored['table'] : [];
	$out    = [];

	foreach ( $table as $key => $value ) {
		if ( is_numeric( $key ) && is_numeric( $value ) ) {
			$out[ (int) $key ] = (float) $value;
		}
	}

	return $out;
}

/** سهم داده خود سایت در جدول نرخ کلیک (۰ تا ۱؛ میانگین وزن سطل‌ها به نسبت نمایش). */
function hodima_seo_advisor_ctr_learned_share(): float {

	$stored   = get_option( HODIMA_SEO_ADVISOR_CTR_OPTION, [] );
	$buckets  = is_array( $stored ) && is_array( $stored['buckets'] ?? null ) ? $stored['buckets'] : [];
	$weighted = 0.0;
	$total    = 0;

	foreach ( $buckets as $bucket ) {
		$weighted += (float) ( $bucket['weight'] ?? 0 ) * (int) ( $bucket['impressions'] ?? 0 );
		$total    += (int) ( $bucket['impressions'] ?? 0 );
	}

	return $total > 0 ? $weighted / $total : 0.0;
}

/** نرخ کلیک معمول (٪) برای یک رتبه: جدول آموخته‌شده از سایت، وگرنه عمومی؛ فیلتر hodima_seo_advisor_ctr_table آخر. */
function hodima_seo_advisor_expected_ctr( float $position ): float {

	$table = (array) apply_filters( 'hodima_seo_advisor_ctr_table', hodima_seo_advisor_ctr_learned() ?: hodima_seo_advisor_ctr_default() ); // بیشینه رتبه ← نرخ
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

/**
 * آیا یک واژه (یکسان‌شده) در متن به‌عنوان «کلمه» هست؟ مرز کلمه از چپ و راست؛ پسوندهای رایج
 * فارسی (ها، های، ی، ان، تر…) پذیرفته می‌شوند. «مو» داخل «موبایل» حساب نمی‌شود (قبلا زیررشته بود).
 */
function hodima_seo_advisor_word_in( string $haystack, string $word ): bool {
	return 1 === preg_match( '/(?<![\p{L}\p{N}])' . preg_quote( $word, '/' ) . '(?:ها|های|هایی|ی|ای|ان|ات|تر|ترین)?(?![\p{L}\p{N}])/u', $haystack );
}

/** آیا همه کلمه‌های عبارت در متن (از پیش یکسان‌شده) هستند؟ عبارت بدون کلمه قابل‌سنجش «هست» حساب می‌شود (پیشنهاد نمی‌دهیم). */
function hodima_seo_advisor_text_has( string $haystack, string $term ): bool {

	$tokens = array_filter( explode( ' ', hodima_seo_advisor_normalize( $term ) ), static fn( string $t ): bool => mb_strlen( $t ) >= 2 );

	foreach ( $tokens as $token ) {
		if ( ! hodima_seo_advisor_word_in( $haystack, $token ) ) {
			return false;
		}
	}

	return true;
}

/**
 * واژه‌های نام سایت (برند): نام سایت و بخش اول دامنه. کلمه‌ای که برند را دارد نرخ کلیک بسیار بالاتر از
 * جدول عمومی دارد و برایش «کلیک بالقوه» ساختن گمراه‌کننده است. با فیلتر hodima_seo_advisor_brand_terms عوض می‌شود.
 *
 * @return list<string>
 */
function hodima_seo_advisor_brand_terms(): array {

	$host  = (string) wp_parse_url( home_url(), PHP_URL_HOST );
	$label = explode( '.', (string) preg_replace( '/^www\./', '', $host ) )[0];
	$words = array_merge( explode( ' ', hodima_seo_advisor_normalize( (string) get_bloginfo( 'name' ) ) ), [ hodima_seo_advisor_normalize( $label ) ] );
	$words = array_filter( $words, static fn( string $w ): bool => mb_strlen( $w ) >= 4 );

	return array_values( array_unique( array_map( 'strval', (array) apply_filters( 'hodima_seo_advisor_brand_terms', $words ) ) ) );
}

/** آیا کلمه جستجو برندی است؟ */
function hodima_seo_advisor_is_brand( string $term ): bool {

	$normalized = hodima_seo_advisor_normalize( $term );

	foreach ( hodima_seo_advisor_brand_terms() as $brand ) {
		if ( '' !== $brand && hodima_seo_advisor_word_in( $normalized, $brand ) ) {
			return true;
		}
	}

	return false;
}

/**
 * رتبه‌ای که واقع‌بینانه می‌شود به آن رسید: ۴ پله بهتر، نه بهتر از ۷ و نه بدتر از ۱۰ (هدف: صفحه اول؛
 * رتبه ۱۵ ← ۱۰، نه ۷).
 */
function hodima_seo_advisor_target_position( float $position ): float {
	return $position <= 7.0 ? $position : max( 7.0, min( 10.0, $position - 4.0 ) );
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

/** افت صفحه باید دست‌کم این‌قدر بدتر از انتظارِ همراه با کل سایت باشد (۰٫۸۵ = ۱۵٪ بیشتر از افت سایت). */
const HODIMA_SEO_ADVISOR_DECLINE_BEYOND_SITE = 0.85;

/**
 * علت افت کلیک یک صفحه از تغییر رتبه، نمایش و نرخ کلیک (بازه فعلی و قبلی همان ردیف) و وضعیت کل سایت.
 *
 *   position     رتبه دست‌کم ۲ پله بدتر شده
 *   impressions  نمایش ≥ ۲۵٪ کمتر ولی رتبه ثابت (تقاضا یا ایندکس)
 *   ctr          نمایش و رتبه ثابت ولی نرخ کلیک ≥ ۲۵٪ کمتر (عنوان/متا یا نتیجه‌های رقیب)
 *   mixed        هیچ‌کدام تنها نیست
 *
 * @param array<string, mixed> $row
 * @param array{clicks_ratio: float|null, impressions_ratio: float|null}|null $site
 * @return array{cause: string, text: string}
 */
function hodima_seo_advisor_decline_cause( array $row, ?array $site ): array {

	$impressions      = (int) ( $row['impressions'] ?? 0 );
	$prev_impressions = (int) ( $row['prev_impressions'] ?? 0 );
	$position         = (float) ( $row['position'] ?? 0 );
	$prev_position    = (float) ( $row['prev_position'] ?? 0 );
	$delta            = $position > 0 && $prev_position > 0 ? $position - $prev_position : 0.0;
	$impr_ratio       = $prev_impressions > 0 ? $impressions / $prev_impressions : null;
	$ctr_now          = $impressions > 0 ? (int) $row['clicks'] / $impressions : 0.0;
	$ctr_prev         = $prev_impressions > 0 ? (int) $row['prev_clicks'] / $prev_impressions : 0.0;
	$ctr_ratio        = $ctr_prev > 0 ? $ctr_now / $ctr_prev : null;

	if ( $delta >= 2.0 ) {
		$cause = 'position';
		$text  = sprintf( 'علت اصلی: رتبه از %1$s به %2$s بدتر شده است. محتوای صفحه‌های بالاتر از شما، لینک‌های داخلی به این صفحه و تغییرهای اخیر خود صفحه (متن، آدرس، عنوان) را بررسی کنید.', hodima_seo_advisor_num( $prev_position, 1 ), hodima_seo_advisor_num( $position, 1 ) );
	} elseif ( null !== $impr_ratio && $impr_ratio <= 0.75 ) {
		$cause = 'impressions';
		$text  = sprintf( 'علت اصلی: نمایش %s٪ کمتر شده ولی رتبه تقریبا ثابت است؛ یعنی تقاضای جستجو (فصل) کم شده یا گوگل صفحه را برای کلمه‌های کمتری نشان می‌دهد. وضعیت ایندکس و canonical این صفحه را در تب «ایندکس» ببینید.', hodima_seo_advisor_num( ( 1 - $impr_ratio ) * 100 ) );
	} elseif ( null !== $ctr_ratio && $ctr_ratio <= 0.75 ) {
		$cause = 'ctr';
		$text  = sprintf( 'علت اصلی: نمایش و رتبه تقریبا ثابت‌اند ولی نرخ کلیک از %1$s٪ به %2$s٪ رسیده؛ عنوان و توضیح متا ضعیف‌تر شده‌اند یا نتیجه‌های رقیب (ریچ‌اسنیپت، تبلیغ، پاسخ مستقیم) جذاب‌ترند. عنوان و متا را بازنویسی کنید.', hodima_seo_advisor_num( $ctr_prev * 100, 2 ), hodima_seo_advisor_num( $ctr_now * 100, 2 ) );
	} else {
		$cause = 'mixed';
		$text  = 'رتبه، نمایش و نرخ کلیک هرکدام کمی تغییر کرده و علت واحدی دیده نمی‌شود؛ تغییر اخیر عنوان، آدرس، لینک‌های داخلی و رتبه کلمه‌های اصلی را بررسی کنید.';
	}

	$ratio = $site['clicks_ratio'] ?? null;

	if ( null !== $ratio && $ratio <= 0.85 ) {
		$text .= sprintf( ' توجه: کل سایت هم در همین بازه حدود %s٪ کلیک از دست داده؛ «کلیک بالقوه» فقط افت اضافه این صفحه است.', hodima_seo_advisor_num( ( 1 - $ratio ) * 100 ) );
	} elseif ( null !== $ratio && $ratio >= 1.15 ) {
		$text .= sprintf( ' توجه: در همین بازه کل سایت حدود %s٪ رشد کرده، پس این افت مخصوص همین صفحه است.', hodima_seo_advisor_num( ( $ratio - 1 ) * 100 ) );
	}

	return [ 'cause' => $cause, 'text' => $text ];
}

/**
 * پیشنهادهای یک آدرس (تابع خالص: بدون خواندن پایگاه داده).
 *
 * @param array{path: string, clicks: int, impressions: int, position: float, prev_clicks: int} $row
 * @param list<array{term: string, clicks: int, impressions: int, position: float}> $queries پرنمایش‌ترین اول
 * @param array{title_headings: string, content: string, rendered?: array{title: string, h1: string}}|null $text null = متن صفحه معلوم نیست؛ قاعده‌های متن اجرا نمی‌شوند
 * @param array<string, list<array{path: string, impressions: int}>> $competitors کلمه ← آدرس‌های دیگری که برایش نمایش می‌گیرند
 * @param array{clicks_ratio: float|null, impressions_ratio: float|null}|null $site تغییر کل سایت نسبت به بازه قبل (hodima_seo_advisor_site_change)؛ null = ناشناخته
 * @param array{healthy: bool, title_len: int, meta_len: int, problems: list<string>}|null $meta طول عنوان و متا طبق خزش SF (hodima_seo_advisor_crawl_meta_health)؛ null = ناشناخته
 * @param array{total: int, text: int, score: float, depth: int, anchors: list<array{anchor: string, count: int}>}|null $links لینک‌های ورودی طبق خزش (hodima_seo_advisor_crawl_links_info)؛ null = ناشناخته
 * @return list<array{rule: string, term: string, title: string, detail: string, potential: int}>
 */
function hodima_seo_advisor_suggestions( array $row, array $queries, ?array $text, array $competitors = [], ?array $site = null, ?array $meta = null, ?array $links = null ): array {

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
			$detail = sprintf(
				'این صفحه %1$s بار نمایش گرفته ولی فقط %2$s٪ کلیک شده؛ برای رتبه %3$s معمولا حدود %4$s٪ انتظار می‌رود%6$s.%5$s',
				hodima_seo_advisor_num( $row['impressions'] ),
				hodima_seo_advisor_num( $now, 2 ),
				hodima_seo_advisor_num( $position, 1 ),
				hodima_seo_advisor_num( $expected, 1 ),
				'' !== $top ? ' مهم‌ترین کلمه این صفحه «' . $top . '» است؛ آن را ابتدای عنوان بیاورید.' : '',
				hodima_seo_advisor_ctr_learned_share() >= 0.4 ? ' (این نرخ معمول از داده خود سایت تنظیم شده)' : ''
			);
			$title  = 'عنوان و توضیح متا را بازنویسی کنید';

			if ( null !== $meta && $meta['healthy'] ) {
				// طبق خزش طول عنوان و متا سالم است؛ بازنویسی طولی کمکی نمی‌کند و تخمین کلیک بالقوه کم‌اطمینان‌تر است
				$title  = 'نرخ کلیک کم است؛ طول عنوان و متا سالم است';
				$detail = sprintf(
					'این صفحه %1$s بار نمایش گرفته ولی فقط %2$s٪ کلیک شده؛ برای رتبه %3$s معمولا حدود %4$s٪ انتظار می‌رود. طبق خزش، عنوان %5$s و توضیح متا %6$s نویسه است و از نظر طول ایرادی ندارد؛ پس مشکل احتمالا طول نیست. نتیجه این صفحه را در خود گوگل ببینید: عنوان جذاب و متفاوت از رقبا باشد، با قصد جستجو بخواند، و نتیجه‌های تصویر/ویدیو/دسته بالاتر از آن نباشند. فقط در صورت لزوم متن عنوان را بازنویسی کنید.',
					hodima_seo_advisor_num( $row['impressions'] ),
					hodima_seo_advisor_num( $now, 2 ),
					hodima_seo_advisor_num( $position, 1 ),
					hodima_seo_advisor_num( $expected, 1 ),
					hodima_seo_advisor_num( $meta['title_len'] ),
					hodima_seo_advisor_num( $meta['meta_len'] )
				);
				$gain   = max( 1, (int) round( $gain * HODIMA_SEO_ADVISOR_LOW_CTR_HEALTHY_FACTOR ) );
			} elseif ( null !== $meta ) {
				$detail .= ' طبق خزش: ' . implode( '؛ ', $meta['problems'] ) . '.';
			}

			$list[] = [
				'rule'      => 'low_ctr',
				'term'      => '',
				'title'     => $title,
				'detail'    => $detail,
				'potential' => $gain,
			];
		}
	}

	// ۲ و ۳. کلمه‌ها
	$near    = [];
	$missing = [];
	$weak    = [];
	foreach ( $queries as $query ) {
		if ( $query['impressions'] < $cfg['query_min_impressions'] || hodima_seo_advisor_is_brand( $query['term'] ) ) {
			continue; // برندی: نرخ کلیکش از جدول عمومی بالاتر است و پیشنهاد/عدد گمراه‌کننده می‌شود
		}
		$gain = (int) round( $query['impressions'] * ( hodima_seo_advisor_expected_ctr( hodima_seo_advisor_target_position( $query['position'] ) ) - $ctr( $query['clicks'], $query['impressions'] ) ) / 100 );
		$info = sprintf( '%1$s نمایش با رتبه %2$s.', hodima_seo_advisor_num( $query['impressions'] ), hodima_seo_advisor_num( $query['position'], 1 ) );

		if ( $gain <= 0 ) {
			continue;
		}

		if ( null !== $text && $query['position'] <= $cfg['near_first_max'] && ! hodima_seo_advisor_text_has( $text['title_headings'], $query['term'] ) ) {
			$in_content = hodima_seo_advisor_text_has( $text['content'], $query['term'] );
			$rendered   = isset( $text['rendered'] ) ? sprintf( ' طبق خزش، عنوان فعلی «%1$s» و H1 «%2$s» است.', $text['rendered']['title'], $text['rendered']['h1'] ) : '';
			$missing[]  = [
				'rule'      => $in_content ? 'missing_headings' : 'missing_content',
				'term'      => $query['term'],
				'title'     => $in_content ? 'کلمه «' . $query['term'] . '» در عنوان و تیترها نیست' : 'کلمه «' . $query['term'] . '» در متن صفحه نیست',
				'detail'    => $info . ( $in_content ? ' آن را در عنوان یا یک تیتر (H2) بیاورید.' : ' آن را در عنوان، یک تیتر و پاراگراف اول بیاورید، یا بخش پرسش‌های متداول برایش بنویسید.' ) . $rendered,
				'potential' => $gain,
			];
		}

		// رتبه خیلی عقب (بعد از صفحه دوم): این صفحه فقط حاشیه‌ای به کلمه جواب می‌دهد؛ صفحه یا بخش اختصاصی لازم است
		if ( $query['position'] > $cfg['near_first_max'] && $query['position'] <= $cfg['weak_max_position'] ) {
			$weak[] = [
				'rule'      => 'weak_query',
				'term'      => $query['term'],
				'title'     => 'برای «' . $query['term'] . '» صفحه یا بخش اختصاصی بنویسید',
				'detail'    => $info . ' این صفحه جواب اصلی این کلمه نیست. یک مقاله یا صفحه (یا دست‌کم یک بخش با تیتر) مخصوص همین کلمه بسازید و از این صفحه و صفحه‌های مرتبط به آن لینک بدهید.',
				'potential' => $gain,
			];
		}

		if ( $query['position'] >= $cfg['near_first_min'] && $query['position'] <= $cfg['near_first_max'] ) {
			$near[] = [
				'rule'      => 'near_first',
				'term'      => $query['term'],
				'title'     => 'کلمه «' . $query['term'] . '» نزدیک صفحه اول است',
				'detail'    => $info . ' آن را در عنوان، یک تیتر و اولین پاراگراف تقویت کنید و از ۲ تا ۳ صفحه مرتبط به این صفحه لینک بدهید.' . hodima_seo_advisor_links_evidence( $links, $query['term'] ),
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

	foreach ( [ $missing, $near, $weak ] as $group ) {
		usort( $group, static fn( array $a, array $b ): int => $b['potential'] <=> $a['potential'] );
		array_push( $list, ...array_slice( $group, 0, HODIMA_SEO_ADVISOR_MAX_PER_QUERY_RULE ) );
	}

	// ۴. رقابت داخلی (فقط اطلاع؛ بدون کلیک بالقوه): هر کلمه رقابتی یک ردیف، پرنمایش‌ترین‌ها اول
	$cannibal = [];
	foreach ( $queries as $query ) {
		$others = $competitors[ hodima_seo_advisor_term_key( $query['term'] ) ] ?? $competitors[ $query['term'] ] ?? [];
		if ( $query['impressions'] >= $cfg['cannibal_min_impressions'] && $others && ! hodima_seo_advisor_is_brand( $query['term'] ) ) {
			$cannibal[] = [
				'rule'      => 'cannibal',
				'term'      => $query['term'],
				'title'     => sprintf( 'رقابت با %1$s صفحه برای «%2$s»', hodima_seo_advisor_num( count( $others ) ), $query['term'] ),
				// آدرس کامل (نه فقط مسیر) تا مدیر همان را کپی کند و باز کند
				'detail'    => 'علاوه بر این صفحه، ' . implode( '، ', array_map( static fn( array $o ): string => rawurldecode( hodima_seo_advisor_url( $o['path'] ) ), array_slice( $others, 0, 3 ) ) ) . ' هم برای این کلمه نمایش می‌گیرد. یکی را صفحه اصلی کلمه کنید و بقیه را با لینک داخلی به آن وصل کنید.',
				'potential' => 0,
			];
			if ( count( $cannibal ) >= HODIMA_SEO_ADVISOR_MAX_CANNIBAL ) {
				break;
			}
		}
	}

	// ۵. افت کلیک نسبت به انتظار: اگر کل سایت هم افت کرده باشد، انتظار همراه آن پایین می‌آید و فقط افت اضافه
	// این صفحه پیشنهاد می‌شود (افتی که کل سایت دارد علت مخصوص این صفحه ندارد)
	$site_ratio = $site['clicks_ratio'] ?? null;
	$expected   = $row['prev_clicks'] * ( null === $site_ratio ? 1.0 : max( 0.1, min( 1.0, $site_ratio ) ) );
	if ( $row['prev_clicks'] >= $cfg['decline_min_clicks'] && $row['clicks'] < $row['prev_clicks'] * $cfg['decline_ratio'] && $row['clicks'] < $expected * HODIMA_SEO_ADVISOR_DECLINE_BEYOND_SITE ) {
		$diagnosis = hodima_seo_advisor_decline_cause( $row, $site );
		$list[]    = [
			'rule'      => 'decline',
			'term'      => '',
			'cause'     => $diagnosis['cause'],
			'title'     => 'کلیک این صفحه افت کرده',
			'detail'    => sprintf(
				'کلیک از %1$s به %2$s رسیده (%3$s٪ کمتر از بازه قبل). %4$s',
				hodima_seo_advisor_num( $row['prev_clicks'] ),
				hodima_seo_advisor_num( $row['clicks'] ),
				hodima_seo_advisor_num( ( 1 - $row['clicks'] / $row['prev_clicks'] ) * 100 ),
				$diagnosis['text']
			),
			'potential' => (int) max( 0, round( $expected - $row['clicks'] ) ),
		];
	}

	usort( $list, static fn( array $a, array $b ): int => $b['potential'] <=> $a['potential'] );

	// رقابت داخلی جدا از سقف می‌آید تا با پیشنهادهای پرکلیک‌تر بریده نشود (هر دو صفحه رقیب باید آن را ببینند)
	return [ ...array_slice( $list, 0, HODIMA_SEO_ADVISOR_MAX_SUGGESTIONS ), ...$cannibal ];
}

/**
 * آدرس‌های دیگری که برای همان کلمه‌های این آدرس نمایش می‌گیرند. کلمه‌ها با term_key (املای یکسان‌شده)
 * وصل می‌شوند، پس «کیف ي» و «کیف ی» یک کلمه‌اند.
 *
 * @param list<array{term: string, impressions: int}> $queries
 * @return array<string, list<array{path: string, impressions: int}>> term_key ← آدرس‌ها
 */
function hodima_seo_advisor_competitors( string $path, array $queries ): array {

	global $wpdb;

	$resolved = hodima_seo_advisor_resolved_paths();

	if ( isset( $resolved[ $path ] ) ) {
		return []; // این صفحه خودش ریدایرکت/۴۰۴/noindex است؛ رقابتی ندارد
	}

	$min   = hodima_seo_advisor_config()['cannibal_min_impressions'];
	$terms = array_values( array_unique( array_map( static fn( array $q ): string => hodima_seo_advisor_term_key( $q['term'] ), array_filter( $queries, static fn( array $q ): bool => $q['impressions'] >= $min ) ) ) );

	if ( ! $terms ) {
		return [];
	}

	$rows = (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			'SELECT term_key, path, impressions FROM %i WHERE term_key IN (' . implode( ',', array_fill( 0, count( $terms ), '%s' ) ) . ') AND path <> %s AND impressions >= %d ORDER BY impressions DESC', // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- تعداد placeholderها با array_fill هم‌اندازه کلمه‌هاست
			hodima_seo_advisor_table( 'queries' ),
			...$terms,
			...[ $path, $min ]
		),
		ARRAY_A
	);

	$out = [];
	foreach ( $rows as $row ) {
		if ( 1 !== preg_match( '#/page/\d+/?$#', (string) $row['path'] ) && ! isset( $resolved[ (string) $row['path'] ] ) ) {
			$out[ (string) $row['term_key'] ][] = [ 'path' => (string) $row['path'], 'impressions' => (int) $row['impressions'] ];
		}
	}

	return $out;
}

/**
 * تحلیل زنده یک آدرس از داده ذخیره‌شده و متن فعلی صفحه.
 *
 * @param bool $with_steps گام‌های «دقیقا چه کنید» هم ساخته شوند (فقط کادر ویرایش؛ پرهزینه: جستجوی متن همه نوشته‌ها)
 * @return array{row: array<string, mixed>, suggestions: list<array<string, mixed>>, potential: int}|null
 */
function hodima_seo_advisor_analyze( string $path, bool $with_steps = false ): ?array {

	$row = hodima_seo_advisor_page( $path );

	if ( null === $row ) {
		return null;
	}

	$queries     = hodima_seo_advisor_queries( $row['path'], HODIMA_SEO_ADVISOR_QUERIES_PER_PAGE );
	$text        = hodima_seo_advisor_page_text( $row );
	$rendered    = hodima_seo_advisor_crawl_rendered( $row['path'] );

	// عنوان و H1 و H2 رندرشده (خزش) به متن پایگاه داده اضافه می‌شود: کلمه‌ای که در تیتر واقعی صفحه هست «غایب» حساب نشود
	if ( null !== $text && null !== $rendered ) {
		$text['title_headings'] .= ' ' . $rendered['text'];
		$text['content']        .= ' ' . $rendered['text'];
		$text['rendered']        = [ 'title' => $rendered['title'], 'h1' => $rendered['h1'] ];
	}

	$suggestions = hodima_seo_advisor_suggestions( $row, $queries, $text, hodima_seo_advisor_competitors( $row['path'], $queries ), hodima_seo_advisor_site_change(), hodima_seo_advisor_crawl_meta_health( $row['path'] ), hodima_seo_advisor_crawl_links_info( $row['path'] ) );

	if ( $with_steps ) {
		$suggestions = hodima_seo_advisor_enrich( $suggestions, $row, $queries );
	}

	// کاری که با داده بعد از تغییر «اثر داشت» تا وقتی جمع بازه روزهای پیش از تغییر را دارد پیشنهاد نمی‌شود (advisor-verify.php)
	if ( function_exists( 'hodima_seo_advisor_verify_filter' ) ) {
		$suggestions = hodima_seo_advisor_verify_filter( (string) $row['path'], $suggestions );
	}

	return [
		'row'         => $row,
		'suggestions' => $suggestions,
		'potential'   => (int) max( [ 0, ...array_column( $suggestions, 'potential' ) ] ),
	];
}

/**
 * تحلیل یک آدرس ← ستون‌های ردیف جدول (آدرس نادیده‌گرفته‌شده: بدون پیشنهاد). فقط می‌سازد، نمی‌نویسد.
 *
 * @return array{potential: int, advice_count: int, advice: string}
 */
function hodima_seo_advisor_advice_fields( string $path ): array {

	$result = in_array( $path, hodima_seo_advisor_ignored(), true ) ? null : hodima_seo_advisor_analyze( $path );

	if ( null === $result || [] === $result['suggestions'] ) {
		return [ 'potential' => 0, 'advice_count' => 0, 'advice' => '' ];
	}

	return [ 'potential' => $result['potential'], 'advice_count' => count( $result['suggestions'] ), 'advice' => (string) wp_json_encode( $result['suggestions'], JSON_UNESCAPED_UNICODE ) ];
}

/**
 * تحلیل یک آدرس و ذخیره نتیجه در ردیف جدول. true = پیشنهاد دارد.
 */
function hodima_seo_advisor_save_advice( string $path ): bool {

	global $wpdb;

	$fields = hodima_seo_advisor_advice_fields( $path );

	$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		hodima_seo_advisor_table( 'pages' ),
		$fields,
		[ 'path' => $path ],
		[ '%d', '%d', '%s' ],
		[ '%s' ]
	);

	hodima_seo_advisor_sync_fields( $path, $fields );

	return $fields['advice_count'] > 0;
}

/**
 * چرخه عمر «کارهای من» بعد از نوشتن پیشنهادهای یک آدرس (advisor-tasks.php؛ فقط اگر آدرس هنوز در جدول است).
 *
 * @param array{advice: string} $fields خروجی hodima_seo_advisor_advice_fields()
 */
function hodima_seo_advisor_sync_fields( string $path, array $fields ): void {

	if ( ! function_exists( 'hodima_seo_advisor_tasks_sync' ) || null === hodima_seo_advisor_page( $path ) ) {
		return;
	}

	$items = '' !== $fields['advice'] ? json_decode( $fields['advice'], true ) : [];
	hodima_seo_advisor_tasks_sync( $path, is_array( $items ) ? array_values( array_filter( $items, 'is_array' ) ) : [] );
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

/** وضعیت تحلیل نیمه‌کاره (صف آدرس‌های باقی‌مانده)؛ autoload خاموش. */
const HODIMA_SEO_ADVISOR_ANALYSIS_OPTION = 'hodima_advisor_analysis';

/**
 * آدرس‌های قابل‌بررسی: نمایش ≥ حداقل، یا کلیک بازه قبل ≥ حداقل افت؛ پرنمایش‌ترین‌ها اول.
 *
 * @return list<string>
 */
function hodima_seo_advisor_analysis_candidates(): array {

	global $wpdb;

	$cfg = hodima_seo_advisor_config();

	return array_map(
		'strval',
		(array) $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare( 'SELECT path FROM %i WHERE impressions >= %d OR prev_clicks >= %d ORDER BY impressions DESC LIMIT %d', hodima_seo_advisor_table( 'pages' ), $cfg['min_impressions'], $cfg['decline_min_clicks'], HODIMA_SEO_ADVISOR_ANALYZE_LIMIT )
		)
	);
}

/** شمار آدرس‌های دارای پیشنهاد در جدول. */
function hodima_seo_advisor_advised_count(): int {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE advice_count > 0', hodima_seo_advisor_table( 'pages' ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

/**
 * یک نوبت تحلیل آدرس‌ها تا رسیدن به `$deadline` (زمان یونیکس با کسر)؛ صف باقی‌مانده در گزینه می‌ماند تا
 * نوبت بعد ادامه دهد. هر آدرس بلافاصله در ردیف خودش نوشته می‌شود، پس نیمه‌کاره ماندن چیزی را خراب
 * نمی‌کند (ردیف‌های تحلیل‌نشده پیشنهاد قبلی خود را نگه می‌دارند: hodima_seo_advisor_store).
 * اولین نوبت، پیشنهاد آدرس‌هایی را که دیگر قابل‌بررسی نیستند پاک می‌کند.
 *
 * @return bool|WP_Error true = تمام شد، false = وقت تمام شد و صف باقی است
 */
function hodima_seo_advisor_analyze_batch( float $deadline ): bool|WP_Error {

	global $wpdb;

	$table = hodima_seo_advisor_table( 'pages' );
	$fail  = static fn(): WP_Error => new WP_Error( 'hodima_advisor_store', 'ذخیره پیشنهادها ناموفق بود: ' . $wpdb->last_error );
	$state = get_option( HODIMA_SEO_ADVISOR_ANALYSIS_OPTION, null );

	if ( is_array( $state ) && is_array( $state['queue'] ?? null ) ) {
		$queue = array_values( array_map( 'strval', $state['queue'] ) );
	} else {
		$queue = hodima_seo_advisor_analysis_candidates();
		$stale = array_diff( array_map( 'strval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT path FROM %i WHERE advice_count > 0', $table ) ) ), $queue ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		foreach ( $stale as $path ) {
			if ( false === $wpdb->update( $table, [ 'potential' => 0, 'advice_count' => 0, 'advice' => '' ], [ 'path' => $path ], [ '%d', '%d', '%s' ], [ '%s' ] ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				return $fail();
			}
		}
	}

	$done = 0;

	hodima_seo_advisor_crawl_map(); // صدها آدرس: کل جدول خزش یک بار، نه یک پرسش برای هر آدرس

	while ( $queue && microtime( true ) < $deadline ) {
		$path   = (string) array_shift( $queue );
		$fields = hodima_seo_advisor_advice_fields( $path );
		if ( false === $wpdb->update( $table, $fields, [ 'path' => $path ], [ '%d', '%d', '%s' ], [ '%s' ] ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			return $fail();
		}
		hodima_seo_advisor_sync_fields( $path, $fields ); // آدرس‌های «کهنه» بالا عمدا نه: زیر آستانه رفتن برطرف شدن نیست
		++$done;
		if ( 0 === $done % 20 ) {
			update_option( HODIMA_SEO_ADVISOR_ANALYSIS_OPTION, [ 'queue' => $queue ], false ); // قطع ناگهانی: از همین‌جا ادامه می‌یابد
		}
	}

	if ( $queue ) {
		update_option( HODIMA_SEO_ADVISOR_ANALYSIS_OPTION, [ 'queue' => $queue ], false );
		return false;
	}

	delete_option( HODIMA_SEO_ADVISOR_ANALYSIS_OPTION );

	return true;
}

/**
 * تحلیل همه آدرس‌های قابل‌بررسی یک‌جا و در یک تراکنش (آزمون‌ها و ابزار). پیشخوان از آن استفاده نمی‌کند: تغییر «حداقل
 * نمایش» و خزش تازه با hodima_seo_advisor_request_reanalyze() در نوبت‌های زمان‌دار می‌رود (SEO 2.9.9؛ قبلا بدون سقف زمان
 * در همان درخواست ذخیره تنظیم اجرا می‌شد). هر خطا همه را برمی‌گرداند. شمار آدرس دارای پیشنهاد را می‌دهد.
 *
 * @return int|WP_Error
 */
function hodima_seo_advisor_analyze_all(): int|WP_Error {

	delete_option( HODIMA_SEO_ADVISOR_ANALYSIS_OPTION ); // آستانه عوض شده؛ صف قدیمی معتبر نیست

	$result = hodima_seo_advisor_transaction( static fn(): bool|WP_Error => hodima_seo_advisor_analyze_batch( INF ) );

	if ( is_wp_error( $result ) ) {
		delete_option( HODIMA_SEO_ADVISOR_ANALYSIS_OPTION );
		return $result;
	}

	return hodima_seo_advisor_advised_count();
}
