<?php
/**
 * ماژول «دستار سئو» — نمای تب «آمار جستجو» و کارت «آدرس‌های تکراری»
 * Path: core/advisor/advisor-insights-view.php
 *
 * داده از advisor-insights.php و advisor-trend.php. تب «آمار جستجو» (SEO 3.1.3؛ قبلا «کلمه‌های جستجو» + «روند ماه‌به‌ماه»):
 * ۴ کارت آمار ← نمودار کلیک ماهانه ← پرنمایش‌ترین کلمه‌های غیربرندی | کلمه‌های از دست رفته و تازه ← رتبه بد موبایل.
 * کارت «آدرس‌های تکراری یک صفحه» پایین تب «رقابت صفحه‌ها» می‌آید. فقط می‌خواند؛ چیزی را در سایت عوض نمی‌کند.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** جمله تفسیر سهم کلیک برندی. */
function hodima_seo_advisor_brand_reading( float $brand_share ): string {

	if ( $brand_share >= 0.6 ) {
		return 'بیشتر کلیک سایت از جستجوی نام خودتان می‌آید؛ یعنی مردم شما را می‌شناسند ولی بیشتر مشتری تازه پیدا نمی‌شود. رشد واقعی از کلمه‌های غیربرندی می‌آید: برای کلمه‌های عمومی صفحه یا مقاله بنویسید و رتبه‌شان را بالا ببرید.';
	}

	if ( $brand_share >= 0.3 ) {
		return 'تعادل خوبی است: هم مشتری‌های وفادار با نام سایت می‌آیند، هم بخش مهمی از ترافیک از جستجوی عمومی است. برای رشد، کلمه‌های غیربرندی نزدیک صفحه اول (جدول پایین) را تقویت کنید.';
	}

	return 'بیشتر کلیک سایت از جستجوی عمومی است و نام سایت کم جستجو می‌شود. برای ماندگاری مشتری کمک می‌کند که مردم نام سایت را بشناسند و دوباره جستجو کنند (محتوا، شبکه‌های اجتماعی، بسته‌بندی).';
}

/**
 * محتوای تب «آمار جستجو» (SEO 3.1.3: ادغام «کلمه‌های جستجو» + «روند ماه‌به‌ماه» + کارت رتبه بد موبایل از «موبایل و کشور»).
 * کالبد: ۴ کارت آمار ← نمودار کلیک ماهانه ← دو کارت هم‌ردیف (پرنمایش‌ترین کلمه‌های غیربرندی | کلمه‌های از دست رفته و تازه)
 * ← صفحه‌های با رتبه موبایل بدتر. فقط می‌خواند و دستور می‌دهد؛ چیزی را در سایت عوض نمی‌کند.
 *
 * @param array<string, mixed> $status خروجی hodima_seo_advisor_status()
 */
function hodima_seo_advisor_render_terms( array $status ): void {

	$split  = hodima_seo_advisor_brand_split();
	$trend  = hodima_seo_advisor_trend();
	$n      = static fn( int|float $value ): string => number_format_i18n( $value );
	$clicks = $split['brand']['clicks'] + $split['other']['clicks'];
	$no_terms = 0 === $split['brand']['terms'] + $split['other']['terms'];
	$no_trend = (int) ( $trend['fetched'] ?? 0 ) <= 0;
	$brand  = $clicks > 0 ? $split['brand']['clicks'] / $clicks : 0.0;
	$pct    = static fn( float $share ): string => number_format_i18n( round( $share * 100 ) ) . '٪';
	$months = (array) ( $trend['months'] ?? [] );
	$last   = null;
	foreach ( $months as $month ) {
		$last = $month['partial'] ? $last : $month;
	}
	$yoy = hodima_seo_advisor_trend_year_over_year( $months );

	hodima_seo_ui_stats( [
		[
			'label' => 'کلیک ماه کامل اخیر',
			'value' => $no_trend || null === $last ? '—' : esc_html( $n( $last['clicks'] ) ),
			'sub'   => null === $last ? '' : esc_html( (string) $last['label'] . ( null === $yoy ? '' : sprintf( ' · %1$s٪ %2$s از سال قبل', $n( abs( round( $yoy['change'] ) ) ), $yoy['change'] >= 0 ? 'بیشتر' : 'کمتر' ) ) ),
			'empty' => $no_trend || null === $last,
		],
		[ 'label' => 'سهم کلیک برندی', 'value' => $no_terms ? '—' : esc_html( $pct( $brand ) ), 'sub' => $no_terms ? '' : esc_html( sprintf( '%s کلیک', $n( $split['brand']['clicks'] ) ) ), 'empty' => $no_terms ],
		[ 'label' => 'کلمه تازه', 'value' => $no_trend ? '—' : esc_html( $n( count( (array) ( $trend['new'] ?? [] ) ) ) ), 'sub' => $no_trend ? '' : '۲۸ روز اخیر', 'empty' => $no_trend ],
		[ 'label' => 'کلمه از دست رفته', 'value' => $no_trend ? '—' : esc_html( $n( count( (array) ( $trend['lost'] ?? [] ) ) ) ), 'sub' => $no_trend ? '' : '۲۸ روز اخیر', 'empty' => $no_trend ],
	] );

	$device = hodima_seo_advisor_breakdown();
	$has_device = (int) ( $device['fetched'] ?? 0 ) > 0 && ! empty( $device['device'] );

	if ( $no_terms && $no_trend && ! $has_device ) {
		hodima_seo_ui_card_open( 'آمار جستجو' );
		hodima_seo_ui_empty( 'هنوز گرفته نشده', 'بعد از «گرفتن داده تازه» (و تا چند دقیقه) روند ماهانه، کلمه‌ها و کلمه‌های تازه و از دست رفته اینجا پر می‌شود.' );
		hodima_seo_ui_card_close();
		return;
	}

	if ( ! $no_trend ) {
		hodima_seo_advisor_render_trend_chart( $trend );
	}

	$cols = ! $no_terms || ! $no_trend;
	if ( $cols ) {
		echo '<div class="hodima-advisor__cols">';
	}

	if ( ! $no_terms ) {
		hodima_seo_ui_card_open( 'پرنمایش‌ترین کلمه‌های غیربرندی', sprintf( 'کلمه‌هایی که مردم بدون دانستن نام سایت جستجو می‌کنند (داده %s روز اخیر؛ رتبه میانگین همه صفحه‌ها).', $n( $status['days'] ) ) );
		?>
		<div class="hds-table-wrap" tabindex="0" role="region" aria-label="جدول کلمه‌های غیربرندی">
			<table class="widefat striped hodima-advisor__terms">
				<thead><tr><th scope="col">کلمه</th><th scope="col">نمایش</th><th scope="col">کلیک</th><th scope="col">رتبه</th></tr></thead>
				<tbody>
					<?php foreach ( array_slice( (array) $split['other_top'], 0, 8 ) as $row ) : ?>
						<tr>
							<th scope="row"><?php echo esc_html( (string) $row['term'] ); ?></th>
							<td><?php echo esc_html( $n( $row['impressions'] ) ); ?></td>
							<td><?php echo esc_html( $n( $row['clicks'] ) ); ?></td>
							<td><?php echo esc_html( number_format_i18n( $row['position'], 1 ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
		echo '<div class="hodima-advisor__advice">' . esc_html( hodima_seo_advisor_brand_reading( $brand ) ) . '</div>';
		echo '<p class="hodima-advisor__more">' . esc_html( sprintf( '«برندی» یعنی کلمه نام سایت را دارد (%1$s). گوگل کلمه‌های ناشناس را نمی‌دهد؛ این آمار از کلمه‌های ثبت‌شده است (پوشش %2$s).', implode( '، ', array_map( 'strval', (array) $split['words'] ) ), $pct( $split['coverage'] ) ) ) . '</p>';
		hodima_seo_ui_card_close();
	}

	if ( ! $no_trend ) {
		hodima_seo_advisor_render_term_changes( $trend );
	}

	if ( $cols ) {
		echo '</div>';
	}

	hodima_seo_advisor_render_mobile_gaps();

	if ( ! empty( $trend['errors'] ) ) {
		echo '<p class="hodima-advisor__more">' . esc_html( 'بعضی بخش‌ها گرفته نشد (بار بعد دوباره امتحان می‌شود): ' . implode( ' | ', array_map( 'strval', (array) $trend['errors'] ) ) ) . '</p>';
	}
}

/** برچسب فارسی علت تکراری بودن آدرس. */
function hodima_seo_advisor_variant_cause_label( string $cause ): string {
	return [ 'host' => 'دامنه یا پروتکل', 'query' => 'پارامتر آدرس', 'case' => 'حروف بزرگ', 'slash' => 'اسلش پایانی' ][ $cause ] ?? $cause;
}

/**
 * کارت «آدرس‌های تکراری یک صفحه» (پایین تب «صفحه‌های هم‌کلمه»).
 */
function hodima_seo_advisor_render_variants(): void {

	$variants = hodima_seo_advisor_variants();
	$n        = static fn( int|float $value ): string => number_format_i18n( $value );

	hodima_seo_ui_card_open( 'آدرس‌های تکراری یک صفحه', 'گوگل بعضی صفحه‌ها را با چند آدرس نشان می‌دهد (با و بی www، پارامتر، حروف بزرگ، اسلش)؛ نمایش و رتبه بین آدرس‌ها پخش می‌شود. اصلاح با ریدایرکت ۳۰۱ یا canonical است و با شما.' );

	if ( ! $variants ) {
		hodima_seo_ui_empty( 'آدرس تکراری پیدا نشد', 'در آخرین دریافت داده، هیچ صفحه‌ای با بیش از یک آدرس در گوگل نمایش نگرفته است.' );
		hodima_seo_ui_card_close();
		return;
	}

	echo '<div class="hodima-advisor__groups">';
	foreach ( $variants as $variant ) {
		?>
		<details class="hodima-advisor__group hodima-advisor__group--variant">
			<summary class="hodima-advisor__group-head">
				<strong class="hodima-advisor__group-term"><bdi><?php echo esc_html( rawurldecode( hodima_seo_advisor_url( (string) $variant['path'] ) ) ); ?></bdi></strong>
				<span class="hodima-advisor__group-meta">
					<span><?php echo esc_html( sprintf( '%s نمایش', $n( $variant['impressions'] ) ) ); ?></span>
					<span><?php echo esc_html( sprintf( '%s آدرس', $n( count( $variant['urls'] ) ) ) ); ?></span>
				</span>
				<?php foreach ( (array) $variant['causes'] as $cause ) : ?>
					<span class="hds-pill hds-pill--warn"><?php echo esc_html( hodima_seo_advisor_variant_cause_label( (string) $cause ) ); ?></span>
				<?php endforeach; ?>
			</summary>
			<div class="hodima-advisor__group-body">
				<ul class="hodima-advisor__rivals">
					<?php foreach ( (array) $variant['urls'] as $i => $url ) : ?>
						<li class="hodima-advisor__rival hodima-advisor__rival--url<?php echo 0 === $i ? ' is-winner' : ''; ?>">
							<div class="hodima-advisor__rival-name">
								<?php echo hodima_seo_advisor_url_link( (string) $url['url'], 'hodima-advisor__rival-url' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML ساخته‌شده و escape‌شده در hodima_seo_advisor_url_link ?>
								<?php if ( 0 === $i ) : ?>
									<span class="hds-pill">بیشترین نمایش</span>
								<?php endif; ?>
							</div>
							<span class="hodima-advisor__rival-stat"><?php echo esc_html( sprintf( '%s نمایش', $n( $url['impressions'] ) ) ); ?></span>
							<span class="hodima-advisor__rival-stat"><?php echo esc_html( sprintf( '%s کلیک', $n( $url['clicks'] ) ) ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
				<div class="hodima-advisor__advice"><strong>چه کنید:</strong> <?php echo esc_html( (string) $variant['advice'] ); ?></div>
			</div>
		</details>
		<?php
	}
	echo '</div>';

	hodima_seo_ui_card_close();
}
