<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <?php
    /**
     * نکته سئو (فاز ۲):
     *
     * ۱. نود Organization که قبلا اینجا به صورت hardcode چاپ می‌شد حذف شد.
     *    schema/homepage-schema.php روی هر صفحه یک Organization کامل با
     *    @id برابر <site_url>#organization می‌سازد (شامل logo، sameAs،
     *    contactPoint و address از پنل مدیریت). نسخه ثابت اینجا یک موجودیت
     *    دوم و بدون @id ایجاد می‌کرد که گوگل آن را تکرار می‌دید.
     *
     * ۲. تگ og:image ثابت هم حذف شد. core/seobox/front-output.php برای هر
     *    صفحه og:image اختصاصی با ابعاد، alt و نوع فایل تولید می‌کند، ولی
     *    چون تگ ثابت قبل از wp_head() چاپ می‌شد، همیشه اول قرار می‌گرفت و
     *    اسکرپرها (تلگرام، واتساپ، فیسبوک) همان لوگو را برمی‌داشتند —
     *    نه تصویر محصول یا مقاله.
     *
     * لوگو و لینک شبکه‌های اجتماعی را از پنل «اسکیما ← صفحه اصلی» تنظیم کنید.
     */
    ?>
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link" href="#site-content">پرش به محتوای اصلی</a>
<?php
// کانال‌های تماس از «نمایش ← تنظیمات هدیما»؛ بدون کانال، دکمه و پنجره پشتیبانی چاپ نمی‌شوند.
$hodima_channels = function_exists( 'hodima_contact_channels' ) ? hodima_contact_channels() : [];
$hodima_logo_cls = ( function_exists( 'hodima_setting' ) && hodima_setting( 'logo_invert' ) ) ? ' header__logo--invert' : '';
?>

    <!-- ============================================
         ناحیه کامل هدر سایت
    ============================================ -->
    <div class="site-header-area" id="headerWrapper">

        <!-- ─── هدر اصلی ─── -->
        <header class="header" id="mainHeader" role="banner">
            <div class="header__container">

                <!-- سمت راست: همبرگر + لوگو + منوی اصلی -->
                <div class="header__right">

                    <!-- ✅ دکمه همبرگر (فقط موبایل) -->
                    <button
                        class="header__mobile-toggle"
                        id="mobileMenuTrigger"
                        type="button"
                        aria-label="باز کردن منوی موبایل"
                        aria-expanded="false"
                        aria-controls="mainNavWrapper"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M4 6h16M4 12h16m-7 6h7"/>
                        </svg>
                    </button>

                    <!-- لوگو از تنظیمات قالب؛ در صورت فعال بودن گزینه، با CSS سفید نمایش داده می‌شود -->
                    <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="header__logo<?php echo esc_attr( $hodima_logo_cls ); ?>" aria-label="<?php echo esc_attr( 'صفحه اصلی ' . get_bloginfo( 'name' ) ); ?>" rel="home">
                        <?php echo function_exists( 'hodima_logo_html' ) ? hodima_logo_html() : esc_html( get_bloginfo( 'name' ) ); ?>
                    </a>

                    <!-- منوی اصلی (یکپارچه برای دسکتاپ و موبایل) -->
                    <nav class="main-nav" id="mainNavWrapper" aria-label="منوی اصلی سایت">
                        <?php
                        wp_nav_menu([
                            'menu'        => 'منوی اصلی',
                            'menu_class'  => 'main-menu',
                            'container'   => false,
                            'echo'        => true,
                            'fallback_cb' => false,
                        ]);
                        ?>
                    </nav>
                </div>

                <!-- مرکز: جستجو با ساختار فلکس و منعطف -->
                <div class="header__center">
                    <search class="header__search" role="search" aria-label="جستجوی محصولات">
                        <?php echo do_shortcode('[woo_live_search]'); ?>
                    </search>
                </div>

                <!-- سمت چپ: دکمه پشتیبانی -->
                <div class="header__left">
                    <?php if ( $hodima_channels ) : ?>
                    <button
                        class="btn-support"
                        id="supportTrigger"
                        type="button"
                        aria-label="باز کردن پنجره پشتیبانی"
                        aria-haspopup="dialog"
                    >
                        <svg viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M3 18v-6a9 9 0 0 1 18 0v6"/>
                            <path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"/>
                            <path d="M19 22v-3"/>
                        </svg>
                        <span>پشتیبانی</span>
                    </button>
                    <?php endif; ?>
                </div>

            </div>
        </header>
    </div>

    <?php if ( $hodima_channels ) : ?>
    <!-- ============================================
         پاپ‌آپ پشتیبانی (کانال‌ها از تنظیمات قالب)
    ============================================ -->
    <div
        class="overlay"
        id="supportOverlay"
        role="dialog"
        aria-modal="true"
        aria-labelledby="supportTitle"
        aria-hidden="true"
    >
        <article class="popup">
            <button
                class="popup__close"
                id="supportClose"
                type="button"
                aria-label="بستن پنجره پشتیبانی"
            >&times;</button>
            <h3 class="popup__title" id="supportTitle">ارتباط با ما</h3>

            <?php
            $hodima_channel_icons = [
                'call'     => '<path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/>',
                'whatsapp' => '<path d="M12 2a10 10 0 0 0-8.66 15l-1.3 4.76 4.87-1.28A10 10 0 1 0 12 2zm5.2 14.1c-.22.62-1.28 1.18-1.77 1.23-.47.05-.92.22-3.09-.64-2.61-1.03-4.27-3.7-4.4-3.87-.13-.17-1.05-1.4-1.05-2.67 0-1.27.66-1.9.9-2.16.23-.26.5-.32.67-.32h.48c.15 0 .36-.06.56.43.22.5.73 1.77.8 1.9.06.13.1.28.02.45-.08.17-.13.28-.26.43l-.38.45c-.13.13-.26.27-.11.53.15.26.67 1.1 1.44 1.78.99.88 1.82 1.15 2.08 1.28.26.13.41.11.56-.07.15-.17.65-.75.82-1.01.17-.26.34-.22.58-.13.24.09 1.5.71 1.76.84.26.13.43.19.5.3.06.1.06.62-.16 1.24z"/>',
                'rubika'   => '<path d="M20 2H4A2 2 0 0 0 2 4v18l4-4h14a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2z"/>',
                'telegram' => '<path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>',
            ];
            foreach ( $hodima_channels as $hodima_channel ) :
                $hodima_external = 'call' !== $hodima_channel['key'];
                ?>
                <a
                    href="<?php echo esc_url( $hodima_channel['url'], $hodima_external ? [ 'https', 'http' ] : [ 'tel' ] ); ?>"
                    class="popup__link popup__link--<?php echo esc_attr( $hodima_channel['key'] ); ?>"
                    aria-label="<?php echo esc_attr( $hodima_channel['aria'] ); ?>"
                    <?php echo $hodima_external ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>
                >
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><?php echo $hodima_channel_icons[ $hodima_channel['key'] ]; // SVG ثابت داخل همین فایل ?></svg>
                    <?php echo esc_html( $hodima_channel['label'] ); ?>
                </a>
            <?php endforeach; ?>
        </article>
    </div>    <?php endif; ?>

    <!-- مقصد لینک «پرش به محتوای اصلی»: شروع محتوای هر قالب -->
    <span id="site-content" class="skip-link-target" tabindex="-1"></span>
