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
 * وارونه لینک‌های به ریدایرکت: آدرس ریدایرکت‌شده ← صفحه‌هایی که هنوز به آن لینک داده‌اند (با متن لینک).
 *
 * @param array<string, array<string, list<array{path: string, note: string}>>> $bad خروجی hodima_seo_advisor_crawl_bad_links()
 * @return array<string, list<array{path: string, note: string}>>
 */
function hodima_seo_advisor_redirect_sources( array $bad ): array {

	$out = [];
	foreach ( $bad as $source => $codes ) {
		foreach ( $codes['link_redirect'] ?? [] as $target ) {
			$note                                 = (string) preg_replace( '/^.*؛ /u', '', (string) $target['note'] ); // فقط «متن لینک «…»»
			$out[ (string) $target['path'] ][] = [ 'path' => (string) $source, 'note' => $note ];
		}
	}

	return $out;
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
	return hodima_seo_advisor_dup_key( $title ) . ( mb_strlen( $title ) >= HODIMA_SEO_ADVISOR_KEY_LENGTH ? '|' . $length : '' );
}

/**
 * کلید مقایسه برای «تکراری»: ی/ک عربی، نیم‌فاصله، اعراب، ارقام فارسی/عربی/لاتین و حروف بزرگ یکسان می‌شوند (SEO 3.6.3). تا ۳.۶.۲
 * فقط حروف کوچک می‌شد و دو عنوان که فقط در «ي» عربی یا «۱» فارسی/«1» لاتین فرق داشتند تکراری شمرده نمی‌شدند.
 */
function hodima_seo_advisor_dup_key( string $text ): string {
	return hodima_seo_advisor_normalize( $text );
}

/**
 * داده مشترک همه ردیف‌ها که فقط با دیدن کل خزش ساخته می‌شود (تکراری‌ها، عنوان مشابه، نقشه سایت، لینک‌های خراب).
 * تکراری‌ها فقط بین صفحه‌های سالم و ایندکس‌پذیر شمرده می‌شوند و صفحه‌بندی کنار می‌ماند.
 *
 * @param list<array<string, mixed>> $rows ردیف‌های جدول خزش (با ستون‌های impressions، avg_position، kind)
 * @return array{titles: array<string, int>, h1s: array<string, int>, metas: array<string, int>, dups: array{title: array<string, list<string>>, h1: array<string, list<string>>, meta: array<string, list<string>>}, words: array<string, list<string>>, hidden: bool, similar: array<string, array<string, list<string>>>, by_path: array<string, array<string, mixed>>, sitemap: array<string, true>, bad: array<string, array<string, list<array{path: string, note: string}>>>, kinds: array<string, string>, taxonomies: array<string, true>, redirect_from: array<string, list<array{path: string, note: string}>>}
 */
function hodima_seo_advisor_checks_context( array $rows ): array {

	$titles = [];
	$h1s    = [];
	$metas  = [];
	$dups   = [ 'title' => [], 'h1' => [], 'meta' => [] ]; // کلید ← آدرس‌ها، برای «کدام صفحه‌ها؟» زیر ایراد تکراری (SEO 3.7.3)
	$words  = []; // آدرس ← کلمه‌های معنی‌دار عنوان، برای «از کدام صفحه لینک بدهم؟» زیر «بدون لینک ورودی»
	$site_w = hodima_seo_advisor_similar_tokens( (string) get_bloginfo( 'name' ) );
	foreach ( $rows as $r ) {
		if ( 200 === (int) $r['status'] && 1 === (int) $r['indexable'] && ! hodima_seo_advisor_crawl_is_paged( $r ) ) {
			$mh = (string) ( $r['meta_hash'] ?? '' );
			if ( '' !== $mh ) {
				$metas[ $mh ]          = ( $metas[ $mh ] ?? 0 ) + 1;
				$dups['meta'][ $mh ][] = (string) $r['path'];
			}
			$tk                           = hodima_seo_advisor_title_key( (string) $r['title'], (int) $r['title_len'] );
			$titles[ $tk ]                = ( $titles[ $tk ] ?? 0 ) + 1;
			$h1k                          = hodima_seo_advisor_dup_key( (string) $r['h1'] );
			$h1s[ $h1k ]                  = ( $h1s[ $h1k ] ?? 0 ) + 1;
			$dups['title'][ $tk ][]       = (string) $r['path'];
			$dups['h1'][ $h1k ][]         = (string) $r['path'];
			$words[ (string) $r['path'] ] = hodima_seo_advisor_similar_tokens( (string) $r['title'] . ' ' . (string) $r['h1'], $site_w );
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

	$bad     = hodima_seo_advisor_crawl_bad_links();
	$by_path = [];
	$kinds   = []; // نوع صفحه‌های کم‌متنی که جدول نمایش‌ها نوعشان را ندارد (برای «متن کم»)
	foreach ( $rows as $r ) {
		$by_path[ (string) $r['path'] ] = $r;
		$count                          = (int) ( $r['words'] ?? -1 ); // نه $words: همان نام بالاتر کلمه‌های عنوان است
		if ( '' === (string) ( $r['kind'] ?? '' ) && $count >= 0 && $count < HODIMA_SEO_ADVISOR_CRAWL_THIN_WORDS && '/' !== (string) $r['path'] && ! hodima_seo_advisor_crawl_is_paged( $r ) ) {
			$kinds[ (string) $r['path'] ] = hodima_seo_advisor_classify( (string) $r['path'] )['kind'];
		}
	}

	return [
		'titles'  => $titles,
		'h1s'     => $h1s,
		'metas'   => $metas,
		'dups'    => $dups,
		'words'   => $words,
		'similar' => $similar,
		'by_path' => $by_path,
		'sitemap' => hodima_seo_advisor_sitemap_paths(),
		'bad'     => $bad,
		'site'    => [ strtolower( (string) wp_parse_url( home_url(), PHP_URL_SCHEME ) ), strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) ], // [پروتکل، دامنه] برای canonical با شکل دیگر (SEO 3.9.3)
		'hidden'  => '0' === (string) get_option( 'blog_public', '1' ), // «تنظیمات ← خواندن ← از موتورهای جستجو بخواه ایندکس نکند» (SEO 3.6.7)
		// تابع‌های ایراد خالص‌اند (بدون دیتابیس/وردپرس)؛ تا SEO ۳.۸.۰ check_thin خودش classify و taxonomy_exists را صدا می‌زد
		'kinds'      => $kinds,
		'redirect_from' => hodima_seo_advisor_redirect_sources( $bad ),
		'taxonomies' => array_fill_keys( get_taxonomies(), true ),
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

	// کدام آدرس باید جایش در نقشه بیاید (SEO 3.8.5): مقصد نهایی ریدایرکت یا صفحه canonical؛ تا ۳.۸.۴ فقط «کد ۳۰۱» یا «canonical به صفحه دیگر»
	$url = '';
	if ( $status >= 300 && $status < 400 ) {
		$trace  = hodima_seo_advisor_redirect_trace( (string) $r['path'], $ctx['by_path'] );
		$url    = $trace['loop'] ? '' : ( '' !== $trace['final'] ? $trace['final'] : (string) $r['redirect_to'] );
		$detail = sprintf( 'کد %s', number_format_i18n( $status ) ) . ( '' !== $url ? '؛ ریدایرکت به آدرس زیر' : '' );
	} elseif ( 200 !== $status ) {
		$detail = sprintf( 'کد %s', number_format_i18n( $status ) );
	} elseif ( 'Canonicalised' === (string) $r['reason'] ) {
		$canonical = (string) $r['canonical'];
		$url       = '' !== $canonical && ! str_starts_with( $canonical, '!' ) ? $canonical : '';
		$detail    = 'canonical به صفحه دیگر' . ( '' !== $url ? ' (زیر)' : '' );
	} else {
		$detail = 'noindex';
	}

	return [ hodima_seo_advisor_issue( 'sitemap_bad', $detail, $url ) ];
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

	if ( (int) $r['words'] < 0 || 200 !== (int) $r['status'] || '' === $canonical || '!ext' === $canonical || '!body' === $canonical || $canonical === (string) $r['path'] || ! isset( $ctx['by_path'][ $canonical ] ) ) {
		return [];
	}

	$target = $ctx['by_path'][ $canonical ];

	if ( 200 === (int) $target['status'] && 'Noindex' !== (string) $target['reason'] ) {
		return [];
	}

	// صفحه‌های سیستمی (حساب، سبد، پرداخت) عمدا noindex‌اند و صفحه‌های زیرشان (فراموشی رمز) canonical به صفحه اصلی حساب دارند (SEO 3.6.6)
	if ( hodima_seo_advisor_is_system_path( (string) $r['path'] ) || hodima_seo_advisor_is_system_path( $canonical ) ) {
		return [];
	}

	// canonical به آدرسی که ریدایرکت می‌شود (SEO 3.8.5): مقصد نهایی سالم همان آدرس درست است و با دکمه کپی می‌آید
	$status = (int) $target['status'];
	if ( $status >= 300 && $status < 400 ) {
		$trace = hodima_seo_advisor_redirect_trace( $canonical, $ctx['by_path'] );
		$final = $trace['loop'] ? '' : $trace['final'];
		$ok    = '' !== $final && 200 === (int) ( $ctx['by_path'][ $final ]['status'] ?? 0 ) && 'Noindex' !== (string) ( $ctx['by_path'][ $final ]['reason'] ?? '' );
		return [ hodima_seo_advisor_issue( 'canonical_bad', sprintf( 'به %1$s اشاره می‌کند که خودش ریدایرکت می‌شود (کد %2$s)%3$s', rawurldecode( $canonical ), number_format_i18n( $status ), $ok ? '؛ آدرس درست احتمالا مقصد آن است (زیر)' : '' ), $ok ? $final : '' ) ];
	}

	return [ hodima_seo_advisor_issue( 'canonical_bad', sprintf( 'به %s اشاره می‌کند (%s)', rawurldecode( $canonical ), 200 !== $status ? 'کد ' . number_format_i18n( $status ) : 'noindex' ) ) ];
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
 * توضیح متای خالی؛ برای صفحه‌های /page/N/ توضیح جدا لازم نیست (SEO 3.3.5). گوگل نبود متا را مشکل نمی‌داند (خلاصه را از متن صفحه
 * می‌سازد) و فقط توصیه می‌کند «دست‌کم برای URLهای مهم، مثل صفحه اصلی و پربازدیدها» نوشته شود (Search Central، «snippet»)؛ پس برای
 * بقیه صفحه‌ها سطح همین ایراد «اطلاع» است (SEO 3.6.2؛ کلید level هر ایراد سطح کاتالوگ را برای همان صفحه عوض می‌کند).
 *
 * @param array<string, mixed> $r   ردیف جدول خزش (+ impressions، kind)
 * @param array<string, mixed> $ctx زمینه hodima_seo_advisor_checks_context()
 * @return list<array<string, mixed>>
 */
function hodima_seo_advisor_check_no_meta( array $r, array $ctx ): array {

	if ( (int) $r['meta_len'] > 0 || hodima_seo_advisor_crawl_is_paged( $r ) ) {
		return [];
	}

	$popular = '/' === (string) $r['path'] || (int) ( $r['impressions'] ?? 0 ) >= HODIMA_SEO_ADVISOR_CRAWL_BIG_IMPRESSIONS;

	return [ hodima_seo_advisor_issue( 'no_meta', '', '', [], $popular ? [] : [ 'level' => 'info' ] ) ];
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

	$key   = hodima_seo_advisor_title_key( $title, (int) $r['title_len'] );
	$count = $ctx['titles'][ $key ] ?? 0;

	return $count > 1 ? [ hodima_seo_advisor_issue( 'dup_title', sprintf( 'در %s صفحه', number_format_i18n( $count ) ), '', hodima_seo_advisor_dup_others( $ctx, 'title', $key, $r ) ) ] : [];
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

	$key   = hodima_seo_advisor_dup_key( $h1 );
	$count = $ctx['h1s'][ $key ] ?? 0;

	return $count > 1 ? [ hodima_seo_advisor_issue( 'dup_h1', sprintf( 'در %s صفحه', number_format_i18n( $count ) ), '', hodima_seo_advisor_dup_others( $ctx, 'h1', $key, $r ) ) ] : [];
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
		return [ hodima_seo_advisor_issue( 'orphan', '', '', hodima_seo_advisor_link_sources_suggest( $r, $ctx ) ) ];
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
	if ( (int) $r['http_links'] <= 0 ) {
		return [];
	}

	// کدام لینک‌ها (SEO 3.7.3، ستون http_list؛ ردیف خزش قدیمی‌تر تا خزش بعدی فهرست ندارد): آدرس نوشته‌شده + متن لینک
	$list  = json_decode( (string) ( $r['http_list'] ?? '' ), true );
	$items = [];
	foreach ( is_array( $list ) ? $list : [] as $link ) {
		if ( is_array( $link ) && '' !== (string) ( $link[0] ?? '' ) ) {
			$items[] = [ 'path' => (string) $link[0], 'note' => '' !== (string) ( $link[1] ?? '' ) ? sprintf( 'متن لینک «%s»', (string) $link[1] ) : '' ];
		}
	}

	return [ hodima_seo_advisor_issue( 'http_link', sprintf( '%s لینک', number_format_i18n( (int) $r['http_links'] ) ), '', $items ) ];
}

/**
 * فهرست JSON ذخیره‌شده خزنده [آدرس نوشته‌شده، متن] ← آیتم‌های «کدام لینک‌ها؟».
 *
 * @return list<array{path: string, note: string}>
 */
function hodima_seo_advisor_check_list_items( string $json ): array {

	$list  = json_decode( $json, true );
	$items = [];
	foreach ( is_array( $list ) ? $list : [] as $link ) {
		if ( is_array( $link ) && '' !== (string) ( $link[0] ?? '' ) ) {
			$items[] = [ 'path' => (string) $link[0], 'note' => '' !== (string) ( $link[1] ?? '' ) ? sprintf( 'متن «%s»', (string) $link[1] ) : '' ];
		}
	}

	return $items;
}

/**
 * لینک بی‌متن در متن صفحه (SEO 3.8.4): نه نوشته، نه alt تصویر؛ مقصدی که لینک متن‌دار دیگری در همین صفحه دارد حساب نمی‌شود (خزنده).
 * ردیف خزش قدیمی (-1) تا خزش بعدی سنجیده نمی‌شود.
 *
 * @param array<string, mixed> $r   ردیف جدول خزش (+ impressions، kind)
 * @param array<string, mixed> $ctx زمینه hodima_seo_advisor_checks_context()
 * @return list<array<string, mixed>>
 */
function hodima_seo_advisor_check_link_no_text( array $r, array $ctx ): array {
	return (int) ( $r['notext_links'] ?? -1 ) > 0 ? [ hodima_seo_advisor_issue( 'link_no_text', sprintf( '%s لینک', number_format_i18n( (int) $r['notext_links'] ) ), '', hodima_seo_advisor_check_list_items( (string) ( $r['notext_list'] ?? '' ) ) ) ] : [];
}

/**
 * لینک جاوااسکریپتی به صفحه سایت (SEO 3.8.4): onclick/‏javascript:/‏data-href بدون <a href>؛ گوگل دنبالش نمی‌کند.
 *
 * @param array<string, mixed> $r   ردیف جدول خزش (+ impressions، kind)
 * @param array<string, mixed> $ctx زمینه hodima_seo_advisor_checks_context()
 * @return list<array<string, mixed>>
 */
function hodima_seo_advisor_check_link_js( array $r, array $ctx ): array {
	return (int) ( $r['js_links'] ?? -1 ) > 0 ? [ hodima_seo_advisor_issue( 'link_js', sprintf( '%s لینک', number_format_i18n( (int) $r['js_links'] ) ), '', hodima_seo_advisor_check_list_items( (string) ( $r['js_list'] ?? '' ) ) ) ] : [];
}

/**
 * Soft 404 (SEO 3.8.6): صفحه سالم (۲۰۰) که عنوان/H1 «یافت نشد» دارد یا پیام آرشیو خالی نشان می‌دهد. صفحه‌های سیستمی (جستجو، حساب) کنار.
 *
 * @param array<string, mixed> $r   ردیف جدول خزش (+ impressions، kind)
 * @param array<string, mixed> $ctx زمینه hodima_seo_advisor_checks_context()
 * @return list<array<string, mixed>>
 */
function hodima_seo_advisor_check_soft_404( array $r, array $ctx ): array {

	$soft = (string) ( $r['soft404'] ?? '' );

	if ( '' === $soft || hodima_seo_advisor_is_system_path( (string) $r['path'] ) ) {
		return [];
	}

	[ $kind, $sign ] = array_pad( explode( '|', $soft, 2 ), 2, '' );

	return [ hodima_seo_advisor_issue( 'soft_404', 'empty' === $kind ? 'پیام «چیزی یافت نشد» نشان می‌دهد (دسته یا آرشیو خالی)' : sprintf( 'عنوان یا تیتر اصلی‌اش «%s» دارد', $sign ) ) ];
}

/**
 * داده ساختاریافته (JSON-LD) خراب (SEO 3.8.6): خوانده نمی‌شود، بی @context schema.org، یا گره بی @type. ردیف قدیمی (-1) سنجیده نمی‌شود.
 *
 * @param array<string, mixed> $r   ردیف جدول خزش (+ impressions، kind)
 * @param array<string, mixed> $ctx زمینه hodima_seo_advisor_checks_context()
 * @return list<array<string, mixed>>
 */
function hodima_seo_advisor_check_ld_invalid( array $r, array $ctx ): array {

	$errors = json_decode( (string) ( $r['ld_errors'] ?? '' ), true );

	if ( (int) ( $r['ld_count'] ?? -1 ) < 0 || ! is_array( $errors ) || ! $errors ) {
		return [];
	}

	$names = [ 'json' => 'خوانده نمی‌شود (%s)', 'context' => '@context به schema.org ندارد (%s)', 'type' => 'نوع (@type) ندارد (%s)' ];
	$parts = [];
	foreach ( $errors as $error ) {
		if ( is_array( $error ) && isset( $names[ (string) ( $error[0] ?? '' ) ] ) ) {
			$parts[] = sprintf( $names[ (string) $error[0] ], (string) ( $error[1] ?? '' ) );
		}
	}

	return $parts ? [ hodima_seo_advisor_issue( 'ld_invalid', implode( '؛ ', array_unique( $parts ) ) ) ] : [];
}

/**
 * FAQPage اسکیما که در صفحه دیده نمی‌شود (SEO 3.9.5). نشانه را خزنده در ld_errors می‌گذارد (faq_hidden)؛ ردیف قدیمی (ld_count = -1) یا پیش از ۳.۹.۴ این نشانه را ندارد و تا خزش بعدی ایراد نمی‌دهد.
 *
 * @param array<string, mixed> $r   ردیف جدول خزش (+ impressions، kind)
 * @param array<string, mixed> $ctx زمینه hodima_seo_advisor_checks_context()
 * @return list<array<string, mixed>>
 */
function hodima_seo_advisor_check_faq_schema( array $r, array $ctx ): array {

	$errors = json_decode( (string) ( $r['ld_errors'] ?? '' ), true );

	if ( (int) ( $r['ld_count'] ?? -1 ) < 0 || ! is_array( $errors ) ) {
		return [];
	}

	$parts = [];
	foreach ( $errors as $error ) {
		if ( is_array( $error ) && 'faq_hidden' === (string) ( $error[0] ?? '' ) ) {
			$parts[] = (string) ( $error[1] ?? '' );
		}
	}

	return $parts ? [ hodima_seo_advisor_issue( 'faq_schema', implode( '؛ ', $parts ) ) ] : [];
}

/**
 * اسکیمای Product ناقص در صفحه محصول (SEO 3.9.5). نشانه را خزنده در ld_errors می‌گذارد (product_missing، product_fields)؛ ردیف قدیمی
 * (ld_count = -1) یا پیش از ۳.۹.۵ تا خزش بعدی ایراد نمی‌دهد.
 *
 * @param array<string, mixed> $r   ردیف جدول خزش (+ impressions، kind)
 * @param array<string, mixed> $ctx زمینه hodima_seo_advisor_checks_context()
 * @return list<array<string, mixed>>
 */
function hodima_seo_advisor_check_product_schema( array $r, array $ctx ): array {

	$errors = json_decode( (string) ( $r['ld_errors'] ?? '' ), true );

	if ( (int) ( $r['ld_count'] ?? -1 ) < 0 || ! is_array( $errors ) ) {
		return [];
	}

	foreach ( $errors as $error ) {
		if ( is_array( $error ) && in_array( (string) ( $error[0] ?? '' ), [ 'product_missing', 'product_fields' ], true ) ) {
			return [ hodima_seo_advisor_issue( 'product_schema', (string) ( $error[1] ?? '' ) ) ];
		}
	}

	return [];
}

/**
 * canonical به سایت دیگر (SEO 3.9.3): گوگل می‌گوید این صفحه را کنار می‌گذارد و سایت مقصد را نمایش می‌دهد. تا ۳.۹.۲ ردیف
 * ایندکس‌پذیر می‌ماند و هیچ ایرادی نمی‌ساخت (canonical_bad آدرس دامنه دیگر را اصلا نمی‌سنجید). صفحه‌های سیستمی کنار.
 *
 * @param array<string, mixed> $r   ردیف جدول خزش (+ impressions، kind)
 * @param array<string, mixed> $ctx زمینه hodima_seo_advisor_checks_context()
 * @return list<array<string, mixed>>
 */
function hodima_seo_advisor_check_canonical_external( array $r, array $ctx ): array {

	if ( 200 !== (int) $r['status'] || '!ext' !== (string) $r['canonical'] || hodima_seo_advisor_is_system_path( (string) $r['path'] ) ) {
		return [];
	}

	$url = (string) ( $r['canonical_url'] ?? '' ); // ردیف قدیمی آدرس را ندارد؛ ایراد همچنان می‌آید

	return [ hodima_seo_advisor_issue( 'canonical_external', '' !== $url ? 'به آدرسی در سایت دیگر اشاره می‌کند (زیر).' : 'به سایتی غیر از این سایت اشاره می‌کند.', $url ) ];
}

/**
 * canonical با شکل غیرمعمول (SEO 3.9.3): پروتکل دیگر (http در سایت https)، دامنه دیگر (با www در سایت بی‌www) یا پارامتر (?color=red).
 * تا ۳.۹.۲ فقط مسیر مقایسه می‌شد و این‌ها «همان صفحه» حساب می‌شدند، پس canonical غلط دیده نمی‌شد. canonical به دامنه کاملا دیگر ایراد
 * جدا دارد (canonical_external).
 *
 * @param array<string, mixed> $r   ردیف جدول خزش
 * @param array<string, mixed> $ctx زمینه hodima_seo_advisor_checks_context()
 * @return list<array<string, mixed>>
 */
function hodima_seo_advisor_check_canonical_variant( array $r, array $ctx ): array {

	$url = (string) ( $r['canonical_url'] ?? '' );

	if ( 200 !== (int) $r['status'] || '' === $url || '!ext' === (string) $r['canonical'] || hodima_seo_advisor_is_system_path( (string) $r['path'] ) ) {
		return [];
	}

	$kind = hodima_seo_advisor_canonical_variant( $url, $ctx['site'] ?? [ '', '' ] );

	if ( '' === $kind ) {
		return [];
	}

	$why = [
		'scheme' => 'با پروتکل دیگری از سایت نوشته شده (سایت %s است).',
		'host'   => 'با دامنه‌ای نوشته شده که با دامنه اصلی سایت (%s) فرق دارد.',
		'param'  => 'پارامتر دارد (%s).',
	][ $kind ];
	$arg = match ( $kind ) {
		'scheme' => ( $ctx['site'][0] ?? '' ) . '://',
		'host'   => (string) ( $ctx['site'][1] ?? '' ),
		default  => '?' . (string) wp_parse_url( $url, PHP_URL_QUERY ),
	};

	return [ hodima_seo_advisor_issue( 'canonical_variant', sprintf( $why, $arg ), $url ) ];
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
 * canonical فقط در تگ <body> (یا بعد از اولین عنصر غیر head): گوگل «فقط در بخش head می‌پذیرد» و آن را نادیده می‌گیرد (Search Central،
 * «Consolidate duplicate URLs»؛ SEO 3.6.5). خزنده این حالت را در ستون canonical با نشانه `!body` ذخیره می‌کند.
 *
 * @param array<string, mixed> $r   ردیف جدول خزش (+ impressions، kind)
 * @param array<string, mixed> $ctx زمینه hodima_seo_advisor_checks_context()
 * @return list<array<string, mixed>>
 */
function hodima_seo_advisor_check_canonical_in_body( array $r, array $ctx ): array {
	return '!body' === (string) $r['canonical'] ? [ hodima_seo_advisor_issue( 'canonical_in_body' ) ] : [];
}

/**
 * توضیح متای تکراری: همان توضیح (یکسان‌شده) در صفحه سالم دیگر هم هست. مرجع: گوگل «توضیح متای خوب برای یک صفحه یگانه است»
 * (SEO starter guide)؛ SEOnaut، SiteOne و FreeCrawl هم دارند. فقط صفحه ۲۰۰ ایندکس‌پذیر غیرصفحه‌بندی؛ ردیف قدیمی بی‌hash نمی‌شمارد.
 *
 * @param array<string, mixed> $r   ردیف جدول خزش (+ impressions، kind)
 * @param array<string, mixed> $ctx زمینه hodima_seo_advisor_checks_context()
 * @return list<array<string, mixed>>
 */
function hodima_seo_advisor_check_dup_meta( array $r, array $ctx ): array {

	$hash = (string) ( $r['meta_hash'] ?? '' );

	if ( '' === $hash || hodima_seo_advisor_crawl_is_paged( $r ) ) {
		return [];
	}

	$count = $ctx['metas'][ $hash ] ?? 0;

	return $count > 1 ? [ hodima_seo_advisor_issue( 'dup_meta', sprintf( 'در %s صفحه', number_format_i18n( $count ) ), '', hodima_seo_advisor_dup_others( $ctx, 'meta', $hash, $r ) ) ] : [];
}

/**
 * سایت از گوگل پنهان است: گزینه وردپرس «از موتورهای جستجو بخواه این سایت را ایندکس نکند» (`blog_public` = 0) روشن است و وردپرس روی
 * همه صفحه‌ها noindex می‌گذارد. گوگل: noindex یعنی «Do not show this page, media, or resource in search results» (Search Central،
 * «robots meta tag»)؛ Rank Math هم همین را آزمون «Blog Public» می‌داند. ایراد روی ردیف صفحه اصلی می‌آید و noindex جداجدای بقیه
 * صفحه‌ها را (که همه از همین یک تنظیم‌اند) خاموش می‌کند (SEO 3.6.7).
 *
 * @param array<string, mixed> $r   ردیف جدول خزش (+ impressions، kind)
 * @param array<string, mixed> $ctx زمینه hodima_seo_advisor_checks_context()
 * @return list<array<string, mixed>>
 */
function hodima_seo_advisor_check_site_hidden( array $r, array $ctx ): array {
	return '/' === (string) $r['path'] && ! empty( $ctx['hidden'] ) ? [ hodima_seo_advisor_issue( 'site_hidden' ) ] : [];
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
		$kind = (string) ( $ctx['kinds'][ $path ] ?? '' ); // از زمینه (checks_context)، نه دیتابیس
	}

	// صفحه ویدیو (روتر نمی‌شناخت، SEO 3.6.2 با url_to_postid شناخته می‌شود) ذاتا متن کم دارد؛ «متن کم» برای آن معنی ندارد.
	// آرشیو طبقه‌بندی (دسته، برچسب) هم «متن کم» نمی‌گیرد
	return '' !== $kind && 'video' !== $kind && ! isset( $ctx['taxonomies'][ $kind ] ) ? [ hodima_seo_advisor_issue( 'thin', sprintf( '%s کلمه', number_format_i18n( $words ) ) ) ] : [];
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
 * آدرس‌های سیستمی که عمدا noindex‌اند (حساب کاربری، سبد، پرداخت، ورود): «noindex» برایشان ایراد نیست و دستورش («اگر باید در گوگل
 * باشد…») گمراه‌کننده است (`/my-account/lost-password/` در سایت واقعی؛ SEO 3.6.2). صفحه‌های ووکامرس از شناسه صفحه‌شان خوانده
 * می‌شوند (نه نامک ثابت)؛ فیلتر `hodima_seo_advisor_system_paths` می‌گذارد آدرس دیگری اضافه شود.
 *
 * @return list<string> مسیرهای یکسان‌شده (path_key) با اسلش پایانی
 */
function hodima_seo_advisor_system_paths(): array {

	static $paths = null;

	if ( null !== $paths ) {
		return $paths;
	}

	$shop = [];

	if ( function_exists( 'wc_get_page_id' ) ) {
		foreach ( [ 'myaccount', 'cart', 'checkout' ] as $page ) {
			$id   = (int) wc_get_page_id( $page );
			$link = $id > 0 ? get_permalink( $id ) : false;
			if ( is_string( $link ) && '' !== $link ) {
				$shop[] = hodima_seo_advisor_path_key( $link );
			}
		}
	}

	if ( ! $shop ) {
		$shop = [ '/my-account/', '/cart/', '/checkout/' ]; // ووکامرس شناخته نشد: نامک‌های رایج
	}

	$all   = array_map( 'strval', (array) apply_filters( 'hodima_seo_advisor_system_paths', [ '/wp-login.php', ...$shop ] ) );
	$paths = array_values( array_unique( array_filter( $all, static fn( string $p ): bool => '' !== $p && '/' !== $p ) ) );

	return $paths;
}

/** آیا مسیر خود یکی از آدرس‌های سیستمی یا زیرمسیر آن است (مثل /my-account/lost-password/)؟ */
function hodima_seo_advisor_is_system_path( string $path ): bool {

	foreach ( hodima_seo_advisor_system_paths() as $system ) {
		if ( $path === $system || str_starts_with( $path, rtrim( $system, '/' ) . '/' ) ) {
			return true;
		}
	}

	return false;
}

/** از این تعداد پرش ریدایرکت به بعد «زنجیره ریدایرکت» کهربایی است (گوگل تا ۱۰ پرش را دنبال می‌کند؛ کمتر از این اطلاع است). */
const HODIMA_SEO_ADVISOR_REDIRECT_WARN_HOPS = 5;

/**
 * مسیر ریدایرکت یک آدرس را تا مقصد نهایی دنبال می‌کند (فقط با ردیف‌های خزش‌شده؛ بدون درخواست تازه).
 * hops = شمار پاسخ‌های ۳xx پشت‌سرهم از همین آدرس؛ loop = به آدرسی که قبلا دیدیم برگشت؛ final = آخرین مقصد شناخته‌شده.
 *
 * @param array<string, array<string, mixed>> $by_path ردیف‌های خزش به کلید مسیر
 * @return array{hops: int, final: string, loop: bool}
 */
function hodima_seo_advisor_redirect_trace( string $path, array $by_path ): array {

	$seen  = [ $path => true ];
	$hops  = 0;
	$cur   = $path;
	$final = '';

	while ( isset( $by_path[ $cur ] ) && (int) $by_path[ $cur ]['status'] >= 300 && (int) $by_path[ $cur ]['status'] < 400 && $hops < 20 ) {
		++$hops;
		$next = (string) $by_path[ $cur ]['redirect_to'];

		if ( '' === $next ) {
			break;
		}

		$final = $next;

		if ( isset( $seen[ $next ] ) ) {
			return [ 'hops' => $hops, 'final' => $next, 'loop' => true ];
		}

		$seen[ $next ] = true;
		$cur           = $next;
	}

	return [ 'hops' => $hops, 'final' => $final, 'loop' => false ];
}

/**
 * صفحه‌ای که ایندکس‌پذیر نیست (ریدایرکت، خطا، noindex، canonical به صفحه دیگر) و نمایش گوگل یا لینک داخلی دارد. ریدایرکت فقط
 * وقتی ایراد است که لینک داخلی به آن برود (دستورش اصلاح همان لینک‌هاست؛ تا SEO 3.0.1 با نمایش گوگل هم می‌آمد و هیچ‌وقت برطرف
 * نمی‌شد). SEO 3.6.6: ۴xx و ۵xx جدا (گوگل ۴۰۴ را مشکل نمی‌داند ولی ۵xx دائمی را از ایندکس برمی‌دارد)؛ زنجیره با شمار پرش تا مقصد
 * نهایی و حلقه؛ noindex و canonical به صفحه دیگر دو ایراد جدا؛ صفحه‌های سیستمی (حساب، سبد، پرداخت) عمدا noindex‌اند.
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
			// کدام صفحه‌ها هنوز به این آدرس قدیمی لینک داده‌اند (SEO 3.8.4؛ قبلا فقط «لینک ورودی دارد» بی‌نام مبدأ)
			$out[] = hodima_seo_advisor_issue( 'redirect', '' !== (string) $r['redirect_to'] ? 'به آدرس زیر می‌رود.' : '', (string) $r['redirect_to'], array_slice( $ctx['redirect_from'][ (string) $r['path'] ] ?? [], 0, HODIMA_SEO_ADVISOR_CRAWL_ITEMS ) ); // آدرس جدا با کپی (وسط جمله به‌هم می‌ریخت)
		}

		$trace = hodima_seo_advisor_redirect_trace( (string) $r['path'], $ctx['by_path'] );

		if ( $trace['loop'] ) {
			$out[] = hodima_seo_advisor_issue( 'redirect_loop', 'ریدایرکت‌ها به هم برمی‌گردند (حلقه)؛ آدرس بعدی زیر است.', (string) $r['redirect_to'] );
		} elseif ( $trace['hops'] >= 2 ) {
			$out[] = hodima_seo_advisor_issue(
				'redirect_chain',
				sprintf( '%s پرش تا مقصد نهایی (آدرس زیر).', number_format_i18n( $trace['hops'] ) ),
				$trace['final'],
				[],
				$trace['hops'] >= HODIMA_SEO_ADVISOR_REDIRECT_WARN_HOPS ? [ 'level' => 'warn' ] : []
			);
		}
	} elseif ( $status >= 500 ) {
		$out[] = hodima_seo_advisor_issue( 'server_error', sprintf( 'کد %s', number_format_i18n( $status ) ) );
	} elseif ( $status >= 400 ) {
		$out[] = hodima_seo_advisor_issue( 'broken', sprintf( 'کد %s', number_format_i18n( $status ) ) );
	} elseif ( 200 === $status && 0 === (int) $r['indexable'] && ! hodima_seo_advisor_is_system_path( (string) $r['path'] ) && ! ( 'Noindex' === (string) $r['reason'] && ! empty( $ctx['hidden'] ) ) ) { // سایت پنهان: noindex همه صفحه‌ها از همان یک تنظیم است (ایراد site_hidden)
		if ( 'Canonicalised' === (string) $r['reason'] ) {
			$canonical = (string) $r['canonical'];
			$out[]     = hodima_seo_advisor_issue( 'canonicalised', '' !== $canonical && ! str_starts_with( $canonical, '!' ) ? sprintf( 'به %s اشاره می‌کند.', rawurldecode( $canonical ) ) : '' );
		} else {
			$out[] = hodima_seo_advisor_issue( 'noindex' );
		}
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
		'site_hidden'    => [ 'when' => 'always', 'codes' => [ 'site_hidden' ], 'fn' => 'hodima_seo_advisor_check_site_hidden' ],
		'sitemap_bad'    => [ 'when' => 'always', 'codes' => [ 'sitemap_bad' ], 'fn' => 'hodima_seo_advisor_check_sitemap_bad' ],
		'canonical_bad'  => [ 'when' => 'always', 'codes' => [ 'canonical_bad' ], 'fn' => 'hodima_seo_advisor_check_canonical_bad' ],
		'canonical_external' => [ 'when' => 'always', 'codes' => [ 'canonical_external' ], 'fn' => 'hodima_seo_advisor_check_canonical_external' ],
		'canonical_variant'  => [ 'when' => 'always', 'codes' => [ 'canonical_variant' ], 'fn' => 'hodima_seo_advisor_check_canonical_variant' ],
		'title'          => [ 'when' => 'live', 'codes' => [ 'no_title', 'title_short', 'title_long' ], 'fn' => 'hodima_seo_advisor_check_title' ],
		'no_meta'        => [ 'when' => 'live', 'codes' => [ 'no_meta' ], 'fn' => 'hodima_seo_advisor_check_no_meta' ],
		'no_snippet'     => [ 'when' => 'live', 'codes' => [ 'no_snippet' ], 'fn' => 'hodima_seo_advisor_check_no_snippet' ],
		'no_h1'          => [ 'when' => 'live', 'codes' => [ 'no_h1' ], 'fn' => 'hodima_seo_advisor_check_no_h1' ],
		'dup_title'      => [ 'when' => 'live', 'codes' => [ 'dup_title' ], 'fn' => 'hodima_seo_advisor_check_dup_title' ],
		'dup_h1'         => [ 'when' => 'live', 'codes' => [ 'dup_h1' ], 'fn' => 'hodima_seo_advisor_check_dup_h1' ],
		'dup_meta'       => [ 'when' => 'live', 'codes' => [ 'dup_meta' ], 'fn' => 'hodima_seo_advisor_check_dup_meta' ],
		'near_dup'       => [ 'when' => 'live', 'codes' => [ 'near_dup' ], 'fn' => 'hodima_seo_advisor_check_near_dup' ],
		'slow'           => [ 'when' => 'live', 'codes' => [ 'slow' ], 'fn' => 'hodima_seo_advisor_check_slow' ],
		'inlinks'        => [ 'when' => 'live', 'codes' => [ 'orphan', 'low_inlinks' ], 'fn' => 'hodima_seo_advisor_check_inlinks' ],
		'link_targets'   => [ 'when' => 'live', 'codes' => [ 'link_broken', 'link_redirect', 'link_noindex' ], 'fn' => 'hodima_seo_advisor_check_link_targets' ],
		'multi_h1'       => [ 'when' => 'known', 'codes' => [ 'multi_h1' ], 'fn' => 'hodima_seo_advisor_check_multi_h1' ],
		'images'         => [ 'when' => 'known', 'codes' => [ 'img_alt', 'img_empty' ], 'fn' => 'hodima_seo_advisor_check_images' ],
		'http_link'      => [ 'when' => 'known', 'codes' => [ 'http_link' ], 'fn' => 'hodima_seo_advisor_check_http_link' ],
		'link_no_text'   => [ 'when' => 'known', 'codes' => [ 'link_no_text' ], 'fn' => 'hodima_seo_advisor_check_link_no_text' ],
		'link_js'        => [ 'when' => 'known', 'codes' => [ 'link_js' ], 'fn' => 'hodima_seo_advisor_check_link_js' ],
		'soft_404'       => [ 'when' => 'known', 'codes' => [ 'soft_404' ], 'fn' => 'hodima_seo_advisor_check_soft_404' ],
		'ld_invalid'     => [ 'when' => 'known', 'codes' => [ 'ld_invalid' ], 'fn' => 'hodima_seo_advisor_check_ld_invalid' ],
		'faq_schema'     => [ 'when' => 'known', 'codes' => [ 'faq_schema' ], 'fn' => 'hodima_seo_advisor_check_faq_schema' ],
		'product_schema' => [ 'when' => 'known', 'codes' => [ 'product_schema' ], 'fn' => 'hodima_seo_advisor_check_product_schema' ],
		'no_canonical'   => [ 'when' => 'known', 'codes' => [ 'no_canonical' ], 'fn' => 'hodima_seo_advisor_check_no_canonical' ],
		'canonical_in_body' => [ 'when' => 'known', 'codes' => [ 'canonical_in_body' ], 'fn' => 'hodima_seo_advisor_check_canonical_in_body' ],
		'thin'           => [ 'when' => 'known', 'codes' => [ 'thin' ], 'fn' => 'hodima_seo_advisor_check_thin' ],
		'not_in_sitemap' => [ 'when' => 'known', 'codes' => [ 'not_in_sitemap' ], 'fn' => 'hodima_seo_advisor_check_not_in_sitemap' ],
		'depth'          => [ 'when' => 'live', 'codes' => [ 'island', 'deep' ], 'fn' => 'hodima_seo_advisor_check_depth' ],
		'status'         => [ 'when' => 'dead', 'codes' => [ 'redirect', 'redirect_chain', 'redirect_loop', 'broken', 'server_error', 'noindex', 'canonicalised' ], 'fn' => 'hodima_seo_advisor_check_status' ],
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
	// صفحه‌ای که سایت خودش در نقشه سایت معرفی کرده «شناخته‌شده» است (SEO 3.9.3). باگ قبلی: نوشته منتشرشده‌ای که ۵۰۰ یا ۴۰۴ می‌داد ولی
	// نمایش گوگل و لینک ورودی نداشت، بی‌صدا می‌ماند؛ نقشه سایت همه منتشرشده‌های فعال را دارد.
	$seen  = (int) ( $r['impressions'] ?? 0 ) > 0 || (int) $r['inlinks'] > 0 || isset( $ctx['sitemap'][ (string) $r['path'] ] );
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

/**
 * صفحه‌های دیگرِ همان عنوان/H1/توضیح متای تکراری (بی‌خود صفحه؛ تا HODIMA_SEO_ADVISOR_CRAWL_ITEMS) برای «کدام صفحه‌ها؟» (SEO 3.7.3).
 * قبلا ایراد فقط «در ۲ صفحه» می‌گفت و مدیر نمی‌دانست کدام‌ها.
 *
 * @param array<string, mixed> $ctx زمینه hodima_seo_advisor_checks_context()
 * @param array<string, mixed> $r   ردیف همین صفحه
 * @return list<array{path: string, note: string}>
 */
function hodima_seo_advisor_dup_others( array $ctx, string $kind, string $key, array $r ): array {

	$out = [];
	foreach ( (array) ( $ctx['dups'][ $kind ][ $key ] ?? [] ) as $path ) {
		if ( $path !== (string) $r['path'] ) {
			$other = (array) ( $ctx['by_path'][ $path ] ?? [] );
			$out[] = [ 'path' => (string) $path, 'note' => 'meta' === $kind ? '' : mb_substr( (string) ( 'h1' === $kind ? ( $other['title'] ?? '' ) : ( $other['h1'] ?? '' ) ), 0, 70 ) ];
		}
	}

	return array_slice( $out, 0, HODIMA_SEO_ADVISOR_CRAWL_ITEMS );
}

/**
 * پیشنهاد «از این صفحه‌ها لینک بدهید» برای صفحه بی‌لینک ورودی (SEO 3.7.3): صفحه‌های سالم و ایندکس‌پذیر که در عنوان/H1 بیشترین کلمه
 * معنی‌دار مشترک را با این صفحه دارند (همان کلمه‌سازی «عنوان مشابه»، بدون نام سایت)؛ هم‌امتیازها با لینک ورودی بیشتر (صفحه قوی‌تر) اول.
 * فقط پیشنهاد؛ مدیر خودش می‌سنجد کدام مرتبط است.
 *
 * @param array<string, mixed> $r   ردیف همین صفحه
 * @param array<string, mixed> $ctx زمینه hodima_seo_advisor_checks_context()
 * @return list<array{path: string, note: string}>
 */
function hodima_seo_advisor_link_sources_suggest( array $r, array $ctx ): array {

	$path  = (string) $r['path'];
	$mine  = $ctx['words'][ $path ] ?? hodima_seo_advisor_similar_tokens( (string) $r['title'] . ' ' . (string) $r['h1'] );
	$found = [];

	if ( ! $mine ) {
		return [];
	}

	foreach ( (array) ( $ctx['words'] ?? [] ) as $other => $tokens ) {
		$other = (string) $other;
		if ( $other === $path ) {
			continue;
		}
		$shared = array_values( array_intersect( $mine, (array) $tokens ) );
		if ( $shared ) {
			$found[] = [ $other, count( $shared ), (int) ( $ctx['by_path'][ $other ]['inlinks'] ?? 0 ), $shared ];
		}
	}

	usort( $found, static fn( array $a, array $b ): int => [ $b[1], $b[2], $a[0] ] <=> [ $a[1], $a[2], $b[0] ] );

	return array_map(
		static fn( array $f ): array => [ 'path' => $f[0], 'note' => sprintf( 'کلمه مشترک: %s', implode( '، ', array_slice( array_unique( $f[3] ), 0, 3 ) ) ) ],
		array_slice( $found, 0, 3 )
	);
}
