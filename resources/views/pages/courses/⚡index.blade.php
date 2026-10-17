<?php

use App\Enums\CourseStatus;
use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\CourseEnrollment;
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
