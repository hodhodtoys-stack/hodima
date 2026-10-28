<?php
/**
 * ماژول «دستار سئو» — تب «ایرادهای فنی»
 * Path: core/advisor/advisor-crawl-view.php
 *
 * داده از advisor-crawl.php. کالبد کیت SEO: ۴ کارت آمار ← کارت «فایل‌های خزش» (یک کنش اصلی:
 * خواندن فایل‌ها) ← کارت «ایرادهای صفحه‌ها» (چیپ‌های فیلتر + جستجو + جدول مرتب با نمایش گوگل).
 * فقط می‌خواند و دستور می‌دهد؛ چیزی را در سایت عوض نمی‌کند. (طرح تأییدشده کاربر ۴ آبان ۱۴۰۵)
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
 * محتوای تب «ایرادهای فنی».
 */
function hodima_seo_advisor_render_crawl(): void {

	$status = hodima_seo_advisor_crawl_status();
	$has    = $status['imported'] > 0 && $status['pages'] > 0;
	$n      = static fn( int|float $value ): string => number_format_i18n( $value );
	$stale  = $has && time() - $status['imported'] > HODIMA_SEO_ADVISOR_CRAWL_STALE_DAYS * DAY_IN_SECONDS;
	$issues = $has ? hodima_seo_advisor_crawl_issues() : [];
	$sum    = $has ? hodima_seo_advisor_crawl_summary( $issues ) : [ 'crawled' => 0, 'with_issues' => 0, 'big' => 0, 'low_links' => 0, 'groups' => [] ];

	hodima_seo_ui_stats( [
		[ 'label' => 'صفحه خزش‌شده', 'value' => $has ? esc_html( $n( $sum['crawled'] ) ) : '—', 'sub' => $has ? esc_html( sprintf( 'خزش %s پیش', human_time_diff( $status['imported'] ) ) ) : '', 'empty' => ! $has ],
		[ 'label' => 'صفحه دارای ایراد', 'value' => $has ? esc_html( $n( $sum['with_issues'] ) ) : '—', 'sub' => $has ? esc_html( sprintf( 'از %s صفحه', $n( $sum['crawled'] ) ) ) : '', 'empty' => ! $has ],
		[ 'label' => 'ایراد در صفحه‌های پرنمایش', 'value' => $has ? esc_html( $n( $sum['big'] ) ) : '—', 'sub' => $has ? esc_html( sprintf( 'بیش از %s نمایش', $n( HODIMA_SEO_ADVISOR_CRAWL_BIG_IMPRESSIONS ) ) ) : '', 'empty' => ! $has ],
		[ 'label' => 'لینک ورودی کم', 'value' => $has ? esc_html( $n( $sum['low_links'] ) ) : '—', 'sub' => $has ? esc_html( sprintf( '%s لینک یا کمتر', $n( HODIMA_SEO_ADVISOR_CRAWL_LOW_INLINKS ) ) ) : '', 'empty' => ! $has ],
	], 'خلاصه ایرادهای فنی' );

	$prepared = hodima_seo_advisor_crawl_prepare_dir();
	$files    = [];
	foreach ( array_keys( HODIMA_SEO_ADVISOR_CRAWL_FILES ) as $kind ) {
		$files[ $kind ] = hodima_seo_advisor_crawl_file_state( $kind );
	}
	$saved = $status['files'];
	$label = hodima_seo_advisor_crawl_dir_label();
	$ready = 'missing' !== $files['pages']['state'];

	hodima_seo_ui_card_open( 'فایل‌های خزش', 'خروجی Screaming Frog را با همین دو نام در پوشه زیر بگذارید و «خواندن فایل‌ها» را بزنید. فایل‌ها از وب بسته‌اند و در به‌روزرسانی افزونه پاک نمی‌شوند.' );
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
				<span class="dashicons dashicons-update" aria-hidden="true"></span> <?php echo esc_html( $has ? 'خواندن دوباره فایل‌ها' : 'خواندن فایل‌ها' ); ?>
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
	if ( $stale ) {
		echo '<p class="hodima-advisor__advice"><strong>خزش قدیمی است:</strong> بیش از ' . esc_html( $n( HODIMA_SEO_ADVISOR_CRAWL_STALE_DAYS ) ) . ' روز از آخرین خزش گذشته. یک خزش تازه بگیرید و فایل‌ها را جایگزین کنید.</p>';
	}
	hodima_seo_ui_card_close();

	if ( ! $has ) {
		hodima_seo_ui_card_open( 'ایرادهای صفحه‌ها' );
		hodima_seo_ui_empty( 'هنوز خروجی خزش خوانده نشده', '۱) در Screaming Frog سایت را خزش کنید. ۲) Internal ← HTML را با نام internal_html.csv و Bulk Export ← Links ← All Inlinks را با نام all_inlinks.csv خروجی بگیرید. ۳) هر دو را در پوشه بالا بگذارید و «خواندن فایل‌ها» را بزنید.' );
		hodima_seo_ui_card_close();
		return;
	}

	hodima_seo_advisor_render_crawl_list( $issues, $sum );
}

/**
 * کارت «ایرادهای صفحه‌ها»: چیپ‌های فیلتر، جستجو، جدول و صفحه‌بندی.
 *
 * @param list<array<string, mixed>> $issues
 * @param array<string, mixed>       $sum
 */
function hodima_seo_advisor_render_crawl_list( array $issues, array $sum ): void {

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
		'meta'     => [ 'بدون متا', $sum['groups']['meta'] ?? 0 ],
		'title'    => [ 'عنوان یا H1', $sum['groups']['title'] ?? 0 ],
		'dup'      => [ 'تکراری', $sum['groups']['dup'] ?? 0 ],
		'slow'     => [ 'کند', $sum['groups']['slow'] ?? 0 ],
		'redirect' => [ 'ریدایرکت یا noindex', $sum['groups']['redirect'] ?? 0 ],
		'links'    => [ 'لینک ورودی کم', $sum['groups']['links'] ?? 0 ],
		'orphan'   => [ 'بدون لینک ورودی', $sum['groups']['orphan'] ?? 0 ],
	];

	$url = static fn( array $args ): string => hodima_seo_advisor_page_url( [ 'tab' => 'crawl' ] + $args );

	hodima_seo_ui_card_open( 'ایرادهای صفحه‌ها', 'مرتب بر اساس نمایش در گوگل؛ اول صفحه‌هایی که ایرادشان بیشتر دیده می‌شود. فقط پیشنهاد است؛ چیزی خودکار عوض نمی‌شود.' );
	?>
	<form method="get" class="hodima-advisor__toolbar">
		<input type="hidden" name="page" value="<?php echo esc_attr( HODIMA_SEO_ADVISOR_PAGE ); ?>">
		<input type="hidden" name="tab" value="crawl">
		<?php if ( '' !== $group ) : ?>
			<input type="hidden" name="cf" value="<?php echo esc_attr( $group ); ?>">
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
		hodima_seo_ui_empty( $issues ? 'اینجا چیزی نیست' : 'ایرادی پیدا نشد', $issues ? 'با این فیلتر صفحه‌ای ایراد ندارد. فیلتر را عوض کنید.' : 'همه صفحه‌های خزش‌شده از دید این بررسی‌ها سالم‌اند.' );
		hodima_seo_ui_card_close();
		return;
	}

	$pages = (int) ceil( count( $rows ) / HODIMA_SEO_ADVISOR_CRAWL_PER_PAGE );
	$paged = min( $paged, $pages );
	$slice = array_slice( $rows, ( $paged - 1 ) * HODIMA_SEO_ADVISOR_CRAWL_PER_PAGE, HODIMA_SEO_ADVISOR_CRAWL_PER_PAGE );
	?>
	<div class="hds-table-wrap" tabindex="0" role="region" aria-label="ایرادهای صفحه‌ها">
		<table class="widefat striped hodima-advisor__terms hodima-advisor__crawl-table">
			<thead><tr><th scope="col">صفحه</th><th scope="col">نمایش</th><th scope="col">رتبه</th><th scope="col">ایراد و دستور</th><th scope="col"><span class="screen-reader-text">ویرایش</span></th></tr></thead>
			<tbody>
				<?php foreach ( $slice as $row ) : ?>
					<?php $target = hodima_seo_advisor_crawl_row_target( (string) $row['path'] ); ?>
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
							<?php // هر ایراد یک سطر: نشان + دستور خودش؛ همه ایرادها (تا SEO 3.0.0 فقط ۳ دستور اول، جدا از نشان‌ها) ?>
							<?php foreach ( $row['issues'] as $issue ) : ?>
								<?php $info = hodima_seo_advisor_crawl_issue_info( (string) $issue['code'] ); ?>
								<div class="hodima-advisor__crawl-fix">
									<span class="<?php echo esc_attr( hodima_seo_advisor_crawl_pill_class( $info['level'] ) ); ?>"><?php echo esc_html( $info['label'] ); ?></span>
									<span>
										<?php echo esc_html( '' !== (string) $issue['detail'] ? $issue['detail'] . ( str_ends_with( (string) $issue['detail'], ':' ) ? '' : '.' ) : '' ); ?>
										<?php if ( '' !== (string) ( $issue['url'] ?? '' ) ) : ?>
											<?php echo hodima_seo_advisor_url_copy( (string) $issue['url'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML امن‌شده در hodima_seo_advisor_url_copy ?>
										<?php endif; ?>
										<?php echo esc_html( $info['fix'] ); ?>
									</span>
								</div>
							<?php endforeach; ?>
						</td>
						<td class="hodima-advisor__crawl-edit">
							<?php if ( '' !== $target['edit'] ) : ?>
								<?php echo hodima_seo_advisor_action( $target['edit'], 'ویرایش صفحه', 'edit' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML امن‌شده در hodima_seo_advisor_action ?>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<div class="hodima-advisor__foot">
		<span><?php echo esc_html( sprintf( '%1$s صفحه با ایراد از %2$s صفحه خزش‌شده', $n( count( $rows ) ), $n( $sum['crawled'] ) ) ); ?></span>
		<?php hodima_seo_ui_pager( $paged, $pages, static fn( int $i ): string => $url( [ 'cf' => $group, 'q' => $q, 'paged' => $i > 1 ? $i : '' ] ) ); ?>
	</div>
	<?php
	hodima_seo_ui_card_close();
}
