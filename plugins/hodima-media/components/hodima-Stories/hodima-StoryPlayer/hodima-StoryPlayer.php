<?php
declare(strict_types=1);

namespace Hodima\Stories\StoryPlayer;

defined('ABSPATH') || exit;

class Core {
    public const VERSION = '2.6.1';
    private static bool $needs_modal = false;

    public static function init(): void {
        add_action('wp_enqueue_scripts', [__CLASS__, 'register_assets']);
        add_action('wp_footer', [__CLASS__, 'render_modal_html']);
        // Loaded on every front-end page (not just pages with the stories
        // shortcode) because the CTA link can lead to any page on the site
        // — that destination page is where the floating mini-player needs
        // to pick up and keep playing. The script itself is tiny and is a
        // complete no-op when there's nothing pending, so the sitewide cost
        // is negligible.
        add_action('wp_footer', [__CLASS__, 'print_mini_player_bootstrap'], 99);
    }

    public static function register_assets(): void {
        $base_url  = get_template_directory_uri() . '/components/hodima-Stories/hodima-StoryPlayer/';

        wp_register_style('hodima-story-player', $base_url . 'hodima-player.css', [], self::file_version('hodima-player.css'));
        wp_register_script('hodima-story-player', $base_url . 'hodima-player.js', [], self::file_version('hodima-player.js'), true);

        wp_localize_script('hodima-story-player', 'HS_PLAYER_STR', [
            'playHint'        => __('برای پخش ویدیو روی دکمه پخش بزنید.', 'hodima'),
            'playError'       => __('پخش ویدیو ممکن نیست.', 'hodima'),
            'copySuccess'     => __('لینک استوری کپی شد.', 'hodima'),
            'copyError'       => __('کپی لینک انجام نشد.', 'hodima'),
            'shareError'      => __('اشتراک‌گذاری انجام نشد.', 'hodima'),
            'playlistEnd'     => __('استوری‌ها به پایان رسید.', 'hodima'),
            'ctaDefault'      => __('مشاهده محصول', 'hodima'),
            'ajaxUrl'         => admin_url('admin-ajax.php'),
            'nonce'           => wp_create_nonce('hs_track_nonce'),
            'selectors'       => [
                'storyLink'     => '.story-link',
                'playlistScope' => '[data-story-group], .section-stories',
            ],
            'settings'        => [
                'autoplayNext'    => true,
                'loopPlaylist'    => false,
                'tapNavigation'   => true,
                'swipeNavigation' => true,
                'preloadAdjacent' => true
            ],
        ]);
    }

    public static function enable(): void {
        self::$needs_modal = true;
        wp_enqueue_style('hodima-story-player');
        wp_enqueue_script('hodima-story-player');
    }

    private static function file_version(string $file): string {
        $path = get_template_directory() . '/components/hodima-Stories/hodima-StoryPlayer/' . $file;
        return file_exists($path) ? (string) filemtime($path) : self::VERSION;
    }

    /**
     * راه‌انداز مینی‌پلیر — حدود ۴۰۰ بایت درون‌خطی به جای دو فایل.
     *
     * نسخه قبلی CSS و JS مینی‌پلیر را روی *هر صفحه سایت* بارگذاری می‌کرد
     * (CSS هم مسدودکننده رندر بود)، در حالی که مینی‌پلیر فقط وقتی فعال
     * می‌شود که کاربر همین الان روی لینک محصول یک استوری کلیک کرده باشد.
     * حالا فقط وجود رکورد sessionStorage بررسی می‌شود؛ اگر بود، همان دو
     * فایل بارگذاری می‌شوند (CSS اول، تا مینی‌پلیر بدون استایل ظاهر نشود).
     * منطق خود مینی‌پلیر دست‌نخورده است و رکورد را خودش مصرف می‌کند.
     */
    public static function print_mini_player_bootstrap(): void {
        if (is_admin()) return;

        $base = get_template_directory_uri() . '/components/hodima-Stories/hodima-StoryPlayer/';
        $css  = $base . 'hodima-mini-player.css?ver=' . self::file_version('hodima-mini-player.css');
        $js   = $base . 'hodima-mini-player.js?ver=' . self::file_version('hodima-mini-player.js');
        $str  = wp_json_encode(['close' => __('بستن', 'hodima')], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
        ?>
<script id="hs-mini-bootstrap">(function(){try{if(!sessionStorage.getItem('hs_mini_player'))return;}catch(e){return;}window.HS_MINI_STR=<?php echo $str; ?>;var l=document.createElement('link');l.rel='stylesheet';l.href=<?php echo wp_json_encode(esc_url_raw($css)); ?>;l.onload=l.onerror=function(){var s=document.createElement('script');s.src=<?php echo wp_json_encode(esc_url_raw($js)); ?>;document.body.appendChild(s);};document.head.appendChild(l);})();</script>
        <?php
    }

    public static function render_modal_html(): void {
        if (!self::$needs_modal) return;
        ?>
        <div id="hs-video-modal" class="hs-modal" aria-hidden="true" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e('پخش کننده استوری', 'hodima'); ?>">
            <div class="hs-modal__overlay" data-close="1"></div>
            <div class="hs-modal__content" role="document" tabindex="-1">
                <button class="hs-modal__close hs-flex-center" type="button" aria-label="<?php esc_attr_e('بستن', 'hodima'); ?>">
                    <span aria-hidden="true">&times;</span>
                </button>

                <div class="hs-player is-loading">
                    <div class="hs-loader hs-flex-center" aria-hidden="true"></div>
                    <div class="hs-topbar">
                        <div class="hs-progress-wrap" aria-label="<?php esc_attr_e('پیشرفت استوری‌ها', 'hodima'); ?>"></div>
                        <div class="hs-meta">
                            <div class="hs-title" id="hs-story-title"></div>
                            <div class="hs-subtitle" id="hs-story-position"></div>
                        </div>
                    </div>

                    <div class="hs-stage">
                        <video id="hs-modal-video" playsinline preload="metadata" webkit-playsinline></video>

                        <button class="hs-nav hs-flex-center hs-nav--prev" type="button" aria-label="<?php esc_attr_e('استوری قبلی', 'hodima'); ?>">
                            <span aria-hidden="true">&#10094;</span>
                        </button>
                        <button class="hs-nav hs-flex-center hs-nav--next" type="button" aria-label="<?php esc_attr_e('استوری بعدی', 'hodima'); ?>">
                            <span aria-hidden="true">&#10095;</span>
                        </button>

                        <div class="hs-tap-zone hs-tap-zone--left" data-tap="next" aria-hidden="true"></div>
                        <div class="hs-tap-zone hs-tap-zone--center" data-tap="toggle" aria-hidden="true"></div>
                        <div class="hs-tap-zone hs-tap-zone--right" data-tap="prev" aria-hidden="true"></div>

                        <div class="hs-center-control hs-flex-center">
                            <button class="hs-play-toggle hs-flex-center" type="button" aria-label="<?php esc_attr_e('پخش / توقف', 'hodima'); ?>">
                                <span class="hs-play-icon hs-icon-play" aria-hidden="true">▶</span>
                                <span class="hs-play-icon hs-icon-pause" aria-hidden="true">❚❚</span>
                            </button>
                        </div>
                    </div>

                    <div class="hs-bottombar">
                        <div class="hs-message" id="hs-player-message" aria-live="polite"></div>

                        <div class="hs-actions hs-flex-center">
                            <div class="hs-action-item hs-flex-center hs-view-stat" title="<?php esc_attr_e('بازدید', 'hodima'); ?>">
                                <span><?php esc_html_e('بازدید', 'hodima'); ?></span>
                                <span id="hs-view-count">0</span>
                            </div>
                            <button class="hs-action-item hs-flex-center hs-share-btn" type="button" aria-label="<?php esc_attr_e('اشتراک گذاری', 'hodima'); ?>">
                                <span><?php esc_html_e('اشتراک', 'hodima'); ?></span>
                                <span class="hs-icon hs-share-icon" aria-hidden="true">⤴</span>
                            </button>
                            <a href="#" id="hs-cta-box" class="hs-cta-box" style="display:none;">
                                <span class="hs-cta-text" id="hs-cta-text"></span>
                                <span class="hs-cta-arrow" aria-hidden="true">&#8592;</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="hs-toast" id="hs-player-toast" aria-live="polite" aria-atomic="true"></div>
        </div>
        <?php
    }
}
