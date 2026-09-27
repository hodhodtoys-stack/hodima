<?php
/**
 * Social Networks Section
 * آدرس‌ها و آیکون‌ها از «نمایش ← تنظیمات هدیما ← شبکه‌های اجتماعی».
 * (قبلا آدرس‌ها و شناسه تصاویر مستقیم در همین فایل نوشته شده بودند.)
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$hodima_socials = function_exists( 'hodima_social_links' ) ? hodima_social_links() : [];

// بدون هیچ شبکه‌ای، بخش اصلا چاپ نمی‌شود
if ( ! $hodima_socials ) {
    return;
}

$hodima_social_title = function_exists( 'hodima_setting' ) ? (string) hodima_setting( 'social_title' ) : '';
?>

<section class="arian-section section-socials" aria-labelledby="arian-social-title">
    <div class="arian-container">
        <div class="arian-header social-header">
            <div class="arian-title-group">
                <h2 id="arian-social-title" class="arian-title"><?php echo esc_html( $hodima_social_title ); ?></h2>
                <div class="arian-line"></div>
            </div>
        </div>

        <ul class="social-grid" role="list">
            <?php foreach ( $hodima_socials as $hodima_social ) : ?>
                <li>
                    <a
                        href="<?php echo esc_url( $hodima_social['url'] ); ?>"
                        target="_blank"
                        rel="nofollow noopener noreferrer"
                        class="modern-social-item modern-social-item--<?php echo esc_attr( $hodima_social['key'] ); ?>"
                        aria-label="<?php echo esc_attr( $hodima_social['label'] ); ?>"
                    >
                        <span class="social-icon-wrapper">
                            <?php if ( $hodima_social['icon_id'] && wp_attachment_is_image( $hodima_social['icon_id'] ) ) : ?>
                                <?php
                                echo wp_get_attachment_image( $hodima_social['icon_id'], 'full', false, [
                                    'alt'      => '', // نام شبکه در aria-label لینک آمده است
                                    'loading'  => 'lazy',
                                    'decoding' => 'async',
                                ] );
                                ?>
                            <?php else : ?>
                                <span class="social-icon-text"><?php echo esc_html( $hodima_social['label'] ); ?></span>
                            <?php endif; ?>
                        </span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>
