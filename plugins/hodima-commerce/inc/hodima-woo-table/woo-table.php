<?php
/**
 * Hodima Product Specs Table - HTML Generator Only (PHP 8.1+)
 *
 * @package Hodima
 * @version 2.10.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// enum بدون گارد، در صورت include دوباره فایل خطای کشنده می‌دهد.
if ( ! enum_exists( 'Hodima_Source_Type' ) ) {
	enum Hodima_Source_Type: string {
		case Attribute = 'attribute';
		case Meta      = 'meta';
		case Property  = 'property';
	}
}

if ( ! class_exists( 'Hodima_Product_Specs_Table' ) ) {

	final class Hodima_Product_Specs_Table {

		private static ?self $instance = null;

		public const VERSION = '2.10.0';

		/**
		 * حافظه موقت همین درخواست، کلید = شناسه محصول.
		 *
		 * _prepare_specs_data() در هر بازدید صفحه محصول *دو بار* صدا زده
		 * می‌شود: یک بار از schema/product-schema-pro.php روی wp_head و یک
		 * بار از شورت‌کد روی the_content. کش ترنزینت فقط HTML مسیر دوم را
		 * پوشش می‌داد و مسیر اول هیچ کشی نداشت.
		 */
		private array $prepared_memo = [];

		public static function get_instance(): self {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		private function __construct() {

			add_shortcode( 'woo_specs_table', [ $this, 'shortcode_handler' ] );

			add_action( 'save_post_product', [ $this, 'clear_transient_cache' ], 10, 1 );
			add_action( 'woocommerce_update_product_variation', [ $this, 'clear_transient_cache' ], 10, 1 );

			/*
			 * تغییر نام یا حذف یک ترم ویژگی (مثلا «استیل» در pa_material)
			 * خروجی get_attribute() همه محصولات مرتبط را عوض می‌کند، ولی
			 * هیچ‌کدام از هوک‌های بالا اجرا نمی‌شود. بدون این، جدول تا ۱۲
			 * ساعت مقدار قدیمی را نشان می‌داد.
			 */
			add_action( 'edited_term', [ $this, 'maybe_flush_attribute_cache' ], 10, 3 );
			add_action( 'delete_term', [ $this, 'maybe_flush_attribute_cache' ], 10, 3 );

			add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ] );
		}

		/*--------------------------------------------------------------
		# Assets
		--------------------------------------------------------------*/

		/**
		 * استایل ثبت می‌شود و فقط هنگام رندر واقعی جدول enqueue می‌شود.
		 *
		 * نسخه قبلی شرط is_product() داشت. اگر شورت‌کد جای دیگری استفاده
		 * می‌شد (توضیح کوتاه در حلقه آرشیو، یا یک بلوک محصولات مرتبط)
		 * جدول بدون هیچ استایلی رندر می‌شد.
		 */
		public function register_assets(): void {
			wp_register_style(
				'hodima-woo-specs-table',
				HODIMA_COMMERCE_URL . '/inc/hodima-woo-table/woo-table.css',
				[],
				/*
				 * نسخه = زمان تغییر فایل. با نسخه ثابت (self::VERSION) آدرس CSS
				 * بعد از تغییر فایل عوض نمی‌شد و مرورگر و لایت‌اسپید همان نسخه
				 * قدیمی را سرو می‌کردند — تغییر ۳۰/۷۰ جدول دیده نمی‌شد.
				 */
				(string) filemtime( HODIMA_COMMERCE_DIR . '/inc/hodima-woo-table/woo-table.css' )
			);
		}

		/*--------------------------------------------------------------
		# Shortcode
		--------------------------------------------------------------*/

		public function shortcode_handler( $atts = [] ): string {

			if ( is_admin() && ! wp_doing_ajax() ) {
				return '';
			}

			if ( ! function_exists( 'wc_get_product' ) ) {
				return '';
			}

			// نسخه قبلی $atts را کامل نادیده می‌گرفت، پس نمایش مشخصات یک
			// محصول دیگر (مثلا در یک برگه مقایسه) ممکن نبود.
			$atts = shortcode_atts( [ 'id' => '' ], (array) $atts, 'woo_specs_table' );

			$product_id = absint( $atts['id'] ) ?: (int) get_the_ID();

			if ( ! $product_id ) {
				return '';
			}

			$product = wc_get_product( $product_id );

			if ( ! $product instanceof WC_Product ) {
				return '';
			}

			return $this->get_specs_table_html( $product );
		}

		/*--------------------------------------------------------------
		# HTML
		--------------------------------------------------------------*/

		public function get_specs_table_html( WC_Product $product_obj ): string {

			$transient_key = $this->transient_key( $product_obj->get_id() );
			$cached_html   = get_transient( $transient_key );

			if ( false !== $cached_html ) {
				if ( '' !== $cached_html ) {
					wp_enqueue_style( 'hodima-woo-specs-table' );
				}
				return $this->inject_updated_row( (string) $cached_html, $product_obj );
			}

			return $this->inject_updated_row( $this->_generate_and_cache_table_html( $product_obj, $transient_key ), $product_obj );
		}

		/** آیا جدول مشخصات این محصول در توضیح کوتاه نمایش داده می‌شود؟ */
		public function is_shown_for( WC_Product $product ): bool {
			return has_shortcode( (string) $product->get_short_description(), 'woo_specs_table' )
				&& '' !== $this->get_specs_table_html( $product );
		}

		/**
		 * ردیف آخر جدول: «بروزرسانی | تاریخ: …».
		 *
		 * تاریخ *داخل* HTML کش‌شده نیست: کش فقط یک نشانگر دارد و تاریخ هر بار
		 * تازه ساخته می‌شود. موجودی و قیمت بدون ذخیره معمولی محصول هم عوض
		 * می‌شوند (مثلا کم شدن موجودی با هر سفارش) و تاریخ کش‌شده تا ۱۲
		 * ساعت قدیمی می‌ماند. فقط در صفحه خود همان محصول.
		 */
		private function inject_updated_row( string $html, WC_Product $product ): string {

			if ( '' === $html || ! str_contains( $html, '<!--hodima-specs-updated-->' ) ) {
				return $html;
			}

			$modified = $product->get_date_modified();
			$show     = $modified
				&& function_exists( 'is_product' ) && is_product()
				&& (int) get_queried_object_id() === (int) $product->get_id()
				&& apply_filters( 'hodima_specs_table_show_updated', true, $product );

			/*
			 * ردیف عادی دو ستونی (۳۰/۷۰) داخل tbody، نه ردیف تمام‌عرض در tfoot.
			 * چون آخرین ردیف tbody است، همان خط رنگی پایین جدول زیر آن می‌افتد —
			 * یک خط رنگی، نه دو خط (قبلا یکی زیر آخرین مشخصه و یکی زیر tfoot).
			 * سلول دوم فقط «تاریخ: …»؛ قبلا «آخرین به‌روزرسانی: …» بود و کلمه
			 * به‌روزرسانی دو بار در یک ردیف تکرار می‌شد (عنوان ردیف هم همین است).
			 */
			$row = $show
				? '<tr class="hodima-specs-updated"><th scope="row">بروزرسانی</th><td>تاریخ: <time datetime="'
					. esc_attr( $modified->date( 'c' ) ) . '">' . esc_html( wp_date( 'j F Y', $modified->getTimestamp() ) ) . '</time></td></tr>'
				: '';

			return str_replace( '<!--hodima-specs-updated-->', $row, $html );
		}

		private function _generate_and_cache_table_html( WC_Product $product, string $transient_key ): string {

			$prepared   = $this->_prepare_specs_data( $product );
			$specs_data = $prepared['specs_data'];

			if ( empty( $specs_data ) ) {
				/*
				 * نتیجه خالی هم کش می‌شود. نسخه قبلی قبل از set_transient
				 * برمی‌گشت، پس برای هر محصولِ بدون مشخصات، حلقه کامل پیکربندی
				 * و تمام جستجوهای ویژگی در *هر* بازدید دوباره اجرا می‌شد.
				 */
				set_transient( $transient_key, '', 12 * HOUR_IN_SECONDS );
				return '';
			}

			ob_start();
			$this->render_html( $specs_data );
			$raw_html = ob_get_clean();

			$output = (string) apply_filters( 'hodima_specs_table_html_output', $raw_html, $specs_data, $product );

			set_transient( $transient_key, $output, 12 * HOUR_IN_SECONDS );

			if ( '' !== $output ) {
				wp_enqueue_style( 'hodima-woo-specs-table' );
			}

			return $output;
		}

		private function render_html( array $specs_data ): void {

			$table_class = (string) apply_filters( 'hodima_specs_table_class', 'hodima-specs-table' );
			?>
			<div class="hodima-specs-wrap" role="region" tabindex="0" aria-label="جدول مشخصات محصول">
				<?php
				/*
				 * تقسیم ۳۰/۷۰ در خود HTML (colgroup) و table-layout: fixed روی
				 * جدول. قوانین CSS همین کار را هم دارند (با !important)، ولی نسخه
				 * کش‌شده فایل CSS (مثلا فایل ترکیبی لایت‌اسپید) عرض را اعمال
				 * نمی‌کرد و جدول ۵۰/۵۰ نمایش داده می‌شد. عرض در HTML به هیچ
				 * فایل CSS وابسته نیست.
				 */
				?>
				<table class="<?php echo esc_attr( $table_class ); ?>" style="table-layout:fixed;width:100%">
					<caption class="screen-reader-text">مشخصات محصول</caption>
					<colgroup>
						<col class="hodima-specs-col-label" style="width:30%">
						<col class="hodima-specs-col-value" style="width:70%">
					</colgroup>
					<tbody>
						<?php foreach ( $specs_data as $data ) : ?>
							<tr>
								<th scope="row"><?php echo esc_html( $data['label'] ); ?></th>
								<td data-label="<?php echo esc_attr( $data['label'] ); ?>"><?php echo wp_kses_post( $data['value'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					<!--hodima-specs-updated-->
					</tbody>
				</table>
			</div>
			<?php
		}

		/*--------------------------------------------------------------
		# Data preparation
		--------------------------------------------------------------*/

		/**
		 * @return array{specs_data: array, schema_properties: array}
		 */
		public function _prepare_specs_data( WC_Product $product ): array {

			$product_id = $product->get_id();

			if ( isset( $this->prepared_memo[ $product_id ] ) ) {
				return $this->prepared_memo[ $product_id ];
			}

			$config            = $this->get_table_config();
			$specs_data        = [];
			$schema_properties = [];

			foreach ( $config as $spec_key => $spec_details ) {

				$sources = (array) ( $spec_details['sources'] ?? [] );
				$default = (string) ( $spec_details['default'] ?? '' );
				$unit    = (string) ( $spec_details['unit'] ?? '' );
				$label   = (string) ( $spec_details['label'] ?? $spec_key );

				$value       = $this->get_product_value_by_priority( $product, $sources );
				$is_fallback = false;

				// مقدار کدگذاری‌شده → نام نمایشی (مثلا CN → چین)
				if ( '' !== $value && isset( $spec_details['map'] ) && is_array( $spec_details['map'] ) ) {
					$value = (string) ( $spec_details['map'][ strtoupper( $value ) ] ?? $value );
				}

				if ( '' === $value && '' !== $default ) {
					$value       = $default;
					$is_fallback = true;
				}

				if ( 'color' === $spec_key && '' !== $value ) {
					$value = str_replace( [ ', ', ',' ], ' | ', $value );
				}

				if ( '' === $value || '-' === $value || '—' === $value ) {
					continue;
				}

				/*
				 * واحد فقط به مقدار واقعی اضافه می‌شود، نه به مقدار پیش‌فرض.
				 * نسخه قبلی این را با $value !== $spec_details['default']
				 * تشخیص می‌داد که اگر پیکربندی فیلترشده کلید default نداشت،
				 * اخطار Undefined array key می‌داد.
				 */
				if ( '' !== $unit && ! $is_fallback && ! str_contains( $value, $unit ) ) {
					$value .= ' ' . $unit;
				}

				$specs_data[ $spec_key ] = [
					'label' => $label,
					'value' => $value,
				];

				$schema_properties[] = [
					'@type' => 'PropertyValue',
					'name'  => $label,
					'value' => wp_strip_all_tags( $value ),
				];
			}

			$prepared = [
				'specs_data'        => $specs_data,
				'schema_properties' => $schema_properties,
			];

			$this->prepared_memo[ $product_id ] = $prepared;

			return $prepared;
		}

		/**
		 * نام‌های ردیف‌هایی که این جدول می‌سازد (برچسب‌ها + نام ویژگی‌های منبع +
		 * ردیف «بروزرسانی»).
		 *
		 * جدول دستی hodima-table (Hodima Media) برای مقایسه و کاربرد محصول است
		 * و نباید هیچ‌کدام از این‌ها را بسازد؛ قبلا «سايز: بزرگ» یا «رنگ‌بندی: …»
		 * جدول دستی کنار «سایز: متنوع» و «رنگ: تک رنگ» این جدول در اسکیمای
		 * محصول می‌نشست (دو مقدار متناقض برای یک ویژگی). hodima-table این
		 * فهرست را می‌خواند؛ پیکربندی فیلترشده (hodima_specs_table_config) هم
		 * خودکار حساب می‌شود.
		 *
		 * @return list<string>
		 */
		public function property_names(): array {

			$names = [ 'بروزرسانی' ];

			foreach ( $this->get_table_config() as $spec_key => $spec ) {

				$names[] = (string) ( $spec['label'] ?? $spec_key );

				foreach ( (array) ( $spec['sources'] ?? [] ) as $source ) {

					if ( Hodima_Source_Type::Attribute !== ( $source['type'] ?? null ) ) {
						continue;
					}

					$attr = (string) ( $source['name'] ?? '' );

					// ویژگی سراسری (pa_…) با برچسب نمایشی‌اش، ویژگی سفارشی با خود نامش
					if ( str_starts_with( $attr, 'pa_' ) ) {
						if ( function_exists( 'wc_attribute_label' ) ) {
							$names[] = (string) wc_attribute_label( $attr );
						}
						continue;
					}

					$names[] = $attr;
				}
			}

			return array_values( array_unique( array_filter( $names, static fn( string $n ): bool => '' !== trim( $n ) ) ) );
		}

		/**
		 * واحد وزن فروشگاه.
		 *
		 * نسخه قبلی «گرم» را در پیکربندی هاردکد کرده بود. اگر واحد وزن
		 * ووکامرس روی کیلوگرم تنظیم باشد، جدول عدد کیلوگرم را با برچسب
		 * گرم نشان می‌داد.
		 */
		private function get_weight_unit(): string {

			$unit = function_exists( 'get_option' ) ? (string) get_option( 'woocommerce_weight_unit', 'g' ) : 'g';

			$map = [
				'g'   => 'گرم',
				'kg'  => 'کیلوگرم',
				'lbs' => 'پوند',
				'oz'  => 'اونس',
			];

			return $map[ $unit ] ?? $unit;
		}

		private function get_table_config(): array {

			$default_config = [
				'material' => [
					'label'   => 'جنس',
					'sources' => [
						[ 'type' => Hodima_Source_Type::Attribute, 'name' => 'pa_material' ],
						[ 'type' => Hodima_Source_Type::Attribute, 'name' => 'جنس' ],
						[ 'type' => Hodima_Source_Type::Attribute, 'name' => 'material' ],
					],
					'default' => '-',
				],
				'size' => [
					'label'   => 'سایز',
					'sources' => [
						[ 'type' => Hodima_Source_Type::Attribute, 'name' => 'pa_size' ],
						[ 'type' => Hodima_Source_Type::Attribute, 'name' => 'سایز' ],
						[ 'type' => Hodima_Source_Type::Attribute, 'name' => 'size' ],
						[ 'type' => Hodima_Source_Type::Attribute, 'name' => 'اندازه' ],
					],
					'default' => 'متنوع',
				],
				'weight' => [
					'label'   => 'وزن',
					'unit'    => $this->get_weight_unit(),
					'sources' => [
						[ 'type' => Hodima_Source_Type::Property, 'name' => 'get_weight' ],
						[ 'type' => Hodima_Source_Type::Meta, 'name' => '_weight' ],
						[ 'type' => Hodima_Source_Type::Attribute, 'name' => 'pa_weight' ],
					],
					'default' => '-',
				],
				'quantity' => [
					'label'   => 'تعداد',
					'unit'    => '',
					'sources' => [
						[ 'type' => Hodima_Source_Type::Attribute, 'name' => 'pa_pack-size' ],
						[ 'type' => Hodima_Source_Type::Attribute, 'name' => 'pa_quantity' ],
						[ 'type' => Hodima_Source_Type::Attribute, 'name' => 'تعداد در بسته' ],
						[ 'type' => Hodima_Source_Type::Attribute, 'name' => 'تعداد در جین' ],
						[ 'type' => Hodima_Source_Type::Attribute, 'name' => 'تعداد در کارتن' ],
						[ 'type' => Hodima_Source_Type::Attribute, 'name' => 'بسته بندی' ],
						[ 'type' => Hodima_Source_Type::Attribute, 'name' => 'تعداد' ],
						[ 'type' => Hodima_Source_Type::Meta, 'name' => 'custom_acf_quantity' ],
					],
					'default' => '-',
				],
				'color' => [
					'label'   => 'رنگ',
					'sources' => [
						[ 'type' => Hodima_Source_Type::Attribute, 'name' => 'pa_color' ],
						[ 'type' => Hodima_Source_Type::Attribute, 'name' => 'رنگ' ],
					],
					'default' => 'تک رنگ',
				],
				/*
				 * کشور سازنده («تولید») — آخرین ردیف. کد کشور متای قدیمی
				 * (IR / CN) و با map به نام فارسی تبدیل می‌شود.
				 * بدون مقدار پیش‌فرض: اگر انتخاب نشده، ردیف نمایش داده نمی‌شود.
				 */
				'origin' => [
					'label'   => 'تولید',
					/*
					 * اول ویژگی محصول (مثل بقیه ردیف‌های جدول: جنس، سایز، …)، سپس
					 * متای قدیمی فیلد «کشور سازنده». آن فیلد در Commerce 1.1.4 از
					 * پیشخوان حذف شد (تکراری با ویژگی «تولید»)؛ مقدار ذخیره‌شده
					 * محصولات قدیمی فقط به عنوان آخرین منبع خوانده می‌شود.
					 */
					'sources' => [
						// «تولید» اول (نام فعلی ردیف)؛ نام‌های قبلی برای محصولاتی که
						// ویژگی را با آن نام‌ها ثبت کرده‌اند باقی می‌مانند.
						[ 'type' => Hodima_Source_Type::Attribute, 'name' => 'تولید' ],
						[ 'type' => Hodima_Source_Type::Attribute, 'name' => 'pa_tolid' ],
						[ 'type' => Hodima_Source_Type::Attribute, 'name' => 'pa_country-of-origin' ],
						[ 'type' => Hodima_Source_Type::Attribute, 'name' => 'pa_origin' ],
						[ 'type' => Hodima_Source_Type::Attribute, 'name' => 'pa_country' ],
						[ 'type' => Hodima_Source_Type::Attribute, 'name' => 'pa_made-in' ],
						[ 'type' => Hodima_Source_Type::Attribute, 'name' => 'کشور سازنده' ],
						[ 'type' => Hodima_Source_Type::Attribute, 'name' => 'کشور' ],
						[ 'type' => Hodima_Source_Type::Attribute, 'name' => 'ساخت' ],
						[ 'type' => Hodima_Source_Type::Attribute, 'name' => 'ساخت کشور' ],
						[ 'type' => Hodima_Source_Type::Attribute, 'name' => 'مبدا' ],
						[ 'type' => Hodima_Source_Type::Meta, 'name' => '_hodima_country_of_origin' ],
					],
					'map'     => function_exists( 'hodima_country_of_origin_options' )
						? array_map( static fn( $o ) => $o['label'], hodima_country_of_origin_options() )
						: [ 'IR' => 'ایران', 'CN' => 'چین' ],
				],
			];

			return (array) apply_filters( 'hodima_specs_table_config', $default_config );
		}

		private function get_product_value_by_priority( WC_Product $product, array $sources ): string {

			foreach ( $sources as $source ) {

				$type = $source['type'] ?? Hodima_Source_Type::Attribute;
				$name = (string) ( $source['name'] ?? '' );

				if ( '' === $name ) {
					continue;
				}

				$value = match ( $type ) {
					Hodima_Source_Type::Attribute => $product->get_attribute( $name ),
					Hodima_Source_Type::Meta      => $product->get_meta( $name, true ) ?: get_post_meta( $product->get_id(), $name, true ),
					Hodima_Source_Type::Property  => method_exists( $product, $name ) ? $product->{$name}() : '',
					default                       => '',
				};

				if ( is_array( $value ) ) {
					$value = implode( '، ', array_filter( array_map( 'strval', $value ) ) );
				}

				$value = trim( (string) $value );

				if ( '' === $value || 'N/A' === $value ) {
					continue;
				}

				/*
				 * ووکامرس وزن را با سه رقم اعشار ذخیره می‌کند («12.000»).
				 * نسخه قبلی همان را خام نمایش می‌داد: «12.000 گرم».
				 */
				if ( is_numeric( $value ) && function_exists( 'wc_format_localized_decimal' ) ) {
					$value = (string) wc_format_localized_decimal( $value );
					$value = rtrim( rtrim( $value, '0' ), '.' );
					if ( '' === $value ) {
						continue;
					}
				}

				return $value;
			}

			return '';
		}

		/*--------------------------------------------------------------
		# Cache
		--------------------------------------------------------------*/

		private function transient_key( int $product_id ): string {
			// v3: ساختار جدید (colgroup + کشور سازنده). HTML کش‌شده قبلی بدون
			// این تغییرات تا ویرایش بعدی هر محصول نمایش داده می‌شد.
			return 'hodima_specs_table_html_v7_' . $product_id;
		}

		public function clear_transient_cache( int $product_id ): void {

			if ( wp_is_post_revision( $product_id ) ) {
				return;
			}

			unset( $this->prepared_memo[ $product_id ] );
			delete_transient( $this->transient_key( $product_id ) );

			if ( ! function_exists( 'wc_get_product' ) ) {
				return;
			}

			$product = wc_get_product( $product_id );

			if ( $product instanceof WC_Product && $product->is_type( 'variation' ) ) {
				$parent_id = $product->get_parent_id();
				unset( $this->prepared_memo[ $parent_id ] );
				delete_transient( $this->transient_key( $parent_id ) );
			}
		}

		/**
		 * ویرایش یا حذف یک ترم ویژگی محصول، کش تمام محصولات آن ترم را
		 * باطل می‌کند.
		 */
		public function maybe_flush_attribute_cache( $term_id, $tt_id = 0, $taxonomy = '' ): void {

			$taxonomy = (string) $taxonomy;

			if ( '' === $taxonomy || ! str_starts_with( $taxonomy, 'pa_' ) ) {
				return;
			}

			$product_ids = get_objects_in_term( [ (int) $term_id ], [ $taxonomy ] );

			if ( is_wp_error( $product_ids ) || empty( $product_ids ) ) {
				return;
			}

			// سقف محافظتی: یک ترم پرکاربرد می‌تواند هزاران محصول داشته باشد
			// و پاک کردن تک‌تک آن‌ها در یک درخواست، ذخیره ترم را کند می‌کند.
			foreach ( array_slice( $product_ids, 0, 500 ) as $product_id ) {
				$product_id = (int) $product_id;
				unset( $this->prepared_memo[ $product_id ] );
				delete_transient( $this->transient_key( $product_id ) );
			}
		}
	}
}

Hodima_Product_Specs_Table::get_instance();
