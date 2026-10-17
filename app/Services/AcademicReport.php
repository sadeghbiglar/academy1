<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\EnrollmentStatus;
use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\Grade;
use App\Models\Student;
use Illuminate\Support\Collection;

/**
 * گزارش‌های آموزشی: حضور در یک بازه، میانگین نمرات، دانش‌آموزان کم‌حضور.
 *
 * همهٔ بازه‌ها روی تاریخ جلسه/نمره اعمال می‌شوند، نه روی فاکتور یا ثبت‌نام.
 * گزارش حضور باید همان جلسات برگزارشده را بشمارد.
 */
class AcademicReport
{
    public function __construct(
        private readonly AttendanceThreshold $threshold = new AttendanceThreshold,
        private readonly WeightedAverage $average = new WeightedAverage,
    ) {}

    /**
     * درصد حضور یک دانش‌آموز در یک بازه.
     *
     * جلسات موجه از مخرج کم می‌شوند: جلسه‌ای که برگزار نشد نباید درصد را
     * خراب کند. و اگر هیچ جلسهٔ قابل محاسبه‌ای نبود، null برمی‌گردد نه صفر —
     * «۰٪» اتهام است.
     *
     * @return array{rate: float|null, present: int, absent: int, excused: int, total: int}
     */
    public function attendanceFor(Student $student, ?string $from = null, ?string $to = null): array
    {
        $rows = $this->attendanceRows($student, $from, $to);

        $counts = [
            AttendanceStatus::Present->value => 0,
            AttendanceStatus::Absent->value => 0,
            AttendanceStatus::Late->value => 0,
            AttendanceStatus::Excused->value => 0,
        ];

        foreach ($rows as $row) {
            $counts[$row->status->value] = ($counts[$row->status->value] ?? 0) + 1;
        }

        // تأخیر یعنی در کلاس بوده، پس در حاضرها می‌شمارد
        $present = $counts[AttendanceStatus::Present->value] + $counts[AttendanceStatus::Late->value];
        $absent = $counts[AttendanceStatus::Absent->value];
        $excused = $counts[AttendanceStatus::Excused->value];
        $countable = $present + $absent;

        return [
            'rate' => $countable === 0 ? null : round($present / $countable * 100, 2),
            'present' => $present,
            'absent' => $absent,
            'excused' => $excused,
            'total' => $countable,
        ];
    }

    /** @return Collection<int, Attendance> */
    private function attendanceRows(Student $student, ?string $from, ?string $to): Collection
    {
        return Attendance::query()
            ->where('student_id', $student->id)
            ->whereHas('classSession', fn ($q) => $q
                ->when($from, fn ($s) => $s->whereDate('session_date', '>=', $from))
                ->when($to, fn ($s) => $s->whereDate('session_date', '<=', $to)))
            ->get();
    }

    /**
     * میانگین نمرهٔ نهایی هر دوره برای یک دانش‌آموز، فقط دوره‌های ثبت‌نام‌شده.
     *
     * @return Collection<int, array{course: mixed, score: float|null, rank: int|null}>
     */
    public function gradesFor(Student $student): Collection
    {
        return $student->enrollments()
            ->where('status', EnrollmentStatus::Confirmed->value)
            ->with('course.subject')
            ->get()
            ->map(function ($enrollment) use ($student) {
                $ranking = $this->average->rankStudents($enrollment->course);

                $row = $ranking->firstWhere('student.id', $student->id);

                return [
                    'course' => $enrollment->course,
                    'score' => $row['score'] ?? $this->average->finalScore($student, $enrollment->course),
                    'rank' => $row['rank'] ?? null,
                ];
            })
            ->values();
    }

    /** میانگین نمره‌های خام یک دانش‌آموز در یک بازه. */
    public function rawAverage(Student $student, ?string $from = null, ?string $to = null): ?float
    {
        $rows = Grade::query()
            ->where('student_id', $student->id)
            ->when($from, fn ($q) => $q->whereDate('graded_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('graded_at', '<=', $to))
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        // میانگین درصدها، نه میانگین نمره‌های خام: یک نمرهٔ ۱۰ از ۱۰ و یک
        // نمرهٔ ۲۰ از ۲۰ میانگین ۱۵ می‌دهند و این با میانگین ۱۰ و ۲۰ یکی نیست
        return round($rows->sum(fn (Grade $grade) => $grade->percentage()) / $rows->count(), 2);
    }

    /**
     * دانش‌آموزان کم‌حضور، بدترین اول.
     *
     * دانش‌آموزی که در بازه اصلاً حضور ثبت نشده ندارد در این فهرست نمی‌آید:
     * بدون رکورد، نرخی وجود ندارد و «کم‌حضور» دربارهٔ کسی که هنوز سنجیده
     * نشده یعنی اتهام بی‌سند.
     *
     * @param  Collection<int, Student>  $students
     * @return Collection<int, array>
     */
    public function lowAttendance($students, ?string $from = null, ?string $to = null): Collection
    {
        return $students
            ->map(fn (Student $student) => [
                'student' => $student,
                'attendance' => $this->attendanceFor($student, $from, $to),
            ])
            ->reject(fn (array $row) => $row['attendance']['rate'] === null)
            ->filter(fn (array $row) => $this->threshold->needsAttention($row['attendance']['rate']))
            ->sortBy(fn (array $row) => $row['attendance']['rate'])
            ->map(function (array $row) {
                $row['level'] = $this->threshold->level($row['attendance']['rate']);

                return $row;
            })
            ->values();
    }

    /**
     * جلساتِ برگزارشده در یک بازه — نمایندهٔ مخرجِ گزارش حضور.
     *
     * «جلسهٔ برگزارشده» یعنی تاریخ مشخص در گذشته؛ جلسهٔ هفتگیِ بدون تاریخ در
     * بازه نمی‌افتد چون تاریخی ندارد.
     *
     * @return Collection<int, ClassSession>
     */
    public function sessionsIn(?string $from = null, ?string $to = null): Collection
    {
        return ClassSession::query()
            ->whereNotNull('session_date')
            ->when($from, fn ($q) => $q->whereDate('session_date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('session_date', '<=', $to))
            ->orderBy('session_date')
            ->get();
    }

    /**
     * مقایسهٔ دو بازه برای یک دانش‌آموز: رشد حضور و نمره.
     *
     * تفاضل، نه درصد تغییر: «رشد ۱۲ واحدی» برای درصد حضور معنی دارد،
     * «۲۰٪ رشد» روی ۸۰٪ گمراه‌کننده است.
     *
     * @return array{attendance_delta: float|null, score_delta: float|null}
     */
    public function compare(Student $student, array $current, array $previous): array
    {
        $currentAttendance = $this->attendanceFor($student, $current['from'] ?? null, $current['to'] ?? null);
        $previousAttendance = $this->attendanceFor($student, $previous['from'] ?? null, $previous['to'] ?? null);

        $currentScore = $this->rawAverage($student, $current['from'] ?? null, $current['to'] ?? null);
        $previousScore = $this->rawAverage($student, $previous['from'] ?? null, $previous['to'] ?? null);

        return [
            'attendance_delta' => $this->delta($currentAttendance['rate'], $previousAttendance['rate']),
            'score_delta' => $this->delta($currentScore, $previousScore),
        ];
    }

    /** null اگر یکی از دو نرخ نباشد — نبودن داده، صفر نیست. */
    private function delta(?float $current, ?float $previous): ?float
    {
        if ($current === null || $previous === null) {
            return null;
        }

        return round($current - $previous, 2);
    }

    /** برچسب رشد/افت برای نمایش. */
    public function trendLabel(?float $delta): string
    {
        if ($delta === null) {
            return '—';
        }

        return match (true) {
            $delta > 0 => 'رشد '.to_persian_digits(number_format(abs($delta), 1)),
            $delta < 0 => 'افت '.to_persian_digits(number_format(abs($delta), 1)),
            default => 'بدون تغییر',
        };
    }
}
