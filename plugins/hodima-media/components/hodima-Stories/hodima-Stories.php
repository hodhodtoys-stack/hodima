<?php
/**
 * Hodima Stories - Admin Panel + Shortcode (Video Stories Only)
 */

declare(strict_types=1);

namespace Hodima\Stories;

use Hodima\Stories\Analytics\WP_Story_Analytics;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/hodima-analytics.php';
require_once __DIR__ . '/hodima-StoryPlayer/hodima-StoryPlayer.php';

class Core {
    public const OPTION_NAME   = 'hodima_stories_items';
    public const SETTINGS_NAME = 'hodima_stories_settings';
    public const MAX_ITEMS     = 50;
    public const VERSION       = '3.0.0';

    /**
     * تنظیمات نمایش.
     *
     * seen_to_end: استوری‌های دیده‌شده به انتهای ردیف بروند (رفتار نسخه
     * قبلی که همیشه روشن بود). پیش‌فرض خاموش: ترتیب فرانت *دقیقا* همان
     * ترتیبی است که در پنل چیده شده.
     */
    public static function settings(): array {
        $saved = get_option( self::SETTINGS_NAME, [] );
        return wp_parse_args( is_array( $saved ) ? $saved : [], [ 'seen_to_end' => false ] );
    }

    /** آدرس و نسخه یک فایل (نسخه = زمان تغییر؛ نسخه ثابت به‌روزرسانی را پنهان می‌کرد). */
    public static function asset( string $relative ): array {
        $path = HODIMA_MEDIA_DIR . '/components/hodima-Stories/' . $relative;
        return [
            HODIMA_MEDIA_URL . '/components/hodima-Stories/' . $relative,
            file_exists( $path ) ? (string) filemtime( $path ) : self::VERSION,
        ];
    }

    public static function init(): void {
        Admin::init();
        Frontend::init();
        Analytics_API::init();
        \Hodima\Stories\StoryPlayer\Core::init();
    }
}

class Helpers {
    public static function allowed_video_exts(): array {
        return ['mp4'];
    }

    public static function is_valid_video_url( string $url ): bool {
        $url = trim( $url );
        if ( $url === '' || ! filter_var( $url, FILTER_VALIDATE_URL ) ) return false;
        $scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
        if ( ! in_array( $scheme, [ 'http', 'https' ], true ) ) return false;
        $path = (string) wp_parse_url( $url, PHP_URL_PATH );
        $ext  = strtolower( (string) ( wp_check_filetype( $path )['ext'] ?? '' ) );
        return in_array( $ext, self::allowed_video_exts(), true );
    }

    public static function normalize_url( string $url ): string {
        $parts = wp_parse_url( esc_url_raw( $url ) );
        if ( ! is_array( $parts ) ) return esc_url_raw( $url );
        $scheme = strtolower( $parts['scheme'] ?? 'https' );
        $host   = strtolower( $parts['host'] ?? '' );
        $port   = isset( $parts['port'] ) ? ':' . (int) $parts['port'] : '';
        $path   = $parts['path'] ?? '';
        return "{$scheme}://{$host}{$port}{$path}";
    }

    public static function validate_cover_id( $id ): int {
        $id = absint( $id );
        return ( $id > 0 && wp_attachment_is_image( $id ) ) ? $id : 0;
    }

    /**
     * Validates the optional CTA (call-to-action) link. Unlike the story
     * video link, this field is optional — an empty value is valid and
     * simply means "no CTA box for this story".
     */
    public static function is_valid_link_url( string $url ): bool {
        $url = trim( $url );
        if ( $url === '' ) return true;
        if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) return false;
        $scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
        return in_array( $scheme, [ 'http', 'https' ], true );
    }

    public static function get_placeholder_cover(): string {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120"><rect width="100%" height="100%" fill="#f2f2f2"/><path d="M20 90l25-30 20 24 15-18 20 24" fill="none" stroke="#bbb" stroke-width="6"/><circle cx="42" cy="40" r="10" fill="#ccc"/></svg>';
        return 'data:image/svg+xml;base64,' . base64_encode( $svg );
    }

    public static function get_cover_url( int $id, string $size = 'thumbnail' ): string {
        $url = $id ? wp_get_attachment_image_url( $id, $size ) : '';
        return $url ?: self::get_placeholder_cover();
    }

    /**
     * New: lightweight server-side rate limit for the analytics AJAX
     * endpoints. Previously the only protection was a page-wide nonce,
     * which does nothing to stop a script from replaying the same request
     * indefinitely. This caps requests per client IP in a rolling window.
     *
     * Uses REMOTE_ADDR only (not X-Forwarded-For) since that header can be
     * spoofed by the client; behind a trusted proxy/CDN this should read
     * the proxy's verified client-IP header instead.
     */
    public static function check_rate_limit( int $limit = 30, int $window = 60 ): bool {
        /*
         * پشت کلادفلر REMOTE_ADDR آدرس سرور کلادفلر است، نه بازدیدکننده.
         * نسخه قبلی همه کاربران را در یک سهمیه ۳۰ درخواست در دقیقه جمع
         * می‌کرد؛ در ساعات شلوغ ثبت بازدید برای بیشتر کاربران رد می‌شد.
         * تابع مرکزی قالب فقط وقتی به هدر کلادفلر اعتماد می‌کند که اتصال
         * واقعا از محدوده IP کلادفلر آمده باشد.
         */
        $key = function_exists( 'hodima_rate_limit_key' )
            ? hodima_rate_limit_key( 'hs_rl_' )
            : 'hs_rl_' . md5( (string) ( $_SERVER['REMOTE_ADDR'] ?? 'unknown' ) );

        $count = (int) get_transient( $key );
        if ( $count >= $limit ) {
            return false;
        }

        set_transient( $key, $count + 1, $window );
        return true;
    }
}

class Analytics_API {
    public static function init(): void {
        add_action( 'wp_ajax_hs_track_view', [ __CLASS__, 'track_view' ] );
        add_action( 'wp_ajax_nopriv_hs_track_view', [ __CLASS__, 'track_view' ] );
        add_action( 'wp_ajax_hs_track_share', [ __CLASS__, 'track_share' ] );
        add_action( 'wp_ajax_nopriv_hs_track_share', [ __CLASS__, 'track_share' ] );
    }

    private static function verify_request(): int {
        check_ajax_referer( 'hs_track_nonce', 'security' );

        if ( ! Helpers::check_rate_limit() ) {
            wp_send_json_error( [ 'message' => 'Too many requests', 'recorded' => false ] );
        }

        $story_id = isset( $_POST['story_id'] ) ? absint( $_POST['story_id'] ) : 0;

        if ( $story_id <= 0 || ! class_exists( WP_Story_Analytics::class ) ) {
            wp_send_json_error( [ 'message' => 'Invalid ID or DB missing', 'recorded' => false ] );
        }
        return $story_id;
    }

    public static function track_view(): void {
        $id = self::verify_request();
        wp_send_json_success( [ 'recorded' => WP_Story_Analytics::record_view( $id ) ] );
    }

    public static function track_share(): void {
        $id = self::verify_request();
        wp_send_json_success( [ 'recorded' => WP_Story_Analytics::record_share( $id ) ] );
    }
}

class Admin {
    public static function init(): void {
        add_action( 'admin_menu', [ __CLASS__, 'add_menu' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
        add_action( 'admin_init', [ __CLASS__, 'handle_save' ] );
    }

    public static function add_menu(): void {
        add_menu_page( __('پنل استوری', 'hodima'), __('پنل استوری', 'hodima'), 'manage_options', 'hodima-stories', [ __CLASS__, 'render_page' ], 'dashicons-format-gallery', 40 );
    }

    public static function enqueue_assets( $hook ): void {
        if ( $hook !== 'toplevel_page_hodima-stories' ) return;
        wp_enqueue_media();
        [ $css_url, $css_ver ] = Core::asset( 'hodima-admin.css' );
        [ $js_url, $js_ver ]   = Core::asset( 'hodima-admin.js' );
        wp_enqueue_style( 'hodima-stories-admin', $css_url, [], $css_ver );
        wp_enqueue_script( 'hodima-stories-admin', $js_url, [], $js_ver, true );
        wp_localize_script( 'hodima-stories-admin', 'HS_ADMIN', [
            'maxItems' => Core::MAX_ITEMS,
            'strings'  => [
                'limit'       => __( 'حداکثر ۵۰ آیتم مجاز است.', 'hodima' ),
                'selectCover' => __( 'انتخاب کاور', 'hodima' ),
                'remove'      => __( 'حذف استوری', 'hodima' ),
                'title'       => __( 'عنوان استوری', 'hodima' ),
                'link'        => __( 'آدرس ویدیو', 'hodima' ),
                'ctaLink'     => __( 'لینک محصول', 'hodima' ),
                'ctaText'     => __( 'متن دکمه', 'hodima' ),
                'confirmDel'  => __( 'این استوری حذف شود؟ با ذخیره، آمار بازدید آن هم پاک می‌شود.', 'hodima' ),
                'dragHint'    => __( 'برای جابه‌جایی بکشید', 'hodima' ),
                'moveUp'      => __( 'انتقال به بالا', 'hodima' ),
                'moveDown'    => __( 'انتقال به پایین', 'hodima' ),
                'unsaved'     => __( 'تغییرات ذخیره نشده‌اند.', 'hodima' ),
            ],
            'placeholderImg' => Helpers::get_placeholder_cover(),
            'mediaTitle'     => __( 'انتخاب کاور', 'hodima' ),
            'mediaBtn'       => __( 'انتخاب', 'hodima' )
        ] );
    }

    /**
     * Bug fix: this used to process the save inline with no redirect, so
     * refreshing the admin page after saving would resubmit the form (the
     * browser's "confirm form resubmission" prompt). It now redirects
     * (Post/Redirect/Get) and passes the notice through a short-lived
     * per-user transient instead of the settings-errors global.
     */
    public static function handle_save(): void {
        $nonce = isset( $_POST['hodima_stories_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['hodima_stories_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $nonce, 'hodima_stories_save' ) ) return;
        if ( ! current_user_can( 'manage_options' ) ) return;

        /*
         * ترتیب ذخیره = ترتیب ردیف‌ها در فرم. PHP آرایه را به ترتیب ظاهر شدن
         * فیلدها در بدنه درخواست می‌سازد، و فرم فیلدها را به ترتیب DOM ارسال
         * می‌کند؛ پس هر جابه‌جایی در پنل عینا ذخیره می‌شود.
         */
        $raw   = isset( $_POST['hodima_stories'] ) ? (array) wp_unslash( $_POST['hodima_stories'] ) : [];
        $items = [];
        $seen_links = [];

        foreach ( $raw as $row ) {
            // Defensive: a malformed/spoofed POST could send a non-array
            // value here (e.g. hodima_stories[0]=foo instead of an array),
            // which would throw on array access below.
            if ( ! is_array( $row ) ) continue;

            $link = esc_url_raw( $row['link'] ?? '' );
            if ( ! Helpers::is_valid_video_url( $link ) ) continue;

            $title    = sanitize_text_field( $row['title'] ?? '' );
            $cover_id = Helpers::validate_cover_id( $row['cover_id'] ?? 0 );
            $norm     = Helpers::normalize_url( $link );
            $uid      = isset( $row['uid'] ) && ! empty( $row['uid'] ) ? absint( $row['uid'] ) : hexdec( substr( md5( uniqid( (string) wp_rand(), true ) ), 0, 8 ) );

            // CTA (call-to-action) box: optional link + button text shown
            // above the view/share bar in the player. If the URL is missing
            // or invalid, both fields are cleared so nothing renders on the
            // front-end.
            $cta_link = esc_url_raw( $row['cta_link'] ?? '' );
            if ( ! Helpers::is_valid_link_url( $cta_link ) ) $cta_link = '';
            $cta_text = $cta_link !== '' ? sanitize_text_field( $row['cta_text'] ?? '' ) : '';

            // Bug fix: a "same cover image used twice" check used to silently
            // drop the second row. Reusing one cover image across several
            // stories is a legitimate, common case — only exact duplicate
            // video links are still rejected.
            if ( isset( $seen_links[ $norm ] ) ) continue;
            if ( count( $items ) >= Core::MAX_ITEMS ) break;

            $items[] = [
                'uid'      => $uid,
                'title'    => $title,
                'link'     => $link,
                'cover_id' => $cover_id,
                'cta_link' => $cta_link,
                'cta_text' => $cta_text,
            ];
            $seen_links[ $norm ] = true;
        }

        update_option( Core::OPTION_NAME, $items, false );

        update_option( Core::SETTINGS_NAME, [
            'seen_to_end' => ! empty( $_POST['hodima_stories_seen_to_end'] ),
        ], false );

        if ( class_exists( WP_Story_Analytics::class ) ) {
            WP_Story_Analytics::cleanup_deleted_stories( array_column( $items, 'uid' ) );
        }

        set_transient( self::notice_key(), [
            'type'    => 'updated',
            'message' => __( 'تغییرات با موفقیت ذخیره شد.', 'hodima' ),
        ], 30 );

        wp_safe_redirect( add_query_arg( [ 'page' => 'hodima-stories', 'hs_saved' => '1' ], admin_url( 'admin.php' ) ) );
        exit;
    }

    private static function notice_key(): string {
        return 'hodima_stories_notice_' . get_current_user_id();
    }

    public static function render_page(): void {
        $items  = get_option( Core::OPTION_NAME, [] );
        $notice = get_transient( self::notice_key() );
        if ( $notice ) {
            delete_transient( self::notice_key() );
        }
        ?>
        <div class="wrap hdn-admin-wrapper anim-fade_in">
            <div class="hdn-header hd-shadow-soft">
                <div class="hdn-header-info">
                    <div class="hdn-icon"><span class="dashicons dashicons-format-gallery hdn-header-icon"></span></div>
                    <div>
                        <h1><?php esc_html_e( 'تنظیمات استوری‌های ویدیویی', 'hodima' ); ?></h1>
                        <p><?php esc_html_e( 'لینک ویدیوها (mp4) و تصویر کاور را برای نمایش پاپ‌آپ استوری وارد کنید.', 'hodima' ); ?></p>
                    </div>
                </div>
                <div class="hdn-version-badge">V <?php echo esc_html( Core::VERSION ); ?></div>
            </div>

            <form method="post" id="hodima-stories-form">
                <?php wp_nonce_field( 'hodima_stories_save', 'hodima_stories_nonce' ); ?>
                <div class="hdn-card hd-shadow-soft">
                    <h2 class="hdn-card-title"><?php esc_html_e( 'پنل تنظیمات', 'hodima' ); ?></h2>

                    <div class="hdn-actions-bar">
                        <button type="button" class="hdn-btn hdn-btn-outline" id="hs-add-new">+ <?php esc_html_e( 'افزودن استوری جدید', 'hodima' ); ?></button>
                        <?php if ( $notice ) : ?>
                            <div class="hdn-actions-msg">
                                <span class="hdn-msg <?php echo esc_attr( $notice['type'] ); ?>">
                                    <span class="hdn-msg-text"><?php echo esc_html( $notice['message'] ); ?></span>
                                    <button type="button" class="hdn-msg-close" aria-label="<?php esc_attr_e( 'بستن', 'hodima' ); ?>">×</button>
                                </span>
                            </div>
                        <?php endif; ?>
                        <button type="submit" class="hdn-btn hdn-btn-primary">
                            <span class="dashicons dashicons-saved"></span>
                            <?php esc_html_e( 'ذخیره تنظیمات', 'hodima' ); ?>
                        </button>
                    </div>

                    <?php $settings = Core::settings(); ?>
                    <label class="hs-setting">
                        <input type="checkbox" name="hodima_stories_seen_to_end" value="1" <?php checked( $settings['seen_to_end'] ); ?>>
                        <span>
                            <strong><?php esc_html_e( 'استوری‌های دیده‌شده به انتهای ردیف بروند', 'hodima' ); ?></strong>
                        </span>
                    </label>

                    <p class="hs-order-help"><?php esc_html_e( 'ترتیب نمایش در سایت همان ترتیب زیر است. با کشیدن دستگیره یا دکمه‌های بالا و پایین جابه‌جا کنید و در پایان «ذخیره تنظیمات» را بزنید.', 'hodima' ); ?></p>

                    <div id="hodima-stories-list">
                        <?php foreach ( $items as $i => $item ) :
                            $cover_id  = Helpers::validate_cover_id( $item['cover_id'] ?? 0 );
                            $cover_url = Helpers::get_cover_url( $cover_id, 'thumbnail' );
                            $uid       = esc_attr( $item['uid'] ?? '' );
                        ?>
                        <div class="hodima-story-row anim-slide_up">
                            <div class="hs-order-col">
                                <span class="hs-drag-handle" title="<?php esc_attr_e( 'برای جابه‌جایی بکشید', 'hodima' ); ?>" aria-hidden="true"><span class="dashicons dashicons-menu"></span></span>
                                <span class="hs-order-num"><?php echo (int) $i + 1; ?></span>
                                <button type="button" class="hs-move hs-move-up" aria-label="<?php esc_attr_e( 'انتقال به بالا', 'hodima' ); ?>"><span class="dashicons dashicons-arrow-up-alt2"></span></button>
                                <button type="button" class="hs-move hs-move-down" aria-label="<?php esc_attr_e( 'انتقال به پایین', 'hodima' ); ?>"><span class="dashicons dashicons-arrow-down-alt2"></span></button>
                            </div>
                            <input type="hidden" class="hs-uid-input" name="hodima_stories[<?php echo esc_attr( $i ); ?>][uid]" value="<?php echo $uid; ?>">
                            <div class="hdn-flex-col">
                                <input type="text" class="hs-title-input hdn-input" name="hodima_stories[<?php echo esc_attr( $i ); ?>][title]" value="<?php echo esc_attr( $item['title'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'عنوان استوری', 'hodima' ); ?>">
                            </div>
                            <div class="hdn-flex-col">
                                <input type="url" class="hs-link-input hdn-input" name="hodima_stories[<?php echo esc_attr( $i ); ?>][link]" value="<?php echo esc_url( $item['link'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'آدرس ویدیو', 'hodima' ); ?>">
                            </div>
                            <div class="hdn-flex-col hdn-cta-col">
                                <input type="url" class="hs-cta-link-input hdn-input" name="hodima_stories[<?php echo esc_attr( $i ); ?>][cta_link]" value="<?php echo esc_url( $item['cta_link'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'لینک مقصد', 'hodima' ); ?>">
                                <input type="text" class="hs-cta-text-input hdn-input" name="hodima_stories[<?php echo esc_attr( $i ); ?>][cta_text]" value="<?php echo esc_attr( $item['cta_text'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'متن دکمه', 'hodima' ); ?>">
                            </div>
                            <div class="hdn-flex-col">
                                <div class="hdn-media-container">
                                    <div class="hdn-img-preview-box"><img src="<?php echo esc_url( $cover_url ); ?>" alt="" class="hs-cover-preview"></div>
                                    <div class="hdn-media-actions">
                                        <input type="hidden" class="hs-cover-id" name="hodima_stories[<?php echo esc_attr( $i ); ?>][cover_id]" value="<?php echo esc_attr( $cover_id ); ?>">
                                        <button type="button" class="hdn-btn hdn-upload-btn hs-select-cover"><?php esc_html_e( 'انتخاب کاور', 'hodima' ); ?></button>
                                        <button type="button" class="hdn-btn hdn-remove-btn hs-remove-row"><?php esc_html_e( 'حذف', 'hodima' ); ?></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </form>
        </div>
        <?php
    }
}

class Frontend {
    public static function init(): void {
        add_action( 'wp_enqueue_scripts', [ __CLASS__, 'register_scripts' ] );
        add_shortcode( 'hodima-Stories', [ __CLASS__, 'render_shortcode' ] );
        add_filter( 'script_loader_tag', [ __CLASS__, 'defer_scripts' ], 10, 2 );
    }

    public static function register_scripts(): void {
        [ $css_url, $css_ver ] = Core::asset( 'hodima-front.css' );
        [ $js_url, $js_ver ]   = Core::asset( 'hodima-front.js' );
        wp_register_style( 'hodima-stories-front', $css_url, [], $css_ver );
        wp_register_script( 'hodima-stories-front', $js_url, [], $js_ver, true );
    }

    public static function defer_scripts( string $tag, string $handle ): string {
        if ( in_array( $handle, [ 'hodima-stories-front', 'hodima-story-player', 'hodima-mini-player' ], true ) ) {
            return str_replace( ' src', ' defer="defer" src', $tag );
        }
        return $tag;
    }

    public static function render_shortcode( $atts = [] ): string {
        self::enqueue_assets();
        \Hodima\Stories\StoryPlayer\Core::enable();

        $items = get_option( Core::OPTION_NAME, [] );
        if ( empty( $items ) || ! is_array( $items ) ) return '';

        // Bug fix: shuffle() used to randomize order on every render. Combined
        // with this project's full-page caching (Cloudflare/WP Rocket), that
        // "random" order gets baked into the cached HTML and every visitor
        // sees the same frozen order until the cache is purged — the
        // randomization was silently defeating itself. Per the requested
        // behavior, items now render in stored order (admin inserts new
        // stories at the top, so this is newest-first) and any "freshness"
        // logic is handled client-side instead (see hodima-front.js).
        $story_ids = array_column( $items, 'uid' );
        $db_stats  = class_exists( WP_Story_Analytics::class ) ? WP_Story_Analytics::get_stats_bulk( $story_ids ) : [];

        ob_start();
        ?>
        <?php
        /*
         * کلاس hs-loading (opacity:0 تا اجرای JS) حذف شد.
         * نوار استوری معمولا بالای صفحه اصلی است؛ پنهان ماندنش تا اجرای JS
         * هم LCP را عقب می‌انداخت و هم با حالت Delay JS لایت‌اسپید — که
         * اسکریپت را بعد از DOMContentLoaded اجرا می‌کند — نوار *هرگز*
         * نمایش داده نمی‌شد.
         */
        $seen_to_end = Core::settings()['seen_to_end'];
        $position    = 0;
        ?>
        <section class="arian-section section-stories is-ready" data-nosnippet data-seen-to-end="<?php echo $seen_to_end ? '1' : '0'; ?>">
            <div class="hs-scroll-indicator" aria-hidden="true">
                <span class="hs-scroll-indicator__circle">
                    <span class="hs-scroll-indicator__arrow"></span>
                </span>
            </div>

            <div class="arian-scroller story-wrapper" id="story-container">
                <?php foreach ( $items as $story ) :
                    $link = $story['link'] ?? '';
                    if ( ! Helpers::is_valid_video_url( $link ) ) continue;

                    $uid        = absint( $story['uid'] ?? 0 );
                    $title      = $story['title'] ?? '';
                    $cover_id   = Helpers::validate_cover_id( $story['cover_id'] ?? 0 );
                    $poster_img = Helpers::get_cover_url( $cover_id, 'large' );
                    $img        = Helpers::get_cover_url( $cover_id, function_exists( 'wc_get_image_size' ) ? 'woocommerce_thumbnail' : 'thumbnail' );
                    $safe_title = $title !== '' ? esc_html( $title ) : esc_html__( 'مشاهده', 'hodima' );
                    $stats      = $db_stats[ $uid ] ?? [ 'views' => 0 ];
                    $cta_link   = $story['cta_link'] ?? '';
                    $cta_text   = $story['cta_text'] ?? '';
                ?>
                <div class="story-item">
                    <a href="<?php echo esc_url( $link ); ?>" class="story-link" rel="nofollow noopener"
                       data-video="<?php echo esc_url( $link ); ?>"
                       data-poster="<?php echo esc_url( $poster_img ); ?>"
                       data-story-id="<?php echo $uid; ?>"
                       data-views="<?php echo esc_attr( $stats['views'] ); ?>"
                       data-title="<?php echo esc_attr( $title ); ?>"
                       <?php if ( $cta_link !== '' ) : ?>
                       data-cta-link="<?php echo esc_url( $cta_link ); ?>"
                       data-cta-text="<?php echo esc_attr( $cta_text ); ?>"
                       <?php endif; ?>>
                        <div class="story-ring">
                            <?php
                            // چند کاور اول در دید اولیه‌اند؛ lazy آن‌ها را دیرتر از لازم نمایش می‌داد
                            $eager = ( ++$position <= 6 );
                            ?>
                            <img src="<?php echo esc_url( $img ); ?>" alt="<?php echo esc_attr( $safe_title ); ?>" loading="<?php echo $eager ? 'eager' : 'lazy'; ?>" width="75" height="75" decoding="async">
                        </div>
                        <span class="story-text"><?php echo $safe_title; ?></span>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }

    private static function enqueue_assets(): void {
        wp_enqueue_style( 'hodima-stories-front' );
        wp_enqueue_script( 'hodima-stories-front' );
    }
}

Core::init();
