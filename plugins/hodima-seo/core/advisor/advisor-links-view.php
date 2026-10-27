<?php
/**
 * ماژول «دستار سئو» — نمایش «لینک‌های این کلمه» زیر هر گروه رقابت
 * Path: core/advisor/advisor-links-view.php
 *
 * نتیجه hodima_seo_advisor_links_scan() را (فقط خواندنی) به HTML escape‌شده تبدیل می‌کند: سه نشان خلاصه،
 * جدول «کجا / متن لینک / الان کجا می‌رود / باید کجا برود / ویرایش»؛ ۸ ردیف اول باز و بقیه پشت «همه ردیف‌ها».
 * در موبایل جدول با CSS به کارت‌های زیر هم تبدیل می‌شود (data-label). طرح تأییدشده: Artifact «طرح لینک‌های رقابت».
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** ردیف‌هایی که بدون باز کردن «همه ردیف‌ها» دیده می‌شوند. */
const HODIMA_SEO_ADVISOR_LINKS_VISIBLE = 8;

/**
 * بلوک «لینک‌های این کلمه» داخل بدنه یک گروه رقابت (دکمه + جای نتیجه؛ نتیجه با admin-ajax می‌آید).
 * فقط برای دستورهایی که جهت لینک‌دهی روشن است (برنده روشن، دسته اصلی شود).
 */
function hodima_seo_advisor_render_links_box( string $term, string $target_title ): void {
	?>
	<div class="hodima-advisor__links" data-hdsa-links data-term="<?php echo esc_attr( $term ); ?>">
		<div class="hodima-advisor__links-head">
			<div>
				<h4 class="hodima-advisor__links-title">لینک‌های این کلمه</h4>
				<p class="hodima-advisor__links-desc"><?php echo esc_html( sprintf( 'کدام نوشته‌ها با متن «%1$s» به رقیب لینک داده‌اند و باید به «%2$s» بروند.', $term, $target_title ) ); ?></p>
			</div>
			<button type="button" class="hds-btn" data-hdsa-links-run>بررسی لینک‌ها</button>
		</div>
		<p class="hodima-advisor__links-hint" data-hdsa-links-hint>منبع‌ها: متن نوشته‌ها، برگه‌ها و محصول‌ها، توضیح دسته‌ها، منوها و تنظیمات قالب. چند ثانیه طول می‌کشد و چیزی در سایت عوض نمی‌شود.</p>
		<div class="hodima-advisor__links-out" data-hdsa-links-out aria-live="polite"></div>
	</div>
	<?php
}

/**
 * یک ردیف نتیجه (tr).
 *
 * @param array<string, mixed> $row
 * @param array<string, mixed> $target
 */
function hodima_seo_advisor_render_links_row( array $row, array $target ): string {

	$n      = static fn( int|float $value ): string => number_format_i18n( $value );
	$source = (array) $row['source'];
	$now    = (array) $row['now'];
	$code   = static fn( string $path ): string => '<code class="hodima-advisor__lk-path" dir="ltr">' . esc_html( rawurldecode( $path ) ) . '</code>';

	$where = '<strong>' . esc_html( (string) $source['title'] ) . '</strong>'
		. '<span class="hds-pill hds-pill--info">' . esc_html( (string) $source['label'] ) . '</span> '
		. '<small>' . esc_html( (string) $source['field'] ) . '</small>'
		. ( $row['times'] > 1 ? ' <small>' . esc_html( sprintf( '(%s بار)', $n( $row['times'] ) ) ) . '</small>' : '' );

	$anchor = '' !== $row['anchor'] ? '<span class="hodima-advisor__lk-anchor">' . esc_html( (string) $row['anchor'] ) . '</span>' : '—';

	$current = match ( $row['issue'] ) {
		'broken'  => '<span class="hds-pill hds-pill--error">خراب (۴۰۴)</span> ' . $code( (string) $now['path'] ),
		'missing' => '<span class="hodima-advisor__lk-muted">کلمه در متن هست، ولی لینک ندارد</span>',
		default   => $code( (string) $now['path'] ) . ( '' !== (string) $now['title'] ? '<small class="hodima-advisor__lk-muted">' . esc_html( (string) $now['title'] ) . '</small>' : '' ),
	};

	$edit = '' !== (string) $source['edit'] ? hodima_seo_advisor_action( (string) $source['edit'], 'ویرایش', 'edit' ) : '';

	return '<tr>'
		. '<td data-label=""><div class="hodima-advisor__lk-src">' . $where . '</div></td>'
		. '<td data-label="متن لینک">' . $anchor . '</td>'
		. '<td data-label="الان کجا می‌رود">' . $current . '</td>'
		. '<td data-label="باید کجا برود"><span class="hodima-advisor__lk-to"><span class="hodima-advisor__lk-arrow" aria-hidden="true">←</span>' . $code( (string) $target['path'] ) . '</span><small class="hodima-advisor__lk-muted">' . esc_html( (string) $target['title'] ) . '</small></td>'
		. '<td data-label="">' . $edit . '</td>'
		. '</tr>';
}

/**
 * جدول ردیف‌ها.
 *
 * @param list<array<string, mixed>> $rows
 * @param array<string, mixed>       $target
 */
function hodima_seo_advisor_render_links_table( array $rows, array $target ): string {

	$html = '<div class="hodima-advisor__lk-wrap"><table class="hodima-advisor__lk-table"><thead><tr>'
		. '<th scope="col">کجا</th><th scope="col">متن لینک</th><th scope="col">الان کجا می‌رود</th><th scope="col">باید کجا برود</th><th scope="col"><span class="screen-reader-text">ویرایش</span></th>'
		. '</tr></thead><tbody>';

	foreach ( $rows as $row ) {
		$html .= hodima_seo_advisor_render_links_row( $row, $target );
	}

	return $html . '</tbody></table></div>';
}

/**
 * HTML نتیجه یک بررسی (برای پاسخ admin-ajax؛ همه مقدارها escape می‌شوند).
 *
 * @param array<string, mixed> $scan خروجی hodima_seo_advisor_links_scan()
 */
function hodima_seo_advisor_render_links_result( array $scan ): string {

	$n      = static fn( int|float $value ): string => number_format_i18n( $value );
	$counts = (array) $scan['counts'];
	$rows   = (array) $scan['rows'];
	$html   = '<div class="hodima-advisor__lk-sums">';

	foreach ( [
		[ 'wrong', 'hds-pill hds-pill--error', '%s لینک به رقیب' ],
		[ 'broken', 'hds-pill hds-pill--warn', '%s لینک خراب' ],
		[ 'missing', 'hds-pill hds-pill--info', '%s جای بدون لینک' ],
		[ 'good', 'hds-pill', '%s لینک درست به برنده' ],
	] as [ $key, $class, $format ] ) {
		$html .= '<span class="' . esc_attr( $class ) . '">' . esc_html( sprintf( $format, $n( (int) $counts[ $key ] ) ) ) . '</span>';
	}

	$html .= '<button type="button" class="hds-btn hds-btn--outline hodima-advisor__lk-again" data-hdsa-links-run data-force="1">بررسی دوباره</button></div>';

	if ( ! $scan['router'] ) {
		$html .= '<p class="hodima-advisor__links-hint">ماژول «آدرس تمیز» خاموش است؛ فقط لینک‌هایی که آدرسشان دقیقا برابر است پیدا شدند (آدرس قدیمی با پایه یا ریدایرکت دیده نمی‌شود).</p>';
	}

	if ( $scan['capped'] ) {
		$html .= '<p class="hodima-advisor__links-hint">' . esc_html( sprintf( 'فقط %s نوشته اول که کلمه در آن‌هاست بررسی شد؛ بقیه دیده نشد.', $n( HODIMA_SEO_ADVISOR_LINKS_MAX_POSTS ) ) ) . '</p>';
	}

	if ( ! $rows ) {
		return $html . '<p class="hodima-advisor__links-hint">' . esc_html( sprintf( 'لینکی با متن «%s» که به صفحه رقیب برود پیدا نشد. پس رقابت از لینک‌های متنی نیست؛ عنوان، تیتر و محتوای صفحه‌ها را ببینید.', (string) $scan['term'] ) ) . '</p>';
	}

	$target = (array) $scan['target'];
	$head   = array_slice( $rows, 0, HODIMA_SEO_ADVISOR_LINKS_VISIBLE );
	$rest   = array_slice( $rows, HODIMA_SEO_ADVISOR_LINKS_VISIBLE );
	$html  .= hodima_seo_advisor_render_links_table( $head, $target );

	if ( $rest ) {
		$html .= '<details class="hodima-advisor__lk-more"><summary>' . esc_html( sprintf( 'همه ردیف‌ها (%s ردیف دیگر)', $n( count( $rest ) ) ) ) . '</summary>'
			. hodima_seo_advisor_render_links_table( $rest, $target ) . '</details>';
	}

	if ( $scan['total'] > count( $rows ) ) {
		$html .= '<p class="hodima-advisor__links-hint">' . esc_html( sprintf( 'نمایش %1$s از %2$s ردیف؛ مهم‌ترین‌ها اول است.', $n( count( $rows ) ), $n( (int) $scan['total'] ) ) ) . '</p>';
	}

	return $html;
}
