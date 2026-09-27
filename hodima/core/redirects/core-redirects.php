<?php
/**
 * Hodima Redirects — engine & shared API
 * Path: core/redirects/core-redirects.php
 * Version: 3.0.0
 *
 * ساختار قانون (گزینه hodima_redirect_rules، کلید = مسیر یکسان‌شده مبدا):
 *   [ '/old-path' => [ 'status' => 301, 'target' => '/new-path/' , 'source' => '/Old-Path',
 *                      'auto' => '' | 'term' | 'post', 'created' => 1727000000 ] ]
 */

namespace Hodima\Redirects;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const OPTION      = 'hodima_redirect_rules';
const HITS_OPTION = 'hodima_redirect_last_used';
const DB_VERSION  = '3';
const REDIRECTS   = [ 301, 302, 307, 308 ];
const STATUSES    = [ 301, 302, 307, 308, 404, 410 ];

/* =====================================================================
 * یکسان‌سازی
 * ===================================================================== */

/**
 * مسیر مبدا به شکل یکسان: «/گل-سر-قدیمی»
 *
 * آدرس کامل یا مسیر، کدگذاری‌شده (کپی از نوار آدرس مرورگر) یا نه.
 *
 * نسخه قبلی دو خطای جدی داشت:
 *   - sanitize_text_field() هر %XX را *حذف* می‌کند. آدرس فارسی کپی‌شده از
 *     مرورگر (%DA%AF%D9%84-…) به «/--» تبدیل می‌شد؛ همه قوانین فارسی به
 *     تقریبا یک کلید می‌رسیدند و روی هم بازنویسی می‌شدند.
 *   - urldecode() به جای rawurldecode(): «+» در مسیر به فاصله تبدیل می‌شد.
 *
 * حروف لاتین کوچک می‌شوند تا /Sale و /sale یکی باشند (فارسی تغییری نمی‌کند).
 */
function normalize_path( string $input ): string {

	$input = trim( wp_strip_all_tags( $input ) );

	if ( '' === $input ) {
		return '';
	}

	$path = (string) ( wp_parse_url( $input, PHP_URL_PATH ) ?? '' );

	if ( '' === $path && ! preg_match( '#^[a-z][a-z0-9+.-]*://#i', $input ) ) {
		$path = (string) strtok( $input, '?#' );
	}

	// نصب در زیرپوشه: مسیر نسبت به ریشه سایت
	$home_path = rtrim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
	if ( '' !== $home_path && str_starts_with( $path, $home_path . '/' ) ) {
		$path = substr( $path, strlen( $home_path ) );
	}

	$path = rawurldecode( $path );
	$path = (string) preg_replace( '#/+#', '/', '/' . ltrim( $path, '/' ) );
	$path = '/' === $path ? '/' : rtrim( $path, '/' );

	return mb_strtolower( $path );
}

/**
 * مقصد یکسان‌شده.
 *   - آدرس همین سایت → مسیر نسبی خوانا («/اکسسوری/گل-سر/»)، تا تغییر دامنه
 *     یا http→https قانون را خراب نکند.
 *   - آدرس خارجی → آدرس کامل http(s).
 *   - نامعتبر → رشته خالی.
 */
function normalize_target( string $input ): string {

	$input = trim( wp_strip_all_tags( $input ) );

	if ( '' === $input ) {
		return '';
	}

	if ( str_starts_with( $input, '//' ) ) {
		$input = ( is_ssl() ? 'https:' : 'http:' ) . $input;
	}

	$parts = wp_parse_url( $input );

	if ( ! is_array( $parts ) ) {
		return '';
	}

	$home_host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	$host      = strtolower( (string) ( $parts['host'] ?? '' ) );

	if ( '' === $host || $host === $home_host || 'www.' . $host === $home_host || 'www.' . $home_host === $host ) {

		$path = rawurldecode( (string) ( $parts['path'] ?? '/' ) );
		$path = '/' . ltrim( (string) preg_replace( '#/+#', '/', $path ), '/' );

		$home_path = rtrim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
		if ( '' !== $home_path && str_starts_with( $path, $home_path . '/' ) ) {
			$path = substr( $path, strlen( $home_path ) );
		}

		return $path
			. ( isset( $parts['query'] ) ? '?' . $parts['query'] : '' )
			. ( isset( $parts['fragment'] ) ? '#' . $parts['fragment'] : '' );
	}

	$scheme = strtolower( (string) ( $parts['scheme'] ?? '' ) );

	if ( ! in_array( $scheme, [ 'http', 'https' ], true ) ) {
		return '';
	}

	return (string) esc_url_raw( $input, [ 'http', 'https' ] );
}

function is_external( string $target ): bool {
	return (bool) preg_match( '#^https?://#i', $target );
}

/** آدرس کامل و قابل‌ارسال در هدر Location (مسیرهای فارسی کدگذاری‌شده). */
function build_location( string $target ): string {

	if ( is_external( $target ) ) {
		return $target;
	}

	$fragment = '';
	if ( false !== ( $h = strpos( $target, '#' ) ) ) {
		$fragment = substr( $target, $h );
		$target   = substr( $target, 0, $h );
	}

	$query = '';
	if ( false !== ( $q = strpos( $target, '?' ) ) ) {
		$query  = substr( $target, $q );
		$target = substr( $target, 0, $q );
	}

	$encoded = implode( '/', array_map( 'rawurlencode', explode( '/', $target ) ) );

	return home_url( $encoded ) . $query . $fragment;
}

/* =====================================================================
 * خواندن و نوشتن قوانین
 * ===================================================================== */

function get_rules(): array {

	$rules = wp_cache_get( OPTION, 'hodima_redirects' );

	if ( false === $rules ) {
		$rules = get_option( OPTION, [] );
		$rules = is_array( $rules ) ? $rules : [];
		wp_cache_set( OPTION, $rules, 'hodima_redirects' );
	}

	return $rules;
}

function save_rules( array $rules ): void {
	update_option( OPTION, $rules, false );
	wp_cache_delete( OPTION, 'hodima_redirects' );
}

/** پاک کردن کش صفحه یک مسیر در لایت‌اسپید (قانون تازه باید فورا اثر کند). */
function purge( string $path ): void {
	if ( '' !== $path ) {
		do_action( 'litespeed_purge_url', build_location( $path ) );
		do_action( 'litespeed_purge_url', trailingslashit( build_location( $path ) ) );
	}
}

/**
 * افزودن یا ویرایش قانون.
 *
 * زنجیره‌ها صاف می‌شوند و حلقه‌ها رد می‌شوند. نسخه قبلی هیچ بررسی‌ای
 * نداشت: A→B و B→C یعنی دو پرش (هر پرش بخشی از اعتبار لینک را هدر
 * می‌دهد و گوگل بعد از چند پرش دنبال نمی‌کند)، و A→B کنار B→A یعنی حلقه
 * بی‌نهایت و خطای «Too many redirects» برای بازدیدکننده.
 *
 * @return array{ok:bool, message:string, key?:string}
 */
function add_rule( string $source_input, string $target_input, int $status, string $auto = '', string $replace_key = '' ): array {

	$key = normalize_path( $source_input );

	if ( '' === $key ) {
		return [ 'ok' => false, 'message' => 'آدرس مبدا خالی یا نامعتبر است.' ];
	}

	if ( '/' === $key ) {
		return [ 'ok' => false, 'message' => 'ریدایرکت صفحه اصلی سایت مجاز نیست.' ];
	}

	if ( ! in_array( $status, STATUSES, true ) ) {
		return [ 'ok' => false, 'message' => 'کد وضعیت نامعتبر است.' ];
	}

	$target = '';

	if ( in_array( $status, REDIRECTS, true ) ) {

		$target = normalize_target( $target_input );

		if ( '' === $target ) {
			return [ 'ok' => false, 'message' => 'برای ریدایرکت، آدرس مقصد معتبر لازم است.' ];
		}
	}

	$rules = get_rules();

	// ویرایش با تغییر مبدا: قانون قبلی برداشته می‌شود
	if ( '' !== $replace_key && $replace_key !== $key ) {
		unset( $rules[ $replace_key ] );
	}

	if ( '' !== $target && ! is_external( $target ) ) {

		$target_key = normalize_path( $target );

		if ( $target_key === $key ) {
			return [ 'ok' => false, 'message' => 'مبدا و مقصد یکسان‌اند (حلقه بی‌نهایت).' ];
		}

		/*
		 * بازگشت به نامک قبلی: X→Y وجود دارد و حالا Y→X ساخته می‌شود.
		 * X دوباره آدرس زنده است؛ قانون X→Y برداشته می‌شود. بدون این، بررسی
		 * حلقه این قانون را رد می‌کرد و X همچنان به Y (که حالا ۴۰۴ است) می‌رفت.
		 */
		if ( isset( $rules[ $target_key ] ) && ! is_external( (string) $rules[ $target_key ]['target'] )
			&& normalize_path( (string) $rules[ $target_key ]['target'] ) === $key ) {
			unset( $rules[ $target_key ] );
			purge( $target_key );
		}

		// صاف کردن رو به جلو: مقصد خودش ریدایرکت دارد → مستقیم به مقصد نهایی
		$seen = [ $key => true ];
		while ( isset( $rules[ $target_key ] ) && in_array( (int) $rules[ $target_key ]['status'], REDIRECTS, true ) ) {
			if ( isset( $seen[ $target_key ] ) ) {
				return [ 'ok' => false, 'message' => 'این قانون با قوانین موجود یک حلقه می‌سازد.' ];
			}
			$seen[ $target_key ] = true;
			$target              = (string) $rules[ $target_key ]['target'];
			if ( is_external( $target ) ) {
				break;
			}
			$target_key = normalize_path( $target );
			if ( $target_key === $key ) {
				return [ 'ok' => false, 'message' => 'این قانون با قوانین موجود یک حلقه می‌سازد.' ];
			}
		}
	}

	// صاف کردن رو به عقب: قوانینی که به همین مبدا می‌رفتند، مستقیم به مقصد جدید
	if ( '' !== $target ) {
		foreach ( $rules as $other_key => $other ) {
			if ( $other_key !== $key && ! is_external( (string) $other['target'] ) && normalize_path( (string) $other['target'] ) === $key ) {
				if ( ! is_external( $target ) && normalize_path( $target ) === $other_key ) {
					unset( $rules[ $other_key ] ); // X→A و A→X: قانون قدیمی حذف (مقصد تازه صفحه واقعی است)
				} else {
					$rules[ $other_key ]['target'] = $target;
				}
			}
		}
	}

	$rules[ $key ] = [
		'status'  => $status,
		'target'  => $target,
		'source'  => '/' . ltrim( rawurldecode( (string) ( wp_parse_url( trim( $source_input ), PHP_URL_PATH ) ?: $source_input ) ), '/' ),
		'auto'    => $auto,
		'created' => (int) ( $rules[ $key ]['created'] ?? time() ),
	];

	save_rules( $rules );
	purge( $key );

	return [ 'ok' => true, 'message' => 'قانون ذخیره شد.', 'key' => $key ];
}

function delete_rule( string $key ): bool {

	$rules = get_rules();

	if ( ! isset( $rules[ $key ] ) ) {
		return false;
	}

	unset( $rules[ $key ] );
	save_rules( $rules );

	$hits = get_option( HITS_OPTION, [] );
	if ( is_array( $hits ) && isset( $hits[ $key ] ) ) {
		unset( $hits[ $key ] );
		update_option( HITS_OPTION, $hits, false );
	}

	purge( $key );
	return true;
}

/**
 * «آخرین استفاده» — حداکثر یک نوشتن در ساعت برای هر قانون.
 * برای پیدا کردن قوانینی که دیگر کسی به آن‌ها نمی‌رسد و می‌شود پاکشان کرد.
 */
function touch_rule( string $key ): void {

	$hits = get_option( HITS_OPTION, [] );
	$hits = is_array( $hits ) ? $hits : [];

	if ( ( time() - (int) ( $hits[ $key ] ?? 0 ) ) < HOUR_IN_SECONDS ) {
		return;
	}

	$hits[ $key ] = time();
	update_option( HITS_OPTION, $hits, false );
}

/* =====================================================================
 * ارتقای داده قدیمی
 * ===================================================================== */

add_action( 'admin_init', __NAMESPACE__ . '\\maybe_upgrade' );

/**
 * کلیدهای قدیمی با الگوریتم جدید بازسازی می‌شوند (کوچک‌سازی، یکسان‌سازی).
 * قوانینی که قبلا با sanitize_text_field خراب شده‌اند (مثل «/--») قابل
 * بازیابی نیستند؛ در پنل با نشان «مشکوک» مشخص می‌شوند.
 */
function maybe_upgrade(): void {

	if ( DB_VERSION === get_option( 'hodima_redirects_db_version' ) ) {
		return;
	}

	$old = get_option( OPTION, [] );
	$new = [];

	foreach ( (array) $old as $raw_key => $rule ) {
		$key = normalize_path( (string) $raw_key );
		if ( '' === $key || ! is_array( $rule ) ) {
			continue;
		}
		$status = (int) ( $rule['status'] ?? 301 );
		$new[ $key ] = [
			'status'  => in_array( $status, STATUSES, true ) ? $status : 301,
			'target'  => in_array( $status, REDIRECTS, true ) ? normalize_target( (string) ( $rule['target'] ?? '' ) ) : '',
			'source'  => (string) $raw_key,
			'auto'    => (string) ( $rule['auto'] ?? '' ),
			'created' => (int) ( $rule['created'] ?? 0 ),
		];
	}

	save_rules( $new );
	update_option( 'hodima_redirects_db_version', DB_VERSION, false );
}

/** قانونی که احتمالا با باگ قدیمی خراب شده (مسیر بدون هیچ حرف یا عدد). */
function is_suspicious( string $key ): bool {
	return ! preg_match( '/[\p{L}\p{N}]/u', $key );
}

/* =====================================================================
 * اجرا در فرانت
 * ===================================================================== */

add_action( 'template_redirect', __NAMESPACE__ . '\\process_redirects', 1 );

function process_redirects(): void {

	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return;
	}

	$rules = get_rules();

	if ( empty( $rules ) ) {
		return;
	}

	$key  = normalize_path( (string) wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ) );
	$rule = $rules[ $key ] ?? null;

	if ( null === $rule ) {
		return;
	}

	$status = (int) ( $rule['status'] ?? 301 );

	if ( in_array( $status, REDIRECTS, true ) && '' !== (string) ( $rule['target'] ?? '' ) ) {

		$location = build_location( (string) $rule['target'] );

		// حفظ پارامترهای آدرس (utm و …) — پیش از #
		$query = (string) ( $_SERVER['QUERY_STRING'] ?? '' );
		if ( '' !== $query ) {
			$hash     = '';
			if ( false !== ( $h = strpos( $location, '#' ) ) ) {
				$hash     = substr( $location, $h );
				$location = substr( $location, 0, $h );
			}
			$location .= ( str_contains( $location, '?' ) ? '&' : '?' ) . $query . $hash;
		}

		// محافظ نهایی حلقه
		if ( ! is_external( (string) $rule['target'] ) && normalize_path( (string) $rule['target'] ) === $key ) {
			return;
		}

		touch_rule( $key );

		if ( in_array( $status, [ 302, 307 ], true ) ) {
			nocache_headers(); // موقت: مرورگر نباید برای همیشه به خاطر بسپارد
		}

		/*
		 * wp_redirect به جای wp_safe_redirect.
		 * wp_safe_redirect فقط دامنه خود سایت را مجاز می‌داند و برای هر
		 * مقصد دیگری *بی‌صدا* به /wp-admin/ می‌فرستد؛ قانونی که به دامنه
		 * دیگر یا صفحه اینستاگرام اشاره داشت، بازدیدکننده را به صفحه ورود
		 * پیشخوان می‌برد. مقصدها را فقط مدیر تعریف می‌کند و هنگام ذخیره
		 * اعتبارسنجی می‌شوند (فقط http و https).
		 */
		wp_redirect( $location, $status, 'Hodima Redirects' );
		exit;
	}

	if ( in_array( $status, [ 404, 410 ], true ) ) {

		touch_rule( $key );

		global $wp_query;
		$wp_query->set_404();
		status_header( $status );
		nocache_headers();

		$template = get_query_template( '404' );
		if ( $template ) {
			include $template;
		}
		exit;
	}
}

/* =====================================================================
 * ریدایرکت خودکار هنگام تغییر نامک
 * ---------------------------------------------------------------------
 * تغییر نامک یک دسته‌بندی آدرس آن و همه زیردسته‌هایش را عوض می‌کند؛
 * نسخه قبلی هیچ ریدایرکتی نمی‌ساخت و آدرس‌های قبلی — که گوگل ایندکس
 * کرده و بک‌لینک دارند — ۴۰۴ می‌شدند. برای محصولات و نوشته‌ها هم
 * ریدایرکت «نامک قدیمی» خود وردپرس روی نوع پست کوئری تکیه دارد که با
 * آدرس‌های بدون پیشوند روتر قالب قابل اتکا نیست.
 * ===================================================================== */

/** @var array<int, array<int, string>> */
$GLOBALS['hodima_redirect_pending_terms'] = [];

function auto_enabled(): bool {
	return (bool) apply_filters( 'hodima_redirects_auto', true );
}

function term_paths_with_children( int $term_id, string $taxonomy ): array {

	$ids   = array_merge( [ $term_id ], array_slice( (array) get_term_children( $term_id, $taxonomy ), 0, 200 ) );
	$paths = [];

	foreach ( $ids as $id ) {
		$link = get_term_link( (int) $id, $taxonomy );
		if ( ! is_wp_error( $link ) ) {
			$paths[ (int) $id ] = (string) $link;
		}
	}

	return $paths;
}

add_action( 'edit_terms', static function ( $term_id, $taxonomy = '' ): void {
	if ( auto_enabled() && is_taxonomy_viewable( (string) $taxonomy ) ) {
		$GLOBALS['hodima_redirect_pending_terms'][ (int) $term_id ] = term_paths_with_children( (int) $term_id, (string) $taxonomy );
	}
}, 10, 2 );

add_action( 'edited_term', static function ( $term_id, $tt_id = 0, $taxonomy = '' ): void {

	$before = $GLOBALS['hodima_redirect_pending_terms'][ (int) $term_id ] ?? null;
	unset( $GLOBALS['hodima_redirect_pending_terms'][ (int) $term_id ] );

	if ( null === $before ) {
		return;
	}

	clean_term_cache( (int) $term_id, (string) $taxonomy );
	$after = term_paths_with_children( (int) $term_id, (string) $taxonomy );

	foreach ( $before as $id => $old_url ) {
		$new_url = $after[ $id ] ?? '';
		if ( '' !== $new_url && normalize_path( $old_url ) !== normalize_path( $new_url ) ) {
			add_rule( $old_url, $new_url, 301, 'term' );
		}
	}
}, 10, 3 );

add_action( 'post_updated', static function ( $post_id, $after, $before ): void {

	if ( ! auto_enabled() || ! ( $after instanceof \WP_Post ) || ! ( $before instanceof \WP_Post ) ) {
		return;
	}

	if ( 'publish' !== $before->post_status || 'publish' !== $after->post_status || ! is_post_type_viewable( $after->post_type ) ) {
		return;
	}

	if ( $before->post_name === $after->post_name && $before->post_parent === $after->post_parent ) {
		return;
	}

	$old_url = (string) get_permalink( $before );
	$new_url = (string) get_permalink( $after );

	if ( '' !== $old_url && '' !== $new_url && normalize_path( $old_url ) !== normalize_path( $new_url ) ) {
		add_rule( $old_url, $new_url, 301, 'post' );
	}
}, 10, 3 );

/*
 * آدرسی که دوباره زنده شد: اگر محتوای جدیدی در آدرسی منتشر شود که مبدا
 * یک قانون است، قانون آن را پنهان می‌کرد. قانون برداشته می‌شود.
 */
add_action( 'transition_post_status', static function ( $new, $old, $post ): void {
	if ( 'publish' === $new && $post instanceof \WP_Post && is_post_type_viewable( $post->post_type ) ) {
		$url = (string) get_permalink( $post );
		if ( '' !== $url && isset( get_rules()[ normalize_path( $url ) ] ) ) {
			delete_rule( normalize_path( $url ) );
		}
	}
}, 20, 3 );

add_action( 'created_term', static function ( $term_id, $tt_id = 0, $taxonomy = '' ): void {
	if ( is_taxonomy_viewable( (string) $taxonomy ) ) {
		$url = get_term_link( (int) $term_id, (string) $taxonomy );
		if ( ! is_wp_error( $url ) && isset( get_rules()[ normalize_path( (string) $url ) ] ) ) {
			delete_rule( normalize_path( (string) $url ) );
		}
	}
}, 10, 3 );
