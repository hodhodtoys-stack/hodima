<?php
/**
 * صفحه «آمار» نوتیفیکیشن‌ها (تب کنار فهرست و افزودن).
 *
 * تا 2.0.4 آمار فقط در کادر صفحه ویرایش هر نوتیفیکیشن بود؛ حالا همه در یک
 * صفحه: بازه زمانی، جمع با مقایسه دوره قبل، نمودار روزانه و جدول هر
 * نوتیفیکیشن. آدرس: admin.php?page=hodima-notification-stats
 *
 * @package Hodima\Media
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/notification-stats.php';

/** نامک صفحه آمار. */
const HODIMA_NOTIFICATION_STATS_PAGE = 'hodima-notification-stats';

/** بازه‌های قابل انتخاب (روز). */
const HODIMA_NOTIFICATION_RANGES = [
	'7'   => '۷ روز',
	'30'  => '۳۰ روز',
	'90'  => '۹۰ روز',
	'365' => 'یک سال',
];

/**
 * آدرس صفحه آمار.
 *
 * @param array<string, string|int> $args پارامترها.
 */
function hodima_notification_stats_url( array $args = [] ): string {
	return add_query_arg( array_merge( [ 'page' => HODIMA_NOTIFICATION_STATS_PAGE ], $args ), admin_url( 'admin.php' ) );
}

/**
 * تب‌های مشترک صفحه‌های نوتیفیکیشن (فهرست، افزودن، آمار).
 *
 * @return array<string, array{label: string, url: string, icon: string}>
 */
function hodima_notification_admin_tabs(): array {
	return [
		'list'  => [
			'label' => 'نوتیفیکیشن‌ها',
			'url'   => admin_url( 'edit.php?post_type=' . HODIMA_NOTIFICATION_POST_TYPE ),
			'icon'  => 'dashicons-list-view',
		],
		'new'   => [
			'label' => 'افزودن',
			'url'   => admin_url( 'post-new.php?post_type=' . HODIMA_NOTIFICATION_POST_TYPE ),
			'icon'  => 'dashicons-plus-alt2',
		],
		'stats' => [
			'label' => 'آمار',
			'url'   => hodima_notification_stats_url(),
			'icon'  => 'dashicons-chart-bar',
		],
	];
}

add_action( 'admin_menu', 'hodima_notification_stats_menu', 30 );

/**
 * ثبت صفحه. با Hodima Core زیر «ابزارهای هدیما» ثبت و فقط از *نمایش* منو
 * برداشته می‌شود (یک تب کنار فهرست است؛ همان روش صفحه‌های اسکیما) و منوی
 * «نوتیفیکیشن‌ها» روشن می‌ماند. بدون Core زیرمنوی «آمار» همان منو.
 */
function hodima_notification_stats_menu(): void {
	$hub    = function_exists( 'hodima_admin_menu_parent' ) ? hodima_admin_menu_parent() : '';
	$list   = 'edit.php?post_type=' . HODIMA_NOTIFICATION_POST_TYPE;
	$parent = '' !== $hub ? $hub : $list;
	$hook   = add_submenu_page( $parent, 'آمار نوتیفیکیشن‌ها', 'آمار', 'edit_posts', HODIMA_NOTIFICATION_STATS_PAGE, 'hodima_notification_stats_render' );
	if ( $hook ) {
		add_action( 'admin_print_styles-' . $hook, 'hodima_notification_stats_assets' );
	}
	if ( '' === $hub ) {
		return;
	}
	// زمان مهم است: حذف در admin_menu دسترسی صفحه را می‌برد؛ admin_head درست پیش از چاپ منوست
	add_action(
		'admin_head',
		static function () use ( $hub ): void {
			remove_submenu_page( $hub, HODIMA_NOTIFICATION_STATS_PAGE );
		}
	);
	add_filter(
		'submenu_file',
		static function ( ?string $file ) use ( $list ): ?string {
			global $plugin_page;
			return HODIMA_NOTIFICATION_STATS_PAGE === $plugin_page ? $list : $file;
		}
	);
}

/** CSS/JS صفحه آمار. */
function hodima_notification_stats_assets(): void {
	wp_enqueue_style( 'hd-notification-stats-css', hodima_notification_asset_url( 'css/notification-stats.css' ), [], hodima_notification_asset_version( 'css/notification-stats.css' ) );
	wp_enqueue_script( 'hd-notification-stats-js', hodima_notification_asset_url( 'js/notification-stats.js' ), [], hodima_notification_asset_version( 'js/notification-stats.js' ), [ 'in_footer' => true ] );
}

add_action( 'admin_post_hodima_notif_stats_reset', 'hodima_notification_stats_reset_action' );

/** صفر کردن آمار یک نوتیفیکیشن از جدول صفحه آمار. */
function hodima_notification_stats_reset_action(): void {
	$id = isset( $_POST['post'] ) ? absint( $_POST['post'] ) : 0;
	check_admin_referer( 'hodima_notif_stats_reset_' . $id );
	if ( HODIMA_NOTIFICATION_POST_TYPE !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) ) {
		wp_die( esc_html( 'اجازه این کار را ندارید.' ), 403 );
	}
	hodima_notification_stats_reset( $id );
	if ( function_exists( 'hodima_admin_flash' ) ) {
		hodima_admin_flash( sprintf( 'آمار «%s» صفر شد.', get_the_title( $id ) ) );
	}
	wp_safe_redirect( wp_get_referer() ?: hodima_notification_stats_url() );
	exit;
}

/**
 * پارامترهای صفحه (بازه، نوتیفیکیشن، مرتب‌سازی) از آدرس.
 *
 * @return array{range: string, notif: int, orderby: string, order: string}
 */
function hodima_notification_stats_query(): array {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- فقط فیلتر نمایش
	$range   = isset( $_GET['range'] ) ? sanitize_key( wp_unslash( $_GET['range'] ) ) : '30';
	$notif   = isset( $_GET['notif'] ) ? absint( $_GET['notif'] ) : 0;
	$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'views';
	$order   = isset( $_GET['order'] ) ? sanitize_key( wp_unslash( $_GET['order'] ) ) : 'desc';
	// phpcs:enable
	return [
		'range'   => isset( HODIMA_NOTIFICATION_RANGES[ $range ] ) || 'all' === $range ? $range : '30',
		'notif'   => HODIMA_NOTIFICATION_POST_TYPE === get_post_type( $notif ) ? $notif : 0,
		'orderby' => in_array( $orderby, [ 'title', 'views', 'clicks', 'ctr', 'closes' ], true ) ? $orderby : 'views',
		'order'   => 'asc' === $order ? 'asc' : 'desc',
	];
}

/** نرخ کلیک (درصد، یک رقم اعشار). */
function hodima_notification_ctr( int $views, int $clicks ): float {
	return $views > 0 ? round( 100 * $clicks / $views, 1 ) : 0.0;
}

/** عدد کوتاه برای محور (۱٫۲K). */
function hodima_notification_compact( int $n ): string {
	return $n >= 1000 ? number_format_i18n( $n / 1000, $n >= 10000 ? 0 : 1 ) . 'K' : number_format_i18n( $n );
}

/** محتوای صفحه آمار. */
function hodima_notification_stats_render(): void {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}
	hodima_notification_daily_cleanup();

	$q     = hodima_notification_stats_query();
	$today = current_time( 'Y-m-d' );
	$first = hodima_notification_daily_first_day();
	$days  = 'all' === $q['range'] ? 0 : (int) $q['range'];
	$from  = $days ? wp_date( 'Y-m-d', strtotime( $today . ' 12:00 UTC' ) - ( $days - 1 ) * DAY_IN_SECONDS, new DateTimeZone( 'UTC' ) ) : '';
	$prev  = $days ? [ wp_date( 'Y-m-d', strtotime( $from . ' 12:00 UTC' ) - $days * DAY_IN_SECONDS, new DateTimeZone( 'UTC' ) ), wp_date( 'Y-m-d', strtotime( $from . ' 12:00 UTC' ) - DAY_IN_SECONDS, new DateTimeZone( 'UTC' ) ) ] : [];

	$posts = get_posts(
		[
			'post_type'      => HODIMA_NOTIFICATION_POST_TYPE,
			'post_status'    => [ 'publish', 'future', 'draft', 'pending', 'private' ],
			'posts_per_page' => 100,
			'no_found_rows'  => true,
		]
	);

	// جمع هر نوتیفیکیشن در بازه (همه = شمارنده‌های کل، از پیش از آمار روزانه هم)
	$range_totals = $days ? hodima_notification_range_totals( $from, $today ) : [];
	$rows         = [];
	foreach ( $posts as $post ) {
		$t      = $days ? ( $range_totals[ $post->ID ] ?? [ 'views' => 0, 'clicks' => 0, 'closes' => 0 ] ) : hodima_notification_stats( $post->ID );
		$rows[] = [
			'post'   => $post,
			'title'  => get_the_title( $post ),
			'views'  => (int) $t['views'],
			'clicks' => (int) $t['clicks'],
			'closes' => (int) $t['closes'],
			'ctr'    => hodima_notification_ctr( (int) $t['views'], (int) $t['clicks'] ),
		];
	}
	usort(
		$rows,
		static function ( array $a, array $b ) use ( $q ): int {
			$cmp = 'title' === $q['orderby'] ? strnatcasecmp( $a['title'], $b['title'] ) : $a[ $q['orderby'] ] <=> $b[ $q['orderby'] ];
			return 'asc' === $q['order'] ? $cmp : -$cmp;
		}
	);

	// جمع بالای صفحه: یک نوتیفیکیشن یا همه
	$scope = $q['notif'] ? array_values( array_filter( $rows, static fn( array $r ): bool => $r['post']->ID === $q['notif'] ) ) : $rows;
	$sum   = static fn( array $items, string $key ): int => (int) array_sum( array_column( $items, $key ) );
	$total = [
		'views'  => $sum( $scope, 'views' ),
		'clicks' => $sum( $scope, 'clicks' ),
		'closes' => $sum( $scope, 'closes' ),
	];
	$prev_total = null;
	if ( $prev ) {
		$prev_rows  = hodima_notification_range_totals( $prev[0], $prev[1] );
		$prev_rows  = $q['notif'] ? array_intersect_key( $prev_rows, [ $q['notif'] => true ] ) : $prev_rows;
		$prev_total = [
			'views'  => $sum( $prev_rows, 'views' ),
			'clicks' => $sum( $prev_rows, 'clicks' ),
			'closes' => $sum( $prev_rows, 'closes' ),
		];
	}

	// نمودار: بازه انتخابی؛ «همه» = از اولین روز آمار روزانه (حداکثر یک سال)
	$chart_from = $days ? $from : ( '' !== $first ? max( $first, wp_date( 'Y-m-d', time() - 364 * DAY_IN_SECONDS ) ) : $today );
	$daily      = hodima_notification_daily( $q['notif'] ? [ $q['notif'] ] : [], $chart_from, $today );

	hodima_admin_page_open(
		[
			'title'       => 'آمار نوتیفیکیشن‌ها',
			'description' => 'چند بار هر پاپ‌آپ دیده شد، چند نفر رویش کلیک کردند و چند بار بسته شد.',
			'icon'        => 'dashicons-chart-bar',
			'tabs'        => hodima_notification_admin_tabs(),
			'current'     => 'stats',
			'tabs_label'  => 'بخش‌های نوتیفیکیشن',
		],
		'hdn-stats-page'
	);
	?>
	<form class="hdn-filter" method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
		<input type="hidden" name="page" value="<?php echo esc_attr( HODIMA_NOTIFICATION_STATS_PAGE ); ?>">
		<div class="hdn-filter__ranges" role="group" aria-label="بازه زمانی">
			<?php foreach ( HODIMA_NOTIFICATION_RANGES + [ 'all' => 'همه' ] as $key => $label ) : ?>
				<a class="button<?php echo (string) $key === $q['range'] ? ' button-primary' : ''; ?>" href="<?php echo esc_url( hodima_notification_stats_url( array_filter( [ 'range' => (string) $key, 'notif' => $q['notif'] ] ) ) ); ?>"<?php echo (string) $key === $q['range'] ? ' aria-current="true"' : ''; ?>><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</div>
		<input type="hidden" name="range" value="<?php echo esc_attr( $q['range'] ); ?>">
		<label class="hdn-filter__notif">
			<span>نوتیفیکیشن</span>
			<select name="notif" data-hdn-autosubmit>
				<option value="0">همه نوتیفیکیشن‌ها</option>
				<?php foreach ( $posts as $post ) : ?>
					<option value="<?php echo (int) $post->ID; ?>" <?php selected( $q['notif'], $post->ID ); ?>><?php echo esc_html( get_the_title( $post ) ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<button type="submit" class="button hdn-filter__go">نمایش</button>
	</form>

	<?php if ( ! $posts ) : ?>
		<div class="hd-card hd-empty">
			<?php echo hodima_admin_icon( 'dashicons-megaphone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside ?>
			<p>هنوز نوتیفیکیشنی ساخته نشده است.</p>
			<a class="button button-primary" href="<?php echo esc_url( hodima_notification_admin_tabs()['new']['url'] ); ?>">افزودن نوتیفیکیشن</a>
		</div>
		<?php
		hodima_admin_page_close();
		return;
	endif;
	?>

	<div class="hd-grid hd-grid--stats hdn-tiles">
		<?php
		hodima_notification_stat_tile( 'نمایش', $total['views'], $prev_total['views'] ?? null );
		hodima_notification_stat_tile( 'کلیک', $total['clicks'], $prev_total['clicks'] ?? null );
		hodima_notification_stat_tile( 'نرخ کلیک', hodima_notification_ctr( $total['views'], $total['clicks'] ), null === $prev_total ? null : hodima_notification_ctr( $prev_total['views'], $prev_total['clicks'] ), true );
		hodima_notification_stat_tile( 'بستن', $total['closes'], $prev_total['closes'] ?? null );
		?>
	</div>
	<?php if ( $prev_total ) : ?>
		<p class="description hdn-tiles__note">
			<?php
			echo esc_html(
				sprintf(
					'مقایسه با %1$s قبل از آن (%2$s تا %3$s).',
					HODIMA_NOTIFICATION_RANGES[ $q['range'] ],
					hodima_notification_format_date( $prev[0] ),
					hodima_notification_format_date( $prev[1] )
				)
			);
			?>
		</p>
	<?php endif; ?>

	<section class="hd-card hdn-charts" aria-labelledby="hdn-charts-title">
		<div class="hd-card__head">
			<?php echo hodima_admin_icon( 'dashicons-chart-area' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside ?>
			<h2 class="hd-card__title" id="hdn-charts-title">روز به روز<?php echo $q['notif'] ? esc_html( ' — ' . get_the_title( $q['notif'] ) ) : ''; ?></h2>
			<p class="hd-card__desc">
				<?php
				echo esc_html(
					'' === $first
						? 'آمار روزانه از نسخه ۲.۰.۵ ثبت می‌شود و هنوز داده‌ای نیست؛ از امروز پر می‌شود.'
						: 'آمار روزانه از ' . hodima_notification_format_date( $first ) . ' ثبت شده است (قبل از آن فقط جمع کل). ماوس یا انگشت را روی ستون‌ها ببرید.'
				);
				?>
			</p>
		</div>
		<div class="hdn-charts__grid">
			<?php
			hodima_notification_chart( 'نمایش روزانه', $daily, 'views' );
			hodima_notification_chart( 'کلیک روزانه', $daily, 'clicks' );
			?>
		</div>
		<details class="hdn-daily-table">
			<summary>جدول روز به روز</summary>
			<div class="hd-table-wrap">
				<table class="widefat striped">
					<thead><tr><th scope="col">روز</th><th scope="col">نمایش</th><th scope="col">کلیک</th><th scope="col">نرخ کلیک</th><th scope="col">بستن</th></tr></thead>
					<tbody>
						<?php foreach ( array_reverse( $daily, true ) as $day => $d ) : ?>
							<tr>
								<th scope="row"><?php echo esc_html( hodima_notification_format_date( $day ) ); ?></th>
								<td><?php echo esc_html( number_format_i18n( $d['views'] ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $d['clicks'] ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( hodima_notification_ctr( $d['views'], $d['clicks'] ), 1 ) ); ?>٪</td>
								<td><?php echo esc_html( number_format_i18n( $d['closes'] ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</details>
	</section>

	<section class="hd-card hdn-table" aria-labelledby="hdn-table-title">
		<div class="hd-card__head">
			<?php echo hodima_admin_icon( 'dashicons-list-view' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside ?>
			<h2 class="hd-card__title" id="hdn-table-title">هر نوتیفیکیشن (<?php echo esc_html( 'all' === $q['range'] ? 'از ابتدا' : HODIMA_NOTIFICATION_RANGES[ $q['range'] ] . ' اخیر' ); ?>)</h2>
		</div>
		<div class="hd-table-wrap">
			<table class="widefat striped hdn-table__table">
				<thead>
					<tr>
						<?php
						hodima_notification_sort_th( 'title', 'نوتیفیکیشن', $q );
						hodima_notification_sort_th( 'views', 'نمایش', $q );
						hodima_notification_sort_th( 'clicks', 'کلیک', $q );
						hodima_notification_sort_th( 'ctr', 'نرخ کلیک', $q );
						hodima_notification_sort_th( 'closes', 'بستن', $q );
						?>
						<th scope="col"><span class="screen-reader-text">کارها</span></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $r ) : ?>
						<?php
						$post   = $r['post'];
						$meta   = hodima_notification_meta( $post->ID );
						$thumb  = (int) $meta['hd_notif_bg_image_id'] > 0 ? wp_get_attachment_image_url( (int) $meta['hd_notif_bg_image_id'], 'thumbnail' ) : false;
						$thumb  = $thumb ? $thumb : $meta['hd_notif_bg_image'];
						$state  = hodima_notification_display_state( $post->ID );
						$active = $q['notif'] === $post->ID;
						?>
						<tr<?php echo $active ? ' class="is-selected"' : ''; ?>>
							<th scope="row" class="hdn-table__name">
								<span class="hdn-thumb<?php echo '1' === $meta['hd_notif_bg_transparent'] ? ' is-transparent' : ''; ?>" aria-hidden="true">
									<?php if ( '' !== $thumb ) : ?>
										<img src="<?php echo esc_url( (string) $thumb ); ?>" alt="" loading="lazy" decoding="async">
									<?php endif; ?>
								</span>
								<span class="hdn-table__title">
									<a href="<?php echo esc_url( hodima_notification_stats_url( [ 'range' => $q['range'], 'notif' => $post->ID ] ) ); ?>"<?php echo $active ? ' aria-current="true"' : ''; ?>><?php echo esc_html( $r['title'] ); ?></a>
									<span class="hd-pill hd-pill--<?php echo esc_attr( $state[0] ); ?>"><?php echo esc_html( $state[1] ); ?></span>
								</span>
							</th>
							<td><?php echo esc_html( number_format_i18n( $r['views'] ) ); ?></td>
							<td><?php echo esc_html( number_format_i18n( $r['clicks'] ) ); ?></td>
							<td><?php echo esc_html( number_format_i18n( $r['ctr'], 1 ) ); ?>٪</td>
							<td><?php echo esc_html( number_format_i18n( $r['closes'] ) ); ?></td>
							<td class="hdn-table__actions">
								<a class="button button-small" href="<?php echo esc_url( (string) get_edit_post_link( $post->ID ) ); ?>">ویرایش<span class="screen-reader-text"> «<?php echo esc_html( $r['title'] ); ?>»</span></a>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-hdn-confirm="<?php echo esc_attr( sprintf( 'همه آمار «%s» (کل و روزانه) صفر شود؟ این کار برگشت ندارد.', $r['title'] ) ); ?>">
									<input type="hidden" name="action" value="hodima_notif_stats_reset">
									<input type="hidden" name="post" value="<?php echo (int) $post->ID; ?>">
									<?php wp_nonce_field( 'hodima_notif_stats_reset_' . $post->ID ); ?>
									<button type="submit" class="button button-small button-link-delete">صفر کردن<span class="screen-reader-text"> آمار «<?php echo esc_html( $r['title'] ); ?>»</span></button>
								</form>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</section>

	<dialog class="hd-dialog hdn-confirm" aria-labelledby="hdn-confirm-text" data-hdn-dialog>
		<p id="hdn-confirm-text" data-hdn-dialog-text></p>
		<div class="hdn-confirm__actions">
			<button type="button" class="button button-primary" data-hdn-dialog-ok>بله، صفر شود</button>
			<button type="button" class="button" data-hdn-dialog-cancel>انصراف</button>
		</div>
	</dialog>
	<?php
	hodima_admin_page_close();
}

/**
 * یک کاشی آمار با تغییر نسبت به دوره قبل.
 *
 * @param int|float      $value   مقدار.
 * @param int|float|null $before  مقدار دوره قبل (null = بدون مقایسه).
 * @param bool           $percent درصد است (تغییر به «واحد درصد»).
 */
function hodima_notification_stat_tile( string $label, int|float $value, int|float|null $before, bool $percent = false ): void {
	$text  = $percent ? number_format_i18n( (float) $value, 1 ) . '٪' : number_format_i18n( (int) $value );
	$delta = '';
	$dir   = 'flat';
	if ( null !== $before ) {
		$diff = $value - $before;
		$dir  = $diff > 0 ? 'up' : ( $diff < 0 ? 'down' : 'flat' );
		if ( $percent ) {
			$delta = 0.0 === (float) $diff ? 'بدون تغییر' : sprintf( '%s%s واحد درصد', $diff > 0 ? '+' : '−', number_format_i18n( abs( (float) $diff ), 1 ) );
		} elseif ( $before > 0 ) {
			$delta = 0 === (int) $diff ? 'بدون تغییر' : sprintf( '%s%s٪', $diff > 0 ? '+' : '−', number_format_i18n( abs( 100 * $diff / $before ), 0 ) );
		} else {
			$delta = $value > 0 ? 'تازه' : 'بدون تغییر';
		}
	}
	?>
	<div class="hd-stat">
		<span class="hd-stat__label"><?php echo esc_html( $label ); ?></span>
		<span class="hd-stat__value"><?php echo esc_html( $text ); ?></span>
		<?php if ( '' !== $delta ) : ?>
			<span class="hdn-delta hdn-delta--<?php echo esc_attr( $dir ); ?>">
				<?php echo hodima_admin_icon( 'up' === $dir ? 'dashicons-arrow-up-alt' : ( 'down' === $dir ? 'dashicons-arrow-down-alt' : 'dashicons-minus' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inside ?>
				<?php echo esc_html( $delta ); ?><span class="screen-reader-text"> نسبت به دوره قبل</span>
			</span>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * سرستون قابل مرتب‌سازی.
 *
 * @param array{range: string, notif: int, orderby: string, order: string} $q پارامترها.
 */
function hodima_notification_sort_th( string $key, string $label, array $q ): void {
	$current = $q['orderby'] === $key;
	$next    = $current && 'desc' === $q['order'] ? 'asc' : 'desc';
	$url     = hodima_notification_stats_url( array_filter( [ 'range' => $q['range'], 'notif' => $q['notif'], 'orderby' => $key, 'order' => $next ] ) );
	$sort    = $current ? ( 'asc' === $q['order'] ? 'ascending' : 'descending' ) : 'none';
	printf(
		'<th scope="col" aria-sort="%1$s" class="hdn-sort%2$s"><a href="%3$s">%4$s<span class="hdn-sort__icon" aria-hidden="true"></span></a></th>',
		esc_attr( $sort ),
		$current ? ' is-' . esc_attr( $q['order'] ) : '',
		esc_url( $url ),
		esc_html( $label )
	);
}

/**
 * نمودار ستونی روزانه یک سنجه (SVG سمت سرور؛ راهنما با JS، جدول زیرش).
 *
 * @param array<string, array{views: int, clicks: int, closes: int}> $daily روزها.
 * @param string                                                     $key   views یا clicks.
 */
function hodima_notification_chart( string $title, array $daily, string $key ): void {
	$values = array_map( static fn( array $d ): int => (int) $d[ $key ], $daily );
	$count  = max( 1, count( $values ) );
	$max    = max( 1, ...array_values( $values ) );
	// محور: عدد گرد بالای بیشینه (۱، ۲، ۵ × ۱۰ⁿ)
	$step = 10 ** max( 0, (int) floor( log10( $max ) ) );
	foreach ( [ 1, 2, 5, 10 ] as $m ) {
		if ( $m * $step >= $max ) {
			$top = $m * $step;
			break;
		}
	}
	$top   = $top ?? $max;
	$w     = 640;
	$h     = 180;
	$pad_t = 8;
	$pad_b = 22;
	$pad_s = 36; // جای عددهای محور (سمت راست در راست‌به‌چپ)
	$plot  = $w - $pad_s;
	$slot  = $plot / $count;
	$bar   = max( 2.0, min( 24.0, $slot - 2 ) );
	$sum   = array_sum( $values );
	$id    = 'hdn-chart-' . $key;
	?>
	<figure class="hdn-chart" aria-labelledby="<?php echo esc_attr( $id ); ?>-title">
		<figcaption class="hdn-chart__head">
			<span class="hdn-chart__title" id="<?php echo esc_attr( $id ); ?>-title"><?php echo esc_html( $title ); ?></span>
			<span class="hdn-chart__sum"><?php echo esc_html( 'جمع ' . number_format_i18n( $sum ) ); ?></span>
		</figcaption>
		<div class="hdn-chart__plot">
			<svg viewBox="0 0 <?php echo (int) $w; ?> <?php echo (int) $h; ?>" role="img" aria-label="<?php echo esc_attr( sprintf( '%s: جمع %s در %s روز، بیشترین %s در یک روز. جزئیات در «جدول روز به روز».', $title, number_format_i18n( $sum ), number_format_i18n( $count ), number_format_i18n( $max ) ) ); ?>" preserveAspectRatio="none">
				<?php foreach ( [ 0, 0.5, 1 ] as $f ) : ?>
					<?php $y = $pad_t + ( $h - $pad_t - $pad_b ) * ( 1 - $f ); ?>
					<line class="hdn-chart__grid" x1="0" x2="<?php echo (int) $plot; ?>" y1="<?php echo esc_attr( (string) round( $y, 1 ) ); ?>" y2="<?php echo esc_attr( (string) round( $y, 1 ) ); ?>"></line>
				<?php endforeach; ?>
				<?php
				$i = 0;
				foreach ( $daily as $day => $d ) :
					$v  = (int) $d[ $key ];
					$bh = $v > 0 ? max( 2.0, ( $h - $pad_t - $pad_b ) * $v / $top ) : 0.0;
					// راست‌به‌چپ: امروز سمت چپ
					$x = $plot - ( $i + 1 ) * $slot + ( $slot - $bar ) / 2;
					?>
					<g class="hdn-chart__col" data-day="<?php echo esc_attr( hodima_notification_format_date( $day ) ); ?>" data-value="<?php echo esc_attr( number_format_i18n( $v ) ); ?>">
						<rect class="hdn-chart__hit" x="<?php echo esc_attr( (string) round( $plot - ( $i + 1 ) * $slot, 1 ) ); ?>" y="0" width="<?php echo esc_attr( (string) round( $slot, 1 ) ); ?>" height="<?php echo (int) ( $h - $pad_b ); ?>"></rect>
						<?php if ( $bh > 0 ) : ?>
							<path class="hdn-chart__bar" d="<?php echo esc_attr( hodima_notification_bar_path( $x, $h - $pad_b, $bar, $bh ) ); ?>"></path>
						<?php endif; ?>
					</g>
					<?php
					++$i;
				endforeach;
				?>
				<line class="hdn-chart__base" x1="0" x2="<?php echo (int) $plot; ?>" y1="<?php echo (int) ( $h - $pad_b ); ?>" y2="<?php echo (int) ( $h - $pad_b ); ?>"></line>
			</svg>
			<?php // عددهای محور در HTML (SVG با preserveAspectRatio=none کش می‌آید و متنش کج می‌شد) ?>
			<div class="hdn-chart__ticks" aria-hidden="true">
				<?php foreach ( [ 1, 0.5, 0 ] as $f ) : ?>
					<span style="<?php echo esc_attr( sprintf( 'top:%.2f%%', 100 * ( $pad_t + ( $h - $pad_t - $pad_b ) * ( 1 - $f ) ) / $h ) ); ?>"><?php echo esc_html( hodima_notification_compact( (int) round( $top * $f ) ) ); ?></span>
				<?php endforeach; ?>
			</div>
			<div class="hdn-chart__axis" aria-hidden="true">
				<span><?php echo esc_html( hodima_notification_format_date( (string) array_key_first( $daily ) ) ); ?></span>
				<span><?php echo esc_html( hodima_notification_format_date( (string) array_key_last( $daily ) ) ); ?></span>
			</div>
			<div class="hdn-chart__tip" role="status" hidden></div>
		</div>
	</figure>
	<?php
}

/** مسیر ستون با گوشه گرد ۴px بالا و پایه صاف. */
function hodima_notification_bar_path( float $x, float $base, float $width, float $height ): string {
	$r = min( 4.0, $width / 2, $height );
	$t = $base - $height;
	return sprintf(
		'M%1$.1f %2$.1fV%3$.1fQ%1$.1f %4$.1f %5$.1f %4$.1fH%6$.1fQ%7$.1f %4$.1f %7$.1f %3$.1fV%2$.1fZ',
		$x,
		$base,
		$t + $r,
		$t,
		$x + $r,
		$x + $width - $r,
		$x + $width
	);
}
