<?php

namespace Database\Factories;

use App\Models\Teacher;
use App\Models\TeacherExperience;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeacherExperience>
 */
class TeacherExperienceFactory extends Factory
{
    protected $model = TeacherExperience::class;

    public function definition(): array
    {
        $started = fake()->dateTimeBetween('-18 years', '-1 year');

        return [
            'teacher_id' => Teacher::factory(),
            'title' => fake()->randomElement(['دبیر', 'مدرس', 'دبیر ارشد', 'کارشناس ارشد', 'مدرس آزاد']),
            'organization' => fake()->randomElement([
                'دبیرستان شاداب', 'دبیرستان ایران‌دبیر', 'آموزشگاه نبوغ', 'دانشگاه آزاد',
            ]),
            'started_at' => $started->format('Y-m-d'),
            'ended_at' => fake()->dateTimeBetween($started, '+3 years')->format('Y-m-d'),
        ];
    }

    /** سابقه‌ای که هنوز تمام نشده — ended_at باید null باشد، نه یک تاریخ ساختگی. */
    public function current(): static
    {
        return $this->state(fn () => ['ended_at' => null]);
    }
}
