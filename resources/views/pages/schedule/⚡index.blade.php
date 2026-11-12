<?php

use App\Enums\Role;
use App\Services\CalendarBuilder;
use App\Services\ConflictDetector;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * تقویم ماهانهٔ شمسی کلاس‌ها با تعارض‌یاب (شماره 47).
 *
 * قاعدهٔ R6: تداخل استاد/کلاس/دانش‌آموز خطای عملیاتی است، پس تقویم فقط
 * «کجا کلاس داریم» نیست — همان‌جا باید تعارض را هم نشان دهد، چون مدیر وقتی
 * برنامه را می‌بیند باید اشتباه را ببیند نه اینکه بعداً گزارش بگیرد.
 *
 * تاریخ‌ها همه شمسی‌اند (R1) و تنها ورودیِ تاریخِ این صفحه از
 * jalali-date-picker می‌آید (R2) — خودِ صفحه هیچ متن تاریخی نمی‌پذیرد.
 */
new #[Layout('layouts::academy')]
class extends Component
{
    public string $title = 'تقویم کلاس‌ها';

    /** ماهِ در حال نمایش، شمسی و از URL تا لینک قابل اشتراک باشد. */
    #[Url]
    public int $year = 0;

    #[Url]
    public int $month = 0;

    /** تاریخ میلادیِ انتخاب‌شده از picker؛ صفحه فقط ماهش را برمی‌دارد. */
    #[Url]
    public string $jump = '';

    public function mount(): void
    {
        $now = $this->currentJalali();

        $this->year = $this->year > 0 ? $this->year : $now[0];
        $this->month = $this->month >= 1 && $this->month <= 12 ? $this->month : $now[1];
    }

    /**
     * پرش به تاریخِ انتخاب‌شده در تقویم شمسی.
     *
     * ورودی نامعتبر صفحه را جابه‌جا نمی‌کند: کاربر خطای picker را می‌بیند و
     * ماهی که هست باقی می‌ماند، نه اینکه ناگهان به ماه دیگری بپرد.
     */
    public function updatedJump(string $value): void
    {
        if (trim($value) === '') {
            return;
        }

        try {
            $shamsi = jalali_date(miladi_date($value));
        } catch (\InvalidArgumentException) {
            return;
        }

        if ($shamsi !== null && preg_match('#^(\d{4})/(\d{2})/(\d{2})$#', $shamsi, $parts)) {
            $this->year = (int) $parts[1];
            $this->month = (int) $parts[2];
        }
    }

    public function previousMonth(): void
    {
        $this->shiftMonth(-1);
    }

    public function nextMonth(): void
    {
        $this->shiftMonth(1);
    }

    #[Computed]
    public function monthLabel(): string
    {
        return jalali_month_name($this->month).' '.to_persian_digits($this->year);
    }

    /** شبکهٔ هفتگی ماه؛ خانه‌ها شنبه تا جمعه‌اند. */
    #[Computed]
    public function grid(): array
    {
        return app(CalendarBuilder::class)->grid($this->year, $this->month, $this->teacherId());
    }

    /** تعارض‌های همین ماه، با پیام فارسی. */
    #[Computed]
    public function conflicts()
    {
        return app(ConflictDetector::class)->forJalaliMonth($this->year, $this->month, $this->teacherId());
    }

    /**
     * خانه‌های درگیر تعارض: session_id => تاریخ میلادی => true.
     *
     * @return array<int, array<string, bool>>
     */
    #[Computed]
    public function conflicted(): array
    {
        $map = [];

        foreach ($this->conflicts as $conflict) {
            foreach ($conflict['dates'] as $date) {
                $map[$conflict['left']->session->id][$date] = true;
                $map[$conflict['right']->session->id][$date] = true;
            }
        }

        return $map;
    }

    /** رنگِ پایدار هر دوره تا کاربر دوره را در ماه‌های مختلف بشناسد. */
    public function colorFor(int $courseId): string
    {
        $palette = ['badge-primary', 'badge-secondary', 'badge-accent', 'badge-info', 'badge-success', 'badge-warning'];

        return $palette[$courseId % count($palette)];
    }

    /** دامنهٔ دید: دبیر فقط کلاس‌های خودش را می‌بیند، مثل صفحهٔ حضور و غیاب. */
    private function teacherId(): ?int
    {
        $user = auth()->user();

        if ($user?->role !== Role::Teacher) {
            return null;
        }

        return $user->teacher?->id ?? 0;
    }

    /** @return array{0: int, 1: int} */
    private function currentJalali(): array
    {
        $shamsi = jalali_date(now()) ?? '1404/01/01';
        preg_match('#^(\d{4})/(\d{2})/(\d{2})$#', $shamsi, $parts);

        return [(int) $parts[1], (int) $parts[2]];
    }

    private function shiftMonth(int $delta): void
    {
        $month = $this->month + $delta;
        $year = $this->year;

        if ($month < 1) {
            $month = 12;
            $year--;
        } elseif ($month > 12) {
            $month = 1;
            $year++;
        }

        $this->year = $year;
        $this->month = $month;
    }
}
?>
<div>
    {{-- سربرگ: جابه‌جایی ماه با دکمه، پرش به روز با تقویم شمسی (R2) --}}
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <h1 class="text-2xl font-bold">تقویم کلاس‌ها</h1>

        <div class="flex items-center gap-2">
            <x-button label="ماه قبل" class="btn-sm btn-ghost" wire:click="previousMonth" />

            <span class="font-bold text-lg min-w-36 text-center" data-testid="month-label">
                {{ $this->monthLabel }}
            </span>

            <x-button label="ماه بعد" class="btn-sm btn-ghost" wire:click="nextMonth" />
        </div>
    </div>

    <div class="bg-base-100 rounded-box shadow p-4 mb-6 max-w-sm">
        <livewire:jalali-date-picker wire:model.live="jump" label="پرش به تاریخ (شمسی)" />
    </div>

    {{-- تعارض همین‌جا دیده می‌شود، نه در گزارشی که بعداً کسی باز کند (R6) --}}
    <x-card title="تعارض‌های این ماه" class="mb-6">
        @if ($this->conflicts->isEmpty())
            <p class="text-sm opacity-70">تعارضی در این ماه نیست</p>
        @else
            <ul class="space-y-2">
                @foreach ($this->conflicts as $conflict)
                    <li class="flex items-start gap-2 border border-error/40 bg-error/5 rounded-lg p-2">
                        <span class="badge badge-error badge-sm shrink-0 mt-0.5">
                            {{ $conflict['label'] }}
                        </span>
                        <span class="text-sm">{{ $conflict['message'] }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>

    <div class="bg-base-100 rounded-box shadow p-4 overflow-x-auto">
        <div class="grid grid-cols-7 gap-1 min-w-[720px]">
            @foreach (['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'] as $dayName)
                <div class="text-center text-xs font-bold opacity-60 py-1">{{ $dayName }}</div>
            @endforeach

            @foreach ($this->grid as $week)
                @foreach ($week as $cell)
                    @if ($cell === null)
                        <div class="min-h-28 rounded-lg bg-base-200/50"></div>
                    @else
                        <div @class([
                            'min-h-28 rounded-lg border p-1',
                            'border-primary bg-primary/5' => $cell['is_today'],
                            'border-base-200' => ! $cell['is_today'],
                        ])>
                            <div class="flex items-center justify-between mb-1">
                                <span @class([
                                    'text-sm font-bold',
                                    'text-primary' => $cell['is_today'],
                                ])>{{ to_persian_digits($cell['day']) }}</span>

                                @if ($cell['is_today'])
                                    <span class="badge badge-primary badge-xs">امروز</span>
                                @endif
                            </div>

                            @foreach ($cell['occurrences'] as $occurrence)
                                @php
                                    $hasConflict = isset($this->conflicted[$occurrence->session->id][$cell['date']]);
                                @endphp

                                <div @class([
                                        'rounded px-1 py-0.5 mb-1 border-r-4',
                                        'bg-error/10 border-error' => $hasConflict,
                                        'bg-base-200 border-base-300' => ! $hasConflict,
                                    ])
                                    wire:key="occ-{{ $occurrence->session->id }}-{{ $cell['date'] }}"
                                    title="{{ $occurrence->title() }}">
                                    <div class="text-[11px] font-bold truncate">
                                        <span @class(['badge badge-xs ml-1', $this->colorFor($occurrence->course->id)])></span>
                                        {{ $occurrence->title() }}
                                    </div>

                                    <div class="text-[10px] opacity-75">
                                        {{ $occurrence->timeLabel() }}@if ($occurrence->room()) · {{ $occurrence->room() }}@endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                @endforeach
            @endforeach
        </div>
    </div>
</div>
