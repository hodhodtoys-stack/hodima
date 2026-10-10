<?php
/**
 * ماژول «گوگل دیسکاور» — داده، تصویر و برش‌ها
 * Path: core/discover/discover-init.php
 *
 * تا Hodima SEO 1.15.0 این بخش داخل «سیستم رسانه» افزونه Hodima Media بود
 * (media-discover.php) و با خاموش شدن آن ماژول، دیسکاور هم خاموش می‌شد؛
 * در حالی که همه مصرف‌کننده‌هایش (سئوباکس، اسکیمای مقاله، فید) اینجا،
 * در افزونه سئو هستند. حالا ماژول مستقل با کلید خودش در «ماژول‌های سئو».
 *
 * داده سایت عوض نشده (قانون ۵): همان کلیدهای متای قبلی
 *   _hook_discover_title      عنوان دیسکاور
 *   _hook_discover_image_id   تصویر دیسکاور (شناسه پیوست)
 *   _hook_key_entities        موضوعات اصلی («الف, ب»)
 * دسته‌های محصول (از SEO 1.17.0) همان‌ها در term meta با پیشوند «hook_»
 * (قرارداد سیستم رسانه برای ترم‌ها): hook_discover_title، hook_discover_image_id،
 * hook_key_entities.
 *
 * نام توابع تازه است (hodima_seo_discover_*). نام‌های قبلی
 * (hodima_media_discover_*، hook_modern_seo_enabled) در legacy.php فقط اگر
 * تعریف نشده باشند ساخته می‌شوند.
 *
 * هم‌زیستی با Hodima Media قدیمی (۱.۴ و پایین‌تر که دیسکاور را خودش دارد):
 * این فایل فقط تابع تعریف می‌کند؛ هوک‌ها، کادر و نام‌های قدیمی در
 * plugins_loaded اولویت ۲۰ (بعد از لود ماژول‌های Media و SEO در اولویت ۵) و فقط وقتی
 * Media قدیمی دیسکاور را نساخته ثبت می‌شوند. وگرنه همه چیز دو بار اجرا
 * می‌شد (دو لینک فید، دو تصویر در فید، دو کادر) و نام‌های قدیمی خطای
 * «Cannot redeclare» می‌دادند.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** برش‌های تصویر: کلید ← [ عرض نسبت, ارتفاع نسبت ]. */
const HODIMA_SEO_DISCOVER_CROPS = [ '16x9' => [ 16, 9 ], '4x3' => [ 4, 3 ], '1x1' => [ 1, 1 ] ];

/** حداقل عرض تصویر بزرگ دیسکاور (مستندات گوگل). */
const HODIMA_SEO_DISCOVER_MIN_WIDTH = 1200;

/**
 * عبارت‌های طعمه کلیک و اغراق (گوگل دیسکاور عنوان اغراق‌آمیز، پنهان‌کاری
 * محتوا و تحریک احساس را جریمه می‌کند).
 *
 * مقایسه «کلمه کامل» است (hodima_seo_discover_clickbait_match)؛ «*» در آخر
 * یعنی ادامه کلمه آزاد است («باورتان نمی*» = نمی‌شود/نمی‌کنید). نیم‌فاصله،
 * اعراب و «ي/ك» عربی پیش از مقایسه یکسان می‌شوند.
 * باگ قبلی (تا SEO 2.1.1): تکه‌ای از کلمه مقایسه می‌شد؛ «موی افشان» (افشا)،
 * «شیراز» (راز)، «فوریه» (فوری) طعمه کلیک شمرده می‌شدند. «جادویی» هم برداشته
 * شد: نام واقعی محصول است («شانه جادویی»). سایت با فیلتر
 * hodima_seo_discover_clickbait_phrases فهرست را عوض می‌کند.
 */
const HODIMA_SEO_DISCOVER_CLICKBAIT = [
	// باور و شگفتی
	'باورتان نمی*', 'باورت نمی*', 'باور نمی*', 'باورنکردنی', 'باور نکردنی', 'غیرقابل باور', 'غیر قابل باور', 'شوکه*', 'شوک', 'تکان دهنده',
	'شگفت زده*', 'هرگز تصور*', 'معجزه', 'معجزه آسا', 'معجزه گر',
	// پنهان‌کاری و کنجکاوی
	'راز', 'رازهای', 'افشا', 'افشای', 'افشاگری', 'ببینید چه', 'ببینید چی', 'نمی دانستید', 'نمیدانستید', 'هیچ کس نمی*', 'هیچکس نمی*',
	'حدس بزنید', 'آخرش', 'تا آخر ببینید', 'کلیک کنید',
	// فوریت ساختگی
	'حتما ببینید', 'از دست ندهید', 'فوری', 'همین الان', 'فقط امروز',
	// نشانه‌گذاری اغراق‌آمیز
	'!!', '؟؟', '??', '؟!', '!؟', '?!', '!?',
];

/** طول پیشنهادی عنوان کارت دیسکاور (کاراکتر): کوتاه‌تر مبهم، بلندتر کوتاه می‌شود. */
const HODIMA_SEO_DISCOVER_TITLE_MIN = 30;
const HODIMA_SEO_DISCOVER_TITLE_MAX = 110;

/** کلیدهای متای نوشته/محصول (همان کلیدهای سیستم رسانه قبلی). */
const HODIMA_SEO_DISCOVER_META = [
	'title'    => '_hook_discover_title',
	'image_id' => '_hook_discover_image_id',
	'entities' => '_hook_key_entities',
];

/** کلید متای یک فیلد: نوشته «_hook_…»، ترم «hook_…». */
function hodima_seo_discover_meta_key( string $field, string $context = 'post' ): string {
	$key = HODIMA_SEO_DISCOVER_META[ $field ];
	return 'term' === $context ? ltrim( $key, '_' ) : $key;
}

// فهرست «آمادگی برای دیسکاور» و پیشنهاد عنوان (فقط تابع)
require_once __DIR__ . '/discover-checks.php';

add_action( 'plugins_loaded', 'hodima_seo_discover_boot', 20 );

/** ثبت هوک‌ها، کادر و نام‌های قدیمی — فقط اگر Hodima Media قدیمی دیسکاور را ندارد. */
function hodima_seo_discover_boot(): void {

	if ( hodima_seo_discover_provided_by_media() ) {
		return;
	}

	require_once __DIR__ . '/legacy.php';
	require_once __DIR__ . '/discover-front.php';
	require_once __DIR__ . '/discover-stats.php'; // WP-Cron هم در درخواست غیر پیشخوان اجرا می‌شود
	require_once __DIR__ . '/discover-history.php'; // تاریخچه آمار و ثبت تغییرها (ذخیره از REST هم)
	// کش ردیف گزارش: پاک شدن آن باید در REST (ویرایشگر بلوکی) و کرون هم رخ دهد، نه فقط پیشخوان
	require_once __DIR__ . '/discover-cache.php';

	add_action( 'init', 'hodima_seo_discover_register_meta', 20 ); // بعد از ثبت «محصول» ووکامرس (init ۵)

	if ( is_admin() ) {
		require_once __DIR__ . '/discover-admin.php';
		require_once __DIR__ . '/discover-insights.php';
		require_once __DIR__ . '/discover-report.php';
	}
}

/**
 * ثبت متاهای دیسکاور (register_post_meta / register_term_meta):
 *   - REST و ویرایشگر بلوکی نوع و دسترسی آن‌ها را می‌شناسند (فقط ویرایشگر همان شیء)؛
 *   - نوع‌هایی که «نسخه‌ها» دارند (نوشته، برگه): بازگردانی یک نسخه، عنوان،
 *     تصویر و موضوعات دیسکاور همان نسخه را هم برمی‌گرداند.
 * پاک‌سازی همان قاعده ذخیره کادر است (تکرار آن روی مقدار تمیز، همان را می‌دهد).
 */
function hodima_seo_discover_register_meta(): void {

	$sanitize = [
		'title'    => static fn( mixed $v ): string => sanitize_text_field( is_scalar( $v ) ? (string) $v : '' ),
		'image_id' => static fn( mixed $v ): int => absint( is_scalar( $v ) ? $v : 0 ),
		'entities' => static fn( mixed $v ): string => hodima_seo_discover_format_entities( hodima_seo_discover_entity_items( sanitize_textarea_field( is_scalar( $v ) ? (string) $v : '' ) ) ),
	];

	foreach ( hodima_seo_discover_post_types() as $post_type ) {
		if ( ! post_type_exists( $post_type ) ) {
			continue;
		}
		foreach ( array_keys( HODIMA_SEO_DISCOVER_META ) as $field ) {
			register_post_meta( $post_type, hodima_seo_discover_meta_key( $field ), [
				'type'              => 'image_id' === $field ? 'integer' : 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => $sanitize[ $field ],
				'auth_callback'     => static fn( bool $allowed, string $key, int $post_id ): bool => current_user_can( 'edit_post', $post_id ),
				// فقط نوع دارای نسخه؛ وگرنه وردپرس _doing_it_wrong می‌دهد (محصول نسخه ندارد)
				'revisions_enabled' => post_type_supports( $post_type, 'revisions' ),
			] );
		}
		// «این صفحه برای گوگل دیسکاور نیست» (discover-checks.php)
		register_post_meta( $post_type, HODIMA_SEO_DISCOVER_SKIP_META, [
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => true,
			'sanitize_callback' => static fn( mixed $v ): string => '1' === ( is_scalar( $v ) ? (string) $v : '' ) ? '1' : '',
			'auth_callback'     => static fn( bool $allowed, string $key, int $post_id ): bool => current_user_can( 'edit_post', $post_id ),
		] );
	}

	// نقطه تمرکز برش‌ها روی خود تصویر (ویرایشگر بلوکی آن را با تصویر شاخص از REST می‌خواند)
	register_post_meta( 'attachment', HODIMA_SEO_DISCOVER_FOCUS_META, [
		'type'              => 'string',
		'single'            => true,
		'show_in_rest'      => true,
		'sanitize_callback' => static function ( mixed $v ): string {
			$focus = hodima_seo_discover_parse_focus( is_scalar( $v ) ? (string) $v : '' );
			return null === $focus ? '' : hodima_seo_discover_focus_string( $focus );
		},
		'auth_callback'     => static fn( bool $allowed, string $key, int $post_id ): bool => current_user_can( 'edit_post', $post_id ),
	] );

	foreach ( hodima_seo_discover_taxonomies() as $taxonomy ) {
		if ( ! taxonomy_exists( $taxonomy ) ) {
			continue;
		}
		foreach ( array_keys( HODIMA_SEO_DISCOVER_META ) as $field ) {
			register_term_meta( $taxonomy, hodima_seo_discover_meta_key( $field, 'term' ), [
				'type'              => 'image_id' === $field ? 'integer' : 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => $sanitize[ $field ],
				'auth_callback'     => static fn( bool $allowed, string $key, int $term_id ): bool => current_user_can( 'edit_term', $term_id ),
			] );
		}
		register_term_meta( $taxonomy, HODIMA_SEO_DISCOVER_SKIP_META, [
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => true,
			'sanitize_callback' => static fn( mixed $v ): string => '1' === ( is_scalar( $v ) ? (string) $v : '' ) ? '1' : '',
			'auth_callback'     => static fn( bool $allowed, string $key, int $term_id ): bool => current_user_can( 'edit_term', $term_id ),
		] );
	}
}

/** آیا Hodima Media قدیمی (۱.۴ و پایین‌تر) دیسکاور را خودش ساخته است؟ */
function hodima_seo_discover_provided_by_media(): bool {
	// ماژول‌های Media در plugins_loaded اولویت ۵ لود می‌شوند؛ این تابع در اولویت ۲۰ صدا زده می‌شود
	return function_exists( 'hodima_media_discover_image' );
}

/* =====================================================================
 * داده
 * ===================================================================== */

/**
 * نوع‌های نوشته‌ای که دیسکاور دارند: نوشته، برگه و (از SEO 1.17.0) محصول.
 * نام فیلتر همان نسخه‌های قبلی است تا کد سفارشی سایت کار کند.
 *
 * @return list<string>
 */
function hodima_seo_discover_post_types(): array {
	return array_values( array_map( 'strval', (array) apply_filters( 'hook_modern_seo_post_types', [ 'post', 'page', 'product' ] ) ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- نام فیلتر قبلی سیستم رسانه؛ کد سفارشی سایت از آن استفاده می‌کند
}

/**
 * تکسونومی‌هایی که دیسکاور دارند: دسته محصول (صفحه‌اش را قالب با تصویر و
 * متن معرفی کامل نمایش می‌دهد). دسته‌های وبلاگ نه.
 *
 * @return list<string>
 */
function hodima_seo_discover_taxonomies(): array {
	return array_values( array_map( 'strval', (array) apply_filters( 'hodima_seo_discover_taxonomies', [ 'product_cat' ] ) ) );
}

/**
 * آیا دیسکاور برای این شیء فعال است؟
 *
 * @param string     $context   post | term
 * @param int|string $object_id ۰ = از صفحه فعلی پیشخوان (نوع/تکسونومی)
 */
function hodima_seo_discover_enabled( string $context, int|string $object_id = 0 ): bool {

	$id      = is_numeric( $object_id ) ? (int) $object_id : 0;
	$screen  = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	$enabled = false;

	if ( 'post' === $context ) {
		$post_type = $id > 0 ? (string) get_post_type( $id ) : (string) ( $screen->post_type ?? '' );
		$enabled   = in_array( $post_type, hodima_seo_discover_post_types(), true );
	} elseif ( 'term' === $context ) {
		$term     = $id > 0 ? get_term( $id ) : null;
		$taxonomy = $term instanceof WP_Term ? $term->taxonomy : (string) ( $screen->taxonomy ?? '' );
		$enabled  = in_array( $taxonomy, hodima_seo_discover_taxonomies(), true );
	}

	return (bool) apply_filters( 'hook_modern_seo_enabled', $enabled, $context, $object_id ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- نام فیلتر قبلی سیستم رسانه
}

/** آیا دیسکاور برای این نوشته/محصول فعال است؟ */
function hodima_seo_discover_for_post( int $post_id ): bool {
	return $post_id > 0 && hodima_seo_discover_enabled( 'post', $post_id );
}

/** آیا دیسکاور برای این دسته فعال است؟ */
function hodima_seo_discover_for_term( int $term_id ): bool {
	return $term_id > 0 && hodima_seo_discover_enabled( 'term', $term_id );
}

/** «post» یا «term». */
function hodima_seo_discover_context( string $context ): string {
	return 'term' === $context ? 'term' : 'post';
}

/**
 * موضوعات اصلی: هر مورد نام و (اختیاری) آدرس هویت آن در ویکی‌داده/ویکی‌پدیا.
 *
 *   کش مو
 *   کلیپس https://www.wikidata.org/wiki/Q1234
 *   گلسر Q5678                ← شناسه ویکی‌داده خودش آدرس می‌شود
 *
 * آدرس، موضوع را برای گوگل بی‌ابهام می‌کند (sameAs): «کلیپس» دقیقا کدام
 * چیز است. جداکننده‌ها: خط جدید، «,» «،» «;» «؛» «|». یکتا بر اساس نام.
 *
 * @return list<array{name: string, url: string}>
 */
function hodima_seo_discover_entity_items( mixed $raw ): array {

	$parts = preg_split( '/[\r\n,،;؛|]+/u', is_scalar( $raw ) ? (string) $raw : '' ) ?: [];
	$out   = [];

	foreach ( $parts as $part ) {

		$part = trim( wp_strip_all_tags( $part ) );
		$url  = '';

		if ( preg_match( '#^(.*?)\s+(https?://\S+|Q\d{1,12})$#u', $part, $m ) ) {
			$part = $m[1];
			$url  = str_starts_with( $m[2], 'Q' ) ? 'https://www.wikidata.org/wiki/' . $m[2] : hodima_seo_discover_clean_url( $m[2] );
		}

		$name = trim( str_replace( "\u{200C}", ' ', $part ) );
		$key  = mb_strtolower( $name );

		// تکراری: اولی می‌ماند، ولی آدرسش اگر نداشت از تکرار بعدی گرفته می‌شود
		if ( '' !== $name && mb_strlen( $name ) <= 120 ) {
			$out[ $key ] = isset( $out[ $key ] )
				? [ 'name' => $out[ $key ]['name'], 'url' => '' !== $out[ $key ]['url'] ? $out[ $key ]['url'] : $url ]
				: [ 'name' => $name, 'url' => $url ];
		}
	}

	return array_values( $out );
}

/** آدرس معتبر http(s) با دامنه، یا رشته خالی (متن دلخواه آدرس جعلی «http://…» نشود). */
function hodima_seo_discover_clean_url( string $url ): string {
	$url  = trim( $url );
	$host = (string) wp_parse_url( $url, PHP_URL_HOST );
	return ( false !== filter_var( $url, FILTER_VALIDATE_URL ) && preg_match( '#^https?://#i', $url ) && str_contains( $host, '.' ) )
		? esc_url_raw( $url, [ 'https', 'http' ] )
		: '';
}

/**
 * نام موضوعات (بدون آدرس).
 *
 * @return list<string>
 */
function hodima_seo_discover_parse_entities( mixed $raw ): array {
	return array_column( hodima_seo_discover_entity_items( $raw ), 'name' );
}

/**
 * موضوعات به شکل ذخیره («نام آدرس, نام»).
 *
 * @param list<array{name: string, url: string}> $items
 */
function hodima_seo_discover_format_entities( array $items, string $glue = ', ' ): string {
	return implode( $glue, array_map(
		static fn( array $i ): string => trim( $i['name'] . ( '' !== $i['url'] ? ' ' . $i['url'] : '' ) ),
		$items
	) );
}

/**
 * داده دیسکاور یک نوشته.
 *
 * داده خیلی قدیمی (پیش از نسخه ۴ سیستم رسانه) بدون پیشوند ذخیره شده بود؛
 * فقط وقتی خوانده می‌شود که نوشته هرگز با کادر رسانه جدید ذخیره نشده
 * (ردیف «_hook_enabled» ندارد) و نشانه نسخه قدیمی (enabled = yes) را دارد —
 * همان قاعده hodima_media_has_legacy_data().
 *
 * entities: فقط نام‌ها (سازگار با نسخه قبلی)؛ entity_items: نام + آدرس هویت.
 *
 * @return array{title: string, image_id: int, entities: list<string>, entity_items: list<array{name: string, url: string}>}
 */
function hodima_seo_discover_data( int $object_id, string $context = 'post' ): array {

	$context = hodima_seo_discover_context( $context );

	if ( $object_id <= 0 ) {
		return [ 'title' => '', 'image_id' => 0, 'entities' => [], 'entity_items' => [] ];
	}

	// داده بی‌پیشوند خیلی قدیمی فقط برای نوشته‌ها وجود داشت
	$legacy = 'post' === $context && ! metadata_exists( 'post', $object_id, '_hook_enabled' ) && 'yes' === get_post_meta( $object_id, 'enabled', true );
	$read   = static function ( string $field, string $old ) use ( $object_id, $context, $legacy ): string {
		$value = (string) get_metadata( $context, $object_id, hodima_seo_discover_meta_key( $field, $context ), true );
		return ( '' === $value && $legacy ) ? (string) get_post_meta( $object_id, $old, true ) : $value;
	};

	$items = hodima_seo_discover_entity_items( $read( 'entities', 'key_entities' ) );

	return [
		'title'        => sanitize_text_field( $read( 'title', 'discover_title' ) ),
		'image_id'     => (int) get_metadata( $context, $object_id, hodima_seo_discover_meta_key( 'image_id', $context ), true ),
		'entities'     => array_column( $items, 'name' ),
		'entity_items' => $items,
	];
}

/** عنوان اصلی شیء (نام نوشته/محصول یا نام دسته). */
function hodima_seo_discover_object_title( int $object_id, string $context = 'post' ): string {
	if ( 'term' === hodima_seo_discover_context( $context ) ) {
		$term = get_term( $object_id );
		return $term instanceof WP_Term ? $term->name : '';
	}
	return (string) get_the_title( $object_id );
}

/** آدرس عمومی شیء. */
function hodima_seo_discover_object_url( int $object_id, string $context = 'post' ): string {
	if ( 'term' === hodima_seo_discover_context( $context ) ) {
		$link = get_term_link( $object_id );
		return is_wp_error( $link ) ? '' : (string) $link;
	}
	return (string) ( get_permalink( $object_id ) ?: '' );
}

/** تصویر پیش‌فرض شیء: تصویر شاخص نوشته/محصول، یا تصویر دسته. */
function hodima_seo_discover_default_image_id( int $object_id, string $context = 'post' ): int {
	if ( 'term' === hodima_seo_discover_context( $context ) ) {
		return (int) ( get_term_meta( $object_id, 'thumbnail_id', true ) ?: get_term_meta( $object_id, 'category_image_id', true ) );
	}
	return (int) get_post_thumbnail_id( $object_id );
}

/**
 * یکسان‌سازی متن فارسی برای مقایسه: نیم‌فاصله ← فاصله، بدون اعراب
 * («حتماً» = «حتما»)، «ي/ك» عربی ← «ی/ک»، فاصله‌های پشت هم یکی، حروف کوچک.
 * همان قاعده در JS پیشخوان (discover-admin.js: norm).
 */
function hodima_seo_discover_text_norm( string $text ): string {
	$text = str_replace( [ "\u{200C}", 'ي', 'ك' ], [ ' ', 'ی', 'ک' ], $text );
	$text = (string) preg_replace( '/[\x{064B}-\x{0652}\x{0670}]/u', '', $text );
	return mb_strtolower( trim( (string) preg_replace( '/\s+/u', ' ', $text ) ) );
}

/**
 * فهرست عبارت‌های طعمه کلیک (قابل تغییر با فیلتر).
 *
 * @return list<string>
 */
function hodima_seo_discover_clickbait_phrases(): array {
	$phrases = apply_filters( 'hodima_seo_discover_clickbait_phrases', HODIMA_SEO_DISCOVER_CLICKBAIT );
	return array_values( array_filter( array_map( 'strval', is_array( $phrases ) ? $phrases : [] ), static fn( string $p ): bool => '' !== trim( $p, " *\t" ) ) );
}

/**
 * عبارت طعمه کلیکی که عنوان دارد (بدون «*»)، یا رشته خالی.
 *
 * عبارت حرف‌دار فقط به‌صورت کلمه کامل پیدا می‌شود: پیش از آن حرف نباشد و
 * (بدون «*») بعد از آن هم نه. نشانه‌گذاری («!!») هر جا باشد.
 */
function hodima_seo_discover_clickbait_match( string $title ): string {

	static $patterns = null;

	if ( null === $patterns ) {
		$patterns = [];
		foreach ( hodima_seo_discover_clickbait_phrases() as $raw ) {
			$phrase = hodima_seo_discover_text_norm( $raw );
			$prefix = str_ends_with( $phrase, '*' );
			$phrase = rtrim( $phrase, ' *' );
			$letter = 1 === preg_match( '/\p{L}/u', $phrase );
			$body   = str_replace( ' ', '\s+', preg_quote( $phrase, '/' ) );

			$patterns[ rtrim( $raw, ' *' ) ] = '/' . ( $letter ? '(?<![\p{L}\p{M}\p{N}])' : '' ) . $body . ( $letter && ! $prefix ? '(?![\p{L}\p{M}\p{N}])' : '' ) . '/u';
		}
	}

	$title = hodima_seo_discover_text_norm( $title );

	foreach ( $patterns as $phrase => $pattern ) {
		if ( 1 === preg_match( $pattern, $title ) ) {
			return (string) $phrase;
		}
	}
	return '';
}

/** آیا عنوان عبارت طعمه کلیک دارد؟ (همان قاعده در JS پیشخوان) */
function hodima_seo_discover_is_clickbait( string $title ): bool {
	return '' !== hodima_seo_discover_clickbait_match( $title );
}

/* =====================================================================
 * تصویر دیسکاور و برش‌ها
 * ===================================================================== */

/**
 * تصویر دیسکاور یک نوشته/محصول/دسته: تصویر انتخابی دیسکاور ← تصویر
 * شاخص (نوشته/محصول) یا تصویر دسته.
 *
 * @return array{id: int, url: string, width: int, height: int, mime: string, alt: string}|null
 */
function hodima_seo_discover_image( int $object_id, string $context = 'post' ): ?array {

	$id = hodima_seo_discover_data( $object_id, $context )['image_id'];

	if ( ! $id || ! wp_attachment_is_image( $id ) ) {
		$id = hodima_seo_discover_default_image_id( $object_id, $context );
	}

	$src = $id ? wp_get_attachment_image_src( $id, 'full' ) : false;

	if ( ! is_array( $src ) || empty( $src[0] ) ) {
		return null;
	}

	return [
		'id'     => $id,
		'url'    => (string) $src[0],
		'width'  => (int) $src[1],
		'height' => (int) $src[2],
		'mime'   => (string) get_post_mime_type( $id ),
		'alt'    => (string) ( get_post_meta( $id, '_wp_attachment_image_alt', true ) ?: hodima_seo_discover_object_title( $object_id, $context ) ),
	];
}

/**
 * ابعاد برش با عرض حداکثر ۱۲۰۰ که در تصویر اصلی جا شود؛ [0, 0] اگر خیلی
 * کوچک است (کمتر از ۵۰ هزار پیکسل، حداقل گوگل برای تصویر مقاله).
 *
 * @return array{0: int, 1: int}
 */
function hodima_seo_discover_crop_size( int $src_w, int $src_h, int $rw, int $rh ): array {

	$w = min( HODIMA_SEO_DISCOVER_MIN_WIDTH, $src_w, intdiv( $src_h * $rw, $rh ) );
	$h = intdiv( $w * $rh, $rw );

	return ( $w > 0 && $w * $h >= 50000 ) ? [ $w, $h ] : [ 0, 0 ];
}

/** متای پیوست: نقطه تمرکز برش‌ها «x,y» (۰ تا ۱ از چپ و بالا؛ نبودن = وسط). */
const HODIMA_SEO_DISCOVER_FOCUS_META = '_hodima_discover_focus';

/**
 * نقطه تمرکز یک تصویر: [ x, y ] بین ۰ و ۱ (دو رقم اعشار)، پیش‌فرض وسط.
 *
 * مال خود پیوست است، نه صفحه: هر صفحه‌ای که این تصویر را دارد همان برش‌ها را
 * می‌گیرد (برش‌ها هم در اطلاعات همان پیوست ذخیره می‌شوند).
 *
 * @return array{0: float, 1: float}
 */
function hodima_seo_discover_focus( int $attachment_id ): array {
	return hodima_seo_discover_parse_focus( (string) get_post_meta( $attachment_id, HODIMA_SEO_DISCOVER_FOCUS_META, true ) ) ?? [ 0.5, 0.5 ];
}

/**
 * «x,y» ← [ x, y ] محدود به ۰ تا ۱، یا null اگر نامعتبر است.
 *
 * @return array{0: float, 1: float}|null
 */
function hodima_seo_discover_parse_focus( string $raw ): ?array {
	if ( 1 !== preg_match( '/^\s*(-?[\d.]+)\s*,\s*(-?[\d.]+)\s*$/', $raw, $m ) ) {
		return null;
	}
	$clamp = static fn( string $v ): float => round( max( 0.0, min( 1.0, (float) $v ) ), 2 );
	return [ $clamp( $m[1] ), $clamp( $m[2] ) ];
}

/**
 * @param array{0: float, 1: float} $focus
 */
function hodima_seo_discover_focus_string( array $focus ): string {
	return sprintf( '%.2F,%.2F', $focus[0], $focus[1] );
}

/**
 * ذخیره نقطه تمرکز یک تصویر (وسط = حذف متا). true اگر عوض شد؛ برش‌ها در
 * ذخیره بعدی (یا همین ذخیره صفحه) با نقطه تازه دوباره ساخته می‌شوند.
 */
function hodima_seo_discover_set_focus( int $attachment_id, string $raw ): bool {

	$focus = hodima_seo_discover_parse_focus( $raw );

	if ( null === $focus || ! wp_attachment_is_image( $attachment_id ) ) {
		return false;
	}

	$value = hodima_seo_discover_focus_string( $focus );

	if ( hodima_seo_discover_focus_string( hodima_seo_discover_focus( $attachment_id ) ) === $value ) {
		return false;
	}

	'0.50,0.50' === $value
		? delete_post_meta( $attachment_id, HODIMA_SEO_DISCOVER_FOCUS_META )
		: update_post_meta( $attachment_id, HODIMA_SEO_DISCOVER_FOCUS_META, $value );

	return true;
}

/**
 * ناحیه برش در تصویر اصلی: بزرگ‌ترین ناحیه با نسبت rw:rh که مرکزش تا جای ممکن
 * روی نقطه تمرکز است (بیرون از تصویر نمی‌رود). همان حساب در JS پیش‌نمایش
 * (discover-admin.js: focusPosition).
 *
 * @return array{x: int, y: int, w: int, h: int}
 */
function hodima_seo_discover_crop_rect( int $src_w, int $src_h, int $rw, int $rh, float $fx, float $fy ): array {

	if ( $src_w * $rh > $src_h * $rw ) {   // پهن‌تر از نسبت: تمام ارتفاع
		$h = $src_h;
		$w = (int) round( $src_h * $rw / $rh );
	} else {                               // بلندتر: تمام عرض
		$w = $src_w;
		$h = (int) round( $src_w * $rh / $rw );
	}

	$x = (int) round( max( 0, min( $src_w - $w, $fx * $src_w - $w / 2 ) ) );
	$y = (int) round( max( 0, min( $src_h - $h, $fy * $src_h - $h / 2 ) ) );

	return [ 'x' => $x, 'y' => $y, 'w' => $w, 'h' => $h ];
}

/**
 * سه برش دیسکاور را (اگر نیست یا نقطه تمرکز عوض شده) برای یک پیوست می‌سازد و
 * تعداد برش تازه را برمی‌گرداند. فقط هنگام ذخیره نوشته/تغییر تصویر شاخص و
 * «ساخت برش برای همه» (گزارش) اجرا می‌شود، نه هنگام بازدید.
 * برش‌ها در اطلاعات همان پیوست («sizes») ثبت می‌شوند؛ با حذف تصویر،
 * وردپرس آن‌ها را هم پاک می‌کند. (اندازه تصویر سراسری ثبت نشد تا هر آپلود
 * سه فایل اضافه نسازد.)
 *
 * SEO 2.1.6: برش دور «نقطه تمرکز» (hodima_seo_discover_focus) بریده می‌شود، نه
 * همیشه از وسط (سوژه کنار کادر، مثلا محصول، بریده می‌شد). نقطه غیر از وسط در
 * نام فایل می‌آید (…-1200x675-f30-40.jpg) تا با عوض شدنش آدرس og:image هم عوض
 * شود و کش‌ها (لایت‌اسپید، گوگل، شبکه‌ها) تصویر کهنه نشان ندهند؛ فایل برش
 * قبلی اگر اندازه دیگری از آن استفاده نکند، پاک می‌شود. برش وسط همان نام
 * قبلی را دارد، پس برش‌های موجود دوباره ساخته نمی‌شوند.
 */
function hodima_seo_discover_make_crops( int $attachment_id ): int {

	if ( ! $attachment_id || ! wp_attachment_is_image( $attachment_id ) ) {
		return 0;
	}

	$meta = wp_get_attachment_metadata( $attachment_id );
	$file = (string) get_attached_file( $attachment_id );

	if ( ! is_array( $meta ) || empty( $meta['width'] ) || '' === $file || ! is_file( $file ) ) {
		return 0;
	}

	$src_w      = (int) $meta['width'];
	$src_h      = (int) $meta['height'];
	$focus      = hodima_seo_discover_focus( $attachment_id );
	$focus_key  = hodima_seo_discover_focus_string( $focus );
	$dir        = dirname( $file );
	$made_count = 0;

	foreach ( HODIMA_SEO_DISCOVER_CROPS as $key => [ $rw, $rh ] ) {

		$name      = 'hodima-discover-' . $key;
		[ $w, $h ] = hodima_seo_discover_crop_size( $src_w, $src_h, $rw, $rh );
		$existing  = $meta['sizes'][ $name ] ?? null;

		// بدون برش: خیلی کوچک، یا خود تصویر اصلی دقیقا همین نسبت را دارد
		if ( ! $w || ( $w === $src_w && $h === $src_h ) ) {
			continue;
		}

		// برش‌های قبل از SEO 2.1.6 کلید نقطه ندارند و از وسط‌اند
		if ( is_array( $existing ) && (int) $existing['width'] === $w && (int) $existing['height'] === $h
			&& ( $existing['hodima_focus'] ?? '0.50,0.50' ) === $focus_key
			&& is_file( $dir . '/' . $existing['file'] ) ) {
			continue;
		}

		$editor = wp_get_image_editor( $file );
		if ( is_wp_error( $editor ) ) {
			continue;
		}

		$rect   = hodima_seo_discover_crop_rect( $src_w, $src_h, $rw, $rh, $focus[0], $focus[1] );
		$suffix = "{$w}x{$h}" . ( '0.50,0.50' === $focus_key ? '' : sprintf( '-f%d-%d', (int) round( 100 * $focus[0] ), (int) round( 100 * $focus[1] ) ) );

		if ( is_wp_error( $editor->crop( $rect['x'], $rect['y'], $rect['w'], $rect['h'], $w, $h ) ) ) {
			continue;
		}

		$saved = $editor->save( $editor->generate_filename( $suffix ) );

		if ( is_wp_error( $saved ) || empty( $saved['file'] ) ) {
			continue;
		}

		$old = is_array( $existing ) ? (string) ( $existing['file'] ?? '' ) : '';

		$meta['sizes'][ $name ] = [
			'file'         => (string) $saved['file'],
			'width'        => (int) $saved['width'],
			'height'       => (int) $saved['height'],
			'mime-type'    => (string) $saved['mime-type'],
			'filesize'     => (int) ( $saved['filesize'] ?? 0 ),
			'hodima_focus' => $focus_key,
		];
		++$made_count;

		// فایل برش قبلی (نقطه دیگر) اگر اندازه دیگری یا خود اصل از آن استفاده نمی‌کند
		if ( '' !== $old && $old !== $saved['file'] && $old !== basename( $file ) && ! in_array( $old, array_column( $meta['sizes'], 'file' ), true ) ) {
			wp_delete_file( $dir . '/' . $old );
		}
	}

	if ( $made_count ) {
		wp_update_attachment_metadata( $attachment_id, $meta );
	}

	return $made_count;
}

/**
 * تصاویر دیسکاور آماده (برش‌های ساخته‌شده؛ یا خود تصویر وقتی همان نسبت را دارد).
 *
 * @return list<array{url: string, width: int, height: int, ratio: string, mime: string}>
 */
function hodima_seo_discover_images( int $object_id, string $context = 'post' ): array {

	$image = hodima_seo_discover_image( $object_id, $context );

	if ( null === $image ) {
		return [];
	}

	$meta = wp_get_attachment_metadata( $image['id'] );
	$out  = [];

	foreach ( HODIMA_SEO_DISCOVER_CROPS as $key => [ $rw, $rh ] ) {

		if ( is_array( $meta ) && isset( $meta['sizes'][ 'hodima-discover-' . $key ] ) ) {
			$src = wp_get_attachment_image_src( $image['id'], 'hodima-discover-' . $key );
			if ( is_array( $src ) && ! empty( $src[0] ) ) {
				// نوع فایل خود برش (اگر سایت خروجی WebP را روشن کند، با اصل فرق دارد)
				$mime  = (string) ( $meta['sizes'][ 'hodima-discover-' . $key ]['mime-type'] ?? $image['mime'] );
				$out[] = [ 'url' => (string) $src[0], 'width' => (int) $src[1], 'height' => (int) $src[2], 'ratio' => $key, 'mime' => $mime ];
				continue;
			}
		}

		// خود تصویر همین نسبت را دارد (مثلا اصل ۱۲۰۰×۶۷۵)
		if ( $image['height'] > 0 && abs( $image['width'] / $image['height'] - $rw / $rh ) < 0.01 ) {
			$out[] = [ 'url' => $image['url'], 'width' => $image['width'], 'height' => $image['height'], 'ratio' => $key, 'mime' => $image['mime'] ];
		}
	}

	return $out;
}

/**
 * برش ۱۶:۹ آماده، یا null.
 *
 * @return array{url: string, width: int, height: int, ratio: string, mime: string}|null
 */
function hodima_seo_discover_wide_image( int $object_id, string $context = 'post' ): ?array {
	foreach ( hodima_seo_discover_images( $object_id, $context ) as $image ) {
		if ( '16x9' === $image['ratio'] ) {
			return $image;
		}
	}
	return null;
}

/**
 * تصویر دیسکاور و برش‌هایش به شکل ImageObject اسکیما: اول خود تصویر (همان
 * og:image و #primaryimage)، بعد برش‌های ۱۶:۹، ۴:۳ و ۱:۱ (بدون تکرار).
 * تا SEO 2.1.4 برش‌ها فقط در اسکیمای مقاله بودند؛ برگه، محصول و دسته محصول
 * فقط og:image داشتند.
 *
 * @return list<array{'@type': string, url: string, width: int, height: int}>
 */
function hodima_seo_discover_image_objects( int $object_id, string $context = 'post' ): array {

	$image = hodima_seo_discover_image( $object_id, $context );

	if ( null === $image ) {
		return [];
	}

	$out  = [];
	$seen = [];

	foreach ( [ $image, ...hodima_seo_discover_images( $object_id, $context ) ] as $item ) {
		if ( isset( $seen[ $item['url'] ] ) ) {
			continue;
		}
		$seen[ $item['url'] ] = true;
		$out[]                = [ '@type' => 'ImageObject', 'url' => $item['url'], 'width' => $item['width'], 'height' => $item['height'] ];
	}

	return $out;
}

/* =====================================================================
 * اسکیما
 * ===================================================================== */

/**
 * عنوان دیسکاور و موضوعات برای یک نود مقاله/صفحه:
 *   alternativeHeadline (headline همان عنوان اصلی صفحه می‌ماند)
 *   about → Thing برای هر موضوع، با sameAs اگر آدرس ویکی‌داده/ویکی‌پدیا دارد
 *   (به ارجاع‌های موجود، مثلا #organization، اضافه می‌شود)
 *
 * @param array<string, mixed> $node
 * @return array<string, mixed>
 */
function hodima_seo_discover_enrich( array $node, int $object_id, string $context = 'post' ): array {

	$data = hodima_seo_discover_data( $object_id, $context );

	if ( '' !== $data['title'] ) {
		$node['alternativeHeadline'] = $data['title'];
	}

	if ( $data['entity_items'] ) {
		$about = $node['about'] ?? [];
		$about = ( is_array( $about ) && array_is_list( $about ) ) ? $about : ( $about ? [ $about ] : [] );

		foreach ( $data['entity_items'] as $item ) {
			$about[] = [ '@type' => 'Thing', 'name' => $item['name'] ] + ( '' !== $item['url'] ? [ 'sameAs' => $item['url'] ] : [] );
		}

		$node['about'] = $about;
	}

	return $node;
}

/* =====================================================================
 * نویسنده (E-E-A-T)
 * ===================================================================== */

/** کلیدهای متای کاربر برای معرفی نویسنده. */
const HODIMA_SEO_DISCOVER_AUTHOR_META = [
	'job_title'   => 'hodima_author_job_title',
	'knows_about' => 'hodima_author_knows_about',
	'same_as'     => 'hodima_author_same_as',
];

/**
 * معرفی تخصص نویسنده: سمت، حوزه‌های تخصص و پروفایل‌های معتبر بیرونی
 * (اینستاگرام، لینکدین، آپارات، ویکی‌پدیا…). گوگل برای دیسکاور و نتایج
 * مقاله به «چه کسی نوشته و چرا قابل اعتماد است» وزن می‌دهد.
 *
 * @return array{job_title: string, knows_about: list<string>, same_as: list<string>}
 */
function hodima_seo_discover_author( int $user_id ): array {

	$lines = static fn( string $key ): array => array_values( array_filter( array_map(
		'trim',
		preg_split( '/[\r\n]+/', (string) get_user_meta( $user_id, $key, true ) ) ?: []
	) ) );

	return [
		'job_title'   => sanitize_text_field( (string) get_user_meta( $user_id, HODIMA_SEO_DISCOVER_AUTHOR_META['job_title'], true ) ),
		'knows_about' => array_map( 'sanitize_text_field', $lines( HODIMA_SEO_DISCOVER_AUTHOR_META['knows_about'] ) ),
		'same_as'     => array_values( array_filter( array_map( 'hodima_seo_discover_clean_url', $lines( HODIMA_SEO_DISCOVER_AUTHOR_META['same_as'] ) ) ) ),
	];
}

/* =====================================================================
 * آمار سرچ کنسول (فقط خواندن داده ذخیره‌شده؛ دریافت: discover-stats.php)
 * ===================================================================== */

/** نام گزینه آمار دیسکاور (autoload خاموش). */
const HODIMA_SEO_DISCOVER_STATS_OPTION = 'hodima_discover_sc_stats';

/** پارامترهای ردیابی که آدرس دیگری نمی‌سازند (سرچ کنسول گاهی با آن‌ها گزارش می‌دهد؛ srsltid = لینک‌های فروشگاهی گوگل). */
const HODIMA_SEO_DISCOVER_TRACKING_PARAMS = '/^(utm_[a-z_]+|gclid|gbraid|wbraid|fbclid|srsltid|_gl|mc_[a-z]+)$/i';

/**
 * کلید یکسان یک آدرس برای مقایسه با آدرس‌های سرچ کنسول: مسیر decode‌شده
 * بدون اسلش پایانی، و پارامترها بدون پارامترهای ردیابی (مرتب‌شده).
 * باگ قبلی (تا SEO 2.1.1): «‎/x?utm_source=…» ردیف جدا از «‎/x» شمرده می‌شد.
 */
function hodima_seo_discover_url_key( string $url ): string {

	$path  = (string) wp_parse_url( $url, PHP_URL_PATH );
	$query = [];

	wp_parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
	$query = array_filter( $query, static fn( mixed $v, int|string $k ): bool => 1 !== preg_match( HODIMA_SEO_DISCOVER_TRACKING_PARAMS, (string) $k ), ARRAY_FILTER_USE_BOTH );
	ksort( $query );

	$path = rawurldecode( untrailingslashit( $path ) );

	// صفحه اصلی «/» (قبلا رشته خالی بود و در جدول آمار بی‌نام دیده می‌شد)
	return ( '' !== $path ? $path : '/' ) . ( $query ? '?' . http_build_query( $query ) : '' );
}

/**
 * آمار ذخیره‌شده دیسکاور.
 *
 *   totals/rows: ۲۸ روز آخر (هر مسیر ← کلیک/نمایش)
 *   prev:        ۲۸ روز پیش از آن (برای مقایسه و «افت نمایش»)
 *   daily:       روز به روز کل سایت (حداکثر ۹۰ روز؛ نمودار)
 *
 * @return array{property: string, fetched: int, start: string, end: string, totals: array{clicks: int, impressions: int}, rows: array<string, array{clicks: int, impressions: int}>, prev: array{start: string, end: string, totals: array{clicks: int, impressions: int}, rows: array<string, array{clicks: int, impressions: int}>}, daily: array<string, array{clicks: int, impressions: int}>, error: string}
 */
function hodima_seo_discover_stats(): array {

	$stored = get_option( HODIMA_SEO_DISCOVER_STATS_OPTION, [] );
	$stored = is_array( $stored ) ? $stored : [];
	$totals = static fn( mixed $t ): array => [
		'clicks'      => (int) ( is_array( $t ) ? ( $t['clicks'] ?? 0 ) : 0 ),
		'impressions' => (int) ( is_array( $t ) ? ( $t['impressions'] ?? 0 ) : 0 ),
	];
	$rows   = static function ( mixed $r ) use ( $totals ): array {
		$out = [];
		foreach ( is_array( $r ) ? $r : [] as $key => $row ) {
			$out[ '' === (string) $key ? '/' : (string) $key ] = $totals( $row ); // صفحه اصلی تا SEO 2.1.1 کلید خالی داشت
		}
		return $out;
	};
	$prev   = is_array( $stored['prev'] ?? null ) ? $stored['prev'] : [];

	return [
		'property' => (string) ( $stored['property'] ?? '' ),
		'fetched'  => (int) ( $stored['fetched'] ?? 0 ),
		'start'    => (string) ( $stored['start'] ?? '' ),
		'end'      => (string) ( $stored['end'] ?? '' ),
		'totals'   => $totals( $stored['totals'] ?? [] ),
		'rows'     => $rows( $stored['rows'] ?? [] ),
		'prev'     => [
			'start'  => (string) ( $prev['start'] ?? '' ),
			'end'    => (string) ( $prev['end'] ?? '' ),
			'totals' => $totals( $prev['totals'] ?? [] ),
			'rows'   => $rows( $prev['rows'] ?? [] ),
		],
		'daily'    => $rows( $stored['daily'] ?? [] ),
		'error'    => (string) ( $stored['error'] ?? '' ),
	];
}

/**
 * آمار دیسکاور یک نوشته/محصول/دسته (۲۸ روز آخر سرچ کنسول و ۲۸ روز پیش از
 * آن)، یا null اگر آماری نیست. prev_* = null وقتی دوره قبل گرفته نشده است.
 *
 * @return array{clicks: int, impressions: int, prev_clicks: int|null, prev_impressions: int|null}|null
 */
function hodima_seo_discover_post_stats( int $object_id, string $context = 'post' ): ?array {

	$stats = hodima_seo_discover_stats();
	$url   = hodima_seo_discover_object_url( $object_id, $context );

	if ( ! $stats['fetched'] || '' === $url ) {
		return null;
	}

	$key  = hodima_seo_discover_url_key( $url );
	$row  = $stats['rows'][ $key ] ?? null;
	$prev = '' !== $stats['prev']['start'] ? ( $stats['prev']['rows'][ $key ] ?? [ 'clicks' => 0, 'impressions' => 0 ] ) : null;

	return [
		'clicks'           => (int) ( $row['clicks'] ?? 0 ),
		'impressions'      => (int) ( $row['impressions'] ?? 0 ),
		'prev_clicks'      => null !== $prev ? $prev['clicks'] : null,
		'prev_impressions' => null !== $prev ? $prev['impressions'] : null,
	];
}

/**
 * درصد تغییر (برای «▲ ۱۲٪»)، یا null وقتی دوره قبل صفر/نامعلوم است.
 */
function hodima_seo_discover_change( int $now, ?int $before ): ?float {
	return ( null === $before || 0 === $before ) ? null : 100 * ( $now - $before ) / $before;
}

/** متن تغییر با جهت به‌صورت نوشته (نه فقط رنگ): «▲ ۱۲٪» / «▼ ۳۰٪» / «بدون تغییر». */
function hodima_seo_discover_change_text( ?float $change ): string {
	return match ( true ) {
		null === $change      => '',
		abs( $change ) < 0.5  => 'بدون تغییر',
		$change > 0           => '▲ ' . number_format_i18n( $change, 0 ) . '٪',
		default               => '▼ ' . number_format_i18n( abs( $change ), 0 ) . '٪',
	};
}
