<?php
/**
 * ماژول «دستار سئو» — تب «روند و فصل‌ها»
 * Path: core/advisor/advisor-trend-view.php
 *
 * داده از advisor-trend.php. کالبد: ۴ کارت آمار ← نمودار کلیک ماهانه (۱۶ ماه، ماه ناقص کم‌رنگ، تفسیر و
 * مقایسه با سال قبل) ← کلمه‌های تازه ← کلمه‌های از دست رفته (جدول با صفحه مرتبط). فقط می‌خواند و
 * دستور می‌دهد؛ چیزی را در سایت عوض نمی‌کند.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * صفحه‌ای که برای یک کلمه بیشترین نمایش را دارد (از جدول کلمه‌ها)، یا ''.
 */
function hodima_seo_advisor_trend_term_page( string $term ): string {
	$pages = hodima_seo_advisor_term_pages( $term, 1 );
	return $pages ? hodima_seo_advisor_breakdown_page_title( (string) $pages[0]['path'] ) : '';
}

/**
 * جدول کلمه‌های تازه یا از دست رفته.
 *
 * @param list<array<string, mixed>> $terms
 */
function hodima_seo_advisor_render_terms_table( array $terms, string $label, string $page_label ): void {

	$n = static fn( int|float $value ): string => number_format_i18n( $value );
	?>
	<div class="hds-table-wrap" tabindex="0" role="region" aria-label="<?php echo esc_attr( $label ); ?>">
		<table class="widefat striped hodima-advisor__terms">
			<thead><tr><th scope="col">کلمه</th><th scope="col">نمایش</th><th scope="col">کلیک</th><th scope="col">رتبه</th><th scope="col"><?php echo esc_html( $page_label ); ?></th></tr></thead>
			<tbody>
				<?php foreach ( array_slice( $terms, 0, 15 ) as $row ) : ?>
					<?php $page = hodima_seo_advisor_trend_term_page( (string) $row['term'] ); ?>
					<tr>
						<th scope="row"><?php echo esc_html( (string) $row['term'] ); ?></th>
						<td><?php echo esc_html( $n( $row['impressions'] ) ); ?></td>
						<td><?php echo esc_html( $n( $row['clicks'] ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $row['position'], 1 ) ); ?></td>
						<td><?php echo '' !== $page ? esc_html( $page ) : '—'; ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
}

/**
 * محتوای تب «روند و فصل‌ها».
 */
function hodima_seo_advisor_render_trend_tab(): void {

	$data   = hodima_seo_advisor_trend();
	$n      = static fn( int|float $value ): string => number_format_i18n( $value );
	$months = (array) ( $data['months'] ?? [] );
	$new    = (array) ( $data['new'] ?? [] );
	$lost   = (array) ( $data['lost'] ?? [] );
	$empty  = (int) ( $data['fetched'] ?? 0 ) <= 0;

	$complete = array_values( array_filter( $months, static fn( array $m ): bool => ! $m['partial'] ) );
	$last     = $complete ? end( $complete ) : null;
	$yoy      = hodima_seo_advisor_trend_year_over_year( $months );

	hodima_seo_ui_stats( [
		[ 'label' => 'کلیک ماه کامل اخیر', 'value' => $empty || null === $last ? '—' : esc_html( $n( $last['clicks'] ) ), 'sub' => null === $last ? '' : esc_html( (string) $last['label'] ), 'empty' => $empty || null === $last ],
		[ 'label' => 'نسبت به سال قبل', 'value' => null === $yoy ? '—' : esc_html( $n( abs( round( $yoy['change'] ) ) ) . '٪' ), 'sub' => null === $yoy ? 'داده سال قبل نیست' : esc_html( sprintf( '%1$s · %2$s: %3$s ← %4$s', $yoy['change'] >= 0 ? 'بیشتر' : 'کمتر', $yoy['label'], $n( $yoy['prev_clicks'] ), $n( $yoy['clicks'] ) ) ), 'empty' => null === $yoy ],
		[ 'label' => 'کلمه تازه', 'value' => $empty ? '—' : esc_html( $n( count( $new ) ) ), 'sub' => $empty ? '' : '۲۸ روز اخیر', 'empty' => $empty ],
		[ 'label' => 'کلمه از دست رفته', 'value' => $empty ? '—' : esc_html( $n( count( $lost ) ) ), 'sub' => $empty ? '' : '۲۸ روز اخیر', 'empty' => $empty ],
	] );

	hodima_seo_advisor_render_toolbar( 'trend' );

	if ( $empty ) {
		hodima_seo_ui_card_open( 'روند و فصل‌ها' );
		hodima_seo_ui_empty( 'هنوز گرفته نشده', 'بعد از «به‌روزرسانی داده» (و تا چند دقیقه) روند ۱۶ ماهه و کلمه‌های تازه و از دست رفته اینجا پر می‌شود.' );
		hodima_seo_ui_card_close();
		return;
	}

	// روند ماهانه
	hodima_seo_ui_card_open( 'کلیک هر ماه', 'تا ۱۶ ماه اخیر (سقف داده گوگل)، به ماه شمسی؛ ماه ناقص (کم‌رنگ) در مقایسه نمی‌آید.' );

	if ( count( $months ) < 2 ) {
		hodima_seo_ui_empty( 'داده کافی نیست', 'حداقل دو ماه داده لازم است.' );
	} else {
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
				$reading .= ' اگر پرکلیک‌ترین ماه هر سال تکرار می‌شود، از ۴ تا ۶ هفته قبلش محتوا، عنوان‌ها و موجودی آن فصل را آماده کنید تا گوگل به‌موقع ایندکس و رتبه را بالا بیاورد.';
			}
			echo '<div class="hodima-advisor__advice">' . esc_html( $reading ) . '</div>';
		}
	}
	hodima_seo_ui_card_close();

	// کلمه‌های تازه
	hodima_seo_ui_card_open( 'کلمه‌های تازه (۲۸ روز اخیر)', 'کلمه‌هایی که الان دست‌کم ۱۰۰ نمایش دارند ولی ۲۸ روز قبلش تقریبا نمایشی نداشتند؛ فرصت زودهنگام.' );
	if ( ! $new ) {
		hodima_seo_ui_empty( 'کلمه تازه‌ای نیست', 'در این پنجره کلمه غیربرندی تازه با نمایش کافی پیدا نشد.' );
	} else {
		hodima_seo_advisor_render_terms_table( $new, 'کلمه‌های تازه', 'صفحه مرتبط' );
		echo '<div class="hodima-advisor__advice"><strong>چه کنید:</strong> برای هر کلمه ببینید صفحه‌ای که گوگل نشان می‌دهد جواب واقعی آن است یا نه. اگر صفحه مناسب ندارید یک صفحه یا بخش اختصاصی بنویسید؛ اگر دارید کلمه را در عنوان و یک تیتر بیاورید تا پیش از رقبا رتبه بگیرد.</div>';
	}
	hodima_seo_ui_card_close();

	// کلمه‌های از دست رفته
	hodima_seo_ui_card_open( 'کلمه‌های از دست رفته (۲۸ روز اخیر)', 'کلمه‌هایی که ۲۸ روز قبلش دست‌کم ۱۵۰ نمایش داشتند و الان تقریبا نمایشی ندارند.' );
	if ( ! $lost ) {
		hodima_seo_ui_empty( 'کلمه از دست رفته‌ای نیست', 'در این پنجره کلمه غیربرندی پرنمایشی از نتایج نیفتاده است.' );
	} else {
		hodima_seo_advisor_render_terms_table( $lost, 'کلمه‌های از دست رفته', 'صفحه قبلی' );
		echo '<div class="hodima-advisor__advice"><strong>چه کنید:</strong> بررسی کنید صفحه مرتبط حذف، ریدایرکت یا ناموجود نشده و عنوان یا آدرسش تغییر نکرده باشد. اگر تغییر عمدی نبوده، همان را برگردانید؛ اگر فصلی است نگران نباشید.</div>';
	}
	hodima_seo_ui_card_close();

	if ( ! empty( $data['errors'] ) ) {
		echo '<p class="hodima-advisor__more">' . esc_html( 'بعضی بخش‌ها گرفته نشد (بار بعد دوباره امتحان می‌شود): ' . implode( ' | ', array_map( 'strval', (array) $data['errors'] ) ) ) . '</p>';
	}
}
