<?php
/**
 * ماژول «دستار سئو» — نمایش گام‌های دقیق پیشنهادها
 * Path: core/advisor/advisor-steps-view.php
 *
 * نمایش در کادر ویرایش صفحه (advisor-box.php). داده را advisor-steps.php همان لحظه برای همان صفحه می‌سازد
 * (کلید steps هر پیشنهاد؛ در جدول ذخیره نمی‌شود). فقط خواندنی؛ لینک‌ها به ویرایش صفحه‌ها.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * استایل گام‌ها و جزء «آدرس کامل + کپی» (همان فایل برای صفحه دستار سئو و کادر ویرایش؛ رنگ‌ها با مقدار پیش‌فرض چون صفحه
 * ویرایش توکن‌های کیت را ندارد) و اسکریپت کوچک دکمه کپی.
 */
function hodima_seo_advisor_enqueue_steps_css(): void {
	$file = HODIMA_SEO_DIR . '/core/advisor/advisor-steps.css';
	wp_enqueue_style( 'hodima-seo-advisor-steps', HODIMA_SEO_URL . '/core/advisor/advisor-steps.css', [], is_file( $file ) ? (string) filemtime( $file ) : HODIMA_SEO_VERSION );
	$script = HODIMA_SEO_DIR . '/core/advisor/advisor-copy.js';
	wp_enqueue_script( 'hodima-seo-advisor-copy', HODIMA_SEO_URL . '/core/advisor/advisor-copy.js', [], is_file( $script ) ? (string) filemtime( $script ) : HODIMA_SEO_VERSION, [ 'in_footer' => true, 'strategy' => 'defer' ] );
}

/**
 * مسیر ذخیره‌شده (/x/)، آدرس نسبی نوشته‌شده یا آدرس کامل ← آدرس کامل سایت با حروف خوانا (فارسی رمزگشایی‌شده).
 * چیزی که آدرس نیست (مثلا «#بخش») همان برمی‌گردد.
 */
function hodima_seo_advisor_url_full( string $path_or_url ): string {

	$value = trim( $path_or_url );

	if ( str_starts_with( $value, '//' ) ) {
		$value = 'https:' . $value;
	} elseif ( str_starts_with( $value, '/' ) ) {
		$value = hodima_seo_advisor_url( $value );
	}

	return rawurldecode( $value );
}

/**
 * متن نمایشی یک آدرس (escape‌شده) با جای شکستن خط بعد از هر «/» مسیر (نه داخل «https://»): در ستون باریک آدرس وسط
 * کلمه نمی‌شکند («…/20» و «7/»)؛ شکستن وسط کلمه فقط وقتی یک بخش از کل ستون بلندتر است (CSS).
 */
function hodima_seo_advisor_url_display( string $url ): string {
	return (string) preg_replace( '~(?<![/:])/(?!/)~', '/<wbr>', esc_html( $url ) );
}

/**
 * جزء «آدرس کامل + کپی» (طرح تأییدشده کاربر ۶ آبان ۱۴۰۵، Artifact «طرح آدرس کامل دستار سئو»): آدرس کامل با دامنه، قابل
 * کلیک در زبانه تازه، چپ‌به‌راست و جدا از متن فارسی، و دکمه «کپی آدرس» کنارش (۴۰px، فقط با JS دیده می‌شود؛ بدون آن آدرس
 * را می‌شود انتخاب کرد). تا SEO 3.0.0 بیشتر جاها فقط مسیر (/x/) بدون دامنه و بدون کپی نشان داده می‌شد. HTML امن‌شده.
 *
 * @param string $path_or_url مسیر یکسان‌شده، آدرس نسبی یا کامل
 * @param bool   $copy        دکمه کپی (ردیف «همین صفحه» نه)
 */
function hodima_seo_advisor_url_copy( string $path_or_url, bool $copy = true ): string {

	$url = hodima_seo_advisor_url_full( $path_or_url );

	if ( '' === $url ) {
		return '';
	}

	return sprintf(
		'<span class="hodima-advisor-url"><a class="hodima-advisor-url__link" href="%1$s" target="_blank" rel="noopener" dir="ltr"><bdi>%2$s</bdi></a>%3$s</span>',
		esc_url( $url ),
		hodima_seo_advisor_url_display( $url ),
		$copy
			? sprintf( '<button type="button" class="hodima-advisor-url__copy" data-hdsa-copy-url="%1$s" aria-label="%2$s"><span class="dashicons dashicons-admin-page" aria-hidden="true"></span></button>', esc_attr( $url ), esc_attr( sprintf( 'کپی آدرس %s', $url ) ) )
			: ''
	);
}

/**
 * متن یک پیشنهاد/دستور با آدرس‌های داخلش به‌صورت لینک چپ‌به‌راست جدا از متن (bdi). بدون این، آدرس وسط جمله فارسی
 * به‌هم می‌ریخت (اسلش آخر به اول آدرس می‌پرید). دکمه کپی وسط جمله نمی‌آید (خطوط متن به‌هم می‌خورد). HTML امن‌شده.
 */
function hodima_seo_advisor_text_html( string $text ): string {

	$parts = preg_split( '~(https?://[^\s،؛]+)~u', $text, -1, PREG_SPLIT_DELIM_CAPTURE );

	if ( false === $parts || count( $parts ) < 2 ) {
		return esc_html( $text );
	}

	$html = '';

	foreach ( $parts as $i => $part ) {
		if ( 0 === $i % 2 ) {
			$html .= esc_html( $part );
			continue;
		}
		$url   = rtrim( $part, '.,)»' ); // نقطه آخر جمله جزو آدرس نیست
		$tail  = substr( $part, strlen( $url ) );
		$html .= sprintf( '<a class="hodima-advisor-url__inline" href="%1$s" target="_blank" rel="noopener" dir="ltr"><bdi>%2$s</bdi></a>%3$s', esc_url( $url ), hodima_seo_advisor_url_display( rawurldecode( $url ) ), esc_html( $tail ) );
	}

	return $html;
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
							'<li>%1$s <small>%2$s · %3$s</small>%4$s</li>',
							esc_html( (string) $source['title'] ) . ( '' !== $edit ? ' <a class="button button-small" href="' . esc_url( $edit ) . '" target="_blank" rel="noopener">ویرایش این صفحه</a>' : '' ),
							esc_html( hodima_seo_advisor_kind_label( (string) $source['kind'] ) ),
							esc_html( $source['impressions'] > 0 ? sprintf( '%s نمایش', $n( (int) $source['impressions'] ) ) : 'بدون آمار گوگل' ),
							'' !== (string) ( $source['url'] ?? '' ) ? '<br>' . hodima_seo_advisor_url_copy( (string) $source['url'] ) : '' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML امن‌شده در hodima_seo_advisor_url_copy
						);
					}
					echo '</ol>';
					if ( '' !== (string) ( $step['target'] ?? '' ) ) {
						echo '<p class="hodima-advisor-steps__tip">آدرس این صفحه (همان که لینک می‌گیرد): ' . hodima_seo_advisor_url_copy( (string) $step['target'] ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML امن‌شده در hodima_seo_advisor_url_copy
					}
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
						'<tr%1$s><td>%2$s<br>%3$s</td><td>%4$s</td><td>%5$s</td><td>%6$s</td></tr>',
						$is_me ? ' class="is-me"' : '',
						$is_me ? 'همین صفحه · ' . esc_html( $name ) : esc_html( $name ) . ( (string) $page['path'] === (string) $step['main'] ? ' <span class="hodima-advisor-steps__main-pill">صفحه مرجع</span>' : '' ) . ( '' !== $edit ? ' <a class="button button-small" href="' . esc_url( $edit ) . '" target="_blank" rel="noopener">ویرایش</a>' : '' ),
						hodima_seo_advisor_url_copy( (string) $page['path'], ! $is_me ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML امن‌شده در hodima_seo_advisor_url_copy (آدرس کامل با کپی؛ تا SEO 3.0.0 فقط مسیر)
						esc_html( $n( (float) $page['position'], 1 ) ),
						esc_html( $n( (int) $page['impressions'] ) ),
						esc_html( $n( (int) $page['clicks'] ) )
					);
				}
				echo '</tbody></table>';
				// برنده همان تب «صفحه‌های هم‌کلمه» (بیشترین کلیک)؛ آدرس کامل با دکمه کپی
				echo '<p class="hodima-advisor-steps__tip hodima-advisor-steps__main">' . ( $step['is_main']
					? esc_html( 'صفحه مرجع پیشنهادی: همین صفحه (بیشترین کلیک برای این کلمه، همان برنده تب «صفحه‌های هم‌کلمه»). صفحه‌های دیگر را با همین عبارت به آن لینک بدهید.' )
					: esc_html( 'صفحه مرجع پیشنهادی (بیشترین کلیک، همان برنده تب «صفحه‌های هم‌کلمه»): ' ) . hodima_seo_advisor_url_copy( (string) $step['main'] ) . ' ' . esc_html( sprintf( 'در این صفحه عبارت «%s» را موضوع اصلی نکنید و از صفحه‌های دیگر با همین عبارت به مرجع لینک بدهید.', (string) $step['term'] ) ) ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- همه تکه‌ها escape شده‌اند
				break;
		}

		echo '</div>';
	}

	echo '</details>';
}
