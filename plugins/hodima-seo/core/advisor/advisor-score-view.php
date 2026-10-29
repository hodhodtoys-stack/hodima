<?php
/**
 * ماژول «دستار سئو» — کارت «سلامت سایت» بالای تب «ایرادهای فنی» (SEO 3.1.6)
 * Path: core/advisor/advisor-score-view.php
 *
 * طرح تأییدشده کاربر (۸ آبان ۱۴۰۵): نمره بزرگ با حلقه و برچسب، نمره پنج بخش (هر کدام پیوند به فیلتر همان بخش در
 * جدول ایرادها)، سه کار اول برای بالا بردن نمره و «نمره چطور حساب می‌شود؟». محاسبه در advisor-score.php.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * کارت سلامت سایت.
 *
 * @param list<array<string, mixed>> $issues  ایرادهای خزش (بدون فیلتر علامت‌ها)
 * @param int                        $crawled شمار صفحه‌های خزش‌شده
 */
function hodima_seo_advisor_render_score( array $issues, int $crawled ): void {

	$result = hodima_seo_advisor_score( $issues, $crawled );

	if ( null === $result ) {
		return;
	}

	$n       = static fn( int|float $value ): string => number_format_i18n( $value );
	$grade   = hodima_seo_advisor_score_grade( $result['score'] );
	$history = hodima_seo_advisor_score_history();
	$before  = count( $history ) >= 2 ? $history[ count( $history ) - 2 ] : null;
	$delta   = null === $before ? null : $result['score'] - $before['score'];
	$level   = static fn( int $score ): string => hodima_seo_advisor_score_grade( $score )['level'];
	$url     = static fn( string $filter ): string => hodima_seo_advisor_page_url( [ 'tab' => 'crawl', 'cf' => $filter ] );

	hodima_seo_ui_card_open( 'سلامت سایت', 'نمره از روی همه صفحه‌های خزش‌شده حساب می‌شود. هر بخش را بزنید تا ایرادهایش در فهرست پایین فیلتر شود.' );
	?>
	<div class="hodima-advisor__sc">
		<div class="hodima-advisor__sc-gauge-wrap">
			<div class="hodima-advisor__sc-gauge hodima-advisor__sc-gauge--<?php echo esc_attr( $grade['level'] ); ?>" role="img" aria-label="<?php echo esc_attr( sprintf( 'نمره %1$s از ۱۰۰؛ %2$s', $n( $result['score'] ), $grade['label'] ) ); ?>" style="--p: <?php echo esc_attr( (string) $result['score'] ); ?>">
				<span class="hodima-advisor__sc-gauge-in"><b><?php echo esc_html( $n( $result['score'] ) ); ?></b><small>از ۱۰۰</small></span>
			</div>
			<span class="hodima-advisor__sc-grade hodima-advisor__sc-grade--<?php echo esc_attr( $grade['level'] ); ?>"><?php echo esc_html( $grade['label'] ); ?></span>
			<?php if ( null !== $delta ) : ?>
				<span class="hodima-advisor__sc-delta">
					<?php
					echo esc_html(
						0 === $delta
							? sprintf( 'بدون تغییر نسبت به دور قبل (%s پیش)', human_time_diff( $before['time'] ) )
							: sprintf( '%1$s امتیاز %2$s از دور قبل (%3$s پیش)', $n( abs( $delta ) ), $delta > 0 ? 'بهتر' : 'بدتر', human_time_diff( $before['time'] ) )
					);
					?>
				</span>
			<?php endif; ?>
		</div>
		<ul class="hodima-advisor__sc-cats" aria-label="نمره بخش‌ها">
			<?php foreach ( $result['categories'] as $key => $category ) : ?>
				<li>
					<a class="hodima-advisor__sc-cat" href="<?php echo esc_url( $url( $key ) ); ?>">
						<span class="hodima-advisor__sc-cat-name"><?php echo esc_html( $category['label'] ); ?></span>
						<span class="hodima-advisor__sc-bar" aria-hidden="true"><i class="hodima-advisor__sc-bar-fill hodima-advisor__sc-bar-fill--<?php echo esc_attr( $level( $category['score'] ) ); ?>" style="inline-size: <?php echo esc_attr( (string) $category['score'] ); ?>%"></i></span>
						<span class="hodima-advisor__sc-cat-val"><b><?php echo esc_html( $n( $category['score'] ) ); ?></b> <small><?php echo esc_html( $category['pages'] > 0 ? sprintf( '· %s صفحه ←', $n( $category['pages'] ) ) : '· بدون ایراد' ); ?></small></span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
	<?php if ( $result['top'] ) : ?>
		<div class="hodima-advisor__sc-todo">
			<h4>سه کار اول برای بالا بردن نمره</h4>
			<ol>
				<?php foreach ( $result['top'] as $item ) : ?>
					<?php
					$info   = hodima_seo_advisor_crawl_issue_info( $item['code'] );
					$filter = '';
					foreach ( HODIMA_SEO_ADVISOR_CRAWL_GROUPS as $group => $codes ) {
						if ( in_array( $item['code'], $codes, true ) ) {
							$filter = $group;
							break;
						}
					}
					?>
					<li>
						<span class="hodima-advisor__dot hodima-advisor__dot--<?php echo esc_attr( 'bad' === $info['level'] ? 'bad' : 'warn' ); ?>" aria-hidden="true"></span>
						<span><?php echo esc_html( sprintf( '%1$s صفحه: %2$s', $n( $item['pages'] ), $info['label'] ) ); ?></span>
						<a href="<?php echo esc_url( $url( $filter ) ); ?>">دیدن و رفع ←</a>
					</li>
				<?php endforeach; ?>
			</ol>
		</div>
	<?php endif; ?>
	<details class="hodima-advisor__sc-how">
		<summary>نمره چطور حساب می‌شود؟</summary>
		<p>هر ایراد نمره بخش خودش را به نسبت شمار صفحه‌هایی که آن را دارند کم می‌کند: ایراد قرمز ضریب ۲، کهربایی ۱ و آبی ۰٫۳. مثلا اگر ۵٪ صفحه‌ها یک ایراد قرمز داشته باشند ۱۰ امتیاز کم می‌شود. نمره کل میانگین وزنی بخش‌هاست؛ بعد از هر دور کامل خزش ثبت می‌شود تا با دور قبل مقایسه شود.</p>
		<table>
			<thead><tr><th scope="col">بخش</th><th scope="col">وزن</th></tr></thead>
			<tbody>
				<?php foreach ( HODIMA_SEO_ADVISOR_SCORE_CATEGORIES as $category ) : ?>
					<tr><td><?php echo esc_html( $category['label'] ); ?></td><td><?php echo esc_html( $n( $category['weight'] ) . '٪' ); ?></td></tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</details>
	<?php
	hodima_seo_ui_card_close();
}
