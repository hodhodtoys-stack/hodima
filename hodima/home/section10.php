<?php
/**
 * Social Networks Section – Final Optimized Version
 * Uses WordPress Media Library for automatic WebP and optimization.
 */
if ( ! defined('ABSPATH') ) exit;

// ✅ مرحله ۱: ID هر تصویر را از کتابخانه رسانه جایگزین کنید
$socials = [
    [ "url" => "https://instagram.com/hodimahli", "id" => 6371, "alt" => "اینستاگرام" ],
    [ "url" => "https://rubika.ir/hodhodli",    "id" => 6372, "alt" => "روبیکا" ],
    [ "url" => "https://www.aparat.com/hodhodli","id" => 6369, "alt" => "آپارات" ],
    [ "url" => "https://t.me/hodhodaccessory",  "id" => 6374, "alt" => "تلگرام" ],
    [ "url" => "https://wa.me/989124093140",    "id" => 6375, "alt" => "واتس‌اپ" ],
];
?>

<section class="arian-section section-socials" aria-labelledby="arian-social-title">
    <div class="arian-container">
        <div class="arian-header social-header">
            <div class="arian-title-group">
                <h2 id="arian-social-title" class="arian-title">شبکه های اجتماعی</h2>
                <div class="arian-line"></div>
            </div>
        </div>

        <div class="social-grid">
            <?php foreach ( $socials as $s ): ?>
                <a
                    href="<?php echo esc_url($s['url']); ?>"
                    target="_blank"
                    rel="nofollow noopener noreferrer"
                    class="modern-social-item"
                    title="<?php echo esc_attr($s['alt']); ?>"
                    aria-label="<?php echo esc_attr($s['alt']); ?>"
                >
                    <span class="social-icon-wrapper">
                        <?php
                        // ✅ مرحله ۲: اجازه دهید وردپرس تگ <img> را بهینه تولید کند
                        if ( ! empty( $s['id'] ) ) {
                            echo wp_get_attachment_image(
                                $s['id'],
                                'full', // برای آیکون‌های کوچک، سایز کامل مناسب است
                                false,
                                [
                                    'alt'      => esc_attr($s['alt']),
                                    'loading'  => 'lazy',
                                    'decoding' => 'async'
                                    // width و height به صورت خودکار توسط وردپرس اضافه می‌شود
                                ]
                            );
                        }
                        ?>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
