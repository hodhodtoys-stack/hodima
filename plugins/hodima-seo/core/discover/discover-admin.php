<?php
/**
 * ماژول «Google Discover» — کادر ویرایش نوشته، برگه، محصول و دسته محصول
 * Path: core/discover/discover-admin.php
 *
 * چیدمان (SEO 2.1.3): سربرگ با دایره امتیاز و دکمه پیش‌نمایش، سه تب
 * (تنظیمات، آمادگی، آمار) و پنجره پیش‌نمایش کارت؛ توضیح کامل بالای
 * hodima_seo_discover_render_fields. پیشنهاد عنوان، آمار با مقایسه ۲۸ روز
 * قبل و هماهنگی زنده با ویرایشگر بلوکی و کلاسیک (discover-admin.js).
 * نام فیلدها hodima_discover[…]، nonce جدا؛ کلیدهای متا همان قبلی.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

add_action( 'admin_enqueue_scripts', static function (): void {

	$screen = get_current_screen();

	$is_post = $screen && 'post' === $screen->base && hodima_seo_discover_enabled( 'post' );
	$is_term = $screen && 'term' === $screen->base && hodima_seo_discover_enabled( 'term' );

	if ( ! $is_post && ! $is_term ) {
		return;
	}

	wp_enqueue_media();

	$url = HODIMA_SEO_URL . '/core/discover/assets';
	// نسخه دارایی = زمان تغییر فایل (به‌روزرسانی از کش مرورگر پنهان نماند)
	$ver = static function ( string $file ): string {
		$path = __DIR__ . '/assets/' . $file;
		return is_file( $path ) ? (string) filemtime( $path ) : HODIMA_SEO_VERSION;
	};

	wp_enqueue_style( 'hodima-discover-admin', $url . '/discover-admin.css', [ 'dashicons' ], $ver( 'discover-admin.css' ) );
	wp_enqueue_script( 'hodima-discover-admin', $url . '/discover-admin.js', [], $ver( 'discover-admin.js' ), [
		'in_footer' => true,
		'strategy'  => 'defer',
	] );

	wp_add_inline_script( 'hodima-discover-admin', 'window.hodimaDiscover = ' . wp_json_encode( [
		'minWidth'  => HODIMA_SEO_DISCOVER_MIN_WIDTH,
		'titleMin'  => HODIMA_SEO_DISCOVER_TITLE_MIN,
		'titleMax'  => HODIMA_SEO_DISCOVER_TITLE_MAX,
		'clickbait' => hodima_seo_discover_clickbait_phrases(),
		'ajax'      => admin_url( 'admin-ajax.php' ),
	], JSON_UNESCAPED_UNICODE ) . ';', 'before' );
} );

add_action( 'add_meta_boxes', static function ( string $post_type ): void {

	if ( ! in_array( $post_type, hodima_seo_discover_post_types(), true ) ) {
		return;
	}

	add_meta_box( 'hodima_discover_box', 'Google Discover', 'hodima_seo_discover_render_box', $post_type, 'normal', 'high' );
} );

// دسته محصول: کادر در فرم ویرایش دسته (بعد از کادر رسانه) و ذخیره
add_action( 'admin_init', static function (): void {
	foreach ( hodima_seo_discover_taxonomies() as $taxonomy ) {
		add_action( "{$taxonomy}_edit_form", 'hodima_seo_discover_render_term_box', 5 );
		add_action( "edited_{$taxonomy}", static fn( int $term_id ) => hodima_seo_discover_save( $term_id, 'term' ) );
	}
} );

/** کادر Discover نوشته/برگه/محصول. */
function hodima_seo_discover_render_box( WP_Post $post ): void {

	if ( ! hodima_seo_discover_for_post( $post->ID ) ) {
		echo '<p>Discover برای این نوع محتوا فعال نیست.</p>';
		return;
	}

	hodima_seo_discover_render_fields( $post );
}

/** کادر Discover دسته محصول (فرم ویرایش ترم، بیرون از جعبه‌های meta-box). */
function hodima_seo_discover_render_term_box( WP_Term $term ): void {

	if ( ! hodima_seo_discover_for_term( (int) $term->term_id ) ) {
		return;
	}
	?>
	<div id="hodima_discover_term_box" class="postbox hodima-dc-postbox">
		<div class="postbox-header"><h2 class="hndle">Google Discover</h2></div>
		<div class="inside"><?php hodima_seo_discover_render_fields( $term ); ?></div>
	</div>
	<?php
}

/**
 * اطلاعات یک تصویر برای کادر: آدرس اندازه متوسط (نمایش)، ابعاد اصل فایل
 * (بررسی ۱۲۰۰ پیکسل) و متن جایگزین؛ یا null.
 *
 * @return array{id: int, url: string, width: int, height: int, alt: string}|null
 */
function hodima_seo_discover_admin_image( int $attachment_id ): ?array {

	if ( $attachment_id <= 0 || ! wp_attachment_is_image( $attachment_id ) ) {
		return null;
	}

	$src  = wp_get_attachment_image_src( $attachment_id, 'medium_large' );
	$meta = wp_get_attachment_metadata( $attachment_id );

	return [
		'id'     => $attachment_id,
		'url'    => is_array( $src ) ? (string) $src[0] : (string) wp_get_attachment_url( $attachment_id ),
		'width'  => (int) ( is_array( $meta ) ? ( $meta['width'] ?? 0 ) : 0 ),
		'height' => (int) ( is_array( $meta ) ? ( $meta['height'] ?? 0 ) : 0 ),
		'alt'    => trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ),
	];
}

/** سطح امتیاز آمادگی: ok (همه)، warn (۶۰٪ یا بیشتر)، error. */
function hodima_seo_discover_score_level( int $ok, int $total ): string {
	return match ( true ) {
		$ok === $total      => 'ok',
		$ok >= $total * 0.6 => 'warn',
		default             => 'error',
	};
}

/** دکمه «ⓘ» که راهنمای یک فیلد را باز/بسته می‌کند (به‌جای متن ثابت زیر فیلد). */
function hodima_seo_discover_info_button( string $help_id, string $label ): string {
	return sprintf(
		'<button type="button" class="hodima-dc__info" aria-expanded="false" aria-controls="%1$s" data-hodima-dc-info><span class="dashicons dashicons-info-outline" aria-hidden="true"></span><span class="screen-reader-text">%2$s</span></button>',
		esc_attr( $help_id ),
		esc_html( 'راهنمای ' . $label )
	);
}

/**
 * کادر Discover (SEO 2.1.3، طراحی تازه). تا 2.1.2 همه چیز زیر هم بود: کارت تصویر
 * بزرگ و کنارش پیش‌نمایش گوشی با همان تصویر (تصویر دو بار)، راهنمای ثابت زیر
 * هر فیلد و ۱۳ مورد آمادگی همیشه باز؛ کادر در دسکتاپ ~۱۴۰۰ و در موبایل ~۲۱۰۰
 * پیکسل بود. حالا:
 *   سربرگ: دایره امتیاز، خلاصه، آمار کوتاه و دکمه «پیش‌نمایش کارت»؛
 *   تب‌ها: تنظیمات (عنوان، یک تصویر کوچک با ابعاد و وضعیت برش، موضوعات) |
 *          آمادگی (فقط مشکل‌ها، هر کدام بازشونده؛ موارد درست جمع) | آمار؛
 *   راهنمای هر فیلد پشت دکمه ⓘ؛ پیش‌نمایش گوشی/دسکتاپ/شبکه‌ها در <dialog>.
 * نام فیلدها، nonce و داده ذخیره‌شده همان قبلی است.
 */
function hodima_seo_discover_render_fields( WP_Post|WP_Term $target ): void {

	$context   = $target instanceof WP_Term ? 'term' : 'post';
	$id        = $target instanceof WP_Term ? (int) $target->term_id : (int) $target->ID;
	$data      = hodima_seo_discover_data( $id, $context );
	$fallback  = hodima_seo_discover_object_title( $id, $context );
	$own       = hodima_seo_discover_admin_image( $data['image_id'] );
	$default   = hodima_seo_discover_admin_image( hodima_seo_discover_default_image_id( $id, $context ) );
	$shown     = $own ?? $default;
	$checks    = hodima_seo_discover_checks( $target );
	$status    = array_column( $checks, 'status', 'key' );
	$published = $target instanceof WP_Term || 'publish' === $target->post_status;
	$all_stats = hodima_seo_discover_stats();
	$stats     = $published ? hodima_seo_discover_post_stats( $id, $context ) : null;
	$change    = null !== $stats ? hodima_seo_discover_change( $stats['impressions'], $stats['prev_impressions'] ) : null;
	$own_label = 'term' === $context ? 'تصویر دسته' : ( 'product' === $target->post_type ? 'تصویر محصول' : 'تصویر شاخص' );
	$host      = (string) wp_parse_url( home_url(), PHP_URL_HOST );
	$icon      = (string) get_site_icon_url( 64 );
	$title     = '' !== $data['title'] ? $data['title'] : $fallback;
	$length    = mb_strlen( $title );
	$ideas     = hodima_seo_discover_title_ideas( $target );
	$meta_desc = trim( (string) get_metadata( $context, $id, '_seobox_description', true ) );
	$summary   = '' !== $meta_desc ? $meta_desc : trim( wp_strip_all_tags( $target instanceof WP_Post ? $target->post_excerpt : $target->description ) );
	$summary   = '' !== $summary && ! str_contains( $summary, '%' ) ? wp_html_excerpt( $summary, 160, '…' ) : '';
	$report    = function_exists( 'hodima_seo_discover_page_url' ) ? hodima_seo_discover_page_url() : '';
	$num       = static fn( int $n ): string => number_format_i18n( $n );

	[ $ok, $total ] = hodima_seo_discover_score( $checks );
	$level          = hodima_seo_discover_score_level( $ok, $total );
	$issues         = array_values( array_filter( $checks, static fn( array $c ): bool => 'ok' !== $c['status'] ) );
	$passed         = array_values( array_filter( $checks, static fn( array $c ): bool => 'ok' === $c['status'] ) );
	usort( $issues, static fn( array $a, array $b ): int => ( 'error' === $b['status'] ) <=> ( 'error' === $a['status'] ) );

	$title_state = $status['title'] ?? 'ok';
	$title_msg   = 'ok' === $title_state ? '' : (string) ( array_column( $checks, 'detail', 'key' )['title'] ?? '' );
	$crops       = $status['crops'] ?? '';
	?>
	<div class="hodima-dc" data-hodima-dc data-context="<?php echo esc_attr( $context ); ?>" data-object-id="<?php echo (int) $id; ?>" data-refresh-nonce="<?php echo esc_attr( wp_create_nonce( 'hodima_discover_box_' . $context . '_' . $id ) ); ?>" data-has-meta-desc="<?php echo '' !== $meta_desc ? '1' : '0'; ?>">
		<?php wp_nonce_field( 'hodima_discover_save_' . $context . '_' . $id, 'hodima_discover_nonce' ); ?>
		<input type="hidden" name="hodima_discover[present]" value="1">

		<header class="hodima-dc__head">
			<span class="hodima-dc__ring is-<?php echo esc_attr( $level ); ?>" style="--p: <?php echo (int) ( $total ? round( 100 * $ok / $total ) : 0 ); ?>" role="img" aria-label="<?php echo esc_attr( sprintf( 'آمادگی %1$s از %2$s', $num( $ok ), $num( $total ) ) ); ?>" data-hodima-dc-ring>
				<span data-hodima-dc-ring-text><?php echo esc_html( $num( $ok ) . '/' . $num( $total ) ); ?></span>
			</span>
			<div class="hodima-dc__head-text">
				<strong>آمادگی برای Discover</strong>
				<span class="hodima-dc__head-summary" data-hodima-dc-summary aria-live="polite"><?php echo esc_html( $issues ? sprintf( '%s مورد نیاز به توجه', $num( count( $issues ) ) ) : 'همه موارد درست است' ); ?></span>
			</div>
			<?php if ( null !== $stats ) : ?>
				<span class="hodima-dc__stat-chip" title="Discover، ۲۸ روز آخر (Search Console)">
					<span class="dashicons dashicons-chart-bar" aria-hidden="true"></span>
					<?php echo esc_html( sprintf( '%1$s کلیک · %2$s نمایش', $num( $stats['clicks'] ), $num( $stats['impressions'] ) ) ); ?>
					<?php if ( null !== $change ) : ?>
						<span class="hodima-dc__change<?php echo esc_attr( abs( $change ) < 0.5 ? '' : ( $change > 0 ? ' is-up' : ' is-down' ) ); ?>"><?php echo esc_html( hodima_seo_discover_change_text( $change ) ); ?></span>
					<?php endif; ?>
				</span>
			<?php endif; ?>
			<button type="button" class="button hodima-dc__preview-btn" data-hodima-dc-open-preview aria-haspopup="dialog">
				<span class="dashicons dashicons-visibility" aria-hidden="true"></span> پیش‌نمایش کارت
			</button>
		</header>

		<div class="hodima-dc__tabs" role="tablist" aria-label="Google Discover">
			<button type="button" role="tab" id="hodima-dc-tab-settings" aria-controls="hodima-dc-panel-settings" aria-selected="true" data-hodima-dc-tab="settings">
				<span class="dashicons dashicons-admin-generic" aria-hidden="true"></span> تنظیمات
			</button>
			<button type="button" role="tab" id="hodima-dc-tab-checks" aria-controls="hodima-dc-panel-checks" aria-selected="false" tabindex="-1" data-hodima-dc-tab="checks">
				<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> آمادگی
				<span class="hodima-dc__badge is-<?php echo esc_attr( $level ); ?>" data-hodima-dc-issue-count <?php echo $issues ? '' : 'hidden'; ?>><?php echo esc_html( $num( count( $issues ) ) ); ?></span>
			</button>
			<button type="button" role="tab" id="hodima-dc-tab-stats" aria-controls="hodima-dc-panel-stats" aria-selected="false" tabindex="-1" data-hodima-dc-tab="stats">
				<span class="dashicons dashicons-chart-area" aria-hidden="true"></span> آمار
			</button>
		</div>

		<?php /* ── تب «تنظیمات» ── */ ?>
		<section class="hodima-dc__panel" role="tabpanel" id="hodima-dc-panel-settings" aria-labelledby="hodima-dc-tab-settings" data-hodima-dc-panel="settings">

			<div class="hodima-dc__field">
				<div class="hodima-dc__label-row">
					<label class="hodima-dc__label" for="hodima-dc-title">عنوان کارت</label>
					<?php echo hodima_seo_discover_info_button( 'hodima-dc-help-title', 'عنوان کارت' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escape‌شده در تابع ?>
				</div>
				<p class="hodima-dc__help" id="hodima-dc-help-title" hidden>فقط کارت Discover و اشتراک‌گذاری (og:title) عوض می‌شود؛ عنوان صفحه و h1 همان می‌ماند. خالی = عنوان صفحه. جذاب ولی صادق؛ «طعمه کلیک» جریمه دارد. <?php echo esc_html( sprintf( 'بهتر است %1$s تا %2$s کاراکتر باشد.', $num( HODIMA_SEO_DISCOVER_TITLE_MIN ), $num( HODIMA_SEO_DISCOVER_TITLE_MAX ) ) ); ?></p>
				<div class="hodima-dc__input-wrap<?php echo 'ok' === $title_state ? '' : ' is-warn'; ?>" data-hodima-dc-title-wrap>
					<input type="text" id="hodima-dc-title" name="hodima_discover[title]" value="<?php echo esc_attr( $data['title'] ); ?>" maxlength="200" placeholder="<?php echo esc_attr( $fallback ); ?>" aria-describedby="hodima-dc-title-msg" data-hodima-dc-title data-hodima-dc-fallback="<?php echo esc_attr( $fallback ); ?>">
					<span class="hodima-dc__counter<?php echo $length > HODIMA_SEO_DISCOVER_TITLE_MAX ? ' is-over' : ''; ?>" data-hodima-dc-counter aria-hidden="true"><?php echo esc_html( $num( $length ) . '/' . $num( HODIMA_SEO_DISCOVER_TITLE_MAX ) ); ?></span>
				</div>
				<p class="hodima-dc__msg" id="hodima-dc-title-msg" data-hodima-dc-title-msg aria-live="polite" <?php echo '' === $title_msg ? 'hidden' : ''; ?>><?php echo esc_html( $title_msg ); ?></p>
				<?php if ( $ideas ) : ?>
					<div class="hodima-dc__ideas" role="group" aria-label="پیشنهاد عنوان">
						<span class="hodima-dc__ideas-label"><span class="dashicons dashicons-lightbulb" aria-hidden="true"></span> پیشنهاد</span>
						<?php foreach ( $ideas as $idea ) : ?>
							<button type="button" class="hodima-dc__idea" data-hodima-dc-idea="<?php echo esc_attr( $idea ); ?>"><?php echo esc_html( $idea ); ?></button>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

			<div class="hodima-dc__field" data-hodima-dc-image
				data-default-url="<?php echo esc_url( $default['url'] ?? '' ); ?>"
				data-default-width="<?php echo (int) ( $default['width'] ?? 0 ); ?>"
				data-default-height="<?php echo (int) ( $default['height'] ?? 0 ); ?>"
				data-default-alt="<?php echo '' !== ( $default['alt'] ?? '' ) ? '1' : '0'; ?>"
				data-own-label="<?php echo esc_attr( $own_label ); ?>">
				<div class="hodima-dc__label-row">
					<span class="hodima-dc__label" id="hodima-dc-image-label">تصویر کارت</span>
					<?php echo hodima_seo_discover_info_button( 'hodima-dc-help-image', 'تصویر کارت' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escape‌شده در تابع ?>
				</div>
				<p class="hodima-dc__help" id="hodima-dc-help-image" hidden><?php echo esc_html( sprintf( 'حداقل %1$s پیکسل عرض، ترجیحا افقی ۱۶:۹؛ بدون لوگو و متن زیاد روی تصویر. اگر تصویر جدا انتخاب نکنید، %2$s استفاده می‌شود. سه برش ۱۶:۹، ۴:۳ و ۱:۱ هنگام ذخیره خودکار ساخته می‌شود.', $num( HODIMA_SEO_DISCOVER_MIN_WIDTH ), $own_label ) ); ?></p>
				<input type="hidden" name="hodima_discover[image_id]" value="<?php echo null !== $own ? (int) $own['id'] : ''; ?>" data-hodima-dc-image-id
					data-own-url="<?php echo esc_url( $own['url'] ?? '' ); ?>"
					data-own-width="<?php echo (int) ( $own['width'] ?? 0 ); ?>"
					data-own-height="<?php echo (int) ( $own['height'] ?? 0 ); ?>"
					data-own-alt="<?php echo '' !== ( $own['alt'] ?? '' ) ? '1' : '0'; ?>">
				<div class="hodima-dc__media">
					<div class="hodima-dc__thumb<?php echo null !== $shown ? ' has-image' : ''; ?>" data-hodima-dc-thumb>
						<img src="<?php echo esc_url( $shown['url'] ?? '' ); ?>" alt="" data-hodima-dc-thumb-img <?php echo null !== $shown ? '' : 'hidden'; ?>>
						<span class="dashicons dashicons-format-image" aria-hidden="true" data-hodima-dc-thumb-empty <?php echo null !== $shown ? 'hidden' : ''; ?>></span>
					</div>
					<div class="hodima-dc__media-body">
						<strong class="hodima-dc__media-source" data-hodima-dc-image-source><?php echo esc_html( null !== $own ? 'تصویر جدای Discover' : ( null !== $default ? $own_label . ' (پیش‌فرض)' : 'تصویری انتخاب نشده' ) ); ?></strong>
						<span class="hodima-dc__facts">
							<span class="hodima-dc__fact <?php echo null !== $shown && $shown['width'] >= HODIMA_SEO_DISCOVER_MIN_WIDTH ? 'is-ok' : 'is-error'; ?>" data-hodima-dc-dims>
								<?php echo esc_html( null !== $shown ? sprintf( '%1$s×%2$s', $num( $shown['width'] ), $num( $shown['height'] ) ) : 'بدون تصویر' ); ?>
							</span>
							<?php if ( '' !== $crops ) : ?>
								<span class="hodima-dc__fact <?php echo 'ok' === $crops ? 'is-ok' : 'is-warn'; ?>" data-hodima-dc-crops><?php echo 'ok' === $crops ? 'برش‌ها آماده' : 'برش بعد از ذخیره'; ?></span>
							<?php endif; ?>
						</span>
						<div class="hodima-dc__media-actions">
							<button type="button" class="button" data-hodima-dc-pick aria-describedby="hodima-dc-image-label"><?php echo null !== $own ? 'تغییر تصویر' : 'انتخاب تصویر جدا'; ?></button>
							<button type="button" class="button-link hodima-dc__clear" data-hodima-dc-clear <?php echo null !== $own ? '' : 'hidden'; ?>>برگشت به <?php echo esc_html( $own_label ); ?></button>
						</div>
					</div>
				</div>
				<p class="hodima-dc__msg" data-hodima-dc-status aria-live="polite" hidden></p>
			</div>

			<div class="hodima-dc__field" data-hodima-dc-topics>
				<div class="hodima-dc__label-row">
					<label class="hodima-dc__label" for="hodima-dc-topic-input">موضوعات اصلی</label>
					<?php echo hodima_seo_discover_info_button( 'hodima-dc-help-topics', 'موضوعات اصلی' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escape‌شده در تابع ?>
				</div>
				<p class="hodima-dc__help" id="hodima-dc-help-topics" hidden>به گوگل می‌گوید صفحه درباره چیست. Enter یا ویرگول = افزودن، × = حذف. برای دقت بیشتر بعد از نام، آدرس ویکی‌داده یا شناسه‌اش را بنویسید: <code>کلیپس Q1234</code></p>
				<div class="hodima-dc__chips" data-hodima-dc-chips>
					<?php foreach ( $data['entity_items'] as $item ) : ?>
						<span class="hodima-dc__chip" data-value="<?php echo esc_attr( trim( $item['name'] . ' ' . $item['url'] ) ); ?>">
							<span><?php echo esc_html( $item['name'] ); ?></span>
							<?php if ( '' !== $item['url'] ) : ?><span class="hodima-dc__chip-link" title="<?php echo esc_attr( $item['url'] ); ?>">ویکی</span><?php endif; ?>
							<button type="button" class="hodima-dc__chip-x" data-hodima-dc-chip-remove aria-label="<?php echo esc_attr( 'حذف ' . $item['name'] ); ?>">×</button>
						</span>
					<?php endforeach; ?>
					<input type="text" id="hodima-dc-topic-input" class="hodima-dc__chip-input" placeholder="افزودن موضوع…" data-hodima-dc-topic-input>
				</div>
				<textarea name="hodima_discover[entities]" hidden data-hodima-dc-entities><?php echo esc_textarea( hodima_seo_discover_format_entities( $data['entity_items'], "\n" ) ); ?></textarea>
			</div>
		</section>

		<?php /* ── تب «آمادگی» ── */ ?>
		<section class="hodima-dc__panel" role="tabpanel" id="hodima-dc-panel-checks" aria-labelledby="hodima-dc-tab-checks" data-hodima-dc-panel="checks" hidden>
			<ul class="hodima-dc__checks" data-hodima-dc-issues>
				<?php foreach ( $issues as $check ) : ?>
					<?php hodima_seo_discover_render_check( $check ); ?>
				<?php endforeach; ?>
			</ul>
			<p class="hodima-dc__all-ok" data-hodima-dc-all-ok <?php echo $issues ? 'hidden' : ''; ?>><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span> همه موارد آمادگی درست است.</p>
			<details class="hodima-dc__passed" data-hodima-dc-passed-box <?php echo $passed ? '' : 'hidden'; ?>>
				<summary><span class="dashicons dashicons-yes" aria-hidden="true"></span> <span data-hodima-dc-ok-count><?php echo esc_html( $num( count( $passed ) ) ); ?></span> مورد درست</summary>
				<ul class="hodima-dc__checks" data-hodima-dc-passed>
					<?php foreach ( $passed as $check ) : ?>
						<?php hodima_seo_discover_render_check( $check ); ?>
					<?php endforeach; ?>
				</ul>
			</details>
			<p class="hodima-dc__foot">Discover فید پیشنهادی گوگل در موبایل است و گوگل خودش صفحه‌ها را انتخاب می‌کند؛ این موارد شانس انتخاب را بالا می‌برند.<?php if ( '' !== $report ) : ?> <a href="<?php echo esc_url( $report ); ?>">گزارش همه صفحه‌ها</a><?php endif; ?></p>
		</section>

		<?php /* ── تب «آمار» ── */ ?>
		<section class="hodima-dc__panel" role="tabpanel" id="hodima-dc-panel-stats" aria-labelledby="hodima-dc-tab-stats" data-hodima-dc-panel="stats" hidden>
			<?php if ( null !== $stats ) : ?>
				<div class="hodima-dc__tiles">
					<div class="hodima-dc__tile"><span>کلیک</span><strong><?php echo esc_html( $num( $stats['clicks'] ) ); ?></strong></div>
					<div class="hodima-dc__tile"><span>نمایش</span><strong><?php echo esc_html( $num( $stats['impressions'] ) ); ?></strong></div>
					<div class="hodima-dc__tile"><span>نرخ کلیک</span><strong><?php echo esc_html( $stats['impressions'] ? number_format_i18n( 100 * $stats['clicks'] / $stats['impressions'], 1 ) . '٪' : '—' ); ?></strong></div>
					<div class="hodima-dc__tile"><span>نمایش نسبت به ۲۸ روز قبل</span><strong><?php echo esc_html( null !== $change ? hodima_seo_discover_change_text( $change ) : ( 0 === $stats['prev_impressions'] && $stats['impressions'] > 0 ? 'تازه' : '—' ) ); ?></strong></div>
				</div>
				<p class="hodima-dc__foot"><?php echo esc_html( sprintf( 'Discover، ۲۸ روز آخر تا %s (Search Console، با دو روز تاخیر).', $all_stats['end'] ) ); ?><?php if ( '' !== $report ) : ?> <a href="<?php echo esc_url( add_query_arg( 'tab', 'stats', $report ) ); ?>">آمار همه صفحه‌ها</a><?php endif; ?></p>
			<?php else : ?>
				<p class="hodima-dc__empty">
					<span class="dashicons dashicons-chart-area" aria-hidden="true"></span>
					<?php if ( ! $published ) : ?>
						آمار Discover بعد از انتشار و نمایش صفحه در گوگل اینجا می‌آید.
					<?php elseif ( ! $all_stats['fetched'] ) : ?>
						آمار Search Console هنوز گرفته نشده است.<?php if ( '' !== $report ) : ?> <a href="<?php echo esc_url( add_query_arg( 'tab', 'stats', $report ) ); ?>">اتصال به Search Console</a><?php endif; ?>
					<?php else : ?>
						این صفحه در ۲۸ روز آخر در Discover نمایش نداشته است.
					<?php endif; ?>
				</p>
			<?php endif; ?>
		</section>

		<?php /* ── پیش‌نمایش (پنجره) ── */ ?>
		<dialog class="hodima-dc__dialog" aria-labelledby="hodima-dc-dialog-title" data-hodima-dc-dialog>
			<div class="hodima-dc__dialog-head">
				<h2 id="hodima-dc-dialog-title">پیش‌نمایش کارت Discover</h2>
				<button type="button" class="hodima-dc__icon-btn" data-hodima-dc-close aria-label="بستن"><span class="dashicons dashicons-no-alt" aria-hidden="true"></span></button>
			</div>
			<div class="hodima-dc__segment" role="group" aria-label="نوع پیش‌نمایش">
				<button type="button" aria-pressed="true" data-hodima-dc-view="phone"><span class="dashicons dashicons-smartphone" aria-hidden="true"></span> گوشی</button>
				<button type="button" aria-pressed="false" data-hodima-dc-view="desktop"><span class="dashicons dashicons-desktop" aria-hidden="true"></span> دسکتاپ</button>
				<button type="button" aria-pressed="false" data-hodima-dc-view="social"><span class="dashicons dashicons-share" aria-hidden="true"></span> اشتراک‌گذاری</button>
			</div>
			<div class="hodima-dc__stage" data-hodima-dc-stage>
				<?php foreach ( [ 'phone', 'desktop', 'social' ] as $view ) : ?>
					<article class="hodima-dc__card hodima-dc__card--<?php echo esc_attr( $view ); ?>" data-hodima-dc-pane="<?php echo esc_attr( $view ); ?>" <?php echo 'phone' === $view ? '' : 'hidden'; ?>>
						<div class="hodima-dc__card-img">
							<img src="<?php echo esc_url( $shown['url'] ?? '' ); ?>" alt="" data-hodima-dc-preview-img <?php echo null !== $shown ? '' : 'hidden'; ?>>
							<span class="hodima-dc__card-noimg" data-hodima-dc-preview-noimg <?php echo null !== $shown ? 'hidden' : ''; ?>>بدون تصویر؛ Discover کارت بی‌تصویر را تقریبا نشان نمی‌دهد</span>
						</div>
						<div class="hodima-dc__card-body">
							<span class="hodima-dc__card-site">
								<?php if ( '' !== $icon && 'social' !== $view ) : ?><img src="<?php echo esc_url( $icon ); ?>" alt="" width="16" height="16"><?php endif; ?>
								<?php echo esc_html( 'social' === $view ? strtoupper( $host ) : $host ); ?>
							</span>
							<strong class="hodima-dc__card-title" data-hodima-dc-preview-title><?php echo esc_html( $title ); ?></strong>
							<?php if ( 'social' === $view && '' !== $summary ) : ?>
								<span class="hodima-dc__card-desc"><?php echo esc_html( $summary ); ?></span>
							<?php endif; ?>
						</div>
					</article>
				<?php endforeach; ?>
			</div>
			<p class="hodima-dc__dialog-note">نمای تقریبی؛ گوگل و شبکه‌ها ممکن است عنوان یا برش تصویر را خودشان کوتاه یا جابه‌جا کنند. تغییرها بعد از ذخیره صفحه اعمال می‌شوند.</p>
		</dialog>
	</div>
	<?php
}

/**
 * یک ردیف «آمادگی»: خلاصه یک‌خطی (آیکون + عنوان) که با کلیک توضیح کامل را
 * باز می‌کند، و دکمه «رفع» اگر جای رفع مشخص است.
 *
 * @param array{key: string, status: string, label: string, detail: string, link?: string} $check
 */
function hodima_seo_discover_render_check( array $check ): void {
	$icons = [ 'ok' => 'dashicons-yes-alt', 'warn' => 'dashicons-warning', 'error' => 'dashicons-dismiss' ];
	$link  = (string) ( $check['link'] ?? '' );
	?>
	<li class="hodima-dc__check is-<?php echo esc_attr( $check['status'] ); ?>" data-hodima-dc-check="<?php echo esc_attr( $check['key'] ); ?>">
		<details>
			<summary>
				<span class="dashicons <?php echo esc_attr( $icons[ $check['status'] ] ?? 'dashicons-info' ); ?>" aria-hidden="true"></span>
				<span class="hodima-dc__check-label"><?php echo esc_html( $check['label'] ); ?></span>
			</summary>
			<p class="hodima-dc__check-text" data-hodima-dc-check-text><?php echo esc_html( $check['detail'] ); ?></p>
		</details>
		<?php if ( '' !== $link ) : ?>
			<a class="hodima-dc__fix" href="<?php echo esc_url( $link ); ?>" <?php echo 'ok' === $check['status'] ? 'hidden' : ''; ?>>رفع</a>
		<?php endif; ?>
	</li>
	<?php
}

/*
 * کادر تازه بعد از ذخیره ویرایشگر بلوکی. ویرایشگر بلوکی با «به‌روزرسانی» صفحه
 * را دوباره بارگذاری نمی‌کند: فیلدهای کادر را جدا می‌فرستد ولی خود کادر را
 * از سرور نمی‌گیرد. باگ تا SEO 2.1.3: بعد از تغییر تصویر و ذخیره، کادر همچنان
 * «برش بعد از ذخیره»، امتیاز و آمادگی کهنه را نشان می‌داد تا صفحه دوباره
 * بارگذاری شود. حالا JS بعد از پایان ذخیره کادر را از اینجا می‌گیرد
 * (discover-admin.js: refreshBox).
 */
add_action( 'wp_ajax_hodima_discover_box', static function (): void {

	$context = isset( $_POST['context'] ) && 'term' === $_POST['context'] ? 'term' : 'post'; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce پایین (نامش به همین دو مقدار بسته است)
	$id      = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- همان
	$nonce   = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
	$target  = 'term' === $context ? get_term( $id ) : get_post( $id );
	$allowed = 'term' === $context
		? $target instanceof WP_Term && current_user_can( 'edit_term', $id ) && hodima_seo_discover_for_term( $id )
		: $target instanceof WP_Post && current_user_can( 'edit_post', $id ) && hodima_seo_discover_for_post( $id );

	if ( ! $allowed || ! wp_verify_nonce( $nonce, 'hodima_discover_box_' . $context . '_' . $id ) ) {
		wp_send_json_error( null, 403 );
	}

	ob_start();
	hodima_seo_discover_render_fields( $target );
	wp_send_json_success( [ 'html' => (string) ob_get_clean() ] );
} );

/** ذخیره کادر (نوشته/برگه/محصول یا دسته). */
function hodima_seo_discover_save( int $object_id, string $context ): void {

	$context = hodima_seo_discover_context( $context );
	$nonce   = isset( $_POST['hodima_discover_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['hodima_discover_nonce'] ) ) : '';

	if ( ! wp_verify_nonce( $nonce, 'hodima_discover_save_' . $context . '_' . $object_id ) ) {
		return;
	}

	$allowed = 'term' === $context
		? hodima_seo_discover_for_term( $object_id ) && current_user_can( 'edit_term', $object_id )
		: hodima_seo_discover_for_post( $object_id ) && current_user_can( 'edit_post', $object_id );

	if ( ! $allowed ) {
		return;
	}

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- هر فیلد پایین جداگانه پاک‌سازی می‌شود
	$in  = isset( $_POST['hodima_discover'] ) && is_array( $_POST['hodima_discover'] ) ? wp_unslash( $_POST['hodima_discover'] ) : [];
	$get = static fn( string $key ): string => isset( $in[ $key ] ) && is_scalar( $in[ $key ] ) ? trim( (string) $in[ $key ] ) : '';

	// نبودن فیلدها = دست نزدن به داده (مثلا فرمی که کادر را نداشت)
	if ( '1' !== $get( 'present' ) ) {
		return;
	}

	$image_id = absint( $get( 'image_id' ) );

	$values = [
		'title'    => sanitize_text_field( $get( 'title' ) ),
		'entities' => hodima_seo_discover_format_entities( hodima_seo_discover_entity_items( sanitize_textarea_field( $get( 'entities' ) ) ) ),
		'image_id' => $image_id && wp_attachment_is_image( $image_id ) ? $image_id : 0,
	];

	foreach ( $values as $field => $value ) {
		$key = hodima_seo_discover_meta_key( $field, $context );
		( '' === $value || 0 === $value )
			? delete_metadata( $context, $object_id, $key )
			: update_metadata( $context, $object_id, $key, $value );
	}
}

add_action( 'save_post', static function ( int $post_id ): void {
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}
	hodima_seo_discover_save( $post_id, 'post' );
}, 10 );

/* =====================================================================
 * نمایه کاربر: «نویسنده در گوگل» (E-E-A-T)
 * ===================================================================== */

/** فیلدهای معرفی نویسنده در «کاربران ← نمایه». */
function hodima_seo_discover_profile_fields( WP_User $user ): void {

	$author = hodima_seo_discover_author( (int) $user->ID );
	wp_nonce_field( 'hodima_discover_author_' . $user->ID, 'hodima_discover_author_nonce' );
	?>
	<h2>نویسنده در گوگل (Google Discover)</h2>
	<p class="description">گوگل برای نمایش مقاله در Discover و نتایج جستجو به «چه کسی نوشته و چرا قابل اعتماد است» وزن می‌دهد. این اطلاعات در اسکیمای نویسنده (Person) همه مقاله‌هایش می‌رود. بیوگرافی همان «اطلاعات زندگی‌نامه» بالاست.</p>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><label for="hodima-author-job">سمت و تخصص</label></th>
			<td>
				<input type="text" class="regular-text" id="hodima-author-job" name="hodima_author[job_title]" value="<?php echo esc_attr( $author['job_title'] ); ?>" placeholder="کارشناس اکسسوری مو و فروش عمده">
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="hodima-author-knows">حوزه‌های تخصص</label></th>
			<td>
				<textarea class="large-text" rows="3" id="hodima-author-knows" name="hodima_author[knows_about]" placeholder="<?php echo esc_attr( "اکسسوری مو\nواردات و پخش عمده" ); ?>"><?php echo esc_textarea( implode( "\n", $author['knows_about'] ) ); ?></textarea>
				<p class="description">هر مورد در یک خط.</p>
			</td>
		</tr>
		<tr>
			<th scope="row"><label for="hodima-author-sameas">پروفایل‌های معتبر</label></th>
			<td>
				<textarea class="large-text" rows="3" id="hodima-author-sameas" name="hodima_author[same_as]" dir="ltr" placeholder="https://www.instagram.com/…"><?php echo esc_textarea( implode( "\n", $author['same_as'] ) ); ?></textarea>
				<p class="description">هر آدرس در یک خط: اینستاگرام، لینکدین، آپارات، صفحه ویکی‌پدیا، مصاحبه‌ها. فقط صفحه‌هایی که واقعا مال همین شخص است.</p>
			</td>
		</tr>
	</table>
	<?php
}

add_action( 'show_user_profile', 'hodima_seo_discover_profile_fields' );
add_action( 'edit_user_profile', 'hodima_seo_discover_profile_fields' );

/** ذخیره فیلدهای معرفی نویسنده. */
function hodima_seo_discover_profile_save( int $user_id ): void {

	$nonce = isset( $_POST['hodima_discover_author_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['hodima_discover_author_nonce'] ) ) : '';

	if ( ! wp_verify_nonce( $nonce, 'hodima_discover_author_' . $user_id ) || ! current_user_can( 'edit_user', $user_id ) ) {
		return;
	}

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- هر فیلد پایین جداگانه پاک‌سازی می‌شود
	$in  = isset( $_POST['hodima_author'] ) && is_array( $_POST['hodima_author'] ) ? wp_unslash( $_POST['hodima_author'] ) : [];
	$get = static fn( string $key ): string => isset( $in[ $key ] ) && is_scalar( $in[ $key ] ) ? (string) $in[ $key ] : '';

	$lines = static fn( string $raw ): array => array_values( array_filter( array_map( 'trim', preg_split( '/[\r\n]+/', $raw ) ?: [] ) ) );

	$values = [
		HODIMA_SEO_DISCOVER_AUTHOR_META['job_title']   => sanitize_text_field( $get( 'job_title' ) ),
		HODIMA_SEO_DISCOVER_AUTHOR_META['knows_about'] => implode( "\n", array_map( 'sanitize_text_field', $lines( $get( 'knows_about' ) ) ) ),
		HODIMA_SEO_DISCOVER_AUTHOR_META['same_as']     => implode( "\n", array_filter( array_map( 'hodima_seo_discover_clean_url', $lines( $get( 'same_as' ) ) ) ) ),
	];

	foreach ( $values as $key => $value ) {
		'' === $value ? delete_user_meta( $user_id, $key ) : update_user_meta( $user_id, $key, $value );
	}
}

add_action( 'personal_options_update', 'hodima_seo_discover_profile_save' );
add_action( 'edit_user_profile_update', 'hodima_seo_discover_profile_save' );
