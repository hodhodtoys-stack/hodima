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

/**
 * یک ردیف مرجع: عنوان و توضیح، زیرش مسیر ماشین‌خوان و دکمه‌ها (کپی / مشاهده).
 * متن عنوان و توضیح HTML امن‌شده‌اند (code، strong)؛ مسیر و آدرس‌ها در خود تابع escape می‌شوند.
 *
 * @param string      $title  عنوان (HTML امن)
 * @param string      $desc   توضیح یک‌خطی (HTML امن؛ خالی = بدون توضیح)
 * @param string      $path   مسیر نمایشی
 * @param string|null $link   آدرس کامل برای «کپی» و «مشاهده»؛ null = فقط نمایش مسیر
 * @param string      $view   متن دکمه باز کردن آدرس
 * @param string|null $copy   متن کپی‌شدنی (پیش‌فرض $link)؛ برای تگ‌های هدر
 */
$hodima_aeo_row = static function ( string $title, string $desc, string $path, ?string $link = null, string $view = 'مشاهده', ?string $copy = null ): void {
    echo '<div class="hds-item"><div><div class="hds-item__title">' . wp_kses_post( $title ) . '</div>';
    if ( '' !== $desc ) {
        echo '<p class="hds-item__desc">' . wp_kses_post( $desc ) . '</p>';
    }
    echo '</div><div class="hds-item__body"><span class="hds-path" dir="ltr">' . esc_html( $path ) . '</span>';
    $copy = $copy ?? $link;
    if ( null !== $copy ) {
        printf( '<button type="button" class="hds-btn hds-btn--outline" data-hds-copy="%s">کپی</button>', esc_attr( $copy ) );
    }
    if ( null !== $link && '' !== $view ) {
        printf( '<a class="hds-btn hds-btn--outline" href="%1$s" target="_blank" rel="noopener">%2$s</a>', esc_url( $link ), esc_html( $view ) );
    }
    echo '</div></div>';
};

hodima_view_header(
    'هوش مصنوعی (AEO و وب معنایی)',
    'همه مسیرها، فایل‌ها و وب‌سرویس‌هایی که سایت را برای ربات‌های هوش مصنوعی خوانا می‌کنند.',
    'dashicons-lightbulb'
);
?>

<div class="hds-ui"><div class="hds-page__body">

<?php
hodima_seo_ui_card_open( 'نقشه‌های راه برای هوش مصنوعی', 'این دو فایل نقطه شروع ربات‌هاست. نیازی به تنظیم نیست؛ فقط آدرس را دارید.', 'dashicons-location-alt' );
$hodima_aeo_row( 'نقشه سبک و سیلویی <code class="hds-code">llms.txt</code>', 'معماری سایت، اطلاعات شرکت و ۱۰ محصول به‌روز آخر هر دسته.', $base_url . '/llms.txt', $base_url . '/llms.txt' );
$hodima_aeo_row( 'نقشه کامل کاتالوگ <code class="hds-code">llms-full.txt</code>', 'تا ۵۰۰ لینک از همه محصولات و مقالات برای خزش عمیق.', $base_url . '/llms-full.txt', $base_url . '/llms-full.txt' );
hodima_seo_ui_card_close();

echo '<section class="hds-card">';
hodima_seo_ui_tabs( 'hodima-llms', 'بخش‌های هوش مصنوعی', [
    'tags' => [
        'label' => 'تگ‌های هدر',
        'count' => '۲',
        'html'  => static function () use ( $hodima_aeo_row, $base_url ): void {
            $hodima_aeo_row(
                'دروازه ورود اصلی (Global Discovery Tag)',
                'نقشه راه کل سایت (<code class="hds-code">llms.txt</code>) را در هدر هر صفحه به هوش مصنوعی معرفی می‌کند.',
                '<link rel="llms" href="' . $base_url . '/llms.txt">',
                null,
                '',
                '<link rel="llms" href="' . $base_url . '/llms.txt">'
            );
            $hodima_aeo_row(
                'کشف سطح صفحه (Page-Level Discovery Tag)',
                'در محصولات و مقالات چاپ می‌شود تا نسخه Markdown همان صفحه را معرفی کند.',
                '<link rel="alternate" type="text/markdown" href="' . $base_url . '/product-url.md">',
                null,
                '',
                '<link rel="alternate" type="text/markdown" href="' . $base_url . '/product-url.md">'
            );
        },
    ],
    'md'   => [
        'label' => 'صفحه‌های .md',
        'count' => '۳',
        'html'  => static function () use ( $hodima_aeo_row ): void {
            echo '<p class="hds-card__desc">با افزودن <code class="hds-code">.md</code> به انتهای آدرس هر محصول، دسته یا مقاله، نسخه پردازش‌شده (متادیتا، MOQ و گراف دانش) را ببینید.</p>';
            $hodima_aeo_row( 'محصولات', '', '/product-name.md' );
            $hodima_aeo_row( 'دسته‌بندی‌ها', '', '/category-name.md' );
            $hodima_aeo_row( 'مقالات', '', '/article-name.md' );
        },
    ],
    'api'  => [
        'label' => 'وب‌سرویس‌ها و فیدها',
        'count' => '۳',
        'html'  => static function () use ( $hodima_aeo_row, $base_url ): void {
            $hodima_aeo_row( 'جستجوی زنده (Live Search)', 'جستجو در محصولات با خروجی Markdown برای هوش مصنوعی.', '/llm-search?q=keyword', $base_url . '/llm-search?q=کلیپس', 'آزمایش', $base_url . '/llm-search?q=keyword' );
            $hodima_aeo_row( 'فید تازه‌ها (۲۴ ساعت)', 'خروجی JSON از موجودیت‌هایی که در ۲۴ ساعت گذشته به‌روز شده‌اند.', '/ai-feed.json', $base_url . '/ai-feed.json' );
            $hodima_aeo_row( 'معرفی وب‌سرویس (OpenAPI)', 'استاندارد اتصال Custom GPTها به جستجوی سایت.', '/openapi.json', $base_url . '/openapi.json' );
        },
    ],
    'sec'  => [
        'label' => 'امنیت و مانیتورینگ',
        'count' => '۲',
        'html'  => static function () use ( $hodima_aeo_row, $base_url ): void {
            $hodima_aeo_row( 'تله امنیتی (Honeypot) <span class="hds-tag hds-tag--warn">خودتان باز نکنید</span>', 'اسکرپرها با باز کردن این آدرس بلافاصله مسدود می‌شوند.', '/ai-internal-data.md' );
            $hodima_aeo_row( 'لاگ ربات‌ها (AI Analytics)', 'گزارش JSON رفتار و IP ربات‌های هوش مصنوعی (فقط مدیر).', '/ai-analytics.json', $base_url . '/ai-analytics.json' );
        },
    ],
] );
echo '</section>';
?>

</div></div>

<?php hodima_view_footer(); ?>
