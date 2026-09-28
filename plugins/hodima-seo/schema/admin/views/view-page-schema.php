<?php
/**
 * Admin View: Sitemap / Schema Settings
 * Status: Display Only (View Mode)
 */

if ( ! defined( 'ABSPATH' ) ) exit;
// دفاع در عمق: علاوه بر گیت‌وی متد add_submenu_page، دسترسی هم اینجا مستقیماً بررسی می‌شود
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( __( 'شما به این بخش دسترسی ندارید.' ) );
}


hodima_view_header(
    'اسکیمای برگه‌ها',
    'وضعیت اسکیمای برگه‌های اصلی سایت (نمایشی).',
    'dashicons-admin-page'
);

$schema_pages = array(
    array( 'title' => 'صفحه اصلی', 'slug' => 'home',       'icon' => 'dashicons-admin-home' ),
    array( 'title' => 'فروشگاه',   'slug' => 'shop',       'icon' => 'dashicons-store' ),
    array( 'title' => 'درباره ما', 'slug' => 'about-us',   'icon' => 'dashicons-info-outline' ),
    array( 'title' => 'تماس با ما', 'slug' => 'contact-us', 'icon' => 'dashicons-phone' ),
);
?>

<section class="hd-card">
    <header class="hd-card__head">
        <?php echo hodima_admin_icon( 'dashicons-admin-page' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
        <h2 class="hd-card__title">برگه‌های اصلی</h2>
        <span class="hd-pill hd-pill--ok">فعال</span>
    </header>

    <div class="hd-grid hd-grid--stats">
        <?php foreach ( $schema_pages as $page ) : ?>
            <div class="hd-link-card hodima-page-status">
                <?php echo hodima_admin_icon( $page['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
                <h3 class="hd-link-card__title"><?php echo esc_html( $page['title'] ); ?></h3>
                <code><?php echo esc_html( $page['slug'] ); ?></code>
                <span class="hd-pill hd-pill--ok">فعال</span>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<?php hodima_view_footer(); ?>
