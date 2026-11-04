<?php
/**
 * ماژول «دستار سئو» — تب «ایندکس»
 * Path: core/advisor/advisor-inspect-view.php
 *
 * داده از advisor-inspect.php. کالبد: ۴ کارت آمار ← کارت «وضعیت ایندکس آدرس‌های مهم» (یک کنش اصلی:
 * «بررسی ۲۰ آدرس بعدی»، جدول مشکل‌دارها با وضعیت و آخرین خزش، دستور هر وضعیت). فقط می‌خواند و دستور
 * می‌دهد؛ چیزی را در سایت یا Search Console عوض نمی‌کند. SEO 3.0.2: هر آدرس مشکل‌دار «انجام شد / رد شد» دارد
 * (advisor-marks.php)؛ «انجام دادم» آدرس را اول صف بررسی بعدی می‌گذارد.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * برچسب، رنگ نشان، اولویت و دستور هر وضعیت ایندکس؛ از SEO 3.7.0 همراه راهنمای پنج‌بخشی «کارها» (seen، steps، why، need).
 *
 * @return array{label: string, class: string, weight: int, advice: string, seen?: string, steps?: list<string>, why?: string, need?: string}
 */
function hodima_seo_advisor_inspect_state_info( string $state ): array {

	$base  = hodima_seo_advisor_inspect_state_base( $state );
	$guide = hodima_seo_advisor_inspect_state_guide()[ $state ] ?? null;

	return null === $guide ? $base : $base + $guide;
}

/**
 * راهنمای هر وضعیت ایندکس برای یک صفحه (SEO 3.7.0؛ قبلا یک جمله کلی: «noindex را در سئوباکس یا تنظیمات همان صفحه بردارید»).
 *
 * @return array<string, array{seen: string, steps: list<string>, why: string, need?: string}>
 */
function hodima_seo_advisor_inspect_state_guide(): array {

	$seobox = 'کادر «سئو (SeoBox)»';
	$claude = 'آدرس صفحه را به Claude بدهید تا در کد سایت درست شود.';

	return [
		'crawled_not_indexed'    => [
			'seen'  => 'گوگل این صفحه را خوانده ولی آن را ارزش نگه‌داشتن در نتیجه‌ها ندیده است.',
			'steps' => [ '«{where}» ← متن صفحه را کامل‌تر و مخصوص همین صفحه کنید (جنس، اندازه، کاربرد؛ متن تکراری از صفحه‌های دیگر نه).', 'از ۲ تا ۳ صفحه مرتبط (دسته، مقاله) به آن لینک بدهید.', 'اگر صفحه‌ای تقریبا همین را دارد، یکی را نگه دارید و دیگری را در «ابزارهای هدیما ← ریدایرکت‌ها» با ۳۰۱ به آن بفرستید.', 'بعد در Search Console همین آدرس را «بررسی آدرس» کنید و «درخواست ایندکس» بزنید.' ],
			'why'   => 'گوگل همه صفحه‌ها را نگه نمی‌دارد؛ صفحه کم‌محتوا یا شبیه صفحه دیگر بیرون می‌ماند.',
		],
		'discovered_not_indexed' => [
			'seen'  => 'گوگل از وجود این صفحه خبر دارد ولی هنوز سراغش نرفته است.',
			'steps' => [ 'از صفحه‌های قوی سایت (صفحه اصلی، دسته پربازدید) به آن لینک بدهید.', 'مطمئن شوید نوع این صفحه در «اسکیما ← نقشه سایت» تیک دارد.', 'چند روز صبر کنید؛ گوگل صفحه تازه را با تأخیر می‌خواند.' ],
			'why'   => 'گوگل صفحه‌ای را زودتر می‌خواند که از صفحه‌های مهم سایت به آن لینک باشد.',
		],
		'noindex'                => [
			'seen'  => 'گوگل در این صفحه دستور noindex دیده و آن را در نتیجه‌ها نشان نمی‌دهد.',
			'steps' => [ 'اگر عمدی است (صفحه آزمایشی، تشکر از خرید): «لازم نیست».', 'وگرنه: «{where}» ← ' . $seobox . ' ← تب «ایندکس / نوایندکس» ← «ایندکس» را انتخاب و ذخیره کنید.', 'اگر آنجا «ایندکس» است و باز همین وضعیت ماند: ' . $claude ],
			'why'   => 'صفحه noindex هرگز در گوگل دیده نمی‌شود؛ فقط باید مطمئن شوید عمدی است.',
			'need'  => 'info',
		],
		'robots'                 => [
			'seen'  => 'robots.txt سایت به گوگل اجازه خواندن این آدرس را نمی‌دهد.',
			'steps' => [ 'اگر این آدرس عمدا بسته است (سبد خرید، حساب کاربری، جستجو): «لازم نیست».', 'وگرنه: robots.txt را ماژول «robots.txt هوشمند» هدیما خودکار می‌سازد؛ ' . $claude ],
			'why'   => 'آدرسی که robots.txt بسته است خوانده نمی‌شود و معمولا در نتیجه‌ها نمی‌آید.',
		],
		'soft404'                => [
			'seen'  => 'گوگل این صفحه را شبیه صفحه خالی یا «پیدا نشد» می‌بیند (۴۰۴ نرم).',
			'steps' => [ 'صفحه را در مرورگر باز کنید؛ اگر خالی است یا «محصولی پیدا نشد» دارد، علت همین است.', 'اگر صفحه ارزش دارد: «{where}» ← متن و محتوای واقعی اضافه کنید و ذخیره کنید.', 'اگر ارزشی ندارد: آن را حذف کنید یا در «ابزارهای هدیما ← ریدایرکت‌ها» با ۳۰۱ به صفحه مرتبط بفرستید.' ],
			'why'   => 'گوگل صفحه‌ای را که محتوای واقعی ندارد ایندکس نمی‌کند.',
		],
		'not_found'              => [
			'seen'  => 'گوگل این آدرس را خواسته و سایت «پیدا نشد» (۴۰۴) داده است.',
			'steps' => [ 'اگر نباید حذف می‌شد: از «زباله‌دان» همان بخش برگردانید.', 'اگر جایگزین دارد: در «ابزارهای هدیما ← ریدایرکت‌ها» یک ۳۰۱ از این آدرس به صفحه جایگزین بسازید.', 'اگر جایگزینی ندارد: «لازم نیست»؛ گوگل آن را کم‌کم کنار می‌گذارد.' ],
			'why'   => 'ریدایرکت بازدیدکننده‌ای را که از گوگل می‌آید به صفحه درست می‌برد.',
		],
		'server_error'           => [
			'seen'  => 'وقتی گوگل این صفحه را خواند، سرور خطا داد (۵xx).',
			'steps' => [ 'آدرس را در مرورگر باز کنید؛ اگر باز شد، خطا لحظه‌ای بوده است.', 'اگر باز نشد: فایل لاگ خطای هاست (error_log در کنترل‌پنل هاست) را ببینید و آخرین خطا را به Claude بدهید.', 'اگر در ساعت‌های شلوغ تکرار می‌شود: از پشتیبانی هاست درباره منابع و بار سرور بپرسید.' ],
			'why'   => 'گوگل صفحه‌ای را که پیوسته خطا بدهد از نتیجه‌ها برمی‌دارد.',
		],
		'duplicate'              => [
			'seen'  => 'گوگل صفحه دیگری را نسخه «اصلی» این صفحه دانسته و آن را نشان می‌دهد.',
			'steps' => [ 'اگر درست است (مثلا آدرس فیلتر یا تکراری): «لازم نیست»؛ فقط لینک‌های داخلی را به همان نسخه اصلی بدهید.', 'اگر این صفحه باید خودش در گوگل باشد: «{where}» ← ' . $seobox . ' ← تب «canonical» ← «آدرس قانونی» را خالی کنید و متن صفحه را از صفحه دیگر متفاوت کنید.', 'اگر هر دو یک محتوا دارند: یکی را در «ابزارهای هدیما ← ریدایرکت‌ها» با ۳۰۱ به دیگری بفرستید.' ],
			'why'   => 'گوگل از چند صفحه شبیه فقط یکی را نشان می‌دهد.',
			'need'  => 'info',
		],
		'redirect'               => [
			'seen'  => 'این آدرس به آدرس دیگری ریدایرکت می‌شود.',
			'steps' => [ 'اگر ریدایرکت عمدی است، کاری لازم نیست.', 'جاهایی از سایت (منو، متن) که هنوز به این آدرس قدیمی لینک داده‌اند را به آدرس نهایی عوض کنید.' ],
			'why'   => 'گوگل ریدایرکت را دنبال می‌کند؛ لینک مستقیم فقط یک پرش کمتر دارد.',
			'need'  => 'info',
		],
		'unknown_to_google'      => [
			'seen'  => 'گوگل هنوز این آدرس را نمی‌شناسد.',
			'steps' => [ 'مطمئن شوید نوع این صفحه در «اسکیما ← نقشه سایت» تیک دارد.', 'از یک صفحه مرتبط سایت به آن لینک بدهید.', 'در Search Console آدرس را «بررسی آدرس» کنید و «درخواست ایندکس» بزنید.' ],
			'why'   => 'گوگل صفحه را از نقشه سایت و لینک‌ها پیدا می‌کند.',
		],
		'other'                  => [
			'seen'  => 'گوگل برای این آدرس وضعیتی گزارش داده که دستار نمی‌شناسد.',
			'steps' => [ 'در Search Console همین آدرس را «بررسی آدرس» کنید.', 'متن وضعیتی که گوگل نشان می‌دهد را به Claude بدهید.' ],
			'why'   => 'بدون دانستن وضعیت دقیق نمی‌شود دستور درست داد.',
			'need'  => 'info',
		],
	];
}

/**
 * برچسب، رنگ نشان، اولویت و دستور کوتاه هر وضعیت (جدول تب «ایندکس» و پیام‌ها).
 *
 * @return array{label: string, class: string, weight: int, advice: string}
 */
function hodima_seo_advisor_inspect_state_base( string $state ): array {

	$warn = 'hds-pill hds-pill--warn';
	$info = 'hds-pill hds-pill--info';

	return [
		'crawled_not_indexed'    => [ 'label' => 'خزیده شد ولی ایندکس نشد', 'class' => $warn, 'weight' => 0, 'advice' => 'گوگل این صفحه‌ها را دیده ولی ارزش ایندکس ندیده است. محتوا را کامل‌تر و منحصربه‌فردتر کنید، دست‌کم از ۲ تا ۳ صفحه مرتبط لینک داخلی بدهید، صفحه‌های تکراری را ادغام کنید؛ بعد در Search Console «درخواست ایندکس» بزنید.' ],
		'discovered_not_indexed' => [ 'label' => 'کشف شد ولی خزیده نشده', 'class' => $warn, 'weight' => 1, 'advice' => 'گوگل از وجود این صفحه‌ها خبر دارد ولی هنوز سراغشان نرفته. از صفحه‌های قوی سایت لینک داخلی بدهید، مطمئن شوید در سایت‌مپ‌اند و سرعت سرور خوب است؛ کمی صبر هم لازم است.' ],
		'noindex'                => [ 'label' => 'noindex', 'class' => $warn, 'weight' => 2, 'advice' => 'این صفحه‌ها noindex دارند. اگر نباید باشند، گزینه noindex را در سئوباکس یا تنظیمات همان صفحه بردارید.' ],
		'robots'                 => [ 'label' => 'بسته با robots.txt', 'class' => $warn, 'weight' => 2, 'advice' => 'robots.txt اجازه خزش این آدرس‌ها را نمی‌دهد. اگر نباید بسته باشند، قانون را در «robots.txt» بردارید.' ],
		'soft404'                => [ 'label' => '۴۰۴ نرم', 'class' => $warn, 'weight' => 3, 'advice' => 'گوگل این صفحه‌ها را شبیه صفحه خالی یا ۴۰۴ می‌بیند. محتوای واقعی بگذارید، یا اگر ارزشی ندارند ۴۰۴ یا ریدایرکت کنید.' ],
		'not_found'              => [ 'label' => '۴۰۴', 'class' => $warn, 'weight' => 3, 'advice' => 'این آدرس‌ها در سایت ۴۰۴ هستند؛ اگر جایگزین دارند در «ریدایرکت‌ها» با ۳۰۱ ببرید.' ],
		'server_error'           => [ 'label' => 'خطای سرور', 'class' => $warn, 'weight' => 3, 'advice' => 'سرور هنگام خزش خطا داده است. لاگ خطای سرور و سرعت و بار آن را بررسی کنید.' ],
		'duplicate'              => [ 'label' => 'تکراری / canonical دیگر', 'class' => $info, 'weight' => 4, 'advice' => 'گوگل صفحه دیگری را «اصلی» دانسته. اگر درست است، لینک‌های داخلی را به همان بدهید؛ اگر نه، canonical را درست کنید یا دو صفحه را ادغام کنید (تب «صفحه‌های هم‌کلمه»).' ],
		'redirect'               => [ 'label' => 'ریدایرکت', 'class' => $info, 'weight' => 5, 'advice' => 'این آدرس‌ها ریدایرکت می‌شوند؛ لینک‌های داخلی را به آدرس نهایی بدهید.' ],
		'unknown_to_google'      => [ 'label' => 'برای گوگل ناشناخته', 'class' => $warn, 'weight' => 1, 'advice' => 'گوگل هنوز این آدرس را نمی‌شناسد. آن را در سایت‌مپ بگذارید، لینک داخلی بدهید و «درخواست ایندکس» بزنید.' ],
		'other'                  => [ 'label' => 'نیازمند بررسی', 'class' => $info, 'weight' => 6, 'advice' => 'وضعیت این آدرس‌ها را در Search Console با «بررسی آدرس» ببینید.' ],
		'indexed'                => [ 'label' => 'ایندکس شده', 'class' => 'hds-pill', 'weight' => 9, 'advice' => '' ],
	][ $state ] ?? [ 'label' => $state, 'class' => $info, 'weight' => 6, 'advice' => '' ];
}

/**
 * محتوای تب «ایندکس».
 */
function hodima_seo_advisor_render_indexing(): void {

	$records = hodima_seo_advisor_inspections();
	$n       = static fn( int|float $value ): string => number_format_i18n( $value );
	$empty   = ! $records;

	$states   = [];
	$mismatch = 0;
	foreach ( $records as $path => $record ) {
		$states[ $path ] = hodima_seo_advisor_inspect_state( $record );
		if ( hodima_seo_advisor_inspect_canonical_mismatch( $record ) ) {
			++$mismatch;
		}
	}

	$indexed = count( array_filter( $states, static fn( string $s ): bool => 'indexed' === $s ) );
	$problem = count( $states ) - $indexed;
	$pending = hodima_seo_advisor_inspect_pending();

	hodima_seo_ui_stats( [
		[ 'label' => 'بررسی‌شده', 'value' => $empty ? '—' : esc_html( $n( count( $records ) ) ), 'sub' => esc_html( sprintf( '%s منتظر بررسی', $n( $pending ) ) ), 'empty' => $empty ],
		[ 'label' => 'ایندکس‌شده', 'value' => $empty ? '—' : esc_html( $n( $indexed ) ), 'empty' => $empty ],
		[ 'label' => 'نیازمند بررسی', 'value' => $empty ? '—' : esc_html( $n( $problem ) ), 'empty' => $empty ],
		[ 'label' => 'canonical متفاوت', 'value' => $empty ? '—' : esc_html( $n( $mismatch ) ), 'sub' => $empty ? '' : 'گوگل غیر از شما انتخاب کرده', 'empty' => $empty ],
	] );

	hodima_seo_ui_card_open( 'وضعیت ایندکس آدرس‌های مهم', 'از گوگل می‌پرسد هر آدرس ایندکس شده یا نه و اگر نه چرا؛ اول صفحه‌های بدون نمایش. هر آدرس هر ۱۴ روز؛ خودکار روزی دو بار ۲۰ آدرس.' );
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="hodima-advisor__form">
		<input type="hidden" name="action" value="hodima_advisor_inspect_now">
		<?php wp_nonce_field( 'hodima_advisor_inspect_now' ); ?>
		<button type="submit" class="hds-btn"<?php disabled( 0 === $pending ); ?>><span class="dashicons dashicons-search" aria-hidden="true"></span> <?php echo esc_html( $pending > 0 ? sprintf( 'بررسی %s آدرس بعدی', $n( min( HODIMA_SEO_ADVISOR_INSPECT_BATCH, $pending ) ) ) : 'همه آدرس‌های صف بررسی شده‌اند' ); ?></button>
	</form>
	<?php

	if ( $empty ) {
		hodima_seo_ui_empty( 'هنوز آدرسی بررسی نشده', 'دکمه بالا را بزنید یا تا بررسی خودکار بعدی صبر کنید. به داده «گرفتن داده تازه» و property سرچ کنسول نیاز دارد.' );
		hodima_seo_ui_card_close();
		return;
	}

	$problems = array_filter( $records, static fn( array $r, string $path ): bool => 'indexed' !== $states[ $path ], ARRAY_FILTER_USE_BOTH );
	$show     = hodima_seo_advisor_show_marked();
	$hidden   = 0;
	foreach ( array_keys( $problems ) as $path ) {
		if ( ! $show && null !== hodima_seo_advisor_task_closed( (string) $path, 'index_problem' ) ) {
			unset( $problems[ $path ] );
			++$hidden;
		}
	}
	uksort(
		$problems,
		static fn( string $a, string $b ): int => [ hodima_seo_advisor_inspect_state_info( $states[ $a ] )['weight'], -( (int) $problems[ $a ]['checked'] ) ] <=> [ hodima_seo_advisor_inspect_state_info( $states[ $b ] )['weight'], -( (int) $problems[ $b ]['checked'] ) ]
	);

	if ( ! $problems && $hidden > 0 ) {
		hodima_seo_ui_empty( 'کار بازی نمانده', sprintf( 'همه آدرس‌های مشکل‌دار را علامت زده‌اید؛ در بررسی بعدی گوگل سنجیده می‌شوند. %s', hodima_seo_advisor_marked_toggle( $hidden, [ 'tab' => 'indexing' ], 'آدرس' ) ) );
		hodima_seo_ui_card_close();
		return;
	}

	if ( ! $problems ) {
		hodima_seo_ui_empty( 'همه آدرس‌های بررسی‌شده ایندکس‌اند', 'مشکلی در بررسی‌های تا الان پیدا نشد.' );
		hodima_seo_ui_card_close();
		return;
	}

	// صفحه‌بندی (SEO 3.0.1): تا ۶۰۰ آدرس بررسی می‌شود ولی قبلا فقط ۴۰ مشکل اول دیده می‌شد
	$pages = (int) ceil( count( $problems ) / HODIMA_SEO_ADVISOR_HEALTH_PER_PAGE );
	$paged = min( $pages, max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط صفحه‌بندی نمایش
	?>
	<div class="hds-table-wrap" tabindex="0" role="region" aria-label="آدرس‌های نیازمند بررسی ایندکس">
		<table class="widefat striped hodima-advisor__terms hodima-advisor__cards">
			<thead><tr><th scope="col">آدرس</th><th scope="col">وضعیت</th><th scope="col">آخرین خزش</th><th scope="col">canonical گوگل</th><th scope="col">کنش</th></tr></thead>
			<tbody>
				<?php foreach ( array_slice( $problems, ( $paged - 1 ) * HODIMA_SEO_ADVISOR_HEALTH_PER_PAGE, HODIMA_SEO_ADVISOR_HEALTH_PER_PAGE, true ) as $path => $record ) : ?>
					<?php
					$info   = hodima_seo_advisor_inspect_state_info( $states[ $path ] );
					$crawl  = '' !== (string) $record['last_crawl'] ? strtotime( (string) $record['last_crawl'] ) : false;
					$google = hodima_seo_advisor_inspect_canonical_mismatch( $record ) ? hodima_seo_advisor_path_key( (string) $record['google_canonical'] ) : null;
					$task   = hodima_seo_advisor_task( (string) $path, 'index_problem', '' );
					$title  = hodima_seo_advisor_breakdown_page_title( (string) $path );
					?>
					<tr>
						<th scope="row"><?php echo $title !== rawurldecode( (string) $path ) ? esc_html( $title ) . ' ' : ''; // عنوان فقط وقتی با خود مسیر فرق دارد ?><?php echo hodima_seo_advisor_url_link( (string) $path ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML ساخته‌شده و escape‌شده در hodima_seo_advisor_url_link ?> <?php echo hodima_seo_advisor_task_badge( $task ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML امن‌شده در hodima_seo_advisor_task_badge ?></th>
						<td><span class="<?php echo esc_attr( $info['class'] ); ?>"><?php echo esc_html( $info['label'] ); ?></span></td>
						<td data-label="آخرین خزش"><?php echo false !== $crawl ? esc_html( hodima_seo_advisor_task_date( $crawl ) ) : '—'; ?></td>
						<td data-label="canonical گوگل"><?php echo null !== $google ? hodima_seo_advisor_url_link( $google, 'hodima-advisor__rival-url' ) : '—'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML ساخته‌شده و escape‌شده در hodima_seo_advisor_url_link ?></td>
						<td>
							<?php if ( null === $task || ! in_array( hodima_seo_advisor_task_state( $task ), HODIMA_SEO_ADVISOR_TASKS_CLOSED, true ) ) : ?>
								<?php hodima_seo_advisor_mark_form( hodima_seo_advisor_mark_id(), 'indexing', (string) $path, '' !== $title ? $title : rawurldecode( (string) $path ) ); ?>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php $toggle = hodima_seo_advisor_marked_toggle( $hidden, [ 'tab' => 'indexing' ], 'آدرس' ); ?>
	<?php if ( $pages > 1 || '' !== $toggle ) : ?>
		<div class="hodima-advisor__foot">
			<span>
				<?php echo esc_html( sprintf( 'نمایش %1$s از %2$s آدرس نیازمند بررسی', $n( count( array_slice( $problems, ( $paged - 1 ) * HODIMA_SEO_ADVISOR_HEALTH_PER_PAGE, HODIMA_SEO_ADVISOR_HEALTH_PER_PAGE ) ) ), $n( count( $problems ) ) ) ); ?>
				<?php echo $toggle; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML امن‌شده در hodima_seo_advisor_action ?>
			</span>
			<?php hodima_seo_ui_pager( $paged, $pages, static fn( int $i ): string => hodima_seo_advisor_page_url( [ 'tab' => 'indexing', 'tasks' => $show ? 'all' : '', 'paged' => $i > 1 ? $i : '' ] ) ); ?>
		</div>
	<?php endif; ?>
	<details class="hds-more hodima-advisor__explain">
		<summary class="hds-more__summary">توضیح هر وضعیت و چه کنید؟</summary>
		<div class="hds-more__body">
			<p class="hodima-advisor__advice">بعد از رفع، «انجام دادم» را بزنید: آدرس اول صف بررسی بعدی گوگل می‌رود. ایندکس شد ← برطرف؛ گوگل دوباره خواند و هنوز نه ← با دلیلش به «کارها» ← «نیاز به کار» برمی‌گردد.</p>
			<?php
			foreach ( array_unique( array_map( static fn( string $path ): string => $states[ $path ], array_keys( $problems ) ) ) as $state ) {
				$advice = hodima_seo_advisor_inspect_state_info( $state );
				if ( '' !== $advice['advice'] ) {
					echo '<div class="hodima-advisor__advice"><strong>' . esc_html( $advice['label'] ) . ':</strong> ' . esc_html( $advice['advice'] ) . '</div>';
				}
			}
			?>
		</div>
	</details>
	<?php

	hodima_seo_ui_card_close();
}
