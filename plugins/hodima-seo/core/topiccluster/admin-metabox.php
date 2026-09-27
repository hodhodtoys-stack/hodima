<?php
/**
 * Topic Cluster — Post / Page / Product Meta Box
 * Path: core/topiccluster/admin-metabox.php
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'add_meta_boxes', static function (): void {
	foreach ( Hodima_TC_Helper::post_types() as $post_type ) {
		if ( post_type_exists( $post_type ) ) {
			add_meta_box( 'hodima_tc_box', 'خوشه‌بندی محتوا', 'hodima_tc_render_metabox', $post_type, 'side', 'high' );
		}
	}
}, 20 );

function hodima_tc_render_metabox( WP_Post $post ): void {

	wp_nonce_field( 'hodima_tc_save', 'hodima_tc_nonce' );

	$pillar_ids  = Hodima_TC_Helper::get_parents( $post->ID, Hodima_TC_Helper::KIND_POST );
	$is_pillar   = Hodima_TC_Helper::is_pillar( $post->ID, Hodima_TC_Helper::KIND_POST );
	$parent_kind = Hodima_TC_Helper::parent_kind( $post->ID, Hodima_TC_Helper::KIND_POST );

	/*
	 * نوع هدف جستجو از نقشه مرکزی می‌آید:
	 *   نوشته → category ، محصول → product_cat ، برگه → page
	 * نسخه قبلی فقط محصول را می‌شناخت و برای نوشته، نوشته‌های دیگر را
	 * پیشنهاد می‌داد.
	 */
	$taxonomy    = Hodima_TC_Helper::parent_taxonomy_for( $post->post_type );
	$target_type = '' !== $taxonomy ? $taxonomy : $post->post_type;

	$parent_label = '' !== $taxonomy ? 'دسته‌بندی‌های والد' : 'برگه‌های والد';
	?>
	<div class="htc-admin-box htc-admin-box--flush">

		<div class="htc-admin-section">
			<label class="htc-admin-label">
				<input type="checkbox" name="hodima_is_pillar" class="hodima-pillar-toggle" value="1" <?php checked( $is_pillar ); ?>>
				<strong>این محتوا خودش یک Pillar (هسته) است.</strong>
			</label>
		</div>

		<div class="hodima-pillar-select-container">
			<p class="description htc-admin-label"><strong><?php echo esc_html( $parent_label ); ?></strong> را جستجو و انتخاب کنید:</p>

			<select name="hodima_pillar_id[]" multiple class="hodima-tc-ajax-select"
				data-context="<?php echo esc_attr( $parent_kind ); ?>"
				data-type="<?php echo esc_attr( $target_type ); ?>"
				data-exclude="<?php echo esc_attr( (string) $post->ID ); ?>">
				<?php
				foreach ( $pillar_ids as $pillar_id ) {
					/*
					 * نسخه قبلی برای محصول get_term( $id )->name را مستقیم
					 * صدا می‌زد. اگر دسته‌بندی والد حذف شده بود، get_term()
					 * مقدار null برمی‌گرداند و صفحه ویرایش محصول با خطای
					 * کشنده باز نمی‌شد.
					 */
					$node = Hodima_TC_Helper::resolve_node( $pillar_id, $parent_kind );
					if ( null === $node ) {
						continue;
					}
					printf(
						'<option value="%d" selected="selected">%s</option>',
						(int) $pillar_id,
						esc_html( $node['title'] )
					);
				}
				?>
			</select>
		</div>

		<div class="htc-code-box">
			<p>برای نمایش این خوشه در وسط متن، این کد را قرار دهید:</p>
			<code>[hodima_topic_cluster]</code>
		</div>

		<div class="htc-seo-guide">
			<strong>راهنمای بررسی در سورس صفحه:</strong>
			<ul>
				<li>والد: <code>hodima-tc-parent</code></li>
				<li>فرزند: <code>hodima-tc-child</code></li>
			</ul>
		</div>

	</div>
	<?php
}

add_action( 'save_post', static function ( int $post_id, WP_Post $post ): void {

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	// بازنگری‌ها شناسه مستقل دارند و save_post برایشان هم اجرا می‌شود
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}

	if ( ! in_array( $post->post_type, Hodima_TC_Helper::post_types(), true ) ) {
		return;
	}

	$nonce = isset( $_POST['hodima_tc_nonce'] )
		? sanitize_text_field( wp_unslash( $_POST['hodima_tc_nonce'] ) )
		: '';

	if ( ! wp_verify_nonce( $nonce, 'hodima_tc_save' ) ) {
		return;
	}

	// نسخه قبلی هیچ بررسی دسترسی نداشت
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$parent_ids = isset( $_POST['hodima_pillar_id'] )
		? array_map( 'absint', (array) wp_unslash( $_POST['hodima_pillar_id'] ) )
		: [];

	Hodima_TC_Helper::save(
		$post_id,
		Hodima_TC_Helper::KIND_POST,
		isset( $_POST['hodima_is_pillar'] ),
		$parent_ids
	);
}, 10, 2 );
