<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Enums\GradeType;
use App\Models\Attendance;
use App\Models\Course;
use App\Models\Grade;
use App\Models\Setting;
use App\Models\Student;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * کارنامهٔ رسمی دانش‌آموز (issue #37، قاعدهٔ R4).
 *
 * سند رسمی است: ریزنمرات، ساعت دوره، درصد حضور، نمرهٔ نهایی و وضعیت قبولی،
 * با شمارهٔ سند و تاریخ شمسی و محل امضا. همهٔ اعداد همین‌جا ساخته می‌شوند
 * تا view فقط نمایش بدهد و محاسبه در سند چاپی با پنل یکی بماند.
 *
 * نکتهٔ سند: شمارهٔ سند از دانش‌آموز و سال شمسی ساخته می‌شود، نه از شمارنده.
 * چاپ دوبارهٔ همان کارنامه باید همان شماره را بدهد — سندی که هر بار چاپ شدنش
 * شمارهٔ تازه بگیرد، برای ادارهٔ ثبت یک سند نیست.
 */
class Transcript
{
    /**
     * نمرهٔ قبولی، از ۲۰.
     *
     * ۱۰ پیش‌فرض است چون نصاب رایج است؛ آموزشگاه می‌تواند در تنظیمات عوضش کند.
     * مقدار خراب یا خارج از بازهٔ نمره نباید همه را مردود کند — به بازهٔ ۰ تا ۲۰
     * برمی‌گردد.
     */
    public const PASS_SCORE = 'academic.pass_score';

    public const MIN_SCORE = 0.0;

    public const MAX_SCORE = 20.0;

    public function __construct(
        private readonly WeightedAverage $average = new WeightedAverage,
    ) {}

    /**
     * @return array{
     *     student: Student,
     *     number: string,
     *     issued_at: Carbon,
     *     rows: Collection<int, array>,
     *     has_records: bool,
     *     overall: float|null,
     *     passed: int,
     *     failed: int,
     *     hours: float,
     *     pass_score: float
     * }
     */
    public function build(Student $student): array
    {
        $rows = $student->enrollments()
            ->where('status', EnrollmentStatus::Confirmed->value)
            ->with('course.subject', 'course.teacher')
            ->get()
            ->map(fn ($enrollment) => $this->row($student, $enrollment->course))
            ->values();

        $scored = $rows->pluck('score')->filter(fn ($score) => $score !== null);

        return [
            'student' => $student,
            'number' => $this->number($student),
            'issued_at' => now(),
            'rows' => $rows,
            // بدون هیچ نمره‌ای سند با وضعیت «فاقد سابقه» صادر می‌شود؛ نبودن نمره
            // یعنی هنوز سنجیده نشده، نه اینکه صفر گرفته باشد
            'has_records' => Grade::query()->where('student_id', $student->id)->exists(),
            'overall' => $scored->isEmpty() ? null : round($scored->avg(), 2),
            'passed' => $rows->where('status', 'passed')->count(),
            'failed' => $rows->where('status', 'failed')->count(),
            'hours' => round($rows->sum('hours'), 2),
            'pass_score' => $this->passScore(),
        ];
    }

    /**
     * شمارهٔ سند: سال شمسی، بعد شمارهٔ دانش‌آموز.
     *
     * قطعی و بدون شمارنده: دو چاپ از یک کارنامه یک شماره دارند، و دو دانش‌آموز
     * هرگز یک شماره نمی‌گیرند.
     */
    public function number(Student $student): string
    {
        return sprintf('%s-%04d', jalali_date(now(), 'Y'), $student->id);
    }

    public function passScore(): float
    {
        $value = Setting::get(self::PASS_SCORE);

        if (! is_numeric($value)) {
            return 10.0;
        }

        return min(self::MAX_SCORE, max(self::MIN_SCORE, (float) $value));
    }

    /** یک سطر کارنامه: یک دورهٔ ثبت‌نام‌قطعی‌شده. */
    private function row(Student $student, Course $course): array
    {
        $score = $this->average->finalScore($student, $course);
        $pass = $this->passScore();

        return [
            'course' => $course,
            'grades' => $this->grades($student, $course),
            'score' => $score,
            'hours' => round($course->totalHours(), 2),
            'attendance' => Attendance::attendanceRateFor($student, $course),
            'status' => match (true) {
                $score === null => 'pending',
                $score >= $pass => 'passed',
                default => 'failed',
            },
        ];
    }

    /**
     * ریزنمرات یک دوره، به ترتیب نوع نمره و بعد تاریخ.
     *
     * ترتیب الفبایی نوع (continuous، final، homework…) نمای نمرات را به هم می‌ریزد؛
     * ترتیب enum همان ترتیبی است که میانگین هم با آن کار می‌کند.
     *
     * عمومی است چون کارنامهٔ نهایی دوره (issue #51) هم همین ریزنمرات را با همین
     * ترتیب چاپ می‌کند؛ دو ترتیب متفاوت روی دو سندِ از یک نمره ساخته‌شده،
     * سندی است که با خودش نمی‌خواند.
     *
     * @return Collection<int, Grade>
     */
    public function grades(Student $student, Course $course)
    {
        $order = array_flip(array_map(
            fn (GradeType $type) => $type->value,
            GradeType::cases(),
        ));

        return Grade::query()
            ->where('student_id', $student->id)
            ->where('course_id', $course->id)
            ->get()
            ->sortBy(fn (Grade $grade) => [
                $order[$grade->type->value] ?? 99,
                (string) $grade->graded_at,
                $grade->id,
            ])
            ->values();
    }
}
