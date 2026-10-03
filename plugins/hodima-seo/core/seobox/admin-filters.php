<?php
/**
 * SeoBox — list screens: Index/Noindex filter and status column
 * Path: core/seobox/admin-filters.php
 *
 * «noindex» در فهرست دقیقا همان چیزی است که روی سایت چاپ می‌شود
 * (seobox_object_robots / seobox_noindex_ids = قاعده واحد hodima_is_noindex):
 *
 *   - قبلا ستون و فیلتر فقط آرایه سریالایز _seobox_robots را می‌شناختند؛
 *     ردیف قدیمی رشته‌ای («noindex,follow») روی سایت noindex بود ولی در
 *     فهرست «Index» نشان داده می‌شد و فیلترها برعکس عمل می‌کردند.
 *   - فیلتر فهرست دسته‌ها با خطای کشنده صفحه را از کار می‌انداخت:
 *     meta_query پیش‌فرض WP_Term_Query رشته خالی است و «$q[] = …» روی
 *     رشته Fatal Error می‌دهد. حالا شناسه‌ها با include/exclude اعمال می‌شوند.
 *   - منوی کشویی فیلتر روی هوک ناموجود restrict_manage_terms ثبت شده بود
 *     و هرگز نمایش داده نمی‌شد؛ حالا پیوندهای «همه | ایندکس | noindex» با
 *     شمارنده بالای جدول (views_edit-{taxonomy}).
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** مقدار فیلتر فعلی: 'index' | 'noindex' | ''. */
function seobox_list_filter_value(): string {
	$value = isset( $_GET['seobox_index_filter'] ) && is_string( $_GET['seobox_index_filter'] ) ? sanitize_key( wp_unslash( $_GET['seobox_index_filter'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- فقط فیلتر نمایش
	return in_array( $value, [ 'index', 'noindex' ], true ) ? $value : '';
}

/**
 * ثبت هوک‌ها در admin_init: قبلا هنگام بارگذاری افزونه (پیش از قالب و
 * ثبت تکسونومی‌های ووکامرس) ثبت می‌شدند و فیلتر seobox_post_types قالب
 * روی ستون اثر نداشت ولی روی کادر سئو داشت.
 */
add_action( 'admin_init', static function (): void {

	foreach ( seobox_post_types() as $post_type ) {
		add_filter( "manage_edit-{$post_type}_columns", 'seobox_add_robots_column' );
		add_action( "manage_{$post_type}_posts_custom_column", 'seobox_render_posts_robots_column', 10, 2 );
	}

	foreach ( seobox_taxonomies() as $taxonomy ) {
		add_filter( "manage_edit-{$taxonomy}_columns", 'seobox_add_robots_column' );
		add_filter( "manage_{$taxonomy}_custom_column", 'seobox_render_terms_robots_column', 10, 3 );
		add_filter( "views_edit-{$taxonomy}", 'seobox_term_views' );
	}
} );

/* =====================================================================
 * ۱. نوشته‌ها
 * ===================================================================== */

add_action( 'restrict_manage_posts', 'seobox_add_post_index_filter', 10, 2 );

function seobox_add_post_index_filter( string $post_type = '', string $which = 'top' ): void {

	if ( 'top' !== $which || ! in_array( $post_type, seobox_post_types(), true ) ) {
		return;
	}

	$selected = seobox_list_filter_value();
	?>
	<label class="screen-reader-text" for="seobox_index_filter">فیلتر وضعیت ایندکس</label>
	<select name="seobox_index_filter" id="seobox_index_filter">
		<option value="">همه وضعیت‌های سئو</option>
		<option value="index" <?php selected( $selected, 'index' ); ?>>قابل ایندکس (Index)</option>
		<option value="noindex" <?php selected( $selected, 'noindex' ); ?>>نوایندکس (Noindex)</option>
	</select>
	<?php
}

add_action( 'pre_get_posts', 'seobox_filter_posts_by_index_status' );

function seobox_filter_posts_by_index_status( WP_Query $query ): void {

	global $pagenow;

	$filter = seobox_list_filter_value();

	if ( '' === $filter || 'edit.php' !== $pagenow || ! $query->is_main_query() ) {
		return;
	}

	$post_type = (string) ( $query->get( 'post_type' ) ?: 'post' );

	if ( ! in_array( $post_type, seobox_post_types(), true ) ) {
		return;
	}

	$ids = seobox_noindex_ids( 'post' );

	if ( 'noindex' === $filter ) {
		$current = array_map( 'intval', (array) $query->get( 'post__in' ) );
		$ids     = $current ? array_values( array_intersect( $current, $ids ) ) : $ids;
		$query->set( 'post__in', $ids ?: [ 0 ] ); // [0] = هیچ نتیجه (آرایه خالی یعنی بدون محدودیت)
	} else {
		$query->set( 'post__not_in', array_values( array_unique( [ ...array_map( 'intval', (array) $query->get( 'post__not_in' ) ), ...$ids ] ) ) );
	}
}

/* =====================================================================
 * ۲. ترم‌ها
 * ===================================================================== */

/**
 * @param array<string, string> $views
 * @return array<string, string>
 */
function seobox_term_views( array $views ): array {

	$screen   = get_current_screen();
	$taxonomy = $screen ? (string) $screen->taxonomy : '';

	if ( '' === $taxonomy ) {
		return $views;
	}

	$total   = (int) wp_count_terms( [ 'taxonomy' => $taxonomy, 'hide_empty' => false ] );
	$noindex = count( seobox_noindex_ids( 'term', $taxonomy ) );
	$current = seobox_list_filter_value();
	$base    = remove_query_arg( [ 'seobox_index_filter', 'paged', 'orderby', 'order' ] );

	$link = static fn( string $value, string $label, int $count ): string => sprintf(
		'<a href="%1$s"%2$s>%3$s <span class="count">(%4$s)</span></a>',
		esc_url( '' === $value ? $base : add_query_arg( 'seobox_index_filter', $value, $base ) ),
		$current === $value ? ' class="current" aria-current="page"' : '',
		esc_html( $label ),
		esc_html( number_format_i18n( $count ) )
	);

	return [
		...$views,
		'seobox_all'     => $link( '', 'همه', $total ),
		'seobox_index'   => $link( 'index', 'قابل ایندکس', max( 0, $total - $noindex ) ),
		'seobox_noindex' => $link( 'noindex', 'نوایندکس', $noindex ),
	];
}

/*
 * تکسونومی درختی (دسته‌ها) بدون مرتب‌سازی، کل درخت را با number=0 می‌گیرد
 * و فقط ترم‌هایی را نشان می‌دهد که والدشان هم در نتیجه باشد؛ با فیلتر،
 * زیردسته‌ای که والدش حذف شده هرگز دیده نمی‌شد. با فیلتر فعال، فهرست
 * مثل حالت مرتب‌شده وردپرس تخت (و صفحه‌بندی‌شده) نمایش داده می‌شود.
 */
add_action( 'load-edit-tags.php', static function (): void {
	if ( '' !== seobox_list_filter_value() && empty( $_GET['orderby'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$_GET['orderby'] = $_REQUEST['orderby'] = 'name';
	}
} );

add_action( 'pre_get_terms', 'seobox_filter_terms_by_index_status' );

function seobox_filter_terms_by_index_status( WP_Term_Query $query ): void {

	global $pagenow, $taxnow;

	$filter = seobox_list_filter_value();

	if ( '' === $filter || 'edit-tags.php' !== $pagenow || ! is_string( $taxnow ) || ! in_array( $taxnow, seobox_taxonomies(), true ) ) {
		return;
	}

	$vars = &$query->query_vars;

	if ( (array) ( $vars['taxonomy'] ?? [] ) !== [ $taxnow ] ) {
		return;
	}

	/*
	 * فقط کوئری جدول فهرست (آرگومان «page» را فقط WP_Terms_List_Table
	 * می‌فرستد) و شمارش صفحه‌بندی آن (fields=count)؛ منوی «دسته والد»
	 * فرم افزودن و ابر برچسب‌های پرکاربرد دست نمی‌خورند.
	 */
	if ( ! array_key_exists( 'page', $vars ) && 'count' !== ( $vars['fields'] ?? '' ) ) {
		return;
	}

	$ids = seobox_noindex_ids( 'term', $taxnow );

	if ( 'noindex' === $filter ) {
		$vars['include'] = $ids ?: [ 0 ];
	} else {
		$vars['exclude'] = array_values( array_unique( [ ...wp_parse_id_list( $vars['exclude'] ?? [] ), ...$ids ] ) );
	}
}

/* =====================================================================
 * ۳. ستون «وضعیت سئو»
 * ===================================================================== */

/**
 * @param array<string, string>|mixed $columns
 * @return array<string, string>|mixed
 */
function seobox_add_robots_column( mixed $columns ): mixed {
	if ( is_array( $columns ) ) {
		$columns['seobox_status'] = 'وضعیت سئو';
	}
	return $columns;
}

/** نشان‌های ستون. */
function seobox_status_badges( int $id, string $type ): string {

	$robots = seobox_object_robots( $id, $type );
	$html   = $robots['index']
		? '<span class="seobox-badge seobox-badge--index">Index</span>'
		: '<span class="seobox-badge seobox-badge--noindex"' . ( $robots['external'] ? ' title="noindex از تنظیم قدیمی (افزونه سئوی قبلی)"' : '' ) . '>Noindex</span>';

	if ( ! $robots['follow'] ) {
		$html .= ' <span class="seobox-badge seobox-badge--nofollow">Nofollow</span>';
	}

	return $html;
}

function seobox_render_posts_robots_column( string $column, int $post_id ): void {
	if ( 'seobox_status' === $column ) {
		echo seobox_status_badges( $post_id, 'post' ); // phpcs:ignore WordPress.Security.EscapeOutput -- ثابت
	}
}

function seobox_render_terms_robots_column( mixed $content, string $column_name, int $term_id ): mixed {
	return 'seobox_status' === $column_name ? seobox_status_badges( $term_id, 'term' ) : $content;
}