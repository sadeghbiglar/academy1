<?php

namespace Tests\Feature;

use App\Enums\CourseStatus;
use App\Models\Course;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| دروس و دوره‌ها (شماره 42 و 43)
*/

class CourseAndSubjectTest extends TestCase
{
    use RefreshDatabase;

    // ── دروس ────────────────────────────────────────────────

    public function test_a_subject_can_be_created(): void
    {
        $subject = Subject::create([
            'name' => 'ریاضی',
            'code' => 'MATH-10',
            'grade_level' => 10,
            'annual_hours' => 120,
        ]);

        $this->assertModelExists($subject);
        $this->assertTrue($subject->is_active);
    }

    public function test_the_subject_code_is_unique(): void
    {
        Subject::create(['name' => 'ریاضی', 'code' => 'MATH-10']);

        $this->expectException(QueryException::class);

        Subject::create(['name' => 'ریاضی دهم', 'code' => 'MATH-10']);
    }

    public function test_a_subject_belongs_to_many_courses(): void
    {
        $subject = Subject::factory()->create();
        Course::factory()->count(3)->create(['subject_id' => $subject->id]);

        $this->assertCount(3, $subject->courses);
    }

    public function test_a_subject_can_be_deactivated_without_deleting_it(): void
    {
        // غیرفعال کردن، نه حذف: دوره‌های گذشته به همین درس ارجاع دارند
        $subject = Subject::factory()->create(['is_active' => true]);

        $subject->update(['is_active' => false]);

        $this->assertModelExists($subject->fresh());
        $this->assertFalse($subject->fresh()->is_active);

        // غیرفعال یعنی از فیلترِ «فقط فعال‌ها» بیرون می‌رود، ولی ردیف می‌ماند
        $this->assertSame(0, Subject::query()->active()->count());
        $this->assertSame(1, Subject::query()->count());

        Subject::factory()->create();

        $this->assertSame(1, Subject::query()->active()->count());
    }

    public function test_deleting_a_subject_cascades_to_its_courses(): void
    {
        $subject = Subject::factory()->create();
        Course::factory()->count(2)->create(['subject_id' => $subject->id]);

        $subject->delete();

        $this->assertSame(0, Course::query()->count());
    }

    public function test_the_remaining_annual_hours_account_for_session_length(): void
    {
        // ۱۰ جلسهٔ ۹۰ دقیقه‌ای = ۱۵ ساعت، نه ۱۰ ساعت. جمع سادهٔ تعداد جلسات
        // ظرفیت را غلط حساب می‌کرد
        $subject = Subject::factory()->create(['annual_hours' => 100]);

        Course::factory()->create([
            'subject_id' => $subject->id,
            'session_count' => 10,
            'session_duration' => 90,
            'status' => CourseStatus::Open,
        ]);

        $this->assertSame(85, $subject->fresh()->remainingHours());
    }

    public function test_archived_courses_do_not_consume_annual_capacity(): void
    {
        $subject = Subject::factory()->create(['annual_hours' => 100]);

        Course::factory()->archived()->create([
            'subject_id' => $subject->id,
            'session_count' => 20,
            'session_duration' => 120,
        ]);

        // دورهٔ بایگانی‌شده سالش تمام است و نباید ظرفیت سال بعد را بخورد
        $this->assertSame(100, $subject->fresh()->remainingHours());
    }

    public function test_a_subject_without_an_hour_limit_reports_no_remaining_hours(): void
    {
        $subject = Subject::factory()->create(['annual_hours' => null]);

        $this->assertNull($subject->remainingHours());
        $this->assertFalse($subject->isOverCapacity());
    }

    public function test_a_subject_can_be_over_capacity(): void
    {
        $subject = Subject::factory()->create(['annual_hours' => 10]);

        Course::factory()->create([
            'subject_id' => $subject->id,
            'session_count' => 20,
            'session_duration' => 60,
        ]);

        $this->assertTrue($subject->fresh()->isOverCapacity());
    }

    // ── دوره‌ها ─────────────────────────────────────────────

    public function test_a_course_can_be_created_with_a_teacher(): void
    {
        $teacher = Teacher::factory()->create();
        $subject = Subject::factory()->create();

        $course = Course::create([
            'title' => 'دورهٔ جمع‌بندی',
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'capacity' => 15,
            'session_count' => 12,
            'session_duration' => 90,
            'base_fee' => 25_000_000,
            'starts_at' => '2026-11-01',
        ]);

        $this->assertSame(CourseStatus::Open, $course->status);
        $this->assertSame('باز', $course->statusLabel());
        $this->assertTrue($course->teacher->is($teacher));
        $this->assertTrue($course->subject->is($subject));
    }

    public function test_a_negative_capacity_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        Course::factory()->create(['capacity' => -1]);
    }

    public function test_a_negative_fee_is_rejected(): void
    {
        // شهریهٔ منفی یعنی آموزشگاه به دانش‌آموز بدهکار می‌شود
        $this->expectException(ValidationException::class);

        Course::factory()->create(['base_fee' => -1]);
    }

    public function test_a_zero_fee_is_allowed_because_it_means_free(): void
    {
        $course = Course::factory()->create(['base_fee' => 0]);

        $this->assertSame(0, $course->base_fee);
    }

    public function test_a_zero_session_duration_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        Course::factory()->create(['session_duration' => 0]);
    }

    public function test_the_total_hours_multiply_sessions_by_length(): void
    {
        $course = Course::factory()->make([
            'session_count' => 12,
            'session_duration' => 90,
        ]);

        $this->assertEqualsWithDelta(18.0, $course->totalHours(), 0.001);
    }

    public function test_the_start_date_is_stored_gregorian_and_shown_jalali(): void
    {
        $course = Course::factory()->create(['starts_at' => '2026-11-01']);

        $this->assertSame('2026-11-01', $course->fresh()->starts_at->format('Y-m-d'));
        $this->assertSame('1405/08/10', $course->startsAtJalali());
    }

    public function test_courses_can_be_filtered_by_status(): void
    {
        Course::factory()->count(3)->create();
        Course::factory()->closed()->count(2)->create();
        Course::factory()->archived()->count(4)->create();

        $this->assertSame(3, Course::query()->status(CourseStatus::Open)->count());
        $this->assertSame(2, Course::query()->status(CourseStatus::Closed)->count());
        $this->assertSame(4, Course::query()->status(CourseStatus::Archived)->count());

        // فیلتر با رشته هم کار می‌کند چون فرم مقدار را به صورت متن می‌فرستد
        $this->assertSame(2, Course::query()->status('closed')->count());
    }

    public function test_the_open_scope_returns_only_enrollable_courses(): void
    {
        Course::factory()->count(2)->create();
        Course::factory()->closed()->create();
        Course::factory()->archived()->create();

        $this->assertSame(2, Course::query()->open()->count());
    }

    public function test_only_an_open_course_accepts_enrollment(): void
    {
        $open = Course::factory()->create(['status' => CourseStatus::Open]);
        $closed = Course::factory()->closed()->create();
        $archived = Course::factory()->archived()->create();

        $this->assertTrue($open->status->acceptsEnrollment());
        $this->assertFalse($closed->status->acceptsEnrollment());
        $this->assertFalse($archived->status->acceptsEnrollment());
    }

    public function test_a_course_survives_the_deletion_of_its_teacher(): void
    {
        // حذف استاد نباید سابقهٔ دوره را پاک کند؛ دوره بایگانی می‌شود
        $course = Course::factory()->create();

        $course->teacher->delete();

        $this->assertModelExists($course->fresh());
        $this->assertNull($course->fresh()->teacher);
    }

    public function test_a_course_can_have_no_teacher_yet(): void
    {
        $course = Course::factory()->withoutTeacher()->create();

        $this->assertNull($course->teacher);
    }

    public function test_the_archived_courses_are_hidden_from_the_daily_filter(): void
    {
        Course::factory()->create();
        Course::factory()->archived()->create();

        $this->assertSame(1, Course::query()->where('status', '!=', CourseStatus::Archived->value)->count());
    }
}
