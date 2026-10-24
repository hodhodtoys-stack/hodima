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
			'label'  => 'ورژن قالب هدیما',
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

	// بررسی‌های محیط: «نیازمند توجه» (هشدار/خطا)، «توصیه» (info) و سالم؛ سالم‌ها جمع‌شده می‌آیند
	$healthy = [];
	$notes   = [];
	foreach ( environment_checks() as $check ) {
		if ( $check['ok'] ) {
			$healthy[] = $check;
		} else {
			$notes[] = $check;
		}
	}
	$problems = count( array_filter( $notes, static fn( array $c ): bool => 'info' !== $c['level'] ) );
	$advice   = count( $notes ) - $problems;
	$has_err  = (bool) array_filter( $notes, static fn( array $c ): bool => 'error' === $c['level'] );
	$state    = $has_err ? 'error' : ( $problems > 0 ? 'warn' : 'ok' );

	$active_count  = 0;
	$enabled_total = 0;
	foreach ( family() as $item ) {
		if ( defined( $item['constant'] ) ) {
			++$active_count;
		}
		$data = $item['plugin'] ? ( $registered[ $item['plugin'] ] ?? null ) : null;
		if ( $data ) {
			foreach ( array_keys( $data['modules'] ) as $id ) {
				$enabled_total += Modules::is_enabled( $item['plugin'], $id ) ? 1 : 0;
			}
		}
	}

	$title = match ( $state ) {
		'error' => 'مشکلی نیاز به رفع دارد',
		'warn'  => 'چند مورد نیاز به بررسی دارد',
		default => 'همه‌چیز سالم است',
	};
	$text  = sprintf( '%1$s افزونه فعال، %2$s ماژول روشن.', number_format_i18n( $active_count ), number_format_i18n( $enabled_total ) );
	if ( $problems > 0 ) {
		$text .= sprintf( ' %s مورد در «بررسی محیط» نیاز به رفع دارد.', number_format_i18n( $problems ) );
	} elseif ( $advice > 0 ) {
		$text .= sprintf( ' %s توصیه برای بهبود پایین آمده است.', number_format_i18n( $advice ) );
	}
	?>
	<div class="wrap hd-wrap hodima-hub">
		<?php render_header( 'ابزارهای هدیما', 'وضعیت افزونه‌ها و ماژول‌ها، و بررسی آمادگی سرور.' ); ?>

		<section class="hodima-statusbar is-<?php echo esc_attr( $state ); ?>" aria-label="خلاصه وضعیت">
			<span class="dashicons <?php echo esc_attr( 'ok' === $state ? 'dashicons-yes-alt' : 'dashicons-warning' ); ?>" aria-hidden="true"></span>
			<div class="hodima-statusbar__body">
				<h2 class="hodima-statusbar__title"><?php echo esc_html( $title ); ?></h2>
				<p class="hodima-statusbar__text"><?php echo esc_html( $text ); ?></p>
			</div>
			<span class="hodima-pill <?php echo esc_attr( match ( $state ) { 'ok' => 'hodima-pill--ok', 'warn' => 'hodima-pill--warn', default => 'hodima-pill--error' } ); ?>">
				<?php echo esc_html( match ( $state ) { 'ok' => 'سالم', 'warn' => 'نیاز به بررسی', default => 'خطا' } ); ?>
			</span>
		</section>

		<section class="hodima-kpis" aria-label="افزونه‌ها">
			<?php foreach ( family() as $key => $item ) : ?>
				<?php
				$active  = defined( $item['constant'] );
				$data    = $item['plugin'] ? ( $registered[ $item['plugin'] ] ?? null ) : null;
				$total   = $data ? count( $data['modules'] ) : 0;
				$enabled = $data ? count( array_filter( array_keys( $data['modules'] ), static fn( string $id ): bool => Modules::is_enabled( $item['plugin'], $id ) ) ) : 0;
				?>
				<article class="hodima-kpi <?php echo $active ? 'is-on' : 'is-off'; ?>">
					<div class="hodima-kpi__top">
						<h3 class="hodima-kpi__title"><?php echo esc_html( $item['title'] ); ?></h3>
						<?php if ( $active ) : ?>
							<span class="hodima-kpi__ver"><?php echo esc_html( (string) constant( $item['constant'] ) ); ?></span>
						<?php else : ?>
							<span class="hodima-pill hodima-pill--muted">غیرفعال</span>
						<?php endif; ?>
					</div>
					<?php if ( $data ) : ?>
						<p class="hodima-kpi__text">
							<b><?php echo esc_html( number_format_i18n( $enabled ) ); ?></b><?php echo esc_html( sprintf( 'از %s ماژول روشن', number_format_i18n( $total ) ) ); ?>
						</p>
						<div class="hodima-kpi__bar" aria-hidden="true"><i style="inline-size: <?php echo esc_attr( (string) ( $total > 0 ? round( 100 * $enabled / $total ) : 0 ) ); ?>%"></i></div>
						<a class="hodima-kpi__link" href="<?php echo esc_url( modules_page_url( $item['plugin'] ) ); ?>">مدیریت ماژول‌ها</a>
					<?php elseif ( ! $active ) : ?>
						<p class="hodima-kpi__text">برای استفاده، افزونه را از «افزونه‌ها» فعال کنید.</p>
						<a class="hodima-kpi__link" href="<?php echo esc_url( admin_url( 'plugins.php' ) ); ?>">افزونه‌ها</a>
					<?php else : ?>
						<p class="hodima-kpi__text">کتابخانه مشترک و همین پنل.</p>
					<?php endif; ?>
				</article>
			<?php endforeach; ?>
		</section>

		<section class="hodima-env" aria-labelledby="hodima-hub-env">
			<div class="hodima-env__head">
				<h2 id="hodima-hub-env">بررسی محیط</h2>
				<div class="hodima-env__counts">
					<?php if ( $problems > 0 ) : ?>
						<span class="hodima-pill hodima-pill--warn"><?php echo esc_html( sprintf( '%s نیاز به بررسی', number_format_i18n( $problems ) ) ); ?></span>
					<?php endif; ?>
					<?php if ( $advice > 0 ) : ?>
						<span class="hodima-pill hodima-pill--info"><?php echo esc_html( sprintf( '%s توصیه', number_format_i18n( $advice ) ) ); ?></span>
					<?php endif; ?>
					<span class="hodima-pill hodima-pill--ok"><?php echo esc_html( sprintf( '%s سالم', number_format_i18n( count( $healthy ) ) ) ); ?></span>
				</div>
			</div>
			<?php if ( $notes ) : ?>
				<ul class="hodima-checks">
					<?php foreach ( $notes as $check ) : ?>
						<li class="hodima-check hodima-check--<?php echo esc_attr( $check['level'] ); ?>">
							<span class="hodima-check__icon" aria-hidden="true"></span>
							<span class="hodima-check__label"><?php echo esc_html( $check['label'] ); ?></span>
							<span class="hodima-check__detail"><?php echo esc_html( $check['detail'] ); ?></span>
							<span class="screen-reader-text">نیاز به بررسی</span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<?php if ( $healthy ) : ?>
				<details class="hodima-env__more"<?php echo $notes ? '' : ' open'; ?>>
					<summary><?php echo esc_html( sprintf( '%s مورد سالم', number_format_i18n( count( $healthy ) ) ) ); ?></summary>
					<ul class="hodima-checks">
						<?php foreach ( $healthy as $check ) : ?>
							<li class="hodima-check hodima-check--ok">
								<span class="hodima-check__icon" aria-hidden="true"></span>
								<span class="hodima-check__label"><?php echo esc_html( $check['label'] ); ?></span>
								<span class="hodima-check__detail"><?php echo esc_html( $check['detail'] ); ?></span>
								<span class="screen-reader-text">درست</span>
							</li>
						<?php endforeach; ?>
					</ul>
				</details>
			<?php endif; ?>
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

			<?php
			$module_total   = count( $data['modules'] );
			$module_enabled = count( array_filter( array_keys( $data['modules'] ), static fn( string $mid ): bool => Modules::is_enabled( $plugin, $mid ) ) );
			$all_enabled    = $module_enabled === $module_total;
			?>
			<section class="hodima-statusbar<?php echo $all_enabled ? '' : ' is-warn'; ?>" aria-label="خلاصه ماژول‌ها">
				<span class="dashicons <?php echo esc_attr( $all_enabled ? 'dashicons-yes-alt' : 'dashicons-info' ); ?>" aria-hidden="true"></span>
				<div class="hodima-statusbar__body">
					<h2 class="hodima-statusbar__title"><?php echo esc_html( $all_enabled ? sprintf( 'همه %s ماژول روشن‌اند', number_format_i18n( $module_total ) ) : sprintf( '%1$s از %2$s ماژول روشن است', number_format_i18n( $module_enabled ), number_format_i18n( $module_total ) ) ); ?></h2>
					<p class="hodima-statusbar__text">ماژول خاموش اصلا بارگذاری نمی‌شود (نه منو، نه هوک، نه کوئری). داده‌هایش پاک نمی‌شود و با روشن کردن دوباره برمی‌گردد.</p>
				</div>
				<span class="hodima-pill <?php echo $all_enabled ? 'hodima-pill--ok' : 'hodima-pill--warn'; ?>"><?php echo esc_html( $all_enabled ? 'همه روشن' : 'برخی خاموش' ); ?></span>
			</section>

			<section class="hodima-modlist" aria-label="ماژول‌ها">
				<?php foreach ( $data['modules'] as $id => $module ) : ?>
					<?php
					$enabled  = Modules::is_enabled( $plugin, $id );
					$loaded   = Modules::is_loaded( $plugin, $id );
					$field_id = 'hodima-module-' . $plugin . '-' . $id;

					// فقط وضعیت‌های استثنا نوشته می‌شود؛ «فعال/خاموش» را خود کلید می‌گوید
					$note = match ( true ) {
						! $module->available => 'نیاز به ووکامرس',
						$enabled && ! $loaded => 'پس از ذخیره فعال می‌شود',
						default              => '',
					};

					$recommends = array_values( array_filter( array_map(
						static fn( string $rid ): string => $data['modules'][ $rid ]->title ?? '',
						$module->recommends
					) ) );
					?>
					<article class="hodima-module <?php echo $enabled ? 'is-on' : 'is-off'; ?>" data-hodima-module>
						<span class="dashicons <?php echo esc_attr( $module->icon ); ?> hodima-module__icon" aria-hidden="true"></span>

						<div class="hodima-module__text">
							<h3 class="hodima-module__title" id="<?php echo esc_attr( $field_id ); ?>-title"><?php echo esc_html( $module->title ); ?></h3>
							<p class="hodima-module__desc"><?php echo esc_html( $module->description ); ?></p>
							<?php if ( '' !== $note || $recommends || '' !== $module->warning ) : ?>
								<div class="hodima-module__meta">
									<?php if ( '' !== $note ) : ?>
										<span class="hodima-pill <?php echo esc_attr( ! $module->available ? 'hodima-pill--warn' : 'hodima-pill--info' ); ?>"><?php echo esc_html( $note ); ?></span>
									<?php endif; ?>
									<?php if ( $recommends ) : ?>
										<span class="hodima-module__hint">بهتر است همراه با: <?php echo esc_html( implode( '، ', $recommends ) ); ?></span>
									<?php endif; ?>
									<?php
									/*
									 * هشدار «اثر خاموش کردن» است، نه وضعیت فعلی (قبلا کادر زردِ همیشه‌پیدا
									 * بود). حالا جمع‌شونده؛ پنجره تأیید هنگام خاموش‌کردن همان متن را می‌گوید.
									 */
									?>
									<?php if ( '' !== $module->warning ) : ?>
										<details class="hodima-module__warning">
											<summary>اگر خاموش شود؟</summary>
											<p><?php echo esc_html( $module->warning ); ?></p>
										</details>
									<?php endif; ?>
								</div>
							<?php endif; ?>
						</div>

						<?php if ( $loaded && '' !== $module->settings ) : ?>
							<a class="hodima-module__link" href="<?php echo esc_url( admin_url( $module->settings ) ); ?>">تنظیمات ←</a>
						<?php endif; ?>

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
					</article>
				<?php endforeach; ?>
			</section>

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
