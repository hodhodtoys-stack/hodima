<?php
/**
 * Theme Settings — صفحه پیشخوان: صف‌کردن CSS/JS، رندر صفحه، قاب‌ها، ستون‌ها، ردیف‌ها، تیترها و کاشی‌ها
 * Path: hodima/inc/theme-settings/admin-page.php
 *
 * از theme-settings.php جدا شد (3.6.3، بخش ۱۶۱ HODIMA-AUDIT.md): کد بدون تغییر، فقط جابه‌جا؛
 * ترتیب بارگذاری (و ثبت هوک‌ها) همان ترتیب قبلی فایل است (فهرست: theme-settings.php).
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =========================================================================
 * ۳. رندر صفحه تنظیمات
 * ========================================================================= */

add_action( 'admin_enqueue_scripts', static function ( string $hook ): void {

	if ( HODIMA_SETTINGS_HOOK !== $hook ) {
		return;
	}

	wp_enqueue_media();

	hodima_enqueue_asset( 'hodima-theme-settings', 'inc/theme-settings/admin.css' );
	hodima_enqueue_asset( 'hodima-theme-settings', 'inc/theme-settings/admin.js', [ 'media-editor' ], [ 'in_footer' => true, 'strategy' => 'defer' ] );
} );

/**
 * صفحه «تنظیمات قالب هدیما» (منوی سطح اول پیشخوان): منوی کناری گروه‌بندی‌شده + پنل هر تب.
 *
 * تاریخچه: اول همه بخش‌ها زیر هم بودند؛ بعد ده تب در یک ردیف افقی (بی‌ترتیب و
 * در صفحه کوچک‌تر بیرون‌زده)؛ حالا منوی کناری با چهار گروه (ظاهر سایت، صفحه‌ها،
 * ارتباط با مشتری، آمار) و در موبایل یک ردیف لغزنده.
 *   - بدون جاوااسکریپت: هر تب لینک ?tab=… است و سرور پنل همان تب را نشان می‌دهد؛
 *   - با جاوااسکریپت (admin.js): جابه‌جایی فوری، تب فعال بعد از «ذخیره» حفظ
 *     می‌شود (آدرس بازگشت فرم به‌روز می‌شود)، نشانه «ذخیره‌نشده» و Ctrl+S.
 * همه فیلدها در همان یک فرم و یک گزینه (hodima_theme_settings) می‌مانند؛ پنل‌های
 * پنهان هم ارسال می‌شوند، پس ذخیره یک تب مقدار تب‌های دیگر را پاک نمی‌کند.
 */
function hodima_settings_render_page(): void {

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$settings = hodima_settings();
	$fields   = hodima_settings_fields();
	$sections = hodima_settings_sections();
	$panels   = hodima_settings_panels();
	$version  = (string) wp_get_theme( get_template() )->get( 'Version' );

	$requested = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط انتخاب تب
	$current   = isset( $sections[ $requested ] ) ? $requested : (string) array_key_first( $sections );
	?>
	<div class="wrap hodima-settings">
		<header class="hodima-settings__header">
			<span class="dashicons dashicons-admin-appearance hodima-settings__header-icon" aria-hidden="true"></span>
			<div class="hodima-settings__header-body">
				<?php if ( '' !== $version ) : ?>
					<span class="hodima-settings__badge">قالب هدیما · نسخه <bdi><?php echo esc_html( $version ); ?></bdi></span>
				<?php endif; ?>
				<h1>تنظیمات قالب هدیما</h1>
				<p>ظاهر سایت، صفحه‌ها و راه‌های ارتباطی. هر تغییر بعد از «ذخیره» روی همه صفحه‌های سایت اعمال می‌شود.</p>
			</div>
			<a class="hodima-settings__visit" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener">
				<span class="dashicons dashicons-external" aria-hidden="true"></span>
				مشاهده سایت<span class="screen-reader-text"> (در زبانه جدید)</span>
			</a>
		</header>

		<hr class="wp-header-end">

		<?php
		/*
		 * بدون آرگومان: پیام «تنظیمات ذخیره شد» را options.php زیر نامک general
		 * ثبت می‌کند. نسخه قبلی فقط خطاهای همین گزینه را چاپ می‌کرد و بعد از
		 * ذخیره موفق هیچ پیامی دیده نمی‌شد.
		 */
		settings_errors();
		?>

		<div class="hodima-settings__layout">
			<nav class="hodima-settings__nav" aria-label="بخش‌های تنظیمات" data-hodima-nav>
				<?php foreach ( hodima_settings_nav_groups() as $nav_key => $nav_label ) : ?>
					<?php $nav_sections = array_filter( $sections, static fn( array $section ): bool => $section['nav'] === $nav_key ); ?>
					<?php if ( $nav_sections ) : ?>
						<div class="hodima-settings__nav-group">
							<p class="hodima-settings__nav-title" id="hodima-nav-<?php echo esc_attr( $nav_key ); ?>"><?php echo esc_html( $nav_label ); ?></p>
							<ul aria-labelledby="hodima-nav-<?php echo esc_attr( $nav_key ); ?>">
								<?php foreach ( $nav_sections as $section_key => $section ) : ?>
									<li>
										<a
											class="hodima-settings__tab"
											href="<?php echo esc_url( hodima_settings_url( [ 'tab' => $section_key ] ) ); ?>"
											aria-controls="hodima-section-<?php echo esc_attr( $section_key ); ?>"
											data-tab="<?php echo esc_attr( $section_key ); ?>"
											<?php echo $section_key === $current ? 'aria-current="page"' : ''; ?>
										>
											<span class="dashicons <?php echo esc_attr( $section['icon'] ); ?>" aria-hidden="true"></span>
											<span class="hodima-settings__tab-label"><?php echo esc_html( $section['title'] ); ?></span>
											<span class="hodima-settings__tab-dirty" data-hodima-dirty hidden><span class="screen-reader-text">(تغییر ذخیره‌نشده)</span></span>
										</a>
									</li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endif; ?>
				<?php endforeach; ?>
			</nav>

			<form method="post" action="options.php" class="hodima-settings__form" novalidate data-hodima-form>
				<?php settings_fields( 'hodima_theme_settings_group' ); ?>

				<?php foreach ( $sections as $section_key => $section ) : ?>
					<section
						class="hodima-settings__section"
						id="hodima-section-<?php echo esc_attr( $section_key ); ?>"
						data-section="<?php echo esc_attr( $section_key ); ?>"
						aria-labelledby="hodima-section-<?php echo esc_attr( $section_key ); ?>-title"
						<?php echo $section_key === $current ? '' : 'hidden'; ?>
					>
						<header class="hodima-settings__section-head">
							<span class="dashicons <?php echo esc_attr( $section['icon'] ); ?>" aria-hidden="true"></span>
							<div>
								<h2 id="hodima-section-<?php echo esc_attr( $section_key ); ?>-title" tabindex="-1"><?php echo esc_html( $section['title'] ); ?></h2>
								<p><?php echo esc_html( $section['description'] ); ?></p>
							</div>
						</header>

						<div class="hodima-settings__panels">
							<?php
							// تب «شبکه‌های اجتماعی»: فهرست پویا (گزینه جدا، همین فرم) بالای قاب «نمایش در صفحه‌ها»
							if ( 'social' === $section_key && function_exists( 'hodima_social_admin_render' ) ) {
								hodima_social_admin_render();
							}

							foreach ( $panels as $panel_key => $panel ) {
								if ( $panel['section'] === $section_key ) {
									hodima_settings_render_panel(
										$panel_key,
										$panel,
										array_filter( $fields, static fn( array $field ): bool => ( $field['panel'] ?? '' ) === $panel_key ),
										$settings
									);
								}
							}

							// تب «صفحه اصلی»: فهرست بخش‌ها (گزینه جدا، همین فرم)
							if ( 'home' === $section_key && function_exists( 'hodima_home_admin_render' ) ) {
								hodima_home_admin_render();
							}
							?>
						</div>
					</section>
				<?php endforeach; ?>

				<footer class="hodima-settings__actions">
					<p class="hodima-settings__status" data-hodima-status aria-live="polite">
						<span class="hodima-settings__status-dirty">تغییرهای ذخیره‌نشده دارید</span>
						<span class="hodima-settings__status-hint">میان‌بر ذخیره: <kbd dir="ltr">Ctrl + S</kbd></span>
					</p>
					<?php submit_button( 'ذخیره تنظیمات', 'primary hodima-settings__save', 'submit', false ); ?>
				</footer>
			</form>
		</div>
		<?php hodima_settings_backup_form_if_any(); ?>
	</div>
	<?php
}

/** فرم بازگردانی پشتیبان (inc/extras.php) بیرون از فرم اصلی. */
function hodima_settings_backup_form_if_any(): void {
	if ( function_exists( 'hodima_settings_backup_form' ) ) {
		hodima_settings_backup_form();
	}
}

/**
 * یک قاب: سرتیتر (آیکون، عنوان، توضیح)، فیلدهای آزاد در شبکه، بعد زیرگروه‌ها
 * (کارت‌های نماد اعتماد یا ردیف‌های شبکه‌ها).
 *
 * @param array<string, mixed>                $panel
 * @param array<string, array<string, mixed>> $fields فیلدهای همین قاب، به ترتیب تعریف
 * @param array<string, mixed>                $settings
 */
function hodima_settings_render_panel( string $key, array $panel, array $fields, array $settings ): void {

	$layout  = (string) ( $panel['layout'] ?? 'grid' );
	$groups  = hodima_settings_groups();
	$loose   = array_filter( $fields, static fn( array $field ): bool => ! isset( $field['group'] ) );
	$grouped = [];

	foreach ( $fields as $field_key => $field ) {
		if ( isset( $field['group'] ) ) {
			$grouped[ $field['group'] ][ $field_key ] = $field;
		}
	}

	$collapse = ! empty( $panel['collapsible'] ); // قاب جمع‌شونده (<details>، پیش‌فرض بسته)؛ مثلا پیش‌نمایش تایپوگرافی
	$classes  = 'hodima-panel hodima-panel--' . $layout . ' hodima-panel-' . $key . ( ! empty( $panel['half'] ) ? ' hodima-panel--half' : '' ) . ( $collapse ? ' hodima-panel--collapsible' : '' ) . ( ! empty( $panel['headings'] ) ? ' hodima-panel--headings' : '' );
	$title    = 'hodima-panel-' . $key . '-title';
	$tips     = hodima_settings_section_tips( (string) $panel['section'] ); // تب با قانون «توضیح فقط داخل ؟»
	?>
	<?php if ( $collapse ) : ?>
		<?php
		// باز بودن اجباری (مثلا «فونت آپلودی» وقتی همان فونت انتخاب شده) و بازشدن با تغییر فیلدهای دیگر (admin.js)
		$force_open = ! empty( $panel['force_open'] ) && is_callable( $panel['force_open'] ) && (bool) call_user_func( $panel['force_open'] );
		?>
		<details class="<?php echo esc_attr( $classes ); ?>" data-hodima-collapsible="<?php echo esc_attr( $key ); ?>"<?php echo $force_open ? ' open data-hodima-keep-open' : ''; ?><?php echo ! empty( $panel['open_on'] ) ? ' data-hodima-open-on="' . esc_attr( implode( ' ', (array) $panel['open_on'] ) ) . '"' : ''; ?>>
			<summary class="hodima-panel__summary">
				<?php // دکمه ؟ داخل <summary> تودرتو است (axe nested-interactive) و بیرونش هم با بسته بودن <details> پنهان می‌شد: در تب tips توضیح قاب جمع‌شونده اولین سطر بدنه است (فقط وقتی باز شد دیده می‌شود) ?>
				<?php hodima_settings_panel_head( $title, $panel['title'], $tips ? '' : ( $panel['help'] ?? '' ), $panel['icon'] ?? '', '<span class="hodima-panel__chevron dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>', $tips ); ?>
			</summary>
	<?php else : ?>
	<section class="<?php echo esc_attr( $classes ); ?>" aria-labelledby="<?php echo esc_attr( $title ); ?>">
		<?php hodima_settings_panel_head( $title, $panel['title'], $panel['help'] ?? '', $panel['icon'] ?? '', '', $tips ); ?>
	<?php endif; ?>

		<div class="hodima-panel__body">
			<?php if ( $collapse && $tips && '' !== ( $panel['help'] ?? '' ) ) : ?>
				<p class="hodima-panel__note"><?php echo esc_html( (string) $panel['help'] ); ?></p>
			<?php endif; ?>
			<?php if ( 'swatches' === $layout ) : ?>
				<?php hodima_settings_palette_preview( $settings ); ?>
			<?php elseif ( 'typepreview' === $layout ) : ?>
				<?php hodima_typography_preview(); ?>
			<?php elseif ( 'lookpreview' === $layout ) : ?>
				<?php hodima_appearance_preview(); ?>
			<?php elseif ( 'backup' === $layout && function_exists( 'hodima_settings_backup_panel' ) ) : ?>
				<?php hodima_settings_backup_panel(); ?>
			<?php elseif ( 'ccss' === $layout && function_exists( 'hodima_ccss_admin_panel' ) ) : ?>
				<?php hodima_ccss_admin_panel(); // inc/performance/critical-css.php ?>
			<?php endif; ?>

			<?php if ( $loose && empty( $panel['headings'] ) ) : ?>
				<div class="hodima-panel__fields">
					<?php foreach ( $loose as $field_key => $field ) : ?>
						<?php hodima_settings_render_field( $field_key, $field, $settings[ $field_key ] ); ?>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( $grouped ) : ?>
				<?php if ( ! empty( $panel['headings'] ) ) : ?>
					<?php hodima_settings_render_headings( $panel, $grouped, $groups, $settings, $loose ); ?>
				<?php elseif ( 'tiles' === $layout ) : ?>
					<?php hodima_settings_render_tiles( $panel, $grouped, $groups, $settings ); ?>
				<?php else : ?>
					<?php if ( ! empty( $panel['columns'] ) ) : ?>
						<?php hodima_settings_render_columns( (array) $panel['columns'] ); ?>
					<?php endif; ?>
					<?php hodima_settings_render_items( $grouped, $groups, $settings ); ?>
				<?php endif; ?>
			<?php endif; ?>
		</div>
	<?php echo $collapse ? '</details>' : '</section>'; // phpcs:ignore WordPress.Security.EscapeOutput -- برچسب ثابت ?>
	<?php
}

/**
 * سرستون‌های جدول «ردیف‌ها» (فقط تزئینی؛ برچسب فیلدها برای صفحه‌خوان در خود ردیف است).
 *
 * @param array<int, string> $columns
 */
function hodima_settings_render_columns( array $columns ): void {
	?>
	<div class="hodima-panel__columns" aria-hidden="true">
		<?php foreach ( $columns as $column ) : ?>
			<span><?php echo esc_html( (string) $column ); ?></span>
		<?php endforeach; ?>
	</div>
	<?php
}

/**
 * ردیف‌های یک قاب (هر زیرگروه یک fieldset: نماد اعتماد، شبکه، تیتر).
 *
 * @param array<string, array<string, array<string, mixed>>> $grouped
 * @param array<string, array<string, mixed>>               $groups
 * @param array<string, mixed>                              $settings
 */
function hodima_settings_render_items( array $grouped, array $groups, array $settings, string $css_class = 'hodima-panel__items' ): void {
	?>
	<div class="<?php echo esc_attr( $css_class ); ?>">
		<?php foreach ( $grouped as $group => $group_fields ) : ?>
			<fieldset class="hodima-group" data-group="<?php echo esc_attr( $group ); ?>">
				<legend class="hodima-group__title"><?php echo esc_html( $groups[ $group ]['title'] ?? $group ); ?></legend>
				<div class="hodima-group__fields">
					<?php foreach ( $group_fields as $field_key => $field ) : ?>
						<?php hodima_settings_render_field( $field_key, $field, $settings[ $field_key ] ); ?>
					<?php endforeach; ?>
				</div>
			</fieldset>
		<?php endforeach; ?>
	</div>
	<?php
}

/**
 * قاب «تیترها» (3.0.9): اندازه‌ها (فقط همین دو ستون برای هر تیتر فرق دارند) در جدول کوتاه،
 * وزن/فاصله خطوط/رنگ یک بار برای همه (کنترل کمکی بی name که مقدار شش فیلد واقعی را
 * هم‌زمان عوض می‌کند، admin.js) و «تنظیم جدا برای هر تیتر» جمع‌شونده. فیلدهای واقعی
 * همان کلیدهای قبلی‌اند و همیشه در فرم هستند (جمع‌شده هم ارسال می‌شوند).
 *
 * @param array<string, mixed>                               $panel
 * @param array<string, array<string, array<string, mixed>>> $grouped
 * @param array<string, array<string, mixed>>               $groups
 * @param array<string, mixed>                              $settings
 * @param array<string, array<string, mixed>>               $loose فیلدهای آزاد قاب (فونت تیترها)؛ اول کارت «همه تیترها»
 */
function hodima_settings_render_headings( array $panel, array $grouped, array $groups, array $settings, array $loose = [] ): void {

	$sizes  = [];
	$others = [];
	foreach ( $grouped as $group => $group_fields ) {
		foreach ( $group_fields as $field_key => $field ) {
			if ( str_contains( $field_key, '_size' ) ) {
				$sizes[ $group ][ $field_key ] = $field;
			} else {
				$others[ $group ][ $field_key ] = $field;
			}
		}
	}

	$shared = hodima_typography_shared_heading_fields( $settings );
	?>
	<?php if ( $shared['fields'] || $loose ) : ?>
		<div class="hodima-headings-all">
			<h4 class="hodima-headings-all__title">همه تیترها <?php echo hodima_settings_tip_html( 'فونت، وزن، فاصله خطوط و رنگ یکسان برای H1 تا H6. اندازه هر تیتر در جدول کنار آن است.', 'hodima-headings-all-help', 'همه تیترها' ); // phpcs:ignore WordPress.Security.EscapeOutput -- hodima_settings_tip_html خروجی را escape کرده ?></h4>
			<div class="hodima-panel__fields hodima-headings-all__fields">
				<?php foreach ( $loose as $field_key => $field ) : ?>
					<?php hodima_settings_render_field( $field_key, $field, $settings[ $field_key ] ); ?>
				<?php endforeach; ?>
				<?php foreach ( $shared['fields'] as $field_key => $item ) : ?>
					<?php hodima_settings_render_field( $field_key, $item['field'], $item['value'] ); ?>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endif; ?>

	<div class="hodima-headings-sizes">
		<?php hodima_settings_render_columns( (array) ( $panel['columns'] ?? [] ) ); ?>
		<?php hodima_settings_render_items( $sizes, $groups, $settings ); ?>
	</div>

	<details class="hodima-more hodima-headings-more" <?php echo $shared['differs'] ? 'open' : ''; ?>>
		<summary class="hodima-more__summary">تنظیم جدا برای هر تیتر<?php echo $shared['differs'] ? ' (مقدارهای فعلی یکسان نیستند)' : ''; ?></summary>
		<?php hodima_settings_render_columns( (array) ( $panel['columns_more'] ?? [] ) ); ?>
		<?php hodima_settings_render_items( $others, $groups, $settings ); ?>
	</details>
	<?php
}

/**
 * قاب «فونت آپلودی»: کاشی هر وزن؛ وزن‌های اصلی دیده می‌شوند و بقیه زیر «وزن‌های بیشتر»
 * (اگر یکی‌شان فایل دارد خودکار باز است).
 *
 * @param array<string, mixed>                               $panel
 * @param array<string, array<string, array<string, mixed>>> $grouped
 * @param array<string, array<string, mixed>>               $groups
 * @param array<string, mixed>                              $settings
 */
function hodima_settings_render_tiles( array $panel, array $grouped, array $groups, array $settings ): void {

	$primary = (array) ( $panel['primary'] ?? [] );
	$main    = array_intersect_key( $grouped, array_flip( $primary ) );
	$more    = array_diff_key( $grouped, $main );
	$filled  = false;

	foreach ( $more as $group_fields ) {
		foreach ( array_keys( $group_fields ) as $field_key ) {
			$filled = $filled || ! empty( $settings[ $field_key ] );
		}
	}

	hodima_settings_render_items( $main, $groups, $settings, 'hodima-panel__items hodima-panel__items--tiles' );
	?>
	<?php if ( $more ) : ?>
		<details class="hodima-more" <?php echo $filled ? 'open' : ''; ?>>
			<summary class="hodima-more__summary">وزن‌های بیشتر (نازک، متوسط، نیم‌ضخیم، خیلی ضخیم، سیاه)</summary>
			<?php hodima_settings_render_items( $more, $groups, $settings, 'hodima-panel__items hodima-panel__items--tiles' ); ?>
		</details>
	<?php endif; ?>
	<?php
}

