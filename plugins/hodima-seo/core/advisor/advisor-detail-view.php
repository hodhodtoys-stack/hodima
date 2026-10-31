<?php
/**
 * ماژول «دستار سئو» — جزئیات دقیق زیر هر ایراد در صفحه «کارها» (SEO 3.3.6)
 * Path: core/advisor/advisor-detail-view.php
 *
 * به‌جای فقط یک جمله کلی: پیش‌نویس عنوان و توضیح متا (با شمار نویسه و دکمه کپی)، فهرست دقیق تصویرهای بدون alt
 * (با دکمه «ویرایش در رسانه»)، و فهرست لینک‌های خراب. فقط می‌خواند و نشان می‌دهد؛ چیزی ذخیره نمی‌شود.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** دکمه کپی متن (اسکریپت advisor-copy.js؛ بدون JS پنهان است و متن را دستی انتخاب می‌کنید). */
function hodima_seo_advisor_detail_copy( string $text, string $label ): string {

	return sprintf(
		'<button type="button" class="hodima-advisor-url__copy" data-hdsa-copy="%1$s" data-hdsa-said="متن کپی شد" aria-label="%2$s"><span class="dashicons dashicons-admin-page" aria-hidden="true"></span></button>',
		esc_attr( $text ),
		esc_attr( $label )
	);
}

/** نام فایل خوانا از آدرس تصویر (بدون پوشه و پرسش؛ فارسی رمزگشایی‌شده). */
function hodima_seo_advisor_detail_filename( string $url ): string {

	$path = (string) wp_parse_url( $url, PHP_URL_PATH );
	$name = rawurldecode( basename( $path ) );

	return '' !== $name ? $name : $url;
}

/**
 * جزئیات یک ایراد خزنده (ردیف صفحه «کارها»). کد بدون جزئیات = چیزی چاپ نمی‌شود.
 *
 * @param array<string, mixed> $item ردیف hodima_seo_advisor_overview_build (mark = کد ایراد)
 */
function hodima_seo_advisor_render_issue_detail( array $item, string $path ): void {

	$code = (string) ( $item['mark'] ?? '' );

	if ( 'crawl' !== ( $item['src'] ?? '' ) ) {
		return;
	}

	if ( in_array( $code, [ 'no_title', 'title_short', 'title_long' ], true ) ) {
		$row     = hodima_seo_advisor_crawl_row( $path );
		$current = null === $row ? '' : (string) $row['title'];
		$draft   = hodima_seo_advisor_draft_title( $path, $code, $current );
		hodima_seo_advisor_render_draft(
			'پیش‌نویس عنوان',
			$draft,
			'title_long' === $code ? 'کوتاه‌شده عنوان فعلی؛ بخوانید و اصلاح کنید.' : 'ساخته‌شده از نام صفحه، دسته و نام سایت؛ بخوانید و اصلاح کنید.',
			'no_title' === $code ? '' : $current
		);
		return;
	}

	if ( 'no_meta' === $code ) {
		hodima_seo_advisor_render_draft( 'پیش‌نویس توضیح متا', hodima_seo_advisor_draft_meta( $path ), 'ساخته‌شده از نام و متن همین صفحه؛ بخوانید و اصلاح کنید.', '' );
		return;
	}

	if ( 'img_alt' === $code ) {
		hodima_seo_advisor_render_image_list( (array) ( $item['images'] ?? [] ), (int) ( $item['total'] ?? 0 ) );
		return;
	}

	if ( in_array( $code, [ 'link_broken', 'link_redirect', 'link_noindex' ], true ) ) {
		hodima_seo_advisor_render_link_list( (array) ( $item['targets'] ?? [] ) );
	}
}

/** کادر پیش‌نویس (متن، شمار نویسه، کپی، راهنما). */
function hodima_seo_advisor_render_draft( string $label, string $draft, string $hint, string $current ): void {

	if ( '' === $draft ) {
		return;
	}
	?>
	<div class="hodima-advisor__detail">
		<div class="hodima-advisor__draft">
			<?php if ( '' !== $current ) : ?>
				<p class="hodima-advisor__draft-cur"><?php echo esc_html( sprintf( 'عنوان فعلی: %s', $current ) ); ?></p>
			<?php endif; ?>
			<div class="hodima-advisor__draft-label">
				<span><?php echo esc_html( $label ); ?></span>
				<span><?php echo esc_html( sprintf( '%s نویسه', number_format_i18n( mb_strlen( $draft ) ) ) ); ?></span>
			</div>
			<div class="hodima-advisor__draft-row">
				<p class="hodima-advisor__draft-text"><?php echo esc_html( $draft ); ?></p>
				<?php echo hodima_seo_advisor_detail_copy( $draft, 'کپی ' . $label ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML امن‌شده در hodima_seo_advisor_detail_copy ?>
			</div>
			<p class="hodima-advisor__draft-hint"><?php echo esc_html( $hint ); ?></p>
		</div>
	</div>
	<?php
}

/**
 * فهرست «کدام تصویرها؟»: تصویر کتابخانه رسانه ← «ویرایش در رسانه» (صفحه همان پیوست با فیلد «متن جایگزین»)؛
 * بیرون از کتابخانه (اسلایدر، صفحه‌ساز) ← آدرس با دکمه کپی. تا ۳ مورد باز، بیشتر بسته.
 *
 * @param list<string> $images آدرس تصویرها (تا ۸)
 * @param int          $total  شمار همه تصویرهای بدون alt صفحه
 */
function hodima_seo_advisor_render_image_list( array $images, int $total ): void {

	$images = array_values( array_filter( array_map( 'strval', $images ) ) );

	if ( ! $images ) {
		// خزش قدیمی‌تر از ۳.۳.۶: فهرست ندارد و تا خزش بعدی نمی‌آید؛ ساکت ماندن مدیر را سردرگم کرد (SEO 3.3.7)
		printf(
			'<div class="hodima-advisor__detail"><p class="hodima-advisor__draft-hint">%1$s <a href="%2$s">%3$s</a></p></div>',
			esc_html( 'فهرست تصویرها با خزش بعدی ساخته می‌شود.' ),
			esc_url( hodima_seo_advisor_page_url( [ 'tab' => 'crawl' ] ) ),
			esc_html( 'برای دیدنش همین حالا «خزش همین حالا» را بزنید.' )
		);
		return;
	}

	$more = max( 0, $total - count( $images ) );
	?>
	<div class="hodima-advisor__detail">
		<details class="hodima-advisor__more"<?php echo count( $images ) <= 3 ? ' open' : ''; ?>>
			<summary><?php echo esc_html( sprintf( 'کدام تصویرها؟ (%s)', number_format_i18n( max( $total, count( $images ) ) ) ) ); ?></summary>
			<div class="hodima-advisor__more-body">
				<?php
				foreach ( $images as $url ) {
					$id    = attachment_url_to_postid( $url );
					$edit  = $id > 0 && current_user_can( 'edit_post', $id ) ? (string) get_edit_post_link( $id, 'raw' ) : '';
					$thumb = $id > 0 ? (string) wp_get_attachment_image_url( $id, 'thumbnail' ) : '';
					$thumb = '' !== $thumb ? $thumb : $url;
					?>
					<div class="hodima-advisor__li">
						<span class="hodima-advisor__thumb"><img src="<?php echo esc_url( $thumb ); ?>" alt="" width="44" height="44" loading="lazy"></span>
						<div class="hodima-advisor__li-main">
							<span class="hodima-advisor__li-name" dir="ltr"><bdi><?php echo esc_html( $id > 0 ? hodima_seo_advisor_detail_filename( $url ) : $url ); ?></bdi></span>
							<span class="hodima-advisor__li-note">
								<?php if ( $id > 0 ) : ?>
									<?php echo esc_html( $edit ? 'در کتابخانه رسانه؛ «متن جایگزین» را همان‌جا بنویسید.' : 'در کتابخانه رسانه (اجازه ویرایش ندارید).' ); ?>
								<?php else : ?>
									<span class="hodima-advisor__li-tag">بیرون از کتابخانه</span>
									<?php echo esc_html( 'آدرس را کپی کنید و جایی که گذاشته‌اید (اسلایدر، صفحه‌ساز، قالب) alt بنویسید.' ); ?>
								<?php endif; ?>
							</span>
						</div>
						<div class="hodima-advisor__li-act">
							<?php if ( '' !== $edit ) : ?>
								<a class="hds-btn hds-btn--outline hodima-advisor__act" href="<?php echo esc_url( $edit ); ?>" target="_blank" rel="noopener"><span class="dashicons dashicons-external" aria-hidden="true"></span> ویرایش در رسانه</a>
							<?php elseif ( 0 === $id ) : ?>
								<?php echo hodima_seo_advisor_detail_copy( $url, 'کپی آدرس تصویر ' . hodima_seo_advisor_detail_filename( $url ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML امن‌شده ?>
							<?php endif; ?>
						</div>
					</div>
					<?php
				}
				if ( $more > 0 ) {
					printf( '<p class="hodima-advisor__more-note">%s</p>', esc_html( sprintf( 'و %s تصویر دیگر (بعد از رفع این‌ها دوباره خزش می‌شود).', number_format_i18n( $more ) ) ) );
				}
				?>
			</div>
		</details>
	</div>
	<?php
}

/**
 * فهرست «کدام لینک‌ها؟» برای لینک به صفحه خراب/ریدایرکت/ایندکس‌نشدنی (تا ۵ مورد).
 *
 * @param list<array{path: string, note: string}> $targets
 */
function hodima_seo_advisor_render_link_list( array $targets ): void {

	if ( ! $targets ) {
		return;
	}

	$shown = array_slice( $targets, 0, 5 );
	?>
	<div class="hodima-advisor__detail">
		<details class="hodima-advisor__more"<?php echo count( $targets ) <= 3 ? ' open' : ''; ?>>
			<summary><?php echo esc_html( sprintf( 'کدام لینک‌ها؟ (%s)', number_format_i18n( count( $targets ) ) ) ); ?></summary>
			<div class="hodima-advisor__more-body">
				<?php foreach ( $shown as $target ) : ?>
					<div class="hodima-advisor__li">
						<div class="hodima-advisor__li-main">
							<span class="hodima-advisor__li-name"><?php echo hodima_seo_advisor_url_copy( (string) $target['path'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML امن‌شده در hodima_seo_advisor_url_copy ?></span>
							<?php if ( '' !== (string) $target['note'] ) : ?>
								<span class="hodima-advisor__li-note"><?php echo esc_html( (string) $target['note'] ); ?></span>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>
				<?php if ( count( $targets ) > count( $shown ) ) : ?>
					<p class="hodima-advisor__more-note"><?php echo esc_html( sprintf( 'و %s لینک دیگر', number_format_i18n( count( $targets ) - count( $shown ) ) ) ); ?></p>
				<?php endif; ?>
			</div>
		</details>
	</div>
	<?php
}
