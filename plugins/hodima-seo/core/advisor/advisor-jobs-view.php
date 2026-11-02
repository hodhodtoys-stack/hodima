<?php
/**
 * ماژول «دستار سئو» — تب «خزش و داده»: هر کار خودکار کارت خودش را دارد (SEO 3.3.9)
 * Path: core/advisor/advisor-jobs-view.php
 *
 * طرح تأییدشده کاربر (۱۰ آبان ۱۴۰۵؛ Artifact «طرح کارهای جدای خزش و داده»): به‌جای یک کارت بزرگ «کارهای خودکار» و صفحه جدای
 * خزش، هر کار (دریافت داده گوگل، خزنده داخلی، بررسی ایندکس، ساخت پیشنهادها) کارت خودش را دارد: وضعیت، آخرین و بعدی،
 * پیشرفت (فقط وقتی کار در جریان است)، «اجرا همین حالا» و تنظیم همان کار. کار تازه فقط یک کارت دیگر می‌شود.
 * محاسبه سلامت در advisor-cron.php؛ فرم‌ها و کنش‌ها همان قبل‌اند (فقط بازگشت به همین تب).
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** Dashicon هر کار. */
function hodima_seo_advisor_job_icon( string $key ): string {
	return [
		'refresh'  => 'dashicons-cloud',
		'crawler'  => 'dashicons-networking',
		'inspect'  => 'dashicons-search',
		'pipeline' => 'dashicons-lightbulb',
	][ $key ] ?? 'dashicons-admin-generic';
}

/**
 * درصد پیشرفت دور خزش در جریان (۰ تا ۱۰۰).
 *
 * @param array{done: int, queued: int, seeding: int} $status
 */
function hodima_seo_advisor_crawler_percent( array $status ): int {

	$total = $status['done'] + $status['queued'] + $status['seeding'];

	return $total > 0 ? (int) min( 99, max( 0, round( $status['done'] / $total * 100 ) ) ) : 0;
}

/**
 * محتوای تب «خزش و داده».
 */
function hodima_seo_advisor_render_jobs(): void {

	$health = hodima_seo_advisor_cron_health();
	$pills  = hodima_seo_advisor_cron_pills();
	$n      = static fn( int $value ): string => number_format_i18n( $value );
	$bad    = $health['problems'] > 0;
	$total  = count( array_filter( $health['jobs'], static fn( array $job ): bool => 'off' !== $job['level'] ) );
	$mode   = $health['server_cron'] ? 'کرون سرور فعال است.' : 'کرون وردپرس: فقط با بازدید سایت اجرا می‌شود.';
	?>
	<div id="hodima-advisor-cron" class="hodima-advisor__jobs">
		<section class="hds-card hodima-advisor__jobs-status" aria-label="وضعیت کارهای خودکار">
			<header class="hds-statusbar hodima-advisor__cron-status" data-level="<?php echo esc_attr( $health['level'] ); ?>">
				<span class="hds-statusbar__icon dashicons <?php echo $bad ? 'dashicons-warning' : 'dashicons-yes-alt'; ?>" aria-hidden="true"></span>
				<div class="hds-statusbar__body">
					<h2 class="hds-statusbar__title">
						<?php
						echo esc_html(
							$bad
								? sprintf( '%1$s کار از %2$s کار خودکار عقب است', $n( $health['problems'] ), $n( $total ) )
								: sprintf( 'هر %s کار خودکار به موقع اجرا شده‌اند', $n( $total ) )
						);
						?>
					</h2>
					<p class="hds-statusbar__text" aria-live="polite"><?php echo esc_html( ( $bad ? 'داده‌های دستار ممکن است کهنه باشند؛ هر کار دیرشده را همین‌جا اجرا کنید. ' : 'دستار شب‌ها و هر روز بدون دخالت شما داده می‌گیرد و سایت را می‌خزد. ' ) . $mode ); ?></p>
				</div>
				<div class="hds-statusbar__aside">
					<span class="<?php echo $bad ? 'hds-pill hds-pill--error' : 'hds-pill'; ?>"><?php echo esc_html( $bad ? 'نیاز به رسیدگی' : 'سالم' ); ?></span>
				</div>
			</header>
		</section>

		<div class="hodima-advisor__jobs-list">
			<?php
			foreach ( $health['jobs'] as $job ) {
				hodima_seo_advisor_render_job_card( $job, $pills );
			}
			?>
		</div>
		<?php

		if ( $bad ) {
			hodima_seo_advisor_render_cron_guide( $health['server_cron'] );
		}

		hodima_seo_advisor_render_quality( 'jobs' );
		?>
	</div>
	<?php
}

/**
 * ردیف یک کار خودکار (SEO 3.4.8؛ طرح تأییدشده): آیکون، نام، وضعیت و زمان‌ها، نشان، «اجرا همین حالا» و «تنظیمات» در یک ردیف
 * تمام‌عرض؛ نوار پیشرفت زیر همان ردیف وقتی کار در جریان است؛ تنظیم همان کار فقط زیر خودش باز می‌شود (کارهای دیگر تکان نمی‌خورند).
 * بدون جاوااسکریپت: دکمه «اجرا» فرم admin-post است و پنل تنظیمات همیشه باز.
 *
 * @param array{key: string, label: string, desc: string, last: string, expect: string, level: string} $job
 * @param array<string, array{0: string, 1: string}>                                                   $pills
 */
function hodima_seo_advisor_render_job_card( array $job, array $pills ): void {

	$key      = $job['key'];
	$pill     = $pills[ $job['level'] ] ?? $pills['ok'];
	$title    = 'hodima-advisor-job-' . $key;
	$panel    = 'hodima-advisor-job-set-' . $key;
	$progress = hodima_seo_advisor_job_progress_view( $key );
	?>
	<article class="hodima-advisor__job" data-job="<?php echo esc_attr( $key ); ?>" data-level="<?php echo esc_attr( $job['level'] ); ?>" data-hdsa-job-nonce="<?php echo esc_attr( wp_create_nonce( 'hodima_advisor_job' ) ); ?>" data-hdsa-ajax="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>"<?php echo 'crawler' === $key ? ' data-hdsa-crawl-nonce="' . esc_attr( wp_create_nonce( 'hodima_advisor_crawler_step' ) ) . '"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- امن‌شده بالا ?> aria-labelledby="<?php echo esc_attr( $title ); ?>"<?php echo $progress['running'] ? ' data-running="1"' : ''; ?>>
		<div class="hodima-advisor__job-row">
			<span class="hodima-advisor__job-icon dashicons <?php echo esc_attr( hodima_seo_advisor_job_icon( $key ) ); ?>" aria-hidden="true"></span>
			<div class="hodima-advisor__job-title">
				<h3 id="<?php echo esc_attr( $title ); ?>"><?php echo esc_html( $job['label'] ); ?></h3>
				<p><?php echo esc_html( $job['desc'] ); ?></p>
			</div>
			<dl class="hodima-advisor__job-facts">
				<div><dt>وضعیت اجرا</dt><dd data-hdsa-fact="last"><?php echo esc_html( $job['last'] ); ?></dd></div>
				<div><dt>اجرای بعدی</dt><dd data-hdsa-fact="expect"><?php echo esc_html( $job['expect'] ); ?></dd></div>
			</dl>
			<span class="<?php echo esc_attr( $pill[1] ); ?> hodima-advisor__job-pill" data-hdsa-pill><?php echo esc_html( $progress['running'] ? 'در حال اجرا' : $pill[0] ); ?></span>
			<div class="hodima-advisor__job-actions">
				<?php hodima_seo_advisor_render_job_run( $key ); ?>
				<button type="button" class="hodima-advisor__job-toggle" data-hdsa-job-toggle aria-expanded="false" aria-controls="<?php echo esc_attr( $panel ); ?>">
					تنظیمات <span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span>
				</button>
			</div>
		</div>
		<?php hodima_seo_advisor_render_job_progress( $key, $progress ); ?>
		<p class="hodima-advisor__job-done" role="status" data-hdsa-job-done hidden></p>
		<div class="hodima-advisor__job-set" id="<?php echo esc_attr( $panel ); ?>" data-hdsa-job-panel>
			<div class="hodima-advisor__job-set-body">
				<?php hodima_seo_advisor_render_job_settings( $key ); ?>
			</div>
		</div>
	</article>
	<?php
}

/**
 * وضعیت پیشرفت برای نمایش هنگام بارگذاری صفحه: خزنده از شمارنده خودش، بقیه از advisor-progress.php.
 *
 * @return array{running: bool, percent: int, label: string}
 */
function hodima_seo_advisor_job_progress_view( string $key ): array {

	if ( 'crawler' === $key ) {
		$status = hodima_seo_advisor_crawler_status();
		[ , , $info ] = hodima_seo_advisor_crawler_status_view();

		return [ 'running' => in_array( $status['state'], [ 'running', 'paused' ], true ), 'percent' => hodima_seo_advisor_crawler_percent( $status ), 'label' => $info ];
	}

	$progress = hodima_seo_advisor_progress_get( $key );

	return [ 'running' => $progress['running'], 'percent' => $progress['percent'], 'label' => $progress['total'] > 1 ? sprintf( 'گام %1$s از %2$s: %3$s', number_format_i18n( $progress['step'] ), number_format_i18n( $progress['total'] ), $progress['label'] ) : $progress['label'] ];
}

/**
 * نوار پیشرفت زیر ردیف؛ تا کار در جریان نیست پنهان است (JS با «اجرا» نشانش می‌دهد).
 *
 * @param array{running: bool, percent: int, label: string} $progress
 */
function hodima_seo_advisor_render_job_progress( string $key, array $progress ): void {

	$live = '';

	if ( $progress['running'] && 'crawler' === $key && [] !== array_filter( [ hodima_seo_advisor_crawler_status_view()[3] ] ) ) {
		$live = ' data-hdsa-crawl-live="' . esc_attr( wp_create_nonce( 'hodima_advisor_crawler_step' ) ) . '" data-hdsa-ajax="' . esc_url( admin_url( 'admin-ajax.php' ) ) . '"';
	} elseif ( $progress['running'] ) {
		$live = ' data-hdsa-job-poll="1"';
	}
	?>
	<div class="hodima-advisor__job-prog" data-hdsa-job-prog<?php echo $progress['running'] ? '' : ' hidden'; ?><?php echo $live; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- ویژگی‌های امن‌شده بالا ?>>
		<div class="hodima-advisor__job-prog-top">
			<span class="hodima-advisor__job-prog-label" role="status" data-hdsa-crawl-text data-hdsa-job-text><?php echo esc_html( $progress['label'] ); ?></span>
			<b class="hodima-advisor__job-prog-pc" data-hdsa-job-pc><?php echo esc_html( number_format_i18n( $progress['percent'] ) ); ?>٪</b>
		</div>
		<div class="hodima-advisor__job-bar" role="progressbar" aria-label="پیشرفت <?php echo esc_attr( hodima_seo_advisor_job_name( $key ) ); ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr( (string) $progress['percent'] ); ?>" data-hdsa-crawl-bar>
			<i style="inline-size: <?php echo esc_attr( (string) $progress['percent'] ); ?>%"></i>
		</div>
	</div>
	<?php
}

/** نام فارسی کار (برچسب خوانشگر نوار پیشرفت). */
function hodima_seo_advisor_job_name( string $key ): string {
	return [ 'refresh' => 'دریافت داده گوگل', 'crawler' => 'خزنده داخلی', 'inspect' => 'بررسی ایندکس گوگل', 'pipeline' => 'ساخت پیشنهادها' ][ $key ] ?? $key;
}

/**
 * دکمه «اجرا همین حالا»: فرم admin-post (بدون JS) که JS آن را به ایجکس نوار پیشرفت می‌برد (data-hdsa-job-form).
 * دریافت داده: فیلد «بازه» در پنل تنظیمات با ویژگی form به همین فرم وصل است.
 */
function hodima_seo_advisor_render_job_run( string $key ): void {

	if ( 'refresh' === $key ) {
		$form    = 'hodima-advisor-job-form-refresh';
		$has_key = '' !== hodima_seo_sc_key_json( HODIMA_SEO_SC_ADVISOR );
		?>
		<form id="<?php echo esc_attr( $form ); ?>" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="hodima-advisor__cron-run" data-hdsa-job-form="refresh">
			<input type="hidden" name="action" value="hodima_advisor_refresh">
			<input type="hidden" name="tab" value="jobs">
			<?php wp_nonce_field( 'hodima_advisor_refresh' ); ?>
			<button type="submit" class="hds-btn hds-btn--outline"<?php disabled( ! $has_key ); ?>><span class="dashicons dashicons-controls-play" aria-hidden="true"></span> اجرا همین حالا</button>
		</form>
		<?php
		return;
	}

	echo hodima_seo_advisor_cron_run_form( $key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML ساخته‌شده با esc_*
}

/** محتوای پنل «تنظیمات» هر کار. */
function hodima_seo_advisor_render_job_settings( string $key ): void {

	if ( 'refresh' === $key ) {
		$days   = hodima_seo_advisor_days();
		$labels = [ 28 => '۲۸ روز', 90 => '۳ ماه', 180 => '۶ ماه' ];
		?>
		<fieldset class="hodima-advisor__seg">
			<legend>داده چند روز اخیر گرفته شود؟</legend>
			<?php foreach ( $labels as $value => $label ) : ?>
				<label class="hodima-advisor__seg-opt">
					<input type="radio" name="days" value="<?php echo esc_attr( (string) $value ); ?>" form="hodima-advisor-job-form-refresh"<?php checked( $days, $value ); ?>>
					<span><?php echo esc_html( $label ); ?></span>
				</label>
			<?php endforeach; ?>
		</fieldset>
		<p class="hodima-advisor__setting-help">بازه بلندتر تحلیل دقیق‌تری می‌دهد ولی دریافتش کندتر است. بازه با دکمه «اجرا همین حالا» ذخیره و از همین به بعد استفاده می‌شود.</p>
		<?php
		return;
	}

	if ( 'crawler' === $key ) {
		?>
		<p class="hodima-advisor__setting-help">«اجرا همین حالا» همیشه یک دور کامل است (بی‌توجه به ساعت‌ها).</p>
		<?php
		hodima_seo_advisor_render_crawler_settings_form( 'jobs' );
		return;
	}

	?>
	<p class="hodima-advisor__setting-help">
		<?php echo esc_html( 'inspect' === $key ? 'این کار تنظیم جدا ندارد؛ هر دسته ' . number_format_i18n( HODIMA_SEO_ADVISOR_INSPECT_BATCH ) . ' آدرس از صف بررسی می‌کند و سهمیهٔ روزانهٔ گوگل را رعایت می‌کند.' : 'این کار تنظیم جدا ندارد؛ بعد از هر دریافت و هر خزش به‌طور خودکار اجرا می‌شود.' ); ?>
	</p>
	<?php
}

/**
 * فرم زمان‌بندی خزش (روشن/خاموش، ساعت‌ها، فاصله دور کامل). ذخیره با hodima_seo_advisor_handle_crawler_settings و بازگشت به `$back`.
 */
function hodima_seo_advisor_render_crawler_settings_form( string $back ): void {

	$settings = hodima_seo_advisor_crawler_settings();
	$n        = static fn( int|float $value ): string => number_format_i18n( $value );
	$hour     = static fn( int $h ): string => $n( $h ) . ':۰۰';
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="hodima-advisor__crawler-form">
		<input type="hidden" name="action" value="hodima_advisor_crawler_settings">
		<input type="hidden" name="tab" value="<?php echo esc_attr( $back ); ?>">
		<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( 'hodima_advisor_crawler_settings' ) ); ?>">
		<label class="hodima-advisor__crawler-switch">
			<input type="checkbox" class="hds-switch" name="enabled" value="1"<?php checked( $settings['enabled'] ); ?>>
			<span>خزش خودکار روشن باشد</span>
		</label>
		<fieldset class="hodima-advisor__crawler-mode">
			<legend>چه ساعت‌هایی بخزد؟</legend>
			<label><input type="radio" name="always" value="0"<?php checked( ! $settings['always'] && $settings['start'] !== $settings['end'] ); ?>> <span>فقط در ساعت‌های انتخاب‌شده (پیشنهادی: شب، تا سایت کند نشود)</span></label>
			<label><input type="radio" name="always" value="1"<?php checked( $settings['always'] || $settings['start'] === $settings['end'] ); ?>> <span>همه ساعت‌ها (۲۴ ساعته)</span></label>
		</fieldset>
		<div class="hodima-advisor__crawler-fields">
			<label class="hodima-advisor__crawler-field" for="hodima-crawler-start">
				<span>از ساعت</span>
				<select id="hodima-crawler-start" name="start" class="hds-input">
					<?php for ( $h = 0; $h < 24; $h++ ) : ?>
						<option value="<?php echo esc_attr( (string) $h ); ?>"<?php selected( $settings['start'], $h ); ?>><?php echo esc_html( $hour( $h ) ); ?></option>
					<?php endfor; ?>
				</select>
			</label>
			<label class="hodima-advisor__crawler-field" for="hodima-crawler-end">
				<span>تا ساعت</span>
				<select id="hodima-crawler-end" name="end" class="hds-input">
					<?php for ( $h = 1; $h <= 24; $h++ ) : ?>
						<option value="<?php echo esc_attr( (string) $h ); ?>"<?php selected( $settings['end'], $h ); ?>><?php echo esc_html( $hour( $h ) ); ?></option>
					<?php endfor; ?>
				</select>
			</label>
			<label class="hodima-advisor__crawler-field" for="hodima-crawler-interval">
				<span>دور کامل هر</span>
				<select id="hodima-crawler-interval" name="interval" class="hds-input">
					<?php foreach ( HODIMA_SEO_ADVISOR_CRAWLER_INTERVALS as $days ) : ?>
						<option value="<?php echo esc_attr( (string) $days ); ?>"<?php selected( $settings['interval'], $days ); ?>><?php echo esc_html( $n( $days ) . ' روز' ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
		</div>
		<p class="hodima-advisor__setting-help">
			«تا ساعت ۲۴:۰۰» یعنی تا نیمه‌شب. اگر ساعت پایان از شروع کوچک‌تر باشد بازه از شب به صبح می‌رود (مثلا ۲۲ تا ۶).
			هر روز یک دور سریع (صفحه اصلی، پرنمایش‌ترین‌ها و نوشته‌های تازه‌ویرایش‌شده) انجام می‌شود و هر چند روز یک دور کامل؛ برای کمترین بار روی سایت ۷ روز بگذارید.
		</p>
		<button type="submit" class="hds-btn">ذخیره زمان‌بندی</button>
	</form>
	<?php
}

/**
 * راهنمای «چطور درستش کنم؟» وقتی کاری عقب است: کرون واقعی روی هاست.
 */
function hodima_seo_advisor_render_cron_guide( bool $server_cron ): void {
	?>
	<details class="hds-more hodima-advisor__cron-guide" open>
		<summary class="hds-more__summary">چطور درستش کنم؟</summary>
		<div class="hds-more__body">
			<p class="hds-card__desc">کرون وردپرس فقط وقتی اجرا می‌شود که کسی سایت را باز کند؛ شب‌ها بازدید کم است و کارها عقب می‌افتند. یک کرون واقعی روی هاست این مشکل را برای همیشه حل می‌کند.</p>
			<div class="hodima-advisor__cron-step">
				<b>۱. در هاست (مثلا cPanel ← Cron Jobs) این دستور را هر ۵ دقیقه اجرا کنید:</b>
				<div class="hodima-advisor__cron-code">
					<code id="hodima-advisor-cron-cmd" dir="ltr"><?php echo esc_html( hodima_seo_advisor_cron_command() ); ?></code>
					<button type="button" class="hds-btn hds-btn--outline" data-hdsa-copy="hodima-advisor-cron-cmd" aria-label="کپی دستور کرون">کپی</button>
				</div>
			</div>
			<?php if ( ! $server_cron ) : ?>
				<div class="hodima-advisor__cron-step">
					<b>۲. بعد از ساخت آن، این خط را به wp-config.php اضافه کنید تا کرون دو بار اجرا نشود:</b>
					<div class="hodima-advisor__cron-code">
						<code id="hodima-advisor-cron-def" dir="ltr">define( 'DISABLE_WP_CRON', true );</code>
						<button type="button" class="hds-btn hds-btn--outline" data-hdsa-copy="hodima-advisor-cron-def" aria-label="کپی خط wp-config">کپی</button>
					</div>
				</div>
			<?php endif; ?>
			<p class="hds-card__desc">چند ساعت بعد همین صفحه را دوباره ببینید؛ وقتی همه نشان‌ها سبز شد کار تمام است.</p>
		</div>
	</details>
	<?php
}
