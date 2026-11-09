<?php
/**
 * ماژول «دستار سئو» — خزنده شبانه (SEO 3.1.4)
 * Path: core/advisor/advisor-crawler.php
 *
 * خود دستار صفحه‌های سایت را می‌خواند با کرون هر ۵ دقیقه یک دسته کوچک، فقط در پنجره
 * شبانه (پیش‌فرض ۲ تا ۸ صبح به وقت سایت). نتیجه در همان جدول‌های خزش (crawl_pages، crawl_links) می‌رود؛ پس همه
 * ایرادها (advisor-crawl.php) بدون تغییر کار می‌کنند. خزنده فقط می‌خواند؛ چیزی را در سایت عوض نمی‌کند.
 *
 * اصل‌های سبک‌ماندن سایت: یک درخواست در هر لحظه، فاصله دست‌کم ۱ ثانیه (دو برابر زمان پاسخ اگر سایت کند باشد)،
 * بودجه ۲۰ ثانیه برای هر نوبت، و اگر پاسخ‌ها کند شد (میانگین ۳ درخواست آخر بیش از ۳ ثانیه) نیم ساعت مکث.
 * هر چه خزیده شد همان لحظه ذخیره می‌شود و فقط آدرس‌هایی که در این دور دیده نشدند در پایان دور پاک می‌شوند؛
 * خطای وسط کار داده قبلی را خراب نمی‌کند.
 *
 * محدودیت: جاوااسکریپت اجرا نمی‌شود (لینک یا متنی که فقط با JS ساخته شود دیده نمی‌شود)؛ «جایگاه لینک»
 * (محتوا/منو/فوتر/کنار) از تگ‌ها و نام کلاس‌ها حدس زده می‌شود.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

// ویژگی‌های DOMDocument (parentNode، nodeName، textContent) camelCase‌اند و اسم‌شان در دست ما نیست
// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

/** وضعیت خزش جاری (صف، دیده‌شده‌ها، شمارنده‌ها)؛ autoload نه. */
const HODIMA_SEO_ADVISOR_CRAWLER_OPTION = 'hodima_advisor_crawler';

/** تنظیمات خزنده: روشن/خاموش، ساعت شروع و پایان، فاصله دورها (روز). */
const HODIMA_SEO_ADVISOR_CRAWLER_SETTINGS = 'hodima_advisor_crawler_settings';

/** قفل نوبت خزنده (جدا از قفل دریافت داده تا هم‌دیگر را معطل نکنند). */
const HODIMA_SEO_ADVISOR_CRAWLER_LOCK = 'hodima_advisor_crawler_lock';

/** رویداد تکرارشونده کرون. */
const HODIMA_SEO_ADVISOR_CRAWLER_CRON = 'hodima_advisor_crawler_tick';

/** بودجه هر نوبت (ثانیه)؛ کمتر از زمان انقضای قفل. */
const HODIMA_SEO_ADVISOR_CRAWLER_BUDGET = 20;

/** کمترین فاصله بین دو درخواست (ثانیه). */
const HODIMA_SEO_ADVISOR_CRAWLER_DELAY = 1.0;

/** سقف آدرس‌های هر دور. */
const HODIMA_SEO_ADVISOR_CRAWLER_MAX_PAGES = 5000;

/** بیشینه آدرس تصویر بدون alt که برای فهرست «کدام تصویرها؟» نگه داشته می‌شود (SEO 3.3.6). */
const HODIMA_SEO_ADVISOR_CRAWLER_IMG_LIST = 20; // تا ۳.۴.۹: ۸؛ حالا هر صفحه تا ۲۰ تصویر ایراد و ۲۰ تصویر «بررسی کنید»

/**
 * دور سریع (SEO 3.3.8): هر روز فقط صفحه اصلی، پرنمایش‌ترین صفحه‌ها (۱۵٪ صفحه‌ها، بین ۵۰ و ۳۰۰) و نوشته‌های تازه یا ویرایش‌شده را
 * می‌خزد؛ صفحه‌های دیگر داده دور کامل را نگه می‌دارند. دور کامل (حذف صفحه‌های رفته، نقشه سایت) هر «فاصله دورها» روز می‌ماند.
 */
const HODIMA_SEO_ADVISOR_CRAWLER_QUICK_TOP_MIN   = 50;
const HODIMA_SEO_ADVISOR_CRAWLER_QUICK_TOP_MAX   = 300;
const HODIMA_SEO_ADVISOR_CRAWLER_QUICK_TOP_SHARE = 0.15;

/** پاسخ کندتر از این (میلی‌ثانیه، میانگین ۳ درخواست آخر) خزنده را موقتا نگه می‌دارد. */
const HODIMA_SEO_ADVISOR_CRAWLER_SLOW_MS = 3000;

/** مدت مکث بعد از کندی سایت یا خطای پی‌درپی (ثانیه). */
const HODIMA_SEO_ADVISOR_CRAWLER_PAUSE = 1800;

/** آدرس‌های نقشه سایت دور آخر (مسیر یکسان‌شده)؛ autoload نه. */
const HODIMA_SEO_ADVISOR_SITEMAP_OPTION = 'hodima_advisor_sitemap';

/** سقف تعداد فایل نقشه سایت که در هر دور خوانده می‌شود. */
const HODIMA_SEO_ADVISOR_CRAWLER_MAX_MAPS = 40;

/** گزینه‌های فاصله دورها (روز). */
const HODIMA_SEO_ADVISOR_CRAWLER_INTERVALS = [ 1, 3, 7, 14 ];

/**
 * تنظیمات خزنده. `always` = همه ساعت‌های شبانه‌روز (۲۴ ساعته)؛ ساعت شروع و پایان برابر هم همان است (سازگاری با ۳.۱.۴).
 * ساعت پایان تا ۲۴ (نیمه‌شب) می‌شود؛ پایان کوچک‌تر از شروع یعنی بازه شب تا صبح.
 *
 * @return array{enabled: bool, always: bool, start: int, end: int, interval: int}
 */
function hodima_seo_advisor_crawler_settings(): array {

	$saved = get_option( HODIMA_SEO_ADVISOR_CRAWLER_SETTINGS, [] );
	$saved = is_array( $saved ) ? $saved : [];
	$hour  = static fn( mixed $value, int $fallback, int $max = 23 ): int => max( 0, min( $max, is_numeric( $value ) ? (int) $value : $fallback ) );
	$days  = (int) ( $saved['interval'] ?? 7 );

	return [
		'enabled'  => (bool) ( $saved['enabled'] ?? true ),
		'always'   => (bool) ( $saved['always'] ?? false ),
		'start'    => $hour( $saved['start'] ?? null, 2 ),
		'end'      => $hour( $saved['end'] ?? null, 8, 24 ),
		'interval' => in_array( $days, HODIMA_SEO_ADVISOR_CRAWLER_INTERVALS, true ) ? $days : 7,
	];
}

/** الان (به وقت سایت) داخل پنجره خزش است؟ */
function hodima_seo_advisor_crawler_in_window( ?int $now = null ): bool {

	$s    = hodima_seo_advisor_crawler_settings();
	$hour = (int) wp_date( 'G', $now ?? time() );

	if ( $s['always'] || $s['start'] === $s['end'] ) {
		return true;
	}

	return $s['start'] < $s['end'] ? ( $hour >= $s['start'] && $hour < $s['end'] ) : ( $hour >= $s['start'] || $hour < $s['end'] );
}

/**
 * وضعیت ذخیره‌شده خزش با پیش‌فرض‌ها.
 *
 * @return array{status: string, run: int, started: int, seed: list<string>, queue: list<string>, seen: array<string, int>, sitemap: array<string, int>, maps: int, manual: bool, done: int, errors: int, recent: list<int>, pause_until: int, throttled: int, finished: int, pages: int, links: int, capped: bool, tick: int, mode: string, full_at: int, sitemap_problems: list<array{u: string, s: int, t: string, k: string}>}
 */
function hodima_seo_advisor_crawler_state(): array {

	$state = get_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION, [] );
	$state = is_array( $state ) ? $state : [];

	return [
		'status'      => 'running' === ( $state['status'] ?? '' ) ? 'running' : 'idle',
		'run'         => (int) ( $state['run'] ?? 0 ),
		'started'     => (int) ( $state['started'] ?? 0 ),
		'seed'        => array_values( array_map( 'strval', (array) ( $state['seed'] ?? [] ) ) ),
		'queue'       => array_values( array_map( 'strval', (array) ( $state['queue'] ?? [] ) ) ),
		'seen'        => array_map( 'intval', (array) ( $state['seen'] ?? [] ) ),
		'sitemap'     => array_map( 'intval', (array) ( $state['sitemap'] ?? [] ) ),
		'maps'        => (int) ( $state['maps'] ?? 0 ),
		'manual'      => ! empty( $state['manual'] ),
		'done'        => (int) ( $state['done'] ?? 0 ),
		'errors'      => (int) ( $state['errors'] ?? 0 ),
		'recent'      => array_values( array_map( 'intval', (array) ( $state['recent'] ?? [] ) ) ),
		'pause_until' => (int) ( $state['pause_until'] ?? 0 ),
		'throttled'   => (int) ( $state['throttled'] ?? 0 ), // آخرین «درخواست زیاد است» سرور (SEO 3.8.7)؛ پیام مکث در پیشخوان
		'finished'    => (int) ( $state['finished'] ?? 0 ),
		'pages'       => (int) ( $state['pages'] ?? 0 ),
		'links'       => (int) ( $state['links'] ?? 0 ),
		'capped'      => ! empty( $state['capped'] ),
		'tick'        => (int) ( $state['tick'] ?? 0 ),
		'mode'        => 'quick' === ( $state['mode'] ?? '' ) ? 'quick' : 'full',
		'full_at'     => (int) ( $state['full_at'] ?? $state['finished'] ?? 0 ), // پیش از ۳.۳.۸ همه دورها کامل بودند
		'sitemap_problems' => hodima_seo_advisor_sitemap_problems_clean( $state['sitemap_problems'] ?? [] ), // ریدایرکت یا خطای فایل‌های نقشه سایت این دور (SEO 3.5.2)
	];
}

/**
 * @param array<string, mixed> $state
 */
function hodima_seo_advisor_crawler_save( array $state ): void {
	$state['tick'] = time(); // زمان آخرین نوبت: سنجش سلامت کرون (advisor-cron.php)
	update_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION, $state, false );
}

/* ── آدرس‌ها ── */

/**
 * آدرس لینک (نسبی یا کامل) ← آدرس کامل نسبت به صفحه مبدأ؛ null = لینک غیرصفحه (mailto، #، javascript…).
 */
function hodima_seo_advisor_crawler_resolve( string $href, string $base ): ?string {

	$href = trim( $href );

	// هر پروتکل غیر http(s) لینک صفحه نیست: mailto، tel، javascript و لینک باز کردن اپ‌ها (snapp://، tapsi://، tg://، viber://، intent://…).
	// تا ۳.۶.۴ فقط چند پروتکل شناخته‌شده کنار می‌رفت و «snapp://open» به آدرس ساختگی «/contact-us/snapp:/open/» تبدیل و ۴۰۴ گزارش می‌شد.
	// مرورگر هم هر «نام:» ابتدای مقدار را پروتکل می‌داند (RFC 3986)، پس مسیر نسبی با دو نقطه در بخش اول وجود ندارد.
	if ( '' === $href || str_starts_with( $href, '#' ) || ( 1 === preg_match( '#^[a-z][a-z0-9+.\-]*:#i', $href ) && 1 !== preg_match( '#^https?:#i', $href ) ) ) {
		return null;
	}

	$href  = explode( '#', $href, 2 )[0];
	$parts = wp_parse_url( $base );

	if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
		return null;
	}

	$origin = ( $parts['scheme'] ?? 'https' ) . '://' . $parts['host'] . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' );

	if ( str_starts_with( $href, '//' ) ) {
		return ( $parts['scheme'] ?? 'https' ) . ':' . $href;
	}

	if ( 1 === preg_match( '#^https?://#i', $href ) ) {
		return $href;
	}

	if ( str_starts_with( $href, '/' ) ) {
		return $origin . $href;
	}

	// نسبی: نسبت به پوشه صفحه مبدأ، با «.» و «..»
	$query = '';
	if ( str_contains( $href, '?' ) ) {
		[ $href, $query ] = explode( '?', $href, 2 );
		$query            = '?' . $query;
	}

	$dir      = (string) ( $parts['path'] ?? '/' );
	$dir      = str_ends_with( $dir, '/' ) ? $dir : substr( $dir, 0, (int) strrpos( $dir, '/' ) + 1 );
	$segments = [];

	foreach ( explode( '/', $dir . $href ) as $segment ) {
		if ( '..' === $segment ) {
			array_pop( $segments );
		} elseif ( '' !== $segment && '.' !== $segment ) {
			$segments[] = $segment;
		}
	}

	return $origin . '/' . implode( '/', $segments ) . ( str_ends_with( $href, '/' ) && $segments ? '/' : '' ) . $query;
}

/** آدرسی که نباید خزید (فایل، مسیرهای فنی وردپرس)؛ فیلتر hodima_seo_advisor_crawler_skip برای استثناهای سایت. */
function hodima_seo_advisor_crawler_should_skip( string $path ): bool {

	if ( 1 === preg_match( '/\.(?:jpe?g|png|gif|webp|avif|svg|ico|pdf|zip|rar|gz|mp[34]|webm|mov|css|js|json|xml|txt|woff2?|ttf|eot|docx?|xlsx?|pptx?)$/i', $path ) ) {
		return true;
	}

	foreach ( [ '/wp-admin', '/wp-login.php', '/wp-json', '/xmlrpc.php', '/wp-content/', '/wp-includes/' ] as $prefix ) {
		if ( str_starts_with( $path, $prefix ) ) {
			return true;
		}
	}

	if ( str_contains( $path, '/feed/' ) ) {
		return true;
	}

	return (bool) apply_filters( 'hodima_seo_advisor_crawler_skip', false, $path );
}

/**
 * آدرس قابل‌خزش با دامنه خود سایت (نسخه www یا پروتکل دیگر به دامنه اصلی می‌رود)، با حفظ حروف مسیر؛ null = بیرونی/نخزیدنی/دارای پارامتر.
 */
function hodima_seo_advisor_crawler_normalize( string $url ): ?string {

	$parts = wp_parse_url( $url );

	if ( ! is_array( $parts ) || isset( $parts['query'] ) || null === hodima_seo_advisor_path_key( $url ) ) {
		return null;
	}

	$path = (string) ( $parts['path'] ?? '/' );
	$key  = hodima_seo_advisor_path_key( $url );

	if ( null === $key || hodima_seo_advisor_crawler_should_skip( $key ) ) {
		return null;
	}

	// مسیر آدرس مطلق از پیش پیشوند زیرپوشه را دارد؛ به دامنه چسبانده می‌شود نه به home_url() (SEO 3.4.7: در نصب زیرپوشه‌ای دوبار می‌آمد)
	return untrailingslashit( hodima_seo_advisor_url( '/' ) ) . ( '' === $path ? '/' : $path );
}

/**
 * آدرس‌های خزیده‌شده در دورهای قبل (برای دور سریع). فقط در همین درخواست نگه داشته می‌شود.
 *
 * @return array<string, int>
 */
function hodima_seo_advisor_crawler_known(): array {

	global $wpdb;

	static $known = null;

	return $known ??= array_fill_keys( array_map( 'strval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT path FROM %i', hodima_seo_advisor_table( 'crawl_pages' ) ) ) ), 1 ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
}

/**
 * آدرس را به صف اضافه می‌کند اگر در این دور هنوز دیده نشده.
 *
 * @param array<string, mixed> $state
 */
function hodima_seo_advisor_crawler_enqueue( array &$state, string $url, bool $force = false ): void {

	$fetch = hodima_seo_advisor_crawler_normalize( $url );
	$key   = null === $fetch ? null : hodima_seo_advisor_path_key( $fetch );

	if ( null === $fetch || null === $key || isset( $state['seen'][ $key ] ) ) {
		return;
	}

	// دور سریع: لینک پیداشده فقط اگر آدرس تازه است خزیده می‌شود؛ صفحه شناخته‌شده را خود seed (پرنمایش/ویرایش‌شده) تعیین می‌کند ($force)
	if ( ! $force && 'quick' === ( $state['mode'] ?? '' ) && isset( hodima_seo_advisor_crawler_known()[ $key ] ) ) {
		return;
	}

	// سقف پر است: این صفحه خزیده نمی‌شود. نشانش می‌کنیم تا پایان دور «ندیده‌شده» را «حذف‌شده» نگیرد (باگ قبلی: ردیف‌های بالای سقف هر دور پاک می‌شدند)
	if ( count( $state['seen'] ) >= HODIMA_SEO_ADVISOR_CRAWLER_MAX_PAGES ) {
		$state['capped'] = true;
		return;
	}

	$state['seen'][ $key ] = 1;
	$state['queue'][]      = $fetch;
}

/* ── خواندن یک صفحه ── */

/**
 * عنصر داخل یکی از این تگ‌هاست؟ (نیاکانش را تا ریشه می‌پیماید.)
 *
 * @param list<string> $tags نام تگ‌ها (حروف کوچک)
 */
function hodima_seo_advisor_crawler_inside( DOMNode $node, array $tags ): bool {

	for ( $parent = $node->parentNode; $parent instanceof DOMElement; $parent = $parent->parentNode ) {
		if ( in_array( strtolower( $parent->nodeName ), $tags, true ) ) {
			return true;
		}
	}

	return false;
}

/**
 * نشانه‌های عبارت «یافت نشد» در عنوان/H1 (یکسان‌شده؛ عدد ۴۰۴ به‌تنهایی هم). قالب هدیما برای آرشیو خالی H1 «محتوایی یافت نشد!» دارد.
 */
const HODIMA_SEO_ADVISOR_SOFT404_PHRASES = [ 'یافت نشد', 'پیدا نشد', 'not found', 'وجود ندارد', 'صفحه مورد نظر' ];

/** کلاس پیام «چیزی نیست» آرشیو خالی: ووکامرس (دسته بی‌محصول) و قالب هدیما (مقاله، ویدیو، آرشیو عمومی). */
const HODIMA_SEO_ADVISOR_SOFT404_MARKERS = [ 'woocommerce-no-products-found', 'blog-page-empty', 'videos-page-empty', 'hodima-empty' ];

/**
 * Soft 404 (SEO 3.8.6؛ گوگل: صفحه‌ای که «یافت نشد» یا خالی است ولی کد ۲۰۰ می‌دهد): عنوان یا H1 با عبارت «یافت نشد»، یا پیام آرشیو خالی
 * (ووکامرس/قالب). خروجی «title|عبارت» یا «empty|کلاس»؛ خالی = نیست. پیام «نتیجه‌ای پیدا نشد» جستجو کلاس نشانه ندارد و آدرس جستجو خزیده نمی‌شود.
 */
function hodima_seo_advisor_crawler_soft404( string $title, string $h1, DOMXPath $xpath ): string {

	foreach ( [ $h1, $title ] as $text ) {
		$norm = hodima_seo_advisor_normalize( $text );
		if ( 1 === preg_match( '/(?:^|\s)404(?:$|\s)/u', $norm ) ) {
			return 'title|404';
		}
		foreach ( HODIMA_SEO_ADVISOR_SOFT404_PHRASES as $phrase ) {
			if ( '' !== $norm && str_contains( $norm, hodima_seo_advisor_normalize( $phrase ) ) ) {
				return 'title|' . $phrase;
			}
		}
	}

	foreach ( HODIMA_SEO_ADVISOR_SOFT404_MARKERS as $marker ) {
		$found = $xpath->query( "//*[contains(concat(' ', normalize-space(@class), ' '), ' {$marker} ')]" );
		if ( false !== $found && $found->length > 0 ) {
			return 'empty|' . $marker;
		}
	}

	return '';
}

/**
 * داده ساختاریافته JSON-LD صفحه (SEO 3.8.6؛ گوگل: داده‌ای که خوانده نمی‌شود یا @type ندارد نادیده گرفته می‌شود): شمار اسکریپت‌ها و
 * تا ۵ ایراد [نوع، توضیح]: json (خوانده نمی‌شود)، context (بی @context schema.org)، type (گره بی @type).
 *
 * @return array{count: int, errors: list<array{0: string, 1: string}>}
 */
function hodima_seo_advisor_crawler_jsonld( DOMXPath $xpath ): array {

	$list   = $xpath->query( "//script[translate(normalize-space(@type),'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz')='application/ld+json']" );
	$count  = 0;
	$errors = [];
	$add    = static function ( string $kind, string $text ) use ( &$errors ): void {
		if ( count( $errors ) < 5 ) {
			$errors[] = [ $kind, mb_substr( $text, 0, 120 ) ];
		}
	};

	foreach ( false === $list ? [] : $list as $node ) {
		++$count;
		$raw  = trim( (string) $node->textContent );
		$data = '' === $raw ? null : json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			$add( 'json', '' === $raw ? 'اسکریپت خالی' : json_last_error_msg() );
			continue;
		}
		$tops = array_is_list( $data ) ? $data : [ $data ];
		foreach ( $tops as $top ) {
			if ( ! is_array( $top ) ) {
				$add( 'type', 'مقدار بی‌ساختار' );
				continue;
			}
			if ( ! str_contains( strtolower( (string) wp_json_encode( $top['@context'] ?? '' ) ), 'schema.org' ) ) {
				$add( 'context', (string) ( $top['@type'] ?? $top['@id'] ?? 'بی‌نام' ) );
			}
			foreach ( isset( $top['@graph'] ) && is_array( $top['@graph'] ) ? $top['@graph'] : [ $top ] as $item ) {
				if ( ! is_array( $item ) || empty( $item['@type'] ) ) {
					$add( 'type', is_array( $item ) ? (string) ( $item['@id'] ?? implode( '، ', array_slice( array_keys( $item ), 0, 3 ) ) ) : 'مقدار بی‌ساختار' );
				}
			}
		}
	}

	return [ 'count' => $count, 'errors' => $errors ];
}

/** نزدیک‌ترین نیای <a> با href واقعی (دکمه/آیکون داخل لینک معمولی، لینک جاوااسکریپتی جدا نیست)؛ null = ندارد. */
function hodima_seo_advisor_crawler_closest_href( DOMElement $el ): ?DOMElement {

	for ( $parent = $el->parentNode; $parent instanceof DOMElement; $parent = $parent->parentNode ) {
		if ( 'a' === strtolower( $parent->nodeName ) && '' !== trim( $parent->getAttribute( 'href' ) ) && ! str_starts_with( strtolower( trim( $parent->getAttribute( 'href' ) ) ), 'javascript:' ) ) {
			return $parent;
		}
	}

	return null;
}

/**
 * جایگاه لینک (یا تصویر/تیتر) از نیای آن: footer، navigation (nav/هدر سایت/منو/مسیر راهنما)، aside (کنار/ویجت) یا content.
 *
 * SEO 3.4.5 (دقت): (۱) «widget» و «sidebar» فقط وقتی کلاس یا شناسه *خودشان* باشند، نه پاره‌ای از نام بلندتر: قبلا هر
 * `elementor-widget-*` (همه محتوای صفحه‌های المنتور) و `content-sidebar-wrap` (ژنسیس) «کناری» می‌شد و لینک و تصویرشان از
 * ایراد تصویر بدون alt و لینک متنی بیرون می‌ماند؛ (۲) `<header>` داخل `<article>` یا `<main>` سربرگ خود نوشته است نه منو.
 */
function hodima_seo_advisor_crawler_link_position( DOMElement $link ): string {

	$found = [];

	for ( $node = $link->parentNode; $node instanceof DOMElement; $node = $node->parentNode ) {
		$tag = strtolower( $node->nodeName );

		if ( in_array( $tag, [ 'body', 'html' ], true ) ) {
			break;
		}

		$names  = strtolower( $node->getAttribute( 'class' ) . ' ' . $node->getAttribute( 'id' ) );
		$tokens = preg_split( '/\s+/', trim( $names ), -1, PREG_SPLIT_NO_EMPTY ) ?: [];
		$side   = false;

		foreach ( $tokens as $token ) {
			if ( in_array( $token, [ 'sidebar', 'widget', 'widgets', 'widget-area', 'secondary' ], true ) || str_starts_with( $token, 'sidebar-' ) || str_starts_with( $token, 'widget_' ) ) {
				$side = true;
				break;
			}
		}

		if ( 'footer' === $tag || 1 === preg_match( '/(?:^|[\s_-])(?:footer|colophon)(?:$|[\s_-])/', $names ) ) {
			$found['footer'] = true;
		} elseif ( 'nav' === $tag || ( 'header' === $tag && ! hodima_seo_advisor_crawler_inside( $node, [ 'article', 'main' ] ) ) || 1 === preg_match( '/(?:^|[\s_-])(?:nav|navbar|menu|breadcrumbs?)(?:$|[\s_-])/', $names ) ) {
			$found['navigation'] = true;
		} elseif ( 'aside' === $tag || $side ) {
			$found['aside'] = true;
		}
	}

	// فوتر بر منو غالب است (منوی داخل فوتر «فوتر» است)، منو بر کنار
	foreach ( [ 'footer', 'navigation', 'aside' ] as $position ) {
		if ( isset( $found[ $position ] ) ) {
			return $position;
		}
	}

	return 'content';
}

/**
 * تصویری که alt خالی دارد ولی لینک/دکمه یا figure اطرافش متن دارد (کارت محصول/مقاله)؛ alt خالی آنجا درست است.
 * نزدیک‌ترین لینک/دکمه تصمیم می‌گیرد: متن یا aria-label داشته باشد = برچسب‌دار؛ فقط خود تصویر = بی‌برچسب.
 *
 * @param callable(string): string $clean یکسان‌ساز فاصله‌ها
 */
function hodima_seo_advisor_crawler_img_labelled( DOMElement $img, callable $clean ): bool {

	for ( $node = $img->parentNode; $node instanceof DOMElement; $node = $node->parentNode ) {
		$tag = strtolower( $node->nodeName );

		if ( in_array( $tag, [ 'body', 'html' ], true ) ) {
			break;
		}

		if ( 'a' === $tag || 'button' === $tag ) {
			return '' !== $clean( $node->getAttribute( 'aria-label' ) ) || '' !== $clean( $node->textContent );
		}

		if ( 'figure' === $tag ) {
			foreach ( $node->getElementsByTagName( 'figcaption' ) as $caption ) {
				if ( '' !== $clean( $caption->textContent ) ) {
					return true;
				}
			}
		}
	}

	return false;
}

/**
 * تصویر دیده نمی‌شود؟ (SEO 3.4.9) داخل `<template>`، `<dialog>` بسته (پاپ‌آپ نوتیفیکیشن، نمای سریع)، عنصر `hidden`،
 * `aria-hidden="true"` یا `display:none`/`visibility:hidden` درون‌خطی. قبلا چنین تصویری هم «بدون alt» حساب می‌شد و
 * پاپ‌آپی که روی همه صفحه‌ها چاپ می‌شود یک ایراد مشترک می‌ساخت. پنهان‌سازی با کلاس CSS بیرونی را بدون اجرای CSS نمی‌شود دید.
 */
function hodima_seo_advisor_crawler_img_hidden( DOMElement $img ): bool {

	for ( $node = $img->parentNode; $node instanceof DOMElement; $node = $node->parentNode ) {
		$tag = strtolower( $node->nodeName );

		if ( 'template' === $tag || ( 'dialog' === $tag && ! $node->hasAttribute( 'open' ) ) ) {
			return true;
		}

		if ( ( $node->hasAttribute( 'hidden' ) && 'until-found' !== strtolower( $node->getAttribute( 'hidden' ) ) ) || 'true' === strtolower( $node->getAttribute( 'aria-hidden' ) ) ) {
			return true;
		}

		if ( 1 === preg_match( '/(?:^|;)\s*(?:display\s*:\s*none|visibility\s*:\s*hidden)/i', $node->getAttribute( 'style' ) ) ) {
			return true;
		}
	}

	return $img->hasAttribute( 'hidden' ) || 1 === preg_match( '/(?:^|;)\s*(?:display\s*:\s*none|visibility\s*:\s*hidden)/i', $img->getAttribute( 'style' ) );
}

/**
 * جایگاه تصویر در HTML برای «مدرک» کارت: نزدیک‌ترین نیای معنادار با شناسه یا کلاس (مثلا `dialog#hd-notification-5`،
 * `div.sp-gallery`). فقط حدس ساختاری از روی HTML است، نه شناسایی افزونه.
 */
function hodima_seo_advisor_crawler_img_context( DOMElement $img ): string {

	$skip  = [ 'a', 'p', 'span', 'picture', 'figure', 'li', 'button', 'b', 'i', 'em', 'strong', 'label' ];
	$depth = 0;

	for ( $node = $img->parentNode; $node instanceof DOMElement && $depth < 10; $node = $node->parentNode, ++$depth ) {
		$tag = strtolower( $node->nodeName );

		if ( in_array( $tag, [ 'body', 'html' ], true ) ) {
			break;
		}

		if ( in_array( $tag, $skip, true ) ) {
			continue;
		}

		$id      = trim( $node->getAttribute( 'id' ) );
		$classes = array_slice( (array) preg_split( '/\s+/', trim( $node->getAttribute( 'class' ) ), -1, PREG_SPLIT_NO_EMPTY ), 0, 2 );
		$label   = '' !== $id ? $tag . '#' . $id : ( $classes ? $tag . '.' . implode( '.', $classes ) : '' );

		if ( '' !== $label ) {
			return mb_substr( (string) preg_replace( '/[^\p{L}\p{N}_#.\-]/u', '', $label ), 0, 60 );
		}
	}

	return '';
}

/**
 * آدرس واقعی تصویر (کامل): data-src افزونه‌های تنبل‌بارگذاری، src، یا اولین آدرس srcset؛ رشته خالی = آدرسی در HTML نیست
 * (جاگیر `data:`، src خالی یا ساخته‌شده با جاوااسکریپت) یا بلندتر از ۳۰۰ نویسه.
 */
function hodima_seo_advisor_crawler_img_url( DOMElement $img, string $base ): string {

	$source = '';

	foreach ( [ 'data-src', 'data-lazy-src', 'data-original', 'src' ] as $attr ) {
		$candidate = trim( $img->getAttribute( $attr ) );
		if ( '' !== $candidate && ! str_starts_with( $candidate, 'data:' ) ) {
			$source = $candidate;
			break;
		}
	}

	if ( '' === $source ) {
		foreach ( [ 'data-srcset', 'srcset' ] as $attr ) {
			$first = trim( (string) ( preg_split( '/\s*,\s*/', trim( $img->getAttribute( $attr ) ) )[0] ?? '' ) );
			$first = trim( (string) ( preg_split( '/\s+/', $first )[0] ?? '' ) );
			if ( '' !== $first && ! str_starts_with( $first, 'data:' ) ) {
				$source = $first;
				break;
			}
		}
	}

	$resolved = '' === $source ? null : hodima_seo_advisor_crawler_resolve( $source, $base );

	return null !== $resolved && mb_strlen( $resolved ) <= 300 ? $resolved : '';
}

/**
 * تصویرهای دیده‌شونده محتوای صفحه و وضعیت alt هر کدام (SEO 3.4.9؛ قبلا alt خالی و نبود alt یکی بود و تصویر نامرئی هم می‌آمد):
 *   missing = ویژگی alt اصلا نیست؛ linked = `alt=""` داخل لینک/دکمه‌ای که خودش نام ندارد (لینک بی‌نام)؛ هر دو «ایراد».
 *   empty   = `alt=""` تنها (لوگو، آیکون، تزئین؟) ← «بررسی کنید»، ایراد نیست و در نمره نمی‌آید.
 * alt پر یعنی تصویر درست است؛ فایلی که در همین صفحه جای دیگری alt دارد هم ایراد نمی‌گیرد. تزئینی صریح (`aria-hidden`،
 * `role=presentation`)، ریز/ردیاب، `alt=""` داخل لینک یا figure با متن، تصویر نامرئی و `<noscript>` کنار می‌روند.
 *
 * @param callable(string): string $clean یکسان‌ساز فاصله‌ها
 * @return array{noalt: int, empty: int, list: list<array{u: string, s: string, c: string}>}
 */
function hodima_seo_advisor_crawler_scan_images( DOMXPath $xpath, callable $clean, string $url ): array {

	$entries  = [];
	$alt_seen = [];
	$images   = $xpath->query( '//img' );

	foreach ( false === $images ? [] : $images as $img ) {
		// <noscript> نسخه پشتیبان همان تصویر تنبل‌بارگذاری است؛ libxml آن را عنصر می‌بیند و یک تصویر دو بار شمرده می‌شد (SEO 3.4.5)
		if ( ! $img instanceof DOMElement || hodima_seo_advisor_crawler_inside( $img, [ 'noscript' ] ) || 'content' !== hodima_seo_advisor_crawler_link_position( $img ) || hodima_seo_advisor_crawler_img_hidden( $img ) ) {
			continue;
		}

		$source = hodima_seo_advisor_crawler_img_url( $img, $url );
		$decor  = 'true' === strtolower( $img->getAttribute( 'aria-hidden' ) ) || in_array( strtolower( $img->getAttribute( 'role' ) ), [ 'presentation', 'none' ], true );
		$tiny   = ( $img->hasAttribute( 'width' ) && (int) $img->getAttribute( 'width' ) <= 2 ) || ( '' === $source && str_starts_with( trim( $img->getAttribute( 'src' ) ), 'data:image/gif' ) ); // جاگیر gif فقط وقتی «ریز» است که آدرس واقعی (data-src) ندارد

		if ( $decor || $tiny ) {
			continue;
		}

		$raw   = $img->getAttribute( 'alt' );
		$blank = 1 === preg_match( '/^[\s\x{00A0}\x{200B}-\x{200F}\x{2060}\x{FEFF}]*$/u', $raw ); // فقط فاصله یا نویسه نامرئی هم «خالی» است

		if ( $img->hasAttribute( 'alt' ) && ! $blank ) {
			if ( '' !== $source ) {
				$alt_seen[ $source ] = true;
			}
			continue;
		}

		if ( ! $img->hasAttribute( 'alt' ) ) {
			$state = 'missing';
		} elseif ( hodima_seo_advisor_crawler_img_labelled( $img, $clean ) ) {
			continue; // alt خالی عمدی کارت: متن یا aria-label لینک/دکمه یا کپشن figure نامش را می‌گوید (SEO 3.3.3)
		} else {
			$state = hodima_seo_advisor_crawler_inside( $img, [ 'a', 'button' ] ) ? 'linked' : 'empty';
		}

		$context                                       = hodima_seo_advisor_crawler_img_context( $img );
		$entries[ $state . '|' . $source . '|' . $context . ( '' === $source ? '|' . count( $entries ) : '' ) ] ??= [ 'u' => $source, 's' => $state, 'c' => $context ]; // تصویر بی‌آدرس‌ها از هم جدا می‌مانند
	}

	$entries = array_values( array_filter( $entries, static fn( array $e ): bool => '' === $e['u'] || ! isset( $alt_seen[ $e['u'] ] ) ) );
	$issues  = array_values( array_filter( $entries, static fn( array $e ): bool => 'empty' !== $e['s'] ) );
	$review  = array_values( array_filter( $entries, static fn( array $e ): bool => 'empty' === $e['s'] ) );

	return [
		'noalt' => count( $issues ),
		'empty' => count( $review ),
		'list'  => [ ...array_slice( $issues, 0, HODIMA_SEO_ADVISOR_CRAWLER_IMG_LIST ), ...array_slice( $review, 0, HODIMA_SEO_ADVISOR_CRAWLER_IMG_LIST ) ],
	];
}

/**
 * HTML یک صفحه ← عنوان، متا، تیترها، robots، canonical و لینک‌های داخلی.
 *
 * @return array{title: string, meta: string, robots: string, canonical: string, canonical_body: bool, h1: string, h1_count: int, h2: string, words: int, img_noalt: int, img_empty: int, img_list: list<array{u: string, s: string, c: string}>, http_links: int, http_list: list<array{0: string, 1: string}>, notext_links: int, notext_list: list<array{0: string, 1: string}>, js_links: int, js_list: list<array{0: string, 1: string}>, soft404: string, ld_count: int, ld_errors: list<array{0: string, 1: string}>, links: list<array{0: string, 1: string, 2: string, 3: int}>}
 */
function hodima_seo_advisor_crawler_parse( string $html, string $url ): array {

	$previous = libxml_use_internal_errors( true );
	$dom      = new DOMDocument();
	$dom->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING );
	libxml_clear_errors();
	libxml_use_internal_errors( $previous );

	$xpath = new DOMXPath( $dom );
	$lower = static fn( string $attr ): string => "translate({$attr},'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz')";
	$clean = static fn( string $text ): string => trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	$first = static function ( DOMXPath $xp, string $query ) use ( $clean ): string {
		$list = $xp->query( $query );
		$node = false === $list ? null : $list->item( 0 );
		return null === $node ? '' : $clean( $node->textContent );
	};
	$metas = static function ( DOMXPath $xp, string $name ) use ( $lower ): array {
		$out  = [];
		$list = $xp->query( "//meta[{$lower( '@name' )}='{$name}']" );
		foreach ( false === $list ? [] : $list as $node ) {
			if ( $node instanceof DOMElement ) {
				$out[] = trim( $node->getAttribute( 'content' ) );
			}
		}
		return $out;
	};

	$h2s = [];
	$h2l = $xpath->query( '//h2' );
	foreach ( false === $h2l ? [] : $h2l as $node ) {
		$text = $clean( $node->textContent );
		if ( '' !== $text ) {
			$h2s[] = $text;
		}
		if ( count( $h2s ) >= 2 ) {
			break;
		}
	}

	// canonical فقط داخل <head> پذیرفته می‌شود («The rel="canonical" link element is only accepted if it appears in the <head> section»،
	// Google Search Central)؛ تگی که یک افزونه یا قالب بعد از شروع body چاپ کرده نادیده گرفته می‌شود. تا ۳.۶.۴ از هر جای سند خوانده می‌شد.
	// پارسر libxml مثل مرورگر عمل می‌کند: head با اولین عنصر غیر head (مثل div) بسته می‌شود.
	$canonical      = '';
	$canonical_body = false;
	$can_list       = $xpath->query( "//head//link[{$lower( '@rel' )}='canonical']" );
	$can_node       = false === $can_list ? null : $can_list->item( 0 );
	if ( $can_node instanceof DOMElement ) {
		$canonical = trim( $can_node->getAttribute( 'href' ) );
	} else {
		$can_any        = $xpath->query( "//link[{$lower( '@rel' )}='canonical']" );
		$canonical_body = false !== $can_any && $can_any->length > 0;
	}

	$links      = [];
	$http_links = 0;
	$http_list  = []; // تا ۵ لینک http نخست [آدرس نوشته‌شده، متن لینک] برای فهرست «کدام لینک‌ها؟» (SEO 3.7.3؛ قبلا فقط شمار)
	$https_site = str_starts_with( home_url(), 'https://' );
	$all        = $xpath->query( '//a[@href]' );
	$texted     = []; // مقصدهایی که دست‌کم یک لینک متن‌دار در همین صفحه دارند
	$empty      = []; // مقصد ← آدرس نوشته‌شده، برای لینک‌های بی‌متن متن صفحه (SEO 3.8.4)

	foreach ( false === $all ? [] : $all as $a ) {
		if ( ! $a instanceof DOMElement ) {
			continue;
		}

		$target = hodima_seo_advisor_crawler_resolve( $a->getAttribute( 'href' ), $url );
		$dest   = null === $target ? null : hodima_seo_advisor_path_key( $target );

		if ( null === $target || null === $dest ) {
			continue;
		}

		$anchor = $clean( $a->textContent );
		if ( '' === $anchor ) {
			$img = $a->getElementsByTagName( 'img' )->item( 0 );
			if ( $img instanceof DOMElement ) {
				$anchor = $clean( $img->getAttribute( 'alt' ) );
			}
		}

		if ( $https_site && 1 === preg_match( '#^http://#i', trim( $a->getAttribute( 'href' ) ) ) ) {
			++$http_links; // لینک داخلی با پروتکل ناامن در سایت https
			if ( count( $http_list ) < 5 ) {
				$http_list[] = [ mb_substr( trim( $a->getAttribute( 'href' ) ), 0, 300 ), mb_substr( $anchor, 0, 80 ) ];
			}
		}

		$follow   = str_contains( strtolower( $a->getAttribute( 'rel' ) ), 'nofollow' ) ? 0 : 1;
		$position = hodima_seo_advisor_crawler_link_position( $a );
		$links[]  = [ $target, $position, mb_substr( $anchor, 0, 120 ), $follow ];

		if ( '' !== $anchor ) {
			$texted[ $dest ] = true;
		} elseif ( 'content' === $position ) {
			$empty[ $dest ] ??= mb_substr( trim( $a->getAttribute( 'href' ) ), 0, 300 );
		}
	}

	// لینک بی‌متن (SEO 3.8.4؛ گوگل: «Write good link text»): فقط در متن صفحه (آیکون منو/فوتر روی همه صفحه‌ها تکرار می‌شود) و فقط اگر
	// همان مقصد در همین صفحه لینک متن‌دار دیگری ندارد (کارت محصول: تصویر با alt خالی کنار نام محصول با همان لینک ایراد نیست)
	$notext = array_diff_key( $empty, $texted );

	// لینک جاوااسکریپتی (SEO 3.8.4؛ گوگل: فقط <a> با href دنبال می‌شود): onclick با آدرس، <a> بی href با data-href/data-url،
	// href="javascript:…" با آدرس. فقط آدرس داخلی سایت؛ <a> با href واقعی (onclick کنار آن) و عنصر داخل چنین لینکی ایراد نیست.
	$js_list = [];
	$js_seen = [];
	$js_all  = $xpath->query( "//*[@onclick] | //a[not(@href)][@data-href or @data-url] | //a[starts-with(normalize-space({$lower( '@href' )}),'javascript:')]" );
	foreach ( false === $js_all ? [] : $js_all as $el ) {
		if ( ! $el instanceof DOMElement ) {
			continue;
		}
		$href = trim( $el->getAttribute( 'href' ) );
		if ( ( 'a' === strtolower( $el->nodeName ) && null !== hodima_seo_advisor_crawler_resolve( $href, $url ) ) || null !== hodima_seo_advisor_crawler_closest_href( $el ) ) {
			continue;
		}
		$code = $el->getAttribute( 'onclick' ) . ' ' . $el->getAttribute( 'data-href' ) . ' ' . $el->getAttribute( 'data-url' ) . ' ' . $href;
		// onclick فقط وقتی کاربر را جابه‌جا می‌کند (location، window.open، href)، نه آمار و رویداد («ga('send','/event')»)
		if ( '' === $el->getAttribute( 'data-href' ) . $el->getAttribute( 'data-url' ) && ! str_starts_with( strtolower( $href ), 'javascript:' ) && 1 !== preg_match( '#location|window\.open|\.href\s*=#i', $el->getAttribute( 'onclick' ) ) ) {
			continue;
		}
		if ( 1 !== preg_match( '#(https?://[^\s\'"()<>]+|(?<=[\'"])/[^\s\'"()<>]*)#u', $code . ' "' . trim( $el->getAttribute( 'data-href' ) . $el->getAttribute( 'data-url' ) ) . '"', $m ) ) {
			continue;
		}
		$target = hodima_seo_advisor_crawler_resolve( $m[1], $url );
		$dest   = null === $target ? null : hodima_seo_advisor_path_key( $target );
		if ( null === $dest || isset( $js_seen[ $dest ] ) ) {
			continue;
		}
		$js_seen[ $dest ] = true;
		if ( count( $js_list ) < 5 ) {
			$js_list[] = [ mb_substr( $m[1], 0, 300 ), mb_substr( $clean( $el->textContent ), 0, 80 ) ];
		}
	}

	// تصویرهای دیده‌شونده و وضعیت alt هر کدام (SEO 3.4.9: hodima_seo_advisor_crawler_scan_images)
	$scan = hodima_seo_advisor_crawler_scan_images( $xpath, $clean, $url );

	// شمار کلمه‌های متن اصلی: main، وگرنه article، وگرنه body؛ بدون منو، سربرگ، فوتر، کنار، اسکریپت و استایل
	$root = '//body';
	foreach ( [ '//main', '//article' ] as $candidate ) {
		$found = $xpath->query( $candidate );
		if ( false !== $found && $found->length > 0 ) {
			$root = $candidate;
			break;
		}
	}
	$texts = $xpath->query( "{$root}//text()[not(ancestor::script or ancestor::style or ancestor::noscript or ancestor::nav or ancestor::header or ancestor::footer or ancestor::aside)]" );
	$plain = '';
	foreach ( false === $texts ? [] : $texts as $node ) {
		$plain .= ' ' . $node->textContent;
	}
	$words = count( (array) preg_split( '/\s+/u', trim( $plain ), -1, PREG_SPLIT_NO_EMPTY ) );

	// H1 صفحه: تیترهای H1 محتوا؛ H1 لوگوی هدر سایت یا پاپ‌آپ با آن یکی حساب نمی‌شود. اگر محتوا H1 نداشت همه H1ها می‌آیند
	// تا صفحه‌ای که فقط H1 لوگو دارد «بدون H1» نشود (SEO 3.4.5)
	$h1_all     = [];
	$h1_content = [];
	$h1_query   = $xpath->query( '//h1' );
	foreach ( false === $h1_query ? [] : $h1_query as $h1 ) {
		if ( ! $h1 instanceof DOMElement || hodima_seo_advisor_crawler_inside( $h1, [ 'noscript' ] ) ) {
			continue;
		}
		$h1_all[] = $h1;
		if ( 'content' === hodima_seo_advisor_crawler_link_position( $h1 ) ) {
			$h1_content[] = $h1;
		}
	}
	$h1_pick = $h1_content ?: $h1_all;
	$title   = $first( $xpath, '/html/head/title' );
	$h1_text = $h1_pick ? $clean( $h1_pick[0]->textContent ) : '';
	$ld      = hodima_seo_advisor_crawler_jsonld( $xpath );

	return [
		'title'      => $title,
		'soft404'    => hodima_seo_advisor_crawler_soft404( $title, $h1_text, $xpath ), // SEO 3.8.6
		'ld_count'   => $ld['count'],
		'ld_errors'  => $ld['errors'],
		'meta'       => $metas( $xpath, 'description' )[0] ?? '',
		'robots'     => implode( ', ', [ ...$metas( $xpath, 'robots' ), ...$metas( $xpath, 'googlebot' ) ] ), // googlebot هم noindex/nosnippet را می‌رساند (SEO 3.4.7)
		'canonical'  => $canonical,
		'canonical_body' => $canonical_body,
		'h1'         => $h1_text,
		'h1_count'   => count( $h1_pick ),
		'h2'         => implode( ' | ', $h2s ),
		'words'      => $words,
		'img_noalt'  => $scan['noalt'],
		'img_empty'  => $scan['empty'],
		'img_list'   => $scan['list'],
		'http_links' => $http_links,
		'http_list'  => $http_list,
		'notext_links' => count( $notext ),
		'notext_list'  => array_slice( array_map( static fn( string $href ): array => [ $href, '' ], array_values( $notext ) ), 0, 5 ),
		'js_links'     => count( $js_seen ),
		'js_list'      => $js_list,
		'links'      => $links,
	];
}

/**
 * یک آدرس را می‌خواند (بدون دنبال‌کردن ریدایرکت، بدون کوکی؛ همان HTML کش‌شده‌ای که گوگل می‌بیند).
 *
 * @return array{status: int, ms: int, body: string, html: bool, location: string, x_robots: string}|null null = خطای اتصال یا مهلت
 */
function hodima_seo_advisor_crawler_fetch( string $url ): ?array {

	$start    = microtime( true );
	$response = wp_remote_get(
		$url,
		[
			'timeout'             => 20,
			'redirection'         => 0,
			'user-agent'          => 'HodimaSEO-Advisor/' . ( defined( 'HODIMA_SEO_VERSION' ) ? (string) HODIMA_SEO_VERSION : '1' ) . ' (site audit; nightly)',
			'limit_response_size' => 2 * MB_IN_BYTES,
			'sslverify'           => (bool) apply_filters( 'hodima_seo_advisor_crawler_sslverify', true ),
			'headers'             => [ 'Accept' => 'text/html,application/xhtml+xml' ],
		]
	);
	$ms       = (int) round( ( microtime( true ) - $start ) * 1000 );

	if ( is_wp_error( $response ) ) {
		return null;
	}

	$header   = static function ( string $name ) use ( $response ): string {
		$value = wp_remote_retrieve_header( $response, $name );
		return is_array( $value ) ? implode( ', ', array_map( 'strval', $value ) ) : (string) $value;
	};
	$is_html  = str_contains( strtolower( $header( 'content-type' ) ), 'html' );

	return [
		'status'   => (int) wp_remote_retrieve_response_code( $response ),
		'ms'       => $ms,
		'body'     => wp_remote_retrieve_body( $response ),
		'html'     => $is_html,
		'location' => $header( 'location' ),
		'x_robots' => $header( 'x-robots-tag' ),
		'retry'    => $header( 'retry-after' ), // ۴۲۹/۵۰۳ موقت (SEO 3.8.7)
	];
}

/** سقف مکث خزنده بعد از «درخواست زیاد است» (ثانیه): Retry-After بزرگ‌تر از این بریده می‌شود. */
const HODIMA_SEO_ADVISOR_CRAWLER_THROTTLE_MAX = 6 * HOUR_IN_SECONDS;

/**
 * پاسخ «فعلا نه» سرور/دیوار امنیتی (SEO 3.8.7): ۴۲۹ همیشه، ۵۰۳ فقط با Retry-After (نگهداری یا محدودیت موقت). چند ثانیه مکث (حداقل
 * مکث کندی، حداکثر ۶ ساعت)؛ ۰ = محدودیت نیست. باگ تا ۳.۸.۶: ۴۲۹ مثل صفحه ذخیره و «صفحه خراب» گزارش می‌شد و داده سالم قبلی همان
 * صفحه پاک می‌شد؛ ۵۰۳ موقت هم «خطای سرور» قرمز می‌ساخت.
 *
 * @param array{status: int, retry?: string} $fetched
 */
function hodima_seo_advisor_crawler_throttle_seconds( array $fetched ): int {

	$retry = trim( (string) ( $fetched['retry'] ?? '' ) );

	if ( 429 !== $fetched['status'] && ! ( 503 === $fetched['status'] && '' !== $retry ) ) {
		return 0;
	}

	$wait = ctype_digit( $retry ) ? (int) $retry : ( '' !== $retry && false !== strtotime( $retry ) ? (int) strtotime( $retry ) - time() : 0 ); // ثانیه یا تاریخ HTTP

	return min( HODIMA_SEO_ADVISOR_CRAWLER_THROTTLE_MAX, max( HODIMA_SEO_ADVISOR_CRAWLER_PAUSE, $wait ) );
}

/**
 * خواندن یک آدرس با دو اصلاح دقت (SEO 3.4.5): (۱) خطای اتصال یا مهلت یک بار دیگر تلاش می‌شود (خطای گذرای هاست صفحه را
 * «نرسیده» نکند)؛ (۲) پاسخ ۲۰۰ کندتر از آستانه «کند» یک بار دوباره خوانده می‌شود و سریع‌تر ثبت می‌شود: اولین خواندن
 * اغلب کش سرد است و ایراد «پاسخ کند» را برای صفحه‌ای که بعد از کش سریع است می‌ساخت.
 *
 * @return array{status: int, ms: int, body: string, html: bool, location: string, x_robots: string}|null
 */
function hodima_seo_advisor_crawler_fetch_checked( string $url ): ?array {

	$fetched = hodima_seo_advisor_crawler_fetch( $url );

	if ( null === $fetched ) {
		$pause = (float) apply_filters( 'hodima_seo_advisor_crawler_retry_delay', 2.0 ); // فیلتر فقط برای آزمون
		if ( $pause > 0 ) {
			usleep( (int) round( $pause * 1000000 ) );
		}
		return hodima_seo_advisor_crawler_fetch( $url );
	}

	if ( 200 === $fetched['status'] && $fetched['ms'] >= HODIMA_SEO_ADVISOR_CRAWL_SLOW_MS ) {
		$again = hodima_seo_advisor_crawler_fetch( $url );
		if ( null !== $again && 200 === $again['status'] && $again['ms'] < $fetched['ms'] ) {
			return $again;
		}
	}

	return $fetched;
}

/**
 * ردیف جدول خزش یک صفحه.
 *
 * @param array<string, mixed>|null $parsed
 * @return array<string, mixed>
 */
function hodima_seo_advisor_crawler_page_row( string $path, int $status, int $ms, ?array $parsed, string $redirect_to, string $x_robots ): array {

	$robots    = strtolower( (string) ( $parsed['robots'] ?? '' ) . ', ' . $x_robots );
	$noindex   = str_contains( $robots, 'noindex' ) || 1 === preg_match( '/(?:^|[\s,])none(?:$|[\s,])/', $robots );
	$canonical = (string) ( $parsed['canonical'] ?? '' );
	$can_path  = '' === $canonical ? null : hodima_seo_advisor_path_key( $canonical );
	$reason    = '';

	if ( 200 !== $status ) {
		$reason = $status >= 300 && $status < 400 ? 'Redirected' : '';
	} elseif ( $noindex ) {
		$reason = 'Noindex';
	} elseif ( null !== $can_path && $can_path !== $path ) {
		$reason = 'Canonicalised';
	}

	$title = (string) ( $parsed['title'] ?? '' );
	$meta  = (string) ( $parsed['meta'] ?? '' );

	return [
		'path'       => $path,
		'status'     => $status,
		'indexable'  => 200 === $status && '' === $reason ? 1 : 0,
		'reason'     => $reason,
		'title'      => mb_substr( $title, 0, HODIMA_SEO_ADVISOR_KEY_LENGTH ),
		'title_len'  => mb_strlen( $title ),
		'meta_len'   => mb_strlen( $meta ),
		'title_px'   => hodima_seo_text_px( $title, 'title' ), // عرض پیکسلی با فونت مرجع (۰ = ردیف قدیمی، هنوز اندازه‌گیری نشده)
		'meta_px'    => hodima_seo_text_px( $meta, 'description' ),
		'h1'         => mb_substr( (string) ( $parsed['h1'] ?? '' ), 0, HODIMA_SEO_ADVISOR_KEY_LENGTH ),
		'h2'         => mb_substr( (string) ( $parsed['h2'] ?? '' ), 0, 255 ),
		'resp_ms'    => $ms,
		'inlinks'    => 0,
		'text_links' => 0,
		'depth'      => 0,
		'score'      => 0,
		'redirect'   => $redirect_to,
		'no_snippet' => hodima_seo_advisor_robots_no_snippet( (string) ( $parsed['robots'] ?? '' ), $x_robots ) ? 1 : 0,
		'words'      => (int) ( $parsed['words'] ?? -1 ),
		'h1_count'   => (int) ( $parsed['h1_count'] ?? -1 ),
		'img_noalt'  => (int) ( $parsed['img_noalt'] ?? -1 ),
		'http_links' => (int) ( $parsed['http_links'] ?? -1 ),
		'canonical'  => '' === $canonical ? ( ! empty( $parsed['canonical_body'] ) ? '!body' : '' ) : ( null === $can_path ? '!ext' : $can_path ), // !body = فقط در body (گوگل نادیده می‌گیرد)، !ext = دامنه دیگر
		'img_list'   => hodima_seo_advisor_img_list_encode( (array) ( $parsed['img_list'] ?? [] ) ), // JSON تصویرها: آدرس، وضعیت alt، جایگاه (SEO 3.4.9؛ پیش‌تر یک آدرس در هر خط)
		'img_empty'  => (int) ( $parsed['img_empty'] ?? -1 ),
		'meta_hash'  => '' === trim( $meta ) ? '' : substr( md5( hodima_seo_advisor_normalize( $meta ) ), 0, 12 ), // برای «توضیح متای تکراری» (SEO 3.6.5)؛ خالی = بدون متا یا ردیف قدیمی
		'http_list'  => empty( $parsed['http_list'] ) ? '' : (string) wp_json_encode( array_values( (array) $parsed['http_list'] ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ), // SEO 3.7.3
		'notext_links' => (int) ( $parsed['notext_links'] ?? -1 ), // SEO 3.8.4: -1 = ردیف قدیمی (تا خزش بعدی سنجیده نشده)
		'notext_list'  => empty( $parsed['notext_list'] ) ? '' : (string) wp_json_encode( array_values( (array) $parsed['notext_list'] ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
		'js_links'     => (int) ( $parsed['js_links'] ?? -1 ),
		'js_list'      => empty( $parsed['js_list'] ) ? '' : (string) wp_json_encode( array_values( (array) $parsed['js_list'] ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
		'soft404'      => mb_substr( (string) ( $parsed['soft404'] ?? '' ), 0, 64 ), // SEO 3.8.6: «title|عبارت» یا «empty|نشانه»؛ خالی = نیست
		'ld_count'     => (int) ( $parsed['ld_count'] ?? -1 ),
		'ld_errors'    => empty( $parsed['ld_errors'] ) ? '' : (string) wp_json_encode( array_values( (array) $parsed['ld_errors'] ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ),
	];
}

/**
 * یک آدرس صف را می‌خواند و ذخیره می‌کند؛ آدرس‌های داخلی تازه را به صف می‌افزاید.
 *
 * @param array<string, mixed> $state
 * @param bool                 $keep_stats لینک ورودی، لینک متنی، عمق و امتیاز ردیف قبلی حفظ شود تا پایان دور (finalize) از نو بشمارد
 * @return int|null زمان پاسخ (میلی‌ثانیه) یا null اگر اتصال شکست خورد
 */
function hodima_seo_advisor_crawler_process( string $url, array &$state, bool $keep_stats = false ): ?int {

	$key = hodima_seo_advisor_path_key( $url );

	if ( null === $key ) {
		return 0;
	}

	$fetched = hodima_seo_advisor_crawler_fetch_checked( $url );

	if ( null === $fetched ) {
		return null;
	}

	$status = $fetched['status'];

	// «درخواست زیاد است»: صفحه ذخیره نمی‌شود (ردیف قبلی سالم می‌ماند)، آدرس به سر صف برمی‌گردد و خزنده مکث می‌کند
	$throttle = hodima_seo_advisor_crawler_throttle_seconds( $fetched );
	if ( $throttle > 0 ) {
		array_unshift( $state['queue'], $url );
		$state['throttle'] = $throttle;
		return $fetched['ms'];
	}

	if ( 200 === $status && ! $fetched['html'] ) {
		return $fetched['ms']; // فایل یا خروجی غیر HTML صفحه نیست
	}

	$parsed   = null;
	$redirect = '';
	$links    = [];

	if ( $status >= 300 && $status < 400 ) {
		$target   = hodima_seo_advisor_crawler_resolve( $fetched['location'], $url );
		$redirect = null === $target ? '' : (string) ( hodima_seo_advisor_path_key( $target ) ?? '' );
		if ( null !== $target ) {
			hodima_seo_advisor_crawler_enqueue( $state, $target );
		}
	} elseif ( 200 === $status ) {
		$parsed = hodima_seo_advisor_crawler_parse( $fetched['body'], $url );
		$links  = hodima_seo_advisor_crawler_page_links( $parsed, $key );

		foreach ( $parsed['links'] as [ $target ] ) {
			hodima_seo_advisor_crawler_enqueue( $state, $target );
		}
	}

	$row = hodima_seo_advisor_crawler_page_row( $key, $status, $fetched['ms'], $parsed, $redirect, $fetched['x_robots'] );
	hodima_seo_advisor_crawler_store_page( $key, $row, $links, $keep_stats );

	return $fetched['ms'];
}

/**
 * لینک‌های یکتای خروجی یک صفحه خوانده‌شده (یکتا بر اساس مقصد، جایگاه و متن لینک؛ لینک به خود صفحه نه).
 *
 * @param array<string, mixed> $parsed خروجی hodima_seo_advisor_crawler_parse
 * @return list<array{0: string, 1: string, 2: string, 3: string, 4: int}> [مبدأ، مقصد، جایگاه، متن، follow]
 */
function hodima_seo_advisor_crawler_page_links( array $parsed, string $key ): array {

	$unique = [];
	$links  = [];

	foreach ( (array) ( $parsed['links'] ?? [] ) as [ $target, $position, $anchor, $follow ] ) {
		$dest = hodima_seo_advisor_path_key( $target );

		if ( null === $dest || $dest === $key ) {
			continue;
		}

		$link_key = "{$dest}|{$position}|{$anchor}";

		if ( ! isset( $unique[ $link_key ] ) ) {
			$unique[ $link_key ] = true;
			$links[]             = [ $key, $dest, mb_substr( $position, 0, 16 ), $anchor, $follow ];
		}
	}

	return $links;
}

/**
 * ردیف خزش یک صفحه و لینک‌های خروجی‌اش را (جایگزین قبلی‌ها) می‌نویسد. هم خزنده شبانه و هم بررسی فوری بعد از
 * ذخیره (advisor-live.php) از همین استفاده می‌کنند تا ستون‌ها و روش خواندن یکی بماند.
 *
 * @param array<string, mixed> $row ردیف hodima_seo_advisor_crawler_page_row
 * @param list<array{0: string, 1: string, 2: string, 3: string, 4: int}> $links
 * @param bool $keep_stats لینک ورودی، لینک متنی، عمق و امتیاز ردیف قبلی حفظ شود تا پایان دور (finalize) از نو بشمارد
 * @return bool false = نوشتن شکست خورد (داده قبلی برگشت)
 */
function hodima_seo_advisor_crawler_store_page( string $key, array $row, array $links, bool $keep_stats ): bool {

	global $wpdb;

	hodima_seo_advisor_ensure_tables(); // ستون تازه img_list (DB 1.9.0) پیش از اولین نوشتن بعد از به‌روزرسانی

	$columns = 'path, status, indexable, reason, title, title_len, meta_len, title_px, meta_px, h1, h2, resp_ms, inlinks, text_links, depth, score, redirect_to, no_snippet, words, h1_count, img_noalt, http_links, canonical, img_list, img_empty, meta_hash, http_list, notext_links, notext_list, js_links, js_list, soft404, ld_count, ld_errors';
	$format  = '(%s,%d,%d,%s,%s,%d,%d,%d,%d,%s,%s,%d,%d,%d,%d,%f,%s,%d,%d,%d,%d,%d,%s,%s,%d,%s,%s,%d,%s,%d,%s,%s,%d,%s)';
	$page_t  = hodima_seo_advisor_table( 'crawl_pages' );
	$link_t  = hodima_seo_advisor_table( 'crawl_links' );

	if ( $keep_stats ) {
		$old = $wpdb->get_row( $wpdb->prepare( 'SELECT inlinks, text_links, depth, score FROM %i WHERE path = %s', $page_t, $key ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( is_array( $old ) ) {
			$row = array_merge( $row, [ 'inlinks' => (int) $old['inlinks'], 'text_links' => (int) $old['text_links'], 'depth' => (int) $old['depth'], 'score' => (float) $old['score'] ] ); // جای کلیدها در ردیف عوض نمی‌شود (درج بر اساس ترتیب است)
		}
	}

	$stored = hodima_seo_advisor_transaction(
		static function () use ( $wpdb, $page_t, $link_t, $key, $columns, $format, $row, $links ): bool {
			$wpdb->delete( $page_t, [ 'path' => $key ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( $link_t, [ 'source_path' => $key ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			hodima_seo_advisor_insert( $page_t, $columns, $format, [ array_values( $row ) ] );
			foreach ( array_chunk( $links, 200 ) as $chunk ) {
				hodima_seo_advisor_insert( $link_t, 'source_path, dest_path, link_position, anchor, is_follow', '(%s,%s,%s,%s,%d)', $chunk );
			}
			return true;
		}
	);

	return true === $stored;
}

/* ── شروع، نوبت، پایان ── */

/**
 * حالت تازه یک دور (صف با صفحه اصلی، seed داده‌شده).
 *
 * @param array<string, mixed> $old  حالت پیشین
 * @param list<string>         $seed
 * @return array<string, mixed>
 */
function hodima_seo_advisor_crawler_new_state( array $old, string $mode, array $seed ): array {

	$state = [
		'status'      => 'running',
		'run'         => time(),
		'started'     => time(),
		'seed'        => $seed,
		'queue'       => [],
		'seen'        => [],
		'sitemap'     => [],
		'maps'        => 0,
		'manual'      => false,
		'done'        => 0,
		'errors'      => 0,
		'recent'      => [],
		'pause_until' => 0,
		'finished'    => $old['finished'],
		'pages'       => $old['pages'],
		'links'       => $old['links'],
		'capped'      => false,
		'mode'        => $mode,
		'full_at'     => $old['full_at'],
		'sitemap_problems' => [],
	];

	hodima_seo_advisor_crawler_enqueue( $state, home_url( '/' ), true );

	return $state;
}

/**
 * seed دور سریع: پرنمایش‌ترین صفحه‌ها (از داده گوگل) و نوشته‌های تازه یا ویرایش‌شده از دور پیشین؛ صفحه اصلی خودش در new_state می‌آید.
 *
 * @return list<string>
 */
function hodima_seo_advisor_crawler_quick_seed( int $since ): array {

	global $wpdb;

	$seed  = [];
	$known = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', hodima_seo_advisor_table( 'crawl_pages' ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$limit = max( HODIMA_SEO_ADVISOR_CRAWLER_QUICK_TOP_MIN, min( HODIMA_SEO_ADVISOR_CRAWLER_QUICK_TOP_MAX, (int) ceil( $known * HODIMA_SEO_ADVISOR_CRAWLER_QUICK_TOP_SHARE ) ) );

	foreach ( (array) $wpdb->get_col( $wpdb->prepare( 'SELECT path FROM %i WHERE impressions > 0 ORDER BY impressions DESC LIMIT %d', hodima_seo_advisor_table( 'pages' ), $limit ) ) as $path ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$seed[] = 'u:' . (string) $path;
	}

	$types = array_values( array_diff( array_keys( get_post_types( [ 'public' => true ] ) ), [ 'attachment' ] ) );
	if ( $types ) {
		$ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_modified_gmt >= %s AND post_type IN (" . implode( ',', array_fill( 0, count( $types ), '%s' ) ) . ') ORDER BY ID LIMIT %d', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- placeholderهای هم‌اندازه
				...array_merge( [ gmdate( 'Y-m-d H:i:s', max( 0, $since - HOUR_IN_SECONDS ) ) ], $types, [ HODIMA_SEO_ADVISOR_CRAWLER_MAX_PAGES ] )
			)
		);
		foreach ( $ids as $id ) {
			$seed[] = 'p:' . (int) $id;
		}
	}

	return $seed;
}

/**
 * دور سررسید: `full` (دور کامل)، `quick` (دور سریع روزانه) یا رشته خالی (هنوز وقتش نیست).
 *
 * @param array<string, mixed> $state
 * @param array<string, mixed> $settings
 */
function hodima_seo_advisor_crawler_due_mode( array $state, array $settings ): string {

	$now = time();

	if ( 0 === $state['finished'] || $now - $state['full_at'] >= $settings['interval'] * DAY_IN_SECONDS - HOUR_IN_SECONDS ) {
		return 'full';
	}

	return (bool) apply_filters( 'hodima_seo_advisor_crawler_quick', true ) && $now - $state['finished'] >= DAY_IN_SECONDS - HOUR_IN_SECONDS ? 'quick' : '';
}

/**
 * خزش تازه را شروع می‌کند: صف با صفحه اصلی، و همه نوشته‌ها و دسته‌های عمومی منتشرشده برای پیدا شدن صفحه‌های بی‌لینک.
 */
function hodima_seo_advisor_crawler_start( string $mode = 'full' ): void {

	global $wpdb;

	hodima_seo_advisor_ensure_tables();

	$mode = 'quick' === $mode ? 'quick' : 'full';
	$old  = hodima_seo_advisor_crawler_state();

	if ( 'quick' === $mode ) {
		hodima_seo_advisor_crawler_save( hodima_seo_advisor_crawler_new_state( $old, 'quick', hodima_seo_advisor_crawler_quick_seed( $old['finished'] ) ) );
		return;
	}

	$types = array_values( array_diff( array_keys( get_post_types( [ 'public' => true ] ) ), [ 'attachment' ] ) );
	$seed  = [];

	if ( $types ) {
		$ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN (" . implode( ',', array_fill( 0, count( $types ), '%s' ) ) . ') ORDER BY ID LIMIT %d', // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- جدول وردپرس و placeholderهای هم‌اندازه
				...array_merge( $types, [ HODIMA_SEO_ADVISOR_CRAWLER_MAX_PAGES ] )
			)
		);
		foreach ( $ids as $id ) {
			$seed[] = 'p:' . (int) $id;
		}
	}

	foreach ( get_taxonomies( [ 'public' => true ] ) as $taxonomy ) {
		$terms = get_terms( [ 'taxonomy' => $taxonomy, 'hide_empty' => true, 'fields' => 'ids', 'number' => 2000 ] );
		foreach ( is_wp_error( $terms ) ? [] : $terms as $term_id ) {
			$seed[] = 't:' . (int) $term_id . ':' . $taxonomy;
		}
	}

	// فایل‌های نقشه سایت اول خوانده می‌شوند: هم آدرس‌های نقشه برای سنجش، هم کمک به پیدا شدن صفحه‌های بی‌لینک
	$maps = [];
	// همان منطق robots.txt: سایت‌مپ هدیما (sitemap.xml)؛ وگرنه اگر خاموش است و سایت‌مپ وردپرس روشن، wp-sitemap.xml (SEO 3.4.7)
	$map_root = '1' !== (string) get_option( 'hodima_sitemap_status', '1' ) && function_exists( 'wp_sitemaps_get_server' ) && wp_sitemaps_get_server()->sitemaps_enabled() ? home_url( '/wp-sitemap.xml' ) : home_url( '/sitemap.xml' );
	foreach ( (array) apply_filters( 'hodima_seo_advisor_sitemap_roots', [ $map_root ] ) as $root ) {
		$maps[] = 'm:' . (string) $root;
	}

	$state = hodima_seo_advisor_crawler_new_state( $old, 'full', array_merge( $maps, $seed ) );

	hodima_seo_advisor_crawler_save( $state );
}

/**
 * یک فایل نقشه سایت را می‌خواند: فهرست نقشه‌ها (sitemapindex) فایل‌های زیرمجموعه را به seed می‌افزاید و فهرست آدرس
 * (urlset) آدرس‌های نقشه را برای سنجش ثبت و به صف خزش می‌دهد. فقط آدرس همین سایت؛ XML بدون بارگیری موجودیت بیرونی.
 *
 * @param array<string, mixed> $state
 * @return int|null زمان پاسخ (میلی‌ثانیه) یا null اگر اتصال شکست خورد
 */
function hodima_seo_advisor_crawler_sitemap( string $url, array &$state ): ?int {

	if ( $state['maps'] >= HODIMA_SEO_ADVISOR_CRAWLER_MAX_MAPS || null === hodima_seo_advisor_path_key( $url ) ) {
		return 0;
	}

	++$state['maps'];
	$fetched = hodima_seo_advisor_crawler_fetch( $url );

	if ( null === $fetched ) {
		return null;
	}

	// SEO 3.5.2: فایل نقشه که ریدایرکت می‌دهد (مثلا ۳۰۱ به آدرس با «/» آخر؛ وردپرس برای نقشه ما هم این کار را می‌کرد) تا ۲ پله
	// دنبال می‌شود. تا ۳.۵.1 پاسخ ۳۰۱ «نقشه نیست» حساب می‌شد و ایرادهای نقشه سایت هرگز ساخته نمی‌شد. ریدایرکت و خطا ثبت می‌شود
	// تا دستار «نقشه سایت ریدایرکت می‌دهد» را به ادمین بگوید.
	$origin = (string) hodima_seo_advisor_path_key( $url );
	$hops   = 0;
	$target = '';
	$first  = 0;

	while ( $fetched['status'] >= 300 && $fetched['status'] < 400 && $hops < 2 ) {
		$next = hodima_seo_advisor_crawler_resolve( $fetched['location'], $url );

		if ( null === $next || null === hodima_seo_advisor_path_key( $next ) ) {
			break; // ریدایرکت به بیرون از سایت یا نامعتبر: دنبال نمی‌شود
		}

		$first = 0 === $hops ? $fetched['status'] : $first;
		$again = hodima_seo_advisor_crawler_fetch( $next );

		if ( null === $again ) {
			return null;
		}

		++$hops;
		$url     = $next;
		$target  = (string) hodima_seo_advisor_path_key( $next );
		$fetched = $again;
	}

	if ( $hops > 0 ) {
		hodima_seo_advisor_crawler_sitemap_problem( $state, [ 'u' => $origin, 's' => $first, 't' => $target, 'k' => 'redirect' ] );
	}

	if ( 200 !== $fetched['status'] || '' === $fetched['body'] ) {
		if ( 200 !== $fetched['status'] ) {
			hodima_seo_advisor_crawler_sitemap_problem( $state, [ 'u' => '' !== $target ? $target : $origin, 's' => $fetched['status'], 't' => '', 'k' => 'error' ] );
		}
		return $fetched['ms'];
	}

	$previous = libxml_use_internal_errors( true );
	$dom      = new DOMDocument();
	$loaded   = $dom->loadXML( $fetched['body'], LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR );
	libxml_clear_errors();
	libxml_use_internal_errors( $previous );

	if ( ! $loaded || null === $dom->documentElement ) {
		return $fetched['ms'];
	}

	$xpath = new DOMXPath( $dom );
	$root  = strtolower( $dom->documentElement->localName );

	if ( 'sitemapindex' === $root ) {
		$children = [];
		$nodes    = $xpath->query( "//*[local-name()='sitemap']/*[local-name()='loc']" );
		foreach ( false === $nodes ? [] : $nodes as $node ) {
			$children[] = 'm:' . trim( $node->textContent );
		}
		$state['seed'] = array_merge( $children, $state['seed'] ); // فایل‌های زیرمجموعه پیش از نوشته‌ها
	} elseif ( 'urlset' === $root ) {
		$nodes = $xpath->query( "//*[local-name()='url']/*[local-name()='loc']" );
		foreach ( false === $nodes ? [] : $nodes as $node ) {
			$loc = trim( $node->textContent );
			$key = hodima_seo_advisor_path_key( $loc );

			if ( null !== $key && ! hodima_seo_advisor_crawler_should_skip( $key ) && count( $state['sitemap'] ) < HODIMA_SEO_ADVISOR_CRAWLER_MAX_PAGES ) {
				$state['sitemap'][ $key ] = 1;
				hodima_seo_advisor_crawler_enqueue( $state, $loc );
			}
		}
	}

	return $fetched['ms'];
}

/**
 * مشکل‌های فایل‌های نقشه سایت را تمیز و تا ۲۰ مورد نگه می‌دارد.
 *
 * @param mixed $raw
 * @return list<array{u: string, s: int, t: string, k: string}>
 */
function hodima_seo_advisor_sitemap_problems_clean( mixed $raw ): array {

	$out = [];

	foreach ( is_array( $raw ) ? $raw : [] as $item ) {
		if ( is_array( $item ) && isset( $item['u'], $item['k'] ) && in_array( $item['k'], [ 'redirect', 'error' ], true ) ) {
			$out[] = [ 'u' => mb_substr( (string) $item['u'], 0, 191 ), 's' => (int) ( $item['s'] ?? 0 ), 't' => mb_substr( (string) ( $item['t'] ?? '' ), 0, 191 ), 'k' => (string) $item['k'] ];
		}
	}

	return array_slice( $out, 0, 20 );
}

/**
 * یک مشکل فایل نقشه را به حالت خزش می‌افزاید (هر آدرس و نوع یک بار).
 *
 * @param array<string, mixed>                            $state
 * @param array{u: string, s: int, t: string, k: string}  $problem
 */
function hodima_seo_advisor_crawler_sitemap_problem( array &$state, array $problem ): void {

	$list = hodima_seo_advisor_sitemap_problems_clean( $state['sitemap_problems'] ?? [] );

	foreach ( $list as $known ) {
		if ( $known['u'] === $problem['u'] && $known['k'] === $problem['k'] ) {
			return;
		}
	}

	$list[]                     = $problem;
	$state['sitemap_problems']  = hodima_seo_advisor_sitemap_problems_clean( $list );
}

/**
 * خلاصه نقشه سایت دور کامل آخر: زمان خواندن، شمار آدرس و فایل، و مشکل‌های فایل‌ها (ریدایرکت/خطا).
 *
 * @return array{fetched: int, urls: int, files: int, problems: list<array{u: string, s: int, t: string, k: string}>}
 */
function hodima_seo_advisor_sitemap_info(): array {

	$saved = get_option( HODIMA_SEO_ADVISOR_SITEMAP_OPTION, [] );
	$saved = is_array( $saved ) ? $saved : [];

	return [
		'fetched'  => (int) ( $saved['fetched'] ?? 0 ),
		'urls'     => count( (array) ( $saved['paths'] ?? [] ) ),
		'files'    => (int) ( $saved['maps'] ?? 0 ),
		'problems' => hodima_seo_advisor_sitemap_problems_clean( $saved['problems'] ?? [] ),
	];
}

/**
 * آدرس‌های نقشه سایت دور آخر خزنده (مسیر ← true)؛ خالی = نقشه‌ای خوانده نشده.
 *
 * @return array<string, true>
 */
function hodima_seo_advisor_sitemap_paths(): array {

	$saved = get_option( HODIMA_SEO_ADVISOR_SITEMAP_OPTION, [] );

	return array_fill_keys( array_map( 'strval', is_array( $saved ) ? (array) ( $saved['paths'] ?? [] ) : [] ), true );
}

/**
 * یک صفحه را همین الان دوباره می‌خواند و ردیفش (با لینک‌هایش) را به‌روز می‌کند؛ آمار لینک ورودی و عمق همان می‌ماند تا
 * دور بعد. برای سنجش فوری بعد از «انجام دادم» (advisor-marks.php). false = صفحه در خزش نیست یا خواندن نشد.
 */
function hodima_seo_advisor_crawler_recrawl( string $path ): bool {

	$fetch = hodima_seo_advisor_crawler_normalize( home_url( implode( '/', array_map( 'rawurlencode', explode( '/', $path ) ) ) ) );

	if ( null === $fetch || null === hodima_seo_advisor_crawl_row( $path ) ) {
		return false;
	}

	$state = [ 'seen' => array_fill( 0, HODIMA_SEO_ADVISOR_CRAWLER_MAX_PAGES, 1 ), 'queue' => [] ]; // پر = لینک تازه‌ای به صف نمی‌رود (دور واقعی نیست)

	$ms = hodima_seo_advisor_crawler_process( $fetch, $state, true );
	hodima_seo_advisor_crawl_map( true );

	return null !== $ms && empty( $state['throttle'] ); // ۴۲۹: خوانده نشد (ردیف قبلی دست‌نخورده)
}

/**
 * یک نمونه از seed (p:ID، t:ID:taxonomy یا u:مسیر) ← آدرس یا null.
 */
function hodima_seo_advisor_crawler_seed_url( string $item ): ?string {

	if ( str_starts_with( $item, 'u:' ) ) { // مسیر ذخیره‌شده (دور سریع)
		// مسیر ذخیره‌شده رمزگشایی‌شده است (آدرس فارسی)؛ برای درخواست HTTP هر بخش دوباره کد می‌شود (SEO 3.4.7: مثل live_fetch)
		return strlen( $item ) > 2 ? hodima_seo_advisor_url( implode( '/', array_map( 'rawurlencode', explode( '/', substr( $item, 2 ) ) ) ) ) : null;
	}

	$bits = explode( ':', $item );

	if ( 'p' === $bits[0] && isset( $bits[1] ) ) {
		$url = get_permalink( (int) $bits[1] );
		return false === $url ? null : $url;
	}

	if ( 't' === $bits[0] && isset( $bits[1], $bits[2] ) ) {
		$url = get_term_link( (int) $bits[1], $bits[2] );
		return is_wp_error( $url ) ? null : $url;
	}

	return null;
}

/**
 * پایان دور: لینک‌های ورودی، لینک متنی و عمق از جدول لینک‌ها، حذف آدرس‌هایی که در این دور دیده نشدند،
 * ثبت وضعیت برای صفحه «ایرادهای فنی»، سنجش کارهای ایراد فنی و ساخت دوباره پیشنهادها.
 *
 * @param array<string, mixed> $state
 */
function hodima_seo_advisor_crawler_finalize( array &$state ): void {

	global $wpdb;

	$page_t = hodima_seo_advisor_table( 'crawl_pages' );
	$link_t = hodima_seo_advisor_table( 'crawl_links' );

	// آدرس‌های دور قبل که این بار دیده نشدند (حذف‌شده، تغییر آدرس)
	$known = array_map( 'strval', (array) $wpdb->get_col( $wpdb->prepare( 'SELECT path FROM %i', $page_t ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$stale = array_values( array_filter( $known, static fn( string $path ): bool => ! isset( $state['seen'][ $path ] ) ) );

	// دور سریع فقط بخشی از صفحه‌ها را دیده؛ «ندیده‌شده» یعنی «در این دور نخزیدیم» نه «حذف شده»: چیزی پاک نمی‌شود (SEO 3.3.8)
	$quick = 'quick' === ( $state['mode'] ?? '' );

	// دور به سقف رسید: صفحه‌ای که دیده نشد شاید فقط بالای سقف مانده؛ هیچ ردیفی پاک نمی‌شود (ردیف صفحه واقعا حذف‌شده می‌ماند و هشدار نشان داده می‌شود)
	if ( $quick || ! empty( $state['capped'] ) ) {
		$stale = [];
	}

	foreach ( array_chunk( $stale, 200 ) as $chunk ) {
		$marks = implode( ',', array_fill( 0, count( $chunk ), '%s' ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE path IN ({$marks})", $page_t, ...$chunk ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- placeholderهای هم‌اندازه
		$wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE source_path IN ({$marks})", $link_t, ...$chunk ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- placeholderهای هم‌اندازه
	}

	// لینک ورودی یکتا، لینک متنی (محتوا و follow) و عمق (کمترین کلیک از صفحه اصلی). SEO 3.3.8: شمارش و پیمایش در خود پایگاه داده
	// (یک ردیف برای هر صفحه) نه با بار کردن همه لینک‌ها در حافظه PHP؛ با ۳٬۰۰۰ صفحه و ۱۲۰٬۰۰۰ لینک پیک حافظه ≈ ۹۱ مگابایت بود و
	// روی سایت بزرگ‌تر یا هاست ۱۲۸ مگابایتی دور را نیمه‌کاره می‌کشت.
	$wpdb->query( $wpdb->prepare( 'UPDATE %i SET inlinks = 0, text_links = 0, depth = 0', $page_t ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

	$by_count = [];
	foreach ( (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			"SELECT dest_path, COUNT(DISTINCT source_path), COUNT(DISTINCT CASE WHEN link_position = 'content' AND is_follow = 1 THEN source_path END) FROM %i GROUP BY dest_path",
			$link_t
		),
		ARRAY_N
	) as $count ) {
		$by_count[ (int) $count[1] . '|' . (int) $count[2] ][] = (string) $count[0];
	}

	foreach ( $by_count as $pair => $paths ) {
		[ $inlinks, $text_links ] = array_map( 'intval', explode( '|', (string) $pair ) );
		foreach ( array_chunk( $paths, 200 ) as $chunk ) {
			$marks = implode( ',', array_fill( 0, count( $chunk ), '%s' ) );
			$wpdb->query( $wpdb->prepare( "UPDATE %i SET inlinks = %d, text_links = %d WHERE path IN ({$marks})", $page_t, $inlinks, $text_links, ...$chunk ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- placeholderهای هم‌اندازه
		}
	}
	unset( $by_count );

	// عمق: پیمایش سطح‌به‌سطح؛ هر سطح یک یا چند پرسش با فهرست مبدأها (حافظه فقط به اندازه شمار صفحه‌ها)
	$depth    = [ '/' => 0 ];
	$frontier = [ '/' ];
	$level    = 0;

	while ( $frontier ) {
		++$level;
		$next = [];
		foreach ( array_chunk( $frontier, 300 ) as $chunk ) {
			$marks = implode( ',', array_fill( 0, count( $chunk ), '%s' ) );
			foreach ( (array) $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT dest_path FROM %i WHERE source_path IN ({$marks})", $link_t, ...$chunk ) ) as $to ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- placeholderهای هم‌اندازه
				$to = (string) $to;
				if ( ! isset( $depth[ $to ] ) ) {
					$depth[ $to ] = $level;
					$next[]       = $to;
				}
			}
		}
		$frontier = $next;
	}

	$by_depth = [];
	foreach ( $depth as $path => $level ) {
		$by_depth[ $level ][] = (string) $path;
	}

	foreach ( $by_depth as $level => $paths ) {
		foreach ( array_chunk( $paths, 200 ) as $chunk ) {
			$marks = implode( ',', array_fill( 0, count( $chunk ), '%s' ) );
			$wpdb->query( $wpdb->prepare( "UPDATE %i SET depth = %d WHERE path IN ({$marks})", $page_t, min( 65000, (int) $level ), ...$chunk ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- placeholderهای هم‌اندازه
		}
	}

	// صفحه‌ای که از صفحه اصلی با هیچ مسیر لینکی نمی‌رسیم عمق 0 می‌ماند و با صفحه اصلی یکی دیده می‌شد؛ نشانه جدا می‌گیرد
	// (SEO 3.3.5). فقط وقتی صفحه اصلی خزیده شد و به جای دیگری لینک داد؛ وگرنه خزش ناقص همه را «جزیره» می‌کرد.
	if ( count( $depth ) > 1 && 200 === (int) $wpdb->get_var( $wpdb->prepare( 'SELECT status FROM %i WHERE path = %s', $page_t, '/' ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( 'UPDATE %i SET depth = %d WHERE depth = 0 AND path <> %s', $page_t, HODIMA_SEO_ADVISOR_CRAWL_UNREACHABLE, '/' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	$pages = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $page_t ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$links = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $link_t ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

	hodima_seo_advisor_crawl_map( true );
	update_option( HODIMA_SEO_ADVISOR_CRAWL_OPTION, [ 'imported' => time(), 'pages' => $pages, 'links' => $links, 'source' => 'crawler' ], false );

	if ( ! $quick ) { // دور سریع نقشه سایت را نخوانده: نقشه و ایرادهای نقشه دور کامل می‌ماند
		$problems = hodima_seo_advisor_sitemap_problems_clean( $state['sitemap_problems'] ?? [] );

		if ( $state['sitemap'] || $problems ) {
			update_option( HODIMA_SEO_ADVISOR_SITEMAP_OPTION, [ 'fetched' => time(), 'paths' => array_map( 'strval', array_keys( $state['sitemap'] ) ), 'maps' => (int) ( $state['maps'] ?? 0 ), 'problems' => $problems ], false ); // مشکل‌ها حتی وقتی هیچ آدرسی خوانده نشد می‌ماند تا علتش دیده شود (SEO 3.5.2)
		} else {
			delete_option( HODIMA_SEO_ADVISOR_SITEMAP_OPTION ); // نقشه‌ای خوانده نشد: ایرادهای نقشه سایت ساخته نمی‌شوند
		}
	}

	$state['status']   = 'idle';
	$state['manual']   = false;
	$state['finished'] = time();
	$state['full_at']  = $quick ? (int) $state['full_at'] : time();
	$state['mode']     = 'full';
	$state['capped']   = ! empty( $state['capped'] );
	$state['pages']    = $pages;
	$state['links']    = $links;
	$state['seed']     = [];
	$state['queue']    = [];
	$state['seen']     = [];
	$state['sitemap']  = [];
	$state['recent']   = [];

	hodima_seo_advisor_tasks_sync_crawl();
	hodima_seo_advisor_score_record();
	hodima_seo_advisor_request_reanalyze();
	hodima_seo_advisor_pipeline_schedule( 60 );
}

/**
 * یک نوبت خزش (کرون هر ۵ دقیقه). بیرون از پنجره، خاموش، در مکث یا در حال اجرای نوبت دیگر ← بی‌کار برمی‌گردد.
 *
 * @param bool $force true = بدون پنجره زمانی و فاصله دورها (آزمون)
 * @return array{ran: int, finished: bool}
 */
function hodima_seo_advisor_crawler_tick( bool $force = false ): array {

	$none     = [ 'ran' => 0, 'finished' => false ];
	$settings = hodima_seo_advisor_crawler_settings();
	$state    = hodima_seo_advisor_crawler_state();
	$manual   = $state['manual'] && 'running' === $state['status']; // «خزش همین حالا»: بی‌توجه به ساعت و روشن بودن

	if ( ! $force && ! $manual && ( ! $settings['enabled'] || ! hodima_seo_advisor_crawler_in_window() ) ) {
		return $none;
	}

	if ( $state['pause_until'] > time() ) {
		return $none;
	}

	$mode = 'full';

	if ( 'idle' === $state['status'] ) {
		$due = hodima_seo_advisor_crawler_due_mode( $state, $settings ); // دور کامل یا سریع روزانه (SEO 3.3.8)
		if ( '' === $due && ! $force ) {
			return $none;
		}
		$mode = '' === $due ? 'full' : $due;
	}

	if ( ! hodima_seo_advisor_lock( HODIMA_SEO_ADVISOR_CRAWLER_LOCK ) ) {
		return $none;
	}

	if ( 'idle' === $state['status'] ) {
		hodima_seo_advisor_crawler_start( $mode );
		$state = hodima_seo_advisor_crawler_state();
	}

	if ( function_exists( 'set_time_limit' ) ) {
		set_time_limit( HODIMA_SEO_ADVISOR_CRAWLER_BUDGET + 40 );
	}

	$deadline = microtime( true ) + HODIMA_SEO_ADVISOR_CRAWLER_BUDGET;
	$ran      = 0;
	$finished = false;

	while ( microtime( true ) < $deadline ) {

		if ( $state['seed'] && str_starts_with( $state['seed'][0], 'm:' ) ) {
			$ms = hodima_seo_advisor_crawler_sitemap( substr( (string) array_shift( $state['seed'] ), 2 ), $state ); // فایل نقشه سایت یک درخواست است (با همان فاصله)
		} elseif ( $state['seed'] ) {
			$count = 0;
			while ( $count < 200 && isset( $state['seed'][ $count ] ) && ! str_starts_with( $state['seed'][ $count ], 'm:' ) ) {
				++$count;
			}
			foreach ( array_splice( $state['seed'], 0, $count ) as $item ) {
				$url = hodima_seo_advisor_crawler_seed_url( $item );
				if ( null !== $url ) {
					hodima_seo_advisor_crawler_enqueue( $state, $url, true );
				}
			}
			continue;
		} else {
			$url = array_shift( $state['queue'] );

			if ( null === $url ) {
				hodima_seo_advisor_crawler_finalize( $state );
				$finished = true;
				break;
			}

			// لینک ورودی و عمق دور قبل می‌ماند تا finalize همه را از نو بشمارد (SEO 3.7.4). باگ قبلی: هر صفحه خوانده‌شده با
			// «لینک ورودی ۰» نوشته می‌شد و وسط خزش صدها ایراد ساختگی «بدون لینک ورودی» و افت نمره نشان داده می‌شد.
			$ms = hodima_seo_advisor_crawler_process( $url, $state, true );

			if ( ! empty( $state['throttle'] ) ) { // ۴۲۹/۵۰۳ موقت: همین آدرس بعد از مکث دوباره (SEO 3.8.7)
				$state['pause_until'] = time() + (int) $state['throttle'];
				$state['throttled']   = time();
				unset( $state['throttle'] );
				$state['recent'] = [];
				break;
			}
		}

		++$ran;
		++$state['done'];

		if ( null === $ms ) { // خطای اتصال یا مهلت: آدرس رد می‌شود و پنج خطای پی‌درپی = مکث نیم‌ساعته
			++$state['errors'];
			$state['recent'][] = HODIMA_SEO_ADVISOR_CRAWLER_SLOW_MS * 2;
		} else {
			$state['errors']   = 0;
			$state['recent'][] = $ms;
		}

		$state['recent'] = array_slice( $state['recent'], -3 );

		if ( $state['errors'] >= 5 || ( 3 === count( $state['recent'] ) && array_sum( $state['recent'] ) / 3 > HODIMA_SEO_ADVISOR_CRAWLER_SLOW_MS ) ) {
			$state['pause_until'] = time() + HODIMA_SEO_ADVISOR_CRAWLER_PAUSE;
			$state['throttled']   = 0; // این مکث از کندی است، نه ۴۲۹
			$state['recent']      = [];
			$state['errors']      = 0;
			break;
		}

		if ( 0 === $ran % 5 ) {
			hodima_seo_advisor_crawler_save( $state );
		}

		$wait = max( (float) apply_filters( 'hodima_seo_advisor_crawler_delay', HODIMA_SEO_ADVISOR_CRAWLER_DELAY ), min( 8.0, 2 * ( (int) $ms / 1000 ) ) ); // سایت کندتر ← فاصله بیشتر؛ فیلتر فقط برای آزمون
		if ( microtime( true ) + $wait >= $deadline ) {
			break;
		}
		if ( $wait > 0 ) {
			usleep( (int) round( $wait * 1000000 ) );
		}
	}

	hodima_seo_advisor_crawler_save( $state );
	hodima_seo_advisor_unlock( HODIMA_SEO_ADVISOR_CRAWLER_LOCK );

	if ( $state['manual'] && 'running' === $state['status'] && $state['pause_until'] <= time() ) {
		wp_schedule_single_event( time() + 10, HODIMA_SEO_ADVISOR_CRAWLER_CRON, [ $state['done'] ] ); // ادامه زنجیره بدون انتظار برای کرون ۵ دقیقه‌ای (آرگومان متفاوت: وردپرس رویداد هم‌نام نزدیک را تکراری می‌داند)
	}

	return [ 'ran' => $ran, 'finished' => $finished ];
}

/**
 * «خزش همین حالا»: دور تازه (یا ادامه دور در جریان) بی‌توجه به ساعت خزش و روشن بودن؛ مکث کندی سایت برداشته می‌شود
 * ولی همان سرعت آهسته می‌ماند. پیش‌رفتن با کرون (زنجیره ۱۰ ثانیه‌ای) و گام‌های JS صفحه پیشخوان.
 */
function hodima_seo_advisor_crawler_start_now(): void {

	if ( 'running' !== hodima_seo_advisor_crawler_state()['status'] ) {
		hodima_seo_advisor_crawler_start();
	}

	$state                = hodima_seo_advisor_crawler_state();
	$state['manual']      = true;
	$state['pause_until'] = 0;
	hodima_seo_advisor_crawler_save( $state );

	wp_schedule_single_event( time() + 5, HODIMA_SEO_ADVISOR_CRAWLER_CRON, [ 0 ] );
}

/**
 * خزش هدفمند (SEO 3.4.9): فقط همین صفحه‌ها دوباره خوانده می‌شوند (دکمه «بازخوانی این N صفحه» کار کلی)، نه کل سایت.
 * همان دور «سریع» است: چیزی پاک نمی‌شود و نقشه سایت خوانده نمی‌شود؛ فقط ردیف و لینک این صفحه‌ها تازه می‌شود و در پایان
 * لینک ورودی، نمره و پیشنهادها دوباره ساخته می‌شوند. بی‌توجه به ساعت خزش (مثل «خزش همین حالا»).
 *
 * @param list<string> $paths مسیر یکسان‌شده صفحه‌ها
 * @return bool false = دوری در جریان است یا مسیری نیست (چیزی شروع نشد)
 */
function hodima_seo_advisor_crawler_start_paths( array $paths ): bool {

	$paths = array_values( array_unique( array_filter( array_map( 'strval', $paths ), static fn( string $path ): bool => str_starts_with( $path, '/' ) ) ) );

	if ( ! $paths || 'running' === hodima_seo_advisor_crawler_state()['status'] ) {
		return false;
	}

	hodima_seo_advisor_ensure_tables();

	$state                = hodima_seo_advisor_crawler_new_state( hodima_seo_advisor_crawler_state(), 'quick', array_map( static fn( string $path ): string => 'u:' . $path, $paths ) );
	$state['manual']      = true;
	$state['pause_until'] = 0;
	hodima_seo_advisor_crawler_save( $state );

	wp_schedule_single_event( time() + 5, HODIMA_SEO_ADVISOR_CRAWLER_CRON, [ 0 ] );

	return true;
}

/** میانگین زمان هر صفحه در خزش (ثانیه): فاصله ۱ ثانیه + زمان پاسخ؛ فقط برای «حدود N دقیقه مانده». */
const HODIMA_SEO_ADVISOR_CRAWLER_ETA_SECONDS = 1.5;

/**
 * زمان تقریبی خزش N صفحه برای نمایش: «حدود N دقیقه» یا «کمتر از یک دقیقه».
 */
function hodima_seo_advisor_crawler_duration( int $pages ): string {

	$seconds = max( 0, $pages ) * HODIMA_SEO_ADVISOR_CRAWLER_ETA_SECONDS;

	return $seconds < 60 ? 'کمتر از یک دقیقه' : sprintf( 'حدود %s دقیقه', number_format_i18n( (int) ceil( $seconds / 60 ) ) );
}

/**
 * برآورد زمان مانده خزش در جریان («حدود N دقیقه مانده»)؛ خالی = چیزی در صف نیست.
 *
 * @param array{queued: int, seeding: int} $status hodima_seo_advisor_crawler_status()
 */
function hodima_seo_advisor_crawler_eta( array $status ): string {

	$left = (int) $status['queued'] + (int) $status['seeding'];

	return $left > 0 ? hodima_seo_advisor_crawler_duration( $left ) . ' مانده' : '';
}

/**
 * وضعیت برای نمایش: off، waiting (منتظر پنجره یا موعد)، running، paused.
 *
 * @return array{state: string, manual: bool, done: int, queued: int, seeding: int, finished: int, pause_until: int, throttled: bool, pages: int, capped: bool, mode: string}
 */
function hodima_seo_advisor_crawler_status(): array {

	$settings = hodima_seo_advisor_crawler_settings();
	$state    = hodima_seo_advisor_crawler_state();
	$label    = 'waiting';

	if ( $state['pause_until'] > time() && 'running' === $state['status'] ) {
		$label = 'paused';
	} elseif ( 'running' === $state['status'] && ( $state['manual'] || $settings['enabled'] ) ) {
		$label = 'running';
	} elseif ( ! $settings['enabled'] ) {
		$label = 'off';
	} elseif ( $state['pause_until'] > time() ) {
		$label = 'paused';
	}

	return [
		'state'       => $label,
		'manual'      => $state['manual'],
		'done'        => $state['done'],
		'queued'      => count( $state['queue'] ),
		'seeding'     => count( $state['seed'] ),
		'finished'    => $state['finished'],
		'pause_until' => $state['pause_until'],
		'throttled'   => $state['throttled'] > 0 && $state['pause_until'] > time(), // مکث فعلی از «درخواست زیاد است» است (مکث کندی throttled را صفر می‌کند)
		'pages'       => $state['pages'],
		'capped'      => $state['capped'],
		'mode'        => $state['mode'],
	];
}

/* ── کرون ── */

add_filter(
	'cron_schedules', // phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval -- هر نوبت چند ثانیه و فقط در پنجره شبانه کار می‌کند؛ فاصله کم برای تمام‌شدن دور در چند ساعت لازم است
	static function ( array $schedules ): array {
		$schedules['hodima_five_minutes'] = [ 'interval' => 300, 'display' => 'هر ۵ دقیقه' ];
		return $schedules;
	}
);

add_action(
	HODIMA_SEO_ADVISOR_CRAWLER_CRON,
	static function ( int $step = 0 ): void { // $step فقط برای یکتا کردن رویدادهای زنجیره «خزش همین حالا» است
		unset( $step );
		hodima_seo_advisor_crawler_tick();
	},
	10,
	1
);

add_action(
	'init',
	static function (): void {
		$scheduled = false !== wp_get_schedule( HODIMA_SEO_ADVISOR_CRAWLER_CRON ); // فقط رویداد تکرارشونده (بدون آرگومان)
		$enabled   = hodima_seo_advisor_crawler_settings()['enabled'];
		if ( $enabled && ! $scheduled ) {
			wp_schedule_event( time() + 300, 'hodima_five_minutes', HODIMA_SEO_ADVISOR_CRAWLER_CRON );
		} elseif ( ! $enabled && $scheduled && ! hodima_seo_advisor_crawler_state()['manual'] ) {
			wp_clear_scheduled_hook( HODIMA_SEO_ADVISOR_CRAWLER_CRON ); // خاموش شد: رویداد بی‌کار نماند (مگر «خزش همین حالا» در جریان است)
		}
	}
);
