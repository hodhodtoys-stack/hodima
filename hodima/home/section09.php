<?php
/**
 * Blog Section – Hyper Performance Version (اصلاح‌شده برای تصاویر واکنش‌گرا)
 * فایل: section9.php
 * - کش کردن شناسه تصویر (thumbnail_id) به جای URL برای پشتیبانی از srcset و WebP.
 * - استفاده از wp_get_attachment_image برای رندر بهینه تصویر.
 */
if ( ! defined('ABSPATH') ) exit;

// لینک صفحه بلاگ: برگه «نوشته‌ها» در «تنظیمات ← خواندن» (قبلا آدرس ثابت دامنه اصلی)
$blog_page_id = (int) get_option( 'page_for_posts' );
$blog_url     = $blog_page_id ? (string) get_permalink( $blog_page_id ) : home_url( '/blog/' );

// نام Transient
$transient_name = 'arian_latest_blog_posts_hyper_v2';
$posts_data = get_transient( $transient_name );

if ( false === $posts_data ) {
    
    $post_objects = get_posts([
        'post_type'      => 'post',
        'posts_per_page' => 8,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'post_status'    => 'publish',
        'no_found_rows'  => true,
    ]);

    $posts_data = [];

    if ( ! empty( $post_objects ) ) {
        foreach ( $post_objects as $post ) {
            
            // به جای URL، شناسه تصویر شاخص را دریافت می‌کنیم.
            $thumbnail_id = get_post_thumbnail_id( $post->ID );
            
            $posts_data[] = [
                'title'        => $post->post_title,
                'link'         => get_permalink( $post->ID ),
                'thumbnail_id' => $thumbnail_id, // شناسه را در کش ذخیره می‌کنیم.
            ];
        }
    }
    
    set_transient( $transient_name, $posts_data, 15 * MINUTE_IN_SECONDS );
}

if ( empty( $posts_data ) ) {
    return;
}
?>

<section class="arian-section section-blog" aria-labelledby="arian-blog-title">
    <div class="arian-header">
        <div class="arian-title-group">
            <h2 id="arian-blog-title" class="arian-title">
                <a href="<?php echo esc_url( $blog_url ); ?>">
                    نبض بازار
                </a>
            </h2>
            <div class="arian-line"></div>
        </div>

        <a href="<?php echo esc_url( $blog_url ); ?>" class="arian-view-all">
            همه مقالات
        </a>
    </div>

    <div class="arian-scroller blog-wrapper">
        <?php foreach ( $posts_data as $index => $post ) : ?>
            <article class="modern-blog-card">
                <a href="<?php echo esc_url( $post['link'] ); ?>" class="blog-card-img-link">
                    <?php
                    // با استفاده از شناسه، تگ <img> بهینه را تولید می‌کنیم.
                    if ( ! empty( $post['thumbnail_id'] ) ) {
                        
                        // آماده‌سازی آرایه ویژگی‌ها برای تابع
                        $img_attrs = [
                            'alt'      => esc_attr( $post['title'] ),
                            'decoding' => 'async',
                        ];

                        // افزودن منطق fetchpriority و loading
                        if ( $index === 0 ) {
                            $img_attrs['fetchpriority'] = 'high';
                        } else {
                            $img_attrs['loading'] = 'lazy';
                        }
                        
                        // چاپ تگ <img> کامل با srcset, sizes, width, height و...
                        echo wp_get_attachment_image( $post['thumbnail_id'], 'medium_large', false, $img_attrs );

                    } else {
                        /*
                         * مقاله بدون تصویر شاخص: کادر جایگزین با رنگ برند.
                         * قبلا assets/img/blog-placeholder.webp صدا زده می‌شد که در قالب
                         * وجود نداشت و تصویر شکسته (۴۰۴) نمایش داده می‌شد.
                         */
                        ?>
                        <span class="blog-card-img-placeholder" role="img" aria-label="<?php echo esc_attr( $post['title'] ); ?>">
                            <svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h4"/></svg>
                        </span>
                        <?php
                    }
                    ?>
                </a>

                <div class="blog-card-info">
                    <h3 class="blog-title">
                        <a href="<?php echo esc_url( $post['link'] ); ?>">
                            <?php echo esc_html( $post['title'] ); ?>
                        </a>
                    </h3>

                    <a href="<?php echo esc_url( $post['link'] ); ?>" class="read-more-btn" aria-label="مطالعه مقاله <?php echo esc_attr( $post['title'] ); ?>">
                        مشاهده
                    </a>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>