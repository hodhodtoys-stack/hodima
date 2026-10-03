<?php
/**
 * خوشه موضوعی — اسکیما (JSON-LD)
 * Path: core/topiccluster/includes/schema.php
 *
 * رابطه‌های خوشه به نود صفحه اصلی (#webpage، ساخته schema/homepage-schema.php)
 * از طریق فیلتر hodima_schema_webpage_node اضافه می‌شوند:
 *
 *   فرزند → isPartOf : [#website, {والد}#webpage …]
 *   پیلار → hasPart  : [{فرزند}#webpage …]
 *   پیلار → about    : موجودیت موضوع (Thing با sameAs ویکی‌پدیا/ویکی‌داده)
 *   فرزند → about    : همان موجودیت موضوعِ پیلارش (اتصال معنایی خوشه)
 *
 * تغییرات نسبت به نسخه ۳:
 *   - ارجاع به صفحه‌های دیگر حالا نود کوچک کامل است (@type، url، name)، نه
 *     فقط @id خالی که در گراف این صفحه به هیچ نودی نمی‌رسید.
 *   - نود جداگانه ItemList (#cluster-list) حذف شد: به هیچ نودی وصل نبود
 *     (توضیح قبلی که «از hasPart وصل است» درست نبود — hasPart به صفحه‌های
 *     فرزند اشاره می‌کرد) و در صفحه دسته محصول کنار OfferCatalog یک فهرست
 *     دوم می‌ساخت. همان اطلاعات در hasPart هست.
 *   - والد/فرزند noindex حذف می‌شود (در ایندکس گوگل نیست).
 */

declare(strict_types=1);

namespace Hodima\TopicCluster;

defined( 'ABSPATH' ) || exit;

final class Schema {

	public static function init(): void {
		add_filter( 'hodima_schema_webpage_node', [ self::class, 'enrich' ], 20, 2 );
		add_action( 'wp_head', [ self::class, 'fallback' ], 30 );
	}

	/**
	 * رابطه‌های صفحه جاری — یک بار محاسبه، دو بار مصرف.
	 *
	 * @return array{parents: list<array<string, mixed>>, children: list<array<string, mixed>>, topic: array<string, mixed>|null}|null
	 */
	public static function relations(): ?array {

		static $memo = false;

		if ( false !== $memo ) {
			return $memo;
		}

		$memo = null;

		if ( is_admin() || is_feed() || is_404() || is_search() ) {
			return $memo;
		}

		$ref = Render::current();
		if ( null === $ref || ! Graph::is_member( $ref ) ) {
			return $memo;
		}

		$parents = [];
		foreach ( Graph::parents( $ref ) as $parent ) {
			$node = Graph::node( $parent );
			if ( null !== $node && ! $node['noindex'] ) {
				$parents[] = $node;
			}
		}

		$children = array_values( array_filter( Graph::children( $ref ), static fn( array $c ): bool => ! $c['noindex'] ) );

		// موضوع: خود پیلار، وگرنه موضوع اولین والد
		$topic = self::topic_node( $ref );
		if ( null === $topic && $parents ) {
			$topic = self::topic_node( new Ref( Kind::from( $parents[0]['kind'] ), (int) $parents[0]['id'] ) );
		}

		if ( ! $parents && ! $children && null === $topic ) {
			return $memo;
		}

		return $memo = [ 'parents' => $parents, 'children' => $children, 'topic' => $topic ];
	}

	/** @return array<string, mixed>|null */
	private static function topic_node( Ref $ref ): ?array {

		if ( ! Graph::is_pillar( $ref ) ) {
			return null;
		}

		$topic = Graph::topic( $ref );
		$node  = Graph::node( $ref );

		if ( ( '' === $topic['name'] && ! $topic['sameas'] ) || null === $node ) {
			return null;
		}

		return array_filter( [
			'@type'  => 'Thing',
			'@id'    => $node['url'] . '#topic',
			'name'   => '' !== $topic['name'] ? $topic['name'] : $node['title'],
			'sameAs' => $topic['sameas'] ? ( 1 === count( $topic['sameas'] ) ? $topic['sameas'][0] : $topic['sameas'] ) : null,
		] );
	}

	/** نود کوچک صفحه دیگر (برای isPartOf/hasPart). @param array<string, mixed> $node */
	private static function page_ref( array $node ): array {
		return [
			'@type' => 'term' === $node['kind'] ? 'CollectionPage' : 'WebPage',
			'@id'   => $node['url'] . '#webpage',
			'url'   => $node['url'],
			'name'  => $node['title'],
		];
	}

	/**
	 * @param array<string, mixed> $node
	 * @return array<string, mixed>
	 */
	public static function enrich( array $node, string $page_url = '' ): array {

		$rel = self::relations();
		if ( null === $rel ) {
			return $node;
		}

		if ( $rel['parents'] ) {
			// isPartOf موجود (معمولا #website) حفظ و والدها به آن افزوده می‌شوند
			$existing = self::as_list( $node['isPartOf'] ?? [] );
			foreach ( $rel['parents'] as $parent ) {
				$existing[] = self::page_ref( $parent );
			}
			$node['isPartOf'] = $existing;
		}

		if ( $rel['children'] ) {
			$node['hasPart'] = array_merge(
				self::as_list( $node['hasPart'] ?? [] ),
				array_map( [ self::class, 'page_ref' ], $rel['children'] )
			);
		}

		if ( null !== $rel['topic'] ) {
			$about = self::as_list( $node['about'] ?? [] );
			$ids   = array_column( $about, '@id' );
			if ( ! in_array( $rel['topic']['@id'], $ids, true ) ) {
				$about[] = $rel['topic'];
			}
			$node['about'] = 1 === count( $about ) ? $about[0] : $about;
		}

		return $node;
	}

	/** @return list<array<string, mixed>> */
	private static function as_list( mixed $value ): array {
		if ( ! is_array( $value ) || ! $value ) {
			return [];
		}
		return array_is_list( $value ) ? $value : [ $value ];
	}

	/**
	 * فالبک: گراف اصلی خاموش است → فقط رابطه‌ها را روی نود #webpage
	 * همین صفحه به گراف واحد بدهیم.
	 */
	public static function fallback(): void {

		if ( function_exists( 'hodima_schema_webpage_emitted' ) && hodima_schema_webpage_emitted() ) {
			return;
		}

		$rel = self::relations();
		$url = function_exists( 'hodima_get_canonical_url' ) ? hodima_get_canonical_url() : '';

		if ( null === $rel || '' === $url || ! function_exists( 'hodima_schema_add' ) ) {
			return;
		}

		$partial = self::enrich( [ '@id' => $url . '#webpage' ], $url );

		if ( count( $partial ) > 1 ) {
			hodima_schema_add( [ '@graph' => [ $partial ] ], 'hodima-seo: topiccluster' );
		}
	}
}
