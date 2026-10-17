<?php

use App\Enums\CourseStatus;
use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Mary\Traits\Toast;

new #[Layout('layouts::academy')]
class extends Component {
    use Toast;
    use WithPagination;

    public string $title = 'دوره‌ها';

    public string $search = '';

    public string $statusFilter = '';

    public bool $showCreateModal = false;

    // فرم ایجاد. رشته‌ها نگه داشته می‌شوند چون کاربر تایپ می‌کند؛ تبدیل عدد در
    // مرحلهٔ validate انجام می‌شود، چون parse_number('') مقدار null می‌دهد نه صفر
    public string $new_title = '';
    public string $new_code = '';
    public string $new_subject_id = '';
    public string $new_teacher_id = '';
    public string $new_capacity = '';
    public string $new_session_count = '';
    public string $new_session_duration = '';
    public string $new_base_fee = '';

    /** تاریخ شروع: مقدار این property همیشه میلادی ISO است. */
    public string $new_starts_at = '';

    public array $headers = [
        ['key' => 'row_number', 'label' => '#'],
        ['key' => 'title', 'label' => 'عنوان'],
        ['key' => 'subject', 'label' => 'درس'],
        ['key' => 'teacher', 'label' => 'استاد'],
        ['key' => 'capacity', 'label' => 'ظرفیت'],
        ['key' => 'base_fee', 'label' => 'شهریه'],
        ['key' => 'status', 'label' => 'وضعیت'],
        ['key' => 'starts_at', 'label' => 'شروع'],
    ];

    /** جست‌وجو و فیلتر باید صفحه را برگردانند، وگرنه کاربر روی صفحهٔ خالی گیر می‌کند. */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'statusFilter']);
    }

    public function createCourse(): void
    {
        $this->validate(
            [
                'new_title' => ['required', 'string', 'min:3', 'max:255'],
                'new_code' => ['nullable', 'string', 'max:30', Rule::unique('courses', 'code')],
                'new_subject_id' => ['required', 'integer', Rule::exists('subjects', 'id')],
                'new_teacher_id' => ['nullable', 'integer', Rule::exists('teachers', 'id')],
                'new_capacity' => ['required', 'integer', 'min:0', 'max:1000'],
                'new_session_count' => ['required', 'integer', 'min:0', 'max:500'],
                'new_session_duration' => ['required', 'integer', 'min:15', 'max:480'],
                'new_base_fee' => ['required', 'integer', 'min:0'],
                'new_starts_at' => ['nullable', 'date_format:Y-m-d'],
            ],
            [],
            [
                'new_title' => 'عنوان',
                'new_code' => 'کد دوره',
                'new_subject_id' => 'درس',
                'new_teacher_id' => 'استاد',
                'new_capacity' => 'ظرفیت',
                'new_session_count' => 'تعداد جلسات',
                'new_session_duration' => 'مدت هر جلسه',
                'new_base_fee' => 'شهریه',
                'new_starts_at' => 'تاریخ شروع',
            ],
        );

        // ظرفیت ۰ یعنی نامحدود و ذخیره‌سازی باید عدد باشد نه رشتهٔ خالی؛
        // parse_number('') مقدار null می‌دهد، نه صفر
        Course::create([
            'title' => $this->new_title,
            'code' => $this->new_code === '' ? null : $this->new_code,
            'subject_id' => (int) $this->new_subject_id,
            'teacher_id' => $this->new_teacher_id === '' ? null : (int) $this->new_teacher_id,

            'capacity' => (int) $this->new_capacity,
            'session_count' => (int) $this->new_session_count,
            'session_duration' => (int) $this->new_session_duration,
            'base_fee' => (int) $this->new_base_fee,

            'starts_at' => $this->new_starts_at === '' ? null : $this->new_starts_at,
            'status' => CourseStatus::Open,
        ]);

        $this->resetCreateForm();
        $this->showCreateModal = false;
        $this->resetPage();

        $this->success('ایجاد شد', 'دورهٔ جدید ثبت شد.');
    }

    public function resetCreateForm(): void
    {
        $this->reset([
            'new_title', 'new_code', 'new_subject_id', 'new_teacher_id',
            'new_capacity', 'new_session_count', 'new_session_duration',
            'new_base_fee', 'new_starts_at',
        ]);
    }

    #[Computed]
    public function subjectOptions(): array
    {
        return Subject::query()->active()->orderBy('grade_level')->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Subject $s) => ['id' => $s->id, 'name' => $s->name])
            ->all();
    }

    #[Computed]
    public function teacherOptions(): array
    {
        return Teacher::query()->orderBy('first_name')->get(['id', 'first_name', 'last_name'])
            ->map(fn (Teacher $t) => ['id' => $t->id, 'name' => $t->first_name.' '.$t->last_name])
            ->all();
    }

    #[Computed]
    public function courses()
    {
        $courses = Course::query()
            ->with(['subject', 'teacher'])
            ->when($this->search !== '', function ($query) {
                // نام درس و استاد هم قابل جست‌وجوست، از راه رابطه — وگرنه عنوانی که
                // کاربر می‌زند در خودِ ردیف دوره نیست
                $term = '%'.$this->search.'%';

                $query->where(function ($query) use ($term) {
                    $query->where('title', 'like', $term)
                        ->orWhereHas('subject', fn ($q) => $q->where('name', 'like', $term))
                        ->orWhereHas('teacher', fn ($q) => $q
                            ->where('first_name', 'like', $term)
                            ->orWhere('last_name', 'like', $term));
                });
            })
            ->when($this->statusFilter !== '', fn ($query) => $query->where('status', $this->statusFilter))

            // ترتیب قطعی. بدون orderBy، MySQL ردیف‌ها را به هر ترتیبی برمی‌گرداند و
            // صفحه‌بندی تکرار یا رد می‌کند.
            ->orderByDesc('id')
            ->paginate(15);

        // شمارهٔ ردیف روی خود مدل می‌نشیند، چون scope جدول آرگومان شماره را
        // دریافت نمی‌کند و باید از خود صفحه شروع شماره را بداند
        $courses->getCollection()->each(
            fn (Course $course, int $index) => $course->row_number = $courses->firstItem() + $index
        );

        return $courses;
    }

    /**
     * تعداد ثبت‌نام‌های قطعی برای همهٔ دوره‌ها در یک کوئری.
     *
     * یک کوئری به‌ازای هر ردیفِ جدول یعنی ۱۵ کوئری اضافه برای یک صفحه.
     *
     * کلیدها با array_map به int تبدیل می‌شوند: pluck روی بعضی درایورها کلید را
     * رشته می‌دهد و `$counts[$course->id]` با کلید عددی بی‌صدا به null می‌رسد —
     * یعنی ستون ظرفیت همیشه خالی نشان داده می‌شود، بدون هیچ خطایی.
     */
    #[Computed]
    public function confirmedCounts(): array
    {
        $counts = CourseEnrollment::query()
            ->where('status', EnrollmentStatus::Confirmed->value)
            ->selectRaw('course_id, count(*) as total')
            ->groupBy('course_id')
            ->pluck('total', 'course_id')
            ->all();

        return array_map('intval', $counts);
    }

    public function render()
    {
        return view('pages.courses.index', [
            'statusOptions' => CourseStatus::options(),
        ]);
    }
}
?>
