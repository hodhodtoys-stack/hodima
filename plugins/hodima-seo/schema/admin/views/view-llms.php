<?php
/**
 * Admin View: LLMs.txt Configuration (Display Only Version)
 * Path: wp-content/plugins/hodima-seo/schema/admin/views/view-llms.php
 */

if ( ! defined( 'ABSPATH' ) ) exit;
// دفاع در عمق: علاوه بر گیت‌وی متد add_submenu_page، دسترسی هم اینجا مستقیماً بررسی می‌شود
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'شما به این بخش دسترسی ندارید.' ) );
}


$base_url = untrailingslashit( home_url() );

hodima_view_header(
    'هوش مصنوعی (AEO و وب معنایی)',
    'همه مسیرها، فایل‌ها و وب‌سرویس‌هایی که سایت را برای ربات‌های هوش مصنوعی خوانا می‌کنند.',
    'dashicons-lightbulb'
);

/**
 * یک ردیف آدرس ماشین‌خوان.
 *
 * @param string      $path  مسیر نمایشی
 * @param string|null $link  آدرس کامل برای لینک؛ null = بدون لینک
 * @param string      $label متن لینک، یا برچسب کنار مسیر وقتی لینکی نیست
 */
$hodima_aeo_row = static function ( string $path, ?string $link = null, string $label = 'مشاهده' ): void {
    echo '<div class="hd-code"><span>' . esc_html( $path ) . '</span>';
    if ( null !== $link ) {
        printf( '<a href="%1$s" target="_blank" rel="noopener">%2$s</a>', esc_url( $link ), esc_html( $label ) );
    } elseif ( '' !== $label ) {
        echo '<span class="hd-code__tag">' . esc_html( $label ) . '</span>';
    }
    echo '</div>';
};
?>

<section class="hd-card hd-card--accent">
    <header class="hd-card__head">
        <?php echo hodima_admin_icon( 'dashicons-tag' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
        <h2 class="hd-card__title">تگ‌های معرفی در هدر صفحه</h2>
    </header>

    <div class="hd-fields">
        <div class="hd-field">
            <span class="hd-field__label">۱. دروازه ورود اصلی (Global Discovery Tag)</span>
            <p class="hd-field__help">نقشه راه کل سایت (<code>llms.txt</code>) را به هوش مصنوعی معرفی می‌کند.</p>
            <div class="hd-code">&lt;link rel="llms" href="<?php echo esc_url($base_url); ?>/llms.txt"&gt;</div>
        </div>
        <div class="hd-field">
            <span class="hd-field__label">۲. کشف سطح صفحه (Page-Level Discovery Tag)</span>
            <p class="hd-field__help">در محصولات و مقالات چاپ می‌شود تا نسخه Markdown همان صفحه را معرفی کند.</p>
            <div class="hd-code">&lt;link rel="alternate" type="text/markdown" href="<?php echo esc_url($base_url); ?>/product-url.md"&gt;</div>
        </div>
    </div>
</section>

<div class="hd-grid hd-grid--wide">
    <section class="hd-card">
        <header class="hd-card__head">
            <?php echo hodima_admin_icon( 'dashicons-location-alt' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
            <h2 class="hd-card__title">نقشه‌های راه (AI Sitemaps)</h2>
        </header>
        <div class="hd-field">
            <span class="hd-field__label">نقشه سبک و سیلویی <code>llms.txt</code></span>
            <p class="hd-field__help">معماری سایت، اطلاعات شرکت و ۱۰ محصول به‌روز آخر هر دسته.</p>
            <?php $hodima_aeo_row( '/llms.txt', $base_url . '/llms.txt' ); ?>
        </div>
        <hr class="hd-divider">
        <div class="hd-field">
            <span class="hd-field__label">نقشه کامل کاتالوگ <code>llms-full.txt</code></span>
            <p class="hd-field__help">تا ۵۰۰ لینک از همه محصولات و مقالات برای خزش عمیق.</p>
            <?php $hodima_aeo_row( '/llms-full.txt', $base_url . '/llms-full.txt' ); ?>
        </div>
    </section>

    <section class="hd-card">
        <header class="hd-card__head">
            <?php echo hodima_admin_icon( 'dashicons-media-text' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
            <h2 class="hd-card__title">صفحه‌های خوانا برای ماشین (.md)</h2>
        </header>
        <p class="hd-text">با افزودن <code>.md</code> به انتهای آدرس هر محصول یا مقاله، نسخه پردازش‌شده (متادیتا، MOQ و گراف دانش) را ببینید.</p>
        <?php
        $hodima_aeo_row( '/product-name.md', null, 'محصولات' );
        $hodima_aeo_row( '/category-name.md', null, 'دسته‌بندی‌ها' );
        $hodima_aeo_row( '/article-name.md', null, 'مقالات' );
        ?>
    </section>

    <section class="hd-card">
        <header class="hd-card__head">
            <?php echo hodima_admin_icon( 'dashicons-rest-api' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
            <h2 class="hd-card__title">وب‌سرویس‌ها و فیدهای زنده</h2>
        </header>
        <div class="hd-field">
            <span class="hd-field__label">جستجوی زنده (Live Search)</span>
            <p class="hd-field__help">جستجو در محصولات با خروجی Markdown برای هوش مصنوعی.</p>
            <?php $hodima_aeo_row( '/llm-search?q=keyword', $base_url . '/llm-search?q=کلیپس', 'تست' ); ?>
        </div>
        <hr class="hd-divider">
        <div class="hd-field">
            <span class="hd-field__label">فید تازه‌ها (۲۴ ساعت)</span>
            <p class="hd-field__help">خروجی JSON از موجودیت‌هایی که در ۲۴ ساعت گذشته به‌روز شده‌اند.</p>
            <?php $hodima_aeo_row( '/ai-feed.json', $base_url . '/ai-feed.json' ); ?>
        </div>
        <hr class="hd-divider">
        <div class="hd-field">
            <span class="hd-field__label">معرفی وب‌سرویس (OpenAPI)</span>
            <p class="hd-field__help">استاندارد اتصال Custom GPTها به جستجوی سایت.</p>
            <?php $hodima_aeo_row( '/openapi.json', $base_url . '/openapi.json' ); ?>
        </div>
    </section>

    <section class="hd-card">
        <header class="hd-card__head">
            <?php echo hodima_admin_icon( 'dashicons-lock' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
            <h2 class="hd-card__title">امنیت و مانیتورینگ</h2>
        </header>
        <div class="hd-field">
            <span class="hd-field__label">تله امنیتی (Honeypot)</span>
            <p class="hd-field__help">اسکرپرها با باز کردن این آدرس بلافاصله مسدود می‌شوند. <strong>خودتان باز نکنید.</strong></p>
            <?php $hodima_aeo_row( '/ai-internal-data.md', null, '' ); ?>
        </div>
        <hr class="hd-divider">
        <div class="hd-field">
            <span class="hd-field__label">لاگ ربات‌ها (AI Analytics)</span>
            <p class="hd-field__help">گزارش JSON رفتار و IP ربات‌های هوش مصنوعی (فقط مدیر).</p>
            <?php $hodima_aeo_row( '/ai-analytics.json', $base_url . '/ai-analytics.json' ); ?>
        </div>
    </section>
</div>

<?php hodima_view_footer(); ?>