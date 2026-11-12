<?php

use App\Enums\DisciplineType;
use App\Enums\EnrollmentStatus;
use App\Enums\PreviousStatus;
use App\Enums\Section;
use App\Models\Attendance;
use App\Models\Discipline;
use App\Models\PreviousEnrollment;
use App\Models\Student;
use App\Services\AttendanceThreshold;
use App\Services\WeightedAverage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Mary\Traits\Toast;

new #[Layout('layouts::academy')]
class extends Component {
    use Toast;

    public int $studentId;

    /**
     * فرم ثبت انضباط در همین پرونده.
     *
     * disciplineDate همیشه میلادی ISO است؛ شمسی‌دیدنش کار picker است، نه این
     * property — وگرنه ذخیره و نمایش دو تاریخ می‌شدند.
     */
    public string $disciplineType = DisciplineType::Warning->value;

    public string $disciplineNote = '';

    public string $disciplineDate = '';

    /**
     * فرم رونویس (سوابق تحصیلی قبلی).
     *
     * prevYear رشته است نه عدد: کاربر «۱۴۰۳» با ارقام فارسی تایپ می‌کند و
     * تبدیلِ آن (parse_integer) کار storePreviousEnrollment است، نه ویو.
     */
    public string $prevSchool = '';

    public string $prevYear = '';

    public string $prevGrade = '';

    public string $prevGrades = '';

    public string $prevStatus = '';

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
        // امروز پیش‌فرض می‌شود: رخداد انضباطی معمولاً همان روز ثبت می‌شود
        $this->disciplineDate = now()->toDateString();
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

    /** رخدادهای انضباطی پرونده، جدیدترین اول. */
    #[Computed]
    public function disciplines()
    {
        return $this->student->disciplines()
            ->with('user')
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->get();
    }

    /** گزینه‌های select نوع؛ در ویو فقط از $this صدا زده می‌شود. */
    public function disciplineOptions(): array
    {
        return DisciplineType::options();
    }

    /**
     * ثبت یک رخداد انضباطی در پرونده.
     *
     * تاریخِ شمسیِ تایپ‌شده باید پیش از اعتبارسنجی میلادی شود، وگرنه قاعدهٔ
     * date_format روی شکلِ نمایشی شکست می‌خورد یا بدتر: تاریخ شمسی در DB
     * می‌نشیند و بازهٔ گزارش به‌هم می‌ریزد. نامعتبر هم نباید بی‌صدا تبدیل شود —
     * خطای فارسی می‌گیرد و چیزی ذخیره نمی‌شود.
     */
    public function storeDiscipline(): void
    {
        // پنهان کردن فرم در ویو کافی نیست: بدون این، پذیرش با فراخوانی مستقیم
        // می‌توانست در پروندهٔ آموزشی رکورد بگذارد
        abort_unless($this->seesAcademic(), 403);

        if (! $this->normalizeDisciplineDate()) {
            $this->addError('disciplineDate', 'تاریخ شمسی نامعتبر است. نمونه درست: ۱۴۰۴/۰۸/۲۱');

            return;
        }

        $this->validate([
            'disciplineType' => ['required', Rule::enum(DisciplineType::class)],
            'disciplineNote' => ['required', 'string', 'max:1000'],
            'disciplineDate' => ['required', 'date_format:Y-m-d'],
        ]);

        Discipline::record(
            $this->student,
            DisciplineType::from($this->disciplineType),
            trim($this->disciplineNote),
            $this->disciplineDate,
        );

        $this->disciplineType = DisciplineType::Warning->value;
        $this->disciplineNote = '';
        $this->disciplineDate = now()->toDateString();

        $this->success('رخداد انضباطی ثبت شد.');
    }

    /** @return bool آیا تاریخ به میلادیِ درست رسید؟ خالی = درست، ولی بعداً required ردش می‌کند. */
    private function normalizeDisciplineDate(): bool
    {
        $value = trim($this->disciplineDate);

        // از قبل میلادی ISO است — همان را نگه می‌دار
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

        $this->disciplineDate = $converted;

        return true;
    }

    /** سوابق تحصیلی قبلی، جدیدترین سال اول. */
    #[Computed]
    public function previousEnrollments()
    {
        return $this->student->previousEnrollments()
            ->orderByDesc('academic_year')
            ->orderByDesc('id')
            ->get();
    }

    /** گزینه‌های select پایه و وضعیت؛ در ویو فقط از $this صدا زده می‌شوند. */
    public function prevGradeOptions(): array
    {
        return PreviousEnrollment::levels();
    }

    public function prevStatusOptions(): array
    {
        return PreviousStatus::options();
    }

    /**
     * ثبت رونویس (سوابق تحصیلی قبلی) در پرونده.
     *
     * «۱۴۰۳» باید پیش از اعتبارسنجی عدد شود: قاعدهٔ numeric روی ارقام فارسی
     * شکست می‌خورد در حالی که ورودی کاملاً درست است، و ذخیرهٔ خامش هم در ستون
     * integer ممکن نیست. ورودیِ غیرعددی هم باید همان بماند تا قاعده آن را رد
     * کند، نه اینکه بی‌صدا صفر یا خالی شود.
     */
    public function storePreviousEnrollment(): void
    {
        // همان قاعدهٔ انضباط: پنهان کردن فرم در ویو امنیت نیست
        abort_unless($this->seesAcademic(), 403);

        $year = parse_integer($this->prevYear);
        $this->prevYear = $year === null ? trim($this->prevYear) : (string) $year;

        $this->validate([
            'prevSchool' => ['required', 'string', 'max:150'],
            'prevYear' => ['required', 'integer', 'between:1300,1500'],
            'prevGrade' => ['required', 'string', Rule::in(array_column(PreviousEnrollment::levels(), 'id'))],
            'prevGrades' => ['nullable', 'string', 'max:2000'],
            'prevStatus' => ['required', Rule::enum(PreviousStatus::class)],
        ]);

        PreviousEnrollment::record(
            $this->student,
            trim($this->prevSchool),
            (int) $this->prevYear,
            $this->prevGrade,
            trim($this->prevGrades) ?: null,
            PreviousStatus::from($this->prevStatus),
        );

        $this->prevSchool = '';
        $this->prevYear = '';
        $this->prevGrade = '';
        $this->prevGrades = '';
        $this->prevStatus = '';

        $this->success('سوابق تحصیلی قبلی ثبت شد.');
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

            {{-- سوابق تحصیلی قبلی (رونویس): همان قاعدهٔ انضباط — فقط نقش‌های
                 آموزشی، با بسته بودن ثبت سمت سرور برای بقیه. --}}
            @if ($this->seesAcademic())
                <x-card title="سوابق تحصیلی قبلی" class="mt-4">
                    <form wire:submit="storePreviousEnrollment" class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3 items-end">
                        <x-input
                            label="مدرسه"
                            type="text"
                            wire:model="prevSchool"
                            placeholder="نام مدرسهٔ قبلی"
                            maxlength="150"
                            error="prevSchool" />

                        {{-- شمسی و ارقام فارسی؛ تبدیل به عدد کار storePreviousEnrollment است --}}
                        <x-input
                            label="سال تحصیلی (شمسی)"
                            type="text"
                            wire:model="prevYear"
                            placeholder="۱۴۰۳"
                            error="prevYear" />

                        <x-select
                            label="پایه"
                            wire:model="prevGrade"
                            :options="$this->prevGradeOptions()"
                            option-value="id"
                            option-label="name"
                            placeholder="انتخاب پایه"
                            error="prevGrade" />

                        <x-textarea
                            label="نمرات قبلی"
                            wire:model="prevGrades"
                            placeholder="مثلاً ریاضی ۱۸، علوم ۱۷"
                            error="prevGrades" />

                        <x-select
                            label="وضعیت فارغ‌التحصیلی"
                            wire:model="prevStatus"
                            :options="$this->prevStatusOptions()"
                            option-value="id"
                            option-label="name"
                            placeholder="انتخاب وضعیت"
                            error="prevStatus" />

                        <div class="pb-1">
                            <x-button label="ثبت رونویس" type="submit" class="btn-primary" />
                        </div>
                    </form>

                    <div class="divider"></div>

                    @forelse ($this->previousEnrollments as $entry)
                        <div class="flex flex-wrap items-center justify-between gap-2 py-2 border-b border-base-200 last:border-0 text-sm">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="badge {{ $entry->statusColor() }} badge-sm">{{ $entry->statusLabel() }}</span>
                                <span class="font-medium">{{ $entry->school }}</span>
                                <span class="text-xs opacity-70">
                                    سال {{ $entry->yearLabel() }} — {{ $entry->gradeLabel() }}
                                </span>
                            </div>
                            <span class="text-xs opacity-70">
                                {{ $entry->previous_grades ?: 'بدون کارنامهٔ قبلی' }}
                            </span>
                        </div>
                    @empty
                        <p class="text-sm opacity-60">سوابق تحصیلی قبلی ثبت نشده است.</p>
                    @endforelse
                </x-card>
            @endif

            {{-- انضباط و رفتار: فقط نقش‌های آموزشی. امور مالی و پذیرش پرونده را
                 باز می‌کنند ولی رفتار دانش‌آموز کار آن‌ها نیست، پس نه فرم دیده
                 می‌شود نه فهرست — و ثبت سمت سرور هم بسته است. --}}
            @if ($this->seesAcademic())
                <x-card title="انضباط و رفتار" class="mt-4">
                    <form wire:submit="storeDiscipline" class="grid sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end">
                        <x-select
                            label="نوع"
                            wire:model="disciplineType"
                            :options="$this->disciplineOptions()"
                            option-value="id"
                            option-label="name"
                            placeholder="انتخاب نوع"
                            error="disciplineType" />

                        <x-input
                            label="شرح"
                            type="text"
                            wire:model="disciplineNote"
                            placeholder="چه اتفاقی افتاد؟"
                            maxlength="1000"
                            error="disciplineNote" />

                        {{-- تنها راه ورود تاریخ؛ کاربر فقط شمسی می‌بیند (R2) --}}
                        <livewire:jalali-date-picker wire:model.live="disciplineDate" label="تاریخ رخداد (شمسی)" />

                        <div class="pb-1">
                            <x-button label="ثبت رفتار" type="submit" class="btn-primary" />
                        </div>
                    </form>

                    <div class="divider"></div>

                    @forelse ($this->disciplines as $record)
                        <div class="flex flex-wrap items-center justify-between gap-2 py-2 border-b border-base-200 last:border-0 text-sm">
                            <div class="flex items-center gap-2">
                                <span class="badge {{ $record->typeColor() }} badge-sm">{{ $record->typeLabel() }}</span>
                                <span>{{ $record->description }}</span>
                            </div>
                            <div class="flex items-center gap-3 text-xs opacity-70">
                                <span>{{ to_persian_digits($record->occurredOnJalali()) }}</span>
                                <span>ثبت‌کننده: {{ $record->user?->display_name ?? '—' }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm opacity-60">هنوز رخدادی ثبت نشده است.</p>
                    @endforelse
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
