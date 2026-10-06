<?php
/**
 * Footer Module – Enqueue styles & scripts و نوار شبکه‌های اجتماعی
 *
 * @package hodima
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Enqueue footer-specific CSS & JS (Vanilla JS، در فوتر).
 */
function hodima_footer_assets(): void {
    // CSS فوتر با CSS مشترک همه صفحه‌ها (hodima_enqueue_common_css در inc/enqueue.php)
    hodima_enqueue_asset( 'hodima-footer', 'assets/js/footer.js', [], [ 'in_footer' => true ] );
}
add_action( 'wp_enqueue_scripts', 'hodima_footer_assets', 20 );

/**
 * نوار شبکه‌های اجتماعی بالای فوتر، در همه صفحه‌ها.
 *
 * قبلا فقط شورت‌کد [section10] صفحه اصلی آن را چاپ می‌کرد و بقیه صفحه‌ها
 * شبکه‌های اجتماعی نداشتند. حالا footer.php پیش از <footer> این تابع را صدا
 * می‌زند؛ صفحه‌های فهرست «تنظیمات قالب هدیما ← شبکه‌های اجتماعی ← صفحه‌هایی که …
 * نمایش داده نشود» مستثنا هستند. استایل در assets/css/footer.css (همه صفحه‌ها).
 */
function hodima_render_social_bar(): void {

    if ( ! function_exists( 'hodima_social_links' ) || ! function_exists( 'hodima_socials_visible_here' ) ) {
        return;
    }

    $socials = hodima_social_links();

    // بدون هیچ شبکه‌ای، یا در صفحه مستثنا، نوار اصلا چاپ نمی‌شود
    if ( ! $socials || ! hodima_socials_visible_here() ) {
        return;
    }

    $title = (string) hodima_setting( 'social_title' );
    ?>
    <section class="hodima-socials" aria-labelledby="hodima-socials-title">
        <div class="hodima-socials__inner">
            <h2 id="hodima-socials-title" class="hodima-socials__title"><?php echo esc_html( $title ); ?></h2>

            <ul class="hodima-socials__list" role="list">
                <?php foreach ( $socials as $social ) : ?>
                    <li>
                        <a
                            class="hodima-socials__link hodima-socials__link--<?php echo esc_attr( $social['key'] ); ?>"
                            href="<?php echo esc_url( $social['url'] ); ?>"
                            target="_blank"
                            rel="nofollow noopener noreferrer"
                            aria-label="<?php echo esc_attr( $social['label'] ); ?>"
                        >
                            <?php if ( $social['icon_id'] && wp_attachment_is_image( $social['icon_id'] ) ) : ?>
                                <?php
                                // medium: برش نمی‌خورد (برخلاف thumbnail)؛ sizes کوچک‌ترین نسخه srcset را انتخاب می‌کند
                                echo wp_get_attachment_image( $social['icon_id'], 'medium', false, [
                                    'class'    => 'hodima-socials__icon',
                                    'sizes'    => '48px',
                                    'alt'      => '', // نام شبکه در aria-label لینک آمده است
                                    'loading'  => 'lazy',
                                    'decoding' => 'async',
                                ] );
                                ?>
                            <?php else : ?>
                                <span class="hodima-socials__name"><?php echo esc_html( $social['label'] ); ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>
    <?php
}
