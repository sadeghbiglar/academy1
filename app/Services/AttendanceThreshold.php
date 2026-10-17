<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Models\Attendance;
use App\Models\Course;
use App\Models\Student;
use Illuminate\Support\Collection;

/**
 * هشدار حضور زیر آستانه.
 *
 * آستانه‌ها یک تنظیم آموزشگاه‌اند، نه یک ثابت کد: اینجا مقدار پیش‌فرض است و
 * بعداً از صفحهٔ تنظیمات می‌آید.
 *
 * «زیر آستانه» وقتی معنا دارد که درصد واقعاً قابل محاسبه باشد. اگر همهٔ
 * جلسات موجه باشند درصد نداریم و نباید هشدار داد — دانش‌آموزی که جلسه نداشته
 * غایب نیست.
 */
class AttendanceThreshold
{
    public const WARNING_BELOW = 80.0;   // زیر این، هشدار جدی

    public const CRITICAL_BELOW = 65.0; // زیر این، بیش از حد غیبت

    public function __construct(private readonly float $warningBelow = self::WARNING_BELOW) {}

    public function level(?float $rate): ?string
    {
        if ($rate === null) {
            return null;
        }

        if ($rate < self::CRITICAL_BELOW) {
            return 'critical';
        }

        return $rate < $this->warningBelow ? 'warning' : 'ok';
    }

    public function levelLabel(?float $rate): string
    {
        return match ($this->level($rate)) {
            'critical' => 'غیبت بیش از حد',
            'warning' => 'حضور زیر آستانه',
            'ok' => 'حضور مناسب',
            default => '—',
        };
    }

    public function color(?float $rate): string
    {
        return match ($this->level($rate)) {
            'critical' => 'badge-error',
            'warning' => 'badge-warning',
            'ok' => 'badge-success',
            default => 'badge-ghost',
        };
    }

    public function needsAttention(?float $rate): bool
    {
        return $this->level($rate) !== null && $this->level($rate) !== 'ok';
    }

    /**
     * دانش‌آموزان یک دوره با درصد حضور و سطح هشدار.
     *
     * @return Collection<int, array{student: Student, rate: float|null, level: string|null}>
     */
    public function forCourse(Course $course): Collection
    {
        return $course->enrollments()
            ->where('status', EnrollmentStatus::Confirmed->value)
            ->with('student')
            ->get()
            ->map(function ($enrollment) use ($course) {
                // یک بار محاسبه، دو بار استفاده: تکرار فراخوانی یعنی دو کوئری
                // به‌ازای هر دانش‌آموز
                $rate = Attendance::attendanceRateFor($enrollment->student, $course);

                return [
                    'student' => $enrollment->student,
                    'rate' => $rate,
                    'level' => $this->level($rate),
                ];
            });
    }
}
