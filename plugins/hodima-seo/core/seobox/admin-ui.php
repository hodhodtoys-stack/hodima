<?php
/**
 * SeoBox — shared editor UI
 * Path: core/seobox/admin-ui.php
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @param array  $data        مقادیر فعلی (به seobox_render_post_ui مراجعه کنید)
 * @param string $object_slug استفاده نمی‌شود؛ برای سازگاری با فراخوانی قدیمی نگه داشته شده
 */
function seobox_render_html( array $data, string $object_slug = '' ): void {

	wp_nonce_field( 'seobox_save_action', 'seobox_nonce' );

	$robots   = is_array( $data['robots'] ?? null ) && isset( $data['robots']['index'] )
		? $data['robots']
		: seobox_normalize_robots( $data['robots'] ?? [] );

	$site     = (string) get_bloginfo( 'name' );
	$url      = (string) ( $data['current_url'] ?? '' );
	$fallback = (string) ( $data['fallback'] ?? '' );
	?>
	<div class="seobox-wrapper-card">
		<div class="seobox-container">

			<div class="seobox-tabs" role="tablist">
				<button type="button" class="seobox-tab-btn active" data-target="general" role="tab" aria-selected="true">محتوا و سئو</button>
				<button type="button" class="seobox-tab-btn" data-target="advanced" role="tab" aria-selected="false">پیشرفته</button>
			</div>

			<div id="seobox-tab-general" class="seobox-tab-content active" role="tabpanel">

				<?php /* پیش‌نمایش نتیجه گوگل — با تایپ به‌روز می‌شود */ ?>
				<div class="seobox-serp" id="seobox_serp" aria-hidden="true"
					data-site="<?php echo esc_attr( $site ); ?>"
					data-fallback="<?php echo esc_attr( $fallback ); ?>">
					<div class="seobox-serp-url" dir="ltr"><?php echo esc_html( $url ); ?></div>
					<div class="seobox-serp-title" id="seobox_serp_title"></div>
					<div class="seobox-serp-desc" id="seobox_serp_desc"></div>
				</div>

				<div class="seobox-field-group">
					<label for="seobox_title">
						عنوان سئو
						<span class="seobox-counter" id="seobox_title_counter" aria-live="polite"></span>
					</label>
					<input type="text" id="seobox_title" name="seobox_title" value="<?php echo esc_attr( (string) $data['title'] ); ?>" placeholder="%title% %sep% %sitename%">
					<div class="seobox-meter"><span id="seobox_title_meter"></span></div>
				</div>

				<div class="seobox-field-group">
					<label for="seobox_description">
						توضیحات متا
						<span class="seobox-counter" id="seobox_description_counter" aria-live="polite"></span>
					</label>
					<textarea id="seobox_description" name="seobox_description" rows="3" placeholder="<?php echo esc_attr( '' !== $fallback ? 'اگر خالی بماند: ' . $fallback : 'اگر خالی بماند، گوگل خودش از متن صفحه انتخاب می‌کند.' ); ?>"><?php echo esc_textarea( (string) $data['description'] ); ?></textarea>
					<div class="seobox-meter"><span id="seobox_description_meter"></span></div>
				</div>

				<p class="seobox-hint">
					متغیرها: <code>%title%</code> <code>%sitename%</code> <code>%sep%</code> <code>%currentyear%</code>
				</p>
			</div>

			<div id="seobox-tab-advanced" class="seobox-tab-content" role="tabpanel">

				<?php
				/*
				 * رادیو به جای چک‌باکس. نسخه قبلی چهار چک‌باکس مستقل داشت که
				 * اجازه می‌داد Index و Noindex همزمان تیک بخورند؛ یک اسکریپت
				 * جداگانه سعی می‌کرد جلویش را بگیرد و اگر اسکریپت لود نمی‌شد،
				 * وضعیت متناقض ذخیره می‌شد.
				 */
				?>
				<div class="seobox-field-group">
					<label>وضعیت ربات‌ها</label>
					<div class="seobox-robot-checks">
						<fieldset class="seobox-radio-set">
							<legend>ایندکس</legend>
							<label class="seobox-check-item"><input type="radio" name="seobox_robot_index" value="index" <?php checked( $robots['index'] ); ?>> Index</label>
							<label class="seobox-check-item"><input type="radio" name="seobox_robot_index" value="noindex" <?php checked( ! $robots['index'] ); ?>> No Index</label>
						</fieldset>
						<fieldset class="seobox-radio-set">
							<legend>دنبال کردن لینک‌ها</legend>
							<label class="seobox-check-item"><input type="radio" name="seobox_robot_follow" value="follow" <?php checked( $robots['follow'] ); ?>> Follow</label>
							<label class="seobox-check-item"><input type="radio" name="seobox_robot_follow" value="nofollow" <?php checked( ! $robots['follow'] ); ?>> No Follow</label>
						</fieldset>
					</div>
				</div>

				<div class="seobox-divider"></div>

				<div class="seobox-advanced-grid">
					<div class="seobox-field-group">
						<label for="seobox_adv_snippet">حداکثر اسنیپت</label>
						<input type="number" min="-1" step="1" id="seobox_adv_snippet" name="seobox_adv_snippet" value="<?php echo esc_attr( (string) $data['adv_snippet'] ); ?>" placeholder="-1">
						<small>‎-1 یعنی بدون محدودیت</small>
					</div>

					<div class="seobox-field-group">
						<label for="seobox_adv_video">پیش‌نمایش ویدئو (ثانیه)</label>
						<input type="number" min="-1" step="1" id="seobox_adv_video" name="seobox_adv_video" value="<?php echo esc_attr( (string) $data['adv_video'] ); ?>" placeholder="-1">
						<small>‎-1 یعنی بدون محدودیت</small>
					</div>

					<div class="seobox-field-group">
						<label for="seobox_adv_image">پیش‌نمایش تصویر</label>
						<select id="seobox_adv_image" name="seobox_adv_image" class="seobox-select">
							<option value="large" <?php selected( $data['adv_image'], 'large' ); ?>>بزرگ (پیشنهادی — لازم برای Discover)</option>
							<option value="standard" <?php selected( $data['adv_image'], 'standard' ); ?>>استاندارد</option>
							<option value="none" <?php selected( $data['adv_image'], 'none' ); ?>>هیچ‌کدام</option>
						</select>
					</div>
				</div>

				<div class="seobox-divider"></div>

				<div class="seobox-field-group">
					<label for="seobox_canonical">آدرس قانونی (Canonical URL)</label>
					<input type="url" id="seobox_canonical" name="seobox_canonical" class="seobox-ltr" value="<?php echo esc_url( (string) $data['canonical'] ); ?>" placeholder="<?php echo esc_url( $url ); ?>" dir="ltr">
					<small>فقط اگر این محتوا نسخه تکراری صفحه دیگری است پر کنید. خالی = آدرس خود صفحه.</small>
				</div>
			</div>

		</div>
	</div>
	<?php
}
