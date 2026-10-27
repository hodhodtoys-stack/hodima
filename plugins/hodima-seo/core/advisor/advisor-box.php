<?php
/**
 * ماژول «دستار سئو» — کادر داخل ویرایش صفحه
 * Path: core/advisor/advisor-box.php
 *
 * کادر «دستار سئو» کنار سئوباکس در ویرایش نوشته/برگه/محصول (کادر عادی) و دسته (postbox بعد
 * از جدول فرم، پس از «سئو»، «خوشه‌بندی» و «لینک‌های مرتبط»): پیشنهادهای همین آدرس با متن
 * تازه صفحه و آمار گوگل (hodima_seo_advisor_analyze، نه نسخه ذخیره‌شده فهرست) + عدد و کلمه‌های
 * برتر. فقط خواندن؛ هیچ فیلدی ندارد و ذخیره صفحه را عوض نمی‌کند. فقط برای صفحه منتشرشده و
 * مدیر. طرح تأییدشده: Artifact «طرح دستار سئو» (HODIMA-AUDIT.md بخش ۲۵۶). کادر داخل فرم خود
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

	add_meta_box( 'hodima_advisor_box', 'دستار سئو', 'hodima_seo_advisor_render_post_box', $post_type, 'normal', 'default' );
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
		<div class="postbox-header"><h2 class="hndle">دستار سئو</h2></div>
		<div class="inside"><?php hodima_seo_advisor_render_box( is_string( $link ) ? $link : '' ); ?></div>
	</div>
	<?php
}

/**
 * برچسب کوتاه یک هفته (پایان هفته): «۲ آبان» با تقویم شمسی Core، وگرنه «Nov 2».
 */
function hodima_seo_advisor_week_label( string $date ): string {

	$time = strtotime( $date . ' 12:00:00 UTC' );
	if ( false === $time ) {
		return $date;
	}

	return function_exists( 'hodima_jalali_format' ) ? hodima_jalali_format( 'j F', $time ) : gmdate( 'M j', $time );
}

/**
 * کارت «روند کلیک هفتگی» + جمله «قبل/بعد از آخرین ویرایش» (طرح تأییدشده Artifact «طرح روند هفتگی
 * دستار سئو»). بدون عکس هفتگی یا بدون هیچ نمایش در این هفته‌ها چیزی نشان داده نمی‌شود.
 *
 * @param array<string, mixed> $row ردیف آدرس (hodima_seo_advisor_page_row)
 */
function hodima_seo_advisor_render_trend( array $row ): void {

	$weeks = hodima_seo_advisor_history( (string) $row['path'], HODIMA_SEO_ADVISOR_HISTORY_WEEKS );

	if ( count( $weeks ) < 2 || array_sum( array_column( $weeks, 'impressions' ) ) <= 0 ) {
		return;
	}

	$n        = static fn( int|float $value, int $decimals = 0 ): string => number_format_i18n( $value, $decimals );
	$modified = hodima_seo_advisor_object_modified( (string) $row['kind'], (int) $row['object_id'] );
	$max      = max( 1, ...array_column( $weeks, 'clicks' ) );

	echo '<div class="hodima-advisor-box__trend"><div class="hodima-advisor-box__trend-head"><h5>روند کلیک هفتگی</h5>';
	echo '<small>' . esc_html( sprintf( '%s هفته اخیر', $n( count( $weeks ) ) ) ) . '</small></div><ol class="hodima-advisor-box__bars">';

	foreach ( $weeks as $week ) {
		$after = '' !== $modified && ( new DateTimeImmutable( $week['week'] ) )->modify( '-6 days' )->format( 'Y-m-d' ) > $modified;
		printf(
			'<li%1$s><span class="hodima-advisor-box__bar-value">%2$s</span><span class="hodima-advisor-box__bar" style="--h:%3$d%%" aria-hidden="true"></span><span class="hodima-advisor-box__bar-label">%4$s</span></li>',
			$after ? ' class="is-after"' : '',
			esc_html( $n( $week['clicks'] ) ),
			(int) round( $week['clicks'] / $max * 100 ),
			esc_html( hodima_seo_advisor_week_label( $week['week'] ) )
		);
	}

	echo '</ol>';

	$compare = hodima_seo_advisor_before_after( $weeks, $modified );
	if ( null !== $compare ) {
		$vs_text = hodima_seo_advisor_effect_vs_site_text( hodima_seo_advisor_effect_vs_site( $compare ) );
		printf(
			'<p class="hodima-advisor-box__compare"><strong>%1$s</strong> %2$s</p>',
			esc_html( sprintf( 'بعد از آخرین ویرایش (%s):', hodima_seo_advisor_week_label( $compare['modified'] ) ) ),
			esc_html(
				sprintf(
					'میانگین کلیک هفتگی از %1$s به %2$s %3$s (%4$s هفته قبل ← %5$s هفته بعد)؛ رتبه از %6$s به %7$s.',
					$n( $compare['before']['clicks'], 1 ),
					$n( $compare['after']['clicks'], 1 ),
					$compare['after']['clicks'] >= $compare['before']['clicks'] ? 'رسیده' : 'افتاده',
					$n( $compare['before']['weeks'] ),
					$n( $compare['after']['weeks'] ),
					$n( $compare['before']['position'], 1 ),
					$n( $compare['after']['position'], 1 )
				) . ( '' !== $vs_text ? ' ' . $vs_text : '' )
			)
		);
	}

	echo '<p class="hodima-advisor-box__trend-note">' . esc_html( 'هفته‌ها دوشنبه تا یکشنبه‌اند. تغییر کلیک می‌تواند از فصل یا ویرایش دیگری هم باشد؛ این فقط مقایسه است.' ) . '</p></div>';
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
		'<header class="hodima-advisor-box__head"><span class="hodima-advisor-box__icon dashicons dashicons-chart-line" aria-hidden="true"></span><div class="hodima-advisor-box__title"><strong>دستار سئو</strong><small>%s</small></div>%s</header>',
		esc_html( $status['fetched'] > 0 ? sprintf( 'داده %1$s روز اخیر · آخرین دریافت %2$s پیش', $n( $status['days'] ), human_time_diff( $status['fetched'] ) ) : 'هنوز داده‌ای گرفته نشده' ),
		$ignored
			? '<span class="hodima-advisor-box__pill">نادیده گرفته شده</span>'
			: ( null === $analysis ? '' : ( $count > 0 ? '<span class="hodima-advisor-box__pill hodima-advisor-box__pill--warn">' . esc_html( $n( $count ) ) . ' پیشنهاد</span>' : '<span class="hodima-advisor-box__pill hodima-advisor-box__pill--ok">پیشنهادی نیست</span>' ) )
	);

	if ( null === $analysis ) {
		echo '<p class="hodima-advisor-box__empty">' . esc_html( $status['fetched'] > 0 ? 'برای این آدرس هنوز داده‌ای در سرچ کنسول ثبت نشده است (تازه منتشر شده یا نمایشی نداشته). بعد از دریافت داده بعدی دوباره ببینید.' : 'داده سرچ کنسول هنوز گرفته نشده است؛ در «ابزارهای هدیما ← دستار سئو» دکمه «به‌روزرسانی همه» را بزنید.' ) . '</p></div>';
		return;
	}

	$row     = $analysis['row'];
	$queries = hodima_seo_advisor_queries( (string) $row['path'], 5 );
	$ctr     = $row['impressions'] > 0 ? $row['clicks'] / $row['impressions'] * 100 : 0.0;

	// پنل داده: سه عدد هم‌اندازه با همان ظاهر کادر «عنوان سئو و توضیح متا» (برچسب بالا، مقدار در کادر) + جدول کلمه‌ها
	echo '<section class="hodima-advisor-box__data" aria-label="داده گوگل برای این صفحه"><h4>داده گوگل برای این صفحه</h4>';
	printf(
		'<div class="hodima-advisor-box__metrics"><div><span class="hodima-advisor-box__label">نمایش <small>(%1$s روز)</small></span><div class="hodima-advisor-box__value">%2$s</div></div><div><span class="hodima-advisor-box__label">نرخ کلیک</span><div class="hodima-advisor-box__value">%3$s٪</div></div><div><span class="hodima-advisor-box__label">رتبه</span><div class="hodima-advisor-box__value">%4$s</div></div></div>',
		esc_html( $n( $status['days'] ) ),
		esc_html( $n( $row['impressions'] ) ),
		esc_html( $n( $ctr, 2 ) ),
		esc_html( $n( (float) $row['position'], 1 ) )
	);

	hodima_seo_advisor_render_trend( $row );

	if ( $queries ) {
		echo '<div><span class="hodima-advisor-box__label">کلمه‌های برتر</span><table class="hodima-advisor-box__table"><thead><tr><th>کلمه</th><th>نمایش</th><th>نرخ کلیک</th><th>رتبه</th></tr></thead><tbody>';
		foreach ( $queries as $query ) {
			printf(
				'<tr><td>%1$s</td><td>%2$s</td><td>%3$s٪</td><td>%4$s</td></tr>',
				esc_html( $query['term'] ),
				esc_html( $n( $query['impressions'] ) ),
				esc_html( $n( $query['impressions'] > 0 ? $query['clicks'] / $query['impressions'] * 100 : 0, 2 ) ),
				esc_html( $n( $query['position'], 1 ) )
			);
		}
		echo '</tbody></table></div>';
	}
	echo '</section>';

	// ایرادها و گام‌های رفع
	echo '<div class="hodima-advisor-box__main">';
	if ( $ignored ) {
		echo '<p class="hodima-advisor-box__empty">این آدرس در «دستار سئو» نادیده گرفته شده و پیشنهادی نمی‌گیرد.</p>';
	} elseif ( ! $analysis['suggestions'] ) {
		echo '<p class="hodima-advisor-box__empty">با آمار این بازه پیشنهادی برای این صفحه نیست؛ نرخ کلیک و کلمه‌های اصلی در حد معمول‌اند.</p>';
	}

	$position = 0;
	$has_box  = function_exists( 'seobox_post_types' );
	// تحلیل زنده خودش نادیده‌گرفتن را نمی‌داند؛ آدرس نادیده‌گرفته‌شده پیشنهادی نشان نمی‌دهد
	foreach ( $ignored ? [] : $analysis['suggestions'] as $item ) {
		$jump = 'low_ctr' === $item['rule'] && $has_box
			? '<a class="button" href="#seobox_title">' . esc_html( 'رفتن به عنوان سئو' ) . '</a><a class="button" href="#seobox_description">' . esc_html( 'رفتن به توضیح متا' ) . '</a>'
			: '';
		echo '<section class="hodima-advisor-box__item">';
		printf(
			'<h4>%1$s</h4><p>%2$s</p>%3$s',
			esc_html( (string) $item['title'] ),
			esc_html( (string) $item['detail'] ),
			'' !== $jump || (int) $item['potential'] > 0
				? '<div class="hodima-advisor-box__meta">' . wp_kses_post( $jump ) . ( (int) $item['potential'] > 0 ? '<small>' . esc_html( sprintf( 'کلیک بالقوه ≈ %s', $n( (int) $item['potential'] ) ) ) . '</small>' : '' ) . '</div>'
				: ''
		);
		hodima_seo_advisor_render_steps( (array) ( $item['steps'] ?? [] ), 0 === $position++ );
		if ( function_exists( 'hodima_seo_advisor_render_task_controls' ) ) {
			hodima_seo_advisor_render_task_controls( (string) $row['path'], $item );
		}
		echo '</section>';
	}
	echo '</div>';

	echo '<footer class="hodima-advisor-box__foot">';
	printf( '<a class="button" href="%s"><span class="dashicons dashicons-chart-line" aria-hidden="true"></span> دیدن در صفحه دستار سئو</a>', esc_url( hodima_seo_advisor_page_url( [ 'q' => (string) $row['path'] ] ) ) );

	if ( $ignored ) {
		printf(
			'<a class="button" href="%s"><span class="dashicons dashicons-undo" aria-hidden="true"></span> بازگرداندن به پیشنهادها</a>',
			esc_url( wp_nonce_url( add_query_arg( [ 'action' => 'hodima_advisor_ignore', 'path' => (string) $row['path'], 'restore' => 1 ], admin_url( 'admin-post.php' ) ), 'hodima_advisor_ignore' ) )
		);
	}

	echo '</footer></div>';
}
