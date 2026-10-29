<?php
/**
 * ماژول «دستار سئو» — نمایش «لینک‌های این کلمه» زیر هر گروه رقابت
 * Path: core/advisor/advisor-links-view.php
 *
 * نتیجه hodima_seo_advisor_links_scan() را (فقط خواندنی) به HTML escape‌شده تبدیل می‌کند: رابطه دو صفحه (والد/فرزند)،
 * «لینک‌های ورودی هر صفحه» به تفکیک منبع، سه نشان خلاصه، جدول لینک‌ها (کجا / جای ذخیره / متن / آدرس نوشته‌شده / الان
 * می‌رود به / باید برود به / ویرایش) و جدول جدای «فرصت لینک» با تکه‌ای از متن؛ ۸ ردیف اول باز و بقیه پشت «همه ردیف‌ها».
 * در موبایل جدول‌ها با CSS به کارت تبدیل می‌شوند (data-label). طرح تأییدشده: Artifact «طرح لینک‌های رقابت» (نسخه ۲).
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
 * آدرس نوشته‌شده یک لینک: آدرس کامل با کپی؛ اگر در متن نسبی نوشته شده (/207/)، همان شکل نوشته‌شده زیرش (escape‌شده).
 *
 * @param array<string, mixed> $row
 */
function hodima_seo_advisor_links_written( array $row ): string {

	$href = (string) $row['href'];

	if ( '' === $href ) {
		return '—';
	}

	$html = hodima_seo_advisor_url_copy( $href );

	// آدرس خزش همیشه مسیر است؛ فقط آدرسی که مدیر در متن/منو نسبی نوشته شکل خودش را هم نشان می‌دهد
	if ( str_starts_with( $href, '/' ) && ! str_starts_with( $href, '//' ) && empty( $row['structural'] ) && 'متن (طبق خزش)' !== (string) ( $row['source']['field'] ?? '' ) ) {
		$html .= '<small class="hodima-advisor__lk-muted">در متن «<bdi dir="ltr">' . esc_html( rawurldecode( $href ) ) . '</bdi>» نوشته شده (نسبی)</small>'; // bdi: اسلش‌های مسیر وسط جمله فارسی جابه‌جا نشوند
	}

	return $html;
}

/**
 * یک ردیف لینک واقعی (tr): کجا، جای ذخیره، متن، آدرس نوشته‌شده، الان می‌رود به، باید برود به، ویرایش.
 *
 * @param array<string, mixed> $row
 * @param array<string, mixed> $target
 */
function hodima_seo_advisor_render_links_row( array $row, array $target ): string {

	$n      = static fn( int|float $value ): string => number_format_i18n( $value );
	$source = (array) $row['source'];
	$now    = (array) $row['now'];
	$edit   = (string) ( $source['edit'] ?? '' );

	$where = '<strong>' . esc_html( (string) $source['title'] ) . '</strong>'
		. '<span class="hds-pill hds-pill--info">' . esc_html( (string) $source['label'] ) . '</span>'
		. ( $row['times'] > 1 && ! $row['structural'] ? '<small>' . esc_html( sprintf( '(%s بار)', $n( $row['times'] ) ) ) . '</small>' : '' );

	$store  = '<span class="hodima-advisor__lk-store' . ( '' !== $edit ? ' is-editable' : '' ) . '">' . esc_html( (string) $row['store'] ) . '</span>';
	$anchor = '' !== $row['anchor'] ? '<span class="hodima-advisor__lk-anchor">' . esc_html( (string) $row['anchor'] ) . '</span>' : '—';

	$current = 'broken' === $row['issue']
		? '<span class="hds-pill hds-pill--error">خراب (۴۰۴)</span>'
		: ( '' !== (string) $now['title'] ? '<strong>' . esc_html( (string) $now['title'] ) . '</strong>' : '' )
			. ( '' !== (string) $now['kind'] ? '<small class="hodima-advisor__lk-muted">' . esc_html( (string) $now['kind'] ) . '</small>' : '' )
			. ( '' !== (string) $now['note'] ? '<small class="hodima-advisor__lk-muted">' . esc_html( (string) $now['note'] ) . '</small>' : '' );

	$should = $row['structural']
		? '<span class="hodima-advisor__lk-muted">در متن قابل ویرایش نیست؛ از منو یا چیدمان قالب درست می‌شود.</span>'
		: '<span class="hodima-advisor__lk-to"><span class="hodima-advisor__lk-arrow" aria-hidden="true">←</span>' . hodima_seo_advisor_url_copy( (string) $target['path'] ) . '</span><small class="hodima-advisor__lk-muted">' . esc_html( (string) $target['title'] ) . '</small>'; // آدرس کامل با کپی (تا SEO 3.0.0 فقط مسیر)

	return '<tr>'
		. '<td data-label=""><div class="hodima-advisor__lk-src">' . $where . '</div></td>'
		. '<td data-label="جای ذخیره">' . $store . '</td>'
		. '<td data-label="متن لینک">' . $anchor . '</td>'
		. '<td data-label="آدرس نوشته‌شده">' . hodima_seo_advisor_links_written( $row ) . '</td>'
		. '<td data-label="الان می‌رود به">' . $current . '</td>'
		. '<td data-label="باید برود به">' . $should . '</td>'
		. '<td data-label="">' . ( '' !== $edit ? hodima_seo_advisor_action( $edit, 'ویرایش صفحه', 'edit' ) : '' ) . '</td>'
		. '</tr>';
}

/**
 * یک ردیف «فرصت لینک»: کجا، تکه‌ای از متن با کلمه، پیشنهاد، ویرایش.
 *
 * @param array<string, mixed> $opp
 * @param array<string, mixed> $target
 */
function hodima_seo_advisor_render_links_opp( array $opp, array $target ): string {

	$source  = (array) $opp['source'];
	$snippet = $opp['snippet'];
	$text    = is_array( $snippet )
		? '<span class="hodima-advisor__lk-snippet">' . esc_html( $snippet[0] ) . '<mark>' . esc_html( $snippet[1] ) . '</mark>' . esc_html( $snippet[2] ) . '</span>'
		: '<span class="hodima-advisor__lk-muted">کلمه در متن هست (تکه متن پیدا نشد).</span>';
	$edit    = (string) ( $source['edit'] ?? '' );

	return '<tr>'
		. '<td data-label=""><div class="hodima-advisor__lk-src"><strong>' . esc_html( (string) $source['title'] ) . '</strong><span class="hds-pill hds-pill--info">' . esc_html( (string) $source['label'] ) . '</span></div></td>'
		. '<td data-label="تکه‌ای از متن">' . $text . '</td>'
		. '<td data-label="پیشنهاد"><span class="hodima-advisor__lk-opp">همین عبارت را به این آدرس لینک کنید:</span>' . hodima_seo_advisor_url_copy( (string) $target['path'] ) . '<small class="hodima-advisor__lk-muted">' . esc_html( (string) $target['title'] ) . '</small></td>'
		. '<td data-label="">' . ( '' !== $edit ? hodima_seo_advisor_action( $edit, 'ویرایش صفحه', 'edit' ) : '' ) . '</td>'
		. '</tr>';
}

/**
 * جدول با سرستون و ردیف‌های آماده.
 *
 * @param list<string> $heads
 * @param list<string> $body ردیف‌های tr آماده
 */
function hodima_seo_advisor_render_links_table( array $heads, array $body, string $css_class = '' ): string {

	$html = '<div class="hodima-advisor__lk-wrap"><table class="hodima-advisor__lk-table ' . esc_attr( $css_class ) . '"><thead><tr>';

	foreach ( $heads as $head ) {
		$html .= '' !== $head ? '<th scope="col">' . esc_html( $head ) . '</th>' : '<th scope="col"><span class="screen-reader-text">ویرایش</span></th>';
	}

	return $html . '</tr></thead><tbody>' . implode( '', $body ) . '</tbody></table></div>';
}

/**
 * ردیف‌ها: ۸ ردیف اول باز و بقیه پشت «همه ردیف‌ها».
 *
 * @param list<string> $heads
 * @param list<string> $body
 */
function hodima_seo_advisor_render_links_paged( array $heads, array $body, string $css_class = '' ): string {

	$html = hodima_seo_advisor_render_links_table( $heads, array_slice( $body, 0, HODIMA_SEO_ADVISOR_LINKS_VISIBLE ), $css_class );
	$rest = array_slice( $body, HODIMA_SEO_ADVISOR_LINKS_VISIBLE );

	if ( $rest ) {
		$html .= '<details class="hodima-advisor__lk-more"><summary>' . esc_html( sprintf( 'همه ردیف‌ها (%s ردیف دیگر)', number_format_i18n( count( $rest ) ) ) ) . '</summary>'
			. hodima_seo_advisor_render_links_table( $heads, $rest, $css_class ) . '</details>';
	}

	return $html;
}

/**
 * جدول «لینک‌های ورودی هر صفحه» به تفکیک منبع.
 *
 * @param array<string, mixed> $scan
 */
function hodima_seo_advisor_render_links_inventory( array $scan ): string {

	$n    = static fn( int|float $value ): string => number_format_i18n( $value );
	$cell = static fn( ?int $value ): string => null === $value ? '—' : esc_html( $n( $value ) );
	$body = [];

	foreach ( (array) $scan['inventory'] as $page ) {
		$crawl  = $page['crawl'];
		$body[] = '<tr>'
			. '<td data-label="صفحه"><strong>' . esc_html( (string) $page['title'] ) . '</strong> <span class="hds-pill' . ( 'target' === $page['role'] ? '' : ' hds-pill--info' ) . '">' . ( 'target' === $page['role'] ? 'برنده' : 'رقیب' ) . '</span></td>'
			. '<td data-label="متن" class="is-num">' . $cell( (int) $page['text'] ) . '</td>'
			. '<td data-label="لینک مرتبط" class="is-num">' . $cell( (int) $page['related'] ) . '</td>'
			. '<td data-label="منو" class="is-num">' . $cell( (int) $page['menu'] ) . '</td>'
			. '<td data-label="قالب و ساختاری" class="is-num is-struct">' . $cell( null === $crawl ? null : (int) $crawl['other'] ) . '</td>'
			. '<td data-label="جمع خزش" class="is-num">' . $cell( null === $crawl ? null : (int) $crawl['total'] ) . '</td>'
			. '</tr>';
	}

	$note = $scan['has_crawl']
		? '«قالب و ساختاری»: لینک‌هایی که قالب سایت می‌سازد (منوی بزرگ، فوتر، کارت زیردسته‌ها، مسیر breadcrumb) و در متن ذخیره نیستند. با ویرایش متن عوض نمی‌شوند.'
		: 'برای دیدن لینک‌های ساختاری قالب (منوی بزرگ، فوتر، کارت‌ها) فایل خزش Screaming Frog را در تب «ایرادهای فنی» بگذارید؛ تا آن موقع فقط لینک‌های ذخیره‌شده در دیتابیس شمرده می‌شود.';

	return '<div class="hodima-advisor__lk-inv"><h4 class="hodima-advisor__lk-h">لینک‌های ورودی هر صفحه</h4>'
		. hodima_seo_advisor_render_links_table( [ 'صفحه', 'متن', 'لینک مرتبط', 'منو', 'قالب و ساختاری', 'جمع خزش' ], $body, 'is-inventory' )
		. '<p class="hodima-advisor__links-hint">' . esc_html( $note ) . '</p></div>';
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
	$target = (array) $scan['target'];
	$html   = '';

	foreach ( (array) $scan['relations'] as $relation ) {
		$html .= '<p class="hodima-advisor__lk-rel">' . esc_html( (string) $relation['text'] ) . '</p>';
	}

	$html .= hodima_seo_advisor_render_links_inventory( $scan );
	$html .= '<div class="hodima-advisor__lk-sums">';

	foreach ( [
		[ 'wrong', 'hds-pill hds-pill--error', '%s لینک به رقیب' ],
		[ 'broken', 'hds-pill hds-pill--warn', '%s لینک خراب' ],
		[ 'good', 'hds-pill', '%s لینک درست به برنده' ],
	] as [ $key, $class, $format ] ) {
		$html .= '<span class="' . esc_attr( $class ) . '">' . esc_html( sprintf( $format, $n( (int) $counts[ $key ] ) ) ) . '</span>';
	}

	$html .= '<button type="button" class="hds-btn hds-btn--outline hodima-advisor__lk-again" data-hdsa-links-run data-force="1">بررسی لینک‌ها</button></div>';

	if ( ! $scan['router'] ) {
		$html .= '<p class="hodima-advisor__links-hint">ماژول «آدرس تمیز» خاموش است؛ فقط لینک‌هایی که آدرسشان دقیقا برابر است پیدا شدند (آدرس قدیمی با پایه یا ریدایرکت دیده نمی‌شود).</p>';
	}

	if ( $scan['capped'] ) {
		$html .= '<p class="hodima-advisor__links-hint">' . esc_html( sprintf( 'فقط %s نوشته اول که کلمه یا آدرس صفحه‌ها در آن‌هاست بررسی شد؛ بقیه دیده نشد.', $n( HODIMA_SEO_ADVISOR_LINKS_MAX_POSTS ) ) ) . '</p>';
	}

	if ( $rows ) {
		$body  = array_map( static fn( array $row ): string => hodima_seo_advisor_render_links_row( $row, $target ), $rows );
		$html .= hodima_seo_advisor_render_links_paged( [ 'کجا', 'جای ذخیره', 'متن لینک', 'آدرس نوشته‌شده', 'الان می‌رود به', 'باید برود به', '' ], $body, 'is-links' );
		if ( $scan['total'] > count( $rows ) ) {
			$html .= '<p class="hodima-advisor__links-hint">' . esc_html( sprintf( 'نمایش %1$s از %2$s ردیف؛ مهم‌ترین‌ها اول است.', $n( count( $rows ) ), $n( (int) $scan['total'] ) ) ) . '</p>';
		}
	} else {
		$html .= '<p class="hodima-advisor__links-hint">' . esc_html( sprintf( 'لینکی با متن «%s» که به صفحه رقیب برود پیدا نشد. پس رقابت از لینک متنی نیست؛ عنوان، تیتر و محتوای صفحه‌ها و جدول «لینک‌های ورودی» بالا را ببینید.', (string) $scan['term'] ) ) . '</p>';
	}

	$opps = (array) $scan['opps'];
	if ( $opps ) {
		$body  = array_map( static fn( array $opp ): string => hodima_seo_advisor_render_links_opp( $opp, $target ), $opps );
		$html .= '<div class="hodima-advisor__lk-opps"><h4 class="hodima-advisor__lk-h">فرصت لینک: کلمه در متن هست ولی لینک ندارد <span class="hds-pill hds-pill--info">' . esc_html( $n( (int) $counts['missing'] ) ) . '</span></h4>'
			. hodima_seo_advisor_render_links_paged( [ 'کجا', 'تکه‌ای از متن', 'پیشنهاد', '' ], $body, 'is-opps' );
		if ( (int) $counts['missing'] > count( $opps ) ) {
			$html .= '<p class="hodima-advisor__links-hint">' . esc_html( sprintf( 'نمایش %1$s از %2$s مورد؛ پرنمایش‌ترین صفحه‌ها اول است.', $n( count( $opps ) ), $n( (int) $counts['missing'] ) ) ) . '</p>';
		}
		$html .= '</div>';
	}

	return $html;
}
