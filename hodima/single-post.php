<?php
/**
 * Template Name: Single Blog Post
 * File: single-post.php
 * Description: قالب نمایش تکی مقالات وبلاگ در قالب هدهد یکپارچه با سیستم رسانه و باکس‌های بازشونده
 */

defined( 'ABSPATH' ) || exit;

get_header(); 
?>

<div class="hodima-page-wrapper">
    <?php 
    // شروع حلقه وردپرس برای نمایش محتوای مقاله
    while ( have_posts() ) : the_post(); 
        $post_id = get_the_ID(); // دریافت آیدی مقاله فعلی برای سیستم رسانه
    ?>

        <!-- 1. بخش مسیرنما (Breadcrumb) -->
        <section class="hodima-section-box section-breadcrumb">
            <?php 
                if ( function_exists('yoast_breadcrumb') ) {
                    yoast_breadcrumb( '<div id="breadcrumbs">','</div>' );
                } else {
                    // داینامیک و قابل کلیک کردن مسیرنما
                    $home_url = home_url('/');
                    
                    // پیدا کردن لینک برگه وبلاگ (اگر در تنظیمات وردپرس ست شده باشد)
                    $blog_page_id = get_option('page_for_posts');
                    if ($blog_page_id) {
                        $blog_url = get_permalink($blog_page_id);
                    } else {
                        // اگر برگه‌ای اختصاص داده نشده بود، به دسته‌بندی اولِ همین مقاله لینک می‌دهیم
                        $categories = get_the_category();
                        $blog_url = !empty($categories) ? get_category_link($categories[0]->term_id) : home_url('/blog/');
                    }
                    
                    echo '<p class="hodima-custom-breadcrumb">';
                    echo '<a href="' . esc_url($home_url) . '" style="text-decoration:none; color:inherit;">خانه</a> / ';
                    echo '<a href="' . esc_url($blog_url) . '" style="text-decoration:none; color:inherit;">وبلاگ</a> &gt; ';
                    echo '<span class="current-item" style="color:#888;">' . get_the_title() . '</span>';
                    echo '</p>';
                }
            ?>
        </section>

<!-- 2. بخش معرفی (تگ H1) + متن معرفی و ویدیو -->
        <section class="hodima-section-box section-intro-media">
            <h1 class="single-post-title"><?php the_title(); ?></h1>
            
            <div class="video-thumbnail-wrapper" style="margin-top: 20px;">
                
                <!-- ستون اول (سمت راست): هوک متن معرفی -->
                <div class="intro-content">
                    <?php echo hodima_shortcode( 'hook_intro', [ 'id' => $post_id, 'context' => 'post' ] ); // خروجی افزونه Hodima Media ?>
                </div>

                <!-- ستون دوم (سمت چپ): ویدیو یا تصویر شاخص -->
                <div class="video-content">
                    <?php 
                    $video_output = hodima_shortcode( 'hook_video', [ 'id' => $post_id, 'context' => 'post' ] );
                    // اگر هوک ویدیو چیزی برگرداند، آن را نمایش بده، در غیر این صورت تصویر شاخص
                    if ( ! empty( trim( $video_output ) ) && strpos( $video_output, '<' ) !== false ) {
                        echo $video_output;
                    } elseif ( has_post_thumbnail() ) {
                        the_post_thumbnail('large', array('class' => 'single-post-image'));
                    }
                    ?>
                </div>

            </div>
        </section>

        <!-- 3. بخش محتوای متنی مقاله -->
        <section class="hodima-section-box section-description">
            <div class="single-post-content">
                <?php 
                // دریافت محتوای اصلی مقاله
                $post_content = apply_filters('the_content', get_the_content());

                // قرار دادن محتوا در آغوش شورت‌کد نمایش بیشتر
                if ( shortcode_exists('expand_blog_content') ) {
                    echo do_shortcode('[expand_blog_content height="400"]' . $post_content . '[/expand_blog_content]');
                } else {
                    echo $post_content;
                }
                ?>
            </div>
        </section>

        <?php
        // بدون افزونه Hodima Media (یا بدون محتوا) کل بخش نمایش داده نمی‌شود
        $hodima_voice = hodima_shortcode( 'hook_voice', [ 'id' => $post_id, 'context' => 'post' ] );
        $hodima_faq   = hodima_shortcode( 'hook_faq', [ 'id' => $post_id, 'context' => 'post' ] );
        ?>
        <?php if ( '' !== $hodima_voice || '' !== $hodima_faq ) : ?>
        <!-- 4. بخش پادکست و سوالات متداول (FAQ) -->
        <section class="hodima-section-box section-voice-faq">
            <div class="voice-faq-wrapper">
                <?php if ( '' !== $hodima_voice ) : ?>
                <!-- بخش پادکست -->
                <div class="voice-content">
                    <?php echo $hodima_voice; // خروجی شورت‌کد افزونه ?>
                </div>
                <?php endif; ?>

                <?php if ( '' !== $hodima_faq ) : ?>
                <!-- بخش سوالات متداول (FAQ) -->
                <div class="faq-content">
                    <?php echo $hodima_faq; // خروجی شورت‌کد افزونه ?>
                </div>
                <?php endif; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- 5. بخش دیدگاه‌ها (کاملا مجزا) -->
        <section class="hodima-section-box section-comments">
            <div class="comments-wrapper">
                <?php 
                if ( comments_open() || get_comments_number() ) :
                    if ( shortcode_exists('expand_blog_comments') ) {
                        // شورت‌کد نظرات نیازی به محتوا دهی بین دو تگ ندارد
                        // خودش وظیفه فراخوانی کامنت‌ها را بر عهده دارد
                        echo do_shortcode('[expand_blog_comments height="500"]');
                    } else {
                        comments_template();
                    }
                endif;
                ?>
            </div>
        </section>

        <!-- 6. بخش مقالات مرتبط (گرید 4 ستونه) -->
        <section class="hodima-section-box section-upsells">
            <h3 class="upsells-title">مقالات مرتبط</h3>
            <?php
            $categories = get_the_category($post_id);
            if ($categories) {
                $category_ids = array();
                foreach($categories as $individual_category) {
                    $category_ids[] = $individual_category->term_id;
                }
                
                $args = array(
                    'category__in'        => $category_ids,
                    'post__not_in'        => array($post_id),
                    'posts_per_page'      => 4,
                    'ignore_sticky_posts' => 1
                );
                
                $related_query = new WP_Query( $args );
                
                if( $related_query->have_posts() ) {
                    echo '<div class="related-posts-grid">';
                    while( $related_query->have_posts() ) {
                        $related_query->the_post();
                        ?>
                        <div class="related-post-card">
                            <a href="<?php the_permalink(); ?>">
                                <?php 
                                if(has_post_thumbnail()) {
                                    the_post_thumbnail('medium'); 
                                }
                                ?>
                            </a>
                            <h4 class="related-post-title">
                                <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                            </h4>
                        </div>
                        <?php
                    }
                    echo '</div>';
                } else {
                    echo '<p>مقاله مرتبطی یافت نشد.</p>';
                }
                
                wp_reset_postdata(); 
            }
            ?>
        </section>

>>>

    <?php endwhile; // پایان حلقه وردپرس ?>
</div>

<?php get_footer(); ?>