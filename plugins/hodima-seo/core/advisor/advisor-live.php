<?php
/**
 * ماژول «دستار سئو» — بررسی فوری عنوان، توضیح متا و تیترها از خود سایت
 * Path: core/advisor/advisor-live.php
 *
 * تا SEO 2.8.9 ایرادهای «ایرادهای فنی» (بدون متا، عنوان کوتاه/بلند، بدون H1، تکراری‌ها) فقط با خواندن دوباره
 * خروجی Screaming Frog عوض می‌شدند: مدیر متا را می‌نوشت و ایراد تا خزش بعدی می‌ماند. حالا بعد از ذخیره
 * نوشته/برگه/محصول یا دسته، همان صفحه از خود سایت خوانده می‌شود و با **همان خواندن و نوشتن خزنده شبانه**
 * (advisor-crawler.php: fetch، parse، page_row، store_page) ردیف خزش و لینک‌های خروجی همان آدرس تازه می‌شود
 * (از ۳.۱.۹؛ پیش‌تر پارسر و ۹ ستون جدا داشت و canonical، تعداد کلمه، H1، تصویر بدون alt و X-Robots-Tag تا
 * خزش بعدی کهنه می‌ماندند)؛ ایرادها و پیشنهادهای وابسته (نرخ کلیک با سلامت متا، کلمه در تیتر) همان لحظه از
 * همین جدول تازه ساخته می‌شوند — منطق دوم نه.
 *
 * چرا خواندن صفحه و نه حساب از کد: H1 در قالب‌های مختلف (محصول، دسته با عنوان دلخواه، برگه با H1 داخل
 * متن، برگه ووکامرس) جای متفاوتی دارد؛ فقط خود صفحه همان چیزی است که گوگل می‌بیند.
 *
 * فقط آدرسی که در خزش هست به‌روز می‌شود (ردیف تازه نمی‌سازد: بدون لینک ورودی «یتیم» حساب می‌شد).
 * کار در کرون یک‌باره کمی بعد از ذخیره است: ذخیره کند نمی‌شود و پاک شدن کش صفحه (لایت‌اسپید) پیش از
 * خواندن انجام شده؛ پارامتر یکتا هم کش را دور می‌زند. چیزی را در سایت عوض نمی‌کند.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** رویداد بررسی یک آدرس (آرگومان: مسیر یکسان‌شده). */
const HODIMA_SEO_ADVISOR_LIVE_CRON = 'hodima_advisor_live_check';

/** گزینه زمان آخرین بررسی هر آدرس (مسیر ← زمان؛ autoload خاموش). */
const HODIMA_SEO_ADVISOR_LIVE_OPTION = 'hodima_advisor_live';

/** حداکثر آدرس در گزینه بالا (قدیمی‌ترها می‌روند). */
const HODIMA_SEO_ADVISOR_LIVE_MAX = 2000;

/** پارامتر دور زدن کش در آدرس بررسی. */
const HODIMA_SEO_ADVISOR_LIVE_PARAM = 'hodima-advisor-check';

add_action( HODIMA_SEO_ADVISOR_LIVE_CRON, static function ( string $path ): void {
	hodima_seo_advisor_live_check( $path );
} );

/**
 * آدرس یک شیء ذخیره‌شده را برای بررسی زمان‌بندی می‌کند (اگر در خزش هست). فقط نوشته منتشرشده.
 */
function hodima_seo_advisor_live_schedule_object( string $kind, int $id ): void {

	if ( $id <= 0 || hodima_seo_advisor_crawl_status()['imported'] <= 0 ) {
		return;
	}

	if ( taxonomy_exists( $kind ) ) {
		$link = get_term_link( $id, $kind );
		$url  = is_string( $link ) ? $link : '';
	} elseif ( post_type_exists( $kind ) && 'publish' === get_post_status( $id ) ) {
		$url = (string) get_permalink( $id );
	} else {
		return;
	}

	$path = '' !== $url ? hodima_seo_advisor_path_key( $url ) : null;

	if ( null !== $path && null !== hodima_seo_advisor_crawl_row( $path ) && ! wp_next_scheduled( HODIMA_SEO_ADVISOR_LIVE_CRON, [ $path ] ) ) {
		wp_schedule_single_event( time(), HODIMA_SEO_ADVISOR_LIVE_CRON, [ $path ] );
	}
}

/**
 * یک آدرس را از سایت می‌خواند و ردیف خزشش را به‌روز می‌کند؛ بعد پیشنهادهای همان آدرس دوباره ساخته می‌شوند.
 *
 * @return bool true = خوانده و نوشته شد
 */
function hodima_seo_advisor_live_check( string $path ): bool {
	return 200 === hodima_seo_advisor_live_fetch( $path );
}

/**
 * بدنه بررسی یک آدرس. `$record_status`: پاسخ غیر ۲۰۰ (ریدایرکت یا خطا) هم در ردیف خزش نوشته شود؛ فقط بعد از
 * «انجام دادم» مدیر روی ایراد «خطای ۴۰۴/۵۰۰» (advisor-marks.php). بررسی خودکار بعد از ذخیره این کار را نمی‌کند:
 * یک خطای گذرای هاست صفحه سالم را «خراب» نشان می‌داد (ریدایرکت و خطا را خزش بعدی می‌گوید).
 *
 * @return int کد پاسخ HTTP؛ صفر = آدرس در خزش نیست، درخواست نرسید یا نوشتن نشد
 */
function hodima_seo_advisor_live_fetch( string $path, bool $record_status = false ): int {

	global $wpdb;

	if ( null === hodima_seo_advisor_crawl_row( $path ) ) {
		return 0;
	}

	// مسیر ذخیره‌شده رمزگشایی‌شده است (آدرس فارسی)؛ برای درخواست HTTP هر بخش دوباره کد می‌شود
	$url = hodima_seo_advisor_url( implode( '/', array_map( 'rawurlencode', explode( '/', $path ) ) ) );
	$res = hodima_seo_advisor_crawler_fetch( add_query_arg( HODIMA_SEO_ADVISOR_LIVE_PARAM, (string) time(), $url ) ); // همان خواندن خزنده (بدون ریدایرکت، با پارامتر دور زدن کش)

	if ( null === $res ) {
		return 0;
	}

	$code = $res['status'];

	// صفحه ۲۰۰ نیست: داده خزش دست نمی‌خورد، مگر بررسی دستی مدیر روی ایراد خطا (بالا)
	if ( 200 !== $code ) {
		if ( ! $record_status || $code < 300 ) {
			return 0;
		}
		$updated = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			hodima_seo_advisor_table( 'crawl_pages' ),
			[ 'status' => $code, 'redirect_to' => $code < 400 && '' !== $res['location'] ? (string) ( hodima_seo_advisor_path_key( $res['location'] ) ?? '' ) : '' ],
			[ 'path' => $path ],
			[ '%d', '%s' ],
			[ '%s' ]
		);
		hodima_seo_advisor_crawl_map( true );
		return false === $updated ? 0 : $code;
	}

	if ( ! $res['html'] ) {
		return 0; // خروجی غیر HTML صفحه نیست
	}

	// خواندن و نوشتن مثل دور خزنده شبانه (یک روش برای عنوان، متا، تیترها، robots/X-Robots-Tag، canonical، کلمه‌ها، تصویرها و لینک‌ها)؛
	// آمار لینک ورودی و عمق همان می‌ماند تا دور بعد
	$parsed = hodima_seo_advisor_crawler_parse( $res['body'], $url );
	$ms     = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT resp_ms FROM %i WHERE path = %s', hodima_seo_advisor_table( 'crawl_pages' ), $path ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$row    = hodima_seo_advisor_crawler_page_row( $path, 200, $ms, $parsed, '', $res['x_robots'] ); // زمان پاسخ همان خزش می‌ماند: این درخواست پارامتر دور زدن کش دارد و سرعتش معیار «کند» نیست

	if ( ! hodima_seo_advisor_crawler_store_page( $path, $row, hodima_seo_advisor_crawler_page_links( $parsed, $path ), true ) ) {
		return 0;
	}

	$times          = get_option( HODIMA_SEO_ADVISOR_LIVE_OPTION, [] );
	$times          = is_array( $times ) ? $times : [];
	unset( $times[ $path ] );
	$times[ $path ] = time(); // تازه‌ترین آخر؛ از پرشدن، قدیمی‌ترها از اول می‌روند
	update_option( HODIMA_SEO_ADVISOR_LIVE_OPTION, array_slice( $times, -HODIMA_SEO_ADVISOR_LIVE_MAX, null, true ), false );

	hodima_seo_advisor_crawl_map( true );

	if ( hodima_seo_advisor_status()['fetched'] > 0 && null !== hodima_seo_advisor_page( $path ) ) {
		hodima_seo_advisor_save_advice( $path ); // سلامت متا و تیتر رندرشده در پیشنهادها
	}

	// کارهای «انجام دادم» ایرادهای متنی همین صفحه با همین خواندن سنجیده می‌شوند (ذخیره صفحه هم همین را صدا می‌زند)
	if ( function_exists( 'hodima_seo_advisor_tasks_sync_crawl' ) ) {
		hodima_seo_advisor_tasks_sync_crawl( $path, [ ...HODIMA_SEO_ADVISOR_MARK_LIVE_CODES, 'broken' ] );
	}

	return 200;
}

/**
 * زمان آخرین بررسی یک آدرس از سایت، فقط اگر بعد از (یا هم‌زمان با) آخرین خواندن خزش بوده (خزش تازه‌تر جای آن را گرفته)؛ وگرنه صفر.
 */
function hodima_seo_advisor_live_time( string $path ): int {

	$times = get_option( HODIMA_SEO_ADVISOR_LIVE_OPTION, [] );
	$time  = is_array( $times ) ? (int) ( $times[ $path ] ?? 0 ) : 0;

	return $time >= hodima_seo_advisor_crawl_status()['imported'] ? $time : 0;
}
