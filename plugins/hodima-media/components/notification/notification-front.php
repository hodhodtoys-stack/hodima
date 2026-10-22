<?php
/**
 * نمایش نوتیفیکیشن‌ها در سایت.
 *
 * هر اعلان یک <dialog> بومی است: پنجره وسط با showModal() (بقیه صفحه inert،
 * Escape، پرده ::backdrop) و جعبه گوشه با ویژگی open (فوکوس را نمی‌گیرد).
 * سرور فقط قوانین وابسته به آدرس/کاربر را می‌سنجد؛ قوانین وابسته به
 * بازدیدکننده و زمان (تاریخ دقیق، UTM، ارجاع‌دهنده، دستگاه، جستجو) در مرورگر،
 * چون صفحه‌ها با لایت‌اسپید کش می‌شوند و برای همه یکسان‌اند.
 *
 * @package Hodima\Media
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/notification-settings.php';

/**
 * نمایش نوتیفیکیشن‌ها در سایت.
 */
final class Hodima_Notification_Front {

	/**
	 * اعلان‌هایی که در این صفحه چاپ می‌شوند.
	 *
	 * @var list<array<string, mixed>>
	 */
	private array $to_render = [];

	/** ثبت هوک‌ها. */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', [ $this, 'prepare' ] );
		add_action( 'wp_footer', [ $this, 'render_all' ] );
	}

	/** انتخاب اعلان‌های این صفحه و بارگذاری CSS/JS فقط اگر چیزی هست. */
	public function prepare(): void {
		foreach ( $this->active() as $item ) {
			if ( $this->passes_server_rules( $item['meta'] ) ) {
				$this->to_render[] = $item;
			}
		}
		if ( ! $this->to_render ) {
			return;
		}
		wp_enqueue_style( 'hd-notification-front-css', hodima_notification_asset_url( 'css/notification-front.css' ), [], hodima_notification_asset_version( 'css/notification-front.css' ) );
		wp_enqueue_script( 'hd-notification-front-js', hodima_notification_asset_url( 'js/notification-front.js' ), [], hodima_notification_asset_version( 'js/notification-front.js' ), [ 'in_footer' => true, 'strategy' => 'defer' ] );
	}

	/** چاپ همه اعلان‌های این صفحه. */
	public function render_all(): void {
		foreach ( $this->to_render as $item ) {
			$this->render( $item );
		}
	}

	/**
	 * اعلان‌های منتشرشده و روشن، مرتب با اولویت (کش ۱۲ ساعته؛ هر تغییر پاکش می‌کند).
	 *
	 * @return list<array<string, mixed>>
	 */
	private function active(): array {
		$cached = get_transient( HODIMA_NOTIFICATION_CACHE );
		// قالب کش تا 2.0.2 آرایه تخت متاها بود (بدون کلید meta)؛ دوباره ساخته شود
		if ( is_array( $cached ) && ( ! $cached || isset( $cached[0]['meta'] ) ) ) {
			return array_values( $cached );
		}

		$items = [];
		$ids   = get_posts(
			[
				'post_type'      => HODIMA_NOTIFICATION_POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			]
		);
		foreach ( $ids as $id ) {
			$id   = (int) $id;
			$meta = hodima_notification_meta( $id );
			if ( '1' !== $meta['hd_notif_is_active'] || '' === $meta['hd_notif_bg_image'] ) {
				continue;
			}
			$items[] = [
				'id'    => $id,
				'title' => trim( get_the_title( $id ) ) ?: 'اطلاعیه',
				'meta'  => $meta,
				'image' => $this->image( $meta ),
			];
		}
		usort( $items, static fn( array $a, array $b ): int => (int) $a['meta']['hd_notif_priority'] <=> (int) $b['meta']['hd_notif_priority'] );

		set_transient( HODIMA_NOTIFICATION_CACHE, $items, 12 * HOUR_IN_SECONDS );
		return $items;
	}

	/**
	 * عکس اعلان: با شناسه پیوست اندازه‌های کوچک‌تر (srcset) و ابعاد واقعی؛
	 * اعلان قدیمی که فقط آدرس دارد یک بار (هنگام ساخت کش) به پیوست برگردانده می‌شود.
	 *
	 * @param array<string, string> $meta تنظیمات اعلان.
	 * @return array{src: string, srcset: string, width: int, height: int}
	 */
	private function image( array $meta ): array {
		$url = $meta['hd_notif_bg_image'];
		$id  = (int) $meta['hd_notif_bg_image_id'];
		if ( $id <= 0 ) {
			$id = attachment_url_to_postid( $url );
		}
		$full = $id > 0 ? wp_get_attachment_image_src( $id, 'full' ) : false;
		if ( ! $full ) {
			return [ 'src' => $url, 'srcset' => '', 'width' => 0, 'height' => 0 ];
		}
		return [
			'src'    => (string) $full[0],
			'srcset' => (string) wp_get_attachment_image_srcset( $id, 'full' ),
			'width'  => (int) $full[1],
			'height' => (int) $full[2],
		];
	}

	/**
	 * قوانینی که به آدرس صفحه یا کاربر وارد شده بستگی دارند.
	 *
	 * @param array<string, string> $meta تنظیمات اعلان.
	 */
	private function passes_server_rules( array $meta ): bool {

		$status = $meta['hd_notif_user_status'];
		if ( ( 'logged_in' === $status && ! is_user_logged_in() ) || ( 'logged_out' === $status && is_user_logged_in() ) ) {
			return false;
		}

		$role = $meta['hd_notif_target_role'];
		if ( 'all' !== $role && ( ! is_user_logged_in() || ! in_array( $role, (array) wp_get_current_user()->roles, true ) ) ) {
			return false;
		}

		$post_type = $meta['hd_notif_target_post_type'];
		if ( 'all' !== $post_type && ! is_singular( $post_type ) && ! ( 'post' === $post_type && is_home() ) && ! ( 'product' === $post_type && function_exists( 'is_shop' ) && is_shop() ) ) {
			return false;
		}

		$lines = 'all' === $meta['hd_notif_target_type'] ? [] : array_filter( array_map( 'trim', preg_split( '/\R/u', $meta['hd_notif_target_page'] ) ?: [] ) );
		if ( $lines ) {
			$matched = (bool) array_filter( $lines, [ $this, 'matches_current_page' ] );
			if ( ( 'specific' === $meta['hd_notif_target_type'] ) !== $matched ) {
				return false;
			}
		}

		// اعلان تمام‌شده چاپ نشود؛ شروع/پایان دقیق در مرورگر (صفحه کش‌شده روزها می‌ماند)
		$end = $meta['hd_notif_end_date'];
		return '' === $end || current_time( 'Y-m-d' ) <= $end;
	}

	/** یک خط هدف (شناسه نوشته یا آدرس/مسیر) با صفحه فعلی جور است؟ زیرمسیرها هم. */
	private function matches_current_page( string $line ): bool {
		if ( ctype_digit( $line ) ) {
			return is_singular() && get_queried_object_id() === (int) $line;
		}
		$normalize = static function ( string $path ): string {
			$path = untrailingslashit( '/' . ltrim( $path, '/' ) );
			return '' === $path ? '/' : $path;
		};
		$uri     = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		$current = $normalize( rawurldecode( (string) wp_parse_url( $uri, PHP_URL_PATH ) ) );
		$target  = $normalize( rawurldecode( (string) wp_parse_url( $line, PHP_URL_PATH ) ) );
		return $current === $target || ( '/' !== $target && str_starts_with( $current . '/', $target . '/' ) );
	}

	/** مرز روز در منطقه زمانی سایت ← میلی‌ثانیه برای مقایسه در مرورگر. */
	private function day_boundary_ms( string $date, bool $end_of_day ): int {
		if ( '' === $date ) {
			return 0;
		}
		try {
			return ( new DateTimeImmutable( $date . ( $end_of_day ? ' 23:59:59' : ' 00:00:00' ), wp_timezone() ) )->getTimestamp() * 1000;
		} catch ( Exception ) {
			return 0;
		}
	}

	/**
	 * نشانه‌گذاری یک اعلان.
	 *
	 * @param array<string, mixed> $item اعلان (id، title، meta، image).
	 */
	private function render( array $item ): void {
		$id          = (int) $item['id'];
		$title       = (string) $item['title'];
		$meta        = (array) $item['meta'];
		$image       = (array) $item['image'];
		$position    = hodima_notification_normalize_position( (string) $meta['hd_notif_position'] );
		$transparent = '1' === $meta['hd_notif_bg_transparent'];
		$width       = (int) $meta['hd_notif_width'];
		$height      = (int) $meta['hd_notif_height'];
		$link        = (string) $meta['hd_notif_main_link'];

		$classes = [ 'hd-notification-popup', 'hd-pos-' . $position ];
		if ( $transparent ) {
			$classes[] = 'hd-notif-transparent';
		}
		if ( $height > 0 ) {
			$classes[] = 'hd-notif-fixed-height';
		}

		// اندازه‌ها با متغیر CSS (نه style کامل): ظاهر پایه در فایل CSS
		$style = sprintf( '--hd-notif-w:%dpx;', $width );
		if ( $height > 0 ) {
			$style .= sprintf( '--hd-notif-h:%dpx;', $height );
		}
		if ( ! $transparent ) {
			$style .= '--hd-notif-bg:' . $meta['hd_notif_bg_color'] . ';';
		}
		$style .= '--hd-notif-close:' . $meta['hd_notif_close_color'] . ';';

		// عکس تا لحظه نمایش دانلود نمی‌شود (data-src)؛ sizes همان عرض پاپ‌آپ
		$sizes = sprintf( '(max-width: 768px) 90vw, %dpx', $width );
		$data  = [
			'id'                 => $id,
			'delay'              => (int) $meta['hd_notif_delay'] * 1000,
			'auto-close'         => (int) $meta['hd_notif_auto_close_time'] * 1000,
			'max-count'          => (int) $meta['hd_notif_show_count'],
			'anim-in'            => $meta['hd_notif_anim_in'],
			'anim-out'           => $meta['hd_notif_anim_out'],
			'trigger-exit'       => $meta['hd_notif_trigger_exit'],
			'trigger-scroll'     => (int) $meta['hd_notif_trigger_scroll'],
			'trigger-inactivity' => (int) $meta['hd_notif_trigger_inactivity'],
			'position'           => $position,
			'mobile-mode'        => $meta['hd_notif_mobile_mode'],
			'device'             => $meta['hd_notif_device'],
			'start'              => $this->day_boundary_ms( (string) $meta['hd_notif_start_date'], false ),
			'end'                => $this->day_boundary_ms( (string) $meta['hd_notif_end_date'], true ),
			'utm'                => mb_strtolower( trim( (string) $meta['hd_notif_target_utm'] ) ),
			'referrer'           => mb_strtolower( trim( (string) $meta['hd_notif_target_referrer'] ) ),
			'skip-search'        => $meta['hd_notif_skip_search'],
		];
		$attrs = '';
		foreach ( $data as $name => $value ) {
			$attrs .= sprintf( ' data-%s="%s"', $name, esc_attr( (string) $value ) );
		}
		?>
		<dialog id="hd-notification-<?php echo (int) $id; ?>" class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" aria-label="<?php echo esc_attr( $title ); ?>" tabindex="-1" style="<?php echo esc_attr( $style ); ?>"<?php echo $attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- بالا esc_attr شده ?>>
			<?php if ( '0' !== $meta['hd_notif_close_btn'] ) : ?>
				<button type="button" class="hd-notif-close" aria-label="بستن"><span aria-hidden="true">&times;</span></button>
			<?php endif; ?>
			<div class="hd-notif-main-area">
				<?php if ( '' !== $link ) : ?>
					<a class="hd-notif-half-link" href="<?php echo esc_url( $link ); ?>">
				<?php endif; ?>
				<img data-src="<?php echo esc_url( (string) $image['src'] ); ?>"
					<?php if ( '' !== $image['srcset'] ) : ?>
						data-srcset="<?php echo esc_attr( (string) $image['srcset'] ); ?>" data-sizes="<?php echo esc_attr( $sizes ); ?>"
					<?php endif; ?>
					<?php if ( $image['width'] > 0 && $image['height'] > 0 ) : ?>
						width="<?php echo (int) $image['width']; ?>" height="<?php echo (int) $image['height']; ?>"
					<?php endif; ?>
					alt="<?php echo esc_attr( $title ); ?>" decoding="async">
				<?php if ( '' !== $link ) : ?>
					</a>
				<?php endif; ?>
			</div>
		</dialog>
		<?php
	}
}

new Hodima_Notification_Front();
