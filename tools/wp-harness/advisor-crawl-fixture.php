<?php
/**
 * فقط برای ابزار تست دستار: تولید داده خزش از فایل‌های CSV نمونه با ستون‌های ثابت.
 * ورود فایل از SEO 3.1.7 از افزونه برداشته شد (خزنده داخلی جایش را گرفت)؛ آزمون‌ها همچنان با CSV نمونه
 * داده خزش می‌سازند و این فایل همان خواندن قبلی را برایشان نگه می‌دارد. در ZIP افزونه نیست.
 */

defined( 'ABSPATH' ) || exit;

/** نام ثابت فایل‌ها (نام ثابت فایل‌های نمونه). */
const HODIMA_SEO_ADVISOR_CRAWL_FILES = [ 'pages' => 'internal_html.csv', 'links' => 'all_inlinks.csv' ];

/** فایلی که کمتر از این ثانیه پیش تغییر کرده هنوز آپلود می‌شود و خوانده نمی‌شود. */
const HODIMA_SEO_ADVISOR_CRAWL_SETTLE = 120;

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
 * فایل‌های خزش از اینترنت قابل‌دانلودند؟ `.htaccess` فقط Apache/LiteSpeed را می‌بندد؛ روی nginx بی‌اثر است و
 * (نام فایل‌ها ثابت و قابل‌حدس‌اند) فهرست آدرس‌ها و ایرادهای سایت بیرون می‌ماند. با یک درخواست HEAD به همان فایل سنجیده می‌شود.
 *
 * @return bool|null true = قابل‌دانلود، false = بسته، null = نمی‌شود فهمید (فایلی نیست یا درخواست نرسید)
 */
function hodima_seo_advisor_crawl_web_exposed(): ?bool {

	$cached = get_transient( 'hodima_advisor_crawl_exposed' );

	if ( in_array( $cached, [ 'yes', 'no' ], true ) ) {
		return 'yes' === $cached;
	}

	$upload = wp_upload_dir( null, false );
	$base   = rtrim( str_replace( '\\', '/', (string) $upload['basedir'] ), '/' );
	$dir    = rtrim( str_replace( '\\', '/', hodima_seo_advisor_crawl_dir() ), '/' );

	if ( ! str_starts_with( $dir, $base . '/' ) ) {
		return false; // پوشه با فیلتر بیرون از uploads رفته؛ آدرس وب ندارد
	}

	foreach ( HODIMA_SEO_ADVISOR_CRAWL_FILES as $name ) {
		if ( ! is_file( "{$dir}/{$name}" ) ) {
			continue;
		}

		$response = wp_remote_head( rtrim( (string) $upload['baseurl'], '/' ) . substr( $dir, strlen( $base ) ) . '/' . rawurlencode( $name ), [ 'timeout' => 5, 'redirection' => 0 ] );

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$exposed = 200 === (int) wp_remote_retrieve_response_code( $response );
		set_transient( 'hodima_advisor_crawl_exposed', $exposed ? 'yes' : 'no', HOUR_IN_SECONDS );

		return $exposed;
	}

	return null;
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

	// فایل نمونه ممکن است با BOM بیاید و عنوان ستون‌ها در گیومه باشد؛ BOM پیش از گیومه باشد خواندن CSV گیومه را جزو نام ستون می‌کند (باگ ۲.۸.۱: «ستون address نیست»)، پس BOM را پیش از خواندن رد می‌کنیم
	if ( "\xEF\xBB\xBF" !== fread( $handle, 3 ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- فایل CSV محلی، خواندن سه بایت
		rewind( $handle );
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
		return new WP_Error( 'hodima_crawl_columns', 'ستون‌های لازم در فایل نیست: ' . implode( '، ', $missing ) . '.' );
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
			'h2'         => mb_substr( trim( $cell( $row, 'h2-1' ) . ' | ' . $cell( $row, 'h2-2' ), ' |' ), 0, 255 ), // فقط دو H2 اول در فایل نمونه است
			'resp_ms'    => (int) round( hodima_seo_advisor_crawl_num( $cell( $row, $time ) ) * 1000 ),
			'inlinks'    => (int) hodima_seo_advisor_crawl_num( $cell( $row, isset( $map['unique inlinks'] ) ? 'unique inlinks' : 'inlinks' ) ),
			'text_links' => -1, // بدون all_inlinks نامعلوم
			'depth'      => (int) hodima_seo_advisor_crawl_num( $cell( $row, 'crawl depth' ) ),
			'score'      => hodima_seo_advisor_crawl_num( $cell( $row, 'link score' ) ),
			'redirect'   => (string) ( hodima_seo_advisor_path_key( $cell( $row, 'redirect url' ) ) ?? '' ),
			'no_snippet' => hodima_seo_advisor_robots_no_snippet( $cell( $row, 'meta robots 1' ), $cell( $row, 'x-robots-tag 1' ) ) ? 1 : 0,
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

	hodima_seo_advisor_crawl_map( true );
	update_option( HODIMA_SEO_ADVISOR_CRAWL_OPTION, [ 'imported' => time(), 'pages' => $stored['pages'], 'links' => $stored['links'], 'files' => $files, 'source' => 'fixture' ], false );

	// کارهای «انجام دادم» ایرادهای فنی با خزش تازه سنجیده می‌شوند (کند، لینک ورودی و ریدایرکت فقط همین‌جا)
	hodima_seo_advisor_tasks_sync_crawl();
	hodima_seo_advisor_score_record();

	// پیشنهادهای ذخیره‌شده (نرخ کلیک و رقابت) با داده خزش دوباره ساخته می‌شوند: چند ثانیه همین‌جا، بقیه در پس‌زمینه.
	// دریافت داده‌ای که در میانه است و نوبت analyze آن گذشته هم analyze تازه می‌گیرد (قبلا تا فردا اثر نمی‌کرد)
	hodima_seo_advisor_request_reanalyze();
	if ( hodima_seo_advisor_pipeline_pending() ) {
		hodima_seo_advisor_pipeline_run( (float) HODIMA_SEO_ADVISOR_MANUAL_BUDGET );
	}

	hodima_seo_advisor_unlock();

	return $stored;
}

