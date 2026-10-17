<?php

namespace Tests\Unit;

use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| تست اصول پایه: نمایش شمسی، ورودی شمسی، ارقام فارسی
|--------------------------------------------------------------------------
| نگهبان قوانین R1 و R3 از REQUIREMENTS.md: پایگاه داده میلادی، ورودی و نمایش
| شمسی. هر فیلد تاریخی در این پروژه از همین توابع عبور می‌کند.
*/

class HelpersTest extends TestCase
{
    public function test_jalali_date_converts_a_gregorian_string_to_shamsi(): void
    {
        $this->assertSame('1403/05/12', jalali_date('2024-08-02'));
    }

    public function test_jalali_date_returns_null_for_empty_values(): void
    {
        $this->assertNull(jalali_date(null));
        $this->assertNull(jalali_date(''));
        $this->assertNull(jalali_date('   '));
    }

    public function test_jalali_date_accepts_carbon_and_timestamp(): void
    {
        $this->assertSame('1403/05/12 10:30', jalali_date(Carbon::parse('2024-08-02 10:30:00'), 'Y/m/d H:i'));
        $this->assertSame('1403/05/12', jalali_date(strtotime('2024-08-02')));
    }

    public function test_jalali_date_handles_the_shamsi_new_year_boundary(): void
    {
        $this->assertSame('1403/01/01', jalali_date('2024-03-20'));
        $this->assertSame('1402/12/29', jalali_date('2024-03-19'));
    }

    public function test_miladi_date_converts_shamsi_to_gregorian(): void
    {
        $this->assertSame('2024-08-02', miladi_date('1403/05/12'));
    }

    public function test_miladi_date_accepts_persian_digits_and_mixed_separators(): void
    {
        foreach (['۱۴۰۳/۰۵/۱۲', '۱۴۰۳-۰۵-۱۲', '۱۴۰۳.۰۵.۱۲', '۱۴۰۳ ۰۵ ۱۲', '1403/5/12'] as $input) {
            $this->assertSame('2024-08-02', miladi_date($input), "input: {$input}");
        }
    }

    public function test_miladi_date_returns_null_for_empty_input(): void
    {
        $this->assertNull(miladi_date(null));
        $this->assertNull(miladi_date(''));
    }

    public function test_miladi_date_rejects_an_impossible_month(): void
    {
        $this->expectException(InvalidArgumentException::class);
        miladi_date('1403/13/01');
    }

    public function test_miladi_date_rejects_day_31_in_a_30_day_month(): void
    {
        $this->expectException(InvalidArgumentException::class);
        miladi_date('1403/07/31');
    }

    public function test_miladi_date_rejects_30_esfand_in_a_non_leap_year(): void
    {
        // 1403 و 1399 کبیسه‌اند (نوروز ۱۴۰۴ برابر ۲۰۲۵-۰۳-۲۰ است)، 1404 نیست.
        $this->expectException(InvalidArgumentException::class);
        miladi_date('1404/12/30');
    }

    public function test_30_esfand_is_valid_only_in_a_leap_year(): void
    {
        $this->assertTrue(is_valid_jalali_date('1399/12/30'), '1399 is a leap shamsi year');
        $this->assertTrue(is_valid_jalali_date('1403/12/30'), '1403 is a leap shamsi year');
        $this->assertFalse(is_valid_jalali_date('1404/12/30'), '1404 is not');
    }

    public function test_miladi_date_rejects_garbage(): void
    {
        $this->expectException(InvalidArgumentException::class);
        miladi_date('سلام');
    }

    public function test_to_english_digits_handles_persian_and_arabic_digits(): void
    {
        $this->assertSame('123456789', to_english_digits('۱۲۳۴۵۶۷۸۹'));
        $this->assertSame('456', to_english_digits('٤٥٦'));
        $this->assertSame('abc123', to_english_digits('abc۱۲۳'));
    }

    public function test_to_persian_digits_is_for_display_only(): void
    {
        $this->assertSame('۱۴۰۳/۰۵/۱۲', to_persian_digits('1403/05/12'));
        $this->assertSame('۰', to_persian_digits(0));
        $this->assertSame('', to_persian_digits(null));
    }

    public function test_shamsi_conversion_round_trips(): void
    {
        $gregorian = '2024-08-02';
        $this->assertSame($gregorian, miladi_date(jalali_date($gregorian)));
    }

    public function test_parse_number_accepts_persian_digits_and_separators(): void
    {
        $this->assertSame(1234.0, parse_number('۱۲۳۴'));
        $this->assertSame(12500.0, parse_number('۱۲٬۵۰۰'));
        $this->assertSame(12500.0, parse_number('12,500'));
        $this->assertSame(12345.0, parse_number('۱۲ ۳۴۵'));
        $this->assertSame(12500.0, parse_number('12500'));
    }

    public function test_parse_number_distinguishes_empty_from_zero(): void
    {
        $this->assertNull(parse_number(''));
        $this->assertNull(parse_number('   '));
        $this->assertNull(parse_number(null));
        $this->assertNull(parse_number('سلام'));
        $this->assertSame(0.0, parse_number('0'));
        $this->assertSame(0.0, parse_number('۰'));
    }

    public function test_parse_integer_converts_for_codes(): void
    {
        $this->assertSame(123456789, parse_integer('۰۰۱۲۳۴۵۶۷۸۹'));
        $this->assertSame(12345678, parse_integer('۰۰۱۲۳۴۵۶۷۸'));
        $this->assertNull(parse_integer(''));
    }

    public function test_jalali_month_days_respects_leap_years(): void
    {
        $this->assertSame(31, jalali_month_days(1403, 1));
        $this->assertSame(30, jalali_month_days(1403, 7));
        $this->assertSame(31, jalali_month_days(1403, 6));
        $this->assertSame(29, jalali_month_days(1404, 12));
        $this->assertSame(30, jalali_month_days(1399, 12));
        $this->assertSame(30, jalali_month_days(1403, 12));
    }

    public function test_a_whole_leap_shamsi_year_converts(): void
    {
        $count = 0;

        for ($m = 1; $m <= 12; $m++) {
            for ($d = 1; $d <= jalali_month_days(1399, $m); $d++) {
                $gregorian = miladi_date(sprintf('1399/%02d/%02d', $m, $d));
                $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $gregorian);
                $count++;
            }
        }

        $this->assertSame(366, $count);
    }
}
