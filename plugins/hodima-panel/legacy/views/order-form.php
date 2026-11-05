<?php
/**
 * فرم پیش‌خرید و پیش‌فاکتور (منتقل‌شده از پنل قدیمی؛ دو فایل تقریبا یکسان یکی شد).
 *
 * باقی‌مانده: بررسی حداقل سفارش و مجاز بودن محصول در سرور (مرحله ۲)، صفحه‌بندی
 * محصولات و بازطراحی (مرحله ۵).
 *
 * @package Hodima\Panel
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

use Hodima\Panel\Page;
use function Hodima\Panel\table;

global $wpdb;
$user_id   = get_current_user_id();
$form_type = 'preinvoice' === Page::section() ? 'preinvoice' : 'prebuy';
$order_id  = absint( $_GET['edit_order'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

// نام کلاس‌ها همان پنل قدیمی تا ظاهر دو فرم مثل قبل بماند
$cls = 'preinvoice' === $form_type
	? [ 'wrap' => 'preinvoice-wrapper', 'grid' => 'pinvoice-grid', 'card' => 'pinvoice-card', 'body' => 'pinvoice-body', 'title' => 'pinvoice-title', 'price' => 'pinvoice-price', 'total' => 'pinvoice-grand-total', 'submit' => 'btn-submit-pinvoice', 'form' => 'pinvoice-form' ]
	: [ 'wrap' => 'prebuy-container', 'grid' => 'product-selection-grid', 'card' => 'product-item-card', 'body' => 'card-body', 'title' => 'p-title', 'price' => 'p-price', 'total' => 'prebuy-grand-total', 'submit' => 'btn-save-order btn-submit-prebuy', 'form' => 'prebuy-form' ];
$label = 'preinvoice' === $form_type ? 'پیش‌فاکتور' : 'پیش‌خرید';

$item_quantities = [];
$locked          = false;
if ( $order_id > 0 ) {
	$owned = \Hodima\Panel\Orders::owned( $order_id, $user_id );
	if ( $owned && ! \Hodima\Panel\Orders::can_edit( $owned ) ) {
		$locked = true;
	} elseif ( $owned && $owned->type !== $form_type ) {
		// ویرایش فقط در فرم همان نوع سفارش
		$order_id = 0;
	} elseif ( $owned ) {
		foreach ( (array) $wpdb->get_results( $wpdb->prepare( 'SELECT product_id, quantity FROM %i WHERE order_id = %d', table( 'order_items' ), $order_id ) ) as $item ) {
			$item_quantities[ (int) $item->product_id ] = (float) $item->quantity;
		}
	} else {
		$order_id = 0;
	}
}

// خطاهای سرور از آخرین ارسال (Orders::handle_form)
$errors = (array) get_transient( 'hodima_panel_errors_' . $user_id );
delete_transient( 'hodima_panel_errors_' . $user_id );

$products = ! $locked && \Hodima\Panel\wc_active() ? new WP_Query(
	[
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => -1,
		'meta_query'     => [ [ 'key' => '_' . $form_type . '_enabled', 'value' => 'yes' ] ], // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
	]
) : null;
?>

<div class="<?php echo esc_attr( $cls['wrap'] ); ?> dashboard-container">
	<div class="section-header">
		<div class="header-info">
			<h2><?php echo esc_html( $order_id ? 'ویرایش ' . $label . ' #' . $order_id : 'ثبت درخواست ' . $label . ' جدید' ); ?></h2>
			<p>محصولات را انتخاب کرده تا پیش‌فاکتور صادر شود.</p>
		</div>
		<a href="<?php echo esc_url( Page::url() ); ?>" class="btn-back">بازگشت ←</a>
	</div>

	<?php if ( $errors ) : ?>
		<div class="up-form-errors" role="alert">
			<ul>
				<?php foreach ( $errors as $error ) : ?>
					<li><?php echo esc_html( (string) $error ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<?php if ( $locked ) : ?>
		<p class="no-data">این سفارش در حال بررسی است و دیگر قابل ویرایش نیست. برای تغییر، در «سفارش‌ها» از دکمه «پشتیبانی سفارش» پیام بدهید.</p>
	<?php elseif ( $products && $products->have_posts() ) : ?>
		<form method="POST" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="<?php echo esc_attr( $cls['form'] ); ?>" novalidate>
			<?php wp_nonce_field( 'user_panel_action' ); ?>
			<input type="hidden" name="action" value="<?php echo esc_attr( 'save_' . $form_type ); ?>">
			<?php if ( $order_id ) : ?><input type="hidden" name="order_id" value="<?php echo esc_attr( (string) $order_id ); ?>"><?php endif; ?>

			<div class="<?php echo esc_attr( $cls['grid'] ); ?>">
				<?php
				while ( $products->have_posts() ) :
					$products->the_post();
					$p_id    = get_the_ID();
					$product = wc_get_product( $p_id );
					if ( ! $product ) {
						continue;
					}
					$is_checked      = isset( $item_quantities[ $p_id ] );
					$base_price      = (float) $product->get_price();
					// همان قانونی که سرور می‌سنجد (Orders::validate_items)
					$rules           = \Hodima\Panel\Orders::product_rules( $p_id );
					$unit_capacity   = $rules['capacity'];
					$min_qty         = $rules['min'];
					$sale_unit       = $rules['unit'];
					$current_qty     = $item_quantities[ $p_id ] ?? $min_qty;
					$validation_type = $rules['multiple'] ? 'multiple' : 'minimum';
					$step_val        = 'minimum' === $validation_type ? 1 : $min_qty;
					?>
					<div class="<?php echo esc_attr( $cls['card'] . ( $is_checked ? ' is-selected' : '' ) ); ?>">
						<input type="checkbox" name="products[]" value="<?php echo esc_attr( (string) $p_id ); ?>" <?php checked( $is_checked ); ?> aria-label="<?php echo esc_attr( 'انتخاب ' . get_the_title() ); ?>">

						<div class="<?php echo esc_attr( $cls['body'] ); ?>">
							<div class="img-wrapper">
								<?php echo get_the_post_thumbnail( $p_id, 'medium' ) ?: '<img src="' . esc_url( wc_placeholder_img_src() ) . '" alt="">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- خروجی وردپرس. ?>
							</div>

							<h4 class="<?php echo esc_attr( $cls['title'] ); ?>"><?php the_title(); ?></h4>

							<div class="<?php echo esc_attr( $cls['price'] ); ?>"><?php echo wp_kses_post( wc_price( $base_price ) ); ?></div>

							<div class="p-spec">
								<?php if ( 'کیلو' !== $sale_unit ) : ?>
									<div>هر <?php echo esc_html( $sale_unit ); ?> شامل <?php echo esc_html( (string) $unit_capacity ); ?> عدد</div>
								<?php else : ?>
									<div>هر کیسه شامل وزن درج شده</div>
								<?php endif; ?>
								<div class="p-min">حداقل سفارش: <?php echo esc_html( \Hodima\Panel\Orders::format_qty( $min_qty ) . ' ' . $sale_unit ); ?></div>
							</div>

							<div class="qty-field-wrapper">
								<div class="qty-input-with-unit">
									<input type="number"
										name="qty[<?php echo esc_attr( (string) $p_id ); ?>]"
										value="<?php echo esc_attr( (string) $current_qty ); ?>"
										min="<?php echo esc_attr( (string) $min_qty ); ?>"
										step="<?php echo esc_attr( (string) $step_val ); ?>"
										data-unit="<?php echo esc_attr( $sale_unit ); ?>"
										data-min="<?php echo esc_attr( (string) $min_qty ); ?>"
										data-validation-type="<?php echo esc_attr( $validation_type ); ?>"
										data-price="<?php echo esc_attr( (string) $base_price ); ?>"
										data-capacity="<?php echo esc_attr( (string) $unit_capacity ); ?>"
										aria-label="<?php echo esc_attr( 'تعداد ' . get_the_title() ); ?>"
										class="up-qty-logic">
									<span class="unit-suffix"><?php echo esc_html( $sale_unit ); ?></span>
								</div>
								<div class="up-msg-wrap"></div>
							</div>

							<div class="row-total-container">
								مبلغ کل: <span class="row-total">0</span> <span class="row-total-unit">تومان</span>
							</div>
						</div>
					</div>
				<?php endwhile; ?>
			</div>

			<div class="sticky-actions-bar">
				<div class="sticky-inner-container">
					<div class="grand-total-box">
						مبلغ کل <?php echo esc_html( $label ); ?>: <span id="<?php echo esc_attr( $cls['total'] ); ?>">0</span> <small>تومان</small>
					</div>
					<div class="submit-container">
						<button type="submit" class="<?php echo esc_attr( $cls['submit'] ); ?>">ثبت <?php echo esc_html( $label ); ?></button>
					</div>
				</div>
			</div>
		</form>
	<?php else : ?>
		<p class="no-data">فعلا محصولی برای <?php echo esc_html( $label ); ?> فعال نشده است.</p>
	<?php endif; ?>
	<?php wp_reset_postdata(); ?>
</div>
