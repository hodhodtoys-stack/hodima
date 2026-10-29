<?php
/**
 * ماژول «دستار سئو» — تب «موبایل و کشور»
 * Path: core/advisor/advisor-breakdown-view.php
 *
 * داده از advisor-breakdown.php. کالبد: ۴ کارت آمار ← کارت دستگاه (سه ردیف با نوار سهم کلیک و تفسیر)
 * ← کارت «صفحه‌هایی که در موبایل بدتر رتبه می‌گیرند» ← کشورها ← جستجوی تصویر و ویدیو ← نوع نمایش در
 * گوگل. فقط می‌خواند و دستور می‌دهد؛ چیزی را در سایت عوض نمی‌کند.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** نام فارسی کشور (کد سه‌حرفی سرچ کنسول)؛ ناشناس: خود کد. */
function hodima_seo_advisor_country_label( string $code ): string {

	$names = [
		'irn' => 'ایران', 'afg' => 'افغانستان', 'are' => 'امارات', 'irq' => 'عراق', 'tur' => 'ترکیه', 'usa' => 'آمریکا', 'deu' => 'آلمان',
		'can' => 'کانادا', 'gbr' => 'بریتانیا', 'aze' => 'آذربایجان', 'arm' => 'ارمنستان', 'pak' => 'پاکستان', 'qat' => 'قطر', 'kwt' => 'کویت',
		'omn' => 'عمان', 'sau' => 'عربستان', 'swe' => 'سوئد', 'nld' => 'هلند', 'fra' => 'فرانسه', 'rus' => 'روسیه', 'tjk' => 'تاجیکستان',
		'tkm' => 'ترکمنستان', 'geo' => 'گرجستان', 'ind' => 'هند', 'aus' => 'استرالیا', 'chn' => 'چین',
	];

	return $names[ strtolower( $code ) ] ?? strtoupper( $code );
}

/** نام فارسی نوع نمایش در گوگل؛ ناشناس: خود مقدار. */
function hodima_seo_advisor_appearance_label( string $key ): string {

	$names = [
		'product_snippets'   => 'ریچ‌اسنیپت محصول',
		'merchant_listings'  => 'فهرست فروشگاه (Merchant)',
		'review_snippet'     => 'ستاره امتیاز',
		'videos'             => 'ویدیو',
		'video'              => 'ویدیو',
		'faq_rich_result'    => 'پرسش‌های متداول',
		'recipe_rich_result' => 'دستور پخت',
		'amp_blue_link'      => 'AMP',
		'web_light_results'  => 'نسخه سبک وب',
	];

	return $names[ strtolower( $key ) ] ?? str_replace( '_', ' ', $key );
}

/** عنوان صفحه از جدول مشاور، وگرنه خود آدرس. */
function hodima_seo_advisor_breakdown_page_title( string $path ): string {

	$row = hodima_seo_advisor_page( $path );

	if ( null !== $row ) {
		$title = hodima_seo_advisor_row_title( $row );
		if ( '' !== $title ) {
			return $title;
		}
	}

	return $path;
}

/**
 * یک سطر «برچسب + نوار + اعداد» برای دستگاه‌ها/کشورها.
 *
 * @param array{clicks: int, impressions: int, position: float} $stat
 */
function hodima_seo_advisor_render_share_row( string $label, array $stat, float $share, bool $winner = false ): void {

	$n   = static fn( int|float $value ): string => number_format_i18n( $value );
	$ctr = $stat['impressions'] > 0 ? $stat['clicks'] / $stat['impressions'] * 100 : 0.0;
	?>
	<li class="hodima-advisor__rival hodima-advisor__rival--share<?php echo $winner ? ' is-winner' : ''; ?>">
		<div class="hodima-advisor__rival-name"><strong><?php echo esc_html( $label ); ?></strong></div>
		<div class="hodima-advisor__rival-share">
			<span class="hodima-advisor__share-bar" aria-hidden="true"><span style="inline-size: <?php echo esc_attr( (string) (int) round( $share * 100 ) ); ?>%"></span></span>
			<span><?php echo esc_html( sprintf( '%s٪ کلیک', $n( round( $share * 100 ) ) ) ); ?></span>
		</div>
		<span class="hodima-advisor__rival-stat"><?php echo esc_html( sprintf( '%s کلیک', $n( $stat['clicks'] ) ) ); ?></span>
		<span class="hodima-advisor__rival-stat"><?php echo esc_html( sprintf( '%s نمایش', $n( $stat['impressions'] ) ) ); ?></span>
		<span class="hodima-advisor__rival-stat"><?php echo esc_html( sprintf( 'رتبه %s', number_format_i18n( $stat['position'], 1 ) ) ); ?></span>
		<span class="hodima-advisor__rival-stat"><?php echo esc_html( sprintf( 'نرخ کلیک %s٪', number_format_i18n( $ctr, 2 ) ) ); ?></span>
	</li>
	<?php
}

/**
 * محتوای تب «موبایل و کشور».
 *
 * @param array<string, mixed> $status خروجی hodima_seo_advisor_status()
 */
function hodima_seo_advisor_render_breakdown( array $status ): void {

	$data  = hodima_seo_advisor_breakdown();
	$n     = static fn( int|float $value ): string => number_format_i18n( $value );
	$pct   = static fn( float $share ): string => $n( round( $share * 100 ) ) . '٪';
	$empty = (int) ( $data['fetched'] ?? 0 ) <= 0;

	$devices = (array) ( $data['device']['devices'] ?? [] );
	$gaps    = (array) ( $data['device']['gaps'] ?? [] );
	$country = (array) ( $data['country'] ?? [] );
	$image   = (array) ( $data['image'] ?? [] );
	$video   = (array) ( $data['video'] ?? [] );
	$appear  = (array) ( $data['appearance'] ?? [] );

	$total_clicks   = array_sum( array_column( $devices, 'clicks' ) );
	$mobile_share   = $total_clicks > 0 ? ( $devices['mobile']['clicks'] ?? 0 ) / $total_clicks : 0.0;
	$country_clicks = array_sum( array_column( $country, 'clicks' ) );
	$top_country    = (string) array_key_first( $country );
	$top_share      = $country_clicks > 0 && '' !== $top_country ? $country[ $top_country ]['clicks'] / $country_clicks : 0.0;

	hodima_seo_ui_stats( [
		[ 'label' => 'سهم کلیک موبایل', 'value' => $empty || ! $devices ? '—' : esc_html( $pct( $mobile_share ) ), 'empty' => $empty || ! $devices ],
		[ 'label' => 'کشور اول', 'value' => $empty || '' === $top_country ? '—' : esc_html( hodima_seo_advisor_country_label( $top_country ) ), 'sub' => '' === $top_country ? '' : esc_html( $pct( $top_share ) . ' کلیک' ), 'empty' => $empty || '' === $top_country ],
		[ 'label' => 'کلیک جستجوی تصویر', 'value' => $empty || ! $image ? '—' : esc_html( $n( (int) ( $image['clicks'] ?? 0 ) ) ), 'empty' => $empty || ! $image ],
		[ 'label' => 'رتبه موبایل ضعیف', 'value' => $empty || ! $devices ? '—' : esc_html( $n( count( $gaps ) ) ), 'sub' => $gaps ? 'صفحه' : '', 'empty' => $empty || ! $devices ],
	] );

	if ( $empty ) {
		hodima_seo_ui_card_open( 'موبایل و کشور' );
		hodima_seo_ui_empty( 'هنوز گرفته نشده', 'بعد از «گرفتن داده تازه» (و تا چند دقیقه) سهم موبایل و دسکتاپ، کشورها، جستجوی تصویر و نوع نمایش در گوگل اینجا پر می‌شود.' );
		hodima_seo_ui_card_close();
		return;
	}

	// دستگاه‌ها
	if ( $devices ) {
		hodima_seo_ui_card_open( 'مردم با چه دستگاهی می‌آیند؟', sprintf( 'داده %s روز اخیر؛ سهم از کل کلیک.', $n( $status['days'] ) ) );
		echo '<ul class="hodima-advisor__rivals">';
		foreach ( [ 'mobile' => 'موبایل', 'desktop' => 'دسکتاپ', 'tablet' => 'تبلت' ] as $key => $label ) {
			if ( isset( $devices[ $key ] ) ) {
				hodima_seo_advisor_render_share_row( $label, $devices[ $key ], $total_clicks > 0 ? $devices[ $key ]['clicks'] / $total_clicks : 0.0, ( 'mobile' === $key && $mobile_share >= 0.5 ) || ( 'desktop' === $key && $mobile_share < 0.5 ) );
			}
		}
		echo '</ul>';

		$mobile_pos  = (float) ( $devices['mobile']['position'] ?? 0 );
		$desktop_pos = (float) ( $devices['desktop']['position'] ?? 0 );
		$reading     = $mobile_pos > 0 && $desktop_pos > 0 && $mobile_pos - $desktop_pos >= 2
			? sprintf( 'رتبه میانگین موبایل (%1$s) از دسکتاپ (%2$s) بدتر است؛ سایت را در موبایل واقعی ببینید: سرعت (PageSpeed موبایل)، اندازه دکمه‌ها و متن، و محتوایی که فقط در دسکتاپ هست.', number_format_i18n( $mobile_pos, 1 ), number_format_i18n( $desktop_pos, 1 ) )
			: 'رتبه موبایل و دسکتاپ نزدیک به هم است؛ مشکل کلی موبایل دیده نمی‌شود.';
		echo '<div class="hodima-advisor__advice">' . esc_html( $reading ) . '</div>';
		hodima_seo_ui_card_close();
	}

	// صفحه‌های با رتبه موبایل بدتر
	hodima_seo_ui_card_open( 'صفحه‌هایی که در موبایل بدتر رتبه می‌گیرند', 'رتبه موبایل دست‌کم ۳ پله بدتر از دسکتاپ (با ≥ ۵۰۰ نمایش موبایل)؛ مرتب بر اساس نمایش موبایل.' );
	if ( ! $gaps ) {
		hodima_seo_ui_empty( 'موردی نیست', 'صفحه‌ای پیدا نشد که رتبه موبایلش به‌طور محسوس از دسکتاپ بدتر باشد.' );
	} else {
		?>
		<div class="hds-table-wrap" tabindex="0" role="region" aria-label="صفحه‌های با رتبه موبایل ضعیف">
			<table class="widefat striped hodima-advisor__terms">
				<thead><tr><th scope="col">صفحه</th><th scope="col">رتبه موبایل</th><th scope="col">رتبه دسکتاپ</th><th scope="col">فاصله</th><th scope="col">نمایش موبایل</th></tr></thead>
				<tbody>
					<?php foreach ( $gaps as $gap ) : ?>
						<tr>
							<th scope="row"><?php echo esc_html( hodima_seo_advisor_breakdown_page_title( (string) $gap['path'] ) ); ?> <?php echo hodima_seo_advisor_url_link( (string) $gap['path'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML ساخته‌شده و escape‌شده در hodima_seo_advisor_url_link ?></th>
							<td><?php echo esc_html( number_format_i18n( $gap['mobile']['position'], 1 ) ); ?></td>
							<td><?php echo esc_html( number_format_i18n( $gap['desktop']['position'], 1 ) ); ?></td>
							<td><?php echo esc_html( number_format_i18n( $gap['diff'], 1 ) ); ?></td>
							<td><?php echo esc_html( $n( $gap['mobile']['impressions'] ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<div class="hodima-advisor__advice"><strong>چه کنید:</strong> صفحه را روی گوشی باز کنید و بررسی کنید: سرعت بارگذاری، محتوا یا جدولی که در موبایل پنهان یا بریده می‌شود، و دکمه‌ها و لینک‌هایی که کوچک‌اند. همان صفحه را در PageSpeed با گزینه «موبایل» امتحان کنید.</div>
		<?php
	}
	hodima_seo_ui_card_close();

	// کشورها
	if ( $country ) {
		hodima_seo_ui_card_open( 'از کدام کشورها؟', 'سهم از کل کلیک بین ۱۵ کشور اول.' );
		echo '<ul class="hodima-advisor__rivals">';
		$first = true;
		foreach ( array_slice( $country, 0, 10, true ) as $code => $stat ) {
			hodima_seo_advisor_render_share_row( hodima_seo_advisor_country_label( (string) $code ), $stat, $country_clicks > 0 ? $stat['clicks'] / $country_clicks : 0.0, $first );
			$first = false;
		}
		echo '</ul>';
		$reading = $top_share >= 0.9
			? sprintf( 'تقریبا همه کلیک (%1$s) از %2$s است؛ برای بیشتر شدن ترافیک تمرکز روی همین بازار درست است.', $pct( $top_share ), hodima_seo_advisor_country_label( $top_country ) )
			: sprintf( '%1$s از کلیک‌ها از بیرون از %2$s است. اگر به مشتری آن کشورها هم می‌فروشید، صفحه‌ها و روش ارسال و پرداخت را برای آن‌ها هم روشن کنید؛ اگر نه، دنبال ترافیک بی‌ارزش نباشید.', $pct( 1 - $top_share ), hodima_seo_advisor_country_label( $top_country ) );
		echo '<div class="hodima-advisor__advice">' . esc_html( $reading ) . '</div>';
		hodima_seo_ui_card_close();
	}

	// جستجوی تصویر و ویدیو
	hodima_seo_ui_card_open( 'جستجوی تصویر و ویدیو', 'کلیکی که از جستجوی عکس و ویدیوی گوگل می‌آید (جدا از جستجوی وب).' );
	$img_pages = (array) ( $image['pages'] ?? [] );
	printf(
		'<p class="hodima-advisor__more">%s</p>',
		esc_html( sprintf( 'تصویر: %1$s کلیک از %2$s نمایش · ویدیو: %3$s کلیک از %4$s نمایش', $n( (int) ( $image['clicks'] ?? 0 ) ), $n( (int) ( $image['impressions'] ?? 0 ) ), $n( (int) ( $video['clicks'] ?? 0 ) ), $n( (int) ( $video['impressions'] ?? 0 ) ) ) )
	);
	if ( $img_pages ) {
		echo '<ul class="hodima-advisor__rivals">';
		foreach ( array_slice( $img_pages, 0, 8 ) as $page ) {
			?>
			<li class="hodima-advisor__rival hodima-advisor__rival--url">
				<div class="hodima-advisor__rival-name"><strong><?php echo esc_html( hodima_seo_advisor_breakdown_page_title( (string) $page['path'] ) ); ?></strong><?php echo hodima_seo_advisor_url_link( (string) $page['path'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML ساخته‌شده و escape‌شده در hodima_seo_advisor_url_link ?></div>
				<span class="hodima-advisor__rival-stat"><?php echo esc_html( sprintf( '%s کلیک', $n( $page['clicks'] ) ) ); ?></span>
				<span class="hodima-advisor__rival-stat"><?php echo esc_html( sprintf( '%s نمایش', $n( $page['impressions'] ) ) ); ?></span>
			</li>
			<?php
		}
		echo '</ul>';
		echo '<div class="hodima-advisor__advice"><strong>چه کنید:</strong> برای عکس‌های محصول، نام فایل و متن جایگزین (alt) توصیفی بنویسید و عکس را سبک و واضح بگذارید؛ صفحه‌هایی که از این راه کلیک می‌گیرند اولویت دارند.</div>';
	} else {
		hodima_seo_ui_empty( 'کلیکی از جستجوی تصویر ثبت نشده', 'اگر عکس‌های محصول نام فایل و متن جایگزین (alt) توصیفی ندارند، کم‌دیده می‌شوند؛ بعد از اصلاح، این بخش پر می‌شود.' );
	}
	hodima_seo_ui_card_close();

	// نوع نمایش
	if ( $appear ) {
		hodima_seo_ui_card_open( 'نوع نمایش در گوگل', 'صفحه‌هایی که با ریچ‌اسنیپت (نتیجه غنی) نشان داده می‌شوند.' );
		echo '<ul class="hodima-advisor__rivals">';
		foreach ( $appear as $key => $stat ) {
			hodima_seo_advisor_render_share_row( hodima_seo_advisor_appearance_label( (string) $key ), $stat, $total_clicks > 0 ? min( 1.0, $stat['clicks'] / $total_clicks ) : 0.0 );
		}
		echo '</ul>';
		hodima_seo_ui_card_close();
	}

	if ( ! empty( $data['errors'] ) ) {
		echo '<p class="hodima-advisor__more">' . esc_html( 'بعضی بخش‌ها گرفته نشد (بار بعد دوباره امتحان می‌شود): ' . implode( ' | ', array_map( 'strval', (array) $data['errors'] ) ) ) . '</p>';
	}
}
