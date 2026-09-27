<?php
/**
 * Manual Related Links (Products + Posts + Categories)
 * Path: /wp-content/themes/hodima/inc/manual_related_link/manual_related_link.php
 *
 * @version 1.8.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Hodima_Manual_Related_Link' ) ) {

    class Hodima_Manual_Related_Link {

        private static $instance = null;

        const VERSION            = '1.8.0';
        const DEFAULT_ITEM_COUNT = 3;
        const META_KEY           = '_hodima_mrl_data';

        public static function get_instance() {
            if ( null === self::$instance ) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct() {
            add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
            add_action( 'save_post', array( $this, 'save_meta_box_data' ), 10, 2 );
            add_action( 'init', array( $this, 'register_taxonomy_hooks' ) );
            add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
            add_shortcode( 'manual_related_products', array( $this, 'render_shortcode' ) );
            add_shortcode( 'manual_related_links', array( $this, 'render_shortcode' ) );
            add_action( 'wp_enqueue_scripts', array( $this, 'register_frontend_assets' ) );
        }

        private function get_items_count() {
            return max( 1, (int) apply_filters( 'hodima_internal_links_count', self::DEFAULT_ITEM_COUNT ) );
        }

        private function get_post_types() {
            return (array) apply_filters( 'hodima_internal_links_post_types', array( 'product', 'post' ) );
        }

        private function get_taxonomies() {
            return (array) apply_filters( 'hodima_internal_links_taxonomies', array( 'category', 'product_cat' ) );
        }

        private function render_shortcode_notice() {
            echo '<p class="hodima-mrl-notice">شورت‌کد نمایش در فرانت: <code>[manual_related_products]</code></p>';
        }

        /** ساختار یکدست یک آیتم — هیچ کلیدی نمی‌تواند غایب باشد. */
        private function normalize_item( $item ) {
            $item = is_array( $item ) ? $item : array();

            return array(
                'title'  => isset( $item['title'] ) ? (string) $item['title'] : '',
                'url'    => isset( $item['url'] ) ? (string) $item['url'] : '',
                'img_id' => isset( $item['img_id'] ) ? absint( $item['img_id'] ) : 0,
            );
        }

        private function get_items_data( $object_id, $context = 'post' ) {

            $object_id = absint( $object_id );
            if ( ! $object_id ) {
                return array();
            }

            $count    = $this->get_items_count();
            $is_term  = ( 'term' === $context );
            $data     = $is_term
                ? get_term_meta( $object_id, self::META_KEY, true )
                : get_post_meta( $object_id, self::META_KEY, true );

            if ( ! empty( $data ) && is_array( $data ) ) {
                // نسخه قبلی خروجی array_pad را بدون بررسی کلیدها برمی‌گرداند.
                // اگر داده ذخیره‌شده شکل متفاوتی داشت (مثلا از نسخه قدیمی‌تر)،
                // $item['img_id'] اخطار Undefined array key می‌داد.
                $items = array_map( array( $this, 'normalize_item' ), array_values( $data ) );

                if ( count( $items ) < $count ) {
                    $items = array_pad( $items, $count, $this->normalize_item( array() ) );
                }

                return array_slice( $items, 0, $count );
            }

            // سازگاری با ساختار متای نسخه‌های قدیمی
            $fallback = array();
            for ( $i = 1; $i <= $count; $i++ ) {
                $fallback[] = $this->normalize_item( array(
                    'title'  => $is_term ? get_term_meta( $object_id, "_internal_link_{$i}_title", true )  : get_post_meta( $object_id, "_internal_link_{$i}_title", true ),
                    'url'    => $is_term ? get_term_meta( $object_id, "_internal_link_{$i}_url", true )    : get_post_meta( $object_id, "_internal_link_{$i}_url", true ),
                    'img_id' => $is_term ? get_term_meta( $object_id, "_internal_link_{$i}_img_id", true ) : get_post_meta( $object_id, "_internal_link_{$i}_img_id", true ),
                ) );
            }

            return $fallback;
        }

        public function add_meta_box() {
            add_meta_box(
                'hodima_internal_links_box',
                'ویترین لینک داخلی',
                array( $this, 'render_meta_box_content' ),
                $this->get_post_types(),
                'normal',
                'high'
            );
        }

        public function render_meta_box_content( $post ) {
            wp_nonce_field( 'hodima_internal_links_save', 'hodima_internal_links_nonce' );
            $this->render_shortcode_notice();
            $this->render_fields( $post->ID, 'post' );
        }

        public function save_meta_box_data( $post_id, $post ) {

            if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
            if ( wp_is_post_revision( $post_id ) ) return;
            if ( ! ( $post instanceof WP_Post ) ) return;
            if ( ! in_array( $post->post_type, $this->get_post_types(), true ) ) return;

            $nonce = isset( $_POST['hodima_internal_links_nonce'] )
                ? sanitize_text_field( wp_unslash( $_POST['hodima_internal_links_nonce'] ) )
                : '';

            if ( ! wp_verify_nonce( $nonce, 'hodima_internal_links_save' ) ) return;
            if ( ! current_user_can( 'edit_post', $post_id ) ) return;

            $this->save_fields( $post_id, 'post' );
        }

        public function register_taxonomy_hooks() {
            foreach ( $this->get_taxonomies() as $taxonomy ) {
                add_action( "{$taxonomy}_edit_form", array( $this, 'render_taxonomy_edit_fields' ), 9, 2 );
                add_action( "edited_{$taxonomy}", array( $this, 'save_taxonomy_fields' ), 10, 2 );
                // هوک create_{$taxonomy} حذف شد: فرم فقط روی {$taxonomy}_edit_form
                // رندر می‌شود، پس در فرم «افزودن دسته‌بندی» هیچ nonce ای وجود
                // ندارد و آن کالبک همیشه در خط اول return می‌کرد.
            }
        }

        public function render_taxonomy_edit_fields( $term, $taxonomy = '' ) {

            if ( ! ( $term instanceof WP_Term ) ) return;

            wp_nonce_field( 'hodima_internal_links_term_save', 'hodima_internal_links_term_nonce' );
            ?>
            <div class="postbox hodima-mrl-postbox">
                <div class="postbox-header">
                    <h2 class="hndle hodima-mrl-hndle">ویترین لینک داخلی</h2>
                </div>
                <div class="inside hodima-mrl-tax-wrap">
                    <?php $this->render_shortcode_notice(); ?>
                    <?php $this->render_fields( $term->term_id, 'term' ); ?>
                </div>
            </div>
            <?php
        }

        public function save_taxonomy_fields( $term_id, $tt_id = 0 ) {

            $nonce = isset( $_POST['hodima_internal_links_term_nonce'] )
                ? sanitize_text_field( wp_unslash( $_POST['hodima_internal_links_term_nonce'] ) )
                : '';

            if ( ! wp_verify_nonce( $nonce, 'hodima_internal_links_term_save' ) ) return;

            // تکسونومی از خود ترم خوانده می‌شود، نه از $_POST.
            // نسخه قبلی اگر فیلد taxonomy در درخواست نبود بی‌صدا return می‌کرد،
            // و بررسی دسترسی را روی مقداری انجام می‌داد که کاربر فرستاده بود.
            $term = get_term( $term_id );
            if ( ! ( $term instanceof WP_Term ) ) return;

            $taxonomy = get_taxonomy( $term->taxonomy );
            if ( ! $taxonomy ) return;

            // برای *ویرایش* ترم، قابلیت درست edit_terms است نه manage_terms.
            if ( ! current_user_can( $taxonomy->cap->edit_terms ) ) return;

            $this->save_fields( $term_id, 'term' );
        }

        private function render_fields( $object_id = 0, $context = 'post' ) {

            $items_data = $this->get_items_data( $object_id, $context );

            echo '<div class="hodima-mrl-grid">';

            foreach ( $items_data as $index => $item ) {

                $i       = (int) $index + 1;
                $img_url = $item['img_id'] ? wp_get_attachment_image_url( $item['img_id'], 'thumbnail' ) : '';
                ?>
                <div class="hodima-mrl-item">
                    <strong class="hodima-mrl-item-title"><?php printf( 'بخش %d', $i ); ?></strong>

                    <p>
                        <label for="internal_link_<?php echo $i; ?>_title">عنوان / کلمه کلیدی:</label><br>
                        <input type="text" id="internal_link_<?php echo $i; ?>_title" name="internal_link_<?php echo $i; ?>_title" value="<?php echo esc_attr( $item['title'] ); ?>">
                    </p>

                    <p>
                        <label for="internal_link_<?php echo $i; ?>_url">لینک:</label><br>
                        <input type="url" id="internal_link_<?php echo $i; ?>_url" name="internal_link_<?php echo $i; ?>_url" value="<?php echo esc_url( $item['url'] ); ?>">
                    </p>

                    <div>
                        <label>کاور:</label><br>
                        <div id="preview_internal_link_<?php echo $i; ?>_img" class="hodima-mrl-preview">
                            <?php if ( $img_url ) : ?>
                                <img src="<?php echo esc_url( $img_url ); ?>" alt="">
                            <?php endif; ?>
                        </div>
                        <input type="hidden" name="internal_link_<?php echo $i; ?>_img_id" id="internal_link_<?php echo $i; ?>_img_id" value="<?php echo esc_attr( (string) $item['img_id'] ); ?>">
                        <div class="hodima-mrl-actions">
                            <button type="button" class="button hodima_upload_image" data-target="internal_link_<?php echo $i; ?>_img">تصویر</button>
                            <button type="button" class="button hodima_remove_image" data-target="internal_link_<?php echo $i; ?>_img"<?php echo $item['img_id'] ? '' : ' hidden'; ?>>حذف</button>
                        </div>
                    </div>
                </div>
                <?php
            }

            echo '</div>';
        }

        private function save_fields( $object_id, $context = 'post' ) {

            $is_term = ( 'term' === $context );
            $items   = array();
            $has_any = false;

            for ( $i = 1; $i <= $this->get_items_count(); $i++ ) {

                $item = $this->normalize_item( array(
                    'title'  => isset( $_POST["internal_link_{$i}_title"] )  ? sanitize_text_field( wp_unslash( $_POST["internal_link_{$i}_title"] ) ) : '',
                    'url'    => isset( $_POST["internal_link_{$i}_url"] )    ? esc_url_raw( wp_unslash( $_POST["internal_link_{$i}_url"] ) ) : '',
                    'img_id' => isset( $_POST["internal_link_{$i}_img_id"] ) ? absint( $_POST["internal_link_{$i}_img_id"] ) : 0,
                ) );

                if ( '' !== $item['title'] || '' !== $item['url'] || $item['img_id'] > 0 ) {
                    $has_any = true;
                }

                $items[] = $item;
            }

            /*
             * اگر هیچ فیلدی پر نشده، ردیف متا حذف می‌شود.
             * نسخه قبلی بدون شرط update_meta صدا می‌زد، یعنی هر محصول و
             * نوشته‌ای که ذخیره می‌شد یک ردیف در postmeta می‌گرفت حتی اگر
             * این ماژول اصلا برایش استفاده نشده بود.
             */
            if ( $has_any ) {
                $is_term
                    ? update_term_meta( $object_id, self::META_KEY, $items )
                    : update_post_meta( $object_id, self::META_KEY, $items );
            } else {
                $is_term
                    ? delete_term_meta( $object_id, self::META_KEY )
                    : delete_post_meta( $object_id, self::META_KEY );
            }
        }

        public function enqueue_admin_scripts( $hook ) {

            $screen = get_current_screen();
            if ( ! $screen ) return;

            /*
             * نسخه قبلی فقط post_type و taxonomy را بررسی می‌کرد. در صفحه
             * *فهرست* محصولات (edit.php) هم post_type برابر product است، پس
             * wp_enqueue_media() — که حدود صد کیلوبایت اسکریپت و قالب
             * Backbone لود می‌کند — روی صفحه‌ای اجرا می‌شد که هیچ متاباکسی
             * ندارد. حالا فقط صفحات ویرایش.
             */
            $is_post_edit = ( 'post' === $screen->base && in_array( $screen->post_type, $this->get_post_types(), true ) );
            $is_term_edit = ( 'term' === $screen->base && in_array( $screen->taxonomy, $this->get_taxonomies(), true ) );

            if ( ! $is_post_edit && ! $is_term_edit ) return;

            wp_enqueue_media();

            wp_enqueue_style(
                'hodima-mrl-admin-css',
                get_theme_file_uri( '/inc/manual_related_link/admin-style.css' ),
                array(),
                self::VERSION
            );

            /*
             * وابستگی media-editor لازم است. نسخه قبلی آرایه خالی داشت با
             * توضیح «تا اسکریپت سبک‌تر اجرا شود» — ولی حذف وابستگی چیزی را
             * سبک نمی‌کند، فقط تضمین ترتیب اجرا را از بین می‌برد و
             * wp.media ممکن است هنوز تعریف نشده باشد.
             */
            wp_enqueue_script(
                'hodima-mrl-admin-js',
                get_theme_file_uri( '/inc/manual_related_link/admin-script.js' ),
                array( 'media-editor' ),
                self::VERSION,
                true
            );

            wp_localize_script( 'hodima-mrl-admin-js', 'hodima_mrl_vars', array(
                'i18n_title'  => 'انتخاب تصویر',
                'i18n_button' => 'استفاده از این تصویر',
            ) );
        }

        public function register_frontend_assets() {
            wp_register_style(
                'hodima-related-products',
                get_theme_file_uri( '/inc/manual_related_link/frontend-style.css' ),
                array(),
                self::VERSION
            );
        }

        public function render_shortcode( $atts ) {

            if ( is_admin() && ! wp_doing_ajax() ) {
                return '';
            }

            $atts = shortcode_atts( array(
                'title' => 'ویترین پیشنهادی',
                'id'    => '',
                'type'  => 'auto',
            ), $atts, 'manual_related_products' );

            $type = strtolower( trim( (string) $atts['type'] ) );

            if ( ! in_array( $type, array( 'post', 'term', 'auto' ), true ) ) {
                $type = 'auto';
            }

            if ( 'auto' === $type ) {
                if ( is_category() || is_tax() ) {
                    $type = 'term';
                } elseif ( is_singular() ) {
                    $type = 'post';
                }
            }

            // نسخه قبلی در این حالت با context برابر «auto» ادامه می‌داد و
            // get_items_data آن را مثل «post» تفسیر می‌کرد — یعنی اگر شناسه
            // یک ترم داده شده بود، بی‌صدا متای نوشته خوانده می‌شد.
            if ( 'post' !== $type && 'term' !== $type ) {
                return '';
            }

            $object_id = $atts['id']
                ? absint( $atts['id'] )
                : ( 'term' === $type ? get_queried_object_id() : get_the_ID() );

            if ( ! $object_id ) return '';

            $items_data = $this->get_items_data( $object_id, $type );

            $valid_items = array_filter( $items_data, static function ( $item ) {
                return ! empty( $item['url'] ) && ! empty( $item['img_id'] );
            } );

            if ( empty( $valid_items ) ) return '';

            wp_enqueue_style( 'hodima-related-products' );

            ob_start();
            ?>
            <div class="manual-related-inline" data-nosnippet>
                <?php if ( '' !== $atts['title'] ) : ?>
                    <h3 class="manual-related-title"><?php echo esc_html( $atts['title'] ); ?></h3>
                <?php endif; ?>

                <ul class="related-products-grid">
                    <?php foreach ( $valid_items as $item ) :

                        $display_title = '' !== $item['title'] ? $item['title'] : 'مشاهده لینک';
                        $img_url       = wp_get_attachment_image_url( $item['img_id'], 'medium' );

                        if ( ! $img_url ) {
                            continue;
                        }
                        ?>
                        <li>
                            <figure class="related-item-figure">
                                <a href="<?php echo esc_url( $item['url'] ); ?>" class="related-item-full-link" aria-label="<?php echo esc_attr( $display_title ); ?>"></a>
                                <figcaption class="related-item-title-wrapper">
                                    <span class="related-item-link"><?php echo esc_html( $display_title ); ?></span>
                                </figcaption>
                                <div class="related-item-img-wrap">
                                    <img src="<?php echo esc_url( $img_url ); ?>"
                                         class="related-item-img"
                                         alt=""
                                         role="presentation"
                                         aria-hidden="true"
                                         decoding="async"
                                         loading="lazy"
                                         data-nosnippet>
                                </div>
                            </figure>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php
            return ob_get_clean();
        }
    }
}

Hodima_Manual_Related_Link::get_instance();
