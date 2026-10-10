<?php
/**
 * ماژول «گوگل دیسکاور» — فهرست «آمادگی برای دیسکاور» و پیشنهاد عنوان
 * Path: core/discover/discover-checks.php
 *
 * فقط تابع تعریف می‌کند (از discover-init.php لود می‌شود). همین فهرست در
 * کادر ویرایش، گزارش «ابزارهای هدیما ← گوگل دیسکاور» و ستون فهرست‌ها
 * استفاده می‌شود؛ نتیجه هر شیء در کش ردیف (discover-cache.php) می‌ماند.
 *
 * SEO 2.1.2: موارد تازه — متن جایگزین تصویر، تصویر تکراری، عنوان تکراری،
 * تازگی مطلب، طول مطلب و نمایش تاریخ/نویسنده در قالب؛ پیام عنوان با همان
 * حد واقعی (قبلا زیر ۳۰ هشدار می‌داد ولی «۴۰ تا ۱۰۰» می‌نوشت) و نام عبارت
 * طعمه کلیک.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** کمترین تعداد کلمه مقاله (کمتر = «مطلب کوتاه»). */
const HODIMA_SEO_DISCOVER_MIN_WORDS = 300;

/** مقاله‌ای که این مدت به‌روز نشده، هشدار «تازگی» می‌گیرد (روز). */
const HODIMA_SEO_DISCOVER_STALE_DAYS = 365;

/* =====================================================================
 * نمایه سایت: تصویر و عنوان هر صفحه (برای «تکراری»)
 * ===================================================================== */

/** ترنزینت نمایه سایت (تصویر و عنوان همه صفحه‌ها). */
const HODIMA_SEO_DISCOVER_INDEX_CACHE = 'hodima_discover_index';

/**
 * تصویر و عنوان دیسکاور همه نوشته‌ها/محصولات منتشرشده و دسته‌ها، با دو
 * کوئری: images[شناسه پیوست] و titles[عنوان یکسان‌شده] ← فهرست کلیدهای
 * «post:ID» / «term:ID». با $reset نمایه کنار گذاشته می‌شود تا دفعه بعد از
 * نو ساخته شود (discover-cache.php: ذخیره، حذف، تغییر تصویر/عنوان).
 *
 * از SEO 2.1.5 در ترنزینت هم می‌ماند؛ قبلا با هر باز شدن کادر ویرایش و هر
 * صفحه فهرست، دو کوئری روی همه نوشته‌ها و محصولات سایت زده می‌شد.
 *
 * @return array{images: array<int, list<string>>, titles: array<string, list<string>>, keys: array<string, array{0: int, 1: string}>}
 */
function hodima_seo_discover_index( bool $reset = false ): array {

	static $index = null;

	if ( $reset ) {
		$index = null; // تصویر یا عنوانی عوض شد (discover-cache.php)
		delete_transient( HODIMA_SEO_DISCOVER_INDEX_CACHE );
		return [ 'images' => [], 'titles' => [], 'keys' => [] ];
	}

	if ( null !== $index ) {
		return $index;
	}

	$cached = get_transient( HODIMA_SEO_DISCOVER_INDEX_CACHE );
	if ( is_array( $cached ) && isset( $cached['images'], $cached['titles'], $cached['keys'] ) && is_array( $cached['images'] ) && is_array( $cached['titles'] ) && is_array( $cached['keys'] ) ) {
		$index = $cached;
		return $index;
	}

	global $wpdb;

	$index = [ 'images' => [], 'titles' => [], 'keys' => [] ];
	$add   = static function ( string $key, int $image, string $title ) use ( &$index ): void {
		if ( $image > 0 ) {
			$index['images'][ $image ][] = $key;
		}
		$title = hodima_seo_discover_text_norm( $title );
		if ( '' !== $title ) {
			$index['titles'][ $title ][] = $key;
		}
		$index['keys'][ $key ] = [ $image, $title ]; // برای امضای «تکراری» بدون گشتن کل نمایه
	};

	$types = array_values( array_filter( hodima_seo_discover_post_types(), 'post_type_exists' ) );

	if ( $types ) {
		$in   = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		$sql  = "SELECT p.ID AS id, p.post_title AS title,
				MAX( CASE WHEN m.meta_key = %s THEN m.meta_value END ) AS dtitle,
				MAX( CASE WHEN m.meta_key = %s THEN m.meta_value END ) AS dimage,
				MAX( CASE WHEN m.meta_key = '_thumbnail_id' THEN m.meta_value END ) AS thumb
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key IN ( %s, %s, '_thumbnail_id' )
			WHERE p.post_status = 'publish' AND p.post_password = '' AND p.post_type IN ( {$in} )
			GROUP BY p.ID, p.post_title";
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders -- یک کوئری برای همه صفحه‌ها (نتیجه در همین درخواست می‌ماند)؛ $in فقط «%s» به تعداد ...$types است
		$rows = $wpdb->get_results( $wpdb->prepare(
			$sql,
			HODIMA_SEO_DISCOVER_META['title'],
			HODIMA_SEO_DISCOVER_META['image_id'],
			HODIMA_SEO_DISCOVER_META['title'],
			HODIMA_SEO_DISCOVER_META['image_id'],
			...$types
		), ARRAY_A );
		// phpcs:enable

		foreach ( (array) $rows as $row ) {
			$add(
				'post:' . (int) $row['id'],
				(int) ( $row['dimage'] ?: $row['thumb'] ),
				(string) ( '' !== (string) $row['dtitle'] ? $row['dtitle'] : $row['title'] )
			);
		}
	}

	$taxonomies = array_values( array_filter( hodima_seo_discover_taxonomies(), 'taxonomy_exists' ) );

	if ( $taxonomies ) {
		$in   = implode( ',', array_fill( 0, count( $taxonomies ), '%s' ) );
		$sql  = "SELECT t.term_id AS id, t.name AS title,
				MAX( CASE WHEN m.meta_key = %s THEN m.meta_value END ) AS dtitle,
				MAX( CASE WHEN m.meta_key = %s THEN m.meta_value END ) AS dimage,
				MAX( CASE WHEN m.meta_key = 'thumbnail_id' THEN m.meta_value END ) AS thumb,
				MAX( CASE WHEN m.meta_key = 'category_image_id' THEN m.meta_value END ) AS catimg
			FROM {$wpdb->terms} t
			INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
			LEFT JOIN {$wpdb->termmeta} m ON m.term_id = t.term_id AND m.meta_key IN ( %s, %s, 'thumbnail_id', 'category_image_id' )
			WHERE tt.taxonomy IN ( {$in} )
			GROUP BY t.term_id, t.name";
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders -- همان دلیل بالا
		$rows = $wpdb->get_results( $wpdb->prepare(
			$sql,
			hodima_seo_discover_meta_key( 'title', 'term' ),
			hodima_seo_discover_meta_key( 'image_id', 'term' ),
			hodima_seo_discover_meta_key( 'title', 'term' ),
			hodima_seo_discover_meta_key( 'image_id', 'term' ),
			...$taxonomies
		), ARRAY_A );
		// phpcs:enable

		foreach ( (array) $rows as $row ) {
			$add(
				'term:' . (int) $row['id'],
				(int) ( $row['dimage'] ?: ( $row['thumb'] ?: $row['catimg'] ) ),
				(string) ( '' !== (string) $row['dtitle'] ? $row['dtitle'] : $row['title'] )
			);
		}
	}

	set_transient( HODIMA_SEO_DISCOVER_INDEX_CACHE, $index, DAY_IN_SECONDS );

	return $index;
}

/**
 * امضای «تکراری بودن» یک صفحه در نمایه فعلی: تعداد صفحه‌های هم‌تصویر و
 * صفحه هم‌عنوان. ردیف کش هر صفحه (discover-cache.php) این را نگه می‌دارد و
 * اگر عوض شده باشد (مثلا صفحه دیگری تصویرش را عوض کرد)، ردیف کهنه است.
 */
function hodima_seo_discover_dup_signature( string $self_key ): string {

	$index             = hodima_seo_discover_index();
	[ $image, $title ] = $index['keys'][ $self_key ] ?? [ 0, '' ];
	$others            = static fn( array $keys ): array => array_values( array_diff( $keys, [ $self_key ] ) );
	$images            = $others( $index['images'][ $image ] ?? [] );
	$titles            = $others( $index['titles'][ $title ] ?? [] );

	return md5( implode( '|', [ $image, count( $images ), $title, $titles[0] ?? '' ] ) );
}

/** چند صفحه دیگر همین تصویر را به‌عنوان تصویر اصلی/دیسکاور دارند؟ */
function hodima_seo_discover_image_shared( int $image_id, string $self_key ): int {
	$keys = hodima_seo_discover_index()['images'][ $image_id ] ?? [];
	return count( array_diff( $keys, [ $self_key ] ) );
}

/** کلید یک صفحه دیگر با همین عنوان کارت، یا رشته خالی. */
function hodima_seo_discover_title_twin( string $title, string $self_key ): string {
	$keys = hodima_seo_discover_index()['titles'][ hodima_seo_discover_text_norm( $title ) ] ?? [];
	return (string) ( array_values( array_diff( $keys, [ $self_key ] ) )[0] ?? '' );
}

/** نام صفحه‌ای که کلیدش «post:ID» / «term:ID» است. */
function hodima_seo_discover_key_title( string $key ): string {
	[ $context, $id ] = array_pad( explode( ':', $key, 2 ), 2, '0' );
	return hodima_seo_discover_object_title( (int) $id, $context );
}

/** تعداد کلمه متن نوشته (بدون شورت‌کد و HTML). */
function hodima_seo_discover_word_count( WP_Post $post ): int {
	$plain = trim( wp_strip_all_tags( strip_shortcodes( (string) $post->post_content ) ) );
	return '' === $plain ? 0 : count( preg_split( '/\s+/u', $plain, -1, PREG_SPLIT_NO_EMPTY ) ?: [] );
}

/* =====================================================================
 * صفحه‌هایی که برای دیسکاور نیستند (کنار گذاشته از گزارش و ستون‌ها)
 * ===================================================================== */

/** کلید متای «این صفحه برای گوگل دیسکاور نیست» (نوشته و ترم؛ '1' = کنار گذاشته). */
const HODIMA_SEO_DISCOVER_SKIP_META = '_hodima_discover_skip';

/**
 * چرا این صفحه از گزارش دیسکاور کنار گذاشته می‌شود؛ رشته خالی = بررسی می‌شود.
 *
 *   manual   مدیر در کادر دیسکاور زده «این صفحه برای گوگل دیسکاور نیست»
 *   noindex  صفحه noindex است (سئوباکس یا متای noindex قدیمی) و اصلا در گوگل نمی‌آید
 *   utility  برگه کاربردی: سبد خرید، پرداخت، حساب کاربری، شرایط، حریم خصوصی
 *
 * باگ قبلی (تا SEO 2.1.4): همه برگه‌ها بررسی می‌شدند و سبد خرید و پرداخت
 * جزو «مشکل دارد» شمرده می‌شدند. فقط گزارش، فرصت‌ها و ستون فهرست‌ها؛ خروجی
 * سایت (og:image، اسکیما، فید) عوض نمی‌شود.
 */
function hodima_seo_discover_skip_reason( WP_Post|WP_Term $target ): string {

	$context = $target instanceof WP_Term ? 'term' : 'post';
	$id      = $target instanceof WP_Term ? (int) $target->term_id : (int) $target->ID;

	if ( '1' === (string) get_metadata( $context, $id, HODIMA_SEO_DISCOVER_SKIP_META, true ) ) {
		return 'manual';
	}

	if ( function_exists( 'seobox_object_robots' ) && empty( seobox_object_robots( $id, $context )['index'] ) ) {
		return 'noindex';
	}

	if ( $target instanceof WP_Post && 'page' === $target->post_type && in_array( $id, hodima_seo_discover_utility_pages(), true ) ) {
		return 'utility';
	}

	return '';
}

/** متن فارسی دلیل کنار گذاشتن. */
function hodima_seo_discover_skip_label( string $reason ): string {
	return match ( $reason ) {
		'manual'  => 'انتخاب دستی: این صفحه برای گوگل دیسکاور نیست',
		'noindex' => 'noindex است و در گوگل نمی‌آید',
		'utility' => 'برگه کاربردی (سبد خرید، پرداخت، حساب کاربری، شرایط یا حریم خصوصی)',
		default   => '',
	};
}

/**
 * شناسه برگه‌های کاربردی سایت (ووکامرس و حریم خصوصی).
 *
 * @return list<int>
 */
function hodima_seo_discover_utility_pages(): array {

	static $ids = null;

	if ( null === $ids ) {
		$ids = [ (int) get_option( 'wp_page_for_privacy_policy' ) ];
		if ( function_exists( 'wc_get_page_id' ) ) {
			foreach ( [ 'cart', 'checkout', 'myaccount', 'terms' ] as $page ) {
				$ids[] = (int) wc_get_page_id( $page );
			}
		}
		$ids = array_values( array_filter( array_unique( (array) apply_filters( 'hodima_seo_discover_utility_pages', $ids ) ), static fn( mixed $id ): bool => (int) $id > 0 ) );
		$ids = array_map( 'intval', $ids );
	}

	return $ids;
}

/* =====================================================================
 * فهرست بررسی آمادگی
 * ===================================================================== */

/**
 * وضعیت آمادگی یک نوشته، محصول یا دسته برای دیسکاور.
 *
 * هر ردیف: key، status (ok | warn | error)، label، detail، و link (اختیاری:
 * آدرس بخشی از صفحه ویرایش یا تنظیمی که مشکل را رفع می‌کند).
 *
 * @return list<array{key: string, status: string, label: string, detail: string, link: string}>
 */
function hodima_seo_discover_checks( WP_Post|WP_Term $target ): array {

	$context  = $target instanceof WP_Term ? 'term' : 'post';
	$id       = $target instanceof WP_Term ? (int) $target->term_id : (int) $target->ID;
	$type     = $target instanceof WP_Term ? $target->taxonomy : $target->post_type;
	$self     = $context . ':' . $id;
	$is_post  = $target instanceof WP_Post && 'post' === $type; // مقاله
	$data     = hodima_seo_discover_data( $id, $context );
	$image    = hodima_seo_discover_image( $id, $context );
	$checks   = [];
	$row      = static fn( string $key, string $status, string $label, string $detail, string $link = '' ): array
		=> [ 'key' => $key, 'status' => $status, 'label' => $label, 'detail' => $detail, 'link' => $link ];
	$num      = static fn( int $n ): string => number_format_i18n( $n );

	// ۱. تصویر بزرگ
	$own      = 'term' === $context ? 'تصویر دسته' : ( 'product' === $type ? 'تصویر محصول' : 'تصویر شاخص' );
	$logo_ids = array_filter( [
		(int) get_theme_mod( 'custom_logo' ),
		(int) get_option( 'site_icon' ),
		function_exists( 'hodima_setting' ) ? (int) hodima_setting( 'logo_id' ) : 0, // لوگوی تنظیمات قالب هدیما
	] );
	$checks[] = match ( true ) {
		null === $image => $row( 'image', 'error', 'تصویر بزرگ', $own . ' یا تصویر دیسکاور ندارد؛ دیسکاور صفحه بی‌تصویر را تقریبا نشان نمی‌دهد.' ),
		in_array( $image['id'], $logo_ids, true ) => $row( 'image', 'error', 'تصویر بزرگ', 'لوگوی سایت به عنوان تصویر انتخاب شده؛ گوگل تصویر عمومی و لوگو را نمی‌پذیرد.' ),
		$image['width'] < HODIMA_SEO_DISCOVER_MIN_WIDTH => $row( 'image', 'error', 'تصویر بزرگ', sprintf( 'عرض تصویر %s پیکسل است؛ برای کارت بزرگ دیسکاور حداقل %s لازم است.', $num( $image['width'] ), $num( HODIMA_SEO_DISCOVER_MIN_WIDTH ) ) ),
		default => $row( 'image', 'ok', 'تصویر بزرگ', sprintf( '%s×%s پیکسل.', $num( $image['width'] ), $num( $image['height'] ) ) ),
	};

	if ( null !== $image ) {

		// ۲. برش‌های ۱۶:۹ / ۴:۳ / ۱:۱
		if ( $image['width'] >= HODIMA_SEO_DISCOVER_MIN_WIDTH ) {
			$ready    = array_column( hodima_seo_discover_images( $id, $context ), 'ratio' );
			$checks[] = count( $ready ) === count( HODIMA_SEO_DISCOVER_CROPS )
				? $row( 'crops', 'ok', 'برش‌های ۱۶:۹، ۴:۳ و ۱:۱', 'ساخته شده و در اسکیما و og:image استفاده می‌شوند.' )
				: $row( 'crops', 'warn', 'برش‌های ۱۶:۹، ۴:۳ و ۱:۱', 'هنوز ساخته نشده‌اند؛ با ذخیره همین صفحه یا «ساخت برش برای همه» در گزارش دیسکاور ساخته می‌شوند.' );
		}

		// ۳. متن جایگزین (خود فایل؛ hodima_seo_discover_image نبودنش را با عنوان پر می‌کند)
		$alt      = trim( (string) get_post_meta( $image['id'], '_wp_attachment_image_alt', true ) );
		$checks[] = '' !== $alt
			? $row( 'alt', 'ok', 'متن جایگزین تصویر', 'دارد.' )
			: $row( 'alt', 'warn', 'متن جایگزین تصویر', 'تصویر متن جایگزین (alt) ندارد؛ گوگل با آن می‌فهمد تصویر چه نشان می‌دهد و برای نابینایان هم لازم است.', (string) get_edit_post_link( $image['id'], 'raw' ) );

		// ۴. تصویر تکراری
		$shared   = hodima_seo_discover_image_shared( $image['id'], $self );
		$checks[] = 0 === $shared
			? $row( 'unique_image', 'ok', 'تصویر اختصاصی', 'تصویر فقط مال همین صفحه است.' )
			: $row( 'unique_image', 'warn', 'تصویر اختصاصی', sprintf( 'همین تصویر، تصویر اصلی %s صفحه دیگر هم هست؛ کارت‌های با تصویر یکسان در دیسکاور تکراری دیده می‌شوند. برای این صفحه تصویر دیسکاور جدا انتخاب کنید.', $num( $shared ) ) );
	}

	// ۵. ایندکس و پیش‌نمایش بزرگ تصویر
	$robots   = function_exists( 'seobox_object_robots' ) ? seobox_object_robots( $id, $context ) : [ 'index' => true ];
	$preview  = (string) get_metadata( $context, $id, '_seobox_adv_image', true );
	$checks[] = match ( true ) {
		'0' === (string) get_option( 'blog_public', '1' ) => $row( 'robots', 'error', 'دسترسی گوگل', 'در «تنظیمات ← خواندن» گزینه پنهان کردن سایت از موتورهای جستجو روشن است.', admin_url( 'options-reading.php' ) ),
		empty( $robots['index'] ) => $row( 'robots', 'error', 'دسترسی گوگل', 'این صفحه noindex است (سئوباکس) و در دیسکاور نمی‌آید.' ),
		in_array( $preview, [ 'none', 'standard' ], true ) => $row( 'robots', 'error', 'دسترسی گوگل', 'در سئوباکس «پیش‌نمایش تصویر» روی ' . $preview . ' است؛ باید «بزرگ» باشد.' ),
		default => $row( 'robots', 'ok', 'دسترسی گوگل', 'ایندکس و max-image-preview:large.' ),
	};

	// ۶. عنوان
	$title    = '' !== $data['title'] ? $data['title'] : hodima_seo_discover_object_title( $id, $context );
	$length   = mb_strlen( $title );
	$bait     = hodima_seo_discover_clickbait_match( $title );
	$checks[] = match ( true ) {
		'' !== $bait => $row( 'title', 'warn', 'عنوان', sprintf( 'عبارت «%s» اغراق‌آمیز یا طعمه کلیک است؛ گوگل در دیسکاور آن را جریمه می‌کند.', $bait ) ),
		$length < HODIMA_SEO_DISCOVER_TITLE_MIN => $row( 'title', 'warn', 'عنوان', sprintf( '%1$s کاراکتر؛ کوتاه است. عنوانی که اصل مطلب را بگوید: %2$s تا %3$s کاراکتر.', $num( $length ), $num( HODIMA_SEO_DISCOVER_TITLE_MIN ), $num( HODIMA_SEO_DISCOVER_TITLE_MAX ) ) ),
		$length > HODIMA_SEO_DISCOVER_TITLE_MAX => $row( 'title', 'warn', 'عنوان', sprintf( '%1$s کاراکتر؛ بیشتر از %2$s در کارت کوتاه می‌شود.', $num( $length ), $num( HODIMA_SEO_DISCOVER_TITLE_MAX ) ) ),
		default => $row( 'title', 'ok', 'عنوان', sprintf( '%s کاراکتر.', $num( $length ) ) ),
	};

	// ۷. عنوان تکراری
	$twin     = hodima_seo_discover_title_twin( $title, $self );
	$checks[] = '' === $twin
		? $row( 'unique_title', 'ok', 'عنوان اختصاصی', 'صفحه دیگری همین عنوان را ندارد.' )
		: $row( 'unique_title', 'warn', 'عنوان اختصاصی', sprintf( 'صفحه «%s» هم همین عنوان را دارد؛ گوگل از دو صفحه هم‌عنوان معمولا یکی را نشان می‌دهد.', hodima_seo_discover_key_title( $twin ) ) );

	// ۸. متن معرفی (گوگل و موتورهای پاسخ آن را به‌عنوان توضیح صفحه می‌خوانند)
	if ( 'product' === $type ) {
		$checks[] = '' !== trim( wp_strip_all_tags( $target instanceof WP_Post ? $target->post_excerpt : '' ) )
			? $row( 'intro', 'ok', 'توضیح کوتاه', 'توضیح کوتاه محصول دارد.' )
			: $row( 'intro', 'warn', 'توضیح کوتاه', 'توضیح کوتاه محصول خالی است؛ متن کارت و توضیح صفحه از آن ساخته می‌شود.', '#postexcerpt' );
	} elseif ( function_exists( 'hodima_seo_page_intro_text' ) && function_exists( 'hodima_media_get_data' ) ) {
		$anchor   = 'term' === $context ? '#hook_term_media_box' : '#hook_media_box';
		$checks[] = '' !== hodima_seo_page_intro_text( $id, $context )
			? $row( 'intro', 'ok', 'متن معرفی', 'دارد؛ در اسکیما و فایل‌های ماشین‌خوان توضیح صفحه است.' )
			: $row( 'intro', 'warn', 'متن معرفی', 'متن معرفی ندارد (یا بخشش پنهان است)؛ یک پاراگراف که اصل صفحه را بگوید، هم در صفحه دیده می‌شود هم گوگل و موتورهای پاسخ آن را می‌خوانند.', $anchor );
	}

	// ۹. خلاصه (نوشته و برگه)
	if ( 'post' === $context && 'product' !== $type ) {
		$has_desc = '' !== trim( $target instanceof WP_Post ? $target->post_excerpt : '' ) || '' !== trim( (string) get_post_meta( $id, '_seobox_description', true ) );
		$checks[] = $has_desc
			? $row( 'desc', 'ok', 'خلاصه', 'چکیده یا توضیحات متا دارد.' )
			: $row( 'desc', 'warn', 'خلاصه', 'چکیده و توضیحات متا خالی است؛ متن کارت از ابتدای مطلب برداشته می‌شود.' );
	}

	if ( $is_post && $target instanceof WP_Post ) {

		// ۱۰. طول مطلب (دیسکاور مطلب کامل و مفید را ترجیح می‌دهد)
		$words    = hodima_seo_discover_word_count( $target );
		$checks[] = $words >= HODIMA_SEO_DISCOVER_MIN_WORDS
			? $row( 'length', 'ok', 'عمق مطلب', sprintf( '%s کلمه.', $num( $words ) ) )
			: $row( 'length', 'warn', 'عمق مطلب', sprintf( '%1$s کلمه؛ مطلب کوتاه است. دیسکاور مطلبی را نشان می‌دهد که موضوع را کامل توضیح دهد (دست‌کم حدود %2$s کلمه).', $num( $words ), $num( HODIMA_SEO_DISCOVER_MIN_WORDS ) ) );

		// ۱۱. تازگی (فقط منتشرشده؛ پیش‌نویس هنوز تاریخ ندارد)
		$modified = 'publish' === $target->post_status ? get_post_datetime( $target, 'modified', 'gmt' ) : false;
		if ( $modified ) {
			$days     = max( 0, intdiv( time() - $modified->getTimestamp(), DAY_IN_SECONDS ) );
			$checks[] = $days <= HODIMA_SEO_DISCOVER_STALE_DAYS
				? $row( 'fresh', 'ok', 'تازگی', 0 === $days ? 'امروز به‌روز شده.' : sprintf( 'آخرین به‌روزرسانی %s روز پیش.', $num( $days ) ) )
				: $row( 'fresh', 'warn', 'تازگی', sprintf( 'آخرین به‌روزرسانی %s روز پیش؛ دیسکاور بیشتر مطالب تازه را نشان می‌دهد. اگر مطلب هنوز درست است، اطلاعاتش را به‌روز و دوباره منتشر کنید.', $num( $days ) ) );
		}

		// ۱۲. نمایش تاریخ و نویسنده در صفحه (تنظیمات قالب هدیما)
		if ( function_exists( 'hodima_setting' ) ) {
			$missing  = array_keys( array_filter( [
				'تاریخ'   => ! hodima_setting( 'blog_meta_date' ) && ! hodima_setting( 'blog_meta_updated' ),
				'نویسنده' => ! hodima_setting( 'blog_meta_author' ),
			] ) );
			$settings = function_exists( 'hodima_settings_url' ) ? hodima_settings_url( [ 'tab' => 'blog' ] ) : '';
			$checks[] = ! $missing
				? $row( 'byline', 'ok', 'تاریخ و نویسنده در صفحه', 'زیر عنوان مقاله نمایش داده می‌شوند.' )
				: $row( 'byline', 'warn', 'تاریخ و نویسنده در صفحه', sprintf( '%s زیر عنوان مقاله نمایش داده نمی‌شود؛ گوگل تازگی و نویسنده را از خود صفحه هم می‌خواند (تنظیمات قالب هدیما ← وبلاگ ← اطلاعات زیر عنوان مقاله).', implode( ' و ', $missing ) ), $settings );
		}
	}

	// ۱۳. نویسنده (اعتماد: E-E-A-T) — فقط نوشته و برگه
	if ( $target instanceof WP_Post && 'product' !== $type ) {
		$author   = hodima_seo_discover_author( (int) $target->post_author );
		$bio      = trim( (string) get_the_author_meta( 'description', (int) $target->post_author ) );
		$profile  = admin_url( 'user-edit.php?user_id=' . (int) $target->post_author );
		$checks[] = match ( true ) {
			'' === $bio => $row( 'author', 'warn', 'نویسنده', 'بیوگرافی نویسنده خالی است؛ معرفی نویسنده اعتماد گوگل را بالا می‌برد.', $profile ),
			'' === $author['job_title'] && ! $author['same_as'] => $row( 'author', 'warn', 'نویسنده', 'بیوگرافی هست؛ «سمت و تخصص» یا «پروفایل‌های معتبر» نویسنده را هم کامل کنید.', $profile ),
			default => $row( 'author', 'ok', 'نویسنده', 'بیوگرافی و معرفی تخصص نویسنده کامل است.' ),
		};
	}

	return $checks;
}

/**
 * امتیاز آمادگی: [ تعداد درست, کل ].
 *
 * @param list<array{status: string}> $checks
 * @return array{0: int, 1: int}
 */
function hodima_seo_discover_score( array $checks ): array {
	return [ count( array_filter( $checks, static fn( array $c ): bool => 'ok' === $c['status'] ) ), count( $checks ) ];
}

/* =====================================================================
 * پیشنهاد عنوان (بدون هوش مصنوعی؛ فقط از داده واقعی خود صفحه)
 * ===================================================================== */

/**
 * چند عنوان پیشنهادی برای کارت دیسکاور، ساخته از داده واقعی صفحه:
 *   - عنوان سئوی دستی (سئوباکس) اگر با نام صفحه فرق دارد؛
 *   - نام صفحه + دسته اصلی (زمینه: «کلیپس فلزی | گیره مو»)؛
 *   - دسته محصول: «۴۵ مدل کلیپس» از تعداد واقعی محصولات؛
 *   - نخستین جمله چکیده/توضیحات متا اگر اندازه عنوان است.
 * فقط عنوان‌های بدون طعمه کلیک، در بازه طول و غیر از عنوان فعلی.
 *
 * @return list<string>
 */
function hodima_seo_discover_title_ideas( WP_Post|WP_Term $target ): array {

	$context = $target instanceof WP_Term ? 'term' : 'post';
	$id      = $target instanceof WP_Term ? (int) $target->term_id : (int) $target->ID;
	$name    = trim( hodima_seo_discover_object_title( $id, $context ) );
	$ideas   = [];

	if ( '' === $name ) {
		return [];
	}

	$seo = trim( (string) get_metadata( $context, $id, '_seobox_title', true ) );
	if ( '' !== $seo && ! str_contains( $seo, '%' ) ) {
		$ideas[] = $seo;
	}

	if ( $target instanceof WP_Term ) {
		$parent  = $target->parent ? get_term( $target->parent ) : null;
		$scope  = $parent instanceof WP_Term ? $parent->name : (string) get_bloginfo( 'name' ); // زمینه: دسته والد یا نام فروشگاه
		if ( $target->count >= 3 ) {
			$ideas[] = sprintf( '%1$s مدل %2$s | %3$s', number_format_i18n( (int) $target->count ), $name, $scope );
		}
		if ( '' !== $scope ) {
			$ideas[] = $name . ' | ' . $scope;
		}
	} else {
		$taxonomy = 'product' === $target->post_type ? 'product_cat' : 'category';
		$primary  = (int) get_post_meta( $id, '_hodima_primary_' . $taxonomy, true );
		$term     = $primary ? get_term( $primary, $taxonomy ) : null;
		if ( ! $term instanceof WP_Term ) {
			$terms = get_the_terms( $target, $taxonomy );
			$term  = is_array( $terms ) ? ( $terms[0] ?? null ) : null;
		}
		if ( $term instanceof WP_Term && 'uncategorized' !== $term->slug && ! str_contains( hodima_seo_discover_text_norm( $name ), hodima_seo_discover_text_norm( $term->name ) ) ) {
			$ideas[] = $name . ' | ' . $term->name;
		}
	}

	// نخستین جمله چکیده یا توضیحات متا
	$summary = $target instanceof WP_Post ? trim( wp_strip_all_tags( $target->post_excerpt ) ) : '';
	$summary = '' !== $summary ? $summary : trim( (string) get_metadata( $context, $id, '_seobox_description', true ) );
	if ( '' !== $summary && ! str_contains( $summary, '%' ) ) {
		$ideas[] = trim( (string) preg_split( '/(?<=[.!؟?])\s/u', $summary )[0], " .\u{200C}" );
	}

	$current = hodima_seo_discover_data( $id, $context )['title'];
	$seen    = [ hodima_seo_discover_text_norm( '' !== $current ? $current : $name ) => true ];
	$out     = [];

	foreach ( (array) apply_filters( 'hodima_seo_discover_title_ideas', $ideas, $target ) as $idea ) {
		$idea = sanitize_text_field( (string) $idea );
		$key  = hodima_seo_discover_text_norm( $idea );
		$len  = mb_strlen( $idea );

		if ( '' === $key || isset( $seen[ $key ] ) || $len < HODIMA_SEO_DISCOVER_TITLE_MIN || $len > HODIMA_SEO_DISCOVER_TITLE_MAX || hodima_seo_discover_is_clickbait( $idea ) ) {
			continue;
		}

		$seen[ $key ] = true;
		$out[]        = $idea;
	}

	return array_slice( $out, 0, 4 );
}
