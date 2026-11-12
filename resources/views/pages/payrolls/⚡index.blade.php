<?php

use App\Enums\PayrollStatus;
use App\Enums\Section;
use App\Models\Payroll;
use App\Models\Teacher;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Mary\Traits\Toast;

/**
 * حقوق و دستمزد اساتید (شماره 40).
 *
 * صفحهٔ Livewire است نه کنترلر، چون ورودیِ دوره باید از تقویم شمسی بیاید
 * (R2) و انتخابگر فقط با والد Livewire دوطرفه می‌شود. دوره یک «ماه» است نه
 * یک روز: کاربر روزی از ماه را انتخاب می‌کند و سال/ماهِ شمسیِ همان روز،
 * دورهٔ فیش را می‌سازد — به‌جای ساختنِ یک انتخابگرِ ماه که در پروژه نیست.
 *
 * خالصِ پرداختی را این صفحه نمی‌نویسد؛ مدل از ناخالص و کسورات می‌سازد.
 */
new #[Layout('layouts::academy')]
class extends Component
{
    use Toast;

    public string $title = 'حقوق و دستمزد اساتید';

    public string $payTeacher = '';

    /** میلادی ISO — picker همان را شمسی نشان می‌دهد؛ دوره از همین ساخته می‌شود. */
    public string $payDate = '';

    public string $payDays = '';

    public string $payOvertime = '0';

    public string $payInsurance = '0';

    public string $payTax = '0';

    public string $payGross = '';

    public function mount(): void
    {
        // معمولاً فیشِ همان ماهِ جاری صادر می‌شود؛ پیش‌فرض امروز است
        $this->payDate = now()->toDateString();
    }

    #[Computed]
    public function rows()
    {
        return Payroll::query()
            ->with('teacher')
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->orderByDesc('id')
            ->get();
    }

    /** گزینه‌های select استاد؛ در ویو فقط از $this صدا زده می‌شود. */
    public function teacherOptions(): array
    {
        return Teacher::query()
            ->orderBy('last_name')
            ->get()
            ->map(fn (Teacher $teacher) => ['id' => (string) $teacher->id, 'name' => $teacher->full_name])
            ->all();
    }

    /**
     * صدور فیش حقوقی.
     *
     * سه بررسیِ فراتر از قاعده‌های معمول این‌جا لازم است چون هیچ‌کدام با
     * «required/numeric» گرفته نمی‌شوند ولی هر سه فیشِ غلط می‌سازند:
     * روزِ بیش از ماه، کسوراتِ بیش از ناخالص، و فیشِ دوم برای یک ماه.
     */
    public function storePayroll(): void
    {
        // مجوز سمت سرور، مستقل از اینکه فرم برای چه کسی رندر شده
        abort_unless(auth()->user()?->canSee(Section::Payrolls), 403);

        if (! $this->normalizePayDate()) {
            $this->addError('payDate', 'تاریخ شمسی نامعتبر است. نمونه درست: ۱۴۰۴/۰۸/۲۱');

            return;
        }

        // «۱۰٬۰۰۰٬۰۰۰» باید پیش از اعتبارسنجی عدد شود، نه بعد از آن
        $this->normalizeNumbers();

        [$year, $month] = array_map('intval', explode('/', jalali_date($this->payDate)));

        $this->validate([
            'payTeacher' => [
                'required', 'integer', 'exists:teachers,id',
                // یک فیش برای هر استاد در هر ماه
                Rule::unique('payrolls', 'teacher_id')
                    ->where('period_year', $year)
                    ->where('period_month', $month),
            ],
            'payDate' => ['required', 'date_format:Y-m-d'],
            'payDays' => ['required', 'integer', 'between:0,66'],
            'payOvertime' => ['required', 'numeric', 'min:0', 'max:400'],
            'payInsurance' => ['required', 'integer', 'min:0'],
            'payTax' => ['required', 'integer', 'min:0'],
            'payGross' => ['required', 'integer', 'min:1'],
        ]);

        $monthDays = jalali_month_days($year, $month);

        if ((int) $this->payDays > $monthDays) {
            $this->addError('payDays', "در ماهِ انتخاب‌شده فقط {$monthDays} روز وجود دارد.");

            return;
        }

        if ((int) $this->payInsurance + (int) $this->payTax > (int) $this->payGross) {
            $this->addError('payInsurance', 'جمع کسورات از مبلغ ناخالص بیشتر است.');

            return;
        }

        Payroll::create([
            'teacher_id' => (int) $this->payTeacher,
            'period_year' => $year,
            'period_month' => $month,
            'days_worked' => (int) $this->payDays,
            'overtime_hours' => (float) $this->payOvertime,
            'insurance' => (int) $this->payInsurance,
            'tax' => (int) $this->payTax,
            'gross' => (int) $this->payGross,
            'status' => PayrollStatus::Pending,
        ]);

        $this->resetForm();
        $this->success('فیش حقوقی صادر شد.');
    }

    /** ثبت پرداخت؛ تاریخِ پرداخت میلادی ذخیره و شمسی نمایش داده می‌شود. */
    public function markPaid(int $id): void
    {
        abort_unless(auth()->user()?->canSee(Section::Payrolls), 403);

        $payroll = Payroll::findOrFail($id);

        if ($payroll->status === PayrollStatus::Paid) {
            return;
        }

        $payroll->update([
            'status' => PayrollStatus::Paid,
            'paid_at' => now()->toDateString(),
        ]);

        $this->success('پرداخت ثبت شد.');
    }

    public function resetForm(): void
    {
        $this->payTeacher = '';
        $this->payDate = now()->toDateString();
        $this->payDays = '';
        $this->payOvertime = '0';
        $this->payInsurance = '0';
        $this->payTax = '0';
        $this->payGross = '';
    }

    /** هر شکلِ غیرمیلادی را به ISO تبدیل می‌کند؛ نامعتبر false می‌دهد. */
    private function normalizePayDate(): bool
    {
        $value = trim($this->payDate);

        if ($value === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return true;
        }

        try {
            $converted = miladi_date($value);
        } catch (\InvalidArgumentException) {
            return false;
        }

        if ($converted === null) {
            return false;
        }

        $this->payDate = $converted;

        return true;
    }

    /** ورودیِ عددی کاربر را لاتین می‌کند؛ غیرعددی همان می‌ماند تا قاعده ردش کند. */
    private function normalizeNumbers(): void
    {
        foreach (['payDays', 'payInsurance', 'payTax', 'payGross'] as $field) {
            $number = parse_number($this->{$field});
            $this->{$field} = $number === null ? trim($this->{$field}) : (string) (int) round($number);
        }

        $hours = parse_number($this->payOvertime);
        $this->payOvertime = $hours === null ? trim($this->payOvertime) : (string) round($hours, 1);
    }
}
?>
<div>
    <h1 class="text-2xl font-bold mb-4">{{ $this->title }}</h1>

    <div class="bg-base-100 rounded-box shadow p-4 mb-6">
        <form wire:submit="storePayroll" class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end">
            <x-select
                label="استاد"
                wire:model="payTeacher"
                :options="$this->teacherOptions()"
                option-value="id"
                option-label="name"
                placeholder="انتخاب استاد"
                error="payTeacher" />

            {{-- تنها راه ورود تاریخ؛ دورهٔ فیش از همین روز ساخته می‌شود (R2) --}}
            <livewire:jalali-date-picker wire:model.live="payDate" label="دوره (روزی از ماه شمسی)" />

            <x-input
                label="روزهای کارکرد"
                type="text"
                wire:model="payDays"
                placeholder="۲۰"
                error="payDays" />

            <x-input
                label="اضافه‌کاری (ساعت)"
                type="text"
                wire:model="payOvertime"
                placeholder="۰"
                error="payOvertime" />

            <x-input
                label="کسورات بیمه (تومان)"
                type="text"
                wire:model="payInsurance"
                placeholder="۰"
                error="payInsurance" />

            <x-input
                label="کسورات مالیات (تومان)"
                type="text"
                wire:model="payTax"
                placeholder="۰"
                error="payTax" />

            <x-input
                label="مبلغ ناخالص (تومان)"
                type="text"
                wire:model="payGross"
                placeholder="۱۰٬۰۰۰٬۰۰۰"
                error="payGross" />

            <div class="pb-1">
                <x-button label="صدور فیش" type="submit" class="btn-primary" />
            </div>
        </form>

        <p class="text-xs opacity-60 mt-3">
            مبلغ خالص از ناخالص و کسورات محاسبه می‌شود و قابل تایپ نیست.
        </p>
    </div>

    <x-card title="فیش‌های صادرشده">
        <table class="table table-sm">
            <thead>
                <tr>
                    <th>#</th>
                    <th>استاد</th>
                    <th>دوره</th>
                    <th>روز کار</th>
                    <th>اضافه‌کاری</th>
                    <th>ناخالص</th>
                    <th>کسورات</th>
                    <th>خالص</th>
                    <th>وضعیت</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->rows as $index => $payroll)
                    <tr>
                        <td>{{ to_persian_digits($index + 1) }}</td>
                        <td>{{ $payroll->teacher?->full_name ?? '—' }}</td>
                        <td>{{ $payroll->periodLabel() }}</td>
                        <td>{{ to_persian_digits($payroll->days_worked) }}</td>
                        <td>{{ to_persian_digits($payroll->overtime_hours) }} ساعت</td>
                        <td>{{ $payroll->grossLabel() }}</td>
                        <td class="text-error">
                            {{ to_persian_digits(number_format($payroll->insurance + $payroll->tax)) }}
                        </td>
                        <td class="font-bold">{{ $payroll->netLabel() }}</td>
                        <td>
                            <span class="badge {{ $payroll->statusColor() }} badge-sm">
                                {{ $payroll->statusLabel() }}
                            </span>
                        </td>
                        <td>
                            <div class="flex gap-1 justify-end">
                                <a href="{{ route('payroll.print', $payroll) }}"
                                   target="_blank"
                                   class="btn btn-ghost btn-xs">چاپ فیش</a>
                                @if ($payroll->status->value === 'pending')
                                    <button type="button"
                                            class="btn btn-primary btn-xs"
                                            wire:click="markPaid({{ $payroll->id }})">پرداخت شد</button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="10" class="text-center opacity-60">
                            هنوز فیش حقوقی صادر نشده است.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </x-card>
</div>
