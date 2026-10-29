<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Attendance;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| گزارش ساعت کارکرد اساتید (شماره 41)
|
| مبنای محاسبهٔ حقوق است، پس بازه باید شمسی گرفته شود و «جمع ساعت» باید
| جلسهٔ واقعاً برگزارشده را بشمارد نه جلسهٔ برنامه‌ریزی‌شده را.
*/

class TeacherHoursTest extends TestCase
{
    use RefreshDatabase;

    /** جلسهٔ برگزارشده: تاریخ مشخص و دست‌کم یک ردیف حضور. */
    private function heldSession(Course $course, string $date, int $minutes = 90): ClassSession
    {
        $session = ClassSession::factory()->create([
            'course_id' => $course->id,
            'session_date' => $date,
            'start_minute' => 10 * 60,
            'end_minute' => 10 * 60 + $minutes,
        ]);

        Attendance::factory()->create(['class_session_id' => $session->id]);

        return $session;
    }

    public function test_a_teachers_hours_are_totalled_in_hours(): void
    {
        $teacher = Teacher::factory()->create();
        $course = Course::factory()->create(['teacher_id' => $teacher->id]);

        $this->heldSession($course, '2026-10-20', 60);
        $this->heldSession($course, '2026-10-22', 120);

        $response = $this->actingAs(User::factory()->create(['role' => Role::Admin]))
            ->get('/reports/teacher-hours');

        $response->assertOk();
        // ۶۰ + ۱۲۰ دقیقه = ۳ ساعت، نه تعداد جلسه‌ها
        $response->assertSee('۳٫۰۰');
        $response->assertSee($teacher->first_name);
    }

    public function test_the_shamsi_range_filter_excludes_sessions_outside_it(): void
    {
        $teacher = Teacher::factory()->create();
        $course = Course::factory()->create(['teacher_id' => $teacher->id]);

        $this->heldSession($course, '2026-10-20', 60);
        $this->heldSession($course, '2026-10-22', 60);
        // بیرون از بازهٔ شمسی خواسته‌شده
        $this->heldSession($course, '2026-12-01', 60);

        $from = jalali_date('2026-10-01');
        $to = jalali_date('2026-10-31');

        $response = $this->actingAs(User::factory()->create(['role' => Role::Admin]))
            ->get('/reports/teacher-hours?from='.urlencode($from).'&to='.urlencode($to));

        $response->assertOk();
        // فقط دو جلسهٔ داخل بازه: ۲ ساعت
        $response->assertSee('۲٫۰۰');
        $response->assertDontSee('۳٫۰۰');
    }

    public function test_a_session_with_no_attendance_was_not_held(): void
    {
        $teacher = Teacher::factory()->create();
        $course = Course::factory()->create(['teacher_id' => $teacher->id]);

        // جلسهٔ تقویمی بدون هیچ حضوری: برای حقوق حساب نمی‌شود
        ClassSession::factory()->create([
            'course_id' => $course->id,
            'session_date' => '2026-10-20',
            'start_minute' => 10 * 60,
            'end_minute' => 11 * 60 + 30,
        ]);

        $response = $this->actingAs(User::factory()->create(['role' => Role::Admin]))
            ->get('/reports/teacher-hours');

        $response->assertOk();
        $response->assertSee('۰٫۰۰');
    }

    public function test_an_undated_weekly_pattern_is_not_counted(): void
    {
        $teacher = Teacher::factory()->create();
        $course = Course::factory()->create(['teacher_id' => $teacher->id]);

        // الگوی هفتگی، تاریخ ندارد: در بازه نمی‌افتد
        ClassSession::factory()->create([
            'course_id' => $course->id,
            'session_date' => null,
            'start_minute' => 10 * 60,
            'end_minute' => 11 * 60 + 30,
        ]);

        $response = $this->actingAs(User::factory()->create(['role' => Role::Admin]))
            ->get('/reports/teacher-hours');

        $response->assertOk();
        $response->assertSee('۰٫۰۰');
    }

    public function test_only_admin_and_deputy_can_open_the_report(): void
    {
        foreach ([Role::Finance, Role::Reception, Role::Teacher] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get('/reports/teacher-hours')
                ->assertForbidden();
        }

        $this->actingAs(User::factory()->create(['role' => Role::Deputy]))
            ->get('/reports/teacher-hours')
            ->assertOk();
    }
}
