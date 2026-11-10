<?php
/**
 * ماژول «دستار سئو» — جزئیات دقیق زیر هر ایراد در صفحه «کارها» (SEO 3.3.6)
 * Path: core/advisor/advisor-detail-view.php
 *
 * به‌جای فقط یک جمله کلی: پیش‌نویس عنوان و توضیح متا (با عرض پیکسلی و دکمه کپی)، فهرست دقیق تصویرهای بدون alt
 * (با دکمه «ویرایش در رسانه»)، و فهرست لینک‌های خراب. فقط می‌خواند و نشان می‌دهد؛ چیزی ذخیره نمی‌شود.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

add_action( 'wp_ajax_hodima_advisor_steps', 'hodima_seo_advisor_handle_steps_ajax' );

/**
 * «جزئیات و پیش‌نویس» هر پیشنهاد گوگل در «کارها» (SEO 3.7.5؛ طرح تأییدشده کاربر «یک منبع»): عنوان فعلی و پیشنهادی، تیترها،
 * صفحه‌هایی که از آن‌ها لینک بدهید، صفحه‌های رقیب. قبلا فقط کادر ویرایش صفحه این‌ها را داشت. ساختنش گران است (جستجوی متن
 * همه صفحه‌ها برای لینک)، پس فقط وقتی مدیر بازش می‌کند و فقط برای همان یک صفحه ساخته می‌شود؛ بدون JS پیوند «نمایش جزئیات»
 * همان صفحه «کارها» را با همین یک مورد باز و ساخته‌شده نشان می‌دهد (?steps=کلید).
 */
const HODIMA_SEO_ADVISOR_STEPS_HINTS = [
	'low_ctr'          => 'عنوان فعلی، عنوان پیشنهادی، توضیح متا',
	'missing_headings' => 'تیترهای فعلی، تیتر پیشنهادی، صفحه‌هایی که از آن‌ها لینک بدهید',
	'missing_content'  => 'تیترهای فعلی، تیتر پیشنهادی، صفحه‌هایی که از آن‌ها لینک بدهید',
	'near_first'       => 'صفحه‌هایی که از آن‌ها لینک بدهید',
	'cannibal'         => 'صفحه‌های رقیب با رتبه و نمایش',
];

/** کلید کوتاه یک پیشنهاد (آدرس + قاعده + کلمه) برای پیوند بدون JS. */
function hodima_seo_advisor_steps_key( string $path, string $rule, string $term ): string {
	return substr( md5( $path . '|' . $rule . '|' . $term ), 0, 12 );
}

/**
 * گام‌های ساخت‌یافته همان پیشنهاد با تحلیل زنده همین صفحه؛ null = پیشنهاد با متن فعلی صفحه دیگر برقرار نیست.
 *
 * @return list<array<string, mixed>>|null
 */
function hodima_seo_advisor_steps_for( string $path, string $rule, string $term ): ?array {

	// رقابت: جدول رقبا مستقیم از آمار گوگل همان کلمه (SEO 3.7.8)؛ کار یکی‌شده هم‌کلمه به پیشنهاد زنده همان صفحه وابسته نیست
	if ( 'cannibal' === $rule ) {
		$rivals = hodima_seo_advisor_term_rivals( $term );
		return count( $rivals ) >= 2 ? [ hodima_seo_advisor_step_canonical( $term, $rivals, $path ) ] : null;
	}

	$analysis = hodima_seo_advisor_analyze( $path, true );

	foreach ( null === $analysis ? [] : $analysis['suggestions'] as $item ) {
		if ( $rule === (string) $item['rule'] && $term === (string) ( $item['term'] ?? '' ) ) {
			return (array) ( $item['steps'] ?? [] );
		}
	}

	return null;
}

/** محتوای جزئیات یک پیشنهاد (یا یک خط توضیح وقتی چیزی برای نشان دادن نیست). */
function hodima_seo_advisor_render_steps_result( string $path, string $rule, string $term ): void {

	$steps = hodima_seo_advisor_steps_for( $path, $rule, $term );

	if ( null === $steps ) {
		echo '<p class="hodima-advisor-steps__tip">' . esc_html( 'با متن فعلی صفحه این پیشنهاد دیگر برقرار نیست (احتمالا رفعش کرده‌اید)؛ «انجام دادم» را بزنید تا از فهرست برود.' ) . '</p>';
		return;
	}

	if ( ! $steps ) {
		echo '<p class="hodima-advisor-steps__tip">' . esc_html( 'برای این پیشنهاد جزئیات بیشتری نیست؛ همان گام‌های زیر را انجام دهید.' ) . '</p>';
		return;
	}

	hodima_seo_advisor_render_steps_body( $steps );
}

/** کادر بازشونده «جزئیات و پیش‌نویس» زیر یک پیشنهاد گوگل در «کارها»؛ فقط برای قاعده‌هایی که جزئیات دارند. */
function hodima_seo_advisor_render_steps_lazy( string $path, string $rule, string $term ): void {

	if ( ! isset( HODIMA_SEO_ADVISOR_STEPS_HINTS[ $rule ] ) ) {
		return;
	}

	$key  = hodima_seo_advisor_steps_key( $path, $rule, $term );
	$here = isset( $_GET['steps'] ) && $key === sanitize_key( (string) $_GET['steps'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط نمایش (پیوند بدون JS)
	$url  = add_query_arg( 'steps', $key, (string) wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ) . '#' . hodima_seo_advisor_row_anchor( $path ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- خروجی با esc_url

	printf(
		'<details class="hodima-advisor__lazy" data-hdsa-steps data-path="%1$s" data-rule="%2$s" data-term="%3$s"%4$s><summary><span class="hodima-advisor__lazy-title">جزئیات و پیش‌نویس</span> <span class="hodima-advisor__lazy-hint">%5$s</span></summary><div class="hodima-advisor__lazy-body" data-hdsa-steps-out aria-live="polite">',
		esc_attr( $path ),
		esc_attr( $rule ),
		esc_attr( $term ),
		$here ? ' open data-loaded="1"' : '',
		esc_html( HODIMA_SEO_ADVISOR_STEPS_HINTS[ $rule ] )
	);

	if ( $here ) {
		hodima_seo_advisor_render_steps_result( $path, $rule, $term );
	} else {
		printf( '<p class="hodima-advisor-steps__tip"><a href="%s">نمایش جزئیات</a></p>', esc_url( $url ) ); // با JS همین خط جای خودش را به محتوای ساخته‌شده می‌دهد
	}

	echo '</div></details>';
}

/** admin-ajax: محتوای «جزئیات و پیش‌نویس» یک پیشنهاد (HTML escape‌شده در سرور). */
function hodima_seo_advisor_handle_steps_ajax(): void {

	if ( ! current_user_can( 'manage_options' ) || ! check_ajax_referer( 'hodima_advisor_steps', 'nonce', false ) ) {
		wp_send_json_error( [ 'message' => 'دسترسی ندارید؛ صفحه را دوباره باز کنید.' ], 403 );
	}

	$path = isset( $_POST['path'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['path'] ) ) : '';
	$rule = isset( $_POST['rule'] ) ? sanitize_key( (string) $_POST['rule'] ) : '';
	$term = isset( $_POST['term'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['term'] ) ) : '';

	if ( '' === $path || ! isset( HODIMA_SEO_ADVISOR_STEPS_HINTS[ $rule ] ) ) {
		wp_send_json_error( [ 'message' => 'درخواست نامعتبر است.' ], 400 );
	}

	ob_start();
	hodima_seo_advisor_render_steps_result( $path, $rule, $term );
	wp_send_json_success( [ 'html' => (string) ob_get_clean() ] );
}

/** دکمه کپی متن (اسکریپت advisor-copy.js؛ بدون JS پنهان است و متن را دستی انتخاب می‌کنید). */
function hodima_seo_advisor_detail_copy( string $text, string $label ): string {

	return sprintf(
		'<button type="button" class="hodima-advisor-url__copy" data-hdsa-copy-text="%1$s" data-hdsa-said="متن کپی شد" aria-label="%2$s"><span class="dashicons dashicons-admin-page" aria-hidden="true"></span></button>',
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
			'title_long' === $code ? 'کوتاه‌شده عنوان فعلی؛ بخوانید و اصلاح کنید.' : ( 'title_short' === $code ? 'عنوان فعلی خودتان + دسته و نام سایت؛ بخوانید و اصلاح کنید.' : 'ساخته‌شده از نام صفحه، دسته و نام سایت؛ بخوانید و اصلاح کنید.' ),
			'no_title' === $code ? '' : $current
		);
		return;
	}

	if ( 'no_meta' === $code ) {
		hodima_seo_advisor_render_draft( 'پیش‌نویس توضیح متا', hodima_seo_advisor_draft_meta( $path ), 'ساخته‌شده از نام و متن همین صفحه؛ بخوانید و اصلاح کنید.', '', 'description' );
		return;
	}

	if ( 'img_alt' === $code ) {
		hodima_seo_advisor_render_image_list( (array) ( $item['images'] ?? [] ), (int) ( $item['total'] ?? 0 ), $path );
		return;
	}

	if ( 'img_empty' === $code ) {
		hodima_seo_advisor_render_image_review( (array) ( $item['images'] ?? [] ), (int) ( $item['total'] ?? 0 ), $path, (array) ( $item['marked'] ?? [] ) );
		return;
	}

	if ( in_array( $code, [ 'link_broken', 'link_redirect', 'link_noindex' ], true ) ) {
		hodima_seo_advisor_render_link_list( (array) ( $item['targets'] ?? [] ) );
	}

	// مقصد ریدایرکت (تا ۳.۶.۵ فقط «به این آدرس می‌رود:» بدون آدرس چاپ می‌شد): آدرس با دکمه کپی
	if ( in_array( $code, [ 'redirect', 'redirect_chain', 'redirect_loop', 'canonical_bad', 'canonical_external', 'canonical_variant', 'sitemap_bad' ], true ) && '' !== (string) ( $item['url'] ?? '' ) ) {
		hodima_seo_advisor_render_link_list( [ [ 'path' => (string) $item['url'], 'note' => '' ] ], [ 'redirect' => 'مقصد ریدایرکت', 'redirect_chain' => 'مقصد نهایی', 'redirect_loop' => 'ریدایرکت بعدی', 'canonical_bad' => 'آدرس پیشنهادی برای canonical', 'canonical_external' => 'آدرس canonical فعلی', 'canonical_variant' => 'آدرس canonical فعلی', 'sitemap_bad' => 'آدرسی که باید در نقشه سایت باشد' ][ $code ], 'مقصد' ); // canonical_bad و sitemap_bad از SEO 3.8.5
	}

	if ( 'near_dup' === $code ) {
		hodima_seo_advisor_render_link_list( (array) ( $item['targets'] ?? [] ), 'کدام صفحه‌ها شبیه‌اند؟', 'صفحه' );
	}

	// فهرست‌های SEO 3.7.3: صفحه‌های دیگر همان عنوان/H1/متا، لینک‌های http، صفحه‌های پیشنهادی برای لینک به صفحه بی‌لینک
	$lists = [
		'dup_title' => [ 'کدام صفحه‌ها همین عنوان را دارند؟', 'صفحه' ],
		'dup_h1'    => [ 'کدام صفحه‌ها همین تیتر را دارند؟', 'صفحه' ],
		'dup_meta'  => [ 'کدام صفحه‌ها همین توضیح متا را دارند؟', 'صفحه' ],
		'http_link' => [ 'کدام لینک‌ها؟', 'لینک' ],
		'link_no_text' => [ 'کدام لینک‌ها؟ (آدرس مقصد)', 'لینک' ],
		'link_js'      => [ 'کدام لینک‌ها؟', 'لینک' ],
		'redirect'     => [ 'کدام صفحه‌ها هنوز به این آدرس لینک داده‌اند؟', 'صفحه' ],
		'orphan'    => [ 'از این صفحه‌ها لینک بدهید (پیشنهاد)', 'صفحه' ],
	];
	if ( isset( $lists[ $code ] ) ) {
		hodima_seo_advisor_render_link_list( (array) ( $item['targets'] ?? [] ), $lists[ $code ][0], $lists[ $code ][1] );
	}
}

/** کادر پیش‌نویس (متن، عرض پیکسلی نسبت به سقف سئوباکس، کپی، راهنما). `$field`: title یا description. */
function hodima_seo_advisor_render_draft( string $label, string $draft, string $hint, string $current, string $field = 'title' ): void {

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
				<span><?php echo esc_html( sprintf( '%1$s از %2$s پیکسل', number_format_i18n( hodima_seo_text_px( $draft, $field ) ), number_format_i18n( hodima_seo_px_limit( $field )['max'] ) ) ); ?></span>
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

/** دکمه فرم «تزئینی است» / «alt می‌خواهد» / «برگرداندن» (admin-post؛ بدون شناسه تا nonce تکراری نشود). */
function hodima_seo_advisor_img_mark_form( string $url, string $mark, string $label, bool $primary = false ): string {

	return sprintf(
		'<form method="post" action="%1$s" class="hodima-advisor__img-mark"><input type="hidden" name="action" value="hodima_advisor_img_mark"><input type="hidden" name="_wpnonce" value="%2$s"><input type="hidden" name="url" value="%3$s"><input type="hidden" name="mark" value="%4$s"><button type="submit" class="%5$s">%6$s</button></form>',
		esc_url( admin_url( 'admin-post.php' ) ),
		esc_attr( wp_create_nonce( 'hodima_advisor_img_mark' ) ),
		esc_attr( $url ),
		esc_attr( $mark ),
		$primary ? 'hds-btn hds-btn--outline hodima-advisor__act hodima-advisor__img-ok' : 'hds-btn hds-btn--outline hodima-advisor__act',
		esc_html( $label )
	);
}

/**
 * برچسب فارسی وضعیت alt در HTML.
 *
 * @param array{u?: string, s: string, c?: string, need?: bool} $entry
 */
function hodima_seo_advisor_img_state_label( array $entry ): string {

	return ! empty( $entry['need'] ) ? 'alt می‌خواهد (علامت شما)' : match ( $entry['s'] ) {
		'linked' => 'لینک بی‌نام (alt خالی)',
		'empty'  => 'alt خالی',
		default  => 'در HTML: نبود ویژگی alt',
	};
}

/**
 * «چه کنم؟» هر حالت علت رفع (ردیف تصویر و کارت تصویر مشترک). حالت «page» با نام «ویرایش» و ویرایشگر همان جا (مثلا «ویرایش دسته» ←
 * ویرایشگر «توضیح») نوشته می‌شود (SEO 3.5.1).
 *
 * @param array<string, mixed> $info hodima_seo_advisor_img_fix_info
 */
function hodima_seo_advisor_img_help_text( string $kind, array $info = [] ): string {

	return match ( $kind ) {
		'library'   => 'در کتابخانه رسانه برای همین تصویر «متن جایگزین» بنویسید. چون قالب alt را از کتابخانه می‌خواند، در همه صفحه‌هایی که از این تصویر استفاده می‌کنند اثر می‌کند.',
		'page'      => sprintf(
			'«%1$s» ← در ویرایشگر «%2$s» روی تصویر کلیک کنید ← «متن جایگزین» را پر کنید ← «به‌روزرسانی» را بزنید. (alt هنگام درج تصویر همان‌جا کپی شده؛ پرکردن کتابخانه آن را عوض نمی‌کند.)',
			(string) ( $info['edit_label'] ?? 'ویرایش صفحه' ),
			(string) ( $info['field'] ?? 'متن' )
		),
		'component' => 'این تصویر در متن صفحه، توضیح دسته و خلاصه پیدا نشد؛ پس جای دیگری ساخته می‌شود (صفحه‌ساز، ویجت، تنظیمات قالب یا افزونه) و alt را از کتابخانه نمی‌خواند. اگر خودتان آنجا تصویر را گذاشته‌اید همان‌جا alt بنویسید؛ وگرنه آدرس را کپی کنید و برای توسعه‌دهنده بفرستید.',
		'external'  => 'این تصویر از کتابخانه رسانه نیست (اسلایدر، صفحه‌ساز یا CDN). آدرس را کپی کنید و همان‌جا که تصویر را گذاشته‌اید alt بنویسید.',
		default     => 'در HTML صفحه آدرسی برای این تصویر نبود؛ احتمالا با جاوااسکریپت ساخته می‌شود. از اینجا قابل رفع نیست.',
	};
}

/**
 * یک ردیف تصویر بدون alt: نام، وضعیت و علت، «کجا؟»، «چه کنم؟»، متن پیشنهادی (فقط پیشنهاد) و دکمه رفع. چیزی ذخیره نمی‌شود.
 *
 * @param array{u: string, s: string, c: string, need?: bool} $entry
 */
function hodima_seo_advisor_render_image_row( array $entry, string $path ): void {

	$info   = hodima_seo_advisor_img_fix_info( $entry, $path );
	$group  = hodima_seo_advisor_image_groups()[ $entry['u'] ] ?? null;
	$shared = null !== $group && count( $group['pages'] ) >= HODIMA_SEO_ADVISOR_BULK_MIN;
	$chips  = [
		'library'   => [ 'در کتابخانه رسانه است', 'hds-pill hds-pill--info' ],
		'page'      => [ 'alt کتابخانه پر است', 'hds-pill' ],
		'component' => [ 'از قالب یا افزونه', 'hds-pill hds-pill--warn' ],
		'external'  => [ 'بیرون از کتابخانه', 'hds-pill hds-pill--warn' ],
		'none'      => [ 'آدرسی در HTML نیست', 'hds-pill hds-pill--warn' ],
	][ $info['case'] ];
	$help   = hodima_seo_advisor_img_help_text( $info['case'], $info );
	?>
	<div class="hodima-advisor__li hodima-advisor__img-row">
		<span class="hodima-advisor__thumb"><?php if ( '' !== $info['thumb'] ) : ?><img src="<?php echo esc_url( $info['thumb'] ); ?>" alt="" width="44" height="44" loading="lazy"><?php endif; ?></span>
		<div class="hodima-advisor__li-main">
			<span class="hodima-advisor__li-name" dir="ltr"><bdi><?php echo esc_html( '' !== $info['name'] ? $info['name'] : 'بدون آدرس' ); ?></bdi></span>
			<span class="hodima-advisor__img-chips">
				<span class="hds-pill hds-pill--error"><?php echo esc_html( hodima_seo_advisor_img_state_label( $entry ) ); ?></span>
				<span class="<?php echo esc_attr( $chips[1] ); ?>"><?php echo esc_html( $chips[0] ); ?></span>
				<?php if ( $shared ) : ?>
					<span class="hds-pill hds-pill--info"><?php echo esc_html( sprintf( 'مشترک در %s صفحه', number_format_i18n( count( $group['pages'] ) ) ) ); ?></span>
				<?php endif; ?>
			</span>
			<?php if ( '' !== $info['where'] ) : ?>
				<p class="hodima-advisor__img-help"><b>کجا؟</b> <span dir="auto"><?php echo esc_html( $info['where'] ); ?></span></p>
			<?php endif; ?>
			<?php if ( '' !== $info['lib_alt'] ) : ?>
				<p class="hodima-advisor__img-help"><b>alt کتابخانه:</b> <?php echo esc_html( '«' . mb_substr( $info['lib_alt'], 0, 80 ) . '»' ); ?></p>
			<?php endif; ?>
			<p class="hodima-advisor__img-help"><b>چه کنم؟</b> <?php echo esc_html( $help ); ?></p>
			<?php if ( '' !== $info['suggest'] && 'none' !== $info['case'] ) : ?>
				<div class="hodima-advisor__img-sug">
					<span>پیشنهاد متن (بخوانید و اصلاح کنید):</span>
					<b><?php echo esc_html( '«' . $info['suggest'] . '»' ); ?></b>
					<?php echo hodima_seo_advisor_detail_copy( $info['suggest'], 'کپی متن پیشنهادی alt' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML امن‌شده در hodima_seo_advisor_detail_copy ?>
				</div>
			<?php endif; ?>
		</div>
		<div class="hodima-advisor__li-act">
			<?php if ( 'library' === $info['case'] && '' !== $info['edit'] ) : ?>
				<a class="hds-btn hds-btn--outline hodima-advisor__act" href="<?php echo esc_url( $info['edit'] ); ?>" target="_blank" rel="noopener"><span class="dashicons dashicons-external" aria-hidden="true"></span> ویرایش در رسانه</a>
			<?php elseif ( 'page' === $info['case'] && '' !== $info['page_edit'] ) : ?>
				<a class="hds-btn hds-btn--outline hodima-advisor__act" href="<?php echo esc_url( $info['page_edit'] ); ?>" target="_blank" rel="noopener"><span class="dashicons dashicons-external" aria-hidden="true"></span> <?php echo esc_html( $info['edit_label'] ); ?></a>
			<?php elseif ( in_array( $info['case'], [ 'component', 'external' ], true ) ) : ?>
				<?php echo hodima_seo_advisor_detail_copy( $info['url'], 'کپی آدرس تصویر ' . $info['name'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML امن‌شده ?>
			<?php endif; ?>
			<?php if ( ! empty( $entry['need'] ) ) : ?>
				<?php echo hodima_seo_advisor_img_mark_form( $entry['u'], 'reset', 'برگرداندن به «بررسی کنید»' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML امن‌شده ?>
			<?php endif; ?>
		</div>
	</div>
	<?php
}

/**
 * فهرست «کدام تصویرها؟» (ایراد تصویر بدون alt): هر تصویر با وضعیت، علت، جای رفع و متن پیشنهادی. تا ۳ مورد باز، بیشتر بسته.
 * فهرست خالی (خزش قدیمی‌تر از ۳.۴.۹): دکمه بازخوانی همین صفحه به‌جای پیوند به تب دیگر (SEO 3.4.9).
 *
 * @param list<array{u: string, s: string, c: string, need?: bool}> $images
 * @param int                                                       $total  شمار همه تصویرهای ایراددار صفحه
 * @param string                                                    $path   مسیر صفحه خزش‌شده
 */
function hodima_seo_advisor_render_image_list( array $images, int $total, string $path = '' ): void {

	if ( ! $images ) {
		// فهرست ندارد: خزش قدیمی‌تر از این قابلیت. تا ۳.۴.۸ پیوندی به تب «سلامت سایت» می‌داد که دکمه خزش ندارد (بن‌بست)؛ حالا
		// دکمه همان‌جا فقط همین صفحه را از سایت دوباره می‌خواند (SEO 3.4.9)
		printf(
			'<div class="hodima-advisor__detail"><div class="hodima-advisor__recrawl-box"><p class="hodima-advisor__draft-hint">%1$s</p>%2$s</div></div>',
			esc_html( 'فهرست تصویرها برای این صفحه هنوز ساخته نشده.' ),
			hodima_seo_advisor_recrawl_form( [ 'scope' => 'page', 'path' => $path, 'code' => 'img_alt' ], 'بازخوانی همین صفحه', 'فقط همین یک صفحه؛ چند ثانیه' ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML امن‌شده در recrawl_form
		);
		return;
	}

	$more = max( 0, $total - count( $images ) );
	?>
	<div class="hodima-advisor__detail">
		<details class="hodima-advisor__more"<?php echo count( $images ) <= 3 ? ' open' : ''; ?>>
			<summary><?php echo esc_html( sprintf( 'کدام تصویرها؟ (%s)', number_format_i18n( max( $total, count( $images ) ) ) ) ); ?></summary>
			<div class="hodima-advisor__more-body">
				<p class="hodima-advisor__img-note">فقط تصویرهای دیده‌شونده؛ تصویر پنجره‌های بسته (مثل نوتیفیکیشن) و بخش‌های پنهان حساب نمی‌شود.</p>
				<?php
				foreach ( $images as $entry ) {
					hodima_seo_advisor_render_image_row( $entry, $path );
				}
				if ( $more > 0 ) {
					printf( '<p class="hodima-advisor__more-note">%s</p>', esc_html( sprintf( 'و %s تصویر دیگر (بعد از رفع این‌ها با بازخوانی صفحه دیده می‌شوند).', number_format_i18n( $more ) ) ) );
				}
				?>
			</div>
		</details>
	</div>
	<?php
}

/**
 * ایراد «alt خالی (بررسی کنید)»: تصویر با `alt=""` تنها. برای تزئین درست است؛ ادمین برای هر تصویر دستی «تزئینی است» (برای همه صفحه‌ها
 * ثبت می‌شود) یا «alt می‌خواهد» (به فهرست ایرادها می‌رود) را می‌زند. فقط ادمین، خودکار نه.
 *
 * @param list<array{u: string, s: string, c: string}> $images
 * @param list<array{u: string, s: string, c: string}> $marked تصویرهای همین صفحه که ادمین «تزئینی» زده
 */
function hodima_seo_advisor_render_image_review( array $images, int $total, string $path, array $marked = [] ): void {

	if ( ! $images && ! $marked ) {
		return;
	}
	?>
	<div class="hodima-advisor__detail">
		<?php if ( $images ) : ?>
			<details class="hodima-advisor__more"<?php echo count( $images ) <= 3 ? ' open' : ''; ?>>
				<summary><?php echo esc_html( sprintf( 'کدام تصویرها؟ (%s)', number_format_i18n( max( $total, count( $images ) ) ) ) ); ?></summary>
				<div class="hodima-advisor__more-body">
					<p class="hodima-advisor__img-note">alt خالی برای لوگو، آیکون و تزئین درست است و نمره را کم نمی‌کند. اگر تصویر مطلب مهمی نشان می‌دهد (محصول، نمودار) باید alt بنویسید. «تزئینی است» برای همه صفحه‌هایی که این تصویر را دارند ثبت می‌شود.</p>
					<?php foreach ( $images as $entry ) : ?>
						<?php $thumb = '' !== $entry['u'] ? (string) wp_get_attachment_image_url( attachment_url_to_postid( $entry['u'] ), 'thumbnail' ) : ''; ?>
						<div class="hodima-advisor__li hodima-advisor__img-row">
							<span class="hodima-advisor__thumb"><?php if ( '' !== $entry['u'] ) : ?><img src="<?php echo esc_url( '' !== $thumb ? $thumb : $entry['u'] ); ?>" alt="" width="44" height="44" loading="lazy"><?php endif; ?></span>
							<div class="hodima-advisor__li-main">
								<span class="hodima-advisor__li-name" dir="ltr"><bdi><?php echo esc_html( '' !== $entry['u'] ? rawurldecode( basename( (string) wp_parse_url( $entry['u'], PHP_URL_PATH ) ) ) : 'بدون آدرس' ); ?></bdi></span>
								<?php $where = hodima_seo_advisor_img_context_label( $entry['c'] ) ?: $entry['c']; ?>
								<?php if ( '' !== $where ) : ?>
									<p class="hodima-advisor__img-help"><b>جایگاه:</b> <span dir="auto"><?php echo esc_html( $where ); ?></span></p>
								<?php endif; ?>
							</div>
							<div class="hodima-advisor__li-act hodima-advisor__img-acts">
								<?php if ( '' !== $entry['u'] ) : ?>
									<?php echo hodima_seo_advisor_img_mark_form( $entry['u'], 'ok', 'تزئینی است', true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML امن‌شده ?>
									<?php echo hodima_seo_advisor_img_mark_form( $entry['u'], 'need', 'alt می‌خواهد' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML امن‌شده ?>
								<?php else : ?>
									<span class="hodima-advisor__img-note">آدرسی در HTML نبود؛ قابل علامت‌گذاری نیست.</span>
								<?php endif; ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</details>
		<?php endif; ?>
		<?php if ( $marked ) : ?>
			<details class="hodima-advisor__more">
				<summary><?php echo esc_html( sprintf( 'تصویرهای «تزئینی» علامت‌خورده در این صفحه (%s)', number_format_i18n( count( $marked ) ) ) ); ?></summary>
				<div class="hodima-advisor__more-body">
					<?php foreach ( $marked as $entry ) : ?>
						<div class="hodima-advisor__li">
							<div class="hodima-advisor__li-main"><span class="hodima-advisor__li-name" dir="ltr"><bdi><?php echo esc_html( rawurldecode( basename( (string) wp_parse_url( $entry['u'], PHP_URL_PATH ) ) ) ); ?></bdi></span></div>
							<div class="hodima-advisor__li-act"><?php echo hodima_seo_advisor_img_mark_form( $entry['u'], 'reset', 'برگرداندن' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML امن‌شده ?></div>
						</div>
					<?php endforeach; ?>
				</div>
			</details>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * کارت «تصویرهای مشترک بدون alt» بالای فهرست «کارها»: تصویری که در ۵ صفحه یا بیشتر ایراد دارد یک بار نشان داده می‌شود (با شمار صفحه،
 * نمایش گوگل، علت، جای رفع، متن پیشنهادی)، نه N کار جدا (SEO 3.4.9). فقط نمایش؛ alt را ادمین خودش می‌نویسد.
 *
 * @param callable(int|float): string $n قالب‌دهنده عدد
 */
function hodima_seo_advisor_render_shared_images( callable $n ): void {

	$groups = array_slice( array_filter( hodima_seo_advisor_image_groups(), static fn( array $g ): bool => count( $g['pages'] ) >= HODIMA_SEO_ADVISOR_BULK_MIN ), 0, 3 );

	if ( ! $groups ) {
		return;
	}
	?>
	<section class="hds-card hodima-advisor__shared" id="hodima-advisor-shared-images" aria-labelledby="hodima-advisor-shared-title">
		<div>
			<h2 class="hds-card__title" id="hodima-advisor-shared-title">تصویرهای مشترک بدون alt</h2>
			<p class="hds-card__desc">هر تصویر یک بار شمرده می‌شود، حتی اگر در صدها صفحه باشد. یک بار درست کنید و بعد «بازخوانی» را بزنید تا دستار از خود سایت ببیند.</p>
		</div>
		<?php foreach ( $groups as $group ) : ?>
			<?php
			$entry  = [ 'u' => $group['url'], 's' => $group['state'], 'c' => $group['context'] ];
			$info   = hodima_seo_advisor_img_fix_info( $entry, (string) $group['pages'][0] );
			$key    = hodima_seo_advisor_img_key( $group['url'] );
			$count  = count( $group['pages'] );
			$cases  = [ 'library' => 'در کتابخانه رسانه است', 'page' => 'alt کتابخانه پر است', 'component' => 'از قالب یا افزونه', 'external' => 'بیرون از کتابخانه', 'none' => 'آدرسی در HTML نیست' ];
			$suggest = 'none' === $info['case'] ? '' : $info['suggest'];
			?>
			<div class="hodima-advisor__shared-item">
				<span class="hodima-advisor__shared-thumb"><?php if ( '' !== $info['thumb'] ) : ?><img src="<?php echo esc_url( $info['thumb'] ); ?>" alt="" width="96" height="96" loading="lazy"><?php endif; ?></span>
				<div class="hodima-advisor__shared-main">
					<div class="hodima-advisor__img-chips">
						<b dir="ltr"><bdi><?php echo esc_html( $info['name'] ); ?></bdi></b>
						<span class="hds-pill hds-pill--error"><?php echo esc_html( hodima_seo_advisor_img_state_label( $entry ) ); ?></span>
						<span class="hds-pill hds-pill--info"><?php echo esc_html( $cases[ $info['case'] ] ); ?></span>
					</div>
					<p class="hodima-advisor__img-help"><b>کجاست؟</b> <?php echo esc_html( sprintf( 'در %1$s صفحه (%2$s نمایش در گوگل)', $n( $count ), $n( (int) $group['impressions'] ) ) ); ?><?php echo '' !== $info['where'] ? ' · ' : ''; ?><span dir="auto"><?php echo esc_html( $info['where'] ); ?></span></p>
					<p class="hodima-advisor__img-help"><b>چه کنم؟</b> <?php echo esc_html( hodima_seo_advisor_img_help_text( $info['case'], $info ) ); ?></p>
					<?php if ( '' !== $suggest ) : ?>
						<div class="hodima-advisor__img-sug">
							<span>پیشنهاد متن (بخوانید و اصلاح کنید):</span>
							<b><?php echo esc_html( '«' . $suggest . '»' ); ?></b>
							<?php echo hodima_seo_advisor_detail_copy( $suggest, 'کپی متن پیشنهادی alt' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML امن‌شده ?>
						</div>
					<?php endif; ?>
				</div>
				<div class="hodima-advisor__shared-act">
					<?php if ( 'library' === $info['case'] && '' !== $info['edit'] ) : ?>
						<a class="hds-btn hodima-advisor__act" href="<?php echo esc_url( $info['edit'] ); ?>" target="_blank" rel="noopener"><span class="dashicons dashicons-external" aria-hidden="true"></span> ویرایش در رسانه</a>
					<?php elseif ( 'page' === $info['case'] && '' !== $info['page_edit'] ) : ?>
						<a class="hds-btn hodima-advisor__act" href="<?php echo esc_url( $info['page_edit'] ); ?>" target="_blank" rel="noopener"><span class="dashicons dashicons-external" aria-hidden="true"></span> <?php echo esc_html( $info['edit_label'] ); ?></a>
					<?php elseif ( in_array( $info['case'], [ 'component', 'external' ], true ) ) : ?>
						<?php echo hodima_seo_advisor_detail_copy( $info['url'], 'کپی آدرس تصویر ' . $info['name'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML امن‌شده ?>
					<?php endif; ?>
					<a class="hds-btn hds-btn--outline hodima-advisor__act" href="<?php echo esc_url( hodima_seo_advisor_page_url( [ 'img' => $key ] ) ); ?>"><?php echo esc_html( sprintf( 'نمایش %s صفحه', $n( $count ) ) ); ?></a>
					<?php
					echo hodima_seo_advisor_recrawl_form( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML امن‌شده
						[ 'scope' => 'img', 'img' => $key ],
						sprintf( 'بازخوانی %s صفحه', $n( $count ) ),
						sprintf( '%s؛ پیشرفت بالای صفحه', hodima_seo_advisor_crawler_duration( $count ) ),
						'running' === hodima_seo_advisor_crawler_state()['status']
					);
					?>
				</div>
			</div>
		<?php endforeach; ?>
	</section>
	<?php
}

/**
 * فهرست «کدام لینک‌ها؟» برای لینک به صفحه خراب/ریدایرکت/ایندکس‌نشدنی (تا ۵ مورد).
 *
 * @param list<array{path: string, note: string}> $targets
 */
function hodima_seo_advisor_render_link_list( array $targets, string $label = 'کدام لینک‌ها؟', string $unit = 'لینک' ): void {

	if ( ! $targets ) {
		return;
	}

	$shown = array_slice( $targets, 0, 5 );
	?>
	<div class="hodima-advisor__detail">
		<details class="hodima-advisor__more"<?php echo count( $targets ) <= 3 ? ' open' : ''; ?>>
			<summary><?php echo esc_html( sprintf( '%1$s (%2$s)', $label, number_format_i18n( count( $targets ) ) ) ); ?></summary>
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
					<p class="hodima-advisor__more-note"><?php echo esc_html( sprintf( 'و %1$s %2$s دیگر', number_format_i18n( count( $targets ) - count( $shown ) ), $unit ) ); ?></p>
				<?php endif; ?>
			</div>
		</details>
	</div>
	<?php
}
