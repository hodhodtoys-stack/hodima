<?php
/**
 * Hodima Core — پنل مدیریت «هدیما»
 * Path: plugins/hodima-core/includes/admin-hub.php
 *
 *   هدیما ← وضعیت            : افزونه‌ها، ماژول‌های فعال و بررسی محیط سرور
 *   هدیما ← سئو / فروشگاه / رسانه : روشن و خاموش کردن ماژول‌های هر افزونه
 *
 * صفحات تنظیمات خود ماژول‌ها (اسکیما، ریدایرکت‌ها، استوری و...) همان‌جای
 * قبلی‌شان مانده‌اند؛ از کارت هر ماژول به آن‌ها لینک داده می‌شود.
 */

declare(strict_types=1);

namespace Hodima\Core\Admin;

use Hodima\Core\Modules;

defined( 'ABSPATH' ) || exit;

const HUB_SLUG = 'hodima-hub';

/* =========================================================================
 * ۱. منوها
 * ========================================================================= */

add_action( 'admin_menu', static function (): void {

	// عنوان منو «ابزارهای هدیما»؛ اسلاگ (hodima-hub) و آدرس صفحه‌ها تغییر نکرده است.
	// ریدایرکت‌ها، خوشه‌بندی، اسلایدر و نوتیفیکیشن‌ها هم زیر همین منو هستند
	// (هر افزونه با hodima_admin_menu_parent() زیرمنوی خودش را اضافه می‌کند).
	add_menu_page(
		'ابزارهای هدیما',
		'ابزارهای هدیما',
		'manage_options',
		HUB_SLUG,
		__NAMESPACE__ . '\\render_dashboard',
		'dashicons-screenoptions',
		58.5
	);

	add_submenu_page( HUB_SLUG, 'وضعیت ابزارهای هدیما', 'وضعیت', 'manage_options', HUB_SLUG, __NAMESPACE__ . '\\render_dashboard' );

	foreach ( Modules::plugins() as $plugin => $data ) {
		add_submenu_page(
			HUB_SLUG,
			'ماژول‌های ' . $data['title'],
			'ماژول‌های ' . $data['title'],
			'manage_options',
			modules_page_slug( $plugin ),
			static fn() => render_modules_page( $plugin )
		);
	}
}, 5 );

function modules_page_slug( string $plugin ): string {
	return HUB_SLUG . '-' . $plugin;
}

function modules_page_url( string $plugin ): string {
	return admin_url( 'admin.php?page=' . modules_page_slug( $plugin ) );
}

/* =========================================================================
 * ۲. ذخیره تنظیمات ماژول‌ها (Settings API)
 * ========================================================================= */

add_action( 'admin_init', static function (): void {

	foreach ( array_keys( Modules::plugins() ) as $plugin ) {
		register_setting( 'hodima_modules_group_' . $plugin, Modules::option_name( $plugin ), [
			'type'              => 'array',
			'sanitize_callback' => static fn( mixed $input ): array => Modules::sanitize( $plugin, $input ),
			'default'           => [],
			'show_in_rest'      => false,
		] );

		// ماژول‌ها آدرس (rewrite) و خروجی صفحات را عوض می‌کنند
		add_action( 'update_option_' . Modules::option_name( $plugin ), __NAMESPACE__ . '\\after_modules_changed' );
		add_action( 'add_option_' . Modules::option_name( $plugin ), __NAMESPACE__ . '\\after_modules_changed' );
	}
} );

function after_modules_changed(): void {
	// قوانین بازنویسی در درخواست بعدی با ماژول‌های جدید ساخته می‌شوند
	delete_option( 'rewrite_rules' );
	delete_option( 'arian_router_flushed' );
	do_action( 'litespeed_purge_all' );
}

/* =========================================================================
 * ۳. لینک «تنظیمات» در فهرست افزونه‌ها
 * ========================================================================= */

add_action( 'admin_init', static function (): void {

	add_filter( 'plugin_action_links_' . plugin_basename( HODIMA_CORE_PLUGIN_FILE ), static fn( array $links ): array => [
		'<a href="' . esc_url( admin_url( 'admin.php?page=' . HUB_SLUG ) ) . '">وضعیت</a>',
		...$links,
	] );

	foreach ( Modules::plugins() as $plugin => $data ) {
		add_filter( 'plugin_action_links_' . plugin_basename( $data['file'] ), static fn( array $links ): array => [
			'<a href="' . esc_url( modules_page_url( $plugin ) ) . '">ماژول‌ها</a>',
			...$links,
		] );
	}
} );

/* =========================================================================
 * ۴. دارایی‌ها
 * ========================================================================= */

add_action( 'admin_enqueue_scripts', static function ( string $hook ): void {

	if ( ! str_contains( $hook, HUB_SLUG ) ) {
		return;
	}

	foreach ( [ 'css' => 'admin-hub.css', 'js' => 'admin-hub.js' ] as $type => $file ) {
		$path    = HODIMA_CORE_PLUGIN_DIR . '/assets/' . $file;
		$url     = HODIMA_CORE_PLUGIN_URL . '/assets/' . $file;
		$version = is_file( $path ) ? (string) filemtime( $path ) : HODIMA_CORE_PLUGIN_VERSION;

		'css' === $type
			? wp_enqueue_style( 'hodima-hub', $url, [], $version )
			: wp_enqueue_script( 'hodima-hub', $url, [], $version, [ 'in_footer' => true, 'strategy' => 'defer' ] );
	}
} );

/* =========================================================================
 * ۵. صفحه «وضعیت»
 * ========================================================================= */

/**
 * افزونه‌های خانواده هدیما، حتی وقتی فعال نیستند.
 *
 * @return array<string, array{title:string, constant:string, plugin:string}>
 */
function family(): array {
	return [
		'core'     => [ 'title' => 'Hodima Core', 'constant' => 'HODIMA_CORE_PLUGIN_VERSION', 'plugin' => '' ],
		'seo'      => [ 'title' => 'Hodima SEO', 'constant' => 'HODIMA_SEO_VERSION', 'plugin' => 'seo' ],
		'commerce' => [ 'title' => 'Hodima Commerce', 'constant' => 'HODIMA_COMMERCE_VERSION', 'plugin' => 'commerce' ],
		'media'    => [ 'title' => 'Hodima Media', 'constant' => 'HODIMA_MEDIA_VERSION', 'plugin' => 'media' ],
	];
}

/**
 * بررسی‌های محیط اجرا.
 *
 * @return list<array{label:string, ok:bool, level:string, detail:string}>
 */
function environment_checks(): array {

	global $wp_version;

	$theme      = wp_get_theme( get_template() );
	$is_hodima  = 'Hodima' === $theme->get( 'Name' );
	$theme_ver  = (string) $theme->get( 'Version' );
	$robots_txt = is_file( ABSPATH . 'robots.txt' );
	$updates    = function_exists( 'Hodima\\Core\\Updates\\status' ) ? \Hodima\Core\Updates\status() : [ 'enabled' => false, 'count' => 0 ];

	return [
		[
			'label'  => 'نسخه PHP',
			'ok'     => version_compare( PHP_VERSION, '8.4', '>=' ),
			'level'  => 'error',
			'detail' => PHP_VERSION . ' — حداقل 8.4',
		],
		[
			'label'  => 'نسخه وردپرس',
			'ok'     => version_compare( (string) $wp_version, '6.5', '>=' ),
			'level'  => 'error',
			'detail' => (string) $wp_version . ' — حداقل 6.5',
		],
		[
			'label'  => 'قالب هدیما نسخه ۲',
			'ok'     => $is_hodima && version_compare( $theme_ver, '2.0.0', '>=' ),
			'level'  => 'warning',
			'detail' => $is_hodima ? 'نسخه ' . $theme_ver : 'قالب فعال: ' . $theme->get( 'Name' ),
		],
		[
			'label'  => 'ووکامرس',
			'ok'     => class_exists( 'WooCommerce' ),
			'level'  => 'warning',
			'detail' => class_exists( 'WooCommerce' ) ? 'فعال' : 'غیرفعال — ماژول‌های فروشگاه اجرا نمی‌شوند',
		],
		[
			'label'  => 'کش دائمی اشیا (Redis/Memcached)',
			'ok'     => wp_using_ext_object_cache(),
			'level'  => 'info',
			'detail' => wp_using_ext_object_cache() ? 'فعال' : 'توصیه می‌شود؛ محدودیت نرخ و کش‌های ماژول‌ها بدون آن در دیتابیس نوشته می‌شوند',
		],
		[
			'label'  => 'LiteSpeed Cache',
			'ok'     => defined( 'LSCWP_V' ),
			'level'  => 'info',
			'detail' => defined( 'LSCWP_V' ) ? 'فعال' : 'نصب نیست',
		],
		[
			'label'  => 'HTTPS',
			'ok'     => str_starts_with( home_url(), 'https://' ),
			'level'  => 'warning',
			'detail' => home_url(),
		],
		[
			'label'  => 'robots.txt هوشمند',
			'ok'     => ! $robots_txt,
			'level'  => 'warning',
			'detail' => $robots_txt ? 'فایل فیزیکی robots.txt در ریشه سایت جلوی قوانین افزونه سئو را گرفته است' : 'توسط افزونه سئو ساخته می‌شود',
		],
		[
			'label'  => 'به‌روزرسانی خودکار و دستی از پیشخوان وردپرس',
			'ok'     => $updates['enabled'],
			'level'  => 'warning',
			'detail' => $updates['enabled']
				? sprintf( '%s بسته (قالب و افزونه‌ها) هر ۱۲ ساعت بررسی می‌شوند؛ نسخه جدید در «پیشخوان ← به‌روزرسانی‌ها» می‌آید', number_format_i18n( $updates['count'] ) )
				: 'غیرفعال (فیلتر hodima_updates_enabled یا کتابخانه ناقص)',
		],
		[
			'label'  => 'نمایش خطاها روی سایت',
			'ok'     => ! ( defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY && defined( 'WP_DEBUG' ) && WP_DEBUG ),
			'level'  => 'warning',
			'detail' => 'در سایت اصلی WP_DEBUG_DISPLAY باید خاموش باشد',
		],
	];
}

function render_dashboard(): void {

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$registered = Modules::plugins();
	?>
	<div class="wrap hd-wrap hodima-hub">
		<?php render_header( 'ابزارهای هدیما', 'وضعیت افزونه‌ها و ماژول‌ها، و بررسی آمادگی سرور.' ); ?>

		<section class="hodima-hub__section" aria-labelledby="hodima-hub-plugins">
			<h2 id="hodima-hub-plugins">افزونه‌ها</h2>
			<div class="hodima-hub__grid">
				<?php foreach ( family() as $key => $item ) : ?>
					<?php
					$active  = defined( $item['constant'] );
					$data    = $item['plugin'] ? ( $registered[ $item['plugin'] ] ?? null ) : null;
					$total   = $data ? count( $data['modules'] ) : 0;
					$enabled = $data ? count( array_filter( array_keys( $data['modules'] ), static fn( string $id ): bool => Modules::is_enabled( $item['plugin'], $id ) ) ) : 0;
					?>
					<article class="hodima-card <?php echo $active ? 'is-on' : 'is-off'; ?>">
						<header class="hodima-card__head">
							<h3 class="hodima-card__title"><?php echo esc_html( $item['title'] ); ?></h3>
							<span class="hodima-pill <?php echo $active ? 'hodima-pill--ok' : 'hodima-pill--muted'; ?>">
								<?php echo $active ? esc_html( 'فعال · ' . constant( $item['constant'] ) ) : 'غیرفعال'; ?>
							</span>
						</header>
						<p class="hodima-card__desc">
							<?php
							echo esc_html( match ( true ) {
								'core' === $key => 'کتابخانه مشترک و همین پنل.',
								null !== $data  => sprintf( '%1$s از %2$s ماژول روشن', number_format_i18n( $enabled ), number_format_i18n( $total ) ),
								default         => 'برای استفاده، افزونه را از «افزونه‌ها» فعال کنید.',
							} );
							?>
						</p>
						<?php if ( $data ) : ?>
							<a class="button hodima-button" href="<?php echo esc_url( modules_page_url( $item['plugin'] ) ); ?>">مدیریت ماژول‌ها</a>
						<?php elseif ( ! $active ) : ?>
							<a class="button" href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>">افزونه‌ها</a>
						<?php endif; ?>
					</article>
				<?php endforeach; ?>
			</div>
		</section>

		<section class="hodima-hub__section" aria-labelledby="hodima-hub-env">
			<h2 id="hodima-hub-env">بررسی محیط</h2>
			<ul class="hodima-checks">
				<?php foreach ( environment_checks() as $check ) : ?>
					<li class="hodima-check hodima-check--<?php echo esc_attr( $check['ok'] ? 'ok' : $check['level'] ); ?>">
						<span class="hodima-check__icon" aria-hidden="true"></span>
						<span class="hodima-check__label"><?php echo esc_html( $check['label'] ); ?></span>
						<span class="hodima-check__detail"><?php echo esc_html( $check['detail'] ); ?></span>
						<span class="screen-reader-text"><?php echo $check['ok'] ? 'درست' : 'نیاز به بررسی'; ?></span>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>

		<?php if ( function_exists( 'hodima_settings' ) ) : ?>
			<p class="hodima-hub__footnote">
				لوگو، اطلاعات تماس، فوتر، شبکه‌های اجتماعی و Google Analytics در
				<a href="<?php echo esc_url( menu_page_url( 'hodima-settings', false ) ?: admin_url( 'themes.php?page=hodima-settings' ) ); ?>">تنظیمات قالب هدیما</a>
				(تنظیمات قالب) هستند.
			</p>
		<?php endif; ?>
	</div>
	<?php
}

/* =========================================================================
 * ۶. صفحه ماژول‌های هر افزونه
 * ========================================================================= */

function render_modules_page( string $plugin ): void {

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$data = Modules::plugins()[ $plugin ] ?? null;
	if ( ! $data ) {
		return;
	}

	$option = Modules::option_name( $plugin );
	?>
	<div class="wrap hd-wrap hodima-hub">
		<?php render_header( 'ماژول‌های ' . $data['title'], $data['description'], modules_page_slug( $plugin ), 'dashicons-admin-plugins' ); ?>

		<?php settings_errors(); ?>

		<form method="post" action="options.php" class="hodima-modules" data-hodima-modules>
			<?php settings_fields( 'hodima_modules_group_' . $plugin ); ?>

			<p class="hodima-hub__intro">
				ماژول خاموش اصلا بارگذاری نمی‌شود (نه منو، نه هوک، نه کوئری). داده‌هایش پاک نمی‌شود و با روشن کردن دوباره برمی‌گردد.
			</p>

			<div class="hodima-hub__grid">
				<?php foreach ( $data['modules'] as $id => $module ) : ?>
					<?php
					$enabled  = Modules::is_enabled( $plugin, $id );
					$loaded   = Modules::is_loaded( $plugin, $id );
					$field_id = 'hodima-module-' . $plugin . '-' . $id;

					[ $status_class, $status_text ] = match ( true ) {
						! $module->available => [ 'hodima-pill--warn', 'نیاز به ووکامرس' ],
						$loaded              => [ 'hodima-pill--ok', 'فعال' ],
						$enabled             => [ 'hodima-pill--info', 'پس از ذخیره فعال می‌شود' ],
						default              => [ 'hodima-pill--muted', 'خاموش' ],
					};

					$recommends = array_values( array_filter( array_map(
						static fn( string $rid ): string => $data['modules'][ $rid ]->title ?? '',
						$module->recommends
					) ) );
					?>
					<article class="hodima-card hodima-module <?php echo $enabled ? 'is-on' : 'is-off'; ?>" data-hodima-module>
						<header class="hodima-card__head">
							<span class="dashicons <?php echo esc_attr( $module->icon ); ?> hodima-module__icon" aria-hidden="true"></span>
							<h3 class="hodima-card__title" id="<?php echo esc_attr( $field_id ); ?>-title"><?php echo esc_html( $module->title ); ?></h3>

							<label class="hodima-switch" for="<?php echo esc_attr( $field_id ); ?>">
								<input
									type="checkbox"
									role="switch"
									id="<?php echo esc_attr( $field_id ); ?>"
									name="<?php echo esc_attr( $option . '[' . $id . ']' ); ?>"
									value="1"
									aria-labelledby="<?php echo esc_attr( $field_id ); ?>-title"
									<?php checked( $enabled ); ?>
									<?php echo '' !== $module->warning ? 'data-warning="' . esc_attr( $module->warning ) . '"' : ''; ?>
								>
								<span class="hodima-switch__track" aria-hidden="true"></span>
							</label>
						</header>

						<p class="hodima-card__desc"><?php echo esc_html( $module->description ); ?></p>

						<?php
						/*
						 * هشدار «اثر خاموش کردن» است، نه وضعیت فعلی. قبلا بدون هیچ
						 * توضیحی و همیشه با رنگ هشدار نمایش داده می‌شد؛ روی ماژول روشن
						 * طوری خوانده می‌شد که انگار همین الان چیزی کار نمی‌کند.
						 * حالا با «اگر خاموش شود:» و روی ماژول روشن کم‌رنگ (admin-hub.css).
						 */
						?>
						<?php if ( '' !== $module->warning ) : ?>
							<p class="hodima-module__warning"><strong>اگر خاموش شود:</strong> <?php echo esc_html( $module->warning ); ?></p>
						<?php endif; ?>

						<?php if ( $recommends ) : ?>
							<p class="hodima-module__hint">بهتر است همراه با: <?php echo esc_html( implode( '، ', $recommends ) ); ?></p>
						<?php endif; ?>

						<footer class="hodima-card__foot">
							<span class="hodima-pill <?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_text ); ?></span>
							<?php if ( $loaded && '' !== $module->settings ) : ?>
								<a class="hodima-module__link" href="<?php echo esc_url( admin_url( $module->settings ) ); ?>">تنظیمات ماژول ←</a>
							<?php endif; ?>
						</footer>
					</article>
				<?php endforeach; ?>
			</div>

			<footer class="hodima-hub__actions">
				<span class="hodima-hub__dirty" data-hodima-dirty hidden>تغییرات ذخیره نشده‌اند.</span>
				<?php submit_button( 'ذخیره ماژول‌ها', 'primary hodima-button', 'submit', false ); ?>
			</footer>
		</form>

		<dialog class="hodima-dialog" data-hodima-dialog aria-labelledby="hodima-dialog-title">
			<form method="dialog">
				<h2 id="hodima-dialog-title">خاموش کردن این ماژول؟</h2>
				<p data-hodima-dialog-text></p>
				<div class="hodima-dialog__actions">
					<button class="button" value="cancel" autofocus>انصراف</button>
					<button class="button hodima-button hodima-button--danger" value="confirm">خاموش شود</button>
				</div>
			</form>
		</dialog>
	</div>
	<?php
}

/** هدر مشترک + تب‌های پنل (وضعیت و ماژول‌های هر افزونه) زیر هدر. */
function render_header( string $title, string $description, string $current = HUB_SLUG, string $icon = 'dashicons-screenoptions' ): void {

	$tabs = [
		HUB_SLUG => [ 'label' => 'وضعیت', 'url' => admin_url( 'admin.php?page=' . HUB_SLUG ), 'icon' => 'dashicons-dashboard' ],
	];

	foreach ( Modules::plugins() as $plugin => $data ) {
		$tabs[ modules_page_slug( $plugin ) ] = [
			'label' => 'ماژول‌های ' . $data['title'],
			'url'   => modules_page_url( $plugin ),
			'icon'  => match ( $plugin ) {
				'seo'      => 'dashicons-search',
				'commerce' => 'dashicons-cart',
				'media'    => 'dashicons-format-video',
				default    => 'dashicons-admin-plugins',
			},
		];
	}

	hodima_admin_header( [
		'title'       => $title,
		'description' => $description,
		'icon'        => $icon,
		'tabs'        => $tabs,
		'current'     => $current,
		'tabs_label'  => 'بخش‌های ابزارهای هدیما',
	] );
}
