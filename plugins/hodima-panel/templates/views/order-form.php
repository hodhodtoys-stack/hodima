<?php
/**
 * فرم پیش‌خرید و پیش‌فاکتور: جستجو، کارت محصول با شمارنده، نوار جمع ثابت (طرح تأییدشده، مرحله ۵).
 *
 * نام فیلدها همان قبلی (products[]، qty[شناسه]، order_id) و Orders::handle_form بررسی سرور را دارد؛
 * جاوااسکریپت فقط شمارنده، جستجو، جمع زنده و اصلاح تعداد را اضافه می‌کند.
 *
 * @package Hodima\Panel
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

use Hodima\Panel\Orders;
use Hodima\Panel\Page;
use Hodima\Panel\View;
use function Hodima\Panel\table;

global $wpdb;
$hodima_user_id  = get_current_user_id();
$hodima_type     = 'preinvoice' === Page::section() ? 'preinvoice' : 'prebuy';
$hodima_label    = 'preinvoice' === $hodima_type ? 'پیش‌فاکتور' : 'پیش‌خرید';
$hodima_order_id = absint( $_GET['edit_order'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط انتخاب نما.
$hodima_qty      = [];
$hodima_locked   = false;
if ( $hodima_order_id > 0 ) {
	$hodima_owned = Orders::owned( $hodima_order_id, $hodima_user_id );
	if ( $hodima_owned && ! Orders::can_edit( $hodima_owned ) ) {
		$hodima_locked = true;
	} elseif ( $hodima_owned && $hodima_owned->type === $hodima_type ) {
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT product_id, quantity FROM %i WHERE order_id = %d', table( 'order_items' ), $hodima_order_id ) ) as $hodima_item ) {
			$hodima_qty[ (int) $hodima_item->product_id ] = (float) $hodima_item->quantity;
		}
	} else {
		$hodima_order_id = 0;
	}
}
// خطاهای سرور از آخرین ارسال (Orders::handle_form)
$hodima_errors = get_transient( 'hodima_panel_errors_' . $hodima_user_id );
$hodima_errors = is_array( $hodima_errors ) ? array_filter( $hodima_errors, 'is_string' ) : [];
delete_transient( 'hodima_panel_errors_' . $hodima_user_id );
// ورودی همان ارسال ردشده جای مقدار ذخیره‌شده (مشتری دوباره همه را نزند)
$hodima_draft = $hodima_locked ? null : Orders::take_draft( $hodima_user_id, $hodima_type, $hodima_order_id );
if ( null !== $hodima_draft ) {
	$hodima_qty = $hodima_draft;
}

$hodima_products = [];
if ( ! $hodima_locked && \Hodima\Panel\wc_active() ) {
	$hodima_query    = new WP_Query(
		[
			'post_type'              => 'product',
			'post_status'            => 'publish',
			'posts_per_page'         => Orders::FORM_MAX_PRODUCTS,
			'orderby'                => 'title',
			'order'                  => 'ASC',
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_term_cache' => false, // فرم دسته/برچسب نمی‌خواند
			'meta_query'             => [ [ 'key' => '_' . $hodima_type . '_enabled', 'value' => 'yes' ] ], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		]
	);
	$hodima_products = $hodima_query->posts;
	// اقلام سفارش در حال ویرایش که بیرون از سقف فرم افتاده‌اند هم در فرم باشند (وگرنه با ذخیره حذف می‌شدند)
	$hodima_missing = array_diff( array_keys( $hodima_qty ), array_map( static fn( WP_Post $p ): int => (int) $p->ID, $hodima_products ) );
	foreach ( $hodima_missing as $hodima_pid ) {
		$hodima_post = get_post( (int) $hodima_pid );
		if ( $hodima_post instanceof WP_Post && 'product' === $hodima_post->post_type && 'publish' === $hodima_post->post_status && 'yes' === get_post_meta( $hodima_post->ID, '_' . $hodima_type . '_enabled', true ) ) {
			$hodima_products[] = $hodima_post;
		}
	}
	// عکس همه کارت‌ها با یک کوئری (تا 0.2.3 یک کوئری پیوست برای هر محصول)
	update_post_thumbnail_cache( $hodima_query );
}
// فقط محصولی که پنل از آن سفارش می‌گیرد (قیمت دار، تک‌مدل)؛ همان قاعده Orders::validate_items
$hodima_prices = [];
foreach ( $hodima_products as $hodima_i => $hodima_post ) {
	$hodima_price = Orders::price_of( wc_get_product( (int) $hodima_post->ID ) );
	if ( null === $hodima_price ) {
		unset( $hodima_products[ $hodima_i ] );
		continue;
	}
	$hodima_prices[ (int) $hodima_post->ID ] = $hodima_price;
}
// اقلام سفارش در حال ویرایش که دیگر سفارش‌پذیر نیستند: تا 0.2.2 بی‌صدا از فرم و با ذخیره از سفارش حذف می‌شدند
$hodima_gone = [];
foreach ( array_keys( $hodima_qty ) as $hodima_pid ) {
	if ( ! isset( $hodima_prices[ $hodima_pid ] ) ) {
		$hodima_gone[] = get_the_title( $hodima_pid );
	}
}
?>
<header class="hpn-head hpn-head--row">
	<div>
		<h1 class="hpn-title"><?php echo esc_html( $hodima_order_id ? 'ویرایش ' . $hodima_label . ' #' . number_format_i18n( $hodima_order_id ) : 'ثبت ' . $hodima_label ); ?></h1>
		<p class="hpn-lead">تعداد هر محصول را بزنید؛ مبلغ همین‌جا حساب می‌شود و پشتیبانی پیش‌فاکتور را می‌فرستد.</p>
	</div>
	<?php if ( count( $hodima_products ) > 6 ) : ?>
		<div class="hpn-search" hidden data-hpn-search-wrap>
			<label class="hpn-label" for="hpn-search">جستجوی محصول</label>
			<input class="hpn-input" type="search" id="hpn-search" placeholder="نام محصول…" data-hpn-search>
		</div>
	<?php endif; ?>
</header>

<?php if ( $hodima_errors ) : ?>
	<div class="hpn-notice hpn-notice--error" role="alert">
		<?php echo View::icon( 'alert', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG ثابت. ?>
		<ul>
			<?php foreach ( $hodima_errors as $hodima_error ) : ?>
				<li><?php echo esc_html( $hodima_error ); ?></li>
			<?php endforeach; ?>
		</ul>
	</div>
<?php endif; ?>

<?php if ( $hodima_gone && ! $hodima_locked ) : ?>
	<div class="hpn-notice hpn-notice--error" role="alert">
		<?php echo View::icon( 'alert', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG ثابت. ?>
		<span><?php echo esc_html( 'این اقلام دیگر برای ' . $hodima_label . ' فعال نیستند و با ذخیره از سفارش حذف می‌شوند: ' . implode( '، ', $hodima_gone ) . '. اگر لازمشان دارید پیش از ذخیره با پشتیبانی گفتگو کنید.' ); ?></span>
	</div>
<?php endif; ?>

<?php if ( $hodima_locked ) : ?>
	<p class="hpn-card hpn-empty">این سفارش در حال بررسی است و دیگر قابل ویرایش نیست. برای تغییر، در «سفارش‌ها» از دکمه «گفتگو با پشتیبانی» پیام بدهید.</p>
<?php elseif ( ! $hodima_products ) : ?>
	<p class="hpn-card hpn-empty"><?php echo esc_html( 'فعلا محصولی برای ' . $hodima_label . ' فعال نشده است.' ); ?></p>
<?php else : ?>
	<form class="hpn-order-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-hpn-order-form novalidate>
		<?php wp_nonce_field( 'user_panel_action' ); ?>
		<input type="hidden" name="action" value="<?php echo esc_attr( 'save_' . $hodima_type ); ?>">
		<?php if ( $hodima_order_id ) : ?>
			<input type="hidden" name="order_id" value="<?php echo esc_attr( (string) $hodima_order_id ); ?>">
		<?php endif; ?>

		<ul class="hpn-products">
			<?php foreach ( $hodima_products as $hodima_post ) : ?>
				<?php
				$hodima_pid      = (int) $hodima_post->ID;
				$hodima_rules    = Orders::product_rules( $hodima_pid );
				$hodima_price    = $hodima_prices[ $hodima_pid ];
				$hodima_selected = isset( $hodima_qty[ $hodima_pid ] );
				$hodima_value    = $hodima_qty[ $hodima_pid ] ?? $hodima_rules['min'];
				$hodima_step     = $hodima_rules['multiple'] ? $hodima_rules['min'] : 1;
				$hodima_title    = get_the_title( $hodima_post );
				$hodima_unit     = $hodima_rules['unit'];
				$hodima_spec     = 'کیلو' === $hodima_unit
					? 'به کیلو · حداقل ' . View::qty( $hodima_rules['min'] ) . ' کیلو' . ( $hodima_rules['multiple'] ? ' (مضرب ' . View::qty( $hodima_rules['min'] ) . ')' : '' ) . ' · هر کیلو ' . View::money( $hodima_price * $hodima_rules['capacity'] )
					: 'هر ' . $hodima_unit . ' ' . number_format_i18n( $hodima_rules['capacity'] ) . ' عدد · حداقل ' . View::qty( $hodima_rules['min'] ) . ' ' . $hodima_unit . ' · هر عدد ' . View::money( $hodima_price );
				$hodima_field    = 'hpn-qty-' . $hodima_pid;
				?>
				<li class="hpn-card hpn-product<?php echo $hodima_selected ? ' is-selected' : ''; ?>" data-hpn-product data-name="<?php echo esc_attr( $hodima_title ); ?>">
					<div class="hpn-product__img"><?php echo get_the_post_thumbnail( $hodima_pid, 'woocommerce_thumbnail', [ 'alt' => '', 'loading' => 'lazy' ] ) ?: '<span class="hpn-muted">بدون عکس</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- خروجی وردپرس. ?></div>
					<div class="hpn-product__info">
						<h2 class="hpn-product__title"><?php echo esc_html( $hodima_title ); ?></h2>
						<p class="hpn-muted hpn-product__spec"><?php echo esc_html( $hodima_spec ); ?></p>
					</div>
					<div class="hpn-product__buy">
						<label class="hpn-pick">
							<input type="checkbox" name="products[]" value="<?php echo esc_attr( (string) $hodima_pid ); ?>"<?php checked( $hodima_selected ); ?> data-hpn-pick>
							<span class="hpn-pick__on">در سفارش</span><span class="hpn-pick__off">افزودن به سفارش</span>
							<span class="screen-reader-text"><?php echo esc_html( $hodima_title ); ?></span>
						</label>
						<div class="hpn-stepper" data-hpn-stepper>
							<button type="button" class="hpn-stepper__btn" data-hpn-step="1" aria-label="<?php echo esc_attr( 'یکی بیشتر: ' . $hodima_title ); ?>" hidden>+</button>
							<label class="screen-reader-text" for="<?php echo esc_attr( $hodima_field ); ?>"><?php echo esc_html( 'تعداد ' . $hodima_title . ' (' . $hodima_unit . ')' ); ?></label>
							<input class="hpn-stepper__input" type="number" inputmode="decimal" id="<?php echo esc_attr( $hodima_field ); ?>"
								name="qty[<?php echo esc_attr( (string) $hodima_pid ); ?>]"
								value="<?php echo esc_attr( Orders::format_qty( $hodima_value ) ); ?>"
								min="<?php echo esc_attr( Orders::format_qty( $hodima_rules['min'] ) ); ?>"
								step="<?php echo esc_attr( Orders::format_qty( $hodima_step ) ); ?>"
								data-price="<?php echo esc_attr( (string) $hodima_price ); ?>"
								data-capacity="<?php echo esc_attr( (string) $hodima_rules['capacity'] ); ?>"
								data-multiple="<?php echo $hodima_rules['multiple'] ? '1' : '0'; ?>">
							<span class="hpn-stepper__unit"><?php echo esc_html( $hodima_unit ); ?></span>
							<button type="button" class="hpn-stepper__btn" data-hpn-step="-1" aria-label="<?php echo esc_attr( 'یکی کمتر: ' . $hodima_title ); ?>" hidden>−</button>
						</div>
						<strong class="hpn-product__line" data-hpn-line aria-live="polite"></strong>
					</div>
					<p class="hpn-product__msg" data-hpn-msg hidden></p>
				</li>
			<?php endforeach; ?>
		</ul>
		<p class="hpn-card hpn-empty" data-hpn-no-match hidden>محصولی با این نام پیدا نشد.</p>

		<div class="hpn-bar" role="region" aria-label="جمع سفارش">
			<div class="hpn-bar__sum">
				<span class="hpn-muted" data-hpn-count>محصولی انتخاب نشده</span>
				<strong data-hpn-total aria-live="polite"></strong>
			</div>
			<span class="hpn-muted hpn-bar__note">مبلغ نهایی را پیش‌فاکتور پشتیبانی تعیین می‌کند.</span>
			<button class="hpn-btn hpn-btn--lg" type="submit"><?php echo esc_html( ( $hodima_order_id ? 'ذخیره ' : 'ثبت ' ) . $hodima_label ); ?></button>
		</div>
	</form>
<?php endif; ?>
