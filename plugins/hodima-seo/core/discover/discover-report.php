<?php
/**
 * ماژول «Google Discover» — صفحه گزارش و ستون فهرست نوشته‌ها
 * Path: core/discover/discover-report.php
 *
 * «ابزارهای هدیما ← Google Discover»:
 *   تب «آمادگی نوشته‌ها»: همان فهرست بررسی کادر ویرایش، برای همه نوشته‌ها
 *     و برگه‌های منتشرشده (۳۰۰ آخر)، با شمارش و فیلتر «فقط مشکل‌دار».
 *   تب «آمار Search Console»: کلیک و نمایش واقعی Discover هر صفحه (۲۸ روز)
 *     و تنظیم property.
 * و ستون «Discover» در فهرست نوشته‌ها/برگه‌ها.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

const HODIMA_SEO_DISCOVER_PAGE = 'hodima-discover';

/** تعداد نوشته‌های بررسی‌شده در گزارش (جدیدترین‌ها). */
const HODIMA_SEO_DISCOVER_REPORT_LIMIT = 300;

add_action( 'admin_menu', static function (): void {

	$parent = function_exists( 'hodima_admin_menu_parent' ) ? hodima_admin_menu_parent() : '';

	if ( '' === $parent ) {
		add_menu_page( 'Google Discover', 'Google Discover', 'edit_others_posts', HODIMA_SEO_DISCOVER_PAGE, 'hodima_seo_discover_render_page', 'dashicons-visibility', 26 );
		return;
	}

	add_submenu_page( $parent, 'Google Discover', 'Google Discover', 'edit_others_posts', HODIMA_SEO_DISCOVER_PAGE, 'hodima_seo_discover_render_page' );
}, 20 );

/**
 * آدرس صفحه گزارش.
 *
 * @param array<string, string> $args
 */
function hodima_seo_discover_page_url( array $args = [] ): string {
	return add_query_arg( [ 'page' => HODIMA_SEO_DISCOVER_PAGE ] + $args, admin_url( 'admin.php' ) );
}

/* =====================================================================
 * داده گزارش
 * ===================================================================== */

/** بیشترین دسته‌های بررسی‌شده در گزارش. */
const HODIMA_SEO_DISCOVER_REPORT_TERMS = 200;

/**
 * خلاصه آمادگی نوشته‌ها، برگه‌ها، محصولات (منتشرشده) و دسته‌های محصول
 * (کش یک ساعته؛ با ذخیره هر نوشته/دسته پاک می‌شود).
 *
 * @return list<array{id: int, context: string, type: string, title: string, edit: string, date: string, error: int, warn: int, ok: int, total: int, issues: list<array{status: string, label: string, detail: string}>}>
 */
function hodima_seo_discover_report_rows(): array {

	$cached = get_transient( 'hodima_discover_report' );

	if ( is_array( $cached ) ) {
		return $cached;
	}

	$objects = [];
	$types   = array_values( array_filter( hodima_seo_discover_post_types(), 'post_type_exists' ) );

	if ( $types ) {
		foreach ( get_posts( [
			'post_type'      => $types,
			'post_status'    => 'publish',
			'has_password'   => false,
			'posts_per_page' => HODIMA_SEO_DISCOVER_REPORT_LIMIT,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'no_found_rows'  => true,
		] ) as $post ) {
			$objects[] = $post;
		}
	}

	$taxonomies = array_values( array_filter( hodima_seo_discover_taxonomies(), 'taxonomy_exists' ) );
	$terms      = $taxonomies ? get_terms( [ 'taxonomy' => $taxonomies, 'hide_empty' => false, 'number' => HODIMA_SEO_DISCOVER_REPORT_TERMS ] ) : [];
	foreach ( is_array( $terms ) ? $terms : [] as $term ) {
		$objects[] = $term;
	}

	$rows = [];

	foreach ( $objects as $target ) {

		$is_term = $target instanceof WP_Term;
		$id      = $is_term ? (int) $target->term_id : (int) $target->ID;

		if ( $is_term ? ! hodima_seo_discover_for_term( $id ) : ! hodima_seo_discover_for_post( $id ) ) {
			continue;
		}

		$checks          = hodima_seo_discover_checks( $target );
		[ $ok, $total ]  = hodima_seo_discover_score( $checks );
		$issues          = array_values( array_filter( $checks, static fn( array $c ): bool => 'ok' !== $c['status'] ) );

		$rows[] = [
			'id'      => $id,
			'context' => $is_term ? 'term' : 'post',
			'type'    => $is_term ? $target->taxonomy : $target->post_type,
			'title'   => $is_term ? $target->name : (string) get_the_title( $target ),
			'edit'    => (string) ( $is_term ? get_edit_term_link( $id, $target->taxonomy ) : get_edit_post_link( $id, 'raw' ) ),
			'date'    => $is_term ? '' : (string) get_the_date( '', $target ),
			'error'   => count( array_filter( $issues, static fn( array $c ): bool => 'error' === $c['status'] ) ),
			'warn'    => count( array_filter( $issues, static fn( array $c ): bool => 'warn' === $c['status'] ) ),
			'ok'      => $ok,
			'total'   => $total,
			'issues'  => array_map( static fn( array $c ): array => [ 'status' => $c['status'], 'label' => $c['label'], 'detail' => $c['detail'] ], $issues ),
		];
	}

	set_transient( 'hodima_discover_report', $rows, HOUR_IN_SECONDS );

	return $rows;
}

/** برچسب فارسی نوع ردیف گزارش. */
function hodima_seo_discover_type_label( string $type ): string {
	return match ( $type ) {
		'post'        => 'نوشته',
		'page'        => 'برگه',
		'product'     => 'محصول',
		'product_cat' => 'دسته محصول',
		default       => (string) ( get_post_type_object( $type )?->labels->singular_name ?? get_taxonomy( $type )?->labels->singular_name ?? $type ),
	};
}

// هر تغییر نوشته، تصویر یا نمایه نویسنده: گزارش از نو
foreach ( [ 'save_post', 'deleted_post', 'edit_attachment', 'profile_update', 'edited_term', 'delete_term' ] as $hodima_seo_discover_hook ) {
	add_action( $hodima_seo_discover_hook, static function (): void {
		delete_transient( 'hodima_discover_report' );
	} );
}
unset( $hodima_seo_discover_hook );

/**
 * برچسب وضعیت یک ردیف: [ کلاس pill, متن ].
 *
 * @return array{0: string, 1: string}
 */
function hodima_seo_discover_row_state( int $error, int $warn ): array {
	return match ( true ) {
		$error > 0 => [ 'hd-pill--error', 'مشکل دارد' ],
		$warn > 0  => [ 'hd-pill--warn', 'قابل بهتر شدن' ],
		default    => [ 'hd-pill--ok', 'آماده' ],
	};
}

/* =====================================================================
 * عملیات (admin-post)
 * ===================================================================== */

add_action( 'admin_post_hodima_discover_sc', static function (): void {

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'دسترسی ندارید.', '', [ 'response' => 403 ] );
	}

	check_admin_referer( 'hodima_discover_sc' );

	$property = isset( $_POST['property'] ) ? sanitize_text_field( wp_unslash( $_POST['property'] ) ) : '';
	$property = str_starts_with( $property, 'sc-domain:' ) ? $property : ( '' !== $property ? trailingslashit( esc_url_raw( $property, [ 'https', 'http' ] ) ) : '' );

	update_option( HODIMA_SEO_DISCOVER_SC_OPTION, [ 'property' => $property ], false );

	$result = hodima_seo_discover_sc_refresh();

	if ( function_exists( 'hodima_admin_flash' ) ) {
		is_wp_error( $result )
			? hodima_admin_flash( $result->get_error_message(), 'error' )
			: hodima_admin_flash( 'آمار Discover از Search Console به‌روز شد.', 'success' );
	}

	wp_safe_redirect( hodima_seo_discover_page_url( [ 'tab' => 'stats' ] ) );
	exit;
} );

/* =====================================================================
 * صفحه
 * ===================================================================== */

function hodima_seo_discover_render_page(): void {

	$tab  = isset( $_GET['tab'] ) && 'stats' === sanitize_key( wp_unslash( $_GET['tab'] ) ) ? 'stats' : 'report'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط انتخاب تب
	$tabs = [
		'report' => [ 'label' => 'آمادگی نوشته‌ها', 'url' => hodima_seo_discover_page_url(), 'icon' => 'dashicons-yes-alt' ],
		'stats'  => [ 'label' => 'آمار Search Console', 'url' => hodima_seo_discover_page_url( [ 'tab' => 'stats' ] ), 'icon' => 'dashicons-chart-bar' ],
	];
	?>
	<div class="wrap hd-wrap">
		<?php
		hodima_admin_header( [
			'title'       => 'Google Discover',
			'description' => 'فید پیشنهادی گوگل در موبایل؛ برای مقاله‌ها پرورودی‌ترین مسیر گوگل. آمادگی همه نوشته‌ها و آمار واقعی Discover در یک جا.',
			'icon'        => 'dashicons-visibility',
			'tabs'        => $tabs,
			'current'     => $tab,
		] );

		'stats' === $tab ? hodima_seo_discover_render_stats() : hodima_seo_discover_render_report();
		?>
	</div>
	<?php
}

/** تب «آمادگی نوشته‌ها». */
function hodima_seo_discover_render_report(): void {

	$rows   = hodima_seo_discover_report_rows();
	$stats  = hodima_seo_discover_stats();
	$only   = isset( $_GET['issues'] ) && '1' === sanitize_key( wp_unslash( $_GET['issues'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط فیلتر نمایش
	$type   = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط فیلتر نمایش
	$types  = array_values( array_unique( array_column( $rows, 'type' ) ) );
	$type   = in_array( $type, $types, true ) ? $type : '';
	$ready  = count( array_filter( $rows, static fn( array $r ): bool => 0 === $r['error'] && 0 === $r['warn'] ) );
	$errors = count( array_filter( $rows, static fn( array $r ): bool => $r['error'] > 0 ) );
	$warns  = count( $rows ) - $ready - $errors;

	$list = array_values( array_filter(
		$rows,
		static fn( array $r ): bool => ( '' === $type || $type === $r['type'] ) && ( ! $only || $r['error'] > 0 || $r['warn'] > 0 )
	) );
	$args  = array_filter( [ 'type' => $type, 'issues' => $only ? '1' : '' ] );
	$per   = 25;
	$paged = max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط صفحه‌بندی
	$pages = max( 1, (int) ceil( count( $list ) / $per ) );
	$list  = array_slice( $list, ( min( $paged, $pages ) - 1 ) * $per, $per );
	?>
	<div class="hd-grid hd-grid--stats">
		<div class="hd-stat"><span class="hd-stat__label">صفحه بررسی‌شده</span><span class="hd-stat__value"><?php echo esc_html( number_format_i18n( count( $rows ) ) ); ?></span></div>
		<div class="hd-stat"><span class="hd-stat__label">آماده</span><span class="hd-stat__value"><?php echo esc_html( number_format_i18n( $ready ) ); ?></span></div>
		<div class="hd-stat"><span class="hd-stat__label">قابل بهتر شدن</span><span class="hd-stat__value"><?php echo esc_html( number_format_i18n( $warns ) ); ?></span></div>
		<div class="hd-stat"><span class="hd-stat__label">مشکل دارد</span><span class="hd-stat__value"><?php echo esc_html( number_format_i18n( $errors ) ); ?></span></div>
		<?php if ( $stats['fetched'] ) : ?>
			<div class="hd-stat"><span class="hd-stat__label">کلیک Discover (۲۸ روز)</span><span class="hd-stat__value"><?php echo esc_html( number_format_i18n( $stats['totals']['clicks'] ) ); ?></span></div>
			<div class="hd-stat"><span class="hd-stat__label">نمایش Discover (۲۸ روز)</span><span class="hd-stat__value"><?php echo esc_html( number_format_i18n( $stats['totals']['impressions'] ) ); ?></span></div>
		<?php endif; ?>
	</div>

	<section class="hd-card">
		<div class="hd-card__head">
			<?php echo hodima_admin_icon( 'dashicons-list-view' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escape‌شده در تابع ?>
			<h2 class="hd-card__title">آمادگی نوشته‌ها، برگه‌ها، محصولات و دسته‌ها</h2>
			<p class="hd-card__desc">همان «آمادگی برای Discover» کادر ویرایش: تصویر بزرگ ≥۱۲۰۰ پیکسل، برش‌ها، ایندکس و پیش‌نمایش تصویر بزرگ، عنوان بدون طعمه کلیک، متن معرفی، خلاصه و معرفی نویسنده. <?php echo esc_html( number_format_i18n( HODIMA_SEO_DISCOVER_REPORT_LIMIT ) ); ?> صفحه آخر و دسته‌های محصول.</p>
		</div>
		<div class="hd-inline">
			<a class="button<?php echo '' === $type ? ' button-primary' : ''; ?>" href="<?php echo esc_url( hodima_seo_discover_page_url( array_filter( [ 'issues' => $only ? '1' : '' ] ) ) ); ?>">همه</a>
			<?php foreach ( $types as $t ) : ?>
				<a class="button<?php echo $t === $type ? ' button-primary' : ''; ?>" href="<?php echo esc_url( hodima_seo_discover_page_url( array_filter( [ 'type' => $t, 'issues' => $only ? '1' : '' ] ) ) ); ?>"><?php echo esc_html( hodima_seo_discover_type_label( $t ) ); ?></a>
			<?php endforeach; ?>
			<a class="button<?php echo $only ? ' button-primary' : ''; ?>" href="<?php echo esc_url( hodima_seo_discover_page_url( array_filter( [ 'type' => $type, 'issues' => $only ? '' : '1' ] ) ) ); ?>" aria-pressed="<?php echo $only ? 'true' : 'false'; ?>"><?php echo hodima_admin_icon( 'dashicons-warning' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escape‌شده در تابع ?> فقط نیازمند توجه</a>
		</div>

		<?php if ( ! $list ) : ?>
			<p class="hd-empty">موردی نیست.</p>
		<?php else : ?>
			<div class="hd-table-wrap">
				<table class="widefat striped">
					<thead>
						<tr>
							<th scope="col">عنوان</th>
							<th scope="col">آمادگی</th>
							<th scope="col">موارد</th>
							<?php if ( $stats['fetched'] ) : ?>
								<th scope="col">Discover (کلیک / نمایش)</th>
							<?php endif; ?>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $list as $row ) : ?>
							<?php
							[ $class, $label ] = hodima_seo_discover_row_state( $row['error'], $row['warn'] );
							$post_stats        = $stats['fetched'] ? hodima_seo_discover_post_stats( $row['id'], $row['context'] ) : null;
							?>
							<tr>
								<td>
									<strong><a href="<?php echo esc_url( $row['edit'] ); ?>"><?php echo esc_html( '' !== $row['title'] ? $row['title'] : '(بدون عنوان)' ); ?></a></strong>
									<div class="hd-muted"><?php echo esc_html( hodima_seo_discover_type_label( $row['type'] ) . ( '' !== $row['date'] ? ' · ' . $row['date'] : '' ) ); ?></div>
								</td>
								<td><span class="hd-pill <?php echo esc_attr( $class ); ?>"><?php echo esc_html( sprintf( '%1$s · %2$s از %3$s', $label, number_format_i18n( $row['ok'] ), number_format_i18n( $row['total'] ) ) ); ?></span></td>
								<td>
									<?php foreach ( $row['issues'] as $issue ) : ?>
										<span class="hd-pill <?php echo esc_attr( 'error' === $issue['status'] ? 'hd-pill--error' : 'hd-pill--warn' ); ?>" title="<?php echo esc_attr( $issue['detail'] ); ?>"><?php echo esc_html( $issue['label'] ); ?></span>
									<?php endforeach; ?>
								</td>
								<?php if ( $stats['fetched'] ) : ?>
									<td dir="ltr"><?php echo null !== $post_stats ? esc_html( number_format_i18n( $post_stats['clicks'] ) . ' / ' . number_format_i18n( $post_stats['impressions'] ) ) : '—'; ?></td>
								<?php endif; ?>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php if ( $pages > 1 ) : ?>
				<div class="tablenav"><div class="tablenav-pages">
					<?php
					echo wp_kses_post( (string) paginate_links( [
						'base'    => add_query_arg( [ 'paged' => '%#%' ] + $args ),
						'format'  => '',
						'current' => min( $paged, $pages ),
						'total'   => $pages,
					] ) );
					?>
				</div></div>
			<?php endif; ?>
		<?php endif; ?>
	</section>
	<?php
}

/** تب «آمار Search Console». */
function hodima_seo_discover_render_stats(): void {

	$stats    = hodima_seo_discover_stats();
	$email    = hodima_seo_discover_sc_email();
	$has_key  = '' !== hodima_seo_discover_sc_key_json();
	$can_edit = current_user_can( 'manage_options' );

	if ( '' !== $stats['error'] && function_exists( 'hodima_admin_notice' ) ) {
		hodima_admin_notice( $stats['error'], 'warning' );
	}

	$top = $stats['rows'];
	uasort( $top, static fn( array $a, array $b ): int => $b['impressions'] <=> $a['impressions'] );
	$top = array_slice( $top, 0, 50, true );
	?>
	<?php if ( $stats['fetched'] ) : ?>
		<div class="hd-grid hd-grid--stats">
			<div class="hd-stat"><span class="hd-stat__label">کلیک Discover</span><span class="hd-stat__value"><?php echo esc_html( number_format_i18n( $stats['totals']['clicks'] ) ); ?></span></div>
			<div class="hd-stat"><span class="hd-stat__label">نمایش Discover</span><span class="hd-stat__value"><?php echo esc_html( number_format_i18n( $stats['totals']['impressions'] ) ); ?></span></div>
			<div class="hd-stat"><span class="hd-stat__label">نرخ کلیک</span><span class="hd-stat__value"><?php echo esc_html( $stats['totals']['impressions'] ? number_format_i18n( 100 * $stats['totals']['clicks'] / $stats['totals']['impressions'], 1 ) . '٪' : '—' ); ?></span></div>
			<div class="hd-stat"><span class="hd-stat__label">صفحه‌های دیده‌شده</span><span class="hd-stat__value"><?php echo esc_html( number_format_i18n( count( $stats['rows'] ) ) ); ?></span></div>
		</div>
	<?php endif; ?>

	<section class="hd-card">
		<div class="hd-card__head">
			<?php echo hodima_admin_icon( 'dashicons-chart-bar' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escape‌شده در تابع ?>
			<h2 class="hd-card__title">پرنمایش‌ترین صفحه‌ها در Discover</h2>
			<p class="hd-card__desc">
				<?php if ( $stats['fetched'] ) : ?>
					<?php echo esc_html( sprintf( 'از %1$s تا %2$s (داده Discover با دو روز تاخیر می‌رسد) — property: %3$s — آخرین دریافت: %4$s', $stats['start'], $stats['end'], $stats['property'], wp_date( 'Y/m/d H:i', $stats['fetched'] ) ) ); ?>
				<?php else : ?>
					هنوز آماری گرفته نشده است. اگر سایت هنوز در Discover نمایش نداشته، Search Console هم داده‌ای ندارد.
				<?php endif; ?>
			</p>
		</div>
		<?php if ( $top ) : ?>
			<div class="hd-table-wrap">
				<table class="widefat striped">
					<thead><tr><th scope="col">صفحه</th><th scope="col">کلیک</th><th scope="col">نمایش</th><th scope="col">نرخ کلیک</th></tr></thead>
					<tbody>
						<?php foreach ( $top as $path => $row ) : ?>
							<tr>
								<td dir="ltr"><a href="<?php echo esc_url( home_url( (string) $path ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( (string) $path ); ?></a></td>
								<td><?php echo esc_html( number_format_i18n( $row['clicks'] ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $row['impressions'] ) ); ?></td>
								<td><?php echo esc_html( $row['impressions'] ? number_format_i18n( 100 * $row['clicks'] / $row['impressions'], 1 ) . '٪' : '—' ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php elseif ( $stats['fetched'] ) : ?>
			<p class="hd-empty">در این بازه هیچ صفحه‌ای از سایت در Discover نمایش داده نشده است.</p>
		<?php endif; ?>
	</section>

	<section class="hd-card">
		<div class="hd-card__head">
			<?php echo hodima_admin_icon( 'dashicons-admin-network' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escape‌شده در تابع ?>
			<h2 class="hd-card__title">اتصال به Search Console</h2>
			<p class="hd-card__desc">از همان کلید سرویس اکانت ماژول «Google Indexing API» استفاده می‌شود (فقط دسترسی خواندنی). آمار روزی یک بار خودکار به‌روز می‌شود.</p>
		</div>
		<?php if ( ! $has_key ) : ?>
			<p class="hd-callout hd-callout--warning">کلید سرویس اکانت تنظیم نشده است. اول ماژول «Google Indexing API» را روشن و کلید JSON را در تنظیمات آن وارد کنید.</p>
		<?php else : ?>
			<p class="hd-muted">ایمیل سرویس اکانت باید در Search Console ← تنظیمات ← کاربران و مجوزها، کاربر همین property باشد: <code dir="ltr"><?php echo esc_html( $email ); ?></code></p>
		<?php endif; ?>
		<?php if ( $can_edit ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="hodima_discover_sc">
				<?php wp_nonce_field( 'hodima_discover_sc' ); ?>
				<div class="hd-fields">
				<div class="hd-field hd-field--wide">
					<label class="hd-field__label" for="hodima-discover-property">property در Search Console (اختیاری)</label>
					<input type="text" id="hodima-discover-property" name="property" dir="ltr" value="<?php echo esc_attr( hodima_seo_discover_sc_property() ); ?>" placeholder="<?php echo esc_attr( implode( '  یا  ', hodima_seo_discover_sc_candidates() ) ); ?>">
					<p class="hd-field__help">خالی = اول آدرس سایت و بعد دامنه (sc-domain) خودکار امتحان می‌شود.</p>
				</div>
				</div>
				<div class="hd-actions">
					<button type="submit" class="button button-primary" <?php disabled( ! $has_key ); ?>><?php echo hodima_admin_icon( 'dashicons-update' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escape‌شده در تابع ?> ذخیره و به‌روزرسانی آمار</button>
				</div>
			</form>
		<?php endif; ?>
	</section>
	<?php
}

/* =====================================================================
 * ستون «Discover» در فهرست نوشته‌ها و برگه‌ها
 * ===================================================================== */

/** نشان آمادگی یک شیء برای ستون فهرست‌ها. */
function hodima_seo_discover_column_html( WP_Post|WP_Term $target ): string {

	$checks = hodima_seo_discover_checks( $target );
	$issues = array_filter( $checks, static fn( array $c ): bool => 'ok' !== $c['status'] );
	$error  = count( array_filter( $issues, static fn( array $c ): bool => 'error' === $c['status'] ) );
	[ $ok, $total ] = hodima_seo_discover_score( $checks );

	$icon = match ( true ) {
		$error > 0 => [ 'dashicons-dismiss', 'is-error', 'Discover: مشکل دارد' ],
		(bool) $issues => [ 'dashicons-warning', 'is-warn', 'Discover: قابل بهتر شدن' ],
		default    => [ 'dashicons-yes-alt', 'is-ok', 'Discover: آماده' ],
	};
	$title = sprintf( '%1$s (%2$s از %3$s)', $icon[2], number_format_i18n( $ok ), number_format_i18n( $total ) )
		. ( $issues ? ' — ' . implode( ' · ', array_column( $issues, 'label' ) ) : '' );

	return sprintf(
		'<span class="dashicons %1$s hodima-dc-dot %2$s" title="%3$s" aria-hidden="true"></span><span class="screen-reader-text">%3$s</span>',
		esc_attr( $icon[0] ),
		esc_attr( $icon[1] ),
		esc_attr( $title )
	);
}

add_action( 'admin_init', static function (): void {

	$heading = '<span class="dashicons dashicons-visibility" aria-hidden="true"></span><span class="screen-reader-text">Discover</span>';

	// نوشته، برگه، محصول
	foreach ( hodima_seo_discover_post_types() as $post_type ) {

		add_filter( "manage_{$post_type}_posts_columns", static function ( array $columns ) use ( $heading ): array {
			$columns['hodima_discover'] = $heading;
			return $columns;
		} );

		add_action( "manage_{$post_type}_posts_custom_column", static function ( string $column, int $post_id ): void {
			$post = get_post( $post_id );
			if ( 'hodima_discover' === $column && $post instanceof WP_Post ) {
				echo hodima_seo_discover_column_html( $post ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escape‌شده در تابع
			}
		}, 10, 2 );
	}

	// دسته محصول
	foreach ( hodima_seo_discover_taxonomies() as $taxonomy ) {

		add_filter( "manage_edit-{$taxonomy}_columns", static function ( array $columns ) use ( $heading ): array {
			$columns['hodima_discover'] = $heading;
			return $columns;
		} );

		add_filter( "manage_{$taxonomy}_custom_column", static function ( string $content, string $column, int $term_id ): string {
			$term = get_term( $term_id );
			return 'hodima_discover' === $column && $term instanceof WP_Term ? $content . hodima_seo_discover_column_html( $term ) : $content;
		}, 10, 3 );
	}
} );

/** رنگ نشان ستون (فقط صفحه فهرست، فقط زیر همان ستون). */
add_action( 'admin_enqueue_scripts', static function (): void {

	$screen = get_current_screen();

	// فهرست نوشته‌ها/محصولات (edit) و فهرست دسته‌ها (edit-tags)
	if ( ! $screen || ! in_array( $screen->base, [ 'edit', 'edit-tags' ], true ) ) {
		return;
	}

	wp_register_style( 'hodima-discover-list', false, [], HODIMA_SEO_VERSION );
	wp_enqueue_style( 'hodima-discover-list' );
	wp_add_inline_style( 'hodima-discover-list', '.column-hodima_discover{inline-size:2.5rem}.column-hodima_discover .hodima-dc-dot.is-ok{color:#1f7a4d}.column-hodima_discover .hodima-dc-dot.is-warn{color:#9a5b00}.column-hodima_discover .hodima-dc-dot.is-error{color:#b3261e}' );
} );
