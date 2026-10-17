<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\WeekDay;
use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Student;
use App\Services\AttendanceThreshold;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| حضور و غیاب روزانه (شماره 48)
*/

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    private function course(): Course
    {
        return Course::factory()->create();
    }

    private function classSession(Course $course, int $day = WeekDay::Saturday->value): ClassSession
    {
        return ClassSession::factory()->at($day, 9 * 60, 11 * 60)->create(['course_id' => $course->id]);
    }

    private function enrolled(Course $course): Student
    {
        $student = Student::factory()->create();
        CourseEnrollment::enroll($student, $course)->confirm();

        return $student;
    }

    // ── ثبت ────────────────────────────────────────────────

    public function test_an_attendance_is_recorded(): void
    {
        $course = $this->course();
        $session = $this->classSession($course);
        $student = $this->enrolled($course);

        $attendance = Attendance::create([
            'class_session_id' => $session->id,
            'student_id' => $student->id,
            'status' => AttendanceStatus::Present,
        ]);

        $this->assertModelExists($attendance);
        $this->assertSame(AttendanceStatus::Present, $attendance->status);
        $this->assertTrue($attendance->attended());
    }

    public function test_the_same_student_cannot_be_recorded_twice_in_one_session(): void
    {
        $course = $this->course();
        $session = $this->classSession($course);
        $student = $this->enrolled($course);

        Attendance::create([
            'class_session_id' => $session->id,
            'student_id' => $student->id,
            'status' => AttendanceStatus::Present,
        ]);

        $this->expectException(QueryException::class);

        Attendance::create([
            'class_session_id' => $session->id,
            'student_id' => $student->id,
            'status' => AttendanceStatus::Absent,
        ]);
    }

    public function test_a_whole_class_is_marked_present_in_one_call(): void
    {
        $course = $this->course();
        $session = $this->classSession($course);

        $students = collect(range(1, 5))->map(fn () => $this->enrolled($course));

        $written = Attendance::markAllPresent($session);

        $this->assertSame(5, $written);
        $this->assertSame(5, $session->attendances()->count());
        $this->assertSame(5, $session->attendances()->where('status', AttendanceStatus::Present->value)->count());
    }

    public function test_marking_all_present_twice_does_not_duplicate(): void
    {
        // دکمهٔ «همه حاضر» روی جلسه‌ای که نیمی‌اش دستی ثبت شده نباید
        // رکورد تکراری بسازد یا ثبت دستی را پاک کند
        $course = $this->course();
        $session = $this->classSession($course);

        $present = $this->enrolled($course);
        $absent = $this->enrolled($course);

        Attendance::create([
            'class_session_id' => $session->id,
            'student_id' => $absent->id,
            'status' => AttendanceStatus::Absent,
        ]);

        Attendance::markAllPresent($session);
        Attendance::markAllPresent($session);

        $this->assertSame(2, $session->attendances()->count());
        $this->assertSame(AttendanceStatus::Absent, $absent->fresh()->attendances()->first()->status);
    }

    public function test_pending_enrolments_are_not_marked_present(): void
    {
        // رزرو تأییدنشده در کلاس حاضر نیست
        $course = $this->course();
        $session = $this->classSession($course);

        $this->enrolled($course);
        CourseEnrollment::enroll(Student::factory()->create(), $course);

        Attendance::markAllPresent($session);

        $this->assertSame(1, $session->attendances()->count());
    }

    public function test_a_batch_records_individual_statuses(): void
    {
        $course = $this->course();
        $session = $this->classSession($course);

        $a = $this->enrolled($course);
        $b = $this->enrolled($course);
        $c = $this->enrolled($course);

        $written = Attendance::recordFor($session, [
            ['student_id' => $a->id, 'status' => AttendanceStatus::Present->value],
            ['student_id' => $b->id, 'status' => AttendanceStatus::Absent->value],
            ['student_id' => $c->id, 'status' => AttendanceStatus::Late->value, 'arrival_minute' => 9 * 60 + 15],
        ]);

        $this->assertSame(3, $written);
        $this->assertSame(AttendanceStatus::Absent, $b->fresh()->attendances()->first()->status);
        $this->assertSame('09:15', $c->fresh()->attendances()->first()->arrivalLabel());
    }

    public function test_the_arrival_time_is_shown_as_a_label(): void
    {
        $course = $this->course();
        $attendance = Attendance::factory()->create([
            'class_session_id' => $this->classSession($course)->id,
            'arrival_minute' => 16 * 60 + 45,
        ]);

        $this->assertSame('16:45', $attendance->arrivalLabel());
        $this->assertNull(Attendance::factory()->create([
            'class_session_id' => $this->classSession($course)->id,
        ])->arrivalLabel());
    }

    // ── معنی وضعیت‌ها ──────────────────────────────────────

    public function test_late_counts_as_attended_but_is_not_the_same_as_present(): void
    {
        // تأخیر یعنی سر کلاس بوده ولی جریمهٔ جزئی دارد
        $this->assertTrue(AttendanceStatus::Late->attended());
        $this->assertTrue(AttendanceStatus::Present->attended());
        $this->assertFalse(AttendanceStatus::Absent->attended());
        $this->assertFalse(AttendanceStatus::Excused->attended());
    }

    public function test_only_an_unexcused_absence_affects_the_fee(): void
    {
        // غیبت موجه نباید جریمه شود
        $this->assertTrue(AttendanceStatus::Absent->affectsFee());
        $this->assertFalse(AttendanceStatus::Excused->affectsFee());
        $this->assertFalse(AttendanceStatus::Present->affectsFee());
        $this->assertFalse(AttendanceStatus::Late->affectsFee());
    }

    public function test_every_status_has_a_persian_label_and_color(): void
    {
        foreach (AttendanceStatus::cases() as $status) {
            $this->assertNotSame('', $status->label());
            $this->assertStringStartsWith('badge-', $status->color());
        }

        $this->assertSame('—', AttendanceStatus::labelFor(null));
    }

    // ── درصد حضور ─────────────────────────────────────────

    public function test_the_attendance_rate_is_computed_per_course(): void
    {
        $course = $this->course();
        $student = $this->enrolled($course);

        foreach (range(1, 4) as $ignored) {
            $session = $this->classSession($course);

            Attendance::recordFor($session, [
                ['student_id' => $student->id, 'status' => AttendanceStatus::Present->value],
            ]);
        }

        $rate = Attendance::attendanceRateFor($student, $course);

        $this->assertEqualsWithDelta(100.0, $rate, 0.01);
    }

    public function test_absences_lower_the_rate(): void
    {
        $course = $this->course();
        $student = $this->enrolled($course);

        // چهار جلسهٔ جدا، نه چهار ردیف در یک جلسه — یکتایی
        // (جلسه، دانش‌آموز) چهار ردیف یکسان را به یکی تبدیل می‌کند
        foreach (range(1, 4) as $ignored) {
            Attendance::recordFor($this->classSession($course), [
                ['student_id' => $student->id, 'status' => AttendanceStatus::Present->value],
            ]);
        }

        Attendance::recordFor($this->classSession($course), [
            ['student_id' => $student->id, 'status' => AttendanceStatus::Absent->value],
        ]);

        // ۴ از ۵ حاضر = ۸۰٪
        $this->assertEqualsWithDelta(80.0, Attendance::attendanceRateFor($student, $course), 0.01);
    }

    public function test_late_sessions_count_as_present_in_the_rate(): void
    {
        $course = $this->course();
        $student = $this->enrolled($course);

        Attendance::recordFor($this->classSession($course), [
            ['student_id' => $student->id, 'status' => AttendanceStatus::Late->value],
        ]);

        $this->assertEqualsWithDelta(100.0, Attendance::attendanceRateFor($student, $course), 0.01);
    }

    public function test_excused_sessions_are_excluded_from_the_rate(): void
    {
        // جلسه‌ای که برگزار نشد نباید درصد دانش‌آموز را خراب کند
        $course = $this->course();
        $student = $this->enrolled($course);

        Attendance::recordFor($this->classSession($course), [
            ['student_id' => $student->id, 'status' => AttendanceStatus::Present->value],
        ]);

        Attendance::recordFor($this->classSession($course), [
            ['student_id' => $student->id, 'status' => AttendanceStatus::Excused->value],
        ]);

        $this->assertEqualsWithDelta(100.0, Attendance::attendanceRateFor($student, $course), 0.01);
    }

    public function test_only_excused_sessions_gives_no_rate_at_all(): void
    {
        // تقسیم بر صفر که «۰٪» یا «۱۰۰٪» دروغ می‌گفت
        $course = $this->course();
        $student = $this->enrolled($course);

        Attendance::recordFor($this->classSession($course), [
            ['student_id' => $student->id, 'status' => AttendanceStatus::Excused->value],
        ]);

        $this->assertNull(Attendance::attendanceRateFor($student, $course));
    }

    public function test_a_student_with_no_record_has_no_rate(): void
    {
        $course = $this->course();
        $student = $this->enrolled($course);

        $this->assertNull(Attendance::attendanceRateFor($student, $course));
    }

    public function test_the_rate_only_counts_that_students_own_sessions(): void
    {
        $course = $this->course();
        $student = $this->enrolled($course);
        $other = $this->enrolled($course);

        Attendance::recordFor($this->classSession($course), [
            ['student_id' => $student->id, 'status' => AttendanceStatus::Absent->value],
        ]);

        Attendance::recordFor($this->classSession($course), [
            ['student_id' => $other->id, 'status' => AttendanceStatus::Absent->value],
        ]);

        $this->assertEqualsWithDelta(0.0, Attendance::attendanceRateFor($student, $course), 0.01);
    }

    // ── آستانه هشدار ───────────────────────────────────────

    public function test_a_high_rate_needs_no_attention(): void
    {
        $threshold = new AttendanceThreshold;

        $this->assertSame('ok', $threshold->level(95.0));
        $this->assertFalse($threshold->needsAttention(95.0));
        $this->assertSame('حضور مناسب', $threshold->levelLabel(95.0));
    }

    public function test_a_rate_below_the_warning_threshold_is_flagged(): void
    {
        $threshold = new AttendanceThreshold;

        $this->assertSame('warning', $threshold->level(75.0));
        $this->assertTrue($threshold->needsAttention(75.0));
        $this->assertSame('حضور زیر آستانه', $threshold->levelLabel(75.0));
    }

    public function test_a_rate_below_the_critical_threshold_is_critical(): void
    {
        $threshold = new AttendanceThreshold;

        $this->assertSame('critical', $threshold->level(50.0));
        $this->assertSame('غیبت بیش از حد', $threshold->levelLabel(50.0));
    }

    public function test_a_null_rate_is_never_flagged(): void
    {
        // بدون جلسهٔ قابل محاسبه، هشدار دادن یعنی اتهام بی‌دلیل
        $threshold = new AttendanceThreshold;

        $this->assertNull($threshold->level(null));
        $this->assertFalse($threshold->needsAttention(null));
        $this->assertSame('—', $threshold->levelLabel(null));
    }

    public function test_the_threshold_is_configurable(): void
    {
        // آستانه یک تنظیم آموزشگاه است، نه ثابت کد
        $threshold = new AttendanceThreshold(warningBelow: 95.0);

        $this->assertSame('warning', $threshold->level(90.0));
        $this->assertSame('ok', $threshold->level(96.0));
    }

    public function test_the_course_report_lists_every_confirmed_student(): void
    {
        $course = $this->course();
        $good = $this->enrolled($course);
        $bad = $this->enrolled($course);

        CourseEnrollment::enroll(Student::factory()->create(), $course);   // رزرو، نباید بیاید

        foreach (range(1, 4) as $ignored) {
            Attendance::recordFor($this->classSession($course), [
                ['student_id' => $good->id, 'status' => AttendanceStatus::Present->value],
            ]);
        }

        Attendance::recordFor($this->classSession($course), [
            ['student_id' => $bad->id, 'status' => AttendanceStatus::Absent->value],
        ]);

        $rows = (new AttendanceThreshold)->forCourse($course);

        $this->assertCount(2, $rows);

        $byStudent = $rows->keyBy(fn (array $row) => $row['student']->id);

        $this->assertSame('ok', $byStudent[$good->id]['level']);
        $this->assertSame('critical', $byStudent[$bad->id]['level']);
        $this->assertSame(EnrollmentStatus::Confirmed, $good->enrollments()->first()->status);
    }

    public function test_deleting_a_session_removes_its_attendance(): void
    {
        $course = $this->course();
        $session = $this->classSession($course);
        $student = $this->enrolled($course);

        Attendance::recordFor($session, [['student_id' => $student->id]]);

        $session->delete();

        $this->assertSame(0, Attendance::query()->count());
    }
}
