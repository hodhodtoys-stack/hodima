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

        <!-- 1. بخش مسیرنما (Breadcrumb): همان مسیر اسکیما (inc/breadcrumb.php) -->
        <section class="hodima-section-box section-breadcrumb">
            <?php echo hodima_breadcrumb_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escape‌شده در inc/breadcrumb.php ?>
        </section>

<!-- 2. بخش معرفی (تگ H1) + متن معرفی و ویدیو -->
        <section class="hodima-section-box section-intro-media">
            <h1 class="single-post-title"><?php the_title(); ?></h1>
            
            <div class="video-thumbnail-wrapper single-post-media">
                
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

        <?php
        /*
         * 6. مقالات مرتبط — «تنظیمات قالب ← وبلاگ» (عنوان، تعداد، هم‌دسته/هم‌خوشه).
         * بدون مقاله مرتبط بخش چاپ نمی‌شود (قبلا عنوان + «مقاله مرتبطی یافت نشد»
         * یا برای مقاله بی‌دسته فقط عنوان خالی).
         */
        $hodima_related = hodima_related_post_ids(
            (int) $post_id,
            max( 0, (int) hodima_setting( 'blog_related_limit' ) ),
            (string) hodima_setting( 'blog_related_source' )
        );
        ?>
        <?php if ( $hodima_related ) : ?>
        <section class="hodima-section-box section-upsells">
            <h3 class="upsells-title"><?php echo esc_html( (string) hodima_setting( 'blog_related_title' ) ); ?></h3>
            <div class="related-posts-grid">
                <?php foreach ( $hodima_related as $hodima_related_id ) : ?>
                    <div class="related-post-card">
                        <a href="<?php echo esc_url( get_permalink( $hodima_related_id ) ); ?>">
                            <?php echo get_the_post_thumbnail( $hodima_related_id, 'medium' ); ?>
                        </a>
                        <h4 class="related-post-title">
                            <a href="<?php echo esc_url( get_permalink( $hodima_related_id ) ); ?>"><?php echo esc_html( get_the_title( $hodima_related_id ) ); ?></a>
                        </h4>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

    <?php endwhile; // پایان حلقه وردپرس ?>
</div>

<?php get_footer(); ?>