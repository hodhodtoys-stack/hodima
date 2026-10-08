<?php
/**
 * Single product content
 *
 * بازنویسی قالب content-single-product.php ووکامرس با چیدمان اختصاصی.
 * شماره @version همان نسخه قالب اصلی است که این فایل بر پایه آن است؛
 * «ووکامرس ← وضعیت ← قالب‌ها» با تغییر آن در آینده هشدار می‌دهد.
 *
 * تفاوت‌های عمدی با قالب اصلی: هوک‌های woocommerce_single_product_summary و
 * woocommerce_after_single_product_summary صدا زده نمی‌شوند؛ قیمت، دکمه
 * افزودن به سبد، مشخصات و محصولات مرتبط مستقیم در همین قالب چیده شده‌اند.
 * افزونه‌ای که فقط به این دو هوک وصل می‌شود، در صفحه محصول دیده نمی‌شود.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package Hodima\WooCommerce
 * @version 3.6.0
 *
 * منطق مشترک (موجودی، حداقل سفارش، LCP، ساختار) در
 * inc/woocommerce/product-page.php است و اسکیمای محصول هم از همان توابع
 * استفاده می‌کند — آنچه بازدیدکننده می‌بیند و آنچه گوگل می‌خواند یکی است.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- هوک‌های خود ووکامرس در قالب ووکامرس (باید همین نام‌ها اجرا شوند)
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- قالب داخل تابع (load_template/wc_get_template_part) لود می‌شود؛ متغیرها محلی‌اند، نه سراسری

global $product;

do_action( 'woocommerce_before_single_product' );

if ( post_password_required() ) {
	echo get_the_password_form(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- فرم خود وردپرس
	return;
}

$hp_location  = function_exists( 'hodima_product_stock_location' ) ? hodima_product_stock_location( $product ) : '';
$hp_min_total = (float) get_post_meta( $product->get_id(), '_wholesale_price', true );
$hp_sku       = (string) $product->get_sku();
$hp_modified  = $product->get_date_modified();

/*
 * متن وضعیت‌های انبار از Hodima Commerce (همان گزینه‌های فیلد «وضعیت موجودی»)؛
 * قبلا اینجا ثابت بود — بازسازی قالب، مرحله ۲. بدون افزونه، متن‌های پیش‌فرض.
 */
$hp_stock_texts = function_exists( 'hodima_product_stock_texts' ) ? hodima_product_stock_texts() : [
	'iran'  => [ 'label' => 'موجود در انبار ایران', 'notice' => 'امکان ثبت پیش فاکتور دارد' ],
	'china' => [ 'label' => 'موجود در انبار چین', 'notice' => 'امکان ثبت پیش خرید دارد' ],
	'out'   => [ 'label' => 'اتمام موجودی', 'notice' => '' ],
];

$hp_stock_labels = array_filter( array_map( static fn( $t ): string => (string) ( $t['label'] ?? '' ), $hp_stock_texts ) );

$hp_notices = [];
foreach ( $hp_stock_texts as $hp_key => $hp_text ) {
	if ( '' !== (string) ( $hp_text['notice'] ?? '' ) ) {
		$hp_notices[ $hp_key ] = [ (string) $hp_text['notice'], 'custom-stock-notice-' . sanitize_html_class( (string) $hp_key ) ];
	}
}

$hp_can_buy = ( 'out' !== $hp_location );

// متن‌ها و بخش‌های صفحه از «تنظیمات قالب ← صفحه محصول» (قبلا ثابت در همین فایل)
$hp_show = static fn( string $part ): bool => (bool) hodima_setting( 'product_show_' . $part );

$hp_table_shown = class_exists( 'Hodima_Product_Specs_Table' )
	&& Hodima_Product_Specs_Table::get_instance()->is_shown_for( $product );

?>

<div class="custom-product-page-container">

	<main id="main-content" class="hodima-section-box section-product-main">
		<div id="product-<?php the_ID(); ?>" <?php wc_product_class( 'custom-single-product-wrapper', $product ); ?>>

			<div class="custom-product-gallery">
				<?php do_action( 'woocommerce_before_single_product_summary' ); ?>
			</div>

			<div class="custom-product-summary">

				<h1 class="product_title entry-title"><?php the_title(); ?></h1>

				<div class="product-rating-box">
					<?php woocommerce_template_single_rating(); ?>
				</div>

				<?php
				/*
				 * اطلاعات کلیدی در <dl>.
				 *
				 * فهرست تعریف (برچسب ← مقدار) ساختاری است که موتورهای جستجو و
				 * مدل‌های زبانی مستقیم به صورت «ویژگی: مقدار» استخراج می‌کنند.
				 * همه مقادیر با اسکیمای محصول یکی‌اند: قیمت، حداقل تعداد
				 * (eligibleQuantity)، وضعیت انبار (availability) و کد (sku).
				 *
				 * تاریخ آخرین به‌روزرسانی: سیگنال تازگی برای قیمت عمده —
				 * موتورهای مولد اطلاعات تاریخ‌دار را ترجیح می‌دهند.
				 */
				?>
				<dl class="wholesale-price-container">

					<div class="unit-price-line">
						<dt><strong>قیمت:</strong></dt>
						<dd class="unit-price">
							<?php
							echo $product->is_type( 'variable' ) || '' === (string) $product->get_price()
								? wp_kses_post( $product->get_price_html() ?: esc_html( (string) hodima_setting( 'product_price_empty' ) ) )
								: wp_kses_post( wc_price( $product->get_price() ) );
							?>
						</dd>
					</div>

					<?php if ( $hp_min_total > 0 ) : ?>
						<div class="wholesale-price-line">
							<dt><strong>حداقل سفارش:</strong></dt>
							<dd class="wholesale-price"><?php echo wp_kses_post( wc_price( $hp_min_total ) ); ?></dd>
						</div>
					<?php endif; ?>



					<?php // هر <div> داخل <dl> فقط یک گروه dt/dd می‌تواند داشته باشد ?>
					<?php if ( '' !== $hp_sku ) : ?>
						<div class="hp-meta-line">
							<dt>کد محصول:</dt>
							<dd><span class="hodima-num" translate="no"><?php echo esc_html( $hp_sku ); ?></span></dd>
						</div>
					<?php endif; ?>

					<?php
					/*
					 * «آخرین به‌روزرسانی» وقتی جدول مشخصات هست، به صورت ردیف آخر
					 * تمام‌عرض همان جدول نمایش داده می‌شود (inc/hodima-woo-table).
					 * اینجا فقط برای محصولی که جدول ندارد، تا تاریخ گم نشود.
					 */
					?>
					<?php if ( $hp_modified && ! $hp_table_shown ) : ?>
						<div class="hp-meta-line">
							<dt>آخرین به‌روزرسانی:</dt>
							<dd><time datetime="<?php echo esc_attr( $hp_modified->date( 'c' ) ); ?>"><?php echo esc_html( wp_date( 'j F Y', $hp_modified->getTimestamp() ) ); ?></time></dd>
						</div>
					<?php endif; ?>

				</dl>

				<div class="woocommerce-product-details__short-description">
					<?php echo apply_filters( 'woocommerce_short_description', $product->get_short_description() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- خروجی فیلتر خود ووکامرس (مثل قالب اصلی) ?>
				</div>

				<?php
				/*
				 * پادکست: زیر جدول مشخصات، پیش از ردیف خرید.
				 * به شکل یک ردیف فشرده («پادکست» + پلیر) — نه سرتیتر جدا —
				 * پس دکمه خرید را از دید اول بیرون نمی‌برد.
				 */
				?>
				<?php
				// فقط برچسب کوتاه («پادکست») داخل کادر پلیر؛ عنوان کامل برچسب دسترس‌پذیری پلیر می‌ماند
				$hp_voice = $hp_show( 'podcast' ) ? hodima_theme_media_html( 'voice', [ 'title' => (string) hodima_setting( 'product_podcast_label' ), 'layout' => 'inline' ] ) : '';
				?>
				<?php if ( '' !== $hp_voice ) : ?>
					<div class="custom-voice-shortcode"><?php echo $hp_voice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- خروجی شورت‌کد افزونه Hodima Media ?></div>
				<?php endif; ?>

				<?php
				/*
				 * ردیف خرید: تعداد + دکمه + وضعیت پیش‌خرید/پیش‌فاکتور کنار هم.
				 * قبلا اعلان وضعیت یک کادر تمام‌عرض جداگانه زیر دکمه بود.
				 */
				?>
				<div class="hp-buy-row">
					<div class="product-add-to-cart-box">
						<?php if ( $hp_can_buy ) : ?>
							<?php woocommerce_template_single_add_to_cart(); ?>
						<?php else : ?>
							<div class="custom-out-of-stock-message">
								<p><?php echo esc_html( (string) hodima_setting( 'product_out_of_stock' ) ); ?></p>
							</div>
						<?php endif; ?>
					</div>

					<?php
					/*
					 * وضعیت انبار کنار دکمه خرید (قبلا یک کادر جدا بالای جدول).
					 * دسکتاپ: یک ردیف چهارتایی. موبایل: دو ردیف دوتایی.
					 */
					?>
					<?php if ( isset( $hp_stock_labels[ $hp_location ] ) ) : ?>
						<?php // نام کلاس‌های قبلی حفظ شد (استایل CSS به آن‌ها وابسته است) ?>
						<div class="custom-stock-status-line hp-stock-pill stock-status-<?php echo esc_attr( [ 'iran' => 'iran_stock', 'china' => 'china_stock', 'out' => 'out_of_stock' ][ $hp_location ] ); ?>">
							<span class="custom-stock-status-text"><?php echo esc_html( $hp_stock_labels[ $hp_location ] ); ?></span>
						</div>
					<?php endif; ?>

					<?php if ( $hp_can_buy && isset( $hp_notices[ $hp_location ] ) ) : ?>
						<div class="custom-stock-notice <?php echo esc_attr( $hp_notices[ $hp_location ][1] ); ?>">
							<p><?php echo esc_html( $hp_notices[ $hp_location ][0] ); ?></p>
						</div>
					<?php endif; ?>
				</div>
				<?php
				/*
				 * باگ قبلی: endif بلوک پادکست اینجا (بعد از ردیف خرید) بود؛ بدون افزونه
				 * Hodima Media (شورت‌کد hook_voice) دکمه افزودن به سبد و وضعیت انبار
				 * اصلا چاپ نمی‌شدند. حالا بلوک پادکست جدا بسته می‌شود.
				 */
				?>

			</div>
		</div>
	</main>

	<section class="hodima-section-box section-description" aria-label="<?php echo esc_attr( 'توضیحات ' . $product->get_name() ); ?>">
		<?php
		$hp_desc = apply_filters( 'the_content', get_the_content() ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- فیلتر خود وردپرس
		$hp_faq  = $hp_show( 'faq' ) ? hodima_theme_media_html( 'faq' ) : '';

		if ( '' !== $hp_faq ) {
			$hp_desc .= '<div class="faq-inline-wrapper">' . $hp_faq . '</div>';
		}

		echo shortcode_exists( 'expand_product_description' )
			? do_shortcode( '[expand_product_description]' . $hp_desc . '[/expand_product_description]' ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- متن محصول (the_content)
			: $hp_desc; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- متن محصول (the_content)
		?>
	</section>

	<?php
	$hp_video   = $hp_show( 'video' ) ? hodima_theme_media_html( 'video' ) : '';
	$hp_reviews = shortcode_exists( 'expand_product_reviews' ) && $hp_show( 'reviews' ) ? trim( do_shortcode( '[expand_product_reviews]' ) ) : '';
	$hp_upsells = max( 0, (int) hodima_setting( 'product_upsells_limit' ) );
	?>
	<?php if ( '' !== $hp_video || '' !== $hp_reviews ) : ?>
	<section class="hodima-section-box section-reviews">
		<?php if ( '' !== $hp_video ) : ?>
			<div class="video-wrapper"><?php echo $hp_video; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- خروجی شورت‌کد افزونه Hodima Media ?></div>
		<?php endif; ?>

		<?php echo $hp_reviews; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- خروجی شورت‌کد افزونه ?>
	</section>
	<?php endif; ?>

	<?php if ( $hp_upsells > 0 && $product->get_upsell_ids() ) : ?>
		<section class="hodima-section-box section-upsells product-card-scope">
			<?php function_exists( 'hodima_render_upsells' ) ? hodima_render_upsells( $hp_upsells ) : woocommerce_upsell_display( $hp_upsells, $hp_upsells ); ?>
		</section>
	<?php endif; ?>

</div>

<?php do_action( 'woocommerce_after_single_product' );
