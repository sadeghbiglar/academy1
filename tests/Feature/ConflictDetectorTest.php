<?php

namespace Tests\Feature;

use App\Enums\ConflictType;
use App\Enums\WeekDay;
use App\Models\ClassSession;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\ConflictDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| تعارض‌یاب زمان‌بندی (شماره 47)
|--------------------------------------------------------------------------
| قاعده R6: تداخل استاد/کلاس/دانش‌آموز خطای عملیاتی است، نه مشکل ظاهری.
| هر سه قاعده باید با دادهٔ واقعی رد شوند — و مهم‌تر از آن، نبودِ تعارض
| هم باید درست تشخیص داده شود، وگرنه تقویم پر از خطای کاذب می‌شود و
| کاربر بعد از چند بار، هشدار واقعی را هم نادیده می‌گیرد.
*/

class ConflictDetectorTest extends TestCase
{
    use RefreshDatabase;

    /** دورهٔ «شروع‌شده» با یک جلسهٔ الگویی (بدون تاریخ مشخص). */
    private function course(
        Teacher $teacher,
        int $weekDay,
        int $start,
        int $end,
        array $session = [],
        array $attributes = [],
    ): Course {
        $course = Course::factory()->create(array_merge([
            'teacher_id' => $teacher->id,
            // تاریخ شروع باید گذشته باشد تا جلسه در ماه جاری نمایش داده شود
            'starts_at' => '2026-01-01',
        ], $attributes));

        ClassSession::factory()->at($weekDay, $start, $end)
            ->create(array_merge(['course_id' => $course->id], $session));

        return $course;
    }

    /**
     * دورهٔ بدون جلسه — برای ساختن «جلسهٔ بعداً اضافه‌شده».
     *
     * دروازهٔ ثبت‌نام جلوی تداخلِ از پیش‌تعیین‌شده را می‌گیرد؛ آنچه این سرویس
     * باید پیدا کند دقیقاً جلسه‌ای است که بعد از تأیید ساخته می‌شود.
     */
    private function courseWithoutSession(Teacher $teacher, array $attributes = []): Course
    {
        return Course::factory()->create(array_merge([
            'teacher_id' => $teacher->id,
            'starts_at' => '2026-01-01',
        ], $attributes));
    }

    private function detector(): ConflictDetector
    {
        return app(ConflictDetector::class);
    }

    /** فقط تعارض‌های یک نوع. */
    private function ofType(ConflictType $type, string $from = '2026-10-23', string $to = '2026-11-21'): array
    {
        return $this->detector()
            ->detect($from, $to)
            ->filter(fn (array $conflict) => $conflict['type'] === $type)
            ->values()
            ->all();
    }

    // ── قاعدهٔ ۱: استاد در دو کلاس همزمان ──────────────────────

    public function test_a_teacher_in_two_classes_at_the_same_time_is_reported(): void
    {
        $teacher = Teacher::factory()->create(['first_name' => 'علی', 'last_name' => 'رضایی']);

        $this->course($teacher, WeekDay::Saturday->value, 9 * 60, 11 * 60);
        $this->course($teacher, WeekDay::Saturday->value, 10 * 60, 12 * 60);

        $conflicts = $this->ofType(ConflictType::Teacher);

        $this->assertCount(1, $conflicts);
        $this->assertStringContainsString('علی رضایی', $conflicts[0]['message']);
        // پیام بدون نام هر دو دوره برای مدیر بی‌فایده است
        $this->assertStringContainsString($conflicts[0]['left']->course->title, $conflicts[0]['message']);
        $this->assertStringContainsString($conflicts[0]['right']->course->title, $conflicts[0]['message']);
    }

    public function test_the_same_time_on_different_days_is_not_a_teacher_conflict(): void
    {
        $teacher = Teacher::factory()->create();

        $this->course($teacher, WeekDay::Saturday->value, 9 * 60, 11 * 60);
        $this->course($teacher, WeekDay::Sunday->value, 9 * 60, 11 * 60);

        $this->assertSame([], $this->ofType(ConflictType::Teacher));
    }

    public function test_two_teachers_in_the_same_slot_are_not_a_teacher_conflict(): void
    {
        // هم‌زمانی دو استادِ متفاوت عادی است؛ فقط وقتی یک نفر دو جا باشد خطا است
        $this->course(Teacher::factory()->create(), WeekDay::Saturday->value, 9 * 60, 11 * 60);
        $this->course(Teacher::factory()->create(), WeekDay::Saturday->value, 9 * 60, 11 * 60);

        $this->assertSame([], $this->ofType(ConflictType::Teacher));
    }

    // ── قاعدهٔ ۲: یک کلاس در دو مکان همزمان ────────────────────

    public function test_one_room_hosting_two_classes_at_once_is_reported(): void
    {
        $session = ['room' => 'کلاس ۱'];

        $this->course(Teacher::factory()->create(), WeekDay::Monday->value, 16 * 60, 18 * 60, $session);
        $this->course(Teacher::factory()->create(), WeekDay::Monday->value, 17 * 60, 19 * 60, $session);

        $conflicts = $this->ofType(ConflictType::Room);

        $this->assertCount(1, $conflicts);
        $this->assertStringContainsString('کلاس ۱', $conflicts[0]['message']);
    }

    public function test_two_rooms_used_at_once_are_not_a_room_conflict(): void
    {
        $this->course(Teacher::factory()->create(), WeekDay::Monday->value, 16 * 60, 18 * 60, ['room' => 'کلاس ۱']);
        $this->course(Teacher::factory()->create(), WeekDay::Monday->value, 17 * 60, 19 * 60, ['room' => 'کلاس ۲']);

        $this->assertSame([], $this->ofType(ConflictType::Room));
    }

    public function test_a_session_without_a_room_is_never_a_room_conflict(): void
    {
        // اتاقِ خالی یعنی «مکان مشخص نشده»، نه «اتاقی به نام خالی»
        $this->course(Teacher::factory()->create(), WeekDay::Monday->value, 16 * 60, 18 * 60, ['room' => null]);
        $this->course(Teacher::factory()->create(), WeekDay::Monday->value, 17 * 60, 19 * 60, ['room' => null]);

        $this->assertSame([], $this->ofType(ConflictType::Room));
    }

    // ── قاعدهٔ ۳: دانش‌آموز در دو دوره همزمان ──────────────────

    public function test_a_student_in_two_overlapping_courses_is_reported(): void
    {
        $student = Student::factory()->create(['first_name' => 'مریم', 'last_name' => 'قاسمی']);

        $first = $this->course(Teacher::factory()->create(), WeekDay::Saturday->value, 9 * 60, 11 * 60);
        $second = $this->courseWithoutSession(Teacher::factory()->create());

        // ثبت‌نام اول تأیید می‌شود، بعد جلسهٔ هم‌زمانِ دورهٔ دوم ساخته می‌شود:
        // دقیقاً همان حالتی که دروازهٔ ثبت‌نام نمی‌بیند و باید سرویس پیدا کند
        CourseEnrollment::enroll($student, $first)->confirm();
        CourseEnrollment::enroll($student, $second)->confirm();

        ClassSession::factory()->at(WeekDay::Saturday->value, 9 * 60, 11 * 60)
            ->create(['course_id' => $second->id]);

        $conflicts = $this->ofType(ConflictType::Student);

        $this->assertCount(1, $conflicts);
        $this->assertStringContainsString('مریم قاسمی', $conflicts[0]['message']);
        $this->assertSame($student->id, $conflicts[0]['student']->id);
    }

    public function test_a_cancelled_enrollment_is_not_a_student_conflict(): void
    {
        $student = Student::factory()->create();

        $first = $this->course(Teacher::factory()->create(), WeekDay::Saturday->value, 9 * 60, 11 * 60);
        $second = $this->courseWithoutSession(Teacher::factory()->create());

        CourseEnrollment::enroll($student, $first)->confirm();
        CourseEnrollment::enroll($student, $second)->confirm()->cancel();

        ClassSession::factory()->at(WeekDay::Saturday->value, 9 * 60, 11 * 60)
            ->create(['course_id' => $second->id]);

        $this->assertSame([], $this->ofType(ConflictType::Student));
    }

    public function test_a_pending_enrollment_is_not_a_student_conflict(): void
    {
        $student = Student::factory()->create();

        $first = $this->course(Teacher::factory()->create(), WeekDay::Saturday->value, 9 * 60, 11 * 60);
        $second = $this->courseWithoutSession(Teacher::factory()->create());

        CourseEnrollment::enroll($student, $first)->confirm();
        CourseEnrollment::enroll($student, $second);

        ClassSession::factory()->at(WeekDay::Saturday->value, 9 * 60, 11 * 60)
            ->create(['course_id' => $second->id]);

        $this->assertSame([], $this->ofType(ConflictType::Student));
    }

    public function test_two_students_in_separate_courses_are_not_a_student_conflict(): void
    {
        // تداخل بین دو دانش‌آموز یک مشکل تقویم نیست و نباید مانع شود
        $first = $this->course(Teacher::factory()->create(), WeekDay::Saturday->value, 9 * 60, 11 * 60);
        $second = $this->courseWithoutSession(Teacher::factory()->create());

        CourseEnrollment::enroll(Student::factory()->create(), $first)->confirm();
        CourseEnrollment::enroll(Student::factory()->create(), $second)->confirm();

        ClassSession::factory()->at(WeekDay::Saturday->value, 9 * 60, 11 * 60)
            ->create(['course_id' => $second->id]);

        $this->assertSame([], $this->ofType(ConflictType::Student));
    }

    // ── بازهٔ گزارش ─────────────────────────────────────────────

    public function test_a_dated_session_only_conflicts_on_its_own_date(): void
    {
        // جلسهٔ تاریخ‌دار فقط همان یک روز اتفاق می‌افتد؛ جلسهٔ الگویی هر هفته
        $teacher = Teacher::factory()->create();

        $pattern = $this->course($teacher, WeekDay::Saturday->value, 9 * 60, 11 * 60);
        $dated = $this->course($teacher, WeekDay::Saturday->value, 9 * 60, 11 * 60, [
            // ۱۴۰۵/۰۹/۰۶ = شنبهٔ آذر؛ در آبان اصلاً اتفاق نمی‌افتد
            'session_date' => '2026-11-28',
        ]);

        $this->assertSame([], $this->ofType(ConflictType::Teacher));

        // همان جلسه داخل بازهٔ خودش دیده می‌شود — فقط در همان یک تاریخ
        $inNovember = $this->detector()->detect('2026-11-22', '2026-12-21')
            ->filter(fn (array $conflict) => $conflict['type'] === ConflictType::Teacher)
            ->all();

        $this->assertCount(1, $inNovember);
        $this->assertContains($dated->id, [$inNovember[0]['left']->course->id, $inNovember[0]['right']->course->id]);
        $this->assertContains($pattern->id, [$inNovember[0]['left']->course->id, $inNovember[0]['right']->course->id]);
    }

    public function test_the_detector_returns_no_conflicts_for_an_empty_month(): void
    {
        $this->assertSame(0, $this->detector()->detect('2027-01-01', '2027-01-31')->count());
    }
}
