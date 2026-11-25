<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Models\Attendance;
use App\Models\Course;
use App\Models\Grade;
use App\Models\Student;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * کارنامهٔ نهایی یک دوره (issue #51).
 *
 * کارنامهٔ رسمی دانش‌آموز (Transcript) جواب «این دانش‌آموز چه کرده» را می‌دهد؛
 * این سند جواب «این دوره تمام شد، سرنوشت ثبت‌نام‌ها چه شد» را: هر ثبت‌نامِ
 * قطعی یک سطر است با ریزنمراتِ به تفکیک نوع، میانگین وزنی، نمرهٔ قبولی،
 * ساعت حضور و وضعیت.
 *
 * همهٔ اعداد همین‌جا ساخته می‌شوند تا view فقط نمایش بدهد — سندی که محاسبه‌اش
 * در پنل و محاسبه‌اش روی کاغذ دو جا باشد، دو واقعیت مختلف چاپ می‌کند.
 */
class CourseTranscript
{
    public function __construct(
        private readonly Transcript $transcript = new Transcript,
        private readonly WeightedAverage $average = new WeightedAverage,
        private readonly AttendanceThreshold $threshold = new AttendanceThreshold,
    ) {}

    /**
     * @return array{
     *     course: Course,
     *     number: string,
     *     issued_at: Carbon,
     *     rows: Collection<int, array>,
     *     has_records: bool,
     *     average: float|null,
     *     passed: int,
     *     failed: int,
     *     conditional: int,
     *     pending: int,
     *     hours: float,
     *     attended_hours: float,
     *     pass_score: float
     * }
     */
    public function build(Course $course): array
    {
        $passScore = $this->transcript->passScore();

        $rows = $course->enrollments()
            ->where('status', EnrollmentStatus::Confirmed->value)
            ->with('student')
            ->get()
            // ثبت‌نامِ بدون دانش‌آموز قابل چاپ نیست: سطری بدون نام، در سندِ
            // رسمی جای خالیِ بی‌معنا می‌گذارد
            ->filter(fn ($enrollment) => $enrollment->student !== null)
            ->map(fn ($enrollment) => $this->row($enrollment->student, $course, $passScore))
            ->values();

        $scored = $rows->pluck('score')->filter(fn ($score) => $score !== null);

        return [
            'course' => $course,
            'number' => $this->number($course),
            'issued_at' => now(),
            'rows' => $rows,
            // بدون نمره سند با وضعیت «فاقد سابقه» صادر می‌شود؛ نبودن نمره یعنی
            // هنوز سنجیده نشده، نه اینکه صفر گرفته باشد
            'has_records' => Grade::query()->where('course_id', $course->id)->exists(),
            'average' => $scored->isEmpty() ? null : round($scored->avg(), 2),
            'passed' => $rows->where('status', 'passed')->count(),
            'failed' => $rows->where('status', 'failed')->count(),
            'conditional' => $rows->where('status', 'conditional')->count(),
            'pending' => $rows->where('status', 'pending')->count(),
            'hours' => round($course->totalHours(), 2),
            'attended_hours' => round($rows->sum('attended_hours'), 2),
            'pass_score' => $passScore,
        ];
    }

    /**
     * شمارهٔ سند: سال شمسی، بعد «ک» و شمارهٔ دوره.
     *
     * «ک» برای «دوره» است تا شمارهٔ کارنامهٔ یک دوره با شمارهٔ کارنامهٔ یک
     * دانش‌آموز (1404-0007) یکی درنیاید؛ دو سندِ متفاوت با یک شماره، برای
     * ادارهٔ ثبت یک سند نیست. قطعی و بدون شمارنده: دو چاپ یک شماره دارند.
     */
    public function number(Course $course): string
    {
        return sprintf('%s-ک%04d', jalali_date(now(), 'Y'), $course->id);
    }

    /** یک سطر کارنامه: یک دانش‌آموزِ ثبت‌نام‌قطعی‌شده در این دوره. */
    private function row(Student $student, Course $course, float $passScore): array
    {
        $score = $this->average->finalScore($student, $course);
        $rate = Attendance::attendanceRateFor($student, $course);

        return [
            'student' => $student,
            'grades' => $this->transcript->grades($student, $course),
            'score' => $score,
            'attended_hours' => Attendance::attendedHoursFor($student, $course),
            'attendance' => $rate,
            'status' => $this->status($score, $rate, $passScore),
        ];
    }

    /**
     * وضعیت قبولی یک دانش‌آموز در این دوره: passed، failed یا conditional.
     *
     * نمرهٔ نهایی زیر نمرهٔ قبولی یعنی مردود، با هر تعداد نمرهٔ زیر حد —
     * میانگین وزنیِ اعدادِ زیر حد همیشه زیر حد است، پس «دو نمره زیر حد»
     * هرگز نمی‌تواند مشروط شود.
     *
     * «قبولی مشروط» برای حضور است نه برای نمره: نمره کافی آمده ولی حضور زیر
     * آستانهٔ خودِ آموزشگاه (تنظیم AttendanceThreshold) است، یعنی قبولی مشروط
     * به جبران غیبت. نمرهٔ بالا و غیبتِ زیاد نباید «قبولی» مطلق چاپ شود.
     *
     * نبودِ رکورد حضور یعنی چیزی برای سنجیدن نیست، نه قبولی مشروط: نرخ null
     * اینجا هیچ تصمیمی نمی‌گیرد.
     */
    private function status(?float $score, ?float $rate, float $passScore): string
    {
        if ($score === null) {
            return 'pending';
        }

        if ($score < $passScore) {
            return 'failed';
        }

        if ($rate !== null && $this->threshold->needsAttention($rate)) {
            return 'conditional';
        }

        return 'passed';
    }
}
