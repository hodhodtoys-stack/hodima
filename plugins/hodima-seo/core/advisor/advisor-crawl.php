<?php
/**
 * ماژول «دستار سئو» — سلامت خزش: نقشه خزش، ایرادها و آمار (داده از خزنده داخلی advisor-crawler.php؛ ورود فایل Screaming Frog از 3.1.7 برداشته شد)
 * Path: core/advisor/advisor-crawl.php
 *
 * ورودی: دو فایل CSV با نام ثابت در پوشه امن wp-content/uploads/hodima-advisor/ (مدیر خودش با FTP/هاست
 * می‌گذارد؛ آپلود از پیشخوان نیست):
 *   internal_html.csv (Internal ← HTML) و all_inlinks.csv (Bulk Export ← Links ← All Inlinks، اختیاری).
 * داده «نرمال‌شده و مستقل از منبع» ذخیره می‌شود (جدول‌های crawl_pages و crawl_links با ستون منبع در
 * وضعیت) تا بعدا خزنده داخلی همان جدول‌ها را پر کند و قاعده‌ها دوباره نوشته نشوند.
 * فایل در حال آپلود خوانده نمی‌شود، فایل اشتباه داده قبلی را خراب نمی‌کند، خواندن خط‌به‌خط است
 * (fgetcsv؛ حافظه با اندازه فایل بالا نمی‌رود). فقط می‌خواند و پیشنهاد می‌دهد؛ چیزی را عوض نمی‌کند.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** وضعیت آخرین خواندن (گزینه؛ autoload خاموش). */
const HODIMA_SEO_ADVISOR_CRAWL_OPTION = 'hodima_advisor_crawl';

/** خزش قدیمی‌تر از این روز «قدیمی» نشان می‌گیرد. */
const HODIMA_SEO_ADVISOR_CRAWL_STALE_DAYS = 30;

/** صفحه با این تعداد لینک متنی (و این تعداد با عبارت کلمه) «لینک داخلی کافی» دارد؛ دستور تمرکز را روی عنوان و متن می‌گذارد. */
const HODIMA_SEO_ADVISOR_CRAWL_WELL_LINKED      = 15;
const HODIMA_SEO_ADVISOR_CRAWL_WELL_LINKED_TERM = 5;

/** عنوان کوتاه‌تر / بلندتر از این (نویسه) ایراد است؛ گوگل حدود ۶۰ نویسه را نشان می‌دهد. */
const HODIMA_SEO_ADVISOR_CRAWL_TITLE_MIN = 30;
const HODIMA_SEO_ADVISOR_CRAWL_TITLE_MAX = 65;

/** پاسخ سرور کندتر از این (میلی‌ثانیه) «کند» است. */
const HODIMA_SEO_ADVISOR_CRAWL_SLOW_MS = 2000;

/** صفحه با این تعداد لینک ورودی یکتا یا کمتر «لینک ورودی کم» دارد. */
const HODIMA_SEO_ADVISOR_CRAWL_LOW_INLINKS = 2;

/** طول توضیح متا که سالم حساب می‌شود (نویسه). */
const HODIMA_SEO_ADVISOR_CRAWL_META_MIN = 70;
const HODIMA_SEO_ADVISOR_CRAWL_META_MAX = 170;

/** صفحه‌ای با این نمایش یا بیشتر «پرنمایش» است (آمار «ایراد در صفحه‌های پرنمایش»). */
const HODIMA_SEO_ADVISOR_CRAWL_BIG_IMPRESSIONS = 1000;

/** محتوای کم: متن اصلی کمتر از این تعداد کلمه (فقط نوشته، برگه و محصول شناخته‌شده). */
const HODIMA_SEO_ADVISOR_CRAWL_THIN_WORDS = 120;

/** صفحه‌ای که از صفحه اصلی دست‌کم این‌قدر کلیک فاصله دارد «عمیق» است. */
const HODIMA_SEO_ADVISOR_CRAWL_DEEP = 5;

/** حداکثر مقصد نمایش‌داده‌شده در هر ایراد لینک. */
const HODIMA_SEO_ADVISOR_CRAWL_ITEMS = 10;

/**
 * دستور robots به گوگل می‌گوید زیر عنوان متنی نشان نده؟ (`max-snippet:0` یا `nosnippet`، در متاتگ یا هدر X-Robots-Tag.)
 * با خزش واقعی هدهدلی ۸ محصول این را داشتند (تنظیم «پیشرفته» سئوباکس) و نرخ کلیکشان کم می‌شد.
 */
function hodima_seo_advisor_robots_no_snippet( string ...$values ): bool {
	return 1 === preg_match( '/(?:^|[\s,;])(?:nosnippet|max-snippet\s*:\s*0)(?![\d])/i', implode( ',', $values ) );
}

/* ── نقشه خزش و صفحه‌های حل‌شده (برای رقابت و قاعده نرخ کلیک) ── */

/** ستون‌های جدول خزش که نقشه و ردیف تک از آن می‌خوانند. */
const HODIMA_SEO_ADVISOR_CRAWL_COLUMNS = 'path, status, indexable, reason, title_len, meta_len, inlinks, text_links, depth, score, title, h1, h2';

/**
 * ردیف خام جدول خزش ← آرایه با نوع‌های درست.
 *
 * @param array<string, mixed> $r
 * @return array{status: int, indexable: bool, reason: string, title_len: int, meta_len: int, inlinks: int, text_links: int, depth: int, score: float, title: string, h1: string, h2: string}
 */
function hodima_seo_advisor_crawl_map_row( array $r ): array {
	return [ 'status' => (int) $r['status'], 'indexable' => 1 === (int) $r['indexable'], 'reason' => (string) $r['reason'], 'title_len' => (int) $r['title_len'], 'meta_len' => (int) $r['meta_len'], 'inlinks' => (int) $r['inlinks'], 'text_links' => (int) $r['text_links'], 'depth' => (int) $r['depth'], 'score' => (float) $r['score'], 'title' => (string) $r['title'], 'h1' => (string) $r['h1'], 'h2' => (string) $r['h2'] ];
}

/**
 * همه صفحه‌های خزش‌شده، آدرس ← وضعیت. یک بار در هر درخواست خوانده می‌شود (تحلیل صدها آدرس را صدا می‌زند).
 *
 * `$only`: فقط همین آدرس (کادر ویرایش یک صفحه): اگر نقشه کامل هنوز خوانده نشده، فقط یک ردیف خوانده می‌شود
 * (تا SEO 2.9.8 باز کردن کادر ویرایش هر صفحه کل جدول خزش را در حافظه می‌آورد).
 *
 * @return array<string, array{status: int, indexable: bool, reason: string, title_len: int, meta_len: int, inlinks: int, text_links: int, depth: int, score: float, title: string, h1: string, h2: string}>
 */
function hodima_seo_advisor_crawl_map( bool $reset = false, ?string $only = null ): array {

	global $wpdb;
	static $map    = null;
	static $single = [];

	if ( $reset ) {
		$map    = null;
		$single = [];
		hodima_seo_advisor_resolved_paths( true );
		return [];
	}

	if ( null !== $only && null === $map ) {
		if ( ! array_key_exists( $only, $single ) ) {
			hodima_seo_advisor_ensure_tables();
			$r               = $wpdb->get_row( $wpdb->prepare( 'SELECT ' . HODIMA_SEO_ADVISOR_CRAWL_COLUMNS . ' FROM %i WHERE path = %s', hodima_seo_advisor_table( 'crawl_pages' ), $only ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- نام ستون‌ها ثابت همین فایل است
			$single[ $only ] = is_array( $r ) ? hodima_seo_advisor_crawl_map_row( $r ) : null;
		}
		return null !== $single[ $only ] ? [ $only => $single[ $only ] ] : [];
	}

	if ( null === $map ) {
		hodima_seo_advisor_ensure_tables();
		$map = [];
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT ' . HODIMA_SEO_ADVISOR_CRAWL_COLUMNS . ' FROM %i', hodima_seo_advisor_table( 'crawl_pages' ) ), ARRAY_A ) as $r ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- نام ستون‌ها ثابت همین فایل است
			$map[ (string) $r['path'] ] = hodima_seo_advisor_crawl_map_row( $r );
		}
		$single = [];
	}

	return $map;
}

/**
 * وضعیت خزش یک صفحه، یا null اگر در خزش نیست.
 *
 * @return array{status: int, indexable: bool, reason: string, title_len: int, meta_len: int, inlinks: int, text_links: int, depth: int, score: float, title: string, h1: string, h2: string}|null
 */
function hodima_seo_advisor_crawl_row( string $path ): ?array {
	return hodima_seo_advisor_crawl_map( false, $path )[ $path ] ?? null;
}

/**
 * صفحه‌هایی که دیگر «رقیب» نیستند: طبق خزش ریدایرکت‌اند، خطا می‌دهند یا noindex/canonical به صفحه دیگرند، یا
 * (طبق بررسی روتر در «آدرس‌های خراب») در سایت ۴۰۴‌اند یا ریدایرکت می‌شوند.
 *
 * صفحه اصلی همیشه حل‌شده است (home): هدفش کلمه مشخصی نیست و گوگل فقط به‌خاطر لینک‌هایش گاهی آن را برای کلمه‌ها نشان می‌دهد؛
 * با صفحه دیگری رقابت واقعی ندارد.
 *
 * @return array<string, string> آدرس ← redirect | gone | noindex | home
 */
function hodima_seo_advisor_resolved_paths( bool $reset = false ): array {

	static $cache = null;

	if ( $reset ) {
		$cache = null;
		return [];
	}

	if ( null !== $cache ) {
		return $cache;
	}

	global $wpdb;

	$cache = [ ( hodima_seo_advisor_path_key( home_url( '/' ) ) ?? '/' ) => 'home' ];

	// فقط ردیف‌های ناسالم (کادر ویرایش یک صفحه هم این را می‌خواند؛ کل جدول خزش لازم نیست)
	hodima_seo_advisor_ensure_tables();
	$rows = (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare( 'SELECT path, status FROM %i WHERE status >= 300 OR ( status = 200 AND indexable = 0 )', hodima_seo_advisor_table( 'crawl_pages' ) ),
		ARRAY_A
	);

	foreach ( $rows as $r ) {
		$path   = (string) $r['path'];
		$status = (int) $r['status'];
		if ( ! isset( $cache[ $path ] ) ) {
			$cache[ $path ] = $status >= 400 ? 'gone' : ( $status >= 300 ? 'redirect' : 'noindex' );
		}
	}

	foreach ( (array) ( hodima_seo_advisor_health()['dead'] ?? [] ) as $dead ) {
		if ( is_array( $dead ) && isset( $dead['path'], $dead['state'] ) ) {
			$cache[ (string) $dead['path'] ] ??= in_array( $dead['state'], [ 'redirect', 'rule' ], true ) ? 'redirect' : 'gone';
		}
	}

	return $cache;
}

/** برچسب کوتاه دلیل حل‌شدن. */
function hodima_seo_advisor_resolved_label( string $code ): string {
	return [ 'redirect' => 'ریدایرکت می‌شود', 'gone' => 'در سایت ۴۰۴ است', 'noindex' => 'noindex یا canonical', 'home' => 'صفحه اصلی' ][ $code ] ?? '';
}

/**
 * طول عنوان و متا یک صفحه طبق خزش: ok (هر دو سالم)، یا فهرست ایرادها، یا null (در خزش نیست/صفحه سالم نیست).
 *
 * @return array{healthy: bool, title_len: int, meta_len: int, problems: list<string>}|null
 */
function hodima_seo_advisor_crawl_meta_health( string $path ): ?array {

	$r = hodima_seo_advisor_crawl_row( $path );

	if ( null === $r || 200 !== $r['status'] || ! $r['indexable'] ) {
		return null;
	}

	$problems = [];

	if ( $r['title_len'] < HODIMA_SEO_ADVISOR_CRAWL_TITLE_MIN ) {
		$problems[] = 'عنوان کوتاه است';
	} elseif ( $r['title_len'] > HODIMA_SEO_ADVISOR_CRAWL_TITLE_MAX ) {
		$problems[] = 'عنوان بلند است';
	}
	if ( $r['meta_len'] <= 0 ) {
		$problems[] = 'توضیح متا ندارد';
	} elseif ( $r['meta_len'] < HODIMA_SEO_ADVISOR_CRAWL_META_MIN ) {
		$problems[] = 'توضیح متا کوتاه است';
	} elseif ( $r['meta_len'] > HODIMA_SEO_ADVISOR_CRAWL_META_MAX ) {
		$problems[] = 'توضیح متا بلند است';
	}

	return [ 'healthy' => [] === $problems, 'title_len' => $r['title_len'], 'meta_len' => $r['meta_len'], 'problems' => $problems ];
}

/* ── عنوان و تیترهای رندرشده (انتشار ۴) ── */

/**
 * عنوان، H1 و دو H2 اول صفحه طبق خزش (آنچه گوگل واقعا می‌بیند)، یکسان‌شده برای جستجوی کلمه؛ null = در خزش نیست.
 *
 * @return array{text: string, title: string, h1: string}|null
 */
function hodima_seo_advisor_crawl_rendered( string $path ): ?array {

	$r = hodima_seo_advisor_crawl_row( $path );

	if ( null === $r || 200 !== $r['status'] || '' === trim( $r['title'] . $r['h1'] . $r['h2'] ) ) {
		return null;
	}

	return [ 'text' => hodima_seo_advisor_normalize( $r['title'] . ' ' . $r['h1'] . ' ' . $r['h2'] ), 'title' => $r['title'], 'h1' => $r['h1'] ];
}

/* ── شواهد لینک داخلی (انتشار ۳) ── */

/**
 * متن لینک‌های ورودی یک صفحه (فقط لینک متنی بدنه و follow)، پرتکرارترین اول؛ هر متن با شمار صفحه‌های مبدأ یکتا.
 *
 * @return list<array{anchor: string, count: int}>
 */
function hodima_seo_advisor_crawl_anchors( string $path, int $limit = 40 ): array {

	global $wpdb;

	$rows = (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			"SELECT anchor, COUNT(DISTINCT source_path) AS n FROM %i WHERE dest_path = %s AND link_position = 'content' AND is_follow = 1 AND anchor <> '' GROUP BY anchor ORDER BY n DESC, anchor ASC LIMIT %d",
			hodima_seo_advisor_table( 'crawl_links' ),
			$path,
			max( 1, $limit )
		),
		ARRAY_A
	);

	return array_map( static fn( array $r ): array => [ 'anchor' => (string) $r['anchor'], 'count' => (int) $r['n'] ], $rows );
}

/**
 * لینک‌های ورودی یک صفحه طبق خزش، یا null اگر در خزش نیست یا فایل لینک‌ها (all_inlinks.csv) خوانده نشده.
 *
 * @return array{total: int, text: int, score: float, depth: int, anchors: list<array{anchor: string, count: int}>}|null
 */
function hodima_seo_advisor_crawl_links_info( string $path ): ?array {

	$r = hodima_seo_advisor_crawl_row( $path );

	if ( null === $r || $r['text_links'] < 0 ) {
		return null;
	}

	return [ 'total' => $r['inlinks'], 'text' => $r['text_links'], 'score' => $r['score'], 'depth' => $r['depth'], 'anchors' => hodima_seo_advisor_crawl_anchors( $path ) ];
}

/**
 * شمار لینک‌هایی (از متن‌های لینک) که عبارت کلمه را دارند. کلمه و متن یکسان‌سازی می‌شوند (ی/ی، اعراب، حروف).
 *
 * @param list<array{anchor: string, count: int}> $anchors
 */
function hodima_seo_advisor_crawl_term_links( array $anchors, string $term ): int {

	$sum = 0;

	foreach ( $anchors as $a ) {
		if ( hodima_seo_advisor_text_has( hodima_seo_advisor_normalize( $a['anchor'] ), $term ) ) {
			$sum += $a['count'];
		}
	}

	return $sum;
}

/**
 * جمله شواهد لینک داخلی برای «کلمه نزدیک صفحه اول» (تابع خالص؛ فقط از ورودی می‌خواند). خالی = خزش/لینک‌ها نیست.
 *
 * @param array{total: int, text: int, score: float, depth: int, anchors: list<array{anchor: string, count: int}>}|null $links
 */
function hodima_seo_advisor_links_evidence( ?array $links, string $term ): string {

	if ( null === $links ) {
		return '';
	}

	$num        = static fn( int|float $v, int $d = 0 ): string => number_format_i18n( $v, $d );
	$term_links = hodima_seo_advisor_crawl_term_links( $links['anchors'], $term );
	$text       = sprintf( ' طبق خزش این صفحه %1$s لینک ورودی یکتا دارد (%2$s از متن)%3$s.', $num( $links['total'] ), $num( $links['text'] ), $links['score'] > 0 ? '، Link Score ' . $num( $links['score'], 1 ) : '' );

	if ( 0 === $term_links ) {
		$text .= sprintf( ' هیچ لینک متنی با عبارت «%s» به آن نیست؛ لینک‌های موجود را با همین متن بنویسید و از صفحه‌های مرتبط لینک تازه با همین متن بدهید.', $term );
	} else {
		$text .= sprintf( ' %1$s لینک با متن «%2$s» دارد.', $num( $term_links ), $term );
		if ( $links['text'] <= 3 ) {
			$text .= ' لینک متنی کم است؛ ۳ تا ۵ لینک از صفحه‌های مرتبط بدهید.';
		} elseif ( $links['text'] >= HODIMA_SEO_ADVISOR_CRAWL_WELL_LINKED && $term_links >= HODIMA_SEO_ADVISOR_CRAWL_WELL_LINKED_TERM ) {
			$text .= ' لینک داخلی این صفحه کم نیست؛ لینک بیشتر اولویت نیست و عنوان، تیتر و متن را تقویت کنید.';
		}
	}

	if ( $links['depth'] >= 4 ) {
		$text .= sprintf( ' عمق خزش %s است؛ آن را از منو یا صفحه اصلی نزدیک‌تر کنید.', $num( $links['depth'] ) );
	}

	return $text;
}

/**
 * جمله مقایسه لینک متنی با کلمه بین صفحه‌های یک گروه رقابت (برنده اول). خالی = شواهد کافی نیست.
 *
 * @param list<array<string, mixed>> $pages صفحه‌ها با کلید term_links (int|null)، برنده اول
 */
function hodima_seo_advisor_competition_links_note( string $term, array $pages ): string {

	$known = array_values( array_filter( $pages, static fn( array $p ): bool => null !== ( $p['term_links'] ?? null ) ) );

	if ( count( $known ) < 2 || $known[0]['path'] !== $pages[0]['path'] ) {
		return '';
	}

	$winner = $known[0];
	$rival  = $known[1];

	foreach ( array_slice( $known, 1 ) as $p ) {
		if ( $p['term_links'] > $rival['term_links'] ) {
			$rival = $p;
		}
	}

	$n   = static fn( int $v ): string => number_format_i18n( $v );
	$url = rawurldecode( hodima_seo_advisor_url( (string) $rival['path'] ) ); // آدرس کامل رقیب، نه فقط مسیر

	if ( $rival['term_links'] > $winner['term_links'] ) {
		return sprintf( ' لینک‌های داخلی: برنده فقط %1$s لینک با متن «%2$s» دارد ولی %3$s %4$s لینک؛ لینک‌ها را به برنده منتقل کنید.', $n( $winner['term_links'] ), $term, $url, $n( $rival['term_links'] ) );
	}

	if ( $winner['term_links'] - $rival['term_links'] <= 2 ) {
		return sprintf( ' لینک‌های داخلی: برنده %1$s و %2$s %3$s لینک با متن «%4$s» دارند؛ تقریبا برابر است و گوگل بین آن‌ها مردد می‌ماند. لینک‌های رقیب را به برنده بدهید.', $n( $winner['term_links'] ), $url, $n( $rival['term_links'] ), $term );
	}

	return sprintf( ' لینک‌های داخلی: برنده %1$s لینک با متن «%2$s» دارد در برابر %3$s لینک رقیب؛ همین لینک‌دهی را حفظ کنید.', $n( $winner['term_links'] ), $term, $n( $rival['term_links'] ) );
}

/* ── ذخیره ── */

/**
 * جایگزینی کامل جدول‌های خزش در یک تراکنش؛ خطا = داده قبلی دست‌نخورده.
 *
 * @param array<string, array<string, mixed>> $pages
 * @param list<list<scalar>>                  $links
 * @return array{pages: int, links: int}|WP_Error
 */
function hodima_seo_advisor_crawl_store( array $pages, array $links ): array|WP_Error {

	global $wpdb;

	hodima_seo_advisor_ensure_tables();
	hodima_seo_advisor_crawl_map( true );

	$page_table = hodima_seo_advisor_table( 'crawl_pages' );
	$link_table = hodima_seo_advisor_table( 'crawl_links' );

	$page_rows = [];
	foreach ( $pages as $p ) {
		$page_rows[] = [ $p['path'], $p['status'], $p['indexable'], $p['reason'], $p['title'], $p['title_len'], $p['meta_len'], $p['h1'], $p['h2'], $p['resp_ms'], $p['inlinks'], $p['text_links'], $p['depth'], $p['score'], $p['redirect'], (int) ( $p['no_snippet'] ?? 0 ) ];
	}

	return hodima_seo_advisor_transaction( static function () use ( $wpdb, $page_table, $link_table, $page_rows, $links ): array|WP_Error {

		$fail = static fn( string $what ): WP_Error => new WP_Error( 'hodima_crawl_store', sprintf( 'ذخیره %1$s ناموفق بود (%2$s)؛ داده قبلی دست‌نخورده ماند.', $what, $wpdb->last_error ) );

		foreach ( [ $page_table, $link_table ] as $table ) {
			if ( false === $wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $table ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				return $fail( 'داده' );
			}
		}

		$insert = [
			[ $page_table, 'path, status, indexable, reason, title, title_len, meta_len, h1, h2, resp_ms, inlinks, text_links, depth, score, redirect_to, no_snippet', '(%s,%d,%d,%s,%s,%d,%d,%s,%s,%d,%d,%d,%d,%f,%s,%d)', $page_rows, 200, 'صفحه‌ها' ],
			[ $link_table, 'source_path, dest_path, link_position, anchor, is_follow', '(%s,%s,%s,%s,%d)', $links, 400, 'لینک‌ها' ],
		];

		foreach ( $insert as [ $table, $columns, $row_format, $rows, $size, $what ] ) {
			$skipped = 0;
			foreach ( array_chunk( $rows, $size ) as $chunk ) {
				if ( false !== hodima_seo_advisor_insert( $table, $columns, $row_format, $chunk ) ) {
					continue;
				}
				// دسته افتاد (معمولا ردیف تکراریِ از دید مقایسه پایگاه داده): تک‌تک؛ فقط ردیف مشکل‌دار رد می‌شود
				foreach ( $chunk as $one ) {
					if ( false === hodima_seo_advisor_insert( $table, $columns, $row_format, [ $one ] ) ) {
						++$skipped;
					}
				}
			}
			if ( $skipped > max( 5, (int) ( count( $rows ) * 0.05 ) ) ) {
				return $fail( $what );
			}
		}

		return [ 'pages' => count( $page_rows ), 'links' => count( $links ) ];
	} );
}

/* ── وضعیت ── */

/**
 * وضعیت ذخیره‌شده.
 *
 * @return array{imported: int, pages: int, links: int}
 */
function hodima_seo_advisor_crawl_status(): array {

	$stored = get_option( HODIMA_SEO_ADVISOR_CRAWL_OPTION, [] );
	$stored = is_array( $stored ) ? $stored : [];

	return [
		'imported' => (int) ( $stored['imported'] ?? 0 ),
		'pages'    => (int) ( $stored['pages'] ?? 0 ),
		'links'    => (int) ( $stored['links'] ?? 0 ),
	];
}

/* ── ایرادها ── */

/** گروه چیپ‌های فیلتر ← کدهای ایراد. */
const HODIMA_SEO_ADVISOR_CRAWL_GROUPS = [
	'meta'     => [ 'no_meta', 'no_snippet' ],
	'title'    => [ 'no_title', 'title_short', 'title_long', 'no_h1', 'multi_h1' ],
	'dup'      => [ 'dup_title', 'dup_h1' ],
	'slow'     => [ 'slow' ],
	'redirect' => [ 'redirect', 'noindex', 'broken' ],
	'linkfix'  => [ 'link_broken', 'link_redirect', 'link_noindex', 'redirect_chain' ],
	'content'  => [ 'img_alt', 'thin' ],
	'tech'     => [ 'canonical_bad', 'no_canonical', 'http_link', 'sitemap_bad', 'not_in_sitemap', 'deep' ],
	'links'    => [ 'low_inlinks' ],
	'orphan'   => [ 'orphan' ],
];

/**
 * برچسب، رنگ نشان و دستور هر ایراد.
 *
 * @return array{label: string, level: string, fix: string}
 */
function hodima_seo_advisor_crawl_issue_info( string $code ): array {

	return [
		'no_meta'     => [ 'label' => 'بدون توضیح متا', 'level' => 'bad', 'fix' => 'توضیح متا (۱۲۰ تا ۱۶۰ نویسه) بنویسید.' ],
		'no_snippet'  => [ 'label' => 'متن زیر عنوان ممنوع', 'level' => 'bad', 'fix' => 'در کادر سئو همین صفحه، بخش «پیشرفته»، حداکثر طول متن (max-snippet) را روی پیش‌فرض بگذارید؛ مقدار ۰ به گوگل می‌گوید زیر عنوان متنی نشان ندهد و نرخ کلیک را کم می‌کند.' ],
		'no_h1'       => [ 'label' => 'بدون H1', 'level' => 'warn', 'fix' => 'هر صفحه یک تیتر اصلی (H1) لازم دارد.' ],
		'no_title'    => [ 'label' => 'بدون عنوان', 'level' => 'bad', 'fix' => 'عنوان سئو بنویسید.' ],
		'title_short' => [ 'label' => 'عنوان کوتاه', 'level' => 'bad', 'fix' => 'عنوان را به ۴۸ تا ۶۲ نویسه برسانید.' ],
		'title_long'  => [ 'label' => 'عنوان بلند', 'level' => 'warn', 'fix' => 'عنوان را به ۴۸ تا ۶۲ نویسه برسانید تا در گوگل بریده نشود.' ],
		'dup_title'   => [ 'label' => 'عنوان تکراری', 'level' => 'warn', 'fix' => 'برای هر صفحه عنوان یگانه بنویسید.' ],
		'dup_h1'      => [ 'label' => 'H1 تکراری', 'level' => 'warn', 'fix' => 'تیتر اصلی هر صفحه یگانه باشد.' ],
		'slow'        => [ 'label' => 'کند', 'level' => 'warn', 'fix' => 'کش و سرعت سرور را بررسی کنید.' ],
		'redirect'    => [ 'label' => 'ریدایرکت', 'level' => 'info', 'fix' => 'لینک‌های داخلی را مستقیم به آدرس مقصد بدهید.' ],
		'noindex'     => [ 'label' => 'noindex یا canonical', 'level' => 'info', 'fix' => 'اگر این صفحه باید در گوگل باشد، noindex/canonical را بردارید.' ],
		'broken'      => [ 'label' => 'خطای ۴۰۴/۵۰۰', 'level' => 'bad', 'fix' => 'لینک‌های داخلی به آن را درست کنید یا ریدایرکت بدهید.' ],
		'low_inlinks' => [ 'label' => 'لینک ورودی کم', 'level' => 'warn', 'fix' => 'از صفحه‌های مرتبط (دسته، مقاله) لینک متنی بدهید.' ],
		'link_broken'    => [ 'label' => 'لینک به صفحه خراب', 'level' => 'bad', 'fix' => 'این صفحه به آدرس‌هایی لینک می‌دهد که خطای ۴۰۴/۵۰۰ می‌دهند؛ هر لینک را به آدرس درست تغییر بدهید یا بردارید.' ],
		'link_redirect'  => [ 'label' => 'لینک به ریدایرکت', 'level' => 'warn', 'fix' => 'این صفحه به آدرس‌هایی لینک می‌دهد که به جای دیگر می‌روند؛ لینک را مستقیم به آدرس مقصد بدهید.' ],
		'link_noindex'   => [ 'label' => 'لینک به صفحه ایندکس‌نشدنی', 'level' => 'info', 'fix' => 'در متن این صفحه به صفحه‌ای لینک داده شده که noindex است یا به صفحه دیگری canonical دارد؛ اگر لازم نیست لینک را بردارید یا به آدرس اصلی بدهید.' ],
		'redirect_chain' => [ 'label' => 'زنجیره ریدایرکت', 'level' => 'warn', 'fix' => 'این آدرس به آدرسی می‌رود که خودش دوباره ریدایرکت می‌شود؛ ریدایرکت را مستقیم به مقصد نهایی بدهید.' ],
		'multi_h1'       => [ 'label' => 'چند H1', 'level' => 'warn', 'fix' => 'هر صفحه فقط یک تیتر اصلی (H1) داشته باشد؛ بقیه را H2 کنید.' ],
		'img_alt'        => [ 'label' => 'تصویر بدون alt', 'level' => 'warn', 'fix' => 'برای تصویرهای متن صفحه توضیح کوتاه (alt) بنویسید؛ در کتابخانه رسانه یا همان‌جا که تصویر گذاشته‌اید.' ],
		'thin'           => [ 'label' => 'متن کم', 'level' => 'info', 'fix' => 'متن اصلی این صفحه کم است؛ اگر قرار است در گوگل رتبه بگیرد توضیح کامل‌تر اضافه کنید.' ],
		'canonical_bad'  => [ 'label' => 'canonical اشتباه', 'level' => 'bad', 'fix' => 'canonical این صفحه به آدرسی اشاره می‌کند که ۲۰۰ نیست یا noindex است؛ آن را به آدرس درست یا خود صفحه برگردانید.' ],
		'no_canonical'   => [ 'label' => 'بدون canonical', 'level' => 'warn', 'fix' => 'صفحه آدرس canonical ندارد؛ در کادر سئو یا افزونه سئو آن را فعال کنید.' ],
		'http_link'      => [ 'label' => 'لینک http', 'level' => 'warn', 'fix' => 'در سایت https لینک داخلی با http می‌دهد؛ لینک‌ها را https کنید تا ریدایرکت اضافه نشود.' ],
		'sitemap_bad'    => [ 'label' => 'ایراد در نقشه سایت', 'level' => 'bad', 'fix' => 'این آدرس در نقشه سایت است ولی صفحه سالم و ایندکس‌پذیر نیست؛ از نقشه سایت بردارید یا صفحه را درست کنید.' ],
		'not_in_sitemap' => [ 'label' => 'خارج از نقشه سایت', 'level' => 'info', 'fix' => 'صفحه سالم و ایندکس‌پذیر است ولی در نقشه سایت نیست؛ اگر باید در گوگل باشد به نقشه سایت اضافه کنید.' ],
		'deep'           => [ 'label' => 'صفحه عمیق', 'level' => 'info', 'fix' => 'از صفحه اصلی چند کلیک فاصله دارد؛ از صفحه‌های نزدیک‌تر (دسته، مقاله) به آن لینک بدهید.' ],
		'orphan'      => [ 'label' => 'بدون لینک ورودی', 'level' => 'bad', 'fix' => 'از دست‌کم دو صفحه مرتبط به آن لینک بدهید.' ],
	][ $code ] ?? [ 'label' => $code, 'level' => 'info', 'fix' => '' ];
}

/**
 * لینک‌های داخلی به صفحه خراب، ریدایرکت‌شده یا ایندکس‌نشدنی، گروه‌شده بر اساس صفحه مبدأ و نوع ایراد.
 * لینک به صفحه noindex فقط از متن (نه منو و فوتر: سبد و حساب کاربری عمدا noindex‌اند).
 *
 * @return array<string, array<string, list<array{path: string, note: string}>>> مبدأ ← کد ایراد ← مقصدها
 */
function hodima_seo_advisor_crawl_bad_links(): array {

	global $wpdb;

	$rows = (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			'SELECT l.source_path, l.dest_path, l.link_position, l.anchor, c.status, c.indexable, c.reason, c.redirect_to FROM %i l INNER JOIN %i c ON c.path = l.dest_path WHERE c.status >= 300 OR (c.status = 200 AND c.indexable = 0)',
			hodima_seo_advisor_table( 'crawl_links' ),
			hodima_seo_advisor_table( 'crawl_pages' )
		),
		ARRAY_A
	);

	$out = [];

	foreach ( $rows as $r ) {
		$status = (int) $r['status'];
		$dest   = (string) $r['dest_path'];
		$anchor = '' !== (string) $r['anchor'] ? sprintf( 'متن لینک «%s»', $r['anchor'] ) : 'بدون متن لینک';

		if ( $status >= 400 ) {
			$code = 'link_broken';
			$note = sprintf( 'کد %s؛ %s', number_format_i18n( $status ), $anchor );
		} elseif ( $status >= 300 ) {
			$code = 'link_redirect';
			$note = ( '' !== (string) $r['redirect_to'] ? sprintf( 'می‌رود به %s؛ ', rawurldecode( (string) $r['redirect_to'] ) ) : 'ریدایرکت می‌شود؛ ' ) . $anchor;
		} elseif ( 'content' === (string) $r['link_position'] ) {
			$code = 'link_noindex';
			$note = ( 'Canonicalised' === (string) $r['reason'] ? 'canonical به صفحه دیگر؛ ' : 'noindex؛ ' ) . $anchor;
		} else {
			continue;
		}

		$out[ (string) $r['source_path'] ][ $code ][ $dest ] ??= [ 'path' => $dest, 'note' => $note ]; // هر مقصد یک بار
	}

	foreach ( $out as $source => $codes ) {
		foreach ( $codes as $code => $items ) {
			$out[ $source ][ $code ] = array_values( $items );
		}
	}

	return $out;
}

/**
 * ایرادهای هر صفحه خزش‌شده، با نمایش گوگل (از داده Search Console)، پرنمایش‌ترین اول.
 * ایرادهای ریدایرکت/noindex/خطا فقط وقتی می‌آیند که صفحه نمایش یا لینک ورودی داشته باشد.
 *
 * @return list<array{path: string, impressions: int, position: float, inlinks: int, text_links: int, issues: list<array{code: string, detail: string, url: string}>}>
 */
function hodima_seo_advisor_crawl_issues(): array {

	global $wpdb;

	$rows = (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			'SELECT c.*, COALESCE(p.impressions, 0) AS impressions, COALESCE(p.avg_position, 0) AS avg_position, COALESCE(p.kind, %s) AS kind FROM %i c LEFT JOIN %i p ON p.path = c.path',
			'',
			hodima_seo_advisor_table( 'crawl_pages' ),
			hodima_seo_advisor_table( 'pages' )
		),
		ARRAY_A
	);

	// تکراری‌ها فقط بین صفحه‌های سالم و ایندکس‌پذیر شمرده می‌شوند. صفحه‌بندی آرشیو (/page/2/) H1 و عنوان آرشیو را عمدا دارد
	// و لینک ورودی‌اش فقط از خود صفحه‌بندی است؛ با خزش واقعی ۶ از ۶ «H1 تکراری» و ۳ «لینک ورودی کم» همین‌ها بودند (SEO 3.0.5)
	$paged  = static fn( array $r ): bool => 1 === preg_match( '#/page/\d+/?$#', (string) $r['path'] );
	$titles = [];
	$h1s    = [];
	foreach ( $rows as $r ) {
		if ( 200 === (int) $r['status'] && 1 === (int) $r['indexable'] && ! $paged( $r ) ) {
			$titles[ mb_strtolower( (string) $r['title'] ) ] = ( $titles[ mb_strtolower( (string) $r['title'] ) ] ?? 0 ) + 1;
			$h1s[ mb_strtolower( (string) $r['h1'] ) ]       = ( $h1s[ mb_strtolower( (string) $r['h1'] ) ] ?? 0 ) + 1;
		}
	}

	$out = [];

	$by_path = [];
	foreach ( $rows as $r ) {
		$by_path[ (string) $r['path'] ] = $r;
	}
	$sitemap = hodima_seo_advisor_sitemap_paths();
	$bad     = hodima_seo_advisor_crawl_bad_links();
	$n       = static fn( int|float $value ): string => number_format_i18n( $value );

	foreach ( $rows as $r ) {

		$status      = (int) $r['status'];
		$impressions = (int) $r['impressions'];
		$inlinks     = (int) $r['inlinks'];
		$path        = (string) $r['path'];
		$known       = (int) $r['words'] >= 0; // ستون‌های خزنده داخلی؛ فایل Screaming Frog آن‌ها را ندارد (-1)
		$issues      = [];
		$add         = static function ( string $code, string $detail = '', string $url = '', array $items = [] ) use ( &$issues ): void {
			$issues[] = [ 'code' => $code, 'detail' => $detail, 'url' => $url, 'items' => $items ]; // url: آدرس مرتبط (مقصد ریدایرکت)؛ items: مقصدهای لینک (مسیر + توضیح) برای جزء آدرس کامل + کپی
		};

		// نقشه سایت: آدرسی که در نقشه است باید ۲۰۰ و ایندکس‌پذیر باشد (هر وضعیتی، حتی ریدایرکت و ۴۰۴)
		if ( $sitemap && isset( $sitemap[ $path ] ) && ( 200 !== $status || 0 === (int) $r['indexable'] ) ) {
			$add( 'sitemap_bad', 200 !== $status ? sprintf( 'کد %s', $n( $status ) ) : ( 'Canonicalised' === (string) $r['reason'] ? 'canonical به صفحه دیگر' : 'noindex' ) );
		}

		// canonical به آدرسی که سالم نیست
		if ( $known && 200 === $status && '' !== (string) $r['canonical'] && '!ext' !== $r['canonical'] && (string) $r['canonical'] !== $path && isset( $by_path[ (string) $r['canonical'] ] ) ) {
			$target = $by_path[ (string) $r['canonical'] ];
			if ( 200 !== (int) $target['status'] || 'Noindex' === (string) $target['reason'] ) {
				$add( 'canonical_bad', sprintf( 'به %s اشاره می‌کند (%s)', rawurldecode( (string) $r['canonical'] ), 200 !== (int) $target['status'] ? 'کد ' . $n( (int) $target['status'] ) : 'noindex' ) );
			}
		}

		if ( 200 === $status && 1 === (int) $r['indexable'] ) {
			$title = (string) $r['title'];
			$tlen  = (int) $r['title_len'];

			if ( '' === $title ) {
				$add( 'no_title' );
			} elseif ( $tlen < HODIMA_SEO_ADVISOR_CRAWL_TITLE_MIN ) {
				$add( 'title_short', sprintf( '%s نویسه', number_format_i18n( $tlen ) ) );
			} elseif ( $tlen > HODIMA_SEO_ADVISOR_CRAWL_TITLE_MAX ) {
				$add( 'title_long', sprintf( '%s نویسه', number_format_i18n( $tlen ) ) );
			}

			if ( (int) $r['meta_len'] <= 0 ) {
				$add( 'no_meta' );
			}
			if ( 1 === (int) ( $r['no_snippet'] ?? 0 ) ) {
				$add( 'no_snippet' );
			}
			if ( '' === (string) $r['h1'] ) {
				$add( 'no_h1' );
			}
			if ( '' !== $title && ! $paged( $r ) && ( $titles[ mb_strtolower( $title ) ] ?? 0 ) > 1 ) {
				$add( 'dup_title', sprintf( 'در %s صفحه', number_format_i18n( $titles[ mb_strtolower( $title ) ] ) ) );
			}
			if ( '' !== (string) $r['h1'] && ! $paged( $r ) && ( $h1s[ mb_strtolower( (string) $r['h1'] ) ] ?? 0 ) > 1 ) {
				$add( 'dup_h1', sprintf( 'در %s صفحه', number_format_i18n( $h1s[ mb_strtolower( (string) $r['h1'] ) ] ) ) );
			}
			if ( (int) $r['resp_ms'] >= HODIMA_SEO_ADVISOR_CRAWL_SLOW_MS ) {
				$add( 'slow', sprintf( 'پاسخ سرور %s ثانیه', number_format_i18n( (int) $r['resp_ms'] / 1000, 1 ) ) );
			}
			if ( '/' !== (string) $r['path'] && ! $paged( $r ) ) {
				if ( 0 === $inlinks ) {
					$add( 'orphan' );
				} elseif ( $inlinks <= HODIMA_SEO_ADVISOR_CRAWL_LOW_INLINKS ) {
					$text = (int) $r['text_links'];
					$add( 'low_inlinks', sprintf( '%s لینک ورودی', number_format_i18n( $inlinks ) ) . ( $text >= 0 ? sprintf( '؛ %s لینک از متن', number_format_i18n( $text ) ) : '' ) );
				}
			}

			// لینک‌های این صفحه به آدرس خراب، ریدایرکت‌شده یا ایندکس‌نشدنی (هر مقصد: مسیر + توضیح)
			foreach ( [ 'link_broken', 'link_redirect', 'link_noindex' ] as $code ) {
				if ( ! empty( $bad[ $path ][ $code ] ) ) {
					$add( $code, sprintf( '%s لینک', $n( count( $bad[ $path ][ $code ] ) ) ), '', array_slice( $bad[ $path ][ $code ], 0, HODIMA_SEO_ADVISOR_CRAWL_ITEMS ) );
				}
			}

			if ( $known ) {
				if ( (int) $r['h1_count'] > 1 ) {
					$add( 'multi_h1', sprintf( '%s تیتر H1', $n( (int) $r['h1_count'] ) ) );
				}
				if ( (int) $r['img_noalt'] > 0 ) {
					$add( 'img_alt', sprintf( '%s تصویر', $n( (int) $r['img_noalt'] ) ) );
				}
				if ( (int) $r['http_links'] > 0 ) {
					$add( 'http_link', sprintf( '%s لینک', $n( (int) $r['http_links'] ) ) );
				}
				if ( '' === (string) $r['canonical'] ) {
					$add( 'no_canonical' );
				}
				if ( '' !== (string) $r['kind'] && ! taxonomy_exists( (string) $r['kind'] ) && '/' !== $path && ! $paged( $r ) && (int) $r['words'] < HODIMA_SEO_ADVISOR_CRAWL_THIN_WORDS ) {
					$add( 'thin', sprintf( '%s کلمه', $n( (int) $r['words'] ) ) );
				}
				if ( $sitemap && ! isset( $sitemap[ $path ] ) && ! $paged( $r ) ) {
					$add( 'not_in_sitemap' );
				}
			}

			if ( (int) $r['depth'] >= HODIMA_SEO_ADVISOR_CRAWL_DEEP ) {
				$add( 'deep', sprintf( '%s کلیک از صفحه اصلی', $n( (int) $r['depth'] ) ) );
			}
		} elseif ( $impressions > 0 || $inlinks > 0 ) {
			// ریدایرکت فقط وقتی ایراد است که لینک داخلی به آن برود (دستورش اصلاح همان لینک‌هاست). تا SEO 3.0.1 با نمایش
			// گوگل هم می‌آمد و هیچ‌وقت برطرف نمی‌شد: گوگل آدرس کهنه را تا مدت‌ها نشان می‌دهد (تب «آدرس‌های خراب» آن را می‌گوید)
			if ( $status >= 300 && $status < 400 ) {
				if ( $inlinks > 0 ) {
					$add( 'redirect', '' !== (string) $r['redirect_to'] ? 'به این آدرس می‌رود:' : '', (string) $r['redirect_to'] ); // آدرس جدا با کپی (وسط جمله به‌هم می‌ریخت)
				}
				$next = $by_path[ (string) $r['redirect_to'] ] ?? null;
				if ( null !== $next && (int) $next['status'] >= 300 && (int) $next['status'] < 400 ) {
					$add( 'redirect_chain', 'به آدرسی می‌رود که خودش ریدایرکت است:', (string) $r['redirect_to'] );
				}
			} elseif ( $status >= 400 ) {
				$add( 'broken', sprintf( 'کد %s', number_format_i18n( $status ) ) );
			} elseif ( 200 === $status && 0 === (int) $r['indexable'] ) {
				$add( 'noindex', (string) $r['reason'] );
			}
		}

		if ( $issues ) {
			$out[] = [
				'path'        => (string) $r['path'],
				'impressions' => $impressions,
				'position'    => (float) $r['avg_position'],
				'inlinks'     => $inlinks,
				'text_links'  => (int) $r['text_links'],
				'issues'      => $issues,
			];
		}
	}

	usort( $out, static fn( array $a, array $b ): int => [ $b['impressions'], $a['path'] ] <=> [ $a['impressions'], $b['path'] ] );

	return $out;
}

/**
 * آمار و شمار هر گروه برای چیپ‌ها.
 *
 * @param list<array<string, mixed>> $issues خروجی hodima_seo_advisor_crawl_issues()
 * @return array{crawled: int, with_issues: int, big: int, low_links: int, groups: array<string, int>}
 */
function hodima_seo_advisor_crawl_summary( array $issues ): array {

	global $wpdb;

	$groups = array_fill_keys( array_keys( HODIMA_SEO_ADVISOR_CRAWL_GROUPS ), 0 );
	$big    = 0;

	foreach ( $issues as $row ) {
		$codes = array_column( $row['issues'], 'code' );
		foreach ( HODIMA_SEO_ADVISOR_CRAWL_GROUPS as $group => $members ) {
			if ( array_intersect( $codes, $members ) ) {
				++$groups[ $group ];
			}
		}
		if ( $row['impressions'] >= HODIMA_SEO_ADVISOR_CRAWL_BIG_IMPRESSIONS ) {
			++$big;
		}
	}

	return [
		'crawled'     => (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', hodima_seo_advisor_table( 'crawl_pages' ) ) ), // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		'with_issues' => count( $issues ),
		'big'         => $big,
		'low_links'   => $groups['links'] + $groups['orphan'],
		'groups'      => $groups,
	];
}
