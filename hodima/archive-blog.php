<?php
/**
 * Template Name: Blog Archive
 * Description: صفحه آرشیو مقالات وبلاگ با اسکرول بی‌نهایت
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) exit;

// فراخوانی فایل استایل اختصاصی این صفحه
add_action( 'wp_enqueue_scripts', static function (): void {
    hodima_enqueue_asset( 'hodima-archive-blog', 'assets/css/archive-blog.css' );

    // بارگذاری خودکار با اسکرول (قبلا اسکریپت inline پایین همین فایل)
    if ( hodima_setting( 'blog_infinite_scroll' ) ) {
        hodima_enqueue_asset( 'hodima-archive-blog', 'assets/js/archive-blog.js', [], [ 'in_footer' => true, 'strategy' => 'defer' ] );
    }
} );

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
                            echo esc_html( wp_strip_all_tags( get_the_archive_title() ) );
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
                // قبلا $paged: متغیر سراسری خود وردپرس را بازنویسی می‌کرد (این قالب در فضای سراسری اجرا می‌شود)
                $hodima_paged = max( 1, (int) get_query_var( 'paged' ) );
                
                // فهرست ساختاریافته مقاله‌ها (ItemList) را افزونه Hodima SEO می‌سازد:
                // schema/collection-lists-schema.php (قبلا همین‌جا) — بازسازی قالب، مرحله ۲
                if ( have_posts() ) : ?>
                    <?php while ( have_posts() ) : the_post(); ?>
                        <a href="<?php the_permalink(); ?>" class="blog-page-card">

                            <?php if ( has_post_thumbnail() ) : ?>
                                <?php the_post_thumbnail( 'medium', [
                                    'class' => 'blog-page-thumbnail',
                                    'alt'   => get_the_title(),
                                ] ); ?>
                            <?php else : ?>
                                <div class="blog-page-thumbnail blog-page-thumbnail--empty">
                                    <span>بدون تصویر</span>
                                </div>
                            <?php endif; ?>

                            <div class="blog-page-content">
                                <?php // عنوان کامل؛ دو خط را CSS می‌بُرد (تا 2.9.9 بریدن ۴۵ حرفی PHP عنوان کوتاه دوخطی را هم می‌برید) ?>
                                <h2 class="blog-page-title"><?php echo esc_html( get_the_title() ); ?></h2>
                                <?php if ( hodima_setting( 'blog_card_excerpt' ) ) : ?>
                                    <p class="blog-page-excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 22 ) ); ?></p>
                                <?php endif; ?>
                                <div class="blog-page-meta">
                                    <span class="blog-date"><?php echo esc_html( (string) get_the_date( 'j F Y' ) ); ?></span>
                                    <?php if ( hodima_setting( 'blog_meta_reading' ) ) : ?>
                                        <span class="blog-reading"><?php echo esc_html( hodima_reading_label( (int) get_the_ID() ) ); ?></span>
                                    <?php endif; ?>
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
                    <?php
                    echo paginate_links( [ // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML ساخته‌شده خود وردپرس
                        'total'   => $wp_query->max_num_pages,
                        'current' => $hodima_paged,
                    ] );
                    ?>
                </div>
            <?php endif; ?>

        </div>
    </section>
</main>


<?php get_footer(); ?>
