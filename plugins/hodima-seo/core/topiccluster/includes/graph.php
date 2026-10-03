<?php
/**
 * خوشه موضوعی — مدل گراف (والد، فرزند، هم‌خوشه، کش)
 * Path: core/topiccluster/includes/graph.php
 *
 * ─────────────────────────────────────────────────────────────────────
 * مدل داده (کلیدها عوض نشده‌اند؛ فقط کلید جدید اضافه شده)
 * ─────────────────────────────────────────────────────────────────────
 *   _hodima_is_pillar        '1' اگر گره پیلار (هسته خوشه) است
 *   _hodima_pillar_id        یک ردیف به ازای هر والد دستی:
 *                              ترم → ترم همان تکسونومی
 *                              نوشته/محصول → دسته (category / product_cat)
 *                              برگه → نوشته یا برگه پیلار
 *   _hodima_pillar_post_id   (جدید) والد دستی از نوع «محتوا» برای نوشته‌ها:
 *                              مقاله یا برگه پیلار (راهنمای جامع). تا نسخه ۳
 *                              نوشته فقط می‌توانست زیر دسته باشد و یک «مقاله
 *                              ستون» عملا هیچ‌وقت فرزندی نداشت.
 *   _hodima_tc_exclude       (جدید) '1' = خارج از خوشه (والد خودکار هم ندارد)
 *   _hodima_tc_order         (جدید، روی پیلار) ترتیب دستی فرزندان: ['post:12', 'term:5']
 *   _hodima_tc_topic         (جدید، روی پیلار) موجودیت موضوع: name و sameAs
 *
 * ─────────────────────────────────────────────────────────────────────
 * والد مؤثر (همان چیزی که سایت، اسکیما، سایت‌مپ و گزارش‌ها می‌بینند)
 * ─────────────────────────────────────────────────────────────────────
 *   ۱. «خارج از خوشه» → هیچ.
 *   ۲. والدهای دستی معتبر: موجود، منتشرشده، از نوع درست و **پیلار**.
 *      نسخه قبلی هر دسته‌ای را می‌پذیرفت؛ اگر پیلار نبود، فرزند به آن لینک
 *      می‌داد ولی آن صفحه هیچ لینکی به فرزند نمی‌داد (لینک یک‌طرفه) و لنگر
 *      #topic-cluster-section به جایی نمی‌رسید.
 *   ۳. اگر والد دستی معتبر نبود و «والد خودکار» روشن است: نزدیک‌ترین پیلار
 *      در سلسله‌مراتب خود وردپرس — دسته اصلی نوشته/محصول و والدهایش، والد
 *      دسته، یا برگه والد. (قبلا هر نوشته‌ای که دسته داشت باید دوباره دستی
 *      والد می‌گرفت و بیشتر محتوا یتیم می‌ماند.)
 *   ۴. والدی که به حلقه می‌رسد (الف ← ب ← الف) کنار گذاشته می‌شود؛ ذخیره
 *      چنین والدی هم رد می‌شود. نسخه قبلی حلقه را می‌پذیرفت و اسکیما
 *      isPartOf/hasPart دوری می‌ساخت.
 *
 * فرزندان یک پیلار = گره‌هایی که آن پیلار در والدهای مؤثرشان هست؛ پس فرزند
 * و والد همیشه از یک قاعده ساخته می‌شوند و لینک دوطرفه تضمین است.
 */

declare(strict_types=1);

namespace Hodima\TopicCluster;

use WP_Post;
use WP_Term;

defined( 'ABSPATH' ) || exit;

final class Graph {

	public const META_PILLAR      = '_hodima_is_pillar';
	public const META_PARENT      = '_hodima_pillar_id';
	public const META_PARENT_POST = '_hodima_pillar_post_id';
	public const META_EXCLUDE     = '_hodima_tc_exclude';
	public const META_ORDER       = '_hodima_tc_order';
	public const META_TOPIC       = '_hodima_tc_topic';

	/** سقف فرزندان یک پیلار (نمایش، اسکیما و سایت‌مپ). */
	public const MAX_CHILDREN = 100;

	/** سقف نامزدهای والد خودکار که برای هر پیلار بررسی می‌شوند. */
	private const MAX_CANDIDATES = 400;

	/** سقف گره‌های پیموده‌شده برای یافتن حلقه. */
	private const MAX_ANCESTORS = 60;

	private const GEN_OPTION = 'hodima_tc_cache_gen';
	private const CACHE_TTL  = 12 * HOUR_IN_SECONDS;

	/** @var array<string, array<string, mixed>> کش درون درخواست */
	private static array $memo = [];

	private static bool $dirty = false;

	/* =================================================================
	 * دامنه
	 * ================================================================= */

	/** @return list<string> */
	public static function post_types(): array {
		return array_values( (array) apply_filters( 'hodima_tc_post_types', [ 'post', 'page', 'product' ] ) );
	}

	/** @return list<string> */
	public static function taxonomies(): array {
		return array_values( (array) apply_filters( 'hodima_tc_taxonomies', [ 'category', 'post_tag', 'product_cat' ] ) );
	}

	/**
	 * نوع پست → تکسونومی‌ای که والد دسته‌ای‌اش از آن انتخاب می‌شود.
	 *
	 * @return array<string, string>
	 */
	public static function parent_taxonomy_map(): array {
		return (array) apply_filters( 'hodima_tc_parent_taxonomy_map', [
			'post'    => 'category',
			'product' => 'product_cat',
		] );
	}

	public static function parent_taxonomy_for( string $post_type ): string {
		$map = self::parent_taxonomy_map();
		return isset( $map[ $post_type ] ) && taxonomy_exists( $map[ $post_type ] ) ? (string) $map[ $post_type ] : '';
	}

	/**
	 * نوع‌هایی که در فهرست فرزندان/هم‌خوشه‌ها نمایش داده نمی‌شوند.
	 * تصمیم اصلی ماژول: محصولات در کادر خوشه فهرست نمی‌شوند (دسته محصول
	 * خودش فهرست محصولات را دارد).
	 *
	 * @return list<string>
	 */
	public static function hidden_child_post_types(): array {
		return array_values( (array) apply_filters( 'hodima_tc_hidden_child_post_types', [ 'product' ] ) );
	}

	/**
	 * نوع‌هایی که می‌توانند «محتوای ستون» دیگران باشند (والد از نوع نوشته).
	 *
	 * @return list<string>
	 */
	public static function post_pillar_types(): array {
		$types = (array) apply_filters( 'hodima_tc_post_pillar_types', [ 'post', 'page' ] );
		return array_values( array_intersect( $types, self::post_types() ) );
	}

	public static function post_type_of( Ref $ref ): string {
		return $ref->is_post() ? (string) get_post_type( $ref->id ) : '';
	}

	/** آیا این گره اصلا در خوشه‌بندی شرکت می‌کند؟ */
	public static function is_member( Ref $ref ): bool {

		if ( $ref->is_post() ) {
			return in_array( self::post_type_of( $ref ), self::post_types(), true );
		}

		$term = get_term( $ref->id );
		return $term instanceof WP_Term && in_array( $term->taxonomy, self::taxonomies(), true );
	}

	/**
	 * والدهای ردیف _hodima_pillar_id این گره از چه نوعی‌اند؟
	 * ترم → ترم؛ نوشته/محصول (دارای تکسونومی والد) → ترم؛ برگه → نوشته.
	 */
	public static function parent_kind( Ref $ref ): Kind {
		if ( $ref->is_term() ) {
			return Kind::Term;
		}
		return '' !== self::parent_taxonomy_for( self::post_type_of( $ref ) ) ? Kind::Term : Kind::Post;
	}

	/** آیا این گره علاوه بر دسته، «محتوای ستون» هم به عنوان والد می‌پذیرد؟ */
	public static function accepts_post_parents( Ref $ref ): bool {
		if ( ! $ref->is_post() ) {
			return false;
		}
		$type = self::post_type_of( $ref );
		return '' !== self::parent_taxonomy_for( $type ) && ! in_array( $type, self::hidden_child_post_types(), true );
	}

	/* =================================================================
	 * پرچم‌ها
	 * ================================================================= */

	public static function is_pillar( Ref $ref ): bool {
		return '1' === (string) get_metadata( $ref->kind->meta_type(), $ref->id, self::META_PILLAR, true );
	}

	public static function is_excluded( Ref $ref ): bool {
		return '1' === (string) get_metadata( $ref->kind->meta_type(), $ref->id, self::META_EXCLUDE, true );
	}

	/** @return list<string> ترتیب دستی فرزندان (کلیدهای Ref) */
	public static function manual_order( Ref $pillar ): array {
		$order = get_metadata( $pillar->kind->meta_type(), $pillar->id, self::META_ORDER, true );
		return is_array( $order ) ? array_values( array_filter( array_map( 'strval', $order ), static fn( string $k ): bool => null !== Ref::parse( $k ) ) ) : [];
	}

	/* =================================================================
	 * والدها
	 * ================================================================= */

	/**
	 * شناسه‌های یک کلید چندردیفی؛ ردیف سریالایزشده نسخه ۱/۲ هم خوانده
	 * می‌شود (تا مهاجرت روی سایت قدیمی تمام شود).
	 *
	 * @return list<int>
	 */
	private static function ids( Ref $ref, string $key ): array {

		$ids = [];

		foreach ( (array) get_metadata( $ref->kind->meta_type(), $ref->id, $key, false ) as $row ) {
			$values = is_array( $row ) ? $row : [ $row ];
			foreach ( $values as $value ) {
				$value = (int) $value;
				if ( $value > 0 ) {
					$ids[ $value ] = $value;
				}
			}
		}

		return array_values( $ids );
	}

	/**
	 * والدهای دستی (بدون اعتبارسنجی).
	 *
	 * @return list<Ref>
	 */
	public static function explicit_parents( Ref $ref ): array {

		$kind = self::parent_kind( $ref );
		$out  = [];

		foreach ( self::ids( $ref, self::META_PARENT ) as $id ) {
			$out[] = new Ref( $kind, $id );
		}

		if ( self::accepts_post_parents( $ref ) ) {
			foreach ( self::ids( $ref, self::META_PARENT_POST ) as $id ) {
				$out[] = Ref::post( $id );
			}
		}

		return array_values( array_filter( $out, static fn( Ref $p ): bool => ! $p->is( $ref ) ) );
	}

	/**
	 * چرا این والد برای این گره پذیرفته نیست؟ رشته خالی = معتبر.
	 *
	 * کدها: self، missing، unpublished، type، not_pillar (بررسی حلقه جداست).
	 */
	public static function parent_problem( Ref $child, Ref $parent ): string {

		if ( $child->is( $parent ) ) {
			return 'self';
		}

		if ( $parent->is_term() ) {

			$term = get_term( $parent->id );
			if ( ! $term instanceof WP_Term ) {
				return 'missing';
			}

			if ( $child->is_term() ) {
				$own = get_term( $child->id );
				$tax = $own instanceof WP_Term ? $own->taxonomy : '';
			} else {
				$tax = self::parent_taxonomy_for( self::post_type_of( $child ) );
			}

			if ( '' === $tax || $term->taxonomy !== $tax ) {
				return 'type';
			}

		} else {

			$post = get_post( $parent->id );
			if ( ! $post instanceof WP_Post ) {
				return 'missing';
			}

			$accepts = $child->is_post() && ( self::accepts_post_parents( $child ) || Kind::Post === self::parent_kind( $child ) );
			if ( ! $accepts || ! in_array( $post->post_type, self::post_pillar_types(), true ) ) {
				return 'type';
			}

			if ( 'publish' !== $post->post_status ) {
				return 'unpublished';
			}
		}

		return self::is_pillar( $parent ) ? '' : 'not_pillar';
	}

	/** توضیح فارسی هر مشکل والد (پیشخوان و گزارش سلامت). */
	public static function problem_label( string $code ): string {
		return match ( $code ) {
			'self'        => 'والد خود این صفحه است',
			'missing'     => 'والد حذف شده است',
			'unpublished' => 'والد منتشر نشده است',
			'type'        => 'نوع والد برای این صفحه مجاز نیست',
			'not_pillar'  => 'والد پیلار نیست (نادیده گرفته می‌شود)',
			'cycle'       => 'حلقه: این والد خودش زیرمجموعه همین صفحه است',
			default       => $code,
		};
	}

	/**
	 * والد خودکار: نزدیک‌ترین پیلار در سلسله‌مراتب وردپرس.
	 */
	public static function auto_parent( Ref $ref ): ?Ref {

		if ( $ref->is_term() ) {

			$term = get_term( $ref->id );
			if ( ! $term instanceof WP_Term ) {
				return null;
			}

			foreach ( get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) as $ancestor ) {
				if ( self::is_pillar( Ref::term( (int) $ancestor ) ) ) {
					return Ref::term( (int) $ancestor );
				}
			}
			return null;
		}

		$type     = self::post_type_of( $ref );
		$taxonomy = self::parent_taxonomy_for( $type );

		if ( '' !== $taxonomy ) {

			$primary = self::primary_term( $ref->id, $taxonomy );
			if ( ! $primary ) {
				return null;
			}

			$chain = array_merge( [ $primary->term_id ], get_ancestors( $primary->term_id, $taxonomy, 'taxonomy' ) );
			foreach ( $chain as $term_id ) {
				if ( self::is_pillar( Ref::term( (int) $term_id ) ) ) {
					return Ref::term( (int) $term_id );
				}
			}
			return null;
		}

		foreach ( get_post_ancestors( $ref->id ) as $ancestor ) {
			$candidate = Ref::post( (int) $ancestor );
			if ( '' === self::parent_problem( $ref, $candidate ) ) {
				return $candidate;
			}
		}

		return null;
	}

	/**
	 * دسته اصلی — همان قاعده بردکرامب (schema/breadcrumb-schema.php):
	 * _hodima_primary_{taxonomy}، سپس یواست، سپس عمیق‌ترین دسته نوشته.
	 */
	public static function primary_term( int $post_id, string $taxonomy ): ?WP_Term {

		foreach ( [ "_hodima_primary_{$taxonomy}", "_yoast_wpseo_primary_{$taxonomy}" ] as $key ) {
			$term_id = (int) get_post_meta( $post_id, $key, true );
			if ( $term_id > 0 ) {
				$term = get_term( $term_id, $taxonomy );
				if ( $term instanceof WP_Term ) {
					return $term;
				}
			}
		}

		$terms = get_the_terms( $post_id, $taxonomy );
		if ( ! is_array( $terms ) || ! $terms ) {
			return null;
		}

		$main  = $terms[0];
		$depth = -1;
		foreach ( $terms as $term ) {
			$d = count( get_ancestors( $term->term_id, $taxonomy, 'taxonomy' ) );
			if ( $d > $depth ) {
				$depth = $d;
				$main  = $term;
			}
		}

		return $main;
	}

	/**
	 * والدها پیش از حذف حلقه: دستی معتبر، وگرنه خودکار.
	 *
	 * @return array{0: list<Ref>, 1: string} والدها و منبع ('explicit'|'auto'|'')
	 */
	private static function raw_parents( Ref $ref ): array {

		$key = $ref->key();
		if ( isset( self::$memo['raw'][ $key ] ) ) {
			return self::$memo['raw'][ $key ];
		}

		$result = [ [], '' ];

		if ( ! self::is_excluded( $ref ) ) {

			$valid = array_values( array_filter(
				self::explicit_parents( $ref ),
				static fn( Ref $p ): bool => '' === self::parent_problem( $ref, $p )
			) );

			if ( $valid ) {
				$result = [ $valid, 'explicit' ];
			} elseif ( Settings::get( 'auto_parent' ) ) {
				$auto = self::auto_parent( $ref );
				if ( $auto ) {
					$result = [ [ $auto ], 'auto' ];
				}
			}
		}

		return self::$memo['raw'][ $key ] = $result;
	}

	/**
	 * کلید همه گره‌هایی که از $ref به بالا می‌رسند (والد، والدِ والد، …).
	 *
	 * @return list<string>
	 */
	public static function ancestor_keys( Ref $ref ): array {

		$key = $ref->key();
		if ( isset( self::$memo['anc'][ $key ] ) ) {
			return self::$memo['anc'][ $key ];
		}

		$seen  = [];
		$queue = [ $ref ];

		while ( $queue && count( $seen ) < self::MAX_ANCESTORS ) {
			$current = array_shift( $queue );
			foreach ( self::raw_parents( $current )[0] as $parent ) {
				$k = $parent->key();
				if ( ! isset( $seen[ $k ] ) ) {
					$seen[ $k ] = true;
					$queue[]    = $parent;
				}
			}
		}

		return self::$memo['anc'][ $key ] = array_keys( $seen );
	}

	/**
	 * والدهای مؤثر.
	 *
	 * @return list<Ref>
	 */
	public static function parents( Ref $ref ): array {

		$key = $ref->key();
		if ( isset( self::$memo['parents'][ $key ] ) ) {
			return self::$memo['parents'][ $key ];
		}

		$parents = array_values( array_filter(
			self::raw_parents( $ref )[0],
			static fn( Ref $p ): bool => ! in_array( $key, self::ancestor_keys( $p ), true )
		) );

		return self::$memo['parents'][ $key ] = $parents;
	}

	/** منبع والد مؤثر: 'explicit'، 'auto' یا ''. */
	public static function parent_source( Ref $ref ): string {
		return self::parents( $ref ) ? self::raw_parents( $ref )[1] : '';
	}

	/** آیا افزودن $parent به والدهای $child حلقه می‌سازد؟ */
	public static function would_cycle( Ref $child, Ref $parent ): bool {
		return $child->is( $parent ) || in_array( $child->key(), self::ancestor_keys( $parent ), true );
	}

	/* =================================================================
	 * فرزندان و هم‌خوشه‌ها
	 * ================================================================= */

	/**
	 * فرزندان یک پیلار، حل‌شده و مرتب (کش با شماره نسل).
	 *
	 * شورت‌کد، اسکیما، سایت‌مپ خوشه‌ها، AEO، نقشه و گزارش سلامت همه از همین
	 * خروجی استفاده می‌کنند؛ پس همه دقیقا یک چیز می‌بینند.
	 *
	 * @return list<array{id:int, kind:string, key:string, type:string, title:string, url:string, noindex:bool, modified:int, source:string}>
	 */
	public static function children( Ref $pillar ): array {

		if ( ! self::is_pillar( $pillar ) ) {
			return [];
		}

		$cache_key = sprintf( 'hodima_tc_c4_%s_%d_%d', $pillar->kind->value, $pillar->id, self::gen() );
		$cached    = get_transient( $cache_key );

		if ( is_array( $cached ) ) {
			return $cached;
		}

		$children = [];

		foreach ( self::child_refs( $pillar ) as $ref ) {
			$node = self::node( $ref );
			if ( null === $node ) {
				continue;
			}
			$node['source'] = self::raw_parents( $ref )[1];
			$children[]     = $node;
			if ( count( $children ) >= self::MAX_CHILDREN ) {
				break;
			}
		}

		set_transient( $cache_key, $children, self::CACHE_TTL );

		return $children;
	}

	/**
	 * هم‌خوشه‌ها: بقیه فرزندان والد اصلی (اولین والد مؤثر).
	 *
	 * @return list<array<string, mixed>>
	 */
	public static function siblings( Ref $ref, int $limit = 6 ): array {

		$parents = self::parents( $ref );
		if ( ! $parents ) {
			return [];
		}

		$out = [];
		foreach ( self::children( $parents[0] ) as $child ) {
			if ( $child['key'] === $ref->key() ) {
				continue;
			}
			$out[] = $child;
			if ( count( $out ) >= $limit ) {
				break;
			}
		}

		return $out;
	}

	/**
	 * نامزدها → تأیید با parents() → مرتب‌سازی.
	 *
	 * @return list<Ref>
	 */
	private static function child_refs( Ref $pillar ): array {

		$candidates = $pillar->is_term()
			? self::term_pillar_candidates( $pillar )
			: self::post_pillar_candidates( $pillar );

		$hidden   = self::hidden_child_post_types();
		$verified = [];

		foreach ( $candidates as $ref ) {

			if ( $ref->is( $pillar ) || isset( $verified[ $ref->key() ] ) ) {
				continue;
			}

			if ( $ref->is_post() ) {
				$post = get_post( $ref->id );
				if ( ! $post instanceof WP_Post || 'publish' !== $post->post_status || in_array( $post->post_type, $hidden, true ) ) {
					continue;
				}
			}

			foreach ( self::parents( $ref ) as $parent ) {
				if ( $parent->is( $pillar ) ) {
					$verified[ $ref->key() ] = $ref;
					break;
				}
			}
		}

		return self::sort( $pillar, array_values( $verified ) );
	}

	/** @return list<Ref> */
	private static function term_pillar_candidates( Ref $pillar ): array {

		$term = get_term( $pillar->id );
		if ( ! $term instanceof WP_Term ) {
			return [];
		}

		$out = [];

		// زیردسته‌ها با والد دستی
		$terms = get_terms( [
			'taxonomy'   => $term->taxonomy,
			'hide_empty' => false,
			'number'     => self::MAX_CANDIDATES,
			'fields'     => 'ids',
			'meta_query' => [ [ 'key' => self::META_PARENT, 'value' => (string) $pillar->id ] ],
		] );
		foreach ( is_array( $terms ) ? $terms : [] as $id ) {
			$out[] = Ref::term( (int) $id );
		}

		// نوشته‌هایی که این دسته را والد دستی دارند
		$post_types = self::types_with_parent_taxonomy( $term->taxonomy );
		if ( $post_types ) {
			$out = array_merge( $out, self::post_query( $post_types, [ 'meta_query' => [ [ 'key' => self::META_PARENT, 'value' => (string) $pillar->id ] ] ] ) );
		}

		if ( ! Settings::get( 'auto_parent' ) ) {
			return $out;
		}

		/*
		 * والد خودکار: «منطقه» این پیلار = خودش + زیردسته‌هایی که بین آن‌ها و
		 * این پیلار، پیلار دیگری نیست. زیردسته‌های مستقیم هر عضو منطقه و
		 * نوشته‌هایی که دسته‌ای در منطقه دارند نامزدند؛ دسته اصلی‌شان در
		 * parents() بررسی می‌شود.
		 */
		$zone  = [ $term->term_id ];
		$queue = [ $term->term_id ];

		while ( $queue && count( $zone ) < self::MAX_CANDIDATES ) {
			$kids = get_terms( [
				'taxonomy'               => $term->taxonomy,
				'hide_empty'             => false,
				'parent'                 => (int) array_shift( $queue ),
				'fields'                 => 'ids',
				'update_term_meta_cache' => true,
			] );
			foreach ( is_array( $kids ) ? $kids : [] as $kid ) {
				$kid   = (int) $kid;
				$out[] = Ref::term( $kid );
				if ( ! self::is_pillar( Ref::term( $kid ) ) ) {
					$zone[]  = $kid;
					$queue[] = $kid;
				}
			}
		}

		if ( $post_types ) {
			$out = array_merge( $out, self::post_query( $post_types, [
				'tax_query' => [ [ 'taxonomy' => $term->taxonomy, 'field' => 'term_id', 'terms' => $zone, 'include_children' => false ] ],
			] ) );
		}

		return $out;
	}

	/** @return list<Ref> */
	private static function post_pillar_candidates( Ref $pillar ): array {

		$out  = [];
		$self = self::post_type_of( $pillar );

		if ( ! in_array( $self, self::post_pillar_types(), true ) ) {
			return [];
		}

		// برگه‌ها (والدشان نوشته است) با والد دستی
		$page_types = array_values( array_filter(
			self::post_types(),
			static fn( string $pt ): bool => '' === self::parent_taxonomy_for( $pt )
		) );
		if ( $page_types ) {
			$out = self::post_query( $page_types, [ 'meta_query' => [ [ 'key' => self::META_PARENT, 'value' => (string) $pillar->id ] ] ] );
		}

		// نوشته‌هایی که این محتوا را «محتوای ستون» خود کرده‌اند
		$post_types = array_values( array_filter(
			self::post_types(),
			static fn( string $pt ): bool => '' !== self::parent_taxonomy_for( $pt ) && ! in_array( $pt, self::hidden_child_post_types(), true )
		) );
		if ( $post_types ) {
			$out = array_merge( $out, self::post_query( $post_types, [ 'meta_query' => [ [ 'key' => self::META_PARENT_POST, 'value' => (string) $pillar->id ] ] ] ) );
		}

		// والد خودکار: زیربرگه‌ها (تا پیلار بعدی)
		if ( Settings::get( 'auto_parent' ) && is_post_type_hierarchical( $self ) ) {
			$zone = [ $pillar->id ];
			$seen = 0;
			while ( $zone && $seen < self::MAX_CANDIDATES ) {
				$kids = self::post_query( [ $self ], [ 'post_parent__in' => $zone ] );
				$zone = [];
				foreach ( $kids as $kid ) {
					$out[] = $kid;
					++$seen;
					if ( ! self::is_pillar( $kid ) ) {
						$zone[] = $kid->id;
					}
				}
			}
		}

		return $out;
	}

	/** @return list<string> */
	private static function types_with_parent_taxonomy( string $taxonomy ): array {
		$types = [];
		foreach ( self::post_types() as $post_type ) {
			if ( self::parent_taxonomy_for( $post_type ) === $taxonomy && ! in_array( $post_type, self::hidden_child_post_types(), true ) ) {
				$types[] = $post_type;
			}
		}
		return $types;
	}

	/**
	 * @param list<string>         $post_types
	 * @param array<string, mixed> $args
	 * @return list<Ref>
	 */
	private static function post_query( array $post_types, array $args ): array {

		$ids = get_posts( array_merge( [
			'post_type'        => $post_types,
			'post_status'      => 'publish',
			'posts_per_page'   => self::MAX_CANDIDATES,
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'orderby'          => 'date',
			'order'            => 'DESC',
			'suppress_filters' => true,
		], $args ) );

		if ( ! $ids ) {
			return [];
		}

		$ids = array_map( 'intval', $ids );
		// یک کوئری برای متا و دسته‌های همه نامزدها (بررسی دسته اصلی)
		_prime_post_caches( $ids, true, true );

		return array_map( static fn( int $id ): Ref => Ref::post( $id ), $ids );
	}

	/**
	 * ترتیب: اول ترتیب دستی پیلار، بعد دسته‌ها (الفبایی) و بعد نوشته‌ها (جدیدترین).
	 *
	 * @param list<Ref> $refs
	 * @return list<Ref>
	 */
	private static function sort( Ref $pillar, array $refs ): array {

		$manual = array_flip( self::manual_order( $pillar ) );

		$rank = static function ( Ref $ref ) use ( $manual ): array {
			if ( isset( $manual[ $ref->key() ] ) ) {
				return [ 0, $manual[ $ref->key() ], '' ];
			}
			if ( $ref->is_term() ) {
				$term = get_term( $ref->id );
				return [ 1, 0, $term instanceof WP_Term ? $term->name : '' ];
			}
			// جدیدترین اول: منفیِ زمان انتشار
			return [ 2, -1 * (int) get_post_time( 'U', true, $ref->id ), '' ];
		};

		usort( $refs, static function ( Ref $a, Ref $b ) use ( $rank ): int {
			return $rank( $a ) <=> $rank( $b );
		} );

		return $refs;
	}

	/* =================================================================
	 * حل یک گره
	 * ================================================================= */

	/**
	 * عنوان، آدرس و وضعیت یک گره؛ null اگر قابل نمایش نیست (حذف‌شده،
	 * منتشرنشده یا بدون آدرس عمومی).
	 *
	 * @return array{id:int, kind:string, key:string, type:string, title:string, url:string, noindex:bool, modified:int}|null
	 */
	public static function node( Ref $ref ): ?array {

		$key = $ref->key();
		if ( array_key_exists( $key, self::$memo['node'] ?? [] ) ) {
			return self::$memo['node'][ $key ];
		}

		$node = null;

		if ( $ref->is_post() ) {
			$post = get_post( $ref->id );
			if ( $post instanceof WP_Post && 'publish' === $post->post_status ) {
				$url   = get_permalink( $post );
				$title = get_the_title( $post );
				$type  = $post->post_type;
				$time  = (int) get_post_modified_time( 'U', true, $post );
			}
		} else {
			$term = get_term( $ref->id );
			if ( $term instanceof WP_Term ) {
				$link  = get_term_link( $term );
				$url   = is_wp_error( $link ) ? '' : $link;
				$title = $term->name;
				$type  = $term->taxonomy;
				$time  = 0;
			}
		}

		if ( isset( $url, $title ) && is_string( $url ) && '' !== $url && '' !== trim( wp_strip_all_tags( (string) $title ) ) ) {
			$node = [
				'id'       => $ref->id,
				'kind'     => $ref->kind->value,
				'key'      => $key,
				'type'     => (string) $type,
				'title'    => trim( wp_strip_all_tags( html_entity_decode( (string) $title, ENT_QUOTES, 'UTF-8' ) ) ),
				'url'      => $url,
				'noindex'  => self::is_noindex( $ref ),
				'modified' => (int) ( $time ?? 0 ),
			];
		}

		return self::$memo['node'][ $key ] = $node;
	}

	/** noindex از تشخیص واحد Core؛ بدون Core فقط سئوباکس. */
	public static function is_noindex( Ref $ref ): bool {

		if ( function_exists( 'hodima_is_noindex' ) ) {
			return hodima_is_noindex( $ref->id, $ref->kind->value );
		}

		$robots = get_metadata( $ref->kind->meta_type(), $ref->id, '_seobox_robots', true );
		if ( is_array( $robots ) ) {
			return in_array( 'noindex', $robots, true );
		}
		return is_string( $robots ) && str_contains( strtolower( $robots ), 'noindex' );
	}

	/**
	 * همه پیلارها (حداکثر ۵۰۰ از هر نوع).
	 *
	 * @return list<Ref>
	 */
	public static function pillars(): array {

		if ( isset( self::$memo['pillars'] ) ) {
			return self::$memo['pillars'];
		}

		$out = [];

		$posts = get_posts( [
			'post_type'        => self::post_types(),
			'post_status'      => 'publish',
			'posts_per_page'   => 500,
			'fields'           => 'ids',
			'no_found_rows'    => true,
			'suppress_filters' => true,
			'meta_query'       => [ [ 'key' => self::META_PILLAR, 'value' => '1' ] ],
		] );
		if ( $posts ) {
			_prime_post_caches( array_map( 'intval', $posts ), false, true );
			foreach ( $posts as $id ) {
				$out[] = Ref::post( (int) $id );
			}
		}

		$terms = get_terms( [
			'taxonomy'   => array_values( array_filter( self::taxonomies(), 'taxonomy_exists' ) ),
			'hide_empty' => false,
			'number'     => 500,
			'fields'     => 'ids',
			'meta_query' => [ [ 'key' => self::META_PILLAR, 'value' => '1' ] ],
		] );
		foreach ( is_array( $terms ) ? $terms : [] as $id ) {
			$out[] = Ref::term( (int) $id );
		}

		return self::$memo['pillars'] = $out;
	}

	/* =================================================================
	 * نوشتن
	 * ================================================================= */

	/**
	 * ذخیره تنظیمات خوشه یک گره.
	 *
	 * ردیفی بازنویسی می‌شود که واقعا عوض شده باشد (هر ذخیره بی‌تغییر
	 * کش کل گراف را پاک نمی‌کند). والد حذف‌شده، از نوع نادرست یا حلقه‌ساز
	 * رد می‌شود؛ والد پیلارنشده یا منتشرنشده نگه داشته می‌شود (داده مدیر
	 * پاک نمی‌شود) ولی تا معتبر نشود نادیده گرفته می‌شود.
	 *
	 * @param list<Ref>         $parents
	 * @param list<string>|null $order   null = دست نزن
	 * @return list<array{0: Ref, 1: string}> والدهای ردشده و دلیل
	 */
	public static function save( Ref $ref, bool $pillar, array $parents, bool $exclude = false, ?array $order = null ): array {

		$type     = $ref->kind->meta_type();
		$kind     = self::parent_kind( $ref );
		$sets     = [ self::META_PARENT => [], self::META_PARENT_POST => [] ];
		$rejected = [];

		foreach ( $parents as $parent ) {

			$problem = self::parent_problem( $ref, $parent );

			if ( in_array( $problem, [ 'self', 'missing', 'type' ], true ) ) {
				$rejected[] = [ $parent, $problem ];
				continue;
			}
			if ( self::would_cycle( $ref, $parent ) ) {
				$rejected[] = [ $parent, 'cycle' ];
				continue;
			}

			if ( $parent->kind === $kind ) {
				$sets[ self::META_PARENT ][ $parent->id ] = $parent->id;
			} elseif ( $parent->is_post() && self::accepts_post_parents( $ref ) ) {
				$sets[ self::META_PARENT_POST ][ $parent->id ] = $parent->id;
			} else {
				$rejected[] = [ $parent, 'type' ];
			}
		}

		foreach ( $sets as $key => $ids ) {

			if ( self::META_PARENT_POST === $key && ! self::accepts_post_parents( $ref ) ) {
				continue;
			}

			$ids     = array_values( $ids );
			$current = self::ids( $ref, $key );
			$raw     = (array) get_metadata( $type, $ref->id, $key, false );
			$legacy  = (bool) array_filter( $raw, 'is_array' );

			sort( $current );
			$sorted = $ids;
			sort( $sorted );

			if ( $current === $sorted && ! $legacy ) {
				continue;
			}

			delete_metadata( $type, $ref->id, $key );
			foreach ( $ids as $id ) {
				add_metadata( $type, $ref->id, $key, $id );
			}
		}

		self::set_flag( $ref, self::META_PILLAR, $pillar );
		self::set_flag( $ref, self::META_EXCLUDE, $exclude );

		if ( null !== $order ) {
			$order = array_values( array_unique( array_filter( array_map( 'strval', $order ), static fn( string $k ): bool => null !== Ref::parse( $k ) ) ) );
			if ( $pillar && $order ) {
				if ( $order !== self::manual_order( $ref ) ) {
					update_metadata( $type, $ref->id, self::META_ORDER, $order );
				}
			} elseif ( metadata_exists( $type, $ref->id, self::META_ORDER ) ) {
				delete_metadata( $type, $ref->id, self::META_ORDER );
			}
		}

		self::reset_memo();

		return $rejected;
	}

	/** پرچم «۱» یا حذف — ردیف «۰» ساخته نمی‌شود. */
	private static function set_flag( Ref $ref, string $key, bool $on ): void {

		$type    = $ref->kind->meta_type();
		$current = '1' === (string) get_metadata( $type, $ref->id, $key, true );

		if ( $on && ! $current ) {
			update_metadata( $type, $ref->id, $key, '1' );
		} elseif ( ! $on && metadata_exists( $type, $ref->id, $key ) ) {
			delete_metadata( $type, $ref->id, $key );
		}
	}

	/** موجودیت موضوع پیلار (برای about اسکیما). @return array{name:string, sameas:list<string>} */
	public static function topic( Ref $ref ): array {
		$raw = get_metadata( $ref->kind->meta_type(), $ref->id, self::META_TOPIC, true );
		$raw = is_array( $raw ) ? $raw : [];
		return [
			'name'   => sanitize_text_field( (string) ( $raw['name'] ?? '' ) ),
			'sameas' => array_values( array_filter( array_map( 'esc_url_raw', (array) ( $raw['sameas'] ?? [] ) ) ) ),
		];
	}

	/** @param array{name?:string, sameas?:list<string>|string} $topic */
	public static function save_topic( Ref $ref, array $topic ): void {

		$sameas = $topic['sameas'] ?? [];
		if ( is_string( $sameas ) ) {
			$sameas = preg_split( '/[\s,]+/', $sameas, -1, PREG_SPLIT_NO_EMPTY ) ?: [];
		}

		$clean = [
			'name'   => sanitize_text_field( (string) ( $topic['name'] ?? '' ) ),
			'sameas' => array_values( array_unique( array_filter( array_map(
				static fn( $url ): string => wp_http_validate_url( (string) $url ) ? esc_url_raw( (string) $url ) : '',
				(array) $sameas
			) ) ) ),
		];

		$type = $ref->kind->meta_type();

		if ( '' === $clean['name'] && ! $clean['sameas'] ) {
			if ( metadata_exists( $type, $ref->id, self::META_TOPIC ) ) {
				delete_metadata( $type, $ref->id, self::META_TOPIC );
			}
			return;
		}

		if ( $clean !== self::topic( $ref ) ) {
			update_metadata( $type, $ref->id, self::META_TOPIC, $clean );
		}
	}

	/* =================================================================
	 * کش
	 * ================================================================= */

	/**
	 * شماره نسل کش. هر تغییری در گراف شماره را یک واحد بالا می‌برد و همه
	 * کلیدهای قدیمی (فرزندان، یتیم‌ها، سایت‌مپ خوشه‌ها) خودبه‌خود از
	 * دسترس خارج می‌شوند.
	 */
	public static function gen(): int {
		return (int) get_option( self::GEN_OPTION, 1 );
	}

	/**
	 * علامت «گراف عوض شد». افزایش شماره در پایان درخواست و فقط یک بار
	 * انجام می‌شود؛ ذخیره یک نوشته که ده متا را عوض می‌کند ده بار گزینه را
	 * بازنویسی نمی‌کند. هر چیزی که تا آن لحظه با شماره قدیمی کش شود، بعد از
	 * افزایش خودبه‌خود کنار می‌رود.
	 */
	public static function touch(): void {

		self::reset_memo();

		if ( self::$dirty ) {
			return;
		}

		self::$dirty = true;
		add_action( 'shutdown', [ self::class, 'commit' ], 0 );
	}

	/** @internal */
	public static function commit(): void {

		if ( ! self::$dirty ) {
			return;
		}

		self::$dirty = false;
		update_option( self::GEN_OPTION, self::gen() + 1, false );
		delete_transient( 'hodima_tc_orphan_count' );

		do_action( 'hodima_tc_graph_changed' );
	}

	/** پاک کردن کش درون درخواست (بعد از هر نوشتن). */
	public static function reset_memo(): void {
		self::$memo = [];
	}

	/* =================================================================
	 * رویدادهایی که گراف را عوض می‌کنند
	 * ================================================================= */

	public static function init(): void {

		foreach ( [ 'post', 'term' ] as $type ) {
			foreach ( [ 'added', 'updated', 'deleted' ] as $op ) {
				add_action( "{$op}_{$type}_meta", [ self::class, 'on_meta' ], 10, 3 );
			}
		}

		add_action( 'transition_post_status', [ self::class, 'on_status' ], 10, 3 );
		add_action( 'post_updated', [ self::class, 'on_post_updated' ], 10, 3 );
		add_action( 'set_object_terms', [ self::class, 'on_object_terms' ], 10, 6 );
		add_action( 'delete_post', [ self::class, 'on_delete_post' ], 10, 1 );

		foreach ( [ 'created_term', 'edited_term', 'delete_term' ] as $hook ) {
			add_action( $hook, [ self::class, 'on_term' ], 10, 3 );
		}

		add_action( 'init', [ self::class, 'register_meta' ], 20 );
	}

	/** کلیدهای متایی که روی گراف یا نمایش آن اثر دارند. */
	public static function watched_meta( string $key ): bool {

		$lower = strtolower( $key );

		return in_array( $key, [
			self::META_PILLAR, self::META_PARENT, self::META_PARENT_POST, self::META_EXCLUDE, self::META_ORDER,
			'_seobox_robots', '_yoast_wpseo_meta-robots-noindex', '_aioseo_robots_noindex',
		], true )
			|| str_starts_with( $key, '_hodima_primary_' )
			|| str_starts_with( $key, '_yoast_wpseo_primary_' )
			|| 'noindex' === $lower
			|| str_ends_with( $lower, '_noindex' );
	}

	/** @param mixed $meta_ids */
	public static function on_meta( $meta_ids, $object_id, $meta_key ): void {
		if ( is_string( $meta_key ) && self::watched_meta( $meta_key ) ) {
			self::touch();
		}
	}

	/** انتشار، بازگشت به پیش‌نویس، زباله — از جمله انتشار زمان‌بندی‌شده (cron) که post_updated ندارد. */
	public static function on_status( $new, $old, $post ): void {
		if ( $new !== $old && ( 'publish' === $new || 'publish' === $old )
			&& $post instanceof WP_Post && in_array( $post->post_type, self::post_types(), true ) ) {
			self::touch();
		}
	}

	public static function on_post_updated( $post_id, $after, $before ): void {

		if ( ! $after instanceof WP_Post || ! $before instanceof WP_Post || 'publish' !== $after->post_status
			|| ! in_array( $after->post_type, self::post_types(), true ) ) {
			return;
		}

		if ( $after->post_title !== $before->post_title
			|| $after->post_name !== $before->post_name
			|| $after->post_parent !== $before->post_parent ) {
			self::touch();
		}
	}

	/** تغییر دسته یک نوشته/محصول (والد خودکار از دسته اصلی می‌آید). */
	public static function on_object_terms( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ): void {

		if ( ! in_array( $taxonomy, self::parent_taxonomy_map(), true ) ) {
			return;
		}

		$new = array_map( 'intval', (array) $tt_ids );
		$old = array_map( 'intval', (array) $old_tt_ids );
		sort( $new );
		sort( $old );

		if ( $new !== $old ) {
			self::touch();
		}
	}

	public static function on_delete_post( $post_id ): void {
		if ( in_array( get_post_type( (int) $post_id ), self::post_types(), true ) ) {
			self::touch();
		}
	}

	/** ساخت، ویرایش (نام، نامک، والد) یا حذف دسته. */
	public static function on_term( $term_id, $tt_id = 0, $taxonomy = '' ): void {
		if ( in_array( (string) $taxonomy, self::taxonomies(), true ) ) {
			self::touch();
		}
	}

	/**
	 * ثبت متاها برای REST (ویرایشگر بلوک، اپ‌ها). نسخه قبلی ثبت نمی‌کرد و
	 * این داده بیرون از متاباکس کلاسیک دیده نمی‌شد.
	 */
	public static function register_meta(): void {

		$auth = static fn( $allowed, $meta_key, $object_id ): bool => current_user_can( 'edit_post', (int) $object_id );

		foreach ( self::post_types() as $post_type ) {
			if ( ! post_type_exists( $post_type ) ) {
				continue;
			}
			$supports_rest = (bool) ( get_post_type_object( $post_type )->show_in_rest ?? false );

			foreach ( [ self::META_PILLAR => 'string', self::META_EXCLUDE => 'string' ] as $key => $schema ) {
				register_post_meta( $post_type, $key, [
					'type'          => $schema,
					'single'        => true,
					'show_in_rest'  => $supports_rest,
					'auth_callback' => $auth,
				] );
			}
			foreach ( [ self::META_PARENT, self::META_PARENT_POST ] as $key ) {
				register_post_meta( $post_type, $key, [
					'type'          => 'integer',
					'single'        => false,
					'show_in_rest'  => $supports_rest,
					'auth_callback' => $auth,
				] );
			}
		}

		$term_auth = static function ( $allowed, $meta_key, $term_id ): bool {
			$term = get_term( (int) $term_id );
			$tax  = $term instanceof WP_Term ? get_taxonomy( $term->taxonomy ) : null;
			return $tax && current_user_can( $tax->cap->edit_terms );
		};

		foreach ( self::taxonomies() as $taxonomy ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}
			$supports_rest = (bool) ( get_taxonomy( $taxonomy )->show_in_rest ?? false );

			foreach ( [ self::META_PILLAR, self::META_EXCLUDE ] as $key ) {
				register_term_meta( $taxonomy, $key, [ 'type' => 'string', 'single' => true, 'show_in_rest' => $supports_rest, 'auth_callback' => $term_auth ] );
			}
			register_term_meta( $taxonomy, self::META_PARENT, [ 'type' => 'integer', 'single' => false, 'show_in_rest' => $supports_rest, 'auth_callback' => $term_auth ] );
		}
	}
}
