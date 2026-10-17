<?php

namespace Tests\Feature;

use App\Enums\CourseStatus;
use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Student;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| ثبت‌نام در دوره (شماره 44)
*/

class EnrollmentTest extends TestCase
{
    use RefreshDatabase;

    private function course(array $attributes = []): Course
    {
        return Course::factory()->create(array_merge([
            'capacity' => 3,
            'base_fee' => 20_000_000,
        ], $attributes));
    }

    // ── ثبت اولیه ───────────────────────────────────────────

    public function test_a_student_is_enrolled_as_pending_first(): void
    {
        // تأیید فقط بعد از پرداخت است؛ ثبت اولیه رزرو جاست
        $enrollment = CourseEnrollment::enroll(Student::factory()->create(), $this->course());

        $this->assertSame(EnrollmentStatus::Pending, $enrollment->status);
        $this->assertNull($enrollment->confirmed_at);
        $this->assertNotNull($enrollment->enrolled_at);
    }

    public function test_a_closed_course_refuses_enrollment(): void
    {
        $this->expectException(ValidationException::class);

        CourseEnrollment::enroll(
            Student::factory()->create(),
            $this->course(['status' => CourseStatus::Closed]),
        );
    }

    public function test_an_archived_course_refuses_enrollment(): void
    {
        $this->expectException(ValidationException::class);

        CourseEnrollment::enroll(
            Student::factory()->create(),
            $this->course(['status' => CourseStatus::Archived]),
        );
    }

    // ── ظرفیت ───────────────────────────────────────────────

    public function test_the_course_becomes_full_at_capacity(): void
    {
        $course = $this->course();

        CourseEnrollment::enroll($student = Student::factory()->create(), $course)->confirm();

        $this->assertSame(1, $course->enrolledCount());
        $this->assertSame(2, $course->freeSeats());
        $this->assertFalse($course->isFull());
    }

    public function test_enrolling_past_capacity_goes_to_the_waitlist(): void
    {
        $course = $this->course(['capacity' => 2]);

        CourseEnrollment::enroll(Student::factory()->create(), $course)->confirm();
        CourseEnrollment::enroll(Student::factory()->create(), $course)->confirm();

        $this->assertTrue($course->isFull());

        $late = CourseEnrollment::enroll(Student::factory()->create(), $course);

        $this->assertSame(EnrollmentStatus::Waitlisted, $late->status);
        $this->assertSame(2, $course->enrolledCount(), 'a waitlisted student must not take a seat');
    }

    public function test_pending_enrollments_do_not_consume_capacity(): void
    {
        $course = $this->course(['capacity' => 1]);

        CourseEnrollment::enroll(Student::factory()->create(), $course);
        CourseEnrollment::enroll(Student::factory()->create(), $course);

        // هیچ‌کدام قطعی نیست، پس هنوز جا هست
        $this->assertSame(0, $course->enrolledCount());
        $this->assertFalse($course->isFull());
    }

    public function test_an_unlimited_course_is_never_full(): void
    {
        // ظرفیت صفر یعنی بی‌نهایت
        $course = $this->course(['capacity' => 0]);

        foreach (range(1, 8) as $ignored) {
            CourseEnrollment::enroll(Student::factory()->create(), $course)->confirm();
        }

        $this->assertNull($course->freeSeats());
        $this->assertFalse($course->isFull());
    }

    public function test_confirming_is_rejected_when_someone_else_took_the_last_seat(): void
    {
        // دو کارمند پذیرش هم‌زمان کار می‌کنند؛ صف باید هنگام «تأیید» دوباره
        // چک شود، نه فقط هنگام نمایش فرم
        $course = $this->course(['capacity' => 2]);

        $first = CourseEnrollment::enroll(Student::factory()->create(), $course);
        $second = CourseEnrollment::enroll(Student::factory()->create(), $course);
        $third = CourseEnrollment::enroll(Student::factory()->create(), $course);

        $first->confirm();
        $third->confirm();

        $this->expectException(ValidationException::class);

        $second->confirm();
    }

    public function test_a_waitlisted_student_can_be_confirmed_when_a_seat_frees_up(): void
    {
        $course = $this->course(['capacity' => 1]);

        $confirmed = CourseEnrollment::enroll(Student::factory()->create(), $course)->confirm();
        $waiting = CourseEnrollment::enroll(Student::factory()->create(), $course);

        $this->assertSame(EnrollmentStatus::Waitlisted, $waiting->status);

        $confirmed->cancel();
        $waiting->refresh()->confirm();

        $this->assertSame(EnrollmentStatus::Confirmed, $waiting->fresh()->status);
        $this->assertSame(1, $course->enrolledCount());
    }

    // ── ثبت‌نام تکراری ───────────────────────────────────────

    public function test_the_same_student_cannot_enroll_twice(): void
    {
        $course = $this->course();
        $student = Student::factory()->create();

        CourseEnrollment::enroll($student, $course);

        $this->expectException(ValidationException::class);

        CourseEnrollment::enroll($student, $course);
    }

    public function test_the_duplicate_check_is_enforced_by_the_database_not_just_a_check(): void
    {
        // ایندکس یکتا روی active_key تنها چیزی است که دو درخواست هم‌زمان را
        // می‌گیرد؛ بررسی در PHP به تنهایی مسابقه را می‌بازد
        $course = $this->course();
        $student = Student::factory()->create();

        CourseEnrollment::enroll($student, $course);

        $this->expectException(UniqueConstraintViolationException::class);

        CourseEnrollment::create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'enrolled_at' => now()->toDateString(),
            'status' => EnrollmentStatus::Pending,
        ]);
    }

    public function test_a_cancelled_enrollment_frees_the_student_to_re_enroll(): void
    {
        $course = $this->course();
        $student = Student::factory()->create();

        $first = CourseEnrollment::enroll($student, $course);
        $first->cancel();

        $second = CourseEnrollment::enroll($student, $course);

        // لغو در سابقه می‌ماند ولی ثبت‌نام دوباره ممکن است
        $this->assertSame(EnrollmentStatus::Pending, $second->status);
        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(2, CourseEnrollment::query()->where('student_id', $student->id)->count());
    }

    public function test_two_students_in_one_course_are_fine(): void
    {
        $course = $this->course();

        CourseEnrollment::enroll(Student::factory()->create(), $course);
        CourseEnrollment::enroll(Student::factory()->create(), $course);

        $this->assertSame(2, $course->enrollments()->count());
    }

    // ── لغو و آزاد شدن ظرفیت ─────────────────────────────────

    public function test_cancelling_frees_a_seat(): void
    {
        $course = $this->course(['capacity' => 2]);

        $enrollment = CourseEnrollment::enroll(Student::factory()->create(), $course)->confirm();

        $this->assertSame(1, $course->freeSeats());

        $enrollment->cancel();

        $this->assertSame(0, $course->enrolledCount());
        $this->assertSame(2, $course->freeSeats(), 'cancelling must free the seat');
        $this->assertNotNull($enrollment->fresh()->cancelled_at);
    }

    public function test_cancelling_keeps_the_record_for_the_history(): void
    {
        // حذف با لغو فرق دارد: لغو یعنی «آمد و برگشت» و باید بماند
        $course = $this->course();
        $enrollment = CourseEnrollment::enroll(Student::factory()->create(), $course)->confirm();

        $enrollment->cancel('انصراف از دوره');

        $fresh = $enrollment->fresh();

        $this->assertModelExists($fresh);
        $this->assertSame(EnrollmentStatus::Cancelled, $fresh->status);
        $this->assertStringContainsString('انصراف', $fresh->notes);
    }

    public function test_a_cancelled_enrollment_is_not_active(): void
    {
        $enrollment = CourseEnrollment::enroll(Student::factory()->create(), $this->course());

        $this->assertTrue($enrollment->status->isActive());
        $enrollment->cancel();
        $this->assertFalse($enrollment->fresh()->status->isActive());
    }

    // ── شهریه ────────────────────────────────────────────────

    public function test_the_final_fee_is_the_base_fee_minus_the_discount(): void
    {
        $course = $this->course(['base_fee' => 20_000_000]);

        $enrollment = CourseEnrollment::enroll(Student::factory()->create(), $course, [
            'discount' => 3_000_000,
        ]);

        $this->assertSame(17_000_000, $enrollment->calculateFinalFee());
    }

    public function test_the_final_fee_never_goes_below_zero(): void
    {
        $course = $this->course(['base_fee' => 5_000_000]);

        $enrollment = CourseEnrollment::enroll(Student::factory()->create(), $course, [
            'discount' => 9_000_000,
        ]);

        $this->assertSame(0, $enrollment->calculateFinalFee());
    }

    public function test_a_negative_discount_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        CourseEnrollment::enroll(Student::factory()->create(), $this->course(), [
            'discount' => -1,
        ]);
    }

    public function test_the_enrolment_date_is_shown_jalali_and_stored_gregorian(): void
    {
        $enrollment = CourseEnrollment::enroll(
            Student::factory()->create(),
            $this->course(),
            ['enrolled_at' => '2026-11-01'],
        );

        $this->assertSame('2026-11-01', $enrollment->fresh()->enrolled_at->format('Y-m-d'));
        $this->assertSame('1405/08/10', $enrollment->enrolledAtJalali());
    }

    public function test_confirming_stamps_the_confirmation_time_only_once(): void
    {
        $enrollment = CourseEnrollment::enroll(Student::factory()->create(), $this->course());

        $enrollment->confirm();
        $first = $enrollment->confirmed_at;

        sleep(1);

        $enrollment->update(['notes' => 'توضیح']);
        $enrollment->refresh();

        $this->assertTrue($first->equalTo($enrollment->confirmed_at));
    }
}
