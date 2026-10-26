<?php
/**
 * ماژول «دستار سئو» — سلامت خزش: خواندن خروجی Screaming Frog (SF) و ساخت ایرادها
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

/** نام ثابت فایل‌ها (همان نام پیش‌فرض SF). */
const HODIMA_SEO_ADVISOR_CRAWL_FILES = [ 'pages' => 'internal_html.csv', 'links' => 'all_inlinks.csv' ];

/** فایلی که کمتر از این ثانیه پیش تغییر کرده هنوز آپلود می‌شود و خوانده نمی‌شود. */
const HODIMA_SEO_ADVISOR_CRAWL_SETTLE = 120;

/** خزش قدیمی‌تر از این روز «قدیمی» نشان می‌گیرد. */
const HODIMA_SEO_ADVISOR_CRAWL_STALE_DAYS = 30;

/** عنوان کوتاه‌تر / بلندتر از این (نویسه) ایراد است؛ گوگل حدود ۶۰ نویسه را نشان می‌دهد. */
const HODIMA_SEO_ADVISOR_CRAWL_TITLE_MIN = 30;
const HODIMA_SEO_ADVISOR_CRAWL_TITLE_MAX = 65;

/** پاسخ سرور کندتر از این (میلی‌ثانیه) «کند» است. */
const HODIMA_SEO_ADVISOR_CRAWL_SLOW_MS = 2000;

/** صفحه با این تعداد لینک ورودی یکتا یا کمتر «لینک ورودی کم» دارد. */
const HODIMA_SEO_ADVISOR_CRAWL_LOW_INLINKS = 2;

/** صفحه‌ای با این نمایش یا بیشتر «پرنمایش» است (آمار «ایراد در صفحه‌های پرنمایش»). */
const HODIMA_SEO_ADVISOR_CRAWL_BIG_IMPRESSIONS = 1000;

/* ── پوشه ── */

/** پوشه فایل‌های خزش (بیرون از افزونه تا با به‌روزرسانی افزونه پاک نشود). */
function hodima_seo_advisor_crawl_dir(): string {

	$upload = wp_upload_dir( null, false );
	$dir    = rtrim( (string) $upload['basedir'], '/\\' ) . '/hodima-advisor';

	return (string) apply_filters( 'hodima_seo_advisor_crawl_dir', $dir );
}

/** مسیر قابل‌نمایش به مدیر (نسبت به ریشه وردپرس اگر ممکن است). */
function hodima_seo_advisor_crawl_dir_label(): string {

	$dir  = str_replace( '\\', '/', hodima_seo_advisor_crawl_dir() );
	$root = str_replace( '\\', '/', rtrim( ABSPATH, '/\\' ) ) . '/';

	return ( str_starts_with( $dir, $root ) ? substr( $dir, strlen( $root ) ) : $dir ) . '/';
}

/**
 * پوشه را (اگر نیست) می‌سازد و از وب می‌بندد: .htaccess برای Apache و LiteSpeed و index.php خالی.
 */
function hodima_seo_advisor_crawl_prepare_dir(): bool {

	$dir = hodima_seo_advisor_crawl_dir();

	if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
		return false;
	}

	$guards = [
		'.htaccess' => "# Hodima: فایل‌های خزش از وب بسته‌اند\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n",
		'index.php' => "<?php\n// Silence is golden.\n",
	];

	foreach ( $guards as $name => $body ) {
		if ( ! is_file( "{$dir}/{$name}" ) ) {
			file_put_contents( "{$dir}/{$name}", $body ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
	}

	return is_dir( $dir ) && wp_is_writable( $dir );
}

/**
 * وضعیت یک فایل روی دیسک: missing | uploading | ready.
 *
 * @return array{kind: string, name: string, state: string, mtime: int, size: int}
 */
function hodima_seo_advisor_crawl_file_state( string $kind ): array {

	$name = HODIMA_SEO_ADVISOR_CRAWL_FILES[ $kind ];
	$file = hodima_seo_advisor_crawl_dir() . '/' . $name;
	$base = [ 'kind' => $kind, 'name' => $name, 'state' => 'missing', 'mtime' => 0, 'size' => 0 ];

	if ( ! is_file( $file ) ) {
		return $base;
	}

	clearstatcache( true, $file );
	$mtime = (int) filemtime( $file );
	$size  = (int) filesize( $file );

	return [ 'state' => time() - $mtime < HODIMA_SEO_ADVISOR_CRAWL_SETTLE ? 'uploading' : 'ready', 'mtime' => $mtime, 'size' => $size ] + $base;
}

/* ── خواندن CSV ── */

/**
 * فایل را باز می‌کند و ستون‌های لازم را می‌سنجد.
 *
 * @param list<string> $required نام ستون‌ها (کوچک)
 * @return array{0: resource, 1: array<string, int>}|WP_Error دسته فایل و نقشه نام ستون ← جایگاه
 */
function hodima_seo_advisor_crawl_open( string $file, array $required ): array|WP_Error {

	$handle = fopen( $file, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

	if ( false === $handle ) {
		return new WP_Error( 'hodima_crawl_open', 'فایل خوانده نشد؛ دسترسی فایل را بررسی کنید.' );
	}

	$head = fgetcsv( $handle, 0, ',', '"', '' );

	if ( ! is_array( $head ) || [ null ] === $head ) {
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return new WP_Error( 'hodima_crawl_empty', 'فایل خالی است.' );
	}

	$map = [];
	foreach ( $head as $i => $name ) {
		$key = strtolower( trim( (string) preg_replace( '/^\xEF\xBB\xBF/', '', (string) $name ) ) );
		if ( '' !== $key ) {
			$map[ $key ] ??= (int) $i;
		}
	}

	$missing = array_diff( $required, array_keys( $map ) );

	if ( $missing ) {
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return new WP_Error( 'hodima_crawl_columns', 'ستون‌های لازم در فایل نیست: ' . implode( '، ', $missing ) . '. خروجی درست را از SF بگیرید.' );
	}

	return [ $handle, $map ];
}

/** عدد از متن CSV (جداکننده هزارگان و فاصله را می‌پذیرد). */
function hodima_seo_advisor_crawl_num( string $value ): float {
	return (float) str_replace( [ ',', ' ', '٬' ], '', $value );
}

/**
 * Internal:HTML ← صفحه‌ها (مسیر یکسان‌شده ← ردیف). آدرس با پارامتر فقط وقتی می‌آید که نسخه تمیزش نیست.
 *
 * @return array<string, array<string, mixed>>|WP_Error
 */
function hodima_seo_advisor_crawl_parse_pages( string $file ): array|WP_Error {

	$opened = hodima_seo_advisor_crawl_open( $file, [ 'address', 'status code' ] );

	if ( is_wp_error( $opened ) ) {
		return $opened;
	}

	[ $handle, $map ] = $opened;

	$cell = static fn( array $row, string $col ): string => isset( $map[ $col ], $row[ $map[ $col ] ] ) ? trim( (string) $row[ $map[ $col ] ] ) : '';
	$time = isset( $map['response time'] ) ? 'response time' : 'ttfb';
	$pages = [];

	do {
		$row = fgetcsv( $handle, 0, ',', '"', '' );

		if ( false === $row ) {
			break;
		}

		$address = $cell( $row, 'address' );
		$path    = '' === $address ? null : hodima_seo_advisor_path_key( $address );

		if ( null === $path ) {
			continue;
		}

		$has_query = str_contains( $address, '?' );
		if ( isset( $pages[ $path ] ) && ( $has_query || ! $pages[ $path ]['has_query'] ) ) {
			continue; // نسخه تمیز یا اولین نسخه می‌ماند
		}

		$title = $cell( $row, 'title 1' );
		$meta  = $cell( $row, 'meta description 1' );

		$pages[ $path ] = [
			'path'       => $path,
			'status'     => (int) hodima_seo_advisor_crawl_num( $cell( $row, 'status code' ) ),
			'indexable'  => isset( $map['indexability'] ) ? ( 'indexable' === strtolower( $cell( $row, 'indexability' ) ) ? 1 : 0 ) : 1,
			'reason'     => mb_substr( $cell( $row, 'indexability status' ), 0, 64 ),
			'title'      => mb_substr( $title, 0, HODIMA_SEO_ADVISOR_KEY_LENGTH ),
			'title_len'  => isset( $map['title 1 length'] ) ? (int) hodima_seo_advisor_crawl_num( $cell( $row, 'title 1 length' ) ) : mb_strlen( $title ),
			'meta_len'   => isset( $map['meta description 1 length'] ) ? (int) hodima_seo_advisor_crawl_num( $cell( $row, 'meta description 1 length' ) ) : mb_strlen( $meta ),
			'h1'         => mb_substr( $cell( $row, 'h1-1' ), 0, HODIMA_SEO_ADVISOR_KEY_LENGTH ),
			'resp_ms'    => (int) round( hodima_seo_advisor_crawl_num( $cell( $row, $time ) ) * 1000 ),
			'inlinks'    => (int) hodima_seo_advisor_crawl_num( $cell( $row, isset( $map['unique inlinks'] ) ? 'unique inlinks' : 'inlinks' ) ),
			'text_links' => -1, // بدون all_inlinks نامعلوم
			'depth'      => (int) hodima_seo_advisor_crawl_num( $cell( $row, 'crawl depth' ) ),
			'score'      => hodima_seo_advisor_crawl_num( $cell( $row, 'link score' ) ),
			'redirect'   => (string) ( hodima_seo_advisor_path_key( $cell( $row, 'redirect url' ) ) ?? '' ),
			'has_query'  => $has_query,
		];
	} while ( true );

	fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

	return $pages;
}

/**
 * All Inlinks ← لینک‌های داخلی (فقط Hyperlink بین آدرس‌های همین سایت؛ بدون تکرار) و شمار لینک متنی یکتا
 * برای هر مقصد (لینک «Content» و follow).
 *
 * @return array{links: list<list<scalar>>, text: array<string, int>}|WP_Error
 */
function hodima_seo_advisor_crawl_parse_links( string $file ): array|WP_Error {

	$opened = hodima_seo_advisor_crawl_open( $file, [ 'source', 'destination' ] );

	if ( is_wp_error( $opened ) ) {
		return $opened;
	}

	[ $handle, $map ] = $opened;

	$cell  = static fn( array $row, string $col ): string => isset( $map[ $col ], $row[ $map[ $col ] ] ) ? trim( (string) $row[ $map[ $col ] ] ) : '';
	$seen  = [];
	$links = [];
	$text  = [];
	$by    = [];

	do {
		$row = fgetcsv( $handle, 0, ',', '"', '' );

		if ( false === $row ) {
			break;
		}

		if ( isset( $map['type'] ) && 'hyperlink' !== strtolower( $cell( $row, 'type' ) ) ) {
			continue;
		}

		$source = hodima_seo_advisor_path_key( $cell( $row, 'source' ) );
		$dest   = hodima_seo_advisor_path_key( $cell( $row, 'destination' ) );

		if ( null === $source || null === $dest || $source === $dest ) {
			continue;
		}

		$position = strtolower( $cell( $row, 'link position' ) );
		$anchor   = mb_substr( $cell( $row, 'anchor' ), 0, 120 );
		$follow   = isset( $map['follow'] ) ? ( 'false' === strtolower( $cell( $row, 'follow' ) ) ? 0 : 1 ) : 1;
		$key      = "{$source}|{$dest}|{$position}|{$anchor}";

		if ( isset( $seen[ $key ] ) ) {
			continue;
		}
		$seen[ $key ] = true;

		$links[] = [ $source, $dest, mb_substr( $position, 0, 16 ), $anchor, $follow ];

		if ( 'content' === $position && 1 === $follow && ! isset( $by[ $dest ][ $source ] ) ) {
			$by[ $dest ][ $source ] = true;
			$text[ $dest ]          = ( $text[ $dest ] ?? 0 ) + 1;
		}
	} while ( true );

	fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

	return [ 'links' => $links, 'text' => $text ];
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

	$page_table = hodima_seo_advisor_table( 'crawl_pages' );
	$link_table = hodima_seo_advisor_table( 'crawl_links' );

	$page_rows = [];
	foreach ( $pages as $p ) {
		$page_rows[] = [ $p['path'], $p['status'], $p['indexable'], $p['reason'], $p['title'], $p['title_len'], $p['meta_len'], $p['h1'], $p['resp_ms'], $p['inlinks'], $p['text_links'], $p['depth'], $p['score'], $p['redirect'] ];
	}

	return hodima_seo_advisor_transaction( static function () use ( $wpdb, $page_table, $link_table, $page_rows, $links ): array|WP_Error {

		$fail = static fn( string $what ): WP_Error => new WP_Error( 'hodima_crawl_store', sprintf( 'ذخیره %1$s ناموفق بود (%2$s)؛ داده قبلی دست‌نخورده ماند.', $what, $wpdb->last_error ) );

		foreach ( [ $page_table, $link_table ] as $table ) {
			if ( false === $wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $table ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				return $fail( 'داده' );
			}
		}

		$insert = [
			[ $page_table, 'path, status, indexable, reason, title, title_len, meta_len, h1, resp_ms, inlinks, text_links, depth, score, redirect_to', '(%s,%d,%d,%s,%s,%d,%d,%s,%d,%d,%d,%d,%f,%s)', $page_rows, 200, 'صفحه‌ها' ],
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

/* ── خواندن فایل‌ها (دکمه «خواندن فایل‌ها») ── */

/**
 * وضعیت ذخیره‌شده.
 *
 * @return array{imported: int, pages: int, links: int, files: array<string, array<string, mixed>>}
 */
function hodima_seo_advisor_crawl_status(): array {

	$stored = get_option( HODIMA_SEO_ADVISOR_CRAWL_OPTION, [] );
	$stored = is_array( $stored ) ? $stored : [];

	return [
		'imported' => (int) ( $stored['imported'] ?? 0 ),
		'pages'    => (int) ( $stored['pages'] ?? 0 ),
		'links'    => (int) ( $stored['links'] ?? 0 ),
		'files'    => is_array( $stored['files'] ?? null ) ? $stored['files'] : [],
	];
}

/**
 * دو فایل را می‌خواند و ذخیره می‌کند. internal_html.csv لازم است؛ all_inlinks.csv اختیاری.
 * فایل در حال آپلود، ناقص یا اشتباه رد می‌شود و داده قبلی می‌ماند؛ پیام هر فایل در وضعیت می‌آید.
 *
 * @return array{pages: int, links: int}|WP_Error
 */
function hodima_seo_advisor_crawl_import(): array|WP_Error {

	$dir = hodima_seo_advisor_crawl_dir();

	if ( ! hodima_seo_advisor_crawl_prepare_dir() ) {
		return new WP_Error( 'hodima_crawl_dir', 'پوشه ' . hodima_seo_advisor_crawl_dir_label() . ' ساخته نشد؛ دسترسی نوشتن پوشه uploads را بررسی کنید.' );
	}

	if ( ! hodima_seo_advisor_lock() ) {
		return new WP_Error( 'hodima_crawl_busy', 'دریافت داده در حال اجراست؛ چند دقیقه بعد دوباره امتحان کنید.' );
	}

	$old   = hodima_seo_advisor_crawl_status();
	$files = [];
	$fail  = static function ( string $message ) use ( &$files, $old ): WP_Error {
		hodima_seo_advisor_unlock();
		update_option( HODIMA_SEO_ADVISOR_CRAWL_OPTION, [ 'imported' => $old['imported'], 'pages' => $old['pages'], 'links' => $old['links'], 'files' => $files ], false );
		return new WP_Error( 'hodima_crawl_import', $message );
	};

	foreach ( array_keys( HODIMA_SEO_ADVISOR_CRAWL_FILES ) as $kind ) {
		$files[ $kind ] = hodima_seo_advisor_crawl_file_state( $kind ) + [ 'rows' => 0, 'error' => '' ];
	}

	if ( 'missing' === $files['pages']['state'] ) {
		return $fail( 'internal_html.csv در پوشه نیست.' );
	}
	if ( 'uploading' === $files['pages']['state'] || 'uploading' === $files['links']['state'] ) {
		return $fail( 'یکی از فایل‌ها همین الان تغییر کرده و شاید هنوز آپلود می‌شود؛ چند دقیقه بعد دوباره بزنید.' );
	}

	if ( function_exists( 'set_time_limit' ) ) {
		set_time_limit( 180 );
	}

	$pages = hodima_seo_advisor_crawl_parse_pages( "{$dir}/" . HODIMA_SEO_ADVISOR_CRAWL_FILES['pages'] );

	if ( is_wp_error( $pages ) ) {
		$files['pages']['state'] = 'invalid';
		$files['pages']['error'] = $pages->get_error_message();
		return $fail( 'internal_html.csv: ' . $pages->get_error_message() );
	}
	if ( ! $pages ) {
		$files['pages']['state'] = 'invalid';
		$files['pages']['error'] = 'هیچ آدرسی از این سایت در فایل نیست.';
		return $fail( 'internal_html.csv: هیچ آدرسی از این سایت در فایل نیست (دامنه خزش‌شده با دامنه سایت یکی نیست؟).' );
	}
	$files['pages']['rows'] = count( $pages );

	$links = [];

	if ( 'ready' === $files['links']['state'] ) {
		$parsed = hodima_seo_advisor_crawl_parse_links( "{$dir}/" . HODIMA_SEO_ADVISOR_CRAWL_FILES['links'] );

		if ( is_wp_error( $parsed ) ) {
			$files['links']['state'] = 'invalid';
			$files['links']['error'] = $parsed->get_error_message();
			return $fail( 'all_inlinks.csv: ' . $parsed->get_error_message() );
		}

		$links                  = $parsed['links'];
		$files['links']['rows'] = count( $links );

		foreach ( $pages as $path => $page ) {
			$pages[ $path ]['text_links'] = $parsed['text'][ $path ] ?? 0;
		}
	}

	$stored = hodima_seo_advisor_crawl_store( $pages, $links );

	if ( is_wp_error( $stored ) ) {
		return $fail( $stored->get_error_message() );
	}

	update_option( HODIMA_SEO_ADVISOR_CRAWL_OPTION, [ 'imported' => time(), 'pages' => $stored['pages'], 'links' => $stored['links'], 'files' => $files, 'source' => 'screamingfrog' ], false );
	hodima_seo_advisor_unlock();

	return $stored;
}

/* ── ایرادها ── */

/** گروه چیپ‌های فیلتر ← کدهای ایراد. */
const HODIMA_SEO_ADVISOR_CRAWL_GROUPS = [
	'meta'     => [ 'no_meta' ],
	'title'    => [ 'no_title', 'title_short', 'title_long' ],
	'dup'      => [ 'dup_title', 'dup_h1' ],
	'slow'     => [ 'slow' ],
	'redirect' => [ 'redirect', 'noindex', 'broken' ],
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
		'orphan'      => [ 'label' => 'بدون لینک ورودی', 'level' => 'bad', 'fix' => 'از دست‌کم دو صفحه مرتبط به آن لینک بدهید.' ],
	][ $code ] ?? [ 'label' => $code, 'level' => 'info', 'fix' => '' ];
}

/**
 * ایرادهای هر صفحه خزش‌شده، با نمایش گوگل (از داده Search Console)، پرنمایش‌ترین اول.
 * ایرادهای ریدایرکت/noindex/خطا فقط وقتی می‌آیند که صفحه نمایش یا لینک ورودی داشته باشد.
 *
 * @return list<array{path: string, impressions: int, position: float, inlinks: int, text_links: int, issues: list<array{code: string, detail: string}>}>
 */
function hodima_seo_advisor_crawl_issues(): array {

	global $wpdb;

	$rows = (array) $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			'SELECT c.*, COALESCE(p.impressions, 0) AS impressions, COALESCE(p.avg_position, 0) AS avg_position FROM %i c LEFT JOIN %i p ON p.path = c.path',
			hodima_seo_advisor_table( 'crawl_pages' ),
			hodima_seo_advisor_table( 'pages' )
		),
		ARRAY_A
	);

	// تکراری‌ها فقط بین صفحه‌های سالم و ایندکس‌پذیر شمرده می‌شوند
	$titles = [];
	$h1s    = [];
	foreach ( $rows as $r ) {
		if ( 200 === (int) $r['status'] && 1 === (int) $r['indexable'] ) {
			$titles[ mb_strtolower( (string) $r['title'] ) ] = ( $titles[ mb_strtolower( (string) $r['title'] ) ] ?? 0 ) + 1;
			$h1s[ mb_strtolower( (string) $r['h1'] ) ]       = ( $h1s[ mb_strtolower( (string) $r['h1'] ) ] ?? 0 ) + 1;
		}
	}

	$out = [];

	foreach ( $rows as $r ) {

		$status      = (int) $r['status'];
		$impressions = (int) $r['impressions'];
		$inlinks     = (int) $r['inlinks'];
		$issues      = [];
		$add         = static function ( string $code, string $detail = '' ) use ( &$issues ): void {
			$issues[] = [ 'code' => $code, 'detail' => $detail ];
		};

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
			if ( '' !== $title && ( $titles[ mb_strtolower( $title ) ] ?? 0 ) > 1 ) {
				$add( 'dup_title', sprintf( 'در %s صفحه', number_format_i18n( $titles[ mb_strtolower( $title ) ] ) ) );
			}
			if ( '' !== (string) $r['h1'] && ( $h1s[ mb_strtolower( (string) $r['h1'] ) ] ?? 0 ) > 1 ) {
				$add( 'dup_h1', sprintf( 'در %s صفحه', number_format_i18n( $h1s[ mb_strtolower( (string) $r['h1'] ) ] ) ) );
			}
			if ( (int) $r['resp_ms'] >= HODIMA_SEO_ADVISOR_CRAWL_SLOW_MS ) {
				$add( 'slow', sprintf( 'پاسخ سرور %s ثانیه', number_format_i18n( (int) $r['resp_ms'] / 1000, 1 ) ) );
			}
			if ( '/' !== (string) $r['path'] ) {
				if ( 0 === $inlinks ) {
					$add( 'orphan' );
				} elseif ( $inlinks <= HODIMA_SEO_ADVISOR_CRAWL_LOW_INLINKS ) {
					$text = (int) $r['text_links'];
					$add( 'low_inlinks', sprintf( '%s لینک ورودی', number_format_i18n( $inlinks ) ) . ( $text >= 0 ? sprintf( '؛ %s لینک از متن', number_format_i18n( $text ) ) : '' ) );
				}
			}
		} elseif ( $impressions > 0 || $inlinks > 0 ) {
			if ( $status >= 300 && $status < 400 ) {
				$add( 'redirect', '' !== (string) $r['redirect_to'] ? 'به ' . $r['redirect_to'] . ' می‌رود' : '' );
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
