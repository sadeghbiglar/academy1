<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\Role;
use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| ورقهٔ حضور روزانهٔ یک جلسه (شماره 41)
|
| فرم گروهی است: یک جلسه، همهٔ ثبت‌نام‌های قطعی، یک وضعیت برای هر نفر.
| اجرای دوبارهٔ فرم نباید ردیف تکراری بسازد، چون در پایگاه داده یکتایی
| (جلسه، دانش‌آموز) هست و فرم «همه حاضر» ممکن است چند بار زده شود.
*/

class AttendanceSheetTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    /** جلسهٔ تاریخ‌دار برای یک دوره. */
    private function sessionFor(Course $course, string $date = '2026-10-20'): ClassSession
    {
        return ClassSession::factory()->create([
            'course_id' => $course->id,
            'session_date' => $date,
            'start_minute' => 10 * 60,
            'end_minute' => 11 * 60 + 30,
        ]);
    }

    public function test_only_confirmed_enrollments_appear_on_the_sheet(): void
    {
        $course = Course::factory()->create();
        $session = $this->sessionFor($course);

        $confirmed = Student::factory()->create(['first_name' => 'مریم', 'last_name' => 'قاسمی']);
        $pending = Student::factory()->create(['first_name' => 'رضا', 'last_name' => 'کریمی']);

        CourseEnrollment::factory()->confirmed()->create([
            'course_id' => $course->id, 'student_id' => $confirmed->id,
        ]);
        CourseEnrollment::factory()->create([
            'course_id' => $course->id, 'student_id' => $pending->id,
        ]);

        Livewire::actingAs($this->admin())
            ->test('pages::attendance.index')
            ->set('course_id', $course->id)
            ->set('session_id', $session->id)
            ->assertSee('مریم')
            ->assertDontSee('رضا');
    }

    public function test_saving_twice_does_not_duplicate_attendance_rows(): void
    {
        $course = Course::factory()->create();
        $session = $this->sessionFor($course);

        $student = Student::factory()->create();
        CourseEnrollment::factory()->confirmed()->create([
            'course_id' => $course->id, 'student_id' => $student->id,
        ]);

        $component = Livewire::actingAs($this->admin())
            ->test('pages::attendance.index')
            ->set('course_id', $course->id)
            ->set('session_id', $session->id)
            ->set('statuses', [$student->id => AttendanceStatus::Absent->value])
            ->call('save');

        $this->assertSame(1, Attendance::query()
            ->where('class_session_id', $session->id)
            ->where('student_id', $student->id)
            ->count());

        // اجرای دوبارهٔ فرم همان ردیف را به‌روزرسانی می‌کند، ردیف دوم نمی‌سازد
        $component->set('statuses', [$student->id => AttendanceStatus::Present->value])->call('save');

        $this->assertSame(1, Attendance::query()->count());
        $this->assertSame(
            AttendanceStatus::Present->value,
            Attendance::query()->where('student_id', $student->id)->first()->status->value,
        );
    }

    public function test_mark_all_present_fills_every_student(): void
    {
        $course = Course::factory()->create();
        $session = $this->sessionFor($course);

        $students = Student::factory()->count(3)->create();
        foreach ($students as $student) {
            CourseEnrollment::factory()->confirmed()->create([
                'course_id' => $course->id, 'student_id' => $student->id,
            ]);
        }

        Livewire::actingAs($this->admin())
            ->test('pages::attendance.index')
            ->set('course_id', $course->id)
            ->set('session_id', $session->id)
            ->call('markAllPresent')
            ->assertSet('statuses', [
                $students[0]->id => AttendanceStatus::Present->value,
                $students[1]->id => AttendanceStatus::Present->value,
                $students[2]->id => AttendanceStatus::Present->value,
            ]);
    }

    public function test_saving_without_a_session_is_refused(): void
    {
        $course = Course::factory()->create();
        $student = Student::factory()->create();
        CourseEnrollment::factory()->confirmed()->create([
            'course_id' => $course->id, 'student_id' => $student->id,
        ]);

        Livewire::actingAs($this->admin())
            ->test('pages::attendance.index')
            ->set('course_id', $course->id)
            ->set('statuses', [$student->id => AttendanceStatus::Present->value])
            ->call('save')
            ->assertHasErrors(['session_id']);

        $this->assertSame(0, Attendance::query()->count());
    }

    public function test_the_page_is_gated_by_role(): void
    {
        // همان نقش‌هایی که بخش «حضور و غیاب» را می‌بینند (Role::sections):
        // پذیرش ثبت‌نام کارش است نه حضور کلاس، و مالی به حضور دسترسی ندارد
        foreach ([Role::Finance, Role::Reception] as $role) {
            $this->actingAs(User::factory()->role($role)->create())
                ->get('/attendance')
                ->assertForbidden();
        }

        foreach ([Role::Admin, Role::Deputy, Role::Teacher] as $role) {
            $this->actingAs(User::factory()->role($role)->create())
                ->get('/attendance')
                ->assertOk();
        }
    }
}
