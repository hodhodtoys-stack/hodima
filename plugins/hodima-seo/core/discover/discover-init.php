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

/** عبارت‌های طعمه کلیک (گوگل Discover عنوان اغراق‌آمیز را جریمه می‌کند). */
const HODIMA_SEO_DISCOVER_CLICKBAIT = [
	'باورتان نمی', 'باورت نمی', 'باور نمی‌کنید', 'شوکه', 'حتما ببینید', 'حتماً ببینید', 'از دست ندهید',
	'هرگز تصور', 'معجزه', 'راز ', 'فوری', '!!', '؟؟', '??',
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

	if ( is_admin() ) {
		require_once __DIR__ . '/discover-admin.php';
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
 * موضوعات اصلی به صورت آرایه (یکتا، بدون HTML، حداکثر ۱۲۰ حرف هر کدام).
 * جداکننده‌ها: خط جدید، «,» «،» «;» «؛» «|».
 *
 * @return list<string>
 */
function hodima_seo_discover_parse_entities( mixed $raw ): array {

	$parts = preg_split( '/[\r\n,،;؛|]+/u', is_scalar( $raw ) ? (string) $raw : '' ) ?: [];
	$out   = [];

	foreach ( $parts as $part ) {
		$part = trim( str_replace( "\u{200C}", ' ', wp_strip_all_tags( $part ) ) );
		if ( '' !== $part && mb_strlen( $part ) <= 120 ) {
			$out[ mb_strtolower( $part ) ] = $part;
		}
	}

	return array_values( $out );
}

/**
 * داده Discover یک نوشته.
 *
 * داده خیلی قدیمی (پیش از نسخه ۴ سیستم رسانه) بدون پیشوند ذخیره شده بود؛
 * فقط وقتی خوانده می‌شود که نوشته هرگز با کادر رسانه جدید ذخیره نشده
 * (ردیف «_hook_enabled» ندارد) و نشانه نسخه قدیمی (enabled = yes) را دارد —
 * همان قاعده hodima_media_has_legacy_data().
 *
 * @return array{title: string, image_id: int, entities: list<string>}
 */
function hodima_seo_discover_data( int $post_id ): array {

	if ( $post_id <= 0 ) {
		return [ 'title' => '', 'image_id' => 0, 'entities' => [] ];
	}

	$legacy = ! metadata_exists( 'post', $post_id, '_hook_enabled' ) && 'yes' === get_post_meta( $post_id, 'enabled', true );
	$read   = static function ( string $key, string $old ) use ( $post_id, $legacy ): string {
		$value = (string) get_post_meta( $post_id, $key, true );
		return ( '' === $value && $legacy ) ? (string) get_post_meta( $post_id, $old, true ) : $value;
	};

	return [
		'title'    => sanitize_text_field( $read( HODIMA_SEO_DISCOVER_META['title'], 'discover_title' ) ),
		'image_id' => (int) get_post_meta( $post_id, HODIMA_SEO_DISCOVER_META['image_id'], true ),
		'entities' => hodima_seo_discover_parse_entities( $read( HODIMA_SEO_DISCOVER_META['entities'], 'key_entities' ) ),
	];
}

/** آیا عنوان عبارت طعمه کلیک دارد؟ (همان فهرست در JS پیشخوان) */
function hodima_seo_discover_is_clickbait( string $title ): bool {
	foreach ( HODIMA_SEO_DISCOVER_CLICKBAIT as $phrase ) {
		if ( str_contains( $title, $phrase ) ) {
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
 *   about → Thing برای هر موضوع (به ارجاع‌های موجود، مثلا #organization، اضافه می‌شود)
 *
 * @param array<string, mixed> $node
 * @return array<string, mixed>
 */
function hodima_seo_discover_enrich( array $node, int $post_id ): array {

	$data = hodima_seo_discover_data( $post_id );

	if ( '' !== $data['title'] ) {
		$node['alternativeHeadline'] = $data['title'];
	}

	if ( $data['entities'] ) {
		$about = $node['about'] ?? [];
		$about = ( is_array( $about ) && array_is_list( $about ) ) ? $about : ( $about ? [ $about ] : [] );

		foreach ( $data['entities'] as $name ) {
			$about[] = [ '@type' => 'Thing', 'name' => $name ];
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

	// ۶. نویسنده (اعتماد: E-E-A-T)
	$bio      = trim( (string) get_the_author_meta( 'description', (int) $post->post_author ) );
	$checks[] = '' !== $bio
		? [ 'author', 'ok', 'نویسنده', 'بیوگرافی نویسنده کامل است.' ]
		: [ 'author', 'warn', 'نویسنده', 'بیوگرافی نویسنده (کاربران ← نمایه) خالی است؛ معرفی نویسنده اعتماد گوگل را بالا می‌برد.' ];

	return array_map(
		static fn( array $c ): array => array_combine( [ 'key', 'status', 'label', 'detail' ], $c ),
		$checks
	);
}
