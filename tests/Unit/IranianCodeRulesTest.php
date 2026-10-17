<?php

namespace Tests\Unit;

use App\Rules\IranianNationalCode;
use App\Rules\IranianPostalCode;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| اعتبارسنجی کد ملی و کد پستی
|--------------------------------------------------------------------------
| کد ملی کلید تطبیق است؛ رکورد تکراری یعنی دو دانش‌آموز برای یک نفر و
| بی‌اعتباری داده‌های مالی و آموزشی در آینده.
*/

class IranianCodeRulesTest extends TestCase
{
    public function test_it_accepts_real_valid_national_codes(): void
    {
        foreach (['0499370899', '0084575948', '1234567891'] as $code) {
            $this->assertTrue(IranianNationalCode::isValid($code), "code: {$code}");
        }
    }

    public function test_it_rejects_a_wrong_control_digit(): void
    {
        // همان کد معتبر با رقم کنترلی عوض‌شده
        $this->assertFalse(IranianNationalCode::isValid('0084575947'));
        $this->assertFalse(IranianNationalCode::isValid('0499370890'));
    }

    public function test_it_rejects_repeated_digit_codes(): void
    {
        // از نظر الگوریتم معتبرند ولی کد ملی واقعی نیستند
        $this->assertFalse(IranianNationalCode::isValid('1111111111'));
        $this->assertFalse(IranianNationalCode::isValid('0000000000'));
    }

    public function test_it_rejects_wrong_length_and_non_digits(): void
    {
        foreach (['', '123456789', '12345678901', 'abcdefghij', null] as $code) {
            $this->assertFalse(IranianNationalCode::isValid($code), 'code: '.var_export($code, true));
        }
    }

    public function test_it_normalizes_persian_digits_and_dashes_before_validating(): void
    {
        // همان کد معتبر، اما با ارقام فارسی و خط تیره
        $this->assertTrue(IranianNationalCode::isValid('۰۰۸۴۵۷۵۹۴۸'));
        $this->assertTrue(IranianNationalCode::isValid('008-457-5948'));
        $this->assertSame('0084575948', IranianNationalCode::normalize('۰۰۸-۴۵۷-۵۹۴۸'));
    }

    public function test_the_control_digit_algorithm_is_self_consistent(): void
    {
        // رقم کنترلی را مستقلاً حساب کن و با قاعده مقایسه کن؛ اگر پیاده‌سازی
        // با تعریف الگوریتم نخواند، این تست می‌افتد.
        for ($i = 0; $i < 300; $i++) {
            $base = str_pad((string) $i, 9, '0', STR_PAD_LEFT);
            $sum = 0;
            for ($k = 0; $k < 9; $k++) {
                $sum += ((int) $base[$k]) * (10 - $k);
            }
            $remainder = $sum % 11;
            $control = $remainder < 2 ? $remainder : 11 - $remainder;

            $full = $base.$control;

            if (preg_match('/^(\d)\1{9}$/', $full) === 1) {
                continue; // کد همه‌تکراری عمداً رد می‌شود
            }

            $this->assertTrue(IranianNationalCode::isValid($full), "{$full} should be valid");
        }
    }

    public function test_a_single_digit_change_breaks_validity_most_of_the_time(): void
    {
        // کنترل‌کردن صحت الگوریتم: تغییر یک رقم نباید اکثراً معتبر بماند
        $valid = 0;
        $stillValid = 0;

        for ($i = 0; $i < 100; $i++) {
            $base = str_pad((string) $i, 9, '0', STR_PAD_LEFT);
            $sum = 0;
            for ($k = 0; $k < 9; $k++) {
                $sum += ((int) $base[$k]) * (10 - $k);
            }
            $r = $sum % 11;
            $full = $base.($r < 2 ? $r : 11 - $r);

            $changed = $full;
            $changed[3] = (string) (((int) $full[3] + 1) % 10);

            if (IranianNationalCode::isValid($full)) {
                $valid++;

                if (IranianNationalCode::isValid($changed)) {
                    $stillValid++;
                }
            }
        }

        $this->assertGreaterThan(0, $valid);
        $this->assertLessThan((int) ($valid * 0.2), $stillValid);
    }

    public function test_postal_code_accepts_ten_digits_with_or_without_dashes(): void
    {
        $this->assertTrue(IranianPostalCode::isValid('1234567890'));
        $this->assertTrue(IranianPostalCode::isValid('12345-67890'));
        $this->assertTrue(IranianPostalCode::isValid('۱۲۳۴۵۶۷۸۹۰'));
        $this->assertSame('1234567890', IranianPostalCode::normalize('۱۲۳۴۵-۶۷۸۹۰'));
    }

    public function test_postal_code_rejects_wrong_length(): void
    {
        foreach (['123456789', '12345678901', 'abcdefghij', '', null] as $code) {
            $this->assertFalse(IranianPostalCode::isValid($code), 'code: '.var_export($code, true));
        }
    }

    public function test_postal_code_formats_for_display(): void
    {
        $this->assertSame('12345-67890', IranianPostalCode::format('1234567890'));
        $this->assertSame('12345-67890', IranianPostalCode::format('۱۲۳۴۵-۶۷۸۹۰'));
        // ورودی نامعتبر نباید خراب شود
        $this->assertSame('123', IranianPostalCode::format('123'));
    }

    public function test_the_rules_run_through_laravel_validation_with_persian_messages(): void
    {
        $v = validator(
            ['national_code' => '۰۰۸۴۵۷۵۹۴۷', 'postal_code' => '۱۲۳'],
            ['national_code' => ['required', new IranianNationalCode], 'postal_code' => ['required', new IranianPostalCode]]
        );

        $this->assertTrue($v->fails());
        $this->assertSame('کد ملی معتبر نیست.', $v->errors()->first('national_code'));
        $this->assertStringContainsString('کد پستی', $v->errors()->first('postal_code'));
    }

    public function test_valid_codes_pass_laravel_validation(): void
    {
        $v = validator(
            ['national_code' => '۰۰۸-۴۵۷-۵۹۴۸', 'postal_code' => '۱۲۳۴۵-۶۷۸۹۰'],
            ['national_code' => ['required', new IranianNationalCode], 'postal_code' => ['required', new IranianPostalCode]]
        );

        $this->assertFalse($v->fails());
    }
}
