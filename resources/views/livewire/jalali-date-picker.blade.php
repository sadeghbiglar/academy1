<div class="jalali-date-picker relative" wire:key="jdp-{{ $this->id() }}">

    {{-- ورودی متنی: کاربر فقط شمسی می‌بیند و شمسی تایپ می‌کند --}}
    <div class="flex gap-2 items-start">
        <div class="grow">
            @if ($label)
                <label class="block text-sm font-bold mb-1">{{ $label }}</label>
            @endif

            <input
                type="text"
                wire:model.live.debounce.400ms="display"
                placeholder="۱۴۰۳/۰۵/۱۲"
                autocomplete="off"
                class="w-full border rounded-lg px-3 py-2 bg-white dark:bg-base-300
                       {{ $error ? 'border-red-500' : 'border-base-300' }}"
                @keydown.escape="toggleCalendar"
            >

            @if ($error)
                <p class="text-red-600 text-sm mt-1" data-testid="jdp-error">{{ $error }}</p>
            @endif
        </div>

        <button
            type="button"
            wire:click="toggleCalendar"
            class="border rounded-lg px-3 py-2 bg-base-100 hover:bg-base-200 mt-6"
            aria-label="نمایش تقویم"
        >
            📅
        </button>
    </div>

    {{-- مقدار میلادی که واقعاً ذخیره می‌شود؛ برای بازرسی و تست --}}
    <input type="hidden" wire:model="value" data-testid="jdp-value">

    @if ($calendarOpen)
        <div class="absolute z-50 mt-2 bg-white dark:bg-base-300 border border-base-300 rounded-xl shadow-xl p-3 w-72">

            <div class="flex items-center justify-between mb-2">
                <button type="button" wire:click="previousMonth" class="px-2 py-1 hover:bg-base-200 rounded">‹</button>
                <span class="font-bold" data-testid="jdp-title">
                    {{ jalali_month_name($calMonth) }} {{ to_persian_digits($calYear) }}
                </span>
                <button type="button" wire:click="nextMonth" class="px-2 py-1 hover:bg-base-200 rounded">›</button>
            </div>

            <div class="grid grid-cols-7 text-center text-xs opacity-70 mb-1">
                @foreach (['ش', 'ی', 'د', 'س', 'چ', 'پ', 'ج'] as $weekday)
                    <span>{{ $weekday }}</span>
                @endforeach
            </div>

            <div class="grid grid-cols-7 gap-0.5 text-center text-sm">
                @foreach ($this->calendar as $day)
                    @if ($day === null)
                        <span></span>
                    @else
                        @php
                            $isSelected = $this->selectedParts === to_persian_digits(sprintf('%04d/%02d/%02d', $calYear, $calMonth, $day));
                        @endphp
                        <button
                            type="button"
                            wire:click="selectDate({{ $calYear }}, {{ $calMonth }}, {{ $day }})"
                            @class([
                                'py-1 rounded hover:bg-primary hover:text-white',
                                'bg-primary text-white font-bold' => $isSelected,
                            ])
                            data-testid="jdp-day-{{ $day }}"
                        >{{ to_persian_digits($day) }}</button>
                    @endif
                @endforeach
            </div>

            <div class="flex justify-between mt-2 pt-2 border-t border-base-300">
                <button type="button" wire:click="today" class="text-sm hover:underline">امروز</button>
                <button type="button" wire:click="$set('display', ''); $set('value', '')" class="text-sm hover:underline opacity-70">
                    پاک کردن
                </button>
            </div>
        </div>
    @endif
</div>
