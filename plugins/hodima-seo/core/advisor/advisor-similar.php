<?php
/**
 * ماژول «دستار سئو» — عنوان و H1 مشابه بین صفحه‌ها (کانیبال بالقوه از خزنده؛ SEO 3.5.7)
 * Path: core/advisor/advisor-similar.php
 *
 * «رقابت صفحه‌ها» (advisor-competition.php) فقط وقتی رقابت را می‌بیند که گوگل برای هر دو صفحه نمایش داده باشد، و
 * `dup_title`/`dup_h1` فقط عنوان یا H1 «دقیقا یکسان» را می‌گیرند. این فایل صفحه‌هایی را پیدا می‌کند که عنوان (یا H1) آن‌ها
 * با املا یا ترتیب کلمه فرق دارد ولی یک موضوع را می‌گویند («کلیپ مو» و «کلیپس مو»)؛ پیش از اینکه گوگل گیج شود.
 *
 * قاعده: هر دو متن (بعد از یکسان‌سازی، برداشتن نام سایت و کلمه‌های تجاری عمومی مثل «خرید»، «عمده»، «قیمت») دست‌کم ۲ کلمه
 * دارند و کلمه‌هایشان یک‌به‌یک یکی‌اند (ترتیب مهم نیست)؛ کلمه‌ای که فقط یک حرف فرق دارد و ۴ حرف یا بیشتر است یکی حساب
 * می‌شود (املا)، عدد باید دقیقا یکی باشد (مدل ۱۲ با ۱۳ فرق دارد). کلمه اضافه یا کم یعنی دو موضوع جدا («کلیپ مو فلزی» و
 * «کلیپ مو پلاستیکی» مشابه نیستند). فقط پیشنهاد؛ چیزی خودکار عوض نمی‌شود.
 *
 * سرعت: صفحه‌ها اول با «کلید» (حرف اول هر کلمه، مرتب؛ دسته بزرگ با دو حرف اول) دسته می‌شوند و فقط درون هر دسته مقایسه کامل
 * انجام می‌شود، نه هر جفت. محدودیت‌های شناخته‌شده: کلمه‌ای که حرف اولش با املا عوض شود («عینک» و «اینک») هم‌دسته نمی‌شود؛ دسته بزرگ‌تر
 * از ۴۰ صفحه که با دو حرف اول هم تقسیم نشود (کلمه‌های بسیار عمومی) رد می‌شود.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * کلمه‌های ربط دستوری فارسی (SEO 3.9.3: از «عنوان مشابه» به این‌جا آمد تا «کلمه در تیتر نیست» هم همین فهرست را بخواند). باگ قبلی: جستجوی
 * «کش مو برای عروس» در عنوان «خرید عمده کش مو عروس» «در عنوان نیست» حساب می‌شد، چون «برای» در عنوان نیامده. کلمه تجاری (خرید، عمده…)
 * این‌جا نیست: آن‌ها معنی جستجو را می‌سازند و در فهرست شباهت می‌مانند.
 */
const HODIMA_SEO_ADVISOR_CONNECTORS = [ 'و', 'با', 'از', 'به', 'برای', 'در', 'که', 'این', 'یک', 'را', 'یا', 'تا' ];

/** کلمه‌های تجاری/ربطی که شباهت را نمی‌سازند. فیلتر `hodima_seo_advisor_similar_stopwords` برای افزودن کلمه سایت. */
const HODIMA_SEO_ADVISOR_SIMILAR_STOP = [
	...HODIMA_SEO_ADVISOR_CONNECTORS,
	'خرید', 'عمده', 'قیمت', 'فروش', 'مشخصات', 'ارزان', 'اینترنتی', 'آنلاین', 'بهترین', 'انواع', 'جدید', 'مدل', 'مدلهای', 'سفارش',
];

/** حداکثر نمونه هم‌دسته که برای هر دسته مقایسه می‌شود (دسته غول‌آسا = کلمه عمومی؛ مقایسه‌اش پرهزینه و کم‌ارزش است). */
const HODIMA_SEO_ADVISOR_SIMILAR_MAX_BUCKET = 40;

/** حداکثر صفحه مشابه نشان‌داده‌شده برای هر صفحه. */
const HODIMA_SEO_ADVISOR_SIMILAR_MAX_PARTNERS = 5;

/**
 * کلمه‌های معنی‌دار یک عنوان/H1.
 *
 * @param list<string> $drop کلمه‌های اضافه‌ای که برداشته شوند (نام سایت)
 * @return list<string>
 */
function hodima_seo_advisor_similar_tokens( string $text, array $drop = [] ): array {

	$text = hodima_seo_advisor_normalize( str_replace( "\u{200C}", '', $text ) ); // «بچه‌گانه» و «بچگانه» نزدیک شوند؛ نیم‌فاصله حذف می‌شود، نه فاصله
	$text = (string) preg_replace( '/[^\p{L}\p{N}]+/u', ' ', $text );
	$stop = array_map( 'hodima_seo_advisor_normalize', array_merge( (array) apply_filters( 'hodima_seo_advisor_similar_stopwords', HODIMA_SEO_ADVISOR_SIMILAR_STOP ), $drop ) );

	$out = [];
	foreach ( explode( ' ', $text ) as $token ) {
		// «ی» و «ای» آخر کلمه (اضافه، نکره: «موی»، «گیره‌ی»، «پارچه‌ای») کلمه را عوض نمی‌کند
		if ( mb_strlen( $token ) >= 5 && str_ends_with( $token, 'ای' ) ) {
			$token = mb_substr( $token, 0, -2 );
		} elseif ( mb_strlen( $token ) >= 3 && str_ends_with( $token, 'ی' ) && 1 !== preg_match( '/\d/', $token ) ) {
			$token = mb_substr( $token, 0, -1 );
		}
		if ( ( mb_strlen( $token ) >= 2 || 1 === preg_match( '/^\d$/', $token ) ) && 'ای' !== $token && ! in_array( $token, $stop, true ) && ! in_array( $token, $out, true ) ) {
			$out[] = $token;
		}
	}

	return $out;
}

/** دو کلمه دقیقا یکی‌اند یا (هر دو ≥ ۴ حرف، بدون رقم) فقط یک حرف فرق دارند. */
function hodima_seo_advisor_similar_token_match( string $a, string $b ): bool {

	if ( $a === $b ) {
		return true;
	}

	if ( mb_strlen( $a ) < 4 || mb_strlen( $b ) < 4 || 1 === preg_match( '/\d/', $a . $b ) ) {
		return false;
	}

	$x  = mb_str_split( $a );
	$y  = mb_str_split( $b );
	$nx = count( $x );
	$ny = count( $y );

	if ( abs( $nx - $ny ) > 1 ) {
		return false;
	}

	[ $i, $j, $edits ] = [ 0, 0, 0 ];

	while ( $i < $nx && $j < $ny ) {
		if ( $x[ $i ] === $y[ $j ] ) {
			++$i;
			++$j;
			continue;
		}
		if ( ++$edits > 1 ) {
			return false;
		}
		if ( $nx > $ny ) {
			++$i;
		} elseif ( $nx < $ny ) {
			++$j;
		} else {
			++$i;
			++$j;
		}
	}

	return $edits + ( $nx - $i ) + ( $ny - $j ) <= 1;
}

/**
 * آیا دو فهرست کلمه یک موضوع‌اند: هر دو دست‌کم ۲ کلمه، شمارشان برابر و کلمه‌ها یک‌به‌یک یکی (ترتیب مهم نیست).
 *
 * @param list<string> $a
 * @param list<string> $b
 */
function hodima_seo_advisor_similar_match( array $a, array $b ): bool {

	if ( count( $a ) < 2 || count( $a ) !== count( $b ) ) {
		return false;
	}

	foreach ( $a as $x ) {
		$found = false;
		foreach ( $b as $k => $y ) {
			if ( hodima_seo_advisor_similar_token_match( $x, $y ) ) {
				unset( $b[ $k ] );
				$found = true;
				break;
			}
		}
		if ( ! $found ) {
			return false;
		}
	}

	return true;
}

/**
 * کلید دسته‌بندی: حرف اول (`$len` = 1) یا دو حرف اول (`$len` = 2) هر کلمه، مرتب؛ عدد کامل. املای کلیپ/کلیپس یا بچگانه/بچه‌گانه هم‌دسته
 * می‌شود؛ مقایسه کامل جداست. «آ» با «ا» یکی است.
 *
 * @param list<string> $tokens
 */
function hodima_seo_advisor_similar_key( array $tokens, int $len = 1 ): string {
	$keys = array_map( static fn( string $t ): string => 1 === preg_match( '/\d/', $t ) ? $t : mb_substr( str_replace( 'آ', 'ا', $t ), 0, $len ), $tokens );
	sort( $keys );
	return implode( ' ', $keys );
}

/**
 * صفحه‌های مشابه از روی متن هر صفحه.
 *
 * @param array<string, string> $texts مسیر ← عنوان یا H1 (فقط صفحه‌های سالم و ایندکس‌پذیر و غیرصفحه‌بندی)
 * @param list<string>          $drop  کلمه‌هایی که برداشته شوند (نام سایت)
 * @return array<string, list<string>> مسیر ← مسیرهای مشابه (متن دقیقا یکسان نمی‌آید؛ آن را dup_title/dup_h1 می‌گیرد)
 */
function hodima_seo_advisor_similar_pairs( array $texts, array $drop = [] ): array {

	$tokens  = [];
	$buckets = [];

	foreach ( $texts as $path => $text ) {
		$list = hodima_seo_advisor_similar_tokens( $text, $drop );
		if ( count( $list ) >= 2 ) {
			$tokens[ $path ]                                       = $list;
			$buckets[ hodima_seo_advisor_similar_key( $list ) ][] = (string) $path;
		}
	}

	// دسته بزرگ‌تر از سقف (کلمه‌های عمومی) با دو حرف اول هر کلمه دوباره دسته می‌شود؛ هنوز بزرگ بود رد می‌شود
	$groups = [];
	foreach ( $buckets as $members ) {
		if ( count( $members ) > HODIMA_SEO_ADVISOR_SIMILAR_MAX_BUCKET ) {
			$split = [];
			foreach ( $members as $member ) {
				$split[ hodima_seo_advisor_similar_key( $tokens[ $member ], 2 ) ][] = $member;
			}
			array_push( $groups, ...array_values( $split ) );
		} else {
			$groups[] = $members;
		}
	}

	$out = [];

	foreach ( $groups as $members ) {
		if ( count( $members ) < 2 || count( $members ) > HODIMA_SEO_ADVISOR_SIMILAR_MAX_BUCKET ) {
			continue;
		}
		foreach ( $members as $i => $a ) {
			foreach ( array_slice( $members, $i + 1 ) as $b ) {
				if ( mb_strtolower( $texts[ $a ] ) === mb_strtolower( $texts[ $b ] ) || ! hodima_seo_advisor_similar_match( $tokens[ $a ], $tokens[ $b ] ) ) {
					continue;
				}
				$out[ $a ][] = $b;
				$out[ $b ][] = $a;
			}
		}
	}

	foreach ( $out as $path => $partners ) {
		$out[ $path ] = array_slice( $partners, 0, HODIMA_SEO_ADVISOR_SIMILAR_MAX_PARTNERS );
	}

	return $out;
}
