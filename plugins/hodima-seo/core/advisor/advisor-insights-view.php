<?php
/**
 * ماژول «دستار سئو» — نمای تب «کلمه‌ها» و کارت «آدرس‌های تکراری»
 * Path: core/advisor/advisor-insights-view.php
 *
 * داده از advisor-insights.php. تب «کلمه‌ها»: ۴ کارت آمار ← کارت سهم برندی/غیربرندی (نوار دوتکه
 * با عدد) ← جدول پرنمایش‌ترین کلمه‌های غیربرندی. کارت «آدرس‌های تکراری یک صفحه» پایین تب «رقابت
 * صفحه‌ها» می‌آید (ردیف جمع‌شونده برای هر صفحه). فقط می‌خواند؛ چیزی را در سایت عوض نمی‌کند.
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
 * محتوای تب «کلمه‌ها».
 *
 * @param array<string, mixed> $status خروجی hodima_seo_advisor_status()
 */
function hodima_seo_advisor_render_terms( array $status ): void {

	$split  = hodima_seo_advisor_brand_split();
	$n      = static fn( int|float $value ): string => number_format_i18n( $value );
	$clicks = $split['brand']['clicks'] + $split['other']['clicks'];
	$empty  = 0 === $split['brand']['terms'] + $split['other']['terms'];
	$brand  = $clicks > 0 ? $split['brand']['clicks'] / $clicks : 0.0;
	$pct    = static fn( float $share ): string => number_format_i18n( round( $share * 100 ) ) . '٪';

	hodima_seo_ui_stats( [
		[ 'label' => 'سهم کلیک برندی', 'value' => $empty ? '—' : esc_html( $pct( $brand ) ), 'sub' => $empty ? '' : esc_html( sprintf( '%s کلیک', $n( $split['brand']['clicks'] ) ) ), 'empty' => $empty ],
		[ 'label' => 'سهم کلیک غیربرندی', 'value' => $empty ? '—' : esc_html( $pct( 1 - $brand ) ), 'sub' => $empty ? '' : esc_html( sprintf( '%s کلیک', $n( $split['other']['clicks'] ) ) ), 'empty' => $empty ],
		[ 'label' => 'کلمه غیربرندی', 'value' => $empty ? '—' : esc_html( $n( $split['other']['terms'] ) ), 'empty' => $empty ],
		[ 'label' => 'پوشش داده', 'value' => $empty ? '—' : esc_html( $pct( $split['coverage'] ) ), 'sub' => $empty ? '' : 'نمایش کلمه‌های ثبت‌شده از کل', 'empty' => $empty ],
	] );

	hodima_seo_advisor_render_toolbar( 'terms' );

	hodima_seo_ui_card_open( 'کلمه‌های برندی در برابر غیربرندی', sprintf( 'داده %s روز اخیر. «برندی» یعنی کلمه نام سایت را دارد (%s).', $n( $status['days'] ), implode( '، ', array_map( 'esc_html', $split['words'] ) ) ) );

	if ( $empty ) {
		hodima_seo_ui_empty( 'کلمه‌ای ثبت نشده', 'بعد از دریافت داده Search Console این بخش پر می‌شود.' );
		hodima_seo_ui_card_close();
		return;
	}

	printf(
		'<div class="hodima-advisor__split" role="img" aria-label="%1$s"><span class="hodima-advisor__split-brand" style="inline-size: %2$d%%"></span><span class="hodima-advisor__split-other"></span></div>',
		esc_attr( sprintf( 'کلیک برندی %1$s، غیربرندی %2$s', $pct( $brand ), $pct( 1 - $brand ) ) ),
		(int) round( $brand * 100 )
	);
	echo '<p class="hodima-advisor__split-legend"><span><i class="hodima-advisor__dot hodima-advisor__dot--brand"></i> برندی ' . esc_html( $pct( $brand ) ) . '</span><span><i class="hodima-advisor__dot"></i> غیربرندی ' . esc_html( $pct( 1 - $brand ) ) . '</span></p>';
	echo '<div class="hodima-advisor__advice">' . esc_html( hodima_seo_advisor_brand_reading( $brand ) ) . '</div>';
	echo '<p class="hodima-advisor__more">' . esc_html( 'جدول کلمه‌ها برای هر صفحه حداکثر ۵۰ کلمه با دست‌کم ۵ نمایش را نگه می‌دارد و گوگل کلمه‌های ناشناس را نمی‌دهد؛ پس این سهم‌ها از کلمه‌های ثبت‌شده است (پوشش بالا).' ) . '</p>';
	hodima_seo_ui_card_close();

	hodima_seo_ui_card_open( 'پرنمایش‌ترین کلمه‌های غیربرندی', 'کلمه‌هایی که مردم بدون دانستن نام سایت جستجو می‌کنند؛ رتبه میانگین همه صفحه‌هاست.' );
	?>
	<div class="hds-table-wrap" tabindex="0" role="region" aria-label="جدول کلمه‌های غیربرندی">
		<table class="widefat striped hodima-advisor__terms">
			<thead><tr><th scope="col">کلمه</th><th scope="col">نمایش</th><th scope="col">کلیک</th><th scope="col">نرخ کلیک</th><th scope="col">رتبه</th><th scope="col">صفحه</th></tr></thead>
			<tbody>
				<?php foreach ( $split['other_top'] as $row ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( (string) $row['term'] ); ?></th>
						<td><?php echo esc_html( $n( $row['impressions'] ) ); ?></td>
						<td><?php echo esc_html( $n( $row['clicks'] ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $row['impressions'] > 0 ? $row['clicks'] / $row['impressions'] * 100 : 0, 2 ) . '٪' ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $row['position'], 1 ) ); ?></td>
						<td><?php echo esc_html( $n( $row['pages'] ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php
	hodima_seo_ui_card_close();
}

/** برچسب فارسی علت تکراری بودن آدرس. */
function hodima_seo_advisor_variant_cause_label( string $cause ): string {
	return [ 'host' => 'دامنه یا پروتکل', 'query' => 'پارامتر آدرس', 'case' => 'حروف بزرگ', 'slash' => 'اسلش پایانی' ][ $cause ] ?? $cause;
}

/**
 * کارت «آدرس‌های تکراری یک صفحه» (پایین تب «رقابت صفحه‌ها»).
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
				<strong class="hodima-advisor__group-term"><bdi><?php echo esc_html( (string) $variant['path'] ); ?></bdi></strong>
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
								<bdi class="hodima-advisor__rival-url"><?php echo esc_html( (string) $url['url'] ); ?></bdi>
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
