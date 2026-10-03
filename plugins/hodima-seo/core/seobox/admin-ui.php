<?php
/**
 * SeoBox — shared editor UI (post meta box + term postbox)
 * Path: core/seobox/admin-ui.php
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * داده کادر برای یک نوشته یا ترم.
 *
 * @param string $type 'post' یا 'term'
 * @return array<string, mixed>
 */
function seobox_editor_data( int $id, string $type ): array {

	$meta  = static fn( string $key ): string => (string) get_metadata( $type, $id, '_seobox_' . $key, true );
	$front = 'post' === $type && seobox_is_front_page_post( $id );

	if ( 'term' === $type ) {
		$term     = get_term( $id );
		$link     = $term instanceof WP_Term ? get_term_link( $term ) : '';
		$url      = is_string( $link ) ? $link : '';
		$fallback = $term instanceof WP_Term ? seobox_term_fallback_description( $term ) : '';
	} else {
		$post     = get_post( $id );
		$url      = $front ? home_url( '/' ) : (string) get_permalink( $id );
		$fallback = $post instanceof WP_Post ? seobox_post_fallback_description( $post ) : '';
		if ( '' === $fallback && $front ) {
			$fallback = seobox_plain_text( wp_specialchars_decode( (string) get_bloginfo( 'description' ), ENT_QUOTES ) );
		}
	}

	return [
		'type'        => $type,
		'title'       => $meta( 'title' ),
		'description' => $meta( 'description' ),
		'canonical'   => $meta( 'canonical' ),
		'robots'      => seobox_object_robots( $id, $type ),
		'legacy_keys' => seobox_legacy_noindex_keys( $id, $type ),
		'adv_snippet' => $meta( 'adv_snippet' ),
		'adv_video'   => $meta( 'adv_video' ),
		'adv_image'   => in_array( $meta( 'adv_image' ), [ 'none', 'standard' ], true ) ? $meta( 'adv_image' ) : 'large',
		'current_url' => $url,
		'fallback'    => $fallback,
		'is_front'    => $front,
	];
}

/**
 * نماد سایت در پیش‌نمایش گوگل: لوگوی قالب («تنظیمات قالب هدیما» یا
 * لوگوی سفارشی وردپرس) ← آیکون سایت ← رشته خالی (حرف اول نام سایت).
 */
function seobox_brand_icon_url(): string {

	$logo_id = function_exists( 'hodima_setting' ) ? (int) hodima_setting( 'logo_id' ) : 0;
	$logo_id = $logo_id ?: (int) get_theme_mod( 'custom_logo' );

	if ( $logo_id && wp_attachment_is_image( $logo_id ) ) {
		$url = wp_get_attachment_image_url( $logo_id, 'thumbnail' ) ?: wp_get_attachment_image_url( $logo_id, 'full' );
		if ( is_string( $url ) && '' !== $url ) {
			return $url;
		}
	}

	return (string) get_site_icon_url( 64 );
}

/**
 * کادر سئو.
 *
 * سه تب: «محتوا و پیش‌نمایش»، «ایندکس / نوایندکس» (سبز = ایندکس، قرمز =
 * نوایندکس، زنده با انتخاب) و «canonical». راهنمای فیلدها داخل خود
 * کادرهاست (placeholder) و با پر شدن کادر محو می‌شود؛ زیر فیلدها فضای
 * خالی نمی‌ماند. تب‌ها دسترس‌پذیرند (فلش‌ها، aria-controls) و بدون
 * جاوااسکریپت همه بخش‌ها زیر هم دیده می‌شوند.
 * فیلد canonical عمدا type="text" است: type="url" مرورگر آدرس نسبی را
 * نمی‌پذیرفت و *کل فرم ذخیره نوشته* را متوقف می‌کرد.
 *
 * @param array<string, mixed> $data خروجی seobox_editor_data()
 */
function seobox_render_html( array $data ): void {

	wp_nonce_field( 'seobox_save_action', 'seobox_nonce', false );

	$robots   = (array) $data['robots'];
	$advanced = seobox_can_edit_advanced();
	$fallback = (string) $data['fallback'];
	$is_front = ! empty( $data['is_front'] );
	$pattern  = seobox_default_title_pattern( $is_front );
	$vars     = seobox_static_variables();
	$icon     = seobox_brand_icon_url();
	$noindex  = ! $robots['index'];

	$preview = [
		'vars'     => $vars,
		'pattern'  => $pattern,
		'fallback' => $fallback,
		// صفحه اصلی: %title% همان نام سایت است (مثل سایت)
		'title'    => $is_front ? $vars['%sitename%'] : null,
	];

	// وضعیت ایندکس در رنگ تب «ایندکس / نوایندکس» است؛ نوار فقط موارد خاص
	$status = [];
	if ( ! $robots['follow'] ) {
		$status[] = [ 'warn', 'editor-unlink', 'nofollow' ];
	}
	if ( '' !== $data['canonical'] ) {
		$status[] = [ 'info', 'admin-links', 'canonical سفارشی' ];
	}

	$hints = [
		'%title%'        => 'نام همین صفحه',
		'%sitename%'     => 'نام سایت',
		'%sitedesc%'     => 'شعار سایت',
		'%sep%'          => 'جداکننده',
		'%page%'         => 'شماره صفحه (فقط صفحه ۲ به بعد)',
		'%currentyear%'  => 'سال شمسی',
		'%currentmonth%' => 'ماه شمسی',
		'%gyear%'        => 'سال میلادی',
	];

	$title_hint = 'پیش‌فرض: ' . $pattern . ( $is_front ? ' (نام سایت و شعار)' : '' );
	$desc_hint  = '' !== $fallback ? 'پیش‌فرض: ' . $fallback : 'اگر خالی بماند، گوگل خودش از متن صفحه انتخاب می‌کند.';

	$tabs = [ [ 'general', 'search', 'محتوا و پیش‌نمایش', '' ] ];
	if ( $advanced ) {
		$tabs[] = [ 'robots', $noindex ? 'hidden' : 'visibility', 'ایندکس / نوایندکس', $noindex ? 'noindex' : 'index' ];
		$tabs[] = [ 'canonical', 'admin-links', 'canonical', '' ];
	}
	?>
	<div class="seobox" data-seobox="<?php echo esc_attr( (string) wp_json_encode( $preview ) ); ?>" data-seobox-external="<?php echo $robots['external'] ? '1' : '0'; ?>">

		<?php if ( $status ) : ?>
			<ul class="seobox__status" aria-label="وضعیت سئو">
				<?php foreach ( $status as [ $tone, $chip_icon, $label ] ) : ?>
					<li class="seobox__chip seobox__chip--<?php echo esc_attr( $tone ); ?>"><span class="dashicons dashicons-<?php echo esc_attr( $chip_icon ); ?>" aria-hidden="true"></span><?php echo esc_html( $label ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<div class="seobox__tabs" role="tablist" aria-label="تنظیمات سئو">
			<?php foreach ( $tabs as $i => [ $key, $tab_icon, $label, $state ] ) : ?>
				<button type="button" class="seobox__tab<?php echo '' !== $state ? ' seobox__tab--state' : ''; ?>" role="tab" id="seobox-tab-<?php echo esc_attr( $key ); ?>" aria-controls="seobox-panel-<?php echo esc_attr( $key ); ?>" aria-selected="<?php echo 0 === $i ? 'true' : 'false'; ?>"<?php echo 0 === $i ? '' : ' tabindex="-1"'; ?><?php echo '' !== $state ? ' data-state="' . esc_attr( $state ) . '"' : ''; ?>>
					<span class="dashicons dashicons-<?php echo esc_attr( $tab_icon ); ?>" aria-hidden="true"></span><?php echo esc_html( $label ); ?>
				</button>
			<?php endforeach; ?>
		</div>

		<section class="seobox__panel" id="seobox-panel-general" role="tabpanel" aria-labelledby="seobox-tab-general">

			<div class="seobox__serp" aria-hidden="true">
				<div class="seobox__serp-site">
					<span class="seobox__serp-icon<?php echo '' !== $icon ? ' has-image' : ''; ?>">
						<?php if ( '' !== $icon ) : ?>
							<img src="<?php echo esc_url( $icon ); ?>" alt="" loading="lazy" decoding="async">
						<?php else : ?>
							<?php echo esc_html( mb_substr( $vars['%sitename%'], 0, 1 ) ); ?>
						<?php endif; ?>
					</span>
					<span>
						<span class="seobox__serp-name"><?php echo esc_html( $vars['%sitename%'] ); ?></span>
						<span class="seobox__serp-url" dir="ltr"><?php echo esc_html( seobox_display_url( (string) $data['current_url'] ) ); ?></span>
					</span>
				</div>
				<div class="seobox__serp-title" data-seobox-out="title"></div>
				<div class="seobox__serp-desc" data-seobox-out="description"></div>
			</div>

			<div class="seobox__field">
				<div class="seobox__label-row">
					<label for="seobox_title">عنوان سئو</label>
					<span class="seobox__counter" id="seobox_title_counter" data-seobox-counter="title"></span>
				</div>
				<input type="text" class="seobox__input" id="seobox_title" name="seobox_title" value="<?php echo esc_attr( (string) $data['title'] ); ?>" placeholder="<?php echo esc_attr( $title_hint ); ?>" aria-describedby="seobox_title_counter" autocomplete="off">
				<div class="seobox__meter" aria-hidden="true"><span data-seobox-meter="title"></span></div>
			</div>

			<div class="seobox__field">
				<div class="seobox__label-row">
					<label for="seobox_description">توضیحات متا</label>
					<span class="seobox__counter" id="seobox_description_counter" data-seobox-counter="description"></span>
				</div>
				<?php /* دکمه‌های متغیر داخل همان کادر توضیحات (پایین آن)، نه یک ردیف جدا زیر فیلد */ ?>
				<div class="seobox__composer">
					<textarea class="seobox__input seobox__input--bare" id="seobox_description" name="seobox_description" rows="3" aria-describedby="seobox_description_counter" placeholder="<?php echo esc_attr( $desc_hint ); ?>"><?php echo esc_textarea( (string) $data['description'] ); ?></textarea>
					<div class="seobox__vars">
						<span class="seobox__vars-label" id="seobox_vars_label">درج متغیر:</span>
						<div class="seobox__vars-list" role="group" aria-labelledby="seobox_vars_label">
							<?php foreach ( $hints as $var => $hint ) : ?>
								<button type="button" class="seobox__var" data-seobox-var="<?php echo esc_attr( $var ); ?>" title="<?php echo esc_attr( $hint . ' — در آخرین کادر فعال (عنوان یا توضیحات) درج می‌شود' ); ?>" dir="ltr"><?php echo esc_html( $var ); ?></button>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
				<div class="seobox__meter" aria-hidden="true"><span data-seobox-meter="description"></span></div>
			</div>
		</section>

		<?php if ( $advanced ) : ?>
			<section class="seobox__panel" id="seobox-panel-robots" role="tabpanel" aria-labelledby="seobox-tab-robots">

				<?php if ( $robots['external'] ) : ?>
					<div class="seobox__alert" role="note">
						<span class="dashicons dashicons-warning" aria-hidden="true"></span>
						<div>
							<p>این صفحه با یک تنظیم <strong>noindex قدیمی</strong> (افزونه سئوی قبلی) روی سایت noindex چاپ می‌شود و از سایت‌مپ حذف است، حتی اگر پایین «ایندکس» انتخاب باشد.</p>
							<?php if ( $data['legacy_keys'] ) : ?>
								<label class="seobox__check">
									<input type="checkbox" name="seobox_clear_legacy_noindex" value="1" data-seobox-clear-legacy>
									حذف تنظیم قدیمی هنگام ذخیره (<?php echo implode( '، ', array_map( static fn( string $k ): string => '<code dir="ltr">' . esc_html( $k ) . '</code>', $data['legacy_keys'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>)
								</label>
							<?php endif; ?>
						</div>
					</div>
				<?php endif; ?>

				<?php
				$segments = [
					[ 'seobox_robot_index', 'نمایش در نتایج جستجو', $robots['index'] || $robots['external'], [ 'index', 'visibility', 'ایندکس' ], [ 'noindex', 'hidden', 'نوایندکس' ] ],
					[ 'seobox_robot_follow', 'لینک‌های این صفحه', $robots['follow'], [ 'follow', 'admin-links', 'فالو' ], [ 'nofollow', 'editor-unlink', 'نوفالو' ] ],
				];
				?>
				<div class="seobox__grid">
					<?php foreach ( $segments as [ $name, $legend, $positive, $yes, $no ] ) : ?>
						<fieldset class="seobox__segment">
							<legend><?php echo esc_html( $legend ); ?></legend>
							<span class="seobox__segment-options">
								<?php foreach ( [ [ $yes, $positive ], [ $no, ! $positive ] ] as [ [ $value, $opt_icon, $label ], $on ] ) : ?>
									<label class="seobox__option seobox__option--<?php echo str_starts_with( $value, 'no' ) ? 'off' : 'on'; ?>">
										<input type="radio" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" <?php checked( $on ); ?>>
										<span><span class="dashicons dashicons-<?php echo esc_attr( $opt_icon ); ?>" aria-hidden="true"></span><?php echo esc_html( $label ); ?></span>
									</label>
								<?php endforeach; ?>
							</span>
						</fieldset>
					<?php endforeach; ?>
				</div>

				<div class="seobox__grid seobox__grid--3">
					<div class="seobox__field">
						<label for="seobox_adv_snippet">حداکثر طول متن نتیجه</label>
						<input type="number" class="seobox__input" min="0" step="1" id="seobox_adv_snippet" name="seobox_adv_snippet" value="<?php echo esc_attr( (string) $data['adv_snippet'] ); ?>" placeholder="بدون محدودیت" dir="ltr">
					</div>
					<div class="seobox__field">
						<label for="seobox_adv_video">پیش‌نمایش ویدیو (ثانیه)</label>
						<input type="number" class="seobox__input" min="0" step="1" id="seobox_adv_video" name="seobox_adv_video" value="<?php echo esc_attr( (string) $data['adv_video'] ); ?>" placeholder="بدون محدودیت" dir="ltr">
					</div>
					<div class="seobox__field">
						<label for="seobox_adv_image">پیش‌نمایش تصویر</label>
						<select class="seobox__input" id="seobox_adv_image" name="seobox_adv_image" title="تصویر «بزرگ» برای Google Discover لازم است">
							<option value="large" <?php selected( $data['adv_image'], 'large' ); ?>>بزرگ (لازم برای Discover)</option>
							<option value="standard" <?php selected( $data['adv_image'], 'standard' ); ?>>استاندارد</option>
							<option value="none" <?php selected( $data['adv_image'], 'none' ); ?>>هیچ‌کدام</option>
						</select>
					</div>
				</div>
			</section>

			<section class="seobox__panel" id="seobox-panel-canonical" role="tabpanel" aria-labelledby="seobox-tab-canonical">
				<div class="seobox__field">
					<label for="seobox_canonical">آدرس قانونی <span dir="ltr">(Canonical URL)</span></label>
					<input type="text" inputmode="url" class="seobox__input seobox__input--ltr" id="seobox_canonical" name="seobox_canonical" value="<?php echo esc_attr( seobox_display_url( (string) $data['canonical'] ) ); ?>" placeholder="<?php echo esc_attr( seobox_display_url( (string) $data['current_url'] ) ); ?>" dir="ltr" aria-describedby="seobox_canonical_help" autocomplete="off" spellcheck="false">
					<p class="seobox__help" id="seobox_canonical_help">فقط اگر این محتوا نسخه تکراری صفحه دیگری است پر کنید؛ خالی = آدرس خود صفحه. نامک فارسی و مسیر نسبی (<code dir="ltr">/path/</code>) هم پذیرفته می‌شود.</p>
				</div>
			</section>
		<?php else : ?>
			<p class="seobox__help seobox__help--note"><span class="dashicons dashicons-lock" aria-hidden="true"></span> ایندکس و canonical فقط توسط ویرایشگر یا مدیر سایت تنظیم می‌شوند.</p>
		<?php endif; ?>
	</div>
	<?php
}
