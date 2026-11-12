<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\WeekDay;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| تقویم ماهانهٔ شمسی (شماره 47)
|--------------------------------------------------------------------------
| قانون R1/R2: همه‌جا تاریخ شمسی، در پایگاه داده میلادی، و ورودی تاریخ فقط
| از jalali-date-picker. این صفحه تاریخ را تایپ نمی‌کند — فقط ماه را عوض
| می‌کند — اما پرش به یک تاریخ باید از همان کامپوننت بیاید.
*/

class ScheduleCalendarTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    /** دورهٔ الگویی با یک جلسهٔ هفتگی، از گذشته شروع شده. */
    private function course(string $title, int $weekDay, int $start, int $end, array $attributes = []): Course
    {
        $course = Course::factory()->create(array_merge([
            'title' => $title,
            'starts_at' => '2026-01-01',
        ], $attributes));

        ClassSession::factory()->at($weekDay, $start, $end)
            ->create(['course_id' => $course->id]);

        return $course;
    }

    public function test_the_page_renders_a_shamsi_month_title(): void
    {
        $this->actingAs($this->admin())
            ->get('/schedule?year=1405&month=8')
            ->assertOk()
            ->assertSee('تقویم کلاس‌ها')
            ->assertSee('آبان ۱۴۰۵');
    }

    public function test_a_weekly_class_shows_its_persian_time_range(): void
    {
        $this->course('ریاضی پایه دوازدهم', WeekDay::Saturday->value, 9 * 60, 11 * 60);

        $this->actingAs($this->admin())
            ->get('/schedule?year=1405&month=8')
            ->assertOk()
            ->assertSee('ریاضی پایه دوازدهم')
            ->assertSee('۰۹:۰۰–۱۱:۰۰');
    }

    public function test_a_dated_session_appears_on_its_own_date(): void
    {
        $course = Course::factory()->create([
            'title' => 'کارگاه شیمی ویژه',
            'starts_at' => '2026-01-01',
        ]);

        // ۱۴۰۵/۰۸/۱۵ = ۲۰۲۶/۱۱/۰۷
        ClassSession::factory()->create([
            'course_id' => $course->id,
            'session_date' => '2026-11-07',
            'week_day' => WeekDay::Saturday,
            'start_minute' => 14 * 60,
            'end_minute' => 16 * 60,
        ]);

        $this->actingAs($this->admin())
            ->get('/schedule?year=1405&month=8')
            ->assertOk()
            ->assertSee('کارگاه شیمی ویژه')
            ->assertSee('۱۴:۰۰–۱۶:۰۰');
    }

    public function test_a_course_that_has_not_started_yet_is_absent(): void
    {
        // کلاسی که از ماه دیگر شروع می‌شود نباید این ماه در تقویم دیده شود
        $this->course('زبان ترم بعد', WeekDay::Saturday->value, 9 * 60, 11 * 60, [
            'starts_at' => '2027-01-01',
        ]);

        $this->actingAs($this->admin())
            ->get('/schedule?year=1405&month=8')
            ->assertOk()
            ->assertDontSee('زبان ترم بعد');
    }

    public function test_a_course_starting_mid_month_only_counts_from_its_start_date(): void
    {
        // کلاسی که از میانهٔ ماه شروع می‌شود باید همان ماه دیده شود، نه ماه بعد
        $this->course('عربی فشرده', WeekDay::Saturday->value, 9 * 60, 11 * 60, [
            'starts_at' => '2026-11-14',
        ]);

        $this->actingAs($this->admin())
            ->get('/schedule?year=1405&month=8')
            ->assertOk()
            ->assertSee('عربی فشرده');
    }

    // ── تعارض‌ها روی تقویم ─────────────────────────────────────

    public function test_a_conflict_is_listed_with_a_persian_message(): void
    {
        $teacher = Teacher::factory()->create(['first_name' => 'علی', 'last_name' => 'رضایی']);

        $this->course('ریاضی پایه دوازدهم', WeekDay::Saturday->value, 9 * 60, 11 * 60, [
            'teacher_id' => $teacher->id,
        ]);
        $this->course('فیزیک پایه یازدهم', WeekDay::Saturday->value, 10 * 60, 12 * 60, [
            'teacher_id' => $teacher->id,
        ]);

        $this->actingAs($this->admin())
            ->get('/schedule?year=1405&month=8')
            ->assertOk()
            ->assertSee('تعارض')
            ->assertSee('علی رضایی')
            ->assertSee('ریاضی پایه دوازدهم')
            ->assertSee('فیزیک پایه یازدهم');
    }

    public function test_a_month_without_conflicts_says_so(): void
    {
        $this->actingAs($this->admin())
            ->get('/schedule?year=1405&month=8')
            ->assertOk()
            ->assertSee('تعارضی در این ماه نیست');
    }

    // ── پرش از طریق تقویم شمسی ────────────────────────────────

    public function test_picking_a_date_jumps_to_its_shamsi_month(): void
    {
        // ورودی تاریخ فقط از jalali-date-picker می‌آید (R2)؛ صفحه متن خام نمی‌گیرد
        Livewire::test('pages::schedule.index')
            ->set('year', 1405)
            ->set('month', 8)
            ->set('jump', '۱۴۰۵/۱۲/۰۱')
            ->assertSet('year', 1405)
            ->assertSet('month', 12);
    }

    public function test_an_invalid_picked_date_leaves_the_month_alone(): void
    {
        Livewire::test('pages::schedule.index')
            ->set('year', 1405)
            ->set('month', 8)
            ->set('jump', 'فردا')
            ->assertSet('year', 1405)
            ->assertSet('month', 8);
    }

    // ── جابه‌جایی ماه ──────────────────────────────────────────

    public function test_previous_month_crosses_the_year_boundary(): void
    {
        Livewire::test('pages::schedule.index')
            ->set('year', 1405)
            ->set('month', 1)
            ->call('previousMonth')
            ->assertSet('year', 1404)
            ->assertSet('month', 12);
    }

    public function test_next_month_crosses_the_year_boundary(): void
    {
        Livewire::test('pages::schedule.index')
            ->set('year', 1405)
            ->set('month', 12)
            ->call('nextMonth')
            ->assertSet('year', 1406)
            ->assertSet('month', 1);
    }

    // ── دامنهٔ دید هر نقش ─────────────────────────────────────

    public function test_a_teacher_only_sees_his_own_classes(): void
    {
        $mine = Teacher::factory()->create();
        $other = Teacher::factory()->create();

        $this->course('ریاضی پایه دوازدهم', WeekDay::Saturday->value, 9 * 60, 11 * 60, [
            'teacher_id' => $mine->id,
        ]);
        $this->course('فیزیک پایه یازدهم', WeekDay::Saturday->value, 9 * 60, 11 * 60, [
            'teacher_id' => $other->id,
        ]);

        $user = User::factory()->role(Role::Teacher)->create(['teacher_id' => $mine->id]);

        $this->actingAs($user)
            ->get('/schedule?year=1405&month=8')
            ->assertOk()
            ->assertSee('ریاضی پایه دوازدهم')
            ->assertDontSee('فیزیک پایه یازدهم');
    }

    public function test_finance_and_reception_cannot_open_the_schedule(): void
    {
        // برنامهٔ کلاس کار آن‌ها نیست و نباید از پشتِ آدرس مستقیم باز شود
        foreach ([Role::Finance, Role::Reception] as $role) {
            $this->actingAs(User::factory()->role($role)->create())
                ->get('/schedule')
                ->assertForbidden();
        }
    }

    public function test_a_guest_is_redirected_to_login(): void
    {
        $this->get('/schedule')->assertRedirect('/login');
    }
}
