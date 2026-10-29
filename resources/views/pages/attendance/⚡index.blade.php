<?php

use App\Enums\AttendanceStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\Role;
use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\Student;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Mary\Traits\Toast;

/**
 * ورقهٔ حضور روزانهٔ یک جلسه (شماره 41).
 *
 * فرم گروهی است: یک جلسه را انتخاب می‌کنی، همهٔ ثبت‌نام‌های قطعی همان دوره
 * با یک وضعیت هر کدام، و «همه حاضر» برای حالت رایج. اجرا چند بار ممکن است و
 * هر بار باید همان ردیف‌ها را به‌روزرسانی کند، نه ردیف تازه بسازد.
 */
new #[Layout('layouts::academy')]
class extends Component
{
    use Toast;

    public string $title = 'حضور و غیاب روزانه';

    public ?int $course_id = null;

    public ?int $session_id = null;

    /** شناسهٔ دانش‌آموز => یکی از مقادیر AttendanceStatus. */
    public array $statuses = [];

    public function updatedCourseId(): void
    {
        // دوره عوض شد: جلسهٔ قبلی و وضعیت‌هایش به دورهٔ دیگر نمی‌روند،
        // وگرنه حضورِ دانش‌آموزی در جلسهٔ دوره‌ای ثبت می‌شود که در آن نیست
        $this->session_id = null;
        $this->statuses = [];
    }

    public function updatedSessionId(): void
    {
        $this->loadExisting();
    }

    /** «همه حاضر» — پرکردن فرم بدون تک‌تک زدن‌ها. */
    public function markAllPresent(): void
    {
        foreach ($this->students() as $student) {
            $this->statuses[$student->id] = AttendanceStatus::Present->value;
        }
    }

    public function save(): void
    {
        $this->validate([
            'session_id' => ['required', 'integer', 'exists:class_sessions,id'],
            'statuses' => ['array'],
            'statuses.*' => ['string'],
        ]);

        // updateOrCreate روی یکتایی (جلسه، دانش‌آموز): زدن دوبارهٔ دکمهٔ
        // «همه حاضر» و ذخیره نباید ردیف دوم بسازد، چون در پایگاه داده یکتایی
        // روی همین جفت هست و درج دوم با خطای ساختاری می‌شکند
        foreach ($this->students() as $student) {
            $status = $this->statuses[$student->id] ?? AttendanceStatus::Present->value;

            Attendance::query()->updateOrCreate(
                ['class_session_id' => $this->session_id, 'student_id' => $student->id],
                ['status' => $status],
            );
        }

        $this->success('حضور و غیاب ثبت شد.');
    }

    /** @return \Illuminate\Support\Collection<int, Student> */
    public function students()
    {
        if (! $this->course_id) {
            return collect();
        }

        // فقط ثبت‌نام قطعی: دانش‌آموز در انتظار هنوز جایی در کلاس نگرفته و
        // حضورِ او درصد حضور بقیه را خراب می‌کند
        return Student::query()
            ->whereHas('enrollments', fn ($query) => $query
                ->where('course_id', $this->course_id)
                ->where('status', EnrollmentStatus::Confirmed->value))
            ->orderBy('id')
            ->get();
    }

    #[\Livewire\Attributes\Computed]
    public function courses()
    {
        $query = Course::query()->orderBy('id');

        $user = auth()->user();

        if ($user->role === Role::Teacher) {
            // دبیر فقط کلاس‌های خودش را می‌بیند، همان قاعدهٔ صفحهٔ دوره‌ها.
            // اگر پروندهٔ استاد نداشته باشد هیچ دوره‌ای نمی‌بیند: «همهٔ دوره‌ها»
            // برای دبیر نشت اطلاعات است نه سهولت کار
            $query->where('teacher_id', $user->teacher?->id ?? 0);
        }

        return $query->get();
    }

    #[\Livewire\Attributes\Computed]
    public function sessions()
    {
        if (! $this->course_id) {
            return collect();
        }

        // فقط جلسهٔ تاریخ‌دار: حضور روی یک روز مشخص ثبت می‌شود و الگوی هفتگی
        // بدون تاریخ، روزی برای ثبت ندارد
        return ClassSession::query()
            ->where('course_id', $this->course_id)
            ->whereNotNull('session_date')
            ->orderBy('session_date')
            ->get();
    }

    #[\Livewire\Attributes\Computed]
    public function statusOptions()
    {
        return AttendanceStatus::options();
    }

    /** وضعیت‌های موجودِ این جلسه را روی فرم می‌نشاند، تا ویرایش از صفر شروع نشود. */
    private function loadExisting(): void
    {
        $this->statuses = [];

        if (! $this->session_id) {
            return;
        }

        foreach ($this->students() as $student) {
            $existing = Attendance::query()
                ->where('class_session_id', $this->session_id)
                ->where('student_id', $student->id)
                ->first();

            $this->statuses[$student->id] = $existing?->status->value
                ?? AttendanceStatus::Present->value;
        }
    }
}
?>
<div>
    <h1 class="text-2xl font-bold mb-4">{{ $this->title }}</h1>

    <div class="bg-base-100 rounded-box shadow p-4 mb-6 grid sm:grid-cols-2 gap-3 items-end">
        <x-select label="دوره" wire:model.live="course_id"
                  :options="$this->courses" option-value="id" option-label="title"
                  placeholder="انتخاب دوره" />

        <x-select label="جلسه" wire:model.live="session_id"
                  :options="$this->sessions->map(fn ($s) => [
                      'id' => $s->id,
                      'name' => $s->sessionDateJalali().' — '.$s->timeRangeLabel(),
                  ])->values()"
                  option-value="id" option-label="name"
                  placeholder="انتخاب جلسه" :disabled="! $course_id" />
    </div>

    @if ($session_id)
        <x-card title="دانش‌آموزان این جلسه">
            <div class="mb-4">
                <x-button label="همه حاضر" class="btn-sm btn-outline" wire:click="markAllPresent" />
            </div>

            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>دانش‌آموز</th>
                        <th>وضعیت</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->students() as $index => $student)
                        <tr>
                            <td>{{ to_persian_digits($index + 1) }}</td>
                            <td>{{ $student->full_name }}</td>
                            <td>
                                <x-select :options="$this->statusOptions" wire:model="statuses.{{ $student->id }}"
                                          option-value="id" option-label="name" />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-center opacity-60">
                                ثبت‌نام قطعی‌ای در این دوره نیست.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="mt-4">
                <x-button label="ذخیرهٔ حضور و غیاب" class="btn-primary" wire:click="save" />
            </div>
        </x-card>
    @elseif ($course_id)
        <p class="text-sm opacity-60">برای این دوره جلسهٔ تاریخ‌داری ثبت نشده است.</p>
    @else
        <p class="text-sm opacity-60">اول دوره و بعد جلسه را انتخاب کنید.</p>
    @endif
</div>
