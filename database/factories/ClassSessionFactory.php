<?php

namespace Database\Factories;

use App\Enums\WeekDay;
use App\Models\ClassSession;
use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClassSession>
 */
class ClassSessionFactory extends Factory
{
    protected $model = ClassSession::class;

    public function definition(): array
    {
        // ساعت‌ها روی مرز ۱۵ دقیقه‌ای می‌افتند، مثل برنامهٔ واقعی آموزشگاه
        $start = fake()->numberBetween(8 * 4, 20 * 4) * 15;

        return [
            'course_id' => Course::factory(),

            // اول شنبه، آخر جمعه؛ میانهٔ هفته برای جلسهٔ کلاس عجیب است
            'week_day' => fake()->randomElement([
                WeekDay::Saturday, WeekDay::Sunday, WeekDay::Monday,
                WeekDay::Tuesday, WeekDay::Wednesday, WeekDay::Thursday,
            ]),

            'week_number' => fake()->numberBetween(1, 16),
            'start_minute' => $start,
            'end_minute' => $start + fake()->randomElement([60, 90, 120]),

            'room' => 'کلاس '.$start,
            'is_makeup' => false,
        ];
    }

    public function at(int $day, int $startMinute, int $endMinute, int $week = 1): static
    {
        return $this->state(fn () => [
            'week_day' => $day,
            'start_minute' => $startMinute,
            'end_minute' => $endMinute,
            'week_number' => $week,
        ]);
    }

    public function makeup(): static
    {
        return $this->state(fn () => ['is_makeup' => true]);
    }
}
