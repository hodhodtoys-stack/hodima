<?php
/**
 * ماژول «گوگل دیسکاور» — تب «تنظیمات» صفحه گوگل دیسکاور
 * Path: core/discover/discover-settings.php
 *
 * SEO 2.1.8: اتصال به سرچ کنسول (از تب «آمار» به اینجا آمد)، بررسی‌ها و فرصت‌ها
 * (حداقل کلمه، تازگی، عبارت‌های طعمه کلیک، آستانه‌ها؛ discover-options.php)،
 * اعلان‌ها (هشدار و خلاصه هفتگی؛ discover-alerts.php) و برش‌های تصویر.
 * فقط پیشخوان.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/* =====================================================================
 * ذخیره
 * ===================================================================== */

add_action( 'admin_post_hodima_discover_settings', static function (): void {

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'دسترسی ندارید.', '', [ 'response' => 403 ] );
	}

	check_admin_referer( 'hodima_discover_settings' );

	$flash = static function ( string $message, string $type = 'success' ): void {
		if ( function_exists( 'hodima_admin_flash' ) ) {
			hodima_admin_flash( $message, $type );
		}
	};

	if ( isset( $_POST['reset'] ) ) {
		delete_option( HODIMA_SEO_DISCOVER_SETTINGS_OPTION );
		$flash( 'تنظیمات بررسی‌ها و اعلان‌ها به پیش‌فرض برگشت.' );
	} else {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- هر کلید در hodima_seo_discover_sanitize_options پاک‌سازی می‌شود
		$in    = isset( $_POST['discover'] ) && is_array( $_POST['discover'] ) ? wp_unslash( $_POST['discover'] ) : [];
		$lines = array_values( array_filter( array_map( 'trim', preg_split( '/[\r\n]+/', (string) ( $in['clickbait'] ?? '' ) ) ?: [] ) ) );
		$same  = array_map( 'hodima_seo_discover_text_norm', $lines ) === array_map( 'hodima_seo_discover_text_norm', HODIMA_SEO_DISCOVER_CLICKBAIT );

		$values = hodima_seo_discover_sanitize_options( [
			'min_words'           => $in['min_words'] ?? null,
			'stale_days'          => $in['stale_days'] ?? null,
			'clickbait'           => $same ? null : $lines, // همان پیش‌فرض = ذخیره نکن تا فهرست‌های تازه نسخه‌های بعد برسد
			'opp_min_impressions' => $in['opp_min_impressions'] ?? null,
			'opp_ctr_ratio'       => $in['opp_ctr_ratio'] ?? null,
			'opp_drop_ratio'      => $in['opp_drop_ratio'] ?? null,
			'alerts'              => ! empty( $in['alerts'] ),
			'alert_drop'          => $in['alert_drop'] ?? null,
			'digest'              => ! empty( $in['digest'] ),
			'digest_email'        => $in['digest_email'] ?? '',
		] );

		update_option( HODIMA_SEO_DISCOVER_SETTINGS_OPTION, $values, false );
		$flash( 'تنظیمات ذخیره شد. آمادگی صفحه‌ها با تنظیمات تازه دوباره بررسی می‌شود.' );
	}

	// فهرست آمادگی عوض شد: همه ردیف‌های کش کهنه؛ زمان‌بندی خلاصه هفتگی تازه
	hodima_seo_discover_rows_reset();
	if ( function_exists( 'hodima_seo_discover_digest_schedule' ) ) {
		hodima_seo_discover_digest_schedule();
	}

	wp_safe_redirect( hodima_seo_discover_page_url( [ 'tab' => 'settings' ] ) );
	exit;
} );

/* =====================================================================
 * تب «تنظیمات»
 * ===================================================================== */

function hodima_seo_discover_render_settings(): void {
	hodima_seo_discover_render_connection();
	hodima_seo_discover_render_options_form();
	hodima_seo_discover_render_crops_card();
}

/** کارت «برش‌های تصویر دیسکاور» (تعداد صفحه‌های بی‌برش از ردیف‌های کش‌شده، بدون ساختن ردیف). */
function hodima_seo_discover_render_crops_card(): void {

	$rows   = hodima_seo_discover_report_rows( 0.0 )['rows'];
	$nocrop = count( array_filter( $rows, static fn( array $r ): bool => 'missing' === $r['crops'] ) );
	?>
	<section class="hd-card">
		<div class="hd-card__head">
			<?php echo hodima_admin_icon( 'dashicons-image-crop' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escape‌شده در تابع ?>
			<h2 class="hd-card__title">برش‌های تصویر دیسکاور</h2>
			<p class="hd-card__desc">
				برش‌های ۱۶:۹، ۴:۳ و ۱:۱ (عرض ۱۲۰۰) در og:image و اسکیما استفاده می‌شوند و هنگام ذخیره هر صفحه ساخته می‌شوند. صفحه‌هایی که از قبل بوده‌اند و دوباره ذخیره نشده‌اند برش ندارند.
				<?php if ( $nocrop ) : ?>
					<strong><?php echo esc_html( sprintf( '%s صفحه از صفحه‌های گزارش هنوز برش ندارد.', number_format_i18n( $nocrop ) ) ); ?></strong>
				<?php endif; ?>
			</p>
		</div>
		<div class="hodima-dr-batch" data-hodima-dr-batch="crops">
			<div class="hodima-dr-progress" role="progressbar" aria-label="ساخت برش‌ها" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" hidden><span></span></div>
			<p class="hodima-dr-batch__msg" data-hodima-dr-msg aria-live="polite"></p>
			<div class="hodima-dr-form__actions">
				<button type="button" class="button button-primary" data-hodima-dr-start><?php echo hodima_admin_icon( 'dashicons-image-crop' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escape‌شده در تابع ?> ساخت برش برای همه صفحه‌ها</button>
			</div>
		</div>
	</section>
	<?php
}

/** کارت «اتصال به سرچ کنسول»: کلید حساب سرویس و property (تا SEO 2.1.7 در تب «آمار»). */
function hodima_seo_discover_render_connection(): void {

	$email    = hodima_seo_discover_sc_email();
	$has_key  = '' !== hodima_seo_discover_sc_key_json();
	$source   = hodima_seo_discover_sc_key_source();
	$sites    = hodima_seo_discover_sc_sites();
	$settings = get_option( HODIMA_SEO_DISCOVER_SC_OPTION, [] );
	$raw_prop = is_array( $settings ) ? trim( (string) ( $settings['property'] ?? '' ) ) : '';
	$bad_prop = '' !== $raw_prop && is_wp_error( hodima_seo_discover_sc_clean_property( $raw_prop ) );
	$can_edit = current_user_can( 'manage_options' );
	$stats    = hodima_seo_discover_stats();

	if ( '' !== $stats['error'] && function_exists( 'hodima_admin_notice' ) ) {
		hodima_admin_notice( $stats['error'], 'warning' );
	}
	?>
	<section class="hd-card">
		<div class="hd-card__head">
			<?php echo hodima_admin_icon( 'dashicons-admin-network' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escape‌شده در تابع ?>
			<h2 class="hd-card__title">اتصال به سرچ کنسول</h2>
			<p class="hd-card__desc">با یک حساب سرویس گوگل (Service Account) و فقط دسترسی خواندنی. آمار روزی یک بار خودکار به‌روز می‌شود.</p>
		</div>
		<?php
		/*
		 * کلید حساب سرویس، به همان شکل ماژول Google Indexing (SEO 2.1.7): یک ردیف
		 * وضعیت (کلید فعال / تنظیم نشده، ایمیل، منبع) و دکمه‌های «جایگزینی کلید» /
		 * «افزودن کلید» که پنجره‌ای برای انتخاب فایل JSON (یا چسباندن متنش) باز
		 * می‌کند، و «حذف کلید» برای کلید جدای دیسکاور. تا 2.1.6 یک کادر بزرگ همیشه
		 * باز زیر کارت بود و کاربر جای عوض کردن حساب را پیدا نمی‌کرد.
		 */
		?>
		<h3 class="hodima-dr-subtitle">کلید Service Account</h3>
		<div class="hodima-dr-key">
			<?php if ( $has_key ) : ?>
				<span class="hd-pill hd-pill--ok"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>کلید فعال</span>
				<code class="hodima-dr-key__email" dir="ltr"><?php echo esc_html( $email ); ?></code>
				<span class="hd-pill"><?php echo esc_html( 'own' === $source ? 'کلید جدای دیسکاور' : 'از ماژول Google Indexing' ); ?></span>
			<?php else : ?>
				<span class="hd-pill hd-pill--error"><span class="dashicons dashicons-warning" aria-hidden="true"></span>کلیدی تنظیم نشده</span>
				<span class="hd-muted">بدون کلید آمار دیسکاور از سرچ کنسول گرفته نمی‌شود.</span>
			<?php endif; ?>
			<?php if ( $can_edit ) : ?>
				<span class="hodima-dr-key__actions">
					<button type="button" class="button<?php echo $has_key ? '' : ' button-primary'; ?>" data-hodima-dr-key-open aria-haspopup="dialog">
						<?php echo hodima_admin_icon( $has_key ? 'dashicons-update' : 'dashicons-plus-alt2' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escape‌شده در تابع ?>
						<?php echo esc_html( $has_key ? 'جایگزینی کلید' : 'افزودن کلید' ); ?>
					</button>
					<?php if ( 'own' === $source ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="hodima-dr-key__remove" data-hodima-dr-confirm="کلید جدای دیسکاور حذف شود؟ بعد از آن آمار با کلید ماژول Google Indexing گرفته می‌شود (اگر آن ماژول کلید داشته باشد).">
							<input type="hidden" name="action" value="hodima_discover_sc">
							<?php wp_nonce_field( 'hodima_discover_sc' ); ?>
							<button type="submit" class="button hodima-dr-danger" name="do" value="remove_key"><?php echo hodima_admin_icon( 'dashicons-trash' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escape‌شده در تابع ?> حذف کلید</button>
						</form>
					<?php endif; ?>
				</span>
			<?php endif; ?>
		</div>
		<p class="hd-muted hodima-dr-key__hint">
			<?php if ( 'own' === $source ) : ?>
				این کلید فقط برای خواندن آمار دیسکاور است و کلید ماژول Google Indexing را عوض نمی‌کند. «حذف کلید» = برگشت به کلید ماژول Google Indexing.
			<?php elseif ( 'indexing' === $source ) : ?>
				این کلید از تنظیمات ماژول Google Indexing خوانده می‌شود. برای استفاده از حساب دیگری فقط برای دیسکاور، «جایگزینی کلید» را بزنید؛ کلید ماژول Indexing دست نمی‌خورد.
			<?php else : ?>
				اگر ماژول Google Indexing کلید داشته باشد، همان خودکار استفاده می‌شود.
			<?php endif; ?>
			ایمیل حساب باید در سرچ کنسول ← تنظیمات ← کاربران و مجوزها، کاربر property سایت باشد.
		</p>

		<?php if ( $can_edit ) : ?>
			<dialog class="hd-dialog hodima-dr-key-dialog" data-hodima-dr-key-dialog aria-labelledby="hodima-dr-key-title">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="hodima_discover_sc">
					<input type="hidden" name="do" value="add_key">
					<?php wp_nonce_field( 'hodima_discover_sc' ); ?>
					<h2 class="hd-card__title" id="hodima-dr-key-title"><?php echo esc_html( $has_key ? 'جایگزینی کلید Service Account' : 'افزودن کلید Service Account' ); ?></h2>
					<p class="hd-muted">
						فایل JSON کلید را از Google Cloud Console بگیرید
						(<span dir="ltr">IAM &amp; Admin ← Service Accounts ← Keys ← Add key ← JSON</span>).
						ایمیل این حساب باید در سرچ کنسول کاربر property سایت باشد (دسترسی خواندن کافی است).
					</p>
					<div class="hodima-dr-key-dialog__pick">
						<button type="button" class="button button-primary" data-hodima-dr-key-pick><?php echo hodima_admin_icon( 'dashicons-upload' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escape‌شده در تابع ?> انتخاب فایل JSON</button>
						<input type="file" accept=".json,application/json" hidden data-hodima-dr-key-file>
						<span class="hodima-dr-key-dialog__file" dir="ltr" data-hodima-dr-key-name aria-live="polite"></span>
					</div>
					<details class="hodima-dr-key-dialog__paste">
						<summary>یا محتوای فایل را بچسبانید</summary>
						<textarea name="key_json" rows="6" dir="ltr" autocomplete="off" spellcheck="false" placeholder='{"type": "service_account", ...}' data-hodima-dr-key-text></textarea>
					</details>
					<p class="hodima-dr-key-dialog__preview" role="status" data-hodima-dr-key-preview></p>
					<div class="hodima-dr-form__actions">
						<button type="submit" class="button button-primary" data-hodima-dr-key-save disabled><?php echo hodima_admin_icon( 'dashicons-saved' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escape‌شده در تابع ?> ذخیره کلید</button>
						<button type="button" class="button" data-hodima-dr-key-cancel>انصراف</button>
					</div>
				</form>
			</dialog>
		<?php endif; ?>

		<?php if ( $can_edit ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="hodima-dr-form">
				<input type="hidden" name="action" value="hodima_discover_sc">
				<?php wp_nonce_field( 'hodima_discover_sc' ); ?>
				<?php if ( $bad_prop ) : ?>
					<p class="hd-callout hd-callout--warning"><?php echo esc_html( sprintf( 'property ذخیره‌شده قبلی («%s») آدرس سایت نبود و نادیده گرفته شد؛ تا property درست ذخیره نشود، خودکار امتحان می‌شود.', $raw_prop ) ); ?></p>
				<?php endif; ?>
				<div class="hd-field hd-field--wide">
					<label class="hd-field__label" for="hodima-discover-property">property در سرچ کنسول</label>
					<input type="text" id="hodima-discover-property" name="property" dir="ltr" list="hodima-discover-sites" value="<?php echo esc_attr( hodima_seo_discover_sc_property() ); ?>" placeholder="<?php echo esc_attr( implode( '  یا  ', array_slice( hodima_seo_discover_sc_candidates(), 0, 2 ) ) ); ?>">
					<?php if ( $sites ) : ?>
						<datalist id="hodima-discover-sites">
							<?php foreach ( $sites as $site ) : ?>
								<option value="<?php echo esc_attr( $site ); ?>"></option>
							<?php endforeach; ?>
						</datalist>
					<?php endif; ?>
					<p class="hd-field__help">آدرس سایت در سرچ کنسول، نه ایمیل: مثل <code dir="ltr"><?php echo esc_html( trailingslashit( home_url() ) ); ?></code> یا <code dir="ltr">sc-domain:<?php echo esc_html( (string) preg_replace( '/^www\./', '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) ); ?></code>. خالی = خودکار پیدا می‌شود.
						<?php if ( $sites ) : ?>
							<br><?php echo esc_html( 'propertyهایی که این حساب به آن‌ها دسترسی دارد: ' . implode( '، ', $sites ) ); ?>
						<?php endif; ?>
					</p>
				</div>
				<div class="hodima-dr-form__actions">
					<button type="submit" class="button button-primary" name="do" value="save"<?php disabled( ! $has_key ); ?>><?php echo hodima_admin_icon( 'dashicons-update' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escape‌شده در تابع ?> ذخیره و به‌روزرسانی آمار</button>
				</div>
			</form>
		<?php endif; ?>
	</section>
	<?php
}


/** فرم «بررسی‌ها و فرصت‌ها» و «اعلان‌ها» (discover-options.php). */
function hodima_seo_discover_render_options_form(): void {

	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$o        = hodima_seo_discover_options();
	$bait     = is_array( $o['clickbait'] ) ? $o['clickbait'] : HODIMA_SEO_DISCOVER_CLICKBAIT;
	$auto_imp = hodima_seo_discover_opp_min_impressions_auto( hodima_seo_discover_stats() );
	$num      = static fn( int $n ): string => number_format_i18n( $n );
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="hodima_discover_settings">
		<?php wp_nonce_field( 'hodima_discover_settings' ); ?>

		<section class="hd-card">
			<div class="hd-card__head">
				<?php echo hodima_admin_icon( 'dashicons-yes-alt' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escape‌شده در تابع ?>
				<h2 class="hd-card__title">بررسی‌ها و فرصت‌ها</h2>
				<p class="hd-card__desc">مقدارهایی که «آمادگی برای دیسکاور» و تب «فرصت‌ها» با آن‌ها حساب می‌شوند. بعد از ذخیره، آمادگی همه صفحه‌ها دوباره بررسی می‌شود.</p>
			</div>
			<div class="hd-fields">
				<div class="hd-field">
					<label class="hd-field__label" for="hodima-dset-words">کمترین تعداد کلمه مقاله</label>
					<input type="number" id="hodima-dset-words" name="discover[min_words]" min="50" max="5000" step="10" value="<?php echo (int) $o['min_words']; ?>">
					<p class="hd-field__help">مقاله کوتاه‌تر هشدار «عمق مطلب» می‌گیرد. پیش‌فرض <?php echo esc_html( $num( 300 ) ); ?>.</p>
				</div>
				<div class="hd-field">
					<label class="hd-field__label" for="hodima-dset-stale">مرز تازگی مقاله (روز)</label>
					<input type="number" id="hodima-dset-stale" name="discover[stale_days]" min="30" max="3650" value="<?php echo (int) $o['stale_days']; ?>">
					<p class="hd-field__help">مقاله‌ای که این مدت به‌روز نشده، هشدار «تازگی» می‌گیرد. پیش‌فرض <?php echo esc_html( $num( 365 ) ); ?>.</p>
				</div>
				<div class="hd-field">
					<label class="hd-field__label" for="hodima-dset-imp">کمترین نمایش برای «کم‌کلیک» و «افت»</label>
					<input type="number" id="hodima-dset-imp" name="discover[opp_min_impressions]" min="0" max="100000" value="<?php echo (int) $o['opp_min_impressions']; ?>">
					<p class="hd-field__help"><?php echo esc_html( sprintf( '۰ = خودکار به اندازه سایت (نصف میانه نمایش صفحه‌ها، بین ۲۰ و ۲۰۰؛ الان %s).', $num( $auto_imp ) ) ); ?></p>
				</div>
				<div class="hd-field">
					<label class="hd-field__label" for="hodima-dset-ctr">«کم‌کلیک» = نرخ کلیک کمتر از (٪ میانگین سایت)</label>
					<input type="number" id="hodima-dset-ctr" name="discover[opp_ctr_ratio]" min="10" max="100" value="<?php echo (int) $o['opp_ctr_ratio']; ?>">
					<p class="hd-field__help">پیش‌فرض ۶۰.</p>
				</div>
				<div class="hd-field">
					<label class="hd-field__label" for="hodima-dset-drop">«افت نمایش» = نمایش کمتر از (٪ ۲۸ روز قبل)</label>
					<input type="number" id="hodima-dset-drop" name="discover[opp_drop_ratio]" min="10" max="90" value="<?php echo (int) $o['opp_drop_ratio']; ?>">
					<p class="hd-field__help">پیش‌فرض ۵۰.</p>
				</div>
				<div class="hd-field hd-field--wide">
					<label class="hd-field__label" for="hodima-dset-bait">عبارت‌های طعمه کلیک</label>
					<textarea id="hodima-dset-bait" name="discover[clickbait]" rows="8"><?php echo esc_textarea( implode( "\n", $bait ) ); ?></textarea>
					<p class="hd-field__help">هر عبارت در یک خط؛ فقط کلمه کامل حساب می‌شود («راز» در «شیراز» نه). «*» در آخر یعنی ادامه کلمه آزاد است («باورتان نمی*» = نمی‌شود/نمی‌کنید). نیم‌فاصله و ی/ک عربی فرقی ندارند. <?php echo is_array( $o['clickbait'] ) ? 'فهرست ویرایش‌شده است؛ «بازگرداندن پیش‌فرض» فهرست اصلی را برمی‌گرداند.' : 'فهرست پیش‌فرض.'; ?></p>
				</div>
			</div>
		</section>

		<section class="hd-card">
			<div class="hd-card__head">
				<?php echo hodima_admin_icon( 'dashicons-bell' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escape‌شده در تابع ?>
				<h2 class="hd-card__title">اعلان‌ها</h2>
				<p class="hd-card__desc">بعد از هر به‌روزرسانی آمار (روزی یک بار) افت ناگهانی نمایش و صفحه‌هایی که تازه وارد دیسکاور شده‌اند بررسی می‌شوند.</p>
			</div>
			<div class="hd-fields">
				<div class="hd-field">
					<label class="hodima-dr-check"><input type="checkbox" name="discover[alerts]" value="1" <?php checked( $o['alerts'] ); ?>> هشدار در پیشخوان (پیشخوان وردپرس و صفحه گوگل دیسکاور)</label>
				</div>
				<div class="hd-field">
					<label class="hd-field__label" for="hodima-dset-alert">هشدار افت وقتی نمایش ۷ روز آخر کمتر شده از (٪)</label>
					<input type="number" id="hodima-dset-alert" name="discover[alert_drop]" min="10" max="90" value="<?php echo (int) $o['alert_drop']; ?>">
					<p class="hd-field__help">نسبت به ۷ روز پیش از آن. پیش‌فرض ۴۰.</p>
				</div>
				<div class="hd-field">
					<label class="hodima-dr-check"><input type="checkbox" name="discover[digest]" value="1" <?php checked( $o['digest'] ); ?>> خلاصه هفتگی با ایمیل</label>
					<p class="hd-field__help">هر هفته: نمایش و کلیک هفته نسبت به هفته قبل، صفحه‌های پرنمایش، صفحه‌های تازه در دیسکاور و هشدارها.</p>
				</div>
				<div class="hd-field">
					<label class="hd-field__label" for="hodima-dset-email">ایمیل خلاصه هفتگی</label>
					<input type="email" id="hodima-dset-email" name="discover[digest_email]" dir="ltr" value="<?php echo esc_attr( $o['digest_email'] ); ?>" placeholder="<?php echo esc_attr( (string) get_option( 'admin_email' ) ); ?>">
					<p class="hd-field__help">خالی = ایمیل مدیر سایت (تنظیمات ← عمومی).</p>
				</div>
			</div>
			<div class="hodima-dr-form__actions">
				<button type="submit" class="button button-primary"><?php echo hodima_admin_icon( 'dashicons-saved' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escape‌شده در تابع ?> ذخیره تنظیمات</button>
				<button type="submit" class="button" name="reset" value="1" formnovalidate data-hodima-dr-confirm-button="همه تنظیمات بررسی‌ها و اعلان‌ها به پیش‌فرض برگردد؟"><?php echo hodima_admin_icon( 'dashicons-image-rotate' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escape‌شده در تابع ?> بازگرداندن پیش‌فرض</button>
			</div>
		</section>
	</form>
	<?php
}
