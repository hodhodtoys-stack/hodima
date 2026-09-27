<?php
/* Minimal WooCommerce stand-in for the schema harness (only when HARNESS_WC=1). */
if ( getenv( 'HARNESS_WC' ) !== '1' ) return;

class WooCommerce { public $structured_data; public function __construct() { $this->structured_data = new WC_Structured_Data_Stub(); } }
class WC_Structured_Data_Stub { public function output_structured_data() {} public function output_email_structured_data() {} }
function WC() { static $wc; return $wc ??= new WooCommerce(); }

class WC_Product {
	public function __construct( public int $id ) {}
	private function m( $k ) { return get_post_meta( $this->id, $k, true ); }
	public function get_id() { return $this->id; }
	public function get_name() { return get_the_title( $this->id ); }
	public function get_sku() { return (string) $this->m( '_sku' ); }
	public function get_price() { return (string) $this->m( '_price' ); }
	public function get_regular_price() { return (string) $this->m( '_regular_price' ); }
	public function get_sale_price() { return (string) $this->m( '_sale_price' ); }
	public function get_image_id() { return (int) get_post_thumbnail_id( $this->id ); }
	public function get_gallery_image_ids() { return array_filter( array_map( 'intval', explode( ',', (string) $this->m( '_product_image_gallery' ) ) ) ); }
	public function get_short_description() { return (string) get_post_field( 'post_excerpt', $this->id ); }
	public function get_description() { return (string) get_post_field( 'post_content', $this->id ); }
	public function get_review_count() { return (int) $this->m( '_wc_review_count' ); }
	public function get_average_rating() { return (string) $this->m( '_wc_average_rating' ); }
	public function get_rating_count() { return $this->get_review_count(); }
	public function get_attribute( $a ) { return (string) $this->m( 'attr_' . $a ); }
	public function get_attributes() { return []; }
	public function get_date_modified() { return new WC_DateTime( get_post_modified_time( 'c', true, $this->id ) ); }
	public function get_date_created() { return new WC_DateTime( get_post_time( 'c', true, $this->id ) ); }
	public function get_variation_prices( $d = false ) { return [ 'price' => [ $this->get_price() ] ]; }
	public function is_in_stock() { return $this->m( '_stock_status' ) !== 'outofstock'; }
	public function get_stock_status() { return $this->m( '_stock_status' ) ?: 'instock'; }
	public function get_stock_quantity() { return null; }
	public function is_type( $t ) { return in_array( 'simple', (array) $t, true ); }
	public function get_type() { return 'simple'; }
	public function is_on_sale() { return $this->get_sale_price() !== ''; }
	public function get_permalink() { return get_permalink( $this->id ); }
	public function get_category_ids() { return wp_get_post_terms( $this->id, 'product_cat', [ 'fields' => 'ids' ] ); }
	public function get_weight() { return ''; }
	public function get_meta( $k, $single = true ) { return $this->m( $k ); }
	public function get_min_purchase_quantity() { return 1; }
	public function get_max_purchase_quantity() { return -1; }
	public function is_purchasable() { return true; }
	public function get_price_html() { return ''; }
}
class WC_DateTime extends DateTime { public function date( $f ) { return gmdate( $f, $this->getTimestamp() ); } public function getTimestamp(): int { return parent::getTimestamp(); } }
function wc_get_product( $p = false ) { $id = $p instanceof WP_Post ? $p->ID : (int) ( $p ?: get_the_ID() ); return get_post_type( $id ) === 'product' ? new WC_Product( $id ) : false; }
function is_woocommerce() { return is_shop() || is_product() || is_product_taxonomy(); }
function is_shop() { return is_post_type_archive( 'product' ) || ( wc_get_page_id( 'shop' ) > 0 && is_page( wc_get_page_id( 'shop' ) ) ); }
function is_product() { return is_singular( 'product' ); }
function is_product_category( $t = '' ) { return is_tax( 'product_cat', $t ); }
function is_product_tag( $t = '' ) { return is_tax( 'product_tag', $t ); }
function is_product_taxonomy() { return is_tax( [ 'product_cat', 'product_tag' ] ); }
function is_cart() { return false; } function is_checkout() { return false; } function is_account_page() { return false; }
function wc_get_page_id( $p ) { return (int) get_option( 'woocommerce_' . $p . '_page_id', -1 ); }
function get_woocommerce_currency() { return 'IRR'; }
function get_woocommerce_currency_symbol( $c = '' ) { return 'ریال'; }
function wc_price( $p, $a = [] ) { return (string) $p; }
function wc_get_price_decimals() { return 0; }
function wc_placeholder_img_src( $s = '' ) { return 'https://hodima.test/placeholder.png'; }
function wc_get_product_terms( $id, $tax, $args = [] ) { return wp_get_post_terms( $id, $tax, $args ); }
function wc_format_localized_decimal( $v ) { return $v; }
function wc_get_loop_prop( $p, $d = '' ) { return $d; }
function wc_get_image_size( $s ) { return [ 'width' => 300, 'height' => 300, 'crop' => 1 ]; }
function wc_clean( $v ) { return is_array( $v ) ? array_map( 'wc_clean', $v ) : sanitize_text_field( $v ); }
function wc_get_related_products( $id, $l = 5, $e = [] ) { return []; }
function wc_get_products( $a = [] ) { return []; }

add_action( 'init', static function () {
	register_post_type( 'product', [ 'public' => true, 'has_archive' => 'shop', 'rewrite' => [ 'slug' => 'product' ], 'supports' => [ 'title', 'editor', 'thumbnail', 'excerpt' ] ] );
	register_taxonomy( 'product_cat', 'product', [ 'public' => true, 'hierarchical' => true, 'rewrite' => [ 'slug' => 'product-category' ] ] );
	register_taxonomy( 'product_tag', 'product', [ 'public' => true, 'rewrite' => [ 'slug' => 'product-tag' ] ] );
}, 0 );
