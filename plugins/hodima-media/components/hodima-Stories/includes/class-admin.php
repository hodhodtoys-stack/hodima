<?php
/**
 * استوری‌ها — صفحه پیشخوان «پنل استوری» و ذخیره.
 *
 * @package Hodima_Media
 */

declare(strict_types=1);

namespace Hodima\Stories;

use Hodima\Stories\Analytics\WP_Story_Analytics;

defined( 'ABSPATH' ) || exit;

/**
 * پنل مدیریت استوری‌ها.
 */
class Admin {

	/** نامک صفحه (عوض نشود). */
	public const PAGE = 'hodima-stories';

	/** هوک‌ها. */
	public static function init(): void {
		add_action( 'admin_menu', [ self::class, 'add_menu' ] );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_assets' ] );
		add_action( 'admin_init', [ self::class, 'handle_save' ] );
	}

	/** منو. */
	public static function add_menu(): void {
		add_menu_page( __( 'پنل استوری', 'hodima' ), __( 'پنل استوری', 'hodima' ), 'manage_options', self::PAGE, [ self::class, 'render_page' ], 'dashicons-format-gallery', 40 );
	}

	/** فایل‌های صفحه (فقط همین صفحه). */
	public static function enqueue_assets( string $hook ): void {
		if ( 'toplevel_page_' . self::PAGE !== $hook ) {
			return;
		}
		wp_enqueue_media();
		[ $css_url, $css_ver ] = Core::asset( 'hodima-admin.css' );
		[ $js_url, $js_ver ]   = Core::asset( 'hodima-admin.js' );
		wp_enqueue_style( 'hodima-stories-admin', $css_url, [], $css_ver );
		wp_enqueue_script( 'hodima-stories-admin', $js_url, [], $js_ver, true );
		wp_localize_script(
			'hodima-stories-admin',
			'HS_ADMIN',
			[
				'maxItems'   => Core::MAX_ITEMS,
				'strings'    => [
					'limit'   => sprintf(
						/* translators: %s: بیشترین تعداد استوری */
						__( 'حداکثر %s استوری مجاز است.', 'hodima' ),
						number_format_i18n( Core::MAX_ITEMS )
					),
					'unsaved' => __( 'تغییرات ذخیره نشده‌اند.', 'hodima' ),
				],
				'mediaTitle' => __( 'انتخاب کاور', 'hodima' ),
				'mediaBtn'   => __( 'انتخاب', 'hodima' ),
			]
		);
	}

	/**
	 * ذخیره (Post/Redirect/Get).
	 *
	 * ترتیب ذخیره = ترتیب ردیف‌ها در فرم.
	 *
	 * باگ‌های قبلی:
	 * - ردیفی که لینکش mp4 نبود یا ویدیویش تکراری بود بی‌صدا حذف می‌شد و
	 *   آمارش هم پاک می‌شد. حالا ردیف می‌ماند، پیام هشدار می‌گیرد و فقط در
	 *   سایت نمایش داده نمی‌شود تا درست شود.
	 * - آمار «همه به‌جز ذخیره‌شده‌ها» پاک می‌شد (فهرست خالی = پاک شدن همه).
	 *   حالا فقط آمار استوری‌هایی پاک می‌شود که واقعا از فهرست حذف شده‌اند.
	 * - فرم ناقص (محدودیت max_input_vars سرور یا خطای اسکریپت) بخشی از
	 *   استوری‌ها را پاک می‌کرد. شمار ردیف‌ها جدا فرستاده می‌شود و اگر با
	 *   داده رسیده نخواند، هیچ چیزی ذخیره نمی‌شود.
	 */
	public static function handle_save(): void {
		if ( ! isset( $_POST['hodima_stories_nonce'] ) ) {
			return;
		}
		$nonce = sanitize_text_field( wp_unslash( $_POST['hodima_stories_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'hodima_stories_save' ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// هر فیلد پایین جدا پاک‌سازی می‌شود (sanitize_text_field روی کل آرایه کدهای %XX لینک‌ها را خراب می‌کرد)
		$raw = isset( $_POST['hodima_stories'] ) && is_array( $_POST['hodima_stories'] ) ? wp_unslash( $_POST['hodima_stories'] ) : []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$raw = is_array( $raw ) ? $raw : [];

		$expected = isset( $_POST['hodima_stories_count'] ) ? absint( $_POST['hodima_stories_count'] ) : null;
		if ( null !== $expected && count( $raw ) !== $expected ) {
			self::flash( __( 'فرم کامل به سرور نرسید و برای جلوگیری از پاک شدن استوری‌ها چیزی ذخیره نشد. صفحه را تازه کنید و دوباره ذخیره کنید؛ اگر تکرار شد، مقدار max_input_vars سرور کم است.', 'hodima' ), 'error' );
			self::redirect();
		}

		$old_uids = array_values( array_filter( array_map( static fn( array $item ): int => absint( $item['uid'] ?? 0 ), Core::items() ) ) );
		$taken    = array_fill_keys( $old_uids, true );
		$items    = [];
		$used     = [];
		$over     = false;
		$bad_cta  = [];

		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$title    = sanitize_text_field( (string) ( $row['title'] ?? '' ) );
			$link     = esc_url_raw( trim( (string) ( $row['link'] ?? '' ) ) );
			$cover_id = Helpers::validate_cover_id( $row['cover_id'] ?? 0 );
			$cta_raw  = trim( (string) ( $row['cta_link'] ?? '' ) );
			$cta_link = esc_url_raw( $cta_raw );
			$cta_text = sanitize_text_field( (string) ( $row['cta_text'] ?? '' ) );

			// ردیف کاملا خالی (افزوده و پر نشده)
			if ( '' === $title && '' === $link && 0 === $cover_id && '' === $cta_raw && '' === $cta_text ) {
				continue;
			}

			if ( count( $items ) >= Core::MAX_ITEMS ) {
				$over = true;
				break;
			}

			if ( ! Helpers::is_valid_link_url( $cta_link ) || ( '' !== $cta_raw && '' === $cta_link ) ) {
				$cta_link  = '';
				$bad_cta[] = count( $items );
			}

			$uid = absint( $row['uid'] ?? 0 );
			if ( ! $uid || isset( $used[ $uid ] ) ) {
				$uid = Helpers::new_uid( $taken + $used );
			}
			$used[ $uid ] = true;

			$items[] = [
				'uid'      => $uid,
				'title'    => $title,
				'link'     => $link,
				'cover_id' => $cover_id,
				'cta_link' => $cta_link,
				'cta_text' => $cta_text,
			];
		}

		update_option( Core::OPTION_NAME, $items, false );
		update_option( Core::SETTINGS_NAME, [ 'seen_to_end' => ! empty( $_POST['hodima_stories_seen_to_end'] ) ], false );

		$removed = array_values( array_diff( $old_uids, array_keys( $used ) ) );
		if ( $removed && class_exists( WP_Story_Analytics::class ) ) {
			WP_Story_Analytics::delete_stats( $removed );
		}

		self::flash( __( 'تغییرات با موفقیت ذخیره شد.', 'hodima' ) );

		$problems = Helpers::problems( $items );
		$invalid  = array_keys( array_filter( $problems, static fn( string $p ): bool => 'invalid' === $p ) );
		$dupes    = array_keys( array_filter( $problems, static fn( string $p ): bool => 'duplicate' === $p ) );

		if ( $invalid ) {
			self::flash(
				sprintf(
					/* translators: %s: شماره ردیف‌ها */
					__( 'ردیف %s: لینک ویدیو معتبر نیست (فقط فایل mp4 با آدرس کامل که با https شروع شود). این استوری‌ها ذخیره شدند ولی تا لینکشان درست نشود در سایت نمایش داده نمی‌شوند.', 'hodima' ),
					self::row_numbers( $invalid )
				),
				'warning'
			);
		}
		if ( $dupes ) {
			self::flash(
				sprintf(
					/* translators: %s: شماره ردیف‌ها */
					__( 'ردیف %s: این ویدیو در ردیف بالاتری هم هست؛ در سایت فقط ردیف بالاتر نمایش داده می‌شود.', 'hodima' ),
					self::row_numbers( $dupes )
				),
				'warning'
			);
		}
		if ( $bad_cta ) {
			self::flash(
				sprintf(
					/* translators: %s: شماره ردیف‌ها */
					__( 'ردیف %s: لینک محصول معتبر نبود و خالی شد (فقط آدرس کامل که با https یا http شروع شود).', 'hodima' ),
					self::row_numbers( $bad_cta )
				),
				'warning'
			);
		}
		if ( $over ) {
			self::flash(
				sprintf(
					/* translators: %s: بیشترین تعداد استوری */
					__( 'فقط %s استوری اول ذخیره شد (بیشترین تعداد مجاز).', 'hodima' ),
					number_format_i18n( Core::MAX_ITEMS )
				),
				'warning'
			);
		}

		self::redirect();
	}

	/**
	 * شماره ردیف‌ها برای پیام (از یک، با ارقام زبان سایت).
	 *
	 * @param array<int> $indexes شماره‌های از صفر.
	 */
	private static function row_numbers( array $indexes ): string {
		return implode( '، ', array_map( static fn( int $i ): string => number_format_i18n( $i + 1 ), $indexes ) );
	}

	/** پیام برای بارگذاری بعدی صفحه (سیستم پیام مشترک هدیما). */
	private static function flash( string $message, string $type = 'success' ): void {
		if ( function_exists( 'hodima_admin_flash' ) ) {
			hodima_admin_flash( esc_html( $message ), $type );
		}
	}

	/** بازگشت به صفحه پنل. */
	private static function redirect(): never {
		wp_safe_redirect( add_query_arg( [ 'page' => self::PAGE ], admin_url( 'admin.php' ) ) );
		exit;
	}

	/** صفحه پنل. */
	public static function render_page(): void {
		$items    = Core::items();
		$problems = Helpers::problems( $items );
		$settings = Core::settings();
		?>
		<div class="wrap hd-wrap hdn-admin-wrapper">
			<?php
			hodima_admin_header(
				[
					'title'       => __( 'تنظیمات استوری‌های ویدیویی', 'hodima' ),
					'description' => __( 'لینک ویدیوها (mp4) و تصویر کاور را برای نمایش پاپ‌آپ استوری وارد کنید.', 'hodima' ),
					'icon'        => 'dashicons-format-gallery',
					'badge'       => 'ابزارهای هدیما · نسخه ' . Core::VERSION,
				]
			);
			?>
			<form method="post" id="hodima-stories-form">
				<?php wp_nonce_field( 'hodima_stories_save', 'hodima_stories_nonce' ); ?>
				<input type="hidden" name="hodima_stories_count" id="hs-row-count" value="<?php echo esc_attr( (string) count( $items ) ); ?>">
				<div class="hdn-card hd-shadow-soft">
					<h2 class="hdn-card-title"><?php esc_html_e( 'پنل تنظیمات', 'hodima' ); ?></h2>

					<div class="hdn-actions-bar">
						<button type="button" class="hdn-btn hdn-btn-outline" id="hs-add-new"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> <?php esc_html_e( 'افزودن استوری جدید', 'hodima' ); ?></button>
						<p class="hs-limit-msg" id="hs-limit-msg" role="status" hidden></p>
						<button type="submit" class="hdn-btn hdn-btn-primary">
							<span class="dashicons dashicons-saved" aria-hidden="true"></span>
							<?php esc_html_e( 'ذخیره تنظیمات', 'hodima' ); ?>
						</button>
					</div>

					<label class="hs-setting">
						<input type="checkbox" name="hodima_stories_seen_to_end" value="1" <?php checked( $settings['seen_to_end'] ); ?>>
						<span>
							<strong><?php esc_html_e( 'استوری‌های دیده‌شده به انتهای ردیف بروند', 'hodima' ); ?></strong>
						</span>
					</label>

					<p class="hs-order-help"><?php esc_html_e( 'ترتیب نمایش در سایت همان ترتیب زیر است. با کشیدن دستگیره یا دکمه‌های بالا و پایین جابه‌جا کنید و در پایان «ذخیره تنظیمات» را بزنید.', 'hodima' ); ?></p>

					<div id="hodima-stories-list">
						<?php
						foreach ( $items as $i => $item ) {
							self::render_row( $i, $item, $problems[ $i ] ?? '' );
						}
						?>
					</div>
					<template id="hs-row-template"><?php self::render_row( null, [], '' ); ?></template>
				</div>
			</form>
		</div>
		<?php
	}

	/**
	 * یک ردیف استوری. همین نشانه‌گذاری قالب ردیف تازه (template) هم هست؛
	 * قبلا اسکریپت نسخه دومی با متن‌های متفاوت می‌ساخت.
	 *
	 * @param int|null             $index   شماره ردیف (null = قالب خالی بدون name).
	 * @param array<string, mixed> $item    داده ذخیره‌شده.
	 * @param string               $problem مشکل ردیف ('invalid'، 'duplicate' یا خالی).
	 */
	private static function render_row( ?int $index, array $item, string $problem ): void {
		$name     = static fn( string $field ): string => null === $index ? '' : ' name="' . esc_attr( "hodima_stories[{$index}][{$field}]" ) . '"';
		$cover_id = Helpers::validate_cover_id( $item['cover_id'] ?? 0 );
		$row_id   = null === $index ? '' : 'hs-row-' . $index;
		$messages = [
			'invalid'   => __( 'لینک ویدیو معتبر نیست (فقط فایل mp4 با آدرس کامل که با https شروع شود). این استوری تا درست شدن لینک در سایت نمایش داده نمی‌شود.', 'hodima' ),
			'duplicate' => __( 'این ویدیو در ردیف بالاتری هم هست؛ در سایت فقط ردیف بالاتر نمایش داده می‌شود.', 'hodima' ),
		];
		$message  = $messages[ $problem ] ?? '';
		// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- $name() خودش esc_attr می‌کند
		?>
		<div class="hodima-story-row anim-slide_up<?php echo $message ? ' has-problem' : ''; ?>">
			<div class="hs-order-col">
				<span class="hs-drag-handle" title="<?php esc_attr_e( 'برای جابه‌جایی بکشید', 'hodima' ); ?>" aria-hidden="true"><span class="dashicons dashicons-menu"></span></span>
				<span class="hs-order-num"><?php echo null === $index ? '' : esc_html( (string) ( $index + 1 ) ); ?></span>
				<button type="button" class="hs-move hs-move-up" aria-label="<?php esc_attr_e( 'انتقال به بالا', 'hodima' ); ?>"><span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span></button>
				<button type="button" class="hs-move hs-move-down" aria-label="<?php esc_attr_e( 'انتقال به پایین', 'hodima' ); ?>"><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></button>
			</div>
			<input type="hidden" class="hs-uid-input"<?php echo $name( 'uid' ); ?> value="<?php echo esc_attr( (string) absint( $item['uid'] ?? 0 ) ?: '' ); ?>">
			<div class="hdn-flex-col">
				<input type="text" class="hs-title-input hdn-input"<?php echo $name( 'title' ); ?> value="<?php echo esc_attr( (string) ( $item['title'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'عنوان استوری', 'hodima' ); ?>" aria-label="<?php esc_attr_e( 'عنوان استوری', 'hodima' ); ?>">
			</div>
			<div class="hdn-flex-col">
				<input type="url" class="hs-link-input hdn-input" dir="ltr"<?php echo $name( 'link' ); ?> value="<?php echo esc_url( (string) ( $item['link'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'آدرس ویدیو (https://…/video.mp4)', 'hodima' ); ?>" aria-label="<?php esc_attr_e( 'آدرس ویدیو', 'hodima' ); ?>"<?php echo $message ? ' aria-invalid="true" aria-describedby="' . esc_attr( $row_id . '-problem' ) . '"' : ''; ?>>
				<?php if ( $message ) : ?>
					<p class="hs-row-problem" id="<?php echo esc_attr( $row_id . '-problem' ); ?>"><?php echo esc_html( $message ); ?></p>
				<?php endif; ?>
			</div>
			<div class="hdn-flex-col hdn-cta-col">
				<input type="url" class="hs-cta-link-input hdn-input" dir="ltr"<?php echo $name( 'cta_link' ); ?> value="<?php echo esc_url( (string) ( $item['cta_link'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'لینک محصول (اختیاری)', 'hodima' ); ?>" aria-label="<?php esc_attr_e( 'لینک محصول', 'hodima' ); ?>">
				<input type="text" class="hs-cta-text-input hdn-input"<?php echo $name( 'cta_text' ); ?> value="<?php echo esc_attr( (string) ( $item['cta_text'] ?? '' ) ); ?>" placeholder="<?php esc_attr_e( 'متن دکمه', 'hodima' ); ?>" aria-label="<?php esc_attr_e( 'متن دکمه محصول', 'hodima' ); ?>">
			</div>
			<div class="hdn-flex-col">
				<div class="hdn-media-container">
					<div class="hdn-img-preview-box"><img src="<?php echo Helpers::esc_img_src( Helpers::get_cover_url( $cover_id, 'thumbnail' ) ); ?>" alt="" class="hs-cover-preview"></div>
					<div class="hdn-media-actions">
						<input type="hidden" class="hs-cover-id"<?php echo $name( 'cover_id' ); ?> value="<?php echo esc_attr( $cover_id ? (string) $cover_id : '' ); ?>">
						<button type="button" class="hdn-btn hdn-upload-btn hs-select-cover"><?php esc_html_e( 'انتخاب کاور', 'hodima' ); ?></button>
						<button type="button" class="hdn-btn hdn-remove-btn hs-remove-row"><?php esc_html_e( 'حذف', 'hodima' ); ?></button>
					</div>
				</div>
			</div>
			<div class="hs-removed-bar" role="status" hidden>
				<span><?php esc_html_e( 'این استوری با «ذخیره تنظیمات» حذف می‌شود و آمار بازدیدش هم پاک می‌شود.', 'hodima' ); ?></span>
				<button type="button" class="hdn-btn hdn-upload-btn hs-restore-row"><?php esc_html_e( 'بازگردانی', 'hodima' ); ?></button>
			</div>
		</div>
		<?php
		// phpcs:enable
	}
}
