<?php

namespace Database\Factories\Concerns;

use App\Rules\IranianNationalCode;

/**
 * تولید کد ملی معتبر برای داده نمونه و تست.
 *
 * `fake()->numerify('##########')` یک رقم تصادفی ۱۰تایی می‌دهد که تقریباً همیشه
 * از نظر رقم کنترلی نامعتبر است. از آن‌جا که ذخیره‌سازی کد ملی را اعتبارسنجی
 * می‌کند، چنین کدی باعث شکست هر تستی می‌شد که به آن نیاز نداشت.
 *
 * این فقط برای داده آزمایشی است؛ کد ملی واقعی متعلق به افراد واقعی است.
 */
trait GeneratesNationalCode
{
    protected function validNationalCode(): string
    {
        $base = str_pad((string) fake()->unique()->numberBetween(1, 999999999), 9, '0', STR_PAD_LEFT);

        $sum = 0;

        for ($i = 0; $i < 9; $i++) {
            $sum += ((int) $base[$i]) * (10 - $i);
        }

        $remainder = $sum % 11;
        $control = $remainder < 2 ? $remainder : 11 - $remainder;

        $code = $base.$control;

        // تضمین: هر کدی که این متد بدهد باید از قاعده رد شود
        assert(IranianNationalCode::isValid($code));

        return $code;
    }
}
