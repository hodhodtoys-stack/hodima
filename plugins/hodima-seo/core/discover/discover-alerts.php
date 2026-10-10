<?php
/**
 * ماژول «گوگل دیسکاور» — هشدارها، ابزارک پیشخوان و خلاصه هفتگی با ایمیل
 * Path: core/discover/discover-alerts.php
 *
 * SEO 2.1.8. تا 2.1.7 افت ناگهانی یا ورود صفحه تازه به دیسکاور فقط با باز
 * کردن گزارش دیده می‌شد. حالا بعد از هر به‌روزرسانی آمار (کرون روزانه یا
 * دکمه؛ discover-stats.php):
 *   - افت: نمایش ۷ روز آخر نسبت به ۷ روز پیش از آن، بیشتر از درصد تنظیمات؛
 *   - تازه در دیسکاور: صفحه‌هایی که ۲۸ روز آخر نمایش دارند و ۲۸ روز قبل نه.
 * نمایش: بالای صفحه گوگل دیسکاور و ابزارک «گوگل دیسکاور» پیشخوان وردپرس؛
 * خلاصه هفتگی با ایمیل اگر در تنظیمات روشن باشد. هیچ درخواستی به گوگل اینجا
 * نیست؛ فقط داده ذخیره‌شده.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** گزینه هشدارهای آخرین به‌روزرسانی. */
const HODIMA_SEO_DISCOVER_ALERTS_OPTION = 'hodima_discover_alerts';

/** رویداد هفتگی خلاصه ایمیلی. */
const HODIMA_SEO_DISCOVER_DIGEST_CRON = 'hodima_discover_digest';

/** کمترین نمایش هفته قبل که «افت» نسبت به آن معنی دارد. */
const HODIMA_SEO_DISCOVER_ALERT_MIN_BASE = 50;

/**
 * نمایش و کلیک ۷ روز آخر و ۷ روز پیش از آن (کل سایت، از تاریخچه روزانه).
 *
 * @return array{now: array{clicks: int, impressions: int}, before: array{clicks: int, impressions: int}, days: int}
 */
function hodima_seo_discover_week_totals(): array {

	$daily = hodima_seo_discover_history_daily( 0 );
	$last  = (string) array_key_last( $daily );
	$sum   = static fn( array $days ): array => [
		'clicks'      => array_sum( array_column( $days, 'clicks' ) ),
		'impressions' => array_sum( array_column( $days, 'impressions' ) ),
	];

	if ( '' === $last ) {
		return [ 'now' => $sum( [] ), 'before' => $sum( [] ), 'days' => 0 ];
	}

	// ۱۴ روز تقویمی تا آخرین روز داده (روز بی‌ردیف = صفر؛ کرون هم بدون فایل‌های پیشخوان)
	$days = [];
	$seen = 0;
	for ( $i = 13; $i >= 0; $i-- ) {
		$key    = gmdate( 'Y-m-d', (int) strtotime( $last . ' 12:00:00 UTC' ) - $i * DAY_IN_SECONDS );
		$days[] = $daily[ $key ] ?? [ 'clicks' => 0, 'impressions' => 0 ];
		$seen  += isset( $daily[ $key ] ) || (string) array_key_first( $daily ) <= $key ? 1 : 0;
	}

	return [
		'now'    => $sum( array_slice( $days, 7 ) ),
		'before' => $sum( array_slice( $days, 0, 7 ) ),
		'days'   => $seen,
	];
}

/**
 * ساخت هشدارها از آمار ذخیره‌شده (بعد از هر به‌روزرسانی موفق).
 *
 * @return array{at: int, drop: array{now: int, before: int, percent: int}|null, new: list<array{key: string, clicks: int, impressions: int}>}
 */
function hodima_seo_discover_alerts_update(): array {

	$stats = hodima_seo_discover_stats();
	$week  = hodima_seo_discover_week_totals();
	$limit = (int) hodima_seo_discover_option( 'alert_drop' );
	$drop  = null;

	if ( $week['days'] >= 14 && $week['before']['impressions'] >= HODIMA_SEO_DISCOVER_ALERT_MIN_BASE ) {
		$percent = (int) round( 100 * ( $week['before']['impressions'] - $week['now']['impressions'] ) / $week['before']['impressions'] );
		if ( $percent >= $limit ) {
			$drop = [ 'now' => $week['now']['impressions'], 'before' => $week['before']['impressions'], 'percent' => $percent ];
		}
	}

	$new = [];
	if ( '' !== $stats['prev']['start'] ) {
		foreach ( $stats['rows'] as $key => $row ) {
			if ( $row['impressions'] > 0 && empty( $stats['prev']['rows'][ $key ]['impressions'] ) ) {
				$new[] = [ 'key' => (string) $key, 'clicks' => $row['clicks'], 'impressions' => $row['impressions'] ];
			}
		}
		usort( $new, static fn( array $a, array $b ): int => $b['impressions'] <=> $a['impressions'] );
	}

	$alerts = [ 'at' => time(), 'drop' => $drop, 'new' => array_slice( $new, 0, 10 ) ];
	update_option( HODIMA_SEO_DISCOVER_ALERTS_OPTION, $alerts, false );

	return $alerts;
}

/**
 * هشدارهای ذخیره‌شده.
 *
 * @return array{at: int, drop: array{now: int, before: int, percent: int}|null, new: list<array{key: string, clicks: int, impressions: int}>}
 */
function hodima_seo_discover_alerts(): array {
	$a    = get_option( HODIMA_SEO_DISCOVER_ALERTS_OPTION, [] );
	$a    = is_array( $a ) ? $a : [];
	$drop = is_array( $a['drop'] ?? null ) ? [ 'now' => (int) ( $a['drop']['now'] ?? 0 ), 'before' => (int) ( $a['drop']['before'] ?? 0 ), 'percent' => (int) ( $a['drop']['percent'] ?? 0 ) ] : null;
	$new  = [];
	foreach ( is_array( $a['new'] ?? null ) ? $a['new'] : [] as $n ) {
		if ( is_array( $n ) ) {
			$new[] = [ 'key' => (string) ( $n['key'] ?? '' ), 'clicks' => (int) ( $n['clicks'] ?? 0 ), 'impressions' => (int) ( $n['impressions'] ?? 0 ) ];
		}
	}
	return [ 'at' => (int) ( $a['at'] ?? 0 ), 'drop' => $drop, 'new' => $new ];
}

/**
 * متن هشدار افت.
 *
 * @param array{now: int, before: int, percent: int} $drop
 */
function hodima_seo_discover_drop_text( array $drop ): string {
	return sprintf(
		'نمایش دیسکاور در ۷ روز آخر %1$s٪ کم شده است (%2$s ← %3$s نسبت به ۷ روز قبل). اگر تازه عنوان یا تصویر کارت صفحه‌ای را عوض کرده‌اید، علامتش روی نمودار «آمار سرچ کنسول» است.',
		number_format_i18n( (int) $drop['percent'] ),
		number_format_i18n( (int) $drop['before'] ),
		number_format_i18n( (int) $drop['now'] )
	);
}

/**
 * متن «تازه در دیسکاور».
 *
 * @param list<array{key: string, clicks: int, impressions: int}> $pages
 */
function hodima_seo_discover_new_text( array $pages ): string {
	$names = array_map( static fn( array $n ): string => hodima_seo_discover_key_label( $n['key'] ), array_slice( $pages, 0, 3 ) );
	return sprintf(
		'%1$s صفحه تازه در دیسکاور دیده شد: %2$s%3$s.',
		number_format_i18n( count( $pages ) ),
		implode( '، ', $names ),
		count( $pages ) > 3 ? ' و …' : ''
	);
}

/** نام صفحه از کلید آدرس (اگر پیدا شد)، وگرنه خود مسیر. */
function hodima_seo_discover_key_label( string $key ): string {
	$id = url_to_postid( home_url( $key ) );
	return $id > 0 ? '«' . get_the_title( $id ) . '»' : $key;
}

/* =====================================================================
 * نمایش در پیشخوان
 * ===================================================================== */

/** هشدارها بالای صفحه گوگل دیسکاور (discover-report.php). */
function hodima_seo_discover_render_alerts(): void {

	if ( ! hodima_seo_discover_option( 'alerts' ) || ! function_exists( 'hodima_admin_notice' ) ) {
		return;
	}

	$alerts = hodima_seo_discover_alerts();

	if ( null !== $alerts['drop'] ) {
		hodima_admin_notice( hodima_seo_discover_drop_text( $alerts['drop'] ), 'warning' );
	}
	if ( $alerts['new'] ) {
		hodima_admin_notice( hodima_seo_discover_new_text( $alerts['new'] ), 'success' );
	}
}

/** ابزارک «گوگل دیسکاور» پیشخوان وردپرس. */
add_action( 'wp_dashboard_setup', static function (): void {
	if ( current_user_can( 'edit_others_posts' ) && hodima_seo_discover_option( 'alerts' ) && hodima_seo_discover_stats()['fetched'] ) {
		wp_add_dashboard_widget( 'hodima_discover_widget', 'گوگل دیسکاور', 'hodima_seo_discover_dashboard_widget' );
	}
} );

function hodima_seo_discover_dashboard_widget(): void {

	$stats  = hodima_seo_discover_stats();
	$week   = hodima_seo_discover_week_totals();
	$alerts = hodima_seo_discover_alerts();
	$change = hodima_seo_discover_change( $week['now']['impressions'], $week['before']['impressions'] ?: null );
	$url    = function_exists( 'hodima_seo_discover_page_url' ) ? hodima_seo_discover_page_url( [ 'tab' => 'stats' ] ) : admin_url();
	$top    = $stats['rows'];
	uasort( $top, static fn( array $a, array $b ): int => $b['impressions'] <=> $a['impressions'] );
	?>
	<div class="hodima-dw">
		<p><strong><?php echo esc_html( sprintf( '۷ روز آخر: %1$s نمایش، %2$s کلیک', number_format_i18n( $week['now']['impressions'] ), number_format_i18n( $week['now']['clicks'] ) ) ); ?></strong>
			<?php if ( null !== $change ) : ?>
				<span><?php echo esc_html( '(' . hodima_seo_discover_change_text( $change ) . ' نسبت به ۷ روز قبل)' ); ?></span>
			<?php endif; ?>
		</p>
		<?php if ( null !== $alerts['drop'] ) : ?>
			<p class="hodima-dw__alert"><?php echo esc_html( hodima_seo_discover_drop_text( $alerts['drop'] ) ); ?></p>
		<?php endif; ?>
		<?php if ( $alerts['new'] ) : ?>
			<p class="hodima-dw__new"><?php echo esc_html( hodima_seo_discover_new_text( $alerts['new'] ) ); ?></p>
		<?php endif; ?>
		<?php if ( $top ) : ?>
			<p><?php echo esc_html( 'پرنمایش‌ترین صفحه‌ها (۲۸ روز):' ); ?></p>
			<ol>
				<?php foreach ( array_slice( $top, 0, 5, true ) as $key => $row ) : ?>
					<li><?php echo esc_html( sprintf( '%1$s — %2$s نمایش، %3$s کلیک', hodima_seo_discover_key_label( (string) $key ), number_format_i18n( $row['impressions'] ), number_format_i18n( $row['clicks'] ) ) ); ?></li>
				<?php endforeach; ?>
			</ol>
		<?php endif; ?>
		<p><a href="<?php echo esc_url( $url ); ?>">گزارش کامل گوگل دیسکاور</a></p>
	</div>
	<?php
}

// رنگ هشدار ابزارک (فقط صفحه پیشخوان، زیر همان ابزارک)
add_action( 'admin_head-index.php', static function (): void {
	echo '<style>#hodima_discover_widget .hodima-dw__alert{color:#9a5b00;font-weight:700}#hodima_discover_widget .hodima-dw__new{color:#1f7a4d}</style>';
} );

/* =====================================================================
 * خلاصه هفتگی با ایمیل
 * ===================================================================== */

/** زمان‌بندی هفتگی فقط وقتی روشن است و کلید هست؛ وگرنه پاک. */
function hodima_seo_discover_digest_schedule(): void {
	$want      = hodima_seo_discover_option( 'digest' ) && '' !== hodima_seo_discover_sc_key_json();
	$scheduled = (bool) wp_next_scheduled( HODIMA_SEO_DISCOVER_DIGEST_CRON );
	if ( $want && ! $scheduled ) {
		wp_schedule_event( time() + DAY_IN_SECONDS, 'weekly', HODIMA_SEO_DISCOVER_DIGEST_CRON );
	} elseif ( ! $want && $scheduled ) {
		wp_clear_scheduled_hook( HODIMA_SEO_DISCOVER_DIGEST_CRON );
	}
}
add_action( 'admin_init', 'hodima_seo_discover_digest_schedule' );

/** متن HTML خلاصه هفتگی (راست‌به‌چپ؛ فقط داده ذخیره‌شده). */
function hodima_seo_discover_digest_html(): string {

	$stats   = hodima_seo_discover_stats();
	$week    = hodima_seo_discover_week_totals();
	$alerts  = hodima_seo_discover_alerts();
	$n       = static fn( int $v ): string => esc_html( number_format_i18n( $v ) );
	$ctr     = static fn( array $t ): string => $t['impressions'] ? esc_html( number_format_i18n( 100 * $t['clicks'] / $t['impressions'], 1 ) . '٪' ) : '—';
	$change  = hodima_seo_discover_change( $week['now']['impressions'], $week['before']['impressions'] ?: null );
	$since   = time() - WEEK_IN_SECONDS;
	$changes = function_exists( 'hodima_seo_discover_changes' ) ? array_filter( hodima_seo_discover_changes(), static fn( array $c ): bool => $c['t'] >= $since ) : [];
	$top     = $stats['rows'];
	uasort( $top, static fn( array $a, array $b ): int => $b['impressions'] <=> $a['impressions'] );

	$html  = '<div dir="rtl" style="font-family:Tahoma,sans-serif;line-height:1.9;color:#1d2340">';
	$html .= '<h2 style="color:#25316a">خلاصه هفتگی گوگل دیسکاور — ' . esc_html( (string) get_bloginfo( 'name' ) ) . '</h2>';
	$html .= '<p><strong>۷ روز آخر:</strong> ' . $n( $week['now']['impressions'] ) . ' نمایش، ' . $n( $week['now']['clicks'] ) . ' کلیک، نرخ کلیک ' . $ctr( $week['now'] );
	$html .= null !== $change ? ' (' . esc_html( hodima_seo_discover_change_text( $change ) ) . ' نسبت به ۷ روز قبل)' : '';
	$html .= '</p>';

	if ( null !== $alerts['drop'] ) {
		$html .= '<p style="color:#9a5b00"><strong>هشدار:</strong> ' . esc_html( hodima_seo_discover_drop_text( $alerts['drop'] ) ) . '</p>';
	}
	if ( $alerts['new'] ) {
		$html .= '<p style="color:#1f7a4d">' . esc_html( hodima_seo_discover_new_text( $alerts['new'] ) ) . '</p>';
	}
	if ( $top ) {
		$html .= '<p><strong>پرنمایش‌ترین صفحه‌ها (۲۸ روز):</strong></p><ol>';
		foreach ( array_slice( $top, 0, 5, true ) as $key => $row ) {
			$html .= '<li>' . esc_html( hodima_seo_discover_key_label( (string) $key ) ) . ' — ' . $n( $row['impressions'] ) . ' نمایش، ' . $n( $row['clicks'] ) . ' کلیک</li>';
		}
		$html .= '</ol>';
	}
	if ( $changes ) {
		$html .= '<p><strong>تغییرهای کارت این هفته:</strong></p><ul>';
		foreach ( $changes as $c ) {
			$html .= '<li>' . esc_html( hodima_seo_discover_change_entry_text( $c ) ) . '</li>';
		}
		$html .= '</ul>';
	}

	$url   = admin_url( 'admin.php?page=hodima-discover&tab=stats' );
	$html .= '<p><a href="' . esc_url( $url ) . '">گزارش کامل در پیشخوان</a></p>';
	$html .= '<p style="color:#5d6785;font-size:12px">این ایمیل را «ابزارهای هدیما ← گوگل دیسکاور ← تنظیمات ← اعلان‌ها» می‌فرستد و همان‌جا خاموش می‌شود.</p></div>';

	return $html;
}

/** فرستادن خلاصه هفتگی (کرون). */
function hodima_seo_discover_send_digest(): bool {

	if ( ! hodima_seo_discover_option( 'digest' ) || ! hodima_seo_discover_stats()['fetched'] ) {
		return false;
	}

	$to = (string) hodima_seo_discover_option( 'digest_email' );
	$to = '' !== $to ? $to : (string) get_option( 'admin_email' );

	return wp_mail( $to, 'خلاصه هفتگی گوگل دیسکاور — ' . wp_specialchars_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES ), hodima_seo_discover_digest_html(), [ 'Content-Type: text/html; charset=UTF-8' ] );
}
add_action( HODIMA_SEO_DISCOVER_DIGEST_CRON, static function (): void {
	hodima_seo_discover_send_digest();
} );
