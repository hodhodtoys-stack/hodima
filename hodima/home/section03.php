<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// کلید با فانکشن مچ شد و روی v4 تنظیم شد تا کش قبلی کاملا بای‌پس شود
$transient_key = 'arian_categories_hyper_v4'; 
$categories_data = get_transient( $transient_key );

if ( false === $categories_data ) {
    
    $excluded_slugs = ['latest-products', 'equipments', 'plasco', 'rhinestones', 'toys', 'shanci'];
    $excluded_terms = get_terms([
        'taxonomy' => 'product_cat',
        'slug'     => $excluded_slugs,
        'fields'   => 'ids',
    ]);
    $excluded_ids = is_wp_error($excluded_terms) ? [] : $excluded_terms;

    $product_terms = get_terms([
        'taxonomy'               => 'product_cat',
        'hide_empty'             => true,
        'exclude'                => $excluded_ids,
        'update_term_meta_cache' => true,
    ]);

    $categories_data = [];

    if ( ! empty( $product_terms ) && ! is_wp_error( $product_terms ) ) {
        foreach ( $product_terms as $term ) {
            $thumbnail_id = get_term_meta( $term->term_id, 'thumbnail_id', true );
            $categories_data[] = [
                'name'         => $term->name,
                'link'         => get_term_link( $term ),
                'thumbnail_id' => $thumbnail_id,
            ];
        }
    }
    
    // کش کردن برای یک هفته
    set_transient( $transient_key, $categories_data, WEEK_IN_SECONDS );
}

if ( empty( $categories_data ) ) return;
?>

<!-- خروجی HTML -->
<section class="arian-section cat-story-container" aria-labelledby="arian-cat-title" data-nosnippet>
    <header class="arian-header">
        <div class="arian-title-group">
            <h2 id="arian-cat-title" class="arian-title">دسته‌بندی کالاها</h2>
            <div class="arian-line"></div>
        </div>
    </header>
    <div class="arian-scroller cat-story-wrapper">
        <?php foreach ( $categories_data as $cat ): ?>
            <a href="<?php echo esc_url( $cat['link'] ); ?>" class="cat-story-item" aria-label="<?php echo esc_attr( $cat['name'] ); ?>">
                <div class="cat-img-box">
                    <?php
                    if ( ! empty( $cat['thumbnail_id'] ) ) {
                        echo wp_get_attachment_image( $cat['thumbnail_id'], 'woocommerce_thumbnail', false, [
                            'alt' => esc_attr( $cat['name'] ), 'loading' => 'lazy', 'decoding' => 'async'
                        ]);
                    } else {
                        echo '<img src="' . esc_url( wc_placeholder_img_src() ) . '" alt="' . esc_attr( $cat['name'] ) . '" width="150" height="150" loading="lazy" decoding="async">';
                    }
                    ?>
                </div>
                <span class="cat-story-name"><?php echo esc_html( $cat['name'] ); ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</section>