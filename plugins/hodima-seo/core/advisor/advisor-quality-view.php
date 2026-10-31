<?php
/**
 * ماژول «دستار سئو» — نمایش کیفیت داده (SEO 3.2.1)
 * Path: core/advisor/advisor-quality-view.php
 *
 * طرح تأییدشده کاربر (۹ آبان ۱۴۰۵): نوار فشرده زیر «گرفتن داده تازه» فقط روی تب‌های «پیشنهادها» و «ایرادهای فنی»؛
 * یک نتیجه («کامل» یا «کامل نیست (N مورد)») و سه نشان (داده گوگل، پوشش تحلیل، پوشش خزش) هر کدام کامل/ناقص/نامعلوم.
 * کامل = یک خط ساکت؛ ناقص = برای هر مورد یک جمله «چرا و چه کنم». جای نوار قدیمی «بخشی از داده بریده شده است».
 * همه نامعلوم (هنوز داده‌ای نیست) = هیچ نواری. محاسبه در advisor-quality.php.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** تب‌هایی که نوار کیفیت دارند. */
const HODIMA_SEO_ADVISOR_QUALITY_TABS = [ 'opportunities', 'crawl' ];

/**
 * نوار «کیفیت داده».
 */
function hodima_seo_advisor_render_quality( string $tab ): void {

	if ( ! in_array( $tab, HODIMA_SEO_ADVISOR_QUALITY_TABS, true ) ) {
		return;
	}

	$quality = hodima_seo_advisor_quality();

	if ( 'unknown' === $quality['level'] ) {
		return;
	}

	$pills = [
		'ok'      => [ 'کامل', 'hds-pill' ],
		'warn'    => [ 'ناقص', 'hds-pill hds-pill--warn' ],
		'unknown' => [ 'نامعلوم', 'hds-pill hds-pill--info' ],
	];
	$warn  = 'warn' === $quality['level'];
	?>
	<section class="hds-card hodima-advisor__quality" data-level="<?php echo esc_attr( $quality['level'] ); ?>" aria-labelledby="hodima-advisor-quality-title">
		<div class="hodima-advisor__quality-head">
			<h2 class="hodima-advisor__quality-title" id="hodima-advisor-quality-title">کیفیت داده</h2>
			<span class="<?php echo $warn ? 'hds-pill hds-pill--warn' : 'hds-pill'; ?>">
				<?php echo esc_html( $warn ? sprintf( 'کامل نیست (%s مورد)', number_format_i18n( $quality['issues'] ) ) : 'کامل' ); ?>
			</span>
		</div>
		<ul class="hodima-advisor__quality-items">
			<?php foreach ( $quality['items'] as $item ) : ?>
				<li class="hodima-advisor__quality-item" data-level="<?php echo esc_attr( $item['level'] ); ?>">
					<span class="hodima-advisor__quality-label"><?php echo esc_html( $item['label'] ); ?></span>
					<b class="hodima-advisor__quality-value"><?php echo esc_html( $item['value'] ); ?></b>
					<span class="<?php echo esc_attr( $pills[ $item['level'] ][1] ); ?>"><?php echo esc_html( $pills[ $item['level'] ][0] ); ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
		<?php if ( $warn ) : ?>
			<ul class="hodima-advisor__quality-notes">
				<?php foreach ( $quality['items'] as $item ) : ?>
					<?php if ( '' !== $item['note'] ) : ?>
						<li><b><?php echo esc_html( $item['label'] ); ?>:</b> <?php echo esc_html( $item['note'] ); ?></li>
					<?php endif; ?>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</section>
	<?php
}
