<?php
/**
 * Hodima Dynamic Table
 * Path: wp-content/plugins/hodima-media/inc/hodima-table/hodima-table.php
 * Version: 3.1.0
 *
 * جدول مشخصات قابل ویرایش برای نوشته، برگه، محصول و دسته‌ها:
 * کادر ویرایش در پیشخوان، شورت‌کد [hodima_table] در سایت، نود Table
 * در گراف اسکیما و سهم جدول‌های دوستونه در additionalProperty محصول.
 * جزئیات و تاریخچه باگ‌ها: HODIMA-AUDIT.md بخش ۴۶.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// =====================================================================
// SECTION 1: ENUM
// =====================================================================

if ( ! enum_exists( 'Hodima_Table_Context' ) ) {
	enum Hodima_Table_Context: string {
		case Post = 'post';
		case Term = 'term';
	}
}

// =====================================================================
// SECTION 2: MAIN CLASS
// =====================================================================

if ( ! class_exists( 'Hodima_Dynamic_Table' ) ) {

	class Hodima_Dynamic_Table {

		private static ?self $instance = null;

		public const VERSION  = '3.1.0';
		public const META_KEY = '_hodima_table_data';

		private const MAX_COLS = 20;
		private const MAX_ROWS = 100;

		private const SHORTCODE    = 'hodima_table';
		private const NONCE_ACTION = 'hodima_table_save_data';
		private const NONCE_FIELD  = 'hodima_table_meta_box_nonce';

		/**
		 * کل جدول در یک فیلد JSON فرستاده می‌شود (جاوااسکریپت می‌سازد).
		 *
		 * نسخه قبلی هر خانه را یک فیلد جدا می‌فرستاد (تا ۲۰۰۰ فیلد). PHP به‌طور
		 * پیش‌فرض فقط ۱۰۰۰ فیلد می‌پذیرد (max_input_vars) و بقیه را بی‌صدا دور
		 * می‌ریزد؛ در صفحه محصول ووکامرس که خودش صدها فیلد دارد، جدول بریده یا
		 * — اگر فیلدهای جدول اصلا نمی‌رسید — کامل پاک می‌شد.
		 */
		private const JSON_FIELD = 'hodima_table_json';

		/**
		 * ردیف‌هایی که مال «جدول مشخصات محصول» ووکامرس (hodima-woo-table در
		 * Hodima Commerce) است: جنس، سایز، وزن، تعداد، رنگ، تولید و بروزرسانی،
		 * با نام‌های هم‌معنی رایج. جدول دستی در محصول برای مقایسه، کاربرد و
		 * موارد متغیر است و این ردیف‌ها را نمی‌سازد (نه در سایت، نه در اسکیما).
		 * فهرست دقیق جدول ووکامرس (با پیکربندی فیلترشده) از
		 * Hodima_Product_Specs_Table::property_names() به این اضافه می‌شود.
		 */
		private const WOO_TABLE_NAMES = [
			// جنس
			'جنس', 'جنس محصول', 'متریال', 'material',
			// سایز
			'سایز', 'سایزها', 'سایزبندی', 'اندازه', 'اندازه‌ها', 'size',
			// وزن
			'وزن', 'وزن محصول', 'weight',
			// تعداد
			'تعداد', 'تعداد در بسته', 'تعداد در جین', 'تعداد در کارتن', 'بسته بندی', 'quantity', 'pack size',
			// رنگ
			'رنگ', 'رنگ‌ها', 'رنگبندی', 'رنگ محصول', 'color', 'colour',
			// تولید (کشور سازنده؛ countryOfOrigin اسکیمای محصول هم از همین می‌آید)
			'تولید', 'کشور سازنده', 'کشور', 'ساخت', 'ساخت کشور', 'مبدا', 'made in', 'country of origin', 'origin',
			// ردیف «بروزرسانی | تاریخ: …»
			'بروزرسانی', 'به‌روزرسانی', 'تاریخ بروزرسانی', 'آخرین بروزرسانی',
		];

		/** پاکسازی یک‌باره کش نسخه ۲ (گزینه‌های نسل و ترنزینت‌ها). */
		private const CLEANUP_OPTION = 'hodima_table_legacy_cache_cleaned';

		/** نودهای اسکیما برای چاپ در فوتر، کلید = نوع-شناسه جدول تا تکراری نشود. */
		private array $queued_schemas = [];

		public static function get_instance(): self {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		private function __construct() {

			add_action( 'add_meta_boxes', [ $this, 'add_table_meta_box' ] );
			add_action( 'save_post', [ $this, 'save_post_meta' ], 10, 2 );

			// هوک‌های ترم در init: سازنده در plugins_loaded اجرا می‌شود، پیش
			// از functions.php قالب؛ فیلتر hodima_table_taxonomies قالب قبلا
			// هیچ اثری نداشت.
			add_action( 'init', [ $this, 'register_term_hooks' ], 20 );

			add_shortcode( self::SHORTCODE, [ $this, 'render_table_shortcode' ] );

			// توضیح دسته: شورت‌کد فقط در بدنه صفحه همان دسته اجرا شود
			// (قبلا فقط اگر ماژول خوشه موضوعی روشن بود اجرا می‌شد، و آن هم
			// در <head> و توضیح متا).
			add_filter( 'term_description', [ $this, 'strip_from_term_description' ], 9, 4 );
			add_filter( 'term_description', [ $this, 'render_in_term_description' ], 11, 4 );

			add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
			add_action( 'wp_enqueue_scripts', [ $this, 'register_front_assets' ] );
			add_action( 'admin_init', [ $this, 'cleanup_legacy_cache' ] );

			add_action( 'wp_footer', [ $this, 'print_queued_schemas' ], 99 );

			// سهم این ماژول در additionalProperty نود Product.
			// اولویت ۲۰ تا بعد از hodima-woo-table اجرا شود و آن مرجع بماند.
			add_filter( 'hodima_product_additional_properties', [ $this, 'append_manual_properties' ], 20, 2 );
		}

		private function get_post_types(): array {
			return (array) apply_filters( 'hodima_table_post_types', [ 'post', 'page', 'product' ] );
		}

		private function get_taxonomies(): array {
			return (array) apply_filters( 'hodima_table_taxonomies', [ 'category', 'product_cat' ] );
		}

		public function register_term_hooks(): void {
			foreach ( $this->get_taxonomies() as $taxonomy ) {
				add_action( "{$taxonomy}_edit_form", [ $this, 'render_term_meta_box_edit' ] );
				add_action( "edited_{$taxonomy}", [ $this, 'save_term_meta' ] );
			}
		}

		/*--------------------------------------------------------------
		# Scripts & Styles
		--------------------------------------------------------------*/

		public function enqueue_admin_scripts( string $hook ): void {

			$screen = get_current_screen();
			if ( ! $screen ) {
				return;
			}

			$is_post_edit = ( 'post' === $screen->base
				&& in_array( $screen->post_type, $this->get_post_types(), true ) );

			$is_term_edit = ( 'term' === $screen->base
				&& in_array( $screen->taxonomy, $this->get_taxonomies(), true ) );

			if ( ! $is_post_edit && ! $is_term_edit ) {
				return;
			}

			// فقط برای پنجره «درج لینک» وردپرس (wpLink). jQuery UI Sortable
			// دیگر لازم نیست؛ جابه‌جایی ردیف با کشیدن بومی و دکمه‌ها است.
			wp_enqueue_editor();
			wp_enqueue_style( 'dashicons' );

			wp_enqueue_style(
				'hodima-table-admin-css',
				HODIMA_MEDIA_URL . '/inc/hodima-table/hodima-admin.css',
				[ 'dashicons' ],
				self::VERSION
			);

			wp_enqueue_script(
				'hodima-table-admin-js',
				HODIMA_MEDIA_URL . '/inc/hodima-table/hodima-admin.js',
				[],
				self::VERSION,
				[ 'in_footer' => true, 'strategy' => 'defer' ]
			);
		}

		/**
		 * استایل فرانت ثبت می‌شود و اگر از همین ابتدا معلوم باشد صفحه جدول
		 * دارد، در <head> لود می‌شود. قبلا همیشه هنگام اجرای شورت‌کد (وسط
		 * بدنه) صف می‌شد و وردپرس آن را در فوتر چاپ می‌کرد: جدول اول بی‌استایل
		 * دیده می‌شد و بعد می‌پرید (CLS).
		 */
		public function register_front_assets(): void {

			wp_register_style(
				'hodima-table-front-css',
				HODIMA_MEDIA_URL . '/inc/hodima-table/hodima-front.css',
				[],
				self::VERSION
			);

			if ( $this->page_has_table() ) {
				wp_enqueue_style( 'hodima-table-front-css' );
			}
		}

		private function page_has_table(): bool {

			$object = get_queried_object();

			if ( is_singular() && $object instanceof WP_Post ) {
				return has_shortcode( (string) $object->post_content, self::SHORTCODE )
					|| has_shortcode( (string) $object->post_excerpt, self::SHORTCODE );
			}

			if ( ( is_category() || is_tag() || is_tax() ) && $object instanceof WP_Term ) {
				return has_shortcode( (string) $object->description, self::SHORTCODE );
			}

			return false;
		}

		/**
		 * نسخه ۲ برای هر نوشته یک گزینه «نسل کش» (hodima_table_gen_*) و برای
		 * هر نمایش یک ترنزینت می‌ساخت که با حذف نوشته هم پاک نمی‌شد. کش حذف
		 * شد (پایین)؛ این باقی‌مانده‌ها یک بار پاک می‌شوند. الگوی ترنزینت‌ها
		 * عمدا با post_/term_ است: hodima_table_exists() قالب هم پیشوند
		 * hodima_tbl_ دارد ولی پس از آن md5 می‌آید.
		 */
		public function cleanup_legacy_cache(): void {

			if ( get_option( self::CLEANUP_OPTION ) ) {
				return;
			}

			global $wpdb;

			$prefixes = [
				'hodima_table_gen_',
				'_transient_hodima_tbl_post_',
				'_transient_hodima_tbl_term_',
				'_transient_timeout_hodima_tbl_post_',
				'_transient_timeout_hodima_tbl_term_',
			];

			foreach ( $prefixes as $prefix ) {
				$wpdb->query( $wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
					$wpdb->esc_like( $prefix ) . '%'
				) );
			}

			update_option( self::CLEANUP_OPTION, 1, true );
		}

		/*--------------------------------------------------------------
		# Meta Box Rendering
		--------------------------------------------------------------*/

		public function add_table_meta_box(): void {
			foreach ( $this->get_post_types() as $post_type ) {
				add_meta_box(
					'hodima_table_meta_box',
					// در محصول «جدول مشخصات» نام جدول ووکامرس است؛ این کادر جدول دیگری است
					'product' === $post_type ? 'جدول تکمیلی محصول (مقایسه، کاربرد و …)' : 'جدول مشخصات',
					[ $this, 'render_post_meta_box' ],
					$post_type,
					'normal',
					'high'
				);
			}
		}

		public function render_post_meta_box( WP_Post $post ): void {
			wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
			$this->render_table_builder_ui(
				self::get_table( $post->ID, Hodima_Table_Context::Post ),
				'product' === $post->post_type ? self::woo_table_names() : null
			);
		}

		public function render_term_meta_box_edit( $term ): void {

			if ( ! ( $term instanceof WP_Term ) ) {
				return;
			}

			wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD );
			?>
			<div class="postbox hodima-term-meta-box">
				<div class="postbox-header">
					<h2 class="hndle">جدول مشخصات</h2>
				</div>
				<div class="inside">
					<?php $this->render_table_builder_ui( self::get_table( $term->term_id, Hodima_Table_Context::Term ) ); ?>
				</div>
			</div>
			<?php
		}

		/*--------------------------------------------------------------
		# Saving
		--------------------------------------------------------------*/

		public function save_post_meta( int $post_id, $post = null ): void {

			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

			// بازنگری‌ها شناسه مستقل دارند و save_post برایشان هم اجرا می‌شود.
			if ( wp_is_post_revision( $post_id ) ) return;

			/*
			 * فقط همان نوشته‌ای که فرمش فرستاده شده. اگر افزونه‌ای هنگام ذخیره
			 * نوشته دیگری بسازد یا به‌روز کند (wp_insert_post در همان درخواست)،
			 * save_post برای آن هم با همین $_POST اجرا می‌شد و جدول روی آن
			 * کپی می‌شد. post_ID در فرم کلاسیک و فرم متاباکس‌های گوتنبرگ هست.
			 */
			if ( isset( $_POST['post_ID'] ) && (int) $_POST['post_ID'] !== $post_id ) return;

			$post = ( $post instanceof WP_Post ) ? $post : get_post( $post_id );
			if ( ! ( $post instanceof WP_Post ) ) return;
			if ( ! in_array( $post->post_type, $this->get_post_types(), true ) ) return;

			if ( ! $this->verify_save_request() ) return;
			if ( ! current_user_can( 'edit_post', $post_id ) ) return;

			$this->save_data( $post_id, Hodima_Table_Context::Post, $_POST );
		}

		public function save_term_meta( int $term_id ): void {

			if ( ! $this->verify_save_request() ) return;

			// همان منطق post_ID: فقط ترمی که فرمش ویرایش شده
			if ( isset( $_POST['tag_ID'] ) && (int) $_POST['tag_ID'] !== $term_id ) return;

			// قابلیت از خود تکسونومی (برای product_cat یعنی manage_product_terms)
			$term = get_term( $term_id );
			if ( ! ( $term instanceof WP_Term ) ) return;

			if ( ! in_array( $term->taxonomy, $this->get_taxonomies(), true ) ) return;

			$taxonomy = get_taxonomy( $term->taxonomy );
			if ( ! $taxonomy || ! current_user_can( $taxonomy->cap->edit_terms ) ) return;

			$this->save_data( $term_id, Hodima_Table_Context::Term, $_POST );
		}

		private function verify_save_request(): bool {

			$nonce = isset( $_POST[ self::NONCE_FIELD ] )
				? sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) )
				: '';

			return (bool) wp_verify_nonce( $nonce, self::NONCE_ACTION );
		}

		private function save_data( int $object_id, Hodima_Table_Context $context, array $post_data ): void {

			$table_data = $this->read_submitted_table( $post_data );

			/*
			 * فیلدهای جدول نرسیده‌اند (فرم بریده‌شده، JSON خراب): به داده
			 * فعلی دست نزن. نسخه قبلی در این حالت جدول را خالی فرض می‌کرد و
			 * جدول ذخیره‌شده را *پاک* می‌کرد.
			 */
			if ( null === $table_data ) {
				return;
			}

			if ( self::get_table( $object_id, $context ) === $table_data ) {
				return;
			}

			$is_term = ( Hodima_Table_Context::Term === $context );

			if ( self::is_empty_table( $table_data ) ) {
				$is_term
					? delete_term_meta( $object_id, self::META_KEY )
					: delete_post_meta( $object_id, self::META_KEY );
				return;
			}

			// update_*_meta داده را wp_unslash می‌کند؛ بدون wp_slash هر «\»
			// داخل خانه‌ها (مثل C:\ یا 5\6) هنگام ذخیره حذف می‌شد.
			$is_term
				? update_term_meta( $object_id, self::META_KEY, wp_slash( $table_data ) )
				: update_post_meta( $object_id, self::META_KEY, wp_slash( $table_data ) );
		}

		/**
		 * جدول فرستاده‌شده از فرم، تمیز و یکدست؛ null یعنی «جدولی فرستاده نشده».
		 *
		 * مسیر اصلی فیلد JSON است. اگر جاوااسکریپت اجرا نشده باشد، فیلدهای
		 * قدیمی hodima_table_headers[] و hodima_table_rows[][] خوانده می‌شوند.
		 */
		private function read_submitted_table( array $post_data ): ?array {

			if ( isset( $post_data[ self::JSON_FIELD ] ) && is_string( $post_data[ self::JSON_FIELD ] ) ) {

				$decoded = json_decode( wp_unslash( $post_data[ self::JSON_FIELD ] ), true );

				if ( ! is_array( $decoded ) || ! array_key_exists( 'headers', $decoded ) || ! array_key_exists( 'rows', $decoded ) ) {
					return null;
				}

				$headers = is_array( $decoded['headers'] ) ? $decoded['headers'] : [];
				$rows    = is_array( $decoded['rows'] ) ? $decoded['rows'] : [];

			} elseif ( isset( $post_data['hodima_table_headers'] ) || isset( $post_data['hodima_table_rows'] ) ) {

				$headers = wp_unslash( (array) ( $post_data['hodima_table_headers'] ?? [] ) );
				$rows    = wp_unslash( array_values( (array) ( $post_data['hodima_table_rows'] ?? [] ) ) );

			} else {
				return null;
			}

			$clean_headers = [];
			foreach ( array_slice( array_values( $headers ), 0, self::MAX_COLS ) as $header ) {
				$clean_headers[] = is_scalar( $header ) ? wp_kses_post( (string) $header ) : '';
			}

			$clean_rows = [];
			foreach ( array_slice( array_values( $rows ), 0, self::MAX_ROWS ) as $row ) {
				$clean_row = [];
				foreach ( array_slice( array_values( (array) $row ), 0, self::MAX_COLS ) as $cell ) {
					$clean_row[] = is_scalar( $cell ) ? wp_kses_post( (string) $cell ) : '';
				}
				$clean_rows[] = $clean_row;
			}

			return self::normalize_table( [ 'headers' => $clean_headers, 'rows' => $clean_rows ] );
		}

		/*--------------------------------------------------------------
		# Table data
		--------------------------------------------------------------*/

		/** جدول ذخیره‌شده یک شیء، یکدست‌شده. */
		public static function get_table( int $object_id, Hodima_Table_Context $context ): array {

			$raw = ( Hodima_Table_Context::Term === $context )
				? get_term_meta( $object_id, self::META_KEY, true )
				: get_post_meta( $object_id, self::META_KEY, true );

			return self::normalize_table( $raw );
		}

		/**
		 * یکدست کردن جدول — هم هنگام ذخیره و هم هنگام خواندن داده قدیمی.
		 *
		 * - ستونی که عنوان و همه خانه‌هایش خالی است حذف می‌شود. کادر ویرایش با
		 *   ۴ ستون باز می‌شد؛ کاربری که فقط دو ستون «ویژگی / مقدار» را پر
		 *   می‌کرد، جدولی ۴ستونه با دو ستون خالی ذخیره می‌کرد: در سایت دو
		 *   ستون خالی دیده می‌شد و هیچ مشخصه‌ای به اسکیما نمی‌رسید.
		 * - ردیف کاملا خالی حذف و همه ردیف‌ها هم‌طول می‌شوند.
		 * - اگر همه عنوان‌ها خالی باشند، headers آرایه خالی است (جدول بدون سرستون).
		 *
		 * @return array{headers: list<string>, rows: list<list<string>>}
		 */
		public static function normalize_table( mixed $data ): array {

			if ( ! is_array( $data ) ) {
				return [ 'headers' => [], 'rows' => [] ];
			}

			$to_string = static fn( mixed $value ): string => is_scalar( $value ) ? (string) $value : '';

			$headers = array_map( $to_string, array_values( (array) ( $data['headers'] ?? [] ) ) );
			$rows    = [];
			foreach ( (array) ( $data['rows'] ?? [] ) as $row ) {
				$rows[] = array_map( $to_string, array_values( (array) $row ) );
			}

			$width = count( $headers );
			foreach ( $rows as $row ) {
				$width = max( $width, count( $row ) );
			}

			$headers = array_pad( $headers, $width, '' );
			foreach ( $rows as $i => $row ) {
				$rows[ $i ] = array_pad( $row, $width, '' );
			}

			$keep = [];
			for ( $c = 0; $c < $width; $c++ ) {
				if ( ! self::is_blank( $headers[ $c ] ) ) {
					$keep[] = $c;
					continue;
				}
				foreach ( $rows as $row ) {
					if ( ! self::is_blank( $row[ $c ] ) ) {
						$keep[] = $c;
						continue 2;
					}
				}
			}

			$pick    = static fn( array $cells ): array => array_map( static fn( int $c ): string => $cells[ $c ], $keep );
			$headers = $pick( $headers );
			$rows    = array_map( $pick, $rows );

			$rows = array_values( array_filter(
				$rows,
				static fn( array $row ): bool => [] !== array_filter( $row, static fn( string $cell ): bool => ! self::is_blank( $cell ) )
			) );

			if ( [] === array_filter( $headers, static fn( string $header ): bool => ! self::is_blank( $header ) ) ) {
				$headers = [];
			}

			return [ 'headers' => $headers, 'rows' => $rows ];
		}

		private static function is_empty_table( array $table ): bool {
			return [] === $table['headers'] && [] === $table['rows'];
		}

		private static function column_count( array $table ): int {
			return [] !== $table['headers'] ? count( $table['headers'] ) : count( $table['rows'][0] ?? [] );
		}

		/** متن ساده یک خانه: بدون تگ، موجودیت‌های HTML باز، فاصله‌های یکدست. */
		private static function plain_text( string $html ): string {
			$text = html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			$text = str_replace( "\u{00a0}", ' ', $text );
			return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
		}

		private static function is_blank( string $html ): bool {
			return '' === self::plain_text( $html );
		}

		/*--------------------------------------------------------------
		# Builder UI
		--------------------------------------------------------------*/

		/**
		 * کادر ویرایش جدول.
		 *
		 * فیلدها name دارند تا بدون جاوااسکریپت هم ذخیره شود؛ اسکریپت آن‌ها
		 * را برمی‌دارد و کل جدول را در یک فیلد JSON می‌فرستد. دکمه‌ها
		 * <button> واقعی‌اند (قبلا span بودند و با کیبورد در دسترس نبودند).
		 */
		private function render_table_builder_ui( array $table_data, ?array $woo_names = null ): void {

			$headers = [] !== $table_data['headers'] ? $table_data['headers'] : [];
			$rows    = [] !== $table_data['rows'] ? $table_data['rows'] : [];
			$cols    = max( self::column_count( $table_data ), 2 );

			// جدول خالی با دو ستون «ویژگی / مقدار» شروع می‌شود (قبلا ۴ ستون)
			$headers = array_pad( $headers, $cols, '' );
			if ( [] === $rows ) {
				$rows = [ array_fill( 0, $cols, '' ) ];
			}
			?>
			<div class="hodima-wrap" data-hodima-table data-max-cols="<?php echo (int) self::MAX_COLS; ?>" data-max-rows="<?php echo (int) self::MAX_ROWS; ?>"<?php if ( null !== $woo_names ) : ?> data-woo-names="<?php echo esc_attr( (string) wp_json_encode( array_keys( $woo_names ), JSON_UNESCAPED_UNICODE ) ); ?>"<?php endif; ?>>

				<div class="hodima-table-info">
					<p><strong>نمایش در سایت:</strong> <code>[hodima_table]</code> را در متن بگذارید.</p>
					<p class="hodima-hint">
						سقف <?php echo (int) self::MAX_COLS; ?> ستون و <?php echo (int) self::MAX_ROWS; ?> ردیف.
						جدول دوستونه (نام ویژگی / مقدار) در اسکیمای گوگل هم ثبت می‌شود؛ ستون‌های خالی خودکار حذف می‌شوند.
					</p>
					<?php if ( null !== $woo_names ) : ?>
						<p class="hodima-hint hodima-hint--woo">
							<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
							این جدول برای مقایسه، کاربرد و موارد متغیر هر محصول است. جنس، سایز، وزن، تعداد، رنگ، تولید و بروزرسانی را
							«جدول مشخصات محصول» (از ویژگی‌های ووکامرس) می‌سازد؛ اگر این ردیف‌ها را این‌جا بنویسید، در سایت و اسکیما نادیده گرفته می‌شوند.
						</p>
					<?php endif; ?>
					<p class="hodima-hint hodima-woo-warning" data-role="woo-warning" role="status" hidden></p>
				</div>

				<div class="hodima-toolbar" role="toolbar" aria-label="ابزار جدول">
					<div class="hodima-toolbar-group">
						<button type="button" class="button button-primary" data-action="add-row"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> افزودن ردیف</button>
						<button type="button" class="button" data-action="add-col"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> افزودن ستون</button>
						<button type="button" class="button" data-action="link" title="یا Ctrl+K داخل خانه"><span class="dashicons dashicons-admin-links" aria-hidden="true"></span> درج لینک</button>
					</div>
					<div class="hodima-toolbar-group">
						<button type="button" class="button" data-action="export"><span class="dashicons dashicons-download" aria-hidden="true"></span> خروجی CSV</button>
						<button type="button" class="button" data-action="import"><span class="dashicons dashicons-upload" aria-hidden="true"></span> ورود CSV</button>
						<input type="file" accept=".csv,text/csv" data-role="csv-file" hidden>
					</div>
				</div>

				<div class="hodima-table-scroll">
					<table class="hodima-admin-table">
						<thead>
							<tr>
								<th scope="col" class="hodima-col-ops"><span class="screen-reader-text">جابه‌جایی و حذف ردیف</span></th>
								<?php foreach ( $headers as $c => $header ) : ?>
									<th scope="col">
										<div class="hodima-col-head">
											<input type="text" name="hodima_table_headers[]" value="<?php echo esc_attr( $header ); ?>" placeholder="عنوان ستون" aria-label="<?php echo esc_attr( sprintf( 'عنوان ستون %d', $c + 1 ) ); ?>">
											<button type="button" class="hodima-icon-btn" data-action="remove-col" aria-label="<?php echo esc_attr( sprintf( 'حذف ستون %d', $c + 1 ) ); ?>"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button>
										</div>
									</th>
								<?php endforeach; ?>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $rows as $r => $row ) : ?>
								<tr>
									<td class="hodima-col-ops">
										<div class="hodima-row-actions">
											<span class="hodima-drag-handle dashicons dashicons-menu" title="برای جابه‌جایی بکشید" aria-hidden="true"></span>
											<button type="button" class="hodima-icon-btn" data-action="row-up" aria-label="انتقال ردیف به بالا"><span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span></button>
											<button type="button" class="hodima-icon-btn" data-action="row-down" aria-label="انتقال ردیف به پایین"><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></button>
											<button type="button" class="hodima-icon-btn hodima-icon-btn--danger" data-action="remove-row" aria-label="حذف ردیف"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button>
										</div>
									</td>
									<?php for ( $c = 0; $c < $cols; $c++ ) : ?>
										<td>
											<textarea rows="2" name="hodima_table_rows[<?php echo (int) $r; ?>][]" placeholder="مقدار" aria-label="<?php echo esc_attr( sprintf( 'ردیف %1$d، ستون %2$d', $r + 1, $c + 1 ) ); ?>"><?php echo esc_textarea( (string) ( $row[ $c ] ?? '' ) ); ?></textarea>
										</td>
									<?php endfor; ?>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
			<?php
		}

		/*--------------------------------------------------------------
		# Schema
		--------------------------------------------------------------*/

		/**
		 * جدولی که در سایت نمایش داده و به اسکیما داده می‌شود.
		 *
		 * در محصول، ردیف‌های جدول دوستونه‌ای که نامشان مال جدول مشخصات ووکامرس
		 * است حذف می‌شوند. قبلا هر دو جدول همان ویژگی را می‌ساختند: در سایت دو
		 * ردیف «سایز» با دو مقدار، و در additionalProperty محصول «سایز: متنوع»
		 * کنار «سايز: بزرگ» (ی عربی از مقایسه نام‌ها رد می‌شد) یا «رنگ: تک رنگ»
		 * کنار «رنگ‌بندی: صورتی و آبی». جدول چندستونه (مقایسه چند مدل) مشخصه‌ای
		 * به اسکیما نمی‌دهد و دست نمی‌خورد. داده ذخیره‌شده هم دست نمی‌خورد
		 * (کادر ویرایش همه ردیف‌ها را با هشدار نشان می‌دهد).
		 */
		public static function get_public_table( int $object_id, Hodima_Table_Context $context ): array {

			$table = self::get_table( $object_id, $context );

			if ( Hodima_Table_Context::Post !== $context
				|| 2 !== self::column_count( $table )
				|| 'product' !== get_post_type( $object_id ) ) {
				return $table;
			}

			$woo = self::woo_table_names();

			$table['rows'] = array_values( array_filter(
				$table['rows'],
				static fn( array $row ): bool => ! isset( $woo[ self::normalize_property_name( $row[0] ) ] )
			) );

			// همه ردیف‌ها مال ووکامرس بود: جدولی با فقط سرستون نمایش داده نشود
			if ( [] === $table['rows'] ) {
				return [ 'headers' => [], 'rows' => [] ];
			}

			return self::normalize_table( $table );
		}

		/**
		 * نام‌های جدول مشخصات ووکامرس، کلید = نام یکدست‌شده.
		 *
		 * @return array<string, true>
		 */
		public static function woo_table_names(): array {

			static $memo = null;

			if ( null !== $memo ) {
				return $memo;
			}

			$names = self::WOO_TABLE_NAMES;

			if ( class_exists( 'Hodima_Product_Specs_Table' ) && method_exists( 'Hodima_Product_Specs_Table', 'property_names' ) ) {
				$names = [ ...$names, ...Hodima_Product_Specs_Table::get_instance()->property_names() ];
			}

			$names = (array) apply_filters( 'hodima_table_woo_property_names', $names );

			$memo = [];
			foreach ( $names as $name ) {
				$key = self::normalize_property_name( (string) $name );
				if ( '' !== $key ) {
					$memo[ $key ] = true;
				}
			}

			return $memo;
		}

		/**
		 * جدول دوستونه را به آرایه PropertyValue تبدیل می‌کند (ستون اول نام
		 * ویژگی، ستون دوم مقدار).
		 *
		 * جدول سه ستون به بالا (سایزبندی، مقایسه) هیچ مشخصه‌ای نمی‌دهد. نسخه
		 * قبلی آن را با «عنوان ستون = نام» تبدیل می‌کرد ({"سایز":"S"}، {"سایز":"M"}…)
		 * و چون تشخیص «دوستونه» در دو تابع متفاوت بود (یکی ستون‌های خالی را
		 * می‌شمرد و دیگری نه)، جدول «ویژگی / مقدار» با دو ستون خالی در اسکیمای
		 * محصول {"name":"ویژگی","value":"جنس"} می‌ساخت. حالا یک تشخیص واحد
		 * روی جدول یکدست‌شده.
		 *
		 * @return array<int, array<string, string>>
		 */
		public static function get_property_values( int $object_id, string $context = 'post' ): array {

			$context = Hodima_Table_Context::tryFrom( $context ) ?? Hodima_Table_Context::Post;
			$table   = self::get_public_table( $object_id, $context );

			$properties = [];

			if ( 2 === self::column_count( $table ) ) {

				$seen = [];

				foreach ( $table['rows'] as $row ) {

					$name  = self::plain_text( $row[0] );
					$value = self::plain_text( $row[1] );
					$key   = self::normalize_property_name( $name );

					// نام تکراری داخل یک جدول = مقدار متناقض؛ اولی می‌ماند
					if ( '' === $name || '' === $value || isset( $seen[ $key ] ) ) {
						continue;
					}

					$seen[ $key ] = true;
					$properties[] = [
						'@type' => 'PropertyValue',
						'name'  => $name,
						'value' => $value,
					];
				}
			}

			return (array) apply_filters( 'hodima_table_property_values', $properties, $object_id, $context->value );
		}

		/**
		 * افزودن مشخصات جدول دستی به additionalProperty محصول.
		 *
		 * فقط وقتی جدول واقعا در صفحه محصول نمایش داده می‌شود: داده اسکیما
		 * باید در صفحه دیده شود (قانون گوگل). قبلا جدولی که پر شده بود ولی
		 * شورت‌کدش در متن نبود هم به اسکیما می‌رفت. اسکیمای محصول در wp_head
		 * ساخته می‌شود (پیش از بدنه)، پس نمایش از روی متن محصول تشخیص داده
		 * می‌شود؛ قالب/افزونه‌ای که جدول را جای دیگری نشان می‌دهد با فیلتر
		 * hodima_table_is_displayed اعلام می‌کند.
		 *
		 * @param array $properties مقادیری که ماژول‌های قبلی ساخته‌اند.
		 * @param int   $product_id شناسه محصول.
		 */
		public function append_manual_properties( array $properties, int $product_id ): array {

			if ( ! $product_id || ! $this->is_displayed_on_post( $product_id ) ) {
				return $properties;
			}

			// نام‌هایی که ماژول‌های قبل‌تر (hodima-woo-table) پر کرده‌اند
			// مرجع‌اند؛ آن‌ها از ویژگی‌های ساختاریافته ووکامرس می‌آیند.
			$taken = [];
			foreach ( $properties as $property ) {
				if ( isset( $property['name'] ) ) {
					$taken[ self::normalize_property_name( (string) $property['name'] ) ] = true;
				}
			}

			foreach ( self::get_property_values( $product_id, 'post' ) as $candidate ) {

				$key = self::normalize_property_name( (string) ( $candidate['name'] ?? '' ) );

				if ( '' === $key || isset( $taken[ $key ] ) ) {
					continue;
				}

				$taken[ $key ] = true;
				$properties[]  = $candidate;
			}

			return $properties;
		}

		/** شورت‌کد جدول همین نوشته در متن یا خلاصه آن هست؟ */
		private function is_displayed_on_post( int $post_id ): bool {

			$post  = get_post( $post_id );
			$shown = false;

			if ( $post instanceof WP_Post ) {

				$pattern = '/' . get_shortcode_regex( [ self::SHORTCODE ] ) . '/';

				foreach ( [ (string) $post->post_content, (string) $post->post_excerpt ] as $text ) {

					if ( ! has_shortcode( $text, self::SHORTCODE ) || ! preg_match_all( $pattern, $text, $matches, PREG_SET_ORDER ) ) {
						continue;
					}

					foreach ( $matches as $match ) {

						// [[hodima_table]] = شورت‌کد escape‌شده، اجرا نمی‌شود
						if ( '[' === $match[1] && ']' === $match[6] ) {
							continue;
						}

						$atts = shortcode_parse_atts( $match[3] );
						$atts = is_array( $atts ) ? $atts : [];
						$id   = absint( $atts['id'] ?? 0 );
						$type = strtolower( trim( (string) ( $atts['type'] ?? '' ) ) );

						if ( ( 0 === $id || $post_id === $id ) && ! in_array( $type, [ 'term', ...$this->get_taxonomies() ], true ) ) {
							$shown = true;
							break 2;
						}
					}
				}
			}

			return (bool) apply_filters( 'hodima_table_is_displayed', $shown, $post_id );
		}

		/**
		 * کلید مقایسه نام‌ها: بدون تگ، فاصله، نیم‌فاصله، «:» پایانی و حساسیت به
		 * حروف؛ «ي/ك» عربی = «ی/ک» فارسی. قبلا «سايز» (ی عربی، رایج در متن
		 * کپی‌شده) یا «رنگ بندی» با فاصله نام دیگری حساب می‌شد و کنار «سایز» و
		 * «رنگ‌بندی» در اسکیمای محصول می‌نشست.
		 */
		private static function normalize_property_name( string $name ): string {
			$name = self::plain_text( $name );
			$name = str_replace( [ "\u{200c}", "\u{200d}", "\u{200f}", "\u{200e}" ], '', $name );
			$name = strtr( $name, [ 'ي' => 'ی', 'ى' => 'ی', 'ك' => 'ک', 'أ' => 'ا', 'إ' => 'ا', 'ۀ' => 'ه' ] );
			$name = (string) preg_replace( '/[\s:：]+/u', '', $name );
			return mb_strtolower( $name );
		}

		/**
		 * نود اسکیمای جدول (نوع Table، عضو گراف صفحه).
		 *
		 * - @id و cssSelector (کلاس) مخصوص همین جدول: قبلا همه جدول‌ها «#specs-table»
		 *   و «.hodima-dynamic-table» بودند؛ دو جدول در یک صفحه یک @id
		 *   می‌گرفتند و اسکیمای اولی بی‌صدا حذف می‌شد.
		 * - مقادیر (mainEntity) فقط برای جدول دوستونه؛ جدول چندستونه فقط نام و
		 *   جای جدول را اعلام می‌کند.
		 * - about حذف شد: به خود صفحه اشاره می‌کرد («این جدول درباره این صفحه
		 *   است») که معنای درستی ندارد؛ isPartOf کافی است.
		 */
		private function build_schema_node( int $object_id, Hodima_Table_Context $context, string $title, string $css_class ): array {

			$page_url = function_exists( 'hodima_get_canonical_url' ) ? hodima_get_canonical_url() : '';

			if ( '' === $page_url ) {
				$link     = ( Hodima_Table_Context::Term === $context ) ? get_term_link( $object_id ) : get_permalink( $object_id );
				$page_url = ( is_wp_error( $link ) || ! $link ) ? '' : (string) $link;
			}

			if ( '' === $page_url ) {
				return [];
			}

			// بدون trailingslashit: پایه شناسه باید *دقیقا* همان آدرسی باشد که
			// homepage-schema.php برای «#webpage» به کار می‌برد (موتور canonical).
			$name = '' !== $title ? $title : 'جدول مشخصات';

			$node = [
				'@type'       => 'Table',
				'@id'         => $page_url . '#' . $css_class,
				'name'        => $name,
				'isPartOf'    => [ '@id' => $page_url . '#webpage' ],
				'cssSelector' => '.' . $css_class,
			];

			$properties = self::get_property_values( $object_id, $context->value );
			$product_node = $page_url . '#product';

			/*
			 * جدول همین محصول در صفحه خودش: مقدارها در additionalProperty نود
			 * Product‌اند (append_manual_properties)، پس این‌جا تکرار نمی‌شوند؛
			 * نود جدول فقط می‌گوید «درباره این محصول است». قبلا همان مقدارها در
			 * دو موجودیت جدا (Table و Product) می‌آمد.
			 */
			$is_own_product = Hodima_Table_Context::Post === $context
				&& is_singular( 'product' )
				&& (int) get_queried_object_id() === $object_id
				&& function_exists( 'hodima_schema_has' )
				&& hodima_schema_has( $product_node );

			if ( $is_own_product ) {
				$node['about'] = [ '@id' => $product_node ];
			} elseif ( [] !== $properties ) {
				$node['mainEntity'] = [
					'@type'          => 'PropertyValue',
					'name'           => $name,
					'valueReference' => $properties,
				];
			}

			return (array) apply_filters( 'hodima_table_schema_node', $node, $object_id, $context->value );
		}

		public function print_queued_schemas(): void {

			if ( empty( $this->queued_schemas ) || ! function_exists( 'hodima_schema_add' ) ) {
				return;
			}

			// گراف واحد صفحه (hodima-core) — پیش از چاپ آن در wp_footer
			hodima_schema_add( [ '@graph' => array_values( $this->queued_schemas ) ], 'hodima-media: hodima-table' );
			$this->queued_schemas = [];
		}

		/*--------------------------------------------------------------
		# Term description
		--------------------------------------------------------------*/

		/**
		 * توضیح دسته در بدنه صفحه همان دسته است؟ (نه <head>، فید، REST یا
		 * توضیح دسته دیگری در ابزارک)
		 */
		private function is_term_description_body( mixed $term_id, mixed $context ): bool {

			$queried = get_queried_object();

			return 'display' === $context
				&& ! is_admin()
				&& ! doing_action( 'wp_head' )
				&& ! doing_action( 'wp_footer' )
				&& ! is_feed()
				&& ! wp_is_json_request()
				&& $queried instanceof WP_Term
				&& (int) $queried->term_id === (int) $term_id;
		}

		/**
		 * اولویت ۹ (پیش از wpautop و do_shortcode خوشه موضوعی): بیرون از بدنه
		 * صفحه دسته، شورت‌کد جدول حذف می‌شود تا متن جدول وارد توضیح متا یا
		 * اسکیمای <head> نشود.
		 */
		public function strip_from_term_description( mixed $value, mixed $term_id = 0, mixed $taxonomy = '', mixed $context = 'display' ): mixed {

			if ( ! is_string( $value ) || ! str_contains( $value, '[' . self::SHORTCODE ) ) {
				return $value;
			}

			if ( $this->is_term_description_body( $term_id, $context ) ) {
				return $value;
			}

			return (string) preg_replace_callback(
				'/' . get_shortcode_regex( [ self::SHORTCODE ] ) . '/',
				static fn( array $m ): string => ( '[' === $m[1] && ']' === $m[6] ) ? substr( $m[0], 1, -1 ) : '',
				$value
			);
		}

		/** اولویت ۱۱ (بعد از wpautop، مثل the_content): اجرای شورت‌کد جدول در بدنه. */
		public function render_in_term_description( mixed $value, mixed $term_id = 0, mixed $taxonomy = '', mixed $context = 'display' ): mixed {

			if ( ! is_string( $value ) || ! str_contains( $value, '[' . self::SHORTCODE ) ) {
				return $value;
			}

			if ( ! $this->is_term_description_body( $term_id, $context ) ) {
				return $value;
			}

			return (string) preg_replace_callback(
				'/' . get_shortcode_regex( [ self::SHORTCODE ] ) . '/',
				'do_shortcode_tag',
				$value
			);
		}

		/*--------------------------------------------------------------
		# Frontend Shortcode
		--------------------------------------------------------------*/

		/**
		 * [hodima_table] — جدول همین نوشته یا دسته.
		 * [hodima_table id="123"] یا type="post" — جدول نوشته/برگه/محصول ۱۲۳.
		 * [hodima_table id="45" type="term"] یا type="product_cat" — جدول دسته ۴۵.
		 * title یا caption — عنوان جدول.
		 *
		 * کش ترنزینت نسخه قبلی حذف شد: متای جدول همراه نوشته از قبل در حافظه
		 * است، ولی کش هر بار ۲ تا ۳ کوئری اضافه (گزینه نسل + ترنزینت) می‌زد و
		 * اگر جدول از راهی جز این کادر عوض می‌شد (درون‌ریزی، REST، بازگردانی
		 * نسخه) تا ۱۲ ساعت جدول قدیمی با اسکیمای جدید نمایش داده می‌شد.
		 */
		public function render_table_shortcode( array|string $atts = [] ): string {

			if ( is_admin() && ! wp_doing_ajax() ) {
				return '';
			}

			$atts = shortcode_atts( [
				'id'      => '',
				'type'    => '',
				'title'   => '',
				'caption' => '',
			], (array) $atts, self::SHORTCODE );

			$target = $this->resolve_target( absint( $atts['id'] ), strtolower( trim( (string) $atts['type'] ) ) );

			if ( null === $target ) {
				return '';
			}

			[ $object_id, $context ] = $target;

			$table = self::get_public_table( $object_id, $context );

			if ( self::is_empty_table( $table ) ) {
				return '';
			}

			$caption = trim( (string) ( '' !== $atts['caption'] ? $atts['caption'] : $atts['title'] ) );

			// نام پیش‌فرض در محصول با «جدول مشخصات محصول» ووکامرس یکی نباشد
			$default = ( Hodima_Table_Context::Post === $context && 'product' === get_post_type( $object_id ) ) ? 'مشخصات تکمیلی' : 'جدول مشخصات';

			/*
			 * کلاس مخصوص همین جدول (نه id): قالب توضیح دسته را دو بار می‌خواند
			 * (یک بار برای بررسی خالی بودن) و هر نمایش تکراری با id شمارنده‌دار
			 * از cssSelector اسکیما جدا می‌افتاد. کلاس روی همه نسخه‌ها یکی است.
			 */
			$key       = $context->value . '-' . $object_id;
			$css_class = 'hodima-table-' . $key;

			if ( ! isset( $this->queued_schemas[ $key ] ) && ! is_feed() ) {
				$schema = $this->build_schema_node( $object_id, $context, '' !== $caption ? $caption : $default, $css_class );
				if ( ! empty( $schema['@id'] ) ) {
					$this->queued_schemas[ $key ] = $schema;
				}
			}

			wp_enqueue_style( 'hodima-table-front-css' );

			return $this->build_table_html( $table, $caption, $css_class, $default );
		}

		/**
		 * شیء جدول از روی ویژگی‌های شورت‌کد؛ null یعنی چیزی نمایش داده نشود.
		 *
		 * @return array{0: int, 1: Hodima_Table_Context}|null
		 */
		private function resolve_target( int $object_id, string $type ): ?array {

			$subtype = '';

			if ( '' === $type ) {
				$context = null;
			} elseif ( 'post' === $type || 'term' === $type ) {
				$context = Hodima_Table_Context::from( $type );
			} elseif ( post_type_exists( $type ) ) {
				$context = Hodima_Table_Context::Post;
				$subtype = $type;
			} elseif ( taxonomy_exists( $type ) ) {
				$context = Hodima_Table_Context::Term;
				$subtype = $type;
			} else {
				// نوع نامعتبر قبلا بی‌صدا «نوشته» حساب می‌شد
				return null;
			}

			if ( ! $object_id ) {
				/*
				 * بدون id: در آرشیو دسته (بیرون از حلقه نوشته‌ها) جدول همان
				 * دسته، وگرنه نوشته جاری. قبلا شورت‌کد داخل متن نوشته‌ای که در
				 * آرشیو دسته نمایش داده می‌شد هم جدول دسته را نشان می‌داد، و
				 * type="term" بدون id نادیده گرفته می‌شد.
				 */
				$queried = get_queried_object();

				if ( Hodima_Table_Context::Post !== $context && ( is_category() || is_tag() || is_tax() ) && ! in_the_loop() && $queried instanceof WP_Term ) {
					$object_id = (int) $queried->term_id;
					$context   = Hodima_Table_Context::Term;
				} elseif ( Hodima_Table_Context::Term !== $context ) {
					$object_id = (int) get_the_ID();
					$context   = Hodima_Table_Context::Post;
				}
			}

			$context ??= Hodima_Table_Context::Post;

			if ( ! $object_id || ! $this->can_display( $object_id, $context, $subtype ) ) {
				return null;
			}

			return [ $object_id, $context ];
		}

		/**
		 * جدول این شیء برای بیننده فعلی قابل نمایش است؟ قبلا
		 * [hodima_table id="…"] جدول نوشته خصوصی، پیش‌نویس یا رمزدار را هم
		 * نشان می‌داد.
		 */
		private function can_display( int $object_id, Hodima_Table_Context $context, string $subtype ): bool {

			if ( Hodima_Table_Context::Term === $context ) {
				$term = get_term( $object_id );
				return $term instanceof WP_Term
					&& ( '' === $subtype || $term->taxonomy === $subtype )
					&& is_taxonomy_viewable( $term->taxonomy );
			}

			$post = get_post( $object_id );

			if ( ! ( $post instanceof WP_Post ) || ( '' !== $subtype && $post->post_type !== $subtype ) ) {
				return false;
			}

			if ( post_password_required( $post ) ) {
				return false;
			}

			return is_post_publicly_viewable( $post ) || current_user_can( 'read_post', $post->ID );
		}

		/**
		 * HTML جدول، بدون خط خالی و فاصله بین تگ‌ها: در توضیح دسته و
		 * صفحه‌سازها wpautop داخل جدول <p> و <br> نسازد.
		 */
		private function build_table_html( array $table, string $caption, string $css_class, string $default_label = 'جدول مشخصات' ): string {

			$row_header = self::column_count( $table ) > 1;
			$label      = '' !== $caption ? $caption : $default_label;

			$html  = '<div class="hodima-table-container" role="region" tabindex="0" aria-label="' . esc_attr( $label ) . '">';
			$html .= '<table class="hodima-dynamic-table ' . esc_attr( $css_class ) . '">';

			if ( '' !== $caption ) {
				$html .= '<caption class="hodima-table-caption">' . esc_html( $caption ) . '</caption>';
			}

			if ( [] !== $table['headers'] ) {
				$html .= '<thead><tr>';
				foreach ( $table['headers'] as $header ) {
					$html .= '<th scope="col">' . wp_kses_post( $header ) . '</th>';
				}
				$html .= '</tr></thead>';
			}

			if ( [] !== $table['rows'] ) {
				$html .= '<tbody>';
				foreach ( $table['rows'] as $row ) {
					$html .= '<tr>';
					foreach ( $row as $index => $cell ) {
						// سلول اول سرستون ردیف است (نام ویژگی) — برای دسترس‌پذیری
						$html .= ( 0 === $index && $row_header )
							? '<th scope="row">' . wp_kses_post( $cell ) . '</th>'
							: '<td>' . wp_kses_post( $cell ) . '</td>';
					}
					$html .= '</tr>';
				}
				$html .= '</tbody>';
			}

			return $html . '</table></div>';
		}
	}
}

Hodima_Dynamic_Table::get_instance();
