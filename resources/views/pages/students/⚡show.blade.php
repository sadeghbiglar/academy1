<?php

use App\Enums\EnrollmentStatus;
use App\Enums\Section;
use App\Models\Attendance;
use App\Models\Student;
use App\Services\AttendanceThreshold;
use App\Services\WeightedAverage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Mary\Traits\Toast;

new #[Layout('layouts::academy')]
class extends Component {
    use Toast;

    public int $studentId;

    /**
     * ستون‌های جدول فاکتورها به‌صورت key/label.
     *
     * رشتهٔ ساده جواب نمی‌دهد: جدول روی $header['key'] و $header['label'] کار
     * می‌کند و با آرایهٔ رشته‌ای روی همان کلیدها خطای offset می‌دهد.
     */
    public array $invoiceHeaders = [
        ['key' => 'number', 'label' => 'شماره'],
        ['key' => 'course_id', 'label' => 'دوره'],
        ['key' => 'amount', 'label' => 'مبلغ'],
        ['key' => 'discount_amount', 'label' => 'تخفیف'],
        ['key' => 'remaining', 'label' => 'مانده'],
        ['key' => 'due_at', 'label' => 'موعد'],
        ['key' => 'status', 'label' => 'وضعیت'],
    ];

    public function mount(int $student): void
    {
        $this->studentId = $student;
    }

    #[Computed]
    public function student(): Student
    {
        return Student::with(['profile', 'guardians', 'documents'])
            ->findOrFail($this->studentId);
    }

    /**
     * آیا این نقش اجازهٔ دیدن بخش آموزشی را دارد؟
     *
     * امور مالی صفحهٔ دانش‌آموز را می‌بیند ولی فقط برای بدهی؛ نمره و حضور،
     * کار او نیست. این تصمیم در مدل Role است، نه این‌جا.
     */
    #[Computed]
    public function seesAcademic(): bool
    {
        return auth()->user()->canSee(Section::Grades);
    }

    #[Computed]
    public function seesMoney(): bool
    {
        return auth()->user()->canSee(Section::Finance);
    }

    /**
     * هر دوره با وضعیت ثبت‌نام، درصد حضور و نمرهٔ نهایی.
     *
     * رزروهای لغوشده و ردشده نمایش داده نمی‌شوند: یک ردِ ثبت‌نام در کنار
     * دوره‌های جاری، وضعیت واقعی دانش‌آموز را مبهم می‌کند.
     */
    #[Computed]
    public function rows(): array
    {
        $threshold = new AttendanceThreshold;
        $average = new WeightedAverage;

        return $this->student->enrollments()
            ->whereIn('status', [
                EnrollmentStatus::Confirmed->value,
                EnrollmentStatus::Pending->value,
                EnrollmentStatus::Waitlisted->value,
            ])
            ->with('course.subject')
            ->get()
            ->map(function ($enrollment) use ($threshold, $average) {
                $course = $enrollment->course;

                $rate = Attendance::attendanceRateFor($this->student, $course);

                return [
                    'enrollment' => $enrollment,
                    'course' => $course,
                    'rate' => $rate,
                    'level' => $threshold->level($rate),
                    'score' => $this->seesAcademic ? $average->finalScore($this->student, $course) : null,
                ];
            })
            ->all();
    }

    /** جمع نمره‌های هر دوره برای ریزنمرات. */
    public function gradesFor(int $courseId)
    {
        return $this->student->grades()
            ->where('course_id', $courseId)
            ->with('course')
            ->orderBy('type')
            ->orderBy('title')
            ->get();
    }

    public function render()
    {
        return <<<'HTML'
        <div>
            @php
                $student = $this->student();
                $profile = $student->profile;
            @endphp

            <div class="flex items-center gap-2 mb-4">
                <a href="{{ route('students.index') }}" class="btn btn-sm btn-ghost">بازگشت به فهرست</a>
                <span class="text-sm opacity-60">پروندهٔ دانش‌آموز</span>
            </div>

            {{-- هویت و وضعیت --}}
            <x-card class="mb-4">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-bold">{{ $student->full_name }}</h2>
                        <p class="text-sm opacity-70 mt-1">
                            کد دانش‌آموزی: {{ to_persian_digits($profile?->student_code ?? '—') }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        @if ($profile)
                            <span class="badge {{ $profile->statusColor() }}">{{ $profile->statusLabel() }}</span>
                        @else
                            <span class="badge badge-ghost">بدون پروندهٔ آموزشی</span>
                        @endif
                    </div>
                </div>

                <div class="divider"></div>

                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                    <div>
                        <div class="opacity-60">کد ملی</div>
                        <div class="font-medium">{{ to_persian_digits($student->national_code ?? '—') }}</div>
                    </div>
                    <div>
                        <div class="opacity-60">تاریخ تولد</div>
                        <div class="font-medium">{{ jalali_date($student->birth_date) ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="opacity-60">جنسیت</div>
                        <div class="font-medium">{{ $student->genderLabel() }}</div>
                    </div>
                    <div>
                        <div class="opacity-60">شمارهٔ تماس</div>
                        <div class="font-medium">{{ to_persian_digits($student->mobile ?? '—') }}</div>
                    </div>
                </div>
            </x-card>

            <div class="grid lg:grid-cols-3 gap-4">
                {{-- سرپرستان --}}
                <x-card title="سرپرستان" class="lg:col-span-1">
                    @forelse ($student->guardians as $guardian)
                        <div class="flex items-center justify-between py-2 border-b border-base-200 last:border-0">
                            <div>
                                <div class="font-medium">{{ $guardian->full_name }}</div>
                                <div class="text-xs opacity-60">
                                    {{ $guardian->relationLabel() }}
                                    @if ($guardian->mobile)
                                        — {{ to_persian_digits($guardian->mobile) }}
                                    @endif
                                </div>
                            </div>
                            @if ($guardian->is_primary)
                                <span class="badge badge-primary badge-sm">اصلی</span>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm opacity-60">سرپرستی ثبت نشده است.</p>
                    @endforelse
                </x-card>

                {{-- آدرس --}}
                <x-card title="نشانی" class="lg:col-span-2">
                    <p class="text-sm leading-7">
                        {{ $student->address ?: '—' }}
                    </p>
                    <p class="text-xs opacity-60 mt-2">
                        {{ $student->province ? $student->province.'، ' : '' }}{{ $student->city ?? '' }}
                        @if ($student->postal_code)
                            — کد پستی {{ to_persian_digits($student->postal_code) }}
                        @endif
                    </p>
                </x-card>
            </div>

            {{-- دوره‌ها و کارنامه: فقط نقش‌های آموزشی --}}
            @if ($this->seesAcademic())
            <x-card title="دوره‌ها و کارنامه" class="mt-4">
            @forelse ($this->rows() as $row)
                    <div class="border-b border-base-200 last:border-0 py-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <span class="font-medium">{{ $row['course']->title }}</span>
                                <span class="text-xs opacity-60">— {{ $row['course']->subject?->name }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="badge badge-sm">{{ $row['enrollment']->statusLabel() }}</span>
                                @if ($row['level'] === 'critical')
                                    <span class="badge badge-error badge-sm">غیبت بیش از حد</span>
                                @elseif ($row['level'] === 'warning')
                                    <span class="badge badge-warning badge-sm">حضور زیر آستانه</span>
                                @endif
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-6 mt-2 text-xs opacity-70">
                            @if ($this->seesAcademic())
                                <span>
                                    حضور:
                                    {{ $row['rate'] === null ? '—' : to_persian_digits(number_format($row['rate'], 1)).'٪' }}
                                </span>
                                <span>
                                    نمرهٔ نهایی:
                                    {{ $row['score'] === null ? '—' : to_persian_digits(number_format($row['score'], 2)) }}
                                </span>
                            @endif
                            @if ($this->seesMoney())
                                <span>
                                    شهریهٔ این دوره:
                                    {{ to_persian_digits(number_format($row['enrollment']->finalFee() ?? 0)) }} تومان
                                </span>
                            @endif
                        </div>

                        @if ($this->seesAcademic() && $this->gradesFor($row['course']->id)->isNotEmpty())
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach ($this->gradesFor($row['course']->id) as $grade)
                                    <span class="badge badge-ghost badge-sm" title="{{ $grade->title }}">
                                        {{ $grade->typeLabel() }}: {{ $grade->scoreLabel() }}
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @empty
                <p class="text-sm opacity-60">این دانش‌آموز در هیچ دوره‌ای ثبت‌نام نکرده است.</p>
            @endforelse
            </x-card>
            @endif

            @if ($this->seesMoney())
                {{-- مالی --}}
                <x-card title="وضعیت مالی" class="mt-4">
                    <div class="stats stats-vertical sm:stats-horizontal bg-base-200">
                        <div class="stat">
                            <div class="stat-title">جمع فاکتورها</div>
                            <div class="stat-value text-lg">
                                {{ to_persian_digits(number_format($student->totalPayable())) }}
                            </div>
                            <div class="stat-desc">تومان</div>
                        </div>
                        <div class="stat">
                            <div class="stat-title">پرداخت‌شده</div>
                            <div class="stat-value text-lg">
                                {{ to_persian_digits(number_format($student->totalPaid())) }}
                            </div>
                            <div class="stat-desc">تومان</div>
                        </div>
                        <div class="stat">
                            <div class="stat-title">مانده بدهی</div>
                            <div class="stat-value text-lg {{ $student->outstanding() > 0 ? 'text-error' : '' }}">
                                {{ to_persian_digits(number_format($student->outstanding())) }}
                            </div>
                            <div class="stat-desc">
                                @if ($student->credit() > 0)
                                    بیش از حد پرداختی: {{ to_persian_digits(number_format($student->credit())) }} تومان
                                @else
                                    تومان
                                @endif
                            </div>
                        </div>
                    </div>

                    @if ($student->invoices->isNotEmpty())
                        <div class="divider"></div>

                        <x-table :headers="$invoiceHeaders" :rows="$student->invoices" class="text-sm">
                            @scope('cell_course_id', $invoice)
                                {{ $invoice->course?->title ?? 'بدون دوره' }}
                            @endscope
                            @scope('cell_amount', $invoice)
                                {{ to_persian_digits(number_format($invoice->amount)) }}
                            @endscope
                            @scope('cell_discount_amount', $invoice)
                                {{ to_persian_digits(number_format($invoice->discount_amount)) }}
                            @endscope
                            @scope('cell_remaining', $invoice)
                                <span class="{{ $invoice->remaining() > 0 ? 'text-error' : '' }}">
                                    {{ to_persian_digits(number_format($invoice->remaining())) }}
                                </span>
                            @endscope
                            @scope('cell_due_at', $invoice)
                                {{ $invoice->dueAtJalali() ?? '—' }}
                            @endscope
                            @scope('cell_status', $invoice)
                                <span class="badge {{ $invoice->status->color() }} badge-sm">{{ $invoice->statusLabel() }}</span>
                            @endscope
                        </x-table>
                    @endif
                </x-card>
            @endif

            {{-- اسناد --}}
            <x-card title="اسناد پذیرش" class="mt-4 mb-8">
                @forelse ($student->documents as $document)
                    <div class="flex items-center justify-between py-2 border-b border-base-200 last:border-0 text-sm">
                        <span>{{ $document->typeLabel() }} — {{ $document->title }}</span>
                        <span class="opacity-60">{{ jalali_date($document->document_date) ?? '—' }}</span>
                    </div>
                @empty
                    <p class="text-sm opacity-60">سندی بارگذاری نشده است.</p>
                @endforelse
            </x-card>
        </div>
        HTML;
    }
};
?>
