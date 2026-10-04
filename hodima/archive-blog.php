<?php
/**
 * Template Name: Blog Archive
 * Description: صفحه آرشیو مقالات وبلاگ با اسکرول بی‌نهایت
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// فراخوانی فایل استایل اختصاصی این صفحه
add_action( 'wp_enqueue_scripts', function() {
    // نسخه = زمان تغییر فایل (قبلا ثابت '1.0.0': تغییر CSS در کش مرورگرها دیده نمی‌شد)
    wp_enqueue_style( 'hodima-archive-blog', get_template_directory_uri() . '/assets/css/archive-blog.css', array(), hodima_asset_version( 'assets/css/archive-blog.css' ) );
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
                // همان دو پله اسکیمای بردکرامب (خانه › دسته)
                echo esc_html( single_term_title( '', false ) );
            } else {
                echo esc_html( is_home() ? hodima_blog_name() : wp_strip_all_tags( get_the_archive_title() ) );
            }
        }
        ?>
    </section>

    <section class="blog-page-section">
        <div class="blog-page-container">
            
            <?php if ( is_home() ) : ?>
                <?php
                /*
                 * صفحه اصلی وبلاگ: قبلا هیچ H1 و معرفی‌ای نداشت. عنوان و متن از
                 * «تنظیمات قالب ← وبلاگ» (خالی = عنوان برگه «نوشته‌ها»).
                 */
                $hodima_blog_intro = (string) hodima_setting( 'blog_intro' );
                $hodima_blog_paged = max( 1, (int) get_query_var( 'paged' ) );
                ?>
                <header class="blog-archive-header">
                    <h1 class="blog-archive-title">
                        <?php echo esc_html( hodima_blog_name() ); ?>
                        <?php if ( $hodima_blog_paged > 1 ) : ?>
                            <span class="blog-archive-page">— صفحه <?php echo (int) $hodima_blog_paged; ?></span>
                        <?php endif; ?>
                    </h1>
                    <?php if ( '' !== $hodima_blog_intro && 1 === $hodima_blog_paged ) : ?>
                        <div class="blog-archive-desc"><?php echo wp_kses_post( wpautop( esc_html( $hodima_blog_intro ) ) ); ?></div>
                    <?php endif; ?>
                </header>
            <?php elseif ( is_archive() ) : ?>
                <header class="blog-archive-header">
                    <h1 class="blog-archive-title">
                        <?php 
                        // نام خود دسته/برچسب؛ قبلا همه دسته‌های وبلاگ H1 ثابت «هدهدنما» داشتند
                        // (H1 تکراری بین دسته‌ها و ناهمخوان با عنوان صفحه و اسکیما)
                        if ( is_category() || is_tag() || is_tax() ) {
                            echo esc_html( single_term_title( '', false ) );
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
                
                // فهرست ساختاریافته مقاله‌ها (ItemList) را افزونه Hodima SEO می‌سازد:
                // schema/collection-lists-schema.php (قبلا همین‌جا) — بازسازی قالب، مرحله ۲
                if ( have_posts() ) : ?>
                    <?php while ( have_posts() ) : the_post(); ?>
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

                <?php else : ?>
                    <p class="blog-page-empty">هیچ مقاله‌ای یافت نشد.</p>
                <?php endif; ?>

            </div>

            <!-- لودر اسکرول -->
            <div id="infinite-scroll-loader" style="display:none; text-align:center; padding: 20px; width:100%;">
                <span class="loader-text">در حال بارگذاری...</span>
            </div>

            <?php // صفحه‌بندی: با بارگذاری خودکار پنهان (لینک‌ها برای خزنده‌ها)، بدون آن نمایش داده می‌شود ?>
            <?php if ( $wp_query->max_num_pages > 1 ) : ?>
                <div class="hodima-pagination"<?php echo hodima_setting( 'blog_infinite_scroll' ) ? ' style="display: none;"' : ''; ?>>
                    <?php echo paginate_links( array(
                        'total'     => $wp_query->max_num_pages,
                        'current'   => $paged,
                    ) ); ?>
                </div>
            <?php endif; ?>

        </div>
    </section>
</main>

<?php if ( hodima_setting( 'blog_infinite_scroll' ) ) : // «تنظیمات قالب ← وبلاگ» ?>
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
<?php endif; ?>

<?php get_footer(); ?>
