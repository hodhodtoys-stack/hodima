<?php
/**
 * صفحه تنظیمات «ورود و امنیت» (ابزارهای هدیما ← ورود و امنیت)
 * Path: plugins/hodima-core/includes/admin-login.php
 *
 * طرح تأییدشده کاربر (Artifact «طرح صفحه ورود پیشخوان»): نوع «تنظیمات رفتار» با
 * کالبد سربرگ ← ۳ کارت وضعیت ← کارت فرم + کارت پیش‌نمایش ← نوار ذخیره پایین.
 * منطق (خواندن، پاک‌سازی، آدرس لوگو) در login.php است؛ اینجا فقط صفحه و ذخیره.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

const HODIMA_CORE_LOGIN_PAGE = 'hodima-login';

add_action( 'admin_menu', static function (): void {
	add_submenu_page(
		hodima_admin_menu_parent(),
		'ورود و امنیت',
		'ورود و امنیت',
		'manage_options',
		HODIMA_CORE_LOGIN_PAGE,
		'hodima_core_login_render_page'
	);
}, 6 );

add_action( 'admin_enqueue_scripts', static function ( string $hook ): void {

	if ( ! str_ends_with( $hook, '_page_' . HODIMA_CORE_LOGIN_PAGE ) ) {
		return;
	}

	$css = HODIMA_CORE_PLUGIN_DIR . '/assets/admin-login.css';
	$js  = HODIMA_CORE_PLUGIN_DIR . '/assets/admin-login.js';

	wp_enqueue_style( 'hodima-core-admin-login', HODIMA_CORE_PLUGIN_URL . '/assets/admin-login.css', [ HODIMA_ADMIN_UI_HANDLE ], is_readable( $css ) ? (string) filemtime( $css ) : HODIMA_CORE_PLUGIN_VERSION );
	wp_enqueue_script( 'hodima-core-admin-login', HODIMA_CORE_PLUGIN_URL . '/assets/admin-login.js', [], is_readable( $js ) ? (string) filemtime( $js ) : HODIMA_CORE_PLUGIN_VERSION, [ 'in_footer' => true, 'strategy' => 'defer' ] );
} );

/* =====================================================================
 * ذخیره
 * ===================================================================== */

add_action( 'admin_post_hodima_core_login_save', static function (): void {

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html( 'دسترسی لازم را ندارید.' ), '', [ 'response' => 403 ] );
	}

	check_admin_referer( 'hodima_core_login_save' );

	$posted = isset( $_POST['hodima_login'] ) && is_array( $_POST['hodima_login'] )
		? array_map( 'wp_unslash', $_POST['hodima_login'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- در hodima_core_login_sanitize پاک می‌شود
		: [];

	$old      = hodima_core_login_settings();
	$clean    = hodima_core_login_sanitize( $posted );
	$raw_logo = is_string( $posted['logo_url'] ?? null ) ? trim( $posted['logo_url'] ) : '';

	// آدرس نادرست جای لوگوی کارکرده را خالی نکند؛ فقط خالی‌گذاشتن عمدی لوگو را برمی‌دارد
	$logo_rejected = '' !== $raw_logo && '' === $clean['logo_url'];
	if ( $logo_rejected ) {
		$clean['logo_url'] = $old['logo_url'];
	}

	update_option( HODIMA_CORE_LOGIN_OPTION, $clean, false );

	if ( $logo_rejected ) {
		hodima_admin_flash( 'تنظیمات ذخیره شد، ولی <strong>آدرس لوگو پذیرفته نشد</strong>؛ باید با https شروع شود و به png، jpg، webp یا svg ختم شود. لوگوی قبلی نگه داشته شد.', 'warning' );
	} else {
		hodima_admin_flash( 'تنظیمات ورود و امنیت ذخیره شد.' );
	}

	wp_safe_redirect( admin_url( 'admin.php?page=' . HODIMA_CORE_LOGIN_PAGE ) );
	exit;
} );

/* =====================================================================
 * صفحه
 * ===================================================================== */

function hodima_core_login_render_page(): void {

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$settings = hodima_core_login_settings();
	$has_logo = '' !== $settings['logo_url'];
	$forced   = defined( 'HODIMA_CORE_LOGIN_OFF' ) && HODIMA_CORE_LOGIN_OFF;
	$look_on  = hodima_core_login_look_active();
	$generic  = (bool) apply_filters( 'hodima_core_login_generic_errors', true );
	$name     = (string) get_bloginfo( 'name', 'display' );
	?>
	<div class="wrap hd-wrap hd-login-page">
		<?php
		hodima_admin_header( [
			'title'       => 'ورود و امنیت',
			'description' => 'ظاهر و امنیت صفحه ورود پیشخوان (آدرس /wp-admin/).',
			'icon'        => 'dashicons-lock',
			'actions'     => '<a class="button" href="' . esc_url( wp_login_url() ) . '" target="_blank" rel="noopener">دیدن صفحه ورود</a>',
		] );
		?>

		<?php if ( $forced ) : ?>
			<div class="hd-callout hd-callout--warning" role="status">
				<p>ثابت اضطراری <code dir="ltr">HODIMA_CORE_LOGIN_OFF</code> در <code dir="ltr">wp-config.php</code> فعال است؛ ظاهر تازه صفحه ورود هر چه اینجا بنویسید خاموش می‌ماند.</p>
			</div>
		<?php endif; ?>

		<div class="hd-grid hd-grid--stats" role="list" aria-label="وضعیت">
			<div class="hd-stat" role="listitem">
				<span class="hd-stat__label">ظاهر صفحه ورود</span>
				<span class="hd-stat__value"><?php echo $look_on ? 'طرح هدیما' : 'پیش‌فرض وردپرس'; ?></span>
			</div>
			<div class="hd-stat" role="listitem">
				<span class="hd-stat__label">لوگوی صفحه ورود</span>
				<span class="hd-stat__value"><?php echo $has_logo ? 'آدرس واردشده' : 'حرف اول نام سایت'; ?></span>
			</div>
			<div class="hd-stat" role="listitem">
				<span class="hd-stat__label">پیام خطای ورود</span>
				<span class="hd-stat__value"><?php echo $generic ? 'یکسان (امن)' : 'پیش‌فرض وردپرس'; ?></span>
			</div>
		</div>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="hd-login-page__form">
			<input type="hidden" name="action" value="hodima_core_login_save">
			<?php wp_nonce_field( 'hodima_core_login_save' ); ?>

			<div class="hd-login-page__cols">
				<section class="hd-card">
					<div class="hd-card__head">
						<?php echo hodima_admin_icon( 'dashicons-format-image' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- خروجی hodima_admin_icon escape شده ?>
						<h2 class="hd-card__title">لوگوی صفحه ورود</h2>
						<p class="hd-card__desc">آدرس تصویر لوگو را بنویسید. خالی بگذارید تا حرف اول نام سایت نمایش داده شود.</p>
					</div>
					<div class="hd-fields">
						<div class="hd-field hd-field--wide">
							<label class="hd-field__label" for="hd-login-logo">آدرس لوگو</label>
							<input type="url" id="hd-login-logo" name="hodima_login[logo_url]" class="large-text" dir="ltr" value="<?php echo esc_attr( $settings['logo_url'] ); ?>" placeholder="https://example.com/wp-content/uploads/logo.png" autocomplete="off" data-hd-login-logo>
							<p class="hd-field__help">فقط آدرس https و فایل png، jpg، webp یا svg. پیشنهاد: ارتفاع حدود ۹۶ پیکسل با پس‌زمینه شفاف.</p>
						</div>
						<div class="hd-field">
							<label class="hd-field__label" for="hd-login-height">ارتفاع نمایش لوگو</label>
							<div class="hd-inline">
								<input type="number" id="hd-login-height" name="hodima_login[logo_height]" min="<?php echo (int) HODIMA_CORE_LOGIN_LOGO_MIN; ?>" max="<?php echo (int) HODIMA_CORE_LOGIN_LOGO_MAX; ?>" step="1" value="<?php echo (int) $settings['logo_height']; ?>" dir="ltr" class="small-text" data-hd-login-height>
								<span class="hd-muted">پیکسل</span>
							</div>
						</div>
					</div>
				</section>

				<aside class="hd-card hd-card--accent" aria-labelledby="hd-login-preview-title">
					<div class="hd-card__head">
						<?php echo hodima_admin_icon( 'dashicons-visibility' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- خروجی hodima_admin_icon escape شده ?>
						<h2 class="hd-card__title" id="hd-login-preview-title">آنچه در صفحه ورود دیده می‌شود</h2>
					</div>
					<div class="hd-login-preview" data-hd-login-preview data-letter="<?php echo esc_attr( hodima_core_login_letter() ); ?>">
						<?php if ( $has_logo ) : ?>
							<img class="hd-login-preview__logo" src="<?php echo esc_url( $settings['logo_url'] ); ?>" alt="" style="block-size:<?php echo (int) $settings['logo_height']; ?>px" data-hd-login-img>
						<?php endif; ?>
						<span class="hd-login-preview__letter" aria-hidden="true" data-hd-login-letter<?php echo $has_logo ? ' hidden' : ''; ?>><?php echo esc_html( hodima_core_login_letter() ); ?></span>
						<span class="hd-login-preview__text" data-hd-login-text<?php echo $has_logo ? ' hidden' : ''; ?>>
							<strong><?php echo esc_html( $name ); ?></strong>
							<span class="hd-muted">پیشخوان مدیریت</span>
						</span>
					</div>
					<p class="hd-field__help" data-hd-login-note aria-live="polite"><?php echo $has_logo ? 'اگر آدرس باز نشود، حرف اول نام سایت جایگزین می‌شود.' : 'هنوز آدرسی ننوشته‌اید؛ حرف اول نام سایت نمایش داده می‌شود.'; ?></p>
				</aside>
			</div>

			<div class="hd-actions">
				<label class="hd-toggle">
					<input type="checkbox" class="hd-switch" role="switch" name="hodima_login[enabled]" value="1" <?php checked( $settings['enabled'] ); ?>>
					<span>استفاده از طرح هدیما برای صفحه ورود <span class="hd-muted">(خاموش = صفحه ورود پیش‌فرض وردپرس)</span></span>
				</label>
				<button type="submit" class="button button-primary">ذخیره تغییرات</button>
			</div>
		</form>
	</div>
	<?php
}
