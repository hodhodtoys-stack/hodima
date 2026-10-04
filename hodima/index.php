<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * فایل پایه قالب hodima — آخرین فالبک سلسله‌مراتب قالب وردپرس.
 *
 * بیشتر صفحه‌ها قالب اختصاصی دارند (page.php، single-post.php، search.php،
 * archive-blog.php، قالب‌های ووکامرس، front-page.php). اینجا می‌ماند: صفحه
 * اصلی بدون چیدمان «تنظیمات قالب ← صفحه اصلی» (H1 از شورت‌کدهای متن برگه)،
 * و انواع نوشته سفارشی/آرشیوهای بدون قالب.
 *
 * بازسازی قالب، مرحله ۵: استایل‌های inline به style.css رفت؛ در فهرست‌ها
 * عنوان هر مورد h2 (قبلا هر مورد H1 بود) و خود فهرست یک H1.
 *
 * @package hodima
 */

get_header(); ?>

<main id="primary" class="site-main">
    <div class="hodima-container hodima-page__container">
        <?php if ( have_posts() ) : ?>

            <?php if ( ! is_singular() ) : ?>
                <header class="entry-header">
                    <h1 class="entry-title"><?php echo esc_html( wp_strip_all_tags( get_the_archive_title() ) ); ?></h1>
                </header>
            <?php endif; ?>

            <?php while ( have_posts() ) : the_post(); ?>
                <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
                    <?php
                    // برگه‌ها (صفحه اصلی) H1 خود را از متن برگه (شورت‌کد بخش معرفی) می‌گیرند
                    if ( is_singular() && ! is_page() ) :
                        ?>
                        <header class="entry-header">
                            <?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
                        </header>
                    <?php elseif ( ! is_singular() ) : ?>
                        <header class="entry-header">
                            <?php the_title( '<h2 class="entry-title entry-title--list"><a href="' . esc_url( get_permalink() ) . '">', '</a></h2>' ); ?>
                        </header>
                    <?php endif; ?>

                    <div class="entry-content">
                        <?php is_singular() ? the_content() : the_excerpt(); ?>
                    </div>
                </article>
            <?php endwhile; ?>

            <?php
            the_posts_navigation( [
                'prev_text' => '« نوشته‌های قبلی',
                'next_text' => 'نوشته‌های بعدی »',
            ] );
            ?>

        <?php else : ?>
            <div class="hodima-empty">
                <h1 class="hodima-empty__title">محتوایی یافت نشد!</h1>
                <p>متأسفانه چیزی برای نمایش در این صفحه وجود ندارد.</p>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php get_footer(); ?>
