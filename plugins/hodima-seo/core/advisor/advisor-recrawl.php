<?php
/**
 * ماژول «دستار سئو» — بازخوانی از خود صفحه‌ها و نوار وضعیت خزش (SEO 3.4.9)
 * Path: core/advisor/advisor-recrawl.php
 *
 * چرا: پیام «فهرست تصویرها با خزش بعدی ساخته می‌شود؛ «خزش همین حالا» را بزنید» پیوندی به تب «سلامت سایت» بود که دکمه خزش
 * ندارد (دکمه در تب «خزش و داده» است)؛ ادمین به بن‌بست می‌رسید و بعد از شروع خزش هم نمی‌فهمید چه شد. حالا:
 *   • «بازخوانی همین صفحه»: فقط همان یک صفحه چند ثانیه‌ای از سایت خوانده می‌شود و نتیجه همان‌جا می‌آید؛
 *   • «بازخوانی این N صفحه» (کار کلی): خزش هدفمند فقط همین صفحه‌ها (hodima_seo_advisor_crawler_start_paths)؛
 *   • نوار وضعیت خزش بالای «کارها» و «سلامت سایت» (پیشرفت زنده با همان JS تب «خزش و داده»).
 * فقط می‌خواند و صفحه‌ها را از سایت دوباره می‌خواند؛ چیزی در سایت عوض نمی‌شود.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** نوار وضعیت خزش فقط روی این تب‌ها می‌آید. */
const HODIMA_SEO_ADVISOR_CRAWLBAR_TABS = [ 'overview', 'crawl' ];

/** پیام «خزش تمام شد» تا این مدت (ثانیه) بعد از پایان دور دیده می‌شود. */
const HODIMA_SEO_ADVISOR_CRAWLBAR_DONE_WINDOW = 15 * MINUTE_IN_SECONDS;

add_action( 'admin_post_hodima_advisor_recrawl', 'hodima_seo_advisor_handle_recrawl' );

/**
 * مسیر صفحه‌هایی که ایراد باز این کد را دارند (ایراد علامت‌خورده نه): همان صفحه‌هایی که کارت «کار کلی» می‌شمارد.
 *
 * @return list<string>
 */
function hodima_seo_advisor_recrawl_paths( string $code ): array {

	$out = [];

	foreach ( hodima_seo_advisor_crawl_issues() as $row ) {
		foreach ( (array) $row['issues'] as $issue ) {
			if ( $code === (string) $issue['code'] && null === hodima_seo_advisor_task_closed( (string) $row['path'], 'crawl_' . $code ) ) {
				$out[] = (string) $row['path'];
				break;
			}
		}
	}

	return $out;
}

/**
 * جمله نتیجه بعد از بازخوانی یک صفحه: ایراد مورد نظر هنوز هست یا نه (از داده تازه خزش).
 */
function hodima_seo_advisor_recrawl_result( string $path, string $code ): string {

	global $wpdb;

	$still = false;

	foreach ( hodima_seo_advisor_crawl_issues() as $row ) {
		if ( (string) $row['path'] === $path ) {
			$still = in_array( $code, array_column( (array) $row['issues'], 'code' ), true );
			break;
		}
	}

	$text = '<strong>بازخوانی صفحه:</strong> صفحه دوباره از سایت خوانده شد. ';

	if ( 'img_alt' === $code ) {
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT img_noalt, img_list FROM %i WHERE path = %s', hodima_seo_advisor_table( 'crawl_pages' ), $path ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$left = is_array( $row ) ? (int) $row['img_noalt'] : 0;
		$urls = array_filter( hodima_seo_advisor_img_list_decode( is_array( $row ) ? (string) $row['img_list'] : '' ), static fn( array $e ): bool => 'empty' !== $e['s'] && '' !== $e['u'] );

		if ( $left <= 0 ) {
			return $text . 'دیگر تصویر بدون alt ندارد.';
		}

		return $text . sprintf( '%s تصویر بدون alt پیدا شد', number_format_i18n( $left ) ) . ( $urls ? '؛ فهرستشان زیر همین کارت است.' : '، ولی آدرسی برایشان در HTML صفحه نبود (احتمالا با جاوااسکریپت ساخته می‌شوند).' );
	}

	return $text . ( $still ? 'این ایراد هنوز هست.' : 'این ایراد دیگر دیده نمی‌شود.' );
}

/**
 * کنش «بازخوانی»: scope=page (یک صفحه، همین لحظه) یا scope=code (همه صفحه‌های یک ایراد، خزش هدفمند در پس‌زمینه).
 * برمی‌گردد به همان صفحه‌ای که ادمین در آن بود (نه تب دیگر).
 */
function hodima_seo_advisor_handle_recrawl(): void {

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'اجازه این کار را ندارید.', 'دسترسی ندارید', [ 'response' => 403 ] );
	}

	check_admin_referer( 'hodima_advisor_recrawl' );

	$scope = isset( $_POST['scope'] ) ? sanitize_key( wp_unslash( (string) $_POST['scope'] ) ) : 'page'; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce بالا بررسی شد
	$scope = in_array( $scope, [ 'code', 'img' ], true ) ? $scope : 'page';
	$img   = isset( $_POST['img'] ) ? sanitize_key( wp_unslash( (string) $_POST['img'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce بالا بررسی شد
	$code  = isset( $_POST['code'] ) ? sanitize_key( wp_unslash( (string) $_POST['code'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce بالا بررسی شد
	$code  = isset( hodima_seo_advisor_issue_catalog()[ $code ] ) ? $code : '';
	$path  = isset( $_POST['path'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['path'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce بالا بررسی شد
	$back  = wp_get_referer();
	$back  = is_string( $back ) && '' !== $back ? $back : hodima_seo_advisor_page_url( [ 'tab' => 'overview' ] );

	if ( 'code' === $scope || 'img' === $scope ) {
		$paths = [];
		if ( 'img' === $scope ) { // صفحه‌های یک تصویر مشترک (کارت «تصویرهای مشترک»؛ SEO 3.4.9)
			foreach ( hodima_seo_advisor_image_groups() as $group ) {
				if ( hodima_seo_advisor_img_key( $group['url'] ) === $img ) {
					$paths = $group['pages'];
				}
			}
		} elseif ( '' !== $code ) {
			$paths = hodima_seo_advisor_recrawl_paths( $code );
		}

		if ( ! $paths ) {
			hodima_admin_flash( '<strong>بازخوانی:</strong> صفحه‌ای برای بازخوانی پیدا نشد.', 'error' );
		} elseif ( hodima_seo_advisor_crawler_start_paths( $paths ) ) {
			hodima_admin_flash(
				sprintf( '<strong>بازخوانی:</strong> %1$s صفحه در صف خزش رفت (%2$s). پیشرفت بالای همین صفحه دیده می‌شود؛ بسته هم باشد با کرون ادامه می‌یابد.', esc_html( number_format_i18n( count( $paths ) ) ), esc_html( hodima_seo_advisor_crawler_duration( count( $paths ) ) ) ),
				'success'
			);
		} else {
			hodima_admin_flash( '<strong>بازخوانی:</strong> خزش دیگری در جریان است؛ پیشرفتش بالای همین صفحه دیده می‌شود. بعد از تمام شدنش دوباره بزنید.', 'error' );
		}
	} elseif ( '' === $path || ! str_starts_with( $path, '/' ) || ! hodima_seo_advisor_crawler_recrawl( $path ) ) {
		hodima_admin_flash( '<strong>بازخوانی صفحه:</strong> صفحه خوانده نشد؛ یا هنوز در خزش نیست یا سایت جواب نداد. «خزش همین حالا» را در تب «خزش و داده» بزنید.', 'error' );
	} else {
		hodima_seo_advisor_tasks_sync_crawl( $path, '' === $code ? null : [ $code ] ); // کار «انجام دادم» این صفحه همین لحظه سنجیده می‌شود
		hodima_admin_flash( hodima_seo_advisor_recrawl_result( $path, $code ), 'success' );
	}

	wp_safe_redirect( 'page' === $scope && '' !== $path ? $back . '#' . hodima_seo_advisor_row_anchor( $path ) : $back );
	exit;
}

/**
 * فرم دکمه «بازخوانی» (بدون id تا شناسه nonce تکراری نشود؛ بیرون از هر فرم دیگر قرار بگیرد).
 *
 * @param array<string, string> $fields scope و path یا code
 */
function hodima_seo_advisor_recrawl_form( array $fields, string $label, string $note = '', bool $disabled = false, bool $solid = false ): string {

	$hidden = '<input type="hidden" name="action" value="hodima_advisor_recrawl"><input type="hidden" name="_wpnonce" value="' . esc_attr( wp_create_nonce( 'hodima_advisor_recrawl' ) ) . '">';

	foreach ( $fields as $name => $value ) {
		$hidden .= '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">';
	}

	return sprintf(
		'<form method="post" action="%1$s" class="hodima-advisor__recrawl">%2$s<button type="submit" class="%6$s"%3$s><span class="dashicons dashicons-update" aria-hidden="true"></span> %4$s</button>%5$s</form>',
		esc_url( admin_url( 'admin-post.php' ) ),
		$hidden,
		$disabled ? ' disabled' : '',
		esc_html( $label ),
		'' !== $note ? '<span class="hodima-advisor__recrawl-note">' . esc_html( $note ) . '</span>' : '',
		$solid ? 'hds-btn hodima-advisor__act' : 'hds-btn hds-btn--outline hodima-advisor__act'
	);
}

/**
 * دکمه «خزش همین حالا» برای تب‌هایی که خزش را نشان می‌دهند (همان کنش تب «خزش و داده»؛ ادمین در همان تب می‌ماند).
 * دوری در جریان است: دکمه خاموش.
 */
function hodima_seo_advisor_crawl_now_form( string $tab ): string {

	$running = 'running' === hodima_seo_advisor_crawler_state()['status'];

	return sprintf(
		'<form method="post" action="%1$s" class="hodima-advisor__recrawl"><input type="hidden" name="action" value="hodima_advisor_crawler_now"><input type="hidden" name="_wpnonce" value="%2$s"><input type="hidden" name="tab" value="%3$s"><button type="submit" class="hds-btn hds-btn--outline hodima-advisor__act"%4$s><span class="dashicons dashicons-controls-play" aria-hidden="true"></span> %5$s</button></form>',
		esc_url( admin_url( 'admin-post.php' ) ),
		esc_attr( wp_create_nonce( 'hodima_advisor_crawler_now' ) ),
		esc_attr( $tab ),
		$running ? ' disabled' : '',
		esc_html( $running ? 'خزش در جریان است' : 'خزش همین حالا' )
	);
}

/**
 * نوار وضعیت خزش زیر تب‌ها (فقط «کارها» و «سلامت سایت»): وقتی خزش در جریان است پیشرفت زنده و زمان تقریبی (همان JS تب
 * «خزش و داده»: data-hdsa-crawl-live)، و تا ۱۵ دقیقه بعد از پایان دور «خزش تمام شد». وقتی خزشی نیست هیچ‌چیز نمی‌آید.
 */
function hodima_seo_advisor_render_crawlbar( string $tab ): void {

	if ( ! in_array( $tab, HODIMA_SEO_ADVISOR_CRAWLBAR_TABS, true ) ) {
		return;
	}

	$state  = hodima_seo_advisor_crawler_state();
	$status = hodima_seo_advisor_crawler_status();
	$n      = static fn( int|float $value ): string => number_format_i18n( $value );

	[ $level, , $info, $running ] = hodima_seo_advisor_crawler_status_view();

	if ( $running || 'paused' === $status['state'] ) {
		$percent = hodima_seo_advisor_crawler_percent( $status );
		$live    = $running ? ' data-hdsa-crawl-live="' . esc_attr( wp_create_nonce( 'hodima_advisor_crawler_step' ) ) . '" data-hdsa-ajax="' . esc_url( admin_url( 'admin-ajax.php' ) ) . '"' : '';
		?>
		<section class="hodima-advisor__crawlbar" data-level="<?php echo esc_attr( 'warn' === $level ? 'warn' : 'info' ); ?>" aria-label="وضعیت خزش"<?php echo $live; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ویژگی‌های امن‌شده بالا ?>>
			<span class="hodima-advisor__crawlbar-icon dashicons dashicons-networking" aria-hidden="true"></span>
			<div class="hodima-advisor__crawlbar-main">
				<div class="hodima-advisor__crawlbar-top">
					<span role="status"><b><?php echo esc_html( $running ? 'خزنده در حال خواندن سایت است' : 'خزنده مکث کرده است' ); ?></b> · <span data-hdsa-crawl-text><?php echo esc_html( $info ); ?></span></span>
					<b data-hdsa-job-pc><?php echo esc_html( $n( $percent ) ); ?>٪</b>
				</div>
				<div class="hodima-advisor__job-bar" role="progressbar" aria-label="پیشرفت خزش" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr( (string) $percent ); ?>" data-hdsa-crawl-bar>
					<i style="inline-size: <?php echo esc_attr( (string) $percent ); ?>%"></i>
				</div>
			</div>
			<?php if ( $state['manual'] ) : ?>
				<span class="hodima-advisor__crawlbar-note">می‌توانید صفحه را ببندید؛ خزش با کرون ادامه می‌یابد.</span>
			<?php endif; ?>
			<a class="hodima-advisor__crawlbar-link" href="<?php echo esc_url( hodima_seo_advisor_page_url( [ 'tab' => 'jobs' ] ) . '#hodima-advisor-cron' ); ?>">دیدن جزئیات</a>
		</section>
		<?php
		return;
	}

	if ( 'idle' === $state['status'] && $state['finished'] > 0 && time() - $state['finished'] <= HODIMA_SEO_ADVISOR_CRAWLBAR_DONE_WINDOW ) {
		?>
		<section class="hodima-advisor__crawlbar" data-level="ok" aria-label="وضعیت خزش">
			<span class="hodima-advisor__crawlbar-icon dashicons dashicons-yes-alt" aria-hidden="true"></span>
			<span class="hodima-advisor__crawlbar-text" role="status"><b>خزش تمام شد</b> · <?php echo esc_html( sprintf( 'نتیجه %s صفحه به‌روز است.', $n( $state['pages'] ) ) ); ?></span>
			<button type="button" class="hds-btn hds-btn--outline hodima-advisor__crawlbar-close" data-hdsa-crawlbar-close>بستن</button>
		</section>
		<?php
	}
}
