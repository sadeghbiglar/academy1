<?php

namespace Database\Factories;

use App\Enums\CourseStatus;
use App\Models\Course;
use App\Models\Subject;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    protected $model = Course::class;

    public function definition(): array
    {
        $duration = fake()->randomElement([60, 90, 120]);

        return [
            'title' => fake()->randomElement([
                'دورهٔ شب امتحان ریاضی',
                'دورهٔ جمع‌بندی فیزیک',
                'آموزش زبان پایه',
                'کارگاه شیمی',
                'دورهٔ فشردهٔ عربی',
            ]).' ترم '.fake()->numberBetween(1, 4),

            // تا وقتی رکورد موجودی هست، به همان وصل می‌شود. اگر همیشه factory
            // جدید بسازد، هر دوره یک درس و یک استاد تازه می‌آورد و داده نمونه
            // پر از رکورد یک‌بارمصرف و بی‌ربط می‌شود
            'subject_id' => Subject::query()->inRandomOrder()->value('id') ?? Subject::factory(),
            'teacher_id' => Teacher::query()->inRandomOrder()->value('id') ?? Teacher::factory(),

            'capacity' => fake()->numberBetween(5, 25),
            'session_count' => fake()->numberBetween(8, 40),
            'session_duration' => $duration,

            // شهریه به ریال
            'base_fee' => fake()->numberBetween(5_000_000, 40_000_000),

            'status' => CourseStatus::Open,
            'starts_at' => fake()->dateTimeBetween('-2 months', '+3 months'),
        ];
    }

    public function status(CourseStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }

    public function closed(): static
    {
        return $this->status(CourseStatus::Closed);
    }

    public function archived(): static
    {
        return $this->status(CourseStatus::Archived);
    }

    /** دورهٔ بدون استاد — مجاز است، استاد بعداً تعیین می‌شود. */
    public function withoutTeacher(): static
    {
        return $this->state(fn () => ['teacher_id' => null]);
    }
}
