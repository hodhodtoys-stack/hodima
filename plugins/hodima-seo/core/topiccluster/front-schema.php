<?php
/**
 * Topic Cluster — JSON-LD
 * Path: core/topiccluster/front-schema.php
 * Version: 3.2.0
 *
 * ─────────────────────────────────────────────────────────────────────
 * اتصال به گراف اصلی
 * ─────────────────────────────────────────────────────────────────────
 * schema/homepage-schema.php تنها سازنده نود «#webpage» است و فیلتر
 * hodima_schema_webpage_node را برای غنی‌سازی آن فراهم می‌کند. این فایل
 * رابطه‌های خوشه را مستقیم به همان نود اضافه می‌کند:
 *
 *   فرزند → isPartOf  : [#website, <والد>#webpage, …]
 *   پیلار → hasPart   : [<فرزند>#webpage, …]
 *
 * نسخه قبلی یک نود جزئی جداگانه با همان @id چاپ می‌کرد و برای اینکه
 * گارد حذف تکراری schema-cleaner.php آن را حذف نکند، عمدا @type
 * نمی‌گذاشت. آن ترفند شکننده دیگر لازم نیست؛ خروجی این فایل هم مثل
 * بقیه به گراف واحد hodima-core (hodima_schema_add) می‌رود.
 *
 * ─────────────────────────────────────────────────────────────────────
 * باگ رفع‌شده: isPartOf روی ItemList
 * ─────────────────────────────────────────────────────────────────────
 * isPartOf ویژگی CreativeWork است. ItemList زیرمجموعه Intangible است و
 * این ویژگی را نمی‌پذیرد (خطای Rich Results Test: «The property isPartOf
 * is not recognized … for an object of type ItemList»). حالا ItemList
 * بدون آن چاپ می‌شود؛ اتصالش به صفحه از طریق hasPart نود صفحه برقرار است.
 * ─────────────────────────────────────────────────────────────────────
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'hodima_schema_webpage_node', 'hodima_tc_enrich_webpage_node', 20, 2 );
add_action( 'wp_head', 'hodima_tc_print_schema', 30 );

/**
 * رابطه‌های خوشه صفحه جاری — یک بار محاسبه، دو بار مصرف (فیلتر و چاپ).
 *
 * @return array{parents: array, children: array, title: string}|null
 */
function hodima_tc_schema_relations(): ?array {

	static $memo = false;

	if ( false !== $memo ) {
		return $memo;
	}

	$memo = null;

	if ( is_admin() || is_feed() || is_404() || is_search() ) {
		return $memo;
	}

	[ $object_id, $context ] = hodima_tc_current_node();

	if ( ! $object_id ) {
		return $memo;
	}

	$parent_kind = Hodima_TC_Helper::parent_kind( $object_id, $context );
	$parents     = [];

	foreach ( Hodima_TC_Helper::get_parents( $object_id, $context ) as $parent_id ) {
		$parent = Hodima_TC_Helper::resolve_node( $parent_id, $parent_kind );
		// والد noindex در ایندکس گوگل نیست؛ ارجاع به آن بی‌معنی است
		if ( null !== $parent && ! $parent['noindex'] ) {
			$parents[] = $parent;
		}
	}

	$children = [];

	if ( Hodima_TC_Helper::is_pillar( $object_id, $context ) ) {
		$children = array_values( array_filter(
			Hodima_TC_Helper::get_children( $object_id, $context ),
			static fn( array $child ): bool => ! $child['noindex']
		) );
	}

	if ( empty( $parents ) && empty( $children ) ) {
		return $memo;
	}

	$self = Hodima_TC_Helper::resolve_node( $object_id, $context );

	return $memo = [
		'parents'  => $parents,
		'children' => $children,
		'title'    => $self ? $self['title'] : '',
	];
}

/** افزودن isPartOf و hasPart به نود صفحه اصلی. */
function hodima_tc_enrich_webpage_node( array $node, string $page_url ): array {

	$rel = hodima_tc_schema_relations();

	if ( null === $rel ) {
		return $node;
	}

	if ( ! empty( $rel['parents'] ) ) {

		// isPartOf موجود (معمولا #website) حفظ و والدها به آن افزوده می‌شوند
		$existing = $node['isPartOf'] ?? [];
		$existing = isset( $existing['@id'] ) ? [ $existing ] : (array) $existing;

		foreach ( $rel['parents'] as $parent ) {
			$existing[] = [ '@id' => $parent['url'] . '#webpage' ];
		}

		$node['isPartOf'] = $existing;
	}

	if ( ! empty( $rel['children'] ) ) {
		$node['hasPart'] = array_map(
			static fn( array $child ): array => [ '@id' => $child['url'] . '#webpage' ],
			$rel['children']
		);
	}

	return $node;
}

/**
 * چاپ ItemList خوشه (و در صورت خاموش بودن گراف اصلی، رابطه‌ها هم).
 */
function hodima_tc_print_schema(): void {

	$rel = hodima_tc_schema_relations();

	if ( null === $rel ) {
		return;
	}

	$page_url = function_exists( 'hodima_get_canonical_url' ) ? hodima_get_canonical_url() : '';

	if ( '' === $page_url ) {
		return;
	}

	$graph = [];

	// فالبک: گراف اصلی خاموش است → رابطه‌ها را خودمان چاپ کنیم
	$master_ran = function_exists( 'hodima_schema_webpage_emitted' ) && hodima_schema_webpage_emitted();

	if ( ! $master_ran ) {
		$partial = hodima_tc_enrich_webpage_node( [ '@id' => $page_url . '#webpage' ], $page_url );
		if ( count( $partial ) > 1 ) {
			$graph[] = $partial;
		}
	}

	if ( ! empty( $rel['children'] ) ) {

		$items    = [];
		$position = 1;

		foreach ( $rel['children'] as $child ) {
			$items[] = [
				'@type'    => 'ListItem',
				'position' => $position++,
				'name'     => $child['title'],
				'url'      => $child['url'],
			];
		}

		// بدون isPartOf — ItemList آن را نمی‌پذیرد (بالا توضیح داده شده)
		$graph[] = [
			'@type'           => 'ItemList',
			'@id'             => $page_url . '#cluster-list',
			'name'            => '' !== $rel['title'] ? 'محتواهای خوشه ' . $rel['title'] : 'محتواهای خوشه',
			'numberOfItems'   => count( $items ),
			'itemListElement' => $items,
		];
	}

	if ( empty( $graph ) ) {
		return;
	}

	hodima_schema_add( [ '@graph' => $graph ], 'hodima-seo: topiccluster' );
}
