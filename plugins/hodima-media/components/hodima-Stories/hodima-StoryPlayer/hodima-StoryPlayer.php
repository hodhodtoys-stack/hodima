<?php
/**
 * استوری‌ها — پخش‌کننده پاپ‌آپ و راه‌انداز پخش‌کننده کوچک.
 *
 * @package Hodima_Media
 */

declare(strict_types=1);

namespace Hodima\Stories\StoryPlayer;

use Hodima\Stories\Analytics_API;

defined( 'ABSPATH' ) || exit;

/**
 * پخش‌کننده استوری.
 */
class Core {

	/** نسخه پشتیبان فایل‌ها. */
	public const VERSION = '2.7.0';

	/** آیا شورت‌کد در این صفحه اجرا شد (پنجره پخش لازم است)؟ */
	private static bool $needs_modal = false;

	/** هوک‌ها. */
	public static function init(): void {
		add_action( 'wp_enqueue_scripts', [ self::class, 'register_assets' ] );
		add_action( 'wp_footer', [ self::class, 'render_modal_html' ] );
		// روی همه صفحه‌ها: لینک محصول استوری به هر صفحه‌ای می‌رود و پخش‌کننده کوچک همان‌جا ادامه می‌دهد
		add_action( 'wp_footer', [ self::class, 'print_mini_player_bootstrap' ], 99 );
	}

	/** ثبت فایل‌های پخش‌کننده. */
	public static function register_assets(): void {
		$base_url = HODIMA_MEDIA_URL . '/components/hodima-Stories/hodima-StoryPlayer/';

		wp_register_style( 'hodima-story-player', $base_url . 'hodima-player.css', [], self::file_version( 'hodima-player.css' ) );
		wp_register_script(
			'hodima-story-player',
			$base_url . 'hodima-player.js',
			[],
			self::file_version( 'hodima-player.js' ),
			[
				'in_footer' => true,
				'strategy'  => 'defer',
			]
		);

		wp_localize_script(
			'hodima-story-player',
			'HS_PLAYER_STR',
			[
				'playHint'    => __( 'برای پخش ویدیو روی دکمه پخش بزنید.', 'hodima' ),
				'playError'   => __( 'پخش ویدیو ممکن نیست.', 'hodima' ),
				'copySuccess' => __( 'لینک استوری کپی شد.', 'hodima' ),
				'copyError'   => __( 'کپی لینک انجام نشد.', 'hodima' ),
				'shareError'  => __( 'اشتراک‌گذاری انجام نشد.', 'hodima' ),
				'playlistEnd' => __( 'استوری‌ها به پایان رسید.', 'hodima' ),
				'ctaDefault'  => __( 'مشاهده محصول', 'hodima' ),
				'mutedHint'   => __( 'برای شنیدن صدا، دکمه صدا را بزنید.', 'hodima' ),
				/* translators: %s: شماره استوری */
				'storyN'      => __( 'استوری %s', 'hodima' ),
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( Analytics_API::NONCE_ACTION ),
				'selectors'   => [
					'storyLink'     => '.story-link',
					'playlistScope' => '[data-story-group], .section-stories',
				],
				'settings'    => [
					'autoplayNext'    => true,
					'loopPlaylist'    => false,
					'tapNavigation'   => true,
					'swipeNavigation' => true,
					'preloadAdjacent' => true,
				],
			]
		);
	}

	/** بارگذاری پخش‌کننده در این صفحه (شورت‌کد صدا می‌زند). */
	public static function enable(): void {
		self::$needs_modal = true;
		wp_enqueue_style( 'hodima-story-player' );
		wp_enqueue_script( 'hodima-story-player' );
	}

	/** نسخه فایل = زمان تغییر. */
	private static function file_version( string $file ): string {
		$path = HODIMA_MEDIA_DIR . '/components/hodima-Stories/hodima-StoryPlayer/' . $file;
		return file_exists( $path ) ? (string) filemtime( $path ) : self::VERSION;
	}

	/**
	 * راه‌انداز پخش‌کننده کوچک — چند صد بایت درون‌خطی به جای دو فایل روی
	 * همه صفحه‌ها: فقط اگر رکورد sessionStorage (کلیک روی لینک محصول
	 * استوری) باشد، CSS و بعد JS پخش‌کننده کوچک بارگذاری می‌شوند.
	 */
	public static function print_mini_player_bootstrap(): void {
		if ( is_admin() ) {
			return;
		}

		$base = HODIMA_MEDIA_URL . '/components/hodima-Stories/hodima-StoryPlayer/';
		$css  = esc_url_raw( $base . 'hodima-mini-player.css?ver=' . self::file_version( 'hodima-mini-player.css' ) );
		$js   = esc_url_raw( $base . 'hodima-mini-player.js?ver=' . self::file_version( 'hodima-mini-player.js' ) );
		$str  = [ 'close' => __( 'بستن', 'hodima' ) ];
		$flag = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG;

		wp_print_inline_script_tag(
			"(function(){try{if(!sessionStorage.getItem('hs_mini_player'))return;}catch(e){return;}"
			. 'window.HS_MINI_STR=' . wp_json_encode( $str, $flag ) . ';'
			. "var l=document.createElement('link');l.rel='stylesheet';l.href=" . wp_json_encode( $css, $flag ) . ';'
			. "l.onload=l.onerror=function(){var s=document.createElement('script');s.src=" . wp_json_encode( $js, $flag ) . ';document.body.appendChild(s);};'
			. 'document.head.appendChild(l);})();',
			[ 'id' => 'hs-mini-bootstrap' ]
		);
	}

	/**
	 * نشانه‌گذاری پنجره پخش.
	 *
	 * Aria-hidden هنگام باز شدن false می‌شود (باگ قبلی: همیشه true می‌ماند
	 * و صفحه‌خوان کل پنجره باز را نادیده می‌گرفت).
	 */
	public static function render_modal_html(): void {
		if ( ! self::$needs_modal ) {
			return;
		}
		?>
		<div id="hs-video-modal" class="hs-modal" aria-hidden="true" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'پخش کننده استوری', 'hodima' ); ?>">
			<div class="hs-modal__overlay" data-close="1"></div>
			<div class="hs-modal__content" role="document" tabindex="-1">
				<button class="hs-modal__close hs-flex-center" type="button" aria-label="<?php esc_attr_e( 'بستن', 'hodima' ); ?>">
					<span aria-hidden="true">&times;</span>
				</button>

				<div class="hs-player is-loading">
					<div class="hs-loader hs-flex-center" aria-hidden="true"></div>
					<div class="hs-topbar">
						<div class="hs-progress-wrap" role="group" aria-label="<?php esc_attr_e( 'پیشرفت استوری‌ها', 'hodima' ); ?>"></div>
						<div class="hs-meta">
							<button class="hs-mute-toggle hs-flex-center" type="button" aria-pressed="false" aria-label="<?php esc_attr_e( 'بی‌صدا', 'hodima' ); ?>">
								<svg class="hs-icon-sound" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M11 5 6 9H2v6h4l5 4z"/><path d="M15.5 8.5a5 5 0 0 1 0 7M19 5a10 10 0 0 1 0 14"/></svg>
								<svg class="hs-icon-muted" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M11 5 6 9H2v6h4l5 4z"/><path d="m22 9-6 6M16 9l6 6"/></svg>
							</button>
							<div class="hs-meta__text">
								<div class="hs-title" id="hs-story-title"></div>
								<div class="hs-subtitle" id="hs-story-position"></div>
							</div>
						</div>
					</div>

					<div class="hs-stage">
						<video id="hs-modal-video" playsinline preload="metadata" webkit-playsinline></video>

						<button class="hs-nav hs-flex-center hs-nav--prev" type="button" aria-label="<?php esc_attr_e( 'استوری قبلی', 'hodima' ); ?>">
							<span aria-hidden="true">&#10094;</span>
						</button>
						<button class="hs-nav hs-flex-center hs-nav--next" type="button" aria-label="<?php esc_attr_e( 'استوری بعدی', 'hodima' ); ?>">
							<span aria-hidden="true">&#10095;</span>
						</button>

						<div class="hs-tap-zone hs-tap-zone--left" data-tap="next" aria-hidden="true"></div>
						<div class="hs-tap-zone hs-tap-zone--center" data-tap="toggle" aria-hidden="true"></div>
						<div class="hs-tap-zone hs-tap-zone--right" data-tap="prev" aria-hidden="true"></div>

						<div class="hs-center-control hs-flex-center">
							<button class="hs-play-toggle hs-flex-center" type="button" aria-label="<?php esc_attr_e( 'پخش / توقف', 'hodima' ); ?>">
								<span class="hs-play-icon hs-icon-play" aria-hidden="true">▶</span>
								<span class="hs-play-icon hs-icon-pause" aria-hidden="true">❚❚</span>
							</button>
						</div>
					</div>

					<div class="hs-bottombar">
						<div class="hs-message" id="hs-player-message" aria-live="polite"></div>

						<div class="hs-actions hs-flex-center">
							<div class="hs-action-item hs-flex-center hs-view-stat" title="<?php esc_attr_e( 'بازدید', 'hodima' ); ?>">
								<span><?php esc_html_e( 'بازدید', 'hodima' ); ?></span>
								<span id="hs-view-count">0</span>
							</div>
							<button class="hs-action-item hs-flex-center hs-share-btn" type="button" aria-label="<?php esc_attr_e( 'اشتراک گذاری', 'hodima' ); ?>">
								<span><?php esc_html_e( 'اشتراک', 'hodima' ); ?></span>
								<span class="hs-icon hs-share-icon" aria-hidden="true">⤴</span>
							</button>
							<a href="#" id="hs-cta-box" class="hs-cta-box" hidden>
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
