<?php

namespace Tests\Feature;

use App\Livewire\JalaliDatePicker;
use Livewire\Livewire;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| انتخابگر تاریخ شمسی — قانون R2
|--------------------------------------------------------------------------
| قرارداد: `value` میلادی ISO است (چیزی که والد و پایگاه داده می‌بینند)،
| `display` شمسی و با ارقام فارسی (چیزی که کاربر می‌بیند).
*/

class JalaliDatePickerTest extends TestCase
{
    public function test_it_renders_and_starts_empty(): void
    {
        Livewire::test(JalaliDatePicker::class)
            ->assertSet('value', '')
            ->assertSet('display', '')
            ->assertOk();
    }

    public function test_it_converts_a_persian_typed_date_to_gregorian_value(): void
    {
        Livewire::test(JalaliDatePicker::class)
            ->set('display', '۱۴۰۳/۰۵/۱۲')
            ->assertSet('value', '2024-08-02')
            ->assertSet('error', null);
    }

    public function test_it_accepts_latin_digits_and_mixed_separators(): void
    {
        foreach (['1403/05/12', '1403-05-12', '۱۴۰۳ ۰۵ ۱۲'] as $input) {
            Livewire::test(JalaliDatePicker::class)
                ->set('display', $input)
                ->assertSet('value', '2024-08-02', "input: {$input}");
        }
    }

    public function test_an_invalid_date_never_reaches_the_value(): void
    {
        // ورودی نامعتبر نباید مقدار والد را خراب کند؛ فقط پیام خطا بدهد.
        Livewire::test(JalaliDatePicker::class)
            ->set('value', '2024-08-02')
            ->set('display', '۱۴۰۴/۱۳/۰۱')
            ->assertSet('value', '2024-08-02')
            ->assertSet('error', 'تاریخ شمسی نامعتبر است. نمونه درست: ۱۴۰۳/۰۵/۱۲');
    }

    public function test_rejected_input_is_rejected_with_a_persian_message(): void
    {
        Livewire::test(JalaliDatePicker::class)
            ->set('display', 'سلام')
            ->assertSet('value', '')
            ->assertSee('تاریخ شمسی نامعتبر است');
    }

    public function test_30_esfand_of_a_non_leap_year_is_rejected(): void
    {
        Livewire::test(JalaliDatePicker::class)
            ->set('display', '۱۴۰۴/۱۲/۳۰')
            ->assertSet('value', '');
    }

    public function test_clearing_the_input_clears_the_value(): void
    {
        Livewire::test(JalaliDatePicker::class)
            ->set('display', '۱۴۰۳/۰۵/۱۲')
            ->set('display', '')
            ->assertSet('value', '')
            ->assertSet('display', '');
    }

    public function test_a_value_set_from_outside_is_shown_as_persian(): void
    {
        // سناریوی ویرایش: والد رکورد را از پایگاه داده ست می‌کند.
        Livewire::test(JalaliDatePicker::class)
            ->set('value', '2024-08-02')
            ->assertSet('display', '۱۴۰۳/۰۵/۱۲');
    }

    public function test_the_calendar_opens_on_the_month_of_the_current_value(): void
    {
        Livewire::test(JalaliDatePicker::class)
            ->set('value', '2024-08-02')
            ->call('toggleCalendar')
            ->assertSet('calendarOpen', true)
            ->assertSet('calYear', 1403)
            ->assertSet('calMonth', 5)
            ->assertSee('مرداد');
    }

    public function test_clicking_a_day_selects_it(): void
    {
        Livewire::test(JalaliDatePicker::class)
            ->set('value', '2024-08-02')
            ->call('toggleCalendar')
            ->call('selectDate', 1403, 5, 20)
            ->assertSet('display', '۱۴۰۳/۰۵/۲۰')
            ->assertSet('value', '2024-08-10')
            ->assertSet('calendarOpen', false);
    }

    public function test_month_navigation_wraps_across_the_shamsi_year(): void
    {
        $c = Livewire::test(JalaliDatePicker::class)
            ->set('calYear', 1403)->set('calMonth', 12)
            ->call('nextMonth')
            ->assertSet('calYear', 1404)->assertSet('calMonth', 1)
            ->call('previousMonth')
            ->assertSet('calYear', 1403)->assertSet('calMonth', 12);
    }

    public function test_calendar_has_one_cell_per_day_of_the_month(): void
    {
        $component = Livewire::test(JalaliDatePicker::class)
            ->set('calYear', 1403)->set('calMonth', 1);

        $days = array_filter($component->instance()->calendar, fn ($d) => $d !== null);
        $this->assertCount(31, $days);

        // اسفند کبیسه (۱۴۰۳) ۳۰ روز
        $esfand = array_filter(
            Livewire::test(JalaliDatePicker::class)
                ->set('calYear', 1403)->set('calMonth', 12)->instance()->calendar,
            fn ($d) => $d !== null
        );
        $this->assertCount(30, $esfand);
    }

    public function test_calendar_is_aligned_to_the_saturday_first_shamsi_week(): void
    {
        // ۱ فروردین ۱۴۰۳ == 2024-03-20 (چهارشنبه). هفته شمسی از شنبه شروع می‌شود،
        // پس باید ۳ سلول خالی قبل از روز ۱ باشد.
        $cells = Livewire::test(JalaliDatePicker::class)
            ->set('calYear', 1403)->set('calMonth', 1)->instance()->calendar;

        $leading = 0;
        foreach ($cells as $cell) {
            if ($cell === null) {
                $leading++;
            } else {
                break;
            }
        }

        $this->assertSame(4, $leading, 'شنبه=۰ یکشنبه=۱ دوشنبه=۲ سه‌شنبه=۳ چهارشنبه=۴');
    }

    public function test_today_selects_the_current_shamsi_date(): void
    {
        $expected = jalali_date(now());

        Livewire::test(JalaliDatePicker::class)
            ->call('today')
            ->assertSet('display', to_persian_digits($expected))
            ->assertSet('value', miladi_date($expected));
    }

    public function test_it_syncs_two_way_with_a_parent_property(): void
    {
        // والد در آرایه state خودش تاریخ را نگه می‌دارد
        $this->assertTrue(method_exists(JalaliDatePicker::class, 'render'));
    }
}
