<?php
/**
 * صفحه «ابزارهای هدیما ← ترب»: تنظیم‌های ترب و گزارش وضعیت محصولات
 * Path: plugins/hodima-seo/inc/torob-admin.php
 *
 * چرا اینجا: تا 2.2.5 قیمت و شکل نام ترب و گزارش در «تنظیمات قالب ← صفحه محصول»
 * بودند؛ قالب فقط ظاهر سایت است و ترب کار افزونه (خواسته کاربر، بخش ۱۰۲). منطق
 * فقط در inc/torob.php (hodima_seo_torob_info/report)؛ این فایل فقط فرم و نمایش.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', static function (): void {

	// زیر «ابزارهای هدیما»؛ بدون Hodima Core منوی سطح اول
	$parent = function_exists( 'hodima_admin_menu_parent' ) ? hodima_admin_menu_parent() : '';

	if ( '' !== $parent ) {
		add_submenu_page( $parent, 'ترب', 'ترب', 'manage_options', 'hodima-torob', 'hodima_seo_torob_render_page' );
		return;
	}

	add_menu_page( 'ترب', 'ترب', 'manage_options', 'hodima-torob', 'hodima_seo_torob_render_page', 'dashicons-store', 58 );
} );

/** CSS همین صفحه (بعد از ثبت admin-ui.css در Core): راهنمای جمع‌شونده، نوار نشان‌ها و حالت خالی کوچک. */
add_action( 'admin_enqueue_scripts', static function (): void {

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط تشخیص صفحه
	if ( 'hodima-torob' !== sanitize_key( (string) wp_unslash( $_GET['page'] ?? '' ) ) || ! defined( 'HODIMA_ADMIN_UI_HANDLE' ) ) {
		return;
	}

	wp_add_inline_style( HODIMA_ADMIN_UI_HANDLE, '
.hodima-torob-page .hodima-torob__stats { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-inline-end: auto; }
.hodima-torob-page .hodima-torob__more > summary { cursor: pointer; color: var(--hd-primary); font-weight: 700; min-block-size: 2.75rem; display: flex; align-items: center; }
.hodima-torob-page .hodima-torob__more > summary:focus-visible { outline: 2px solid var(--hd-ok); outline-offset: 2px; }
.hodima-torob-page .hodima-torob__ok { margin: 0; color: var(--hd-muted); }
.hodima-torob-page .hodima-torob__ok span { color: var(--hd-ok); font-weight: 700; margin-inline-end: 0.25rem; }
' );
}, 20 );

/** ذخیره تنظیم‌ها (admin-post). */
add_action( 'admin_post_hodima_seo_torob_save', static function (): void {

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'اجازه این کار را ندارید.', 403 );
	}
	check_admin_referer( 'hodima_seo_torob_save' );

	$settings = hodima_seo_torob_clean_settings( [
		'price' => sanitize_key( wp_unslash( (string) ( $_POST['torob_price'] ?? '' ) ) ),
		'title' => sanitize_text_field( wp_unslash( (string) ( $_POST['torob_title'] ?? '' ) ) ),
	] );

	update_option( HODIMA_SEO_TOROB_OPTION, $settings, true );
	hodima_seo_torob_flush_report();

	if ( function_exists( 'hodima_admin_flash' ) ) {
		hodima_admin_flash( 'تنظیم‌های ترب ذخیره شد. ترب تغییر را در بازدید بعدی خودش می‌گیرد.' );
	}

	wp_safe_redirect( hodima_seo_torob_page_url() );
	exit;
} );

/** ساخت دوباره گزارش (admin-post). */
add_action( 'admin_post_hodima_seo_torob_refresh', static function (): void {

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'اجازه این کار را ندارید.', 403 );
	}
	check_admin_referer( 'hodima_seo_torob_refresh' );

	hodima_seo_torob_flush_report();

	if ( function_exists( 'hodima_admin_flash' ) ) {
		hodima_admin_flash( 'گزارش از نو ساخته شد.' );
	}

	wp_safe_redirect( hodima_seo_torob_page_url() );
	exit;
} );

/**
 * ستون «اختلاف» (مبلغ حداقل سفارش نسبت به تعداد × قیمت تک): تا ۱۰ تومان «اختلاف
 * جزئی» (اشتباه تایپی؛ با منبع «حداقل سبد» یک تومان اضافه تعداد را یکی بالا می‌برد)،
 * نزدیک (کمتر از ۱٫۵ برابر) به تومان، دور «N برابر» (معمولا عدد جدول غلط است).
 */
function hodima_seo_torob_diff_pill( float $qty, float $unit_price, float $min ): string {

	$product = $qty * $unit_price;
	$diff    = $min - $product;
	$side    = $diff > 0 ? 'بیشتر' : 'کمتر';

	if ( abs( $diff ) <= 10 ) {
		return sprintf( '<span class="hd-pill hd-pill--warn">اختلاف جزئی: %s تومان</span>', esc_html( number_format_i18n( abs( $diff ) ) ) );
	}

	$ratio = $product > 0 && $min > 0 ? max( $min / $product, $product / $min ) : 0.0;

	if ( $ratio > 0 && $ratio < 1.5 ) {
		return sprintf( '<span class="hd-pill hd-pill--warn">%s تومان %s</span>', esc_html( number_format_i18n( abs( $diff ) ) ), esc_html( $side ) );
	}

	$times = round( $ratio, 1 );

	return sprintf(
		'<span class="hd-pill hd-pill--error">%s برابر %s</span>',
		esc_html( number_format_i18n( $times, floor( $times ) === $times ? 0 : 1 ) ),
		esc_html( $side )
	);
}

/** صفحه «ابزارهای هدیما ← ترب». */
function hodima_seo_torob_render_page(): void {

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$settings = hodima_seo_torob_settings();
	$report   = hodima_seo_torob_report();
	$counts   = $report['counts'];
	$labels   = hodima_seo_torob_source_labels();
	$money    = static fn( float $amount ): string => number_format_i18n( $amount ) . ' تومان';
	$edit     = static fn( int $id ): string => (string) get_edit_post_link( $id );
	$torob_on = defined( 'TOROB_PLUGIN_VERSION' );
	$preview  = admin_url( 'admin.php?page=torob-settings&torob_products_preview=1' );
	?>
	<div class="wrap hd-wrap hodima-torob-page">
		<?php
		hodima_admin_header( [
			'title'       => 'ترب',
			'description' => 'قیمت و نامی که ترب از افزونه رسمی‌اش («استخراج محصولات ووکامرس برای ترب») از محصولات سایت می‌گیرد. قیمت و نام محصول در خود سایت، سبد و پرداخت عوض نمی‌شود.',
			'icon'        => 'dashicons-store',
		] );
		?>

		<div class="hd-body">

		<?php if ( ! $torob_on ) : ?>
			<div class="hd-callout hd-callout--warning">
				<p>افزونه رسمی ترب («استخراج محصولات ووکامرس برای ترب») فعال نیست؛ این تنظیم‌ها فقط وقتی اثر دارند که آن افزونه فعال و به ترب وصل باشد.</p>
			</div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'hodima_seo_torob_save' ); ?>
			<input type="hidden" name="action" value="hodima_seo_torob_save">

			<section class="hd-card">
				<div class="hd-card__head">
					<span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>
					<h2 class="hd-card__title">تنظیم‌ها</h2>
				</div>
				<div class="hd-fields">
					<div class="hd-field">
						<label class="hd-field__label" for="hodima-torob-price">قیمتی که ترب بگیرد</label>
						<select id="hodima-torob-price" name="torob_price">
							<option value="unit" <?php selected( $settings['price'], 'unit' ); ?>>قیمت تک محصول</option>
							<option value="min_order" <?php selected( $settings['price'], 'min_order' ); ?>>مبلغ حداقل سفارش</option>
						</select>
						<p class="hd-field__help">مستقل از قیمت اسکیمای گوگل (تنظیمات قالب ← صفحه محصول). محصولی که حداقل سفارش ندارد یا متغیر است، همیشه قیمت تک را می‌فرستد.</p>
					</div>
					<div class="hd-field">
						<label class="hd-field__label" for="hodima-torob-title">شکل نام در ترب</label>
						<input type="text" id="hodima-torob-title" name="torob_title" value="<?php echo esc_attr( $settings['title'] ); ?>" maxlength="200">
							<details class="hodima-torob__more">
								<summary>راهنمای {نام} {تعداد} {واحد}</summary>
							<p class="hd-field__help">وقتی ترب «مبلغ حداقل سفارش» را می‌گیرد. {نام} = نام محصول، {تعداد} = ردیف «تعداد» جدول مشخصات (اگر نبود: مبلغ حداقل سفارش تقسیم بر قیمت تک)، {واحد} = واحد نوشته‌شده در جدول یا «عدد». هر محصول را در «ویرایش محصول ← داده‌های محصول ← ترب» می‌توان جدا تنظیم کرد.</p>
							</details>
					</div>
				</div>
			</section>

			<div class="hd-actions">
				<span class="hodima-torob__stats">
					<span class="hd-pill hd-pill--info">با حداقل سفارش یا نام دلخواه: <?php echo esc_html( number_format_i18n( $report['total'] ) ); ?></span>
					<?php foreach ( [ 'table', 'cart', 'manual', 'custom' ] as $key ) : ?>
						<?php if ( ! empty( $counts[ $key ] ) ) : ?>
							<span class="hd-pill hd-pill--info"><?php echo esc_html( $labels[ $key ] . ': ' . number_format_i18n( (int) $counts[ $key ] ) ); ?></span>
						<?php endif; ?>
					<?php endforeach; ?>
					<span class="hd-pill <?php echo esc_attr( $counts['warning'] ? 'hd-pill--warn' : 'hd-pill--ok' ); ?>">نیاز به بررسی: <?php echo esc_html( number_format_i18n( (int) $counts['warning'] ) ); ?></span>
				</span>
				<?php if ( $torob_on ) : ?>
					<a class="button" href="<?php echo esc_url( $preview ); ?>">پیش‌نمایش محصولات در افزونه ترب</a>
				<?php endif; ?>
				<button type="submit" class="button button-primary">ذخیره تنظیم‌ها</button>
			</div>
		</form>

		<?php if ( 'min_order' !== $report['mode'] ) : ?>
			<div class="hd-callout">
				<p>«قیمتی که ترب بگیرد» روی قیمت تک است؛ نام خودکار ساخته نمی‌شود و فقط نام‌های کامل دلخواه به ترب می‌روند.</p>
			</div>
		<?php endif; ?>

		<?php if ( $report['samples'] ) : ?>
			<section class="hd-card">
				<div class="hd-card__head">
					<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
					<h2 class="hd-card__title">نمونه آنچه ترب می‌گیرد</h2>
				</div>
				<div class="hd-table-wrap">
					<table class="widefat striped">
						<thead><tr><th>نام در ترب</th><th>قیمت در ترب</th><th>تعداد از کجا</th></tr></thead>
						<tbody>
						<?php foreach ( $report['samples'] as $row ) : ?>
							<tr>
								<td><a href="<?php echo esc_url( $edit( (int) $row['id'] ) ); ?>"><?php echo esc_html( (string) $row['title'] ); ?></a></td>
								<td><?php echo esc_html( '' !== (string) $row['price'] ? $money( (float) $row['price'] ) : '—' ); ?></td>
								<td><?php echo esc_html( $labels[ (string) $row['source'] ] ?? '' ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</section>
		<?php endif; ?>

		<section class="hd-card">
			<div class="hd-card__head">
				<span class="dashicons dashicons-warning" aria-hidden="true"></span>
				<h2 class="hd-card__title">نیاز به بررسی</h2>
				<span class="hd-pill <?php echo esc_attr( $counts['warning'] ? 'hd-pill--warn' : 'hd-pill--ok' ); ?>"><?php echo esc_html( number_format_i18n( (int) $counts['warning'] ) ); ?> محصول</span>
			</div>

			<details class="hodima-torob__more">
				<summary>این بخش چه می‌گوید؟</summary>
				<p class="hd-field__help">
					تعداد این محصولات با مبلغ حداقل سفارش نمی‌خواند، پس نام یا قیمتی که ترب می‌بیند احتمالا غلط است.
					«اختلاف جزئی» معمولا اشتباه تایپی در مبلغ حداقل سفارش است؛ «چند برابر» معمولا یعنی عدد ردیف «تعداد» جدول با حداقل سفارش واقعی فرق دارد.
					عدد جدول یا مبلغ حداقل سفارش را درست کنید، یا در تب «ترب» همان محصول تعداد دستی بدهید.
				</p>
			</details>

			<?php if ( ! $report['issues'] ) : ?>
					<p class="hodima-torob__ok"><span aria-hidden="true">✓</span> موردی برای بررسی نیست؛ تعداد همه محصولات با مبلغ حداقل سفارش می‌خواند.</p>
			<?php else : ?>
				<div class="hd-table-wrap">
					<table class="widefat striped">
						<thead>
							<tr><th>محصول</th><th>تعداد</th><th>قیمت تک</th><th>تعداد × قیمت تک</th><th>مبلغ حداقل سفارش</th><th>اختلاف حداقل سفارش</th></tr>
						</thead>
						<tbody>
						<?php foreach ( $report['issues'] as $row ) : ?>
							<tr>
								<td><a href="<?php echo esc_url( $edit( (int) $row['id'] ) ); ?>"><?php echo esc_html( (string) $row['name'] ); ?></a></td>
								<?php if ( 'mismatch' === $row['problem'] ) : ?>
									<td>
										<?php echo esc_html( hodima_seo_torob_persian_digits( (string) $row['qty'] ) . ' ' . $row['unit'] ); ?>
										<br><small class="hd-muted"><?php echo esc_html( $labels[ (string) $row['source'] ] ?? '' ); ?></small>
									</td>
									<td><?php echo esc_html( $money( (float) $row['unit_price'] ) ); ?></td>
									<td><?php echo esc_html( $money( (float) $row['qty'] * (float) $row['unit_price'] ) ); ?></td>
									<td><?php echo esc_html( $money( (float) $row['min'] ) ); ?></td>
									<td><?php echo wp_kses_post( hodima_seo_torob_diff_pill( (float) $row['qty'], (float) $row['unit_price'], (float) $row['min'] ) ); ?></td>
								<?php else : ?>
									<td colspan="5"><?php echo esc_html( (string) $row['warning'] ); ?></td>
								<?php endif; ?>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>

			<p class="hd-muted">
				<?php echo esc_html( sprintf( 'گزارش %s پیش ساخته شد؛ با ذخیره هر محصول خودکار تازه می‌شود.', human_time_diff( (int) $report['time'] ) ) ); ?>
				<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=hodima_seo_torob_refresh' ), 'hodima_seo_torob_refresh' ) ); ?>">به‌روزرسانی گزارش</a>
			</p>
		</section>

		</div>
	</div>
	<?php
}
