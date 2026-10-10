<?php
/**
 * ماژول «گوگل دیسکاور» — کش نتیجه «آمادگی برای دیسکاور» هر صفحه
 * Path: core/discover/discover-cache.php
 *
 * تا SEO 2.1.1 کل گزارش (۳۰۰ نوشته + ۲۰۰ دسته، هر کدام ده‌ها کوئری) یک
 * ترنزینت بود که با هر save_post — حتی ذخیره خودکار، سفارش ووکامرس یا هر
 * نوع نوشته دیگر — پاک می‌شد؛ پس صفحه گزارش بیشتر وقت‌ها از صفر و کند ساخته
 * می‌شد. پاک شدنش هم فقط در پیشخوان ثبت بود، نه REST (ویرایشگر بلوکی).
 *
 * حالا نتیجه هر صفحه کنار خود همان صفحه در متای «_hodima_discover_row» می‌ماند
 * (با get_posts یک‌جا خوانده می‌شود) و فقط وقتی پاک می‌شود که چیزی از
 * همان صفحه عوض شود: ذخیره، متا، تصویرش، یا تصویری که با آن مشترک است.
 * تغییرهای سراسری (نمایه نویسنده، «پنهان کردن از موتورها»، لوگو، تنظیمات
 * قالب) شماره نسل را بالا می‌برند و همه ردیف‌ها کهنه می‌شوند.
 *
 * اثر صفحه‌های دیگر (تصویر یا عنوان تکراری) با «امضای تکراری» هر ردیف
 * (hodima_seo_discover_dup_signature) سنجیده می‌شود: اگر صفحه دیگری تصویر یا
 * عنوانش را عوض کند، امضا عوض و ردیف کهنه می‌شود. پس اعتبار هر ردیف از یک
 * روز (تا SEO 2.1.4؛ گزارش تقریبا هر روز از نو ساخته می‌شد) به یک هفته رسید.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** کلید متای ردیف کش (نوشته و ترم). */
const HODIMA_SEO_DISCOVER_ROW_META = '_hodima_discover_row';

/** نسخه ساختار ردیف؛ با تغییر فهرست بررسی بالا برود تا ردیف‌های قبلی دوباره ساخته شوند. */
const HODIMA_SEO_DISCOVER_ROW_VERSION = 4; // ۴: شکل و حجم تصویر، موجودی و قیمت محصول (SEO 2.1.8)

/** اعتبار هر ردیف (ثانیه)؛ فقط برای موارد وابسته به زمان مثل «تازگی». */
const HODIMA_SEO_DISCOVER_ROW_TTL = WEEK_IN_SECONDS;

/** گزینه شماره نسل (تغییر سراسری = همه ردیف‌ها کهنه). */
const HODIMA_SEO_DISCOVER_GEN_OPTION = 'hodima_discover_rows_gen';

/** شماره نسل فعلی ردیف‌ها. */
function hodima_seo_discover_rows_gen(): int {
	return (int) get_option( HODIMA_SEO_DISCOVER_GEN_OPTION, 1 );
}

/** همه ردیف‌ها کهنه شوند (بدون پاک کردن تک‌تک متاها). */
function hodima_seo_discover_rows_reset(): void {
	update_option( HODIMA_SEO_DISCOVER_GEN_OPTION, hodima_seo_discover_rows_gen() + 1, true );
}

/**
 * ساخت ردیف گزارش یک صفحه از فهرست بررسی.
 *
 * @return array{v: int, gen: int, at: int, dup: string, error: int, warn: int, ok: int, total: int, crops: string, issues: list<array{key: string, status: string, label: string, detail: string, link: string}>}
 */
function hodima_seo_discover_build_row( WP_Post|WP_Term $target ): array {

	$checks         = hodima_seo_discover_checks( $target );
	[ $ok, $total ] = hodima_seo_discover_score( $checks );
	$issues         = array_values( array_filter( $checks, static fn( array $c ): bool => 'ok' !== $c['status'] ) );
	$crops          = array_column( $checks, 'status', 'key' )['crops'] ?? '';
	$self           = ( $target instanceof WP_Term ? 'term:' . $target->term_id : 'post:' . $target->ID );

	return [
		'v'      => HODIMA_SEO_DISCOVER_ROW_VERSION,
		'gen'    => hodima_seo_discover_rows_gen(),
		'at'     => time(),
		'dup'    => hodima_seo_discover_dup_signature( $self ),
		'error'  => count( array_filter( $issues, static fn( array $c ): bool => 'error' === $c['status'] ) ),
		'warn'   => count( array_filter( $issues, static fn( array $c ): bool => 'warn' === $c['status'] ) ),
		'ok'     => $ok,
		'total'  => $total,
		'crops'  => match ( $crops ) { // برای «ساخت برش برای همه»
			'ok'    => 'ready',
			'warn'  => 'missing',
			default => 'na',
		},
		'issues' => $issues,
	];
}

/**
 * ردیف کش‌شده یک صفحه؛ اگر نیست یا کهنه است و $build، ساخته و ذخیره می‌شود.
 *
 * @return array{v: int, gen: int, at: int, dup: string, error: int, warn: int, ok: int, total: int, crops: string, issues: list<array{key: string, status: string, label: string, detail: string, link: string}>}|null
 */
function hodima_seo_discover_row( WP_Post|WP_Term $target, bool $build = true ): ?array {

	$context = $target instanceof WP_Term ? 'term' : 'post';
	$id      = $target instanceof WP_Term ? (int) $target->term_id : (int) $target->ID;
	$row     = get_metadata( $context, $id, HODIMA_SEO_DISCOVER_ROW_META, true );

	if ( is_array( $row )
		&& HODIMA_SEO_DISCOVER_ROW_VERSION === (int) ( $row['v'] ?? 0 )
		&& hodima_seo_discover_rows_gen() === (int) ( $row['gen'] ?? 0 )
		&& (int) ( $row['at'] ?? 0 ) > time() - HODIMA_SEO_DISCOVER_ROW_TTL
		&& hodima_seo_discover_dup_signature( $context . ':' . $id ) === (string) ( $row['dup'] ?? '' ) ) {
		return $row;
	}

	if ( ! $build ) {
		return null;
	}

	$row = hodima_seo_discover_build_row( $target );
	update_metadata( $context, $id, HODIMA_SEO_DISCOVER_ROW_META, $row );

	return $row;
}

/** پاک کردن ردیف کش یک صفحه (اگر هست؛ بدون کوئری اضافه وقتی نیست). */
function hodima_seo_discover_forget( string $context, int $id ): void {
	if ( $id > 0 && metadata_exists( $context, $id, HODIMA_SEO_DISCOVER_ROW_META ) ) {
		delete_metadata( $context, $id, HODIMA_SEO_DISCOVER_ROW_META );
	}
}

/**
 * پاک کردن ردیف همه صفحه‌هایی که این تصویر را (شاخص، دیسکاور یا تصویر دسته)
 * دارند. مستقیم از دیتابیس، نه نمایه همین درخواست که ممکن است کهنه باشد.
 */
function hodima_seo_discover_forget_image_users( int $attachment_id ): void {

	if ( $attachment_id <= 0 ) {
		return;
	}

	global $wpdb;

	// phpcs:disable WordPress.DB.DirectDatabaseQuery -- فقط هنگام تغییر تصویر؛ شناسه‌ها برای پاک کردن کش
	$posts = $wpdb->get_col( $wpdb->prepare(
		"SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ( '_thumbnail_id', %s ) AND meta_value = %s",
		hodima_seo_discover_meta_key( 'image_id' ),
		(string) $attachment_id
	) );
	$terms = $wpdb->get_col( $wpdb->prepare(
		"SELECT DISTINCT term_id FROM {$wpdb->termmeta} WHERE meta_key IN ( 'thumbnail_id', 'category_image_id', %s ) AND meta_value = %s",
		hodima_seo_discover_meta_key( 'image_id', 'term' ),
		(string) $attachment_id
	) );
	// phpcs:enable

	foreach ( $posts as $id ) {
		hodima_seo_discover_forget( 'post', (int) $id );
	}
	foreach ( $terms as $id ) {
		hodima_seo_discover_forget( 'term', (int) $id );
	}
}

/* =====================================================================
 * پاک شدن به‌موقع
 * ===================================================================== */

// ذخیره نوشته/محصول (بعد از ذخیره کادر و ساخت برش‌ها)
add_action( 'save_post', static function ( int $post_id, WP_Post $post ): void {
	if ( ! wp_is_post_revision( $post ) && in_array( $post->post_type, hodima_seo_discover_post_types(), true ) ) {
		hodima_seo_discover_forget( 'post', $post_id );
		hodima_seo_discover_index( true ); // نام یا وضعیت انتشار شاید عوض شد
	}
}, 99, 2 );

// ذخیره دسته (بعد از edited_{taxonomy} که کادر دیسکاور در آن ذخیره می‌شود)
add_action( 'saved_term', static function ( int $term_id, int $tt_id, string $taxonomy ): void {
	if ( in_array( $taxonomy, hodima_seo_discover_taxonomies(), true ) ) {
		hodima_seo_discover_forget( 'term', $term_id );
		hodima_seo_discover_index( true ); // نام دسته شاید عوض شد
	}
}, 99, 3 );

// حذف یا انتقال به زباله‌دان نوشته/دسته: نمایه «تکراری» بقیه صفحه‌ها کهنه شد
foreach ( [ 'delete_post', 'trashed_post', 'untrashed_post' ] as $hodima_seo_discover_action ) {
	add_action( $hodima_seo_discover_action, static function ( int $post_id ): void {
		if ( in_array( (string) get_post_type( $post_id ), hodima_seo_discover_post_types(), true ) ) {
			hodima_seo_discover_index( true );
		}
	} );
}
unset( $hodima_seo_discover_action );
add_action( 'delete_term', static function ( int $term_id, int $tt_id, string $taxonomy ): void {
	if ( in_array( $taxonomy, hodima_seo_discover_taxonomies(), true ) ) {
		hodima_seo_discover_index( true );
	}
}, 10, 3 );

/**
 * تغییر هر متا (از جمله REST ویرایشگر بلوکی و ذخیره تصویر شاخص جدا):
 *   - پیوست: اطلاعات فایل (برش تازه) یا متن جایگزین ← صفحه‌های همان تصویر؛
 *   - نوشته/محصول/دسته: همان صفحه، و اگر تصویرش عوض شد، صفحه‌هایی که
 *     تصویر تازه را دارند (تصویر تکراری).
 */
$hodima_seo_discover_meta_listener = static function ( string $context ): Closure {
	return static function ( mixed $meta_id, int $object_id, string $meta_key, mixed $value ) use ( $context ): void {

		if ( HODIMA_SEO_DISCOVER_ROW_META === $meta_key || in_array( $meta_key, [ '_edit_lock', '_edit_last' ], true ) ) {
			return;
		}

		$image_keys = [ '_thumbnail_id', 'thumbnail_id', 'category_image_id', hodima_seo_discover_meta_key( 'image_id', $context ) ];

		if ( 'post' === $context && 'attachment' === get_post_type( $object_id ) ) {
			if ( in_array( $meta_key, [ '_wp_attachment_metadata', '_wp_attachment_image_alt' ], true ) ) {
				hodima_seo_discover_forget_image_users( $object_id );
			}
			return;
		}

		$enabled = 'term' === $context ? hodima_seo_discover_for_term( $object_id ) : hodima_seo_discover_for_post( $object_id );

		if ( ! $enabled ) {
			return;
		}

		hodima_seo_discover_forget( $context, $object_id );

		if ( in_array( $meta_key, $image_keys, true ) || hodima_seo_discover_meta_key( 'title', $context ) === $meta_key ) {
			hodima_seo_discover_index( true ); // نمایه «تکراری» این درخواست کهنه شد
		}

		if ( in_array( $meta_key, $image_keys, true ) && is_numeric( $value ) ) {
			hodima_seo_discover_forget_image_users( (int) $value );
		}
	};
};

foreach ( [ 'added', 'updated', 'deleted' ] as $hodima_seo_discover_action ) {
	add_action( "{$hodima_seo_discover_action}_post_meta", $hodima_seo_discover_meta_listener( 'post' ), 10, 4 );
	add_action( "{$hodima_seo_discover_action}_term_meta", $hodima_seo_discover_meta_listener( 'term' ), 10, 4 );
}
unset( $hodima_seo_discover_action, $hodima_seo_discover_meta_listener );

// ویرایش پیوست (عنوان، متن جایگزین از صفحه ویرایش رسانه)
add_action( 'edit_attachment', static function ( int $attachment_id ): void {
	hodima_seo_discover_forget_image_users( $attachment_id );
} );

// تغییرهای سراسری: نمایه نویسنده، قالب، لوگو، «پنهان کردن از موتورهای جستجو»، تنظیمات قالب هدیما
add_action( 'profile_update', static function ( int $user_id ): void {
	// فقط نویسنده‌ها؛ نمایه مشتری ووکامرس با هر سفارش به‌روز می‌شود و نباید همه کش را پاک کند
	if ( count_user_posts( $user_id, [ 'post', 'page' ], true ) > 0 ) {
		hodima_seo_discover_rows_reset();
	}
} );
add_action( 'switch_theme', 'hodima_seo_discover_rows_reset' );
add_action( 'updated_option', static function ( string $option ): void {
	if ( in_array( $option, [ 'blog_public', 'site_icon', 'hodima_theme_settings' ], true ) || str_starts_with( $option, 'theme_mods_' ) ) {
		hodima_seo_discover_rows_reset();
	}
} );
