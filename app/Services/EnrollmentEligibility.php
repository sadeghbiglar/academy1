<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Student;
use Illuminate\Support\Collection;

/**
 * فهرست دوره‌های مجاز برای یک دانش‌آموز.
 *
 * ثبت‌نام فقط نمایش فهرست نیست؛ یک تصمیم است. این سرویس یک بار همهٔ دلایل رد را
 * حساب می‌کند تا صفحهٔ «انتخاب واحد» کنار هر دوره بنویسد چرا غیرقابل انتخاب
 * است — به‌جای اینکه دوره ناگهان ناپدید شود و اپراتور فکر کند سامانه خراب است.
 */
class EnrollmentEligibility
{
    /**
     * همهٔ دوره‌ها به‌همراه وضعیت مجاز بودن و دلایل رد.
     *
     * @return Collection<int, array{course: Course, eligible: bool, reasons: array<int, array{code: string, message: string}>}>
     */
    public function forStudent(Student $student, ?int $gradeLevel = null): Collection
    {
        $courses = Course::query()
            ->with(['subject', 'sessions'])
            ->withCount([
                'enrollments as confirmed_count' => fn ($q) => $q->where('status', EnrollmentStatus::Confirmed->value),
            ])
            ->get();

        $active = $this->activeEnrollments($student);

        $busySessions = ClassSession::query()
            ->with('course')
            ->whereIn('course_id', $active->pluck('course_id')->all())
            ->get();

        // $student هم لازم است: بررسی ثبت‌نام تکراری بدون آن ناقص می‌ماند
        return $courses->map(function (Course $course) use ($student, $active, $busySessions, $gradeLevel) {
            $reasons = [];

            if (! $course->status->acceptsEnrollment()) {
                $reasons[] = ['code' => 'closed', 'message' => 'ثبت‌نام این دوره باز نیست.'];
            }

            if ($course->capacity > 0 && $course->confirmed_count >= $course->capacity) {
                $reasons[] = ['code' => 'full', 'message' => 'ظرفیت دوره تکمیل است.'];
            }

            if ($this->alreadyEnrolled($student, $course)) {
                $reasons[] = ['code' => 'duplicate', 'message' => 'این دانش‌آموز قبلاً در این دوره ثبت‌نام شده است.'];
            }

            $duplicateOf = $this->sameSubjectConflict($course, $active);

            if ($duplicateOf !== null) {
                $reasons[] = [
                    'code' => 'same_subject',
                    'message' => 'درس «'.$course->subject?->name.'» در «'.$duplicateOf->title.'» ثبت‌نام شده است.',
                ];
            }

            $clash = $this->timeConflict($course, $busySessions);

            if ($clash !== null) {
                $reasons[] = [
                    'code' => 'time_conflict',
                    'message' => 'زمان این دوره با «'.$clash.'» تداخل دارد.',
                ];
            }

            if ($gradeLevel !== null
                && $course->subject?->grade_level !== null
                && $course->subject->grade_level !== $gradeLevel) {
                $reasons[] = [
                    'code' => 'grade_mismatch',
                    'message' => 'این درس برای پایهٔ '.to_persian_digits($gradeLevel).' نیست.',
                ];
            }

            return [
                'course' => $course,
                'eligible' => $reasons === [],
                'reasons' => $reasons,
            ];
        });
    }

    /** @return Collection<int, Course> */
    public function eligibleFor(Student $student, ?int $gradeLevel = null): Collection
    {
        return $this->forStudent($student, $gradeLevel)
            ->filter(fn (array $row) => $row['eligible'])
            ->pluck('course');
    }

    /** @return Collection<int, CourseEnrollment> */
    private function activeEnrollments(Student $student): Collection
    {
        return $student->enrollments()
            ->where('status', EnrollmentStatus::Confirmed->value)
            ->with('course')
            ->get();
    }

    private function alreadyEnrolled(Student $student, Course $course): bool
    {
        return $student->enrollments()
            ->where('course_id', $course->id)
            ->where('status', '!=', EnrollmentStatus::Cancelled->value)
            ->exists();
    }

    /** درسِ تکراری: همان درس در یک دورهٔ دیگر که قطعاً ثبت‌نام شده است. */
    private function sameSubjectConflict(Course $course, Collection $active): ?Course
    {
        return $active->first(
            fn (CourseEnrollment $enrollment) => $enrollment->course->subject_id === $course->subject_id
                && $enrollment->course_id !== $course->id
        )?->course;
    }

    /** عنوان دوره‌ای که با این یکی تداخل زمانی دارد. */
    private function timeConflict(Course $course, Collection $busySessions): ?string
    {
        foreach ($course->sessions as $session) {
            foreach ($busySessions as $busy) {
                if ($busy->course_id === $course->id) {
                    continue;
                }

                if ($session->overlapsWith($busy)) {
                    return $busy->course?->title ?? 'دورهٔ دیگر';
                }
            }
        }

        return null;
    }
}
