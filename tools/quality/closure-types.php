<?php
/**
 * بررسی نوع کلوژرها و arrow functionهای قالب (بخش ۱۵۹ HODIMA-AUDIT.md).
 *
 * PHPStan سطح ۶ نوع کلوژر را نمی‌خواهد، ولی قانون قالب «نوع ورودی و خروجی همه توابع» است
 * (کال‌بک هوک وردپرس: ورودی `mixed` و تبدیل داخل تابع). این فایل با tokenizer خود PHP
 * هر `function (...)` و `fn (...)` بی‌نام را می‌گیرد و می‌خواهد:
 *   - هر پارامتر نوع داشته باشد (نه فقط `$x`)،
 *   - خروجی با `: نوع` مشخص باشد (بعد از `use (...)` اگر هست).
 * بدون baseline: هر مورد خطاست.
 *
 *   php tools/quality/closure-types.php hodima [پوشه‌یا‌فایل‌دیگر ...]
 */

declare(strict_types=1);

$roots = array_slice( $argv, 1 ) ?: [ 'hodima' ];
$bad   = 0;
$files = 0;

foreach ( $roots as $root ) {
	$items = is_dir( $root )
		? new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) )
		: [ new SplFileInfo( $root ) ];

	foreach ( $items as $file ) {
		$path = str_replace( '\\', '/', (string) $file->getPathname() );

		if ( ! str_ends_with( $path, '.php' ) || str_contains( $path, '/vendor/' ) || str_contains( $path, '/user-panel/' ) || str_contains( $path, '/node_modules/' ) ) {
			continue;
		}

		++$files;
		$bad += check_file( $path );
	}
}

if ( $bad > 0 ) {
	fwrite( STDOUT, "  ✘ نوع کلوژر: {$bad} مورد بدون نوع\n" );
	exit( 1 );
}

fwrite( STDOUT, "  ✔ نوع کلوژرها: {$files} فایل بررسی شد، همه نوع دارند\n" );
exit( 0 );

/** @return int تعداد خطا */
function check_file( string $path ): int {

	$tokens = token_get_all( (string) file_get_contents( $path ) );
	$count  = count( $tokens );
	$errors = 0;
	$line   = 1;

	for ( $i = 0; $i < $count; $i++ ) {

		$tok = $tokens[ $i ];

		if ( is_array( $tok ) ) {
			$line = $tok[2];
		}

		if ( ! is_array( $tok ) || ! in_array( $tok[0], [ T_FUNCTION, T_FN ], true ) ) {
			continue;
		}

		// نام‌دار (function name(...)) یا متد: بعد از function یک T_STRING می‌آید؛ کلوژر: ( یا & (
		$j = next_sig( $tokens, $i + 1 );
		if ( null !== $j && '&' === $tokens[ $j ] ) {
			$j = next_sig( $tokens, $j + 1 );
		}
		if ( null === $j || '(' !== $tokens[ $j ] ) {
			continue;
		}

		// پارامترها تا پرانتز بسته
		$depth  = 0;
		$k      = $j;
		$param  = [];
		$params = [];
		for ( ; $k < $count; $k++ ) {
			$t = $tokens[ $k ];
			if ( '(' === $t || '[' === $t ) {
				++$depth;
				if ( 1 === $depth ) {
					continue;
				}
			} elseif ( ')' === $t || ']' === $t ) {
				--$depth;
				if ( 0 === $depth ) {
					break;
				}
			} elseif ( ',' === $t && 1 === $depth ) {
				$params[] = $param;
				$param    = [];
				continue;
			}
			if ( $depth >= 1 ) {
				$param[] = $t;
			}
		}
		$params[] = $param;

		foreach ( $params as $p ) {
			$sig = array_values( array_filter( $p, static fn( mixed $t ): bool => ! ( is_array( $t ) && in_array( $t[0], [ T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ], true ) ) ) );
			if ( ! $sig ) {
				continue;
			}
			$first = $sig[0];
			// بی‌نوع: اول پارامتر خود متغیر است (یا & / ... و بعد متغیر)
			$is_var = ( is_array( $first ) && T_VARIABLE === $first[0] )
				|| ( ( '&' === $first || ( is_array( $first ) && T_ELLIPSIS === $first[0] ) ) && isset( $sig[1] ) && is_array( $sig[1] ) && T_VARIABLE === $sig[1][0] );
			if ( $is_var ) {
				echo "  {$path}:{$line} پارامتر بدون نوع\n";
				++$errors;
			}
		}

		// خروجی: بعد از ) و (use (...))
		$m = next_sig( $tokens, $k + 1 );
		if ( null !== $m && is_array( $tokens[ $m ] ) && T_USE === $tokens[ $m ][0] ) {
			$m = next_sig( $tokens, $m + 1 ); // (
			$d = 0;
			for ( ; $m < $count; $m++ ) {
				if ( '(' === $tokens[ $m ] ) {
					++$d;
				} elseif ( ')' === $tokens[ $m ] ) {
					--$d;
					if ( 0 === $d ) {
						break;
					}
				}
			}
			$m = next_sig( $tokens, $m + 1 );
		}
		if ( null === $m || ':' !== $tokens[ $m ] ) {
			echo "  {$path}:{$line} خروجی بدون نوع\n";
			++$errors;
		}
	}

	return $errors;
}

/** اندیس اولین توکن غیرفضای‌خالی/کامنت از $from به بعد. */
function next_sig( array $tokens, int $from ): ?int {

	$count = count( $tokens );
	for ( $i = $from; $i < $count; $i++ ) {
		$t = $tokens[ $i ];
		if ( is_array( $t ) && in_array( $t[0], [ T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ], true ) ) {
			continue;
		}
		return $i;
	}
	return null;
}
