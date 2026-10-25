<?php
/**
 * ماژول «مشاور سئو» — کادر داخل ویرایش صفحه
 * Path: core/advisor/advisor-box.php
 *
 * کادر «مشاور سئو» کنار سئوباکس در ویرایش نوشته/برگه/محصول (کادر عادی) و دسته (postbox بعد
 * از جدول فرم، پس از «سئو»، «خوشه‌بندی» و «لینک‌های مرتبط»): پیشنهادهای همین آدرس با متن
 * تازه صفحه و آمار گوگل (hodima_seo_advisor_analyze، نه نسخه ذخیره‌شده فهرست) + عدد و کلمه‌های
 * برتر. فقط خواندن؛ هیچ فیلدی ندارد و ذخیره صفحه را عوض نمی‌کند. فقط برای صفحه منتشرشده و
 * مدیر. طرح تأییدشده: Artifact «طرح مشاور سئو» (HODIMA-AUDIT.md بخش ۲۵۶). کادر داخل فرم خود
 * وردپرس است، پس «بازگرداندن» لینک GET با nonce است، نه فرم تودرتو.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

add_action( 'add_meta_boxes', 'hodima_seo_advisor_register_box', 20, 2 );
add_action( 'admin_enqueue_scripts', 'hodima_seo_advisor_box_assets' );
add_action( 'init', static function (): void {
	foreach ( hodima_seo_advisor_box_taxonomies() as $taxonomy ) {
		add_action( "{$taxonomy}_edit_form", 'hodima_seo_advisor_render_term_box', 10, 1 ); // بعد از سئو (۷)، خوشه‌بندی (۸)، لینک‌های مرتبط (۹)
	}
}, 25 );

/**
 * نوع‌های نوشته‌ای که کادر دارند: همان نوع‌های سئوباکس.
 *
 * @return list<string>
 */
function hodima_seo_advisor_box_post_types(): array {
	return hodima_seo_advisor_box_post_types_all();
}

/**
 * تاکسونومی‌هایی که کادر دارند: همان تاکسونومی‌های سئوباکس.
 *
 * @return list<string>
 */
function hodima_seo_advisor_box_taxonomies(): array {
	$taxonomies = function_exists( 'seobox_taxonomies' ) ? seobox_taxonomies() : [ 'category', 'product_cat' ];
	return array_values( array_filter( (array) $taxonomies, static fn( mixed $t ): bool => is_string( $t ) && taxonomy_exists( $t ) ) );
}

function hodima_seo_advisor_register_box( string $post_type, mixed $post = null ): void {

	if ( ! current_user_can( 'manage_options' ) || ! $post instanceof WP_Post || 'publish' !== $post->post_status || ! in_array( $post_type, hodima_seo_advisor_box_post_types(), true ) ) {
		return; // پیش‌نویس هنوز در گوگل نیست
	}

	add_meta_box( 'hodima_advisor_box', 'مشاور سئو', 'hodima_seo_advisor_render_post_box', $post_type, 'normal', 'default' );
}

function hodima_seo_advisor_box_assets( string $hook ): void {

	if ( ! in_array( $hook, [ 'post.php', 'term.php' ], true ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$file = HODIMA_SEO_DIR . '/core/advisor/advisor-box.css';
	hodima_seo_advisor_enqueue_steps_css();
	wp_enqueue_style( 'hodima-seo-advisor-box', HODIMA_SEO_URL . '/core/advisor/advisor-box.css', [], is_file( $file ) ? (string) filemtime( $file ) : HODIMA_SEO_VERSION );
}

function hodima_seo_advisor_render_post_box( WP_Post $post ): void {
	hodima_seo_advisor_render_box( (string) get_permalink( $post ) );
}

function hodima_seo_advisor_render_term_box( mixed $term ): void {

	if ( ! $term instanceof WP_Term || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$link = get_term_link( $term );
	?>
	<div class="postbox hodima-advisor-postbox">
		<div class="postbox-header"><h2 class="hndle">مشاور سئو</h2></div>
		<div class="inside"><?php hodima_seo_advisor_render_box( is_string( $link ) ? $link : '' ); ?></div>
	</div>
	<?php
}

/** محتوای کادر برای یک آدرس. */
function hodima_seo_advisor_render_box( string $url ): void {

	$path     = hodima_seo_advisor_path_key( $url );
	$analysis = null !== $path ? hodima_seo_advisor_analyze( $path ) : null;
	$status   = hodima_seo_advisor_status();
	$ignored  = null !== $path && in_array( $path, hodima_seo_advisor_ignored(), true );
	$n        = static fn( int|float $value, int $decimals = 0 ): string => number_format_i18n( $value, $decimals );
	$count    = null !== $analysis && ! $ignored ? count( $analysis['suggestions'] ) : 0;

	echo '<div class="hodima-advisor-box">';

	// نوار وضعیت
	printf(
		'<header class="hodima-advisor-box__head"><span class="hodima-advisor-box__icon dashicons dashicons-chart-line" aria-hidden="true"></span><div class="hodima-advisor-box__title"><strong>مشاور سئو</strong><small>%s</small></div>%s</header>',
		esc_html( $status['fetched'] > 0 ? sprintf( 'داده %1$s روز اخیر · آخرین دریافت %2$s پیش', $n( $status['days'] ), human_time_diff( $status['fetched'] ) ) : 'هنوز داده‌ای گرفته نشده' ),
		$ignored
			? '<span class="hodima-advisor-box__pill">نادیده گرفته شده</span>'
			: ( null === $analysis ? '' : ( $count > 0 ? '<span class="hodima-advisor-box__pill hodima-advisor-box__pill--warn">' . esc_html( $n( $count ) ) . ' پیشنهاد</span>' : '<span class="hodima-advisor-box__pill hodima-advisor-box__pill--ok">پیشنهادی نیست</span>' ) )
	);

	if ( null === $analysis ) {
		echo '<p class="hodima-advisor-box__empty">' . esc_html( $status['fetched'] > 0 ? 'برای این آدرس هنوز داده‌ای در سرچ کنسول ثبت نشده است (تازه منتشر شده یا نمایشی نداشته). بعد از دریافت داده بعدی دوباره ببینید.' : 'داده سرچ کنسول هنوز گرفته نشده است؛ در «ابزارهای هدیما ← مشاور سئو» دکمه «به‌روزرسانی داده» را بزنید.' ) . '</p></div>';
		return;
	}

	$row     = $analysis['row'];
	$queries = hodima_seo_advisor_queries( (string) $row['path'], 5 );
	$ctr     = $row['impressions'] > 0 ? $row['clicks'] / $row['impressions'] * 100 : 0.0;

	echo '<div class="hodima-advisor-box__body">';

	// ستون اصلی: پیشنهادها
	echo '<div class="hodima-advisor-box__main">';
	if ( $ignored ) {
		echo '<p class="hodima-advisor-box__empty">این آدرس در «مشاور سئو» نادیده گرفته شده و پیشنهادی نمی‌گیرد.</p>';
	} elseif ( ! $analysis['suggestions'] ) {
		echo '<p class="hodima-advisor-box__empty">با آمار این بازه پیشنهادی برای این صفحه نیست؛ نرخ کلیک و کلمه‌های اصلی در حد معمول‌اند.</p>';
	}

	$position = 0;
	// تحلیل زنده خودش نادیده‌گرفتن را نمی‌داند؛ آدرس نادیده‌گرفته‌شده پیشنهادی نشان نمی‌دهد
	foreach ( $ignored ? [] : $analysis['suggestions'] as $item ) {
		echo '<section class="hodima-advisor-box__item">';
		printf(
			'<h4>%1$s</h4><p>%2$s</p>%3$s',
			esc_html( (string) $item['title'] ),
			esc_html( (string) $item['detail'] ),
			(int) $item['potential'] > 0 || 'low_ctr' === $item['rule']
				? '<div class="hodima-advisor-box__meta">' . ( 'low_ctr' === $item['rule'] && function_exists( 'seobox_post_types' ) ? '<a class="button" href="#seobox_title">رفتن به عنوان سئو</a>' : '' ) . ( (int) $item['potential'] > 0 ? '<small>' . esc_html( sprintf( 'کلیک بالقوه ≈ %s', $n( (int) $item['potential'] ) ) ) . '</small>' : '' ) . '</div>'
				: ''
		);
		hodima_seo_advisor_render_steps( (array) ( $item['steps'] ?? [] ), 0 === $position++ );
		echo '</section>';
	}
	echo '</div>';

	// ستون کناری: عددها و کلمه‌های برتر
	echo '<aside class="hodima-advisor-box__aside" aria-label="داده این صفحه">';
	printf(
		'<ul class="hodima-advisor-box__metrics"><li><span>نمایش</span><strong>%1$s</strong></li><li><span>نرخ کلیک</span><strong>%2$s٪</strong></li><li><span>رتبه</span><strong>%3$s</strong></li></ul>',
		esc_html( $n( $row['impressions'] ) ),
		esc_html( $n( $ctr, 2 ) ),
		esc_html( $n( (float) $row['position'], 1 ) )
	);

	if ( $queries ) {
		echo '<h4>کلمه‌های برتر <small>(نمایش · نرخ کلیک · رتبه)</small></h4><ul class="hodima-advisor-box__queries">';
		foreach ( $queries as $query ) {
			printf(
				'<li><span>%1$s</span><small>%2$s</small></li>',
				esc_html( $query['term'] ),
				esc_html( sprintf( '%1$s · %2$s٪ · %3$s', $n( $query['impressions'] ), $n( $query['impressions'] > 0 ? $query['clicks'] / $query['impressions'] * 100 : 0, 2 ), $n( $query['position'], 1 ) ) )
			);
		}
		echo '</ul>';
	}

	printf( '<a class="hodima-advisor-box__link" href="%s">دیدن در صفحه مشاور سئو</a>', esc_url( hodima_seo_advisor_page_url( [ 'q' => (string) $row['path'] ] ) ) );

	if ( $ignored ) {
		printf(
			'<a class="hodima-advisor-box__link" href="%s">بازگرداندن به پیشنهادها</a>',
			esc_url( wp_nonce_url( add_query_arg( [ 'action' => 'hodima_advisor_ignore', 'path' => (string) $row['path'], 'restore' => 1 ], admin_url( 'admin-post.php' ) ), 'hodima_advisor_ignore' ) )
		);
	}

	echo '</aside></div></div>';
}
