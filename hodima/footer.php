<?php
/**
 * ==========================================================
 * footer.php — قالب هدیما
 * نسخه: 2.1.0 (محتوا از «نمایش ← تنظیمات قالب هدیما»)
 *
 * فقط شامل HTML معنایی فوتر
 * CSS → assets/css/footer.css
 * JS  → assets/js/footer.js
 *
 * متن‌ها، آدرس، نماد اعتماد و کپی‌رایت قبلا در همین فایل ثابت نوشته
 * شده بودند. ستونی که در تنظیمات محتوایی ندارد نمایش داده نمی‌شود.
 * نوار شبکه‌های اجتماعی (inc/footer.php) درست بالای فوتر همه صفحه‌ها می‌آید.
 * ==========================================================
 */

defined( 'ABSPATH' ) || exit;

$hodima_s = function_exists( 'hodima_settings' ) ? hodima_settings() : [];

$hodima_about_text  = (string) ( $hodima_s['about_text'] ?? '' );
$hodima_guide_text  = (string) ( $hodima_s['guide_text'] ?? '' );
$hodima_address     = (string) ( $hodima_s['address'] ?? '' );
$hodima_map_url     = (string) ( $hodima_s['map_url'] ?? '' );
$hodima_trust      = function_exists( 'hodima_trust_badges' ) ? hodima_trust_badges() : [];
$hodima_trust_title = (string) ( $hodima_s['trust_title'] ?? '' );
$hodima_has_form    = shortcode_exists( 'hodima_phone_form' );
$hodima_copyright   = (string) ( $hodima_s['copyright'] ?? '' ) ?: get_bloginfo( 'name' );
?>

<!-- پایان محتوای اصلی سایت -->

<?php
if ( function_exists( 'hodima_render_social_bar' ) ) {
    hodima_render_social_bar();
}
?>

<footer class="custom-site-footer">
    <div class="footer-container">

        <?php if ( '' !== $hodima_about_text ) : ?>
        <!-- ستون اول: درباره ما -->
        <section class="footer-col" aria-labelledby="footer-about-title">
            <h2 id="footer-about-title"><?php echo esc_html( (string) ( $hodima_s['about_title'] ?? '' ) ); ?></h2>
            <div class="footer-content">
                <?php echo wpautop( esc_html( $hodima_about_text ) ); ?>
            </div>
        </section>
        <?php endif; ?>

        <?php if ( '' !== $hodima_guide_text || '' !== $hodima_address ) : ?>
        <!-- ستون دوم: راهنمای خرید و آدرس -->
        <section class="footer-col" aria-labelledby="footer-guide-title">
            <h2 id="footer-guide-title"><?php echo esc_html( (string) ( $hodima_s['guide_title'] ?? '' ) ); ?></h2>
            <div class="footer-content">
                <?php echo '' !== $hodima_guide_text ? wpautop( esc_html( $hodima_guide_text ) ) : ''; ?>

                <?php if ( '' !== $hodima_address ) : ?>
                <address class="footer-address">
                    <svg class="f-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                    </svg>
                    <?php if ( '' !== $hodima_map_url ) : ?>
                        <a class="footer-address__link" href="<?php echo esc_url( $hodima_map_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $hodima_address ); ?></a>
                    <?php else : ?>
                        <span><?php echo esc_html( $hodima_address ); ?></span>
                    <?php endif; ?>
                </address>
                <?php endif; ?>
            </div>
        </section>
        <?php endif; ?>

        <?php if ( $hodima_trust ) : ?>
        <!-- ستون سوم: نمادهای اعتماد (تا سه نماد، به ترتیب تنظیمات، کنار هم) -->
        <section class="footer-col footer-trust-col" aria-labelledby="footer-trust-title">
            <h2 id="footer-trust-title"><?php echo esc_html( $hodima_trust_title ); ?></h2>
            <div class="footer-content footer-trust">
                <ul class="footer-trust__list" role="list">
                    <?php foreach ( $hodima_trust as $hodima_badge ) : ?>
                        <?php
                        // متن جایگزین: alt خود تصویر در کتابخانه رسانه، وگرنه عنوان ستون
                        $hodima_badge_alt = trim( (string) get_post_meta( $hodima_badge['image_id'], '_wp_attachment_image_alt', true ) ) ?: $hodima_trust_title;
                        $hodima_badge_img = wp_get_attachment_image( $hodima_badge['image_id'], 'medium', false, [
                            'class'    => 'footer-trust__img',
                            'alt'      => $hodima_badge_alt,
                            'sizes'    => '110px',
                            'loading'  => 'lazy',
                            'decoding' => 'async',
                        ] );
                        ?>
                        <li class="footer-trust__item">
                            <?php if ( '' !== $hodima_badge['url'] ) : ?>
                                <a class="footer-trust__link" href="<?php echo esc_url( $hodima_badge['url'] ); ?>" target="_blank" rel="noopener"><?php echo $hodima_badge_img; // خروجی wp_get_attachment_image ?></a>
                            <?php else : ?>
                                <?php echo $hodima_badge_img; // خروجی wp_get_attachment_image ?>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>
        <?php endif; ?>

        <?php if ( $hodima_has_form ) : ?>
        <!-- ستون چهارم: دریافت مشاوره -->
        <section class="footer-col footer-form-col" aria-labelledby="footer-consult-title">
            <h2 id="footer-consult-title"><?php echo esc_html( (string) ( $hodima_s['consult_title'] ?? '' ) ); ?></h2>
            <div class="footer-content footer-form-content">
                <?php echo do_shortcode( '[hodima_phone_form]' ); ?>
            </div>
        </section>
        <?php endif; ?>

    </div>

    <p class="footer-copyright">
        &copy; <?php echo esc_html( $hodima_copyright ); ?>
    </p>
</footer>

<?php wp_footer(); ?>

</body>
</html>
