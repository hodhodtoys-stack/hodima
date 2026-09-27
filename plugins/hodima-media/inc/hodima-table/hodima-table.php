<?php
/**
 * Hodima Dynamic Table
 * Path: wp-content/plugins/hodima-media/inc/hodima-table/hodima-table.php
 * Version: 2.3.0
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

		public const VERSION  = '2.3.0';
		public const META_KEY = '_hodima_table_data';

		private const MAX_COLS = 20;
		private const MAX_ROWS = 100;

		/** نودهای اسکیما برای چاپ در فوتر، کلید = @id تا تکراری نشود. */
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

			foreach ( $this->get_taxonomies() as $taxonomy ) {
				add_action( "{$taxonomy}_edit_form", [ $this, 'render_term_meta_box_edit' ] );
				add_action( "edited_{$taxonomy}", [ $this, 'save_term_meta' ] );
				// هوک created_{$taxonomy} حذف شد: فرم فقط روی {$taxonomy}_edit_form
				// رندر می‌شود، پس در فرم «افزودن» nonce وجود ندارد و آن کالبک
				// همیشه در خط اول return می‌کرد.
			}

			add_shortcode( 'hodima_table', [ $this, 'render_table_shortcode' ] );

			add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );
			add_action( 'wp_enqueue_scripts', [ $this, 'register_front_assets' ] );

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

		/*--------------------------------------------------------------
		# Scripts & Styles
		--------------------------------------------------------------*/

		public function enqueue_admin_scripts( string $hook ): void {

			$screen = get_current_screen();
			if ( ! $screen ) {
				return;
			}

			/*
			 * نسخه قبلی فقط $hook را بررسی می‌کرد، پس روی *هر* نوع پستی و
			 * روی صفحه فهرست ترم‌ها (edit-tags.php) هم ویرایشگر TinyMCE،
			 * jquery-ui-sortable و دشیکون‌ها را لود می‌کرد — در حالی که
			 * متاباکس آنجا اصلا رندر نمی‌شود.
			 */
			$is_post_edit = ( in_array( $screen->base, [ 'post' ], true )
				&& in_array( $screen->post_type, $this->get_post_types(), true ) );

			$is_term_edit = ( 'term' === $screen->base
				&& in_array( $screen->taxonomy, $this->get_taxonomies(), true ) );

			if ( ! $is_post_edit && ! $is_term_edit ) {
				return;
			}

			wp_enqueue_editor();
			wp_enqueue_script( 'jquery-ui-sortable' );
			wp_enqueue_style( 'dashicons' );

			wp_enqueue_style(
				'hodima-table-admin-css',
				HODIMA_MEDIA_URL . '/inc/hodima-table/hodima-admin.css',
				[],
				self::VERSION
			);

			wp_enqueue_script(
				'hodima-table-admin-js',
				HODIMA_MEDIA_URL . '/inc/hodima-table/hodima-admin.js',
				[ 'jquery', 'jquery-ui-sortable' ],
				self::VERSION,
				true
			);

			wp_localize_script( 'hodima-table-admin-js', 'hodimaTableLimits', [
				'maxCols' => self::MAX_COLS,
				'maxRows' => self::MAX_ROWS,
			] );
		}

		/**
		 * استایل فرانت فقط ثبت می‌شود.
		 *
		 * نسخه قبلی آن را روی *هر صفحه سایت* enqueue می‌کرد، حتی صفحاتی که
		 * هیچ جدولی ندارند. حالا فقط وقتی شورت‌کد واقعا رندر شود لود می‌شود.
		 */
		public function register_front_assets(): void {
			wp_register_style(
				'hodima-table-front-css',
				HODIMA_MEDIA_URL . '/inc/hodima-table/hodima-front.css',
				[],
				self::VERSION
			);
		}

		/*--------------------------------------------------------------
		# Meta Box Rendering
		--------------------------------------------------------------*/

		public function add_table_meta_box(): void {
			foreach ( $this->get_post_types() as $post_type ) {
				add_meta_box(
					'hodima_table_meta_box',
					'جدول مشخصات',
					[ $this, 'render_post_meta_box' ],
					$post_type,
					'normal',
					'high'
				);
			}
		}

		public function render_post_meta_box( WP_Post $post ): void {
			wp_nonce_field( 'hodima_table_save_data', 'hodima_table_meta_box_nonce' );
			$table_data = get_post_meta( $post->ID, self::META_KEY, true );
			$this->render_table_builder_ui( is_array( $table_data ) ? $table_data : [] );
		}

		public function render_term_meta_box_edit( $term ): void {

			if ( ! ( $term instanceof WP_Term ) ) {
				return;
			}

			wp_nonce_field( 'hodima_table_save_data', 'hodima_table_meta_box_nonce' );

			$table_data = get_term_meta( $term->term_id, self::META_KEY, true );
			if ( ! is_array( $table_data ) ) {
				$table_data = [];
			}
			?>
			<div class="postbox hodima-term-meta-box">
				<div class="postbox-header hodima-term-meta-box-header">
					<h2 class="hndle">جدول مشخصات</h2>
				</div>
				<div class="inside hodima-term-meta-box-inside">
					<?php $this->render_table_builder_ui( $table_data ); ?>
				</div>
			</div>
			<?php
		}

		/*--------------------------------------------------------------
		# Saving
		--------------------------------------------------------------*/

		public function save_post_meta( int $post_id, $post = null ): void {

			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

			// بازنگری‌ها شناسه مستقل دارند و save_post برایشان هم اجرا
			// می‌شود. نسخه قبلی این را بررسی نمی‌کرد و متای جدول را روی
			// ردیف بازنگری هم می‌نوشت (متای یتیم در postmeta).
			if ( wp_is_post_revision( $post_id ) ) return;

			$post = ( $post instanceof WP_Post ) ? $post : get_post( $post_id );
			if ( ! ( $post instanceof WP_Post ) ) return;
			if ( ! in_array( $post->post_type, $this->get_post_types(), true ) ) return;

			if ( ! $this->verify_save_request() ) return;
			if ( ! current_user_can( 'edit_post', $post_id ) ) return;

			$this->save_data( $post_id, Hodima_Table_Context::Post, $_POST );
		}

		public function save_term_meta( int $term_id ): void {

			if ( ! $this->verify_save_request() ) return;

			/*
			 * نسخه قبلی قابلیت ثابت 'manage_categories' را بررسی می‌کرد.
			 * برای product_cat قابلیت درست manage_product_terms است، پس
			 * کاربری با دسترسی دسته‌بندی مقالات می‌توانست جدول دسته‌بندی
			 * محصولات را هم ویرایش کند. حالا قابلیت از خود تکسونومی
			 * خوانده می‌شود و برای *ویرایش* هم edit_terms درست است.
			 */
			$term = get_term( $term_id );
			if ( ! ( $term instanceof WP_Term ) ) return;

			if ( ! in_array( $term->taxonomy, $this->get_taxonomies(), true ) ) return;

			$taxonomy = get_taxonomy( $term->taxonomy );
			if ( ! $taxonomy || ! current_user_can( $taxonomy->cap->edit_terms ) ) return;

			$this->save_data( $term_id, Hodima_Table_Context::Term, $_POST );
		}

		private function verify_save_request(): bool {

			$nonce = isset( $_POST['hodima_table_meta_box_nonce'] )
				? sanitize_text_field( wp_unslash( $_POST['hodima_table_meta_box_nonce'] ) )
				: '';

			return (bool) wp_verify_nonce( $nonce, 'hodima_table_save_data' );
		}

		private function save_data( int $object_id, Hodima_Table_Context $context, array $post_data ): void {

			$is_term  = ( Hodima_Table_Context::Term === $context );
			$old_data = $is_term
				? get_term_meta( $object_id, self::META_KEY, true )
				: get_post_meta( $object_id, self::META_KEY, true );

			$table_data = $this->get_sanitized_table_data( $post_data );
			$is_empty   = empty( array_filter( $table_data['headers'] ) ) && empty( $table_data['rows'] );

			if ( $old_data === $table_data ) {
				return;
			}

			$this->flush_cache( $object_id, $context );

			if ( $is_empty ) {
				$is_term
					? delete_term_meta( $object_id, self::META_KEY )
					: delete_post_meta( $object_id, self::META_KEY );
				return;
			}

			$is_term
				? update_term_meta( $object_id, self::META_KEY, $table_data )
				: update_post_meta( $object_id, self::META_KEY, $table_data );
		}

		/**
		 * حذف تمام نسخه‌های کش یک شیء.
		 *
		 * کلید کش شامل هش پارامترهای شورت‌کد است، پس یک delete_transient
		 * ساده کافی نیست. شماره نسل بالا می‌رود و کلیدهای قدیمی خودبه‌خود
		 * از دسترس خارج می‌شوند.
		 */
		private function flush_cache( int $object_id, Hodima_Table_Context $context ): void {
			$option = "hodima_table_gen_{$context->value}_{$object_id}";
			update_option( $option, (int) get_option( $option, 0 ) + 1, false );
		}

		private function cache_generation( int $object_id, Hodima_Table_Context $context ): int {
			return (int) get_option( "hodima_table_gen_{$context->value}_{$object_id}", 0 );
		}

		private function get_sanitized_table_data( array $post_data ): array {

			$table_data = [ 'headers' => [], 'rows' => [] ];

			$raw_headers = isset( $post_data['hodima_table_headers'] )
				? array_slice( (array) $post_data['hodima_table_headers'], 0, self::MAX_COLS )
				: [];

			foreach ( $raw_headers as $header ) {
				$table_data['headers'][] = wp_kses_post( wp_unslash( (string) $header ) );
			}

			$col_count = count( $table_data['headers'] );

			$raw_rows = isset( $post_data['hodima_table_rows'] )
				? array_slice( array_values( (array) $post_data['hodima_table_rows'] ), 0, self::MAX_ROWS )
				: [];

			foreach ( $raw_rows as $row ) {

				$row = (array) $row;
				$row = ( $col_count > 0 )
					? array_slice( array_pad( $row, $col_count, '' ), 0, $col_count )
					: array_slice( $row, 0, self::MAX_COLS );

				$sanitized_row = [];
				$row_is_empty  = true;

				foreach ( $row as $cell ) {
					$value           = wp_kses_post( wp_unslash( (string) $cell ) );
					$sanitized_row[] = $value;
					if ( '' !== trim( wp_strip_all_tags( $value ) ) ) {
						$row_is_empty = false;
					}
				}

				if ( ! $row_is_empty ) {
					$table_data['rows'][] = $sanitized_row;
				}
			}

			return $table_data;
		}

		/*--------------------------------------------------------------
		# Builder UI
		--------------------------------------------------------------*/

		private function render_table_builder_ui( array $table_data ): void {

			$headers = ! empty( $table_data['headers'] ) ? $table_data['headers'] : [ '', '', '', '' ];
			$rows    = ! empty( $table_data['rows'] ) ? $table_data['rows'] : [ [ '', '', '', '' ] ];

			$col_count     = count( $headers );
			$input_counter = 1;
			?>
			<div class="hodima-wrap" id="hodima-admin-wrap" data-row-count="<?php echo esc_attr( (string) count( $rows ) ); ?>">

				<div class="hodima-shortcode-info">
					<p><strong>شورت‌کد نمایش در فرانت:</strong> <code>[hodima_table]</code></p>
					<p class="hodima-hint">سقف مجاز: <?php echo (int) self::MAX_COLS; ?> ستون و <?php echo (int) self::MAX_ROWS; ?> ردیف.</p>
				</div>

				<div class="hodima-toolbar">
					<div class="hodima-toolbar-group">
						<button type="button" id="hodima-add-row-btn" class="button button-primary"><span class="dashicons dashicons-plus-alt2 hodima-btn-icon"></span> افزودن ردیف</button>
						<button type="button" id="hodima-add-col-btn" class="button button-secondary"><span class="dashicons dashicons-plus-alt2 hodima-btn-icon"></span> افزودن ستون</button>
						<button type="button" id="hodima-global-link-btn" class="button hodima-btn-link" title="یا زدن کلید Ctrl+K داخل سلول"><span class="dashicons dashicons-admin-links hodima-btn-icon"></span> درج لینک</button>
					</div>

					<div class="hodima-toolbar-group hodima-toolbar-group--end">
						<button type="button" id="hodima-export-csv" class="button"><span class="dashicons dashicons-download hodima-btn-icon"></span> خروجی CSV</button>
						<input type="file" id="hodima-import-csv" accept=".csv,text/csv" class="hodima-hidden-input">
						<button type="button" id="hodima-import-csv-btn" class="button"><span class="dashicons dashicons-upload hodima-btn-icon"></span> ورود CSV</button>
					</div>
				</div>

				<div id="hodima-admin-table-wrapper">
					<table class="hodima-admin-table" id="hodima-table-builder">
						<thead>
							<tr id="hodima-headers-row">
								<th class="hodima-col-ops">هدر</th>
								<?php foreach ( $headers as $index => $header ) :
									$input_id = 'hodima_header_' . $input_counter++;
									?>
									<th>
										<div class="hodima-col-header-inner">
											<div class="hodima-col-actions">
												<span class="hodima-icon-btn hodima-remove-col" data-index="<?php echo esc_attr( (string) $index ); ?>" title="حذف ستون"><span class="dashicons dashicons-trash"></span></span>
											</div>
											<input type="text" id="<?php echo esc_attr( $input_id ); ?>" name="hodima_table_headers[]" value="<?php echo esc_attr( $header ); ?>" placeholder="عنوان ستون">
										</div>
									</th>
								<?php endforeach; ?>
							</tr>
						</thead>
						<tbody id="hodima-rows-body">
							<?php foreach ( $rows as $row_index => $row ) : ?>
								<tr>
									<td class="hodima-col-ops">
										<div class="hodima-row-actions">
											<span class="dashicons dashicons-menu hodima-drag-handle" title="جابجایی ردیف"></span>
											<span class="hodima-icon-btn hodima-remove-row" title="حذف ردیف"><span class="dashicons dashicons-trash"></span></span>
										</div>
									</td>
									<?php for ( $c = 0; $c < $col_count; $c++ ) :
										$textarea_id = 'hodima_cell_' . $input_counter++;
										?>
										<td>
											<textarea id="<?php echo esc_attr( $textarea_id ); ?>" rows="2" name="hodima_table_rows[<?php echo esc_attr( (string) $row_index ); ?>][]" class="hodima-cell-textarea" placeholder="مقدار"><?php echo esc_textarea( (string) ( $row[ $c ] ?? '' ) ); ?></textarea>
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
		 * جدول را به آرایه PropertyValue تبدیل می‌کند.
		 *
		 * این همان ساختاری است که گوگل برای مشخصات محصول استفاده می‌کند
		 * (فیلد additionalProperty روی نود Product). برای اتصال، در
		 * schema/product-schema-pro.php قبل از خط
		 *
		 *     if ( ! empty( $additional_properties ) ) {
		 *
		 * این سه خط را اضافه کنید:
		 *
		 *     if ( class_exists( 'Hodima_Dynamic_Table' ) ) {
		 *         $additional_properties = array_merge( $additional_properties,
		 *             Hodima_Dynamic_Table::get_property_values( $id, 'post' ) );
		 *     }
		 *
		 * @return array<int, array<string, string>>
		 */
		public static function get_property_values( int $object_id, string $context = 'post' ): array {

			$table_data = ( 'term' === $context )
				? get_term_meta( $object_id, self::META_KEY, true )
				: get_post_meta( $object_id, self::META_KEY, true );

			if ( empty( $table_data ) || ! is_array( $table_data ) ) {
				return [];
			}

			$headers = (array) ( $table_data['headers'] ?? [] );
			$rows    = (array) ( $table_data['rows'] ?? [] );

			if ( empty( $rows ) ) {
				return [];
			}

			$properties = [];

			/*
			 * دو چیدمان رایج برای جدول مشخصات:
			 *
			 *   دو ستونی  → ستون اول نام ویژگی، ستون دوم مقدار
			 *   چند ستونی → عنوان هر ستون نام ویژگی است
			 *
			 * تشخیص خودکار، چون هر دو در سایت‌های فروشگاهی استفاده می‌شوند.
			 */
			$two_column = ( count( $headers ) === 2 ) || ( count( $headers ) === 0 && isset( $rows[0] ) && count( (array) $rows[0] ) === 2 );

			foreach ( $rows as $row ) {

				$row = array_values( (array) $row );

				if ( $two_column ) {
					$name  = wp_strip_all_tags( (string) ( $row[0] ?? '' ) );
					$value = wp_strip_all_tags( (string) ( $row[1] ?? '' ) );

					if ( '' !== trim( $name ) && '' !== trim( $value ) ) {
						$properties[] = [
							'@type' => 'PropertyValue',
							'name'  => trim( $name ),
							'value' => trim( $value ),
						];
					}
					continue;
				}

				foreach ( $row as $index => $cell ) {
					$name  = wp_strip_all_tags( (string) ( $headers[ $index ] ?? '' ) );
					$value = wp_strip_all_tags( (string) $cell );

					if ( '' !== trim( $name ) && '' !== trim( $value ) ) {
						$properties[] = [
							'@type' => 'PropertyValue',
							'name'  => trim( $name ),
							'value' => trim( $value ),
						];
					}
				}
			}

			return (array) apply_filters( 'hodima_table_property_values', $properties, $object_id, $context );
		}

		/**
		 * افزودن مشخصات جدول دستی به additionalProperty محصول.
		 *
		 * عمدا محافظه‌کارانه است — فقط جدول‌های *دقیقا دو ستونی* سهم
		 * می‌دهند. جدول سه ستون به بالا معمولا سایزبندی یا مقایسه است،
		 * نه مشخصات، و تبدیل آن به جفت نام/مقدار خروجی بی‌معنی می‌سازد:
		 *
		 *     {"name":"سایز","value":"S"}
		 *     {"name":"سایز","value":"M"}
		 *
		 * داده بی‌کیفیت در additionalProperty بدتر از نبودش است، چون
		 * گوگل مقادیر متناقض را سیگنال کیفیت پایین می‌بیند.
		 *
		 * @param array $properties مقادیری که ماژول‌های قبلی ساخته‌اند.
		 * @param int   $product_id شناسه محصول.
		 */
		public function append_manual_properties( array $properties, int $product_id ): array {

			if ( ! $product_id ) {
				return $properties;
			}

			$table_data = get_post_meta( $product_id, self::META_KEY, true );

			if ( empty( $table_data ) || ! is_array( $table_data ) ) {
				return $properties;
			}

			if ( ! $this->is_two_column_table( $table_data ) ) {
				return $properties;
			}

			// نام‌هایی که ماژول‌های قبل‌تر (hodima-woo-table) پر کرده‌اند
			// مرجع‌اند؛ آن‌ها از ویژگی‌های ساختاریافته ووکامرس می‌آیند.
			$taken = [];
			foreach ( $properties as $property ) {
				if ( isset( $property['name'] ) ) {
					$taken[ $this->normalize_property_name( (string) $property['name'] ) ] = true;
				}
			}

			foreach ( self::get_property_values( $product_id, 'post' ) as $candidate ) {

				$key = $this->normalize_property_name( (string) ( $candidate['name'] ?? '' ) );

				if ( '' === $key || isset( $taken[ $key ] ) ) {
					continue;
				}

				$taken[ $key ] = true;
				$properties[]  = $candidate;
			}

			return $properties;
		}

		/** جدول دقیقا دو ستونی است؟ (هدر دوتایی، یا بدون هدر با ردیف‌های دوتایی) */
		private function is_two_column_table( array $table_data ): bool {

			$headers = (array) ( $table_data['headers'] ?? [] );
			$rows    = (array) ( $table_data['rows'] ?? [] );

			if ( empty( $rows ) ) {
				return false;
			}

			$header_count = count( array_filter( $headers, static fn( $h ) => '' !== trim( wp_strip_all_tags( (string) $h ) ) ) );

			if ( $header_count > 0 ) {
				return 2 === $header_count;
			}

			// بدون هدر: هر ردیف باید دقیقا دو سلول داشته باشد
			foreach ( $rows as $row ) {
				if ( 2 !== count( (array) $row ) ) {
					return false;
				}
			}

			return true;
		}

		/** مقایسه نام‌ها بدون حساسیت به فاصله، نیم‌فاصله و حروف. */
		private function normalize_property_name( string $name ): string {
			$name = wp_strip_all_tags( $name );
			$name = str_replace( [ "\u{200c}", "\u{200f}", "\u{200e}" ], '', $name );
			$name = preg_replace( '/\s+/u', ' ', $name );
			return mb_strtolower( trim( (string) $name ) );
		}

		/**
		 * نود اسکیمای جدول.
		 *
		 * نسخه قبلی ItemList تولید می‌کرد. آن نوع در گوگل برای فهرست مرتب
		 * آیتم‌ها *با لینک* است؛ بدون url یا item هیچ نتیجه غنی نمی‌سازد و
		 * فقط با ItemList صفحات دسته‌بندی هم‌نوع و بی‌هویت می‌شد.
		 *
		 * حالا:
		 *   - نوع Table (زیرمجموعه WebPageElement) که معنای درستی دارد
		 *   - @id و isPartOf، پس عضو گراف صفحه است نه موجودیت شناور
		 *   - cssSelector تا گوگل بداند این نود به کدام عنصر HTML اشاره دارد
		 *   - مقادیر واقعی به شکل PropertyValue
		 */
		private function build_schema_node( int $object_id, Hodima_Table_Context $context, string $title ): array {

			$properties = self::get_property_values( $object_id, $context->value );

			if ( empty( $properties ) ) {
				return [];
			}

			$page_url = function_exists( 'hodima_get_canonical_url' ) ? hodima_get_canonical_url() : '';

			if ( '' === $page_url ) {
				$link     = ( Hodima_Table_Context::Term === $context ) ? get_term_link( $object_id ) : get_permalink( $object_id );
				$page_url = ( is_wp_error( $link ) || ! $link ) ? '' : (string) $link;
			}

			if ( '' === $page_url ) {
				return [];
			}

			$page_url = trailingslashit( $page_url );

			$node = [
				'@type'      => 'Table',
				'@id'        => $page_url . '#specs-table',
				'name'       => '' !== $title ? $title : 'جدول مشخصات',
				'isPartOf'   => [ '@id' => $page_url . '#webpage' ],
				'about'      => [ '@id' => $page_url . '#webpage' ],
				'cssSelector' => '.hodima-dynamic-table',
				'mainEntity' => [
					'@type'             => 'PropertyValue',
					'name'              => '' !== $title ? $title : 'جدول مشخصات',
					'valueReference'    => $properties,
				],
			];

			return (array) apply_filters( 'hodima_table_schema_node', $node, $object_id, $context->value );
		}

		public function print_queued_schemas(): void {

			if ( empty( $this->queued_schemas ) ) {
				return;
			}

			$graph = array_values( $this->queued_schemas );

			$payload = [
				'@context' => 'https://schema.org',
				'@graph'   => $graph,
			];

			echo "\n" . '<script type="application/ld+json" id="hodima-table-schema">'
				. wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP )
				. '</script>' . "\n";
		}

		/*--------------------------------------------------------------
		# Frontend Shortcode
		--------------------------------------------------------------*/

		public function render_table_shortcode( array|string $atts = [] ): string {

			if ( is_admin() && ! wp_doing_ajax() ) {
				return '';
			}

			$atts = shortcode_atts( [
				'id'      => '',
				'type'    => '',
				'title'   => '',
				'caption' => '',
			], (array) $atts, 'hodima_table' );

			$object_id = absint( $atts['id'] );
			$context   = strtolower( trim( (string) $atts['type'] ) );

			if ( ! $object_id ) {
				if ( is_tax() || is_category() || is_tag() ) {
					$object_id = get_queried_object_id();
					$context   = Hodima_Table_Context::Term->value;
				} else {
					$object_id = (int) get_the_ID();
					$context   = Hodima_Table_Context::Post->value;
				}
			}

			if ( ! $object_id ) {
				return '';
			}

			$context_enum = Hodima_Table_Context::tryFrom( $context ) ?? Hodima_Table_Context::Post;

			/*
			 * کلید کش شامل هش پارامترهای شورت‌کد است.
			 * نسخه قبلی فقط شناسه و نوع را در کلید داشت، پس
			 * [hodima_table title="الف"] و [hodima_table title="ب"] روی یک
			 * صفحه هر دو همان HTML کش‌شده اول را برمی‌گرداندند.
			 */
			$cache_key = sprintf(
				'hodima_tbl_%s_%d_%d_%s',
				$context_enum->value,
				$object_id,
				$this->cache_generation( $object_id, $context_enum ),
				substr( md5( (string) wp_json_encode( $atts ) ), 0, 8 )
			);

			$cached = get_transient( $cache_key );

			if ( is_array( $cached ) && isset( $cached['html'] ) ) {
				$this->queue_schema( $cached['schema'] ?? [] );
				if ( '' !== $cached['html'] ) {
					wp_enqueue_style( 'hodima-table-front-css' );
				}
				return (string) $cached['html'];
			}

			$table_data = ( Hodima_Table_Context::Term === $context_enum )
				? get_term_meta( $object_id, self::META_KEY, true )
				: get_post_meta( $object_id, self::META_KEY, true );

			if ( empty( $table_data ) || ! is_array( $table_data ) ) {
				return '';
			}

			$headers    = (array) ( $table_data['headers'] ?? [] );
			$rows       = (array) ( $table_data['rows'] ?? [] );
			$has_header = count( array_filter( $headers ) ) > 0;

			if ( ! $has_header && empty( $rows ) ) {
				return '';
			}

			$caption = '' !== $atts['caption'] ? $atts['caption'] : $atts['title'];

			$html   = $this->build_table_html( $headers, $rows, (string) $caption, $has_header );
			$schema = $this->build_schema_node( $object_id, $context_enum, (string) $caption );

			$this->queue_schema( $schema );

			set_transient( $cache_key, [ 'html' => $html, 'schema' => $schema ], 12 * HOUR_IN_SECONDS );

			if ( '' !== $html ) {
				wp_enqueue_style( 'hodima-table-front-css' );
			}

			return $html;
		}

		/** افزودن نود به صف با کلید @id تا هرگز تکراری چاپ نشود. */
		private function queue_schema( array $schema ): void {
			if ( empty( $schema ) || empty( $schema['@id'] ) ) {
				return;
			}
			$this->queued_schemas[ $schema['@id'] ] = $schema;
		}

		private function build_table_html( array $headers, array $rows, string $caption, bool $has_header ): string {

			ob_start();
			?>
			<div class="hodima-table-container" role="region" tabindex="0" aria-label="<?php echo esc_attr( '' !== $caption ? $caption : 'جدول مشخصات' ); ?>">
				<table class="hodima-dynamic-table">
					<?php if ( '' !== $caption ) : ?>
						<caption class="hodima-table-caption"><?php echo esc_html( $caption ); ?></caption>
					<?php endif; ?>

					<?php if ( $has_header ) : ?>
						<thead>
							<tr>
								<?php foreach ( $headers as $header ) : ?>
									<th scope="col"><?php echo wp_kses_post( $header ); ?></th>
								<?php endforeach; ?>
							</tr>
						</thead>
					<?php endif; ?>

					<?php if ( ! empty( $rows ) ) : ?>
						<tbody>
							<?php foreach ( $rows as $row ) : ?>
								<tr>
									<?php foreach ( (array) $row as $cell_index => $cell ) :
										$label = isset( $headers[ $cell_index ] ) ? wp_strip_all_tags( (string) $headers[ $cell_index ] ) : '';
										?>
										<?php if ( 0 === $cell_index ) : ?>
											<?php /* سلول اول سرستون ردیف است — هم برای دسترس‌پذیری، هم چون
											         گوگل جفت «نام ویژگی / مقدار» را از همین ساختار می‌خواند. */ ?>
											<th scope="row" data-label="<?php echo esc_attr( $label ); ?>"><?php echo wp_kses_post( $cell ); ?></th>
										<?php else : ?>
											<td data-label="<?php echo esc_attr( $label ); ?>"><?php echo wp_kses_post( $cell ); ?></td>
										<?php endif; ?>
									<?php endforeach; ?>
								</tr>
							<?php endforeach; ?>
						</tbody>
					<?php endif; ?>
				</table>
			</div>
			<?php
			return (string) ob_get_clean();
		}
	}
}

Hodima_Dynamic_Table::get_instance();
