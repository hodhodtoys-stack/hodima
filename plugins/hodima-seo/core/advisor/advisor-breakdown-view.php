<?php
/**
 * ماژول «دستار سئو» — کارت «صفحه‌هایی که در موبایل بدتر رتبه می‌گیرند»
 * Path: core/advisor/advisor-breakdown-view.php
 *
 * داده از advisor-breakdown.php (فقط گزارش صفحه × دستگاه). تا SEO 3.1.2 تب «موبایل و کشور» بود (سهم دستگاه، کشورها،
 * جستجوی تصویر و ویدیو، نوع نمایش)؛ از ۳.۱.۳ آن تب حذف شد (سایت یک بازار دارد و بقیه فقط عدد اطلاعاتی بودند) و تنها
 * بخش کاربردی‌اش، همین کارت، پایین تب «آمار جستجو» (advisor-insights-view.php) می‌آید. فقط می‌خواند؛ چیزی را در سایت عوض نمی‌کند.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** عنوان صفحه از جدول مشاور، وگرنه خود آدرس. */
function hodima_seo_advisor_breakdown_page_title( string $path ): string {

	$row = hodima_seo_advisor_page( $path );

	if ( null !== $row ) {
		$title = hodima_seo_advisor_row_title( $row );
		if ( '' !== $title ) {
			return $title;
		}
	}

	return $path;
}

/**
 * کارت صفحه‌هایی که رتبه موبایلشان دست‌کم ۳ پله بدتر از دسکتاپ است (با ≥ ۵۰۰ نمایش موبایل). دریافت دستگاه هنوز نیامده:
 * کارت نمی‌آید (بالای تب خودش «گرفتن داده تازه» را می‌گوید).
 */
function hodima_seo_advisor_render_mobile_gaps(): void {

	$data = hodima_seo_advisor_breakdown();

	if ( (int) ( $data['fetched'] ?? 0 ) <= 0 || empty( $data['device'] ) ) {
		return;
	}

	$gaps = array_slice( (array) ( $data['device']['gaps'] ?? [] ), 0, 8 );
	$n    = static fn( int|float $value ): string => number_format_i18n( $value );

	hodima_seo_ui_card_open( 'صفحه‌هایی که در موبایل بدتر رتبه می‌گیرند', 'رتبه موبایل دست‌کم ۳ پله بدتر از دسکتاپ.' );

	if ( ! $gaps ) {
		hodima_seo_ui_empty( 'موردی نیست', 'صفحه‌ای پیدا نشد که رتبه موبایلش به‌طور محسوس از دسکتاپ بدتر باشد.' );
		hodima_seo_ui_card_close();
		return;
	}
	?>
	<div class="hds-table-wrap" tabindex="0" role="region" aria-label="صفحه‌های با رتبه موبایل ضعیف">
		<table class="widefat striped hodima-advisor__terms">
			<thead><tr><th scope="col">صفحه</th><th scope="col">رتبه موبایل</th><th scope="col">رتبه دسکتاپ</th><th scope="col">فاصله</th><th scope="col">نمایش موبایل</th></tr></thead>
			<tbody>
				<?php foreach ( $gaps as $gap ) : ?>
					<tr>
						<th scope="row"><?php echo esc_html( hodima_seo_advisor_breakdown_page_title( (string) $gap['path'] ) ); ?> <?php echo hodima_seo_advisor_url_link( (string) $gap['path'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML ساخته‌شده و escape‌شده در hodima_seo_advisor_url_link ?></th>
						<td><?php echo esc_html( number_format_i18n( $gap['mobile']['position'], 1 ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $gap['desktop']['position'], 1 ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( $gap['diff'], 1 ) ); ?></td>
						<td><?php echo esc_html( $n( $gap['mobile']['impressions'] ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<div class="hodima-advisor__advice"><strong>چه کنید:</strong> صفحه را روی گوشی باز کنید: سرعت بارگذاری، جدول یا تصویری که در موبایل جا نمی‌شود، و دکمه‌های ریز را بررسی کنید.</div>
	<?php
	hodima_seo_ui_card_close();
}
