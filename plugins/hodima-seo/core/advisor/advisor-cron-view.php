<?php
/**
 * ماژول «دستار سئو» — نمایش سلامت کارهای خودکار (SEO 3.2.0)
 * Path: core/advisor/advisor-cron-view.php
 *
 * طرح تأییدشده کاربر (۹ آبان ۱۴۰۵): کارت «کارهای خودکار به موقع اجرا می‌شوند؟» بالای تب «تنظیمات» (مشکل‌دارها اول
 * و کامل، سالم‌ها یک‌خطی؛ همه سالم = یک خط وضعیت و جزئیات جمع‌شده) و یک نوار هشدار فقط روی تب‌های «پیشنهادها» و
 * «ایرادهای فنی» و فقط وقتی کاری دیر شده یا متوقف است. حالت سالم ساکت است. محاسبه در advisor-cron.php.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** تب‌هایی که نوار هشدار دارند (عددشان مستقیم به تصمیم مدیر می‌رسد). */
const HODIMA_SEO_ADVISOR_CRON_NOTICE_TABS = [ 'opportunities', 'crawl' ];

/**
 * برچسب و کلاس نشان هر حالت.
 *
 * @return array<string, array{0: string, 1: string}> [برچسب، کلاس نشان]
 */
function hodima_seo_advisor_cron_pills(): array {
	return [
		'ok'   => [ 'سالم', 'hds-pill' ],
		'warn' => [ 'دیر شده', 'hds-pill hds-pill--warn' ],
		'bad'  => [ 'متوقف', 'hds-pill hds-pill--error' ],
		'off'  => [ 'غیرفعال', 'hds-pill hds-pill--info' ],
	];
}

add_action( 'admin_post_hodima_advisor_pipeline_now', 'hodima_seo_advisor_handle_pipeline_now' );

/**
 * «اجرا همین حالا» برای ساخت پیشنهادها (نوبت‌های پس‌زمینه): چند ثانیه نوبت‌ها را همین‌جا پیش می‌برد، بی‌نیاز از کرون.
 */
function hodima_seo_advisor_handle_pipeline_now(): void {

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'اجازه این کار را ندارید.', 'دسترسی ندارید', [ 'response' => 403 ] );
	}

	check_admin_referer( 'hodima_advisor_pipeline_now' );

	if ( ! hodima_seo_advisor_pipeline_pending() ) {
		hodima_admin_flash( '<strong>ساخت پیشنهادها:</strong> کاری برای ادامه نیست.', 'success' );
	} elseif ( hodima_seo_advisor_lock() ) {
		try {
			hodima_seo_advisor_pipeline_run( (float) HODIMA_SEO_ADVISOR_MANUAL_BUDGET );
		} finally {
			hodima_seo_advisor_unlock();
		}
		hodima_admin_flash(
			hodima_seo_advisor_pipeline_pending()
				? '<strong>ساخت پیشنهادها:</strong> چند نوبت انجام شد و بقیه مانده؛ دوباره «اجرا همین حالا» را بزنید.'
				: '<strong>ساخت پیشنهادها:</strong> همه نوبت‌ها تمام شد.',
			'success'
		);
	} else {
		hodima_admin_flash( '<strong>ساخت پیشنهادها:</strong> هم‌اکنون کار دیگری از دستار در حال اجراست؛ چند دقیقه بعد دوباره امتحان کنید.', 'error' );
	}

	wp_safe_redirect( hodima_seo_advisor_page_url( [ 'tab' => 'settings' ] ) . '#hodima-advisor-cron' );
	exit;
}

/**
 * فرم «اجرا همین حالا» یک کار دیرشده: به همان کنش دستی خودش (دکمه تب خودش) می‌رود و به کارت برمی‌گردد.
 */
function hodima_seo_advisor_cron_run_form( string $key ): string {

	$map = [
		'refresh'  => [ 'hodima_advisor_refresh', 'hodima_advisor_refresh' ],
		'inspect'  => [ 'hodima_advisor_inspect_now', 'hodima_advisor_inspect_now' ],
		'crawler'  => [ 'hodima_advisor_crawler_now', 'hodima_advisor_crawler_now' ],
		'pipeline' => [ 'hodima_advisor_pipeline_now', 'hodima_advisor_pipeline_now' ],
	];

	if ( ! isset( $map[ $key ] ) ) {
		return '';
	}

	return sprintf(
		'<form method="post" action="%1$s" class="hodima-advisor__cron-run"><input type="hidden" name="action" value="%2$s"><input type="hidden" name="_wpnonce" value="%3$s"><input type="hidden" name="tab" value="settings"><button type="submit" class="hds-btn hds-btn--outline hodima-advisor__act"><span class="dashicons dashicons-controls-play" aria-hidden="true"></span> اجرا همین حالا</button></form>',
		esc_url( admin_url( 'admin-post.php' ) ),
		esc_attr( $map[ $key ][0] ),
		esc_attr( wp_create_nonce( $map[ $key ][1] ) )
	);
}

/**
 * کارت «کارهای خودکار به موقع اجرا می‌شوند؟».
 */
function hodima_seo_advisor_render_cron_card(): void {

	$health = hodima_seo_advisor_cron_health();
	$pills  = hodima_seo_advisor_cron_pills();
	$n      = static fn( int $value ): string => number_format_i18n( $value );
	$bad    = $health['problems'] > 0;
	$total  = count( array_filter( $health['jobs'], static fn( array $job ): bool => 'off' !== $job['level'] ) );
	$mode   = $health['server_cron'] ? 'کرون سرور فعال است.' : 'کرون وردپرس: فقط با بازدید سایت اجرا می‌شود.';
	$weight = [ 'bad' => 0, 'warn' => 1, 'ok' => 2, 'off' => 3 ];
	$jobs   = $health['jobs'];

	usort( $jobs, static fn( array $a, array $b ): int => $weight[ $a['level'] ] <=> $weight[ $b['level'] ] ); // مشکل‌دارها اول (usort در PHP 8 پایدار است)

	hodima_seo_ui_card_open( 'کارهای خودکار به موقع اجرا می‌شوند؟', 'دستار شب‌ها و هر روز بدون دخالت شما داده می‌گیرد و سایت را می‌خزد؛ اینجا می‌بینید این کارها کار می‌کنند یا نه.', '', 'id="hodima-advisor-cron"' );
	?>
	<header class="hds-statusbar hodima-advisor__cron-status" data-level="<?php echo esc_attr( $health['level'] ); ?>">
		<span class="hds-statusbar__icon dashicons <?php echo $bad ? 'dashicons-warning' : 'dashicons-yes-alt'; ?>" aria-hidden="true"></span>
		<div class="hds-statusbar__body">
			<h3 class="hds-statusbar__title">
				<?php
				echo esc_html(
					$bad
						? sprintf( '%1$s کار از %2$s کار خودکار عقب است', $n( $health['problems'] ), $n( $total ) )
						: sprintf( 'هر %s کار خودکار به موقع اجرا شده‌اند', $n( $total ) )
				);
				?>
			</h3>
			<p class="hds-statusbar__text" aria-live="polite"><?php echo esc_html( ( $bad ? 'داده‌های دستار ممکن است کهنه باشند؛ هر کار دیرشده را می‌توانید همین‌جا دستی اجرا کنید. ' : '' ) . $mode ); ?></p>
		</div>
		<div class="hds-statusbar__aside">
			<?php if ( $bad ) : ?>
				<span class="hds-pill hds-pill--error">نیاز به رسیدگی</span>
			<?php else : ?>
				<span class="hds-pill">سالم</span>
			<?php endif; ?>
		</div>
	</header>
	<?php

	$rows = static function () use ( $jobs, $pills, $bad ): void {
		echo '<ul class="hodima-advisor__cron-jobs">';
		foreach ( $jobs as $job ) {
			$compact = $bad && ( 'ok' === $job['level'] || 'off' === $job['level'] );
			printf(
				'<li class="hodima-advisor__cron-job%1$s" data-level="%2$s"><div class="hodima-advisor__cron-name"><b>%3$s</b><span>%4$s</span></div><div class="hodima-advisor__cron-when"><span>وضعیت اجرا</span><b>%5$s</b></div><div class="hodima-advisor__cron-exp"><span>انتظار</span><b>%6$s</b></div><div class="hodima-advisor__cron-end"><span class="%7$s">%8$s</span>%9$s</div></li>',
				$compact ? ' hodima-advisor__cron-job--compact' : '',
				esc_attr( $job['level'] ),
				esc_html( $job['label'] ),
				esc_html( $job['desc'] ),
				esc_html( $job['last'] ),
				esc_html( $job['expect'] ),
				esc_attr( $pills[ $job['level'] ][1] ),
				esc_html( $pills[ $job['level'] ][0] ),
				'warn' === $job['level'] || 'bad' === $job['level'] ? hodima_seo_advisor_cron_run_form( $job['key'] ) : '' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML ساخته‌شده و escape‌شده در hodima_seo_advisor_cron_run_form
			);
		}
		echo '</ul>';
	};

	if ( $bad ) {
		$rows();
	} else {
		echo '<details class="hds-more hodima-advisor__cron-more"><summary class="hds-more__summary">دیدن جزئیات هر کار</summary><div class="hds-more__body">';
		$rows();
		echo '</div></details>';
	}

	if ( $bad ) {
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
				<?php if ( ! $health['server_cron'] ) : ?>
					<div class="hodima-advisor__cron-step">
						<b>۲. بعد از ساخت آن، این خط را به wp-config.php اضافه کنید تا کرون دو بار اجرا نشود:</b>
						<div class="hodima-advisor__cron-code">
							<code id="hodima-advisor-cron-def" dir="ltr">define( 'DISABLE_WP_CRON', true );</code>
							<button type="button" class="hds-btn hds-btn--outline" data-hdsa-copy="hodima-advisor-cron-def" aria-label="کپی خط wp-config">کپی</button>
						</div>
					</div>
				<?php endif; ?>
				<p class="hds-card__desc">چند ساعت بعد همین کارت را دوباره ببینید؛ وقتی همه نشان‌ها سبز شد کار تمام است.</p>
			</div>
		</details>
		<?php
	}

	hodima_seo_ui_card_close();
}

/**
 * نوار هشدار زیر سربرگ، فقط روی تب‌های HODIMA_SEO_ADVISOR_CRON_NOTICE_TABS و فقط وقتی کاری دیر شده یا متوقف است.
 */
function hodima_seo_advisor_cron_notice( string $tab ): void {

	if ( ! in_array( $tab, HODIMA_SEO_ADVISOR_CRON_NOTICE_TABS, true ) ) {
		return;
	}

	$health = hodima_seo_advisor_cron_health();

	if ( 'ok' === $health['level'] ) {
		return;
	}

	$late = [];
	foreach ( $health['jobs'] as $job ) {
		if ( 'warn' === $job['level'] || 'bad' === $job['level'] ) {
			$late[] = sprintf( '%1$s: %2$s', $job['label'], $job['last'] );
		}
	}

	wp_admin_notice(
		sprintf(
			'<strong>کارهای خودکار دستار عقب‌اند.</strong> %1$s؛ عددهای این صفحه ممکن است کهنه باشند. <a href="%2$s">دیدن راه‌حل</a>',
			esc_html( implode( '، ', $late ) ),
			esc_url( hodima_seo_advisor_page_url( [ 'tab' => 'settings' ] ) . '#hodima-advisor-cron' )
		),
		[ 'type' => 'bad' === $health['level'] ? 'error' : 'warning', 'additional_classes' => [ 'inline' ] ] // اعلان خود وردپرس: صفحه به Core وابسته نشود
	);
}
