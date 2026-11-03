<?php
/**
 * ماژول «دستار سئو» — قاعده‌های ایراد فنی: هر ایراد یک تابع خالص + یک ثبت مرکزی (SEO 3.6.1)
 * Path: core/advisor/advisor-checks.php
 *
 * تا SEO 3.6.0 همه شرط‌های ایراد داخل یک تابع ۲۴۰ خطی (`hodima_seo_advisor_crawl_issues`) بودند: افزودن یا تغییر یک ایراد خطر
 * شکستن بقیه را داشت و هیچ‌کدام جدا قابل آزمون نبود (الگوی SEOnaut: هر قاعده یک تابع کوچک + فهرست مرکزی؛ FreeCrawl: یک کاتالوگ).
 * حالا هر ایراد یک تابع `hodima_seo_advisor_check_*( ردیف، زمینه )` است که فقط فهرست ایراد برمی‌گرداند (بدون دیتابیس و بدون
 * اثر جانبی)، و `hodima_seo_advisor_checks()` ترتیب و شرط اجرای هر تابع را یک‌جا می‌گوید. رفتار نسبت به 3.6.0 عوض نشده
 * (عکس مرجع `tools/wp-harness/advisor-issues-golden.php`). برچسب، سطح و دستور هر کد: advisor-issues.php.
 *
 * قانون: ایراد تازه = یک تابع check + یک ردیف در `hodima_seo_advisor_checks()` + یک رکورد در کاتالوگ + آزمون «دارد/ندارد».
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

// همه قاعده‌ها امضای یکسان (ردیف، زمینه) دارند تا ثبت مرکزی بتواند هر کدام را یکسان صدا بزند؛ بعضی زمینه را لازم ندارند.
// phpcs:disable Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed

/**
 * یک ایراد با قالب یکسان (کلیدهای ثابت؛ `$extra` کلیدهای اضافه مثل images/total/marked).
 *
 * @param list<array<string, mixed>> $items
 * @param array<string, mixed>       $extra
 * @return array<string, mixed>
 */
function hodima_seo_advisor_issue( string $code, string $detail = '', string $url = '', array $items = [], array $extra = [] ): array {
	return $extra + [ 'code' => $code, 'detail' => $detail, 'url' => $url, 'items' => $items ];
}

/**
 * صفحه‌بندی آرشیو (/page/2/)؛ عنوان، H1 و لینک ورودی آن عمدا مثل آرشیو است (SEO 3.0.5).
 *
 * @param array<string, mixed> $r ردیف جدول خزش
 */
function hodima_seo_advisor_crawl_is_paged( array $r ): bool {
	return 1 === preg_match( '#/page/\d+/?$#', (string) $r['path'] );
}

/**
 * کلید عنوان برای شمردن تکراری‌ها. عنوان در ۱۹۱ نویسه بریده ذخیره می‌شود: برای عنوان بریده‌شده طول واقعی هم در کلید می‌آید تا
 * دو عنوان بلند با ابتدای یکسان «تکراری» نشوند (SEO 3.4.7).
 */
function hodima_seo_advisor_title_key( string $title, int $length ): string {
	return mb_strtolower( $title ) . ( mb_strlen( $title ) >= HODIMA_SEO_ADVISOR_KEY_LENGTH ? '|' . $length : '' );
}

/**
 * داده مشترک همه ردیف‌ها که فقط با دیدن کل خزش ساخته می‌شود (تکراری‌ها، عنوان مشابه، نقشه سایت، لینک‌های خراب).
 * تکراری‌ها فقط بین صفحه‌های سالم و ایندکس‌پذیر شمرده می‌شوند و صفحه‌بندی کنار می‌ماند.
 *
 * @param list<array<string, mixed>> $rows ردیف‌های جدول خزش (با ستون‌های impressions، avg_position، kind)
 * @return array{titles: array<string, int>, h1s: array<string, int>, similar: array<string, array<string, list<string>>>, by_path: array<string, array<string, mixed>>, sitemap: array<string, true>, bad: array<string, array<string, list<array{path: string, note: string}>>>}
 */
function hodima_seo_advisor_checks_context( array $rows ): array {

	$titles = [];
	$h1s    = [];
	foreach ( $rows as $r ) {
		if ( 200 === (int) $r['status'] && 1 === (int) $r['indexable'] && ! hodima_seo_advisor_crawl_is_paged( $r ) ) {
			$tk            = hodima_seo_advisor_title_key( (string) $r['title'], (int) $r['title_len'] );
			$titles[ $tk ] = ( $titles[ $tk ] ?? 0 ) + 1;
			$h1k           = mb_strtolower( (string) $r['h1'] );
			$h1s[ $h1k ]   = ( $h1s[ $h1k ] ?? 0 ) + 1;
		}
	}

	// عنوان یا H1 «مشابه» (نه یکسان) صفحه دیگر: کانیبال بالقوه پیش از اینکه گوگل برای هر دو نمایش بدهد (SEO 3.5.7، advisor-similar.php)
	$site_words = hodima_seo_advisor_similar_tokens( (string) get_bloginfo( 'name' ) );
	$sim_title  = [];
	$sim_h1     = [];
	foreach ( $rows as $r ) {
		if ( 200 === (int) $r['status'] && 1 === (int) $r['indexable'] && ! hodima_seo_advisor_crawl_is_paged( $r ) && '/' !== (string) $r['path'] ) {
			$sim_title[ (string) $r['path'] ] = (string) $r['title'];
			$sim_h1[ (string) $r['path'] ]    = (string) $r['h1'];
		}
	}
	$similar = [];
	foreach ( [ 'عنوان' => hodima_seo_advisor_similar_pairs( $sim_title, $site_words ), 'H1' => hodima_seo_advisor_similar_pairs( $sim_h1, $site_words ) ] as $what => $found ) {
		foreach ( $found as $path => $partners ) {
			foreach ( $partners as $partner ) {
				$similar[ $path ][ $partner ][] = $what;
			}
		}
	}

	$by_path = [];
	foreach ( $rows as $r ) {
		$by_path[ (string) $r['path'] ] = $r;
	}

	return [
		'titles'  => $titles,
		'h1s'     => $h1s,
		'similar' => $similar,
		'by_path' => $by_path,
		'sitemap' => hodima_seo_advisor_sitemap_paths(),
		'bad'     => hodima_seo_advisor_crawl_bad_links(),
	];
}

/* ── قاعده‌ها: هر تابع (ردیف، زمینه) ← فهرست ایراد ─────────────────────────────────────────── */

/**
 * نقشه سایت: آدرسی که در نقشه است باید ۲۰۰ و ایندکس‌پذیر باشد (هر وضعیتی، حتی ریدایرکت و ۴۰۴).
 *
 * @param array<string, mixed> $r   ردیف جدول خزش (+ impressions، kind)
 * @param array<string, mixed> $ctx زمینه hodima_seo_advisor_checks_context()
 * @return list<array<string, mixed>>
 */
function hodima_seo_advisor_check_sitemap_bad( array $r, array $ctx ): array {

	$status = (int) $r['status'];

	if ( ! $ctx['sitemap'] || ! isset( $ctx['sitemap'][ (string) $r['path'] ] ) || ( 200 === $status && 0 !== (int) $r['indexable'] ) ) {
		return [];
	}

	return [ hodima_seo_advisor_issue( 'sitemap_bad', 200 !== $status ? sprintf( 'کد %s', number_format_i18n( $status ) ) : ( 'Canonicalised' === (string) $r['reason'] ? 'canonical به صفحه دیگر' : 'noindex' ) ) ];
}

/**
 * canonical به آدرسی که سالم نیست (ردیف قدیمی پیش از خزنده داخلی، words = -1، بررسی نمی‌شود).
 *
 * @param array<string, mixed> $r   ردیف جدول خزش (+ impressions، kind)
 * @param array<string, mixed> $ctx زمینه hodima_seo_advisor_checks_context()
 * @return list<array<string, mixed>>
 */
function hodima_seo_advisor_check_canonical_bad( array $r, array $ctx ): array {

	$canonical = (string) $r['canonical'];

	if ( (int) $r['words'] < 0 || 200 !== (int) $r['status'] || '' === $canonical || '!ext' === $canonical || $canonical === (string) $r['path'] || ! isset( $ctx['by_path'][ $canonical ] ) ) {
		return [];
	}

	$target = $ctx['by_path'][ $canonical ];

	if ( 200 === (int) $target['status'] && 'Noindex' !== (string) $target['reason'] ) {
		return [];
	}

	return [ hodima_seo_advisor_issue( 'canonical_bad', sprintf( 'به %s اشاره می‌کند (%s)', rawurldecode( $canonical ), 200 !== (int) $target['status'] ? 'کد ' . number_format_i18n( (int) $target['status'] ) : 'noindex' ) ) ];
}

/**
 * عنوان: بدون عنوان، کوتاه، بلند (عرض پیکسلی همان خط‌کش سئوباکس؛ ردیف قدیمی بی‌پیکسل ایراد طولی نمی‌سازد، SEO 3.5.4).
 *
 * @param array<string, mixed> $r   ردیف جدول خزش (+ impressions، kind)
 * @param array<string, mixed> $ctx زمینه hodima_seo_advisor_checks_context()
 * @return list<array<string, mixed>>
 */
function hodima_seo_advisor_check_title( array $r, array $ctx ): array {

	if ( '' === (string) $r['title'] ) {
		return [ hodima_seo_advisor_issue( 'no_title' ) ];
	}

	$state = hodima_seo_advisor_crawl_px_state( (int) $r['title_len'], (int) $r['title_px'], 'title' );
	$limit = hodima_seo_px_limit( 'title' );

	if ( 'short' === $state && ! hodima_seo_advisor_crawl_is_paged( $r ) ) { // صفحه‌بندی آرشیو عنوان کوتاه آرشیو را عمدا دارد (SEO 3.3.5)
		return [ hodima_seo_advisor_issue( 'title_short', sprintf( '%1$s از %2$s پیکسل؛ حداقل %3$s', number_format_i18n( (int) $r['title_px'] ), number_format_i18n( $limit['max'] ), number_format_i18n( $limit['min'] ) ) ) ];
	}

	if ( 'long' === $state ) {
		return [ hodima_seo_advisor_issue( 'title_long', sprintf( '%1$s از %2$s پیکسل؛ %3$s پیکسل بیشتر', number_format_i18n( (int) $r['title_px'] ), number_format_i18n( $limit['max'] ), number_format_i18n( (int) $r['title_px'] - $limit['max'] ) ) ) ];
	}

	return [];
}

/**
 * توضیح متای خالی؛ برای صفحه‌های /page/N/ توضیح جدا لازم نیست (SEO 3.3.5).
 *
 * @param array<string, mixed> $r   ردیف جدول خزش (+ impressions، kind)
 * @param array<string, mixed> $ctx زمینه hodima_seo_advisor_checks_context()
 * @return list<array<string, mixed>>
 */
function hodima_seo_advisor_check_no_meta( array $r, array $ctx ): array {
	return (int) $r['meta_len'] <= 0 && ! hodima_seo_advisor_crawl_is_paged( $r ) ? [ hodima_seo_advisor_issue( 'no_meta' ) ] : [];
}

/**
 * `nosnippet` یا `max-snippet:0` (متاتگ یا هدر).
 *
 * @param array<string, mixed> $r   ردیف جدول خزش (+ impressions، kind)
 * @param array<string, mixed> $ctx زمینه hodima_seo_advisor_checks_context()
 * @return list<array<string, mixed>>
 */
function hodima_seo_advisor_check_no_snippet( array $r, array $ctx ): array {
	return 1 === (int) ( $r['no_snippet'] ?? 0 ) ? [ hodima_seo_advisor_issue( 'no_snippet' ) ] : [];
}

/**
 * H1 خالی.
 *
 * @param array<string, mixed> $r   ردیف جدول خزش (+ impressions، kind)
 * @param array<string, mixed> $ctx زمینه hodima_seo_advisor_checks_context()
 * @return list<array<string, mixed>>
 */
function hodima_seo_advisor_check_no_h1( array $r, array $ctx ): array {
	return '' === (string) $r['h1'] ? [ hodima_seo_advisor_issue( 'no_h1' ) ] : [];
}

/**
 * عنوان یکسان با صفحه‌های دیگر (فقط بین صفحه‌های سالم و ایندکس‌پذیر غیرصفحه‌بندی؛ حروف کوچک‌شده).
 *
 * @param array<string, mixed> $r   ردیف جدول خزش (+ impressions، kind)
 * @param array<string, mixed> $ctx زمینه hodima_seo_advisor_checks_context()
 * @return list<array<string, mixed>>
 */
function hodima_seo_advisor_check_dup_title( array $r, array $ctx ): array {

	$title = (string) $r['title'];

	if ( '' === $title || hodima_seo_advisor_crawl_is_paged( $r ) ) {
		return [];
	}

	$count = $ctx['titles'][ hodima_seo_advisor_title_key( $title, (int) $r['title_len'] ) ] ?? 0;

	return $count > 1 ? [ hodima_seo_advisor_issue( 'dup_title', sprintf( 'در %s صفحه', number_format_i18n( $count ) ) ) ] : [];
}

/**
 * dup_h1
 *
 * @param array<string, mixed> $r   ردیف جدول خزش (+ impressions، kind)
 * @param array<string, mixed> $ctx زمینه hodima_seo_advisor_checks_context()
 * @return list<array<string, mixed>>
 */
function hodima_seo_advisor_check_dup_h1( array $r, array $ctx ): array {

	$h1 = (string) $r['h1'];

	if ( '' === $h1 || hodima_seo_advisor_crawl_is_paged( $r ) ) {
		return [];
	}

	$count = $ctx['h1s'][ mb_strtolower( $h1 ) ] ?? 0;

	return $count > 1 ? [ hodima_seo_advisor_issue( 'dup_h1', sprintf( 'در %s صفحه', number_format_i18n( $count ) ) ) ] : [];
}

/**
 * عنوان یا H1 «مشابه» (نه یکسان) صفحه دیگر.
 *
 * @param array<string, mixed> $r   ردیف جدول خزش (+ impressions، kind)
 * @param array<string, mixed> $ctx زمینه hodima_seo_advisor_checks_context()
 * @return list<array<string, mixed>>
 */
function hodima_seo_advisor_check_near_dup( array $r, array $ctx ): array {

	$path = (string) $r['path'];

	if ( empty( $ctx['similar'][ $path ] ) || hodima_seo_advisor_crawl_is_paged( $r ) ) {
		return [];
	}

	$items = [];
	foreach ( array_slice( $ctx['similar'][ $path ], 0, HODIMA_SEO_ADVISOR_SIMILAR_MAX_PARTNERS, true ) as $partner => $whats ) {
		$other   = $ctx['by_path'][ $partner ] ?? [ 'title' => '', 'h1' => '' ];
		$items[] = [ 'path' => (string) $partner, 'note' => sprintf( '%1$s شبیه: «%2$s»', implode( ' و ', $whats ), mb_substr( in_array( 'H1', $whats, true ) && ! in_array( 'عنوان', $whats, true ) ? (string) $other['h1'] : (string) $other['title'], 0, 70 ) ) ];
	}

	return [ hodima_seo_advisor_issue( 'near_dup', sprintf( 'شبیه %s صفحه دیگر', number_format_i18n( count( $ctx['similar'][ $path ] ) ) ), '', $items ) ];
}

/**
 * پاسخ کند سرور (کندتر از آستانه).
 *
 * @param array<string, mixed> $r   ردیف جدول خزش (+ impressions، kind)
 * @param array<string, mixed> $ctx زمینه hodima_seo_advisor_checks_context()
 * @return list<array<string, mixed>>
 */
function hodima_seo_advisor_check_slow( array $r, array $ctx ): array {
	return (int) $r['resp_ms'] >= HODIMA_SEO_ADVISOR_CRAWL_SLOW_MS ? [ hodima_seo_advisor_issue( 'slow', sprintf( 'پاسخ سرور %s ثانیه', number_format_i18n( (int) $r['resp_ms'] / 1000, 1 ) ) ) ] : [];
}

/**
 * بدون لینک ورودی یا لینک ورودی کم (صفحه اصلی و صفحه‌بندی کنار می‌مانند).
 *
 * @param array<string, mixed> $r   ردیف جدول خزش (+ impressions، kind)
 * @param array<string, mixed> $ctx زمینه hodima_seo_advisor_checks_context()
 * @return list<array<string, mixed>>
 */
function hodima_seo_advisor_check_inlinks( array $r, array $ctx ): array {

	if ( '/' === (string) $r['path'] || hodima_seo_advisor_crawl_is_paged( $r ) ) {
		return [];
	}

	$inlinks = (int) $r['inlinks'];

	if ( 0 === $inlinks ) {
		return [ hodima_seo_advisor_issue( 'orphan' ) ];
	}

	if ( $inlinks <= HODIMA_SEO_ADVISOR_CRAWL_LOW_INLINKS ) {
		$text = (int) $r['text_links'];
		return [ hodima_seo_advisor_issue( 'low_inlinks', sprintf( '%s لینک ورودی', number_format_i18n( $inlinks ) ) . ( $text >= 0 ? sprintf( '؛ %s لینک از متن', number_format_i18n( $text ) ) : '' ) ) ];
	}

	return [];
}

/**
 * لینک‌های این صفحه به آدرس خراب، ریدایرکت‌شده یا ایندکس‌نشدنی (هر مقصد: مسیر + توضیح).
 *
 * @param array<string, mixed> $r   ردیف جدول خزش (+ impressions، kind)
 * @param array<string, mixed> $ctx زمینه hodima_seo_advisor_checks_context()
 * @return list<array<string, mixed>>
 */
function hodima_seo_advisor_check_link_targets( array $r, array $ctx ): array {

	$out = [];

	foreach ( [ 'link_broken', 'link_redirect', 'link_noindex' ] as $code ) {
		if ( ! empty( $ctx['bad'][ (string) $r['path'] ][ $code ] ) ) {
			$out[] = hodima_seo_advisor_issue( $code, sprintf( '%s لینک', number_format_i18n( count( $ctx['bad'][ (string) $r['path'] ][ $code ] ) ) ), '', array_slice( $ctx['bad'][ (string) $r['path'] ][ $code ], 0, HODIMA_SEO_ADVISOR_CRAWL_ITEMS ) );
		}
	}

	return $out;
}

/**
 * بیش از یک H1.
 *
 * @param array<string, mixed> $r   ردیف جدول خزش (+ impressions، kind)
 * @param array<string, mixed> $ctx زمینه hodima_seo_advisor_checks_context()
 * @return list<array<string, mixed>>
 */
function hodima_seo_advisor_check_multi_h1( array $r, array $ctx ): array {
	return (int) $r['h1_count'] > 1 ? [ hodima_seo_advisor_issue( 'multi_h1', sprintf( '%s تیتر H1', number_format_i18n( (int) $r['h1_count'] ) ) ) ] : [];
}

/**
 * تصویرها (SEO 3.4.9): ایراد = نبود alt، لینک بی‌نام یا «alt می‌خواهد»؛ alt خالی تنها = «بررسی کنید» (ایراد نیست). علامت‌های
 * ادمین («تزئینی است») همین‌جا اعمال می‌شود. images: ردیف‌های تصویر (آدرس، وضعیت، جایگاه) برای فهرست «کدام تصویرها؟»؛ total: شمار کل.
 *
 * @param array<string, mixed> $r   ردیف جدول خزش (+ impressions، kind)
 * @param array<string, mixed> $ctx زمینه hodima_seo_advisor_checks_context()
 * @return list<array<string, mixed>>
 */
function hodima_seo_advisor_check_images( array $r, array $ctx ): array {

	if ( (int) $r['img_noalt'] <= 0 && (int) $r['img_empty'] <= 0 ) {
		return [];
	}

	$out      = [];
	$split    = hodima_seo_advisor_img_split( hodima_seo_advisor_img_list_decode( (string) ( $r['img_list'] ?? '' ) ) );
	$need     = count( array_filter( $split['issues'], static fn( array $e ): bool => ! empty( $e['need'] ) ) );
	$issue_n  = max( 0, (int) $r['img_noalt'] ) + $need;
	$review_n = max( 0, (int) $r['img_empty'] - count( $split['ok'] ) - $need );

	if ( $issue_n > 0 ) {
		$out[] = hodima_seo_advisor_issue( 'img_alt', sprintf( '%s تصویر', number_format_i18n( $issue_n ) ), '', [], [ 'images' => $split['issues'], 'total' => $issue_n, 'marked' => $split['ok'] ] );
	}
	if ( $review_n > 0 ) {
		$out[] = hodima_seo_advisor_issue( 'img_empty', sprintf( '%s تصویر', number_format_i18n( $review_n ) ), '', [], [ 'images' => $split['review'], 'total' => $review_n, 'marked' => $split['ok'] ] );
	}

	return $out;
}

/**
 * لینک داخلی با http در سایت https.
 *
 * @param array<string, mixed> $r   ردیف جدول خزش (+ impressions، kind)
 * @param array<string, mixed> $ctx زمینه hodima_seo_advisor_checks_context()
 * @return list<array<string, mixed>>
 */
function hodima_seo_advisor_check_http_link( array $r, array $ctx ): array {
	return (int) $r['http_links'] > 0 ? [ hodima_seo_advisor_issue( 'http_link', sprintf( '%s لینک', number_format_i18n( (int) $r['http_links'] ) ) ) ] : [];
}

/**
 * بدون canonical.
 *
 * @param array<string, mixed> $r   ردیف جدول خزش (+ impressions، kind)
 * @param array<string, mixed> $ctx زمینه hodima_seo_advisor_checks_context()
 * @return list<array<string, mixed>>
 */
function hodima_seo_advisor_check_no_canonical( array $r, array $ctx ): array {
	return '' === (string) $r['canonical'] ? [ hodima_seo_advisor_issue( 'no_canonical' ) ] : [];
}

/**
 * متن کم. نوع صفحه از داده گوگل می‌آید و صفحه بدون نمایش (همان‌هایی که محتوای کمشان مهم‌تر است) نوع ندارد: فقط برای
 * نامزدهای «محتوای کم» با روتر شناخته می‌شود (SEO 3.4.5). دسته‌ها و صفحه اصلی و صفحه‌بندی کنار می‌مانند.
 *
 * @param array<string, mixed> $r   ردیف جدول خزش (+ impressions، kind)
 * @param array<string, mixed> $ctx زمینه hodima_seo_advisor_checks_context()
 * @return list<array<string, mixed>>
 */
function hodima_seo_advisor_check_thin( array $r, array $ctx ): array {

	$path  = (string) $r['path'];
	$words = (int) $r['words'];

	if ( $words >= HODIMA_SEO_ADVISOR_CRAWL_THIN_WORDS || '/' === $path || hodima_seo_advisor_crawl_is_paged( $r ) ) {
		return [];
	}

	$kind = (string) $r['kind'];
	if ( '' === $kind ) {
		$kind = hodima_seo_advisor_classify( $path )['kind'];
	}

	return '' !== $kind && ! taxonomy_exists( $kind ) ? [ hodima_seo_advisor_issue( 'thin', sprintf( '%s کلمه', number_format_i18n( $words ) ) ) ] : [];
}

/**
 * صفحه سالم که در نقشه سایت نیست (فقط وقتی نقشه سایت شناخته شده است).
 *
 * @param array<string, mixed> $r   ردیف جدول خزش (+ impressions، kind)
 * @param array<string, mixed> $ctx زمینه hodima_seo_advisor_checks_context()
 * @return list<array<string, mixed>>
 */
function hodima_seo_advisor_check_not_in_sitemap( array $r, array $ctx ): array {
	return $ctx['sitemap'] && ! isset( $ctx['sitemap'][ (string) $r['path'] ] ) && ! hodima_seo_advisor_crawl_is_paged( $r ) ? [ hodima_seo_advisor_issue( 'not_in_sitemap' ) ] : [];
}

/**
 * جزیره (لینک ورودی دارد ولی از صفحه اصلی نمی‌رسیم، SEO 3.3.5) یا صفحه عمیق.
 *
 * @param array<string, mixed> $r   ردیف جدول خزش (+ impressions، kind)
 * @param array<string, mixed> $ctx زمینه hodima_seo_advisor_checks_context()
 * @return list<array<string, mixed>>
 */
function hodima_seo_advisor_check_depth( array $r, array $ctx ): array {

	$path  = (string) $r['path'];
	$depth = (int) $r['depth'];

	if ( HODIMA_SEO_ADVISOR_CRAWL_UNREACHABLE === $depth ) {
		return (int) $r['inlinks'] > 0 && '/' !== $path && ! hodima_seo_advisor_crawl_is_paged( $r )
			? [ hodima_seo_advisor_issue( 'island', sprintf( '%s لینک ورودی، همه از صفحه‌های جدا از سایت', number_format_i18n( (int) $r['inlinks'] ) ) ) ]
			: [];
	}

	return $depth >= HODIMA_SEO_ADVISOR_CRAWL_DEEP ? [ hodima_seo_advisor_issue( 'deep', sprintf( '%s کلیک از صفحه اصلی', number_format_i18n( $depth ) ) ) ] : [];
}

/**
 * صفحه‌ای که ایندکس‌پذیر نیست (ریدایرکت، خطا، noindex) و نمایش گوگل یا لینک داخلی دارد. ریدایرکت فقط وقتی ایراد است که
 * لینک داخلی به آن برود (دستورش اصلاح همان لینک‌هاست؛ تا SEO 3.0.1 با نمایش گوگل هم می‌آمد و هیچ‌وقت برطرف نمی‌شد).
 *
 * @param array<string, mixed> $r   ردیف جدول خزش (+ impressions، kind)
 * @param array<string, mixed> $ctx زمینه hodima_seo_advisor_checks_context()
 * @return list<array<string, mixed>>
 */
function hodima_seo_advisor_check_status( array $r, array $ctx ): array {

	$status = (int) $r['status'];
	$out    = [];

	if ( $status >= 300 && $status < 400 ) {
		if ( (int) $r['inlinks'] > 0 ) {
			$out[] = hodima_seo_advisor_issue( 'redirect', '' !== (string) $r['redirect_to'] ? 'به این آدرس می‌رود:' : '', (string) $r['redirect_to'] ); // آدرس جدا با کپی (وسط جمله به‌هم می‌ریخت)
		}
		$next = $ctx['by_path'][ (string) $r['redirect_to'] ] ?? null;
		if ( null !== $next && (int) $next['status'] >= 300 && (int) $next['status'] < 400 ) {
			$out[] = hodima_seo_advisor_issue( 'redirect_chain', 'به آدرسی می‌رود که خودش ریدایرکت است:', (string) $r['redirect_to'] );
		}
	} elseif ( $status >= 400 ) {
		$out[] = hodima_seo_advisor_issue( 'broken', sprintf( 'کد %s', number_format_i18n( $status ) ) );
	} elseif ( 200 === $status && 0 === (int) $r['indexable'] ) {
		$out[] = hodima_seo_advisor_issue( 'noindex', (string) $r['reason'] );
	}

	return $out;
}

/**
 * ثبت مرکزی: ترتیب اجرا = ترتیب ایرادها در هر صفحه (نباید عوض شود؛ فهرست و آزمون‌ها به آن تکیه دارند).
 * when: always = همیشه؛ live = صفحه ۲۰۰ و ایندکس‌پذیر؛ known = live و ردیف از خزنده داخلی (words ≥ ۰)؛
 *       dead = ایندکس‌پذیر نیست و نمایش گوگل یا لینک داخلی دارد.
 * codes: کدهایی که تابع می‌تواند بسازد (آزمون ثبت می‌کند هر کد کاتالوگ دقیقا یک صاحب دارد).
 *
 * @return array<string, array{when: string, codes: list<string>, fn: string}>
 */
function hodima_seo_advisor_checks(): array {

	static $checks = null;

	return $checks ??= [
		'sitemap_bad'    => [ 'when' => 'always', 'codes' => [ 'sitemap_bad' ], 'fn' => 'hodima_seo_advisor_check_sitemap_bad' ],
		'canonical_bad'  => [ 'when' => 'always', 'codes' => [ 'canonical_bad' ], 'fn' => 'hodima_seo_advisor_check_canonical_bad' ],
		'title'          => [ 'when' => 'live', 'codes' => [ 'no_title', 'title_short', 'title_long' ], 'fn' => 'hodima_seo_advisor_check_title' ],
		'no_meta'        => [ 'when' => 'live', 'codes' => [ 'no_meta' ], 'fn' => 'hodima_seo_advisor_check_no_meta' ],
		'no_snippet'     => [ 'when' => 'live', 'codes' => [ 'no_snippet' ], 'fn' => 'hodima_seo_advisor_check_no_snippet' ],
		'no_h1'          => [ 'when' => 'live', 'codes' => [ 'no_h1' ], 'fn' => 'hodima_seo_advisor_check_no_h1' ],
		'dup_title'      => [ 'when' => 'live', 'codes' => [ 'dup_title' ], 'fn' => 'hodima_seo_advisor_check_dup_title' ],
		'dup_h1'         => [ 'when' => 'live', 'codes' => [ 'dup_h1' ], 'fn' => 'hodima_seo_advisor_check_dup_h1' ],
		'near_dup'       => [ 'when' => 'live', 'codes' => [ 'near_dup' ], 'fn' => 'hodima_seo_advisor_check_near_dup' ],
		'slow'           => [ 'when' => 'live', 'codes' => [ 'slow' ], 'fn' => 'hodima_seo_advisor_check_slow' ],
		'inlinks'        => [ 'when' => 'live', 'codes' => [ 'orphan', 'low_inlinks' ], 'fn' => 'hodima_seo_advisor_check_inlinks' ],
		'link_targets'   => [ 'when' => 'live', 'codes' => [ 'link_broken', 'link_redirect', 'link_noindex' ], 'fn' => 'hodima_seo_advisor_check_link_targets' ],
		'multi_h1'       => [ 'when' => 'known', 'codes' => [ 'multi_h1' ], 'fn' => 'hodima_seo_advisor_check_multi_h1' ],
		'images'         => [ 'when' => 'known', 'codes' => [ 'img_alt', 'img_empty' ], 'fn' => 'hodima_seo_advisor_check_images' ],
		'http_link'      => [ 'when' => 'known', 'codes' => [ 'http_link' ], 'fn' => 'hodima_seo_advisor_check_http_link' ],
		'no_canonical'   => [ 'when' => 'known', 'codes' => [ 'no_canonical' ], 'fn' => 'hodima_seo_advisor_check_no_canonical' ],
		'thin'           => [ 'when' => 'known', 'codes' => [ 'thin' ], 'fn' => 'hodima_seo_advisor_check_thin' ],
		'not_in_sitemap' => [ 'when' => 'known', 'codes' => [ 'not_in_sitemap' ], 'fn' => 'hodima_seo_advisor_check_not_in_sitemap' ],
		'depth'          => [ 'when' => 'live', 'codes' => [ 'island', 'deep' ], 'fn' => 'hodima_seo_advisor_check_depth' ],
		'status'         => [ 'when' => 'dead', 'codes' => [ 'redirect', 'redirect_chain', 'broken', 'noindex' ], 'fn' => 'hodima_seo_advisor_check_status' ],
	];
}

/**
 * ایرادهای یک ردیف خزش با اجرای همه قاعده‌ها به ترتیب ثبت.
 *
 * @param array<string, mixed> $r   ردیف جدول خزش + impressions، avg_position، kind
 * @param array<string, mixed> $ctx hodima_seo_advisor_checks_context()
 * @return list<array<string, mixed>>
 */
function hodima_seo_advisor_checks_run( array $r, array $ctx ): array {

	$live  = 200 === (int) $r['status'] && 1 === (int) $r['indexable'];
	$known = (int) $r['words'] >= 0; // ردیف قدیمی پیش از خزنده داخلی ستون‌های تازه را ندارد (-1)
	$seen  = (int) ( $r['impressions'] ?? 0 ) > 0 || (int) $r['inlinks'] > 0;
	$out   = [];

	foreach ( hodima_seo_advisor_checks() as $check ) {
		$run = match ( $check['when'] ) {
			'always' => true,
			'live'   => $live,
			'known'  => $live && $known,
			'dead'   => ! $live && $seen,
			default  => false,
		};

		if ( $run ) {
			foreach ( $check['fn']( $r, $ctx ) as $issue ) {
				$out[] = $issue;
			}
		}
	}

	return $out;
}
