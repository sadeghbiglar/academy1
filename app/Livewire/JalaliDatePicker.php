<?php

namespace App\Livewire;

use Livewire\Attributes\Modelable;
use Livewire\Component;

/**
 * انتخابگر تاریخ شمسی — تنها راه مجاز برای ورود تاریخ در این پروژه (قانون R2).
 *
 * قرارداد دوطرفه:
 *   - `value` همیشه تاریخ **میلادی** ISO (`2024-08-02`) است؛ چیزی که والد
 *     در property و پایگاه داده می‌بیند. به همین دلیل مرتب‌سازی و
 *     whereBetween روی تاریخ‌ها درست کار می‌کند.
 *   - کاربر فقط شمسی می‌بیند و فقط شمسی تایپ می‌کند (`۱۴۰۳/۰۵/۱۲`).
 *
 * ارقام فارسی، جداکننده‌های متنوع و کلیک روی تقویم همه پذیرفته می‌شوند.
 * تاریخ نامعتبر ذخیره نمی‌شود و پیام فارسی می‌گیرد — هرگز بی‌صدا به تاریخ
 * دیگری تبدیل نمی‌شود.
 */
class JalaliDatePicker extends Component
{
    /** تاریخ میلادی ISO که با property والد دوطرفه sync است. */
    #[Modelable]
    public string $value = '';

    /** آنچه کاربر می‌بیند و تایپ می‌کند: شمسی. */
    public string $display = '';

    public string $label = '';

    public ?string $error = null;

    public bool $calendarOpen = false;

    /** نمای ماه جاری تقویم (شمسی). */
    public int $calYear;

    public int $calMonth;

    public function mount(string $value = '', string $label = ''): void
    {
        $this->label = $label;
        $this->setValue($value);
    }

    /**
     * همگام‌سازی از بیرون (مثلاً وقتی والد مقدار را از پایگاه داده ست می‌کند).
     * بدون این، بازکردن رکورد ویرایشی تاریخ را نشان نمی‌داد.
     */
    public function updatedValue(string $value): void
    {
        if ($this->display !== $this->toDisplay($value)) {
            $this->display = $this->toDisplay($value);
            $this->error = null;
        }
    }

    /**
     * تایپ کاربر. ورودی نامعتبر مقدار والد را **لمس نمی‌کند** تا داده سالم بماند.
     */
    public function updatedDisplay(string $value): void
    {
        $this->error = null;

        if (trim($value) === '') {
            $this->value = '';

            return;
        }

        try {
            $this->value = miladi_date($value);
            $this->error = null;
        } catch (\Throwable) {
            $this->error = 'تاریخ شمسی نامعتبر است. نمونه درست: ۱۴۰۳/۰۵/۱۲';
        }
    }

    public function selectDate(int $year, int $month, int $day): void
    {
        $this->calYear = $year;
        $this->calMonth = $month;
        $this->display = $this->formatDisplay($year, $month, $day);
        $this->value = miladi_date($this->display);
        $this->error = null;
        $this->calendarOpen = false;
    }

    public function toggleCalendar(): void
    {
        if (! $this->calendarOpen) {
            // تقویم روی ماهِ تاریخ انتخاب‌شده باز شود، نه ماه جاری میلادی.
            if ($this->value !== '') {
                [$y, $m] = $this->toParts($this->value);
                $this->calYear = $y;
                $this->calMonth = $m;
            } else {
                $now = jalali_date(now());
                [$this->calYear, $this->calMonth] = $this->splitJalali($now);
            }
        }

        $this->calendarOpen = ! $this->calendarOpen;
    }

    public function nextMonth(): void
    {
        $this->shiftMonth(1);
    }

    public function previousMonth(): void
    {
        $this->shiftMonth(-1);
    }

    public function today(): void
    {
        [$y, $m, $d] = $this->splitJalali(jalali_date(now()));
        $this->selectDate($y, $m, $d);
    }

    /** سلول‌های تقویم: هفته‌شنبه‌تا-جمعه با null برای روزهای پرکننده. */
    public function getCalendarProperty(): array
    {
        $days = jalali_month_days($this->calYear, $this->calMonth);
        $first = new \DateTimeImmutable(miladi_date(sprintf('%d/%02d/01', $this->calYear, $this->calMonth)));

        // هفته شمسی از شنبه شروع می‌شود. Carbon::SATURDAY = 6
        $offset = ((int) $first->format('w') + 1) % 7;

        $cells = array_fill(0, $offset, null);

        for ($d = 1; $d <= $days; $d++) {
            $cells[] = $d;
        }

        return $cells;
    }

    public function getSelectedPartsProperty(): ?string
    {
        if ($this->value === '') {
            return null;
        }

        [$y, $m, $d] = $this->toParts($this->value);

        return to_persian_digits(sprintf('%04d/%02d/%02d', $y, $m, $d));
    }

    public function render()
    {
        return view('livewire.jalali-date-picker');
    }

    // ── کمکی‌های داخلی ─────────────────────────────────────────────

    private function setValue(string $value): void
    {
        $this->value = $value;
        $this->display = $this->toDisplay($value);

        if ($this->value !== '') {
            [$this->calYear, $this->calMonth] = $this->toParts($this->value);
        }
    }

    private function toDisplay(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $shamsi = jalali_date($value);

        return $shamsi ? to_persian_digits($shamsi) : '';
    }

    /** @return array{0:int,1:int,2:int} */
    private function toParts(string $value): array
    {
        $shamsi = jalali_date($value);

        return $this->splitJalali($shamsi ?? jalali_date(now()));
    }

    /**
     * شکستن تاریخ شمسی به سه عدد. مقدار نامعتبر به «امروز» برمی‌گردد تا تقویم
     * هیچ‌وقت در حالت خالی/بی‌در ویرماندن نماند.
     *
     * @return array{0:int,1:int,2:int}
     */
    private function splitJalali(?string $shamsi): array
    {
        if (! $shamsi || ! preg_match('#^(\d{4})/(\d{2})/(\d{2})$#', $shamsi, $m)) {
            $shamsi = jalali_date(now());
            preg_match('#^(\d{4})/(\d{2})/(\d{2})$#', $shamsi, $m);
        }

        return [(int) $m[1], (int) $m[2], (int) $m[3]];
    }

    private function formatDisplay(int $y, int $m, int $d): string
    {
        return to_persian_digits(sprintf('%04d/%02d/%02d', $y, $m, $d));
    }

    private function shiftMonth(int $delta): void
    {
        $m = $this->calMonth + $delta;
        $y = $this->calYear;

        if ($m < 1) {
            $m = 12;
            $y--;
        } elseif ($m > 12) {
            $m = 1;
            $y++;
        }

        $this->calYear = $y;
        $this->calMonth = $m;
    }
}
