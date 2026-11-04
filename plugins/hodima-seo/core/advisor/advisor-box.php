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

	$file = HODIMA_SEO_DIR . '/core/advisor/advisor-box.css'; // گام‌ها و دکمه کپی دیگر در کادر نیست (SEO 3.7.6)؛ فقط استایل خود کادر
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

/**
 * کارهای باز همین آدرس، از همان فهرست «کارها» (SEO 3.7.6؛ طرح تأییدشده «یک منبع»): کادر و دستار دیگر عدد و متن متفاوت نشان
 * نمی‌دهند. قبلا کادر با تحلیل زنده خودش فقط پیشنهادهای گوگل را می‌ساخت (بدون ایراد فنی، ایندکس و هم‌کلمه) و برای «از کجا
 * لینک بدهید» متن همه صفحه‌ها را با هر بار باز کردن ویرایش می‌گشت؛ آن جزئیات حالا در «کارها» با باز کردن ساخته می‌شود.
 *
 * @return list<array<string, mixed>>
 */
function hodima_seo_advisor_box_tasks( string $path ): array {

	if ( ! function_exists( 'hodima_seo_advisor_overview_build' ) ) {
		return [];
	}

	foreach ( hodima_seo_advisor_overview_build() as $page ) {
		if ( $path === (string) $page['path'] ) {
			return (array) $page['items'];
		}
	}

	return [];
}

/**
 * توضیح کوتاه کنار نام یک کار در کادر (کلمه پیشنهاد گوگل یا «ایراد فنی»).
 *
 * @param array<string, mixed> $item یک مورد hodima_seo_advisor_overview_build()
 */
function hodima_seo_advisor_box_task_note( array $item ): string {

	return match ( (string) ( $item['src'] ?? '' ) ) {
		'opp', 'comp' => '' !== (string) ( $item['rule_term'] ?? '' ) ? '«' . (string) $item['rule_term'] . '»' : '',
		'crawl' => 'ایراد فنی' . ( '' !== (string) ( $item['detail'] ?? '' ) ? ' · ' . mb_strimwidth( (string) $item['detail'], 0, 60, '…' ) : '' ),
		default => '',
	};
}

/** محتوای کادر برای یک آدرس: وضعیت، داده گوگل همین صفحه، فهرست کوتاه کارها و یک دکمه به «کارها». */
function hodima_seo_advisor_render_box( string $url ): void {

	$path    = hodima_seo_advisor_path_key( $url );
	$row     = null !== $path ? hodima_seo_advisor_page( $path ) : null;
	$status  = hodima_seo_advisor_status();
	$ignored = null !== $path && in_array( $path, hodima_seo_advisor_ignored(), true );
	$tasks   = null !== $path && ! $ignored ? hodima_seo_advisor_box_tasks( $path ) : [];
	$n       = static fn( int|float $value, int $decimals = 0 ): string => number_format_i18n( $value, $decimals );
	$count   = count( $tasks );

	echo '<div class="hodima-advisor-box">';

	// نوار وضعیت
	printf(
		'<header class="hodima-advisor-box__head"><span class="hodima-advisor-box__icon dashicons dashicons-chart-line" aria-hidden="true"></span><div class="hodima-advisor-box__title"><strong>دستار سئو</strong><small>%s</small></div>%s</header>',
		esc_html( $status['fetched'] > 0 ? sprintf( 'داده %1$s روز اخیر · آخرین دریافت %2$s پیش', $n( $status['days'] ), human_time_diff( $status['fetched'] ) ) : 'هنوز داده‌ای گرفته نشده' ),
		null === $path ? '' : ( $ignored
			? '<span class="hodima-advisor-box__pill">نادیده گرفته شده</span>'
			: ( $count > 0 ? '<span class="hodima-advisor-box__pill hodima-advisor-box__pill--warn">' . esc_html( sprintf( '%s کار', $n( $count ) ) ) . '</span>' : '<span class="hodima-advisor-box__pill hodima-advisor-box__pill--ok">کاری نیست</span>' ) )
	);

	if ( null === $path ) {
		echo '</div>';
		return;
	}

	if ( null === $row ) {
		echo '<p class="hodima-advisor-box__empty">' . esc_html( $status['fetched'] > 0 ? 'برای این آدرس هنوز داده‌ای در سرچ کنسول ثبت نشده است (تازه منتشر شده یا نمایشی نداشته). بعد از دریافت داده بعدی دوباره ببینید.' : 'داده سرچ کنسول هنوز گرفته نشده است؛ در «ابزارهای هدیما ← دستار سئو ← خزش و داده» ردیف «دریافت داده گوگل ← اجرا همین حالا» را بزنید.' ) . '</p>';
	} else {
		$queries = hodima_seo_advisor_queries( (string) $row['path'], 5 );
		$ctr     = $row['impressions'] > 0 ? $row['clicks'] / $row['impressions'] * 100 : 0.0;

		// پنل داده: سه عدد هم‌اندازه با همان ظاهر کادر «عنوان سئو و توضیح متا» (برچسب بالا، مقدار در کادر) + جدول کلمه‌ها
		echo '<section class="hodima-advisor-box__data" aria-label="داده گوگل برای این صفحه"><h4>داده گوگل برای این صفحه</h4>';
		printf(
			'<div class="hodima-advisor-box__metrics"><div><span class="hodima-advisor-box__label">نمایش <small>(%1$s روز)</small>%5$s</span><div class="hodima-advisor-box__value">%2$s</div></div><div><span class="hodima-advisor-box__label">نرخ کلیک%6$s</span><div class="hodima-advisor-box__value">%3$s٪</div></div><div><span class="hodima-advisor-box__label">رتبه%7$s</span><div class="hodima-advisor-box__value">%4$s</div></div></div>',
			esc_html( $n( $status['days'] ) ),
			esc_html( $n( $row['impressions'] ) ),
			esc_html( $n( $ctr, 2 ) ),
			esc_html( $n( (float) $row['position'], 1 ) ),
			hodima_seo_advisor_help( 'impressions' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML امن‌شده در hodima_seo_advisor_help
			hodima_seo_advisor_help( 'ctr' ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- همان
			hodima_seo_advisor_help( 'position' ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- همان
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
	}

	// کارهای این صفحه: یک خط برای هر کار، همان فهرست «کارها»؛ راهنما و جزئیات فقط آنجا (SEO 3.7.6)
	echo '<section class="hodima-advisor-box__tasks" aria-label="کارهای این صفحه">';
	if ( $ignored ) {
		echo '<p class="hodima-advisor-box__empty">این آدرس در «دستار سئو» نادیده گرفته شده و پیشنهادی نمی‌گیرد.</p>';
	} elseif ( ! $tasks ) {
		echo '<p class="hodima-advisor-box__empty">برای این صفحه کار بازی در «کارها» نیست.</p>';
	} else {
		$srcs = array_count_values( array_map( static fn( array $item ): string => 'crawl' === ( $item['src'] ?? '' ) ? 'crawl' : ( 'opp' === ( $item['src'] ?? '' ) ? 'opp' : 'other' ), $tasks ) );
		$mix  = array_filter( [ isset( $srcs['crawl'] ) ? sprintf( '%s فنی', $n( $srcs['crawl'] ) ) : '', isset( $srcs['opp'] ) ? sprintf( '%s گوگل', $n( $srcs['opp'] ) ) : '', isset( $srcs['other'] ) ? sprintf( '%s دیگر', $n( $srcs['other'] ) ) : '' ] );
		printf( '<h4>کارهای این صفحه <small>(%s)</small></h4><ul class="hodima-advisor-box__list">', esc_html( 'همان فهرست «کارها»: ' . implode( '، ', $mix ) ) );
		foreach ( $tasks as $item ) {
			$level = (string) ( $item['level'] ?? 'warn' );
			$band  = (string) ( $item['band'] ?? '' );
			$note  = hodima_seo_advisor_box_task_note( $item );
			printf(
				'<li><span class="hodima-advisor-box__dot hodima-advisor-box__dot--%1$s" aria-hidden="true"></span><strong>%2$s</strong>%3$s%4$s</li>',
				esc_attr( in_array( $level, [ 'bad', 'warn', 'info' ], true ) ? $level : 'warn' ),
				esc_html( (string) $item['label'] ),
				'' !== $note ? ' <span class="hodima-advisor-box__note">' . esc_html( $note ) . '</span>' : '',
				isset( [ 'high' => 1, 'mid' => 1, 'low' => 1 ][ $band ] ) ? '<span class="hodima-advisor-box__prio hodima-advisor-box__prio--' . esc_attr( $band ) . '">' . esc_html( [ 'high' => 'اولویت بالا', 'mid' => 'اولویت متوسط', 'low' => 'اولویت کم' ][ $band ] ) . '</span>' : ''
			);
		}
		echo '</ul><p class="hodima-advisor-box__hint">نتیجه آخرین بررسی است. بعد از ویرایش و ذخیره، در «کارها» «انجام دادم» را بزنید تا همان لحظه دوباره سنجیده شود.</p>';
	}
	echo '</section>';

	echo '<footer class="hodima-advisor-box__foot">';
	printf( '<a class="button hodima-advisor-box__go" href="%s"><span class="dashicons dashicons-chart-line" aria-hidden="true"></span> دیدن راهنما و رفع در دستار سئو</a>', esc_url( hodima_seo_advisor_page_url( [ 'q' => $path ] ) ) );

	if ( $ignored ) {
		printf(
			'<a class="button" href="%s"><span class="dashicons dashicons-undo" aria-hidden="true"></span> بازگرداندن به پیشنهادها</a>',
			esc_url( wp_nonce_url( add_query_arg( [ 'action' => 'hodima_advisor_ignore', 'path' => $path, 'restore' => 1 ], admin_url( 'admin-post.php' ) ), 'hodima_advisor_ignore' ) )
		);
	}

	echo '</footer></div>';
}
