<?php
/**
 * Topic Cluster — رابط سازگاری (Hodima_TC_Helper)
 * Path: core/topiccluster/helper.php
 *
 * از نسخه ۴ منطق اصلی در includes/graph.php (فضای نام Hodima\TopicCluster)
 * است. این کلاس همان امضاهای نسخه ۳ را نگه می‌دارد تا کد بیرونی (سایت‌مپ
 * خوشه‌ها، AEO، افزونه‌های دیگر) نشکند؛ همه چیز به Graph سپرده می‌شود.
 * کد جدید مستقیم از Graph و Ref استفاده کند.
 */

declare(strict_types=1);

use Hodima\TopicCluster\Graph;
use Hodima\TopicCluster\Kind;
use Hodima\TopicCluster\Ref;
use Hodima\TopicCluster\Render;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Hodima_TC_Helper {

	public const META_PILLAR = Graph::META_PILLAR;
	public const META_PARENT = Graph::META_PARENT;

	public const KIND_POST = 'post';
	public const KIND_TERM = 'term';

	private static function ref( int $id, string $context ): Ref {
		return new Ref( Kind::from_context( $context ), $id );
	}

	/** @return list<string> */
	public static function post_types(): array {
		return Graph::post_types();
	}

	/** @return list<string> */
	public static function taxonomies(): array {
		return Graph::taxonomies();
	}

	/** @return array<string, string> */
	public static function parent_taxonomy_map(): array {
		return Graph::parent_taxonomy_map();
	}

	public static function parent_taxonomy_for( string $post_type ): string {
		return Graph::parent_taxonomy_for( $post_type );
	}

	/** نوع ردیف‌های _hodima_pillar_id این گره: 'post' یا 'term'. */
	public static function parent_kind( int $id, string $context ): string {
		return Graph::parent_kind( self::ref( $id, $context ) )->value;
	}

	/**
	 * شناسه‌های والد *دستی* ردیف _hodima_pillar_id (رفتار نسخه ۳).
	 * برای والد مؤثر (با والد خودکار و «محتوای ستون») parent_nodes().
	 *
	 * @return int[]
	 */
	public static function get_parents( int $id, string $context ): array {

		if ( $id <= 0 ) {
			return [];
		}

		$ref  = self::ref( $id, $context );
		$kind = Graph::parent_kind( $ref );

		return array_values( array_map(
			static fn( Ref $p ): int => $p->id,
			array_filter( Graph::explicit_parents( $ref ), static fn( Ref $p ): bool => $p->kind === $kind )
		) );
	}

	/**
	 * والدهای مؤثر، حل‌شده.
	 *
	 * @return list<array<string, mixed>>
	 */
	public static function parent_nodes( int $id, string $context ): array {
		return array_values( array_filter( array_map( [ Graph::class, 'node' ], Graph::parents( self::ref( $id, $context ) ) ) ) );
	}

	/** @return list<array<string, mixed>> */
	public static function sibling_nodes( int $id, string $context, int $limit = 6 ): array {
		return Graph::siblings( self::ref( $id, $context ), $limit );
	}

	public static function is_pillar( int $id, string $context ): bool {
		return $id > 0 && Graph::is_pillar( self::ref( $id, $context ) );
	}

	/**
	 * ذخیره به سبک نسخه ۳ (فقط ردیف _hodima_pillar_id). «محتوای ستون»،
	 * «خارج از خوشه» و ترتیب دست نمی‌خورند.
	 *
	 * @param int[] $parent_ids
	 */
	public static function save( int $id, string $context, bool $is_pillar, array $parent_ids ): void {

		$ref     = self::ref( $id, $context );
		$kind    = Graph::parent_kind( $ref );
		$parents = array_map( static fn( $pid ): Ref => new Ref( $kind, absint( $pid ) ), array_filter( array_map( 'absint', $parent_ids ) ) );

		foreach ( Graph::explicit_parents( $ref ) as $existing ) {
			if ( $existing->kind !== $kind ) {
				$parents[] = $existing;
			}
		}

		Graph::save( $ref, $is_pillar, array_values( $parents ), Graph::is_excluded( $ref ) );
	}

	/**
	 * فرزندان یک پیلار.
	 *
	 * @return list<array<string, mixed>>
	 */
	public static function get_children( int $pillar_id, string $pillar_kind = self::KIND_POST ): array {
		return $pillar_id > 0 ? Graph::children( self::ref( $pillar_id, $pillar_kind ) ) : [];
	}

	/** @return array<string, mixed>|null */
	public static function resolve_node( int $id, string $kind ): ?array {
		return $id > 0 ? Graph::node( self::ref( $id, $kind ) ) : null;
	}

	public static function node_is_noindex( int $id, string $kind ): bool {
		return Graph::is_noindex( self::ref( $id, $kind ) );
	}

	/**
	 * همه پیلارها: [شناسه، 'post'|'term'].
	 *
	 * @return list<array{0:int, 1:string}>
	 */
	public static function pillars(): array {
		return array_map( static fn( Ref $r ): array => [ $r->id, $r->kind->value ], Graph::pillars() );
	}

	/** آیا صفحه این گره کادر خوشه را نشان می‌دهد؟ */
	public static function has_box( int $id, string $context ): bool {
		return Render::has_box( self::ref( $id, $context ) );
	}

	public static function cache_gen(): int {
		return Graph::gen();
	}

	public static function bump_cache(): void {
		Graph::touch();
	}

	public static function in_graph( int $id, string $context ): bool {
		$ref = self::ref( $id, $context );
		return Graph::is_pillar( $ref ) || [] !== Graph::parents( $ref );
	}

	/** @deprecated 3.0.0 از bump_cache() استفاده کنید. */
	public static function clear_schema_cache( int $object_id, string $context ): void {
		Graph::touch();
	}
}
