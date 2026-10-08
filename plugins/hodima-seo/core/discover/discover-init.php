<?php
/**
 * ماژول «Google Discover» — داده، تصویر و برش‌ها
 * Path: core/discover/discover-init.php
 *
 * تا Hodima SEO 1.15.0 این بخش داخل «سیستم رسانه» افزونه Hodima Media بود
 * (media-discover.php) و با خاموش شدن آن ماژول، Discover هم خاموش می‌شد؛
 * در حالی که همه مصرف‌کننده‌هایش (سئوباکس، اسکیمای مقاله، فید) اینجا،
 * در افزونه سئو هستند. حالا ماژول مستقل با کلید خودش در «ماژول‌های سئو».
 *
 * داده سایت عوض نشده (قانون ۵): همان کلیدهای متای قبلی
 *   _hook_discover_title      عنوان Discover
 *   _hook_discover_image_id   تصویر Discover (شناسه پیوست)
 *   _hook_key_entities        موضوعات اصلی («الف, ب»)
 *
 * نام توابع تازه است (hodima_seo_discover_*). نام‌های قبلی
 * (hodima_media_discover_*، hook_modern_seo_enabled) در legacy.php فقط اگر
 * تعریف نشده باشند ساخته می‌شوند.
 *
 * هم‌زیستی با Hodima Media قدیمی (۱.۴ و پایین‌تر که Discover را خودش دارد):
 * این فایل فقط تابع تعریف می‌کند؛ هوک‌ها، کادر و نام‌های قدیمی در
 * plugins_loaded اولویت ۲۰ (بعد از لود Media در اولویت ۱۰) و فقط وقتی
 * Media قدیمی Discover را نساخته ثبت می‌شوند. وگرنه همه چیز دو بار اجرا
 * می‌شد (دو لینک فید، دو تصویر در فید، دو کادر) و نام‌های قدیمی خطای
 * «Cannot redeclare» می‌دادند.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** برش‌های تصویر: کلید ← [ عرض نسبت, ارتفاع نسبت ]. */
const HODIMA_SEO_DISCOVER_CROPS = [ '16x9' => [ 16, 9 ], '4x3' => [ 4, 3 ], '1x1' => [ 1, 1 ] ];

/** حداقل عرض تصویر بزرگ Discover (مستندات گوگل). */
const HODIMA_SEO_DISCOVER_MIN_WIDTH = 1200;

/**
 * عبارت‌های طعمه کلیک و اغراق (گوگل Discover عنوان اغراق‌آمیز، پنهان‌کاری
 * محتوا و تحریک احساس را جریمه می‌کند). مقایسه بدون نیم‌فاصله انجام می‌شود
 * (hodima_seo_discover_is_clickbait) تا «باورنکردنی» و «باور نکردنی» یکی باشند.
 */
const HODIMA_SEO_DISCOVER_CLICKBAIT = [
	// باور و شگفتی
	'باورتان نمی', 'باورت نمی', 'باور نمی', 'باورنکردنی', 'باور نکردنی', 'غیرقابل باور', 'شوکه', 'شوک ', 'تکان دهنده', 'شگفت زده',
	'هرگز تصور', 'معجزه', 'جادویی',
	// پنهان‌کاری و کنجکاوی
	'راز ', 'رازهای', 'افشا', 'ببینید چه', 'ببینید چی', 'نمی دانستید', 'نمیدانستید', 'هیچ کس نمی', 'هیچکس نمی', 'حدس بزنید',
	'آخرش', 'تا آخر ببینید', 'کلیک کنید',
	// فوریت ساختگی
	'حتما ببینید', 'حتماً ببینید', 'از دست ندهید', 'فوری', 'همین الان', 'فقط امروز',
	// نشانه‌گذاری اغراق‌آمیز
	'!!', '؟؟', '??', '؟!', '!؟',
];

/** کلیدهای متا (همان کلیدهای سیستم رسانه قبلی). */
const HODIMA_SEO_DISCOVER_META = [
	'title'    => '_hook_discover_title',
	'image_id' => '_hook_discover_image_id',
	'entities' => '_hook_key_entities',
];

add_action( 'plugins_loaded', 'hodima_seo_discover_boot', 20 );

/** ثبت هوک‌ها، کادر و نام‌های قدیمی — فقط اگر Hodima Media قدیمی Discover را ندارد. */
function hodima_seo_discover_boot(): void {

	if ( hodima_seo_discover_provided_by_media() ) {
		return;
	}

	require_once __DIR__ . '/legacy.php';
	require_once __DIR__ . '/discover-front.php';
	require_once __DIR__ . '/discover-stats.php'; // WP-Cron هم در درخواست غیر پیشخوان اجرا می‌شود

	if ( is_admin() ) {
		require_once __DIR__ . '/discover-admin.php';
		require_once __DIR__ . '/discover-report.php';
	}
}

/** آیا Hodima Media قدیمی (۱.۴ و پایین‌تر) Discover را خودش ساخته است؟ */
function hodima_seo_discover_provided_by_media(): bool {
	// Media در plugins_loaded اولویت ۱۰ لود می‌شود؛ این تابع در اولویت ۲۰ صدا زده می‌شود
	return function_exists( 'hodima_media_discover_image' );
}

/* =====================================================================
 * داده
 * ===================================================================== */

/**
 * آیا Discover برای این شیء فعال است؟
 *
 *   نوشته و برگه: بله.
 *   محصول و دسته: خیر (عنوان تبلیغاتی جای نام محصول نیست و دسته نود
 *     WebPage خودش را دارد). داده ذخیره‌شده حذف نمی‌شود. برای برگرداندن:
 *     add_filter( 'hook_modern_seo_post_types', fn( $t ) => [ ...$t, 'product' ] );
 * (نام فیلترها همان نسخه‌های قبلی است تا کد سفارشی سایت کار کند.)
 */
function hodima_seo_discover_enabled( string $context, int|string $object_id = 0 ): bool {

	$enabled = false;

	if ( 'post' === $context ) {

		$post_type = ( is_numeric( $object_id ) && (int) $object_id > 0 ) ? (string) get_post_type( (int) $object_id ) : '';

		if ( '' === $post_type && function_exists( 'get_current_screen' ) && get_current_screen() ) {
			$post_type = (string) get_current_screen()->post_type;
		}

		$enabled = in_array( $post_type, (array) apply_filters( 'hook_modern_seo_post_types', [ 'post', 'page' ] ), true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- نام فیلتر قبلی سیستم رسانه؛ کد سفارشی سایت از آن استفاده می‌کند
	}

	return (bool) apply_filters( 'hook_modern_seo_enabled', $enabled, $context, $object_id ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- نام فیلتر قبلی سیستم رسانه
}

/** آیا Discover برای این نوشته (نوع پشتیبانی‌شده) فعال است؟ */
function hodima_seo_discover_for_post( int $post_id ): bool {
	return $post_id > 0 && hodima_seo_discover_enabled( 'post', $post_id );
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
 * داده Discover یک نوشته.
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
function hodima_seo_discover_data( int $post_id ): array {

	if ( $post_id <= 0 ) {
		return [ 'title' => '', 'image_id' => 0, 'entities' => [], 'entity_items' => [] ];
	}

	$legacy = ! metadata_exists( 'post', $post_id, '_hook_enabled' ) && 'yes' === get_post_meta( $post_id, 'enabled', true );
	$read   = static function ( string $key, string $old ) use ( $post_id, $legacy ): string {
		$value = (string) get_post_meta( $post_id, $key, true );
		return ( '' === $value && $legacy ) ? (string) get_post_meta( $post_id, $old, true ) : $value;
	};

	$items = hodima_seo_discover_entity_items( $read( HODIMA_SEO_DISCOVER_META['entities'], 'key_entities' ) );

	return [
		'title'        => sanitize_text_field( $read( HODIMA_SEO_DISCOVER_META['title'], 'discover_title' ) ),
		'image_id'     => (int) get_post_meta( $post_id, HODIMA_SEO_DISCOVER_META['image_id'], true ),
		'entities'     => array_column( $items, 'name' ),
		'entity_items' => $items,
	];
}

/** آیا عنوان عبارت طعمه کلیک دارد؟ (همان قاعده در JS پیشخوان) */
function hodima_seo_discover_is_clickbait( string $title ): bool {

	$norm = static fn( string $s ): string => str_replace( "\u{200C}", ' ', $s );
	$title = $norm( $title );

	foreach ( HODIMA_SEO_DISCOVER_CLICKBAIT as $phrase ) {
		if ( str_contains( $title, $norm( $phrase ) ) ) {
			return true;
		}
	}
	return false;
}

/* =====================================================================
 * تصویر Discover و برش‌ها
 * ===================================================================== */

/**
 * تصویر Discover یک نوشته: تصویر انتخابی Discover ← تصویر شاخص.
 *
 * @return array{id: int, url: string, width: int, height: int, mime: string, alt: string}|null
 */
function hodima_seo_discover_image( int $post_id ): ?array {

	$id = hodima_seo_discover_data( $post_id )['image_id'];

	if ( ! $id || ! wp_attachment_is_image( $id ) ) {
		$id = (int) get_post_thumbnail_id( $post_id );
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
		'alt'    => (string) ( get_post_meta( $id, '_wp_attachment_image_alt', true ) ?: get_the_title( $post_id ) ),
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

/**
 * سه برش Discover را (اگر نیست) برای یک پیوست می‌سازد.
 * فقط هنگام ذخیره نوشته/تغییر تصویر شاخص اجرا می‌شود، نه هنگام بازدید.
 * برش‌ها در اطلاعات همان پیوست («sizes») ثبت می‌شوند؛ با حذف تصویر،
 * وردپرس آن‌ها را هم پاک می‌کند. (اندازه تصویر سراسری ثبت نشد تا هر آپلود
 * سه فایل اضافه نسازد.)
 */
function hodima_seo_discover_make_crops( int $attachment_id ): void {

	if ( ! $attachment_id || ! wp_attachment_is_image( $attachment_id ) ) {
		return;
	}

	$meta = wp_get_attachment_metadata( $attachment_id );
	$file = (string) get_attached_file( $attachment_id );

	if ( ! is_array( $meta ) || empty( $meta['width'] ) || '' === $file || ! is_file( $file ) ) {
		return;
	}

	$changed = false;

	foreach ( HODIMA_SEO_DISCOVER_CROPS as $key => [ $rw, $rh ] ) {

		$name      = 'hodima-discover-' . $key;
		[ $w, $h ] = hodima_seo_discover_crop_size( (int) $meta['width'], (int) $meta['height'], $rw, $rh );
		$existing  = $meta['sizes'][ $name ] ?? null;

		// بدون برش: خیلی کوچک، یا خود تصویر اصلی دقیقا همین نسبت را دارد
		if ( ! $w || ( $w === (int) $meta['width'] && $h === (int) $meta['height'] ) ) {
			continue;
		}

		if ( is_array( $existing ) && (int) $existing['width'] === $w && (int) $existing['height'] === $h
			&& is_file( dirname( $file ) . '/' . $existing['file'] ) ) {
			continue;
		}

		$made = image_make_intermediate_size( $file, $w, $h, true );

		if ( is_array( $made ) ) {
			$meta['sizes'][ $name ] = $made;
			$changed                = true;
		}
	}

	if ( $changed ) {
		wp_update_attachment_metadata( $attachment_id, $meta );
	}
}

/**
 * تصاویر Discover آماده (برش‌های ساخته‌شده؛ یا خود تصویر وقتی همان نسبت را دارد).
 *
 * @return list<array{url: string, width: int, height: int, ratio: string}>
 */
function hodima_seo_discover_images( int $post_id ): array {

	$image = hodima_seo_discover_image( $post_id );

	if ( null === $image ) {
		return [];
	}

	$meta = wp_get_attachment_metadata( $image['id'] );
	$out  = [];

	foreach ( HODIMA_SEO_DISCOVER_CROPS as $key => [ $rw, $rh ] ) {

		if ( is_array( $meta ) && isset( $meta['sizes'][ 'hodima-discover-' . $key ] ) ) {
			$src = wp_get_attachment_image_src( $image['id'], 'hodima-discover-' . $key );
			if ( is_array( $src ) && ! empty( $src[0] ) ) {
				$out[] = [ 'url' => (string) $src[0], 'width' => (int) $src[1], 'height' => (int) $src[2], 'ratio' => $key ];
				continue;
			}
		}

		// خود تصویر همین نسبت را دارد (مثلا اصل ۱۲۰۰×۶۷۵)
		if ( $image['height'] > 0 && abs( $image['width'] / $image['height'] - $rw / $rh ) < 0.01 ) {
			$out[] = [ 'url' => $image['url'], 'width' => $image['width'], 'height' => $image['height'], 'ratio' => $key ];
		}
	}

	return $out;
}

/**
 * برش ۱۶:۹ آماده، یا null.
 *
 * @return array{url: string, width: int, height: int, ratio: string}|null
 */
function hodima_seo_discover_wide_image( int $post_id ): ?array {
	foreach ( hodima_seo_discover_images( $post_id ) as $image ) {
		if ( '16x9' === $image['ratio'] ) {
			return $image;
		}
	}
	return null;
}

/* =====================================================================
 * اسکیما
 * ===================================================================== */

/**
 * عنوان Discover و موضوعات برای یک نود مقاله/صفحه:
 *   alternativeHeadline (headline همان عنوان اصلی صفحه می‌ماند)
 *   about → Thing برای هر موضوع، با sameAs اگر آدرس ویکی‌داده/ویکی‌پدیا دارد
 *   (به ارجاع‌های موجود، مثلا #organization، اضافه می‌شود)
 *
 * @param array<string, mixed> $node
 * @return array<string, mixed>
 */
function hodima_seo_discover_enrich( array $node, int $post_id ): array {

	$data = hodima_seo_discover_data( $post_id );

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
 * فهرست بررسی آمادگی
 * ===================================================================== */

/**
 * وضعیت آمادگی یک نوشته برای Discover.
 *
 * @return list<array{key: string, status: string, label: string, detail: string}>
 *   status: ok | warn | error
 */
function hodima_seo_discover_checks( WP_Post $post ): array {

	$data   = hodima_seo_discover_data( $post->ID );
	$image  = hodima_seo_discover_image( $post->ID );
	$checks = [];

	// ۱. تصویر بزرگ
	$logo_ids = array_filter( [ (int) get_theme_mod( 'custom_logo' ), (int) get_option( 'site_icon' ) ] );
	$checks[] = match ( true ) {
		null === $image => [ 'image', 'error', 'تصویر بزرگ', 'تصویر شاخص یا تصویر Discover ندارد؛ Discover نوشته بی‌تصویر را تقریبا نشان نمی‌دهد.' ],
		in_array( $image['id'], $logo_ids, true ) => [ 'image', 'error', 'تصویر بزرگ', 'لوگوی سایت به عنوان تصویر انتخاب شده؛ گوگل تصویر عمومی و لوگو را نمی‌پذیرد.' ],
		$image['width'] < HODIMA_SEO_DISCOVER_MIN_WIDTH => [ 'image', 'error', 'تصویر بزرگ', sprintf( 'عرض تصویر %s پیکسل است؛ برای کارت بزرگ Discover حداقل ۱۲۰۰ لازم است.', number_format_i18n( $image['width'] ) ) ],
		default => [ 'image', 'ok', 'تصویر بزرگ', sprintf( '%s×%s پیکسل.', number_format_i18n( $image['width'] ), number_format_i18n( $image['height'] ) ) ],
	};

	// ۲. برش‌های ۱۶:۹ / ۴:۳ / ۱:۱
	if ( null !== $image && $image['width'] >= HODIMA_SEO_DISCOVER_MIN_WIDTH ) {
		$ready    = array_column( hodima_seo_discover_images( $post->ID ), 'ratio' );
		$checks[] = count( $ready ) === count( HODIMA_SEO_DISCOVER_CROPS )
			? [ 'crops', 'ok', 'برش‌های ۱۶:۹، ۴:۳ و ۱:۱', 'ساخته شده و در اسکیما و og:image استفاده می‌شوند.' ]
			: [ 'crops', 'warn', 'برش‌های ۱۶:۹، ۴:۳ و ۱:۱', 'بعد از «به‌روزرسانی» نوشته خودکار ساخته می‌شوند.' ];
	}

	// ۳. پیش‌نمایش بزرگ تصویر و ایندکس
	$robots   = function_exists( 'seobox_normalize_robots' ) ? seobox_normalize_robots( get_post_meta( $post->ID, '_seobox_robots', true ) ) : [ 'index' => true ];
	$preview  = (string) get_post_meta( $post->ID, '_seobox_adv_image', true );
	$checks[] = match ( true ) {
		'0' === (string) get_option( 'blog_public', '1' ) => [ 'robots', 'error', 'دسترسی گوگل', 'در «تنظیمات ← خواندن» گزینه پنهان کردن سایت از موتورهای جستجو روشن است.' ],
		empty( $robots['index'] ) => [ 'robots', 'error', 'دسترسی گوگل', 'این نوشته در سئوباکس noindex است و در Discover نمی‌آید.' ],
		in_array( $preview, [ 'none', 'standard' ], true ) => [ 'robots', 'error', 'دسترسی گوگل', 'در سئوباکس «پیش‌نمایش تصویر» روی ' . $preview . ' است؛ باید large باشد.' ],
		default => [ 'robots', 'ok', 'دسترسی گوگل', 'ایندکس و max-image-preview:large.' ],
	};

	// ۴. عنوان
	$title    = '' !== $data['title'] ? $data['title'] : get_the_title( $post );
	$length   = mb_strlen( $title );
	$checks[] = match ( true ) {
		hodima_seo_discover_is_clickbait( $title ) => [ 'title', 'warn', 'عنوان', 'عبارت اغراق‌آمیز یا طعمه کلیک دارد؛ گوگل در Discover آن را جریمه می‌کند.' ],
		$length < 30 => [ 'title', 'warn', 'عنوان', sprintf( '%s کاراکتر؛ کوتاه است. عنوانی که اصل مطلب را بگوید (۴۰ تا ۱۰۰ کاراکتر).', number_format_i18n( $length ) ) ],
		$length > 110 => [ 'title', 'warn', 'عنوان', sprintf( '%s کاراکتر؛ بیشتر از ۱۱۰ کوتاه می‌شود.', number_format_i18n( $length ) ) ],
		default => [ 'title', 'ok', 'عنوان', sprintf( '%s کاراکتر.', number_format_i18n( $length ) ) ],
	};

	// ۵. خلاصه
	$has_desc = '' !== trim( $post->post_excerpt ) || '' !== trim( (string) get_post_meta( $post->ID, '_seobox_description', true ) );
	$checks[] = $has_desc
		? [ 'desc', 'ok', 'خلاصه', 'چکیده یا توضیحات متا دارد.' ]
		: [ 'desc', 'warn', 'خلاصه', 'چکیده و توضیحات متا خالی است؛ متن کارت از ابتدای مطلب برداشته می‌شود.' ];

	// ۶. نویسنده (اعتماد: E-E-A-T) — بیوگرافی + سمت/تخصص یا پروفایل معتبر بیرونی
	$author   = hodima_seo_discover_author( (int) $post->post_author );
	$bio      = trim( (string) get_the_author_meta( 'description', (int) $post->post_author ) );
	$checks[] = match ( true ) {
		'' === $bio => [ 'author', 'warn', 'نویسنده', 'بیوگرافی نویسنده (کاربران ← نمایه) خالی است؛ معرفی نویسنده اعتماد گوگل را بالا می‌برد.' ],
		'' === $author['job_title'] && ! $author['same_as'] => [ 'author', 'warn', 'نویسنده', 'بیوگرافی هست؛ «سمت و تخصص» یا «پروفایل‌های معتبر» نویسنده (کاربران ← نمایه ← نویسنده در گوگل) را هم کامل کنید.' ],
		default => [ 'author', 'ok', 'نویسنده', 'بیوگرافی و معرفی تخصص نویسنده کامل است.' ],
	};

	return array_map(
		static fn( array $c ): array => array_combine( [ 'key', 'status', 'label', 'detail' ], $c ),
		$checks
	);
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
 * (اینستاگرام، لینکدین، آپارات، ویکی‌پدیا…). گوگل برای Discover و نتایج
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
 * آمار Search Console (فقط خواندن داده ذخیره‌شده؛ دریافت: discover-stats.php)
 * ===================================================================== */

/** نام گزینه آمار Discover (autoload خاموش). */
const HODIMA_SEO_DISCOVER_STATS_OPTION = 'hodima_discover_sc_stats';

/** کلید یکسان یک آدرس برای مقایسه با آدرس‌های Search Console (مسیر بدون اسلش پایانی). */
function hodima_seo_discover_url_key( string $url ): string {
	$path  = (string) wp_parse_url( $url, PHP_URL_PATH );
	$query = (string) wp_parse_url( $url, PHP_URL_QUERY );
	return rawurldecode( untrailingslashit( '' !== $path ? $path : '/' ) ) . ( '' !== $query ? '?' . $query : '' );
}

/**
 * آمار ذخیره‌شده Discover.
 *
 * @return array{property: string, fetched: int, start: string, end: string, totals: array{clicks: int, impressions: int}, rows: array<string, array{clicks: int, impressions: int}>, error: string}
 */
function hodima_seo_discover_stats(): array {

	$stored = get_option( HODIMA_SEO_DISCOVER_STATS_OPTION, [] );
	$stored = is_array( $stored ) ? $stored : [];

	return [
		'property' => (string) ( $stored['property'] ?? '' ),
		'fetched'  => (int) ( $stored['fetched'] ?? 0 ),
		'start'    => (string) ( $stored['start'] ?? '' ),
		'end'      => (string) ( $stored['end'] ?? '' ),
		'totals'   => [
			'clicks'      => (int) ( $stored['totals']['clicks'] ?? 0 ),
			'impressions' => (int) ( $stored['totals']['impressions'] ?? 0 ),
		],
		'rows'     => is_array( $stored['rows'] ?? null ) ? $stored['rows'] : [],
		'error'    => (string) ( $stored['error'] ?? '' ),
	];
}

/**
 * آمار Discover یک نوشته (۲۸ روز آخر Search Console)، یا null اگر آماری نیست.
 *
 * @return array{clicks: int, impressions: int}|null
 */
function hodima_seo_discover_post_stats( int $post_id ): ?array {

	$stats = hodima_seo_discover_stats();

	if ( ! $stats['fetched'] ) {
		return null;
	}

	$row = $stats['rows'][ hodima_seo_discover_url_key( (string) get_permalink( $post_id ) ) ] ?? null;

	return [ 'clicks' => (int) ( $row['clicks'] ?? 0 ), 'impressions' => (int) ( $row['impressions'] ?? 0 ) ];
}
