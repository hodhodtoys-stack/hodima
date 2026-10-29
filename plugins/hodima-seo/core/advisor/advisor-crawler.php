<?php
/**
 * ماژول «دستار سئو» — خزنده شبانه (SEO 3.1.4)
 * Path: core/advisor/advisor-crawler.php
 *
 * خود دستار صفحه‌های سایت را می‌خواند (جای فایل Screaming Frog): با کرون هر ۵ دقیقه یک دسته کوچک، فقط در پنجره
 * شبانه (پیش‌فرض ۲ تا ۸ صبح به وقت سایت). نتیجه در همان جدول‌های خزش (crawl_pages، crawl_links) می‌رود؛ پس همه
 * ایرادها (advisor-crawl.php) بدون تغییر کار می‌کنند. خزنده فقط می‌خواند؛ چیزی را در سایت عوض نمی‌کند.
 *
 * اصل‌های سبک‌ماندن سایت: یک درخواست در هر لحظه، فاصله دست‌کم ۱ ثانیه (دو برابر زمان پاسخ اگر سایت کند باشد)،
 * بودجه ۲۰ ثانیه برای هر نوبت، و اگر پاسخ‌ها کند شد (میانگین ۳ درخواست آخر بیش از ۳ ثانیه) نیم ساعت مکث.
 * هر چه خزیده شد همان لحظه ذخیره می‌شود و فقط آدرس‌هایی که در این دور دیده نشدند در پایان دور پاک می‌شوند؛
 * خطای وسط کار داده قبلی را خراب نمی‌کند.
 *
 * محدودیت: جاوااسکریپت اجرا نمی‌شود (لینک یا متنی که فقط با JS ساخته شود دیده نمی‌شود)؛ «جایگاه لینک»
 * (محتوا/منو/فوتر/کنار) از تگ‌ها و نام کلاس‌ها حدس زده می‌شود و ممکن است با Screaming Frog کمی فرق کند.
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
 * تنظیمات خزنده. ساعت شروع و پایان برابر = همه ساعت‌ها.
 *
 * @return array{enabled: bool, start: int, end: int, interval: int}
 */
function hodima_seo_advisor_crawler_settings(): array {

	$saved = get_option( HODIMA_SEO_ADVISOR_CRAWLER_SETTINGS, [] );
	$saved = is_array( $saved ) ? $saved : [];
	$hour  = static fn( mixed $value, int $fallback ): int => max( 0, min( 23, is_numeric( $value ) ? (int) $value : $fallback ) );
	$days  = (int) ( $saved['interval'] ?? 7 );

	return [
		'enabled'  => (bool) ( $saved['enabled'] ?? true ),
		'start'    => $hour( $saved['start'] ?? null, 2 ),
		'end'      => $hour( $saved['end'] ?? null, 8 ),
		'interval' => in_array( $days, HODIMA_SEO_ADVISOR_CRAWLER_INTERVALS, true ) ? $days : 7,
	];
}

/** الان (به وقت سایت) داخل پنجره خزش است؟ */
function hodima_seo_advisor_crawler_in_window( ?int $now = null ): bool {

	$s    = hodima_seo_advisor_crawler_settings();
	$hour = (int) wp_date( 'G', $now ?? time() );

	if ( $s['start'] === $s['end'] ) {
		return true;
	}

	return $s['start'] < $s['end'] ? ( $hour >= $s['start'] && $hour < $s['end'] ) : ( $hour >= $s['start'] || $hour < $s['end'] );
}

/**
 * وضعیت ذخیره‌شده خزش با پیش‌فرض‌ها.
 *
 * @return array{status: string, run: int, started: int, seed: list<string>, queue: list<string>, seen: array<string, int>, sitemap: array<string, int>, maps: int, done: int, errors: int, recent: list<int>, pause_until: int, finished: int, requested: bool, pages: int, links: int}
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
		'done'        => (int) ( $state['done'] ?? 0 ),
		'errors'      => (int) ( $state['errors'] ?? 0 ),
		'recent'      => array_values( array_map( 'intval', (array) ( $state['recent'] ?? [] ) ) ),
		'pause_until' => (int) ( $state['pause_until'] ?? 0 ),
		'finished'    => (int) ( $state['finished'] ?? 0 ),
		'requested'   => ! empty( $state['requested'] ),
		'pages'       => (int) ( $state['pages'] ?? 0 ),
		'links'       => (int) ( $state['links'] ?? 0 ),
	];
}

/**
 * @param array<string, mixed> $state
 */
function hodima_seo_advisor_crawler_save( array $state ): void {
	update_option( HODIMA_SEO_ADVISOR_CRAWLER_OPTION, $state, false );
}

/* ── آدرس‌ها ── */

/**
 * آدرس لینک (نسبی یا کامل) ← آدرس کامل نسبت به صفحه مبدأ؛ null = لینک غیرصفحه (mailto، #، javascript…).
 */
function hodima_seo_advisor_crawler_resolve( string $href, string $base ): ?string {

	$href = trim( $href );

	if ( '' === $href || str_starts_with( $href, '#' ) || 1 === preg_match( '#^(?:mailto|tel|javascript|data|sms|whatsapp|callto):#i', $href ) ) {
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

	return untrailingslashit( home_url() ) . ( '' === $path ? '/' : $path );
}

/**
 * آدرس را به صف اضافه می‌کند اگر در این دور هنوز دیده نشده.
 *
 * @param array<string, mixed> $state
 */
function hodima_seo_advisor_crawler_enqueue( array &$state, string $url ): void {

	$fetch = hodima_seo_advisor_crawler_normalize( $url );
	$key   = null === $fetch ? null : hodima_seo_advisor_path_key( $fetch );

	if ( null === $fetch || null === $key || isset( $state['seen'][ $key ] ) || count( $state['seen'] ) >= HODIMA_SEO_ADVISOR_CRAWLER_MAX_PAGES ) {
		return;
	}

	$state['seen'][ $key ] = 1;
	$state['queue'][]      = $fetch;
}

/* ── خواندن یک صفحه ── */

/**
 * جایگاه لینک از نیای آن: footer، navigation (nav/header/منو/مسیر راهنما)، aside (کنار/ویجت) یا content.
 */
function hodima_seo_advisor_crawler_link_position( DOMElement $link ): string {

	$found = [];

	for ( $node = $link->parentNode; $node instanceof DOMElement; $node = $node->parentNode ) {
		$tag = strtolower( $node->nodeName );

		if ( in_array( $tag, [ 'body', 'html' ], true ) ) {
			break;
		}

		$names = strtolower( $node->getAttribute( 'class' ) . ' ' . $node->getAttribute( 'id' ) );

		if ( 'footer' === $tag || 1 === preg_match( '/(?:^|[\s_-])(?:footer|colophon)(?:$|[\s_-])/', $names ) ) {
			$found['footer'] = true;
		} elseif ( 'nav' === $tag || 'header' === $tag || 1 === preg_match( '/(?:^|[\s_-])(?:nav|navbar|menu|breadcrumbs?)(?:$|[\s_-])/', $names ) ) {
			$found['navigation'] = true;
		} elseif ( 'aside' === $tag || 1 === preg_match( '/(?:^|[\s_-])(?:sidebar|widget)(?:$|[\s_-])/', $names ) ) {
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
 * HTML یک صفحه ← عنوان، متا، تیترها، robots، canonical و لینک‌های داخلی.
 *
 * @return array{title: string, meta: string, robots: string, canonical: string, h1: string, h1_count: int, h2: string, words: int, img_noalt: int, http_links: int, links: list<array{0: string, 1: string, 2: string, 3: int}>}
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

	$canonical = '';
	$can_list  = $xpath->query( "//link[{$lower( '@rel' )}='canonical']" );
	$can_node  = false === $can_list ? null : $can_list->item( 0 );
	if ( $can_node instanceof DOMElement ) {
		$canonical = trim( $can_node->getAttribute( 'href' ) );
	}

	$links      = [];
	$http_links = 0;
	$https_site = str_starts_with( home_url(), 'https://' );
	$all        = $xpath->query( '//a[@href]' );

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
		}

		$follow  = str_contains( strtolower( $a->getAttribute( 'rel' ) ), 'nofollow' ) ? 0 : 1;
		$links[] = [ $target, hodima_seo_advisor_crawler_link_position( $a ), mb_substr( $anchor, 0, 120 ), $follow ];
	}

	// تصویر محتوای صفحه بدون متن جایگزین (تزئینی با aria-hidden/role=presentation، تصویر ریز و جاگیر base64 حساب نمی‌شود)
	$img_noalt = 0;
	$images    = $xpath->query( '//img' );
	foreach ( false === $images ? [] : $images as $img ) {
		if ( ! $img instanceof DOMElement || 'content' !== hodima_seo_advisor_crawler_link_position( $img ) ) {
			continue;
		}
		$decor = 'true' === strtolower( $img->getAttribute( 'aria-hidden' ) ) || in_array( strtolower( $img->getAttribute( 'role' ) ), [ 'presentation', 'none' ], true );
		$tiny  = ( $img->hasAttribute( 'width' ) && (int) $img->getAttribute( 'width' ) <= 2 ) || str_starts_with( trim( $img->getAttribute( 'src' ) ), 'data:image/gif' );
		if ( ! $decor && ! $tiny && '' === $clean( $img->getAttribute( 'alt' ) ) ) {
			++$img_noalt;
		}
	}

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

	$h1_all = $xpath->query( '//h1' );

	return [
		'title'      => $first( $xpath, '/html/head/title' ),
		'meta'       => $metas( $xpath, 'description' )[0] ?? '',
		'robots'     => implode( ', ', $metas( $xpath, 'robots' ) ),
		'canonical'  => $canonical,
		'h1'         => $first( $xpath, '//h1' ),
		'h1_count'   => false === $h1_all ? 0 : $h1_all->length,
		'h2'         => implode( ' | ', $h2s ),
		'words'      => $words,
		'img_noalt'  => $img_noalt,
		'http_links' => $http_links,
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
	];
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
		'canonical'  => '' === $canonical ? '' : ( null === $can_path ? '!ext' : $can_path ),
	];
}

/**
 * یک آدرس صف را می‌خواند و ذخیره می‌کند؛ آدرس‌های داخلی تازه را به صف می‌افزاید.
 *
 * @param array<string, mixed> $state
 * @param bool                 $keep_stats لینک ورودی، لینک متنی، عمق و امتیاز ردیف قبلی حفظ شود (خزش دوباره یک صفحه، نه دور کامل)
 * @return int|null زمان پاسخ (میلی‌ثانیه) یا null اگر اتصال شکست خورد
 */
function hodima_seo_advisor_crawler_process( string $url, array &$state, bool $keep_stats = false ): ?int {

	global $wpdb;

	$key = hodima_seo_advisor_path_key( $url );

	if ( null === $key ) {
		return 0;
	}

	$fetched = hodima_seo_advisor_crawler_fetch( $url );

	if ( null === $fetched ) {
		return null;
	}

	$status = $fetched['status'];

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
		$unique = [];

		foreach ( $parsed['links'] as [ $target, $position, $anchor, $follow ] ) {
			$dest = hodima_seo_advisor_path_key( $target );

			if ( null === $dest || $dest === $key ) {
				continue;
			}

			$link_key = "{$dest}|{$position}|{$anchor}";

			if ( ! isset( $unique[ $link_key ] ) ) {
				$unique[ $link_key ] = true;
				$links[]             = [ $key, $dest, mb_substr( $position, 0, 16 ), $anchor, $follow ];
			}

			hodima_seo_advisor_crawler_enqueue( $state, $target );
		}
	}

	$row     = hodima_seo_advisor_crawler_page_row( $key, $status, $fetched['ms'], $parsed, $redirect, $fetched['x_robots'] );
	$columns = 'path, status, indexable, reason, title, title_len, meta_len, h1, h2, resp_ms, inlinks, text_links, depth, score, redirect_to, no_snippet, words, h1_count, img_noalt, http_links, canonical';
	$format  = '(%s,%d,%d,%s,%s,%d,%d,%s,%s,%d,%d,%d,%d,%f,%s,%d,%d,%d,%d,%d,%s)';
	$page_t  = hodima_seo_advisor_table( 'crawl_pages' );
	$link_t  = hodima_seo_advisor_table( 'crawl_links' );

	if ( $keep_stats ) {
		$old = $wpdb->get_row( $wpdb->prepare( 'SELECT inlinks, text_links, depth, score FROM %i WHERE path = %s', $page_t, $key ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( is_array( $old ) ) {
			$row = array_merge( $row, [ 'inlinks' => (int) $old['inlinks'], 'text_links' => (int) $old['text_links'], 'depth' => (int) $old['depth'], 'score' => (float) $old['score'] ] ); // جای کلیدها در ردیف عوض نمی‌شود (درج بر اساس ترتیب است)
		}
	}

	hodima_seo_advisor_transaction(
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

	return $fetched['ms'];
}

/* ── شروع، نوبت، پایان ── */

/**
 * خزش تازه را شروع می‌کند: صف با صفحه اصلی، و همه نوشته‌ها و دسته‌های عمومی منتشرشده برای پیدا شدن صفحه‌های بی‌لینک.
 */
function hodima_seo_advisor_crawler_start(): void {

	global $wpdb;

	hodima_seo_advisor_ensure_tables();

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
	foreach ( (array) apply_filters( 'hodima_seo_advisor_sitemap_roots', [ home_url( '/sitemap.xml' ) ] ) as $root ) {
		$maps[] = 'm:' . (string) $root;
	}

	$old   = hodima_seo_advisor_crawler_state();
	$state = [
		'status'      => 'running',
		'run'         => time(),
		'started'     => time(),
		'seed'        => array_merge( $maps, $seed ),
		'queue'       => [],
		'seen'        => [],
		'sitemap'     => [],
		'maps'        => 0,
		'done'        => 0,
		'errors'      => 0,
		'recent'      => [],
		'pause_until' => 0,
		'finished'    => $old['finished'],
		'requested'   => false,
		'pages'       => $old['pages'],
		'links'       => $old['links'],
	];

	hodima_seo_advisor_crawler_enqueue( $state, home_url( '/' ) );
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

	if ( 200 !== $fetched['status'] || '' === $fetched['body'] ) {
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

	return null !== $ms;
}

/**
 * یک نمونه از seed (p:ID یا t:ID:taxonomy) ← آدرس یا null.
 */
function hodima_seo_advisor_crawler_seed_url( string $item ): ?string {

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

	foreach ( array_chunk( $stale, 200 ) as $chunk ) {
		$marks = implode( ',', array_fill( 0, count( $chunk ), '%s' ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE path IN ({$marks})", $page_t, ...$chunk ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- placeholderهای هم‌اندازه
		$wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE source_path IN ({$marks})", $link_t, ...$chunk ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- placeholderهای هم‌اندازه
	}

	// لینک ورودی یکتا و لینک متنی (محتوا و follow) هر صفحه و عمق (کمترین کلیک از صفحه اصلی)؛ در PHP حساب می‌شود
	// تا به نحو SQL پایگاه‌های مختلف (UPDATE با زیرپرسش هم‌بسته) وابسته نباشیم
	$edges   = [];
	$inbound = [];
	$text    = [];
	foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT DISTINCT source_path, dest_path, link_position, is_follow FROM %i', $link_t ), ARRAY_N ) as $edge ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		[ $from, $to, $position, $follow ] = [ (string) $edge[0], (string) $edge[1], (string) $edge[2], (int) $edge[3] ];
		$edges[ $from ][]                  = $to;
		$inbound[ $to ][ $from ]           = true;
		if ( 'content' === $position && 1 === $follow ) {
			$text[ $to ][ $from ] = true;
		}
	}

	$wpdb->query( $wpdb->prepare( 'UPDATE %i SET inlinks = 0, text_links = 0, depth = 0', $page_t ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

	$by_count = [];
	foreach ( $inbound as $to => $sources ) {
		$by_count[ count( $sources ) . '|' . count( $text[ $to ] ?? [] ) ][] = (string) $to;
	}

	foreach ( $by_count as $pair => $paths ) {
		[ $inlinks, $text_links ] = array_map( 'intval', explode( '|', $pair ) );
		foreach ( array_chunk( $paths, 200 ) as $chunk ) {
			$marks = implode( ',', array_fill( 0, count( $chunk ), '%s' ) );
			$wpdb->query( $wpdb->prepare( "UPDATE %i SET inlinks = %d, text_links = %d WHERE path IN ({$marks})", $page_t, $inlinks, $text_links, ...$chunk ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- placeholderهای هم‌اندازه
		}
	}

	$depth    = [ '/' => 0 ];
	$frontier = [ '/' ];

	while ( $frontier ) {
		$next = [];
		foreach ( $frontier as $from ) {
			foreach ( $edges[ $from ] ?? [] as $to ) {
				if ( ! isset( $depth[ $to ] ) ) {
					$depth[ $to ] = $depth[ $from ] + 1;
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

	$pages = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $page_t ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$links = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $link_t ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

	hodima_seo_advisor_crawl_map( true );
	update_option( HODIMA_SEO_ADVISOR_CRAWL_OPTION, [ 'imported' => time(), 'pages' => $pages, 'links' => $links, 'files' => [], 'source' => 'crawler' ], false );

	if ( $state['sitemap'] ) {
		update_option( HODIMA_SEO_ADVISOR_SITEMAP_OPTION, [ 'fetched' => time(), 'paths' => array_map( 'strval', array_keys( $state['sitemap'] ) ) ], false );
	} else {
		delete_option( HODIMA_SEO_ADVISOR_SITEMAP_OPTION ); // نقشه‌ای خوانده نشد: ایرادهای نقشه سایت ساخته نمی‌شوند
	}

	$state['status']   = 'idle';
	$state['finished'] = time();
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

	if ( ! $force && ( ! $settings['enabled'] || ! hodima_seo_advisor_crawler_in_window() ) ) {
		return $none;
	}

	$state = hodima_seo_advisor_crawler_state();

	if ( $state['pause_until'] > time() ) {
		return $none;
	}

	if ( 'idle' === $state['status'] ) {
		$due = $state['requested'] || 0 === $state['finished'] || time() - $state['finished'] >= $settings['interval'] * DAY_IN_SECONDS - HOUR_IN_SECONDS;
		if ( ! $due && ! $force ) {
			return $none;
		}
	}

	if ( ! hodima_seo_advisor_lock( HODIMA_SEO_ADVISOR_CRAWLER_LOCK ) ) {
		return $none;
	}

	if ( 'idle' === $state['status'] ) {
		hodima_seo_advisor_crawler_start();
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
					hodima_seo_advisor_crawler_enqueue( $state, $url );
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

			$ms = hodima_seo_advisor_crawler_process( $url, $state );
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

	return [ 'ran' => $ran, 'finished' => $finished ];
}

/** خزش تازه را در اولین پنجره شبانه درخواست می‌کند (اگر دوری در حال اجرا نیست). */
function hodima_seo_advisor_crawler_request(): void {

	$state = hodima_seo_advisor_crawler_state();

	if ( 'running' !== $state['status'] ) {
		$state['requested'] = true;
		hodima_seo_advisor_crawler_save( $state );
	}
}

/**
 * وضعیت برای نمایش: off، waiting (منتظر پنجره یا موعد)، running، paused.
 *
 * @return array{state: string, done: int, queued: int, seeding: int, finished: int, pause_until: int, requested: bool, pages: int}
 */
function hodima_seo_advisor_crawler_status(): array {

	$settings = hodima_seo_advisor_crawler_settings();
	$state    = hodima_seo_advisor_crawler_state();
	$label    = 'waiting';

	if ( ! $settings['enabled'] ) {
		$label = 'off';
	} elseif ( $state['pause_until'] > time() ) {
		$label = 'paused';
	} elseif ( 'running' === $state['status'] ) {
		$label = 'running';
	}

	return [
		'state'       => $label,
		'done'        => $state['done'],
		'queued'      => count( $state['queue'] ),
		'seeding'     => count( $state['seed'] ),
		'finished'    => $state['finished'],
		'pause_until' => $state['pause_until'],
		'requested'   => $state['requested'],
		'pages'       => $state['pages'],
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
	static function (): void {
		hodima_seo_advisor_crawler_tick();
	}
);

add_action(
	'init',
	static function (): void {
		$scheduled = (bool) wp_next_scheduled( HODIMA_SEO_ADVISOR_CRAWLER_CRON );
		if ( hodima_seo_advisor_crawler_settings()['enabled'] && ! $scheduled ) {
			wp_schedule_event( time() + 300, 'hodima_five_minutes', HODIMA_SEO_ADVISOR_CRAWLER_CRON );
		} elseif ( ! hodima_seo_advisor_crawler_settings()['enabled'] && $scheduled ) {
			wp_clear_scheduled_hook( HODIMA_SEO_ADVISOR_CRAWLER_CRON ); // خاموش شد: رویداد بی‌کار نماند
		}
	}
);
