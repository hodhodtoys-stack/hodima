<?php
/**
 * Expandable Boxes — «نمایش بیشتر / کمتر»
 * Path: components/expandable-boxes/expandable-boxes.php
 * Version: 3.0.0 (PHP 8.4)
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class Arian_Expandable_Boxes {

    private const POST_META_KEY = '_has_arian_expandable';
    private const VERSION       = '3.0.0';

    private static ?self $instance = null;
    private static array $active_render_stack = [];

    private array $shortcodes = [
        'product_description',
        'category_description',
        'product_reviews',
        'blog_content',
        'blog_comments',
    ];

    public static function init(): self {
        return self::$instance ??= new self();
    }

    private function __construct() {
        foreach ( $this->shortcodes as $type ) {
            add_shortcode( "expand_{$type}", fn( $atts, $content = null ) => $this->{"render_{$type}"}( $atts, $content ) );
        }

        add_shortcode( 'product_reviews_only', [ $this, 'render_reviews_only' ] );

        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'save_post', [ $this, 'update_post_meta_on_save' ], 10, 2 );
        add_action( 'before_delete_post', fn( int $post_id ) => delete_post_meta( $post_id, self::POST_META_KEY ) );
    }

    public static function wrap( string $rendered_html, string $context = '', array $atts = [] ): string {
        $self = self::init();
        return $self->render_box( $rendered_html, $self->parse_atts( $atts, "expand_{$context}" ), $context );
    }

    public function update_post_meta_on_save( int $post_id, WP_Post $post ): void {
        if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) || ! $this->supports_post_type( $post->post_type ) ) {
            return;
        }

        $this->content_has_expandable_shortcode( (string) $post->post_content )
            ? update_post_meta( $post_id, self::POST_META_KEY, '1' )
            : delete_post_meta( $post_id, self::POST_META_KEY );
    }

    public function enqueue_assets(): void {
        if ( ! $this->should_enqueue_assets() ) {
            return;
        }

        $base = '/components/expandable-boxes/';

        wp_enqueue_style( 'arian-expandable-style', HODIMA_MEDIA_URL . '/' . ltrim( $base . 'expandable-boxes.css', '/' ), [], $this->asset_version( $base . 'expandable-boxes.css' ) );
        wp_enqueue_script( 'arian-expandable-script', HODIMA_MEDIA_URL . '/' . ltrim( $base . 'expandable-boxes.js', '/' ), [], $this->asset_version( $base . 'expandable-boxes.js' ), true );

        wp_localize_script( 'arian-expandable-script', 'ArianExpandableBoxesData', [
            'transitionMs'        => 600,
            'defaultMoreText'     => 'نمایش بیشتر',
            'defaultLessText'     => 'نمایش کمتر',
            'defaultScrollOffset' => 120,
            'hashExpand'          => true,
            'selectors'           => [
                'container' => '[data-expandable-container]',
                'content'   => '[data-expandable-content]',
                'toggle'    => '[data-expandable-toggle]',
                'inner'     => '.arian-expandable-inner',
            ],
        ] );
    }

    private function asset_version( string $relative ): string {
        $path = HODIMA_MEDIA_DIR . '/' . ltrim( $relative, '/' );
        return file_exists( $path ) ? (string) filemtime( $path ) : self::VERSION;
    }

    private function supports_post_type( string $post_type ): bool {
        $supported = (array) apply_filters( 'arian_expandable_boxes_supported_post_types', [ 'post', 'page', 'product' ] );
        return in_array( $post_type, $supported, true );
    }

    private function content_has_expandable_shortcode( string $content ): bool {
        foreach ( $this->shortcodes as $type ) {
            if ( has_shortcode( $content, "expand_{$type}" ) ) return true;
        }
        return has_shortcode( $content, 'product_reviews_only' );
    }

    private function should_enqueue_assets(): bool {
        if ( is_admin() ) return false;

        if ( is_singular( 'post' ) || ( function_exists( 'is_product' ) && is_product() ) ) return true;
        if ( is_tax( 'product_cat' ) || is_category() ) return true;

        if ( is_singular() ) {
            $post_id = (int) get_queried_object_id();
            if ( $post_id && get_post_meta( $post_id, self::POST_META_KEY, true ) ) return true;
            
            $post = get_post( $post_id );
            return $post instanceof WP_Post && $this->content_has_expandable_shortcode( (string) $post->post_content );
        }

        return false;
    }

    private function get_default_atts( string $shortcode_tag ): array {
        // تعیین ارتفاع پویا بر اساس نوع محتوا (۶۰۰ برای محتوا، ۳۵۰ برای نظرات)
        $dynamic_height = match ( $shortcode_tag ) {
            'expand_product_reviews', 'expand_blog_comments' => 350,
            default => 600,
        };

        return (array) apply_filters( "arian_expandable_boxes_defaults_{$shortcode_tag}", [
            'height'        => $dynamic_height,
            'more'          => 'نمایش بیشتر',
            'less'          => 'نمایش کمتر',
            'scroll_offset' => 120,
            'class'         => '',
            'gradient'      => 'yes',
            'bg'            => '',
            'fade_height'   => 80,
            'smart'         => 'yes',
        ] );
    }

    private function parse_atts( array|string $atts, string $shortcode_tag ): array {
        $atts = shortcode_atts( $this->get_default_atts( $shortcode_tag ), (array) $atts, $shortcode_tag );

        $atts['height']        = max( 100, absint( $atts['height'] ) );
        $atts['scroll_offset'] = max( 0, absint( $atts['scroll_offset'] ) );
        $atts['fade_height']   = min( max( 0, absint( $atts['fade_height'] ) ), $atts['height'] );
        $atts['more']          = sanitize_text_field( (string) $atts['more'] );
        $atts['less']          = sanitize_text_field( (string) $atts['less'] );
        $atts['class']         = $this->sanitize_html_classes( (string) $atts['class'] );
        $atts['gradient']      = match( strtolower( trim( (string) $atts['gradient'] ) ) ) { 'yes', 'true', '1', 'on' => 'yes', default => 'no' };
        $atts['smart']         = match( strtolower( trim( (string) $atts['smart'] ) ) ) { 'yes', 'true', '1', 'on' => 'yes', default => 'no' };
        $atts['bg']            = $this->sanitize_css_color( (string) $atts['bg'] );

        return $atts;
    }

    private function sanitize_html_classes( string $classes ): string {
        $parts = preg_split( '/\s+/', trim( $classes ) ) ?: [];
        return implode( ' ', array_unique( array_filter( array_map( 'sanitize_html_class', $parts ) ) ) );
    }

    private function sanitize_css_color( string $value ): string {
        $value = trim( wp_strip_all_tags( $value ) );
        return (bool) preg_match( '/^(#[0-9a-fA-F]{3,8}|rgba?\([\d\s.,%\/]+\)|hsla?\([\d\s.,%\/deg]+\)|var\(--[a-zA-Z0-9_-]+\)|[a-zA-Z]{3,20})$/', $value ) ? $value : '';
    }

    private function has_meaningful_content( string $content ): bool {
        $content = trim( $content );
        if ( $content === '' ) return false;

        return trim( wp_strip_all_tags( $content ) ) !== '' 
            || (bool) preg_match( '/<(img|video|iframe|audio|picture|canvas|svg|object|table)\b/i', $content );
    }

    private function build_wrapper_classes( array $atts ): string {
        $classes = [ 'arian-expandable-box' ];
        if ( $atts['class'] !== '' ) $classes[] = $atts['class'];
        if ( $atts['gradient'] === 'no' ) $classes[] = 'arian-no-gradient';
        if ( $atts['smart'] === 'yes' ) $classes[] = 'arian-smart-mode';

        return $this->sanitize_html_classes( implode( ' ', $classes ) );
    }

    private function build_inline_css_vars( array $atts ): string {
        $styles = [
            '--arian-collapsed-height:' . (int) $atts['height'] . 'px',
            '--arian-fade-height:' . (int) $atts['fade_height'] . 'px',
        ];
        if ( $atts['bg'] !== '' ) $styles[] = '--arian-bg:' . $atts['bg'];

        return implode( ';', $styles );
    }

    private function maybe_kses( string $content, string $context ): string {
        return apply_filters( 'arian_expandable_boxes_kses_output', false, $context )
            ? wp_kses_post( $content )
            : $content;
    }

    private function with_render_guard( string $key, callable $callback ): string {
        if ( isset( self::$active_render_stack[ $key ] ) ) return '';
        
        self::$active_render_stack[ $key ] = true;
        try {
            return (string) $callback();
        } finally {
            unset( self::$active_render_stack[ $key ] );
        }
    }

    private function render_box( string $content, array $atts, string $context = '' ): string {
        $content = $this->maybe_kses( $content, $context );
        if ( ! $this->has_meaningful_content( $content ) ) return '';

        $content_id = wp_unique_id( 'arian-box-' );
        $button_id  = wp_unique_id( 'arian-box-toggle-' );

        return sprintf(
            '<div class="%1$s" data-expandable-container data-context="%2$s" style="%3$s">'
                . '<div id="%4$s" class="arian-expandable-wrapper" data-expandable-content data-height="%5$d" data-more="%6$s" data-less="%7$s" data-scroll-offset="%8$d" data-smart="%9$s" tabindex="-1" role="region" aria-labelledby="%11$s">'
                    . '<div class="arian-expandable-inner">%10$s</div>'
                . '</div>'
                . '<button id="%11$s" type="button" class="arian-expand-toggle-btn" data-expandable-toggle aria-controls="%4$s" aria-expanded="false"><span class="arian-btn-text">%12$s</span></button>'
            . '</div>',
            esc_attr( $this->build_wrapper_classes( $atts ) ),
            esc_attr( $context ),
            esc_attr( $this->build_inline_css_vars( $atts ) ),
            esc_attr( $content_id ),
            (int) $atts['height'],
            esc_attr( $atts['more'] ),
            esc_attr( $atts['less'] ),
            (int) $atts['scroll_offset'],
            esc_attr( $atts['smart'] ),
            $content,
            esc_attr( $button_id ),
            esc_html( $atts['more'] )
        );
    }

    private function enclosed( string $content ): string {
        return do_shortcode( $content );
    }

    private function render_source( string $raw ): string {
        return (string) apply_filters( 'the_content', $raw );
    }

    private function render_comments_box( array $atts, string $context ): string {
        return $this->with_render_guard( 'comments_template', function () use ( $atts, $context ): string {
            ob_start();
            comments_template();
            return $this->render_box( (string) ob_get_clean(), $atts, $context );
        } );
    }

    public function render_product_description( $atts, $content = null ): string {
        if ( ! function_exists( 'is_product' ) || ! is_product() || ! function_exists( 'wc_get_product' ) ) return '';

        $atts = $this->parse_atts( $atts ?? [], 'expand_product_description' );

        return $this->with_render_guard( 'product_description', function () use ( $atts, $content ): string {
            if ( $content !== null && trim( (string) $content ) !== '' ) {
                $html = $this->enclosed( (string) $content );
            } else {
                $product = wc_get_product( (int) get_queried_object_id() );
                $html    = $product ? $this->render_source( (string) $product->get_description() ) : '';
            }
            return $this->render_box( $html, $atts, 'product_description' );
        } );
    }

    public function render_category_description( $atts, $content = null ): string {
        if ( ! is_tax( 'product_cat' ) && ! is_category() ) return '';

        $atts = $this->parse_atts( $atts ?? [], 'expand_category_description' );

        return $this->with_render_guard( 'category_description', function () use ( $atts, $content ): string {
            if ( $content !== null && trim( (string) $content ) !== '' ) {
                $html = $this->enclosed( (string) $content );
            } else {
                $term = get_queried_object();
                $html = $term instanceof WP_Term ? (string) term_description( $term ) : '';
            }
            return $this->render_box( $html, $atts, 'category_description' );
        } );
    }

    public function render_product_reviews( $atts ): string {
        if ( ! function_exists( 'is_product' ) || ! is_product() ) return '';
        return $this->render_comments_box( $this->parse_atts( $atts ?? [], 'expand_product_reviews' ), 'product_reviews' );
    }

    public function render_reviews_only( $atts = [] ): string {
        if ( ! function_exists( 'is_product' ) || ! is_product() ) return '';

        $atts  = shortcode_atts( [ 'class' => '' ], (array) $atts, 'product_reviews_only' );
        $class = $this->sanitize_html_classes( (string) $atts['class'] );

        return $this->with_render_guard( 'comments_template', static function () use ( $class ): string {
            ob_start();
            comments_template();
            return '<div class="arian-product-reviews-wrapper ' . esc_attr( $class ) . '">' . ob_get_clean() . '</div>';
        } );
    }

    public function render_blog_content( $atts, $content = null ): string {
        if ( ! is_singular( 'post' ) ) return '';

        $atts = $this->parse_atts( $atts ?? [], 'expand_blog_content' );

        return $this->with_render_guard( 'blog_content', function () use ( $atts, $content ): string {
            if ( $content !== null && trim( (string) $content ) !== '' ) {
                $html = $this->enclosed( (string) $content );
            } else {
                $post = get_post( (int) get_queried_object_id() );
                $html = $post instanceof WP_Post ? $this->render_source( (string) $post->post_content ) : '';
            }
            return $this->render_box( $html, $atts, 'blog_content' );
        } );
    }

    public function render_blog_comments( $atts ): string {
        if ( ! is_singular( 'post' ) ) return '';
        return $this->render_comments_box( $this->parse_atts( $atts ?? [], 'expand_blog_comments' ), 'blog_comments' );
    }
}

Arian_Expandable_Boxes::init();

function hodima_expandable_box( string $rendered_html, string $context = '', array $atts = [] ): string {
    return Arian_Expandable_Boxes::wrap( $rendered_html, $context, $atts );
}