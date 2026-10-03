<?php
/**
 * Hodima Router — جلوگیری از نامک تکراری بین انواع
 * Path: core/router/slug-guard.php
 *
 * بدون پایه، محصول، نوشته، برگه، دسته محصول و دسته نوشته همه در یک فضای آدرس‌اند.
 * وردپرس یکتایی نامک را فقط داخل همان نوع بررسی می‌کند؛ محصول و نوشته یا دسته
 * محصول و دسته نوشته هم‌نام می‌شدند و یکی هرگز باز نمی‌شد، ولی لینکش در سایت‌مپ،
 * canonical و اسکیما پخش می‌شد و محتوای دیگری را نشان می‌داد.
 *
 * حالا نامک *جدید* یا *تغییرکرده* که آدرسش با شیء دیگری یکی باشد، مثل خود
 * وردپرس پسوند -2، -3 … می‌گیرد. شیئی که نامکش عوض نمی‌شود دست نمی‌خورد
 * (تغییر خودکار آدرس صفحه ایندکس‌شده خطرناک است)؛ تداخل‌های قدیمی در
 * «ابزارهای هدیما ← آدرس تمیز» فهرست می‌شوند.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * آیا آدرس $path را شیء دیگری (غیر از $self_kind:$self_id) دارد؟
 * همه ردیف‌ها دیده می‌شوند (خصوصی هم)، مستقل از کاربر فعلی.
 */
function hodima_router_path_taken( string $slug, string $path, string $self_kind, int $self_id ): bool {

	$path = hodima_router_compare_form( $path );

	foreach ( hodima_router_lookup( $slug ) as $row ) {

		if ( $row['kind'] === $self_kind && $row['id'] === $self_id ) {
			continue;
		}

		$object = hodima_router_row_object( $row );
		$url    = null !== $object ? hodima_router_object_url( $object ) : '';
		$other  = '' !== $url ? hodima_router_url_path( $url ) : null;

		if ( null !== $other && hodima_router_compare_form( $other ) === $path ) {
			return true;
		}
	}

	return false;
}

/**
 * اولین نامک آزاد: نامک، نامک-2، نامک-3 …
 *
 * @param callable(string):string $path_of مسیر آدرس شیء با یک نامک فرضی
 * @param string                  $base    نامک پیش از پسوند وردپرس (برای ساخت -2، -3 …)
 */
function hodima_router_free_slug( string $slug, callable $path_of, string $self_kind, int $self_id, string $base = '' ): string {

	if ( ! hodima_router_path_taken( $slug, $path_of( $slug ), $self_kind, $self_id ) ) {
		return $slug;
	}

	// پسوند روی نامک اصلی (اگر وردپرس خودش «-2» زده بود، «-2-2» نشود)
	$base = '' !== $base ? $base : $slug;

	for ( $i = 2; $i < 100; $i++ ) {
		$suffix    = '-' . $i;
		$candidate = _truncate_post_slug( $base, 200 - strlen( $suffix ) ) . $suffix;
		if ( ! hodima_router_path_taken( $candidate, $path_of( $candidate ), $self_kind, $self_id ) ) {
			return $candidate;
		}
	}

	return $slug;
}

/* =====================================================================
 * نوشته، برگه، محصول
 * ===================================================================== */

add_filter( 'wp_unique_post_slug', 'hodima_router_unique_post_slug', 20, 6 );

/**
 * @param string     $slug
 * @param int|string $post_id
 * @param string     $post_status
 * @param string     $post_type
 * @param int|string $post_parent
 */
function hodima_router_unique_post_slug( $slug, $post_id, $post_status, $post_type, $post_parent, $original_slug = '' ) {

	$slug    = (string) $slug;
	$post_id = (int) $post_id;

	if ( '' === $slug || ! hodima_router_active() || ! in_array( (string) $post_type, hodima_router_post_types(), true ) ) {
		return $slug;
	}

	$current = $post_id ? get_post( $post_id ) : null;

	// شیء منتشرشده‌ای که نامک و والدش عوض نمی‌شود: دست نزن
	if ( $current instanceof WP_Post && in_array( $current->post_status, [ 'publish', 'private' ], true )
		&& $current->post_name === $slug && (int) $current->post_parent === (int) $post_parent ) {
		return $slug;
	}

	$draft = $current instanceof WP_Post ? clone $current : new WP_Post( new stdClass() );
	// get_permalink() برای شناسه خالی (نوشته‌ای که هنوز ذخیره نشده) false می‌دهد؛
	// شناسه ساختگی فقط برای ساخت آدرس است و هیچ‌جا ذخیره نمی‌شود
	$draft->ID          = $post_id > 0 ? $post_id : PHP_INT_MAX;
	$draft->post_type   = (string) $post_type;
	$draft->post_parent = (int) $post_parent;
	$draft->post_status = 'publish';
	$draft->filter      = 'raw';

	$path_of = static function ( string $candidate ) use ( $draft ): string {
		$draft->post_name = $candidate;
		return (string) hodima_router_url_path( (string) get_permalink( $draft ) );
	};

	return hodima_router_free_slug( $slug, $path_of, 'post', $post_id, (string) $original_slug );
}

/* =====================================================================
 * دسته محصول و دسته نوشته
 * ===================================================================== */

/** مسیر فرضی ترم: (مسیر والد/)نامک، بسته به تنظیم سلسله‌مراتبی پیوند تاکسونومی. */
function hodima_router_term_path( string $slug, string $taxonomy, int $parent ): string {

	$tax          = get_taxonomy( $taxonomy );
	$hierarchical = $tax && is_array( $tax->rewrite ) && ! empty( $tax->rewrite['hierarchical'] );

	if ( $hierarchical && $parent > 0 ) {
		$link = get_term_link( $parent, $taxonomy );
		if ( ! is_wp_error( $link ) ) {
			return trim( (string) hodima_router_url_path( (string) $link ), '/' ) . '/' . $slug;
		}
	}

	return $slug;
}

add_filter( 'wp_unique_term_slug', 'hodima_router_unique_term_slug', 20, 3 );

/**
 * ترم جدید (wp_insert_term).
 *
 * @param string $slug
 * @param object $term taxonomy و parent
 */
function hodima_router_unique_term_slug( $slug, $term, $original_slug = '' ) {

	$slug     = (string) $slug;
	$taxonomy = is_object( $term ) ? (string) ( $term->taxonomy ?? '' ) : '';

	if ( '' === $slug || ! hodima_router_active() || ! in_array( $taxonomy, hodima_router_taxonomies(), true ) ) {
		return $slug;
	}

	$term_id = (int) ( $term->term_id ?? 0 );
	$parent  = (int) ( $term->parent ?? 0 );

	return hodima_router_free_slug( $slug, static fn( string $s ): string => hodima_router_term_path( $s, $taxonomy, $parent ), 'term', $term_id, (string) $original_slug );
}

add_filter( 'wp_update_term_data', 'hodima_router_update_term_data', 20, 4 );

/**
 * ویرایش ترم: wp_unique_term_slug فقط وقتی صدا زده می‌شود که نامک در همان
 * تاکسونومی تکراری باشد؛ تغییر نامک یا والد به آدرس شیء دیگر این‌جا گرفته می‌شود.
 *
 * @param array<string, mixed> $data
 */
function hodima_router_update_term_data( $data, $term_id, $taxonomy, $args = [] ) {

	$data     = (array) $data;
	$taxonomy = (string) $taxonomy;
	$slug     = (string) ( $data['slug'] ?? '' );

	if ( '' === $slug || ! hodima_router_active() || ! in_array( $taxonomy, hodima_router_taxonomies(), true ) ) {
		return $data;
	}

	$current = get_term( (int) $term_id, $taxonomy );
	$parent  = (int) ( $args['parent'] ?? ( $current instanceof WP_Term ? $current->parent : 0 ) );

	if ( $current instanceof WP_Term && $current->slug === $slug && (int) $current->parent === $parent ) {
		return $data;
	}

	$data['slug'] = hodima_router_free_slug( $slug, static fn( string $s ): string => hodima_router_term_path( $s, $taxonomy, $parent ), 'term', (int) $term_id );

	return $data;
}

/* =====================================================================
 * گزارش تداخل‌های موجود (برای صفحه پیشخوان)
 * ===================================================================== */

/**
 * آدرس‌هایی که بیش از یک شیء دارند. اولی برنده است (همانی که سایت نشان می‌دهد).
 *
 * @return list<array{path:string, items:list<array{kind:string, id:int, type:string, object:WP_Post|WP_Term, url:string}>}>
 */
function hodima_router_conflicts( int $limit = 300 ): array {

	global $wpdb;

	$post_types = hodima_router_post_types();
	$taxonomies = hodima_router_taxonomies();

	if ( ! $post_types || ! $taxonomies ) {
		return [];
	}

	$pt = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
	$tx = implode( ',', array_fill( 0, count( $taxonomies ), '%s' ) );

	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders above
	$slugs = (array) $wpdb->get_col( $wpdb->prepare(
		"SELECT slug FROM (
			SELECT slug, COUNT(*) AS total FROM (
				SELECT post_name AS slug FROM {$wpdb->posts} WHERE post_type IN ($pt) AND post_status IN ('publish','private') AND post_name <> ''
				UNION ALL
				SELECT t.slug FROM {$wpdb->terms} t INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id WHERE tt.taxonomy IN ($tx)
			) s GROUP BY slug
		) g WHERE total > 1 LIMIT %d",
		...array_merge( $post_types, $taxonomies, [ $limit ] )
	) );

	$priorities = hodima_router_priorities();
	$out        = [];

	foreach ( $slugs as $slug ) {

		$by_path = [];
		foreach ( hodima_router_lookup( (string) $slug ) as $row ) {
			$object = hodima_router_row_object( $row );
			$url    = null !== $object ? hodima_router_object_url( $object ) : '';
			$path   = '' !== $url ? hodima_router_url_path( $url ) : null;
			if ( null !== $path ) {
				$by_path[ hodima_router_compare_form( $path ) ][] = $row + [ 'object' => $object, 'url' => $url ];
			}
		}

		foreach ( $by_path as $path => $items ) {
			if ( count( $items ) > 1 ) {
				usort( $items, static fn( array $a, array $b ): int => ( $priorities[ $a['type'] ] ?? 99 ) <=> ( $priorities[ $b['type'] ] ?? 99 ) );
				$out[] = [ 'path' => (string) $path, 'items' => $items ];
			}
		}
	}

	return $out;
}
