<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) exit; ?>
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
                        <?php echo function_exists( 'hodima_logo_html' ) ? hodima_logo_html() : esc_html( get_bloginfo( 'name' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- hodima_logo_html خودش escape می‌کند ?>
                    </a>

                    <!-- منوی اصلی (یکپارچه برای دسکتاپ و موبایل) -->
                    <nav class="main-nav" id="mainNavWrapper" aria-label="منوی اصلی سایت">
                        <?php
                        // مکان «منوی اصلی (هدر)» در «نمایش ← فهرست‌ها»؛ قبلا منو با نامش صدا زده می‌شد
                        wp_nav_menu( [
                            'theme_location' => HODIMA_MENU_PRIMARY,
                            'menu_class'     => 'main-menu',
                            'container'      => '', // بدون ظرف (رشته خالی = false در wp_nav_menu)
                            'fallback_cb'    => false,
                        ] );
                        ?>
                    </nav>
                </div>

                <!-- مرکز: جستجو با ساختار فلکس و منعطف -->
                <div class="header__center">
                    <?php if ( ! function_exists( 'hodima_setting' ) || hodima_setting( 'header_search' ) ) : ?>
                    <search class="header__search" role="search" aria-label="جستجوی محصولات">
                        <?php
                        // جستجوی زنده محصولات از افزونه Hodima Commerce؛ بدون آن فرم جستجوی وردپرس
                        echo shortcode_exists( 'woo_live_search' ) ? do_shortcode( '[woo_live_search]' ) : get_search_form( [ 'echo' => false ] );
                        ?>
                    </search>
                    <?php endif; ?>
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
                        <span><?php echo esc_html( function_exists( 'hodima_setting' ) ? (string) hodima_setting( 'header_support_text' ) : 'پشتیبانی' ); ?></span>
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
    <?php
    /*
     * <dialog> بومی (قبلا div با role="dialog"، aria-hidden و تله فوکوس دستی در
     * header.js): showModal() بقیه صفحه را inert می‌کند، Escape و برگشت فوکوس به
     * دکمه را خود مرورگر انجام می‌دهد. ظاهر و انیمیشن همان .overlay قبلی.
     */
    ?>
    <dialog
        class="overlay"
        id="supportOverlay"
        aria-labelledby="supportTitle"
    >
        <article class="popup">
            <button
                class="popup__close"
                id="supportClose"
                type="button"
                aria-label="بستن پنجره پشتیبانی"
            >&times;</button>
            <h3 class="popup__title" id="supportTitle"><?php echo esc_html( function_exists( 'hodima_setting' ) ? (string) hodima_setting( 'header_popup_title' ) : 'ارتباط با ما' ); ?></h3>

            <?php foreach ( $hodima_channels as $hodima_link ) : ?>
                <a
                    href="<?php echo esc_url( $hodima_link->url, $hodima_link->channel->protocols() ); ?>"
                    class="popup__link popup__link--<?php echo esc_attr( $hodima_link->channel->value ); ?>"
                    aria-label="<?php echo esc_attr( $hodima_link->aria ); ?>"
                    <?php echo $hodima_link->channel->is_external() ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>
                >
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><?php echo $hodima_link->channel->icon(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG ثابت داخل enum (inc/classes) ?></svg>
                    <?php echo esc_html( $hodima_link->channel->label() ); ?>
                </a>
            <?php endforeach; ?>
        </article>
    </dialog>
    <?php endif; ?>

    <!-- مقصد لینک «پرش به محتوای اصلی»: شروع محتوای هر قالب -->
    <span id="site-content" class="skip-link-target" tabindex="-1"></span>
