<?php
/**
 * Template Name: Single Blog Post
 * File: single-post.php
 * Description: قالب نمایش تکی مقالات وبلاگ در قالب هدهد یکپارچه با سیستم رسانه و باکس‌های بازشونده
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

get_header(); 
?>

<div class="hodima-page-wrapper">
    <?php 
    // شروع حلقه وردپرس برای نمایش محتوای مقاله
    while ( have_posts() ) : the_post(); 
        // آیدی مقاله برای سیستم رسانه (قبلا $post_id: این قالب در فضای سراسری PHP اجرا می‌شود)
        $hodima_post_id = (int) get_the_ID();
    ?>

        <!-- 1. بخش مسیرنما (Breadcrumb): همان مسیر اسکیما (inc/breadcrumb.php) -->
        <section class="hodima-section-box section-breadcrumb">
            <?php echo hodima_breadcrumb_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escape‌شده در inc/breadcrumb.php ?>
        </section>

        <?php
        /*
         * 2. بخش معرفی (تگ H1) + متن معرفی و ویدیو (template-parts/media/intro-media.php).
         * اگر هوک ویدیو چیزی برنگرداند، تصویر شاخص مقاله جای ویدیو.
         */
        $hodima_media_atts   = [ 'id' => $hodima_post_id, 'context' => 'post' ];
        $hodima_intro_output = hodima_theme_media_html( 'intro', $hodima_media_atts ); // ترتیب قبلی: اول معرفی، بعد ویدیو
        $hodima_video_output = hodima_theme_media_html( 'video', $hodima_media_atts );
        if ( '' === $hodima_video_output || ! str_contains( $hodima_video_output, '<' ) ) {
            $hodima_video_output = has_post_thumbnail() ? (string) get_the_post_thumbnail( null, 'large', [ 'class' => 'single-post-image' ] ) : '';
        }
        get_template_part( 'template-parts/media/intro-media', null, [
            'layout' => 'post',
            'title'  => get_the_title(),
            'intro'  => $hodima_intro_output,
            'video'  => $hodima_video_output,
        ] );
        ?>

        <!-- 3. بخش محتوای متنی مقاله -->
        <section class="hodima-section-box section-description">
            <div class="single-post-content">
                <?php 
                // دریافت محتوای اصلی مقاله
                $hodima_post_content = apply_filters( 'the_content', get_the_content() ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- فیلتر خود وردپرس

                // قرار دادن محتوا در آغوش شورت‌کد نمایش بیشتر
                if ( shortcode_exists('expand_blog_content') ) {
                    echo do_shortcode( '[expand_blog_content height="400"]' . $hodima_post_content . '[/expand_blog_content]' );
                } else {
                    echo $hodima_post_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- متن مقاله (the_content)
                }
                ?>
            </div>
        </section>

        <?php
        // 4. پادکست و سوالات متداول؛ بدون افزونه Hodima Media (یا بدون محتوا) کل بخش نمایش داده نمی‌شود
        get_template_part( 'template-parts/media/voice-faq', null, [
            'layout' => 'post',
            'voice'  => hodima_theme_media_html( 'voice', $hodima_media_atts ),
            'faq'    => hodima_theme_media_html( 'faq', $hodima_media_atts ),
        ] );
        ?>

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
            $hodima_post_id,
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