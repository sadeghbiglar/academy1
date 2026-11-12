<?php

use App\Models\Discipline;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * گزارش انضباط بازه‌ای (شماره 36).
 *
 * چرا Livewire و نه کنترلر + فرم GET: بازه باید از تقویم شمسی بیاید (R2) و
 * انتخابگر فقط با والد Livewire دوطرفه می‌شود. ورودیِ متنیِ خام، تاریخِ
 * میلادیِ ناخواسته را به سامانه راه می‌دهد.
 *
 * چرا فیلتر روی میلادی می‌نشیند: سرها در پایگاه میلادی‌اند. مقایسهٔ رشتهٔ
 * شمسی («۱۴۰۴/۰۸/۲۱») الفبایی است و آخر هر ماه بازه را می‌شکند؛ به همین
 * دلیل DB هم میلادی است نه شمسی.
 */
new #[Layout('layouts::academy')]
class extends Component
{
    public string $title = 'گزارش انضباط دانش‌آموزان';

    /** بازه به صورت میلادی ISO؛ picker همان را شمسی نشان می‌دهد. */
    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    public function mount(): void
    {
        // نشانیِ اشتراک‌گذاری‌شده ممکن است شمسی باشد («۱۴۰۴/۰۸/۲۱»)، چون همان
        // چیزی است که کاربر روی صفحه می‌بیند. هر دو شکل باید خوانده شوند.
        $this->from = $this->toIso($this->from);
        $this->to = $this->toIso($this->to);
    }

    #[Computed]
    public function rows()
    {
        return Discipline::query()
            ->with(['student', 'user'])
            ->inRange($this->from ?: null, $this->to ?: null)
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->get();
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

    <x-card title="رخدادهای انضباطی" class="mb-6">
        <table class="table table-sm">
            <thead>
                <tr>
                    <th>#</th>
                    <th>دانش‌آموز</th>
                    <th>نوع</th>
                    <th>شرح</th>
                    <th>تاریخ (شمسی)</th>
                    <th>ثبت‌کننده</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->rows as $index => $record)
                    <tr>
                        <td>{{ to_persian_digits($index + 1) }}</td>
                        <td>{{ $record->student?->full_name ?? '—' }}</td>
                        <td>
                            <span class="badge {{ $record->typeColor() }} badge-sm">
                                {{ $record->typeLabel() }}
                            </span>
                        </td>
                        <td>{{ $record->description }}</td>
                        <td>{{ to_persian_digits($record->occurredOnJalali()) }}</td>
                        <td>{{ $record->user?->display_name ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center opacity-60">
                            در این بازه رخدادی ثبت نشده است.
                        </td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="1">جمع رخدادها</th>
                    <th colspan="5" class="text-lg">
                        {{ to_persian_digits($this->rows->count()) }}
                    </th>
                </tr>
            </tfoot>
        </table>
    </x-card>
</div>
