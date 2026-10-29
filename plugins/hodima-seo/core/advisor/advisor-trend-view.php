<?php
/**
 * ماژول «دستار سئو» — نمودار روند ماهانه و کلمه‌های تازه/از دست رفته
 * Path: core/advisor/advisor-trend-view.php
 *
 * داده از advisor-trend.php. تا SEO 3.1.2 تب «روند ماه‌به‌ماه» بود؛ از ۳.۱.۳ دو کارت تب «آمار جستجو» است
 * (advisor-insights-view.php): نمودار کلیک ماهانه (۱۶ ماه، ماه ناقص کم‌رنگ، تفسیر و مقایسه با سال قبل) و یک کارت
 * کلمه‌های از دست رفته و تازه. فقط می‌خواند و دستور می‌دهد؛ چیزی را در سایت عوض نمی‌کند.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * فهرست فشرده کلمه‌های تازه یا از دست رفته (کلمه + نمایش)؛ جدول ۱۵ ردیفی جای خود را به آن داد.
 *
 * @param list<array<string, mixed>> $terms
 */
function hodima_seo_advisor_render_term_list( array $terms, string $impressions_label, int $limit = 6 ): void {

	$n = static fn( int|float $value ): string => number_format_i18n( $value );

	echo '<ul class="hodima-advisor__termlist">';
	foreach ( array_slice( $terms, 0, $limit ) as $row ) {
		printf(
			'<li><span class="hodima-advisor__termlist-name">%1$s</span><small>%2$s</small></li>',
			esc_html( (string) $row['term'] ),
			esc_html( sprintf( '%1$s نمایش %2$s', $n( (int) $row['impressions'] ), $impressions_label ) )
		);
	}
	echo '</ul>';
	if ( count( $terms ) > $limit ) {
		echo '<p class="hodima-advisor__more">' . esc_html( sprintf( 'و %s کلمه دیگر', $n( count( $terms ) - $limit ) ) ) . '</p>';
	}
}

/**
 * کارت نمودار کلیک ماهانه (۱۶ ماه) با تفسیر و مقایسه با سال قبل.
 *
 * @param array<string, mixed> $data خروجی hodima_seo_advisor_trend()
 */
function hodima_seo_advisor_render_trend_chart( array $data ): void {

	$n        = static fn( int|float $value ): string => number_format_i18n( $value );
	$months   = (array) ( $data['months'] ?? [] );
	$complete = array_values( array_filter( $months, static fn( array $m ): bool => ! $m['partial'] ) );
	$yoy      = hodima_seo_advisor_trend_year_over_year( $months );

	hodima_seo_ui_card_open( 'کلیک هر ماه', 'تا ۱۶ ماه اخیر به ماه شمسی؛ ماه ناقص (کم‌رنگ) در مقایسه نمی‌آید.' );

	if ( count( $months ) < 2 ) {
		hodima_seo_ui_empty( 'داده کافی نیست', 'حداقل دو ماه داده لازم است.' );
		hodima_seo_ui_card_close();
		return;
	}

	$max = max( 1, ...array_column( $months, 'clicks' ) );
	echo '<div class="hodima-advisor__months-wrap" tabindex="0" role="region" aria-label="نمودار کلیک ماهانه"><ol class="hodima-advisor__months">';
	foreach ( $months as $month ) {
		printf(
			'<li%1$s><span class="hodima-advisor__months-value">%2$s</span><span class="hodima-advisor__months-bar" style="--h:%3$d%%" aria-hidden="true"></span><span class="hodima-advisor__months-label">%4$s</span></li>',
			$month['partial'] ? ' class="is-partial"' : '',
			esc_html( $n( $month['clicks'] ) ),
			(int) round( $month['clicks'] / $max * 100 ),
			esc_html( (string) $month['label'] . ( $month['partial'] ? ' (ناقص)' : '' ) )
		);
	}
	echo '</ol></div>';

	if ( $complete ) {
		$best  = $complete[0];
		$worst = $complete[0];
		foreach ( $complete as $month ) {
			$best  = $month['clicks'] > $best['clicks'] ? $month : $best;
			$worst = $month['clicks'] < $worst['clicks'] ? $month : $worst;
		}
		$reading = sprintf( 'پرکلیک‌ترین ماه کامل: %1$s (%2$s کلیک)؛ کم‌کلیک‌ترین: %3$s (%4$s کلیک).', $best['label'], $n( $best['clicks'] ), $worst['label'], $n( $worst['clicks'] ) );
		if ( null !== $yoy ) {
			$reading .= sprintf( ' %1$s نسبت به همان ماه سال قبل %2$s٪ %3$s.', $yoy['label'], $n( abs( round( $yoy['change'] ) ) ), $yoy['change'] >= 0 ? 'بیشتر شده' : 'کمتر شده' );
		}
		if ( count( $complete ) >= 12 ) {
			$reading .= ' اگر پرکلیک‌ترین ماه هر سال تکرار می‌شود، از ۴ تا ۶ هفته قبلش محتوا و موجودی آن فصل را آماده کنید.';
		}
		echo '<div class="hodima-advisor__advice">' . esc_html( $reading ) . '</div>';
	}

	hodima_seo_ui_card_close();
}

/**
 * کارت «کلمه‌های از دست رفته و تازه» (۲۸ روز اخیر در برابر ۲۸ روز پیش از آن).
 *
 * @param array<string, mixed> $data خروجی hodima_seo_advisor_trend()
 */
function hodima_seo_advisor_render_term_changes( array $data ): void {

	$new  = (array) ( $data['new'] ?? [] );
	$lost = (array) ( $data['lost'] ?? [] );

	hodima_seo_ui_card_open( 'کلمه‌های از دست رفته و تازه', '۲۸ روز اخیر در برابر ۲۸ روز پیش از آن؛ فقط کلمه‌های غیربرندی.' );

	echo '<h4 class="hodima-advisor__subhead hodima-advisor__subhead--warn">' . esc_html( sprintf( 'از دست رفته (%s)', number_format_i18n( count( $lost ) ) ) ) . '</h4>';
	if ( $lost ) {
		hodima_seo_advisor_render_term_list( $lost, 'قبلا' );
		echo '<div class="hodima-advisor__advice"><strong>چه کنید:</strong> بررسی کنید صفحه مرتبط حذف، ریدایرکت یا ناموجود نشده و عنوان یا آدرسش تغییر نکرده باشد؛ اگر فصلی است نگران نباشید.</div>';
	} else {
		echo '<p class="hodima-advisor__more">کلمه پرنمایشی از نتایج نیفتاده است.</p>';
	}

	echo '<h4 class="hodima-advisor__subhead hodima-advisor__subhead--ok">' . esc_html( sprintf( 'تازه (%s)', number_format_i18n( count( $new ) ) ) ) . '</h4>';
	if ( $new ) {
		hodima_seo_advisor_render_term_list( $new, 'حالا' );
		echo '<div class="hodima-advisor__advice"><strong>چه کنید:</strong> اگر صفحه مناسب کلمه ندارید یک صفحه یا بخش اختصاصی بنویسید؛ اگر دارید کلمه را در عنوان و یک تیتر بیاورید.</div>';
	} else {
		echo '<p class="hodima-advisor__more">کلمه غیربرندی تازه با نمایش کافی پیدا نشد.</p>';
	}

	hodima_seo_ui_card_close();
}
