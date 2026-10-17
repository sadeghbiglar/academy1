<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\GradeType;
use App\Enums\Role;
use App\Enums\WeekDay;
use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Grade;
use App\Models\Student;
use App\Models\User;
use App\Services\AcademicReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| گزارش‌های آموزشی (شماره 57)
*/

class AcademicReportTest extends TestCase
{
    use RefreshDatabase;

    private function report(): AcademicReport
    {
        return new AcademicReport;
    }

    private function enrolled(): array
    {
        $course = Course::factory()->create();
        $student = Student::factory()->create();
        CourseEnrollment::enroll($student, $course)->confirm();

        return [$student, $course];
    }

    private function classSession(Course $course, ?string $date): ClassSession
    {
        return ClassSession::factory()->at(WeekDay::Saturday->value, 540, 660)
            ->create(['course_id' => $course->id, 'session_date' => $date]);
    }

    // ── حضور ───────────────────────────────────────────────

    public function test_the_attendance_rate_is_computed_over_the_range(): void
    {
        [$student, $course] = $this->enrolled();

        foreach ([1, 2, 3, 4] as $ignored) {
            Attendance::recordFor($this->classSession($course, '2026-10-05'), [
                ['student_id' => $student->id, 'status' => AttendanceStatus::Present->value],
            ]);
        }

        $row = $this->report()->attendanceFor($student, '2026-10-01', '2026-10-31');

        $this->assertEqualsWithDelta(100.0, $row['rate'], 0.01);
        $this->assertSame(4, $row['present']);
    }

    public function test_late_counts_as_present_in_the_rate(): void
    {
        [$student, $course] = $this->enrolled();

        Attendance::recordFor($this->classSession($course, '2026-10-05'), [
            ['student_id' => $student->id, 'status' => AttendanceStatus::Late->value],
        ]);

        $row = $this->report()->attendanceFor($student, '2026-10-01', '2026-10-31');

        $this->assertEqualsWithDelta(100.0, $row['rate'], 0.01);
        $this->assertSame(1, $row['present']);
    }

    public function test_excused_sessions_leave_the_denominator(): void
    {
        // جلسهٔ موجه نباید درصد را خراب کند
        [$student, $course] = $this->enrolled();

        Attendance::recordFor($this->classSession($course, '2026-10-05'), [
            ['student_id' => $student->id, 'status' => AttendanceStatus::Present->value],
        ]);

        Attendance::recordFor($this->classSession($course, '2026-10-12'), [
            ['student_id' => $student->id, 'status' => AttendanceStatus::Excused->value],
        ]);

        $row = $this->report()->attendanceFor($student, '2026-10-01', '2026-10-31');

        $this->assertEqualsWithDelta(100.0, $row['rate'], 0.01);
        $this->assertSame(1, $row['excused']);
        $this->assertSame(1, $row['total']);
    }

    public function test_no_record_means_no_rate(): void
    {
        [$student] = $this->enrolled();

        $row = $this->report()->attendanceFor($student, '2026-10-01', '2026-10-31');

        $this->assertNull($row['rate']);
        $this->assertSame(0, $row['total']);
    }

    public function test_sessions_outside_the_range_are_excluded(): void
    {
        [$student, $course] = $this->enrolled();

        Attendance::recordFor($this->classSession($course, '2026-09-05'), [
            ['student_id' => $student->id, 'status' => AttendanceStatus::Present->value],
        ]);

        $this->assertNull($this->report()->attendanceFor($student, '2026-10-01', '2026-10-31')['rate']);
        $this->assertEqualsWithDelta(100.0, $this->report()->attendanceFor($student)['rate'], 0.01);
    }

    public function test_absences_are_counted_separately(): void
    {
        [$student, $course] = $this->enrolled();

        Attendance::recordFor($this->classSession($course, '2026-10-05'), [
            ['student_id' => $student->id, 'status' => AttendanceStatus::Present->value],
        ]);

        Attendance::recordFor($this->classSession($course, '2026-10-12'), [
            ['student_id' => $student->id, 'status' => AttendanceStatus::Absent->value],
        ]);

        $row = $this->report()->attendanceFor($student, '2026-10-01', '2026-10-31');

        $this->assertEqualsWithDelta(50.0, $row['rate'], 0.01);
        $this->assertSame(1, $row['absent']);
    }

    // ── کم‌حضور ────────────────────────────────────────────

    public function test_a_low_attendance_student_is_listed(): void
    {
        [$student, $course] = $this->enrolled();

        foreach (range(1, 4) as $index) {
            Attendance::recordFor($this->classSession($course, '2026-10-0'.$index), [
                ['student_id' => $student->id, 'status' => AttendanceStatus::Absent->value],
            ]);
        }

        $rows = $this->report()->lowAttendance(collect([$student]), '2026-10-01', '2026-10-31');

        $this->assertCount(1, $rows);
        $this->assertSame($student->id, $rows->first()['student']->id);
        $this->assertSame('critical', $rows->first()['level']);
    }

    public function test_a_student_with_no_record_is_not_called_low_attendance(): void
    {
        // بدون رکورد نرخی وجود ندارد؛ «کم‌حضور» دربارهٔ کسی که سنجیده نشده
        // یعنی اتهام بی‌سند
        [$student] = $this->enrolled();

        $this->assertCount(0, $this->report()->lowAttendance(collect([$student])));
    }

    public function test_a_good_attendance_student_is_not_listed(): void
    {
        [$student, $course] = $this->enrolled();

        Attendance::recordFor($this->classSession($course, '2026-10-05'), [
            ['student_id' => $student->id, 'status' => AttendanceStatus::Present->value],
        ]);

        $this->assertCount(0, $this->report()->lowAttendance(collect([$student])));
    }

    public function test_the_worst_attender_comes_first(): void
    {
        $report = $this->report();

        $worst = Student::factory()->create(['first_name' => 'الف']);
        $middle = Student::factory()->create(['first_name' => 'ب']);

        foreach ([[$worst, 4], [$middle, 2]] as [$student, $absences]) {
            $course = Course::factory()->create();
            CourseEnrollment::enroll($student, $course)->confirm();

            foreach (range(1, 4) as $index) {
                Attendance::recordFor($this->classSession($course, '2026-10-0'.$index), [
                    ['student_id' => $student->id,
                        'status' => $index <= $absences
                            ? AttendanceStatus::Absent->value
                            : AttendanceStatus::Present->value],
                ]);
            }
        }

        $rows = $report->lowAttendance(collect([$middle, $worst]));

        $this->assertSame($worst->id, $rows->first()['student']->id);
    }

    // ── نمرات ──────────────────────────────────────────────

    public function test_the_raw_average_is_the_mean_of_percentages(): void
    {
        // ۱۰ از ۱۰ و ۱۰ از ۲۰ هر دو ۱۰۰٪ و ۵۰٪ هستند؛ میانگین نمرهٔ خام ۱۰ می‌شود
        // که با میانگین درصد یکی نیست
        [$student, $course] = $this->enrolled();

        Grade::record($student, $course, GradeType::Quiz, 'الف', 10, maxScore: 10, gradedAt: '2026-10-05');
        Grade::record($student, $course, GradeType::Quiz, 'ب', 10, maxScore: 20, gradedAt: '2026-10-05');

        $this->assertEqualsWithDelta(75.0, $this->report()->rawAverage($student), 0.01);
    }

    public function test_the_average_respects_the_range(): void
    {
        [$student, $course] = $this->enrolled();

        Grade::record($student, $course, GradeType::Quiz, 'قدیمی', 20, gradedAt: '2026-09-01');
        Grade::record($student, $course, GradeType::Quiz, 'جدید', 10, gradedAt: '2026-10-05');

        $this->assertEqualsWithDelta(50.0, $this->report()->rawAverage($student, '2026-10-01', '2026-10-31'), 0.01);
    }

    public function test_a_student_without_grades_has_no_average(): void
    {
        [$student] = $this->enrolled();

        $this->assertNull($this->report()->rawAverage($student));
    }

    public function test_the_final_score_and_rank_come_per_confirmed_course(): void
    {
        [$student, $course] = $this->enrolled();

        Grade::record($student, $course, GradeType::Final, 'پایان‌ترم', 18);

        $rows = $this->report()->gradesFor($student);

        $this->assertCount(1, $rows);
        $this->assertEqualsWithDelta(18.0, $rows->first()['score'], 0.01);
        $this->assertSame(1, $rows->first()['rank']);
    }

    public function test_an_unconfirmed_enrolment_is_not_reported(): void
    {
        $course = Course::factory()->create();
        $student = Student::factory()->create();
        CourseEnrollment::enroll($student, $course);

        $this->assertCount(0, $this->report()->gradesFor($student));
    }

    // ── جلسات ──────────────────────────────────────────────

    public function test_only_dated_sessions_are_counted(): void
    {
        // جلسهٔ هفتگیِ بدون تاریخ در بازه نمی‌افتد چون تاریخی ندارد
        $course = Course::factory()->create();

        $this->classSession($course, '2026-10-05');
        $this->classSession($course, '2026-10-12');
        $this->classSession($course, null);

        $this->assertCount(2, $this->report()->sessionsIn('2026-10-01', '2026-10-31'));
    }

    public function test_sessions_respect_the_range(): void
    {
        $course = Course::factory()->create();

        $this->classSession($course, '2026-09-05');
        $this->classSession($course, '2026-10-12');

        $this->assertCount(1, $this->report()->sessionsIn('2026-10-01', '2026-10-31'));
    }

    // ── مقایسهٔ دو بازه ────────────────────────────────────

    public function test_the_difference_between_two_ranges_is_reported(): void
    {
        [$student, $course] = $this->enrolled();

        // بازهٔ قبل: دو غیبت از دو جلسه = ۰٪
        Attendance::recordFor($this->classSession($course, '2026-09-01'), [
            ['student_id' => $student->id, 'status' => AttendanceStatus::Absent->value],
        ]);

        // بازهٔ فعلی: دو حاضر = ۱۰۰٪
        Attendance::recordFor($this->classSession($course, '2026-10-01'), [
            ['student_id' => $student->id, 'status' => AttendanceStatus::Present->value],
        ]);

        $delta = $this->report()->compare(
            $student,
            ['from' => '2026-10-01', 'to' => '2026-10-31'],
            ['from' => '2026-09-01', 'to' => '2026-09-30'],
        );

        $this->assertEqualsWithDelta(100.0, $delta['attendance_delta'], 0.01);
        $this->assertSame('رشد ۱۰۰٫۰', $this->report()->trendLabel($delta['attendance_delta']));
    }

    public function test_a_decline_is_reported_as_a_decline(): void
    {
        [$student, $course] = $this->enrolled();

        Attendance::recordFor($this->classSession($course, '2026-09-01'), [
            ['student_id' => $student->id, 'status' => AttendanceStatus::Present->value],
        ]);

        Attendance::recordFor($this->classSession($course, '2026-10-01'), [
            ['student_id' => $student->id, 'status' => AttendanceStatus::Absent->value],
        ]);

        $delta = $this->report()->compare(
            $student,
            ['from' => '2026-10-01', 'to' => '2026-10-31'],
            ['from' => '2026-09-01', 'to' => '2026-09-30'],
        );

        $this->assertEqualsWithDelta(-100.0, $delta['attendance_delta'], 0.01);
        $this->assertStringContainsString('افت', $this->report()->trendLabel($delta['attendance_delta']));
    }

    public function test_the_comparison_is_a_difference_not_a_relative_change(): void
    {
        // «رشد ۱۰ درصدی» روی درصد حضور گمراه‌کننده است؛ واحد درصد است
        [$student, $course] = $this->enrolled();

        foreach (range(1, 10) as $index) {
            Attendance::recordFor($this->classSession($course, sprintf('2026-09-%02d', $index)), [
                ['student_id' => $student->id, 'status' => AttendanceStatus::Present->value],
            ]);
        }

        // ۸ حاضر و ۲ غیبت در اکتبر = ۸۰٪، در برابر ۱۰۰٪ سپتامبر
        foreach (range(1, 10) as $index) {
            Attendance::recordFor($this->classSession($course, sprintf('2026-10-%02d', $index)), [
                [
                    'student_id' => $student->id,
                    'status' => $index > 8
                        ? AttendanceStatus::Absent->value
                        : AttendanceStatus::Present->value,
                ],
            ]);
        }

        $delta = $this->report()->compare(
            $student,
            ['from' => '2026-10-01', 'to' => '2026-10-31'],
            ['from' => '2026-09-01', 'to' => '2026-09-30'],
        );

        $this->assertEqualsWithDelta(-20.0, $delta['attendance_delta'], 0.01);
    }

    public function test_a_missing_range_yields_no_delta(): void
    {
        // نبودن داده صفر نیست؛ بدون بازهٔ قبل «رشد» نسبت به هیچ است
        [$student, $course] = $this->enrolled();

        Attendance::recordFor($this->classSession($course, '2026-10-01'), [
            ['student_id' => $student->id, 'status' => AttendanceStatus::Present->value],
        ]);

        // بازهٔ قبل یک ماهِ دیگر است که هیچ رکوردی ندارد
        $delta = $this->report()->compare(
            $student,
            ['from' => '2026-10-01', 'to' => '2026-10-31'],
            ['from' => '2026-08-01', 'to' => '2026-08-31'],
        );

        $this->assertNull($delta['attendance_delta']);
        $this->assertSame('—', $this->report()->trendLabel(null));
    }

    public function test_no_change_is_reported_as_no_change(): void
    {
        $this->assertSame('بدون تغییر', $this->report()->trendLabel(0.0));
    }

    // ── صفحه ───────────────────────────────────────────────

    public function test_the_page_is_open_to_deputy_and_teacher(): void
    {
        foreach ([Role::Deputy, Role::Teacher, Role::Admin] as $role) {
            $this->actingAs(User::factory()->role($role)->create());

            $this->get(route('reports.academic'))->assertOk();
        }
    }

    public function test_finance_cannot_open_the_academic_report(): void
    {
        $this->actingAs(User::factory()->role(Role::Finance)->create());

        $this->get(route('reports.academic'))->assertForbidden();
    }

    public function test_the_page_shows_the_students_and_the_session_count(): void
    {
        $this->actingAs(User::factory()->role(Role::Admin)->create());

        $student = Student::factory()->create(['first_name' => 'یاسین']);
        $course = Course::factory()->create();
        CourseEnrollment::enroll($student, $course)->confirm();
        $this->classSession($course, '2026-10-05');

        $this->get(route('reports.academic', ['from' => '1405/07/01', 'to' => '1405/07/30']))
            ->assertOk()
            ->assertSee('یاسین')
            ->assertSee('جلسات برگزارشده');
    }

    public function test_the_page_lists_low_attendance_students(): void
    {
        $this->actingAs(User::factory()->role(Role::Admin)->create());

        $student = Student::factory()->create(['first_name' => 'کم‌حضور']);
        $course = Course::factory()->create();
        CourseEnrollment::enroll($student, $course)->confirm();

        foreach (range(1, 4) as $index) {
            Attendance::recordFor($this->classSession($course, '2026-10-0'.$index), [
                ['student_id' => $student->id, 'status' => AttendanceStatus::Absent->value],
            ]);
        }

        $this->get(route('reports.academic', ['from' => '1405/07/01', 'to' => '1405/07/30']))
            ->assertOk()
            ->assertSee('کم‌حضور')
            ->assertSee('غیبت بیش از حد');
    }

    public function test_the_page_says_when_no_previous_range_was_given(): void
    {
        $this->actingAs(User::factory()->role(Role::Admin)->create());
        Student::factory()->create();

        $this->get(route('reports.academic'))
            ->assertOk()
            ->assertSee('بازهٔ قبل تعیین نشده');
    }

    public function test_the_page_shows_the_comparison_when_a_previous_range_is_given(): void
    {
        $this->actingAs(User::factory()->role(Role::Admin)->create());

        $student = Student::factory()->create();
        $course = Course::factory()->create();
        CourseEnrollment::enroll($student, $course)->confirm();

        Attendance::recordFor($this->classSession($course, '2026-10-05'), [
            ['student_id' => $student->id, 'status' => AttendanceStatus::Absent->value],
        ]);

        $this->get(route('reports.academic', [
            'from' => '1405/07/01', 'to' => '1405/07/30',
            'previous_from' => '1405/07/01', 'previous_to' => '1405/07/30',
        ]))->assertOk()->assertSee('رشد');
    }

    public function test_the_page_lists_a_student_with_no_attendance_record(): void
    {
        // نبودن رکورد باید ردیف بسازد با «—»، نه اینکه ردیف را حذف کند
        $this->actingAs(User::factory()->role(Role::Admin)->create());
        Student::factory()->create(['first_name' => 'بدون‌رکورد']);

        $this->get(route('reports.academic'))
            ->assertOk()
            ->assertSee('بدون‌رکورد')
            ->assertSee('دانش‌آموز کم‌حضوری در این بازه نیست');
    }

    public function test_the_page_says_so_when_there_are_no_students(): void
    {
        $this->actingAs(User::factory()->role(Role::Admin)->create());

        $this->get(route('reports.academic'))
            ->assertOk()
            ->assertSee('دانش‌آموزی ثبت نشده است');
    }
}
