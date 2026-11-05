<?php
/**
 * داشبورد مشتری: کار بعدی، آمار، سفارش تازه، سفارش‌های اخیر (طرح تأییدشده، مرحله ۵).
 *
 * @package Hodima\Panel
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

use Hodima\Panel\Page;
use Hodima\Panel\Profile;
use Hodima\Panel\View;
use function Hodima\Panel\table;
use function Hodima\Panel\type_label;

global $wpdb;
$hodima_user_id = get_current_user_id();
$hodima_fields  = Profile::fields( wp_get_current_user() );
$hodima_name    = '' !== $hodima_fields['name'] ? strtok( $hodima_fields['name'], ' ' ) : '';
$hodima_rows    = (array) $wpdb->get_results( $wpdb->prepare( 'SELECT id, type, status, total_price, created_at FROM %i WHERE user_id = %d ORDER BY id DESC', table( 'orders' ), $hodima_user_id ) );
$hodima_groups  = [ 'review' => 0, 'wait' => 0, 'done' => 0 ];
$hodima_next    = null;
foreach ( $hodima_rows as $hodima_row ) {
	$hodima_g = View::group( (string) $hodima_row->status );
	if ( isset( $hodima_groups[ $hodima_g ] ) ) {
		++$hodima_groups[ $hodima_g ];
	}
	if ( 'wait' === $hodima_g ) {
		$hodima_next = $hodima_row; // قدیمی‌ترین سفارشِ منتظر مشتری (فهرست نزولی است)
	}
}
$hodima_titles = [];
$hodima_recent = array_slice( $hodima_rows, 0, 3 );
if ( $hodima_recent ) {
	$hodima_ids = array_map( static fn( object $o ): int => (int) $o->id, $hodima_recent );
	$hodima_in  = implode( ',', array_fill( 0, count( $hodima_ids ), '%d' ) );
	$hodima_sql = "SELECT order_id, product_id FROM %i WHERE order_id IN ($hodima_in) ORDER BY id";
	foreach ( (array) $wpdb->get_results( $wpdb->prepare( $hodima_sql, ...[ table( 'order_items' ), ...$hodima_ids ] ) ) as $hodima_item ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $hodima_in فقط %d است.
		$hodima_titles[ (int) $hodima_item->order_id ][] = get_the_title( (int) $hodima_item->product_id );
	}
}
?>
<header class="hpn-head">
	<h1 class="hpn-title"><?php echo esc_html( '' !== $hodima_name ? 'سلام ' . $hodima_name . '، خوش آمدید' : 'خوش آمدید' ); ?></h1>
	<p class="hpn-lead">وضعیت سفارش‌ها و کاری که الان منتظر شماست.</p>
</header>

<?php if ( $hodima_next ) : ?>
	<section class="hpn-card hpn-next" aria-labelledby="hpn-next-title">
		<span class="hpn-next__icon"><?php echo View::icon( 'alert', 24 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG ثابت. ?></span>
		<div class="hpn-next__body">
			<h2 class="hpn-card__title" id="hpn-next-title"><?php echo esc_html( 'پیش‌فاکتور سفارش #' . number_format_i18n( (int) $hodima_next->id ) . ' آماده است' ); ?></h2>
			<p class="hpn-muted"><?php echo esc_html( ( (float) $hodima_next->total_price > 0 ? 'مبلغ ' . View::money( (float) $hodima_next->total_price ) : 'مبلغ پیش‌فاکتور' ) . ' را واریز کنید و فیش را بفرستید تا سفارش آماده ارسال شود.' ); ?></p>
		</div>
		<a class="hpn-btn" href="<?php echo esc_url( Page::url( [ 'section' => 'orders', 'filter' => 'wait' ] ) . '#hpn-order-' . (int) $hodima_next->id ); ?>">دیدن پیش‌فاکتور و ارسال فیش</a>
	</section>
<?php endif; ?>

<ul class="hpn-stats" aria-label="خلاصه سفارش‌ها">
	<li class="hpn-stat"><span>در حال بررسی</span><strong><?php echo esc_html( number_format_i18n( $hodima_groups['review'] ) ); ?></strong></li>
	<li class="hpn-stat hpn-stat--wait"><span>منتظر پرداخت شما</span><strong><?php echo esc_html( number_format_i18n( $hodima_groups['wait'] ) ); ?></strong></li>
	<li class="hpn-stat hpn-stat--done"><span>تکمیل‌شده</span><strong><?php echo esc_html( number_format_i18n( $hodima_groups['done'] ) ); ?></strong></li>
</ul>

<div class="hpn-quick">
	<a class="hpn-card hpn-quick__item" href="<?php echo esc_url( Page::url( [ 'section' => 'prebuy' ] ) ); ?>">
		<span class="hpn-quick__icon"><?php echo View::icon( 'plus', 22 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG ثابت. ?></span>
		<span><strong>ثبت پیش‌خرید تازه</strong><span class="hpn-muted">محصولات را انتخاب کنید؛ مبلغ همان لحظه حساب می‌شود.</span></span>
	</a>
	<a class="hpn-card hpn-quick__item" href="<?php echo esc_url( Page::url( [ 'section' => 'preinvoice' ] ) ); ?>">
		<span class="hpn-quick__icon"><?php echo View::icon( 'doc', 22 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG ثابت. ?></span>
		<span><strong>درخواست پیش‌فاکتور</strong><span class="hpn-muted">پیش‌فاکتور رسمی را پشتیبانی برایتان صادر می‌کند.</span></span>
	</a>
</div>

<section class="hpn-card" aria-labelledby="hpn-recent-title">
	<div class="hpn-card__head">
		<h2 class="hpn-card__title" id="hpn-recent-title">سفارش‌های اخیر</h2>
		<?php if ( $hodima_rows ) : ?>
			<a href="<?php echo esc_url( Page::url( [ 'section' => 'orders' ] ) ); ?>">همه سفارش‌ها</a>
		<?php endif; ?>
	</div>
	<?php if ( ! $hodima_recent ) : ?>
		<p class="hpn-empty">هنوز سفارشی ندارید. از «ثبت پیش‌خرید تازه» شروع کنید.</p>
	<?php else : ?>
		<ul class="hpn-recent">
			<?php foreach ( $hodima_recent as $hodima_row ) : ?>
				<?php
				$hodima_names = $hodima_titles[ (int) $hodima_row->id ] ?? [];
				$hodima_desc  = $hodima_names ? $hodima_names[0] . ( count( $hodima_names ) > 1 ? ' و ' . number_format_i18n( count( $hodima_names ) - 1 ) . ' مورد دیگر' : '' ) : '';
				?>
				<li>
					<a class="hpn-recent__row" href="<?php echo esc_url( Page::url( [ 'section' => 'orders' ] ) . '#hpn-order-' . (int) $hodima_row->id ); ?>">
						<strong><?php echo esc_html( '#' . number_format_i18n( (int) $hodima_row->id ) ); ?></strong>
						<span class="hpn-muted"><?php echo esc_html( type_label( (string) $hodima_row->type ) . ( '' !== $hodima_desc ? ' · ' . $hodima_desc : '' ) ); ?></span>
						<span><?php echo esc_html( (float) $hodima_row->total_price > 0 ? View::money( (float) $hodima_row->total_price ) : '—' ); ?></span>
						<?php echo View::pill( (string) $hodima_row->status ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- خروجی امن. ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
</section>
