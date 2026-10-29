<?php
/**
 * ماژول «دستار سئو» — تب «ایرادهای فنی»
 * Path: core/advisor/advisor-crawl-view.php
 *
 * داده از advisor-crawl.php. کالبد کیت SEO: ۴ کارت آمار ← کارت «فایل‌های خزش» (یک کنش اصلی:
 * خواندن فایل‌های خزش) ← کارت «ایرادهای صفحه‌ها» (چیپ‌های فیلتر + جستجو + جدول مرتب با نمایش گوگل).
 * فقط می‌خواند و دستور می‌دهد؛ چیزی را در سایت عوض نمی‌کند. (طرح تأییدشده کاربر ۴ آبان ۱۴۰۵)
 * SEO 3.0.2: هر ایراد تیک و هر ردیف «انجام شد / رد شد» دارد (advisor-marks.php)؛ ایراد علامت‌خورده پنهان می‌شود.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** ردیف هر صفحه در فهرست ایرادها. */
const HODIMA_SEO_ADVISOR_CRAWL_PER_PAGE = 25;

add_action( 'admin_post_hodima_advisor_crawl', 'hodima_seo_advisor_handle_crawl' );

function hodima_seo_advisor_handle_crawl(): void {

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'اجازه این کار را ندارید.', 'دسترسی ندارید', [ 'response' => 403 ] );
	}

	check_admin_referer( 'hodima_advisor_crawl' );

	$result = hodima_seo_advisor_crawl_import();

	if ( is_wp_error( $result ) ) {
		hodima_admin_flash( '<strong>ایرادهای فنی:</strong> ' . esc_html( $result->get_error_message() ) . ' داده قبلی همان‌طور ماند.', 'error' );
	} else {
		hodima_admin_flash(
			sprintf(
				'<strong>ایرادهای فنی:</strong> %1$s صفحه و %2$s لینک داخلی خوانده شد.',
				esc_html( number_format_i18n( $result['pages'] ) ),
				esc_html( number_format_i18n( $result['links'] ) )
			),
			'success'
		);
	}

	wp_safe_redirect( hodima_seo_advisor_page_url( [ 'tab' => 'crawl' ] ) );
	exit;
}

add_action( 'admin_post_hodima_advisor_crawler_request', 'hodima_seo_advisor_handle_crawler_request' );
add_action( 'admin_post_hodima_advisor_crawler_settings', 'hodima_seo_advisor_handle_crawler_settings' );

/** دکمه «خزش تازه در شب آینده»: دور تازه را درخواست می‌کند؛ اجرا در پنجره شبانه با کرون. */
function hodima_seo_advisor_handle_crawler_request(): void {

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'اجازه این کار را ندارید.', 'دسترسی ندارید', [ 'response' => 403 ] );
	}

	check_admin_referer( 'hodima_advisor_crawler_request' );
	hodima_seo_advisor_crawler_request();
	hodima_admin_flash( '<strong>خزش خودکار:</strong> دور تازه در اولین نوبت پنجره شبانه شروع می‌شود.', 'success' );

	wp_safe_redirect( hodima_seo_advisor_page_url( [ 'tab' => 'crawl' ] ) );
	exit;
}

/** ذخیره تنظیم خزنده: روشن/خاموش، ساعت شروع و پایان، فاصله دورها. */
function hodima_seo_advisor_handle_crawler_settings(): void {

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'اجازه این کار را ندارید.', 'دسترسی ندارید', [ 'response' => 403 ] );
	}

	check_admin_referer( 'hodima_advisor_crawler_settings' );

	$hour     = static fn( string $key, int $fallback ): int => max( 0, min( 23, isset( $_POST[ $key ] ) ? absint( wp_unslash( $_POST[ $key ] ) ) : $fallback ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce بالا بررسی شد
	$interval = isset( $_POST['interval'] ) ? absint( wp_unslash( $_POST['interval'] ) ) : 7; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce بالا بررسی شد

	update_option(
		HODIMA_SEO_ADVISOR_CRAWLER_SETTINGS,
		[
			'enabled'  => ! empty( $_POST['enabled'] ), // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce بالا بررسی شد
			'start'    => $hour( 'start', 2 ),
			'end'      => $hour( 'end', 8 ),
			'interval' => in_array( $interval, HODIMA_SEO_ADVISOR_CRAWLER_INTERVALS, true ) ? $interval : 7,
		],
		false
	);
	hodima_admin_flash( '<strong>خزش خودکار:</strong> تنظیم ذخیره شد.', 'success' );

	wp_safe_redirect( hodima_seo_advisor_page_url( [ 'tab' => 'crawl' ] ) );
	exit;
}

/**
 * نام و آدرس ویرایش صفحه یک ردیف خزش: از ردیف داده گوگل (شناسایی‌شده هنگام دریافت)، وگرنه با روتر. خالی = ناشناخته.
 *
 * @return array{title: string, edit: string}
 */
function hodima_seo_advisor_crawl_row_target( string $path ): array {

	$row   = hodima_seo_advisor_page( $path );
	$class = null !== $row && (int) $row['object_id'] > 0 ? [ 'kind' => (string) $row['kind'], 'id' => (int) $row['object_id'] ] : hodima_seo_advisor_classify( $path );

	if ( $class['id'] <= 0 || '' === $class['kind'] ) {
		return [ 'title' => null !== $row ? (string) $row['title'] : '', 'edit' => '' ];
	}

	$title = hodima_seo_advisor_object_title( $class['kind'], $class['id'] );
	$edit  = taxonomy_exists( $class['kind'] ) ? (string) get_edit_term_link( $class['id'], $class['kind'] ) : ( current_user_can( 'edit_post', $class['id'] ) ? (string) get_edit_post_link( $class['id'], 'raw' ) : '' );

	return [ 'title' => $title, 'edit' => $edit ];
}

/**
 * ایرادهای علامت‌خورده (منتظر نتیجه، برطرف، رد شد) پنهان؛ صفحه‌ای که ایراد بازی ندارد کنار می‌رود. با «نمایش
 * علامت‌خورده‌ها» همه می‌مانند. هر ایراد کار ثبت‌شده‌اش را همراه دارد (نشان وضعیت).
 *
 * @param list<array<string, mixed>> $issues
 * @return array{0: list<array<string, mixed>>, 1: int} ردیف‌ها و شمار ایرادهای پنهان‌شده
 */
function hodima_seo_advisor_crawl_open_issues( array $issues, bool $show ): array {

	$hidden = 0;
	$out    = [];

	foreach ( $issues as $row ) {
		$open = [];
		foreach ( $row['issues'] as $issue ) {
			$rule          = 'crawl_' . $issue['code'];
			$issue['task'] = hodima_seo_advisor_task( (string) $row['path'], $rule, '' );
			$closed        = null !== $issue['task'] && in_array( hodima_seo_advisor_task_state( $issue['task'] ), HODIMA_SEO_ADVISOR_TASKS_CLOSED, true );
			if ( $closed && ! $show ) {
				++$hidden;
				continue;
			}
			$issue['closed'] = $closed;
			$open[]          = $issue;
		}
		if ( $open ) {
			$out[] = [ 'issues' => $open ] + $row;
		}
	}

	return [ $out, $hidden ];
}

/** نشان وضعیت هر ایراد (رنگ کیت: ok سبز، bad قرمز، warn کهربایی، info آبی). */
function hodima_seo_advisor_crawl_pill_class( string $level ): string {
	return 'hds-pill' . ( [ 'bad' => ' hds-pill--error', 'warn' => ' hds-pill--warn', 'info' => ' hds-pill--info' ][ $level ] ?? '' );
}

/**
 * یک ردیف وضعیت فایل: نقطه، نام، توضیح و نشان.
 *
 * @param array<string, mixed> $file وضعیت فایل
 * @param array<string, mixed> $saved آنچه آخرین بار برای این فایل ذخیره شد (rows، error)
 */
function hodima_seo_advisor_crawl_file_row( array $file, array $saved, bool $stale ): void {

	$state = (string) $file['state'];
	$rows  = (int) ( $saved['rows'] ?? 0 );
	$error = (string) ( $saved['error'] ?? '' );
	$unit  = 'pages' === $file['kind'] ? 'صفحه' : 'لینک';
	$level = 'bad';
	$label = 'نیست';
	$info  = 'هنوز در پوشه نیست';

	if ( 'uploading' === $state ) {
		[ $level, $label, $info ] = [ 'warn', 'در حال آپلود', 'همین الان تغییر کرده؛ چند دقیقه صبر کنید' ];
	} elseif ( 'ready' === $state ) {
		$info = sprintf( '%1$s پیش · %2$s', human_time_diff( (int) $file['mtime'] ), size_format( (int) $file['size'] ) ) . ( $rows > 0 ? sprintf( ' · %1$s %2$s', number_format_i18n( $rows ), $unit ) : '' );
		[ $level, $label ] = '' !== $error ? [ 'bad', 'رد شد' ] : ( $rows > 0 ? ( $stale ? [ 'warn', 'قدیمی' ] : [ 'ok', 'خوانده شد' ] ) : [ 'info', 'آماده خواندن' ] );
		if ( '' !== $error ) {
			$info = $error;
		}
	} elseif ( 'links' === $file['kind'] ) {
		[ $level, $label, $info ] = [ 'info', 'اختیاری', 'بدون آن شمار لینک از متن نمایش داده نمی‌شود' ];
	}
	?>
	<li class="hodima-advisor__file">
		<span class="hodima-advisor__dot hodima-advisor__dot--<?php echo esc_attr( $level ); ?>" aria-hidden="true"></span>
		<span class="hodima-advisor__file-body">
			<code dir="ltr"><?php echo esc_html( (string) $file['name'] ); ?></code>
			<small><?php echo esc_html( $info ); ?></small>
		</span>
		<span class="<?php echo esc_attr( hodima_seo_advisor_crawl_pill_class( $level ) ); ?>"><?php echo esc_html( $label ); ?></span>
	</li>
	<?php
}

/**
 * کارت «خزش خودکار شبانه»: وضعیت، یک کنش اصلی (درخواست خزش تازه) و تنظیم زمان داخل «جمع‌شونده».
 */
function hodima_seo_advisor_render_crawler_card(): void {

	$settings = hodima_seo_advisor_crawler_settings();
	$status   = hodima_seo_advisor_crawler_status();
	$n        = static fn( int|float $value ): string => number_format_i18n( $value );
	$hour     = static fn( int $h ): string => $n( $h ) . ':۰۰';
	$window   = $settings['start'] === $settings['end'] ? 'همه ساعت‌ها' : sprintf( 'هر شب %1$s تا %2$s', $hour( $settings['start'] ), $hour( $settings['end'] ) );

	[ $level, $label, $info ] = match ( $status['state'] ) {
		'off'     => [ 'warn', 'خاموش', 'خزش خودکار خاموش است؛ داده تازه نمی‌آید.' ],
		'paused'  => [ 'warn', 'مکث موقت', sprintf( 'سایت کند پاسخ داد؛ خزنده تا %s ساکت است تا سایت سبک بماند.', wp_date( 'H:i', $status['pause_until'] ) ) ],
		'running' => [ 'info', 'در حال خزش', sprintf( '%1$s صفحه خوانده شد، %2$s در صف%3$s.', $n( $status['done'] ), $n( $status['queued'] + $status['seeding'] ), hodima_seo_advisor_crawler_in_window() ? '' : '؛ ادامه در پنجره شبانه بعد' ) ],
		default   => $status['requested']
			? [ 'info', 'درخواست شد', 'دور تازه در اولین نوبت پنجره شبانه شروع می‌شود.' ]
			: ( $status['finished'] > 0
				? [ 'ok', 'آماده', sprintf( 'آخرین دور %1$s پیش تمام شد (%2$s صفحه)؛ دور بعد خودکار.', human_time_diff( $status['finished'] ), $n( $status['pages'] ) ) ]
				: [ 'info', 'منتظر اولین دور', 'اولین دور در پنجره شبانه شروع می‌شود.' ] ),
	};
	$busy = 'running' === $status['state'] || $status['requested'] || 'off' === $status['state'];

	hodima_seo_ui_card_open( 'خزش خودکار شبانه', 'دستار خودش صفحه‌های سایت را می‌خواند (جای Screaming Frog)؛ آهسته و فقط شب‌ها تا سایت کند نشود.' );
	?>
	<div class="hodima-advisor__crawl-files">
		<ul class="hodima-advisor__files" aria-label="وضعیت خزش خودکار">
			<li class="hodima-advisor__file">
				<span class="hodima-advisor__dot hodima-advisor__dot--<?php echo esc_attr( $level ); ?>" aria-hidden="true"></span>
				<span class="hodima-advisor__file-body">
					<strong>وضعیت</strong>
					<small><?php echo esc_html( $info ); ?></small>
				</span>
				<span class="<?php echo esc_attr( hodima_seo_advisor_crawl_pill_class( $level ) ); ?>"><?php echo esc_html( $label ); ?></span>
			</li>
			<li class="hodima-advisor__file">
				<span class="hodima-advisor__dot hodima-advisor__dot--info" aria-hidden="true"></span>
				<span class="hodima-advisor__file-body">
					<strong>زمان خزش</strong>
					<small><?php echo esc_html( sprintf( '%1$s به وقت سایت، هر %2$s روز یک دور', $window, $n( $settings['interval'] ) ) ); ?></small>
				</span>
			</li>
		</ul>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="hodima_advisor_crawler_request">
			<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( 'hodima_advisor_crawler_request' ) ); ?>"><?php // بدون id: چند فرم در یک صفحه، شناسه تکراری نسازد ?>
			<button type="submit" class="hds-btn"<?php disabled( $busy ); ?>>
				<span class="dashicons dashicons-update" aria-hidden="true"></span> خزش تازه در شب آینده
			</button>
		</form>
	</div>
	<details class="hodima-advisor__crawler-more">
		<summary>تنظیم زمان و فاصله</summary>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="hodima-advisor__crawler-form">
			<input type="hidden" name="action" value="hodima_advisor_crawler_settings">
			<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( 'hodima_advisor_crawler_settings' ) ); ?>"><?php // بدون id: چند فرم در یک صفحه، شناسه تکراری نسازد ?>
			<label class="hodima-advisor__confirm">
				<input type="checkbox" name="enabled" value="1"<?php checked( $settings['enabled'] ); ?>>
				<span>خزش خودکار روشن باشد</span>
			</label>
			<div class="hodima-advisor__crawler-fields">
				<?php foreach ( [ 'start' => 'از ساعت', 'end' => 'تا ساعت' ] as $name => $text ) : ?>
					<label class="hodima-advisor__crawler-field" for="hodima-crawler-<?php echo esc_attr( $name ); ?>">
						<span><?php echo esc_html( $text ); ?></span>
						<select id="hodima-crawler-<?php echo esc_attr( $name ); ?>" name="<?php echo esc_attr( $name ); ?>" class="hds-input">
							<?php for ( $h = 0; $h < 24; $h++ ) : ?>
								<option value="<?php echo esc_attr( (string) $h ); ?>"<?php selected( $settings[ $name ], $h ); ?>><?php echo esc_html( $hour( $h ) ); ?></option>
							<?php endfor; ?>
						</select>
					</label>
				<?php endforeach; ?>
				<label class="hodima-advisor__crawler-field" for="hodima-crawler-interval">
					<span>هر چند روز یک دور</span>
					<select id="hodima-crawler-interval" name="interval" class="hds-input">
						<?php foreach ( HODIMA_SEO_ADVISOR_CRAWLER_INTERVALS as $days ) : ?>
							<option value="<?php echo esc_attr( (string) $days ); ?>"<?php selected( $settings['interval'], $days ); ?>><?php echo esc_html( $n( $days ) . ' روز' ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			</div>
			<p class="hodima-advisor__setting-help">
				ساعت شروع و پایان برابر یعنی همه ساعت‌ها. خزنده یک درخواست در هر لحظه می‌زند و اگر سایت کند شد خودش نیم ساعت می‌ایستد. کرون وردپرس فقط وقتی کسی به سایت سر بزند اجرا می‌شود؛ اگر شب‌ها بازدید کم است در هاست یک کرون واقعی هر ۵ دقیقه بگذارید: <bdi class="hds-code" dir="ltr"><?php echo esc_html( '*/5 * * * * curl -s ' . home_url( '/wp-cron.php?doing_wp_cron' ) . ' >/dev/null' ); ?></bdi>
			</p>
			<button type="submit" class="hds-btn">ذخیره</button>
		</form>
	</details>
	<?php
	hodima_seo_ui_card_close();
}

/**
 * محتوای تب «ایرادهای فنی».
 */
function hodima_seo_advisor_render_crawl(): void {

	$status = hodima_seo_advisor_crawl_status();
	$has    = $status['imported'] > 0 && $status['pages'] > 0;
	$n      = static fn( int|float $value ): string => number_format_i18n( $value );
	$stale  = $has && time() - $status['imported'] > HODIMA_SEO_ADVISOR_CRAWL_STALE_DAYS * DAY_IN_SECONDS;
	$issues = $has ? hodima_seo_advisor_crawl_issues() : [];
	// آمار و چیپ‌ها با همان فهرستی که دیده می‌شود (ایرادهای علامت‌خورده بیرون)
	[ $issues, $hidden ] = hodima_seo_advisor_crawl_open_issues( $issues, hodima_seo_advisor_show_marked() );
	$sum    = $has ? hodima_seo_advisor_crawl_summary( $issues ) : [ 'crawled' => 0, 'with_issues' => 0, 'big' => 0, 'low_links' => 0, 'groups' => [] ];

	hodima_seo_ui_stats( [
		[ 'label' => 'صفحه خزش‌شده', 'value' => $has ? esc_html( $n( $sum['crawled'] ) ) : '—', 'sub' => $has ? esc_html( sprintf( 'خزش %s پیش', human_time_diff( $status['imported'] ) ) ) : '', 'empty' => ! $has ],
		[ 'label' => 'صفحه دارای ایراد', 'value' => $has ? esc_html( $n( $sum['with_issues'] ) ) : '—', 'sub' => $has ? esc_html( sprintf( 'از %s صفحه', $n( $sum['crawled'] ) ) ) : '', 'empty' => ! $has ],
		[ 'label' => 'ایراد در صفحه‌های پرنمایش', 'value' => $has ? esc_html( $n( $sum['big'] ) ) : '—', 'sub' => $has ? esc_html( sprintf( 'بیش از %s نمایش', $n( HODIMA_SEO_ADVISOR_CRAWL_BIG_IMPRESSIONS ) ) ) : '', 'empty' => ! $has ],
		[ 'label' => 'لینک ورودی کم', 'value' => $has ? esc_html( $n( $sum['low_links'] ) ) : '—', 'sub' => $has ? esc_html( sprintf( '%s لینک یا کمتر', $n( HODIMA_SEO_ADVISOR_CRAWL_LOW_INLINKS ) ) ) : '', 'empty' => ! $has ],
	], 'خلاصه ایرادهای فنی' );

	hodima_seo_advisor_render_crawler_card();

	$prepared = hodima_seo_advisor_crawl_prepare_dir();
	$files    = [];
	foreach ( array_keys( HODIMA_SEO_ADVISOR_CRAWL_FILES ) as $kind ) {
		$files[ $kind ] = hodima_seo_advisor_crawl_file_state( $kind );
	}
	$saved = $status['files'];
	$label = hodima_seo_advisor_crawl_dir_label();
	$ready = 'missing' !== $files['pages']['state'];

	hodima_seo_ui_card_open( 'فایل‌های خزش', 'خروجی Screaming Frog را با همین دو نام در پوشه زیر بگذارید و «خواندن فایل‌های خزش» را بزنید. فایل‌ها از وب بسته‌اند و در به‌روزرسانی افزونه پاک نمی‌شوند.' );
	?>
	<div class="hodima-advisor__crawl-files">
		<ul class="hodima-advisor__files" aria-label="وضعیت فایل‌ها">
			<?php foreach ( $files as $kind => $file ) : ?>
				<?php hodima_seo_advisor_crawl_file_row( $file, (array) ( $saved[ $kind ] ?? [] ), $stale ); ?>
			<?php endforeach; ?>
		</ul>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="hodima_advisor_crawl">
			<?php wp_nonce_field( 'hodima_advisor_crawl' ); ?>
			<button type="submit" class="hds-btn"<?php disabled( ! $ready || ! $prepared ); ?>>
				<span class="dashicons dashicons-update" aria-hidden="true"></span> خواندن فایل‌های خزش
			</button>
		</form>
	</div>
	<div class="hodima-advisor__folder">
		<bdi id="hodima-advisor-crawl-path" dir="ltr"><?php echo esc_html( $label ); ?></bdi>
		<button type="button" class="hds-btn hds-btn--outline" data-hdsa-copy="hodima-advisor-crawl-path">کپی مسیر</button>
	</div>
	<?php
	if ( ! $prepared ) {
		echo '<p class="hodima-advisor__advice">پوشه ساخته نشد؛ دسترسی نوشتن پوشه uploads را بررسی کنید.</p>';
	}
	if ( true === hodima_seo_advisor_crawl_web_exposed() ) {
		echo '<p class="hodima-advisor__advice"><strong>فایل‌های خزش از اینترنت قابل دانلودند:</strong> این سرور (احتمالا nginx) فایل <code>.htaccess</code> را اجرا نمی‌کند. از هاست بخواهید دسترسی به پوشه <bdi dir="ltr">uploads/hodima-advisor/</bdi> را ببندد (مثلا <bdi dir="ltr">location ~* /uploads/hodima-advisor/ { deny all; }</bdi>)، یا فایل‌ها را بعد از «خواندن» از پوشه پاک کنید.</p>';
	}
	if ( $stale ) {
		echo '<p class="hodima-advisor__advice"><strong>خزش قدیمی است:</strong> بیش از ' . esc_html( $n( HODIMA_SEO_ADVISOR_CRAWL_STALE_DAYS ) ) . ' روز از آخرین خزش گذشته. یک خزش تازه بگیرید و فایل‌ها را جایگزین کنید.</p>';
	}
	hodima_seo_ui_card_close();

	if ( ! $has ) {
		hodima_seo_ui_card_open( 'ایرادهای صفحه‌ها' );
		hodima_seo_ui_empty( 'هنوز خزشی انجام نشده', 'خزش خودکار شبانه (کارت بالا) اولین دور را در پنجره شبانه انجام می‌دهد و اینجا پر می‌شود؛ یا خروجی Screaming Frog را بخوانید: ۱) در Screaming Frog سایت را خزش کنید. ۲) Internal ← HTML را با نام internal_html.csv و Bulk Export ← Links ← All Inlinks را با نام all_inlinks.csv خروجی بگیرید. ۳) هر دو را در پوشه بالا بگذارید و «خواندن فایل‌های خزش» را بزنید.' );
		hodima_seo_ui_card_close();
		return;
	}

	hodima_seo_advisor_render_crawl_list( $issues, $sum, $hidden );
}

/**
 * کارت «ایرادهای صفحه‌ها»: چیپ‌های فیلتر، جستجو، جدول و صفحه‌بندی.
 *
 * @param list<array<string, mixed>> $issues
 * @param array<string, mixed>       $sum
 * @param int                        $hidden شمار ایرادهای علامت‌خورده پنهان
 */
function hodima_seo_advisor_render_crawl_list( array $issues, array $sum, int $hidden = 0 ): void {

	$n      = static fn( int|float $value ): string => number_format_i18n( $value );
	$groups = array_keys( HODIMA_SEO_ADVISOR_CRAWL_GROUPS );
	$group  = isset( $_GET['cf'] ) ? sanitize_key( (string) $_GET['cf'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط فیلتر نمایش
	$group  = in_array( $group, $groups, true ) ? $group : '';
	$q      = isset( $_GET['q'] ) ? trim( sanitize_text_field( wp_unslash( (string) $_GET['q'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$paged  = max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	$rows = array_values( array_filter(
		$issues,
		static fn( array $row ): bool => ( '' === $group || array_intersect( array_column( $row['issues'], 'code' ), HODIMA_SEO_ADVISOR_CRAWL_GROUPS[ $group ] ) )
			&& ( '' === $q || str_contains( mb_strtolower( rawurldecode( (string) $row['path'] ) ), mb_strtolower( $q ) ) )
	) );

	$views = [
		''         => [ 'همه', $sum['with_issues'] ],
		'meta'     => [ 'متا و خلاصه', $sum['groups']['meta'] ?? 0 ],
		'title'    => [ 'عنوان یا H1', $sum['groups']['title'] ?? 0 ],
		'dup'      => [ 'تکراری', $sum['groups']['dup'] ?? 0 ],
		'slow'     => [ 'کند', $sum['groups']['slow'] ?? 0 ],
		'redirect' => [ 'ریدایرکت یا noindex', $sum['groups']['redirect'] ?? 0 ],
		'linkfix'  => [ 'لینک اشتباه', $sum['groups']['linkfix'] ?? 0 ],
		'content'  => [ 'تصویر و محتوا', $sum['groups']['content'] ?? 0 ],
		'tech'     => [ 'ساختار و نقشه سایت', $sum['groups']['tech'] ?? 0 ],
		'links'    => [ 'لینک ورودی کم', $sum['groups']['links'] ?? 0 ],
		'orphan'   => [ 'بدون لینک ورودی', $sum['groups']['orphan'] ?? 0 ],
	];

	$marked = hodima_seo_advisor_show_marked() ? 'all' : '';
	$url    = static fn( array $args ): string => hodima_seo_advisor_page_url( [ 'tab' => 'crawl', 'tasks' => $marked ] + $args );

	hodima_seo_ui_card_open( 'ایرادهای صفحه‌ها', 'مرتب بر اساس نمایش در گوگل. بعد از رفع «انجام دادم» را بزنید؛ عنوان، متا، تیتر و خطای ۴۰۴/۵۰۰ همان لحظه دوباره سنجیده می‌شوند و بقیه با خزش بعدی.' );
	?>
	<form method="get" class="hodima-advisor__toolbar">
		<input type="hidden" name="page" value="<?php echo esc_attr( HODIMA_SEO_ADVISOR_PAGE ); ?>">
		<input type="hidden" name="tab" value="crawl">
		<?php if ( '' !== $group ) : ?>
			<input type="hidden" name="cf" value="<?php echo esc_attr( $group ); ?>">
		<?php endif; ?>
		<?php if ( '' !== $marked ) : ?>
			<input type="hidden" name="tasks" value="all">
		<?php endif; ?>
		<nav class="hodima-advisor__views" aria-label="نوع ایراد">
			<?php foreach ( $views as $key => [ $text, $count ] ) : ?>
				<?php if ( '' !== $key && $count < 1 ) { continue; } ?>
				<a class="hodima-advisor__view" href="<?php echo esc_url( $url( [ 'cf' => $key, 'q' => $q ] ) ); ?>"<?php echo $key === $group ? ' aria-current="page"' : ''; ?>>
					<?php echo esc_html( $text ); ?> <span class="hodima-advisor__count"><?php echo esc_html( $n( $count ) ); ?></span>
				</a>
			<?php endforeach; ?>
		</nav>
		<div class="hodima-advisor__search">
			<label class="screen-reader-text" for="hodima-advisor-crawl-q">جستجو در آدرس صفحه</label>
			<input type="search" id="hodima-advisor-crawl-q" name="q" class="hds-input" value="<?php echo esc_attr( $q ); ?>" placeholder="جستجوی آدرس…">
			<button type="submit" class="hodima-advisor__go" aria-label="اعمال جستجو"><span class="dashicons dashicons-search" aria-hidden="true"></span></button>
		</div>
	</form>
	<?php

	if ( ! $rows ) {
		$toggle = hodima_seo_advisor_marked_toggle( $hidden, [ 'tab' => 'crawl', 'cf' => $group, 'q' => $q ], 'ایراد' );
		if ( ! $issues && $hidden > 0 ) {
			hodima_seo_ui_empty( 'کار بازی نمانده', sprintf( 'همه ایرادهای باقی‌مانده را «انجام دادم» یا «لازم نیست» علامت زده‌اید. %s', $toggle ) );
		} else {
			hodima_seo_ui_empty( $issues ? 'اینجا چیزی نیست' : 'ایرادی پیدا نشد', $issues ? 'با این فیلتر صفحه‌ای ایراد ندارد. فیلتر را عوض کنید.' : 'همه صفحه‌های خزش‌شده از دید این بررسی‌ها سالم‌اند.' );
		}
		hodima_seo_ui_card_close();
		return;
	}

	$pages = (int) ceil( count( $rows ) / HODIMA_SEO_ADVISOR_CRAWL_PER_PAGE );
	$paged = min( $paged, $pages );
	$slice = array_slice( $rows, ( $paged - 1 ) * HODIMA_SEO_ADVISOR_CRAWL_PER_PAGE, HODIMA_SEO_ADVISOR_CRAWL_PER_PAGE );
	?>
	<div class="hds-table-wrap" tabindex="0" role="region" aria-label="ایرادهای صفحه‌ها">
		<table class="widefat striped hodima-advisor__terms hodima-advisor__crawl-table">
			<thead><tr><th scope="col">صفحه</th><th scope="col">نمایش</th><th scope="col">رتبه</th><th scope="col">ایراد و دستور</th><th scope="col">کنش</th></tr></thead>
			<tbody>
				<?php foreach ( $slice as $row ) : ?>
					<?php
					$target = hodima_seo_advisor_crawl_row_target( (string) $row['path'] );
					$form   = hodima_seo_advisor_mark_id();
					$open   = array_filter( $row['issues'], static fn( array $issue ): bool => ! $issue['closed'] );
					?>
					<tr>
						<th scope="row">
							<?php if ( '' !== $target['title'] ) : ?>
								<strong class="hodima-advisor__crawl-name"><?php echo esc_html( $target['title'] ); ?></strong>
							<?php endif; ?>
							<?php echo hodima_seo_advisor_url_copy( (string) $row['path'], false ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML امن‌شده در hodima_seo_advisor_url_copy ?>
							<?php $live = hodima_seo_advisor_live_time( (string) $row['path'] ); ?>
							<?php if ( $live > 0 ) : ?>
								<small class="hodima-advisor__live"><?php echo esc_html( sprintf( 'عنوان، متا و تیترها %s پیش از خود سایت بررسی شد', human_time_diff( $live ) ) ); ?></small>
							<?php endif; ?>
						</th>
						<td data-label="نمایش"><?php echo $row['impressions'] > 0 ? esc_html( $n( $row['impressions'] ) ) : '—'; ?></td>
						<td data-label="رتبه"><?php echo $row['position'] > 0 ? esc_html( number_format_i18n( $row['position'], 1 ) ) : '—'; ?></td>
						<td class="hodima-advisor__crawl-issues">
							<?php // هر ایراد یک سطر: تیک + نشان + دستور خودش (آدرس مقصد بیرون از برچسب: پیوند و دکمه کپی داخل label نامعتبر است) ?>
							<?php foreach ( $row['issues'] as $issue ) : ?>
								<?php
								$info  = hodima_seo_advisor_crawl_issue_info( (string) $issue['code'] );
								$has_u = '' !== (string) ( $issue['url'] ?? '' );
								$text  = sprintf(
									'<span class="%1$s">%2$s</span> <span>%3$s</span>%4$s',
									esc_attr( hodima_seo_advisor_crawl_pill_class( $info['level'] ) ),
									esc_html( $info['label'] ),
									esc_html( trim( ( '' !== (string) $issue['detail'] && ! $has_u ? $issue['detail'] . '. ' : '' ) . $info['fix'] . ( $has_u ? ' مقصد ریدایرکت:' : '' ) ) ),
									null !== $issue['task'] ? ' ' . hodima_seo_advisor_task_badge( $issue['task'] ) : ''
								);
								?>
								<div class="hodima-advisor__crawl-fix<?php echo $issue['closed'] ? ' is-marked' : ''; ?>">
									<?php if ( $issue['closed'] ) : ?>
										<span class="hodima-advisor__mark-text"><?php echo $text; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- اجزا بالا escape شدند ?></span>
									<?php else : ?>
										<?php echo hodima_seo_advisor_mark_check( $form, (string) $issue['code'], $text ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- اجزا بالا escape شدند ?>
									<?php endif; ?>
									<?php if ( $has_u ) : ?>
										<?php echo hodima_seo_advisor_url_copy( (string) $issue['url'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML امن‌شده در hodima_seo_advisor_url_copy ?>
									<?php endif; ?>
									<?php if ( ! empty( $issue['items'] ) ) : ?>
										<?php // مقصدهای لینک: آدرس کامل قابل کپی + توضیح (کد پاسخ، مقصد ریدایرکت، متن لینک) ?>
										<ul class="hodima-advisor__crawl-items" aria-label="<?php echo esc_attr( 'مقصد لینک‌ها: ' . $info['label'] ); ?>">
											<?php foreach ( array_slice( $issue['items'], 0, 5 ) as $item ) : ?>
												<li>
													<?php echo hodima_seo_advisor_url_copy( (string) $item['path'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML امن‌شده در hodima_seo_advisor_url_copy ?>
													<small><?php echo esc_html( (string) $item['note'] ); ?></small>
												</li>
											<?php endforeach; ?>
											<?php if ( count( $issue['items'] ) > 5 ) : ?>
												<li><small><?php echo esc_html( sprintf( 'و %s مقصد دیگر', $n( count( $issue['items'] ) - 5 ) ) ); ?></small></li>
											<?php endif; ?>
										</ul>
									<?php endif; ?>
								</div>
							<?php endforeach; ?>
						</td>
						<td class="hodima-advisor__crawl-edit">
							<div class="hodima-advisor__row-acts">
								<?php if ( '' !== $target['edit'] ) : ?>
									<?php echo hodima_seo_advisor_action( $target['edit'], 'ویرایش صفحه', 'edit' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML امن‌شده در hodima_seo_advisor_action ?>
								<?php endif; ?>
								<?php if ( $open ) : ?>
									<?php hodima_seo_advisor_mark_form( $form, 'crawl', (string) $row['path'], '' !== $target['title'] ? $target['title'] : rawurldecode( (string) $row['path'] ) ); ?>
								<?php endif; ?>
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<div class="hodima-advisor__foot">
		<span>
			<?php echo esc_html( sprintf( '%1$s صفحه با ایراد از %2$s صفحه خزش‌شده', $n( count( $rows ) ), $n( $sum['crawled'] ) ) ); ?>
			<?php echo hodima_seo_advisor_marked_toggle( $hidden, [ 'tab' => 'crawl', 'cf' => $group, 'q' => $q ], 'ایراد' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML امن‌شده در hodima_seo_advisor_action ?>
		</span>
		<?php hodima_seo_ui_pager( $paged, $pages, static fn( int $i ): string => $url( [ 'cf' => $group, 'q' => $q, 'paged' => $i > 1 ? $i : '' ] ) ); ?>
	</div>
	<?php
	hodima_seo_ui_card_close();
}
