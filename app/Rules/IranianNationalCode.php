<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * کد ملی ایرانی — الگوریتم رسمی با رقم کنترلی.
 *
 * چرا سخت‌گیرانه است: کد ملی کلید تطبیق است. یک رقم غلط یعنی دو رکورد برای
 * یک نفر، و اصلاحش بعداً داده‌های مالی و آموزشی را به هم می‌ریزد. رکوردهای
 * تکراری که کد ملی ندارند مجازند — فقط کد نامعتبر رد می‌شود.
 *
 * ورودی فارسی و خط تیره را می‌پذیرد؛ normalize باید قبل از این قاعده اجرا شود
 * (یا این قاعده خودش نرمال کند — که می‌کند).
 */
class IranianNationalCode implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) && ! is_int($value)) {
            $fail('کد ملی نامعتبر است.');

            return;
        }

        if (! self::isValid((string) $value)) {
            $fail('کد ملی معتبر نیست.');
        }
    }

    /**
     * اعتبارسنجی کد ملی.
     *
     * قاعده: مجموع ۱۰ رقم اول × وزن (۱۰ تا ۲) + رقم کنترلی، باید مضرب ۱۱ باشد.
     * اگر باقی‌مانده ۱۰ باشد، رقم کنترلی صفر است.
     * کدهای همه‌تکراری (۱۱۱۱۱۱۱۱۱۱) رد می‌شوند.
     */
    public static function isValid(?string $code): bool
    {
        $code = self::normalize($code);

        if (strlen($code) !== 10 || ! ctype_digit($code)) {
            return false;
        }

        // کد همه‌یک و همه‌صفر معتبر شمرده می‌شوند ولی کد ملی واقعی نیستند
        if (preg_match('/^(\d)\1{9}$/', $code) === 1) {
            return false;
        }

        $sum = 0;

        for ($i = 0; $i < 9; $i++) {
            $sum += ((int) $code[$i]) * (10 - $i);
        }

        $remainder = $sum % 11;

        $control = (int) $code[9];

        return $remainder < 2 ? $control === $remainder : $control === 11 - $remainder;
    }

    /**
     * تبدیل ورودی کاربر به رقم خام: ارقام فارسی/عربی → لاتین، حذف خط تیره و فاصله.
     */
    public static function normalize(?string $code): string
    {
        if ($code === null) {
            return '';
        }

        $code = to_english_digits($code);

        // PCRE2 از \u{...} پشتیبانی نمی‌کند؛ نیم‌فاصله به‌صورت کاراکتر واقعی.
        return preg_replace('/[\s\-\x{200C}]/u', '', $code) ?? '';
    }
}
