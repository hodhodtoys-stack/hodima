<?php
/**
 * ماژول «دستار سئو» — نمایش گام‌های دقیق پیشنهادها
 * Path: core/advisor/advisor-steps-view.php
 *
 * یک نمایش برای دو جا: جزئیات ردیف فهرست (advisor-admin.php) و کادر ویرایش صفحه (advisor-box.php).
 * داده را advisor-steps.php ساخته (کلید steps هر پیشنهاد). فقط خواندنی؛ لینک‌ها به ویرایش صفحه‌ها.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** استایل گام‌ها (همان فایل برای فهرست و کادر ویرایش؛ رنگ‌ها با مقدار پیش‌فرض چون صفحه ویرایش توکن‌های کیت را ندارد). */
function hodima_seo_advisor_enqueue_steps_css(): void {
	$file = HODIMA_SEO_DIR . '/core/advisor/advisor-steps.css';
	wp_enqueue_style( 'hodima-seo-advisor-steps', HODIMA_SEO_URL . '/core/advisor/advisor-steps.css', [], is_file( $file ) ? (string) filemtime( $file ) : HODIMA_SEO_VERSION );
}

/** لینک ویرایش یک نوشته یا ترم، یا رشته خالی. */
function hodima_seo_advisor_edit_link( string $kind, int $id ): string {
	return taxonomy_exists( $kind ) ? (string) get_edit_term_link( $id, $kind ) : (string) get_edit_post_link( $id, 'raw' );
}

/**
 * گام‌های یک پیشنهاد.
 *
 * @param list<array<string, mixed>> $steps
 */
function hodima_seo_advisor_render_steps( array $steps, bool $open = true ): void {

	if ( ! $steps ) {
		return;
	}

	$n = static fn( int|float $value, int $decimals = 0 ): string => number_format_i18n( $value, $decimals );

	// کادر ویرایش کوتاه بماند: گام‌های پیشنهاد اول باز، بقیه با یک کلیک؛ داخل ردیف فهرست همه باز
	printf( '<details class="hodima-advisor-steps__more"%s><summary>ببینید دقیقا چه کنید</summary>', $open ? ' open' : '' );

	foreach ( $steps as $step ) {
		echo '<div class="hodima-advisor-steps">';

		switch ( (string) ( $step['type'] ?? '' ) ) {

			case 'title':
				$meta = (array) $step['meta'];
				printf(
					'<h5>عنوان سئو <small>(%1$s نویسه%2$s)</small></h5><p class="hodima-advisor-steps__value">%3$s</p>',
					esc_html( $n( (int) $step['current_len'] ) ),
					$step['custom'] ? '' : '، از نام صفحه',
					esc_html( (string) $step['current'] )
				);
				if ( '' !== (string) $step['tip'] ) {
					echo '<p class="hodima-advisor-steps__tip">' . esc_html( (string) $step['tip'] ) . '</p>';
				}
				if ( '' !== (string) $step['suggested'] ) {
					printf( '<h5>عنوان پیشنهادی <small>(پیش‌نویس)</small></h5><p class="hodima-advisor-steps__value hodima-advisor-steps__value--new">%s</p>', esc_html( (string) $step['suggested'] ) );
				}
				printf(
					'<h5>توضیح متا <small>(%1$s نویسه، %2$s)</small></h5><p class="hodima-advisor-steps__value">%3$s</p>',
					esc_html( $n( (int) $meta['len'] ) ),
					$meta['custom'] ? 'دستی' : 'خودکار از متن صفحه',
					esc_html( (string) $meta['text'] )
				);
				if ( '' !== (string) $meta['tip'] ) {
					echo '<p class="hodima-advisor-steps__tip">' . esc_html( (string) $meta['tip'] ) . '</p>';
				}
				break;

			case 'headings':
				echo '<h5>تیترهای فعلی صفحه</h5>';
				if ( $step['current'] ) {
					echo '<ul class="hodima-advisor-steps__list">';
					foreach ( (array) $step['current'] as $heading ) {
						echo '<li>' . esc_html( (string) $heading ) . '</li>';
					}
					echo '</ul>';
				} else {
					echo '<p class="hodima-advisor-steps__tip">این صفحه تیتر (H2 تا H4) ندارد؛ متن را با چند تیتر بخش‌بندی کنید.</p>';
				}
				if ( $step['ideas'] ) {
					echo '<h5>تیتر (H2) پیشنهادی <small>(از کلمه‌هایی که گوگل همین صفحه را برایشان نشان می‌دهد)</small></h5><ul class="hodima-advisor-steps__list hodima-advisor-steps__list--new">';
					foreach ( (array) $step['ideas'] as $idea ) {
						echo '<li>' . esc_html( (string) $idea ) . '</li>';
					}
					echo '</ul>';
				}
				break;

			case 'links':
				if ( $step['items'] ) {
					printf( '<h5>لینک بدهید <small>(قوی‌ترین صفحه اول)</small></h5><p class="hodima-advisor-steps__tip">در متن این صفحه‌ها عبارت «%s» را پیدا کنید و همان را به این صفحه لینک بدهید:</p><ol class="hodima-advisor-steps__links">', esc_html( (string) $step['term'] ) );
					foreach ( (array) $step['items'] as $source ) {
						$edit = hodima_seo_advisor_edit_link( (string) $source['kind'], (int) $source['id'] );
						printf(
							'<li>%1$s <small>%2$s · %3$s</small></li>',
							esc_html( (string) $source['title'] ) . ( '' !== $edit ? ' <a class="button button-small" href="' . esc_url( $edit ) . '" target="_blank" rel="noopener">ویرایش این صفحه</a>' : '' ),
							esc_html( hodima_seo_advisor_kind_label( (string) $source['kind'] ) ),
							esc_html( $source['impressions'] > 0 ? sprintf( '%s نمایش', $n( (int) $source['impressions'] ) ) : 'بدون آمار گوگل' )
						);
					}
					echo '</ol>';
					if ( (int) $step['more'] > 0 ) {
						echo '<p class="hodima-advisor-steps__tip">' . esc_html( sprintf( 'و %s صفحه دیگر هم این عبارت را دارند.', $n( (int) $step['more'] ) ) ) . '</p>';
					}
				} else {
					echo '<h5>لینک بدهید</h5><p class="hodima-advisor-steps__tip">' . esc_html( sprintf( 'صفحه‌ای که متنش «%s» را داشته باشد و هنوز به این صفحه لینک نداشته باشد پیدا نشد. در متن یک صفحه مرتبط جمله‌ای با این عبارت بنویسید و به این صفحه لینک بدهید.', (string) $step['term'] ) ) . '</p>';
				}
				break;

			case 'canonical':
				printf( '<h5>صفحه‌هایی که برای «%s» نمایش می‌گیرند</h5>', esc_html( (string) $step['term'] ) );
				echo '<table class="hodima-advisor-steps__table"><thead><tr><th>صفحه</th><th>رتبه</th><th>نمایش</th><th>کلیک</th></tr></thead><tbody>';
				foreach ( (array) $step['pages'] as $page ) {
					$is_me = (string) $page['path'] === (string) ( $step['current'] ?? '' );
					$edit  = $is_me ? '' : hodima_seo_advisor_edit_link( (string) $page['kind'], (int) $page['object_id'] );
					$name  = '' !== (string) $page['title'] ? (string) $page['title'] : rawurldecode( (string) $page['path'] );
					printf(
						'<tr%1$s><td>%2$s<small dir="ltr">%3$s</small></td><td>%4$s</td><td>%5$s</td><td>%6$s</td></tr>',
						$is_me ? ' class="is-me"' : '',
						$is_me ? 'همین صفحه · ' . esc_html( $name ) : esc_html( $name ) . ( '' !== $edit ? ' <a class="button button-small" href="' . esc_url( $edit ) . '" target="_blank" rel="noopener">ویرایش</a>' : '' ),
						esc_html( rawurldecode( (string) $page['path'] ) ),
						esc_html( $n( (float) $page['position'], 1 ) ),
						esc_html( $n( (int) $page['impressions'] ) ),
						esc_html( $n( (int) $page['clicks'] ) )
					);
				}
				echo '</tbody></table>';
				echo '<p class="hodima-advisor-steps__tip hodima-advisor-steps__main">' . esc_html( $step['is_main']
					? 'صفحه مرجع پیشنهادی: همین صفحه (بهترین رتبه). صفحه‌های دیگر را با همین عبارت به آن لینک بدهید.'
					: sprintf( 'صفحه مرجع پیشنهادی (بهترین رتبه): %s. در این صفحه عبارت «%s» را موضوع اصلی نکنید و از صفحه‌های دیگر با همین عبارت به مرجع لینک بدهید.', rawurldecode( (string) $step['main'] ), (string) $step['term'] ) ) . '</p>';
				break;
		}

		echo '</div>';
	}

	echo '</details>';
}
