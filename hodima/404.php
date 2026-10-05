<?php
/**
 * صفحه «پیدا نشد» (۴۰۴)
 *
 * متن‌ها از «نمایش ← تنظیمات قالب هدیما ← هدر و ۴۰۴» (بازسازی قالب، مرحله ۴؛
 * قبلا ثابت). دکمه فروشگاه به برگه فروشگاه ووکامرس (قبلا آدرس ثابت /shop که
 * با نامک دیگر برگه فروشگاه ۴۰۴ می‌داد) و فقط وقتی فروشگاه هست.
 */

declare(strict_types=1);
if ( ! defined( 'ABSPATH' ) ) exit;

$hodima_404_setting = static fn( string $key, string $fallback ): string => function_exists( 'hodima_setting' ) ? (string) hodima_setting( $key ) : $fallback;
$hodima_404_shop    = function_exists( 'wc_get_page_permalink' ) && ( ! function_exists( 'hodima_setting' ) || hodima_setting( 'notfound_shop_button' ) )
	? (string) wc_get_page_permalink( 'shop' )
	: '';

get_header();
?>

<div class="error404-page">
    <div class="error404-box">

        <h1 class="error404-code">404</h1>

        <h2 class="error404-title"><?php echo esc_html( $hodima_404_setting( 'notfound_title', 'صفحه مورد نظر پیدا نشد!' ) ); ?></h2>

        <p class="error404-text">
            <?php echo esc_html( $hodima_404_setting( 'notfound_text', 'متأسفیم، به نظر می‌رسد آدرسی که وارد کرده‌اید اشتباه است یا این صفحه به مکان دیگری منتقل شده است.' ) ); ?>
        </p>

        <form class="error404-search" action="<?php echo esc_url( home_url( '/' ) ); ?>" method="get" role="search">
            <input type="text" name="s" placeholder="<?php echo esc_attr( $hodima_404_setting( 'notfound_placeholder', 'دنبال چه چیزی می‌گردید؟' ) ); ?>" aria-label="جستجو در سایت" required>
            <button type="submit">
                <svg class="error404-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                جستجو
            </button>
        </form>

        <div class="error404-actions">
            <a class="error404-btn error404-home" href="<?php echo esc_url( home_url( '/' ) ); ?>">
                <svg class="error404-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                صفحه اصلی
            </a>

            <?php if ( '' !== $hodima_404_shop ) : ?>
            <a class="error404-btn error404-shop" href="<?php echo esc_url( $hodima_404_shop ); ?>">
                <svg class="error404-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                رفتن به فروشگاه
            </a>
            <?php endif; ?>
        </div>

    </div>
</div>

<?php get_footer(); ?>
