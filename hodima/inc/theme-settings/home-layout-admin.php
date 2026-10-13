<?php
/**
 * تب «صفحه اصلی» در «نمایش ← تنظیمات قالب هدیما»
 * Path: hodima/inc/theme-settings/home-layout-admin.php
 *
 * فهرست بخش‌های صفحه اصلی با ترتیب دلخواه (کشیدن، یا دکمه‌های بالا/پایین با
 * کیبورد)، روشن/خاموش و تنظیمات هر بخش. داده و رندر: inc/home-layout.php.
 * همه در همان فرم تنظیمات قالب ذخیره می‌شود (گزینه جدا: hodima_home_layout).
 * رفتار سمت مرورگر: admin.js (initHomeLayout)، ظاهر: admin.css (.hodima-home).
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

add_action( 'admin_init', static function (): void {
	register_setting( 'hodima_theme_settings_group', HODIMA_HOME_LAYOUT_OPTION, [
		'type'              => 'array',
		'sanitize_callback' => 'hodima_home_sanitize_layout',
		'default'           => [],
		'show_in_rest'      => false,
	] );
} );

/** قاب «بخش‌های صفحه اصلی» در تب «صفحه اصلی» (زیر قاب «روش ساخت صفحه اصلی»). */
function hodima_home_admin_render(): void {

	$types  = hodima_home_section_types();
	$saved  = hodima_home_layout_saved();
	$layout = $saved ? hodima_home_layout() : hodima_home_layout_from_content();
	$terms  = taxonomy_exists( 'product_cat' ) ? get_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false, 'orderby' => 'name' ] ) : [];
	$terms  = is_array( $terms ) ? $terms : [];

	// وضعیت واقعی (3.1.5): کلید روشن ولی صفحه اصلی «برگه» نیست یا همه بخش‌ها خاموش‌اند ← «اعمال نمی‌شود»
	$switch  = function_exists( 'hodima_setting' ) && hodima_setting( 'home_builder' );
	$page_ok = 'page' === get_option( 'show_on_front' ) && (int) get_option( 'page_on_front' ) > 0;
	$any_on  = (bool) array_filter( $layout, static fn( array $item ): bool => $item['enabled'] );
	$state   = ! $switch ? 'off' : ( $page_ok && $any_on ? 'on' : 'blocked' );
	$labels  = [ 'on' => 'در حال استفاده', 'off' => 'استفاده نمی‌شود', 'blocked' => 'روشن است ولی اعمال نمی‌شود' ];
	$status  = sprintf(
		'<span class="hodima-panel__status%1$s" data-hodima-builder-status data-page-ok="%2$s" data-on="%3$s" data-off="%4$s" data-blocked="%5$s">%6$s</span>',
		'on' === $state ? ' is-on' : ( 'blocked' === $state ? ' is-blocked' : '' ),
		$page_ok ? '1' : '0',
		esc_attr( $labels['on'] ),
		esc_attr( $labels['off'] ),
		esc_attr( $labels['blocked'] ),
		esc_html( $labels[ $state ] )
	);
	?>
	<section class="hodima-panel hodima-panel--builder hodima-home" aria-labelledby="hodima-panel-home_layout-title" data-hodima-home>
		<?php
		hodima_settings_panel_head(
			'hodima-panel-home_layout-title',
			'بخش‌های صفحه اصلی',
			'به ترتیب از بالا به پایین. ترتیب را با کشیدن دستگیره یا دکمه‌های بالا/پایین عوض کنید؛ با کلیک روی نام هر بخش تنظیماتش باز می‌شود. بخش خاموش ذخیره می‌ماند ولی نمایش داده نمی‌شود.',
			'dashicons-layout',
			$status
		);
		?>

		<div class="hodima-panel__body">
			<input type="hidden" name="<?php echo esc_attr( HODIMA_HOME_LAYOUT_OPTION ); ?>[_sent]" value="1">

			<?php foreach ( hodima_home_admin_notices( $layout ) as $notice ) : ?>
				<p class="hodima-home__notice hodima-home__notice--warn" role="note">
					<span class="dashicons dashicons-warning hodima-home__notice-icon" aria-hidden="true"></span>
					<?php echo esc_html( $notice ); ?>
				</p>
			<?php endforeach; ?>

			<?php if ( ! $saved ) : ?>
				<p class="hodima-home__notice" role="note">
					<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
					این چیدمان از روی متن فعلی برگه صفحه اصلی خوانده شده است. بررسی و «ذخیره» کنید، بعد کلید «ساخت صفحه اصلی از چیدمان پایین» را روشن کنید.
				</p>
			<?php endif; ?>

			<ol class="hodima-home__list" data-hodima-home-list>
				<?php foreach ( $layout as $item ) : ?>
					<?php hodima_home_admin_item( $item, $terms ); ?>
				<?php endforeach; ?>
			</ol>

			<p class="hodima-home__empty" data-hodima-home-empty <?php echo $layout ? 'hidden' : ''; ?>>هنوز بخشی ندارید؛ از پایین اضافه کنید.</p>

			<div class="hodima-home__toolbar">
				<label class="screen-reader-text" for="hodima-home-add-type">نوع بخش جدید</label>
				<select id="hodima-home-add-type" data-hodima-home-type>
					<?php foreach ( $types as $type => $def ) : ?>
						<option value="<?php echo esc_attr( $type ); ?>" data-single="<?php echo $def['single'] ? '1' : '0'; ?>"><?php echo esc_html( $def['label'] ); ?></option>
					<?php endforeach; ?>
				</select>
				<button type="button" class="button" data-hodima-home-add>
					<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> افزودن بخش
				</button>
				<a class="button-link hodima-home__view" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener">دیدن صفحه اصلی <span class="dashicons dashicons-external" aria-hidden="true"></span></a>
				<button type="submit" class="button-link hodima-home__import" name="<?php echo esc_attr( HODIMA_HOME_LAYOUT_OPTION ); ?>[_import]" value="1" data-hodima-home-import>
					خواندن دوباره چیدمان از متن برگه صفحه اصلی
				</button>
			</div>

			<?php
			// الگوی هر نوع بخش برای «افزودن بخش» (بدون ارسال؛ __UID__ را admin.js جایگزین می‌کند)
			foreach ( array_keys( $types ) as $type ) :
				?>
				<template data-hodima-home-template="<?php echo esc_attr( $type ); ?>">
					<?php hodima_home_admin_item( [ 'id' => '__UID__', 'type' => $type, 'enabled' => true ] + hodima_home_section_defaults( $type ), $terms, true ); ?>
				</template>
			<?php endforeach; ?>
		</div>
	</section>
	<?php
}

/**
 * اعلان‌های بالای فهرست: چیزهایی که باعث می‌شود چیدمان بی‌صدا کار نکند.
 *
 * @param list<array<string, mixed>> $layout
 * @return list<string>
 */
function hodima_home_admin_notices( array $layout ): array {

	$notices = [];
	$builder = function_exists( 'hodima_setting' ) && hodima_setting( 'home_builder' );
	$front   = (int) get_option( 'page_on_front' );
	$enabled = array_values( array_filter( $layout, static fn( array $item ): bool => $item['enabled'] ) );

	if ( 'page' !== get_option( 'show_on_front' ) || $front < 1 ) {
		$notices[] = 'صفحه اصلی سایت یک «برگه» نیست؛ چیدمان فقط روی برگه کار می‌کند. در «تنظیمات ← خواندن» گزینه «یک برگه ایستا» را انتخاب کنید.';
	}

	if ( $builder && ! $enabled ) {
		$notices[] = 'کلید «ساخت صفحه اصلی از چیدمان» روشن است ولی همه بخش‌ها خاموش‌اند؛ تا دست‌کم یکی روشن نشود، صفحه اصلی از متن برگه ساخته می‌شود.';
	}

	// «متن برگه» + شورت‌کدهای دیگر: ممکن است یک بخش دوبار نمایش داده شود
	$has_content = false;
	foreach ( $enabled as $item ) {
		$has_content = $has_content || 'content' === $item['type'];
	}
	$page_text = $front > 0 ? (string) get_post_field( 'post_content', $front ) : '';
	$legacy    = array_filter( [ 'section03', 'section07', 'section09', 'latest-products' ], static fn( string $tag ): bool => has_shortcode( $page_text, $tag ) );
	if ( $has_content && count( $enabled ) > 1 && $legacy ) {
		$notices[] = 'بخش «متن برگه صفحه اصلی» روشن است و متن برگه هنوز شورت‌کدهای قدیمی (مثل [section07]) دارد؛ اگر همان‌ها را بخش جدا هم ساخته‌اید، دوبار نمایش داده می‌شوند.';
	}

	return $notices;
}

/**
 * نشانه هشدار کنار نام بخش (HTML escape شده؛ خالی بدون هشدار).
 *
 * @param array<string, mixed> $item
 */
function hodima_home_admin_chips( array $item, string $warning ): string {

	if ( '' === $warning ) {
		return '';
	}

	return '<span class="hodima-home__chip hodima-home__chip--warn" title="' . esc_attr( $warning ) . '"><span class="dashicons dashicons-warning hodima-home__warn-icon" aria-hidden="true"></span>' . esc_html( $warning ) . '</span>';
}

/**
 * یک بخش در فهرست.
 *
 * @param array<int, WP_Term> $terms دسته‌های محصول
 * @param array<string, mixed> $item
 */
function hodima_home_admin_item( array $item, array $terms, bool $open = false ): void {

	$types = hodima_home_section_types();
	$type  = $item['type'];
	$def   = $types[ $type ];
	$uid   = (string) $item['id'];
	$base  = HODIMA_HOME_LAYOUT_OPTION . '[' . $uid . ']';
	$idp   = 'hodima-home-' . $uid;

	// خلاصه جلوی نام بخش: عنوان، نام دسته، یا برای «محتوای دلخواه» نام دلخواه/شورت‌کد
	$summary = hodima_home_section_summary( $item, $terms );
	$warning = isset( $item['enabled'] ) && '__UID__' !== $uid ? hodima_home_section_warning( $item ) : '';
	?>
	<li class="hodima-home__item" data-hodima-home-item data-type="<?php echo esc_attr( $type ); ?>">
		<input type="hidden" name="<?php echo esc_attr( $base ); ?>[type]" value="<?php echo esc_attr( $type ); ?>">

		<div class="hodima-home__bar">
			<span class="hodima-home__handle dashicons dashicons-move" data-hodima-home-handle title="برای جابه‌جایی بکشید" aria-hidden="true"></span>
			<span class="hodima-home__icon dashicons <?php echo esc_attr( $def['icon'] ); ?>" aria-hidden="true"></span>

			<button type="button" class="hodima-home__title" aria-expanded="<?php echo $open ? 'true' : 'false'; ?>" aria-controls="<?php echo esc_attr( $idp ); ?>-body" data-hodima-home-toggle>
				<strong><?php echo esc_html( $def['label'] ); ?></strong>
				<span class="hodima-home__summary" data-hodima-home-summary><?php echo esc_html( $summary ); ?></span>
				<span class="hodima-home__chips" data-hodima-home-chips><?php echo hodima_home_admin_chips( $item, $warning ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- خروجی hodima_home_admin_chips escape شده ?></span>
			</button>

			<label class="hodima-toggle hodima-home__switch">
				<input type="checkbox" role="switch" name="<?php echo esc_attr( $base ); ?>[enabled]" value="1" <?php checked( ! empty( $item['enabled'] ) ); ?>>
				<span class="hodima-toggle__track" aria-hidden="true"></span>
				<span class="hodima-toggle__label">نمایش</span>
			</label>

			<?php // چهار جای ثابت: بالا، پایین، کپی، حذف؛ جای «کپی» برای انواع تک‌نمونه‌ای خالی می‌ماند تا کلیدها در همه ردیف‌ها هم‌تراز باشند ?>
			<span class="hodima-home__actions">
				<button type="button" class="button-link" data-hodima-home-move="up" aria-label="<?php echo esc_attr( 'بالا بردن «' . $def['label'] . '»' ); ?>"><span class="dashicons dashicons-arrow-up-alt2" aria-hidden="true"></span></button>
				<button type="button" class="button-link" data-hodima-home-move="down" aria-label="<?php echo esc_attr( 'پایین بردن «' . $def['label'] . '»' ); ?>"><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></button>
				<?php if ( empty( $def['single'] ) ) : ?>
					<button type="button" class="button-link" data-hodima-home-duplicate aria-label="<?php echo esc_attr( 'کپی «' . $def['label'] . '»' ); ?>" title="کپی بخش"><span class="dashicons dashicons-admin-page" aria-hidden="true"></span></button>
				<?php else : ?>
					<span class="hodima-home__slot" aria-hidden="true"></span>
				<?php endif; ?>
				<button type="button" class="button-link hodima-home__remove" data-hodima-home-remove aria-label="<?php echo esc_attr( 'حذف «' . $def['label'] . '»' ); ?>"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button>
			</span>
		</div>

		<div class="hodima-home__body" id="<?php echo esc_attr( $idp ); ?>-body" <?php echo $open ? '' : 'hidden'; ?>>
			<p class="hodima-field__help"><?php echo esc_html( $def['help'] ); ?></p>
			<?php if ( $def['fields'] ) : ?>
				<div class="hodima-home__fields">
					<?php foreach ( $def['fields'] as $key => $field ) : ?>
						<?php hodima_home_admin_field( $base . '[' . $key . ']', $idp . '-' . $key, $field, $item[ $key ] ?? $field['default'], $terms, $key ); ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</li>
	<?php
}

/**
 * یک فیلد تنظیمات بخش.
 *
 * @param array<string, mixed> $field
 * @param array<int, WP_Term> $terms
 */
function hodima_home_admin_field( string $name, string $id, array $field, mixed $value, array $terms, string $key ): void {

	$summary = in_array( $key, [ 'title', 'category', 'label' ], true ) ? ' data-hodima-home-summary-source' : '';
	$wide    = in_array( $field['type'], [ 'lines', 'categories', 'html' ], true ) ? ' hodima-field--wide' : '';
	?>
	<div class="hodima-field hodima-field--<?php echo esc_attr( $field['type'] . $wide ); ?><?php echo 'toggle' === $field['type'] ? ' hodima-home__cell--toggle' : ''; ?>">
		<?php if ( 'categories' === $field['type'] ) : ?>
			<fieldset class="hodima-home__checks">
				<legend class="hodima-field__label"><?php echo esc_html( $field['label'] ); ?></legend>
				<?php if ( ! empty( $field['help'] ) ) : ?>
					<p class="hodima-field__help"><?php echo esc_html( $field['help'] ); ?></p>
				<?php endif; ?>
				<input type="hidden" name="<?php echo esc_attr( $name ); ?>[]" value="">
				<?php if ( ! $terms ) : ?>
					<p class="hodima-field__help">دسته محصولی وجود ندارد (ووکامرس).</p>
				<?php elseif ( count( $terms ) > 8 ) : ?>
					<?php // جستجو در فهرست دسته‌ها (admin.js: فقط نمایش را فیلتر می‌کند؛ تیک‌ها دست‌نخورده می‌مانند) ?>
					<input type="search" class="hodima-home__filter" data-hodima-home-filter placeholder="جستجو در دسته‌ها…" aria-label="<?php echo esc_attr( 'جستجو در ' . $field['label'] ); ?>">
				<?php endif; ?>
				<div class="hodima-home__checklist">
					<?php foreach ( $terms as $term ) : ?>
						<label data-hodima-home-check>
							<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[]" value="<?php echo esc_attr( $term->slug ); ?>" <?php checked( in_array( $term->slug, (array) $value, true ) ); ?>>
							<?php echo esc_html( $term->name ); ?>
						</label>
					<?php endforeach; ?>
				</div>
			</fieldset>

		<?php elseif ( 'toggle' === $field['type'] ) : ?>
			<?php // 0 + 1: کلید خاموش هم در فرم می‌آید و با «پیش‌فرض روشن» اشتباه نمی‌شود ?>
			<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="0">
			<label class="hodima-home__check" for="<?php echo esc_attr( $id ); ?>">
				<input type="checkbox" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( ! empty( $value ) ); ?>>
				<?php echo esc_html( $field['label'] ); ?>
			</label>

		<?php else : ?>
			<?php hodima_settings_field_head( '<label class="hodima-field__label" for="' . esc_attr( $id ) . '">' . esc_html( $field['label'] ) . '</label>', (string) ( $field['help'] ?? '' ), $id . '-help', (string) $field['label'] ); ?>

			<?php if ( 'lines' === $field['type'] ) : ?>
				<textarea id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" rows="4"><?php echo esc_textarea( implode( "\n", (array) $value ) ); ?></textarea>

			<?php elseif ( 'html' === $field['type'] ) : ?>
				<textarea id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" rows="4" dir="ltr" spellcheck="false" class="hodima-home__code" placeholder="[hodima_slider id=&quot;1&quot;]"><?php echo esc_textarea( (string) $value ); ?></textarea>

			<?php elseif ( 'tone' === $field['type'] ) : ?>
				<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>">
					<?php foreach ( hodima_home_tones() as $tone => $meta ) : ?>
						<option value="<?php echo esc_attr( $tone ); ?>" <?php selected( $value, $tone ); ?>><?php echo esc_html( $meta['label'] ); ?></option>
					<?php endforeach; ?>
				</select>

			<?php elseif ( 'category' === $field['type'] ) : ?>
				<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>"<?php echo $summary; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ویژگی ثابت (escape‌شده بالاتر) ?>>
					<option value="">— انتخاب دسته —</option>
					<?php foreach ( $terms as $term ) : ?>
						<option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $value, $term->slug ); ?>><?php echo esc_html( $term->name ); ?></option>
					<?php endforeach; ?>
				</select>

			<?php elseif ( 'select' === $field['type'] ) : ?>
				<select id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>">
					<?php foreach ( (array) $field['options'] as $option => $label ) : ?>
						<option value="<?php echo esc_attr( (string) $option ); ?>" <?php selected( (string) $value, (string) $option ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>

			<?php elseif ( 'number' === $field['type'] ) : ?>
				<input type="number" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( (string) (int) $value ); ?>" min="<?php echo esc_attr( (string) ( $field['min'] ?? 1 ) ); ?>" max="<?php echo esc_attr( (string) ( $field['max'] ?? 100 ) ); ?>" inputmode="numeric">

			<?php else : ?>
				<input type="text" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( (string) $value ); ?>"<?php echo 'link' === $field['type'] ? ' dir="ltr" spellcheck="false"' : ''; ?><?php echo $summary; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ویژگی ثابت (escape‌شده بالاتر) ?>>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php
}
