<?php

namespace Tests\Feature;

use App\Models\ClassSession;
use App\Models\Course;
use App\Models\Subject;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\SubjectSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| دادهٔ نمونه باید تکرارپذیر باشد (شماره 9)
|
| db:seed روی پایگاه‌ای که قبلاً seed شده اجرا می‌شود — نصب نیمه‌کاره را کامل
| می‌کند و روی نصب سالم هیچ‌چیز نمی‌سازد. اگر seed دوم دورهٔ تازه بسازد، هر
| بار اجرا دادهٔ نمونه را دو برابر می‌کند و گزارش‌ها را از آب درمی‌آورد.
*/

class SeedIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeding_twice_does_not_duplicate_courses(): void
    {
        $this->seed(DatabaseSeeder::class);

        $courses = Course::count();
        $sessions = ClassSession::count();

        $this->assertNotSame(0, $courses, 'nothing was seeded at all');

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($courses, Course::count(), 'a second seed duplicated the courses');
        $this->assertSame($sessions, ClassSession::count(), 'a second seed duplicated the class sessions');
    }

    public function test_seeding_twice_does_not_duplicate_subjects(): void
    {
        $this->seed(DatabaseSeeder::class);

        $subjects = Subject::count();

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($subjects, Subject::count(), 'a second seed duplicated the subjects');
    }

    /**
     * نصب نیمه‌کاره باید قابل ترمیم باشد، نه اینکه با شمارش کم بسته شود.
     */
    public function test_a_partially_seeded_set_is_topped_up_not_skipped(): void
    {
        $target = (new \ReflectionClass(SubjectSeeder::class))->getConstant('COURSE_COUNT');

        Course::factory()->count(5)->create();

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($target, Course::count(), 'the seeder did not top up to its target');

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($target, Course::count(), 'the top-up overshot the target');
    }
}
