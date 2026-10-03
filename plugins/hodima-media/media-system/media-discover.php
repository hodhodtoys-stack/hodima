<?php
/**
 * Media System — Google Discover (نوشته‌ها و برگه‌ها)
 * Path: media-system/media-discover.php
 *
 * آنچه گوگل برای Discover می‌خواهد و این فایل فراهم می‌کند:
 *
 *   ۱. تصویر بزرگ: حداقل ۱۲۰۰ پیکسل عرض، ترجیحا ۱۶:۹، با
 *      max-image-preview:large (سئوباکس پیش‌فرض همین را چاپ می‌کند).
 *      «تصویر Discover» اختیاری است؛ خالی = تصویر شاخص. از روی آن هنگام
 *      ذخیره سه برش ۱۶:۹، ۴:۳ و ۱:۱ (عرض ۱۲۰۰) ساخته می‌شود؛ گوگل برای
 *      مقاله هر سه نسبت را در اسکیما توصیه می‌کند.
 *   ۲. og:image و og:title: کارت Discover از تگ‌های Open Graph خوانده
 *      می‌شود. «عنوان Discover» فقط og:title / twitter:title می‌شود (و
 *      alternativeHeadline در اسکیما)؛ <title> و h1 همان عنوان اصلی می‌مانند.
 *   ۳. فید: دکمه «دنبال کردن» Discover از فید RSS سایت استفاده می‌کند. قالب
 *      لینک فید را در head چاپ نمی‌کرد؛ حالا چاپ می‌شود و هر نوشته در فید
 *      تصویر بزرگ (media:content) دارد.
 *   ۴. فهرست بررسی در کادر ویرایش نوشته (تصویر، عنوان، ایندکس، نویسنده…).
 *
 * هیچ‌کدام به کلید «فعال‌سازی سیستم رسانه» وابسته نیست: آن کلید نمایش
 * ویدیو/پادکست/FAQ را کنترل می‌کند؛ Discover سئوی خود نوشته است.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** برش‌های تصویر: کلید ← [ عرض نسبت, ارتفاع نسبت ]. */
const HODIMA_MEDIA_DISCOVER_CROPS = [ '16x9' => [ 16, 9 ], '4x3' => [ 4, 3 ], '1x1' => [ 1, 1 ] ];

/** حداقل عرض تصویر بزرگ Discover (مستندات گوگل). */
const HODIMA_MEDIA_DISCOVER_MIN_WIDTH = 1200;

/** عبارت‌های طعمه کلیک (گوگل Discover عنوان اغراق‌آمیز را جریمه می‌کند). */
const HODIMA_MEDIA_CLICKBAIT = [
	'باورتان نمی', 'باورت نمی', 'باور نمی‌کنید', 'شوکه', 'حتما ببینید', 'حتماً ببینید', 'از دست ندهید',
	'هرگز تصور', 'معجزه', 'راز ', 'فوری', '!!', '؟؟', '??',
];

/* =====================================================================
 * تصویر Discover و برش‌ها
 * ===================================================================== */

/**
 * تصویر Discover یک نوشته: تصویر انتخابی Discover ← تصویر شاخص.
 *
 * @return array{id: int, url: string, width: int, height: int, mime: string, alt: string}|null
 */
function hodima_media_discover_image( int $post_id ): ?array {

	$data = hodima_media_get_data( $post_id, 'post' );
	$id   = (int) ( $data['discover_image_id'] ?? 0 );

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
function hodima_media_discover_crop_size( int $src_w, int $src_h, int $rw, int $rh ): array {

	$w = min( HODIMA_MEDIA_DISCOVER_MIN_WIDTH, $src_w, intdiv( $src_h * $rw, $rh ) );
	$h = intdiv( $w * $rh, $rw );

	return ( $w > 0 && $w * $h >= 50000 ) ? [ $w, $h ] : [ 0, 0 ];
}

/**
 * سه برش Discover را (اگر نیست) برای یک پیوست می‌سازد.
 * فقط هنگام ذخیره نوشته/تغییر تصویر شاخص اجرا می‌شود، نه هنگام بازدید.
 * برش‌ها در اطلاعات همان پیوست («sizes») ثبت می‌شوند؛ با حذف تصویر،
 * وردپرس آن‌ها را هم پاک می‌کند.
 */
function hodima_media_discover_make_crops( int $attachment_id ): void {

	if ( ! $attachment_id || ! wp_attachment_is_image( $attachment_id ) ) {
		return;
	}

	$meta = wp_get_attachment_metadata( $attachment_id );
	$file = (string) get_attached_file( $attachment_id );

	if ( ! is_array( $meta ) || empty( $meta['width'] ) || '' === $file || ! is_file( $file ) ) {
		return;
	}

	$changed = false;

	foreach ( HODIMA_MEDIA_DISCOVER_CROPS as $key => [ $rw, $rh ] ) {

		$name     = 'hodima-discover-' . $key;
		[ $w, $h ] = hodima_media_discover_crop_size( (int) $meta['width'], (int) $meta['height'], $rw, $rh );
		$existing = $meta['sizes'][ $name ] ?? null;

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
function hodima_media_discover_images( int $post_id ): array {

	$image = hodima_media_discover_image( $post_id );

	if ( null === $image ) {
		return [];
	}

	$meta = wp_get_attachment_metadata( $image['id'] );
	$out  = [];

	foreach ( HODIMA_MEDIA_DISCOVER_CROPS as $key => [ $rw, $rh ] ) {

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

/** آیا Discover برای این نوشته (نوع پشتیبانی‌شده) فعال است؟ */
function hodima_media_discover_for_post( int $post_id ): bool {
	return $post_id > 0 && hodima_media_discover_enabled( 'post', $post_id );
}

// ساخت برش‌ها بعد از ذخیره نوشته (اولویت ۳۰: بعد از ذخیره کادر رسانه) …
add_action( 'save_post', static function ( int $post_id, WP_Post $post ): void {

	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ! hodima_media_discover_for_post( $post_id ) ) {
		return;
	}

	$image = hodima_media_discover_image( $post_id );
	if ( null !== $image ) {
		hodima_media_discover_make_crops( $image['id'] );
	}
}, 30, 2 );

// … و وقتی تصویر شاخص جدا (ویرایشگر بلوکی با REST) عوض می‌شود.
foreach ( [ 'added_post_meta', 'updated_post_meta' ] as $hodima_media_hook ) {
	add_action( $hodima_media_hook, static function ( int $meta_id, int $post_id, string $meta_key, mixed $value ): void {
		if ( '_thumbnail_id' === $meta_key && hodima_media_discover_for_post( $post_id ) ) {
			hodima_media_discover_make_crops( (int) $value );
		}
	}, 10, 4 );
}
unset( $hodima_media_hook );

/* =====================================================================
 * Open Graph / توییتر (فیلترهای سئوباکس)
 * ===================================================================== */

/** «عنوان Discover» → og:title و twitter:title. */
add_filter( 'hodima_seobox_social_title', static function ( string $title, int $post_id ): string {

	if ( ! hodima_media_discover_for_post( $post_id ) ) {
		return $title;
	}

	$discover = trim( (string) ( hodima_media_get_data( $post_id, 'post' )['discover_title'] ?? '' ) );

	return '' !== $discover ? $discover : $title;
}, 10, 2 );

/**
 * og:image بزرگ: برش ۱۶:۹ تصویر Discover (یا خود تصویر اگر حداقل ۱۲۰۰ عرض دارد).
 *
 * @param array{url: string, width: int|string, height: int|string, type: string, alt: string} $image
 */
add_filter( 'hodima_seobox_og_image', static function ( array $image, int $post_id ): array {

	if ( ! hodima_media_discover_for_post( $post_id ) ) {
		return $image;
	}

	$source = hodima_media_discover_image( $post_id );

	if ( null === $source ) {
		return $image;
	}

	$wide = array_values( array_filter( hodima_media_discover_images( $post_id ), static fn( array $i ): bool => '16x9' === $i['ratio'] ) )[0] ?? null;

	$pick = match ( true ) {
		null !== $wide && $wide['width'] >= HODIMA_MEDIA_DISCOVER_MIN_WIDTH => $wide,
		$source['width'] >= HODIMA_MEDIA_DISCOVER_MIN_WIDTH,
		(int) ( hodima_media_get_data( $post_id, 'post' )['discover_image_id'] ?? 0 ) === $source['id'] => $source,
		default => null,
	};

	return null === $pick ? $image : [
		'url'    => $pick['url'],
		'width'  => $pick['width'],
		'height' => $pick['height'],
		'type'   => $source['mime'],
		'alt'    => $source['alt'],
	];
}, 10, 2 );

/* =====================================================================
 * فید RSS («دنبال کردن» در Discover)
 * ===================================================================== */

/**
 * لینک فید در head. قالب automatic-feed-links را فعال نکرده و
 * feed_links_extra را هم عمدا حذف کرده (فید دیدگاه‌ها لازم نیست)؛ پس گوگل
 * فیدی برای دکمه «دنبال کردن» پیدا نمی‌کرد. فقط فید نوشته‌ها اعلام می‌شود.
 */
add_action( 'wp_head', static function (): void {

	if ( current_theme_supports( 'automatic-feed-links' ) || ! ( is_front_page() || is_home() || is_singular( 'post' ) || is_category() ) ) {
		return;
	}

	printf(
		'<link rel="alternate" type="application/rss+xml" title="%s" href="%s">' . "\n",
		esc_attr( get_bloginfo( 'name' ) . ' — نوشته‌ها' ),
		esc_url( get_feed_link() )
	);

	if ( is_category() ) {
		printf(
			'<link rel="alternate" type="application/rss+xml" title="%s" href="%s">' . "\n",
			esc_attr( single_cat_title( '', false ) ),
			esc_url( get_category_feed_link( (int) get_queried_object_id() ) )
		);
	}
}, 3 );

/*
 * «بهینه‌سازی بودجه خزش» افزونه سئو (router-pruning.php) همه فیدهای پیش‌فرض
 * را به صفحه اصلی ۳۰۱ می‌کرد؛ یعنی گوگل هیچ فیدی برای «دنبال کردن» نداشت.
 * فقط فید اصلی نوشته‌ها و فید دسته‌ها باز می‌ماند؛ فید دیدگاه‌ها و فید تک‌نوشته
 * (بی‌مصرف برای خزش) مثل قبل ریدایرکت می‌شوند.
 */
add_filter( 'hodima_gi_redirect_core_feeds', static function ( $redirect ): bool {
	// فید اصلی (/feed/): وردپرس در فید is_home را false می‌گذارد؛ پس «نه آرشیو، نه جستجو»
	$posts_feed = is_feed() && ! is_comment_feed() && ! is_singular() && ! is_search() && ( ! is_archive() || is_category() );
	return (bool) $redirect && ! $posts_feed;
} );

add_action( 'rss2_ns', static function (): void {
	echo 'xmlns:media="http://search.yahoo.com/mrss/"' . "\n\t";
} );

/** تصویر بزرگ هر نوشته در فید (فید پیش‌فرض وردپرس هیچ تصویری ندارد). */
add_action( 'rss2_item', static function (): void {

	$post_id = (int) get_the_ID();

	if ( ! hodima_media_discover_for_post( $post_id ) ) {
		return;
	}

	$source = hodima_media_discover_image( $post_id );

	if ( null === $source ) {
		return;
	}

	$wide = array_values( array_filter( hodima_media_discover_images( $post_id ), static fn( array $i ): bool => '16x9' === $i['ratio'] ) )[0] ?? $source;

	printf(
		"\t\t<media:content url=\"%s\" medium=\"image\" type=\"%s\" width=\"%d\" height=\"%d\" />\n",
		esc_url( $wide['url'] ),
		esc_attr( $source['mime'] ),
		$wide['width'],
		$wide['height']
	);
} );

/* =====================================================================
 * فهرست بررسی Discover (کادر ویرایش نوشته)
 * ===================================================================== */

/** آیا عنوان عبارت طعمه کلیک دارد؟ (همان فهرست در JS پیشخوان) */
function hodima_media_is_clickbait( string $title ): bool {
	foreach ( HODIMA_MEDIA_CLICKBAIT as $phrase ) {
		if ( str_contains( $title, $phrase ) ) {
			return true;
		}
	}
	return false;
}

/**
 * وضعیت آمادگی یک نوشته برای Discover.
 *
 * @return list<array{key: string, status: string, label: string, detail: string}>
 *   status: ok | warn | error
 */
function hodima_media_discover_checks( WP_Post $post ): array {

	$data   = hodima_media_get_data( $post->ID, 'post' );
	$image  = hodima_media_discover_image( $post->ID );
	$checks = [];

	// ۱. تصویر بزرگ
	$logo_ids = array_filter( [ (int) get_theme_mod( 'custom_logo' ), (int) get_option( 'site_icon' ) ] );
	$checks[] = match ( true ) {
		null === $image => [ 'image', 'error', 'تصویر بزرگ', 'تصویر شاخص یا تصویر Discover ندارد؛ Discover نوشته بی‌تصویر را تقریبا نشان نمی‌دهد.' ],
		in_array( $image['id'], $logo_ids, true ) => [ 'image', 'error', 'تصویر بزرگ', 'لوگوی سایت به عنوان تصویر انتخاب شده؛ گوگل تصویر عمومی و لوگو را نمی‌پذیرد.' ],
		$image['width'] < HODIMA_MEDIA_DISCOVER_MIN_WIDTH => [ 'image', 'error', 'تصویر بزرگ', sprintf( 'عرض تصویر %s پیکسل است؛ برای کارت بزرگ Discover حداقل ۱۲۰۰ لازم است.', number_format_i18n( $image['width'] ) ) ],
		default => [ 'image', 'ok', 'تصویر بزرگ', sprintf( '%s×%s پیکسل.', number_format_i18n( $image['width'] ), number_format_i18n( $image['height'] ) ) ],
	};

	// ۲. برش‌های ۱۶:۹ / ۴:۳ / ۱:۱
	if ( null !== $image && $image['width'] >= HODIMA_MEDIA_DISCOVER_MIN_WIDTH ) {
		$ready    = array_column( hodima_media_discover_images( $post->ID ), 'ratio' );
		$checks[] = count( $ready ) === count( HODIMA_MEDIA_DISCOVER_CROPS )
			? [ 'crops', 'ok', 'برش‌های ۱۶:۹، ۴:۳ و ۱:۱', 'ساخته شده و در اسکیما و og:image استفاده می‌شوند.' ]
			: [ 'crops', 'warn', 'برش‌های ۱۶:۹، ۴:۳ و ۱:۱', 'بعد از «به‌روزرسانی» نوشته خودکار ساخته می‌شوند.' ];
	}

	// ۳. پیش‌نمایش بزرگ تصویر و ایندکس
	$robots  = function_exists( 'seobox_normalize_robots' ) ? seobox_normalize_robots( get_post_meta( $post->ID, '_seobox_robots', true ) ) : [ 'index' => true ];
	$preview = (string) get_post_meta( $post->ID, '_seobox_adv_image', true );
	$checks[] = match ( true ) {
		'0' === (string) get_option( 'blog_public', '1' ) => [ 'robots', 'error', 'دسترسی گوگل', 'در «تنظیمات ← خواندن» گزینه پنهان کردن سایت از موتورهای جستجو روشن است.' ],
		empty( $robots['index'] ) => [ 'robots', 'error', 'دسترسی گوگل', 'این نوشته در سئوباکس noindex است و در Discover نمی‌آید.' ],
		in_array( $preview, [ 'none', 'standard' ], true ) => [ 'robots', 'error', 'دسترسی گوگل', 'در سئوباکس «پیش‌نمایش تصویر» روی ' . $preview . ' است؛ باید large باشد.' ],
		default => [ 'robots', 'ok', 'دسترسی گوگل', 'ایندکس و max-image-preview:large.' ],
	};

	// ۴. عنوان
	$discover = trim( (string) ( $data['discover_title'] ?? '' ) );
	$title    = '' !== $discover ? $discover : get_the_title( $post );
	$length   = mb_strlen( $title );
	$checks[] = match ( true ) {
		hodima_media_is_clickbait( $title ) => [ 'title', 'warn', 'عنوان', 'عبارت اغراق‌آمیز یا طعمه کلیک دارد؛ گوگل در Discover آن را جریمه می‌کند.' ],
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
