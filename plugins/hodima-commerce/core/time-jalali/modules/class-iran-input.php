<?php
declare( strict_types=1 );
namespace Hodima\Core\Time_Jalali\Modules;

defined( 'ABSPATH' ) || exit;

/**
 * یکدست‌سازی و اعتبارسنجی ورودی‌های ایرانی (موبایل، کد پستی، کد ملی).
 * همان قاعده‌ها در checkout-fields.js برای بازخورد زنده تکرار شده‌اند؛ هر تغییری هر دو جا.
 */
final class Iran_Input {

    private const DIGITS = [
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ];

    public static function latin_digits( string $value ): string {
        return strtr( $value, self::DIGITS );
    }

    /**
     * موبایل به شکل ۰۹xxxxxxxxx: رقم فارسی/عربی، فاصله و خط تیره، و پیش‌شماره +98 / 0098 / 98
     * یا بدون صفر اول (912…) را می‌پذیرد. اگر قابل تبدیل نبود، همان ورودی تمیزشده برمی‌گردد.
     */
    public static function mobile( string $value ): string {
        $value  = trim( self::latin_digits( $value ) );
        $digits = preg_replace( '/[\s\-().]/u', '', $value ) ?? $value;
        $digits = preg_replace( '/^(\+98|0098|98)(?=9\d{9}$)/', '', $digits ) ?? $digits;
        if ( preg_match( '/^9\d{9}$/', $digits ) ) {
            $digits = '0' . $digits;
        }
        return $digits;
    }

    public static function is_mobile( string $value ): bool {
        return 1 === preg_match( '/^09\d{9}$/', $value );
    }

    /** کد پستی: فقط رقم لاتین (فاصله و خط تیره برداشته می‌شود). */
    public static function postcode( string $value ): string {
        $value = trim( self::latin_digits( $value ) );
        return preg_replace( '/[\s\-]/u', '', $value ) ?? $value;
    }

    public static function is_postcode( string $value ): bool {
        return 1 === preg_match( '/^\d{10}$/', $value );
    }

    /** کد ملی: فقط رقم لاتین؛ کد ۸ یا ۹ رقمی (صفرهای اول نوشته‌نشده) به ۱۰ رقم می‌رسد. */
    public static function national_id( string $value ): string {
        $value = preg_replace( '/[\s\-]/u', '', trim( self::latin_digits( $value ) ) ) ?? '';
        return preg_match( '/^\d{8,9}$/', $value ) ? str_pad( $value, 10, '0', STR_PAD_LEFT ) : $value;
    }

    /** رقم کنترل کد ملی (الگوریتم رسمی ثبت احوال). */
    public static function is_national_id( string $value ): bool {
        if ( ! preg_match( '/^\d{10}$/', $value ) || preg_match( '/^(\d)\1{9}$/', $value ) ) {
            return false;
        }
        $sum = 0;
        for ( $i = 0; $i < 9; $i++ ) {
            $sum += (int) $value[ $i ] * ( 10 - $i );
        }
        $rem   = $sum % 11;
        $check = (int) $value[9];
        return $rem < 2 ? $check === $rem : $check === 11 - $rem;
    }
}
