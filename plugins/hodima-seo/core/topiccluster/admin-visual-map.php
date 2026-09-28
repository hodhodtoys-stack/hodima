<?php
/**
 * Topic Cluster — Visual Map
 * Path: core/topiccluster/admin-visual-map.php
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ثبت منوی این صفحه در admin-orphan.php است (هر دو صفحه زیر «خوشه‌بندی»)

/**
 * نقشه همه پیلارها و فرزندانشان.
 *
 * نسخه قبلی فقط پیلارهای نوع نوشته و برگه را نشان می‌داد. برای یک
 * فروشگاه که پیلارهایش معمولا دسته‌بندی محصول‌اند، نقشه عملا خالی بود.
 * همچنین با posts_per_page = -1 شیء *کامل* همه نوشته‌های دارای والد را
 * در حافظه بارگذاری می‌کرد.
 *
 * حالا هر دو نوع پیلار نمایش داده می‌شود و فرزندان از همان منبع کش‌شده‌ای
 * خوانده می‌شوند که صفحات سایت استفاده می‌کنند — پس نقشه دقیقا همان
 * چیزی را نشان می‌دهد که بازدیدکننده می‌بیند.
 */
function hodima_tc_render_visual_map(): void {

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$post_pillars = get_posts( [
		'post_type'              => Hodima_TC_Helper::post_types(),
		'post_status'            => 'publish',
		'posts_per_page'         => 200,
		'fields'                 => 'ids',
		'no_found_rows'          => true,
		'update_post_term_cache' => false,
		'meta_query'             => [ [ 'key' => Hodima_TC_Helper::META_PILLAR, 'value' => '1' ] ],
	] );

	$term_pillars = get_terms( [
		'taxonomy'   => Hodima_TC_Helper::taxonomies(),
		'hide_empty' => false,
		'number'     => 200,
		'fields'     => 'ids',
		'meta_query' => [ [ 'key' => Hodima_TC_Helper::META_PILLAR, 'value' => '1' ] ],
	] );

	if ( ! empty( $post_pillars ) ) {
		_prime_post_caches( array_map( 'intval', $post_pillars ), false, true );
	}

	$pillars = [];

	foreach ( (array) $post_pillars as $id ) {
		$pillars[] = [ (int) $id, Hodima_TC_Helper::KIND_POST ];
	}
	if ( ! is_wp_error( $term_pillars ) ) {
		foreach ( (array) $term_pillars as $id ) {
			$pillars[] = [ (int) $id, Hodima_TC_Helper::KIND_TERM ];
		}
	}
	?>
	<div class="wrap hd-wrap htc-wrap">
		<?php hodima_tc_admin_header( 'hodima-tc-map', 'نقشه خوشه‌های محتوایی', 'همه صفحه‌های ستون (پیلار) و زیرمجموعه‌هایشان، همان‌طور که بازدیدکننده می‌بیند.' ); ?>

		<?php if ( empty( $pillars ) ) : ?>
			<div class="hd-card hd-empty htc-map-empty">
				<?php echo hodima_admin_icon( 'dashicons-networking' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<p>هنوز هیچ پیلاری تعریف نشده است.</p>
			</div>
		<?php else : ?>
			<div class="htc-grid">
				<?php foreach ( $pillars as [ $pillar_id, $kind ] ) :

					$pillar = Hodima_TC_Helper::resolve_node( $pillar_id, $kind );
					if ( null === $pillar ) {
						continue;
					}

					$edit_link = ( Hodima_TC_Helper::KIND_POST === $kind )
						? get_edit_post_link( $pillar_id, 'raw' )
						: get_edit_term_link( $pillar_id );

					$children = Hodima_TC_Helper::get_children( $pillar_id, $kind );
					?>
					<div class="htc-card">
						<h3 class="htc-card-title">
							<a href="<?php echo esc_url( (string) $edit_link ); ?>"><?php echo esc_html( $pillar['title'] ); ?></a>
							<span class="htc-badge"><?php echo esc_html( Hodima_TC_Helper::KIND_POST === $kind ? 'محتوا' : 'دسته‌بندی' ); ?></span>
						</h3>

						<?php if ( empty( $children ) ) : ?>
							<p class="htc-empty">زیرمجموعه‌ای ندارد.</p>
						<?php else : ?>
							<ul class="htc-list">
								<?php foreach ( $children as $child ) :
									$child_edit = ( Hodima_TC_Helper::KIND_POST === $child['kind'] )
										? get_edit_post_link( $child['id'], 'raw' )
										: get_edit_term_link( $child['id'] );
									?>
									<li>
										<a href="<?php echo esc_url( (string) $child_edit ); ?>"><?php echo esc_html( $child['title'] ); ?></a>
										<?php if ( $child['noindex'] ) : ?>
											<span class="htc-badge htc-badge--muted">noindex</span>
										<?php endif; ?>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
	<?php
}
