<?php
/**
 * کالبد ثابت صفحه‌های پیشخوان SEO (سربرگ ← آمار ← کنش اصلی ← محتوا)
 * Path: inc/ui-skeleton.php
 *
 * قانون ۸ CLAUDE.md و اسکیل hodima-admin-ui «کالبد ثابت صفحه»: ماژول HTML کادر را از
 * صفر نمی‌چیند و فقط خانه‌های این کالبد را پر می‌کند. استایل و رفتار از کیت همین
 * افزونه می‌آید (ui/ui-kit.css و ui-kit.js؛ منبع tools/ui-kit، کپی را bin/build.sh
 * می‌نویسد، پیشوند hds-) و به Core تکیه ندارد؛ پس بدون Core هم صفحه کامل است.
 * نام همه تابع‌ها hodima_seo_ui_ است تا با افزونه‌های دیگر برخورد نکند.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** کیت UI همین افزونه را در صفحه لود می‌کند (فقط صفحه‌ای که این تابع را صدا بزند). */
function hodima_seo_ui_enqueue(): void {

	foreach ( [ 'css' => 'style', 'js' => 'script' ] as $ext => $kind ) {
		$file = HODIMA_SEO_DIR . '/ui/ui-kit.' . $ext;
		$ver  = is_file( $file ) ? (string) filemtime( $file ) : HODIMA_SEO_VERSION;
		$url  = HODIMA_SEO_URL . '/ui/ui-kit.' . $ext;

		if ( 'style' === $kind ) {
			wp_enqueue_style( 'hodima-seo-ui', $url, [], $ver );
		} else {
			wp_enqueue_script( 'hodima-seo-ui', $url, [], $ver, true );
		}
	}

	// اجزای مخصوص SEO که کیت مشترک ندارد (صفحه‌بندی، پنجره): فایل خود افزونه، نه tools/ui-kit، تا بسته‌های دیگر عوض نشوند
	$extras = HODIMA_SEO_DIR . '/inc/ui-extras.css';
	wp_enqueue_style( 'hodima-seo-ui-extras', HODIMA_SEO_URL . '/inc/ui-extras.css', [ 'hodima-seo-ui' ], is_file( $extras ) ? (string) filemtime( $extras ) : HODIMA_SEO_VERSION );
}

/**
 * شروع صفحه: ریشه کیت + سربرگ. هشدارهای وردپرس بعد از `wp-header-end` جابه‌جا می‌شوند،
 * پس پیام‌های صفحه را بعد از این تابع و پیش از hodima_seo_ui_body_open() بنویس.
 *
 * @param array{title:string, description?:string, icon?:string, badge?:string, class?:string, actions?:string} $args actions = HTML امن‌شده دکمه‌های آخر سربرگ (مثلا «تنظیمات»؛ یک بار برای کل صفحه، نه تکرار در هر تب)
 */
function hodima_seo_ui_open( array $args ): void {

	$icon  = (string) ( $args['icon'] ?? 'dashicons-admin-generic' );
	$badge = (string) ( $args['badge'] ?? 'ابزارهای هدیما' );
	$desc  = (string) ( $args['description'] ?? '' );

	printf( '<div class="wrap hds-ui hds-page %s">', esc_attr( (string) ( $args['class'] ?? '' ) ) );
	?>
	<header class="hds-hero">
		<span class="hds-hero__icon dashicons <?php echo esc_attr( $icon ); ?>" aria-hidden="true"></span>
		<div>
			<?php if ( '' !== $badge ) : ?>
				<span class="hds-hero__badge"><?php echo esc_html( $badge ); ?></span>
			<?php endif; ?>
			<h1 class="hds-hero__title"><?php echo esc_html( (string) $args['title'] ); ?></h1>
			<?php if ( '' !== $desc ) : ?>
				<p class="hds-hero__desc"><?php echo esc_html( $desc ); ?></p>
			<?php endif; ?>
		</div>
		<?php if ( '' !== (string) ( $args['actions'] ?? '' ) ) : ?>
			<div class="hds-hero__actions"><?php echo $args['actions']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML ساخته‌شده و escape‌شده در فراخواننده ?></div>
		<?php endif; ?>
	</header>
	<hr class="wp-header-end">
	<?php
}

/** بدنه صفحه (بعد از پیام‌ها) و بستن آن. */
function hodima_seo_ui_body_open(): void {
	echo '<div class="hds-page__body">';
}

function hodima_seo_ui_close(): void {
	echo '</div></div>';
}

/**
 * ردیف آمار: ۲ تا ۴ کارت هم‌اندازه. مقدار و توضیح زیر کارت HTML امن‌شده‌اند (نشان، code).
 *
 * @param array<int, array{label:string, value:string, sub?:string, empty?:bool}> $items
 */
function hodima_seo_ui_stats( array $items, string $label = 'خلاصه وضعیت' ): void {

	echo '<ul class="hds-stats" aria-label="' . esc_attr( $label ) . '">';

	foreach ( $items as $item ) {
		printf(
			'<li class="hds-stat"><span class="hds-stat__label">%1$s</span><span class="hds-stat__value"%2$s>%3$s</span>%4$s</li>',
			esc_html( $item['label'] ),
			! empty( $item['empty'] ) ? ' data-empty' : '',
			wp_kses_post( $item['value'] ),
			isset( $item['sub'] ) ? '<span class="hds-stat__sub">' . wp_kses_post( $item['sub'] ) . '</span>' : ''
		);
	}

	echo '</ul>';
}

/** شروع کارت: عنوان با آیکون و توضیح یک‌خطی. */
function hodima_seo_ui_card_open( string $title, string $desc = '', string $icon = '', string $attrs = '' ): void {

	printf( '<section class="hds-card"%s>', '' !== $attrs ? ' ' . $attrs : '' ); // phpcs:ignore WordPress.Security.EscapeOutput -- فقط نام data-… ثابت از کد
	printf(
		'<h2 class="hds-card__title">%s%s</h2>',
		'' !== $icon ? '<span class="dashicons ' . esc_attr( $icon ) . '" aria-hidden="true"></span>' : '',
		esc_html( $title )
	);

	if ( '' !== $desc ) {
		echo '<p class="hds-card__desc">' . wp_kses_post( $desc ) . '</p>';
	}
}

function hodima_seo_ui_card_close(): void {
	echo '</section>';
}

/** حالت خالی یا «همه سالم» با تیک سبز. */
function hodima_seo_ui_empty( string $title, string $text = '' ): void {
	printf(
		'<div class="hds-empty"><span class="hds-empty__icon dashicons dashicons-yes" aria-hidden="true"></span><div><strong class="hds-empty__title">%s</strong>%s</div></div>',
		esc_html( $title ),
		'' !== $text ? '<span class="hds-empty__text">' . wp_kses_post( $text ) . '</span>' : ''
	);
}

/**
 * نوار تب‌های یک کارت (کیت: ARIA، فلش، Home/End) و پنل‌ها. هر پنل یک بسته خروجی است.
 * بدون JS تب‌ها پنهان و همه پنل‌ها پشت‌سرهم دیده می‌شوند.
 *
 * @param array<string, array{label:string, count?:string, html:callable}> $tabs کلید = شناسه تب
 */
function hodima_seo_ui_tabs( string $id, string $label, array $tabs, string $active = '' ): void {

	$active = isset( $tabs[ $active ] ) ? $active : (string) array_key_first( $tabs );

	echo '<div class="hds-tabset" data-hds-tabset>';
	printf( '<div class="hds-tablist" role="tablist" aria-label="%s" hidden>', esc_attr( $label ) );

	foreach ( $tabs as $key => $tab ) {
		$count = (string) ( $tab['count'] ?? '' );
		printf(
			'<button type="button" class="hds-tab" role="tab" id="%1$s-tab-%2$s" aria-controls="%1$s-panel-%2$s" aria-selected="%3$s">%4$s%5$s</button>',
			esc_attr( $id ),
			esc_attr( (string) $key ),
			(string) $key === $active ? 'true' : 'false',
			esc_html( $tab['label'] ),
			'' !== $count ? ' <span class="hds-tab__count">' . esc_html( $count ) . '</span>' : ''
		);
	}

	echo '</div>';

	foreach ( $tabs as $key => $tab ) {
		printf(
			'<div class="hds-tabpanel" role="tabpanel" id="%1$s-panel-%2$s" aria-labelledby="%1$s-tab-%2$s">',
			esc_attr( $id ),
			esc_attr( (string) $key )
		);
		( $tab['html'] )();
		echo '</div>';
	}

	echo '</div>';
}

/**
 * صفحه‌بندی مدرن: «قبلی»، شماره‌ها (اول، آخر و همسایه‌های صفحه جاری، با «…»)، «بعدی». صفحه جاری پر شده
 * (رنگ اصلی)؛ بدون JS کار می‌کند. جایگزین paginate_links / فهرست شماره‌های دستی.
 *
 * @param callable(int): string $url آدرس هر صفحه (صفحه ۱ هم می‌آید)
 */
function hodima_seo_ui_pager( int $current, int $total, callable $url, string $label = 'صفحه‌بندی' ): void {

	if ( $total < 2 ) {
		return;
	}

	$current = max( 1, min( $total, $current ) );
	$shown   = array_values( array_unique( array_filter( [ 1, $total, $current - 1, $current, $current + 1 ], static fn( int $i ): bool => $i >= 1 && $i <= $total ) ) );
	sort( $shown );
	$n = static fn( int $i ): string => number_format_i18n( $i );

	printf( '<nav class="hds-pager" aria-label="%s">', esc_attr( $label ) );

	if ( $current > 1 ) {
		printf( '<a class="hds-pager__nav" rel="prev" href="%s">قبلی</a>', esc_url( $url( $current - 1 ) ) );
	}

	$prev = 0;
	foreach ( $shown as $i ) {
		if ( $i - $prev > 1 ) {
			echo '<span class="hds-pager__gap" aria-hidden="true">…</span>';
		}
		if ( $i === $current ) {
			printf( '<span class="hds-pager__page is-current" aria-current="page"><span class="screen-reader-text">صفحه </span>%s</span>', esc_html( $n( $i ) ) );
		} else {
			printf( '<a class="hds-pager__page" href="%1$s"><span class="screen-reader-text">صفحه </span>%2$s</a>', esc_url( $url( $i ) ), esc_html( $n( $i ) ) );
		}
		$prev = $i;
	}

	if ( $current < $total ) {
		printf( '<a class="hds-pager__nav" rel="next" href="%s">بعدی</a>', esc_url( $url( $current + 1 ) ) );
	}

	echo '</nav>';
}
