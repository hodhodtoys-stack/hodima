<?php
/**
 * ==========================================================
 * footer.php — قالب هدیما
 * نسخه: 1.0.5 (استفاده از ساختار آیکون‌دار برای آدرس)
 *
 * فقط شامل HTML معنایی فوتر
 * CSS → assets/css/footer.css
 * JS  → assets/js/footer.js
 * ==========================================================
 */

defined('ABSPATH') || exit;
?>

<!-- پایان محتوای اصلی سایت -->

<footer class="custom-site-footer">
    <div class="footer-container">

        <!-- ستون اول: هویت هدهد -->
        <section class="footer-col">
            <h2>هویت هدهد</h2>
            <div class="footer-content">
                <p>
                شرکت بازرگانی هدهد از سال ۱۳۸۸ پیشرو در واردات مستقیم کالاهای درجه یک با قیمت عمده بی‌نظیر است. 
                هدهد با حذف واسطه‌ها، جدیدترین محصولات بازار جهانی را با بهترین قیمت برای پخش در بازار ایران و خاورمیانه فراهم می‌کند.
                </p>
            </div>
        </section>

        <!-- ستون دوم: راهنمای خرید -->
        <section class="footer-col">
            <h2>راهنمای خرید</h2>
            <div class="footer-content">
                <p>
                برای خرید محصولات، می‌توانید از سایت و کانال‌های تلگرام ما بازدید کرده، با واحد فروش تماس بگیرید یا برای دیدن نمونه محصولات به‌صورت حضوری مراجعه نمایید.
                </p>

                <!-- بخش آدرس با آیکون و لینک مپ -->
                <div class="footer-address" style="margin-top: 15px;">
                    <svg class="f-icon" viewBox="0 0 24 24" aria-hidden="true" style="width: 18px; height: 18px; fill: currentColor; vertical-align: middle; margin-left: 5px;">
                        <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                    </svg>

                    <a href="https://maps.app.goo.gl/hwnF4UqMJyeoQFrAA" target="_blank" rel="noopener noreferrer" style="color: inherit; text-decoration: none; transition: opacity 0.3s ease;" onmouseover="this.style.opacity='0.7'" onmouseout="this.style.opacity='1'">
                        تهران، تهرانپارس، فلکه اول، خیابان رشید، پلاک ۱۱۴
                    </a>
                </div>

            </div>
        </section>

        <!-- ستون سوم: نماد اعتماد -->
        <section class="footer-col">
            <h2>نماد اعتماد</h2>
            <div class="footer-content" style="text-align: center;">
                <a href="https://hodhodli.com/about-us/">
                    <img src="https://hodhodli.com/wp-content/uploads/2025/07/namad26.jpg" alt="نماد اعتماد بازرگانی هدهد" style="max-width: 110px; height: auto; border-radius: 12px; transition: transform 0.3s ease;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                </a>
            </div>
        </section>

        <!-- ستون چهارم: دریافت مشاوره -->
        <section class="footer-col footer-form-col">
            <h2>مشاوره خرید</h2>
            <div class="footer-content footer-form-content">

                <?php echo do_shortcode('[hodima_phone_form]'); ?>

            </div>
        </section>

    </div>

    <div class="footer-copyright">
        شرکت بازرگانی هدهد
    </div>
</footer>

<?php wp_footer(); ?>

</body>
</html>
