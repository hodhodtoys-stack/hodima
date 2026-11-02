<?php
/**
 * خط‌کش پیکسلی مشترک عنوان و توضیح متا (سئوباکس و دستار سئو)
 * Path: core/seobox/seobox-metrics.php
 *
 * تا SEO 3.5.3 سئوباکس عنوان را با پیکسل (canvas مرورگر، فونت Arial/Tahoma هر دستگاه) می‌سنجید و دستار با نویسه (mb_strlen)؛
 * ادمین در یک صفحه دو عدد متفاوت می‌دید. حالا هر دو یک خط‌کش دارند: عرض پیکسلی با فونت وزیرمتن ۴۰۰ (فونت قالب هدیما).
 * مرورگر همان فونت را از assets/vazirmatn-ref.woff2 بارگذاری می‌کند؛ PHP همان عرض را از جدول سرور می‌سازد
 * (tools/fonts/make-seo-metrics.py). سقف‌ها فقط همین‌جا نوشته می‌شوند و به جاوااسکریپت منتقل می‌شوند.
 *
 * دقت: جدول چهارشکلی (تنها/اول/وسط/آخر) + لیگاتور «لا»؛ روی ۴۰ متن نمونه میانگین خطا ≈ ۰٫۶٪ و بیشینه ≈ ۶٪ (متن بسیار
 * کوتاه)، چون کرنینگ و بعضی شکل‌های وابسته به همسایه (مثل «یی») در جدول نیست. نمایش دقیق گوگل را تضمین نمی‌کند:
 * وزیرمتن فونت سایت است، نه فونت نتایج گوگل؛ سقف ۵۸۰/۹۲۰ تخمین رایج است.
 */

declare(strict_types=1);

defined( 'ABSPATH' ) || exit;

/** سقف عرض پیکسلی و کف نسبی (سهمی از سقف) هر فیلد؛ اندازه فونت مرجع به پیکسل. */
const HODIMA_SEO_PX_LIMITS = [
	'title'       => [ 'max' => 580, 'min_ratio' => 0.5, 'font_px' => 20 ],
	'description' => [ 'max' => 920, 'min_ratio' => 0.4, 'font_px' => 14 ],
];

/** خانواده فونت مرجع در مرورگر (FontFace). */
const HODIMA_SEO_PX_FONT_FAMILY = 'HodimaSeoRef';

/**
 * سقف و کف پیکسلی یک فیلد.
 *
 * @return array{max: int, min: int, font_px: int}
 */
function hodima_seo_px_limit( string $field ): array {
	$limit = HODIMA_SEO_PX_LIMITS[ 'description' === $field || 'meta' === $field ? 'description' : 'title' ];
	return [ 'max' => $limit['max'], 'min' => (int) round( $limit['max'] * $limit['min_ratio'] ), 'font_px' => $limit['font_px'] ];
}

/** @return array{plain: array<int, int>, joined: array<int, list<int>>, dual: array<int, int>, lam_alef: array<int, list<int>>, default: int} */
function hodima_seo_px_table(): array {
	static $table = null;
	return $table ??= require __DIR__ . '/seobox-metrics-table.php';
}

/**
 * عرض پیکسلی متن با فونت مرجع (گرد به عدد صحیح).
 *
 * @param string $field title (۲۰px) یا description/meta (۱۴px)
 */
function hodima_seo_text_px( string $text, string $field = 'title' ): int {

	$text = trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
	if ( '' === $text ) {
		return 0;
	}

	$chars = preg_split( '//u', $text, -1, PREG_SPLIT_NO_EMPTY );
	if ( false === $chars ) {
		return 0;
	}

	$table = hodima_seo_px_table();
	$marks = static fn( int $cp ): bool => ( $cp >= 0x64B && $cp <= 0x65F ) || 0x670 === $cp || ( $cp >= 0x610 && $cp <= 0x61A ) || ( $cp >= 0x6D6 && $cp <= 0x6ED );
	$cps   = array_map( 'mb_ord', $chars );
	$count = count( $cps );
	$sum   = 0;
	$prev  = 0; // کدنقطه حرف قبلی که می‌تواند به بعدی بچسبد (دو طرفه)، وگرنه صفر
	$skip  = -1;

	foreach ( $cps as $i => $cp ) {
		if ( $i === $skip || $marks( $cp ) ) { // علامت‌های اعراب عرض صفر دارند؛ الف لیگاتور قبلا شمرده شد
			continue;
		}
		if ( 0x200C === $cp ) { // نیم‌فاصله اتصال را قطع می‌کند
			$prev = 0;
			continue;
		}
		if ( 0x200D === $cp ) {
			continue;
		}

		$joins_prev = 0 !== $prev && isset( $table['dual'][ $prev ] );

		// لیگاتور «ل» + الف
		if ( 0x644 === $cp && $i + 1 < $count && isset( $table['lam_alef'][ $cps[ $i + 1 ] ] ) ) {
			$sum += $table['lam_alef'][ $cps[ $i + 1 ] ][ $joins_prev ? 1 : 0 ];
			$skip = $i + 1;
			$prev = 0; // الف به بعدی نمی‌چسبد
			continue;
		}

		if ( isset( $table['joined'][ $cp ] ) ) {
			$forms = $table['joined'][ $cp ];
			$next  = 0;
			for ( $j = $i + 1; $j < $count; ++$j ) {
				if ( ! $marks( $cps[ $j ] ) ) {
					$next = $cps[ $j ];
					break;
				}
			}
			if ( isset( $table['dual'][ $cp ] ) ) { // [تنها، اول، وسط، آخر]
				$joins_next = 0 !== $next && isset( $table['joined'][ $next ] );
				$sum       += $forms[ $joins_prev ? ( $joins_next ? 2 : 3 ) : ( $joins_next ? 1 : 0 ) ];
				$prev       = $cp;
			} else { // [تنها، آخر]
				$sum += $forms[ $joins_prev ? 1 : 0 ];
				$prev = 0;
			}
			continue;
		}

		$sum += $table['plain'][ $cp ] ?? $table['default'];
		$prev = 0;
	}

	return (int) round( $sum / 10000 * HODIMA_SEO_PX_LIMITS['description' === $field || 'meta' === $field ? 'description' : 'title']['font_px'] );
}
