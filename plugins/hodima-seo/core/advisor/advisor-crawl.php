<?php
/**
 * ماژول «دستار سئو» — سلامت خزش: نقشه خزش، ایرادها و آمار
 * Path: core/advisor/advisor-crawl.php
 *
 * داده را خزنده داخلی (advisor-crawler.php) در جدول‌های crawl_pages و crawl_links می‌نویسد؛ این فایل همان جدول‌ها را
 * می‌خواند و ایرادها، نقشه خزش و آمار را می‌سازد. فقط می‌خواند و پیشنهاد می‌دهد؛ چیزی را در سایت عوض نمی‌کند.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** وضعیت آخرین خواندن (گزینه؛ autoload خاموش). */
const HODIMA_SEO_ADVISOR_CRAWL_OPTION = 'hodima_advisor_crawl';

/** عمق ذخیره‌شده برای صفحه‌ای که از صفحه اصلی با هیچ مسیر لینکی نمی‌رسیم (SEO 3.3.5؛ ستون عمق بدون علامت است، پس -1 نشد). */
const HODIMA_SEO_ADVISOR_CRAWL_UNREACHABLE = 65535;

/** صفحه با این تعداد لینک متنی (و این تعداد با عبارت کلمه) «لینک داخلی کافی» دارد؛ دستور تمرکز را روی عنوان و متن می‌گذارد. */
const HODIMA_SEO_ADVISOR_CRAWL_WELL_LINKED      = 15;
const HODIMA_SEO_ADVISOR_CRAWL_WELL_LINKED_TERM = 5;

/*
 * طول عنوان و توضیح متا با «پیکسل» سنجیده می‌شود، همان خط‌کش سئوباکس (seobox-metrics.php؛ SEO 3.5.4).
 * تا ۳.۵.۳ نویسه بود (عنوان ۳۰ تا ۶۵، متا ۷۰ تا ۱۷۰) و ادمین در سئوباکس عدد پیکسلی دیگری می‌دید.
 */

/** پاسخ سرور کندتر از این (میلی‌ثانیه) «کند» است. */
const HODIMA_SEO_ADVISOR_CRAWL_SLOW_MS = 2000;

/** صفحه با این تعداد لینک ورودی یکتا یا کمتر «لینک ورودی کم» دارد. */
const HODIMA_SEO_ADVISOR_CRAWL_LOW_INLINKS = 2;

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

/**
 * دستورهای هدر X-Robots-Tag که برای گوگل معتبرند: دستور بی‌نام یا «googlebot: …» (SEO 3.9.3).
 * فرمت گوگل: «[نام ربات:] دستور، دستور، …»؛ اسم ربات تا اسم بعدی روی دستورهای پشت سرش اثر دارد (مثلا
 * «bingbot: noindex, googlebot: nosnippet»). باگ قبلی: کل هدر خام به متای robots چسبانده می‌شد و «bingbot: noindex»
 * صفحه سالم را برای گوگل «ایندکس‌نشدنی» می‌کرد. دستورهای دارای دونقطه (unavailable_after و max-*) نام ربات نیستند.
 *
 * @return string دستورها با ویرگول جدا شده؛ خالی = برای گوگل چیزی نیست
 */
function hodima_seo_advisor_robots_for_google( string $header ): string {

	$agent = '';
	$out   = [];

	foreach ( explode( ',', strtolower( $header ) ) as $segment ) {
		$segment = trim( $segment );

		if ( 1 === preg_match( '/^([a-z][a-z0-9_\-]*)\s*:\s*(.*)$/', $segment, $m ) && ! in_array( $m[1], [ 'unavailable_after', 'max-snippet', 'max-image-preview', 'max-video-preview' ], true ) ) {
			$agent   = $m[1];
			$segment = trim( $m[2] );
		}

		if ( '' !== $segment && ( '' === $agent || 'googlebot' === $agent ) ) {
			$out[] = $segment;
		}
	}

	return implode( ', ', $out );
}

/* ── نقشه خزش و صفحه‌های حل‌شده (برای رقابت و قاعده نرخ کلیک) ── */

/** ستون‌های جدول خزش که نقشه و ردیف تک از آن می‌خوانند. */
const HODIMA_SEO_ADVISOR_CRAWL_COLUMNS = 'path, status, indexable, reason, title_len, meta_len, title_px, meta_px, inlinks, text_links, depth, score, title, h1, h2, words';

/**
 * ردیف خام جدول خزش ← آرایه با نوع‌های درست.
 *
 * @param array<string, mixed> $r
 * @return array{status: int, indexable: bool, reason: string, title_len: int, meta_len: int, title_px: int, meta_px: int, inlinks: int, text_links: int, depth: int, score: float, title: string, h1: string, h2: string, words: int}
 */
function hodima_seo_advisor_crawl_map_row( array $r ): array {
	return [ 'status' => (int) $r['status'], 'indexable' => 1 === (int) $r['indexable'], 'reason' => (string) $r['reason'], 'title_len' => (int) $r['title_len'], 'meta_len' => (int) $r['meta_len'], 'title_px' => (int) $r['title_px'], 'meta_px' => (int) $r['meta_px'], 'inlinks' => (int) $r['inlinks'], 'text_links' => (int) $r['text_links'], 'depth' => (int) $r['depth'], 'score' => (float) $r['score'], 'title' => (string) $r['title'], 'h1' => (string) $r['h1'], 'h2' => (string) $r['h2'], 'words' => (int) ( $r['words'] ?? -1 ) ]; // words: گام «متن کم است» پیشنهادها (SEO 3.8.3)
}

/**
 * همه صفحه‌های خزش‌شده، آدرس ← وضعیت. یک بار در هر درخواست خوانده می‌شود (تحلیل صدها آدرس را صدا می‌زند).
 *
 * `$only`: فقط همین آدرس (کادر ویرایش یک صفحه): اگر نقشه کامل هنوز خوانده نشده، فقط یک ردیف خوانده می‌شود
 * (تا SEO 2.9.8 باز کردن کادر ویرایش هر صفحه کل جدول خزش را در حافظه می‌آورد).
 *
 * @return array<string, array{status: int, indexable: bool, reason: string, title_len: int, meta_len: int, title_px: int, meta_px: int, inlinks: int, text_links: int, depth: int, score: float, title: string, h1: string, h2: string, words: int}>
 */
function hodima_seo_advisor_crawl_map( bool $reset = false, ?string $only = null ): array {

	global $wpdb;
	static $map    = null;
	static $single = [];

	if ( $reset ) {
		$map    = null;
		$single = [];
		$memo   = &hodima_seo_advisor_crawl_issues_cache();
		$memo   = null; // ایرادها از همین جدول ساخته می‌شوند (SEO 3.4.3)
		$groups = &hodima_seo_advisor_image_groups_cache();
		$groups = null; // تصویرهای مشترک هم (SEO 3.4.9)
		hodima_seo_advisor_resolved_paths( true );
		hodima_seo_advisor_crawl_anchors_between( '', '', true ); // لینک‌های بین صفحه‌ها هم از همین خزش (SEO 3.8.0)
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
 * @return array{status: int, indexable: bool, reason: string, title_len: int, meta_len: int, title_px: int, meta_px: int, inlinks: int, text_links: int, depth: int, score: float, title: string, h1: string, h2: string, words: int}|null
 */
function hodima_seo_advisor_crawl_row( string $path ): ?array {
	return hodima_seo_advisor_crawl_map( false, $path )[ $path ] ?? null;
}

/**
 * متن لینک‌های صفحه $source به صفحه $dest طبق آخرین خزش (SEO 3.8.0؛ گام‌های «رقابت داخلی»: لینک به صفحه اصلی هست؟ با همین کلمه؟).
 * فهرست خالی = لینک ندارد؛ null = صفحه مبدأ خزیده نشده (معلوم نیست). لینک‌های هر مبدأ یک بار در هر درخواست خوانده می‌شود.
 *
 * @return list<string>|null
 */
function hodima_seo_advisor_crawl_anchors_between( string $source, string $dest, bool $reset = false ): ?array {

	global $wpdb;
	static $cache = [];

	if ( $reset ) {
		$cache = [];
		return null;
	}

	if ( '' === $source || '' === $dest || null === hodima_seo_advisor_crawl_row( $source ) ) {
		return null;
	}

	if ( ! isset( $cache[ $source ] ) ) {
		$cache[ $source ] = [];
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT dest_path, anchor FROM %i WHERE source_path = %s', hodima_seo_advisor_table( 'crawl_links' ), $source ), ARRAY_A ) as $link ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$cache[ $source ][ (string) $link['dest_path'] ][] = trim( (string) $link['anchor'] );
		}
	}

	return array_values( array_unique( $cache[ $source ][ $dest ] ?? [] ) );
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
 * وضعیت عرض پیکسلی عنوان یا توضیح متا: short، long، ok، یا null = هنوز اندازه‌گیری نشده (ردیف خزش قدیمی؛ تا خزش بعدی ایراد ساخته نمی‌شود).
 * طول ۰ (بدون عنوان/متا) را فراخوان جدا مدیریت می‌کند.
 */
function hodima_seo_advisor_crawl_px_state( int $length, int $px, string $field ): ?string {

	if ( $length > 0 && $px <= 0 ) {
		return null;
	}

	$limit = hodima_seo_px_limit( $field );

	return $px < $limit['min'] ? 'short' : ( $px > $limit['max'] ? 'long' : 'ok' );
}

/**
 * طول عنوان و متا یک صفحه طبق خزش (پیکسل): ok (هر دو سالم)، یا فهرست ایرادها، یا null (در خزش نیست/صفحه سالم نیست/هنوز پیکسلش اندازه‌گیری نشده).
 *
 * @return array{healthy: bool, title_px: int, meta_px: int, problems: list<string>}|null
 */
function hodima_seo_advisor_crawl_meta_health( string $path ): ?array {

	$r = hodima_seo_advisor_crawl_row( $path );

	if ( null === $r || 200 !== $r['status'] || ! $r['indexable'] ) {
		return null;
	}

	$title = hodima_seo_advisor_crawl_px_state( $r['title_len'], $r['title_px'], 'title' );
	$meta  = hodima_seo_advisor_crawl_px_state( $r['meta_len'], $r['meta_px'], 'description' );

	if ( null === $title || null === $meta ) {
		return null; // ردیف قدیمی: بدون خط‌کش پیکسلی نمی‌شود سالم/ناسالم گفت
	}

	$problems = [];

	if ( 'short' === $title ) {
		$problems[] = 'عنوان کوتاه است';
	} elseif ( 'long' === $title ) {
		$problems[] = 'عنوان بلند است';
	}
	if ( $r['meta_len'] <= 0 ) {
		$problems[] = 'توضیح متا ندارد';
	} elseif ( 'short' === $meta ) {
		$problems[] = 'توضیح متا کوتاه است';
	} elseif ( 'long' === $meta ) {
		$problems[] = 'توضیح متا بلند است';
	}

	return [ 'healthy' => [] === $problems, 'title_px' => $r['title_px'], 'meta_px' => $r['meta_px'], 'problems' => $problems ];
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

	if ( $links['depth'] >= 4 && $links['depth'] < HODIMA_SEO_ADVISOR_CRAWL_UNREACHABLE ) {
		$text .= sprintf( ' عمق خزش %s است؛ آن را از منو یا صفحه اصلی نزدیک‌تر کنید.', $num( $links['depth'] ) );
	}

	return $text;
}

/**
 * همان شواهد لینک hodima_seo_advisor_links_evidence، جدا به «چه دیدیم» و «گام» برای راهنمای پنج‌بخشی «کارها» (SEO 3.7.0).
 * متن کامل قدیمی (کادر ویرایش) عوض نشد.
 *
 * @param array{total: int, text: int, score: float, depth: int, anchors: list<array{anchor: string, count: int}>}|null $links
 * @return array{seen: list<string>, steps: list<string>, well: bool} well = لینک داخلی کافی است (گام لینک‌دهی لازم نیست)
 */
function hodima_seo_advisor_links_evidence_parts( ?array $links, string $term ): array {

	if ( null === $links ) {
		return [ 'seen' => [], 'steps' => [], 'well' => false ];
	}

	$well       = false;
	$num        = static fn( int|float $v, int $d = 0 ): string => number_format_i18n( $v, $d );
	$term_links = hodima_seo_advisor_crawl_term_links( $links['anchors'], $term );
	$seen       = [ sprintf( ' طبق خزش این صفحه %1$s لینک ورودی یکتا دارد (%2$s از متن).', $num( $links['total'] ), $num( $links['text'] ) ) ];
	$steps      = [];

	if ( 0 === $term_links ) {
		$seen[] = sprintf( ' هیچ لینک متنی با عبارت «%s» به آن نیست.', $term );
		// متن لینک‌های فعلی را نام می‌برد تا مدیر بداند کدام را عوض کند (SEO 3.8.3؛ قبلا «لینک‌های موجود» بی‌نام)
		$now     = array_slice( array_values( array_filter( array_map( static fn( array $a ): string => (string) $a['anchor'], $links['anchors'] ) ) ), 0, 2 );
		$steps[] = $now
			? sprintf( 'این صفحه %1$s لینک ورودی از متن دارد ولی متن هیچ‌کدام «%2$s» نیست (مثلا «%3$s»)؛ متن همین لینک‌ها را «%2$s» کنید و از ۲ تا ۳ صفحه مرتبط لینک تازه با همین متن بدهید.', $num( $links['text'] ), $term, implode( '»، «', $now ) )
			: sprintf( 'لینک‌های موجود به این صفحه را با متن «%1$s» بنویسید و از ۲ تا ۳ صفحه مرتبط لینک تازه با همین متن بدهید.', $term );
	} else {
		$seen[] = sprintf( ' %1$s لینک با متن «%2$s» دارد.', $num( $term_links ), $term );
		if ( $links['text'] <= 3 ) {
			$steps[] = sprintf( 'لینک متنی کم است؛ از ۳ تا ۵ صفحه مرتبط با متن «%s» به این صفحه لینک بدهید.', $term );
		} elseif ( $links['text'] >= HODIMA_SEO_ADVISOR_CRAWL_WELL_LINKED && $term_links >= HODIMA_SEO_ADVISOR_CRAWL_WELL_LINKED_TERM ) {
			$seen[] = ' لینک داخلی این صفحه کم نیست؛ لینک بیشتر اولویت نیست.';
			$well   = true;
		}
	}

	if ( $links['depth'] >= 4 && $links['depth'] < HODIMA_SEO_ADVISOR_CRAWL_UNREACHABLE ) {
		$seen[]  = sprintf( ' از صفحه اصلی %s کلیک فاصله دارد.', $num( $links['depth'] ) );
		$steps[] = 'از منو یا صفحه اصلی به این صفحه (یا دسته‌اش) لینک بدهید تا نزدیک‌تر شود.';
	}

	return [ 'seen' => $seen, 'steps' => $steps, 'well' => $well ];
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
	'dup'      => [ 'dup_title', 'dup_h1', 'dup_meta', 'near_dup' ],
	'slow'     => [ 'slow' ],
	'redirect' => [ 'redirect', 'noindex', 'canonicalised', 'broken', 'server_error' ],
	'linkfix'  => [ 'link_broken', 'link_redirect', 'link_noindex', 'link_no_text', 'link_js', 'redirect_chain', 'redirect_loop' ],
	'content'  => [ 'img_alt', 'img_empty', 'thin', 'soft_404' ], // img_empty تا SEO ۳.۸.۰ بی‌گروه بود و در فیلتر چیپ‌های «کارها» دیده نمی‌شد
	'tech'     => [ 'site_hidden', 'ld_invalid', 'faq_schema', 'product_schema', 'canonical_bad', 'canonical_external', 'canonical_variant', 'canonical_in_body', 'no_canonical', 'http_link', 'sitemap_bad', 'not_in_sitemap', 'deep' ],
	'links'    => [ 'low_inlinks' ],
	'orphan'   => [ 'orphan', 'island' ],
];

/**
 * برچسب، رنگ نشان و دستور هر ایراد.
 *
 * @return array{label: string, level: string, fix: string}
 */
function hodima_seo_advisor_crawl_issue_info( string $code ): array {

	$record = hodima_seo_advisor_issue_catalog()[ $code ] ?? null;

	return null === $record
		? [ 'label' => $code, 'level' => 'info', 'fix' => '' ]
		: [ 'label' => $record['label'], 'level' => $record['level'], 'fix' => hodima_seo_advisor_issue_fix( $code ) ];
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
		} elseif ( 'content' === (string) $r['link_position'] && ! hodima_seo_advisor_is_system_path( $dest ) ) { // لینک به حساب/سبد/پرداخت (عمدا noindex) ایراد نیست (SEO 3.6.2)
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
 * @return list<array{path: string, impressions: int, position: float, inlinks: int, text_links: int, issues: list<array{code: string, detail: string, url: string, items: list<array{path: string, note: string}>, images?: list<string>, total?: int}>}>
 */
function hodima_seo_advisor_crawl_issues(): array {

	global $wpdb;

	// یک بار در هر درخواست (SEO 3.4.3): «کارها»، «سلامت سایت»، امتیاز و سنجش کارها هر کدام کل جدول خزش را از نو می‌خواندند
	// (پایان دور خزش دو بار پشت‌سرهم). با خزش یا دریافت تازه (hodima_seo_advisor_crawl_map(true)) کش دور ریخته می‌شود.
	$memo = &hodima_seo_advisor_crawl_issues_cache();

	if ( null !== $memo ) {
		return $memo;
	}

	$rows = (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			'SELECT c.*, COALESCE(p.impressions, 0) AS impressions, COALESCE(p.avg_position, 0) AS avg_position, COALESCE(p.kind, %s) AS kind FROM %i c LEFT JOIN %i p ON p.path = c.path',
			'',
			hodima_seo_advisor_table( 'crawl_pages' ),
			hodima_seo_advisor_table( 'pages' )
		),
		ARRAY_A
	);

	// قاعده‌های ایراد هر کدام یک تابع‌اند و ترتیب و شرط اجرایشان یک‌جا ثبت است (advisor-checks.php؛ SEO 3.6.1). تکراری‌ها فقط
	// بین صفحه‌های سالم و ایندکس‌پذیر شمرده می‌شوند و صفحه‌بندی آرشیو (/page/2/) کنار می‌ماند (SEO 3.0.5)
	$ctx = hodima_seo_advisor_checks_context( $rows );
	$out = [];

	foreach ( $rows as $r ) {

		$issues = hodima_seo_advisor_checks_run( $r, $ctx );

		if ( $issues ) {
			$out[] = [
				'path'        => (string) $r['path'],
				'impressions' => (int) $r['impressions'],
				'position'    => (float) $r['avg_position'],
				'inlinks'     => (int) $r['inlinks'],
				'text_links'  => (int) $r['text_links'],
				'issues'      => $issues,
			];
		}
	}

	usort( $out, static fn( array $a, array $b ): int => [ $b['impressions'], $a['path'] ] <=> [ $a['impressions'], $b['path'] ] );

	$memo = $out;

	return $out;
}

/** کش درخواست جاری ایرادهای خزش (مرجع تا reset بتواند دورش بریزد).
 *
 * @return list<array<string, mixed>>|null
 */
function &hodima_seo_advisor_crawl_issues_cache(): ?array {
	static $cache = null;
	return $cache;
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
