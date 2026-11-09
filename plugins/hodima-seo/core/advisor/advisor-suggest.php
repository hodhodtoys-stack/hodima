<?php
/**
 * ماژول «دستار سئو» — قاعده‌های پیشنهاد گوگل (داده Search Console)، هر قاعده یک تابع
 * Path: core/advisor/advisor-suggest.php
 *
 * همان الگوی ایرادهای فنی (advisor-checks.php، SEO 3.6.1): هر پیشنهاد یک رکورد در فهرست مرکزی
 * (HODIMA_SEO_ADVISOR_SUGGEST_RULES: نام، سختی، مرجع) و یک تابع خالص (بدون دیتابیس) که فقط ورودی را می‌خواند.
 * تا SEO ۳.۸.۱ همه پیشنهادها در یک تابع ۲۸۰ خطی (hodima_seo_advisor_suggestions) بودند و نام، سختی و فهرست قاعده‌ها
 * در سه فایل جدا تکرار می‌شد. خروجی با عکس مرجع tools/wp-harness/golden/advisor-suggest.json یکی ماند.
 *
 * ورودی هر قاعده ($in): row، queries، text، competitors، site، meta، links (همان پارامترهای
 * hodima_seo_advisor_suggestions) + cfg (hodima_seo_advisor_config). خروجی: فهرست پیشنهاد با کلیدهای
 * rule، term، title، detail، seen، report، todo، potential (افت: cause هم).
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * فهرست مرکزی پیشنهادهای گوگل: نام فارسی (منو، فیلتر، کار)، سختی رفع (۱ تا ۳؛ اولویت «کارها») و مرجع.
 * مرجع همان قالب ایرادهای فنی: `google:<صفحه>` (نشانی کامل در docs/seo-advisor-rulebook.md) یا `own` (حساب خود دستار).
 * ترتیب = ترتیب منوی «نوع پیشنهاد».
 */
const HODIMA_SEO_ADVISOR_SUGGEST_RULES = [
	'low_ctr'          => [ 'label' => 'نرخ کلیک کم', 'effort' => 1, 'ref' => 'google:title-link,snippet' ],
	'near_first'       => [ 'label' => 'کلمه نزدیک صفحه اول', 'effort' => 2, 'ref' => 'google:seo-starter-guide,links-crawlable' ],
	'missing_headings' => [ 'label' => 'کلمه در تیترها نیست', 'effort' => 1, 'ref' => 'google:seo-starter-guide' ],
	'missing_content'  => [ 'label' => 'کلمه در متن نیست', 'effort' => 2, 'ref' => 'google:seo-starter-guide' ],
	'weak_query'       => [ 'label' => 'کلمه بدون صفحه اختصاصی', 'effort' => 3, 'ref' => 'own' ],
	'cannibal'         => [ 'label' => 'رقابت داخلی', 'effort' => 3, 'ref' => 'own' ],
	'decline'          => [ 'label' => 'افت کلیک', 'effort' => 3, 'ref' => 'google:traffic-drops' ],
];

/**
 * تابع هر گروه قاعده به ترتیب اجرا و کدهایی که می‌سازد. cap = سقف همان گروه (۰ = بی‌سقف جدا)؛ outside = بیرون از سقف کل
 * پیشنهادها (رقابت داخلی: هر دو صفحه رقیب باید آن را ببینند).
 */
const HODIMA_SEO_ADVISOR_SUGGEST_RUN = [
	'low_ctr'    => [ 'fn' => 'hodima_seo_advisor_suggest_low_ctr', 'codes' => [ 'low_ctr' ], 'cap' => 0 ],
	'missing'    => [ 'fn' => 'hodima_seo_advisor_suggest_missing', 'codes' => [ 'missing_headings', 'missing_content' ], 'cap' => HODIMA_SEO_ADVISOR_MAX_PER_QUERY_RULE ],
	'near_first' => [ 'fn' => 'hodima_seo_advisor_suggest_near_first', 'codes' => [ 'near_first' ], 'cap' => HODIMA_SEO_ADVISOR_MAX_PER_QUERY_RULE ],
	'weak_query' => [ 'fn' => 'hodima_seo_advisor_suggest_weak_query', 'codes' => [ 'weak_query' ], 'cap' => HODIMA_SEO_ADVISOR_MAX_PER_QUERY_RULE ],
	'decline'    => [ 'fn' => 'hodima_seo_advisor_suggest_decline', 'codes' => [ 'decline' ], 'cap' => 0 ],
	'cannibal'   => [ 'fn' => 'hodima_seo_advisor_suggest_cannibal', 'codes' => [ 'cannibal' ], 'cap' => HODIMA_SEO_ADVISOR_MAX_CANNIBAL, 'outside' => true ],
];

/**
 * یک باکس «گزارش» (SEO 3.7.9، طرح تأییدشده کاربر): برچسب، عدد، توضیح، رنگ و کلیدهای اضافه (bar، chips).
 *
 * @param array<string, mixed> $extra
 * @return array<string, mixed>
 */
function hodima_seo_advisor_report_box( string $label, string $value, string $sub = '', string $tone = '', array $extra = [] ): array {
	return [ 'label' => $label, 'value' => $value, 'sub' => $sub, 'tone' => $tone ] + $extra;
}

/** توضیح ساده یک رتبه برای باکس «رتبه». */
function hodima_seo_advisor_position_sub( float $position, float $near_max ): string {
	return $position <= 10 ? 'صفحه اول گوگل' : ( $position <= $near_max ? 'بالای صفحه دوم؛ نزدیک صفحه اول' : ( $position <= 20 ? 'صفحه دوم گوگل' : 'بعد از صفحه دوم گوگل' ) );
}

/** نرخ کلیک (٪). */
function hodima_seo_advisor_ctr_pct( int $clicks, int $impressions ): float {
	return $impressions > 0 ? $clicks / $impressions * 100 : 0.0;
}

/**
 * کلمه‌ای که قاعده‌های کلمه (نیست در تیتر/متن، نزدیک صفحه اول، رتبه عقب) رویش اجرا می‌شوند: کلیک بالقوه و جمله «N نمایش با رتبه R».
 * null = کم‌نمایش، برندی (نرخ کلیکش از جدول عمومی بالاتر است و عدد گمراه‌کننده می‌شود) یا بی‌کلیک بالقوه.
 *
 * @param array{term: string, clicks: int, impressions: int, position: float} $query
 * @param array<string, int|float>                                          $cfg
 * @return array{gain: int, info: string}|null
 */
function hodima_seo_advisor_suggest_query_base( array $query, array $cfg ): ?array {

	if ( $query['impressions'] < $cfg['query_min_impressions'] || hodima_seo_advisor_is_brand( $query['term'] ) ) {
		return null;
	}

	$gain = (int) round( $query['impressions'] * ( hodima_seo_advisor_expected_ctr( hodima_seo_advisor_target_position( $query['position'] ) ) - hodima_seo_advisor_ctr_pct( $query['clicks'], $query['impressions'] ) ) / 100 );

	return $gain > 0 ? [ 'gain' => $gain, 'info' => sprintf( '%1$s نمایش با رتبه %2$s.', hodima_seo_advisor_num( $query['impressions'] ), hodima_seo_advisor_num( $query['position'], 1 ) ) ] : null;
}

/**
 * ۱. نرخ کلیک کمتر از حد معمول رتبه. اگر کلمه‌های ذخیره‌شده کافی‌اند، هر کلمه با نرخ معمول رتبه خودش سنجیده می‌شود، نه کل صفحه با
 * میانگین رتبه (صفحه‌ای با رتبه ۱ برای یک کلمه و ۴۰ برای ده‌ها کلمه میانگین ۱۰ دارد ولی نرخ کلیکش ذاتا از «معمولِ رتبه ۱۰» کمتر است).
 *
 * @param array<string, mixed> $in
 * @return list<array<string, mixed>>
 */
function hodima_seo_advisor_suggest_low_ctr( array $in ): array {

	[ $row, $queries, $meta, $cfg ] = [ $in['row'], $in['queries'], $in['meta'], $in['cfg'] ];

	$top      = $queries[0]['term'] ?? '';
	$position = $row['position'];
	$gap      = $row['impressions'] >= $cfg['min_impressions'] && $position > 0 ? hodima_seo_advisor_ctr_gap( $queries, $row['impressions'], $cfg['near_first_max'] ) : null;

	if ( ! ( $row['impressions'] >= $cfg['min_impressions'] && $position > 0 && ( null !== $gap || $position <= $cfg['near_first_max'] ) ) ) {
		return [];
	}

	if ( null !== $gap ) {
		[ $now, $expected, $gain ] = [ $gap['ctr'], $gap['expected_ctr'], $gap['gain'] ];
	} else {
		$now      = hodima_seo_advisor_ctr_pct( $row['clicks'], $row['impressions'] );
		$expected = hodima_seo_advisor_expected_ctr( $position );
		$gain     = (int) round( $row['impressions'] * ( $expected - $now ) / 100 );
	}

	if ( $gain <= 0 ) {
		return [];
	}

	$box     = 'hodima_seo_advisor_report_box';
	$learned = hodima_seo_advisor_ctr_learned_share() >= 0.4;
	$lead    = null !== $gap
		? sprintf(
			'کلمه‌های غیربرندی این صفحه که در ۲۰ نتیجه اول‌اند %1$s بار نمایش گرفته‌اند ولی فقط %2$s٪ کلیک شده؛ با توجه به رتبه هر کلمه معمولا حدود %3$s٪ انتظار می‌رود',
			hodima_seo_advisor_num( $gap['impressions'] ),
			hodima_seo_advisor_num( $now, 2 ),
			hodima_seo_advisor_num( $expected, 1 )
		)
		: sprintf(
			'این صفحه %1$s بار نمایش گرفته ولی فقط %2$s٪ کلیک شده؛ برای رتبه %3$s معمولا حدود %4$s٪ انتظار می‌رود',
			hodima_seo_advisor_num( $row['impressions'] ),
			hodima_seo_advisor_num( $now, 2 ),
			hodima_seo_advisor_num( $position, 1 ),
			hodima_seo_advisor_num( $expected, 1 )
		);
	$worst  = $gap['worst'] ?? null;
	$focus  = null !== $worst
		? sprintf( ' مهم‌ترین کلمه کم‌کلیک «%1$s» است (رتبه %2$s، نرخ کلیک %3$s٪ به‌جای حدود %4$s٪)؛ آن را ابتدای عنوان بیاورید.', $worst['term'], hodima_seo_advisor_num( $worst['position'], 1 ), hodima_seo_advisor_num( $worst['ctr'], 2 ), hodima_seo_advisor_num( $worst['expected_ctr'], 1 ) )
		: ( '' !== $top ? ' مهم‌ترین کلمه این صفحه «' . $top . '» است؛ آن را ابتدای عنوان بیاورید.' : '' );
	$detail = $lead . ( $learned ? ' (این نرخ معمول از داده خود سایت تنظیم شده)' : '' ) . '.' . $focus;
	$title  = 'عنوان و توضیح متا را بازنویسی کنید';
	// راهنمای پنج‌بخشی «کارها» (SEO 3.7.0): «چه دیدیم» فقط واقعیت، دستورها گام جدا؛ detail همان قبلی می‌ماند (کادر ویرایش)
	$focus_term = null !== $worst ? $worst['term'] : $top;
	$seen       = $lead . ( $learned ? ' (این نرخ معمول از داده خود سایت تنظیم شده)' : '' ) . '.'
		. ( null !== $worst ? sprintf( ' مهم‌ترین کلمه کم‌کلیک «%1$s» است (رتبه %2$s، نرخ کلیک %3$s٪ به‌جای حدود %4$s٪).', $worst['term'], hodima_seo_advisor_num( $worst['position'], 1 ), hodima_seo_advisor_num( $worst['ctr'], 2 ), hodima_seo_advisor_num( $worst['expected_ctr'], 1 ) ) : ( '' !== $top ? ' مهم‌ترین کلمه این صفحه «' . $top . '» است.' : '' ) );
	$steps      = [
		'«{where}» را بزنید؛ در کادر «سئو (SeoBox)» ← «عنوان سئو»' . ( '' !== $focus_term ? ' کلمه «' . $focus_term . '» را ابتدای عنوان بیاورید' : ' عنوان را جذاب‌تر و دقیق‌تر بنویسید' ) . '.',
		'در «توضیحات متا» یک دلیل روشن برای کلیک بنویسید (مثلا قیمت عمده، ارسال، تنوع رنگ) تا نوار شمارنده سبز شود.',
		'ذخیره کنید.',
	];

	if ( null !== $meta && $meta['healthy'] ) {
		// طبق خزش طول عنوان و متا سالم است؛ بازنویسی طولی کمکی نمی‌کند و تخمین کلیک بالقوه کم‌اطمینان‌تر است
		$title  = 'نرخ کلیک کم است؛ طول عنوان و متا سالم است';
		$detail = sprintf(
			'%1$s. طبق خزش، عنوان %2$s پیکسل و توضیح متا %3$s پیکسل است و از نظر طول ایرادی ندارد؛ پس مشکل احتمالا طول نیست. نتیجه این صفحه را در خود گوگل ببینید: عنوان جذاب و متفاوت از رقبا باشد، با قصد جستجو بخواند، و نتیجه‌های تصویر/ویدیو/دسته بالاتر از آن نباشند. فقط در صورت لزوم متن عنوان را بازنویسی کنید.',
			$lead,
			hodima_seo_advisor_num( $meta['title_px'] ),
			hodima_seo_advisor_num( $meta['meta_px'] )
		);
		$gain   = max( 1, (int) round( $gain * HODIMA_SEO_ADVISOR_LOW_CTR_HEALTHY_FACTOR ) );
		$seen   = sprintf( '%1$s. طبق خزش، عنوان %2$s پیکسل و توضیح متا %3$s پیکسل است و طولشان ایرادی ندارد؛ پس مشکل احتمالا طول نیست.', $lead, hodima_seo_advisor_num( $meta['title_px'] ), hodima_seo_advisor_num( $meta['meta_px'] ) );
		$steps  = [
			'کلمه اصلی این صفحه' . ( '' !== $focus_term ? ' («' . $focus_term . '»)' : '' ) . ' را در گوگل جستجو کنید و نتیجه خودتان را با رقبا مقایسه کنید.',
			'اگر عنوان شما شبیه رقباست: «{where}» ← کادر «سئو (SeoBox)» ← «عنوان سئو» را با مزیت خودتان (عمده، تنوع، ارسال) متفاوت کنید و ذخیره کنید.',
			'اگر بالای نتیجه شما تصویر، ویدیو یا نتیجه‌های ویژه گوگل است، از عنوان کاری برنمی‌آید؛ «لازم نیست» را بزنید.',
		];
	} elseif ( null !== $meta ) {
		$detail  .= ' طبق خزش: ' . implode( '؛ ', $meta['problems'] ) . '.';
		$seen    .= ' طبق خزش: ' . implode( '؛ ', $meta['problems'] ) . '.';
		$steps[1] = 'در «توضیحات متا» یک دلیل روشن برای کلیک بنویسید (مثلا قیمت عمده، ارسال، تنوع رنگ) و طول عنوان و متا را درست کنید تا هر دو نوار شمارنده سبز شوند.';
	}

	$report = [
		'boxes' => [
			null !== $gap
				? $box( 'نمایش در ۲۰ نتیجه اول گوگل', hodima_seo_advisor_num( $gap['impressions'] ), 'کلمه‌های این صفحه، به‌جز نام سایت' )
				: $box( 'نمایش در گوگل', hodima_seo_advisor_num( $row['impressions'] ), sprintf( 'رتبه میانگین %s', hodima_seo_advisor_num( $position, 1 ) ) ),
			$box( 'نرخ کلیک', hodima_seo_advisor_num( $now, 2 ) . '٪', sprintf( 'انتظار حدود %s٪', hodima_seo_advisor_num( $expected, 1 ) ), 'warn', [ 'bar' => $expected > 0 ? round( min( 1.0, max( 0.0, $now / $expected ) ), 2 ) : 0.0 ] ),
			null !== $worst
				? $box( 'کم‌کلیک‌ترین کلمه', '«' . $worst['term'] . '»', sprintf( 'رتبه %1$s · کلیک %2$s٪ به‌جای حدود %3$s٪', hodima_seo_advisor_num( $worst['position'], 1 ), hodima_seo_advisor_num( $worst['ctr'], 2 ), hodima_seo_advisor_num( $worst['expected_ctr'], 1 ) ) )
				: ( '' !== $top ? $box( 'مهم‌ترین کلمه', '«' . $top . '»' ) : null ),
		],
		'foot'  => $learned ? '«انتظار» از آمار خود سایت شما برای همین رتبه‌ها حساب شده است.' : '',
	];
	if ( null !== $meta && $meta['healthy'] ) {
		$report['note'] = [ 'tone' => 'info', 'title' => 'طبق خزش', 'text' => sprintf( 'عنوان %1$s پیکسل و توضیح متا %2$s پیکسل است و طولشان ایرادی ندارد؛ پس مشکل احتمالا طول نیست.', hodima_seo_advisor_num( $meta['title_px'] ), hodima_seo_advisor_num( $meta['meta_px'] ) ) ];
	} elseif ( null !== $meta && $meta['problems'] ) {
		$report['note'] = [ 'tone' => 'warn', 'title' => 'علت احتمالی (طبق خزش)', 'text' => implode( '؛ ', $meta['problems'] ) . '.' ];
	}
	$report['boxes'] = array_values( array_filter( $report['boxes'] ) );

	return [
		[
			'rule'      => 'low_ctr',
			'term'      => '',
			'title'     => $title,
			'detail'    => $detail,
			'seen'      => $seen,
			'report'    => $report,
			'todo'      => $steps,
			'potential' => $gain,
		],
	];
}

/**
 * ۲. کلمه نزدیک صفحه اول یا در صفحه اول که در عنوان و تیترها نیست (در متن هست: missing_headings؛ در متن هم نیست: missing_content).
 * متن صفحه ناشناخته (null) ← اجرا نمی‌شود.
 *
 * @param array<string, mixed> $in
 * @return list<array<string, mixed>>
 */
function hodima_seo_advisor_suggest_missing( array $in ): array {

	[ $text, $cfg ] = [ $in['text'], $in['cfg'] ];

	if ( null === $text ) {
		return [];
	}

	$box = 'hodima_seo_advisor_report_box';
	$out = [];

	foreach ( $in['queries'] as $query ) {
		$base = hodima_seo_advisor_suggest_query_base( $query, $cfg );
		if ( null === $base || $query['position'] > $cfg['near_first_max'] || hodima_seo_advisor_text_has( $text['title_headings'], $query['term'] ) ) {
			continue;
		}

		$info       = $base['info'];
		$in_content = hodima_seo_advisor_text_has( $text['content'], $query['term'] );
		$rendered   = isset( $text['rendered'] ) ? sprintf( ' طبق خزش، عنوان فعلی «%1$s» و H1 «%2$s» است.', $text['rendered']['title'], $text['rendered']['h1'] ) : '';
		$out[]      = [
			'rule'      => $in_content ? 'missing_headings' : 'missing_content',
			'term'      => $query['term'],
			'title'     => $in_content ? 'کلمه «' . $query['term'] . '» در عنوان و تیترها نیست' : 'کلمه «' . $query['term'] . '» در متن صفحه نیست',
			'detail'    => $info . ( $in_content ? ' آن را در عنوان یا یک تیتر (H2) بیاورید.' : ' آن را در عنوان، یک تیتر و پاراگراف اول بیاورید، یا بخش پرسش‌های متداول برایش بنویسید.' ) . $rendered,
			'seen'      => $info . ( $in_content ? ' کلمه «' . $query['term'] . '» در متن صفحه هست ولی در عنوان و تیترها نیست.' : ' کلمه «' . $query['term'] . '» حتی در متن صفحه نیست.' ) . $rendered,
			'report'    => [
				'boxes' => [
					$box( 'نمایش این کلمه', hodima_seo_advisor_num( $query['impressions'] ) ),
					$box( 'رتبه', hodima_seo_advisor_num( $query['position'], 1 ), hodima_seo_advisor_position_sub( (float) $query['position'], (float) $cfg['near_first_max'] ) ),
					$box( 'این کلمه در صفحه', '', '', 'warn', [ 'chips' => [ [ false, 'عنوان و تیترها' ], [ $in_content, 'متن' ] ] ] ),
				],
				'note'  => isset( $text['rendered'] ) ? [ 'tone' => 'info', 'title' => 'طبق خزش', 'text' => sprintf( 'عنوان فعلی «%1$s» و H1 «%2$s» است.', $text['rendered']['title'], $text['rendered']['h1'] ) ] : null,
			],
			'todo'      => $in_content
				? [ '«{where}» ← کادر «سئو (SeoBox)» ← «عنوان سئو»: کلمه «' . $query['term'] . '» را در عنوان بیاورید.', 'یا در ویرایشگر یک تیتر (تیتر ۲) با همین کلمه بسازید.', 'ذخیره کنید.' ]
				: [ '«{where}» ← کادر «سئو (SeoBox)» ← «عنوان سئو»: کلمه «' . $query['term'] . '» را در عنوان بیاورید.', 'در متن صفحه یک تیتر ۲ و پاراگراف اول با همین کلمه بنویسید (یا یک بخش پرسش‌های متداول درباره آن).', 'ذخیره کنید.' ],
			'potential' => $base['gain'],
		];
	}

	return $out;
}

/**
 * ۳. کلمه با رتبه ۸ تا ۲۰ (نزدیک صفحه اول): تقویت در عنوان، تیتر، پاراگراف اول و لینک داخلی (با شاهد لینک‌های ورودی خزش).
 *
 * @param array<string, mixed> $in
 * @return list<array<string, mixed>>
 */
function hodima_seo_advisor_suggest_near_first( array $in ): array {

	[ $links, $cfg ] = [ $in['links'], $in['cfg'] ];

	$box = 'hodima_seo_advisor_report_box';
	$out = [];

	foreach ( $in['queries'] as $query ) {
		$base = hodima_seo_advisor_suggest_query_base( $query, $cfg );
		if ( null === $base || $query['position'] < $cfg['near_first_min'] || $query['position'] > $cfg['near_first_max'] ) {
			continue;
		}

		$info     = $base['info'];
		$evidence = hodima_seo_advisor_links_evidence_parts( $links, $query['term'] );
		$out[]    = [
			'rule'      => 'near_first',
			'term'      => $query['term'],
			'title'     => 'کلمه «' . $query['term'] . '» نزدیک صفحه اول است',
			'detail'    => $info . ' آن را در عنوان، یک تیتر و اولین پاراگراف تقویت کنید و از ۲ تا ۳ صفحه مرتبط به این صفحه لینک بدهید.' . hodima_seo_advisor_links_evidence( $links, $query['term'] ),
			'seen'      => trim( $info . ' این کلمه صفحه شما را تا نزدیک صفحه اول گوگل آورده.' . implode( '', $evidence['seen'] ) ),
			'report'    => [
				'boxes' => [
					$box( 'نمایش این کلمه', hodima_seo_advisor_num( $query['impressions'] ) ),
					$box( 'رتبه', hodima_seo_advisor_num( $query['position'], 1 ), hodima_seo_advisor_position_sub( (float) $query['position'], (float) $cfg['near_first_max'] ) ),
				],
				'note'  => $evidence['seen'] ? [ 'tone' => 'info', 'title' => 'لینک‌های ورودی (طبق خزش)', 'text' => trim( implode( '', $evidence['seen'] ) ) ] : null,
			],
			'todo'      => [ '«{where}» ← کادر «سئو (SeoBox)» ← «عنوان سئو»: کلمه «' . $query['term'] . '» را در عنوان بیاورید (اگر نیست).', 'در متن صفحه یک تیتر ۲ و پاراگراف اول با همین کلمه بنویسید و ذخیره کنید.', ...( $evidence['steps'] ?: ( $evidence['well'] ? [] : [ 'از ۲ تا ۳ صفحه مرتبط (دسته، مقاله) با متن لینک «' . $query['term'] . '» به این صفحه لینک بدهید.' ] ) ) ],
			'potential' => $base['gain'],
		];
	}

	return $out;
}

/**
 * ۴. رتبه خیلی عقب (بعد از صفحه دوم تا weak_max_position): این صفحه فقط حاشیه‌ای به کلمه جواب می‌دهد؛ صفحه یا بخش اختصاصی لازم است.
 *
 * @param array<string, mixed> $in
 * @return list<array<string, mixed>>
 */
function hodima_seo_advisor_suggest_weak_query( array $in ): array {

	$cfg = $in['cfg'];
	$box = 'hodima_seo_advisor_report_box';
	$out = [];

	foreach ( $in['queries'] as $query ) {
		$base = hodima_seo_advisor_suggest_query_base( $query, $cfg );
		if ( null === $base || $query['position'] <= $cfg['near_first_max'] || $query['position'] > $cfg['weak_max_position'] ) {
			continue;
		}

		$info  = $base['info'];
		$out[] = [
			'rule'      => 'weak_query',
			'term'      => $query['term'],
			'title'     => 'برای «' . $query['term'] . '» صفحه یا بخش اختصاصی بنویسید',
			'detail'    => $info . ' این صفحه جواب اصلی این کلمه نیست. یک مقاله یا صفحه (یا دست‌کم یک بخش با تیتر) مخصوص همین کلمه بسازید و از این صفحه و صفحه‌های مرتبط به آن لینک بدهید.',
			'seen'      => $info . ' گوگل این صفحه را برای «' . $query['term'] . '» فقط بعد از صفحه دوم نتیجه‌ها نشان می‌دهد؛ یعنی این صفحه جواب اصلی این کلمه نیست.',
			'report'    => [
				'boxes' => [
					$box( 'نمایش این کلمه', hodima_seo_advisor_num( $query['impressions'] ) ),
					$box( 'رتبه', hodima_seo_advisor_num( $query['position'], 1 ), hodima_seo_advisor_position_sub( (float) $query['position'], (float) $cfg['near_first_max'] ), 'warn' ),
				],
				'note'  => [ 'tone' => 'info', 'title' => 'یعنی', 'text' => 'این صفحه جواب اصلی این کلمه نیست؛ گوگل آن را فقط حاشیه‌ای برای «' . $query['term'] . '» نشان می‌دهد.' ],
			],
			'todo'      => [ 'اگر این کلمه برایتان مهم نیست، «لازم نیست» را بزنید.', 'وگرنه یک مقاله یا صفحه (یا دست‌کم یک بخش با تیتر ۲) مخصوص «' . $query['term'] . '» بسازید.', 'از همین صفحه و صفحه‌های مرتبط با متن لینک «' . $query['term'] . '» به آن لینک بدهید.' ],
			'potential' => $base['gain'],
		];
	}

	return $out;
}

/**
 * ۵. رقابت داخلی (فقط اطلاع؛ بدون کلیک بالقوه): هر کلمه رقابتی یک ردیف، به ترتیب کلمه‌ها (پرنمایش‌ترین اول)، تا سقف.
 *
 * @param array<string, mixed> $in
 * @return list<array<string, mixed>>
 */
function hodima_seo_advisor_suggest_cannibal( array $in ): array {

	[ $row, $competitors, $cfg ] = [ $in['row'], $in['competitors'], $in['cfg'] ];

	$out = [];

	foreach ( $in['queries'] as $query ) {
		$others = $competitors[ hodima_seo_advisor_term_key( $query['term'] ) ] ?? $competitors[ $query['term'] ] ?? [];
		if ( $query['impressions'] < $cfg['cannibal_min_impressions'] || ! $others || hodima_seo_advisor_is_brand( $query['term'] ) ) {
			continue;
		}

		$out[] = [
			'rule'      => 'cannibal',
			'term'      => $query['term'],
			'title'     => sprintf( 'رقابت با %1$s صفحه برای «%2$s»', hodima_seo_advisor_num( count( $others ) ), $query['term'] ),
			// آدرس کامل (نه فقط مسیر) تا مدیر همان را کپی کند و باز کند
			'detail'    => 'علاوه بر این صفحه، ' . implode( '، ', array_map( static fn( array $o ): string => rawurldecode( hodima_seo_advisor_url( $o['path'] ) ), array_slice( $others, 0, 3 ) ) ) . ' هم برای این کلمه نمایش می‌گیرد. یکی را صفحه اصلی کلمه کنید و بقیه را با لینک داخلی به آن وصل کنید.',
			'seen'      => 'علاوه بر این صفحه، ' . implode( '، ', array_map( static fn( array $o ): string => rawurldecode( hodima_seo_advisor_url( $o['path'] ) ), array_slice( $others, 0, 3 ) ) ) . ' هم برای «' . $query['term'] . '» در گوگل نمایش می‌گیرد.',
			'report'    => [
				'intro' => sprintf( '%1$s صفحه سایت شما برای «%2$s» در گوگل نشان داده می‌شوند', hodima_seo_advisor_num( count( $others ) + 1 ), $query['term'] ),
				'pages' => [
					[ 'badge' => 'همین صفحه', 'win' => false, 'path' => (string) $row['path'], 'title' => '', 'impressions' => (int) $query['impressions'], 'clicks' => (int) $query['clicks'], 'position' => (float) $query['position'] ],
					...array_map( static fn( array $o ): array => [ 'badge' => 'صفحه دیگر', 'win' => false, 'path' => (string) $o['path'], 'title' => '', 'impressions' => (int) $o['impressions'], 'clicks' => -1, 'position' => -1.0 ], array_slice( $others, 0, 3 ) ),
				],
			],
			'todo'      => [ 'از این صفحه‌ها یکی را صفحه اصلی «' . $query['term'] . '» کنید (معمولا همان که نمایش بیشتری دارد).', 'در صفحه‌های دیگر یک لینک با متن «' . $query['term'] . '» به صفحه اصلی بدهید.', 'کلمه را از «عنوان سئو»ی صفحه‌های دیگر بردارید یا با کلمه دقیق‌تری عوض کنید.' ],
			'potential' => 0,
		];
	}

	return $out;
}

/**
 * ۶. افت کلیک نسبت به انتظار: اگر کل سایت هم افت کرده باشد، انتظار همراه آن پایین می‌آید و فقط افت اضافه این صفحه پیشنهاد می‌شود
 * (افتی که کل سایت دارد علت مخصوص این صفحه ندارد).
 *
 * @param array<string, mixed> $in
 * @return list<array<string, mixed>>
 */
function hodima_seo_advisor_suggest_decline( array $in ): array {

	[ $row, $site, $cfg ] = [ $in['row'], $in['site'], $in['cfg'] ];

	$site_ratio = $site['clicks_ratio'] ?? null;
	$expected   = $row['prev_clicks'] * ( null === $site_ratio ? 1.0 : max( 0.1, min( 1.0, $site_ratio ) ) );

	if ( ! ( $row['prev_clicks'] >= $cfg['decline_min_clicks'] && $row['clicks'] < $row['prev_clicks'] * $cfg['decline_ratio'] && $row['clicks'] < $expected * HODIMA_SEO_ADVISOR_DECLINE_BEYOND_SITE ) ) {
		return [];
	}

	$box       = 'hodima_seo_advisor_report_box';
	$diagnosis = hodima_seo_advisor_decline_cause( $row, $site );
	$drop      = hodima_seo_advisor_num( ( 1 - $row['clicks'] / $row['prev_clicks'] ) * 100 );

	return [
		[
			'rule'      => 'decline',
			'term'      => '',
			'cause'     => $diagnosis['cause'],
			'title'     => 'کلیک این صفحه افت کرده',
			'detail'    => sprintf( 'کلیک از %1$s به %2$s رسیده (%3$s٪ کمتر از بازه قبل). %4$s', hodima_seo_advisor_num( $row['prev_clicks'] ), hodima_seo_advisor_num( $row['clicks'] ), $drop, $diagnosis['text'] ),
			'seen'      => sprintf( 'کلیک از %1$s به %2$s رسیده (%3$s٪ کمتر از بازه قبل). %4$s', hodima_seo_advisor_num( $row['prev_clicks'] ), hodima_seo_advisor_num( $row['clicks'] ), $drop, $diagnosis['seen'] ),
			'report'    => [
				'boxes' => [
					$box( 'کلیک این بازه', hodima_seo_advisor_num( $row['clicks'] ), sprintf( 'بازه قبل %s', hodima_seo_advisor_num( $row['prev_clicks'] ) ), 'warn' ),
					$box( 'افت', $drop . '٪', 'نسبت به بازه قبل', 'warn' ),
					$box( 'رتبه', hodima_seo_advisor_num( (float) $row['position'], 1 ), (float) ( $row['prev_position'] ?? 0 ) > 0 ? sprintf( 'بازه قبل %s', hodima_seo_advisor_num( (float) $row['prev_position'], 1 ) ) : '' ),
				],
				'note'  => [ 'tone' => 'warn', 'title' => 'علت', 'text' => preg_replace( '/^علت اصلی: /u', '', $diagnosis['seen'] ) ],
			],
			'todo'      => $diagnosis['todo'],
			'potential' => (int) max( 0, round( $expected - $row['clicks'] ) ),
		],
	];
}
