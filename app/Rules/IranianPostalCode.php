<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * کد پستی ایرانی — ۱۰ رقم.
 *
 * قالب با خط تیره (۱۲۳۴۵-۶۷۸۹) و بدون خط تیره هر دو پذیرفته می‌شود و به شکل
 * استاندارد ذخیره می‌شود. ارقام فارسی هم پذیرفته می‌شود.
 */
class IranianPostalCode implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::isValid(is_scalar($value) ? (string) $value : null)) {
            $fail('کد پستی معتبر نیست. نمونه درست: ۱۲۳۴۵۶۷۸۹');
        }
    }

    public static function isValid(?string $code): bool
    {
        return (bool) preg_match('/^\d{10}$/', self::normalize($code));
    }

    /** @return string  ده رقم بدون خط تیره، یا رشته خالی */
    public static function normalize(?string $code): string
    {
        if ($code === null) {
            return '';
        }

        $code = to_english_digits($code);

        return preg_replace('/[\s\-]/u', '', $code) ?? '';
    }

    /** قالب نمایشی: ۱۲۳۴۵-۶۷۸۹ */
    public static function format(?string $code): string
    {
        $digits = self::normalize($code);

        if (strlen($digits) !== 10) {
            return $digits;
        }

        return substr($digits, 0, 5).'-'.substr($digits, 5);
    }
}
