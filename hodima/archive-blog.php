<?php
/**
 * Template Name: Blog Archive
 * Description: صفحه آرشیو مقالات وبلاگ با اسکرول بی‌نهایت
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// فراخوانی فایل استایل اختصاصی این صفحه
add_action( 'wp_enqueue_scripts', function() {
    wp_enqueue_style( 'hodima-archive-blog', get_template_directory_uri() . '/assets/css/archive-blog.css', array(), '1.0.0' );
});

get_header(); ?>

<main class="hodima-page-wrapper">

    <section class="section-breadcrumb">
        <?php
        if ( function_exists('yoast_breadcrumb') ) {
            yoast_breadcrumb( '<div id="breadcrumbs">', '</div>' );
        } else {
            echo '<a href="' . esc_url( home_url( '/' ) ) . '">خانه</a> / ';
            if ( is_category() || is_tag() || is_tax() ) {
                echo single_term_title( '', false );
            } else {
                echo 'وبلاگ';
            }
        }
        ?>
    </section>

    <section class="blog-page-section">
        <div class="blog-page-container">
            
            <?php if ( is_archive() ) : ?>
                <header class="blog-archive-header">
                    <h1 class="blog-archive-title">
                        <?php 
                        if ( is_category() ) {
                            echo 'هدهدنما';
                        } elseif ( is_tag() || is_tax() ) {
                            echo single_term_title( '', false );
                        } else {
                            echo wp_strip_all_tags( get_the_archive_title() );
                        }
                        ?>
                    </h1>
                    <?php if ( get_the_archive_description() ) : ?>
                        <div class="blog-archive-desc"><?php echo wp_kses_post( wpautop( get_the_archive_description() ) ); ?></div>
                    <?php endif; ?>
                </header>
            <?php endif; ?>

            <div class="blog-page-grid" id="blog-grid">

                <?php 
                global $wp_query;
                $paged = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;
                
                $schema_items = array();
                $position = 1;

                if ( have_posts() ) : ?>
                    <?php while ( have_posts() ) : the_post(); 
                        $thumb_url = get_the_post_thumbnail_url( get_the_ID(), 'full' );
                        
                        $item_data = array(
                            '@type'    => 'ListItem',
                            'position' => $position,
                            'name'     => get_the_title(),
                            'url'      => get_permalink(),
                        );
                        if ( $thumb_url ) {
                            $item_data['image'] = $thumb_url;
                        }
                        
                        $schema_items[] = $item_data;
                        $position++;
                    ?>
                        <a href="<?php the_permalink(); ?>" class="blog-page-card">

                            <?php if ( has_post_thumbnail() ) : ?>
                                <?php the_post_thumbnail( 'medium', array(
                                    'class' => 'blog-page-thumbnail',
                                    'alt'   => get_the_title(),
                                ) ); ?>
                            <?php else : ?>
                                <div class="blog-page-thumbnail blog-page-thumbnail--empty">
                                    <span>بدون تصویر</span>
                                </div>
                            <?php endif; ?>

                            <div class="blog-page-content">
                                <h2 class="blog-page-title" title="<?php echo esc_attr(get_the_title()); ?>">
                                    <?php 
                                        $title = get_the_title();
                                        if (mb_strlen($title, 'UTF-8') > 45) {
                                            echo esc_html( mb_substr($title, 0, 45, 'UTF-8') ) . '...';
                                        } else {
                                            echo esc_html( $title );
                                        }
                                    ?>
                                </h2>
                                <div class="blog-page-meta">
                                    <span class="blog-date"><?php echo get_the_date('j F Y'); ?></span>
                                </div>
                            </div>

                        </a>
                    <?php endwhile; ?>
                    
                    <?php 
                    /*
                     * پایه شناسه از موتور canonical مشترک — همان آدرسی که
                     * homepage-schema.php برای «#webpage» به کار می‌برد.
                     * نسخه قبلی home_url($wp->request) بود که با canonical
                     * دستی یا ساختار پیوند بدون اسلش پایانی یکی نمی‌شد.
                     */
                    global $wp;
                    $current_url = function_exists( 'hodima_get_canonical_url' ) ? hodima_get_canonical_url() : '';
                    if ( '' === $current_url ) {
                        $current_url = trailingslashit( home_url( $wp->request ) );
                    }
                    
                    // استخراج نام برای اسکیما (با تغییر مدنظر شما)
                    $archive_name = 'وبلاگ';
                    if ( is_category() ) {
                        $archive_name = 'بلاگ هدهدنما';
                    } elseif ( is_archive() ) {
                        $archive_name = is_tag() || is_tax() ? single_term_title( '', false ) : wp_strip_all_tags( get_the_archive_title() );
                    }

                    $item_list_schema = array(
                        '@type'            => 'ItemList',
                        '@id'              => $current_url . '#itemlist',
                        'mainEntityOfPage' => array( '@id' => $current_url . '#webpage' ),
                        'name'             => 'آرشیو ' . $archive_name . ( $paged > 1 ? ' - صفحه ' . $paged : '' ),
                        'description'      => 'لیست مقالات و نوشته‌های مرتبط',
                        'itemListElement'  => $schema_items
                    );
                    
                    // گراف واحد صفحه (hodima-core) — در فوتر با بقیه نودها چاپ می‌شود
                    hodima_schema_add( $item_list_schema, 'theme: archive-blog.php' );
                    ?>

                <?php else : ?>
                    <p class="blog-page-empty">هیچ مقاله‌ای یافت نشد.</p>
                <?php endif; ?>

            </div>

            <!-- لودر اسکرول -->
            <div id="infinite-scroll-loader" style="display:none; text-align:center; padding: 20px; width:100%;">
                <span class="loader-text">در حال بارگذاری...</span>
            </div>

            <!-- صفحه‌بندی مخفی -->
            <?php if ( $wp_query->max_num_pages > 1 ) : ?>
                <div class="hodima-pagination" style="display: none;">
                    <?php echo paginate_links( array(
                        'total'     => $wp_query->max_num_pages,
                        'current'   => $paged,
                    ) ); ?>
                </div>
            <?php endif; ?>

        </div>
    </section>
</main>

<script>
document.addEventListener("DOMContentLoaded", function() {
    let nextLink = document.querySelector('.hodima-pagination .next');
    if (!nextLink) return;

    const loader = document.getElementById('infinite-scroll-loader');
    const grid = document.getElementById('blog-grid');
    let isFetching = false;

    const observer = new IntersectionObserver((entries) => {
        if (entries[0].isIntersecting && !isFetching && nextLink) {
            loadNextPage();
        }
    }, { rootMargin: "200px" });

    observer.observe(loader);
    loader.style.display = 'block';

    async function loadNextPage() {
        isFetching = true;
        let url = nextLink.href;
        
        try {
            const response = await fetch(url);
            const text = await response.text();
            const parser = new DOMParser();
            const html = parser.parseFromString(text, 'text/html');
            
            const newItems = html.querySelectorAll('.blog-page-card');
            newItems.forEach(item => {
                grid.appendChild(item);
            });

            const newNextLink = html.querySelector('.hodima-pagination .next');
            if (newNextLink) {
                nextLink.href = newNextLink.href;
            } else {
                nextLink = null;
                loader.style.display = 'none';
                observer.disconnect();
            }
        } catch (error) {
            console.error('Error loading next page:', error);
        }
        
        isFetching = false;
    }
});
</script>

<?php get_footer(); ?>
