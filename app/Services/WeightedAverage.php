<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Enums\GradeType;
use App\Models\Course;
use App\Models\Grade;
use App\Models\Student;
use Illuminate\Support\Collection;

/**
 * میانگین وزنی و رتبه‌بندی.
 *
 * وزن روی «نوع نمره» است، نه روی تک‌تک نمره‌ها. میانگین سادهٔ همهٔ نمره‌ها یعنی
 * بیست تکلیف می‌تواند پایان‌ترم را کاملاً بی‌اثر کند — یعنی کارنامهٔ دانش‌آموزی
 * که همهٔ کلاس را کار کرده، از کسی که فقط امتحان داده ضعیف‌تر شود.
 */
class WeightedAverage
{
    /**
     * نمرهٔ نهایی یک دانش‌آموز در یک دوره، از ۲۰.
     *
     * هر نوع نمره اول به درصد تبدیل و بعد با وزن همان نوع میانگین گرفته می‌شود.
     * وزن‌دادن به نمرهٔ خام اشتباه است: یک تکلیف ۱۰نمره‌ای و یک پایان‌ترم
     * ۲۰نمره‌ای اسمی ۲ برابر وزن دارند و ترم را به هم می‌ریزند.
     */
    public function finalScore(Student $student, Course $course): ?float
    {
        $weighted = $this->weightedPercentage($student, $course);

        if ($weighted === null) {
            return null;
        }

        return round($weighted * 20 / 100, 2);
    }

    /** درصد وزنی، بدون تبدیل به ۲۰. */
    public function weightedPercentage(Student $student, Course $course): ?float
    {
        $byType = Grade::forStudentInCourse($student, $course)
            ->groupBy(fn (Grade $grade) => $grade->type->value);

        $weightedSum = 0.0;
        $weightTotal = 0.0;

        foreach ($byType as $typeValue => $grades) {
            $type = GradeType::tryFrom($typeValue);

            if ($type === null || ! $type->countsTowardFinal()) {
                continue;
            }

            $weight = $type->weight();

            $weightedSum += $this->averagePercentage($grades) * $weight;
            $weightTotal += $weight;
        }

        if ($weightTotal === 0.0) {
            return null;
        }

        return round($weightedSum / $weightTotal, 2);
    }

    /** میانگین سادهٔ درصدهای یک گروه نمره. */
    private function averagePercentage(Collection $grades): float
    {
        if ($grades->isEmpty()) {
            return 0.0;
        }

        return $grades->sum(fn (Grade $grade) => $grade->percentage()) / $grades->count();
    }

    /**
     * رتبهٔ دانش‌آموزان یک دوره، به ترتیب نمرهٔ نهایی.
     *
     * رتبه بدون تراز شدن یعنی دو نفر با نمرهٔ برابر رتبه‌های متفاوت می‌گیرند و
     * کارنامه‌شان با هم نمی‌خواند. مساوات با رتبهٔ یکسان برگردانده می‌شود
     * (rank-competition).
     *
     * @return Collection<int, array{student: Student, score: float|null, rank: int|null}>
     */
    public function rankStudents(Course $course): Collection
    {
        $rows = $course->enrollments()
            ->where('status', EnrollmentStatus::Confirmed->value)
            ->with('student')
            ->get()
            ->map(fn ($enrollment) => [
                'student' => $enrollment->student,
                'score' => $this->finalScore($enrollment->student, $course),
            ])
            ->sortByDesc('score')
            ->values();

        $rank = 0;
        $index = 0;
        $previous = null;

        return $rows->map(function (array $row) use (&$rank, &$index, &$previous) {
            $index++;

            // دانش‌آموزی که هیچ نمره‌ای ندارد رتبه نمی‌گیرد؛ رتبهٔ صفر یا
            // انتهای فهرست برایش یعنی «بدترین» و این برداشت نادرستی است
            if ($row['score'] === null) {
                return $row + ['rank' => null];
            }

            if ($previous === null || $row['score'] < $previous) {
                $rank = $index;
                $previous = $row['score'];
            }

            return $row + ['rank' => $rank];
        });
    }
}
