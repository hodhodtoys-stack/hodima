<?php
/**
 * کادر «پیش‌خرید و پیش‌فاکتور» در ویرایش محصول ووکامرس.
 *
 * @package Hodima\Panel
 */

declare(strict_types=1);

namespace Hodima\Panel;

defined( 'ABSPATH' ) || exit;

/**
 * کادر محصول (طرح تأییدشده کاربر، مرحله ۶؛ نوع «ورود محتوا» اسکیل hodima-admin-ui).
 *
 * قبلی (legacy/product-fields.php) با woocommerce_wp_checkbox/select ساخته می‌شد: بی آن توابع
 * (ووکامرس ناقص، ابزار تست) صفحه ویرایش محصول خطای مهلک می‌داد؛ حداقل سفارش step=1 داشت و
 * ۰٫۵ کیلو را مرورگر رد می‌کرد؛ و ذخیره بی‌نشانه بود (ذخیره‌ای که کادر را نداشت تیک‌ها را
 * «no» می‌کرد). حالا کنترل‌های کیت، پیش‌نمایش «مشتری در پنل این را می‌بیند» و ذخیره فقط
 * وقتی کادر در فرم بوده. نام متاها همان قبلی (قانون ۵).
 */
final class Admin_Product {

	/** واحدهای فروش (مقدار ذخیره‌شده = همین متن). */
	public const UNITS = [ 'کیلو', 'بسته', 'کارتن', 'جین' ];

	/** ثبت هوک‌ها. */
	public static function boot(): void {
		add_action( 'add_meta_boxes_product', [ self::class, 'add' ] );
		add_action( 'woocommerce_process_product_meta', [ self::class, 'save' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'assets' ] );
	}

	/** کادر در ستون اصلی، بالای کادرهای سئو. */
	public static function add(): void {
		add_meta_box( 'up_product_settings_box', 'پیش‌خرید و پیش‌فاکتور', [ self::class, 'render' ], 'product', 'normal', 'high' );
	}

	/**
	 * کیت و CSS/JS کادر فقط در ویرایش/افزودن محصول.
	 *
	 * @param string $hook صفحه.
	 */
	public static function assets( string $hook ): void {
		$screen = get_current_screen();
		if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) || ! $screen || 'product' !== $screen->post_type ) {
			return;
		}
		$base = HODIMA_PANEL_URL;
		wp_enqueue_style( 'hodima-panel-ui', $base . '/ui/ui-kit.css', [], asset_version( 'ui/ui-kit.css' ) );
		wp_enqueue_style( 'hodima-panel-product', $base . '/assets/css/admin-product.css', [ 'hodima-panel-ui' ], asset_version( 'assets/css/admin-product.css' ) );
		wp_enqueue_script( 'hodima-panel-product', $base . '/assets/js/admin-product.js', [], asset_version( 'assets/js/admin-product.js' ), true );
	}

	/**
	 * کادر.
	 *
	 * @param \WP_Post $post محصول.
	 */
	public static function render( \WP_Post $post ): void {
		$id       = (int) $post->ID;
		$prebuy   = 'yes' === get_post_meta( $id, '_prebuy_enabled', true );
		$preinv   = 'yes' === get_post_meta( $id, '_preinvoice_enabled', true );
		$unit     = (string) get_post_meta( $id, '_up_sale_unit', true );
		$capacity = (string) get_post_meta( $id, '_up_unit_capacity', true );
		$min      = (string) get_post_meta( $id, '_up_min_qty', true );
		$product  = function_exists( 'wc_get_product' ) ? wc_get_product( $id ) : null;
		$price    = $product ? (float) $product->get_price() : (float) get_post_meta( $id, '_price', true );
		$units    = self::UNITS;
		if ( '' !== $unit && ! in_array( $unit, $units, true ) ) {
			$units[] = $unit; // مقدار قدیمی ناشناخته از دست نرود
		}
		$shown = '' !== $unit ? $unit : 'واحد';
		?>
		<div class="hdp-ui hpp" data-hpp data-price="<?php echo esc_attr( (string) $price ); ?>">
			<input type="hidden" name="hodima_panel_product_box" value="1">
			<p class="hpp__status" aria-live="polite"><span class="hdp-pill<?php echo $prebuy || $preinv ? '' : ' hpp__off'; ?>" data-hpp-status><?php echo esc_html( self::status_text( $prebuy, $preinv ) ); ?></span></p>
			<div class="hpp__grid">
				<div class="hpp__main">
					<label class="hpp__opt">
						<span class="hpp__opt-text"><strong>در «ثبت پیش‌خرید» باشد</strong><span>مشتری مقدار می‌زند و مبلغ همان لحظه حساب می‌شود.</span></span>
						<input type="checkbox" class="hdp-switch" name="_prebuy_enabled" value="yes" data-hpp-prebuy<?php checked( $prebuy ); ?>>
					</label>
					<label class="hpp__opt">
						<span class="hpp__opt-text"><strong>در «درخواست پیش‌فاکتور» باشد</strong><span>مشتری درخواست می‌دهد؛ شما پیش‌فاکتور رسمی می‌فرستید.</span></span>
						<input type="checkbox" class="hdp-switch" name="_preinvoice_enabled" value="yes" data-hpp-preinvoice<?php checked( $preinv ); ?>>
					</label>
					<div class="hpp__fields">
						<p class="hdp-field">
							<label class="hdp-field__label" for="hpp-unit">به چه واحدی؟</label>
							<select id="hpp-unit" name="_up_sale_unit" class="hdp-input" data-hpp-unit>
								<option value="">انتخاب کنید</option>
								<?php foreach ( $units as $u ) : ?>
									<option value="<?php echo esc_attr( $u ); ?>"<?php selected( $u, $unit ); ?>><?php echo esc_html( $u ); ?></option>
								<?php endforeach; ?>
							</select>
						</p>
						<p class="hdp-field">
							<label class="hdp-field__label" for="hpp-capacity"><?php echo esc_html( 'هر ' ); ?><span data-hpp-unit-name><?php echo esc_html( $shown ); ?></span><?php echo esc_html( ' چند عدد؟' ); ?></label>
							<span class="hpp__unit"><input type="number" id="hpp-capacity" name="_up_unit_capacity" class="hdp-input" min="1" step="1" inputmode="numeric" value="<?php echo esc_attr( $capacity ); ?>" placeholder="۱" data-hpp-capacity><span aria-hidden="true">عدد</span></span>
						</p>
						<p class="hdp-field">
							<label class="hdp-field__label" for="hpp-min">حداقل سفارش</label>
							<span class="hpp__unit"><input type="number" id="hpp-min" name="_up_min_qty" class="hdp-input" min="0" step="any" inputmode="decimal" value="<?php echo esc_attr( $min ); ?>" placeholder="۱" data-hpp-min><span aria-hidden="true" data-hpp-unit-name><?php echo esc_html( $shown ); ?></span></span>
						</p>
					</div>
					<p class="hdp-field__help"><?php echo esc_html( 'قیمت هر عدد از قیمت خود محصول خوانده می‌شود. کیلو: مقدار سفارش باید مضرب حداقل باشد؛ کارتن، بسته و جین: فقط از حداقل کمتر نباشد.' ); ?></p>
				</div>
				<aside class="hpp__preview" aria-label="پیش‌نمایش در پنل مشتری">
					<strong class="hpp__preview-title">مشتری در پنل این را می‌بیند</strong>
					<div class="hpp__card">
						<span class="hpp__img"><?php echo get_the_post_thumbnail( $post, [ 56, 56 ], [ 'alt' => '' ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- خروجی امن وردپرس. ?></span>
						<span class="hpp__card-text">
							<strong><?php echo esc_html( get_the_title( $post ) ?: 'نام محصول' ); ?></strong>
							<span data-hpp-rule><?php echo esc_html( self::rule_text( $unit, (int) $capacity, (float) $min ) ); ?></span>
							<span><?php echo esc_html( $price > 0 ? 'هر عدد ' . number_format_i18n( $price ) . ' تومان' : 'قیمت محصول خالی است' ); ?></span>
						</span>
					</div>
					<ul class="hpp__check">
						<li data-state="<?php echo '' !== $unit && (int) $capacity > 0 ? 'ok' : 'warn'; ?>" data-hpp-check-unit>واحد و ظرفیت</li>
						<li data-state="<?php echo $price > 0 ? 'ok' : 'warn'; ?>">قیمت محصول</li>
						<li data-state="<?php echo has_post_thumbnail( $post ) ? 'ok' : 'warn'; ?>">عکس شاخص</li>
					</ul>
				</aside>
			</div>
		</div>
		<?php
	}

	/** متن نشان وضعیت. */
	public static function status_text( bool $prebuy, bool $preinvoice ): string {
		return match ( true ) {
			$prebuy && $preinvoice => 'در پنل: پیش‌خرید و پیش‌فاکتور',
			$prebuy                => 'در پنل: پیش‌خرید',
			$preinvoice            => 'در پنل: پیش‌فاکتور',
			default                => 'در پنل نیست',
		};
	}

	/** همان جمله‌ای که کارت محصول پنل مشتری نشان می‌دهد. */
	public static function rule_text( string $unit, int $capacity, float $min ): string {
		if ( '' === $unit ) {
			return 'واحد فروش انتخاب نشده';
		}
		$parts = [];
		if ( $capacity > 1 ) {
			$parts[] = 'هر ' . $unit . ' ' . number_format_i18n( $capacity ) . ' عدد';
		}
		$parts[] = 'حداقل ' . View::qty( $min > 0 ? $min : 1 ) . ' ' . $unit;
		return implode( ' · ', $parts );
	}

	/**
	 * ذخیره (ووکامرس nonce فرم محصول را پیش از این هوک سنجیده).
	 *
	 * @param int $post_id محصول.
	 */
	public static function save( int $post_id ): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- woocommerce_process_product_meta بعد از بررسی nonce ووکامرس.
		if ( empty( $_POST['hodima_panel_product_box'] ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return; // فرمی که کادر را نداشت (ویرایش سریع، API) چیزی را خاموش نکند
		}
		update_post_meta( $post_id, '_prebuy_enabled', isset( $_POST['_prebuy_enabled'] ) ? 'yes' : 'no' );
		update_post_meta( $post_id, '_preinvoice_enabled', isset( $_POST['_preinvoice_enabled'] ) ? 'yes' : 'no' );
		$unit = Orders::normalize_unit( sanitize_text_field( wp_unslash( (string) ( $_POST['_up_sale_unit'] ?? '' ) ) ) );
		update_post_meta( $post_id, '_up_sale_unit', $unit );
		$capacity = Profile::digits( sanitize_text_field( wp_unslash( (string) ( $_POST['_up_unit_capacity'] ?? '' ) ) ) );
		update_post_meta( $post_id, '_up_unit_capacity', '' === $capacity ? '' : (string) max( 1, (int) $capacity ) );
		$min = Profile::digits( sanitize_text_field( wp_unslash( (string) ( $_POST['_up_min_qty'] ?? '' ) ) ) );
		update_post_meta( $post_id, '_up_min_qty', '' === $min ? '' : (string) max( 0, (float) $min ) );
		// phpcs:enable
	}
}
