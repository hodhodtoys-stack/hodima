<?php
/**
 * Hodima Router — صفحه پیشخوان «آدرس تمیز»
 * Path: core/router/admin.php
 *
 * وضعیت روتر، بررسی یک آدرس، و فهرست آدرس‌هایی که بیش از یک شیء دارند
 * (نامک تکراری قدیمی). پیش از این لینک «تنظیمات» ماژول به «پیوندهای یکتا»
 * می‌رفت که هیچ چیزی از روتر نداشت.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

const HODIMA_ROUTER_PAGE = 'hodima-router';

add_action( 'admin_menu', 'hodima_router_admin_menu', 30 );

function hodima_router_admin_menu(): void {

	$parent = function_exists( 'hodima_admin_menu_parent' ) ? hodima_admin_menu_parent() : '';

	if ( '' !== $parent ) {
		add_submenu_page( $parent, 'آدرس تمیز', 'آدرس تمیز', 'manage_options', HODIMA_ROUTER_PAGE, 'hodima_router_render_admin' );
		return;
	}

	add_menu_page( 'آدرس تمیز', 'آدرس تمیز', 'manage_options', HODIMA_ROUTER_PAGE, 'hodima_router_render_admin', 'dashicons-admin-site-alt3', 31 );
}

/** نام فارسی نوع شیء. */
function hodima_router_type_label( string $type ): string {
	return match ( $type ) {
		'product'     => 'محصول',
		'post'        => 'نوشته',
		'page'        => 'برگه',
		'product_cat' => 'دسته محصول',
		'category'    => 'دسته نوشته',
		default       => $type,
	};
}

/** عنوان و لینک ویرایش یک شیء. */
function hodima_router_object_label( WP_Post|WP_Term $object ): string {

	if ( $object instanceof WP_Post ) {
		$title = '' !== $object->post_title ? $object->post_title : '#' . $object->ID;
		$edit  = (string) get_edit_post_link( $object->ID, 'raw' );
		$type  = $object->post_type;
	} else {
		$title = $object->name;
		$edit  = (string) get_edit_term_link( $object->term_id, $object->taxonomy );
		$type  = $object->taxonomy;
	}

	$label = sprintf( '<span class="hd-pill hd-pill--info">%s</span> ', esc_html( hodima_router_type_label( $type ) ) );

	return $label . ( '' !== $edit
		? sprintf( '<a href="%s">%s</a>', esc_url( $edit ), esc_html( $title ) )
		: esc_html( $title ) );
}

function hodima_router_render_admin(): void {

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$active    = hodima_router_active();
	$bases     = hodima_router_bases();
	$conflicts = $active ? hodima_router_conflicts() : [];
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط خواندن
	$check     = isset( $_GET['check'] ) ? trim( (string) wp_unslash( $_GET['check'] ) ) : '';

	echo '<div class="wrap hd-wrap">';

	hodima_admin_header( [
		'title'       => 'آدرس تمیز',
		'description' => 'محصول، دسته محصول و دسته نوشته بدون /product/، /product-category/ و /category/؛ هر صفحه فقط یک آدرس دارد و شکل‌های دیگر با ۳۰۱ به آن می‌روند.',
		'icon'        => 'dashicons-admin-site-alt3',
	] );

	if ( ! $active ) {
		hodima_admin_notice( 'پیوندهای یکتا روی «ساده» است؛ آدرس تمیز فقط با پیوندهای یکتای زیبا (مثل «نام نوشته») کار می‌کند. <a href="' . esc_url( admin_url( 'options-permalink.php' ) ) . '">تنظیمات پیوندهای یکتا</a>', 'warning' );
	}

	echo '<div class="hd-body">';

	// وضعیت
	?>
	<section class="hd-card hd-card--accent">
		<h2 class="hd-section-title"><?php echo hodima_admin_icon( 'dashicons-admin-site-alt3' ); // phpcs:ignore ?> وضعیت</h2>
		<div class="hd-grid hd-grid--stats">
			<div class="hd-stat">
				<span class="hd-stat__label">روتر</span>
				<span class="hd-stat__value"><?php echo $active ? '<span class="hd-pill hd-pill--ok">فعال</span>' : '<span class="hd-pill hd-pill--warn">غیرفعال</span>'; ?></span>
			</div>
			<div class="hd-stat">
				<span class="hd-stat__label">پایه‌هایی که حذف می‌شوند</span>
				<span class="hd-stat__value"><code dir="ltr">/<?php echo esc_html( $bases['product'] ); ?>/</code> <code dir="ltr">/<?php echo esc_html( $bases['product_cat'] ); ?>/</code> <code dir="ltr">/<?php echo esc_html( $bases['category'] ); ?>/</code></span>
			</div>
			<div class="hd-stat">
				<span class="hd-stat__label">آدرس‌های مشترک بین دو شیء</span>
				<span class="hd-stat__value"><?php echo esc_html( number_format_i18n( count( $conflicts ) ) ); ?></span>
			</div>
		</div>
	</section>

	<section class="hd-card">
		<h2 class="hd-section-title"><?php echo hodima_admin_icon( 'dashicons-search' ); // phpcs:ignore ?> بررسی یک آدرس</h2>
		<form method="get" class="hd-inline">
			<input type="hidden" name="page" value="<?php echo esc_attr( HODIMA_ROUTER_PAGE ); ?>">
			<label class="screen-reader-text" for="hodima-router-check">آدرس</label>
			<input type="text" id="hodima-router-check" name="check" class="regular-text" dir="ltr" value="<?php echo esc_attr( $check ); ?>" placeholder="<?php echo esc_attr( home_url( '/product/...' ) ); ?>">
			<button type="submit" class="button button-primary">بررسی</button>
		</form>
		<?php
		if ( '' !== $check ) {
			$hit = hodima_router_resolve_url( $check, true );
			if ( null === $hit ) {
				echo '<div class="hd-callout hd-callout--warning">' . hodima_admin_icon( 'dashicons-warning' ) . '<p>روتر این آدرس را به محصول، نوشته، برگه یا دسته‌ای نمی‌رساند (یا مال بخش دیگری از وردپرس است، مثل برچسب و نویسنده).</p></div>'; // phpcs:ignore
			} else {
				$text = $hit['exact']
					? 'همین آدرس اصلی ' . hodima_router_object_label( $hit['object'] ) . ' است.'
					: 'با ۳۰۱ به آدرس اصلی ' . hodima_router_object_label( $hit['object'] ) . ' می‌رود: <code dir="ltr">' . esc_html( rawurldecode( $hit['url'] ) ) . '</code>';
				echo '<div class="hd-callout hd-callout--success">' . hodima_admin_icon( 'dashicons-yes-alt' ) . '<p>' . wp_kses_post( $text ) . '</p></div>'; // phpcs:ignore
			}
		}
		?>
	</section>

	<section class="hd-card">
		<h2 class="hd-section-title"><?php echo hodima_admin_icon( 'dashicons-warning' ); // phpcs:ignore ?> آدرس‌های مشترک (نامک تکراری)</h2>
		<?php if ( ! $conflicts ) : ?>
			<div class="hd-empty"><?php echo hodima_admin_icon( 'dashicons-yes-alt' ); // phpcs:ignore ?><p>هیچ دو صفحه‌ای آدرس یکسان ندارند.</p></div>
		<?php else : ?>
			<div class="hd-callout hd-callout--warning">
				<?php echo hodima_admin_icon( 'dashicons-info' ); // phpcs:ignore ?>
				<p><strong>این آدرس‌ها مال بیش از یک صفحه‌اند و فقط اولی نمایش داده می‌شود.</strong> نامک صفحه پنهان‌مانده را در ویرایش آن عوض کنید (نامک تکراری جدید دیگر ساخته نمی‌شود). آدرس فعلی برای صفحه نمایش‌داده‌شده می‌ماند و ریدایرکت خودکار برایش ساخته نمی‌شود.</p>
			</div>
			<div class="hd-table-wrap">
				<table class="widefat striped">
					<thead><tr><th scope="col">آدرس</th><th scope="col">نمایش داده می‌شود</th><th scope="col">پنهان مانده</th></tr></thead>
					<tbody>
					<?php foreach ( $conflicts as $conflict ) : ?>
						<tr>
							<td><a href="<?php echo esc_url( $conflict['items'][0]['url'] ); ?>" dir="ltr"><?php echo esc_html( '/' . $conflict['path'] . '/' ); ?></a></td>
							<td><?php echo hodima_router_object_label( $conflict['items'][0]['object'] ); // phpcs:ignore -- escaped inside ?></td>
							<td><?php echo implode( '<br>', array_map( static fn( array $item ): string => hodima_router_object_label( $item['object'] ), array_slice( $conflict['items'], 1 ) ) ); // phpcs:ignore ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</section>
	<?php

	echo '</div></div>';
}
