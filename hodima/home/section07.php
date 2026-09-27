<?php
/**
 * Section 01 - Intro, Middle Space, and Video Hooks
 * فایل: section01.php
 */
if ( ! defined( 'ABSPATH' ) ) exit;

// دریافت عنوان هوشمند:
if ( is_shop() && ! is_search() ) {
    $page_title = get_the_title( wc_get_page_id( 'shop' ) );
} elseif ( is_page() || is_front_page() ) {
    $page_title = get_the_title();
} else {
    $page_title = woocommerce_page_title( false );
}
?>

<?php if ( shortcode_exists( 'hook_intro' ) || shortcode_exists( 'hook_video' ) ) : ?>
<section class="arian-section section-intro-media" aria-labelledby="arian-section01-title">

    <div class="intro-media-wrapper">

        <?php /* باکس اول: متن */ ?>
        <?php if ( shortcode_exists( 'hook_intro' ) ) : ?>
            <div class="intro-content-box">
                <h1 id="arian-section01-title" class="section-col-title">
                    <?php echo esc_html( $page_title ); ?>
                </h1>
                <div class="intro-hook-content">
                    <?php echo do_shortcode( '[hook_intro]' ); ?>
                </div>
            </div>
        <?php endif; ?>

        <?php /* باکس دوم: اهداف و مزایا */ ?>
        <div class="intro-middle-space">
            <div class="about-features-inline">
                <h2 class="section-col-title">اهداف و مزایای ما</h2>
                <ul class="features-list">
                    <li>شبکه تأمین گسترده و تیمی مجرب در واردات</li>
                    <li>ارائه کالاهای پرفروش و هم‌راستا با ترندهای جهانی</li>
                    <li>تأمین نیاز بازار با قیمت رقابتی با حذف واسطه‌ها</li>
                    <li>تمرکز بر رضایت مشتریان در سراسر منطقه</li>
                </ul>
            </div>
        </div>

        <?php /* باکس سوم: ویدیو */ ?>
        <?php if ( shortcode_exists( 'hook_video' ) ) : ?>
            <div class="intro-video-box" aria-label="<?php esc_attr_e( 'Category Video', 'arian' ); ?>">
                <h2 class="section-col-title">درباره ما</h2>
                <div class="video-content-wrapper">
                    <?php echo do_shortcode( '[hook_video]' ); ?>
                </div>
            </div>
        <?php endif; ?>

    </div>

</section>
<?php endif; ?>