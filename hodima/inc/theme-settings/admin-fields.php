<?php
/**
 * Theme Settings — رندر فیلدها در پیشخوان: راهنما و حباب، سرتیتر قاب، پیش‌نمایش پالت، hodima_settings_render_field، پیشنهاد دامنه
 * Path: hodima/inc/theme-settings/admin-fields.php
 *
 * از theme-settings.php جدا شد (3.6.3، بخش ۱۶۱ HODIMA-AUDIT.md): کد بدون تغییر، فقط جابه‌جا؛
 * ترتیب بارگذاری (و ثبت هوک‌ها) همان ترتیب قبلی فایل است (فهرست: theme-settings.php).
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * راهنمای بلند ← یک جمله دیدنی + بقیه در آیکون ؟: جمله اول (تا اولین نقطه/؟/! با فاصله بعدش)
 * همیشه دیده می‌شود؛ بقیه به حباب می‌رود. متن کوتاه (تا ۹۰ حرف) یا بدون جمله دوم دست نمی‌خورد.
 *
 * @return array{0: string, 1: string} [ دیدنی، حباب ]
 */
function hodima_settings_split_help( string $help ): array {

	if ( mb_strlen( $help ) <= 90 || ! preg_match( '/^(.{20,}?[.؟!])\s+(.+)$/us', $help, $match ) ) {
		return [ $help, '' ];
	}

	return [ $match[1], $match[2] ];
}

/**
 * آیکون ؟ با حباب راهنما (admin.js: هاور، فوکوس، کلیک/لمس، Esc). متن راهنما در HTML می‌ماند
 * و کنترل با aria-describedby به آن وصل است؛ پس صفحه‌خوان هم می‌خواند.
 */
function hodima_settings_tip_html( string $text, string $id, string $label ): string {

	return '<span class="hodima-tip">'
		. '<button type="button" class="hodima-tip__button" aria-expanded="false" aria-label="' . esc_attr( 'راهنما: ' . $label ) . '"><span class="dashicons dashicons-editor-help hodima-tip__icon" aria-hidden="true"></span></button>'
		. '<span class="hodima-tip__bubble" role="tooltip" id="' . esc_attr( $id ) . '">' . esc_html( $text ) . '</span>'
		. '</span>';
}

/**
 * برچسب کادر؛ اگر راهنما دارد با آیکون ؟ کنارش در یک ردیف (3.1.1).
 *
 * @param string $label_html برچسب آماده (label یا span؛ escape شده توسط فراخواننده)
 */
function hodima_settings_field_head( string $label_html, string $help, string $help_id, string $label ): void {

	if ( '' === $help ) {
		echo $label_html; // phpcs:ignore WordPress.Security.EscapeOutput -- فراخواننده escape کرده
		return;
	}

	echo '<span class="hodima-field__head">' . $label_html . hodima_settings_tip_html( $help, $help_id, $label ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput -- برچسب و حباب escape شده‌اند
}

/** سرتیتر قاب (مشترک با قاب «بخش‌های صفحه اصلی» در home-layout-admin.php). */
function hodima_settings_panel_head( string $id, string $title, string $help = '', string $icon = '', string $extra = '' ): void {
	?>
	<header class="hodima-panel__head">
		<?php if ( '' !== $icon ) : ?>
			<span class="hodima-panel__icon dashicons <?php echo esc_attr( $icon ); ?>" aria-hidden="true"></span>
		<?php endif; ?>
		<div class="hodima-panel__heading">
			<h3 class="hodima-panel__title" id="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $title ); ?></h3>
			<?php if ( '' !== $help ) : ?>
				<?php [ $help_first, $help_rest ] = hodima_settings_split_help( $help ); ?>
				<p class="hodima-panel__help"><?php echo esc_html( $help_first ); ?><?php echo '' !== $help_rest ? ' ' . hodima_settings_tip_html( $help_rest, $id . '-help', $title ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput -- hodima_settings_tip_html خروجی را escape کرده ?></p>
			<?php endif; ?>
		</div>
		<?php echo $extra; // phpcs:ignore WordPress.Security.EscapeOutput -- نشانه‌گذاری ثابت فراخواننده ?>
	</header>
	<?php
}

/**
 * نمونه کوچک سایت با پالت فعلی (تب «برند و رنگ‌ها»). admin.js با تغییر هر رنگ
 * متغیرهای همین ظرف را عوض می‌کند تا نتیجه پیش از ذخیره دیده شود.
 * @param array<string, mixed> $settings
 */
function hodima_settings_palette_preview( array $settings ): void {

	$vars = '';
	foreach ( [ 'primary', 'secondary', 'third', 'accent', 'accent_light' ] as $name ) {
		$value = sanitize_hex_color( (string) ( $settings[ 'color_' . $name ] ?? '' ) );
		if ( $value ) {
			$vars .= '--pv-' . str_replace( '_', '-', $name ) . ':' . $value . ';';
		}
	}
	?>
	<div class="hodima-palette-preview" style="<?php echo esc_attr( $vars ); ?>" data-hodima-palette-preview aria-hidden="true">
		<div class="hodima-palette-preview__header">
			<span class="hodima-palette-preview__logo"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></span>
			<span class="hodima-palette-preview__menu"><span></span><span></span><span></span></span>
			<span class="hodima-palette-preview__support">پشتیبانی</span>
		</div>
		<div class="hodima-palette-preview__body">
			<span class="hodima-palette-preview__chip">دسته‌بندی</span>
			<strong class="hodima-palette-preview__title">عنوان نمونه محصول</strong>
			<span class="hodima-palette-preview__line"></span>
			<span class="hodima-palette-preview__buttons">
				<span class="hodima-palette-preview__button">افزودن به سبد</span>
				<span class="hodima-palette-preview__button hodima-palette-preview__button--accent">خرید عمده</span>
			</span>
		</div>
	</div>
	<?php
}

/**
 * @param array{panel:string, type:string, label:string, default:mixed, group?:string, help?:string, placeholder?:string, wide?:bool, preview?:string, options?:array<string, string>, min?:int, max?:int, custom_key?:string, embedded?:bool} $field
 */
function hodima_settings_render_field( string $key, array $field, mixed $value ): void {

	// رنگ دلخواه (embedded): داخل کارت فهرست مالکش (custom_key) چاپ می‌شود، نه جدا
	if ( ! empty( $field['embedded'] ) ) {
		return;
	}

	$id        = 'hodima-setting-' . $key;
	$name      = empty( $field['unsaved'] ) ? HODIMA_SETTINGS_OPTION . '[' . $key . ']' : ''; // unsaved: کنترل کمکی پیشخوان («همه تیترها»)، ارسال نمی‌شود
	$help      = $field['help'] ?? '';
	$help_id   = $id . '-help';
	$described = '' !== $help ? ' aria-describedby="' . esc_attr( $help_id ) . '"' : '';
	$type      = hodima_setting_type( $field );
	// راهنمای کادر ← آیکون ؟ کنار برچسب (3.1.1). می‌ماند: کلید (توضیح جزو کاشی است)، فیلد زیرگروه (ردیف جدول) و فیلد با help_visible (هشدار/شرط مهم)
	$tip       = '' !== $help && ! isset( $field['group'] ) && empty( $field['help_visible'] ) && Hodima_Setting_Type::Toggle !== $type;
	$wide      = ( $field['wide'] ?? $type->is_wide() ) && ! isset( $field['group'] ) ? ' hodima-field--wide' : '';
	?>
	<div class="hodima-field hodima-field--<?php echo esc_attr( $field['type'] . $wide ); ?>">
		<?php if ( Hodima_Setting_Type::Toggle === $type ) : ?>
			<?php /* کاشی کلید: کل کادر قابل کلیک؛ توضیح داخل همان کادر */ ?>
			<label class="hodima-toggle" for="<?php echo esc_attr( $id ); ?>">
				<input type="checkbox" role="switch" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( (bool) $value ); ?><?php echo $described; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above ?>>
				<span class="hodima-toggle__text">
					<span class="hodima-toggle__label"><?php echo esc_html( $field['label'] ); ?></span>
					<?php if ( '' !== $help ) : ?>
						<span class="hodima-field__help" id="<?php echo esc_attr( $help_id ); ?>"><?php echo esc_html( $help ); ?></span>
					<?php endif; ?>
				</span>
				<span class="hodima-toggle__track" aria-hidden="true"></span>
			</label>
			<?php $help = ''; // توضیح بالاتر چاپ شد ?>

		<?php elseif ( Hodima_Setting_Type::Color === $type ) : ?>
			<?php
			/*
			 * کنترل رنگ یکدست (3.0.1): برچسب بالا، [مربع رنگ | کد رنگ] هم‌ارتفاع بقیه
			 * کادرها، توضیح و «بازگشت به پیش‌فرض» زیر آن — همان شکل «انتخاب از پالت»
			 * (.hodima-color). تا 3.0.0 کارت با بلوک رنگ تمام‌عرض بود.
			 * کد رنگ بی name است (مقدار با input رنگ ارسال می‌شود؛ admin.js همگام می‌کند).
			 */
			$default = strtolower( (string) $field['default'] );
			?>
			<?php hodima_settings_field_head( '<label class="hodima-field__label" for="' . esc_attr( $id ) . '-hex">' . esc_html( $field['label'] ) . '</label>', $tip ? $help : '', $help_id, (string) $field['label'] ); ?>
			<span class="hodima-color">
				<input
					type="color"
					class="hodima-color__swatch"
					id="<?php echo esc_attr( $id ); ?>"
					name="<?php echo esc_attr( $name ); ?>"
					value="<?php echo esc_attr( (string) $value ); ?>"
					data-hodima-color="<?php echo esc_attr( str_replace( [ 'color_', '_' ], [ '', '-' ], $key ) ); ?>"
					aria-label="<?php echo esc_attr( sprintf( 'انتخاب %s از جعبه رنگ', $field['label'] ) ); ?>"
				>
				<input
					type="text"
					class="hodima-color__hex"
					id="<?php echo esc_attr( $id ); ?>-hex"
					value="<?php echo esc_attr( (string) $value ); ?>"
					dir="ltr"
					maxlength="7"
					spellcheck="false"
					autocomplete="off"
					pattern="#?([0-9a-fA-F]{3}|[0-9a-fA-F]{6})"
					placeholder="#25316a"
					data-hodima-color-value
					<?php echo $described; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above ?>
				>
			</span>
			<?php if ( '' !== $help && ! $tip ) : ?>
				<p class="hodima-field__help" id="<?php echo esc_attr( $help_id ); ?>"><?php echo esc_html( $help ); ?></p>
			<?php endif; ?>
			<button type="button" class="hodima-color__reset" title="بازگشت به پیش‌فرض" data-hodima-color-reset="<?php echo esc_attr( $default ); ?>" <?php echo strtolower( (string) $value ) === $default ? 'hidden' : ''; ?>>
				<span class="dashicons dashicons-undo" aria-hidden="true"></span>
				بازگشت به پیش‌فرض <code dir="ltr"><?php echo esc_html( $default ); ?></code>
			</button>
			<?php $help = ''; // توضیح بالاتر چاپ شد ?>

		<?php elseif ( Hodima_Setting_Type::Image === $type ) : ?>
			<?php
			$image_id = (int) $value;
			$header   = 'header' === ( $field['preview'] ?? '' );
			$inverted = $header && hodima_setting( 'logo_invert' );
			?>
			<?php hodima_settings_field_head( '<span class="hodima-field__label" id="' . esc_attr( $id ) . '-label">' . esc_html( $field['label'] ) . '</span>', $tip ? $help : '', $help_id, (string) $field['label'] ); ?>
			<div class="hodima-media" data-hodima-media>
				<input type="hidden" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( (string) $image_id ); ?>" data-hodima-media-input>
				<figure
					class="hodima-media__preview<?php echo $header ? ' hodima-media__preview--header' : ''; ?><?php echo $inverted ? ' is-inverted' : ''; ?><?php echo $image_id ? '' : ' is-empty'; ?>"
					data-hodima-media-preview
					<?php echo $header ? 'data-hodima-invert-source="hodima-setting-logo_invert"' : ''; ?>
				>
					<?php echo $image_id ? wp_get_attachment_image( $image_id, 'medium', false, [ 'alt' => '' ] ) : ''; ?>
					<span class="hodima-media__empty" aria-hidden="true"><span class="dashicons dashicons-format-image"></span><span class="hodima-media__empty-text">بدون تصویر</span></span>
				</figure>
				<div class="hodima-media__buttons">
					<button type="button" class="button" data-hodima-media-select aria-describedby="<?php echo esc_attr( $id ); ?>-label" data-title="<?php echo esc_attr( $field['label'] ); ?>"><?php echo $image_id ? 'تغییر تصویر' : 'انتخاب تصویر'; ?></button>
					<button type="button" class="button-link hodima-media__remove" data-hodima-media-remove aria-describedby="<?php echo esc_attr( $id ); ?>-label" <?php echo $image_id ? '' : 'hidden'; ?>>حذف</button>
				</div>
			</div>

		<?php elseif ( Hodima_Setting_Type::Font === $type ) : ?>
			<?php
			$font_id   = (int) $value;
			$font_name = $font_id ? wp_basename( (string) get_attached_file( $font_id ) ) : '';
			?>
			<span class="hodima-field__label screen-reader-text" id="<?php echo esc_attr( $id ); ?>-label"><?php echo esc_html( $field['label'] ); ?></span>
			<div class="hodima-media hodima-media--file" data-hodima-media data-hodima-media-kind="font">
				<input type="hidden" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( (string) $font_id ); ?>" data-hodima-media-input>
				<span class="hodima-media__file<?php echo $font_id ? '' : ' is-empty'; ?>" data-hodima-media-preview>
					<span class="dashicons dashicons-media-default" aria-hidden="true"></span>
					<bdi dir="ltr" data-hodima-media-name><?php echo esc_html( $font_name ); ?></bdi>
					<span class="hodima-media__empty-text">بدون فایل</span>
				</span>
				<div class="hodima-media__buttons">
					<button type="button" class="button" data-hodima-media-select aria-describedby="<?php echo esc_attr( $id ); ?>-label" data-title="<?php echo esc_attr( $field['label'] ); ?>"><?php echo $font_id ? 'تغییر فایل' : 'انتخاب فایل'; ?></button>
					<button type="button" class="button-link hodima-media__remove" data-hodima-media-remove aria-describedby="<?php echo esc_attr( $id ); ?>-label" <?php echo $font_id ? '' : 'hidden'; ?>>حذف</button>
				</div>
				<?php if ( preg_match( '/^font_custom_(\d+)$/', $key, $hodima_w ) ) : ?>
					<?php // نمونه همین وزن با فونت آپلودی (پیش از ذخیره هم: admin.js همان خانواده را با FontFace اضافه می‌کند) ?>
					<span class="hodima-font-sample" data-hodima-font-sample style="<?php echo esc_attr( sprintf( "font-family: '%s', Tahoma, sans-serif; font-weight: %d", hodima_typography_custom_family(), (int) $hodima_w[1] ) ); ?>" <?php echo $font_id ? '' : 'hidden'; ?>>نمونه متن فارسی ۱۲۳ Abc</span>
				<?php endif; ?>
			</div>

		<?php else : ?>
			<?php hodima_settings_field_head( '<label class="hodima-field__label" for="' . esc_attr( $id ) . '">' . esc_html( $field['label'] ) . '</label>', $tip ? $help : '', $help_id, (string) $field['label'] ); ?>
			<?php if ( Hodima_Setting_Type::Textarea === $type ) : ?>
				<textarea id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" rows="4"<?php echo $described; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above ?>><?php echo esc_textarea( (string) $value ); ?></textarea>
			<?php elseif ( Hodima_Setting_Type::Number === $type ) : ?>
				<input
					type="number"
					id="<?php echo esc_attr( $id ); ?>"
					name="<?php echo esc_attr( $name ); ?>"
					value="<?php echo esc_attr( hodima_number_text( is_numeric( $value ) ? 0 + $value : 0 ) ); ?>"
					min="<?php echo esc_attr( (string) ( $field['min'] ?? 0 ) ); ?>"
					max="<?php echo esc_attr( (string) ( $field['max'] ?? '' ) ); ?>"
					<?php if ( isset( $field['step'] ) ) : ?>
						step="<?php echo esc_attr( (string) $field['step'] ); ?>"
						inputmode="decimal"
					<?php else : ?>
						inputmode="numeric"
					<?php endif; ?>
					<?php echo $described; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above ?>
				>
			<?php elseif ( Hodima_Setting_Type::Select === $type ) : ?>
				<?php
				$hodima_swatch = ! empty( $field['swatch'] );
				$custom_key    = (string) ( $field['custom_key'] ?? '' );
				$custom_on     = '' !== $custom_key && 'custom' === (string) $value;
				$custom_val    = '' !== $custom_key ? (string) hodima_setting( $custom_key ) : '';
				$custom_def    = '' !== $custom_key ? strtolower( (string) ( hodima_settings_fields()[ $custom_key ]['default'] ?? '' ) ) : '';
				// انتخاب از پالت: همان کنترل رنگ (.hodima-color)، مربع رنگ کنار فهرست (admin.js با پالت زنده)
				?>
				<?php if ( $hodima_swatch ) : ?>
					<?php
					// «دلخواه»: مربع رنگ همین کارت انتخاب‌گر می‌شود و کد رنگ زیر فهرست می‌آید (3.2.0)؛ با فهرست روی رنگ پالت پنهان‌اند (admin.js)
					?>
					<span class="hodima-color">
						<span class="hodima-color__swatch" data-hodima-color-dot style="background: <?php echo esc_attr( hodima_settings_palette_hex( (string) $value ) ); ?>" aria-hidden="true"<?php echo $custom_on ? ' hidden' : ''; ?>></span>
					<?php if ( '' !== $custom_key ) : ?>
						<input
							type="color"
							class="hodima-color__swatch"
							id="hodima-setting-<?php echo esc_attr( $custom_key ); ?>"
							name="<?php echo esc_attr( HODIMA_SETTINGS_OPTION . '[' . $custom_key . ']' ); ?>"
							value="<?php echo esc_attr( $custom_val ); ?>"
							data-hodima-color="<?php echo esc_attr( str_replace( '_', '-', $custom_key ) ); ?>"
							data-hodima-when-custom
							aria-label="<?php echo esc_attr( sprintf( 'انتخاب %s از جعبه رنگ', $field['label'] ) ); ?>"
							<?php echo $custom_on ? '' : 'hidden'; ?>
						>
					<?php endif; ?>
				<?php endif; ?>
				<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>"<?php echo $described; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above ?>>
					<?php foreach ( (array) ( $field['options'] ?? [] ) as $option => $label ) : ?>
						<option value="<?php echo esc_attr( (string) $option ); ?>" <?php selected( (string) $value, (string) $option ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<?php if ( $hodima_swatch && '' !== $custom_key ) : ?>
					<input type="text" class="hodima-color__hex" id="hodima-setting-<?php echo esc_attr( $custom_key ); ?>-hex" value="<?php echo esc_attr( $custom_val ); ?>" dir="ltr" maxlength="7" spellcheck="false" autocomplete="off" placeholder="#25316a" aria-label="<?php echo esc_attr( sprintf( 'کد %s', $field['label'] ) ); ?>" data-hodima-color-value data-hodima-when-custom<?php echo $custom_on ? '' : ' hidden'; ?>>
					<button type="button" class="hodima-color__reset" title="بازگشت به پیش‌فرض" data-hodima-color-reset="<?php echo esc_attr( $custom_def ); ?>" data-hodima-when-custom <?php echo $custom_on && strtolower( $custom_val ) !== $custom_def ? '' : 'hidden'; ?>>
						<span class="dashicons dashicons-undo" aria-hidden="true"></span>
						بازگشت به پیش‌فرض <code dir="ltr"><?php echo esc_html( $custom_def ); ?></code>
					</button>
				<?php endif; ?>
				<?php if ( $hodima_swatch ) : ?>
					</span>
				<?php endif; ?>
			<?php elseif ( Hodima_Setting_Type::Code === $type ) : ?>
				<textarea
					class="hodima-code"
					id="<?php echo esc_attr( $id ); ?>"
					name="<?php echo esc_attr( $name ); ?>"
					rows="6"
					dir="ltr"
					spellcheck="false"
					autocomplete="off"
					<?php echo isset( $field['placeholder'] ) ? 'placeholder="' . esc_attr( $field['placeholder'] ) . '"' : ''; ?>
					<?php echo current_user_can( 'unfiltered_html' ) ? '' : 'readonly'; ?>
					<?php echo $described; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above ?>
				><?php echo esc_textarea( (string) $value ); ?></textarea>
			<?php elseif ( $type->is_list() ) : ?>
				<textarea
					id="<?php echo esc_attr( $id ); ?>"
					name="<?php echo esc_attr( $name ); ?>"
					rows="<?php echo Hodima_Setting_Type::HostList === $type ? '3' : '5'; // یک یا دو دامنه؛ فهرست آدرس بلندتر ?>"
					dir="ltr"
					spellcheck="false"
					autocomplete="off"
					<?php echo isset( $field['placeholder'] ) ? 'placeholder="' . esc_attr( $field['placeholder'] ) . '"' : ''; ?>
					<?php echo $described; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above ?>
				><?php echo esc_textarea( (string) $value ); ?></textarea>
			<?php else : ?>
				<input
					type="<?php echo esc_attr( $type->input_type() ); ?>"
					id="<?php echo esc_attr( $id ); ?>"
					name="<?php echo esc_attr( $name ); ?>"
					value="<?php echo esc_attr( (string) $value ); ?>"
					<?php echo isset( $field['placeholder'] ) ? 'placeholder="' . esc_attr( $field['placeholder'] ) . '"' : ''; ?>
					<?php echo $type->is_ltr() ? 'dir="ltr"' : ''; ?>
					<?php echo Hodima_Setting_Type::Ga === $type ? 'pattern="G-[A-Za-z0-9]{4,16}" autocomplete="off" spellcheck="false"' : ''; ?>
					<?php echo $described; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above ?>
				>
			<?php endif; ?>
		<?php endif; ?>

		<?php if ( '' !== $help && ! $tip ) : ?>
			<p class="hodima-field__help" id="<?php echo esc_attr( $help_id ); ?>"><?php echo esc_html( $help ); ?></p>
		<?php endif; ?>

		<?php if ( Hodima_Setting_Type::HostList === $type ) : // بعد از توضیح، مثل بقیه فیلدها (کنترل ← توضیح ← افزوده‌ها) ?>
			<?php hodima_settings_render_host_suggestions( $id, (string) $value ); ?>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * «دامنه‌های پیدا شده» زیر کادر دامنه ویدئو: دامنه‌هایی که ویدیو، کاور و
 * پادکست‌های همین سایت واقعا از آن‌ها پخش می‌شوند (Hodima Media، پرتکرار
 * اول) با دکمه «افزودن» (admin.js). دامنه عوض شود یا قالب روی سایت دیگری
 * نصب شود، فهرست از رسانه‌های همان سایت ساخته می‌شود؛ هیچ دامنه‌ای در کد نیست.
 */
function hodima_settings_render_host_suggestions( string $target_id, string $value ): void {

	if ( ! function_exists( 'hodima_media_external_hosts' ) ) {
		echo '<p class="hodima-hosts__note">برای پیدا کردن خودکار دامنه‌ها، افزونه Hodima Media لازم است.</p>';
		return;
	}

	$hosts   = array_slice( hodima_media_external_hosts(), 0, 12, true );
	$entered = array_filter( array_map( static fn( string $line ): string => strtolower( trim( $line ) ), preg_split( '/\R/u', $value ) ?: [] ) );
	?>
	<div class="hodima-hosts" data-hodima-hosts="<?php echo esc_attr( $target_id ); ?>">
		<p class="hodima-hosts__title">دامنه‌های پیدا شده در ویدیو و پادکست‌های سایت</p>
		<?php if ( ! $hosts ) : ?>
			<p class="hodima-hosts__note">هنوز ویدیو یا پادکستی از دامنه دیگری در سایت ثبت نشده است.</p>
		<?php else : ?>
			<ul class="hodima-hosts__list">
				<?php foreach ( $hosts as $origin => $count ) : ?>
					<?php $hodima_added = in_array( strtolower( $origin ), $entered, true ); ?>
					<li>
						<button
							type="button"
							class="button button-small hodima-hosts__add"
							data-hodima-host="<?php echo esc_attr( $origin ); ?>"
							aria-label="<?php echo esc_attr( sprintf( 'افزودن %s', $origin ) ); ?>"
							<?php disabled( $hodima_added ); ?>
						>
							<span class="dashicons <?php echo $hodima_added ? 'dashicons-yes' : 'dashicons-plus-alt2'; ?>" aria-hidden="true"></span>
							<bdi dir="ltr"><?php echo esc_html( (string) preg_replace( '#^https?://#', '', $origin ) ); ?></bdi>
							<span class="hodima-hosts__count"><?php echo esc_html( sprintf( '%s رسانه', number_format_i18n( $count ) ) ); ?></span>
						</button>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
	<?php
}
