<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * فایل اصلی و پایه قالب hodima
 * وردپرس برای شناختن و اجرای قالب به این فایل به عنوان نقطه شروع نیاز دارد.
 *
 * @package hodima
 */

// فراخوانی هدر سایت
get_header(); ?>

<main id="primary" class="site-main">
    <div class="hodima-container" style="padding-top: 40px; padding-bottom: 40px; min-height: 50vh;">
        <?php
        // حلقه اصلی وردپرس برای نمایش محتوای صفحات یا نوشته‌ها
        if ( have_posts() ) :
            while ( have_posts() ) :
                the_post();
                ?>
                <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
                    
                    <?php 
                    // 🚀 فقط برای نوشته‌های وبلاگ و سایر بخش‌ها H1 را اینجا چاپ می‌کنیم.
                    // برگه‌ها (مثل صفحه اصلی) H1 خود را از فایل‌های سکشن مثل section01.php می‌گیرند.
                    if ( ! is_page() ) : 
                    ?>
                        <header class="entry-header" style="margin-bottom: 20px;">
                            <?php the_title( '<h1 class="entry-title" style="font-size: 2rem; font-weight: bold; color: var(--hodima-text-dark); margin-bottom: 15px;">', '</h1>' ); ?>
                        </header>
                    <?php endif; ?>

                    <div class="entry-content" style="color: var(--hodima-text-dark);">
                        <?php the_content(); ?>
                    </div>
                </article>
                <?php
            endwhile;

            // نمایش دکمه‌های صفحه‌بندی در صورت نیاز
            the_posts_navigation( array(
                'prev_text' => '« نوشته‌های قبلی',
                'next_text' => 'نوشته‌های بعدی »',
            ) );

        else :
            // پیامی در صورت پیدا نشدن محتوا
            echo '<div style="text-align:center; padding: 50px 0;">';
            echo '<h2 style="font-size: 1.5rem; margin-bottom: 15px; color: var(--hodima-primary);">محتوایی یافت نشد!</h2>';
            echo '<p>متأسفانه چیزی برای نمایش در این صفحه وجود ندارد.</p>';
            echo '</div>';
        endif;
        ?>
    </div>
</main>

<?php 
// فراخوانی فوتر سایت
get_footer(); 
?>
