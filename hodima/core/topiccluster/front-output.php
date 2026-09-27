<?php
/**
 * Topic Cluster — Front-end Box
 * Path: core/topiccluster/front-output.php
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_shortcode( 'hodima_topic_cluster', 'hodima_tc_display_shortcode' );

/**
 * گره صفحه جاری: [شناسه، context] یا [0, ''] اگر قابل تشخیص نباشد.
 * مشترک بین شورت‌کد و اسکیما تا هر دو دقیقا یک گره را ببینند.
 */
function hodima_tc_current_node(): array {

	if ( is_singular( Hodima_TC_Helper::post_types() ) ) {
		return [ (int) get_queried_object_id(), Hodima_TC_Helper::KIND_POST ];
	}

	$queried = get_queried_object();

	if ( $queried instanceof WP_Term && in_array( $queried->taxonomy, Hodima_TC_Helper::taxonomies(), true ) ) {
		return [ (int) $queried->term_id, Hodima_TC_Helper::KIND_TERM ];
	}

	return [ 0, '' ];
}

function hodima_tc_display_shortcode( $atts = [] ): string {

	$atts = shortcode_atts( [ 'id' => 0, 'type' => '' ], is_array( $atts ) ? $atts : [], 'hodima_topic_cluster' );

	$object_id = absint( $atts['id'] );
	$context   = sanitize_key( (string) $atts['type'] );

	if ( ! $object_id ) {
		[ $object_id, $context ] = hodima_tc_current_node();
	}

	if ( ! in_array( $context, [ Hodima_TC_Helper::KIND_POST, Hodima_TC_Helper::KIND_TERM ], true ) ) {
		$context = Hodima_TC_Helper::KIND_POST;
	}

	if ( ! $object_id ) {
		return hodima_tc_debug( 'Object ID not detected' );
	}

	$parent_kind = Hodima_TC_Helper::parent_kind( $object_id, $context );

	$parents = [];
	foreach ( Hodima_TC_Helper::get_parents( $object_id, $context ) as $parent_id ) {
		$node = Hodima_TC_Helper::resolve_node( $parent_id, $parent_kind );
		if ( null !== $node ) {
			$parents[] = $node;
		}
	}

	$children = Hodima_TC_Helper::is_pillar( $object_id, $context )
		? Hodima_TC_Helper::get_children( $object_id, $context )
		: [];

	if ( empty( $parents ) && empty( $children ) ) {
		return hodima_tc_debug( 'No parents and no children' );
	}

	wp_enqueue_style( 'hodima-tc-front' );

	/*
	 * شناسه HTML «topic-cluster-section» فقط یک بار در صفحه چاپ می‌شود.
	 * اگر شورت‌کد دو بار استفاده شود، شناسه تکراری HTML نامعتبر می‌سازد و
	 * لینک‌های لنگر والدها به جای نامشخصی پرش می‌کنند.
	 */
	static $anchor_printed = false;

	ob_start();
	?>
	<nav class="hodima-tc-unified-box" aria-label="خوشه محتوایی">

		<?php if ( ! empty( $parents ) ) : ?>
			<div class="hodima-tc-section hodima-tc-parents-section">
				<div class="hodima-tc-header">دسته‌بندی‌های مرجع:</div>
				<div class="hodima-tc-inline-list">
					<?php foreach ( $parents as $parent ) : ?>
						<a href="<?php echo esc_url( $parent['url'] . '#topic-cluster-section' ); ?>" class="hodima-tc-parent-btn hodima-tc-parent"><?php echo esc_html( $parent['title'] ); ?></a>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $children ) ) : ?>
			<div<?php echo $anchor_printed ? '' : ' id="topic-cluster-section"'; ?> class="hodima-tc-section hodima-tc-children-section">
				<div class="hodima-tc-header">زیرمجموعه‌ها:</div>
				<div class="hodima-tc-grid-list">
					<?php foreach ( $children as $child ) : ?>
						<a href="<?php echo esc_url( $child['url'] ); ?>" class="hodima-tc-child-link hodima-tc-child"><?php echo esc_html( $child['title'] ); ?></a>
					<?php endforeach; ?>
				</div>
			</div>
			<?php $anchor_printed = true; ?>
		<?php endif; ?>

	</nav>
	<?php
	return (string) ob_get_clean();
}

/**
 * کامنت اشکال‌زدایی فقط در حالت دیباگ.
 * نسخه قبلی این کامنت‌ها را روی سایت زنده برای همه بازدیدکنندگان
 * و ربات‌ها چاپ می‌کرد.
 */
function hodima_tc_debug( string $message ): string {
	return ( defined( 'WP_DEBUG' ) && WP_DEBUG )
		? '<!-- Hodima TC: ' . esc_html( $message ) . " -->\n"
		: '';
}

add_filter( 'term_description', 'do_shortcode' );
add_filter( 'woocommerce_short_description', 'do_shortcode' );
