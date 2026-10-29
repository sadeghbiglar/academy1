<?php

use App\Services\TeacherHours;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * گزارش ساعت کارکرد اساتید (شماره 41).
 *
 * برخلاف گزارش‌های دیگر که کنترلر + ویو هستند، این صفحه یک کامپوننت
 * Livewire است، چون فیلتر بازه باید از «تقویم شمسی» بیاید (قانون R2) و آن
 * کامپوننت جز با والد Livewire دوطرفه نمی‌شود. ورودیِ متنی خام، تاریخِ
 * میلادیِ ناخواسته را به سامانه راه می‌دهد.
 */
new #[Layout('layouts::academy')]
class extends Component
{
    public string $title = 'ساعت کارکرد اساتید';

    /** بازه به صورت میلادی ISO؛ picker همان را شمسی نشان می‌دهد. */
    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public function mount(): void
    {
        // نشانیِ اشتراک‌گذاری‌شده ممکن است شمسی باشد («۱۴۰۴/۰۷/۰۹»)، چون همان
        // چیزی است که کاربر روی صفحه می‌بیند. هر دو شکل باید خوانده شوند.
        $this->from = $this->toIso($this->from);
        $this->to = $this->toIso($this->to);
    }

    #[Computed]
    public function rows()
    {
        $hours = new TeacherHours;

        return $hours->byTeacher($this->from ?: null, $this->to ?: null);
    }

    #[Computed]
    public function totalHours()
    {
        return (new TeacherHours)->totalHours($this->rows());
    }

    public function clearFilters(): void
    {
        $this->from = '';
        $this->to = '';
    }

    /** هر شکلِ غیرمیلادی را به ISO تبدیل می‌کند؛ خالی و نامعتبر خالی می‌ماند. */
    private function toIso(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        // از قبل میلادی ISO است — همان را نگه می‌دار
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return $value;
        }

        try {
            return miladi_date($value) ?? '';
        } catch (\InvalidArgumentException) {
            // تاریخ نامعتبر یعنی بازهٔ خالی، نه خطای مرگبار — ولی نباید
            // بی‌صدا به کل تاریخ‌ها تبدیل شود
            return '';
        }
    }
}
?>
<div>
    <h1 class="text-2xl font-bold mb-4">{{ $this->title }}</h1>

    {{-- بازه فقط از تقویم شمسی پر می‌شود؛ ورودی متنی خام نداریم (R2) --}}
    <div class="bg-base-100 rounded-box shadow p-4 mb-6 grid sm:grid-cols-2 lg:grid-cols-3 gap-3 items-end">
        <livewire:jalali-date-picker wire:model.live="from" label="از تاریخ (شمسی)" />

        <livewire:jalali-date-picker wire:model.live="to" label="تا تاریخ (شمسی)" />

        <div class="flex gap-2">
            @if ($from || $to)
                <x-button label="حذف فیلتر" class="btn-ghost btn-sm" wire:click="clearFilters" />
            @endif
        </div>
    </div>

    <x-card title="ساعت کارکرد هر استاد" class="mb-6">
        <table class="table table-sm">
            <thead>
                <tr>
                    <th>#</th>
                    <th>استاد</th>
                    <th>جلسات برگزارشده</th>
                    <th>دقیقه</th>
                    <th>ساعت</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->rows as $index => $row)
                    <tr>
                        <td>{{ to_persian_digits($index + 1) }}</td>
                        <td>{{ $row['teacher']->first_name.' '.$row['teacher']->last_name }}</td>
                        <td>{{ to_persian_digits($row['sessions']) }}</td>
                        <td>{{ to_persian_digits(number_format($row['minutes'])) }}</td>
                        <td class="font-bold">{{ to_persian_digits(number_format($row['hours'], 2)) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center opacity-60">
                            در این بازه جلسهٔ برگزارشده‌ای نیست.
                        </td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="4">جمع کل ساعت</th>
                    <th class="text-lg">{{ to_persian_digits(number_format($this->totalHours, 2)) }}</th>
                </tr>
            </tfoot>
        </table>
    </x-card>
</div>
