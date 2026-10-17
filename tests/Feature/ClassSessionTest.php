<?php

namespace Tests\Feature;

use App\Enums\WeekDay;
use App\Models\ClassSession;
use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| زمان‌بندی کلاس (شماره 46)
*/

class ClassSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_session_is_created_for_a_course(): void
    {
        $course = Course::factory()->create();

        $session = ClassSession::create([
            'course_id' => $course->id,
            'week_number' => 3,
            'week_day' => WeekDay::Saturday,
            'start_minute' => 16 * 60 + 30,
            'end_minute' => 18 * 60,
            'room' => 'کلاس ۳',
        ]);

        $this->assertModelExists($session);
        $this->assertSame(WeekDay::Saturday, $session->week_day);
        $this->assertSame(90, $session->durationMinutes());
    }

    public function test_the_end_must_be_after_the_start(): void
    {
        $this->expectException(ValidationException::class);

        ClassSession::factory()->create([
            'start_minute' => 18 * 60,
            'end_minute' => 16 * 60,
        ]);
    }

    public function test_a_zero_length_session_is_rejected(): void
    {
        // جلسهٔ بدون طول یعنی رکوردی که در تقویم دیده می‌شود ولی زمانی ندارد
        $this->expectException(ValidationException::class);

        ClassSession::factory()->create([
            'start_minute' => 16 * 60,
            'end_minute' => 16 * 60,
        ]);
    }

    public function test_an_end_one_minute_after_the_start_is_allowed(): void
    {
        $session = ClassSession::factory()->create([
            'start_minute' => 16 * 60,
            'end_minute' => 16 * 60 + 1,
        ]);

        $this->assertSame(1, $session->durationMinutes());
    }

    public function test_an_hour_outside_the_day_is_rejected(): void
    {
        $this->expectException(ValidationException::class);

        ClassSession::factory()->create(['start_minute' => 25 * 60]);
    }

    public function test_the_week_number_must_be_in_range(): void
    {
        $this->expectException(ValidationException::class);

        ClassSession::factory()->create(['week_number' => 54]);
    }

    public function test_the_time_is_shown_as_a_label(): void
    {
        $session = ClassSession::factory()->create([
            'start_minute' => 16 * 60 + 30,
            'end_minute' => 18 * 60,
        ]);

        $this->assertSame('16:30', $session->startTimeLabel());
        $this->assertSame('18:00', $session->endTimeLabel());
        $this->assertSame('16:30–18:00', $session->timeRangeLabel());
    }

    public function test_the_day_is_shown_in_persian(): void
    {
        $session = ClassSession::factory()->create(['week_day' => WeekDay::Wednesday]);

        $this->assertSame('چهارشنبه', $session->dayLabel());
    }

    public function test_the_session_date_is_stored_gregorian_and_shown_jalali(): void
    {
        $session = ClassSession::factory()->create(['session_date' => '2026-11-01']);

        $this->assertSame('2026-11-01', $session->fresh()->session_date->format('Y-m-d'));
        $this->assertSame('1405/08/10', $session->sessionDateJalali());
    }

    public function test_a_session_without_a_date_has_no_displayed_date(): void
    {
        // الگوی هفتگی تاریخ ندارد؛ جلسهٔ ثبت‌شده دارد
        $this->assertNull(ClassSession::factory()->create()->sessionDateJalali());
    }

    public function test_overlapping_sessions_are_detected(): void
    {
        $a = ClassSession::factory()->at(WeekDay::Saturday->value, 16 * 60, 18 * 60)->create();
        $b = ClassSession::factory()->at(WeekDay::Saturday->value, 17 * 60, 19 * 60)->create();

        $this->assertTrue($a->overlapsWith($b));
        $this->assertTrue($b->overlapsWith($a));
    }

    public function test_sessions_that_touch_at_one_instant_overlap(): void
    {
        // جلسهٔ ۱۶–۱۸ و ۱۸–۱۹ یک لحظه مشترک دارند
        $a = ClassSession::factory()->at(WeekDay::Saturday->value, 16 * 60, 18 * 60)->create();
        $b = ClassSession::factory()->at(WeekDay::Saturday->value, 18 * 60, 19 * 60)->create();

        $this->assertTrue($a->overlapsWith($b));
    }

    public function test_separated_sessions_do_not_overlap(): void
    {
        $a = ClassSession::factory()->at(WeekDay::Saturday->value, 16 * 60, 18 * 60)->create();
        $b = ClassSession::factory()->at(WeekDay::Saturday->value, 18 * 60 + 1, 19 * 60)->create();

        $this->assertFalse($a->overlapsWith($b));
    }

    public function test_the_same_time_on_another_day_does_not_overlap(): void
    {
        $a = ClassSession::factory()->at(WeekDay::Saturday->value, 16 * 60, 18 * 60)->create();
        $b = ClassSession::factory()->at(WeekDay::Sunday->value, 16 * 60, 18 * 60)->create();

        $this->assertFalse($a->overlapsWith($b));
    }

    public function test_the_same_time_in_another_week_does_not_overlap(): void
    {
        $a = ClassSession::factory()->at(WeekDay::Saturday->value, 16 * 60, 18 * 60, 1)->create();
        $b = ClassSession::factory()->at(WeekDay::Saturday->value, 16 * 60, 18 * 60, 2)->create();

        $this->assertFalse($a->overlapsWith($b));
    }

    public function test_a_course_can_have_several_sessions_a_week(): void
    {
        $course = Course::factory()->create();

        ClassSession::factory()->at(WeekDay::Saturday->value, 9 * 60, 11 * 60)->create(['course_id' => $course->id]);
        ClassSession::factory()->at(WeekDay::Tuesday->value, 16 * 60, 18 * 60)->create(['course_id' => $course->id]);

        $this->assertCount(2, $course->sessions);
        $this->assertCount(2, $course->weeklySchedule(1));
    }

    public function test_the_weekly_schedule_is_sorted_by_day_then_time(): void
    {
        // ترتیب نامشخص یعنی تقویم به‌هم‌ریخته
        $course = Course::factory()->create();

        ClassSession::factory()->at(WeekDay::Tuesday->value, 9 * 60, 11 * 60)->create(['course_id' => $course->id]);
        ClassSession::factory()->at(WeekDay::Saturday->value, 16 * 60, 18 * 60)->create(['course_id' => $course->id]);
        ClassSession::factory()->at(WeekDay::Saturday->value, 9 * 60, 11 * 60)->create(['course_id' => $course->id]);

        $schedule = $course->weeklySchedule(1);

        $this->assertSame(
            [WeekDay::Saturday, WeekDay::Saturday, WeekDay::Tuesday],
            $schedule->pluck('week_day')->all(),
        );
        $this->assertSame(9 * 60, $schedule[0]->start_minute);
    }

    public function test_the_weekly_schedule_of_another_week_is_empty(): void
    {
        $course = Course::factory()->create();
        ClassSession::factory()->at(WeekDay::Saturday->value, 9 * 60, 11 * 60, 1)->create(['course_id' => $course->id]);

        $this->assertCount(0, $course->weeklySchedule(2));
    }

    public function test_a_makeup_session_is_flagged(): void
    {
        $session = ClassSession::factory()->makeup()->create();

        $this->assertTrue($session->is_makeup);
        $this->assertFalse(ClassSession::factory()->create()->is_makeup);
    }

    public function test_deleting_a_course_removes_its_sessions(): void
    {
        $course = Course::factory()->create();
        ClassSession::factory()->count(2)->create(['course_id' => $course->id]);

        $course->delete();

        $this->assertSame(0, ClassSession::query()->count());
    }

    public function test_week_day_maps_the_persian_week_correctly(): void
    {
        // PHP روز را ISO می‌دهد (شنبه = 6)؛ تقویم فارسی شنبه = 1 است
        $this->assertSame(WeekDay::Saturday, WeekDay::fromDate(new \DateTimeImmutable('2026-10-17')));
        $this->assertSame(WeekDay::Sunday, WeekDay::fromDate(new \DateTimeImmutable('2026-10-18')));
        $this->assertSame(WeekDay::Friday, WeekDay::fromDate(new \DateTimeImmutable('2026-10-23')));
    }

    public function test_every_week_day_has_a_persian_label(): void
    {
        foreach (WeekDay::cases() as $day) {
            $this->assertNotSame('', $day->label());
            $this->assertSame(1, mb_strlen($day->shortLabel()));
        }

        $this->assertSame(7, count(WeekDay::cases()));
        $this->assertSame('—', WeekDay::labelFor(null));
    }
}
