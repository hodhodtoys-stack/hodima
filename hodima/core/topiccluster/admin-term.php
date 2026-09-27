<?php
/**
 * Topic Cluster — Term Fields
 * Path: core/topiccluster/admin-term.php
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', static function (): void {
	foreach ( Hodima_TC_Helper::taxonomies() as $taxonomy ) {
		add_action( "{$taxonomy}_edit_form_fields", 'hodima_tc_term_edit', 10, 2 );
		add_action( "edited_{$taxonomy}", 'hodima_tc_term_save', 10, 1 );
		add_action( "{$taxonomy}_add_form_fields", 'hodima_tc_term_add', 10, 1 );
		add_action( "created_{$taxonomy}", 'hodima_tc_term_save', 10, 1 );
	}
} );

/** بخش مشترک فرم ویرایش و افزودن. */
function hodima_tc_term_fields( string $taxonomy, int $term_id = 0 ): void {

	wp_nonce_field( 'hodima_tc_save', 'hodima_tc_nonce' );

	$pillar_ids = $term_id ? Hodima_TC_Helper::get_parents( $term_id, Hodima_TC_Helper::KIND_TERM ) : [];
	$is_pillar  = $term_id ? Hodima_TC_Helper::is_pillar( $term_id, Hodima_TC_Helper::KIND_TERM ) : false;
	?>
	<div class="htc-admin-section">
		<label class="htc-admin-label">
			<input type="checkbox" name="hodima_is_pillar" class="hodima-pillar-toggle" value="1" <?php checked( $is_pillar ); ?>>
			<strong>این دسته‌بندی خودش یک Pillar است.</strong>
		</label>
	</div>

	<div class="hodima-pillar-select-container">
		<p class="description htc-admin-label"><strong>دسته‌بندی‌های والد</strong> را جستجو و انتخاب کنید:</p>

		<select name="hodima_pillar_id[]" multiple class="hodima-tc-ajax-select"
			data-context="term"
			data-type="<?php echo esc_attr( $taxonomy ); ?>"
			data-exclude="<?php echo esc_attr( (string) $term_id ); ?>">
			<?php
			foreach ( $pillar_ids as $pillar_id ) {
				$node = Hodima_TC_Helper::resolve_node( $pillar_id, Hodima_TC_Helper::KIND_TERM );
				if ( null !== $node ) {
					printf( '<option value="%d" selected="selected">%s</option>', (int) $pillar_id, esc_html( $node['title'] ) );
				}
			}
			?>
		</select>
	</div>

	<div class="htc-code-box">
		<p>برای نمایش این خوشه در توضیحات دسته‌بندی، این کد را قرار دهید:</p>
		<code>[hodima_topic_cluster]</code>
	</div>

	<div class="htc-seo-guide">
		<strong>راهنمای بررسی در سورس صفحه:</strong>
		<ul>
			<li>والد: <code>hodima-tc-parent</code></li>
			<li>فرزند: <code>hodima-tc-child</code></li>
		</ul>
	</div>
	<?php
}

function hodima_tc_term_edit( WP_Term $term, string $taxonomy ): void {
	?>
	<tr class="form-field">
		<td colspan="2" class="htc-td-reset">
			<div class="htc-admin-box">
				<span class="htc-admin-box-title">خوشه‌بندی محتوا (Pillar)</span>
				<?php hodima_tc_term_fields( $taxonomy, (int) $term->term_id ); ?>
			</div>
		</td>
	</tr>
	<?php
}

function hodima_tc_term_add( string $taxonomy ): void {
	?>
	<div class="form-field htc-admin-box">
		<span class="htc-admin-box-title">خوشه‌بندی محتوا (Pillar)</span>
		<?php hodima_tc_term_fields( $taxonomy ); ?>
	</div>
	<?php
}

function hodima_tc_term_save( int $term_id ): void {

	$nonce = isset( $_POST['hodima_tc_nonce'] )
		? sanitize_text_field( wp_unslash( $_POST['hodima_tc_nonce'] ) )
		: '';

	if ( ! wp_verify_nonce( $nonce, 'hodima_tc_save' ) ) {
		return;
	}

	// نسخه قبلی هیچ بررسی دسترسی نداشت. قابلیت از خود تکسونومی خوانده می‌شود.
	$term = get_term( $term_id );
	if ( ! ( $term instanceof WP_Term ) ) {
		return;
	}

	$taxonomy = get_taxonomy( $term->taxonomy );
	if ( ! $taxonomy || ! current_user_can( $taxonomy->cap->edit_terms ) ) {
		return;
	}

	$parent_ids = isset( $_POST['hodima_pillar_id'] )
		? array_map( 'absint', (array) wp_unslash( $_POST['hodima_pillar_id'] ) )
		: [];

	Hodima_TC_Helper::save(
		$term_id,
		Hodima_TC_Helper::KIND_TERM,
		isset( $_POST['hodima_is_pillar'] ),
		$parent_ids
	);
}
