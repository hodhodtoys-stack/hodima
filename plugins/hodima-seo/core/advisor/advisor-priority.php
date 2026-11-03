<?php
/**
 * ماژول «دستار سئو» — موتور اولویت (SEO 3.4.0)
 * Path: core/advisor/advisor-priority.php
 *
 * به هر ایراد صفحه «کارها» یک امتیاز می‌دهد تا مدیر بداند از کجا شروع کند:
 *   امتیاز = (اهمیت ایراد ÷ ۳) × (۰٫۱۵ + ۰٫۸۵ × ارزش صفحه) × اطمینان ÷ سختی رفع
 * اهمیت: قرمز ۳، کهربایی ۲، آبی ۱. ارزش صفحه (۰ تا ۱، مقیاس لگاریتمی): بیشتر از نمایش گوگل و فروش ۹۰ روز اخیر ووکامرس
 * (فقط خواندن از جدول آمار خود ووکامرس). اطمینان: ایراد فنی خزنده ۱، پیشنهاد و رقابت گوگل کمتر (تخمین‌اند). سختی: ۱ چند دقیقه،
 * ۲ ویرایش محتوا یا لینک، ۳ کار بزرگ. فقط می‌خواند و ترتیب می‌دهد؛ چیزی را در سایت عوض نمی‌کند.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** کش فروش محصول‌ها (transient؛ ۶ ساعت). */
const HODIMA_SEO_ADVISOR_SALES_CACHE = 'hodima_advisor_sales';

/** بازه فروش به روز و بیشینه محصول خوانده‌شده. */
const HODIMA_SEO_ADVISOR_SALES_DAYS  = 90;
const HODIMA_SEO_ADVISOR_SALES_LIMIT = 300;

/** ایراد یکسان روی این شمار صفحه یا بیشتر = «کار کلی» (کارت «تصویر مشترک»). */
const HODIMA_SEO_ADVISOR_BULK_MIN = 5;

/** مرز نشان اولویت: امتیاز ≥ بالا ← «بالا»، ≥ متوسط ← «متوسط»، وگرنه «کم». */
const HODIMA_SEO_ADVISOR_BAND_HIGH = 0.30;
const HODIMA_SEO_ADVISOR_BAND_MID  = 0.10;

/** اطمینان هر منبع (ایراد فنی قطعی؛ پیشنهاد گوگل تخمین). */
const HODIMA_SEO_ADVISOR_CONFIDENCE = [
	'crawl'  => 1.0,
	'dead'   => 1.0,
	'index'  => 0.9,
	'opp'    => 0.7,
	'comp'   => 0.7,
	'unseen' => 0.6,
];

/** سختی رفع پیشنهادهای گوگل بر اساس قاعده (پیش‌فرض ۲). */
const HODIMA_SEO_ADVISOR_OPP_EFFORT = [
	'low_ctr'          => 1,
	'missing_headings' => 1,
	'missing_content'  => 2,
	'near_first'       => 2,
	'weak_query'       => 3,
	'cannibal'         => 3,
	'decline'          => 3,
];

/**
 * فروش ۹۰ روز اخیر هر محصول: مسیر صفحه ← مبلغ. فقط می‌خواند (جدول wc_order_product_lookup ووکامرس)؛ بدون ووکامرس یا بدون آن
 * جدول خالی برمی‌گردد و اولویت فقط از نمایش گوگل می‌آید. فیلتر `hodima_seo_advisor_use_sales` برای خاموش‌کردن.
 *
 * @return array<string, int>
 */
function hodima_seo_advisor_sales_map(): array {

	global $wpdb;

	if ( ! (bool) apply_filters( 'hodima_seo_advisor_use_sales', true ) ) {
		return [];
	}

	$cached = get_transient( HODIMA_SEO_ADVISOR_SALES_CACHE );

	if ( is_array( $cached ) ) {
		return $cached;
	}

	$map = [];

	$wpdb->suppress_errors( true ); // query() خودش last_error را پیش از هر پرسش خالی می‌کند
	$rows             = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->prepare(
			'SELECT product_id, SUM(product_net_revenue) AS total FROM %i WHERE date_created >= %s GROUP BY product_id ORDER BY total DESC LIMIT %d',
			$wpdb->prefix . 'wc_order_product_lookup',
			gmdate( 'Y-m-d H:i:s', time() - HODIMA_SEO_ADVISOR_SALES_DAYS * DAY_IN_SECONDS ),
			HODIMA_SEO_ADVISOR_SALES_LIMIT
		),
		ARRAY_A
	);
	$failed           = '' !== $wpdb->last_error; // جدول نیست (ووکامرس یا آمارش نیست)
	$wpdb->suppress_errors( false );

	if ( ! $failed ) {
		foreach ( (array) $rows as $row ) {
			$url = get_permalink( (int) $row['product_id'] );
			$key = false === $url ? null : hodima_seo_advisor_path_key( $url );
			if ( null !== $key && (float) $row['total'] > 0 ) {
				$map[ $key ] = (int) round( (float) $row['total'] );
			}
		}
	}

	set_transient( HODIMA_SEO_ADVISOR_SALES_CACHE, $map, 6 * HOUR_IN_SECONDS );

	return $map;
}

/** مبلغ فروش برای نمایش: «۱۴ میلیون تومان» (واحد از ووکامرس). */
function hodima_seo_advisor_money_label( int $amount ): string {

	$unit = function_exists( 'get_woocommerce_currency_symbol' ) ? html_entity_decode( (string) get_woocommerce_currency_symbol(), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) : '';
	$text = $amount >= 1000000 ? sprintf( '%s میلیون', number_format_i18n( round( $amount / 1000000, 1 ), $amount >= 10000000 ? 0 : 1 ) ) : number_format_i18n( $amount );

	return trim( $text . ' ' . $unit );
}

/**
 * ارزش صفحه (۰ تا ۱): بیشتر از نمایش گوگل و فروش، هر کدام نسبت به بیشینه همین سایت در مقیاس لگاریتمی (یک صفحه خیلی
 * پرنمایش بقیه را صفر نکند).
 */
function hodima_seo_advisor_page_value( int $impressions, int $sales, int $max_impressions, int $max_sales ): float {

	$part = static fn( int $value, int $max ): float => $max > 0 && $value > 0 ? min( 1.0, log10( 1 + $value ) / log10( 1 + $max ) ) : 0.0;

	return max( $part( $impressions, $max_impressions ), $part( $sales, $max_sales ) );
}

/**
 * سختی رفع یک ایراد (۱ تا ۳).
 *
 * @param array<string, mixed> $item
 */
function hodima_seo_advisor_item_effort( array $item ): int {

	$src = (string) ( $item['src'] ?? '' );

	if ( 'crawl' === $src ) {
		return (int) ( hodima_seo_advisor_issue_catalog()[ (string) ( $item['mark'] ?? '' ) ]['effort'] ?? 2 );
	}

	if ( 'opp' === $src ) {
		return HODIMA_SEO_ADVISOR_OPP_EFFORT[ (string) strtok( (string) ( $item['mark'] ?? '' ), '|' ) ] ?? 2;
	}

	return 'index' === $src ? 1 : 2;
}

/**
 * امتیاز ایراد (۰ تا ۱).
 *
 * @param array<string, mixed> $item
 */
function hodima_seo_advisor_item_score( array $item, float $value ): float {

	$level      = [ 'bad' => 3, 'warn' => 2, 'info' => 1 ][ (string) ( $item['level'] ?? 'warn' ) ] ?? 2;
	$confidence = HODIMA_SEO_ADVISOR_CONFIDENCE[ (string) ( $item['src'] ?? '' ) ] ?? 0.7;
	$potential  = (int) ( $item['potential'] ?? 0 );

	// پیشنهاد گوگل کلیک بالقوه دارد: ارزش از *اندازه سود* می‌آید (۷۰٪) و فقط ۳۰٪ از پرنمایش‌بودن صفحه. تا ۳.۵.۱ ارزش = بیشتر از
	// این دو بود؛ صفحه پرنمایش با «+۱ کلیک (تخمین)» هم «اولویت بالا» می‌شد و هر ۵ «کار مهم امروز» بالا بود (SEO 3.5.2)
	if ( $potential > 0 ) {
		$gain  = min( 1.0, log10( 1 + $potential ) / log10( 1 + 300 ) );
		$value = 0.3 * $value + 0.7 * $gain;
	}

	$score = ( $level / 3 ) * ( 0.15 + 0.85 * $value ) * $confidence / max( 1, hodima_seo_advisor_item_effort( $item ) );

	// «alt خالی (بررسی کنید)» ایراد قطعی نیست (لوگو و تزئین درست است)؛ هرگز «اولویت بالا» نمی‌شود (SEO 3.4.9)
	return 'crawl' === ( $item['src'] ?? '' ) && 'img_empty' === ( $item['mark'] ?? '' ) ? min( $score, HODIMA_SEO_ADVISOR_BAND_MID / 2 ) : $score;
}

/** نشان اولویت از امتیاز: high | mid | low. */
function hodima_seo_advisor_priority_band( float $score ): string {

	if ( $score >= HODIMA_SEO_ADVISOR_BAND_HIGH ) {
		return 'high';
	}

	return $score >= HODIMA_SEO_ADVISOR_BAND_MID ? 'mid' : 'low';
}

/**
 * ارزش و امتیاز را به صفحه‌ها و ایرادهایشان اضافه و آن‌ها را به ترتیب اولویت مرتب می‌کند (بیشترین اثر اول).
 *
 * @param list<array{path: string, title: string, impressions: int, items: list<array<string, mixed>>}> $pages
 * @return list<array<string, mixed>>
 */
function hodima_seo_advisor_priority_rank( array $pages ): array {

	$sales   = hodima_seo_advisor_sales_map();
	$imps    = array_column( $pages, 'impressions' );
	$max_imp = $imps ? (int) max( $imps ) : 0;
	$max_sal = $sales ? (int) max( $sales ) : 0;

	foreach ( $pages as &$page ) {
		$page['sales'] = (int) ( $sales[ $page['path'] ] ?? 0 );
		$value         = hodima_seo_advisor_page_value( (int) $page['impressions'], $page['sales'], $max_imp, $max_sal );

		foreach ( $page['items'] as &$item ) {
			$item['effort'] = hodima_seo_advisor_item_effort( $item );
			$item['score']  = hodima_seo_advisor_item_score( $item, $value );
			$item['band']   = hodima_seo_advisor_priority_band( $item['score'] );
		}
		unset( $item );

		usort( $page['items'], static fn( array $a, array $b ): int => $b['score'] <=> $a['score'] );

		$page['score'] = (float) $page['items'][0]['score'] + 0.02 * ( count( $page['items'] ) - 1 ); // بیشترین ایراد + کمی برای تعداد
	}
	unset( $page );

	usort( $pages, static fn( array $a, array $b ): int => [ $b['score'], $b['impressions'], $a['path'] ] <=> [ $a['score'], $a['impressions'], $b['path'] ] );

	return $pages;
}
