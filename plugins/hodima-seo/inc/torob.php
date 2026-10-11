<?php
/**
 * نام محصول در ترب: «شانه سه شاخ (حداقل سفارش ۱۲ عدد)»
 * Path: plugins/hodima-seo/inc/torob.php
 *
 * چرا: ترب با قاب «قیمت در ترب» روی «مبلغ حداقل سفارش» (inc/product-price.php)
 * مبلغ بسته را می‌گیرد، ولی خریدار نام تکی («شانه سه شاخ — ۹۲٬۴۰۰») را می‌بیند؛
 * پشتیبانی ترب خواست حداقل تعداد سفارش در نام باشد (برچسب روی همه محصولات).
 *
 * - نام فقط در درخواست ترب (همان تشخیص hodima_seo_is_feed_request()) عوض می‌شود؛
 *   نام اصلی محصول، عنوان صفحه، سبد و فاکتور دست نمی‌خورند.
 * - تنظیم‌ها (قیمت ترب، شکل نام) و گزارش: «ابزارهای هدیما ← ترب» (inc/torob-admin.php،
 *   گزینه hodima_seo_torob). تا 2.2.5 در تنظیمات قالب بودند (product_torob_price/title)؛
 *   همان مقدارها یک بار خودکار منتقل می‌شوند (hodima_seo_torob_settings()).
 * - تعداد: دستی (تب «ترب» ویرایش محصول) ← ردیف «تعداد» جدول مشخصات (woo-table
 *   Commerce) ← حداقل تعداد سبد (مبلغ حداقل تقسیم بر قیمت تک، رو به بالا).
 * - فقط محصول ساده‌ای که قیمت ترب‌اش مبلغ حداقل سفارش است نام خودکار می‌گیرد؛
 *   «نام کامل دلخواه» تب ترب برای هر محصولی همیشه می‌رود.
 * - گزارش (hodima_seo_torob_report()) محصولاتی را نشان می‌دهد که
 *   «تعداد × قیمت تک» با مبلغ حداقل سفارش نمی‌خواند.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** شکل پیش‌فرض نام (همان پیش‌فرض فیلد قالب). */
const HODIMA_SEO_TOROB_TEMPLATE = '{نام} (حداقل سفارش {تعداد} {واحد})';

/** متاهای تب «ترب» ویرایش محصول. */
const HODIMA_SEO_TOROB_META = [
	'qty'   => '_hodima_torob_qty',
	'unit'  => '_hodima_torob_unit',
	'title' => '_hodima_torob_title',
];

/** کش گزارش صفحه ترب. */
const HODIMA_SEO_TOROB_REPORT_KEY = 'hodima_seo_torob_report';

/** تنظیم‌های ترب: price = unit | min_order، title = شکل نام. */
const HODIMA_SEO_TOROB_OPTION = 'hodima_seo_torob';

/**
 * تنظیم‌های ترب. نبودن گزینه = سایت از 2.2.5 یا قبل‌تر می‌آید: مقدارهای قبلی از گزینه
 * خام تنظیمات قالب (product_torob_price/title) برداشته و یک بار ذخیره می‌شوند —
 * خام، چون قالب 3.0.7 این فیلدها را ندارد و hodima_setting() آن‌ها را نمی‌دهد و با
 * اولین ذخیره تنظیمات قالب از گزینه‌اش پاک می‌شوند.
 *
 * @return array{price: string, title: string}
 */
function hodima_seo_torob_settings(): array {

	$stored = get_option( HODIMA_SEO_TOROB_OPTION, null );

	if ( ! is_array( $stored ) ) {
		$theme  = get_option( 'hodima_theme_settings', [] );
		$theme  = is_array( $theme ) ? $theme : [];
		$stored = [
			'price' => (string) ( $theme['product_torob_price'] ?? 'unit' ),
			'title' => (string) ( $theme['product_torob_title'] ?? '' ),
		];
		update_option( HODIMA_SEO_TOROB_OPTION, hodima_seo_torob_clean_settings( $stored ), true );
	}

	return hodima_seo_torob_clean_settings( $stored );
}

/**
 * @param array<mixed> $raw
 * @return array{price: string, title: string}
 */
function hodima_seo_torob_clean_settings( array $raw ): array {

	$title = trim( sanitize_text_field( (string) ( $raw['title'] ?? '' ) ) );

	return [
		'price' => 'min_order' === ( $raw['price'] ?? '' ) ? 'min_order' : 'unit',
		'title' => '' !== $title ? mb_substr( $title, 0, 200 ) : HODIMA_SEO_TOROB_TEMPLATE,
	];
}

/** یک تنظیم ترب: 'price' یا 'title'. */
function hodima_seo_torob_setting( string $key ): string {
	return hodima_seo_torob_settings()[ $key ] ?? '';
}

// انتقال یک‌باره در اولین درخواست بعد از به‌روزرسانی (پیش از اینکه ذخیره تنظیمات قالب مقدار قدیمی را پاک کند)
add_action( 'init', static function (): void {
	hodima_seo_torob_settings();
}, 1 );

/** آدرس صفحه «ابزارهای هدیما ← ترب». */
function hodima_seo_torob_page_url(): string {
	return admin_url( 'admin.php?page=hodima-torob' );
}

/**
 * واحدهای شناخته‌شده؛ به همین ترتیب در متن جدول جستجو می‌شوند («عدد» اول: «۱۲
 * عدد در بسته» یعنی ۱۲ عدد، نه بسته؛ «کیلو» پیش از «گرم»).
 *
 * @return list<string>
 */
function hodima_seo_torob_units(): array {
	return [ 'عدد', 'کیلو', 'گرم', 'کارتن', 'جین', 'بسته', 'جفت', 'متر', 'رول', 'ورق' ];
}

/** ارقام فارسی و عربی ← لاتین. */
function hodima_seo_torob_latin_digits( string $text ): string {
	return strtr( $text, [
		'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
		'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
		'٫' => '.',
	] );
}

/** ارقام لاتین ← فارسی (نام در ترب فارسی است). */
function hodima_seo_torob_persian_digits( string $text ): string {
	return strtr( $text, [ '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹', '.' => '٫' ] );
}

/** عدد مثبت به شکل متن کوتاه (12.0 ← 12) یا '' اگر عدد مثبت نیست. */
function hodima_seo_torob_number( string $raw ): string {

	$raw = str_replace( [ ',', ' ' ], [ '.', '' ], hodima_seo_torob_latin_digits( trim( $raw ) ) );

	if ( ! is_numeric( $raw ) || (float) $raw <= 0 ) {
		return '';
	}

	$text = rtrim( rtrim( number_format( (float) $raw, 3, '.', '' ), '0' ), '.' );

	return '' === $text ? '' : $text;
}

/**
 * عدد و واحد از متن ردیف «تعداد» جدول («۱۲ عدد»، «12 عددی»، «۲ کیلو»).
 *
 * @return array{qty: string, unit: string}|null بدون عدد = null.
 */
function hodima_seo_torob_parse_quantity( string $text ): ?array {

	$latin = hodima_seo_torob_latin_digits( $text );

	// جداکننده هزارگان (1,200) پیش از خواندن عدد حذف می‌شود
	$latin = (string) preg_replace( '/(\d),(?=\d{3}\b)/u', '$1', $latin );

	if ( ! preg_match( '/\d+(?:[.,]\d+)?/u', $latin, $match ) ) {
		return null;
	}

	$qty = hodima_seo_torob_number( $match[0] );
	if ( '' === $qty ) {
		return null;
	}

	$unit = 'عدد';
	foreach ( hodima_seo_torob_units() as $candidate ) {
		if ( str_contains( $text, $candidate ) ) {
			$unit = $candidate;
			break;
		}
	}

	return [ 'qty' => $qty, 'unit' => $unit ];
}

/** متن ردیف «تعداد» جدول مشخصات محصول (Hodima Commerce)؛ '' اگر نیست یا مقدار پیش‌فرض است. */
function hodima_seo_torob_table_text( WC_Product $product ): string {

	if ( ! class_exists( 'Hodima_Product_Specs_Table' ) ) {
		return '';
	}

	$row = Hodima_Product_Specs_Table::get_instance()->_prepare_specs_data( $product )['specs_data']['quantity'] ?? null;

	if ( ! is_array( $row ) || ! empty( $row['is_fallback'] ) ) {
		return '';
	}

	return trim( wp_strip_all_tags( (string) ( $row['value'] ?? '' ) ) );
}

/** شکل نام از «ابزارهای هدیما ← ترب». */
function hodima_seo_torob_template(): string {
	return hodima_seo_torob_setting( 'title' );
}

/** جایگذاری نام، تعداد و واحد در شکل نام (برچسب فارسی یا انگلیسی). */
function hodima_seo_torob_format( string $template, string $name, string $qty, string $unit ): string {

	$qty  = hodima_seo_torob_persian_digits( $qty );
	$text = strtr( $template, [
		'{نام}'   => $name,
		'{name}'  => $name,
		'{تعداد}' => $qty,
		'{qty}'   => $qty,
		'{واحد}'  => $unit,
		'{unit}'  => $unit,
	] );

	return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
}

/**
 * همه اطلاعات ترب یک محصول (فیلتر نام، تب ویرایش، گزارش).
 *
 * @return array{
 *   name: string, title: string, custom: bool, applies: bool,
 *   qty: string, unit: string, source: string, table: string,
 *   auto_qty: string, auto_unit: string, auto_source: string,
 *   price: string, min_order: bool, warning: string, problem: string,
 *   unit_price: float, min: float
 * }
 *   title: نامی که ترب می‌گیرد ('' = همان نام محصول)؛ applies: نام خودکار ساخته می‌شود؛
 *   source / auto_source: manual | table | cart | none؛ problem: '' | unread | mismatch | none.
 */
function hodima_seo_torob_info( WC_Product $product ): array {

	$name   = $product->get_name( 'edit' ); // بدون فیلتر نام پایین
	$custom = trim( (string) $product->get_meta( HODIMA_SEO_TOROB_META['title'], true ) );
	$feed   = hodima_seo_product_feed_price( $product, 'torob' );
	$unit_p = (float) hodima_seo_unit_price( $product );
	$min    = (float) $product->get_meta( '_wholesale_price', true );

	// تعداد خودکار: جدول، وگرنه حداقل تعدادی که سبد اجبار می‌کند
	$table  = hodima_seo_torob_table_text( $product );
	$parsed = '' !== $table ? hodima_seo_torob_parse_quantity( $table ) : null;
	$cart   = ( $min > 0 && $unit_p > 0 ) ? (string) (int) ceil( round( $min / $unit_p, 6 ) ) : '';

	[ $auto_qty, $auto_unit, $auto_source ] = match ( true ) {
		null !== $parsed => [ $parsed['qty'], $parsed['unit'], 'table' ],
		'' !== $cart     => [ $cart, 'عدد', 'cart' ],
		default          => [ '', 'عدد', 'none' ],
	};

	$manual_qty  = hodima_seo_torob_number( (string) $product->get_meta( HODIMA_SEO_TOROB_META['qty'], true ) );
	$manual_unit = trim( (string) $product->get_meta( HODIMA_SEO_TOROB_META['unit'], true ) );

	$qty    = '' !== $manual_qty ? $manual_qty : $auto_qty;
	$unit   = '' !== $manual_unit ? $manual_unit : $auto_unit;
	$source = '' !== $manual_qty ? 'manual' : $auto_source;

	$applies = $feed['min_order'] && '' !== $qty;

	// هشدار فقط برای تعداد خودکار؛ تعداد دستی تصمیم مدیر است (مثلا کیلو با قیمت تک دیگر)
	$warning = '';
	$problem = '';
	if ( $feed['min_order'] && 'manual' !== $source ) {
		if ( '' !== $table && null === $parsed ) {
			$problem = 'unread';
			$warning = sprintf( 'عددی در ردیف «تعداد» جدول («%s») پیدا نشد؛ حداقل تعداد سبد خرید استفاده شد.', $table );
		} elseif ( '' !== $qty && $unit_p > 0 && abs( (float) $qty * $unit_p - $min ) > 0.5 ) {
			$problem = 'mismatch';
			$warning = sprintf(
				'تعداد × قیمت تک = %1$s تومان، ولی مبلغ حداقل سفارش %2$s تومان است.',
				number_format_i18n( (float) $qty * $unit_p ),
				number_format_i18n( $min )
			);
		} elseif ( '' === $qty ) {
			$problem = 'none';
			$warning = 'تعداد پیدا نشد؛ نام بدون تعداد می‌رود.';
		}
	}

	$title = match ( true ) {
		'' !== $custom => $custom,
		$applies       => hodima_seo_torob_format( hodima_seo_torob_template(), $name, $qty, $unit ),
		default        => '',
	};

	return [
		'name'        => $name,
		'title'       => $title,
		'custom'      => '' !== $custom,
		'applies'     => $applies,
		'qty'         => $qty,
		'unit'        => $unit,
		'source'      => $source,
		'table'       => $table,
		'auto_qty'    => $auto_qty,
		'auto_unit'   => $auto_unit,
		'auto_source' => $auto_source,
		'price'       => $feed['amount'],
		'min_order'   => $feed['min_order'],
		'warning'     => $warning,
		'problem'     => $problem,
		'unit_price'  => $unit_p,
		'min'         => $min,
	];
}

/* =========================================================================
 * نام در وب‌سرویس ترب
 * ========================================================================= */

/**
 * افزونه ترب نام را با get_name() می‌خواند (تنوع: نام محصول مادر). فقط در
 * درخواست ترب و پیش‌نمایش خود افزونه ترب؛ صفحه‌های سایت هرگز.
 */
function hodima_seo_torob_name_filter( mixed $name, mixed $product ): mixed {

	static $busy = false;

	if ( $busy || ! $product instanceof WC_Product || ! function_exists( 'hodima_seo_is_feed_request' ) || ! hodima_seo_is_feed_request() ) {
		return $name;
	}

	$busy = true;
	try {
		$title = hodima_seo_torob_info( $product )['title'];
	} finally {
		$busy = false;
	}

	return '' !== $title ? $title : $name;
}
add_filter( 'woocommerce_product_get_name', 'hodima_seo_torob_name_filter', 99, 2 );

/* =========================================================================
 * تب «ترب» در ویرایش محصول (داده‌های محصول)
 * ========================================================================= */

add_filter( 'woocommerce_product_data_tabs', static function ( mixed $tabs ): mixed {

	if ( is_array( $tabs ) ) {
		$tabs['hodima_torob'] = [
			'label'    => 'ترب',
			'target'   => 'hodima_torob_data',
			'class'    => [],
			'priority' => 85,
		];
	}

	return $tabs;
} );

add_action( 'woocommerce_product_data_panels', 'hodima_seo_torob_render_panel' );

/** محتوای تب «ترب». */
function hodima_seo_torob_render_panel(): void {

	global $post;

	$product = $post instanceof WP_Post ? wc_get_product( $post->ID ) : null;
	$info    = $product instanceof WC_Product ? hodima_seo_torob_info( $product ) : null;
	$mode    = hodima_seo_torob_setting( 'price' );
	$labels  = hodima_seo_torob_source_labels();

	$auto_qty  = $info ? hodima_seo_torob_persian_digits( $info['auto_qty'] ) : '';
	$qty_hint  = '' !== $auto_qty ? sprintf( 'خودکار: %s (%s)', $auto_qty, $labels[ $info['auto_source'] ] ?? '' ) : 'خودکار';
	$unit_hint = 'خودکار: ' . ( $info ? $info['auto_unit'] : 'عدد' );
	$settings  = hodima_seo_torob_page_url();
	?>
	<div id="hodima_torob_data" class="panel woocommerce_options_panel hidden hodima-torob">
		<div
			class="hodima-torob__preview"
			data-hodima-torob
			data-template="<?php echo esc_attr( hodima_seo_torob_template() ); ?>"
			data-applies="<?php echo esc_attr( $info && $info['min_order'] ? '1' : '0' ); ?>"
			data-auto-qty="<?php echo esc_attr( $info['auto_qty'] ?? '' ); ?>"
			data-auto-unit="<?php echo esc_attr( $info['auto_unit'] ?? 'عدد' ); ?>"
		>
			<p class="hodima-torob__label">ترب این نام را می‌گیرد:</p>
			<p class="hodima-torob__name"><strong data-hodima-torob-name><?php echo esc_html( $info ? ( '' !== $info['title'] ? $info['title'] : $info['name'] ) : '' ); ?></strong></p>
			<?php if ( $info && '' !== $info['price'] ) : ?>
				<p class="hodima-torob__price">
					<?php echo esc_html( $info['min_order'] ? 'مبلغ حداقل سفارش: ' : 'قیمت: ' ); ?>
					<?php echo wp_kses_post( wc_price( (float) $info['price'] ) ); ?>
				</p>
			<?php endif; ?>
			<?php if ( $info && '' !== $info['warning'] ) : ?>
				<p class="hodima-torob__warning"><span class="dashicons dashicons-warning" aria-hidden="true"></span> <?php echo esc_html( $info['warning'] ); ?></p>
			<?php endif; ?>
			<?php if ( 'min_order' !== $mode ) : ?>
				<p class="hodima-torob__note">
					«قیمتی که ترب بگیرد» روی «قیمت تک» است؛ نام خودکار ساخته نمی‌شود و فقط «نام کامل دلخواه» (اگر پر شود) به ترب می‌رود.
					<a href="<?php echo esc_url( $settings ); ?>">ابزارهای هدیما ← ترب</a>
				</p>
			<?php elseif ( $info && ! $info['min_order'] && ! $info['custom'] ) : ?>
				<p class="hodima-torob__note">این محصول ساده با «حداقل سفارش» نیست؛ ترب همان نام و قیمت تک را می‌گیرد.</p>
			<?php endif; ?>
		</div>

		<div class="options_group">
			<?php
			woocommerce_wp_text_input( [
				'id'                => HODIMA_SEO_TOROB_META['qty'],
				'label'             => 'حداقل تعداد',
				'placeholder'       => $qty_hint,
				'description'       => 'خالی = خودکار از ردیف «تعداد» جدول مشخصات (اگر نبود: مبلغ حداقل سفارش تقسیم بر قیمت تک).',
				'desc_tip'          => true,
				// متن، نه number: کادر عددی مرورگر رقم فارسی صفحه‌کلید فارسی را نمی‌پذیرد؛ ذخیره هر دو را می‌خواند
				'custom_attributes' => [ 'inputmode' => 'decimal', 'maxlength' => '10' ],
			] );

			woocommerce_wp_text_input( [
				'id'                => HODIMA_SEO_TOROB_META['unit'],
				'label'             => 'واحد',
				'placeholder'       => $unit_hint,
				'description'       => 'خالی = خودکار (واحدی که در جدول نوشته شده، وگرنه «عدد»). مثلا کیلو، کارتن، بسته.',
				'desc_tip'          => true,
				'custom_attributes' => [ 'list' => 'hodima-torob-units', 'maxlength' => '20' ],
			] );
			?>
			<datalist id="hodima-torob-units">
				<?php foreach ( hodima_seo_torob_units() as $hodima_unit ) : ?>
					<option value="<?php echo esc_attr( $hodima_unit ); ?>"></option>
				<?php endforeach; ?>
			</datalist>
			<?php
			woocommerce_wp_text_input( [
				'id'                => HODIMA_SEO_TOROB_META['title'],
				'label'             => 'نام کامل دلخواه',
				'placeholder'       => 'خالی = ساخت خودکار',
				'description'       => 'فقط برای موارد خاص: اگر پر شود، دقیقا همین متن به‌جای نام محصول به ترب می‌رود. نام محصول در سایت عوض نمی‌شود.',
				'desc_tip'          => true,
				'custom_attributes' => [ 'maxlength' => '300' ],
			] );
			?>
		</div>
	</div>
	<?php
}

/** ذخیره تب «ترب» (nonce را ووکامرس پیش از این هوک بررسی کرده است). */
add_action( 'woocommerce_admin_process_product_object', static function ( mixed $product ): void {

	if ( ! $product instanceof WC_Product ) {
		return;
	}

	// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce فرم محصول را ووکامرس بررسی کرده است
	$raw = static fn( string $key ): string => isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( (string) $_POST[ $key ] ) ) : '';
	// phpcs:enable WordPress.Security.NonceVerification.Missing

	$values = [
		HODIMA_SEO_TOROB_META['qty']   => hodima_seo_torob_number( $raw( HODIMA_SEO_TOROB_META['qty'] ) ),
		HODIMA_SEO_TOROB_META['unit']  => mb_substr( $raw( HODIMA_SEO_TOROB_META['unit'] ), 0, 20 ),
		HODIMA_SEO_TOROB_META['title'] => mb_substr( $raw( HODIMA_SEO_TOROB_META['title'] ), 0, 300 ),
	];

	foreach ( $values as $key => $value ) {
		'' === $value ? $product->delete_meta_data( $key ) : $product->update_meta_data( $key, $value );
	}
} );

/** اسکریپت پیش‌نمایش زنده و استایل تب، فقط در ویرایش محصول. */
add_action( 'admin_enqueue_scripts', static function ( mixed $hook ): void {

	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

	if ( ! in_array( $hook, [ 'post.php', 'post-new.php' ], true ) || ! $screen || 'product' !== $screen->post_type ) {
		return;
	}

	$base = ( defined( 'HODIMA_SEO_URL' ) ? HODIMA_SEO_URL : plugins_url( '', dirname( __DIR__ ) . '/hodima-seo.php' ) ) . '/assets/';
	$ver  = defined( 'HODIMA_SEO_VERSION' ) ? HODIMA_SEO_VERSION : false;

	wp_enqueue_style( 'hodima-seo-torob', $base . 'torob-product.css', [], $ver );
	wp_enqueue_script( 'hodima-seo-torob', $base . 'torob-product.js', [], $ver, [ 'in_footer' => true, 'strategy' => 'defer' ] );
} );

/* =========================================================================
 * گزارش صفحه «ابزارهای هدیما ← ترب»
 * ========================================================================= */

/** @return array<string, string> برچسب منبع تعداد. */
function hodima_seo_torob_source_labels(): array {
	return [
		'table'  => 'از جدول مشخصات',
		'cart'   => 'از مبلغ حداقل سفارش تقسیم بر قیمت تک',
		'manual' => 'دستی (تب ترب)',
		'custom' => 'نام کامل دلخواه',
		'none'   => 'نامشخص',
	];
}

/**
 * وضعیت نام ترب همه محصولاتی که حداقل سفارش یا نام دلخواه ترب دارند.
 * کش یک‌ساعته؛ با ذخیره/حذف محصول، ذخیره تنظیم‌های ترب یا دکمه «به‌روزرسانی» پاک می‌شود.
 *
 * @return array{
 *   mode: string, total: int, time: int, counts: array<string, int>,
 *   issues: list<array<string, mixed>>, samples: list<array<string, mixed>>
 * }
 */
function hodima_seo_torob_report(): array {

	$mode   = hodima_seo_torob_setting( 'price' );
	$cached = get_transient( HODIMA_SEO_TOROB_REPORT_KEY );

	if ( is_array( $cached ) && ( $cached['mode'] ?? '' ) === $mode && isset( $cached['time'] ) ) {
		return $cached;
	}

	$report = [
		'mode'    => $mode,
		'total'   => 0,
		'time'    => time(),
		'counts'  => [ 'table' => 0, 'cart' => 0, 'manual' => 0, 'custom' => 0, 'none' => 0, 'warning' => 0 ],
		'issues'  => [],
		'samples' => [],
	];

	if ( ! function_exists( 'wc_get_product' ) ) {
		return $report;
	}

	$ids = get_posts( [
		'post_type'              => 'product',
		'post_status'            => 'publish',
		'fields'                 => 'ids',
		'posts_per_page'         => -1,
		'no_found_rows'          => true,
		'update_post_term_cache' => true,
		'orderby'                => 'ID',
		'order'                  => 'DESC',
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- فقط صفحه پیشخوان ترب، با کش
		'meta_query'             => [
			'relation' => 'OR',
			[ 'key' => '_wholesale_price', 'value' => 0, 'compare' => '>', 'type' => 'DECIMAL(20,4)' ],
			[ 'key' => HODIMA_SEO_TOROB_META['title'], 'compare' => 'EXISTS' ],
		],
	] );

	foreach ( $ids as $id ) {

		$product = wc_get_product( (int) $id );
		if ( ! $product instanceof WC_Product ) {
			continue;
		}

		$info = hodima_seo_torob_info( $product );
		if ( ! $info['min_order'] && ! $info['custom'] ) {
			continue;
		}

		++$report['total'];
		++$report['counts'][ $info['custom'] ? 'custom' : $info['source'] ];

		$row = [
			'id'         => (int) $id,
			'name'       => $info['name'],
			'title'      => '' !== $info['title'] ? $info['title'] : $info['name'],
			'price'      => $info['price'],
			'qty'        => $info['qty'],
			'unit'       => $info['unit'],
			'source'     => $info['custom'] ? 'custom' : $info['source'],
			'table'      => $info['table'],
			'unit_price' => $info['unit_price'],
			'min'        => $info['min'],
			'problem'    => $info['custom'] ? '' : $info['problem'],
			'warning'    => $info['custom'] ? '' : $info['warning'],
		];

		if ( '' !== $row['problem'] ) {
			++$report['counts']['warning'];
			if ( count( $report['issues'] ) < 500 ) {
				$report['issues'][] = $row;
			}
		} elseif ( count( $report['samples'] ) < 5 ) {
			$report['samples'][] = $row;
		}
	}

	set_transient( HODIMA_SEO_TOROB_REPORT_KEY, $report, HOUR_IN_SECONDS );

	return $report;
}

/** کش گزارش با تغییر محصول یا تنظیم‌های ترب (شکل نام، حالت قیمت) کهنه می‌شود. */
function hodima_seo_torob_flush_report(): void {
	delete_transient( HODIMA_SEO_TOROB_REPORT_KEY );
}
add_action( 'woocommerce_update_product', 'hodima_seo_torob_flush_report' );
add_action( 'woocommerce_new_product', 'hodima_seo_torob_flush_report' );
add_action( 'trashed_post', 'hodima_seo_torob_flush_report' );
add_action( 'update_option_' . HODIMA_SEO_TOROB_OPTION, 'hodima_seo_torob_flush_report' );

// صفحه «ابزارهای هدیما ← ترب» (تنظیم‌ها و گزارش)
if ( is_admin() ) {
	require_once __DIR__ . '/torob-admin.php';
}
