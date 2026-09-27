<?php
/**
 * Topic Cluster — Orphan Content Report
 * Path: core/topiccluster/admin-orphan.php
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', static function (): void {
	add_menu_page( 'مدیریت خوشه‌های محتوایی', 'خوشه‌بندی', 'manage_options', 'hodima-tc-orphans', 'hodima_tc_render_orphan_page', 'dashicons-networking', 25 );
	add_submenu_page( 'hodima-tc-orphans', 'محتواهای یتیم', 'محتواهای یتیم', 'manage_options', 'hodima-tc-orphans', 'hodima_tc_render_orphan_page' );
} );

/**
 * گره‌هایی که نه پیلارند و نه والد دارند.
 *
 * باگ نسخه قبلی: شرط «meta_value != '' AND != '0'» ردیف «a:0:{}» را
 * (آرایه خالی سریالایزشده) «دارای والد» حساب می‌کرد. و چون هر ذخیره
 * نوشته همان ردیف خالی را می‌ساخت، *هر نوشته‌ای که یک بار در ویرایشگر
 * ذخیره شده بود* از گزارش یتیم‌ها ناپدید می‌شد — حتی بدون هیچ والدی.
 * گزارش عملا تعداد واقعی را خیلی کمتر نشان می‌داد.
 *
 * همچنین NOT IN با زیرکوئری به LEFT JOIN تبدیل شد که روی جدول‌های
 * بزرگ متا به‌مراتب سریع‌تر است.
 *
 * @return array<int, array{id:int, type:string}>
 */
function hodima_tc_get_all_orphans_fast(): array {

	global $wpdb;

	$post_types = array_map( 'esc_sql', Hodima_TC_Helper::post_types() );
	$taxonomies = array_map( 'esc_sql', [ 'category', 'product_cat' ] );

	$pt_in  = "'" . implode( "','", $post_types ) . "'";
	$tax_in = "'" . implode( "','", $taxonomies ) . "'";

	// «مقدار معتبر والد» یعنی نه خالی، نه صفر، نه آرایه خالی قدیمی
	$has_parent = "pm_par.meta_value NOT IN ('', '0', 'a:0:{}')";

	$post_ids = $wpdb->get_col( "
		SELECT p.ID
		FROM {$wpdb->posts} p
		LEFT JOIN {$wpdb->postmeta} pm_pil
			ON pm_pil.post_id = p.ID AND pm_pil.meta_key = '_hodima_is_pillar' AND pm_pil.meta_value = '1'
		LEFT JOIN {$wpdb->postmeta} pm_par
			ON pm_par.post_id = p.ID AND pm_par.meta_key = '_hodima_pillar_id' AND {$has_parent}
		WHERE p.post_type IN ({$pt_in})
		  AND p.post_status = 'publish'
		  AND pm_pil.meta_id IS NULL
		  AND pm_par.meta_id IS NULL
		GROUP BY p.ID
		ORDER BY p.post_modified DESC
	" );

	$has_parent_t = str_replace( 'pm_par.', 'tm_par.', $has_parent );

	$term_ids = $wpdb->get_col( "
		SELECT t.term_id
		FROM {$wpdb->terms} t
		INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
		LEFT JOIN {$wpdb->termmeta} tm_pil
			ON tm_pil.term_id = t.term_id AND tm_pil.meta_key = '_hodima_is_pillar' AND tm_pil.meta_value = '1'
		LEFT JOIN {$wpdb->termmeta} tm_par
			ON tm_par.term_id = t.term_id AND tm_par.meta_key = '_hodima_pillar_id' AND {$has_parent_t}
		WHERE tt.taxonomy IN ({$tax_in})
		  AND tm_pil.meta_id IS NULL
		  AND tm_par.meta_id IS NULL
		GROUP BY t.term_id
		ORDER BY t.name ASC
	" );

	$orphans = [];

	foreach ( (array) $post_ids as $id ) {
		$orphans[] = [ 'id' => (int) $id, 'type' => 'post' ];
	}
	foreach ( (array) $term_ids as $id ) {
		$orphans[] = [ 'id' => (int) $id, 'type' => 'term' ];
	}

	return $orphans;
}

add_action( 'wp_dashboard_setup', static function (): void {
	if ( current_user_can( 'manage_options' ) ) {
		wp_add_dashboard_widget( 'hodima_tc_dashboard_widget', 'وضعیت خوشه‌بندی محتوا', 'hodima_tc_dashboard_widget_render' );
	}
} );

function hodima_tc_dashboard_widget_render(): void {

	$count = get_transient( 'hodima_tc_orphan_count' );

	if ( false === $count ) {
		$count = count( hodima_tc_get_all_orphans_fast() );
		set_transient( 'hodima_tc_orphan_count', $count, 12 * HOUR_IN_SECONDS );
	}
	?>
	<div class="htc-widget">
		<h3 class="htc-widget-count"><?php echo esc_html( number_format_i18n( (int) $count ) ); ?></h3>
		<p class="htc-widget-text">محتوای یتیم (بدون خوشه)</p>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=hodima-tc-orphans' ) ); ?>" class="htc-widget-btn">ساماندهی محتوا</a>
	</div>
	<?php
}

function hodima_tc_render_orphan_page(): void {

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$all_orphans    = hodima_tc_get_all_orphans_fast();
	$total_items    = count( $all_orphans );
	$items_per_page = 30;
	$total_pages    = (int) ceil( $total_items / $items_per_page );
	$current_page   = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
	$page_items     = array_slice( $all_orphans, ( $current_page - 1 ) * $items_per_page, $items_per_page );

	// شمارنده ویجت داشبورد با همین عدد تازه هماهنگ می‌شود
	set_transient( 'hodima_tc_orphan_count', $total_items, 12 * HOUR_IN_SECONDS );

	// یک کوئری به جای یک کوئری به ازای هر ردیف
	$post_ids = array_column( array_filter( $page_items, static fn( $i ) => 'post' === $i['type'] ), 'id' );
	if ( ! empty( $post_ids ) ) {
		_prime_post_caches( $post_ids, false, false );
	}

	$post_labels = [ 'post' => 'نوشته وبلاگ', 'page' => 'برگه', 'product' => 'محصول' ];
	$term_labels = [ 'category' => 'دسته‌بندی مقالات', 'product_cat' => 'دسته‌بندی محصولات' ];
	?>
	<div class="wrap htc-wrap">
		<h1 class="htc-header">
			<span class="dashicons dashicons-networking"></span> محتواهای یتیم (بدون خوشه)
		</h1>
		<p class="htc-notice">
			مقالات، برگه‌ها، محصولات و <strong>دسته‌بندی‌هایی</strong> که هنوز نه پیلارند و نه والدی دارند.
			(تعداد کل: <?php echo esc_html( number_format_i18n( $total_items ) ); ?> مورد)
		</p>

		<div class="htc-table-wrap">
			<table class="wp-list-table widefat fixed striped htc-orphan-table">
				<thead>
					<tr>
						<th class="htc-col-id">ID</th>
						<th>عنوان محتوا</th>
						<th class="htc-col-type">نوع محتوا</th>
						<th class="htc-col-action">عملیات</th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $page_items ) ) : ?>
						<tr><td colspan="4" class="htc-empty-row">هیچ محتوای یتیمی یافت نشد.</td></tr>
					<?php else : ?>
						<?php foreach ( $page_items as $item ) :

							if ( 'post' === $item['type'] ) {
								$post = get_post( $item['id'] );
								if ( ! $post ) {
									continue;
								}
								$title     = get_the_title( $post );
								$edit_link = get_edit_post_link( $post->ID, 'raw' );
								$label     = $post_labels[ $post->post_type ] ?? $post->post_type;
							} else {
								// ترم ممکن است بین دو کوئری حذف شده باشد
								$term = get_term( $item['id'] );
								if ( ! ( $term instanceof WP_Term ) ) {
									continue;
								}
								$title     = $term->name;
								$edit_link = get_edit_term_link( $term->term_id, $term->taxonomy );
								$label     = $term_labels[ $term->taxonomy ] ?? 'دسته‌بندی';
							}
							?>
							<tr>
								<td class="htc-col-id"><?php echo (int) $item['id']; ?></td>
								<td><strong><a href="<?php echo esc_url( (string) $edit_link ); ?>"><?php echo esc_html( $title ); ?></a></strong></td>
								<td><span class="htc-badge"><?php echo esc_html( $label ); ?></span></td>
								<td class="htc-col-action"><a href="<?php echo esc_url( (string) $edit_link ); ?>" class="button htc-btn-edit" target="_blank" rel="noopener">ویرایش</a></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<?php
		if ( $total_pages > 1 ) {
			$page_links = paginate_links( [
				'base'      => add_query_arg( 'paged', '%#%' ),
				'format'    => '',
				'prev_text' => '&laquo; قبلی',
				'next_text' => 'بعدی &raquo;',
				'total'     => $total_pages,
				'current'   => $current_page,
				'type'      => 'array',
			] );

			if ( is_array( $page_links ) ) {
				echo '<div class="htc-pagination">' . wp_kses_post( implode( '', $page_links ) ) . '</div>';
			}
		}
		?>
	</div>
	<?php
}
