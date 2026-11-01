<?php
/**
 * دقت خواندن صفحه توسط خزنده دستار (SEO 3.4.5): هر مورد یک HTML کوچک با جواب درست معلوم است (قالب‌های واقعی: المنتور، ژنسیس،
 * ویجت‌های وردپرس، هدر نوشته، لوگوی H1، lazy-load با noscript). نمره = موردهای درست ÷ همه؛ هر خطا با نام مورد چاپ می‌شود.
 *   HARNESS=1 php tools/wp-harness/advisor-accuracy.php
 * ci-check.sh هم اجرا می‌کند؛ کد خروج ۱ اگر موردی غلط باشد. مورد تازه = یک ردیف در $cases (از باگ واقعی سایت).
 */

declare(strict_types=1);

$_SERVER['HTTP_HOST'] = $_SERVER['SERVER_NAME'] = 'hodima.test';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['HTTPS']       = 'on';
require rtrim( (string) ( getenv( 'HODIMA_WP' ) ?: '/tmp/hodima-harness/wp' ), '/' ) . '/wp-load.php';

if ( ! function_exists( 'hodima_seo_advisor_crawler_parse' ) ) {
	echo "✘ ماژول دستار سئو لود نشده است\n";
	exit( 1 );
}

$base = home_url( '/page/' );

/** موقعیت لینک مقصد داده‌شده در خروجی parse (یا null). */
$pos = static function ( array $parsed, string $target ): ?string {
	foreach ( $parsed['links'] as [ $url, $position ] ) {
		if ( str_contains( (string) $url, $target ) ) {
			return (string) $position;
		}
	}
	return null;
};

// [نام، HTML بدنه، تابع سنجش (parsed) ← آرایه‌ی «چیز: [واقعی، درست]»]
$cases = [
	[ 'المنتور: لینک داخل elementor-widget محتواست نه کناری', '<div class="elementor-widget-container"><a href="/a/">لینک</a></div>', static fn( array $p ): array => [ 'موقعیت' => [ $pos( $p, '/a/' ), 'content' ] ] ],
	[ 'المنتور: تصویر بدون alt داخل elementor-widget شمرده می‌شود', '<div class="elementor-widget-image"><img src="/x.jpg"></div>', static fn( array $p ): array => [ 'تصویر بدون alt' => [ $p['img_noalt'], 1 ] ] ],
	[ 'ژنسیس: content-sidebar-wrap محتواست؛ aside.sidebar کناری', '<div class="content-sidebar-wrap"><main><a href="/b/">b</a></main><aside class="sidebar"><a href="/c/">c</a></aside></div>', static fn( array $p ): array => [ 'محتوا' => [ $pos( $p, '/b/' ), 'content' ], 'کناری' => [ $pos( $p, '/c/' ), 'aside' ] ] ],
	[ 'ویجت وردپرس (widget-area, widget_text) کناری است', '<div id="secondary" class="widget-area"><section class="widget widget_text"><a href="/w/">w</a></section></div><main><a href="/m/">m</a></main>', static fn( array $p ): array => [ 'ویجت' => [ $pos( $p, '/w/' ), 'aside' ], 'محتوا' => [ $pos( $p, '/m/' ), 'content' ] ] ],
	[ 'منوی هدر سایت منو است', '<header class="site-header"><nav><a href="/n/">n</a></nav></header><main><p>x</p></main>', static fn( array $p ): array => [ 'منو' => [ $pos( $p, '/n/' ), 'navigation' ] ] ],
	[ 'فوتر فوتر است', '<main><p>x</p></main><footer><a href="/f/">f</a></footer>', static fn( array $p ): array => [ 'فوتر' => [ $pos( $p, '/f/' ), 'footer' ] ] ],
	[ 'سربرگ داخل article محتواست: لینک و تصویر آن شمرده می‌شود', '<main><article><header><a href="/h/">h</a><img src="/hero.jpg"></header><p>متن</p></article></main>', static fn( array $p ): array => [ 'لینک' => [ $pos( $p, '/h/' ), 'content' ], 'تصویر بدون alt' => [ $p['img_noalt'], 1 ] ] ],
	[ 'H1 لوگوی هدر سایت با H1 صفحه یکی حساب نمی‌شود', '<header class="site-header"><h1><a href="/">لوگو</a></h1></header><main><h1>عنوان صفحه</h1></main>', static fn( array $p ): array => [ 'شمار H1' => [ $p['h1_count'], 1 ], 'متن H1' => [ $p['h1'], 'عنوان صفحه' ] ] ],
	[ 'صفحه‌ای که فقط H1 لوگو دارد «بدون H1» نمی‌شود', '<header class="site-header"><h1>لوگو</h1></header><main><p>x</p></main>', static fn( array $p ): array => [ 'شمار H1' => [ $p['h1_count'], 1 ] ] ],
	[ 'دو H1 در محتوا دو تاست', '<main><h1>اول</h1><h1>دوم</h1></main>', static fn( array $p ): array => [ 'شمار H1' => [ $p['h1_count'], 2 ] ] ],
	[ 'lazy-load با noscript: یک تصویر یک بار شمرده می‌شود', '<main><img data-src="/l.jpg" src="data:image/gif;base64,R0lGOD" alt=""><noscript><img src="/l.jpg" alt=""></noscript></main>', static fn( array $p ): array => [ 'تصویر بدون alt' => [ $p['img_noalt'], 1 ] ] ],
	[ 'تصویر با alt درست ایراد نیست (کادر کارت با لینک دارای متن: alt خالی عمدی)', '<main><img src="/a.jpg" alt="گل سر"><a href="/p/">محصول <img src="/t.jpg" alt=""></a></main>', static fn( array $p ): array => [ 'تصویر بدون alt' => [ $p['img_noalt'], 0 ] ] ],
	[ 'تصویر تزئینی با aria-hidden ایراد نیست', '<main><img src="/d.jpg" alt="" aria-hidden="true"></main>', static fn( array $p ): array => [ 'تصویر بدون alt' => [ $p['img_noalt'], 0 ] ] ],
];

$total  = 0;
$right  = 0;
$failed = [];

foreach ( $cases as [ $name, $body, $check ] ) {
	$parsed = hodima_seo_advisor_crawler_parse( '<!doctype html><html><head><title>t</title></head><body>' . $body . '</body></html>', $base );
	$bad    = [];
	foreach ( $check( $parsed ) as $what => [ $actual, $expected ] ) {
		if ( $actual !== $expected ) {
			$bad[] = sprintf( '%s: واقعی %s، درست %s', $what, var_export( $actual, true ), var_export( $expected, true ) );
		}
	}
	++$total;
	if ( ! $bad ) {
		++$right;
		echo "  ✔ {$name}\n";
		continue;
	}
	$failed[] = $name;
	echo "  ✘ {$name}\n      " . implode( "\n      ", $bad ) . "\n";
}

printf( "\nدقت خواندن صفحه: %d از %d مورد درست (%d٪)\n", $right, $total, (int) round( $right / $total * 100 ) );
exit( $failed ? 1 : 0 );
