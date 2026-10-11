<?php
/**
 * تاریخ شمسی مشترک همه بسته‌های هدیما (Commerce: تاریخ شمسی سایت؛ SEO: آدرس بایگانی تاریخ).
 *
 * الگوریتم: چرخه ۳۳ ساله (همان ICU و jdf). نوروز ۲۰۲۴ تا ۲۰۳۵ با زمان نجومی اعتدال
 * بهاری (قاعده ظهر تهران) بررسی شد (HODIMA-AUDIT.md بخش ۹۹)؛ آزمون: tools/wp-harness/commerce-tests.php.
 *
 * @package Hodima_Core
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/**
 * میلادی ← شمسی.
 *
 * @param int $gy سال میلادی.
 * @param int $gm ماه میلادی (۱ تا ۱۲).
 * @param int $gd روز.
 * @return array{0: int, 1: int, 2: int} [سال، ماه، روز] شمسی.
 */
function hodima_jalali_from_gregorian( int $gy, int $gm, int $gd ): array {
	$g_d_m = [ 0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334 ];
	$gy2   = ( $gm > 2 ) ? ( $gy + 1 ) : $gy;
	$days  = 355666 + ( 365 * $gy ) + intdiv( $gy2 + 3, 4 ) - intdiv( $gy2 + 99, 100 ) + intdiv( $gy2 + 399, 400 ) + $gd + $g_d_m[ $gm - 1 ];
	$jy    = -1595 + 33 * intdiv( $days, 12053 );
	$days %= 12053;
	$jy   += 4 * intdiv( $days, 1461 );
	$days %= 1461;
	if ( $days > 365 ) {
		--$days;
		$jy  += intdiv( $days, 365 );
		$days = $days % 365;
	}
	$jm = ( $days < 186 ) ? 1 + intdiv( $days, 31 ) : 7 + intdiv( $days - 186, 30 );
	$jd = 1 + ( ( $days < 186 ) ? ( $days % 31 ) : ( ( $days - 186 ) % 30 ) );
	return [ $jy, $jm, $jd ];
}

/**
 * شمسی ← میلادی.
 *
 * @param int $jy سال شمسی.
 * @param int $jm ماه شمسی (۱ تا ۱۲).
 * @param int $jd روز.
 * @return array{0: int, 1: int, 2: int} [سال، ماه، روز] میلادی.
 */
function hodima_jalali_to_gregorian( int $jy, int $jm, int $jd ): array {
	$jy  += 1595;
	$days = -355668 + ( 365 * $jy ) + intdiv( $jy, 33 ) * 8 + intdiv( ( $jy % 33 ) + 3, 4 ) + $jd
		+ ( ( $jm < 7 ) ? ( $jm - 1 ) * 31 : ( ( $jm - 7 ) * 30 ) + 186 );
	$gy   = 400 * intdiv( $days, 146097 );
	$days = $days % 146097;
	if ( $days > 36524 ) {
		--$days;
		$gy  += 100 * intdiv( $days, 36524 );
		$days = $days % 36524;
		if ( $days >= 365 ) {
			++$days;
		}
	}
	$gy  += 4 * intdiv( $days, 1461 );
	$days = $days % 1461;
	if ( $days > 365 ) {
		--$days;
		$gy  += intdiv( $days, 365 );
		$days = $days % 365;
	}
	$gd     = $days + 1;
	$months = [ 31, ( ( 0 === $gy % 4 && 0 !== $gy % 100 ) || 0 === $gy % 400 ) ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 ];
	$gm     = 1;
	foreach ( $months as $length ) {
		if ( $gd <= $length ) {
			break;
		}
		$gd -= $length;
		++$gm;
	}
	return [ $gy, $gm, $gd ];
}

/**
 * سال کبیسه شمسی (اسفند ۳۰ روزه).
 *
 * @param int $jy سال شمسی.
 */
function hodima_jalali_is_leap( int $jy ): bool {
	[ $gy, $gm, $gd ] = hodima_jalali_to_gregorian( $jy, 12, 30 );
	return [ $jy, 12, 30 ] === hodima_jalali_from_gregorian( $gy, $gm, $gd );
}

/**
 * تعداد روزهای یک ماه شمسی.
 *
 * @param int $jy سال شمسی.
 * @param int $jm ماه شمسی.
 */
function hodima_jalali_month_days( int $jy, int $jm ): int {
	if ( $jm <= 6 ) {
		return 31;
	}
	if ( $jm <= 11 ) {
		return 30;
	}
	return hodima_jalali_is_leap( $jy ) ? 30 : 29;
}

/**
 * نام ماه‌های شمسی (کلید ۱ تا ۱۲).
 *
 * @return array<int, string>
 */
function hodima_jalali_months(): array {
	return [ 1 => 'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند' ];
}

/**
 * نام روزهای هفته از شنبه (کلید ۰ = شنبه).
 *
 * @return list<string>
 */
function hodima_jalali_weekdays(): array {
	return [ 'شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه' ];
}

/**
 * رقم لاتین ← فارسی.
 *
 * @param string $text متن.
 */
function hodima_persian_digits( string $text ): string {
	return strtr( $text, [ '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' ] );
}

/**
 * قالب‌بندی شمسی با حروف قالب تاریخ PHP.
 *
 * حروف شمسی: d j D l N w z S، F M m n t، L o Y y؛ ساعت: a A g G h H i s.
 * بقیه (u v e I O P p T Z c r U B W) همان مقدار date() در منطقه زمانی داده‌شده است.
 * «\x» نویسه x را بی‌تغییر می‌گذارد. ارقام خروجی به‌صورت پیش‌فرض فارسی‌اند.
 *
 * @param string             $format         قالب (مثل «j F Y»).
 * @param int                $timestamp      Unix timestamp.
 * @param DateTimeZone|null  $timezone       منطقه زمانی؛ null یعنی UTC (timestamp از قبل با اختلاف ساعت محلی).
 * @param bool               $persian_digits ارقام فارسی.
 */
function hodima_jalali_format( string $format, int $timestamp, ?DateTimeZone $timezone = null, bool $persian_digits = true ): string {
	$date             = ( new DateTimeImmutable( '@' . $timestamp ) )->setTimezone( $timezone ?? new DateTimeZone( 'UTC' ) );
	[ $gy, $gm, $gd ] = array_map( 'intval', explode( '-', $date->format( 'Y-n-j' ) ) );
	[ $jy, $jm, $jd ] = hodima_jalali_from_gregorian( $gy, $gm, $gd );
	$weekday          = ( (int) $date->format( 'w' ) + 1 ) % 7; // ۰ = شنبه
	$hour             = (int) $date->format( 'G' );
	$months           = hodima_jalali_months();
	$weekdays         = hodima_jalali_weekdays();

	$output = '';
	$length = strlen( $format );
	for ( $i = 0; $i < $length; $i++ ) {
		$char = $format[ $i ];
		if ( '\\' === $char ) {
			++$i;
			$output .= $format[ $i ] ?? '';
			continue;
		}
		$output .= match ( $char ) {
			'd'      => sprintf( '%02d', $jd ),
			'j'      => (string) $jd,
			'D'      => mb_substr( $weekdays[ $weekday ], 0, 1 ),
			'l'      => $weekdays[ $weekday ],
			'N'      => (string) ( $weekday + 1 ),
			'w'      => (string) $weekday,
			'z'      => (string) ( ( $jm <= 7 ? ( $jm - 1 ) * 31 : 186 + ( $jm - 7 ) * 30 ) + $jd - 1 ),
			'S'      => 'ام',
			'F', 'M' => $months[ $jm ],
			'm'      => sprintf( '%02d', $jm ),
			'n'      => (string) $jm,
			't'      => (string) hodima_jalali_month_days( $jy, $jm ),
			'L'      => hodima_jalali_is_leap( $jy ) ? '1' : '0',
			'Y', 'o' => (string) $jy,
			'y'      => substr( (string) $jy, -2 ),
			'a'      => $hour < 12 ? 'ق.ظ' : 'ب.ظ',
			'A'      => $hour < 12 ? 'قبل از ظهر' : 'بعد از ظهر',
			'g', 'G', 'h', 'H', 'i', 's', 'u', 'v', 'e', 'I', 'O', 'P', 'p', 'T', 'Z', 'c', 'r', 'U', 'B', 'W' => $date->format( $char ),
			default  => $char,
		};
	}

	return $persian_digits ? hodima_persian_digits( $output ) : $output;
}
