<?php
/**
 * ماژول «دستار سئو» — صفحه «کارها»: یک فهرست واحد همه ایرادها (SEO 3.3.0)
 * Path: core/advisor/advisor-overview-view.php
 *
 * هر صفحه یک ردیف؛ زیر آن هر ایراد یک خط با نوع، «چه کنید» و دکمه‌های «انجام دادم / لازم نیست». شش منبع در یک فهرست:
 * ایراد فنی خزنده، پیشنهاد گوگل، هم‌کلمه، آدرس خراب، صفحه بدون نمایش، وضعیت ایندکس. علامت‌ها همان کارهای قبلی‌اند
 * (hodima_advisor_mark، advisor-marks.php)؛ پس سنجش و تاریخچه «کارهای انجام‌شده» همان می‌ماند.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** ردیف (صفحه) در هر صفحه فهرست «کارها». */
const HODIMA_SEO_ADVISOR_OVERVIEW_PER_PAGE = 20;

/** نوع ایراد ← برچسب فیلتر و چیپ ردیف. */
const HODIMA_SEO_ADVISOR_OVERVIEW_SOURCES = [
	'crawl'  => 'ایراد فنی',
	'opp'    => 'پیشنهاد گوگل',
	'comp'   => 'هم‌کلمه',
	'dead'   => 'آدرس خراب',
	'unseen' => 'بدون نمایش',
	'index'  => 'ایندکس',
];

/**
 * ایرادهای باز هر صفحه از همه منبع‌ها (مواردی که «انجام دادم / لازم نیست» خورده‌اند نمی‌آیند).
 *
 * @return list<array{path: string, title: string, impressions: int, items: list<array<string, mixed>>, sales: int, score: float}>
 */
function hodima_seo_advisor_overview_build(): array {

	$pages     = [];
	$comp_done = hodima_seo_advisor_competition_tasks();

	$add = static function ( string $path, array $item ) use ( &$pages ): void {
		$pages[ $path ]['items'][] = $item;
	};
	$imp = static function ( string $path, int $value ) use ( &$pages ): void {
		$pages[ $path ]['impressions'] = max( (int) ( $pages[ $path ]['impressions'] ?? 0 ), $value );
	};

	// ایرادهای فنی خزنده
	foreach ( hodima_seo_advisor_crawl_issues() as $row ) {
		$path = $row['path'];
		$imp( $path, (int) $row['impressions'] );
		foreach ( $row['issues'] as $issue ) {
			$code = (string) $issue['code'];
			$info = hodima_seo_advisor_crawl_issue_info( $code );
			if ( '' === $info['fix'] || null !== hodima_seo_advisor_task_closed( $path, 'crawl_' . $code ) ) {
				continue;
			}
			$add( $path, [ 'src' => 'crawl', 'label' => $info['label'], 'text' => '' !== (string) $issue['detail'] ? $issue['detail'] . ' ' . $info['fix'] : $info['fix'], 'detail' => (string) $issue['detail'], 'images' => (array) ( $issue['images'] ?? [] ), 'total' => (int) ( $issue['total'] ?? 0 ), 'marked' => (array) ( $issue['marked'] ?? [] ), 'targets' => (array) ( $issue['items'] ?? [] ), 'url' => (string) ( $issue['url'] ?? '' ), 'level' => (string) ( $issue['level'] ?? $info['level'] ), 'source' => 'crawl', 'mark' => $code, 'term' => '' ] ); // راهنما (guide) هنگام نمایش با نام «ویرایش» همان صفحه ساخته می‌شود
		}
	}

	// پیشنهادهای گوگل
	foreach ( hodima_seo_advisor_pages( [ 'has_advice' => true, 'limit' => 500 ] )['rows'] as $row ) {
		$path = (string) $row['path'];
		$imp( $path, (int) $row['impressions'] );
		$pages[ $path ]['title'] = (string) $row['title'];
		foreach ( $row['advice'] as $advice ) {
			$rule = (string) ( $advice['rule'] ?? '' );
			$term = (string) ( $advice['term'] ?? '' );
			if ( '' === $rule || null !== hodima_seo_advisor_task_closed( $path, $rule, $term ) ) {
				continue;
			}
			$add( $path, [ 'src' => 'opp', 'label' => hodima_seo_advisor_rule_label( $rule ), 'text' => (string) ( $advice['detail'] ?? '' ), 'level' => 'warn', 'source' => 'opportunities', 'mark' => $rule . '|' . $term, 'term' => '', 'rule' => $rule, 'rule_term' => $term, 'potential' => (int) ( $advice['potential'] ?? 0 ), 'guide' => [ 'report' => is_array( $advice['report'] ?? null ) ? $advice['report'] : [] ] + hodima_seo_advisor_overview_guide( (string) ( $advice['seen'] ?? $advice['detail'] ?? '' ), 'rec', array_values( array_map( 'strval', (array) ( $advice['todo'] ?? [] ) ) ), $rule ) ] ); // seen/todo از SEO 3.7.0؛ report (باکس‌ها) از 3.7.9؛ پیشنهاد ذخیره‌شده قدیمی‌تر تا دریافت داده بعدی همان متن را نشان می‌دهد
		}
	}

	// صفحه‌های هم‌کلمه
	foreach ( hodima_seo_advisor_competition_groups()['groups'] as $group ) {
		$term = (string) $group['term'];
		$done = $comp_done[ hodima_seo_advisor_normalize( $term ) ] ?? null;
		if ( null !== $done && in_array( hodima_seo_advisor_task_state( $done ), HODIMA_SEO_ADVISOR_TASKS_CLOSED, true ) ) {
			continue;
		}
		$norm = hodima_seo_advisor_normalize( $term );
		foreach ( (array) ( $group['pages'] ?? [] ) as $page ) {
			$path = (string) ( $page['path'] ?? '' );
			if ( '' === $path ) {
				continue;
			}
			// یک کار برای یک مشکل (SEO 3.7.8، خواست کاربر): «رقابت داخلی» پیشنهاد گوگل و «رقابت برای «…»» هم‌کلمه برای همان صفحه و همان
			// کلمه یک کار می‌شوند. کار هم‌کلمه می‌ماند (گام‌های برنده/بازنده دارد و سنجشش گروهی است)؛ اگر نسخه پیشنهاد گوگل را قبلا علامت زده‌اید، دوباره نمی‌آید
			$dup = false;
			foreach ( (array) ( $pages[ $path ]['items'] ?? [] ) as $i => $existing ) {
				if ( 'opp' === $existing['src'] && 'cannibal' === ( $existing['rule'] ?? '' ) && hodima_seo_advisor_normalize( (string) ( $existing['rule_term'] ?? '' ) ) === $norm ) {
					unset( $pages[ $path ]['items'][ $i ] );
					$dup = true;
				}
			}
			if ( $dup ) {
				$pages[ $path ]['items'] = array_values( $pages[ $path ]['items'] );
			}
			if ( null !== hodima_seo_advisor_task_closed( $path, 'cannibal', $term ) ) {
				continue;
			}
			$add( $path, [ 'src' => 'comp', 'label' => hodima_seo_advisor_rule_label( 'cannibal' ), 'rule' => 'cannibal', 'rule_term' => $term, 'text' => (string) ( $group['advice']['detail'] ?? 'چند صفحه برای یک کلمه رقابت می‌کنند. صفحه برنده را روشن کنید و بقیه را با لینک داخلی به آن وصل کنید.' ), /* باگ تا SEO 3.6.9: $group['detail'] وجود نداشت و همیشه جمله پیش‌فرض می‌آمد */ 'level' => 'warn', 'source' => 'competition', 'mark' => '', 'term' => $term, 'guide' => hodima_seo_advisor_competition_guide( $group, $path ) ] );
		}
	}

	// آدرس‌های خراب
	// نتیجه ذخیره‌شده دریافت روزانه (SEO 3.4.3): تا ۳.۴.۲ هر بازدید «کارها» ۲۰۰ آدرس را با روتر و ۳٬۰۰۰ نوشته را از نو می‌سنجید
	$health = hodima_seo_advisor_health();
	foreach ( (array) ( $health['dead'] ?? [] ) as $row ) {
		$path = (string) $row['path'];
		if ( ! in_array( $row['state'], [ 'missing', 'gone', 'rule_broken' ], true ) || null !== hodima_seo_advisor_task_closed( $path, 'health_dead' ) ) {
			continue;
		}
		$imp( $path, (int) $row['impressions'] );
		$add( $path, [ 'src' => 'dead', 'label' => 'آدرس خراب در گوگل', 'text' => 'rule_broken' === $row['state'] ? 'این آدرس هنوز در گوگل است ولی مقصد ریدایرکتش خراب است؛ مقصد را اصلاح کنید.' : 'این آدرس هنوز در گوگل است ولی در سایت پیدا نمی‌شود؛ در «ریدایرکت‌ها» یک ۳۰۱ به نزدیک‌ترین صفحه درست بگذارید.', 'level' => 'bad', 'source' => 'health', 'mark' => 'dead', 'term' => '', 'guide' => hodima_seo_advisor_overview_guide(
			'rule_broken' === $row['state'] ? 'این آدرس هنوز در گوگل است ولی مقصد ریدایرکتش خراب است.' : 'این آدرس هنوز در گوگل است ولی در سایت پیدا نمی‌شود.',
			'must',
			'rule_broken' === $row['state']
				? [ 'به «ابزارهای هدیما ← ریدایرکت‌ها» بروید و این آدرس را جستجو کنید.', 'مقصد قانون آن را یک صفحه سالم بگذارید و ذخیره کنید.' ]
				: [ 'به «ابزارهای هدیما ← ریدایرکت‌ها» بروید.', 'یک ریدایرکت ۳۰۱ از این آدرس به نزدیک‌ترین صفحه سالم (مثلا دسته همان محصول) بسازید.' ],
			'',
			'بعد از ذخیره، «انجام دادم» را بزنید؛ دستار همان لحظه آدرس را می‌سنجد.',
			'کسی که از گوگل روی این آدرس کلیک کند به بن‌بست می‌خورد؛ ریدایرکت بازدیدکننده و اعتبار آدرس را به صفحه درست می‌برد.'
		) ] );
	}

	// صفحه‌های بدون نمایش
	foreach ( (array) ( $health['unseen'] ?? [] ) as $row ) {
		$path = (string) $row['path'];
		if ( null !== hodima_seo_advisor_task_closed( $path, 'health_unseen' ) ) {
			continue;
		}
		$pages[ $path ]['title'] ??= (string) $row['title'];
		$add( $path, [ 'src' => 'unseen', 'label' => 'بدون نمایش در گوگل', 'text' => sprintf( 'این صفحه %s روز است در گوگل نمایش نگرفته. از صفحه‌های مرتبط به آن لینک بدهید و عنوان و متنش را به یک جستجوی مشخص نزدیک کنید.', number_format_i18n( (int) $row['days'] ) ), 'level' => 'warn', 'source' => 'health', 'mark' => 'unseen', 'term' => '', 'guide' => hodima_seo_advisor_overview_guide(
			sprintf( 'این صفحه %s روز است در گوگل حتی یک بار نمایش نگرفته.', number_format_i18n( (int) $row['days'] ) ),
			'opt',
			[ 'اگر این صفحه برایتان مهم نیست، کاری لازم نیست؛ «لازم نیست» را بزنید.', 'از ۲ تا ۳ صفحه مرتبط (دسته، مقاله) به آن لینک بدهید.', '«ویرایش» ← کادر «سئو (SeoBox)» ← «عنوان سئو» را به یک جستجوی مشخص نزدیک کنید (همان کلمه‌ای که مردم واقعا می‌نویسند).' ],
			'',
			'با آمار بعدی گوگل: وقتی صفحه نمایش گرفت، خودش از فهرست می‌رود.',
			'گوگل این صفحه را برای هیچ جستجویی مناسب ندیده؛ لینک و عنوان روشن کمکش می‌کند.'
		) ] );
	}

	// وضعیت ایندکس
	foreach ( hodima_seo_advisor_inspections() as $path => $record ) {
		$state = hodima_seo_advisor_inspect_state( $record );
		if ( 'indexed' === $state || null !== hodima_seo_advisor_task_closed( (string) $path, 'index_problem' ) ) {
			continue;
		}
		$info = hodima_seo_advisor_inspect_state_info( $state );
		$add( (string) $path, [ 'src' => 'index', 'label' => 'ایندکس: ' . $info['label'], 'text' => $info['advice'], 'level' => 'warn', 'source' => 'indexing', 'mark' => '', 'term' => '', 'guide' => hodima_seo_advisor_overview_guide( (string) ( $info['seen'] ?? $info['advice'] ), 'info' === ( $info['need'] ?? '' ) ? 'opt' : 'rec', (array) ( $info['steps'] ?? [] ), '', 'بعد از تغییر «انجام دادم» را بزنید؛ با بررسی ایندکس بعدی گوگل (روزانه) نتیجه همین‌جا می‌آید.', (string) ( $info['why'] ?? '' ) ) ] );
	}

	$out = [];
	foreach ( $pages as $path => $data ) {
		if ( empty( $data['items'] ) ) {
			continue;
		}
		$out[] = [
			'path'        => (string) $path,
			'title'       => (string) ( $data['title'] ?? '' ),
			'impressions' => (int) ( $data['impressions'] ?? 0 ),
			'items'       => $data['items'],
		];
	}

	return hodima_seo_advisor_priority_rank( $out ); // به ترتیب اولویت: بیشترین اثر با کمترین زحمت اول (SEO 3.4.0؛ قبلا فقط نمایش گوگل)
}

/**
 * راهنمای پنج‌بخشی یک مورد غیرخزنده (SEO 3.6.9): چه دیدیم، لازم است؟، گام‌ها، از کجا بفهمم، چرا مهم است.
 * `$rule` = قاعده پیشنهاد گوگل؛ «چرا» و «از کجا بفهمم» آن از hodima_seo_advisor_rule_guide (همان متن کادر ویرایش) می‌آید.
 *
 * @param list<string> $steps
 * @return array{seen: string, need: string, steps: list<string>, verify: string, why: string}
 */
function hodima_seo_advisor_overview_guide( string $seen, string $need, array $steps = [], string $rule = '', string $verify = '', string $why = '' ): array {

	$rule_guide = '' !== $rule && function_exists( 'hodima_seo_advisor_rule_guide' ) ? hodima_seo_advisor_rule_guide( $rule ) : [ 'why' => '', 'verify' => '' ];

	return [
		'seen'   => trim( $seen ),
		'need'   => $need,
		'steps'  => $steps,
		'verify' => '' !== $verify ? $verify : $rule_guide['verify'],
		'why'    => '' !== $why ? $why : $rule_guide['why'],
	];
}

/**
 * راهنمای یک گروه رقابت (هم‌کلمه) برای یک صفحه آن (SEO 3.7.0): رقیب‌ها با نمایش، و گام‌ها بر پایه نوع دستور گروه
 * (دسته / برنده روشن / انتخاب) و شواهد لینک.
 *
 * @param array<string, mixed> $group گروه hodima_seo_advisor_competition_groups
 * @return array{seen: string, need: string, steps: list<string>, verify: string, why: string}
 */
function hodima_seo_advisor_competition_guide( array $group, string $path ): array {

	$term   = (string) $group['term'];
	$pages  = (array) ( $group['pages'] ?? [] );
	$winner = (array) ( $pages[0] ?? [] );
	$url    = static fn( array $p ): string => rawurldecode( hodima_seo_advisor_url( (string) ( $p['path'] ?? '' ) ) );
	$share  = static fn( array $p ): string => number_format_i18n( (float) ( $p['share'] ?? 0 ) * 100 ) . '٪';
	$others = array_values( array_filter( $pages, static fn( array $p ): bool => (string) ( $p['path'] ?? '' ) !== $path ) );
	$is_win = (string) ( $winner['path'] ?? '' ) === $path;
	$type   = (string) ( $group['advice']['type'] ?? 'choose' );

	$seen = sprintf(
		'%1$s صفحه سایت برای «%2$s» در گوگل نمایش می‌گیرند. %3$s',
		number_format_i18n( count( $pages ) ),
		$term,
		$is_win
			? sprintf( 'این صفحه بیشترین سهم را دارد (%1$s)؛ رقیب‌ها: %2$s.', $share( $winner ), implode( '، ', array_map( static fn( array $p ): string => $url( $p ) . ' (' . $share( $p ) . ')', array_slice( $others, 0, 3 ) ) ) )
			: sprintf( 'سهم این صفحه کمتر است؛ بیشترین سهم با %1$s (%2$s) است.', $url( $winner ), $share( $winner ) )
	);

	// «دقیقا چه کنم» با داده واقعی آخرین خزش (SEO 3.8.0، خواست کاربر): عنوان سئوی فعلی، تیترهای ۲ دارای کلمه و لینک بین صفحه‌ها خوانده
	// می‌شوند؛ گامی که انجام شده (کلمه در عنوان نیست، لینک با همین متن هست) نمی‌آید. صفحه‌ای که خزیده نشده گام کلی همان قبلی را می‌گیرد.
	$name     = static fn( array $p ): string => '' !== (string) ( $p['title'] ?? '' ) ? (string) $p['title'] : rawurldecode( (string) ( $p['path'] ?? '' ) );
	$crawl    = static fn( string $p ): ?array => hodima_seo_advisor_crawl_row( $p );
	$h2_with  = static function ( string $p ) use ( $crawl, $term ): array {
		$row = $crawl( $p );
		return null === $row ? [] : array_values( array_filter( array_map( 'trim', explode( ' | ', $row['h2'] ) ), static fn( string $h ): bool => '' !== $h && hodima_seo_advisor_text_has( $h, $term ) ) );
	};
	$has_term = static fn( array $anchors ): bool => (bool) array_filter( $anchors, static fn( string $a ): bool => hodima_seo_advisor_text_has( $a, $term ) );
	$focus    = '';
	foreach ( hodima_seo_advisor_queries( $path, 8 ) as $q ) {
		if ( hodima_seo_advisor_normalize( (string) $q['term'] ) !== hodima_seo_advisor_normalize( $term ) && ! hodima_seo_advisor_is_brand( (string) $q['term'] ) ) {
			$focus = (string) $q['term'];
			break;
		}
	}
	$w_path = (string) ( $winner['path'] ?? '' );

	if ( 'choose' === $type ) {
		$steps = [
			'این صفحه‌ها را باز کنید و مقایسه کنید: ' . implode( '، ', array_map( static fn( array $p ): string => '«' . $name( $p ) . '» ' . $url( $p ), array_slice( $pages, 0, 4 ) ) ) . '.',
			'اگر محتوایشان یکی است: یکی را نگه دارید (پیشنهاد: «' . $name( $winner ) . '» با بیشترین کلیک) و بقیه را در «ابزارهای هدیما ← ریدایرکت‌ها» با ۳۰۱ به آن بفرستید.',
			'اگر جدا هستند: موضوع هر صفحه را در «عنوان سئو»ی خودش روشن کنید و فقط صفحه اصلی را با «' . $term . '» تقویت کنید.',
		];
	} elseif ( ! $is_win ) {
		$steps = [];
		$row   = $crawl( $path );
		if ( null === $row ) {
			$steps[] = 'اگر «' . $term . '» در «عنوان سئو»ی همین صفحه هست: «{where}» ← کادر «سئو (SeoBox)» ← «عنوان سئو»، آن را بردارید' . ( '' !== $focus ? ' و روی «' . $focus . '» تمرکز کنید' : '' ) . '.';
		} elseif ( hodima_seo_advisor_text_has( $row['title'], $term ) ) {
			$steps[] = '«{where}» ← کادر «سئو (SeoBox)» ← «عنوان سئو»: عنوان فعلی «' . $row['title'] . '» کلمه «' . $term . '» را دارد؛ آن را بردارید' . ( '' !== $focus ? ' و روی کلمه اصلی همین صفحه («' . $focus . '») تمرکز کنید' : '' ) . '.';
		}
		$h2s = $h2_with( $path );
		if ( $h2s ) {
			$steps[] = 'در متن همین صفحه این تیترها «' . $term . '» را دارند: «' . implode( '»، «', array_slice( $h2s, 0, 3 ) ) . '»؛ کلمه را از آن‌ها بردارید یا تیتر را دقیق‌تر کنید.';
		}
		$anchors = null === $row ? null : hodima_seo_advisor_crawl_anchors_between( $path, $w_path );
		if ( null === $anchors || ! $anchors ) {
			$steps[] = 'در متن همین صفحه یک جمله با «' . $term . '» بنویسید و همان را به صفحه اصلی لینک بدهید: ' . $url( $winner );
		} elseif ( ! $has_term( $anchors ) ) {
			$steps[] = 'از همین صفحه به صفحه اصلی لینک دارید ولی متن لینک «' . $anchors[0] . '» است؛ متن لینک را «' . $term . '» کنید یا یک لینک دیگر با همین متن به ' . $url( $winner ) . ' بدهید.';
		}
		$steps = $steps
			? [ ...$steps, 'ذخیره کنید و «انجام دادم» را بزنید.' ]
			: [ 'طبق آخرین خزش کار این صفحه انجام شده: «' . $term . '» در عنوان و تیترهای ۲ آن نیست و با همین متن به صفحه اصلی لینک دارد. «انجام دادم» را بزنید تا با آمار گوگل سنجیده شود.' ];
	} else {
		$steps = [ 'این صفحه صفحه اصلی «' . $term . '» است؛ متن و آدرسش را عوض نکنید.' ];
		$row   = $crawl( $path );
		if ( null !== $row && '' !== $row['title'] && ! hodima_seo_advisor_text_has( $row['title'], $term ) ) {
			$steps[] = '«{where}» ← کادر «سئو (SeoBox)» ← «عنوان سئو»: عنوان فعلی «' . $row['title'] . '» کلمه «' . $term . '» را ندارد؛ آن را به ابتدای عنوان بیاورید.';
		}
		foreach ( array_slice( $others, 0, 3 ) as $o ) {
			$anchors = null === $crawl( (string) $o['path'] ) ? null : hodima_seo_advisor_crawl_anchors_between( (string) $o['path'], $path );
			if ( null === $anchors || ! $has_term( $anchors ) ) {
				$steps[] = 'در صفحه «' . $name( $o ) . '» (' . $url( $o ) . ') یک جمله با «' . $term . '» بنویسید و آن را به همین صفحه لینک بدهید' . ( $anchors ? ' (لینک فعلی‌اش متن «' . $anchors[0] . '» دارد)' : '' ) . '.';
				$h2o = $h2_with( (string) $o['path'] );
				if ( $h2o ) {
					$steps[] = 'در همان صفحه «' . $name( $o ) . '» تیتر «' . $h2o[0] . '» این کلمه را دارد؛ آن را دقیق‌تر کنید تا با همین صفحه رقابت نکند.';
				}
			}
		}
		if ( 1 === count( $steps ) ) {
			$steps[] = 'طبق آخرین خزش همه صفحه‌های رقیب با «' . $term . '» به این صفحه لینک داده‌اند؛ کاری نمانده. «انجام دادم» را بزنید تا با آمار گوگل سنجیده شود.';
		}
	}

	if ( 'category' === $type ) { // کلمه عمومی: صفحه دسته از محصول مناسب‌تر است (همان دستور قبلی، اول فهرست)
		array_unshift( $steps, 'برای کلمه عمومی «' . $term . '» صفحه دسته از محصول مناسب‌تر است؛ اگر یکی از این صفحه‌ها دسته است، همان را صفحه اصلی کلمه کنید.' );
	}

	// «گزارش» باکس‌دار (SEO 3.7.9): کارت هر صفحه رقیب (برنده سبز «صفحه اصلی این کلمه»، «همین صفحه» پررنگ) + یک خط نتیجه
	$cards = [];
	foreach ( array_slice( $pages, 0, 4 ) as $i => $p ) {
		$p_path  = (string) ( $p['path'] ?? '' );
		$cards[] = [
			'badge'       => 0 === $i ? ( $p_path === $path ? 'صفحه اصلی این کلمه · همین صفحه' : 'صفحه اصلی این کلمه' ) : ( $p_path === $path ? 'همین صفحه' : 'صفحه رقیب' ),
			'me'          => $p_path === $path,
			'win'         => 0 === $i,
			'path'        => $p_path,
			'title'       => (string) ( $p['title'] ?? '' ),
			'impressions' => (int) ( $p['impressions'] ?? 0 ),
			'clicks'      => (int) ( $p['clicks'] ?? 0 ),
			'position'    => (float) ( $p['position'] ?? 0 ),
			'edit'        => function_exists( 'hodima_seo_advisor_edit_link' ) && (int) ( $p['object_id'] ?? 0 ) > 0 ? hodima_seo_advisor_edit_link( (string) ( $p['kind'] ?? '' ), (int) $p['object_id'] ) : '',
			// وضعیت لینک این صفحه به صفحه اصلی با متن همین کلمه، طبق آخرین خزش (SEO 3.8.0)؛ null = خزیده نشده
			'linked'      => 0 === $i ? null : ( null === $crawl( $p_path ) ? null : $has_term( hodima_seo_advisor_crawl_anchors_between( $p_path, $w_path ) ?? [] ) ),
		];
	}
	if ( ! $is_win ) { // همین صفحه کنار برنده دیده شود، حتی اگر رتبه سوم یا بعدتر است
		$cards = [ $cards[0], ...array_values( array_filter( $cards, static fn( array $c ): bool => $c['me'] ) ), ...array_values( array_filter( array_slice( $cards, 1 ), static fn( array $c ): bool => ! $c['me'] ) ) ];
		$cards = array_slice( $cards, 0, 4 );
	}

	return [
		'report' => [
			'intro' => sprintf( '%1$s صفحه سایت شما برای «%2$s» در گوگل نشان داده می‌شوند و کلیک بینشان تقسیم شده', number_format_i18n( count( $pages ) ), $term ),
			'pages' => $cards,
			'note'  => [ 'tone' => 'info', 'title' => 'نتیجه', 'text' => $is_win ? sprintf( 'این صفحه برای «%s» صفحه اصلی است (بیشترین کلیک)؛ گام‌های زیر برای همین حالت است.', $term ) : sprintf( 'این صفحه برای «%s» صفحه اصلی نیست؛ گام‌های زیر برای همین حالت است.', $term ) ],
		],
	] + hodima_seo_advisor_overview_guide(
		$seen,
		'rec',
		$steps,
		'cannibal',
		sprintf( 'بعد از تغییر «انجام دادم» را بزنید؛ حدود %s روز بعد با آمار گوگل سنجیده می‌شود که یک صفحه سهم اصلی را گرفت یا نه.', number_format_i18n( HODIMA_SEO_ADVISOR_MARK_COMPETITION_DAYS ) )
	);
}

/**
 * بدنه راهنما: «چه دیدیم» ← (جزئیات: فهرست تصویر/لینک/پیش‌نویس) ← «دقیقا چه کنم» ← «از کجا بفهمم» ← «چرا مهم است؟» بسته.
 * قبلا یک پاراگراف بود: جزئیات + دستور بی‌نقطه پشت هم (خواست کاربر: «راهنمایی گنگ است»).
 *
 * @param array{seen: string, need: string, steps: list<string>, verify: string, why: string, report?: array<string, mixed>} $guide report = باکس‌های «گزارش» (SEO 3.7.9)
 * @param callable|null $between چاپ جزئیات بین «گزارش» و گام‌ها
 */
function hodima_seo_advisor_render_guide( array $guide, ?callable $between = null ): void {

	$guide = [ 'seen' => hodima_seo_advisor_fa_digits( $guide['seen'] ), 'steps' => array_map( 'hodima_seo_advisor_fa_digits', $guide['steps'] ), 'verify' => hodima_seo_advisor_fa_digits( $guide['verify'] ), 'why' => hodima_seo_advisor_fa_digits( $guide['why'] ) ] + $guide;

	// «چه دیدیم» ← «گزارش» (SEO 3.7.9، خواست کاربر)؛ پیشنهادی که باکس دارد (report) گزارش باکس‌دار می‌گیرد، بقیه همان یک خط
	if ( ! empty( $guide['report'] ) && is_array( $guide['report'] ) ) {
		hodima_seo_advisor_render_report( $guide['report'] );
	} elseif ( '' !== $guide['seen'] ) {
		// آدرس وسط جمله فارسی لینک چپ‌به‌راست جدا (bdi) می‌شود؛ با esc_html اسلش آخر آدرس به اولش می‌پرید (عکس کاربر، SEO 3.7.1)
		printf( '<p class="hodima-advisor__seen">%s</p>', hodima_seo_advisor_text_html( $guide['seen'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML امن‌شده در hodima_seo_advisor_text_html؛ پیشوند «گزارش:» از ۳.۸.۹ در ردیف برچسب‌هاست
	}

	if ( null !== $between ) {
		$between();
	}

	if ( $guide['steps'] ) {
		echo '<p class="hodima-advisor__guide-label">دقیقا چه کنم</p><ol class="hodima-advisor__steps-list">';
		foreach ( $guide['steps'] as $step ) {
			printf( '<li>%s</li>', hodima_seo_advisor_text_html( $step ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML امن‌شده در hodima_seo_advisor_text_html
		}
		echo '</ol>';
	}

	if ( '' !== $guide['verify'] ) {
		printf( '<p class="hodima-advisor__verify"><span class="dashicons dashicons-yes" aria-hidden="true"></span><span><strong>%1$s</strong> %2$s</span></p>', esc_html( 'از کجا بفهمم درست شد:' ), esc_html( $guide['verify'] ) );
	}

	if ( '' !== $guide['why'] ) {
		printf( '<details class="hodima-advisor__why"><summary>%1$s</summary><p>%2$s</p></details>', esc_html( 'چرا مهم است؟' ), esc_html( $guide['why'] ) );
	}
}

/**
 * پای کارت صفحه رقیب در «گزارش» (SEO 3.8.0): وضعیت لینک به صفحه اصلی با همین کلمه (طبق آخرین خزش) و دکمه «ویرایش».
 *
 * @param array<string, mixed> $card
 */
function hodima_seo_advisor_report_card_foot( array $card ): string {

	$linked = $card['linked'] ?? null;
	$parts  = [];

	if ( true === $linked ) {
		$parts[] = '<span class="hodima-advisor__rchip hodima-advisor__rchip--ok"><span aria-hidden="true">✓</span> به صفحه اصلی با همین کلمه لینک دارد</span>';
	} elseif ( false === $linked ) {
		$parts[] = '<span class="hodima-advisor__rchip"><span aria-hidden="true">✕</span> به صفحه اصلی با همین کلمه لینک ندارد</span>';
	}

	if ( '' !== (string) ( $card['edit'] ?? '' ) ) {
		$parts[] = sprintf( '<a class="hodima-advisor__rpage-edit" href="%1$s"><span class="dashicons dashicons-edit" aria-hidden="true"></span>ویرایش<span class="screen-reader-text"> %2$s</span></a>', esc_url( (string) $card['edit'] ), esc_html( (string) ( $card['title'] ?? '' ) ) );
	}

	return $parts ? '<span class="hodima-advisor__rpage-foot">' . implode( '', $parts ) . '</span>' : '';
}

/**
 * «گزارش» باکس‌دار یک پیشنهاد (SEO 3.7.9؛ طرح تأییدشده کاربر «طرح گزارش دستار سئو»): باکس هر واقعیت (برچسب، عدد درشت، توضیح؛
 * نارنجی = جای مشکل، با نوار پیشرفت یا نشان ✓/✕)، کارت صفحه‌های رقیب، یک نوار علت/نتیجه و یک خط پانویس. داده از report خود پیشنهاد.
 *
 * @param array<string, mixed> $report
 */
function hodima_seo_advisor_render_report( array $report ): void {

	$fa    = static fn( string $text ): string => hodima_seo_advisor_fa_digits( $text );
	$boxes = array_values( array_filter( (array) ( $report['boxes'] ?? [] ), 'is_array' ) );
	$pages = array_values( array_filter( (array) ( $report['pages'] ?? [] ), 'is_array' ) );
	$note  = is_array( $report['note'] ?? null ) ? $report['note'] : null;

	echo '<div class="hodima-advisor__report">';
	// عنوان «گزارش» از SEO 3.8.9 در ردیف برچسب‌های همان کار است (طرح تأییدشده کاربر)؛ اینجا فقط جمله مقدمه
	if ( '' !== (string) ( $report['intro'] ?? '' ) ) {
		printf( '<p class="hodima-advisor__report-intro">%s</p>', esc_html( $fa( (string) $report['intro'] ) ) );
	}

	if ( $boxes ) {
		echo '<div class="hodima-advisor__report-boxes">';
		foreach ( $boxes as $b ) {
			$warn = 'warn' === ( $b['tone'] ?? '' );
			printf( '<div class="hodima-advisor__rbox%s">', $warn ? ' hodima-advisor__rbox--warn' : '' );
			printf( '<span class="hodima-advisor__rbox-label">%s</span>', esc_html( $fa( (string) ( $b['label'] ?? '' ) ) ) );
			if ( '' !== (string) ( $b['value'] ?? '' ) ) {
				printf( '<strong class="hodima-advisor__rbox-value">%s</strong>', esc_html( $fa( (string) $b['value'] ) ) );
			}
			if ( isset( $b['bar'] ) ) {
				$pct = (int) round( max( 0.0, min( 1.0, (float) $b['bar'] ) ) * 100 );
				printf( '<span class="hodima-advisor__rbox-bar" role="img" aria-label="%1$s"><span style="inline-size: %2$s%%"></span></span>', esc_attr( $fa( sprintf( '%s٪ از مقدار مورد انتظار', number_format_i18n( $pct ) ) ) ), esc_attr( (string) $pct ) );
			}
			if ( ! empty( $b['chips'] ) && is_array( $b['chips'] ) ) {
				echo '<span class="hodima-advisor__rbox-chips">';
				foreach ( $b['chips'] as $chip ) {
					$ok = ! empty( $chip[0] );
					printf( '<span class="hodima-advisor__rchip%1$s"><span aria-hidden="true">%2$s</span> %3$s<span class="screen-reader-text">%4$s</span></span>', $ok ? ' hodima-advisor__rchip--ok' : '', $ok ? '✓' : '✕', esc_html( (string) ( $chip[1] ?? '' ) ), $ok ? ': هست' : ': نیست' );
				}
				echo '</span>';
			}
			if ( '' !== (string) ( $b['sub'] ?? '' ) ) {
				printf( '<span class="hodima-advisor__rbox-sub">%s</span>', esc_html( $fa( (string) $b['sub'] ) ) );
			}
			echo '</div>';
		}
		echo '</div>';
	}

	if ( $pages ) {
		echo '<div class="hodima-advisor__report-pages">';
		foreach ( $pages as $p ) {
			$path  = (string) ( $p['path'] ?? '' );
			$title = (string) ( $p['title'] ?? '' );
			if ( '' === $title && '' !== $path ) {
				$title = (string) ( hodima_seo_advisor_page( $path )['title'] ?? '' );
			}
			$stat = static fn( int|float $value, int $decimals = 0 ): string => $value < 0 ? '—' : $fa( number_format_i18n( $value, $decimals ) );
			printf(
				'<div class="hodima-advisor__rpage%1$s"><span class="hodima-advisor__rpage-badge">%2$s</span><strong class="hodima-advisor__rpage-title">%3$s</strong>%4$s<span class="hodima-advisor__rpage-stats"><span><b>%5$s</b> نمایش</span><span><b>%6$s</b> کلیک</span><span><b>%7$s</b> رتبه</span></span>%8$s</div>',
				! empty( $p['win'] ) ? ' hodima-advisor__rpage--win' : ( ! empty( $p['me'] ) || 'همین صفحه' === ( $p['badge'] ?? '' ) ? ' hodima-advisor__rpage--me' : '' ),
				esc_html( (string) ( $p['badge'] ?? '' ) ),
				esc_html( '' !== $title ? $title : rawurldecode( $path ) ),
				'' !== $path ? hodima_seo_advisor_url_copy( $path ) : '', // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML امن‌شده در hodima_seo_advisor_url_copy
				esc_html( $stat( (int) ( $p['impressions'] ?? -1 ) ) ),
				esc_html( $stat( (int) ( $p['clicks'] ?? -1 ) ) ),
				esc_html( $stat( (float) ( $p['position'] ?? -1 ), 1 ) ),
				hodima_seo_advisor_report_card_foot( $p ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML امن‌شده در همان تابع
			);
		}
		echo '</div>';
	}

	if ( null !== $note && '' !== (string) ( $note['text'] ?? '' ) ) {
		$warn = 'warn' === ( $note['tone'] ?? '' );
		printf(
			'<p class="hodima-advisor__report-note%1$s"><span class="hodima-advisor__report-icon" aria-hidden="true">%2$s</span><span><strong>%3$s:</strong> %4$s</span></p>',
			$warn ? ' hodima-advisor__report-note--warn' : '',
			$warn ? '!' : 'i',
			esc_html( (string) ( $note['title'] ?? '' ) ),
			hodima_seo_advisor_text_html( $fa( (string) $note['text'] ) ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML امن‌شده در hodima_seo_advisor_text_html
		);
	}

	if ( '' !== (string) ( $report['foot'] ?? '' ) ) {
		printf( '<p class="hodima-advisor__report-foot">%s</p>', esc_html( $fa( (string) $report['foot'] ) ) );
	}

	echo '</div>';
}

/**
 * شمار صفحه‌های دارای ایراد هر نوع (برای منوی فیلتر).
 *
 * @param list<array{items: list<array<string, mixed>>}> $pages
 * @return array<string, int>
 */
function hodima_seo_advisor_overview_counts( array $pages ): array {
	$counts = array_fill_keys( array_keys( HODIMA_SEO_ADVISOR_OVERVIEW_SOURCES ), 0 );
	foreach ( $pages as $page ) {
		foreach ( array_unique( array_column( $page['items'], 'src' ) ) as $src ) {
			++$counts[ $src ];
		}
	}
	return $counts;
}

/**
 * دکمه «ویرایش صفحه» (اگر نوشته/محصول شناخته شد و اجازه ویرایش هست)، وگرنه «دیدن در سایت».
 *
 * @return array{0: string, 1: string, 2: string} آدرس، برچسب، Dashicon
 */
function hodima_seo_advisor_overview_link( string $kind, int $id, string $path ): array {

	if ( $id > 0 && post_type_exists( $kind ) && current_user_can( 'edit_post', $id ) ) {
		$url = get_edit_post_link( $id, 'raw' );
		if ( is_string( $url ) && '' !== $url ) {
			return [ $url, 'ویرایش صفحه', 'edit' ];
		}
	}

	// دسته و برچسب (SEO 3.7.0): تا ۳.۶.۹ فقط نوشته/محصول دکمه ویرایش داشت و صفحه دسته «دیدن در سایت» می‌گرفت، با اینکه گام‌ها می‌گفتند «ویرایش دسته را بزنید»
	if ( $id > 0 && taxonomy_exists( $kind ) && current_user_can( 'edit_term', $id ) ) {
		$url = get_edit_term_link( $id, $kind );
		if ( is_string( $url ) && '' !== $url ) {
			return [ $url, 'ویرایش صفحه', 'edit' ];
		}
	}

	return [ hodima_seo_advisor_url_full( $path ), 'دیدن در سایت', 'external' ];
}

/** شناسه ردیف صفحه (پیوند «دیدن و رفع» کادر «۵ کار مهم»). */
function hodima_seo_advisor_row_anchor( string $path ): string {
	return 'hodima-ov-' . substr( md5( $path ), 0, 10 );
}

/** نشان کوچک اولویت (بالا، متوسط، کم). */
function hodima_seo_advisor_priority_chip( string $band ): string {

	$label = [ 'high' => 'اولویت بالا', 'mid' => 'اولویت متوسط', 'low' => 'اولویت کم' ][ $band ] ?? '';

	return '' === $label ? '' : sprintf( '<span class="hodima-advisor__prio hodima-advisor__prio--%1$s">%2$s</span>', esc_attr( $band ), esc_html( $label ) );
}

/** گروه‌های «نوع ایراد فنی» منوی «کارها» ← برچسب (کلیدها همان HODIMA_SEO_ADVISOR_CRAWL_GROUPS؛ SEO 3.5.8). */
const HODIMA_SEO_ADVISOR_OVERVIEW_CRAWL_TYPES = [
	'meta'     => 'متا و خلاصه',
	'title'    => 'عنوان یا H1',
	'dup'      => 'تکراری و مشابه',
	'slow'     => 'کند',
	'redirect' => 'ریدایرکت یا noindex',
	'linkfix'  => 'لینک اشتباه',
	'content'  => 'تصویر و محتوا',
	'tech'     => 'canonical، نقشه سایت و اسکیما', // «اسکیما» از SEO 3.8.6 (ایراد داده ساختاریافته خراب)
	'links'    => 'لینک ورودی کم',
	'orphan'   => 'بدون لینک ورودی',
];

/**
 * ایراد فنی سطح «اطلاع» (در نمره ضریب صفر دارد). سطح خود ایراد برای همان صفحه (`level` در ایراد) بر سطح کاتالوگ مقدم است.
 *
 * @param array<string, mixed> $item آیتم فهرست «کارها» (hodima_seo_advisor_overview_build)
 */
function hodima_seo_advisor_is_info_item( array $item ): bool {
	return 'crawl' === ( $item['src'] ?? '' ) && 'info' === ( $item['level'] ?? '' );
}

/**
 * شمار پیشنهادهای «اطلاع» باز و صفحه‌هایشان برای خط زیر نمره (SEO 3.8.8، طرح تأییدشده کاربر). از همان فهرست «کارها» (بدون
 * موردهای علامت‌خورده) شمرده می‌شود تا با فیلتر «دیدن در فهرست» یکی باشد.
 *
 * @param list<array{path: string, items: list<array<string, mixed>>}> $pages
 * @return array{items: int, pages: int}
 */
function hodima_seo_advisor_info_counts( array $pages ): array {

	$items = 0;
	$with  = 0;
	foreach ( $pages as $page ) {
		$n      = count( array_filter( $page['items'], 'hodima_seo_advisor_is_info_item' ) );
		$items += $n;
		$with  += $n > 0 ? 1 : 0;
	}

	return [ 'items' => $items, 'pages' => $with ];
}

/** صفحه «کارها». */
function hodima_seo_advisor_render_overview(): void {

	$n      = static fn( int|float $value ): string => number_format_i18n( $value );
	$pages  = hodima_seo_advisor_overview_build();
	$counts = hodima_seo_advisor_overview_counts( $pages );
	$src    = isset( $_GET['src'] ) ? sanitize_key( (string) $_GET['src'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط فیلتر نمایش
	$src    = isset( HODIMA_SEO_ADVISOR_OVERVIEW_SOURCES[ $src ] ) ? $src : '';
	$code   = isset( $_GET['code'] ) ? sanitize_key( (string) $_GET['code'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط فیلتر نمایش (پیوند «کار کلی»؛ SEO 3.4.0)
	$code   = isset( hodima_seo_advisor_issue_catalog()[ $code ] ) ? $code : '';
	$q      = isset( $_GET['q'] ) ? trim( sanitize_text_field( wp_unslash( (string) $_GET['q'] ) ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$paged  = max( 1, isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$cf     = isset( $_GET['cf'] ) ? sanitize_key( (string) $_GET['cf'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط فیلتر نمایش: بخش نمره یا گروه نوع ایراد فنی (SEO 3.5.8؛ پیوند قدیمی تب «سلامت سایت»)
	$cf_codes = hodima_seo_advisor_score_filter_codes( $cf );
	$cf     = $cf_codes ? $cf : '';
	$lvl    = isset( $_GET['lvl'] ) && 'info' === sanitize_key( (string) $_GET['lvl'] ) ? 'info' : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط فیلتر نمایش: پیشنهادهای «اطلاع» بیرون از نمره (SEO 3.8.8)
	$img    = isset( $_GET['img'] ) ? sanitize_key( (string) $_GET['img'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- فقط فیلتر نمایش: صفحه‌های یک تصویر مشترک (SEO 3.4.9)
	$only   = null;
	foreach ( '' === $img ? [] : hodima_seo_advisor_image_groups() as $group ) {
		if ( hodima_seo_advisor_img_key( $group['url'] ) === $img ) {
			$only = array_fill_keys( $group['pages'], true );
			$code = 'img_alt';
		}
	}

	$rows = [];
	foreach ( $pages as $page ) {
		if ( '' !== $q && ! str_contains( mb_strtolower( rawurldecode( $page['path'] ) . ' ' . $page['title'] ), mb_strtolower( $q ) ) ) {
			continue;
		}
		if ( null !== $only && ! isset( $only[ $page['path'] ] ) ) {
			continue;
		}
		if ( '' !== $src ) {
			$page['items'] = array_values( array_filter( $page['items'], static fn( array $item ): bool => $src === $item['src'] ) );
		}
		if ( '' !== $code ) {
			$page['items'] = array_values( array_filter( $page['items'], static fn( array $item ): bool => 'crawl' === $item['src'] && $code === $item['mark'] ) );
		}
		if ( '' !== $cf ) {
			$page['items'] = array_values( array_filter( $page['items'], static fn( array $item ): bool => 'crawl' === $item['src'] && in_array( $item['mark'], $cf_codes, true ) ) );
		}
		if ( '' !== $lvl ) {
			$page['items'] = array_values( array_filter( $page['items'], 'hodima_seo_advisor_is_info_item' ) );
		}
		if ( $page['items'] ) {
			$rows[] = $page;
		}
	}

	// نوار «سلامت سایت» (SEO 3.5.8؛ قبلا تب جدا): نمره از همه ایرادهای خزش، نه فقط آنچه علامت نخورده
	$crawl = hodima_seo_advisor_crawl_status();
	if ( $crawl['imported'] > 0 && $crawl['pages'] > 0 ) {
		hodima_seo_advisor_render_score( hodima_seo_advisor_crawl_issues(), $crawl['pages'], $cf, hodima_seo_advisor_info_counts( $pages ) );
	}

	if ( '' === $src && '' === $q && '' === $code && '' === $cf && '' === $lvl && 1 === $paged ) {
		hodima_seo_advisor_render_shared_images( $n ); // تصویر مشترک بین چند صفحه = یک کار (SEO 3.4.9)
	}

	hodima_seo_ui_card_open( 'کارها', sprintf( '%s ایراد باز روی %s صفحه، به ترتیب اولویت (بیشترین اثر با کمترین زحمت). هر ایراد را رفع کنید و «انجام دادم» را بزنید؛ اگر لازم نیست، «لازم نیست».', $n( array_sum( array_map( static fn( array $p ): int => count( $p['items'] ), $pages ) ) ), $n( count( $pages ) ) ) );

	$items = [ '' => [ 'همه ایرادها', count( $pages ), hodima_seo_advisor_page_url( [ 'q' => $q ] ) ] ];
	foreach ( HODIMA_SEO_ADVISOR_OVERVIEW_SOURCES as $key => $label ) {
		if ( $counts[ $key ] > 0 || $key === $src ) {
			$items[ $key ] = [ $label, $counts[ $key ], hodima_seo_advisor_page_url( [ 'src' => $key, 'q' => $q ] ) ];
		}
	}

	// ردیف دوم چیپ‌ها «نوع ایراد فنی» (SEO 3.6.0؛ جایگزین فیلتر جدول تب قدیمی «سلامت سایت»؛ تا ۳.۵.۹ منوی کشویی بود). گروه چیپ‌ها
	// با شمار صفحه‌های دارای ایراد؛ زدن چیپ فعال فیلتر را برمی‌دارد. فقط وقتی «ایراد فنی» انتخاب است یا یک نوع فعال است.
	$url_type = static fn( string $key ): string => hodima_seo_advisor_page_url( [ 'src' => $src, 'cf' => $key === $cf ? '' : $key, 'q' => $q ] );
	$types    = [];
	foreach ( HODIMA_SEO_ADVISOR_OVERVIEW_CRAWL_TYPES as $key => $label ) {
		$members = HODIMA_SEO_ADVISOR_CRAWL_GROUPS[ $key ];
		$n_type  = count( array_filter( $pages, static fn( array $p ): bool => (bool) array_filter( $p['items'], static fn( array $i ): bool => 'crawl' === $i['src'] && in_array( $i['mark'], $members, true ) ) ) );
		if ( $n_type > 0 || $key === $cf ) {
			$types[ $key ] = [ $label, $n_type, $url_type( $key ) ];
		}
	}
	if ( str_starts_with( $cf, 'cat-' ) ) { // بخش نمره (از نوار سلامت)
		$n_cat        = count( array_filter( $pages, static fn( array $p ): bool => (bool) array_filter( $p['items'], static fn( array $i ): bool => 'crawl' === $i['src'] && in_array( $i['mark'], $cf_codes, true ) ) ) );
		$types[ $cf ] = [ HODIMA_SEO_ADVISOR_SCORE_CATEGORIES[ $cf ]['label'], $n_cat, $url_type( $cf ) ];
	}
	?>
	<form method="get" class="hodima-advisor__toolbar">
		<input type="hidden" name="page" value="<?php echo esc_attr( HODIMA_SEO_ADVISOR_PAGE ); ?>">
		<?php if ( '' !== $src ) : ?>
			<input type="hidden" name="src" value="<?php echo esc_attr( $src ); ?>">
		<?php endif; ?>
		<?php if ( '' !== $code ) : ?>
			<input type="hidden" name="code" value="<?php echo esc_attr( $code ); ?>">
		<?php endif; ?>
		<?php if ( '' !== $cf ) : ?>
			<input type="hidden" name="cf" value="<?php echo esc_attr( $cf ); ?>">
		<?php endif; ?>
		<?php hodima_seo_advisor_filter_chips( 'نوع ایراد', $items, $src ); ?>
		<?php if ( $types && ( 'crawl' === $src || '' !== $cf ) ) : ?>
			<div class="hodima-advisor__chips-sub">
				<?php hodima_seo_advisor_filter_chips( 'نوع ایراد فنی', $types, $cf, [ 'small' => true ] ); // بدون برچسب دیداری؛ فقط aria-label برای صفحه‌خوان ?>
			</div>
		<?php endif; ?>
		<div class="hodima-advisor__search">
			<label class="screen-reader-text" for="hodima-advisor-ov-q">جستجو در عنوان یا آدرس صفحه</label>
			<input type="search" id="hodima-advisor-ov-q" name="q" class="hds-input" value="<?php echo esc_attr( $q ); ?>" placeholder="جستجو در عنوان یا آدرس…">
			<button type="submit" class="hodima-advisor__go" aria-label="اعمال جستجو"><span class="dashicons dashicons-search" aria-hidden="true"></span></button>
		</div>
	</form>
	<?php

	if ( '' !== $code ) {
		printf(
			'<p class="hodima-advisor__back">%1$s <a href="%2$s">%3$s</a></p>',
			esc_html( sprintf( 'فقط نمایش: «%s»', hodima_seo_advisor_issue_catalog()[ $code ]['label'] ) ),
			esc_url( hodima_seo_advisor_page_url( array_filter( [ 'src' => $src, 'q' => $q ] ) ) ),
			esc_html( 'برداشتن فیلتر' )
		);
	}

	if ( '' !== $lvl ) {
		printf(
			'<p class="hodima-advisor__back">%1$s <a href="%2$s">%3$s</a></p>',
			esc_html( 'فقط نمایش: پیشنهادهای «اطلاع» (در نمره حساب نمی‌شوند)' ),
			esc_url( hodima_seo_advisor_page_url( array_filter( [ 'src' => $src, 'q' => $q ] ) ) ),
			esc_html( 'برداشتن فیلتر' )
		);
	}

	if ( '' !== $cf ) {
		printf(
			'<p class="hodima-advisor__back">%1$s <a href="%2$s">%3$s</a></p>',
			esc_html( sprintf( 'فیلتر فعال: «%s»', $types[ $cf ][0] ?? $cf ) ),
			esc_url( hodima_seo_advisor_page_url( array_filter( [ 'src' => $src, 'q' => $q ] ) ) ),
			esc_html( 'برداشتن فیلتر' )
		);
	}

	if ( ! $rows ) {
		hodima_seo_ui_empty( $pages ? 'اینجا چیزی نیست' : 'کار بازی نمانده', $pages ? 'با این فیلتر ایرادی نیست. فیلتر را عوض کنید.' : 'همه ایرادها رفع یا علامت زده شده‌اند. بعد از خزش شبانه یا «خزش و داده ← دریافت داده گوگل ← اجرا همین حالا» دوباره ببینید.' );
		hodima_seo_advisor_render_overview_foot( 0, $n );
		hodima_seo_ui_card_close();
		return;
	}

	$total = count( $rows );
	$last  = max( 1, (int) ceil( $total / HODIMA_SEO_ADVISOR_OVERVIEW_PER_PAGE ) );
	$paged = min( $paged, $last );
	$slice = array_slice( $rows, ( $paged - 1 ) * HODIMA_SEO_ADVISOR_OVERVIEW_PER_PAGE, HODIMA_SEO_ADVISOR_OVERVIEW_PER_PAGE );
	?>
	<div class="hodima-advisor__ov">
		<?php foreach ( $slice as $page ) : ?>
			<?php
			$row  = hodima_seo_advisor_page( $page['path'] );
			$link = hodima_seo_advisor_overview_link( (string) ( $row['kind'] ?? '' ), (int) ( $row['object_id'] ?? 0 ), $page['path'] );
			$name = '' !== $page['title'] ? $page['title'] : rawurldecode( $page['path'] );
			// صفحه‌ای که فقط خزنده دیده (بدون ردیف داده گوگل) هم «ویرایش صفحه» و نام واقعی بگیرد (جدول قدیمی «سلامت سایت» همین را داشت؛ SEO 3.5.8)
			if ( null === $row ) {
				$target = hodima_seo_advisor_crawl_row_target( $page['path'] );
				$link   = '' !== $target['edit'] ? [ $target['edit'], 'ویرایش صفحه', 'edit' ] : $link;
				$name   = '' !== $target['title'] ? $target['title'] : $name;
			}
			// دستور ایراد خزنده با نام «ویرایش» همین نوع صفحه (محصول، دسته…)؛ فقط برای ردیف‌های همین صفحه ساخته می‌شود (SEO 3.3.5)
			$where = hodima_seo_advisor_issue_where( $page['path'], (string) ( $row['kind'] ?? '' ) );
			// نام دکمه همان نامی که گام‌ها می‌گویند («ویرایش محصول»، «ویرایش دسته»)؛ قبلا همیشه «ویرایش صفحه» (SEO 3.7.0)
			if ( 'edit' === $link[2] && '' !== $where ) {
				$link[1] = $where;
			}
			?>
			<article class="hodima-advisor__ov-row" id="<?php echo esc_attr( hodima_seo_advisor_row_anchor( $page['path'] ) ); ?>">
				<?php // هر صفحه یک کارت کامل (SEO 3.8.9، طرح تأییدشده کاربر): سربرگ نوار بالای کارت (نام و آدرس در یک ردیف، نشان‌های نمایش/فروش/شمار کار، دکمه ویرایش)؛ تا ۳.۸.۸ سربرگ بیرون از قاب کارها بود ?>
				<div class="hodima-advisor__ov-head">
					<div class="hodima-advisor__ov-id">
						<strong class="hodima-advisor__ov-name"><?php echo esc_html( $name ); ?></strong>
						<?php echo hodima_seo_advisor_url_copy( $page['path'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML امن‌شده ?>
					</div>
					<span class="hodima-advisor__ov-meta">
						<?php if ( $page['impressions'] > 0 ) : ?>
							<span class="hodima-advisor__ov-fact"><?php echo esc_html( sprintf( '%s نمایش', $n( $page['impressions'] ) ) ); ?></span>
						<?php endif; ?>
						<?php if ( $page['sales'] > 0 ) : ?>
							<span class="hodima-advisor__ov-fact"><?php echo esc_html( 'فروش ' . hodima_seo_advisor_money_label( $page['sales'] ) ); ?></span>
						<?php endif; ?>
						<span class="hodima-advisor__ov-fact hodima-advisor__ov-fact--count"><?php echo esc_html( sprintf( '%s کار', $n( count( $page['items'] ) ) ) ); ?></span>
					</span>
					<?php echo hodima_seo_advisor_action( $link[0], $link[1], $link[2] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML امن‌شده ?>
				</div>
				<ul class="hodima-advisor__ov-list">
					<?php foreach ( $page['items'] as $item ) : ?>
						<li class="hodima-advisor__ov-item">
							<div class="hodima-advisor__ov-what">
								<?php
								// راهنمای پنج‌بخشی (SEO 3.6.9): ایراد خزنده از کاتالوگ با نام «ویرایش» همین صفحه؛ بقیه از overview_build
								$guide = 'crawl' === $item['src']
									? hodima_seo_advisor_issue_guide( (string) $item['mark'], (string) ( $item['detail'] ?? '' ), $where, (string) $item['level'] )
									: ( $item['guide'] ?? null );
								$guide ??= hodima_seo_advisor_overview_guide( (string) $item['text'], 'rec' );
								$guide['steps'] = array_map( static fn( string $step ): string => str_replace( '{where}', '' !== $where ? $where : 'ویرایش صفحه', $step ), $guide['steps'] );
								?>
								<?php
								// ردیف برچسب‌ها و دکمه‌ها (SEO 3.7.7، خواست کاربر): نام ایراد، کلمه، لازم است؟، اولویت و «انجام دادم / لازم نیست» در یک ردیف و یک
								// اندازه؛ تا ۳.۷.۶ دکمه‌ها ستون جدا با فضای خالی بلند کنار راهنما بودند. کلمه پیشنهاد گوگل نشان جدا دارد تا با نام صفحه یکی گرفته نشود
								$hidden = [ 'term' => (string) $item['term'] ];
								if ( '' !== (string) $item['mark'] ) {
									$hidden['items[]'] = (string) $item['mark'];
								}
								?>
								<div class="hodima-advisor__ov-labels">
									<?php // ترتیب (SEO 3.7.8، خواست کاربر): «گزارش» (۳.۸.۹: از ردیف جدا به همین ردیف) ← کلمه ← نام کار ← اولویت ← دو دکمه ?>
									<span class="hodima-advisor__ov-report"><span class="dashicons dashicons-chart-bar" aria-hidden="true"></span>گزارش</span>
									<?php if ( '' !== (string) ( $item['rule_term'] ?? '' ) ) : ?>
										<span class="hodima-advisor__ov-term">کلمه: «<?php echo esc_html( (string) $item['rule_term'] ); ?>»</span>
									<?php endif; ?>
									<span class="hds-pill <?php echo esc_attr( [ 'bad' => 'hds-pill--error', 'info' => 'hds-pill--info' ][ (string) $item['level'] ] ?? 'hds-pill--warn' ); ?>"><?php echo esc_html( $item['label'] ); ?></span>
									<?php echo hodima_seo_advisor_priority_chip( (string) $item['band'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML امن‌شده ?>
									<?php hodima_seo_advisor_mark_form( hodima_seo_advisor_mark_id(), (string) $item['source'], $page['path'], $name . ': ' . $item['label'], $hidden ); ?>
								</div>
								<?php
								// پیش‌نویس، فهرست تصویر و لینک (SEO 3.3.6) بین «چه دیدیم» و گام‌ها؛ پیشنهاد گوگل: «جزئیات و پیش‌نویس» که با باز کردن ساخته می‌شود (SEO 3.7.5؛ قبلا فقط در کادر ویرایش صفحه)
								hodima_seo_advisor_render_guide(
									$guide,
									in_array( $item['src'], [ 'opp', 'comp' ], true )
										? static fn() => hodima_seo_advisor_render_steps_lazy( $page['path'], (string) ( $item['rule'] ?? '' ), (string) ( $item['rule_term'] ?? '' ) )
										: static fn() => hodima_seo_advisor_render_issue_detail( $item, $page['path'] )
								);
								?>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>
			</article>
		<?php endforeach; ?>
	</div>
	<?php
	hodima_seo_advisor_render_overview_foot( $total, $n, $paged, $last, $src, $q, $cf );
	hodima_seo_ui_card_close();
}

/** پای فهرست: شمار، پیوند «کارهای انجام‌شده» و صفحه‌بندی. */
function hodima_seo_advisor_render_overview_foot( int $total, callable $n, int $paged = 1, int $last = 1, string $src = '', string $q = '', string $cf = '' ): void {
	?>
	<div class="hodima-advisor__foot">
		<span>
			<?php echo esc_html( $total > 0 ? sprintf( 'نمایش %s صفحه', $n( $total ) ) : '' ); ?>
			<?php echo hodima_seo_advisor_action( hodima_seo_advisor_page_url( [ 'tab' => 'tasks' ] ), 'کارهای انجام‌شده و نتیجه‌شان', 'yes-alt' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML امن‌شده ?>
		</span>
		<?php if ( $last > 1 ) : ?>
			<?php hodima_seo_ui_pager( $paged, $last, static fn( int $i ): string => hodima_seo_advisor_page_url( array_filter( [ 'src' => $src, 'cf' => $cf, 'q' => $q, 'paged' => $i > 1 ? $i : '' ] ) ) ); ?>
		<?php endif; ?>
	</div>
	<?php
}
