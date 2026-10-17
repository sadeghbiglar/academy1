<?php

namespace Tests\Feature;

use App\Enums\CourseStatus;
use App\Enums\WeekDay;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Student;
use App\Models\Subject;
use App\Services\EnrollmentEligibility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| انتخاب واحد با اعتبارسنجی (شماره 45)
*/

class EnrollmentEligibilityTest extends TestCase
{
    use RefreshDatabase;

    private function service(): EnrollmentEligibility
    {
        return app(EnrollmentEligibility::class);
    }

    private function course(array $session = [], array $attributes = []): Course
    {
        $course = Course::factory()->create($attributes);

        if ($session !== []) {
            [$day, $start, $end] = $session;

            ClassSession::factory()->at($day, $start, $end)->create(['course_id' => $course->id]);
        }

        return $course;
    }

    private function reasonsFor(Student $student, Course $course): array
    {
        return $this->service()
            ->forStudent($student)
            ->firstWhere('course.id', $course->id)['reasons'];
    }

    private function codesFor(Student $student, Course $course): array
    {
        return array_column($this->reasonsFor($student, $course), 'code');
    }

    // ── مجاز بودن ───────────────────────────────────────────

    public function test_an_open_course_with_room_is_eligible(): void
    {
        $course = $this->course([WeekDay::Saturday->value, 9 * 60, 11 * 60]);
        $student = Student::factory()->create();

        $row = $this->service()->forStudent($student)->firstWhere('course.id', $course->id);

        $this->assertTrue($row['eligible']);
        $this->assertSame([], $row['reasons']);
    }

    public function test_a_closed_course_is_rejected(): void
    {
        $course = $this->course([], ['status' => CourseStatus::Closed]);
        $student = Student::factory()->create();

        $this->assertContains('closed', $this->codesFor($student, $course));
    }

    public function test_an_archived_course_is_rejected(): void
    {
        $course = $this->course([], ['status' => CourseStatus::Archived]);
        $student = Student::factory()->create();

        $this->assertContains('closed', $this->codesFor($student, $course));
    }

    public function test_a_full_course_is_rejected(): void
    {
        $course = $this->course([], ['capacity' => 1]);
        $student = Student::factory()->create();

        CourseEnrollment::enroll(Student::factory()->create(), $course)->confirm();

        $this->assertContains('full', $this->codesFor($student, $course));
    }

    public function test_a_duplicate_enrolment_is_rejected(): void
    {
        $course = $this->course();
        $student = Student::factory()->create();

        CourseEnrollment::enroll($student, $course);

        $this->assertContains('duplicate', $this->codesFor($student, $course));
    }

    // ── تداخل زمانی ─────────────────────────────────────────

    public function test_a_time_conflict_is_reported(): void
    {
        $student = Student::factory()->create();

        $taken = $this->course([WeekDay::Saturday->value, 9 * 60, 12 * 60]);
        $conflicting = $this->course([WeekDay::Saturday->value, 11 * 60, 13 * 60]);

        CourseEnrollment::enroll($student, $taken)->confirm();

        $this->assertContains('time_conflict', $this->codesFor($student, $conflicting));
    }

    public function test_the_conflict_message_names_the_other_course(): void
    {
        // پیام بدون نام دوره برای اپراتور بی‌فایده است
        $student = Student::factory()->create();

        $taken = $this->course([WeekDay::Saturday->value, 9 * 60, 12 * 60], ['title' => 'ریاضی صبح']);
        $conflicting = $this->course([WeekDay::Saturday->value, 11 * 60, 13 * 60]);

        CourseEnrollment::enroll($student, $taken)->confirm();

        $messages = array_column($this->reasonsFor($student, $conflicting), 'message');

        $this->assertStringContainsString('ریاضی صبح', implode(' ', $messages));
    }

    public function test_the_same_time_on_another_day_is_not_a_conflict(): void
    {
        $student = Student::factory()->create();

        $taken = $this->course([WeekDay::Saturday->value, 9 * 60, 12 * 60]);
        $other = $this->course([WeekDay::Sunday->value, 9 * 60, 12 * 60]);

        CourseEnrollment::enroll($student, $taken)->confirm();

        $this->assertNotContains('time_conflict', $this->codesFor($student, $other));
    }

    public function test_the_same_time_in_another_week_is_not_a_conflict(): void
    {
        $student = Student::factory()->create();

        $course = $this->course();
        $taken = $this->course();

        ClassSession::factory()->at(WeekDay::Saturday->value, 9 * 60, 12 * 60, 1)
            ->create(['course_id' => $taken->id]);

        ClassSession::factory()->at(WeekDay::Saturday->value, 9 * 60, 12 * 60, 2)
            ->create(['course_id' => $course->id]);

        CourseEnrollment::enroll($student, $taken)->confirm();

        $this->assertNotContains('time_conflict', $this->codesFor($student, $course));
    }

    public function test_a_conflict_with_a_cancelled_enrolment_is_ignored(): void
    {
        // لغو یعنی دیگر جایی نمی‌رود، پس نباید جلوی انتخاب واحد را بگیرد
        $student = Student::factory()->create();

        $taken = $this->course([WeekDay::Saturday->value, 9 * 60, 12 * 60]);
        $other = $this->course([WeekDay::Saturday->value, 9 * 60, 12 * 60]);

        CourseEnrollment::enroll($student, $taken)->confirm()->cancel();

        $this->assertNotContains('time_conflict', $this->codesFor($student, $other));
    }

    public function test_a_conflict_with_a_pending_enrolment_is_ignored(): void
    {
        // رزرو هنوز قطعی نیست؛ سخت‌گیری روی آن انتخاب واحد را غیرممکن می‌کند
        $student = Student::factory()->create();

        $taken = $this->course([WeekDay::Saturday->value, 9 * 60, 12 * 60]);
        $other = $this->course([WeekDay::Saturday->value, 9 * 60, 12 * 60]);

        CourseEnrollment::enroll($student, $taken);

        $this->assertNotContains('time_conflict', $this->codesFor($student, $other));
    }

    public function test_confirming_a_conflicting_enrolment_is_refused(): void
    {
        // سرویس فقط پیشنهاد می‌دهد؛ خود مدل باید جلوی ثبت را بگیرد
        $student = Student::factory()->create();

        $taken = $this->course([WeekDay::Saturday->value, 9 * 60, 12 * 60]);
        $clashing = $this->course([WeekDay::Saturday->value, 11 * 60, 13 * 60]);

        CourseEnrollment::enroll($student, $taken)->confirm();

        $this->expectException(ValidationException::class);

        CourseEnrollment::enroll($student, $clashing)->confirm();
    }

    public function test_a_course_does_not_conflict_with_itself(): void
    {
        $student = Student::factory()->create();

        $course = $this->course([WeekDay::Saturday->value, 9 * 60, 12 * 60]);

        ClassSession::factory()->at(WeekDay::Saturday->value, 10 * 60, 11 * 60)
            ->create(['course_id' => $course->id]);

        CourseEnrollment::enroll($student, $course)->confirm();

        $this->assertNotContains('time_conflict', $this->codesFor($student, $course));
    }

    // ── تکرار درس ───────────────────────────────────────────

    public function test_the_same_subject_in_two_courses_is_rejected(): void
    {
        $student = Student::factory()->create();
        $subject = Subject::factory()->create();

        $first = $this->course([], ['subject_id' => $subject->id]);
        $second = $this->course([], ['subject_id' => $subject->id]);

        CourseEnrollment::enroll($student, $first)->confirm();

        $this->assertContains('same_subject', $this->codesFor($student, $second));
    }

    public function test_a_different_subject_is_not_a_duplicate(): void
    {
        $student = Student::factory()->create();

        $first = $this->course();
        $second = $this->course([], ['subject_id' => Subject::factory()->create()->id]);

        CourseEnrollment::enroll($student, $first)->confirm();

        $this->assertNotContains('same_subject', $this->codesFor($student, $second));
    }

    public function test_the_duplicate_message_names_the_other_course(): void
    {
        $student = Student::factory()->create();
        $subject = Subject::factory()->create();

        $first = $this->course([], ['subject_id' => $subject->id, 'title' => 'فیزیک اول']);
        $second = $this->course([], ['subject_id' => $subject->id]);

        CourseEnrollment::enroll($student, $first)->confirm();

        $messages = array_column($this->reasonsFor($student, $second), 'message');

        $this->assertStringContainsString('فیزیک اول', implode(' ', $messages));
    }

    // ── پایهٔ تحصیلی ────────────────────────────────────────

    public function test_a_course_of_another_grade_is_rejected(): void
    {
        $student = Student::factory()->create();

        $course = $this->course([], [
            'subject_id' => Subject::factory()->create(['grade_level' => 10])->id,
        ]);

        $codes = array_column(
            $this->service()->forStudent($student, gradeLevel: 12)->firstWhere('course.id', $course->id)['reasons'],
            'code',
        );

        $this->assertContains('grade_mismatch', $codes);
    }

    public function test_a_subject_without_a_grade_is_never_a_mismatch(): void
    {
        $student = Student::factory()->create();

        $course = $this->course([], [
            'subject_id' => Subject::factory()->create(['grade_level' => null])->id,
        ]);

        $codes = array_column(
            $this->service()->forStudent($student, gradeLevel: 12)->firstWhere('course.id', $course->id)['reasons'],
            'code',
        );

        $this->assertNotContains('grade_mismatch', $codes);
    }

    // ── خروجی ───────────────────────────────────────────────

    public function test_eligible_only_returns_the_allowed_courses(): void
    {
        $student = Student::factory()->create();

        $open = $this->course([], ['status' => CourseStatus::Open]);
        $closed = $this->course([], ['status' => CourseStatus::Closed]);

        $eligible = $this->service()->eligibleFor($student);

        $this->assertTrue($eligible->contains('id', $open->id));
        $this->assertFalse($eligible->contains('id', $closed->id));
    }

    public function test_every_reason_has_a_persian_message_and_a_code(): void
    {
        // پیام خالی یعنی اپراتور فقط یک دورهٔ غیرقابل انتخاب می‌بیند بدون توضیح
        $student = Student::factory()->create();

        $course = $this->course([], ['status' => CourseStatus::Closed]);

        foreach ($this->reasonsFor($student, $course) as $reason) {
            $this->assertNotSame('', $reason['code']);
            $this->assertNotSame('', $reason['message']);
        }
    }

    public function test_a_course_with_no_schedule_never_conflicts(): void
    {
        $student = Student::factory()->create();

        $taken = $this->course();
        $unscheduled = $this->course();

        CourseEnrollment::enroll($student, $taken)->confirm();

        $this->assertNotContains('time_conflict', $this->codesFor($student, $unscheduled));
    }
}
