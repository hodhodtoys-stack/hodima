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

    // بارگذاری خودکار با اسکرول (قبلا اسکریپت inline پایین همین فایل)
    if ( hodima_setting( 'blog_infinite_scroll' ) ) {
        wp_enqueue_script( 'hodima-archive-blog', get_template_directory_uri() . '/assets/js/archive-blog.js', array(), hodima_asset_version( 'assets/js/archive-blog.js' ), array( 'in_footer' => true, 'strategy' => 'defer' ) );
    }
});

get_header(); ?>

<main class="hodima-page-wrapper">

    <section class="section-breadcrumb">
        <?php echo hodima_breadcrumb_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escape‌شده در inc/breadcrumb.php (همان مسیر اسکیما) ?>
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
            <div id="infinite-scroll-loader" hidden>
                <span class="loader-text">در حال بارگذاری...</span>
            </div>

            <?php
            /*
             * صفحه‌بندی: با بارگذاری خودکار پنهان (لینک‌ها برای خزنده‌ها)، بدون آن نمایش داده می‌شود.
             * قبلا style="display:none" ثابت بود: اگر جاوااسکریپت اجرا نمی‌شد (خطا، مسدود)
             * بازدیدکننده هیچ راهی به صفحه‌های بعد نداشت. حالا فقط وقتی اسکریپت فعال است
             * (CSS: @media (scripting: enabled)، archive-blog.css).
             */
            ?>
            <?php if ( $wp_query->max_num_pages > 1 ) : ?>
                <div class="hodima-pagination<?php echo hodima_setting( 'blog_infinite_scroll' ) ? ' hodima-pagination--auto' : ''; ?>">
                    <?php echo paginate_links( array(
                        'total'     => $wp_query->max_num_pages,
                        'current'   => $paged,
                    ) ); ?>
                </div>
            <?php endif; ?>

        </div>
    </section>
</main>


<?php get_footer(); ?>
