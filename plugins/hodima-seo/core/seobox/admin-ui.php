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
 * کادر سئو.
 *
 * بازطراحی: نوار وضعیت، تب‌های دسترس‌پذیر (فلش‌ها، aria-controls)، بدون
 * جاوااسکریپت هر دو بخش دیده می‌شوند، متغیرها با یک کلیک درج می‌شوند،
 * پیش‌نمایش با همان مقادیر سرور (سال شمسی، جداکننده، شعار).
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

	$preview = [
		'vars'     => $vars,
		'pattern'  => $pattern,
		'fallback' => $fallback,
		// صفحه اصلی: %title% همان نام سایت است (مثل سایت)
		'title'    => $is_front ? $vars['%sitename%'] : null,
	];

	$status = [];
	if ( ! $robots['index'] ) {
		$status[] = [ 'error', 'hidden', $robots['external'] ? 'noindex (تنظیم قدیمی)' : 'noindex — در گوگل نمایش داده نمی‌شود' ];
	} else {
		$status[] = [ 'ok', 'visibility', 'قابل ایندکس' ];
	}
	if ( ! $robots['follow'] ) {
		$status[] = [ 'warn', 'admin-links', 'nofollow' ];
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
	?>
	<div class="seobox" data-seobox="<?php echo esc_attr( (string) wp_json_encode( $preview ) ); ?>">

		<ul class="seobox__status" aria-label="وضعیت سئو">
			<?php foreach ( $status as [ $tone, $icon, $label ] ) : ?>
				<li class="seobox__chip seobox__chip--<?php echo esc_attr( $tone ); ?>"><span class="dashicons dashicons-<?php echo esc_attr( $icon ); ?>" aria-hidden="true"></span><?php echo esc_html( $label ); ?></li>
			<?php endforeach; ?>
		</ul>

		<div class="seobox__tabs" role="tablist" aria-label="تنظیمات سئو">
			<button type="button" class="seobox__tab" role="tab" id="seobox-tab-general" aria-controls="seobox-panel-general" aria-selected="true">
				<span class="dashicons dashicons-search" aria-hidden="true"></span>محتوا و پیش‌نمایش
			</button>
			<?php if ( $advanced ) : ?>
				<button type="button" class="seobox__tab" role="tab" id="seobox-tab-advanced" aria-controls="seobox-panel-advanced" aria-selected="false" tabindex="-1">
					<span class="dashicons dashicons-admin-generic" aria-hidden="true"></span>ربات‌ها و canonical
				</button>
			<?php endif; ?>
		</div>

		<section class="seobox__panel" id="seobox-panel-general" role="tabpanel" aria-labelledby="seobox-tab-general">

			<div class="seobox__serp" aria-hidden="true">
				<div class="seobox__serp-site">
					<span class="seobox__serp-icon"><?php echo esc_html( mb_substr( $vars['%sitename%'], 0, 1 ) ); ?></span>
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
				<input type="text" class="seobox__input" id="seobox_title" name="seobox_title" value="<?php echo esc_attr( (string) $data['title'] ); ?>" placeholder="<?php echo esc_attr( $pattern ); ?>" aria-describedby="seobox_title_counter seobox_title_help" autocomplete="off">
				<div class="seobox__meter" aria-hidden="true"><span data-seobox-meter="title"></span></div>
				<p class="seobox__help" id="seobox_title_help">خالی = <code dir="ltr"><?php echo esc_html( $pattern ); ?></code><?php echo $is_front ? ' (صفحه اصلی: نام سایت و شعار)' : ''; ?></p>
			</div>

			<div class="seobox__field">
				<div class="seobox__label-row">
					<label for="seobox_description">توضیحات متا</label>
					<span class="seobox__counter" id="seobox_description_counter" data-seobox-counter="description"></span>
				</div>
				<textarea class="seobox__input" id="seobox_description" name="seobox_description" rows="3" aria-describedby="seobox_description_counter seobox_description_help" placeholder="<?php echo esc_attr( '' !== $fallback ? $fallback : 'اگر خالی بماند، گوگل خودش از متن صفحه انتخاب می‌کند.' ); ?>"><?php echo esc_textarea( (string) $data['description'] ); ?></textarea>
				<div class="seobox__meter" aria-hidden="true"><span data-seobox-meter="description"></span></div>
				<p class="seobox__help" id="seobox_description_help"><?php echo '' !== $fallback ? 'خالی = متن کم‌رنگ داخل کادر (از خلاصه یا متن صفحه) استفاده می‌شود.' : 'خالی = توضیحی چاپ نمی‌شود.'; ?></p>
			</div>

			<div class="seobox__vars">
				<span class="seobox__vars-label" id="seobox_vars_label">درج متغیر:</span>
				<div class="seobox__vars-list" role="group" aria-labelledby="seobox_vars_label">
					<?php foreach ( $hints as $var => $hint ) : ?>
						<button type="button" class="seobox__var" data-seobox-var="<?php echo esc_attr( $var ); ?>" title="<?php echo esc_attr( $hint ); ?>" dir="ltr"><?php echo esc_html( $var ); ?></button>
					<?php endforeach; ?>
				</div>
			</div>
		</section>

		<?php if ( $advanced ) : ?>
			<section class="seobox__panel" id="seobox-panel-advanced" role="tabpanel" aria-labelledby="seobox-tab-advanced">

				<?php if ( $robots['external'] ) : ?>
					<div class="seobox__alert" role="note">
						<span class="dashicons dashicons-warning" aria-hidden="true"></span>
						<div>
							<p>این صفحه با یک تنظیم <strong>noindex قدیمی</strong> (افزونه سئوی قبلی) روی سایت noindex چاپ می‌شود و از سایت‌مپ حذف است، حتی اگر پایین «ایندکس» انتخاب باشد.</p>
							<?php if ( $data['legacy_keys'] ) : ?>
								<label class="seobox__check">
									<input type="checkbox" name="seobox_clear_legacy_noindex" value="1">
									حذف تنظیم قدیمی هنگام ذخیره (<?php echo implode( '، ', array_map( static fn( string $k ): string => '<code dir="ltr">' . esc_html( $k ) . '</code>', $data['legacy_keys'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>)
								</label>
							<?php endif; ?>
						</div>
					</div>
				<?php endif; ?>

				<div class="seobox__grid">
					<fieldset class="seobox__segment">
						<legend>نمایش در نتایج جستجو</legend>
						<label><input type="radio" name="seobox_robot_index" value="index" <?php checked( $robots['index'] || $robots['external'] ); ?>><span>ایندکس <small dir="ltr">index</small></span></label>
						<label><input type="radio" name="seobox_robot_index" value="noindex" <?php checked( ! $robots['index'] && ! $robots['external'] ); ?>><span>عدم ایندکس <small dir="ltr">noindex</small></span></label>
					</fieldset>

					<fieldset class="seobox__segment">
						<legend>لینک‌های این صفحه</legend>
						<label><input type="radio" name="seobox_robot_follow" value="follow" <?php checked( $robots['follow'] ); ?>><span>دنبال شوند <small dir="ltr">follow</small></span></label>
						<label><input type="radio" name="seobox_robot_follow" value="nofollow" <?php checked( ! $robots['follow'] ); ?>><span>دنبال نشوند <small dir="ltr">nofollow</small></span></label>
					</fieldset>
				</div>

				<div class="seobox__grid seobox__grid--3">
					<div class="seobox__field">
						<label for="seobox_adv_snippet">حداکثر طول متن نتیجه</label>
						<input type="number" class="seobox__input" min="0" step="1" id="seobox_adv_snippet" name="seobox_adv_snippet" value="<?php echo esc_attr( (string) $data['adv_snippet'] ); ?>" placeholder="بدون محدودیت" dir="ltr" aria-describedby="seobox_adv_help">
					</div>
					<div class="seobox__field">
						<label for="seobox_adv_video">پیش‌نمایش ویدیو (ثانیه)</label>
						<input type="number" class="seobox__input" min="0" step="1" id="seobox_adv_video" name="seobox_adv_video" value="<?php echo esc_attr( (string) $data['adv_video'] ); ?>" placeholder="بدون محدودیت" dir="ltr" aria-describedby="seobox_adv_help">
					</div>
					<div class="seobox__field">
						<label for="seobox_adv_image">پیش‌نمایش تصویر</label>
						<select class="seobox__input" id="seobox_adv_image" name="seobox_adv_image">
							<option value="large" <?php selected( $data['adv_image'], 'large' ); ?>>بزرگ (پیشنهادی)</option>
							<option value="standard" <?php selected( $data['adv_image'], 'standard' ); ?>>استاندارد</option>
							<option value="none" <?php selected( $data['adv_image'], 'none' ); ?>>هیچ‌کدام</option>
						</select>
					</div>
				</div>
				<p class="seobox__help" id="seobox_adv_help">خالی = بدون محدودیت (پیشنهادی). تصویر «بزرگ» برای Google Discover لازم است. این سه فقط برای صفحه قابل ایندکس چاپ می‌شوند.</p>

				<div class="seobox__field">
					<label for="seobox_canonical">آدرس قانونی <span dir="ltr">(Canonical URL)</span></label>
					<input type="text" inputmode="url" class="seobox__input seobox__input--ltr" id="seobox_canonical" name="seobox_canonical" value="<?php echo esc_attr( seobox_display_url( (string) $data['canonical'] ) ); ?>" placeholder="<?php echo esc_attr( seobox_display_url( (string) $data['current_url'] ) ); ?>" dir="ltr" aria-describedby="seobox_canonical_help" autocomplete="off" spellcheck="false">
					<p class="seobox__help" id="seobox_canonical_help">فقط اگر این محتوا نسخه تکراری صفحه دیگری است پر کنید. خالی = آدرس خود صفحه. نامک فارسی و مسیر نسبی (<code dir="ltr">/path/</code>) هم پذیرفته می‌شود.</p>
				</div>
			</section>
		<?php else : ?>
			<p class="seobox__help seobox__help--note"><span class="dashicons dashicons-lock" aria-hidden="true"></span> ربات‌ها و canonical فقط توسط ویرایشگر یا مدیر سایت تنظیم می‌شوند.</p>
		<?php endif; ?>
	</div>
	<?php
}
