<?php
/**
 * لینک‌های مرتبط دستی — صفحه «ابزارهای هدیما ← لینک‌های مرتبط»
 * Path: plugins/hodima-seo/inc/manual_related_link/includes/admin-page.php
 *
 * تب «تنظیمات»: تعداد، عنوان و نمایش خودکار هر گروه، سطح عنوان، noindex،
 *               ثبت کلیک در Google Analytics.
 * تب «گزارش سلامت»: همه لینک‌های مرتبط سایت با مشکلاتشان (مقصد حذف‌شده،
 *               پیش‌نویس، noindex، ریدایرکت، لینک به خود…)، پرلینک‌ترین مقصدها
 *               و لینک‌های «ویدئوهای مرتبط» ماژول ویدیو (hodima-media).
 */

declare(strict_types=1);

namespace Hodima\RelatedLinks;

defined( 'ABSPATH' ) || exit;

final class AdminPage {

	public const SLUG  = 'hodima-related-links';
	private const CAP  = 'manage_options';
	private const SAVE = 'hodima_rl_settings_save';

	/** سقف ردیف‌های بررسی‌شده در گزارش (برای سایت‌های خیلی بزرگ). */
	private const REPORT_LIMIT = 3000;

	public static function init(): void {
		add_action( 'admin_menu', [ self::class, 'menu' ], 20 );
		add_action( 'admin_init', [ self::class, 'handle_save' ] );
	}

	public static function menu(): void {

		$parent = function_exists( 'hodima_admin_menu_parent' ) ? hodima_admin_menu_parent() : '';
		$title  = 'لینک‌های مرتبط';

		if ( '' !== $parent ) {
			add_submenu_page( $parent, $title, $title, self::CAP, self::SLUG, [ self::class, 'render' ] );
			return;
		}

		add_menu_page( $title, $title, self::CAP, self::SLUG, [ self::class, 'render' ], 'dashicons-admin-links', 31 );
	}

	private static function url( string $tab = '' ): string {
		return add_query_arg( array_filter( [ 'page' => self::SLUG, 'tab' => $tab ] ), admin_url( 'admin.php' ) );
	}

	public static function handle_save(): void {

		if ( ! isset( $_POST['hodima_rl_settings_submit'] ) ) {
			return;
		}
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'دسترسی ندارید.', 403 );
		}
		check_admin_referer( self::SAVE );

		$input = isset( $_POST['hodima_rl'] ) && is_array( $_POST['hodima_rl'] ) ? wp_unslash( $_POST['hodima_rl'] ) : [];
		Store::save_settings( (array) $input );

		if ( function_exists( 'hodima_admin_flash' ) ) {
			hodima_admin_flash( 'تنظیمات لینک‌های مرتبط ذخیره شد.' );
		}
		wp_safe_redirect( self::url() );
		exit;
	}

	public static function render(): void {

		if ( ! current_user_can( self::CAP ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط انتخاب تب
		$tab = 'report' === sanitize_key( (string) wp_unslash( $_GET['tab'] ?? '' ) ) ? 'report' : 'settings';

		echo '<div class="wrap hd-wrap hodima-rl-page">';
		hodima_admin_header( [
			'title'       => 'لینک‌های مرتبط',
			'description' => 'پیشنهاد خرید (در سایت: «محصولات مکمل») و مقاله پیشنهادی که در ویرایشگر هر نوشته، محصول و دسته انتخاب می‌شوند.',
			'icon'        => 'dashicons-admin-links',
			'current'     => $tab,
			'tabs'        => [
				'settings' => [ 'label' => 'تنظیمات', 'url' => self::url(), 'icon' => 'dashicons-admin-settings' ],
				'report'   => [ 'label' => 'گزارش سلامت', 'url' => self::url( 'report' ), 'icon' => 'dashicons-heart' ],
			],
		] );

		'report' === $tab ? self::render_report() : self::render_settings();

		echo '</div>';
	}

	/* =====================================================================
	 * تنظیمات
	 * ===================================================================== */

	private static function render_settings(): void {

		$s = Store::settings();
		?>
		<form method="post" action="<?php echo esc_url( self::url() ); ?>" class="hd-body">
			<?php wp_nonce_field( self::SAVE ); ?>

			<?php foreach ( Group::cases() as $group ) : $g = $s['groups'][ $group->value ]; $f = 'hodima_rl[groups][' . $group->value . ']'; ?>
				<section class="hd-card">
					<header class="hd-card__head">
						<?php echo hodima_admin_icon( $group->icon() ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<h2 class="hd-card__title"><?php echo esc_html( $group->label() ); ?></h2>
					</header>
					<p class="hd-card__desc">
						شورت‌کد: <code>[<?php echo esc_html( $group->shortcode() ); ?>]</code>
						<?php echo Group::Article === $group ? ' — بدون هیچ عنوانی بالای آن؛ کارت کوچک، داخل لینک نیمی عکس و نیمی برچسب و عنوان مقاله.' : ' — کارت‌های تصویری با عنوان بالای کادر.'; ?>
					</p>
					<div class="hd-fields">
						<div class="hd-field">
							<label class="hd-field__label" for="hodima-rl-count-<?php echo esc_attr( $group->value ); ?>">تعداد لینک نمایشی</label>
							<input type="number" min="1" max="<?php echo esc_attr( (string) Store::MAX_SLOTS ); ?>" id="hodima-rl-count-<?php echo esc_attr( $group->value ); ?>" name="<?php echo esc_attr( $f ); ?>[count]" value="<?php echo esc_attr( (string) $g['count'] ); ?>">
							<p class="hd-field__help">ویرایشگر به همین تعداد خانه جستجو نشان می‌دهد. اگر تعداد را کم کنید، لینک‌های اضافه پاک نمی‌شوند و به‌عنوان «ذخیره» می‌مانند.</p>
						</div>
						<?php if ( $group->has_title() ) : ?>
							<div class="hd-field">
								<label class="hd-field__label" for="hodima-rl-title-<?php echo esc_attr( $group->value ); ?>">عنوان بالای کادر</label>
								<input type="text" id="hodima-rl-title-<?php echo esc_attr( $group->value ); ?>" name="<?php echo esc_attr( $f ); ?>[title]" value="<?php echo esc_attr( (string) $g['title'] ); ?>" placeholder="خالی = بدون عنوان">
							</div>
						<?php endif; ?>
						<div class="hd-field hd-field--wide">
							<label class="hd-toggle">
								<input type="checkbox" class="hd-switch" role="switch" name="<?php echo esc_attr( $f ); ?>[auto]" value="1" <?php checked( $g['auto'] ); ?>>
								<span>نمایش خودکار (بدون شورت‌کد)</span>
							</label>
							<p class="hd-field__help">
								<?php
								echo Group::Article === $group
									? 'در نوشته‌ها و محصولات بعد از پاراگرافی که پایین‌تر تعیین می‌کنید، و در دسته‌ها انتهای توضیح دسته.'
									: 'انتهای محتوای نوشته/محصول/برگه و انتهای توضیح دسته. اگر شورت‌کد در متن باشد، همان‌جا یک بار نمایش داده می‌شود.';
								?>
							</p>
						</div>
						<?php if ( Group::Article === $group ) : ?>
							<div class="hd-field">
								<label class="hd-field__label" for="hodima-rl-label">برچسب داخل کارت (بالای عنوان مقاله)</label>
								<input type="text" id="hodima-rl-label" name="hodima_rl[article_label]" value="<?php echo esc_attr( (string) $s['article_label'] ); ?>" placeholder="خالی = بدون برچسب">
								<p class="hd-field__help">جزو متن لینک حساب نمی‌شود؛ گوگل فقط عنوان مقاله را متن لینک می‌بیند.</p>
							</div>
							<div class="hd-field">
								<label class="hd-field__label" for="hodima-rl-paragraph">جایگاه در نمایش خودکار: بعد از پاراگراف</label>
								<input type="number" min="0" max="50" id="hodima-rl-paragraph" name="hodima_rl[article_paragraph]" value="<?php echo esc_attr( (string) $s['article_paragraph'] ); ?>">
								<p class="hd-field__help">۰ یعنی انتهای محتوا؛ اگر متن پاراگراف کمتری داشته باشد هم انتها می‌آید.</p>
							</div>
						<?php endif; ?>
					</div>
				</section>
			<?php endforeach; ?>

			<section class="hd-card">
				<header class="hd-card__head">
					<?php echo hodima_admin_icon( 'dashicons-admin-generic' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<h2 class="hd-card__title">عمومی</h2>
				</header>
				<div class="hd-fields">
					<div class="hd-field">
						<label class="hd-field__label" for="hodima-rl-heading">تگ عنوان کادرها</label>
						<select id="hodima-rl-heading" name="hodima_rl[heading]">
							<?php foreach ( Store::HEADINGS as $tag ) : ?>
								<option value="<?php echo esc_attr( $tag ); ?>" <?php selected( $s['heading'], $tag ); ?>><?php echo esc_html( 'p' === $tag ? 'متن ساده (بدون سرتیتر)' : strtoupper( $tag ) ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="hd-field__help">اگر کادر وسط متن مقاله است و سرتیترهای متن H2 هستند، H3 ترتیب سرتیترها را درست نگه می‌دارد. در هر شورت‌کد هم با <code>heading="h2"</code> قابل تغییر است.</p>
					</div>
					<div class="hd-field hd-field--wide">
						<label class="hd-toggle">
							<input type="checkbox" class="hd-switch" role="switch" name="hodima_rl[hide_noindex]" value="1" <?php checked( $s['hide_noindex'] ); ?>>
							<span>لینک به صفحه‌های noindex نمایش داده نشود</span>
						</label>
						<p class="hd-field__help">لینک داخلی به صفحه‌ای که نباید ایندکس شود، بودجه خزش را هدر می‌دهد.</p>
					</div>
					<div class="hd-field hd-field--wide">
						<label class="hd-toggle">
							<input type="checkbox" class="hd-switch" role="switch" name="hodima_rl[track_clicks]" value="1" <?php checked( $s['track_clicks'] ); ?>>
							<span>ثبت کلیک در Google Analytics / Tag Manager</span>
						</label>
						<p class="hd-field__help">رویداد <code>select_content</code> با <code>content_type=related_link</code> و گروه و جایگاه کارت (از gtag، یا رویداد <code>hodima_related_click</code> در dataLayer). فقط وقتی کد Analytics روی سایت نصب باشد اثر دارد.</p>
					</div>
				</div>
			</section>

			<section class="hd-card">
				<header class="hd-card__head">
					<?php echo hodima_admin_icon( 'dashicons-editor-help' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<h2 class="hd-card__title">راهنمای شورت‌کدها</h2>
				</header>
				<ul class="hd-list">
					<li><code>[manual_related_products]</code> پیشنهاد خرید (عنوان کادر در سایت: «محصولات مکمل»، از همین صفحه قابل تغییر) · <code>[hodima_related_article]</code> مقاله پیشنهادی</li>
					<li>«دسته‌بندی‌های مرتبط» حذف شد (لینک دسته‌ها را خوشه موضوعی می‌سازد). اگر <code>[hodima_related_categories]</code> جایی در متن مانده باشد چیزی نمایش نمی‌دهد.</li>
					<li>هر شورت‌کد مستقل است: هر کدام را هر جای متن (یا توضیح دسته) بگذارید، فقط کادر خودش همان‌جا نمایش داده می‌شود. لازم نیست کنار هم باشند و لازم نیست هر دو را بگذارید.</li>
					<li>ویژگی‌ها: <code>title="…"</code> عنوان دلخواه (<code>title=""</code> بدون عنوان)، <code>heading="h2"</code>، و <code>id="123" type="term"</code> برای نمایش لینک‌های صفحه یا دسته‌ای دیگر.</li>
					<li><code>[manual_related_products]</code> که از قبل در محتوای سایت است، حالا همان کادر «پیشنهاد خرید» است و لازم نیست پاک شود. <code>[manual_related_links]</code> و <code>[hodima_complementary_products]</code> هم همین کادر را نشان می‌دهند.</li>
					<li>در توضیح دسته‌ها هم شورت‌کد کار می‌کند (قبلا به شکل متن خام دیده می‌شد).</li>
				</ul>
			</section>

			<div class="hd-actions">
				<?php submit_button( 'ذخیره تنظیمات', 'primary', 'hodima_rl_settings_submit', false ); ?>
			</div>
		</form>
		<?php
	}

	/* =====================================================================
	 * گزارش سلامت
	 * ===================================================================== */

	private static function render_report(): void {

		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$post_ids = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s LIMIT %d", Store::META, self::REPORT_LIMIT ) ) );
		$term_ids = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT term_id FROM {$wpdb->termmeta} WHERE meta_key = %s LIMIT %d", Store::META, self::REPORT_LIMIT ) ) );
		$legacy   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key IN (%s, %s)", Store::LEGACY_META, '_internal_link_1_url' ) )
			+ (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->termmeta} WHERE meta_key IN (%s, %s)", Store::LEGACY_META, '_internal_link_1_url' ) );
		// phpcs:enable

		if ( $post_ids ) {
			_prime_post_caches( $post_ids, false, true );
		}

		$sources  = [];
		foreach ( $post_ids as $id ) {
			$sources[] = [ $id, 'post' ];
		}
		foreach ( $term_ids as $id ) {
			$sources[] = [ $id, 'term' ];
		}

		$links    = 0;
		$problems = [];
		$unplaced = [];
		$inbound  = [];

		foreach ( $sources as [ $id, $context ] ) {
			foreach ( Group::cases() as $group ) {
				foreach ( Store::get( $id, $context )[ $group->value ] as $position => $item ) {
					++$links;
					$r = Store::resolve( $item, $id, $context );
					if ( $r['visible'] ) {
						$inbound[ $r['url'] ] ??= [ 'title' => (string) $r['title'], 'count' => 0 ];
						++$inbound[ $r['url'] ]['count'];
					}
					foreach ( array_diff( $r['problems'], [ 'no_image' ] ) as $code ) {
						$problems[] = [ $id, $context, $group, $position + 1, $r, $code ];
					}
				}
			}

			$placed = Front::placed_groups( Front::source_text( $id, $context ) );
			foreach ( Group::cases() as $group ) {
				if ( Store::get( $id, $context )[ $group->value ] && ! in_array( $group, $placed, true ) ) {
					$unplaced[] = [ $id, $context, $group ];
				}
			}
		}

		$hiding = count( array_filter( $problems, static fn( array $p ): bool => Store::is_hiding( $p[5] ) ) );
		uasort( $inbound, static fn( array $a, array $b ): int => $b['count'] <=> $a['count'] );
		?>
		<div class="hd-body">
			<div class="hd-grid hd-grid--stats">
				<div class="hd-stat"><span class="hd-stat__label">صفحه‌های دارای لینک مرتبط</span><span class="hd-stat__value"><?php echo esc_html( number_format_i18n( count( $sources ) ) ); ?></span></div>
				<div class="hd-stat"><span class="hd-stat__label">کل لینک‌ها</span><span class="hd-stat__value"><?php echo esc_html( number_format_i18n( $links ) ); ?></span></div>
				<div class="hd-stat"><span class="hd-stat__label">لینک‌های پنهان‌شده (مشکل جدی)</span><span class="hd-stat__value"><?php echo esc_html( number_format_i18n( $hiding ) ); ?></span></div>
				<div class="hd-stat"><span class="hd-stat__label">هشدارها</span><span class="hd-stat__value"><?php echo esc_html( number_format_i18n( count( $problems ) - $hiding ) ); ?></span></div>
				<div class="hd-stat"><span class="hd-stat__label">گروه‌های بدون شورت‌کد در متن</span><span class="hd-stat__value"><?php echo esc_html( number_format_i18n( count( $unplaced ) ) ); ?></span></div>
			</div>

			<?php if ( $legacy ) : ?>
				<div class="hd-callout hd-callout--warning">
					<?php echo esc_html( sprintf( '%s صفحه هنوز داده نسخه قبل را دارد؛ با باز شدن صفحه‌های پیشخوان کم‌کم (۲۵ مورد در هر بار) خودکار منتقل می‌شود.', number_format_i18n( $legacy ) ) ); ?>
				</div>
			<?php endif; ?>

			<section class="hd-card">
				<header class="hd-card__head">
					<?php echo hodima_admin_icon( 'dashicons-warning' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<h2 class="hd-card__title">مشکلات لینک‌ها</h2>
				</header>
				<?php if ( ! $problems ) : ?>
					<p class="hd-empty">هیچ مشکلی پیدا نشد.</p>
				<?php else : ?>
					<div class="hd-table-wrap">
						<table class="widefat striped hodima-rl-report">
							<thead><tr><th>صفحه</th><th>گروه</th><th>خانه</th><th>مقصد</th><th>مشکل</th></tr></thead>
							<tbody>
							<?php foreach ( $problems as [ $id, $context, $group, $position, $r, $code ] ) : ?>
								<tr>
									<td><?php self::source_link( $id, $context ); ?></td>
									<td><?php echo esc_html( $group->label() ); ?></td>
									<td><?php echo esc_html( number_format_i18n( $position ) ); ?></td>
									<td>
										<?php echo esc_html( '' !== (string) $r['title'] ? (string) $r['title'] : '—' ); ?>
										<?php if ( '' !== $r['url'] ) : ?><br><bdi dir="ltr" class="hd-muted"><?php echo esc_html( rawurldecode( (string) $r['url'] ) ); ?></bdi><?php endif; ?>
									</td>
									<td>
										<span class="hd-pill <?php echo Store::is_hiding( $code ) ? 'hd-pill--error' : 'hd-pill--warn'; ?>"><?php echo esc_html( Store::is_hiding( $code ) ? 'پنهان' : 'هشدار' ); ?></span>
										<?php echo esc_html( Store::problem_label( $code ) ); ?>
									</td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
			</section>

			<?php if ( $unplaced ) : ?>
				<section class="hd-card">
					<header class="hd-card__head">
						<?php echo hodima_admin_icon( 'dashicons-hidden' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
						<h2 class="hd-card__title">لینک‌هایی که جایی نمایش داده نمی‌شوند</h2>
					</header>
					<p class="hd-card__desc">این صفحه‌ها در این گروه لینک دارند ولی شورت‌کد گروه در متن (یا توضیح دسته) نیست و نمایش خودکارش خاموش است. شورت‌کد را هر جای متن که می‌خواهید بگذارید، یا نمایش خودکار را روشن کنید.</p>
					<div class="hd-table-wrap">
						<table class="widefat striped hodima-rl-report">
							<thead><tr><th>صفحه</th><th>گروه</th><th>شورت‌کد لازم</th></tr></thead>
							<tbody>
							<?php foreach ( $unplaced as [ $id, $context, $group ] ) : ?>
								<tr>
									<td><?php self::source_link( $id, $context ); ?></td>
									<td><?php echo esc_html( $group->label() ); ?></td>
									<td><code>[<?php echo esc_html( $group->shortcode() ); ?>]</code></td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</section>
			<?php endif; ?>

			<section class="hd-card">
				<header class="hd-card__head">
					<?php echo hodima_admin_icon( 'dashicons-chart-bar' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<h2 class="hd-card__title">پرلینک‌ترین مقصدها</h2>
				</header>
				<p class="hd-card__desc">صفحه‌هایی که از لینک‌های مرتبط بیشترین لینک داخلی را می‌گیرند. برای صفحه‌هایی که هیچ لینکی نمی‌گیرند، گزارش «محتواهای یتیم» خوشه‌بندی را هم ببینید.</p>
				<?php if ( ! $inbound ) : ?>
					<p class="hd-empty">هنوز لینکی ثبت نشده است.</p>
				<?php else : ?>
					<div class="hd-table-wrap">
						<table class="widefat striped hodima-rl-report">
							<thead><tr><th>مقصد</th><th>تعداد لینک ورودی</th></tr></thead>
							<tbody>
							<?php foreach ( array_slice( $inbound, 0, 20, true ) as $url => $row ) : ?>
								<tr>
									<td><a href="<?php echo esc_url( (string) $url ); ?>"><?php echo esc_html( $row['title'] ); ?></a></td>
									<td><?php echo esc_html( number_format_i18n( $row['count'] ) ); ?></td>
								</tr>
							<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				<?php endif; ?>
			</section>

			<?php self::render_video_report(); ?>
		</div>
		<?php
	}

	/**
	 * لینک‌های «ویدئوهای مرتبط» ماژول ویدیو (hodima-media): همان بررسی
	 * آدرس (ریدایرکت، ۴۰۴، پیش‌نویس) تا همه لینک‌سازی دستی سایت در یک گزارش
	 * باشد. داده آن ماژول فقط خوانده می‌شود.
	 */
	private static function render_video_report(): void {

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s LIMIT %d", '_hod_related_links', self::REPORT_LIMIT ) );
		if ( ! $rows ) {
			return;
		}

		$issues = [];
		foreach ( $rows as $row ) {
			foreach ( (array) maybe_unserialize( $row->meta_value ) as $position => $link ) {
				$url = trim( (string) ( is_array( $link ) ? ( $link['url'] ?? '' ) : '' ) );
				if ( '' === $url ) {
					continue;
				}
				$item = Store::normalize_item( [ 'kind' => 'url', 'url' => $url, 'title' => (string) ( $link['title'] ?? '' ) ] );
				$r    = $item ? Store::resolve( $item, (int) $row->post_id, 'post' ) : null;
				foreach ( $r ? array_diff( $r['problems'], [ 'no_image', 'external' ] ) : [] as $code ) {
					$issues[] = [ (int) $row->post_id, (int) $position + 1, $url, $code ];
				}
			}
		}
		?>
		<section class="hd-card">
			<header class="hd-card__head">
				<?php echo hodima_admin_icon( 'dashicons-video-alt3' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<h2 class="hd-card__title">ویدئوهای مرتبط (ماژول ویدیو)</h2>
			</header>
			<?php if ( ! $issues ) : ?>
				<p class="hd-empty"><?php echo esc_html( sprintf( 'لینک‌های مرتبط %s ویدیو بررسی شد؛ مشکلی نیست.', number_format_i18n( count( $rows ) ) ) ); ?></p>
			<?php else : ?>
				<div class="hd-table-wrap">
					<table class="widefat striped hodima-rl-report">
						<thead><tr><th>ویدیو</th><th>خانه</th><th>آدرس</th><th>مشکل</th></tr></thead>
						<tbody>
						<?php foreach ( $issues as [ $id, $position, $url, $code ] ) : ?>
							<tr>
								<td><?php self::source_link( $id, 'post' ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $position ) ); ?></td>
								<td><bdi dir="ltr"><?php echo esc_html( rawurldecode( $url ) ); ?></bdi></td>
								<td><?php echo esc_html( Store::problem_label( $code ) ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>
		</section>
		<?php
	}

	private static function source_link( int $id, string $context ): void {

		if ( 'term' === $context ) {
			$term = get_term( $id );
			$name = $term instanceof \WP_Term ? $term->name : '#' . $id;
			$edit = $term instanceof \WP_Term ? get_edit_term_link( $term ) : '';
		} else {
			$name = get_the_title( $id ) ?: '#' . $id;
			$edit = get_edit_post_link( $id );
		}

		if ( $edit ) {
			printf( '<a href="%s">%s</a>', esc_url( $edit ), esc_html( wp_strip_all_tags( $name ) ) );
		} else {
			echo esc_html( wp_strip_all_tags( $name ) );
		}
	}
}
