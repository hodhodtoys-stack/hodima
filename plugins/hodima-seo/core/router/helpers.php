<?php
/**
 * Hodima Router — ابزارهای مشترک: پایه‌ها، مسیر، پیشوندهای رزرو، اندپوینت‌ها
 * Path: core/router/helpers.php
 *
 * نام‌های قدیمی arian_* فقط در legacy.php برای سازگاری‌اند.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * روتر فقط با پیوندهای یکتای «زیبا» معنا دارد. با پیوند ساده (?p=123)
 * لینک‌های بدون پایه ساخته می‌شدند ولی هیچ قانونی آن‌ها را نمی‌شناخت.
 */
function hodima_router_active(): bool {
	return '' !== (string) get_option( 'permalink_structure' );
}

/**
 * نوع‌های پست و تاکسونومی‌های روتر به ترتیب اولویت.
 * اولویت فقط برای نامک تکراری *قدیمی* است (دو شیء با یک آدرس)؛ نامک تکراری
 * جدید در slug-guard.php ساخته نمی‌شود. ترتیب همان نسخه قبلی است تا آدرس‌های
 * فعلی سایت همان محتوای قبلی را نشان دهند.
 *
 * @return array<string, int> نوع => اولویت (کمتر = مقدم)
 */
function hodima_router_priorities(): array {
	return [ 'product_cat' => 1, 'product' => 2, 'category' => 3, 'page' => 4, 'post' => 5 ];
}

/** @return list<string> */
function hodima_router_post_types(): array {
	return array_values( array_filter( [ 'product', 'page', 'post' ], 'post_type_exists' ) );
}

/** @return list<string> */
function hodima_router_taxonomies(): array {
	return array_values( array_filter( [ 'product_cat', 'category' ], 'taxonomy_exists' ) );
}

/* =====================================================================
 * پایه‌ها (product / product-category / category)
 * ===================================================================== */

/**
 * پایه‌های آدرس از تنظیمات واقعی.
 * باگ قبلی: وقتی تنظیم ووکامرس خالی بود «product» و «product-category» ثابت
 * فرض می‌شد؛ ووکامرس پیش‌فرض را از ترجمه نامک می‌گیرد (wc_get_permalink_structure).
 * پایه محصول ممکن است %product_cat% داشته باشد؛ فقط بخش ثابت قبل از % برمی‌گردد.
 *
 * @return array{product:string, product_cat:string, category:string}
 */
function hodima_router_bases(): array {

	$product     = '';
	$product_cat = '';

	if ( function_exists( 'wc_get_permalink_structure' ) ) {
		$wc          = (array) wc_get_permalink_structure();
		$product     = (string) ( $wc['product_rewrite_slug'] ?? '' );
		$product_cat = (string) ( $wc['category_rewrite_slug'] ?? '' );
	} else {
		$perms       = get_option( 'woocommerce_permalinks' );
		$product     = is_array( $perms ) ? (string) ( $perms['product_base'] ?? '' ) : '';
		$product_cat = is_array( $perms ) ? (string) ( $perms['category_base'] ?? '' ) : '';
	}

	$static = static function ( string $base, string $default ): string {
		$pos  = strpos( $base, '%' );
		$base = trim( false === $pos ? $base : substr( $base, 0, $pos ), '/' );
		return '' !== $base ? $base : $default;
	};

	$category = trim( (string) get_option( 'category_base' ), '/' );

	return [
		'product'     => $static( $product, 'product' ),
		'product_cat' => $static( $product_cat, 'product-category' ),
		'category'    => '' !== $category ? $category : 'category',
	];
}

/** حذف یک پایه از ابتدای آدرس، فقط درست بعد از آدرس خانه (مرز-امن). */
function hodima_router_strip_base( string $url, string $base ): string {

	$base = trim( $base, '/' );
	if ( '' === $base ) {
		return $url;
	}

	$home   = trailingslashit( home_url() );
	$needle = $home . $base . '/';

	return str_starts_with( $url, $needle ) ? $home . substr( $url, strlen( $needle ) ) : $url;
}

/**
 * آدرس بدون پایه (فقط کار رشته‌ای، بدون کوئری).
 * ماژول‌های IndexNow و Google Indexing قبلا هر کدام نسخه خودشان را داشتند و
 * وقتی روتر خاموش بود /product/ را از آدرس واقعی حذف می‌کردند (آدرس ۴۰۴).
 * این تابع فقط وقتی روتر روشن است وجود دارد.
 */
function hodima_router_clean_url( string $url ): string {

	if ( ! hodima_router_active() ) {
		return $url;
	}

	foreach ( hodima_router_bases() as $base ) {
		$stripped = hodima_router_strip_base( $url, $base );
		if ( $stripped !== $url ) {
			return $stripped;
		}
	}

	return $url;
}

/* =====================================================================
 * مسیر
 * ===================================================================== */

/** مسیر نسبی (بدون پوشه نصب، پرس‌وجو و اسلش دو طرف) از یک مسیر خام. */
function hodima_router_relative_path( string $path ): string {

	$path = explode( '#', explode( '?', $path, 2 )[0], 2 )[0];
	// اسلش‌های پشت‌سرهم (//a///b) یک مسیر حساب می‌شوند
	$path = implode( '/', array_filter( explode( '/', $path ), 'strlen' ) );

	$home_path = trim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
	if ( '' !== $home_path ) {
		if ( $path === $home_path ) {
			return '';
		}
		if ( str_starts_with( $path, $home_path . '/' ) ) {
			$path = substr( $path, strlen( $home_path ) + 1 );
		}
	}

	return $path;
}

/**
 * مسیر درخواست فعلی.
 * نسخه قبلی esc_url_raw روی REQUEST_URI می‌زد که برای مسیر لازم نیست و
 * بعضی کاراکترها را حذف می‌کرد.
 */
function hodima_router_request_path(): string {
	$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
	return hodima_router_relative_path( $uri );
}

/** مسیر نسبی یک آدرس داخلی، یا null برای آدرس دامنه دیگر. */
function hodima_router_url_path( string $url ): ?string {

	$url = trim( $url );
	if ( '' === $url ) {
		return null;
	}

	$host = (string) wp_parse_url( $url, PHP_URL_HOST );
	if ( '' !== $host ) {
		$bare = static fn( string $h ): string => (string) preg_replace( '/^www\./i', '', strtolower( $h ) );
		if ( $bare( $host ) !== $bare( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) ) {
			return null;
		}
		$url = (string) wp_parse_url( $url, PHP_URL_PATH );
	}

	return hodima_router_relative_path( $url );
}

/** شکل قابل مقایسه مسیر: رمزگشایی‌شده (%D8 و %d8 و حرف فارسی خام یکی‌اند). */
function hodima_router_compare_form( string $path ): string {
	return trim( rawurldecode( $path ), '/' );
}

/**
 * نامک به همان شکلی که وردپرس ذخیره می‌کند: حروف کوچک و حروف غیرلاتین با
 * %xx کوچک. آدرس با حروف بزرگ یا حرف فارسی خام هم پیدا می‌شود (بعد با ۳۰۱
 * به شکل درست می‌رود).
 */
function hodima_router_normalize_slug( string $segment ): string {
	return strtolower( rawurlencode( rawurldecode( $segment ) ) );
}

/* =====================================================================
 * مرز روتر با قانون‌های خود وردپرس
 * ===================================================================== */

/**
 * پیشوندهای ثابت ساختارهای دیگر (tag/، author/، product-tag/، video/ و پایه‌های
 * محصول و دسته). آدرسی که با یکی از این‌ها شروع شود مال وردپرس است.
 *
 * باگ قبلی: روتر روی همه آدرس‌ها اجرا می‌شد؛ /tag/clips/ دسته محصول clips
 * را نشان می‌داد و ۳۰۱ به /clips/ می‌رفت و صفحه برچسب دیگر باز نمی‌شد.
 *
 * @return list<string>
 */
function hodima_router_reserved_prefixes(): array {

	global $wp_rewrite;

	// بعد از wp_loaded همه ساختارها ثبت شده‌اند؛ یک بار در هر درخواست
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}

	if ( ! ( $wp_rewrite instanceof WP_Rewrite ) ) {
		return [];
	}

	$prefixes = [];

	foreach ( (array) $wp_rewrite->extra_permastructs as $struct ) {
		$pattern = is_array( $struct ) ? (string) ( $struct['struct'] ?? '' ) : (string) $struct;
		$pos     = strpos( $pattern, '%' );
		$prefix  = trim( false === $pos ? $pattern : substr( $pattern, 0, $pos ), '/' );
		if ( '' !== $prefix ) {
			$prefixes[] = $prefix;
		}
	}

	// نویسنده (front + author_base)، جستجو و دیدگاه‌ها
	$prefixes[] = trim( (string) $wp_rewrite->front . (string) $wp_rewrite->author_base, '/' );
	$prefixes[] = trim( (string) $wp_rewrite->search_base, '/' );
	$prefixes[] = trim( (string) $wp_rewrite->comments_base, '/' );
	$prefixes   = array_filter( $prefixes, 'strlen' );

	$prefixes = array_merge( $prefixes, array_values( hodima_router_bases() ) );

	/**
	 * پیشوندهایی که روتر به آن‌ها دست نمی‌زند.
	 *
	 * @param list<string> $prefixes
	 */
	$prefixes = (array) apply_filters( 'hodima_router_reserved_prefixes', $prefixes );

	$prefixes = array_values( array_unique( array_map( 'hodima_router_compare_form', array_map( 'strval', $prefixes ) ) ) );

	if ( did_action( 'wp_loaded' ) ) {
		$cache = $prefixes;
	}

	return $prefixes;
}

/** آیا مسیر با یکی از پیشوندهای رزرو شروع می‌شود؟ */
function hodima_router_path_is_reserved( string $path ): bool {

	$path = hodima_router_compare_form( $path );

	foreach ( hodima_router_reserved_prefixes() as $prefix ) {
		if ( '' !== $prefix && ( $path === $prefix || str_starts_with( $path, $prefix . '/' ) ) ) {
			return true;
		}
	}

	return false;
}

/**
 * متغیرهای کوئری که قانون «عمومی» وردپرس (برگه، نوشته با نامک، پیوست، فید،
 * صفحه‌بندی، اندپوینت) می‌سازد. قانونی که متغیر دیگری بسازد (برچسب، نویسنده،
 * تاریخ، پست‌تایپ سفارشی، سایت‌مپ، robots …) مال روتر نیست.
 *
 * @return list<string>
 */
function hodima_router_generic_vars(): array {

	global $wp_rewrite;

	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}

	$vars = [ 'pagename', 'name', 'attachment', 'page', 'feed', 'embed', 'paged', 'cpage', 'tb', 'error' ];

	// برچسب‌های ساختار پیوند نوشته (مثل %category% در /%category%/%postname%/)
	$structure = (string) get_option( 'permalink_structure' );
	if ( $wp_rewrite instanceof WP_Rewrite ) {
		foreach ( (array) $wp_rewrite->rewritecode as $i => $tag ) {
			if ( str_contains( $structure, (string) $tag ) && isset( $wp_rewrite->queryreplace[ $i ] ) ) {
				$vars[] = rtrim( (string) $wp_rewrite->queryreplace[ $i ], '=' );
			}
		}
	}

	foreach ( hodima_router_endpoints() as $endpoint ) {
		$vars[] = $endpoint['var'];
	}

	$vars = array_values( array_unique( $vars ) );

	if ( did_action( 'wp_loaded' ) ) {
		$cache = $vars;
	}

	return $vars;
}

/* =====================================================================
 * اندپوینت‌ها و فیدها
 * ===================================================================== */

/**
 * اندپوینت‌های ووکامرس: نامک => [متغیر، برگه‌ای که اندپوینت روی آن معنا دارد].
 *
 * باگ قبلی: فهرست ثابت ۹ تایی بود؛ order-pay (پرداخت سفارش) نداشت، نامک
 * سفارشی/ترجمه‌شده تنظیمات ووکامرس را نمی‌شناخت و اندپوینت روی *هر* صفحه
 * پذیرفته می‌شد (/hair/orders/ = همان دسته با کد ۲۰۰).
 *
 * @return array<string, array{var:string, page:string}>
 */
function hodima_router_endpoints(): array {

	$defaults = [ 'order-pay', 'order-received', 'orders', 'view-order', 'downloads', 'edit-account', 'edit-address', 'payment-methods', 'lost-password', 'customer-logout', 'add-payment-method', 'delete-payment-method', 'set-default-payment-method' ];

	$query = function_exists( 'WC' ) && is_object( WC() ) && isset( WC()->query ) && is_object( WC()->query ) && method_exists( WC()->query, 'get_query_vars' )
		? (array) WC()->query->get_query_vars()
		: array_combine( $defaults, $defaults );

	$map = [];
	foreach ( $query as $var => $slug ) {
		$slug = trim( (string) $slug, '/' );
		if ( '' === $slug ) {
			continue;
		}
		$map[ hodima_router_compare_form( $slug ) ] = [
			'var'  => (string) $var,
			'page' => in_array( (string) $var, [ 'order-pay', 'order-received' ], true ) ? 'checkout' : 'myaccount',
		];
	}

	/**
	 * اندپوینت‌هایی که روتر می‌شناسد.
	 *
	 * @param array<string, array{var:string, page:string}> $map نامک => [var, page (نام برگه ووکامرس)]
	 */
	return (array) apply_filters( 'hodima_router_endpoints', $map );
}

/** @return list<string> نوع فیدهای ثبت‌شده (feed، rss2، … و فیدهای add_feed مثل podcast) */
function hodima_router_feed_types(): array {
	global $wp_rewrite;
	$feeds = $wp_rewrite instanceof WP_Rewrite ? (array) $wp_rewrite->feeds : [];
	return array_values( array_unique( array_merge( [ 'feed', 'rdf', 'rss', 'rss2', 'atom' ], array_map( 'strval', $feeds ) ) ) );
}
