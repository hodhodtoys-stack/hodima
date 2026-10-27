<?php
/**
 * ماژول «دستار سئو» — تب «سلامت آدرس‌ها»
 * Path: core/advisor/advisor-health-view.php
 *
 * داده از advisor-health.php. کالبد: ۴ کارت آمار ← کارت «آدرس‌هایی که در گوگل‌اند ولی در سایت مرده‌اند»
 * (جدول با وضعیت و دستور) ← کارت «صفحه‌هایی که نمایش نگرفته‌اند». فقط می‌خواند و دستور می‌دهد؛ چیزی را
 * در سایت عوض نمی‌کند.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * برچسب و دستور هر وضعیت آدرس مرده.
 *
 * @return array{label: string, class: string, advice: string}
 */
function hodima_seo_advisor_health_state( string $state ): array {

	return [
		'missing'     => [ 'label' => '۴۰۴', 'class' => 'hds-pill hds-pill--warn', 'advice' => 'آدرس‌های «۴۰۴» هنوز در گوگل نمایش می‌گیرند ولی در سایت صفحه‌ای ندارند. اگر صفحه جایگزین مناسبی هست، در «ابزارهای هدیما ← ریدایرکت‌ها» با ریدایرکت ۳۰۱ به آن ببرید تا کلیک و اعتبارش هدر نرود. اگر صفحه عمدی حذف شده و جایگزینی ندارد، ۴۰۴ یا ۴۱۰ می‌ماند و گوگل کم‌کم حذفش می‌کند.' ],
		'rule_broken' => [ 'label' => 'ریدایرکت خراب', 'class' => 'hds-pill hds-pill--warn', 'advice' => 'آدرس‌های «ریدایرکت خراب» قانون ریدایرکت دارند ولی مقصدشان ۴۰۴ است. مقصد قانون را در «ریدایرکت‌ها» درست کنید.' ],
		'gone'        => [ 'label' => 'حذف‌شده (۴۰۴/۴۱۰)', 'class' => 'hds-pill hds-pill--info', 'advice' => 'آدرس‌های «حذف‌شده» با قانون عمدا ۴۰۴ یا ۴۱۰ داده می‌شوند؛ اگر جایگزین مناسب پیدا کردید به آن ریدایرکت کنید، وگرنه همین‌طور خوب است.' ],
		'redirect'    => [ 'label' => 'ریدایرکت ۳۰۱', 'class' => 'hds-pill hds-pill--info', 'advice' => 'آدرس‌های «ریدایرکت ۳۰۱» درست کار می‌کنند؛ گوگل کم‌کم آدرس جدید را جایگزین می‌کند. لینک‌های داخلی سایت را هم فقط به آدرس جدید بدهید.' ],
		'rule'        => [ 'label' => 'ریدایرکت (قانون)', 'class' => 'hds-pill hds-pill--info', 'advice' => 'آدرس‌های «ریدایرکت (قانون)» درست کار می‌کنند؛ گوگل کم‌کم آدرس جدید را جایگزین می‌کند. لینک‌های داخلی سایت را هم فقط به آدرس جدید بدهید.' ],
	][ $state ] ?? [ 'label' => $state, 'class' => 'hds-pill', 'advice' => '' ];
}

/**
 * محتوای تب «سلامت آدرس‌ها».
 *
 * @param array<string, mixed> $status خروجی hodima_seo_advisor_status()
 */
function hodima_seo_advisor_render_health( array $status ): void {

	$data   = hodima_seo_advisor_health();
	$n      = static fn( int|float $value ): string => number_format_i18n( $value );
	$empty  = (int) ( $data['computed'] ?? 0 ) <= 0;
	$dead   = (array) ( $data['dead'] ?? [] );
	$unseen = (array) ( $data['unseen'] ?? [] );
	$total  = (int) ( $data['total'] ?? 0 );
	$seen   = (int) ( $data['seen'] ?? 0 );
	$fresh  = (int) ( $data['fresh'] ?? 0 );

	$bad_dead = count( array_filter( $dead, static fn( array $d ): bool => in_array( $d['state'], [ 'missing', 'rule_broken' ], true ) ) );

	hodima_seo_ui_stats( [
		[ 'label' => 'صفحه بدون نمایش', 'value' => $empty ? '—' : esc_html( $n( count( $unseen ) ) ), 'sub' => $empty ? '' : esc_html( sprintf( 'از %s صفحه منتشرشده', $n( $total ) ) ), 'empty' => $empty ],
		[ 'label' => 'صفحه تازه', 'value' => $empty ? '—' : esc_html( $n( $fresh ) ), 'sub' => $empty ? '' : esc_html( sprintf( 'کمتر از %s روز؛ هنوز زود است', $n( HODIMA_SEO_ADVISOR_HEALTH_FRESH_DAYS ) ) ), 'empty' => $empty ],
		[ 'label' => 'آدرس مرده در گوگل', 'value' => $empty ? '—' : esc_html( $n( $bad_dead ) ), 'sub' => $empty ? '' : esc_html( sprintf( 'از %s آدرس نیازمند بررسی', $n( count( $dead ) ) ) ), 'empty' => $empty ],
		[ 'label' => 'پوشش در گوگل', 'value' => $empty || $total <= 0 ? '—' : esc_html( $n( round( $seen / $total * 100 ) ) . '٪' ), 'sub' => $empty || $total <= 0 ? '' : esc_html( sprintf( '%1$s از %2$s صفحه نمایش گرفته', $n( $seen ), $n( $total ) ) ), 'empty' => $empty || $total <= 0 ],
	] );

	if ( $empty ) {
		hodima_seo_ui_card_open( 'سلامت آدرس‌ها' );
		hodima_seo_ui_empty( 'هنوز ساخته نشده', 'بعد از «به‌روزرسانی همه» این بخش پر می‌شود.' );
		hodima_seo_ui_card_close();
		return;
	}

	// آدرس‌های مرده
	hodima_seo_ui_card_open( 'آدرس‌هایی که در گوگل‌اند ولی در سایت مرده‌اند', 'پرنمایش‌ترین آدرس‌های گوگل (تا ۲۰۰ آدرس) که در خود سایت ۴۰۴ هستند یا ریدایرکت می‌شوند؛ مرتب بر اساس اهمیت و نمایش.' );

	if ( empty( $data['router'] ) ) {
		hodima_seo_ui_empty( 'بررسی انجام نشد', 'ماژول «آدرس تمیز» سایت خاموش است و بدون آن وضعیت آدرس‌ها بررسی نمی‌شود. آن را در «ابزارهای هدیما ← ماژول‌های سئو» روشن کنید.' );
	} elseif ( ! $dead ) {
		hodima_seo_ui_empty( 'آدرس مرده‌ای پیدا نشد', 'همه آدرس‌های پرنمایش گوگل در سایت صفحه سالم دارند.' );
	} else {
		?>
		<div class="hds-table-wrap" tabindex="0" role="region" aria-label="آدرس‌های مرده در گوگل">
			<table class="widefat striped hodima-advisor__terms">
				<thead><tr><th scope="col">آدرس</th><th scope="col">نمایش</th><th scope="col">کلیک</th><th scope="col">وضعیت</th><th scope="col">مقصد</th></tr></thead>
				<tbody>
					<?php foreach ( $dead as $row ) : ?>
						<?php $info = hodima_seo_advisor_health_state( (string) $row['state'] ); ?>
						<tr>
							<th scope="row"><?php echo hodima_seo_advisor_url_link( (string) $row['path'], 'hodima-advisor__rival-url' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML ساخته‌شده و escape‌شده در hodima_seo_advisor_url_link ?></th>
							<td><?php echo esc_html( $n( $row['impressions'] ) ); ?></td>
							<td><?php echo esc_html( $n( $row['clicks'] ) ); ?></td>
							<td><span class="<?php echo esc_attr( $info['class'] ); ?>"><?php echo esc_html( $info['label'] ); ?></span></td>
							<td><?php echo '' !== (string) $row['target'] ? hodima_seo_advisor_url_link( (string) $row['target'], 'hodima-advisor__rival-url' ) : '—'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML ساخته‌شده و escape‌شده در hodima_seo_advisor_url_link ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php
		foreach ( array_unique( array_column( $dead, 'state' ) ) as $state ) {
			echo '<div class="hodima-advisor__advice">' . esc_html( hodima_seo_advisor_health_state( (string) $state )['advice'] ) . '</div>';
		}
	}
	hodima_seo_ui_card_close();

	// صفحه‌های بدون نمایش
	hodima_seo_ui_card_open( 'صفحه‌هایی که نمایش نگرفته‌اند', sprintf( 'نوشته، برگه و محصول منتشرشده (غیر noindex، بدون رمز) که در %s روز اخیر هیچ نمایشی در گوگل نداشته‌اند و دست‌کم %s روز از انتشارشان گذشته؛ قدیمی‌ترین اول.', $n( $status['days'] ), $n( HODIMA_SEO_ADVISOR_HEALTH_FRESH_DAYS ) ) );

	if ( ! $unseen ) {
		hodima_seo_ui_empty( 'همه صفحه‌ها دیده شده‌اند', 'هر صفحه منتشرشده‌ای دست‌کم یک نمایش در گوگل گرفته است.' );
	} else {
		?>
		<div class="hds-table-wrap" tabindex="0" role="region" aria-label="صفحه‌های بدون نمایش">
			<table class="widefat striped hodima-advisor__terms">
				<thead><tr><th scope="col">صفحه</th><th scope="col">نوع</th><th scope="col">سن</th><th scope="col"></th></tr></thead>
				<tbody>
					<?php foreach ( array_slice( $unseen, 0, 30 ) as $row ) : ?>
						<?php $edit = get_edit_post_link( (int) $row['id'], 'raw' ); ?>
						<tr>
							<th scope="row"><?php echo esc_html( '' !== (string) $row['title'] ? (string) $row['title'] : (string) $row['path'] ); ?> <?php echo hodima_seo_advisor_url_link( (string) $row['path'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML ساخته‌شده و escape‌شده در hodima_seo_advisor_url_link ?></th>
							<td><?php echo esc_html( hodima_seo_advisor_kind_label( (string) $row['type'] ) ); ?></td>
							<td><?php echo esc_html( sprintf( '%s روز', $n( $row['days'] ) ) ); ?></td>
							<td><?php echo is_string( $edit ) && '' !== $edit ? hodima_seo_advisor_action( $edit, 'ویرایش صفحه', 'edit' ) : hodima_seo_advisor_action( home_url( (string) $row['path'] ), 'دیدن در سایت', 'external', true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML امن‌شده در hodima_seo_advisor_action ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<div class="hodima-advisor__advice"><strong>چه کنید:</strong> برای هر صفحه بررسی کنید: noindex یا canonical به آدرس دیگر نباشد؛ در سایت‌مپ باشد؛ دست‌کم از ۲ تا ۳ صفحه مرتبط لینک داخلی بگیرد؛ و محتوایش کم یا تکراری نباشد. بعد در Search Console همان آدرس را در «بررسی آدرس» بگذارید و «درخواست ایندکس» بزنید. اگر صفحه ارزشی ندارد، ادغام یا حذفش با ریدایرکت بهتر است.</div>
		<?php
	}
	hodima_seo_ui_card_close();
}
