<?php
/**
 * ماژول «گوگل دیسکاور» — صفحه گزارش و ستون فهرست‌ها
 * Path: core/discover/discover-report.php
 *
 * «ابزارهای هدیما ← گوگل دیسکاور»:
 *   تب «آمادگی»: فهرست بررسی کادر ویرایش برای ۱۰۰۰ صفحه منتشرشده آخر و
 *     دسته‌های محصول (از کش هر صفحه؛ discover-cache.php)، با شمارش، فیلتر،
 *     مرتب‌سازی (تاریخ، آمادگی، نمایش، کلیک) و «ساخت برش برای همه».
 *   تب «فرصت‌ها»: آمادگی کنار آمار واقعی (discover-insights.php).
 *   تب «آمار سرچ کنسول»: مقایسه با ۲۸ روز قبل، نمودار روزانه، صفحه‌ها
 *     و تنظیم property.
 * و ستون «دیسکاور» در فهرست نوشته‌ها، برگه‌ها، محصولات و دسته‌های محصول.
 *
 * کارهای طولانی (ساختن ردیف‌های تازه، برش تصویر همه صفحه‌ها) دسته‌دسته با
 * admin-ajax و نوار پیشرفت انجام می‌شوند تا هیچ درخواستی به محدودیت زمان
 * سرور نخورد (discover-report.js).
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

const HODIMA_SEO_DISCOVER_PAGE = 'hodima-discover';

/** سقف نوشته، برگه و محصول منتشرشده بررسی‌شده در گزارش، از هر نوع جدا (جدیدترین‌ها). */
const HODIMA_SEO_DISCOVER_REPORT_LIMIT = 1000;

/** بیشترین دسته‌های بررسی‌شده در گزارش. */
const HODIMA_SEO_DISCOVER_REPORT_TERMS = 300;

/** صفحه در هر دسته «ساخت برش برای همه». */
const HODIMA_SEO_DISCOVER_CROP_BATCH = 6;

/** حداکثر زمان کار هر درخواست دسته‌ای (ثانیه). */
const HODIMA_SEO_DISCOVER_BATCH_SECONDS = 8.0;

add_action( 'admin_menu', static function (): void {

	$parent = function_exists( 'hodima_admin_menu_parent' ) ? hodima_admin_menu_parent() : '';

	if ( '' === $parent ) {
		add_menu_page( 'گوگل دیسکاور', 'گوگل دیسکاور', 'edit_others_posts', HODIMA_SEO_DISCOVER_PAGE, 'hodima_seo_discover_render_page', 'dashicons-visibility', 26 );
		return;
	}

	add_submenu_page( $parent, 'گوگل دیسکاور', 'گوگل دیسکاور', 'edit_others_posts', HODIMA_SEO_DISCOVER_PAGE, 'hodima_seo_discover_render_page' );
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

/**
 * صفحه‌های گزارش: نوشته‌ها، برگه‌ها و محصولات منتشرشده (از هر نوع جدا تا
 * سقف خودش، جدیدترین‌ها؛ متاها یک‌جا در کش وردپرس) و دسته‌های محصول.
 * صفحه‌هایی که برای دیسکاور نیستند (hodima_seo_discover_skip_reason) جدا
 * برمی‌گردند.
 *
 * باگ قبلی (تا SEO 2.1.4): سقف ۱۰۰۰ برای همه نوع‌ها با هم بود؛ با زیاد شدن
 * محصولات، مقاله‌های قدیمی‌تر از گزارش و «فرصت‌ها» بیرون می‌افتادند.
 *
 * @return array{targets: list<WP_Post|WP_Term>, skipped: list<array{target: WP_Post|WP_Term, reason: string}>}
 */
function hodima_seo_discover_report_targets(): array {

	$out   = [ 'targets' => [], 'skipped' => [] ];
	$add   = static function ( WP_Post|WP_Term $target ) use ( &$out ): void {
		$reason = hodima_seo_discover_skip_reason( $target );
		if ( '' === $reason ) {
			$out['targets'][] = $target;
		} else {
			$out['skipped'][] = [ 'target' => $target, 'reason' => $reason ];
		}
	};

	foreach ( array_filter( hodima_seo_discover_post_types(), 'post_type_exists' ) as $type ) {
		$posts = get_posts( [
			'post_type'              => $type,
			'post_status'            => 'publish',
			'has_password'           => false,
			'posts_per_page'         => HODIMA_SEO_DISCOVER_REPORT_LIMIT,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'no_found_rows'          => true,
			'update_post_term_cache' => false,
		] );
		foreach ( $posts as $post ) {
			if ( hodima_seo_discover_for_post( (int) $post->ID ) ) {
				$add( $post );
			}
		}
	}

	$taxonomies = array_values( array_filter( hodima_seo_discover_taxonomies(), 'taxonomy_exists' ) );
	$terms      = $taxonomies ? get_terms( [ 'taxonomy' => $taxonomies, 'hide_empty' => false, 'number' => HODIMA_SEO_DISCOVER_REPORT_TERMS, 'update_term_meta_cache' => true ] ) : [];

	foreach ( is_array( $terms ) ? $terms : [] as $term ) {
		if ( $term instanceof WP_Term && hodima_seo_discover_for_term( (int) $term->term_id ) ) {
			$add( $term );
		}
	}

	// گزارش به ترتیب جدیدترین‌ها (نوع‌ها جدا خوانده شدند)؛ دسته‌ها آخر
	usort( $out['targets'], static fn( WP_Post|WP_Term $a, WP_Post|WP_Term $b ): int => ( $b instanceof WP_Post ? $b->post_date_gmt : '' ) <=> ( $a instanceof WP_Post ? $a->post_date_gmt : '' ) );

	return $out;
}

/**
 * ردیف‌های گزارش. ردیف کش‌شده هر صفحه خوانده می‌شود؛ ردیف تازه فقط تا
 * $budget ثانیه ساخته می‌شود و بقیه «در انتظار» می‌مانند (JS دسته‌دسته
 * کاملشان می‌کند).
 *
 * @return array{rows: list<array<string, mixed>>, pending: int, total: int, skipped: list<array{target: WP_Post|WP_Term, reason: string}>}
 */
function hodima_seo_discover_report_rows( float $budget = 4.0 ): array {

	$start   = microtime( true );
	$rows    = [];
	$pending = 0;
	$found   = hodima_seo_discover_report_targets();
	$targets = $found['targets'];

	foreach ( $targets as $target ) {

		$cached = hodima_seo_discover_row( $target, microtime( true ) - $start < $budget );

		if ( null === $cached ) {
			++$pending;
			continue;
		}

		$is_term = $target instanceof WP_Term;
		$id      = $is_term ? (int) $target->term_id : (int) $target->ID;
		$context = $is_term ? 'term' : 'post';

		$rows[] = [
			'id'      => $id,
			'context' => $context,
			'type'    => $is_term ? $target->taxonomy : $target->post_type,
			'title'   => $is_term ? $target->name : (string) get_the_title( $target ),
			'edit'    => (string) ( $is_term ? get_edit_term_link( $id, $target->taxonomy ) : get_edit_post_link( $id, 'raw' ) ),
			'date'    => $is_term ? '' : (string) get_the_date( '', $target ),
			'ts'      => $is_term ? 0 : (int) strtotime( $target->post_date_gmt . ' UTC' ),
			'url_key' => hodima_seo_discover_url_key( hodima_seo_discover_object_url( $id, $context ) ),
		] + $cached;
	}

	return [ 'rows' => $rows, 'pending' => $pending, 'total' => count( $targets ), 'skipped' => $found['skipped'] ];
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
 * عملیات
 * ===================================================================== */

add_action( 'admin_post_hodima_discover_sc', static function (): void {

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'دسترسی ندارید.', '', [ 'response' => 403 ] );
	}

	check_admin_referer( 'hodima_discover_sc' );

	$back  = hodima_seo_discover_page_url( [ 'tab' => 'stats' ] );
	$flash = static function ( string $message, string $type ): void {
		if ( function_exists( 'hodima_admin_flash' ) ) {
			hodima_admin_flash( $message, $type );
		}
	};

	/*
	 * کلید جدای دیسکاور (از SEO 2.1.5): حساب سرویس (و ایمیلش) را مدیر همین‌جا
	 * عوض یا حذف می‌کند. خالی = کلید فعلی می‌ماند. کلید نامعتبر ذخیره نمی‌شود.
	 */
	if ( isset( $_POST['remove_key'] ) ) {
		delete_option( HODIMA_SEO_DISCOVER_SC_KEY_OPTION );
	} else {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON کلید؛ با hodima_seo_discover_sc_validate_key بررسی و دست‌نخورده ذخیره می‌شود (پاک‌سازی، کلید خصوصی را خراب می‌کند)
		$json = isset( $_POST['key_json'] ) ? trim( (string) wp_unslash( $_POST['key_json'] ) ) : '';
		if ( '' !== $json ) {
			$check = hodima_seo_discover_sc_validate_key( $json );
			if ( ! $check['ok'] ) {
				$flash( 'کلید ذخیره نشد: ' . $check['message'], 'error' );
				wp_safe_redirect( $back );
				exit;
			}
			update_option( HODIMA_SEO_DISCOVER_SC_KEY_OPTION, $json, false );
		}
	}

	$property = isset( $_POST['property'] ) ? sanitize_text_field( wp_unslash( $_POST['property'] ) ) : '';
	$property = str_starts_with( $property, 'sc-domain:' ) ? $property : ( '' !== $property ? trailingslashit( esc_url_raw( $property, [ 'https', 'http' ] ) ) : '' );

	update_option( HODIMA_SEO_DISCOVER_SC_OPTION, [ 'property' => $property ], false );

	if ( '' === hodima_seo_discover_sc_key_json() ) {
		$flash( 'کلید حساب سرویس تنظیم نشده است؛ تا کلید وارد نشود آمار به‌روز نمی‌شود.', 'warning' );
		wp_safe_redirect( $back );
		exit;
	}

	$result = hodima_seo_discover_sc_refresh();

	is_wp_error( $result )
		? $flash( $result->get_error_message(), 'error' )
		: $flash( 'آمار دیسکاور از سرچ کنسول به‌روز شد.', 'success' );

	wp_safe_redirect( $back );
	exit;
} );

/** بررسی دسترسی و nonce درخواست‌های دسته‌ای (پاسخ JSON خطا و پایان در صورت رد). */
function hodima_seo_discover_batch_guard(): void {
	if ( ! current_user_can( 'edit_others_posts' ) || ! check_ajax_referer( 'hodima_discover_batch', 'nonce', false ) ) {
		wp_send_json_error( [ 'message' => 'دسترسی ندارید یا صفحه منقضی شده است؛ صفحه را دوباره باز کنید.' ], 403 );
	}
}

// ساختن ردیف‌های کش‌نشده گزارش، دسته‌دسته
add_action( 'wp_ajax_hodima_discover_warm', static function (): void {
	hodima_seo_discover_batch_guard();
	$result = hodima_seo_discover_report_rows( HODIMA_SEO_DISCOVER_BATCH_SECONDS );
	wp_send_json_success( [
		'done'  => $result['total'] - $result['pending'],
		'total' => $result['total'],
		'next'  => 0,
		'end'   => 0 === $result['pending'],
	] );
} );

/**
 * «ساخت برش برای همه»: برش‌های ۱۶:۹، ۴:۳ و ۱:۱ برای همه صفحه‌های منتشرشده
 * (نه فقط ۱۰۰۰ صفحه گزارش). برش‌ها تا SEO 2.1.1 فقط هنگام ذخیره ساخته
 * می‌شد؛ صفحه‌هایی که بعد از نصب ذخیره نشده بودند og:image بزرگ نداشتند.
 * offset = جای ادامه در فهرست (اول نوشته‌ها به ترتیب شناسه، بعد دسته‌ها).
 */
add_action( 'wp_ajax_hodima_discover_crops', static function (): void {

	hodima_seo_discover_batch_guard();

	$offset     = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- در hodima_seo_discover_batch_guard
	$types      = array_values( array_filter( hodima_seo_discover_post_types(), 'post_type_exists' ) );
	$taxonomies = array_values( array_filter( hodima_seo_discover_taxonomies(), 'taxonomy_exists' ) );
	$posts      = array_sum( array_map( static fn( string $t ): int => (int) ( wp_count_posts( $t )->publish ?? 0 ), $types ) );
	$terms      = $taxonomies ? (int) wp_count_terms( [ 'taxonomy' => $taxonomies, 'hide_empty' => false ] ) : 0;
	$total      = $posts + $terms;
	$start      = microtime( true );
	$made       = 0;
	$done       = 0;

	while ( $done < HODIMA_SEO_DISCOVER_CROP_BATCH && $offset < $total && microtime( true ) - $start < HODIMA_SEO_DISCOVER_BATCH_SECONDS ) {

		if ( $offset < $posts ) {
			$ids     = get_posts( [ 'post_type' => $types, 'post_status' => 'publish', 'orderby' => 'ID', 'order' => 'ASC', 'posts_per_page' => 1, 'offset' => $offset, 'fields' => 'ids', 'no_found_rows' => true ] );
			$id      = (int) ( $ids[0] ?? 0 );
			$context = 'post';
			$enabled = $id && hodima_seo_discover_for_post( $id );
		} else {
			$ids     = get_terms( [ 'taxonomy' => $taxonomies, 'hide_empty' => false, 'orderby' => 'term_id', 'order' => 'ASC', 'number' => 1, 'offset' => $offset - $posts, 'fields' => 'ids' ] );
			$id      = is_array( $ids ) ? (int) ( $ids[0] ?? 0 ) : 0;
			$context = 'term';
			$enabled = $id && hodima_seo_discover_for_term( $id );
		}

		$image = $enabled ? hodima_seo_discover_image( $id, $context ) : null;
		if ( null !== $image ) {
			$made += hodima_seo_discover_make_crops( $image['id'] );
		}

		++$offset;
		++$done;
	}

	wp_send_json_success( [
		'done'  => min( $offset, $total ),
		'total' => $total,
		'next'  => $offset,
		'made'  => $made,
		'end'   => $offset >= $total,
	] );
} );

// CSS و JS صفحه گزارش (فقط همین صفحه)
add_action( 'admin_enqueue_scripts', static function ( string $hook ): void {

	if ( ! str_ends_with( $hook, '_page_' . HODIMA_SEO_DISCOVER_PAGE ) ) {
		return;
	}

	$url = HODIMA_SEO_URL . '/core/discover/assets';
	$ver = static function ( string $file ): string {
		$path = __DIR__ . '/assets/' . $file;
		return is_file( $path ) ? (string) filemtime( $path ) : HODIMA_SEO_VERSION;
	};

	wp_enqueue_style( 'hodima-discover-report', $url . '/discover-report.css', [], $ver( 'discover-report.css' ) );
	wp_enqueue_script( 'hodima-discover-report', $url . '/discover-report.js', [], $ver( 'discover-report.js' ), [ 'in_footer' => true, 'strategy' => 'defer' ] );
	wp_add_inline_script( 'hodima-discover-report', 'window.hodimaDiscoverReport = ' . wp_json_encode( [
		'ajax'  => admin_url( 'admin-ajax.php' ),
		'nonce' => wp_create_nonce( 'hodima_discover_batch' ),
	] ) . ';', 'before' );
} );

/* =====================================================================
 * صفحه
 * ===================================================================== */

function hodima_seo_discover_render_page(): void {

	$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط انتخاب تب
	$tab  = in_array( $tab, [ 'opportunities', 'stats', 'settings' ], true ) ? $tab : 'report';
	$tabs = [
		'report'        => [ 'label' => 'آمادگی', 'url' => hodima_seo_discover_page_url(), 'icon' => 'dashicons-yes-alt' ],
		'opportunities' => [ 'label' => 'فرصت‌ها', 'url' => hodima_seo_discover_page_url( [ 'tab' => 'opportunities' ] ), 'icon' => 'dashicons-lightbulb' ],
		'stats'         => [ 'label' => 'آمار سرچ کنسول', 'url' => hodima_seo_discover_page_url( [ 'tab' => 'stats' ] ), 'icon' => 'dashicons-chart-area' ],
		'settings'      => [ 'label' => 'تنظیمات', 'url' => hodima_seo_discover_page_url( [ 'tab' => 'settings' ] ), 'icon' => 'dashicons-admin-generic' ],
	];
	?>
	<div class="wrap hd-wrap hodima-dr">
		<?php
		hodima_admin_header( [
			'title'       => 'گوگل دیسکاور',
			'description' => 'فید پیشنهادی گوگل در موبایل؛ برای مقاله‌ها پرورودی‌ترین مسیر گوگل. آمادگی همه صفحه‌ها، فرصت‌های بهتر شدن و آمار واقعی دیسکاور در یک جا.',
			'icon'        => 'dashicons-visibility',
			'tabs'        => $tabs,
			'current'     => $tab,
		] );

		match ( $tab ) {
			'stats'         => hodima_seo_discover_render_stats(),
			'settings'      => hodima_seo_discover_render_settings(),
			'opportunities' => hodima_seo_discover_render_opportunities(),
			default         => hodima_seo_discover_render_report(),
		};
		?>
	</div>
	<?php
}

/** کادر «در حال آماده‌سازی گزارش» با نوار پیشرفت (JS خودکار ادامه می‌دهد و صفحه را تازه می‌کند). */
function hodima_seo_discover_render_warming( int $done, int $total ): void {
	?>
	<div class="hd-callout hodima-dr-batch" data-hodima-dr-batch="warm" data-autostart data-reload>
		<p><strong>در حال بررسی صفحه‌ها:</strong> <span data-hodima-dr-count><?php echo esc_html( sprintf( '%1$s از %2$s', number_format_i18n( $done ), number_format_i18n( $total ) ) ); ?></span>. نتیجه هر صفحه ذخیره می‌شود و دفعه بعد گزارش فوری باز می‌شود.</p>
		<div class="hodima-dr-progress" role="progressbar" aria-label="بررسی صفحه‌ها" aria-valuemin="0" aria-valuemax="<?php echo (int) $total; ?>" aria-valuenow="<?php echo (int) $done; ?>"><span style="inline-size: <?php echo esc_attr( (string) ( $total ? round( 100 * $done / $total ) : 0 ) ); ?>%"></span></div>
		<p class="hodima-dr-batch__msg" data-hodima-dr-msg aria-live="polite"></p>
		<noscript><p><a class="button" href="<?php echo esc_url( hodima_seo_discover_page_url() ); ?>">ادامه بررسی</a></p></noscript>
	</div>
	<?php
}

/** تب «آمادگی». */
function hodima_seo_discover_render_report(): void {

	$data   = hodima_seo_discover_report_rows();
	$rows   = $data['rows'];
	$stats  = hodima_seo_discover_stats();
	$get    = static fn( string $key ): string => isset( $_GET[ $key ] ) ? sanitize_key( wp_unslash( $_GET[ $key ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط فیلتر نمایش
	$only   = '1' === $get( 'issues' );
	$types  = array_values( array_unique( array_column( $rows, 'type' ) ) );
	$type   = in_array( $get( 'type' ), $types, true ) ? $get( 'type' ) : '';
	$sorts  = [ 'date' => 'تاریخ', 'score' => 'آمادگی' ] + ( $stats['fetched'] ? [ 'impressions' => 'نمایش', 'clicks' => 'کلیک' ] : [] );
	$sort   = isset( $sorts[ $get( 'orderby' ) ] ) ? $get( 'orderby' ) : 'date';
	$ready  = count( array_filter( $rows, static fn( array $r ): bool => 0 === $r['error'] && 0 === $r['warn'] ) );
	$errors = count( array_filter( $rows, static fn( array $r ): bool => $r['error'] > 0 ) );
	$warns  = count( $rows ) - $ready - $errors;
	$metric = static fn( array $r, string $m ): int => (int) ( $stats['rows'][ $r['url_key'] ][ $m ] ?? 0 );

	if ( $data['pending'] > 0 ) {
		hodima_seo_discover_render_warming( $data['total'] - $data['pending'], $data['total'] );
	}

	$list = array_values( array_filter(
		$rows,
		static fn( array $r ): bool => ( '' === $type || $type === $r['type'] ) && ( ! $only || $r['error'] > 0 || $r['warn'] > 0 )
	) );

	match ( $sort ) {
		'score'       => usort( $list, static fn( array $a, array $b ): int => [ $b['error'], $b['warn'] ] <=> [ $a['error'], $a['warn'] ] ),
		'impressions',
		'clicks'      => usort( $list, static fn( array $a, array $b ): int => $metric( $b, $sort ) <=> $metric( $a, $sort ) ),
		default       => null, // همان ترتیب جدیدترین‌ها
	};

	$args  = array_filter( [ 'type' => $type, 'issues' => $only ? '1' : '', 'orderby' => 'date' !== $sort ? $sort : '' ] );
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
			<div class="hd-stat"><span class="hd-stat__label">کلیک دیسکاور (۲۸ روز)</span><span class="hd-stat__value"><?php echo esc_html( number_format_i18n( $stats['totals']['clicks'] ) ); ?></span></div>
			<div class="hd-stat"><span class="hd-stat__label">نمایش دیسکاور (۲۸ روز)</span><span class="hd-stat__value"><?php echo esc_html( number_format_i18n( $stats['totals']['impressions'] ) ); ?></span></div>
		<?php endif; ?>
	</div>

	<section class="hd-card">
		<div class="hd-card__head">
			<?php echo hodima_admin_icon( 'dashicons-list-view' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escape‌شده در تابع ?>
			<h2 class="hd-card__title">آمادگی نوشته‌ها، برگه‌ها، محصولات و دسته‌ها</h2>
			<p class="hd-card__desc">همان «آمادگی برای دیسکاور» کادر ویرایش: تصویر بزرگ و اختصاصی با متن جایگزین، برش‌ها، ایندکس، عنوان اختصاصی بدون طعمه کلیک، متن معرفی، خلاصه، عمق و تازگی مقاله، و معرفی نویسنده. <?php echo esc_html( number_format_i18n( HODIMA_SEO_DISCOVER_REPORT_LIMIT ) ); ?> صفحه آخر و دسته‌های محصول.</p>
		</div>
		<div class="hd-inline">
			<a class="button<?php echo '' === $type ? ' button-primary' : ''; ?>" href="<?php echo esc_url( hodima_seo_discover_page_url( array_diff_key( $args, [ 'type' => 1 ] ) ) ); ?>">همه</a>
			<?php foreach ( $types as $t ) : ?>
				<a class="button<?php echo $t === $type ? ' button-primary' : ''; ?>" href="<?php echo esc_url( hodima_seo_discover_page_url( [ 'type' => $t ] + $args ) ); ?>"><?php echo esc_html( hodima_seo_discover_type_label( $t ) ); ?></a>
			<?php endforeach; ?>
			<a class="button<?php echo $only ? ' button-primary' : ''; ?>" href="<?php echo esc_url( hodima_seo_discover_page_url( array_filter( [ 'issues' => $only ? '' : '1' ] + $args ) ) ); ?>" aria-pressed="<?php echo $only ? 'true' : 'false'; ?>"><?php echo hodima_admin_icon( 'dashicons-warning' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escape‌شده در تابع ?> فقط نیازمند توجه</a>
		</div>
		<p class="hd-inline hodima-dr-sort">
			<span class="hd-muted">مرتب‌سازی:</span>
			<?php foreach ( $sorts as $key => $label ) : ?>
				<?php if ( $key === $sort ) : ?>
					<strong aria-current="true"><?php echo esc_html( $label ); ?></strong>
				<?php else : ?>
					<a href="<?php echo esc_url( hodima_seo_discover_page_url( array_filter( [ 'orderby' => 'date' !== $key ? $key : '' ] + $args ) ) ); ?>"><?php echo esc_html( $label ); ?></a>
				<?php endif; ?>
			<?php endforeach; ?>
		</p>

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
								<th scope="col">دیسکاور (کلیک / نمایش)</th>
							<?php endif; ?>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $list as $row ) : ?>
							<?php [ $class, $label ] = hodima_seo_discover_row_state( $row['error'], $row['warn'] ); ?>
							<tr>
								<td>
									<strong><a href="<?php echo esc_url( $row['edit'] ); ?>"><?php echo esc_html( '' !== $row['title'] ? $row['title'] : '(بدون عنوان)' ); ?></a></strong>
									<div class="hd-muted"><?php echo esc_html( hodima_seo_discover_type_label( $row['type'] ) . ( '' !== $row['date'] ? ' · ' . $row['date'] : '' ) ); ?></div>
								</td>
								<td><span class="hd-pill <?php echo esc_attr( $class ); ?>"><?php echo esc_html( sprintf( '%1$s · %2$s از %3$s', $label, number_format_i18n( $row['ok'] ), number_format_i18n( $row['total'] ) ) ); ?></span></td>
								<td><?php hodima_seo_discover_render_issue_pills( $row['issues'] ); ?></td>
								<?php if ( $stats['fetched'] ) : ?>
									<td dir="ltr"><?php echo esc_html( number_format_i18n( $metric( $row, 'clicks' ) ) . ' / ' . number_format_i18n( $metric( $row, 'impressions' ) ) ); ?></td>
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

		<?php if ( $data['skipped'] ) : ?>
			<details class="hodima-dr-skipped">
				<summary><?php echo esc_html( sprintf( '%s صفحه کنار گذاشته شد (برای گوگل دیسکاور نیستند)', number_format_i18n( count( $data['skipped'] ) ) ) ); ?></summary>
				<p class="hd-muted">این صفحه‌ها در شمارش، «فرصت‌ها» و ستون فهرست‌ها نمی‌آیند. صفحه‌ای که اشتباه اینجاست: در کادر دیسکاور همان صفحه، تیک «این صفحه برای گوگل دیسکاور نیست» را بردارید یا noindex آن را در سئوباکس عوض کنید.</p>
				<div class="hd-table-wrap">
					<table class="widefat striped">
						<thead><tr><th scope="col">عنوان</th><th scope="col">دلیل</th></tr></thead>
						<tbody>
							<?php foreach ( $data['skipped'] as $item ) : ?>
								<?php
								$target  = $item['target'];
								$is_term = $target instanceof WP_Term;
								$edit    = (string) ( $is_term ? get_edit_term_link( (int) $target->term_id, $target->taxonomy ) : get_edit_post_link( (int) $target->ID, 'raw' ) );
								$name    = $is_term ? $target->name : (string) get_the_title( $target );
								?>
								<tr>
									<td>
										<strong><a href="<?php echo esc_url( $edit ); ?>"><?php echo esc_html( '' !== $name ? $name : '(بدون عنوان)' ); ?></a></strong>
										<div class="hd-muted"><?php echo esc_html( hodima_seo_discover_type_label( $is_term ? $target->taxonomy : $target->post_type ) ); ?></div>
									</td>
									<td><?php echo esc_html( hodima_seo_discover_skip_label( $item['reason'] ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</details>
		<?php endif; ?>
	</section>
	<?php
}

/**
 * تب «تنظیمات»: برش‌های تصویر دیسکاور (تا SEO 2.1.4 بالای تب «آمادگی» بود).
 * تعداد صفحه‌های بی‌برش از ردیف‌های کش‌شده (بدون ساختن ردیف تازه).
 */
function hodima_seo_discover_render_settings(): void {

	$rows   = hodima_seo_discover_report_rows( 0.0 )['rows'];
	$nocrop = count( array_filter( $rows, static fn( array $r ): bool => 'missing' === $r['crops'] ) );
	?>
	<section class="hd-card">
		<div class="hd-card__head">
			<?php echo hodima_admin_icon( 'dashicons-image-crop' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escape‌شده در تابع ?>
			<h2 class="hd-card__title">برش‌های تصویر دیسکاور</h2>
			<p class="hd-card__desc">
				برش‌های ۱۶:۹، ۴:۳ و ۱:۱ (عرض ۱۲۰۰) در og:image و اسکیما استفاده می‌شوند و هنگام ذخیره هر صفحه ساخته می‌شوند. صفحه‌هایی که از قبل بوده‌اند و دوباره ذخیره نشده‌اند برش ندارند.
				<?php if ( $nocrop ) : ?>
					<strong><?php echo esc_html( sprintf( '%s صفحه از صفحه‌های گزارش هنوز برش ندارد.', number_format_i18n( $nocrop ) ) ); ?></strong>
				<?php endif; ?>
			</p>
		</div>
		<div class="hodima-dr-batch" data-hodima-dr-batch="crops">
			<div class="hodima-dr-progress" role="progressbar" aria-label="ساخت برش‌ها" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" hidden><span></span></div>
			<p class="hodima-dr-batch__msg" data-hodima-dr-msg aria-live="polite"></p>
			<div class="hd-actions">
				<button type="button" class="button button-primary" data-hodima-dr-start><?php echo hodima_admin_icon( 'dashicons-image-crop' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escape‌شده در تابع ?> ساخت برش برای همه صفحه‌ها</button>
			</div>
		</div>
	</section>

	<?php
}

/**
 * برچسب‌های موارد نیازمند توجه یک ردیف (توضیح در title).
 *
 * @param list<array{status: string, label: string, detail: string}> $issues
 */
function hodima_seo_discover_render_issue_pills( array $issues ): void {
	foreach ( $issues as $issue ) {
		printf(
			'<span class="hd-pill %1$s" title="%2$s">%3$s</span> ',
			esc_attr( 'error' === $issue['status'] ? 'hd-pill--error' : 'hd-pill--warn' ),
			esc_attr( $issue['detail'] ),
			esc_html( $issue['label'] )
		);
	}
}

/** تب «فرصت‌ها». */
function hodima_seo_discover_render_opportunities(): void {

	$data  = hodima_seo_discover_report_rows();
	$stats = hodima_seo_discover_stats();
	$opp   = hodima_seo_discover_opportunities( $data['rows'], $stats );
	$pct   = static fn( float $v ): string => number_format_i18n( 100 * $v, 1 ) . '٪';
	$num   = static fn( int $v ): string => number_format_i18n( $v );

	if ( $data['pending'] > 0 ) {
		hodima_seo_discover_render_warming( $data['total'] - $data['pending'], $data['total'] );
	}

	if ( ! $stats['fetched'] ) {
		?>
		<p class="hd-callout hd-callout--warning">بیشتر فرصت‌ها از آمار واقعی سرچ کنسول ساخته می‌شوند که هنوز گرفته نشده است. از تب «آمار سرچ کنسول» اتصال را تنظیم کنید؛ تا آن موقع فقط «مقاله تازه بدون نمایش» کامل نیست.</p>
		<?php
	}

	$groups = [
		'low_ctr'    => [
			'icon'  => 'dashicons-visibility',
			'title' => 'دیده می‌شود ولی کم کلیک می‌خورد',
			'desc'  => sprintf( 'دست‌کم %1$s نمایش و نرخ کلیک کمتر از %2$s (۶۰٪ میانگین سایت، %3$s). کارت را جذاب‌تر کنید: عنوان دیسکاور صادق ولی گیرا، و تصویر بزرگ روشن و مرتبط. «کلیک از دست رفته» تخمین فاصله با میانگین سایت است.', $num( HODIMA_SEO_DISCOVER_OPP_MIN_IMPRESSIONS ), $pct( $opp['site_ctr'] * HODIMA_SEO_DISCOVER_OPP_CTR_RATIO ), $pct( $opp['site_ctr'] ) ),
		],
		'dropping'   => [
			'icon'  => 'dashicons-arrow-down-alt',
			'title' => 'افت نمایش',
			'desc'  => 'نمایش ۲۸ روز آخر نصف ۲۸ روز قبل یا کمتر. دیسکاور به تازگی حساس است؛ اطلاعات مطلب را به‌روز کنید، چیز تازه اضافه کنید و دوباره منتشر کنید.',
		],
		'not_ready'  => [
			'icon'  => 'dashicons-warning',
			'title' => 'در دیسکاور هست ولی آماده نیست',
			'desc'  => 'گوگل این صفحه‌ها را انتخاب کرده؛ رفع موارد آمادگی (مثلا تصویر بزرگ و برش‌ها) کارت را بزرگ‌تر و نمایش را بیشتر می‌کند. اول پرنمایش‌ترها.',
		],
		'new_unseen' => [
			'icon'  => 'dashicons-clock',
			'title' => 'مقاله تازه، هنوز دیده نشده',
			'desc'  => 'مقاله‌های ۳ تا ۳۰ روز اخیر که در دیسکاور نمایش نداشته‌اند و مورد آمادگی دارند. دیسکاور بیشتر مطالب تازه را نشان می‌دهد؛ تا مقاله تازه است، موارد را رفع کنید.',
		],
	];

	foreach ( $groups as $key => $group ) {
		$items = $opp[ $key ];
		?>
		<section class="hd-card">
			<div class="hd-card__head">
				<?php echo hodima_admin_icon( $group['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escape‌شده در تابع ?>
				<h2 class="hd-card__title"><?php echo esc_html( $group['title'] ); ?> <span class="hd-pill"><?php echo esc_html( $num( count( $items ) ) ); ?></span></h2>
				<p class="hd-card__desc"><?php echo esc_html( $group['desc'] ); ?></p>
			</div>
			<?php if ( ! $items ) : ?>
				<p class="hd-empty"><?php echo $stats['fetched'] || 'new_unseen' === $key ? 'موردی نیست.' : 'بدون آمار سرچ کنسول قابل محاسبه نیست.'; ?></p>
			<?php else : ?>
				<div class="hd-table-wrap">
					<table class="widefat striped">
						<thead>
							<tr>
								<th scope="col">صفحه</th>
								<?php if ( 'new_unseen' !== $key ) : ?>
									<th scope="col">نمایش</th>
									<th scope="col">کلیک</th>
									<th scope="col">نرخ کلیک</th>
								<?php endif; ?>
								<?php if ( 'low_ctr' === $key ) : ?>
									<th scope="col">کلیک از دست رفته</th>
								<?php elseif ( 'dropping' === $key ) : ?>
									<th scope="col">۲۸ روز قبل</th>
								<?php else : ?>
									<th scope="col">موارد آمادگی</th>
								<?php endif; ?>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $items as $item ) : ?>
								<tr>
									<td>
										<strong><a href="<?php echo esc_url( $item['edit'] ); ?>"><?php echo esc_html( '' !== $item['title'] ? $item['title'] : '(بدون عنوان)' ); ?></a></strong>
										<div class="hd-muted"><?php echo esc_html( hodima_seo_discover_type_label( $item['type'] ) . ( '' !== $item['date'] ? ' · ' . $item['date'] : '' ) ); ?></div>
									</td>
									<?php if ( 'new_unseen' !== $key ) : ?>
										<td><?php echo esc_html( $num( $item['impressions'] ) ); ?></td>
										<td><?php echo esc_html( $num( $item['clicks'] ) ); ?></td>
										<td><?php echo esc_html( $pct( $item['ctr'] ) ); ?></td>
									<?php endif; ?>
									<?php if ( 'low_ctr' === $key ) : ?>
										<td><?php echo esc_html( '≈ ' . $num( $item['lost'] ) ); ?></td>
									<?php elseif ( 'dropping' === $key ) : ?>
										<td><?php echo esc_html( $num( (int) $item['prev_impressions'] ) . ' ← ' . hodima_seo_discover_change_text( hodima_seo_discover_change( $item['impressions'], $item['prev_impressions'] ) ) ); ?></td>
									<?php else : ?>
										<td><?php hodima_seo_discover_render_issue_pills( $item['issues'] ); ?></td>
									<?php endif; ?>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</section>
		<?php
	}
}

/** کاشی آمار با تغییر نسبت به ۲۸ روز قبل (جهت با ▲/▼ نوشته می‌شود، نه فقط رنگ). */
function hodima_seo_discover_render_stat_tile( string $label, string $value, ?float $change = null ): void {
	$level = null === $change || abs( $change ) < 0.5 ? '' : ( $change > 0 ? ' is-up' : ' is-down' );
	?>
	<div class="hd-stat">
		<span class="hd-stat__label"><?php echo esc_html( $label ); ?></span>
		<span class="hd-stat__value"><?php echo esc_html( $value ); ?></span>
		<?php if ( null !== $change ) : ?>
			<span class="hodima-dr-change<?php echo esc_attr( $level ); ?>"><?php echo esc_html( hodima_seo_discover_change_text( $change ) ); ?> <span class="hd-muted">نسبت به ۲۸ روز قبل</span></span>
		<?php endif; ?>
	</div>
	<?php
}

/** تب «آمار سرچ کنسول». */
function hodima_seo_discover_render_stats(): void {

	$stats    = hodima_seo_discover_stats();
	$email    = hodima_seo_discover_sc_email();
	$has_key  = '' !== hodima_seo_discover_sc_key_json();
	$source   = hodima_seo_discover_sc_key_source();
	$can_edit = current_user_can( 'manage_options' );
	$has_prev = '' !== $stats['prev']['start'];
	$now      = $stats['totals'];
	$before   = $stats['prev']['totals'];
	$ctr      = static fn( array $t ): ?float => $t['impressions'] ? $t['clicks'] / $t['impressions'] : null;
	$daily    = hodima_seo_discover_daily_filled( $stats['daily'] );

	if ( '' !== $stats['error'] && function_exists( 'hodima_admin_notice' ) ) {
		hodima_admin_notice( $stats['error'], 'warning' );
	}

	$top = $stats['rows'];
	uasort( $top, static fn( array $a, array $b ): int => $b['impressions'] <=> $a['impressions'] );
	$top = array_slice( $top, 0, 50, true );
	?>
	<?php if ( $stats['fetched'] ) : ?>
		<div class="hd-grid hd-grid--stats">
			<?php
			hodima_seo_discover_render_stat_tile( 'کلیک دیسکاور', number_format_i18n( $now['clicks'] ), $has_prev ? hodima_seo_discover_change( $now['clicks'], $before['clicks'] ) : null );
			hodima_seo_discover_render_stat_tile( 'نمایش دیسکاور', number_format_i18n( $now['impressions'] ), $has_prev ? hodima_seo_discover_change( $now['impressions'], $before['impressions'] ) : null );
			$ctr_now  = $ctr( $now );
			$ctr_prev = $ctr( $before );
			hodima_seo_discover_render_stat_tile(
				'نرخ کلیک',
				null !== $ctr_now ? number_format_i18n( 100 * $ctr_now, 1 ) . '٪' : '—',
				$has_prev && null !== $ctr_now && null !== $ctr_prev && $ctr_prev > 0 ? 100 * ( $ctr_now - $ctr_prev ) / $ctr_prev : null
			);
			hodima_seo_discover_render_stat_tile( 'صفحه‌های دیده‌شده', number_format_i18n( count( $stats['rows'] ) ), $has_prev ? hodima_seo_discover_change( count( $stats['rows'] ), count( $stats['prev']['rows'] ) ) : null );
			?>
		</div>
	<?php endif; ?>

	<?php if ( count( $daily ) > 1 ) : ?>
		<section class="hd-card">
			<div class="hd-card__head">
				<?php echo hodima_admin_icon( 'dashicons-chart-area' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escape‌شده در تابع ?>
				<h2 class="hd-card__title">روند روزانه</h2>
				<p class="hd-card__desc">کل سایت در دیسکاور، روز به روز (وقت اقیانوس آرام، مثل خود سرچ کنسول). نمایش و کلیک دو نمودار جدا با محور خودشان‌اند.</p>
			</div>
			<div class="hodima-dr-charts">
				<?php
				echo hodima_seo_discover_chart( $daily, 'impressions', 'نمایش' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escape‌شده در تابع
				echo hodima_seo_discover_chart( $daily, 'clicks', 'کلیک' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escape‌شده در تابع
				?>
			</div>
			<details class="hodima-dr-daily">
				<summary>جدول روزانه</summary>
				<div class="hd-table-wrap">
					<table class="widefat striped">
						<thead><tr><th scope="col">روز</th><th scope="col">نمایش</th><th scope="col">کلیک</th></tr></thead>
						<tbody>
							<?php foreach ( array_reverse( $daily, true ) as $day => $row ) : ?>
								<tr>
									<td><?php echo esc_html( (string) ( wp_date( 'l j F Y', (int) strtotime( $day . ' 12:00:00 UTC' ) ) ?: $day ) ); ?></td>
									<td><?php echo esc_html( number_format_i18n( $row['impressions'] ) ); ?></td>
									<td><?php echo esc_html( number_format_i18n( $row['clicks'] ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</details>
		</section>
	<?php endif; ?>

	<section class="hd-card">
		<div class="hd-card__head">
			<?php echo hodima_admin_icon( 'dashicons-chart-bar' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escape‌شده در تابع ?>
			<h2 class="hd-card__title">پرنمایش‌ترین صفحه‌ها در دیسکاور</h2>
			<p class="hd-card__desc">
				<?php if ( $stats['fetched'] ) : ?>
					<?php echo esc_html( sprintf( 'از %1$s تا %2$s (داده دیسکاور با دو روز تاخیر می‌رسد) — property: %3$s — آخرین دریافت: %4$s', $stats['start'], $stats['end'], $stats['property'], wp_date( 'Y/m/d H:i', $stats['fetched'] ) ) ); ?>
				<?php else : ?>
					هنوز آماری گرفته نشده است. اگر سایت هنوز در دیسکاور نمایش نداشته، سرچ کنسول هم داده‌ای ندارد.
				<?php endif; ?>
			</p>
		</div>
		<?php if ( $top ) : ?>
			<div class="hd-table-wrap">
				<table class="widefat striped">
					<thead>
						<tr>
							<th scope="col">صفحه</th>
							<th scope="col">کلیک</th>
							<th scope="col">نمایش</th>
							<th scope="col">نرخ کلیک</th>
							<?php if ( $has_prev ) : ?>
								<th scope="col">نمایش نسبت به ۲۸ روز قبل</th>
							<?php endif; ?>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $top as $path => $row ) : ?>
							<?php $prev_imp = $stats['prev']['rows'][ $path ]['impressions'] ?? 0; ?>
							<tr>
								<td dir="ltr"><a href="<?php echo esc_url( home_url( (string) $path ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( (string) $path ); ?></a></td>
								<td><?php echo esc_html( number_format_i18n( $row['clicks'] ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $row['impressions'] ) ); ?></td>
								<td><?php echo esc_html( $row['impressions'] ? number_format_i18n( 100 * $row['clicks'] / $row['impressions'], 1 ) . '٪' : '—' ); ?></td>
								<?php if ( $has_prev ) : ?>
									<td><?php echo esc_html( 0 === $prev_imp ? 'تازه' : hodima_seo_discover_change_text( hodima_seo_discover_change( $row['impressions'], $prev_imp ) ) ); ?></td>
								<?php endif; ?>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php elseif ( $stats['fetched'] ) : ?>
			<p class="hd-empty">در این بازه هیچ صفحه‌ای از سایت در دیسکاور نمایش داده نشده است.</p>
		<?php endif; ?>
	</section>

	<section class="hd-card">
		<div class="hd-card__head">
			<?php echo hodima_admin_icon( 'dashicons-admin-network' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escape‌شده در تابع ?>
			<h2 class="hd-card__title">اتصال به سرچ کنسول</h2>
			<p class="hd-card__desc">با یک حساب سرویس گوگل (Service Account) و فقط دسترسی خواندنی. آمار روزی یک بار خودکار به‌روز می‌شود.</p>
		</div>
		<?php if ( ! $has_key ) : ?>
			<p class="hd-callout hd-callout--warning">کلید حساب سرویس تنظیم نشده است. فایل JSON حساب سرویس را پایین بچسبانید (اگر ماژول «Google Indexing API» کلید داشته باشد، همان خودکار استفاده می‌شود).</p>
		<?php else : ?>
			<div class="hd-callout">
				<div>
					<p><strong>حساب فعلی:</strong> <code dir="ltr"><?php echo esc_html( $email ); ?></code>
						<span class="hd-pill"><?php echo esc_html( 'own' === $source ? 'کلید جدای دیسکاور' : 'کلید ماژول Google Indexing' ); ?></span></p>
					<p class="hd-muted">همین ایمیل باید در سرچ کنسول ← تنظیمات ← کاربران و مجوزها، کاربر همین property باشد. ایمیل جزئی از خود کلید است؛ برای عوض کردن حساب (و ایمیل)، فایل JSON حساب تازه را پایین بچسبانید.</p>
				</div>
			</div>
		<?php endif; ?>
		<?php if ( $can_edit ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="hodima_discover_sc">
				<?php wp_nonce_field( 'hodima_discover_sc' ); ?>
				<div class="hd-fields">
					<div class="hd-field hd-field--wide">
						<label class="hd-field__label" for="hodima-discover-key"><?php echo esc_html( $has_key ? 'کلید حساب سرویس تازه (فایل JSON)' : 'کلید حساب سرویس (فایل JSON)' ); ?></label>
						<textarea id="hodima-discover-key" name="key_json" rows="5" dir="ltr" autocomplete="off" spellcheck="false" placeholder='{"type": "service_account", "client_email": "…", "private_key": "…"}'></textarea>
						<p class="hd-field__help">کل محتوای فایل JSON که از Google Cloud ← IAM ← حساب‌های سرویس ← کلیدها دانلود کرده‌اید. خالی بماند = کلید فعلی عوض نمی‌شود. این کلید فقط برای خواندن آمار دیسکاور است و ماژول Google Indexing را عوض نمی‌کند.</p>
					</div>
					<div class="hd-field hd-field--wide">
						<label class="hd-field__label" for="hodima-discover-property">property در سرچ کنسول</label>
						<input type="text" id="hodima-discover-property" name="property" dir="ltr" value="<?php echo esc_attr( hodima_seo_discover_sc_property() ); ?>" placeholder="<?php echo esc_attr( implode( '  یا  ', hodima_seo_discover_sc_candidates() ) ); ?>">
						<p class="hd-field__help">خالی = اول آدرس سایت و بعد دامنه (sc-domain) خودکار امتحان می‌شود.</p>
					</div>
				</div>
				<div class="hd-actions">
					<button type="submit" class="button button-primary"><?php echo hodima_admin_icon( 'dashicons-update' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escape‌شده در تابع ?> ذخیره و به‌روزرسانی آمار</button>
					<?php if ( 'own' === $source ) : ?>
						<button type="submit" class="button" name="remove_key" value="1"><?php echo hodima_admin_icon( 'dashicons-trash' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escape‌شده در تابع ?> حذف کلید جدا (برگشت به کلید ماژول Google Indexing)</button>
					<?php endif; ?>
				</div>
			</form>
		<?php endif; ?>
	</section>
	<?php
}

/* =====================================================================
 * ستون «دیسکاور» در فهرست نوشته‌ها، برگه‌ها، محصولات و دسته‌ها
 * ===================================================================== */

/** حداکثر زمان ساختن ردیف‌های تازه در یک صفحه فهرست (ثانیه). */
const HODIMA_SEO_DISCOVER_COLUMN_BUDGET = 1.0;

/**
 * نشان آمادگی یک شیء برای ستون فهرست‌ها (از کش ردیف همان صفحه).
 *
 * ردیف‌های کهنه فقط تا HODIMA_SEO_DISCOVER_COLUMN_BUDGET ثانیه در هر صفحه
 * فهرست ساخته می‌شوند؛ بقیه «هنوز بررسی نشده» (با بارگذاری بعدی یا صفحه گزارش
 * ساخته می‌شوند). باگ قبلی (تا SEO 2.1.4): بعد از هر تغییر سراسری (مثلا ذخیره
 * تنظیمات قالب) فهرست ۱۰۰ محصولی همه ردیف‌ها را همان لحظه می‌ساخت و کند باز می‌شد.
 */
function hodima_seo_discover_column_html( WP_Post|WP_Term $target ): string {

	static $start = null;
	$start      ??= microtime( true );

	$dot = static fn( string $icon, string $state, string $title ): string => sprintf(
		'<span class="dashicons %1$s hodima-dc-dot %2$s" title="%3$s" aria-hidden="true"></span><span class="screen-reader-text">%3$s</span>',
		esc_attr( $icon ),
		esc_attr( $state ),
		esc_attr( $title )
	);

	$skip = hodima_seo_discover_skip_reason( $target );
	if ( '' !== $skip ) {
		return $dot( 'dashicons-hidden', 'is-skip', 'دیسکاور: کنار گذاشته — ' . hodima_seo_discover_skip_label( $skip ) );
	}

	$row = hodima_seo_discover_row( $target, microtime( true ) - $start < HODIMA_SEO_DISCOVER_COLUMN_BUDGET );

	if ( null === $row ) {
		return $dot( 'dashicons-clock', 'is-skip', 'دیسکاور: هنوز بررسی نشده (با بارگذاری دوباره همین صفحه یا گزارش گوگل دیسکاور ساخته می‌شود)' );
	}

	$icon = match ( true ) {
		$row['error'] > 0 => [ 'dashicons-dismiss', 'is-error', 'دیسکاور: مشکل دارد' ],
		$row['warn'] > 0  => [ 'dashicons-warning', 'is-warn', 'دیسکاور: قابل بهتر شدن' ],
		default           => [ 'dashicons-yes-alt', 'is-ok', 'دیسکاور: آماده' ],
	};
	$title = sprintf( '%1$s (%2$s از %3$s)', $icon[2], number_format_i18n( $row['ok'] ), number_format_i18n( $row['total'] ) )
		. ( $row['issues'] ? ' — ' . implode( ' · ', array_column( $row['issues'], 'label' ) ) : '' );

	return $dot( $icon[0], $icon[1], $title );
}

add_action( 'admin_init', static function (): void {

	$heading = '<span class="dashicons dashicons-visibility" aria-hidden="true"></span><span class="screen-reader-text">دیسکاور</span>';

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
	wp_add_inline_style( 'hodima-discover-list', '.column-hodima_discover{inline-size:2.5rem}.column-hodima_discover .hodima-dc-dot.is-ok{color:#1f7a4d}.column-hodima_discover .hodima-dc-dot.is-warn{color:#9a5b00}.column-hodima_discover .hodima-dc-dot.is-error{color:#b3261e}.column-hodima_discover .hodima-dc-dot.is-skip{color:#5d6785}' );
} );
